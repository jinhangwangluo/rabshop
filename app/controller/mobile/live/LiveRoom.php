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
use app\common\model\live\LiveRoom as LiveRoomModel;
use app\common\service\live\LiveCategoryService;
use app\common\service\live\LiveGiftRecordService;
use app\common\service\live\LiveGiftService;
use app\common\service\live\LiveGoodsService;
use app\common\service\live\LiveRoomService;
use app\common\service\user\UserRechargeService;
use app\common\service\websocket\WebSocketUrlService;
use Rabbit\service\websocket\TokenHelper;
use think\annotation\Inject;
use think\facade\Db;
use think\Response;

/**
 * 移动端直播间控制器.
 */
class LiveRoom extends BaseController
{
    /**
     * 直播间服务.
     *
     * @var LiveRoomService
     */
    #[Inject]
    protected LiveRoomService $service;

    /**
     * 直播分类服务.
     *
     * @var LiveCategoryService
     */
    #[Inject]
    protected LiveCategoryService $categoryService;

    /**
     * 直播商品服务.
     *
     * @var LiveGoodsService
     */
    #[Inject]
    protected LiveGoodsService $goodsService;

    /**
     * 直播礼物服务.
     *
     * @var LiveGiftService
     */
    #[Inject]
    protected LiveGiftService $giftService;

    /**
     * 直播礼物流水服务.
     *
     * @var LiveGiftRecordService
     */
    #[Inject]
    protected LiveGiftRecordService $giftRecordService;

    /**
     * 用户充值服务.
     *
     * @var UserRechargeService
     */
    #[Inject]
    protected UserRechargeService $userRechargeService;

    /**
     * 获取直播分类下拉选项.
     *
     * @return Response 返回启用状态的直播分类列表。
     */
    public function categoryOptions(): Response
    {
        $list = $this->categoryService->getOptions();
        return $this->success('获取成功', $list);
    }

    /**
     * 获取直播间列表.
     *
     * GET 参数：
     * - status int 直播间状态。
     * - keyword string 标题关键词。
     * - is_hot int 是否热门。
     * - category_id int 直播分类 ID。
     * - merchant_id int 商户 ID。
     *
     * @return Response 返回直播间分页列表。
     */
    public function index(): Response
    {
        $status = $this->request->get('status', '');
        $keyword = trim((string)$this->request->get('keyword', ''));
        $isHot = $this->request->get('is_hot', '');
        $categoryId = $this->request->get('category_id', '');
        $merchantId = $this->request->get('merchant_id', '');

        $params = [
            'status' => ($status === '' || !is_numeric((string)$status)) ? '' : (int)$status,
            'audit_status' => LiveRoomService::AUDIT_PASS,
            'title' => $keyword,
            'is_hot' => ($isHot === '' || !is_numeric((string)$isHot)) ? '' : (int)$isHot,
            'category_id' => ($categoryId === '' || !is_numeric((string)$categoryId)) ? '' : (int)$categoryId,
            'merchant_id' => ($merchantId === '' || !is_numeric((string)$merchantId)) ? '' : max(0, (int)$merchantId),
        ];

        $data = $this->service
            ->getResourceList(
                $params,
                [
                    'id', 'merchant_id', 'live_anchor_id', 'category_id', 'title', 'cover', 'banner', 'poster',
                    'description', 'notice', 'welcome_msg', 'tags', 'start_time',
                    'status', 'view_count', 'like_count', 'is_hot', 'sort', 'create_time',
                    'stream_type', 'external_play_url'
                ]
            )
            ->each(function ($item) {
                $anchor = is_array($item['anchor'] ?? null) ? $item['anchor'] : [];
                $category = is_array($item['category'] ?? null) ? $item['category'] : [];
                $item['anchor_name'] = (string)($anchor['name'] ?? '');
                $item['anchor_avatar'] = (string)($anchor['avatar'] ?? '');
                $item['category_name'] = (string)($category['name'] ?? '');
                $item['cover_image'] = (string)($item['cover'] ?: ($item['banner'] ?: $item['poster']));
                $item['tag_list'] = array_values(array_filter(array_map('trim', explode(',', (string)($item['tags'] ?? '')))));
                return $item;
            })
            ->toArray();

        $data = $this->service->appendMobileAnchorFollowStatus($data, (int)($this->request->user_id ?? 0));

        return $this->success('获取成功', $data);
    }

    /**
     * 获取直播间详情.
     *
     * 路由参数：
     * - id int 直播间 ID。
     *
     * @param int $id 直播间 ID。
     * @return Response 返回直播间详情和推流/播放信息。
     */
    public function detail(int $id): Response
    {
        if ($id <= 0) {
            return $this->fail('直播间 ID 不能为空');
        }

        $data = $this->service->getMobileRoomDetail($id, (int)($this->request->user_id ?? 0));

        return $this->success('获取成功', $data);
    }

    /**
     * 获取直播间观看用户列表.
     *
     * @param int $id 直播间 ID。
     * @return Response 返回观看用户分页列表。
     */
    public function users(int $id): Response
    {
        if ($id <= 0) {
            return $this->fail('直播间 ID 不能为空');
        }

        $page = max(1, (int)$this->request->get('page', 1));
        $limit = min(50, max(1, (int)$this->request->get('limit', 20)));

        return $this->success('获取成功', $this->service->getMobileRoomUsers($id, $page, $limit));
    }

    /**
     * 获取直播间商品列表.
     *
     * @param int $id 直播间 ID。
     * @return Response 返回直播间挂载商品列表。
     */
    public function goods(int $id): Response
    {
        if ($id <= 0) {
            return $this->fail('直播间 ID 不能为空');
        }

        return $this->success('获取成功', [
            'list' => $this->goodsService->getMobileRoomGoods($id),
        ]);
    }

    /**
     * 创建直播间商品立即购买临时项。
     *
     * POST 参数：
     * - live_goods_id int 直播间商品 ID。
     *
     * @param int $id 直播间 ID。
     * @return Response 返回立即购买购物车项和结算预览。
     */
    public function buyNowGoods(int $id): Response
    {
        if ($id <= 0) {
            return $this->fail('直播间 ID 不能为空');
        }

        $liveGoodsId = (int)$this->request->post('live_goods_id/d', 0);
        if ($liveGoodsId <= 0) {
            return $this->fail('请选择要购买的直播商品');
        }

        $data = $this->goodsService->buyNowMobileRoomGoods($id, (int)$this->request->user_id, $liveGoodsId);

        return $this->success('获取成功', $data);
    }

    /**
     * 获取可用礼物列表.
     *
     * @return Response 返回可用礼物列表。
     */
    public function gifts(): Response
    {
        return $this->success('获取成功', [
            'list' => $this->giftService->getMobileAvailableGifts(),
        ]);
    }

    /**
     * 获取直播金币快捷充值档位。
     *
     * 每个档位只在组合数据中保存金币数量；读取时按当前直播金币兑换比例实时计算
     * pay_amount，确保后台调整兑换比例后，直播间展示金额立即同步。
     *
     * @return Response 返回快捷档位列表和当前兑换比例。
     */
    public function coinRechargeOptions(): Response
    {
        return $this->success('获取成功', $this->userRechargeService->getLiveCoinRechargeOptions());
    }

    /**
     * 获取直播间评论列表.
     *
     * 路由参数：
     * - id int 直播间 ID。
     *
     * GET 参数：
     * - page int 页码，默认 1。
     * - limit int 每页数量，最大 50。
     *
     * @param int $id 直播间 ID。
     * @return Response 返回直播间评论分页列表。
     */
    public function comments(int $id): Response
    {
        if ($id <= 0) {
            return $this->fail('直播间 ID 不能为空');
        }

        $page  = max(1, (int)$this->request->get('page', 1));
        $limit = min(50, max(1, (int)$this->request->get('limit', 20)));
        $data  = $this->service->getMobileRoomComments($id, $page, $limit);

        return $this->success('获取成功', $data);
    }

    /**
     * 赠送直播间礼物.
     *
     * @param int $id 直播间 ID。
     * @return Response 返回送礼结果与用户余额。
     */
    public function sendGift(int $id): Response
    {
        if ($id <= 0) {
            return $this->fail('直播间 ID 不能为空');
        }

        $userId = (int)($this->request->user_id ?? 0);
        if ($userId <= 0) {
            return $this->fail('请先登录后赠送礼物');
        }

        $giftId = (int)$this->request->post('gift_id', 0);
        $quantity = (int)$this->request->post('quantity', 1);
        $data = $this->giftRecordService->sendGift($id, $userId, $giftId, $quantity);

        return $this->success('赠送成功', $data);
    }

    /**
     * 批量上报直播间点赞。
     *
     * @param int $id 直播间 ID。
     * @return Response 返回本次点赞数与直播间总点赞数。
     */
    public function like(): Response
    {
        $id = (int)$this->request->post('room_id', 0);
        if ($id <= 0) {
            return $this->fail('直播间 ID 不能为空');
        }

        $userId = (int)($this->request->user_id ?? 0);
        if ($userId <= 0) {
            return $this->fail('请先登录后点赞');
        }

        $count = (int)$this->request->post('count', 1);
        $data = $this->service->submitMobileRoomLike($id, $userId, $count);

        return $this->success('点赞成功', $data);
    }

    /**
     * 兼容旧版动态路径的直播间点赞上报。
     *
     * @param int $id 直播间 ID。
     * @return Response 返回本次点赞数与直播间总点赞数。
     */
    public function likeRoom(int $id): Response
    {
        if ($id <= 0) {
            return $this->fail('直播间 ID 不能为空');
        }

        $userId = (int)($this->request->user_id ?? 0);
        if ($userId <= 0) {
            return $this->fail('请先登录后点赞');
        }

        $count = (int)$this->request->post('count', 1);
        $data = $this->service->submitMobileRoomLike($id, $userId, $count);

        return $this->success('点赞成功', $data);
    }

    /**
     * 获取直播间 WebSocket Token.
     *
     * 路由参数：
     * - id int 直播间 ID。
     *
     * @param int $id 直播间 ID。
     * @return Response 返回 WebSocket token、地址和用户信息。
     */
    public function wsToken(int $id): Response
    {
        if ($id <= 0) {
            return $this->fail('直播间 ID 不能为空');
        }

        $userId = (int)($this->request->user_id ?? 0);
        if ($userId <= 0) {
            return $this->fail('未获取到登录用户');
        }

        $roomModel = app()->make(LiveRoomModel::class);
        $room = $roomModel->find($id);
        if (!$room) {
            return $this->fail('直播间不存在');
        }

        return $this->success('获取成功', [
            'token' => TokenHelper::generateLiveRoomToken($userId, $id),
            'ws_url' => app()->make(WebSocketUrlService::class)->getClientUrl($this->request),
            'room_id' => $id,
            'user_id' => $userId,
        ]);
    }
}
