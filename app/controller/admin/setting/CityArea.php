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

use app\common\service\setting\CityAreaService;
use app\controller\admin\AuthController;
use app\validate\setting\CityAreaValidate;
use think\annotation\Inject;
use think\Response;

/**
 * 地址管理控制器。
 */
class CityArea extends AuthController
{
    /**
     * @var CityAreaService
     */
    #[Inject]
    protected CityAreaService $service;

    /**
     * 获取后台地址列表。
     *
     * @return Response
     */
    public function list(): Response
    {
        $where = $this->request->getMore([
            ['id', ''],
            ['pid', ''],
            ['parent_id', ''],
            ['level', ''],
            ['status', ''],
            ['name', ''],
        ]);

        return $this->success($this->service->getAdminList($where));
    }

    /**
     * 获取地址树。
     *
     * @return Response
     */
    public function tree(): Response
    {
        $where = $this->request->getMore([
            ['level', ''],
            ['status', ''],
            ['name', ''],
        ]);

        return $this->success($this->service->tree($where));
    }

    /**
     * 获取下拉列表。
     *
     * @return Response
     */
    public function options(): Response
    {
        return $this->success($this->service->options());
    }

    /**
     * 保存地址。
     *
     * @return Response
     */
    public function save(): Response
    {
        $data = $this->checkParams();
        $this->service->create($data);

        return $this->success('保存成功');
    }

    /**
     * 修改地址。
     *
     * @param int $id 地址ID。
     * @return Response
     */
    public function edit(int $id): Response
    {
        $data = $this->checkParams();
        $this->service->update($id, $data);

        return $this->success('修改成功');
    }

    /**
     * 修改地址状态。
     *
     * @param int $id 地址ID。
     * @param int $status 状态。
     * @return Response
     */
    public function status(int $id, int $status): Response
    {
        $status = $status === 1 ? 1 : 0;
        $this->service->update($id, ['status' => $status]);

        return $this->success('修改成功');
    }

    /**
     * 删除地址。
     *
     * @param int $id 地址ID。
     * @return Response
     */
    public function delete(int $id): Response
    {
        $this->service->deleteCityArea($id);

        return $this->success('删除成功');
    }

    /**
     * 获取并校验地址提交参数。
     *
     * @return array
     */
    public function checkParams(): array
    {
        $data = $this->request->postMore([
            ['id', 0],
            ['path', ''],
            ['parent_id', 0],
            ['type', 1],
            ['name', ''],
            ['level', 1],
            ['sort', 0],
            ['status', 1],
        ]);
        $this->validate($data, CityAreaValidate::class);

        return $data;
    }
}
