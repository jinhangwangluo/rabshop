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

namespace Rabbit\util;


use think\facade\Cache;

/**
 * Class CacheTag
 * @package Rabbit\util
 */
class CacheTag
{

    /**
     * @var string
     */
    protected $tag;

    /**
     * CacheTag constructor.
     * @param string $tag
     */
    public function __construct(string $tag)
    {
        $this->tag = $tag;
    }

    /**
     * @return bool
     */
    public function clear()
    {
        return Cache::tag($this->tag)->clear();
    }

    /**
     * 判断缓存是否存在
     * @param string $name
     * @return bool
     */
    public function has(string $name)
    {
        return Cache::has($name);
    }

    /**
     * 设置缓存
     * @param string $name
     * @param $value
     * @param int|\DateInterval|\DateTimeInterface|null $expire
     * @return bool
     */
    public function set(string $name, $value, int|\DateInterval|\DateTimeInterface|null $expire = null)
    {
        return Cache::tag($this->tag)->set(':' . $this->tag . ':' . $name, $value, $expire);
    }

    /**
     * 获取key
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null)
    {
        return Cache::get(':' . $this->tag . ':' . $key, $default);
    }

    /**
     * 删除缓存
     * @param string $key
     * @return bool
     */
    public function delete(string $key)
    {
        return Cache::delete(':' . $this->tag . ':' . $key);
    }

    /**
     * 如果不存在则写入缓存
     * @param string $name
     * @param $value
     * @param null $expire
     * @return mixed
     */
    public function remember(string $name, $value, $expire = null)
    {
        return Cache::tag($this->tag)->remember(':' . $this->tag . ':' . $name, $value, $expire);
    }
}
