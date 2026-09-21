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

namespace app\common\service\order;

use app\common\exception\AdminResponseException;
use app\common\model\order\Order;
use app\common\model\order\OrderGoods;
use app\common\model\order\OrderReview;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\plugin\PluginCheckoutCapabilityService;

/**
 * 订单评价服务
 */
class OrderReviewService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 指定当前服务管理的持久化模型。
     *
     * @return string 当前服务对应的模型完整类名。
     */
    protected function setModel(): string
    {
        return OrderReview::class;
    }

    /**
     * 用户提交评价.
     *
     * @param int $userId 当前业务用户编号。
     * @param int $orderId 当前业务订单的内部编号。
     * @param int $orderGoodsId 当前订单商品明细的内部编号。
     * @param int $star 用户提交的评价星级。
     * @param string $content 用户提交的评价正文。
     * @param array $images 用户提交的评价图片地址列表。
     * @param int $descriptionStar 商品描述一致性的评价星级。
     * @param int $logisticsStar 物流服务的评价星级。
     * @param int $serviceStar 商户服务的评价星级。
     * @param int $isAnonymous 是否匿名展示评价用户身份。
     * @return void 无返回值；保存评价并在全部商品已评后推进订单完成。
     * @throws AdminResponseException
     */
    public function submitReview(
        int $userId,
        int $orderId,
        int $orderGoodsId,
        int $star,
        string $content,
        array $images,
        int $descriptionStar = OrderReview::STAR_MAX,
        int $logisticsStar = OrderReview::STAR_MAX,
        int $serviceStar = OrderReview::STAR_MAX,
        int $isAnonymous = 1
    ): void
    {
        $star = $this->normalizeStar($star, '整体评分');
        $descriptionStar = $this->normalizeStar($descriptionStar, '描述相符评分');
        $logisticsStar = $this->normalizeStar($logisticsStar, '物流服务评分');
        $serviceStar = $this->normalizeStar($serviceStar, '服务态度评分');

        /**
         * 订单服务实例，复用统一订单状态与完成规则。
         *
         * @var OrderService $orderService
         */
        $orderService = app()->make(OrderService::class);
        $order = $orderService->get(['id' => $orderId, 'user_id' => $userId]);
        if (!$order) {
            throw new AdminResponseException('订单不存在');
        }
        if (app()->make(PluginCheckoutCapabilityService::class)->isHotelOrder($order->toArray())) {
            throw new AdminResponseException('酒店预订不支持普通商品评价');
        }
        if (!$this->isReviewableStatus((int)$order['status'])) {
            throw new AdminResponseException('仅待评价订单可以评价');
        }
        $orderGoods = $this->resolveReviewOrderGoods($orderId, $orderGoodsId);
        $orderGoodsId = (int)($orderGoods['id'] ?? 0);

        $exists = $this->get([
            'order_id' => $orderId,
            'order_goods_id' => $orderGoodsId,
        ]);
        if ($exists) {
            throw new AdminResponseException('该商品已评价，不可重复提交');
        }

        $this->create([
            'order_id'         => $orderId,
            'order_goods_id'   => $orderGoodsId,
            'user_id'          => $userId,
            'merchant_id'      => (int)($order['merchant_id'] ?? 0),
            'star'             => $star,
            'description_star' => $descriptionStar,
            'logistics_star'   => $logisticsStar,
            'service_star'     => $serviceStar,
            'content'          => $content,
            'images'           => $this->normalizeImages($images),
            'is_anonymous'     => $isAnonymous ? 1 : 0,
        ]);

        $this->completeOrderWhenFullyReviewed($orderService, $order->toArray());
    }

    /**
     * 获取订单评价页面数据.
     *
     * @param int $userId 当前业务用户编号。
     * @param int $orderId 当前业务订单的内部编号。
     * @param int $orderGoodsId 当前订单商品明细的内部编号。
     * @return array 当前订单、目标商品、已有评价及是否可评价的页面数据。
     * @throws AdminResponseException
     */
    public function getReviewPage(int $userId, int $orderId, int $orderGoodsId = 0): array
    {
        /**
         * 订单服务实例，复用统一订单状态与完成规则。
         *
         * @var OrderService $orderService
         */
        $orderService = app()->make(OrderService::class);
        $order = $orderService->getUserOrderDetailById($userId, $orderId);
        if (app()->make(PluginCheckoutCapabilityService::class)->isHotelOrder($order)) {
            throw new AdminResponseException('酒店预订不支持普通商品评价');
        }
        $targetGoods = $this->resolveReviewPageGoods($order, $orderGoodsId);
        $targetOrderGoodsId = (int)($targetGoods['order_goods_id'] ?? 0);
        $review = $this->getUserReview($userId, $orderId, $targetOrderGoodsId);
        $state = $this->resolveReviewItemState($order, $targetOrderGoodsId, $review);

        return [
            'order' => $order,
            'target_goods' => $targetGoods,
            'review' => $review,
            'can_review' => $state['can_review'],
            'has_reviewed' => $state['has_reviewed'],
            'reason' => $state['reason'],
        ];
    }

    /**
     * 获取用户对指定订单的评价.
     *
     * @param int $userId 当前业务用户编号。
     * @param int $orderId 当前业务订单的内部编号。
     * @param int $orderGoodsId 当前订单商品明细的内部编号。
     * @return ?array 当前用户指定商品的规范化评价；未评价时为空。
     */
    public function getUserReview(int $userId, int $orderId, int $orderGoodsId = 0): ?array
    {
        $query = $this->getModel()
            ->where('order_id', $orderId)
            ->where('user_id', $userId);

        if ($orderGoodsId > 0) {
            $query->where('order_goods_id', $orderGoodsId);
        }

        $review = $query->order('order_goods_id asc, id asc')->find();

        return $review ? $this->normalizeReview($review->toArray()) : null;
    }

    /**
     * 获取订单评价，不限制用户，用于判断订单操作状态。
     *
     * @param int $orderId 当前业务订单的内部编号。
     * @param int $orderGoodsId 当前订单商品明细的内部编号。
     * @return ?array 不限制用户的指定订单商品评价；未评价时为空。
     */
    public function getOrderReview(int $orderId, int $orderGoodsId = 0): ?array
    {
        $query = $this->getModel()->where('order_id', $orderId);
        if ($orderGoodsId > 0) {
            $query->where('order_goods_id', $orderGoodsId);
        }

        $review = $query->order('order_goods_id asc, id asc')->find();

        return $review ? $this->normalizeReview($review->toArray()) : null;
    }

    /**
     * 获取订单评价映射，键为订单商品ID.
     *
     * @param int $orderId 当前业务订单的内部编号。
     * @return array 以订单商品编号为键的已有评价映射。
     */
    public function getOrderReviewMap(int $orderId): array
    {
        if ($orderId <= 0) {
            return [];
        }

        $list = $this->getModel()
            ->where('order_id', $orderId)
            ->where('order_goods_id', '>', 0)
            ->select()
            ->toArray();

        $map = [];
        foreach ($list as $review) {
            $map[(int)($review['order_goods_id'] ?? 0)] = $this->normalizeReview($review);
        }

        return $map;
    }

    /**
     * 获取商品详情页高分评价列表与统计。
     *
     * @param int $productId 商品ID
     * @param int $page 页码
     * @param int $limit 每页数量
     * @param int $starMin 最低星级
     * @return array{list: array, summary: array} 达到星级要求的商品评价列表及真实评价统计。
     */
    public function getMobileProductReviews(int $productId, int $page = 1, int $limit = 3, int $starMin = 4): array
    {
        if ($productId <= 0) {
            return $this->buildMobileProductReviewResult([], 0, 0, 0.0);
        }

        $page = max(1, $page);
        $limit = max(1, min(20, $limit));
        $starMin = max(OrderReview::STAR_MIN, min(OrderReview::STAR_MAX, $starMin));
        $orderGoodsIds = $this->getProductOrderGoodsIds($productId);
        if ($orderGoodsIds === []) {
            return $this->buildMobileProductReviewResult([], 0, 0, 0.0);
        }

        $total = (int)$this->getModel()
            ->whereIn('order_goods_id', $orderGoodsIds)
            ->count();
        $avgStar = $total > 0
            ? round((float)$this->getModel()->whereIn('order_goods_id', $orderGoodsIds)->avg('star'), 1)
            : 0.0;
        $goodCount = (int)$this->getModel()
            ->whereIn('order_goods_id', $orderGoodsIds)
            ->where('star', '>=', $starMin)
            ->count();

        $list = $this->getModel()
            ->whereIn('order_goods_id', $orderGoodsIds)
            ->where('star', '>=', $starMin)
            ->with(['user', 'orderGoods'])
            ->order('star desc, id desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        return $this->buildMobileProductReviewResult($list, $total, $goodCount, $avgStar);
    }

    /**
     * 获取商品评价统计。
     *
     * @param int $productId 商品ID
     * @param int $starMin 高分评价阈值
     * @return array{total: int, good_count: int, good_rate: float|int, avg_star: float} 商品评价总量、好评量、好评率及平均星级。
     */
    public function getMobileProductReviewSummary(int $productId, int $starMin = 4): array
    {
        if ($productId <= 0) {
            return $this->buildMobileProductReviewSummary(0, 0, 0.0);
        }

        $starMin = max(OrderReview::STAR_MIN, min(OrderReview::STAR_MAX, $starMin));
        $orderGoodsIds = $this->getProductOrderGoodsIds($productId);
        if ($orderGoodsIds === []) {
            return $this->buildMobileProductReviewSummary(0, 0, 0.0);
        }

        $total = (int)$this->getModel()
            ->whereIn('order_goods_id', $orderGoodsIds)
            ->count();
        $goodCount = (int)$this->getModel()
            ->whereIn('order_goods_id', $orderGoodsIds)
            ->where('star', '>=', $starMin)
            ->count();
        $avgStar = $total > 0
            ? round((float)$this->getModel()->whereIn('order_goods_id', $orderGoodsIds)->avg('star'), 1)
            : 0.0;

        return $this->buildMobileProductReviewSummary($total, $goodCount, $avgStar);
    }

    /**
     * 获取商品关联订单商品 ID。
     *
     * @param int $productId 商品ID
     * @return array<int> 当前商品关联的去重订单商品明细编号。
     */
    protected function getProductOrderGoodsIds(int $productId): array
    {
        if ($productId <= 0) {
            return [];
        }

        $orderGoodsIds = app()->make(OrderGoodsService::class)->getModel()
            ->where('goods_id', $productId)
            ->where('order_type', OrderGoods::ORDER_TYPE_GOODS)
            ->column('id');

        return array_values(array_unique(array_filter(array_map('intval', $orderGoodsIds))));
    }

    /**
     * 组装商品详情页评价返回结构。
     *
     * @param array $reviews 原始评价列表
     * @param int $total 评价总数
     * @param int $goodCount 高分评价数
     * @param float $avgStar 平均星级
     * @return array{list: array, summary: array} 规范化的商品评价列表及汇总统计。
     */
    protected function buildMobileProductReviewResult(array $reviews, int $total, int $goodCount, float $avgStar): array
    {
        $list = [];
        foreach ($reviews as $review) {
            $user = is_array($review['user'] ?? null) ? $review['user'] : [];
            $orderGoods = is_array($review['orderGoods'] ?? null) ? $review['orderGoods'] : [];
            $isAnonymous = (int)($review['is_anonymous'] ?? 1) === 1;
            $list[] = [
                'id' => (int)($review['id'] ?? 0),
                'user' => [
                    'nickname' => $isAnonymous ? $this->maskReviewNickname((string)($user['nickname'] ?? '匿名用户')) : (string)($user['nickname'] ?? ''),
                    'avatar' => (string)($user['avatar'] ?? ''),
                ],
                'star' => (int)($review['star'] ?? OrderReview::STAR_MAX),
                'content' => (string)($review['content'] ?? ''),
                'images' => $this->normalizeImages($review['images'] ?? []),
                'sku_name' => (string)($orderGoods['sku_name'] ?? ''),
                'admin_reply' => (string)($review['admin_reply'] ?? ''),
                'create_time' => $review['create_time'] ?? null,
            ];
        }

        return [
            'list' => $list,
            'summary' => $this->buildMobileProductReviewSummary($total, $goodCount, $avgStar),
        ];
    }

    /**
     * 组装商品评价统计。
     *
     * @param int $total 评价总数
     * @param int $goodCount 高分评价数
     * @param float $avgStar 平均星级
     * @return array{total: int, good_count: int, good_rate: float|int, avg_star: float} 评价总量、好评量、好评率及平均星级。
     */
    protected function buildMobileProductReviewSummary(int $total, int $goodCount, float $avgStar): array
    {
        return [
            'total' => $total,
            'good_count' => $goodCount,
            'good_rate' => $total > 0 ? round($goodCount * 100 / $total, 2) : 0,
            'avg_star' => round(max(0.0, min((float)OrderReview::STAR_MAX, $avgStar)), 1),
        ];
    }

    /**
     * 匿名评价昵称脱敏。
     *
     * @param string $nickname 待匿名脱敏的评价昵称。
     * @return string 仅保留必要首字的匿名评价昵称。
     */
    protected function maskReviewNickname(string $nickname): string
    {
        $name = trim($nickname);
        if ($name === '') {
            return '匿名用户';
        }

        return mb_substr($name, 0, 1) . '***';
    }

    /**
     * 解析订单是否可评价.
     *
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @param array|null $review 当前订单或商品的已有评价记录。
     * @return array 订单是否可评价、是否已评价及不可评价原因。
     */
    public function resolveReviewState(array $order, array|null $review = null): array
    {
        if (app()->make(PluginCheckoutCapabilityService::class)->isHotelOrder($order)) {
            return [
                'can_review' => false,
                'has_reviewed' => false,
                'reason' => '酒店预订不支持普通商品评价',
            ];
        }

        $orderId = (int)($order['order_id'] ?? ($order['id'] ?? 0));
        $status = (int)($order['status'] ?? Order::STATUS_PENDING);
        $goodsCount = $this->countReviewGoods($orderId, $order);
        $reviewCount = $this->countReviewedGoods($orderId);
        $hasReviewed = $goodsCount > 0 && $reviewCount >= $goodsCount;
        $canReview = $this->isReviewableStatus($status) && $goodsCount > 0 && !$hasReviewed;
        $reason = '';
        if (!$this->isReviewableStatus($status)) {
            $reason = '仅待评价订单可以评价';
        } elseif ($hasReviewed) {
            $reason = '该订单已评价';
        } elseif ($goodsCount <= 0) {
            $reason = '订单商品不存在';
        }

        return [
            'can_review' => $canReview,
            'has_reviewed' => $hasReviewed,
            'reason' => $canReview ? '' : $reason,
        ];
    }

    /**
     * 解析指定订单商品是否可评价.
     *
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @param int $orderGoodsId 当前订单商品明细的内部编号。
     * @param array|null $review 当前订单或商品的已有评价记录。
     * @return array 指定商品是否可评价、是否已评价及不可评价原因。
     */
    public function resolveReviewItemState(array $order, int $orderGoodsId, array|null $review = null): array
    {
        if (app()->make(PluginCheckoutCapabilityService::class)->isHotelOrder($order)) {
            return [
                'can_review' => false,
                'has_reviewed' => false,
                'reason' => '酒店预订不支持普通商品评价',
            ];
        }

        $orderId = (int)($order['order_id'] ?? ($order['id'] ?? 0));
        if ($review === null && $orderId > 0 && $orderGoodsId > 0) {
            $review = $this->getOrderReview($orderId, $orderGoodsId);
        }

        $status = (int)($order['status'] ?? Order::STATUS_PENDING);
        $hasReviewed = !empty($review);
        $canReview = $this->isReviewableStatus($status) && $orderGoodsId > 0 && !$hasReviewed;
        $reason = '';
        if (!$this->isReviewableStatus($status)) {
            $reason = '仅待评价订单可以评价';
        } elseif ($orderGoodsId <= 0) {
            $reason = '请选择要评价的商品';
        } elseif ($hasReviewed) {
            $reason = '该商品已评价';
        }

        return [
            'can_review' => $canReview,
            'has_reviewed' => $hasReviewed,
            'reason' => $canReview ? '' : $reason,
        ];
    }

    /**
     * 规范化评价输出.
     *
     * @param array $review 当前订单或商品的已有评价记录。
     * @return array 统一字段类型并补齐展示身份的评价记录。
     */
    protected function normalizeReview(array $review): array
    {
        return [
            'id' => (int)($review['id'] ?? 0),
            'order_id' => (int)($review['order_id'] ?? 0),
            'order_goods_id' => (int)($review['order_goods_id'] ?? 0),
            'user_id' => (int)($review['user_id'] ?? 0),
            'merchant_id' => (int)($review['merchant_id'] ?? 0),
            'star' => (int)($review['star'] ?? OrderReview::STAR_MAX),
            'description_star' => (int)($review['description_star'] ?? ($review['star'] ?? OrderReview::STAR_MAX)),
            'logistics_star' => (int)($review['logistics_star'] ?? ($review['star'] ?? OrderReview::STAR_MAX)),
            'service_star' => (int)($review['service_star'] ?? ($review['star'] ?? OrderReview::STAR_MAX)),
            'content' => (string)($review['content'] ?? ''),
            'images' => $this->normalizeImages($review['images'] ?? []),
            'is_anonymous' => (int)($review['is_anonymous'] ?? 1),
            'admin_reply' => (string)($review['admin_reply'] ?? ''),
            'reply_time' => $review['reply_time'] ?? null,
            'create_time' => $review['create_time'] ?? null,
            'update_time' => $review['update_time'] ?? null,
        ];
    }

    /**
     * 待评价新状态和历史已完成订单均允许评价。
     *
     * @param int $status 当前业务状态或列表状态筛选值。
     * @return bool 当前订单状态是否允许评价。
     */
    protected function isReviewableStatus(int $status): bool
    {
        return in_array($status, [Order::STATUS_WAIT_REVIEW, Order::STATUS_COMPLETED], true);
    }

    /**
     * 校验并规范化星级.
     *
     * @param int $star 用户提交的评价星级。
     * @param string $label 星级校验失败时使用的业务字段名称。
     * @return int 通过业务范围校验的评价星级。
     * @throws AdminResponseException
     */
    protected function normalizeStar(int $star, string $label): int
    {
        if ($star < OrderReview::STAR_MIN || $star > OrderReview::STAR_MAX) {
            throw new AdminResponseException($label . '须在 1 到 5 之间');
        }

        return $star;
    }

    /**
     * 规范化评价图片地址列表。
     *
     * @param mixed $images 用户提交的评价图片地址列表。
     * @return array 规范化并去重后的评价图片地址列表。
     */
    protected function normalizeImages(mixed $images): array
    {
        if (!is_array($images)) {
            return [];
        }

        $result = [];
        foreach ($images as $image) {
            $url = trim((string)$image);
            if ($url !== '') {
                $result[] = $url;
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * 获取评价页面目标商品.
     *
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @param int $orderGoodsId 当前订单商品明细的内部编号。
     * @return array 当前订单内指定或默认的评价目标商品。
     * @throws AdminResponseException
     */
    protected function resolveReviewPageGoods(array $order, int $orderGoodsId): array
    {
        $goodsList = is_array($order['goods'] ?? null) ? $order['goods'] : [];
        if ($goodsList === []) {
            throw new AdminResponseException('订单商品不存在');
        }

        if ($orderGoodsId > 0) {
            foreach ($goodsList as $goods) {
                if ((int)($goods['order_goods_id'] ?? 0) === $orderGoodsId) {
                    return $goods;
                }
            }

            throw new AdminResponseException('评价商品不存在');
        }

        return $goodsList[0];
    }

    /**
     * 获取提交评价时的订单商品.
     *
     * @param int $orderId 当前业务订单的内部编号。
     * @param int $orderGoodsId 当前订单商品明细的内部编号。
     * @return array 校验订单归属后的待评价商品成交资料。
     * @throws AdminResponseException
     */
    protected function resolveReviewOrderGoods(int $orderId, int $orderGoodsId): array
    {
        $query = app()->make(OrderGoodsService::class)->getModel()
            ->where('order_id', $orderId)
            ->order('id asc');
        if ($orderGoodsId > 0) {
            $query->where('id', $orderGoodsId);
        }

        $orderGoods = $query->find();
        if (!$orderGoods) {
            throw new AdminResponseException('评价商品不存在');
        }

        return $orderGoods->toArray();
    }

    /**
     * 统计订单可评价商品数量.
     *
     * @param int $orderId 当前业务订单的内部编号。
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @return int 当前订单可评价的商品明细数量。
     */
    protected function countReviewGoods(int $orderId, array $order = []): int
    {
        if ($orderId <= 0) {
            return 0;
        }

        if (is_array($order['goods'] ?? null)) {
            return count($order['goods']);
        }

        return (int)app()->make(OrderGoodsService::class)->getModel()
            ->where('order_id', $orderId)
            ->count();
    }

    /**
     * 统计订单已评价商品数量.
     *
     * @param int $orderId 当前业务订单的内部编号。
     * @return int 当前订单已评价的去重商品明细数量。
     */
    protected function countReviewedGoods(int $orderId): int
    {
        if ($orderId <= 0) {
            return 0;
        }

        $orderGoodsIds = $this->getModel()
            ->where('order_id', $orderId)
            ->where('order_goods_id', '>', 0)
            ->column('order_goods_id');

        return count(array_unique(array_filter(array_map('intval', $orderGoodsIds))));
    }

    /**
     * 全部商品完成评价后，将订单推进为已完成。
     *
     * @param OrderService $orderService 推进真实订单完成状态的宿主订单服务。
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @return void 无返回值；仅在全部商品完成评价后推进订单完成。
     */
    protected function completeOrderWhenFullyReviewed(OrderService $orderService, array $order): void
    {
        $orderId = (int)($order['id'] ?? ($order['order_id'] ?? 0));
        if ($orderId <= 0 || (int)($order['status'] ?? Order::STATUS_PENDING) !== Order::STATUS_WAIT_REVIEW) {
            return;
        }

        $goodsCount = $this->countReviewGoods($orderId, $order);
        if ($goodsCount <= 0 || $this->countReviewedGoods($orderId) < $goodsCount) {
            return;
        }

        $orderService->update($orderId, [
            'status' => Order::STATUS_COMPLETED,
            'finish_time' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 管理员获取评价列表（分页，支持订单号/星级/是否已回复筛选）
     *
     * @param array $where 由控制器从请求参数提取后传入
     * @return array 按订单、星级及回复状态筛选的后台评价分页。
     */
    public function getAdminList(array $where = []): array
    {
        [$page, $limit] = $this->getPageValue();

        $model = $this->getModel()->searchWhere($where)->with(['order', 'user']);
        $count = $model->count();
        $list  = $model->order('id', 'desc')->page($page, $limit)->select()->toArray();

        return $this->listData($list, $count)->toArray();
    }

    /**
     * 管理员回复评价
     *
     * @param int $reviewId 当前评价记录编号。
     * @param string $reply 管理员提交的评价回复正文。
     * @return void 无返回值；登记管理员回复内容及回复时间。
     * @throws AdminResponseException
     */
    public function adminReply(int $reviewId, string $reply): void
    {
        if ($reply === '') {
            throw new AdminResponseException('回复内容不能为空');
        }

        $review = $this->get($reviewId);
        if (!$review) {
            throw new AdminResponseException('评价不存在');
        }

        $this->update($reviewId, [
            'admin_reply' => $reply,
            'reply_time'  => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 管理员删除评价（软删除）
     *
     * @param int $reviewId 当前评价记录编号。
     * @return void 无返回值；按项目软删除规则删除评价记录。
     * @throws AdminResponseException
     */
    public function adminDelete(int $reviewId): void
    {
        $review = $this->get($reviewId);
        if (!$review) {
            throw new AdminResponseException('评价不存在');
        }

        $review->delete();
    }
}
