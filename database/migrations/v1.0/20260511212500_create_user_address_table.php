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
 * 用户收货地址表
 */
class CreateUserAddressTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('user_address', ['comment' => '用户收货地址表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('user_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '用户ID']);
        $table->addColumn('real_name', 'string', ['limit' => 64, 'default' => '', 'comment' => '收货人姓名']);
        $table->addColumn('phone', 'string', ['limit' => 20, 'default' => '', 'comment' => '收货人手机号']);
        $table->addColumn('province', 'string', ['limit' => 64, 'default' => '', 'comment' => '收货省']);
        $table->addColumn('city', 'string', ['limit' => 64, 'default' => '', 'comment' => '收货市']);
        $table->addColumn('district', 'string', ['limit' => 64, 'default' => '', 'comment' => '收货区']);
        $table->addColumn('province_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '省级行政区ID']);
        $table->addColumn('city_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '市级行政区ID']);
        $table->addColumn('district_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '区县行政区ID']);
        $table->addColumn('area_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '末级行政区ID']);
        $table->addColumn('latitude', 'decimal', ['precision' => 10, 'scale' => 6, 'default' => '0.000000', 'comment' => '地图纬度']);
        $table->addColumn('longitude', 'decimal', ['precision' => 10, 'scale' => 6, 'default' => '0.000000', 'comment' => '地图经度']);
        $table->addColumn('map_name', 'string', ['limit' => 128, 'default' => '', 'comment' => '地图位置名称']);
        $table->addColumn('detail', 'string', ['limit' => 255, 'default' => '', 'comment' => '详细地址']);
        $table->addColumn('user_address', 'string', ['limit' => 255, 'default' => '', 'comment' => '完整收货地址']);
        $table->addColumn('is_default', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '是否默认地址：0-否，1-是']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex('user_id');
        $table->addIndex('is_default');
        $table->addIndex(['user_id', 'is_default']);
        $table->create();
    }
}
