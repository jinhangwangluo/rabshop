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

namespace Rabbit\util;

use think\Response;

/**
 * Json输出类
 * Class Json
 * @package app\common\util
 */
class Json
{
    /**
     * @var int
     */
    private int $code = 200;

    /**
     * 设置http状态
     * @param int $code
     * @return $this
     */
    public function code(int $code): self
    {
        $this->code = $code;
        return $this;
    }

    /**
     * 创建返回Response
     * @param int $status
     * @param string $msg
     * @param array|null $data
     * @return Response
     */
    public function make(int $status, string $msg, ?array $data = null): Response
    {
        $res = [
            'message' => $msg,
            'status' => $status
        ];

        if (!is_null($data)) {
            $res['data'] = $data;
        }

        return Response::create($res, 'json', $this->code);
    }

    /**
     * 成功返回
     * @param $msg
     * @param array|null $data
     * @return Response
     */
    public function success($msg = 'ok', ?array $data = null): Response
    {
        if (is_array($msg)) {
            $data = $msg;
            $msg = 'ok';
        }

        return $this->make(200, $msg, $data);
    }

    /**
     * 失败返回
     * @param $msg
     * @param array|null $data
     * @return Response
     */
    public function fail($msg = 'fail', ?array $data = null,int $status = 400): Response
    {
        if (is_array($msg)) {
            $data = $msg;
            $msg = 'ok';
        }

        return $this->make($status, $msg, $data);
    }

    /**
     * 状态返回
     * @param $status
     * @param $msg
     * @param $result
     * @return Response
     */
    public function status($status, $msg, $result = [])
    {
        $status = strtoupper($status);
        if (is_array($msg)) {
            $result = $msg;
            $msg = 'ok';
        }
        return $this->success($msg, compact('status', 'result'));
    }
}
