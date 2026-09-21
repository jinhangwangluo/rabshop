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
 * 主播表
 */
class CreateLiveAnchorTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change()
    {
        $table = $this->table('live_anchor', ['comment' => '主播表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商户ID']);
        $table->addColumn('commission_rate', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => '0.00', 'comment' => '商户对主播礼物收益抽成比例(%)']);
        $table->addColumn('name', 'string', ['limit' => 30, 'default' => '', 'comment' => '主播姓名']);
        $table->addColumn('avatar', 'string', ['limit' => 255, 'default' => '', 'comment' => '主播头像']);
        $table->addColumn('description', 'string', ['limit' => 255, 'default' => '', 'comment' => '主播描述']);
        $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '状态：0-禁用，1-启用']);
        $table->addColumn('real_name', 'string', ['limit' => 30, 'default' => '', 'comment' => '真实姓名']);
        $table->addColumn('phone', 'string', ['limit' => 20, 'default' => '', 'comment' => '联系方式']);
        $table->addColumn('id_card_front', 'string', ['limit' => 500, 'default' => '', 'comment' => '身份证正面照片']);
        $table->addColumn('id_card_back', 'string', ['limit' => 500, 'default' => '', 'comment' => '身份证背面照片']);
        $table->addColumn('direction', 'string', ['limit' => 200, 'default' => '', 'comment' => '直播方向说明']);
        $table->addColumn('audit_status', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '审核状态：0-待审核，1-审核通过，2-审核驳回']);
        $table->addColumn('audit_remark', 'string', ['limit' => 500, 'default' => '', 'comment' => '审核备注（驳回原因）']);
        $table->addColumn('violation_count', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '违规次数']);
        $table->addColumn('is_live_banned', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '是否禁播：0-正常，1-禁播']);
        $table->addColumn('sort', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '排序']);
        $table->addSoftDelete();
        $table->addTimestamps();
        $table->addIndex('id');
        $table->addIndex('merchant_id');
        $table->addIndex('status');
        $table->create();
    }
}
