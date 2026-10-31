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

class CreateCategory extends Migrator
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
     *
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change()
    {
        $table = $this->table('category', ['comment' => '分类', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('pid', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '上级id']);
        $table->addColumn('owner_id', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '所属人，0为全部']);
        $table->addColumn('name', 'string', ['limit' => 255, 'default' => '', 'comment' => '分类名称']);
        $table->addColumn('path', 'string', ['limit' => 500, 'default' => '', 'comment' => '分类路径']);
        $table->addColumn('image', 'string', ['limit' => 255, 'default' => '', 'comment' => '分类图片']);
        $table->addColumn('banner_image', 'string', ['limit' => 500, 'default' => '', 'comment' => '分类大图（移动端分类页Banner）']);
        $table->addColumn('description', 'string', ['limit' => 255, 'default' => '', 'comment' => '描述']);
        $table->addColumn('sort', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '排序']);
        $table->addColumn('type', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '分类类型0=文件分类,1=配置分类']);
        $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '0=关闭,1=开启']);
        $table->addColumn('other', 'text', ['comment' => '其他参数']);
        $table->addTimestamps();
        $table->addIndex(['pid', 'type']);
        $table->addIndex('sort');
        $table->create();

        $now = date('Y-m-d H:i:s');
        $rows = [
            ['pid' => 0, 'owner_id' => 0, 'name' => '基础页面', 'path' => '', 'image' => '', 'description' => 'UniApp 主包基础导航页面', 'sort' => 800, 'type' => 2, 'status' => 1, 'other' => '{}', 'create_time' => $now, 'update_time' => $now],
            ['pid' => 0, 'owner_id' => 0, 'name' => 'DIY页面', 'path' => '', 'image' => '', 'description' => 'UniApp DIY 页面', 'sort' => 700, 'type' => 2, 'status' => 1, 'other' => '{}', 'create_time' => $now, 'update_time' => $now],
            ['pid' => 0, 'owner_id' => 0, 'name' => '直播页面', 'path' => '', 'image' => '', 'description' => 'UniApp 直播相关页面', 'sort' => 600, 'type' => 2, 'status' => 1, 'other' => '{}', 'create_time' => $now, 'update_time' => $now],
            ['pid' => 0, 'owner_id' => 0, 'name' => '用户中心', 'path' => '', 'image' => '', 'description' => 'UniApp 用户中心相关页面', 'sort' => 500, 'type' => 2, 'status' => 1, 'other' => '{}', 'create_time' => $now, 'update_time' => $now],
            ['pid' => 0, 'owner_id' => 0, 'name' => '订单交易', 'path' => '', 'image' => '', 'description' => 'UniApp 订单与支付相关页面', 'sort' => 400, 'type' => 2, 'status' => 1, 'other' => '{}', 'create_time' => $now, 'update_time' => $now],
            ['pid' => 0, 'owner_id' => 0, 'name' => '商户页面', 'path' => '', 'image' => '', 'description' => 'UniApp 商户相关页面', 'sort' => 300, 'type' => 2, 'status' => 1, 'other' => '{}', 'create_time' => $now, 'update_time' => $now],
            ['pid' => 0, 'owner_id' => 0, 'name' => '营销活动', 'path' => '', 'image' => '', 'description' => 'UniApp 活动营销页面', 'sort' => 200, 'type' => 2, 'status' => 1, 'other' => '{}', 'create_time' => $now, 'update_time' => $now],
            ['pid' => 0, 'owner_id' => 0, 'name' => '内容页面', 'path' => '', 'image' => '', 'description' => 'UniApp 内容与专题页面', 'sort' => 100, 'type' => 2, 'status' => 1, 'other' => '{}', 'create_time' => $now, 'update_time' => $now],
        ];
        $this->table('category')->insert($rows)->saveData();
    }
}
