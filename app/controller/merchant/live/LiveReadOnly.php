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

namespace app\controller\merchant\live;

use app\common\service\live\LiveCategoryService;
use app\common\service\live\LiveGiftService;
use app\common\service\live\LiveRoomLevelConfigService;
use app\controller\merchant\AuthController;
use think\annotation\Inject;

/**
 * 商户端直播只读资源控制器
 * 直播分类、礼物、等级配置由平台管理，商户端仅提供只读访问
 */
class LiveReadOnly extends AuthController
{
    #[Inject]
    protected LiveCategoryService $categoryService;

    #[Inject]
    protected LiveGiftService $giftService;

    #[Inject]
    protected LiveRoomLevelConfigService $levelConfigService;

    /**
     * 获取直播分类下拉选项（启用状态）
     */
    public function categoryOptions(): mixed
    {
        $list = $this->categoryService->getOptions();
        return $this->success('获取成功', $list);
    }

    /**
     * 获取可用礼物列表
     */
    public function availableGifts(): mixed
    {
        $list = $this->giftService->getAvailableGifts();
        return $this->success('获取成功', $list);
    }

    /**
     * 获取等级配置下拉选项（启用状态）
     */
    public function levelConfigOptions(): mixed
    {
        $list = $this->levelConfigService->getOptions();
        return $this->success('获取成功', $list);
    }
}
