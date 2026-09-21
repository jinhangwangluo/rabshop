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

namespace app\controller\admin\setting;

use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\common\service\setting\CategoryService;
use app\controller\admin\AuthController;
use think\annotation\Inject;

/**
 * 系统分类
 */
class ConfigCategory extends AuthController implements ResourceControllerInterface
{

    use ResourceControllerTrait;

    /**
     * @var CategoryService
     */
    #[Inject]
    protected CategoryService $service;

    /**
     * 状态字段
     * @return string
     */
    protected function getStatusField(): string
    {
        return 'status';
    }

    /**
     * 验证类
     * @return string|null
     */
    protected function getValidateClassName(): string|null
    {
        return null;
    }

    /**
     * 索引字段
     * @return array
     */
    protected function getIndexFields(): array
    {
        return [];
    }

    /**
     * 请求字段
     * @return array
     */
    protected function getRequestFields(): array
    {
        return [
            ['path', ''],
            ['name', ''],
            ['sort', 0],
        ];
    }

    /**
     * 表单字段
     * @return array[]
     */
    protected function getFormFields(): array
    {
        return [
            ['status', 1]
        ];
    }
}
