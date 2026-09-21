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

use app\common\model\activity\UserSigninRecord;
use app\common\service\BaseService;

/**
 * 用户签到记录服务。
 *
 * 封装签到记录的日期查询，供 ActivitySigninService 计算今日是否已签和连续签到天数。
 */
class UserSigninRecordService extends BaseService
{
    /**
     * 设置签到记录模型。
     */
    protected function setModel(): string
    {
        return UserSigninRecord::class;
    }

    /**
     * 获取用户指定日期签到记录。
     *
     * @param int $userId 用户ID。
     * @param string $date 签到日期，格式 Y-m-d。
     * @return UserSigninRecord|null 存在则返回签到记录，否则返回 null。
     */
    public function getByDate(int $userId, string $date): ?UserSigninRecord
    {
        if ($userId <= 0 || $date === '') {
            return null;
        }

        return $this->getModel()
            ->where('user_id', $userId)
            ->where('sign_date', $date)
            ->find();
    }

    /**
     * 获取用户最近一次签到记录。
     *
     * @param int $userId 用户ID。
     * @param string $date 查询边界日期，格式 Y-m-d。
     * @return UserSigninRecord|null 返回边界日期之前最近一条签到记录。
     */
    public function getLatestBefore(int $userId, string $date): ?UserSigninRecord
    {
        if ($userId <= 0 || $date === '') {
            return null;
        }

        return $this->getModel()
            ->where('user_id', $userId)
            ->where('sign_date', '<', $date)
            ->order('sign_date', 'desc')
            ->find();
    }
}
