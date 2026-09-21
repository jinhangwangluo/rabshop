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

use app\common\enum\AuditStatusEnum;
use app\common\enum\ConfigKeyEnum;
use app\common\enum\ContentTypeEnum;
use app\common\exception\AdminResponseException;
use app\common\job\content\ArticleImageLocalizeJob;
use app\common\model\content\Article;
use app\common\model\content\ContentComment;
use app\common\service\merchant\MerchantService;
use app\common\model\order\Order;
use app\common\service\order\UserPurchaseRecordService;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\message\BackendMessageEventService;
use app\common\service\message\BackendMessageService;
use app\common\service\SensitiveFilterTrait;
use app\common\service\user\UserFavoriteService;
use app\common\service\user\UserLikeService;
use app\common\service\user\UserMemberService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Log;

/**
 * 内容服务（支持图文/文字/小说/图片/音频/视频）
 * 内容发布、审核及移动端展示服务。
 *
 * @package app\common\service\content
 */
class ArticleService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;
    use SensitiveFilterTrait;

    /**
     * 快捷编辑允许更新的内容主表字段。
     */
    private const QUICK_UPDATE_MAIN_FIELDS = [
        'title',
        'article_category_id',
        'author',
        'source',
        'keywords',
        'description',
        'cover',
        'content_type',
        'media_url',
        'media_duration',
        'trial_media_url',
        'price',
        'original_price',
        'gift_points',
        'is_trial',
        'word_count',
        'virtual_learn_count',
        'sort',
        'is_hot',
        'is_banner',
        'status',
    ];

    /**
     * 快捷编辑允许更新的内容扩展表字段。
     */
    private const QUICK_UPDATE_CONTENT_FIELDS = ['content', 'trial_content', 'images', 'trial_images'];

    /**
     * 商户快捷编辑这些字段后需要重新审核。
     */
    private const QUICK_UPDATE_AUDIT_FIELDS = [
        'title',
        'cover',
        'content',
        'trial_content',
        'images',
        'trial_images',
    ];

    /**
     * 快捷编辑字符串字段长度限制。
     */
    private const QUICK_UPDATE_STRING_LIMITS = [
        'title' => 100,
        'author' => 30,
        'source' => 50,
        'keywords' => 100,
        'description' => 1000,
        'cover' => 255,
        'media_url' => 500,
        'trial_media_url' => 500,
    ];

    /**
     * 设置对应的模型
     *
     * @return string 当前服务对应的模型类名。
     */
    protected function setModel(): string
    {
        return Article::class;
    }

    /**
     * 在所有文章 HTML 读写边界统一执行安全清洗。
     *
     * @param string $html 原始 HTML。
     * @return string 清洗后的 HTML。
     */
    protected function sanitizeRichContent(string $html): string
    {
        return app()->make(ArticleHtmlSanitizer::class)->clean($html);
    }

    /**
     * 获取内容列表并附加标签。
     *
     * @param array $params 内容列表的筛选和分页参数。
     * @param array|string $fields 本次查询需要返回的字段集合。
     * @param array $with 需要预加载的关联名称。
     * @param string|array|null $sort 业务排序字段、方向或预定义排序规则。
     * @return mixed 按筛选和分页规则组装的资源列表结果。
     */
    public function getResourceList(array $params = [], array|string $fields = [], array $with = [], string|array|null $sort = null)
    {
        $result = $this->getDefaultList($params, $fields ?: ['*'], $with, $sort)->toArray();
        $result['list'] = app()->make(TagService::class)->attachTagsToList(
            $result['list'] ?? [],
            TagService::RELATION_TYPE_ARTICLE
        );
        return $result;
    }

    /**
     * 校验内容商户归属
     *
     * @param int $merId 当前业务所属商户标识。
     * @param int $id 内容记录标识。
     * @param string $label 用于校验错误提示或展示的中文名称。
     * @return void 无返回值。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function checkMerchantOwner(int $merId, int $id, string $label = '内容'): void
    {
        $article = $this->get($id);
        if (!$article) {
            throw new AdminResponseException($label . '不存在');
        }
        if ($article['mer_id'] != $merId) {
            throw new AdminResponseException('无权操作该' . $label);
        }
    }

    /**
     * 获取内容详情（包含对应类型的填充内容）
     *
     * @param int|array $id 内容记录标识或资源查询条件。
     * @return array|null 内容主表及对应类型的扩展详情；不存在时为空。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function resourceRead(int|array $id): ?array
    {
        $article = $this->get($id);
        if (!$article) {
            return null;
        }

        $result = $article->toArray();
        $contentType = (int)($result['content_type'] ?? ContentTypeEnum::GRAPHIC);
        $articleContentService = app()->make(ArticleContentService::class);
        $content = $articleContentService->findByArticleId((int)$result['id']);

        $result['content'] = $content
            ? $this->sanitizeRichContent((string)($content['content'] ?? ''))
            : '';
        $result['trial_content'] = $content
            ? $this->sanitizeRichContent((string)($content['trial_content'] ?? ''))
            : '';
        $result['images'] = $content && is_array($content['images'] ?? null) ? $content['images'] : [];
        $result['trial_images'] = $content && is_array($content['trial_images'] ?? null) ? $content['trial_images'] : [];

        // 小说类型：返回章节列表
        if (ContentTypeEnum::isNovel($contentType)) {
            $result['chapters'] = $articleContentService->getListByArticleId((int)$result['id']);
        }

        return app()->make(TagService::class)->attachTagsToDetail($result, TagService::RELATION_TYPE_ARTICLE);
    }

    /**
     * 删除商户内容
     *
     * @param int $merId 当前业务所属商户标识。
     * @param int $id 内容记录标识。
     * @return bool 内容及其关联数据是否删除成功。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function deleteMerchantArticle(int $merId, int $id): bool
    {
        $article = $this->get($id);
        if (!$article) {
            throw new AdminResponseException('内容不存在');
        }
        if ($article['mer_id'] != $merId) {
            throw new AdminResponseException('无权操作该内容');
        }

        return $this->deleteContentWithRelations($id);
    }

    /**
     * 新增内容（平台端使用）
     *
     * @param array $data 待校验或保存的内容主表、扩展字段及标签数据。
     * @return array 新增后的内容主表和扩展数据。
     * @throws AdminResponseException
     */
    public function resourceSave(array $data): array
    {
        [$tagIds, $syncTags] = $this->pullTagIds($data);
        if (($data['mer_id'] ?? 0) == 0) {
            $data['audit_status'] = 1;
        } else {
            $data['audit_status'] = 0;
        }
        $result = $this->saveContentWithType($data);
        $articleId = (int)($result['id'] ?? 0);
        if ($syncTags && $articleId > 0) {
            $ownerId = (int)($result['mer_id'] ?? ($data['mer_id'] ?? 0));
            app()->make(TagRelationService::class)->syncRelation(
                TagService::RELATION_TYPE_ARTICLE,
                $articleId,
                $tagIds,
                $ownerId,
                TagService::TAG_TYPE_CONTENT
            );
            $result = $this->resourceRead($articleId) ?? $result;
        }
        if ((int)($result['mer_id'] ?? 0) > 0 && $articleId > 0) {
            app()->make(BackendMessageEventService::class)->auditSubmitted(
                BackendMessageService::SUB_TYPE_CONTENT_AUDIT,
                TagService::RELATION_TYPE_ARTICLE,
                $articleId,
                (int)$result['mer_id'],
                (string)($result['title'] ?? '内容')
            );
        }
        return $result;
    }

    /**
     * 更新内容（平台端使用）
     *
     * @param int|array $id 内容标识，按整数标识处理；联合类型仅沿用资源接口签名。
     * @param array $data 待校验或保存的内容主表、扩展字段及标签数据。
     * @return true 更新成功时返回真；失败时抛出业务异常。
     * @throws AdminResponseException
     */
    public function resourceUpdate(int|array $id, array $data)
    {
        [$tagIds, $syncTags] = $this->pullTagIds($data);
        //商户的内容
        if (!empty($data['mer_id'])) {
            $article = $this->get($id);
            if (!$article) {
                throw new AdminResponseException('内容不存在');
            }
            if ($article['mer_id'] != $data['mer_id']) {
                throw new AdminResponseException('无权操作该内容');
            }

            // 修改后重新变为待审核状态
            $data['audit_status'] = 0;
        }
        $this->updateContentWithType((int)$id, $data);
        $article = $this->get((int)$id);
        if ($syncTags) {
            $ownerId = $article ? (int)($article['mer_id'] ?? 0) : 0;
            app()->make(TagRelationService::class)->syncRelation(
                TagService::RELATION_TYPE_ARTICLE,
                (int)$id,
                $tagIds,
                $ownerId,
                TagService::TAG_TYPE_CONTENT
            );
        }
        if ($article && (int)($article['mer_id'] ?? 0) > 0) {
            app()->make(BackendMessageEventService::class)->auditSubmitted(
                BackendMessageService::SUB_TYPE_CONTENT_AUDIT,
                TagService::RELATION_TYPE_ARTICLE,
                (int)$id,
                (int)$article['mer_id'],
                (string)($article['title'] ?? '内容')
            );
        }
        return true;
    }

    /**
     * 快捷编辑内容字段，支持主表字段与内容扩展表字段局部更新。
     *
     * @param int $id 内容ID。
     * @param array $data 请求字段。
     * @param bool $needAudit 是否按敏感字段变更触发审核。
     * @param int $merchantId 商户ID，传入后会校验归属。
     * @return array 包含内容详情、是否重新审核及变更字段名的响应。
     * @throws AdminResponseException
     */
    public function quickUpdate(int $id, array $data, bool $needAudit = false, int $merchantId = 0): array
    {
        $article = $this->get($id);
        if (!$article) {
            throw new AdminResponseException('内容不存在');
        }

        $articleData = $article->toArray();
        if ($merchantId > 0 && (int)($articleData['mer_id'] ?? 0) !== $merchantId) {
            throw new AdminResponseException('无权操作该内容');
        }

        [$mainData, $contentData, $changedFields] = $this->normalizeQuickUpdateData($data, $articleData);
        if (!$mainData && !$contentData) {
            throw new AdminResponseException('请选择要修改的字段');
        }

        $shouldAudit = $needAudit && $merchantId > 0 && $this->quickUpdateNeedsAudit($changedFields, $articleData);
        if ($shouldAudit) {
            $mainData['audit_status'] = AuditStatusEnum::PENDING;
            $mainData['audit_remark'] = '';
            $mainData['audit_time'] = null;
            $mainData['audit_admin_id'] = 0;
        }

        $this->transaction(function () use ($id, $mainData, $contentData) {
            if ($mainData) {
                $this->update($id, $mainData);
            }
            if ($contentData) {
                app()->make(ArticleContentService::class)->saveOrCreateByArticleId($id, $contentData);
            }
        });

        if ($contentData || array_intersect(array_keys($mainData), ['cover'])) {
            $this->dispatchImageLocalizeIfNeeded($id);
        }

        $detail = $this->resourceRead($id) ?? [];
        if ($shouldAudit) {
            app()->make(BackendMessageEventService::class)->auditSubmitted(
                BackendMessageService::SUB_TYPE_CONTENT_AUDIT,
                TagService::RELATION_TYPE_ARTICLE,
                $id,
                $merchantId,
                (string)($detail['title'] ?? $articleData['title'] ?? '内容')
            );
        }

        return [
            'detail' => $detail,
            'audit_required' => $shouldAudit,
            'changed_fields' => $changedFields,
        ];
    }

    /**
     * 规范化快捷编辑入参。
     *
     * @param array $data 请求数据。
     * @param array $articleData 当前内容数据。
     * @return array{0: array, 1: array, 2: array} 依次包含主表更新字段、扩展表更新字段和变更字段名的三元数组。
     */
    private function normalizeQuickUpdateData(array $data, array $articleData): array
    {
        $data = $this->resolveQuickUpdatePayload($data);
        $mainData = [];
        $contentData = [];
        $changedFields = [];

        foreach (self::QUICK_UPDATE_MAIN_FIELDS as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $mainData[$field] = $this->normalizeQuickUpdateMainField($field, $data[$field]);
            $changedFields[] = $field;
        }

        foreach (self::QUICK_UPDATE_CONTENT_FIELDS as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $contentData[$field] = $this->normalizeQuickUpdateContentField($field, $data[$field]);
            $changedFields[] = $field;
        }

        if ($mainData) {
            $mainData = $this->filterSensitiveFields($mainData, ['title', 'description', 'keywords']);
            $this->applyQuickUpdatePriceRules($mainData, $articleData);
        }

        return [$mainData, $contentData, array_values(array_unique($changedFields))];
    }

    /**
     * 兼容多字段对象、fields 对象和 field/value 单字段写法。
     *
     * @param array $data 待校验或保存的内容主表、扩展字段及标签数据。
     * @return array 统一为字段名到值映射的快捷编辑载荷。
     */
    private function resolveQuickUpdatePayload(array $data): array
    {
        if (isset($data['fields']) && is_array($data['fields'])) {
            return $data['fields'];
        }

        if (isset($data['field']) && is_string($data['field']) && array_key_exists('value', $data)) {
            return [$data['field'] => $data['value']];
        }

        return $data;
    }

    /**
     * 规范化内容主表快捷编辑字段。
     *
     * @param string $field 当前处理的内容字段名称。
     * @param mixed $value 需要校验或规范化的原始输入值。
     * @return mixed 符合主表字段规则的值。
     */
    private function normalizeQuickUpdateMainField(string $field, mixed $value): mixed
    {
        if (array_key_exists($field, self::QUICK_UPDATE_STRING_LIMITS)) {
            $normalizedValue = in_array($field, ['media_url', 'trial_media_url'], true)
                ? $this->normalizeMediaUrl($value)
                : $value;

            return $this->normalizeQuickUpdateString($field, $normalizedValue, self::QUICK_UPDATE_STRING_LIMITS[$field]);
        }

        return match ($field) {
            'content_type' => $this->normalizeQuickUpdateContentType($value),
            'price', 'original_price' => $this->normalizeQuickUpdateMoney($field, $value),
            'article_category_id', 'media_duration', 'word_count', 'virtual_learn_count', 'sort', 'gift_points' => $this->normalizeQuickUpdateInteger($field, $value),
            'is_trial', 'is_hot', 'is_banner', 'status' => $this->normalizeQuickUpdateSwitch($value),
            default => $value,
        };
    }

    /**
     * 规范化内容扩展表快捷编辑字段。
     *
     * @param string $field 当前处理的内容字段名称。
     * @param mixed $value 需要校验或规范化的原始输入值。
     * @return mixed 符合内容扩展表字段规则的值。
     */
    private function normalizeQuickUpdateContentField(string $field, mixed $value): mixed
    {
        if (in_array($field, ['images', 'trial_images'], true)) {
            return $this->normalizeQuickUpdateImages($field, $value);
        }

        return $this->sanitizeRichContent($this->filterSensitiveText((string)($value ?? '')));
    }

    /**
     * 规范化字符串字段。
     *
     * @param string $field 当前处理的内容字段名称。
     * @param mixed $value 需要校验或规范化的原始输入值。
     * @param int $limit 该字符串字段允许的最大字符数。
     * @return string 去除首尾空白并通过长度校验的字符串。
     */
    private function normalizeQuickUpdateString(string $field, mixed $value, int $limit): string
    {
        if (is_array($value) || is_object($value)) {
            throw new AdminResponseException($this->getQuickUpdateFieldLabel($field) . '格式错误');
        }

        $text = trim((string)($value ?? ''));
        if ($field === 'title' && $text === '') {
            throw new AdminResponseException('文章标题不能为空');
        }
        if (mb_strlen($text, 'UTF-8') > $limit) {
            throw new AdminResponseException($this->getQuickUpdateFieldLabel($field) . '长度不能超过' . $limit . '个字符');
        }

        return $text;
    }

    /**
     * 统一规范化内容媒体地址。
     *
     * 上传组件可能返回不带协议的完整域名，例如 qiniu.example.com/file.mp4；
     * 这类地址保存前统一补充 HTTPS，历史相对路径和已有协议地址保持原值。
     *
     * @param mixed $url 上传组件返回的媒体地址，兼容历史相对路径。
     * @return string 补齐必要协议的媒体地址，历史相对路径保持原值。
     */
    protected function normalizeMediaUrl(mixed $url): string
    {
        if (is_array($url) || is_object($url)) {
            throw new AdminResponseException('媒体地址格式错误');
        }

        $value = trim((string)($url ?? ''));
        if ($value === '') {
            return '';
        }

        if (str_starts_with($value, '//')) {
            return 'https:' . $value;
        }

        if (preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $value) === 1
            || preg_match('/^[a-z][a-z0-9+.-]*:/i', $value) === 1) {
            return $value;
        }

        if (preg_match('/^[a-z0-9.-]+\.[a-z]{2,}(?::\d+)?(?:\/|$)/i', $value) === 1) {
            return 'https://' . $value;
        }

        return $value;
    }

    /**
     * 规范化内容类型。
     *
     * @param mixed $value 需要校验或规范化的原始输入值。
     * @return int 合法的内容类型枚举值。
     */
    private function normalizeQuickUpdateContentType(mixed $value): int
    {
        if (!is_numeric($value)) {
            throw new AdminResponseException('内容类型格式错误');
        }
        $contentType = (int)$value;
        $this->validateContentType($contentType);
        return $contentType;
    }

    /**
     * 规范化金额字段。
     *
     * @param string $field 当前处理的内容字段名称。
     * @param mixed $value 需要校验或规范化的原始输入值。
     * @return float 通过范围校验的两位小数金额。
     */
    private function normalizeQuickUpdateMoney(string $field, mixed $value): float
    {
        if ($value === '' || $value === null) {
            return 0.00;
        }
        if (!is_numeric($value)) {
            throw new AdminResponseException($this->getQuickUpdateFieldLabel($field) . '必须为数字');
        }
        $amount = (float)$value;
        if ($amount < 0) {
            throw new AdminResponseException($this->getQuickUpdateFieldLabel($field) . '不能小于0');
        }
        if ($amount > 99999999.99) {
            throw new AdminResponseException($this->getQuickUpdateFieldLabel($field) . '不能超过99999999.99');
        }

        return (float)number_format($amount, 2, '.', '');
    }

    /**
     * 规范化整数字段。
     *
     * @param string $field 当前处理的内容字段名称。
     * @param mixed $value 需要校验或规范化的原始输入值。
     * @return int 通过整数及范围校验的字段值。
     */
    private function normalizeQuickUpdateInteger(string $field, mixed $value): int
    {
        if ($value === '' || $value === null) {
            return 0;
        }
        if (!is_numeric($value)) {
            throw new AdminResponseException($this->getQuickUpdateFieldLabel($field) . '必须为数字');
        }
        $number = (int)$value;
        if (in_array($field, ['article_category_id', 'media_duration', 'word_count', 'virtual_learn_count', 'gift_points'], true)) {
            return max(0, $number);
        }

        return $number;
    }

    /**
     * 规范化开关字段。
     *
     * @param mixed $value 需要校验或规范化的原始输入值。
     * @return int 规范化为零或一的开关值。
     */
    private function normalizeQuickUpdateSwitch(mixed $value): int
    {
        return in_array($value, [1, '1', true, 'true'], true) ? 1 : 0;
    }

    /**
     * 规范化图片数组字段。
     *
     * @param string $field 当前处理的内容字段名称。
     * @param mixed $value 需要校验或规范化的原始输入值。
     * @return array 已提取并校验地址的图片列表。
     */
    private function normalizeQuickUpdateImages(string $field, mixed $value): array
    {
        if ($value === '' || $value === null) {
            return [];
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $value = $decoded;
            }
        }
        if (!is_array($value)) {
            throw new AdminResponseException($this->getQuickUpdateFieldLabel($field) . '格式错误');
        }

        $singleImage = $this->extractQuickUpdateImageUrl($value);
        if ($singleImage !== '') {
            return [$singleImage];
        }

        $images = [];
        foreach ($value as $item) {
            $image = $this->extractQuickUpdateImageUrl($item);
            if ($image !== '') {
                $images[] = $image;
            }
        }

        return $images;
    }

    /**
     * 从上传组件返回值中提取图片地址。
     *
     * @param mixed $item 单个上传结果，支持地址字符串或图片对象。
     * @return string 单个上传结果中的图片地址。
     */
    private function extractQuickUpdateImageUrl(mixed $item): string
    {
        if (is_string($item) || is_numeric($item)) {
            return trim((string)$item);
        }
        if (!is_array($item)) {
            return '';
        }

        foreach (['url', 'path', 'filePath', 'src', 'value'] as $key) {
            if (isset($item[$key]) && (is_string($item[$key]) || is_numeric($item[$key]))) {
                return trim((string)$item[$key]);
            }
        }

        return '';
    }

    /**
     * 快捷编辑价格时同步维护赠送积分规则。
     *
     * @param array $mainData 主表更新字段，按引用清零免费内容的赠送积分。
     * @param array $articleData 当前内容记录的原有数据。
     * @return void 无返回值。
     */
    private function applyQuickUpdatePriceRules(array &$mainData, array $articleData): void
    {
        if (!array_key_exists('price', $mainData) && !array_key_exists('gift_points', $mainData)) {
            return;
        }

        $price = array_key_exists('price', $mainData) ? (float)$mainData['price'] : (float)($articleData['price'] ?? 0);
        if ($price <= 0) {
            $mainData['gift_points'] = 0;
        }
    }

    /**
     * 判断商户快捷编辑是否需要重新审核。
     *
     * @param array $changedFields 本次实际发生变化的字段名称集合。
     * @param array $articleData 当前内容记录的原有数据。
     * @return bool 本次字段修改是否需要商户内容重新审核。
     */
    private function quickUpdateNeedsAudit(array $changedFields, array $articleData): bool
    {
        if ((int)($articleData['mer_id'] ?? 0) <= 0) {
            return false;
        }

        return (bool)array_intersect($changedFields, self::QUICK_UPDATE_AUDIT_FIELDS);
    }

    /**
     * 快捷编辑字段中文名。
     *
     * @param string $field 当前处理的内容字段名称。
     * @return string 指定快捷编辑字段的中文名称。
     */
    private function getQuickUpdateFieldLabel(string $field): string
    {
        $map = [
            'title' => '文章标题',
            'article_category_id' => '内容分类',
            'author' => '作者',
            'source' => '来源',
            'keywords' => '关键词',
            'description' => '描述',
            'cover' => '封面图片',
            'content_type' => '内容类型',
            'media_url' => '媒体地址',
            'media_duration' => '媒体时长',
            'trial_media_url' => '试听地址',
            'price' => '售价',
            'original_price' => '原价',
            'gift_points' => '赠送积分',
            'is_trial' => '是否试看',
            'word_count' => '字数',
            'virtual_learn_count' => '虚拟学习人数',
            'sort' => '排序',
            'is_hot' => '是否热门',
            'is_banner' => '是否轮播图',
            'status' => '状态',
            'content' => '正文内容',
            'trial_content' => '试读内容',
            'images' => '图片',
            'trial_images' => '试看图片',
        ];

        return $map[$field] ?? $field;
    }

    /**
     * 删除内容（平台端使用）
     *
     * @param int $id 内容记录标识。
     * @return bool 内容及其关联数据是否删除成功。
     * @throws AdminResponseException
     */
    public function resourceDelete(int $id): bool
    {
        return $this->deleteContentWithRelations($id);
    }

    /**
     * 审核内容
     *
     * @param int $id 内容记录标识。
     * @param int $auditStatus 目标审核状态，须符合模块的审核枚举。
     * @param string $remark 审核或操作备注。
     * @param int $adminId 执行操作的后台管理员标识。
     * @return void 无返回值。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function auditArticle(int $id, int $auditStatus, string $remark = '', int $adminId = 0): void
    {
        $article = $this->get($id);
        if (!$article) {
            throw new AdminResponseException('内容不存在');
        }
        if (!array_key_exists($auditStatus, AuditStatusEnum::list())) {
            throw new AdminResponseException('审核状态不正确');
        }
        if (AuditStatusEnum::isViolation($auditStatus) && trim($remark) === '') {
            throw new AdminResponseException('驳回或下架时请填写审核备注');
        }

        $updateData = [
            'audit_status' => $auditStatus,
            'audit_remark' => AuditStatusEnum::isViolation($auditStatus) ? trim($remark) : '',
        ];
        $tableFields = $article->getTableFields();
        if (in_array('audit_time', $tableFields, true)) {
            $updateData['audit_time'] = date('Y-m-d H:i:s');
        }
        if (in_array('audit_admin_id', $tableFields, true)) {
            $updateData['audit_admin_id'] = $adminId;
        }
        if ($auditStatus === AuditStatusEnum::OFFLINE && in_array('status', $tableFields, true)) {
            $updateData['status'] = 0;
        }

        $this->update($id, $updateData);
        app()->make(BackendMessageEventService::class)->auditResult(
            BackendMessageService::SUB_TYPE_CONTENT_AUDIT,
            TagService::RELATION_TYPE_ARTICLE,
            $id,
            (int)($article['mer_id'] ?? 0),
            $auditStatus,
            trim($remark),
            (string)($article['title'] ?? '内容')
        );
    }

    /**
     * 批量审核内容。
     *
     * @param array $ids 待审核的内容标识集合。
     * @param int $auditStatus 目标审核状态，须符合模块的审核枚举。
     * @param string $remark 审核或操作备注。
     * @param int $adminId 执行操作的后台管理员标识。
     * @return void 无返回值。
     */
    public function batchAuditArticle(array $ids, int $auditStatus, string $remark = '', int $adminId = 0): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        foreach ($ids as $id) {
            if ($id > 0) {
                $this->auditArticle($id, $auditStatus, $remark, $adminId);
            }
        }
    }

    /**
     * 获取待审核内容的 Tab 统计。
     *
     * @return array 按商户来源统计的待审核内容数量。
     */
    public function getPendingAuditTabCounts(): array
    {
        $baseQuery = $this->getModel()
            ->whereNull('delete_time')
            ->where('audit_status', AuditStatusEnum::PENDING);

        $merchant = (int)(clone $baseQuery)
            ->where('mer_id', '>', 0)
            ->count();
        $platform = (int)(clone $baseQuery)
            ->where('mer_id', 0)
            ->count();

        return [
            'all' => $merchant + $platform,
            'merchant' => $merchant,
            'platform' => $platform,
        ];
    }

    /**
     * 根据内容类型存储内容
     *
     * @param array $data 待校验或保存的内容主表、扩展字段及标签数据。
     * @return array 已创建的内容主表及对应类型的扩展数据。
     * @throws AdminResponseException
     */
    protected function saveContentWithType(array $data): array
    {
        $contentType = (int)($data['content_type'] ?? ContentTypeEnum::GRAPHIC);
        $this->validateContentType($contentType);
        $this->validateArticleCategoryRequired($data);
        $this->validateCoverRequired($data);
        $data = $this->normalizeGiftPoints($data);
        $data['media_url'] = $this->normalizeMediaUrl($data['media_url'] ?? '');
        $data['trial_media_url'] = $this->normalizeMediaUrl($data['trial_media_url'] ?? '');

        // 对文字字段执行敏感词过滤
        $data = $this->filterSensitiveFields($data, ['title', 'description', 'keywords']);

        $result = $this->transaction(function () use ($data, $contentType) {
            $richContent = $trialContent = '';
            if (ContentTypeEnum::needsContent($contentType)) {
                $richContent = $this->sanitizeRichContent($this->filterSensitiveText($data['content'] ?? ''));
                $trialContent = $this->sanitizeRichContent($this->filterSensitiveText($data['trial_content'] ?? ''));
            }

            $articleContent = [
                'content' => $richContent,
                'trial_content' => $trialContent,
                'images' => $data['images'] ?? [],
                'trial_images' => $data['trial_images'] ?? [],
            ];

            unset($data['content'], $data['images'], $data['trial_images'], $data['article_category_path'], $data['trial_content']);

            $article = $this->create($data);
            $articleId = (int)($article['id'] ?? 0);

            if ((ContentTypeEnum::needsContent($contentType) || ContentTypeEnum::needsImages($contentType)) && $articleId > 0) {
                app()->make(ArticleContentService::class)->saveOrCreateByArticleId($articleId, $articleContent);
            }

            return $this->resourceRead($articleId) ?? [];
        });

        $this->dispatchImageLocalizeIfNeeded((int)($result['id'] ?? 0));
        return $result;
    }

    /**
     * 根据内容类型更新内容
     *
     * @param int $id 内容记录标识。
     * @param array $data 待校验或保存的内容主表、扩展字段及标签数据。
     * @return void 无返回值。
     * @throws AdminResponseException
     */
    protected function updateContentWithType(int $id, array $data): void
    {
        $contentType = (int)($data['content_type'] ?? ContentTypeEnum::GRAPHIC);
        $this->validateContentType($contentType);
        $this->validateArticleCategoryRequired($data);
        $this->validateCoverRequired($data);
        $data = $this->normalizeGiftPoints($data);
        $data['media_url'] = $this->normalizeMediaUrl($data['media_url'] ?? '');
        $data['trial_media_url'] = $this->normalizeMediaUrl($data['trial_media_url'] ?? '');

        // 对文字字段执行敏感词过滤
        $data = $this->filterSensitiveFields($data, ['title', 'description', 'keywords']);

        $richContent = $trialContent = '';
        if (ContentTypeEnum::needsContent($contentType)) {
            $richContent = $this->sanitizeRichContent($this->filterSensitiveText($data['content'] ?? ''));
            $trialContent = $this->sanitizeRichContent($this->filterSensitiveText($data['trial_content'] ?? ''));
        }

        // 图文/文字/小说：内容存放到article_content
        $articleContent = [
            'content' => $richContent,
            'trial_content' => $trialContent,
            'images' => $data['images'] ?? [],
            'trial_images' => $data['trial_images'] ?? [],
        ];

        unset($data['content'], $data['images'], $data['trial_images'], $data['trial_content'], $data['article_category_path']);

        $contentService = app()->make(ArticleContentService::class);

        $this->transaction(function () use ($id, $data, $contentType, $contentService, $articleContent) {
            $this->update($id, $data);

            if (ContentTypeEnum::needsContent($contentType) || ContentTypeEnum::needsImages($contentType)) {
                $contentService->saveOrCreateByArticleId($id, $articleContent);
            } else {
                $contentService->deleteByArticleId($id);
            }
        });
    }

    /**
     * 删除内容及关联数据
     *
     * @param int $id 内容记录标识。
     * @return bool 内容及其关联记录是否删除成功。
     */
    protected function deleteContentWithRelations(int $id): bool
    {
        return (bool)$this->transaction(function () use ($id) {
            $deleted = $this->delete($id);
            app()->make(ArticleContentService::class)->deleteByArticleId($id);
            app()->make(SpecialContentService::class)->deleteByArticleId($id);
            app()->make(TagRelationService::class)->deleteByRelation(TagService::RELATION_TYPE_ARTICLE, $id);

            return $deleted;
        });
    }

    /**
     * 归一化付费内容赠送积分。
     *
     * @param array $data 待校验或保存的内容主表、扩展字段及标签数据。
     * @return array 已按付费规则修正赠送积分的内容字段。
     */
    protected function normalizeGiftPoints(array $data): array
    {
        if ((float)($data['price'] ?? 0) <= 0) {
            $data['gift_points'] = 0;
            return $data;
        }

        $data['gift_points'] = max(0, (int)($data['gift_points'] ?? 0));

        return $data;
    }

    /**
     * 提取标签 ID，避免写入内容主表。
     *
     * @param array $data 内容保存数据，传入后会移除 tag_ids。
     * @return array{0: array, 1: bool} 第一个值为标签 ID 数组，第二个值表示请求中是否包含 tag_ids。
     */
    protected function pullTagIds(array &$data): array
    {
        $exists = array_key_exists('tag_ids', $data);
        $tagIds = $exists && is_array($data['tag_ids']) ? $data['tag_ids'] : [];
        unset($data['tag_ids']);

        return [$tagIds, $exists];
    }

    /**
     * 获取可购买内容
     *
     * @param int $articleId 内容记录标识。
     * @return array 通过上架和审核校验的可购买内容。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function findAvailableById(int $articleId): array
    {
        if (!app()->make(ContentDisplayConfigService::class)->isContentEnabled()) {
            throw new AdminResponseException('内容功能已关闭');
        }

        $article = $this->get($articleId);
        if (!$article) {
            throw new AdminResponseException('内容不存在');
        }

        $articleData = $article->toArray();
        if ((int)($articleData['status'] ?? 0) !== 1) {
            throw new AdminResponseException('内容已下架');
        }
        if ((int)($articleData['audit_status'] ?? 0) !== 1) {
            throw new AdminResponseException('内容暂不可购买');
        }
        if ((float)($articleData['price'] ?? 0) <= 0) {
            throw new AdminResponseException('免费内容无需购买');
        }

        return $articleData;
    }

    /**
     * 获取各内容类型的移动端详情，附加阅读权限和专题章节上下文。
     *
     * @param int $articleId 内容ID
     * @param int $userId    用户ID（0 表示未登录）
     * @param int $specialId 专题ID，从专题进入小说详情时传入。
     * @param int $chapterPage 专题章节页码。
     * @param int $chapterLimit 专题章节每页数量。
     * @return array 供移动端展示的内容资料、权限和专题章节上下文。
     * @throws AdminResponseException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getMobileDetail(
        int $articleId,
        int $userId = 0,
        int $specialId = 0,
        int $chapterPage = 1,
        int $chapterLimit = 20
    ): array
    {
        $displayConfig = app()->make(ContentDisplayConfigService::class);
        if (!$displayConfig->isContentEnabled()) {
            return [];
        }

        $article = $this->get($articleId);
        if (!$article) {
            throw new AdminResponseException('内容不存在');
        }

        $data = $article->toArray();
        if ((int)($data['status'] ?? 0) !== 1) {
            throw new AdminResponseException('内容已下架');
        }
        if ((int)($data['audit_status'] ?? 0) !== 1) {
            throw new AdminResponseException('内容暂不可访问');
        }

        // 移动端详情支持文字、图文、小说、图片、音频和视频类型。
        $contentType = (int)($data['content_type'] ?? ContentTypeEnum::GRAPHIC);
        if (!in_array($contentType, [ContentTypeEnum::TEXT, ContentTypeEnum::GRAPHIC, ContentTypeEnum::NOVEL, ContentTypeEnum::IMAGE, ContentTypeEnum::AUDIO, ContentTypeEnum::VIDEO], true)) {
            throw new AdminResponseException('不支持的内容类型');
        }

        // 获取正文/图片（视频类型直接从 article 表取媒体字段）
        if (ContentTypeEnum::needsMedia($contentType)) {
            $data['media_url'] = (string)($data['media_url'] ?? '');
            $data['trial_media_url'] = (string)($data['trial_media_url'] ?? '');
            $data['content'] = '';
            $data['trial_content'] = '';
            $data['images'] = [];
            $data['trial_images'] = [];
        } else {
            $contentModel = app()->make(ArticleContentService::class)->findByArticleId($articleId);
            $data['content'] = $contentModel
                ? $this->sanitizeRichContent((string)($contentModel['content'] ?? ''))
                : '';
            $data['trial_content'] = $contentModel
                ? $this->sanitizeRichContent((string)($contentModel['trial_content'] ?? ''))
                : '';
            $data['images'] = $contentModel && is_array($contentModel['images'] ?? null) ? $contentModel['images'] : [];
            $data['trial_images'] = $contentModel && is_array($contentModel['trial_images'] ?? null) ? $contentModel['trial_images'] : [];
        }

        $price = (float)($data['price'] ?? 0);
        $memberService = app()->make(UserMemberService::class);
        $memberInfo = $memberService->getMemberRuntimeState($userId);
        $memberPricePreview = $memberService->getMemberPricePreview((int)($data['mer_id'] ?? 0), $price, $userId);

        // 判断购买状态
        $isPurchased = app()->make(UserPurchaseRecordService::class)
            ->hasPurchased($userId, Order::ORDER_TYPE_CONTENT, $articleId);
        $isFree = $price <= 0;
        $canLearn = $isFree || $isPurchased;
        $canBuy = !$canLearn && $price > 0;

        // 会员专属内容访问控制
        $isMemberOnly = (int)($data['is_member_only'] ?? 0) === 1;
        $memberAccess = true;
        if ($isMemberOnly && $userId > 0) {
            $memberAccess = (bool)($memberInfo['is_member'] ?? false);
        } elseif ($isMemberOnly) {
            $memberAccess = false;
        }
        if ($isMemberOnly && !$memberAccess) {
            $canLearn = false;
            $canBuy = false;
        }

        $specialChapterContext = null;
        $specialAccessState = '';
        if ($specialId > 0 && $contentType === ContentTypeEnum::NOVEL) {
            $specialChapterContext = app()->make(SpecialService::class)
                ->getMobileNovelChapterContext($specialId, $articleId, $userId, $chapterPage, $chapterLimit);
            $canLearn = (bool)($specialChapterContext['current_can_learn'] ?? false);
            $canBuy = (bool)($specialChapterContext['can_buy'] ?? false);
            $isPurchased = (bool)($specialChapterContext['is_purchased'] ?? false);
            $specialAccessState = (string)($specialChapterContext['access_state'] ?? '');
        }

        $mediaUrl = $canLearn ? $this->normalizeMediaUrl($data['media_url'] ?? '') : '';
        $hasTrial = (int)($data['is_trial'] ?? 0) === 1;
        $content = $canLearn ? (string)($data['content'] ?? '') : '';
        $trialContent = !$canLearn && $hasTrial ? (string)($data['trial_content'] ?? '') : '';
        $images = $canLearn && is_array($data['images'] ?? null) ? $data['images'] : [];
        $trialImages = !$canLearn && $hasTrial && is_array($data['trial_images'] ?? null) ? $data['trial_images'] : [];
        $accessState = !$isMemberOnly || $memberAccess
            ? ($canLearn ? 'learning' : 'purchase')
            : ((bool)($memberInfo['member_enabled'] ?? false) ? 'member_required' : 'unavailable');
        if ($specialAccessState !== '') {
            $accessState = $specialAccessState;
        }
        $isLiked = app()->make(UserLikeService::class)->isContentLiked($userId, $articleId);
        $isFavorite = app()->make(UserFavoriteService::class)->isFavorite(
            $userId,
            UserFavoriteService::TARGET_TYPE_CONTENT,
            $articleId
        );
        $commentTotal = (int)app()->make(ContentComment::class)
            ->where('article_id', $articleId)
            ->where('pid', 0)
            ->count();
        $learnCountMap = app()->make(UserPurchaseRecordService::class)->getContentLearnCountMap([$articleId]);
        $realLearnCount = (int)($learnCountMap[$articleId] ?? 0);
        $virtualLearnCount = max(0, (int)($data['virtual_learn_count'] ?? 0));
        $viewCount = max(0, (int)($data['view_count'] ?? 0));
        $this->incrementViewCount($articleId);
        $viewCount++;
        $memberPriceAvailable = (bool)($memberPricePreview['member_price_available'] ?? false);
        $memberPrice = $memberPriceAvailable ? (float)($memberPricePreview['member_price'] ?? 0) : 0.0;
        $merchantInfo = $this->resolveMobileMerchantInfo((int)($data['mer_id'] ?? 0));

        return [
            'id' => (int)($data['id'] ?? 0),
            'mer_id' => (int)($data['mer_id'] ?? 0),
            // merchant_info 为当前内容所属的平台/店铺信息；store_info 保留同值兼容不同前端调用方。
            'merchant_info' => $merchantInfo,
            'store_info' => $merchantInfo,
            'merchant' => $merchantInfo,
            'title' => (string)($data['title'] ?? ''),
            'author' => (string)($data['author'] ?? ''),
            'avatar' => (string)($merchantInfo['avatar'] ?? ''),
            'description' => (string)($data['description'] ?? ''),
            'cover' => (string)($data['cover'] ?? ''),
            'content_type' => $contentType,
            'price' => $price,
            'original_price' => (float)($data['original_price'] ?? 0),
            'is_trial' => (int)($data['is_trial'] ?? 0),
            'is_member_only' => $isMemberOnly,
            'member_access' => $memberAccess,
            'is_member' => (bool)($memberInfo['is_member'] ?? false),
            'member_end_time' => $memberInfo['end_time'] ?? null,
            'member_enabled' => (bool)($memberInfo['member_enabled'] ?? false),
            'member_visible' => (bool)($memberInfo['member_visible'] ?? false),
            'can_purchase' => (bool)($memberInfo['can_purchase'] ?? false),
            'can_renew' => (bool)($memberInfo['can_renew'] ?? false),
            'member_discount_rate' => (float)($memberPricePreview['discount_rate'] ?? 1.00),
            'member_discount_amount' => (float)($memberPricePreview['discount_amount'] ?? 0),
            'member_price' => $memberPrice,
            'member_price_available' => $memberPriceAvailable,
            'merchant_member_discount_joined' => (bool)($memberPricePreview['merchant_joined'] ?? false),
            'access_state' => $accessState,
            'word_count' => (int)($data['word_count'] ?? 0),
            'chapter_count' => (int)($data['chapter_count'] ?? 0),
            'view_count' => $viewCount,
            'virtual_learn_count' => $virtualLearnCount,
            'real_learn_count' => $realLearnCount,
            'learn_count' => $virtualLearnCount + $realLearnCount,
            'like_count' => (int)($data['like_count'] ?? 0),
            'is_liked' => $isLiked,
            'is_favorite' => $isFavorite,
            'create_time' => (string)($data['create_time'] ?? ''),
            'is_free' => $isFree,
            'is_purchased' => $isPurchased,
            'can_learn' => $canLearn,
            'can_buy' => $canBuy,
            'content' => $content,
            'trial_content' => $trialContent,
            'images' => $images,
            'trial_images' => $trialImages,
            'media_url' => $mediaUrl,
            'trial_media_url' => $this->normalizeMediaUrl($data['trial_media_url'] ?? ''),
            'media_duration' => (int)($data['media_duration'] ?? 0),
            'comment_total' => $commentTotal,
            'special_chapter_context' => $specialChapterContext,
        ];
    }

    /**
     * 获取内容收藏状态。
     *
     * @param int $userId 当前用户标识，匿名访问时为零。
     * @param int $articleId 内容记录标识。
     * @return array 内容标识和当前用户的收藏状态。
     */
    public function getFavoriteStatus(int $userId, int $articleId): array
    {
        $displayConfig = app()->make(ContentDisplayConfigService::class);
        if (!$displayConfig->isContentEnabled()) {
            return [
                'article_id' => $articleId,
                'is_favorite' => false,
            ];
        }

        if ($userId <= 0 || $articleId <= 0) {
            return [
                'article_id' => $articleId,
                'is_favorite' => false,
            ];
        }

        return [
            'article_id' => $articleId,
            'is_favorite' => app()->make(UserFavoriteService::class)->isFavorite(
                $userId,
                UserFavoriteService::TARGET_TYPE_CONTENT,
                $articleId
            ),
        ];
    }

    /**
     * 切换内容收藏状态。
     *
     * @param int $userId 执行收藏操作的已登录用户标识。
     * @param int $articleId 内容记录标识。
     * @return array 切换后的内容收藏状态。
     * @throws AdminResponseException
     */
    public function toggleFavorite(int $userId, int $articleId): array
    {
        if (!app()->make(ContentDisplayConfigService::class)->isContentEnabled()) {
            throw new AdminResponseException('内容功能已关闭');
        }

        if ($userId <= 0) {
            throw new AdminResponseException('未获取到登录用户');
        }

        $article = $this->get($articleId);
        if (!$article) {
            throw new AdminResponseException('内容不存在');
        }

        $articleData = $article->toArray();
        $result = app()->make(UserFavoriteService::class)->toggleFavorite(
            $userId,
            UserFavoriteService::TARGET_TYPE_CONTENT,
            $articleId,
            (int)($articleData['mer_id'] ?? 0)
        );

        return [
            'article_id' => $articleId,
            'is_favorite' => (bool)($result['is_favorite'] ?? false),
            'status' => (int)($result['status'] ?? 0),
        ];
    }

    /**
     * 增加内容浏览量。
     *
     * @param int $articleId 内容记录标识。
     * @param int $step 本次增加的真实浏览次数。
     * @return void 无返回值。
     */
    public function incrementViewCount(int $articleId, int $step = 1): void
    {
        if ($articleId <= 0 || $step <= 0) {
            return;
        }

        $this->getModel()->where('id', $articleId)->inc('view_count', $step)->update();
    }

    /**
     * 获取移动端内容列表。
     *
     * 只返回已启用、已审核内容，支持按关键词、文章分类与内容类型筛选。
     *
     * @param array $params 查询参数。
     * @return array 公开内容列表、展示状态和分页信息。
     */
    public function getMobileList(array $params = []): array
    {
        [$page, $limit] = $this->getPagesValue(true, true, $params, (int)($params['_limit_max'] ?? 30));
        $displayConfig = app()->make(ContentDisplayConfigService::class);
        if (!$displayConfig->isContentEnabled()) {
            return $displayConfig->emptyPage($page, $limit);
        }

        $keyword = trim((string)($params['keyword'] ?? ''));
        $categoryId = max(0, (int)($params['category_id'] ?? 0));
        $contentType = (int)($params['content_type'] ?? 0);
        $minPrice = $this->normalizeMobilePriceFilter($params['min_price'] ?? '');
        $maxPrice = $this->normalizeMobilePriceFilter($params['max_price'] ?? '');
        $merchantId = max(0, (int)($params['merchant_id'] ?? 0));
        $sort = trim((string)($params['sort'] ?? 'default'));
        $userId = max(0, (int)($params['user_id'] ?? 0));
        $memberState = app()->make(UserMemberService::class)->getMemberRuntimeState($userId);

        if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
            [$minPrice, $maxPrice] = [$maxPrice, $minPrice];
        }

        $query = $this->getModel()
            ->whereNull('delete_time')
            ->where('status', 1)
            ->where('audit_status', AuditStatusEnum::APPROVED);

        if (!(bool)($memberState['member_visible'] ?? false)) {
            $query->where('is_member_only', 0);
        }

        if ($merchantId > 0) {
            $query->where('mer_id', $merchantId);
        }

        // 商户展示服务传入的有效集合；空自选/分类集合不得退回全量查询。
        if (array_key_exists('_ids', $params)) {
            $ids = is_array($params['_ids']) ? array_values(array_map('intval', $params['_ids'])) : [];
            $query->whereIn('id', $ids === [] ? [0] : $ids);
            $query->orderField('id', $ids, 'asc');
        }
        if (array_key_exists('_category_ids', $params)) {
            $categoryIds = is_array($params['_category_ids']) ? $params['_category_ids'] : [];
            $query->whereIn('article_category_id', $categoryIds === [] ? [0] : $categoryIds);
        }

        if ($keyword !== '') {
            $query->where(function ($subQuery) use ($keyword): void {
                $subQuery->whereLike('title', '%' . $keyword . '%')
                    ->whereOr('keywords', 'like', '%' . $keyword . '%')
                    ->whereOr('description', 'like', '%' . $keyword . '%');
            });
        }

        if ($categoryId > 0) {
            $categoryIds = app()->make(ArticleCategoryService::class)->getMobileScopeIds($categoryId);
            if ($categoryIds !== []) {
                $query->whereIn('article_category_id', $categoryIds);
            } else {
                $query->where('article_category_id', $categoryId);
            }
        }

        if ($contentType > 0 && array_key_exists($contentType, ContentTypeEnum::list())) {
            $query->where('content_type', $contentType);
        }

        if ($minPrice !== null) {
            $query->where('price', '>=', $minPrice);
        }

        if ($maxPrice !== null) {
            $query->where('price', '<=', $maxPrice);
        }

        $total = (int)(clone $query)->count();
        $listQuery = array_key_exists('_ids', $params) ? $query : $this->applyMobileListSort($query, $sort);
        $list = $listQuery
            ->page($page, $limit)
            ->field([
                'id',
                'mer_id',
                'article_category_id',
                'title',
                'author',
                'keywords',
                'description',
                'cover',
                'content_type',
                'media_duration',
                'price',
                'original_price',
                'sort',
                'word_count',
                'chapter_count',
                'view_count',
                'virtual_learn_count',
                'like_count',
                'is_hot',
                'is_member_only',
                'create_time',
            ])
            ->select()
            ->toArray();

        $articleIds = array_values(array_filter(array_map(static fn(array $item): int => (int)($item['id'] ?? 0), $list)));
        $purchaseService = app()->make(UserPurchaseRecordService::class);
        $learnCountMap = $purchaseService->getContentLearnCountMap($articleIds);
        $purchasedMap = $userId > 0
            ? $purchaseService->getPurchasedMap($userId, [Order::ORDER_TYPE_CONTENT => $articleIds])
            : [];
        $list = array_map(fn(array $item): array => $this->formatMobileListItem(
            $item,
            (int)($learnCountMap[(int)($item['id'] ?? 0)] ?? 0),
            $userId,
            isset($purchasedMap[Order::ORDER_TYPE_CONTENT . '_' . (int)($item['id'] ?? 0)])
        ), $list);
        $list = app()->make(TagService::class)->attachTagsToList($list, TagService::RELATION_TYPE_ARTICLE);

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'page_size' => $limit,
            'has_more' => $page * $limit < $total,
        ];
    }

    /**
     * 标准化移动端内容列表价格筛选值。
     *
     * @param mixed $value 原始价格筛选值。
     * @return float|null 非数字或空值返回 null。
     */
    protected function normalizeMobilePriceFilter(mixed $value): ?float
    {
        $normalized = trim((string)$value);
        if ($normalized === '' || !is_numeric($normalized)) {
            return null;
        }

        return max(0, (float)$normalized);
    }

    /**
     * 应用移动端内容列表排序。
     *
     * @param mixed $query 查询对象。
     * @param string $sort 排序标识。
     * @return mixed 已应用所选排序规则的查询对象。
     */
    protected function applyMobileListSort(mixed $query, string $sort): mixed
    {
        return match ($sort) {
            'hot' => $query->order('is_hot', 'desc')->order('view_count', 'desc')->order('sort', 'desc')->order('id', 'desc'),
            'view' => $query->order('view_count', 'desc')->order('sort', 'desc')->order('id', 'desc'),
            'price_asc' => $query->order('price', 'asc')->order('sort', 'desc')->order('id', 'desc'),
            'price_desc' => $query->order('price', 'desc')->order('sort', 'desc')->order('id', 'desc'),
            default => $query->order('is_hot', 'desc')->order('sort', 'desc')->order('id', 'desc'),
        };
    }

    /**
     * 格式化移动端内容列表项。
     *
     * @param array $item 内容原始数据。
     * @param int $realLearnCount 真实学习人数。
     * @param int $userId 当前用户 ID。
     * @param bool $isPurchased 当前用户是否已购买。
     * @return array 附带价格、学习状态和跳转地址的内容列表项。
     */
    protected function formatMobileListItem(
        array $item,
        int $realLearnCount = 0,
        int $userId = 0,
        bool $isPurchased = false
    ): array
    {
        $contentType = (int)($item['content_type'] ?? ContentTypeEnum::GRAPHIC);
        $viewCount = (int)($item['view_count'] ?? 0);
        $virtualLearnCount = max(0, (int)($item['virtual_learn_count'] ?? 0));
        $learnCount = $virtualLearnCount + max(0, $realLearnCount);
        $price = (float)($item['price'] ?? 0);
        $originalPrice = (float)($item['original_price'] ?? 0);
        $memberPricePreview = app()->make(UserMemberService::class)
            ->getMemberPricePreview((int)($item['mer_id'] ?? 0), $price, $userId);
        $memberPriceAvailable = (bool)($memberPricePreview['member_price_available'] ?? false);
        $memberPrice = $memberPriceAvailable ? (float)($memberPricePreview['member_price'] ?? 0) : 0.0;
        $keywordList = $this->parseMobileKeywordList((string)($item['keywords'] ?? ''));

        return [
            'id' => (int)($item['id'] ?? 0),
            'mer_id' => (int)($item['mer_id'] ?? 0),
            'article_category_id' => (int)($item['article_category_id'] ?? 0),
            'title' => (string)($item['title'] ?? ''),
            'author' => (string)($item['author'] ?? ''),
            'keywords' => (string)($item['keywords'] ?? ''),
            'keyword_list' => $keywordList,
            'description' => (string)($item['description'] ?? ''),
            'cover' => (string)($item['cover'] ?? ''),
            'image' => (string)($item['cover'] ?? ''),
            'content_type' => $contentType,
            'content_type_text' => ContentTypeEnum::getName($contentType),
            'type_text' => ContentTypeEnum::getName($contentType),
            'media_duration' => (int)($item['media_duration'] ?? 0),
            'price' => $price,
            'original_price' => $originalPrice,
            'member_price' => $memberPrice,
            'member_price_text' => $memberPriceAvailable ? '¥' . number_format($memberPrice, 2, '.', '') : '',
            'member_price_available' => $memberPriceAvailable,
            'member_enabled' => (bool)($memberPricePreview['member_enabled'] ?? false),
            'member_visible' => (bool)($memberPricePreview['member_visible'] ?? false),
            'price_text' => $price > 0 ? '¥' . number_format($price, 2, '.', '') : '免费',
            'original_price_text' => $originalPrice > $price ? '¥' . number_format($originalPrice, 2, '.', '') : '',
            'is_purchased' => $isPurchased,
            'has_buy' => $isPurchased ? 1 : 0,
            'is_bought' => $isPurchased ? 1 : 0,
            'word_count' => (int)($item['word_count'] ?? 0),
            'chapter_count' => (int)($item['chapter_count'] ?? 0),
            'view_count' => $viewCount,
            'views' => $viewCount,
            'virtual_learn_count' => $virtualLearnCount,
            'real_learn_count' => max(0, $realLearnCount),
            'learn_count' => $learnCount,
            'learn_count_text' => $this->formatMobileCount($learnCount) . '人已学',
            'like_count' => (int)($item['like_count'] ?? 0),
            'is_hot' => (int)($item['is_hot'] ?? 0),
            'is_member_only' => (int)($item['is_member_only'] ?? 0),
            'stat_text' => $this->formatMobileCount($viewCount) . '浏览',
            'detail_url' => $this->buildMobileDetailUrl((int)($item['id'] ?? 0), $contentType),
            'create_time' => (string)($item['create_time'] ?? ''),
        ];
    }

    /**
     * 拆分移动端关键词标签。
     *
     * @param string $keywords 关键词字符串。
     * @return array 去重后的非空关键词标签。
     */
    protected function parseMobileKeywordList(string $keywords): array
    {
        if ($keywords === '') {
            return [];
        }

        $items = preg_split('/[,，、\s]+/u', $keywords) ?: [];
        $items = array_values(array_unique(array_filter(array_map(
            static fn(string $item): string => mb_substr(trim($item), 0, 8),
            $items
        ))));

        return array_slice($items, 0, 3);
    }

    /**
     * 格式化移动端数量。
     *
     * @param int $count 数量。
     * @return string 移动端展示的数量文案。
     */
    protected function formatMobileCount(int $count): string
    {
        if ($count >= 10000) {
            return rtrim(rtrim(number_format($count / 10000, 1, '.', ''), '0'), '.') . '万';
        }

        return (string)$count;
    }

    /**
     * 构建内容详情地址。
     *
     * @param int $articleId 内容ID。
     * @param int $contentType 内容类型。
     * @return string 对应内容类型的独立详情页地址。
     */
    protected function buildMobileDetailUrl(int $articleId, int $contentType): string
    {
        if ($articleId <= 0) {
            return '';
        }

        return match ($contentType) {
            ContentTypeEnum::AUDIO => '/pages/contents/content/aud/index?id=' . $articleId,
            ContentTypeEnum::VIDEO => '/pages/contents/content/vid/index?id=' . $articleId,
            ContentTypeEnum::NOVEL => '/pages/contents/content/novel/index?id=' . $articleId,
            default => '/pages/contents/content/detail/index?id=' . $articleId,
        };
    }

    /**
     * 按 ID 获取内容映射
     *
     * @param array $articleIds 内容记录标识集合。
     * @return array 以内容标识为键的主表记录映射。
     */
    public function getMapByIds(array $articleIds): array
    {
        if (!app()->make(ContentDisplayConfigService::class)->isContentEnabled()) {
            return [];
        }

        $articleIds = array_values(array_unique(array_filter(array_map('intval', $articleIds))));
        if ($articleIds === []) {
            return [];
        }

        $list = $this->getModel()->whereIn('id', $articleIds)->select()->toArray();
        $result = [];
        foreach ($list as $article) {
            $id = (int)($article['id'] ?? 0);
            if ($id > 0) {
                $result[$id] = $article;
            }
        }

        return $result;
    }

    /**
     * 统计启用中的内容数量
     *
     * @param array $articleIds 内容记录标识集合。
     * @return int 指定标识范围内启用内容的数量。
     */
    public function countEnabledByIds(array $articleIds): int
    {
        $articleIds = array_values(array_unique(array_filter(array_map('intval', $articleIds))));
        if ($articleIds === []) {
            return 0;
        }

        return (int)$this->getModel()
            ->whereIn('id', $articleIds)
            ->where('status', 1)
            ->count();
    }

    /**
     * 验证内容类型合法性
     *
     * @param int $type 需要校验的内容类型枚举值。
     * @return void 无返回值。
     * @throws AdminResponseException
     */
    protected function validateContentType(int $type): void
    {
        if (!array_key_exists($type, ContentTypeEnum::list())) {
            throw new AdminResponseException('内容类型非法，支持类型：' . implode('/', array_values(ContentTypeEnum::list())));
        }
    }

    /**
     * 校验内容分类必选。
     *
     * @param array $data 内容表单数据。
     * @return void 无返回值。
     * @throws AdminResponseException
     */
    protected function validateArticleCategoryRequired(array $data): void
    {
        if ((int)($data['article_category_id'] ?? 0) <= 0) {
            throw new AdminResponseException('请选择内容分类');
        }
    }

    /**
     * 校验封面图必填。
     *
     * @param array $data 内容表单数据。
     * @return void 无返回值。
     * @throws AdminResponseException
     */
    protected function validateCoverRequired(array $data): void
    {
        if (trim((string)($data['cover'] ?? '')) === '') {
            throw new AdminResponseException('封面图不能为空');
        }
    }

    /**
     * 解析移动端内容详情的当前店铺信息，平台内容返回平台名称。
     *
     * @param int $merId 当前业务所属商户标识。
     * @return array 所属商户或平台的名称、头像和公开店铺信息。
     */
    private function resolveMobileMerchantInfo(int $merId): array
    {
        if ($merId <= 0) {
            $siteName = trim((string)db_config(ConfigKeyEnum::SITE_NAME, ''));
            $logo = $this->normalizeImageConfigValue(db_config(ConfigKeyEnum::LOGIN_LOGO, ''));

            return [
                'id' => 0,
                'mer_id' => 0,
                'type' => 'platform',
                'name' => $siteName !== '' ? $siteName : '平台',
                'mer_name' => $siteName !== '' ? $siteName : '平台',
                'logo' => $logo,
                'avatar' => $logo,
                'banner' => '',
                'description' => '',
            ];
        }

        $merchant = app()->make(MerchantService::class)->get($merId, [
            'id',
            'mer_name',
            'logo',
            'mer_avatar',
            'mer_banner',
            'description',
            'business_status',
            'follow_count',
            'status',
            'audit_status',
        ]);
        if (!$merchant) {
            return [
                'id' => $merId,
                'mer_id' => $merId,
                'type' => 'merchant',
                'name' => '',
                'mer_name' => '',
                'logo' => '',
                'avatar' => '',
                'banner' => '',
                'description' => '',
            ];
        }

        $data = $merchant->toArray();
        $avatar = trim((string)($data['mer_avatar'] ?? ''));
        $logo = trim((string)($data['logo'] ?? ''));

        return [
            'id' => $merId,
            'mer_id' => $merId,
            'type' => 'merchant',
            'name' => trim((string)($data['mer_name'] ?? '')),
            'mer_name' => trim((string)($data['mer_name'] ?? '')),
            'logo' => $logo !== '' ? $logo : $avatar,
            'avatar' => $avatar,
            'banner' => trim((string)($data['mer_banner'] ?? '')),
            'description' => trim((string)($data['description'] ?? '')),
            'business_status' => (int)($data['business_status'] ?? 1),
            'follow_count' => (int)($data['follow_count'] ?? 0),
            'status' => (int)($data['status'] ?? 0),
            'audit_status' => (int)($data['audit_status'] ?? 0),
        ];
    }

    /**
     * 规范化图片配置值，兼容上传组件返回的字符串、单图数组和图片列表。
     *
     * @param mixed $value 需要校验或规范化的原始输入值。
     * @return string 从兼容配置中提取并规范化的首张图片地址。
     */
    private function normalizeImageConfigValue(mixed $value): string
    {
        if (is_string($value) || is_numeric($value)) {
            return (string)$value;
        }

        if (!is_array($value)) {
            return '';
        }

        foreach (['url', 'path', 'filePath', 'src', 'value'] as $key) {
            if (isset($value[$key]) && (is_string($value[$key]) || is_numeric($value[$key]))) {
                return (string)$value[$key];
            }
        }

        $first = reset($value);
        if ($first === false) {
            return '';
        }

        return $this->normalizeImageConfigValue($first);
    }

    /**
     * 搜索内容列表（专题内容选择使用）
     *
     * @param array $params 内容列表的筛选和分页参数。
     * @return array 供专题选择器使用的内容列表和分页信息。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function searchContentList(array $params = []): array
    {
        $where = [];

        if (!empty($params['title'])) {
            $where[] = ['title', 'like', '%' . $params['title'] . '%'];
        }
        if (isset($params['content_type']) && $params['content_type'] !== '') {
            $where[] = ['content_type', '=', (int)$params['content_type']];
        }
        // 支持商户ID过滤
        if (isset($params['merchant_id']) && $params['merchant_id'] !== '') {
            $where[] = ['mer_id', '=', (int)$params['merchant_id']];
        }

        [$page, $pageSize] = $this->getPageValue();

        $query = $this->getModel()->where($where)->where('status', 1);

        // 排除已在直播间添加的商品
        if (!empty($params['exclude_ids']) && is_array($params['exclude_ids'])) {
            $query->whereNotIn('id', array_map('intval', $params['exclude_ids']));
        }

        $total = (clone $query)->count();
        $list = $query->order('id', 'desc')
            ->page($page, $pageSize)
            ->field('id,title,content_type,cover,price,original_price,is_trial,status')
            ->select()
            ->toArray();

        return [
            'total' => $total,
            'list' => $list,
            'page' => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * 采集 URL 内容，仅解析文字和远程图片地址，不下载图片。
     *
     * @param string $url 采集地址。
     * @return array 从网页提取的标题、正文、图片地址及建议内容类型。
     */
    public function collectFromUrl(string $url): array
    {
        $url = trim($url);
        if (!$this->isSafeHttpUrl($url)) {
            throw new AdminResponseException('请输入合法的公网 http/https 地址');
        }

        [$html, $finalUrl, $contentType] = $this->fetchCollectHtml($url);
        if ($html === '') {
            throw new AdminResponseException('URL内容为空或抓取失败');
        }
        if ($finalUrl !== '' && !$this->isSafeHttpUrl($finalUrl)) {
            throw new AdminResponseException('URL重定向地址不允许采集');
        }
        if ($contentType !== '' && !str_contains(strtolower($contentType), 'text/html')) {
            throw new AdminResponseException('仅支持采集网页内容');
        }

        $html = $this->normalizeHtmlCharset($html);
        $cleanHtml = $this->removeUnsafeHtmlBlocks($html);
        $images = array_slice($this->extractHtmlImages($cleanHtml, $finalUrl ?: $url), 0, 100);
        $content = $this->sanitizeRichContent(
            $this->buildCollectContent($cleanHtml, $finalUrl ?: $url, $images)
        );
        $plainText = $this->normalizeCollectText(strip_tags($content));
        $warnings = [];

        if (preg_match('/<(audio|video|source)\b/i', $cleanHtml)) {
            $warnings[] = '检测到音频或视频资源，系统不会采集媒体文件，请使用上传功能上传。';
        }
        if ($images !== []) {
            $warnings[] = '图片将在文章提交保存后进入队列异步本地化。';
        }

        return [
            'title' => mb_substr($this->extractMetaValue($cleanHtml, ['og:title', 'twitter:title']) ?: $this->extractHtmlTitle($cleanHtml), 0, 100),
            'author' => mb_substr($this->extractMetaValue($cleanHtml, ['author', 'article:author']), 0, 30),
            'source' => mb_substr((string)(parse_url($finalUrl ?: $url, PHP_URL_HOST) ?: ''), 0, 50),
            'description' => mb_substr($this->extractMetaValue($cleanHtml, ['description', 'og:description']) ?: $plainText, 0, 255),
            'cover' => $images[0] ?? '',
            'content_type' => $this->guessCollectedContentType($plainText, $images),
            'content' => $content,
            'images' => $images,
            'keywords' => mb_substr($this->extractMetaValue($cleanHtml, ['keywords']), 0, 100),
            'warnings' => $warnings,
        ];
    }

    /**
     * 保存后异步本地化文章图片。
     *
     * @param int $articleId 文章ID。
     * @return void 无返回值。
     */
    private function dispatchImageLocalizeIfNeeded(int $articleId): void
    {
        if ($articleId <= 0) {
            return;
        }

        try {
            ArticleImageLocalizeJob::dispatch($articleId);
        } catch (\Throwable $e) {
            Log::error('文章图片本地化队列投递失败：article_id=' . $articleId . '|error=' . $e->getMessage());
        }
    }

    /**
     * 抓取采集 HTML。
     *
     * @param string $url 采集地址。
     * @return array{string,string,string} 依次包含网页正文、最终请求地址和响应媒体类型的三元数组。
     */
    private function fetchCollectHtml(string $url): array
    {
        $maxRedirects = 3;
        $currentUrl = $url;

        for ($i = 0; $i <= $maxRedirects; $i++) {
            $ch = curl_init();
            $options = [
                CURLOPT_URL => $currentUrl,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_MAXREDIRS => 0,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_USERAGENT => 'KnowledgePaymentBot/1.0',
                CURLOPT_HTTPHEADER => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
                ],
            ];
            $proxy = $this->getProxyUrl();
            if ($proxy !== '') {
                $options[CURLOPT_PROXY] = $proxy;
            }
            curl_setopt_array($ch, $options);
            $body = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $redirectUrl = (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            curl_close($ch);

            $contentType = strtolower(trim(explode(';', $contentType)[0] ?? ''));

            // 处理重定向
            if ($httpCode >= 300 && $httpCode < 400 && $httpCode !== 304 && $redirectUrl !== '') {
                $currentUrl = $this->resolveUrl($redirectUrl, $currentUrl);
                if (!$this->isSafeHttpUrl($currentUrl)) {
                    return ['', $currentUrl, $contentType];
                }
                continue;
            }

            if (!is_string($body) || $body === '' || $httpCode >= 400 || strlen($body) > 2097152) {
                return ['', $currentUrl, $contentType];
            }

            return [$body, $currentUrl, $contentType];
        }

        return ['', $currentUrl, ''];
    }

    /**
     * 构建采集正文 HTML。
     *
     * @param string $html HTML。
     * @param string $baseUrl 基础地址。
     * @param array $images 图片地址。
     * @return string 经过清洗并解析相对资源地址的采集正文。
     */
    private function buildCollectContent(string $html, string $baseUrl, array $images): string
    {
        $body = $html;
        if (preg_match('/<article\b[^>]*>(.*?)<\/article>/is', $html, $matches)) {
            $body = (string)$matches[1];
        } elseif (preg_match('/<body\b[^>]*>(.*?)<\/body>/is', $html, $matches)) {
            $body = (string)$matches[1];
        }

        $body = preg_replace('/<(iframe|embed|object|audio|video|source)\b[^>]*>.*?<\/\1>/is', '', $body) ?? $body;
        $body = preg_replace('/\s(on[a-z]+)=["\'][^"\']*["\']/i', '', $body) ?? $body;
        $allowed = '<p><br><h1><h2><h3><h4><h5><h6><strong><b><em><i><u><ol><ul><li><blockquote><img>';
        $body = strip_tags($body, $allowed);
        $body = preg_replace_callback('/<img\b[^>]*\bsrc=["\']?([^"\'\s>]+)["\']?[^>]*>/i', function ($matches) use ($baseUrl) {
            $src = $this->resolveUrl((string)$matches[1], $baseUrl);
            if (!$this->isImageUrl($src)) {
                return '';
            }
            return '<p><img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" /></p>';
        }, $body) ?? $body;
        $body = preg_replace('/<p>\s*<\/p>/i', '', $body) ?? $body;
        $body = trim($body);

        if ($body === '' && $images !== []) {
            $items = [];
            foreach ($images as $image) {
                $items[] = '<p><img src="' . htmlspecialchars($image, ENT_QUOTES, 'UTF-8') . '" /></p>';
            }
            $body = implode('', $items);
        }

        return $body;
    }

    /**
     * 提取 HTML 图片。
     *
     * @param string $html HTML。
     * @param string $baseUrl 基础地址。
     * @return array 解析为完整地址并去重的网页图片列表。
     */
    private function extractHtmlImages(string $html, string $baseUrl): array
    {
        preg_match_all('/<img\b[^>]*\bsrc=["\']?([^"\'\s>]+)["\']?[^>]*>/i', $html, $matches);
        $images = [];
        foreach (($matches[1] ?? []) as $src) {
            $url = $this->resolveUrl((string)$src, $baseUrl);
            if ($this->isImageUrl($url) && $this->isSafeHttpUrl($url)) {
                $images[] = $url;
            }
        }

        return array_values(array_unique($images));
    }

    /**
     * 提取 meta 值。
     *
     * @param string $html HTML。
     * @param array $names 名称集合。
     * @return string 首个命中的页面元信息值；未命中时为空字符串。
     */
    private function extractMetaValue(string $html, array $names): string
    {
        foreach ($names as $name) {
            $pattern = '/<meta\b[^>]*(?:name|property)=["\']' . preg_quote($name, '/') . '["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/i';
            if (preg_match($pattern, $html, $matches)) {
                return $this->normalizeCollectText(html_entity_decode((string)$matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
        }

        return '';
    }

    /**
     * 提取标题。
     *
     * @param string $html HTML。
     * @return string 清理后的网页标题。
     */
    private function extractHtmlTitle(string $html): string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            return $this->normalizeCollectText(html_entity_decode((string)$matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return '';
    }

    /**
     * 自动判断内容类型。
     *
     * @param string $text 正文文本。
     * @param array $images 图片集合。
     * @return int 根据正文和图片推断的内容类型。
     */
    private function guessCollectedContentType(string $text, array $images): int
    {
        $textLength = mb_strlen($text);
        if ($images !== [] && $textLength < 80) {
            return ContentTypeEnum::IMAGE;
        }
        if ($images !== []) {
            return ContentTypeEnum::GRAPHIC;
        }

        return ContentTypeEnum::TEXT;
    }

    /**
     * 清除危险 HTML 块。
     *
     * @param string $html HTML。
     * @return string 移除危险块后的网页正文。
     */
    private function removeUnsafeHtmlBlocks(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html) ?? $html;
        return preg_replace('/<noscript\b[^>]*>.*?<\/noscript>/is', '', $html) ?? $html;
    }

    /**
     * 规范 HTML 编码。
     *
     * @param string $html HTML。
     * @return string 统一字符编码后的网页正文。
     */
    private function normalizeHtmlCharset(string $html): string
    {
        if (preg_match('/charset=["\']?([a-zA-Z0-9\-_]+)/i', $html, $matches)) {
            $charset = strtoupper((string)$matches[1]);
            if ($charset !== 'UTF-8') {
                $converted = @mb_convert_encoding($html, 'UTF-8', $charset);
                if (is_string($converted) && $converted !== '') {
                    return $converted;
                }
            }
        }

        return $html;
    }

    /**
     * 规范文本。
     *
     * @param string $text 文本。
     * @return string 清理标签、实体和多余空白后的文本。
     */
    private function normalizeCollectText(string $text): string
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[\t ]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
        return trim($text);
    }

    /**
     * 解析相对 URL。
     *
     * @param string $url 地址。
     * @param string $baseUrl 基础地址。
     * @return string 基于基础地址解析的资源地址。
     */
    private function resolveUrl(string $url, string $baseUrl): string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '' || str_starts_with($url, 'data:')) {
            return '';
        }
        if (str_starts_with($url, '//')) {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
            return $scheme . ':' . $url;
        }
        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }
        if (str_starts_with($url, '/')) {
            return rtrim((string)(parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https') . '://' . (string)parse_url($baseUrl, PHP_URL_HOST), '/') . $url;
        }

        $path = (string)parse_url($baseUrl, PHP_URL_PATH);
        $dir = rtrim(str_replace('\\', '/', dirname($path)), '/');
        return rtrim((string)(parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https') . '://' . (string)parse_url($baseUrl, PHP_URL_HOST) . $dir, '/') . '/' . $url;
    }

    /**
     * 判断是否图片地址。
     *
     * @param string $url 地址。
     * @return bool 地址是否按现有扩展名规则允许作为图片候选，排除已知音视频扩展名。
     */
    private function isImageUrl(string $url): bool
    {
        $path = strtolower((string)parse_url($url, PHP_URL_PATH));
        return (bool)preg_match('/\.(jpg|jpeg|png|gif|webp|bmp)$/i', $path) || !preg_match('/\.(mp3|wav|aac|flac|m4a|ogg|mp4|avi|mov|wmv|flv|mkv)$/i', $path);
    }

    /**
     * 获取 HTTP 代理地址。
     *
     * @return string 可用的代理地址；未配置时为空字符串。
     */
    private function getProxyUrl(): string
    {
        $env = $_SERVER;
        $proxy = $env['HTTPS_PROXY'] ?? $env['https_proxy'] ?? $env['HTTP_PROXY'] ?? $env['http_proxy'] ?? '';
        if ($proxy !== '' && filter_var($proxy, FILTER_VALIDATE_URL) !== false) {
            return $proxy;
        }
        return '';
    }

    /**
     * 判断是否安全公网 HTTP 地址。
     *
     * @param string $url 地址。
     * @return bool 地址是否为允许访问的公网网页地址。
     */
    private function isSafeHttpUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)) {
            return false;
        }

        $host = (string)($parts['host'] ?? '');
        if ($host === '' || in_array(strtolower($host), ['localhost', '127.0.0.1', '0.0.0.0'], true)) {
            return false;
        }

        // 仅验证主机名格式（不执行 DNS 查询，防止 DNS 卡死导致超时）
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        // 主机名必须包含至少一个点，防止裸主机名（如 http://internal/）
        return str_contains($host, '.') && preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$/iD', $host) === 1;
    }
}
