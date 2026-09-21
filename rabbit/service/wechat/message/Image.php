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

namespace Rabbit\service\wechat\message;

/**
 * 图片
 * Class Image
 * @date 2023/9/14
 * @package Rabbit\services\wechat\message
 */
class Image extends Media
{
    /**
     * 消息类型
     * @var string
     */
    protected string $type = 'image';
}
