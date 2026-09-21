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
 * 资源服务接口
 */
interface ResourceServiceInterfaceTrait
{
    /**
     * 保存资源
     * @param array $data
     * @return mixed
     */
    public function resourceSave(array $data);

    /**
     * 更新资源
     * @param int|array $id
     * @param array $data
     * @return mixed
     */
    public function resourceUpdate(int|array $id, array $data);

    /**
     * 删除资源
     * @param int $id
     * @return mixed
     */
    public function resourceDelete(int $id);

    /**
     * 显示更新
     * @param int|array $id
     * @param array $data
     * @return mixed
     */
    public function resourceShowUpdate(int|array $id, array $data);

    /**
     * 读取资源
     * @param int|array $id
     * @return mixed
     */
    public function resourceRead(int|array $id);

    /**
     * 设置资源选项
     * @param array $options
     * @return self
     */
    public function serResourceOptions(array $options = []): self;

    /**
     * 获取列表
     * @param array $params
     * @param array|string $fields
     * @param array $with
     * @param string|array|null $sort
     * @return mixed
     */
    public function getResourceList(array $params = [], array|string $fields = [], array $with = [], string|array|null $sort = null);

    /**
     * 创建资源
     * @param array $params
     * @return array
     */
    public function resourceCreate(array $params): array;
}