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


use app\common\exception\AdminException;
use app\common\model\setting\system\Category;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use FormBuilder\Exception\FormBuilderException;
use Rabbit\util\FormBuild as Build;
use Rabbit\traits\ViewTrait;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\model\contract\Modelable;

/**
 * Class CategoryService
 * @package app\common\service\setting
 */
class CategoryService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ViewTrait;
    use ResourceServiceTrait;

    // 文件类型
    const FILE_TYPE = 1;
    // 系统配置类型
    const CONFIG_TYPE = 0;
    // 自定义分类类型
    const DIY_TYPE = 2;
    // 文章分类类型
    const ARTICLE_TYPE = 3;
    // 专题分类类型
    const SPECIAL_TYPE = 4;
    // 话术分类类型
    const SCRIPT_TYPE = 5;
    // 知识库分类类型
    const KNOWLEDGE_TYPE = 6;
    // 组合数据分类类型
    const COMBINATION_DATA_TYPE = 7;
    // 内容/专题标签分组类型
    const CONTENT_TAG_GROUP_TYPE = 8;
    // 商品标签分组类型
    const PRODUCT_TAG_GROUP_TYPE = 9;
    // 商品分类类型
    const PRODUCT_TYPE = 10;

    /**
     * @var
     */
    protected int $categoryType = self::CONFIG_TYPE;

    /**
     * 获取模型
     * @return string
     */
    protected function setModel(): string
    {
        return Category::class;
    }

    /**
     * 保存资源
     * @param array $data
     * @return Modelable
     */
    public function resourceSave(array $data)
    {
        $data['type'] = $this->categoryType;

        return $this->create($data);
    }

    /**
     * 获取分类
     * @param array $params
     * @param array|string $fields
     * @param array $with
     * @param string|array|null $sort
     * @return array|mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getResourceList(array $params = [], array|string $fields = [], array $with = [], string|array|null $sort = null)
    {
        $type = $this->categoryType;
        $count = $this->getModel()->where(['type' => $type])->count();
        $list = $this->getModel()->where(['type' => $type])
            ->order('sort desc,id desc')
            ->field(['other', 'id', 'name', 'sort', 'status', 'path', 'create_time'])
            ->select()->toArray();

        return compact('list', 'count');
    }

    /**
     * @param int $type
     * @return $this
     */
    public function setCategoryType(int $type)
    {
        $this->categoryType = $type;
        return $this;
    }

    /**
     * 获取编辑和创建分类表单
     * @param array $params
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws FormBuilderException
     * @throws ModelNotFoundException
     */
    public function resourceCreate(array $params): array
    {
        $id = (int)($params['id'] ?? 0);
        $pid = $params['pid'] ?? 0;
        $url = $params['url'] ?? '';
        $ownerId = $params['owner_id'] ?? 0;

        $info = $path = [];
        if ($id) {
            $info = $this->getModel()->find($id);
            if (!$info) {
                throw new AdminException('分类信息没有查询到');
            }
            $info = $info->toArray();
        } else if ($pid) {
            $path = $this->getModel()->where('id', $pid)->where('owner_id', $ownerId)->value('path');
            $path = $this->getModel()->getPathAttr($path, []);
            if ($path) {
                array_push($path, $pid);
            } else {
                $path = [$pid];
            }
        }

        if (!$id) {
            $pid = 0;
        }

        $treeList = $this->getModel()->getTreeList(
            ['type' => $this->categoryType, 'owner_id' => $ownerId],
            ['name as label', 'id as value', 'id', 'pid'],
            'id desc,sort desc',
            $pid
        );
        array_unshift($treeList, ['label' => '全部分类', 'value' => 0, 'pid' => 0, 'id' => -1]);

        foreach ($treeList as $key => $item) {
            if ($item['pid']) {
                $item['disabled'] = true;
            }
            $treeList[$key] = $item;
        }

        $treeList = get_tree_children($treeList);

        return $this->buildView([
            Build::cascader('path', '上级分类', $info['path'] ?? $path)->options($treeList),
            Build::input('name', '分类名称', $info['name'] ?? '')->required(),
            Build::number('sort', '排序', $info['sort'] ?? ''),
            Build::hidden('type', $this->categoryType),
        ], $url ?: '/system/config_cate?id=' . $id, 'POST', $id ? '修改分类' : '添加分类');
    }

    /**
     * 获取分类tree数据
     * @param int $type
     * @param int $ownerId
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getCategoryTree(int $type = 0, int $ownerId = 0, array $categoryIds = [])
    {
        $list = $this->getModel()->where(['type' => $type, 'owner_id' => $ownerId])
            ->field(['name as label', 'id as value', 'id', 'pid', 'sort', 'status', 'name', 'image', 'banner_image', 'description'])
            ->order('id desc,sort desc')
            ->select()
            ->toArray();
        $list = $this->filterCategoryListByIds($list, $categoryIds);

        return get_tree_children($list);
    }

    /**
     * 按选中的分类 ID 保留自身及所有子级；空数组表示不过滤。
     *
     * @param array $list 分类平铺列表。
     * @param array $categoryIds 选中的分类 ID。
     * @return array 过滤后的分类平铺列表。
     */
    protected function filterCategoryListByIds(array $list, array $categoryIds): array
    {
        $categoryIds = array_values(array_unique(array_filter(array_map('intval', $categoryIds))));
        if ($categoryIds === []) {
            return $list;
        }

        $childrenMap = [];
        foreach ($list as $item) {
            $pid = (int)($item['pid'] ?? 0);
            $id = (int)($item['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $childrenMap[$pid][] = $id;
        }

        $allowedIds = [];
        $stack = $categoryIds;
        while ($stack !== []) {
            $id = array_pop($stack);
            if (isset($allowedIds[$id])) {
                continue;
            }

            $allowedIds[$id] = true;
            foreach ($childrenMap[$id] ?? [] as $childId) {
                $stack[] = $childId;
            }
        }

        return array_values(array_filter($list, static function (array $item) use ($allowedIds): bool {
            return isset($allowedIds[(int)($item['id'] ?? 0)]);
        }));
    }

    /**
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getCategoryList(array $where = [])
    {
        return $this->getModel()->where(['type' => $this->categoryType] + $where)
            ->field(['name', 'id'])
            ->order('id desc,sort desc')
            ->select()
            ->toArray();
    }
}
