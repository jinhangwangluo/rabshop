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

namespace app\common\job\cs;

use app\common\job\BaseJob;
use app\common\job\Dispatchable;
use app\common\job\ShouldQueue;
use app\common\service\cs\CsKnowledgeVectorizeService;
use think\facade\Log;

/**
 * 段落向量化队列任务
 */
class CsSegmentVectorizeJob extends BaseJob implements ShouldQueue
{
    use Dispatchable;

    /**
     * 执行段落向量化
     */
    public function handle(int $segmentId, int $merchantId = -1): void
    {
        /** @var CsKnowledgeVectorizeService $service */
        $service = app()->make(CsKnowledgeVectorizeService::class);
        $result  = $service->vectorizeSegment($segmentId, $merchantId);

        if (!$result) {
            Log::error('段落向量化队列执行失败', [
                'segment_id'  => $segmentId,
                'merchant_id' => $merchantId,
            ]);
        }
    }
}
