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

namespace app\controller\merchant\content;

use app\common\service\content\SpecialCategoryService;
use app\controller\merchant\AuthController;
use think\annotation\Inject;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Response;

/**
 * 商户端专题分类控制器（只读）
 * 分类由平台管理，商户仅可查看
 */
class SpecialCategory extends AuthController
{
    /**
     * @var SpecialCategoryService
     */
    #[Inject]
    protected SpecialCategoryService $service;

    /**
     * 获取平台专题分类树（用于级联选择器）
     * @return Response
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function tree(): Response
    {
        $tree = $this->service->getPlatformTree();
        return $this->success('获取成功', $tree);
    }
}
