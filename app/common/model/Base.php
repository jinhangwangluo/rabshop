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

namespace app\common\model;

use app\common\enum\QueryEnum;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\helper\Str;
use think\Model;

/**
 * Class Base
 * @package app\common\model
 */
class Base extends Model
{
    /**
     * @var string
     */
    protected $autoWriteTimestamp = 'datetime';

    /**
     * path是否返回string类型
     * @var bool
     */
    protected bool $isPathStr = false;

    /**
     * 模型别名
     * @var string
     */
    protected string $queryAlias = '';

    /**
     * 连表
     * @var array
     */
    protected array $joinModels
    = [
        // 例如
        //['app\common\model\UserProfile p', 'a.id = p.uid', 'LEFT'],
    ];

    /**
     * @param $value
     * @return string
     */
    public function setPathAttr($value): string
    {
        if ($this->isPathStr) {
            return (string)$value;
        } else {
            if (is_array($value) && $value) {
                return '/' . implode('/', $value) . '/';
            }
            return '';
        }
    }

    /**
     * @param $value
     * @param $data
     * @return int|string|mixed
     */
    public function setPidAttr($value, $data)
    {
        if ($this->isPathStr) {
            if (isset($data['path']) && $data['path']) {
                return is_array($data['path']) ? $data['path'][count($data['path']) - 1] : $data['path'];
            } else {
                return $value;
            }
        } else {
            return $value;
        }
    }

    /**
     * @param $value
     * @param $data
     * @return array|string
     */
    public function getPathAttr($value, $data)
    {
        if ($this->isPathStr) {
            return $value;
        } else {
            return $value ? array_map('intval', array_merge(array_filter(explode('/', $value)))) : [];
        }
    }

    /**
     * 默认时间搜索器
     * @param $query
     * @param $value
     * @author 橙猫
     * @email 17349395805@163.com
     * @date 2022/11/10
     */
    public function searchTimeAttr($query, $value)
    {
        $field = $this->createTime;
        if (!empty($this->joinModels) && !str_contains($field, '.')) {
            $alias = $this->queryAlias ?: 'a';
            $field = $alias . '.' . $field;
        }
        set_model_time($query, $value, (string)$field);
    }

    /**
     * 获取tree型数据 自带某个id下的菜单禁用选择
     * @param array $where 原始条件
     * @param array|string[] $field 展示字段
     * @param null $sort 排序
     * @param int $id 禁用id
     * @param array $otherWhere 禁用其他条件
     * @param string $key 禁用id字段
     * @param string $pathKey 禁用查询key
     * @param string $disabledName 禁用key名
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getTreeList(array $where, array $field = ['*'], $sort = null, int $id = 0, array $otherWhere = [], string $key = 'id', string $pathKey = 'path', string $disabledName = 'disabled'): array
    {
        $data = $this->searchWhere($where)->field($field)
            ->when($sort, function ($query) use ($sort) {
                if (is_array($sort)) {
                    foreach ($sort as $k => $v) {
                        if (is_numeric($k)) {
                            $query->order($v, 'desc');
                        } else {
                            $query->order($k, $v);
                        }
                    }
                } else {
                    $query->order($sort, 'desc');
                }
            })->select()->toArray();
        if ($id) {
            $totalIds = $this->searchWhere($otherWhere)
                ->where($pathKey, 'LIKE', '%/' . $id . '/%')
                ->field([$key])->select()->map(function ($item) use ($key) {
                    return $item[$key];
                })->all();

            array_push($totalIds, $id);

            if ($totalIds) {
                foreach ($data as &$item) {
                    if (in_array($item[$key], $totalIds)) {
                        $item[$disabledName] = true;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * 获取当前类中的搜索器内容
     * @param array $withSearch
     * @return array[]
     * @author 橙猫
     * @email 17349395805@163.com
     * @date 2022/11/10
     */
    protected function getWithKey(array $withSearch): array
    {
        $with = [];
        $whereKey = [];
        foreach (array_keys($withSearch) as $fieldName) {
            $method = 'search' . Str::studly($fieldName) . 'Attr';
            if (method_exists($this, $method)) {
                $with[] = $fieldName;
            } else {
                $whereKey[] = $fieldName;
            }
        }
        return [$with, $whereKey];
    }

    /**
     * 搜索（没有进入搜索器的自动进入where条件）
     * @param array $where
     * @param bool $authWhere
     * @param array $options
     * @return Base
     * @author 橙猫
     * @email 17349395805@163.com
     * @date 2022/11/10
     */
    public function searchWhere(array $where, bool $authWhere = true, array $options = [])
    {
        [$with, $whereKey] = $this->getWithKey($where);
        $whereData = [];
        foreach ($whereKey as $key) {
            if (isset($where[$key]) && 'timeKey' !== $key) {
                $field = $key;
                if (!empty($this->joinModels) && !str_contains($field, '.')) {
                    $alias = $this->queryAlias ?: 'a';
                    $field = $alias . '.' . $field;
                }
                $whereData[$field] = $where[$key];
            }
        }

        $query = $this->withSearch($with, $where);

        if (!empty($this->joinModels)) {
            $alias = $this->queryAlias ?: 'a';
            $query->alias($alias);
            foreach ($this->joinModels as $item) {
                $query->join(...$item);
            }
            $this->queryAlias = $alias;
        } elseif ($this->queryAlias) {
            $query->alias($this->queryAlias);
        }

        return $this->applyFilterToQuery($query->when($authWhere && $whereData, function ($query) use ($whereData) {
            $query->where($whereData);
        }), $options);
    }

    /**
     * 数量
     * @param array $where
     * @param bool $authWhere
     * @return int
     */
    public function getDataCount(array $where = [], bool $authWhere = true): int
    {
        return $this->searchWhere($where, $authWhere)->count();
    }

    /**
     * 搜索
     * @param array $where
     * @param array|string[] $field
     * @param int $page
     * @param int $limit
     * @param null $sort
     * @param array $with
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getDataList(array $where, array $field = ['*'], int $page = 0, int $limit = 0, $sort = null, array $with = []): array
    {
        return $this->searchWhere($where)->when($page && $limit, function ($query) use ($page, $limit) {
            $query->page($page, $limit);
        })->when(!$page && $limit, function ($query) use ($limit) {
            $query->limit($limit);
        })->when($sort, function ($query) use ($sort) {
            if (is_array($sort)) {
                foreach ($sort as $k => $v) {
                    if (is_numeric($k)) {
                        $query->order($v, 'desc');
                    } else {
                        $query->order($k, $v);
                    }
                }
            } else {
                $query->order(str_contains($sort, ' ') ? $sort : $sort . ' desc');
            }
        })->field($field)->with($with)->select()->toArray();
    }

    /**
     * 通用搜索构造器
     * @param array $filter
     * @return \think\db\Query
     */
    protected function buildFilterQuery(array $filter)
    {
        $query = $this->db();
        return $this->applyFilterToQuery($query, $filter);
    }

    /**
     * 作用域：通用搜索
     * @param $query
     * @param array $filter
     */
    public function scopeFilter($query, array $filter)
    {
        $this->applyFilterToQuery($query, $filter);
    }

    /**
     * 应用过滤条件到查询对象
     * @param $query
     * @param array $filter
     * @return mixed
     */
    protected function applyFilterToQuery($query, array $filter)
    {
        foreach ($filter as $item) {
            if (!is_array($item) || count($item) < 3)
                continue;
            [$field, $op, $value] = $item;
            if (!empty($this->joinModels) && !str_contains($field, '.')) {
                $alias = $this->queryAlias ?: 'a';
                $field = $alias . '.' . $field;
            }
            $op = strtoupper($op);
            switch ($op) {
                case QueryEnum::EQ:
                case QueryEnum::NEQ:
                case QueryEnum::GT:
                case QueryEnum::EGT:
                case QueryEnum::LT:
                case QueryEnum::ELT:
                    $query->where($field, $op, $value);
                    break;
                case QueryEnum::LIKE:
                    $query->where($field, 'LIKE', "%$value%");
                    break;
                case QueryEnum::NOT_LIKE:
                    $query->where($field, 'NOT LIKE', "%$value%");
                    break;
                case QueryEnum::IN:
                    $query->whereIn($field, (array)$value);
                    break;
                case QueryEnum::NOT_IN:
                    $query->whereNotIn($field, (array)$value);
                    break;
                case QueryEnum::BETWEEN:
                    if (is_array($value) && count($value) >= 2) {
                        $query->whereBetween($field, $value);
                    }
                    break;
                case QueryEnum::NOT_BETWEEN:
                    if (is_array($value) && count($value) >= 2) {
                        $query->whereNotBetween($field, $value);
                    }
                    break;
                case QueryEnum::NULL:
                    if ($value) {
                        $query->whereNull($field);
                    } else {
                        $query->whereNotNull($field);
                    }
                    break;
                case QueryEnum::NOT_NULL:
                    if ($value) {
                        $query->whereNotNull($field);
                    } else {
                        $query->whereNull($field);
                    }
                    break;
                case QueryEnum::TIME:
                    set_model_time($query, $value, $field);
                    break;
            }
        }
        return $query;
    }
}
