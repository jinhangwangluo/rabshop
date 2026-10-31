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

namespace app\controller\merchant;

use app\BaseController;
use app\common\exception\AdminException;

/**
 * 商户端基类控制器
 * Class AuthController
 * @package app\controller\merchant
 * @property int $adminId 当前登录管理员ID
 * @property array $adminInfo 当前登录管理员信息
 * @property int $merchantId 当前登录商户ID
 * @property array $merchantInfo 当前登录商户信息
 */
class AuthController extends BaseController
{
    /**
     * @var string[]
     */
    protected $rule = ['adminId', 'adminInfo', 'merchantId', 'merchantInfo'];

    /**
     * @param $name
     * @return array|mixed
     */
    public function __get($name)
    {
        if (in_array($name, $this->rule)) {
            return request()->{$name};
        }
        throw new AdminException(sprintf(
            'attribute %s::%s does not exist.', static::class, $name
        ));
    }
}
