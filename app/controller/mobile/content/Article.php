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
use app\common\service\content\ArticleService;
use app\common\service\user\UserBrowseRecordService;
use app\common\service\user\UserLikeService;
use think\annotation\Inject;
use think\Response;

/**
 * 移动端内容控制器（图文/图片）.
 */
class Article extends BaseController
{
    /**
     * 内容服务.
     *
     * @var ArticleService
     */
    #[Inject]
    protected ArticleService $service;

    /**
     * 用户点赞服务.
     *
     * @var UserLikeService
     */
    #[Inject]
    protected UserLikeService $likeService;

    /**
     * 用户浏览记录服务.
     *
     * @var UserBrowseRecordService
     */
    #[Inject]
    protected UserBrowseRecordService $browseRecordService;

    /**
     * 获取移动端内容列表。
     *
     * GET 参数：
     * - page int 页码。
     * - limit int 每页数量。
     * - keyword string 搜索关键词。
     * - category_id int 内容分类 ID。
     * - content_type int 内容类型。
     * - min_price float 最低价。
     * - max_price float 最高价。
     * - merchant_id int 商户 ID。
     * - sort string 排序：default/hot/view/price_asc/price_desc。
     *
     * @return Response 返回分页内容列表。
     */
    public function index(): Response
    {
        $params = [
            'page' => (int)$this->request->get('page/d', 1),
            'limit' => (int)$this->request->get('limit/d', 10),
            'keyword' => trim((string)$this->request->get('keyword', '')),
            'category_id' => (int)$this->request->get('category_id/d', 0),
            'content_type' => (int)$this->request->get('content_type/d', 0),
            'min_price' => trim((string)$this->request->get('min_price', '')),
            'max_price' => trim((string)$this->request->get('max_price', '')),
            'merchant_id' => max(0, (int)$this->request->get('merchant_id/d', 0)),
            'sort' => trim((string)$this->request->get('sort', 'default')),
            'user_id' => (int)($this->request->user_id ?? 0),
        ];

        return $this->success('获取成功', $this->service->getMobileList($params));
    }

    /**
     * 获取移动端内容详情。
     *
     * GET 参数：
     * - id int 内容 ID。
     * - special_id int 专题 ID，从专题进入小说详情时传入。
     * - chapter_page int 专题章节页码，默认 1。
     * - chapter_limit int 专题章节每页数量，默认 20。
     *
     * 匿名用户返回公共详情，登录用户额外返回已购状态。
     *
     * @return Response 返回内容详情。
     */
    public function detail(): Response
    {
        $articleId = (int)$this->request->get('id/d', 0);
        if ($articleId <= 0) {
            return $this->fail('内容ID不能为空');
        }

        $userId = (int)($this->request->user_id ?? 0);
        $data = $this->service->getMobileDetail(
            $articleId,
            $userId,
            (int)$this->request->get('special_id/d', 0),
            (int)$this->request->get('chapter_page/d', 1),
            (int)$this->request->get('chapter_limit/d', 20)
        );

        if ($userId > 0 && $data !== []) {
            $this->browseRecordService->recordBrowse(
                $userId,
                UserBrowseRecordService::TARGET_TYPE_CONTENT,
                $articleId,
                (int)($data['mer_id'] ?? 0)
            );
        }

        return $this->success('获取成功', $data);
    }

    /**
     * 查询内容收藏状态。
     *
     * GET 参数：
     * - article_id int 内容 ID，兼容 id。
     *
     * @return Response 返回是否已收藏。
     */
    public function favoriteStatus(): Response
    {
        $articleId = (int)$this->request->get('article_id/d', 0);
        if ($articleId <= 0) {
            $articleId = (int)$this->request->get('id/d', 0);
        }
        if ($articleId <= 0) {
            return $this->fail('内容ID不能为空');
        }

        $data = $this->service->getFavoriteStatus((int)($this->request->user_id ?? 0), $articleId);

        return $this->success('获取成功', $data);
    }

    /**
     * 切换内容收藏状态。
     *
     * POST 参数：
     * - article_id int 内容 ID，兼容 id。
     *
     * @return Response 返回切换后的收藏状态。
     */
    public function toggleFavorite(): Response
    {
        $articleId = (int)$this->request->post('article_id/d', 0);
        if ($articleId <= 0) {
            $articleId = (int)$this->request->post('id/d', 0);
        }
        if ($articleId <= 0) {
            return $this->fail('内容ID不能为空');
        }

        $data = $this->service->toggleFavorite((int)($this->request->user_id ?? 0), $articleId);

        return $this->success('操作成功', $data);
    }

    /**
     * 切换内容点赞状态。
     *
     * POST 参数：
     * - article_id int 内容 ID，兼容 id。
     *
     * @return Response 返回切换后的点赞状态与点赞数。
     */
    public function toggleLike(): Response
    {
        $articleId = (int)$this->request->post('article_id/d', 0);
        if ($articleId <= 0) {
            $articleId = (int)$this->request->post('id/d', 0);
        }
        if ($articleId <= 0) {
            return $this->fail('内容ID不能为空');
        }

        $result = $this->likeService->toggleContentLike(
            (int)($this->request->user_id ?? 0),
            $articleId
        );

        return $this->success($result);
    }
}
