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
 * 专题分类服务
 */
class SpecialCategoryService extends CategoryService
{
    /**
     * 专题分类类型
     */
    protected int $categoryType = self::SPECIAL_TYPE;

    /**
     * 获取平台专题分类树。
     *
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getPlatformTree(): array
    {
        return $this->getCategoryTree(self::SPECIAL_TYPE, 0);
    }

    /**
     * 获取后台专题分类树。
     *
     * @param array $params 筛选参数：name、status。
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getAdminTree(array $params = []): array
    {
        $name = trim((string)($params['name'] ?? ''));
        $status = $params['status'] ?? '';

        $query = $this->getModel()->where([
            'type'     => $this->categoryType,
            'owner_id' => 0,
        ]);

        if ($name !== '') {
            $query->where('name', 'like', '%' . $name . '%');
        }
        if ($status !== '') {
            $query->where('status', (int)$status);
        }

        $list = $query
            ->field(['name as label', 'id as value', 'id', 'pid', 'sort', 'status', 'name', 'image', 'banner_image', 'description'])
            ->order('id desc,sort desc')
            ->select()
            ->toArray();

        return get_tree_children($list);
    }

    /**
     * 校验平台专题分类可用于专题发布。
     *
     * @param int $categoryId 分类 ID。
     * @return void
     * @throws AdminResponseException
     */
    public function assertPlatformCategory(int $categoryId): void
    {
        if ($categoryId <= 0) {
            throw new AdminResponseException('请选择专题分类');
        }

        $exists = $this->getModel()
            ->where([
                'id' => $categoryId,
                'type' => $this->categoryType,
                'owner_id' => 0,
            ])
            ->find();

        if (!$exists) {
            throw new AdminResponseException('专题分类不存在');
        }
    }

    /**
     * 保存专题分类。
     *
     * @param array $data 分类数据。
     * @return mixed
     * @throws AdminResponseException
     */
    public function resourceSave(array $data)
    {
        $data = $this->buildSaveData($data);
        return parent::resourceSave($data);
    }

    /**
     * 更新专题分类。
     *
     * @param int|array $id 分类 ID。
     * @param array $data 分类数据。
     * @return bool
     * @throws AdminResponseException
     */
    public function resourceUpdate(int|array $id, array $data)
    {
        $id = (int)$id;
        $model = $this->getModel()->where([
            'id'       => $id,
            'type'     => $this->categoryType,
            'owner_id' => 0,
        ])->find();
        if (!$model) {
            throw new AdminResponseException('专题分类不存在');
        }

        $data = $this->buildSaveData($data, $id);
        return parent::resourceUpdate($id, $data);
    }

    /**
     * 快捷添加专题分类
     * @param string $name 分类名称
     * @param int $pid 父级ID（0为一级分类）
     * @param string $image
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function quickAdd(string $name, int $pid = 0, string $image = ''): array
    {
        $data = $this->buildSaveData([
            'name'   => $name,
            'pid'    => $pid,
            'path'   => $pid > 0 ? [$pid] : [],
            'image'  => $image,
            'sort'   => 0,
            'status' => 1,
        ]);

        $model = parent::resourceSave($data);

        return [
            'id'    => $model->id,
            'label' => $model->name,
            'value' => $model->id,
            'pid'   => $model->pid,
        ];
    }

    /**
     * 获取移动端专题分类树。
     *
     * 返回一级分类及其二级子分类，并补充图标、Banner、热门搜索等展示字段。
     *
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getMobileTree(array $categoryIds = []): array
    {
        if (!app()->make(ContentDisplayConfigService::class)->isSpecialEnabled()) {
            return [];
        }

        return $this->getCategoryTree(self::SPECIAL_TYPE, 0, $categoryIds);
    }

    /**
     * 组装保存数据。
     *
     * @param array $data 原始分类数据。
     * @param int $id 编辑时的分类 ID。
     * @return array 可写入 category 表的数据。
     * @throws AdminResponseException
     */
    protected function buildSaveData(array $data, int $id = 0): array
    {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            throw new AdminResponseException('分类名称不能为空');
        }
        if (mb_strlen($name) > 30) {
            throw new AdminResponseException('分类名称最多30个字符');
        }

        $image = $this->normalizeImage($data['image'] ?? '');
        if ($image === '') {
            throw new AdminResponseException('专题分类图片不能为空');
        }

        $bannerImage = $this->normalizeImage($data['banner_image'] ?? '');
        $path = $this->normalizePath($data['path'] ?? [], (int)($data['pid'] ?? 0));
        $pid = $path ? (int)end($path) : 0;
        if ($id > 0 && $pid === $id) {
            throw new AdminResponseException('上级分类不能选择自己');
        }

        if ($pid > 0) {
            $parent = $this->getModel()->where([
                'id'       => $pid,
                'type'     => $this->categoryType,
                'owner_id' => 0,
            ])->find();
            if (!$parent) {
                throw new AdminResponseException('父级分类不存在');
            }
            if ((int)$parent->pid !== 0) {
                throw new AdminResponseException('最多支持二级分类');
            }
            $path = [(int)$parent->id];
        }

        if ($id > 0 && $pid > 0) {
            $childCount = $this->getModel()->where([
                'pid'      => $id,
                'type'     => $this->categoryType,
                'owner_id' => 0,
            ])->count();
            if ($childCount > 0) {
                throw new AdminResponseException('存在子分类的一级分类不能调整为二级分类');
            }
        }

        $duplicateQuery = $this->getModel()->where([
            'type'     => $this->categoryType,
            'owner_id' => 0,
            'pid'      => $pid,
            'name'     => $name,
        ]);
        if ($id > 0) {
            $duplicateQuery->where('id', '<>', $id);
        }
        if ($duplicateQuery->count()) {
            throw new AdminResponseException('同级分类名称已存在');
        }

        return [
            'name'         => $name,
            'image'        => $image,
            'banner_image' => $bannerImage,
            'description'  => trim((string)($data['description'] ?? '')),
            'pid'          => $pid,
            'path'         => $path,
            'type'         => $this->categoryType,
            'owner_id'     => 0,
            'sort'         => (int)($data['sort'] ?? 0),
            'status'       => (int)($data['status'] ?? 1) === 0 ? 0 : 1,
            'other'        => (string)($data['other'] ?? ''),
        ];
    }

    /**
     * 归一化图片字段。
     *
     * @param mixed $image 图片值。
     * @return string 图片地址。
     */
    protected function normalizeImage(mixed $image): string
    {
        if (is_array($image)) {
            foreach (['path', 'url', 'src'] as $key) {
                if (!empty($image[$key])) {
                    return trim((string)$image[$key]);
                }
            }
            $image = array_values(array_filter($image))[0] ?? '';
        }

        return trim((string)$image);
    }

    /**
     * 归一化层级路径。
     *
     * @param mixed $path 路径数组或字符串。
     * @param int $pid 父级 ID。
     * @return array
     */
    protected function normalizePath(mixed $path, int $pid = 0): array
    {
        if (is_string($path)) {
            $path = trim($path, '/,');
            $path = $path === '' ? [] : preg_split('/[\/,]+/', $path);
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

}
