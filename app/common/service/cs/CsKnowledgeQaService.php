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
use app\common\job\cs\CsQaVectorizeJob;
use app\common\model\cs\CsKnowledgeSegment;
use app\common\model\cs\CsKnowledgeQa;
use app\common\service\BaseService;

/**
 * 知识库问答服务
 * 负责 QA 的 CRUD 与批量保存
 */
class CsKnowledgeQaService extends BaseService
{
    protected function setModel(): string
    {
        return CsKnowledgeQa::class;
    }

    /**
     * 获取某段落的 QA 列表
     */
    public function getListBySegmentId(int $segmentId, int $merchantId = -1): array
    {
        $segment = $this->getOwnedSegment($segmentId, 0, $merchantId);
        if (!$segment) {
            return [];
        }

        return $this->getModel()
            ->where('segment_id', $segmentId)
            ->where('knowledge_id', (int)$segment->knowledge_id)
            ->whereNull('delete_time')
            ->order('sort asc, id asc')
            ->select()
            ->toArray();
    }

    /**
     * 获取某知识的全部 QA（含段落ID）
     */
    public function getListByKnowledgeId(int $knowledgeId, int $merchantId = -1): array
    {
        if (!$this->getOwnedKnowledge($knowledgeId, $merchantId)) {
            return [];
        }

        return $this->getModel()
            ->where('knowledge_id', $knowledgeId)
            ->whereNull('delete_time')
            ->order('segment_id asc, sort asc, id asc')
            ->select()
            ->toArray();
    }

    /**
     * 分页查询 QA 列表
     *
     * @param int $knowledgeBaseId  知识库ID（必传，用于限定范围）
     * @param int $knowledgeId      可选，为 0 表示不限
     * @param int $segmentId        可选，为 0 表示不限
     * @return array  listData 格式 {list, total, page, limit, totalPage}
     */
    public function getPaginatedList(
        int $knowledgeBaseId,
        int $knowledgeId = 0,
        int $segmentId = 0,
        int $merchantId = -1
    ): array
    {
        [$page, $limit, $defaultLimit] = $this->getPagesValue();
        $page  = $page  ?: 1;
        $limit = $limit ?: $defaultLimit ?: 15;

        $query = $this->getModel()->alias('q')
            ->join('cs_knowledge k', 'k.id = q.knowledge_id')
            ->join('cs_knowledge_base b', 'b.id = k.knowledge_base_id')
            ->join('cs_knowledge_segment s', 's.id = q.segment_id AND s.knowledge_id = q.knowledge_id')
            ->where('b.id', $knowledgeBaseId)
            ->whereNull('q.delete_time')
            ->whereNull('k.delete_time')
            ->whereNull('b.delete_time')
            ->whereNull('s.delete_time')
            ->when($knowledgeId > 0, fn($q) => $q->where('q.knowledge_id', $knowledgeId))
            ->when($segmentId > 0, fn($q) => $q->where('q.segment_id', $segmentId))
            ->when($merchantId >= 0, function ($q) use ($merchantId) {
                $q->where('k.merchant_id', $merchantId)
                    ->where('b.merchant_id', $merchantId);
            })
            ->field('q.*')
            ->order('q.segment_id asc, q.sort asc, q.id asc');

        $total = (clone $query)->count();
        $list  = $query->page($page, $limit)->select()->toArray();

        return $this->listData($list, $total, [], $page, $limit)->toArray();
    }

    /**
     * 批量保存某段落的 QA（先删旧再插新）
     *
     * @param int $segmentId  段落ID
     * @param int $knowledgeId 知识ID
     * @param array $qaList [['question'=>'...','answer'=>'...','sort'=>0], ...]
     * @return array 新建 QA id 列表
     */
    public function batchSave(int $segmentId, int $knowledgeId, array $qaList, int $merchantId = -1): array
    {
        return $this->transaction(function () use ($segmentId, $knowledgeId, $qaList, $merchantId): array {
            if (!$this->getOwnedSegment($segmentId, $knowledgeId, $merchantId)) {
                throw new AdminException('段落或知识不存在');
            }

            // 覆盖式保存只作用于已验证的 segment + knowledge 关系。
            $this->getModel()
                ->where('segment_id', $segmentId)
                ->where('knowledge_id', $knowledgeId)
                ->delete();

            $ids = [];
            foreach ($qaList as $index => $item) {
                $question = trim((string)($item['question'] ?? ''));
                $answer   = trim((string)($item['answer'] ?? ''));
                if ($question === '' || $answer === '') {
                    continue;
                }

                $row = $this->getModel()->create([
                    'knowledge_id'  => $knowledgeId,
                    'segment_id'    => $segmentId,
                    'question'      => $question,
                    'answer'        => $answer,
                    'sort'          => (int)($item['sort'] ?? $index),
                    'vector_status' => CsKnowledgeQa::VECTOR_STATUS_PENDING,
                ]);
                $ids[] = (int)$row->id;
            }

            return $ids;
        });
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
     * 获取某知识下所有 QA 的 id 列表
     */
    public function getIdsByKnowledgeId(int $knowledgeId): array
    {
        return array_map(
            'intval',
            $this->getModel()->where('knowledge_id', $knowledgeId)->whereNull('delete_time')->column('id')
        );
    }

    /**
     * 获取某知识下所有 QA 的 vector_status 列表
     */
    public function getVectorStatusesByKnowledgeId(int $knowledgeId): array
    {
        return array_map(
            'intval',
            $this->getModel()->where('knowledge_id', $knowledgeId)->whereNull('delete_time')->column('vector_status')
        );
    }

    /**
     * 更新单条 QA 的 vector_status
     */
    public function updateVectorStatus(int $qaId, int $status): void
    {
        $this->getModel()->where('id', $qaId)->update(['vector_status' => $status]);
    }

    /**
     * 批量加入 QA 向量化队列。
     *
     * @param int[] $qaIds QA ID数组
     * @param int $merchantId 商户ID，-1 表示不校验
     * @return array{total:int, queued_count:int, invalid_count:int, queued_ids:array, invalid_ids:array}
     */
    public function batchVectorize(array $qaIds, int $merchantId = -1): array
    {
        $queuedIds = [];
        $invalidIds = [];

        foreach ($qaIds as $qaId) {
            $qaId = (int)$qaId;
            if ($qaId <= 0) {
                continue;
            }

            $qa = $this->getModel()
                ->where('id', $qaId)
                ->whereNull('delete_time')
                ->find();
            if (!$qa) {
                $invalidIds[] = $qaId;
                continue;
            }
            if (!$this->canVectorizeQa((int)$qa->knowledge_id, $merchantId)) {
                $invalidIds[] = $qaId;
                continue;
            }

            $qa->vector_status = CsKnowledgeQa::VECTOR_STATUS_PENDING;
            $qa->save();

            CsQaVectorizeJob::dispatch($qaId, $merchantId);
            $queuedIds[] = $qaId;
        }

        return [
            'total' => count($qaIds),
            'queued_count' => count($queuedIds),
            'invalid_count' => count($invalidIds),
            'queued_ids' => $queuedIds,
            'invalid_ids' => $invalidIds,
        ];
    }

    /**
     * 判断 QA 归属知识是否允许当前商户入队。
     */
    private function canVectorizeQa(int $knowledgeId, int $merchantId): bool
    {
        if ($merchantId < 0) {
            return true;
        }

        return app()->make(CsKnowledgeService::class)->getKnowledgeById($knowledgeId, $merchantId) !== null;
    }

    /**
     * 新增单条 QA
     */
    public function createQa(
        int $segmentId,
        int $knowledgeId,
        string $question,
        string $answer,
        int $sort = 0,
        int $merchantId = -1
    ): int
    {
        if (!$this->getOwnedSegment($segmentId, $knowledgeId, $merchantId)) {
            throw new AdminException('段落或知识不存在');
        }

        $row = $this->getModel()->create([
            'knowledge_id'  => $knowledgeId,
            'segment_id'    => $segmentId,
            'question'      => trim($question),
            'answer'        => trim($answer),
            'sort'          => $sort,
            'vector_status' => CsKnowledgeQa::VECTOR_STATUS_PENDING,
        ]);

        return (int)$row->id;
    }

    /**
     * 更新单条 QA（同时重置向量状态）
     */
    public function updateQa(
        int $qaId,
        string $question,
        string $answer,
        int $sort = 0,
        int $merchantId = -1
    ): bool
    {
        $qa = $this->getOwnedQa($qaId, $merchantId);
        if (!$qa) {
            throw new AdminException('QA不存在');
        }

        return (bool)$qa->save([
            'question'      => trim($question),
            'answer'        => trim($answer),
            'sort'          => $sort,
            'vector_status' => CsKnowledgeQa::VECTOR_STATUS_PENDING,
        ]);
    }

    /**
     * 删除单条 QA
     */
    public function deleteQa(int $qaId, int $merchantId = -1): bool
    {
        $qa = $this->getOwnedQa($qaId, $merchantId);
        if (!$qa) {
            throw new AdminException('QA不存在');
        }

        return (bool)$qa->delete();
    }

    /**
     * 获取并校验 QA 的知识与段落归属。
     */
    private function getOwnedQa(int $qaId, int $merchantId): ?CsKnowledgeQa
    {
        $qa = $this->getModel()
            ->where('id', $qaId)
            ->whereNull('delete_time')
            ->find();
        if (!$qa || !$this->getOwnedSegment((int)$qa->segment_id, (int)$qa->knowledge_id, $merchantId)) {
            return null;
        }

        return $qa;
    }

    /**
     * 获取并校验段落属于指定知识及当前商户。
     */
    private function getOwnedSegment(int $segmentId, int $knowledgeId, int $merchantId): ?CsKnowledgeSegment
    {
        return app()->make(CsKnowledgeSegmentService::class)
            ->findById($segmentId, $knowledgeId, $merchantId);
    }

    /**
     * 校验知识及其知识库归属。
     */
    private function getOwnedKnowledge(int $knowledgeId, int $merchantId): ?\app\common\model\cs\CsKnowledge
    {
        return app()->make(CsKnowledgeService::class)->getKnowledgeById($knowledgeId, $merchantId);
    }
}
