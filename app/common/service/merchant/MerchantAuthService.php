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

use app\common\exception\AdminAuthException;
use app\common\exception\AdminResponseException;
use app\common\model\merchant\Merchant;
use app\common\model\setting\system\Admin;
use app\common\service\BaseService;
use app\Request;
use Rabbit\util\CacheTag;
use Rabbit\util\Jwt;
use Rabbit\service\websocket\TokenHelper;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\exception\ValidateException;

/**
 * 商户认证服务类
 * 商户管理员使用admin表，通过platform_type字段区分
 * Class MerchantAuthService
 * @package app\common\service\merchant
 */
class MerchantAuthService extends BaseService
{
    /**
     * 设置模型
     * @return string
     */
    protected function setModel(): string
    {
        return Admin::class;
    }

    /**
     * 获取缓存标签
     * @return CacheTag
     */
    public function cacheTag()
    {
        return new CacheTag($this->getModel()->getName() . '_mechant');
    }

    /**
     * 商户管理员登录
     * @param string $account 账号
     * @param string $password 密码
     * @param string $key 缓存键（用于登录失败次数限制）
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws AdminResponseException
     */
    public function login(string $account, string $password, string $key = ''): array
    {
        $timeout = config('app.token_timeout', 864000);
        $ttl = 3600;

        // 获取登录失败次数
        $failCount = $this->cacheTag()->get($key);
        if (!$failCount) {
            $failCount = 0;
        }

        // 查询商户管理员（使用admin表，platform_type=merchant）
        $admin = $this->getModel()
            ->where('account', $account)
            ->where('platform_type', Admin::PLATFORM_TYPE_MERCHANT)
            ->field(['id', 'account', 'password', 'real_name', 'avatar', 'roles',
                     'status', 'last_ip', 'last_time', 'merchant_id', 'platform_type'])
            ->find();

        if (!$admin) {
            $this->cacheTag()->set($key, $failCount + 1, $ttl);
            throw new AdminResponseException('账号或密码不正确');
        }

        // 验证密码
        if (!password_verify($password, $admin->password)) {
            $this->cacheTag()->set($key, $failCount + 1, $ttl);
            throw new AdminResponseException('账号或密码不正确');
        }

        // 验证状态
        if (!$admin->status) {
            $this->cacheTag()->set($key, $failCount + 1, $ttl);
            throw new AdminResponseException('您的账号已被禁用');
        }

        // 验证是否关联商户
        if (!$admin->merchant_id) {
            throw new AdminResponseException('该管理员未关联商户');
        }

        // 获取商户信息并验证商户状态
        $merchant = app()->make(MerchantService::class)->get($admin->merchant_id);
        if (!$merchant) {
            throw new AdminResponseException('关联商户不存在');
        }

        if (!$merchant->status) {
            throw new AdminResponseException('商户已被禁用');
        }

        if ($merchant->audit_status != 1) {
            $auditStatusText = ['待审核', '已通过', '已驳回'];
            throw new AdminResponseException('商户审核状态为：' . ($auditStatusText[$merchant->audit_status] ?? '未知'));
        }

        // 更新登录信息
        $admin->last_ip = request()->ip();
        $admin->last_time = date('Y-m-d H:i:s');
        $admin->save();

        // 生成Token（使用merchant类型）
        $token = $this->getModel()->setToken((int)$admin->id, 'merchant');
        $this->cacheTag()->set(md5($token), $token, $timeout);

        // 清除登录失败次数
        if ($key) {
            $this->cacheTag()->delete($key);
        }

        // 管理员信息
        $adminInfo = $admin->hidden(['password', 'last_ip'])->toArray();

        // 附加商户信息
        $adminInfo['merchant'] = $merchant->toArray();

        // 获取商户菜单
        $menus = app()->make(MerchantMenuService::class)->getMenusByRoles((array)$adminInfo['roles'], (int)$admin->merchant_id);

        // 登录失败次数>=3需要验证码
        $loginCaptcha = $this->cacheTag()->get($key) >= 3;

        return [
            'adminInfo'     => $adminInfo,
            'token'         => $token,
            'expiresIn'     => $timeout,
            'menus'         => $menus,
            'login_captcha' => $loginCaptcha,
            'isLogin'       => true,
            'tokenmd5'      => md5($token),
        ];
    }

    /**
     * 验证Token
     * @param string $token
     * @return array
     * @throws AdminAuthException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function auth(string $token): array
    {
        if (!$token) {
            throw new AdminAuthException('缺少TOKEN', 41001);
        }

        if (!$this->cacheTag()->get(md5($token))) {
            throw new AdminAuthException('TOKEN已过期或者无法解析', 41003);
        }

        $jwt = Jwt::decode($token);
        if (!$jwt) {
            throw new AdminAuthException('TOKEN已过期或者无法解析', 41003);
        }

        if (empty($jwt->id) || ($jwt->type ?? '') !== 'merchant') {
            throw new AdminAuthException('TOKEN不合法', 41003);
        }

        // 获取管理员信息
        $admin = $this->get($jwt->id);

        if (!$admin) {
            throw new AdminAuthException('管理员不存在', 41004);
        }

        // 验证是否为商户管理员
        if ($admin->platform_type !== Admin::PLATFORM_TYPE_MERCHANT) {
            throw new AdminAuthException('非商户管理员', 41004);
        }

        if (!$admin->status) {
            throw new AdminAuthException('您的账号已被禁用', 41004);
        }

        // 验证商户状态
        if ($admin->merchant_id) {
            $merchant = app()->make(MerchantService::class)->get($admin->merchant_id);
            if (!$merchant || !$merchant->status) {
                throw new AdminAuthException('商户已被禁用', 41004);
            }
            if ($merchant->audit_status != 1) {
                throw new AdminAuthException('商户未通过审核', 41004);
            }
        }

        $adminInfo = $admin->toArray();

        return compact('token', 'adminInfo', 'jwt');
    }

    /**
     * 校验商户管理员当前请求是否命中其角色权限。
     *
     * 商户主账号沿用平台端主账号语义，不受角色菜单限制；子管理员默认拒绝未配置的请求。
     *
     * @param Request $request 当前请求
     * @param array $adminInfo 当前管理员信息
     * @return true
     * @throws ValidateException
     */
    public function checkRole(Request $request, array $adminInfo): true
    {
        if ((int)($adminInfo['pid'] ?? 0) <= 0) {
            return true;
        }

        $rule = $this->normalizePermissionRule((string)$request->rule()->getRule());
        $method = strtoupper((string)$request->method());

        // 退出和当前管理员信息是认证生命周期接口，不能依赖角色菜单，否则子管理员无法退出或初始化。
        if (in_array($rule, ['auth/logout', 'auth/info'], true)) {
            return true;
        }

        $menus = app()->make(MerchantMenuService::class)->getPermissionMenusByRoles(
            (array)($adminInfo['roles'] ?? []),
            (int)($adminInfo['merchant_id'] ?? 0)
        );

        foreach ($menus as $menu) {
            if ($this->isPermissionMatch($menu, $rule, $method)) {
                return true;
            }
        }

        throw new ValidateException('暂无权限访问');
    }

    /**
     * 统一接口权限中的前缀和路径参数表示，兼容路由 :id 与菜单 <id> 写法。
     */
    private function normalizePermissionRule(string $rule): string
    {
        $rule = trim(strtolower($rule), '/');
        $rule = str_replace(['api/merchant/', '/api/merchant/', 'api/', '/api/'], '', $rule);
        $rule = preg_replace('/(?:^|\/)[:][^\/]+/', '/{param}', $rule) ?? $rule;
        $rule = preg_replace('/(?:^|\/)<[^>]+>/', '/{param}', $rule) ?? $rule;
        $rule = preg_replace('/(?:^|\/)\{[^}]+\}/', '/{param}', $rule) ?? $rule;

        return trim($rule, '/');
    }

    /**
     * 判断单个商户权限节点是否匹配当前路由和 HTTP method。
     *
     * @param array $menu 权限菜单节点
     * @param string $rule 已规范化的当前路由
     * @param string $method 当前 HTTP method
     * @return bool 是否允许访问
     */
    private function isPermissionMatch(array $menu, string $rule, string $method): bool
    {
        $menuRule = $this->normalizePermissionRule((string)($menu['url'] ?? ''));
        if ($menuRule !== $rule) {
            return false;
        }

        $menuMethods = strtoupper(trim((string)($menu['methods'] ?? '')));
        if ($menuMethods === '') {
            // 只有导航菜单可以没有 method；功能节点缺少 method 不能放行任意请求。
            return (int)($menu['auth_type'] ?? 0) === 0;
        }

        $allowedMethods = preg_split('/[\s,|]+/', $menuMethods, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return in_array($method, $allowedMethods, true);
    }

    /**
     * 退出登录
     * @param string $token
     * @return void
     */
    public function logout(string $token): void
    {
        $this->cacheTag()->delete(md5($token));

        $payload = Jwt::decode($token);
        $adminId = is_object($payload) ? (int)($payload->id ?? 0) : 0;
        if ($adminId <= 0) {
            return;
        }

        $admin = $this->get($adminId, ['id', 'merchant_id', 'platform_type']);
        if ($admin && (int)$admin->platform_type === Admin::PLATFORM_TYPE_MERCHANT) {
            TokenHelper::revokeMerchantToken((int)$admin->merchant_id, $adminId);
        }
    }

    /**
     * 获取当前登录商户管理员ID
     * @return int
     */
    public function getCurrentAdminId(): int
    {
        return request()->adminId ?? 0;
    }

    /**
     * 获取当前登录商户ID
     * @return int
     */
    public function getCurrentMerchantId(): int
    {
        return request()->merchantId ?? 0;
    }

    /**
     * 获取当前登录管理员信息
     * @return array
     */
    public function getCurrentAdminInfo(): array
    {
        return request()->adminInfo ?? [];
    }

    /**
     * 获取当前登录商户信息
     * @return array
     */
    public function getCurrentMerchantInfo(): array
    {
        return request()->merchantInfo ?? [];
    }

    /**
     * 修改密码
     * @param int $adminId
     * @param string $oldPassword
     * @param string $newPassword
     * @return void
     * @throws AdminResponseException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function changePassword(int $adminId, string $oldPassword, string $newPassword): void
    {
        $admin = $this->get($adminId);
        if (!$admin) {
            throw new AdminResponseException('管理员不存在');
        }

        if (!password_verify($oldPassword, $admin->password)) {
            throw new AdminResponseException('原密码不正确');
        }

        $this->update($adminId, [
            'password' => password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 10])
        ]);
    }

    /**
     * 重置密码
     * @param int $adminId
     * @param string $newPassword
     * @return void
     * @throws AdminResponseException
     */
    public function resetPassword(int $adminId, string $newPassword): void
    {
        $admin = $this->get($adminId);
        if (!$admin) {
            throw new AdminResponseException('管理员不存在');
        }

        $this->update($adminId, [
            'password' => password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 10])
        ]);
    }
}
