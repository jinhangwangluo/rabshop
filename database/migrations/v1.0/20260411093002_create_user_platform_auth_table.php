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
use think\migration\db\Column;

class CreateUserPlatformAuthTable extends Migrator
{
    public function change()
    {
        $table = $this->table('user_platform_auth', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('uid', 'integer', ['limit' => 11, 'null' => false, 'comment' => '用户ID'])
            ->addColumn('platform', 'string', ['limit' => 20, 'null' => false, 'comment' => '平台类型: wechat_oa, wechat_mp, qq, apple'])
            ->addColumn('openid', 'string', ['limit' => 64, 'null' => false, 'comment' => '平台用户唯一标识'])
            ->addColumn('unionid', 'string', ['limit' => 64, 'null' => true, 'comment' => '跨应用统一标识'])
            ->addColumn('nickname', 'string', ['limit' => 100, 'null' => true, 'comment' => '昵称'])
            ->addColumn('avatar', 'string', ['limit' => 500, 'null' => true, 'comment' => '头像'])
            ->addColumn('extra_data', 'json', ['null' => true, 'comment' => '扩展数据'])
            ->addColumn('bind_time', 'datetime', ['null' => true, 'comment' => '绑定时间'])
            ->addColumn('update_time', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addIndex(['uid'], ['name' => 'idx_uid'])
            ->addIndex(['platform', 'openid'], ['name' => 'uk_platform_openid', 'unique' => true])
            ->addIndex(['unionid'], ['name' => 'idx_unionid'])
            ->create();
    }
}
