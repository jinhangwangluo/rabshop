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

namespace app\common\service\activity;

use app\common\model\activity\UserSigninTaskRecord;
use app\common\service\BaseService;

/**
 * 用户签到任务领奖记录服务。
 *
 * 封装任务领奖记录查询，供签到页判断任务是否已领取、领取接口防重复提交。
 */
class UserSigninTaskRecordService extends BaseService
{
    /**
     * 设置签到任务领奖记录模型。
     */
    protected function setModel(): string
    {
        return UserSigninTaskRecord::class;
    }

    /**
     * 获取用户指定日期任务领奖记录。
     *
     * @param int $userId 用户ID。
     * @param string $taskKey 任务标识。
     * @param string $date 领奖日期，格式 Y-m-d。
     * @return UserSigninTaskRecord|null 存在则表示该任务当天已领取。
     */
    public function getByTaskDate(int $userId, string $taskKey, string $date): ?UserSigninTaskRecord
    {
        if ($userId <= 0 || $taskKey === '' || $date === '') {
            return null;
        }

        return $this->getModel()
            ->where('user_id', $userId)
            ->where('task_key', $taskKey)
            ->where('task_date', $date)
            ->find();
    }

    /**
     * 获取用户当天已领奖任务映射。
     *
     * @param int $userId 用户ID。
     * @param string $date 领奖日期，格式 Y-m-d。
     * @return array 以 task_key 为键的领奖记录映射。
     */
    public function getClaimedMap(int $userId, string $date): array
    {
        if ($userId <= 0 || $date === '') {
            return [];
        }

        $list = $this->getModel()
            ->where('user_id', $userId)
            ->where('task_date', $date)
            ->select()
            ->toArray();

        $result = [];
        foreach ($list as $item) {
            $taskKey = (string)($item['task_key'] ?? '');
            if ($taskKey !== '') {
                $result[$taskKey] = $item;
            }
        }

        return $result;
    }
}
