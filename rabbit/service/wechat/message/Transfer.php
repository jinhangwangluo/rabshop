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


class Transfer extends Message
{
    /**
     * Messages type.
     *
     * @var string
     */
    protected string $type = 'transfer_customer_service';

    /**
     * Properties.
     *
     * @var array
     */
    protected array $properties = [
        'account',
    ];

    /**
     * Transfer constructor.
     *
     * @param string|null $account
     */
    public function __construct(string $account = null)
    {
        parent::__construct(compact('account'));
    }

    /**
     * @return array
     * @author 橙猫
     * @email 17349395805@163.com
     * @date 2023/9/26
     */
    public function toXmlArray(): array
    {
        return empty($this->get('account')) ? [] : [
            'TransInfo' => [
                'KfAccount' => $this->get('account'),
            ],
        ];
    }
}
