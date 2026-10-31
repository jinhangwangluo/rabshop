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

namespace app\common\service\search;

use app\common\model\search\UserSearchHistory;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;

/**
 * 用户搜索历史服务.
 */
class UserSearchHistoryService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 设置对应模型.
     */
    protected function setModel(): string
    {
        return UserSearchHistory::class;
    }

    /**
     * 获取用户最近搜索.
     *
     * @param int $userId 用户ID.
     * @param int $limit 返回数量.
     * @return array
     */
    public function getRecentKeywords(int $userId, int $limit = 10): array
    {
        if ($userId <= 0) {
            return [];
        }

        $limit = max(1, min(20, $limit));
        $list = $this->getModel()
            ->where('user_id', $userId)
            ->field(['id', 'keyword', 'search_count', 'last_search_time'])
            ->order('last_search_time', 'desc')
            ->order('id', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();

        return array_values(array_filter(array_map(function (array $item): ?array {
            $keyword = $this->normalizeKeyword((string)($item['keyword'] ?? ''));
            if ($keyword === '') {
                return null;
            }

            return [
                'id' => (int)($item['id'] ?? 0),
                'search' => $keyword,
                'keyword' => $keyword,
                'search_count' => (int)($item['search_count'] ?? 0),
                'last_search_time' => (string)($item['last_search_time'] ?? ''),
            ];
        }, $list)));
    }

    /**
     * 记录用户搜索关键词.
     *
     * @param int $userId 用户ID.
     * @param string $keyword 关键词.
     * @return array 更新后的最近搜索.
     */
    public function recordKeyword(int $userId, string $keyword): array
    {
        $keyword = $this->normalizeKeyword($keyword);
        if ($userId <= 0 || $keyword === '') {
            return [];
        }

        $now = date('Y-m-d H:i:s');
        $history = $this->getModel()
            ->where('user_id', $userId)
            ->where('keyword', $keyword)
            ->find();

        if ($history) {
            $this->getModel()
                ->where('id', (int)$history['id'])
                ->inc('search_count')
                ->update();
            $this->getModel()
                ->where('id', (int)$history['id'])
                ->update(['last_search_time' => $now]);
        } else {
            $this->create([
                'user_id' => $userId,
                'keyword' => $keyword,
                'search_count' => 1,
                'last_search_time' => $now,
            ]);
        }

        $this->pruneUserHistory($userId, 10);
        return $this->getRecentKeywords($userId, 10);
    }

    /**
     * 清空用户搜索历史.
     *
     * @param int $userId 用户ID.
     * @return void
     */
    public function clearUserHistory(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $this->getModel()->where('user_id', $userId)->delete();
    }

    /**
     * 裁剪用户历史数量.
     *
     * @param int $userId 用户ID.
     * @param int $keepLimit 保留数量.
     * @return void
     */
    protected function pruneUserHistory(int $userId, int $keepLimit): void
    {
        $keepIds = $this->getModel()
            ->where('user_id', $userId)
            ->order('last_search_time', 'desc')
            ->order('id', 'desc')
            ->limit(max(1, $keepLimit))
            ->column('id');

        if ($keepIds === []) {
            return;
        }

        $this->getModel()
            ->where('user_id', $userId)
            ->whereNotIn('id', $keepIds)
            ->delete();
    }

    /**
     * 规范化搜索关键词.
     *
     * @param string $keyword 原始关键词.
     * @return string
     */
    protected function normalizeKeyword(string $keyword): string
    {
        $keyword = mb_substr(trim(preg_replace('/\s+/u', ' ', $keyword) ?: ''), 0, 50);
        return $keyword === 'function search() { [native code] }' ? '' : $keyword;
    }
}
