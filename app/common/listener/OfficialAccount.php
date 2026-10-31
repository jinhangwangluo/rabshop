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

namespace app\common\listener;

use app\common\service\wechat\WechatReplyService;
use Rabbit\service\wechat\message\Message;
use think\facade\Log;

class OfficialAccount
{
    /**
     * 处理公众号回调消息与事件.
     *
     * @param mixed $payload EasyWechat 消息对象或数组。
     * @param \Closure $next 后续处理器。
     * @return mixed
     */
    public function __invoke(mixed $payload, \Closure $next): mixed
    {
        $response = null;

        try {
            $message = $this->normalizePayload($payload);
            $response = $this->dispatchMessage($message);
        } catch (\Throwable $e) {
            Log::error('微信消息服务端消息执行错误', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

        if (!empty($response)) {
            if ($response instanceof Message) {
                return $response->transformToXml([], true);
            }

            return $response;
        }

        return $next($payload);
    }

    /**
     * 将 EasyWechat 消息对象规范化为数组.
     *
     * @param mixed $payload 回调原始消息。
     * @return array
     */
    private function normalizePayload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (is_object($payload) && method_exists($payload, 'toArray')) {
            return $payload->toArray();
        }

        if ($payload instanceof \JsonSerializable) {
            $data = $payload->jsonSerialize();
            return is_array($data) ? $data : [];
        }

        return [];
    }

    /**
     * 根据消息类型分发处理.
     *
     * @param array $message 公众号回调消息。
     * @return mixed
     */
    private function dispatchMessage(array $message): mixed
    {
        $msgType = strtolower((string)($message['MsgType'] ?? ''));

        return match ($msgType) {
            'event' => $this->dispatchEvent($message),
            'text' => $this->replyByKeyword($this->makeReplyService(), (string)($message['Content'] ?? '')),
            'image', 'voice', 'video', 'shortvideo', 'location', 'link' => $this->replyDefault($this->makeReplyService()),
            default => null,
        };
    }

    /**
     * 根据公众号事件类型分发处理.
     *
     * @param array $message 公众号事件消息。
     * @return mixed
     */
    private function dispatchEvent(array $message): mixed
    {
        $event = strtolower((string)($message['Event'] ?? ''));
        $eventKey = $this->normalizeEventKey((string)($message['EventKey'] ?? ''));

        return match ($event) {
            'subscribe' => $this->replySubscribe($this->makeReplyService()),
            'click' => $this->replyByKeyword($this->makeReplyService(), $eventKey),
            'scan' => $this->replyByKeyword($this->makeReplyService(), $eventKey, false),
            'unsubscribe', 'location', 'view' => null,
            default => null,
        };
    }

    /**
     * 获取微信回复服务实例.
     *
     * @return WechatReplyService
     */
    private function makeReplyService(): WechatReplyService
    {
        return app()->make(WechatReplyService::class);
    }

    /**
     * 返回关注回复.
     *
     * @param WechatReplyService $replyService 微信回复服务。
     * @return array|null
     */
    private function replySubscribe(WechatReplyService $replyService): ?array
    {
        $reply = $replyService->getSubscribeReplyContent();

        return $reply ? $replyService->formatReplyForWechat($reply) : null;
    }

    /**
     * 根据关键词返回配置回复.
     *
     * @param WechatReplyService $replyService 微信回复服务。
     * @param string $keyword 关键词。
     * @param bool $fallbackDefault 未匹配时是否返回默认回复。
     * @return array|null
     */
    private function replyByKeyword(
        WechatReplyService $replyService,
        string $keyword,
        bool $fallbackDefault = true
    ): ?array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return $fallbackDefault ? $this->replyDefault($replyService) : null;
        }

        $reply = $replyService->getReplyByKey($keyword);
        if (!$reply) {
            return null;
        }

        if (!$fallbackDefault && (string)($reply['key'] ?? '') === 'default') {
            return null;
        }

        $reply['data'] = $this->normalizeReplyData($reply['data'] ?? []);

        return $replyService->formatReplyForWechat($reply);
    }

    /**
     * 返回默认回复.
     *
     * @param WechatReplyService $replyService 微信回复服务。
     * @return array|null
     */
    private function replyDefault(WechatReplyService $replyService): ?array
    {
        $reply = $replyService->getDefaultReplyContent();

        return $reply ? $replyService->formatReplyForWechat($reply) : null;
    }

    /**
     * 规范化事件 Key，兼容扫码关注的 qrscene_ 前缀.
     *
     * @param string $eventKey 事件 Key。
     * @return string
     */
    private function normalizeEventKey(string $eventKey): string
    {
        $eventKey = trim($eventKey);
        if (str_starts_with($eventKey, 'qrscene_')) {
            return substr($eventKey, 8);
        }

        return $eventKey;
    }

    /**
     * 规范化回复数据，兼容数据库中的 JSON 字符串与数组.
     *
     * @param mixed $data 回复数据。
     * @return array
     */
    private function normalizeReplyData(mixed $data): array
    {
        if (is_array($data)) {
            return $data;
        }

        if (!is_string($data) || trim($data) === '') {
            return [];
        }

        $decoded = json_decode($data, true);

        return is_array($decoded) ? $decoded : [];
    }
}
