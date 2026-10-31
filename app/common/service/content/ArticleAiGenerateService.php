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

namespace app\common\service\content;

use app\common\enum\ContentTypeEnum;
use app\common\exception\AdminResponseException;
use app\common\model\content\Article;
use app\common\service\BaseService;
use app\common\service\cs\AiModelConfigService;
use Rabbit\service\ai\chat\ChatService;
use think\facade\Log;

/**
 * 内容 AI 润色写作服务。
 *
 * 负责为后台内容/专题编辑页生成简介、正文、试读内容、售价与建议标签。
 * 本服务只返回生成建议，不直接保存内容，也不会创建或绑定标签。
 */
class ArticleAiGenerateService extends BaseService
{
    /**
     * 支持 AI 生成的内容类型。
     */
    private const SUPPORT_TYPES = [
        ContentTypeEnum::GRAPHIC,
        ContentTypeEnum::TEXT,
        ContentTypeEnum::NOVEL,
    ];

    /**
     * 设置关联模型。
     *
     * @return string 内容模型类名。
     */
    protected function setModel(): string
    {
        return Article::class;
    }

    /**
     * 生成内容编辑建议。
     *
     * @param array $params 请求参数：
     *                      - title string 内容标题，必填。
     *                      - content_type int 内容类型，仅支持图文、文字、小说。
     *                      - author string 作者，可选。
     *                      - keywords string 关键词，可选。
     *                      - description string 当前简介，可选。
     *                      - content string 当前正文，可选。
     *                      - trial_content string 当前试读内容，可选。
     *                      - price float 当前售价，可选。
     *                      - is_trial int 是否支持试读，可选。
     * @param int $merchantId 商户 ID，0 表示使用平台模型。
     * @return array 返回生成结果：
     *               - description string 简介建议。
     *               - content string 正文 HTML。
     *               - trial_content string 试读 HTML。
     *               - keywords string 关键词建议。
     *               - price float 售价建议。
     *               - tag_suggestions array 建议标签列表，仅作展示，不自动创建。
     *               - model array 本次使用的默认模型信息。
     */
    public function generate(array $params, int $merchantId = 0): array
    {
        $payload = $this->normalizePayload($params);

        return $this->generateFromPayload($payload, $merchantId);
    }

    /**
     * 生成专题编辑建议。
     *
     * @param array $params 请求参数：
     *                      - title string 专题标题，必填。
     *                      - keywords string 当前关键词，可选。
     *                      - description string 当前专题介绍，可选。
     *                      - price float 当前售价，可选。
     *                      - special_type int 专题类型，可选。
     * @param int $merchantId 商户 ID，0 表示使用平台模型。
     * @return array 返回生成结果：
     *               - description string 专题介绍建议。
     *               - keywords string 关键词建议。
     *               - price float 售价建议。
     *               - tag_suggestions array 建议标签列表，仅作展示，不自动创建。
     */
    public function generateSpecial(array $params, int $merchantId = 0): array
    {
        $payload = $this->normalizeSpecialPayload($params);

        return $this->generateFromPayload($payload, $merchantId);
    }

    /**
     * 续写正文或试读内容。
     *
     * 基于当前内容末尾的上下文，调用默认 AI 对话模型生成续写片段，
     * 由前端追加到编辑器现有内容末尾。
     *
     * @param array $params 请求参数：
     *                      - title string 内容标题，必填。
     *                      - content_type int 内容类型，仅支持图文、文字、小说。
     *                      - field string 续写字段：content | trial_content。
     *                      - content string 该字段当前 HTML，允许为空（为空时 AI 从标题起笔）。
     * @param int $merchantId 商户 ID，0 表示使用平台模型。
     * @return array 返回生成结果：
     *               - content string 续写 HTML，由前端追加到现有内容末尾。
     *               - model array 本次使用的默认模型信息。
     */
    public function continueWriting(array $params, int $merchantId = 0): array
    {
        $payload = $this->normalizeContinuePayload($params);
        $runtime = $this->resolveChatRuntime($merchantId);
        $prompt = $this->buildContinuePrompt($payload);

        try {
            // 续写输出较长，解除默认执行时间限制
            set_time_limit(0);
            /** @var ChatService $chatService */
            $chatService = app()->make(ChatService::class);
            $response = (string)$chatService->reply(
                $prompt,
                [],
                [
                    'max_tokens' => 2400,
                    'temperature' => 0.5,
                ],
                (string)$runtime['provider'],
                (array)$runtime['options']
            );
        } catch (\Throwable $exception) {
            Log::error('[内容AI续写] 对话模型调用失败: ' . $exception->getMessage());
            throw new AdminResponseException(
                'AI续写失败，请检查' . ($merchantId > 0 ? '当前商户' : '') . '默认对话模型配置'
            );
        }

        if (trim($response) === '') {
            throw new AdminResponseException(
                'AI未返回内容，请检查' . ($merchantId > 0 ? '当前商户' : '') . '默认对话模型或稍后重试'
            );
        }

        $data = $this->decodeJsonResponse($response);
        $content = $this->sanitizeHtml((string)($data['content'] ?? ''));
        if ($content === '') {
            throw new AdminResponseException('AI未返回续写内容，请重新生成');
        }

        return [
            'content' => $content,
            'model' => [
                'provider_name' => (string)($runtime['provider_name'] ?? ''),
                'model_id' => (string)($runtime['model_id'] ?? ''),
            ],
        ];
    }

    /**
     * 按规范化后的 payload 调用 AI 生成建议。
     */
    protected function generateFromPayload(array $payload, int $merchantId = 0): array
    {
        $runtime = $this->resolveChatRuntime($merchantId);
        $prompt = $this->buildPrompt($payload);

        try {
            // 生成长度调大后可能超过默认 30 秒执行限制，解除单次请求时间限制
            set_time_limit(0);
            /** @var ChatService $chatService */
            $chatService = app()->make(ChatService::class);
            $response = (string)$chatService->reply(
                $prompt,
                [],
                [
                    'max_tokens' => 4500,
                    'temperature' => 0.45,
                ],
                (string)$runtime['provider'],
                (array)$runtime['options']
            );
        } catch (\Throwable $exception) {
            Log::error('[内容AI润色] 对话模型调用失败: ' . $exception->getMessage());
            throw new AdminResponseException(
                'AI生成失败，请检查' . ($merchantId > 0 ? '当前商户' : '') . '默认对话模型配置'
            );
        }

        if (trim($response) === '') {
            throw new AdminResponseException(
                'AI未返回内容，请检查' . ($merchantId > 0 ? '当前商户' : '') . '默认对话模型或稍后重试'
            );
        }

        $result = $this->normalizeResult($this->decodeJsonResponse($response));
        $result['model'] = [
            'provider_name' => (string)($runtime['provider_name'] ?? ''),
            'model_id' => (string)($runtime['model_id'] ?? ''),
        ];

        return $result;
    }

    /**
     * 规范化请求参数。
     *
     * @param array $params 原始请求参数。
     * @return array 规范化后的 Prompt 输入。
     */
    protected function normalizePayload(array $params): array
    {
        $title = trim((string)($params['title'] ?? ''));
        if ($title === '') {
            throw new AdminResponseException('请先输入内容标题，再使用AI润色写作');
        }

        $contentType = (int)($params['content_type'] ?? ContentTypeEnum::GRAPHIC);
        if (!in_array($contentType, self::SUPPORT_TYPES, true)) {
            throw new AdminResponseException('AI润色写作仅支持图文、文字、小说类型');
        }

        $price = max(0, (float)($params['price'] ?? 0));

        return [
            'target_type' => 'article',
            'title' => mb_substr($title, 0, 100),
            'content_type' => $contentType,
            'content_type_text' => ContentTypeEnum::getName($contentType),
            'author' => mb_substr(trim((string)($params['author'] ?? '')), 0, 30),
            'keywords' => mb_substr(trim((string)($params['keywords'] ?? '')), 0, 100),
            'description' => mb_substr(trim(strip_tags((string)($params['description'] ?? ''))), 0, 600),
            'content' => $this->toPlainText((string)($params['content'] ?? ''), 2000),
            'trial_content' => $this->toPlainText((string)($params['trial_content'] ?? ''), 1000),
            'price' => round($price, 2),
            'is_trial' => (int)($params['is_trial'] ?? 0) === 1 ? 1 : 0,
        ];
    }

    /**
     * 规范化专题 AI 请求参数。
     *
     * @param array $params 原始请求参数。
     * @return array 规范化后的 Prompt 输入。
     */
    protected function normalizeSpecialPayload(array $params): array
    {
        $title = trim((string)($params['title'] ?? ''));
        if ($title === '') {
            throw new AdminResponseException('请先输入专题标题，再使用AI润色写作');
        }

        $price = max(0, (float)($params['price'] ?? 0));
        $specialType = (int)($params['special_type'] ?? 0) === 1 ? 1 : 0;

        return [
            'target_type' => 'special',
            'title' => mb_substr($title, 0, 100),
            'keywords' => mb_substr(trim((string)($params['keywords'] ?? '')), 0, 100),
            'description' => mb_substr(trim(strip_tags((string)($params['description'] ?? ''))), 0, 600),
            'price' => round($price, 2),
            'special_type' => $specialType,
            'special_type_text' => $specialType === 1 ? '付费专题' : '免费专题',
        ];
    }

    /**
     * 规范化续写请求参数。
     *
     * @param array $params 原始请求参数。
     * @return array 规范化后的续写 Prompt 输入。
     */
    protected function normalizeContinuePayload(array $params): array
    {
        $title = trim((string)($params['title'] ?? ''));
        if ($title === '') {
            throw new AdminResponseException('请先输入内容标题，再使用AI继续写');
        }

        $contentType = (int)($params['content_type'] ?? ContentTypeEnum::GRAPHIC);
        if (!in_array($contentType, self::SUPPORT_TYPES, true)) {
            throw new AdminResponseException('AI续写仅支持图文、文字、小说类型');
        }

        $field = (string)($params['field'] ?? '');
        if (!in_array($field, ['content', 'trial_content'], true)) {
            throw new AdminResponseException('AI续写字段参数不合法');
        }

        $current = (string)($params['content'] ?? '');
        $contextLimit = $field === 'content' ? 1500 : 800;

        return [
            'target_type' => 'article',
            'field' => $field,
            'field_text' => $field === 'content' ? '正文内容' : '试读内容',
            'title' => mb_substr($title, 0, 100),
            'content_type' => $contentType,
            'content_type_text' => ContentTypeEnum::getName($contentType),
            'current_content' => $this->toPlainTextTail($current, $contextLimit),
            'has_content' => trim($current) !== '',
        ];
    }

    /**
     * 获取默认聊天模型运行时配置。
     *
     * @param int $merchantId 商户 ID，0 表示平台模型。
     * @return array 默认聊天模型运行时。
     */
    protected function resolveChatRuntime(int $merchantId = 0): array
    {
        /** @var AiModelConfigService $service */
        $service = app()->make(AiModelConfigService::class);
        $merchantId = max(0, $merchantId);
        $runtime = $service->resolveRuntime(AiModelConfigService::TYPE_CHAT, $merchantId);
        if (!is_array($runtime) || !isset($runtime['provider'], $runtime['options'])) {
            throw new AdminResponseException(
                $merchantId > 0 ? '请先配置当前商户默认启用的 AI 对话模型' : '请先在AI模型配置中设置对话默认模型'
            );
        }

        // 生成长度调大后耗时会超过默认 30 秒，放宽单次请求超时（保留模型配置中的更大值）
        $runtime['options']['timeout'] = max(180, (int)($runtime['options']['timeout'] ?? 30));

        return $runtime;
    }

    /**
     * 组装 AI 写作 Prompt。
     *
     * @param array $payload 规范化后的请求参数。
     * @return string Prompt 文本。
     */
    protected function buildPrompt(array $payload): string
    {
        if (($payload['target_type'] ?? 'article') === 'special') {
            return $this->buildSpecialPrompt($payload);
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
你是乖乖兔多商户平台的内容编辑与运营助手。请根据输入信息生成适合后台内容发布的文案建议。

【输入信息】
{$json}

【生成要求】
1. 仅支持当前 content_type_text 对应的内容形态，不要改写内容类型。
2. description 是内容简介，控制在 80 到 180 个中文字符，适合列表和详情页展示。
3. content 是正文内容，必须输出简洁 HTML，可使用 <h2>、<h3>、<p>、<ul>、<ol>、<li>、<blockquote>、<strong> 标签，不要输出 Markdown，不要包含图片、脚本、样式和外链，控制在 1000 到 2000 个中文字符。
4. trial_content 是试读内容，必须是 content 的自然开头或精华节选，输出简洁 HTML，控制在 200 到 400 个中文字符。
5. price 是合理售价建议，单位元，数字类型，免费内容可为 0，付费建议不要超过 99999。
6. tag_suggestions 是建议标签，只用于编辑人员参考，不代表已创建标签。给出 3 到 6 个标签，每个标签包含 name、reason、text_color、bg_color、border_color，name 不超过 8 个中文字符，颜色必须是 #RRGGBB 格式且同一标签的文字、背景、边框颜色要协调。
7. 如果输入中已有 description/content/trial_content，请优先在原意基础上润色和补全，不要完全偏离标题。

【输出格式】
只输出一段紧凑 JSON，不要换行解释，不要包裹 Markdown 代码块：
{
  "description": "简介",
  "content": "<p>正文HTML</p>",
  "trial_content": "<p>试读HTML</p>",
  "price": 19.9,
  "tag_suggestions": [
    {"name": "标签", "reason": "推荐原因", "text_color": "#E65A2E", "bg_color": "#FFF0E8", "border_color": "#FFD4BE"}
  ]
}
PROMPT;
    }

    /**
     * 组装 AI 续写 Prompt。
     *
     * @param array $payload 规范化后的续写参数。
     * @return string Prompt 文本。
     */
    protected function buildContinuePrompt(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $fieldText = (string)$payload['field_text'];

        return <<<PROMPT
你是乖乖兔多商户平台的内容编辑与运营助手。请基于现有内容，为{$fieldText}续写后续段落。

【输入信息】
{$json}

【生成要求】
1. 只续写 field 字段对应的{$fieldText}，不要输出简介、试读、价格、标签等其他内容。
2. 如果 current_content 为空，说明该字段还没有内容，请以标题为主题直接生成该字段的开头部分。
3. 严格延续现有内容的文风、语气、人称与结构（段落节奏、小标题、列表等），与已有内容自然衔接；不要重复已有内容的结尾，不要复述或改写已有内容。
4. {$fieldText}续写字数：正文 content 为 300 到 600 个中文字符，试读 trial_content 为 150 到 300 个中文字符。
5. 必须输出简洁 HTML，可使用 <h2>、<h3>、<p>、<ul>、<ol>、<li>、<blockquote>、<strong> 标签，不要输出 Markdown，不要包含图片、脚本、样式和外链，不要输出文章大标题。
6. 只输出续写部分本身，不要输出"续写如下"之类的说明文字。

【输出格式】
只输出一段紧凑 JSON，不要换行解释，不要包裹 Markdown 代码块：
{
  "content": "<p>续写HTML</p>"
}
PROMPT;
    }

    /**
     * 组装专题 AI 写作 Prompt。
     *
     * @param array $payload 规范化后的请求参数。
     * @return string Prompt 文本。
     */
    protected function buildSpecialPrompt(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
你是乖乖兔多商户平台的专题运营助手。请根据输入信息生成适合商户专题发布页使用的润色建议。

【输入信息】
{$json}

【生成要求】
1. description 是专题介绍，控制在 80 到 180 个中文字符，突出专题价值、适合人群和学习/阅读收益。
2. keywords 是专题关键词，给出 5 到 10 个关键词，用中文逗号分隔，关键词应适合搜索、推荐和运营筛选。
3. price 是合理售价建议，单位元，数字类型；免费专题可为 0，付费建议不要超过 99999。
4. tag_suggestions 是建议标签，只用于编辑人员参考，不代表已创建标签。给出 3 到 6 个标签，每个标签包含 name、reason、text_color、bg_color、border_color，name 不超过 8 个中文字符，颜色必须是 #RRGGBB 格式且同一标签的文字、背景、边框颜色要协调。
5. 如果输入中已有 description 或 keywords，请优先在原意基础上润色和补全，不要完全偏离标题。

【输出格式】
只输出一段紧凑 JSON，不要换行解释，不要包裹 Markdown 代码块：
{
  "description": "专题介绍",
  "keywords": "关键词一，关键词二，关键词三",
  "price": 19.9,
  "tag_suggestions": [
    {"name": "标签", "reason": "推荐原因", "text_color": "#E65A2E", "bg_color": "#FFF0E8", "border_color": "#FFD4BE"}
  ]
}
PROMPT;
    }

    /**
     * 解析模型返回的 JSON。
     *
     * @param string $response 模型原始返回。
     * @return array 解析后的数组。
     */
    protected function decodeJsonResponse(string $response): array
    {
        $text = trim($response);
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
        $text = preg_replace('/\s*```$/', '', $text) ?? $text;

        $decoded = json_decode($text, true, 512, JSON_INVALID_UTF8_IGNORE);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $json = substr($text, $start, $end - $start + 1);
            $decoded = json_decode($json, true, 512, JSON_INVALID_UTF8_IGNORE);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $loose = $this->decodeLooseJsonResponse($text);
        if ($loose !== []) {
            return $loose;
        }

        Log::warning('[内容AI润色] 模型返回不是合法JSON: ' . mb_substr($response, 0, 500));
        throw new AdminResponseException('AI返回格式异常，请重新生成');
    }

    /**
     * 宽松解析模型返回。
     *
     * 当模型内容因为 max_tokens 或网络中断导致 JSON 未闭合时，尽量提取已返回的字段，
     * 避免整次生成失败。缺失字段会在 normalizeResult 中按空值处理。
     *
     * @param string $text 模型返回文本。
     * @return array 可解析到的字段。
     */
    protected function decodeLooseJsonResponse(string $text): array
    {
        $result = [];
        foreach (['description', 'keywords', 'content', 'trial_content'] as $field) {
            $value = $this->extractLooseStringField($text, $field);
            if ($value !== '') {
                $result[$field] = $value;
            }
        }

        $price = $this->extractLooseNumberField($text, 'price');
        if ($price !== null) {
            $result['price'] = $price;
        }

        $tags = $this->extractLooseTagSuggestions($text);
        if ($tags !== []) {
            $result['tag_suggestions'] = $tags;
        }

        if ($result !== []) {
            Log::warning('[内容AI润色] 模型返回JSON不完整，已使用宽松解析兜底');
            return $result;
        }

        return [];
    }

    /**
     * 从不完整 JSON 中提取字符串字段。
     *
     * @param string $text 原始文本。
     * @param string $field 字段名。
     * @return string 字段值。
     */
    protected function extractLooseStringField(string $text, string $field): string
    {
        $pattern = '/"' . preg_quote($field, '/') . '"\s*:\s*"/u';
        if (!preg_match($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
            return '';
        }

        $start = (int)$matches[0][1] + strlen($matches[0][0]);
        $length = strlen($text);
        $buffer = '';
        $escaped = false;

        for ($index = $start; $index < $length; $index++) {
            $char = $text[$index];
            if (!$escaped && $char === '"') {
                break;
            }
            if (!$escaped && $char === '\\') {
                $escaped = true;
                $buffer .= $char;
                continue;
            }
            $escaped = false;
            $buffer .= $char;
        }

        $decoded = json_decode('"' . $buffer . '"', true, 512, JSON_INVALID_UTF8_IGNORE);
        if (is_string($decoded)) {
            return trim($decoded);
        }

        return trim(str_replace(
            ['\\"', '\\n', '\\r', '\\t', '\\\\'],
            ['"', "\n", "\r", "\t", '\\'],
            $buffer
        ));
    }

    /**
     * 从不完整 JSON 中提取数字字段。
     *
     * @param string $text 原始文本。
     * @param string $field 字段名。
     * @return float|null 数字值。
     */
    protected function extractLooseNumberField(string $text, string $field): ?float
    {
        $pattern = '/"' . preg_quote($field, '/') . '"\s*:\s*([0-9]+(?:\.[0-9]+)?)/u';
        if (!preg_match($pattern, $text, $matches)) {
            return null;
        }

        return (float)$matches[1];
    }

    /**
     * 从不完整 JSON 中提取建议标签。
     *
     * @param string $text 原始文本。
     * @return array 建议标签列表。
     */
    protected function extractLooseTagSuggestions(string $text): array
    {
        if (!preg_match_all('/"name"\s*:\s*"([^"]{1,30})"(?:\s*,\s*"reason"\s*:\s*"([^"]{0,120})")?/u', $text, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $tags = [];
        foreach ($matches as $item) {
            $name = trim((string)($item[1] ?? ''));
            if ($name === '') {
                continue;
            }
            $tags[] = [
                'name' => $name,
                'reason' => trim((string)($item[2] ?? '')),
            ];
        }

        return $tags;
    }

    /**
     * 规范化模型结果，限制字段长度和标签数量。
     *
     * @param array $data 模型解析结果。
     * @return array 前端可直接消费的生成建议。
     */
    protected function normalizeResult(array $data): array
    {
        $price = max(0, min(99999, (float)($data['price'] ?? 0)));

        return [
            'description' => mb_substr(trim(strip_tags((string)($data['description'] ?? ''))), 0, 1000),
            'keywords' => $this->normalizeKeywords($data['keywords'] ?? ''),
            'content' => $this->sanitizeHtml((string)($data['content'] ?? '')),
            'trial_content' => $this->sanitizeHtml((string)($data['trial_content'] ?? '')),
            'price' => (float)number_format($price, 2, '.', ''),
            'tag_suggestions' => $this->normalizeTagSuggestions($data['tag_suggestions'] ?? ($data['tags'] ?? [])),
        ];
    }

    /**
     * 规范化模型返回的关键词。
     */
    protected function normalizeKeywords(mixed $keywords): string
    {
        if (is_array($keywords)) {
            $keywords = implode('，', array_filter(array_map(static fn($item): string => trim((string)$item), $keywords)));
        }

        return mb_substr(trim(strip_tags((string)$keywords)), 0, 120);
    }

    /**
     * 规范化建议标签。
     *
     * @param mixed $tags 模型返回的标签数据。
     * @return array<int, array{name:string,reason:string,text_color:string,bg_color:string,border_color:string}> 建议标签列表。
     */
    protected function normalizeTagSuggestions(mixed $tags): array
    {
        if (!is_array($tags)) {
            return [];
        }

        $result = [];
        foreach ($tags as $item) {
            if (is_string($item)) {
                $name = $item;
                $reason = '';
                $colors = [];
            } elseif (is_array($item)) {
                $name = (string)($item['name'] ?? ($item['label'] ?? ''));
                $reason = (string)($item['reason'] ?? '');
                $colors = $item;
            } else {
                continue;
            }

            $name = mb_substr(trim(strip_tags($name)), 0, 8);
            if ($name === '') {
                continue;
            }

            $result[] = [
                'name' => $name,
                'reason' => mb_substr(trim(strip_tags($reason)), 0, 80),
                'text_color' => $this->normalizeTagColor($colors['text_color'] ?? '', '#E65A2E'),
                'bg_color' => $this->normalizeTagColor($colors['bg_color'] ?? '', '#FFF0E8'),
                'border_color' => $this->normalizeTagColor($colors['border_color'] ?? '', '#FFD4BE'),
            ];

            if (count($result) >= 8) {
                break;
            }
        }

        return $result;
    }

    /**
     * 规范化模型返回的标签颜色。
     *
     * @param mixed $color 原始颜色。
     * @param string $fallback 兜底颜色。
     * @return string 十六进制颜色。
     */
    protected function normalizeTagColor(mixed $color, string $fallback): string
    {
        $value = trim((string)$color);
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            return strtoupper($value);
        }

        return $fallback;
    }

    /**
     * 将 HTML 转为精简纯文本。
     *
     * @param string $html 原始 HTML。
     * @param int $limit 最大字符数。
     * @return string 纯文本。
     */
    protected function toPlainText(string $html, int $limit): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');
        return mb_substr($text, 0, max(1, $limit));
    }

    /**
     * 将 HTML 转为精简纯文本并保留末尾片段（续写上下文用）。
     *
     * @param string $html 原始 HTML。
     * @param int $limit 最大保留字符数（从末尾截取）。
     * @return string 纯文本末尾片段。
     */
    protected function toPlainTextTail(string $html, int $limit): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');
        if ($text === '') {
            return '';
        }

        return mb_substr($text, -max(1, $limit));
    }

    /**
     * 清理模型返回的正文 HTML。
     *
     * @param string $html 模型返回 HTML。
     * @return string 清理后的 HTML。
     */
    protected function sanitizeHtml(string $html): string
    {
        return app()->make(ArticleHtmlSanitizer::class)->clean($html);
    }
}
