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
 * 内容商品关联表
 *
 * 同时承载文章（article）和专题（special）与店铺商品（store_product）的多对多关系。
 * 只保存关联和展示顺序，不保存商品快照，移动端读取时按商品当前状态实时过滤。
 */
class CreateContentProductRelationTable extends Migrator
{
    /**
     * 创建内容商品关联表，已存在时跳过。
     *
     * @return void 无返回值。
     */
    public function up(): void
    {
        if ($this->hasTable('content_product_relation')) {
            return;
        }

        $table = $this->table('content_product_relation', ['comment' => '内容商品关联表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('relation_type', 'string', ['limit' => 16, 'default' => '', 'comment' => '关联类型：article/special']);
        $table->addColumn('relation_id', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '文章ID或专题ID']);
        $table->addColumn('product_id', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '店铺商品ID']);
        $table->addColumn('sort', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '展示顺序']);
        $table->addTimestamps();
        $table->addIndex(['relation_type', 'relation_id'], ['name' => 'idx_relation']);
        $table->addIndex(['product_id'], ['name' => 'idx_product_id']);
        $table->addIndex(['relation_type', 'relation_id', 'product_id'], ['unique' => true, 'name' => 'uniq_content_product']);
        $table->create();
    }

    /**
     * 回滚时删除本迁移创建的内容商品关联表。
     *
     * @return void 无返回值。
     */
    public function down(): void
    {
        if (!$this->hasTable('content_product_relation')) {
            return;
        }

        $this->table('content_product_relation')->drop()->save();
    }
}
