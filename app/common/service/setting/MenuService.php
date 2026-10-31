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

namespace app\common\service\setting;

use app\common\model\setting\system\AdminRole;
use app\common\model\setting\system\Menu;
use app\common\service\BaseService;
use Rabbit\traits\ViewTrait;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;


/**
 * 菜单服务
 * @package app\common\service\setting
 */
class MenuService extends BaseService
{
    use ViewTrait;

    /**
     * 获取菜单列表
     * @return string
     */
    protected function setModel(): string
    {
        return Menu::class;
    }

    /**
     * 获取菜单列表
     * @param array $where
     * @return array
     */
    public function getMenusList(array $where)
    {
        $menus = $this->getModel()->search($where)->order('sort', 'desc')
            ->order('id', 'desc')
            ->field(['id', 'pid', 'name as title', 'icon', 'url as path', 'status', 'auth_type', 'methods', 'is_hidden as hidden'])
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
                if ((int)$item['auth_type'] !== 1) {
                    $path = (config('app.admin_ui_prefix') ? '/' . config('app.admin_ui_prefix') : '') . $path;
                }
                $item['path'] = $path;
                $item['hidden'] = !!$item['hidden'];
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
    public function getList(array $where)
    {
        return get_tree_children($this->getModel()->search($where)->order('sort desc,id asc')->select()->toArray());
    }

    /**
     * 获取菜单索引
     * @param string $url
     * @return array|string[]
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getPathIndex(string $url)
    {
        $info = $this->getModel()->where(['url' => $url])->field('path,id,name')->find();
        if ($info) {
            return [get_menu_index($info->path, $info->id), $info->name];
        }
        return ['', ''];
    }

    /**
     * 获取缓存菜单tree
     * @return mixed
     */
    public function getMenusTreeCache(array $adminInfo)
    {
        return $this->cacheTag()->remember('admin_menus_' . $adminInfo['id'], function () use ($adminInfo) {
            $menusIds = $adminInfo['level'] ? AdminRole::getRules($adminInfo['roles']) : [];
            return $this->getModel()->getMenusTree(true, [
                'icon', 'name as label',
                'name', 'url',
                'pid', 'id', 'id as value', 'path'], $menusIds, 'asc');
        }, 1800);
    }

    /**
     * @param array $adminInfo
     * @param array $field
     * @param string $order
     * @param bool $auth
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getMenusTree(array $adminInfo, array $field = [], string $order = 'desc', bool $auth = false)
    {
        $menusIds = $adminInfo['level'] ? AdminRole::getRules($adminInfo['roles']) : [];
        return $this->getModel()->getMenusTree($auth, $field, $menusIds, $order);
    }

    /**
     * 获取管理员权限菜单
     * @param array $roleId
     * @return mixed
     */
    public function getAdminLevelMenus(array $roleId)
    {
        return $this->cacheTag()->remember(md5('admin_level_menus' . json_encode($roleId)), function () use ($roleId) {
            $menusIds = AdminRole::getRules($roleId);
            if (!$menusIds) {
                return [];
            }
            return $this->getModel()->whereIn('id', $menusIds)->column(['url', 'methods', 'auth_type'], 'id');
        });
    }

    /**
     * 获取权限名称
     * @param string $rule
     * @return mixed
     */
    public function getRuleName(string $rule)
    {
        return $this->where(['url' => $rule])->value('name');
    }

    /**
     * 获取菜单
     * @param array $roleIds
     * @param int $status
     * @param bool $isTree
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getMenusAll(array $roleIds = [], int $status = 1, bool $isTree = false)
    {
        $menusIds = [];
        if ($roleIds) {
            $menusIds = app()->make(AdminRoleService::class)->getRoleMenuIds($roleIds);
        }

        $menus = $this->getModel()
            ->when(!empty($menusIds), function ($query) use ($menusIds) {
                $query->whereIn('id', $menusIds);
            })->when($status, function ($query) {
                $query->where('status', 1);
            })->whereIn('auth_type', [0, 2])
            ->where('platform_type', 1)
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

                $item['path'] = (config('app.admin_ui_prefix') ? '/' . config('app.admin_ui_prefix') : '') . $path;
                $item['hidden'] = !!$item['hidden'];
            })->toArray();
        if ($isTree) {
            return $menus;
        }

        return get_tree_children($menus);
    }
}
