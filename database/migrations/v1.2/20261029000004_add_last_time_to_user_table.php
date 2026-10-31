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

use think\migration\Migrator;

/**
 * 给用户表补充最后登录时间字段。
 *
 * 背景：20260917 分销员列表重构（f7029ae7）在 DistributionUserService 查询字段中
 * 引入了 user.last_time，但建表迁移 20220712140709 从未定义过该列，导致后台
 * 分销用户列表接口在任何环境均报 1054 Unknown column 'last_time'。
 * 历史行默认 NULL（未记录），由后续登录逻辑按需写入。
 */
class AddLastTimeToUserTable extends Migrator
{
    public function up(): void
    {
        if (!$this->hasTable('user')) {
            return;
        }

        $table = $this->table('user');
        if ($table->hasColumn('last_time')) {
            return;
        }

        $table->addColumn('last_time', 'timestamp', [
            'null' => true,
            'default' => null,
            'timezone' => false,
            'comment' => '最后登录时间',
            'after' => 'last_ip',
        ])->update();
    }

    public function down(): void
    {
        if (!$this->hasTable('user')) {
            return;
        }

        $table = $this->table('user');
        if (!$table->hasColumn('last_time')) {
            return;
        }

        $table->removeColumn('last_time')->update();
    }
}
