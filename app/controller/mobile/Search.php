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

namespace app\controller\mobile;

use app\BaseController;
use app\common\service\search\MobileSearchService;
use think\annotation\Inject;
use think\Response;

/**
 * 移动端统一搜索控制器.
 */
class Search extends BaseController
{
    /**
     * 搜索服务.
     *
     * @var MobileSearchService
     */
    #[Inject]
    protected MobileSearchService $service;

    /**
     * 获取搜索默认态数据.
     *
     * GET 参数：
     * - limit int 热门搜索与发现内容数量.
     *
     * @return Response
     */
    public function meta(): Response
    {
        $data = $this->service->getMeta(
            (int)($this->request->user_id ?? 0),
            [
                'limit' => (int)$this->request->get('limit/d', 10),
            ]
        );

        return $this->success('获取成功', $data);
    }

    /**
     * 执行统一搜索.
     *
     * GET 参数：
     * - keyword string 搜索关键词.
     * - type string 结果类型：all/goods/special/content/merchant，兼容 product=goods.
     * - page int 页码.
     * - limit int 每页数量.
     * - sort string 排序.
     * - category_id/brand_id int 商品筛选ID.
     * - price_min/price_max string 价格区间.
     * - merchant_type string 商户类型：self/non_self.
     * - is_free_shipping int 是否包邮.
     * - param_filters array 商品参数筛选.
     *
     * @return Response
     */
    public function result(): Response
    {
        $data = $this->service->search([
            'keyword' => trim((string)$this->request->get('keyword', $this->request->get('search', ''))),
            'type' => trim((string)$this->request->get('type', 'all')),
            'page' => (int)$this->request->get('page/d', 1),
            'limit' => (int)$this->request->get('limit/d', 10),
            'sort' => trim((string)$this->request->get('sort', 'default')),
            'category_id' => (int)$this->request->get('category_id/d', 0),
            'brand_id' => (int)$this->request->get('brand_id/d', 0),
            'price_min' => trim((string)$this->request->get('price_min', '')),
            'price_max' => trim((string)$this->request->get('price_max', '')),
            'merchant_type' => trim((string)$this->request->get('merchant_type', '')),
            'is_free_shipping' => (int)$this->request->get('is_free_shipping/d', 0),
            'param_filters' => $this->parseParamFilters($this->request->get('param_filters', [])),
        ]);

        return $this->success('搜索成功', $data);
    }

    /**
     * 记录搜索行为.
     *
     * POST 参数：
     * - keyword string 搜索关键词.
     * - result_count int 搜索结果数量.
     *
     * @return Response
     */
    public function record(): Response
    {
        $keyword = trim((string)$this->request->post('keyword', ''));
        if ($keyword === '') {
            $keyword = trim((string)$this->request->post('search', ''));
        }

        $data = $this->service->recordKeyword(
            (int)($this->request->user_id ?? 0),
            $keyword,
            (int)$this->request->post('result_count/d', 0)
        );

        return $this->success('记录成功', $data);
    }

    /**
     * 清空搜索历史.
     *
     * @return Response
     */
    public function clearHistory(): Response
    {
        return $this->success('清空成功', $this->service->clearHistory((int)($this->request->user_id ?? 0)));
    }

    /**
     * 解析参数筛选条件。
     *
     * @return array<int, array<string, mixed>>
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
