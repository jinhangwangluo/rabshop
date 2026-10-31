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

namespace app\common\service\live;

use app\common\enum\ContentTypeEnum;
use app\common\exception\AdminResponseException;
use app\common\model\live\LiveGoods;
use app\common\model\order\Cart as CartModel;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\content\ArticleService;
use app\common\service\content\SpecialService;
use app\common\service\order\CartService;
use app\common\service\store\StoreProductService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\model\contract\Modelable;

/**
 * 直播带货商品服务
 * @package app\common\service\live
 */
class LiveGoodsService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    private const MOBILE_ROOM_GOODS_CACHE_TTL = 15;

    /**
     * 设置模型
     * @return string
     */
    protected function setModel(): string
    {
        return LiveGoods::class;
    }

    /**
     * 资源保存
     * @param array $data
     * @return Modelable
     */
    public function resourceSave(array $data)
    {
        if (!empty($data['live_room_id']) && empty($data['link_no'])) {
            $maxLinkNo = $this->getModel()
                ->where('live_room_id', (int)$data['live_room_id'])
                ->max('link_no');
            $data['link_no'] = (int)$maxLinkNo + 1;
        }
        $result = $this->create($data);
        $this->clearMobileRoomGoodsCache((int)($data['live_room_id'] ?? 0));
        return $result;
    }

    /**
     * 更新直播商品资源并清理移动端缓存。
     */
    public function resourceUpdate(int|array $id, array $data): bool
    {
        $oldRoomId = is_int($id) ? (int)$this->getModel()->where('id', $id)->value('live_room_id') : 0;
        $newRoomId = (int)($data['live_room_id'] ?? 0);

        $this->update($id, $data);
        $this->clearMobileRoomGoodsCache($oldRoomId);
        if ($newRoomId > 0 && $newRoomId !== $oldRoomId) {
            $this->clearMobileRoomGoodsCache($newRoomId);
        }

        return true;
    }

    /**
     * 切换上下架状态时同步处理正在讲解状态。
     */
    public function resourceShowUpdate(int|array $id, array $data): bool
    {
        $goodsId = is_int($id) ? $id : (int)($id['id'] ?? 0);
        if ($goodsId <= 0) {
            throw new AdminResponseException('直播间商品不存在');
        }

        $status = (int)($data['status'] ?? 0);
        $this->switchGoodsStatus($goodsId, $status);

        return true;
    }

    /**
     * 删除直播商品资源并清理移动端缓存。
     */
    public function resourceDelete(int $id): bool
    {
        $roomId = (int)$this->getModel()->where('id', $id)->value('live_room_id');
        $result = (bool)$this->getModel()->destroy($id);
        $this->clearMobileRoomGoodsCache($roomId);

        return $result;
    }

    /**
     * 获取指定直播间已添加的商品 ID 列表
     * @param int $liveRoomId 直播间ID
     * @param string $goodsType 商品类型（article / special），空则返回全部
     * @return array int[]
     */
    public function getAddedGoodsIds(int $liveRoomId, string $goodsType = ''): array
    {
        if ($liveRoomId <= 0) {
            return [];
        }
        $query = $this->getModel()->where('live_room_id', $liveRoomId);
        if ($goodsType !== '') {
            $query->where('goods_type', $goodsType);
        }
        return $query->column('goods_id') ?: [];
    }

    /**
     * 按商户直播间范围获取商品列表。
     *
     * @param array $params 查询参数
     * @param array $roomIds 当前商户拥有的直播间 ID 列表
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getMerchantGoodsList(array $params, array $roomIds): array
    {
        [$page, $limit] = $this->getPagesValue();

        if ($roomIds === []) {
            return $this->listData([], 0, [], $page, $limit)->toArray();
        }

        $query = $this->getModel()->whereIn('live_room_id', $roomIds);

        if (($params['live_room_id'] ?? '') !== '') {
            $query->where('live_room_id', (int)$params['live_room_id']);
        }
        if (($params['goods_type'] ?? '') !== '') {
            $query->where('goods_type', (string)$params['goods_type']);
        }
        if (($params['status'] ?? '') !== '') {
            $query->where('status', (int)$params['status']);
        }

        $count = (clone $query)->count();
        $list = $query
            ->order('sort desc, id desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        return $this->listData($this->fillGoodsCover($list), $count, [], $page, $limit)->toArray();
    }

    /**
     * 获取资源列表时保证 goods_cover 是最终展示封面。
     */
    public function getResourceList(array $params = [], array|string $fields = [], array $with = [], string|array|null $sort = null): mixed
    {
        return $this->getDefaultList($params, $fields ?: ['*'], $with, $sort ?: ['sort' => 'desc', 'id' => 'desc'])
            ->each(fn(array $item): array => $this->fillGoodsCoverForItem($item));
    }

    /**
     * 获取付费专题下拉选项（含价格）。
     *
     * @param int $merchantId 商户 ID，传入后仅返回该商户的专题
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getSpecialOptions(int $merchantId = 0): array
    {
        return app()->make(SpecialService::class)->getLiveGoodsOptions($merchantId);
    }

    /**
     * 获取移动端直播间商品列表（带短缓存）。
     * @param int $roomId
     * @return array
     */
    public function getMobileRoomGoods(int $roomId): array
    {
        if ($roomId <= 0) {
            return [];
        }

        return $this->cacheTag()->remember(
            $this->getMobileRoomGoodsCacheKey($roomId),
            function () use ($roomId) {
                $list = $this->getModel()
                    ->where('live_room_id', $roomId)
                    ->where('status', 1)
                    ->field([
                        'id', 'live_room_id', 'goods_id', 'goods_type', 'goods_title',
                        'goods_cover', 'goods_price', 'link_no', 'status', 'sort', 'is_explaining'
                    ])
                    ->order('sort desc, link_no asc, id asc')
                    ->select()
                    ->toArray();

                $displayMap = $this->buildMobileGoodsDisplayMap($list);
                return array_map(
                    fn(array $item): array => $this->formatMobileGoodsItem($item, $displayMap),
                    $list
                );
            },
            self::MOBILE_ROOM_GOODS_CACHE_TTL
        );
    }

    /**
     * 移动端直播间商品立即购买。
     *
     * 直播商品的价格来自 live_goods.goods_price，不能落入普通购物车；这里创建立即购买临时项，
     * 并通过 CartService 的直播标记在结算时还原直播商品快照。
     *
     * @param int $roomId 直播间 ID
     * @param int $userId 当前用户 ID
     * @param int $liveGoodsId live_goods.id
     * @return array
     */
    public function buyNowMobileRoomGoods(int $roomId, int $userId, int $liveGoodsId): array
    {
        if ($roomId <= 0) {
            throw new AdminResponseException('直播间 ID 不能为空');
        }
        if ($userId <= 0) {
            throw new AdminResponseException('请先登录后购买');
        }
        if ($liveGoodsId <= 0) {
            throw new AdminResponseException('请选择要购买的直播商品');
        }

        $liveGoods = $this->getModel()
            ->where('id', $liveGoodsId)
            ->where('live_room_id', $roomId)
            ->where('status', 1)
            ->field([
                'id', 'live_room_id', 'goods_id', 'goods_type', 'goods_title',
                'goods_cover', 'goods_price', 'status',
            ])
            ->find();
        if (!$liveGoods) {
            throw new AdminResponseException('直播间商品已下架');
        }

        $liveGoods = $liveGoods->toArray();
        $goodsId = (int)($liveGoods['goods_id'] ?? 0);
        $orderType = $this->resolveMobileRoomGoodsOrderType((string)($liveGoods['goods_type'] ?? ''));
        if ($goodsId <= 0 || $orderType <= 0) {
            throw new AdminResponseException('直播间商品暂不支持购买');
        }

        return app()->make(CartService::class)->buyNow(
            $userId,
            $goodsId,
            1,
            CartService::LIVE_GOODS_ATTR_PREFIX . (int)$liveGoods['id'],
            $orderType
        );
    }

    /**
     * 获取上架直播商品快照映射。
     *
     * @param array $ids live_goods.id 列表
     * @return array<int,array>
     */
    public function getActiveMapByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }

        $list = $this->getModel()
            ->whereIn('id', $ids)
            ->where('status', 1)
            ->field([
                'id', 'live_room_id', 'goods_id', 'goods_type', 'goods_title',
                'goods_cover', 'goods_price', 'status',
            ])
            ->select()
            ->toArray();
        $map = [];
        foreach ($list as $item) {
            $map[(int)($item['id'] ?? 0)] = $item;
        }

        return $map;
    }

    /**
     * 清理移动端直播商品缓存。
     * @param int $roomId
     * @return void
     */
    public function clearMobileRoomGoodsCache(int $roomId): void
    {
        if ($roomId <= 0) {
            return;
        }

        $this->cacheTag()->delete($this->getMobileRoomGoodsCacheKey($roomId));
    }

    /**
     * 批量更新直播间商品排序
     * @param int $liveRoomId
     * @param array $sortData [['id'=>1,'sort'=>10],...]
     * @return bool
     */
    public function batchUpdateSort(int $liveRoomId, array $sortData): bool
    {
        $result = $this->transaction(function () use ($liveRoomId, $sortData) {
            foreach ($sortData as $item) {
                if (!isset($item['id'], $item['sort'])) {
                    continue;
                }
                $this->getModel()->where([
                    'id' => (int)$item['id'],
                    'live_room_id' => $liveRoomId,
                ])->update(['sort' => (int)$item['sort']]);
            }
            return true;
        });
        $this->clearMobileRoomGoodsCache($liveRoomId);
        return $result;
    }

    /**
     * 切换直播间商品上下架状态
     * @param int $goodsId live_goods.id
     * @param int $status 1=上架，0=下架
     * @return array         更新后的商品信息
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function switchGoodsStatus(int $goodsId, int $status): array
    {
        $goods = $this->getModel()->find($goodsId);
        if (!$goods) {
            throw new AdminResponseException('直播间商品不存在');
        }

        $updateData = ['status' => $status];
        if ($status !== 1) {
            $updateData['is_explaining'] = 0;
        }

        $this->getModel()->where('id', $goodsId)->update($updateData);
        $this->clearMobileRoomGoodsCache((int)$goods['live_room_id']);

        return [
            'id' => $goodsId,
            'live_room_id' => $goods['live_room_id'],
            'status' => $status,
            'is_explaining' => $status === 1 ? (int)($goods['is_explaining'] ?? 0) : 0,
            'goods_title' => $goods['goods_title'],
            'link_no' => $goods['link_no'],
        ];
    }

    /**
     * 设置直播间正在讲解商品，同一直播间只允许一个商品处于讲解中。
     */
    public function setExplaining(int $goodsId, int $isExplaining = 1): array
    {
        $goods = $this->getModel()->find($goodsId);
        if (!$goods) {
            throw new AdminResponseException('直播间商品不存在');
        }

        $roomId = (int)($goods['live_room_id'] ?? 0);
        if ($roomId <= 0) {
            throw new AdminResponseException('直播间信息不正确');
        }

        $nextStatus = $isExplaining === 1 ? 1 : 0;
        if ($nextStatus === 1 && (int)($goods['status'] ?? 0) !== 1) {
            throw new AdminResponseException('请先上架商品后再设置正在讲解');
        }

        $this->transaction(function () use ($goodsId, $roomId, $nextStatus) {
            if ($nextStatus === 1) {
                $this->getModel()
                    ->where('live_room_id', $roomId)
                    ->where('is_explaining', 1)
                    ->update(['is_explaining' => 0]);
            }

            $this->getModel()
                ->where('id', $goodsId)
                ->where('live_room_id', $roomId)
                ->update(['is_explaining' => $nextStatus]);

            return true;
        });
        $this->clearMobileRoomGoodsCache($roomId);

        return [
            'id' => $goodsId,
            'live_room_id' => $roomId,
            'is_explaining' => $nextStatus,
            'goods_title' => (string)($goods['goods_title'] ?? ''),
            'link_no' => (int)($goods['link_no'] ?? 0),
        ];
    }

    /**
     * 更新直播间商品关联的实物商品库存
     * 仅支持 goods_type = product 类型
     * @param int $goodsId live_goods.id
     * @param int $stockChange 库存变化量（正数增加，负数减少）
     * @return array          更新后的信息
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function updateGoodsStock(int $goodsId, int $stockChange): array
    {
        unset($goodsId, $stockChange);
        throw new AdminResponseException('当前版本暂不支持直播商品库存操作');
    }

    /**
     * 更新直播间商品展示信息（标题/封面/价格）
     * @param int $goodsId live_goods.id
     * @param array $info 可包含：goods_title, goods_cover, goods_price
     * @return array          更新后的商品信息
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function updateGoodsInfo(int $goodsId, array $info): array
    {
        $goods = $this->getModel()->find($goodsId);
        if (!$goods) {
            throw new AdminResponseException('直播间商品不存在');
        }

        $allowFields = ['goods_title', 'goods_cover', 'goods_price'];
        $updateData = array_intersect_key($info, array_flip($allowFields));

        if (empty($updateData)) {
            throw new AdminResponseException('未传入有效的更新字段（goods_title/goods_cover/goods_price）');
        }

        if (isset($updateData['goods_price'])) {
            $updateData['goods_price'] = max(0, (float)$updateData['goods_price']);
        }

        $this->getModel()->where('id', $goodsId)->update($updateData);
        $this->clearMobileRoomGoodsCache((int)$goods['live_room_id']);

        return array_merge([
            'id' => $goodsId,
            'live_room_id' => $goods['live_room_id'],
            'link_no' => $goods['link_no'],
            'goods_type' => $goods['goods_type'],
        ], $updateData);
    }

    /**
     * 生成移动端直播商品缓存 key。
     * @param int $roomId
     * @return string
     */
    private function getMobileRoomGoodsCacheKey(int $roomId): string
    {
        return 'mobile_room_goods:' . $roomId;
    }

    /**
     * 格式化移动端直播商品字段。
     * @param array $item
     * @param array $displayMap
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    private function formatMobileGoodsItem(array $item, array $displayMap = []): array
    {
        $goodsType = (string)($item['goods_type'] ?? '');
        $goodsId = (int)($item['goods_id'] ?? 0);
        $target = $this->buildMobileGoodsTarget($goodsType, $goodsId);
        $display = array_replace(
            $this->getDefaultMobileGoodsDisplay(),
            $displayMap[$goodsType . ':' . $goodsId] ?? []
        );

        return [
            'id' => (int)($item['id'] ?? 0),
            'live_room_id' => (int)($item['live_room_id'] ?? 0),
            'goods_id' => $goodsId,
            'goods_type' => $goodsType,
            'goods_title' => (string)($item['goods_title'] ?? ''),
            'goods_cover' => (string)($item['goods_cover'] ?? ''),
            'goods_price' => (string)($item['goods_price'] ?? '0.00'),
            'goods_original_price' => $display['goods_original_price'],
            'goods_subtitle' => $display['goods_subtitle'],
            'goods_tag' => $display['goods_tag'],
            'display_sales' => $display['display_sales'],
            'sales_text' => $display['sales_text'],
            'link_no' => (int)($item['link_no'] ?? 0),
            'status' => (int)($item['status'] ?? 0),
            'sort' => (int)($item['sort'] ?? 0),
            'is_explaining' => (int)($item['is_explaining'] ?? 0),
            'target_type' => $target['target_type'],
            'target_id' => $target['target_id'],
            'target_url' => $target['target_url'],
            'can_open' => $target['can_open'],
        ];
    }

    /**
     * 构建直播商品移动端展示补充字段。
     */
    private function buildMobileGoodsDisplayMap(array $list): array
    {
        $specialIds = [];
        $productIds = [];
        foreach ($list as $item) {
            $goodsType = (string)($item['goods_type'] ?? '');
            $goodsId = (int)($item['goods_id'] ?? 0);
            if ($goodsType === 'special' && $goodsId > 0) {
                $specialIds[] = $goodsId;
            }
            if ($goodsType === 'product' && $goodsId > 0) {
                $productIds[] = $goodsId;
            }
        }

        $specialIds = array_values(array_unique($specialIds));
        $displayMap = [];
        if ($specialIds !== []) {
            $specialDisplayMap = app()->make(SpecialService::class)->getLiveMobileDisplayMap($specialIds);
            foreach ($specialDisplayMap as $specialId => $display) {
                $displayMap['special:' . (int)$specialId] = $display;
            }
        }

        $productIds = array_values(array_unique($productIds));
        if ($productIds !== []) {
            $productDisplayMap = app()->make(StoreProductService::class)->getLiveMobileDisplayMap($productIds);
            foreach ($productDisplayMap as $productId => $display) {
                $displayMap['product:' . (int)$productId] = $display;
            }
        }

        return $displayMap;
    }

    /**
     * 批量补齐直播商品展示封面。
     */
    private function fillGoodsCover(array $list): array
    {
        $articleIds = [];
        $specialIds = [];
        foreach ($list as $item) {
            if ((string)($item['goods_cover'] ?? '') !== '') {
                continue;
            }
            $goodsId = (int)($item['goods_id'] ?? 0);
            if ($goodsId <= 0) {
                continue;
            }
            if ((string)($item['goods_type'] ?? '') === 'article') {
                $articleIds[] = $goodsId;
            }
            if ((string)($item['goods_type'] ?? '') === 'special') {
                $specialIds[] = $goodsId;
            }
        }

        $articleCoverMap = $this->getArticleCoverMap($articleIds);
        $specialCoverMap = $this->getSpecialCoverMap($specialIds);
        foreach ($list as &$item) {
            if ((string)($item['goods_cover'] ?? '') !== '') {
                continue;
            }
            $goodsId = (int)($item['goods_id'] ?? 0);
            if ((string)($item['goods_type'] ?? '') === 'article') {
                $item['goods_cover'] = $articleCoverMap[$goodsId] ?? '';
            }
            if ((string)($item['goods_type'] ?? '') === 'special') {
                $item['goods_cover'] = $specialCoverMap[$goodsId] ?? '';
            }
        }

        return $list;
    }

    /**
     * 补齐单个直播商品展示封面。
     */
    private function fillGoodsCoverForItem(array $item): array
    {
        if ((string)($item['goods_cover'] ?? '') !== '') {
            return $item;
        }
        $goodsId = (int)($item['goods_id'] ?? 0);
        if ($goodsId <= 0) {
            $item['goods_cover'] = '';
            return $item;
        }
        if ((string)($item['goods_type'] ?? '') === 'article') {
            $item['goods_cover'] = $this->getArticleCoverMap([$goodsId])[$goodsId] ?? '';
        }
        if ((string)($item['goods_type'] ?? '') === 'special') {
            $item['goods_cover'] = $this->getSpecialCoverMap([$goodsId])[$goodsId] ?? '';
        }

        return $item;
    }

    /**
     * 内容封面映射。
     */
    private function getArticleCoverMap(array $articleIds): array
    {
        $articleIds = array_values(array_unique(array_filter(array_map('intval', $articleIds))));
        if ($articleIds === []) {
            return [];
        }

        return app()->make(ArticleService::class)
            ->getModel()
            ->whereIn('id', $articleIds)
            ->column('cover', 'id') ?: [];
    }

    /**
     * 专题封面映射。
     */
    private function getSpecialCoverMap(array $specialIds): array
    {
        $specialIds = array_values(array_unique(array_filter(array_map('intval', $specialIds))));
        if ($specialIds === []) {
            return [];
        }

        return app()->make(SpecialService::class)
            ->getModel()
            ->whereIn('id', $specialIds)
            ->column('cover', 'id') ?: [];
    }

    /**
     * 默认直播商品移动端展示补充字段。
     */
    private function getDefaultMobileGoodsDisplay(): array
    {
        return [
            'goods_original_price' => '',
            'goods_subtitle' => '',
            'goods_tag' => '',
            'display_sales' => 0,
            'sales_text' => '',
        ];
    }

    /**
     * 将直播商品类型映射为移动端订单类型。
     */
    private function resolveMobileRoomGoodsOrderType(string $goodsType): int
    {
        return match ($goodsType) {
            'article' => CartModel::ORDER_TYPE_CONTENT,
            'special' => CartModel::ORDER_TYPE_SPECIAL,
            default => 0,
        };
    }

    /**
     * 构建直播商品移动端跳转目标。
     * @param string $goodsType
     * @param int $goodsId
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    private function buildMobileGoodsTarget(string $goodsType, int $goodsId): array
    {
        if ($goodsType === 'article' && $goodsId > 0) {
            $article = app()->make(ArticleService::class)->get($goodsId, ['id', 'status', 'content_type']);
            if ($article && (int)($article['status'] ?? 0) === 1) {
                return [
                    'target_type' => 'article',
                    'target_id' => $goodsId,
                    'target_url' => $this->buildMobileArticleTargetUrl($goodsId, (int)($article['content_type'] ?? 0)),
                    'can_open' => true,
                ];
            }
        }

        if ($goodsType === 'special' && $goodsId > 0) {
            $special = app()->make(SpecialService::class)->get($goodsId, ['id', 'status']);
            if ($special && (int)($special['status'] ?? 0) === 1) {
                return [
                    'target_type' => 'special',
                    'target_id' => $goodsId,
                    'target_url' => '/pages/contents/special/detail/index?id=' . $goodsId,
                    'can_open' => true,
                ];
            }
        }

        if ($goodsType === 'product' && $goodsId > 0) {
            $product = app()->make(StoreProductService::class)->get($goodsId, ['id', 'status']);
            if ($product && (int)($product['status'] ?? 0) === 1) {
                return [
                    'target_type' => 'product',
                    'target_id' => $goodsId,
                    'target_url' => '/pages/goods/detail/index?id=' . $goodsId,
                    'can_open' => true,
                ];
            }
        }

        return [
            'target_type' => $goodsType,
            'target_id' => $goodsId,
            'target_url' => '',
            'can_open' => false,
        ];
    }

    /**
     * 按内容类型构建移动端内容详情页路径。
     * @param int $articleId
     * @param int $contentType
     * @return string
     */
    private function buildMobileArticleTargetUrl(int $articleId, int $contentType): string
    {
        $baseUrl = match ($contentType) {
            ContentTypeEnum::NOVEL => '/pages/contents/content/novel/index',
            ContentTypeEnum::AUDIO => '/pages/contents/content/aud/index',
            ContentTypeEnum::VIDEO => '/pages/contents/content/vid/index',
            default => '/pages/contents/content/detail/index',
        };

        return $baseUrl . '?id=' . $articleId;
    }
}
