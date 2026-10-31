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
use app\common\model\setting\system\Admin;
use app\common\service\BaseService;
use FormBuilder\Exception\FormBuilderException;
use Rabbit\service\websocket\TokenHelper;
use Rabbit\traits\ViewTrait;
use Rabbit\util\FormBuild as Build;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 商户管理员服务
 * Class MerchantAdminService
 * @package app\common\service\merchant
 */
class MerchantAdminService extends BaseService
{
    use ViewTrait;

    /**
     * 设置模型
     * @return string
     */
    protected function setModel(): string
    {
        return Admin::class;
    }

    /**
     * 获取商户管理员列表
     * @param array $where
     * @param int $merchantId
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getAdminList(array $where, int $merchantId): array
    {
        [$page, $limit] = $this->getPageValue();

        // 强制只查询当前商户的管理员
        $where['platform_type'] = Admin::PLATFORM_TYPE_MERCHANT;
        $where['merchant_id'] = $merchantId;

        $model = $this->getModel()->search($where);

        $roleIds = [];
        $count = $model->count();
        $list = $model->order('id', 'desc')->page($page, $limit)
            ->field([
                'id',
                'roles',
                'last_time',
                'account',
                'real_name',
                'avatar',
                'status',
                'pid',
                'platform_type',
                'merchant_id',
                'message_notify_enabled',
                'message_notify_phone',
                'message_notify_email',
            ])
            ->select()
            ->each(function ($item) use (&$roleIds) {
                if ($item['roles']) {
                    $roleIds = array_merge($roleIds, $item['roles']);
                }
            })
            ->toArray();

        if ($roleIds) {
            $roleIds = array_merge_filter_unique($roleIds);
            $roleList = app()->make(MerchantAdminRoleService::class)->getRoleIdsByList($roleIds, $merchantId);
            foreach ($list as &$item) {
                $item['role_list'] = [];
                foreach ($item['roles'] as $id) {
                    if (isset($roleList[$id])) {
                        $item['role_list'][] = $roleList[$id];
                    }
                }
            }
        }

        return compact('count', 'list');
    }

    /**
     * 获取表单
     * @param int $id
     * @param int $merchantId
     * @param int $adminId 当前登录管理员ID
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws FormBuilderException
     * @throws ModelNotFoundException
     */
    public function getFormBuild(int $id, int $merchantId, int $adminId): array
    {
        $adminInfo = [];
        if ($id) {
            $adminInfo = $this->getModel()
                ->where('id', $id)
                ->where('merchant_id', $merchantId)
                ->where('platform_type', Admin::PLATFORM_TYPE_MERCHANT)
                ->field([
                    'id',
                    'account',
                    'roles',
                    'real_name',
                    'avatar',
                    'status',
                    'pid',
                    'message_notify_enabled',
                    'message_notify_phone',
                    'message_notify_email',
                ])
                ->find();
            if (!$adminInfo) {
                throw new AdminResponseException('管理员不存在');
            }
            $adminInfo = $adminInfo->toArray();
        }

        return $this->createElementForm($id ? '修改管理员' : '新增管理员', function () use ($adminInfo, $id, $merchantId, $adminId) {
            $roles = app()->make(MerchantAdminRoleService::class)->getRoles($merchantId);
            return [
                Build::input('real_name', '管理员姓名'),
                $id ? Build::input('account', '管理员账号')->disabled(true) : Build::input('account', '管理员账号')->required(),
                Build::password('password', '管理员密码')
                    ->placeholder($id ? '不修改密码不需要填写管理员密码' : '请输入管理员密码'),
                Build::password('confirm_password', '确认密码')
                    ->placeholder($id ? '不修改密码不需要填写确认密码' : '请输入确认密码'),
                Build::selectMultiple('roles', '管理员角色')->options($roles),
                Build::frameImageV1('avatar', '管理员头像'),
                Build::switches('status', '管理员状态')->inactiveValue(0)
                    ->activeValue(1)
                    ->inactiveText('关闭')
                    ->activeText('开启'),
                Build::switches('message_notify_enabled', '消息提醒')
                    ->inactiveValue(0)
                    ->activeValue(1)
                    ->inactiveText('关闭')
                    ->activeText('开启'),
                Build::input('message_notify_phone', '提醒手机号')
                    ->placeholder('开启消息提醒后必须填写提醒手机号'),
                Build::input('message_notify_email', '提醒邮箱')
                    ->placeholder('开启消息提醒后可填写提醒邮箱'),
            ];
        }, '/merchant/setting/admin/' . $id, 'post', ['data' => $adminInfo]);
    }

    /**
     * 标准化并校验商户管理员消息提醒配置。
     */
    public function normalizeMessageNotifyData(array $data): array
    {
        $enabled = (int)($data['message_notify_enabled'] ?? 0) === 1 ? 1 : 0;
        $phone = trim((string)($data['message_notify_phone'] ?? ''));
        $email = trim((string)($data['message_notify_email'] ?? ''));

        if ($enabled === 1 && $phone === '' && $email === '') {
            throw new AdminResponseException('开启消息提醒后请配置提醒手机号或提醒邮箱');
        }

        if ($phone !== '' && !preg_match('/^1[3-9]\d{9}$/', $phone)) {
            throw new AdminResponseException('提醒手机号格式不正确');
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new AdminResponseException('提醒邮箱格式不正确');
        }

        $data['message_notify_enabled'] = $enabled;
        $data['message_notify_phone'] = $phone;
        $data['message_notify_email'] = $email;

        return $data;
    }

    /**
     * 获取指定商户下已开启消息提醒的管理员。
     */
    public function getMessageNotifyAdminList(int $merchantId): array
    {
        return $this->getModel()
            ->where('merchant_id', $merchantId)
            ->where('platform_type', Admin::PLATFORM_TYPE_MERCHANT)
            ->where('status', 1)
            ->where('message_notify_enabled', 1)
            ->where(function ($query) {
                $query->where('message_notify_phone', '<>', '')
                    ->whereOr('message_notify_email', '<>', '');
            })
            ->field(['id', 'account', 'real_name', 'message_notify_phone', 'message_notify_email', 'merchant_id'])
            ->order('id', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * 创建商户管理员
     * @param array $data
     * @param int $merchantId
     * @param int $adminId 创建者ID
     * @return \think\model\Model
     * @throws AdminResponseException
     */
    public function createAdmin(array $data, int $merchantId, int $adminId)
    {
        $data = $this->normalizeMessageNotifyData($data);

        // 检查账号是否存在
        if ($this->getModel()->where('account', $data['account'])->value('id')) {
            throw new AdminResponseException('账号已存在');
        }

        // 设置商户管理员属性
        $data['platform_type'] = Admin::PLATFORM_TYPE_MERCHANT;
        $data['merchant_id'] = $merchantId;
        $data['pid'] = $adminId;

        // 密码处理
        if (isset($data['password']) && $data['password']) {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        } else {
            throw new AdminResponseException('请输入密码');
        }

        return $this->create($data);
    }

    /**
     * 更新商户管理员
     * @param int $id
     * @param array $data
     * @param int $merchantId
     * @return \think\model\Model
     * @throws AdminResponseException
     */
    public function updateAdmin(int $id, array $data, int $merchantId)
    {
        $data = $this->normalizeMessageNotifyData($data);

        $admin = $this->getModel()
            ->where('id', $id)
            ->where('merchant_id', $merchantId)
            ->where('platform_type', Admin::PLATFORM_TYPE_MERCHANT)
            ->find();

        if (!$admin) {
            throw new AdminResponseException('管理员不存在');
        }

        // 密码处理
        if (isset($data['password']) && $data['password']) {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        } else {
            unset($data['password']);
        }

        // 不能修改账号、平台类型、商户ID
        unset($data['account'], $data['platform_type'], $data['merchant_id']);

        $result = $this->update($id, $data);
        TokenHelper::revokeMerchantToken($merchantId, $id);

        return $result;
    }

    /**
     * 删除商户管理员
     * @param int $id
     * @param int $merchantId
     * @return bool
     * @throws AdminResponseException
     */
    public function deleteAdmin(int $id, int $merchantId): bool
    {
        $admin = $this->getModel()
            ->where('id', $id)
            ->where('merchant_id', $merchantId)
            ->where('platform_type', Admin::PLATFORM_TYPE_MERCHANT)
            ->find();

        if (!$admin) {
            throw new AdminResponseException('管理员不存在');
        }

        $result = $this->destroy($id);
        TokenHelper::revokeMerchantToken($merchantId, $id);

        return $result;
    }

    /**
     * 修改状态
     * @param int $id
     * @param int $status
     * @param int $merchantId
     * @return bool
     * @throws AdminResponseException
     */
    public function updateStatus(int $id, int $status, int $merchantId): bool
    {
        $admin = $this->getModel()
            ->where('id', $id)
            ->where('merchant_id', $merchantId)
            ->where('platform_type', Admin::PLATFORM_TYPE_MERCHANT)
            ->find();

        if (!$admin) {
            throw new AdminResponseException('管理员不存在');
        }

        $result = $this->update($id, ['status' => $status]) ? true : false;
        if ($result) {
            TokenHelper::revokeMerchantToken($merchantId, $id);
        }

        return $result;
    }

    /**
     * 获取商户管理员下拉列表
     * @param int $merchantId
     * @return array
     */
    public function getAdminSelect(int $merchantId): array
    {
        return $this->getModel()
            ->where('merchant_id', $merchantId)
            ->where('platform_type', Admin::PLATFORM_TYPE_MERCHANT)
            ->where('status', 1)
            ->field(['id as value', 'real_name as label'])
            ->select()
            ->toArray();
    }
}
