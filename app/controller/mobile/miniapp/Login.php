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

namespace app\controller\mobile\miniapp;

use app\BaseController;
use app\common\service\user\UserAuthService;
use app\common\service\user\UserService;
use Rabbit\service\wechat\MiniProgram;
use think\annotation\Inject;

/**
 * 小程序登录控制器.
 */
class Login extends BaseController
{
    /**
     * 用户认证服务.
     *
     * @var UserAuthService
     */
    #[Inject]
    protected UserAuthService $authService;

    /**
     * 用户服务.
     *
     * @var UserService
     */
    #[Inject]
    protected UserService $userService;

    /**
     * 小程序登录.
     *
     * POST 参数：
     * - code string 小程序登录 code。
     * - userInfo array 小程序用户资料。
     * - spread_uid int 上级分销人 UID，可选。
     *
     * @return mixed 返回登录 token 和用户信息。
     */
    public function login(): mixed
    {
        $code = (string) $this->request->post('code', '');
        $userInfo = $this->request->post('userInfo', []);
        $spreadUid = (int) $this->request->post('spread_uid', 0);

        if ($code === '') {
            return $this->fail('缺少 code 参数');
        }

        try {
            $miniProgram = app()->make(MiniProgram::class);
            $serverAppId = $miniProgram->getAppId();

            if ($serverAppId === '') {
                return $this->fail('服务端小程序 AppID 未配置');
            }

            $sessionData = $miniProgram->code2session($code);

            $openid = (string) ($sessionData['openid'] ?? '');
            $sessionKey = (string) ($sessionData['session_key'] ?? '');

            if ($openid === '') {
                return $this->fail('获取 openid 失败');
            }

            $result = $this->authService->loginByWechatMini(
                $openid,
                is_array($userInfo) ? $userInfo : [],
                $sessionKey,
                $spreadUid
            );

            return $this->success('登录成功', $result);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    /**
     * 获取小程序手机号.
     *
     * POST 参数：
     * - code string 小程序手机号授权 code。
     *
     * @return mixed 返回微信手机号信息。
     */
    public function getPhoneNumber(): mixed
    {
        $code = (string) $this->request->post('code', '');

        if ($code === '') {
            return $this->fail('缺少 code 参数');
        }

        try {
            $miniProgram = app()->make(MiniProgram::class);
            $phoneInfo = $miniProgram->getPhoneNumber($code);

            return $this->success('获取成功', $phoneInfo);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    /**
     * 绑定小程序手机号.
     *
     * POST 参数：
     * - code string 小程序手机号授权 code。
     *
     * @return mixed 返回绑定结果和手机号信息。
     */
    public function bindPhone(): mixed
    {
        $userId = (int) ($this->request->user_id ?? 0);
        if ($userId <= 0) {
            return $this->fail('未获取到登录用户');
        }

        $code = (string) $this->request->post('code', '');
        if ($code === '') {
            return $this->fail('缺少 code 参数');
        }

        try {
            $miniProgram = app()->make(MiniProgram::class);
            $phoneInfo = $miniProgram->getPhoneNumber($code);
            $phone = trim((string) ($phoneInfo['purePhoneNumber'] ?? $phoneInfo['phoneNumber'] ?? ''));

            if (!$this->isValidPhone($phone)) {
                return $this->fail('获取到的手机号无效');
            }

            $result = $this->userService->bindPhone($userId, $phone, true);

            return $this->success('绑定成功', array_merge($result, [
                'phone' => $phone,
                'phone_info' => $phoneInfo,
            ]));
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    /**
     * 校验手机号格式.
     *
     * @param string $phone 手机号。
     * @return bool 是否为合法手机号。
     */
    protected function isValidPhone(string $phone): bool
    {
        return (bool) preg_match('/^1\d{10}$/', $phone);
    }
}
