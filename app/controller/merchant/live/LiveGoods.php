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

use app\common\exception\AdminResponseException;
use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\common\service\live\LiveGoodsService;
use app\common\service\live\LiveRoomService;
use app\controller\merchant\AuthController;
use think\annotation\Inject;

/**
 * 商户端直播带货商品管理控制器
 */
class LiveGoods extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait {
        save as protected traitSave;
        show as protected traitShow;
        update as protected traitUpdate;
        delete as protected traitDelete;
    }

    #[Inject]
    protected LiveGoodsService $service;

    #[Inject]
    protected LiveRoomService $liveRoomService;

    protected function getStatusField(): string
    {
        return 'status';
    }

    protected function getValidateClassName(): ?string
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
     * 获取商品列表时校验直播间归属。
     */
    public function index(): mixed
    {
        $params  = $this->request->getMore($this->getIndexFields());
        $roomIds = $this->liveRoomService->getMerchantRoomIds($this->merchantId);

        if (($params['live_room_id'] ?? '') !== '') {
            $this->checkRoomOwner((int)$params['live_room_id']);
            $roomIds = [(int)$params['live_room_id']];
        }

        return $this->success($this->service->getMerchantGoodsList($params, $roomIds));
    }

    /**
     * 读取直播商品详情时校验归属。
     *
     * @param mixed $id 直播商品 ID
     */
    public function read($id): mixed
    {
        if (!$id) {
            return $this->fail($this->message['edit']['empty']);
        }

        $this->checkGoodsOwner((int)$id);
        return $this->success($this->service->resourceRead((int)$id));
    }

    /**
     * 新增直播商品前校验直播间归属。
     */
    public function save(): mixed
    {
        $this->checkRoomOwner((int)$this->request->post('live_room_id', 0));
        return $this->traitSave();
    }

    /**
     * 修改商品状态前显式校验归属。
     *
     * @param mixed $id 直播商品 ID
     */
    public function show($id): mixed
    {
        if (!$id) {
            return $this->fail($this->message['show']['empty']);
        }

        $this->checkGoodsOwner((int)$id);
        return $this->traitShow($id);
    }

    /**
     * 更新直播商品前显式校验归属。
     *
     * @param mixed $id 直播商品 ID
     */
    public function update($id): mixed
    {
        if (!$id) {
            return $this->fail($this->message['update']['empty']);
        }

        $goods      = $this->checkGoodsOwner((int)$id);
        $liveRoomId = (int)$this->request->post('live_room_id', (int)($goods['live_room_id'] ?? 0));
        $this->checkRoomOwner($liveRoomId);

        return $this->traitUpdate($id);
    }

    /**
     * 删除直播商品前显式校验归属。
     *
     * @param mixed $id 直播商品 ID
     */
    public function delete($id): mixed
    {
        if (!$id) {
            return $this->fail($this->message['delete']['empty']);
        }

        $this->checkGoodsOwner((int)$id);
        return $this->traitDelete($id);
    }

    /**
     * 批量更新排序。
     */
    public function batchSort(): mixed
    {
        $liveRoomId = (int)$this->request->post('live_room_id', 0);
        $this->checkRoomOwner($liveRoomId);

        $sortData = $this->request->post('sort_data', []);
        $this->service->batchUpdateSort($liveRoomId, $sortData);
        return $this->success('排序更新成功');
    }

    /**
     * 获取付费专题下拉选项（含价格、封面）。
     */
    public function specialOptions(): mixed
    {
        $data = $this->service->getSpecialOptions($this->merchantId);
        return $this->success('ok', $data);
    }

    /**
     * 校验直播间归属。
     */
    protected function checkRoomOwner(int $liveRoomId): void
    {
        $this->liveRoomService->checkMerchantOwner($this->merchantId, $liveRoomId, '直播间');
    }

    /**
     * 校验直播商品归属，并返回商品数据。
     */
    protected function checkGoodsOwner(int $goodsId): array
    {
        $goods = $this->service->get($goodsId);
        if (!$goods) {
            throw new AdminResponseException('直播间商品不存在');
        }

        $goodsData = $goods->toArray();
        $this->checkRoomOwner((int)($goodsData['live_room_id'] ?? 0));
        return $goodsData;
    }
}
