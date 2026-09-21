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

namespace app\common\command;

use app\common\service\install\InstallService;
use think\console\Command;
use think\console\Input;
use think\console\Output;

/**
 * 后台执行系统安装任务。
 */
class InstallRun extends Command
{
    /**
     * 配置命令。
     */
    protected function configure()
    {
        $this->setName('install:run')
            ->setDescription('执行安装向导创建的后台安装任务');
    }

    /**
     * 执行命令。
     */
    protected function execute(Input $input, Output $output): int
    {
        /** @var InstallService $service */
        $service = app()->make(InstallService::class);
        $result = $service->runPendingInstall();
        if (!$result['passed']) {
            $output->error((string)$result['message']);

            return 1;
        }

        $output->info('安装完成');

        return 0;
    }
}
