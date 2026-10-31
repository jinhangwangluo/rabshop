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

namespace app\controller\admin\live;

use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\common\service\live\LiveRoomLevelConfigService;
use app\controller\admin\AuthController;
use think\annotation\Inject;

/**
 * 直播间等级配置管理控制器
 */
class LiveRoomLevelConfig extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    #[Inject]
    protected LiveRoomLevelConfigService $service;

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
            ['name', ''],
            ['status', ''],
        ];
    }

    protected function getRequestFields(): array
    {
        return [
            ['level', 1],
            ['name', ''],
            ['icon', ''],
            ['min_contribution', '0.00'],
            ['benefits', ''],
            ['sort', 0],
            ['status', 1],
        ];
    }

    protected function getFormFields(): array
    {
        return [];
    }

    /**
     * 获取启用的等级选项列表
     */
    public function options(): mixed
    {
        $list = $this->service->getOptions();
        return $this->success('获取成功', $list);
    }
}
