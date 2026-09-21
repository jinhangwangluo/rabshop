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
use app\common\model\user\UserAssetLog;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;

/**
 * 用户资产记录服务
 */
class UserAssetLogService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    public const CHANGE_TYPE_IN = 'in';
    public const CHANGE_TYPE_OUT = 'out';

    /**
     * 设置对应的模型
     */
    protected function setModel(): string
    {
        return UserAssetLog::class;
    }

    /**
     * 获取资产记录列表（携带用户及来源用户信息）
     */
    public function getResourceList(array $params = [], array|string $fields = [], array $with = [], string|array|null $sort = null)
    {
        $with = array_merge(['user', 'sourceUser'], $with);
        return $this->getDefaultList($params, $fields ?: ['*'], $with, $sort ?: ['id' => 'desc']);
    }

    /**
     * 读取资产记录详情（携带用户及来源用户信息）
     */
    public function resourceRead(int|array $id)
    {
        $model = $this->get($id, ['*'], ['user', 'sourceUser']);
        if (!$model) {
            throw new AdminResponseException('资产记录不存在');
        }
        return $model->toArray();
    }

    /**
     * 获取某用户的资产记录列表
     */
    public function getUserLogs(int $userId, array $params = [])
    {
        $params['user_id'] = $userId;
        return $this->getDefaultList($params, ['*'], ['user', 'sourceUser'], ['id' => 'desc']);
    }

    /**
     * 获取某用户的佣金记录（type=commission）
     */
    public function getCommissionLogs(int $userId, array $params = [])
    {
        $params['user_id'] = $userId;
        $params['type']    = 'commission';
        return $this->getDefaultList($params, ['*'], ['user', 'sourceUser'], ['id' => 'desc']);
    }

    /**
     * 创建用户资产流水。
     */
    public function createAssetLog(array $data)
    {
        return $this->create($data);
    }

    /**
     * 获取用户指定资产类型的统计数据。
     */
    public function getAssetStatistics(int $userId, string $type): array
    {
        $monthStart = date('Y-m-01 00:00:00');
        $monthEnd = date('Y-m-t 23:59:59');

        return [
            'month_income' => $this->sumUserAssetLogAmount(
                $userId,
                $type,
                self::CHANGE_TYPE_IN,
                $monthStart,
                $monthEnd
            ),
            'month_expense' => $this->sumUserAssetLogAmount(
                $userId,
                $type,
                self::CHANGE_TYPE_OUT,
                $monthStart,
                $monthEnd
            ),
            'total_income' => $this->sumUserAssetLogAmount($userId, $type, self::CHANGE_TYPE_IN),
        ];
    }

    /**
     * 获取移动端用户资产流水分页列表。
     */
    public function getMobileUserLogs(
        int $userId,
        string $type,
        string $changeType = '',
        int $page = 1,
        int $limit = 10
    ): array {
        $page = max(1, $page);
        $limit = max(1, min(50, $limit));

        $where = [
            'user_id' => $userId,
            'type' => $type,
        ];

        if ($this->isValidChangeType($changeType)) {
            $where['change_type'] = $changeType;
        }

        $total = (int) $this->getModel()->where($where)->count();
        $list = $this->getModel()
            ->where($where)
            ->order('id', 'desc')
            ->limit(($page - 1) * $limit, $limit)
            ->select()
            ->toArray();

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    /**
     * 汇总用户资产流水金额。
     */
    private function sumUserAssetLogAmount(
        int $userId,
        string $type,
        string $changeType,
        string $startTime = '',
        string $endTime = ''
    ): float {
        $query = $this->getModel()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->where('change_type', $changeType);

        if ($startTime !== '') {
            $query->where('create_time', '>=', $startTime);
        }

        if ($endTime !== '') {
            $query->where('create_time', '<=', $endTime);
        }

        return (float) $query->sum('amount');
    }

    /**
     * 判断流水变动方向是否合法。
     */
    private function isValidChangeType(string $changeType): bool
    {
        return in_array($changeType, [self::CHANGE_TYPE_IN, self::CHANGE_TYPE_OUT], true);
    }
}
