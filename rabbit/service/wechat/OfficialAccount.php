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

use EasyWeChat\Kernel\HttpClient\Response;
use EasyWeChat\OfficialAccount\Application;
use Rabbit\service\wechat\message\MessageInterface;
use Rabbit\service\wechat\message\News;
use Rabbit\service\wechat\message\NewsItem;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use think\facade\Cache;
use think\Request;

/**
 *
 * Class OfficialAccount
 * @package Rabbit\service\wehcat
 * @property Application $app
 */
class OfficialAccount
{
    use WechatApplicationConfigTrait;

    /**
     * 微信授权 URL 基础地址。
     */
    private const OAUTH_BASE_URL = 'https://open.weixin.qq.com/connect/oauth2/authorize';

    /**
     * 配置
     * @var array|callable
     */
    protected mixed $config = [];

    /**
     * @var Application
     */
    protected $serve;

    /**
     * 中间件
     * @var string
     */
    protected string $withHandler;

    /**
     * OfficialAccount constructor.
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->name = 'wechat_official_account';
    }

    /**
     * 设置配置
     * @param array|callable $config
     * @return $this
     */
    public function setConfig(array|callable $config = [])
    {
        $this->config = $config;
        return $this;
    }


    /**
     * @param string $withHandler
     * @return $this
     */
    public function setWithHandler(string $withHandler)
    {
        $this->withHandler = $withHandler;
        return $this;
    }

    /**
     * 实例化
     */
    protected function app()
    {
        if ($this->serve) {
            return $this->serve;
        }

        $config = is_array($this->config) ? $this->config : call_user_func($this->config);

        try {
            $this->serve = new Application($config);
            $this->configureApplication($this->serve);
        } catch (\Throwable $e) {
            throw new WechatException($e->getMessage());
        }

        return $this->serve;
    }

    /**
     * 微信公众号服务
     * @param Request $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function serve(Request $request)
    {
        $server = $this->app()->getServer();
        $server->with($this->withHandler);
        return $server->serve();
    }

    /**
     * 创建自定义菜单
     * @param array $buttons
     * @param array $matchRule
     * @return WechatResponse
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function createMenu(array $buttons, array $matchRule = []): WechatResponse
    {
        if (!empty($matchRule)) {
            return $this->postJson('cgi-bin/menu/addconditional', [
                'button'    => $buttons,
                'matchrule' => $matchRule,
            ]);
        }

        return $this->postJson('cgi-bin/menu/create', ['button' => $buttons]);
    }

    /**
     * 发起post请求
     * @param string $url
     * @param array $data
     * @param array $options
     * @return WechatResponse
     * @throws TransportExceptionInterface
     */
    protected function postJson(string $url, array $data = [], array $options = [])
    {
        $response = $this->app()->getClient()->postJson($url, $data, $options);

        return new WechatResponse($response);
    }

    /**
     * 发起GET请求
     * @param string $url
     * @param array $query
     * @return array
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    protected function getRequest(string $url, array $query = []): array
    {
        $response = $this->app()->getClient()->get($url, ['query' => $query]);
        $result = (new WechatResponse($response))->getResponse();

        return $result;
    }

    // =========================================================
    // 客服消息
    // =========================================================

    /**
     * 发送客服消息（文本/图片/图文等任意消息类型）
     * @param string $openid 接收用户 openid
     * @param MessageInterface $message 消息对象
     * @return WechatResponse
     * @throws TransportExceptionInterface
     */
    public function sendCustomMessage(string $openid, MessageInterface $message): WechatResponse
    {
        $data = array_merge(
            ['touser' => $openid],
            $message->transformForJsonRequest()
        );

        return $this->postJson('cgi-bin/message/custom/send', $data);
    }

    /**
     * 发送图文客服消息（外链图文 news 类型）
     * @param string $openid 接收用户 openid
     * @param array<array{title:string,description:string,url:string,picurl:string}> $articles 图文列表
     * @return WechatResponse
     * @throws TransportExceptionInterface
     */
    public function sendNewsMessage(string $openid, array $articles): WechatResponse
    {
        $items = [];
        foreach ($articles as $article) {
            $item = new NewsItem([
                'title'       => $article['title'] ?? '',
                'description' => $article['description'] ?? '',
                'url'         => $article['url'] ?? '',
                'image'       => $article['picurl'] ?? $article['image'] ?? '',
            ]);
            $items[] = $item;
        }

        $news = new News($items);

        return $this->sendCustomMessage($openid, $news);
    }

    /**
     * 上传图文素材（公众号图文 mpnews，可用于群发）
     * @param array<array{thumb_media_id:string,title:string,content:string,author:string,digest:string,content_source_url:string,show_cover_pic:int}> $articles
     * @return WechatResponse
     * @throws TransportExceptionInterface
     */
    public function uploadArticle(array $articles): WechatResponse
    {
        return $this->postJson('cgi-bin/media/uploadnews', ['articles' => $articles]);
    }

    /**
     * 群发消息给全部用户
     * @param MessageInterface $message 消息对象（建议使用 mpnews/text/voice/image/video）
     * @param bool $isToAll 是否发给所有用户（false 则只发给有tagid的用户）
     * @return WechatResponse
     * @throws TransportExceptionInterface
     */
    public function broadcastMessage(MessageInterface $message, bool $isToAll = true): WechatResponse
    {
        $data = array_merge(
            [
                'filter' => [
                    'is_to_all' => $isToAll,
                ],
                'send_ignore_reprint' => 0,
            ],
            $message->transformForJsonRequest()
        );

        return $this->postJson('cgi-bin/message/mass/sendall', $data);
    }

    /**
     * 群发消息给指定 tag 下的用户
     * @param int $tagId 标签 ID
     * @param MessageInterface $message 消息对象
     * @return WechatResponse
     * @throws TransportExceptionInterface
     */
    public function broadcastMessageByTag(int $tagId, MessageInterface $message): WechatResponse
    {
        $data = array_merge(
            [
                'filter' => [
                    'is_to_all' => false,
                    'tag_id'    => $tagId,
                ],
                'send_ignore_reprint' => 0,
            ],
            $message->transformForJsonRequest()
        );

        return $this->postJson('cgi-bin/message/mass/sendall', $data);
    }

    /**
     * 预览群发消息（发送给指定 openid，用于开发调试）
     * @param string $openid 接收预览的用户 openid
     * @param MessageInterface $message 消息对象
     * @return WechatResponse
     * @throws TransportExceptionInterface
     */
    public function previewMessage(string $openid, MessageInterface $message): WechatResponse
    {
        $data = array_merge(
            ['touser' => $openid],
            $message->transformForJsonRequest()
        );

        return $this->postJson('cgi-bin/message/mass/preview', $data);
    }

    /**
     * 删除群发消息
     * @param int $msgId 群发消息的 msg_id
     * @return WechatResponse
     * @throws TransportExceptionInterface
     */
    public function deleteBroadcastMessage(int $msgId): WechatResponse
    {
        return $this->postJson('cgi-bin/message/mass/delete', ['msg_id' => $msgId]);
    }

    /**
     * 查询群发消息发送状态
     * @param int $msgId 群发消息的 msg_id
     * @return array
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function getBroadcastMessageStatus(int $msgId): array
    {
        return $this->getRequest('cgi-bin/message/mass/get', ['msg_id' => $msgId]);
    }

    // =========================================================
    // 模板消息
    // =========================================================

    /**
     * 发送模板消息
     * @param string $openid 接收用户 openid
     * @param string $templateId 模板 ID
     * @param string $url 模板跳转链接（可为空）
     * @param array $data 模板数据，格式：['key' => ['value' => '内容', 'color' => '#173177']]
     * @param string $miniAppId 跳转小程序的 appid（可选）
     * @param string $miniPagePath 跳转小程序的页面路径（可选）
     * @return WechatResponse
     * @throws TransportExceptionInterface
     */
    public function sendTemplateMessage(
        string $openid,
        string $templateId,
        string $url,
        array $data,
        string $miniAppId = '',
        string $miniPagePath = ''
    ): WechatResponse {
        $params = [
            'touser'      => $openid,
            'template_id' => $templateId,
            'url'         => $url,
            'data'        => $data,
        ];

        if (!empty($miniAppId)) {
            $params['miniprogram'] = [
                'appid'    => $miniAppId,
                'pagepath' => $miniPagePath,
            ];
        }

        return $this->postJson('cgi-bin/message/template/send', $params);
    }

    /**
     * 获取已添加的模板列表
     * @return array
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function getTemplateList(): array
    {
        return $this->getRequest('cgi-bin/template/get_all_private_template');
    }

    /**
     * 从行业模板库中选取模板添加到账号
     * @param string $templateIdShort 模板库中模板的编号（短 ID）
     * @return WechatResponse
     * @throws TransportExceptionInterface
     */
    public function addTemplate(string $templateIdShort): WechatResponse
    {
        return $this->postJson('cgi-bin/template/api_add_template', [
            'template_id_short' => $templateIdShort,
        ]);
    }

    /**
     * 删除模板
     * @param string $templateId 要删除的模板 ID
     * @return WechatResponse
     * @throws TransportExceptionInterface
     */
    public function deleteTemplate(string $templateId): WechatResponse
    {
        return $this->postJson('cgi-bin/template/del_private_template', [
            'template_id' => $templateId,
        ]);
    }

    /**
     * 设置所属行业（影响模板消息的可选模板库）
     * @param int $industryId1 公众号行业类别 ID（主行业）
     * @param int $industryId2 公众号行业类别 ID（副行业）
     * @return WechatResponse
     * @throws TransportExceptionInterface
     */
    public function setIndustry(int $industryId1, int $industryId2): WechatResponse
    {
        return $this->postJson('cgi-bin/template/api_set_industry', [
            'industry_id1' => $industryId1,
            'industry_id2' => $industryId2,
        ]);
    }

    /**
     * 获取设置的行业信息
     * @return array
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function getIndustry(): array
    {
        return $this->getRequest('cgi-bin/template/get_industry');
    }

    // =========================================================
    // 网页授权与 JS-SDK
    // =========================================================

    /**
     * 获取网页授权跳转 URL。
     *
     * @param string $redirectUri 授权后重定向的回调地址。
     * @param string $scope 授权作用域：snsapi_base 或 snsapi_userinfo。
     * @param string $state 附带参数。
     * @return string
     */
    public function getOAuthRedirectUrl(
        string $redirectUri,
        string $scope = 'snsapi_base',
        string $state = ''
    ): string {
        $config = is_array($this->config) ? $this->config : call_user_func($this->config);
        $appId = $config['app_id'] ?? '';

        $params = http_build_query([
            'appid' => $appId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => $scope,
            'state' => $state,
        ]);

        return self::OAUTH_BASE_URL . '?' . $params . '#wechat_redirect';
    }

    /**
     * 通过 code 换取网页授权 access_token 及 openid。
     *
     * @param string $code 微信回调携带的 code。
     * @return array
     * @throws WechatException
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function getOAuthAccessToken(string $code): array
    {
        $config = is_array($this->config) ? $this->config : call_user_func($this->config);

        $response = $this->app()->getClient()->get('sns/oauth2/access_token', [
            'query' => [
                'appid' => $config['app_id'] ?? '',
                'secret' => $config['secret'] ?? '',
                'code' => $code,
                'grant_type' => 'authorization_code',
            ],
        ]);

        $result = $response->toArray();
        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new WechatException($result['errmsg'] ?? 'OAuth access_token error', $result['errcode']);
        }

        return $result;
    }

    /**
     * 刷新网页授权 access_token。
     *
     * @param string $refreshToken 上次获取的 refresh_token。
     * @return array
     * @throws WechatException
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function refreshOAuthAccessToken(string $refreshToken): array
    {
        $config = is_array($this->config) ? $this->config : call_user_func($this->config);

        $response = $this->app()->getClient()->get('sns/oauth2/refresh_token', [
            'query' => [
                'appid' => $config['app_id'] ?? '',
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ],
        ]);

        $result = $response->toArray();
        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new WechatException($result['errmsg'] ?? 'OAuth refresh_token error', $result['errcode']);
        }

        return $result;
    }

    /**
     * 获取授权用户信息。
     *
     * @param string $accessToken 网页授权 access_token。
     * @param string $openid 用户 openid。
     * @param string $lang 返回语言版本。
     * @return array
     * @throws WechatException
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function getOAuthUserInfo(string $accessToken, string $openid, string $lang = 'zh_CN'): array
    {
        $response = $this->app()->getClient()->get('sns/userinfo', [
            'query' => [
                'access_token' => $accessToken,
                'openid' => $openid,
                'lang' => $lang,
            ],
        ]);

        $result = $response->toArray();
        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new WechatException($result['errmsg'] ?? 'OAuth get userinfo error', $result['errcode']);
        }

        return $result;
    }

    /**
     * 验证网页授权 access_token 是否有效。
     *
     * @param string $accessToken 网页授权 access_token。
     * @param string $openid 用户 openid。
     * @return bool
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function validateOAuthAccessToken(string $accessToken, string $openid): bool
    {
        $response = $this->app()->getClient()->get('sns/auth', [
            'query' => [
                'access_token' => $accessToken,
                'openid' => $openid,
            ],
        ]);

        $result = $response->toArray();

        return isset($result['errcode']) && $result['errcode'] === 0;
    }

    /**
     * 获取 JS-SDK 签名配置。
     *
     * @param string $url 当前页面地址，不含 # 及其后内容。
     * @param array $jsApiList 需要鉴权的 JS API 列表。
     * @param array $openTagList 需要鉴权的开放标签列表。
     * @param bool $debug 是否开启 JS-SDK 调试。
     * @return array
     * @throws WechatException
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function getJsSdkSignature(
        string $url,
        array $jsApiList = [],
        array $openTagList = [],
        bool $debug = false
    ): array {
        $config = is_array($this->config) ? $this->config : call_user_func($this->config);
        $appId = $config['app_id'] ?? '';
        $ticket = $this->getJsApiTicket();
        $timestamp = time();
        $nonceStr = $this->generateNonceStr();

        $string = 'jsapi_ticket=' . $ticket
            . '&noncestr=' . $nonceStr
            . '&timestamp=' . $timestamp
            . '&url=' . $url;

        return [
            'appId' => $appId,
            'timestamp' => $timestamp,
            'nonceStr' => $nonceStr,
            'signature' => sha1($string),
            'jsApiList' => array_values($jsApiList),
            'openTagList' => array_values($openTagList),
            'debug' => $debug,
            'url' => $url,
        ];
    }

    /**
     * 获取 jsapi_ticket。
     *
     * @return string
     * @throws WechatException
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    protected function getJsApiTicket(): string
    {
        $config = is_array($this->config) ? $this->config : call_user_func($this->config);
        $appId = (string) ($config['app_id'] ?? '');
        $cacheKey = 'wechat_jsapi_ticket:' . md5($appId ?: 'default');
        $cachedTicket = Cache::get($cacheKey);

        if (is_string($cachedTicket) && $cachedTicket !== '') {
            return $cachedTicket;
        }

        $response = $this->app()->getClient()->get('cgi-bin/ticket/getticket', [
            'query' => ['type' => 'jsapi'],
        ]);

        $result = $response->toArray();
        if (!isset($result['errcode']) || $result['errcode'] !== 0) {
            throw new WechatException($result['errmsg'] ?? 'Get jsapi_ticket error', $result['errcode'] ?? -1);
        }

        $ticket = (string) ($result['ticket'] ?? '');
        if ($ticket === '') {
            throw new WechatException('Get jsapi_ticket empty', $result['errcode'] ?? -1);
        }

        $ttl = max(300, (int) ($result['expires_in'] ?? 7200) - 300);
        Cache::set($cacheKey, $ticket, $ttl);

        return $ticket;
    }

    /**
     * 生成随机字符串。
     *
     * @param int $length 长度。
     * @return string
     */
    private function generateNonceStr(int $length = 16): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $str;
    }
}
