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

namespace app\controller\admin\finance;

use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\controller\admin\AuthController;
use app\common\service\finance\FinanceService;
use think\annotation\Inject;

/**
 * 财务记录控制器
 */
class FinanceRecord extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    /**
     * 财务管理服务
     */
    #[Inject]
    protected FinanceService $service;

    /**
     * 状态字段
     */
    protected function getStatusField(): string
    {
        return 'status';
    }

    /**
     * 验证类
     */
    protected function getValidateClassName(): string|null
    {
        return 'app\common\validate\FinanceRecordValidate';
    }

    /**
     * 搜索字段
     */
    protected function getIndexFields(): array
    {
        return [
            ['pid', ''],
            ['user_id', ''],
            ['merchant_id', ''],
            ['type', ''],
            ['change_type', ''],
            ['fund_status', ''],
            ['live_room_id', ''],
            ['live_gift_record_id', ''],
            ['live_anchor_id', ''],
            ['create_time', ''],
        ];
    }

    /**
     * post请求获取的字段
     */
    protected function getRequestFields(): array
    {
        return [
            ['pid', 0],
            ['user_id', 0],
            ['merchant_id', 0],
            ['type', ''],
            ['amount', 0],
            ['change_type', ''],
            ['fund_status', 'available'],
            ['remark', ''],
            ['order_sn', ''],
            ['live_room_id', 0],
            ['live_gift_record_id', 0],
            ['live_anchor_id', 0],
        ];
    }

    /**
     * 获取表单的前置字段
     */
    protected function getFormFields(): array
    {
        return [];
    }

    /**
     * 统计财务数据
     */
    public function statistics()
    {
        $type = $this->request->param('type/s', '');
        $createTime = $this->request->param('create_time/s', '');

        $where = [];
        if ($type) {
            $where['type'] = $type;
        }
        if ($createTime) {
            $where['create_time'] = $createTime;
        }

        $statistics = $this->service->statistics($where);
        return $this->success('获取成功', $statistics);
    }

    /**
     * 按类型统计财务数据
     */
    public function statisticsByType()
    {
        $createTime = $this->request->param('create_time/s', '');

        $where = [];
        if ($createTime) {
            $where['create_time'] = $createTime;
        }

        $statistics = $this->service->statisticsByType($where);
        return $this->success('获取成功', $statistics);
    }
}
