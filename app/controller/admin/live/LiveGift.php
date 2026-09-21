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
use app\common\service\live\LiveGiftService;
use app\controller\admin\AuthController;
use think\annotation\Inject;

/**
 * 直播礼物管理控制器（平台专属）
 */
class LiveGift extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    #[Inject]
    protected LiveGiftService $service;

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
            ['name', ''],
            ['icon', ''],
            ['effect_url', ''],
            ['price', 0],
            ['exchange_amount', '0.00'],
            ['score', 0],
            ['sort', 0],
            ['status', 1],
        ];
    }

    protected function getFormFields(): array
    {
        return [];
    }

    /**
     * 获取可用礼物列表（前端直播间使用）
     */
    public function available(): mixed
    {
        $list = $this->service->getAvailableGifts();
        return $this->success('获取成功', $list);
    }
}
