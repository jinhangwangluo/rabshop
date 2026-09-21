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
 * 用户画像表
 */
class CreateUserProfileTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change()
    {
        $table = $this->table('user_profile', ['comment' => '用户画像表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('user_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '用户ID']);
        $table->addColumn('sex', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '性别：0-未知，1-男，2-女']);
        $table->addColumn('age', 'integer', ['limit' => 3, 'default' => 0, 'comment' => '年龄']);
        $table->addColumn('birthday', 'date', ['null' => true, 'comment' => '生日']);
        $table->addColumn('education', 'string', ['limit' => 50, 'default' => '', 'comment' => '教育程度']);
        $table->addColumn('occupation', 'string', ['limit' => 50, 'default' => '', 'comment' => '职业']);
        $table->addColumn('interests', 'string', ['limit' => 255, 'default' => '', 'comment' => '兴趣标签']);
        $table->addColumn('hobbies', 'string', ['limit' => 255, 'default' => '', 'comment' => '爱好']);
        $table->addColumn('address', 'string', ['limit' => 255, 'default' => '', 'comment' => '地址']);
        $table->addTimestamps();
        $table->addIndex('user_id', ['unique' => true]);
        $table->create();
    }
}
