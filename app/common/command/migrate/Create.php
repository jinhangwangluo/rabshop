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

use think\console\Input;
use think\console\input\Option as InputOption;
use think\console\Output;
use think\migration\command\migrate\Create as BaseCreate;

/**
 * 覆盖 migrate:create：迁移文件创建到版本目录（默认最新版本目录）。
 */
class Create extends BaseCreate
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();
        // 框架 Console 已占用 --version/-V，故使用 --version-dir
        $this->addOption('--version-dir', null, InputOption::VALUE_REQUIRED, '迁移文件所属版本目录，如 v1.1（默认最新版本目录）');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(Input $input, Output $output)
    {
        $creator = new VersionedCreator($this->app);
        $creator->setVersion($input->getOption('version-dir'));

        $className = $input->getArgument('name');

        $path = $creator->create($className);

        $output->writeln('<info>created</info> .' . str_replace(getcwd(), '', realpath($path)));
    }
}
