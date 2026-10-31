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

use app\common\service\plugin\contract\PluginSecurityKeyRepositoryInterface;

/**
 * 安全契约测试用内存密钥仓储。
 */
final class InMemoryPluginSecurityKeyRepository implements PluginSecurityKeyRepositoryInterface
{
    /** @var array<string,array<string,mixed>> */
    private array $keys = [];

    /** @var array<string,array<string,mixed>> */
    private array $nonces = [];

    private int $nextId = 1;

    public function findKey(string $keyId): ?array
    {
        return $this->keys[$keyId] ?? null;
    }

    public function findActiveKey(string $ownerType, string $ownerId, string $keyUse): ?array
    {
        $now = time();
        $matches = array_filter($this->keys, static function (array $key) use ($ownerType, $ownerId, $keyUse, $now): bool {
            if (($key['owner_type'] ?? '') !== $ownerType
                || ($key['owner_id'] ?? '') !== $ownerId
                || ($key['key_use'] ?? '') !== $keyUse
                || ($key['status'] ?? '') !== PluginKeyService::STATUS_ACTIVE
            ) {
                return false;
            }
            $notBefore = (int)($key['not_before_unix'] ?? 0);
            $expiresAt = (int)($key['expires_at_unix'] ?? 0);
            return ($notBefore === 0 || $notBefore <= $now) && ($expiresAt === 0 || $expiresAt > $now);
        });
        usort($matches, static fn (array $left, array $right): int => ((int)($right['id'] ?? 0)) <=> ((int)($left['id'] ?? 0)));
        return $matches[0] ?? null;
    }

    public function createKey(array $data): array
    {
        $data['id'] = $this->nextId++;
        $this->keys[(string)$data['key_id']] = $data;
        return $data;
    }

    public function updateKey(string $keyId, array $data): array
    {
        $this->keys[$keyId] = array_merge($this->keys[$keyId] ?? [], $data);
        return $this->keys[$keyId];
    }

    public function insertNonce(string $clientId, string $nonceHash, string $requestId, string $expiresAt): bool
    {
        $key = $clientId . ':' . $nonceHash;
        $now = time();
        foreach ($this->nonces as $nonceKey => $record) {
            $expires = strtotime((string)($record['expires_at'] ?? ''));
            if ($expires !== false && $expires <= $now) {
                unset($this->nonces[$nonceKey]);
            }
        }
        if (isset($this->nonces[$key])) {
            return false;
        }
        $this->nonces[$key] = [
            'client_id' => $clientId,
            'nonce_hash' => $nonceHash,
            'request_id' => $requestId,
            'expires_at' => $expiresAt,
        ];
        return true;
    }

    /** @return array<string,array<string,mixed>> */
    public function keys(): array
    {
        return $this->keys;
    }
}
