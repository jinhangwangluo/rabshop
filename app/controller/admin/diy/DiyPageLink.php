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
use app\common\service\diy\DiyPageLinkService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * DIY页面链接管理控制器
 */
class DiyPageLink extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    /**
     * DIY页面链接服务
     */
    #[Inject]
    protected DiyPageLinkService $service;

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
        return 'app\\common\\validate\\DiyPageLinkValidate';
    }

    /**
     * 搜索字段
     */
    protected function getIndexFields(): array
    {
        return [
            ['page', 1, '', 'd'],
            ['limit', 20, '', 'd'],
            ['cate_id', '', '', 'd'],
            ['link_name', ''],
            ['keyword', ''],
            ['link_type', ''],
            ['platform_type', ''],
            ['status', ''],
        ];
    }

    /**
     * 请求字段
     */
    protected function getRequestFields(): array
    {
        return [
            ['cate_id', 0, '', 'd'],
            ['link_name', ''],
            ['link_url', ''],
            ['link_type', 'page'],
            ['platform_type', 'common'],
            ['mini_appid', ''],
            ['mini_path', ''],
            ['icon', ''],
            ['param_desc', ''],
            ['example', ''],
            ['remark', ''],
            ['sort', 0, '', 'd'],
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
     * 选链弹窗数据
     */
    public function options(): Response
    {
        $params = $this->request->getMore($this->getIndexFields());
        return $this->success('获取成功', $this->service->getSelectorList($params));
    }
}
