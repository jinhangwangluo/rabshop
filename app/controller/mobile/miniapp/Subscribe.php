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

namespace app\controller\mobile\miniapp;

use app\BaseController;
use app\common\service\message\MessagePushTemplateService;
use think\annotation\Inject;
use think\Response;

/**
 * 微信小程序订阅消息控制器。
 */
class Subscribe extends BaseController
{
    /**
     * @var MessagePushTemplateService
     */
    #[Inject]
    protected MessagePushTemplateService $templateService;

    /**
     * 获取指定业务类型可订阅的小程序模板消息。
     *
     * GET 参数：
     * - type string 可选，订阅业务类型；不传时一次返回全部类型。
     */
    public function messages(): Response
    {
        $type = trim((string)$this->request->get('type', ''));

        return $this->success('获取成功', $this->templateService->getMiniappSubscribeMessages($type));
    }
}
