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

namespace app\controller\mobile\cs;

use app\common\service\cs\CsSessionService;
use app\BaseController;
use Rabbit\service\websocket\TokenHelper;
use think\annotation\Inject;
use think\Response;

/**
 * 用户端客服会话控制器.
 */
class CsSession extends BaseController
{
    /**
     * 客服会话服务.
     *
     * @var CsSessionService
     */
    #[Inject]
    protected CsSessionService $service;

    /**
     * 发起或获取客服会话.
     *
     * POST 参数：
     * - merchant_id int 商户 ID，默认 0。
     *
     * @return Response 返回会话信息。
     */
    public function create(): Response
    {
        $userId = $this->request->user_id;
        if ($userId <= 0) {
            return $this->fail('请先登录');
        }

        $merchantId = max(0, (int)$this->request->post('merchant_id/d', 0));
        $session = $this->service->getOrCreateSession(
            $userId,
            $merchantId,
            (string)$this->request->ip(),
            (string)$this->request->header('user-agent', '')
        );

        return $this->success('获取成功', $session->toArray());
    }

    /**
     * 查询客服会话状态.
     *
     * GET 参数：
     * - session_id string 会话 ID。
     *
     * @return Response 返回会话详情和客服信息。
     */
    public function read(): Response
    {
        $userId = $this->request->user_id;
        if ($userId <= 0) {
            return $this->fail('请先登录');
        }

        $sessionId = (string)$this->request->get('session_id', '');
        if ($sessionId === '') {
            return $this->fail('会话ID不能为空');
        }

        $session = $this->service->getSessionBySessionId($sessionId, -1, ['staff']);
        if (!$session) {
            return $this->fail('会话不存在');
        }

        if ((int)$session->user_id !== $userId) {
            return $this->fail('无权访问该会话');
        }

        return $this->success('获取成功', $session->toArray());
    }

    /**
     * 获取 WebSocket 连接 token.
     *
     * @return Response 返回 token 和 user_id。
     */
    public function wsToken(): Response
    {
        $userId = $this->request->user_id;
        if ($userId <= 0) {
            return $this->fail('请先登录');
        }

        return $this->success('获取成功', [
            'token'   => TokenHelper::generateUserToken($userId),
            'user_id' => $userId,
        ]);
    }
}
