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


use app\common\service\setting\CategoryService;
use app\common\service\setting\FileService;
use app\controller\admin\AuthController;
use FormBuilder\Exception\FormBuilderException;
use think\annotation\Inject;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Response;

/**
 * 文件分类
 * Class FileCategory
 * @package app\admin\controller\setting
 */
class FileCategory extends AuthController
{

    /**
     * @var CategoryService
     */
    #[Inject]
    protected CategoryService $service;

    /**
     * 文件分类
     * @var int
     */
    protected int $type = CategoryService::FILE_TYPE;

    /**
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function index()
    {
        return $this->success($this->service->getCategoryTree($this->type));
    }

    /**
     * 获取分类创建或者修改表单
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException|FormBuilderException
     */
    public function create()
    {
        $id = $this->request->get('id/d', 0);
        $pid = $this->request->get('pid/d', 0);

        return $this->success($this->service->setCategoryType($this->type)->resourceCreate([
            'id'  => $id,
            'pid' => $pid,
            'url' => '/setting/file/category/' . $id
        ]));
    }

    /**
     * 保存修改分类
     * @param int $id
     * @return mixed
     */
    public function save($id = 0)
    {
        $data = $this->request->postMore([
            ['name', ''],
            ['sort', 0, '', 'd'],
            ['path', []],
            ['pid', 0],
            ['other', '']
        ]);

        if (!$data['name']) {
            return $this->fail('分类名称必须填写');
        }

        if (mb_strlen($data['name']) > 20) {
            return $this->fail('分类名称限制6个字');
        }

        if ($id) {
            $this->service->update(['id' => $id], $data);
            return $this->success('修改成功');
        } else {
            $data['type'] = $this->type;
            $this->service->create($data);

            return $this->success('保存成功');
        }
    }

    /**
     * 删除分类
     * @param $id
     * @return mixed
     * @throws DbException
     */
    public function delete($id)
    {
        if (!$id) {
            return $this->fail('缺少参数');
        }

        if ($this->service->count(['pid' => $id, 'type' => $this->type])) {
            return $this->fail('请先删除子分类');
        }

        /** @var FileService $service */
        $service = app()->make(FileService::class);
        if ($service->count(['cate_id' => $id])) {
            return $this->fail('请先删除分类下的图片');
        }

        if ($this->service->delete($id)) {
            return $this->success('删除成功');
        } else {
            return $this->fail('删除失败');
        }
    }

    /**
     * 移动图片
     * @param FileService $service
     * @return Response
     */
    public function move(FileService $service)
    {
        $cateId = $this->request->post('pid', []);
        $imageIds = $this->request->post('images', []);

        if (!$cateId || !$imageIds) {
            return $this->fail('缺少参数');
        }

        $service->move($imageIds, $cateId);

        return $this->success('移动成功');
    }
}
