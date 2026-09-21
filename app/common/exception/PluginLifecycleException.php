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

use Throwable;

/**
 * 插件生命周期契约异常。
 *
 * 对外只暴露脱敏错误码和短消息，原始异常不得直接写入任务或事件载荷。
 */
class PluginLifecycleException extends AdminResponseException
{
    protected string $errorCode;

    public function __construct(
        string $message,
        string $errorCode = 'PLUGIN_LIFECYCLE_ERROR',
        int $code = 0,
        ?Throwable $previous = null,
        mixed $response = null
    ) {
        $this->errorCode = $errorCode;
        parent::__construct($message, $code, $previous, $response);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
