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

use app\common\service\BaseService;
use app\common\service\HelpService;
use ReflectionClass;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Model;
use think\model\contract\Modelable;
use think\model\concern\SoftDelete;

/**
 * 资源服务.
 * @package app\common\resourceTrait
 * @mixin BaseService
 */
trait ResourceServiceTrait
{

    /**
     * 资源服务配置.
     * @var array
     */
    protected $resourceOptions = [];

    /**
     * 保存数据.
     * @return Modelable
     */
    public function resourceSave(array $data)
    {
        return $this->create($data);
    }

    /**
     * 更新数据.
     * @param array|int $id
     * @param array $data
     * @return bool
     */
    public function resourceUpdate(int|array $id, array $data)
    {
        $this->update($id, $data);
        return true;
    }

    /**
     * 递归获取类（含父类）使用的所有 Trait 名称.
     * @param ReflectionClass $reflection
     * @return array
     */
    private function getAllTraitNames(ReflectionClass $reflection): array
    {
        $traits = array_keys($reflection->getTraits());
        $parent = $reflection->getParentClass();
        if ($parent) {
            $traits = array_merge($traits, $this->getAllTraitNames($parent));
        }
        return $traits;
    }

    /**
     * 删除数据.
     * @param int $id
     * @return bool
     */
    public function resourceDelete(int $id)
    {
        $model = $this->getModel();
        $reflection = new ReflectionClass($model);
        if (in_array(SoftDelete::class, $this->getAllTraitNames($reflection), true)) {
            return $model->destroy($id);
        } else {
            return $this->delete($id);
        }
    }

    /**
     * 修改状态
     * @param int|array $id
     * @param array $data
     * @return true
     */
    public function resourceShowUpdate(int|array $id, array $data)
    {
        $this->update($id, $data);
        return true;
    }

    /**
     * 设置资源服务配置.
     * @param array $options
     * @return self
     */
    public function serResourceOptions(array $options = []): self
    {
        $this->resourceOptions = $options;
        return $this;
    }

    /**
     * 获取资源列表.
     * @param array $params
     * @param array|string $fields
     * @param array $with
     * @param string|array|null $sort
     * @return BaseService|HelpService
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getResourceList(array $params = [], array|string $fields = [], array $with = [], string|array|null $sort = null)
    {
        return $this->getDefaultList($params, $fields, $with, $sort);
    }

    /**
     * 创建数据.
     * @param array $params
     * @return array
     */
    public function resourceCreate(array $params): array
    {
        return [];
    }

    /**
     * 读取数据.
     * @param int|array $id
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function resourceRead(int|array $id)
    {
        $model = $this->get($id);
        if (!$model) {
            throw new ModelNotFoundException('查询的数据不存在');
        }
        return $model->toArray();
    }
}
