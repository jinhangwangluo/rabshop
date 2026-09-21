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
use app\common\service\setting\CategoryService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 标签分组服务。
 *
 * 标签分组复用 category 表，通过 CategoryService 类型常量区分：
 * - CategoryService::CONTENT_TAG_GROUP_TYPE：内容/专题标签分组。
 * - CategoryService::PRODUCT_TAG_GROUP_TYPE：商品标签分组。
 * owner_id 用于隔离平台和商户数据，0 表示平台，商户 ID 表示商户自有分组。
 */
class TagCategoryService extends CategoryService
{
    /**
     * 根据标签类型获取分组分类类型。
     *
     * @param int $tagType 标签类型：1=内容/专题标签，2=商品标签。
     * @return int CategoryService 中对应的分类类型常量。
     */
    public function getCategoryTypeByTagType(int $tagType): int
    {
        return match ($tagType) {
            TagService::TAG_TYPE_PRODUCT => self::PRODUCT_TAG_GROUP_TYPE,
            default => self::CONTENT_TAG_GROUP_TYPE,
        };
    }

    /**
     * 获取标签分组树。
     *
     * @param int $tagType 标签类型：1=内容/专题标签，2=商品标签。
     * @param int $ownerId 所属者 ID，0=平台，商户 ID=商户。
     * @return array 分组树，节点包含 id、pid、owner_id、name、sort、status、path、label、value、children。
     */
    public function getTreeByTagType(int $tagType, int $ownerId): array
    {
        $categoryType = $this->getCategoryTypeByTagType($tagType);
        $list = $this->getModel()
            ->where(['type' => $categoryType, 'owner_id' => $ownerId])
            ->field(['id', 'pid', 'owner_id', 'name', 'sort', 'status', 'path', 'image', 'description'])
            ->order('sort desc,id desc')
            ->select()
            ->toArray();

        foreach ($list as &$item) {
            $item['label'] = $item['name'];
            $item['value'] = (int)$item['id'];
        }

        return get_tree_children($list);
    }

    /**
     * 获取标签分组列表。
     *
     * @param array $params 查询参数：tag_type、name、status。
     * @param int $ownerId 所属者 ID，0=平台，商户 ID=商户。
     * @return array{list: array, total: int} 树形分组列表和总数。
     */
    public function getListByOwner(array $params, int $ownerId): array
    {
        $tagType = app()->make(TagService::class)->normalizeTagType((int)($params['tag_type'] ?? TagService::TAG_TYPE_CONTENT));
        $categoryType = $this->getCategoryTypeByTagType($tagType);
        $name = trim((string)($params['name'] ?? ''));
        $status = $params['status'] ?? '';

        $list = $this->getModel()
            ->where(['type' => $categoryType, 'owner_id' => $ownerId])
            ->when($name !== '', fn($query) => $query->whereLike('name', '%' . $name . '%'))
            ->when($status !== '', fn($query) => $query->where('status', (int)$status))
            ->field(['id', 'pid', 'owner_id', 'name', 'sort', 'status', 'path', 'create_time'])
            ->order('sort desc,id desc')
            ->select()
            ->toArray();

        return [
            'list' => get_tree_children($list),
            'total' => count($list),
        ];
    }

    /**
     * 获取平铺分组映射。
     *
     * @param int $tagType 标签类型：1=内容/专题标签，2=商品标签。
     * @param int $ownerId 所属者 ID，0=平台，商户 ID=商户。
     * @return array<int, array> 以分组 ID 为 key 的分组信息映射。
     */
    public function getFlatMap(int $tagType, int $ownerId): array
    {
        $categoryType = $this->getCategoryTypeByTagType($tagType);
        $list = $this->getModel()
            ->where(['type' => $categoryType, 'owner_id' => $ownerId])
            ->field(['id', 'name', 'pid', 'sort', 'status'])
            ->order('sort desc,id desc')
            ->select()
            ->toArray();

        $map = [];
        foreach ($list as $item) {
            $map[(int)$item['id']] = $item;
        }
        return $map;
    }

    /**
     * 保存标签分组。
     *
     * @param array $data 分组数据：name、tag_type、path、pid、sort、status。
     * @param int $ownerId 所属者 ID，0=平台，商户 ID=商户。
     * @param int $id 分组 ID，0 表示新增，大于 0 表示编辑。
     * @return array 保存后的分组数据。
     * @throws AdminResponseException 分组名称为空、同级重名、上级分组非法或分组不存在时抛出。
     */
    public function saveCategory(array $data, int $ownerId, int $id = 0): array
    {
        $tagType = app()->make(TagService::class)->normalizeTagType((int)($data['tag_type'] ?? TagService::TAG_TYPE_CONTENT));
        $categoryType = $this->getCategoryTypeByTagType($tagType);
        $saveData = $this->buildSaveData($data, $ownerId, $categoryType, $id);

        if ($id > 0) {
            $this->assertOwnerCategory($id, $ownerId, $categoryType);
            $this->update($id, $saveData);
            return $this->readCategory($id, $ownerId, $tagType);
        }

        $model = $this->create($saveData);
        return $model->toArray();
    }

    /**
     * 读取标签分组。
     *
     * @param int $id 分组 ID。
     * @param int $ownerId 所属者 ID，0=平台，商户 ID=商户。
     * @param int $tagType 标签类型；0 表示只限制为标签分组，不限制内容/商品类型。
     * @return array 分组详情，附加 tag_type 字段。
     * @throws AdminResponseException 分组不存在或不属于当前 owner 时抛出。
     */
    public function readCategory(int $id, int $ownerId, int $tagType = 0): array
    {
        $categoryType = $tagType > 0
            ? $this->getCategoryTypeByTagType($tagType)
            : 0;

        $query = $this->getModel()->where(['id' => $id, 'owner_id' => $ownerId]);
        if ($categoryType > 0) {
            $query->where('type', $categoryType);
        } else {
            $query->whereIn('type', [self::CONTENT_TAG_GROUP_TYPE, self::PRODUCT_TAG_GROUP_TYPE]);
        }
        $category = $query->find();
        if (!$category) {
            throw new AdminResponseException('标签分组不存在');
        }

        $data = $category->toArray();
        $data['tag_type'] = $this->getTagTypeByCategoryType((int)$data['type']);
        return $data;
    }

    /**
     * 快捷添加分组。
     *
     * @param string $name 分组名称。
     * @param int $tagType 标签类型：1=内容/专题标签，2=商品标签。
     * @param int $ownerId 所属者 ID，0=平台，商户 ID=商户。
     * @param int $pid 父级分组 ID，0 表示一级分组。
     * @return array{id: int, value: int, label: string, pid: int} 适合前端选择器直接使用的分组选项。
     * @throws AdminResponseException 分组校验失败时抛出。
     */
    public function quickAdd(string $name, int $tagType, int $ownerId, int $pid = 0): array
    {
        $data = $this->saveCategory([
            'name' => $name,
            'tag_type' => $tagType,
            'pid' => $pid,
            'path' => $pid > 0 ? [$pid] : [],
            'status' => 1,
        ], $ownerId);

        return [
            'id' => (int)$data['id'],
            'value' => (int)$data['id'],
            'label' => (string)$data['name'],
            'pid' => (int)$data['pid'],
        ];
    }

    /**
     * 修改状态。
     *
     * @param int $id 分组 ID。
     * @param int $status 状态：0=禁用，1=启用。
     * @param int $ownerId 所属者 ID，0=平台，商户 ID=商户。
     * @return bool 修改成功返回 true。
     * @throws AdminResponseException 分组不存在或不属于当前 owner 时抛出。
     */
    public function showCategory(int $id, int $status, int $ownerId): bool
    {
        $category = $this->assertOwnerCategory($id, $ownerId);
        $this->update($id, ['status' => $status]);
        return (bool)$category;
    }

    /**
     * 删除分组。
     *
     * 分组存在子分组或已有标签时不允许删除，避免标签归属丢失。
     *
     * @param int $id 分组 ID。
     * @param int $ownerId 所属者 ID，0=平台，商户 ID=商户。
     * @return bool 删除成功返回 true。
     * @throws AdminResponseException 分组不存在、存在子分组或存在标签时抛出。
     */
    public function deleteCategory(int $id, int $ownerId): bool
    {
        $category = $this->assertOwnerCategory($id, $ownerId);
        $categoryData = $category->toArray();

        if ($this->count(['pid' => $id, 'type' => (int)$categoryData['type'], 'owner_id' => $ownerId]) > 0) {
            throw new AdminResponseException('请先删除子分组');
        }

        $tagType = $this->getTagTypeByCategoryType((int)$categoryData['type']);
        if (app()->make(TagService::class)->countByCategory($id, $ownerId, $tagType) > 0) {
            throw new AdminResponseException('该分组下存在标签，不能删除');
        }

        return (bool)$this->delete($id);
    }

    /**
     * 校验分组归属。
     *
     * @param int $categoryId 分组 ID。
     * @param int $tagType 标签类型：1=内容/专题标签，2=商品标签。
     * @param int $ownerId 所属者 ID，0=平台，商户 ID=商户。
     * @return array 分组数据。
     * @throws AdminResponseException 分组不存在、类型不匹配或不属于当前 owner 时抛出。
     */
    public function assertCategory(int $categoryId, int $tagType, int $ownerId): array
    {
        $categoryType = $this->getCategoryTypeByTagType($tagType);
        $category = $this->assertOwnerCategory($categoryId, $ownerId, $categoryType);
        return $category->toArray();
    }

    /**
     * 根据分类类型反推标签类型。
     *
     * @param int $categoryType CategoryService 中的标签分组分类类型。
     * @return int 标签类型：1=内容/专题标签，2=商品标签。
     */
    protected function getTagTypeByCategoryType(int $categoryType): int
    {
        return $categoryType === self::PRODUCT_TAG_GROUP_TYPE
            ? TagService::TAG_TYPE_PRODUCT
            : TagService::TAG_TYPE_CONTENT;
    }

    /**
     * 组装保存数据。
     *
     * @param array $data 原始分组数据：name、path、pid、sort、status。
     * @param int $ownerId 所属者 ID，0=平台，商户 ID=商户。
     * @param int $categoryType CategoryService 中的标签分组分类类型。
     * @param int $id 分组 ID，编辑时用于排除自身重名。
     * @return array 可直接写入 category 表的数据。
     * @throws AdminResponseException 名称为空、超长、上级非法或同级重名时抛出。
     */
    protected function buildSaveData(array $data, int $ownerId, int $categoryType, int $id = 0): array
    {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            throw new AdminResponseException('分组名称不能为空');
        }
        if (mb_strlen($name) > 30) {
            throw new AdminResponseException('分组名称最多30个字符');
        }

        $path = $this->normalizePath($data['path'] ?? []);
        $pid = $path !== [] ? (int)end($path) : (int)($data['pid'] ?? 0);
        if ($pid > 0) {
            $parent = $this->assertOwnerCategory($pid, $ownerId, $categoryType);
            if ((int)$parent['pid'] !== 0) {
                throw new AdminResponseException('最多支持二级分组');
            }
            if ($id > 0 && $pid === $id) {
                throw new AdminResponseException('上级分组不能选择自己');
            }
            $path = [(int)$parent['id']];
        }

        $existsQuery = $this->getModel()
            ->where([
                'type' => $categoryType,
                'owner_id' => $ownerId,
                'pid' => $pid,
                'name' => $name,
            ]);
        if ($id > 0) {
            $existsQuery->where('id', '<>', $id);
        }
        if ($existsQuery->find()) {
            throw new AdminResponseException('同级分组名称已存在');
        }

        return [
            'owner_id' => $ownerId,
            'type' => $categoryType,
            'name' => $name,
            'pid' => $pid,
            'path' => $path,
            'sort' => (int)($data['sort'] ?? 0),
            'status' => (int)($data['status'] ?? 1),
            'other' => '{}',
        ];
    }

    /**
     * 归一化 path。
     *
     * @param mixed $path 前端提交的级联 path。
     * @return array<int> 过滤后的正整数 ID 数组。
     */
    protected function normalizePath(mixed $path): array
    {
        if (!is_array($path)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $path), fn(int $id): bool => $id > 0));
    }

    /**
     * 校验分组所属者与类型。
     *
     * @param int $id 分组 ID。
     * @param int $ownerId 所属者 ID，0=平台，商户 ID=商户。
     * @param int $categoryType 指定分类类型；0 时仅允许内容/商品标签分组。
     * @return mixed ThinkPHP 模型实例。
     * @throws AdminResponseException 分组不存在、类型不匹配或不属于当前 owner 时抛出。
     */
    protected function assertOwnerCategory(int $id, int $ownerId, int $categoryType = 0)
    {
        $query = $this->getModel()->where(['id' => $id, 'owner_id' => $ownerId]);
        if ($categoryType > 0) {
            $query->where('type', $categoryType);
        } else {
            $query->whereIn('type', [self::CONTENT_TAG_GROUP_TYPE, self::PRODUCT_TAG_GROUP_TYPE]);
        }
        $category = $query->find();
        if (!$category) {
            throw new AdminResponseException('标签分组不存在');
        }
        return $category;
    }
}
