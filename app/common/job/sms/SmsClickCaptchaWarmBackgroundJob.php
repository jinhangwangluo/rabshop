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

namespace app\common\job\sms;

use app\common\job\BaseJob;
use app\common\job\Dispatchable;
use app\common\job\ShouldQueue;
use Rabbit\service\SmsClickCaptchaService;
use think\facade\Log;

/**
 * 短信点击验证码背景图预热任务。
 */
class SmsClickCaptchaWarmBackgroundJob extends BaseJob implements ShouldQueue
{
    use Dispatchable;

    /**
     * 预热背景图目录。
     *
     * @param int $targetCount 目标图片数量。
     * @return void
     */
    public function handle(int $targetCount = 0): void
    {
        /** @var SmsClickCaptchaService $service */
        $service = app()->make(SmsClickCaptchaService::class);
        $result = $service->warmBackgroundPool($targetCount);

        Log::info('短信点击验证码背景图预热完成', $result);
    }
}
