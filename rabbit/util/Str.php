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
 * 字符串操作
 * Class Str
 * @package Rabbit\util
 */
class Str
{

    /**
     * @param $subject
     * @param $search
     * @return mixed|string
     */
    public static function after($subject, $search)
    {
        return $search === '' ? $subject : array_reverse(explode($search, $subject, 2))[0];
    }

    /**
     * 去空格，转小写
     * @param string $str
     * @return string
     */
    public static function trimStrtolower(string $str)
    {
        return trim(strtolower($str));
    }

    /**
     * 获取当前url信息
     * @return array
     */
    public static function getRuleUrl()
    {
        $request = request();
        $appName = app()->http->getName();
        $controller = strtolower($request->controller());
        $action = strtolower($request->action());
        $param = [];
        $url = '/' . $appName . '/' . $controller . '/' . $action;
        $urlParam = self::getRuleUrlParam($url, $param);
        return compact('appName', 'controller', 'action', 'param', 'url', 'urlParam');
    }

    /**
     * 获取带参数的url
     * @param string $url
     * @param array $param
     * @return string
     */
    public static function getRuleUrlParam(string $url, array $param = [])
    {
        $paramData = [];
        foreach ($param as $k => $v) {
            $paramData [] = $k . '=' . $v;
        }
        if ($paramData) {
            $url .= '?' . implode('&', $paramData);
        }
        return $url;
    }
}
