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

namespace Rabbit\service\websocket\handlers;

use app\common\enum\ClientTypeEnum;
use app\common\exception\AdminResponseException;
use app\common\service\setting\AdminService;
use Rabbit\service\websocket\ConnectionManager;
use Rabbit\service\websocket\PushNotification;
use Rabbit\service\websocket\TokenHelper;
use think\facade\Log;

/**
 * 系统广播处理器
 * 用于全服广播或指定客户端类型广播
 */
class SystemBroadcastHandler implements MessageHandlerInterface
{
    /**
     * @inheritDoc
     */
    public function handle(string|int $fd, array $data): void
    {
        $connection = ConnectionManager::getConnection($fd);
        if (!$connection) {
            throw new AdminResponseException('连接不存在');
        }

        // 验证权限：仅平台端可发送系统广播
        if ($connection['client_type'] !== ClientTypeEnum::PLATFORM) {
            throw new AdminResponseException('权限不足：仅平台管理员可发送系统广播');
        }

        if (!TokenHelper::hasScope($connection, 'system_broadcast')) {
            throw new AdminResponseException('权限不足：当前账号无权发送系统广播');
        }

        // 通过 AdminService 验证管理员状态
        $adminId = $connection['user_id'];
        $adminService = app()->make(AdminService::class);
        $adminInfo = $adminService->get(['id' => $adminId], ['id', 'status']);
        if (!$adminInfo || !$adminInfo['status']) {
            throw new AdminResponseException('管理员不存在或已禁用');
        }

        // TODO: 这里可以添加更细粒度的权限验证，如角色权限组

        $content = trim($data['data']['content'] ?? '');
        if (empty($content)) {
            throw new AdminResponseException('广播内容不能为空');
        }

        $targetClientType = (int)($data['data']['client_type'] ?? 0);

        Log::info("[系统广播] admin={$adminId}, content={$content}, target_client_type=" . ($targetClientType ?: 'all'));

        // 执行广播
        PushNotification::broadcastAll($content, $targetClientType > 0 ? $targetClientType : null);
    }

    /**
     * @inheritDoc
     */
    public static function getEvents(): array
    {
        return ['system_broadcast'];
    }
}
