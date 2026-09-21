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
 * 专题表
 */
class CreateSpecialTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change()
    {
        $table = $this->table('special', ['comment' => '专题表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商户ID']);
        $table->addColumn('category_id', 'integer', ['limit'   => 11, 'default' => 0, 'comment' => '专题分类ID']);
        $table->addColumn('title', 'string', ['limit' => 100, 'default' => '', 'comment' => '专题标题']);
        $table->addColumn('keywords', 'string', ['limit' => 100, 'default' => '', 'comment' => '关键词']);
        $table->addColumn('description', 'string', ['limit' => 255, 'default' => '', 'comment' => '描述']);
        $table->addColumn('cover', 'string', ['limit' => 255, 'default' => '', 'comment' => '封面图片']);
        $table->addColumn('sort', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '排序']);
        $table->addColumn('is_hot', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '是否热门：0-否，1-是']);
        $table->addColumn('is_member_only', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '是否会员专属：0否 1是']);
        $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '状态：0-禁用，1-启用']);
        $table->addColumn('audit_status', 'integer', ['limit' => 4, 'null' => false, 'default' => 0, 'comment' => '审核状态：0=待审核 1=已通过 2=已驳回 3=已下架',]);
        $table->addColumn('audit_remark', 'string', ['limit' => 500, 'null' => false, 'default' => '', 'comment' => '审核备注/驳回原因',]);
        $table->addColumn('audit_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '最近一次审核时间',]);
        $table->addColumn('audit_admin_id', 'integer', ['limit' => 11, 'null' => false, 'default' => 0, 'comment' => '最近一次审核管理员ID',]);
        $table->addColumn('special_type', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '专题类型：0-免费，1-付费']);
        $table->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '购买价格']);
        $table->addColumn('original_price', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '原价（划线价）']);
        $table->addColumn('content_count', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '内容数量']);
        $table->addColumn('view_count', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '浏览量']);
        $table->addColumn('buy_count', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '购买数量']);
        $table->addIndex('category_id');
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex('id');
        $table->addIndex('merchant_id');
        $table->addIndex('status');
        $table->addIndex('is_hot');
        $table->addIndex('special_type');
        $table->create();
    }
}
