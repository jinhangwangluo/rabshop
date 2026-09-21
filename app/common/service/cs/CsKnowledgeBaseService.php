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

namespace app\common\service\cs;

use app\common\exception\AdminException;
use app\common\model\cs\CsKnowledgeBase;
use app\common\service\BaseService;
use Rabbit\service\ai\chat\ChatService;
use think\facade\Log;

/**
 * 知识库实体服务
 */
class CsKnowledgeBaseService extends BaseService
{
    /**
     * 当前商户ID（-1=不限）
     */
    protected int $merchantId = -1;

    /**
     * 设置模型
     */
    protected function setModel(): string
    {
        return CsKnowledgeBase::class;
    }

    /**
     * 设置归属商户
     * @param int $merchantId 商户ID
     */
    public function setMerchantId(int $merchantId): void
    {
        $this->merchantId = $merchantId;
    }

    /**
     * 获取知识库列表（含文档数量统计）
     * @param int $page  页码
     * @param int $limit 每页数量
     * @param string $keyword 名称关键词
     * @param int|string $status 状态筛选（''=不限）
     * @return array{list:array, total:int, page:int, limit:int}
     */
    public function getList(int $page = 1, int $limit = 20, string $keyword = '', int|string $status = ''): array
    {
        $query = $this->buildOwnerQuery();

        if ($keyword !== '') {
            $query->where('name', 'like', '%' . $keyword . '%');
        }
        if ($status !== '') {
            $query->where('status', (int)$status);
        }

        $total = $query->count();
        $list = $query->order('sort asc, id desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        if ($list !== []) {
            $ids = array_column($list, 'id');
            $docCounts = app()->make(CsKnowledgeService::class)->countByBaseIds($ids, $this->merchantId);

            foreach ($list as &$item) {
                $item['doc_count'] = (int)($docCounts[$item['id']] ?? 0);
            }
            unset($item);
        }

        return ['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit];
    }

    /**
     * 获取单个知识库（含归属校验）
     * @param int $id 知识库ID
     * @return CsKnowledgeBase
     * @throws AdminException
     */
    public function getOneOrFail(int $id): CsKnowledgeBase
    {
        $query = $this->buildOwnerQuery()->where('id', $id);
        $record = $query->find();
        if (!$record) {
            throw new AdminException('知识库不存在');
        }

        return $record;
    }

    /**
     * 按 ID 获取知识库，不存在时返回 null。
     *
     * @param int $id 知识库 ID
     * @return CsKnowledgeBase|null
     */
    public function findById(int $id): ?CsKnowledgeBase
    {
        return $this->buildOwnerQuery()->where('id', $id)->find();
    }

    /**
     * 创建知识库
     * @param array $data 字段数据（name/description/cover/ai_model_id/status/sort/threshold/search_limit）
     * @return CsKnowledgeBase
     * @throws AdminException
     */
    public function create(array $data): CsKnowledgeBase
    {
        $this->validateName((string)($data['name'] ?? ''));

        $data['merchant_id']  = $this->getMerchantIdOrFail();
        $data['threshold']    = isset($data['threshold'])    ? max(0.0, min(1.0, (float)$data['threshold']))    : 0.6;
        $data['search_limit'] = isset($data['search_limit']) ? max(1, (int)$data['search_limit'])               : 5;

        return $this->getModel()->create($data);
    }

    /**
     * 更新知识库
     * @param int $id    知识库ID
     * @param array $data 更新字段
     * @throws AdminException
     */
    public function updateBase(int $id, array $data): void
    {
        if (isset($data['name'])) {
            $this->validateName((string)$data['name']);
        }
        if (isset($data['threshold'])) {
            $data['threshold'] = max(0.0, min(1.0, (float)$data['threshold']));
        }
        if (isset($data['search_limit'])) {
            $data['search_limit'] = max(1, (int)$data['search_limit']);
        }

        $record = $this->getOneOrFail($id);
        $record->save($data);
    }

    /**
     * 删除知识库
     * @param int $id 知识库ID
     * @throws AdminException
     */
    public function deleteBase(int $id): void
    {
        $record = $this->getOneOrFail($id);
        if ($this->countKnowledgeDocuments((int)$record->id) > 0) {
            throw new AdminException('请先删除知识库内的所有文档后再删除知识库');
        }

        $record->delete();
    }

    /**
     * 切换状态
     * @param int $id     知识库ID
     * @param int $status 目标状态（0/1）
     * @throws AdminException
     */
    public function toggleStatus(int $id, int $status): void
    {
        $record = $this->getOneOrFail($id);
        $record->status = $status;
        $record->save();
    }

    /**
     * 流式测试知识库回复。
     *
     * 事件协议固定为 start/chunk/done/error，前端可统一按 SSE 事件增量渲染。
     *
     * @param int $id 知识库ID
     * @param string $question 用户问题
     * @param callable $emit SSE 事件回调，参数为可 JSON 编码的事件数组
     */
    public function streamTestReply(int $id, string $question, callable $emit): void
    {
        $base = $this->getOneOrFail($id);
        $hits = $this->searchTestReplyKnowledge($question, (int)$base->id);

        if ($hits === []) {
            $this->emitStaticTestReply('未在该知识库中检索到相关内容，请尝试换个问题。', [], $emit);
            return;
        }

        $runtime = $this->resolveChatRuntime();
        if ($runtime === null) {
            $emit([
                'type' => 'error',
                'message' => 'AI模型调用失败，请检查模型配置',
            ]);
            return;
        }

        $this->streamAiTestReply($this->buildTestReplyPrompt($question, $hits), $hits, $runtime, $emit);
    }

    // ─── 私有辅助方法 ────────────────────────────────────────────

    /**
     * 构建带商户限定的查询
     */
    private function buildOwnerQuery()
    {
        $query = $this->getModel()->newQuery()->whereNull('delete_time');
        if ($this->merchantId >= 0) {
            $query->where('merchant_id', $this->merchantId);
        }

        return $query;
    }

    /**
     * 校验知识库名称
     * @param string $name
     * @throws AdminException
     */
    private function validateName(string $name): void
    {
        if (trim($name) === '') {
            throw new AdminException('知识库名称不能为空');
        }
    }

    /**
     * 获取商户ID（未设置时抛出异常）
     * @throws AdminException
     */
    private function getMerchantIdOrFail(): int
    {
        if ($this->merchantId < 0) {
            throw new AdminException('请先设置商户ID');
        }

        return $this->merchantId;
    }

    /**
     * 统计知识库下文档数量。
     *
     * @param int $knowledgeBaseId 知识库ID
     * @return int
     */
    private function countKnowledgeDocuments(int $knowledgeBaseId): int
    {
        return app()->make(CsKnowledgeService::class)->countByBaseId($knowledgeBaseId, $this->merchantId);
    }

    /**
     * 测试回复只允许使用当前知识库内的检索结果，避免回答串库。
     */
    private function searchTestReplyKnowledge(string $question, int $knowledgeBaseId): array
    {
        $merchantId = $this->merchantId >= 0 ? $this->merchantId : 0;

        return app()->make(CsKnowledgeService::class)->searchByBase($question, $merchantId, $knowledgeBaseId, 5);
    }

    /**
     * 构建测试回复专用 Prompt，约束模型只基于命中的知识片段回答。
     */
    private function buildTestReplyPrompt(string $question, array $hits): string
    {
        $context = '';
        foreach ($hits as $i => $hit) {
            $no = $i + 1;
            $context .= "[知识{$no}] 标题: {$hit['title']}\n";
            $context .= "[知识{$no}] 内容: {$hit['content']}\n\n";
        }

        $prompt = "你是一个专业的客服助手，请仅根据以下知识库内容回答用户问题。\n\n";
        $prompt .= "【知识库内容】\n" . $context;
        $prompt .= "【用户问题】\n" . $question . "\n\n";
        $prompt .= "【要求】仅基于上方知识库内容作答，不足则告知并建议联系人工。";

        return $prompt;
    }

    /**
     * 无命中时仍走同一事件协议，前端无需分支处理普通 JSON。
     */
    private function emitStaticTestReply(string $answer, array $hits, callable $emit): void
    {
        $emit([
            'type' => 'start',
            'hits' => $hits,
        ]);
        $emit([
            'type' => 'chunk',
            'delta' => $answer,
            'done' => false,
        ]);
        $emit([
            'type' => 'done',
            'delta' => '',
            'content' => $answer,
            'hits' => $hits,
            'done' => true,
        ]);
    }

    /**
     * 使用项目统一聊天模型配置。
     */
    private function resolveChatRuntime(): ?array
    {
        /** @var AiModelConfigService $aiModelConfigService */
        $aiModelConfigService = app()->make(AiModelConfigService::class);
        $runtime = $aiModelConfigService->resolveRuntime(AiModelConfigService::TYPE_CHAT);
        if (!is_array($runtime) || !isset($runtime['provider'], $runtime['options'])) {
            return null;
        }

        return $runtime;
    }

    /**
     * 调用 AI 流式回复，并把底层分片转换成页面需要的 start/chunk/done/error 事件。
     */
    private function streamAiTestReply(string $prompt, array $hits, array $runtime, callable $emit): void
    {
        $emit([
            'type' => 'start',
            'hits' => $hits,
        ]);

        /** @var ChatService $chatService */
        $chatService = app()->make(ChatService::class);
        $sentByHandler = false;
        $fullContent = '';

        try {
            $chunks = $chatService->replyStream(
                $prompt,
                [],
                [
                    'direct_output' => false,
                    'output_handler' => function (array $chunk) use (&$sentByHandler, &$fullContent, $hits, $emit): void {
                        $sentByHandler = true;
                        $this->emitChatChunk($chunk, $hits, $fullContent, $emit);
                    },
                ],
                (string)$runtime['provider'],
                (array)$runtime['options']
            );

            if (!$sentByHandler) {
                $this->emitReturnedChunks($chunks, $hits, $fullContent, $emit);
            }
        } catch (\Throwable $exception) {
            Log::error('知识库测试回复流式调用失败: ' . $exception->getMessage(), [
                'knowledge_base_merchant_id' => $this->merchantId,
            ]);
            $emit([
                'type' => 'error',
                'message' => 'AI模型调用失败，请检查模型配置',
            ]);
        }
    }

    /**
     * 处理降级场景中由驱动一次性返回的分片数组。
     */
    private function emitReturnedChunks(array $chunks, array $hits, string &$fullContent, callable $emit): void
    {
        $hasContent = false;
        foreach ($chunks as $chunk) {
            if (!is_array($chunk)) {
                continue;
            }

            $delta = (string)($chunk['delta'] ?? '');
            $done = (bool)($chunk['done'] ?? false);
            if ($delta !== '' || ($done && !empty($chunk['content']))) {
                $hasContent = true;
            }

            $this->emitChatChunk($chunk, $hits, $fullContent, $emit);
        }

        if (!$hasContent) {
            $emit([
                'type' => 'error',
                'message' => 'AI模型调用失败，请检查模型配置',
            ]);
        }
    }

    /**
     * 统一底层 ChatService 分片为前端消费的事件格式。
     */
    private function emitChatChunk(array $chunk, array $hits, string &$fullContent, callable $emit): void
    {
        $delta = (string)($chunk['delta'] ?? '');
        $done = (bool)($chunk['done'] ?? false);

        if (!$done && $delta !== '') {
            $fullContent .= $delta;
            $emit([
                'type' => 'chunk',
                'delta' => $delta,
                'done' => false,
            ]);
            return;
        }

        if (!$done) {
            return;
        }

        $content = isset($chunk['content']) && is_string($chunk['content'])
            ? $chunk['content']
            : $fullContent;
        $fullContent = $content;
        $emit([
            'type' => 'done',
            'delta' => '',
            'content' => $content,
            'hits' => $hits,
            'done' => true,
        ]);
    }
}
