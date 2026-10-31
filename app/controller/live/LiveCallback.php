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

namespace app\controller\live;

use app\BaseController;
use app\common\enum\LiveConfigKeyEnum;
use app\common\service\live\LiveRoomService;
use think\facade\Log;

/**
 * 直播回调控制器
 *
 * 说明：
 * - 接收阿里云/七牛的流状态回调
 * - 当检测到“流结束”事件时，自动将直播间切换为已结束
 */
class LiveCallback extends BaseController
{
    /**
     * 七牛回调入口
     */
    public function qiniu(): mixed
    {
        return $this->handleCallback('qiniu');
    }

    /**
     * 阿里云回调入口
     */
    public function aliyun(): mixed
    {
        return $this->handleCallback('aliyun');
    }

    /**
     * 统一回调处理
     */
    private function handleCallback(string $provider): mixed
    {
        $rawBody = (string)$this->request->getInput();
        $payload = $this->readPayload($rawBody);

        if (!$this->verifySignature($provider)) {
            Log::warning('[live-callback] token verify failed', [
                'provider' => $provider,
                'payload'  => $payload,
            ]);
            return $this->fail('Token 鉴权失败');
        }

        if (!$this->isStreamOfflineEvent($payload)) {
            return $this->success('事件已忽略');
        }

        $streamName = $this->extractStreamName($payload);
        if ($streamName === '') {
            Log::warning('[live-callback] stream_name missing', [
                'provider' => $provider,
                'payload'  => $payload,
            ]);
            return $this->success('缺少 stream_name，事件已忽略');
        }

        /** @var LiveRoomService $roomService */
        $roomService = app()->make(LiveRoomService::class);
        $room = $roomService->getRoomByStreamName($streamName);
        if (!$room) {
            Log::info('[live-callback] room not found by stream_name', [
                'provider'    => $provider,
                'stream_name' => $streamName,
            ]);
            return $this->success('未匹配到直播间，事件已忽略');
        }

        if ((int)$room->status === LiveRoomService::STATUS_ENDED) {
            return $this->success('直播间已结束，无需重复处理');
        }

        $result = $roomService->switchStatus((int)$room->id, LiveRoomService::STATUS_ENDED);

        Log::info('[live-callback] room auto ended', [
            'provider'    => $provider,
            'room_id'     => (int)$room->id,
            'stream_name' => $streamName,
            'result'      => $result,
        ]);

        return $this->success('直播自动关播成功', $result);
    }

    /**
     * 读取回调数据（兼容 JSON 与表单）
     */
    private function readPayload(string $rawBody): array
    {
        $payload = $this->request->post();
        if (!empty($payload)) {
            return is_array($payload) ? $payload : [];
        }

        if ($rawBody === '') {
            return [];
        }

        $json = json_decode($rawBody, true);
        return is_array($json) ? $json : [];
    }

    /**
     * Token 鉴权校验
     * - 未配置 Token 时放行，便于研发阶段快速接入
     * - 配置 Token 后，校验 URL GET 参数 token 是否与存储值一致
     */
    private function verifySignature(string $provider): bool
    {
        $configToken = (string)db_config(
            $provider === 'qiniu' ? LiveConfigKeyEnum::QINIU_CALLBACK_TOKEN : LiveConfigKeyEnum::ALI_CALLBACK_TOKEN,
            ''
        );
    
        if ($configToken === '') {
            return true;
        }
    
        $requestToken = (string)$this->request->get('token', '');
    
        return $requestToken !== '' && hash_equals($configToken, $requestToken);
    }

    /**
     * 判断是否为“流结束/离线”事件
     */
    private function isStreamOfflineEvent(array $payload): bool
    {
        $event = strtolower((string)($payload['event'] ?? $payload['event_type'] ?? $payload['action'] ?? ''));
        $status = strtolower((string)($payload['status'] ?? $payload['stream_status'] ?? $payload['state'] ?? ''));

        if (in_array($event, ['stream_end', 'stream_stop', 'publish_done', 'disconnect', 'offline'], true)) {
            return true;
        }

        if (in_array($status, ['offline', 'disconnected', 'stopped', 'end', 'ended'], true)) {
            return true;
        }

        // 未识别事件名时，默认按“回调即关播”处理可改为 false，这里保守只识别明确离线事件
        return false;
    }

    /**
     * 从回调数据提取流名称
     */
    private function extractStreamName(array $payload): string
    {
        $candidates = [
            $payload['stream_name'] ?? '',
            $payload['stream'] ?? '',
            $payload['streamName'] ?? '',
            $payload['StreamName'] ?? '',
            $payload['data']['stream_name'] ?? '',
            $payload['data']['stream'] ?? '',
        ];

        foreach ($candidates as $name) {
            $name = trim((string)$name);
            if ($name !== '') {
                return $name;
            }
        }

        return '';
    }
}

