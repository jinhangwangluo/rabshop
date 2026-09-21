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

namespace app\common\service\content;

use app\common\exception\AdminResponseException;
use app\common\model\content\ContentComment;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\message\BackendMessageEventService;
use app\common\service\user\UserService;

/**
 * 内容评论服务
 */
class ContentCommentService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 对应模型
     */
    protected function setModel(): string
    {
        return ContentComment::class;
    }

    /**
     * 后台评论列表
     *
     * @param array $params 搜索参数（article_id/user_id/nickname/keyword/is_root/create_time）
     * @return array
     */
    public function getAdminList(array $params = []): array
    {
        return $this->getDefaultList(
            $params,
            ['*'],
            ['user', 'article'],
            ['id' => 'desc']
        )->toArray();
    }

    /**
     * 移动端获取内容评论列表（两级结构）
     *
     * 查询策略：先分页获取一级评论，再批量取子评论，PHP 内存组装 children。
     *
     * @param int   $articleId 内容ID
     * @param int   $page      当前页
     * @param int   $limit     每页数量
     * @return array { list: array, count: int }
     */
    public function getMobileList(int $articleId, int $page = 1, int $limit = 15): array
    {
        if (!app()->make(ContentDisplayConfigService::class)->isContentEnabled()) {
            return ['list' => [], 'count' => 0];
        }

        [$page, $limit] = $this->getPageValue();
        $model = $this->getModel();

        // 一级评论分页
        $total = $model->where('article_id', $articleId)
            ->where('pid', ContentComment::PID_ROOT)
            ->count();

        $userFields = ['uid', 'nickname', 'avatar'];

        $roots = $model->where('article_id', $articleId)
            ->where('pid', ContentComment::PID_ROOT)
            ->with([
                'user' => function ($query) use ($userFields): void {
                    $query->field($userFields);
                },
            ])
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        if (empty($roots)) {
            return ['list' => [], 'count' => $total];
        }

        // 批量取二级评论，避免 N+1
        $rootIds = array_column($roots, 'id');
        $children = $model->whereIn('pid', $rootIds)
            ->with([
                'user' => function ($query) use ($userFields): void {
                    $query->field($userFields);
                },
            ])
            ->order('id', 'asc')
            ->select()
            ->toArray();

        // 按 pid 分组挂载
        $childMap = [];
        foreach ($children as $child) {
            $childMap[$child['pid']][] = $child;
        }

        foreach ($roots as &$root) {
            $root['children'] = $childMap[$root['id']] ?? [];
        }
        unset($root);

        return ['list' => $roots, 'count' => $total];
    }

    /**
     * 发布评论（一级）
     *
     * @param int    $userId    评论者用户ID
     * @param int    $articleId 内容ID
     * @param string $content   评论文字
     * @param array  $images    图片URL数组（最多5张）
     * @return ContentComment
     * @throws AdminResponseException
     */
    public function createComment(int $userId, int $articleId, string $content, array $images = []): ContentComment
    {
        if (!app()->make(ContentDisplayConfigService::class)->isContentEnabled()) {
            throw new AdminResponseException('内容功能已关闭');
        }

        $this->checkCommentBan($userId);
        $this->validateImages($images);

        if (empty(trim($content)) && empty($images)) {
            throw new AdminResponseException('评论内容不能为空');
        }

        $comment = $this->create([
            'article_id'       => $articleId,
            'user_id'          => $userId,
            'pid'              => ContentComment::PID_ROOT,
            'root_id'          => 0,
            'reply_to_uid'     => 0,
            'reply_to_nickname' => '',
            'content'          => trim($content),
            'images'           => $images,
        ]);

        app()->make(BackendMessageEventService::class)->platformCommentCreated($comment->toArray());

        return $comment;
    }

    /**
     * 回复评论（二级）
     *
     * @param int    $userId          回复者用户ID
     * @param int    $articleId       内容ID
     * @param int    $parentId        被回复的评论ID（直接父级）
     * @param string $content         回复内容
     * @param array  $images          图片URL数组（最多5张）
     * @return ContentComment
     * @throws AdminResponseException
     */
    public function replyComment(int $userId, int $articleId, int $parentId, string $content, array $images = []): ContentComment
    {
        if (!app()->make(ContentDisplayConfigService::class)->isContentEnabled()) {
            throw new AdminResponseException('内容功能已关闭');
        }

        $this->checkCommentBan($userId);
        $this->validateImages($images);

        if (empty(trim($content)) && empty($images)) {
            throw new AdminResponseException('回复内容不能为空');
        }

        $parent = $this->getModel()->find($parentId);
        if (!$parent) {
            throw new AdminResponseException('被回复的评论不存在');
        }

        // root_id：若父级是一级评论，root_id = parent.id；否则继承父级 root_id
        $rootId = (int)$parent->pid === ContentComment::PID_ROOT
            ? (int)$parent->id
            : (int)$parent->root_id;

        // 获取被回复者昵称（快照冗余）
        $replyToUser = app()->make(UserService::class)->getModel()->find((int)$parent->user_id);
        $replyToNickname = $replyToUser ? (string)$replyToUser->nickname : '';

        $comment = $this->create([
            'article_id'        => $articleId,
            'user_id'           => $userId,
            'pid'               => $parentId,
            'root_id'           => $rootId,
            'reply_to_uid'      => (int)$parent->user_id,
            'reply_to_nickname' => $replyToNickname,
            'content'           => trim($content),
            'images'            => $images,
        ]);

        app()->make(BackendMessageEventService::class)->platformCommentCreated($comment->toArray());

        return $comment;
    }

    /**
     * 用户删除自己的评论（软删除）
     *
     * @param int $commentId 评论ID
     * @param int $userId    当前用户ID
     * @return void
     * @throws AdminResponseException
     */
    public function deleteByUser(int $commentId, int $userId): void
    {
        $comment = $this->getModel()->find($commentId);
        if (!$comment) {
            throw new AdminResponseException('评论不存在');
        }
        if ((int)$comment->user_id !== $userId) {
            throw new AdminResponseException('无权删除他人评论');
        }
        $comment->delete();
    }

    /**
     * 管理员删除评论（软删除）
     *
     * @param int $commentId 评论ID
     * @return void
     * @throws AdminResponseException
     */
    public function adminDelete(int $commentId): void
    {
        $comment = $this->getModel()->find($commentId);
        if (!$comment) {
            throw new AdminResponseException('评论不存在');
        }
        $comment->delete();
    }

    /**
     * 校验用户是否被禁止发评论
     *
     * @param int $userId 用户ID
     * @return void
     * @throws AdminResponseException
     */
    public function checkCommentBan(int $userId): void
    {
        $user = app()->make(UserService::class)->getModel()->find($userId);
        if ($user && (int)$user->is_comment_ban === 1) {
            throw new AdminResponseException('您已被禁止发表评论');
        }
    }

    /**
     * 校验图片数量
     *
     * @param array $images 图片数组
     * @return void
     * @throws AdminResponseException
     */
    private function validateImages(array $images): void
    {
        if (count($images) > ContentComment::MAX_IMAGES) {
            throw new AdminResponseException('最多上传 ' . ContentComment::MAX_IMAGES . ' 张图片');
        }
    }
}
