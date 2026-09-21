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

use InvalidArgumentException;
use think\migration\Creator;

/**
 * 迁移文件创建器：将新迁移写入版本目录，而非平铺目录。
 */
class VersionedCreator extends Creator
{
    /**
     * 目标版本目录（如 v1.1）；为空时自动取最新版本目录。
     */
    protected ?string $version = null;

    public function setVersion(?string $version): void
    {
        $this->version = $version;
    }

    protected function ensureDirectory()
    {
        $root = $this->app->getRootPath() . 'database' . DIRECTORY_SEPARATOR . 'migrations';

        $path = $root . DIRECTORY_SEPARATOR . $this->resolveVersion($root);

        if (!is_dir($path) && !mkdir($path, 0755, true)) {
            throw new InvalidArgumentException(sprintf('directory "%s" does not exist', $path));
        }

        if (!is_writable($path)) {
            throw new InvalidArgumentException(sprintf('directory "%s" is not writable', $path));
        }

        return $path;
    }

    /**
     * 解析目标版本目录：优先使用指定版本，否则取现有最新版本目录（无则默认 v1.0）。
     */
    protected function resolveVersion(string $root): string
    {
        if (!empty($this->version)) {
            return trim($this->version, '/\\');
        }

        $versions = [];
        foreach (glob($root . DIRECTORY_SEPARATOR . 'v*', GLOB_ONLYDIR) as $dir) {
            $versions[] = basename($dir);
        }

        if (!$versions) {
            return 'v1.0';
        }

        natsort($versions);

        return (string)end($versions);
    }
}
