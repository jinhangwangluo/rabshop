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

namespace app\common\model\setting\system;


use app\common\model\Base;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\model\concern\SoftDelete;

/**
 * Class Menu
 * @package app\common\model
 */
class Menu extends Base
{

    use SoftDelete;

    /**
     * @var string
     */
    protected $name = 'menus';

    /**
     * @var string
     */
    protected $pk = 'id';

    /**
     * @var bool
     */
    protected $autoWriteTimestamp = true;

    /**
     * @param $value
     * @return string
     */
    public function setPathAttr($value): string
    {
        return is_array($value) ? '/' . implode('/', $value) . '/' : $value;
    }

    /**
     * @param $value
     * @param $data
     * @return int
     */
    public function setPidAttr($value, $data)
    {
        return is_array($value) ? $value[count($value) - 1] : $value;
    }

    /**
     * @param $value
     * @param $data
     * @return array
     */
    public function getPathAttr($value, $data)
    {
        return $value;
    }

    public function getParamsAttr($value)
    {
        return $value ? explode('&', $value) : [];
    }

    /**
     * 获取菜单tree
     * @param bool $auth
     * @param array $field
     * @param array $menusIds
     * @param string $order
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getMenusTree(bool      $auth = true, array $field = [
        'icon', 'name as label',
        'name', 'url',
        'pid', 'id', 'id as value'], array $menusIds = [], string $order = 'desc')
    {
        return get_tree_children($this->when($auth, function ($query) {
            $query->where(['auth_type' => 0]);
        })->when($menusIds, function ($query) use ($menusIds) {
            $query->whereIn('id', $menusIds);
        })->where(['status' => 1])->field($field)->order('sort desc,id ' . $order)->select()->toArray());
    }

    /**
     * @param array $where
     * @return Menu
     */
    public function search(array $where)
    {
        return $this->when(isset($where['status']) && '' !== $where['status'], function ($query) use ($where) {
            $query->where('status', $where['status']);
        })->when(isset($where['auth_type']) && '' !== $where['auth_type'], function ($query) use ($where) {
            if (is_array($where['auth_type'])) {
                $query->whereIn('auth_type', $where['auth_type']);
            } else {
                $query->where('auth_type', $where['auth_type']);
            }
        })->when(!empty($where['title']), function ($query) use ($where) {
            $query->where('name', 'like', '%' . $where['title'] . '%');
        })->when(isset($where['platform_type']) && '' !== $where['platform_type'], function ($query) use ($where) {
            $query->where('platform_type', $where['platform_type']);
        });
    }
}
