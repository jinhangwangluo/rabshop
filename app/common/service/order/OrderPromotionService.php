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
use app\common\plugin\PluginRegistry;
use DateTimeImmutable;
use DateTimeZone;

/**
 * 可信促销能力接口，宿主不硬编码任何插件实现。
 */
final class OrderPromotionService
{
    /**
     * 保存临时购物车前，校验普通商品的促销身份。
     *
     * @param int $userId 当前业务用户编号。
     * @param array $product 活动绑定的原普通实物商品资料。
     * @param array $sku 参与抢购且已确认有效的原商品规格资料。
     * @param int $quantity 本次促销立即购买的实物件数。
     * @param array $payload 仅包含一个促销插件命名空间的活动参数。
     * @return array 促销服务校验并规范化后的可信临时结算参数。
     */
    public function normaliseCartPayload(int $userId, array $product, array $sku, int $quantity, array $payload): array
    {
        if (count($payload) !== 1 || trim((string) ($product['plugin_code'] ?? '')) !== '') {
            throw new AdminResponseException('活动商品身份无效');
        }
        $code = (string) array_key_first($payload);
        if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/', $code) || !is_array($payload[$code])) {
            throw new AdminResponseException('活动参数无效');
        }
        $service = $this->resolve($code);
        return $service->normaliseCartPayload($userId, (int) $product['id'], (string) ($sku['uniqid'] ?? ''), $quantity, $payload);
    }

    /**
     * 根据已保存的购物车身份选择报价服务，不改变原商品身份。
     *
     * @param array $cart 已加载的购物车记录及其结算身份。
     * @param array $product 购物车关联的原始商品资料，包括不可改写的插件归属。
     * @return string 当前结算使用的促销或商品插件编码，不改变原商品归属。
     */
    public function effectiveCode(array $cart, array $product): string
    {
        $promotion = trim((string) ($cart['promotion_code'] ?? ''));
        if ($promotion !== '') {
            if ((int) ($cart['is_new'] ?? 0) !== 1 || trim((string) ($product['plugin_code'] ?? '')) !== '') {
                throw new AdminResponseException('活动商品仅支持立即抢购');
            }
            $this->resolve($promotion);
            return $promotion;
        }
        return strtolower(trim((string) ($product['plugin_code'] ?? '')));
    }

    /**
     * 在调用方的支付事务中同步确认促销预留成交。
     *
     * @param int $orderId 当前业务订单的内部编号。
     * @return void 无返回值；在共享支付事务内确认促销库存预留成交。
     */
    public function paid(int $orderId): void
    {
        $this->transition($orderId, 'paid');
    }

    /**
     * 在调用方的取消事务中同步释放促销额度。
     *
     * @param int $orderId 当前业务订单的内部编号。
     * @return void 无返回值；在共享取消事务内释放促销额度和资格。
     */
    public function release(int $orderId): void
    {
        $this->transition($orderId, 'release');
    }

    /**
     * 退款后更新已安装促销插件的财务凭证。
     *
     * @param int $orderId 当前业务订单的内部编号。
     * @param array $lines 永久订单商品库存或退款状态凭证。
     * @return void 无返回值；仅同步仍可用促销插件的历史财务凭证。
     */
    public function refund(int $orderId, array $lines = []): void
    {
        $this->transition($orderId, 'refund', $lines);
    }

    /**
     * 创建订单时固化已校验促销报价的支付规则。
     *
     * @param array $items 当前结算或订单中的商品明细列表。
     * @return ?string 新促销订单的固定支付截止时间；普通订单返回空值。
     */
    public function paymentDeadline(array $items): ?string
    {
        if (count($items) === 1 && (string)($items[0]['plugin_code'] ?? '') === 'hotel') {
            // 酒店逐晚库存只在支付截止前保留；此时间由宿主创建时固化，不接收客户端时限。
            return (new DateTimeImmutable('now', new DateTimeZone('Asia/Shanghai')))
                ->modify('+15 minutes')->format('Y-m-d H:i:s');
        }
        foreach ($items as $item) {
            if (trim((string) ($item['promotion_code'] ?? '')) === '') {
                continue;
            }
            if (count($items) !== 1) {
                throw new AdminResponseException('活动商品不支持混合订单');
            }
            $snapshot = is_array($item['plugin_snapshot'] ?? null) ? $item['plugin_snapshot'] : [];
            $minutes = (int) ($snapshot['pay_expire_minutes'] ?? $item['plugin_quote']['pay_expire_minutes'] ?? 0);
            if ($minutes < 1 || $minutes > 1440) {
                throw new AdminResponseException('活动支付时限无效');
            }
            return date('Y-m-d H:i:s', time() + $minutes * 60);
        }
        return null;
    }

    /**
     * 仅允许可信的注册元数据选择服务类。
     *
     * @param string $code 可信注册表中的促销插件编码。
     * @param bool $forCheckout 是否用于结算，结算时额外要求插件启用。
     * @return object 由可信插件注册元数据选择的促销交易服务实例。
     */
    protected function resolve(string $code, bool $forCheckout = true): object
    {
        $registry = app()->make(PluginRegistry::class);
        $metadata = $registry->getPlugin($code);
        $class = (string) ($metadata['promotion_service'] ?? $metadata['manifest']['promotion_service'] ?? '');
        if (($forCheckout && !$registry->isPluginEnabled($code)) || !$metadata || !str_starts_with($class, 'Plugin\\') || !class_exists($class)) {
            throw new AdminResponseException('活动插件未安装或未启用');
        }
        $service = app()->make($class);
        if (!method_exists($service, 'normaliseCartPayload')) {
            throw new AdminResponseException('活动插件交易能力不可用');
        }
        return $service;
    }

    /**
     * 每个促销仅处理一次，使用不可变订单快照，不关联实时活动数据。
     *
     * @param int $orderId 当前业务订单的内部编号。
     * @param string $operation 需要执行的促销成交、释放或退款状态操作。
     * @param array $lines 永久订单商品库存或退款状态凭证。
     * @return void 无返回值；按永久商品快照对各促销仅执行一次状态操作。
     */
    protected function transition(int $orderId, string $operation, array $lines = []): void
    {
        $codes = [];
        foreach (app()->make(OrderGoodsService::class)->getByOrderId($orderId) as $goods) {
            $snapshot = $goods['goods_info'] ?? [];
            $snapshot = is_array($snapshot) ? $snapshot : (json_decode((string) $snapshot, true) ?: []);
            $code = (string) ($snapshot['plugin_code'] ?? '');
            if ($code !== '' && (int) ($goods['stock_reserved_num'] ?? 0) > 0) {
                $codes[$code] = true;
            }
        }
        foreach (array_keys($codes) as $code) {
            // 历史财务退款和实物退货回补必须在插件卸载后仍可处理。
            if ($operation === 'refund' && !app()->make(PluginRegistry::class)->isPluginEnabled($code)) {
                continue;
            }
            $service = $this->resolve($code, false);
            if (!method_exists($service, $operation)) {
                throw new AdminResponseException('活动插件订单处理能力不可用');
            }
            if ($operation === 'refund') {
                $service->refund($orderId, $lines);
            } else {
                $service->{$operation}($orderId);
            }
        }
    }
}
