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

namespace app\common\service;

use app\common\model\Base;
use Rabbit\util\CacheTag;
use http\Exception\BadMethodCallException;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Config;
use think\facade\Db;
use think\Model;
use think\model\contract\Modelable;

/**
 * Trait HelpService
 * @package app\common\service
 * @property Model $model
 * @mixin Model
 */
trait HelpService
{

    /**
     * @var null
     */
    protected $each = null;

    /**
     * @var array
     */
    protected array $response = [];

    /**
     * @var null
     */
    protected $sort = null;

    /**
     * @param $sort
     * @return $this
     */
    public function setSort($sort = null)
    {
        $this->sort = $sort ?: $this->getModel()->getPk();
        return $this;
    }

    /**
     * 默认查询列表方法
     * @param array $where
     * @param array $field
     * @param array $with
     * @param null $sort
     * @return BaseService|HelpService
     * @throws DbException
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     */
    public function getDefaultList(array $where = [], array $field = ['*'], array $with = [], array|string|null $sort = null)
    {
        [$page, $limit] = $this->getPagesValue();

        $list = $this->getModel()->getDataList($where, $field, $page, $limit, $this->sort ?: $sort, $with);
        $count = $this->getModel()->getDataCount($where);

        return $this->listData($list, $count, [], $page, $limit);
    }

    /**
     * 遍历
     * @param callable $fn
     * @return $this
     * @author 橙猫
     * @email 17349395805@163.com
     * @date 2024/1/26
     */
    public function each(callable $fn)
    {
        $list = $this->response['list'];

        foreach ($list as $index => $item) {
            $list[$index] = is_null($dataItem = $fn($item, $index)) ? $item : $dataItem;
        }

        $this->response['list'] = $list;

        return $this;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->response;
    }

    /**
     * 组合数据返回正确的数据格式
     * @param array $list
     * @param int $count
     * @param array $other
     * @param int $page
     * @param int $limit
     * @return HelpService
     */
    protected function listData(array $list, int $count = 0, array $other = [], int $page = 0, int $limit = 0)
    {
        if (!$page && !$limit) {
            [$page, $limit] = $this->getPagesValue();
        }

        $response = [
            'list'      => $list,
            'total'     => $count,
            'limit'     => $limit,
            'page'      => $page,
            'totalPage' => $count && $limit ? ceil($count / $limit) : 0,
        ];

        if ($other) {
            $response = array_merge($response, $other);
        }

        $this->response = $response;

        return $this;
    }

    /**
     * 获取分页配置
     * @param bool $isPage
     * @param bool $isRelieve
     * @param array $params 外部传入的分页参数，为空时读取当前请求。
     * @param int $limitMaxOverride 本次调用的分页上限，0 表示使用全局上限。
     * @return int[]
     */
    public function getPagesValue(
        bool $isPage = true,
        bool $isRelieve = true,
        array $params = [],
        int $limitMaxOverride = 0
    ): array
    {
        $pageKey = Config::get('database.pages.page_key', 'page');
        $limitKey = Config::get('database.pages.limit_key', 'limit');
        $limitMax = max(1, (int)Config::get('database.pages.limit_max'));
        $defaultLimit = max(1, (int)Config::get('database.pages.default_limit', 10));
        if ($limitMaxOverride > 0) {
            $limitMax = min($limitMax, $limitMaxOverride);
        }

        $page = $limit = 0;
        if ($isPage && $params !== []) {
            $page = max(1, (int)($params[$pageKey] ?? $params['page'] ?? 1));
            $limit = max(1, (int)($params[$limitKey] ?? $params['limit'] ?? $defaultLimit));
        } elseif ($isPage) {
            $page = app()->request->param($pageKey . '/d', 0);
            $limit = app()->request->param($limitKey . '/d', 0);
        }

        if ($limit > $limitMax && $isRelieve) {
            $limit = $limitMax;
        }

        return [(int)$page, (int)$limit, (int)$defaultLimit];
    }

    /**
     * 获取表主键
     * @return array|string|null
     */
    protected function getPkStr()
    {
        return $this->getModel()->getPk();
    }

    /**
     * 处理where数据
     * @param int|string|array|float $data
     * @return float[]|int[]|string[]
     */
    protected function handleData(int|string|array|float $data)
    {
        if (is_int($data) || is_string($data) || is_float($data)) {
            return [$this->getPkStr() => $data];
        } else {
            return $data;
        }
    }

    /**
     * 删除操作
     * @param int|string|array|float $data
     * @return bool
     */
    public function delete(int|string|array|float $data)
    {
        return $this->getModel()->where($this->handleData($data))->delete();
    }


    /**
     * 更新数据
     * @param int|string|array|float $where
     * @param array $data
     * @return Modelable
     */
    public function update(int|string|array|float $where, array $data = [])
    {
        return $this->getModel()->where($this->handleData($where))->update($data);
    }

    /**
     * 创建
     * @param array $data
     * @return Modelable
     */
    public function create(array $data)
    {
        return $this->getModel()->create($data);
    }

    /**
     * 查询或者创建
     * @param array $where
     * @return Base|array|mixed|Model
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function findOrcreate(array $where)
    {
        $res = $this->getModel()->where($this->handleData($where))->find();
        if (!$res)
            $res = $this->create($where);
        return $res;

    }

    /**
     * @param int|string|array|float $data
     * @param null $notValue
     * @param null $notField
     * @return int
     * @throws DbException
     */
    public function count(int|string|array|float $data, $notValue = null, $notField = null)
    {
        return $this->getModel()->where($this->handleData($data))->when($notValue, fn($q) => $q->where($notField ?: $this->getPkStr(), '<>', $notValue))->count();
    }

    /**
     * @param int|string|array|float $data
     * @param $field
     * @param string $key
     * @return array
     */
    public function column(int|string|array|float $data, $field, string $key = '')
    {
        return $this->getModel()->where($this->handleData($data))->column($field, $key);
    }

    /**
     * 获取单个数据
     * @param int|string|array|float $data
     * @param array $field
     * @param array $with
     * @return array|mixed|Model|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function get(int|string|array|float $data, array $field = ['*'], array $with = [])
    {
        return $this->getModel()::where($this->handleData($data))->when($with, fn($q) => $q->with($with))->field($field)->find();
    }

    /**
     * 获取包含软删除的数据
     * @param int|string|array|float $data
     * @param array $field
     * @param array $with
     * @return array|mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function trashedGet(int|string|array|float $data, array $field = ['*'], array $with = [])
    {
        return $this->getModel()->withTrashed()->where($this->handleData($data))->when($with, fn($q) => $q->with($with))->field($field)->find();
    }

    /**
     * @param bool $isPage
     * @param bool $isRelieve
     * @return int[]
     */
    public function getPageValue(bool $isPage = true, bool $isRelieve = true)
    {
        $page = 1;
        $limit = 10;
        if ($isPage) {
            $page = request()->param(config('database.pages.page_key', 'page'), 0);
            $limit = request()->param(config('database.pages.limit_key', 'limit'), 0);
        }

        $limitMax = config('database.pages.limit_max');
        $defaultLimit = config('database.pages.default_limit', 10);
        if ($limit > $limitMax && $isRelieve) {
            $limit = $limitMax;
        }

        return [(int)$page, (int)$limit, (int)$defaultLimit];
    }

    /**
     * @param int|string|array|float $data
     * @param string $field
     * @return mixed
     */
    public function value(int|string|array|float $data, string $field)
    {
        return $this->getModel()->where($this->handleData($data))->value($field);
    }

    /**
     * 软删除
     * @param $data
     * @param bool $force
     * @return bool
     */
    public function destroy($data, bool $force = false)
    {
        return $this->getModel()->destroy($data, $force);
    }

    /**
     * 获取标签操作缓存
     * @return CacheTag
     */
    public function cacheTag()
    {
        return new CacheTag($this->getModel()->getName());
    }

    /**
     * 监听sql
     * @return void
     */
    public function listen()
    {
        Db::listen(function ($sql, $time) {
            var_dump($sql . ';使用时间：' . $time);
        });
    }

    /**
     * @param string $name
     * @return CacheTag
     */
    public function __get(string $name)
    {
        if ('cacheTag' === $name) {
            return $this->cacheTag();
        } else {
            throw new \BadMethodCallException('The accessed property does not exist:' . __CLASS__ . ':' . $name);
        }
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->getModel(), $name], $arguments);
    }
}
