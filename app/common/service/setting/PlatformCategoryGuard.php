<?php

declare(strict_types=1);

namespace app\common\service\setting;

use app\common\exception\AdminResponseException;

/** 平台分类的 ID 操作必须验证类型，不能仅凭分类主键修改其他模块的数据。 */
trait PlatformCategoryGuard
{
    /** @param array $params 筛选。 @param array|string $fields 保留资源接口。 @param array $with 保留资源接口。 @param string|array|null $sort 保留资源接口。 @return array 平台同类型分类列表。 */
    public function getResourceList(array $params = [], array|string $fields = [], array $with = [], string|array|null $sort = null)
    {
        $query = $this->getModel()->where(['type' => $this->categoryType, 'owner_id' => 0]);
        $count = (clone $query)->count();
        $list = $query->order('sort desc,id desc')->field(['other', 'id', 'pid', 'name', 'sort', 'status', 'path', 'create_time'])->select()->toArray();
        return compact('list', 'count');
    }

    /** @param array $params 动态表单参数。 @return array 分类表单，主键及父级同样校验类型。 */
    public function resourceCreate(array $params): array
    {
        if ((int)($params['id'] ?? 0) > 0) $this->assertPlatformCategoryRow((int)$params['id']);
        if ((int)($params['pid'] ?? 0) > 0) $this->assertPlatformCategoryRow((int)$params['pid']);
        $params['owner_id'] = 0;
        return parent::resourceCreate($params);
    }

    /** @param int|array $id 分类 ID。 @return array 已验证的平台分类。 */
    public function resourceRead(int|array $id)
    {
        return $this->assertPlatformCategoryRow((int)$id)->toArray();
    }

    /** @param int $id 分类 ID。 @return bool 删除结果。 */
    public function resourceDelete(int $id)
    {
        $this->assertPlatformCategoryRow($id);
        if ($this->getModel()->where(['pid' => $id, 'type' => $this->categoryType, 'owner_id' => 0])->count()) {
            throw new AdminResponseException('请先删除子分类');
        }
        $table = $this->categoryType === CategoryService::LIVE_TYPE ? 'live_room' : 'special';
        if (\think\facade\Db::name($table)->where('category_id', $id)->count()) {
            throw new AdminResponseException('分类仍被业务数据引用，请先调整关联');
        }
        return parent::resourceDelete($id);
    }

    /** @param int|array $id 分类 ID。 @param array $data 状态。 @return bool 更新结果。 */
    public function resourceShowUpdate(int|array $id, array $data)
    {
        $this->assertPlatformCategoryRow((int)$id);
        if (!isset($data['status']) || !in_array((int)$data['status'], [0, 1], true)) {
            throw new AdminResponseException('分类状态无效');
        }
        return parent::resourceShowUpdate($id, ['status' => (int)$data['status']]);
    }

    /** @param int $id 分类 ID。 @return mixed 同类型且归属平台的分类模型。 */
    protected function assertPlatformCategoryRow(int $id)
    {
        $row = $this->getModel()->where(['id' => $id, 'type' => $this->categoryType, 'owner_id' => 0])->find();
        if (!$row) {
            throw new AdminResponseException('分类不存在或分类类型不匹配');
        }
        return $row;
    }
}
