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

use app\common\exception\AdminResponseException;
use app\common\model\user\UserFavorite;
use app\common\service\BaseService;
use app\common\service\content\ArticleService;
use app\common\service\content\ContentDisplayConfigService;

/**
 * 用户点赞服务.
 */
class UserLikeService extends BaseService
{
    public const TARGET_TYPE_CONTENT = 'content_like';

    /**
     * 对应模型.
     */
    protected function setModel(): string
    {
        return UserFavorite::class;
    }

    /**
     * 判断用户是否已点赞内容.
     *
     * @param int $userId 用户 ID。
     * @param int $articleId 内容 ID。
     * @return bool
     */
    public function isContentLiked(int $userId, int $articleId): bool
    {
        if (!app()->make(ContentDisplayConfigService::class)->isContentEnabled()) {
            return false;
        }

        if ($userId <= 0 || $articleId <= 0) {
            return false;
        }

        return (bool)$this->getModel()
            ->where('user_id', $userId)
            ->where('target_type', self::TARGET_TYPE_CONTENT)
            ->where('target_id', $articleId)
            ->where('status', 1)
            ->count();
    }

    /**
     * 切换内容点赞状态.
     *
     * @param int $userId 用户 ID。
     * @param int $articleId 内容 ID。
     * @return array
     * @throws AdminResponseException
     */
    public function toggleContentLike(int $userId, int $articleId): array
    {
        if (!app()->make(ContentDisplayConfigService::class)->isContentEnabled()) {
            throw new AdminResponseException('内容功能已关闭');
        }

        if ($userId <= 0) {
            throw new AdminResponseException('请先登录');
        }
        if ($articleId <= 0) {
            throw new AdminResponseException('内容ID不能为空');
        }

        $articleService = app()->make(ArticleService::class);
        $article = $articleService->get($articleId);
        if (!$article) {
            throw new AdminResponseException('内容不存在');
        }

        return $this->transaction(function () use ($userId, $articleId, $articleService): array {
            $record = $this->getModel()
                ->where('user_id', $userId)
                ->where('target_type', self::TARGET_TYPE_CONTENT)
                ->where('target_id', $articleId)
                ->find();

            $now = date('Y-m-d H:i:s');
            $nextStatus = 1;

            if ($record) {
                $nextStatus = (int)($record['status'] ?? 0) === 1 ? 0 : 1;
                $record->save([
                    'status' => $nextStatus,
                    'update_time' => $now,
                ]);
            } else {
                $this->create([
                    'user_id' => $userId,
                    'target_type' => self::TARGET_TYPE_CONTENT,
                    'target_id' => $articleId,
                    'merchant_id' => 0,
                    'status' => 1,
                    'create_time' => $now,
                    'update_time' => $now,
                ]);
            }

            $articleQuery = $articleService->getModel()->where('id', $articleId);
            if ($nextStatus === 1) {
                $articleQuery->inc('like_count')->update();
            } else {
                $articleQuery->where('like_count', '>', 0)->dec('like_count')->update();
            }

            $likeCount = (int)$articleService->getModel()
                ->where('id', $articleId)
                ->value('like_count');

            return [
                'target_type' => 'content',
                'target_id' => $articleId,
                'is_liked' => $nextStatus === 1,
                'status' => $nextStatus,
                'like_count' => $likeCount,
            ];
        });
    }
}
