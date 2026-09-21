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

namespace app\common\migration;

trait SeedSqlTrait
{
    /**
     * 执行种子 SQL 文件。
     */
    protected function executeSeedSqlFile(string $file, string $prefix): void
    {
        if (!is_file($file)) {
            return;
        }

        $sql = file_get_contents($file);
        if (!is_string($sql) || trim($sql) === '') {
            return;
        }

        $sql = str_replace(['`ll_', '`lf_'], '`' . $prefix, $sql);
        foreach ($this->splitSqlStatements($sql) as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                $this->execute($statement);
            }
        }
    }

    /**
     * 将 SQL 拆分为单条语句。
     */
    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $length = strlen($sql);
        $state = 'code';
        $escaped = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($state === 'line') {
                if ($char === "\n") {
                    $state = 'code';
                }
                $current .= $char;
                continue;
            }

            if ($state === 'block') {
                if ($char === '*' && $next === '/') {
                    $state = 'code';
                    $current .= '*/';
                    $i++;
                    continue;
                }
                $current .= $char;
                continue;
            }

            if ($state === 'code') {
                if ($char === '-' && $next === '-') {
                    $state = 'line';
                    $current .= '--';
                    $i++;
                    continue;
                }
                if ($char === '#') {
                    $state = 'line';
                    $current .= $char;
                    continue;
                }
                if ($char === '/' && $next === '*') {
                    $state = 'block';
                    $current .= '/*';
                    $i++;
                    continue;
                }
                if ($char === "'" || $char === '"' || $char === '`') {
                    $state = $char;
                    $current .= $char;
                    continue;
                }
                if ($char === ';') {
                    $statement = trim($current);
                    if ($statement !== '') {
                        $statements[] = $statement;
                    }
                    $current = '';
                    continue;
                }
                $current .= $char;
                continue;
            }

            if ($escaped) {
                $current .= $char;
                $escaped = false;
                continue;
            }
            if ($char === '\\') {
                $current .= $char;
                $escaped = true;
                continue;
            }
            if ($char === $state) {
                if ($next === $state) {
                    $current .= $state . $state;
                    $i++;
                    continue;
                }
                $state = 'code';
            }
            $current .= $char;
        }

        if (trim($current) !== '') {
            $statements[] = trim($current);
        }

        return $statements;
    }
}
