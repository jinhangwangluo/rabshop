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
use Throwable;

/**
 * 最小 JOSE 编解码器：JWS ES256 与 JWE RSA-OAEP-256/A256GCM。
 *
 * 项目当前没有可用的 JOSE JWE 依赖，故这里严格实现 RFC 7515/7516 所需
 * 的 compact 序列化和密码原语。任何算法不匹配都会失败，不会降级。
 *
 * 职责划分：
 * - JWS：ES256（P-256 + SHA-256）签名与验签，签名值在 DER 与 JOSE 要求的定长
 *   R||S 表示之间转换；
 * - JWE：RSA-OAEP-256 封装 256 位内容密钥（CEK），A256GCM 以 96 位 IV 和 128 位
 *   认证标签加密载荷，AAD 由受保护头段与外部认证数据拼接；
 * - 头部：受保护头写入 alg/enc/kid/typ/cty/protocol_version，读取侧按固定白名单校验。
 *
 * 调用方：PluginRequestCrypto（请求层门面）、PluginSecureGatewayClient（系统间请求）。
 *
 * 安全不变量：
 * - alg/enc/protocol_version 只能等于本类常量，调用方传参无法替换或降级；
 * - 头部必须是 JSON 对象，各段必须是 JWS 规定的 base64url 无填充编码；
 * - 验签、解密所需密钥类型（P-256 / RSA）不匹配时直接失败，不做回退；
 * - 所有失败统一抛 PluginSecurityException，不泄露内部密钥或 OpenSSL 细节。
 */
class PluginJoseCodec
{
    /** JWS 签名算法：ECDSA over P-256 with SHA-256。 */
    public const JWS_ALGORITHM = 'ES256';
    /** JWE 密钥封装算法：RSA-OAEP，使用 SHA-256 与 MGF1-SHA-256。 */
    public const JWE_ALGORITHM = 'RSA-OAEP-256';
    /** JWE 内容加密算法：AES-256-GCM。 */
    public const JWE_ENCRYPTION = 'A256GCM';
    /** 当前协议版本；不匹配的版本一律拒绝，不提供降级路径。 */
    public const PROTOCOL_VERSION = '1.0';

    /** 单个 compact token 的最大字节数。 */
    private const MAX_TOKEN_LENGTH = 16 * 1024 * 1024;
    /** 单次签名或加密载荷的最大字节数。 */
    private const MAX_PAYLOAD_LENGTH = 12 * 1024 * 1024;
    /** SHA-256 输出长度，同时作为 A256GCM 的 CEK 长度（32 字节 / 256 位）。 */
    private const HASH_LENGTH = 32;
    /** GCM 推荐的 96 位初始向量长度。 */
    private const GCM_IV_LENGTH = 12;
    /** GCM 认证标签长度（128 位），短标签会削弱认证强度，故此固定值。 */
    private const GCM_TAG_LENGTH = 16;

    /**
     * 创建 JOSE 编解码器：注入确定性 JSON 编码器，供 JWS 签名输入与 JWE AAD 复用。
     *
     * @param ?PluginCanonicalJson $json 确定性 JSON 编码器；未提供时按需创建默认实现。
     */
    public function __construct(private readonly ?PluginCanonicalJson $json = null)
    {
    }

    /**
     * 生成 JWS compact token。
     *
     * 流程：校验 kid → 确认私钥为 P-256（ES256 只允许 prime256v1 曲线）→ 组装并编码
     * 受保护头 → 编码载荷 → 以 `受保护头段.载荷段` 作为签名输入，用 SHA-256 做 ECDSA
     * 签名 → 把 OpenSSL 输出的 DER 签名转成 JOSE 要求的定长 R||S → 拼成三段 token。
     *
     * @param array<string,mixed>|string $payload 待签名载荷；数组会先做确定性 JSON 编码。
     * @param string|OpenSSLAsymmetricKey $privateKey P-256 签名私钥，PEM 字符串或密钥对象。
     * @param string $kid 密钥 ID，写入受保护头；非法字符或超长会被 assertKid() 拒绝。
     * @param array<string,mixed> $headers 覆盖受保护头的可选字段（如 cty 传 JWT）。
     * @return string JWS compact 字符串：受保护头.载荷.签名。
     * @throws PluginSecurityException kid 非法、私钥非 P-256、头字段非法或签名失败时抛出。
     */
    public function sign(
        array|string $payload,
        string|OpenSSLAsymmetricKey $privateKey,
        string $kid,
        array $headers = []
    ): string {
        $kid = $this->assertKid($kid);
        $key = $this->privateKey($privateKey);
        $details = openssl_pkey_get_details($key);
        if (!is_array($details)
            || (int)($details['type'] ?? 0) !== OPENSSL_KEYTYPE_EC
            || (string)($details['ec']['curve_name'] ?? '') !== 'prime256v1'
        ) {
            throw new PluginSecurityException('JWS 签名密钥必须为 P-256');
        }

        $protected = $this->protectedHeaders([
            'alg' => self::JWS_ALGORITHM,
            'kid' => $kid,
            'typ' => 'JOSE',
            'cty' => 'application/json',
            'protocol_version' => self::PROTOCOL_VERSION,
        ], $headers, false);
        $protectedSegment = $this->encode($this->json()->encode($protected));
        $payloadSegment = $this->encodePayload($payload);
        // JWS 签名输入是 ASCII 形式的 `受保护头段.载荷段`，不含签名段。
        $input = $protectedSegment . '.' . $payloadSegment;
        $derSignature = '';
        if (!openssl_sign($input, $derSignature, $key, OPENSSL_ALGO_SHA256)) {
            throw new PluginSecurityException('JWS 签名失败');
        }
        // OpenSSL 输出 DER 编码的 ECDSA 签名，JOSE 要求定长 R||S，必须转换后再编码。
        $rawSignature = $this->derToRawSignature($derSignature);
        return $protectedSegment . '.' . $payloadSegment . '.' . $this->encode($rawSignature);
    }

    /**
     * 验证 JWS，并返回 JSON 对象/数组载荷；非 JSON 载荷返回原始字符串。
     *
     * 校验顺序：段数与编码格式 → 受保护头（算法必须为 ES256、kid、协议版本、可选 cty）
     * → 签名字段必须是 64 字节（P-256 的 R||S 定长，长度不符说明不是本方案产物）
     * → 公钥必须是 P-256 → openssl_verify 结果必须严格等于 1。任一步失败即抛错，
     * 不会进入载荷解析。
     *
     * 返回值语义：载荷能解析为 JSON 数组时返回数组，否则原样返回字符串，调用方需自行判断类型。
     *
     * @param string $token 待验证的 JWS compact 字符串。
     * @param string|OpenSSLAsymmetricKey $publicKey 发送方 P-256 验签公钥。
     * @param ?string $expectedKid 期望的 kid；非空时必须与受保护头一致。
     * @param array<string,mixed> $options 附加约束，支持 required_cty 要求 cty 等于指定值。
     * @return array<string,mixed>|string JSON 载荷返回数组，非 JSON 载荷返回原始字符串。
     * @throws PluginSecurityException 段格式、头字段、kid、密钥类型或签名校验失败时抛出。
     */
    public function verify(
        string $token,
        string|OpenSSLAsymmetricKey $publicKey,
        ?string $expectedKid = null,
        array $options = []
    ): array|string {
        $parts = $this->splitToken($token, 3);
        $header = $this->decodeJsonSegment($parts[0], true);
        $this->assertJwsHeaders($header, $expectedKid, $options);
        $signature = $this->decode($parts[2]);
        // ES256 签名必须是 r、s 各 32 字节拼接的定长 64 字节；长度不符直接拒绝，
        // 避免把任意字节喂给后续的 DER 转换逻辑。
        if (strlen($signature) !== 64) {
            throw new PluginSecurityException('JWS 签名长度无效');
        }
        // OpenSSL 只接受 DER 形式的 ECDSA 签名，这里把 JOSE 的 R||S 还原成 DER。
        $derSignature = $this->rawToDerSignature($signature);
        $key = $this->publicKey($publicKey);
        $details = openssl_pkey_get_details($key);
        if (!is_array($details)
            || (int)($details['type'] ?? 0) !== OPENSSL_KEYTYPE_EC
            || (string)($details['ec']['curve_name'] ?? '') !== 'prime256v1'
        ) {
            throw new PluginSecurityException('JWS 验签密钥必须为 P-256');
        }
        $verified = openssl_verify(
            $parts[0] . '.' . $parts[1],
            $derSignature,
            $key,
            OPENSSL_ALGO_SHA256
        );
        if ($verified !== 1) {
            // 严格比较：openssl_verify 返回 -1 表示出错，同样必须按失败处理。
            throw new PluginSecurityException('JWS 签名校验失败');
        }
        return $this->decodePayload($parts[1]);
    }

    /**
     * 生成 JWE compact token。
     *
     * 流程：校验 kid → 确认加密公钥为 RSA → 组装并编码受保护头 → 把载荷（数组先做
     * 确定性 JSON 编码）做长度护栏 → 随机生成 32 字节 CEK 并用 RSA-OAEP-256 封装
     * → 随机生成 12 字节 GCM IV → 以受保护头段（含外部认证数据）作为 AAD，用
     * AES-256-GCM 加密并取得 16 字节认证标签 → 拼成五段 token。
     *
     * @param array<string,mixed>|string $payload 待加密载荷；数组会先做确定性 JSON 编码。
     * @param string|OpenSSLAsymmetricKey $recipientPublicKey 接收方 RSA 公钥，PEM 或密钥对象。
     * @param string $kid 加密密钥 ID，写入受保护头供接收端选择解密私钥。
     * @param array<string,mixed> $headers 覆盖受保护头的可选字段；alg/enc 不允许替换。
     * @param string $aadExternal 额外认证数据，采用 headerSegment + '.' + b64url(aad) 规则。
     * @return string JWE compact 字符串：受保护头.加密密钥.IV.密文.认证标签。
     * @throws PluginSecurityException kid 非法、公钥非 RSA、载荷大小非法或加密失败时抛出。
     */
    public function encrypt(
        array|string $payload,
        string|OpenSSLAsymmetricKey $recipientPublicKey,
        string $kid,
        array $headers = [],
        string $aadExternal = ''
    ): string {
        $kid = $this->assertKid($kid);
        $key = $this->publicKey($recipientPublicKey);
        $details = openssl_pkey_get_details($key);
        if (!is_array($details)
            || (int)($details['type'] ?? 0) !== OPENSSL_KEYTYPE_RSA
        ) {
            throw new PluginSecurityException('JWE 加密密钥必须为 RSA');
        }
        $protected = $this->protectedHeaders([
            'alg' => self::JWE_ALGORITHM,
            'enc' => self::JWE_ENCRYPTION,
            'kid' => $kid,
            'typ' => 'JOSE',
            'cty' => 'application/json',
            'protocol_version' => self::PROTOCOL_VERSION,
        ], $headers, true);
        $protectedSegment = $this->encode($this->json()->encode($protected));
        // 数组按确定性 JSON 编码；字符串按调用方给的原始字节加密（嵌套 JWS 走这条路径）。
        $plaintext = is_array($payload) ? $this->json()->encode($payload) : $payload;
        // 空载荷没有加密意义，超限载荷会超出接收端的长度护栏，两者都直接拒绝。
        if ($plaintext === '' || strlen($plaintext) > self::MAX_PAYLOAD_LENGTH) {
            throw new PluginSecurityException('JWE 载荷大小无效');
        }
        // 每次加密都生成新的 256 位内容密钥，不复用、不派生自任何可预测输入。
        $cek = random_bytes(self::HASH_LENGTH);
        $encryptedKey = $this->rsaOaep256Encrypt($cek, $key);
        // GCM 对同一密钥不允许重复的 IV，因此每次加密都必须随机生成。
        $iv = random_bytes(self::GCM_IV_LENGTH);
        $aad = $this->aad($protectedSegment, $aadExternal);
        $tag = '';
        // 认证标签通过引用参数返回；GCM 同时提供机密性与完整性校验。
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $cek,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad,
            self::GCM_TAG_LENGTH
        );
        if ($ciphertext === false || strlen($tag) !== self::GCM_TAG_LENGTH) {
            throw new PluginSecurityException('JWE 加密失败');
        }
        return implode('.', [
            $protectedSegment,
            $this->encode($encryptedKey),
            $this->encode($iv),
            $this->encode($ciphertext),
            $this->encode($tag),
        ]);
    }

    /**
     * 解密 JWE compact token。
     *
     * 校验顺序与 encrypt() 对称：段数与编码格式 → 受保护头（alg 必须为 RSA-OAEP-256、
     * enc 必须为 A256GCM、kid、协议版本、可选 cty）→ 私钥必须为 RSA → IV 必须是 12 字节、
     * 认证标签必须是 16 字节 → RSA-OAEP-256 解出 CEK → 用同一 AAD 做 GCM 解密并校验标签。
     * 标签校验失败不会返回任何明文。
     *
     * @param string $token 待解密的 JWE compact 字符串。
     * @param string|OpenSSLAsymmetricKey $privateKey 接收方 RSA 私钥。
     * @param ?string $expectedKid 期望的 kid；非空时必须与受保护头一致。
     * @param string $aadExternal 与加密端一致的外部认证数据，不一致会导致认证失败。
     * @param array<string,mixed> $options 附加选项：raw 为 true 时直接返回解密后的原始字符串，
     *        required_cty 用于要求 cty 等于指定值。
     * @return array<string,mixed>|string 解密载荷；raw 模式返回原始字符串，非 JSON 载荷返回字符串。
     * @throws PluginSecurityException 段格式、头字段、kid、密钥类型、参数长度、RSA 解封装或
     *         GCM 认证任一失败时抛出。
     */
    public function decrypt(
        string $token,
        string|OpenSSLAsymmetricKey $privateKey,
        ?string $expectedKid = null,
        string $aadExternal = '',
        array $options = []
    ): array|string {
        $parts = $this->splitToken($token, 5);
        $header = $this->decodeJsonSegment($parts[0], true);
        $this->assertJweHeaders($header, $expectedKid, $options);
        $key = $this->privateKey($privateKey);
        $details = openssl_pkey_get_details($key);
        if (!is_array($details) || (int)($details['type'] ?? 0) !== OPENSSL_KEYTYPE_RSA) {
            throw new PluginSecurityException('JWE 解密密钥必须为 RSA');
        }
        $encryptedKey = $this->decode($parts[1]);
        $iv = $this->decode($parts[2]);
        $ciphertext = $this->decode($parts[3]);
        $tag = $this->decode($parts[4]);
        // GCM 的 IV 与标签长度是协议固定值，长度不符说明不是本方案的密文，直接拒绝。
        if (strlen($iv) !== self::GCM_IV_LENGTH || strlen($tag) !== self::GCM_TAG_LENGTH) {
            throw new PluginSecurityException('JWE 参数长度无效');
        }
        $cek = $this->rsaOaep256Decrypt($encryptedKey, $key);
        $aad = $this->aad($parts[0], $aadExternal);
        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $cek,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad
        );
        // openssl_decrypt 在标签校验失败时返回 false；与超出长度上限的结果一并按认证失败处理。
        if ($plaintext === false || strlen($plaintext) > self::MAX_PAYLOAD_LENGTH) {
            throw new PluginSecurityException('JWE 认证校验失败');
        }
        // raw 模式跳过 JSON 解析，保留原始字节供上层继续处理嵌套 JWS。
        if (($options['raw'] ?? false) === true) {
            return $plaintext;
        }
        // 复用统一的载荷解析路径：非 JSON 内容会按原始字符串返回。
        return $this->decodePayload($this->encode($plaintext));
    }

    /**
     * 只读取并验证 JWE 受保护头，不解密密文。
     *
     * 要求信封为 5 段结构（JWE compact），并对头字段执行 assertJweHeaders() 校验；
     * 调用方用它取出 kid 选择解密私钥、取出路由绑定字段推导 AAD，之后必须继续调用
     * decrypt()，头部内容本身不构成信任依据。
     *
     * @param string $token JWE compact 字符串。
     * @return array<string,mixed> 已通过结构校验的受保护头。
     * @throws PluginSecurityException 段格式、编码或头字段校验失败时抛出。
     */
    public function peekHeader(string $token): array
    {
        $parts = $this->splitToken($token, 5);
        $header = $this->decodeJsonSegment($parts[0], true);
        $this->assertJweHeaders($header, null, []);
        return $header;
    }

    /**
     * 只读取并验证 JWS 受保护头，不验证载荷签名。
     *
     * 调用方仍必须随后执行 verify()；该方法只用于根据受保护的 kid 选择
     * 验签公钥，绝不能单独作为信任依据。
     *
     * @param string $token JWS compact 字符串。
     * @return array<string,mixed> 已通过结构校验的受保护头。
     * @throws PluginSecurityException 段格式、编码或头字段校验失败时抛出。
     */
    public function peekJwsHeader(string $token): array
    {
        $parts = $this->splitToken($token, 3);
        $header = $this->decodeJsonSegment($parts[0], true);
        $this->assertJwsHeaders($header, null, []);
        return $header;
    }

    /**
     * 合并默认头与调用方覆盖项，并把算法与协议版本重新钉死为常量值。
     *
     * 拒绝的输入与理由：
     * - alg（JWE 还含 enc）与常量不一致：覆盖合并会把调用方传值写进头里，这里强制回写，
     *   任何试图替换或降级算法的入参都会以“安全算法不受支持”失败；
     * - protocol_version 不等于当前版本：协议不允许降级；
     * - 头字段名不符合 [A-Za-z0-9_]{1,64}：字段名会进入受保护头 JSON，必须限定字符集与长度；
     * - 头字段值不是标量且非 null：数组/对象值无法稳定序列化为 JOSE 头成员。
     *
     * @param array<string,mixed> $defaults 默认头字段。
     * @param array<string,mixed> $overrides 调用方覆盖项，仅允许覆盖非算法类字段。
     * @param bool $jwe 是否为 JWE 头；JWE 需要额外固定 enc。
     * @return array<string,mixed> 校验通过的受保护头。
     * @throws PluginSecurityException 算法、协议版本、字段名或字段值校验失败时抛出。
     */
    private function protectedHeaders(array $defaults, array $overrides, bool $jwe): array
    {
        $headers = array_merge($defaults, $overrides);
        $required = $jwe
            ? ['alg' => self::JWE_ALGORITHM, 'enc' => self::JWE_ENCRYPTION]
            : ['alg' => self::JWS_ALGORITHM];
        foreach ($required as $name => $value) {
            if (($headers[$name] ?? null) !== $value) {
                throw new PluginSecurityException('安全算法不受支持');
            }
            $headers[$name] = $value;
        }
        // 统一成字符串，避免 int/float 形态的 kid 或版本号序列化出不同 JSON 字节。
        $headers['kid'] = (string)$headers['kid'];
        $headers['protocol_version'] = (string)($headers['protocol_version'] ?? self::PROTOCOL_VERSION);
        if ($headers['protocol_version'] !== self::PROTOCOL_VERSION) {
            throw new PluginSecurityException('安全协议版本不受支持');
        }
        foreach ($headers as $name => $value) {
            if (!is_string($name) || preg_match('/^[A-Za-z0-9_]{1,64}$/', $name) !== 1) {
                throw new PluginSecurityException('安全头字段名无效');
            }
            // 头字段值必须是标量或 null，数组/对象无法作为受保护头的稳定成员。
            if (!is_scalar($value) && $value !== null) {
                throw new PluginSecurityException('安全头字段值无效');
            }
        }
        return $headers;
    }

    /**
     * 校验 JWS 受保护头。
     *
     * 拒绝的输入与理由：
     * - alg 不是 ES256：防止算法替换（例如换成 none 或其它曲线/摘要）；
     * - 缺少 kid：无法定位验签公钥，必须显式提供；
     * - protocol_version 不等于当前版本：协议不允许降级（字段缺失时按当前版本处理，
     *   与创建端写入的默认值一致）；
     * - kid 不满足 assertKid() 的字符集与长度约束；
     * - $expectedKid 非空且与头部 kid 不相等：调用方指定的密钥与签名密钥不一致，用
     *   hash_equals 比较避免时序差异；
     * - $options['required_cty'] 存在且头部 cty 不匹配：内容类型是嵌套结构的判据
     *   （例如要求 JWT），不匹配说明载荷不能被按约定解析。
     *
     * @param array<string,mixed> $header 已解码的受保护头。
     * @param ?string $expectedKid 期望的签名密钥 ID；null 表示不限制。
     * @param array<string,mixed> $options 附加约束，支持 required_cty。
     * @throws PluginSecurityException 上述任一校验失败时抛出。
     */
    private function assertJwsHeaders(array $header, ?string $expectedKid, array $options): void
    {
        if (($header['alg'] ?? null) !== self::JWS_ALGORITHM
            || !isset($header['kid'])
            || ($header['protocol_version'] ?? self::PROTOCOL_VERSION) !== self::PROTOCOL_VERSION
        ) {
            throw new PluginSecurityException('JWS 安全头校验失败');
        }
        $this->assertKid((string)$header['kid']);
        if ($expectedKid !== null && !hash_equals($expectedKid, (string)$header['kid'])) {
            throw new PluginSecurityException('JWS 密钥 ID 不匹配');
        }
        if (isset($options['required_cty']) && ($header['cty'] ?? null) !== $options['required_cty']) {
            throw new PluginSecurityException('JWS 内容类型不匹配');
        }
    }

    /**
     * 校验 JWE 受保护头。
     *
     * 拒绝的输入与理由：
     * - alg 不是 RSA-OAEP-256 或 enc 不是 A256GCM：密钥封装算法与内容加密算法都必须
     *   与常量一致，任一不同即拒绝，避免接受弱算法产生的密文；
     * - 缺少 kid：无法定位解密私钥；
     * - protocol_version 不等于当前版本：协议不允许降级（字段缺失时按当前版本处理）；
     * - kid 不满足 assertKid() 的字符集与长度约束；
     * - $expectedKid 非空且与头部 kid 不相等：防止用非预期的密钥处理密文；
     * - $options['required_cty'] 存在且头部 cty 不匹配：内容类型不符合调用方约定。
     *
     * @param array<string,mixed> $header 已解码的受保护头。
     * @param ?string $expectedKid 期望的加密密钥 ID；null 表示不限制。
     * @param array<string,mixed> $options 附加约束，支持 required_cty。
     * @throws PluginSecurityException 上述任一校验失败时抛出。
     */
    private function assertJweHeaders(array $header, ?string $expectedKid, array $options): void
    {
        if (($header['alg'] ?? null) !== self::JWE_ALGORITHM
            || ($header['enc'] ?? null) !== self::JWE_ENCRYPTION
            || !isset($header['kid'])
            || ($header['protocol_version'] ?? self::PROTOCOL_VERSION) !== self::PROTOCOL_VERSION
        ) {
            throw new PluginSecurityException('JWE 安全头校验失败');
        }
        $this->assertKid((string)$header['kid']);
        if ($expectedKid !== null && !hash_equals($expectedKid, (string)$header['kid'])) {
            throw new PluginSecurityException('JWE 密钥 ID 不匹配');
        }
        if (isset($options['required_cty']) && ($header['cty'] ?? null) !== $options['required_cty']) {
            throw new PluginSecurityException('JWE 内容类型不匹配');
        }
    }

    /**
     * 校验并返回密钥 ID。
     *
     * 只允许 [A-Za-z0-9._:-] 且长度 1~64：kid 会被写进受保护头并用于查表选择密钥，
     * 限定字符集可以避免控制字符、引号等被带入头部 JSON，长度上限则限制外部输入规模。
     * 非法 kid 一律拒绝，不做清洗或截断。
     *
     * @param string $kid 待校验的密钥 ID。
     * @return string 原样返回的合法 kid。
     * @throws PluginSecurityException kid 不符合字符集或长度约束时抛出。
     */
    private function assertKid(string $kid): string
    {
        if (preg_match('/^[A-Za-z0-9._:-]{1,64}$/', $kid) !== 1) {
            throw new PluginSecurityException('密钥 ID 无效');
        }
        return $kid;
    }

    /**
     * 归一化私钥输入。
     *
     * 字符串按 PEM 解析，密钥对象直接复用；解析失败或不是 OpenSSL 密钥对象时拒绝，
     * 避免把无效材料带进后续签名/解密流程。密钥类型（P-256 或 RSA）由调用方按用途校验。
     *
     * @param string|OpenSSLAsymmetricKey $key PEM 字符串或已加载的密钥对象。
     * @return OpenSSLAsymmetricKey 可用的私钥对象。
     * @throws PluginSecurityException 私钥材料无法解析时抛出。
     */
    private function privateKey(string|OpenSSLAsymmetricKey $key): OpenSSLAsymmetricKey
    {
        $resource = is_string($key) ? openssl_pkey_get_private($key) : $key;
        if (!$resource instanceof OpenSSLAsymmetricKey) {
            throw new PluginSecurityException('私钥材料无效');
        }
        return $resource;
    }

    /**
     * 归一化公钥输入。
     *
     * 字符串按 PEM 解析，密钥对象直接复用；失败即拒绝。只有合法公钥材料才能通过
     * openssl_pkey_get_public()，格式错误的内容会在这里被挡下。
     *
     * @param string|OpenSSLAsymmetricKey $key PEM 字符串或已加载的密钥对象。
     * @return OpenSSLAsymmetricKey 可用的公钥对象。
     * @throws PluginSecurityException 公钥材料无法解析时抛出。
     */
    private function publicKey(string|OpenSSLAsymmetricKey $key): OpenSSLAsymmetricKey
    {
        $resource = is_string($key) ? openssl_pkey_get_public($key) : $key;
        if (!$resource instanceof OpenSSLAsymmetricKey) {
            throw new PluginSecurityException('公钥材料无效');
        }
        return $resource;
    }

    /**
     * base64url 编码（URL 安全字母表，去掉尾部填充）。
     *
     * JOSE 各段必须使用 base64url 且不带 '=' 填充：把 '+' '/' 换成 '-' '_' 后，结果只含
     * URL 安全字符，接收端才能按字符集严格解码，出现过的填充也都由本方法统一去掉。
     *
     * @param string $value 待编码的二进制串。
     * @return string 无填充的 base64url 字符串。
     */
    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * base64url 解码（严格模式）。
     *
     * 拒绝的输入与理由：
     * - 空串：空段在 compact 序列化中没有意义，也会让后续长度判断失效；
     * - 含字母表外字符（包括 '='）：JOSE 不允许填充，放行会让不同实现对同一段产生不同解读；
     * - 长度模 4 余 1：base64 编码不可能出现该余数，说明输入被截断或伪造；
     * - 补齐填充后仍解码失败：字符组合非法。
     * 补齐 '=' 只是为了让 PHP 的严格模式解码可用，输入本身仍必须是无填充的 base64url。
     *
     * @param string $value 待解码的 base64url 字符串。
     * @return string 解码后的原始字节。
     * @throws PluginSecurityException 编码为空、字符非法、长度非法或解码失败时抛出。
     */
    private function decode(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1) {
            throw new PluginSecurityException('安全编码无效');
        }
        $padding = strlen($value) % 4;
        if ($padding === 1) {
            throw new PluginSecurityException('安全编码长度无效');
        }
        $decoded = base64_decode(strtr($value, '-_', '+/') . ($padding ? str_repeat('=', 4 - $padding) : ''), true);
        if ($decoded === false) {
            throw new PluginSecurityException('安全编码无效');
        }
        return $decoded;
    }

    /**
     * 拆分 compact token 并做整体格式校验。
     *
     * 拒绝的输入与理由：
     * - 空 token 或超过 MAX_TOKEN_LENGTH：限制外部输入规模，避免超大请求进入后续处理；
     * - 段数与期望不符（JWS 3 段、JWE 5 段）：段数错误说明类型不符或结构被破坏；
     * - 任一段为空：空段无法通过后续的 base64url 解码；
     * - 任一段含字母表外字符：与 decode() 的字符集约束保持一致，提前失败。
     *
     * @param string $token compact 字符串。
     * @param int $partsCount 期望的段数：JWS 为 3，JWE 为 5。
     * @return list<string> 按顺序拆分出的各段。
     * @throws PluginSecurityException 大小、段数、空段或字符集校验失败时抛出。
     */
    private function splitToken(string $token, int $partsCount): array
    {
        if ($token === '' || strlen($token) > self::MAX_TOKEN_LENGTH) {
            throw new PluginSecurityException('安全信封大小无效');
        }
        $parts = explode('.', $token);
        if (count($parts) !== $partsCount || in_array('', $parts, true)) {
            throw new PluginSecurityException('安全信封格式无效');
        }
        foreach ($parts as $part) {
            if (preg_match('/^[A-Za-z0-9_-]+$/', $part) !== 1) {
                throw new PluginSecurityException('安全信封编码无效');
            }
        }
        return $parts;
    }

    /**
     * 解码并解析受保护头段。
     *
     * 先按 base64url 严格解码，再交给 PluginCanonicalJson 解析；结果必须是数组，且当
     * $objectOnly 为 true 时不能是列表——JOSE 受保护头必须是 JSON 对象，数组形态会让
     * 头字段取值语义不确定，因此直接拒绝。
     *
     * @param string $segment base64url 编码的头段。
     * @param bool $objectOnly 是否要求解析结果为对象（头部解析固定传 true）。
     * @return array<string,mixed> 解析后的头部数组。
     * @throws PluginSecurityException 解码失败、解析结果非数组或为列表时抛出。
     */
    private function decodeJsonSegment(string $segment, bool $objectOnly): array
    {
        $decoded = $this->json()->decode($this->decode($segment));
        if (!is_array($decoded) || ($objectOnly && $this->isList($decoded))) {
            throw new PluginSecurityException('安全头结构无效');
        }
        return $decoded;
    }

    /**
     * 编码 JWS 载荷段。
     *
     * 数组先做确定性 JSON 编码，字符串按原样使用（嵌套结构场景）；为空或超过
     * MAX_PAYLOAD_LENGTH 的载荷直接拒绝，保证签名与验签两端对载荷规模的口径一致。
     *
     * @param array<string,mixed>|string $payload 待编码载荷。
     * @return string base64url 编码的载荷段。
     * @throws PluginSecurityException 载荷为空、超限或无法序列化时抛出。
     */
    private function encodePayload(array|string $payload): string
    {
        $value = is_array($payload) ? $this->json()->encode($payload) : $payload;
        if ($value === '' || strlen($value) > self::MAX_PAYLOAD_LENGTH) {
            throw new PluginSecurityException('JWS 载荷大小无效');
        }
        return $this->encode($value);
    }

    /**
     * 解码载荷段并尽量还原为结构化数据。
     *
     * 先严格 base64url 解码并做长度护栏，再尝试 JSON 解析：能解析成数组就返回数组，
     * 解析出其它 JSON 值或解析失败（PluginSecurityException）时返回原始字符串。
     * 因此“非 JSON 载荷按原始字符串返回”是明确约定，而不是错误。
     *
     * @param string $segment base64url 编码的载荷段。
     * @return array<string,mixed>|string JSON 数组载荷，或原始字符串载荷。
     * @throws PluginSecurityException 编码非法、载荷为空或超过大小上限时抛出。
     */
    private function decodePayload(string $segment): array|string
    {
        $raw = $this->decode($segment);
        if ($raw === '' || strlen($raw) > self::MAX_PAYLOAD_LENGTH) {
            throw new PluginSecurityException('安全载荷大小无效');
        }
        try {
            $value = $this->json()->decode($raw);
            return is_array($value) ? $value : $raw;
        } catch (PluginSecurityException) {
            // 解析不出 JSON 的载荷按原始字符串返回，由调用方按协议决定是否接受。
            return $raw;
        }
    }

    /**
     * 计算 GCM 认证附加数据（AAD）。
     *
     * 规则与 JWE 一致：AAD 为 ASCII(受保护头段)，存在外部认证数据时追加 '.' 与
     * BASE64URL(外部数据)。把受保护头纳入 AAD 可以防止头部被篡改后仍能解密；
     * 外部数据通常承载路由绑定字段，从而把密文绑定到唯一的业务入口。
     *
     * @param string $protectedSegment base64url 编码的受保护头段。
     * @param string $external 外部认证数据原文；为空表示不追加。
     * @return string 参与 AES-GCM 认证的 AAD 字符串。
     */
    private function aad(string $protectedSegment, string $external): string
    {
        return $external === ''
            ? $protectedSegment
            : $protectedSegment . '.' . $this->encode($external);
    }

    /**
     * RSA-OAEP-256 加密。GMP 是执行标准 RSA 模幂所需的运行时依赖；缺失时显式失败。
     *
     * 按 RSA-OAEP（SHA-256 摘要 + MGF1-SHA-256 掩码）手工完成 EME-OAEP 编码，再用 GMP
     * 做 c = m^e mod n 的标准模幂，避免依赖 OpenSSL 的 OAEP 填充默认值。
     * 参数：$message 为待封装的内容密钥（32 字节），$key 为接收方 RSA 公钥。
     *
     * @param string $message 待加密的明文，本类中固定为 256 位内容密钥（CEK）。
     * @param OpenSSLAsymmetricKey $key 接收方 RSA 公钥对象。
     * @return string 长度为模长（字节）的密文。
     * @throws PluginSecurityException 明文超过可编码长度、运行环境缺少 GMP 或密钥参数非法时抛出。
     */
    private function rsaOaep256Encrypt(string $message, OpenSSLAsymmetricKey $key): string
    {
        [$n, $e, $length] = $this->rsaPublicParameters($key);
        $hLen = self::HASH_LENGTH;
        // DB 区必须同时容纳 lHash、PS、0x01 分隔符和明文，故明文上限为 k - 2*hLen - 2。
        if (strlen($message) > $length - 2 * $hLen - 2) {
            throw new PluginSecurityException('RSA-OAEP 载荷过大');
        }
        // OAEP 的标签 L 为空串，因此 lHash 固定为 SHA-256("")。
        $lHash = hash('sha256', '', true);
        // 置零填充：长度由总长减去 lHash、分隔符与明文长度得到。
        $ps = str_repeat("\0", $length - strlen($message) - 2 * $hLen - 2);
        // 数据块：DB = lHash || PS || 0x01 || M，解密端据此定位明文起点。
        $db = $lHash . $ps . "\1" . $message;
        // 随机种子保证同一明文每次加密得到不同密文；缺失随机性会退化为确定性加密。
        $seed = random_bytes($hLen);
        $dbMask = $this->mgf1($seed, $length - $hLen - 1);
        $maskedDb = $this->xorBytes($db, $dbMask);
        // 用 maskedDB 生成 seed 的掩码，使 EM 的两半互相绑定，无法单独篡改。
        $seedMask = $this->mgf1($maskedDb, $hLen);
        $maskedSeed = $this->xorBytes($seed, $seedMask);
        // EM = 0x00 || maskedSeed || maskedDB；首字节 0x00 保证 EM 作为整数严格小于 n。
        $encoded = "\0" . $maskedSeed . $maskedDb;
        $m = $this->gmpImport($encoded);
        // 标准 RSA 公钥运算：c = m^e mod n。
        $c = gmp_powm($m, $e, $n);
        // 转回固定模长的密文，长度是接收端校验的前提。
        return $this->i2osp($c, $length);
    }

    /**
     * RSA-OAEP-256 解密。
     *
     * 步骤：校验密文长度必须等于模长 → 校验密文代表值小于 n → 用私钥指数做模幂
     * m = c^d mod n → I2OSP 还原 EM 并逐项校验结构（首字节 0x00、lHash 相等、PS 全零、
     * 存在 0x01 分隔符）→ 返回分隔符之后的明文（内容密钥）。
     *
     * 拒绝的输入与理由：
     * - 密文长度不等于模长：RSA 密文恒为 k 字节，长度不符说明不是本方案产物；
     * - 密文代表的整数不小于 n：超出模数范围的值会被隐式规约，必须直接拒绝；
     * - EM 结构或 lHash、PS 校验失败：说明密文被篡改或不是用对应公钥加密的。
     * lHash 使用 hash_equals 比较，避免通过响应时间泄露填充信息（Bleichenbacher 类攻击的前提）。
     *
     * @param string $ciphertext 密文，长度必须等于模长。
     * @param OpenSSLAsymmetricKey $key 接收方 RSA 私钥对象。
     * @return string 解出的明文（本类中为内容密钥）。
     * @throws PluginSecurityException 长度、代表值、EM 结构或认证校验失败时抛出。
     */
    private function rsaOaep256Decrypt(string $ciphertext, OpenSSLAsymmetricKey $key): string
    {
        [$n, $d, $length] = $this->rsaPrivateParameters($key);
        // RSA 密文长度恒等于模长，长度不符说明数据被截断或不是本方案的密文。
        if (strlen($ciphertext) !== $length) {
            throw new PluginSecurityException('RSA-OAEP 密文长度无效');
        }
        $c = $this->gmpImport($ciphertext);
        // c 必须落在 [0, n-1]，否则模幂会把超范围的代表值隐式规约，掩盖无效输入。
        if (gmp_cmp($c, $n) >= 0) {
            throw new PluginSecurityException('RSA-OAEP 密文无效');
        }
        $m = gmp_powm($c, $d, $n);
        $encoded = $this->i2osp($m, $length);
        $hLen = self::HASH_LENGTH;
        // EM 至少要有 lHash、maskedSeed 与 maskedDB，且首个字节必须是 0x00。
        if (strlen($encoded) < 2 * $hLen + 2 || $encoded[0] !== "\0") {
            throw new PluginSecurityException('RSA-OAEP 认证失败');
        }
        $maskedSeed = substr($encoded, 1, $hLen);
        $maskedDb = substr($encoded, 1 + $hLen);
        // 反向解掩码：先还原 seed，再用 seed 还原 DB。
        $seedMask = $this->mgf1($maskedDb, $hLen);
        $seed = $this->xorBytes($maskedSeed, $seedMask);
        $dbMask = $this->mgf1($seed, $length - $hLen - 1);
        $db = $this->xorBytes($maskedDb, $dbMask);
        // 用固定时间比较 lHash，确认密文确实是按本方案编码的。
        if (!hash_equals(hash('sha256', '', true), substr($db, 0, $hLen))) {
            throw new PluginSecurityException('RSA-OAEP 认证失败');
        }
        $rest = substr($db, $hLen);
        $separator = strpos($rest, "\1");
        // PS 必须是全零字节，且必须存在 0x01 分隔符，否则填充结构非法。
        if ($separator === false || trim(substr($rest, 0, $separator), "\0") !== '' || $separator < 0) {
            throw new PluginSecurityException('RSA-OAEP 认证失败');
        }
        // 分隔符之后的内容即为明文（内容密钥）。
        return substr($rest, $separator + 1);
    }

    /**
     * 从 OpenSSL 密钥参数中取出 RSA 公钥所需的 n、e 和模长。
     *
     * 拒绝的输入与理由：
     * - 缺少 rsa['n']、rsa['e'] 或运行环境没有 gmp_import：无法完成标准 OAEP 编码与模幂，
     *   此时显式失败，而不是退回弱填充；
     * - 模长小于 128 字节（1024 位）：模长同时决定密文长度和 OAEP 可编码空间，过小不安全；
     * - n 非正或 e 不大于 1：模数为零、指数为 0 或 1 都属于无效参数，会让模幂失去密码学意义。
     *
     * @param OpenSSLAsymmetricKey $key RSA 公钥对象。
     * @return array{0:\GMP,1:\GMP,2:int} 依次为模数 n、公钥指数 e、模长（字节）。
     * @throws PluginSecurityException 参数缺失、环境不支持或参数校验失败时抛出。
     */
    private function rsaPublicParameters(OpenSSLAsymmetricKey $key): array
    {
        $details = openssl_pkey_get_details($key);
        $rsa = is_array($details) ? ($details['rsa'] ?? null) : null;
        if (!is_array($rsa) || !isset($rsa['n'], $rsa['e']) || !function_exists('gmp_import')) {
            throw new PluginSecurityException('RSA-OAEP-256 运行环境不可用');
        }
        // OpenSSL 给出的 n、e 是大端字节串，按 OS2IP 规则导入为大整数。
        $n = $this->gmpImport((string)$rsa['n']);
        $e = $this->gmpImport((string)$rsa['e']);
        $length = (int)ceil(((int)($details['bits'] ?? 0)) / 8);
        if ($length < 128 || gmp_cmp($n, 0) <= 0 || gmp_cmp($e, 1) <= 0) {
            throw new PluginSecurityException('RSA 公钥参数无效');
        }
        return [$n, $e, $length];
    }

    /**
     * 从 OpenSSL 密钥参数中取出 RSA 私钥所需的 n、d 和模长。
     *
     * 拒绝的输入与理由：
     * - 缺少 rsa['n']、rsa['d'] 或运行环境没有 gmp_import：无法执行标准私钥模幂 m = c^d mod n；
     * - 模长小于 128 字节（1024 位）：密文长度与安全强度都不足；
     * - n 或 d 非正：私钥指数非法，模幂结果不可信。
     *
     * @param OpenSSLAsymmetricKey $key RSA 私钥对象。
     * @return array{0:\GMP,1:\GMP,2:int} 依次为模数 n、私钥指数 d、模长（字节）。
     * @throws PluginSecurityException 参数缺失、环境不支持或参数校验失败时抛出。
     */
    private function rsaPrivateParameters(OpenSSLAsymmetricKey $key): array
    {
        $details = openssl_pkey_get_details($key);
        $rsa = is_array($details) ? ($details['rsa'] ?? null) : null;
        if (!is_array($rsa) || !isset($rsa['n'], $rsa['d']) || !function_exists('gmp_import')) {
            throw new PluginSecurityException('RSA-OAEP-256 运行环境不可用');
        }
        $n = $this->gmpImport((string)$rsa['n']);
        $d = $this->gmpImport((string)$rsa['d']);
        $length = (int)ceil(((int)($details['bits'] ?? 0)) / 8);
        if ($length < 128 || gmp_cmp($n, 0) <= 0 || gmp_cmp($d, 0) <= 0) {
            throw new PluginSecurityException('RSA 私钥参数无效');
        }
        return [$n, $d, $length];
    }

    /**
     * 把大端字节串导入为 GMP 大整数（OS2IP）。
     *
     * OpenSSL 暴露的 n、e、d 都是大端序字节串，必须按 MSW_FIRST | BIG_ENDIAN、每字 1 字节
     * 导入，才能得到与 OpenSSL 一致的数值；返回值不是对象时抛错，避免把 false 当成整数使用。
     *
     * @param string $bytes 大端序的数值字节串。
     * @return \GMP 对应的 GMP 整数。
     * @throws PluginSecurityException 导入失败时抛出。
     */
    private function gmpImport(string $bytes): \GMP
    {
        $value = gmp_import($bytes, 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN);
        if ($value === false) {
            throw new PluginSecurityException('RSA 参数解析失败');
        }
        return $value;
    }

    /**
     * 把 GMP 整数导出为指定长度的定长大端字节串（I2OSP）。
     *
     * 导出结果为最小长度的大端字节串，0 会导出为空串，因此需要兜底成单个 0x00；
     * 结果长于 $length 说明模幂结果超出模长（正常 RSA 运算不应出现），必须拒绝；
     * 最后左侧补零到恰好 $length 字节，解密端才能按固定偏移解析 EM 结构。
     *
     * @param \GMP $value 待导出的整数。
     * @param int $length 目标字节长度（RSA 中为模长）。
     * @return string 长度为 $length 的定长大端字节串。
     * @throws PluginSecurityException 数值长度超过 $length 时抛出。
     */
    private function i2osp(\GMP $value, int $length): string
    {
        $bytes = gmp_export($value, 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN);
        // 数值 0 会被导出为空串，这里统一成 0x00，保证后续补零逻辑不依赖特殊分支。
        $bytes = $bytes === false || $bytes === '' ? "\0" : $bytes;
        if (strlen($bytes) > $length) {
            throw new PluginSecurityException('RSA 模幂结果溢出');
        }
        return str_pad($bytes, $length, "\0", STR_PAD_LEFT);
    }

    /**
     * MGF1 掩码生成函数（SHA-256）。
     *
     * 反复计算 SHA-256(seed || I2OSP(counter, 4)) 并拼接，直到长度覆盖 $length，再截断。
     * 计数器按 4 字节大端编码（pack('N')），保证与规范定义一致；生成掩码是为了让 DB 与 seed
     * 在不知道随机种子的情况下不可预测，从而隐藏填充结构。
     *
     * @param string $seed 掩码种子。
     * @param int $length 需要的掩码字节数；调用方传入的长度由模长决定，因此循环次数有界。
     * @return string 长度为 $length 的掩码字节串。
     */
    private function mgf1(string $seed, int $length): string
    {
        $output = '';
        for ($counter = 0; strlen($output) < $length; $counter++) {
            $output .= hash('sha256', $seed . pack('N', $counter), true);
        }
        return substr($output, 0, $length);
    }

    /**
     * 按字节异或两个等长字符串。
     *
     * 要求两侧长度完全一致：PHP 的字符串异或会在较短一侧结束时截断结果，长度不符时
     * 继续运算会得到长度错误的掩码，必须在运算前拦截。
     *
     * @param string $left 左操作数（数据或掩码）。
     * @param string $right 右操作数（掩码或数据）。
     * @return string 异或结果，长度与两侧一致。
     * @throws PluginSecurityException 两侧长度不一致时抛出。
     */
    private function xorBytes(string $left, string $right): string
    {
        if (strlen($left) !== strlen($right)) {
            throw new PluginSecurityException('加密参数长度不一致');
        }
        return $left ^ $right;
    }

    /**
     * 把 OpenSSL 的 DER 签名转换为 JOSE 要求的定长 R||S。
     *
     * openssl_sign 输出的是 DER SEQUENCE { INTEGER r, INTEGER s }，而 JWS ES256 规定签名段
     * 必须是 r、s 各 32 字节的定长拼接。DER 的 INTEGER 是带符号的最短编码，因此可能出现
     * 两种偏差：最高位为 1 时带一个前导 0x00，高位为零时长度不足 32 字节。这里统一去掉
     * 前导零后校验每个分量不超过 32 字节，再左补零成定长，保证两端字节一致。
     *
     * 拒绝的输入与理由：结构标记不是 0x30/0x02（不是 DER ECDSA 签名）、分量长度为 0
     * （签名内容缺失）、分量超过 32 字节（超出 P-256 的标量范围，不可能由本方案产生）。
     *
     * @param string $der DER 编码的 ECDSA 签名。
     * @return string 64 字节的 r||s 签名。
     * @throws PluginSecurityException DER 结构非法、分量为空或超出 P-256 范围时抛出。
     */
    private function derToRawSignature(string $der): string
    {
        $offset = 0;
        if (($der[$offset++] ?? '') !== "\x30") {
            throw new PluginSecurityException('ECDSA 签名格式无效');
        }
        // 序列总长在这里只用于推进游标，后续按 INTEGER 逐个取值。
        $this->readDerLength($der, $offset);
        if (($der[$offset++] ?? '') !== "\x02") {
            throw new PluginSecurityException('ECDSA 签名格式无效');
        }
        $rLength = $this->readDerLength($der, $offset);
        $r = substr($der, $offset, $rLength);
        $offset += strlen($r);
        if (($der[$offset++] ?? '') !== "\x02") {
            throw new PluginSecurityException('ECDSA 签名格式无效');
        }
        $sLength = $this->readDerLength($der, $offset);
        $s = substr($der, $offset, $sLength);
        if ($r === '' || $s === '') {
            throw new PluginSecurityException('ECDSA 签名为空');
        }
        // 去掉 DER 有符号整数可能携带的前导 0x00，得到最短的无符号表示。
        $r = ltrim($r, "\0");
        $s = ltrim($s, "\0");
        // P-256 的标量不超过 32 字节，超长说明签名不是本曲线产生的。
        if (strlen($r) > 32 || strlen($s) > 32) {
            throw new PluginSecurityException('ECDSA 签名超出 P-256 范围');
        }
        // 左补零成定长 32 字节，得到 JOSE 要求的 r||s 形式。
        return str_pad($r, 32, "\0", STR_PAD_LEFT) . str_pad($s, 32, "\0", STR_PAD_LEFT);
    }

    /**
     * 把 JOSE 的定长 R||S 还原成 OpenSSL 需要的 DER 签名。
     *
     * 与 derToRawSignature() 互逆：取前 32 字节为 r、后 32 字节为 s，去掉前导零得到最短
     * 整数表示（全零时保留单个 0x00 作为整数 0），若首字节最高位为 1 则补 0x00，避免被
     * DER 解析成负数；最后封装为 SEQUENCE { INTEGER r, INTEGER s }。
     * 调用方（verify）已保证入参为 64 字节，因此这里可以按下标直接切片。
     *
     * @param string $raw 64 字节的 r||s 签名。
     * @return string DER 编码的 ECDSA 签名。
     */
    private function rawToDerSignature(string $raw): string
    {
        // 去掉前导零；全零结果用 "\0" 兜底，避免空字符串导致后续下标访问失败。
        $r = ltrim(substr($raw, 0, 32), "\0") ?: "\0";
        $s = ltrim(substr($raw, 32, 32), "\0") ?: "\0";
        // 首字节最高位为 1 时补一个 0x00，使 DER INTEGER 保持正数语义。
        if ((ord($r[0]) & 0x80) !== 0) {
            $r = "\0" . $r;
        }
        if ((ord($s[0]) & 0x80) !== 0) {
            $s = "\0" . $s;
        }
        $body = "\x02" . $this->derLength(strlen($r)) . $r . "\x02" . $this->derLength(strlen($s)) . $s;
        return "\x30" . $this->derLength(strlen($body)) . $body;
    }

    /**
     * 从 DER 数据中读取一个长度字段，并按引用推进游标。
     *
     * 短格式（首字节最高位为 0）直接就是长度；长格式的低 7 位是后续长度字节数。
     * 拒绝的输入与理由：长度字节数为 0（DER 不允许不定长编码）或大于 4（长度无实际意义，
     * 且位移运算会失控）、声明的长度字节数超出剩余数据（数据被截断）。
     *
     * @param string $data DER 数据。
     * @param int $offset 当前读取位置，按引用推进到长度字段之后。
     * @return int 解析出的长度值。
     * @throws PluginSecurityException 长度编码非法或数据不足时抛出。
     */
    private function readDerLength(string $data, int &$offset): int
    {
        $first = ord($data[$offset++] ?? "\0");
        if (($first & 0x80) === 0) {
            return $first;
        }
        $count = $first & 0x7f;
        // 长格式长度字节数限定为 1~4，并且不能超过剩余可用数据。
        if ($count < 1 || $count > 4 || $offset + $count > strlen($data)) {
            throw new PluginSecurityException('ECDSA 长度编码无效');
        }
        $length = 0;
        for ($i = 0; $i < $count; $i++) {
            $length = ($length << 8) | ord($data[$offset++]);
        }
        return $length;
    }

    /**
     * 按 DER 规则编码长度字段。
     *
     * 小于 128 用短格式单字节；否则用长格式：首字节为 0x80 | 长度字节数，后面跟大端序的
     * 最短长度字节串。生成端必须使用最短编码，否则不同实现产出的签名字节会不一致。
     *
     * @param int $length 待编码的长度值。
     * @return string DER 长度字段字节串。
     */
    private function derLength(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }
        $encoded = '';
        while ($length > 0) {
            $encoded = chr($length & 0xff) . $encoded;
            $length >>= 8;
        }
        return chr(0x80 | strlen($encoded)) . $encoded;
    }

    /**
     * 返回确定性 JSON 编码器，未注入时按需创建默认实现。
     *
     * @return PluginCanonicalJson 编码器实例。
     */
    private function json(): PluginCanonicalJson
    {
        return $this->json ?? new PluginCanonicalJson();
    }

    /**
     * 判断数组是否为列表（JSON 数组语义）：空数组或从 0 开始的连续整数键。
     *
     * 受保护头要求必须是对象，decodeJsonSegment() 用本方法把列表形态挡在头解析之外。
     *
     * @param array $value 待判断的数组。
     * @return bool 是列表返回 true，是关联数组返回 false。
     */
    private function isList(array $value): bool
    {
        return array_keys($value) === range(0, count($value) - 1);
    }
}
