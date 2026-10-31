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

namespace app\common\service\cs;

use app\common\service\setting\CategoryService;

/**
 * 知识库分类服务
 */
class CsKnowledgeCategoryService extends CsScriptCategoryService
{
    /**
     * 知识库分类类型
     */
    protected int $categoryType = CategoryService::KNOWLEDGE_TYPE;
}
