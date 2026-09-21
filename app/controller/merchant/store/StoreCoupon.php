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

use app\common\model\store\StoreCoupon as StoreCouponModel;
use app\common\model\store\StoreCouponRelation;
use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\common\service\store\StoreCouponService;
use app\controller\merchant\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 商户端优惠券管理控制器
 */
class StoreCoupon extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    /**
     * 优惠券服务
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
     * 列表搜索字段
     */
    protected function getIndexFields(): array
    {
        return [
            ['name', ''],
            ['status', ''],
            ['scope_type', ''],
            ['distribute_type', ''],
            ['create_time', []],
        ];
    }

    /**
     * 新增/编辑请求字段
     */
    protected function getRequestFields(): array
    {
        return [
            ['is_time_limited', 0],
            ['receive_start_time', null],
            ['receive_end_time', null],
            ['is_quantity_limited', 0],
            ['total_quantity', 0],
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
            ['scope_type', StoreCouponModel::SCOPE_TYPE_SHOP],
            ['relations', []],
            ['relation_type', StoreCouponRelation::RELATION_TYPE_ARTICLE],
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
     * 新增
     */
    public function save(): Response
    {
        $data = $this->request->postMore($this->getRequestFields());
        $this->validateCheck($data);
        $data = $this->service->prepareMerchantPayload($data, (int) $this->merchantId);

        $res = $this->service->resourceSave($data);
        if ($res) {
            return $this->success($this->message['save']['success'], is_array($res) ? $res : []);
        }

        return $this->fail($this->message['save']['fail']);
    }

    /**
     * 修改
     */
    public function update($id): Response
    {
        if (!$id) {
            return $this->fail($this->message['update']['empty']);
        }

        $this->service->checkMerchantOwner((int) $this->merchantId, (int) $id);
        $data = $this->request->postMore($this->getRequestFields());
        $this->validateCheck($data);
        $data = $this->service->prepareMerchantPayload($data, (int) $this->merchantId, (int) $id);

        if ($this->service->resourceUpdate((int) $id, $data)) {
            return $this->success($this->message['update']['success']);
        }

        return $this->fail($this->message['update']['fail']);
    }

    /**
     * 删除
     */
    public function delete($id): Response
    {
        if (!$id) {
            return $this->fail($this->message['delete']['empty']);
        }

        $this->service->checkMerchantOwner((int) $this->merchantId, (int) $id);

        if ($this->service->resourceDelete((int) $id)) {
            return $this->success($this->message['delete']['success']);
        }

        return $this->fail($this->message['delete']['fail']);
    }

    /**
     * 状态切换
     */
    public function show($id): Response
    {
        if (!$id) {
            return $this->fail($this->message['show']['empty']);
        }

        $this->service->checkMerchantOwner((int) $this->merchantId, (int) $id);
        $status = $this->request->get('status');
        if ($status === null) {
            return $this->fail($this->message['show']['status']);
        }

        if ($this->service->resourceShowUpdate((int) $id, [$this->getStatusField() => (int) $status])) {
            return $this->success($this->message['show']['success']);
        }

        return $this->fail($this->message['show']['fail']);
    }

    /**
     * 获取适用范围枚举
     */
    public function getScopeTypes(): Response
    {
        return $this->success([
            ['value' => StoreCouponModel::SCOPE_TYPE_SHOP, 'label' => '店铺券'],
            ['value' => StoreCouponModel::SCOPE_TYPE_PRODUCT, 'label' => '商品券'],
        ]);
    }

    /**
     * 获取有效期类型枚举
     */
    public function getExpireTypes(): Response
    {
        return $this->success([
            ['value' => StoreCouponModel::EXPIRE_TYPE_DAYS, 'label' => '有效天数'],
            ['value' => StoreCouponModel::EXPIRE_TYPE_RANGE, 'label' => '固定时间段'],
        ]);
    }

    /**
     * 获取发放类型枚举
     */
    public function getDistributeTypes(): Response
    {
        return $this->success([
            ['value' => StoreCouponModel::DISTRIBUTE_TYPE_RECEIVE, 'label' => '领取'],
            ['value' => StoreCouponModel::DISTRIBUTE_TYPE_FULL, 'label' => '消费满赠'],
            ['value' => StoreCouponModel::DISTRIBUTE_TYPE_NEW, 'label' => '新人'],
//            ['value' => StoreCouponModel::DISTRIBUTE_TYPE_BUY, 'label' => '买赠'],
            ['value' => StoreCouponModel::DISTRIBUTE_TYPE_FIRST, 'label' => '首单赠送'],
        ]);
    }

    /**
     * 获取关联类型枚举
     */
    public function getRelationTypes(): Response
    {
        return $this->success([
            'product_types' => [
                ['value' => StoreCouponRelation::RELATION_TYPE_ARTICLE, 'label' => '内容'],
                ['value' => StoreCouponRelation::RELATION_TYPE_SPECIAL, 'label' => '专题'],
            ],
            'category_types' => [
                ['value' => 4, 'label' => '内容品类'],
                ['value' => 5, 'label' => '专题品类'],
            ],
        ]);
    }
}
