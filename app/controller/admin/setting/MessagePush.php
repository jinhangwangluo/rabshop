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

namespace app\controller\admin\setting;

use app\common\service\message\MessagePushDefinitionService;
use app\common\service\message\MessagePushEventManageService;
use app\common\service\message\MessagePushLogService;
use app\common\service\message\MessagePushService;
use app\common\service\message\MessagePushTemplateService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 消息推送配置控制器。
 */
class MessagePush extends AuthController
{
    /**
     * @var MessagePushDefinitionService
     */
    #[Inject]
    protected MessagePushDefinitionService $definitionService;

    /**
     * @var MessagePushEventManageService
     */
    #[Inject]
    protected MessagePushEventManageService $eventManageService;

    /**
     * @var MessagePushTemplateService
     */
    #[Inject]
    protected MessagePushTemplateService $templateService;

    /**
     * @var MessagePushLogService
     */
    #[Inject]
    protected MessagePushLogService $logService;

    /**
     * @var MessagePushService
     */
    #[Inject]
    protected MessagePushService $messagePushService;

    /**
     * 获取事件、渠道、变量选项。
     */
    public function events(): Response
    {
        return $this->success('ok', [
            'events' => array_values($this->definitionService->getEvents()),
            'channels' => array_values($this->definitionService->getChannels()),
            'receiver_types' => array_values($this->definitionService->getReceiverTypes()),
        ]);
    }

    /**
     * 保存自定义消息事件。
     */
    public function saveEvent(): Response
    {
        $data = $this->request->postMore([
            ['id', 0],
            ['event_key', ''],
            ['event_name', ''],
            ['receiver_type', MessagePushDefinitionService::RECEIVER_USER],
            ['relation_type', 'custom'],
            ['variables', []],
            ['status', 1],
            ['sort', 1000],
            ['remark', ''],
        ]);

        return $this->success('保存成功', $this->eventManageService->saveEvent($data));
    }

    /**
     * 获取消息推送配置列表。
     */
    public function index(): Response
    {
        return $this->success('ok', $this->templateService->getList());
    }

    /**
     * 获取事件配置详情。
     */
    public function detail(string $eventKey): Response
    {
        return $this->success('ok', $this->templateService->getDetail($eventKey));
    }

    /**
     * 保存渠道配置。
     */
    public function save(): Response
    {
        $data = $this->request->postMore([
            ['event_key', ''],
            ['channel', ''],
            ['enabled', 0],
            ['content_template', ''],
            ['template_code', ''],
            ['external_template_id', ''],
            ['field_map', []],
            ['jump_config', []],
            ['sync_config', []],
        ]);

        return $this->success('保存成功', $this->templateService->saveConfig($data));
    }

    /**
     * 修改渠道开关。
     */
    public function status(int $id, int $status): Response
    {
        return $this->success('修改成功', $this->templateService->toggleStatus($id, $status));
    }

    /**
     * 按渠道同步全部微信模板。
     */
    public function syncChannel(string $channel): Response
    {
        return $this->success('同步完成', $this->templateService->syncChannelTemplates($channel));
    }

    /**
     * 测试发送。
     */
    public function test(int $id): Response
    {
        $data = $this->request->postMore([
            ['receiver', []],
            ['payload', []],
        ]);

        return $this->success('发送完成', $this->messagePushService->testSend($id, (array)$data['receiver'], (array)$data['payload']));
    }

    /**
     * 获取发送日志。
     */
    public function logs(): Response
    {
        $params = $this->request->getMore([
            ['event_key', ''],
            ['channel', ''],
            ['status', ''],
            ['receiver_type', ''],
            ['page', 1],
            ['limit', 20],
        ]);

        return $this->success('ok', $this->logService->getList($params));
    }

}
