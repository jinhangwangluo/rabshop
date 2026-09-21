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
use app\common\service\user\UserAssetService;
use app\common\service\user\UserMemberService;

/**
 * 订单插件报价编排器。
 *
 * 该类只负责组装宿主可信上下文、调用插件计算器并把插件报价合并
 * 回购物车/确认单；具体商品规则必须由插件自己的 calculator 实现。
 */
final class PluginOrderQuoteService
{
    /**
     * 计算一个订单阶段的插件报价。
     *
     * @param string $eventName 计算事件名。
     * @param int $userId 用户 ID。
     * @param array<int, array<string, mixed>> $groups 商品分组。
     * @param array<string, mixed> $options 客户端选项。
     * @return array{quotes: array<string, array<string, mixed>>, payload: array<string, mixed>, plugin_codes: list<string>}
     */
    public function calculate(string $eventName, int $userId, array $groups, array $options = []): array
    {
        $items = $this->flattenItems($groups);
        $pluginCodes = $this->extractPluginCodes($items);
        if ($pluginCodes === []) {
            return ['quotes' => [], 'payload' => [], 'plugin_codes' => []];
        }

        $pluginPayload = $this->normalisePluginPayload($options['plugin_payload'] ?? [], $pluginCodes);
        // 当调用方没有显式提交页面载荷时，使用购物车行中由服务端持久化的
        // 命名空间作为事件根载荷；每个商品行仍保留自己的 plugin_payload，
        // 因此不会把同一插件的多条商品配置错误合并成一条报价输入。
        foreach ($items as $item) {
            $itemPayload = is_array($item['plugin_payload'] ?? null) ? $item['plugin_payload'] : [];
            foreach ($itemPayload as $code => $value) {
                $code = strtolower(trim((string)$code));
                if (in_array($code, $pluginCodes, true) && !array_key_exists($code, $pluginPayload) && is_array($value)) {
                    $pluginPayload[$code] = $value;
                }
            }
        }
        $member = [];
        try {
            $member = app()->make(UserMemberService::class)
                ->getMemberRuntimeState($userId);
        } catch (\Throwable) {
            // 事件核心单测和无会员表的安装阶段允许缺省会员上下文；插件仍需自行决定是否使用会员价。
        }
        $assets = [];
        $assetsAvailable = false;
        try {
            $assets['points'] = max(0, (int)app()->make(UserAssetService::class)->getAssetValue(
                $userId,
                UserAssetService::TYPE_POINTS
            ));
            $assetsAvailable = true;
        } catch (\Throwable) {
            // 资产服务不可用时不伪造余额；若插件实际使用积分，下面会失败关闭。
        }
        $payload = [
            'scene' => $eventName,
            'user_id' => $userId,
            'groups' => $groups,
            'items' => $items,
            'plugin_payload' => $pluginPayload,
            'member' => $member,
            'assets' => $assets,
            'options' => $this->stripInternalOptions($options),
        ];
        $result = app()->make(PluginBusinessEventService::class)->dispatchCalculation(
            $eventName,
            $payload,
            [
                'surface' => (string)($options['surface'] ?? 'mobile'),
                'merchant_id' => (int)($options['merchant_id'] ?? 0),
            ]
        );
        $quotes = is_array($result['quotes'] ?? null) ? $result['quotes'] : [];
        $claimedCartIds = [];
        foreach ($pluginCodes as $pluginCode) {
            if (!isset($quotes[$pluginCode]) || !is_array($quotes[$pluginCode])) {
                throw new AdminResponseException('插件未返回商品报价：' . $pluginCode);
            }
            $this->validateQuote($pluginCode, $quotes[$pluginCode], $items, $member, $assets, $pluginPayload);
            if (!$assetsAvailable && $this->quotePointsUsed($quotes[$pluginCode]) > 0) {
                throw new AdminResponseException('无法验证插件积分余额，请稍后重试');
            }
            foreach ($quotes[$pluginCode]['items'] as $quoteItem) {
                $cartId = (int)($quoteItem['cart_id'] ?? 0);
                if (isset($claimedCartIds[$cartId]) && $claimedCartIds[$cartId] !== $pluginCode) {
                    throw new AdminResponseException('同一商品被多个插件接管：' . $cartId);
                }
                $claimedCartIds[$cartId] = $pluginCode;
            }
        }
        return [
            'quotes' => $quotes,
            'payload' => $payload,
            'plugin_codes' => $pluginCodes,
        ];
    }

    /**
     * 将报价应用到购物车分组。
     *
     * @param array<string, mixed> $settlement
     * @param array{quotes?: array<string, array<string, mixed>>} $calculation
     * @return array<string, mixed>
     */
    public function applyToSettlement(array $settlement, array $calculation): array
    {
        $quotes = is_array($calculation['quotes'] ?? null) ? $calculation['quotes'] : [];
        if ($quotes === []) {
            return $settlement;
        }

        foreach ($settlement['groups'] ?? [] as $groupIndex => $group) {
            $groupItems = [];
            foreach ($group['items'] ?? [] as $item) {
                $groupItems[] = $this->applyItemQuote($item, $quotes);
            }
            $settlement['groups'][$groupIndex]['items'] = $groupItems;
            $pluginCode = $this->singlePluginCode($groupItems);
            if ($pluginCode !== '' && isset($quotes[$pluginCode]['summary']) && is_array($quotes[$pluginCode]['summary'])) {
                $pluginSummary = $this->normalisePluginSummary($quotes[$pluginCode]['summary']);
                $settlement['groups'][$groupIndex]['summary'] = array_merge(
                    $settlement['groups'][$groupIndex]['summary'] ?? [],
                    $pluginSummary
                );
            }
            $settlement['groups'][$groupIndex]['total_amount'] = $this->sumItems($groupItems, 'line_total');
        }

        $settlement['summary']['total_amount'] = $this->sumGroups($settlement['groups'], 'total_amount');
        $settlement['plugin_quotes'] = $quotes;
        return $settlement;
    }

    /**
     * 将报价应用到确认单，覆盖插件商品的价格和优惠字段。
     *
     * @param array<string, mixed> $confirm
     * @param array{quotes?: array<string, array<string, mixed>>} $calculation
     * @return array<string, mixed>
     */
    public function applyToConfirm(array $confirm, array $calculation): array
    {
        $quotes = is_array($calculation['quotes'] ?? null) ? $calculation['quotes'] : [];
        if ($quotes === []) {
            return $confirm;
        }

        foreach ($confirm['groups'] ?? [] as $groupIndex => $group) {
            $items = [];
            foreach ($group['items'] ?? [] as $item) {
                $items[] = $this->applyItemQuote($item, $quotes);
            }
            $confirm['groups'][$groupIndex]['items'] = $items;
            $pluginCode = $this->singlePluginCode($items);
            if ($pluginCode !== '' && isset($quotes[$pluginCode]['summary']) && is_array($quotes[$pluginCode]['summary'])) {
                $pluginSummary = $this->normalisePluginSummary($quotes[$pluginCode]['summary']);
                $confirm['groups'][$groupIndex]['summary'] = array_merge(
                    $confirm['groups'][$groupIndex]['summary'] ?? [],
                    $pluginSummary
                );
            }
            $summary = $confirm['groups'][$groupIndex]['summary'] ?? [];
            $postage = (float)($summary['postage'] ?? 0);
            $confirm['groups'][$groupIndex]['summary']['pay_amount'] = $this->sumItems($items, 'pay_amount') + $postage;
            $confirm['groups'][$groupIndex]['summary']['total_amount'] = $this->sumItems($items, 'line_total') + $postage;
            $confirm['groups'][$groupIndex]['order_type'] = (int)($items[0]['order_type'] ?? 0);
        }

        $confirm['items'] = $confirm['groups'][0]['items'] ?? [];
        $confirm['summary'] = $this->mergeSummaryFromGroups($confirm['groups'] ?? [], $confirm['summary'] ?? []);
        $confirm['plugin_quotes'] = $quotes;
        return $confirm;
    }

    /**
     * 返回是否包含插件商品。
     *
     * @param array<int, array<string, mixed>> $groups
     */
    public function hasPluginItems(array $groups): bool
    {
        return $this->extractPluginCodes($this->flattenItems($groups)) !== [];
    }

    /**
     * 汇总一次插件报价实际使用的积分数量。
     *
     * 宿主积分会在金额计算后按可支付金额截断，因此只能在宿主生成实际
     * `use_points` 后再与该值做整单余额校验。
     *
     * @param array{quotes?: array<string, array<string, mixed>>} $calculation
     */
    public function pointsUsed(array $calculation): int
    {
        $total = 0;
        foreach ($calculation['quotes'] ?? [] as $quote) {
            if (is_array($quote)) {
                $total += $this->quotePointsUsed($quote);
            }
        }
        return $total;
    }

    /**
     * @param array<int, array<string, mixed>> $groups
     * @return list<array<string, mixed>>
     */
    private function flattenItems(array $groups): array
    {
        $items = [];
        foreach ($groups as $group) {
            foreach ($group['items'] ?? [] as $item) {
                if (is_array($item)) {
                    $items[] = $item;
                }
            }
        }
        return $items;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return list<string>
     */
    private function extractPluginCodes(array $items): array
    {
        $codes = [];
        foreach ($items as $item) {
            $code = strtolower(trim((string)($item['plugin_code'] ?? '')));
            if ($code !== '') {
                $codes[$code] = $code;
            }
        }
        return array_values($codes);
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, array<string, mixed>> $quotes
     * @return array<string, mixed>
     */
    private function applyItemQuote(array $item, array $quotes): array
    {
        $pluginCode = strtolower(trim((string)($item['plugin_code'] ?? '')));
        if ($pluginCode === '' || !isset($quotes[$pluginCode])) {
            return $item;
        }
        $cartId = (int)($item['cart_id'] ?? $item['id'] ?? 0);
        $quoteItems = is_array($quotes[$pluginCode]['items'] ?? null) ? $quotes[$pluginCode]['items'] : [];
        foreach ($quoteItems as $quoteItem) {
            if (!is_array($quoteItem) || (int)($quoteItem['cart_id'] ?? 0) !== $cartId) {
                continue;
            }
            foreach ([
                'price', 'original_price', 'line_total', 'member_price', 'effective_price',
                'member_line_total', 'use_member_price', 'member_discount_amount',
                'points_deduct_amount', 'platform_coupon_amount', 'merchant_coupon_amount',
                'points_used', 'pay_amount', 'plugin_snapshot', 'plugin_quote_fingerprint', 'plugin_discount',
            ] as $field) {
                if (array_key_exists($field, $quoteItem)) {
                    $item[$field] = $quoteItem[$field];
                }
            }
            $quoteFingerprint = trim((string)($quotes[$pluginCode]['fingerprint'] ?? $quotes[$pluginCode]['plugin_quote_fingerprint'] ?? ''));
            if ($quoteFingerprint !== '' && trim((string)($item['plugin_quote_fingerprint'] ?? '')) === '') {
                $item['plugin_quote_fingerprint'] = $quoteFingerprint;
            }
            // 把插件汇总快照挂到商品行，拆单重汇总时仍能识别插件费用。
            $item['plugin_summary'] = is_array($quotes[$pluginCode]['summary'] ?? null)
                ? $quotes[$pluginCode]['summary']
                : [];
            $item['plugin_code'] = $pluginCode;
            break;
        }
        return $item;
    }

    /**
     * @param string $pluginCode
     * @param array<string, mixed> $quote
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $member
     * @param array<string, mixed> $assets
     * @param array<string, mixed> $pluginPayload 本次事件根载荷。
     */
    private function validateQuote(
        string $pluginCode,
        array $quote,
        array $items,
        array $member = [],
        array $assets = [],
        array $pluginPayload = []
    ): void
    {
        $expected = [];
        $fingerprintSources = [];
        foreach ($items as $item) {
            if (strtolower(trim((string)($item['plugin_code'] ?? ''))) === $pluginCode) {
                $cartId = (int)($item['cart_id'] ?? $item['id'] ?? 0);
                if ($cartId <= 0 || isset($expected[$cartId])) {
                    throw new AdminResponseException('插件商品行标识重复：' . $pluginCode);
                }
                $expected[$cartId] = [
                    'goods_id' => (int)($item['goods_id'] ?? 0),
                    'sku_id' => (int)($item['sku_id'] ?? 0),
                    'merchant_id' => (int)($item['merchant_id'] ?? 0),
                    'cart_num' => max(1, (int)($item['cart_num'] ?? 1)),
                ];
                $sourceItem = $item;
                if (array_key_exists($pluginCode, $pluginPayload)) {
                    $sourceItemPayload = is_array($sourceItem['plugin_payload'] ?? null)
                        ? $sourceItem['plugin_payload']
                        : [];
                    $sourceItemPayload[$pluginCode] = $pluginPayload[$pluginCode];
                    $sourceItem['plugin_payload'] = $sourceItemPayload;
                }
                $fingerprintSources[] = $sourceItem;
            }
        }
        $seen = [];
        $quoteItems = $quote['items'] ?? [];
        if (!is_array($quoteItems)) {
            throw new AdminResponseException('插件报价商品行格式错误：' . $pluginCode);
        }
        $pointsUsed = 0;
        foreach ($quoteItems as $item) {
            if (!is_array($item)) {
                throw new AdminResponseException('插件报价商品行格式错误：' . $pluginCode);
            }
            $cartId = (int)($item['cart_id'] ?? 0);
            if (!array_key_exists($cartId, $expected) || isset($seen[$cartId])) {
                throw new AdminResponseException('插件报价商品行不匹配：' . $pluginCode);
            }
            $seen[$cartId] = true;
            $identity = $expected[$cartId];
            foreach (['goods_id', 'sku_id', 'merchant_id'] as $field) {
                if (!array_key_exists($field, $item)) {
                    throw new AdminResponseException('插件报价缺少商品身份字段：' . $pluginCode);
                }
                if ((int)$item[$field] !== (int)$identity[$field]) {
                    throw new AdminResponseException('插件报价商品身份不匹配：' . $pluginCode);
                }
            }
            if (!array_key_exists('cart_num', $item)) {
                throw new AdminResponseException('插件报价缺少商品数量：' . $pluginCode);
            }
            if ((int)$item['cart_num'] !== (int)$identity['cart_num']) {
                throw new AdminResponseException('插件报价商品数量不匹配：' . $pluginCode);
            }
            foreach ([
                'price', 'original_price', 'line_total', 'pay_amount', 'points_deduct_amount',
                'member_discount_amount', 'merchant_coupon_amount', 'platform_coupon_amount',
            ] as $field) {
                if (in_array($field, ['price', 'line_total', 'pay_amount'], true) && !array_key_exists($field, $item)) {
                    throw new AdminResponseException('插件报价缺少金额字段：' . $pluginCode);
                }
                if (array_key_exists($field, $item) && (!is_numeric($item[$field]) || (float)$item[$field] < 0)) {
                    throw new AdminResponseException('插件报价金额非法：' . $pluginCode);
                }
            }
            if (array_key_exists('use_member_price', $item) && (bool)$item['use_member_price'] && !($member['is_member'] ?? false)) {
                throw new AdminResponseException('非会员不能使用插件会员价：' . $pluginCode);
            }
            if (array_key_exists('points_used', $item)
                && (!is_numeric($item['points_used']) || (int)$item['points_used'] < 0)
            ) {
                throw new AdminResponseException('插件报价积分数量非法：' . $pluginCode);
            }
            $itemPoints = max(0, (int)($item['points_used'] ?? 0));
            if ((float)($item['points_deduct_amount'] ?? 0) > 0 && !array_key_exists('points_used', $item)) {
                throw new AdminResponseException('插件报价缺少积分使用数量：' . $pluginCode);
            }
            $pointsUsed += $itemPoints;
            $quantity = (int)$identity['cart_num'];
            if (array_key_exists('price', $item) && array_key_exists('line_total', $item)) {
                $expectedLine = round((float)$item['price'] * $quantity, 2);
                if (abs($expectedLine - (float)$item['line_total']) > 0.01) {
                    throw new AdminResponseException('插件报价行金额不一致：' . $pluginCode);
                }
            }
            if (array_key_exists('line_total', $item) && array_key_exists('pay_amount', $item)) {
                $discount = (float)($item['member_discount_amount'] ?? 0)
                    + (float)($item['merchant_coupon_amount'] ?? 0)
                    + (float)($item['platform_coupon_amount'] ?? 0)
                    + (float)($item['points_deduct_amount'] ?? 0);
                $expectedPay = max(0, round((float)$item['line_total'] - $discount, 2));
                if (abs((float)$item['pay_amount'] - $expectedPay) > 0.01) {
                    throw new AdminResponseException('插件报价应付金额非法：' . $pluginCode);
                }
            }
        }
        if (count($seen) !== count($expected)) {
            throw new AdminResponseException('插件报价缺少商品行：' . $pluginCode);
        }
        if (array_key_exists('points', $assets) && $pointsUsed > (int)$assets['points']) {
            throw new AdminResponseException('插件报价使用积分超过用户余额：' . $pluginCode);
        }

        if (isset($quote['summary']) && !is_array($quote['summary'])) {
            throw new AdminResponseException('插件报价汇总格式错误：' . $pluginCode);
        }
        if (is_array($quote['summary'] ?? null)) {
            foreach (['original_amount', 'goods_amount', 'total_amount', 'discount_amount', 'postage', 'extra_amount', 'fee_amount', 'pay_amount', 'points_deduct_amount', 'member_discount_amount', 'merchant_coupon_amount', 'platform_coupon_amount', 'settlement_pay_amount'] as $field) {
                if (array_key_exists($field, $quote['summary']) && (!is_numeric($quote['summary'][$field]) || (float)$quote['summary'][$field] < 0)) {
                    throw new AdminResponseException('插件报价汇总金额非法：' . $pluginCode);
                }
            }
            $quoteItemTotal = $this->sumItems($quoteItems, 'line_total');
            $quoteItemPay = $this->sumItems($quoteItems, 'pay_amount');
            $quoteDiscount = $this->sumItemDiscounts($quoteItems);
            if (array_key_exists('goods_amount', $quote['summary']) && abs($quoteItemTotal - (float)$quote['summary']['goods_amount']) > 0.01) {
                throw new AdminResponseException('插件报价商品金额汇总不一致：' . $pluginCode);
            }
            if (array_key_exists('total_amount', $quote['summary']) && array_key_exists('postage', $quote['summary'])) {
                $expectedTotal = round($quoteItemTotal + $this->summaryFee($quote['summary']), 2);
                if (abs($expectedTotal - (float)$quote['summary']['total_amount']) > 0.01) {
                    throw new AdminResponseException('插件报价汇总金额不一致：' . $pluginCode);
                }
            }
            if (array_key_exists('discount_amount', $quote['summary']) && abs($quoteDiscount - (float)$quote['summary']['discount_amount']) > 0.01) {
                throw new AdminResponseException('插件报价优惠汇总不一致：' . $pluginCode);
            }
            if (array_key_exists('pay_amount', $quote['summary']) && array_key_exists('postage', $quote['summary'])) {
                $expectedPay = round($quoteItemPay + $this->summaryFee($quote['summary']), 2);
                if (abs($expectedPay - (float)$quote['summary']['pay_amount']) > 0.01) {
                    throw new AdminResponseException('插件报价应付汇总不一致：' . $pluginCode);
                }
            }
        }

        $fingerprint = trim((string)($quote['fingerprint'] ?? $quote['plugin_quote_fingerprint'] ?? ''));
        if ($fingerprint === '') {
            foreach ($quoteItems as $item) {
                if (is_array($item) && trim((string)($item['plugin_quote_fingerprint'] ?? '')) !== '') {
                    $fingerprint = trim((string)$item['plugin_quote_fingerprint']);
                    break;
                }
            }
        }
        if ($fingerprint === '') {
            throw new AdminResponseException('插件报价缺少指纹：' . $pluginCode);
        }
        $expectedFingerprint = self::buildFingerprint($pluginCode, $quote, $fingerprintSources);
        if (!hash_equals($expectedFingerprint, $fingerprint)) {
            throw new AdminResponseException('插件报价指纹无效：' . $pluginCode);
        }
    }

    /**
     * 生成插件报价指纹。插件和宿主使用同一算法，指纹覆盖可信商品身份、
     * 插件载荷、报价行和汇总金额，避免确认页金额被重放。
     *
     * @param array<string, mixed> $quote
     * @param array<int, array<string, mixed>> $sourceItems
     */
    public static function buildFingerprint(string $pluginCode, array $quote, array $sourceItems): string
    {
        $items = [];
        foreach ($quote['items'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $copy = $item;
            unset($copy['fingerprint'], $copy['plugin_quote_fingerprint']);
            $items[(int)($copy['cart_id'] ?? 0)] = $copy;
        }
        ksort($items);
        $sources = [];
        foreach ($sourceItems as $item) {
            $cartId = (int)($item['cart_id'] ?? $item['id'] ?? 0);
            $sources[$cartId] = [
                'cart_id' => $cartId,
                'goods_id' => (int)($item['goods_id'] ?? 0),
                'sku_id' => (int)($item['sku_id'] ?? 0),
                'merchant_id' => (int)($item['merchant_id'] ?? 0),
                'cart_num' => max(1, (int)($item['cart_num'] ?? 1)),
                'plugin_payload' => is_array($item['plugin_payload'] ?? null) ? $item['plugin_payload'] : [],
            ];
        }
        ksort($sources);
        $data = [
            'plugin_code' => strtolower(trim($pluginCode)),
            'sources' => $sources,
            'items' => $items,
            'summary' => $quote['summary'] ?? [],
        ];
        $json = json_encode(self::canonicalise($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        return hash('sha256', $json === false ? '{}' : $json);
    }

    /** @param mixed $value */
    private static function canonicalise(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map([self::class, 'canonicalise'], $value);
        }
        ksort($value);
        foreach ($value as $key => $child) {
            $value[$key] = self::canonicalise($child);
        }
        return $value;
    }

    /** @param array<int, array<string, mixed>> $items */
    private function sumItemDiscounts(array $items): float
    {
        $sum = 0.0;
        foreach ($items as $item) {
            foreach (['member_discount_amount', 'merchant_coupon_amount', 'platform_coupon_amount', 'points_deduct_amount'] as $field) {
                $sum = round($sum + (float)($item[$field] ?? 0), 2);
            }
        }
        return $sum;
    }

    /**
     * 汇总插件报价使用的积分数量。
     *
     * @param array<string, mixed> $quote
     */
    private function quotePointsUsed(array $quote): int
    {
        $points = 0;
        foreach ($quote['items'] ?? [] as $item) {
            if (is_array($item)) {
                $points += max(0, (int)($item['points_used'] ?? 0));
            }
        }
        return $points;
    }

    /**
     * @param mixed $payload
     * @param list<string> $pluginCodes
     * @return array<string, mixed>
     */
    private function normalisePluginPayload(mixed $payload, array $pluginCodes): array
    {
        if (!is_array($payload)) {
            return [];
        }
        $result = [];
        foreach ($payload as $code => $value) {
            $code = strtolower(trim((string)$code));
            if (!in_array($code, $pluginCodes, true)) {
                throw new AdminResponseException('插件载荷命名空间无效：' . $code);
            }
            if (!is_array($value)) {
                throw new AdminResponseException('插件载荷格式错误：' . $code);
            }
            $result[$code] = $value;
        }
        return $result;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function stripInternalOptions(array $options): array
    {
        unset($options['surface'], $options['merchant_id'], $options['_plugin_calculated']);
        return $options;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function singlePluginCode(array $items): string
    {
        $codes = $this->extractPluginCodes($items);
        return count($codes) === 1 ? $codes[0] : '';
    }

    /**
     * @param array<string, mixed> $summary
     * @return array<string, mixed>
     */
    private function numericSummary(array $summary): array
    {
        foreach (['original_amount', 'goods_amount', 'total_amount', 'discount_amount', 'postage', 'extra_amount', 'fee_amount', 'pay_amount', 'points_deduct_amount', 'member_discount_amount', 'platform_coupon_amount', 'merchant_coupon_amount', 'settlement_pay_amount'] as $field) {
            if (array_key_exists($field, $summary) && (!is_numeric($summary[$field]) || (float)$summary[$field] < 0)) {
                throw new AdminResponseException('插件报价汇总金额非法');
            }
        }
        return $summary;
    }

    /**
     * 将插件附加费用并入统一 postage 字段，避免确认单合并时丢失费用。
     *
     * @param array<string, mixed> $summary
     * @return array<string, mixed>
     */
    private function normalisePluginSummary(array $summary): array
    {
        $summary = $this->numericSummary($summary);
        $summary['postage'] = round($this->summaryFee($summary), 2);
        return $summary;
    }

    /**
     * 读取插件报价中的全部费用。
     *
     * @param array<string, mixed> $summary
     */
    private function summaryFee(array $summary): float
    {
        return round(
            (float)($summary['postage'] ?? 0)
            + (float)($summary['extra_amount'] ?? $summary['fee_amount'] ?? 0),
            2
        );
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function sumItems(array $items, string $field): float
    {
        $sum = 0.0;
        foreach ($items as $item) {
            $sum = round($sum + (float)($item[$field] ?? $item['line_total'] ?? 0), 2);
        }
        return $sum;
    }

    /**
     * @param array<int, array<string, mixed>> $groups
     */
    private function sumGroups(array $groups, string $field): float
    {
        $sum = 0.0;
        foreach ($groups as $group) {
            $sum = round($sum + (float)($group[$field] ?? ($group['summary'][$field] ?? 0)), 2);
        }
        return $sum;
    }

    /**
     * @param array<int, array<string, mixed>> $groups
     * @param array<string, mixed> $base
     * @return array<string, mixed>
     */
    private function mergeSummaryFromGroups(array $groups, array $base): array
    {
        $summary = $base;
        foreach (['original_amount', 'goods_amount', 'total_amount', 'discount_amount', 'postage', 'pay_amount', 'points_deduct_amount', 'member_discount_amount', 'platform_coupon_amount', 'merchant_coupon_amount', 'settlement_pay_amount'] as $field) {
            $summary[$field] = $this->sumGroups($groups, $field);
        }
        return $summary;
    }
}
