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

namespace app\controller\admin\setting;

use app\common\service\about\AboutService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 后台关于我们控制器。
 */
class About extends AuthController
{
    #[Inject]
    protected AboutService $service;

    /**
     * 获取关于我们配置。
     */
    public function schema(): Response
    {
        return $this->success('获取成功', $this->service->schema());
    }

    /**
     * 保存关于我们富文本内容。
     */
    public function saveContent(): Response
    {
        // 富文本字段绕过全局输入过滤，保留 HTML
        $content = (string)$this->request->post('content', '', null);

        return $this->success(
            '保存成功',
            $this->service->saveContent($content)
        );
    }
}
