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

namespace app\common\exception;

use RuntimeException;
use Throwable;

/**
 * 插件加密信封校验异常。
 *
 * 所有安全失败统一为此异常，避免把 OpenSSL/GMP 内部细节回显给调用方。
 */
class PluginSecurityException extends RuntimeException
{
    public function __construct(
        string $message = '插件安全校验失败',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
