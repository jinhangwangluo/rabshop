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

namespace app\common\service\search;

use app\common\enum\ProductEnum;
use app\common\model\search\SearchKeywordStat;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\content\ArticleService;
use app\common\service\content\ContentDisplayConfigService;
use app\common\service\content\SpecialService;
use app\common\service\merchant\MerchantService;
use app\common\service\store\StoreProductService;

/**
 * 移动端统一搜索服务.
 */
class MobileSearchService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 设置对应模型.
     */
    protected function setModel(): string
    {
        return SearchKeywordStat::class;
    }

    /**
     * 获取搜索默认态数据.
     *
     * @param int $userId 用户ID.
     * @param array $params 查询参数.
     * @return array
     */
    public function getMeta(int $userId = 0, array $params = []): array
    {
        $limit = max(1, min(20, (int)($params['limit'] ?? 10)));

        return [
            'history' => app()->make(UserSearchHistoryService::class)->getRecentKeywords($userId, 10),
            'hot_keywords' => app()->make(SearchKeywordStatService::class)->getHotKeywords($limit),
            'discover' => $this->getDiscoverItems($limit),
        ];
    }

    /**
     * 执行统一搜索.
     *
     * @param array $params 查询参数.
     * @return array
     */
    public function search(array $params = []): array
    {
        $keyword = $this->normalizeKeyword((string)($params['keyword'] ?? ($params['search'] ?? '')));
        $type = $this->normalizeSearchType((string)($params['type'] ?? 'all'));
        $page = max(1, (int)($params['page'] ?? 1));
        $limit = max(1, min(30, (int)($params['limit'] ?? 10)));
        $sort = trim((string)($params['sort'] ?? 'default'));

        if ($keyword === '') {
            return $this->emptySearchResult($page, $limit, $type);
        }

        $fetchLimit = max($limit, $page * $limit);
        $displayConfig = app()->make(ContentDisplayConfigService::class);
        $goodsData = $this->searchProducts($keyword, 1, $fetchLimit, $sort, $params);
        $specialData = $displayConfig->isSpecialEnabled()
            ? $this->searchSpecials($keyword, 1, $fetchLimit, $sort)
            : ['list' => [], 'total' => 0];
        $contentData = $displayConfig->isContentEnabled()
            ? $this->searchContents($keyword, 1, $fetchLimit, $sort)
            : ['list' => [], 'total' => 0];
        $merchantData = $this->searchMerchants($keyword, 1, $fetchLimit, $sort);

        $allList = $this->buildAllResults(
            $keyword,
            $page,
            $limit,
            $goodsData['list'],
            $specialData['list'],
            $contentData['list'],
            $merchantData['list']
        );

        $summary = [
            'goods' => (int)$goodsData['total'],
            'special' => (int)$specialData['total'],
            'content' => (int)$contentData['total'],
            'merchant' => (int)$merchantData['total'],
        ];
        $summary['all'] = array_sum($summary);
        $currentTotal = $type === 'all' ? $summary['all'] : (int)($summary[$type] ?? 0);

        return [
            'keyword' => $keyword,
            'type' => $type,
            'page' => $page,
            'limit' => $limit,
            'summary' => $summary,
            'results' => [
                'all' => $allList,
                'goods' => array_slice($goodsData['list'], ($page - 1) * $limit, $limit),
                'special' => array_slice($specialData['list'], ($page - 1) * $limit, $limit),
                'content' => array_slice($contentData['list'], ($page - 1) * $limit, $limit),
                'merchant' => array_slice($merchantData['list'], ($page - 1) * $limit, $limit),
            ],
            'has_more' => $page * $limit < $currentTotal,
        ];
    }

    /**
     * 记录搜索行为.
     *
     * @param int $userId 用户ID.
     * @param string $keyword 关键词.
     * @param int $resultCount 结果数量.
     * @return array
     */
    public function recordKeyword(int $userId, string $keyword, int $resultCount = 0): array
    {
        $keyword = $this->normalizeKeyword($keyword);
        if ($keyword === '') {
            return ['history' => [], 'hot_keywords' => []];
        }

        app()->make(SearchKeywordStatService::class)->recordKeyword($keyword, $resultCount);

        $history = [];
        if ($userId > 0) {
            $history = app()->make(UserSearchHistoryService::class)->recordKeyword($userId, $keyword);
        }

        return [
            'history' => $history,
            'hot_keywords' => app()->make(SearchKeywordStatService::class)->getHotKeywords(10),
        ];
    }

    /**
     * 清空用户搜索历史.
     *
     * @param int $userId 用户ID.
     * @return array
     */
    public function clearHistory(int $userId = 0): array
    {
        if ($userId > 0) {
            app()->make(UserSearchHistoryService::class)->clearUserHistory($userId);
        }

        return ['history' => []];
    }

    /**
     * 获取发现内容.
     *
     * @param int $limit 返回数量.
     * @return array
     */
    protected function getDiscoverItems(int $limit): array
    {
        $limit = max(1, min(20, $limit));
        $displayConfig = app()->make(ContentDisplayConfigService::class);
        $specialData = $displayConfig->isSpecialEnabled()
            ? $this->searchSpecials('', 1, max(2, (int)ceil($limit / 2)), 'default')
            : ['list' => []];
        $contentData = $displayConfig->isContentEnabled()
            ? $this->searchContents('', 1, max(2, $limit - count($specialData['list'])), 'hot')
            : ['list' => []];

        return array_slice(array_merge($specialData['list'], $contentData['list']), 0, $limit);
    }

    /**
     * 搜索商品.
     *
     * @param string $keyword 关键词.
     * @param int $page 页码.
     * @param int $limit 每页数量.
     * @param string $sort 排序.
     * @param array $filters 额外筛选条件.
     * @return array
     */
    protected function searchProducts(string $keyword, int $page, int $limit, string $sort, array $filters = []): array
    {
        $paramFilters = is_array($filters['param_filters'] ?? null) ? $filters['param_filters'] : [];
        $data = app()->make(StoreProductService::class)->getMobileSearchList([
            'keyword' => $keyword,
            'page' => $page,
            'limit' => $limit,
            'sort' => in_array($sort, ['sales', 'credit', 'view', 'hot', 'price_asc', 'price_desc', 'latest'], true)
                ? $sort
                : 'default',
            'param_filters' => $paramFilters,
            'category_id' => (int) ($filters['category_id'] ?? 0),
            'brand_id' => (int) ($filters['brand_id'] ?? 0),
            'price_min' => (string) ($filters['price_min'] ?? ''),
            'price_max' => (string) ($filters['price_max'] ?? ''),
            'merchant_type' => trim((string) ($filters['merchant_type'] ?? '')),
            'is_free_shipping' => (int) ($filters['is_free_shipping'] ?? 0),
        ]);

        $list = array_map(
            fn(array $item): array => $this->formatProductItem($item, $keyword),
            $data['list'] ?? []
        );

        return [
            'list' => $list,
            'total' => (int)($data['total'] ?? count($list)),
        ];
    }

    /**
     * 搜索专题.
     *
     * @param string $keyword 关键词.
     * @param int $page 页码.
     * @param int $limit 每页数量.
     * @param string $sort 排序.
     * @return array
     */
    protected function searchSpecials(string $keyword, int $page, int $limit, string $sort): array
    {
        if (!app()->make(ContentDisplayConfigService::class)->isSpecialEnabled()) {
            return ['list' => [], 'total' => 0];
        }

        $data = app()->make(SpecialService::class)->getMobileList([
            'keyword' => $keyword,
            'page' => $page,
            'limit' => $limit,
            'sort' => $sort === 'sales' || $sort === 'latest' ? $sort : 'default',
        ]);

        $list = array_map(
            fn(array $item): array => $this->formatSpecialItem($item, $keyword),
            $data['list'] ?? []
        );

        return [
            'list' => $list,
            'total' => (int)($data['total'] ?? count($list)),
        ];
    }

    /**
     * 搜索内容.
     *
     * @param string $keyword 关键词.
     * @param int $page 页码.
     * @param int $limit 每页数量.
     * @param string $sort 排序.
     * @return array
     */
    protected function searchContents(string $keyword, int $page, int $limit, string $sort): array
    {
        if (!app()->make(ContentDisplayConfigService::class)->isContentEnabled()) {
            return ['list' => [], 'total' => 0];
        }

        $contentSort = in_array($sort, ['hot', 'view', 'price_asc', 'price_desc'], true) ? $sort : 'default';
        $data = app()->make(ArticleService::class)->getMobileList([
            'keyword' => $keyword,
            'page' => $page,
            'limit' => $limit,
            'sort' => $contentSort,
        ]);

        $list = array_map(
            fn(array $item): array => $this->formatContentItem($item, $keyword),
            $data['list'] ?? []
        );

        return [
            'list' => $list,
            'total' => (int)($data['total'] ?? count($list)),
        ];
    }

    /**
     * 搜索商户.
     *
     * @param string $keyword 关键词.
     * @param int $page 页码.
     * @param int $limit 每页数量.
     * @param string $sort 排序.
     * @return array
     */
    protected function searchMerchants(string $keyword, int $page, int $limit, string $sort): array
    {
        $data = app()->make(MerchantService::class)->getMobileSearchList([
            'keyword' => $keyword,
            'page' => $page,
            'limit' => $limit,
            'sort' => in_array($sort, ['sales', 'fans', 'latest'], true) ? $sort : 'default',
        ]);

        $list = array_map(
            fn(array $item): array => $this->formatMerchantItem($item, $keyword),
            $data['list'] ?? []
        );

        return [
            'list' => $list,
            'total' => (int)($data['total'] ?? count($list)),
        ];
    }

    /**
     * 构建全部搜索结果.
     *
     * @param string $keyword 关键词.
     * @param int $page 页码.
     * @param int $limit 每页数量.
     * @param array $goods 商品列表.
     * @param array $specials 专题列表.
     * @param array $contents 内容列表.
     * @param array $merchants 商户列表.
     * @return array
     */
    protected function buildAllResults(
        string $keyword,
        int $page,
        int $limit,
        array $goods,
        array $specials,
        array $contents,
        array $merchants
    ): array {
        $items = array_merge($goods, $specials, $contents, $merchants);
        foreach ($items as $index => $item) {
            $items[$index]['search_score'] = (int)($item['search_score'] ?? 0)
                + $this->typeWeight((string)($item['result_type'] ?? ''))
                + max(0, 100 - $index);
        }

        usort($items, static function (array $left, array $right): int {
            return (int)($right['search_score'] ?? 0) <=> (int)($left['search_score'] ?? 0);
        });

        return array_slice($items, ($page - 1) * $limit, $limit);
    }

    /**
     * 格式化商品搜索项.
     *
     * @param array $item 商品数据.
     * @param string $keyword 关键词.
     * @return array
     */
    protected function formatProductItem(array $item, string $keyword): array
    {
        $id = (int)($item['id'] ?? 0);
        $price = max(0, (float)($item['price'] ?? 0));
        $otPrice = max(0, (float)($item['ot_price'] ?? 0));
        $displaySales = (int)($item['sales'] ?? 0) + (int)($item['virtual_sales'] ?? 0);
        $displayViewCount = (int)($item['view_count'] ?? 0) + (int)($item['virtual_view_count'] ?? 0);
        $tags = $this->buildSearchTags((array)($item['tag_list'] ?? []));
        $isFree = $price <= 0;

        return [
            'id' => $id,
            'result_type' => 'goods',
            'cover' => (string)($item['cover'] ?? ''),
            'title' => (string)($item['name'] ?? ''),
            'name' => (string)($item['name'] ?? ''),
            'subtitle' => (string)($item['description'] ?? ''),
            'pricePrefix' => $isFree ? '' : '¥',
            'price' => $isFree ? '免费' : $this->formatMoney($price),
            'originalPrice' => !$isFree && $otPrice > $price ? $this->formatMoney($otPrice) : '',
            'badge' => ProductEnum::getGoodsTypeName((int)($item['goods_type'] ?? ProductEnum::GOODS_TYPE_PHYSICAL)),
            'badgeClassName' => 'is-brand',
            'tags' => $tags,
            'tag_list' => $tags,
            'tag_ids' => array_values(array_filter(array_map(
                static fn(array $tag): int => (int)($tag['id'] ?? 0),
                $tags
            ))),
            'hotText' => '已售' . $this->formatCount($displaySales),
            'sales' => $displaySales,
            'credit' => $displayViewCount,
            'view_count' => $displayViewCount,
            'goods_type' => (int)($item['goods_type'] ?? ProductEnum::GOODS_TYPE_PHYSICAL),
            'goods_type_text' => ProductEnum::getGoodsTypeName((int)($item['goods_type'] ?? ProductEnum::GOODS_TYPE_PHYSICAL)),
            'stock' => (int)($item['stock'] ?? 0),
            'unit' => (string)($item['unit'] ?? ''),
            'routeUrl' => '/pages/goods/detail/index?id=' . $id,
            'search_score' => $this->calculateScore(
                (string)($item['name'] ?? ''),
                (string)($item['keyword'] ?? ''),
                (string)($item['description'] ?? ''),
                $keyword,
                $displayViewCount + $displaySales * 2
            ),
        ];
    }

    /**
     * 格式化专题搜索项.
     *
     * @param array $item 专题数据.
     * @param string $keyword 关键词.
     * @return array
     */
    protected function formatSpecialItem(array $item, string $keyword): array
    {
        $id = (int)($item['id'] ?? 0);
        $price = (float)($item['price'] ?? 0);
        $originalPrice = (float)($item['original_price'] ?? 0);
        $isFree = (int)($item['special_type'] ?? 0) === 0 || $price <= 0;
        $isHot = (int)($item['is_hot'] ?? 0) === 1;
        $viewCount = (int)($item['view_count'] ?? 0);
        $buyCount = (int)($item['buy_count'] ?? 0);
        $tags = $this->buildSearchTags((array)($item['tag_list'] ?? []));

        return [
            'id' => $id,
            'result_type' => 'special',
            'cover' => (string)($item['cover'] ?? ''),
            'title' => (string)($item['title'] ?? ''),
            'subtitle' => (string)($item['description'] ?? ''),
            'pricePrefix' => $isFree ? '' : '¥',
            'price' => $isFree ? '免费' : $this->formatMoney($price),
            'originalPrice' => !$isFree && $originalPrice > $price ? $this->formatMoney($originalPrice) : '',
            'badge' => $isHot ? '热门专题' : '专题',
            'badgeClassName' => $isHot ? 'is-brand' : 'is-dark',
            'tags' => $tags,
            'tag_list' => $tags,
            'tag_ids' => array_values(array_filter(array_map(
                static fn(array $tag): int => (int)($tag['id'] ?? 0),
                $tags
            ))),
            'hotText' => $this->formatCount($viewCount) . '浏览',
            'sales' => $buyCount,
            'credit' => $viewCount,
            'routeUrl' => '/pages/contents/special/detail/index?id=' . $id,
            'search_score' => $this->calculateScore(
                (string)($item['title'] ?? ''),
                (string)($item['keywords'] ?? ''),
                (string)($item['description'] ?? ''),
                $keyword,
                $viewCount + $buyCount * 2
            ),
        ];
    }

    /**
     * 格式化内容搜索项.
     *
     * @param array $item 内容数据.
     * @param string $keyword 关键词.
     * @return array
     */
    protected function formatContentItem(array $item, string $keyword): array
    {
        $id = (int)($item['id'] ?? 0);
        $priceInfo = $this->splitPriceText((string)($item['price_text'] ?? '免费'));
        $viewCount = (int)($item['view_count'] ?? 0);
        $likeCount = (int)($item['like_count'] ?? 0);
        $tags = $this->buildSearchTags((array)($item['tag_list'] ?? []));

        return [
            'id' => $id,
            'result_type' => 'content',
            'cover' => (string)($item['cover'] ?? ''),
            'title' => (string)($item['title'] ?? ''),
            'subtitle' => (string)($item['description'] ?? ''),
            'pricePrefix' => $priceInfo['prefix'],
            'price' => $priceInfo['price'],
            'originalPrice' => trim(str_replace('¥', '', (string)($item['original_price_text'] ?? ''))),
            'badge' => (string)($item['content_type_text'] ?? '内容'),
            'badgeClassName' => 'is-brand',
            'tags' => $tags,
            'tag_list' => $tags,
            'tag_ids' => array_values(array_filter(array_map(
                static fn(array $tag): int => (int)($tag['id'] ?? 0),
                $tags
            ))),
            'hotText' => (string)($item['stat_text'] ?? ($this->formatCount($viewCount) . '浏览')),
            'sales' => $viewCount,
            'credit' => $likeCount,
            'routeUrl' => (string)($item['detail_url'] ?? ('/pages/contents/content/detail/index?id=' . $id)),
            'search_score' => $this->calculateScore(
                (string)($item['title'] ?? ''),
                (string)($item['keywords'] ?? ''),
                (string)($item['description'] ?? ''),
                $keyword,
                $viewCount + $likeCount
            ),
        ];
    }

    /**
     * 格式化商户搜索项.
     *
     * @param array $item 商户数据.
     * @param string $keyword 关键词.
     * @return array
     */
    protected function formatMerchantItem(array $item, string $keyword): array
    {
        $id = (int)($item['id'] ?? 0);
        $followCount = (int)($item['follow_count'] ?? 0);
        $orderCount = (int)($item['total_orders'] ?? 0);

        return [
            'id' => $id,
            'result_type' => 'merchant',
            'name' => (string)($item['name'] ?? ''),
            'cover' => (string)($item['cover'] ?? ''),
            'title' => (string)($item['name'] ?? ''),
            'subtitle' => (string)($item['description'] ?? ''),
            'pricePrefix' => '',
            'price' => (string)($item['business_status_text'] ?? '商户'),
            'originalPrice' => '',
            'badge' => '商户',
            'badgeClassName' => 'is-dark',
            'tags' => [],
            'tag_list' => [],
            'tag_ids' => [],
            'hotText' => $this->formatCount($followCount) . '粉丝',
            'sales' => $orderCount,
            'credit' => $followCount,
            'routeUrl' => '/pages/merchant/user/index?merchant_id=' . $id,
            'logoText' => $this->getInitial((string)($item['name'] ?? '商')),
            'logoStyle' => 'background: linear-gradient(135deg, #52525b 0%, #18181b 100%);',
            'label' => '商户',
            'ratingText' => (string)($item['business_status_text'] ?? '营业中'),
            'fansText' => $this->formatCount($followCount) . '粉丝',
            'ratingValue' => (int)($item['business_status'] ?? 1),
            'fansValue' => $followCount,
            'products' => [],
            'search_score' => $this->calculateScore(
                (string)($item['name'] ?? ''),
                (string)($item['keyword'] ?? ''),
                (string)($item['description'] ?? ''),
                $keyword,
                $followCount + $orderCount
            ),
        ];
    }

    /**
     * 构建搜索卡片标签.
     *
     * @param array $tagList 资源真实标签列表.
     * @return array
     */
    protected function buildSearchTags(array $tagList): array
    {
        $tags = [];
        foreach ($tagList as $index => $tag) {
            if (!is_array($tag)) {
                $tag = ['text' => (string)$tag];
            }

            $normalized = $this->normalizeSearchTag($tag, $index);
            if ($normalized !== null) {
                $tags[] = $normalized;
            }
        }

        return $tags;
    }

    /**
     * 规范化单个搜索标签，保留后台配置的展示效果字段.
     *
     * @param array $tag 原始标签.
     * @param int $index 标签序号.
     * @return array|null
     */
    protected function normalizeSearchTag(array $tag, int $index = 0): ?array
    {
        $text = trim((string)($tag['text'] ?? ($tag['name'] ?? ($tag['label'] ?? ''))));
        $icon = trim((string)($tag['icon'] ?? ''));
        if ($text === '' && $icon === '') {
            return null;
        }

        $effectType = (int)($tag['effect_type'] ?? ($tag['effectType'] ?? 1));
        $effectType = $effectType === 2 ? 2 : 1;
        $textColor = trim((string)($tag['text_color'] ?? ($tag['textColor'] ?? '')));
        $bgColor = trim((string)($tag['bg_color'] ?? ($tag['bgColor'] ?? '')));
        $borderColor = trim((string)($tag['border_color'] ?? ($tag['borderColor'] ?? '')));
        $className = trim((string)($tag['className'] ?? ($tag['class_name'] ?? '')));
        if ($className === '') {
            $className = $effectType === 2
                ? 'is-image'
                : (($textColor !== '' || $bgColor !== '' || $borderColor !== '') ? 'is-custom' : '');
        }

        $tagId = (int)($tag['id'] ?? 0);

        return [
            'id' => $tagId,
            'key' => $tagId > 0 ? 'tag-' . $tagId : 'tag-' . $index . '-' . md5($text . $icon),
            'text' => $text,
            'name' => trim((string)($tag['name'] ?? $text)),
            'label' => trim((string)($tag['label'] ?? $text)),
            'type' => trim((string)($tag['type'] ?? '')),
            'className' => $className,
            'class_name' => $className,
            'effect_type' => $effectType,
            'effectType' => $effectType,
            'text_color' => $textColor,
            'textColor' => $textColor,
            'bg_color' => $bgColor,
            'bgColor' => $bgColor,
            'border_color' => $borderColor,
            'borderColor' => $borderColor,
            'icon' => $icon,
            'category_id' => (int)($tag['category_id'] ?? 0),
            'category_name' => (string)($tag['category_name'] ?? ''),
            'sort' => (int)($tag['sort'] ?? 0),
        ];
    }

    /**
     * 空搜索结果.
     *
     * @param int $page 页码.
     * @param int $limit 每页数量.
     * @param string $type 当前搜索类型.
     * @return array
     */
    protected function emptySearchResult(int $page, int $limit, string $type = 'all'): array
    {
        return [
            'keyword' => '',
            'type' => $type,
            'page' => $page,
            'limit' => $limit,
            'summary' => ['all' => 0, 'goods' => 0, 'special' => 0, 'content' => 0, 'merchant' => 0],
            'results' => ['all' => [], 'goods' => [], 'special' => [], 'content' => [], 'merchant' => []],
            'has_more' => false,
        ];
    }

    /**
     * 计算匹配分.
     *
     * @param string $title 标题.
     * @param string $keywords 关键词.
     * @param string $description 描述.
     * @param string $needle 搜索词.
     * @param int $hotCount 热度值.
     * @return int
     */
    protected function calculateScore(
        string $title,
        string $keywords,
        string $description,
        string $needle,
        int $hotCount = 0
    ): int {
        if ($needle === '') {
            return min(30, (int)floor($hotCount / 100));
        }

        $normalizedNeedle = mb_strtolower($needle);
        $normalizedTitle = mb_strtolower($title);
        $normalizedKeywords = mb_strtolower($keywords);
        $normalizedDescription = mb_strtolower($description);
        $score = 0;

        if ($normalizedTitle === $normalizedNeedle) {
            $score += 120;
        } elseif (str_contains($normalizedTitle, $normalizedNeedle)) {
            $score += 90;
        }

        if ($normalizedKeywords !== '' && str_contains($normalizedKeywords, $normalizedNeedle)) {
            $score += 70;
        }

        if ($normalizedDescription !== '' && str_contains($normalizedDescription, $normalizedNeedle)) {
            $score += 45;
        }

        return $score + min(40, (int)floor($hotCount / 100));
    }

    /**
     * 类型排序权重.
     *
     * @param string $type 结果类型.
     * @return int
     */
    protected function typeWeight(string $type): int
    {
        return match ($type) {
            'goods' => 40,
            'special' => 30,
            'content' => 20,
            'merchant' => 10,
            default => 0,
        };
    }

    /**
     * 规范化关键词.
     *
     * @param string $keyword 原始关键词.
     * @return string
     */
    protected function normalizeKeyword(string $keyword): string
    {
        $keyword = mb_substr(trim(preg_replace('/\s+/u', ' ', $keyword) ?: ''), 0, 50);
        return $keyword === 'function search() { [native code] }' ? '' : $keyword;
    }

    /**
     * 规范化搜索类型.
     *
     * @param string $type 原始类型.
     * @return string
     */
    protected function normalizeSearchType(string $type): string
    {
        $type = trim($type);
        $aliases = [
            'product' => 'goods',
            'shop' => 'merchant',
            'video' => 'content',
        ];
        $type = $aliases[$type] ?? $type;

        return in_array($type, ['all', 'goods', 'special', 'content', 'merchant'], true) ? $type : 'all';
    }

    /**
     * 拆分价格文案.
     *
     * @param string $priceText 价格文案.
     * @return array
     */
    protected function splitPriceText(string $priceText): array
    {
        $priceText = trim($priceText);
        if ($priceText === '' || $priceText === '免费') {
            return ['prefix' => '', 'price' => '免费'];
        }

        return [
            'prefix' => str_starts_with($priceText, '¥') ? '¥' : '',
            'price' => ltrim($priceText, '¥'),
        ];
    }

    /**
     * 格式化金额.
     *
     * @param float $amount 金额.
     * @return string
     */
    protected function formatMoney(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    /**
     * 格式化数量.
     *
     * @param int $count 数量.
     * @return string
     */
    protected function formatCount(int $count): string
    {
        if ($count >= 10000) {
            return rtrim(rtrim(number_format($count / 10000, 1, '.', ''), '0'), '.') . '万';
        }

        return (string)$count;
    }

    /**
     * 获取标题首字.
     *
     * @param string $title 标题.
     * @return string
     */
    protected function getInitial(string $title): string
    {
        $title = trim($title);
        return $title === '' ? '商' : mb_substr($title, 0, 1);
    }
}
