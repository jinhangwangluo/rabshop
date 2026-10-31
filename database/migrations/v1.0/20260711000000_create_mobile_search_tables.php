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
 * 创建移动端搜索历史与关键词统计表.
 */
class CreateMobileSearchTables extends Migrator
{
    public function change(): void
    {
        $history = $this->table('user_search_history', ['comment' => '用户搜索历史表', 'collation' => 'utf8mb4_general_ci']);
        $history->addColumn('user_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '用户ID']);
        $history->addColumn('keyword', 'string', ['limit' => 80, 'default' => '', 'comment' => '搜索关键词']);
        $history->addColumn('search_count', 'integer', ['limit' => 11, 'default' => 1, 'comment' => '搜索次数']);
        $history->addColumn('last_search_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '最近搜索时间']);
        $history->addTimestamps();
        $history->addIndex('user_id');
        $history->addIndex('keyword');
        $history->addIndex('last_search_time');
        $history->addIndex(['user_id', 'keyword'], ['name' => 'uk_user_search_keyword', 'unique' => true]);
        $history->create();

        $stat = $this->table('search_keyword_stat', ['comment' => '搜索关键词统计表', 'collation' => 'utf8mb4_general_ci']);
        $stat->addColumn('keyword', 'string', ['limit' => 80, 'default' => '', 'comment' => '搜索关键词']);
        $stat->addColumn('search_count', 'integer', ['limit' => 11, 'default' => 1, 'comment' => '累计搜索次数']);
        $stat->addColumn('result_count', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '最近一次结果数量']);
        $stat->addColumn('last_search_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '最近搜索时间']);
        $stat->addTimestamps();
        $stat->addIndex('keyword', ['name' => 'uk_search_keyword', 'unique' => true]);
        $stat->addIndex('search_count');
        $stat->addIndex('last_search_time');
        $stat->create();
    }
}
