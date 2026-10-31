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
use app\common\job\store\ProductBatchCollectJob;
use app\common\service\store\ProductCollectService;

/**
 * 商品批量上架队列任务：一条任务 = 一条店铺商品，逐个插入。
 * 上架失败由 Service 落任务状态（不抛出），不做框架级重试。
 */
class ProductBatchPublishJob extends BaseJob implements ShouldQueue
{
    use Dispatchable;

    /**
     * 路由到批量专用队列（与批量采集同队列，复用其消费通道）。
     */
    protected static function queueName(): string
    {
        return ProductBatchCollectJob::QUEUE_NAME;
    }

    /**
     * 执行上架任务。
     */
    public function handle(int $taskId): void
    {
        app()->make(ProductCollectService::class)->processPublish($taskId);
    }
}
