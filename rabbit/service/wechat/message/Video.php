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


class Video extends Media
{
    /**
     **
     * Messages type.
     *
     * @var string
     */
    protected string $type = 'video';

    /**
     * Properties.
     *
     * @var array
     */
    protected array $properties = [
        'title',
        'description',
        'media_id',
        'thumb_media_id',
    ];

    /**
     * Video constructor.
     *
     * @param string $mediaId
     * @param array $attributes
     */
    public function __construct(string $mediaId, array $attributes = [])
    {
        parent::__construct($mediaId, 'video', $attributes);
    }

    /**
     * @return array[]
     * @author 橙猫
     * @email 17349395805@163.com
     * @date 2023/9/26
     */
    public function toXmlArray(): array
    {
        return [
            'Video' => [
                'MediaId' => $this->get('media_id'),
                'Title' => $this->get('title'),
                'Description' => $this->get('description'),
            ],
        ];
    }
}
