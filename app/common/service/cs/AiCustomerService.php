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

use app\common\enum\ConfigKeyEnum;
use app\common\model\cs\CsSession;
use app\common\service\BaseService;
use app\common\service\setting\ConfigService;
use Rabbit\service\ai\chat\ChatService;
use think\facade\Log;

/**
 * AI客服服务
 */
class AiCustomerService extends BaseService
{
    /**
     * 默认兜底回复内容。
     */
    private const DEFAULT_FALLBACK_REPLY = '暂未检索到相关知识库内容，建议联系人工客服协助处理。';

    /**
     * 知识库 MCP 工具名称。
     */
    private const KNOWLEDGE_SEARCH_TOOL = 'search_knowledge_base';

    /**
     * 知识库 MCP 工具最多检索条数。
     */
    private const KNOWLEDGE_SEARCH_LIMIT = 5;

    /**
     * 设置模型
     * @return string
     */
    protected function setModel(): string
    {
        return CsSession::class;
    }

    /**
     * AI回复
     * @param string   $sessionId        会话ID
     * @param string   $userMessage      用户消息
     * @param int      $merchantId       商户ID
     * @param int[]    $knowledgeBaseIds 绑定的知识库ID列表（空则全商户搜索）
     * @param callable|null $onChunk     保留兼容的旧流式回调参数
     * @return array
     */
    public function reply(string $sessionId, string $userMessage, int $merchantId = 0, array $knowledgeBaseIds = [], ?callable $onChunk = null): array
    {
        if ($this->shouldTransferToHuman($userMessage)) {
            Log::info('[AI客服] 命中转人工关键词，session=' . $sessionId);
            return [
                'should_transfer' => true,
                'message' => '',
                'streamed' => false,
                'extra' => [
                    'ai_route' => 'transfer_keyword',
                    'knowledge_hit' => false,
                    'knowledge_hit_count' => 0,
                    'fallback' => false,
                ],
            ];
        }

        $runtime = $this->resolveChatRuntime($merchantId);
        if ($runtime !== null) {
            $result = $this->replyWithKnowledgeMcp(
                $sessionId,
                $userMessage,
                $merchantId,
                $knowledgeBaseIds,
                $runtime,
                $onChunk
            );
            if ($result !== null) {
                return $result;
            }

            return $this->buildFallbackResult($userMessage, 'knowledge_mcp_failed');
        }

        // 没有配置对话模型时保留旧的服务端检索降级能力，避免客服完全不可用。
        $knowledgeList = $this->searchKnowledge($userMessage, $merchantId, $knowledgeBaseIds);
        $result = $this->buildKnowledgeReply($knowledgeList, $userMessage);

        Log::info('[AI客服] 知识库回复完成', [
            'session_id' => $sessionId,
            'merchant_id' => $merchantId,
            'knowledge_hit_count' => (int)($result['extra']['knowledge_hit_count'] ?? 0),
            'fallback_reason' => (string)($result['extra']['fallback_reason'] ?? ''),
        ]);

        return $result;
    }

    /**
     * 检索当前客服范围内的知识。
     *
     * @param string $userMessage 用户消息
     * @param int $merchantId 商户 ID
     * @param int[] $knowledgeBaseIds 绑定知识库 ID
     * @return array
     */
    protected function searchKnowledge(string $userMessage, int $merchantId, array $knowledgeBaseIds = []): array
    {
        /** @var CsKnowledgeService $knowledgeService */
        $knowledgeService = app()->make(CsKnowledgeService::class);

        if ($knowledgeBaseIds === []) {
            return $knowledgeService->searchWithPlatformFallback($userMessage, $merchantId, 5, 0.6);
        }

        return $knowledgeService->searchByBases($userMessage, $merchantId, $knowledgeBaseIds);
    }

    /**
     * 获取客服使用的聊天模型运行时配置。
     *
     * 优先使用商户模型，商户未配置时回退平台模型。
     *
     * @param int $merchantId 商户ID
     * @return array|null
     */
    protected function resolveChatRuntime(int $merchantId = 0): ?array
    {
        try {
            /** @var AiModelConfigService $service */
            $service = app()->make(AiModelConfigService::class);
            $runtime = $service->resolveRuntime(
                AiModelConfigService::TYPE_CHAT,
                max(0, $merchantId)
            );

            if ($runtime === null && $merchantId > 0) {
                $runtime = $service->resolveRuntime(AiModelConfigService::TYPE_CHAT, 0);
            }

            if (!is_array($runtime) || !isset($runtime['provider'], $runtime['options'])) {
                return null;
            }

            return $runtime;
        } catch (\Throwable $exception) {
            Log::warning('[AI客服] 读取对话模型配置失败: ' . $exception->getMessage());
            return null;
        }
    }

    /**
     * 通过知识库 MCP 工具完成一次客服回复。
     *
     * @param string $sessionId 会话ID
     * @param string $userMessage 用户消息
     * @param int $merchantId 商户ID
     * @param int[] $knowledgeBaseIds 知识库ID列表
     * @param array $runtime 聊天模型运行时
     * @param callable|null $onChunk 流式分片回调
     * @return array|null
     */
    protected function replyWithKnowledgeMcp(
        string $sessionId,
        string $userMessage,
        int $merchantId,
        array $knowledgeBaseIds,
        array $runtime,
        ?callable $onChunk = null
    ): ?array {
        $messages = [
            [
                'role' => 'system',
                'content' => $this->buildKnowledgeMcpSystemPrompt(),
            ],
            [
                'role' => 'user',
                'content' => $userMessage,
            ],
        ];

        try {
            $decision = $this->callChatWithTools(
                '',
                $messages,
                [$this->buildKnowledgeSearchTool()],
                [
                    'max_tokens' => 500,
                    'temperature' => 0.1,
                    'tool_choice' => 'auto',
                ],
                (string)$runtime['provider'],
                (array)$runtime['options']
            );
        } catch (\Throwable $exception) {
            Log::error('[AI客服] 知识库 MCP 首轮调用失败: ' . $exception->getMessage(), [
                'session_id' => $sessionId,
                'merchant_id' => $merchantId,
            ]);
            return null;
        }

        if ((string)($decision['error'] ?? '') !== '') {
            Log::warning('[AI客服] 知识库 MCP 首轮返回错误', [
                'session_id' => $sessionId,
                'error' => (string)$decision['error'],
            ]);
            return null;
        }

        $toolCalls = is_array($decision['tool_calls'] ?? null)
            ? $decision['tool_calls']
            : [];
        if ($toolCalls === []) {
            return $this->buildFallbackResult($userMessage, 'model_skipped_knowledge_tool', 0, [
                'mcp_used' => true,
                'tool_queries' => [],
            ]);
        }

        try {
            $toolExecution = $this->executeKnowledgeToolCalls(
                $toolCalls,
                $userMessage,
                $merchantId,
                $knowledgeBaseIds
            );
        } catch (\Throwable $exception) {
            Log::warning('[AI客服] 知识库 MCP 工具执行失败: ' . $exception->getMessage(), [
                'session_id' => $sessionId,
                'merchant_id' => $merchantId,
            ]);
            return $this->buildFallbackResult($userMessage, 'knowledge_search_failed', 0, [
                'mcp_used' => true,
                'tool_queries' => [],
            ]);
        }
        if (count($toolExecution['messages']) !== count($toolCalls)) {
            return $this->buildFallbackResult($userMessage, 'unsupported_tool_call', 0, [
                'mcp_used' => true,
                'tool_queries' => [],
            ]);
        }
        $hits = $toolExecution['hits'];
        $toolQueries = $toolExecution['queries'];
        $sourceExtra = [
            'mcp_used' => true,
            'tool_queries' => $toolQueries,
            'sources' => $this->buildKnowledgeSources($hits),
        ];

        if ($hits === []) {
            return $this->buildFallbackResult(
                $userMessage,
                'knowledge_empty',
                0,
                $sourceExtra
            );
        }

        $assistantMessage = $this->buildAssistantToolMessage($decision, $toolCalls);
        $answerMessages = array_merge(
            $messages,
            [$assistantMessage],
            $toolExecution['messages']
        );

        if ($onChunk !== null) {
            $streamResult = $this->streamKnowledgeMcpAnswer(
                $answerMessages,
                $hits,
                $sourceExtra,
                $runtime,
                $onChunk
            );
            if ($streamResult !== null) {
                return $streamResult;
            }
        }

        try {
            $answer = $this->callChatWithTools(
                '',
                $answerMessages,
                [],
                [
                    'max_tokens' => 800,
                    'temperature' => 0.2,
                ],
                (string)$runtime['provider'],
                (array)$runtime['options']
            );
        } catch (\Throwable $exception) {
            Log::error('[AI客服] 知识库 MCP 二轮回答失败: ' . $exception->getMessage(), [
                'session_id' => $sessionId,
                'merchant_id' => $merchantId,
            ]);
            return $this->buildFallbackResult(
                $userMessage,
                'model_answer_failed',
                count($hits),
                $sourceExtra
            );
        }

        if ((string)($answer['error'] ?? '') !== '') {
            return $this->buildFallbackResult(
                $userMessage,
                'model_answer_failed',
                count($hits),
                $sourceExtra
            );
        }

        $content = trim((string)($answer['content'] ?? ''));
        if ($content === '') {
            return $this->buildFallbackResult(
                $userMessage,
                'model_answer_empty',
                count($hits),
                $sourceExtra
            );
        }

        $topKnowledge = $hits[0];
        return [
            'should_transfer' => false,
            'message' => $content,
            'streamed' => false,
            'extra' => [
                'ai_route' => 'knowledge_mcp_answer',
                'knowledge_hit' => true,
                'knowledge_hit_count' => count($hits),
                'fallback' => false,
                'fallback_reason' => '',
            ] + $sourceExtra + $this->buildKnowledgeSourceExtra($topKnowledge),
        ];
    }

    /**
     * 流式生成知识库 MCP 最终回答。
     *
     * @param array $messages 带工具结果的消息列表
     * @param array $hits 知识库命中结果
     * @param array $sourceExtra 来源扩展信息
     * @param array $runtime 聊天模型运行时
     * @param callable $onChunk 分片回调
     * @return array|null
     */
    protected function streamKnowledgeMcpAnswer(
        array $messages,
        array $hits,
        array $sourceExtra,
        array $runtime,
        callable $onChunk
    ): ?array {
        $fullContent = '';
        $sentByHandler = false;

        try {
            $chunks = $this->callChatStream(
                '',
                $messages,
                [
                    'max_tokens' => 800,
                    'temperature' => 0.2,
                    'direct_output' => false,
                    'stream_fallback' => false,
                    'output_handler' => function (array $chunk) use (&$fullContent, &$sentByHandler, $onChunk): void {
                        $sentByHandler = true;
                        $this->appendStreamChunk($chunk, $fullContent, $onChunk);
                    },
                ],
                (string)$runtime['provider'],
                (array)$runtime['options']
            );
        } catch (\Throwable $exception) {
            Log::warning('[AI客服] 知识库 MCP 流式回答失败: ' . $exception->getMessage());
            return null;
        }

        if (!$sentByHandler) {
            foreach ($chunks as $chunk) {
                if (is_array($chunk)) {
                    $this->appendStreamChunk($chunk, $fullContent, $onChunk);
                }
            }
        }

        $content = trim($fullContent);
        if ($content === '') {
            return null;
        }

        $topKnowledge = $hits[0];
        return [
            'should_transfer' => false,
            'message' => $content,
            'streamed' => true,
            'extra' => [
                'ai_route' => 'knowledge_mcp_answer',
                'knowledge_hit' => true,
                'knowledge_hit_count' => count($hits),
                'fallback' => false,
                'fallback_reason' => '',
            ] + $sourceExtra + $this->buildKnowledgeSourceExtra($topKnowledge),
        ];
    }

    /**
     * 追加流式分片并回调给调用方。
     *
     * @param array $chunk 模型分片
     * @param string $fullContent 完整内容引用
     * @param callable $onChunk 分片回调
     * @return void
     */
    protected function appendStreamChunk(array $chunk, string &$fullContent, callable $onChunk): void
    {
        $delta = (string)($chunk['delta'] ?? '');
        $done = (bool)($chunk['done'] ?? false);

        if (!$done && $delta !== '') {
            $fullContent .= $delta;
            $onChunk($delta);
            return;
        }

        if ($done && isset($chunk['content']) && is_string($chunk['content'])) {
            $fullContent = $chunk['content'];
        }
    }

    /**
     * 调用聊天模型工具接口。
     *
     * @param string $prompt 提示词
     * @param array $messages 消息列表
     * @param array $tools 工具定义
     * @param array $options 请求参数
     * @param string $provider 服务商
     * @param array $providerOptions 服务商运行时配置
     * @return array
     */
    protected function callChatWithTools(
        string $prompt,
        array $messages,
        array $tools,
        array $options,
        string $provider,
        array $providerOptions
    ): array {
        /** @var ChatService $chatService */
        $chatService = app()->make(ChatService::class);

        return $chatService->replyWithTools(
            $prompt,
            $messages,
            $tools,
            $options,
            $provider,
            $providerOptions
        );
    }

    /**
     * 调用聊天模型流式接口。
     *
     * @param string $prompt 提示词
     * @param array $messages 消息列表
     * @param array $options 请求参数
     * @param string $provider 服务商
     * @param array $providerOptions 服务商运行时配置
     * @return array
     */
    protected function callChatStream(
        string $prompt,
        array $messages,
        array $options,
        string $provider,
        array $providerOptions
    ): array {
        /** @var ChatService $chatService */
        $chatService = app()->make(ChatService::class);

        return $chatService->replyStream(
            $prompt,
            $messages,
            $options,
            $provider,
            $providerOptions
        );
    }

    /**
     * 知识库 MCP 工具定义。
     *
     * @return array
     */
    protected function buildKnowledgeSearchTool(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => self::KNOWLEDGE_SEARCH_TOOL,
                'description' => '检索当前客服可见知识库。用户询问商品、服务、政策、流程或平台规则时必须先调用，禁止直接凭常识回答。',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => '用于知识库检索的完整用户问题，保留关键业务名词和限制条件。',
                        ],
                    ],
                    'required' => ['query'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /**
     * 知识库 MCP 系统提示。
     *
     * @return string
     */
    protected function buildKnowledgeMcpSystemPrompt(): string
    {
        return '你是平台客服助手。你只能依据当前客服可见知识库回答问题，不能使用常识、猜测或外部信息补充答案。'
            . '只要用户的问题可能需要业务信息，就必须先调用 search_knowledge_base 工具；不要直接回答。'
            . '工具返回后，仅根据返回内容组织简洁、准确的中文客服回复；如果内容不足以回答，就明确说明知识库没有覆盖该问题。';
    }

    /**
     * 执行模型发起的知识库工具调用。
     *
     * @param array $toolCalls 工具调用列表
     * @param string $userMessage 原始用户消息
     * @param int $merchantId 商户ID
     * @param int[] $knowledgeBaseIds 知识库ID列表
     * @return array{messages:array,hits:array,queries:array}
     */
    protected function executeKnowledgeToolCalls(
        array $toolCalls,
        string $userMessage,
        int $merchantId,
        array $knowledgeBaseIds
    ): array {
        $messages = [];
        $hitsBySource = [];
        $queries = [];

        foreach ($toolCalls as $toolCall) {
            if (!is_array($toolCall) || (string)($toolCall['name'] ?? '') !== self::KNOWLEDGE_SEARCH_TOOL) {
                continue;
            }

            $arguments = json_decode((string)($toolCall['arguments'] ?? ''), true);
            $arguments = is_array($arguments) ? $arguments : [];
            $query = trim((string)($arguments['query'] ?? ''));
            $query = $query !== '' ? $query : $userMessage;
            $queries[] = $query;

            $hits = $this->searchKnowledge($query, $merchantId, $knowledgeBaseIds);
            usort(
                $hits,
                static fn(array $left, array $right): int => (float)($right['similarity'] ?? 0)
                    <=> (float)($left['similarity'] ?? 0)
            );
            $hits = array_slice($hits, 0, self::KNOWLEDGE_SEARCH_LIMIT);

            foreach ($hits as $hit) {
                $sourceKey = (string)($hit['source_type'] ?? 'knowledge')
                    . ':' . (int)($hit['source_id'] ?? 0);
                if (
                    !isset($hitsBySource[$sourceKey])
                    || (float)($hit['similarity'] ?? 0) > (float)($hitsBySource[$sourceKey]['similarity'] ?? 0)
                ) {
                    $hitsBySource[$sourceKey] = $hit;
                }
            }

            $toolResult = [
                'query' => $query,
                'found' => $hits !== [],
                'items' => $this->buildKnowledgeToolItems($hits),
            ];
            $messages[] = [
                'role' => 'tool',
                'tool_call_id' => (string)($toolCall['id'] ?? ''),
                'name' => self::KNOWLEDGE_SEARCH_TOOL,
                'content' => json_encode($toolResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];
        }

        $hits = array_values($hitsBySource);
        usort(
            $hits,
            static fn(array $left, array $right): int => (float)($right['similarity'] ?? 0)
                <=> (float)($left['similarity'] ?? 0)
        );

        return [
            'messages' => $messages,
            'hits' => array_slice($hits, 0, self::KNOWLEDGE_SEARCH_LIMIT),
            'queries' => array_values(array_unique($queries)),
        ];
    }

    /**
     * 构建交给模型的助手工具调用消息。
     *
     * @param array $decision 首轮模型响应
     * @param array $toolCalls 规范化工具调用
     * @return array
     */
    protected function buildAssistantToolMessage(array $decision, array $toolCalls): array
    {
        $message = $decision['assistant_message'] ?? null;
        if (is_array($message) && isset($message['tool_calls'])) {
            return $message;
        }

        $rawToolCalls = [];
        foreach ($toolCalls as $toolCall) {
            $rawToolCalls[] = $toolCall['raw'] ?? [
                'id' => (string)($toolCall['id'] ?? ''),
                'type' => 'function',
                'function' => [
                    'name' => self::KNOWLEDGE_SEARCH_TOOL,
                    'arguments' => (string)($toolCall['arguments'] ?? ''),
                ],
            ];
        }

        return [
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => $rawToolCalls,
        ];
    }

    /**
     * 构建传给模型的知识片段。
     *
     * @param array $hits 检索结果
     * @return array
     */
    protected function buildKnowledgeToolItems(array $hits): array
    {
        $items = [];
        foreach ($hits as $hit) {
            $items[] = [
                'knowledge_id' => (int)($hit['knowledge_id'] ?? 0),
                'title' => (string)($hit['title'] ?? ''),
                'content' => mb_substr(trim((string)($hit['content'] ?? '')), 0, 3000),
                'source_type' => (string)($hit['source_type'] ?? ''),
                'source_id' => (int)($hit['source_id'] ?? 0),
                'similarity' => (float)($hit['similarity'] ?? 0),
            ];
        }

        return $items;
    }

    /**
     * 构建引用来源列表。
     *
     * @param array $hits 检索结果
     * @return array
     */
    protected function buildKnowledgeSources(array $hits): array
    {
        $sources = [];
        foreach ($hits as $hit) {
            $sources[] = [
                'knowledge_id' => (int)($hit['knowledge_id'] ?? 0),
                'title' => (string)($hit['title'] ?? ''),
                'source_type' => (string)($hit['source_type'] ?? ''),
                'source_id' => (int)($hit['source_id'] ?? 0),
                'similarity' => (float)($hit['similarity'] ?? 0),
            ];
        }

        return $sources;
    }

    /**
     * 构建知识库直返结果。
     *
     * @param array $knowledgeList 检索结果
     * @param string $userMessage 用户消息
     * @return array
     */
    protected function buildKnowledgeReply(array $knowledgeList, string $userMessage): array
    {
        if ($knowledgeList === []) {
            return $this->buildFallbackResult($userMessage, 'knowledge_empty');
        }

        usort(
            $knowledgeList,
            static fn(array $left, array $right): int => (float)($right['similarity'] ?? 0)
                <=> (float)($left['similarity'] ?? 0)
        );

        $topKnowledge = $knowledgeList[0];
        $content = trim((string)($topKnowledge['content'] ?? ''));
        $sourceExtra = $this->buildKnowledgeSourceExtra($topKnowledge);
        if ($content === '') {
            return $this->buildFallbackResult(
                $userMessage,
                'knowledge_content_empty',
                count($knowledgeList),
                $sourceExtra
            );
        }

        return [
            'should_transfer' => false,
            'message' => $content,
            'streamed' => false,
            'extra' => [
                'ai_route' => 'knowledge_answer',
                'knowledge_hit' => true,
                'knowledge_hit_count' => count($knowledgeList),
                'fallback' => false,
                'fallback_reason' => '',
            ] + $sourceExtra,
        ];
    }

    /**
     * 构建知识库兜底结果。
     *
     * @param string $userMessage 用户消息
     * @param string $reason 兜底原因
     * @param int $hitCount 命中数量
     * @param array $sourceExtra 知识来源信息
     * @return array
     */
    protected function buildFallbackResult(
        string $userMessage,
        string $reason,
        int $hitCount = 0,
        array $sourceExtra = []
    ): array {
        return [
            'should_transfer' => false,
            'message' => $this->buildFallbackReply($userMessage),
            'streamed' => false,
            'extra' => array_merge([
                'ai_route' => 'knowledge_fallback',
                'knowledge_hit' => $hitCount > 0,
                'knowledge_hit_count' => $hitCount,
                'fallback' => true,
                'fallback_reason' => $reason,
                'mcp_used' => false,
                'tool_queries' => [],
                'sources' => [],
                'knowledge_id' => 0,
                'source_type' => '',
                'source_id' => 0,
                'similarity' => 0.0,
            ], $sourceExtra),
        ];
    }

    /**
     * 提取知识来源信息。
     *
     * @param array $knowledge 知识命中项
     * @return array
     */
    protected function buildKnowledgeSourceExtra(array $knowledge): array
    {
        return [
            'knowledge_id' => (int)($knowledge['knowledge_id'] ?? 0),
            'source_type' => (string)($knowledge['source_type'] ?? ''),
            'source_id' => (int)($knowledge['source_id'] ?? 0),
            'similarity' => (float)($knowledge['similarity'] ?? 0),
        ];
    }

    /**
     * 检查是否应该转人工
     * @param string $message 用户消息
     * @return bool
     */
    protected function shouldTransferToHuman(string $message): bool
    {
        $keywords = $this->getTransferKeywords();
        foreach ($keywords as $keyword) {
            if ($keyword !== '' && mb_strpos($message, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取转人工关键词
     * @return array
     */
    private function getTransferKeywords(): array
    {
        /** @var ConfigService $configService */
        $configService = app()->make(ConfigService::class);
        $keywords = $configService->getConfigValue('cs_transfer_keywords');

        if (empty($keywords)) {
            return ['人工', '转人工', '人工客服', '客服', '人工服务'];
        }

        if (is_array($keywords)) {
            return array_values(array_filter($keywords, static fn($item) => is_string($item) && $item !== ''));
        }

        $decoded = json_decode((string)$keywords, true);
        return is_array($decoded) ? array_values(array_filter($decoded, static fn($item) => is_string($item) && $item !== '')) : [];
    }

    /**
     * 构建兜底回复。
     *
     * @param string $userMessage 用户消息。
     * @return string 兜底回复内容。
     */
    private function buildFallbackReply(string $userMessage): string
    {
        try {
            $reply = (string)db_config(ConfigKeyEnum::CS_AI_FALLBACK_REPLY, self::DEFAULT_FALLBACK_REPLY);
        } catch (\Throwable $exception) {
            Log::warning('[AI客服] 读取兜底回复配置失败: ' . $exception->getMessage());
            $reply = self::DEFAULT_FALLBACK_REPLY;
        }

        if (trim($reply) !== '') {
            return $reply;
        }

        return self::DEFAULT_FALLBACK_REPLY;
    }

}
