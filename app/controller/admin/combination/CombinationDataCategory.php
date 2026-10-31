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

namespace app\controller\admin\combination;

use app\common\service\setting\CategoryService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 平台端组合数据分类控制器.
 *
 * 分类数据复用统一 category 表，通过 CategoryService::COMBINATION_DATA_TYPE 隔离类型。
 */
class CombinationDataCategory extends AuthController
{
    /**
     * 统一分类服务.
     *
     * @var CategoryService
     */
    #[Inject]
    protected CategoryService $service;

    /**
     * 组合数据分类类型.
     *
     * @var int
     */
    protected int $type = CategoryService::COMBINATION_DATA_TYPE;

    /**
     * 获取平台端组合数据分类树.
     *
     * @return Response
     */
    public function index(): Response
    {
        return $this->success(['list' => $this->service->getCategoryTree($this->type, 0)]);
    }

    /**
     * 获取平台端组合数据分类详情.
     *
     * @param int $id 分类ID
     * @return Response
     */
    public function read(int $id): Response
    {
        $info = $this->service->getModel()
            ->where('id', $id)
            ->where('type', $this->type)
            ->where('owner_id', 0)
            ->find();

        if (!$info) {
            return $this->fail('分类不存在');
        }

        return $this->success($info->toArray());
    }

    /**
     * 保存平台端组合数据分类.
     *
     * @param int $id 分类ID，0 表示新增
     * @return Response
     */
    public function save(int $id = 0): Response
    {
        $data = $this->request->postMore([
            ['name', ''],
            ['sort', 0],
            ['path', []],
            ['pid', 0],
            ['status', 1],
            ['other', ''],
        ]);

        if (!$data['name']) {
            return $this->fail('分类名称必须填写');
        }

        $data['type'] = $this->type;
        $data['owner_id'] = 0;

        if ($id) {
            if (!$this->service->getModel()->where('id', $id)->where('type', $this->type)->where('owner_id', 0)->find()) {
                return $this->fail('分类不存在');
            }
            $this->service->update($id, $data);
            return $this->success('修改成功');
        }

        $this->service->create($data);
        return $this->success('保存成功');
    }

    /**
     * 更新平台端组合数据分类.
     *
     * @param int $id 分类ID
     * @return Response
     */
    public function update(int $id): Response
    {
        return $this->save($id);
    }

    /**
     * 删除平台端组合数据分类.
     *
     * @param int $id 分类ID
     * @return Response
     */
    public function delete(int $id): Response
    {
        if ($this->service->count(['pid' => $id, 'type' => $this->type, 'owner_id' => 0])) {
            return $this->fail('请先删除子分类');
        }

        if ($this->service->delete(['id' => $id, 'type' => $this->type, 'owner_id' => 0])) {
            return $this->success('删除成功');
        }

        return $this->fail('删除失败');
    }

    /**
     * 修改平台端组合数据分类状态.
     *
     * @param int $id 分类ID
     * @return Response
     */
    public function show(int $id): Response
    {
        $status = $this->request->get('status');
        if ($status === null) {
            return $this->fail('修改状态值不存在');
        }

        $this->service->update(['id' => $id, 'type' => $this->type, 'owner_id' => 0], ['status' => (int)$status]);
        return $this->success('修改成功');
    }
}
