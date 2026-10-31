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

namespace app\controller\admin\setting;


use app\common\service\setting\AdminRoleService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Response;

/**
 * 角色管理
 * Class Role
 * @package app\admin\controller\setting
 */
class Role extends AuthController
{
    /**
     * @var AdminRoleService
     */
    #[Inject]
    protected AdminRoleService $service;

    /**
     * 角色列表
     * @return Response
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

        $where['pid'] = $this->adminId;
        return $this->success($this->service->getRoleList($where));
    }

    /**
     * 角色详情
     * @param $id
     * @return Response
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function find($id)
    {
        if (!$id) {
            return $this->fail('缺少参数');
        }
        $role = $this->service->get($id);
        if (!$role) {
            return $this->fail('没有查询到角色');
        }

        return $this->success($role->toArray());
    }

    /**
     * 添加/修改角色
     * @param $id
     * @return Response
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function save($id)
    {
        $data = $this->request->postMore([
            ['role_name', ''],
            ['rule', []],
            ['store_manage_ids', []],
            ['status', 0]
        ]);

        if (!$data['role_name']) {
            return $this->fail('请输入角色名称');
        }
        if (!$data['rule']) {
            return $this->fail('请选择角色权限');
        }
        if ($id) {
            $role = $this->service->get($id);
            if (!$role) {
                return $this->fail('没有查询到角色');
            }
            $role->role_name = $data['role_name'];
            $role->rule = $data['rule'];
            $role->store_manage_ids = $data['store_manage_ids'];
            $role->status = $data['status'];
            $role->save();
        } else {
            $data['pid'] = $this->adminId;
            $this->service->create($data);
        }

        $this->service->cacheTag()->clear();
        return $this->success($id ? '修改成功' : '添加成功');
    }

    /**
     * 角色状态修改
     * @param $id
     * @param $status
     * @return Response
     */
    public function status($id, $status)
    {
        if (!$id) {
            return $this->fail('缺少参数');
        }
        if ('' === $status) {
            return $this->fail('缺少修改数据');
        }

        $this->service->update($id, ['status' => $status]);
        $this->service->cacheTag()->clear();
        return $this->success('修改成功');
    }

    /**
     * 删除角色
     * @param $id
     * @return Response
     */
    public function delete($id)
    {
        if (!$id) {
            return $this->fail('缺少参数');
        }
        $this->service->destroy($id);
        $this->service->cacheTag()->clear();
        return $this->success('删除成功');
    }
}
