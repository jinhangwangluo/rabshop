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

use app\common\exception\AdminResponseException;
use app\common\model\content\Tag;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 标签服务。
 *
 * 统一维护平台与商户标签。owner_id=0 表示平台标签，owner_id=商户 ID 表示商户自有标签。
 * tag_type 用于区分业务域：1=内容/专题标签，2=商品标签。
 */
class TagService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * AI 建议标签默认导入分组名称。
     */
    private const AI_IMPORT_CATEGORY_NAME = 'AI推荐';

    /**
     * 内容/专题标签类型。
     */
    public const TAG_TYPE_CONTENT = 1;

    /**
     * 商品标签类型。
     */
    public const TAG_TYPE_PRODUCT = 2;

    /**
     * 内容关联类型。
     */
    public const RELATION_TYPE_ARTICLE = 'article';

    /**
     * 专题关联类型。
     */
    public const RELATION_TYPE_SPECIAL = 'special';

    /**
     * 商品关联类型。
     */
    public const RELATION_TYPE_PRODUCT = 'product';

    /**
     * 设置当前 Service 对应模型。
     *
     * @return string 标签模型类名。
     */
    protected function setModel(): string
    {
        return Tag::class;
    }

    /**
     * 归一化标签类型。
     *
     * @param int $tagType 标签类型原始值。
     * @return int 标签类型：1=内容/专题标签，2=商品标签。
     */
    public function normalizeTagType(int $tagType): int
    {
        return $tagType === self::TAG_TYPE_PRODUCT ? self::TAG_TYPE_PRODUCT : self::TAG_TYPE_CONTENT;
    }

    /**
     * 归一化并校验关联类型。
     *
     * @param string $relationType 关联类型：article、special、product。
     * @return string 校验后的关联类型。
     * @throws AdminResponseException 关联类型不在白名单时抛出。
     */
    public function normalizeRelationType(string $relationType): string
    {
        $relationType = strtolower(trim($relationType));
        if (!in_array($relationType, [
            self::RELATION_TYPE_ARTICLE,
            self::RELATION_TYPE_SPECIAL,
            self::RELATION_TYPE_PRODUCT,
        ], true)) {
            throw new AdminResponseException('标签关联类型不正确');
        }
        return $relationType;
    }

    /**
     * 根据关联类型获取标签类型。
     *
     * @param string $relationType 关联类型：article、special、product。
     * @return int 标签类型：内容/专题返回 1，商品返回 2。
     * @throws AdminResponseException 关联类型不在白名单时抛出。
     */
    public function getTagTypeByRelationType(string $relationType): int
    {
        return $this->normalizeRelationType($relationType) === self::RELATION_TYPE_PRODUCT
            ? self::TAG_TYPE_PRODUCT
            : self::TAG_TYPE_CONTENT;
    }

    /**
     * 获取指定所属者的标签分页列表。
     *
     * @param array $params 查询参数：tag_type、name、category_id、status、page、limit。
     * @param int $ownerId 所属者 ID，0=平台，商户 ID=商户。
     * @return array{list: array, total: int, limit: int, page: int, totalPage: int} 标签分页数据。
     */
    public function getResourceListByOwner(array $params, int $ownerId): array
    {
        [$page, $limit, $defaultLimit] = $this->getPagesValue();
        $page = max(1, $page ?: 1);
        $limit = max(1, $limit ?: $defaultLimit);

        $tagType = $this->normalizeTagType((int)($params['tag_type'] ?? self::TAG_TYPE_CONTENT));
        $where = [
            'owner_id' => $ownerId,
            'tag_type' => $tagType,
            'name' => trim((string)($params['name'] ?? '')),
            'category_id' => $params['category_id'] ?? '',
            'status' => $params['status'] ?? '',
        ];

        $query = $this->getModel()->searchWhere($where);
        $total = (int)(clone $query)->count();
        $list = $query
            ->order('sort desc,id desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        $list = $this->formatList($list, $tagType, $ownerId);
        return $this->listData($list, $total, [], $page, $limit)->toArray();
    }

    /**
     * 新增标签。
     *
     * @param array $data 标签数据：name、tag_type、category_id、effect_type、text_color、bg_color、border_color、icon、sort、status。
     * @param int $ownerId 所属者 ID，0=平台，商户 ID=商户。
     * @return array 保存后的标签数据，包含 value、label、category_name 等前端可用字段。
     * @throws AdminResponseException 标签分组不存在、名称为空、名称超长或同分组重名时抛出。
     */
    public function saveTag(array $data, int $ownerId): array
    {
        $saveData = $this->buildSaveData($data, $ownerId);
        $model = $this->create($saveData);
        return $this->formatTag($model->toArray(), $this->getCategoryMap($saveData['tag_type'], $ownerId));
    }

    /**
     * 更新标签。
     *
     * @param int $id 标签 ID。
     * @param array $data 标签数据：name、tag_type、category_id、effect_type、text_color、bg_color、border_color、icon、sort、status。
     * @param int $ownerId 所属者 ID，0=平台，商户 ID=商户。
     * @return bool 更新成功返回 true。
     * @throws AdminResponseException 标签不存在、类型变更或保存数据校验失败时抛出。
     */
    public function updateTag(int $id, array $data, int $ownerId): bool
    {
        $tag = $this->assertOwnerTag($id, $ownerId);
        $saveData = $this->buildSaveData($data, $ownerId, $id);
        if ((int)$tag['tag_type'] !== (int)$saveData['tag_type']) {
            throw new AdminResponseException('不能修改标签类型');
        }

        $this->update($id, $saveData);
        return true;
    }

    /**
     * 读取标签详情。
     *
     * @param int $id 标签 ID。
     * @param int $ownerId 所属者 ID，0=平台，商户 ID=商户。
     * @return array 标签详情，包含 value、label、category_name 等前端可用字段。
     * @throws AdminResponseException 标签不存在或不属于当前 owner 时抛出。
     */
    public function readTag(int $id, int $ownerId): array
    {
        $tag = $this->assertOwnerTag($id, $ownerId)->toArray();
        return $this->formatTag($tag, $this->getCategoryMap((int)$tag['tag_type'], $ownerId));
    }

    /**
     * 删除标签并清理关联关系。
     *
     * @param int $id 标签 ID。
     * @param int $ownerId 所属者 ID，0=平台，商户 ID=商户。
     * @return bool 删除成功返回 true。
     * @throws AdminResponseException 标签不存在或不属于当前 owner 时抛出。
     */
    public function deleteTag(int $id, int $ownerId): bool
    {
        $this->assertOwnerTag($id, $ownerId);
        app()->make(TagRelationService::class)->deleteByTagId($id);
        return (bool)$this->destroy($id);
    }

    /**
     * 修改标签状态。
     *
     * @param int $id 标签 ID。
     * @param int $status 状态：0=禁用，1=启用。
     * @param int $ownerId 所属者 ID，0=平台，商户 ID=商户。
     * @return bool 修改成功返回 true。
     * @throws AdminResponseException 标签不存在或不属于当前 owner 时抛出。
     */
    public function showTag(int $id, int $status, int $ownerId): bool
    {
        $this->assertOwnerTag($id, $ownerId);
        $this->update($id, ['status' => $status]);
        return true;
    }

    /**
     * 快捷新增标签。
     *
     * @param array $data 标签数据，至少包含 name、tag_type、category_id。
     * @param int $ownerId 所属者 ID，0=平台，商户 ID=商户。
     * @return array{id: int, value: int, label: string, name: string} 适合前端选择器直接追加的标签选项。
     * @throws AdminResponseException 保存校验失败时抛出。
     */
    public function quickAdd(array $data, int $ownerId): array
    {
        $tag = $this->saveTag($data + [
            'effect_type' => 1,
            'text_color' => '',
            'bg_color' => '',
            'border_color' => '',
            'sort' => 0,
            'status' => 1,
        ], $ownerId);

        return [
            'id' => (int)$tag['id'],
            'value' => (int)$tag['id'],
            'label' => (string)$tag['name'],
            'name' => (string)$tag['name'],
        ];
    }

    /**
     * 批量导入 AI 建议标签。
     *
     * @param array $suggestions AI 返回的建议标签，支持 name、reason、text_color、bg_color、border_color。
     * @param int $ownerId 所属者 ID，0=平台，商户 ID=商户。
     * @param int $categoryId 分组 ID，0 时自动使用或创建“AI推荐”分组。
     * @param int $tagType 标签类型：1=内容/专题标签，2=商品标签。
     * @return array 导入结果，包含 tag_ids、tag_list、created_count、exists_count、category_id。
     * @throws AdminResponseException 无可导入标签或分组无效时抛出。
     */
    public function importAiSuggestions(array $suggestions, int $ownerId, int $categoryId = 0, int $tagType = self::TAG_TYPE_CONTENT): array
    {
        $ownerId = max(0, $ownerId);
        $tagType = $this->normalizeTagType($tagType);
        $items = $this->normalizeAiImportSuggestions($suggestions);
        if ($items === []) {
            throw new AdminResponseException('暂无可导入的建议标签');
        }

        $categoryId = $categoryId > 0
            ? $this->assertImportCategory($categoryId, $tagType, $ownerId)
            : $this->ensureAiImportCategory($tagType, $ownerId);

        return $this->transaction(function () use ($items, $ownerId, $categoryId, $tagType) {
            $tagIds = [];
            $tagList = [];
            $categoryMap = $this->getCategoryMap($tagType, $ownerId);
            $createdCount = 0;
            $existsCount = 0;

            foreach ($items as $item) {
                $tag = $this->getModel()
                    ->where([
                        'owner_id' => $ownerId,
                        'tag_type' => $tagType,
                        'category_id' => $categoryId,
                        'name' => $item['name'],
                    ])
                    ->find();

                if ($tag) {
                    $formatted = $this->formatTag($tag->toArray(), $categoryMap);
                    $existsCount++;
                } else {
                    $formatted = $this->saveTag($item + [
                        'tag_type' => $tagType,
                        'category_id' => $categoryId,
                        'effect_type' => 1,
                        'icon' => '',
                        'sort' => 0,
                        'status' => 1,
                    ], $ownerId);
                    $createdCount++;
                }

                $tagIds[] = (int)$formatted['id'];
                $tagList[] = $formatted;
            }

            return [
                'tag_ids' => array_values(array_unique($tagIds)),
                'tag_list' => $tagList,
                'created_count' => $createdCount,
                'exists_count' => $existsCount,
                'category_id' => $categoryId,
            ];
        });
    }

    /**
     * 规范化 AI 建议标签导入数据。
     *
     * @param array $suggestions AI 建议标签原始数据。
     * @return array<int, array> 可传给标签保存逻辑的数据。
     */
    protected function normalizeAiImportSuggestions(array $suggestions): array
    {
        $result = [];
        $names = [];

        foreach ($suggestions as $index => $item) {
            if (is_string($item)) {
                $name = $item;
                $style = $this->pickAiTagStyle($index, []);
            } elseif (is_array($item)) {
                $name = (string)($item['name'] ?? ($item['label'] ?? ''));
                $style = $this->pickAiTagStyle($index, $item);
            } else {
                continue;
            }

            $name = mb_substr(trim(strip_tags($name)), 0, 30);
            if ($name === '' || isset($names[$name])) {
                continue;
            }

            $names[$name] = true;
            $result[] = [
                'name' => $name,
                'text_color' => $style['text_color'],
                'bg_color' => $style['bg_color'],
                'border_color' => $style['border_color'],
            ];

            if (count($result) >= 8) {
                break;
            }
        }

        return $result;
    }

    /**
     * 选择或校验 AI 标签颜色。
     *
     * @param int $index 标签序号。
     * @param array $item AI 建议标签。
     * @return array{text_color:string,bg_color:string,border_color:string} 标签颜色。
     */
    protected function pickAiTagStyle(int $index, array $item): array
    {
        return [
            'text_color' => $this->normalizeHexColor($item['text_color'] ?? ''),
            'bg_color' => $this->normalizeHexColor($item['bg_color'] ?? ''),
            'border_color' => $this->normalizeHexColor($item['border_color'] ?? ''),
        ];
    }

    /**
     * 归一化十六进制颜色。
     *
     * @param mixed $value 原始颜色值。
     * @return string 可写入标签表的颜色。
     */
    protected function normalizeHexColor(mixed $value): string
    {
        $color = trim((string)$value);
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            return strtoupper($color);
        }

        return '';
    }

    /**
     * 获取或创建 AI 建议标签默认分组。
     *
     * @param int $tagType 标签类型。
     * @param int $ownerId 所属者 ID。
     * @return int 分组 ID。
     */
    protected function ensureAiImportCategory(int $tagType, int $ownerId): int
    {
        /** @var TagCategoryService $categoryService */
        $categoryService = app()->make(TagCategoryService::class);
        $categoryType = $categoryService->getCategoryTypeByTagType($tagType);
        $category = $categoryService->getModel()
            ->where([
                'owner_id' => $ownerId,
                'type' => $categoryType,
                'pid' => 0,
                'name' => self::AI_IMPORT_CATEGORY_NAME,
            ])
            ->find();

        if ($category) {
            return (int)$category['id'];
        }

        $created = $categoryService->quickAdd(self::AI_IMPORT_CATEGORY_NAME, $tagType, $ownerId);
        return (int)$created['id'];
    }

    /**
     * 校验指定导入分组。
     *
     * @param int $categoryId 分组 ID。
     * @param int $tagType 标签类型。
     * @param int $ownerId 所属者 ID。
     * @return int 有效分组 ID。
     */
    protected function assertImportCategory(int $categoryId, int $tagType, int $ownerId): int
    {
        app()->make(TagCategoryService::class)->assertCategory($categoryId, $tagType, $ownerId);
        return $categoryId;
    }

    /**
     * 获取可选标签列表。
     *
     * @param array $params 查询参数：tag_type、category_id、name。
     * @param int $ownerId 所属者 ID，0=平台，商户 ID=商户。
     * @return array{list: array} 可选标签列表，单项包含 id、name、value、label、category_name 等字段。
     */
    public function options(array $params, int $ownerId): array
    {
        $tagType = $this->normalizeTagType((int)($params['tag_type'] ?? self::TAG_TYPE_CONTENT));
        $categoryId = (int)($params['category_id'] ?? 0);
        $name = trim((string)($params['name'] ?? ''));
        $categoryMap = $this->getCategoryMap($tagType, $ownerId);

        $list = $this->getModel()
            ->where([
                'owner_id' => $ownerId,
                'tag_type' => $tagType,
                'status' => 1,
            ])
            ->when($categoryId > 0, fn($query) => $query->where('category_id', $categoryId))
            ->when($name !== '', fn($query) => $query->whereLike('name', '%' . $name . '%'))
            ->order('sort desc,id desc')
            ->limit(500)
            ->select()
            ->toArray();

        return [
            'list' => array_map(fn(array $tag): array => $this->formatTag($tag, $categoryMap), $list),
        ];
    }

    /**
     * 统计分组下标签数量。
     *
     * @param int $categoryId 标签分组 ID。
     * @param int $ownerId 所属者 ID，0=平台，商户 ID=商户。
     * @param int $tagType 标签类型：1=内容/专题标签，2=商品标签。
     * @return int 标签数量。
     */
    public function countByCategory(int $categoryId, int $ownerId, int $tagType): int
    {
        return (int)$this->getModel()
            ->where([
                'category_id' => $categoryId,
                'owner_id' => $ownerId,
                'tag_type' => $this->normalizeTagType($tagType),
            ])
            ->count();
    }

    /**
     * 校验标签 ID 是否可用于指定 owner 和标签类型。
     *
     * @param array $tagIds 标签 ID 数组。
     * @param int $tagType 标签类型：1=内容/专题标签，2=商品标签。
     * @param int $ownerId 所属者 ID，0=平台，商户 ID=商户。
     * @return array<int> 去重后的有效标签 ID。
     * @throws AdminResponseException 存在非当前 owner、非当前类型或禁用标签时抛出。
     */
    public function validateTagIds(array $tagIds, int $tagType, int $ownerId): array
    {
        $tagIds = array_values(array_unique(array_filter(array_map('intval', $tagIds), fn(int $id): bool => $id > 0)));
        if ($tagIds === []) {
            return [];
        }

        $validIds = $this->getModel()
            ->whereIn('id', $tagIds)
            ->where([
                'owner_id' => $ownerId,
                'tag_type' => $this->normalizeTagType($tagType),
                'status' => 1,
            ])
            ->column('id');
        $validIds = array_values(array_map('intval', $validIds));

        if (count($validIds) !== count($tagIds)) {
            throw new AdminResponseException('存在不可用标签');
        }

        return $validIds;
    }

    /**
     * 批量获取标签映射。
     *
     * @param array $tagIds 标签 ID 数组。
     * @return array<int, array> 以标签 ID 为 key 的标签数据映射。
     */
    public function getMapByIds(array $tagIds): array
    {
        $tagIds = array_values(array_unique(array_filter(array_map('intval', $tagIds), fn(int $id): bool => $id > 0)));
        if ($tagIds === []) {
            return [];
        }

        $list = $this->getModel()
            ->whereIn('id', $tagIds)
            ->select()
            ->toArray();
        $categoryIds = array_values(array_unique(array_filter(array_map(
            fn(array $item): int => (int)($item['category_id'] ?? 0),
            $list
        ))));
        $categoryMap = [];
        if ($categoryIds !== []) {
            $categories = app()->make(\app\common\service\setting\CategoryService::class)
                ->getModel()
                ->whereIn('id', $categoryIds)
                ->field(['id', 'name'])
                ->select()
                ->toArray();
            foreach ($categories as $category) {
                $categoryMap[(int)$category['id']] = $category;
            }
        }

        $map = [];
        foreach ($list as $tag) {
            $map[(int)$tag['id']] = $this->formatTag($tag, $categoryMap);
        }
        return $map;
    }

    /**
     * 为资源列表附加 tag_ids 和 tag_list。
     *
     * @param array $list 资源列表，单项必须包含 id。
     * @param string $relationType 关联类型：article、special、product。
     * @return array 附加标签后的资源列表。
     * @throws AdminResponseException 关联类型不正确时抛出。
     */
    public function attachTagsToList(array $list, string $relationType): array
    {
        return app()->make(TagRelationService::class)->appendTagsToList($list, $relationType);
    }

    /**
     * 为资源详情附加 tag_ids 和 tag_list。
     *
     * @param array $detail 资源详情，包含 id 时才查询关联标签。
     * @param string $relationType 关联类型：article、special、product。
     * @return array 附加标签后的资源详情。
     * @throws AdminResponseException 关联类型不正确时抛出。
     */
    public function attachTagsToDetail(array $detail, string $relationType): array
    {
        if (empty($detail['id'])) {
            $detail['tag_ids'] = [];
            $detail['tag_list'] = [];
            return $detail;
        }

        $tags = app()->make(TagRelationService::class)->getRelationTags($relationType, (int)$detail['id']);
        $detail['tag_ids'] = $tags['tag_ids'];
        $detail['tag_list'] = $tags['tag_list'];
        return $detail;
    }

    /**
     * 构造标签保存数据。
     *
     * @param array $data 原始标签数据。
     * @param int $ownerId 所属者 ID，0=平台，商户 ID=商户。
     * @param int $id 标签 ID，编辑时用于排除自身重名。
     * @return array 可直接写入 tag 表的数据。
     * @throws AdminResponseException 分组无效、名称为空、名称超长或同分组重名时抛出。
     */
    protected function buildSaveData(array $data, int $ownerId, int $id = 0): array
    {
        $tagType = $this->normalizeTagType((int)($data['tag_type'] ?? self::TAG_TYPE_CONTENT));
        $categoryId = (int)($data['category_id'] ?? 0);
        if ($categoryId <= 0) {
            throw new AdminResponseException('请选择标签分组');
        }
        app()->make(TagCategoryService::class)->assertCategory($categoryId, $tagType, $ownerId);

        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            throw new AdminResponseException('标签名称不能为空');
        }
        if (mb_strlen($name) > 30) {
            throw new AdminResponseException('标签名称最多30个字符');
        }

        $existsQuery = $this->getModel()
            ->where([
                'owner_id' => $ownerId,
                'tag_type' => $tagType,
                'category_id' => $categoryId,
                'name' => $name,
            ]);
        if ($id > 0) {
            $existsQuery->where('id', '<>', $id);
        }
        if ($existsQuery->find()) {
            throw new AdminResponseException('同分组下标签名称已存在');
        }

        return [
            'owner_id' => $ownerId,
            'tag_type' => $tagType,
            'category_id' => $categoryId,
            'name' => $name,
            'effect_type' => ((int)($data['effect_type'] ?? 1)) === 2 ? 2 : 1,
            'text_color' => trim((string)($data['text_color'] ?? '')),
            'bg_color' => trim((string)($data['bg_color'] ?? '')),
            'border_color' => trim((string)($data['border_color'] ?? '')),
            'icon' => trim((string)($data['icon'] ?? '')),
            'sort' => (int)($data['sort'] ?? 0),
            'status' => (int)($data['status'] ?? 1),
        ];
    }

    /**
     * 校验标签所属者。
     *
     * @param int $id 标签 ID。
     * @param int $ownerId 所属者 ID，0=平台，商户 ID=商户。
     * @return mixed ThinkPHP 模型实例。
     * @throws AdminResponseException 标签不存在或不属于当前 owner 时抛出。
     */
    protected function assertOwnerTag(int $id, int $ownerId)
    {
        $tag = $this->getModel()
            ->where(['id' => $id, 'owner_id' => $ownerId])
            ->find();
        if (!$tag) {
            throw new AdminResponseException('标签不存在');
        }
        return $tag;
    }

    /**
     * 获取指定标签类型与 owner 下的分组映射。
     *
     * @param int $tagType 标签类型：1=内容/专题标签，2=商品标签。
     * @param int $ownerId 所属者 ID，0=平台，商户 ID=商户。
     * @return array<int, array> 以分组 ID 为 key 的分组映射。
     */
    protected function getCategoryMap(int $tagType, int $ownerId): array
    {
        return app()->make(TagCategoryService::class)->getFlatMap($tagType, $ownerId);
    }

    /**
     * 格式化标签列表。
     *
     * @param array $list 标签列表。
     * @param int $tagType 标签类型：1=内容/专题标签，2=商品标签。
     * @param int $ownerId 所属者 ID，0=平台，商户 ID=商户。
     * @return array 格式化后的标签列表。
     */
    protected function formatList(array $list, int $tagType, int $ownerId): array
    {
        $categoryMap = $this->getCategoryMap($tagType, $ownerId);
        return array_map(fn(array $tag): array => $this->formatTag($tag, $categoryMap), $list);
    }

    /**
     * 格式化单个标签。
     *
     * @param array $tag 原始标签数据。
     * @param array $categoryMap 分组映射，key 为分组 ID。
     * @return array 格式化后的标签数据，补充 value、label、category_name，并归一化数值字段。
     */
    protected function formatTag(array $tag, array $categoryMap = []): array
    {
        $categoryId = (int)($tag['category_id'] ?? 0);
        $effectType = (int)($tag['effect_type'] ?? 1);
        $tag['id'] = (int)($tag['id'] ?? 0);
        $tag['value'] = $tag['id'];
        $tag['label'] = (string)($tag['name'] ?? '');
        $tag['owner_id'] = (int)($tag['owner_id'] ?? 0);
        $tag['tag_type'] = (int)($tag['tag_type'] ?? self::TAG_TYPE_CONTENT);
        $tag['category_id'] = $categoryId;
        $tag['category_name'] = (string)($categoryMap[$categoryId]['name'] ?? '');
        $tag['text_color'] = trim((string)($tag['text_color'] ?? ''));
        $tag['bg_color'] = trim((string)($tag['bg_color'] ?? ''));
        $tag['border_color'] = trim((string)($tag['border_color'] ?? ''));
        $tag['icon'] = trim((string)($tag['icon'] ?? ''));
        $tag['effect_type'] = $effectType === 2 && $tag['icon'] !== '' ? 2 : 1;
        $tag['sort'] = (int)($tag['sort'] ?? 0);
        $tag['status'] = (int)($tag['status'] ?? 1);
        return $tag;
    }
}
