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

namespace Rabbit\service\wechat;

use EasyWeChat\Kernel\HttpClient\Response;

/**
 * 微信返回结果统一处理
 */
class WechatResponse
{

    /**
     * 微信返回结果
     * @var array|mixed[]
     */
    protected $data = [];

    /**
     * @param Response $response
     * @throws \EasyWeChat\Kernel\Exceptions\BadResponseException
     */
    public function __construct(protected Response $response)
    {
        $response = $this->response->toArray();
        if ($response['errcode'] !== 0) {
            throw new WechatException($response['errmsg'], $response['errcode']);
        }

        $this->data = $response;
    }

    /**
     * 获取微信返回结果
     * @return array|mixed[]
     * @throws \EasyWeChat\Kernel\Exceptions\BadResponseException
     */
    public function getResponse()
    {
        return $this->data;
    }
}