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

namespace Rabbit\service\schedule;

/**
 * 定时任务监听器约定。
 */
interface ScheduleListenerInterface
{
    /**
     * 声明定时任务规则。
     *
     * @return ScheduleItem
     */
    public function schedule(): ScheduleItem;

    /**
     * 执行定时任务。
     *
     * @return void
     */
    public function handle(): void;
}
