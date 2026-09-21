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

namespace app\controller\mobile\user;

use app\BaseController;
use app\common\service\user\UserFavoriteService;
use think\annotation\Inject;
use think\Response;

/**
 * 移动端用户收藏控制器.
 */
class UserFavorite extends BaseController
{
    /**
     * 用户收藏服务.
     *
     * @var UserFavoriteService
     */
    #[Inject]
    protected UserFavoriteService $service;

    /**
     * 获取用户收藏列表。
     *
     * GET 参数：
     * - type string 收藏对象类型；兼容 target_type。
     * - page int 页码，默认 1。
     * - limit int 每页数量，默认 10。
     *
     * @return Response 返回收藏分页列表。
     */
    public function index(): Response
    {
        $userId = $this->getLoginUserId();
        $type = trim((string)$this->request->get('type', $this->request->get('target_type', UserFavoriteService::TARGET_TYPE_CONTENT)));
        $page = (int)$this->request->get('page/d', 1);
        $limit = (int)$this->request->get('limit/d', 10);

        return $this->success('获取成功', $this->service->getMobileFavoriteList($userId, $type, $page, $limit));
    }

    /**
     * 取消用户收藏。
     *
     * POST 参数：
     * - type string 收藏对象类型；兼容 target_type。
     * - target_id int 收藏对象 ID。
     *
     * @return Response 返回取消结果。
     */
    public function cancel(): Response
    {
        $userId = $this->getLoginUserId();
        $type = trim((string)$this->request->post('type', $this->request->post('target_type', '')));
        $targetId = (int)$this->request->post('target_id/d', 0);

        return $this->success('取消成功', $this->service->cancelMobileFavorite($userId, $type, $targetId));
    }

    /**
     * 获取当前登录用户ID。
     *
     * @return int 当前登录用户 ID。
     */
    protected function getLoginUserId(): int
    {
        return (int)($this->request->user_id ?? 0);
    }
}
