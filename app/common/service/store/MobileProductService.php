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

namespace app\common\service\store;

use app\common\enum\ConfigKeyEnum;
use app\common\enum\ProductEnum;
use app\common\exception\AdminResponseException;
use app\common\model\store\StoreProduct;
use app\common\service\content\TagService;
use app\common\service\merchant\MerchantService;
use app\common\service\order\OrderReviewService;
use app\common\service\BaseService;
use app\common\service\user\UserFavoriteService;
use app\common\service\user\UserMemberService;

/**
 * 移动端商品服务（前台）
 *
 * 前台列表/详情/规格数据：
 * - 列表仅返回上架（status=1）且审核通过的商品
 * - 详情同时返回真实统计与展示值（display_sales = sales + virtual_sales）
 * - getSkuData 返回 6.4 规格数据结构（spec_list + sku_list + default_sku）
 */
class MobileProductService extends BaseService
{
    /**
     * 设置模型
     *
     * @return string 模型类名
     */
    protected function setModel(): string
    {
        return StoreProduct::class;
    }

    /**
     * 前台商品列表
     *
     * @param array $params 搜索与分页参数；merchant_id限制商户，内部_ids/_merchant_category_ids为已校验集合。
     * @return array 列表 + 分页
     */
    public function getList(array $params = []): array
    {
        [$page, $limit] = $this->getPagesValue(true, true, $params, (int)($params['_limit_max'] ?? 0));
        $query = $this->getModel()
            ->where('status', ProductEnum::STATUS_OPEN)
            ->where('audit_status', ProductEnum::AUDIT_STATUS_PASSED);

        $merchantId = (int)($params['merchant_id'] ?? 0);
        if ($merchantId < 0) {
            throw new AdminResponseException('商户ID不能小于0');
        }
        if ($merchantId > 0) {
            app()->make(MerchantService::class)->getMobileDetail($merchantId);
            $query->where('merchant_id', $merchantId);
        }
        // 只接受展示服务组装的自选和商户分类集合，平台分类仍使用category_id。
        if (array_key_exists('_ids', $params)) {
            $ids = is_array($params['_ids']) ? array_values(array_map('intval', $params['_ids'])) : [];
            $query->whereIn('id', $ids === [] ? [0] : $ids)->orderField('id', $ids, 'asc');
        }
        if (array_key_exists('_merchant_category_ids', $params)) {
            if ($merchantId <= 0) {
                throw new AdminResponseException('商户商品分类查询必须指定商户');
            }
            app()->make(ProductCategoryRelationService::class)->applyCategoryFilter(
                $query,
                is_array($params['_merchant_category_ids']) ? $params['_merchant_category_ids'] : [],
                $this->getModel()->getTable()
            );
        }

        app()->make(StoreProductService::class)->applyMobileSearchFilters($query, $params);

        if (!empty($params['keyword'])) {
            $query->where(function ($q) use ($params) {
                $q->where('name', 'like', '%' . $params['keyword'] . '%')
                    ->whereOr('keyword', 'like', '%' . $params['keyword'] . '%');
            });
        }

        if (!array_key_exists('_ids', $params)) {
            $sort = (string) ($params['sort'] ?? '');
            switch ($sort) {
                case 'sales':
                    $query->order('sales desc, id desc');
                    break;
                case 'price_asc':
                    $query->order('price asc, id desc');
                    break;
                case 'price_desc':
                    $query->order('price desc, id desc');
                    break;
                case 'view':
                    $query->order('view_count desc, id desc');
                    break;
                default:
                    $query->order('sort desc, id desc');
            }
        }

        $count = (int)(clone $query)->count();
        $list = $query->field(['id', 'merchant_id', 'category_id', 'name', 'cover', 'price', 'ot_price', 'sales', 'virtual_sales', 'stock', 'unit', 'goods_type', 'spec_type', 'is_free_shipping'])
            ->page($page, $limit)
            ->select()
            ->toArray();

        $memberService = app()->make(UserMemberService::class);
        $userId = max(0, (int)($params['user_id'] ?? 0));
        $pricePreviews = $memberService->getMemberPricePreviewMap($list, $userId);
        foreach ($list as $index => &$item) {
            $price = max(0, (float)($item['price'] ?? 0));
            $originalPrice = max(0, (float)($item['ot_price'] ?? 0));
            $item['display_sales'] = (int) ($item['sales'] ?? 0) + (int) ($item['virtual_sales'] ?? 0);
            $item['title'] = (string)($item['name'] ?? '');
            $item['image'] = (string)($item['cover'] ?? '');
            $item['sales_text'] = '已售' . $item['display_sales'] . '件';
            $item['price_text'] = '¥' . number_format($price, 2, '.', '');
            $item['original_price_text'] = $originalPrice > $price ? '¥' . number_format($originalPrice, 2, '.', '') : '';
            $preview = $pricePreviews[$index];
            $item['member_price_available'] = (bool)($preview['member_price_available'] ?? false);
            $item['member_price'] = $item['member_price_available'] ? (float)($preview['member_price'] ?? 0) : 0.0;
            $item['member_price_text'] = $item['member_price_available'] ? '¥' . number_format($item['member_price'], 2, '.', '') : '';
            $item['member_enabled'] = (bool)($preview['member_enabled'] ?? false);
            $item['member_visible'] = (bool)($preview['member_visible'] ?? false);
        }
        unset($item);

        $list = app()->make(TagService::class)->attachTagsToList($list, TagService::RELATION_TYPE_PRODUCT);

        return $this->listData($list, $count, ['has_more' => $page * $limit < $count], $page, $limit)->toArray();
    }

    /**
     * 前台商品详情（含规格数据与展示统计）
     *
     * @param int $id 店铺商品ID
     * @param int $userId 用户ID，匿名为0
     * @return array 详情数据
     * @throws AdminResponseException 不存在或不可售时抛出
     */
    public function getDetail(int $id, int $userId = 0): array
    {
        $product = $this->getAvailableProduct($id);
        $data = $product->toArray();
        // 展示统计：真实值 + 虚拟值
        $data['display_sales'] = (int) ($data['sales'] ?? 0) + (int) ($data['virtual_sales'] ?? 0);
        $data['display_view_count'] = (int) ($data['view_count'] ?? 0) + (int) ($data['virtual_view_count'] ?? 0);
        // 规格数据（6.4 结构）
        $data['sku_data'] = app()->make(StoreProductSkuService::class)->getSkuData((int) $data['id']);
        $data['spec_config'] = app()->make(StoreProductSpecConfigService::class)->findByProductId((int) $data['id']);
        $data = app()->make(TagService::class)->attachTagsToDetail($data, TagService::RELATION_TYPE_PRODUCT);
        $reviewSummary = app()->make(OrderReviewService::class)->getMobileProductReviewSummary((int) $data['id']);
        $data['store_profile'] = $this->resolveStoreProfile($data, $reviewSummary);
        $data['is_favorite'] = app()->make(UserFavoriteService::class)->isFavorite(
            $userId,
            UserFavoriteService::TARGET_TYPE_PRODUCT,
            (int) $data['id']
        );

        // 服务与参数解析
        $data['services'] = $this->resolveServices((string) ($data['service_ids'] ?? ''), (int) ($data['merchant_id'] ?? 0));
        $data['attributes'] = $this->resolveAttributes($data['spec_config']['param_snapshot'] ?? ($data['attr_values'] ?? []));

        // 浏览量累加（真实统计）
        app()->make(StoreProductService::class)->incViewCount((int) $data['id']);
        $data['view_count'] = (int) $data['view_count'] + 1;

        return $data;
    }

    /**
     * 获取商品详情页可用优惠券。
     *
     * @param int $id 商品ID
     * @param int $userId 用户ID，匿名为0
     * @return array 当前商品及用户可使用的优惠券列表。
     */
    public function getCoupons(int $id, int $userId = 0): array
    {
        $product = $this->getAvailableProduct($id)->toArray();
        return app()->make(StoreCouponService::class)->getMobileProductCoupons($userId, $product);
    }

    /**
     * 获取商品收藏状态。
     *
     * @param int $userId 用户ID，匿名为0
     * @param int $productId 商品ID
     * @return array 收藏状态
     */
    public function getFavoriteStatus(int $userId, int $productId): array
    {
        if ($userId <= 0 || $productId <= 0) {
            return [
                'product_id' => $productId,
                'is_favorite' => false,
            ];
        }

        return [
            'product_id' => $productId,
            'is_favorite' => app()->make(UserFavoriteService::class)->isFavorite(
                $userId,
                UserFavoriteService::TARGET_TYPE_PRODUCT,
                $productId
            ),
        ];
    }

    /**
     * 切换商品收藏状态。
     *
     * @param int $userId 用户ID
     * @param int $productId 商品ID
     * @return array 切换后的收藏状态
     * @throws AdminResponseException
     */
    public function toggleFavorite(int $userId, int $productId): array
    {
        if ($userId <= 0) {
            throw new AdminResponseException('未获取到登录用户');
        }

        $product = $this->getAvailableProduct($productId)->toArray();
        $result = app()->make(UserFavoriteService::class)->toggleFavorite(
            $userId,
            UserFavoriteService::TARGET_TYPE_PRODUCT,
            $productId,
            (int) ($product['merchant_id'] ?? 0)
        );

        return [
            'product_id' => $productId,
            'is_favorite' => (bool) ($result['is_favorite'] ?? false),
            'status' => (int) ($result['status'] ?? 0),
        ];
    }

    /**
     * 获取商品详情页高分评价。
     *
     * @param int $id 商品ID
     * @param int $starMin 最低星级
     * @return array 达到最低星级的商品评价及分页信息。
     */
    public function getReviews(int $id, int $starMin = 4): array
    {
        $this->getAvailableProduct($id);
        [$page, $limit] = $this->getPageValue();
        $page = max(1, $page);
        $limit = $limit > 0 ? min(20, $limit) : 3;

        return app()->make(OrderReviewService::class)->getMobileProductReviews($id, $page, $limit, $starMin);
    }

    /**
     * 前台规格数据（6.4 结构）
     *
     * @param int $id 店铺商品ID
     * @return array 规格数据结构
     */
    public function getSkuData(int $id): array
    {
        $this->getAvailableProduct($id);
        return app()->make(StoreProductSkuService::class)->getSkuData($id);
    }

    /**
     * 获取前台可售商品。
     *
     * @param int $id 商品ID
     * @return StoreProduct 通过上架和审核状态校验的商品模型。
     * @throws AdminResponseException
     */
    protected function getAvailableProduct(int $id): StoreProduct
    {
        $product = $this->getModel()
            ->where('id', $id)
            ->where('status', ProductEnum::STATUS_OPEN)
            ->where('audit_status', ProductEnum::AUDIT_STATUS_PASSED)
            ->find();

        if (!$product) {
            throw new AdminResponseException('商品不存在或已下架');
        }

        return $product;
    }

    /**
     * 解析商品详情页商户/平台展示信息。
     *
     * @param array $product 商品详情数据
     * @param array $reviewSummary 商品评价统计
     * @return array<string,mixed> 所属商户或平台的店铺展示资料，本方法不校验公开资格。
     */
    protected function resolveStoreProfile(array $product, array $reviewSummary): array
    {
        $merchantId = (int)($product['merchant_id'] ?? 0);
        $rating = (float)($reviewSummary['avg_star'] ?? 0);
        if ($merchantId <= 0) {
            $platformName = trim((string)db_config(ConfigKeyEnum::PLATFORM_STORE_NAME, ''));
            if ($platformName === '') {
                $platformName = trim((string)db_config(ConfigKeyEnum::SITE_NAME, ''));
            }
            $avatar = $this->normalizeImageConfigValue(db_config(ConfigKeyEnum::PLATFORM_STORE_AVATAR, ''));
            if ($avatar === '') {
                $avatar = $this->normalizeImageConfigValue(db_config(ConfigKeyEnum::LOGIN_LOGO, ''));
            }

            return $this->formatStoreProfile([
                'merchant_id' => 0,
                'type' => 'platform',
                'name' => $platformName !== '' ? $platformName : '平台自营',
                'avatar' => $avatar,
                'description' => trim((string)db_config(
                    ConfigKeyEnum::PLATFORM_STORE_DESCRIPTION,
                    '品质美妆 · 正品保证'
                )),
                'follow_count' => 0,
                'badge' => '自营',
                'detail_url' => '',
            ], $rating);
        }

        $merchant = app()->make(MerchantService::class)->get($merchantId, [
            'id',
            'mer_name',
            'logo',
            'mer_avatar',
            'description',
            'mer_keyword',
            'follow_count',
            'status',
            'audit_status',
        ]);
        if (!$merchant) {
            return $this->formatStoreProfile([
                'merchant_id' => $merchantId,
                'type' => 'merchant',
                'name' => '',
                'avatar' => '',
                'description' => '',
                'follow_count' => 0,
                'badge' => '店铺',
                'detail_url' => '',
            ], $rating);
        }

        $merchantData = $merchant->toArray();
        $avatar = trim((string)($merchantData['mer_avatar'] ?? ''));
        if ($avatar === '') {
            $avatar = trim((string)($merchantData['logo'] ?? ''));
        }
        $description = trim((string)($merchantData['description'] ?? ''));
        if ($description === '') {
            $description = trim((string)($merchantData['mer_keyword'] ?? ''));
        }

        return $this->formatStoreProfile([
            'merchant_id' => $merchantId,
            'type' => 'merchant',
            'name' => trim((string)($merchantData['mer_name'] ?? '')),
            'avatar' => $avatar,
            'description' => $description,
            'follow_count' => (int)($merchantData['follow_count'] ?? 0),
            'badge' => '店铺',
            'detail_url' => '/pages/merchant/user/index?merchant_id=' . $merchantId,
            'status' => (int)($merchantData['status'] ?? 0),
            'audit_status' => (int)($merchantData['audit_status'] ?? 0),
        ], $rating);
    }

    /**
     * 格式化店铺展示信息。
     *
     * @param array $profile 原始店铺信息
     * @param float $rating 综合体验评分
     * @return array<string,mixed> 附带图片、评分和统计文案的店铺资料。
     */
    protected function formatStoreProfile(array $profile, float $rating): array
    {
        $merchantId = (int)($profile['merchant_id'] ?? 0);
        $name = trim((string)($profile['name'] ?? ''));
        $description = trim((string)($profile['description'] ?? ''));
        $followCount = max(0, (int)($profile['follow_count'] ?? 0));
        $rating = $rating > 0 ? $rating : 4.0;
        $rating = round(max(0.0, min(5.0, $rating)), 1);

        return [
            'id' => $merchantId,
            'merchant_id' => $merchantId,
            'type' => (string)($profile['type'] ?? 'merchant'),
            'name' => $name !== '' ? $name : ($merchantId > 0 ? '店铺' : '平台自营'),
            'avatar' => trim((string)($profile['avatar'] ?? '')),
            'description' => $description,
            'follow_count' => $followCount,
            'follow_count_text' => $this->formatCountText($followCount),
            'rating' => $rating,
            'rating_text' => number_format($rating, 1),
            'badge' => trim((string)($profile['badge'] ?? '')),
            'detail_url' => trim((string)($profile['detail_url'] ?? '')),
            'can_enter' => $merchantId > 0,
            'status' => (int)($profile['status'] ?? 1),
            'audit_status' => (int)($profile['audit_status'] ?? 1),
        ];
    }

    /**
     * 格式化数量文案。
     *
     * @param int $count 数量
     * @return string 用于移动端展示的数量文案。
     */
    protected function formatCountText(int $count): string
    {
        if ($count >= 10000) {
            $value = rtrim(rtrim(number_format($count / 10000, $count % 10000 === 0 ? 0 : 1, '.', ''), '0'), '.');
            return $value . '万';
        }

        return (string)$count;
    }

    /**
     * 规范化图片配置值，兼容字符串、单图对象和图片列表。
     *
     * @param mixed $value 配置值
     * @return string 图片地址
     */
    protected function normalizeImageConfigValue(mixed $value): string
    {
        if (is_string($value) || is_numeric($value)) {
            return trim((string)$value);
        }

        if (!is_array($value)) {
            return '';
        }

        foreach (['url', 'path', 'filePath', 'src', 'value'] as $key) {
            if (isset($value[$key]) && (is_string($value[$key]) || is_numeric($value[$key]))) {
                return trim((string)$value[$key]);
            }
        }

        $first = reset($value);
        if ($first === false) {
            return '';
        }

        return $this->normalizeImageConfigValue($first);
    }

    /**
     * 前台商品分类树
     *
     * @param array $categoryIds 需要查询或展示的分类标识集合。
     * @return array 树形分类数据
     */
    public function getCategories(array $categoryIds = []): array
    {
        return app()->make(ProductCategoryService::class)->getProductCategoryTreeCache(0, $categoryIds);
    }

    /**
     * 解析商品服务列表（service_ids → 服务名称/图标）
     *
     * @param string $serviceIds 服务ID逗号串
     * @param int $merchantId 商品所属商户标识；当前实现未按此参数过滤服务查询。
     * @return array 服务列表
     */
    protected function resolveServices(string $serviceIds, int $merchantId): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $serviceIds)))));
        if ($ids === []) {
            return [];
        }

        return app()->make(ProductServiceService::class)
            ->getModel()
            ->whereIn('id', $ids)
            ->field(['id', 'name', 'icon', 'description'])
            ->select()
            ->toArray();
    }

    /**
     * 解析商品参数属性（attr_values JSON）
     *
     * @param mixed $attrValues 属性值（数组或 JSON 串）
     * @return array 属性列表
     */
    protected function resolveAttributes(mixed $attrValues): array
    {
        if (is_string($attrValues)) {
            $decoded = json_decode($attrValues, true);
            $attrValues = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($attrValues)) {
            return [];
        }

        $attributes = [];
        foreach ($attrValues as $index => $attr) {
            if (!is_array($attr)) {
                continue;
            }

            $name = trim((string) ($attr['name'] ?? $attr['label'] ?? ''));
            if ($name === '') {
                continue;
            }

            $values = $attr['selected_values'] ?? $attr['values'] ?? [];
            if (is_string($values)) {
                $values = array_filter(array_map('trim', explode('|', $values)));
            }
            $values = is_array($values) ? $values : [];
            $values = array_values(array_unique(array_filter(array_map(static function ($value): string {
                return trim((string) $value);
            }, $values))));
            $value = trim((string) ($attr['value'] ?? ''));
            if ($value === '' && $values !== []) {
                $value = implode('、', $values);
            }
            if ($value === '') {
                continue;
            }

            $attributes[] = [
                'id' => (int) ($attr['param_id'] ?? $attr['attribute_id'] ?? $index + 1),
                'name' => $name,
                'value' => $value,
                'selected_values' => $values,
                'sort' => (int) ($attr['sort'] ?? 0),
            ];
        }

        return $attributes;
    }

}
