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
use think\console\input\Option as InputOption;
use think\migration\command\migrate\Run as BaseRun;

/**
 * 覆盖 migrate:run：扫描版本子目录下的迁移文件，支持 --version-dir 仅执行指定版本目录。
 */
class Run extends BaseRun
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();
        $this->addOption('--version-dir', null, InputOption::VALUE_REQUIRED, '仅执行指定版本目录的迁移，如 v1.1（默认扫描所有版本目录）');
    }

    /**
     * 迁移扫描路径：指定 --version-dir 时仅指向该版本目录，否则通配所有版本目录。
     */
    protected function getPath()
    {
        $root = $this->app->getRootPath() . 'database' . DIRECTORY_SEPARATOR . 'migrations';

        $versionDir = trim((string)$this->input->getOption('version-dir'), '/\\');
        if ($versionDir === '') {
            return $root . DIRECTORY_SEPARATOR . '*';
        }

        $path = $root . DIRECTORY_SEPARATOR . $versionDir;
        if (!is_dir($path)) {
            throw new InvalidArgumentException(sprintf('版本目录不存在: %s', $path));
        }

        return $path;
    }
}
