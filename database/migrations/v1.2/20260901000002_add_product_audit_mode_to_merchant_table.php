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
 * 给商户表增加普通商品审核配置。
 */
class AddProductAuditModeToMerchantTable extends Migrator
{
    public function change(): void
    {
        if (!$this->hasTable('merchant')) {
            return;
        }

        $table = $this->table('merchant');
        if ($table->hasColumn('product_audit_mode')) {
            return;
        }

        $table->addColumn('product_audit_mode', 'integer', [
            'limit' => 1,
            'default' => 1,
            'comment' => '商品审核：1-平台审核，2-免审核',
            'after' => 'mer_class',
        ])->update();
    }
}
