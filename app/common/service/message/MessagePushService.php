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

namespace app\common\service\message;

use AlibabaCloud\SDK\Dysmsapi\V20170525\Dysmsapi;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\SendSmsRequest;
use app\common\exception\AdminResponseException;
use app\common\enum\ConfigKeyEnum;
use app\common\service\BaseService;
use app\common\service\merchant\MerchantAdminService;
use app\common\service\merchant\MerchantService;
use app\common\service\order\OrderGoodsService;
use app\common\service\setting\AdminService;
use app\common\service\user\PlatformAuthService;
use app\common\service\user\UserService;
use app\common\model\Base;
use Darabonba\OpenApi\Models\Config as OpenApiConfig;
use GuzzleHttp\Client;
use Rabbit\service\wechat\MiniProgram;
use Rabbit\service\wechat\OfficialAccount;
use Throwable;

/**
 * 消息推送发送服务。
 */
class MessagePushService extends BaseService
{
    /**
     * 设置模型。发送服务不依赖具体表。
     */
    protected function setModel(): string
    {
        return Base::class;
    }

    /**
     * 发送事件消息。
     */
    public function sendEvent(string $eventKey, array $payload = []): array
    {
        $event = app()->make(MessagePushDefinitionService::class)->getEvent($eventKey);
        if (!$event) {
            return ['event_key' => $eventKey, 'success' => 0, 'failed' => 0, 'skipped' => 0];
        }

        $templates = app()->make(MessagePushTemplateService::class)->getEnabledTemplates($eventKey);
        $summary = ['event_key' => $eventKey, 'success' => 0, 'failed' => 0, 'skipped' => 0];
        foreach ($templates as $template) {
            $result = $this->sendTemplate((array)$template, $event, $payload);
            $summary['success'] += $result['success'];
            $summary['failed'] += $result['failed'];
            $summary['skipped'] += $result['skipped'];
        }

        return $summary;
    }

    /**
     * 测试发送单个模板。
     */
    public function testSend(int $templateId, array $receiver, array $payload = []): array
    {
        $template = app()->make(MessagePushTemplateService::class)->get($templateId);
        if (!$template) {
            throw new AdminResponseException('模板配置不存在');
        }

        $templateData = $template->toArray();
        $definitionService = app()->make(MessagePushDefinitionService::class);
        $event = $definitionService->getEvent((string)$templateData['event_key']);
        if (!$event) {
            throw new AdminResponseException('消息事件不存在');
        }

        $channel = (string)$templateData['channel'];
        $isWebhookChannel = $definitionService->isWebhookChannel($channel);
        $account = $isWebhookChannel
            ? trim((string)($this->normalizeArray($templateData['sync_config'] ?? [])['webhook_url'] ?? ''))
            : $this->resolveManualAccount($channel, $receiver);
        if ($account === '') {
            throw new AdminResponseException($isWebhookChannel
                ? '请填写 Webhook 地址'
                : ($channel === MessagePushDefinitionService::CHANNEL_EMAIL ? '请填写测试邮箱' : '请填写测试接收账号'));
        }

        $recipient = [
            'receiver_type' => 'test',
            'receiver_id' => 0,
            'receiver_account' => $account,
            'payload' => $receiver,
        ];

        $result = $this->sendToRecipient($templateData, $event, $payload, $recipient);
        if (($result['status'] ?? '') !== 'success') {
            throw new AdminResponseException((string)($result['message'] ?? '测试发送失败'));
        }

        return $result;
    }

    /**
     * 发送指定模板。
     */
    protected function sendTemplate(array $template, array $event, array $payload): array
    {
        $payload = $this->enrichOrderPayload($event, $payload);
        $recipients = $this->resolveRecipients($event, (string)$template['channel'], $payload, $template);
        if ($recipients === []) {
            $this->createSkippedLog($template, $event, $payload, '未找到可用接收人');
            return ['success' => 0, 'failed' => 0, 'skipped' => 1];
        }

        $summary = ['success' => 0, 'failed' => 0, 'skipped' => 0];
        foreach ($recipients as $recipient) {
            $result = $this->sendToRecipient($template, $event, $payload, $recipient);
            $summary[$result['status']]++;
        }

        return $summary;
    }

    /**
     * 发送到单个接收人。
     */
    protected function sendToRecipient(array $template, array $event, array $payload, array $recipient): array
    {
        $payload = $this->enrichOrderPayload($event, $payload);
        $renderPayload = array_merge($payload, $recipient['payload'] ?? []);
        $renderedContent = $this->renderLogContent($template, $renderPayload);
        $logService = app()->make(MessagePushLogService::class);
        $log = $logService->createLog([
            'event_key' => (string)$template['event_key'],
            'channel' => (string)$template['channel'],
            'template_id' => (int)($template['id'] ?? 0),
            'receiver_type' => (string)($recipient['receiver_type'] ?? ''),
            'receiver_id' => (int)($recipient['receiver_id'] ?? 0),
            'receiver_account' => (string)($recipient['receiver_account'] ?? ''),
            'relation_type' => (string)($payload['relation_type'] ?? $event['relation_type'] ?? ''),
            'relation_id' => (int)($payload['relation_id'] ?? $payload['order_id'] ?? $payload['id'] ?? 0),
            'rendered_content' => $renderedContent,
            'request_payload' => [],
            'response_payload' => [],
        ]);

        try {
            $requestPayload = $this->buildRequestPayload($template, $recipient, $renderPayload);
            $logService->update((int)$log['id'], ['request_payload' => $requestPayload]);
            $response = $this->sendByChannel($template, $recipient, $renderPayload, $requestPayload);
            $logService->markResult((int)$log['id'], MessagePushLogService::STATUS_SUCCESS, $response);

            return ['status' => 'success', 'log_id' => (int)$log['id']];
        } catch (Throwable $throwable) {
            $errorMessage = $throwable->getMessage();
            $logService->markResult(
                (int)$log['id'],
                MessagePushLogService::STATUS_FAILED,
                [],
                $errorMessage
            );

            return ['status' => 'failed', 'log_id' => (int)$log['id'], 'message' => $errorMessage];
        }
    }

    /**
     * 创建跳过日志。
     */
    protected function createSkippedLog(array $template, array $event, array $payload, string $reason): void
    {
        $renderedContent = $this->renderLogContent($template, $payload);
        $logService = app()->make(MessagePushLogService::class);
        $log = $logService->createLog([
            'event_key' => (string)$template['event_key'],
            'channel' => (string)$template['channel'],
            'template_id' => (int)($template['id'] ?? 0),
            'receiver_type' => (string)($event['receiver_type'] ?? ''),
            'receiver_id' => 0,
            'receiver_account' => '',
            'relation_type' => (string)($payload['relation_type'] ?? $event['relation_type'] ?? ''),
            'relation_id' => (int)($payload['relation_id'] ?? $payload['order_id'] ?? $payload['id'] ?? 0),
            'rendered_content' => $renderedContent,
            'request_payload' => [],
            'response_payload' => [],
        ]);
        $logService->markResult((int)$log['id'], MessagePushLogService::STATUS_SKIPPED, [], $reason);
    }

    /**
     * 渲染发送日志内容。
     */
    protected function renderLogContent(array $template, array $payload): string
    {
        $channel = (string)($template['channel'] ?? '');
        $renderService = app()->make(MessagePushRenderService::class);
        if (in_array($channel, [
            MessagePushDefinitionService::CHANNEL_SMS,
            MessagePushDefinitionService::CHANNEL_EMAIL,
        ], true) || app()->make(MessagePushDefinitionService::class)->isWebhookChannel($channel)) {
            return $renderService->renderText((string)($template['content_template'] ?? ''), $payload);
        }

        if (!in_array($channel, [
            MessagePushDefinitionService::CHANNEL_OFFICIAL_ACCOUNT,
            MessagePushDefinitionService::CHANNEL_MINIAPP,
        ], true)) {
            return '';
        }

        $fieldMap = $renderService->renderFieldMap(
            $this->normalizeArray($template['field_map'] ?? []),
            $payload,
            true
        );
        if ($channel === MessagePushDefinitionService::CHANNEL_MINIAPP) {
            $fieldMap = $renderService->renderMiniappFieldMap(
                $this->normalizeArray($template['field_map'] ?? []),
                $payload
            );
        }
        $items = [];
        foreach ($fieldMap as $field => $item) {
            $value = is_array($item) ? (string)($item['value'] ?? '') : (string)$item;
            $items[] = $field . '：' . $value;
        }

        return implode('；', $items);
    }

    /**
     * 组装请求载荷。
     */
    protected function buildRequestPayload(array $template, array $recipient, array $payload): array
    {
        $channel = (string)$template['channel'];
        $account = (string)($recipient['receiver_account'] ?? '');

        return match ($channel) {
            MessagePushDefinitionService::CHANNEL_SMS => $this->buildSmsRequestPayload($template, $account, $payload),
            MessagePushDefinitionService::CHANNEL_EMAIL => $this->buildEmailRequestPayload($template, $account, $payload),
            MessagePushDefinitionService::CHANNEL_OFFICIAL_ACCOUNT => $this->buildOfficialRequestPayload($template, $account, $payload),
            MessagePushDefinitionService::CHANNEL_MINIAPP => $this->buildMiniappRequestPayload($template, $account, $payload),
            MessagePushDefinitionService::CHANNEL_WECOM_BOT,
            MessagePushDefinitionService::CHANNEL_DINGTALK_BOT,
            MessagePushDefinitionService::CHANNEL_CUSTOM_BOT => $this->buildWebhookRequestPayload($template, $payload),
            default => throw new AdminResponseException('消息渠道不存在'),
        };
    }

    /**
     * 根据渠道发送。
     */
    protected function sendByChannel(array $template, array $recipient, array $payload, array $requestPayload): array
    {
        return match ((string)$template['channel']) {
            MessagePushDefinitionService::CHANNEL_SMS => $this->sendSms($requestPayload),
            MessagePushDefinitionService::CHANNEL_EMAIL => $this->sendEmail($requestPayload),
            MessagePushDefinitionService::CHANNEL_OFFICIAL_ACCOUNT => $this->sendOfficialTemplate($requestPayload),
            MessagePushDefinitionService::CHANNEL_MINIAPP => $this->sendMiniappSubscribe($requestPayload),
            MessagePushDefinitionService::CHANNEL_WECOM_BOT,
            MessagePushDefinitionService::CHANNEL_DINGTALK_BOT,
            MessagePushDefinitionService::CHANNEL_CUSTOM_BOT => $this->sendWebhookBot($requestPayload),
            default => throw new AdminResponseException('消息渠道不存在'),
        };
    }

    /**
     * 短信请求载荷。
     */
    protected function buildSmsRequestPayload(array $template, string $phone, array $payload): array
    {
        $templateCode = trim((string)($template['template_code'] ?? ''));
        if ($templateCode === '') {
            $templateCode = trim((string)db_config(ConfigKeyEnum::SMS_CODE_TEMPLATE_ID, ''));
        }
        if ($phone === '') {
            throw new AdminResponseException('短信接收手机号为空');
        }
        if ($templateCode === '') {
            throw new AdminResponseException('短信模板Code未配置');
        }

        $syncConfig = $this->normalizeArray($template['sync_config'] ?? []);
        $signName = trim((string)($syncConfig['sign_name'] ?? ''));
        if ($signName === '') {
            $signName = trim((string)db_config(ConfigKeyEnum::SMS_SIGN, ''));
        }
        if ($signName === '') {
            throw new AdminResponseException('短信签名未配置');
        }

        return [
            'phone_numbers' => $phone,
            'sign_name' => $signName,
            'template_code' => $templateCode,
            'template_param' => app()->make(MessagePushRenderService::class)->buildSmsTemplateParams(
                (string)($template['content_template'] ?? ''),
                $payload
            ),
        ];
    }

    /**
     * 邮箱请求载荷。
     */
    protected function buildEmailRequestPayload(array $template, string $email, array $payload): array
    {
        $syncConfig = app()->make(MessagePushTemplateService::class)->getEmailConfig($template['sync_config'] ?? []);
        $apiUrl = trim((string)($syncConfig['api_url'] ?? ''));
        $apiKey = trim((string)($syncConfig['api_key'] ?? ''));
        $subjectTemplate = trim((string)($syncConfig['subject_template'] ?? ''));
        $messageTemplate = trim((string)($template['content_template'] ?? ''));
        $templateCode = trim((string)($template['template_code'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new AdminResponseException('邮箱接收地址为空或格式错误');
        }
        if ($apiUrl === '' || !preg_match('/^https?:\/\//i', $apiUrl)) {
            throw new AdminResponseException('邮件接口地址未配置或格式错误');
        }
        if ($apiKey === '') {
            throw new AdminResponseException('邮件 API Key 未配置');
        }
        if ($subjectTemplate === '') {
            $event = app()->make(MessagePushDefinitionService::class)->getEvent((string)($template['event_key'] ?? ''));
            $subjectTemplate = (string)($event['label'] ?? '消息通知');
        }
        if ($messageTemplate === '') {
            throw new AdminResponseException('邮件内容模板不能为空');
        }

        $renderService = app()->make(MessagePushRenderService::class);

        $body = [
            'to_email' => $email,
            'subject' => $renderService->renderText($subjectTemplate, $payload),
            'message' => $renderService->renderText($messageTemplate, $payload),
            'data' => $renderService->buildVariables($payload),
            'from_name' => trim((string)($syncConfig['from_name'] ?? '')),
        ];
        if ($templateCode !== '') {
            $body['template_id'] = $templateCode;
        }

        return [
            'api_url' => $apiUrl,
            'api_key' => $apiKey,
            'body' => $body,
        ];
    }

    /**
     * 公众号模板消息请求载荷。
     */
    protected function buildOfficialRequestPayload(array $template, string $openid, array $payload): array
    {
        $templateId = trim((string)($template['external_template_id'] ?? ''));
        if ($openid === '') {
            throw new AdminResponseException('公众号openid为空');
        }
        if ($templateId === '') {
            throw new AdminResponseException('公众号模板ID未配置');
        }

        $jumpConfig = $this->normalizeArray($template['jump_config'] ?? []);

        return [
            'openid' => $openid,
            'template_id' => $templateId,
            'url' => trim((string)($jumpConfig['url'] ?? '')),
            'data' => app()->make(MessagePushRenderService::class)->renderFieldMap(
                $this->normalizeArray($template['field_map'] ?? []),
                $payload,
                true
            ),
            'mini_appid' => trim((string)($jumpConfig['mini_appid'] ?? '')),
            'mini_page_path' => trim((string)($jumpConfig['mini_page_path'] ?? '')),
        ];
    }

    /**
     * 小程序订阅消息请求载荷。
     */
    protected function buildMiniappRequestPayload(array $template, string $openid, array $payload): array
    {
        $templateId = trim((string)($template['external_template_id'] ?? ''));
        if ($openid === '') {
            throw new AdminResponseException('小程序openid为空');
        }
        if ($templateId === '') {
            throw new AdminResponseException('小程序订阅模板ID未配置');
        }

        return [
            'openid' => $openid,
            'template_id' => $templateId,
            'page' => $this->resolveMiniappPage((string)($template['event_key'] ?? ''), $payload),
            'data' => app()->make(MessagePushRenderService::class)->renderMiniappFieldMap(
                $this->normalizeArray($template['field_map'] ?? []),
                $payload
            ),
        ];
    }

    /**
     * Webhook BOT 请求载荷。
     */
    protected function buildWebhookRequestPayload(array $template, array $payload): array
    {
        $channel = (string)($template['channel'] ?? '');
        $syncConfig = $this->normalizeArray($template['sync_config'] ?? []);
        $webhookUrl = trim((string)($syncConfig['webhook_url'] ?? ''));
        if ($webhookUrl === '' || !preg_match('/^https?:\/\//i', $webhookUrl)) {
            throw new AdminResponseException('Webhook 地址未配置或格式错误');
        }

        $content = app()->make(MessagePushRenderService::class)->renderText((string)($template['content_template'] ?? ''), $payload);
        if ($content === '') {
            throw new AdminResponseException('Webhook 内容模板不能为空');
        }

        return [
            'channel' => $channel,
            'webhook_url' => $this->resolveWebhookUrl($channel, $webhookUrl, (string)($syncConfig['secret'] ?? '')),
            'headers' => $this->buildWebhookHeaders($syncConfig['headers'] ?? []),
            'body' => $this->buildWebhookBody($channel, $content, $payload, (string)($template['event_key'] ?? '')),
            'content' => $content,
        ];
    }

    /**
     * 为订单类消息补齐商品名称，便于模板映射 product_name。
     */
    protected function enrichOrderPayload(array $event, array $payload): array
    {
        if (($event['relation_type'] ?? '') !== 'order') {
            return $payload;
        }

        $renderService = app()->make(MessagePushRenderService::class);
        if ($renderService->resolveProductName($payload) !== '') {
            return $payload;
        }

        $orderId = (int)($payload['order_id'] ?? $payload['relation_id'] ?? $payload['id'] ?? 0);
        if ($orderId <= 0) {
            return $payload;
        }

        $goodsList = app()->make(OrderGoodsService::class)->getByOrderId($orderId);
        if ($goodsList === []) {
            return $payload;
        }

        $productName = $renderService->resolveProductName(['goods' => $goodsList]);
        if ($productName !== '') {
            $payload['product_name'] = $productName;
        }

        return $payload;
    }

    /**
     * 发送短信。
     */
    protected function sendSms(array $payload): array
    {
        $accessKeyId = trim((string)db_config(ConfigKeyEnum::SMS_ACCESS_KEY, ''));
        $accessSecret = trim((string)db_config(ConfigKeyEnum::SMS_SECRET_KEY, ''));
        if ($accessKeyId === '' || $accessSecret === '') {
            throw new AdminResponseException('阿里云短信密钥未配置');
        }

        $client = new Dysmsapi(new OpenApiConfig([
            'accessKeyId' => $accessKeyId,
            'accessKeySecret' => $accessSecret,
            'endpoint' => 'dysmsapi.aliyuncs.com',
        ]));
        $request = new SendSmsRequest([
            'phoneNumbers' => (string)$payload['phone_numbers'],
            'signName' => (string)$payload['sign_name'],
            'templateCode' => (string)$payload['template_code'],
            'templateParam' => json_encode($payload['template_param'] ?? [], JSON_UNESCAPED_UNICODE),
        ]);
        $response = $client->sendSms($request)->toArray();
        $code = (string)($response['body']['Code'] ?? '');
        if ($code !== 'OK') {
            throw new AdminResponseException((string)($response['body']['Message'] ?? '短信发送失败'));
        }

        return $response;
    }

    /**
     * 发送邮箱消息。
     */
    protected function sendEmail(array $payload): array
    {
        $client = new Client([
            'timeout' => 8,
            'http_errors' => false,
        ]);
        $response = $client->post((string)$payload['api_url'], [
            'headers' => [
                'Authorization' => 'Bearer ' . (string)$payload['api_key'],
                'Content-Type' => 'application/json',
            ],
            'json' => (array)($payload['body'] ?? []),
        ]);
        $statusCode = $response->getStatusCode();
        $body = (string)$response->getBody();
        $decodedBody = json_decode($body, true);
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new AdminResponseException('邮件接口返回异常：HTTP ' . $statusCode . ' ' . mb_substr($body, 0, 200));
        }
        if (is_array($decodedBody) && isset($decodedBody['code']) && (int)$decodedBody['code'] !== 200) {
            throw new AdminResponseException((string)($decodedBody['message'] ?? $decodedBody['msg'] ?? '邮件发送失败'));
        }

        return [
            'status_code' => $statusCode,
            'body' => is_array($decodedBody) ? $decodedBody : mb_substr($body, 0, 1000),
        ];
    }

    /**
     * 发送公众号模板消息。
     */
    protected function sendOfficialTemplate(array $payload): array
    {
        return app()->make(OfficialAccount::class)->sendTemplateMessage(
            (string)$payload['openid'],
            (string)$payload['template_id'],
            (string)$payload['url'],
            (array)$payload['data'],
            (string)$payload['mini_appid'],
            (string)$payload['mini_page_path']
        )->getResponse();
    }

    /**
     * 发送小程序订阅消息。
     */
    protected function sendMiniappSubscribe(array $payload): array
    {
        return app()->make(MiniProgram::class)->sendSubscribeMessage(
            (string)$payload['openid'],
            (string)$payload['template_id'],
            (array)$payload['data'],
            (string)$payload['page']
        );
    }

    /**
     * 发送 Webhook BOT 消息。
     */
    protected function sendWebhookBot(array $payload): array
    {
        $client = new Client([
            'timeout' => 8,
            'http_errors' => false,
        ]);
        $response = $client->post((string)$payload['webhook_url'], [
            'headers' => array_merge(['Content-Type' => 'application/json'], (array)($payload['headers'] ?? [])),
            'json' => (array)($payload['body'] ?? []),
        ]);
        $statusCode = $response->getStatusCode();
        $body = (string)$response->getBody();
        if ($statusCode >= 400) {
            throw new AdminResponseException('Webhook 返回异常：HTTP ' . $statusCode . ' ' . mb_substr($body, 0, 200));
        }

        return [
            'status_code' => $statusCode,
            'body' => mb_substr($body, 0, 1000),
        ];
    }

    /**
     * 解析小程序订阅消息固定跳转页。
     */
    protected function resolveMiniappPage(string $eventKey, array $payload): string
    {
        $page = app()->make(MessagePushDefinitionService::class)->getMiniappPage($eventKey);
        $relationId = $this->resolveMiniappRelationId($eventKey, $payload);
        if ($relationId <= 0) {
            return $page;
        }

        $separator = str_contains($page, '?') ? '&' : '?';

        return $page . $separator . 'id=' . rawurlencode((string)$relationId);
    }

    /**
     * 解析固定跳转页业务 ID。
     */
    protected function resolveMiniappRelationId(string $eventKey, array $payload): int
    {
        return match ($eventKey) {
            MessagePushDefinitionService::EVENT_REFUND_SUCCESS,
            MessagePushDefinitionService::EVENT_REFUND_FAILED,
            MessagePushDefinitionService::EVENT_REFUND_APPLY_ADMIN => (int)($payload['relation_id'] ?? $payload['order_id'] ?? $payload['id'] ?? 0),
            MessagePushDefinitionService::EVENT_WITHDRAW_APPROVED,
            MessagePushDefinitionService::EVENT_WITHDRAW_FAILED => 0,
            MessagePushDefinitionService::EVENT_CONTENT_AUDIT => (int)($payload['relation_id'] ?? $payload['content_id'] ?? $payload['id'] ?? 0),
            MessagePushDefinitionService::EVENT_SPECIAL_AUDIT => (int)($payload['relation_id'] ?? $payload['special_id'] ?? $payload['id'] ?? 0),
            MessagePushDefinitionService::EVENT_LIVE_AUDIT => (int)($payload['relation_id'] ?? $payload['live_id'] ?? $payload['id'] ?? 0),
            default => (int)($payload['relation_id'] ?? $payload['id'] ?? 0),
        };
    }

    /**
     * 解析接收人。
     */
    protected function resolveRecipients(array $event, string $channel, array $payload, array $template = []): array
    {
        if (app()->make(MessagePushDefinitionService::class)->isWebhookChannel($channel)) {
            return [$this->resolveWebhookRecipient($event, $payload, $template)];
        }

        return match ((string)($event['receiver_type'] ?? '')) {
            MessagePushDefinitionService::RECEIVER_USER => $this->resolveUserRecipients($channel, $payload)
                ?: $this->resolveMerchantRecipients($channel, $payload),
            MessagePushDefinitionService::RECEIVER_ADMIN => $this->resolveAdminRecipients($channel, (string)$event['key']),
            MessagePushDefinitionService::RECEIVER_MERCHANT => $this->resolveMerchantRecipients($channel, $payload),
            default => [],
        };
    }

    /**
     * 解析 Webhook BOT 接收人。
     */
    protected function resolveWebhookRecipient(array $event, array $payload, array $template): array
    {
        $receiverType = (string)($event['receiver_type'] ?? '');
        $receiverId = match ($receiverType) {
            MessagePushDefinitionService::RECEIVER_USER => (int)($payload['user_id'] ?? $payload['uid'] ?? 0),
            MessagePushDefinitionService::RECEIVER_MERCHANT => (int)($payload['merchant_id'] ?? $payload['mer_id'] ?? 0),
            default => 0,
        };
        $syncConfig = $this->normalizeArray($template['sync_config'] ?? []);

        return [
            'receiver_type' => $receiverType,
            'receiver_id' => $receiverId,
            'receiver_account' => trim((string)($syncConfig['webhook_url'] ?? '')),
            'payload' => [],
        ];
    }

    /**
     * 解析用户接收人。
     */
    protected function resolveUserRecipients(string $channel, array $payload): array
    {
        $userId = (int)($payload['user_id'] ?? $payload['uid'] ?? 0);
        if ($userId <= 0) {
            return [];
        }

        $user = app()->make(UserService::class)->get($userId);
        $userData = $user ? $user->toArray() : [];
        $phone = trim((string)($payload['user_phone'] ?? $userData['phone'] ?? ''));
        $email = trim((string)($payload['user_email'] ?? $payload['email'] ?? $userData['email'] ?? ''));
        $payloadData = [
            'user_id' => $userId,
            'user_nickname' => (string)($payload['user_nickname'] ?? $userData['nickname'] ?? ''),
            'user_phone' => $phone,
            'user_email' => $email,
        ];

        if ($channel === MessagePushDefinitionService::CHANNEL_SMS) {
            return $phone !== '' ? [[
                'receiver_type' => MessagePushDefinitionService::RECEIVER_USER,
                'receiver_id' => $userId,
                'receiver_account' => $phone,
                'payload' => $payloadData,
            ]] : [];
        }

        if ($channel === MessagePushDefinitionService::CHANNEL_EMAIL) {
            return $email !== '' ? [[
                'receiver_type' => MessagePushDefinitionService::RECEIVER_USER,
                'receiver_id' => $userId,
                'receiver_account' => $email,
                'payload' => $payloadData,
            ]] : [];
        }

        $platform = $channel === MessagePushDefinitionService::CHANNEL_OFFICIAL_ACCOUNT ? 'wechat_oa' : 'wechat_mp';
        $auth = app()->make(PlatformAuthService::class)->getByUidAndPlatform($userId, $platform);
        $openid = trim((string)($auth['openid'] ?? ''));

        return $openid !== '' ? [[
            'receiver_type' => MessagePushDefinitionService::RECEIVER_USER,
            'receiver_id' => $userId,
            'receiver_account' => $openid,
            'payload' => $payloadData,
        ]] : [];
    }

    /**
     * 解析管理员接收人。
     */
    protected function resolveAdminRecipients(string $channel, string $eventKey): array
    {
        if (!in_array($channel, [MessagePushDefinitionService::CHANNEL_SMS, MessagePushDefinitionService::CHANNEL_EMAIL], true)) {
            return [];
        }

        $rows = app()->make(AdminService::class)->getMessageNotifyAdminList();
        $recipients = [];
        foreach ($rows as $row) {
            $account = $channel === MessagePushDefinitionService::CHANNEL_EMAIL
                ? trim((string)($row['message_notify_email'] ?? ''))
                : trim((string)($row['message_notify_phone'] ?? ''));
            if ($account === '') {
                continue;
            }
            $recipients[] = [
                'receiver_type' => MessagePushDefinitionService::RECEIVER_ADMIN,
                'receiver_id' => (int)($row['id'] ?? 0),
                'receiver_account' => $account,
                'payload' => [
                    'receiver_name' => (string)($row['real_name'] ?? $row['account'] ?? ''),
                    'receiver_account' => (string)($row['account'] ?? ''),
                    'receiver_email' => (string)($row['message_notify_email'] ?? ''),
                ],
            ];
        }

        return $recipients;
    }

    /**
     * 解析商户接收人。
     */
    protected function resolveMerchantRecipients(string $channel, array $payload): array
    {
        $merchantId = (int)($payload['merchant_id'] ?? $payload['mer_id'] ?? 0);
        if ($merchantId <= 0) {
            return [];
        }

        $merchant = app()->make(MerchantService::class)->get($merchantId);
        $merchantData = $merchant ? $merchant->toArray() : [];
        $phone = trim((string)($payload['merchant_phone'] ?? $merchantData['contact_phone'] ?? $merchantData['mer_phone'] ?? ''));
        $email = trim((string)($payload['merchant_email'] ?? $payload['email'] ?? $merchantData['email'] ?? ''));
        $payloadData = [
            'merchant_id' => $merchantId,
            'merchant_name' => (string)($payload['merchant_name'] ?? $merchantData['mer_name'] ?? ''),
            'merchant_phone' => $phone,
            'merchant_email' => $email,
        ];

        if ($channel === MessagePushDefinitionService::CHANNEL_SMS) {
            return $this->resolveMerchantAdminSmsRecipients($merchantId, $payloadData);
        }

        if ($channel === MessagePushDefinitionService::CHANNEL_EMAIL) {
            return $this->resolveMerchantAdminEmailRecipients($merchantId, $payloadData);
        }

        $accountKey = $channel === MessagePushDefinitionService::CHANNEL_OFFICIAL_ACCOUNT
            ? 'official_openid'
            : 'mini_openid';
        $openid = trim((string)($payload[$accountKey] ?? ''));

        return $openid !== '' ? [[
            'receiver_type' => MessagePushDefinitionService::RECEIVER_MERCHANT,
            'receiver_id' => $merchantId,
            'receiver_account' => $openid,
            'payload' => $payloadData,
        ]] : [];
    }

    /**
     * 解析商户管理员短信接收人。
     */
    protected function resolveMerchantAdminSmsRecipients(int $merchantId, array $payloadData): array
    {
        $rows = app()->make(MerchantAdminService::class)->getMessageNotifyAdminList($merchantId);
        $recipients = [];
        foreach ($rows as $row) {
            $account = trim((string)($row['message_notify_phone'] ?? ''));
            if ($account === '') {
                continue;
            }

            $recipients[] = [
                'receiver_type' => MessagePushDefinitionService::RECEIVER_MERCHANT,
                'receiver_id' => (int)($row['id'] ?? 0),
                'receiver_account' => $account,
                'payload' => array_merge($payloadData, [
                    'receiver_name' => (string)($row['real_name'] ?? $row['account'] ?? ''),
                    'receiver_account' => (string)($row['account'] ?? ''),
                ]),
            ];
        }

        return $recipients;
    }

    /**
     * 解析商户管理员邮箱接收人。
     */
    protected function resolveMerchantAdminEmailRecipients(int $merchantId, array $payloadData): array
    {
        $rows = app()->make(MerchantAdminService::class)->getMessageNotifyAdminList($merchantId);
        $recipients = [];
        foreach ($rows as $row) {
            $account = trim((string)($row['message_notify_email'] ?? ''));
            if ($account === '') {
                continue;
            }

            $recipients[] = [
                'receiver_type' => MessagePushDefinitionService::RECEIVER_MERCHANT,
                'receiver_id' => (int)($row['id'] ?? 0),
                'receiver_account' => $account,
                'payload' => array_merge($payloadData, [
                    'receiver_name' => (string)($row['real_name'] ?? $row['account'] ?? ''),
                    'receiver_account' => (string)($row['account'] ?? ''),
                    'receiver_email' => $account,
                ]),
            ];
        }

        return $recipients;
    }

    /**
     * 解析手动账号。
     */
    protected function resolveManualAccount(string $channel, array $receiver): string
    {
        return match ($channel) {
            MessagePushDefinitionService::CHANNEL_SMS => trim((string)($receiver['phone'] ?? '')),
            MessagePushDefinitionService::CHANNEL_EMAIL => trim((string)($receiver['email'] ?? '')),
            MessagePushDefinitionService::CHANNEL_OFFICIAL_ACCOUNT => trim((string)($receiver['official_openid'] ?? '')),
            MessagePushDefinitionService::CHANNEL_MINIAPP => trim((string)($receiver['mini_openid'] ?? '')),
            default => '',
        };
    }

    /**
     * 解析 Webhook URL，钉钉支持加签。
     */
    protected function resolveWebhookUrl(string $channel, string $webhookUrl, string $secret): string
    {
        if ($channel !== MessagePushDefinitionService::CHANNEL_DINGTALK_BOT || $secret === '') {
            return $webhookUrl;
        }

        $timestamp = (string)intval(microtime(true) * 1000);
        $sign = urlencode(base64_encode(hash_hmac('sha256', $timestamp . "\n" . $secret, $secret, true)));
        $separator = str_contains($webhookUrl, '?') ? '&' : '?';

        return $webhookUrl . $separator . 'timestamp=' . $timestamp . '&sign=' . $sign;
    }

    /**
     * 组装 Webhook 请求头。
     */
    protected function buildWebhookHeaders(mixed $headers): array
    {
        $rows = $this->normalizeArray($headers);
        $result = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $key = trim((string)($row['key'] ?? ''));
            if ($key !== '') {
                $result[$key] = trim((string)($row['value'] ?? ''));
            }
        }

        return $result;
    }

    /**
     * 组装 Webhook 请求体。
     */
    protected function buildWebhookBody(string $channel, string $content, array $payload, string $eventKey): array
    {
        if (in_array($channel, [
            MessagePushDefinitionService::CHANNEL_WECOM_BOT,
            MessagePushDefinitionService::CHANNEL_DINGTALK_BOT,
        ], true)) {
            return [
                'msgtype' => 'text',
                'text' => [
                    'content' => $content,
                ],
            ];
        }

        return [
            'event_key' => $eventKey,
            'content' => $content,
            'payload' => $payload,
        ];
    }

    /**
     * 规范化数组。
     */
    protected function normalizeArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

}
