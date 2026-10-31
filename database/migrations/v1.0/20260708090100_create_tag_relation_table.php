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

class CreateTagRelationTable extends Migrator
{
    public function change()
    {
        $table = $this->table('tag_relation', ['comment' => '标签关联', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('tag_id', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '标签ID']);
        $table->addColumn('owner_id', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '所属者ID，0为平台']);
        $table->addColumn('tag_type', 'integer', ['limit' => 2, 'default' => 1, 'comment' => '标签类型：1=内容专题，2=商品']);
        $table->addColumn('relation_type', 'string', ['limit' => 32, 'default' => '', 'comment' => '关联类型：article/special/product']);
        $table->addColumn('relation_id', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '关联业务ID']);
        $table->addTimestamps();
        $table->addIndex(['relation_type', 'relation_id']);
        $table->addIndex(['owner_id', 'tag_type']);
        $table->addIndex(['tag_id']);
        $table->addIndex(['tag_id', 'relation_type', 'relation_id'], ['unique' => true, 'name' => 'uniq_tag_relation']);
        $table->create();
    }
}
