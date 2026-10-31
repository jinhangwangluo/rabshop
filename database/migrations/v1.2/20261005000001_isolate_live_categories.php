<?php

declare(strict_types=1);

use app\common\service\live\LiveCategoryMigrationService;
use think\migration\Migrator;

/** 需维护窗口和显式授权；专题原始分类保留，直播使用独立副本。 */
class IsolateLiveCategories extends Migrator
{
    public function up(): void
    {
        $service = app()->make(LiveCategoryMigrationService::class, [], true);
        $service->preview();
        if (!$this->hasTable('live_category_migration_map')) {
            $this->table('live_category_migration_map', ['comment' => '直播分类拆分及回滚映射'])
                ->addColumn('kind', 'string', ['limit' => 16])
                ->addColumn('source_id', 'integer')
                ->addColumn('target_id', 'integer')
                ->addColumn('payload', 'text')
                ->addIndex(['kind', 'source_id'], ['unique' => true])
                ->create();
        }
        $service->migrate();
    }

    public function down(): void
    {
        app()->make(LiveCategoryMigrationService::class, [], true)->rollback();
        $this->table('live_category_migration_map')->drop()->save();
    }
}
