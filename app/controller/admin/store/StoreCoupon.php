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
use app\common\service\store\StoreCouponService;
use think\annotation\Inject;
use think\Response;

/**
 * 优惠券管理控制器
 */
class StoreCoupon extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    /**
     * @var StoreCouponService
     */
    #[Inject]
    protected StoreCouponService $service;

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
            ['name', ''],
            ['status', ''],
            ['scope_type', ''],
            ['merchant_id', 0],
            ['distribute_type', ''],
            ['create_time', ''],
        ];
    }

    /**
     * POST请求获取的字段
     */
    protected function getRequestFields(): array
    {
        return [
            ['merchant_id', 0],
            ['is_time_limited', 0],
            ['receive_start_time', null],
            ['receive_end_time', null],
            ['is_quantity_limited', 0],
            ['total_quantity', 0],
            ['remain_quantity', 0],
            ['distribute_type', 0],
            ['min_consume_amount', 0],
            ['name', ''],
            ['discount_amount', 0],
            ['min_order_amount', 0],
            ['expire_type', 0],
            ['valid_days', 0],
            ['valid_start_time', null],
            ['valid_end_time', null],
            ['sort', 1],
            ['status', 1],
            ['scope_type', 0],
            ['relations', []],
            ['relation_type', 1],
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
     * 获取签到奖励可选的平台优惠券列表
     */
    public function signinRewardOptions(): Response
    {
        $params = $this->request->getMore([
            ['name', ''],
            ['scope_type', ''],
        ]);

        return $this->success('获取成功', $this->service->getSigninRewardOptions($params));
    }

    /**
     * 获取适用范围选项
     */
    public function getScopeTypes(): Response
    {
        $types = [
            ['value' => 0, 'label' => '店铺券'],
            ['value' => 1, 'label' => '商品券'],
            ['value' => 10, 'label' => '平台通用券'],
            ['value' => 11, 'label' => '平台品类券'],
        ];
        return $this->success($types);
    }

    /**
     * 获取有效期类型选项
     */
    public function getExpireTypes(): Response
    {
        $types = [
            ['value' => 0, 'label' => '有效天数'],
            ['value' => 1, 'label' => '固定时间段'],
        ];
        return $this->success($types);
    }

    /**
     * 获取发放类型选项
     */
    public function getDistributeTypes(): Response
    {
        $types = [
            ['value' => 0, 'label' => '领取'],
            ['value' => 1, 'label' => '消费满赠'],
            ['value' => 2, 'label' => '新人'],
//            ['value' => 3, 'label' => '买赠'],
            ['value' => 4, 'label' => '首单赠送'],
        ];
        return $this->success($types);
    }

    /**
     * 获取关联子类型选项（按适用范围分组）
     */
    public function getRelationTypes(): Response
    {
        $types = [
            'product_types' => [
                ['value' => 1, 'label' => '内容'],
                ['value' => 2, 'label' => '专题'],
//                ['value' => 3, 'label' => '商品（暂未开放）', 'disabled' => true],
            ],
            'category_types' => [
                ['value' => 4, 'label' => '内容品类'],
                ['value' => 5, 'label' => '专题品类'],
//                ['value' => 6, 'label' => '商品品类（暂未开放）', 'disabled' => true],
            ],
        ];
        return $this->success($types);
    }
}
