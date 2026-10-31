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

use app\common\exception\PluginSecurityException;
use app\common\plugin\security\PluginRequestCrypto;
use app\common\plugin\security\PluginRequestEnvelope;
use app\common\service\plugin\contract\PluginGatewayKeyDirectoryInterface;
use app\common\service\plugin\contract\PluginGatewayTransportInterface;
use OpenSSLAsymmetricKey;
use Throwable;

/**
 * 将逻辑请求封装为 ES256 JWS 与 RSA-OAEP-256/A256GCM JWE，仅传输密文且不因错误回退明文。
 */
final class PluginSecureGatewayClient
{
    public const DEFAULT_GATEWAY_PATH = '/openapi/secure-gateway';
    public const CONTENT_TYPE = 'application/jose';

    private PluginKeyService $keys;
    private PluginRequestCrypto $crypto;
    private PluginGatewayTransportInterface $transport;
    private PluginGatewayKeyDirectoryInterface $keyDirectory;
    private ?string $endpoint;
    private ?string $clientId;
    private ?string $gatewayOwnerId;
    private ?string $requestSigningKeyId;
    private ?string $gatewayEncryptionKeyId;
    private ?string $responseSigningKeyId;
    private ?string $instanceDecryptionKeyId;

    /**
     * 初始化本地到官网的安全网关、密钥目录和协议依赖。
     *
     * @param ?PluginKeyService $keys 本地密钥与一次性随机值管理服务。
     * @param ?PluginRequestCrypto $crypto 请求信封签名和加密组件。
     * @param ?PluginGatewayTransportInterface $transport 仅传输密文的安全网关适配器。
     * @param string|null $endpoint 官网安全网关完整 HTTPS 地址；为空时读取配置。
     * @param string|null $clientId 本地实例标识；为空时从密钥服务解析。
     * @param string|null $gatewayOwnerId 官网公钥目录中的所有者标识。
     * @param string|null $requestSigningKeyId 本地请求签名密钥标识。
     * @param string|null $gatewayEncryptionKeyId 官网请求加密公钥标识。
     * @param string|null $responseSigningKeyId 官网响应签名公钥标识；为空时从响应头解析。
     * @param string|null $instanceDecryptionKeyId 本地响应解密私钥标识。
     * @param PluginGatewayKeyDirectoryInterface|null $keyDirectory 官网 HTTPS 公钥目录读取器。
     * @return void 无返回值。
     */
    public function __construct(
        ?PluginKeyService $keys = null,
        ?PluginRequestCrypto $crypto = null,
        ?PluginGatewayTransportInterface $transport = null,
        ?string $endpoint = null,
        ?string $clientId = null,
        ?string $gatewayOwnerId = null,
        ?string $requestSigningKeyId = null,
        ?string $gatewayEncryptionKeyId = null,
        ?string $responseSigningKeyId = null,
        ?string $instanceDecryptionKeyId = null,
        ?PluginGatewayKeyDirectoryInterface $keyDirectory = null
    ) {
        $this->keys = $keys ?? new PluginKeyService();
        $this->crypto = $crypto ?? new PluginRequestCrypto();
        $this->transport = $transport ?? new CurlPluginGatewayTransport();
        $this->keyDirectory = $keyDirectory ?? new CurlPluginGatewayKeyDirectory();
        $this->endpoint = $endpoint !== null ? trim($endpoint) : null;
        $this->clientId = $clientId !== null ? trim($clientId) : null;
        $this->gatewayOwnerId = $gatewayOwnerId !== null ? trim($gatewayOwnerId) : null;
        $this->requestSigningKeyId = $requestSigningKeyId !== null ? trim($requestSigningKeyId) : null;
        $this->gatewayEncryptionKeyId = $gatewayEncryptionKeyId !== null ? trim($gatewayEncryptionKeyId) : null;
        $this->responseSigningKeyId = $responseSigningKeyId !== null ? trim($responseSigningKeyId) : null;
        $this->instanceDecryptionKeyId = $instanceDecryptionKeyId !== null ? trim($instanceDecryptionKeyId) : null;
    }

    /**
     * 创建并发送逻辑请求，载荷网关动作必须与逻辑路由一致。
     *
     * @param string $logicalMethod 仅允许 GET、POST、PUT、PATCH 或 DELETE 的逻辑方法。
     * @param string $logicalPath 官网白名单逻辑路径，不包含查询参数。
     * @param array<string,mixed> $body 仅进入加密信封的业务载荷。
     * @param string|null $idempotencyKey 写请求幂等键；未提供时生成一次性键。
     * @param array<string,mixed> $options 密钥、网关地址、信封时间和传输约束选项。
     * @return array<string,mixed> 已验签解密的官网业务响应，不附加内部密钥资料。
     * @throws PluginSecurityException 任一密钥、协议、nonce、传输或验签失败时抛出。
     */
    public function request(
        string $logicalMethod,
        string $logicalPath,
        array $body = [],
        ?string $idempotencyKey = null,
        array $options = []
    ): array {
        $method = strtoupper(trim($logicalMethod));
        $gatewayMethod = $this->resolveGatewayMethod($method, $logicalPath);
        if (array_key_exists('method', $body)
            && (string)$body['method'] !== $gatewayMethod
        ) {
            throw new PluginSecurityException('安全请求网关方法不匹配');
        }
        $body['method'] = $gatewayMethod;

        // 写请求重试时必须保持同一幂等键；每轮仍创建全新的 nonce 和 request_id。
        $stableIdempotencyKey = $idempotencyKey;
        if ($stableIdempotencyKey === null
            && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)
        ) {
            $stableIdempotencyKey = 'req_' . bin2hex(random_bytes(16));
        }

        $attempts = $this->resolveRetryAttempts($options);
        $lastException = null;
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $envelope = $this->createRequestEnvelope(
                    $method,
                    $logicalPath,
                    $body,
                    $stableIdempotencyKey,
                    $options
                );
                return $this->requestEnvelope($envelope, $options);
            } catch (PluginSecurityException $exception) {
                $lastException = $exception;
                if ($attempt >= $attempts || !$this->isRetryableFailure($exception)) {
                    throw $exception;
                }
                $this->waitBeforeRetry($attempt, $options);
            }
        }

        // 循环必定在成功或抛出异常时结束；保留防御性兜底避免静态分析误判无返回值。
        throw $lastException ?? new PluginSecurityException('安全网关请求失败');
    }

    /**
     * 将逻辑方法与路径转换为官网网关分发动作。
     *
     * @param string $logicalMethod 逻辑接口 HTTP 方法。
     * @param string $logicalPath 官网逻辑接口路径。
     * @return string 稳定且受约束的官网网关动作名。
     */
    private function resolveGatewayMethod(string $logicalMethod, string $logicalPath): string
    {
        $route = strtoupper(trim($logicalMethod)) . ' ' . trim($logicalPath);
        $methods = [
            'GET /openapi/plugin-market/categories' => 'catalog_categories',
            'GET /openapi/plugin-market/list' => 'catalog_list',
            'GET /openapi/plugin-market/featured' => 'catalog_featured',
            'GET /openapi/plugin-market/orders' => 'order_list',
            'GET /openapi/plugin-market/licenses' => 'license_list',
            'GET /openapi/plugin-market/downloads' => 'download_list',
            'GET /openapi/plugin-market/user-info' => 'user_info',
            'GET /openapi/plugin-market/asset-logs' => 'asset_logs',
            'GET /openapi/plugin-market/balance' => 'balance',
            'GET /openapi/plugin-market/publish/categories' => 'publish_categories',
            'GET /openapi/plugin-market/publish/my-list' => 'publish_list',
            'POST /openapi/plugin-market/publish/inspect' => 'publish_inspect',
            'POST /openapi/plugin-market/publish' => 'publish',
            'POST /openapi/plugin-market/upload/image' => 'upload_image',
            'POST /openapi/plugin-market/order/create' => 'order_create',
            'POST /openapi/plugin-market/order/pay' => 'order_pay',
            'GET /openapi/plugin-market/order/status' => 'order_status',
            'POST /openapi/plugin-market/order/cancel' => 'order_cancel',
            'POST /openapi/plugin-market/download/ticket' => 'download_ticket',
            'GET /openapi/plugin-market/download' => 'download_file',
            'POST /openapi/plugin-market/user-update' => 'user_update',
            'POST /openapi/plugin-market/publish/submit' => 'publish_submit',
            'POST /openapi/plugin-market/publish/status' => 'publish_status',
            'POST /openapi/plugin-market/verify/personal' => 'verify_personal',
            'POST /openapi/plugin-market/verify/enterprise' => 'verify_enterprise',
        ];
        if (isset($methods[$route])) {
            return $methods[$route];
        }
        if (preg_match('#^GET /openapi/plugin-market/detail/[A-Za-z0-9_-]+$#', $route) === 1) {
            return 'catalog_detail';
        }
        if (preg_match('#^POST /openapi/plugin-builds/\d+/mp-upload$#', $route) === 1) {
            return 'build_mp_upload';
        }
        if (preg_match('#^POST /openapi/plugin-builds/\d+/mp-upload/retry$#', $route) === 1) {
            return 'build_mp_upload_retry';
        }
        if (preg_match('#^GET /openapi/plugin-builds/\d+/mp-artifact-url$#', $route) === 1) {
            return 'build_mp_artifact_url';
        }
        // 测试替身和未来官网方法仍可通过末段获得稳定、受约束的默认名称。
        $segment = trim((string)basename(trim($logicalPath, '/')));
        $method = strtolower(trim($logicalMethod));
        if ($segment !== '' && preg_match('/^[a-z0-9_-]+$/', $segment) === 1) {
            return $method . '_' . str_replace('-', '_', $segment);
        }
        throw new PluginSecurityException('安全请求逻辑路由无法转换为网关方法');
    }

    /**
     * 发送既有信封；随机值只能消费一次，重试须创建新信封并复用业务幂等键。
     *
     * @param PluginRequestEnvelope $envelope 已创建且尚未消费随机值的请求信封。
     * @param array<string,mixed> $options 密钥、网关地址及传输约束选项。
     * @return array<string,mixed> 已解密验签并核对路由绑定的响应载荷。
     */
    public function requestEnvelope(PluginRequestEnvelope $envelope, array $options = []): array
    {
        $configuredClientId = $this->resolveClientId($options);
        if ($configuredClientId !== '' && !hash_equals($configuredClientId, $envelope->getClientId())) {
            throw new PluginSecurityException('安全请求客户端标识不匹配');
        }

        [$signingKey, $signingKid] = $this->resolveRequestSigningKey($options);
        [$encryptionKey, $encryptionKid] = $this->resolveGatewayEncryptionKey($options);

        try {
            $jws = $this->crypto->sign(
                $envelope->toArray(),
                $signingKey,
                $signingKid,
                ['cty' => 'JWT']
            );
            $jwe = $this->crypto->encrypt(
                $jws,
                $encryptionKey,
                $encryptionKid,
                array_merge(['cty' => 'JWT'], $envelope->publicHeader()),
                $envelope->aad()
            );
        } catch (PluginSecurityException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new PluginSecurityException('安全请求封装失败', 0, $exception);
        }

        // 在发送前登记 nonce；传输失败时调用方必须以新 nonce 重试。
        try {
            if (!$this->keys->consumeNonce($envelope)) {
                throw new PluginSecurityException('安全请求 nonce 已使用', 0);
            }
        } catch (PluginSecurityException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new PluginSecurityException('安全请求 nonce 校验失败', 0, $exception);
        }

        $endpoint = $this->resolveEndpoint($options);
        $headers = [
            'X-Plugin-Protocol-Version' => PluginRequestEnvelope::PROTOCOL_VERSION,
            'X-Plugin-Request-Id' => $envelope->getRequestId(),
        ];
        $transportOptions = $this->transportOptions($options);
        try {
            $response = $this->transport->request($endpoint, $jwe, $headers, $transportOptions);
        } catch (PluginSecurityException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new PluginSecurityException('安全网关传输失败', 0, $exception);
        }
        if (!is_string($response) || trim($response) === '') {
            throw new PluginSecurityException('安全网关响应为空');
        }

        return $this->decryptResponse($response, $envelope, $options + [
            'request_signing_key_id' => $signingKid,
            'gateway_encryption_key_id' => $encryptionKid,
        ]);
    }

    /**
     * 只创建逻辑请求信封，不发起网络请求，便于审计与测试。
     *
     * @param string $logicalMethod 仅允许 GET、POST、PUT、PATCH 或 DELETE 的逻辑方法。
     * @param string $logicalPath 官网白名单逻辑路径，不包含查询参数。
     * @param array<string,mixed> $body 仅进入加密信封的业务载荷。
     * @param ?string $idempotencyKey 写请求幂等键；未提供时生成一次性键。
     * @param array<string,mixed> $options 密钥、网关地址、信封时间和传输约束选项。
     * @return PluginRequestEnvelope 经过校验的请求信封对象。
     */
    public function createRequestEnvelope(
        string $logicalMethod,
        string $logicalPath,
        array $body = [],
        ?string $idempotencyKey = null,
        array $options = []
    ): PluginRequestEnvelope {
        $method = strtoupper(trim($logicalMethod));
        $isWrite = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        if ($idempotencyKey === null && $isWrite) {
            $idempotencyKey = 'req_' . bin2hex(random_bytes(16));
        }
        if ($idempotencyKey === null) {
            $idempotencyKey = '';
        }
        $clientId = (string)($options['client_id'] ?? $this->resolveClientId($options));
        if ($clientId === '') {
            throw new PluginSecurityException('安全请求缺少客户端标识');
        }
        try {
            return PluginRequestEnvelope::create(
                $body,
                $clientId,
                $method,
                $logicalPath,
                $idempotencyKey,
                isset($options['timestamp']) ? (int)$options['timestamp'] : null,
                isset($options['nonce']) ? (string)$options['nonce'] : null,
                isset($options['request_id']) ? (string)$options['request_id'] : null
            );
        } catch (PluginSecurityException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new PluginSecurityException('安全请求信封无效', 0, $exception);
        }
    }

    /**
     * 解密 JWE 中的官网 JWS，并验签及核对请求身份与路由。
     *
     * @param string $jwe 官网返回的 JWE 密文响应。
     * @param PluginRequestEnvelope|array<string,mixed>|null $request 原请求信封或字段；用于核对请求 ID 与路由绑定。
     * @param array<string,mixed> $options 响应验签、解密密钥和所有者约束选项。
     * @return array<string,mixed> 已验证的原始载荷，不附加任何内部密钥信息。
     */
    public function decryptResponse(
        string $jwe,
        PluginRequestEnvelope|array|null $request = null,
        array $options = []
    ): array {
        if (trim($jwe) === '') {
            throw new PluginSecurityException('安全响应不能为空');
        }
        $expectedRequest = $request instanceof PluginRequestEnvelope
            ? $request
            : (is_array($request) ? PluginRequestEnvelope::fromArray($request) : null);

        try {
            $jweHeader = $this->crypto->peekHeader($jwe);
        } catch (PluginSecurityException $exception) {
            throw new PluginSecurityException(
                '开放平台返回的响应不是有效 JWE 安全信封，请检查网关地址及响应协议',
                0,
                $exception
            );
        }

        try {
            $aad = $this->aadFromHeader($jweHeader);
            [$decryptionKey, $decryptionKid] = $this->resolveInstanceDecryptionKey(
                $options,
                (string)($jweHeader['kid'] ?? '')
            );
            $rawJws = $this->crypto->decrypt(
                $jwe,
                $decryptionKey,
                $decryptionKid,
                $aad,
                ['raw' => true, 'required_cty' => 'JWT']
            );
            if (!is_string($rawJws)) {
                throw new PluginSecurityException('安全响应载荷无效');
            }
            $jwsHeader = $this->crypto->peekJwsHeader($rawJws);
            [$verificationKey, $verificationKid] = $this->resolveGatewaySigningKey($jwsHeader, $options);
            $payload = $this->crypto->verify(
                $rawJws,
                $verificationKey,
                $verificationKid,
                ['required_cty' => 'JWT']
            );
            if (!is_array($payload)) {
                throw new PluginSecurityException('安全响应必须为对象');
            }
            $this->assertResponseBinding($payload, $jweHeader, $expectedRequest);
            return $payload;
        } catch (PluginSecurityException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new PluginSecurityException('安全响应校验失败', 0, $exception);
        }
    }

    /**
     * 提供远程业务请求的语义化入口，沿用完整加密流程。
     *
     * @param string $logicalMethod 仅允许 GET、POST、PUT、PATCH 或 DELETE 的逻辑方法。
     * @param string $logicalPath 官网白名单逻辑路径，不包含查询参数。
     * @param array $body 仅进入加密信封的业务载荷。
     * @param ?string $idempotencyKey 写请求幂等键；未提供时生成一次性键。
     * @param array $options 密钥、网关地址、信封时间和传输约束选项。
     * @return array 已验签解密的官网响应。
     */
    public function send(
        string $logicalMethod,
        string $logicalPath,
        array $body = [],
        ?string $idempotencyKey = null,
        array $options = []
    ): array {
        return $this->request($logicalMethod, $logicalPath, $body, $idempotencyKey, $options);
    }

    /**
     * 解析本地请求签名私钥与声明标识，兼容受约束的旧环境别名。
     *
     * @param array<string,mixed> $options 请求签名密钥、标识及实例所有者选项。
     * @return array{0:string|OpenSSLAsymmetricKey,1:string} 请求签名私钥及签名头使用的密钥标识。
     */
    private function resolveRequestSigningKey(array $options): array
    {
        $key = $options['request_signing_private_key'] ?? $options['signing_private_key'] ?? null;
        $kid = (string)($options['request_signing_key_id'] ?? $options['signing_key_id'] ?? $this->requestSigningKeyId ?? '');
        if ($kid === '') {
            $kid = trim((string)$this->config('plugin.gateway.request_signing_key_id', ''));
        }
        if ($key !== null) {
            $this->assertKeyMaterial($key, true);
            if ($kid === '') {
                throw new PluginSecurityException('请求签名私钥缺少 kid');
            }
            return [$key, $kid];
        }
        $ownerId = (string)($options['instance_owner_id'] ?? $this->keys->instanceId());
        if ($kid === '') {
            $metadata = $this->keys->ensureLocalKeyPair(PluginKeyService::KEY_USE_SIGNING, $ownerId);
            $kid = (string)($metadata['key_id'] ?? '');
        }
        if ($kid === '') {
            throw new PluginSecurityException('本地请求签名密钥不存在');
        }
        try {
            return [$this->keys->getPrivateKey($kid, PluginKeyService::KEY_USE_SIGNING, $ownerId), $kid];
        } catch (PluginSecurityException $exception) {
            // 兼容旧环境随机 kid：签名头使用官网约定值，私钥仍取当前有效本地密钥。
            if ($this->requestSigningKeyId !== null
                || $this->config('plugin.gateway.request_signing_key_id', '') === ''
            ) {
                throw $exception;
            }
            $active = $this->keys->getActivePublicKey(
                PluginKeyService::OWNER_INSTANCE,
                $ownerId,
                PluginKeyService::KEY_USE_SIGNING
            );
            $activeKid = (string)($active['key_id'] ?? '');
            if ($activeKid === '') {
                throw $exception;
            }
            return [$this->keys->getPrivateKey($activeKid, PluginKeyService::KEY_USE_SIGNING, $ownerId), $kid];
        }
    }

    /**
     * 解析官网加密公钥，首次缺失时从 HTTPS 公钥目录引导。
     *
     * @param array<string,mixed> $options 官网加密公钥、标识及网关所有者选项。
     * @return array{0:string|OpenSSLAsymmetricKey,1:string} 官网请求加密公钥及其密钥标识。
     */
    private function resolveGatewayEncryptionKey(array $options): array
    {
        $key = $options['gateway_encryption_public_key'] ?? $options['encryption_public_key'] ?? null;
        $kid = (string)($options['gateway_encryption_key_id'] ?? $options['encryption_key_id'] ?? $this->gatewayEncryptionKeyId ?? '');
        if ($key !== null) {
            $this->assertKeyMaterial($key, false);
            if ($kid === '') {
                throw new PluginSecurityException('官网加密公钥缺少 kid');
            }
            return [$key, $kid];
        }
        $ownerId = $this->resolveGatewayOwnerId($options);
        if ($kid === '') {
            $metadata = $this->keys->getActivePublicKey(
                PluginKeyService::OWNER_GATEWAY,
                $ownerId,
                PluginKeyService::KEY_USE_ENCRYPTION
            );
            if ($metadata === null) {
                $this->synchronizeGatewayKeyDirectory($options);
                $metadata = $this->keys->getActivePublicKey(
                    PluginKeyService::OWNER_GATEWAY,
                    $ownerId,
                    PluginKeyService::KEY_USE_ENCRYPTION
                );
            }
            $kid = (string)($metadata['key_id'] ?? '');
            $key = $metadata['public_key_pem'] ?? null;
        } else {
            $metadata = $this->getGatewayPublicKey($kid, $options);
            $key = $metadata['public_key_pem'] ?? null;
        }
        if (!is_string($key) || $key === '' || $kid === '') {
            throw new PluginSecurityException('官网请求加密公钥不存在');
        }
        $this->assertKeyMaterial($key, false);
        return [$key, $kid];
    }

    /**
     * 解析本地响应解密私钥，稳定别名仅可绑定当前有效密钥。
     *
     * @param array<string,mixed> $options 本地响应私钥、标识及实例所有者选项。
     * @param string $headerKid JWE 响应头中的密钥标识。
     * @return array{0:string|OpenSSLAsymmetricKey,1:string} 本地响应解密私钥及实际验证的密钥标识。
     */
    private function resolveInstanceDecryptionKey(array $options, string $headerKid = ''): array
    {
        $key = $options['instance_decryption_private_key'] ?? $options['decryption_private_key'] ?? null;
        $hasExplicitKid = array_key_exists('instance_decryption_key_id', $options)
            || array_key_exists('decryption_key_id', $options)
            || $this->instanceDecryptionKeyId !== null;
        $headerKid = trim($headerKid);
        $kid = (string)($options['instance_decryption_key_id'] ?? $options['decryption_key_id'] ?? $this->instanceDecryptionKeyId ?? '');
        if ($kid === '') {
            $kid = trim((string)$this->config(
                'plugin.gateway.instance_decryption_key_id',
                'instance-encrypt-v1'
            ));
        }
        if ($key !== null) {
            $this->assertKeyMaterial($key, true);
            if ($kid === '') {
                throw new PluginSecurityException('响应解密私钥缺少 kid');
            }
            return [$key, $kid];
        }
        $ownerId = (string)($options['instance_owner_id'] ?? $this->keys->instanceId());
        if ($kid === '') {
            $metadata = $this->keys->ensureLocalKeyPair(PluginKeyService::KEY_USE_ENCRYPTION, $ownerId);
            $kid = (string)($metadata['key_id'] ?? '');
        }
        if ($kid === '') {
            throw new PluginSecurityException('本地响应解密密钥不存在');
        }
        try {
            return [$this->keys->getPrivateKey($kid, PluginKeyService::KEY_USE_ENCRYPTION, $ownerId), $kid];
        } catch (PluginSecurityException $exception) {
            // 官网使用稳定别名时，本地密钥表仍保留随机 key_id；私钥必须取当前有效密钥。
            $configuredKid = trim((string)$this->config(
                'plugin.gateway.instance_decryption_key_id',
                'instance-encrypt-v1'
            ));
            if ($hasExplicitKid || $configuredKid === '' || $kid !== $configuredKid) {
                throw $exception;
            }
            $active = $this->keys->getActivePublicKey(
                PluginKeyService::OWNER_INSTANCE,
                $ownerId,
                PluginKeyService::KEY_USE_ENCRYPTION
            );
            $activeKid = (string)($active['key_id'] ?? '');
            if ($activeKid === '') {
                throw $exception;
            }
            if ($headerKid !== '' && $headerKid !== $kid) {
                if ($headerKid !== $activeKid) {
                    throw $exception;
                }
                return [
                    $this->keys->getPrivateKey($activeKid, PluginKeyService::KEY_USE_ENCRYPTION, $ownerId),
                    $headerKid,
                ];
            }
            return [
                $this->keys->getPrivateKey($activeKid, PluginKeyService::KEY_USE_ENCRYPTION, $ownerId),
                $kid,
            ];
        }
    }

    /**
     * 核对官网响应签名头标识并解析受信官网公钥。
     *
     * @param array<string,mixed> $jwsHeader 官网响应 JWS 公开头。
     * @param array<string,mixed> $options 预期响应公钥、标识及网关所有者选项。
     * @return array{0:string|OpenSSLAsymmetricKey,1:string} 官网响应验签公钥及其密钥标识。
     */
    private function resolveGatewaySigningKey(array $jwsHeader, array $options): array
    {
        $headerKid = (string)($jwsHeader['kid'] ?? '');
        $key = $options['gateway_signing_public_key'] ?? $options['response_signing_public_key'] ?? null;
        $expectedKid = (string)($options['response_signing_key_id'] ?? $options['gateway_signing_key_id'] ?? $this->responseSigningKeyId ?? '');
        if ($expectedKid !== '' && ($headerKid === '' || !hash_equals($expectedKid, $headerKid))) {
            throw new PluginSecurityException('官网响应签名 kid 不匹配');
        }
        $kid = $expectedKid !== '' ? $expectedKid : $headerKid;
        if ($kid === '') {
            throw new PluginSecurityException('官网响应签名缺少 kid');
        }
        if ($key !== null) {
            $this->assertKeyMaterial($key, false);
            return [$key, $kid];
        }
        $metadata = $this->getGatewayPublicKey($kid, $options);
        $key = $metadata['public_key_pem'] ?? null;
        if (!is_string($key) || $key === '') {
            throw new PluginSecurityException('官网响应签名公钥不存在');
        }
        if (($metadata['owner_type'] ?? '') !== PluginKeyService::OWNER_GATEWAY) {
            throw new PluginSecurityException('官网响应签名公钥所有者无效');
        }
        $this->assertKeyMaterial($key, false);
        return [$key, $kid];
    }

    /**
     * 获取官网公钥，首次缺失时同步官网 HTTPS 密钥目录。
     *
     * @param string $kid 预期官网公钥标识。
     * @param array<string,mixed> $options 官网所有者和密钥目录同步选项。
     * @return array<string,mixed> 已登记的官网公钥元数据。
     */
    private function getGatewayPublicKey(string $kid, array $options): array
    {
        try {
            return $this->keys->getPublicKey($kid);
        } catch (PluginSecurityException $exception) {
            if ($exception->getMessage() !== '插件密钥不存在') {
                throw $exception;
            }
        }

        $this->synchronizeGatewayKeyDirectory($options);
        return $this->keys->getPublicKey($kid);
    }

    /**
     * 同步官网加密与响应签名公钥，只接受官网所有者且拒绝同标识材料覆盖。
     *
     * @param array<string,mixed> $options 网关所有者、密钥目录地址和传输约束选项。
     * @return void 无返回值；目录或公钥身份非法时抛出异常。
     */
    private function synchronizeGatewayKeyDirectory(array $options): void
    {
        $ownerId = $this->resolveGatewayOwnerId($options);
        $endpoint = $this->resolveGatewayKeyDirectoryEndpoint($options);
        try {
            $response = $this->keyDirectory->fetch($endpoint, $this->transportOptions($options));
        } catch (PluginSecurityException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new PluginSecurityException('官网密钥目录同步失败', 0, $exception);
        }

        if (array_key_exists('status', $response) && (int)$response['status'] !== 200) {
            throw new PluginSecurityException('官网密钥目录状态无效');
        }
        $directory = $response['data'] ?? $response;
        if (!is_array($directory)) {
            throw new PluginSecurityException('官网密钥目录数据无效');
        }
        if ((string)($directory['protocol_version'] ?? '') !== PluginRequestEnvelope::PROTOCOL_VERSION) {
            throw new PluginSecurityException('官网密钥目录协议版本不受支持');
        }
        if ((string)($directory['owner_type'] ?? '') !== PluginKeyService::OWNER_GATEWAY
            || (string)($directory['owner_id'] ?? '') !== $ownerId
        ) {
            throw new PluginSecurityException('官网密钥目录所有者无效');
        }

        $notBefore = $this->normalizeDirectoryTime($directory['not_before'] ?? null);
        $expiresAt = $this->normalizeDirectoryTime($directory['expires_at'] ?? null);
        $encryptionKey = $this->normalizeDirectoryKey(
            $directory,
            PluginKeyService::KEY_USE_ENCRYPTION,
            PluginKeyService::ALGORITHM_RSA_OAEP_256,
            $ownerId,
            $notBefore,
            $expiresAt
        );

        $signingKeyData = $directory['response_signing_key'] ?? null;
        if (!is_array($signingKeyData)) {
            throw new PluginSecurityException('官网响应签名公钥缺失');
        }
        $signingKey = $this->normalizeDirectoryKey(
            $signingKeyData,
            PluginKeyService::KEY_USE_SIGNING,
            PluginKeyService::ALGORITHM_ES256,
            $ownerId,
            $notBefore,
            $expiresAt
        );

        $this->registerGatewayPublicKey($encryptionKey);
        $this->registerGatewayPublicKey($signingKey);
    }

    /**
     * 校验目录公钥用途、算法和所有者，并统一登记字段。
     *
     * @param array<string,mixed> $data 密钥目录中的原始公钥字段。
     * @param string $keyUse 预期加密或签名用途。
     * @param string $algorithm 预期密码算法。
     * @param string $ownerId 预期官网所有者标识。
     * @param ?string $notBefore 目录声明的生效时间。
     * @param ?string $expiresAt 目录声明的过期时间。
     * @return array<string,mixed> 可登记的规范化官网公钥元数据。
     */
    private function normalizeDirectoryKey(
        array $data,
        string $keyUse,
        string $algorithm,
        string $ownerId,
        ?string $notBefore,
        ?string $expiresAt
    ): array {
        $keyId = trim((string)($data['kid'] ?? $data['key_id'] ?? ''));
        $publicKey = trim((string)($data['public_key_pem'] ?? ''));
        $reportedUse = trim((string)($data['key_use'] ?? ''));
        $reportedAlgorithm = trim((string)($data['algorithm'] ?? ''));
        if ($keyId === '' || $publicKey === ''
            || ($reportedUse !== '' && $reportedUse !== $keyUse)
            || ($reportedAlgorithm !== '' && $reportedAlgorithm !== $algorithm)
        ) {
            throw new PluginSecurityException('官网密钥目录公钥信息无效');
        }
        if (isset($data['owner_type'])
            && (string)$data['owner_type'] !== PluginKeyService::OWNER_GATEWAY
        ) {
            throw new PluginSecurityException('官网密钥目录公钥所有者无效');
        }
        if (isset($data['owner_id']) && (string)$data['owner_id'] !== $ownerId) {
            throw new PluginSecurityException('官网密钥目录公钥所有者无效');
        }

        return [
            'key_id' => $keyId,
            'owner_type' => PluginKeyService::OWNER_GATEWAY,
            'owner_id' => $ownerId,
            'key_use' => $keyUse,
            'algorithm' => $algorithm,
            'public_key_pem' => $publicKey,
            'not_before' => $notBefore,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * 幂等登记官网公钥，拒绝同标识不同材料的覆盖。
     *
     * @param array<string,mixed> $key 已校验的官网公钥元数据。
     * @return void 无返回值；并发登记仅接受完全一致的已有公钥。
     */
    private function registerGatewayPublicKey(array $key): void
    {
        $keyId = (string)$key['key_id'];
        try {
            $existing = $this->keys->getPublicKey($keyId);
        } catch (PluginSecurityException $exception) {
            if ($exception->getMessage() !== '插件密钥不存在') {
                throw $exception;
            }
            $existing = null;
        }

        if ($existing !== null) {
            if (!$this->sameGatewayPublicKey($existing, $key)) {
                throw new PluginSecurityException('官网公钥材料与已登记 kid 不匹配');
            }
            return;
        }

        try {
            $this->keys->registerPublicKey($key);
        } catch (PluginSecurityException $exception) {
            // 并发请求可能由另一个进程先完成登记；只接受完全一致的结果。
            try {
                $existing = $this->keys->getPublicKey($keyId);
            } catch (PluginSecurityException) {
                throw $exception;
            }
            if (!$this->sameGatewayPublicKey($existing, $key)) {
                throw new PluginSecurityException('官网公钥材料与已登记 kid 不匹配', 0, $exception);
            }
        }
    }

    /**
     * 比较官网公钥所有者、用途、算法与实际 PEM 材料。
     *
     * @param array<string,mixed> $left 已登记公钥元数据。
     * @param array<string,mixed> $right 待登记公钥元数据。
     * @return bool 两份公钥身份和材料是否一致。
     */
    private function sameGatewayPublicKey(array $left, array $right): bool
    {
        foreach (['owner_type', 'owner_id', 'key_use', 'algorithm'] as $field) {
            if ((string)($left[$field] ?? '') !== (string)($right[$field] ?? '')) {
                return false;
            }
        }
        return hash_equals(
            trim((string)($left['public_key_pem'] ?? '')),
            trim((string)($right['public_key_pem'] ?? ''))
        );
    }

    /**
     * 将目录时间规范化为持久化时间字段。
     *
     * @param mixed $value 空值、时间戳或时间文本。
     * @return ?string 规范化时间；未声明时为 null。
     */
    private function normalizeDirectoryTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            $timestamp = $value;
        } elseif (is_string($value)) {
            $timestamp = strtotime($value);
        } else {
            throw new PluginSecurityException('官网密钥目录时间无效');
        }
        if ($timestamp === false || $timestamp <= 0) {
            throw new PluginSecurityException('官网密钥目录时间无效');
        }
        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * 解析官网公钥目录地址，未显式设置时从安全网关地址推导。
     *
     * @param array<string,mixed> $options 可选密钥目录地址及网关地址。
     * @return string 官网公钥目录完整地址。
     */
    private function resolveGatewayKeyDirectoryEndpoint(array $options): string
    {
        $endpoint = trim((string)($options['gateway_key_directory_endpoint']
            ?? $this->config('plugin.gateway.key_directory_endpoint', '')));
        if ($endpoint === '') {
            $endpoint = trim((string)(getenv('PLUGIN_GATEWAY_KEY_DIRECTORY_ENDPOINT') ?: ''));
        }
        if ($endpoint !== '') {
            return $endpoint;
        }

        $gatewayEndpoint = $this->resolveEndpoint($options);
        $parts = parse_url($gatewayEndpoint);
        if (!is_array($parts)) {
            throw new PluginSecurityException('官网密钥目录地址无效');
        }
        $path = '/' . trim((string)($parts['path'] ?? ''), '/');
        if ($path === '/') {
            $basePath = '/openapi';
        } else {
            $basePath = str_ends_with($path, '/secure-gateway')
                ? substr($path, 0, -strlen('/secure-gateway'))
                : $path;
            $basePath = rtrim($basePath, '/');
            if ($basePath === '') {
                $basePath = '/openapi';
            }
        }

        $url = strtolower((string)($parts['scheme'] ?? 'https')) . '://'
            . (string)($parts['host'] ?? '');
        if (isset($parts['port'])) {
            $url .= ':' . $parts['port'];
        }
        return $url . $basePath . '/security/public-key';
    }

    /**
     * 核对响应头、载荷和原请求的身份及路由，防止跨请求替换。
     *
     * @param array<string,mixed> $payload 已验签的官网响应载荷。
     * @param array<string,mixed> $header JWE 公开路由绑定头。
     * @param ?PluginRequestEnvelope $request 原始请求信封；未提供时只校验响应内部一致性。
     * @return void 无返回值；任一绑定不一致时抛出异常。
     */
    private function assertResponseBinding(
        array $payload,
        array $header,
        ?PluginRequestEnvelope $request
    ): void {
        foreach (['request_id', 'client_id', 'logical_method', 'logical_path'] as $field) {
            if (!isset($header[$field]) || !is_string($header[$field]) || $header[$field] === '') {
                throw new PluginSecurityException('官网响应缺少路由绑定头');
            }
            if (array_key_exists($field, $payload)
                && (!is_scalar($payload[$field])
                    || (string)$payload[$field] !== (string)$header[$field])
            ) {
                throw new PluginSecurityException('官网响应路由绑定校验失败');
            }
        }
        if (($payload['protocol_version'] ?? PluginRequestEnvelope::PROTOCOL_VERSION)
            !== PluginRequestEnvelope::PROTOCOL_VERSION
        ) {
            throw new PluginSecurityException('官网响应协议版本不受支持');
        }
        if ($request !== null) {
            if (!hash_equals($request->getRequestId(), (string)$header['request_id'])
                || !hash_equals($request->getClientId(), (string)$header['client_id'])
                || $request->logicalMethod !== (string)$header['logical_method']
                || $request->logicalPath !== (string)$header['logical_path']
            ) {
                throw new PluginSecurityException('官网响应与请求路由不匹配');
            }
        }
    }

    /**
     * 按固定顺序从响应路由头构造附加认证数据。
     *
     * @param array<string,mixed> $header 包含逻辑方法、路径、客户端和请求 ID 的响应头。
     * @return string 用于 JWE 解密验证的附加认证数据。
     */
    private function aadFromHeader(array $header): string
    {
        foreach (['logical_method', 'logical_path', 'client_id', 'request_id'] as $field) {
            if (!isset($header[$field]) || !is_string($header[$field]) || $header[$field] === '') {
                throw new PluginSecurityException('安全响应缺少认证路由字段');
            }
        }
        return implode("\n", [
            strtoupper($header['logical_method']),
            $header['logical_path'],
            $header['client_id'],
            $header['request_id'],
        ]);
    }

    /**
     * 解析安全网关地址并限制为无凭据、查询和片段的 HTTPS 地址。
     *
     * @param array<string,mixed> $options 可选服务器网关地址。
     * @return string 符合安全传输约束的网关地址。
     */
    private function resolveEndpoint(array $options): string
    {
        $endpoint = trim((string)($options['endpoint'] ?? $this->endpoint ?? $this->config('plugin.gateway.endpoint', '')));
        if ($endpoint === '') {
            $endpoint = trim((string)(getenv('PLUGIN_GATEWAY_ENDPOINT') ?: ''));
        }
        if ($endpoint === '') {
            throw new PluginSecurityException('未配置插件安全网关地址');
        }
        $parts = parse_url($endpoint);
        if (!is_array($parts)
            || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
            || trim((string)($parts['host'] ?? '')) === ''
            || isset($parts['query'])
            || isset($parts['fragment'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new PluginSecurityException('插件安全网关必须使用 HTTPS 地址');
        }
        $path = (string)($parts['path'] ?? '');
        if ($path === '' || $path === '/') {
            $endpoint = rtrim($endpoint, '/') . self::DEFAULT_GATEWAY_PATH;
        }
        return $endpoint;
    }

    /**
     * 只透传明确允许的超时与 TLS 校验选项。
     *
     * @param array<string,mixed> $options 原始请求选项或嵌套传输选项。
     * @return array<string,mixed> 传输适配器允许的选项字段。
     */
    private function transportOptions(array $options): array
    {
        $source = is_array($options['transport_options'] ?? null)
            ? $options['transport_options']
            : $options;
        $allowed = ['timeout', 'connect_timeout', 'verify_peer', 'allow_insecure_tls'];
        $result = [];
        foreach ($allowed as $name) {
            if (array_key_exists($name, $source)) {
                $result[$name] = $source[$name];
            }
        }
        return $result;
    }

    /**
     * 解析安全网关传输重试次数，限制总尝试次数避免请求风暴。
     *
     * @param array<string,mixed> $options 请求级重试选项。
     * @return int 包含首次请求在内的总尝试次数。
     */
    private function resolveRetryAttempts(array $options): int
    {
        $value = $options['retry_attempts'] ?? $this->config('plugin.gateway.retry_attempts', 2);
        if (!is_int($value) && !is_numeric($value)) {
            return 2;
        }
        return max(1, min(3, (int)$value));
    }

    /**
     * 只对可恢复的传输失败退避重试，不重试安全协议和业务校验错误。
     *
     * @param PluginSecurityException $exception 本次网关请求异常。
     * @return bool 是否允许使用新信封再次发送。
     */
    private function isRetryableFailure(PluginSecurityException $exception): bool
    {
        $message = $exception->getMessage();
        if (str_starts_with($message, '安全网关传输失败')
            || $message === '安全网关响应为空'
            || str_starts_with($message, '官网密钥目录请求失败')
        ) {
            return true;
        }
        return preg_match('/^安全网关返回(?:状态无效|业务错误)（HTTP (408|425|429|500|502|503|504)）/u', $message) === 1
            || preg_match('/^官网密钥目录返回状态无效（HTTP (408|425|429|500|502|503|504)）$/u', $message) === 1;
    }

    /**
     * 按指数退避等待下一轮请求，等待时间可由调用方覆盖并受上限保护。
     *
     * @param int $attempt 已失败的尝试序号，从 1 开始。
     * @param array<string,mixed> $options 请求级退避选项。
     * @return void 无返回值。
     */
    private function waitBeforeRetry(int $attempt, array $options): void
    {
        $value = $options['retry_delay_ms'] ?? $this->config('plugin.gateway.retry_delay_ms', 120);
        if (!is_int($value) && !is_numeric($value)) {
            $value = 120;
        }
        $delayMs = max(0, min(1000, (int)$value));
        if ($delayMs === 0) {
            return;
        }
        usleep($delayMs * (2 ** max(0, $attempt - 1)) * 1000);
    }

    /**
     * 按注入、配置和实例身份兜底解析当前客户端标识。
     *
     * @param array<string,mixed> $options 可选客户端标识字段。
     * @return string 当前本地实例的客户端标识。
     */
    private function resolveClientId(array $options): string
    {
        $clientId = trim((string)($options['client_id'] ?? $this->clientId ?? ''));
        if ($clientId === '') {
            $clientId = trim((string)$this->config('plugin.security.instance_id', ''));
        }
        if ($clientId === '') {
            $clientId = trim((string)(getenv('PLUGIN_INSTANCE_ID') ?: ''));
        }
        if ($clientId === '') {
            $clientId = $this->keys->instanceId();
        }
        return $clientId;
    }

    /**
     * 按注入、配置和默认值解析官网密钥所有者标识。
     *
     * @param array<string,mixed> $options 可选官网所有者字段。
     * @return string 用于限定官网密钥归属的所有者标识。
     */
    private function resolveGatewayOwnerId(array $options): string
    {
        $ownerId = trim((string)($options['gateway_owner_id'] ?? $this->gatewayOwnerId ?? ''));
        if ($ownerId === '') {
            $ownerId = trim((string)$this->config('plugin.gateway.owner_id', ''));
        }
        if ($ownerId === '') {
            $ownerId = trim((string)(getenv('PLUGIN_GATEWAY_OWNER_ID') ?: ''));
        }
        return $ownerId !== '' ? $ownerId : 'plugin-gateway';
    }

    /**
     * 安全读取宿主配置，宿主不可用或读取失败时使用默认值。
     *
     * @param string $key 宿主配置键。
     * @param mixed $default 配置缺失或不可用时的默认值。
     * @return mixed 宿主配置值或默认值。
     */
    private function config(string $key, mixed $default): mixed
    {
        if (function_exists('config')) {
            try {
                return config($key, $default);
            } catch (Throwable) {
                return $default;
            }
        }
        return $default;
    }

    /**
     * 验证公私钥材料类型和非空性，不泄露实际密钥内容。
     *
     * @param mixed $key PEM 文本或 OpenSSL 密钥对象。
     * @param bool $private 是否按私钥提示格式错误。
     * @return void 无返回值；材料类型非法或为空时抛出异常。
     */
    private function assertKeyMaterial(mixed $key, bool $private): void
    {
        if (!is_string($key) && !$key instanceof OpenSSLAsymmetricKey) {
            throw new PluginSecurityException($private ? '私钥材料无效' : '公钥材料无效');
        }
        if (is_string($key) && trim($key) === '') {
            throw new PluginSecurityException($private ? '私钥材料为空' : '公钥材料为空');
        }
    }
}
