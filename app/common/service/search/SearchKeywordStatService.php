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

use app\common\model\search\SearchKeywordStat;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;

/**
 * 搜索关键词统计服务.
 */
class SearchKeywordStatService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 设置对应模型.
     */
    protected function setModel(): string
    {
        return SearchKeywordStat::class;
    }

    /**
     * 获取热门搜索关键词.
     *
     * @param int $limit 返回数量.
     * @return array
     */
    public function getHotKeywords(int $limit = 10): array
    {
        $limit = max(1, min(20, $limit));
        $list = $this->getModel()
            ->field(['keyword', 'search_count', 'result_count', 'last_search_time'])
            ->order('search_count', 'desc')
            ->order('result_count', 'desc')
            ->order('last_search_time', 'desc')
            ->limit(max(100, $limit * 10))
            ->select()
            ->toArray();

        $keywords = [];
        foreach ($list as $item) {
            $keyword = $this->normalizeKeyword((string)($item['keyword'] ?? ''));
            if ($keyword === '') {
                continue;
            }

            if (!isset($keywords[$keyword])) {
                $keywords[$keyword] = [
                    'keyword' => $keyword,
                    'search' => $keyword,
                    'search_count' => 0,
                    'result_count' => 0,
                    'last_search_time' => '',
                ];
            }

            $keywords[$keyword]['search_count'] += max(0, (int)($item['search_count'] ?? 0));
            $keywords[$keyword]['result_count'] = max(
                (int)$keywords[$keyword]['result_count'],
                (int)($item['result_count'] ?? 0)
            );

            $lastSearchTime = (string)($item['last_search_time'] ?? '');
            if ($lastSearchTime > (string)$keywords[$keyword]['last_search_time']) {
                $keywords[$keyword]['last_search_time'] = $lastSearchTime;
            }
        }

        $keywords = array_values($keywords);
        usort($keywords, static function (array $left, array $right): int {
            return ((int)$right['search_count'] <=> (int)$left['search_count'])
                ?: ((int)$right['result_count'] <=> (int)$left['result_count'])
                ?: ((string)$right['last_search_time'] <=> (string)$left['last_search_time']);
        });

        return array_slice($keywords, 0, $limit);
    }

    /**
     * 记录关键词热度.
     *
     * @param string $keyword 关键词.
     * @param int $resultCount 搜索结果总数.
     * @return void
     */
    public function recordKeyword(string $keyword, int $resultCount = 0): void
    {
        $keyword = $this->normalizeKeyword($keyword);
        if ($keyword === '') {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $stat = $this->getModel()->where('keyword', $keyword)->find();
        if ($stat) {
            $this->getModel()
                ->where('id', (int)$stat['id'])
                ->inc('search_count')
                ->update();
            $this->getModel()
                ->where('id', (int)$stat['id'])
                ->update([
                    'result_count' => max(0, $resultCount),
                    'last_search_time' => $now,
                ]);
            return;
        }

        $this->create([
            'keyword' => $keyword,
            'search_count' => 1,
            'result_count' => max(0, $resultCount),
            'last_search_time' => $now,
        ]);
    }

    /**
     * 规范化关键词.
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
