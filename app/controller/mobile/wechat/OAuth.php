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

namespace app\controller\mobile\wechat;

use app\BaseController;
use app\common\enum\WechatJsSdkApiEnum;
use app\common\service\user\UserAuthService;
use Rabbit\service\wechat\OfficialAccount;
use think\annotation\Inject;

/**
 * 微信公众号网页授权控制器.
 */
class OAuth extends BaseController
{
    /**
     * 用户认证服务.
     *
     * @var UserAuthService
     */
    #[Inject]
    protected UserAuthService $authService;

    /**
     * 获取微信授权跳转地址.
     *
     * GET 参数：
     * - redirect_uri string 授权成功后的前端回跳地址。
     * - scope string 授权范围，支持 snsapi_base/snsapi_userinfo。
     * - spread_uid int 上级分销人 UID，可选。
     *
     * @return mixed 返回微信 OAuth 授权地址。
     */
    public function redirect(): mixed
    {
        $redirectUri = trim((string) $this->request->get('redirect_uri', ''));
        $scope = trim((string) $this->request->get('scope', 'snsapi_userinfo'));
        $spreadUid = (int) $this->request->get('spread_uid', 0);

        if ($redirectUri === '') {
            return $this->fail('缺少 redirect_uri 参数');
        }

        $redirectUri = $this->normalizeRedirectUri($redirectUri);
        if ($redirectUri === '') {
            return $this->fail('redirect_uri 参数不合法');
        }

        if (!in_array($scope, ['snsapi_base', 'snsapi_userinfo'], true)) {
            $scope = 'snsapi_userinfo';
        }

        $state = bin2hex(random_bytes(16));
        $this->storeOauthState($state, [
            'redirect_uri' => $redirectUri,
            'spread_uid' => $spreadUid,
        ]);

        $oauth = app()->make(OfficialAccount::class);
        $url = $oauth->getOAuthRedirectUrl($redirectUri, $scope, $state);

        return $this->success('获取成功', ['redirect_url' => $url]);
    }

    /**
     * 微信授权回调.
     *
     * GET 参数：
     * - code string 微信授权 code。
     * - state string 授权状态 token。
     *
     * @return mixed 授权成功后返回移动端登录结果。
     */
    public function callback(): mixed
    {
        $code = trim((string) $this->request->get('code', ''));
        $state = trim((string) $this->request->get('state', ''));

        if ($code === '') {
            return $this->fail('授权失败，未获取到 code');
        }

        if ($state === '') {
            return $this->fail('缺少 state 参数');
        }

        $savedData = $this->pullOauthState($state);
        if (!is_array($savedData) || empty($savedData['redirect_uri'])) {
            return $this->fail('state 无效，请重新授权');
        }

        $oauth = app()->make(OfficialAccount::class);
        $tokenData = $oauth->getOAuthAccessToken($code);
        $openid = (string) ($tokenData['openid'] ?? '');

        if ($openid === '') {
            return $this->fail('获取 openid 失败');
        }

        $userInfo = [];
        if (!empty($tokenData['access_token'])) {
            $userInfo = $this->getOAuthUserInfoSafely($oauth, (string) $tokenData['access_token'], $openid);
        }

        $result = $this->authService->loginByWechatOfficial($openid, $userInfo, (int)($savedData['spread_uid'] ?? 0));

        return $this->success('授权成功', $result);
    }

    /**
     * 获取微信 JS-SDK 鉴权配置.
     *
     * GET 参数：
     * - url string 当前页面 URL，不包含 hash。
     * - jsApiList array|string 需要鉴权的 JS API 列表。
     * - openTagList array|string 需要鉴权的开放标签列表。
     * - debug bool 是否开启调试。
     *
     * @return mixed 返回 JS-SDK 签名配置。
     */
    public function jsSdkConfig(): mixed
    {
        $url = $this->normalizeJsSdkUrl(trim((string) $this->request->get('url', '')));
        if ($url === '') {
            return $this->fail('缺少有效的 url 参数');
        }

        $jsApiList = $this->normalizeList(
            $this->request->get('jsApiList', null),
            WechatJsSdkApiEnum::defaultApiList()
        );
        $openTagList = $this->normalizeList($this->request->get('openTagList', null), []);
        $debug = $this->normalizeBoolean($this->request->get('debug', false));

        try {
            $oauth = app()->make(OfficialAccount::class);
            $config = $oauth->getJsSdkSignature($url, $jsApiList, $openTagList, $debug);
            $config['apiList'] = $jsApiList;

            return $this->success('获取成功', $config);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    /**
     * 规范化前端回跳地址.
     *
     * @param string $redirectUri 前端回跳地址。
     * @return string 合法回跳地址，非法时返回空字符串。
     */
    protected function normalizeRedirectUri(string $redirectUri): string
    {
        if (preg_match('/^https?:\/\//i', $redirectUri) && filter_var($redirectUri, FILTER_VALIDATE_URL)) {
            return $redirectUri;
        }

        if (str_starts_with($redirectUri, '/')) {
            return rtrim((string) $this->request->domain(), '/') . $redirectUri;
        }

        return '';
    }

    /**
     * 安全获取微信授权用户资料.
     *
     * @param OfficialAccount $oauth 微信公众号服务。
     * @param string $accessToken 网页授权 access_token。
     * @param string $openid 微信公众号 openid。
     * @return array 用户资料，获取失败时返回空数组。
     */
    protected function getOAuthUserInfoSafely(OfficialAccount $oauth, string $accessToken, string $openid): array
    {
        try {
            return $oauth->getOAuthUserInfo($accessToken, $openid);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * 规范化 JS-SDK 签名 URL.
     *
     * @param string $url 原始页面 URL。
     * @return string 去除 hash 后的合法 URL。
     */
    protected function normalizeJsSdkUrl(string $url): string
    {
        $url = trim(explode('#', $url, 2)[0]);
        if (!preg_match('/^https?:\/\//i', $url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }

        return $url;
    }

    /**
     * 规范化前端传入的列表参数.
     *
     * @param mixed $value 原始列表，支持数组、JSON 字符串或逗号分隔字符串。
     * @param array $default 默认列表。
     * @return array 规范化后的列表。
     */
    protected function normalizeList(mixed $value, array $default): array
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : explode(',', $value);
        }

        if (!is_array($value)) {
            return $default;
        }

        $list = array_values(array_filter(array_map(static function (mixed $item): string {
            return trim((string) $item);
        }, $value)));

        return $list === [] ? $default : $list;
    }

    /**
     * 规范化布尔参数.
     *
     * @param mixed $value 原始布尔值。
     * @return bool 规范化后的布尔值。
     */
    protected function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * 存储 OAuth state 数据.
     *
     * @param string $state OAuth state。
     * @param array $data 需要临时保存的数据。
     * @return void
     */
    protected function storeOauthState(string $state, array $data): void
    {
        $filePath = $this->getOauthStateFilePath($state);
        $directory = dirname($filePath);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($filePath, json_encode([
            'expire_time' => time() + 300,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * 读取并删除 OAuth state 数据.
     *
     * @param string $state OAuth state。
     * @return array state 关联数据。
     */
    protected function pullOauthState(string $state): array
    {
        $filePath = $this->getOauthStateFilePath($state);
        if (!is_file($filePath)) {
            return [];
        }

        $content = file_get_contents($filePath);
        unlink($filePath);

        $payload = json_decode((string) $content, true);
        if (!is_array($payload)) {
            return [];
        }

        if ((int) ($payload['expire_time'] ?? 0) < time()) {
            return [];
        }

        return is_array($payload['data'] ?? null) ? $payload['data'] : [];
    }

    /**
     * 获取 OAuth state 文件路径.
     *
     * @param string $state OAuth state。
     * @return string state 缓存文件路径。
     */
    protected function getOauthStateFilePath(string $state): string
    {
        $safeState = preg_replace('/[^a-f0-9]/i', '', $state) ?: md5($state);

        return app()->getRuntimePath() . 'wechat_oauth_state' . DIRECTORY_SEPARATOR . $safeState . '.json';
    }
}
