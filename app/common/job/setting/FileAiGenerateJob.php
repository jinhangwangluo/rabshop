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

namespace app\common\job\setting;

use app\common\job\BaseJob;
use app\common\job\Dispatchable;
use app\common\job\ShouldQueue;
use app\common\service\setting\FileAiGenerateService;
use think\facade\Log;

/**
 * 文件库 AI 生图队列任务。
 */
class FileAiGenerateJob extends BaseJob implements ShouldQueue
{
    use Dispatchable;

    /**
     * 执行生图任务。
     */
    public function handle(int $taskId): void
    {
        /** @var FileAiGenerateService $service */
        $service = app()->make(FileAiGenerateService::class);
        $result = $service->processTask($taskId);

        if (!$result) {
            Log::error('文件库 AI 生图队列执行失败', [
                'task_id' => $taskId,
            ]);
        }
    }
}
