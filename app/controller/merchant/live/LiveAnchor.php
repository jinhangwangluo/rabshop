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

namespace app\controller\merchant\live;

use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\common\service\live\LiveAnchorService;
use app\controller\merchant\AuthController;
use think\annotation\Inject;

/**
 * 商户端主播管理控制器
 */
class LiveAnchor extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    #[Inject]
    protected LiveAnchorService $service;

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
            ['name', ''],
            ['audit_status', ''],
            ['status', ''],
            ['create_time', '', 'time'],
        ];
    }

    protected function getRequestFields(): array
    {
        return [
            ['commission_rate', 0],
            ['name', ''],
            ['real_name', ''],
            ['phone', ''],
            ['avatar', ''],
            ['description', ''],
            ['id_card_front', ''],
            ['id_card_back', ''],
            ['direction', ''],
            ['sort', 0],
            ['status', 1],
        ];
    }

    protected function getFormFields(): array
    {
        return [];
    }

    /**
     * 重写 index，商户只看自己的主播
     */
    public function index(): mixed
    {
        $params                = $this->request->getMore($this->getIndexFields());
        $params['merchant_id'] = $this->merchantId;
        return $this->success($this->service->getResourceList($params));
    }

    /**
     * 保存前注入商户 ID
     */
    protected function beforeSave(array &$data, int $id = 0, mixed $res = null): void
    {
        $data['merchant_id'] = $this->merchantId;
    }

    /**
     * 审核主播
     */
    public function audit(int $id): mixed
    {
        $auditStatus = (int)$this->request->post('audit_status');
        $remark      = $this->request->post('audit_remark', '');
        $this->service->audit($id, $auditStatus, (string)$remark, (int)$this->merchantId);
        return $this->success('审核操作成功');
    }

    /**
     * 切换禁播状态
     */
    public function toggleBan(int $id): mixed
    {
        $result = $this->service->toggleBan($id, (int)$this->merchantId);
        return $this->success($result['msg'], $result);
    }

    /**
     * 记录违规
     */
    public function addViolation(int $id): mixed
    {
        $result = $this->service->addViolation($id, (int)$this->merchantId);
        return $this->success('违规记录已更新', $result);
    }

    /**
     * 读取主播详情并校验当前商户归属。
     */
    public function read($id): mixed
    {
        if (!$id) {
            return $this->fail('修改的ID不能为空');
        }

        return $this->success($this->service->resourceRead((int)$id, (int)$this->merchantId));
    }

    /**
     * 修改主播并校验当前商户归属。
     */
    public function update($id): mixed
    {
        if (!$id) {
            return $this->fail('修改的ID不能为空');
        }

        $data = $this->request->postMore($this->getRequestFields());
        $this->validateCheck($data);
        $this->service->resourceUpdate((int)$id, $data, (int)$this->merchantId);
        return $this->success('修改成功');
    }

    /**
     * 修改主播状态并校验当前商户归属。
     */
    public function show($id): mixed
    {
        if (!$id) {
            return $this->fail($this->message['show']['empty']);
        }

        $status = $this->request->get('status');
        if ($status === null) {
            return $this->fail($this->message['show']['status']);
        }

        $this->service->resourceShowUpdate(
            (int)$id,
            [$this->getStatusField() => (int)$status],
            (int)$this->merchantId
        );
        return $this->success($this->message['show']['success']);
    }

    /**
     * 删除主播并校验当前商户归属。
     */
    public function delete($id): mixed
    {
        if (!$id) {
            return $this->fail($this->message['delete']['empty']);
        }

        $this->service->resourceDelete((int)$id, (int)$this->merchantId);
        return $this->success($this->message['delete']['success']);
    }

    /**
     * 获取可用主播下拉（本商户）
     */
    public function options(): mixed
    {
        $list = $this->service->getAnchorOptions((int)$this->merchantId);
        return $this->success('获取成功', $list);
    }
}
