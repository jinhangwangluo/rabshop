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
use app\common\service\live\LiveGoodsService;
use app\controller\admin\AuthController;
use think\annotation\Inject;

/**
 * 直播带货商品管理控制器
 */
class LiveGoods extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    #[Inject]
    protected LiveGoodsService $service;

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
            ['live_room_id', ''],
            ['goods_type', ''],
            ['status', ''],
        ];
    }

    protected function getRequestFields(): array
    {
        return [
            ['live_room_id', 0],
            ['goods_type', 'article'],
            ['goods_id', 0],
            ['goods_title', ''],
            ['goods_cover', ''],
            ['goods_price', 0],
            ['sort', 0],
            ['status', 1],
        ];
    }

    protected function getFormFields(): array
    {
        return [];
    }

    /**
     * 批量更新排序
     */
    public function batchSort(): mixed
    {
        $liveRoomId = (int)$this->request->post('live_room_id', 0);
        $sortData   = $this->request->post('sort_data', []);
        $this->service->batchUpdateSort($liveRoomId, $sortData);
        return $this->success('排序更新成功');
    }

    /**
     * 设置直播间正在讲解商品。
     */
    public function explaining(int $id): mixed
    {
        if ($id <= 0) {
            return $this->fail('直播商品 ID 不能为空');
        }

        $isExplaining = (int)$this->request->post('is_explaining', 1);
        $data = $this->service->setExplaining($id, $isExplaining);
        return $this->success('设置成功', $data);
    }

    /**
     * 获取付费专题下拉选项（含价格、封面）
     */
    public function specialOptions(): mixed
    {
        $data = $this->service->getSpecialOptions();
        return $this->success('ok', $data);
    }
}
