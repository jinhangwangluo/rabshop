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

namespace app\common\service\websocket;

use app\common\model\websocket\WebSocketPushStats;
use app\common\model\websocket\WebSocketMessageHistory;
use app\common\service\BaseService;

/**
 * WebSocket推送统计服务
 */
class WebSocketPushStatsService extends BaseService
{
    /**
     * 设置对应的模型
     */
    protected function setModel(): string
    {
        return WebSocketPushStats::class;
    }

    /**
     * 记录推送统计信息
     * @param array $data
     * @return bool
     */
    public function recordPush(array $data)
    {
        $this->getModel()->save([
            'push_id' => $data['push_id'] ?? $this->generatePushId(),
            'event_type' => $data['event_type'] ?? '',
            'target_type' => $data['target_type'] ?? '',
            'target_id' => $data['target_id'] ?? 0,
            'message_data' => json_encode($data['message_data'] ?? [], JSON_UNESCAPED_UNICODE),
            'recipient_count' => $data['recipient_count'] ?? 0,
            'success_count' => $data['success_count'] ?? 0,
            'failed_count' => $data['failed_count'] ?? 0,
            'success_rate' => $this->calculateSuccessRate(
                $data['success_count'] ?? 0,
                $data['recipient_count'] ?? 0
            ),
            'avg_latency_ms' => $data['avg_latency_ms'] ?? 0,
            'max_latency_ms' => $data['max_latency_ms'] ?? 0,
            'min_latency_ms' => $data['min_latency_ms'] ?? 0,
            'is_important' => $data['is_important'] ?? false,
            'push_time' => date('Y-m-d H:i:s', $data['push_time'] ?? time()),
        ]);


    }

    /**
     * 更新推送结果（异步更新成功/失败数）
     * @param string $pushId
     * @param int $successCount
     * @param int $failedCount
     * @param string|null $errorMessage
     */
    public function updatePushResult(
        string  $pushId,
        int     $successCount,
        int     $failedCount,
        ?string $errorMessage = null
    )
    {

        $stats = $this->$this->getModel()->where('push_id', $pushId)->find();
        if (!$stats) {
            return false;
        }

        $recipientCount = $successCount + $failedCount;
        $stats->success_count = $successCount;
        $stats->failed_count = $failedCount;
        $stats->recipient_count = $recipientCount;
        $stats->success_rate = $this->calculateSuccessRate($successCount, $recipientCount);

        if ($errorMessage) {
            $stats->error_message = $errorMessage;
        }

        $stats->save();

    }

    /**
     * 计算成功率
     * @param int $successCount
     * @param int $totalCount
     * @return float
     */
    protected function calculateSuccessRate(int $successCount, int $totalCount): float
    {
        if ($totalCount === 0) {
            return 0.00;
        }

        return round(($successCount / $totalCount) * 100, 2);
    }

    /**
     * 生成推送 ID
     * @return string
     */
    protected function generatePushId(): string
    {
        return 'PUSH_' . date('YmdHis') . '_' . uniqid('', true);
    }

    /**
     * 获取统计数据
     * @param string $eventType
     * @param int $startTime
     * @param int $endTime
     * @return array
     */
    public function getStatsByEvent(
        string $eventType,
        int    $startTime,
        int    $endTime
    ): array
    {
        $stats = $this->getModel()
            ->where('event_type', $eventType)
            ->where('push_time', '>=', date('Y-m-d H:i:s', $startTime))
            ->where('push_time', '<=', date('Y-m-d H:i:s', $endTime))
            ->select()
            ->toArray();

        if (empty($stats)) {
            return [];
        }

        $total = count($stats);
        $totalRecipients = array_sum(array_column($stats, 'recipient_count'));
        $totalSuccess = array_sum(array_column($stats, 'success_count'));
        $totalFailed = array_sum(array_column($stats, 'failed_count'));
        $avgLatency = round(array_sum(array_column($stats, 'avg_latency_ms')) / $total, 2);

        return [
            'total_pushes' => $total,
            'total_recipients' => $totalRecipients,
            'total_success' => $totalSuccess,
            'total_failed' => $totalFailed,
            'overall_success_rate' => round(($totalSuccess / max($totalRecipients, 1)) * 100, 2),
            'average_latency_ms' => $avgLatency,
            'time_range' => [
                'start' => date('Y-m-d H:i:s', $startTime),
                'end' => date('Y-m-d H:i:s', $endTime),
            ]
        ];
    }
}
