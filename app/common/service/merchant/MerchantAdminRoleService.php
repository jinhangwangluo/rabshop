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
use app\common\model\setting\system\AdminRole;
use app\common\model\setting\system\Menu;
use app\common\service\BaseService;
use FormBuilder\Exception\FormBuilderException;
use Rabbit\traits\ViewTrait;
use Rabbit\util\FormBuild as Build;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 商户角色服务
 * Class MerchantAdminRoleService
 * @package app\common\service\merchant
 */
class MerchantAdminRoleService extends BaseService
{
    use ViewTrait;

    /**
     * 设置模型
     * @return string
     */
    protected function setModel(): string
    {
        return AdminRole::class;
    }

    /**
     * 获取角色列表
     * @param array $where
     * @param int $merchantId
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getRoleList(array $where, int $merchantId): array
    {
        [$page, $limit] = $this->getPageValue();

        // 强制只查询当前商户的角色
        $where['platform_type'] = AdminRole::PLATFORM_TYPE_MERCHANT;
        $where['merchant_id'] = $merchantId;

        $model = $this->getModel()->search($where);

        $count = $model->count();
        $list = $model->order('id', 'desc')
            ->page($page, $limit)
            ->field(['id', 'status', 'role_name', 'pid', 'create_time', 'platform_type', 'merchant_id'])
            ->select()
            ->toArray();

        return compact('count', 'list');
    }

    /**
     * 获取角色名称列表
     * @param array $ids
     * @param int $merchantId
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getRoleIdsByList(array $ids, int $merchantId): array
    {
        $column = [];
        $list = $this->getModel()
            ->whereIn('id', $ids)
            ->where('merchant_id', $merchantId)
            ->where('platform_type', AdminRole::PLATFORM_TYPE_MERCHANT)
            ->field(['role_name', 'id'])
            ->select()
            ->toArray();

        foreach ($list as $item) {
            $column[$item['id']] = $item['role_name'];
        }

        return $column;
    }

    /**
     * 获取角色下拉列表
     * @param int $merchantId
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getRoles(int $merchantId): array
    {
        return $this->getModel()
            ->where('merchant_id', $merchantId)
            ->where('platform_type', AdminRole::PLATFORM_TYPE_MERCHANT)
            ->where('status', 1)
            ->field(['role_name as label', 'id as value'])
            ->select()
            ->toArray();
    }

    /**
     * 获取角色详情
     * @param int $id
     * @param int $merchantId
     * @return array
     * @throws AdminResponseException
     */
    public function getRoleInfo(int $id, int $merchantId): array
    {
        $role = $this->getModel()
            ->where('id', $id)
            ->where('merchant_id', $merchantId)
            ->where('platform_type', AdminRole::PLATFORM_TYPE_MERCHANT)
            ->find();

        if (!$role) {
            throw new AdminResponseException('角色不存在');
        }

        return $role->toArray();
    }

    /**
     * 获取表单
     * @param int $id
     * @param int $merchantId
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws FormBuilderException
     * @throws ModelNotFoundException
     */
    public function getFormBuild(int $id, int $merchantId): array
    {
        $role = [];
        if ($id) {
            $role = $this->getModel()
                ->where('id', $id)
                ->where('merchant_id', $merchantId)
                ->where('platform_type', AdminRole::PLATFORM_TYPE_MERCHANT)
                ->find();
            if (!$role) {
                throw new AdminResponseException('角色不存在');
            }
            $role = $role->toArray();
        }

        // 获取商户端菜单树
        $menuService = app()->make(MerchantMenuService::class);
        $menuList = $menuService->getMenusTree(true, [
            'icon', 'name as label',
            'name', 'url',
            'pid', 'id', 'id as value', 'path'
        ], $role['rule'] ?? [], 'asc');

        return $this->buildView([
            Build::input('role_name', '角色名称', $role['role_name'] ?? '')->required(),
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
        ], '/merchant/setting/role/' . $id, 'post');
    }

    /**
     * 创建角色
     * @param array $data
     * @param int $merchantId
     * @param int $adminId
     * @return \think\model\Model
     * @throws AdminResponseException
     */
    public function createRole(array $data, int $merchantId, int $adminId)
    {
        if (!$data['role_name']) {
            throw new AdminResponseException('请输入角色名称');
        }

        // 设置商户角色属性
        $data['platform_type'] = AdminRole::PLATFORM_TYPE_MERCHANT;
        $data['merchant_id'] = $merchantId;
        $data['pid'] = $adminId;

        // rule 字段处理
        if (isset($data['rules'])) {
            $data['rule'] = $data['rules'];
            unset($data['rules']);
        }

        $result = $this->create($data);
        $this->cacheTag()->clear();

        return $result;
    }

    /**
     * 更新角色
     * @param int $id
     * @param array $data
     * @param int $merchantId
     * @return bool
     * @throws AdminResponseException
     */
    public function updateRole(int $id, array $data, int $merchantId): bool
    {
        $role = $this->getModel()
            ->where('id', $id)
            ->where('merchant_id', $merchantId)
            ->where('platform_type', AdminRole::PLATFORM_TYPE_MERCHANT)
            ->find();

        if (!$role) {
            throw new AdminResponseException('角色不存在');
        }

        if (!$data['role_name']) {
            throw new AdminResponseException('请输入角色名称');
        }

        // rule 字段处理
        if (isset($data['rules'])) {
            $data['rule'] = $data['rules'];
            unset($data['rules']);
        }

        // 不能修改平台类型和商户ID
        unset($data['platform_type'], $data['merchant_id']);

        $result = $this->update($id, $data);
        $this->cacheTag()->clear();

        return $result;
    }

    /**
     * 删除角色
     * @param int $id
     * @param int $merchantId
     * @return bool
     * @throws AdminResponseException
     */
    public function deleteRole(int $id, int $merchantId): bool
    {
        $role = $this->getModel()
            ->where('id', $id)
            ->where('merchant_id', $merchantId)
            ->where('platform_type', AdminRole::PLATFORM_TYPE_MERCHANT)
            ->find();

        if (!$role) {
            throw new AdminResponseException('角色不存在');
        }

        // 检查是否有管理员使用此角色
        $adminCount = \app\common\model\setting\system\Admin::where('merchant_id', $merchantId)
            ->where('platform_type', AdminRole::PLATFORM_TYPE_MERCHANT)
            ->whereRaw("JSON_CONTAINS(roles, ?)", [(string)$id])
            ->count();

        if ($adminCount > 0) {
            throw new AdminResponseException('该角色已被管理员使用，无法删除');
        }

        $result = $this->destroy($id);
        $this->cacheTag()->clear();

        return $result;
    }

    /**
     * 修改状态
     * @param int $id
     * @param int $status
     * @param int $merchantId
     * @return bool
     * @throws AdminResponseException
     */
    public function updateStatus(int $id, int $status, int $merchantId): bool
    {
        $role = $this->getModel()
            ->where('id', $id)
            ->where('merchant_id', $merchantId)
            ->where('platform_type', AdminRole::PLATFORM_TYPE_MERCHANT)
            ->find();

        if (!$role) {
            throw new AdminResponseException('角色不存在');
        }

        $result = $this->update($id, ['status' => $status]);
        $this->cacheTag()->clear();

        return $result;
    }

    /**
     * 获取角色权限菜单ID
     * @param array $roleIds
     * @param int $merchantId
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getRoleMenuIds(array $roleIds, int $merchantId): array
    {
        $menusList = $this->getModel()
            ->whereIn('id', $roleIds)
            ->where('merchant_id', $merchantId)
            ->where('platform_type', AdminRole::PLATFORM_TYPE_MERCHANT)
            ->where('status', 1)
            ->field(['rule'])
            ->select()
            ->toArray();

        $menusIds = [];
        foreach ($menusList as $item) {
            $menusIds = array_merge($menusIds, $item['rule']);
        }

        return array_merge(array_unique(array_filter($menusIds)));
    }
}
