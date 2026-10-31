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

namespace app\common\plugin\security;

use app\common\exception\PluginSecurityException;

/**
 * JOSE 载荷使用的确定性 JSON 编码器。
 *
 * 职责：把 PHP 值编码成收发双方逐字节一致的 JSON，供 JWS 签名输入、JWE 受保护头
 * 和认证附加数据（AAD）复用。JOSE 的签名与 GCM 认证标签都建立在字节级比较之上，
 * 同一份逻辑数据如果在两端产生不同字节序，验签或解密就会失败，因此不能直接用
 * 裸 json_encode 处理安全载荷。
 *
 * 确定性来自三点：关联数组的键统一转成字符串后按 SORT_STRING 排序、列表保持原
 * 顺序、只接受 JSON 可无损表达的类型（null/bool/int/float/string/array）。
 *
 * 调用方：PluginJoseCodec（编码受保护头与载荷、解析头部段）以及安全链路上需要稳定
 * JSON 表示的组件。
 *
 * 安全不变量：同一逻辑输入必须得到同一输出字节；无法确定性表达的值（对象、资源、
 * 非法键名、INF/NAN）必须显式抛错，不允许静默丢弃或降级编码。
 */
final class PluginCanonicalJson
{
    /**
     * 把 PHP 值编码为确定性 JSON 字符串。
     *
     * 先经 normalise() 归一化（排序键、裁剪不支持类型），再以 UTF-8 原文格式
     * 序列化：JSON_UNESCAPED_UNICODE 保留中文原样，JSON_UNESCAPED_SLASHES 保留
     * 路径与 URL 中的斜杠，避免两端转义策略不同造成字节差异；
     * JSON_THROW_ON_ERROR 保证编码失败时抛出异常而不是返回 false。
     *
     * @param mixed $value 待编码的载荷或头部数组，必须是 JSON 可表达的类型。
     * @return string 确定性 JSON 字符串。
     * @throws PluginSecurityException 载荷含不支持的类型、非法键名或非有限浮点数时抛出。
     */
    public function encode(mixed $value): string
    {
        try {
            return json_encode(
                $this->normalise($value),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (\Throwable $exception) {
            throw new PluginSecurityException('安全载荷序列化失败', 0, $exception);
        }
    }

    /**
     * 把 JSON 字符串解析为 PHP 值。
     *
     * 解析前先做长度护栏：空串和超过 16 MiB 的载荷直接拒绝，避免外部输入触发
     * 超大内存分配或进入无意义的解析流程；解析使用 JSON_THROW_ON_ERROR，语法
     * 错误统一转成 PluginSecurityException，不把 json_last_error 细节透给调用方。
     *
     * 采用关联数组模式（assoc 为 true）解码，因此 JSON 对象和数组都会还原为 PHP
     * 数组，两者的区分由调用方按上下文判断（例如 PluginJoseCodec::decodeJsonSegment
     * 会额外拒绝列表形式，要求受保护头必须是对象）。
     *
     * @param string $json 待解析的 JSON 字符串。
     * @return mixed 解析结果；对象/数组返回数组，标量返回对应标量。
     * @throws PluginSecurityException 载荷为空、超过 16 MiB 或 JSON 语法非法时抛出。
     */
    public function decode(string $json): mixed
    {
        // 空载荷没有解析意义；超长载荷直接拒绝，防止外部输入撑爆内存。
        if ($json === '' || strlen($json) > 16 * 1024 * 1024) {
            throw new PluginSecurityException('安全载荷无效');
        }
        try {
            return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            throw new PluginSecurityException('安全载荷解析失败', 0, $exception);
        }
    }

    /**
     * 递归归一化 PHP 值，产出确定性结构。
     *
     * 处理规则：
     * - 列表（连续 0..n-1 整数键）保持原顺序，只递归处理元素，排序会破坏数组语义；
     * - 关联数组把键统一转成字符串后递归，再按 SORT_STRING 排序，保证同一逻辑对象
     *   在收发两端得到相同成员顺序；
     * - 标量与 null 原样返回，但 INF/NAN 无法被 JSON 正确表达，直接拒绝；
     * - 其它类型（对象、资源、闭包等）没有确定的 JSON 映射，一律抛错而非静默丢弃。
     *
     * @param mixed $value 待归一化的值。
     * @return mixed 归一化后的值：数组或 JSON 可表达的标量。
     * @throws PluginSecurityException 键名不是 string/int、浮点数为 INF/NAN 或类型不受支持时抛出。
     */
    private function normalise(mixed $value): mixed
    {
        if (is_array($value)) {
            if ($this->isList($value)) {
                return array_map(fn (mixed $item): mixed => $this->normalise($item), $value);
            }
            $result = [];
            $keys = array_keys($value);
            foreach ($keys as $key) {
                // 键必须是 string 或 int：其它键类型无法稳定映射为 JSON 对象成员名。
                if (!is_string($key) && !is_int($key)) {
                    throw new PluginSecurityException('安全载荷键名无效');
                }
                // 统一转成字符串键，避免 int 键与 string 键在排序和编码时产生差异。
                $result[(string)$key] = $this->normalise($value[$key]);
            }
            // 字符串序排序：对象成员顺序会影响 JSON 字节，必须与两端实现无关。
            ksort($result, SORT_STRING);
            return $result;
        }
        if (is_string($value) || is_int($value) || is_bool($value) || $value === null || is_float($value)) {
            // JSON 没有 INF/NAN 的表示，放行会让两端编码结果不一致。
            if (is_float($value) && (!is_finite($value))) {
                throw new PluginSecurityException('安全载荷数字无效');
            }
            return $value;
        }
        throw new PluginSecurityException('安全载荷类型无效');
    }

    /**
     * 判断数组是否为列表（JSON 数组语义）：空数组或从 0 开始的连续整数键。
     *
     * 空数组也满足列表判断（count 为 0 时 range(0, -1) 得到空数组，与空键列表相等），
     * 因此在本编码器里空对象与空数组不做区分，都按数组语义处理。
     *
     * @param array $value 待判断的数组。
     * @return bool 是列表返回 true，是关联数组（对象语义）返回 false。
     */
    private function isList(array $value): bool
    {
        return array_keys($value) === range(0, count($value) - 1);
    }
}
