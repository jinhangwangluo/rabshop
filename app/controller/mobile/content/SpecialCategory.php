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
use app\common\service\content\SpecialCategoryService;
use think\annotation\Inject;
use think\Response;

/**
 * 移动端专题分类控制器.
 */
class SpecialCategory extends BaseController
{
    /**
     * 专题分类服务.
     *
     * @var SpecialCategoryService
     */
    #[Inject]
    protected SpecialCategoryService $service;

    /**
     * 获取移动端专题分类树.
     *
     * @return Response 返回专题分类树。
     */
    public function tree(): Response
    {
        return $this->success('获取成功', $this->service->getMobileTree($this->parseCategoryIds()));
    }

    /**
     * 解析分类 ID 列表。
     *
     * @return array<int>
     */
    protected function parseCategoryIds(): array
    {
        $rawIds = $this->request->get('category_ids', []);
        if (is_string($rawIds)) {
            $rawIds = array_filter(array_map('trim', explode(',', $rawIds)));
        }

        if (!is_array($rawIds)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $rawIds), static fn (int $id): bool => $id > 0));
    }
}
