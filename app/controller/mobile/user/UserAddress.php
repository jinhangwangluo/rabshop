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

namespace app\controller\mobile\user;

use app\BaseController;
use app\common\service\user\UserAddressService;
use think\annotation\Inject;
use think\Response;

/**
 * 移动端用户收货地址控制器.
 */
class UserAddress extends BaseController
{
    /**
     * 用户收货地址服务.
     *
     * @var UserAddressService
     */
    #[Inject]
    protected UserAddressService $service;

    /**
     * 获取收货地址列表。
     *
     * @return Response 返回当前用户收货地址列表。
     */
    public function index(): Response
    {
        $userId = $this->getLoginUserId();
        if ($userId <= 0) {
            return $this->fail('未获取到登录用户');
        }

        return $this->success('获取成功', $this->service->getUserAddressList($userId));
    }

    /**
     * 获取收货地址详情。
     *
     * GET 参数：
     * - id int 收货地址 ID。
     *
     * @return Response 返回收货地址详情。
     */
    public function detail(): Response
    {
        $userId = $this->getLoginUserId();
        $id = (int)$this->request->get('id/d', 0);

        return $this->success('获取成功', $this->service->getUserAddressDetail($userId, $id));
    }

    /**
     * 新增收货地址。
     *
     * POST 参数：
     * - real_name string 收货人。
     * - phone string 手机号。
     * - province/city/district string 省市区名称。
     * - province_id/city_id/district_id/area_id int 省市区 ID。
     * - latitude/longitude string 经纬度。
     * - map_name string 地图定位名称。
     * - detail string 详细地址。
     * - user_address string 完整地址。
     * - is_default int 是否默认地址。
     *
     * @return Response 返回新增后的地址信息。
     */
    public function create(): Response
    {
        $userId = $this->getLoginUserId();
        $data = $this->getAddressRequestData();

        return $this->success('新增成功', $this->service->createUserAddress($userId, $data));
    }

    /**
     * 修改收货地址。
     *
     * POST 参数：
     * - id int 收货地址 ID。
     * - 其余地址字段同 create()。
     *
     * @return Response 返回修改后的地址信息。
     */
    public function update(): Response
    {
        $userId = $this->getLoginUserId();
        $id = (int)$this->request->post('id/d', 0);
        $data = $this->getAddressRequestData();

        return $this->success('修改成功', $this->service->updateUserAddress($userId, $id, $data));
    }

    /**
     * 删除收货地址。
     *
     * POST 参数：
     * - id int 收货地址 ID。
     *
     * @return Response 返回删除结果。
     */
    public function delete(): Response
    {
        $userId = $this->getLoginUserId();
        $id = (int)$this->request->post('id/d', 0);

        $this->service->deleteUserAddress($userId, $id);

        return $this->success('删除成功');
    }

    /**
     * 获取当前登录用户ID。
     *
     * @return int 当前登录用户 ID。
     */
    protected function getLoginUserId(): int
    {
        return (int)($this->request->user_id ?? 0);
    }

    /**
     * 获取地址提交字段。
     *
     * @return array 地址表单字段。
     */
    protected function getAddressRequestData(): array
    {
        return [
            'real_name' => trim((string)$this->request->post('real_name', '')),
            'phone' => trim((string)$this->request->post('phone', '')),
            'province' => trim((string)$this->request->post('province', '')),
            'city' => trim((string)$this->request->post('city', '')),
            'district' => trim((string)$this->request->post('district', '')),
            'province_id' => (int)$this->request->post('province_id/d', 0),
            'city_id' => (int)$this->request->post('city_id/d', 0),
            'district_id' => (int)$this->request->post('district_id/d', 0),
            'area_id' => (int)$this->request->post('area_id/d', 0),
            'latitude' => trim((string)$this->request->post('latitude', '')),
            'longitude' => trim((string)$this->request->post('longitude', '')),
            'map_name' => trim((string)$this->request->post('map_name', '')),
            'detail' => trim((string)$this->request->post('detail', '')),
            'user_address' => trim((string)$this->request->post('user_address', '')),
            'is_default' => (int)$this->request->post('is_default/d', 0),
        ];
    }
}
