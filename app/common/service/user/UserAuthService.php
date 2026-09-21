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

use app\common\exception\ApiValidateException;
use app\common\model\user\User;
use app\common\service\BaseService;
use app\common\service\message\BackendMessageEventService;
use app\common\service\sms\SmsService;
use app\Request;
use Rabbit\util\Jwt;
use Rabbit\service\websocket\TokenHelper;
use think\facade\Cache;

/**
 * 用户认证服务
 */
class UserAuthService extends BaseService
{
    private const TOKEN_TYPE = 'mobile';
    private const TOKEN_CACHE_PREFIX = 'mobile_token:';
    private const TOKEN_GRACE_CACHE_PREFIX = 'mobile_token_grace:';
    private const TOKEN_REFRESH_HEADER = 'X-New-Token';

    /**
     * 第三方平台授权服务实例.
     *
     * @var PlatformAuthService
     */
    protected PlatformAuthService $platformAuthService;

    /**
     * 初始化用户认证服务.
     *
     * @return void
     */
    public function __construct()
    {
        $this->platformAuthService = app()->make(PlatformAuthService::class);
    }

    /**
     * 手机号登录或自动注册.
     *
     * @param string $phone 手机号.
     * @param string $code 短信验证码.
     * @param int $spreadUid 上级分销人 UID.
     * @return array 登录结果，包含 token、用户信息和认证配置.
     * @throws \Exception
     */
    public function loginByPhone(string $phone, string $code, int $spreadUid = 0): array
    {
        $smsService = app()->make(SmsService::class);
        $smsService->verifyCode($phone, $code);

        $user = $this->findUserByPhone($phone);
        if (!$user instanceof User) {
            $user = $this->createUser([
                'phone' => $phone,
                'spread_uid' => $spreadUid,
            ]);
        } elseif (app()->make(UserDistributionService::class)->bindIfMissing((int)$user->uid, $spreadUid)) {
            $user = $this->findUserById((int)$user->uid) ?? $user;
        }

        $token = $this->generateToken((int) $user->uid);

        return $this->buildAuthResult($user, $token);
    }

    /**
     * 账号密码登录.
     *
     * @param string $account 登录账号，当前使用手机号.
     * @param string $password 登录密码.
     * @param int $spreadUid 上级分销人 UID.
     * @return array 登录结果，包含 token、用户信息和认证配置.
     */
    public function loginByAccount(string $account, string $password, int $spreadUid = 0): array
    {
        $user = $this->findUserByPhone($account);
        if (!$user instanceof User) {
            throw new ApiValidateException('账号或密码不正确');
        }

        if ((int) $user->status !== 1) {
            throw new ApiValidateException('当前账号已被禁用');
        }

        if (!$this->verifyPassword($user, $password)) {
            throw new ApiValidateException('账号或密码不正确');
        }

        if (app()->make(UserDistributionService::class)->bindIfMissing((int)$user->uid, $spreadUid)) {
            $user = $this->findUserById((int)$user->uid) ?? $user;
        }

        $token = $this->generateToken((int) $user->uid);

        return $this->buildAuthResult($user, $token);
    }

    /**
     * 微信公众号登录或自动注册.
     *
     * @param string $openid 微信公众号 openid.
     * @param array $userInfo 微信用户资料.
     * @param int $spreadUid 上级分销人 UID.
     * @return array 登录结果，包含 token、用户信息和认证配置.
     * @throws \Exception
     */
    public function loginByWechatOfficial(string $openid, array $userInfo, int $spreadUid = 0): array
    {
        $platform = 'wechat_oa';
        $auth = $this->platformAuthService->getByPlatformOpenid($platform, $openid);
        $profile = $this->normalizeWechatUserInfo($userInfo);

        if ($auth) {
            $user = $this->findUserById((int) $auth['uid']);
            if (!$user instanceof User) {
                throw new ApiValidateException('用户不存在');
            }
            $user = $this->syncWechatProfile($user, $profile);
            if (app()->make(UserDistributionService::class)->bindIfMissing((int)$user->uid, $spreadUid)) {
                $user = $this->findUserById((int)$user->uid) ?? $user;
            }
        } else {
            $user = $this->createUser([
                'nickname' => $profile['nickname'],
                'avatar' => $profile['avatar'],
                'spread_uid' => $spreadUid,
            ]);

            $this->platformAuthService->bindPlatform(
                (int) $user->uid,
                $platform,
                $openid,
                array_merge($userInfo, $profile),
                []
            );
        }

        $token = $this->generateToken((int) $user->uid);

        return $this->buildAuthResult($user, $token);
    }

    /**
     * 微信小程序登录或自动注册.
     *
     * @param string $openid 微信小程序 openid.
     * @param array $userInfo 微信用户资料.
     * @param string $sessionKey 微信小程序 session_key.
     * @param int $spreadUid 上级分销人 UID.
     * @return array 登录结果，包含 token、用户信息和认证配置.
     * @throws \Exception
     */
    public function loginByWechatMini(string $openid, array $userInfo, string $sessionKey, int $spreadUid = 0): array
    {
        $platform = 'wechat_mp';
        $auth = $this->platformAuthService->getByPlatformOpenid($platform, $openid);
        $profile = $this->normalizeWechatUserInfo($userInfo);

        if ($auth) {
            $user = $this->findUserById((int) $auth['uid']);
            if (!$user instanceof User) {
                throw new ApiValidateException('用户不存在');
            }
            $user = $this->syncWechatProfile($user, $profile, true);
            $this->platformAuthService->updateExtraData((int) $auth['id'], ['session_key' => $sessionKey]);
            if (app()->make(UserDistributionService::class)->bindIfMissing((int)$user->uid, $spreadUid)) {
                $user = $this->findUserById((int)$user->uid) ?? $user;
            }
        } else {
            $user = $this->createUser([
                'nickname' => $profile['nickname'],
                'avatar' => $profile['avatar'],
                'spread_uid' => $spreadUid,
            ]);

            $this->platformAuthService->bindPlatform(
                (int) $user->uid,
                $platform,
                $openid,
                array_merge($userInfo, $profile),
                ['session_key' => $sessionKey]
            );
        }

        $token = $this->generateToken((int) $user->uid);

        return $this->buildAuthResult($user, $token);
    }

    /**
     * 通过手机号和短信验证码重置登录密码.
     *
     * @param string $phone 手机号.
     * @param string $code 短信验证码.
     * @param string $password 新登录密码.
     * @return array 重置结果.
     * @throws \Exception
     */
    public function resetPasswordByPhone(string $phone, string $code, string $password): array
    {
        if (!(bool) preg_match('/^1\d{10}$/', $phone)) {
            throw new ApiValidateException('请输入正确的手机号');
        }

        if (strlen($password) < 6 || strlen($password) > 20) {
            throw new ApiValidateException('密码长度为6-20个字符');
        }

        $smsService = app()->make(SmsService::class);
        $smsService->verifyCode($phone, $code);

        $user = $this->findUserByPhone($phone);
        if (!$user instanceof User) {
            throw new ApiValidateException('手机号未注册');
        }

        $user->password = password_hash($password, PASSWORD_DEFAULT);
        $user->save();

        return ['reset' => true];
    }

    /**
     * 生成移动端登录令牌.
     *
     * @param int $userId 用户 ID.
     * @return string 登录令牌.
     */
    public function generateToken(int $userId): string
    {
        $this->assertActiveUser($userId);
        $expire = $this->getTokenTimeout();
        $token = Jwt::create($userId, time() + $expire, self::TOKEN_TYPE);

        Cache::set($this->getTokenCacheKey($userId), $token, $expire);

        return $token;
    }

    /**
     * 刷新移动端登录令牌.
     *
     * @param string $token 当前登录令牌.
     * @return array 新令牌和有效期.
     */
    public function refreshToken(string $token): array
    {
        $payload = $this->parseValidToken($token);
        if (!$payload) {
            throw new ApiValidateException('token无效或已过期');
        }

        if (!($payload['is_current'] ?? false)) {
            throw new ApiValidateException('token已刷新，请使用最新token');
        }

        $userId = (int)($payload['user_id'] ?? 0);
        $this->assertActiveUser($userId);
        $newToken = $this->replaceToken($token, $userId);

        return [
            'token' => $newToken,
            'expiresIn' => $this->getTokenTimeout(),
        ];
    }

    /**
     * 校验移动端登录令牌.
     *
     * @param string $token 登录令牌.
     * @return int|null 校验成功返回用户 ID，失败返回 null.
     */
    public function validateToken(string $token): ?int
    {
        $payload = $this->parseValidToken($token);
        if (!$payload) {
            return null;
        }

        $userId = (int)($payload['user_id'] ?? 0);
        // 每次请求都读取当前状态，确保禁用用户的既有令牌立即失效。
        if (!$this->isActiveUser($userId)) {
            return null;
        }

        if (($payload['is_current'] ?? false) && $this->shouldRefreshToken((int)($payload['exp'] ?? 0))) {
            $newToken = $this->replaceToken($token, $userId);
            app('http')->getResponse()?->header([self::TOKEN_REFRESH_HEADER => $newToken]);
        }

        return $userId;
    }

    /**
     * 撤销用户当前令牌。
     *
     * @param int $userId 用户 ID。
     * @return void
     */
    public function revokeToken(int $userId): void
    {
        if ($userId > 0) {
            Cache::delete($this->getTokenCacheKey($userId));
        }
    }

    /**
     * 退出登录并清理缓存令牌.
     *
     * @param Request $request 当前请求对象.
     * @return bool 退出处理结果.
     */
    public function logout(Request $request): bool
    {
        $authorization = trim((string) $request->header('Authorization', ''));
        if ($authorization === '') {
            return true;
        }

        $token = trim(str_ireplace('Bearer ', '', $authorization));

        $payload = Jwt::decode($token);
        if (!$payload) {
            return true;
        }

        $userId = (int) (is_object($payload) ? ($payload->id ?? 0) : ($payload['id'] ?? 0));
        if ($userId > 0) {
            Cache::delete($this->getTokenCacheKey($userId));
            Cache::delete($this->getTokenGraceCacheKey($token));
            TokenHelper::revokeUserToken($userId);
        }

        return true;
    }

    /**
     * 根据手机号获取用户模型.
     *
     * @param string $phone 手机号.
     * @return User|null 用户模型，不存在返回 null.
     */
    private function findUserByPhone(string $phone): ?User
    {
        $user = $this->getModel()->where('phone', $phone)->find();

        return $user instanceof User ? $user : null;
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
     * 创建移动端用户.
     *
     * @param array $data 用户基础数据，支持 nickname、avatar、phone、spread_uid.
     * @return User 新用户模型.
     */
    private function createUser(array $data): User
    {
        $nickname = trim((string) ($data['nickname'] ?? ''));
        $spreadUid = app()->make(UserDistributionService::class)->normalizeValidSpreadUid($data['spread_uid'] ?? 0);

        $payload = [
            'nickname' => $nickname !== '' ? $nickname : '用户_' . rand(10000, 99999),
            'avatar' => $data['avatar'] ?? '',
            'phone' => $data['phone'] ?? '',
            'status' => 1,
            'is_verify' => 1,
        ];
        if ($spreadUid > 0) {
            $payload['spread_uid'] = $spreadUid;
            $payload['spread_time'] = date('Y-m-d H:i:s');
        }

        $user = $this->create($payload);
        app()->make(BackendMessageEventService::class)->userRegistered($user->toArray());

        return $user;
    }

    /**
     * 校验登录密码并兼容旧密码格式.
     *
     * @param User $user 用户模型.
     * @param string $password 用户输入密码.
     * @return bool 密码是否正确.
     */
    private function verifyPassword(User $user, string $password): bool
    {
        $storedPassword = (string) $user->password;
        if ($storedPassword === '') {
            return false;
        }

        if (password_verify($password, $storedPassword)) {
            return true;
        }

        if (!$this->isMd5String($password) && password_verify(md5($password), $storedPassword)) {
            $this->upgradePassword($user, $password);

            return true;
        }

        if ($this->isMd5String($storedPassword) && hash_equals(strtolower($storedPassword), strtolower($password))) {
            return true;
        }

        if (!$this->isMd5String($password) && $this->isMd5String($storedPassword) && hash_equals(strtolower($storedPassword), md5($password))) {
            $this->upgradePassword($user, $password);

            return true;
        }

        if (!$this->isMd5String($storedPassword) && hash_equals($storedPassword, $password)) {
            if (!$this->isMd5String($password)) {
                $this->upgradePassword($user, $password);
            }

            return true;
        }

        return false;
    }

    /**
     * 升级旧密码存储格式.
     *
     * @param User $user 用户模型.
     * @param string $password 明文密码.
     * @return void
     */
    private function upgradePassword(User $user, string $password): void
    {
        $user->password = password_hash($password, PASSWORD_DEFAULT);
        $user->save();
    }

    /**
     * 判断字符串是否为 MD5 格式.
     *
     * @param string $value 待判断字符串.
     * @return bool 是否为 MD5 字符串.
     */
    private function isMd5String(string $value): bool
    {
        return (bool) preg_match('/^[a-f0-9]{32}$/i', $value);
    }

    /**
     * 组装登录返回数据.
     *
     * @param User $user 用户模型.
     * @param string $token 登录令牌.
     * @return array 登录返回数据.
     */
    private function buildAuthResult(User $user, string $token): array
    {
        return array_merge([
            'token' => $token,
            'expiresIn' => $this->getTokenTimeout(),
        ], app()->make(UserService::class)->buildMobileUserPayload($user));
    }

    /**
     * 解析并校验移动端令牌.
     *
     * @param string $token 登录令牌.
     * @return array|null 令牌数据，失败返回 null.
     */
    private function parseValidToken(string $token): ?array
    {
        $token = trim(str_ireplace('Bearer ', '', $token));
        if ($token === '') {
            return null;
        }

        try {
            $payload = Jwt::decode($token);
            if (!$payload) {
                return null;
            }

            $type = is_object($payload) ? ($payload->type ?? null) : ($payload['type'] ?? null);
            if ($type !== self::TOKEN_TYPE) {
                return null;
            }

            $userId = (int)(is_object($payload) ? ($payload->id ?? 0) : ($payload['id'] ?? 0));
            if ($userId <= 0) {
                return null;
            }

            $cachedToken = Cache::get($this->getTokenCacheKey($userId));
            $graceUserId = (int)Cache::get($this->getTokenGraceCacheKey($token));
            if ($cachedToken !== $token && $graceUserId !== $userId) {
                return null;
            }

            return [
                'user_id' => $userId,
                'exp' => (int)(is_object($payload) ? ($payload->exp ?? 0) : ($payload['exp'] ?? 0)),
                'is_current' => $cachedToken === $token,
            ];
        } catch (\Throwable $e) {
            unset($e);

            return null;
        }
    }

    /**
     * 替换移动端令牌.
     *
     * @param string $oldToken 旧令牌.
     * @param int $userId 用户 ID.
     * @return string 新令牌.
     */
    private function replaceToken(string $oldToken, int $userId): string
    {
        $newToken = $this->generateToken($userId);
        if ($oldToken !== '' && $oldToken !== $newToken) {
            Cache::set($this->getTokenGraceCacheKey($oldToken), $userId, $this->getTokenGraceTimeout());
        }

        return $newToken;
    }

    /**
     * 判断令牌是否需要刷新.
     *
     * @param int $expireAt 令牌过期时间戳.
     * @return bool 是否需要刷新.
     */
    private function shouldRefreshToken(int $expireAt): bool
    {
        $remaining = $expireAt - time();

        return $remaining > 0 && $remaining < $this->getTokenTimeout() * 0.2;
    }

    /**
     * 校验用户是否可继续登录.
     *
     * @param int $userId 用户 ID.
     * @return void
     */
    private function assertActiveUser(int $userId): void
    {
        $user = $this->findUserById($userId);
        if (!$user instanceof User) {
            throw new ApiValidateException('用户不存在');
        }

        if ((int)$user->status !== 1) {
            throw new ApiValidateException('当前账号已被禁用');
        }
    }

    /**
     * 判断用户是否存在且处于启用状态。
     *
     * @param int $userId 用户 ID。
     * @return bool
     */
    private function isActiveUser(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $user = $this->findUserById($userId);
        return $user instanceof User && (int)$user->status === 1;
    }

    /**
     * 获取移动端令牌有效期.
     *
     * @return int 有效期秒数.
     */
    private function getTokenTimeout(): int
    {
        return (int)config('app.mobile_token_timeout', 1296000);
    }

    /**
     * 获取旧令牌宽限期.
     *
     * @return int 宽限期秒数.
     */
    private function getTokenGraceTimeout(): int
    {
        return (int)config('app.mobile_token_grace_timeout', 120);
    }

    /**
     * 获取当前令牌缓存键.
     *
     * @param int $userId 用户 ID.
     * @return string 缓存键.
     */
    private function getTokenCacheKey(int $userId): string
    {
        return self::TOKEN_CACHE_PREFIX . $userId;
    }

    /**
     * 获取旧令牌宽限缓存键.
     *
     * @param string $token 登录令牌.
     * @return string 缓存键.
     */
    private function getTokenGraceCacheKey(string $token): string
    {
        return self::TOKEN_GRACE_CACHE_PREFIX . md5($token);
    }

    /**
     * 归一微信授权资料字段。
     *
     * 字段来源：
     * - 微信公众号网页授权：nickname、headimgurl。
     * - 微信小程序用户资料能力：nickName、avatarUrl。
     *
     * @param array $userInfo 微信授权资料。
     * @return array{nickname:string,avatar:string} 归一后的昵称和头像。
     */
    private function normalizeWechatUserInfo(array $userInfo): array
    {
        return [
            'nickname' => trim((string)($userInfo['nickname'] ?? $userInfo['nickName'] ?? '')),
            'avatar' => trim($this->extractAvatar($userInfo)),
        ];
    }

    /**
     * 同步微信授权资料到用户主表。
     *
     * @param User $user 用户模型。
     * @param array $profile 归一后的微信资料。
     * @param bool $onlyWhenMissing 是否仅在资料为空时补全。
     * @return User 同步后的用户模型。
     */
    private function syncWechatProfile(User $user, array $profile, bool $onlyWhenMissing = false): User
    {
        $changed = false;
        $nickname = trim((string)($profile['nickname'] ?? ''));
        $avatar = trim((string)($profile['avatar'] ?? ''));
        $canSyncNickname = !$onlyWhenMissing || trim((string)$user->nickname) === '';
        $canSyncAvatar = !$onlyWhenMissing || trim((string)$user->avatar) === '';

        if ($nickname !== '' && $canSyncNickname && (string)$user->nickname !== $nickname) {
            $user->nickname = $nickname;
            $changed = true;
        }

        if ($avatar !== '' && $canSyncAvatar && (string)$user->avatar !== $avatar) {
            $user->avatar = $avatar;
            $changed = true;
        }

        if ($changed) {
            $user->save();
        }

        return $user;
    }

    /**
     * 提取头像地址.
     *
     * @param array $userInfo 微信用户资料.
     * @return string 头像地址.
     */
    private function extractAvatar(array $userInfo): string
    {
        return (string) ($userInfo['avatar'] ?? $userInfo['headimgurl'] ?? $userInfo['avatarUrl'] ?? '');
    }

    /**
     * 设置当前服务对应模型.
     *
     * @return string 用户模型类名.
     */
    protected function setModel(): string
    {
        return User::class;
    }
}
