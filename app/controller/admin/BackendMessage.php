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

namespace app\controller\admin;

use app\common\service\message\BackendMessageService;
use app\common\service\setting\AdminService;
use app\common\service\websocket\WebSocketUrlService;
use Rabbit\service\websocket\TokenHelper;
use think\annotation\Inject;
use think\Response;

/**
 * 平台端后台消息控制器。
 */
class BackendMessage extends AuthController
{
    /**
     * 后台消息服务。
     */
    #[Inject]
    protected BackendMessageService $service;

    /**
     * 获取消息类型和统计。
     *
     * @return Response 返回消息分组、未读数和总数。
     */
    public function types(): Response
    {
        return $this->success('获取成功', [
            'list' => $this->service->getTypes(BackendMessageService::RECEIVER_PLATFORM, 0),
        ]);
    }

    /**
     * 获取消息列表。
     *
     * @return Response 返回分页消息列表。
     */
    public function list(): Response
    {
        $params = $this->request->getMore([
            ['page', 1],
            ['limit', 10],
            ['message_group', ''],
            ['read_status', ''],
        ]);

        return $this->success('获取成功', $this->service->getList(
            $params,
            BackendMessageService::RECEIVER_PLATFORM,
            0
        ));
    }

    /**
     * 获取未读数量。
     *
     * @return Response 返回未读消息数量。
     */
    public function unreadCount(): Response
    {
        return $this->success('获取成功', [
            'count' => $this->service->getUnreadCount(BackendMessageService::RECEIVER_PLATFORM, 0),
        ]);
    }

    /**
     * 标记消息已读。
     *
     * @return Response 返回更新数量。
     */
    public function read(): Response
    {
        $ids = $this->request->param('ids', []);
        if (!is_array($ids)) {
            $ids = [$ids];
        }

        return $this->success('标记成功', [
            'updated' => $this->service->markRead($ids, BackendMessageService::RECEIVER_PLATFORM, 0),
        ]);
    }

    /**
     * 全部标为已读。
     *
     * @return Response 返回更新数量。
     */
    public function readAll(): Response
    {
        $messageGroup = trim((string)$this->request->param('message_group', ''));

        return $this->success('标记成功', [
            'updated' => $this->service->markAllRead(BackendMessageService::RECEIVER_PLATFORM, 0, $messageGroup),
        ]);
    }

    /**
     * 一键软清理消息。
     *
     * @return Response 返回清理数量。
     */
    public function clear(): Response
    {
        $messageGroup = trim((string)$this->request->param('message_group', ''));

        return $this->success('清理成功', [
            'updated' => $this->service->clear(BackendMessageService::RECEIVER_PLATFORM, 0, $messageGroup),
        ]);
    }

    /**
     * 获取后台消息 WebSocket 连接令牌。
     *
     * @return Response 返回 token 和 WebSocket 地址。
     */
    public function wsToken(): Response
    {
        return $this->success('获取成功', [
            'token' => TokenHelper::generatePlatformTokenWithScopes(
                (int)$this->adminId,
                app()->make(AdminService::class)->getWebSocketScopes((array)$this->adminInfo)
            ),
            'ws_url' => app()->make(WebSocketUrlService::class)->getClientUrl($this->request),
        ]);
    }
}
