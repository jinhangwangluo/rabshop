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
use app\common\model\content\ArticleCategory;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\setting\CategoryService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 文章分类服务
 * Class ArticleCategoryService
 * @package app\common\service\content
 */
class ArticleCategoryService extends CategoryService
{
    /**
     * 文章分类类型
     * @var int
     */
    protected int $categoryType = self::ARTICLE_TYPE;

    /**
     * 快捷添加分类
     * @param string $name 分类名称
     * @param int $pid 父级ID（0为一级分类）
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function quickAdd(string $name, int $pid = 0): array
    {
        $name = trim($name);
        if (!$name) {
            throw new AdminResponseException('分类名称不能为空');
        }
        if (mb_strlen($name) > 30) {
            throw new AdminResponseException('分类名称最多30个字符');
        }
        if ($this->getModel()->where(['name' => $name, 'type' => $this->categoryType])->find()) {
            throw new AdminResponseException('分类名称已存在');
        }

        // 校验父级分类是否存在（非一级时）
        if ($pid > 0) {
            $parent = $this->getModel()->where(['id' => $pid, 'type' => $this->categoryType])->find();
            if (!$parent) {
                throw new AdminResponseException('父级分类不存在');
            }
            // 只允许二级，父级必须是一级（pid=0）
            if ((int)$parent->pid !== 0) {
                throw new AdminResponseException('最多支持二级分类');
            }
        }

        $data = [
            'name'     => $name,
            'pid'      => $pid,
            'type'     => $this->categoryType,
            'owner_id' => 0,
            'sort'     => 0,
            'status'   => 1,
            'path'     => $pid > 0 ? json_encode([$pid]) : json_encode([]),
        ];

        $model = $this->getModel()->create($data);

        return [
            'id'    => $model->id,
            'label' => $name,
            'value' => $model->id,
            'pid'   => $pid,
        ];
    }

    /**
     * 获取移动端内容分类树。
     *
     * 仅返回启用状态的文章分类，供移动端内容分类页筛选使用。
     *
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getMobileTree(): array
    {
        if (!app()->make(ContentDisplayConfigService::class)->isContentEnabled()) {
            return [];
        }

        $list = $this->getModel()
            ->where([
                'type' => $this->categoryType,
                'owner_id' => 0,
                'status' => 1,
            ])
            ->field(['name as label', 'id as value', 'id', 'pid', 'sort', 'status', 'name', 'image', 'description'])
            ->order('sort desc,id desc')
            ->select()
            ->toArray();

        return get_tree_children($list);
    }

    /**
     * 获取移动端内容分类筛选范围。
     *
     * 传入一级分类时包含其直接子分类；传入二级分类时只返回自身。
     *
     * @param int $categoryId 分类ID。
     * @return array
     */
    public function getMobileScopeIds(int $categoryId): array
    {
        if (!app()->make(ContentDisplayConfigService::class)->isContentEnabled()) {
            return [];
        }

        if ($categoryId <= 0) {
            return [];
        }

        $childIds = $this->getModel()
            ->where([
                'type' => $this->categoryType,
                'owner_id' => 0,
                'status' => 1,
                'pid' => $categoryId,
            ])
            ->column('id');

        $ids = array_merge([$categoryId], array_map('intval', $childIds));
        return array_values(array_unique(array_filter($ids, static fn(int $id): bool => $id > 0)));
    }
}
