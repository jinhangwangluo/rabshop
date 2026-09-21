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
use app\common\model\plugin\PluginRequestNonce;
use app\common\model\plugin\PluginSecurityKey;
use app\common\service\plugin\contract\PluginSecurityKeyRepositoryInterface;
use think\facade\Db;
use Throwable;

/**
 * plugin_security_key/plugin_request_nonce 模型适配器。
 */
final class DatabasePluginSecurityKeyRepository implements PluginSecurityKeyRepositoryInterface
{
    private ?bool $modernKeySchema = null;

    private ?bool $modernNonceSchema = null;

    public function findKey(string $keyId): ?array
    {
        try {
            if ($this->hasModernKeySchema()) {
                $row = app()->make(PluginSecurityKey::class)->where('key_id', $keyId)->find();
                return $row ? $row->toArray() : null;
            }
            $row = Db::name('plugin_security_key')->where('key_id', $keyId)->find();
        } catch (Throwable $exception) {
            throw new PluginSecurityException('插件密钥存储不可用', 0, $exception);
        }
        return is_array($row) ? $this->inflateLegacyKeyRow($row) : null;
    }

    public function findActiveKey(string $ownerType, string $ownerId, string $keyUse): ?array
    {
        try {
            if ($this->hasModernKeySchema()) {
                $query = app()->make(PluginSecurityKey::class)
                    ->where('owner_type', $ownerType)
                    ->where('owner_id', $ownerId)
                    ->where('key_use', $keyUse)
                    ->where('status', PluginKeyService::STATUS_ACTIVE)
                    ->where(function ($subQuery): void {
                        $now = date('Y-m-d H:i:s');
                        $subQuery->whereNull('not_before')->whereOr('not_before', '<=', $now);
                    })
                    ->where(function ($subQuery): void {
                        $now = date('Y-m-d H:i:s');
                        $subQuery->whereNull('expires_at')->whereOr('expires_at', '>', $now);
                    })
                    ->order('id', 'desc');
                $row = $query->find();
                return $row ? $row->toArray() : null;
            }

            $rows = Db::name('plugin_security_key')
                ->where('status', 1)
                ->order('id', 'desc')
                ->select()
                ->toArray();
        } catch (Throwable $exception) {
            throw new PluginSecurityException('插件密钥存储不可用', 0, $exception);
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $row = $this->inflateLegacyKeyRow($row);
            if (($row['owner_type'] ?? '') !== $ownerType
                || ($row['owner_id'] ?? '') !== $ownerId
                || ($row['key_use'] ?? '') !== $keyUse
            ) {
                continue;
            }
            $status = (string)($row['status'] ?? '');
            if (!in_array($status, [PluginKeyService::STATUS_ACTIVE, PluginKeyService::STATUS_GRACE], true)) {
                continue;
            }
            $now = time();
            $notBefore = $this->unixTime($row['not_before_unix'] ?? $row['not_before'] ?? null);
            $expiresAt = $this->unixTime($row['expires_at_unix'] ?? $row['expires_at'] ?? null);
            if (($notBefore > 0 && $notBefore > $now) || ($expiresAt > 0 && $expiresAt <= $now)) {
                continue;
            }
            return $row;
        }

        return null;
    }

    public function createKey(array $data): array
    {
        try {
            // `not_before_unix`/`expires_at_unix` 只供内存替身筛选，不能写入规划表。
            unset($data['not_before_unix'], $data['expires_at_unix']);
            if ($this->hasModernKeySchema()) {
                $row = app()->make(PluginSecurityKey::class)->create($data);
                return $row->toArray();
            }

            $payload = $this->packLegacyKeyPayload($data);
            $record = [
                'client_id' => $this->legacyClientId((string)($payload['owner_id'] ?? '')),
                'key_id' => (string)($payload['key_id'] ?? ''),
                'logical_method' => (string)($payload['key_use'] ?? ''),
                'logical_path' => (string)($payload['owner_type'] ?? '') . ':' . (string)($payload['owner_id'] ?? ''),
                'secret_ciphertext' => $this->encodeLegacyPayload($payload),
                'status' => $this->legacyStatusValue((string)($payload['status'] ?? PluginKeyService::STATUS_ACTIVE)),
                'create_time' => (string)($payload['create_time'] ?? date('Y-m-d H:i:s')),
                'update_time' => (string)($payload['update_time'] ?? date('Y-m-d H:i:s')),
                'delete_time' => null,
            ];
            Db::name('plugin_security_key')->insert($record);
            $row = Db::name('plugin_security_key')->where('key_id', (string)$payload['key_id'])->find();
        } catch (Throwable $exception) {
            throw new PluginSecurityException('插件密钥保存失败', 0, $exception);
        }
        return is_array($row) ? $this->inflateLegacyKeyRow($row) : $data;
    }

    public function updateKey(string $keyId, array $data): array
    {
        try {
            unset($data['not_before_unix'], $data['expires_at_unix']);
            if ($this->hasModernKeySchema()) {
                $row = app()->make(PluginSecurityKey::class)->where('key_id', $keyId)->find();
                if (!$row) {
                    throw new PluginSecurityException('插件密钥不存在');
                }
                $row->save($data);
                return $row->toArray();
            }

            $row = Db::name('plugin_security_key')->where('key_id', $keyId)->find();
            if (!$row) {
                throw new PluginSecurityException('插件密钥不存在');
            }
            $payload = $this->packLegacyKeyPayload(array_merge($this->inflateLegacyKeyRow($row), $data));
            Db::name('plugin_security_key')
                ->where('key_id', $keyId)
                ->update([
                    'client_id' => $this->legacyClientId((string)($payload['owner_id'] ?? '')),
                    'logical_method' => (string)($payload['key_use'] ?? ''),
                    'logical_path' => (string)($payload['owner_type'] ?? '') . ':' . (string)($payload['owner_id'] ?? ''),
                    'secret_ciphertext' => $this->encodeLegacyPayload($payload),
                    'status' => $this->legacyStatusValue((string)($payload['status'] ?? PluginKeyService::STATUS_ACTIVE)),
                    'update_time' => (string)($payload['update_time'] ?? date('Y-m-d H:i:s')),
                ]);
            $row = Db::name('plugin_security_key')->where('key_id', $keyId)->find();
        } catch (PluginSecurityException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new PluginSecurityException('插件密钥更新失败', 0, $exception);
        }
        return is_array($row) ? $this->inflateLegacyKeyRow($row) : $data;
    }

    public function insertNonce(string $clientId, string $nonceHash, string $requestId, string $expiresAt): bool
    {
        try {
            if ($this->hasModernNonceSchema()) {
                app()->make(PluginRequestNonce::class)->create([
                    'client_id' => $clientId,
                    'nonce_hash' => $nonceHash,
                    'request_id' => $requestId,
                    'expires_at' => $expiresAt,
                    'create_time' => date('Y-m-d H:i:s'),
                ]);
                return true;
            }

            // 旧表只支持整数 client_id，这里用稳定哈希做兼容映射，避免全部落到同一个租户上。
            Db::name('plugin_request_nonce')->insert([
                'client_id' => $this->legacyClientId($clientId),
                'nonce_hash' => $nonceHash,
                'request_id' => $requestId,
                'status' => 1,
                'expire_at' => $expiresAt,
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
            ]);
            return true;
        } catch (Throwable $exception) {
            // 唯一索引冲突表示 nonce 已消费；其他数据库异常不能当作“新 nonce”。
            $message = strtolower($exception->getMessage());
            if (str_contains($message, 'duplicate') || str_contains($message, 'unique')) {
                return false;
            }
            throw new PluginSecurityException('nonce 存储不可用', 0, $exception);
        }
    }

    /**
     * 判断插件密钥表是否已经升级到 v1.2 新结构。
     *
     * 旧表没有 owner_type/public_key_pem 等字段，继续按新字段查询会直接触发 SQL 错误。
     */
    private function hasModernKeySchema(): bool
    {
        if ($this->modernKeySchema !== null) {
            return $this->modernKeySchema;
        }
        try {
            $fields = Db::name('plugin_security_key')->getTableFields();
            $this->modernKeySchema = in_array('owner_type', $fields, true)
                && in_array('public_key_pem', $fields, true)
                && in_array('expires_at', $fields, true);
        } catch (Throwable) {
            $this->modernKeySchema = false;
        }
        return $this->modernKeySchema;
    }

    /**
     * 判断插件 nonce 表是否已经升级到 v1.2 新结构。
     */
    private function hasModernNonceSchema(): bool
    {
        if ($this->modernNonceSchema !== null) {
            return $this->modernNonceSchema;
        }
        try {
            $fields = Db::name('plugin_request_nonce')->getTableFields();
            $this->modernNonceSchema = in_array('expires_at', $fields, true);
        } catch (Throwable) {
            $this->modernNonceSchema = false;
        }
        return $this->modernNonceSchema;
    }

    /**
     * 把 legacy 表中 `secret_ciphertext` 里的兼容 JSON 还原成统一字段。
     *
     * 这样上层服务可以继续只认一套键名，不需要知道当前库是旧表还是新表。
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function inflateLegacyKeyRow(array $row): array
    {
        $payload = $this->decodeLegacyPayload((string)($row['secret_ciphertext'] ?? ''));
        if ($payload !== []) {
            $row = array_merge($row, $payload);
        }
        return $row;
    }

    /**
     * legacy 表把新结构塞进 `secret_ciphertext` 保存。
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function packLegacyKeyPayload(array $data): array
    {
        return [
            'key_id' => (string)($data['key_id'] ?? ''),
            'owner_type' => (string)($data['owner_type'] ?? PluginKeyService::OWNER_INSTANCE),
            'owner_id' => (string)($data['owner_id'] ?? ''),
            'key_use' => (string)($data['key_use'] ?? PluginKeyService::KEY_USE_ENCRYPTION),
            'algorithm' => (string)($data['algorithm'] ?? PluginKeyService::ALGORITHM_RSA_OAEP_256),
            'public_key_pem' => (string)($data['public_key_pem'] ?? ''),
            'private_key_ciphertext' => $data['private_key_ciphertext'] ?? null,
            'wrapping_key_id' => (string)($data['wrapping_key_id'] ?? ''),
            'status' => (string)($data['status'] ?? PluginKeyService::STATUS_ACTIVE),
            'not_before' => $data['not_before'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'revoked_at' => $data['revoked_at'] ?? null,
            'create_time' => $data['create_time'] ?? null,
            'update_time' => $data['update_time'] ?? null,
        ];
    }

    /**
     * 兼容旧表的 JSON 编码。
     *
     * @param array<string,mixed> $payload
     */
    private function encodeLegacyPayload(array $payload): string
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded) || $encoded === '') {
            throw new PluginSecurityException('插件密钥保存失败');
        }
        return $encoded;
    }

    /**
     * legacy 旧表里的整数 client_id 用稳定哈希映射，避免不同租户互相覆盖。
     */
    private function legacyClientId(string $clientId): int
    {
        $hash = crc32($clientId);
        return max(1, (int)($hash & 0x7fffffff));
    }

    /**
     * legacy 旧表的状态字段只支持整数，active/grace 统一记为 1。
     */
    private function legacyStatusValue(string $status): int
    {
        return in_array($status, [PluginKeyService::STATUS_ACTIVE, PluginKeyService::STATUS_GRACE], true) ? 1 : 0;
    }

    /**
     * @param string $ciphertext
     * @return array<string,mixed>
     */
    private function decodeLegacyPayload(string $ciphertext): array
    {
        $ciphertext = trim($ciphertext);
        if ($ciphertext === '') {
            return [];
        }
        $decoded = json_decode($ciphertext, true);
        return is_array($decoded) ? $decoded : [];
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
