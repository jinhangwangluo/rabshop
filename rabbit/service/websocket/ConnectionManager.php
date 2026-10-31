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
use think\facade\Cache;
use think\facade\Log;

/**
 * WebSocket 连接管理器（Redis 存储版）
 *
 * 使用 Redis 存储连接信息，支持分布式部署
 *
 * ### Redis 存储（推荐）✅
 * - 承载能力：取决于 Redis 服务器配置
 * - 典型值：50-100 万连接（单 Redis 实例）
 * - 优势：
 *   - 支持多机分布式部署
 *   - 数据持久化，重启不丢失
 *   - 多个 Swoole worker 共享同一份数据
 *   - 便于监控和运维
 *
 * ## Redis Key 设计
 *
 * ```
 * ws_conn:fd:{fd}         -> Hash    - fd 连接信息
 * ws_conn:user:{user_id}  -> Set     - 用户所有连接 fd
 * ws_conn:room:{room_id}  -> Set     - 直播间所有连接 fd
 * ws_conn:client:{type}   -> Set     - 客户端类型所有连接 fd
 * ws_conn:cs_session:{session_id} -> Set - 客服会话所有连接 fd
 * ws_conn:stats           -> String  - 统计信息
 * ```
 *
 * @package Rabbit\service\websocket
 */
class ConnectionManager
{
    /**
     * Redis Key 前缀
     */
    const REDIS_PREFIX = 'ws_conn:';

    /**
     * Redis 键过期时间（秒）
     * 自动清理超过 24 小时不活跃的连接
     */
    const KEY_EXPIRE = 86400; // 24 小时

    /**
     * 心跳超时阈值（秒）
     * 超过此时间未活跃的连接视为断开，将被主动清理
     */
    const HEARTBEAT_TIMEOUT = 90; // 3x 30s 心跳间隔

    /**
     * 本地缓存（用于热点数据加速）
     * 仅缓存当前 worker 进程的连接，避免跨进程访问
     */
    private static array $localCache = [];

    /**
     * 获取 Redis 实例
     *
     * Swoole 协程下不能跨协程复用同一个原生 Redis socket。
     * 这里每次从 think-swoole cache pool 获取当前协程的 handler，
     * 由框架在协程结束时归还连接。
     *
     * @return object
     */
    private static function getRedis(): object
    {
        return Cache::store('redis')->handler();
    }

    /**
     * 生成 Redis Key
     */
    private static function makeKey(string $type, string|int $identifier): string
    {
        return self::REDIS_PREFIX . $type . ':' . $identifier;
    }

    /**
     * 添加连接
     * @param string|int $fd
     * @param array $info [user_id, client_type, room_id, merchant_id, admin_id, token, ip, scopes]
     */
    public static function addConnection(string|int $fd, array $info): void
    {
        $userId = (int)$info['user_id'];
        $clientType = (int)$info['client_type'];
        $roomId = (int)($info['room_id'] ?? 0);

        $connectionData = [
            'user_id'      => $userId,
            'client_type'  => $clientType,
            'room_id'      => $roomId,
            'merchant_id'  => (int)($info['merchant_id'] ?? 0),
            'admin_id'     => (int)($info['admin_id'] ?? 0),
            'token'        => $info['token'] ?? '',
            'scopes'       => json_encode((array)($info['scopes'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'ip'           => $info['ip'] ?? '',
            'connect_time' => time(),
            'last_active'  => time()
        ];

        $redis = self::getRedis();

        // 1. 存储 fd 详细信息（Hash 结构）
        $fdKey = self::makeKey('fd', $fd);
        $redis->hMSet($fdKey, $connectionData);
        $redis->expire($fdKey, self::KEY_EXPIRE);

        // 2. 用户 -> fd 映射（Set 结构）
        $userKey = self::makeKey('user', $userId);
        $redis->sAdd($userKey, $fd);
        $redis->expire($userKey, self::KEY_EXPIRE);

        // 3. 客户端类型 -> fd 映射（Set 结构）
        $clientKey = self::makeKey('client', $clientType);
        $redis->sAdd($clientKey, $fd);
        $redis->expire($clientKey, self::KEY_EXPIRE);

        // 4. 直播间 -> fd 映射（仅当 client_type=4 时）
        if ($clientType === ClientTypeEnum::LIVE_ROOM && $roomId > 0) {
            $roomKey = self::makeKey('room', $roomId);
            $redis->sAdd($roomKey, $fd);
            $redis->expire($roomKey, self::KEY_EXPIRE);
        }

        // 5. 本地缓存（加速当前 worker 访问）
        self::$localCache[$fd] = $connectionData;

        // 6. 更新统计信息
        self::incrementStats();
    }

    /**
     * 添加连接到客服会话
     * @param string|int $fd
     * @param string $sessionId 会话ID
     */
    public static function addConnectionToCsSession(string|int $fd, string $sessionId): void
    {
        $redis = self::getRedis();
        $sessionKey = self::makeKey('cs_session', $sessionId);
        $redis->sAdd($sessionKey, $fd);
        $redis->expire($sessionKey, self::KEY_EXPIRE);
    }

    /**
     * 从客服会话中移除连接
     * @param string|int $fd
     * @param string $sessionId 会话ID
     */
    public static function removeConnectionFromCsSession(string|int $fd, string $sessionId): void
    {
        $redis = self::getRedis();
        $sessionKey = self::makeKey('cs_session', $sessionId);
        $redis->sRem($sessionKey, $fd);
    }

    /**
     * 获取连接信息
     * @param string|int $fd
     * @return array|null
     */
    public static function getConnection(string|int $fd): ?array
    {
        // 先查本地缓存
        if (isset(self::$localCache[$fd])) {
            return self::$localCache[$fd];
        }

        // 再查 Redis
        $redis = self::getRedis();
        $fdKey = self::makeKey('fd', $fd);
        $data = $redis->hGetAll($fdKey);

        if (empty($data)) {
            unset(self::$localCache[$fd]);
            return null;
        }

        // 类型转换
        $connection = [
            'user_id'      => (int)$data['user_id'],
            'client_type'  => (int)$data['client_type'],
            'room_id'      => (int)($data['room_id'] ?? 0),
            'merchant_id'  => (int)($data['merchant_id'] ?? 0),
            'admin_id'     => (int)($data['admin_id'] ?? 0),
            'token'        => $data['token'] ?? '',
            'scopes'       => self::decodeScopes($data['scopes'] ?? '[]'),
            'ip'           => $data['ip'] ?? '',
            'connect_time' => (int)$data['connect_time'],
            'last_active'  => (int)$data['last_active']
        ];

        // 写入本地缓存
        self::$localCache[$fd] = $connection;

        return $connection;
    }

    /**
     * 删除连接
     * @param string|int $fd
     */
    public static function removeConnection(string|int $fd): void
    {
        $redis = self::getRedis();
        $fdKey = self::makeKey('fd', $fd);

        // 获取连接信息
        $data = $redis->hGetAll($fdKey);
        if (empty($data)) {
            unset(self::$localCache[$fd]);
            return;
        }

        $userId = (int)$data['user_id'];
        $clientType = (int)$data['client_type'];
        $roomId = (int)($data['room_id'] ?? 0);

        // 1. 删除 fd 详细信息
        $redis->del($fdKey);

        // 2. 从用户连接集合中删除
        if ($userId > 0) {
            $userKey = self::makeKey('user', $userId);
            $redis->sRem($userKey, $fd);
        }

        // 3. 从客户端类型集合中删除
        $clientKey = self::makeKey('client', $clientType);
        $redis->sRem($clientKey, $fd);

        // 4. 从直播间集合中删除（如果有）
        if ($clientType === ClientTypeEnum::LIVE_ROOM && $roomId > 0) {
            $roomKey = self::makeKey('room', $roomId);
            $redis->sRem($roomKey, $fd);
        }

        // 5. 清除本地缓存
        unset(self::$localCache[$fd]);

        // 6. 更新统计信息
        self::decrementStats();
    }

    /**
     * 解析连接中保存的权限范围。
     *
     * @param mixed $value Redis 中的 JSON 字符串
     * @return array<string>
     */
    private static function decodeScopes(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('strval', $value)));
        }

        $scopes = json_decode((string)$value, true);

        return is_array($scopes)
            ? array_values(array_filter(array_map('strval', $scopes)))
            : [];
    }

    /**
     * 更新连接最后活跃时间
     * @param string|int $fd
     */
    public static function updateLastActiveTime(string|int $fd): void
    {
        $redis = self::getRedis();
        $fdKey = self::makeKey('fd', $fd);

        if ($redis->exists($fdKey)) {
            $redis->hSet($fdKey, 'last_active', time());
            $redis->expire($fdKey, self::KEY_EXPIRE); // 续期

            // 更新本地缓存
            if (isset(self::$localCache[$fd])) {
                self::$localCache[$fd]['last_active'] = time();
            }
        }
    }

    /**
     * 获取用户的所有连接 fd
     * @param int $userId
     * @return array
     */
    public static function getUserFds(int $userId): array
    {
        $redis = self::getRedis();
        $userKey = self::makeKey('user', $userId);
        $fds = $redis->sMembers($userKey);

        return array_values($fds ?: []);
    }

    /**
     * 获取直播间的所有连接 fd
     * @param int $roomId
     * @return array
     */
    public static function getRoomFds(int $roomId): array
    {
        $redis = self::getRedis();
        $roomKey = self::makeKey('room', $roomId);
        $fds = $redis->sMembers($roomKey);

        return array_values($fds ?: []);
    }

    /**
     * 获取客户端类型的所有连接 fd
     * @param int $clientType
     * @return array
     */
    public static function getClientTypeFds(int $clientType): array
    {
        $redis = self::getRedis();
        $clientKey = self::makeKey('client', $clientType);
        $fds = $redis->sMembers($clientKey);

        return array_values($fds ?: []);
    }

    /**
     * 批量获取直播间用户信息
     * @param int $roomId
     * @return array
     */
    public static function getRoomUsers(int $roomId): array
    {
        $fds = self::getRoomFds($roomId);
        if (empty($fds)) {
            return [];
        }

        $users = [];
        foreach ($fds as $fd) {
            $info = self::getConnection($fd);
            if ($info) {
                $users[] = $info;
            }
        }

        return $users;
    }

    /**
     * 获取客服会话的所有连接 fd
     * @param string $sessionId 会话ID
     * @return array
     */
    public static function getCsSessionFds(string $sessionId): array
    {
        $redis = self::getRedis();
        $sessionKey = self::makeKey('cs_session', $sessionId);
        $fds = $redis->sMembers($sessionKey);

        return array_values($fds ?: []);
    }

    /**
     * 增加统计计数
     */
    private static function incrementStats(): void
    {
        $redis = self::getRedis();
        $statsKey = self::makeKey('stats', 'total');
        $redis->incr($statsKey);
        $redis->expire($statsKey, 86400 * 7); // 7 天过期
    }

    /**
     * 减少统计计数
     */
    private static function decrementStats(): void
    {
        $redis = self::getRedis();
        $statsKey = self::makeKey('stats', 'total');
        $redis->decr($statsKey);
    }

    /**
     * 获取统计信息
     * @return array
     */
    public static function getStats(): array
    {
        $redis = self::getRedis();

        $totalKey = self::makeKey('stats', 'total');
        $total = (int)$redis->get($totalKey);

        // 计算各端连接数
        $platformCount = count(self::getClientTypeFds(ClientTypeEnum::PLATFORM));
        $merchantCount = count(self::getClientTypeFds(ClientTypeEnum::MERCHANT));
        $userCount = count(self::getClientTypeFds(ClientTypeEnum::USER));
        $liveRoomCount = count(self::getClientTypeFds(ClientTypeEnum::LIVE_ROOM));

        return [
            'total'          => $total,
            'by_client_type' => [
                'platform'  => $platformCount,
                'merchant'  => $merchantCount,
                'user'      => $userCount,
                'live_room' => $liveRoomCount,
            ],
            'storage_type'   => 'redis',
            'key_prefix'     => self::REDIS_PREFIX,
        ];
    }

    /**
     * 获取所有在线 fd 列表
     * @return array
     */
    public static function getAllFds(): array
    {
        $fds = [];
        foreach ([
            ClientTypeEnum::PLATFORM,
            ClientTypeEnum::MERCHANT,
            ClientTypeEnum::USER,
            ClientTypeEnum::LIVE_ROOM,
        ] as $clientType) {
            $typeFds = self::getClientTypeFds($clientType);
            foreach ($typeFds as $fd) {
                $fds[] = $fd;
            }
        }
        return array_unique($fds);
    }

    /**
     * 清理心跳超时的连接（定时任务调用，建议每 30 秒执行一次）
     * @param int $timeoutSeconds 超时阈值（秒），默认使用 HEARTBEAT_TIMEOUT
     * @return array 被清理的连接信息列表，每项包含 [fd, user_id, client_type, room_id]
     */
    public static function cleanupExpiredConnections(?int $timeoutSeconds = null): array
    {
        $timeout = $timeoutSeconds ?? self::HEARTBEAT_TIMEOUT;
        $redis = self::getRedis();
        $removed = [];
        $now = time();

        // 使用 SCAN 安全遍历所有 fd key
        $cursor = null;
        $pattern = self::REDIS_PREFIX . 'fd:*';

        do {
            $result = $redis->scan($cursor, ['match' => $pattern, 'count' => 100]);
            if ($result === false) {
                break;
            }

            $cursor = $result[0] ?? null;
            $keys = $result[1] ?? [];

            foreach ($keys as $key) {
                $data = $redis->hGetAll($key);
                if (empty($data)) {
                    continue;
                }

                $lastActive = (int)($data['last_active'] ?? 0);
                if ($lastActive > 0 && ($now - $lastActive) < $timeout) {
                    continue;
                }

                // 连接超时，提取 fd 并清理
                $fd = str_replace(self::REDIS_PREFIX . 'fd:', '', $key);
                $removed[] = [
                    'fd'          => $fd,
                    'user_id'     => (int)($data['user_id'] ?? 0),
                    'client_type' => (int)($data['client_type'] ?? 0),
                    'room_id'     => (int)($data['room_id'] ?? 0),
                ];

                // 从各索引集合中移除
                $userId = (int)($data['user_id'] ?? 0);
                $clientType = (int)($data['client_type'] ?? 0);
                $roomId = (int)($data['room_id'] ?? 0);

                if ($userId > 0) {
                    $redis->sRem(self::makeKey('user', $userId), $fd);
                }
                $redis->sRem(self::makeKey('client', $clientType), $fd);
                if ($clientType === \app\common\enum\ClientTypeEnum::LIVE_ROOM && $roomId > 0) {
                    $redis->sRem(self::makeKey('room', $roomId), $fd);
                }

                $redis->del($key);
                unset(self::$localCache[$fd]);
            }
        } while ($cursor !== '0' && $cursor !== 0 && $cursor !== null);

        if (!empty($removed)) {
            Log::info('[WebSocket] 清理超时连接完成，共 ' . count($removed) . ' 个');
        }

        return $removed;
    }

    /**
     * 清空所有连接（谨慎使用）
     */
    public static function clearAll(): void
    {
        $redis = self::getRedis();
        $keys = $redis->keys(self::REDIS_PREFIX . '*');

        if (!empty($keys)) {
            $redis->del($keys);
        }

        self::$localCache = [];
    }
}
