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

namespace app\common\service\cs;

use app\common\exception\AdminResponseException;
use app\common\service\setting\CategoryService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 话术分类服务
 */
class CsScriptCategoryService extends CategoryService
{
    /**
     * 话术分类类型
     */
    protected int $categoryType = self::SCRIPT_TYPE;

    /**
     * 分类归属
     */
    protected int $ownerId = 0;

    /**
     * 设置归属ID
     */
    public function setOwnerId(int $ownerId): self
    {
        $this->ownerId = $ownerId;
        return $this;
    }

    /**
     * 获取资源列表
     * @param array $params
     * @param array|string $fields
     * @param array $with
     * @param string|array|null $sort
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getResourceList(array $params = [], array|string $fields = [], array $with = [], string|array|null $sort = null)
    {
        $query = $this->getModel()->where([
            'type' => $this->categoryType,
            'owner_id' => $this->ownerId,
        ]);

        if (($params['name'] ?? '') !== '') {
            $query->where('name', 'like', '%' . trim((string)$params['name']) . '%');
        }
        if (($params['pid'] ?? '') !== '') {
            $query->where('pid', (int)$params['pid']);
        }
        if (($params['status'] ?? '') !== '') {
            $query->where('status', (int)$params['status']);
        }

        $count = $query->count();
        $list = $query
            ->order('sort desc,id desc')
            ->field(['other', 'id', 'name', 'sort', 'status', 'path', 'pid', 'create_time'])
            ->select()
            ->toArray();

        return compact('list', 'count');
    }

    /**
     * 保存资源
     * @param array $data
     * @return mixed
     */
    public function resourceSave(array $data)
    {
        $data['type'] = $this->categoryType;
        $data['owner_id'] = $this->ownerId;
        return $this->create($data);
    }

    /**
     * 读取资源
     * @param int|array $id
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function resourceRead(int|array $id)
    {
        $model = $this->getModel()->where([
            'id' => (int)$id,
            'type' => $this->categoryType,
            'owner_id' => $this->ownerId,
        ])->find();
        if (!$model) {
            throw new ModelNotFoundException('查询的数据不存在');
        }
        return $model->toArray();
    }

    /**
     * 更新资源
     * @param int|array $id
     * @param array $data
     * @return bool
     */
    public function resourceUpdate(int|array $id, array $data)
    {
        $this->getModel()->where([
            'id' => (int)$id,
            'type' => $this->categoryType,
            'owner_id' => $this->ownerId,
        ])->update($data);
        return true;
    }

    /**
     * 删除资源
     * @param int $id
     * @return bool
     */
    public function resourceDelete(int $id)
    {
        return $this->getModel()->where([
            'id' => $id,
            'type' => $this->categoryType,
            'owner_id' => $this->ownerId,
        ])->delete() > 0;
    }

    /**
     * 更新展示状态
     * @param int|array $id
     * @param array $data
     * @return bool
     */
    public function resourceShowUpdate(int|array $id, array $data)
    {
        $this->getModel()->where([
            'id' => (int)$id,
            'type' => $this->categoryType,
            'owner_id' => $this->ownerId,
        ])->update($data);
        return true;
    }

    /**
     * 快捷添加分类
     * @param string $name
     * @param int $pid
     * @param int $ownerId
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function quickAdd(string $name, int $pid = 0, int $ownerId = 0): array
    {
        $name = trim($name);
        if ($name === '') {
            throw new AdminResponseException('分类名称不能为空');
        }
        if (mb_strlen($name) > 30) {
            throw new AdminResponseException('分类名称最多30个字符');
        }
        if ($this->getModel()->where([
            'name' => $name,
            'type' => $this->categoryType,
            'owner_id' => $ownerId,
        ])->find()) {
            throw new AdminResponseException('分类名称已存在');
        }

        if ($pid > 0) {
            $parent = $this->getModel()->where([
                'id' => $pid,
                'type' => $this->categoryType,
                'owner_id' => $ownerId,
            ])->find();
            if (!$parent) {
                throw new AdminResponseException('父级分类不存在');
            }
            if ((int)$parent->pid !== 0) {
                throw new AdminResponseException('最多支持二级分类');
            }
        }

        $data = [
            'name' => $name,
            'pid' => $pid,
            'type' => $this->categoryType,
            'owner_id' => $ownerId,
            'sort' => 0,
            'status' => 1,
            'path' => $pid > 0 ? json_encode([$pid]) : json_encode([]),
        ];

        $model = $this->getModel()->create($data);

        return [
            'id' => $model->id,
            'label' => $name,
            'value' => $model->id,
            'pid' => $pid,
        ];
    }
}
