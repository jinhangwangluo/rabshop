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

use Rabbit\service\SmsClickCaptchaService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

/**
 * 短信点击验证码背景图预热命令。
 */
class SmsCaptchaWarmBackground extends Command
{
    /**
     * 配置命令。
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('sms:captcha:warm-backgrounds')
            ->addOption('count', null, Option::VALUE_OPTIONAL, '预生成背景图数量', 10)
            ->setDescription('预生成短信点击验证码背景图到本地目录');
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
        $count = (int) $input->getOption('count');

        /** @var SmsClickCaptchaService $service */
        $service = app()->make(SmsClickCaptchaService::class);
        $result = $service->warmBackgroundPool($count);

        $output->writeln('短信点击验证码背景图预热完成');
        $output->writeln(json_encode($result, JSON_UNESCAPED_UNICODE));

        return 0;
    }
}
