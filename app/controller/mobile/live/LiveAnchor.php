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

namespace app\controller\mobile\live;

use app\BaseController;
use app\common\service\user\UserFavoriteService;
use think\annotation\Inject;
use think\Response;

/**
 * 移动端主播控制器.
 */
class LiveAnchor extends BaseController
{
    /**
     * 用户收藏/关注服务.
     *
     * @var UserFavoriteService
     */
    #[Inject]
    protected UserFavoriteService $favoriteService;

    /**
     * 切换当前用户对主播的关注状态。
     *
     * POST 参数：
     * - anchor_id int 主播 ID。
     *
     * @return Response 返回关注状态。
     */
    public function toggleFollow(): Response
    {
        $anchorId = (int)$this->request->post('anchor_id/d', 0);
        if ($anchorId <= 0) {
            return $this->fail('主播ID不能为空');
        }

        $userId = (int)($this->request->user_id ?? 0);
        $data = $this->favoriteService->toggleMobileLiveAnchorFollow($userId, $anchorId);

        return $this->success('操作成功', $data);
    }
}
