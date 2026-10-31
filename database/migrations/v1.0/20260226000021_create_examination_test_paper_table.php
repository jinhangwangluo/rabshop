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
 * 试卷表
 */
class CreateExaminationTestPaperTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change()
    {
        $table = $this->table('examination_test_paper', ['comment' => '试卷表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('title', 'string', ['limit' => 100, 'default' => '', 'comment' => '试卷标题']);
        $table->addColumn('description', 'string', ['limit' => 255, 'default' => '', 'comment' => '试卷描述']);
        $table->addColumn('total_score', 'integer', ['limit' => 11, 'default' => 100, 'comment' => '总分']);
        $table->addColumn('pass_score', 'integer', ['limit' => 11, 'default' => 60, 'comment' => '及格分数']);
        $table->addColumn('duration', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '考试时长（分钟），0表示无限制']);
        $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '状态：0-禁用，1-启用']);
        $table->addTimestamps();
        $table->addIndex('id');
        $table->addIndex('status');
        $table->create();
    }
}
