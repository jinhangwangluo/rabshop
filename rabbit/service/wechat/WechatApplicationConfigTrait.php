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

namespace Rabbit\service\wechat;

use EasyWeChat\MiniApp\Application as MiniAppApplication;
use EasyWeChat\OfficialAccount\Application as OfficialAccountApplication;
use EasyWeChat\Pay\Application as PayApplication;
use Rabbit\service\wechat\http\SwooleHttpClient;
use Swoole\Coroutine;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/**
 * EasyWeChat Application 通用配置 Trait。
 *
 * 统一为公众号、小程序、微信支付应用注入当前请求、日志和缓存；仅在 Swoole 协程环境下
 * 注入 Swoole 兼容 HTTP Client，非 Swoole 环境保持 EasyWeChat 原始请求实现。
 */
trait WechatApplicationConfigTrait
{
    /**
     * 当前微信应用名称，用于区分缓存命名空间。
     */
    protected string $name = 'wechat';

    /**
     * 配置 EasyWeChat 应用实例。
     *
     * @param MiniAppApplication|OfficialAccountApplication|PayApplication $application EasyWeChat 应用实例。
     * @return void
     */
    protected function configureApplication(
        MiniAppApplication|OfficialAccountApplication|PayApplication $application
    ): void {
        $this->setRequest($application);
        $this->setLogger($application);
        if ($this->isSwooleCoroutine()) {
            $this->setHttpClient($application);
        }

        if ($application instanceof MiniAppApplication || $application instanceof OfficialAccountApplication) {
            $this->setCache($application);
        }
    }

    /**
     * 将 ThinkPHP 当前请求转换为 Symfony Request 并注入 EasyWeChat。
     *
     * @param MiniAppApplication|OfficialAccountApplication|PayApplication $application EasyWeChat 应用实例。
     * @return void
     */
    protected function setRequest(MiniAppApplication|OfficialAccountApplication|PayApplication $application): void
    {
        $request = request();
        $symfonyRequest = new SymfonyRequest(
            $request->get(),
            $request->post(),
            [],
            $request->cookie(),
            [],
            $request->server(),
            $request->getContent()
        );
        $symfonyRequest->headers = new HeaderBag($request->header());
        $application->setRequestFromSymfonyRequest($symfonyRequest);
    }

    /**
     * 注入项目日志实例。
     *
     * @param MiniAppApplication|OfficialAccountApplication|PayApplication $application EasyWeChat 应用实例。
     * @return void
     */
    protected function setLogger(MiniAppApplication|OfficialAccountApplication|PayApplication $application): void
    {
        $application->setLogger(app()->log);
    }

    /**
     * 注入 EasyWeChat 缓存。
     *
     * @param MiniAppApplication|OfficialAccountApplication $application 支持缓存的 EasyWeChat 应用实例。
     * @return void
     */
    protected function setCache(MiniAppApplication|OfficialAccountApplication $application): void
    {
        $application
            ->setCache(new Psr16Cache(new FilesystemAdapter('pro_' . $this->name, 1500)))
            ->setCacheNamespace('pro_' . $this->name);
    }

    /**
     * 判断当前是否运行在 Swoole 协程环境。
     *
     * @return bool 是 Swoole 协程环境时返回 true。
     */
    protected function isSwooleCoroutine(): bool
    {
        return class_exists(Coroutine::class) && Coroutine::getCid() > 0;
    }

    /**
     * 注入 Swoole 兼容 HTTP Client，仅应在 Swoole 协程环境下调用。
     *
     * @param MiniAppApplication|OfficialAccountApplication|PayApplication $application EasyWeChat 应用实例。
     * @param string $baseUrl 默认基础请求地址。
     * @return void
     */
    protected function setHttpClient(
        MiniAppApplication|OfficialAccountApplication|PayApplication $application,
        string $baseUrl = 'https://api.weixin.qq.com/'
    ): void {
        if ($application instanceof PayApplication) {
            $baseUrl = 'https://api.mch.weixin.qq.com/';
        }

        $application->setHttpClient(new SwooleHttpClient([
            'base_uri' => $baseUrl,
            'timeout' => 8.0,
            'max_duration' => 8.0,
        ]));
    }
}
