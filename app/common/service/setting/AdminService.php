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

namespace app\common\service\setting;

use app\common\enum\RuleEnum;
use app\common\exception\AdminAuthException;
use app\common\exception\AdminResponseException;
use app\common\model\setting\system\Admin;
use app\common\service\BaseService;
use app\common\service\user\UserService;
use app\Request;
use Rabbit\util\Jwt;
use Rabbit\util\Str;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\exception\ValidateException;

/**
 * 管理员服务
 * Class AdminService
 * @package app\common\service\setting
 */
class AdminService extends BaseService
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
     * 获取管理员列表
     * @param array $where
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getAdminUserList(array $where)
    {
        [$page, $limit] = $this->getPageValue();

        // 默认只查询平台管理员
        $where['platform_type'] = $where['platform_type'] ?? Admin::PLATFORM_TYPE_PLATFORM;

        $model = $this->getModel()->search($where);

        $roleIds = [];
        $count = $model->count();
        $list = $model->order('id', 'desc')->page($page, $limit)
            ->field([
                'id',
                'roles',
                'last_time',
                'last_ip',
                'account',
                'real_name',
                'avatar',
                'status',
                'pid',
                'platform_type',
                'merchant_id',
                'user_id',
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
        $this->appendBindUserInfo($list);

        if ($roleIds) {
            $roleIds = array_merge_filter_unique($roleIds);
            $roleList = app()->make(AdminRoleService::class)->getRoleIdsByList($roleIds);
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
     * 标准化并校验管理员消息提醒配置。
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
     * 获取已开启平台管理员消息提醒的管理员。
     *
     * @return array
     */
    public function getMessageNotifyAdminList(): array
    {
        return $this->getModel()
            ->where('platform_type', Admin::PLATFORM_TYPE_PLATFORM)
            ->where('status', 1)
            ->where('message_notify_enabled', 1)
            ->where(function ($query) {
                $query->where('message_notify_phone', '<>', '')
                    ->whereOr('message_notify_email', '<>', '');
            })
            ->field(['id', 'account', 'real_name', 'message_notify_phone', 'message_notify_email'])
            ->order('id', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * 根据当前管理员的后台权限生成 WebSocket 入站操作范围。
     *
     * 平台主管理员沿用现有 pid=0 的全量权限语义；子管理员只从已分配的
     * 功能菜单映射范围，不能因为拿到 ws_token 就获得完整中控权限。
     *
     * @param array $adminInfo 当前管理员信息。
     * @return array<string> WebSocket 入站操作范围。
     */
    public function getWebSocketScopes(array $adminInfo): array
    {
        if ((int)($adminInfo['platform_type'] ?? Admin::PLATFORM_TYPE_PLATFORM) !== Admin::PLATFORM_TYPE_PLATFORM) {
            return [];
        }

        $allScopes = [
            'console_action:start_live',
            'console_action:end_live',
            'console_action:mute',
            'console_action:unmute',
            'console_action:kick',
            'console_action:toggle_hot',
            'console_action:get_online_users',
            'console_action:goods_shelf',
            'console_action:goods_stock',
            'console_action:goods_info',
            'console_reply',
            'system_broadcast',
        ];

        if ((int)($adminInfo['pid'] ?? 0) <= 0) {
            return $allScopes;
        }

        $menus = app()->make(MenuService::class)->getAdminLevelMenus(
            (array)($adminInfo['roles'] ?? [])
        );
        $menuRules = [];
        foreach ($menus as $menu) {
            $rule = $this->normalizeWebSocketPermissionRule((string)($menu['url'] ?? ''));
            if ($rule !== '') {
                $menuRules[$rule] = true;
            }
        }

        $scopeRules = [
            'live/room/{param}/switch_status' => [
                'console_action:start_live',
                'console_action:end_live',
            ],
            'live/room/{param}/toggle_hot' => ['console_action:toggle_hot'],
            'live/room/{param}/users' => ['console_action:get_online_users'],
            'live/room/{param}/user/{param}/mute' => [
                'console_action:mute',
                'console_action:unmute',
            ],
            'live/room/{param}/user/{param}/kick' => ['console_action:kick'],
            'live/room/{param}/comment/send' => ['console_reply'],
            'live/goods' => [
                'console_action:goods_shelf',
                'console_action:goods_stock',
                'console_action:goods_info',
            ],
            'live/goods/{param}' => [
                'console_action:goods_shelf',
                'console_action:goods_stock',
                'console_action:goods_info',
            ],
            'live/goods/explaining/{param}' => ['console_action:goods_info'],
            'setting/system_broadcast' => ['system_broadcast'],
        ];

        $scopes = [];
        foreach ($scopeRules as $rule => $ruleScopes) {
            if (isset($menuRules[$rule])) {
                $scopes = array_merge($scopes, $ruleScopes);
            }
        }

        return array_values(array_unique($scopes));
    }

    /**
     * 统一后台菜单规则的前缀和路径参数表示。
     */
    private function normalizeWebSocketPermissionRule(string $rule): string
    {
        $rule = trim(strtolower($rule), '/');
        $rule = preg_replace('#^api/admin/#', '', $rule) ?? $rule;
        $rule = preg_replace('/(?:^|\/)[:][^\/]+/', '/{param}', $rule) ?? $rule;
        $rule = preg_replace('/(?:^|\/)<[^>]+>/', '/{param}', $rule) ?? $rule;
        $rule = preg_replace('/(?:^|\/)\{[^}]+\}/', '/{param}', $rule) ?? $rule;

        return trim($rule, '/');
    }

    /**
     * 获取所有管理员
     * @param $pid
     * @return mixed
     */
    public function getAdminAll($pid = 0)
    {
        return $this->getModel()->search(['pid' => $pid, 'status' => 1])->field(['id as value', 'account as label'])->select()->toArray();
    }

    /**
     * 获取用户选择弹窗列表。
     */
    public function getUserSelectList(array $where): array
    {
        [$page, $limit] = $this->getPagesValue();
        $userService = app()->make(UserService::class);
        $keyword = trim((string)($where['keyword'] ?? ''));

        $buildQuery = function () use ($userService, $keyword) {
            $query = $userService->getModel();
            if ($keyword !== '') {
                $query->where(function ($q) use ($keyword) {
                    $q->where('nickname', 'like', '%' . $keyword . '%')
                        ->whereOr('phone', 'like', '%' . $keyword . '%');
                    if (ctype_digit($keyword)) {
                        $q->whereOr('uid', (int)$keyword);
                    }
                });
            }
            return $query;
        };

        $count = $buildQuery()->count();
        $list = $buildQuery()
            ->field(['uid', 'nickname', 'avatar', 'phone', 'status'])
            ->order('uid desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        return $this->listData($list, (int)$count, [], $page, $limit)->toArray();
    }

    /**
     * 给管理员列表补充绑定用户展示信息。
     */
    private function appendBindUserInfo(array &$list): void
    {
        $userIds = array_values(array_unique(array_filter(array_map(
            fn($item) => (int)($item['user_id'] ?? 0),
            $list
        ))));
        if ($userIds === []) {
            foreach ($list as &$item) {
                $item['bind_user'] = null;
            }
            return;
        }

        $users = app()->make(UserService::class)
            ->getModel()
            ->whereIn('uid', $userIds)
            ->field(['uid', 'nickname', 'avatar', 'phone'])
            ->select()
            ->toArray();
        $userMap = [];
        foreach ($users as $user) {
            $userMap[(int)$user['uid']] = $user;
        }
        foreach ($list as &$item) {
            $item['bind_user'] = $userMap[(int)($item['user_id'] ?? 0)] ?? null;
        }
    }

    /**
     * 检测是否登录，并在 token 剩余有效期不足 20% 时自动续期，写入响应头 X-New-Token
     * @param string $token
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function auth(string $token)
    {
        if (!$token) {
            throw new AdminAuthException('缺少token', 41002);
        }

        if (!$this->cacheTag()->get(md5($token))) {
            throw new AdminAuthException('TOKEN已过期或者无法解析!!', 41003);
        }

        $jwt = Jwt::decode($token);
        if (!$jwt) {
            throw new AdminAuthException('TOKEN已过期或者无法解析', 41003);
        }

        if (empty($jwt->id)) {
            throw new AdminAuthException('TOKEN不合法', 41003);
        }

        $adminInfo = $this->get($jwt->id);

        if (!$adminInfo->status) {
            throw new AdminAuthException('您已被禁止登录', 41004);
        }
        $adminInfo = $adminInfo->toArray();

        // token 剩余有效期不足总有效期的 20% 时，自动颁发新 token（无感刷新）
        $timeout = config('app.token_timeout', 864000);
        $remaining = isset($jwt->exp) ? ($jwt->exp - time()) : 0;
        if ($remaining > 0 && $remaining < $timeout * 0.2) {
            $newToken = $this->doRefreshToken($token, (int)$jwt->id);
            app('http')->getResponse()?->header(['X-New-Token' => $newToken]);
        }

        return compact('token', 'adminInfo', 'jwt');
    }

    /**
     * 刷新 token（主动刷新接口调用）
     * @param string $token 当前 token
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function refreshToken(string $token): array
    {
        if (!$token) {
            throw new AdminAuthException('缺少token', 41002);
        }

        if (!$this->cacheTag()->get(md5($token))) {
            throw new AdminAuthException('TOKEN已过期', 41003);
        }

        $jwt = Jwt::decode($token);
        if (!$jwt || empty($jwt->id)) {
            throw new AdminAuthException('TOKEN无效', 41003);
        }

        $adminInfo = $this->get($jwt->id);
        if (!$adminInfo || !$adminInfo->status) {
            throw new AdminAuthException('账号已被禁用', 41004);
        }

        $newToken = $this->doRefreshToken($token, (int)$jwt->id);
        $timeout = config('app.token_timeout', 864000);

        return [
            'token' => $newToken,
            'expiresIn' => $timeout,
        ];
    }

    /**
     * 执行 token 替换：生成新 token，删除旧 token 缓存
     * @param string $oldToken
     * @param int $adminId
     * @return string
     */
    private function doRefreshToken(string $oldToken, int $adminId): string
    {
        $timeout = config('app.token_timeout', 864000);
        $newToken = $this->getModel()->setToken($adminId, 'admin');
        $this->cacheTag()->set(md5($newToken), $newToken, $timeout);
        $this->cacheTag()->delete(md5($oldToken));
        return $newToken;
    }

    /**
     * 验证权限
     * @param Request $request
     * @param array $adminInfo
     * @return true|void
     */
    public function checkRole(Request $request, array $adminInfo)
    {
        $method = Str::trimStrtolower($request->method());
        $rule = str_replace('api/admin/', '', trim(strtolower($request->rule()->getRule())));

        // 退出和当前管理员信息是认证生命周期接口，不能依赖角色菜单，否则子管理员无法退出或初始化。
        if (in_array($rule, [RuleEnum::ADMIN_LOGOUT_URL, RuleEnum::ADMIN_INFO_URL], true)) {
            return true;
        }

        // 获取管理员权限菜单
        /** @var MenuService $service */
        $service = app()->make(MenuService::class);
        $adminLevelMenus = $service->getAdminLevelMenus($adminInfo['roles']);

        // 验证权限
        if (collect($adminLevelMenus)->filter(function ($item) use ($rule, $method) {
            // 与上方 $rule 的剥离保持一致：先剥 api/admin 前缀，再兼容旧格式 api/ 前缀与尾段格式
            $item['url'] = str_replace(['api/admin/', '/api/admin/', 'api/', '/api/'], '', $item['url']);
            if ($item['url'][0] === '/') {
                $item['url'] = substr($item['url'], 1);
            }
            $urlRule = Str::trimStrtolower($rule) === Str::trimStrtolower(Str::getRuleUrlParam($item['url']));
            if ($item['auth_type'] === 2) {
                $urlRule = $urlRule && $method === $item['methods'];
            }
            if ($urlRule) {
                return true;
            }

            return false;
        })->isEmpty()) {
            throw new ValidateException('暂无权限访问');
        }

        // 配置管理特殊处理
        // 配置管理特殊处理：保存（POST）按已授权表单菜单 url 尾部数字限定可保存的配置 id。
        // 该接口 GET/POST 同规则，而 GET 读取配置没有 id，不能进入本分支，否则已授权的子管理员无法加载配置值。
        if (RuleEnum::CONFIG_SAVE_URL === $rule && $method === 'post') {
            $configId = $request->post('id/d', 0);
            if (!$configId) {
                throw new ValidateException('非法访问缺少参数');
            }
            if (collect($adminLevelMenus)->filter(function ($item) use ($configId) {
                $url = trim((string)$item['url']);
                // 统一补齐前导斜杠，兼容有无斜杠两种历史存储格式后再匹配表单路由前缀
                if ($url !== '' && $url[0] !== '/') {
                    $url = '/' . $url;
                }
                if (str_contains($url, RuleEnum::CONFIG_VIEW_URL_PREFIX)) {
                    // 提取 /setting/config/form/<id> 尾部数字作为该角色可保存的配置 id；
                    // 必须先去掉剩余部分的斜杠，(int)'/2' 会因前导非数字被强转为 0，导致校验永远失败
                    $suffix = trim(substr($url, strlen(RuleEnum::CONFIG_VIEW_URL_PREFIX)), '/');
                    return (int)$suffix === $configId;
                }

                return false;
            })->isEmpty()) {
                throw new ValidateException('暂无权限访问');
            }
        }
    }

    /**
     * 登录
     * @param string $account
     * @param string $password
     * @param string $key
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function login(string $account, string $password, string $key)
    {
        $timeout = config('app.token_timeout');
        $ttl = 3600;
        $num = $this->cacheTag()->get($key);
        if (!$num) {
            $num = 0;
        }

        // 只查询平台管理员
        $adminInfo = $this->getModel()->where('account', $account)
            ->where('platform_type', Admin::PLATFORM_TYPE_PLATFORM)
            ->field(['account', 'roles', 'password', 'real_name', 'last_ip', 'avatar', 'status', 'id', 'platform_type', 'user_id'])
            ->find();
        if (!$adminInfo) {
            $this->cacheTag()->set($key, $num + 1, $ttl);
            throw new ValidateException('账号或密码不正确');
        }

        if (!password_verify($password, $adminInfo->password)) {
            $this->cacheTag()->set($key, $num + 1, $ttl);
            throw new ValidateException('账号或密码不正确!');
        }

        if (!$adminInfo->status) {
            $this->cacheTag()->set($key, $num + 1, $ttl);
            throw new ValidateException('您的账号已被禁止登录');
        }

        $adminInfo->last_ip = request()->ip();
        $adminInfo->last_time = date('Y-m-d H:i:s');
        $adminInfo->save();

        $adminInfo = $adminInfo->hidden(['password', 'last_ip'])->toArray();

        $token = $this->getModel()->setToken((int)$adminInfo['id'], 'admin');
        $this->cacheTag()->set(md5($token), $token, config('app.token_timeout', 864000));

        $loginCaptcha = $this->cacheTag()->get($key) >= 3;

        $menus = app()->make(MenuService::class)->getMenusAll((array)$adminInfo['roles']);

        return [
            'adminInfo' => $adminInfo,
            'token' => $token,
            'expiresIn' => $timeout,
            'menus' => $menus,
            'login_captcha' => $loginCaptcha,
            'isLogin' => true,
            'tokenmd5' => md5($token)
        ];
    }
}
