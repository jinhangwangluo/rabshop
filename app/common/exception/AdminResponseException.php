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

namespace app\common\exception;

use Throwable;

/**
 * 响应异常
 * @package app\common\exception
 */
class AdminResponseException extends \RuntimeException
{

    /**
     * 响应数据
     * @var mixed
     */
    protected mixed $response;

    /**
     * 构造函数
     * @param $message
     * @param $code
     * @param Throwable|null $previous
     * @param mixed|null $response
     */
    public function __construct($message = "", $code = 0, Throwable $previous = null, mixed $response = null)
    {
        parent::__construct($message, $code, $previous);
        $this->response = $response;
    }

    /**
     * 获取响应数据
     * @return mixed|null
     */
    public function getResponse()
    {
        return $this->response;
    }
}
