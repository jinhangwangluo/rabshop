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
use app\common\job\cs\CsSegmentVectorizeJob;
use app\common\model\cs\CsKnowledge;
use app\common\model\cs\CsKnowledgeQa;
use app\common\model\cs\CsKnowledgeSegment;
use app\common\model\cs\CsKnowledgeVector;
use app\common\service\BaseService;
use app\common\service\cs\AiModelConfigService;
use Rabbit\service\ai\embedding\EmbeddingService;
use think\facade\Cache;
use think\facade\Log;

/**
 * 知识库向量化核心服务
 * 支持三种来源：segment（段落）、qa（问答）、knowledge（整篇，兼容旧数据）
 */
class CsKnowledgeVectorizeService extends BaseService
{
    /** Redis缓存前缀（与 CsKnowledgeService 共用同一个key） */
    const CACHE_PREFIX = 'cs_vector:';
    const CACHE_TTL    = 86400;

    /** 向量来源类型常量 */
    const SOURCE_SEGMENT   = 'segment';
    const SOURCE_QA        = 'qa';
    const SOURCE_KNOWLEDGE = 'knowledge';

    protected function setModel(): string
    {
        return CsKnowledgeVector::class;
    }

    /**
     * 对某知识的所有段落和QA批量入队向量化
     * 通常由控制器的"向量化"按钮触发
     */
    public function dispatchKnowledge(int $knowledgeId, int $merchantId): void
    {
        // 重置文档向量状态为"向量化中"
        app()->make(CsKnowledgeService::class)->updateVectorStatus($knowledgeId, CsKnowledge::VECTOR_STATUS_PROCESSING);

        $segmentIds = app()->make(CsKnowledgeSegmentService::class)->getIdsByKnowledgeId($knowledgeId);
        foreach ($segmentIds as $segmentId) {
            CsSegmentVectorizeJob::dispatch($segmentId, $merchantId);
        }

        $qaIds = app()->make(CsKnowledgeQaService::class)->getIdsByKnowledgeId($knowledgeId);
        foreach ($qaIds as $qaId) {
            CsQaVectorizeJob::dispatch($qaId, $merchantId);
        }
    }

    /**
     * 向量化单个段落（同步执行，由队列 Job 调用）
     */
    public function vectorizeSegment(int $segmentId, int $merchantId): bool
    {
        /** @var CsKnowledgeSegmentService $segmentService */
        $segmentService = app()->make(CsKnowledgeSegmentService::class);

        $segmentMap = $segmentService->getMapByIds([$segmentId]);
        $segment    = $segmentMap[$segmentId] ?? null;
        if (!$segment) {
            throw new AdminException('段落不存在');
        }

        $segmentService->updateVectorStatus($segmentId, CsKnowledgeSegment::VECTOR_STATUS_PROCESSING);

        try {
            $vector = $this->embed(trim((string)$segment['content']));
            if (!$vector) {
                $segmentService->updateVectorStatus($segmentId, CsKnowledgeSegment::VECTOR_STATUS_PENDING);
                return false;
            }

            $this->saveVector(
                (int)$segment['knowledge_id'],
                self::SOURCE_SEGMENT,
                $segmentId,
                $vector,
                (string)$segment['content']
            );

            $this->updateRedisVector($merchantId, self::SOURCE_SEGMENT, $segmentId, $vector);

            $segmentService->updateVectorStatus($segmentId, CsKnowledgeSegment::VECTOR_STATUS_COMPLETED);

            $this->syncKnowledgeVectorStatus((int)$segment['knowledge_id']);
            return true;
        } catch (\Throwable $e) {
            Log::error('段落向量化失败: ' . $e->getMessage(), ['segment_id' => $segmentId]);
            $segmentService->updateVectorStatus($segmentId, CsKnowledgeSegment::VECTOR_STATUS_PENDING);
            return false;
        }
    }

    /**
     * 向量化单条 QA（question + answer 合并向量化）
     */
    public function vectorizeQa(int $qaId, int $merchantId): bool
    {
        /** @var CsKnowledgeQaService $qaService */
        $qaService = app()->make(CsKnowledgeQaService::class);

        $qaMap = $qaService->getMapByIds([$qaId]);
        $qa    = $qaMap[$qaId] ?? null;
        if (!$qa) {
            throw new AdminException('QA不存在');
        }

        $qaService->updateVectorStatus($qaId, CsKnowledgeQa::VECTOR_STATUS_PROCESSING);

        try {
            $text   = trim($qa['question'] . ' ' . $qa['answer']);
            $vector = $this->embed($text);
            if (!$vector) {
                $qaService->updateVectorStatus($qaId, CsKnowledgeQa::VECTOR_STATUS_PENDING);
                return false;
            }

            $this->saveVector(
                (int)$qa['knowledge_id'],
                self::SOURCE_QA,
                $qaId,
                $vector,
                $text
            );

            $this->updateRedisVector($merchantId, self::SOURCE_QA, $qaId, $vector);

            $qaService->updateVectorStatus($qaId, CsKnowledgeQa::VECTOR_STATUS_COMPLETED);

            $this->syncKnowledgeVectorStatus((int)$qa['knowledge_id']);
            return true;
        } catch (\Throwable $e) {
            Log::error('QA向量化失败: ' . $e->getMessage(), ['qa_id' => $qaId]);
            $qaService->updateVectorStatus($qaId, CsKnowledgeQa::VECTOR_STATUS_PENDING);
            return false;
        }
    }

    /**
     * 聚合更新文档级向量状态
     * 全部段落+QA已完成 → completed；有处理中 → processing；否则 → pending
     */
    public function syncKnowledgeVectorStatus(int $knowledgeId): void
    {
        $segmentStatuses = app()->make(CsKnowledgeSegmentService::class)->getVectorStatusesByKnowledgeId($knowledgeId);
        $qaStatuses      = app()->make(CsKnowledgeQaService::class)->getVectorStatusesByKnowledgeId($knowledgeId);

        $all = array_merge($segmentStatuses, $qaStatuses);

        if (empty($all)) {
            return;
        }

        // 状态值已在 Service 中 intval，严格比较安全
        if (in_array(CsKnowledgeSegment::VECTOR_STATUS_PROCESSING, $all, true)) {
            $status = CsKnowledge::VECTOR_STATUS_PROCESSING;
        } elseif (count(array_unique($all)) === 1 && reset($all) === CsKnowledgeSegment::VECTOR_STATUS_COMPLETED) {
            $status = CsKnowledge::VECTOR_STATUS_COMPLETED;
        } else {
            $status = CsKnowledge::VECTOR_STATUS_PENDING;
        }

        app()->make(CsKnowledgeService::class)->updateVectorStatus($knowledgeId, $status);
    }

    /**
     * 调用 Embedding API 生成向量
     */
    private function embed(string $text): ?array
    {
        if ($text === '') {
            return null;
        }

        /** @var EmbeddingService $embeddingService */
        $embeddingService = app()->make(EmbeddingService::class);

        /** @var AiModelConfigService $aiModelConfigService */
        $aiModelConfigService = app()->make(AiModelConfigService::class);
        $runtime = $aiModelConfigService->resolveRuntime(AiModelConfigService::TYPE_EMBEDDING);
        if (!is_array($runtime) || !isset($runtime['provider'], $runtime['options'])) {
            throw new AdminException('请先在AI模型配置中设置向量默认模型');
        }

        return $embeddingService->embed($text, (string)$runtime['provider'], (array)$runtime['options']) ?: null;
    }

    /**
     * 保存或更新向量记录（按 source_type+source_id 唯一）
     */
    private function saveVector(
        int $knowledgeId,
        string $sourceType,
        int $sourceId,
        array $vector,
        string $content
    ): void {
        $vectorModel = $this->getModel()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->find();

        if (!$vectorModel) {
            $vectorModel = $this->getModel();
            $vectorModel->knowledge_id = $knowledgeId;
            $vectorModel->source_type  = $sourceType;
            $vectorModel->source_id    = $sourceId;
        }

        /** @var EmbeddingService $embeddingService */
        $embeddingService = app()->make(EmbeddingService::class);
        /** @var AiModelConfigService $aiModelConfigService */
        $aiModelConfigService = app()->make(AiModelConfigService::class);
        $runtime = $aiModelConfigService->resolveRuntime(AiModelConfigService::TYPE_EMBEDDING);

        $vectorModel->vector      = $vector;
        $vectorModel->content_md5 = md5($content);
        $vectorModel->model       = $embeddingService->modelTag(
            (string)$runtime['provider'],
            (array)$runtime['options']
        );
        $vectorModel->save();
    }

    /**
     * 将向量写入 Redis hash，field 为 source_type:source_id
     */
    private function updateRedisVector(int $merchantId, string $sourceType, int $sourceId, array $vector): void
    {
        $cacheKey = self::CACHE_PREFIX . ($merchantId > 0 ? $merchantId : 'platform');
        $field    = $sourceType . ':' . $sourceId;
        Cache::hSet($cacheKey, $field, json_encode($vector, JSON_UNESCAPED_UNICODE));
        Cache::expire($cacheKey, self::CACHE_TTL);
    }
}
