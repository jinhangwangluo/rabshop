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

namespace app\controller\mobile\comment;

use app\BaseController;
use app\common\service\content\ContentCommentService;
use think\annotation\Inject;
use think\Response;

/**
 * 移动端内容评论控制器.
 */
class Comment extends BaseController
{
    /**
     * 评论服务.
     *
     * @var ContentCommentService
     */
    #[Inject]
    protected ContentCommentService $commentService;

    /**
     * 获取文章评论列表（一级 + 二级扁平化）.
     *
     * GET 参数：
     * - article_id int 内容 ID。
     *
     * @return Response 返回评论列表。
     */
    public function list(): Response
    {
        $params = $this->request->getMore([
            ['article_id', 0],
        ]);

        if (empty($params['article_id'])) {
            return $this->fail('请指定文章');
        }

        $result = $this->commentService->getMobileList(
            (int)$params['article_id']
        );

        return $this->success($result);
    }

    /**
     * 发布一级评论.
     *
     * POST 参数：
     * - article_id int 内容 ID。
     * - content string 评论内容。
     * - images array 评论图片列表。
     *
     * @return Response 返回评论结果。
     */
    public function create(): Response
    {
        $data = $this->request->postMore([
            ['article_id', 0],
            ['content', ''],
            ['images', []],
        ]);

        if (empty($data['article_id'])) {
            return $this->fail('请指定文章');
        }

        $userId = $this->request->user_id ?? 0;

        $result = $this->commentService->createComment(
            (int)$userId,
            (int)$data['article_id'],
            (string)$data['content'],
            (array)$data['images']
        );

        return $this->success('评论成功', $result->toArray());
    }

    /**
     * 回复评论（二级）.
     *
     * POST 参数：
     * - article_id int 内容 ID。
     * - pid int 被回复的一级评论 ID。
     * - reply_to_uid int 被回复用户 ID。
     * - reply_to_nickname string 被回复用户昵称。
     * - content string 回复内容。
     * - images array 回复图片列表。
     *
     * @return Response 返回回复结果。
     */
    public function reply(): Response
    {
        $data = $this->request->postMore([
            ['article_id', 0],
            ['pid', 0],
            ['reply_to_uid', 0],
            ['reply_to_nickname', ''],
            ['content', ''],
            ['images', []],
        ]);

        if (empty($data['article_id'])) {
            return $this->fail('请指定文章');
        }

        if (empty($data['pid'])) {
            return $this->fail('请指定回复的评论');
        }

        $userId = $this->request->user_id ?? 0;

        $result = $this->commentService->replyComment(
            (int)$userId,
            (int)$data['article_id'],
            (int)$data['pid'],
            (string)$data['content'],
            (array)$data['images']
        );

        return $this->success('回复成功', $result->toArray());
    }

    /**
     * 用户删除自己的评论.
     *
     * POST 参数：
     * - comment_id int 评论 ID。
     *
     * @return Response 返回删除结果。
     */
    public function delete(): Response
    {
        $commentId = (int)$this->request->post('comment_id', 0);

        if (empty($commentId)) {
            return $this->fail('请指定评论');
        }

        $userId = $this->request->user_id ?? 0;

        $this->commentService->deleteByUser($commentId, (int)$userId);

        return $this->success('删除成功');
    }
}
