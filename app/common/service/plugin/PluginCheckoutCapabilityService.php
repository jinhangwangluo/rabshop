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

namespace app\common\service\plugin;

use app\common\exception\AdminResponseException;

/** 宿主结算的酒店商品边界；房型价格和房态仍由酒店插件负责。 */
final class PluginCheckoutCapabilityService
{
    /** @param array $item 服务端购物车商品行。 */
    public function isHotelItem(array $item): bool
    {
        return strtolower(trim((string)($item['plugin_code'] ?? ''))) === 'hotel';
    }

    /** @param array $items 本次结算的所有商品行；酒店只允许单房型一行。 */
    public function assertCheckoutItems(array $items): void
    {
        $hotelItems = array_filter($items, fn(array $item): bool => $this->isHotelItem($item));
        if ($hotelItems !== [] && (count($hotelItems) !== 1 || count($items) !== 1)) {
            throw new AdminResponseException('酒店房型必须单独结算，一单只可订同房型多间');
        }
    }

    /** @param array $item 服务端商品行。 */
    public function isVirtualGoodsItem(array $item): bool
    {
        return $this->isHotelItem($item) && (int)($item['goods_type'] ?? 0) === 2;
    }

    /** @param array $order 宿主持久化订单快照。 */
    public function isHotelOrder(array $order): bool
    {
        $payload = $order['plugin_payload_json'] ?? [];
        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }
        return is_array($payload) && isset($payload['hotel']) && is_array($payload['hotel']);
    }
}
