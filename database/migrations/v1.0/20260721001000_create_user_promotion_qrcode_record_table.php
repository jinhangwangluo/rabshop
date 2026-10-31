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
 * 创建用户推广二维码生成记录表。
 */
class CreateUserPromotionQrcodeRecordTable extends Migrator
{
    /**
     * 定义表结构。
     *
     * @return void
     */
    public function change()
    {
        $table = $this->table('user_promotion_qrcode_record', [
            'comment' => '用户推广二维码生成记录表',
            'collation' => 'utf8mb4_general_ci',
            'engine' => 'InnoDB',
            'row_format' => 'DYNAMIC',
        ]);
        $table->addColumn('user_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '用户ID']);
        $table->addColumn('platform', 'string', ['limit' => 32, 'default' => '', 'comment' => '终端：h5/official_account/miniapp']);
        $table->addColumn('promotion_factor', 'string', ['limit' => 64, 'default' => '', 'comment' => '推广因子']);
        $table->addColumn('target_url', 'string', ['limit' => 512, 'default' => '', 'comment' => '二维码目标链接或小程序页面']);
        $table->addColumn('qrcode_url', 'string', ['limit' => 512, 'default' => '', 'comment' => '二维码图片地址']);
        $table->addColumn('extra', 'text', ['null' => true, 'comment' => '扩展数据(JSON)']);
        $table->addColumn('status', 'integer', ['limit' => 1, 'default' => 1, 'comment' => '状态：0无效，1有效']);
        $table->addColumn('last_generate_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '最后生成时间']);
        $table->addTimestamps();
        $table->addIndex(['user_id', 'platform'], ['name' => 'user_platform']);
        $table->addIndex(['user_id', 'platform', 'promotion_factor'], ['name' => 'user_platform_factor']);
        $table->create();
    }
}
