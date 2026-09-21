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
 * 创建用户签到与签到任务领奖记录表。
 *
 * user_signin_record 记录每日签到行为，用于计算连续签到和7天周期。
 * user_signin_task_record 记录每日任务积分领取行为，用于防止同一任务当天重复领奖。
 */
class CreateUserSigninTables extends Migrator
{
    public function change(): void
    {
        $signin = $this->table('user_signin_record', ['comment' => '用户签到记录表', 'collation' => 'utf8mb4_general_ci']);
        $signin->addColumn('user_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '用户ID']);
        $signin->addColumn('sign_date', 'date', ['comment' => '签到日期']);
        $signin->addColumn('reward_points', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '获得积分']);
        $signin->addColumn('streak_days', 'integer', ['limit' => 11, 'default' => 1, 'comment' => '连续签到天数']);
        $signin->addColumn('cycle_day', 'integer', ['limit' => 2, 'default' => 1, 'comment' => '7天周期内第几天']);
        $signin->addColumn('remark', 'string', ['limit' => 255, 'default' => '', 'comment' => '备注']);
        $signin->addTimestamps();
        $signin->addIndex('user_id');
        $signin->addIndex('sign_date');
        // 同一用户同一天只能签到一次。
        $signin->addIndex(['user_id', 'sign_date'], ['name' => 'uk_user_signin_date', 'unique' => true]);
        $signin->create();

        $task = $this->table('user_signin_task_record', ['comment' => '用户签到任务领奖记录表', 'collation' => 'utf8mb4_general_ci']);
        $task->addColumn('user_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '用户ID']);
        $task->addColumn('task_key', 'string', ['limit' => 50, 'default' => '', 'comment' => '任务标识']);
        $task->addColumn('task_date', 'date', ['comment' => '领奖日期']);
        $task->addColumn('reward_points', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '获得积分']);
        $task->addColumn('remark', 'string', ['limit' => 255, 'default' => '', 'comment' => '备注']);
        $task->addTimestamps();
        $task->addIndex('user_id');
        $task->addIndex('task_key');
        $task->addIndex('task_date');
        // 同一用户同一任务每天只能领取一次积分。
        $task->addIndex(['user_id', 'task_key', 'task_date'], ['name' => 'uk_user_signin_task_date', 'unique' => true]);
        $task->create();
    }
}
