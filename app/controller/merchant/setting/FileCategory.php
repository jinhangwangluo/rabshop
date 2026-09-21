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

namespace app\controller\merchant\setting;

use app\common\service\setting\CategoryService;
use app\common\service\setting\FileService;
use app\controller\merchant\AuthController;
use FormBuilder\Exception\FormBuilderException;
use think\annotation\Inject;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Response;

/**
 * 商户端文件分类
 * Class FileCategory
 * @package app\controller\merchant\setting
 */
class FileCategory extends AuthController
{
    /**
     * @var CategoryService
     */
    #[Inject]
    protected CategoryService $service;

    /**
     * 文件分类类型
     * @var int
     */
    protected int $type = CategoryService::FILE_TYPE;

    /**
     * 获取分类列表
     * @return Response
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function index(): Response
    {
        // 使用 merchantId 作为 owner_id 区分商户分类
        return $this->success($this->service->getCategoryTree($this->type, $this->merchantId));
    }

    /**
     * 获取分类创建或修改表单
     * @return Response
     * @throws DataNotFoundException
     * @throws DbException
     * @throws FormBuilderException
     * @throws ModelNotFoundException
     */
    public function create(): Response
    {
        $id = $this->request->get('id/d', 0);
        $pid = $this->request->get('pid/d', 0);
        return $this->success($this->service->setCategoryType($this->type)->resourceCreate([
            'id'       => $id,
            'pid'      => $pid,
            'url'      => '/setting/file/category/' . $id,
            'owner_id' => $this->merchantId,
        ]));
    }

    /**
     * 保存分类
     * @param int $id
     * @return Response
     */
    public function save(int $id = 0): Response
    {
        $data = $this->request->postMore([
            ['name', ''],
            ['sort', 0],
            ['path', []],
            ['pid', 0],
            ['other', '']
        ]);

        if (!$data['name']) {
            return $this->fail('分类名称必须填写');
        }

        if (mb_strlen($data['name']) > 20) {
            return $this->fail('分类名称限制20个字');
        }

        // 设置 owner_id 为当前商户ID
        $data['owner_id'] = $this->merchantId;

        if ($id) {
            // 编辑时校验所有权
            $category = $this->service->getModel()
                ->where('id', $id)
                ->where('owner_id', $this->merchantId)
                ->find();
            
            if (!$category) {
                return $this->fail('分类不存在');
            }
            
            $this->service->update($data, ['id' => $id]);
            return $this->success('修改成功');
        } else {
            $data['type'] = $this->type;
            $this->service->create($data);
            return $this->success('保存成功');
        }
    }

    /**
     * 删除分类
     * @param int $id
     * @return Response
     * @throws DbException
     */
    public function delete(int $id): Response
    {
        if (!$id) {
            return $this->fail('缺少参数');
        }

        // 校验所有权
        $category = $this->service->getModel()
            ->where('id', $id)
            ->where('owner_id', $this->merchantId)
            ->find();
        
        if (!$category) {
            return $this->fail('分类不存在');
        }

        // 检查是否有子分类
        if ($this->service->count(['pid' => $id, 'type' => $this->type, 'owner_id' => $this->merchantId])) {
            return $this->fail('请先删除子分类');
        }

        // 检查分类下是否有文件
        /** @var FileService $fileService */
        $fileService = app()->make(FileService::class);
        if ($fileService->count(['cate_id' => $id, 'merchant_id' => $this->merchantId])) {
            return $this->fail('请先删除分类下的图片');
        }

        if ($this->service->delete($id)) {
            return $this->success('删除成功');
        } else {
            return $this->fail('删除失败');
        }
    }

    /**
     * 移动图片到其他分类
     * @return Response
     */
    public function move(): Response
    {
        $cateId = $this->request->post('pid', 0);
        $imageIds = $this->request->post('images', []);

        if (!$cateId || !$imageIds) {
            return $this->fail('缺少参数');
        }

        /** @var FileService $fileService */
        $fileService = app()->make(FileService::class);
        
        // 只能移动自己的文件
        $fileService->getModel()
            ->whereIn('id', $imageIds)
            ->where('merchant_id', $this->merchantId)
            ->update(['cate_id' => $cateId]);

        return $this->success('移动成功');
    }
}
