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

namespace app\common\service\user;

use app\common\enum\AuditStatusEnum;
use app\common\enum\ContentTypeEnum;
use app\common\enum\ConfigKeyEnum;
use app\common\exception\AdminResponseException;
use app\common\model\user\UserFavorite;
use app\common\service\BaseService;
use app\common\service\content\ArticleService;
use app\common\service\content\SpecialService;
use app\common\service\live\LiveAnchorService;
use app\common\service\live\LiveRoomService;
use app\common\service\merchant\MerchantService;
use app\common\service\setting\CategoryService;

/**
 * 用户收藏服务
 */
class UserFavoriteService extends BaseService
{
    public const TARGET_TYPE_SPECIAL = 'special';
    public const TARGET_TYPE_CONTENT = 'content';
    public const TARGET_TYPE_PRODUCT = 'product';
    public const TARGET_TYPE_MERCHANT = 'merchant';
    public const TARGET_TYPE_LIVE_ANCHOR = 'live_anchor';
    public const TARGET_TYPE_PLATFORM = 'platform';

    /**
     * 对应模型
     */
    protected function setModel(): string
    {
        return UserFavorite::class;
    }

    /**
     * 获取收藏状态。
     *
     * @param int $userId 用户ID。
     * @param string $targetType 收藏对象类型。
     * @param int $targetId 收藏对象ID。
     * @return bool
     */
    public function isFavorite(int $userId, string $targetType, int $targetId): bool
    {
        if ($userId <= 0 || !$this->isValidTarget($targetType, $targetId)) {
            return false;
        }

        return (bool)$this->getModel()
            ->where('user_id', $userId)
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->where('status', 1)
            ->count();
    }

    /**
     * 切换收藏状态。
     *
     * @param int $userId 用户ID。
     * @param string $targetType 收藏对象类型。
     * @param int $targetId 收藏对象ID。
     * @param int $merchantId 商户ID。
     * @return array
     * @throws AdminResponseException
     */
    public function toggleFavorite(int $userId, string $targetType, int $targetId, int $merchantId = 0): array
    {
        if ($userId <= 0 || !$this->isValidTarget($targetType, $targetId)) {
            throw new AdminResponseException('收藏参数不正确');
        }

        $record = $this->getModel()
            ->where('user_id', $userId)
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->find();

        $now = date('Y-m-d H:i:s');
        $nextStatus = 1;

        if ($record) {
            $nextStatus = (int)($record['status'] ?? 0) === 1 ? 0 : 1;
            $record->save([
                'merchant_id' => $merchantId,
                'status' => $nextStatus,
                'update_time' => $now,
            ]);
        } else {
            $this->create([
                'user_id' => $userId,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'merchant_id' => $merchantId,
                'status' => 1,
                'create_time' => $now,
                'update_time' => $now,
            ]);
        }

        return [
            'target_type' => $targetType,
            'target_id' => $targetId,
            'is_favorite' => $nextStatus === 1,
            'status' => $nextStatus,
        ];
    }

    /**
     * 获取用户指定类型的收藏对象ID列表。
     *
     * @param int $userId 用户ID。
     * @param string $targetType 收藏对象类型。
     * @param int $limit 限制条数。
     * @return array
     */
    public function getUserFavoriteTargetIds(int $userId, string $targetType, int $limit = 20): array
    {
        if ($userId <= 0 || trim($targetType) === '') {
            return [];
        }

        $limit = max(1, min(100, $limit));
        $targetIds = $this->getModel()
            ->where('user_id', $userId)
            ->where('target_type', $targetType)
            ->where('status', 1)
            ->order('update_time', 'desc')
            ->limit($limit)
            ->column('target_id');

        return array_values(array_unique(array_filter(array_map('intval', $targetIds))));
    }

    /**
     * 批量获取收藏/关注状态映射。
     *
     * @param int $userId 用户ID。
     * @param string $targetType 收藏对象类型。
     * @param array $targetIds 收藏对象ID列表。
     * @return array<int,bool>
     */
    public function getFavoriteStatusMap(int $userId, string $targetType, array $targetIds): array
    {
        $targetType = trim($targetType);
        $targetIds = $this->normalizeIds($targetIds);
        if ($userId <= 0 || $targetType === '' || $targetIds === []) {
            return [];
        }

        $statusMap = array_fill_keys($targetIds, false);
        $favoriteIds = $this->getModel()
            ->where('user_id', $userId)
            ->where('target_type', $targetType)
            ->whereIn('target_id', $targetIds)
            ->where('status', 1)
            ->column('target_id');

        foreach ($favoriteIds as $targetId) {
            $targetId = (int)$targetId;
            if (isset($statusMap[$targetId])) {
                $statusMap[$targetId] = true;
            }
        }

        return $statusMap;
    }

    /**
     * 获取移动端用户收藏列表。
     *
     * @param int $userId 用户ID。
     * @param string $targetType 收藏对象类型，仅支持 content/special。
     * @param int $page 页码。
     * @param int $limit 每页条数。
     * @return array
     * @throws AdminResponseException
     */
    public function getMobileFavoriteList(int $userId, string $targetType, int $page = 1, int $limit = 10): array
    {
        if ($userId <= 0) {
            throw new AdminResponseException('未获取到登录用户');
        }

        $targetType = $this->normalizeMobileTargetType($targetType);
        $page = max(1, $page);
        $limit = max(1, min(50, $limit));

        $baseQuery = $this->getModel()
            ->where('user_id', $userId)
            ->where('target_type', $targetType)
            ->where('status', 1);

        $favoriteList = (clone $baseQuery)
            ->order('update_time', 'desc')
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();
        $total = (int)(clone $baseQuery)->count();

        return $this->listData(
            $this->formatMobileFavoriteList($favoriteList, $targetType),
            $total,
            [],
            $page,
            $limit
        )->toArray();
    }

    /**
     * 获取移动端关注列表。
     *
     * @param int $userId 用户ID。
     * @param string $type 关注类型：all/shop/live。
     * @param int $limit 每类返回条数。
     * @return array
     * @throws AdminResponseException
     */
    public function getMobileFollowList(int $userId, string $type = 'all', int $limit = 20): array
    {
        if ($userId <= 0) {
            throw new AdminResponseException('未获取到登录用户');
        }

        $followType = $this->normalizeMobileFollowType($type, true);
        $limit = max(1, min(50, $limit));
        $platforms = $this->getMobileFollowPlatformList($userId);
        $shops = [];
        $lives = [];

        $shops = $this->getMobileFollowShopList($userId, $limit);

        if ($followType === 'all' || $followType === self::TARGET_TYPE_LIVE_ANCHOR) {
            $lives = $this->getMobileFollowLiveList($userId, $limit);
        }

        return [
            'type' => $this->formatMobileFollowType($followType),
            'platforms' => $platforms,
            'shops' => $shops,
            'lives' => $lives,
            'platform_count' => count($platforms),
            'shop_count' => count($shops),
            'live_count' => count($lives),
            'list' => match ($followType) {
                self::TARGET_TYPE_MERCHANT => $shops,
                self::TARGET_TYPE_LIVE_ANCHOR => $lives,
                default => [],
            },
        ];
    }

    /**
     * 切换移动端主播关注状态。
     *
     * @param int $userId 用户ID。
     * @param int $anchorId 主播ID。
     * @return array
     * @throws AdminResponseException
     */
    public function toggleMobileLiveAnchorFollow(int $userId, int $anchorId): array
    {
        if ($userId <= 0) {
            throw new AdminResponseException('未获取到登录用户');
        }
        if ($anchorId <= 0) {
            throw new AdminResponseException('主播ID不能为空');
        }

        $anchor = app()->make(LiveAnchorService::class)->get(
            $anchorId,
            ['id', 'merchant_id', 'status', 'audit_status', 'is_live_banned']
        );
        if (!$anchor) {
            throw new AdminResponseException('主播不存在');
        }
        if (
            (int)($anchor['status'] ?? 0) !== 1
            || (int)($anchor['audit_status'] ?? 0) !== LiveAnchorService::AUDIT_PASS
            || (int)($anchor['is_live_banned'] ?? 0) === 1
        ) {
            throw new AdminResponseException('主播暂不可关注');
        }

        $merchantId = (int)($anchor['merchant_id'] ?? 0);
        $result = $this->toggleFavorite($userId, self::TARGET_TYPE_LIVE_ANCHOR, $anchorId, $merchantId);
        $isFollowed = (bool)($result['is_favorite'] ?? false);
        $followCount = $this->getTargetFollowCount(self::TARGET_TYPE_LIVE_ANCHOR, $anchorId);

        return [
            'anchor_id' => $anchorId,
            'merchant_id' => $merchantId,
            'target_type' => self::TARGET_TYPE_LIVE_ANCHOR,
            'target_id' => $anchorId,
            'is_followed' => $isFollowed,
            'status' => $isFollowed ? 1 : 0,
            'follow_count' => $followCount,
            'follow_count_text' => $this->formatCountText($followCount, '粉丝'),
        ];
    }

    /**
     * 取消移动端关注。
     *
     * @param int $userId 用户ID。
     * @param string $type 关注类型：shop/live/platform。
     * @param int $targetId 关注对象ID，平台固定为 0。
     * @return array
     * @throws AdminResponseException
     */
    public function cancelMobileFollow(int $userId, string $type, int $targetId): array
    {
        if ($userId <= 0) {
            throw new AdminResponseException('未获取到登录用户');
        }

        $targetType = $this->normalizeMobileFollowType($type);
        if ($targetType === self::TARGET_TYPE_PLATFORM) {
            $targetId = 0;
        } elseif ($targetId <= 0) {
            throw new AdminResponseException('关注对象ID不能为空');
        }

        $record = $this->getModel()
            ->where('user_id', $userId)
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->find();
        $wasFollowed = $record && (int)($record['status'] ?? 0) === 1;

        if ($record) {
            $record->save([
                'status' => 0,
                'update_time' => date('Y-m-d H:i:s'),
            ]);
        }

        if ($wasFollowed && $targetType === self::TARGET_TYPE_MERCHANT) {
            $this->decreaseMerchantFollowCount($targetId);
        }

        return [
            'type' => $this->formatMobileFollowType($targetType),
            'target_type' => $targetType,
            'target_id' => $targetId,
            'is_followed' => false,
            'status' => 0,
        ];
    }

    /**
     * 取消用户收藏。
     *
     * @param int $userId 用户ID。
     * @param string $targetType 收藏对象类型，仅支持 content/special。
     * @param int $targetId 收藏对象ID。
     * @return array
     * @throws AdminResponseException
     */
    public function cancelMobileFavorite(int $userId, string $targetType, int $targetId): array
    {
        if ($userId <= 0) {
            throw new AdminResponseException('未获取到登录用户');
        }

        $targetType = $this->normalizeMobileTargetType($targetType);
        if ($targetId <= 0) {
            throw new AdminResponseException('收藏对象ID不能为空');
        }

        $record = $this->getModel()
            ->where('user_id', $userId)
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->find();

        if ($record) {
            $record->save([
                'status' => 0,
                'update_time' => date('Y-m-d H:i:s'),
            ]);
        }

        return [
            'target_type' => $targetType,
            'target_id' => $targetId,
            'is_favorite' => false,
            'status' => 0,
        ];
    }

    /**
     * 归一化移动端收藏类型。
     *
     * @param string $targetType 收藏对象类型。
     * @return string
     * @throws AdminResponseException
     */
    protected function normalizeMobileTargetType(string $targetType): string
    {
        $targetType = trim($targetType);
        if ($targetType === 'topic') {
            $targetType = self::TARGET_TYPE_SPECIAL;
        }

        if (!in_array($targetType, [self::TARGET_TYPE_CONTENT, self::TARGET_TYPE_SPECIAL], true)) {
            throw new AdminResponseException('收藏类型不支持');
        }

        return $targetType;
    }

    /**
     * 格式化移动端收藏列表。
     *
     * @param array $favoriteList 收藏记录列表。
     * @param string $targetType 收藏对象类型。
     * @return array
     */
    protected function formatMobileFavoriteList(array $favoriteList, string $targetType): array
    {
        if ($favoriteList === []) {
            return [];
        }

        $targetIds = array_values(array_unique(array_filter(array_map(
            static fn(array $item): int => (int)($item['target_id'] ?? 0),
            $favoriteList
        ))));
        $targetMap = $targetType === self::TARGET_TYPE_CONTENT
            ? app()->make(ArticleService::class)->getMapByIds($targetIds)
            : app()->make(SpecialService::class)->getMapByIds($targetIds);

        $result = [];
        foreach ($favoriteList as $favorite) {
            $targetId = (int)($favorite['target_id'] ?? 0);
            $target = is_array($targetMap[$targetId] ?? null) ? $targetMap[$targetId] : [];
            if ($target === []) {
                continue;
            }

            $item = $targetType === self::TARGET_TYPE_CONTENT
                ? $this->formatMobileContentFavorite($favorite, $target)
                : $this->formatMobileSpecialFavorite($favorite, $target);
            if ($item !== []) {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * 格式化内容收藏项。
     *
     * @param array $favorite 收藏记录。
     * @param array $content 内容记录。
     * @return array
     */
    protected function formatMobileContentFavorite(array $favorite, array $content): array
    {
        if ((int)($content['status'] ?? 0) !== 1 || (int)($content['audit_status'] ?? 0) !== AuditStatusEnum::APPROVED) {
            return [];
        }

        $targetId = (int)($content['id'] ?? 0);
        $contentType = (int)($content['content_type'] ?? 0);
        $price = (float)($content['price'] ?? 0);

        return [
            'id' => self::TARGET_TYPE_CONTENT . '-' . $targetId,
            'favorite_id' => (int)($favorite['id'] ?? 0),
            'target_type' => self::TARGET_TYPE_CONTENT,
            'target_id' => $targetId,
            'title' => (string)($content['title'] ?? ''),
            'desc' => (string)($content['description'] ?? ''),
            'image' => (string)($content['cover'] ?? ''),
            'highlight' => $price > 0 ? '¥' . rtrim(rtrim(number_format($price, 2, '.', ''), '0'), '.') : '免费',
            'badge' => ContentTypeEnum::getName($contentType),
            'detail_url' => '/pages/contents/content/detail/index?id=' . $targetId,
            'create_time' => (string)($favorite['create_time'] ?? ''),
            'update_time' => (string)($favorite['update_time'] ?? ''),
        ];
    }

    /**
     * 格式化专题收藏项。
     *
     * @param array $favorite 收藏记录。
     * @param array $special 专题记录。
     * @return array
     */
    protected function formatMobileSpecialFavorite(array $favorite, array $special): array
    {
        if ((int)($special['status'] ?? 0) !== 1 || (int)($special['audit_status'] ?? 0) !== AuditStatusEnum::APPROVED) {
            return [];
        }

        $targetId = (int)($special['id'] ?? 0);
        $price = (float)($special['price'] ?? 0);

        return [
            'id' => self::TARGET_TYPE_SPECIAL . '-' . $targetId,
            'favorite_id' => (int)($favorite['id'] ?? 0),
            'target_type' => self::TARGET_TYPE_SPECIAL,
            'target_id' => $targetId,
            'title' => (string)($special['title'] ?? ''),
            'desc' => (string)($special['description'] ?? ''),
            'image' => (string)($special['cover'] ?? ''),
            'highlight' => $price > 0 ? '¥' . rtrim(rtrim(number_format($price, 2, '.', ''), '0'), '.') : '免费',
            'badge' => '专题',
            'detail_url' => '/pages/contents/special/detail/index?id=' . $targetId,
            'create_time' => (string)($favorite['create_time'] ?? ''),
            'update_time' => (string)($favorite['update_time'] ?? ''),
        ];
    }

    /**
     * 获取关注店铺列表。
     *
     * @param int $userId 用户ID。
     * @param int $limit 返回条数。
     * @return array
     */
    protected function getMobileFollowShopList(int $userId, int $limit): array
    {
        $favorites = $this->getActiveFollowFavorites($userId, self::TARGET_TYPE_MERCHANT, $limit);
        if ($favorites === []) {
            return [];
        }

        $merchantIds = $this->extractFavoriteTargetIds($favorites);
        $merchantMap = $this->getFollowMerchantMap($merchantIds);
        $specialMetaMap = $this->getFollowMerchantSpecialMeta(array_keys($merchantMap));

        $result = [];
        foreach ($favorites as $favorite) {
            $merchantId = (int)($favorite['target_id'] ?? 0);
            $merchant = is_array($merchantMap[$merchantId] ?? null) ? $merchantMap[$merchantId] : [];
            if ($merchant === []) {
                continue;
            }

            $result[] = $this->formatMobileFollowShop(
                $favorite,
                $merchant,
                is_array($specialMetaMap[$merchantId] ?? null) ? $specialMetaMap[$merchantId] : []
            );
        }

        return $result;
    }

    /**
     * 获取关注主播列表。
     *
     * @param int $userId 用户ID。
     * @param int $limit 返回条数。
     * @return array
     */
    protected function getMobileFollowLiveList(int $userId, int $limit): array
    {
        $favorites = $this->getActiveFollowFavorites($userId, self::TARGET_TYPE_LIVE_ANCHOR, $limit);
        if ($favorites === []) {
            return [];
        }

        $anchorIds = $this->extractFavoriteTargetIds($favorites);
        $anchorMap = $this->getFollowAnchorMap($anchorIds);
        $roomMap = $this->getFollowAnchorRoomMap(array_keys($anchorMap));

        $result = [];
        foreach ($favorites as $favorite) {
            $anchorId = (int)($favorite['target_id'] ?? 0);
            $anchor = is_array($anchorMap[$anchorId] ?? null) ? $anchorMap[$anchorId] : [];
            if ($anchor === []) {
                continue;
            }

            $result[] = $this->formatMobileFollowLive(
                $favorite,
                $anchor,
                is_array($roomMap[$anchorId] ?? null) ? $roomMap[$anchorId] : [],
                0
            );
        }

        return $result;
    }

    /**
     * 获取关注平台列表。
     *
     * @param int $userId 用户ID。
     * @return array
     */
    protected function getMobileFollowPlatformList(int $userId): array
    {
        $favorites = $this->getActiveFollowFavorites($userId, self::TARGET_TYPE_PLATFORM, 1);
        if ($favorites === []) {
            return [];
        }

        $favorite = $favorites[0];
        if ((int)($favorite['target_id'] ?? 0) !== 0) {
            return [];
        }

        return [$this->formatMobileFollowPlatform($favorite)];
    }

    /**
     * 获取有效关注记录。
     *
     * @param int $userId 用户ID。
     * @param string $targetType 关注对象类型。
     * @param int $limit 返回条数。
     * @return array
     */
    protected function getActiveFollowFavorites(int $userId, string $targetType, int $limit): array
    {
        return $this->getModel()
            ->where('user_id', $userId)
            ->where('target_type', $targetType)
            ->where('status', 1)
            ->field(['id', 'user_id', 'target_type', 'target_id', 'merchant_id', 'create_time', 'update_time'])
            ->order('update_time', 'desc')
            ->order('id', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();
    }

    /**
     * 提取关注对象 ID。
     *
     * @param array $favorites 关注记录。
     * @return array
     */
    protected function extractFavoriteTargetIds(array $favorites): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn(array $item): int => (int)($item['target_id'] ?? 0),
            $favorites
        ))));
    }

    /**
     * 获取店铺映射。
     *
     * @param array $merchantIds 店铺ID列表。
     * @return array
     */
    protected function getFollowMerchantMap(array $merchantIds): array
    {
        $merchantIds = $this->normalizeIds($merchantIds);
        if ($merchantIds === []) {
            return [];
        }

        $list = app()->make(MerchantService::class)->getModel()
            ->whereIn('id', $merchantIds)
            ->where('status', 1)
            ->where('audit_status', AuditStatusEnum::APPROVED)
            ->field([
                'id', 'mer_name', 'logo', 'mer_avatar', 'description', 'mer_keyword',
                'business_status', 'follow_count', 'total_sales', 'total_orders', 'create_time'
            ])
            ->select()
            ->toArray();

        $result = [];
        foreach ($list as $merchant) {
            $merchantId = (int)($merchant['id'] ?? 0);
            if ($merchantId > 0) {
                $result[$merchantId] = $merchant;
            }
        }

        return $result;
    }

    /**
     * 获取店铺专题上新统计。
     *
     * @param array $merchantIds 店铺ID列表。
     * @return array
     */
    protected function getFollowMerchantSpecialMeta(array $merchantIds): array
    {
        $merchantIds = $this->normalizeIds($merchantIds);
        if ($merchantIds === []) {
            return [];
        }

        $rows = app()->make(SpecialService::class)->getModel()
            ->whereIn('merchant_id', $merchantIds)
            ->where('status', 1)
            ->where('audit_status', AuditStatusEnum::APPROVED)
            ->field([
                'id', 'merchant_id', 'category_id', 'title', 'description', 'cover',
                'special_type', 'price', 'content_count', 'view_count', 'buy_count', 'create_time'
            ])
            ->order('merchant_id', 'asc')
            ->order('create_time', 'desc')
            ->order('id', 'desc')
            ->select()
            ->toArray();

        $meta = [];
        $categoryIds = [];
        foreach ($merchantIds as $merchantId) {
            $meta[$merchantId] = [
                'total' => 0,
                'category_counts' => [],
                'special_rows' => [],
            ];
        }

        foreach ($rows as $row) {
            $merchantId = (int)($row['merchant_id'] ?? 0);
            if (!isset($meta[$merchantId])) {
                continue;
            }

            $categoryId = (int)($row['category_id'] ?? 0);
            $meta[$merchantId]['total']++;
            if ($categoryId > 0) {
                $meta[$merchantId]['category_counts'][$categoryId] = (int)($meta[$merchantId]['category_counts'][$categoryId] ?? 0) + 1;
                $categoryIds[] = $categoryId;
            }

            if (count($meta[$merchantId]['special_rows']) < 3) {
                $meta[$merchantId]['special_rows'][] = $row;
            }
        }

        $categoryNameMap = $this->getCategoryNameMap($categoryIds);
        foreach ($meta as $merchantId => $item) {
            [$categoryId, $categoryCount] = $this->resolveTopCategory($item['category_counts']);
            $categoryName = $categoryId > 0 ? (string)($categoryNameMap[$categoryId] ?? '') : '';
            $specials = [];
            foreach ($item['special_rows'] as $special) {
                $specials[] = $this->formatMobileFollowSpecial($special, $categoryNameMap);
            }

            $meta[$merchantId] = [
                'new_count' => (int)$item['total'],
                'top_category_id' => $categoryId,
                'top_category_name' => $categoryName,
                'top_category_new_count' => $categoryCount,
                'new_summary' => $this->buildMerchantNewSummary((int)$item['total'], $categoryName, $categoryCount),
                'latest_new_time' => (string)($item['special_rows'][0]['create_time'] ?? ''),
                'new_specials' => $specials,
            ];
        }

        return $meta;
    }

    /**
     * 获取专题分类名映射。
     *
     * @param array $categoryIds 分类ID列表。
     * @return array
     */
    protected function getCategoryNameMap(array $categoryIds): array
    {
        $categoryIds = $this->normalizeIds($categoryIds);
        if ($categoryIds === []) {
            return [];
        }

        return app()->make(CategoryService::class)->getModel()
            ->whereIn('id', $categoryIds)
            ->column('name', 'id') ?: [];
    }

    /**
     * 获取最多上新的分类。
     *
     * @param array $categoryCounts 分类统计。
     * @return array
     */
    protected function resolveTopCategory(array $categoryCounts): array
    {
        if ($categoryCounts === []) {
            return [0, 0];
        }

        arsort($categoryCounts);
        $categoryId = (int)array_key_first($categoryCounts);

        return [$categoryId, (int)($categoryCounts[$categoryId] ?? 0)];
    }

    /**
     * 生成店铺上新摘要。
     *
     * @param int $totalCount 上新专题总数。
     * @param string $categoryName 上新最多分类名。
     * @param int $categoryCount 上新最多分类数量。
     * @return string
     */
    protected function buildMerchantNewSummary(int $totalCount, string $categoryName, int $categoryCount): string
    {
        if ($totalCount <= 0) {
            return '暂无新品专题';
        }

        if ($categoryName !== '' && $categoryCount > 0) {
            return $categoryName . '上新' . $categoryCount . '个专题';
        }

        return '上新' . $totalCount . '个专题';
    }

    /**
     * 格式化关注店铺。
     *
     * @param array $favorite 关注记录。
     * @param array $merchant 店铺记录。
     * @param array $specialMeta 上新统计。
     * @return array
     */
    protected function formatMobileFollowShop(array $favorite, array $merchant, array $specialMeta): array
    {
        $merchantId = (int)($merchant['id'] ?? 0);
        $logo = trim((string)($merchant['logo'] ?? ''));
        $avatar = trim((string)($merchant['mer_avatar'] ?? ''));
        $businessStatus = (int)($merchant['business_status'] ?? 1);
        $followCount = (int)($merchant['follow_count'] ?? 0);

        return [
            'id' => $merchantId,
            'favorite_id' => (int)($favorite['id'] ?? 0),
            'target_type' => self::TARGET_TYPE_MERCHANT,
            'target_id' => $merchantId,
            'merchant_id' => $merchantId,
            'name' => trim((string)($merchant['mer_name'] ?? '')),
            'logo' => $logo !== '' ? $logo : $avatar,
            'avatar' => $avatar,
            'description' => trim((string)($merchant['description'] ?? '')),
            'category' => trim((string)($merchant['mer_keyword'] ?? '')) ?: '精选店铺',
            'tag' => $businessStatus === 1 ? '营业中' : '休息中',
            'business_status' => $businessStatus,
            'business_status_text' => $businessStatus === 1 ? '营业中' : '休息中',
            'follow_count' => $followCount,
            'follow_count_text' => $this->formatCountText($followCount, '粉丝'),
            'new_count' => (int)($specialMeta['new_count'] ?? 0),
            'top_category_id' => (int)($specialMeta['top_category_id'] ?? 0),
            'top_category_name' => (string)($specialMeta['top_category_name'] ?? ''),
            'top_category_new_count' => (int)($specialMeta['top_category_new_count'] ?? 0),
            'new_summary' => (string)($specialMeta['new_summary'] ?? '暂无新品专题'),
            'latest_new_time' => (string)($specialMeta['latest_new_time'] ?? ''),
            'new_specials' => is_array($specialMeta['new_specials'] ?? null) ? $specialMeta['new_specials'] : [],
            'route_url' => '/pages/merchant/user/index?merchant_id=' . $merchantId,
            'create_time' => (string)($favorite['create_time'] ?? ''),
            'update_time' => (string)($favorite['update_time'] ?? ''),
        ];
    }

    /**
     * 格式化店铺上新专题。
     *
     * @param array $special 专题记录。
     * @param array $categoryNameMap 分类名映射。
     * @return array
     */
    protected function formatMobileFollowSpecial(array $special, array $categoryNameMap): array
    {
        $specialId = (int)($special['id'] ?? 0);
        $categoryId = (int)($special['category_id'] ?? 0);
        $price = max(0, (float)($special['price'] ?? 0));
        $isFree = (int)($special['special_type'] ?? 0) === 0 || $price <= 0;

        return [
            'id' => $specialId,
            'category_id' => $categoryId,
            'category_name' => $categoryId > 0 ? (string)($categoryNameMap[$categoryId] ?? '') : '',
            'title' => trim((string)($special['title'] ?? '')),
            'description' => trim((string)($special['description'] ?? '')),
            'cover' => trim((string)($special['cover'] ?? '')),
            'price' => $price,
            'price_text' => $isFree ? '免费' : '¥' . number_format($price, 2, '.', ''),
            'content_count' => (int)($special['content_count'] ?? 0),
            'view_count' => (int)($special['view_count'] ?? 0),
            'buy_count' => (int)($special['buy_count'] ?? 0),
            'detail_url' => '/pages/contents/special/detail/index?id=' . $specialId,
            'create_time' => (string)($special['create_time'] ?? ''),
        ];
    }

    /**
     * 获取主播映射。
     *
     * @param array $anchorIds 主播ID列表。
     * @return array
     */
    protected function getFollowAnchorMap(array $anchorIds): array
    {
        $anchorIds = $this->normalizeIds($anchorIds);
        if ($anchorIds === []) {
            return [];
        }

        $list = app()->make(LiveAnchorService::class)->getModel()
            ->whereIn('id', $anchorIds)
            ->where('status', 1)
            ->where('audit_status', LiveAnchorService::AUDIT_PASS)
            ->where('is_live_banned', 0)
            ->field(['id', 'merchant_id', 'name', 'avatar', 'description', 'direction', 'sort', 'create_time'])
            ->select()
            ->toArray();

        $result = [];
        foreach ($list as $anchor) {
            $anchorId = (int)($anchor['id'] ?? 0);
            if ($anchorId > 0) {
                $result[$anchorId] = $anchor;
            }
        }

        return $result;
    }

    /**
     * 获取主播最近直播间映射。
     *
     * @param array $anchorIds 主播ID列表。
     * @return array
     */
    protected function getFollowAnchorRoomMap(array $anchorIds): array
    {
        $anchorIds = $this->normalizeIds($anchorIds);
        if ($anchorIds === []) {
            return [];
        }

        $result = [];
        foreach ($anchorIds as $anchorId) {
            $room = $this->getPreferredFollowRoom($anchorId);
            if ($room !== []) {
                $result[$anchorId] = $room;
            }
        }

        return $result;
    }

    /**
     * 获取主播当前最适合展示的直播间。
     *
     * @param int $anchorId 主播ID。
     * @return array
     */
    protected function getPreferredFollowRoom(int $anchorId): array
    {
        if ($anchorId <= 0) {
            return [];
        }

        $statuses = [
            LiveRoomService::STATUS_LIVING,
            LiveRoomService::STATUS_PENDING,
            LiveRoomService::STATUS_ENDED,
        ];

        foreach ($statuses as $status) {
            $room = app()->make(LiveRoomService::class)->getModel()
                ->where('live_anchor_id', $anchorId)
                ->where('audit_status', LiveRoomService::AUDIT_PASS)
                ->where('status', $status)
                ->field([
                    'id', 'live_anchor_id', 'title', 'cover', 'status',
                    'start_time', 'end_time', 'view_count', 'like_count', 'create_time'
                ])
                ->order('start_time', 'desc')
                ->order('id', 'desc')
                ->find();

            if ($room) {
                return $room->toArray();
            }
        }

        return [];
    }

    /**
     * 格式化关注主播。
     *
     * @param array $favorite 关注记录。
     * @param array $anchor 主播记录。
     * @param array $room 直播间记录。
     * @param int $followCount 主播关注人数。
     * @return array
     */
    protected function formatMobileFollowLive(array $favorite, array $anchor, array $room, int $followCount): array
    {
        $anchorId = (int)($anchor['id'] ?? 0);
        $roomId = (int)($room['id'] ?? 0);
        $status = $room === [] ? null : (int)($room['status'] ?? LiveRoomService::STATUS_PENDING);
        $statusText = $this->getLiveStatusText($status);
        $roomTitle = trim((string)($room['title'] ?? ''));
        $topic = $roomTitle !== ''
            ? $roomTitle
            : (trim((string)($anchor['direction'] ?? '')) ?: trim((string)($anchor['description'] ?? '')));

        return [
            'id' => $anchorId,
            'favorite_id' => (int)($favorite['id'] ?? 0),
            'target_type' => self::TARGET_TYPE_LIVE_ANCHOR,
            'target_id' => $anchorId,
            'anchor_id' => $anchorId,
            'merchant_id' => (int)($anchor['merchant_id'] ?? 0),
            'name' => trim((string)($anchor['name'] ?? '')),
            'avatar' => trim((string)($anchor['avatar'] ?? '')),
            'description' => trim((string)($anchor['description'] ?? '')),
            'direction' => trim((string)($anchor['direction'] ?? '')),
            'follow_count' => $followCount,
            'follow_count_text' => $followCount > 0 ? $this->formatCountText($followCount, '粉丝') : '--',
            'room_id' => $roomId,
            'room_title' => $roomTitle,
            'topic' => $topic,
            'live_status' => $status,
            'live_status_text' => $statusText,
            'watching_text' => $this->buildLiveWatchingText($status, (int)($room['view_count'] ?? 0)),
            'badge' => $statusText,
            'badge_type' => $status === LiveRoomService::STATUS_LIVING ? 'pink' : 'gold',
            'route_url' => $roomId > 0 ? '/pages/lives/room/index?id=' . $roomId : '/pages/lives/live/index',
            'start_time' => (string)($room['start_time'] ?? ''),
            'end_time' => (string)($room['end_time'] ?? ''),
            'create_time' => (string)($favorite['create_time'] ?? ''),
            'update_time' => (string)($favorite['update_time'] ?? ''),
        ];
    }

    /**
     * 格式化关注平台。
     *
     * @param array $favorite 关注记录。
     * @return array
     */
    protected function formatMobileFollowPlatform(array $favorite): array
    {
        $siteName = trim((string)db_config(ConfigKeyEnum::SITE_NAME, ''));
        $logo = get_image_url($this->normalizeImageConfigValue(db_config(ConfigKeyEnum::LOGIN_LOGO, '')));
        $followCount = $this->getMobilePlatformFollowCount();

        return [
            'id' => 0,
            'favorite_id' => (int)($favorite['id'] ?? 0),
            'target_type' => self::TARGET_TYPE_PLATFORM,
            'target_id' => 0,
            'merchant_id' => 0,
            'name' => $siteName !== '' ? $siteName : '平台',
            'logo' => $logo,
            'avatar' => $logo,
            'description' => '',
            'category' => '平台',
            'tag' => '已关注',
            'follow_count' => $followCount,
            'follow_count_text' => $this->formatCountText($followCount, '粉丝'),
            'route_url' => '/pages/index/index',
            'create_time' => (string)($favorite['create_time'] ?? ''),
            'update_time' => (string)($favorite['update_time'] ?? ''),
        ];
    }

    /**
     * 获取直播状态文案。
     *
     * @param int|null $status 直播状态。
     * @return string
     */
    protected function getLiveStatusText(?int $status): string
    {
        return match ($status) {
            LiveRoomService::STATUS_LIVING => '直播中',
            LiveRoomService::STATUS_PENDING => '未开播',
            LiveRoomService::STATUS_ENDED => '已结束',
            default => '暂无直播',
        };
    }

    /**
     * 生成直播观看状态文案。
     *
     * @param int|null $status 直播状态。
     * @param int $viewCount 观看人数。
     * @return string
     */
    protected function buildLiveWatchingText(?int $status, int $viewCount): string
    {
        if ($status === LiveRoomService::STATUS_LIVING) {
            return $viewCount > 0 ? $this->formatCountText($viewCount, '人正在观看') : '直播中';
        }

        return $this->getLiveStatusText($status);
    }

    /**
     * 归一化移动端关注类型。
     *
     * @param string $type 类型。
     * @param bool $allowAll 是否允许 all。
     * @return string
     * @throws AdminResponseException
     */
    protected function normalizeMobileFollowType(string $type, bool $allowAll = false): string
    {
        $type = trim($type);
        if (($type === '' || $type === 'all') && $allowAll) {
            return 'all';
        }

        $map = [
            'shop' => self::TARGET_TYPE_MERCHANT,
            'store' => self::TARGET_TYPE_MERCHANT,
            'merchant' => self::TARGET_TYPE_MERCHANT,
            self::TARGET_TYPE_MERCHANT => self::TARGET_TYPE_MERCHANT,
            'live' => self::TARGET_TYPE_LIVE_ANCHOR,
            'anchor' => self::TARGET_TYPE_LIVE_ANCHOR,
            self::TARGET_TYPE_LIVE_ANCHOR => self::TARGET_TYPE_LIVE_ANCHOR,
            'platform' => self::TARGET_TYPE_PLATFORM,
            self::TARGET_TYPE_PLATFORM => self::TARGET_TYPE_PLATFORM,
        ];

        if (!isset($map[$type])) {
            throw new AdminResponseException('关注类型不支持');
        }

        return $map[$type];
    }

    /**
     * 格式化移动端关注类型。
     *
     * @param string $type 内部类型。
     * @return string
     */
    protected function formatMobileFollowType(string $type): string
    {
        return match ($type) {
            self::TARGET_TYPE_MERCHANT => 'shop',
            self::TARGET_TYPE_LIVE_ANCHOR => 'live',
            self::TARGET_TYPE_PLATFORM => 'platform',
            default => 'all',
        };
    }

    /**
     * 店铺关注人数减一。
     *
     * @param int $merchantId 店铺ID。
     * @return void
     */
    protected function decreaseMerchantFollowCount(int $merchantId): void
    {
        if ($merchantId <= 0) {
            return;
        }

        $merchantService = app()->make(MerchantService::class);
        $merchant = $merchantService->get($merchantId, ['id', 'follow_count']);
        if (!$merchant || (int)($merchant['follow_count'] ?? 0) <= 0) {
            return;
        }

        $merchantService->updateStatistics($merchantId, 'follow_count', -1);
    }

    /**
     * 获取平台关注人数。
     *
     * @return int
     */
    protected function getMobilePlatformFollowCount(): int
    {
        return (int)$this->getModel()
            ->where('target_type', self::TARGET_TYPE_PLATFORM)
            ->where('target_id', 0)
            ->where('status', 1)
            ->count();
    }

    /**
     * 获取指定对象关注/收藏人数。
     *
     * @param string $targetType 收藏对象类型。
     * @param int $targetId 收藏对象ID。
     * @return int
     */
    protected function getTargetFollowCount(string $targetType, int $targetId): int
    {
        if ($targetId < 0 || trim($targetType) === '') {
            return 0;
        }

        return (int)$this->getModel()
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->where('status', 1)
            ->count();
    }

    /**
     * 规范化图片配置值，兼容字符串、单图数组和图片列表。
     *
     * @param mixed $value 配置值。
     * @return string
     */
    protected function normalizeImageConfigValue(mixed $value): string
    {
        if (is_string($value) || is_numeric($value)) {
            return (string)$value;
        }

        if (!is_array($value)) {
            return '';
        }

        foreach (['url', 'path', 'filePath', 'src', 'value'] as $key) {
            if (isset($value[$key]) && (is_string($value[$key]) || is_numeric($value[$key]))) {
                return (string)$value[$key];
            }
        }

        $first = reset($value);
        if ($first === false) {
            return '';
        }

        return $this->normalizeImageConfigValue($first);
    }

    /**
     * 归一化 ID 列表。
     *
     * @param array $ids ID列表。
     * @return array
     */
    protected function normalizeIds(array $ids): array
    {
        return array_values(array_filter(array_unique(array_map('intval', $ids)), static fn(int $id): bool => $id > 0));
    }

    /**
     * 格式化数量文案。
     *
     * @param int $count 数量。
     * @param string $suffix 后缀。
     * @return string
     */
    protected function formatCountText(int $count, string $suffix = ''): string
    {
        if ($count >= 10000) {
            $value = rtrim(rtrim(number_format($count / 10000, $count % 10000 === 0 ? 0 : 1, '.', ''), '0'), '.');
            return $value . 'w' . $suffix;
        }

        return $count . $suffix;
    }

    /**
     * 判断收藏对象参数是否有效。
     *
     * @param string $targetType 收藏对象类型。
     * @param int $targetId 收藏对象ID。
     * @return bool
     */
    protected function isValidTarget(string $targetType, int $targetId): bool
    {
        $targetType = trim($targetType);
        if ($targetType === self::TARGET_TYPE_PLATFORM) {
            return $targetId === 0;
        }

        return $targetType !== '' && $targetId > 0;
    }
}
