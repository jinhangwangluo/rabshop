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

namespace app\common\service\merchant;

use app\common\enum\AuditStatusEnum;
use app\common\exception\AdminResponseException;
use app\common\model\merchant\Merchant;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\content\ArticleService;
use app\common\service\content\ContentDisplayConfigService;
use app\common\service\content\SpecialService;
use app\common\service\setting\CategoryService;
use app\common\service\setting\ConfigService;
use app\common\service\store\MobileProductService;
use app\common\service\store\StoreProductService;

/**
 * 商户移动端首页、分类与底部菜单展示配置。
 *
 * 固定配置键由服务端商户 ID 生成，不复用推荐专题字段存储其他业务数据。
 */
class MerchantMobileDisplayService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    private const CONFIG_KEY_PREFIX = 'merchant_mobile_display_';
    private const SECTION_TYPES = ['content', 'special', 'product'];
    private const MAX_IDS = 100;
    private const MAX_CONFIG_BYTES = 5000;
    private const MAX_CATEGORY_NODES = 1000;

    /**
     * 设置商户模型。
     *
     * @return string 商户模型类名。
     */
    protected function setModel(): string
    {
        return Merchant::class;
    }

    /**
     * 标准化展示配置；不查询数据库，非法字段值明确拒绝。
     *
     * @param array $config 展示配置，空数组表示使用默认配置。
     * @param array $legacySpecialIds 旧版推荐专题 ID，默认配置保留旧选择。
     * @return array 标准化后的首页模块、分类与底部菜单配置。
     */
    public function normalizeConfig(array $config, array $legacySpecialIds = []): array
    {
        if ($config === []) {
            $legacySpecialIds = $this->normalizeIds($legacySpecialIds, '旧版推荐专题');
            $config = [
                'sections' => [
                    $this->defaultSection('special', 8, $legacySpecialIds),
                    $this->defaultSection('content', 10),
                    $this->defaultSection('product', 10),
                ],
                'category' => [
                    'mode' => 'special',
                    'special_category_ids' => [],
                    'product_category_ids' => [],
                ],
                'menu' => ['mode' => 'special'],
            ];
        }

        $sections = $config['sections'] ?? null;
        if (!is_array($sections) || !array_is_list($sections) || count($sections) !== 3) {
            throw new AdminResponseException('首页必须配置内容、专题、商品三个模块');
        }

        $normalizedSections = [];
        $types = [];
        foreach ($sections as $section) {
            if (!is_array($section)) {
                throw new AdminResponseException('首页模块配置格式错误');
            }
            $type = $section['type'] ?? null;
            if (!is_string($type) || !in_array($type, self::SECTION_TYPES, true) || isset($types[$type])) {
                throw new AdminResponseException('首页模块类型错误或重复');
            }
            $types[$type] = true;

            $enabled = $section['enabled'] ?? null;
            if (!is_bool($enabled)) {
                throw new AdminResponseException('首页模块展示状态必须为布尔值');
            }
            $source = $section['source'] ?? null;
            if (!is_string($source) || !in_array($source, ['all', 'category', 'manual'], true)) {
                throw new AdminResponseException('首页模块数据来源错误');
            }
            $limit = $section['limit'] ?? null;
            if ((!is_int($limit) && !(is_string($limit) && ctype_digit($limit)))
                || (int)$limit < 1 || (int)$limit > 50) {
                throw new AdminResponseException('首页模块展示数量必须为1至50的整数');
            }

            $normalizedSections[] = [
                'type' => $type,
                'enabled' => $enabled,
                'source' => $source,
                'limit' => (int)$limit,
                'category_ids' => $this->normalizeIds($section['category_ids'] ?? [], '首页模块分类'),
                'ids' => $this->normalizeIds($section['ids'] ?? [], '首页自选数据'),
            ];
        }

        $category = $config['category'] ?? null;
        if (!is_array($category) || !in_array($category['mode'] ?? null, ['special', 'product'], true)) {
            throw new AdminResponseException('分类展示类型必须为专题分类或商户商品分类');
        }
        // 旧完整配置省略菜单时延续专题入口；显式非法值不能当作缺省配置接受。
        $menu = array_key_exists('menu', $config) ? $config['menu'] : ['mode' => 'special'];
        if (!is_array($menu) || !in_array($menu['mode'] ?? null, ['special', 'product'], true)) {
            throw new AdminResponseException('底部菜单展示类型必须为专题或商品');
        }
        $normalized = [
            'sections' => $normalizedSections,
            'category' => [
                'mode' => $category['mode'],
                'special_category_ids' => $this->normalizeIds($category['special_category_ids'] ?? [], '专题展示分类'),
                'product_category_ids' => $this->normalizeIds($category['product_category_ids'] ?? [], '商户商品展示分类'),
            ],
            'menu' => ['mode' => $menu['mode']],
        ];

        if (strlen(json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) > self::MAX_CONFIG_BYTES) {
            throw new AdminResponseException('移动端展示配置最多5000字节，请减少自选数据或分类');
        }

        return $normalized;
    }

    /**
     * 单键直读商户展示配置，避免共享缓存的延迟旧值回填。
     *
     * @param int $merchantId 已由调用方确认的商户 ID。
     * @param array $legacySpecialIds 旧版推荐专题 ID。
     * @return array 标准化配置；损坏的历史数据使用默认配置。
     */
    public function getConfig(int $merchantId, array $legacySpecialIds = []): array
    {
        $key = $this->configKey($merchantId);
        // 每次仅查询当前商户的一个键，保存提交后的后续请求不消费旧 remember 缓存。
        $values = app()->make(ConfigService::class)->getMultiValue([$key]);
        $stored = $values[$key] ?? null;
        // 历史推荐数量可能超过新契约上限；只在读取兼容路径裁剪，不放宽保存校验。
        $legacySpecialIds = array_slice(array_values(array_unique(array_filter(
            array_map('intval', $legacySpecialIds),
            fn(int $id): bool => $id > 0
        ))), 0, self::MAX_IDS);
        try {
            return $this->normalizeConfig(is_array($stored) ? $stored : [], $legacySpecialIds);
        } catch (AdminResponseException $e) {
            return $this->normalizeConfig([], $legacySpecialIds);
        }
    }

    /**
     * 校验保留选择的归属和当前展示选择的公开状态后保存商户配置。
     *
     * @param int $merchantId 当前登录商户的服务端 ID，不能取自请求体。
     * @param array $config 提交的展示配置。
     * @return array 实际保存的标准化配置。
     */
    public function saveConfig(int $merchantId, array $config): array
    {
        $key = $this->configKey($merchantId);
        if (!$this->get($merchantId)) {
            throw new AdminResponseException('商户不存在');
        }
        $normalized = $this->normalizeConfig($config);
        $this->assertSelections($merchantId, $normalized);
        app()->make(ConfigService::class)->saveKeyValues(
            [$key => $normalized],
            0,
            [$key => ['title' => '商户移动端展示配置', 'status' => 0]]
        );
        return $normalized;
    }

    /**
     * 组装首页模块第一页，不重复查询已有的商户公开信息。
     *
     * @param int $merchantId 已校验可公开访问的商户 ID。
     * @param array $legacySpecialIds 旧版推荐专题 ID。
     * @param int $userId 当前用户 ID，匿名为0。
     * @return array 首页配置与按配置排序的启用模块。
     */
    public function getHomeData(int $merchantId, array $legacySpecialIds = [], int $userId = 0): array
    {
        $config = $this->getConfig($merchantId, $legacySpecialIds);
        $enabledSections = $this->enabledSections($config);
        $lastType = $enabledSections === [] ? '' : $enabledSections[array_key_last($enabledSections)]['type'];
        $sections = [];
        foreach ($enabledSections as $section) {
            $data = $this->querySection($merchantId, $section, 1, $userId);
            $data['has_more'] = $section['type'] === $lastType && $data['has_more'];
            $sections[] = ['type' => $section['type']] + $data;
        }

        return ['home_config' => $config, 'sections' => $sections];
    }

    /**
     * 获取首页最后一个启用模块的分页数据。
     *
     * @param int $merchantId 商户 ID。
     * @param string $type 模块类型。
     * @param array $params 分页请求，仅使用page，数量由商户配置决定。
     * @param int $userId 当前用户 ID。
     * @return array 模块类型、公开数据与分页信息。
     */
    public function getHomeSection(int $merchantId, string $type, array $params = [], int $userId = 0): array
    {
        $merchant = $this->publicMerchant($merchantId);
        $config = $this->getConfig($merchantId, $this->legacySpecialIds($merchant));
        $enabledSections = $this->enabledSections($config);
        $section = $enabledSections === [] ? null : $enabledSections[array_key_last($enabledSections)];
        if ($section === null || $section['type'] !== $type) {
            throw new AdminResponseException('仅首页最后一个启用的展示模块支持触底加载');
        }
        [$page] = $this->getPagesValue(true, true, ['page' => $params['page'] ?? 1, 'limit' => $section['limit']], 50);
        return ['type' => $type] + $this->querySection($merchantId, $section, $page, $userId);
    }

    /**
     * 获取商户分类页展示类型、分类树与独立的底部菜单配置。
     *
     * @param int $merchantId 商户 ID。
     * @return array mode、categories与menu；选择二级分类时保留一级分类导航。
     */
    public function getCategory(int $merchantId): array
    {
        $merchant = $this->publicMerchant($merchantId);
        $config = $this->getConfig($merchantId, $this->legacySpecialIds($merchant));
        $mode = $config['category']['mode'];
        if ($mode === 'special' && !app()->make(ContentDisplayConfigService::class)->isSpecialEnabled()) {
            return ['mode' => $mode, 'categories' => [], 'menu' => $config['menu']];
        }
        $selectedIds = $config['category'][$mode . '_category_ids'];
        $rows = $this->categoryRows($mode, $merchantId);
        $rows = $this->filterCategoryRows($rows, $selectedIds);

        return ['mode' => $mode, 'categories' => get_tree_children($rows), 'menu' => $config['menu']];
    }

    /**
     * 获取配置允许的选中分类下的商品或专题。
     *
     * @param int $merchantId 商户 ID。
     * @param int $categoryId 分类 ID，0表示配置允许的全部分类。
     * @param array $params page与limit。
     * @param int $userId 当前用户 ID。
     * @return array 展示模式、列表与分页信息。
     */
    public function getCategoryList(int $merchantId, int $categoryId, array $params = [], int $userId = 0): array
    {
        $merchant = $this->publicMerchant($merchantId);
        if ($categoryId < 0) {
            throw new AdminResponseException('分类ID不能小于0');
        }
        [$page, $limit] = $this->getPagesValue(true, true, [
            'page' => $params['page'] ?? 1,
            'limit' => $params['limit'] ?? 10,
        ], 50);
        $config = $this->getConfig($merchantId, $this->legacySpecialIds($merchant));
        $mode = $config['category']['mode'];
        if ($mode === 'special' && !app()->make(ContentDisplayConfigService::class)->isSpecialEnabled()) {
            return ['mode' => $mode] + $this->pagePayload([], $page, $limit);
        }
        $rows = $this->categoryRows($mode, $merchantId);
        $selectedIds = $config['category'][$mode . '_category_ids'];
        $allowedIds = $selectedIds === []
            ? array_column($rows, 'id')
            : $this->descendantIds($rows, $selectedIds);
        if ($categoryId > 0) {
            $displayIds = array_column($this->filterCategoryRows($rows, $selectedIds), 'id');
            if (!in_array($categoryId, $displayIds, true)) {
                throw new AdminResponseException('该分类未在当前商户展示范围内');
            }
            $allowedIds = array_values(array_intersect($allowedIds, $this->descendantIds($rows, [$categoryId])));
        }

        $section = $this->defaultSection($mode, $limit);
        $section['source'] = 'category';
        $section['category_ids'] = $allowedIds;
        return ['mode' => $mode] + $this->querySection($merchantId, $section, $page, $userId, true);
    }

    /**
     * 创建默认模块配置。
     *
     * @param string $type 首页模块或分类的资源类型：内容、专题或商品。
     * @param int $limit 模块首屏或分页的记录数量。
     * @param array $ids 选择的资源或分类标识集合，归属由调用链另行校验。
     * @return array 指定模块的默认来源、数量、开关和自选配置。
     */
    private function defaultSection(string $type, int $limit, array $ids = []): array
    {
        return [
            'type' => $type,
            'enabled' => true,
            'source' => $ids === [] ? 'all' : 'manual',
            'limit' => $limit,
            'category_ids' => [],
            'ids' => $ids,
        ];
    }

    /**
     * 标准化有界正整数 ID 数组，保留选择顺序。
     *
     * @param mixed $ids 选择的资源或分类标识集合，归属由调用链另行校验。
     * @param string $label 用于校验错误提示或展示的中文名称。
     * @return array 去重并保留有效顺序的正整数标识集合。
     */
    private function normalizeIds(mixed $ids, string $label): array
    {
        if (!is_array($ids) || !array_is_list($ids) || count($ids) > self::MAX_IDS) {
            throw new AdminResponseException($label . '必须为数组且最多选择100项');
        }
        $normalized = [];
        foreach ($ids as $id) {
            if ((!is_int($id) && !(is_string($id) && ctype_digit($id))) || (int)$id <= 0 || (int)$id > 2147483647) {
                throw new AdminResponseException($label . 'ID必须为有效正整数');
            }
            $normalized[] = (int)$id;
        }
        return array_values(array_unique($normalized));
    }

    /**
     * 配置键只允许从服务端正数商户ID生成。
     *
     * @param int $merchantId 当前业务商户的正整数标识。
     * @return string 当前正整数商户标识对应的独立展示配置键。
     */
    private function configKey(int $merchantId): string
    {
        if ($merchantId <= 0) {
            throw new AdminResponseException('商户ID不能为空');
        }
        return self::CONFIG_KEY_PREFIX . $merchantId;
    }

    /**
     * 取得可公开访问的商户数据。
     *
     * @param int $merchantId 当前业务商户的正整数标识。
     * @return array 通过启用和审核校验的公开商户资料。
     */
    private function publicMerchant(int $merchantId): array
    {
        $this->configKey($merchantId);
        $merchant = $this->get($merchantId);
        if (!$merchant) {
            throw new AdminResponseException('商户不存在');
        }
        $data = $merchant->toArray();
        if ((int)($data['status'] ?? 0) !== 1) {
            throw new AdminResponseException('商户已禁用');
        }
        if ((int)($data['audit_status'] ?? 0) !== AuditStatusEnum::APPROVED) {
            throw new AdminResponseException('商户暂不可访问');
        }
        return $data;
    }

    /**
     * 提取已有商户模型的旧推荐ID。
     *
     * @param array $merchant 已有商户的资料和旧推荐配置。
     * @return array 从旧推荐配置提取的专题标识集合。
     */
    private function legacySpecialIds(array $merchant): array
    {
        $ids = $merchant['merchant_home_recommend_special'] ?? [];
        if (is_string($ids)) {
            $ids = json_decode($ids, true);
        }
        if (!is_array($ids)) {
            return [];
        }
        return array_map(fn(mixed $id): int => is_array($id)
            ? (int)($id['special_id'] ?? $id['id'] ?? $id['value'] ?? 0)
            : (int)$id, $ids);
    }

    /**
     * 商户开关与平台内容能力共同决定实际启用顺序。
     *
     * @param array $config 含模块顺序、来源、底部菜单和分类模式的标准化展示配置。
     * @return array 同时满足商户开关和平台能力的实际模块顺序。
     */
    private function enabledSections(array $config): array
    {
        $display = app()->make(ContentDisplayConfigService::class);
        return array_values(array_filter($config['sections'], fn(array $section): bool => $section['enabled'] && match ($section['type']) {
            'content' => $display->isContentEnabled(),
            'special' => $display->isSpecialEnabled(),
            default => true,
        }));
    }

    /**
     * 分类型复用移动端列表，始终注入当前商户归属。
     *
     * @param int $merchantId 当前业务商户的正整数标识。
     * @param array $section 当前首页模块的来源、数量和自选配置。
     * @param int $page 当前页码，从第一页开始。
     * @param int $userId 当前用户标识，匿名访问时为零。
     * @param bool $resolvedCategories 分类范围是否已由调用方展开，避免重复解析。
     * @return array 始终限定当前商户归属的模块分页数据。
     */
    private function querySection(int $merchantId, array $section, int $page, int $userId, bool $resolvedCategories = false): array
    {
        $params = ['merchant_id' => $merchantId, 'page' => $page, 'limit' => $section['limit'], 'user_id' => $userId, '_limit_max' => 50];
        if ($section['source'] === 'manual') {
            // 空自选集合必须保持空，不能回退为自动推荐。
            $params['_ids'] = $section['ids'];
        } elseif ($section['source'] === 'category') {
            $ids = $resolvedCategories
                ? $section['category_ids']
                : $this->descendantIds($this->categoryRows($section['type'], $merchantId), $section['category_ids']);
            $params[$section['type'] === 'product' ? '_merchant_category_ids' : '_category_ids'] = $ids;
        }
        $data = match ($section['type']) {
            'content' => app()->make(ArticleService::class)->getMobileList($params),
            'special' => app()->make(SpecialService::class)->getMobileList($params),
            'product' => app()->make(MobileProductService::class)->getList($params),
        };
        return $this->pagePayload($data, $page, $section['limit']);
    }

    /**
     * 统一三个模块的分页响应，过滤非列表展示字段。
     *
     * @param array $data 内容、专题或商品服务返回的分页结果。
     * @param int $page 当前页码，从第一页开始。
     * @param int $limit 模块首屏或分页的记录数量。
     * @return array 裁剪为列表及统一分页字段的模块响应。
     */
    private function pagePayload(array $data, int $page, int $limit): array
    {
        $total = (int)($data['total'] ?? 0);
        return [
            'list' => $data['list'] ?? [],
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'has_more' => $page * $limit < $total,
        ];
    }

    /**
     * 查询当前类型和归属的启用分类，排除父级被禁用形成的孤儿分类。
     *
     * @param string $type 首页模块或分类的资源类型：内容、专题或商品。
     * @param int $merchantId 当前业务商户的正整数标识。
     * @return array 正确类型和归属范围内可展示的分类记录。
     */
    private function categoryRows(string $type, int $merchantId): array
    {
        $rows = app()->make(CategoryService::class)->getModel()
            ->where($this->categoryScope($type, $merchantId) + ['status' => 1])
            ->field(['id', 'pid', 'name', 'image', 'banner_image', 'description', 'sort', 'status'])
            ->order('sort desc,id desc')
            ->limit(self::MAX_CATEGORY_NODES)
            ->select()->toArray();
        foreach ($rows as &$row) {
            $row['id'] = (int)$row['id'];
            $row['pid'] = (int)$row['pid'];
            $row['value'] = $row['id'];
            $row['label'] = $row['name'];
        }
        unset($row);
        $roots = array_column(array_filter($rows, fn(array $row): bool => $row['pid'] === 0), 'id');
        $reachable = array_fill_keys($this->descendantIds($rows, $roots), true);
        return array_values(array_filter($rows, fn(array $row): bool => isset($reachable[$row['id']])));
    }

    /**
     * 分类查询始终限定正确类型和归属，不因是否参与展示而放宽权限。
     *
     * @param string $type 首页模块或分类模式。
     * @param int $merchantId 当前商户 ID。
     * @return array 分类类型和所属命名空间条件。
     */
    private function categoryScope(string $type, int $merchantId): array
    {
        return [
            'type' => match ($type) {
                'content' => CategoryService::ARTICLE_TYPE,
                'special' => CategoryService::SPECIAL_TYPE,
                'product' => CategoryService::PRODUCT_TYPE,
            },
            'owner_id' => $type === 'product' ? $merchantId : 0,
        ];
    }

    /**
     * 批量展开选中分类及其子级，使用已加载的归属分类避免循环查询。
     *
     * @param array $rows 已加载并限制分类类型及归属命名空间的分类记录。
     * @param array $ids 选择的资源或分类标识集合，归属由调用链另行校验。
     * @return array 选中分类及全部可用后代的标识集合。
     */
    private function descendantIds(array $rows, array $ids): array
    {
        $byId = array_column($rows, null, 'id');
        $children = [];
        foreach ($rows as $row) {
            $children[$row['pid']][] = $row['id'];
        }
        $seen = [];
        $stack = $ids;
        while ($stack !== []) {
            $id = (int)array_pop($stack);
            if (!isset($byId[$id]) || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            foreach ($children[$id] ?? [] as $childId) {
                $stack[] = $childId;
            }
        }
        return array_keys($seen);
    }

    /**
     * 空选择代表全部分类；选二级时只补导航祖先，不引入其他兄弟分类。
     *
     * @param array $rows 已加载并限制分类类型及归属命名空间的分类记录。
     * @param array $selectedIds 用户选择的分类标识集合，空集合表示全部。
     * @return array 选中分类范围及必要导航祖先的分类记录。
     */
    private function filterCategoryRows(array $rows, array $selectedIds): array
    {
        if ($selectedIds === []) {
            return $rows;
        }
        $byId = array_column($rows, null, 'id');
        $allowed = array_fill_keys($this->descendantIds($rows, $selectedIds), true);
        foreach (array_keys($allowed) as $id) {
            $parentId = $byId[$id]['pid'];
            while ($parentId > 0 && isset($byId[$parentId]) && !isset($allowed[$parentId])) {
                $allowed[$parentId] = true;
                $parentId = $byId[$parentId]['pid'];
            }
        }
        return array_values(array_filter($rows, fn(array $row): bool => isset($allowed[$row['id']])));
    }

    /**
     * 保留选择必须存在且归属正确；公开状态仅约束当前启用的数据来源。
     *
     * @param int $merchantId 当前登录商户的服务端 ID。
     * @param array $config 标准化配置，含非当前来源的保留选择。
     * @return void 无返回值。
     */
    private function assertSelections(int $merchantId, array $config): void
    {
        $categoryIds = ['content' => [], 'special' => $config['category']['special_category_ids'], 'product' => $config['category']['product_category_ids']];
        $activeCategoryIds = ['content' => [], 'special' => [], 'product' => []];
        $categoryMode = $config['category']['mode'];
        $activeCategoryIds[$categoryMode] = $config['category'][$categoryMode . '_category_ids'];
        foreach ($config['sections'] as $section) {
            $type = $section['type'];
            $categoryIds[$type] = array_values(array_unique(array_merge($categoryIds[$type], $section['category_ids'])));
            if ($section['enabled'] && $section['source'] === 'category') {
                $activeCategoryIds[$type] = array_values(array_unique(array_merge($activeCategoryIds[$type], $section['category_ids'])));
            }
            if ($section['ids'] === []) {
                continue;
            }
            $service = match ($type) {
                'content' => app()->make(ArticleService::class),
                'special' => app()->make(SpecialService::class),
                'product' => app()->make(StoreProductService::class),
            };
            $query = $service->getModel()
                ->where($type === 'content' ? 'mer_id' : 'merchant_id', $merchantId)
                ->whereIn('id', $section['ids'])
                ->whereNull('delete_time');
            // 来源切换和关闭模块会保留旧选择，下架或审核变化不能阻塞这些非活动选择。
            if ($section['enabled'] && $section['source'] === 'manual') {
                $query->where('status', 1)->where('audit_status', AuditStatusEnum::APPROVED);
            }
            if ((int)$query->count() !== count($section['ids'])) {
                throw new AdminResponseException('首页自选' . match ($type) {
                    'content' => '内容', 'special' => '专题', 'product' => '商品',
                } . '不存在、未公开或不属于当前商户');
            }
        }
        foreach ($categoryIds as $type => $ids) {
            if ($ids === []) {
                continue;
            }
            $count = app()->make(CategoryService::class)->getModel()
                ->where($this->categoryScope($type, $merchantId))
                ->whereIn('id', $ids)
                ->count();
            if ((int)$count !== count($ids)) {
                throw new AdminResponseException(match ($type) {
                    'content' => '内容', 'special' => '专题', 'product' => '商户商品',
                } . '分类不存在或无权选择');
            }
            if ($activeCategoryIds[$type] !== []
                && array_diff($activeCategoryIds[$type], array_column($this->categoryRows($type, $merchantId), 'id')) !== []) {
                throw new AdminResponseException(match ($type) {
                    'content' => '内容', 'special' => '专题', 'product' => '商户商品',
                } . '分类不存在、已停用或无权选择');
            }
        }
    }
}
