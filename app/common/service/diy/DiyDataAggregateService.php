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

namespace app\common\service\diy;

use app\common\enum\DiyComponentTypeEnum;
use app\common\enum\AuditStatusEnum;
use app\common\enum\ContentTypeEnum;
use app\common\enum\ProductEnum;
use app\common\exception\AdminResponseException;
use app\common\plugin\PluginRegistry;
use app\common\model\order\Cart;
use app\common\model\setting\system\Category;
use app\common\model\store\StoreCoupon;
use app\common\service\content\ArticleService;
use app\common\service\content\ContentDisplayConfigService;
use app\common\service\content\SpecialService;
use app\common\service\content\TagRelationService;
use app\common\service\content\TagService;
use app\common\service\live\LiveRoomService;
use app\common\service\order\UserPurchaseRecordService;
use app\common\service\setting\CategoryService;
use app\common\service\store\ProductCategoryService;
use app\common\service\store\StoreCouponService;
use app\common\service\store\StoreCouponUserService;
use app\common\service\store\StoreProductService;
use app\common\service\user\UserMemberService;

/**
 * DIY数据聚合服务
 * 根据组件配置聚合各类型数据
 */
class DiyDataAggregateService
{
    /**
     * 设置模型（空实现，此类不需要模型）
     *
     * @return string 空字符串；当前服务仅聚合组件数据，不绑定持久化模型。
     */
    protected function setModel(): string
    {
        return '';
    }

    /**
     * 根据组件类型和配置聚合数据
     *
     * @param string $componentType 装修组件类型标识。
     * @param array $config 当前装修组件保存的来源、筛选、展示数量及样式配置。
     * @param int $userId 当前用户标识，匿名访问时为零。
     * @return array 指定组件的实际展示数据。
     */
    public function aggregate(string $componentType, array $config, int $userId = 0): array
    {
        return match ($componentType) {
            DiyComponentTypeEnum::LIVE => $this->aggregateLive($config),
            DiyComponentTypeEnum::CONTENT => $this->aggregateContent($config, $userId),
            DiyComponentTypeEnum::SPECIAL => $this->aggregateSpecial($config, $userId),
            DiyComponentTypeEnum::PRODUCT => $this->aggregateProduct($config, 1, 0, 0, $userId),
            DiyComponentTypeEnum::SECKILL => $this->aggregateSeckill($config, $userId),
            DiyComponentTypeEnum::COUPON => $this->aggregateCoupon($config, $userId),
            DiyComponentTypeEnum::BANNER => $config['items'] ?? [],
            DiyComponentTypeEnum::NAVIGATION => $config['items'] ?? [],
            DiyComponentTypeEnum::NOTICE => $config['items'] ?? [],
            DiyComponentTypeEnum::IMAGE_CUBE => $config['items'] ?? [],
            DiyComponentTypeEnum::HOT_ZONE => $config['hot_areas'] ?? [],
            default => [],
        };
    }

    /**
     * 按已安装可信清单加载插件聚合器，普通商品聚合器不解释秒杀数据。
     *
     * @param array $config 秒杀编辑器配置
     * @param int $userId 用户或0
     * @return array 独立秒杀组件数据。
     */
    protected function aggregateSeckill(array $config, int $userId): array
    {
        $limit = max(1, min(50, (int)($config['show_count'] ?? 6)));
        $empty = ['list' => [], 'count' => 0, 'page' => 1, 'limit' => $limit, 'session' => null, 'server_time' => time()];
        if ((int)($config['activity_id'] ?? 0) <= 0 || (($config['session_mode'] ?? 'auto') === 'fixed' && (int)($config['session_id'] ?? 0) <= 0)) {
            return $empty;
        }
        if (!app()->make(ContentDisplayConfigService::class)->isDiyComponentEnabled(DiyComponentTypeEnum::SECKILL)) {
            return $empty;
        }
        $metadata = app()->make(PluginRegistry::class)->getPlugin('seckill');
        $class = (string)($metadata['diy_aggregate_service'] ?? $metadata['manifest']['diy_aggregate_service'] ?? '');
        $namespace = trim((string)($metadata['namespace'] ?? $metadata['manifest']['namespace'] ?? ''), '\\');
        if ($namespace === '' || !str_starts_with($class, $namespace . '\\') || !class_exists($class)) {
            throw new AdminResponseException('秒杀组件聚合能力未注册');
        }
        $service = app()->make($class);
        if (!method_exists($service, 'diyData')) {
            throw new AdminResponseException('秒杀组件聚合能力不可用');
        }
        $filters = [
            'activity_id' => max(0, (int)($config['activity_id'] ?? 0)),
            'install_generation' => trim((string)($config['install_generation'] ?? '')),
            'session_id' => ($config['session_mode'] ?? 'auto') === 'fixed' ? max(0, (int)($config['session_id'] ?? 0)) : 0,
            'limit' => $limit,
        ];
        if (($config['selection_mode'] ?? 'all') === 'manual') {
            $filters['product_ids'] = is_array($config['product_ids'] ?? null) ? $config['product_ids'] : [];
        }
        try {
            return $service->diyData($filters, $userId);
        } catch (AdminResponseException $exception) {
            // 活动下架/固定场次失效不让整个装修首页失效；组件保留自己的错误状态。
            return array_merge($empty, ['error' => $exception->getMessage()]);
        }
    }

    /**
     * 聚合移动端单组件按需数据。
     *
     * @param string $componentType 装修组件类型标识。
     * @param array $config 当前装修组件保存的来源、筛选、展示数量及样式配置。
     * @param int $userId 当前用户标识，匿名访问时为零。
     * @param int $categoryId 当前选择的分类标识，未选择时按模块默认范围查询。
     * @param int $page 当前页码，从第一页开始。
     * @param int $limit 每页记录数，零值按组件配置使用默认数量。
     * @return array 当前组件或选中分类的按需数据，分页信息按组件类型和配置可选。
     */
    public function aggregateLazy(
        string $componentType,
        array $config,
        int $userId = 0,
        int $categoryId = 0,
        int $page = 1,
        int $limit = 0
    ): array
    {
        if ($componentType === DiyComponentTypeEnum::CONTENT && $this->isCategoryTabsConfig($config)) {
            return $this->aggregateContentCategoryTabs($config, $categoryId, true, $userId);
        }

        if ($componentType === DiyComponentTypeEnum::SPECIAL && $this->isCategoryTabsConfig($config)) {
            return $this->aggregateSpecialCategoryTabs($config, $categoryId, true, $userId);
        }

        if ($componentType === DiyComponentTypeEnum::PRODUCT && $this->isProductCategoryTabsConfig($config)) {
            return $this->aggregateProductCategoryTabs($config, $categoryId, true, $userId);
        }

        $shouldPaginate = (bool)($config['load_more'] ?? false) || $page > 1 || $limit > 0;
        if ($shouldPaginate) {
            $normalizedLimit = $this->normalizePaginationLimit(
                $limit,
                (int)($config['show_count'] ?? ($componentType === DiyComponentTypeEnum::CONTENT ? 3 : 6))
            );

            if ($componentType === DiyComponentTypeEnum::CONTENT) {
                return $this->aggregateContent($config, $userId, $page, $normalizedLimit);
            }

            if ($componentType === DiyComponentTypeEnum::SPECIAL) {
                return $this->aggregateSpecial($config, $userId, $page, $normalizedLimit);
            }

            if ($componentType === DiyComponentTypeEnum::PRODUCT) {
                return $this->aggregateProduct($config, $page, $normalizedLimit, $categoryId, $userId);
            }
        }

        return $this->aggregate($componentType, $config, $userId);
    }

    /**
     * 聚合移动端骨架数据。
     *
     * @param string $componentType 装修组件类型标识。
     * @param array $config 当前装修组件保存的来源、筛选、展示数量及样式配置。
     * @return array 不加载记录的组件骨架和分类导航。
     */
    public function aggregateSkeleton(string $componentType, array $config): array
    {
        if ($componentType === DiyComponentTypeEnum::CONTENT && $this->isCategoryTabsConfig($config)) {
            return $this->aggregateContentCategoryTabs($config, 0, false);
        }

        if ($componentType === DiyComponentTypeEnum::SPECIAL && $this->isCategoryTabsConfig($config)) {
            return $this->aggregateSpecialCategoryTabs($config, 0, false);
        }

        if ($componentType === DiyComponentTypeEnum::PRODUCT && $this->isProductCategoryTabsConfig($config)) {
            return $this->aggregateProductCategoryTabs($config, 0, false);
        }

        return [];
    }

    /**
     * 聚合直播数据
     *
     * @param array $config 当前装修组件保存的来源、筛选、展示数量及样式配置。
     * @return array 符合组件来源和数量配置的公开直播间。
     */
    protected function aggregateLive(array $config): array
    {
        $showCount = (int)($config['show_count'] ?? 4);
        $dataSource = $config['data_source'] ?? 'auto';
        $showStatus = $config['show_status'] ?? 'all';

        $liveRoomService = app()->make(LiveRoomService::class);
        // 手动指定
        $manualIds = $this->normalizeManualIds($config['manual_ids'] ?? []);
        if ($dataSource === 'manual' && $manualIds) {
            $list = $liveRoomService->getModel()->with(['anchor'])->whereIn('id', $manualIds)
                ->where('audit_status', LiveRoomService::AUDIT_PASS)
                ->order('sort', 'desc')
                ->limit($showCount)
                ->field('id, live_anchor_id, title, cover, description, status as live_status, start_time, view_count, like_count')
                ->select()
                ->toArray();

            return $this->formatLiveRoomList($list);
        }

        // 自动获取
        $query = $liveRoomService->getModel()->with(['anchor'])
            ->where('audit_status', LiveRoomService::AUDIT_PASS);

        // 按分类筛选（支持多选 category_ids）
        if (!empty($config['category_ids'])) {
            $categoryIds = is_array($config['category_ids']) ? $config['category_ids'] : explode(',', $config['category_ids']);
            $query->whereIn('category_id', array_map('intval', $categoryIds));
        }

        if ($showStatus === 'living') {
            $query->where('status', 1);
        } elseif ($showStatus === 'preview') {
            $query->where('status', 0);
        }

        $list = $query->order('sort', 'desc')
            ->order('id', 'desc')
            ->limit($showCount)
            ->field('id, live_anchor_id, title, cover, description, status as live_status, start_time, view_count, like_count')
            ->select()
            ->toArray();

        return $this->formatLiveRoomList($list);
    }

    /**
     * 展开直播间主播信息，供移动端 DIY 卡片直接展示。
     *
     * @param array $list 待附加展示字段的组件记录列表。
     * @return array 附带主播名称、头像和直播状态的直播列表。
     */
    protected function formatLiveRoomList(array $list): array
    {
        return array_map(function (array $item) {
            $anchor = is_array($item['anchor'] ?? null) ? $item['anchor'] : [];
            $item['anchor_name'] = (string)($anchor['name'] ?? '');
            $item['anchor_avatar'] = (string)($anchor['avatar'] ?? '');
            unset($item['anchor']);

            return $item;
        }, $list);
    }

    /**
     * 聚合内容数据
     *
     * @param array $config 当前装修组件保存的来源、筛选、展示数量及样式配置。
     * @param int $userId 当前用户标识，匿名访问时为零。
     * @param int $page 当前页码，从第一页开始。
     * @param int $limit 每页记录数，零值按组件配置使用默认数量。
     * @return array 符合配置的内容列表或分页数据。
     */
    protected function aggregateContent(array $config, int $userId = 0, int $page = 1, int $limit = 0): array
    {
        $showCount = (int)($config['show_count'] ?? 6);
        $displayConfig = app()->make(ContentDisplayConfigService::class);
        if (!$displayConfig->isContentEnabled()) {
            if ($this->isCategoryTabsConfig($config)) {
                return $displayConfig->emptyCategoryTabs();
            }

            return $limit > 0
                ? $this->buildPaginationPayload(
                    [],
                    0,
                    max(1, $page),
                    $this->normalizePaginationLimit($limit, $showCount)
                )
                : [];
        }

        if ($this->isCategoryTabsConfig($config)) {
            return $this->aggregateContentCategoryTabs($config, 0, true, $userId);
        }

        $dataSource = $config['data_source'] ?? 'auto';
        $sortBy = $config['sort_by'] ?? 'create_time';
        $sortOrder = $this->normalizeSortOrder($config['sort_order'] ?? 'desc');
        $memberState = app()->make(UserMemberService::class)->getMemberRuntimeState($userId);

        $articleService = app()->make(ArticleService::class);
        // 手动指定
        $manualIds = $this->normalizeManualIds($config['manual_ids'] ?? []);
        if ($dataSource === 'manual' && $manualIds) {
            $query = $articleService->getModel()->whereIn('id', $manualIds)
                ->where('status', 1)
                ->where('audit_status', AuditStatusEnum::APPROVED);
            if (!(bool)($memberState['member_visible'] ?? false)) {
                $query->where('is_member_only', 0);
            }

            if ($limit > 0) {
                $page = max(1, $page);
                $limit = $this->normalizePaginationLimit($limit, $showCount);
                $total = (int)(clone $query)->count();
                $list = $query
                    ->order('sort', 'desc')
                    ->order('id', 'desc')
                    ->page($page, $limit)
                    ->field('id, mer_id, title, cover, author, description, content_type, price, original_price, view_count, article_category_id, keywords, is_member_only, create_time')
                    ->select()
                    ->toArray();

                return $this->buildPaginationPayload(
                    $this->appendArticleMemberState(
                        $this->appendArticlePurchaseStatus($this->attachArticleTags($list), $userId),
                        $userId
                    ),
                    $total,
                    $page,
                    $limit
                );
            }

            $list = $query
                ->order('sort', 'desc')
                ->limit($showCount)
                ->field('id, mer_id, title, cover, author, description, content_type, price, original_price, view_count, article_category_id, keywords, is_member_only, create_time')
                ->select()
                ->toArray();

            return $this->appendArticleMemberState(
                $this->appendArticlePurchaseStatus($this->attachArticleTags($list), $userId),
                $userId
            );
        }

        // 按分类
        $query = $articleService->getModel()
            ->where('status', 1)
            ->where('audit_status', AuditStatusEnum::APPROVED);
        if (!(bool)($memberState['member_visible'] ?? false)) {
            $query->where('is_member_only', 0);
        }
        if ($dataSource === 'category' && !empty($config['category_id'])) {
            $query->where('article_category_id', $config['category_id']);
        }

        // 排序
        $sortField = match ($sortBy) {
            'view_count' => 'view_count',
            'sales' => 'sales',
            default => 'create_time',
        };

        if ($limit > 0) {
            $page = max(1, $page);
            $limit = $this->normalizePaginationLimit($limit, $showCount);
            $total = (int)(clone $query)->count();
            $list = $query->order($sortField, $sortOrder)
                ->order('id', 'desc')
                ->page($page, $limit)
                ->field('id, mer_id, title, cover, author, description, content_type, price, original_price, view_count, article_category_id, keywords, is_member_only, create_time')
                ->select()
                ->toArray();

            return $this->buildPaginationPayload(
                $this->appendArticleMemberState(
                    $this->appendArticlePurchaseStatus($this->attachArticleTags($list), $userId),
                    $userId
                ),
                $total,
                $page,
                $limit
            );
        }

        $list = $query->order($sortField, $sortOrder)
            ->order('id', 'desc')
            ->limit($showCount)
            ->field('id, mer_id, title, cover, author, description, content_type, price, original_price, view_count, article_category_id, keywords, is_member_only, create_time')
            ->select()
            ->toArray();

        return $this->appendArticleMemberState(
            $this->appendArticlePurchaseStatus($this->attachArticleTags($list), $userId),
            $userId
        );
    }

    /**
     * 聚合专题数据
     *
     * @param array $config 当前装修组件保存的来源、筛选、展示数量及样式配置。
     * @param int $userId 当前用户标识，匿名访问时为零。
     * @param int $page 当前页码，从第一页开始。
     * @param int $limit 每页记录数，零值按组件配置使用默认数量。
     * @return array 符合配置的专题列表或分页数据。
     */
    protected function aggregateSpecial(array $config, int $userId = 0, int $page = 1, int $limit = 0): array
    {
        $showCount = (int)($config['show_count'] ?? 3);
        $displayConfig = app()->make(ContentDisplayConfigService::class);
        if (!$displayConfig->isSpecialEnabled()) {
            if ($this->isCategoryTabsConfig($config)) {
                return $displayConfig->emptyCategoryTabs();
            }

            return $limit > 0
                ? $this->buildPaginationPayload(
                    [],
                    0,
                    max(1, $page),
                    $this->normalizePaginationLimit($limit, $showCount)
                )
                : [];
        }

        if ($this->isCategoryTabsConfig($config)) {
            return $this->aggregateSpecialCategoryTabs($config, 0, true, $userId);
        }

        $dataSource = $config['data_source'] ?? 'auto';
        $memberState = app()->make(UserMemberService::class)->getMemberRuntimeState($userId);

        $specialService = app()->make(SpecialService::class);
        // 手动指定
        $manualIds = $this->normalizeManualIds($config['manual_ids'] ?? []);
        if ($dataSource === 'manual' && $manualIds) {
            $query = $specialService->getModel()->whereIn('id', $manualIds)
                ->where('status', 1)
                ->where('audit_status', AuditStatusEnum::APPROVED);
            if (!(bool)($memberState['member_visible'] ?? false)) {
                $query->where('is_member_only', 0);
            }

            if ($limit > 0) {
                $page = max(1, $page);
                $limit = $this->normalizePaginationLimit($limit, $showCount);
                $total = (int)(clone $query)->count();
                $list = $query
                    ->order('sort', 'desc')
                    ->order('id', 'desc')
                    ->page($page, $limit)
                    ->field('id, merchant_id, category_id, title, cover, description, price, original_price, special_type, content_count, view_count, buy_count, is_member_only, create_time')
                    ->select()
                    ->toArray();

                return $this->buildPaginationPayload(
                    $this->appendSpecialMemberState($this->attachSpecialTags($list), $userId),
                    $total,
                    $page,
                    $limit
                );
            }

            $list = $query
                ->order('sort', 'desc')
                ->limit($showCount)
                ->field('id, merchant_id, category_id, title, cover, description, price, original_price, special_type, content_count, view_count, buy_count, is_member_only, create_time')
                ->select()
                ->toArray();

            return $this->appendSpecialMemberState($this->attachSpecialTags($list), $userId);
        }

        // 自动获取热门
        $query = $specialService->getModel()
            ->where('status', 1)
            ->where('audit_status', AuditStatusEnum::APPROVED);
        if (!(bool)($memberState['member_visible'] ?? false)) {
            $query->where('is_member_only', 0);
        }

        if ($dataSource === 'category' && !empty($config['category_id'])) {
            $query->where('category_id', $config['category_id']);
        }

        if ($dataSource === 'auto') {
            $query->where('is_hot', 1);
        }

        if ($limit > 0) {
            $page = max(1, $page);
            $limit = $this->normalizePaginationLimit($limit, $showCount);
            $total = (int)(clone $query)->count();
            $list = $query
                ->order('sort', 'desc')
                ->order('id', 'desc')
                ->page($page, $limit)
                ->field('id, merchant_id, category_id, title, cover, description, price, original_price, special_type, content_count, view_count, buy_count, is_member_only, create_time')
                ->select()
                ->toArray();

            return $this->buildPaginationPayload(
                $this->appendSpecialMemberState($this->attachSpecialTags($list), $userId),
                $total,
                $page,
                $limit
            );
        }

        $list = $query
            ->order('sort', 'desc')
            ->order('id', 'desc')
            ->limit($showCount)
            ->field('id, merchant_id, category_id, title, cover, description, price, original_price, special_type, content_count, view_count, buy_count, is_member_only, create_time')
            ->select()
            ->toArray();

        return $this->appendSpecialMemberState($this->attachSpecialTags($list), $userId);
    }

    /**
     * 聚合商品数据。
     *
     * @param array $config 当前装修组件保存的来源、筛选、展示数量及样式配置。
     * @param int $page 当前页码，从第一页开始。
     * @param int $limit 每页记录数，零值按组件配置使用默认数量。
     * @param int $categoryId 当前选择的分类标识，未选择时按模块默认范围查询。
     * @param int $userId 当前用户标识，匿名访问时为零。
     * @return array 符合组件来源、分类及数量配置的商品数据。
     */
    protected function aggregateProduct(
        array $config,
        int $page = 1,
        int $limit = 0,
        int $categoryId = 0,
        int $userId = 0
    ): array
    {
        if ($this->isProductCategoryTabsConfig($config) && $categoryId <= 0) {
            return $this->aggregateProductCategoryTabs($config, 0, true, $userId);
        }

        $showCount = max(1, min(50, (int)($config['show_count'] ?? 6)));
        $query = $this->buildProductQuery($config, $categoryId);
        $this->applyProductSort($query, (string)($config['sort_by'] ?? 'default'));

        if ($limit > 0) {
            $page = max(1, $page);
            $limit = $this->normalizePaginationLimit($limit, $showCount);
            $total = (int)(clone $query)->count();
            $list = $query
                ->page($page, $limit)
                ->field('id, merchant_id, name, cover, price, ot_price, sales, virtual_sales, stock, unit, goods_type, spec_type, is_free_shipping, category_id, brand_id')
                ->select()
                ->toArray();

            return $this->buildPaginationPayload($this->formatProductList($list, $userId), $total, $page, $limit);
        }

        $list = $query
            ->limit($showCount)
            ->field('id, merchant_id, name, cover, price, ot_price, sales, virtual_sales, stock, unit, goods_type, spec_type, is_free_shipping, category_id, brand_id')
            ->select()
            ->toArray();

        return $this->formatProductList($list, $userId);
    }

    /**
     * 构造商品基础查询。
     *
     * @param array $config 当前装修组件保存的来源、筛选、展示数量及样式配置。
     * @param int $categoryId 当前选择的分类标识，未选择时按模块默认范围查询。
     * @return mixed 限定公开商品并应用来源配置的查询对象。
     */
    protected function buildProductQuery(array $config, int $categoryId = 0)
    {
        $query = app()->make(StoreProductService::class)->getModel()
            ->where('status', ProductEnum::STATUS_OPEN)
            ->where('audit_status', ProductEnum::AUDIT_STATUS_PASSED);

        if ($categoryId > 0) {
            $this->applyProductCategoryScope($query, $categoryId);
            return $query;
        }

        $sourceType = (string)($config['source_type'] ?? $config['data_source'] ?? 'manual');
        if (!in_array($sourceType, ['manual', 'category', 'tag', 'brand', 'auto'], true)) {
            $sourceType = 'manual';
        }

        if ($sourceType === 'manual') {
            $manualIds = $this->normalizeManualIds($config['manual_ids'] ?? []);
            $query->whereIn('id', $manualIds === [] ? [0] : $manualIds);
            return $query;
        }

        if ($sourceType === 'category') {
            $this->applyProductCategoryScope($query, (int)($config['category_id'] ?? 0));
            return $query;
        }

        if ($sourceType === 'tag') {
            $productIds = $this->getProductIdsByTagId((int)($config['tag_id'] ?? 0));
            $query->whereIn('id', $productIds === [] ? [0] : $productIds);
            return $query;
        }

        if ($sourceType === 'brand') {
            $brandId = (int)($config['brand_id'] ?? 0);
            if ($brandId <= 0) {
                $query->whereIn('id', [0]);
            } else {
                $query->where('brand_id', $brandId);
            }
        }

        return $query;
    }

    /**
     * 应用商品分类范围：一级分类包含自身及全部下级分类。
     *
     * @param mixed $query 待追加业务筛选或排序条件的查询对象。
     * @param int $categoryId 当前选择的商品分类标识，小于等于零时强制查询为空结果。
     * @return void 无返回值。
     */
    protected function applyProductCategoryScope($query, int $categoryId): void
    {
        if ($categoryId <= 0) {
            $query->whereIn('id', [0]);
            return;
        }

        $categoryIds = app()->make(ProductCategoryService::class)->getCategoryAndChildIds($categoryId, 0);
        $query->whereIn('category_id', $categoryIds === [] ? [0] : $categoryIds);
    }

    /**
     * 应用商品排序。
     *
     * @param mixed $query 待追加业务筛选或排序条件的查询对象。
     * @param string $sortBy 排序字段或预定义排序类型。
     * @return void 无返回值。
     */
    protected function applyProductSort($query, string $sortBy): void
    {
        if ($sortBy === 'sales') {
            $query->orderRaw('(sales + virtual_sales) desc')->order('id', 'desc');
            return;
        }

        if ($sortBy === 'price') {
            $query->order('price', 'asc')->order('id', 'desc');
            return;
        }

        $query->order('sort', 'desc')->order('id', 'desc');
    }

    /**
     * 为商品列表附加标签、展示销量和展示文案。
     *
     * @param array $list 待附加展示字段的组件记录列表。
     * @param int $userId 当前用户标识，匿名访问时为零。
     * @return array 附带标签、展示销量和会员状态的商品列表。
     */
    protected function formatProductList(array $list, int $userId = 0): array
    {
        if (!$list) {
            return [];
        }

        $list = app()->make(TagService::class)->attachTagsToList($list, TagService::RELATION_TYPE_PRODUCT);
        $list = array_map(function (array $item): array {
            $price = max(0, (float)($item['price'] ?? 0));
            $otPrice = max(0, (float)($item['ot_price'] ?? 0));
            $displaySales = (int)($item['sales'] ?? 0) + (int)($item['virtual_sales'] ?? 0);

            $item['title'] = (string)($item['name'] ?? '');
            $item['image'] = (string)($item['cover'] ?? '');
            $item['display_sales'] = $displaySales;
            $item['sales_text'] = '已售' . $displaySales . '件';
            $item['price_text'] = '¥' . number_format($price, 2, '.', '');
            $item['original_price_text'] = $otPrice > $price ? '¥' . number_format($otPrice, 2, '.', '') : '';

            return $item;
        }, $list);

        return $this->appendProductMemberState($list, $userId);
    }

    /**
     * 为商品列表附加会员价格展示状态。
     *
     * @param array<int, array<string, mixed>> $list 商品列表。
     * @param int $userId 用户 ID。
     * @return array<int, array<string, mixed>> 附带会员身份和价格展示字段的商品列表。
     */
    protected function appendProductMemberState(array $list, int $userId = 0): array
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
     * 根据商品标签反查商品 ID。
     *
     * @param int $tagId 标签记录标识。
     * @return array<int> 与指定标签关联的商品标识集合。
     */
    protected function getProductIdsByTagId(int $tagId): array
    {
        if ($tagId <= 0) {
            return [];
        }

        return app()->make(TagRelationService::class)->getRelationIdsByTagId(
            TagService::RELATION_TYPE_PRODUCT,
            $tagId
        );
    }

    /**
     * 构造分页返回结构。
     *
     * @param array<int, array<string, mixed>> $list 已格式化的记录列表，原样放入分页响应。
     * @param int $total 当前筛选范围内的总记录数。
     * @param int $page 当前页码，从第一页开始。
     * @param int $limit 每页记录数，小于一时规范化为一。
     * @return array<string, mixed> 列表、总数、页码和是否还有下一页的统一结构。
     */
    protected function buildPaginationPayload(array $list, int $total, int $page, int $limit): array
    {
        $limit = max(1, $limit);
        $page = max(1, $page);
        $totalPage = $total > 0 ? (int)ceil($total / $limit) : 0;

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'page_size' => $limit,
            'totalPage' => $totalPage,
            'has_more' => $page < $totalPage,
        ];
    }

    /**
     * 规范分页条数。
     *
     * @param int $limit 每页记录数，零值按组件配置使用默认数量。
     * @param int $fallback 输入无效或缺失时使用的默认数量。
     * @return int 经默认值及边界约束后的分页数量。
     */
    protected function normalizePaginationLimit(int $limit, int $fallback): int
    {
        $normalized = $limit > 0 ? $limit : $fallback;
        return max(1, min(50, $normalized));
    }

    /**
     * 为内容列表附加真实标签。
     *
     * @param array $list 待附加展示字段的组件记录列表。
     * @return array 附带真实标签的内容列表。
     */
    protected function attachArticleTags(array $list): array
    {
        return app()->make(TagService::class)->attachTagsToList($list, TagService::RELATION_TYPE_ARTICLE);
    }

    /**
     * 为内容列表附加当前用户已购状态。
     *
     * @param array<int, array<string, mixed>> $list 内容列表。
     * @param int $userId 用户 ID，未登录时为 0。
     * @return array<int, array<string, mixed>> 附带当前用户已购状态的内容列表。
     */
    protected function appendArticlePurchaseStatus(array $list, int $userId): array
    {
        if (!$list) {
            return [];
        }

        $articleIds = $this->normalizeIds(array_column($list, 'id'));
        $purchasedMap = [];
        if ($userId > 0 && $articleIds) {
            $purchasedMap = app()->make(UserPurchaseRecordService::class)->getPurchasedMap($userId, [
                Cart::ORDER_TYPE_CONTENT => $articleIds,
            ]);
        }

        return array_map(function (array $item) use ($purchasedMap): array {
            $articleId = (int)($item['id'] ?? 0);
            $isPurchased = isset($purchasedMap[Cart::ORDER_TYPE_CONTENT . '_' . $articleId]);
            $item['is_purchased'] = $isPurchased;
            $item['has_buy'] = $isPurchased ? 1 : 0;
            $item['is_bought'] = $isPurchased ? 1 : 0;

            return $item;
        }, $list);
    }

    /**
     * 为内容列表附加会员价格展示状态。
     *
     * @param array<int, array<string, mixed>> $list 内容列表。
     * @param int $userId 用户 ID。
     * @return array<int, array<string, mixed>> 附带会员价格展示状态的内容列表。
     */
    protected function appendArticleMemberState(array $list, int $userId = 0): array
    {
        if ($list === []) {
            return [];
        }

        $memberService = app()->make(UserMemberService::class);
        return array_map(function (array $item) use ($memberService, $userId): array {
            $price = max(0, (float)($item['price'] ?? 0));
            $preview = $memberService->getMemberPricePreview(
                (int)($item['mer_id'] ?? 0),
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
     * 为专题列表附加真实标签。
     *
     * @param array $list 待附加展示字段的组件记录列表。
     * @return array 附带真实标签的专题列表。
     */
    protected function attachSpecialTags(array $list): array
    {
        return app()->make(TagService::class)->attachTagsToList($list, TagService::RELATION_TYPE_SPECIAL);
    }

    /**
     * 为专题列表附加会员价格展示状态。
     *
     * @param array<int, array<string, mixed>> $list 专题列表。
     * @param int $userId 用户 ID。
     * @return array<int, array<string, mixed>> 附带会员价格展示状态的专题列表。
     */
    protected function appendSpecialMemberState(array $list, int $userId = 0): array
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
     * 聚合优惠券数据
     *
     * @param array $config 当前装修组件保存的来源、筛选、展示数量及样式配置。
     * @param int $userId 当前用户标识，匿名访问时为零。
     * @return array 符合展示配置的优惠券及当前用户领取状态。
     */
    protected function aggregateCoupon(array $config, int $userId = 0): array
    {
        $showCount = (int)($config['show_count'] ?? 4);
        $dataSource = $config['data_source'] ?? 'auto';
        $scopeType = $this->normalizeCouponScopeType($config['scope_type'] ?? null);
        $displayStyle = (string)($config['display_style'] ?? $config['style'] ?? '');
        $now = date('Y-m-d H:i:s');

        $storeCoupon = app()->make(StoreCouponService::class);

        // 手动指定
        $manualIds = $this->getConfiguredCouponIds($config);
        if (($dataSource === 'manual' || $displayStyle === 'large_image') && $manualIds) {
            return $this->appendCouponReceiveStatus(
                $this->getCouponListByIds($storeCoupon, $manualIds),
                $userId
            );
        }

        // 自动获取可领取的优惠券
        $query = $storeCoupon->getModel()->where('status', 1)
            ->where(function ($query) use ($now) {
                $this->applyAvailableCouponQuery($query, $now);
            });

        // 按适用范围筛选
        if ($scopeType !== null) {
            $query->where('scope_type', $scopeType);
        }

        $list = $query->order('sort', 'desc')
            ->order('id', 'desc')
            ->limit($showCount)
            ->field('id, merchant_id, name, discount_amount, min_order_amount, valid_days, expire_type, valid_start_time, valid_end_time, scope_type, status, remain_quantity, is_quantity_limited, is_time_limited, receive_start_time, receive_end_time')
            ->select()
            ->toArray();

        return $this->appendCouponReceiveStatus($list, $userId);
    }

    /**
     * 按配置 ID 查询优惠券，并保持配置顺序。
     *
     * @param array<int, int> $couponIds 按展示顺序配置的优惠券标识集合。
     * @param StoreCouponService $storeCoupon 用于查询优惠券的业务服务实例。
     * @return array<int, array<string, mixed>> 按配置顺序返回的已启用自选券，领取可用性由后续步骤补充。
     */
    protected function getCouponListByIds(StoreCouponService $storeCoupon, array $couponIds): array
    {
        $list = $storeCoupon->getModel()->whereIn('id', $couponIds)
            ->where('status', StoreCoupon::STATUS_OPEN)
            ->field('id, merchant_id, name, discount_amount, min_order_amount, valid_days, expire_type, valid_start_time, valid_end_time, scope_type, status, remain_quantity, is_quantity_limited, is_time_limited, receive_start_time, receive_end_time')
            ->select()
            ->toArray();

        if (!$list) {
            return [];
        }

        $map = [];
        foreach ($list as $item) {
            $map[(int)($item['id'] ?? 0)] = $item;
        }

        $sorted = [];
        foreach ($couponIds as $couponId) {
            if (isset($map[$couponId])) {
                $sorted[] = $map[$couponId];
            }
        }

        return $sorted;
    }

    /**
     * 应用移动端可领取优惠券筛选条件。
     *
     * @param mixed $query 待追加业务筛选或排序条件的查询对象。
     * @param string $now 本次查询统一使用的当前时间。
     * @return void 无返回值。
     */
    protected function applyAvailableCouponQuery($query, string $now): void
    {
        $query->where(function ($quantityQuery) {
            $quantityQuery->where('is_quantity_limited', 0)
                ->whereOr(function ($subQuery) {
                    $subQuery->where('is_quantity_limited', 1)
                        ->where('remain_quantity', '>', 0);
                });
        })->where(function ($timeQuery) use ($now) {
            $timeQuery->where('is_time_limited', 0)
                ->whereOr(function ($subQuery) use ($now) {
                    $subQuery->where('is_time_limited', 1)
                        ->where('receive_start_time', '<=', $now)
                        ->where('receive_end_time', '>=', $now);
                });
        });
    }

    /**
     * 为 DIY 优惠券补充当前用户领取状态。
     *
     * @param array<int, array<string, mixed>> $list 待附加展示字段的组件记录列表。
     * @param int $userId 当前用户标识，匿名访问时为零。
     * @return array<int, array<string, mixed>> 附带可领取、已领取、禁用状态及按钮文案的优惠券列表。
     */
    protected function appendCouponReceiveStatus(array $list, int $userId): array
    {
        if (!$list) {
            return [];
        }

        $receivedMap = [];
        if ($userId > 0) {
            $couponIdsByMerchant = [];
            foreach ($list as $item) {
                $couponId = (int)($item['id'] ?? 0);
                if ($couponId <= 0) {
                    continue;
                }
                $merchantId = (int)($item['merchant_id'] ?? 0);
                $couponIdsByMerchant[$merchantId][] = $couponId;
            }

            $userCouponService = app()->make(StoreCouponUserService::class);
            foreach ($couponIdsByMerchant as $merchantId => $couponIds) {
                $receivedIds = $userCouponService->getActiveReceivedCouponIds($userId, $couponIds, (int)$merchantId);
                foreach ($receivedIds as $receivedId) {
                    $receivedMap[(int)$merchantId . ':' . (int)$receivedId] = true;
                }
            }
        }

        return array_map(function (array $item) use ($receivedMap): array {
            $couponId = (int)($item['id'] ?? 0);
            $merchantId = (int)($item['merchant_id'] ?? 0);
            $received = isset($receivedMap[$merchantId . ':' . $couponId]);
            $scopeType = (int)($item['scope_type'] ?? StoreCoupon::SCOPE_TYPE_SHOP);
            $expireType = (int)($item['expire_type'] ?? StoreCoupon::EXPIRE_TYPE_DAYS);
            $validDays = (int)($item['valid_days'] ?? 0);
            $validStartTime = (string)($item['valid_start_time'] ?? '');
            $validEndTime = (string)($item['valid_end_time'] ?? '');

            $item['coupon_id'] = $couponId;
            $item['scope_type_text'] = $this->getCouponScopeTypeText($scopeType);
            $item['tag'] = $item['scope_type_text'];
            $item['expire_text'] = $this->getCouponExpireText($expireType, $validDays, $validStartTime, $validEndTime);
            $item['valid_text'] = $item['expire_text'];
            $unavailableText = $this->getCouponUnavailableText($item);
            $disabled = $received || $unavailableText !== '';
            $item['available'] = !$disabled;
            $item['received'] = $received;
            $item['disabled'] = $disabled;
            $item['button_text'] = $received ? '已领取' : ($unavailableText ?: '领取');

            return $item;
        }, $list);
    }

    /**
     * 获取优惠券适用范围文案
     *
     * @param int $scopeType 优惠券适用范围枚举或历史兼容值。
     * @return string 优惠券适用范围的中文文案。
     */
    protected function getCouponScopeTypeText(int $scopeType): string
    {
        return match ($scopeType) {
            StoreCoupon::SCOPE_TYPE_PLATFORM => '通用券',
            StoreCoupon::SCOPE_TYPE_CATEGORY => '品类券',
            StoreCoupon::SCOPE_TYPE_SHOP => '店铺券',
            default => '优惠券',
        };
    }

    /**
     * 获取优惠券不可领取原因。
     *
     * @param array $coupon 当前优惠券的业务数据。
     * @return string 优惠券不可领取的原因；可领取时为空字符串。
     */
    protected function getCouponUnavailableText(array $coupon): string
    {
        if ((int)($coupon['status'] ?? StoreCoupon::STATUS_OPEN) !== StoreCoupon::STATUS_OPEN) {
            return '不可领取';
        }

        if ((int)($coupon['is_quantity_limited'] ?? 0) === 1 && (int)($coupon['remain_quantity'] ?? 0) <= 0) {
            return '已领完';
        }

        if ((int)($coupon['is_time_limited'] ?? 0) !== 1) {
            return '';
        }

        $now = time();
        $receiveStartTime = (string)($coupon['receive_start_time'] ?? '');
        $receiveEndTime = (string)($coupon['receive_end_time'] ?? '');

        if ($receiveStartTime !== '' && strtotime($receiveStartTime) > $now) {
            return '未开始';
        }

        if ($receiveEndTime !== '' && strtotime($receiveEndTime) < $now) {
            return '已结束';
        }

        return '';
    }

    /**
     * 获取优惠券有效期文案
     *
     * @param int $expireType 优惠券有效期类型。
     * @param int $validDays 领取后有效的天数。
     * @param string $validStartTime 固定有效期的开始时间。
     * @param string $validEndTime 固定有效期的结束时间。
     * @return string 固定或相对有效期的展示文案。
     */
    protected function getCouponExpireText(
        int $expireType,
        int $validDays,
        string $validStartTime,
        string $validEndTime
    ): string {
        if ($expireType === StoreCoupon::EXPIRE_TYPE_DAYS) {
            return $validDays > 0 ? '领取后 ' . $validDays . ' 天内有效' : '领取后有效';
        }

        if ($validEndTime !== '') {
            return '有效期至 ' . date('Y.m.d', strtotime($validEndTime));
        }

        if ($validStartTime !== '') {
            return '有效期自 ' . date('Y.m.d', strtotime($validStartTime));
        }

        return '有效期以券面为准';
    }

    /**
     * 获取优惠券组件中配置的优惠券 ID。
     *
     * 兼容旧版编辑器可能写入的 manual_ids、coupon_ids、items、list 等字段。
     *
     * @param array $config 当前装修组件保存的来源、筛选、展示数量及样式配置。
     * @return array<int, int> 从兼容配置字段提取的自选优惠券标识集合。
     */
    protected function getConfiguredCouponIds(array $config): array
    {
        $ids = [];
        foreach (['manual_ids', 'coupon_ids', 'items', 'list'] as $field) {
            if (array_key_exists($field, $config)) {
                $ids = array_merge($ids, $this->normalizeManualIds($config[$field]));
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * 归一化优惠券适用范围。
     *
     * 后台旧编辑器曾把“全部”保存为 0、“店铺券”保存为 20；数据库中店铺券为 0。
     * 返回 null 表示不按范围筛选。
     *
     * @param mixed $scopeType 优惠券适用范围枚举或历史兼容值。
     * @return int|null 兼容历史值的适用范围枚举；不筛选范围时为空。
     */
    protected function normalizeCouponScopeType(mixed $scopeType): ?int
    {
        if ($scopeType === '' || $scopeType === null) {
            return null;
        }

        $value = (int)$scopeType;
        if ($value === 20) {
            return StoreCoupon::SCOPE_TYPE_SHOP;
        }

        if (in_array($value, [
            StoreCoupon::SCOPE_TYPE_SHOP,
            StoreCoupon::SCOPE_TYPE_PRODUCT,
            StoreCoupon::SCOPE_TYPE_PLATFORM,
            StoreCoupon::SCOPE_TYPE_CATEGORY,
        ], true)) {
            return $value === 0 ? null : $value;
        }

        return null;
    }

    /**
     * 聚合内容分类标签数据。
     *
     * @param array $config 当前装修组件保存的来源、筛选、展示数量及样式配置。
     * @param int $selectedCategoryId 当前选中的分类标识，用于匹配激活导航。
     * @param bool $loadItems 是否加载选中分类下的资源，关闭时只返回导航骨架。
     * @param int $userId 当前用户标识，匿名访问时为零。
     * @return array 内容一级分类、激活分类及可选的内容数据。
     */
    protected function aggregateContentCategoryTabs(
        array $config,
        int $selectedCategoryId = 0,
        bool $loadItems = true,
        int $userId = 0
    ): array {
        $displayConfig = app()->make(ContentDisplayConfigService::class);
        if (!$displayConfig->isContentEnabled()) {
            return $displayConfig->emptyCategoryTabs();
        }

        $showCount = max(1, (int)($config['show_count'] ?? 4));
        $categoryIds = $this->getConfiguredCategoryIds($config);
        $categories = $this->getEnabledTopCategories($categoryIds, CategoryService::ARTICLE_TYPE);
        $contentType = $this->normalizeContentType($config['content_type'] ?? 'all');
        $sortField = $this->resolveContentSortField((string)($config['sort_by'] ?? 'create_time'));
        $sortOrder = $this->normalizeSortOrder($config['sort_order'] ?? 'desc');
        $articleService = app()->make(ArticleService::class);
        $memberState = app()->make(UserMemberService::class)->getMemberRuntimeState($userId);

        return $this->buildCategoryTabsPayload($categories, function (int $categoryId) use (
            $articleService,
            $contentType,
            $showCount,
            $sortField,
            $sortOrder,
            $userId,
            $memberState
        ): array {
            $scopeIds = $this->getCategoryScopeIds($categoryId, CategoryService::ARTICLE_TYPE);
            if (!$scopeIds) {
                return [];
            }

            $query = $articleService->getModel()
                ->where('status', 1)
                ->where('audit_status', AuditStatusEnum::APPROVED)
                ->whereIn('article_category_id', $scopeIds);

            if (!(bool)($memberState['member_visible'] ?? false)) {
                $query->where('is_member_only', 0);
            }

            if ($contentType > 0) {
                $query->where('content_type', $contentType);
            }

            $list = $query->order($sortField, $sortOrder)
                ->order('id', 'desc')
                ->limit($showCount)
                ->field('id, mer_id, title, cover, description, content_type, price, original_price, view_count, article_category_id, keywords, is_member_only')
                ->select()
                ->toArray();

            return $this->appendArticleMemberState(
                $this->appendArticlePurchaseStatus($this->attachArticleTags($list), $userId),
                $userId
            );
        }, $selectedCategoryId, $loadItems);
    }

    /**
     * 聚合专题分类标签数据。
     *
     * @param array $config 当前装修组件保存的来源、筛选、展示数量及样式配置。
     * @param int $selectedCategoryId 当前选中的分类标识，用于匹配激活导航。
     * @param bool $loadItems 是否加载选中分类下的资源，关闭时只返回导航骨架。
     * @param int $userId 当前用户标识，匿名访问时为零。
     * @return array 专题一级分类、激活分类及可选的专题数据。
     */
    protected function aggregateSpecialCategoryTabs(
        array $config,
        int $selectedCategoryId = 0,
        bool $loadItems = true,
        int $userId = 0
    ): array {
        $displayConfig = app()->make(ContentDisplayConfigService::class);
        if (!$displayConfig->isSpecialEnabled()) {
            return $displayConfig->emptyCategoryTabs();
        }

        $showCount = max(1, (int)($config['show_count'] ?? 4));
        $categoryIds = $this->getConfiguredCategoryIds($config);
        $categories = $this->getEnabledTopCategories($categoryIds, CategoryService::SPECIAL_TYPE);
        $specialService = app()->make(SpecialService::class);
        $memberState = app()->make(UserMemberService::class)->getMemberRuntimeState($userId);

        return $this->buildCategoryTabsPayload($categories, function (int $categoryId) use (
            $specialService,
            $showCount,
            $userId,
            $memberState
        ): array {
            $scopeIds = $this->getCategoryScopeIds($categoryId, CategoryService::SPECIAL_TYPE);
            if (!$scopeIds) {
                return [];
            }

            $list = $specialService->getModel()
                ->where('status', 1)
                ->where('audit_status', AuditStatusEnum::APPROVED)
                ->whereIn('category_id', $scopeIds)
                ->when(!(bool)($memberState['member_visible'] ?? false), fn($query) => $query->where('is_member_only', 0))
                ->order('sort', 'desc')
                ->order('id', 'desc')
                ->limit($showCount)
                ->field('id, merchant_id, title, cover, description, price, original_price, special_type, content_count, view_count, buy_count, category_id, keywords, is_member_only')
                ->select()
                ->toArray();

            return $this->appendSpecialMemberState($this->attachSpecialTags($list), $userId);
        }, $selectedCategoryId, $loadItems);
    }

    /**
     * 聚合商品分类导航数据。
     *
     * @param array $config 当前装修组件保存的来源、筛选、展示数量及样式配置。
     * @param int $selectedCategoryId 当前选中的分类标识，用于匹配激活导航。
     * @param bool $loadItems 是否加载选中分类下的资源，关闭时只返回导航骨架。
     * @param int $userId 当前用户标识，匿名访问时为零。
     * @return array 商品一级分类、激活分类及可选的商品数据。
     */
    protected function aggregateProductCategoryTabs(
        array $config,
        int $selectedCategoryId = 0,
        bool $loadItems = true,
        int $userId = 0
    ): array {
        $showCount = max(1, min(50, (int)($config['show_count'] ?? 6)));
        $categories = $this->getEnabledProductTabCategories($config);

        return $this->buildCategoryTabsPayload($categories, function (int $categoryId) use (
            $config,
            $showCount,
            $userId
        ): array {
            $query = $this->buildProductQuery($config, $categoryId);
            $this->applyProductSort($query, (string)($config['sort_by'] ?? 'default'));
            $list = $query
                ->limit($showCount)
                ->field('id, merchant_id, name, cover, price, ot_price, sales, virtual_sales, stock, unit, goods_type, spec_type, is_free_shipping, category_id, brand_id')
                ->select()
                ->toArray();

            return $this->formatProductList($list, $userId);
        }, $selectedCategoryId, $loadItems);
    }

    /**
     * 判断商品组件是否开启分类导航。
     *
     * @param array $config 当前装修组件保存的来源、筛选、展示数量及样式配置。
     * @return bool 商品组件是否开启分类导航。
     */
    private function isProductCategoryTabsConfig(array $config): bool
    {
        return $this->normalizeBoolean($config['with_nav'] ?? false) || $this->isCategoryTabsConfig($config);
    }

    /**
     * 获取商品组件配置的启用一级分类导航。
     *
     * @param array $config 当前装修组件保存的来源、筛选、展示数量及样式配置。
     * @return array<int, array<string, mixed>> 按组件配置顺序返回的启用商品一级分类。
     */
    private function getEnabledProductTabCategories(array $config): array
    {
        $configuredTabs = $this->normalizeProductCategoryTabs(
            $config['category_tabs'] ?? [],
            $config['category_ids'] ?? []
        );
        $categoryIds = $this->normalizeIds($configuredTabs);
        if (!$categoryIds) {
            return [];
        }

        $enabledCategories = $this->getEnabledTopCategories($categoryIds, CategoryService::PRODUCT_TYPE);
        $enabledMap = [];
        foreach ($enabledCategories as $category) {
            $enabledMap[(int)($category['id'] ?? 0)] = $category;
        }

        $tabs = [];
        foreach ($configuredTabs as $tab) {
            $categoryId = (int)($tab['id'] ?? 0);
            if ($categoryId <= 0 || !isset($enabledMap[$categoryId])) {
                continue;
            }

            $category = $enabledMap[$categoryId];
            $tabs[] = [
                'id' => $categoryId,
                'name' => (string)($tab['name'] ?: ($category['name'] ?? '')),
                'subtitle' => (string)($tab['subtitle'] ?? ''),
                'image' => (string)($tab['image'] ?: ($category['image'] ?? '')),
            ];
        }

        return $tabs;
    }

    /**
     * 归一化商品分类导航配置。
     *
     * @param mixed $tabs 分类导航配置，兼容历史存储形态。
     * @param mixed $fallbackIds 导航配置缺省时可使用的分类标识。
     * @return array<int, array{id:int,name:string,subtitle:string,image:string}> 保留配置顺序的商品分类导航项。
     */
    private function normalizeProductCategoryTabs(mixed $tabs, mixed $fallbackIds = []): array
    {
        $source = is_array($tabs) && $tabs !== [] ? $tabs : $this->normalizeIds($fallbackIds);
        $result = [];
        foreach ($source as $item) {
            if (is_array($item)) {
                $id = (int)($item['id'] ?? ($item['value'] ?? 0));
                if ($id <= 0) {
                    continue;
                }
                $result[] = [
                    'id' => $id,
                    'name' => (string)($item['name'] ?? ($item['label'] ?? '')),
                    'subtitle' => (string)($item['subtitle'] ?? ''),
                    'image' => (string)($item['image'] ?? ''),
                ];
                continue;
            }

            $id = (int)$item;
            if ($id > 0) {
                $result[] = [
                    'id' => $id,
                    'name' => '',
                    'subtitle' => '',
                    'image' => '',
                ];
            }
        }

        return array_values($result);
    }

    /**
     * 判断是否为分类标签样式。
     *
     * @param array $config 当前装修组件保存的来源、筛选、展示数量及样式配置。
     * @return bool 组件是否采用分类标签样式。
     */
    private function isCategoryTabsConfig(array $config): bool
    {
        return ($config['display_style'] ?? '') === 'category_tabs'
            || ($config['data_source'] ?? '') === 'category_tabs';
    }

    /**
     * 获取分类标签配置中的分类 ID。
     *
     * @param array $config 当前装修组件保存的来源、筛选、展示数量及样式配置。
     * @return array<int, int> 从分类标签配置提取的分类标识集合。
     */
    private function getConfiguredCategoryIds(array $config): array
    {
        return $this->normalizeIds(array_merge(
            $this->normalizeIds($config['category_ids'] ?? []),
            $this->normalizeIds($config['category_tabs'] ?? [])
        ));
    }

    /**
     * 归一化 ID 数组，兼容逗号字符串、纯 ID 和含 id 的对象数组。
     *
     * @param mixed $value 需要校验或规范化的原始输入值。
     * @return array<int, int> 去重并保留有效顺序的正整数标识集合。
     */
    private function normalizeIds(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (!is_array($value)) {
            $value = [$value];
        }

        $ids = [];
        foreach ($value as $item) {
            $id = is_array($item) ? ($item['id'] ?? ($item['value'] ?? 0)) : $item;
            $id = (int)$id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * 归一化布尔配置，兼容装修 JSON 中的字符串值。
     *
     * @param mixed $value 需要校验或规范化的原始输入值。
     * @return bool 规范化后的布尔配置值。
     */
    private function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int)$value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    /**
     * 获取启用的一级分类，并保持用户配置顺序。
     *
     * @param array $categoryIds 需要查询或展示的分类标识集合。
     * @param int $type 内容或专题分类的类型枚举。
     * @return array<int, array<string, mixed>> 按配置顺序返回的启用一级分类。
     */
    private function getEnabledTopCategories(array $categoryIds, int $type): array
    {
        $categoryIds = $this->normalizeIds($categoryIds);
        if (!$categoryIds) {
            return [];
        }

        $list = Category::whereIn('id', $categoryIds)
            ->where('type', $type)
            ->where('owner_id', 0)
            ->where('pid', 0)
            ->where('status', 1)
            ->field('id, name, image')
            ->select()
            ->toArray();

        if (!$list) {
            return [];
        }

        $map = [];
        foreach ($list as $item) {
            $map[(int)$item['id']] = $item;
        }

        $sorted = [];
        foreach ($categoryIds as $categoryId) {
            if (isset($map[$categoryId])) {
                $sorted[] = $map[$categoryId];
            }
        }

        return $sorted;
    }

    /**
     * 获取一级分类自身及启用的直属二级分类。
     *
     * @param int $categoryId 当前一级分类标识，小于等于零时返回空标识集合。
     * @param int $type 内容或专题分类的类型枚举。
     * @return array<int, int> 一级分类自身及启用直属二级分类的标识。
     */
    private function getCategoryScopeIds(int $categoryId, int $type): array
    {
        if ($categoryId <= 0) {
            return [];
        }

        $childIds = Category::where('pid', $categoryId)
            ->where('type', $type)
            ->where('owner_id', 0)
            ->where('status', 1)
            ->column('id');

        return $this->normalizeIds(array_merge([$categoryId], $childIds));
    }

    /**
     * 构造分类标签聚合结构。
     *
     * @param array<int, array<string, mixed>> $categories 可用分类导航或分类记录集合。
     * @param callable $itemsResolver 按分类范围查询记录的回调。
     * @param int $selectedCategoryId 当前选中的分类标识，用于匹配激活导航。
     * @param bool $loadItems 是否加载选中分类下的资源，关闭时只返回导航骨架。
     * @return array 分类导航、激活标识及可选记录的统一结构。
     */
    private function buildCategoryTabsPayload(
        array $categories,
        callable $itemsResolver,
        int $selectedCategoryId = 0,
        bool $loadItems = true
    ): array {
        $activeCategoryId = $this->resolveActiveCategoryId($categories, $selectedCategoryId);
        $list = ($loadItems && $activeCategoryId > 0) ? $itemsResolver($activeCategoryId) : [];
        $tabs = [];
        foreach ($categories as $category) {
            $categoryId = (int)($category['id'] ?? 0);
            if ($categoryId <= 0) {
                continue;
            }

            $tabs[] = [
                'id' => $categoryId,
                'name' => (string)($category['name'] ?? ''),
                'subtitle' => (string)($category['subtitle'] ?? ($category['description'] ?? '')),
                'image' => (string)($category['image'] ?? ''),
                'items' => [],
            ];
        }

        return [
            'mode' => 'category_tabs',
            'categories' => $tabs,
            'list' => $list,
            'active_category_id' => $activeCategoryId,
        ];
    }

    /**
     * 解析当前激活分类 ID。
     *
     * @param array<int, array<string, mixed>> $categories 可用分类导航或分类记录集合。
     * @param int $selectedCategoryId 当前选中的分类标识，用于匹配激活导航。
     * @return int 有效的激活分类标识；无可用分类时为零。
     */
    private function resolveActiveCategoryId(array $categories, int $selectedCategoryId): int
    {
        $firstCategoryId = 0;
        foreach ($categories as $category) {
            $categoryId = (int)($category['id'] ?? 0);
            if ($categoryId <= 0) {
                continue;
            }

            if ($firstCategoryId <= 0) {
                $firstCategoryId = $categoryId;
            }

            if ($selectedCategoryId > 0 && $categoryId === $selectedCategoryId) {
                return $categoryId;
            }
        }

        return $firstCategoryId;
    }

    /**
     * 归一化内容类型配置。
     *
     * @param mixed $value 需要校验或规范化的原始输入值。
     * @return int 合法内容类型；不限定类型时为零。
     */
    private function normalizeContentType(mixed $value): int
    {
        if ($value === '' || $value === null || $value === 'all') {
            return 0;
        }

        if (is_numeric($value)) {
            return (int)$value;
        }

        return match ((string)$value) {
            'article', 'graphic' => ContentTypeEnum::GRAPHIC,
            'text' => ContentTypeEnum::TEXT,
            'novel' => ContentTypeEnum::NOVEL,
            'image' => ContentTypeEnum::IMAGE,
            'audio' => ContentTypeEnum::AUDIO,
            'video' => ContentTypeEnum::VIDEO,
            default => 0,
        };
    }

    /**
     * 解析内容排序字段，避免新样式使用不存在字段导致 SQL 错误。
     *
     * @param string $sortBy 排序字段或预定义排序类型。
     * @return string 映射到真实数据库字段的内容排序字段。
     */
    private function resolveContentSortField(string $sortBy): string
    {
        return match ($sortBy) {
            'view_count' => 'view_count',
            'price' => 'price',
            'id' => 'id',
            default => 'create_time',
        };
    }

    /**
     * 归一化排序方向。
     *
     * @param mixed $sortOrder 排序方向，规范化为升序或降序。
     * @return string 限定为升序或降序的排序方向。
     */
    private function normalizeSortOrder(mixed $sortOrder): string
    {
        return strtolower((string)$sortOrder) === 'asc' ? 'asc' : 'desc';
    }

    /**
     * 归一化手动选择项为 ID 数组，兼容旧版纯 ID 和新版对象数组配置。
     *
     * @param mixed $manualIds 手动选择配置，支持 ID、逗号字符串或含 id 的数组项。
     * @return array<int, int> 从兼容自选配置提取的有效标识集合。
     */
    private function normalizeManualIds(mixed $manualIds): array
    {
        if (is_string($manualIds)) {
            $manualIds = explode(',', $manualIds);
        }

        if (!is_array($manualIds)) {
            $manualIds = [$manualIds];
        }

        $ids = [];
        foreach ($manualIds as $item) {
            $id = is_array($item) ? ($item['id'] ?? $item['coupon_id'] ?? $item['value'] ?? 0) : $item;
            $id = (int)$id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
