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

namespace Rabbit\service\websocket;

use app\common\enum\ClientTypeEnum;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use think\facade\Cache;

/**
 * WebSocket Token 生成助手
 * 用于生成客户端连接所需的认证 token
 */
class TokenHelper
{
    /** WebSocket Token 身份版本缓存前缀。 */
    private const AUTH_VERSION_PREFIX = 'websocket_auth_version:';

    /** 身份版本至少覆盖默认 WebSocket Token 的完整生命周期。 */
    private const AUTH_VERSION_TTL = 172800;

    /** WebSocket Token 固定发行者。 */
    private const ISSUER = 'knowledge-payment';

    /** WebSocket Token 固定受众。 */
    private const AUDIENCE = 'knowledge-payment-websocket';

    /**
     * 获取 WebSocket Token 签名密钥。
     */
    private static function signingKey(): string
    {
        $key = (string)config('app.app_key', '');
        if ($key === '') {
            throw new \RuntimeException('未配置应用签名密钥');
        }

        return $key;
    }

    /**
     * 生成 WebSocket 连接 token
     * @param int $userId 用户 ID
     * @param int $clientType 客户端类型（1=平台端，2=商户端，3=用户端，4=直播间）
     * @param int $roomId 直播间ID（仅 client_type=4 时需要）
     * @param int $expire 过期时间（秒），默认 24 小时
     * @param array $claims 附加身份声明
     * @return string HS256 JWT token
     */
    public static function generateToken(
        int $userId,
        int $clientType = ClientTypeEnum::USER,
        int $roomId = 0,
        int $expire = 86400,
        array $claims = []
    ): string {
        // 验证客户端类型
        if (!ClientTypeEnum::isValid($clientType)) {
            throw new \InvalidArgumentException('无效的客户端类型');
        }

        // 直播间类型必须有 room_id
        if ($clientType === ClientTypeEnum::LIVE_ROOM && $roomId <= 0) {
            throw new \InvalidArgumentException('直播间类型必须提供 room_id');
        }

        $now = time();

        // 保留字段覆盖调用方声明，避免附加参数改变认证身份或生命周期。
        $scopes = is_array($claims['scopes'] ?? null)
            ? self::normalizeScopes($claims['scopes'])
            : [];
        $tokenData = array_merge($claims, [
            'iss'          => self::ISSUER,
            'aud'          => self::AUDIENCE,
            'user_id'      => $userId,
            'client_type'  => $clientType,
            'room_id'      => $roomId > 0 ? $roomId : null,
            'exp'          => $now + $expire,
            'iat'          => $now,
            'nbf'          => $now,
        ]);
        $tokenData['scopes'] = $scopes;

        $tokenData['auth_version'] = self::getAuthVersion($clientType, $tokenData);
        if ($clientType === ClientTypeEnum::MERCHANT) {
            $tokenData['merchant_auth_version'] = self::getMerchantAuthVersion((int)$tokenData['merchant_id']);
        }

        return JWT::encode($tokenData, self::signingKey(), 'HS256');
    }

    /**
     * 解析 token
     * @param string $token
     * @return array|null ['user_id', 'client_type', 'room_id', 'exp', 'iat']
     */
    public static function parseToken(string $token): ?array
    {
        try {
            if (trim($token) === '') {
                return null;
            }

            $data = (array)JWT::decode($token, new Key(self::signingKey(), 'HS256'));

            // 认证 Token 必须包含固定发行者、受众、生命周期和身份字段，缺失字段不能放行。
            if (
                ($data['iss'] ?? null) !== self::ISSUER
                || ($data['aud'] ?? null) !== self::AUDIENCE
                || !isset($data['user_id'], $data['client_type'], $data['exp'], $data['iat'], $data['nbf'])
                || !is_numeric($data['user_id'])
                || (int)$data['user_id'] <= 0
                || !is_numeric($data['client_type'])
                || !ClientTypeEnum::isValid((int)$data['client_type'])
                || !is_numeric($data['exp'])
                || (int)$data['exp'] <= time()
                || !is_numeric($data['iat'])
                || !is_numeric($data['nbf'])
                || !is_array($data['scopes'] ?? [])
            ) {
                return null;
            }

            if ((int)$data['iat'] > time() + 60) {
                return null;
            }

            if ((int)$data['client_type'] === ClientTypeEnum::LIVE_ROOM && (int)($data['room_id'] ?? 0) <= 0) {
                return null;
            }

            if (self::isRevoked($data)) {
                return null;
            }

            return $data;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * 生成平台端 token
     * @param int $adminId 管理员 ID
     * @return string
     */
    public static function generatePlatformToken(int $adminId): string
    {
        return self::generateToken($adminId, ClientTypeEnum::PLATFORM);
    }

    /**
     * 生成带权限范围的平台端 token。
     *
     * @param int $adminId 管理员 ID
     * @param array<string> $scopes WebSocket 入站操作权限
     * @return string
     */
    public static function generatePlatformTokenWithScopes(int $adminId, array $scopes): string
    {
        return self::generateToken($adminId, ClientTypeEnum::PLATFORM, 0, 86400, [
            'scopes' => self::normalizeScopes($scopes),
        ]);
    }

    /**
     * 撤销平台管理员关联的全部 WebSocket token。
     *
     * @param int $adminId 管理员 ID
     * @return void
     */
    public static function revokePlatformToken(int $adminId): void
    {
        if ($adminId > 0) {
            self::bumpAuthVersion(self::platformVersionKey($adminId));
        }
    }

    /**
     * 撤销指定商户管理员关联的 WebSocket token。
     *
     * @param int $merchantId 商户 ID
     * @param int $adminId 商户管理员 ID
     * @return void
     */
    public static function revokeMerchantToken(int $merchantId, int $adminId): void
    {
        if ($merchantId > 0 && $adminId > 0) {
            self::bumpAuthVersion(self::merchantAdminVersionKey($merchantId, $adminId));
        }
    }

    /**
     * 撤销商户下所有 WebSocket token。
     *
     * @param int $merchantId 商户 ID
     * @return void
     */
    public static function revokeMerchantTokens(int $merchantId): void
    {
        if ($merchantId > 0) {
            self::bumpAuthVersion(self::merchantVersionKey($merchantId));
        }
    }

    /**
     * 撤销用户端及直播间 WebSocket token。
     *
     * @param int $userId 用户 ID
     * @return void
     */
    public static function revokeUserToken(int $userId): void
    {
        if ($userId > 0) {
            self::bumpAuthVersion(self::userVersionKey($userId));
        }
    }

    /**
     * 判断 WebSocket token 是否具备指定入站操作权限。
     *
     * @param array $claims 已验签的 Token 声明或连接信息
     * @param string $scope 权限范围
     * @return bool
     */
    public static function hasScope(array $claims, string $scope): bool
    {
        $scopes = self::normalizeScopes(is_array($claims['scopes'] ?? null) ? $claims['scopes'] : []);

        return in_array('*', $scopes, true)
            || in_array($scope, $scopes, true)
            || in_array(strtok($scope, ':') . ':*', $scopes, true);
    }

    /**
     * 获取身份版本缓存键。
     */
    private static function getAuthVersion(int $clientType, array $claims): int
    {
        if ($clientType === ClientTypeEnum::PLATFORM) {
            return self::readAuthVersion(self::platformVersionKey((int)$claims['user_id']));
        }

        if ($clientType === ClientTypeEnum::MERCHANT) {
            return self::readAuthVersion(self::merchantAdminVersionKey(
                (int)($claims['merchant_id'] ?? 0),
                (int)($claims['admin_id'] ?? 0)
            ));
        }

        return self::readAuthVersion(self::userVersionKey((int)$claims['user_id']));
    }

    /**
     * 获取商户级身份版本。
     */
    private static function getMerchantAuthVersion(int $merchantId): int
    {
        return self::readAuthVersion(self::merchantVersionKey($merchantId));
    }

    /**
     * 校验 token 的身份版本是否仍然有效。
     */
    private static function isRevoked(array $claims): bool
    {
        $clientType = (int)$claims['client_type'];
        $authVersion = (int)($claims['auth_version'] ?? 0);
        if ($authVersion < self::getAuthVersion($clientType, $claims)) {
            return true;
        }

        return $clientType === ClientTypeEnum::MERCHANT
            && (int)($claims['merchant_auth_version'] ?? 0) < self::getMerchantAuthVersion((int)($claims['merchant_id'] ?? 0));
    }

    /**
     * 递增身份版本。
     *
     * Redis 路径直接使用 INCR，并只为首次创建的键设置过期时间，避免
     * 并发登出时后完成的 set 将较新的版本覆盖回旧值。
     */
    private static function bumpAuthVersion(string $key): int
    {
        if (Cache::getDefaultDriver() === 'redis') {
            $store = Cache::store('redis');
            $redis = $store->handler();
            $cacheKey = self::redisCacheKey($key);
            $version = $redis->incr($cacheKey);
            if (!is_numeric($version)) {
                throw new \RuntimeException('无法更新 WebSocket 身份版本');
            }

            if ((int)$version === 1) {
                $redis->expire($cacheKey, self::AUTH_VERSION_TTL);
            }

            return (int)$version;
        }

        // 文件/内存驱动仅用于本地测试和非分布式开发环境。
        $version = Cache::inc($key, 1);
        if (!is_numeric($version)) {
            throw new \RuntimeException('无法更新 WebSocket 身份版本');
        }
        if ((int)$version === 1) {
            Cache::set($key, (int)$version, self::AUTH_VERSION_TTL);
        }

        return (int)$version;
    }

    /**
     * 获取与 ThinkPHP Redis 驱动一致的原始缓存键。
     */
    private static function redisCacheKey(string $key): string
    {
        // Predis 驱动会在驱动初始化后清空 prefix，保持与其 getCacheKey 行为一致。
        $prefix = extension_loaded('redis')
            ? (string)config('cache.stores.redis.prefix', '')
            : '';

        return $prefix . $key;
    }

    /**
     * 读取身份版本。
     */
    private static function readAuthVersion(string $key): int
    {
        return max(0, (int)Cache::get($key, 0));
    }

    private static function platformVersionKey(int $adminId): string
    {
        return self::AUTH_VERSION_PREFIX . 'platform:' . $adminId;
    }

    private static function merchantAdminVersionKey(int $merchantId, int $adminId): string
    {
        return self::AUTH_VERSION_PREFIX . 'merchant:' . $merchantId . ':admin:' . $adminId;
    }

    private static function merchantVersionKey(int $merchantId): string
    {
        return self::AUTH_VERSION_PREFIX . 'merchant:' . $merchantId . ':all';
    }

    private static function userVersionKey(int $userId): string
    {
        return self::AUTH_VERSION_PREFIX . 'user:' . $userId;
    }

    /**
     * 过滤权限范围，避免将任意结构写入 Token。
     */
    private static function normalizeScopes(array $scopes): array
    {
        $scopes = array_map(static fn($scope): string => trim((string)$scope), $scopes);

        return array_values(array_unique(array_filter($scopes, static fn(string $scope): bool => $scope !== '')));
    }

    /**
     * 生成商户端 token
     * @param int $merchantId 商户 ID
     * @param int $adminId 当前商户管理员 ID
     * @return string
     */
    public static function generateMerchantToken(int $merchantId, int $adminId = 0): string
    {
        return self::generateToken(
            $merchantId,
            ClientTypeEnum::MERCHANT,
            0,
            86400,
            [
                'merchant_id' => $merchantId,
                'admin_id' => $adminId,
            ]
        );
    }

    /**
     * 生成用户端 token
     * @param int $userId 用户 ID
     * @return string
     */
    public static function generateUserToken(int $userId): string
    {
        return self::generateToken($userId, ClientTypeEnum::USER);
    }

    /**
     * 生成直播间 token
     * @param int $userId 用户 ID
     * @param int $roomId 直播间 ID
     * @return string
     */
    public static function generateLiveRoomToken(int $userId, int $roomId): string
    {
        return self::generateToken($userId, ClientTypeEnum::LIVE_ROOM, $roomId);
    }
}
