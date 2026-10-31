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

/**
 * Class Arr
 * @package Rabbit\util
 */
class Arr
{


    /**
     * @param array $menu
     * @return mixed
     */
    public static function getOneId(array $menu)
    {
        if (isset($menu['children']) && count($menu['children'])) {
            return self::getOneId($menu['children'][0]);
        }
        return $menu['id'];
    }

    /**
     * @param $instance
     * @param array $params [ ['name','','name_like','s'] ]
     * @param bool|null $suffix
     * @param string $mentoh
     * @return array
     */
    public static function more($instance, array $params, ?bool $suffix = null, string $mentoh = 'post')
    {
        $p = [];
        $i = 0;
        foreach ($params as $param) {
            if (!is_array($param)) {
                $p[$suffix ? $i++ : $param] = $instance->{$mentoh}($param);
            } else {
                if (!isset($param[1])) {
                    $param[1] = null;
                }
                if (!isset($param[2])) {
                    $param[2] = '';
                }
                //[ ['name','','name_like','s'] ]
                // 数组0号元素为参数键，数组1号元素为默认值，数组2号元素是转换为键的名称，数据3号元素是自动转换格式
                //自动转换格式参数可用：s=字符串,d=整数,f=浮点，b=布尔，a=数组

                $type = isset($param[3]) ? '/' . $param[3] : '';
                $value = $instance->{$mentoh}($param[0] . $type, $param[1]);
                $p[$suffix ? $i++ : (isset($param[2]) && $param[2] ? $param[2] : $param[0])] = is_null($value) ? $param[1] : $value;
            }
        }
        return $p;
    }
}
