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

use think\migration\Migrator;

/**
 * 快递公司表（express_company）
 */
class CreateExpressCompanyTable extends Migrator
{
    public function up(): void
    {
        $this->ensureExpressCompanyTable();
        $this->seedExpressCompanies();
        $this->markCommonExpressCompanies();
    }

    public function down(): void
    {
        if ($this->tableExists()) {
            $this->table('express_company')->drop()->save();
        }
    }

    private function ensureExpressCompanyTable(): void
    {
        $table = $this->table('express_company', ['comment' => '快递公司表', 'collation' => 'utf8mb4_general_ci']);
        if (!$this->tableExists()) {
            $table->addColumn('name', 'string', ['limit' => 80, 'default' => '', 'comment' => '快递公司名称']);
            $table->addColumn('code', 'string', ['limit' => 64, 'default' => '', 'comment' => '快递公司编码']);
            $table->addColumn('is_common', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '是否常用：0-否，1-是']);
            $table->addColumn('sort', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '排序（越大越靠前）']);
            $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '状态：0-禁用，1-启用']);
            $table->addTimestamps();
            $table->addSoftDelete();
            $table->addIndex('code', ['name' => 'idx_code']);
            $table->addIndex('is_common', ['name' => 'idx_is_common']);
            $table->addIndex('name', ['name' => 'idx_name']);
            $table->addIndex('status', ['name' => 'idx_status']);
            $table->addIndex('sort', ['name' => 'idx_sort']);
            $table->create();
            return;
        }

        $changed = false;
        foreach ($this->columns() as $name => $definition) {
            if (!$this->columnExists($name)) {
                $table->addColumn($name, $definition['type'], $definition['options']);
                $changed = true;
            }
        }
        foreach ($this->indexes() as $name => $definition) {
            if (!$this->indexExists($name)) {
                $table->addIndex($definition['columns'], ['name' => $name]);
                $changed = true;
            }
        }
        if ($changed) {
            $table->update();
        }
    }

    private function seedExpressCompanies(): void
    {
        $rows = $this->parseExpressFile();
        if ($rows === []) {
            return;
        }

        $table = $this->table('express_company');
        $existingCodes = $this->fetchAll('SELECT `code` FROM `' . $this->tableName() . '`');
        $existingMap = [];
        foreach ($existingCodes as $row) {
            $existingMap[(string)$row['code']] = true;
        }

        $now = date('Y-m-d H:i:s');
        $insert = [];
        $commonCodes = $this->commonExpressCodes();
        foreach ($rows as $index => $row) {
            if (isset($existingMap[$row['code']])) {
                continue;
            }
            $insert[] = [
                'name' => $row['name'],
                'code' => $row['code'],
                'is_common' => isset($commonCodes[$row['code']]) ? 1 : 0,
                'sort' => count($rows) - $index,
                'status' => 1,
                'create_time' => $now,
                'update_time' => $now,
            ];
        }

        foreach (array_chunk($insert, 200) as $chunk) {
            $table->insert($chunk)->saveData();
        }
    }

    private function markCommonExpressCompanies(): void
    {
        $codes = array_map([$this, 'quote'], array_keys($this->commonExpressCodes()));
        if ($codes === []) {
            return;
        }

        $this->execute(
            'UPDATE `' . $this->tableName() . '` SET `is_common` = 1 WHERE `code` IN (' . implode(',', $codes) . ')'
        );
    }

    private function parseExpressFile(): array
    {
        $file = dirname(__DIR__, 3) . '/material/exp.txt';
        if (!is_file($file)) {
            return [];
        }

        $tokens = preg_split('/\s+/u', (string)file_get_contents($file), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $skip = [
            '快递公司列表【陆续更新】' => true,
            '快递公司' => true,
            'type缩写' => true,
        ];
        $rows = [];
        $seen = [];
        $count = count($tokens);
        for ($index = 0; $index < $count - 1; $index++) {
            $name = trim((string)$tokens[$index]);
            $code = trim((string)$tokens[$index + 1]);
            if ($name === '' || $code === '' || isset($skip[$name]) || isset($skip[$code])) {
                continue;
            }
            if (!preg_match('/^[A-Z0-9_]+$/', $code)) {
                continue;
            }
            if (!preg_match('/^[\x{4e00}-\x{9fa5}A-Za-z0-9\s\-_()（）]+$/u', $name)) {
                continue;
            }
            if (isset($seen[$code])) {
                $index++;
                continue;
            }

            $rows[] = ['name' => $name, 'code' => $code];
            $seen[$code] = true;
            $index++;
        }

        return $rows;
    }

    private function commonExpressCodes(): array
    {
        return array_fill_keys([
            'SFEXPRESS',
            'JD',
            'YTO',
            'ZTO',
            'STO',
            'YUNDA',
            'JITU',
            'EMS',
            'CHINAPOST',
            'DEPPON',
            'ANE',
            'ANEEX',
            'ZJS',
        ], true);
    }

    private function columns(): array
    {
        return [
            'name' => ['type' => 'string', 'options' => ['limit' => 80, 'default' => '', 'comment' => '快递公司名称']],
            'code' => ['type' => 'string', 'options' => ['limit' => 64, 'default' => '', 'comment' => '快递公司编码']],
            'is_common' => ['type' => 'boolean', 'options' => ['limit' => 1, 'default' => 0, 'comment' => '是否常用：0-否，1-是']],
            'sort' => ['type' => 'integer', 'options' => ['limit' => 11, 'default' => 0, 'comment' => '排序（越大越靠前）']],
            'status' => ['type' => 'boolean', 'options' => ['limit' => 1, 'default' => 1, 'comment' => '状态：0-禁用，1-启用']],
            'create_time' => ['type' => 'datetime', 'options' => ['null' => true, 'comment' => '创建时间']],
            'update_time' => ['type' => 'datetime', 'options' => ['null' => true, 'comment' => '更新时间']],
            'delete_time' => ['type' => 'datetime', 'options' => ['null' => true, 'comment' => '删除时间']],
        ];
    }

    private function indexes(): array
    {
        return [
            'idx_code' => ['columns' => ['code']],
            'idx_is_common' => ['columns' => ['is_common']],
            'idx_name' => ['columns' => ['name']],
            'idx_status' => ['columns' => ['status']],
            'idx_sort' => ['columns' => ['sort']],
        ];
    }

    private function tableExists(): bool
    {
        return $this->fetchAll("SHOW TABLES LIKE " . $this->quote($this->tableName())) !== [];
    }

    private function columnExists(string $column): bool
    {
        return $this->fetchAll(
            'SHOW COLUMNS FROM `' . $this->tableName() . '` LIKE ' . $this->quote($column)
        ) !== [];
    }

    private function indexExists(string $index): bool
    {
        return $this->fetchAll(
            'SHOW INDEX FROM `' . $this->tableName() . '` WHERE `Key_name` = ' . $this->quote($index)
        ) !== [];
    }

    private function tableName(): string
    {
        return $this->getPrefix() . 'express_company';
    }

    private function getPrefix(): string
    {
        return (string)config('database.connections.' . config('database.default') . '.prefix');
    }

    private function quote(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
