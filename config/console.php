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

// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
use app\common\command\Key;
use app\common\command\InstallRun;
use app\common\command\ScheduleRun;
use app\common\command\SmsCaptchaWarmBackground;

return [
    // 指令定义
    'commands' => [
        Key::class,
        InstallRun::class,
        \app\common\command\Tool::class,
        SmsCaptchaWarmBackground::class,
        ScheduleRun::class,
    ],
];
