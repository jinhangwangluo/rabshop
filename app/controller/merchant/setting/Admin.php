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

use app\common\service\merchant\MerchantAdminService;
use app\controller\merchant\AuthController;
use think\annotation\Inject;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 商户管理员控制器
 * Class Admin
 * @package app\controller\merchant\setting
 */
class Admin extends AuthController
{
    /**
     * @var MerchantAdminService
     */
    #[Inject]
    protected MerchantAdminService $service;

    /**
     * 管理员列表
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function index()
    {
        $where = $this->request->getMore([
            ['status', ''],
            ['account', ''],
        ]);

        return $this->success($this->service->getAdminList($where, $this->merchantId));
    }

    /**
     * 创建管理员表单
     * @param int $id
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function create(int $id = 0)
    {
        return $this->success($this->service->getFormBuild($id, $this->merchantId, $this->adminId));
    }

    /**
     * 保存管理员
     * @param int $id
     * @return mixed
     */
    public function save(int $id = 0)
    {
        $data = $this->request->postMore([
            ['real_name', ''],
            ['account', ''],
            ['roles', []],
            ['avatar', ''],
            ['status', 1],
            ['message_notify_enabled', 0],
            ['message_notify_phone', ''],
            ['message_notify_email', ''],
            ['password', ''],
            ['confirm_password', ''],
        ]);

        if (!$data['account']) {
            return $this->fail('请输入账号');
        }

        if (!$id && !$data['password']) {
            return $this->fail('请输入密码');
        }

        if ($data['password'] && !$data['confirm_password']) {
            return $this->fail('请输入确认密码');
        }

        if (!$data['password'] && $data['confirm_password']) {
            return $this->fail('请输入密码');
        }

        if ($data['password'] && $data['confirm_password']) {
            if ($data['password'] !== $data['confirm_password']) {
                return $this->fail('两次输入的密码不一致');
            }
        }

        unset($data['confirm_password']);

        try {
            if ($id) {
                $this->service->updateAdmin($id, $data, $this->merchantId);
                return $this->success('修改成功');
            } else {
                $this->service->createAdmin($data, $this->merchantId, $this->adminId);
                return $this->success('添加成功');
            }
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }
    }

    /**
     * 管理员详情
     * @param int $id
     * @return mixed
     */
    public function read(int $id)
    {
        if (!$id) {
            return $this->fail('缺少参数');
        }

        try {
            $info = $this->service->get($id);
            if (!$info || $info['merchant_id'] != $this->merchantId) {
                return $this->fail('管理员不存在');
            }
            return $this->success($info->toArray());
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
     * 删除管理员
     * @param int $id
     * @return mixed
     */
    public function delete(int $id)
    {
        if (!$id) {
            return $this->fail('缺少参数');
        }

        // 不能删除自己
        if ($id == $this->adminId) {
            return $this->fail('不能删除自己');
        }

        try {
            $this->service->deleteAdmin($id, $this->merchantId);
            return $this->success('删除成功');
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }
    }

    /**
     * 获取当前管理员信息
     * @return mixed
     */
    public function adminInfo()
    {
        return $this->success($this->adminInfo);
    }

    /**
     * 修改当前管理员信息
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function putAdminInfo()
    {
        $data = $this->request->postMore([
            ['avatar', ''],
            ['real_name', ''],
            ['password', ''],
            ['new_password', ''],
            ['conf_password', ''],
        ]);

        if ($data['password']) {
            if (!$data['new_password'] || !$data['conf_password']) {
                return $this->fail('请输入新密码和确认密码');
            }
            if ($data['new_password'] !== $data['conf_password']) {
                return $this->fail('新密码和确认密码不相同');
            }

            // 验证原密码
            $admin = $this->service->get($this->adminId);
            if (!password_verify($data['password'], $admin->password)) {
                return $this->fail('原密码不正确');
            }

            $data['password'] = password_hash($data['new_password'], PASSWORD_BCRYPT);
        } else {
            unset($data['password']);
        }

        unset($data['new_password'], $data['conf_password']);

        try {
            $this->service->update($this->adminId, $data);
            return $this->success('修改成功');
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }
    }

    /**
     * 获取管理员下拉列表
     * @return mixed
     */
    public function select()
    {
        return $this->success($this->service->getAdminSelect($this->merchantId));
    }
}
