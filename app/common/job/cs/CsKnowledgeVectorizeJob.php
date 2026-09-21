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
use app\common\service\cs\CsKnowledgeService;
use think\facade\Log;

/**
 * 知识向量化队列任务
 */
class CsKnowledgeVectorizeJob extends BaseJob implements ShouldQueue
{
    use Dispatchable;

    /**
     * 执行向量化
     */
    public function handle(int $knowledgeId, int $merchantId = -1): void
    {
        /** @var CsKnowledgeService $knowledgeService */
        $knowledgeService = app()->make(CsKnowledgeService::class);
        $result = $knowledgeService->vectorize($knowledgeId, $merchantId);

        if (!$result) {
            Log::error('知识向量化队列执行失败', [
                'knowledge_id' => $knowledgeId,
                'merchant_id' => $merchantId,
            ]);
        }
    }
}
