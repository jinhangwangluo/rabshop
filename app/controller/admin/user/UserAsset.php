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
use app\common\service\user\UserAssetService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 用户资产管理控制器
 */
class UserAsset extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    /**
     * 用户资产服务
     */
    #[Inject]
    protected UserAssetService $service;

    /**
     * 状态字段
     */
    protected function getStatusField(): string
    {
        return 'user_id';
    }

    /**
     * 验证类
     */
    protected function getValidateClassName(): string|null
    {
        return null;
    }

    /**
     * 搜索字段
     */
    protected function getIndexFields(): array
    {
        return [
            ['user_id', ''],
            ['balance', ''],
            ['points', ''],
            ['coins', ''],
            ['exp', ''],
        ];
    }

    /**
     * POST请求获取的字段（新增/编辑）
     */
    protected function getRequestFields(): array
    {
        return [
            ['user_id', 0],
            ['balance', 0],
            ['points', 0],
            ['coins', 0],
            ['exp', 0],
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
     * 变更用户资产
     * @return Response
     */
    public function changeAsset(): Response
    {
        $data = $this->request->postMore([
            ['user_id', 0],
            ['type', ''],
            ['amount', 0],
            ['change_type', 'in'],
            ['remark', ''],
        ]);

        if (empty($data['user_id'])) {
            return $this->fail('请指定用户');
        }

        if (!in_array($data['type'], ['balance', 'points', 'coins', 'exp'])) {
            return $this->fail('资产类型错误');
        }

        if ($data['amount'] <= 0) {
            return $this->fail('变动金额必须大于0');
        }

        if (!in_array($data['change_type'], ['in', 'out'])) {
            return $this->fail('变动类型错误');
        }

        $this->service->changeAsset(
            (int)$data['user_id'],
            $data['type'],
            (float)$data['amount'],
            $data['change_type'],
            $data['remark']
        );

        return $this->success('资产变更成功');
    }

    /**
     * 获取用户资产详情（通过用户ID）
     * @param int $userId
     * @return Response
     */
    public function getByUser(int $userId): Response
    {
        $result = $this->service->getInfo($userId);
        return $this->success($result->toArray());
    }
}
