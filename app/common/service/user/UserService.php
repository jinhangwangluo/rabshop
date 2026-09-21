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

use app\common\enum\ConfigKeyEnum;
use app\common\exception\AdminResponseException;
use app\common\exception\ApiValidateException;
use app\common\model\user\User;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;

/**
 * 用户服务
 */
class UserService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 设置对应的模型
     */
    protected function setModel(): string
    {
        return User::class;
    }

    /**
     * 获取移动端当前登录用户信息.
     *
     * @param int $userId 用户 ID.
     * @return array 用户信息、资产信息、银行卡信息和认证配置.
     */
    public function getCurrentUserInfo(int $userId): array
    {
        $user = $this->findUserById($userId);
        if (!$user instanceof User) {
            throw new ApiValidateException('用户不存在');
        }

        return $this->buildMobileUserPayload($user);
    }

    /**
     * 绑定或修改移动端用户手机号.
     *
     * @param int $userId 用户 ID.
     * @param string $phone 待绑定手机号.
     * @param bool $onlyWhenMissing 是否仅在手机号为空时补全.
     * @return array 更新后的用户信息、资产信息、银行卡信息和认证配置.
     */
    public function bindPhone(int $userId, string $phone, bool $onlyWhenMissing = false): array
    {
        $user = $this->findUserById($userId);
        if (!$user instanceof User) {
            throw new ApiValidateException('用户不存在');
        }

        if ($onlyWhenMissing && trim((string)$user->phone) !== '') {
            return $this->buildMobileUserPayload($user);
        }

        if (!(bool) preg_match('/^1\d{10}$/', $phone)) {
            throw new ApiValidateException('请输入正确的手机号');
        }

        $existsUser = $this->getModel()
            ->where('phone', $phone)
            ->where('uid', '<>', $userId)
            ->find();

        if ($existsUser instanceof User) {
            throw new ApiValidateException('手机号已被其他账号占用');
        }

        $user->phone = $phone;
        $user->is_verify = 1;
        $user->save();

        return $this->buildMobileUserPayload($user);
    }

    /**
     * 更新移动端用户个人信息.
     *
     * @param int $userId 用户 ID.
     * @param array $data 用户资料字段，支持 nickname、gender、sex、avatar.
     * @return array 更新后的用户信息、资产信息、银行卡信息和认证配置.
     */
    public function updateMobileProfile(int $userId, array $data): array
    {
        $user = $this->findUserById($userId);
        if (!$user instanceof User) {
            throw new ApiValidateException('用户不存在');
        }

        $nickname = trim((string)($data['nickname'] ?? ''));
        if ($nickname === '') {
            throw new ApiValidateException('请输入昵称');
        }

        $sex = (int)($data['gender'] ?? $data['sex'] ?? $user->sex ?? 0);
        if (!in_array($sex, [0, 1, 2], true)) {
            throw new ApiValidateException('请选择正确的性别');
        }

        $user->nickname = $nickname;
        $user->sex = $sex;
        $user->avatar = trim((string)($data['avatar'] ?? $user->avatar ?? ''));
        $user->save();

        return $this->buildMobileUserPayload($user);
    }

    /**
     * 获取移动端用户提现银行卡信息.
     *
     * @param int $userId 用户 ID.
     * @return array 银行卡信息.
     */
    public function getBankCard(int $userId): array
    {
        $user = $this->findUserById($userId);
        if (!$user instanceof User) {
            throw new ApiValidateException('用户不存在');
        }

        return $this->buildBankCardPayload($user);
    }

    /**
     * 保存移动端用户提现银行卡信息.
     *
     * @param int $userId 用户 ID.
     * @param array $data 银行卡表单数据，支持 holder_name、card_no、bank_name、reserved_phone.
     * @return array 保存后的银行卡信息.
     */
    public function saveBankCard(int $userId, array $data): array
    {
        $user = $this->findUserById($userId);
        if (!$user instanceof User) {
            throw new ApiValidateException('用户不存在');
        }

        $holderName = trim((string)($data['holder_name'] ?? $data['holderName'] ?? ''));
        $cardNo = preg_replace('/\s+/', '', trim((string)($data['card_no'] ?? $data['cardNo'] ?? '')));
        $bankName = trim((string)($data['bank_name'] ?? $data['bankName'] ?? ''));
        $reservedPhone = trim((string)($data['reserved_phone'] ?? $data['reservedPhone'] ?? $data['phone'] ?? ''));

        if ($holderName === '') {
            throw new ApiValidateException('请输入持卡人姓名');
        }
        if (!preg_match('/^\d{12,19}$/', $cardNo)) {
            throw new ApiValidateException('请输入正确的银行卡号');
        }
        if ($bankName === '') {
            throw new ApiValidateException('请选择开户银行');
        }
        if (!preg_match('/^1\d{10}$/', $reservedPhone)) {
            throw new ApiValidateException('请输入正确的银行预留手机号');
        }

        $this->update($userId, [
            'bank_holder_name' => $holderName,
            'bank_card_no' => $cardNo,
            'bank_name' => $bankName,
            'bank_reserved_phone' => $reservedPhone,
        ]);

        return $this->getBankCard($userId);
    }

    /**
     * 组装移动端用户信息负载.
     *
     * @param User $user 用户模型.
     * @return array 用户信息负载.
     */
    public function buildMobileUserPayload(User $user): array
    {
        $userInfo = $user->toArray();
        $userInfo['bank_card'] = $this->buildBankCardPayload($user);
        $asset = app()->make(UserAssetService::class)->getInfo((int)$user->uid);
        $userInfo['asset'] = $asset ? $asset->toArray() : [
            'balance' => 0,
            'points' => 0,
            'coins' => 0,
            'exp' => 0,
            'commission' => 0,
        ];
        $userInfo['member'] = app()->make(UserMemberService::class)->getMemberRuntimeState((int)$user->uid);

        return [
            'user' => $userInfo,
            'userinfo' => $userInfo,
            'auth_config' => $this->buildAuthConfig(),
        ];
    }

    /**
     * 获取用户列表（携带资产信息）
     *
     * @param array $params 查询筛选参数，支持模型搜索器字段。
     * @param array|string $fields 查询字段，默认全部字段。
     * @param array $with 额外预加载关联，默认追加 asset。
     * @param string|array|null $sort 排序规则，默认按 uid 倒序。
     * @return array 用户分页列表及统计数据。
     */
    public function getResourceList(array $params = [], array|string $fields = [], array $with = [], string|array|null $sort = null)
    {
        $with = array_merge(['asset'], $with);
        $result = $this->getDefaultList($params, $fields ?: ['*'], $with, $sort ?: ['uid' => 'desc'])->toArray();
        $distributionService = app()->make(UserDistributionService::class);
        foreach (($result['list'] ?? []) as $index => $user) {
            if (!is_array($user)) {
                continue;
            }
            $result['list'][$index] = array_merge($user, $distributionService->getEffectivePermission($user));
        }
        $result['distribution_config'] = $distributionService->getConfig();
        // 附加统计数据
        $result['stats'] = $this->getUserStats();
        return $result;
    }

    /**
     * 用户统计数据
     *
     * @return array 包含总用户数、今日新增、本月新增和禁用用户数。
     */
    public function getUserStats(): array
    {
        $model = $this->getModel();

        return [
            'total' => $model->count(),
            'today' => $model->whereDay('create_time')->count(),
            'month' => $model->whereMonth('create_time')->count(),
            'disabled' => $model->where('status', 0)->count(),
        ];
    }

    /**
     * 读取用户详情（携带资产）
     *
     * @param int|array $id 用户主键或查询条件。
     * @return array 用户详情数据，包含资产与上级推广人信息。
     * @throws AdminResponseException 用户不存在时抛出。
     */
    public function resourceRead(int|array $id)
    {
        $model = $this->get($id, ['*'], ['asset', 'spreadUser']);
        if (!$model) {
            throw new AdminResponseException('用户不存在');
        }
        $data = $model->toArray();
        return array_merge($data, app()->make(UserDistributionService::class)->getEffectivePermission($data));
    }

    /**
     * 新增用户（含密码+确认密码校验）
     *
     * @param array $data 用户表单数据，需包含 password，可选 phone、confirm_password、spread_uid 等字段。
     * @return mixed 创建后的用户模型数据。
     * @throws AdminResponseException 手机号重复、密码为空或确认密码不一致时抛出。
     */
    public function resourceSave(array $data)
    {
        // 手机号去重
        if (!empty($data['phone'])) {
            $exists = $this->count(['phone' => $data['phone']]);
            if ($exists) {
                throw new AdminResponseException('该手机号已注册');
            }
        }

        // 密码与确认密码校验
        if (empty($data['password'])) {
            throw new AdminResponseException('密码不能为空');
        }
        if (($data['confirm_password'] ?? '') !== $data['password']) {
            throw new AdminResponseException('两次输入的密码不一致');
        }
        unset($data['confirm_password']);
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        $data = $this->normalizeDistributionPayload($data);

        // 分销上级绑定时间
        if (!empty($data['spread_uid'])) {
            $this->checkSpreadUser((int)$data['spread_uid']);
            $data['spread_time'] = date('Y-m-d H:i:s');
        }

        return $this->create($data);
    }

    /**
     * 更新用户
     *
     * @param int|array $id 用户主键或查询条件。
     * @param array $data 用户更新数据，可选 phone、password、confirm_password、spread_uid 等字段。
     * @return mixed 更新结果。
     * @throws AdminResponseException 用户不存在、手机号重复、确认密码不一致或上级不合法时抛出。
     */
    public function resourceUpdate(int|array $id, array $data)
    {
        $user = $this->get($id);
        if (!$user) {
            throw new AdminResponseException('用户不存在');
        }

        // 手机号去重（排除自身）
        if (!empty($data['phone']) && $data['phone'] !== $user->phone) {
            $exists = $this->count(['phone' => $data['phone']]);
            if ($exists) {
                throw new AdminResponseException('该手机号已被其他用户使用');
            }
        }

        // 密码修改：需提供确认密码
        if (!empty($data['password'])) {
            if (($data['confirm_password'] ?? '') !== $data['password']) {
                throw new AdminResponseException('两次输入的密码不一致');
            }
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        } else {
            unset($data['password']);
        }
        unset($data['confirm_password']);
        $data = $this->normalizeDistributionPayload($data);

        // 分销上级绑定：变更上级时更新绑定时间
        if (isset($data['spread_uid']) && (int)$data['spread_uid'] !== (int)$user->spread_uid) {
            if (!empty($data['spread_uid'])) {
                $this->checkSpreadUser((int)$data['spread_uid'], (int)$user->uid);
                $data['spread_time'] = date('Y-m-d H:i:s');
            } else {
                $data['spread_time'] = null;
            }
        }

        return $this->update($id, $data);
    }

    /**
     * 修改用户状态
     *
     * @param int $id 用户 ID。
     * @param int $status 用户状态值。
     * @return bool 修改成功返回 true。
     * @throws AdminResponseException 用户不存在时抛出。
     */
    public function updateStatus(int $id, int $status): bool
    {
        $user = $this->get($id);
        if (!$user) {
            throw new AdminResponseException('用户不存在');
        }
        $this->update($id, ['status' => $status]);
        if ($status !== 1) {
            app()->make(UserAuthService::class)->revokeToken($id);
        }
        return true;
    }

    /**
     * 设置用户分销权限
     *
     * @param int $userId 用户 ID。
     * @param int $distributionPermission 分销权限覆盖，-1=跟随模式，0=禁止，1=指定允许。
     * @return bool 设置成功返回 true。
     * @throws AdminResponseException 用户不存在时抛出。
     */
    public function setDistribution(int $userId, int $distributionPermission): bool
    {
        $user = $this->get($userId);
        if (!$user) {
            throw new AdminResponseException('用户不存在');
        }
        $permission = app()->make(UserDistributionService::class)->normalizePermission($distributionPermission);
        $this->update($userId, [
            'distribution_permission' => $permission,
            'is_distribution' => $permission === UserDistributionService::PERMISSION_ALLOW ? 1 : 0,
        ]);
        return true;
    }

    /**
     * 设置用户禁止/允许发评论
     *
     * @param int $userId 用户 ID。
     * @param int $isCommentBan 评论禁用状态，1=禁止评论，0=允许评论。
     * @return bool 设置成功返回 true。
     * @throws AdminResponseException 用户不存在时抛出。
     */
    public function setCommentBan(int $userId, int $isCommentBan): bool
    {
        $user = $this->get($userId);
        if (!$user) {
            throw new AdminResponseException('用户不存在');
        }
        $this->update($userId, ['is_comment_ban' => $isCommentBan]);
        return true;
    }

    /**
     * 检查用户是否被禁止发评论
     *
     * @param int $userId 用户 ID。
     * @return bool 已禁言返回 true，用户不存在或未禁言返回 false。
     */
    public function isCommentBanned(int $userId): bool
    {
        $user = $this->get($userId);
        if (!$user) {
            return false;
        }
        return (int)$user->is_comment_ban === 1;
    }

    /**
     * 绑定上级分销人
     *
     * @param int $userId 当前用户 ID。
     * @param int $spreadUid 上级分销人用户 ID。
     * @return bool 绑定成功返回 true。
     * @throws AdminResponseException 用户不存在或上级不合法时抛出。
     */
    public function bindSpreadUser(int $userId, int $spreadUid): bool
    {
        $user = $this->get($userId);
        if (!$user) {
            throw new AdminResponseException('用户不存在');
        }
        if ($spreadUid > 0) {
            $this->checkSpreadUser($spreadUid, $userId);
        }
        $this->update($userId, [
            'spread_uid' => $spreadUid,
            'spread_time' => $spreadUid > 0 ? date('Y-m-d H:i:s') : null,
        ]);
        return true;
    }

    /**
     * 更新用户基本信息（昵称、头像、性别、生日、职业等）
     *
     * @param int $id 用户 ID。
     * @param array $data 待更新的用户资料字段。
     * @param array $allowed 允许更新的字段白名单。
     * @return bool 更新成功返回 true。
     * @throws AdminResponseException 用户不存在或没有可更新字段时抛出。
     */
    public function updateBasic(int $id, array $data, array $allowed): bool
    {
        $user = $this->get($id);
        if (!$user) {
            throw new AdminResponseException('用户不存在');
        }

        $updateData = array_intersect_key($data, array_flip($allowed));
        if (empty($updateData)) {
            throw new AdminResponseException('请提交需要修改的字段');
        }

        return (bool)$this->update($id, $updateData);
    }

    /**
     * 修改用户手机号
     *
     * @param int $id 用户 ID。
     * @param string $phone 新手机号。
     * @return bool 修改成功返回 true。
     * @throws AdminResponseException 用户不存在或手机号已被占用时抛出。
     */
    public function updatePhone(int $id, string $phone): bool
    {
        $user = $this->get($id);
        if (!$user) {
            throw new AdminResponseException('用户不存在');
        }

        // 手机号去重（排除自身）
        if ($phone !== $user->phone) {
            $exists = $this->count(['phone' => $phone]);
            if ($exists) {
                throw new AdminResponseException('该手机号已被其他用户使用');
            }
        }

        return (bool)$this->update($id, ['phone' => $phone]);
    }

    /**
     * 修改用户密码
     *
     * @param int $id 用户 ID。
     * @param string $password 明文新密码，方法内会加密存储。
     * @return bool 修改成功返回 true。
     * @throws AdminResponseException 用户不存在时抛出。
     */
    public function updatePassword(int $id, string $password): bool
    {
        $user = $this->get($id);
        if (!$user) {
            throw new AdminResponseException('用户不存在');
        }

        $hashed = password_hash($password, PASSWORD_DEFAULT);

        return (bool)$this->update($id, ['password' => $hashed]);
    }

    /**
     * 根据手机号获取用户
     *
     * @param string $phone 手机号。
     * @return mixed 用户模型，不存在时返回 null。
     */
    public function getByPhone(string $phone)
    {
        return $this->get(['phone' => $phone]);
    }

    /**
     * 手动调整用户余额（管理端）
     *
     * @param int $userId 用户 ID。
     * @param string|float $amount 调整金额。
     * @param string $changeType 变动方向，in=增加，out=减少。
     * @param string $remark 备注，留空时使用默认备注。
     * @return bool 调整成功返回 true。
     * @throws AdminResponseException 用户不存在或资产不足时抛出。
     */
    public function adjustBalance(int $userId, string|float $amount, string $changeType, string $remark = ''): bool
    {
        $user = $this->get($userId);
        if (!$user) {
            throw new AdminResponseException('用户不存在');
        }
        /** @var UserAssetService $assetService */
        $assetService = app()->make(UserAssetService::class);
        $assetService->changeAsset($userId, 'balance', $amount, $changeType, $remark ?: '管理员手动调整');
        return true;
    }

    /**
     * 手动调整用户积分（管理端）
     *
     * @param int $userId 用户 ID。
     * @param float $amount 调整积分数量。
     * @param string $changeType 变动方向，in=增加，out=减少。
     * @param string $remark 备注，留空时使用默认备注。
     * @return bool 调整成功返回 true。
     * @throws AdminResponseException 用户不存在或积分不足时抛出。
     */
    public function adjustPoints(int $userId, float $amount, string $changeType, string $remark = ''): bool
    {
        $user = $this->get($userId);
        if (!$user) {
            throw new AdminResponseException('用户不存在');
        }
        /** @var UserAssetService $assetService */
        $assetService = app()->make(UserAssetService::class);
        $assetService->changeAsset($userId, 'points', $amount, $changeType, $remark ?: '管理员手动调整');
        return true;
    }

    /**
     * 获取某用户的下级推广人列表
     *
     * @param int $uid 上级用户 ID。
     * @param array $params 查询筛选参数。
     * @return mixed 下级推广人分页列表。
     */
    public function getSubordinates(int $uid, array $params = [])
    {
        $params['spread_uid'] = $uid;
        return $this->getDefaultList($params, ['*'], ['asset'], ['uid' => 'desc']);
    }

    /**
     * 校验上级分销人合法性（不能绑定自身，不能绑定自己的下级）
     *
     * @param int $spreadUid 待绑定的上级用户 ID。
     * @param int $selfUid 当前用户 ID，新增用户时可为 0。
     * @return void
     * @throws AdminResponseException 上级不存在、绑定自身或绑定下级时抛出。
     */
    protected function checkSpreadUser(int $spreadUid, int $selfUid = 0): void
    {
        app()->make(UserDistributionService::class)->assertValidSpreadUser($spreadUid, $selfUid);
    }

    /**
     * 兼容旧 is_distribution 字段并规范化三态分销权限。
     *
     * @param array $data 用户保存数据。
     * @return array 规范后的用户保存数据。
     */
    protected function normalizeDistributionPayload(array $data): array
    {
        $distributionService = app()->make(UserDistributionService::class);
        if (array_key_exists('distribution_permission', $data)) {
            $permission = $distributionService->normalizePermission($data['distribution_permission']);
        } elseif (array_key_exists('is_distribution', $data)) {
            $permission = (int)$data['is_distribution'] === 1
                ? UserDistributionService::PERMISSION_ALLOW
                : UserDistributionService::PERMISSION_FORBID;
        } else {
            return $data;
        }

        $data['distribution_permission'] = $permission;
        $data['is_distribution'] = $permission === UserDistributionService::PERMISSION_ALLOW ? 1 : 0;

        return $data;
    }

    /**
     * 根据用户 ID 获取用户模型.
     *
     * @param int $userId 用户 ID.
     * @return User|null 用户模型，不存在返回 null.
     */
    private function findUserById(int $userId): ?User
    {
        $user = $this->getModel()->find($userId);

        return $user instanceof User ? $user : null;
    }

    /**
     * 组装用户银行卡信息.
     *
     * @param User $user 用户模型.
     * @return array 银行卡信息负载.
     */
    private function buildBankCardPayload(User $user): array
    {
        $cardNo = preg_replace('/\s+/', '', (string)($user->bank_card_no ?? ''));
        $tailNumber = $cardNo !== '' ? substr($cardNo, -4) : '';

        return [
            'holder_name' => (string)($user->bank_holder_name ?? ''),
            'card_no' => $cardNo,
            'bank_name' => (string)($user->bank_name ?? ''),
            'reserved_phone' => (string)($user->bank_reserved_phone ?? ''),
            'tail_number' => $tailNumber,
            'has_card' => $cardNo !== '' && (string)($user->bank_name ?? '') !== '',
        ];
    }

    /**
     * 组装移动端登录策略配置.
     *
     * @return array 登录策略配置.
     */
    private function buildAuthConfig(): array
    {
        return [
            'miniapp_force_bind_phone' => $this->normalizeBoolean(
                db_config(ConfigKeyEnum::MINIAPP_FORCE_BIND_PHONE, false)
            ),
        ];
    }

    /**
     * 标准化配置布尔值.
     *
     * @param mixed $value 配置值.
     * @return bool 标准布尔值.
     */
    private function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int)$value === 1;
        }

        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
    }
}
