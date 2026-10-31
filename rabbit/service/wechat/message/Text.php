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
 * text消息
 * Class Text
 * @package Rabbit\services\wechat\message
 */
class Text extends Message
{
    /**
     * Message type.
     *
     * @var string
     */
    protected string $type = 'text';

    /**
     * Properties.
     *
     * @var array
     */
    protected array $properties = ['content'];

    /**
     * Text constructor.
     *
     * @param string $content
     */
    public function __construct(string $content = '')
    {
        parent::__construct(compact('content'));
    }

    /**
     * @return array
     */
    public function toXmlArray(): array
    {
        return [
            'Content' => $this->get('content'),
        ];
    }
}
