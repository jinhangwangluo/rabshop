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

namespace app\common\service\content;

use app\common\enum\AuditStatusEnum;
use app\common\enum\ContentTypeEnum;
use app\common\exception\AdminResponseException;
use app\common\model\content\Special;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\message\BackendMessageEventService;
use app\common\service\message\BackendMessageService;
use app\common\service\SensitiveFilterTrait;
use app\common\service\setting\CategoryService;
use app\common\service\user\UserBrowseRecordService;
use app\common\service\user\UserFavoriteService;
use app\common\service\user\UserMemberService;
use app\common\model\order\Order;
use app\common\service\order\UserPurchaseRecordService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 专题服务
 */
class SpecialService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;
    use SensitiveFilterTrait;

    /**
     * 设置对应的模型
     *
     * @return string 当前服务对应的模型类名。
     */
    protected function setModel(): string
    {
        return Special::class;
    }

    /**
     * 批量获取直播移动端专题展示字段。
     *
     * @param int[] $ids 待查询或审核的专题标识集合。
     * @return array<int, array> 以专题标识为键的直播商品展示资料。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getLiveMobileDisplayMap(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }

        $list = $this->getModel()
            ->whereIn('id', $ids)
            ->field(['id', 'description', 'original_price', 'is_hot', 'content_count'])
            ->select()
            ->toArray();

        $displayMap = [];
        foreach ($list as $special) {
            $specialId = (int)($special['id'] ?? 0);
            if ($specialId <= 0) {
                continue;
            }

            $tag = '';
            if ((int)($special['is_hot'] ?? 0) === 1) {
                $tag = '热门专题';
            } elseif ((int)($special['content_count'] ?? 0) > 0) {
                $tag = (int)$special['content_count'] . '节内容';
            }

            $displayMap[$specialId] = [
                'goods_original_price' => (string)($special['original_price'] ?? '0.00'),
                'goods_subtitle' => (string)($special['description'] ?? ''),
                'goods_tag' => $tag,
            ];
        }

        return $displayMap;
    }

    /**
     * 获取直播商品可选专题下拉数据。
     *
     * @param int $merchantId 正数时限定该商户，零值时不追加商户筛选。
     * @return array 当前归属范围内可用于直播的专题选项。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getLiveGoodsOptions(int $merchantId = 0): array
    {
        $query = $this->getModel()->where('status', 1);

        if ($merchantId > 0) {
            $query->where('merchant_id', $merchantId);
        }

        return $query
            ->field('id, title, price, cover')
            ->order('sort desc, id desc')
            ->limit(500)
            ->select()
            ->toArray();
    }

    /**
     * 重写资源列表方法，支持 exclude_ids 排除已添加到直播间的专题
     *
     * @param array $params 专题列表的筛选和分页参数。
     * @param array|string $fields 本次查询需要返回的字段集合。
     * @param array $with 需要预加载的关联名称。
     * @param string|array|null $sort 业务排序字段、方向或预定义排序规则。
     * @return mixed 按筛选和分页规则组装的资源列表结果。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getResourceList(array $params = [], array|string $fields = [], array $with = [], string|array|null $sort = null)
    {
        $excludeIds = [];
        if (!empty($params['exclude_ids']) && is_array($params['exclude_ids'])) {
            $excludeIds = array_map('intval', $params['exclude_ids']);
        }
        unset($params['exclude_ids']);

        if (empty($excludeIds)) {
            $result = $this->getDefaultList($params, $fields, $with, $sort)->toArray();
            $result['list'] = app()->make(TagService::class)->attachTagsToList(
                $result['list'] ?? [],
                TagService::RELATION_TYPE_SPECIAL
            );
            return $result;
        }

        // 有排除 ID 时，手动构建查询
        [$page, $limit] = $this->getPagesValue();
        $list = $this->getModel()
            ->searchWhere($params)
            ->whereNotIn('id', $excludeIds)
            ->when($sort, function ($query) use ($sort) {
                if (is_array($sort)) {
                    foreach ($sort as $k => $v) {
                        is_numeric($k) ? $query->order($v, 'desc') : $query->order($k, $v);
                    }
                } else {
                    $query->order($sort);
                }
            })
            ->field($fields ?: ['*'])
            ->page($page, $limit)
            ->select()
            ->toArray();

        $total = $this->getModel()
            ->searchWhere($params)
            ->whereNotIn('id', $excludeIds)
            ->count();

        $list = app()->make(TagService::class)->attachTagsToList($list, TagService::RELATION_TYPE_SPECIAL);
        return $this->listData($list, $total, [], $page, $limit);
    }

    /**
     * 获取移动端专题列表。
     *
     * @param array $params 公开筛选与分页参数，内部展示服务可传有效_ids/_category_ids集合。
     * @return array 公开专题列表与分页信息。
     */
    public function getMobileList(array $params = []): array
    {
        [$page, $limit] = $this->getPagesValue(true, true, $params, (int)($params['_limit_max'] ?? 30));
        $displayConfig = app()->make(ContentDisplayConfigService::class);
        if (!$displayConfig->isSpecialEnabled()) {
            return $displayConfig->emptyPage($page, $limit);
        }

        $filters = $this->buildMobileListFilters($params);
        $userId = max(0, (int)($params['user_id'] ?? 0));
        $memberState = app()->make(UserMemberService::class)->getMemberRuntimeState($userId);

        $baseQuery = $this->getModel()
            ->searchWhere($filters)
            ->whereNull('delete_time')
            ->where('status', 1)
            ->where('audit_status', AuditStatusEnum::APPROVED);

        // 空自选集合明确表示不展示；排序保留商户自选顺序。
        if (array_key_exists('_ids', $params)) {
            $ids = is_array($params['_ids']) ? array_values(array_map('intval', $params['_ids'])) : [];
            $baseQuery->whereIn('id', $ids === [] ? [0] : $ids)->orderField('id', $ids, 'asc');
        }
        if (array_key_exists('_category_ids', $params)) {
            $categoryIds = is_array($params['_category_ids']) ? $params['_category_ids'] : [];
            $baseQuery->whereIn('category_id', $categoryIds === [] ? [0] : $categoryIds);
        }

        if (!(bool)($memberState['member_visible'] ?? false)) {
            $baseQuery->where('is_member_only', 0);
        }

        $listQuery = array_key_exists('_ids', $params)
            ? clone $baseQuery
            : $this->applyMobileListSort(clone $baseQuery, (string)($params['sort'] ?? 'default'));
        $list = $listQuery
            ->field([
                'id', 'merchant_id', 'category_id', 'title', 'keywords', 'description', 'cover',
                'special_type', 'price', 'original_price', 'content_count',
                'view_count', 'buy_count', 'is_hot', 'is_member_only', 'sort', 'create_time'
            ])
            ->page($page, $limit)
            ->select()
            ->toArray();

        $total = (int)(clone $baseQuery)->count();
        $list = app()->make(TagService::class)->attachTagsToList($list, TagService::RELATION_TYPE_SPECIAL);
        $list = $this->appendMobileSpecialMemberState($list, $userId);

        return $this->listData($list, $total, ['has_more' => $page * $limit < $total], $page, $limit)->toArray();
    }

    /**
     * 获取商户首页推荐专题展示列表。
     *
     * @param int $merchantId 商户ID
     * @param array $specialIds 专题ID列表
     * @param int $userId 当前用户ID，匿名为0。
     * @return array 按商户配置顺序组装的公开推荐专题。
     */
    public function getMerchantHomeRecommendList(int $merchantId, array $specialIds, int $userId = 0): array
    {
        if (!app()->make(ContentDisplayConfigService::class)->isSpecialEnabled()) {
            return [];
        }

        $merchantId = max(0, $merchantId);
        $specialIds = array_slice($this->normalizeIds($specialIds), 0, 100);
        if ($merchantId <= 0 || $specialIds === []) {
            return [];
        }

        $query = $this->getModel()
            ->where('merchant_id', $merchantId)
            ->whereIn('id', $specialIds)
            ->whereNull('delete_time')
            ->where('status', 1)
            ->where('audit_status', AuditStatusEnum::APPROVED);
        $memberState = app()->make(UserMemberService::class)->getMemberRuntimeState($userId);
        if (!(bool)($memberState['member_visible'] ?? false)) {
            $query->where('is_member_only', 0);
        }
        $list = $query
            ->field([
                'id', 'category_id', 'title', 'keywords', 'description', 'cover',
                'special_type', 'price', 'original_price', 'content_count',
                'view_count', 'buy_count', 'is_hot', 'sort'
            ])
            ->select()
            ->toArray();

        $specialMap = [];
        foreach ($list as $special) {
            $specialId = (int)($special['id'] ?? 0);
            if ($specialId > 0) {
                $specialMap[$specialId] = $this->formatMerchantHomeSpecial($special);
            }
        }

        $result = [];
        foreach ($specialIds as $specialId) {
            if (isset($specialMap[$specialId])) {
                $result[] = $specialMap[$specialId];
            }
        }

        return $result;
    }

    /**
     * 获取移动端专题详情。
     *
     * @param int $specialId 专题记录标识。
     * @param int $userId 当前用户标识，匿名访问时为零。
     * @return array 移动端专题资料、购买权限和关联内容列表。
     * @throws AdminResponseException
     */
    public function getMobileDetail(int $specialId, int $userId = 0): array
    {
        $displayConfig = app()->make(ContentDisplayConfigService::class);
        if (!$displayConfig->isSpecialEnabled()) {
            return [];
        }

        $special = $this->get($specialId);
        if (!$special) {
            throw new AdminResponseException('专题不存在');
        }

        $specialData = $special->toArray();
        if ((int)($specialData['status'] ?? 0) !== 1) {
            throw new AdminResponseException('专题已下架');
        }
        if ((int)($specialData['audit_status'] ?? 0) !== AuditStatusEnum::APPROVED) {
            throw new AdminResponseException('专题暂不可访问');
        }

        $contents = app()->make(SpecialContentService::class)
            ->getContentListWithDetail($specialId);
        $contentList = array_map(
            fn(array $item): array => $this->formatMobileDetailContent($item),
            $contents
        );
        $isPurchased = app()->make(UserPurchaseRecordService::class)
            ->hasPurchased($userId, Order::ORDER_TYPE_SPECIAL, $specialId);
        $memberService = app()->make(UserMemberService::class);
        $memberState = $memberService->getMemberRuntimeState($userId);
        $memberPricePreview = $memberService->getMemberPricePreview(
            (int)($specialData['merchant_id'] ?? 0),
            (float)($specialData['price'] ?? 0),
            $userId
        );
        $memberPriceAvailable = (bool)($memberPricePreview['member_price_available'] ?? false);
        $isFree = (int)($specialData['special_type'] ?? 0) === 0 || (float)($specialData['price'] ?? 0) <= 0;
        $canLearn = $isFree || $isPurchased;
        $canBuy = !$canLearn && (int)($specialData['special_type'] ?? 0) === 1 && (float)($specialData['price'] ?? 0) > 0;

        // 会员专属内容访问控制
        $isMemberOnly = (int)($specialData['is_member_only'] ?? 0) === 1;
        $memberAccess = true;
        if ($isMemberOnly && $userId > 0) {
            $memberAccess = (bool)($memberState['is_member'] ?? false);
        } elseif ($isMemberOnly) {
            $memberAccess = false;
        }
        if ($isMemberOnly && !$memberAccess) {
            $canLearn = false;
            $canBuy = false;
        }

        $accessState = !$isMemberOnly || $memberAccess
            ? ($canLearn ? 'learning' : 'purchase')
            : ((bool)($memberState['member_enabled'] ?? false) ? 'member_required' : 'unavailable');

        return [
            'id' => (int)($specialData['id'] ?? 0),
            'merchant_id' => (int)($specialData['merchant_id'] ?? 0),
            'category_id' => (int)($specialData['category_id'] ?? 0),
            'title' => (string)($specialData['title'] ?? ''),
            'keywords' => (string)($specialData['keywords'] ?? ''),
            'description' => (string)($specialData['description'] ?? ''),
            'cover' => (string)($specialData['cover'] ?? ''),
            'special_type' => (int)($specialData['special_type'] ?? 0),
            'price' => (float)($specialData['price'] ?? 0),
            'original_price' => (float)($specialData['original_price'] ?? 0),
            'member_price' => $memberPriceAvailable ? (float)($memberPricePreview['member_price'] ?? 0) : 0.0,
            'member_price_text' => $memberPriceAvailable
                ? '¥' . number_format((float)($memberPricePreview['member_price'] ?? 0), 2, '.', '')
                : '',
            'member_price_available' => $memberPriceAvailable,
            'member_enabled' => (bool)($memberState['member_enabled'] ?? false),
            'member_visible' => (bool)($memberState['member_visible'] ?? false),
            'can_purchase' => (bool)($memberState['can_purchase'] ?? false),
            'can_renew' => (bool)($memberState['can_renew'] ?? false),
            'content_count' => count($contentList),
            'view_count' => (int)($specialData['view_count'] ?? 0),
            'buy_count' => (int)($specialData['buy_count'] ?? 0),
            'is_hot' => (int)($specialData['is_hot'] ?? 0),
            'is_member_only' => $isMemberOnly,
            'member_access' => $memberAccess,
            'is_member' => (bool)($memberState['is_member'] ?? false),
            'member_end_time' => $memberState['end_time'] ?? null,
            'access_state' => $accessState,
            'status' => (int)($specialData['status'] ?? 0),
            'audit_status' => (int)($specialData['audit_status'] ?? 0),
            'is_free' => $isFree,
            'is_purchased' => $isPurchased,
            'is_favorite' => (bool)($this->getFavoriteStatus($userId, $specialId)['is_favorite'] ?? false),
            'can_buy' => $canBuy,
            'can_learn' => $canLearn,
            'contents' => $contentList,
        ];
    }

    /**
     * 获取移动端专题关联商品。
     *
     * 专题未购买时仍返回商品，保证专题详情页的带货入口可见；
     * 商品本身的下架、审核和删除状态由商品服务过滤。
     *
     * @param int $specialId 专题ID
     * @param int $userId 当前用户ID，匿名为0
     * @param array $params 分页参数：page、limit
     * @return array 商品列表和分页信息
     * @throws AdminResponseException 专题不存在或不可访问时抛出
     */
    public function getMobileRelatedProducts(int $specialId, int $userId = 0, array $params = []): array
    {
        $displayConfig = app()->make(ContentDisplayConfigService::class);
        if (!$displayConfig->isSpecialEnabled()) {
            [$page, $limit] = $this->getPagesValue(true, true, $params, 20);
            return $this->listData([], 0, ['has_more' => false], $page, $limit)->toArray();
        }

        $special = $this->get($specialId);
        if (!$special) {
            throw new AdminResponseException('专题不存在');
        }
        if ((int)($special['status'] ?? 0) !== 1) {
            throw new AdminResponseException('专题已下架');
        }
        if ((int)($special['audit_status'] ?? 0) !== AuditStatusEnum::APPROVED) {
            throw new AdminResponseException('专题暂不可访问');
        }

        $params['user_id'] = $userId;

        return app()->make(ContentProductRelationService::class)->getMobileProductPage(
            ContentProductRelationService::RELATION_TYPE_SPECIAL,
            $specialId,
            $params
        );
    }

    /**
     * 获取移动端专题音频列表。
     *
     * @param int $specialId 专题记录标识。
     * @param int $currentId 当前播放或浏览的内容标识。
     * @return array 当前专题可播放的音频及激活项信息。
     * @throws AdminResponseException
     */
    public function getMobileAudioList(int $specialId, int $currentId = 0): array
    {
        $displayConfig = app()->make(ContentDisplayConfigService::class);
        if (!$displayConfig->isSpecialEnabled()) {
            return [
                'special_id' => $specialId,
                'title' => '',
                'total' => 0,
                'current_id' => $currentId,
                'list' => [],
            ];
        }

        $special = $this->get($specialId);
        if (!$special) {
            throw new AdminResponseException('专题不存在');
        }

        $specialData = $special->toArray();
        if ((int)($specialData['status'] ?? 0) !== 1) {
            throw new AdminResponseException('专题已下架');
        }
        if ((int)($specialData['audit_status'] ?? 0) !== AuditStatusEnum::APPROVED) {
            throw new AdminResponseException('专题暂不可访问');
        }

        $contents = app()->make(SpecialContentService::class)->getContentListWithDetail($specialId);
        $audioItems = array_values(array_filter($contents, function (array $item): bool {
            $article = is_array($item['article'] ?? null) ? $item['article'] : [];
            $articleId = (int)($item['article_id'] ?? ($article['id'] ?? 0));
            $contentType = (int)($item['type'] ?? ($article['content_type'] ?? 0));

            return $articleId > 0
                && $contentType === ContentTypeEnum::AUDIO
                && (int)($article['status'] ?? 0) === 1
                && (int)($article['audit_status'] ?? 0) === AuditStatusEnum::APPROVED;
        }));
        $total = count($audioItems);

        return [
            'special_id' => (int)($specialData['id'] ?? 0),
            'title' => (string)($specialData['title'] ?? ''),
            'total' => $total,
            'current_id' => $currentId,
            'list' => array_map(
                fn(array $item): array => $this->formatMobileAudioItem($item, $currentId, $total),
                $audioItems
            ),
        ];
    }

    /**
     * 获取专题小说章节上下文。
     *
     * @param int $specialId 专题记录标识。
     * @param int $currentArticleId 当前阅读的内容章节标识。
     * @param int $userId 当前用户标识，匿名访问时为零。
     * @param int $page 当前页码，从第一页开始。
     * @param int $limit 小说章节列表的每页数量。
     * @return array 小说章节、阅读权限、分页和前后章上下文。
     * @throws AdminResponseException
     */
    public function getMobileNovelChapterContext(
        int $specialId,
        int $currentArticleId,
        int $userId = 0,
        int $page = 1,
        int $limit = 20
    ): array
    {
        if (!app()->make(ContentDisplayConfigService::class)->isSpecialEnabled()) {
            return [];
        }

        $special = $this->get($specialId);
        if (!$special) {
            throw new AdminResponseException('专题不存在');
        }

        $specialData = $special->toArray();
        if ((int)($specialData['status'] ?? 0) !== 1) {
            throw new AdminResponseException('专题已下架');
        }
        if ((int)($specialData['audit_status'] ?? 0) !== AuditStatusEnum::APPROVED) {
            throw new AdminResponseException('专题暂不可访问');
        }

        $isPurchased = app()->make(UserPurchaseRecordService::class)
            ->hasPurchased($userId, Order::ORDER_TYPE_SPECIAL, $specialId);
        $memberState = app()->make(UserMemberService::class)->getMemberRuntimeState($userId);
        $isFree = (int)($specialData['special_type'] ?? 0) === 0 || (float)($specialData['price'] ?? 0) <= 0;
        $canLearn = $isFree || $isPurchased;
        $canBuy = !$canLearn && (int)($specialData['special_type'] ?? 0) === 1 && (float)($specialData['price'] ?? 0) > 0;

        $isMemberOnly = (int)($specialData['is_member_only'] ?? 0) === 1;
        $memberAccess = true;
        if ($isMemberOnly && $userId > 0) {
            $memberAccess = (bool)($memberState['is_member'] ?? false);
        } elseif ($isMemberOnly) {
            $memberAccess = false;
        }
        if ($isMemberOnly && !$memberAccess) {
            $canLearn = false;
            $canBuy = false;
        }

        $accessState = !$isMemberOnly || $memberAccess
            ? ($canLearn ? 'learning' : 'purchase')
            : ((bool)($memberState['member_enabled'] ?? false) ? 'member_required' : 'unavailable');

        $allowFreeChapters = !$isMemberOnly || $memberAccess;
        $chapters = $this->getMobileNovelChapters($specialId, $canLearn, $allowFreeChapters);
        $allChapters = $chapters['all'];
        $visibleChapters = $chapters['visible'];
        $currentAllIndex = $this->findChapterIndex($allChapters, $currentArticleId);
        if ($currentAllIndex < 0) {
            throw new AdminResponseException('当前小说不属于该专题');
        }

        $currentVisibleIndex = $this->findChapterIndex($visibleChapters, $currentArticleId);
        $currentChapterCanLearn = $canLearn
            || ($allowFreeChapters && $this->isMobileSpecialChapterFree($allChapters[$currentAllIndex]));
        $limit = max(1, min(20, $limit));
        $page = max(1, $page);
        if ($currentVisibleIndex >= 0) {
            $page = (int)floor($currentVisibleIndex / $limit) + 1;
        }

        $visibleTotal = count($visibleChapters);
        $totalPage = $visibleTotal > 0 ? (int)ceil($visibleTotal / $limit) : 0;
        $offset = ($page - 1) * $limit;
        $pageChapters = array_slice($visibleChapters, $offset, $limit);

        return [
            'special_id' => (int)($specialData['id'] ?? 0),
            'title' => (string)($specialData['title'] ?? ''),
            'price' => (float)($specialData['price'] ?? 0),
            'original_price' => (float)($specialData['original_price'] ?? 0),
            'is_purchased' => $isPurchased,
            'is_free' => $isFree,
            'is_member_only' => $isMemberOnly,
            'member_access' => $memberAccess,
            'is_member' => (bool)($memberState['is_member'] ?? false),
            'member_enabled' => (bool)($memberState['member_enabled'] ?? false),
            'member_visible' => (bool)($memberState['member_visible'] ?? false),
            'can_purchase' => (bool)($memberState['can_purchase'] ?? false),
            'can_renew' => (bool)($memberState['can_renew'] ?? false),
            'can_learn' => $canLearn,
            'can_buy' => $canBuy,
            'access_state' => $accessState,
            'current_article_id' => $currentArticleId,
            'current_can_learn' => $currentChapterCanLearn,
            'current_number' => $currentAllIndex + 1,
            'current_visible_number' => $currentVisibleIndex >= 0 ? $currentVisibleIndex + 1 : 0,
            'total_all' => count($allChapters),
            'total' => $visibleTotal,
            'page' => $page,
            'limit' => $limit,
            'total_page' => $totalPage,
            'has_more' => $page < $totalPage,
            'prev_chapter' => $currentVisibleIndex > 0
                ? $this->formatMobileNovelChapter($visibleChapters[$currentVisibleIndex - 1], $canLearn)
                : null,
            'next_chapter' => $currentVisibleIndex >= 0 && $currentVisibleIndex < $visibleTotal - 1
                ? $this->formatMobileNovelChapter($visibleChapters[$currentVisibleIndex + 1], $canLearn)
                : null,
            'current_chapter' => $this->formatMobileNovelChapter($allChapters[$currentAllIndex], $currentChapterCanLearn),
            'chapters' => array_map(
                fn(array $item): array => $this->formatMobileNovelChapter($item, $canLearn),
                $pageChapters
            ),
        ];
    }

    /**
     * 获取专题小说章节列表。
     *
     * @param int $specialId 专题记录标识。
     * @param bool $canLearn 当前用户是否有学习或阅读权限。
     * @param bool $allowFreeChapters 是否允许返回可免费阅读的章节。
     * @return array 包含公开完整章节列表 all 和按阅读权限筛选的章节列表 visible。
     */
    protected function getMobileNovelChapters(int $specialId, bool $canLearn, bool $allowFreeChapters): array
    {
        $contents = app()->make(SpecialContentService::class)->getContentListWithDetail($specialId);
        $allChapters = array_values(array_filter($contents, function (array $item): bool {
            $article = is_array($item['article'] ?? null) ? $item['article'] : [];
            $articleId = (int)($item['article_id'] ?? ($article['id'] ?? 0));
            $contentType = (int)($item['type'] ?? ($article['content_type'] ?? 0));

            return $articleId > 0
                && $contentType === ContentTypeEnum::NOVEL
                && (int)($article['status'] ?? 0) === 1
                && (int)($article['audit_status'] ?? 0) === AuditStatusEnum::APPROVED;
        }));

        return [
            'all' => $allChapters,
            'visible' => $canLearn
                ? $allChapters
                : ($allowFreeChapters ? array_values(array_filter(
                    $allChapters,
                    fn(array $item): bool => $this->isMobileSpecialChapterFree($item)
                )) : []),
        ];
    }

    /**
     * 判断专题章节是否可免费阅读。
     *
     * @param array $item 当前专题关联的内容及章节配置。
     * @return bool 当前章节是否免费或允许免费阅读。
     */
    protected function isMobileSpecialChapterFree(array $item): bool
    {
        $article = is_array($item['article'] ?? null) ? $item['article'] : [];
        $price = (float)($article['price'] ?? 0);

        return (int)($item['is_trial'] ?? ($article['is_trial'] ?? 0)) === 1 || $price <= 0;
    }

    /**
     * 查找章节索引。
     *
     * @param array $chapters 当前专题的章节列表。
     * @param int $articleId 内容记录标识。
     * @return int 章节在列表中的下标；未找到时为负一。
     */
    protected function findChapterIndex(array $chapters, int $articleId): int
    {
        foreach ($chapters as $index => $item) {
            $article = is_array($item['article'] ?? null) ? $item['article'] : [];
            $currentArticleId = (int)($item['article_id'] ?? ($article['id'] ?? 0));
            if ($currentArticleId === $articleId) {
                return (int)$index;
            }
        }

        return -1;
    }

    /**
     * 格式化专题小说章节项。
     *
     * @param array $item 当前专题关联的内容及章节配置。
     * @param bool $canLearn 当前用户是否有学习或阅读权限。
     * @return array 附带阅读权限和跳转信息的小说章节。
     */
    protected function formatMobileNovelChapter(array $item, bool $canLearn): array
    {
        $article = is_array($item['article'] ?? null) ? $item['article'] : [];
        $articleId = (int)($item['article_id'] ?? ($article['id'] ?? 0));
        $specialId = (int)($item['special_id'] ?? 0);

        return [
            'id' => $articleId,
            'article_id' => $articleId,
            'special_content_id' => (int)($item['id'] ?? 0),
            'special_id' => $specialId,
            'title' => (string)($article['title'] ?? ''),
            'description' => (string)($article['description'] ?? ''),
            'cover' => (string)($article['cover'] ?? ''),
            'sort' => (int)($item['sort'] ?? 0),
            'is_trial' => (int)($item['is_trial'] ?? ($article['is_trial'] ?? 0)),
            'is_free' => $this->isMobileSpecialChapterFree($item),
            'can_learn' => $canLearn || $this->isMobileSpecialChapterFree($item),
            'word_count' => (int)($article['word_count'] ?? 0),
            'chapter_count' => (int)($article['chapter_count'] ?? 0),
            'detail_url' => '/pages/contents/content/novel/index?id=' . $articleId
                . '&source=special&special_id=' . $specialId,
        ];
    }

    /**
     * 构建移动端专题列表筛选条件
     *
     * @param array $params 专题列表的筛选和分页参数。
     * @return array 移动端专题查询所需的标准筛选条件。
     */
    protected function buildMobileListFilters(array $params): array
    {
        return [
            'keyword' => trim((string)($params['keyword'] ?? '')),
            'category_id' => max(0, (int)($params['category_id'] ?? 0)),
            'merchant_id' => (int)($params['merchant_id'] ?? 0) > 0 ? (int)$params['merchant_id'] : '',
            'min_price' => $this->normalizeMobilePrice($params['min_price'] ?? null),
            'max_price' => $this->normalizeMobilePrice($params['max_price'] ?? null),
        ];
    }

    /**
     * 应用移动端专题列表排序
     *
     * @param mixed $query 待追加业务筛选或排序条件的查询对象。
     * @param string $sort 业务排序字段、方向或预定义排序规则。
     * @return mixed 已应用所选排序规则的查询对象。
     */
    protected function applyMobileListSort($query, string $sort)
    {
        if ($sort === 'sales') {
            return $query->order('buy_count', 'desc')->order('sort', 'desc')->order('id', 'desc');
        }

        if ($sort === 'latest') {
            return $query->order('create_time', 'desc')->order('id', 'desc');
        }

        return $query->order('is_hot', 'desc')->order('sort', 'desc')->order('id', 'desc');
    }

    /**
     * 归一化移动端价格筛选值
     *
     * @param mixed $value 需要校验或规范化的原始输入值。
     * @return string|float 合法的价格筛选金额；无有效筛选时为空字符串。
     */
    protected function normalizeMobilePrice(mixed $value): string|float
    {
        if ($value === '' || $value === null || !is_numeric((string)$value)) {
            return '';
        }

        return max(0, (float)$value);
    }

    /**
     * 为移动端专题列表附加会员价格展示状态。
     *
     * @param array<int, array<string, mixed>> $list 专题列表。
     * @param int $userId 用户 ID。
     * @return array<int, array<string, mixed>> 附带会员身份和会员价展示字段的专题列表。
     */
    protected function appendMobileSpecialMemberState(array $list, int $userId = 0): array
    {
        if ($list === []) {
            return [];
        }

        $memberService = app()->make(UserMemberService::class);
        return array_map(function (array $item) use ($memberService, $userId): array {
            $price = max(0, (float)($item['price'] ?? 0));
            $preview = $memberService->getMemberPricePreview(
                (int)($item['merchant_id'] ?? 0),
                $price,
                $userId
            );
            $memberPriceAvailable = (bool)($preview['member_price_available'] ?? false);
            $memberPrice = $memberPriceAvailable ? (float)($preview['member_price'] ?? 0) : 0.0;

            $item['member_price'] = $memberPrice;
            $item['member_price_text'] = $memberPriceAvailable
                ? '¥' . number_format($memberPrice, 2, '.', '')
                : '';
            $item['member_price_available'] = $memberPriceAvailable;
            $item['member_enabled'] = (bool)($preview['member_enabled'] ?? false);
            $item['member_visible'] = (bool)($preview['member_visible'] ?? false);

            return $item;
        }, $list);
    }

    /**
     * 格式化移动端专题详情内容项。
     *
     * @param array $item 当前专题关联的内容及章节配置。
     * @return array 对应内容类型的专题章节展示数据。
     */
    protected function formatMobileDetailContent(array $item): array
    {
        $article = is_array($item['article'] ?? null) ? $item['article'] : [];
        $articleId = (int)($item['article_id'] ?? ($article['id'] ?? 0));
        $price = (float)($article['price'] ?? 0);
        $isTrial = (int)($item['is_trial'] ?? ($article['is_trial'] ?? 0));

        return [
            'id' => (int)($item['id'] ?? 0),
            'special_id' => (int)($item['special_id'] ?? 0),
            'type' => (int)($item['type'] ?? ($article['content_type'] ?? 0)),
            'article_id' => $articleId,
            'sort' => (int)($item['sort'] ?? 0),
            'is_trial' => $isTrial,
            'article' => [
                'id' => $articleId,
                'title' => (string)($article['title'] ?? ''),
                'description' => (string)($article['description'] ?? ''),
                'cover' => (string)($article['cover'] ?? ''),
                'content_type' => (int)($article['content_type'] ?? 0),
                'price' => $price,
                'original_price' => (float)($article['original_price'] ?? 0),
                'view_count' => (int)($article['view_count'] ?? 0),
                'like_count' => (int)($article['like_count'] ?? 0),
                'media_duration' => (int)($article['media_duration'] ?? 0),
                'word_count' => (int)($article['word_count'] ?? 0),
                'chapter_count' => (int)($article['chapter_count'] ?? 0),
                'is_trial' => $isTrial,
                'status' => (int)($article['status'] ?? 0),
                'audit_status' => (int)($article['audit_status'] ?? 0),
            ],
        ];
    }

    /**
     * 格式化移动端专题音频列表项。
     *
     * @param array $item 当前专题关联的内容及章节配置。
     * @param int $currentId 当前播放或浏览的内容标识。
     * @param int $total 当前专题音频列表的总数量。
     * @return array 附带播放顺序、激活状态和地址的音频项。
     */
    protected function formatMobileAudioItem(array $item, int $currentId, int $total): array
    {
        $article = is_array($item['article'] ?? null) ? $item['article'] : [];
        $articleId = (int)($item['article_id'] ?? ($article['id'] ?? 0));
        $specialId = (int)($item['special_id'] ?? 0);

        return [
            'id' => $articleId,
            'article_id' => $articleId,
            'special_content_id' => (int)($item['id'] ?? 0),
            'title' => (string)($article['title'] ?? ''),
            'subtitle' => (string)($article['description'] ?? ''),
            'author' => (string)($article['author'] ?? ''),
            'cover' => (string)($article['cover'] ?? ''),
            'media_duration' => (int)($article['media_duration'] ?? 0),
            'sort' => (int)($item['sort'] ?? 0),
            'is_current' => $articleId > 0 && $articleId === $currentId,
            'detail_url' => '/pages/contents/content/aud/index?id=' . $articleId
                . '&source=special&special_id=' . $specialId
                . '&audio_count=' . $total,
        ];
    }

    /**
     * 校验专题商户归属
     *
     * @param int $merchantId 执行操作的商户标识，必须与专题归属一致。
     * @param int $id 专题记录标识。
     * @param string $label 用于校验错误提示或展示的中文名称。
     * @return void 无返回值。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function checkMerchantOwner(int $merchantId, int $id, string $label = '专题'): void
    {
        $special = $this->get($id);
        if (!$special) {
            throw new AdminResponseException($label . '不存在');
        }
        if ($special['merchant_id'] != $merchantId) {
            throw new AdminResponseException('无权操作该' . $label);
        }
    }

    /**
     * 获取专题详情（包含内容列表）
     *
     * @param int $id 专题记录标识。
     * @return array|null 专题主表和按配置排序的关联内容；不存在时为空。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getInfo(int $id): ?array
    {
        $special = $this->get($id);
        if (!$special) {
            return null;
        }

        $result = $special->toArray();
        $this->appendCategoryInfo($result);

        // 获取专题内容列表，并带上内容详情
        $contents = app()->make(SpecialContentService::class)
            ->getContentListWithDetail($id);
        $result['contents'] = $contents;
        $result['content_count'] = count($contents);

        return app()->make(ContentProductRelationService::class)->attachProductsToDetail(
            app()->make(TagService::class)->attachTagsToDetail($result, TagService::RELATION_TYPE_SPECIAL),
            ContentProductRelationService::RELATION_TYPE_SPECIAL,
            $id
        );
    }

    /**
     * 为专题详情附加所属分类名称。
     *
     * @param array $special 专题详情数据，会补充 category_name 与 category 字段。
     * @return void 无返回值。
     */
    protected function appendCategoryInfo(array &$special): void
    {
        $categoryId = (int)($special['category_id'] ?? 0);
        $special['category_name'] = '';
        $special['category'] = null;
        if ($categoryId <= 0) {
            return;
        }

        $category = app()->make(CategoryService::class)->get($categoryId);
        if (!$category) {
            return;
        }

        $categoryData = $category->visible(['id', 'name'])->toArray();
        $special['category_name'] = (string)($categoryData['name'] ?? '');
        $special['category'] = $categoryData;
    }

    /**
     * 读取专题详情
     *
     * @param int|array $id 专题标识，按整数标识处理；联合类型仅沿用资源接口签名。
     * @return array 专题详情和关联内容。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function resourceRead(int|array $id): array
    {
        $info = $this->getInfo((int)$id);
        if ($info === null) {
            throw new ModelNotFoundException('查询的数据不存在');
        }

        return $info;
    }

    /**
     * 新增专题
     *
     * @param array $data 专题主表、标签及关联内容配置。
     * @return array 新增后的专题详情。
     * @throws AdminResponseException
     */
    public function resourceSave(array $data): array
    {
        [$tagIds, $syncTags] = $this->pullTagIds($data);
        [$productIds, $syncProducts] = $this->pullProductIds($data);
        $result = $this->saveSpecialWithContents($data, $syncProducts ? $productIds : null);
        $specialId = (int)($result['id'] ?? 0);
        if ($syncTags && $specialId > 0) {
            $ownerId = (int)($result['merchant_id'] ?? ($data['merchant_id'] ?? 0));
            app()->make(TagRelationService::class)->syncRelation(
                TagService::RELATION_TYPE_SPECIAL,
                $specialId,
                $tagIds,
                $ownerId,
                TagService::TAG_TYPE_CONTENT
            );
            $result = $this->resourceRead($specialId);
        }
        return $result;
    }

    /**
     * 更新专题
     *
     * @param int|array $id 专题标识，按整数标识处理；联合类型仅沿用资源接口签名。
     * @param array $data 专题主表、标签及关联内容配置。
     * @return bool 更新成功时返回真；失败时抛出业务异常。
     * @throws AdminResponseException
     */
    public function resourceUpdate(int|array $id, array $data)
    {
        [$tagIds, $syncTags] = $this->pullTagIds($data);
        [$productIds, $syncProducts] = $this->pullProductIds($data);
        $current = $this->get((int)$id);
        if (!$current) {
            throw new AdminResponseException('专题不存在');
        }
        $currentOwnerId = (int)($current['merchant_id'] ?? 0);
        // 平台端允许传入 merchant_id；商户端请求白名单不包含该字段，始终保持当前商户。
        $ownerId = array_key_exists('merchant_id', $data) ? (int)$data['merchant_id'] : $currentOwnerId;
        if ($ownerId !== $currentOwnerId && !$syncProducts) {
            throw new AdminResponseException('变更专题所属商户时请重新选择关联商品');
        }
        $this->updateSpecialWithContents(
            (int)$id,
            $data,
            $syncProducts ? $productIds : null,
            $ownerId
        );
        if ($syncTags) {
            $special = $this->get((int)$id);
            $ownerId = $special ? (int)($special['merchant_id'] ?? 0) : 0;
            app()->make(TagRelationService::class)->syncRelation(
                TagService::RELATION_TYPE_SPECIAL,
                (int)$id,
                $tagIds,
                $ownerId,
                TagService::TAG_TYPE_CONTENT
            );
        }
        return true;
    }

    /**
     * 删除专题
     *
     * @param int $id 专题记录标识。
     * @return bool 专题及其内容关联是否删除成功。
     */
    public function resourceDelete(int $id): bool
    {
        return $this->deleteSpecialWithContents($id);
    }

    /**
     * 创建专题并绑定内容
     *
     * @param array $data 专题主表、标签及关联内容配置。
     * @param array|null $productIds 关联商品ID；null 表示不处理关联。
     * @return array 已创建并绑定内容的专题详情。
     * @throws AdminResponseException
     */
    protected function saveSpecialWithContents(array $data, ?array $productIds = null): array
    {
        $this->validateSpecialCategoryRequired($data);
        $this->validateSpecialCoverRequired($data);

        if (($data['merchant_id'] ?? 0) == 0) {
            $data['audit_status'] = 1;
        } else {
            $data['audit_status'] = 0;
        }

        $contents = $data['contents'] ?? [];
        unset($data['contents']);

        // 付费专题必须填写价格
        if (($data['special_type'] ?? 0) == 1 && empty($data['price'])) {
            throw new AdminResponseException('付费专题必须设置价格');
        }

        // 对文字字段执行敏感词过滤
        $data = $this->filterSensitiveFields($data, ['title', 'description', 'keywords']);

        return $this->transaction(function () use ($data, $contents, $productIds) {
            $special = $this->create($data);
            $specialId = (int)($special['id'] ?? 0);

            if ($specialId > 0 && $contents) {
                $this->syncContents($specialId, $contents);
                $this->update($specialId, ['content_count' => count($contents)]);
            }

            if ($productIds !== null && $specialId > 0) {
                app()->make(ContentProductRelationService::class)->syncRelation(
                    ContentProductRelationService::RELATION_TYPE_SPECIAL,
                    $specialId,
                    $productIds,
                    (int)($data['merchant_id'] ?? 0)
                );
            }

            $result = $this->getInfo($specialId) ?? [];
            if ((int)($result['merchant_id'] ?? 0) > 0) {
                app()->make(BackendMessageEventService::class)->auditSubmitted(
                    BackendMessageService::SUB_TYPE_SPECIAL_AUDIT,
                    'special',
                    $specialId,
                    (int)$result['merchant_id'],
                    (string)($result['title'] ?? '专题')
                );
            }

            return $result;
        });
    }

    /**
     * 更新专题并同步内容
     *
     * @param int $id 专题记录标识。
     * @param array $data 专题主表、标签及关联内容配置。
     * @param array|null $productIds 关联商品ID；null 表示不处理关联。
     * @param int $ownerId 专题归属：0=平台，大于0=商户ID。
     * @return void 无返回值。
     * @throws AdminResponseException
     */
    protected function updateSpecialWithContents(int $id, array $data, ?array $productIds = null, int $ownerId = 0): void
    {
        $this->validateSpecialCategoryRequired($data);
        $this->validateSpecialCoverRequired($data);

        $contents = $data['contents'] ?? null;
        unset($data['contents']);

        // 付费专题必须填写价格
        if (isset($data['special_type']) && $data['special_type'] == 1 && empty($data['price'])) {
            throw new AdminResponseException('付费专题必须设置价格');
        }

        // 对文字字段执行敏感词过滤
        $data = $this->filterSensitiveFields($data, ['title', 'description', 'keywords']);

        $this->transaction(function () use ($id, $data, $contents, $productIds, $ownerId) {
            $current = $this->get($id);
            if ($current && (int)($current['merchant_id'] ?? 0) > 0) {
                $data['audit_status'] = 0;
                $data['audit_remark'] = '';
            }
            $this->update($id, $data);

            // contents 为 null 表示不修改内容列表
            if ($contents !== null) {
                $this->syncContents($id, $contents);
                $this->update($id, ['content_count' => count($contents)]);
            }

            if ($productIds !== null) {
                app()->make(ContentProductRelationService::class)->syncRelation(
                    ContentProductRelationService::RELATION_TYPE_SPECIAL,
                    $id,
                    $productIds,
                    $ownerId
                );
            }

            $fresh = $this->get($id);
            if ($fresh && (int)($fresh['merchant_id'] ?? 0) > 0) {
                app()->make(BackendMessageEventService::class)->auditSubmitted(
                    BackendMessageService::SUB_TYPE_SPECIAL_AUDIT,
                    'special',
                    $id,
                    (int)$fresh['merchant_id'],
                    (string)($fresh['title'] ?? '专题')
                );
            }
        });
    }

    /**
     * 删除专题及关联内容
     *
     * @param int $id 专题记录标识。
     * @return bool 专题及其内容关联是否删除成功。
     */
    protected function deleteSpecialWithContents(int $id): bool
    {
        return (bool)$this->transaction(function () use ($id) {
            $deleted = $this->delete($id);
            app()->make(SpecialContentService::class)->deleteBySpecialId($id);
            app()->make(TagRelationService::class)->deleteByRelation(TagService::RELATION_TYPE_SPECIAL, $id);
            app()->make(ContentProductRelationService::class)->deleteByRelation(
                ContentProductRelationService::RELATION_TYPE_SPECIAL,
                $id
            );

            return $deleted;
        });
    }

    /**
     * 提取标签 ID，避免写入专题主表。
     *
     * @param array $data 专题保存数据，传入后会移除 tag_ids。
     * @return array{0: array, 1: bool} 第一个值为标签 ID 数组，第二个值表示请求中是否包含 tag_ids。
     */
    protected function pullTagIds(array &$data): array
    {
        $exists = array_key_exists('tag_ids', $data);
        $tagIds = $exists && is_array($data['tag_ids']) ? $data['tag_ids'] : [];
        unset($data['tag_ids']);

        return [$tagIds, $exists];
    }

    /**
     * 提取关联商品ID，避免写入专题主表。
     *
     * null 表示本次请求未提交商品关联（编辑时保持原关联），空数组表示清空。
     *
     * @param array $data 专题保存数据，传入后会移除 product_ids。
     * @return array{0: array, 1: bool} 第一个值为商品ID数组，第二个值表示请求中是否提交了商品关联。
     * @throws AdminResponseException 商品关联参数不是数组时抛出
     */
    protected function pullProductIds(array &$data): array
    {
        $productIds = $data['product_ids'] ?? null;
        unset($data['product_ids']);

        if ($productIds === null) {
            return [[], false];
        }
        if (!is_array($productIds)) {
            throw new AdminResponseException('关联商品参数格式不正确');
        }

        return [$productIds, true];
    }

    /**
     * 校验专题分类必选且来源于平台专题分类。
     *
     * @param array $data 专题表单数据。
     * @return void 无返回值。
     * @throws AdminResponseException
     */
    protected function validateSpecialCategoryRequired(array $data): void
    {
        app()->make(SpecialCategoryService::class)->assertPlatformCategory((int)($data['category_id'] ?? 0));
    }

    /**
     * 校验专题封面必填。
     *
     * @param array $data 专题表单数据。
     * @return void 无返回值。
     * @throws AdminResponseException
     */
    protected function validateSpecialCoverRequired(array $data): void
    {
        if (trim((string)($data['cover'] ?? '')) === '') {
            throw new AdminResponseException('请上传专题封面');
        }
    }

    /**
     * 增加专题购买数
     *
     * @param int $specialId 专题记录标识。
     * @param int $buyCount 本次增加的购买数量。
     * @return void 无返回值。
     */
    public function incrementBuyCount(int $specialId, int $buyCount = 1): void
    {
        if ($specialId <= 0 || $buyCount <= 0) {
            return;
        }

        $this->getModel()->where('id', $specialId)->inc('buy_count', $buyCount)->update();
    }

    /**
     * 审核专题（供平台端使用，直接更新审核字段）
     *
     * @param int $id 专题ID
     * @param int $auditStatus 审核状态
     * @param string $remark 审核备注
     * @param int $adminId 管理员ID
     * @return void 无返回值。
     * @throws AdminResponseException
     */
    public function auditSpecial(int $id, int $auditStatus, string $remark = '', int $adminId = 0): void
    {
        $special = $this->get($id);
        if (!$special) {
            throw new AdminResponseException('专题不存在');
        }
        if (!$this->hasAuditFields()) {
            throw new AdminResponseException('当前专题表缺少审核字段，请先执行数据库升级');
        }
        if (!array_key_exists($auditStatus, AuditStatusEnum::list())) {
            throw new AdminResponseException('审核状态不正确');
        }
        if (AuditStatusEnum::isViolation($auditStatus) && trim($remark) === '') {
            throw new AdminResponseException('驳回或下架时请填写审核备注');
        }

        $updateData = [
            'audit_status' => $auditStatus,
            'audit_remark' => AuditStatusEnum::isViolation($auditStatus) ? trim($remark) : '',
        ];
        $tableFields = $special->getTableFields();
        if (in_array('audit_time', $tableFields, true)) {
            $updateData['audit_time'] = date('Y-m-d H:i:s');
        }
        if (in_array('audit_admin_id', $tableFields, true)) {
            $updateData['audit_admin_id'] = $adminId;
        }
        if (AuditStatusEnum::isViolation($auditStatus) && in_array('status', $tableFields, true)) {
            $updateData['status'] = 0;
        }

        $this->update($id, $updateData);
        app()->make(BackendMessageEventService::class)->auditResult(
            BackendMessageService::SUB_TYPE_SPECIAL_AUDIT,
            'special',
            $id,
            (int)($special['merchant_id'] ?? 0),
            $auditStatus,
            trim($remark),
            (string)($special['title'] ?? '专题')
        );
    }

    /**
     * 批量审核专题。
     *
     * @param array $ids 待查询或审核的专题标识集合。
     * @param int $auditStatus 目标审核状态，须符合模块的审核枚举。
     * @param string $remark 审核或操作备注。
     * @param int $adminId 执行操作的后台管理员标识。
     * @return void 无返回值。
     */
    public function batchAuditSpecial(array $ids, int $auditStatus, string $remark = '', int $adminId = 0): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        foreach ($ids as $id) {
            if ($id > 0) {
                $this->auditSpecial($id, $auditStatus, $remark, $adminId);
            }
        }
    }

    /**
     * 获取专题待审核数量。
     *
     * @return int 当前待审核专题数量。
     */
    public function getPendingAuditCount(): int
    {
        if (!$this->hasAuditFields()) {
            return 0;
        }

        return (int)$this->getModel()
            ->whereNull('delete_time')
            ->where('merchant_id', '>', 0)
            ->where('audit_status', AuditStatusEnum::PENDING)
            ->count();
    }

    /**
     * 当前专题表是否已具备审核字段。
     *
     * @return bool 专题表是否已具备审核字段。
     */
    public function hasAuditFields(): bool
    {
        $tableFields = $this->getModel()->getTableFields();

        return in_array('merchant_id', $tableFields, true)
            && in_array('audit_status', $tableFields, true);
    }

    /**
     * 获取专题收藏状态。
     *
     * @param int $userId 当前用户标识，匿名访问时为零。
     * @param int $specialId 专题记录标识。
     * @return array 专题标识和当前用户的收藏状态。
     */
    public function getFavoriteStatus(int $userId, int $specialId): array
    {
        $displayConfig = app()->make(ContentDisplayConfigService::class);
        if (!$displayConfig->isSpecialEnabled()) {
            return [
                'special_id' => $specialId,
                'is_favorite' => false,
            ];
        }

        if ($userId <= 0 || $specialId <= 0) {
            return [
                'special_id' => $specialId,
                'is_favorite' => false,
            ];
        }

        return [
            'special_id' => $specialId,
            'is_favorite' => app()->make(UserFavoriteService::class)->isFavorite(
                $userId,
                UserFavoriteService::TARGET_TYPE_SPECIAL,
                $specialId
            ),
        ];
    }

    /**
     * 切换专题收藏状态。
     *
     * @param int $userId 执行收藏操作的已登录用户标识。
     * @param int $specialId 专题记录标识。
     * @return array 切换后的专题收藏状态。
     * @throws AdminResponseException
     */
    public function toggleFavorite(int $userId, int $specialId): array
    {
        if (!app()->make(ContentDisplayConfigService::class)->isSpecialEnabled()) {
            throw new AdminResponseException('专题功能已关闭');
        }

        $special = $this->requireExistingSpecial($specialId);
        $result = app()->make(UserFavoriteService::class)->toggleFavorite(
            $userId,
            UserFavoriteService::TARGET_TYPE_SPECIAL,
            $specialId,
            (int)($special['merchant_id'] ?? 0)
        );

        return [
            'special_id' => $specialId,
            'is_favorite' => (bool)($result['is_favorite'] ?? false),
            'status' => (int)($result['status'] ?? 0),
        ];
    }

    /**
     * 记录专题预览行为。
     *
     * @param int $userId 当前用户标识，匿名访问时为零。
     * @param int $specialId 专题记录标识。
     * @return array 是否记录用户浏览、专题标识及累计预览次数。
     */
    public function recordPreview(int $userId, int $specialId): array
    {
        if (!app()->make(ContentDisplayConfigService::class)->isSpecialEnabled()) {
            return [
                'recorded' => false,
                'special_id' => $specialId,
                'preview_count' => 0,
            ];
        }

        $special = $this->get($specialId);
        if (!$special) {
            return [
                'recorded' => false,
                'special_id' => $specialId,
                'preview_count' => 0,
            ];
        }

        $specialData = $special->toArray();
        $this->incrementViewCount($specialId);

        if ($userId <= 0) {
            return [
                'recorded' => false,
                'special_id' => $specialId,
                'preview_count' => 0,
            ];
        }

        $result = app()->make(UserBrowseRecordService::class)->recordBrowse(
            $userId,
            UserBrowseRecordService::TARGET_TYPE_SPECIAL,
            $specialId,
            (int)($specialData['merchant_id'] ?? 0)
        );

        return [
            'recorded' => (bool)($result['recorded'] ?? false),
            'special_id' => $specialId,
            'preview_count' => (int)($result['browse_count'] ?? 0),
        ];
    }

    /**
     * 获取专题推荐列表。
     *
     * @param int $userId 当前用户标识，匿名访问时为零。
     * @param array $params 推荐数量及需要排除的专题标识配置。
     * @return array 按推荐规则组装的专题列表及统一分页字段。
     */
    public function getRecommendList(int $userId, array $params = []): array
    {
        $limit = max(1, min(12, (int)($params['limit'] ?? 4)));
        if (!app()->make(ContentDisplayConfigService::class)->isSpecialEnabled()) {
            return $this->listData([], 0, [], 1, $limit)->toArray();
        }

        $excludeIds = $this->normalizeIds($params['exclude_ids'] ?? []);
        $recommendList = $userId > 0
            ? $this->loadPersonalizedRecommendSpecials($userId, $excludeIds, $limit)
            : [];

        if (count($recommendList) < $limit) {
            $filledIds = array_column($recommendList, 'id');
            $recommendList = array_merge(
                $recommendList,
                $this->loadHotRecommendSpecials(array_merge($excludeIds, $filledIds), $limit - count($recommendList))
            );
        }

        $formattedList = array_map(fn(array $special): array => $this->formatRecommendSpecial($special), array_slice($recommendList, 0, $limit));

        return $this->listData($formattedList, count($formattedList), [], 1, $limit)->toArray();
    }

    /**
     * 获取可购买专题。
     *
     * @param int $specialId 专题记录标识。
     * @return array 通过上架与审核校验的可购买专题。
     * @throws AdminResponseException
     */
    public function findAvailableById(int $specialId): array
    {
        if (!app()->make(ContentDisplayConfigService::class)->isSpecialEnabled()) {
            throw new AdminResponseException('专题功能已关闭');
        }

        $special = $this->get($specialId);
        if (!$special) {
            throw new AdminResponseException('专题不存在');
        }

        $specialData = $special->toArray();
        if ((int)($specialData['status'] ?? 0) !== 1) {
            throw new AdminResponseException('专题已下架');
        }
        if ((int)($specialData['audit_status'] ?? 0) !== AuditStatusEnum::APPROVED) {
            throw new AdminResponseException('专题暂不可购买');
        }
        if ((float)($specialData['price'] ?? 0) <= 0) {
            throw new AdminResponseException('免费专题无需购买');
        }

        return $specialData;
    }

    /**
     * 按 ID 获取专题映射。
     *
     * @param array $specialIds 专题记录标识集合。
     * @return array 以专题标识为键的专题记录映射。
     */
    public function getMapByIds(array $specialIds): array
    {
        if (!app()->make(ContentDisplayConfigService::class)->isSpecialEnabled()) {
            return [];
        }

        $specialIds = $this->normalizeIds($specialIds);
        if ($specialIds === []) {
            return [];
        }

        $list = $this->getModel()->whereIn('id', $specialIds)->select()->toArray();
        $result = [];
        foreach ($list as $special) {
            $specialId = (int)($special['id'] ?? 0);
            if ($specialId > 0) {
                $result[$specialId] = $special;
            }
        }

        return $result;
    }

    /**
     * 增加专题浏览量。
     *
     * @param int $specialId 专题记录标识。
     * @param int $step 本次增加的真实浏览次数。
     * @return void 无返回值。
     */
    public function incrementViewCount(int $specialId, int $step = 1): void
    {
        if ($specialId <= 0 || $step <= 0) {
            return;
        }

        $this->getModel()->where('id', $specialId)->inc('view_count', $step)->update();
    }

    /**
     * 加载个性化专题推荐。
     *
     * @param int $userId 当前用户标识，匿名访问时为零。
     * @param array $excludeIds 本次查询需要排除的专题标识集合。
     * @param int $limit 个性化推荐结果的最大数量。
     * @return array 按用户画像筛选并排序的推荐专题。
     */
    protected function loadPersonalizedRecommendSpecials(int $userId, array $excludeIds, int $limit): array
    {
        $profile = $this->buildRecommendProfile($userId);
        if (($profile['source_special_ids'] ?? []) === [] || (!$profile['category_scores'] && !$profile['keyword_scores'])) {
            return [];
        }

        $blockedIds = $this->normalizeIds(array_merge($excludeIds, $profile['source_special_ids']));
        $categoryIds = array_keys($profile['category_scores']);
        $keywordTerms = array_keys($profile['keyword_scores']);
        $candidateLimit = max($limit * 4, 24);
        $query = $this->buildRecommendBaseQuery($blockedIds);

        $query->where(function ($subQuery) use ($categoryIds, $keywordTerms) {
            $hasCondition = false;

            if ($categoryIds !== []) {
                $subQuery->whereIn('category_id', $categoryIds);
                $hasCondition = true;
            }

            foreach ($keywordTerms as $term) {
                if ($term === '') {
                    continue;
                }

                $callback = function ($keywordQuery) use ($term) {
                    $keywordQuery->whereLike('title', '%' . $term . '%')
                        ->whereOr('keywords', 'like', '%' . $term . '%')
                        ->whereOr('description', 'like', '%' . $term . '%');
                };

                if ($hasCondition) {
                    $subQuery->whereOr($callback);
                } else {
                    $subQuery->where($callback);
                    $hasCondition = true;
                }
            }
        });

        $candidates = $query
            ->field($this->getRecommendFields())
            ->limit($candidateLimit)
            ->select()
            ->toArray();

        return $this->sortRecommendSpecials($candidates, $profile, $limit);
    }

    /**
     * 加载公共热门专题推荐。
     *
     * @param array $excludeIds 本次查询需要排除的专题标识集合。
     * @param int $limit 热门推荐补位需要的专题数量。
     * @return array 排除指定标识后的公共热门专题。
     */
    protected function loadHotRecommendSpecials(array $excludeIds, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        return $this->buildRecommendBaseQuery($excludeIds)
            ->field($this->getRecommendFields())
            ->order('is_hot', 'desc')
            ->order('buy_count', 'desc')
            ->order('view_count', 'desc')
            ->order('sort', 'desc')
            ->order('id', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();
    }

    /**
     * 构建专题推荐查询基础条件。
     *
     * @param array $excludeIds 本次查询需要排除的专题标识集合。
     * @return mixed 限定已启用且审核通过的专题查询对象。
     */
    protected function buildRecommendBaseQuery(array $excludeIds = [])
    {
        return $this->getModel()
            ->where('status', 1)
            ->where('audit_status', AuditStatusEnum::APPROVED)
            ->when($excludeIds !== [], function ($query) use ($excludeIds) {
                $query->whereNotIn('id', $excludeIds);
            });
    }

    /**
     * 构建用户推荐画像。
     *
     * @param int $userId 当前用户标识，匿名访问时为零。
     * @return array 汇总来源专题、分类得分和关键词得分的用户画像。
     */
    protected function buildRecommendProfile(int $userId): array
    {
        $profile = [
            'source_special_ids' => [],
            'category_scores' => [],
            'keyword_scores' => [],
            'special_type_scores' => [],
            'paid_score' => 0,
            'free_score' => 0,
        ];

        $purchasedIds = app()->make(\app\common\service\order\UserPurchaseRecordService::class)
            ->getUserPurchasedSpecialIds($userId, 20);
        $favoriteIds = app()->make(UserFavoriteService::class)
            ->getUserFavoriteTargetIds($userId, UserFavoriteService::TARGET_TYPE_SPECIAL, 20);
        $cartIds = app()->make(\app\common\service\order\CartService::class)
            ->getUserSpecialCartIds($userId, 20);
        $previewRecords = app()->make(UserBrowseRecordService::class)
            ->getUserBrowseRecords($userId, UserBrowseRecordService::TARGET_TYPE_SPECIAL, 20);

        $sourceSpecialIds = $this->normalizeIds(array_merge(
            $purchasedIds,
            $favoriteIds,
            $cartIds,
            array_column($previewRecords, 'target_id')
        ));
        if ($sourceSpecialIds === []) {
            return $profile;
        }

        $specialMap = $this->getMapByIds($sourceSpecialIds);
        foreach ($purchasedIds as $specialId) {
            $this->accumulateRecommendProfile($profile, $specialMap[(int)$specialId] ?? [], 8);
        }
        foreach ($favoriteIds as $specialId) {
            $this->accumulateRecommendProfile($profile, $specialMap[(int)$specialId] ?? [], 6);
        }
        foreach ($cartIds as $specialId) {
            $this->accumulateRecommendProfile($profile, $specialMap[(int)$specialId] ?? [], 4);
        }
        foreach ($previewRecords as $record) {
            $specialId = (int)($record['target_id'] ?? 0);
            $previewWeight = min(3, max(1, (int)($record['browse_count'] ?? 1)));
            $this->accumulateRecommendProfile($profile, $specialMap[$specialId] ?? [], $previewWeight);
        }

        $profile['source_special_ids'] = $sourceSpecialIds;

        return $profile;
    }

    /**
     * 累加推荐画像。
     *
     * @param array $profile 按引用累加分类和关键词得分的推荐画像。
     * @param array $special 当前专题的记录数据。
     * @param int $weight 该记录对推荐画像的累计权重。
     * @return void 无返回值。
     */
    protected function accumulateRecommendProfile(array &$profile, array $special, int $weight): void
    {
        if ($special === [] || $weight <= 0) {
            return;
        }

        $categoryId = (int)($special['category_id'] ?? 0);
        if ($categoryId > 0) {
            $profile['category_scores'][$categoryId] = ($profile['category_scores'][$categoryId] ?? 0) + $weight;
        }

        $specialType = (int)($special['special_type'] ?? 0);
        $profile['special_type_scores'][$specialType] = ($profile['special_type_scores'][$specialType] ?? 0) + $weight;
        if ($specialType === 1 || (float)($special['price'] ?? 0) > 0) {
            $profile['paid_score'] += $weight;
        } else {
            $profile['free_score'] += $weight;
        }

        foreach ($this->extractRecommendKeywords($special) as $keyword) {
            $profile['keyword_scores'][$keyword] = ($profile['keyword_scores'][$keyword] ?? 0) + $weight;
        }
    }

    /**
     * 提取专题推荐关键词。
     *
     * @param array $special 当前专题的记录数据。
     * @return array 用于画像匹配的专题关键词集合。
     */
    protected function extractRecommendKeywords(array $special): array
    {
        $segments = [
            (string)($special['keywords'] ?? ''),
            (string)($special['title'] ?? ''),
            (string)($special['description'] ?? ''),
        ];

        $keywords = [];
        foreach ($segments as $segment) {
            $parts = preg_split('/[，,、\s\-|\/]+/u', trim($segment), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($parts as $part) {
                $keyword = trim((string)$part);
                if ($keyword === '' || mb_strlen($keyword) < 2) {
                    continue;
                }
                $keywords[$keyword] = $keyword;
            }
        }

        return array_values($keywords);
    }

    /**
     * 对专题推荐结果按画像打分排序。
     *
     * @param array $candidates 待按用户画像评分的候选专题。
     * @param array $profile 用户偏好或推荐画像数据。
     * @param int $limit 排序后最终保留的推荐专题数量。
     * @return array 按画像得分排序并截取数量的推荐专题。
     */
    protected function sortRecommendSpecials(array $candidates, array $profile, int $limit): array
    {
        if ($candidates === []) {
            return [];
        }

        usort($candidates, function (array $left, array $right) use ($profile) {
            $leftScore = $this->calculateRecommendScore($left, $profile);
            $rightScore = $this->calculateRecommendScore($right, $profile);
            if ($leftScore === $rightScore) {
                return ((int)($right['id'] ?? 0)) <=> ((int)($left['id'] ?? 0));
            }

            return $rightScore <=> $leftScore;
        });

        return array_slice($candidates, 0, $limit);
    }

    /**
     * 计算专题推荐得分。
     *
     * @param array $special 当前专题的记录数据。
     * @param array $profile 用户偏好或推荐画像数据。
     * @return float 当前专题与用户画像的匹配得分。
     */
    protected function calculateRecommendScore(array $special, array $profile): float
    {
        $score = 0.0;
        $categoryId = (int)($special['category_id'] ?? 0);
        $score += (float)(($profile['category_scores'][$categoryId] ?? 0) * 3);

        foreach ($this->extractRecommendKeywords($special) as $keyword) {
            $score += (float)(($profile['keyword_scores'][$keyword] ?? 0) * 1.5);
        }

        $specialType = (int)($special['special_type'] ?? 0);
        $score += (float)(($profile['special_type_scores'][$specialType] ?? 0) * 1.2);

        $isPaid = $specialType === 1 || (float)($special['price'] ?? 0) > 0;
        $score += $isPaid
            ? (float)($profile['paid_score'] ?? 0)
            : (float)($profile['free_score'] ?? 0);

        $score += (int)($special['is_hot'] ?? 0) === 1 ? 3.0 : 0.0;
        $score += min(6, ((int)($special['buy_count'] ?? 0)) / 20);
        $score += min(4, ((int)($special['view_count'] ?? 0)) / 50);
        $score += min(2, ((int)($special['content_count'] ?? 0)) / 5);

        return $score;
    }

    /**
     * 格式化专题推荐项。
     *
     * @param array $special 当前专题的记录数据。
     * @return array 用于移动端展示的推荐专题项。
     */
    protected function formatRecommendSpecial(array $special): array
    {
        $price = max(0, (float)($special['price'] ?? 0));
        $isFree = (int)($special['special_type'] ?? 0) === 0 || $price <= 0;
        $tags = [];

        if ((int)($special['is_hot'] ?? 0) === 1) {
            $tags[] = ['text' => '热门', 'type' => 'hot'];
        }

        $tags[] = [
            'text' => $isFree ? '免费' : '付费',
            'type' => $isFree ? 'free' : 'paid',
        ];

        return [
            'id' => (int)($special['id'] ?? 0),
            'category_id' => (int)($special['category_id'] ?? 0),
            'cover' => trim((string)($special['cover'] ?? '')),
            'title' => trim((string)($special['title'] ?? '')),
            'description' => trim((string)($special['description'] ?? '')),
            'price' => $price,
            'price_text' => $isFree ? '免费' : '¥' . number_format($price, 2, '.', ''),
            'tags' => $tags,
        ];
    }

    /**
     * 格式化商家首页推荐专题项。
     *
     * @param array $special 专题记录
     * @return array 用于商户首页展示的推荐专题项。
     */
    protected function formatMerchantHomeSpecial(array $special): array
    {
        $price = max(0, (float)($special['price'] ?? 0));
        $isFree = (int)($special['special_type'] ?? 0) === 0 || $price <= 0;
        $tags = [];

        if ((int)($special['is_hot'] ?? 0) === 1) {
            $tags[] = ['text' => '热门', 'type' => 'hot'];
        }

        $tags[] = [
            'text' => $isFree ? '免费' : '付费',
            'type' => $isFree ? 'free' : 'paid',
        ];

        return [
            'id' => (int)($special['id'] ?? 0),
            'category_id' => (int)($special['category_id'] ?? 0),
            'cover' => trim((string)($special['cover'] ?? '')),
            'title' => trim((string)($special['title'] ?? '')),
            'description' => trim((string)($special['description'] ?? '')),
            'price' => $price,
            'price_text' => $isFree ? '免费' : '¥' . number_format($price, 2, '.', ''),
            'tags' => $tags,
            'view_count' => (int)($special['view_count'] ?? 0),
            'buy_count' => (int)($special['buy_count'] ?? 0),
            'content_count' => (int)($special['content_count'] ?? 0),
        ];
    }

    /**
     * 获取专题推荐字段。
     *
     * @return array 专题推荐查询所需的字段集合。
     */
    protected function getRecommendFields(): array
    {
        return [
            'id', 'category_id', 'title', 'keywords', 'description', 'cover',
            'special_type', 'price', 'content_count', 'view_count', 'buy_count', 'is_hot', 'sort'
        ];
    }

    /**
     * 标准化专题ID列表。
     *
     * @param array $ids 待查询或审核的专题标识集合。
     * @return array 去重并保留有效顺序的正整数标识集合。
     */
    protected function normalizeIds(array $ids): array
    {
        return array_values(array_filter(array_unique(array_map('intval', $ids)), static fn(int $id): bool => $id > 0));
    }

    /**
     * 获取存在的专题。
     *
     * @param int $specialId 专题记录标识。
     * @return array 已存在的专题记录；不存在时抛出异常。
     * @throws AdminResponseException
     */
    protected function requireExistingSpecial(int $specialId): array
    {
        $special = $this->get($specialId);
        if (!$special) {
            throw new AdminResponseException('专题不存在');
        }

        return $special->toArray();
    }

    /**
     * 同步专题内容（先删除旧的，再写入新的）
     *
     * @param int $specialId 专题记录标识。
     * @param array $contents 专题关联内容及其排序配置。
     * @return void 无返回值。
     * @throws AdminResponseException
     */
    protected function syncContents(int $specialId, array $contents): void
    {
        $specialContentService = app()->make(SpecialContentService::class);
        $articleService = app()->make(ArticleService::class);

        $articleIds = array_column($contents, 'article_id');
        $articleIds = array_values(array_unique(array_filter(array_map('intval', $articleIds))));

        if ($articleIds !== []) {
            $existCount = $articleService->countEnabledByIds($articleIds);
            if ($existCount !== count($articleIds)) {
                throw new AdminResponseException('内容列表中包含不存在或已禁用的内容');
            }
        }

        $specialContentService->replaceBySpecialId($specialId, $contents);
    }
}
