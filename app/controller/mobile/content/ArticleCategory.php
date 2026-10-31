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

namespace app\controller\mobile\content;

use app\BaseController;
use app\common\service\content\ArticleCategoryService;
use think\annotation\Inject;
use think\Response;

/**
 * 移动端内容分类控制器。
 */
class ArticleCategory extends BaseController
{
    /**
     * 内容分类服务。
     *
     * @var ArticleCategoryService
     */
    #[Inject]
    protected ArticleCategoryService $service;

    /**
     * 获取移动端内容分类树。
     *
     * @return Response 返回内容分类树。
     */
    public function tree(): Response
    {
        return $this->success('获取成功', $this->service->getMobileTree());
    }
}
