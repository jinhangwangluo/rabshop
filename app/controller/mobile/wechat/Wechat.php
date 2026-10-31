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

namespace app\controller\mobile\wechat;

use app\BaseController;
use Rabbit\service\wechat\OfficialAccount;

/**
 * 微信公众号服务控制器.
 */
class Wechat extends BaseController
{
    /**
     * 微信公众号服务入口.
     *
     * 用于承接微信服务器验证、消息推送和事件推送。
     *
     * @return mixed 返回微信服务响应。
     */
    public function serve()
    {
        return app()->make(OfficialAccount::class)->serve($this->request);
    }
}
