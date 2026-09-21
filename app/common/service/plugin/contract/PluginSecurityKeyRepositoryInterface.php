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

namespace app\common\service\plugin\contract;

/**
 * 插件安全密钥和 nonce 持久化端口。
 */
interface PluginSecurityKeyRepositoryInterface
{
    /** @return array<string,mixed>|null */
    public function findKey(string $keyId): ?array;

    /** @return array<string,mixed>|null */
    public function findActiveKey(string $ownerType, string $ownerId, string $keyUse): ?array;

    /** @return array<string,mixed> */
    public function createKey(array $data): array;

    /** @return array<string,mixed> */
    public function updateKey(string $keyId, array $data): array;

    /** @return bool true 表示新 nonce，false 表示重复 */
    public function insertNonce(string $clientId, string $nonceHash, string $requestId, string $expiresAt): bool;
}
