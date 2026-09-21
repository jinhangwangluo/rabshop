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

namespace app\common\service\cs;

use app\common\exception\AdminException;
use app\common\job\cs\CsSegmentVectorizeJob;
use app\common\model\cs\CsKnowledgeSegment;
use app\common\service\BaseService;

/**
 * 知识库段落服务
 * 负责段落 CRUD 与文本拆段逻辑
 */
class CsKnowledgeSegmentService extends BaseService
{
    /** 单段最大字数，超出则按句界二次切分 */
    const MAX_SEGMENT_LENGTH = 800;

    protected function setModel(): string
    {
        return CsKnowledgeSegment::class;
    }

    /**
     * 获取知识的段落列表（按 sort asc, id asc 排序）
     */
    public function getListByKnowledgeId(int $knowledgeId, int $merchantId = -1): array
    {
        if (!$this->getOwnedKnowledge($knowledgeId, $merchantId)) {
            return [];
        }

        return $this->getModel()
            ->where('knowledge_id', $knowledgeId)
            ->whereNull('delete_time')
            ->order('sort asc, id asc')
            ->select()
            ->toArray();
    }

    /**
     * 按 ID 获取段落并校验其知识归属。
     *
     * @param int $segmentId 段落 ID
     * @param int $knowledgeId 知识 ID，0 表示不额外限定
     * @param int $merchantId 商户 ID，-1 表示不限
     * @return CsKnowledgeSegment|null
     */
    public function findById(int $segmentId, int $knowledgeId = 0, int $merchantId = -1): ?CsKnowledgeSegment
    {
        $query = $this->getModel()
            ->where('id', $segmentId)
            ->whereNull('delete_time');
        if ($knowledgeId > 0) {
            $query->where('knowledge_id', $knowledgeId);
        }

        $segment = $query->find();
        if (!$segment || !$this->getOwnedKnowledge((int)$segment->knowledge_id, $merchantId)) {
            return null;
        }

        return $segment;
    }

    /**
     * 分页查询段落列表，knowledge_id 为 0 时查询整个知识库下所有段落
     *
     * @param int $knowledgeBaseId  知识库ID（必传，用于限定范围）
     * @param int $knowledgeId      可选，为 0 表示不限制
     * @return array  listData 格式 {list, total, page, limit, totalPage}
     */
    public function getPaginatedList(int $knowledgeBaseId, int $knowledgeId = 0, int $merchantId = -1): array
    {
        [$page, $limit, $defaultLimit] = $this->getPagesValue();
        $page  = $page  ?: 1;
        $limit = $limit ?: $defaultLimit ?: 15;

        $query = $this->getModel()->alias('s')
            ->join('cs_knowledge k', 'k.id = s.knowledge_id')
            ->join('cs_knowledge_base b', 'b.id = k.knowledge_base_id')
            ->where('b.id', $knowledgeBaseId)
            ->whereNull('s.delete_time')
            ->whereNull('k.delete_time')
            ->whereNull('b.delete_time')
            ->when($knowledgeId > 0, fn($q) => $q->where('s.knowledge_id', $knowledgeId))
            ->when($merchantId >= 0, function ($q) use ($merchantId) {
                $q->where('k.merchant_id', $merchantId)
                    ->where('b.merchant_id', $merchantId);
            })
            ->field('s.*')
            ->order('s.sort asc, s.id asc');

        $total = (clone $query)->count();
        $list  = $query->page($page, $limit)->select()->toArray();

        return $this->listData($list, $total, [], $page, $limit)->toArray();
    }

    /**
     * 批量保存段落（先删旧段落，再插入新段落）
     * 仅在保存后由 VectorizeService 触发向量化，此方法不入队
     *
     * @param int $knowledgeId 知识ID
     * @param array $segments [['content'=>'...', 'sort'=>0], ...]
     * @return array 新建段落 id 列表
     */
    public function batchSave(int $knowledgeId, array $segments, int $merchantId = -1): array
    {
        return $this->transaction(function () use ($knowledgeId, $segments, $merchantId): array {
            if (!$this->getOwnedKnowledge($knowledgeId, $merchantId)) {
                throw new AdminException('知识不存在');
            }

            // 覆盖式保存必须与归属校验处于同一业务操作中，避免留下半套段落。
            $this->getModel()->where('knowledge_id', $knowledgeId)->delete();

            $ids = [];
            foreach ($segments as $index => $item) {
                $content = trim((string)($item['content'] ?? ''));
                if ($content === '') {
                    continue;
                }

                $row = $this->getModel()->create([
                    'knowledge_id'  => $knowledgeId,
                    'content'       => $content,
                    'sort'          => (int)($item['sort'] ?? $index),
                    'vector_status' => CsKnowledgeSegment::VECTOR_STATUS_PENDING,
                ]);
                $ids[] = (int)$row->id;
            }

            return $ids;
        });
    }

    /**
     * 更新单个段落内容（同时重置向量状态）
     */
    public function updateSegment(int $segmentId, string $content, int $sort = 0, int $merchantId = -1): bool
    {
        $segment = $this->getOwnedSegment($segmentId, $merchantId);
        if (!$segment) {
            throw new AdminException('段落不存在');
        }

        return (bool)$segment->save([
            'content'       => trim($content),
            'sort'          => $sort,
            'vector_status' => CsKnowledgeSegment::VECTOR_STATUS_PENDING,
        ]);
    }

    /**
     * 删除单个段落
     */
    public function deleteSegment(int $segmentId, int $merchantId = -1): bool
    {
        $segment = $this->getOwnedSegment($segmentId, $merchantId);
        if (!$segment) {
            throw new AdminException('段落不存在');
        }

        return (bool)$segment->delete();
    }

    /**
     * 按 id 列表批量查询，返回以 id 为 key 的关联数组
     *
     * @param int[] $ids
     * @return array  [id => row, ...]
     */
    public function getMapByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $rows = $this->getModel()
            ->whereIn('id', $ids)
            ->whereNull('delete_time')
            ->select()
            ->toArray();
        return array_column($rows, null, 'id');
    }

    /**
     * 获取某知识下所有段落的 id 列表
     */
    public function getIdsByKnowledgeId(int $knowledgeId): array
    {
        return array_map(
            'intval',
            $this->getModel()->where('knowledge_id', $knowledgeId)->whereNull('delete_time')->column('id')
        );
    }

    /**
     * 获取某知识下所有段落的 vector_status 列表
     */
    public function getVectorStatusesByKnowledgeId(int $knowledgeId): array
    {
        return array_map(
            'intval',
            $this->getModel()->where('knowledge_id', $knowledgeId)->whereNull('delete_time')->column('vector_status')
        );
    }

    /**
     * 更新单个段落的 vector_status
     */
    public function updateVectorStatus(int $segmentId, int $status): void
    {
        $this->getModel()->where('id', $segmentId)->update(['vector_status' => $status]);
    }

    /**
     * 批量加入段落向量化队列。
     *
     * @param int[] $segmentIds 段落ID数组
     * @param int $merchantId 商户ID，-1 表示不校验
     * @return array{total:int, queued_count:int, invalid_count:int, queued_ids:array, invalid_ids:array}
     */
    public function batchVectorize(array $segmentIds, int $merchantId = -1): array
    {
        $queuedIds = [];
        $invalidIds = [];

        foreach ($segmentIds as $segmentId) {
            $segmentId = (int)$segmentId;
            if ($segmentId <= 0) {
                continue;
            }

            $segment = $this->getModel()->where('id', $segmentId)->find();
            if (!$segment) {
                $invalidIds[] = $segmentId;
                continue;
            }
            if (!$this->canVectorizeSegment((int)$segment->knowledge_id, $merchantId)) {
                $invalidIds[] = $segmentId;
                continue;
            }

            $segment->vector_status = CsKnowledgeSegment::VECTOR_STATUS_PENDING;
            $segment->save();

            CsSegmentVectorizeJob::dispatch($segmentId, $merchantId);
            $queuedIds[] = $segmentId;
        }

        return [
            'total' => count($segmentIds),
            'queued_count' => count($queuedIds),
            'invalid_count' => count($invalidIds),
            'queued_ids' => $queuedIds,
            'invalid_ids' => $invalidIds,
        ];
    }

    /**
     * 判断段落归属知识是否允许当前商户入队。
     */
    private function canVectorizeSegment(int $knowledgeId, int $merchantId): bool
    {
        if ($merchantId < 0) {
            return true;
        }

        return app()->make(CsKnowledgeService::class)->getKnowledgeById($knowledgeId, $merchantId) !== null;
    }

    /**
     * 将文本按双换行拆分为段落数组
     * 单段超过 MAX_SEGMENT_LENGTH 字时，按句界（。！？\n）二次切分
     *
     * @param string $text 原始文本
     * @return array [['content'=>'...'], ...]
     */
    public function splitContent(string $text): array
    {
        $text = $this->normalizeUtf8Text($text);
        if ($text === '') {
            return [];
        }

        // 先按双换行粗拆
        $chunks = preg_split('/\n{2,}/', $text) ?: [];

        $segments = [];
        foreach ($chunks as $chunk) {
            $chunk = $this->normalizeUtf8Text($chunk);
            if ($chunk === '') {
                continue;
            }

            if (mb_strlen($chunk) <= self::MAX_SEGMENT_LENGTH) {
                $segments[] = ['content' => $chunk];
                continue;
            }

            // 超长段落按句界二次切分
            foreach ($this->splitBySentence($chunk) as $sub) {
                $segments[] = ['content' => $sub];
            }
        }

        // 填入默认 sort
        foreach ($segments as $i => &$seg) {
            $seg['sort'] = $i;
        }
        unset($seg);

        return $segments;
    }

    /**
     * 按句界切分长文本（。！？\n），保证每块不超过 MAX_SEGMENT_LENGTH
     */
    private function splitBySentence(string $text): array
    {
        // 在句尾标点后插入分隔符
        $text = $this->normalizeUtf8Text($text);
        $marked = preg_replace('/([。！？\n]+)/u', '$1|', $text) ?? $text;
        $parts = explode('|', $marked);

        $results = [];
        $current = '';
        foreach ($parts as $part) {
            $part = $this->normalizeUtf8Text($part);
            if ($part === '') {
                continue;
            }

            if (mb_strlen($current) + mb_strlen($part) <= self::MAX_SEGMENT_LENGTH) {
                $current .= $part;
            } else {
                if ($current !== '') {
                    $results[] = $this->normalizeUtf8Text($current);
                }
                $current = $part;
            }
        }

        if ($current !== '') {
            $results[] = $this->normalizeUtf8Text($current);
        }

        return $results ?: [$text];
    }

    /**
     * 清理并确保文本为合法 UTF-8。
     */
    private function normalizeUtf8Text(string $text): string
    {
        if ($text === '') {
            return '';
        }

        if (!mb_check_encoding($text, 'UTF-8')) {
            $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
            if (is_string($converted)) {
                $text = $converted;
            }
        }

        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;

        return trim($text);
    }

    /**
     * 获取并校验段落的知识归属。
     */
    private function getOwnedSegment(int $segmentId, int $merchantId): ?CsKnowledgeSegment
    {
        return $this->findById($segmentId, 0, $merchantId);
    }

    /**
     * 校验知识及其知识库归属。
     */
    private function getOwnedKnowledge(int $knowledgeId, int $merchantId): ?\app\common\model\cs\CsKnowledge
    {
        return app()->make(CsKnowledgeService::class)->getKnowledgeById($knowledgeId, $merchantId);
    }
}
