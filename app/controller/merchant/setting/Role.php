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

namespace app\controller\merchant\setting;

use app\common\service\merchant\MerchantAdminRoleService;
use app\controller\merchant\AuthController;
use think\annotation\Inject;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Response;

/**
 * 商户角色控制器
 * Class Role
 * @package app\controller\merchant\setting
 */
class Role extends AuthController
{
    /**
     * @var MerchantAdminRoleService
     */
    #[Inject]
    protected MerchantAdminRoleService $service;

    /**
     * 角色列表
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function index()
    {
        $where = $this->request->getMore([
            ['status', ''],
            ['role_name', '']
        ]);

        return $this->success($this->service->getRoleList($where, $this->merchantId));
    }

    /**
     * 创建角色表单
     * @param int $id
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function create(int $id = 0)
    {
        return $this->success($this->service->getFormBuild($id, $this->merchantId));
    }

    /**
     * 角色详情
     * @param int $id
     * @return mixed
     */
    public function read(int $id)
    {
        if (!$id) {
            return $this->fail('缺少参数');
        }

        try {
            return $this->success($this->service->getRoleInfo($id, $this->merchantId));
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }
    }

    /**
     * 保存角色
     * @param int $id
     * @return mixed
     */
    public function save(int $id = 0)
    {
        $data = $this->request->postMore([
            ['role_name', ''],
            ['rules', []],
            ['status', 1]
        ]);

        if (!$data['role_name']) {
            return $this->fail('请输入角色名称');
        }

        try {
            if ($id) {
                $this->service->updateRole($id, $data, $this->merchantId);
                return $this->success('修改成功');
            } else {
                $this->service->createRole($data, $this->merchantId, $this->adminId);
                return $this->success('添加成功');
            }
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }
    }

    /**
     * 修改状态
     * @param int $id
     * @param int $status
     * @return mixed
     */
    public function status(int $id, int $status)
    {
        if (!$id) {
            return $this->fail('缺少参数');
        }

        try {
            $this->service->updateStatus($id, $status, $this->merchantId);
            return $this->success('修改成功');
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }
    }

    /**
     * 删除角色
     * @param int $id
     * @return mixed
     */
    public function delete(int $id)
    {
        if (!$id) {
            return $this->fail('缺少参数');
        }

        try {
            $this->service->deleteRole($id, $this->merchantId);
            return $this->success('删除成功');
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }
    }

    /**
     * 获取角色下拉列表
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function select()
    {
        return $this->success($this->service->getRoles($this->merchantId));
    }
}
