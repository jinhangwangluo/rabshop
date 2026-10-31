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

namespace app\common\service\setting;

use app\common\exception\AdminResponseException;
use app\common\model\setting\system\ConfigTemplate;
use app\common\service\BaseService;
use app\common\service\cs\AiModelConfigService;
use Rabbit\service\ai\chat\ChatService;
use think\facade\Log;

/**
 * 配置模板 AI 设计服务。
 *
 * 根据模板预定义字段、当前画布结构和多轮对话生成表单设计器可直接应用的 JSON。
 */
class ConfigTemplateAiService extends BaseService
{
    /**
     * 表单叶子组件类型。
     */
    private const FIELD_TYPES = ['input', 'textarea', 'number', 'switch', 'radio', 'checkbox', 'upload'];

    /**
     * 布局组件类型。
     */
    private const LAYOUT_TYPES = ['card', 'tabs', 'alert'];

    /**
     * 设置关联模型。
     *
     * @return string 配置模板模型类名。
     */
    protected function setModel(): string
    {
        return ConfigTemplate::class;
    }

    /**
     * 流式生成配置模板设计草稿。
     *
     * @param array $params 请求参数，包含 id、prompt、messages、current_fields。
     * @param callable $emit SSE 事件回调。
     */
    public function streamGenerate(array $params, callable $emit): void
    {
        try {
            $payload = $this->normalizePayload($params);
            $runtime = $this->resolveChatRuntime();
            $prompt = $this->buildPrompt($payload);
        } catch (AdminResponseException $exception) {
            $emit([
                'type' => 'error',
                'message' => $exception->getMessage(),
            ]);
            return;
        }

        $emit([
            'type' => 'start',
            'message' => '开始生成配置草稿',
        ]);

        $fullContent = $this->streamAiResponse($prompt, $runtime, $emit);
        if ($fullContent === null) {
            return;
        }
        if (trim($fullContent) === '') {
            $emit([
                'type' => 'error',
                'message' => 'AI未返回内容，请稍后重试',
            ]);
            return;
        }

        try {
            $draft = $this->normalizeDraft($this->decodeJsonResponse($fullContent), $payload);
        } catch (AdminResponseException $exception) {
            $emit([
                'type' => 'error',
                'message' => $exception->getMessage(),
            ]);
            return;
        }

        $emit([
            'type' => 'draft',
            'content' => $draft['content'],
            'fields' => $draft['fields'],
            'new_field_keys' => $draft['new_field_keys'],
            'warnings' => $draft['warnings'],
        ]);
        $emit([
            'type' => 'done',
            'content' => $draft['content'],
            'fields' => $draft['fields'],
            'new_field_keys' => $draft['new_field_keys'],
            'warnings' => $draft['warnings'],
            'done' => true,
        ]);
    }

    /**
     * 规范化请求参数。
     *
     * @param array $params 原始请求参数。
     * @return array Prompt 上下文。
     */
    protected function normalizePayload(array $params): array
    {
        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) {
            throw new AdminResponseException('请选择要配置的模板');
        }

        $prompt = mb_substr(trim((string)($params['prompt'] ?? '')), 0, 1200);
        if ($prompt === '') {
            throw new AdminResponseException('请输入配置需求');
        }

        $template = $this->get($id);
        if (!$template) {
            throw new AdminResponseException('配置模板不存在');
        }

        $definedFields = $this->normalizeDefinedFields($template->fields);

        return [
            'id' => $id,
            'prompt' => $prompt,
            'template' => [
                'config_name' => (string)($template->config_name ?? ''),
                'config_desc' => (string)($template->config_desc ?? ''),
                'config_key' => (string)($template->config_key ?? ''),
            ],
            'defined_fields' => $definedFields,
            'defined_field_keys' => array_column($definedFields, 'field'),
            'messages' => $this->normalizeMessages($params['messages'] ?? []),
            'current_fields' => $this->normalizeCurrentFields($params['current_fields'] ?? []),
        ];
    }

    /**
     * 规范化模板预定义字段。
     *
     * @param mixed $fields 模型中的 fields 字段。
     * @return array<int, array{field:string,label:string,type:string}>
     */
    protected function normalizeDefinedFields(mixed $fields): array
    {
        if (!is_array($fields)) {
            return [];
        }

        $result = [];
        foreach ($fields as $item) {
            if (!is_array($item)) {
                continue;
            }
            $field = $this->normalizeFieldKey($item['field'] ?? '');
            if ($field === '') {
                continue;
            }
            $type = $this->normalizeFieldType((string)($item['type'] ?? 'input'));
            $result[] = [
                'field' => $field,
                'label' => mb_substr(trim(strip_tags((string)($item['label'] ?? $field))), 0, 80),
                'type' => $type,
            ];
        }

        return $result;
    }

    /**
     * 规范化多轮对话历史。
     *
     * @param mixed $messages 原始消息数组。
     * @return array<int, array{role:string,content:string}>
     */
    protected function normalizeMessages(mixed $messages): array
    {
        if (!is_array($messages)) {
            return [];
        }

        $result = [];
        foreach (array_slice($messages, -12) as $message) {
            if (!is_array($message)) {
                continue;
            }
            $role = (string)($message['role'] ?? '');
            if (!in_array($role, ['user', 'assistant'], true)) {
                continue;
            }
            $content = mb_substr(trim(strip_tags((string)($message['content'] ?? ''))), 0, 1200);
            if ($content === '') {
                continue;
            }
            $result[] = [
                'role' => $role,
                'content' => $content,
            ];
        }

        return $result;
    }

    /**
     * 规范化当前画布，仅保留可给模型参考的安全字段。
     *
     * @param mixed $fields 当前画布配置。
     * @return array
     */
    protected function normalizeCurrentFields(mixed $fields): array
    {
        if (!is_array($fields)) {
            return [];
        }

        return array_slice($this->sanitizeReferenceFields($fields), 0, 80);
    }

    /**
     * 递归清理参考字段。
     *
     * @param array $fields 字段列表。
     * @return array
     */
    protected function sanitizeReferenceFields(array $fields): array
    {
        $result = [];
        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }
            $type = (string)($field['type'] ?? '');
            if (!in_array($type, array_merge(self::FIELD_TYPES, self::LAYOUT_TYPES), true)) {
                continue;
            }
            $props = is_array($field['props'] ?? null) ? $field['props'] : [];
            $item = [
                'type' => $type,
                'label' => mb_substr(trim(strip_tags((string)($field['label'] ?? ''))), 0, 60),
                'props' => [
                    'field' => $this->normalizeFieldKey($props['field'] ?? ''),
                    'label' => mb_substr(trim(strip_tags((string)($props['label'] ?? ''))), 0, 80),
                    'title' => mb_substr(trim(strip_tags((string)($props['title'] ?? ''))), 0, 80),
                    'description' => mb_substr(trim(strip_tags((string)($props['description'] ?? ''))), 0, 200),
                ],
            ];

            if ($type === 'card') {
                $children = is_array($props['children'] ?? null) ? $props['children'] : [];
                $item['props']['children'] = $this->sanitizeReferenceFields($children);
            }
            if ($type === 'tabs') {
                $tabs = [];
                $rawTabs = is_array($props['tabs'] ?? null) ? $props['tabs'] : [];
                foreach ($rawTabs as $tab) {
                    if (!is_array($tab)) {
                        continue;
                    }
                    $children = is_array($tab['children'] ?? null) ? $tab['children'] : [];
                    $tabs[] = [
                        'name' => mb_substr(trim(strip_tags((string)($tab['name'] ?? '标签页'))), 0, 40),
                        'children' => $this->sanitizeReferenceFields($children),
                    ];
                }
                $item['props']['tabs'] = $tabs;
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * 获取默认对话模型运行时配置。
     *
     * @return array 默认聊天模型运行时。
     */
    protected function resolveChatRuntime(): array
    {
        /** @var AiModelConfigService $service */
        $service = app()->make(AiModelConfigService::class);
        $runtime = $service->resolveRuntime(AiModelConfigService::TYPE_CHAT);
        if (!is_array($runtime) || !isset($runtime['provider'], $runtime['options'])) {
            throw new AdminResponseException('请先在AI模型配置中设置对话默认模型');
        }

        return $runtime;
    }

    /**
     * 组装配置设计 Prompt。
     *
     * @param array $payload 规范化上下文。
     * @return string Prompt 文本。
     */
    protected function buildPrompt(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $fieldTypes = implode('、', self::FIELD_TYPES);
        $layoutTypes = implode('、', self::LAYOUT_TYPES);

        return <<<PROMPT
你是乖乖兔多商户平台的后台配置表单设计助手。请根据管理员输入和上下文，生成表单设计器可直接应用的 JSON 草稿。

【上下文 JSON】
{$json}

【组件约束】
1. 只能使用字段组件：{$fieldTypes}。
2. 只能使用布局组件：{$layoutTypes}。
3. card 和 tabs 只能作为顶层布局组件；card.children、tabs.tabs[].children 内只能放字段组件或 alert，不能再嵌套 card/tabs。
4. 每个组件必须包含 type、label、props；props 必须是对象。
5. 字段组件 props.field 必填且唯一；alert、card、tabs 不需要 props.field。
6. 优先使用 defined_fields 中的 field、label、type；确需新增字段时可以新增，但字段名必须为小写字母、数字、下划线，并在 props.aiNewField 设置 true。
7. 每个字段的 label、placeholder、description 要清晰，适合后台运营人员理解。
8. radio/checkbox 至少提供 2 个 options，格式为 [{"label":"选项","value":"1"}]。
9. switch 使用 activeValue=1、inactiveValue=0，defaultValue 默认 0。
10. upload 默认 accept="image/*"、limit=1。

【输出格式】
只输出一段紧凑 JSON，不要 Markdown，不要解释：
{
  "content": "本次生成或修改说明",
  "fields": [
    {"type":"input","label":"文本框","props":{"field":"site_name","label":"站点名称","placeholder":"请输入站点名称","maxlength":200,"required":false,"disabled":false,"defaultValue":"","description":""}}
  ]
}
PROMPT;
    }

    /**
     * 调用 AI 流式回复。
     *
     * @param string $prompt Prompt 文本。
     * @param array $runtime 模型运行时配置。
     * @param callable $emit SSE 事件回调。
     * @return string|null 完整模型回复，流式调用失败时返回 null。
     */
    protected function streamAiResponse(string $prompt, array $runtime, callable $emit): ?string
    {
        /** @var ChatService $chatService */
        $chatService = app()->make(ChatService::class);
        $sentByHandler = false;
        $fullContent = '';

        try {
            $chunks = $chatService->replyStream(
                $prompt,
                [],
                [
                    'max_tokens' => 3200,
                    'temperature' => 0.25,
                    'direct_output' => false,
                    'stream_fallback' => false,
                ],
                (string)$runtime['provider'],
                (array)$runtime['options']
            );

            if (!$sentByHandler) {
                foreach ($chunks as $chunk) {
                    if (is_array($chunk)) {
                        $this->appendChunk($chunk, $fullContent, $emit);
                    }
                }
            }
        } catch (\Throwable $exception) {
            Log::error('[配置模板AI设计] 对话模型调用失败: ' . $exception->getMessage());
            $emit([
                'type' => 'error',
                'message' => 'AI生成失败，请检查默认对话模型配置',
            ]);
            return null;
        }

        return $fullContent;
    }

    /**
     * 追加模型分片并透传给前端。
     *
     * @param array $chunk 模型分片。
     * @param string $fullContent 完整内容引用。
     * @param callable $emit SSE 事件回调。
     */
    protected function appendChunk(array $chunk, string &$fullContent, callable $emit): void
    {
        $delta = (string)($chunk['delta'] ?? '');
        $done = (bool)($chunk['done'] ?? false);

        if ($delta !== '') {
            $fullContent .= $delta;
            $emit([
                'type' => 'chunk',
                'delta' => $delta,
                'done' => false,
            ]);
            return;
        }

        if ($done && isset($chunk['content']) && is_string($chunk['content']) && $chunk['content'] !== '') {
            $fullContent = $chunk['content'];
        }
    }

    /**
     * 解析模型返回 JSON。
     *
     * @param string $response 模型原始回复。
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

        Log::warning('[配置模板AI设计] 模型返回不是合法JSON: ' . mb_substr($response, 0, 500));
        throw new AdminResponseException('AI返回格式异常，请重新生成');
    }

    /**
     * 规范化 AI 草稿。
     *
     * @param array $data 模型解析结果。
     * @param array $payload 请求上下文。
     * @return array{content:string,fields:array,new_field_keys:array,warnings:array}
     */
    protected function normalizeDraft(array $data, array $payload): array
    {
        $rawFields = $data['fields'] ?? [];
        if (!is_array($rawFields)) {
            throw new AdminResponseException('AI返回的字段结构不正确，请重新生成');
        }

        $definedMap = [];
        foreach ($payload['defined_fields'] as $field) {
            $definedMap[$field['field']] = $field;
        }

        $usedKeys = [];
        $newFieldKeys = [];
        $warnings = [];
        $fields = $this->normalizeGeneratedFields($rawFields, $definedMap, $usedKeys, $newFieldKeys, $warnings, true);
        if ($fields === []) {
            throw new AdminResponseException('AI未生成可用字段，请换个提示词重试');
        }

        return [
            'content' => mb_substr(trim(strip_tags((string)($data['content'] ?? '已生成配置草稿'))), 0, 500),
            'fields' => $fields,
            'new_field_keys' => array_values(array_unique($newFieldKeys)),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * 递归规范化生成字段。
     *
     * @param array $rawFields 原始字段列表。
     * @param array $definedMap 预定义字段映射。
     * @param array $usedKeys 已使用字段标识。
     * @param array $newFieldKeys 新增字段标识。
     * @param array $warnings 警告列表。
     * @param bool $isTopLevel 是否顶层。
     * @return array
     */
    protected function normalizeGeneratedFields(
        array $rawFields,
        array $definedMap,
        array &$usedKeys,
        array &$newFieldKeys,
        array &$warnings,
        bool  $isTopLevel
    ): array
    {
        $result = [];
        foreach ($rawFields as $rawField) {
            if (!is_array($rawField)) {
                continue;
            }

            $type = (string)($rawField['type'] ?? '');
            if (!in_array($type, array_merge(self::FIELD_TYPES, self::LAYOUT_TYPES), true)) {
                continue;
            }
            if (!$isTopLevel && in_array($type, ['card', 'tabs'], true)) {
                $warnings[] = '已忽略嵌套布局组件';
                continue;
            }

            $props = is_array($rawField['props'] ?? null) ? $rawField['props'] : [];
            if (in_array($type, self::FIELD_TYPES, true)) {
                $item = $this->normalizeGeneratedField($type, $rawField, $props, $definedMap, $usedKeys, $newFieldKeys, $warnings);
                if ($item !== []) {
                    $result[] = $item;
                }
                continue;
            }

            if ($type === 'card') {
                $rawChildren = is_array($props['children'] ?? null)
                    ? $props['children']
                    : (is_array($rawField['children'] ?? null) ? $rawField['children'] : []);
                $children = $this->normalizeGeneratedFields($rawChildren, $definedMap, $usedKeys, $newFieldKeys, $warnings, false);
                $shadow = (string)($props['shadow'] ?? 'hover');
                $shadow = in_array($shadow, ['always', 'hover', 'never'], true) ? $shadow : 'hover';
                $result[] = [
                    'type' => 'card',
                    'label' => '卡片',
                    'props' => [
                        'title' => mb_substr(trim(strip_tags((string)($props['title'] ?? ($rawField['label'] ?? '卡片标题')))), 0, 80),
                        'content' => mb_substr(trim(strip_tags((string)($props['content'] ?? ''))), 0, 200),
                        'shadow' => $shadow,
                        'children' => $children,
                    ],
                ];
                continue;
            }

            if ($type === 'tabs') {
                $tabs = [];
                $rawTabs = is_array($props['tabs'] ?? null)
                    ? $props['tabs']
                    : (is_array($rawField['tabs'] ?? null) ? $rawField['tabs'] : []);
                foreach ($rawTabs as $tab) {
                    if (!is_array($tab)) {
                        continue;
                    }
                    $rawChildren = is_array($tab['children'] ?? null) ? $tab['children'] : [];
                    $tabs[] = [
                        'name' => mb_substr(trim(strip_tags((string)($tab['name'] ?? '标签页'))), 0, 40),
                        'children' => $this->normalizeGeneratedFields($rawChildren, $definedMap, $usedKeys, $newFieldKeys, $warnings, false),
                    ];
                }
                if ($tabs === []) {
                    $tabs[] = ['name' => '标签一', 'children' => []];
                }
                $tabType = (string)($props['tabType'] ?? 'border-card');
                $tabType = in_array($tabType, ['border-card', 'card'], true) ? $tabType : 'border-card';
                $result[] = [
                    'type' => 'tabs',
                    'label' => '标签页',
                    'props' => [
                        'label' => mb_substr(trim(strip_tags((string)($props['label'] ?? ($rawField['label'] ?? '标签页')))), 0, 80),
                        'tabType' => $tabType,
                        'activeTab' => '',
                        'tabs' => $tabs,
                    ],
                ];
                continue;
            }

            $result[] = [
                'type' => 'alert',
                'label' => '温馨提示',
                'props' => [
                    'title' => mb_substr(trim(strip_tags((string)($props['title'] ?? ($rawField['label'] ?? '提示信息')))), 0, 80),
                    'description' => mb_substr(trim(strip_tags((string)($props['description'] ?? ''))), 0, 300),
                    'alertType' => in_array(($props['alertType'] ?? 'info'), ['success', 'warning', 'info', 'error'], true) ? $props['alertType'] : 'info',
                ],
            ];
        }

        return $result;
    }

    /**
     * 规范化单个字段组件。
     *
     * @param string $type 组件类型。
     * @param array $rawField 原始字段。
     * @param array $props 原始属性。
     * @param array $definedMap 预定义字段映射。
     * @param array $usedKeys 已使用字段标识。
     * @param array $newFieldKeys 新增字段标识。
     * @param array $warnings 警告列表。
     * @return array
     */
    protected function normalizeGeneratedField(
        string $type,
        array  $rawField,
        array  $props,
        array  $definedMap,
        array  &$usedKeys,
        array  &$newFieldKeys,
        array  &$warnings
    ): array
    {
        $fieldKey = $this->normalizeFieldKey($props['field'] ?? '');
        if ($fieldKey === '') {
            $warnings[] = '已忽略字段标识为空的组件';
            return [];
        }
        if (in_array($fieldKey, $usedKeys, true)) {
            $warnings[] = '已忽略重复字段：' . $fieldKey;
            return [];
        }
        $usedKeys[] = $fieldKey;

        $defined = $definedMap[$fieldKey] ?? null;
        $isNewField = $defined === null;
        if ($isNewField) {
            $newFieldKeys[] = $fieldKey;
        } elseif (!empty($defined['type'])) {
            $type = $this->normalizeFieldType((string)$defined['type']);
        }

        $label = $defined['label'] ?? ($props['label'] ?? ($rawField['label'] ?? $fieldKey));
        $baseProps = $this->defaultProps($type, $fieldKey, (string)$label);
        $mergedProps = array_merge($baseProps, $this->filterPropsByType($type, $props));
        $mergedProps['field'] = $fieldKey;
        $mergedProps['label'] = mb_substr(trim(strip_tags((string)$label)), 0, 80);
        if ($isNewField) {
            $mergedProps['aiNewField'] = true;
        }

        return [
            'type' => $type,
            'label' => $this->getTypeLabel($type),
            'props' => $mergedProps,
        ];
    }

    /**
     * 按类型过滤并规范化 props。
     *
     * @param string $type 组件类型。
     * @param array $props 原始属性。
     * @return array
     */
    protected function filterPropsByType(string $type, array $props): array
    {
        $common = [
            'placeholder' => mb_substr(trim(strip_tags((string)($props['placeholder'] ?? ''))), 0, 120),
            'required' => (bool)($props['required'] ?? false),
            'disabled' => (bool)($props['disabled'] ?? false),
            'defaultValue' => $props['defaultValue'] ?? '',
            'description' => mb_substr(trim(strip_tags((string)($props['description'] ?? ''))), 0, 200),
        ];

        if ($type === 'input') {
            return array_merge($common, [
                'maxlength' => max(1, min(1000, (int)($props['maxlength'] ?? 200))),
                'showCopy' => (bool)($props['showCopy'] ?? false),
                'showRandom' => (bool)($props['showRandom'] ?? false),
                'randomLength' => max(1, min(128, (int)($props['randomLength'] ?? 16))),
                'fetchEnabled' => (bool)($props['fetchEnabled'] ?? false),
                'fetchButtonText' => mb_substr(trim(strip_tags((string)($props['fetchButtonText'] ?? '获取'))), 0, 20),
                'fetchRequest' => is_array($props['fetchRequest'] ?? null) ? $props['fetchRequest'] : ['url' => '', 'method' => 'get', 'paramsJson' => '', 'dataJson' => ''],
                'fetchValuePath' => mb_substr(trim(strip_tags((string)($props['fetchValuePath'] ?? 'data'))), 0, 100),
            ]);
        }

        if ($type === 'textarea') {
            return array_merge($common, [
                'rows' => max(1, min(12, (int)($props['rows'] ?? 3))),
            ]);
        }

        if ($type === 'number') {
            return array_merge($common, [
                'min' => (float)($props['min'] ?? 0),
                'max' => (float)($props['max'] ?? 99999),
                'step' => (float)($props['step'] ?? 1),
                'defaultValue' => is_numeric($props['defaultValue'] ?? null) ? (float)$props['defaultValue'] : null,
            ]);
        }

        if ($type === 'switch') {
            return array_merge($common, [
                'activeText' => mb_substr(trim(strip_tags((string)($props['activeText'] ?? ''))), 0, 20),
                'inactiveText' => mb_substr(trim(strip_tags((string)($props['inactiveText'] ?? ''))), 0, 20),
                'activeValue' => 1,
                'inactiveValue' => 0,
                'defaultValue' => (int)($props['defaultValue'] ?? 0) === 1 ? 1 : 0,
            ]);
        }

        if (in_array($type, ['radio', 'checkbox'], true)) {
            $options = $this->normalizeOptions($props['options'] ?? []);
            return array_merge($common, [
                'options' => $options,
                'defaultValue' => $type === 'checkbox' ? [] : (string)($options[0]['value'] ?? ''),
                'linkage' => [],
            ]);
        }

        return array_merge($common, [
            'buttonText' => mb_substr(trim(strip_tags((string)($props['buttonText'] ?? '点击上传'))), 0, 30),
            'accept' => mb_substr(trim(strip_tags((string)($props['accept'] ?? 'image/*'))), 0, 80),
            'limit' => max(1, min(20, (int)($props['limit'] ?? 1))),
        ]);
    }

    /**
     * 默认字段 props。
     *
     * @param string $type 组件类型。
     * @param string $field 字段标识。
     * @param string $label 字段标题。
     * @return array
     */
    protected function defaultProps(string $type, string $field, string $label): array
    {
        $common = [
            'field' => $field,
            'label' => $label,
            'placeholder' => '请输入' . $label,
            'required' => false,
            'disabled' => false,
            'defaultValue' => '',
            'description' => '',
        ];

        return match ($type) {
            'textarea' => array_merge($common, ['rows' => 3]),
            'number' => array_merge($common, ['min' => 0, 'max' => 99999, 'step' => 1, 'defaultValue' => null]),
            'switch' => array_merge($common, ['activeText' => '', 'inactiveText' => '', 'activeValue' => 1, 'inactiveValue' => 0, 'defaultValue' => 0]),
            'radio' => array_merge($common, ['options' => [['label' => '开启', 'value' => '1'], ['label' => '关闭', 'value' => '0']], 'linkage' => []]),
            'checkbox' => array_merge($common, ['options' => [['label' => '选项一', 'value' => '1'], ['label' => '选项二', 'value' => '2']], 'defaultValue' => [], 'linkage' => []]),
            'upload' => array_merge($common, ['buttonText' => '点击上传', 'accept' => 'image/*', 'limit' => 1]),
            default => array_merge($common, ['maxlength' => 200, 'showCopy' => false, 'showRandom' => false, 'randomLength' => 16, 'fetchEnabled' => false, 'fetchButtonText' => '获取', 'fetchRequest' => ['url' => '', 'method' => 'get', 'paramsJson' => '', 'dataJson' => ''], 'fetchValuePath' => 'data']),
        };
    }

    /**
     * 规范化选项列表。
     *
     * @param mixed $options 原始选项。
     * @return array<int, array{label:string,value:string}>
     */
    protected function normalizeOptions(mixed $options): array
    {
        $result = [];
        if (is_array($options)) {
            foreach ($options as $index => $option) {
                if (is_array($option)) {
                    $label = mb_substr(trim(strip_tags((string)($option['label'] ?? ('选项' . ($index + 1))))), 0, 40);
                    $value = mb_substr(trim(strip_tags((string)($option['value'] ?? (string)($index + 1)))), 0, 40);
                } else {
                    $label = mb_substr(trim(strip_tags((string)$option)), 0, 40);
                    $value = (string)($index + 1);
                }
                if ($label !== '' && $value !== '') {
                    $result[] = ['label' => $label, 'value' => $value];
                }
            }
        }

        if (count($result) < 2) {
            return [
                ['label' => '是', 'value' => '1'],
                ['label' => '否', 'value' => '0'],
            ];
        }

        return array_slice($result, 0, 20);
    }

    /**
     * 规范化字段类型。
     *
     * @param string $type 原始类型。
     * @return string 设计器支持的字段类型。
     */
    protected function normalizeFieldType(string $type): string
    {
        return in_array($type, self::FIELD_TYPES, true) ? $type : 'input';
    }

    /**
     * 规范化字段标识。
     *
     * @param mixed $field 原始字段标识。
     * @return string 字段标识。
     */
    protected function normalizeFieldKey(mixed $field): string
    {
        $value = strtolower(trim((string)$field));
        $value = preg_replace('/[^a-z0-9_]/', '_', $value) ?? '';
        $value = trim(preg_replace('/_+/', '_', $value) ?? '', '_');

        return mb_substr($value, 0, 80);
    }

    /**
     * 获取组件显示名称。
     *
     * @param string $type 组件类型。
     * @return string 中文名称。
     */
    protected function getTypeLabel(string $type): string
    {
        return [
            'input' => '文本框',
            'textarea' => '多行文本',
            'number' => '数字',
            'switch' => '开关',
            'radio' => '单选',
            'checkbox' => '多选',
            'upload' => '上传',
        ][$type] ?? '文本框';
    }
}
