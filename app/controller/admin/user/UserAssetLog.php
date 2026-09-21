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

namespace app\controller\admin\user;

use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\common\service\user\UserAssetLogService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 用户资产记录控制器
 */
class UserAssetLog extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    /**
     * 用户资产记录服务
     */
    #[Inject]
    protected UserAssetLogService $service;

    /**
     * 状态字段
     */
    protected function getStatusField(): string
    {
        return 'id';
    }

    /**
     * 验证类
     */
    protected function getValidateClassName(): string|null
    {
        return null;
    }

    /**
     * 搜索字段（支持来源类型、订单号等佣金溯源字段）
     */
    protected function getIndexFields(): array
    {
        return [
            ['user_id', ''],
            ['type', ''],
            ['change_type', ''],
            ['source_type', ''],
            ['order_no', ''],
            ['source_user_id', ''],
            ['create_time', ''],
        ];
    }

    /**
     * POST 请求获取的字段（资产记录通常不允许新增/编辑）
     */
    protected function getRequestFields(): array
    {
        return [
            ['user_id', 0],
            ['type', ''],
            ['amount', 0],
            ['change_type', ''],
            ['before', 0],
            ['after', 0],
            ['remark', ''],
            ['source_type', ''],
            ['order_no', ''],
            ['source_user_id', 0],
            ['source_desc', ''],
        ];
    }

    /**
     * 表单初始化字段
     */
    protected function getFormFields(): array
    {
        return [];
    }

    /**
     * 获取指定用户的资产记录
     */
    public function userLogs(int $userId): Response
    {
        $params = $this->request->getMore([
            ['type', ''],
            ['change_type', ''],
            ['source_type', ''],
            ['order_no', ''],
            ['create_time', ''],
        ]);

        $result = $this->service->getUserLogs($userId, $params);
        return $this->success($result->toArray());
    }

    /**
     * 获取指定用户的佣金记录
     */
    public function commissionLogs(int $userId): Response
    {
        $params = $this->request->getMore([
            ['change_type', ''],
            ['source_type', ''],
            ['order_no', ''],
            ['create_time', ''],
        ]);

        $result = $this->service->getCommissionLogs($userId, $params);
        return $this->success($result->toArray());
    }
}
