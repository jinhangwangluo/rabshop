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

namespace app\common\service\message;

use app\common\enum\ConfigKeyEnum;
use app\common\model\Base;
use app\common\service\BaseService;
use Throwable;

/**
 * 消息推送模板渲染服务。
 */
class MessagePushRenderService extends BaseService
{
    /**
     * 小程序订阅消息字段类型长度限制。
     *
     * @var array<string, int>
     */
    protected array $miniappFieldMaxLengths = [
        'thing' => 20,
        'number' => 32,
        'letter' => 32,
        'symbol' => 5,
        'character_string' => 32,
        'phone_number' => 17,
        'car_number' => 8,
        'phrase' => 5,
    ];

    /**
     * 设置模型。渲染服务不依赖具体表。
     */
    protected function setModel(): string
    {
        return Base::class;
    }

    /**
     * 渲染文本模板。
     *
     * 同时支持 {{变量名}} 与 ${变量名} 两种占位符：短信渠道使用阿里云 ${变量} 格式，
     * Webhook 等其他渠道继续使用 {{变量}} 格式。
     */
    public function renderText(string $template, array $payload): string
    {
        $variables = $this->buildVariables($payload);

        return preg_replace_callback('/(?:\{\{|\$\{)\s*([a-zA-Z0-9_]+)\s*(?:\}\}|\})/', function ($matches) use ($variables) {
            $key = (string)($matches[1] ?? '');
            return (string)($variables[$key] ?? '');
        }, $template) ?? $template;
    }

    /**
     * 渲染模板字段映射。
     */
    public function renderFieldMap(array $fieldMap, array $payload, bool $withColor = false): array
    {
        $variables = $this->buildVariables($payload);
        $rows = $this->normalizeFieldMapRows($fieldMap);
        $result = [];

        foreach ($rows as $row) {
            $field = trim((string)($row['field'] ?? ''));
            if ($field === '') {
                continue;
            }

            $valueType = (string)($row['value_type'] ?? $row['type'] ?? 'text');
            $rawValue = (string)($row['value'] ?? '');
            $value = $valueType === 'variable'
                ? (string)($variables[$rawValue] ?? '')
                : $this->renderText($rawValue, $payload);

            $item = ['value' => $value];
            $color = trim((string)($row['color'] ?? ''));
            if ($withColor && $color !== '') {
                $item['color'] = $color;
            }

            $result[$field] = $item;
        }

        return $result;
    }

    /**
     * 渲染小程序订阅消息字段映射，并按微信字段类型长度限制截断。
     */
    public function renderMiniappFieldMap(array $fieldMap, array $payload): array
    {
        $result = $this->renderFieldMap($fieldMap, $payload);

        foreach ($result as $field => $item) {
            if (!is_array($item)) {
                continue;
            }

            $item['value'] = $this->limitMiniappFieldValue($field, (string)($item['value'] ?? ''));
            $result[$field] = $item;
        }

        return $result;
    }

    /**
     * 解析短信模板参数。
     *
     * 短信模板内容使用阿里云 ${变量} 格式，同时兼容历史数据中的 {{变量}} 格式。
     */
    public function buildSmsTemplateParams(string $contentTemplate, array $payload): array
    {
        $variables = $this->buildVariables($payload);
        preg_match_all('/(?:\{\{|\$\{)\s*([a-zA-Z0-9_]+)\s*(?:\}\}|\})/', $contentTemplate, $matches);
        $keys = array_values(array_unique($matches[1] ?? []));
        $params = [];

        foreach ($keys as $key) {
            $params[$key] = (string)($variables[$key] ?? '');
        }

        return $params;
    }

    /**
     * 构建变量数据。
     */
    public function buildVariables(array $payload): array
    {
        $siteName = (string)($payload['site_name'] ?? '');
        if ($siteName === '') {
            $siteName = (string)db_config(ConfigKeyEnum::SITE_NAME, '乖乖兔多商户平台');
        }
        $now = date('Y-m-d H:i:s');
        $title = trim((string)($payload['title'] ?? $payload['content_title'] ?? ''));

        return array_merge($this->stringifyScalarPayload($payload), [
            'site_name' => $siteName,
            'now_time' => $now,
            'order_id' => (string)($payload['order_id'] ?? $payload['id'] ?? ''),
            'order_sn' => (string)($payload['order_sn'] ?? ''),
            'product_name' => $this->resolveProductName($payload),
            'pay_amount' => $this->formatAmount($payload['pay_amount'] ?? $payload['amount'] ?? 0),
            'order_amount' => $this->formatAmount($payload['order_amount'] ?? $payload['pay_amount'] ?? 0),
            'refund_sn' => (string)($payload['refund_sn'] ?? ''),
            'refund_amount' => $this->formatAmount($payload['refund_amount'] ?? 0),
            'refund_reason' => (string)($payload['refund_reason'] ?? $payload['reason'] ?? ''),
            'fail_reason' => (string)($payload['fail_reason'] ?? $payload['error_message'] ?? $payload['refund_reason'] ?? ''),
            'withdraw_id' => (string)($payload['withdraw_id'] ?? $payload['id'] ?? ''),
            'amount' => $this->formatAmount($payload['amount'] ?? 0),
            'actual_amount' => $this->formatAmount($payload['actual_amount'] ?? $payload['amount'] ?? 0),
            'fee' => $this->formatAmount($payload['fee'] ?? 0),
            'bank_name' => (string)($payload['bank_name'] ?? ''),
            'audit_status' => (string)($payload['audit_status'] ?? ''),
            'audit_status_text' => (string)($payload['audit_status_text'] ?? ''),
            'audit_remark' => (string)($payload['audit_remark'] ?? $payload['remark'] ?? ''),
            'audit_time' => (string)($payload['audit_time'] ?? $now),
            'content_id' => (string)($payload['content_id'] ?? $payload['relation_id'] ?? $payload['id'] ?? ''),
            'content_title' => $title,
            'live_id' => (string)($payload['live_id'] ?? $payload['relation_id'] ?? $payload['id'] ?? ''),
            'live_title' => (string)($payload['live_title'] ?? $title),
            'anchor_name' => (string)($payload['anchor_name'] ?? ''),
            'user_id' => (string)($payload['user_id'] ?? $payload['uid'] ?? ''),
            'user_nickname' => (string)($payload['user_nickname'] ?? $payload['nickname'] ?? ''),
            'user_phone' => (string)($payload['user_phone'] ?? $payload['phone'] ?? ''),
            'merchant_id' => (string)($payload['merchant_id'] ?? $payload['mer_id'] ?? ''),
            'merchant_name' => (string)($payload['merchant_name'] ?? $payload['mer_name'] ?? ''),
            'merchant_phone' => (string)($payload['merchant_phone'] ?? $payload['contact_phone'] ?? $payload['mer_phone'] ?? ''),
        ]);
    }

    /**
     * 解析订单商品名称摘要。
     */
    public function resolveProductName(array $payload): string
    {
        foreach (['product_name', 'goods_name', 'product_title', 'goods_title'] as $key) {
            $value = trim((string)($payload[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        foreach (['goods', 'goods_list', 'goodsList', 'order_goods', 'order_goods_list', 'orderGoods', 'items', 'cart_info', 'cartInfo'] as $key) {
            $list = $payload[$key] ?? [];
            if (!is_array($list)) {
                continue;
            }

            $summary = $this->resolveProductNameFromList($list);
            if ($summary !== '') {
                return $summary;
            }
        }

        return '';
    }

    /**
     * 标量载荷也允许直接作为变量使用。
     */
    protected function stringifyScalarPayload(array $payload): array
    {
        $result = [];
        foreach ($payload as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $result[(string)$key] = (string)$value;
            }
        }

        return $result;
    }

    /**
     * 规范化字段映射行。
     */
    protected function normalizeFieldMapRows(array $fieldMap): array
    {
        $isList = array_keys($fieldMap) === range(0, count($fieldMap) - 1);
        if ($isList) {
            return $fieldMap;
        }

        $rows = [];
        foreach ($fieldMap as $field => $config) {
            if (is_array($config)) {
                $rows[] = array_merge(['field' => $field], $config);
                continue;
            }
            $rows[] = [
                'field' => $field,
                'value_type' => 'text',
                'value' => (string)$config,
            ];
        }

        return $rows;
    }

    /**
     * 从商品列表中解析商品名称摘要。
     */
    protected function resolveProductNameFromList(array $list): string
    {
        if ($list === []) {
            return '';
        }

        foreach (['product_name', 'goods_name', 'product_title', 'goods_title', 'title'] as $key) {
            if (isset($list[$key])) {
                $list = [$list];
                break;
            }
        }

        if (isset($list['goods_info']) || isset($list['product'])) {
            $list = [$list];
        }

        $names = [];
        foreach ($list as $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = $this->resolveProductNameFromItem($item);
            if ($name !== '') {
                $names[] = $name;
            }
        }

        $names = array_values(array_unique($names));
        if ($names === []) {
            return '';
        }

        $summary = implode('、', array_slice($names, 0, 2));
        if (count($names) > 2) {
            $summary .= ' 等' . count($names) . '件';
        }

        return $summary;
    }

    /**
     * 从单条商品快照中解析商品名称。
     */
    protected function resolveProductNameFromItem(array $item): string
    {
        foreach (['product_name', 'goods_name', 'product_title', 'goods_title', 'title'] as $key) {
            $value = trim((string)($item[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        foreach (['goods_info', 'product', 'goods'] as $key) {
            if (!is_array($item[$key] ?? null)) {
                continue;
            }

            $value = $this->resolveProductNameFromItem($item[$key]);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * 按小程序订阅消息字段类型截断字段值。
     */
    protected function limitMiniappFieldValue(string $field, string $value): string
    {
        $value = trim(preg_replace('/[\r\n\t]+/u', ' ', $value) ?? $value);
        $type = $this->resolveMiniappFieldType($field);
        if ($type === '') {
            return $value;
        }

        if ($type === 'name') {
            return mb_substr($value, 0, $this->isAscii($value) ? 20 : 10);
        }

        $limit = $this->miniappFieldMaxLengths[$type] ?? 0;
        if ($limit <= 0) {
            return $value;
        }

        return mb_substr($value, 0, $limit);
    }

    /**
     * 从字段名解析小程序订阅消息字段类型，如 thing8 => thing。
     */
    protected function resolveMiniappFieldType(string $field): string
    {
        if (!preg_match('/^([a-z_]+)\d+$/i', trim($field), $matches)) {
            return '';
        }

        return strtolower((string)$matches[1]);
    }

    /**
     * 判断字符串是否全部为 ASCII 字符。
     */
    protected function isAscii(string $value): bool
    {
        return preg_match('/^[\x00-\x7F]*$/', $value) === 1;
    }

    /**
     * 格式化金额。
     */
    protected function formatAmount(mixed $value): string
    {
        return number_format((float)$value, 2, '.', '');
    }
}
