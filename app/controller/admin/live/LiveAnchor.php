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

namespace app\controller\admin\live;

use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\common\service\live\LiveAnchorService;
use app\controller\admin\AuthController;
use think\annotation\Inject;

/**
 * 主播管理控制器
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
            ['tab', 'all'],
            ['name', ''],
            ['merchant_id', ''],
            ['audit_status', ''],
            ['status', ''],
            ['create_time', '', 'time'],
        ];
    }

    /**
     * 列表（预加载商户，供「所属商户」列展示）
     */
    public function index(): mixed
    {
        $params = $this->request->getMore($this->getIndexFields());

        return $this->success($this->service->getResourceList(
            $params,
            ['*'],
            ['merchant'],
            ['sort' => 'desc', 'id' => 'desc']
        ));
    }

    /**
     * 各 Tab 数量统计
     */
    public function tabStats(): mixed
    {
        return $this->success('ok', $this->service->getAdminTabStats());
    }

    protected function getRequestFields(): array
    {
        return [
            ['merchant_id', 0],
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
     * 审核主播
     */
    public function audit(int $id): mixed
    {
        $auditStatus = (int)$this->request->post('audit_status');
        $remark      = $this->request->post('audit_remark', '');
        $this->service->audit($id, $auditStatus, $remark);
        return $this->success('审核操作成功');
    }

    /**
     * 切换禁播状态
     */
    public function toggleBan(int $id): mixed
    {
        $result = $this->service->toggleBan($id);
        return $this->success($result['msg'], $result);
    }

    /**
     * 记录违规
     */
    public function addViolation(int $id): mixed
    {
        $result = $this->service->addViolation($id);
        return $this->success('违规记录已更新', $result);
    }

    /**
     * 获取可用主播下拉
     */
    public function options(): mixed
    {
        $list = $this->service->getAnchorOptions();
        return $this->success('获取成功', $list);
    }
}
