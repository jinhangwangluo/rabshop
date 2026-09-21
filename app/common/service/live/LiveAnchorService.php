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

namespace app\common\service\live;

use app\common\exception\AdminResponseException;
use app\common\model\live\LiveAnchor;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\message\BackendMessageEventService;
use app\common\service\message\BackendMessageService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 主播服务
 */
class LiveAnchorService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    // 审核状态常量
    const AUDIT_PENDING  = 0; // 待审核
    const AUDIT_PASS     = 1; // 审核通过
    const AUDIT_REJECT   = 2; // 审核驳回

    protected function setModel(): string
    {
        return LiveAnchor::class;
    }

    /**
     * 新增主播。
     */
    public function resourceSave(array $data): mixed
    {
        $data = $this->normalizeCommissionRate($data);
        $data = $this->normalizeAuditData($data);
        $anchor = $this->create($data);
        $this->notifyAuditSubmitted($anchor->toArray());

        return $anchor;
    }

    /**
     * 读取主播详情并校验商户归属。
     *
     * @param int|array $id 主播 ID。
     * @param int $merchantId 商户 ID，0 表示平台管理端不限制商户。
     * @return array 主播详情。
     */
    public function resourceRead(int|array $id, int $merchantId = 0): array
    {
        return $this->getOwnedAnchor((int)$id, $merchantId)->toArray();
    }

    /**
     * 更新主播。
     */
    public function resourceUpdate(int|array $id, array $data, int $merchantId = 0): bool
    {
        $anchorId = (int)$id;
        $current = $this->getOwnedAnchor($anchorId, $merchantId);

        if ($merchantId > 0) {
            $data['merchant_id'] = $merchantId;
        } elseif (!array_key_exists('merchant_id', $data)) {
            $data['merchant_id'] = (int)($current['merchant_id'] ?? 0);
        }
        $data = $this->normalizeCommissionRate($data);
        $data = $this->normalizeAuditData($data);
        $this->update($anchorId, $data);

        $fresh = $this->getOwnedAnchor($anchorId, $merchantId);
        if ($fresh) {
            $this->notifyAuditSubmitted($fresh->toArray());
        }

        return true;
    }

    /**
     * 删除主播并校验商户归属。
     *
     * @param int $id 主播 ID。
     * @param int $merchantId 商户 ID，0 表示平台管理端不限制商户。
     * @return bool 是否删除成功。
     */
    public function resourceDelete(int $id, int $merchantId = 0): bool
    {
        return (bool)$this->getOwnedAnchor($id, $merchantId)->delete();
    }

    /**
     * 更新主播状态并校验商户归属。
     *
     * @param int|array $id 主播 ID。
     * @param array $data 要更新的状态字段。
     * @param int $merchantId 商户 ID，0 表示平台管理端不限制商户。
     * @return bool 是否更新成功。
     */
    public function resourceShowUpdate(int|array $id, array $data, int $merchantId = 0): bool
    {
        $anchorId = (int)$id;
        $this->getOwnedAnchor($anchorId, $merchantId);

        return (bool)$this->update($anchorId, $data);
    }

    /**
     * 审核主播
     * @param int $id
     * @param int $auditStatus 1-通过 2-驳回
     * @param string $remark 驳回原因
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function audit(int $id, int $auditStatus, string $remark = '', int $merchantId = 0): bool
    {
        $anchor = $this->getOwnedAnchor($id, $merchantId);
        if ($anchor->audit_status === self::AUDIT_PASS) {
            throw new AdminResponseException('该主播已审核通过，无需重复审核');
        }
        if (!in_array($auditStatus, [self::AUDIT_PASS, self::AUDIT_REJECT], true)) {
            throw new AdminResponseException('审核状态参数错误');
        }
        if ($auditStatus === self::AUDIT_REJECT && !$remark) {
            throw new AdminResponseException('驳回时必须填写原因');
        }

        $anchor->audit_status = $auditStatus;
        $anchor->audit_remark = $remark;
        // 审核通过自动启用，驳回不改变status
        if ($auditStatus === self::AUDIT_PASS) {
            $anchor->status = 1;
        }
        $anchor->save();
        app()->make(BackendMessageEventService::class)->auditResult(
            BackendMessageService::SUB_TYPE_LIVE_ANCHOR_AUDIT,
            'live_anchor',
            $id,
            (int)($anchor->merchant_id ?? 0),
            $auditStatus,
            $remark,
            (string)($anchor->nickname ?? $anchor->real_name ?? '主播')
        );

        return true;
    }

    /**
     * 切换禁播状态
     * @param int $id
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function toggleBan(int $id, int $merchantId = 0): array
    {
        $anchor = $this->getOwnedAnchor($id, $merchantId);
        $anchor->is_live_banned = $anchor->is_live_banned ? 0 : 1;
        $anchor->save();

        return [
            'is_live_banned' => $anchor->is_live_banned,
            'msg'            => $anchor->is_live_banned ? '已禁播' : '已解除禁播',
        ];
    }

    /**
     * 记录违规
     * @param int $id
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function addViolation(int $id, int $merchantId = 0): array
    {
        $anchor = $this->getOwnedAnchor($id, $merchantId);
        $anchor->violation_count += 1;
        // 累计3次违规永久禁播
        if ($anchor->violation_count >= 3) {
            $anchor->is_live_banned = 1;
            $anchor->status         = 0;
        }
        $anchor->save();

        return [
            'violation_count' => $anchor->violation_count,
            'is_live_banned'  => $anchor->is_live_banned,
        ];
    }

    /**
     * 获取可用主播下拉列表
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getAnchorOptions(int $merchantId = 0): array
    {
        $query = $this->getModel()
            ->where(['status' => 1, 'audit_status' => self::AUDIT_PASS, 'is_live_banned' => 0]);
        if ($merchantId > 0) {
            $query->where('merchant_id', $merchantId);
        }

        return $query
            ->field(['id', 'name', 'avatar'])
            ->order('sort desc, id desc')
            ->select()
            ->toArray();
    }

    /**
     * 获取主播并校验商户归属。
     *
     * @param int $id 主播 ID。
     * @param int $merchantId 商户 ID，0 表示平台管理端不限制商户。
     * @return LiveAnchor 主播模型。
     */
    private function getOwnedAnchor(int $id, int $merchantId = 0): LiveAnchor
    {
        $anchor = $this->get($id);
        if (!$anchor || ($merchantId > 0 && (int)($anchor->merchant_id ?? 0) !== $merchantId)) {
            throw new AdminResponseException('主播不存在或无权操作');
        }

        return $anchor;
    }

    /**
     * 管理端各 Tab 数量统计（与列表 Tab 筛选语义一致）
     * @return array{all:int,platform:int,merchant:int,pending:int,violation:int}
     */
    public function getAdminTabStats(): array
    {
        $model = $this->getModel();

        return [
            'all'       => $model->getDataCount(['tab' => 'all']),
            'platform'  => $model->getDataCount(['tab' => 'platform']),
            'merchant'  => $model->getDataCount(['tab' => 'merchant']),
            'pending'   => $model->getDataCount(['tab' => 'pending']),
            'violation' => $model->getDataCount(['tab' => 'violation']),
        ];
    }

    /**
     * 根据主播归属设置审核状态。
     */
    protected function normalizeAuditData(array $data): array
    {
        if ((int)($data['merchant_id'] ?? 0) > 0) {
            $data['audit_status'] = self::AUDIT_PENDING;
            $data['audit_remark'] = '';
            return $data;
        }

        $data['audit_status'] = self::AUDIT_PASS;
        $data['audit_remark'] = '';
        return $data;
    }

    /**
     * 规范化主播礼物收益抽成比例。
     */
    protected function normalizeCommissionRate(array $data): array
    {
        if (array_key_exists('commission_rate', $data)) {
            $rate = max(0, min(100, (float)$data['commission_rate']));
            $data['commission_rate'] = number_format($rate, 2, '.', '');
        }

        return $data;
    }

    /**
     * 通知平台处理主播审核。
     */
    protected function notifyAuditSubmitted(array $anchor): void
    {
        $merchantId = (int)($anchor['merchant_id'] ?? 0);
        $anchorId = (int)($anchor['id'] ?? 0);
        if ($merchantId <= 0 || $anchorId <= 0) {
            return;
        }

        app()->make(BackendMessageEventService::class)->auditSubmitted(
            BackendMessageService::SUB_TYPE_LIVE_ANCHOR_AUDIT,
            'live_anchor',
            $anchorId,
            $merchantId,
            (string)($anchor['name'] ?? $anchor['real_name'] ?? '主播')
        );
    }
}
