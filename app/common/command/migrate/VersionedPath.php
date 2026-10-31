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

declare(strict_types=1);

namespace app\common\command\migrate;

/**
 * 迁移路径 trait：让 think-migration 命令扫描版本子目录。
 *
 * 原生 think-migration 只扫描 database/migrations 平铺目录（glob 顶层 *.php），
 * 迁移文件按版本分目录后无法被发现。此处将路径改为带版本目录通配符的形式，
 * 使 glob 变为 "database/migrations/任意版本目录/*.php"，即可扫描所有版本子目录。
 */
trait VersionedPath
{
    /**
     * 获取迁移扫描路径（含版本目录通配符）。
     */
    protected function getPath()
    {
        return $this->app->getRootPath() . 'database' . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '*';
    }
}
