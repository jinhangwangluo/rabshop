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
 * 用户表
 * Class User
 * @author 橙猫
 * @email 17349395805@163.com
 * @date 2022/11/11
 */
class CreateUser extends Migrator
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
        $table = $this->table('user', ['id' => 'uid', 'comment' => '用户', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('group_id', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '用户分组']);
        $table->addColumn('nickname', 'string', ['limit' => 50, 'default' => '', 'comment' => '用户昵称']);
        $table->addColumn('remark_nickname', 'string', ['limit' => 50, 'default' => '', 'comment' => '备注用户昵称']);
        $table->addColumn('avatar', 'string', ['limit' => 255, 'default' => '', 'comment' => '头像']);
        $table->addColumn('phone', 'string', ['limit' => 11, 'default' => '', 'comment' => '手机号']);
        $table->addColumn('password', 'string', ['limit' => 200, 'default' => '', 'comment' => '密码']);
        $table->addColumn('last_ip', 'string', ['limit' => 16, 'default' => '', 'comment' => '访问ip']);
        $table->addColumn('remarks', 'string', ['limit' => 255, 'default' => '', 'comment' => '备注']);
        $table->addColumn('sex', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '0=未知,1=男,2=女']);
        $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '0=禁用,1=正常']);
        $table->addColumn('is_comment_ban', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '是否禁止发评论：0-正常，1-禁止']);
        $table->addColumn('is_verify', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '0=需要审核,1=不需要审核']);
           // ========== 原 user_profile 表字段（画像信息合并到用户表）==========
        $table->addColumn('birthday', 'date', ['null' => true, 'default' => null, 'comment' => '生日', 'after' => 'sex']);
        $table->addColumn('age', 'integer', ['limit' => 3, 'default' => 0, 'comment' => '年龄', 'after' => 'birthday']);
        $table->addColumn('education', 'string', ['limit' => 50, 'default' => '', 'comment' => '教育程度', 'after' => 'age']);
        $table->addColumn('occupation', 'string', ['limit' => 50, 'default' => '', 'comment' => '职业', 'after' => 'education']);
        $table->addColumn('interests', 'string', ['limit' => 255, 'default' => '', 'comment' => '兴趣标签', 'after' => 'occupation']);
        $table->addColumn('hobbies', 'string', ['limit' => 255, 'default' => '', 'comment' => '爱好', 'after' => 'interests']);
        $table->addColumn('address', 'string', ['limit' => 255, 'default' => '', 'comment' => '地址', 'after' => 'hobbies']);
        $table->addColumn('bank_holder_name', 'string', ['limit' => 50, 'default' => '', 'comment' => '银行卡持卡人姓名']);
        $table->addColumn('bank_card_no', 'string', ['limit' => 32, 'default' => '', 'comment' => '银行卡号']);
        $table->addColumn('bank_name', 'string', ['limit' => 80, 'default' => '', 'comment' => '开户银行']);
        $table->addColumn('bank_reserved_phone', 'string', ['limit' => 11, 'default' => '', 'comment' => '银行预留手机号']);

        // ========== 分销相关字段 ==========
        // 分销权限：0-无权限，1-有权限
        $table->addColumn('is_distribution', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '分销权限：0-无，1-有', 'after' => 'is_verify']);
        $table->addColumn('distribution_permission', 'integer', ['limit' => 2, 'default' => -1, 'comment' => '分销权限覆盖：-1跟随模式，0禁止，1指定允许', 'after' => 'is_distribution']);
        // 上级分销人ID（推荐人uid）
        $table->addColumn('spread_uid', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '上级分销人UID', 'after' => 'is_distribution']);
        // 分销绑定时间
        $table->addColumn('spread_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '分销绑定时间', 'after' => 'spread_uid']);
        $table->addIndex('spread_uid');
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex('uid');
        $table->addIndex(['uid', 'phone']);
        $table->addIndex('distribution_permission');
        $table->create();
    }
}
