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


use app\common\exception\AdminException;
use app\common\model\setting\system\AdminRole;
use app\common\model\setting\system\Menu;
use app\common\service\BaseService;
use FormBuilder\Exception\FormBuilderException;
use Rabbit\util\FormBuild as Build;
use Rabbit\traits\ViewTrait;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * Class AdminRoleService
 * @author 橙猫
 * @email 17349395805@163.com
 * @date 2022/10/21
 * @package app\common\service\setting
 */
class AdminRoleService extends BaseService
{

    use ViewTrait;

    /**
     * @return string
     */
    protected function setModel(): string
    {
        return AdminRole::class;
    }

    /**
     * 获取角色列表
     * @param array $where
     * @return array
     */
    public function getRoleList(array $where)
    {
        [$page, $limit] = $this->getPageValue();

        // 默认只查询平台角色
        $where['platform_type'] = $where['platform_type'] ?? AdminRole::PLATFORM_TYPE_PLATFORM;

        $model = $this->getModel()->search($where);

        $count = $model->count();
        $list = $model->order('id', 'desc')->page($page, $limit)->field(['id', 'status', 'role_name as name', 'pid', 'create_time', 'platform_type', 'merchant_id'])->select()->toArray();

        return compact('count', 'list');
    }

    /**
     * 获取角色权限
     * @param array $roleIds
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getRoleMenuIds(array $roleIds)
    {
        $menusList = $this->getModel()->whereIn('id', $roleIds)->field(['rule'])->select()->toArray();

        $menusIds = [];
        foreach ($menusList as $item) {
            $menusIds = array_merge($menusIds, $item['rule']);
        }

        return array_merge(array_unique(array_filter($menusIds)));
    }

    /**
     * 获取角色名称
     * @param array $ids
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getRoleIdsByList(array $ids)
    {
        $column = [];
        $list = $this->getModel()->whereIn('id', $ids)->field(['role_name', 'id'])->select()->toArray();
        foreach ($list as $item) {
            $column[$item['id']] = $item['role_name'];
        }
        return $column;
    }

    /**
     * 获取角色
     * @param int $pid
     * @param int $platformType 平台类型
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getRoles(int $pid = 0, int $platformType = AdminRole::PLATFORM_TYPE_PLATFORM)
    {
        return $this->getModel()->where(['pid' => $pid, 'status' => 1, 'platform_type' => $platformType])->field(['role_name as label', 'id as value'])->select()->toArray();
    }

    /**
     * 获取角色全部
     * @param int $platformType 平台类型
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getRoleAll(int $platformType = AdminRole::PLATFORM_TYPE_PLATFORM)
    {
        return $this->getModel()->where(['status' => 1, 'platform_type' => $platformType])->field(['id as value', 'role_name as label'])->select()->toArray();
    }

    /**
     * 获取角色列表
     * @param array $where
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getList(array $where)
    {
        $list = $this->getModel()->search($where)->order('id desc')->select()->toArray();
        foreach ($list as &$item) {
            if ($item['rules']) {
                $item['rules'] = Menu::whereIn('id', $item['rule'])->column('name');
            }
        }

        $count = $this->getModel()->search($where)->count();

        return compact('list', 'count');
    }

    /**
     * 获取表单
     * @param int $id
     * @param array $adminInfo
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException|FormBuilderException
     */
    public function getForm(int $id, array $adminInfo)
    {
        $role = [];
        if ($id) {
            $role = $this->getModel()->where('id', $id)->find();
            if (!$role) {
                throw new AdminException('修改的权限不存在');
            }
            $role = $role->toArray();
        }

        /** @var MenuService $menuService */
        $menuService = app()->make(MenuService::class);
        $menuList = $menuService->getMenusTree($adminInfo, [
            'icon', 'name as label',
            'name', 'url',
            'pid', 'id', 'id as value', 'path'], 'asc');
        return $this->buildView([
            Build::input('role_name', '权限名称', $role['role_name'] ?? '')->required(),
            Build::tree('rules', '权限', $role['rule'] ?? [])->data($menuList)->props([
                'props' => [
                    'checkStrictly'    => true,
                    'defaultExpandAll' => true,
                    'showCheckbox'     => true,
                ],
            ])->required(),
            Build::switches('status', '状态', $role['status'] ?? 0)
                ->inactiveText('关闭')
                ->activeValue(1)
                ->inactiveValue(0)
                ->activeText('开启'),
        ], '/admin/setting.role/save?id=' . $id);
    }
}
