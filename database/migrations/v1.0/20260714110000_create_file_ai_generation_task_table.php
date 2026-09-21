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

declare(strict_types=1);

use think\migration\Migrator;

/**
 * 创建文件库 AI 生图任务表。
 */
class CreateFileAiGenerationTaskTable extends Migrator
{
    /**
     * 执行迁移。
     */
    public function change(): void
    {
        $table = $this->table('file_ai_generation_task', [
            'comment' => '文件库AI生图任务表',
            'collation' => 'utf8mb4_general_ci',
        ]);

        $table->addColumn('title', 'string', ['limit' => 100, 'default' => '', 'comment' => '图片主题']);
        $table->addColumn('prompt', 'text', ['null' => true, 'comment' => '提示词']);
        $table->addColumn('negative_prompt', 'text', ['null' => true, 'comment' => '负面提示词']);
        $table->addColumn('style', 'string', ['limit' => 50, 'default' => '', 'comment' => '风格']);
        $table->addColumn('size', 'string', ['limit' => 30, 'default' => '1024x1024', 'comment' => '图片尺寸']);
        $table->addColumn('ratio', 'string', ['limit' => 20, 'default' => '1:1', 'comment' => '比例']);
        $table->addColumn('count', 'integer', ['limit' => 2, 'default' => 4, 'comment' => '生成数量']);
        $table->addColumn('quality', 'string', ['limit' => 20, 'default' => 'auto', 'comment' => '质量参数']);
        $table->addColumn('output_format', 'string', ['limit' => 20, 'default' => 'png', 'comment' => '输出格式']);
        $table->addColumn('output_compression', 'integer', ['limit' => 3, 'default' => 100, 'comment' => '输出压缩率']);
        $table->addColumn('seed', 'string', ['limit' => 64, 'default' => '', 'comment' => '随机种子']);
        $table->addColumn('random_seed', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '是否随机种子']);
        $table->addColumn('high_quality', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '是否高清模式']);
        $table->addColumn('reference_file_id', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '参考图文件ID']);
        $table->addColumn('model', 'string', ['limit' => 100, 'default' => '', 'comment' => '实际模型']);
        $table->addColumn('provider', 'string', ['limit' => 50, 'default' => '', 'comment' => '服务商驱动']);
        $table->addColumn('params', 'text', ['null' => true, 'comment' => '任务参数JSON']);
        $table->addColumn('status', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '状态：0待处理，1处理中，2成功，3失败，4已取消']);
        $table->addColumn('progress', 'integer', ['limit' => 3, 'default' => 0, 'comment' => '进度百分比']);
        $table->addColumn('file_ids', 'text', ['null' => true, 'comment' => '生成文件ID JSON']);
        $table->addColumn('result_images', 'text', ['null' => true, 'comment' => '生成图片结果JSON']);
        $table->addColumn('fail_reason', 'text', ['null' => true, 'comment' => '失败原因']);
        $table->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商户ID，0为平台']);
        $table->addColumn('admin_id', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '创建管理员ID']);
        $table->addColumn('started_time', 'datetime', ['null' => true, 'comment' => '开始时间']);
        $table->addColumn('finished_time', 'datetime', ['null' => true, 'comment' => '结束时间']);
        $table->addTimestamps();
        $table->addIndex(['status', 'create_time'], ['name' => 'idx_status_create_time']);
        $table->addIndex('reference_file_id');
        $table->addIndex('merchant_id', ['name' => 'idx_merchant_id']);
        $table->addIndex(['merchant_id', 'status', 'create_time'], ['name' => 'idx_merchant_status_create_time']);
        $table->create();
    }
}
