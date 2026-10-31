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

namespace app\common\service\diy;

use app\common\exception\AdminResponseException;
use app\common\model\diy\DiyPageLink;
use app\common\service\setting\CategoryService;

/**
 * DIY分类服务
 */
class DiyCategoryService extends CategoryService
{
    /**
     * 分类类型
     * @var int
     */
    protected int $categoryType = CategoryService::DIY_TYPE;

    /**
     * 获取分类列表
     */
    public function getResourceList(array $params = [], array|string $fields = [], array $with = [], string|array|null $sort = null): array
    {
        $list = $this->getTree();
        $count = $this->getModel()->where([
            'type' => $this->categoryType,
            'owner_id' => 0,
        ])->count();

        return compact('list', 'count');
    }

    /**
     * 获取分类树
     */
    public function getTree(?int $status = null): array
    {
        $query = $this->getModel()->where([
            'type' => $this->categoryType,
            'owner_id' => 0,
        ]);

        if ($status !== null) {
            $query->where('status', $status);
        }

        $list = $query->field(['name as label', 'id as value', 'id', 'pid', 'sort', 'status', 'name', 'path'])
            ->order('sort desc,id desc')
            ->select()
            ->toArray();

        return get_tree_children($list);
    }

    /**
     * 保存分类
     */
    public function resourceSave(array $data)
    {
        $data = $this->normalizeData($data);
        $this->validateCategoryData($data);
        return parent::resourceSave($data);
    }

    /**
     * 更新分类
     */
    public function resourceUpdate(int|array $id, array $data)
    {
        $id = (int)$id;
        $model = $this->get($id);
        if (!$model) {
            throw new AdminResponseException('分类不存在');
        }

        $data = $this->normalizeData($data);
        $this->validateCategoryData($data, $id);
        return parent::resourceUpdate($id, $data);
    }

    /**
     * 删除分类
     */
    public function resourceDelete(int $id)
    {
        $model = $this->get($id);
        if (!$model) {
            throw new AdminResponseException('分类不存在');
        }

        $hasChild = $this->getModel()->where([
            'pid' => $id,
            'type' => $this->categoryType,
            'owner_id' => 0,
        ])->count();
        if ($hasChild) {
            throw new AdminResponseException('请先删除子分类');
        }

        $hasLinks = app()->make(DiyPageLink::class)
            ->where('cate_id', $id)
            ->count();
        if ($hasLinks) {
            throw new AdminResponseException('该分类下存在链接，请先删除链接记录');
        }

        return parent::resourceDelete($id);
    }

    /**
     * 快捷添加分类
     */
    public function quickAdd(string $name, int $pid = 0): array
    {
        $data = $this->normalizeData([
            'name' => $name,
            'pid' => $pid,
            'path' => $pid > 0 ? [$pid] : [],
            'sort' => 0,
            'status' => 1,
        ]);

        $this->validateCategoryData($data);
        $model = parent::resourceSave($data);

        return [
            'id' => $model->id,
            'label' => $model->name,
            'value' => $model->id,
            'pid' => $model->pid,
        ];
    }

    /**
     * 数据规范化
     */
    protected function normalizeData(array $data): array
    {
        $path = $this->normalizePath($data['path'] ?? [], (int)($data['pid'] ?? 0));
        $pid = $path ? (int)end($path) : 0;

        return [
            'name' => trim((string)($data['name'] ?? '')),
            'sort' => (int)($data['sort'] ?? 0),
            'status' => isset($data['status']) ? (int)$data['status'] : 1,
            'path' => $path,
            'pid' => $pid,
            'owner_id' => 0,
            'type' => $this->categoryType,
        ];
    }

    /**
     * 标准化路径数据
     */
    protected function normalizePath(mixed $path, int $pid = 0): array
    {
        if (is_string($path)) {
            $path = $path === '' ? [] : array_filter(array_map('intval', explode(',', $path)));
        }

        if (!is_array($path)) {
            $path = [];
        }

        $path = array_values(array_filter(array_map('intval', $path)));
        if (!$path && $pid > 0) {
            $path = [$pid];
        }

        return $path;
    }

    /**
     * 校验分类数据
     */
    protected function validateCategoryData(array $data, int $id = 0): void
    {
        if ($data['name'] === '') {
            throw new AdminResponseException('分类名称不能为空');
        }

        $pid = (int)$data['pid'];
        if ($id > 0 && $pid === $id) {
            throw new AdminResponseException('上级分类不能选择自己');
        }

        if ($pid > 0) {
            $parent = $this->getModel()->where([
                'id' => $pid,
                'type' => $this->categoryType,
                'owner_id' => 0,
            ])->find();
            if (!$parent) {
                throw new AdminResponseException('父级分类不存在');
            }
            if ((int)$parent->pid !== 0) {
                throw new AdminResponseException('最多支持二级分类');
            }
        }

        $duplicateQuery = $this->getModel()->where([
            'type' => $this->categoryType,
            'owner_id' => 0,
            'pid' => $pid,
            'name' => $data['name'],
        ]);
        if ($id > 0) {
            $duplicateQuery->where('id', '<>', $id);
        }
        if ($duplicateQuery->count()) {
            throw new AdminResponseException('同级分类名称已存在');
        }

        if ($id > 0 && $pid > 0) {
            $childCount = $this->getModel()->where([
                'pid' => $id,
                'type' => $this->categoryType,
                'owner_id' => 0,
            ])->count();
            if ($childCount > 0) {
                throw new AdminResponseException('存在子分类的一级分类不能调整为二级分类');
            }
        }
    }
}
