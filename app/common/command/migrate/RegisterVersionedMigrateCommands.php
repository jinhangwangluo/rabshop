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

use think\Console;

/**
 * 版本化迁移命令注册器。
 *
 * think-migration 在自身 Service::boot 阶段通过 Console::starting 注册 migrate 系列命令，
 * 回调按注册顺序执行，后注册的会覆盖同名命令。因此必须在本注册器回调【晚于】think-migration
 * 的 boot 完成之后注册，覆盖才能生效：
 *  - CLI 入口（think 脚本）：应用 initialize() 完成后调用 static::register()；
 *  - HTTP 兜底路径（InstallService 无 proc_open 时 Console::call）：HttpRun 事件触发时调用。
 *
 * 本注册器带进程内去重，保证回调只注册一次。
 */
class RegisterVersionedMigrateCommands
{
    /**
     * 是否已注册。
     */
    protected static bool $registered = false;

    /**
     * 注册版本化迁移命令覆盖。
     */
    public static function register(): void
    {
        if (static::$registered) {
            return;
        }
        static::$registered = true;

        Console::starting(static function (Console $console) {
            $console->addCommand(Run::class);
            $console->addCommand(Rollback::class);
            $console->addCommand(Status::class);
            $console->addCommand(Breakpoint::class);
            $console->addCommand(Create::class);
        });
    }

    /**
     * HttpRun 事件监听入口。
     */
    public function handle(): void
    {
        static::register();
    }
}
