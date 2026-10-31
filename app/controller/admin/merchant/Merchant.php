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

namespace app\controller\admin\merchant;

use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\controller\admin\AuthController;
use app\common\service\merchant\MerchantService;
use think\annotation\Inject;
use think\Response;
use think\response\Json;

/**
 * 商户管理
 * Class Merchant
 * @package app\controller\admin\merchant
 */
class Merchant extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    /**
     * @var MerchantService
     */
    #[Inject]
    protected MerchantService $service;

    /**
     * 状态字段
     * @return string
     */
    protected function getStatusField(): string
    {
        return 'status';
    }

    /**
     * 验证类
     * @return string|null
     */
    protected function getValidateClassName(): string|null
    {
        return \app\validate\merchant\MerchantValidate::class;
    }

    /**
     * 搜索字段
     * @return array[]
     */
    protected function getIndexFields(): array
    {
        return [
            ['mer_name', ''],
            ['status', ''],
            ['audit_status', ''],
            ['business_status', ''],
            ['is_recommend', ''],
            ['create_time', ''],
        ];
    }

    /**
     * post请求获取的字段
     * @return array
     */
    protected function getRequestFields(): array
    {
        return [
            ['mer_name', ''],
            ['mer_phone', ''],
            ['mer_address', ''],
            ['contact_name', ''],
            ['contact_phone', ''],
            ['logo', ''],
            ['mer_avatar', ''],
            ['mer_banner', ''],
            ['mer_street_view', ''],
            ['description', ''],
            ['mer_notice', ''],
            ['mer_keyword', ''],
            ['business_hours', []],
            ['business_status', 1],
            ['commission_rate', 0],
            ['settlement_cycle', 7],
            ['qualification', []],
            ['lat', 0],
            ['lng', 0],
            ['mer_class', 2],
            ['product_audit_mode', 1],
            ['live_audit_mode', 1],
            ['live_product_audit_mode', 1],
            ['content_audit_mode', 1],
            ['sort', 0],
            ['is_recommend', 0],
            ['status', 1],
            ['account', ''],
            ['password', ''],
        ];
    }

    /**
     * 获取表单的前置字段
     * @return array
     */
    protected function getFormFields(): array
    {
        return [];
    }

    /**
     * 启用/禁用商户。
     * @param mixed $id
     * @return Response
     */
    public function show($id): Response
    {
        if (!$id) {
            return $this->fail('修改的ID不能为空');
        }

        $status = $this->request->param('status');
        if (!in_array((string)$status, ['0', '1'], true)) {
            return $this->fail('修改状态值不存在');
        }

        $this->service->resourceShowUpdate((int)$id, [$this->getStatusField() => (int)$status]);
        return $this->success('修改成功');
    }

    /**
     * 修改商户平台抽佣比例。
     */
    public function commissionRate(int $id): Response
    {
        $data = $this->request->postMore([
            ['commission_rate', 0],
        ]);

        $this->service->updateCommissionRate($id, $data['commission_rate']);
        return $this->success('修改成功');
    }

    /**
     * 审核商户
     * @param int $id
     * @return Response
     */
    public function audit(int $id): Response
    {
        $data = $this->request->postMore([
            ['audit_status', 0],
            ['audit_remark', ''],
        ]);

        $this->service->auditMerchant($id, (int)$data['audit_status'], $data['audit_remark']);
        return $this->success('审核操作成功');
    }

    /**
     * 修改营业状态
     * @param int $id
     * @return Response
     */
    public function businessStatus(int $id): Response
    {
        $data = $this->request->postMore([
            ['business_status', 1],
        ]);

        $this->service->updateBusinessStatus($id, (int)$data['business_status']);
        return $this->success('操作成功');
    }

    /**
     * 设置推荐状态
     * @param int $id
     * @return Response
     */
    public function recommend(int $id): Response
    {
        $data = $this->request->postMore([
            ['is_recommend', 0],
        ]);

        $this->service->setRecommend($id, (int)$data['is_recommend']);
        return $this->success('操作成功');
    }

    /**
     * 设置排序
     * @param int $id
     * @return Response
     */
    public function sort($id): Response
    {
        $data = $this->request->postMore([
            ['sort', 0],
        ]);

        $this->service->setMerchantSort($id, (int)$data['sort']);
        return $this->success('操作成功');
    }

    /**
     * 获取商户统计数据
     * @param int $id
     * @return Response
     */
    public function statistics(int $id): Response
    {
        $result = $this->service->getMerchantStatistics($id);
        return $this->success($result);
    }

    /**
     * 修改商户管理员密码
     * @param int $id
     * @return Response
     */
    public function changePassword(int $id): Response
    {
        [$newPassword] = $this->request->postMore([
            ['new_password', ''],
        ], true);

        try {
            $this->service->changePassword($id, $newPassword);
            return $this->success('密码修改成功');
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }
    }

    /**
     * 一键登录商户后台（生成商户 token 给平台端跳转用）
     * @param int $id
     * @return Response
     */
    public function loginAs(int $id): Response
    {
        try {
            $result = $this->service->generateMerchantToken($id);
            return $this->success($result);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }
    }
}
