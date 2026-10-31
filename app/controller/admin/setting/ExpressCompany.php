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
use app\common\service\setting\ExpressCompanyService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 快递公司管理控制器
 */
class ExpressCompany extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    #[Inject]
    protected ExpressCompanyService $service;

    protected function getStatusField(): string
    {
        return 'status';
    }

    protected function getValidateClassName(): string|null
    {
        return null;
    }

    protected function getIndexFields(): array
    {
        return [
            ['keyword', ''],
            ['status', ''],
        ];
    }

    protected function getRequestFields(): array
    {
        return [
            ['name', ''],
            ['code', ''],
            ['is_common', 0],
            ['sort', 0],
            ['status', 1],
        ];
    }

    protected function getFormFields(): array
    {
        return [];
    }

    public function options(): Response
    {
        $params = $this->request->getMore([
            ['keyword', ''],
        ]);

        return $this->success('获取成功', $this->service->options($params));
    }

    public function queryConfig(): Response
    {
        return $this->success('获取成功', $this->service->getQueryConfigStatus());
    }

    public function saveQueryConfig(): Response
    {
        $appcode = (string)$this->request->post('appcode', '');

        return $this->success('保存成功', $this->service->saveQueryConfig($appcode));
    }
}
