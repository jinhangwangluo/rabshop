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

namespace app;

// 应用请求对象类
use Rabbit\util\Arr;

/**
 *
 * Class Request
 * @package app
 * @property $uid;
 * @property $userInfo
 * @method userInfoFn(string $key = null)
 */
class Request extends \think\Request
{

    /**
     * @param array $post
     * @param bool $suffix
     * @return array
     */
    public function postMore(array $post, bool $suffix = false)
    {
        return Arr::more($this, $post, $suffix, 'param');
    }

    /**
     * @param array $get
     * @param bool $suffix
     * @return array
     */
    public function getMore(array $get, bool $suffix = false)
    {
        return Arr::more($this, $get, $suffix, 'get');
    }
}
