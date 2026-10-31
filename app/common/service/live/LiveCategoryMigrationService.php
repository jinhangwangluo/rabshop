<?php

declare(strict_types=1);

namespace app\common\service\live;

use app\common\service\BaseService;
use app\common\model\setting\system\Category;
use RuntimeException;
use think\facade\Db;

/** 维护窗口使用的历史分类拆分；映射和完成标记使再次执行不会复制后来新建的专题分类。 */
final class LiveCategoryMigrationService extends BaseService
{
    protected function setModel(): string
    {
        return Category::class;
    }

    /** @return array 只读预检摘要。 */
    public function preview(): array
    {
        $completed = $this->hasTable('live_category_migration_map')
            && Db::name('live_category_migration_map')->where(['kind' => 'state', 'source_id' => 1])->find();
        if ($completed) {
            $invalid = Db::name('live_room')->alias('r')->leftJoin('category c', 'c.id=r.category_id')
                ->where('r.category_id', '>', 0)->where(function ($q): void {
                    $q->whereNull('c.id')->whereOr('c.type', '<>', 11)->whereOr('c.owner_id', '<>', 0);
                })->count();
            if ($invalid > 0) {
                throw new RuntimeException('已迁移数据仍存在非法直播分类引用');
            }
            return ['completed' => true, 'category_count' => 0, 'room_count' => 0];
        }
        $plan = $this->loadPlan();
        return ['completed' => false, 'category_count' => $plan['category_count'], 'room_count' => count($plan['rooms'])];
    }

    /** @return array 迁移结果；仅由已审核的迁移入口调用。 */
    public function migrate(): array
    {
        if ($this->preview()['completed']) {
            return $this->preview();
        }
        return $this->transaction(function (): array {
            $plan = $this->loadPlan();
            $ids = [];
            foreach ($plan['categories'] as $row) {
                $oldId = (int)$row['id'];
                $sourceHash = $this->rowHash($row);
                unset($row['id']);
                $row['type'] = 11;
                $row['pid'] = (int)$row['pid'] > 0 ? $ids[(int)$row['pid']] : 0;
                $ancestors = array_map(fn(int $id): int => $ids[$id], $plan['paths'][$oldId]);
                // Category 模型使用 /id/id/ 路径；JSON 数组会被读取成 0，必须保持宿主契约。
                $row['path'] = $ancestors === [] ? '' : '/' . implode('/', $ancestors) . '/';
                $newId = (int)Db::name('category')->insertGetId($row);
                $ids[$oldId] = $newId;
                $fresh = Db::name('category')->where('id', $newId)->find();
                Db::name('live_category_migration_map')->insert([
                    'kind' => 'category', 'source_id' => $oldId, 'target_id' => $newId,
                    'payload' => json_encode(['hash' => $this->rowHash($fresh), 'source_hash' => $sourceHash], JSON_THROW_ON_ERROR),
                ]);
            }
            foreach ($plan['rooms'] as $room) {
                $targetId = $ids[(int)$room['category_id']];
                Db::name('live_room')->where('id', $room['id'])->update(['category_id' => $targetId]);
                Db::name('live_category_migration_map')->insert([
                    'kind' => 'room', 'source_id' => (int)$room['id'], 'target_id' => $targetId,
                    'payload' => json_encode(['old_category_id' => (int)$room['category_id']], JSON_THROW_ON_ERROR),
                ]);
            }
            Db::name('live_category_migration_map')->insert([
                'kind' => 'state', 'source_id' => 1, 'target_id' => 0, 'payload' => '{}',
            ]);
            return ['completed' => true, 'category_count' => count($ids), 'room_count' => count($plan['rooms'])];
        });
    }

    /** @return void 回滚前验证新增、改动和引用；存在迁移后编辑时拒绝自动回滚。 */
    public function rollback(): void
    {
        $this->transaction(function (): void {
            $maps = Db::name('live_category_migration_map')->select()->toArray();
            $categoryMaps = array_filter($maps, fn(array $row): bool => $row['kind'] === 'category');
            $roomMaps = array_filter($maps, fn(array $row): bool => $row['kind'] === 'room');
            $categoryIds = array_column($categoryMaps, 'target_id');
            $roomIds = array_column($roomMaps, 'source_id');
            if (Db::name('category')->where('type', 11)->whereNotIn('id', $categoryIds ?: [0])->count()
                || Db::name('live_room')->whereIn('category_id', $categoryIds ?: [0])->whereNotIn('id', $roomIds ?: [0])->count()) {
                throw new RuntimeException('存在迁移后新增分类或直播引用，需人工核对回滚');
            }
            foreach ($categoryMaps as $map) {
                $row = Db::name('category')->where('id', $map['target_id'])->find();
                $payload = json_decode($map['payload'], true, 512, JSON_THROW_ON_ERROR);
                $source = Db::name('category')->where('id', $map['source_id'])->find();
                if (!$row || !$source || $this->rowHash($row) !== $payload['hash'] || $this->rowHash($source) !== $payload['source_hash']) {
                    throw new RuntimeException('迁移后的分类已修改，拒绝自动回滚');
                }
            }
            foreach ($roomMaps as $map) {
                if ((int)Db::name('live_room')->where('id', $map['source_id'])->value('category_id') !== (int)$map['target_id']) {
                    throw new RuntimeException('直播分类引用已修改，拒绝自动回滚');
                }
                $payload = json_decode($map['payload'], true, 512, JSON_THROW_ON_ERROR);
                Db::name('live_room')->where('id', $map['source_id'])->update(['category_id' => $payload['old_category_id']]);
            }
            Db::name('category')->whereIn('id', $categoryIds ?: [0])->delete();
            Db::name('live_category_migration_map')->where('id', '>', 0)->delete();
        });
    }

    /** @return array 已验证复制计划。 */
    private function loadPlan(): array
    {
        $categories = Db::name('category')->whereIn('type', [4, 11])->limit(10001)->select()->toArray();
        $rooms = Db::name('live_room')->field(['id', 'category_id'])->where('category_id', '>', 0)
            ->limit(100001)->select()->toArray();
        if (count($categories) > 10000 || count($rooms) > 100000) {
            throw new RuntimeException('数据超过单次维护迁移上限，请先制定分批迁移方案');
        }
        return app()->make(LiveCategoryMigrationPlan::class, [], true)->build($categories, $rooms);
    }

    /** @param array $row 原始数据库行。 @return string 稳定摘要，不受驱动整数返回类型影响。 */
    private function rowHash(array $row): string
    {
        ksort($row);
        return hash('sha256', json_encode(array_map(fn($value) => $value === null ? null : (string)$value, $row), JSON_THROW_ON_ERROR));
    }
}
