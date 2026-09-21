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

declare(strict_types=1);

namespace app\common\service\plugin;

use app\common\model\plugin\Plugin;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\exception\AdminResponseException;

/**
 * 提供不含配置和授权敏感信息的运行状态投影，清单不能启用未安装源码。
 */
class PluginRuntimeService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 指定运行状态读取使用的插件模型。
     *
     * @return string 插件模型完整类名。
     */
    protected function setModel(): string
    {
        return Plugin::class;
    }

    /**
     * 分批读取插件安装身份与持久化启用状态，供编译后的插件入口判断可用性。
     *
     * @return array{plugins:array<int,array<string,mixed>>,server_time:int} 公开插件运行状态列表和服务器时间。
     */
    public function read(): array
    {
        $plugins = [];
        $last = 0;
        do {
            $rows = $this->getModel()->where('id', '>', $last)->field('id,code,enabled,lifecycle_status,manifest_json')->order('id')->limit(200)->select();
            foreach ($rows as $row) {
                $last = (int)$row['id'];
                $manifest = $row['manifest_json'];
                if (is_string($manifest)) {
                    $manifest = json_decode($manifest, true);
                }
                $generation = is_array($manifest) ? (string)($manifest['installation_id'] ?? '') : '';
                $plugins[] = ['code' => (string)$row['code'], 'enabled' => (int)$row['enabled'] === 1 && $row['lifecycle_status'] === 'active',
                    'lifecycle_status' => (string)$row['lifecycle_status'], 'installation_id' => $generation, 'install_generation' => $generation,
                    'frontend_entry' => $this->frontendEntry((string)$row['code'], is_array($manifest) ? $manifest : [])];
            }
        } while (count($rows) === 200 && count($plugins) < 10000);
        return ['plugins' => $plugins, 'server_time' => time()];
    }

    /**
     * 从已安装资源声明取得第一个前台页面，不暴露完整清单或接受外部跳转地址。
     *
     * @param string $code 插件编码。
     * @param array $manifest 已安装插件清单。
     * @return string 同站前台页面路径；未声明有效页面时为空。
     */
    private function frontendEntry(string $code, array $manifest): string
    {
        foreach ($manifest['install_resources']['mobile_pages'] ?? [] as $page) {
            $path = $page['path'] ?? '';
            if (is_string($path) && preg_match('#^plugins/' . preg_quote($code, '#') . '/pages/[a-z0-9_/]+$#D', $path)) {
                return '/' . $path . '?install_generation=' . rawurlencode((string)($manifest['installation_id'] ?? ''));
            }
        }
        return '';
    }

    /**
     * 按原生路由前缀确定的编码复查持久化安装和启用状态。
     *
     * @param string $code 由固定原生插件路由解析的插件编码。
     * @return void 无返回值；未安装或已停用时抛出异常。
     */
    public function assertEnabled(string $code): void
    {
        if (!$this->isEnabled($code)) {
            throw new AdminResponseException('插件尚未安装或已停用');
        }
    }

    /**
     * 读取插件是否处于可运行状态，不抛出业务异常，供停用存量路由选择门禁分支。
     *
     * @param string $code 固定原生插件路由解析出的编码。
     * @return bool 插件已安装、启用且处于 active 生命周期时为 true。
     */
    public function isEnabled(string $code): bool
    {
        return preg_match('/^[a-z][a-z0-9_]{0,63}$/', $code) === 1
            && (bool)$this->getModel()->where([
                'code' => $code,
                'enabled' => 1,
                'lifecycle_status' => 'active',
            ])->find();
    }

    /**
     * 只准已安装但停用的酒店访问存量订单证据，不以启用状态判断既有售后。
     *
     * @param string $code 固定插件路由解析出的编码。
     * @return void 无返回值；没有安装身份时拒绝。
     */
    public function assertInstalledHotelEvidence(string $code): void
    {
        if ($code !== 'hotel') {
            throw new AdminResponseException('插件已停用');
        }
        $plugin = $this->getModel()->where('code', $code)->where('lifecycle_status', 'disabled')->find();
        $manifest = $plugin ? $plugin['manifest_json'] : null;
        if (is_string($manifest)) {
            $manifest = json_decode($manifest, true);
        }
        if (!$plugin || (int)$plugin['enabled'] !== 0 || !is_array($manifest)
            || !preg_match('/^[a-f0-9]{32}$/', (string)($manifest['installation_id'] ?? ''))) {
            throw new AdminResponseException('酒店插件没有可访问的存量安装身份');
        }
    }
}
