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

namespace app\controller\admin\store;

use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\controller\admin\AuthController;
use app\common\service\store\StoreCouponUserService;
use think\annotation\Inject;
use think\Response;

/**
 * 用户优惠券管理控制器
 */
class StoreCouponUser extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    /**
     * @var StoreCouponUserService
     */
    #[Inject]
    protected StoreCouponUserService $service;

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
        return null;
    }

    /**
     * 搜索字段
     */
    protected function getIndexFields(): array
    {
        return [
            ['coupon_id', ''],
            ['issue_id', ''],
            ['user_id', ''],
            ['merchant_id', ''],
            ['status', ''],
            ['receive_type', ''],
            ['create_time', ''],
        ];
    }

    /**
     * POST请求获取的字段
     */
    protected function getRequestFields(): array
    {
        return [
            ['coupon_id', 0],
            ['issue_id', 0],
            ['merchant_id', 0],
            ['user_id', 0],
            ['name', ''],
            ['discount_amount', 0],
            ['min_order_amount', 0],
            ['receive_type', 'receive'],
            ['valid_start_time', null],
            ['valid_end_time', null],
            ['use_time', null],
            ['order_id', 0],
            ['status', 0],
        ];
    }

    /**
     * 表单初始化字段
     */
    protected function getFormFields(): array
    {
        return [];
    }

    /**
     * 获取状态选项
     */
    public function getStatusList(): Response
    {
        $status = [
            ['value' => 0, 'label' => '未使用'],
            ['value' => 1, 'label' => '已使用'],
            ['value' => 2, 'label' => '已过期'],
        ];
        return $this->success($status);
    }

    /**
     * 获取获取方式选项
     */
    public function getReceiveTypes(): Response
    {
        $types = [
            ['value' => 'receive', 'label' => '领取'],
            ['value' => 'send', 'label' => '后台发送'],
            ['value' => 'give', 'label' => '满赠'],
            ['value' => 'new', 'label' => '新人'],
            ['value' => 'buy', 'label' => '买赠'],
            ['value' => 'first', 'label' => '首单赠送'],
        ];
        return $this->success($types);
    }
}
