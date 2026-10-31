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

namespace app\controller\mobile\content;

use app\BaseController;
use app\common\service\content\SpecialService;
use think\annotation\Inject;
use think\Response;

/**
 * 移动端专题控制器.
 */
class Special extends BaseController
{
    /**
     * 专题服务.
     *
     * @var SpecialService
     */
    #[Inject]
    protected SpecialService $service;

    /**
     * 获取移动端专题列表.
     *
     * GET 参数：
     * - keyword string 搜索关键词。
     * - category_id int 专题分类 ID。
     * - merchant_id int 商户 ID。
     * - min_price float 最低价格。
     * - max_price float 最高价格。
     * - sort string 排序方式：default/sales/latest。
     *
     * @return Response 返回专题分页列表。
     */
    public function index(): Response
    {
        $params = $this->buildListParams();
        $data = $this->service->getMobileList($params);

        return $this->success('获取成功', $data);
    }

    /**
     * 获取移动端专题详情.
     *
     * GET 参数：
     * - id int 专题 ID；兼容 special_id。
     *
     * @return Response 返回专题详情。
     */
    public function detail(): Response
    {
        $specialId = (int)$this->request->get('id/d', $this->request->get('special_id/d', 0));
        if ($specialId <= 0) {
            return $this->fail('专题ID不能为空');
        }

        $data = $this->service->getMobileDetail($specialId, (int)($this->request->user_id ?? 0));

        return $this->success('获取成功', $data);
    }

    /**
     * 获取专题关联商品列表.
     *
     * GET 参数：
     * - id int 专题 ID；兼容 special_id。
     * - page int 页码，默认 1。
     * - limit int 每页数量，默认 10，最大 20。
     *
     * 匿名用户可访问；登录用户会返回会员价相关字段。
     *
     * @return Response 返回关联商品列表及分页信息。
     */
    public function products(): Response
    {
        $specialId = (int)$this->request->get('id/d', $this->request->get('special_id/d', 0));
        if ($specialId <= 0) {
            return $this->fail('专题ID不能为空');
        }

        $params = $this->request->only(['page', 'limit']);
        $data = $this->service->getMobileRelatedProducts(
            $specialId,
            (int)($this->request->user_id ?? 0),
            is_array($params) ? $params : []
        );

        return $this->success('获取成功', $data);
    }

    /**
     * 获取专题下的音频列表.
     *
     * GET 参数：
     * - special_id int 专题 ID。
     * - current_id int 当前音频内容 ID。
     *
     * @return Response 返回专题音频列表。
     */
    public function audioList(): Response
    {
        $specialId = (int)$this->request->get('special_id/d', $this->request->get('id/d', 0));
        if ($specialId <= 0) {
            return $this->fail('专题ID不能为空');
        }

        $currentId = (int)$this->request->get('current_id/d', 0);
        $data = $this->service->getMobileAudioList($specialId, $currentId);

        return $this->success('获取成功', $data);
    }

    /**
     * 获取专题推荐列表.
     *
     * GET 参数：
     * - limit int 推荐数量，默认 4。
     * - exclude_ids array|string 需要排除的专题 ID 列表。
     * - exclude_id int 单个需要排除的专题 ID。
     *
     * @return Response 返回专题推荐列表。
     */
    public function recommend(): Response
    {
        $rawExcludeIds = $this->request->get('exclude_ids', '');
        $excludeIds = parse_ids(
            $this->request->get('exclude_ids/a', []),
            is_array($rawExcludeIds) ? '' : (string)$rawExcludeIds
        );
        $singleExcludeId = (int)$this->request->get('exclude_id/d', 0);
        if ($singleExcludeId > 0) {
            $excludeIds[] = $singleExcludeId;
        }

        $data = $this->service->getRecommendList(
            (int)($this->request->user_id ?? 0),
            [
                'limit' => max(1, (int)$this->request->get('limit/d', 4)),
                'exclude_ids' => array_values(array_unique(array_filter(array_map('intval', $excludeIds)))),
            ]
        );

        return $this->success('获取成功', $data);
    }

    /**
     * 查询专题收藏状态.
     *
     * GET 参数：
     * - special_id int 专题 ID。
     *
     * @return Response 返回是否已收藏。
     */
    public function favoriteStatus(): Response
    {
        $specialId = (int)$this->request->get('special_id/d', 0);
        if ($specialId <= 0) {
            return $this->fail('专题ID不能为空');
        }

        $data = $this->service->getFavoriteStatus((int)($this->request->user_id ?? 0), $specialId);

        return $this->success('获取成功', $data);
    }

    /**
     * 切换专题收藏状态.
     *
     * POST 参数：
     * - special_id int 专题 ID。
     *
     * @return Response 返回切换后的收藏状态。
     */
    public function toggleFavorite(): Response
    {
        $specialId = (int)$this->request->post('special_id/d', 0);
        if ($specialId <= 0) {
            return $this->fail('专题ID不能为空');
        }

        $data = $this->service->toggleFavorite((int)$this->request->user_id, $specialId);

        return $this->success('操作成功', $data);
    }

    /**
     * 记录专题预览行为.
     *
     * POST 参数：
     * - special_id int 专题 ID。
     *
     * @return Response 返回记录结果。
     */
    public function recordPreview(): Response
    {
        $specialId = (int)$this->request->post('special_id/d', 0);
        if ($specialId <= 0) {
            return $this->fail('专题ID不能为空');
        }

        $data = $this->service->recordPreview((int)($this->request->user_id ?? 0), $specialId);

        return $this->success('记录成功', $data);
    }

    /**
     * 组装移动端专题列表查询参数.
     *
     * @return array 专题列表筛选参数。
     */
    protected function buildListParams(): array
    {
        $sort = trim((string)$this->request->get('sort', 'default'));
        $minPrice = $this->normalizePrice($this->request->get('min_price', ''));
        $maxPrice = $this->normalizePrice($this->request->get('max_price', ''));

        if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
            [$minPrice, $maxPrice] = [$maxPrice, $minPrice];
        }

        return [
            'keyword' => trim((string)$this->request->get('keyword', '')),
            'category_id' => max(0, (int)$this->request->get('category_id', 0)),
            'merchant_id' => max(0, (int)$this->request->get('merchant_id', 0)),
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'sort' => in_array($sort, ['default', 'sales', 'latest'], true) ? $sort : 'default',
            'user_id' => (int)($this->request->user_id ?? 0),
        ];
    }

    /**
     * 归一化价格筛选值.
     *
     * @param mixed $value 原始价格值。
     * @return float|null 合法价格或 null。
     */
    protected function normalizePrice(mixed $value): ?float
    {
        if ($value === '' || $value === null) {
            return null;
        }

        if (!is_numeric((string)$value)) {
            return null;
        }

        return max(0, (float)$value);
    }
}
