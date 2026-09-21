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
 * Class Raw
 * @date 2023/9/26
 * @package Rabbit\services\wechat\message
 */
class Raw extends Message
{
    /**
     * @var string
     */
    protected string $type = 'raw';

    /**
     * Properties.
     *
     * @var array
     */
    protected array $properties = ['content'];

    /**
     * Constructor.
     *
     * @param string $content
     */
    public function __construct(string $content)
    {
        parent::__construct(['content' => strval($content)]);
    }

    /**
     * @param array $appends
     * @param bool $withType
     *
     * @return array
     */
    public function transformForJsonRequest(array $appends = [], bool $withType = true): array
    {
        return json_decode($this->content, true) ?? [];
    }

    public function __toString()
    {
        return $this->get('content') ?? '';
    }
}
