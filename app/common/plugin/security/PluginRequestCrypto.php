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
use OpenSSLAsymmetricKey;

/**
 * 插件请求层统一加密门面。
 *
 * 职责：把 JWS 签名/验签、JWE 加密/解密和“JWS 再 JWE”的信封组装收敛到一处，
 * 供发送侧与接收侧共用同一套调用方式；底层算法全部委托给 PluginJoseCodec
 * （JWS ES256、JWE RSA-OAEP-256 + A256GCM），门面本身不改变算法、不做算法降级。
 *
 * 调用方：
 * - 接收侧 PluginEncryptedRequestMiddleware（decrypt、verify、peekHeader、aadFromHeader）；
 * - 发送侧 PluginSecureGatewayClient（createEnvelope、decryptResponse 等）。
 *
 * 安全不变量：门面不提供任何“跳过校验”的开关；信封的 AAD 由信封内的路由绑定字段
 * 推导，因此创建与打开两侧对同一请求必然得到相同的认证数据。
 */
class PluginRequestCrypto
{
    /**
     * 创建请求加解密门面：签名、验签、加密、解密都委托给同一个 JOSE 编解码器。
     *
     * @param ?PluginJoseCodec $codec 底层 JOSE 编解码器；未提供时按需创建默认实现。
     */
    public function __construct(private readonly ?PluginJoseCodec $codec = null)
    {
    }

    /**
     * 对载荷生成 JWS compact token（ES256 签名）。
     *
     * @param array<string,mixed>|string $payload 待签名载荷；数组会先做确定性 JSON 编码。
     * @param string|OpenSSLAsymmetricKey $privateKey P-256 签名私钥，PEM 或已加载的密钥对象。
     * @param string $kid 签名密钥 ID，写入受保护头供接收端选择验签公钥。
     * @param array<string,mixed> $headers 覆盖受保护头的可选字段；alg（以及 JWE 的 enc）
     *        会被底层强制回写为固定算法，不允许替换。
     * @return string JWS compact 字符串。
     * @throws PluginSecurityException kid 非法、密钥不是 P-256 或签名失败时抛出。
     */
    public function sign(
        array|string $payload,
        string|OpenSSLAsymmetricKey $privateKey,
        string $kid,
        array $headers = []
    ): string {
        return $this->codec()->sign($payload, $privateKey, $kid, $headers);
    }

    /**
     * 验证 JWS 并返回载荷。
     *
     * 委托 PluginJoseCodec::verify()：受保护头（alg/protocol_version/kid）校验、P-256
     * 公钥校验、ES256 签名校验全部通过后才解析载荷；JSON 对象/数组返回数组，其它内容
     * 返回原始字符串，类型判断由调用方负责（例如中间件要求必须为对象）。
     *
     * @param string $jws 待验证的 JWS compact 字符串。
     * @param string|OpenSSLAsymmetricKey $publicKey 发送方 P-256 验签公钥。
     * @param ?string $expectedKid 期望的 kid；非空时用 hash_equals 比对，防止密钥错配。
     * @param array<string,mixed> $options 附加约束，支持 required_cty 要求内容类型匹配。
     * @return array<string,mixed>|string JSON 载荷返回数组，非 JSON 载荷返回原始字符串。
     * @throws PluginSecurityException 头、kid、公钥或签名任一校验失败时抛出。
     */
    public function verify(
        string $jws,
        string|OpenSSLAsymmetricKey $publicKey,
        ?string $expectedKid = null,
        array $options = []
    ): array|string {
        return $this->codec()->verify($jws, $publicKey, $expectedKid, $options);
    }

    /**
     * 生成 JWE compact token（RSA-OAEP-256 封装 CEK，A256GCM 加密载荷）。
     *
     * @param array<string,mixed>|string $payload 待加密载荷；数组会先做确定性 JSON 编码。
     * @param string|OpenSSLAsymmetricKey $recipientPublicKey 接收方 RSA 公钥。
     * @param string $kid 加密密钥 ID，写入受保护头供接收端选择解密私钥。
     * @param array<string,mixed> $headers 覆盖受保护头的可选字段；alg/enc 不允许替换。
     * @param string $aad 外部认证数据，按 headerSegment + '.' + b64url(aad) 参与 GCM 认证。
     * @return string JWE compact 字符串。
     * @throws PluginSecurityException kid 非法、密钥不是 RSA、载荷超限或加密失败时抛出。
     */
    public function encrypt(
        array|string $payload,
        string|OpenSSLAsymmetricKey $recipientPublicKey,
        string $kid,
        array $headers = [],
        string $aad = ''
    ): string {
        return $this->codec()->encrypt($payload, $recipientPublicKey, $kid, $headers, $aad);
    }

    /**
     * 解密 JWE compact token 并返回载荷。
     *
     * 校验顺序：受保护头（alg/enc/protocol_version/kid）→ RSA 私钥类型 → 密文各段长度
     * → 用 RSA-OAEP-256 解出 CEK → AES-256-GCM 校验认证标签并解密。标签校验失败即整体
     * 失败，不会返回部分明文；aad 与加密端不一致时同样失败。
     *
     * @param string $jwe 待解密的 JWE compact 字符串。
     * @param string|OpenSSLAsymmetricKey $privateKey 接收方 RSA 私钥。
     * @param ?string $expectedKid 期望的 kid；非空时强比对，防止用其它密钥解开密文。
     * @param string $aad 与加密端一致的外部认证数据，不一致会导致认证失败。
     * @param array<string,mixed> $options 附加选项：raw 为 true 时返回解密后的原始字符串，
     *        required_cty 用于要求内容类型匹配。
     * @return array<string,mixed>|string 解密载荷；raw 模式返回原始字符串。
     * @throws PluginSecurityException 头、kid、密钥、参数长度或认证标签任一校验失败时抛出。
     */
    public function decrypt(
        string $jwe,
        string|OpenSSLAsymmetricKey $privateKey,
        ?string $expectedKid = null,
        string $aad = '',
        array $options = []
    ): array|string {
        return $this->codec()->decrypt($jwe, $privateKey, $expectedKid, $aad, $options);
    }

    /**
     * 解密 JWE 并保留原始字符串载荷，供嵌套 JWS 的安全中间件使用。
     *
     * 嵌套结构中 JWE 的明文就是一段完整的 JWS compact 字符串，必须原样取出再交给
     * verify() 验签；raw 模式明确要求返回字符串，避免载荷被当作 JSON 解析或改变类型。
     * 这里强制覆盖 raw 选项，并在结果不是字符串时立即失败。
     *
     * @param string $jwe 待解密的 JWE compact 字符串。
     * @param string|OpenSSLAsymmetricKey $privateKey 接收方 RSA 私钥。
     * @param ?string $expectedKid 期望的 kid；非空时强比对。
     * @param string $aad 与加密端一致的外部认证数据。
     * @param array<string,mixed> $options 其它解密选项；raw 会被强制覆盖为 true。
     * @return string 解密后的原始载荷（通常是嵌套的 JWS compact 字符串）。
     * @throws PluginSecurityException 解密失败，或解密结果不是字符串时抛出。
     */
    public function decryptRaw(
        string $jwe,
        string|OpenSSLAsymmetricKey $privateKey,
        ?string $expectedKid = null,
        string $aad = '',
        array $options = []
    ): string {
        $options['raw'] = true;
        $payload = $this->decrypt($jwe, $privateKey, $expectedKid, $aad, $options);
        if (!is_string($payload)) {
            throw new PluginSecurityException('安全信封原始载荷无效');
        }
        return $payload;
    }

    /**
     * 只读取 JWS 受保护头，不验证载荷签名。
     *
     * 调用方用它根据受保护的 kid 选择验签公钥，之后必须继续调用 verify()；
     * 该方法返回的头部内容不能单独作为信任依据。
     *
     * @param string $jws JWS compact 字符串。
     * @return array<string,mixed> 已通过结构校验的受保护头。
     * @throws PluginSecurityException 信封格式、头结构或头字段校验失败时抛出。
     */
    public function peekJwsHeader(string $jws): array
    {
        return $this->codec()->peekJwsHeader($jws);
    }

    /**
     * 创建“JWS 再 JWE”系统间请求。
     *
     * 组装顺序：先用签名私钥把信封数组签成 JWS（受保护头 cty 为 JWT），再把整段 JWS
     * 作为明文用接收方公钥加密成 JWE；JWE 受保护头除 cty 外还会合并信封的路由绑定
     * 字段，并把信封 aad() 作为外部认证数据传入。传数组时会先构造成信封（默认校验
     * 时间窗口），因此传入非法字段会在发送前失败，而不是把无效信封发出去。
     *
     * @param array<string,mixed>|PluginRequestEnvelope $request 信封对象或等价的字段数组。
     * @param string|OpenSSLAsymmetricKey $signingPrivateKey 发送方 P-256 签名私钥。
     * @param string|OpenSSLAsymmetricKey $recipientPublicKey 接收方 RSA 加密公钥。
     * @param string $signingKid 签名密钥 ID，写入 JWS 受保护头。
     * @param ?string $encryptionKid 加密密钥 ID；未提供时沿用签名 kid。
     * @return string 完整的 JWE compact 字符串（明文为嵌套 JWS）。
     * @throws PluginSecurityException 信封字段、密钥或算法校验失败时抛出。
     */
    public function createEnvelope(
        array|PluginRequestEnvelope $request,
        string|OpenSSLAsymmetricKey $signingPrivateKey,
        string|OpenSSLAsymmetricKey $recipientPublicKey,
        string $signingKid,
        ?string $encryptionKid = null
    ): string {
        $envelope = $request instanceof PluginRequestEnvelope
            ? $request
            : PluginRequestEnvelope::fromArray($request);
        $jws = $this->codec()->sign(
            $envelope->toArray(),
            $signingPrivateKey,
            $signingKid,
            ['cty' => 'JWT']
        );
        $encryptionKid ??= $signingKid;
        return $this->codec()->encrypt(
            $jws,
            $recipientPublicKey,
            $encryptionKid,
            array_merge(['cty' => 'JWT'], $envelope->publicHeader()),
            $envelope->aad()
        );
    }

    /**
     * 打开并验证“JWS 再 JWE”系统间请求。
     *
     * 解包顺序与创建顺序相反：
     * 1. peekHeader() 校验 JWE 受保护头，并由头部字段推导出 AAD；
     * 2. decrypt()（raw + required_cty=JWT）解密得到内层 JWS；
     * 3. verify()（required_cty=JWT）验签并取得信封数组；
     * 4. 构造 PluginRequestEnvelope（按 $validateTime 决定是否校验时间窗口）；
     * 5. 逐字段比对 JWE 受保护头与信封的 publicHeader()，并核对 AAD 完全一致。
     * 第 5 步把“头部声明的路由”和“密文内部记录的路由”绑定在一起，任一步失败都会抛错。
     *
     * @param string $jwe 待打开的信封。
     * @param string|OpenSSLAsymmetricKey $decryptionPrivateKey 接收方 RSA 解密私钥。
     * @param string|OpenSSLAsymmetricKey $signingPublicKey 发送方 P-256 验签公钥。
     * @param ?string $encryptionKid 期望的加密 kid；非空时强比对。
     * @param ?string $signingKid 期望的签名 kid；非空时强比对。
     * @param bool $validateTime 是否校验信封时间窗口；生产入口应保持 true。
     * @return PluginRequestEnvelope 已验证的请求信封。
     * @throws PluginSecurityException 解密、验签、头绑定或 AAD 校验任一失败时抛出。
     */
    public function openEnvelope(
        string $jwe,
        string|OpenSSLAsymmetricKey $decryptionPrivateKey,
        string|OpenSSLAsymmetricKey $signingPublicKey,
        ?string $encryptionKid = null,
        ?string $signingKid = null,
        bool $validateTime = true
    ): PluginRequestEnvelope {
        $header = $this->codec()->peekHeader($jwe);
        $aad = $this->aadFromHeader($header);
        $rawJws = $this->codec()->decrypt(
            $jwe,
            $decryptionPrivateKey,
            $encryptionKid,
            $aad,
            ['raw' => true, 'required_cty' => 'JWT']
        );
        if (!is_string($rawJws)) {
            throw new PluginSecurityException('加密信封载荷无效');
        }
        $payload = $this->codec()->verify(
            $rawJws,
            $signingPublicKey,
            $signingKid,
            ['required_cty' => 'JWT']
        );
        if (!is_array($payload)) {
            throw new PluginSecurityException('签名信封载荷无效');
        }
        $envelope = PluginRequestEnvelope::fromArray($payload, $validateTime);
        foreach ($envelope->publicHeader() as $key => $value) {
            if (!isset($header[$key]) || !hash_equals((string)$value, (string)$header[$key])) {
                throw new PluginSecurityException('安全信封路由绑定校验失败');
            }
        }
        if ($envelope->aad() !== $aad) {
            throw new PluginSecurityException('安全信封认证数据不匹配');
        }
        return $envelope;
    }

    /**
     * createEnvelope() 的语义化别名，参数与行为完全一致。
     *
     * @param array<string,mixed>|PluginRequestEnvelope $request 信封对象或等价的字段数组。
     * @param string|OpenSSLAsymmetricKey $signingPrivateKey 发送方 P-256 签名私钥。
     * @param string|OpenSSLAsymmetricKey $recipientPublicKey 接收方 RSA 加密公钥。
     * @param string $signingKid 签名密钥 ID。
     * @param ?string $encryptionKid 加密密钥 ID；未提供时沿用签名 kid。
     * @return string 完整的 JWE compact 字符串。
     * @throws PluginSecurityException 信封字段、密钥或算法校验失败时抛出。
     */
    public function createSignedJwe(
        array|PluginRequestEnvelope $request,
        string|OpenSSLAsymmetricKey $signingPrivateKey,
        string|OpenSSLAsymmetricKey $recipientPublicKey,
        string $signingKid,
        ?string $encryptionKid = null
    ): string {
        return $this->createEnvelope($request, $signingPrivateKey, $recipientPublicKey, $signingKid, $encryptionKid);
    }

    /**
     * openEnvelope() 的语义化别名，参数与行为完全一致。
     *
     * @param string $jwe 待打开的信封。
     * @param string|OpenSSLAsymmetricKey $decryptionPrivateKey 接收方 RSA 解密私钥。
     * @param string|OpenSSLAsymmetricKey $signingPublicKey 发送方 P-256 验签公钥。
     * @param ?string $encryptionKid 期望的加密 kid；非空时强比对。
     * @param ?string $signingKid 期望的签名 kid；非空时强比对。
     * @param bool $validateTime 是否校验信封时间窗口。
     * @return PluginRequestEnvelope 已验证的请求信封。
     * @throws PluginSecurityException 解密、验签、头绑定或 AAD 校验任一失败时抛出。
     */
    public function openSignedJwe(
        string $jwe,
        string|OpenSSLAsymmetricKey $decryptionPrivateKey,
        string|OpenSSLAsymmetricKey $signingPublicKey,
        ?string $encryptionKid = null,
        ?string $signingKid = null,
        bool $validateTime = true
    ): PluginRequestEnvelope {
        return $this->openEnvelope($jwe, $decryptionPrivateKey, $signingPublicKey, $encryptionKid, $signingKid, $validateTime);
    }

    /**
     * 只读取并校验 JWE 受保护头，不解密密文。
     *
     * 底层要求信封必须是 5 段结构（JWE compact），因此该方法只用于外层 JWE 头；
     * JWS 头请使用 peekJwsHeader()。调用方据此选择解密密钥，但不能仅凭头部内容信任请求。
     *
     * @param string $jose JWE compact 字符串。
     * @return array<string,mixed> 已通过结构校验的受保护头。
     * @throws PluginSecurityException 信封不是 5 段、编码非法或头字段校验失败时抛出。
     */
    public function peekHeader(string $jose): array
    {
        return $this->codec()->peekHeader($jose);
    }

    /**
     * 从 JWE 受保护头还原外部认证数据（AAD）。
     *
     * 按 logical_method（转大写）、logical_path、client_id、request_id 的固定顺序用换行
     * 拼接，与 PluginRequestEnvelope::aad() 的表示一致；缺少任一字段或字段不是非空字符串
     * 时拒绝，避免用不完整的头部拼出错误的 AAD 后才发现认证失败。
     *
     * @param array<string,mixed> $header JWE 受保护头。
     * @return string 供 GCM 认证使用的 AAD 字符串。
     * @throws PluginSecurityException 缺少路由绑定字段或字段类型非法时抛出。
     */
    public function aadFromHeader(array $header): string
    {
        foreach (['logical_method', 'logical_path', 'client_id', 'request_id'] as $key) {
            if (!isset($header[$key]) || !is_string($header[$key]) || $header[$key] === '') {
                throw new PluginSecurityException('安全信封缺少路由绑定字段');
            }
        }
        return implode("\n", [
            // 与信封构造保持一致：方法统一大写，避免大小写差异导致 AAD 不匹配。
            strtoupper($header['logical_method']),
            $header['logical_path'],
            $header['client_id'],
            $header['request_id'],
        ]);
    }

    /**
     * 返回底层编解码器，未注入时按需创建默认实现。
     *
     * 延迟创建可以避免门面在仅做参数透传的场景下提前实例化依赖。
     *
     * @return PluginJoseCodec JOSE 编解码器实例。
     */
    private function codec(): PluginJoseCodec
    {
        return $this->codec ?? new PluginJoseCodec();
    }
}
