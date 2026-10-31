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
 * 移动端用户关注控制器.
 */
class UserFollow extends BaseController
{
    /**
     * 用户收藏/关注服务.
     *
     * @var UserFavoriteService
     */
    #[Inject]
    protected UserFavoriteService $service;

    /**
     * 获取用户关注列表。
     *
     * GET 参数：
     * - type string 关注类型：all/shop/live。
     * - limit int 每类返回条数，默认 20。
     *
     * @return Response 返回关注平台、店铺和主播列表。
     */
    public function index(): Response
    {
        $userId = $this->getLoginUserId();
        $type = trim((string)$this->request->get('type', 'all'));
        $limit = (int)$this->request->get('limit/d', 20);

        return $this->success('获取成功', $this->service->getMobileFollowList($userId, $type, $limit));
    }

    /**
     * 取消用户关注。
     *
     * POST 参数：
     * - type string 关注类型：shop/live/platform。
     * - target_id int 关注对象 ID，平台固定为 0。
     *
     * @return Response 返回取消后的关注状态。
     */
    public function cancel(): Response
    {
        $userId = $this->getLoginUserId();
        $type = trim((string)$this->request->post('type', $this->request->post('target_type', '')));
        $targetId = (int)$this->request->post('target_id/d', 0);

        if ($type === '') {
            return $this->fail('关注类型不能为空');
        }
        if ($type !== 'platform' && $targetId <= 0) {
            return $this->fail('关注对象ID不能为空');
        }

        if ($type === 'platform') {
            $targetId = 0;
        }

        return $this->success('取消成功', $this->service->cancelMobileFollow($userId, $type, $targetId));
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
