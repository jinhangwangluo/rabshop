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

namespace app\controller\mobile;

use app\BaseController;
use Rabbit\service\SmsClickCaptchaService;
use app\common\service\sms\SmsService;
use app\common\service\user\UserAuthService;
use app\common\service\user\UserService;
use Random\RandomException;
use think\annotation\Inject;
use think\Response;

/**
 * 移动端认证控制器.
 */
class Auth extends BaseController
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
     * 短信服务.
     *
     * @var SmsService
     */
    #[Inject]
    protected SmsService $smsService;

    /**
     * 获取短信点击验证场景.
     *
     * POST 参数：
     * - phone string 手机号。
     *
     * @param SmsClickCaptchaService $smsClickCaptchaService
     * @return Response 返回点击验证场景数据。
     * @throws RandomException
     */
    public function smsCaptchaScene(SmsClickCaptchaService $smsClickCaptchaService): Response
    {
        $phone = trim((string) $this->request->post('phone', ''));
        if (!is_valid_phone($phone)) {
            return $this->fail('请输入正确的手机号');
        }

        $result = $smsClickCaptchaService->createScene($phone);

        return $this->success('获取成功', $result);
    }

    /**
     * 校验短信点击验证并发送验证码.
     *
     * POST 参数：
     * - phone string 手机号。
     * - scene_token string 点击验证场景 token。
     * - selected_points array 用户点击坐标数据。
     *
     * @param SmsClickCaptchaService $smsClickCaptchaService
     * @return Response 返回验证码发送结果。
     * @throws \Exception
     */
    public function smsCaptchaVerifySend(SmsClickCaptchaService $smsClickCaptchaService): Response
    {
        $phone = trim((string) $this->request->post('phone', ''));
        $sceneToken = trim((string) $this->request->post('scene_token', ''));
        $selectedPoints = $this->request->post('selected_points/a', []);

        if (!is_valid_phone($phone)) {
            return $this->fail('请输入正确的手机号');
        }

        if ($sceneToken === '') {
            return $this->fail('验证场景不存在，请刷新后重试');
        }

        if (!is_array($selectedPoints) || $selectedPoints === []) {
            return $this->fail('请先完成安全验证');
        }

        $result = $smsClickCaptchaService->verifyAndSendCode($phone, $sceneToken, $selectedPoints);

        return $this->success('验证码发送成功', $result);
    }

    /**
     * 手机验证码登录.
     *
     * POST 参数：
     * - phone string 手机号。
     * - code string 短信验证码。
     * - spread_uid int 上级分销人 UID，可选。
     *
     * @return Response 返回登录 token 和用户信息。
     * @throws \Exception
     */
    public function phoneLogin(): Response
    {
        $phone = (string)$this->request->post('phone', '');
        $code = (string)$this->request->post('code', '');
        $spreadUid = (int)$this->request->post('spread_uid', 0);

        if (!is_valid_phone($phone)) {
            return $this->fail('请输入正确的手机号');
        }

        if (!preg_match('/^\d{4,6}$/', $code)) {
            return $this->fail('请输入正确的验证码');
        }

        $result = $this->authService->loginByPhone($phone, $code, $spreadUid);

        return $this->success('登录成功', $result);
    }

    /**
     * 账号密码登录.
     *
     * POST 参数：
     * - account string 登录账号；兼容 phone。
     * - password string 登录密码；兼容 pwd。
     * - spread_uid int 上级分销人 UID，可选。
     *
     * @return Response 返回登录 token 和用户信息。
     */
    public function accountLogin(): Response
    {
        $account = (string)$this->request->post('account', '');
        if ($account === '') {
            $account = (string)$this->request->post('phone', '');
        }

        $password = (string)$this->request->post('password', '');
        if ($password === '') {
            $password = (string)$this->request->post('pwd', '');
        }

        if (!is_valid_phone($account)) {
            return $this->fail('请输入正确的账号');
        }

        if ($password === '') {
            return $this->fail('请输入登录密码');
        }

        $spreadUid = (int)$this->request->post('spread_uid', 0);
        $result = $this->authService->loginByAccount($account, $password, $spreadUid);

        return $this->success('登录成功', $result);
    }

    /**
     * 忘记密码重置.
     *
     * POST 参数：
     * - phone string 手机号。
     * - code string 短信验证码。
     * - password string 新密码，长度 6-20。
     *
     * @return Response 返回密码重置结果。
     * @throws \Exception
     */
    public function resetPassword(): Response
    {
        $phone = (string)$this->request->post('phone', '');
        $code = (string)$this->request->post('code', '');
        $password = (string)$this->request->post('password', '');

        if (!is_valid_phone($phone)) {
            return $this->fail('请输入正确的手机号');
        }

        if (!preg_match('/^\d{4,6}$/', $code)) {
            return $this->fail('请输入正确的验证码');
        }

        if (strlen($password) < 6 || strlen($password) > 20) {
            return $this->fail('密码长度为6-20个字符');
        }

        $result = $this->authService->resetPasswordByPhone($phone, $code, $password);

        return $this->success('密码重置成功', $result);
    }

    /**
     * 刷新移动端登录 token.
     *
     * 请求方式：POST auth/refreshToken
     *
     * 请求头：
     * - Authorization string Bearer token。
     *
     * 返回数据：
     * - token string 新 token。
     * - expiresIn int token 有效期秒数。
     *
     * @return Response 返回刷新后的 token 信息。
     */
    public function refreshToken(): Response
    {
        $authorization = trim((string)$this->request->header('Authorization', ''));
        $token = trim(str_ireplace('Bearer ', '', $authorization));
        if ($token === '') {
            return json(['code' => 401, 'msg' => '缺少token', 'data' => null]);
        }

        try {
            return $this->success('刷新成功', $this->authService->refreshToken($token));
        } catch (\Throwable $throwable) {
            return json(['code' => 401, 'msg' => $throwable->getMessage() ?: 'token刷新失败', 'data' => null]);
        }
    }

    /**
     * 获取当前登录用户信息.
     *
     * @return Response 返回当前用户基础信息。
     */
    public function me(): Response
    {
        $userId = (int)($this->request->user_id ?? 0);
        if ($userId <= 0) {
            return $this->fail('未获取到登录用户');
        }

        return $this->success('获取成功', $this->userService->getCurrentUserInfo($userId));
    }

    /**
     * 更新当前登录用户个人信息.
     *
     * POST 参数：
     * - nickname string 用户昵称。
     * - gender int 性别：0=未知，1=男，2=女。
     * - avatar string 头像地址。
     *
     * @return Response 返回更新后的用户信息。
     */
    public function update(): Response
    {
        $userId = (int)($this->request->user_id ?? 0);
        if ($userId <= 0) {
            return $this->fail('未获取到登录用户');
        }

        $data = [
            'nickname' => $this->request->post('nickname', ''),
            'gender' => $this->request->post('gender', $this->request->post('sex')),
            'avatar' => $this->request->post('avatar'),
        ];

        return $this->success('保存成功', $this->userService->updateMobileProfile($userId, $data));
    }

    /**
     * 绑定或修改当前登录用户手机号.
     *
     * POST 参数：
     * - phone string 新手机号。
     * - code string 短信验证码。
     *
     * @return Response 返回绑定后的用户信息。
     * @throws \Exception
     */
    public function bindPhone(): Response
    {
        $userId = (int)($this->request->user_id ?? 0);
        $phone = (string)$this->request->post('phone', '');
        $code = (string)$this->request->post('code', '');

        if ($userId <= 0) {
            return $this->fail('未获取到登录用户');
        }

        if (!is_valid_phone($phone)) {
            return $this->fail('请输入正确的手机号');
        }

        if (!preg_match('/^\d{4,6}$/', $code)) {
            return $this->fail('请输入正确的验证码');
        }

        $this->smsService->verifyCode($phone, $code);
        $result = $this->userService->bindPhone($userId, $phone);

        return $this->success('手机号修改成功', $result);
    }

    /**
     * 获取当前用户提现银行卡信息.
     *
     * @return Response 返回银行卡信息，未填写时 has_card 为 false。
     */
    public function bankCard(): Response
    {
        $userId = (int)($this->request->user_id ?? 0);
        if ($userId <= 0) {
            return $this->fail('未获取到登录用户');
        }

        return $this->success('获取成功', $this->userService->getBankCard($userId));
    }

    /**
     * 保存当前用户提现银行卡信息.
     *
     * POST 参数：
     * - holder_name string 持卡人姓名；兼容 holderName。
     * - card_no string 银行卡号；兼容 cardNo。
     * - bank_name string 开户银行；兼容 bankName。
     * - reserved_phone string 银行预留手机号；兼容 reservedPhone/phone。
     *
     * @return Response 返回保存后的银行卡信息。
     */
    public function saveBankCard(): Response
    {
        $userId = (int)($this->request->user_id ?? 0);
        if ($userId <= 0) {
            return $this->fail('未获取到登录用户');
        }

        $data = [
            'holder_name' => $this->request->post('holder_name', $this->request->post('holderName', '')),
            'card_no' => $this->request->post('card_no', $this->request->post('cardNo', '')),
            'bank_name' => $this->request->post('bank_name', $this->request->post('bankName', '')),
            'reserved_phone' => $this->request->post('reserved_phone', $this->request->post('reservedPhone', $this->request->post('phone', ''))),
        ];

        return $this->success('保存成功', $this->userService->saveBankCard($userId, $data));
    }

    /**
     * 退出登录.
     *
     * @return Response 返回退出结果。
     */
    public function logout(): Response
    {
        $this->authService->logout($this->request);

        return $this->success('退出成功');
    }
}
