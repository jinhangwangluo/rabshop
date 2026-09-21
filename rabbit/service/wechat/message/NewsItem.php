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
 * Class NewsItem
 * @package Rabbit\services\wechat\message
 */
class NewsItem extends Message
{
    /**
     * Messages type.
     *
     * @var string
     */
    protected string $type = 'news';

    /**
     * Properties.
     *
     * @var array
     */
    protected array $properties = [
        'title',
        'description',
        'url',
        'image',
    ];

    /**
     * @return array
     * @author 橙猫
     * @email 17349395805@163.com
     * @date 2023/9/26
     */
    public function toJsonArray(): array
    {
        return [
            'title' => $this->get('title'),
            'description' => $this->get('description'),
            'url' => $this->get('url'),
            'picurl' => $this->get('image'),
        ];
    }

    /**
     * @return array
     * @author 橙猫
     * @email 17349395805@163.com
     * @date 2023/9/26
     */
    public function toXmlArray(): array
    {
        return [
            'Title' => $this->get('title'),
            'Description' => $this->get('description'),
            'Url' => $this->get('url'),
            'PicUrl' => $this->get('image'),
        ];
    }
}
