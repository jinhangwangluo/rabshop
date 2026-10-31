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
 * 练习/考试记录表
 */
class CreateExaminationPracticeTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change()
    {
        $table = $this->table('examination_practice', ['comment' => '练习/考试记录表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('user_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '用户ID']);
        $table->addColumn('examination_test_paper_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '试卷ID']);
        $table->addColumn('score', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '得分']);
        $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '状态：0-未完成，1-已完成，2-已过期']);
        $table->addColumn('start_time', 'datetime', ['comment' => '开始时间']);
        $table->addColumn('end_time', 'datetime', ['null' => true, 'comment' => '结束时间']);
        $table->addTimestamps();
        $table->addIndex('id');
        $table->addIndex('user_id');
        $table->addIndex('examination_test_paper_id');
        $table->addIndex('status');
        $table->create();
    }
}
