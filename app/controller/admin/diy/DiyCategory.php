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

namespace app\controller\admin\diy;

use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\common\service\diy\DiyCategoryService;
use app\controller\admin\AuthController;
use think\Response;
use think\annotation\Inject;

/**
 * DIY分类管理控制器
 */
class DiyCategory extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    /**
     * DIY分类服务
     */
    #[Inject]
    protected DiyCategoryService $service;

    /**
     * 状态字段
     */
    protected function getStatusField(): string
    {
        return 'status';
    }

    /**
     * 验证类
     */
    protected function getValidateClassName(): string|null
    {
        return 'app\\common\\validate\\DiyCategoryValidate';
    }

    /**
     * 搜索字段
     */
    protected function getIndexFields(): array
    {
        return [];
    }

    /**
     * 请求字段
     */
    protected function getRequestFields(): array
    {
        return [
            ['name', ''],
            ['sort', 0, '', 'd'],
            ['path', []],
            ['pid', 0, '', 'd'],
            ['status', 1, '', 'd'],
        ];
    }

    /**
     * 表单字段
     */
    protected function getFormFields(): array
    {
        return [];
    }

    /**
     * 获取分类树
     */
    public function tree(): Response
    {
        $status = $this->request->get('status', '');
        $status = $status === '' ? null : (int)$status;
        return $this->success('获取成功', $this->service->getTree($status));
    }

    /**
     * 快捷新增分类
     */
    public function quickAdd(): Response
    {
        $name = $this->request->post('name', '');
        $pid = (int)$this->request->post('pid', 0);
        $item = $this->service->quickAdd($name, $pid);
        return $this->success('添加成功', $item);
    }
}
