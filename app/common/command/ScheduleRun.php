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

use Rabbit\service\schedule\ScheduleRunner;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

/**
 * 定时任务执行命令。
 */
class ScheduleRun extends Command
{
    /**
     * 配置命令。
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('schedule:run')
            ->addOption('code', null, Option::VALUE_OPTIONAL, '指定任务编码')
            ->addOption('force', null, Option::VALUE_NONE, '强制执行，忽略到期判断')
            ->setDescription('执行到期定时任务');
    }

    /**
     * 执行命令。
     *
     * @param Input $input 输入。
     * @param Output $output 输出。
     * @return int
     */
    protected function execute(Input $input, Output $output): int
    {
        $code = $input->getOption('code');
        $code = is_string($code) && $code !== '' ? $code : null;
        $force = (bool) $input->getOption('force');

        /** @var ScheduleRunner $runner */
        $runner = app()->make(ScheduleRunner::class);
        $result = $runner->runDue($code, $force);

        $output->writeln(json_encode($result, JSON_UNESCAPED_UNICODE));

        return 0;
    }
}
