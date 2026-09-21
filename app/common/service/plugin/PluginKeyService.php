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
use app\common\service\plugin\contract\PluginKeyCipherInterface;
use app\common\service\plugin\contract\PluginSecurityKeyRepositoryInterface;
use app\common\plugin\security\PluginRequestEnvelope;
use OpenSSLAsymmetricKey;
use Throwable;

/**
 * 本地插件实例密钥、官网公钥和 nonce 防重放服务。
 *
 * 私钥只以密文落盘；`getPrivateKey()` 是安全网关内部专用方法，普通元数据
 * 查询永远不会返回 `private_key_ciphertext` 或解密后的私钥。
 */
class PluginKeyService
{
    public const OWNER_INSTANCE = 'instance';
    public const OWNER_GATEWAY = 'gateway';

    public const KEY_USE_SIGNING = 'signing';
    public const KEY_USE_ENCRYPTION = 'encryption';

    public const ALGORITHM_ES256 = 'ES256';
    public const ALGORITHM_RSA_OAEP_256 = 'RSA-OAEP-256';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_GRACE = 'grace';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_EXPIRED = 'expired';

    private PluginSecurityKeyRepositoryInterface $repository;
    private PluginKeyCipherInterface $cipher;

    public function __construct(
        ?PluginSecurityKeyRepositoryInterface $repository = null,
        ?PluginKeyCipherInterface $cipher = null
    ) {
        $this->repository = $repository ?? new DatabasePluginSecurityKeyRepository();
        $this->cipher = $cipher ?? new AppPluginKeyCipher();
    }

    /**
     * 生成并登记本地实例密钥对。
     *
     * @param string $keyUse signing 使用 P-256，encryption 使用 RSA-2048。
     * @param string|null $ownerId 默认使用当前实例 ID。
     * @param int|null $expiresAt Unix 时间戳；null 表示不设置过期时间。
     * @return array<string,mixed> 只含公钥和元数据。
     */
    public function generateLocalKeyPair(
        string $keyUse,
        ?string $ownerId = null,
        ?int $expiresAt = null
    ): array {
        $keyUse = $this->assertKeyUse($keyUse);
        $ownerId = $this->assertOwnerId($ownerId ?? $this->instanceId());
        [$algorithm, $privatePem, $publicPem] = $this->generateKeyMaterial($keyUse);
        $keyId = $this->newKeyId();
        $now = date('Y-m-d H:i:s');
        $ciphertext = $this->cipher->encrypt($privatePem);
        if ($ciphertext === '' || hash_equals($ciphertext, $privatePem)) {
            throw new PluginSecurityException('私钥未被有效加密');
        }
        $row = $this->repository->createKey([
            'key_id' => $keyId,
            'owner_type' => self::OWNER_INSTANCE,
            'owner_id' => $ownerId,
            'key_use' => $keyUse,
            'algorithm' => $algorithm,
            'public_key_pem' => $publicPem,
            'private_key_ciphertext' => $ciphertext,
            'wrapping_key_id' => '',
            'status' => self::STATUS_ACTIVE,
            'not_before' => $now,
            'expires_at' => $expiresAt !== null ? date('Y-m-d H:i:s', $expiresAt) : null,
            'revoked_at' => null,
            'create_time' => $now,
            'update_time' => $now,
            // 内存仓储用于有效期筛选，数据库模型会忽略未知字段或由适配器裁剪。
            'not_before_unix' => time(),
            'expires_at_unix' => $expiresAt ?? 0,
        ]);
        return $this->publicMetadata($row);
    }

    /**
     * 登记官网或其他受信端的公钥；此方法禁止提交私钥字段。
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function registerPublicKey(array $data): array
    {
        if (array_key_exists('private_key', $data)
            || array_key_exists('private_key_ciphertext', $data)
        ) {
            throw new PluginSecurityException('公钥登记请求不得包含私钥');
        }
        $keyUse = $this->assertKeyUse((string)($data['key_use'] ?? ''));
        $algorithm = $keyUse === self::KEY_USE_SIGNING
            ? self::ALGORITHM_ES256
            : self::ALGORITHM_RSA_OAEP_256;
        $publicPem = trim((string)($data['public_key_pem'] ?? ''));
        $this->assertPublicKey($publicPem, $algorithm);
        $ownerType = trim((string)($data['owner_type'] ?? self::OWNER_GATEWAY));
        if (!in_array($ownerType, [self::OWNER_INSTANCE, self::OWNER_GATEWAY], true)) {
            throw new PluginSecurityException('密钥所有者类型无效');
        }
        $ownerId = $this->assertOwnerId((string)($data['owner_id'] ?? ''));
        $keyId = $this->assertKeyId((string)($data['key_id'] ?? $this->newKeyId()));
        $now = date('Y-m-d H:i:s');
        $row = $this->repository->createKey([
            'key_id' => $keyId,
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'key_use' => $keyUse,
            'algorithm' => $algorithm,
            'public_key_pem' => $publicPem,
            'private_key_ciphertext' => null,
            'wrapping_key_id' => '',
            'status' => self::STATUS_ACTIVE,
            'not_before' => $data['not_before'] ?? $now,
            'expires_at' => $data['expires_at'] ?? null,
            'revoked_at' => null,
            'create_time' => $now,
            'update_time' => $now,
            'not_before_unix' => $this->unixTime($data['not_before'] ?? null),
            'expires_at_unix' => $this->unixTime($data['expires_at'] ?? null),
        ]);
        return $this->publicMetadata($row);
    }

    /**
     * 获取指定密钥的公钥元数据。
     *
     * @return array<string,mixed>
     */
    public function getPublicKey(string $keyId): array
    {
        $row = $this->findUsableKey($keyId, false);
        return $this->publicMetadata($row);
    }

    /**
     * 获取当前有效公钥。
     *
     * @return array<string,mixed>|null
     */
    public function getActivePublicKey(
        string $ownerType,
        string $ownerId,
        string $keyUse
    ): ?array {
        $keyUse = $this->assertKeyUse($keyUse);
        $row = $this->repository->findActiveKey(
            trim($ownerType),
            $this->assertOwnerId($ownerId),
            $keyUse
        );
        return $row !== null ? $this->publicMetadata($row) : null;
    }

    /**
     * 确保指定用途的本地密钥可用；不存在时自动生成。
     *
     * 新环境首次访问不会提前跑初始化脚本，因此这里补一层懒初始化。
     *
     * @param string $keyUse signing 或 encryption
     * @param string|null $ownerId 默认使用当前实例 ID
     * @param int|null $expiresAt 过期时间戳；为空表示不设过期时间
     * @return array<string,mixed>
     */
    public function ensureLocalKeyPair(
        string $keyUse,
        ?string $ownerId = null,
        ?int $expiresAt = null
    ): array {
        $keyUse = $this->assertKeyUse($keyUse);
        $ownerId = $this->assertOwnerId($ownerId ?? $this->instanceId());
        $metadata = $this->getActivePublicKey(self::OWNER_INSTANCE, $ownerId, $keyUse);
        if ($metadata !== null) {
            return $metadata;
        }

        // 旧环境第一次访问时直接补一把密钥，避免接口还要求手工初始化。
        return $this->generateLocalKeyPair($keyUse, $ownerId, $expiresAt);
    }

    /**
     * 解密读取本实例私钥，仅供安全网关内部调用。
     */
    public function getPrivateKey(
        string $keyId,
        ?string $expectedKeyUse = null,
        ?string $ownerId = null
    ): string {
        $row = $this->findUsableKey($keyId, true);
        if (($row['owner_type'] ?? '') !== self::OWNER_INSTANCE
            || ($ownerId !== null && (string)$row['owner_id'] !== $ownerId)
        ) {
            throw new PluginSecurityException('私钥所有者不匹配');
        }
        if ($expectedKeyUse !== null && (string)$row['key_use'] !== $this->assertKeyUse($expectedKeyUse)) {
            throw new PluginSecurityException('私钥用途不匹配');
        }
        $ciphertext = (string)($row['private_key_ciphertext'] ?? '');
        if ($ciphertext === '') {
            throw new PluginSecurityException('密钥没有本地私钥');
        }
        $privatePem = $this->cipher->decrypt($ciphertext);
        if ($privatePem === '' || !str_contains($privatePem, 'PRIVATE KEY')) {
            throw new PluginSecurityException('本地私钥解密结果无效');
        }
        return $privatePem;
    }

    /**
     * 获取当前本地有效私钥及其 kid，供安全网关内部编排使用。
     *
     * 返回值包含私钥原文，仅允许在同一安全进程内继续传递；调用方不得把
     * 返回值写入日志、响应、任务载荷或普通业务表。
     *
     * @return array{key_id:string,private_key:string,metadata:array<string,mixed>}
     */
    public function getActivePrivateKey(
        string $keyUse,
        ?string $ownerId = null
    ): array {
        $keyUse = $this->assertKeyUse($keyUse);
        $ownerId = $this->assertOwnerId($ownerId ?? $this->instanceId());
        $metadata = $this->getActivePublicKey(self::OWNER_INSTANCE, $ownerId, $keyUse);
        if ($metadata === null || (string)($metadata['key_id'] ?? '') === '') {
            throw new PluginSecurityException('本地有效密钥不存在');
        }
        $keyId = (string)$metadata['key_id'];
        return [
            'key_id' => $keyId,
            'private_key' => $this->getPrivateKey($keyId, $keyUse, $ownerId),
            'metadata' => $metadata,
        ];
    }

    /**
     * 轮换实例密钥。旧密钥进入宽限状态，不会立即删除。
     *
     * @return array<string,mixed> 新密钥公钥元数据
     */
    public function rotateKey(
        string $keyUse,
        ?string $ownerId = null,
        int $graceSeconds = 86400,
        ?int $expiresAt = null
    ): array {
        $keyUse = $this->assertKeyUse($keyUse);
        $ownerId = $this->assertOwnerId($ownerId ?? $this->instanceId());
        $old = $this->repository->findActiveKey(self::OWNER_INSTANCE, $ownerId, $keyUse);
        $new = $this->generateLocalKeyPair($keyUse, $ownerId, $expiresAt);
        if ($old !== null && ($old['key_id'] ?? '') !== '') {
            $graceEnd = time() + max(0, min($graceSeconds, 7 * 86400));
            $this->repository->updateKey((string)$old['key_id'], [
                'status' => self::STATUS_GRACE,
                'expires_at' => date('Y-m-d H:i:s', $graceEnd),
                'update_time' => date('Y-m-d H:i:s'),
                'expires_at_unix' => $graceEnd,
            ]);
        }
        return $new;
    }

    /**
     * 吊销单个密钥。
     */
    public function revokeKey(string $keyId): array
    {
        $keyId = $this->assertKeyId($keyId);
        $row = $this->repository->findKey($keyId);
        if ($row === null) {
            throw new PluginSecurityException('插件密钥不存在');
        }
        return $this->publicMetadata($this->repository->updateKey($keyId, [
            'status' => self::STATUS_REVOKED,
            'revoked_at' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s'),
        ]));
    }

    /**
     * 消费 nonce；同一 client_id + nonce 在窗口内只能成功一次。
     *
     * @param string|PluginRequestEnvelope $clientOrEnvelope
     * @param string|null $nonce
     * @param string|null $requestId
     * @param int $ttlSeconds
     */
    public function consumeNonce(
        string|PluginRequestEnvelope $clientOrEnvelope,
        ?string $nonce = null,
        ?string $requestId = null,
        int $ttlSeconds = 300
    ): bool {
        if ($clientOrEnvelope instanceof PluginRequestEnvelope) {
            $clientId = $clientOrEnvelope->getClientId();
            $nonce = $clientOrEnvelope->getNonce();
            $requestId = $clientOrEnvelope->getRequestId();
        } else {
            $clientId = trim($clientOrEnvelope);
        }
        if (preg_match('/^[A-Za-z0-9._:-]{1,64}$/', $clientId) !== 1
            || !is_string($nonce)
            || preg_match('/^[A-Za-z0-9_-]{22,172}$/', $nonce) !== 1
            || !is_string($requestId)
            || $requestId === ''
        ) {
            throw new PluginSecurityException('nonce 请求参数无效');
        }
        $ttlSeconds = max(1, min($ttlSeconds, 3600));
        return $this->repository->insertNonce(
            $clientId,
            hash('sha256', $nonce),
            $requestId,
            date('Y-m-d H:i:s', time() + $ttlSeconds)
        );
    }

    public function instanceId(): string
    {
        $configured = '';
        if (function_exists('config')) {
            try {
                $configured = (string)config('plugin.security.instance_id', '');
            } catch (Throwable) {
                $configured = '';
            }
        }
        $configured = trim($configured !== '' ? $configured : (string)(getenv('PLUGIN_INSTANCE_ID') ?: ''));
        return $this->assertOwnerId($configured !== '' ? $configured : 'local-instance');
    }

    /** @return array{0:string,1:string,2:string} */
    private function generateKeyMaterial(string $keyUse): array
    {
        $options = $keyUse === self::KEY_USE_SIGNING
            ? ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']
            : ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $key = openssl_pkey_new($options);
        if (!$key instanceof OpenSSLAsymmetricKey) {
            throw new PluginSecurityException('本地密钥生成失败');
        }
        $privatePem = '';
        if (!openssl_pkey_export($key, $privatePem)) {
            throw new PluginSecurityException('本地私钥导出失败');
        }
        $details = openssl_pkey_get_details($key);
        $publicPem = is_array($details) ? (string)($details['key'] ?? '') : '';
        if ($publicPem === '') {
            throw new PluginSecurityException('本地公钥导出失败');
        }
        return [
            $keyUse === self::KEY_USE_SIGNING ? self::ALGORITHM_ES256 : self::ALGORITHM_RSA_OAEP_256,
            $privatePem,
            $publicPem,
        ];
    }

    private function assertPublicKey(string $pem, string $algorithm): void
    {
        $key = $algorithm === self::ALGORITHM_ES256
            ? openssl_pkey_get_public($pem)
            : openssl_pkey_get_public($pem);
        if (!$key instanceof OpenSSLAsymmetricKey) {
            throw new PluginSecurityException('公钥材料无效');
        }
        $details = openssl_pkey_get_details($key);
        if (!is_array($details)) {
            throw new PluginSecurityException('公钥材料无效');
        }
        if ($algorithm === self::ALGORITHM_ES256
            && ((int)($details['type'] ?? 0) !== OPENSSL_KEYTYPE_EC
                || (string)($details['ec']['curve_name'] ?? '') !== 'prime256v1')
        ) {
            throw new PluginSecurityException('公钥算法不匹配');
        }
        if ($algorithm === self::ALGORITHM_RSA_OAEP_256
            && (int)($details['type'] ?? 0) !== OPENSSL_KEYTYPE_RSA
        ) {
            throw new PluginSecurityException('公钥算法不匹配');
        }
    }

    /** @return array<string,mixed> */
    private function findUsableKey(string $keyId, bool $private): array
    {
        $keyId = $this->assertKeyId($keyId);
        $row = $this->repository->findKey($keyId);
        if ($row === null) {
            throw new PluginSecurityException('插件密钥不存在');
        }
        $status = (string)($row['status'] ?? '');
        if (!in_array($status, [self::STATUS_ACTIVE, self::STATUS_GRACE], true)) {
            throw new PluginSecurityException('插件密钥已吊销或过期');
        }
        $now = time();
        $notBefore = $this->unixTime($row['not_before_unix'] ?? $row['not_before'] ?? null);
        $expiresAt = $this->unixTime($row['expires_at_unix'] ?? $row['expires_at'] ?? null);
        if (($notBefore > 0 && $notBefore > $now) || ($expiresAt > 0 && $expiresAt <= $now)) {
            throw new PluginSecurityException('插件密钥不在有效期');
        }
        if ($private && ($row['owner_type'] ?? '') !== self::OWNER_INSTANCE) {
            throw new PluginSecurityException('官网公钥不允许读取私钥');
        }
        return $row;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function publicMetadata(array $row): array
    {
        unset(
            $row['client_id'],
            $row['logical_method'],
            $row['logical_path'],
            $row['private_key_ciphertext'],
            $row['secret_ciphertext'],
            $row['delete_time'],
            $row['not_before_unix'],
            $row['expires_at_unix']
        );
        return $row;
    }

    private function assertKeyUse(string $keyUse): string
    {
        $keyUse = strtolower(trim($keyUse));
        if (!in_array($keyUse, [self::KEY_USE_SIGNING, self::KEY_USE_ENCRYPTION], true)) {
            throw new PluginSecurityException('密钥用途无效');
        }
        return $keyUse;
    }

    private function assertOwnerId(string $ownerId): string
    {
        $ownerId = trim($ownerId);
        if (preg_match('/^[A-Za-z0-9._:-]{1,64}$/', $ownerId) !== 1) {
            throw new PluginSecurityException('密钥所有者标识无效');
        }
        return $ownerId;
    }

    private function assertKeyId(string $keyId): string
    {
        $keyId = trim($keyId);
        if (preg_match('/^[A-Za-z0-9._:-]{1,64}$/', $keyId) !== 1) {
            throw new PluginSecurityException('密钥 ID 无效');
        }
        return $keyId;
    }

    private function newKeyId(): string
    {
        return 'pk_' . bin2hex(random_bytes(16));
    }

    private function unixTime(mixed $value): int
    {
        if (is_int($value)) {
            return max(0, $value);
        }
        if (is_string($value) && $value !== '') {
            $parsed = strtotime($value);
            return $parsed === false ? 0 : max(0, $parsed);
        }
        return 0;
    }
}
