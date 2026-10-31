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

namespace Rabbit\service\wechat;

use app\common\enum\WechatConfigKeyEnum;
use EasyWeChat\MiniApp\Application;
use think\facade\Cache;
use think\facade\Log;

class MiniProgram
{
    use WechatApplicationConfigTrait;

    protected $app;

    public function __construct()
    {
        $config = $this->getConfig();
        if ($config['app_id'] === '' || $config['secret'] === '') {
            throw new \RuntimeException('微信小程序 AppID 或 AppSecret 未配置');
        }

        $this->name = 'wechat_miniapp';
        $this->app = new Application([
            'app_id' => $config['app_id'],
            'secret' => $config['secret'],
            'response_type' => 'array',
            'log' => [
                'level' => 'debug',
                'file' => runtime_path() . 'log/wechat.log',
            ],
        ]);
        $this->configureApplication($this->app);
    }

    /**
     * 获取小程序运行时配置。
     *
     * @return array{app_id:string,secret:string}
     */
    private function getConfig(): array
    {
        return [
            'app_id' => trim((string) db_config(WechatConfigKeyEnum::MINI_APPID, '')),
            'secret' => trim((string) db_config(WechatConfigKeyEnum::MINI_SECRET, '')),
        ];
    }

    /**
     * 获取当前配置的小程序 AppID。
     *
     * @return string
     */
    public function getAppId(): string
    {
        return (string) $this->app->getConfig()->get('app_id', '');
    }

    /**
     * code2session: 获取 openid 和 session_key
     * @param string $code wx.login 返回的 code
     * @return array ['openid' => '', 'session_key' => '', 'unionid' => '']
     * @throws \Exception
     */
    public function code2session(string $code): array
    {
        $utils = $this->app->getUtils();
        $result = $utils->codeToSession($code);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            Log::error('微信小程序 code2session 失败: ' . json_encode($result));
            $message = $this->formatCode2SessionError((int) $result['errcode'], (string) ($result['errmsg'] ?? '未知错误'));
            throw new \Exception($message, 500);
        }

        // 存储 session_key 到 Redis，有效期 2 小时
        if (isset($result['openid']) && isset($result['session_key'])) {
            $key = "miniapp_session:{$result['openid']}";
            Cache::set($key, $result['session_key'], 7200);
        }

        return [
            'openid' => $result['openid'] ?? '',
            'session_key' => $result['session_key'] ?? '',
            'unionid' => $result['unionid'] ?? '',
        ];
    }

    /**
     * 格式化 code2Session 错误，保留微信原始错误并补充可操作诊断。
     */
    private function formatCode2SessionError(int $errcode, string $errmsg): string
    {
        if ($errcode === 40029) {
            return '微信登录失败：code 无效，请确认小程序 AppSecret 与 AppID 匹配，并避免重复使用或过期 code';
        }

        if ($errcode === 41002) {
            return '微信登录失败：小程序 AppID 未正确传递，请检查服务端小程序配置';
        }

        return '微信登录失败: ' . $errmsg;
    }

    /**
     * 解密用户信息或手机号
     * @param string $sessionKey
     * @param string $iv
     * @param string $encryptedData
     * @return array
     * @throws \Exception
     */
    public function decryptData(string $sessionKey, string $iv, string $encryptedData): array
    {
        $decrypted = $this->app->getCrypt()->decryptData($sessionKey, $iv, $encryptedData);
        if ($decrypted === false) {
            throw new \Exception('解密失败', 400);
        }
        return $decrypted;
    }

    /**
     * 获取用户手机号
     * @param string $code 手机号获取 code
     * @return array
     * @throws \Exception
     */
    public function getPhoneNumber(string $code): array
    {
        $client = $this->app->getClient();
        $response = $client->postJson('/wxa/business/getuserphonenumber', ['code' => $code]);
        $data = $response->toArray(false);

        if (!is_array($data) || !isset($data['errcode']) || $data['errcode'] !== 0) {
            $message = is_array($data) ? (string) ($data['errmsg'] ?? '未知错误') : '未知错误';
            throw new \Exception('获取手机号失败: ' . $message, 500);
        }

        return $data['phone_info'] ?? [];
    }

    /**
     * 添加小程序订阅消息模板。
     *
     * @param string $tid 订阅模板标题 ID。
     * @param array $kidList 关键词 ID 列表。
     * @param string $sceneDesc 服务场景描述。
     * @return array 微信返回结果，包含 priTmplId。
     * @throws \Exception
     */
    public function addSubscribeTemplate(string $tid, array $kidList, string $sceneDesc = '消息通知'): array
    {
        $response = $this->app->getClient()->postJson('/wxaapi/newtmpl/addtemplate', [
            'tid' => $tid,
            'kidList' => array_values(array_map('intval', $kidList)),
            'sceneDesc' => $sceneDesc,
        ]);
        $data = $response->toArray(false);
        $this->assertWechatOk($data, '添加小程序订阅模板失败');

        return $data;
    }

    /**
     * 获取小程序账号已添加的订阅模板列表。
     *
     * @return array
     * @throws \Exception
     */
    public function getSubscribeTemplateList(): array
    {
        $response = $this->app->getClient()->get('/wxaapi/newtmpl/gettemplate');
        $data = $response->toArray(false);
        $this->assertWechatOk($data, '获取小程序订阅模板列表失败');

        return $data;
    }

    /**
     * 发送小程序订阅消息。
     *
     * @param string $openid 接收用户 openid。
     * @param string $templateId 模板 ID。
     * @param array $data 模板数据，格式：['thing1' => ['value' => '内容']]。
     * @param string $page 跳转页面。
     * @param string $miniprogramState 跳转小程序类型：developer/trial/formal。
     * @param string $lang 语言。
     * @return array
     * @throws \Exception
     */
    public function sendSubscribeMessage(
        string $openid,
        string $templateId,
        array $data,
        string $page = '',
        string $miniprogramState = 'formal',
        string $lang = 'zh_CN'
    ): array {
        $payload = [
            'touser' => $openid,
            'template_id' => $templateId,
            'data' => $data,
            'miniprogram_state' => $miniprogramState,
            'lang' => $lang,
        ];
        if ($page !== '') {
            $payload['page'] = ltrim($page, '/');
        }

        $response = $this->app->getClient()->postJson('/cgi-bin/message/subscribe/send', $payload);
        $result = $response->toArray(false);
        $this->assertWechatOk($result, '发送小程序订阅消息失败');

        return $result;
    }

    /**
     * 获取不限制数量的小程序码二进制内容。
     *
     * @param string $scene 小程序码 scene，最大 32 个可见字符。
     * @param string $page 小程序页面路径，不带开头斜杠。
     * @param int $width 二维码宽度，单位 px。
     * @param bool $checkPath 是否检查页面路径。
     * @return string 小程序码图片二进制。
     * @throws \Exception
     */
    public function getUnlimitedQrcode(
        string $scene,
        string $page = 'pages/index/index',
        int $width = 430,
        bool $checkPath = false
    ): string {
        $scene = trim($scene);
        $page = ltrim(trim($page), '/');
        if ($scene === '') {
            throw new \Exception('小程序码 scene 不能为空', 400);
        }

        $response = $this->app->getClient()->postJson('/wxa/getwxacodeunlimit', [
            'scene' => $scene,
            'page' => $page,
            'check_path' => $checkPath,
            'width' => max(280, min(1280, $width)),
        ]);

        $content = $response->getContent(false);
        $contentType = strtolower($response->getHeaderLine('content-type', false));
        if ($content === '') {
            throw new \Exception('获取小程序码失败: 微信返回为空', 500);
        }

        if (str_contains($contentType, 'json') || str_starts_with(trim($content), '{')) {
            $data = json_decode($content, true) ?: [];
            $message = (string)($data['errmsg'] ?? '未知错误');
            throw new \Exception('获取小程序码失败: ' . $message, 500);
        }

        return $content;
    }

    /**
     * 校验微信接口返回。
     *
     * @throws \Exception
     */
    private function assertWechatOk(array $data, string $prefix): void
    {
        if (!isset($data['errcode']) || (int)$data['errcode'] === 0) {
            return;
        }

        throw new \Exception($prefix . ': ' . (string)($data['errmsg'] ?? '未知错误'), 500);
    }
}
