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
use app\common\job\cs\CsKnowledgeVectorizeJob;
use app\common\model\cs\CsKnowledge;
use app\common\model\cs\CsKnowledgeVector;
use app\common\service\BaseService;
use app\common\service\cs\CsKnowledgeQaService;
use app\common\service\cs\CsKnowledgeSegmentService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Rabbit\service\ai\embedding\EmbeddingService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Cache;
use think\facade\Log;
use ZipArchive;

/**
 * 知识库服务
 */
class CsKnowledgeService extends BaseService
{
    /**
     * Redis缓存前缀
     */
    const CACHE_PREFIX = 'cs_vector:';

    /**
     * 缓存TTL(秒)
     */
    const CACHE_TTL = 86400;

    /**
     * 设置模型
     * @return string
     */
    protected function setModel(): string
    {
        return CsKnowledge::class;
    }

    /**
     * 获取知识条目
     * @param int $knowledgeId
     * @param int $merchantId
     * @return CsKnowledge|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getKnowledgeById(int $knowledgeId, int $merchantId = -1): ?CsKnowledge
    {
        $query = $this->getModel()
            ->where('id', $knowledgeId)
            ->whereNull('delete_time');
        if ($merchantId >= 0) {
            $query->where('merchant_id', $merchantId);
        }

        $knowledge = $query->find();
        if (!$knowledge) {
            return null;
        }

        $baseService = app()->make(CsKnowledgeBaseService::class);
        $baseService->setMerchantId($merchantId);
        if (!$baseService->findById((int)$knowledge->knowledge_base_id)) {
            return null;
        }

        return $knowledge;
    }

    /**
     * 获取知识列表
     * @param int $merchantId 商户ID
     * @param int|string $knowledgeBaseId 知识库ID
     * @param string $keyword 关键词
     * @param int|string $vectorStatus 向量化状态
     * @param int|string $status 启用状态
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getKnowledgeList(
        int $merchantId = 0,
        int|string $knowledgeBaseId = 0,
        string $keyword = '',
        int|string $vectorStatus = -1,
        int|string $status = -1,
        int $page = 1,
        int $limit = 20
    ): array {
        $query = $this->getModel()
            ->where('merchant_id', $merchantId)
            ->whereNull('delete_time');

        if ((int)$knowledgeBaseId > 0) {
            $query->where('knowledge_base_id', (int)$knowledgeBaseId);
        }

        if ($keyword !== '') {
            $query->where('title', 'like', '%' . $keyword . '%');
        }

        if ($vectorStatus !== '' && (int)$vectorStatus >= 0) {
            $query->where('vector_status', (int)$vectorStatus);
        }

        if ($status !== '' && (int)$status >= 0) {
            $query->where('status', (int)$status);
        }

        $total = $query->count();
        $list = $query->order('sort desc, id desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    /**
     * 创建知识条目
     * @param int $knowledgeBaseId 知识库ID
     * @param int $merchantId 商户ID
     * @param string $title 标题
     * @param string $content 内容
     * @param string $source 来源
     * @param string $sourceUrl 来源URL
     * @return mixed
     */
    public function createKnowledge(
        int $knowledgeBaseId,
        int $merchantId,
        string $title,
        string $content,
        string $source = CsKnowledge::SOURCE_MANUAL,
        string $sourceUrl = ''
    )
    {
        $baseService = app()->make(CsKnowledgeBaseService::class);
        $baseService->setMerchantId($merchantId);
        if (!$baseService->findById($knowledgeBaseId)) {
            throw new AdminException('知识库不存在');
        }

        return $this->getModel()->create([
            'knowledge_base_id' => $knowledgeBaseId,
            'merchant_id' => $merchantId,
            'title' => $title,
            'content' => $content,
            'source' => $source,
            'source_url' => $sourceUrl,
            'vector_status' => CsKnowledge::VECTOR_STATUS_PENDING,
            'status' => 1,
            'sort' => 0,
            'views' => 0,
        ]);
    }

    /**
     * 更新知识条目
     * @param int $knowledgeId
     * @param array $data
     * @param int $merchantId
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function updateKnowledge(int $knowledgeId, array $data, int $merchantId = -1): bool
    {
        $knowledge = $this->getKnowledgeById($knowledgeId, $merchantId);
        if (!$knowledge) {
            throw new AdminException('知识不存在');
        }

        if (isset($data['title']) || isset($data['content'])) {
            $data['vector_status'] = CsKnowledge::VECTOR_STATUS_PENDING;
            $this->deleteRedisVector((int)$knowledge->merchant_id, (int)$knowledge->id);
        }

        return (bool)$knowledge->save($data);
    }

    /**
     * 删除知识条目
     * @param int $knowledgeId
     * @param int $merchantId
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function deleteKnowledge(int $knowledgeId, int $merchantId = -1): bool
    {
        $knowledge = $this->getKnowledgeById($knowledgeId, $merchantId);
        if (!$knowledge) {
            throw new AdminException('知识不存在');
        }

        $this->deleteRedisVector((int)$knowledge->merchant_id, (int)$knowledge->id);
        $this->getVectorModel()->where('knowledge_id', $knowledgeId)->delete();

        return (bool)$knowledge->delete();
    }

    /**
     * 更新知识的 vector_status
     */
    public function updateVectorStatus(int $knowledgeId, int $status): void
    {
        $this->getModel()->where('id', $knowledgeId)->update(['vector_status' => $status]);
    }

    /**
     * 批量删除知识条目
     * @param array $knowledgeIds
     * @param int $merchantId
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function batchDeleteKnowledge(array $knowledgeIds, int $merchantId = -1): bool
    {
        foreach ($knowledgeIds as $knowledgeId) {
            $this->deleteKnowledge((int)$knowledgeId, $merchantId);
        }

        return true;
    }

    /**
     * 向量化知识条目
     * @param int $knowledgeId 知识ID
     * @param int $merchantId 商户ID（-1表示不校验）
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function vectorize(int $knowledgeId, int $merchantId = -1): bool
    {
        $knowledge = $this->getKnowledgeById($knowledgeId, $merchantId);
        if (!$knowledge) {
            throw new AdminException('知识条目不存在');
        }

        $knowledge->vector_status = CsKnowledge::VECTOR_STATUS_PROCESSING;
        $knowledge->save();

        try {
            /** @var EmbeddingService $embeddingService */
            $embeddingService = app()->make(EmbeddingService::class);
            [$provider, $options] = $this->buildEmbeddingRuntime();
            $text = trim($knowledge->title . ' ' . $knowledge->content);
            $vector = $embeddingService->embed($text, $provider, $options);

            if (!$vector) {
                $knowledge->vector_status = CsKnowledge::VECTOR_STATUS_PENDING;
                $knowledge->save();
                return false;
            }

            $vectorModel = $this->getVectorModel()->where('knowledge_id', $knowledgeId)->find();
            if (!$vectorModel) {
                $vectorModel = $this->getVectorModel();
                $vectorModel->knowledge_id = $knowledgeId;
            }

            $vectorModel->vector = $vector;
            $vectorModel->content_md5 = md5($text);
            $vectorModel->model = $embeddingService->modelTag($provider, $options);
            $vectorModel->save();

            $this->updateRedisVector((int)$knowledge->merchant_id, $knowledgeId, $vector);

            $knowledge->vector_status = CsKnowledge::VECTOR_STATUS_COMPLETED;
            $knowledge->last_vector_time = date('Y-m-d H:i:s');
            $knowledge->save();

            return true;
        } catch (\Throwable $exception) {
            Log::error('向量化失败: ' . $exception->getMessage(), [
                'knowledge_id' => $knowledgeId,
                'merchant_id' => $merchantId,
            ]);
            $knowledge->vector_status = CsKnowledge::VECTOR_STATUS_PENDING;
            $knowledge->save();

            return false;
        }
    }

    /**
     * 批量向量化入队
     * @param array $knowledgeIds 知识ID数组
     * @param int $merchantId 商户ID（-1表示不校验）
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function batchVectorize(array $knowledgeIds, int $merchantId = -1): array
    {
        $queuedIds = [];
        $invalidIds = [];

        foreach ($knowledgeIds as $knowledgeId) {
            $knowledgeId = (int)$knowledgeId;
            if ($knowledgeId <= 0) {
                continue;
            }

            $knowledge = $this->getKnowledgeById($knowledgeId, $merchantId);
            if (!$knowledge) {
                $invalidIds[] = $knowledgeId;
                continue;
            }

            $knowledge->vector_status = CsKnowledge::VECTOR_STATUS_PENDING;
            $knowledge->save();

            CsKnowledgeVectorizeJob::dispatch($knowledgeId, (int)$knowledge->merchant_id);
            $queuedIds[] = $knowledgeId;
        }

        return [
            'total' => count($knowledgeIds),
            'queued_count' => count($queuedIds),
            'invalid_count' => count($invalidIds),
            'queued_ids' => $queuedIds,
            'invalid_ids' => $invalidIds,
        ];
    }

    /**
     * 按知识库检索（指定 knowledge_base_id 过滤）
     * 测试回复场景专用，仅检索指定知识库内的内容
     *
     * @param string $query          查询文本
     * @param int $merchantId        商户ID
     * @param int $knowledgeBaseId   知识库ID
     * @param int $limit             返回条数
     */
    public function searchByBase(string $query, int $merchantId, int $knowledgeBaseId, int $limit = 5): array
    {
        $results = $this->search($query, $merchantId, $limit * 2);

        if ($results === [] || $knowledgeBaseId <= 0) {
            return $results;
        }

        // 过滤属于该知识库的文档命中
        $baseKnowledgeIds = $this->getModel()
            ->where('knowledge_base_id', $knowledgeBaseId)
            ->where('merchant_id', $merchantId)
            ->whereNull('delete_time')
            ->column('id');

        if ($baseKnowledgeIds === []) {
            return [];
        }

        $filtered = array_values(array_filter(
            $results,
            fn($hit) => in_array((int)$hit['knowledge_id'], $baseKnowledgeIds, true)
        ));

        return array_slice($filtered, 0, $limit);
    }

    /**
     * 按知识库批量统计文档数量。
     *
     * @param int[] $knowledgeBaseIds 知识库ID列表
     * @param int $merchantId 商户ID，-1 表示不限商户
     * @return array<int, int> key 为 knowledge_base_id，value 为文档数量
     */
    public function countByBaseIds(array $knowledgeBaseIds, int $merchantId = -1): array
    {
        $baseIds = array_values(array_unique(array_filter(
            array_map('intval', $knowledgeBaseIds),
            static fn(int $id): bool => $id > 0
        )));

        if ($baseIds === []) {
            return [];
        }

        $query = $this->getModel()
            ->whereIn('knowledge_base_id', $baseIds)
            ->where('delete_time', null);

        if ($merchantId >= 0) {
            $query->where('merchant_id', $merchantId);
        }

        $rows = $query->group('knowledge_base_id')->column('COUNT(*) AS cnt', 'knowledge_base_id');
        $counts = [];
        foreach ($rows as $baseId => $count) {
            $counts[(int)$baseId] = (int)$count;
        }

        return $counts;
    }

    /**
     * 统计指定知识库的文档数量。
     *
     * @param int $knowledgeBaseId 知识库ID
     * @param int $merchantId 商户ID，-1 表示不限商户
     * @return int
     */
    public function countByBaseId(int $knowledgeBaseId, int $merchantId = -1): int
    {
        $counts = $this->countByBaseIds([$knowledgeBaseId], $merchantId);

        return (int)($counts[$knowledgeBaseId] ?? 0);
    }

    /**
     * 多知识库检索（各库使用自身的 threshold / search_limit 配置）
     *
     * 流程：
     * 1. 读取各库的 threshold / search_limit 配置
     * 2. 用各库自己的 knowledgeIds 过滤全量向量命中
     * 3. 各库独立按阈值过滤、按 search_limit 截取
     * 4. 合并所有库的结果，按相似度全局降序，取 $globalLimit 条返回
     *
     * @param string $query        查询文本
     * @param int    $merchantId   商户ID
     * @param int[]  $baseIds      知识库ID数组
     * @param int    $globalLimit  最终返回条数上限（跨库合并后截取）
     * @return array
     */
    public function searchByBases(string $query, int $merchantId, array $baseIds, int $globalLimit = 5): array
    {
        if ($baseIds === [] || $query === '') {
            return [];
        }

        // 批量读取知识库配置；column() 不支持 null 字段参数，改用字段裁剪查询后按 id 重组。
        $baseRows = app()->make(\app\common\model\cs\CsKnowledgeBase::class)
            ->whereIn('id', $baseIds)
            ->where('merchant_id', $merchantId)
            ->where('status', 1)
            ->where('delete_time', null)
            ->field('id, threshold, search_limit')
            ->select()
            ->toArray();
        $bases = array_column($baseRows, null, 'id');

        if ($bases === []) {
            return [];
        }

        // 各库及其文档ID
        $baseKnowledgeMap = [];
        foreach (array_keys($bases) as $baseId) {
            $ids = $this->getModel()
                ->where('knowledge_base_id', $baseId)
                ->where('merchant_id', $merchantId)
                ->where('status', 1)
                ->whereNull('delete_time')
                ->column('id');
            if ($ids !== []) {
                $baseKnowledgeMap[$baseId] = array_map('intval', $ids);
            }
        }

        if ($baseKnowledgeMap === []) {
            return [];
        }

        // 全量搜索（阈值设 0，条数取各库 limit 之和，确保候选池够大）
        $poolLimit = array_sum(array_map(
            fn($baseId) => max(1, (int)($bases[$baseId]['search_limit'] ?? 5)),
            array_keys($bases)
        ));
        $allHits = $this->search($query, $merchantId, $poolLimit * 2, 0.0);

        if ($allHits === []) {
            return [];
        }

        // 各库独立过滤，按各自阈值和 search_limit 截取
        $merged = [];
        foreach ($baseKnowledgeMap as $baseId => $knowledgeIds) {
            $threshold   = (float)($bases[$baseId]['threshold'] ?? 0.6);
            $searchLimit = max(1, (int)($bases[$baseId]['search_limit'] ?? 5));

            $hits = array_values(array_filter(
                $allHits,
                fn($hit) => in_array((int)$hit['knowledge_id'], $knowledgeIds, true)
                    && (float)$hit['similarity'] >= $threshold
            ));

            // 按相似度降序，取该库的 limit
            usort($hits, fn($a, $b) => $b['similarity'] <=> $a['similarity']);
            $hits = array_slice($hits, 0, $searchLimit);

            foreach ($hits as $hit) {
                $merged[] = $hit;
            }
        }

        if ($merged === []) {
            return [];
        }

        // 全局合并：去重（同 field 只保留最高分）、降序、截取
        $deduped = [];
        foreach ($merged as $hit) {
            $key = $hit['source_type'] . ':' . $hit['source_id'];
            if (!isset($deduped[$key]) || $hit['similarity'] > $deduped[$key]['similarity']) {
                $deduped[$key] = $hit;
            }
        }

        usort($deduped, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        return array_values(array_slice($deduped, 0, $globalLimit));
    }

    /**
     * 按客服范围检索，商户私有知识无命中时回退平台公共知识。
     *
     * @param string $query 查询文本
     * @param int $merchantId 商户 ID，0 表示平台
     * @param int $limit 返回条数
     * @param float $threshold 相似度阈值
     * @return array
     */
    public function searchWithPlatformFallback(
        string $query,
        int $merchantId = 0,
        int $limit = 5,
        float $threshold = 0.6
    ): array {
        $results = $this->search($query, $merchantId, $limit, $threshold);
        if ($results !== [] || $merchantId <= 0) {
            return $results;
        }

        return $this->search($query, 0, $limit, $threshold);
    }

    /**
     * 向量检索
     * 命中 qa 时优先返回其 answer；命中 segment 时返回段落内容；兼容旧整篇向量
     *
     * @param string $query     查询文本
     * @param int $merchantId   商户ID（0=平台）
     * @param int $limit        返回条数
     * @param float $threshold  相似度阈值
     */
    public function search(string $query, int $merchantId = 0, int $limit = 5, float $threshold = 0.6): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $vectorSets = $this->loadSearchVectorSets($merchantId);
        if ($vectorSets === []) {
            return [];
        }

        /** @var EmbeddingService $embeddingService */
        $embeddingService = app()->make(EmbeddingService::class);
        [$provider, $options] = $this->buildEmbeddingRuntime();
        $queryVector = $embeddingService->embed($query, $provider, $options);
        if (!$queryVector) {
            return [];
        }

        // $scores: field => similarity（field = "source_type:source_id" 或旧格式数字knowledgeId）
        $scores = [];
        foreach ($vectorSets as $vectorSet) {
            foreach ((array)($vectorSet['vectors'] ?? []) as $field => $vector) {
                if (!is_array($vector)) {
                    continue;
                }
                $similarity = $this->cosineSimilarity($queryVector, $vector);
                if ($similarity < $threshold) {
                    continue;
                }
                if (!isset($scores[$field]) || $similarity > $scores[$field]) {
                    $scores[$field] = $similarity;
                }
            }
        }

        if ($scores === []) {
            return [];
        }

        arsort($scores);
        $topFields = array_slice(array_keys($scores), 0, $limit);

        // 解析 field → [sourceType, sourceId]，兼容旧格式（纯数字 = knowledge）
        $sources = [];
        foreach ($topFields as $field) {
            if (str_contains((string)$field, ':')) {
                [$type, $id] = explode(':', (string)$field, 2);
                $sources[$field] = ['type' => $type, 'id' => (int)$id];
            } else {
                $sources[$field] = ['type' => 'knowledge', 'id' => (int)$field];
            }
        }

        // 预加载段落、QA 数据，再从中收集真实 knowledge_id
        $segmentIds = array_values(array_map(fn($s) => $s['id'], array_filter($sources, fn($s) => $s['type'] === 'segment')));
        $qaIds      = array_values(array_map(fn($s) => $s['id'], array_filter($sources, fn($s) => $s['type'] === 'qa')));
        // source_type=knowledge 或旧格式：直接用 source_id 作为 knowledge_id
        $directKnowledgeIds = array_values(array_map(
            fn($s) => $s['id'],
            array_filter($sources, fn($s) => $s['type'] === 'knowledge')
        ));

        $segmentMap = app()->make(CsKnowledgeSegmentService::class)->getMapByIds($segmentIds);

        $qaMap = app()->make(CsKnowledgeQaService::class)->getMapByIds($qaIds);

        // 收集所有需要加载的 knowledge_id（segment/qa 通过 knowledge_id 字段关联）
        $knowledgeIds = array_unique(array_merge(
            $directKnowledgeIds,
            array_map(fn($row) => (int)$row['knowledge_id'], array_values($segmentMap)),
            array_map(fn($row) => (int)$row['knowledge_id'], array_values($qaMap))
        ));

        $knowledgeMap = $knowledgeIds
            ? $this->getModel()
                ->whereIn('id', $knowledgeIds)
                ->where('status', 1)
                ->whereNull('delete_time')
                ->select()
                ->toArray()
            : [];
        $knowledgeMap = array_column($knowledgeMap, null, 'id');

        $results = [];
        foreach ($topFields as $field) {
            $src        = $sources[$field];
            $sourceType = $src['type'];
            $sourceId   = $src['id'];
            $similarity = $scores[$field];

            if ($sourceType === 'qa') {
                $qa = $qaMap[$sourceId] ?? null;
                if (!$qa) {
                    continue;
                }
                $knowledge = $knowledgeMap[(int)($qa['knowledge_id'] ?? 0)] ?? null;
                if (!$knowledge) {
                    continue;
                }
                // QA命中：优先返回答案
                $results[] = [
                    'knowledge_id' => (int)$knowledge['id'],
                    'title'        => (string)$knowledge['title'],
                    'content'      => (string)$qa['answer'],  // 直接返回预设答案
                    'source_type'  => 'qa',
                    'source_id'    => $sourceId,
                    'question'     => (string)$qa['question'],
                    'similarity'   => $similarity,
                    'merchant_id'  => (int)$knowledge['merchant_id'],
                ];
            } elseif ($sourceType === 'segment') {
                $segment = $segmentMap[$sourceId] ?? null;
                if (!$segment) {
                    continue;
                }
                $knowledge = $knowledgeMap[(int)($segment['knowledge_id'] ?? 0)] ?? null;
                if (!$knowledge) {
                    continue;
                }
                $results[] = [
                    'knowledge_id' => (int)$knowledge['id'],
                    'title'        => (string)$knowledge['title'],
                    'content'      => (string)$segment['content'],
                    'source_type'  => 'segment',
                    'source_id'    => $sourceId,
                    'similarity'   => $similarity,
                    'merchant_id'  => (int)$knowledge['merchant_id'],
                ];
            } else {
                // 兼容旧整篇向量（source_type=knowledge 或纯数字field）
                $knowledge = $knowledgeMap[$sourceId] ?? null;
                if (!$knowledge) {
                    continue;
                }
                $results[] = [
                    'knowledge_id' => $sourceId,
                    'title'        => (string)$knowledge['title'],
                    'content'      => (string)$knowledge['content'],
                    'source_type'  => 'knowledge',
                    'source_id'    => $sourceId,
                    'similarity'   => $similarity,
                    'merchant_id'  => (int)$knowledge['merchant_id'],
                ];
            }
        }

        return $results;
    }

    /**
     * 从文件导入知识
     * @param int $knowledgeBaseId 知识库ID
     * @param int $merchantId
     * @param string $originalName
     * @param string $filePath
     * @param string $customTitle
     * @return CsKnowledge
     */
    public function importByFile(
        int $knowledgeBaseId,
        int $merchantId,
        string $originalName,
        string $filePath,
        string $customTitle = ''
    ): CsKnowledge {
        if ($knowledgeBaseId <= 0) {
            throw new AdminException('知识库不能为空');
        }
        if ($originalName === '' || !is_file($filePath)) {
            throw new AdminException('文件不存在或无效');
        }

        $extension = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
        $allowExtensions = ['txt', 'md', 'docx', 'pdf', 'xlsx', 'xls', 'csv'];
        if (!in_array($extension, $allowExtensions, true)) {
            throw new AdminException('仅支持 txt/md/docx/pdf/xlsx/xls/csv 文件');
        }

        $content = $this->extractFileContent($filePath, $extension);
        if ($content === '') {
            throw new AdminException('文件内容为空或解析失败');
        }

        $title = trim($customTitle);
        if ($title === '') {
            $title = trim((string)pathinfo($originalName, PATHINFO_FILENAME));
        }
        if ($title === '') {
            $title = '知识导入_' . date('YmdHis');
        }

        return $this->createKnowledge(
            $knowledgeBaseId,
            $merchantId,
            $title,
            $content,
            CsKnowledge::SOURCE_FILE,
            ''
        );
    }

    /**
     * 从URL采集导入知识
     * @param int $knowledgeBaseId 知识库ID
     * @param int $merchantId
     * @param string $url
     * @param string $customTitle
     * @return CsKnowledge
     */
    public function importByUrl(int $knowledgeBaseId, int $merchantId, string $url, string $customTitle = ''): CsKnowledge
    {
        if ($knowledgeBaseId <= 0) {
            throw new AdminException('知识库不能为空');
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new AdminException('URL格式不正确');
        }

        [$html, $finalUrl] = $this->fetchUrlContent($url);
        if ($html === '') {
            throw new AdminException('URL内容为空或抓取失败');
        }

        $title = trim($customTitle);
        if ($title === '') {
            $title = $this->extractHtmlTitle($html);
        }
        if ($title === '') {
            $title = 'URL采集_' . date('YmdHis');
        }

        $content = $this->cleanHtmlContent($html);
        if ($content === '') {
            throw new AdminException('页面正文解析为空');
        }

        return $this->createKnowledge(
            $knowledgeBaseId,
            $merchantId,
            $title,
            $content,
            CsKnowledge::SOURCE_URL,
            $finalUrl
        );
    }

    /**
     * 提取URL正文文本（供 parseContent 接口使用）
     */
    public function extractTextFromUrl(string $url): string
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new AdminException('URL格式不正确');
        }

        [$html, ] = $this->fetchUrlContent($url);
        if ($html === '') {
            throw new AdminException('URL内容为空或抓取失败');
        }

        $text = $this->cleanHtmlContent($html);
        if ($text === '') {
            throw new AdminException('页面正文解析为空');
        }

        return $text;
    }

    /**
     * 提取文件正文文本（供 parseContent 接口使用）
     */
    public function extractTextFromFile(string $originalName, string $filePath): string
    {
        if ($originalName === '' || !is_file($filePath)) {
            throw new AdminException('文件不存在或无效');
        }

        $extension = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
        $allowExtensions = ['txt', 'md', 'docx', 'pdf', 'xlsx', 'xls', 'csv'];
        if (!in_array($extension, $allowExtensions, true)) {
            throw new AdminException('仅支持 txt/md/docx/pdf/xlsx/xls/csv 文件');
        }

        $text = $this->extractFileContent($filePath, $extension);
        if ($text === '') {
            throw new AdminException('文件内容为空或解析失败');
        }

        return $text;
    }

    /**
     * 删除Redis中的向量
     * @param int $merchantId 商户ID
     * @param int $knowledgeId 知识ID
     */
    public function deleteRedisVector(int $merchantId, int $knowledgeId): void
    {
        $cacheKey = $this->getCacheKey($merchantId);
        Cache::hDel($cacheKey, $knowledgeId);
    }

    /**
     * 获取向量模型实例
     * @return CsKnowledgeVector
     */
    private function getVectorModel(): CsKnowledgeVector
    {
        return app()->make(CsKnowledgeVector::class);
    }

    /**
     * 组装单一客服范围的检索向量集合
     * @param int $merchantId 商户ID
     * @return array
     */
    private function loadSearchVectorSets(int $merchantId = 0): array
    {
        return [[
            'merchant_id' => $merchantId,
            'vectors' => $this->loadVectorsFromRedis($merchantId),
        ]];
    }

    /**
     * 从Redis加载向量（field 保持原始字符串，兼容新旧格式）
     */
    private function loadVectorsFromRedis(int $merchantId = 0): array
    {
        $cacheKey = $this->getCacheKey($merchantId);

        if (!Cache::exists($cacheKey)) {
            $this->loadFromMysqlToRedis($merchantId);
        }

        $vectorsData = Cache::hGetAll($cacheKey);
        if (!is_array($vectorsData) || $vectorsData === []) {
            return [];
        }

        $vectors = [];
        foreach ($vectorsData as $field => $vectorJson) {
            $decoded = json_decode((string)$vectorJson, true);
            if (is_array($decoded) && $decoded !== []) {
                $vectors[$field] = $decoded;
            }
        }

        return $vectors;
    }

    /**
     * 从MySQL加载向量到Redis
     * 新格式 field = source_type:source_id；兼容旧整篇向量（field=knowledge_id 数字）
     */
    private function loadFromMysqlToRedis(int $merchantId = 0): void
    {
        $cacheKey = $this->getCacheKey($merchantId);

        $vectorRows = $this->getVectorModel()
            ->alias('v')
            ->join('cs_knowledge k', 'v.knowledge_id = k.id')
            ->where('k.merchant_id', $merchantId)
            ->where('k.status', 1)
            ->whereNull('k.delete_time')
            ->field('v.knowledge_id, v.source_type, v.source_id, v.vector')
            ->select()
            ->toArray();

        foreach ($vectorRows as $row) {
            $vectorData = is_array($row['vector'])
                ? $row['vector']
                : json_decode((string)$row['vector'], true);

            if (!is_array($vectorData) || $vectorData === []) {
                continue;
            }

            $sourceType = (string)($row['source_type'] ?? '');
            $sourceId   = (int)($row['source_id'] ?? 0);

            // 新格式：source_type:source_id；旧数据兼容（source_type 为空时回退为整篇knowledge_id）
            $field = ($sourceType !== '' && $sourceId > 0)
                ? $sourceType . ':' . $sourceId
                : (int)$row['knowledge_id'];

            Cache::hSet($cacheKey, $field, json_encode($vectorData, JSON_UNESCAPED_UNICODE));
        }

        Cache::expire($cacheKey, self::CACHE_TTL);
    }

    /**
     * 更新Redis中的向量
     * @param int $merchantId 商户ID
     * @param int $knowledgeId 知识ID
     * @param array $vector 向量数据
     */
    private function updateRedisVector(int $merchantId, int $knowledgeId, array $vector): void
    {
        $cacheKey = $this->getCacheKey($merchantId);
        Cache::hSet($cacheKey, $knowledgeId, json_encode($vector, JSON_UNESCAPED_UNICODE));
        Cache::expire($cacheKey, self::CACHE_TTL);
    }

    /**
     * 计算余弦相似度
     * @param array $vecA 向量A
     * @param array $vecB 向量B
     * @return float
     */
    private function cosineSimilarity(array $vecA, array $vecB): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $len = min(count($vecA), count($vecB));

        for ($i = 0; $i < $len; $i++) {
            $dotProduct += $vecA[$i] * $vecB[$i];
            $normA += $vecA[$i] * $vecA[$i];
            $normB += $vecB[$i] * $vecB[$i];
        }

        $normA = sqrt($normA);
        $normB = sqrt($normB);
        if ($normA <= 0 || $normB <= 0) {
            return 0.0;
        }

        return $dotProduct / ($normA * $normB);
    }

    /**
     * 解析文件正文
     * @param string $filePath
     * @param string $extension
     * @return string
     */
    private function extractFileContent(string $filePath, string $extension): string
    {
        $extension = strtolower($extension);

        return match ($extension) {
            'txt', 'md', 'csv' => $this->normalizeText((string)file_get_contents($filePath)),
            'docx' => $this->parseDocxContent($filePath),
            'xlsx', 'xls' => $this->parseSpreadsheetContent($filePath),
            'pdf' => $this->parsePdfContent($filePath),
            default => '',
        };
    }

    /**
     * 解析docx正文
     * @param string $filePath
     * @return string
     */
    private function parseDocxContent(string $filePath): string
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return '';
        }

        $index = $zip->locateName('word/document.xml');
        if ($index === false) {
            $zip->close();
            return '';
        }

        $xml = (string)$zip->getFromIndex($index);
        $zip->close();

        if ($xml === '') {
            return '';
        }

        $xml = str_replace(['</w:p>', '</w:tr>'], ["\n", "\n"], $xml);
        $text = strip_tags($xml);

        return $this->normalizeText(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * 解析Excel正文
     * @param string $filePath
     * @return string
     */
    private function parseSpreadsheetContent(string $filePath): string
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (\Throwable) {
            return '';
        }

        $lines = [];
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $rows = $sheet->toArray(null, true, true, true);
            foreach ($rows as $row) {
                $cells = array_values(array_filter(array_map(static function ($value) {
                    return trim((string)$value);
                }, $row), static fn($value) => $value !== ''));

                if ($cells !== []) {
                    $lines[] = implode(' | ', $cells);
                }
            }
        }

        return $this->normalizeText(implode("\n", $lines));
    }

    /**
     * 解析PDF正文（轻量）
     * @param string $filePath
     * @return string
     */
    private function parsePdfContent(string $filePath): string
    {
        $content = (string)file_get_contents($filePath);
        if ($content === '') {
            return '';
        }

        $texts = [];
        if (preg_match_all('/\((.*?)\)\s*T[Jj]/s', $content, $matches)) {
            foreach ($matches[1] as $item) {
                $texts[] = (string)preg_replace('/\\\\([\\\\()nrtbf])/', '$1', (string)$item);
            }
        }

        if ($texts === []) {
            $stripped = preg_replace('/[^\x20-\x7E\x{4e00}-\x{9fa5}\n\r\t]/u', ' ', $content);
            return $this->normalizeText((string)$stripped);
        }

        return $this->normalizeText(implode("\n", $texts));
    }

    /**
     * 抓取URL正文
     * @param string $url
     * @return array{string,string}
     */
    private function fetchUrlContent(string $url): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING => '',
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'KnowledgePaymentBot/1.0',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $body = curl_exec($ch);
        $finalUrl = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        return [is_string($body) ? $this->normalizeHtmlEncoding($body) : '', $finalUrl !== '' ? $finalUrl : $url];
    }

    /**
     * 提取HTML标题
     * @param string $html
     * @return string
     */
    private function extractHtmlTitle(string $html): string
    {
        $html = $this->normalizeHtmlEncoding($html);
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            return trim($this->normalizeText(html_entity_decode((string)$matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        }

        return '';
    }

    /**
     * 清理HTML正文
     * @param string $html
     * @return string
     */
    private function cleanHtmlContent(string $html): string
    {
        $html = $this->normalizeHtmlEncoding($html);
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html) ?? $html;
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $this->normalizeText($text);
    }

    /**
     * 规范化文本
     * @param string $text
     * @return string
     */
    private function normalizeText(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $text = $this->ensureUtf8($text);
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[\t ]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * 统一网页 HTML 编码为 UTF-8。
     *
     * @param string $html 原始 HTML。
     * @return string UTF-8 HTML。
     */
    private function normalizeHtmlEncoding(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $charset = $this->detectHtmlCharset($html);
        if ($charset !== '') {
            return $this->ensureUtf8($html, $charset);
        }

        return $this->ensureUtf8($html);
    }

    /**
     * 从 HTML meta 中识别 charset。
     *
     * @param string $html 原始 HTML。
     * @return string charset 名称，未识别时返回空字符串。
     */
    private function detectHtmlCharset(string $html): string
    {
        if (preg_match('/<meta[^>]+charset=["\']?\s*([a-zA-Z0-9_\-]+)/i', $html, $matches)) {
            return strtoupper(trim((string)$matches[1]));
        }

        if (preg_match('/<meta[^>]+content=["\'][^"\']*charset=([a-zA-Z0-9_\-]+)/i', $html, $matches)) {
            return strtoupper(trim((string)$matches[1]));
        }

        return '';
    }

    /**
     * 确保字符串为合法 UTF-8，避免 JSON 编码报 Malformed UTF-8。
     *
     * @param string $text 原始文本。
     * @param string $sourceEncoding 已知来源编码。
     * @return string UTF-8 文本。
     */
    private function ensureUtf8(string $text, string $sourceEncoding = ''): string
    {
        if ($text === '') {
            return '';
        }

        if (mb_check_encoding($text, 'UTF-8')) {
            return $text;
        }

        $encoding = $sourceEncoding !== ''
            ? strtoupper($sourceEncoding)
            : (string)mb_detect_encoding($text, ['UTF-8', 'GB18030', 'GBK', 'BIG5', 'ISO-8859-1'], true);

        if ($encoding !== '' && $encoding !== 'UTF-8') {
            $converted = @mb_convert_encoding($text, 'UTF-8', $encoding);
            if (is_string($converted) && mb_check_encoding($converted, 'UTF-8')) {
                return $converted;
            }
        }

        $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
        if (is_string($converted)) {
            return $converted;
        }

        return preg_replace('/[^\x09\x0A\x0D\x20-\x7E\x{4E00}-\x{9FFF}]/u', '', $text) ?? '';
    }

    /**
     * 构建向量调用运行时配置
     * @return array{provider:string,options:array}
     */
    private function buildEmbeddingRuntime(): array
    {
        /** @var AiModelConfigService $aiModelConfigService */
        $aiModelConfigService = app()->make(AiModelConfigService::class);
        $runtime = $aiModelConfigService->resolveRuntime(AiModelConfigService::TYPE_EMBEDDING);
        if (!is_array($runtime) || !isset($runtime['provider'], $runtime['options'])) {
            throw new AdminException('请先在AI模型配置中设置向量默认模型');
        }

        return [
            (string)$runtime['provider'],
            (array)$runtime['options'],
        ];
    }

    /**
     * 获取缓存Key
     * @param int $merchantId 商户ID
     * @return string
     */
    private function getCacheKey(int $merchantId = 0): string
    {
        return self::CACHE_PREFIX . ($merchantId > 0 ? $merchantId : 'platform');
    }
}
