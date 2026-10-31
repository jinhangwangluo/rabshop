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

namespace app\common\service\merchant;

use app\common\exception\AdminResponseException;
use app\common\model\merchant\MerchantApply;
use app\common\model\setting\system\Admin;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\message\BackendMessageEventService;
use app\common\service\setting\AdminService;

/**
 * Class MerchantApplyService
 * @package app\common\service\merchant
 */
class MerchantApplyService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * @return string
     */
    protected function setModel(): string
    {
        return MerchantApply::class;
    }

    /**
     * 审核
     * @param int $id
     * @param int $status
     * @param string $fail_msg
     * @return void
     * @throws \Exception
     */
    public function audit(int $id, int $status, string $fail_msg = '')
    {
        $apply = $this->get($id);
        if (!$apply)
            throw new AdminResponseException('申请不存在');
        if ($apply->status != 0)
            throw new AdminResponseException('已审核');

        $this->transaction(function () use ($apply, $status, $fail_msg) {
            $apply->status = $status;
            $apply->fail_msg = $fail_msg;
            $apply->audit_time = date('Y-m-d H:i:s');
            $apply->save();
            $merchantId = 0;
            $merchantCreated = false;

            if ($status == 1) {
                // 创建商户
                $merchantService = app()->make(MerchantService::class);
                $exist = $merchantService->count(['uid' => $apply->uid]);
                
                if (!$exist) {
                    $merchant = $merchantService->create([
                        'uid'       => $apply->uid,
                        'mer_name'  => $apply->mer_name,
                        'mer_phone' => $apply->mer_phone,
                        'real_name' => $apply->real_name,
                        'status'    => 1,
                        'audit_status' => 1,
                        'audit_time' => date('Y-m-d H:i:s'),
                    ]);
                    $merchantId = $merchant->id;
                    $merchantCreated = true;
                } else {
                    $merchant = $merchantService->getByWhere(['uid' => $apply->uid]);
                    $merchantId = $merchant->id;
                }

                // 创建商户管理员账号
                if ($merchantId && $apply->account) {
                    $adminService = app()->make(AdminService::class);
                    // 检查账号是否已存在
                    $existAdmin = $adminService->count(['account' => $apply->account]);
                    if (!$existAdmin) {
                        $adminService->create([
                            'account'       => $apply->account,
                            'password'      => $apply->password ?: password_hash('123456', PASSWORD_BCRYPT, ['cost' => 10]),
                            'real_name'     => $apply->real_name ?: $apply->mer_name,
                            'platform_type' => Admin::PLATFORM_TYPE_MERCHANT,
                            'merchant_id'   => $merchantId,
                            'status'        => 1,
                            'roles'         => [],
                        ]);
                    }
                }

                if ($merchantCreated && $merchantId > 0) {
                    $merchantService->publishMerchantEvent('merchant.created', $merchantId, [
                        'operation' => 'merchant_apply_audit',
                        'source' => 'merchant_apply',
                    ]);
                }
            }

            app()->make(BackendMessageEventService::class)->merchantAuditResult([
                'id' => $merchantId,
                'apply_id' => (int)$apply->id,
                'uid' => (int)$apply->uid,
                'mer_name' => (string)$apply->mer_name,
                'mer_phone' => (string)$apply->mer_phone,
            ], $status, $fail_msg);

            if ($merchantId > 0) {
                $merchantService = app()->make(MerchantService::class);
                $merchantService->publishMerchantEvent('merchant.audit.changed', $merchantId, [
                    'operation' => 'audit',
                    'audit_status' => $status,
                    'audit_remark' => $fail_msg,
                    'source' => 'merchant_apply',
                ]);
            }
        });
    }
}
