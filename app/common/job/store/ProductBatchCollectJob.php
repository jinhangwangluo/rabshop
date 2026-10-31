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

namespace app\common\job\store;

use app\common\job\BaseJob;
use app\common\job\Dispatchable;
use app\common\job\ShouldQueue;
use app\common\service\store\ProductCollectService;

/**
 * 商品批量采集队列任务：一条任务对应一次采集，插入逻辑与单个采集一致。
 * 采集业务失败由 Service 落任务状态（不抛出），不做框架级重试。
 */
class ProductBatchCollectJob extends BaseJob implements ShouldQueue
{
    use Dispatchable;

    /**
     * 批量任务专用队列名：单次导入可达上千条，与默认队列（manage）隔离，
     * 避免长时间占用普通任务消费通道；消费端在 config/swoole.php queue.workers 配置。
     */
    public const QUEUE_NAME = 'product_batch';

    /**
     * 路由到批量专用队列。
     */
    protected static function queueName(): string
    {
        return self::QUEUE_NAME;
    }

    /**
     * 执行采集任务。
     */
    public function handle(int $taskId): void
    {
        app()->make(ProductCollectService::class)->processTask($taskId);
    }
}
