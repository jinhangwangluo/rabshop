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


class Voice extends Media
{
    /**
     * Messages type.
     *
     * @var string
     */
    protected string $type = 'voice';

    /**
     * Properties.
     *
     * @var array
     */
    protected array $properties = [
        'media_id',
        'recognition',
    ];
}
