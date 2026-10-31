<?php

declare(strict_types=1);

namespace app\common\service\live;

use InvalidArgumentException;

/** 单条有界 INSERT SELECT：已有独立直播分类的升级实例不会再次插入初始化副本。 */
final class LiveCategorySeedSql
{
    /** @param array $rows type=11 分类种子。 @param string $prefix 安装脚本表前缀。 @return string SQL 或空串。 */
    public function build(array $rows, string $prefix): string
    {
        if ($rows === []) return '';
        if (!preg_match('/^[a-zA-Z0-9_]*$/D', $prefix) || count($rows) > 2000) throw new InvalidArgumentException('分类种子前缀或数量无效');
        $columns = array_keys($rows[0]);
        foreach ($columns as $column) if (!preg_match('/^[a-z_]+$/D', $column)) throw new InvalidArgumentException('种子列无效');
        $selects = [];
        foreach ($rows as $index => $row) {
            if ((int)$row['type'] !== 11) throw new InvalidArgumentException('只接受独立直播分类');
            $values = [];
            foreach ($columns as $column) {
                $value = $row[$column] ?? null;
                $values[] = ($value === null ? 'NULL' : "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], (string)$value) . "'")
                    . ($index === 0 ? ' AS `' . $column . '`' : '');
            }
            $selects[] = 'SELECT ' . implode(', ', $values);
        }
        $table = $prefix . 'category';
        return "-- 独立直播分类初始字典：仅在 type=11 为空时写入，专题原始 ID 保留。\n"
            . "INSERT IGNORE INTO `{$table}` (`" . implode('`, `', $columns) . "`)\nSELECT seed.* FROM (\n"
            . implode("\nUNION ALL\n", $selects) . "\n) AS seed WHERE NOT EXISTS (SELECT 1 FROM `{$table}` WHERE `type` = 11);";
    }
}
