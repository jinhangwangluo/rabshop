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

namespace app\controller\merchant;

use app\BaseController;
use app\common\enum\ConfigKeyEnum;
use app\common\service\merchant\MerchantAuthService;
use think\annotation\Inject;
use think\Response;

/**
 * 商户登录控制器
 * Class Auth
 * @package app\controller\merchant
 */
class Auth extends BaseController
{
    #[Inject]
    protected MerchantAuthService $service;

    /**
     * 登录页面
     * @return Response
     */
    public function index(): Response
    {
        return $this->success([
            'login_logo'         => $this->normalizeImageConfigUrl(db_config(ConfigKeyEnum::LOGIN_LOGO, '')),
            'login_left_image'   => $this->normalizeImageConfigUrl(db_config(ConfigKeyEnum::LOGIN_IMAGE_GENERAL_PLATFORM, '')),
            'site_name'          => $this->normalizeConfigString(db_config(ConfigKeyEnum::SITE_NAME, '')),
            'copyright'          => $this->normalizeConfigString(db_config(ConfigKeyEnum::COPYRIGHT, '')),
            'keep_on_record'     => $this->normalizeConfigString(db_config(ConfigKeyEnum::KEEP_ON_RECORD, '')),
            'gov_keep_on_record' => $this->normalizeConfigString(db_config(ConfigKeyEnum::GOV_KEEP_ON_RECORD, '')),
        ]);
    }

    /**
     * 规范化配置字符串，避免数组等复杂类型参与字符串转换。
     */
    private function normalizeConfigString(mixed $value): string
    {
        if (is_string($value) || is_numeric($value)) {
            return (string)$value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return '';
    }

    /**
     * 规范化图片配置值，兼容字符串、单图对象和图片列表。
     */
    private function normalizeImageConfigValue(mixed $value): string
    {
        $stringValue = $this->normalizeConfigString($value);
        if ($stringValue !== '') {
            return $stringValue;
        }

        if (!is_array($value)) {
            return '';
        }

        foreach (['url', 'path', 'filePath', 'src', 'value'] as $key) {
            $candidate = $this->normalizeConfigString($value[$key] ?? '');
            if ($candidate !== '') {
                return $candidate;
            }
        }

        $first = reset($value);
        if ($first === false) {
            return '';
        }

        return $this->normalizeImageConfigValue($first);
    }

    /**
     * 解析图片配置 URL，空值时返回空字符串以保留前端默认图。
     */
    private function normalizeImageConfigUrl(mixed $value): string
    {
        $path = $this->normalizeImageConfigValue($value);

        return $path !== '' ? get_image_url($path) : '';
    }

    /**
     * 商户登录
     * @return Response
     */
    public function login(): Response
    {
        [$account, $password, $captchaVerification, $captchaType] = $this->request->postMore([
            ['account', ''],
            ['password', ''],
            ['captchaVerification', ''],
            ['captchaType', 'blockPuzzle']
        ], true);

        $key = md5($this->request->ip() . 'merchant' . $captchaType);

        // 验证验证码（如果提供了验证码）
        if ($captchaVerification && $captchaType) {
            //二次验证
            try {
                $service = aj_get_serevice($captchaType);
                $service->verificationByEncryptCode($captchaVerification);
                $this->service->cacheTag()->delete($key);
            } catch (\Exception $e) {
                return $this->fail($e->getMessage());
            }
        }

        // 登录失败次数限制的缓存键
        $key = md5($this->request->ip() . '_merchant_login');

        try {
            $result = $this->service->login($account, $password, $key);
            return $this->success('登录成功', $result);
        } catch (\Exception $e) {
            $failCount = $this->service->cacheTag()->get($key);
            return $this->success([
                'isLogin'       => false,
                'message'       => $e->getMessage(),
                'login_captcha' => $failCount >= 3
            ]);
        }
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
     * 退出登录
     * @return Response
     */
    public function logout(): Response
    {
        $authorization = $this->request->header('Authorization');
        if ($authorization) {
            $token = trim(ltrim($authorization, 'Bearer'));
            $this->service->logout($token);
        }

        return $this->success('退出成功');
    }

    /**
     * 获取当前登录商户管理员信息
     * @return Response
     */
    public function info(): Response
    {
        $adminInfo = $this->service->getCurrentAdminInfo();
        return $this->success($adminInfo);
    }
}
