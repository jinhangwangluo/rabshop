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

namespace app\controller\admin\cs;

use app\common\service\cs\CsStaffService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 客服人员控制器
 */
class CsStaff extends AuthController
{
    /**
     * @var CsStaffService
     */
    #[Inject]
    protected CsStaffService $service;

    /**
     * 获取客服列表
     */
    public function index(): Response
    {
        $params = $this->request->getMore([
            ['status', ''],
            ['is_online', ''],
            ['staff_no', ''],
            ['keyword', ''],
        ]);

        return $this->success('获取成功', $this->service->getStaffList($params, $this->getMerchantId()));
    }

    /**
     * 创建客服
     */
    public function create(): Response
    {
        $data = $this->request->postMore([
            ['admin_id', 0],
            ['nickname', ''],
            ['avatar', ''],
            ['max_sessions', 10],
            ['status', 0],
        ]);

        if ((int)$data['admin_id'] <= 0) {
            return $this->fail('管理员ID不能为空');
        }
        if (trim((string)$data['nickname']) === '') {
            return $this->fail('客服昵称不能为空');
        }

        $staff = $this->service->createStaff(
            $this->getMerchantId(),
            (int)$data['admin_id'],
            trim((string)$data['nickname']),
            trim((string)$data['avatar'])
        );

        if ((int)$data['max_sessions'] !== 10 || (int)$data['status'] !== 0) {
            $this->service->updateStaff((int)$staff->id, [
                'max_sessions' => max(1, (int)$data['max_sessions']),
                'status' => (int)$data['status'],
            ], $this->getMerchantId());
            $staff = $this->service->getStaffById((int)$staff->id, $this->getMerchantId());
        }

        return $this->success('保存成功', $staff);
    }

    /**
     * 更新客服
     */
    public function update(int $id): Response
    {
        if ($id <= 0) {
            return $this->fail('ID不能为空');
        }

        $data = $this->request->postMore([
            ['nickname', ''],
            ['avatar', ''],
            ['max_sessions', 10],
            ['status', 0],
        ]);
        if (trim((string)$data['nickname']) === '') {
            return $this->fail('客服昵称不能为空');
        }

        $this->service->updateStaff($id, [
            'nickname' => trim((string)$data['nickname']),
            'avatar' => trim((string)$data['avatar']),
            'max_sessions' => max(1, (int)$data['max_sessions']),
            'status' => (int)$data['status'],
        ], $this->getMerchantId());

        return $this->success('修改成功');
    }

    /**
     * 删除客服
     */
    public function delete(int $id): Response
    {
        if ($id <= 0) {
            return $this->fail('ID不能为空');
        }

        $this->service->deleteStaff($id, $this->getMerchantId());
        return $this->success('删除成功');
    }

    /**
     * 更新在线状态
     */
    public function updateOnlineStatus(): Response
    {
        $data = $this->request->postMore([
            ['id', 0],
            ['is_online', ''],
            ['status', ''],
        ]);

        if ((int)$data['id'] <= 0 || $data['is_online'] === '') {
            return $this->fail('参数错误');
        }

        $status = $data['status'] === '' ? null : (int)$data['status'];
        $this->service->updateOnlineStatus((int)$data['id'], (int)$data['is_online'], $status);
        return $this->success('修改成功');
    }

    /**
     * 获取在线客服列表
     */
    public function online(): Response
    {
        return $this->success('获取成功', $this->service->getOnlineStaff($this->getMerchantId()));
    }

    /**
     * 获取商户ID
     */
    protected function getMerchantId(): int
    {
        return 0;
    }
}
