<?php

declare(strict_types=1);

namespace app\common\service\live;

use RuntimeException;

/** 分类拆分的纯数据预检；不连接数据库、不依据分类名称猜测历史用途。 */
final class LiveCategoryMigrationPlan
{
    /**
     * 为全新安装生成独立直播字典。升级实例已有 type=11 时保留其实际分类。
     * @param array $categories 已保留的分类种子。 @return array 包含直播独立副本的分类。
     */
    public function seedRows(array $categories): array
    {
        if (array_filter($categories, fn(array $row): bool => (int)$row['type'] === 11)) return $categories;
        $plan = $this->build($categories, []);
        $nextId = max(array_column($categories, 'id') ?: [0]) + 1;
        $map = [];
        foreach ($plan['categories'] as $row) {
            $oldId = (int)$row['id'];
            $map[$oldId] = $nextId++;
            $row['id'] = $map[$oldId];
            $row['type'] = 11;
            $row['pid'] = (int)$row['pid'] > 0 ? $map[(int)$row['pid']] : 0;
            $path = array_map(fn(int $id): int => $map[$id], $plan['paths'][$oldId]);
            $row['path'] = $path === [] ? '' : '/' . implode('/', $path) . '/';
            $categories[] = $row;
        }
        return $categories;
    }

    /** @param array $categories 原始分类行。 @param array $rooms 直播 ID/分类引用。 @return array 按祖先先后排列的复制计划。 */
    public function build(array $categories, array $rooms): array
    {
        $all = array_column($categories, null, 'id');
        $source = array_filter($all, fn(array $row): bool => (int)$row['type'] === 4);
        $paths = [];
        foreach ($source as $id => $row) {
            if ((int)$row['owner_id'] !== 0) {
                throw new RuntimeException('历史专题/直播分类存在非平台归属，需人工核对：' . $id);
            }
            $path = [];
            $cursor = (int)$row['pid'];
            while ($cursor > 0) {
                if ($cursor === (int)$id || in_array($cursor, $path, true) || count($path) >= 64) {
                    throw new RuntimeException('分类父子关系存在循环：' . $id);
                }
                if (!isset($source[$cursor]) || (int)$source[$cursor]['owner_id'] !== (int)$row['owner_id']) {
                    throw new RuntimeException('分类父级不存在、类型或归属不一致：' . $id);
                }
                $path[] = $cursor;
                $cursor = (int)$source[$cursor]['pid'];
            }
            $paths[$id] = array_reverse($path);
            $stored = $row['path'] ?? '';
            if (is_string($stored)) {
                $stored = $stored === '' ? [] : (json_decode($stored, true) ?? explode('/', trim($stored, '/')));
            }
            if (!is_array($stored) || array_map('intval', $stored) !== $paths[$id]) {
                throw new RuntimeException('分类路径与父级关系不一致：' . $id);
            }
        }
        foreach ($rooms as $room) {
            $categoryId = (int)$room['category_id'];
            if ($categoryId > 0 && (!isset($all[$categoryId]) || !in_array((int)$all[$categoryId]['type'], [4, 11], true)
                || (int)$all[$categoryId]['owner_id'] !== 0)) {
                throw new RuntimeException('直播分类引用悬空或类型错误：' . (int)$room['id']);
            }
        }
        uasort($source, fn(array $a, array $b): int =>
            [count($paths[$a['id']]), (int)$a['id']] <=> [count($paths[$b['id']]), (int)$b['id']]);
        return ['categories' => array_values($source), 'paths' => $paths,
            'rooms' => array_values(array_filter($rooms, fn(array $row): bool => isset($source[$row['category_id']]))),
            'category_count' => count($source)];
    }
}
