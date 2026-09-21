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

use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\common\service\agreement\AgreementService;
use app\controller\admin\AuthController;
use think\annotation\Inject;

/**
 * 后台协议管理控制器
 */
class Agreement extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    #[Inject]
    protected AgreementService $service;

    protected function getStatusField(): string
    {
        return 'status';
    }

    protected function getValidateClassName(): string|null
    {
        return null;
    }

    protected function getIndexFields(): array
    {
        return [
            ['type', ''],
            ['title', ''],
            ['status', ''],
        ];
    }

    protected function getRequestFields(): array
    {
        return [
            ['type', ''],
            ['title', ''],
            ['content', ''],
            ['version', ''],
            ['status', 1],
            ['sort', 0],
        ];
    }

    protected function getFormFields(): array
    {
        return [
            ['type', 'member'],
            ['status', 1],
            ['sort', 0],
        ];
    }

    /**
     * 获取协议配置页数据。
     */
    public function schema()
    {
        return $this->success('获取成功', $this->service->schema());
    }

    /**
     * 保存固定协议内容。
     */
    public function saveContent()
    {
        $data = $this->request->postMore([
            ['type', ''],
        ]);
        // 富文本字段绕过全局输入过滤，保留 HTML
        $data['content'] = (string)$this->request->post('content', '', null);

        if (trim((string)$data['type']) === '') {
            return $this->fail('协议类型不能为空');
        }

        return $this->success('保存成功', $this->service->saveContent((string)$data['type'], (string)$data['content']));
    }

    /**
     * 新增前钩子：富文本字段绕过全局输入过滤，保留 HTML。
     *
     * @param array $data
     * @param int $id
     * @param mixed|null $res
     */
    protected function beforeSave(array &$data, int $id = 0, mixed $res = null): void
    {
        $data['content'] = (string)$this->request->post('content', '', null);
    }

    /**
     * 编辑前钩子：富文本字段绕过全局输入过滤，保留 HTML。
     *
     * @param array $data
     * @param int $id
     * @param mixed|null $res
     */
    protected function beforeUpdate(array &$data, int $id = 0, mixed $res = null): void
    {
        $data['content'] = (string)$this->request->post('content', '', null);
    }
}
