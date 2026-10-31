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

namespace Rabbit\interfaces;

/**
 * Interface ConfigInterface
 * @package dengfenglai\interfaces
 */
interface ConfigInterface
{
    /**
     * @param string $key
     * @return mixed
     */
    public function getValue(string $key): mixed;

    /**
     * @param array $key
     * @return array
     */
    public function getMultiValue(array $key): array;
}
