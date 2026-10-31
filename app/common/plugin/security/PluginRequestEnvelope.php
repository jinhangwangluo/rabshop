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
 * 解密后的逻辑请求信封。
 *
 * 职责：承载安全网关解密（必要时再验签）之后得到的逻辑请求元数据——协议版本、
 * 请求 ID、时间戳、nonce、客户端标识、逻辑方法/路径、幂等键和业务体，并提供三份
 * 稳定表示供协议核对：
 * - aad()：logical_method、logical_path、client_id、request_id 按固定顺序用换行拼接，
 *   作为 JWE 认证附加数据的外部部分，把密文绑定到唯一业务入口；
 * - publicHeader()：同样四个字段的映射形式，发送端写入 JWE 受保护头，接收端逐字段比对；
 * - nonceHash()：防重放存储使用的 nonce 摘要。
 *
 * 调用方：发送侧 PluginSecureGatewayClient::createRequestEnvelope()，
 * 接收侧 PluginEncryptedRequestMiddleware（解密后 fromArray() 构造），
 * 防重放记录侧 PluginKeyService::consumeNonce()。
 *
 * 安全不变量：构造成功即代表协议版本、request_id、时间戳窗口、nonce、客户端标识、
 * 逻辑方法/路径和幂等键都已通过 validate() 校验；这些字段被篡改后，AAD 与路由绑定
 * 校验必然失败，因此下游可以只依赖已验证的信封实例。
 */
class PluginRequestEnvelope
{
    /** 当前唯一受支持的协议版本，与 PluginJoseCodec 共用同一常量值。 */
    public const PROTOCOL_VERSION = PluginJoseCodec::PROTOCOL_VERSION;
    /** 允许的客户端与服务器时间偏差（秒），超出即判定请求过期。 */
    public const CLOCK_SKEW_SECONDS = 300;

    /** @var array<string,mixed> 解密后的业务请求体，只保存在对象内部，不写入头部或日志。 */
    private array $body;

    // 以下字段均为构造时一次性写入的已校验元数据；aad() 和 publicHeader() 依赖它们，
    // 因此声明为 readonly，避免信封在流转过程中被改写导致绑定关系失效。
    public readonly string $protocolVersion;
    public readonly string $requestId;
    public readonly int $timestamp;
    public readonly string $nonce;
    public readonly string $clientId;
    /** 已统一转为大写的逻辑方法，取值范围由 validate() 收敛为 GET/POST/PUT/PATCH/DELETE。 */
    public readonly string $logicalMethod;
    /** 逻辑路径，必须以 / 开头且不含查询串、.. 和控制字符。 */
    public readonly string $logicalPath;
    /** 写请求必填的幂等键，用于业务层去重；读请求允许为空。 */
    public readonly string $idempotencyKey;

    /**
     * 从解密后的载荷数组构造信封并立即校验。
     *
     * 先逐字段取值并归一化（字符串去首尾空白、逻辑方法转大写、时间戳转整型），
     * 再要求 body 必须是数组（对象语义），否则直接拒绝；最后按需执行时间窗口校验。
     *
     * @param array<string,mixed> $data 解密（必要时再验签）后的逻辑请求字段集合。
     * @param bool $validateTime 是否校验时间窗口；验签后生产入口应保持 true。
     * @throws PluginSecurityException 业务体不是数组，或 validate() 任一项校验失败时抛出。
     */
    public function __construct(array $data, bool $validateTime = true)
    {
        $this->protocolVersion = (string)($data['protocol_version'] ?? '');
        $this->requestId = trim((string)($data['request_id'] ?? ''));
        $this->timestamp = (int)($data['timestamp'] ?? 0);
        $this->nonce = trim((string)($data['nonce'] ?? ''));
        $this->clientId = trim((string)($data['client_id'] ?? ''));
        $this->logicalMethod = strtoupper(trim((string)($data['logical_method'] ?? '')));
        $this->logicalPath = trim((string)($data['logical_path'] ?? ''));
        $this->idempotencyKey = trim((string)($data['idempotency_key'] ?? ''));
        $body = $data['body'] ?? null;
        if (!is_array($body)) {
            // 业务体必须是对象（PHP 关联数组语义），标量、列表或缺失都按协议错误拒绝。
            throw new PluginSecurityException('逻辑请求体必须为对象');
        }
        $this->body = $body;
        // 传入 null 跳过时间窗口校验，仅供离线核对、测试或明确已知时间来源的场景使用。
        $this->validate($validateTime ? time() : null);
    }

    /**
     * 创建新信封（发送侧入口）。
     *
     * 未显式传入的协议字段会就地生成：request_id 使用 UUID v4，nonce 使用 24 字节
     * 随机数的 base64url 编码，timestamp 取当前时间；构造过程同样会执行 validate()，
     * 因此参数不合法时不会产生待发送的无效信封。
     *
     * @param array<string,mixed> $body 仅进入密文的业务载荷。
     * @param string $clientId 客户端标识，需满足 validate() 的字符与长度约束。
     * @param string $logicalMethod 逻辑方法，默认 POST；仅允许 GET/POST/PUT/PATCH/DELETE。
     * @param string $logicalPath 逻辑路径，必须以 / 开头；写请求必须携带幂等键。
     * @param string $idempotencyKey 幂等键；写请求不能为空。
     * @param ?int $timestamp 指定时间戳；为空时使用当前时间。
     * @param ?string $nonce 指定 nonce；为空时随机生成，调用方不得重复使用同一 nonce。
     * @param ?string $requestId 指定请求 ID；为空时生成 UUID v4。
     * @return self 已通过校验的请求信封。
     * @throws PluginSecurityException 生成或传入的字段未通过 validate() 校验时抛出。
     */
    public static function create(
        array $body,
        string $clientId,
        string $logicalMethod = 'POST',
        string $logicalPath = '/',
        string $idempotencyKey = '',
        ?int $timestamp = null,
        ?string $nonce = null,
        ?string $requestId = null
    ): self {
        return new self([
            'protocol_version' => self::PROTOCOL_VERSION,
            'request_id' => $requestId ?? self::uuid4(),
            'timestamp' => $timestamp ?? time(),
            'nonce' => $nonce ?? self::base64Url(random_bytes(24)),
            'client_id' => $clientId,
            'logical_method' => $logicalMethod,
            'logical_path' => $logicalPath,
            'idempotency_key' => $idempotencyKey,
            'body' => $body,
        ]);
    }

    /**
     * 从数组还原信封（接收侧入口）。
     *
     * 等价于 new self()，语义化的命名便于中间件在读密文载荷时表达“解析已解密的信封”。
     *
     * @param array<string,mixed> $data 解密（必要时再验签）后的逻辑请求字段集合。
     * @param bool $validateTime 是否校验时间窗口；接收生产流量时必须保持 true。
     * @return self 已通过校验的请求信封。
     * @throws PluginSecurityException 字段格式或时效校验失败时抛出。
     */
    public static function fromArray(array $data, bool $validateTime = true): self
    {
        return new self($data, $validateTime);
    }

    /**
     * 序列化为字段数组，顺序固定，供 JWS 签名与 JWE 认证附加数据使用。
     *
     * 输出包含 aad() 所依赖的那组路由绑定字段，任何一个字段发生变化都会同时改变
     * 签名输入和 AAD，从而在接收端暴露出来。
     *
     * @return array<string,mixed> 包含全部协议字段与业务体的数组。
     */
    public function toArray(): array
    {
        return [
            'protocol_version' => $this->protocolVersion,
            'request_id' => $this->requestId,
            'timestamp' => $this->timestamp,
            'nonce' => $this->nonce,
            'client_id' => $this->clientId,
            'logical_method' => $this->logicalMethod,
            'logical_path' => $this->logicalPath,
            'idempotency_key' => $this->idempotencyKey,
            'body' => $this->body,
        ];
    }

    /**
     * 返回解密后的业务请求体。
     *
     * @return array<string,mixed> 业务载荷（不含协议字段）。
     */
    public function body(): array
    {
        return $this->body;
    }

    /** {@see self::body()} 的存取器别名，供约定使用 get 前缀的调用方调用。 */
    public function getBody(): array
    {
        return $this->body;
    }

    /** 返回全链路追踪用的请求 ID（UUID v4）；不能替代业务幂等键。 */
    public function getRequestId(): string
    {
        return $this->requestId;
    }

    /** 返回客户端标识，防重放记录以客户端 + nonce 为单位登记。 */
    public function getClientId(): string
    {
        return $this->clientId;
    }

    /** 返回一次性随机值；调用方必须交由 PluginKeyService 消费，禁止重复使用。 */
    public function getNonce(): string
    {
        return $this->nonce;
    }

    /** 返回写请求幂等键；为空表示该请求未要求幂等保护。 */
    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    /**
     * JWE 外部认证数据的稳定表示。
     *
     * 按 logical_method、logical_path、client_id、request_id 的固定顺序用换行拼接，
     * 与 PluginRequestCrypto::aadFromHeader() 以及 PluginSecureGatewayClient 的对应
     * 逻辑保持一致：这四个字段参与 AAD 后，密文被搬到其它路由入口解密就会失败。
     *
     * @return string 供 JWE 认证使用的 AAD 字符串。
     */
    public function aad(): string
    {
        return implode("\n", [
            $this->logicalMethod,
            $this->logicalPath,
            $this->clientId,
            $this->requestId,
        ]);
    }

    /** {@see self::aad()} 的别名，供沿用 get 前缀的调用方调用。 */
    public function getAad(): string
    {
        return $this->aad();
    }

    /**
     * 写入 JWE 受保护头的路由绑定字段子集。
     *
     * 发送端把它合并进受保护头，接收端解密后逐字段与其密文头比对，
     * 用于确认“请求头声明的路由”与“密文内部记录的路由”一致。
     *
     * @return array<string,string> request_id、client_id、logical_method、logical_path。
     */
    public function publicHeader(): array
    {
        return [
            'request_id' => $this->requestId,
            'client_id' => $this->clientId,
            'logical_method' => $this->logicalMethod,
            'logical_path' => $this->logicalPath,
        ];
    }

    /**
     * 返回 nonce 的 SHA-256 十六进制摘要，用于防重放存储。
     *
     * 存储摘要而非 nonce 原文，可以避免把可用于重放的随机值写进数据库；该摘要算法
     * 与 PluginKeyService::consumeNonce() 记录的值保持一致，两端必须同时改动。
     *
     * @return string nonce 的 SHA-256 摘要（十六进制小写）。
     */
    public function nonceHash(): string
    {
        return hash('sha256', $this->nonce);
    }

    /**
     * 判断是否为写请求（POST/PUT/PATCH/DELETE）。
     *
     * validate() 借助该判断强制写请求携带幂等键，避免重复提交产生重复业务动作。
     *
     * @return bool 写请求返回 true，读请求（GET）返回 false。
     */
    public function isWriteRequest(): bool
    {
        return in_array($this->logicalMethod, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    /**
     * 校验信封全部协议字段，任一不合格立即抛错。
     *
     * 拒绝的输入与理由：
     * - protocol_version 不等于当前版本：协议不允许降级，旧版本一律拒绝；
     * - request_id 不是规范 UUID v4：该值参与 AAD 和链路追踪，格式必须统一；
     * - timestamp 非正数，或与 $now 相差超过 CLOCK_SKEW_SECONDS：限制重放时间窗口；
     * - nonce 不符合 base64url 字符集或长度不在 22~172：保证随机性下限并限制输入规模；
     * - client_id 不匹配 [A-Za-z0-9._:-] 且长度不在 1~64：限定可安全落库与入日志的标识；
     * - logical_method 不在 GET/POST/PUT/PATCH/DELETE 白名单内：逻辑路由只认这五种方法；
     * - logical_path 为空、超过 190 字节、不以 / 开头、含查询串 ?、含 .. 或含控制字符：
     *   路径会用于路由白名单匹配，任一异常写法都可能绕过白名单；
     * - 写请求缺少合法幂等键，或读请求携带了格式非法的幂等键：写操作必须有去重依据。
     *
     * 注意：nonce 的一次性由 PluginKeyService 的防重放记录保证，本方法只校验其格式。
     *
     * @param int|null $now 当前时间戳；传 null 表示跳过时间窗口校验。
     * @throws PluginSecurityException 上述任一校验失败时抛出。
     */
    public function validate(?int $now = null): void
    {
        if ($this->protocolVersion !== self::PROTOCOL_VERSION) {
            throw new PluginSecurityException('安全协议版本不受支持');
        }
        // 必须是规范 UUID v4：版本位为 4、变体位为 8/9/a/b，防止用任意串替代请求 ID。
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $this->requestId) !== 1) {
            throw new PluginSecurityException('请求 ID 无效');
        }
        if ($this->timestamp <= 0) {
            throw new PluginSecurityException('请求时间戳无效');
        }
        // 时钟偏差双向限制：过旧或明显来自未来的请求都按重放风险拒绝。
        if ($now !== null && abs($now - $this->timestamp) > self::CLOCK_SKEW_SECONDS) {
            throw new PluginSecurityException('请求已过期');
        }
        // 限定 base64url 字符集与长度区间（下限 22 与 16 字节随机值的 base64url 长度一致）。
        if (preg_match('/^[A-Za-z0-9_-]{22,172}$/', $this->nonce) !== 1) {
            throw new PluginSecurityException('请求 nonce 无效');
        }
        // 客户端标识要能安全落库与写入日志，故只允许字母、数字和 . _ : - 四种分隔符。
        if (preg_match('/^[A-Za-z0-9._:-]{1,64}$/', $this->clientId) !== 1) {
            throw new PluginSecurityException('客户端标识无效');
        }
        if (!in_array($this->logicalMethod, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            throw new PluginSecurityException('逻辑请求方法无效');
        }
        if ($this->logicalPath === ''
            || strlen($this->logicalPath) > 190
            || $this->logicalPath[0] !== '/'
            || str_contains($this->logicalPath, '?')
            // 含 '..' 的路径一律拒绝：宁可误伤也不能放行可能用于目录穿越或白名单绕过的写法。
            || str_contains($this->logicalPath, '..')
            || preg_match('/[\x00-\x1F\x7F]/', $this->logicalPath) === 1
        ) {
            throw new PluginSecurityException('逻辑请求路径无效');
        }
        // 写请求必须携带幂等键，否则同一业务动作可能被重复执行。
        if ($this->isWriteRequest()
            && preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $this->idempotencyKey) !== 1
        ) {
            throw new PluginSecurityException('写请求缺少幂等键');
        }
        // 读请求允许不带幂等键，但一旦提供就必须合法，避免非法值被透传到业务层。
        if ($this->idempotencyKey !== ''
            && preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $this->idempotencyKey) !== 1
        ) {
            throw new PluginSecurityException('幂等键无效');
        }
    }

    /**
     * 生成随机 UUID v4。
     *
     * 取 16 字节随机数后按 RFC 4122 就地改写第 7 字节高 4 位为版本号 4、第 9 字节
     * 高 2 位为变体位 10，再按 8-4-4-4-12 格式拼接；不依赖外部库，避免为单一用途
     * 引入额外依赖。
     *
     * @return string 小写十六进制 UUID v4 字符串。
     */
    private static function uuid4(): string
    {
        $bytes = random_bytes(16);
        // 设置版本位（0100）与变体位（10），使输出符合 UUID v4 规范。
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * base64url 编码（URL 安全字母表，去掉尾部填充）。
     *
     * JOSE 要求所有段落使用 base64url 且不带 '=' 填充，因此这里把 +/ 换成 -_ 并裁掉
     * 结尾的填充字符，保证生成的 nonce 能通过 validate() 的字符集校验。
     *
     * @param string $value 待编码的二进制串。
     * @return string 无填充的 base64url 字符串。
     */
    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
