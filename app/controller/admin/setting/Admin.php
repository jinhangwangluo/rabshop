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


use app\common\service\setting\AdminService;
use app\common\service\user\UserService;
use app\controller\admin\AuthController;
use Rabbit\service\websocket\TokenHelper;
use think\annotation\Inject;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 管理员控制器
 * Class Admin
 * @package app\admin\controller\setting
 */
class Admin extends AuthController
{
    /**
     * @var AdminService
     */
    #[Inject]
    protected AdminService $service;

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

        $where['pid'] = $this->adminId;

        return $this->success($this->service->getAdminUserList($where));
    }

    /**
     * 保存管理员
     * @return mixed
     */
    public function save($id = 0)
    {
        $data = $this->request->postMore([
            ['real_name', ''],
            ['account', ''],
            ['roles', []],
            ['avatar', ''],
            ['user_id', 0],
            ['status', ''],
            ['message_notify_enabled', 0],
            ['message_notify_phone', ''],
            ['message_notify_email', ''],
            ['password', ''],
            ['confirm_password', ''],
        ]);
        $password = $data['password'];
        $confirmPassword = $data['confirm_password'];
        unset($data['password'], $data['confirm_password']);

        if (!$data['account']) {
            return $this->fail('请输入账号');
        }
        if ($this->service->getModel()->where('account', $data['account'])->when($id, fn($q) => $q->where('id', '<>', $id))->value('id')) {
            return $this->fail('账号已存在');
        }
        $data['user_id'] = (int)$data['user_id'];
        if ($data['user_id'] > 0) {
            $user = app()->make(UserService::class)->get(['uid' => $data['user_id']], ['uid']);
            if (!$user) {
                return $this->fail('绑定用户不存在');
            }
        }
        $data = $this->service->normalizeMessageNotifyData($data);
        if ($password && !$confirmPassword) {
            return $this->fail('请输入确认密码');
        }
        if (!$password && $confirmPassword) {
            return $this->fail('请输入密码');
        }
        if ($password && $confirmPassword) {
            if ($password !== $confirmPassword) {
                return $this->fail('两次输入的密码不一致');
            }
            $data['password'] = password_hash($password, PASSWORD_BCRYPT);
        }

        $res = true;
        if ($id) {
            $this->service->update($id, $data);
        } else {
            $data['pid'] = $this->adminId;
            $res = $this->service->create($data);
        }

        if ($res) {
            if ($id) {
                // 管理员资料、角色或状态变化后，旧 WebSocket token 不再具备可信权限。
                TokenHelper::revokePlatformToken((int)$id);
            }
            return $this->success($id ? '修改成功' : '添加成功');
        } else {
            return $this->fail($id ? '修改失败' : '添加失败');
        }
    }

    /**
     * 状态修改
     * @param $id
     * @param $status
     * @return mixed
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
        if ((int)$status !== 1) {
            TokenHelper::revokePlatformToken((int)$id);
        }

        return $this->success('修改成功');

    }

    /**
     * 删除
     * @param $id
     * @return mixed
     */
    public function delete($id)
    {
        if (!$id) {
            return $this->fail('缺少参数');
        }

        $this->service->destroy($id);
        TokenHelper::revokePlatformToken((int)$id);

        return $this->success('删除成功');
    }

    /**
     * 获取管理员信息
     * @return mixed
     */
    public function adminInfo()
    {
        return $this->success($this->adminInfo);
    }

    /**
     * 用户选择弹窗列表。
     */
    public function userSelect(): mixed
    {
        $where = $this->request->getMore([
            ['keyword', ''],
        ]);

        return $this->success('获取成功', $this->service->getUserSelectList($where));
    }

    /**
     * 修改管理员信息
     * @return mixed
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

        $passwordChanged = false;
        if ($data['password']) {
            if (!$data['new_password'] || !$data['conf_password']) {
                return $this->fail('请输入新密码和确认密码');
            }
            if ($data['new_password'] !== $data['conf_password']) {
                return $this->fail('新密码和确认密码不相同');
            }
            $data['password'] = password_hash($data['new_password'], PASSWORD_BCRYPT);
            $passwordChanged = true;
        } else {
            unset($data['password']);
        }

        unset($data['new_password'], $data['conf_password']);

        $this->service->update($this->adminId, $data);
        if ($passwordChanged) {
            TokenHelper::revokePlatformToken((int)$this->adminId);
        }

        return $this->success('修改成功');
    }

}
