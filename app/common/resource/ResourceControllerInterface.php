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

namespace app\common\resource;

/**
 * 资源接口.
 */
interface ResourceControllerInterface
{
    /**
     * 展示数据.
     * @return mixed
     */
    public function index();

    /**
     * 创建数据.
     * @return mixed
     */
    public function create();

    /**
     * 添加.
     * @return mixed
     */
    public function save();

    /**
     * 隐藏展示.
     * @param mixed $id
     * @return mixed
     */
    public function show($id);

    /**
     * 获取修改数据.
     * @param mixed $id
     * @return mixed
     */
    public function read($id);

    /**
     * 修改数据.
     * @param mixed $id
     * @return mixed
     */
    public function update($id);

    /**
     * 删除数据.
     * @param mixed $id
     * @return mixed
     */
    public function delete($id);
}