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

declare(strict_types=1);

namespace Rabbit\service\collector\driver;

/**
 * 淘宝采集兼容驱动。
 *
 * 兼容旧类名，实际实现统一收敛到 OneBound。
 */
class Taobao extends OneBound
{
    /**
     * @return string
     */
    public function getProvider(): string
    {
        return 'taobao';
    }
}
