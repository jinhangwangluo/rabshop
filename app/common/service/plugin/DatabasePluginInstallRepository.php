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

use app\common\exception\PluginLifecycleException;
use app\common\model\plugin\Plugin;
use app\common\model\plugin\PluginMigration;
use app\common\model\plugin\PluginResource;
use app\common\model\setting\system\Menu;
use app\common\model\setting\system\AdminRole;
use app\common\model\diy\DiyPage;
use app\common\model\diy\DiyPageLink;
use app\common\model\diy\DiyComponent;
use think\facade\Db;

/**
 * 负责插件自有菜单、移动页面及装修资源的持久化与清理，隔离宿主数据操作。
 * 每次解析独立模型，避免已保存实例覆盖下一条迁移或资源归属记录。
 */
class DatabasePluginInstallRepository
{
    /**
     * 锁定插件身份和启用状态后，在同一事务内执行资源变更。
     *
     * @param string $code 插件编码。
     * @param callable $callback 接收插件记录并执行业务清理的回调。
     * @return mixed 回调返回的业务结果。
     */
    public function locked(string $code, callable $callback): mixed
    {
        return Db::transaction(function () use ($code, $callback) {
            $plugin = app()->make(Plugin::class, [], true)->where('code', $code)->lock(true)->find();
            if (!$plugin) {
                throw new PluginLifecycleException('插件不存在', 'PLUGIN_NOT_FOUND');
            }
            return $callback($plugin->toArray());
        });
    }

    /**
     * 查询指定插件迁移的执行账本。
     *
     * @param string $code 插件编码。
     * @param string $name 迁移名称。
     * @return array<string,mixed>|null 迁移记录；未登记时为 null。
     */
    public function migration(string $code, string $name): ?array
    {
        $row = app()->make(PluginMigration::class, [], true)->where(['plugin_code' => $code, 'migration_name' => $name])->find();
        return $row?->toArray();
    }

    /**
     * 保存迁移执行状态；DDL 前先记为待处理，避免中断被误判为成功。
     *
     * @param string $code 插件编码。
     * @param string $name 迁移名称。
     * @param array $data 迁移状态及执行证据。
     * @return void 无返回值。
     */
    public function saveMigration(string $code, string $name, array $data): void
    {
        $query = app()->make(PluginMigration::class, [], true)->where(['plugin_code' => $code, 'migration_name' => $name]);
        $row = $query->find();
        $data['update_time'] = date('Y-m-d H:i:s');
        if ($row) {
            $row->save($data);
        } else {
            app()->make(PluginMigration::class, [], true)->save($data + ['plugin_code' => $code, 'migration_name' => $name, 'create_time' => $data['update_time']]);
        }
    }

    /**
     * 查询包括已移除记录在内的插件资源归属。
     *
     * @param string $code 插件编码。
     * @param string $type 资源类型。
     * @param string $key 插件内稳定的资源键。
     * @return array<string,mixed>|null 资源归属记录；不存在时为 null。
     */
    public function resource(string $code, string $type, string $key): ?array
    {
        $row = app()->make(PluginResource::class, [], true)->withTrashed()->where(['plugin_code' => $code, 'resource_type' => $type, 'resource_key' => $key])->find();
        return $row?->toArray();
    }

    /**
     * 读取宿主菜单的归属与父级；软删除菜单不能作为插件父级。
     *
     * @param int $id 待检查的宿主菜单 ID。
     * @return array<string,mixed>|null 菜单层级资料；不存在时为 null。
     */
    public function parentMenu(int $id): ?array
    {
        $row = app()->make(Menu::class, [], true)->where('id', $id)->field('id,pid,platform_type,auth_type,status')->find();
        return $row?->toArray();
    }

    /**
     * 注册或恢复插件自有资源，同时保存不可混淆的归属身份。
     *
     * @param string $code 插件编码。
     * @param string $type 菜单、页面选链或装修页资源类型。
     * @param string $key 插件内稳定的资源键。
     * @param array $data 宿主资源字段及登记快照。
     * @return int 已注册的宿主资源 ID。
     */
    public function installResource(string $code, string $type, string $key, array $data): int
    {
        $model = $this->resourceModel($type);
        $owned = $this->resource($code, $type, $key);
        $row = $owned ? $model->withTrashed()->where('id', (int)$owned['resource_id'])->find() : null;
        if (!$owned) {
            $identity = match ($type) {
                'menu' => ['platform_type' => $data['platform_type'], 'auth_type' => $data['auth_type'], 'url' => $data['url'], 'methods' => $data['methods']],
                'page_link' => ['link_url' => $data['link_url']],
                'page' => ['page_type' => $data['page_type']],
            };
            if ($model->withTrashed()->where($identity)->find()) {
                throw new PluginLifecycleException('插件资源与既有非插件资源冲突', 'PLUGIN_RESOURCE_CONFLICT');
            }
        }
        $data += ['create_time' => date('Y-m-d H:i:s'), 'update_time' => date('Y-m-d H:i:s'), 'delete_time' => null];
        if ($row) {
            /*
             * withTrashed 只放宽读取范围；直接 save 仍会过滤软删除记录，必须先恢复。
             */
            if ($row->trashed() && !$row->restore()) {
                throw new PluginLifecycleException('插件资源恢复失败', 'PLUGIN_RESOURCE_RESTORE_FAILED');
            }
            $row->save($data);
        } else {
            $row = $model->create($data);
        }
        $resourceData = ['resource_id' => (string)$row['id'], 'snapshot_json' => $data, 'status' => 'active', 'delete_time' => null, 'update_time' => date('Y-m-d H:i:s')];
        $resource = app()->make(PluginResource::class, [], true)->withTrashed()->where(['plugin_code' => $code, 'resource_type' => $type, 'resource_key' => $key])->find();
        if ($resource) {
            /*
             * 卸载保留的归属账本需要恢复后再更新，避免新菜单已创建却未记录实际 ID。
             */
            if ($resource->trashed() && !$resource->restore()) {
                throw new PluginLifecycleException('插件资源归属恢复失败', 'PLUGIN_RESOURCE_RESTORE_FAILED');
            }
            $resource->save($resourceData);
        } else {
            app()->make(PluginResource::class, [], true)->save($resourceData + ['plugin_code' => $code, 'resource_type' => $type, 'resource_key' => $key]);
        }
        return (int)$row['id'];
    }

    /**
     * 仅清理归属账本登记的插件资源及声明配置，不删除宿主首页。
     *
     * @param string $code 插件编码。
     * @param array $declaration 插件声明的组件类型和配置键等清理范围。
     * @return void 无返回值。
     */
    public function removeResources(string $code, array $declaration): void
    {
        $menuIds = [];
        $linkIds = [];
        $resources = app()->make(PluginResource::class, [], true)->where(['plugin_code' => $code, 'status' => 'active'])->select();
        foreach ($resources as $resource) {
            $type = (string)$resource['resource_type'];
            if (!in_array($type, ['menu', 'page_link', 'page'], true)) {
                continue;
            }
            $id = (int)$resource['resource_id'];
            if ($type === 'menu') {
                $menuIds[] = $id;
            }
            if ($type === 'page_link') {
                $linkIds[] = $id;
            }
            if ($type === 'page') {
                app()->make(DiyComponent::class, [], true)->withTrashed()->where('diy_page_id', $id)->delete(true);
            }
            $this->resourceModel($type)->withTrashed()->where('id', $id)->delete(true);
            $resource->save(['status' => 'removed', 'delete_time' => date('Y-m-d H:i:s')]);
        }
        if ($menuIds !== []) {
            $last = 0;
            do {
                $roles = app()->make(AdminRole::class, [], true)->where('id', '>', $last)->order('id')->limit(200)->select();
                foreach ($roles as $role) {
                    $last = (int)$role['id'];
                    $rules = $role['rule'];
                    if (is_array($rules)) {
                        $filtered = array_values(array_filter($rules, fn ($id) => !in_array((int)$id, $menuIds, true)));
                        if ($filtered !== $rules) {
                            $role->save(['rule' => $filtered]);
                        }
                    }
                }
            } while (count($roles) === 200);
        }
        $types = $declaration['diy_components'] ?? [];
        if ($types !== []) {
            app()->make(DiyComponent::class, [], true)->withTrashed()->whereIn('component_type', $types)->delete(true);
        }
        // 共享装修页只移除插件链接，避免卸载时删除宿主页面。
        $last = 0;
        do {
            $components = app()->make(DiyComponent::class, [], true)->where('id', '>', $last)->order('id')->limit(200)->lock(true)->select();
            foreach ($components as $component) {
                $last = (int)$component['id'];
                $config = $component['config'];
                if (is_string($config)) {
                    $config = json_decode($config, true);
                }
                if (is_array($config)) {
                    $clean = $this->removePluginLinks($config, $code, $linkIds);
                    if ($clean !== $config) {
                        $component->save(['config' => $clean]);
                    }
                }
            }
        } while (count($components) === 200);
        if (!empty($declaration['config_keys'])) {
            Db::name('config')->whereIn('key_name', $declaration['config_keys'])->delete();
        }
        foreach (['plugin_config', 'plugin_merchant_scope', 'plugin_license_lease'] as $table) {
            Db::name($table)->where('plugin_code', $code)->delete();
        }
    }

    /**
     * 清除插件选链身份和精确插件路由，保留无关装修内容。
     *
     * @param array $data 包含嵌套选链信息的组件配置。
     * @param string $code 待卸载的插件编码。
     * @param array<int> $linkIds 已删除的插件自有 diy_page_link ID。
     * @return array 清理后的组件配置。
     */
    public function removePluginLinks(array $data, string $code, array $linkIds = []): array
    {
        $selectionPrefixes = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->removePluginLinks($value, $code, $linkIds);
            } elseif (is_string($value) && preg_match('~^/?plugins/' . preg_quote($code, '~') . '/(?:[^?#]*)(?:[?#].*)?$~', $value)) {
                $data[$key] = '';
                if (is_string($key) && preg_match('/^(.*)link_url$/D', $key, $match)) {
                    $selectionPrefixes[$match[1]] = true;
                } elseif ($key === 'url' || $key === 'link') {
                    $selectionPrefixes[''] = true;
                }
            }
            if (is_string($key) && preg_match('/^(.*)link_id$/D', $key, $match)
                && (is_int($value) || (is_string($value) && ctype_digit($value)))
                && in_array((int)$value, $linkIds, true)) {
                $selectionPrefixes[$match[1]] = true;
            }
        }
        // 编辑器同时保存路由和选中记录信息，两类身份必须一起清除以免残留旧选链。
        foreach (array_keys($selectionPrefixes) as $prefix) {
            foreach (['link_id' => 0, 'link_name' => '', 'link_type' => '', 'platform_type' => ''] as $field => $empty) {
                if (array_key_exists($prefix . $field, $data)) {
                    $data[$prefix . $field] = $empty;
                }
            }
        }
        return $data;
    }

    /**
     * 按已允许的资源类型解析宿主模型。
     *
     * @param string $type 菜单、页面选链或装修页资源类型。
     * @return object 对应的宿主资源模型实例。
     */
    private function resourceModel(string $type): object
    {
        return app()->make(match ($type) {
            'menu' => Menu::class,
            'page_link' => DiyPageLink::class,
            'page' => DiyPage::class,
            default => throw new PluginLifecycleException('资源类型无效', 'PLUGIN_RESOURCE_INVALID'),
        }, [], true);
    }
}
