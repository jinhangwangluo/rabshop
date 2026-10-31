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

namespace app\common\service\live;

use app\common\exception\AdminResponseException;
use app\common\service\setting\CategoryService;
use app\common\service\setting\PlatformCategoryGuard;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 直播分类服务
 */
class LiveCategoryService extends CategoryService
{
    use PlatformCategoryGuard;

    protected int $categoryType = self::LIVE_TYPE;

    /** @param array $data 分类字段。 @return mixed 保存结果。 */
    public function resourceSave(array $data)
    {
        return parent::resourceSave($this->normalizeCategoryData($data));
    }

    /** @param int|array $id 分类 ID。 @param array $data 更新字段。 @return bool 更新结果。 */
    public function resourceUpdate(int|array $id, array $data)
    {
        $current = $this->assertPlatformCategoryRow((int)$id)->toArray();
        // 动态表单提交 path；不能让旧 pid 覆盖用户刚选的新父级。
        if (array_key_exists('path', $data) && !array_key_exists('pid', $data)) {
            unset($current['pid']);
        }
        $normalized = $this->normalizeCategoryData(array_replace($current, $data), (int)$id);
        return $this->transaction(function () use ($id, $current, $normalized) {
            $result = parent::resourceUpdate($id, $normalized);
            // 移动父分类时同步整棵子树的祖先路径，后续筛选和再次编辑才能保持一致。
            $oldPrefix = '/' . implode('/', [...($current['path'] ?? []), (int)$id]) . '/';
            $newAncestors = [...$normalized['path'], (int)$id];
            if (($current['path'] ?? []) !== $normalized['path']) {
                $descendants = $this->getModel()->where(['type' => self::LIVE_TYPE, 'owner_id' => 0])->whereLike('path', $oldPrefix . '%')->select();
                foreach ($descendants as $child) {
                    $suffix = array_slice($child['path'], count($current['path'] ?? []) + 1);
                    $child->save(['path' => [...$newAncestors, ...$suffix]]);
                }
            }
            return $result;
        });
    }

    /** @param array $data 分类数据。 @param int $id 编辑 ID。 @return array 白名单字段及真实祖先路径。 */
    private function normalizeCategoryData(array $data, int $id = 0): array
    {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 30) {
            throw new AdminResponseException('分类名称不能为空且最多30个字符');
        }
        $path = $data['path'] ?? [];
        if (is_string($path)) {
            $path = json_decode($path, true) ?: [];
        }
        $pid = array_key_exists('pid', $data) ? (int)$data['pid'] : (int)(is_array($path) ? end($path) : 0);
        $ancestors = [];
        for ($parentId = $pid; $parentId > 0;) {
            if ($parentId === $id || in_array($parentId, $ancestors, true) || count($ancestors) >= 64) {
                throw new AdminResponseException('分类父子关系存在循环');
            }
            $parent = $this->assertPlatformCategoryRow($parentId);
            $ancestors[] = $parentId;
            $parentId = (int)$parent['pid'];
        }
        $other = $data['other'] ?? '';
        if (!in_array((int)($data['status'] ?? 1), [0, 1], true)) {
            throw new AdminResponseException('分类状态无效');
        }
        return [
            'name' => $name, 'pid' => $pid, 'path' => array_reverse($ancestors),
            'owner_id' => 0, 'type' => self::LIVE_TYPE,
            'sort' => (int)($data['sort'] ?? 0), 'status' => (int)($data['status'] ?? 1),
            'image' => (string)($data['image'] ?? ''), 'banner_image' => (string)($data['banner_image'] ?? ''),
            'description' => (string)($data['description'] ?? ''),
            'other' => is_string($other) ? $other : json_encode($other, JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * 获取直播分类下拉选项（启用状态）
     *
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getOptions(): array
    {
        return $this->getModel()
            ->where(['type' => self::LIVE_TYPE, 'owner_id' => 0, 'status' => 1])
            ->field(['id', 'name'])
            ->order('sort desc, id desc')
            ->select()
            ->toArray();
    }
}
