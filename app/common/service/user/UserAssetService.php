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

namespace app\common\service\user;

use app\common\exception\AdminResponseException;
use app\common\model\user\User;
use app\common\model\user\UserAsset;
use app\common\model\user\UserAssetLog;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;

/**
 * 用户资产服务
 */
class UserAssetService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    public const TYPE_BALANCE = 'balance';
    public const TYPE_POINTS = 'points';
    public const TYPE_COINS = 'coins';
    public const TYPE_EXP = 'exp';
    public const TYPE_COMMISSION = 'commission';

    /**
     * 设置对应的模型
     */
    protected function setModel(): string
    {
        return UserAsset::class;
    }

    /**
     * 获取用户资产详情（不存在时自动初始化）
     */
    public function getInfo(int $userId)
    {
        $asset = $this->get(['user_id' => $userId]);
        if (!$asset) {
            $asset = $this->create([
                'user_id' => $userId,
                'balance' => 0,
                'points'  => 0,
                'coins'   => 0,
                'exp'     => 0,
                'commission' => 0,
            ]);
        }
        return $asset;
    }

    /**
     * 获取单项用户资产，不加载整条资产记录。
     *
     * @param int $userId 用户 ID。
     * @param string $type 资产类型。
     * @return float|int
     */
    public function getAssetValue(int $userId, string $type): float|int
    {
        $this->assertAssetType($type);
        $value = $this->getModel()->where('user_id', $userId)->value($type);
        if ($value === null) {
            $this->create([
                'user_id' => $userId,
                'balance' => 0,
                'points' => 0,
                'coins' => 0,
                'exp' => 0,
                'commission' => 0,
            ]);

            return $this->isIntegerAssetType($type) ? 0 : 0.0;
        }

        return $this->isIntegerAssetType($type) ? (int)$value : (float)$value;
    }

    /**
     * 读取资产详情（携带用户信息）
     */
    public function resourceRead(int|array $id)
    {
        $model = $this->get($id, ['*'], ['user']);
        if (!$model) {
            throw new AdminResponseException('资产记录不存在');
        }
        return $model->toArray();
    }

    /**
     * 变更用户资产（使用框架事务方法）
     *
     * @param int $userId 用户 ID
     * @param string $type 资产类型：balance|points|coins|exp|commission
     * @param float|string $amount 变动金额
     * @param string $changeType 变动方向：in|out
     * @param string $remark 备注
     * @param array $extra 扩展字段：source_type, order_no, source_user_id, source_desc
     */
    public function changeAsset(int $userId, string $type, float|string $amount, string $changeType, string $remark, array $extra = []): bool
    {
        return $this->transaction(function () use ($userId, $type, $amount, $changeType, $remark, $extra) {
            $this->assertAssetType($type);
            $asset = $this->get(['user_id' => $userId], ['id', 'user_id', $type]);
            if (!$asset) {
                $asset = $this->create([
                    'user_id'    => $userId,
                    'balance'    => 0,
                    'points'     => 0,
                    'coins'      => 0,
                    'exp'        => 0,
                    'commission' => 0
                ]);
            }

            // 使用 bcmath 高精度计算（金额不能直接加减）
            $amount = $this->normalizeChangeAmount($type, $amount);
            $before = $this->normalizeBeforeAmount($type, $asset[$type] ?? 0);
            $scale = $this->isIntegerAssetType($type) ? 0 : 2;

            if ($changeType === 'in') {
                $after = bcadd($before, $amount, $scale);
            } else {
                $after = bcsub($before, $amount, $scale);
            }

            // 检查余额是否充足
            if (bccomp($after, '0', $scale) < 0) {
                throw new AdminResponseException('用户' . $type . '资产不足');
            }

            $this->update(['user_id' => $userId], [$type => $after]);

            // 记录资产日志（含佣金来源字段）
            app()->make(UserAssetLogService::class)->createAssetLog([
                'user_id'        => $userId,
                'type'           => $type,
                'amount'         => $amount,
                'change_type'    => $changeType,
                'before'         => $before,
                'after'          => $after,
                'remark'         => $remark,
                'source_type'    => $extra['source_type'] ?? '',
                'order_no'       => $extra['order_no'] ?? '',
                'source_user_id' => (int)($extra['source_user_id'] ?? 0),
                'source_desc'    => $extra['source_desc'] ?? '',
            ]);

            return true;
        });
    }

    /**
     * 发放分销佣金（当下级下单时调用）
     *
     * @param int $userId 获得佣金的上级用户UID
     * @param float $commission 佣金金额
     * @param int $sourceUserId 产生佣金的下级用户UID
     * @param string $orderNo 下级订单号
     * @param string $sourceDesc 来源描述
     */
    public function addCommission(int $userId, float $commission, int $sourceUserId, string $orderNo, string $sourceDesc = ''): bool
    {
        return $this->changeAsset(
            $userId,
            'commission',
            $commission,
            'in',
            $sourceDesc ?: '下级下单获得佣金',
            [
                'source_type'    => 'order_commission',
                'order_no'       => $orderNo,
                'source_user_id' => $sourceUserId,
                'source_desc'    => $sourceDesc ?: '下级用户(uid:' . $sourceUserId . ')下单获得佣金，订单号：' . $orderNo,
            ]
        );
    }

    /**
     * 获取资产列表（携带用户信息）
     */
    public function getResourceList(array $params = [], array|string $fields = [], array $with = [], string|array|null $sort = null)
    {
        $with = array_merge(['user'], $with);
        return $this->getDefaultList($params, $fields ?: ['*'], $with, $sort);
    }

    /**
     * 获取移动端用户资产统计。
     */
    public function getMobileStatistics(int $userId, string $type = self::TYPE_BALANCE): array
    {
        $type = $this->normalizeAssetType($type);
        $asset = $this->getInfo($userId);
        $statistics = app()->make(UserAssetLogService::class)->getAssetStatistics($userId, $type);

        if ($type === self::TYPE_POINTS) {
            return [
                'points' => (int) ($asset ? ($asset['points'] ?? 0) : 0),
                'month_income' => (int) $statistics['month_income'],
                'month_expense' => (int) $statistics['month_expense'],
                'total_income' => (int) $statistics['total_income'],
            ];
        }

        if ($type === self::TYPE_COINS) {
            return [
                'coins' => (int) ($asset ? ($asset['coins'] ?? 0) : 0),
                'coin_balance' => (int) ($asset ? ($asset['coins'] ?? 0) : 0),
                'month_income' => (int) $statistics['month_income'],
                'month_expense' => (int) $statistics['month_expense'],
                'total_income' => (int) $statistics['total_income'],
            ];
        }

        return [
            'balance' => (float) ($asset ? ($asset['balance'] ?? 0) : 0),
            'month_recharge' => (float) $statistics['month_income'],
            'month_consume' => (float) $statistics['month_expense'],
            'total_income' => (float) $statistics['total_income'],
        ];
    }

    /**
     * 获取移动端用户资产流水。
     */
    public function getMobileLogs(
        int $userId,
        string $type = self::TYPE_BALANCE,
        string $changeType = '',
        int $page = 1,
        int $limit = 10
    ): array {
        return app()->make(UserAssetLogService::class)->getMobileUserLogs(
            $userId,
            $this->normalizeAssetType($type),
            $changeType,
            $page,
            $limit
        );
    }

    /**
     * 标准化移动端资产类型，默认保持余额接口兼容。
     */
    public function normalizeAssetType(string $type): string
    {
        return in_array($type, [self::TYPE_BALANCE, self::TYPE_POINTS, self::TYPE_COINS], true) ? $type : self::TYPE_BALANCE;
    }

    /**
     * 校验资产类型是否存在。
     */
    protected function assertAssetType(string $type): void
    {
        if (!in_array($type, [
            self::TYPE_BALANCE,
            self::TYPE_POINTS,
            self::TYPE_COINS,
            self::TYPE_EXP,
            self::TYPE_COMMISSION,
        ], true)) {
            throw new AdminResponseException('资产类型错误');
        }
    }

    /**
     * 积分、金币、经验值只能按整数变动。
     */
    protected function normalizeChangeAmount(string $type, float|string $amount): string
    {
        $amountText = trim((string)$amount);
        if ($amountText === '' || !is_numeric($amountText) || bccomp($amountText, '0', 4) <= 0) {
            throw new AdminResponseException('变动金额必须大于0');
        }

        if (!$this->isIntegerAssetType($type)) {
            return (string)number_format((float)$amountText, 2, '.', '');
        }

        if (!preg_match('/^\d+$/', $amountText)) {
            throw new AdminResponseException($this->getAssetTypeName($type) . '必须为整数');
        }

        return (string)(int)$amountText;
    }

    /**
     * 整数资产按整数参与计算，金额资产保留两位。
     */
    protected function normalizeBeforeAmount(string $type, mixed $amount): string
    {
        if ($this->isIntegerAssetType($type)) {
            return (string)(int)$amount;
        }

        return (string)number_format((float)$amount, 2, '.', '');
    }

    protected function isIntegerAssetType(string $type): bool
    {
        return in_array($type, [self::TYPE_POINTS, self::TYPE_COINS, self::TYPE_EXP], true);
    }

    protected function getAssetTypeName(string $type): string
    {
        return match ($type) {
            self::TYPE_POINTS => '积分',
            self::TYPE_COINS => '金币',
            self::TYPE_EXP => '经验值',
            default => '资产',
        };
    }
}
