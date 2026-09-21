<?php
// +----------------------------------------------------------------------
// | Rabbit多商户商城系统
// +----------------------------------------------------------------------
// | Copyright (c) 2022~2026 https://www.rabshop.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed Rabbit多商户商城系统不是自由软件，未经许可不能去掉Rabbit相关版权
// +----------------------------------------------------------------------
// | Author: Rabbit Team <admin@rabshop.cn>
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace app;

use app\common\exception\MissingAppKeyException;
use app\common\enum\WechatConfigKeyEnum;
use app\common\listener\OfficialAccount as OfficialAccountListener;
use app\common\plugin\PluginRegistry;
use app\common\plugin\event\PluginEventBus;
use app\common\service\cs\AiEmbeddingRuntimeConfig;
use app\common\service\plugin\DeveloperPluginLifecycleService;
use app\common\service\plugin\PluginBusinessEventService;
use app\common\service\plugin\PluginEventLogService;
use app\common\service\plugin\PluginEventOutboxService;
use app\common\service\plugin\PluginRegistryBootstrap;
use app\common\service\plugin\PluginRouteRegistrar;
use app\common\service\setting\ConfigService;
use Rabbit\service\ai\chat\ChatService;
use Rabbit\service\ai\embedding\EmbeddingService;
use Rabbit\service\collector\CollectorService;
use Rabbit\service\express\ExpressService;
use Rabbit\service\live\LiveService;
use Rabbit\service\payment\PaymentService;
use Rabbit\service\schedule\SwooleScheduleProcess;
use Rabbit\service\schedule\Timer;
use Rabbit\service\uploader\UploaderService;
use Rabbit\service\wechat\OfficialAccount;
use Rabbit\util\Encrypter;
use Rabbit\util\Json;
use think\helper\Str;
use think\Service;
use think\swoole\Manager;

/**
 * 应用服务类
 */
class AppService extends Service
{

    /**
     * 初始化应用使用的目录分隔常量。
     *
     * @return void 无返回值，完成应用基础常量初始化。
     */
    public function boot()
    {
        defined('DS') || define('DS', DIRECTORY_SEPARATOR);
    }

    /**
     * 注册服务
     *
     * @return void 无返回值，服务绑定交由应用容器使用。
     */
    public function register()
    {
        //注册请求过滤
        $this->app->request->filter('trim');

        //绑定解密加密
        $this->app->bind(Encrypter::class, function () {
            $config = $this->app->make('config')->get('app');
            $cipher = (string)($config['cipher'] ?? 'AES-256-CBC');
            return new Encrypter($this->parseKey($config), $cipher);
        });
        //绑定上传
        $this->app->bind('uploader', function () {
            return (new UploaderService)->setConfigServe(new ConfigService());
        });
        //绑定支付
        $this->app->bind('payment', function () {
            return (new PaymentService())->setConfigServe(new ConfigService());
        });
        $this->app->bind(PaymentService::class, function () {
            return (new PaymentService())->setConfigServe(new ConfigService());
        });
        //绑定直播
        $this->app->bind(LiveService::class, function () {
            return (new LiveService)->setConfigServe(new ConfigService());
        });
        // 绑定商品采集服务
        $this->app->bind(CollectorService::class, function () {
            return (new CollectorService)->setConfigServe(new ConfigService());
        });
        //绑定向量服务（默认运行时来自AI模型配置表）
        $this->app->bind(EmbeddingService::class, function () {
            return (new EmbeddingService)->setConfigServe(app()->make(AiEmbeddingRuntimeConfig::class));
        });
        //绑定对话服务
        $this->app->bind(ChatService::class, function () {
            return (new ChatService)->setConfigServe(new ConfigService());
        });
        //绑定返回json
        $this->app->bind('json', function () {
            return new Json;
        });

        // 避免容器将已解析的市场生命周期实例注入开发服务的可选构造参数。
        $this->app->bind(DeveloperPluginLifecycleService::class, function (): DeveloperPluginLifecycleService {
            return new DeveloperPluginLifecycleService();
        });

        $pluginRegistry = new PluginRegistry();
        $pluginOutbox = new PluginEventOutboxService();
        $pluginLogs = new PluginEventLogService();
        $pluginEventBus = new PluginEventBus($pluginRegistry, $pluginOutbox, $pluginLogs);
        $pluginBootstrap = new PluginRegistryBootstrap();
        $pluginRouteRegistrar = new PluginRouteRegistrar();

        $this->app->instance(PluginRegistry::class, $pluginRegistry);
        $this->app->instance(PluginEventOutboxService::class, $pluginOutbox);
        $this->app->instance(PluginEventLogService::class, $pluginLogs);
        $this->app->instance(PluginEventBus::class, $pluginEventBus);
        $this->app->instance(PluginBusinessEventService::class, new PluginBusinessEventService($pluginEventBus));
        $this->app->instance(PluginRegistryBootstrap::class, $pluginBootstrap);
        $this->app->instance(PluginRouteRegistrar::class, $pluginRouteRegistrar);
        if ((bool)config('plugin.enabled', true)) {
            $manifestRoot = (string)config('plugin.registry.manifest_path', root_path('plugins'));
            $pluginBootstrap->load(
                $pluginRegistry,
                $manifestRoot
            );
            $this->registerRoutes(function () use ($pluginRegistry, $pluginRouteRegistrar, $manifestRoot): void {
                $pluginRouteRegistrar->register(
                    $pluginRegistry,
                    $manifestRoot
                );
            });
        }

        // 扩展点注册表按插件运行态重建：安装/停用插件后每个请求立即生效
        $plugins = $pluginRegistry->allPlugins();
        PaymentService::syncFromPlugins($plugins);
        UploaderService::syncFromPlugins($plugins);
        CollectorService::syncFromPlugins($plugins);
        LiveService::syncFromPlugins($plugins);
        ExpressService::syncFromPlugins($plugins);

        //绑定定时器
        $this->app->bind('timer', function () {
            return $this->app->make(Timer::class);
        });
        //绑定 Swoole 定时任务独立进程
        $this->app->bind(Manager::class, function () {
            $manager = new Manager($this->app);
            $manager->addWorker(static function (): void {
                app()->make(SwooleScheduleProcess::class)->start();
            }, 'schedule timer');

            return $manager;
        });

        //绑定微信公众号回调事件
        $this->app->bind(OfficialAccount::class, function () {
            return (new OfficialAccount())
                ->setConfig(fn () => $this->getOfficialAccountConfig())
                ->setWithHandler(OfficialAccountListener::class);
        });

    }

    /**
     * 获取微信公众号运行时配置
     *
     * @return array 微信公众号运行时所需的应用、验证令牌及消息解密配置。
     */
    protected function getOfficialAccountConfig(): array
    {
        return [
            'app_id'  => db_config(WechatConfigKeyEnum::APPID),
            'secret'  => db_config(WechatConfigKeyEnum::SECRET),
            'token'   => db_config(WechatConfigKeyEnum::TOKEN),
            'aes_key' => db_config(WechatConfigKeyEnum::AES_KEY),
        ];
    }

    /**
     * 解析应用包装密钥，兼容编码后的密钥配置。
     *
     * @param array $config 应用加密服务使用的密钥配置。
     * @return false|mixed|string 原始应用密钥，或带编码前缀密钥的解码结果；本方法不校验密钥格式。
     * @throws MissingAppKeyException
     */
    protected function parseKey(array $config)
    {
        if (Str::startsWith($key = $this->key($config), $prefix = 'base64:')) {
            $key = base64_decode(\Rabbit\util\Str::after($key, $prefix));
        }

        return $key;
    }

    /**
     * 读取应用密钥，缺失时阻止初始化加密服务。
     *
     * @param array $config 应用加密服务使用的密钥配置。
     * @return mixed 配置中存在的应用包装密钥。
     * @throws MissingAppKeyException
     */
    protected function key(array $config)
    {
        $key = $config['key'] ?? $config['app_key'] ?? null;
        return tap($key, function ($key) {
            if (empty($key)) {
                throw new MissingAppKeyException('Missing app key');
            }
        });
    }

}
