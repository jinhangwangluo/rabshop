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

namespace app\common\service\order;

use app\common\exception\AdminResponseException;
use app\common\model\order\Order;
use app\common\service\BaseService;
use app\common\service\user\UserAddressService;

/**
 * 确认订单服务
 * 服务类：OrderConfirmService
 *
 * @package app\common\service\order
 */
class OrderConfirmService extends BaseService
{
    /**
     * 设置模型
     *
     * @return string 当前服务对应的模型完整类名。
     */
    protected function setModel(): string
    {
        return Order::class;
    }

    /**
     * 获取确认订单数据
     *
     * @param int $userId 用户ID
     * @param array $cartIds 购物车ID
     * @param bool $isNew 是否立即购买
     * @param array $options 配置项
     * @return array 可信购物车结算数据及重新计算的确认单金额。
     */
    public function confirm(int $userId, array $cartIds = [], bool $isNew = false, array $options = []): array
    {
        $options = $this->resolveAddressOptions($userId, $options);
        // 确认单会按用户最终选择重新计算金额，这里只加载原始结算分组，避免预览金额重复计算一次。
        // 结算预览阶段也要携带插件载荷，确保购物车事件和确认事件看到同一份
        // 用户刚编辑的字段；商品归属与历史载荷仍由 CartService 从服务端记录确定。
        $settlement = app()->make(CartService::class)->getSettlementPreview(
            $userId,
            $cartIds,
            $isNew,
            false,
            is_array($options['plugin_payload'] ?? null) ? $options['plugin_payload'] : []
        );
        $rule = $settlement['settlement_rule'] ?? [];

        if (!($rule['can_submit'] ?? false)) {
            throw new AdminResponseException((string) ($rule['message'] ?? '当前商品不能一起提交'));
        }

        $amount = app()->make(OrderAmountService::class)->buildConfirmAmount($userId, $settlement, $options);

        return [
            'settlement' => $settlement,
            'confirm' => $amount,
        ];
    }

    /**
     * 解析确认单地址选项。
     *
     * @param int $userId 用户ID。
     * @param array $options 原始确认单选项。
     * @return array 已补全地址字段的确认单选项。
     */
    protected function resolveAddressOptions(int $userId, array $options): array
    {
        if (is_array($options['plugin_payload']['hotel'] ?? null)) {
            // 酒店是单独结算的虚拟商品，不请求或返回用户的默认收货地址。
            return $options;
        }
        $addressId = (int)($options['address_id'] ?? 0);
        $hasInlineAddress = trim((string)($options['province'] ?? '')) !== ''
            || trim((string)($options['city'] ?? '')) !== ''
            || trim((string)($options['district'] ?? '')) !== ''
            || trim((string)($options['detail'] ?? '')) !== ''
            || trim((string)($options['user_address'] ?? '')) !== '';

        $address = null;
        $addressService = app()->make(UserAddressService::class);
        if ($addressId > 0) {
            $address = $addressService->getUserAddressDetail($userId, $addressId);
        } elseif (!$hasInlineAddress) {
            $address = $addressService->getDefaultUserAddress($userId);
        }

        if ($address === null) {
            return $options;
        }

        return array_merge($options, [
            'address_id' => (int)($address['id'] ?? 0),
            'real_name' => (string)($address['real_name'] ?? ''),
            'user_phone' => (string)($address['phone'] ?? ''),
            'province' => (string)($address['province'] ?? ''),
            'city' => (string)($address['city'] ?? ''),
            'district' => (string)($address['district'] ?? ''),
            'province_id' => (int)($address['province_id'] ?? 0),
            'city_id' => (int)($address['city_id'] ?? 0),
            'district_id' => (int)($address['district_id'] ?? 0),
            'area_id' => (int)($address['area_id'] ?? 0),
            'detail' => (string)($address['detail'] ?? ''),
            'user_address' => (string)($address['user_address'] ?? ''),
        ]);
    }
}
