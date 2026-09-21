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
use app\common\model\setting\system\CityArea;
use app\common\service\BaseService;

/**
 * 省市区地址服务。
 */
class CityAreaService extends BaseService
{
    protected function setModel(): string
    {
        return CityArea::class;
    }

    /**
     * 获取后台地址列表。
     *
     * @param array $where 查询条件。
     * @return array
     */
    public function getAdminList(array $where): array
    {
        [$page, $limit] = $this->getPageValue();

        $query = $this->getModel()->search($where);
        $count = (int)$query->count();
        $list = $query
            ->order('sort', 'ASC')
            ->order('id', 'ASC')
            ->page($page, $limit)
            ->select()
            ->toArray();

        return compact('count', 'list');
    }

    /**
     * 获取启用地址下拉树。
     *
     * @return array
     */
    public function options(): array
    {
        $list = $this->getModel()
            ->where('status', 1)
            ->field('id,level,name,parent_id,path')
            ->order('sort', 'ASC')
            ->order('id', 'ASC')
            ->select()
            ->toArray();

        return get_tree_children($list, 'children', 'id', 'parent_id');
    }

    /**
     * 获取地址完整树。
     *
     * @return array
     */
    public function tree(array $where = []): array
    {
        $list = $this->getModel()
            ->search($where)
            ->field('id,parent_id,path,type,level,name,sort,status,create_time,update_time')
            ->order('sort', 'ASC')
            ->order('id', 'ASC')
            ->select()
            ->toArray();

        return get_tree_children($list, 'children', 'id', 'parent_id');
    }

    /**
     * 获取 bubble-address 组件所需地址数据。
     *
     * @return array
     */
    public function getBubbleAddressData(): array
    {
        $list = $this->getModel()
            ->where('status', 1)
            ->field('id,parent_id,level,name,sort')
            ->order('sort', 'ASC')
            ->order('id', 'ASC')
            ->select()
            ->toArray();

        return [
            'provinceList' => $this->buildBubbleAddressList($list, 1),
            'cityList' => $this->buildBubbleAddressList($list, 2),
            'areaList' => $this->buildBubbleAddressList($list, 3),
        ];
    }

    /**
     * 转换指定层级地址为 bubble-address 列表项。
     *
     * @param array $list 地址列表。
     * @param int $level 地址层级。
     * @return array
     */
    protected function buildBubbleAddressList(array $list, int $level): array
    {
        $result = [];
        foreach ($list as $item) {
            if ((int)($item['level'] ?? 0) !== $level) {
                continue;
            }

            $id = (string)($item['id'] ?? '');
            $parentId = (string)($item['parent_id'] ?? '');
            $row = [
                'code' => $id,
                'name' => (string)($item['name'] ?? ''),
            ];

            if ($level === 2) {
                $row['province'] = substr($id, 0, 2);
            }

            if ($level === 3) {
                $row['province'] = substr($id, 0, 2);
                $row['city'] = strlen($parentId) >= 4 ? substr($parentId, 2, 2) : substr($id, 2, 2);
            }

            $result[] = $row;
        }

        return $result;
    }

    /**
     * 删除地址前检查是否存在下级。
     *
     * @param int $id 地址ID。
     * @return bool
     */
    public function deleteCityArea(int $id): bool
    {
        if ($id <= 0) {
            throw new AdminResponseException('缺少参数');
        }

        $hasChildren = $this->getModel()->where('parent_id', $id)->count() > 0;
        if ($hasChildren) {
            throw new AdminResponseException('请先删除下级地址');
        }

        return $this->destroy($id);
    }
}
