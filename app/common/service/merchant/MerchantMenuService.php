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

namespace app\common\service\merchant;

use app\common\exception\AdminResponseException;
use app\common\model\setting\system\Menu;
use app\common\service\BaseService;
use Rabbit\traits\ViewTrait;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\model\contract\Modelable;

/**
 * 商户菜单服务类
 * Class MerchantMenuService
 * @package app\common\service\merchant
 */
class MerchantMenuService extends BaseService
{
    use ViewTrait;

    /**
     * 商户端菜单类型标识
     */
    const PLATFORM_TYPE_MERCHANT = 2;

    /**
     * 设置模型
     * @return string
     */
    protected function setModel(): string
    {
        return Menu::class;
    }

    /**
     * 获取商户菜单列表
     * @param array $where
     * @return array
     */
    public function getMenusList(array $where): array
    {
        // 强制只查询商户端菜单
        $where['platform_type'] = self::PLATFORM_TYPE_MERCHANT;

        $menus = $this->getModel()->search($where)
            ->order('sort', 'desc')
            ->order('id', 'desc')
            ->field(['id', 'pid', 'name as title', 'icon', 'url as path', 'status', 'auth_type', 'methods', 'is_hidden as hidden', 'platform_type'])
            ->select()
            ->each(function ($item) {
                $path = is_null($item['path']) ? '' : $item['path'];
                if (!$path) {
                    $path = '/';
                } else {
                    if ($path[0] !== '/') {
                        $path = '/' . $path;
                    }
                }
                $item['path'] = (config('app.merchant_ui_prefix') ? '/' . config('app.merchant_ui_prefix') : '/merchant') . $path;
                $item['hidden'] = (bool)$item['hidden'];
            })->toArray();

        return get_tree_children($menus);
    }

    /**
     * 获取带有下级的菜单列表
     * @param array $where
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getList(array $where): array
    {
        $where['platform_type'] = self::PLATFORM_TYPE_MERCHANT;
        return get_tree_children($this->getModel()->search($where)->order('sort desc,id asc')->select()->toArray());
    }

    /**
     * 获取菜单tree
     * @param bool $auth
     * @param array $field
     * @param array $menusIds
     * @param string $order
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getMenusTree(bool $auth = true, array $field = ['icon', 'name as label', 'name', 'url', 'pid', 'id', 'id as value'], array $menusIds = [], string $order = 'desc'): array
    {
        return get_tree_children(
            $this->getModel()
                ->where('platform_type', self::PLATFORM_TYPE_MERCHANT)
                ->when($auth, function ($query) {
                    $query->where(['auth_type' => 0]);
                })
                ->when($menusIds, function ($query) use ($menusIds) {
                    $query->whereIn('id', $menusIds);
                })
                ->where(['status' => 1])
                ->field($field)
                ->order('sort desc,id ' . $order)
                ->select()
                ->toArray()
        );
    }

    /**
     * 获取商户所有菜单（用于权限分配）
     * @param int $status
     * @param bool $isTree
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getMenusAll(int $status = 1, bool $isTree = false): array
    {
        $menus = $this->getModel()
            ->where('platform_type', self::PLATFORM_TYPE_MERCHANT)
            ->when($status, function ($query) {
                $query->where('status', 1);
            })
            ->where('auth_type', 0)
            ->order('sort', 'desc')
            ->order('id', 'desc')
            ->field(['id', 'pid', 'name as title', 'icon', 'url as path', 'is_hidden as hidden'])
            ->select()
            ->each(function ($item) {
                $path = is_null($item['path']) ? '' : $item['path'];
                if (!$path) {
                    $path = '/';
                } else {
                    if ($path[0] !== '/') {
                        $path = '/' . $path;
                    }
                }
                $item['path'] = (config('app.merchant_ui_prefix') ? '/' . config('app.merchant_ui_prefix') : '/merchant') . $path;
                $item['hidden'] = (bool)$item['hidden'];
            })->toArray();

        if ($isTree) {
            return $menus;
        }

        return get_tree_children($menus);
    }

    /**
     * 根据角色获取商户菜单（登录时调用）
     * @param array $roleIds 角色ID数组
     * @param int $merchantId 商户ID
     * @param int $status
     * @param bool $isTree
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getMenusByRoles(array $roleIds = [], int $merchantId = 0, int $status = 1, bool $isTree = false): array
    {
        $menusIds = [0];
        if ($roleIds && $merchantId) {
            $menusIds = app()->make(MerchantAdminRoleService::class)->getRoleMenuIds($roleIds, $merchantId);
        }

        $menus = $this->getModel()
            ->where('platform_type', self::PLATFORM_TYPE_MERCHANT)
            ->whereIn('id', $menusIds)
            ->when($status, function ($query) {
                $query->where('status', 1);
            })->where('auth_type', 0)
            ->order('sort', 'desc')
            ->order('id', 'desc')
            ->field(['id', 'pid', 'name as title', 'icon', 'url as path', 'is_hidden as hidden'])
            ->select()
            ->each(function ($item) {
                $path = is_null($item['path']) ? '' : $item['path'];
                if (!$path) {
                    $path = '/';
                } else {
                    if ($path[0] !== '/') {
                        $path = '/' . $path;
                    }
                }
                $item['path'] = (config('app.merchant_ui_prefix') ? '/' . config('app.merchant_ui_prefix') : '/merchant') . $path;
                $item['hidden'] = (bool)$item['hidden'];
            })->toArray();

        if ($isTree) {
            return $menus;
        }

        return get_tree_children($menus);
    }

    /**
     * 获取当前商户角色可访问的接口权限菜单。
     *
     * @param array $roleIds 角色ID数组
     * @param int $merchantId 商户ID
     * @return array
     */
    public function getPermissionMenusByRoles(array $roleIds, int $merchantId): array
    {
        if ($merchantId <= 0 || $roleIds === []) {
            return [];
        }

        $menuIds = app()->make(MerchantAdminRoleService::class)->getRoleMenuIds($roleIds, $merchantId);
        if ($menuIds === []) {
            return [];
        }

        return $this->getModel()
            ->where('platform_type', self::PLATFORM_TYPE_MERCHANT)
            ->where('status', 1)
            // API 权限菜单为 auth_type=1，配置型权限为 auth_type=2；普通前端菜单不参与接口鉴权。
            ->whereIn('auth_type', [1, 2])
            ->whereIn('id', $menuIds)
            ->field(['url', 'methods', 'auth_type'])
            ->select()
            ->toArray();
    }

    /**
     * 创建商户菜单
     * @param array $data
     * @return Modelable
     */
    public function createMenu(array $data)
    {
        // 强制设置为商户端菜单
        $data['platform_type'] = self::PLATFORM_TYPE_MERCHANT;
        return $this->create($data);
    }

    /**
     * 更新商户菜单
     * @param int $id
     * @param array $data
     * @return Modelable
     * @throws AdminResponseException
     */
    public function updateMenu(int $id, array $data)
    {
        // 确保菜单属于商户端
        $menu = $this->get($id);
        if (!$menu || $menu['platform_type'] != self::PLATFORM_TYPE_MERCHANT) {
            throw new AdminResponseException('菜单不存在或不属于商户端');
        }
        
        // 保持商户端类型
        $data['platform_type'] = self::PLATFORM_TYPE_MERCHANT;
        return $this->update($id, $data);
    }

    /**
     * 删除商户菜单
     * @param int $id
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function deleteMenu(int $id): bool
    {
        // 确保菜单属于商户端
        $menu = $this->get($id);
        if (!$menu || $menu['platform_type'] != self::PLATFORM_TYPE_MERCHANT) {
            throw new AdminResponseException('菜单不存在或不属于商户端');
        }

        // 检查是否有子菜单
        $childCount = $this->getModel()->where('pid', $id)->count();
        if ($childCount > 0) {
            throw new AdminResponseException('存在子菜单，无法删除');
        }

        return $this->destroy($id);
    }

    /**
     * 获取菜单详情
     * @param int $id
     * @return array
     * @throws AdminResponseException
     */
    public function getMenuInfo(int $id): array
    {
        $menu = $this->get($id);
        if (!$menu || $menu['platform_type'] != self::PLATFORM_TYPE_MERCHANT) {
            throw new AdminResponseException('菜单不存在或不属于商户端');
        }
        
        return $menu->toArray();
    }
}
