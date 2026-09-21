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

namespace app\controller\admin;

use app\common\enum\ConfigKeyEnum;
use app\common\service\setting\AdminService;
use Rabbit\service\websocket\TokenHelper;
use think\annotation\Inject;
use think\Response;

/**
 * 登录验证控制器
 * Class Login
 * @package app\admin\controller
 */
class Login extends AuthController
{

    #[Inject]
    protected AdminService $service;

    /**
     * 登录页面
     * @return Response
     */
    public function index()
    {
        return $this->success([
            'login_logo'       => db_config(ConfigKeyEnum::LOGIN_LOGO),
            'login_left_image' => db_config(ConfigKeyEnum::LOGIN_IMAGE_GENERAL_PLATFORM),
            'site_name'        => db_config(ConfigKeyEnum::SITE_NAME)
        ]);
    }

    /**
     * 获取验证码
     * @return Response
     */
    public function ajcaptcha()
    {
        try {
            $service = aj_get_serevice();
            $data = $service->get();
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), ['file' => $e->getFile(), 'code' => $e->getLine()]);
        }
        return $this->success($data);
    }

    /**
     * 一次验证
     * @return mixed
     */
    public function ajcheck()
    {
        [$token, $pointJson, $captchaType] = $this->request->postMore([
            ['token', ''],
            ['pointJson', ''],
            ['captchaType', ''],
        ], true);
        try {
            aj_captcha_check_one($captchaType, $token, $pointJson);
            return $this->success();
        } catch (\Throwable) {
            return $this->fail('验证失败');
        }
    }

    /**
     * 登录
     * @return mixed
     */
    public function login()
    {
        [$account, $password, $captchaVerification, $captchaType] = $this->request->postMore([
            ['account', ''],
            ['password', ''],
            ['captchaVerification', ''],
            ['captchaType', 'blockPuzzle']
        ], true);

        $key = md5($this->request->ip() . $captchaType);

        if ($this->service->cacheTag()->get($key) >= 3) {
            if (!$captchaVerification) {
                return $this->success([
                    'isLogin'       => false,
                    'message'       => '请先滑动验证码',
                    'login_captcha' => true
                ]);
            }
            //二次验证
            try {
                $service = aj_get_serevice($captchaType);
                $service->verificationByEncryptCode($captchaVerification);
                $this->service->cacheTag()->delete($key);
            } catch (\Exception $e) {
                return $this->fail($e->getMessage());
            }
        }

        try {
            return $this->success($this->service->login($account, $password, $key));
        } catch (\Exception $e) {
            return $this->success([
                'isLogin'       => false,
                'message'       => $e->getMessage(),
                'login_captcha' => $this->service->cacheTag()->get($key) >= 3
            ]);
        }
    }

    /**
     * 退出登录
     * @return mixed
     */
    public function logout()
    {
        $this->service->cacheTag()->delete(md5($this->token));
        TokenHelper::revokePlatformToken((int)$this->adminId);

        return $this->success('退出成功');
    }

    /**
     * 获取当前登录管理员信息
     * @return Response
     */
    public function getInfo(): Response
    {
        return $this->success($this->adminInfo);
    }

    /**
     * 刷新 token（主动调用，无感刷新备用）
     * @return Response
     */
    public function refreshToken(): Response
    {
        $token = trim(ltrim($this->request->header('Authorization', ''), 'Bearer'));
        try {
            $result = $this->service->refreshToken($token);
            return $this->success($result);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }
    }
}
