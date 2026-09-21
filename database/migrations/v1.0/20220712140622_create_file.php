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

class CreateFile extends Migrator
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
        $table = $this->table('file', ['comment' => '文件管理表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('name', 'string', ['limit' => 100, 'default' => '', 'comment' => '文件名称']);
        $table->addColumn('real_name', 'string', ['limit' => 255, 'default' => '', 'comment' => '原始文件名']);
        $table->addColumn('path', 'string', ['limit' => 200, 'default' => '', 'comment' => '文件路径']);
        $table->addColumn('file_size', 'string', ['limit' => 30, 'default' => '', 'comment' => '文件大小']);
        $table->addColumn('file_type', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '文件类型:0=图片，1=音频、2=视频、3=其他文件']);
        $table->addColumn('cate_id', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '分类ID']);
        $table->addColumn('merchant_id', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '商户ID，0表示平台文件']);
        $table->addColumn('update_type', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '图片上传类型 1本地 2七牛云 3OSS 4COS ']);
        $table->addTimestamps();
        $table->addIndex(['cate_id', 'update_type']);
        $table->addIndex(['merchant_id', 'cate_id', 'update_type'], ['name' => 'merchant_cate']);
        $table->create();
    }
}
