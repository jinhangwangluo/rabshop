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

class CreateTagTable extends Migrator
{
    public function change()
    {
        $table = $this->table('tag', ['comment' => '标签', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('owner_id', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '所属者ID，0为平台']);
        $table->addColumn('tag_type', 'integer', ['limit' => 2, 'default' => 1, 'comment' => '标签类型：1=内容专题，2=商品']);
        $table->addColumn('category_id', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '标签分组ID']);
        $table->addColumn('name', 'string', ['limit' => 80, 'default' => '', 'comment' => '标签名称']);
        $table->addColumn('effect_type', 'integer', ['limit' => 2, 'default' => 1, 'comment' => '展示效果：1=自定义颜色，2=图片']);
        $table->addColumn('text_color', 'string', ['limit' => 32, 'default' => '', 'comment' => '文字颜色']);
        $table->addColumn('bg_color', 'string', ['limit' => 32, 'default' => '', 'comment' => '背景颜色']);
        $table->addColumn('border_color', 'string', ['limit' => 32, 'default' => '', 'comment' => '边框颜色']);
        $table->addColumn('icon', 'string', ['limit' => 255, 'default' => '', 'comment' => '标签图片']);
        $table->addColumn('sort', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '排序']);
        $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '状态：0=关闭，1=开启']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex(['owner_id', 'tag_type']);
        $table->addIndex(['category_id']);
        $table->addIndex(['status']);
        $table->addIndex(['sort']);
        $table->create();
    }
}
