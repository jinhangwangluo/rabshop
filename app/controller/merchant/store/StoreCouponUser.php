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

namespace app\controller\merchant\store;

use app\common\model\store\StoreCouponUser as StoreCouponUserModel;
use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\common\service\store\StoreCouponUserService;
use app\controller\merchant\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 商户端优惠券领取记录控制器
 */
class StoreCouponUser extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    /**
     * 用户优惠券服务
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
            ['status', ''],
            ['receive_type', ''],
            ['create_time', []],
        ];
    }

    /**
     * 请求字段
     */
    protected function getRequestFields(): array
    {
        return [
            ['coupon_id', 0],
            ['issue_id', 0],
            ['user_id', 0],
            ['name', ''],
            ['discount_amount', 0],
            ['min_order_amount', 0],
            ['receive_type', StoreCouponUserModel::RECEIVE_TYPE_RECEIVE],
            ['valid_start_time', null],
            ['valid_end_time', null],
            ['use_time', null],
            ['order_id', 0],
            ['status', StoreCouponUserModel::STATUS_UNUSED],
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
     * 列表
     */
    public function index(): Response
    {
        $params = $this->request->getMore($this->getIndexFields());
        $params['merchant_id'] = (int) $this->merchantId;

        return $this->success($this->service->getResourceList($params));
    }

    /**
     * 详情
     */
    public function read($id): Response
    {
        if (!$id) {
            return $this->fail($this->message['edit']['empty']);
        }

        $this->service->checkMerchantOwner((int) $this->merchantId, (int) $id);

        return $this->success($this->service->resourceRead((int) $id));
    }

    /**
     * 获取状态枚举
     */
    public function getStatusList(): Response
    {
        return $this->success([
            ['value' => StoreCouponUserModel::STATUS_UNUSED, 'label' => '未使用'],
            ['value' => StoreCouponUserModel::STATUS_USED, 'label' => '已使用'],
            ['value' => StoreCouponUserModel::STATUS_EXPIRED, 'label' => '已过期'],
        ]);
    }

    /**
     * 获取领取方式枚举
     */
    public function getReceiveTypes(): Response
    {
        return $this->success([
            ['value' => StoreCouponUserModel::RECEIVE_TYPE_RECEIVE, 'label' => '领取'],
            ['value' => StoreCouponUserModel::RECEIVE_TYPE_SEND, 'label' => '后台发送'],
            ['value' => StoreCouponUserModel::RECEIVE_TYPE_GIVE, 'label' => '满赠'],
            ['value' => StoreCouponUserModel::RECEIVE_TYPE_NEW, 'label' => '新人'],
            ['value' => StoreCouponUserModel::RECEIVE_TYPE_BUY, 'label' => '买赠'],
            ['value' => StoreCouponUserModel::RECEIVE_TYPE_FIRST, 'label' => '首单赠送'],
        ]);
    }
}
