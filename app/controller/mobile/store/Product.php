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

namespace app\controller\mobile\store;

use app\BaseController;
use app\common\service\store\ProductAttributeService;
use app\common\service\store\ProductBrandService;
use app\common\service\store\MobileProductService;
use think\annotation\Inject;
use think\Response;

/**
 * 提供移动端普通商品列表、独立详情、规格、分类和筛选选项，商品公开性由前台服务校验。
 */
class Product extends BaseController
{
    /**
     * 前台商品服务
     */
    #[Inject]
    protected MobileProductService $productService;

    /**
     * 商品列表
     *
     * GET 参数：
     * - category_id int 平台商品分类ID
     * - merchant_id int 可选商户ID，只展示该商户的公开商品
     * - keyword string 关键词
     * - sort string 排序：sales/price_asc/price_desc/view/默认 sort
     * - brand_id int 品牌ID
     * - price_min/price_max string 价格区间
     * - merchant_type string 商户类型：self/non_self
     * - is_free_shipping int 是否包邮
     * - include_child_categories int 是否包含子孙分类，1 表示包含
     * - param_filters array 商品参数筛选
     * - page/limit 分页
     *
     * @return Response 列表 + 分页
     */
    public function list(): Response
    {
        $params = [
            'merchant_id' => (int) $this->request->get('merchant_id/d', 0),
            'page' => $this->request->get('page', null),
            'limit' => $this->request->get('limit', null),
            'user_id' => (int)($this->request->user_id ?? 0),
            'category_id' => (int) $this->request->get('category_id/d', 0),
            'keyword' => trim((string) $this->request->get('keyword', '')),
            'sort' => trim((string) $this->request->get('sort', '')),
            'brand_id' => (int) $this->request->get('brand_id/d', 0),
            'price_min' => trim((string) $this->request->get('price_min', '')),
            'price_max' => trim((string) $this->request->get('price_max', '')),
            'merchant_type' => trim((string) $this->request->get('merchant_type', '')),
            'is_free_shipping' => (int) $this->request->get('is_free_shipping/d', 0),
            'include_child_categories' => (int) $this->request->get('include_child_categories/d', 0),
            'param_filters' => $this->parseParamFilters($this->request->get('param_filters', [])),
        ];
        return $this->success('获取成功', $this->productService->getList($params));
    }

    /**
     * 读取普通商品详情、规格与展示统计，用户价格身份来自认证上下文。
     *
     * GET 参数：
     * - id int 店铺商品ID
     *
     * @return Response 包含商品详情或参数错误的响应。
     */
    public function detail(): Response
    {
        $id = (int) $this->request->get('id/d', 0);
        if ($id <= 0) {
            return $this->fail('参数错误');
        }
        return $this->success(
            '获取成功',
            $this->productService->getDetail($id, (int)($this->request->user_id ?? 0))
        );
    }

    /**
     * 按商品 ID 查询当前用户收藏状态，兼容旧 id 字段。
     *
     * GET 参数：
     * - product_id int 商品 ID，兼容 id。
     *
     * @return Response 包含收藏状态或参数错误的响应。
     */
    public function favoriteStatus(): Response
    {
        $id = (int) $this->request->get('product_id/d', 0);
        if ($id <= 0) {
            $id = (int) $this->request->get('id/d', 0);
        }
        if ($id <= 0) {
            return $this->fail('商品ID不能为空');
        }

        return $this->success(
            '获取成功',
            $this->productService->getFavoriteStatus((int)($this->request->user_id ?? 0), $id)
        );
    }

    /**
     * 按商品 ID 切换当前用户收藏状态，兼容旧 id 字段。
     *
     * POST 参数：
     * - product_id int 商品 ID，兼容 id。
     *
     * @return Response 包含切换后的收藏状态或参数错误的响应。
     */
    public function toggleFavorite(): Response
    {
        $id = (int) $this->request->post('product_id/d', 0);
        if ($id <= 0) {
            $id = (int) $this->request->post('id/d', 0);
        }
        if ($id <= 0) {
            return $this->fail('商品ID不能为空');
        }

        return $this->success(
            '操作成功',
            $this->productService->toggleFavorite((int)($this->request->user_id ?? 0), $id)
        );
    }

    /**
     * 读取普通商品规格列表、SKU 列表及默认 SKU。
     *
     * GET 参数：
     * - id int 店铺商品ID
     *
     * @return Response 包含规格数据或参数错误的响应。
     */
    public function sku(): Response
    {
        $id = (int) $this->request->get('id/d', 0);
        if ($id <= 0) {
            return $this->fail('参数错误');
        }
        return $this->success('获取成功', $this->productService->getSkuData($id));
    }

    /**
     * 按商品和当前认证用户查询可用优惠券。
     *
     * GET 参数：
     * - id int 店铺商品ID
     *
     * @return Response 包含可用优惠券列表或参数错误的响应。
     */
    public function coupons(): Response
    {
        $id = (int) $this->request->get('id/d', 0);
        if ($id <= 0) {
            return $this->fail('参数错误');
        }

        return $this->success('获取成功', $this->productService->getCoupons($id, (int)($this->request->user_id ?? 0)));
    }

    /**
     * 按商品 ID 及限制后的最低星级查询高分评价。
     *
     * GET 参数：
     * - id int 店铺商品ID
     * - page int 页码
     * - limit int 每页数量
     * - star_min int 最低星级
     *
     * @return Response 包含评价列表和统计或参数错误的响应。
     */
    public function reviews(): Response
    {
        $id = (int) $this->request->get('id/d', 0);
        if ($id <= 0) {
            return $this->fail('参数错误');
        }

        return $this->success('获取成功', $this->productService->getReviews(
            $id,
            max(1, min(5, (int)$this->request->get('star_min/d', 4)))
        ));
    }

    /**
     * 按可选分类 ID 范围查询商品分类树。
     *
     * @return Response 包含树形商品分类数据的响应。
     */
    public function categories(): Response
    {
        return $this->success('获取成功', $this->productService->getCategories($this->parseCategoryIds()));
    }

    /**
     * 读取平台可用于商品筛选的品牌选项。
     *
     * @return Response 包含品牌选项的响应。
     */
    public function brandOptions(): Response
    {
        return $this->success('获取成功', app()->make(ProductBrandService::class)->options(0));
    }

    /**
     * 读取平台可用于商品筛选的属性选项。
     *
     * @return Response 包含商品属性选项的响应。
     */
    public function attributeOptions(): Response
    {
        return $this->success('获取成功', app()->make(ProductAttributeService::class)->options(0));
    }

    /**
     * 按分类读取平台商品参数模板。
     *
     * @param int $categoryId 平台商品分类 ID。
     * @return Response 包含分类对应参数模板的响应。
     */
    public function attributeTemplate(int $categoryId): Response
    {
        return $this->success('获取成功', app()->make(\app\common\service\store\ProductAttributeService::class)->findByCategoryId($categoryId, 0));
    }

    /**
     * 兼容数组和逗号分隔文本，只保留正整数分类 ID。
     *
     * @return array<int> 请求中有效的分类 ID 列表。
     */
    protected function parseCategoryIds(): array
    {
        $rawIds = $this->request->get('category_ids', []);
        if (is_string($rawIds)) {
            $rawIds = array_filter(array_map('trim', explode(',', $rawIds)));
        }

        if (!is_array($rawIds)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $rawIds), static fn (int $id): bool => $id > 0));
    }

    /**
     * 兼容数组与 JSON 文本的参数筛选输入，解析失败时返回空条件。
     *
     * @param mixed $rawFilters 请求中的参数筛选数组或 JSON 文本。
     * @return array<int, array<string, mixed>> 参数筛选字段数组。
     */
    protected function parseParamFilters(mixed $rawFilters): array
    {
        if (is_string($rawFilters)) {
            $decoded = json_decode($rawFilters, true);
            $rawFilters = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($rawFilters)) {
            return [];
        }

        return $rawFilters;
    }
}
