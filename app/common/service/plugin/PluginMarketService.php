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

declare(strict_types=1);

namespace app\common\service\plugin;

use app\common\exception\AdminResponseException;
use app\common\model\plugin\Plugin;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use think\facade\Cache;
use think\Response;
use think\response\File;
use Throwable;

/**
 * 将本地逻辑路由代理为开放平台接口，负责参数白名单与响应整理，不承载市场权威数据。
 */
class PluginMarketService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    private ?PluginSecureGatewayClient $gatewayClient = null;

    // Base64 文件仍需嵌套 JWS/JWE，预留签名和信封编码开销。
    private const MAX_GATEWAY_FILE_BYTES = 6 * 1024 * 1024;

    /**
     * 指定本地插件模型，不以其替代远程市场数据。
     *
     * @return string 宿主插件模型完整类名。
     */
    protected function setModel(): string
    {
        return Plugin::class;
    }

    /**
     * 查询远程插件分类树并补充分类计数。
     *
     * @param array<string,mixed> $payload 分类查询参数；当前无额外筛选字段。
     * @return array<int,array<string,mixed>> 包含插件计数的分类节点列表。
     */
    public function catalogCategories(array $payload = []): array
    {
        $categories = $this->requestJson('GET', '/openapi/plugin-market/categories');
        return $this->appendCategoryCounts($categories);
    }

    /**
     * 查询分类、计费类型及排序条件下的市场插件。
     *
     * @param array<string,mixed> $payload 分类、关键词、排序、精选及分页筛选字段。
     * @return array<string,mixed> 归一化的插件分页数据及分类信息。
     */
    public function catalogList(array $payload = []): array
    {
        $params = [
            'category_id' => $this->normalizeUnsignedInt($payload['category_id'] ?? 0, 0, 0, null),
            'keyword' => $this->normalizeText($payload['keyword'] ?? '', 100),
            'sort' => $this->normalizeSort($payload['sort'] ?? 'default'),
            'page' => $this->normalizeUnsignedInt($payload['page'] ?? 1, 1, 1, 100000),
            'limit' => $this->normalizeUnsignedInt($payload['limit'] ?? 12, 12, 1, 48),
            'is_feature' => $this->normalizeUnsignedInt($payload['is_feature'] ?? 0, 0, 0, 1),
        ];
        $params['billing_type'] = $this->normalizeBillingType($payload['billing_type'] ?? '');
        $response = $this->requestJson('GET', '/openapi/plugin-market/list', $params);
        return $this->normalizePageResponse($response);
    }

    /**
     * 查询指定分类的精选插件并限制数量。
     *
     * @param array<string,mixed> $payload 分类和展示数量字段。
     * @return array<int,array<string,mixed>> 精选插件列表。
     */
    public function catalogFeatured(array $payload = []): array
    {
        $params = [
            'category_id' => $this->normalizeUnsignedInt($payload['category_id'] ?? 0, 0, 0, null),
            'limit' => $this->normalizeUnsignedInt($payload['limit'] ?? 12, 12, 1, 24),
        ];
        $response = $this->requestJson('GET', '/openapi/plugin-market/featured', $params);
        return $this->normalizeListResponse($response);
    }

    /**
     * 查询指定编码的远程市场插件详情。
     *
     * @param string $code 市场插件编码。
     * @return array<string,mixed> 远程插件详情字段。
     */
    public function catalogDetail(string $code): array
    {
        $pluginCode = $this->normalizePluginCode($code);
        return $this->requestJson('GET', '/openapi/plugin-market/detail/' . $pluginCode);
    }

    /**
     * 直连开放平台公开登录接口获取短信点击验证场景，不走加密网关。
     *
     * @param array<string,mixed> $payload 待验证的手机号字段。
     * @return array<string,mixed> 行为验证场景信息。
     */
    public function authSmsScene(array $payload = []): array
    {
        return $this->requestPublicJson('POST', '/openapi/auth/sms-captcha-scene', [
            'phone' => $this->normalizePhone($payload['phone'] ?? ''),
        ]);
    }

    /**
     * 提交点击场景或云验证凭证，行为验证通过后发送短信。
     *
     * @param array<string,mixed> $payload 手机号、自建场景点击结果或云通道验证凭证。
     * @return array<string,mixed> 短信发送和行为验证结果。
     */
    public function authSmsSend(array $payload = []): array
    {
        $params = [
            'phone' => $this->normalizePhone($payload['phone'] ?? ''),
        ];
        $sceneToken = $this->normalizeText($payload['scene_token'] ?? '', 128);
        if ($sceneToken !== '') {
            $params['scene_token'] = $sceneToken;
        }
        $points = $payload['selected_points'] ?? [];
        if (is_array($points)) {
            $params['selected_points'] = array_values(array_filter(
                $points,
                static fn(mixed $point): bool => is_numeric($point)
            ));
        }
        foreach (['ticket', 'randstr', 'biz_state'] as $key) {
            $value = $this->normalizeText($payload[$key] ?? '', 512);
            if ($value !== '') {
                $params[$key] = $value;
            }
        }
        return $this->requestPublicJson('POST', '/openapi/auth/sms-captcha-verify-send', $params);
    }

    /**
     * 验证码登录开放平台并绑定当前管理员，未注册账号按平台规则创建。
     *
     * @param array<string,mixed> $payload 手机号、短信验证码及可选推荐用户字段。
     * @return array<string,mixed> 开放平台登录结果。
     */
    public function phoneLogin(array $payload = []): array
    {
        $params = [
            'phone' => $this->normalizePhone($payload['phone'] ?? ''),
            'code' => $this->normalizeText($payload['code'] ?? '', 8),
        ];
        $spreadUid = (int)($payload['spread_uid'] ?? 0);
        if ($spreadUid > 0) {
            $params['spread_uid'] = $spreadUid;
        }
        return $this->bindOpenAuth($this->requestPublicJson('POST', '/openapi/auth/phone-login', $params));
    }

    /**
     * 使用手机账号密码登录开放平台并绑定当前管理员。
     *
     * @param array<string,mixed> $payload 账号、密码及可选推荐用户字段。
     * @return array<string,mixed> 开放平台登录结果。
     */
    public function accountLogin(array $payload = []): array
    {
        $params = [
            'account' => $this->normalizeText($payload['account'] ?? '', 32),
            'password' => $this->normalizeText($payload['password'] ?? '', 64),
        ];
        $spreadUid = (int)($payload['spread_uid'] ?? 0);
        if ($spreadUid > 0) {
            $params['spread_uid'] = $spreadUid;
        }
        return $this->bindOpenAuth($this->requestPublicJson('POST', '/openapi/auth/account-login', $params));
    }

    /**
     * 读取当前管理员绑定的开放平台登录状态。
     *
     * @return array<string,mixed> 登录标记、用户 ID 和公开用户资料。
     */
    public function openAuthInfo(): array
    {
        $context = $this->openAuthContext();
        $uid = (int)($context['uid'] ?? 0);
        return [
            'login' => $uid > 0,
            'uid' => $uid,
            'user' => $context['user'] ?? [],
        ];
    }

    /**
     * 解除当前管理员的开放平台登录绑定。
     *
     * @return array<string,mixed> 已退出登录的结果标记。
     */
    public function openAuthLogout(): array
    {
        Cache::delete($this->openAuthKey());
        return ['logout' => true];
    }

    /**
     * 读取当前管理员绑定的开放平台访问令牌。
     *
     * @return string 访问令牌；尚未登录时为空字符串。
     */
    public function openAuthToken(): string
    {
        return trim((string)($this->openAuthContext()['token'] ?? ''));
    }

    /**
     * 查询当前绑定开放平台用户的插件订单。
     *
     * @param array<string,mixed> $payload 预留查询参数；用户身份由当前管理员绑定决定。
     * @return array<int,array<string,mixed>> 当前用户的订单列表。
     */
    public function orders(array $payload = []): array
    {
        return $this->normalizeListResponse($this->requestJson('GET', '/openapi/plugin-market/orders', $this->withOpenUser()));
    }

    /**
     * 创建插件购买订单，用户身份不能由客户端指定。
     *
     * @param array<string,mixed> $payload 市场商品 ID 和 SKU ID。
     * @return array<string,mixed> 远程订单创建结果。
     */
    public function orderCreate(array $payload = []): array
    {
        $params = $this->withOpenUser([
            'product_id' => $this->normalizeUnsignedInt($payload['product_id'] ?? 0, 0, 1, null),
            'sku_id' => $this->normalizeUnsignedInt($payload['sku_id'] ?? 0, 0, 1, null),
        ]);
        return $this->requestJson('POST', '/openapi/plugin-market/order/create', $params);
    }

    /**
     * 按白名单支付方式支付当前用户的插件订单。
     *
     * @param array<string,mixed> $payload 订单号和余额、微信或支付宝支付方式。
     * @return array<string,mixed> 远程支付结果。
     */
    public function orderPay(array $payload = []): array
    {
        $params = $this->withOpenUser([
            'order_no' => $this->normalizeOrderNo($payload['order_no'] ?? ''),
            'pay_type' => $this->normalizePayType($payload['pay_type'] ?? 'balance'),
        ]);
        return $this->requestJson('POST', '/openapi/plugin-market/order/pay', $params);
    }

    /**
     * 按订单号与轮询渠道查询支付状态。
     *
     * @param array<string,mixed> $payload 订单号和可选微信或支付宝轮询渠道。
     * @return array<string,mixed> 远程订单支付状态。
     */
    public function orderStatus(array $payload = []): array
    {
        $params = $this->withOpenUser([
            'order_no' => $this->normalizeOrderNo($payload['order_no'] ?? ''),
            'pay_type' => $this->normalizePollType($payload['pay_type'] ?? ''),
        ]);
        return $this->requestJson('GET', '/openapi/plugin-market/order/status', $params);
    }

    /**
     * 取消当前绑定用户的指定插件订单。
     *
     * @param array<string,mixed> $payload 待取消订单号。
     * @return array<string,mixed> 远程订单取消结果。
     */
    public function orderCancel(array $payload = []): array
    {
        $params = $this->withOpenUser([
            'order_no' => $this->normalizeOrderNo($payload['order_no'] ?? ''),
        ]);
        return $this->requestJson('POST', '/openapi/plugin-market/order/cancel', $params);
    }

    /**
     * 查询当前绑定用户的插件授权。
     *
     * @param array<string,mixed> $payload 预留查询参数；当前仅使用已绑定用户身份。
     * @return array<int,array<string,mixed>> 当前用户的插件授权列表。
     */
    public function licenses(array $payload = []): array
    {
        return $this->normalizeListResponse($this->requestJson('GET', '/openapi/plugin-market/licenses', $this->withOpenUser()));
    }

    /**
     * 查询当前绑定用户可下载的插件。
     *
     * @param array<string,mixed> $payload 预留查询参数；当前仅使用已绑定用户身份。
     * @return array<int,array<string,mixed>> 可下载插件列表。
     */
    public function downloads(array $payload = []): array
    {
        return $this->normalizeListResponse($this->requestJson('GET', '/openapi/plugin-market/downloads', $this->withOpenUser()));
    }

    /**
     * 按当前用户授权申请一次性下载票据。
     *
     * @param array<string,mixed> $payload 插件授权记录 ID。
     * @return array<string,mixed> 远程签发的下载票据信息。
     */
    public function downloadTicket(array $payload = []): array
    {
        $params = $this->withOpenUser([
            'license_id' => $this->normalizeUnsignedInt($payload['license_id'] ?? 0, 0, 1, null),
        ]);
        return $this->requestJson('POST', '/openapi/plugin-market/download/ticket', $params);
    }

    /**
     * 通过加密接口获取下载内容并生成原始文件响应。
     *
     * @param array<string,mixed> $payload 已签发的下载票据。
     * @return Response 插件文件下载响应，不使用 JSON 成功包装。
     */
    public function download(array $payload = []): Response
    {
        $ticket = $this->normalizeDownloadTicket($payload['ticket'] ?? '');
        try {
            $result = $this->requestJson('GET', '/openapi/plugin-market/download', ['ticket' => $ticket]);
        } catch (AdminResponseException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new AdminResponseException('下载失败', 0, $exception);
        }
        $content = base64_decode((string)($result['content_base64'] ?? ''), true);
        if ($content === false || $content === '') {
            throw new AdminResponseException('下载内容无效');
        }
        $response = Response::create($content, File::class);
        $response->isContent(true)->mimeType((string)($result['mime_type'] ?? 'application/octet-stream'));
        $response->name((string)($result['name'] ?? 'plugin-download.zip'), false);
        return $response;
    }

    /**
     * 查询当前绑定开放平台用户的个人资料。
     *
     * @param array<string,mixed> $payload 预留查询参数；当前仅使用已绑定用户身份。
     * @return array<string,mixed> 远程个人中心资料。
     */
    public function userInfo(array $payload = []): array
    {
        return $this->requestJson('GET', '/openapi/plugin-market/user-info', $this->withOpenUser());
    }

    /**
     * 更新当前绑定开放平台用户的昵称。
     *
     * @param array<string,mixed> $payload 待保存的昵称字段。
     * @return array<string,mixed> 远程资料更新结果。
     */
    public function userUpdate(array $payload = []): array
    {
        $params = $this->withOpenUser([
            'nickname' => $this->normalizeText($payload['nickname'] ?? '', 32),
        ]);
        return $this->requestJson('POST', '/openapi/plugin-market/user-update', $params);
    }

    /**
     * 分页查询当前用户指定资产的收支流水。
     *
     * @param array<string,mixed> $payload 资产类型、收支类型与分页条件。
     * @return array<string,mixed> 远程资产流水分页结果。
     */
    public function assetLogs(array $payload = []): array
    {
        $params = $this->withOpenUser([
            'type' => $this->normalizeAssetType($payload['type'] ?? 'balance'),
            'change_type' => $this->normalizeChangeType($payload['change_type'] ?? ''),
            'page' => $this->normalizeUnsignedInt($payload['page'] ?? 1, 1, 1, 100000),
            'limit' => $this->normalizeUnsignedInt($payload['limit'] ?? 10, 10, 1, 50),
        ]);
        return $this->requestJson('GET', '/openapi/plugin-market/asset-logs', $params);
    }

    /**
     * 查询当前绑定开放平台用户余额。
     *
     * @return array<string,mixed> 远程余额字段。
     */
    public function balance(): array
    {
        return $this->requestJson('GET', '/openapi/plugin-market/balance', $this->withOpenUser());
    }

    /**
     * 查询插件发布可选分类。
     *
     * @return array<int,array<string,mixed>> 发布分类列表。
     */
    public function publishCategories(): array
    {
        return $this->normalizeListResponse($this->requestJson('GET', '/openapi/plugin-market/publish/categories'));
    }

    /**
     * 查询当前绑定用户发布的插件。
     *
     * @return array<int,array<string,mixed>> 当前用户的发布列表。
     */
    public function publishMyList(): array
    {
        return $this->normalizeListResponse($this->requestJson('GET', '/openapi/plugin-market/publish/my-list', $this->withOpenUser()));
    }

    /**
     * 验证编码文件描述并申请远程插件包解析。
     *
     * @param array<string,mixed> $payload 待解析插件文件的编码描述。
     * @return array<string,mixed> 远程插件包解析结果。
     */
    public function publishInspect(array $payload = []): array
    {
        $params = $this->withOpenUser($this->normalizeFileRequestPayload($payload));
        return $this->requestJson('POST', '/openapi/plugin-market/publish/inspect', $params);
    }

    /**
     * 按白名单提交发布资料及可选插件包，审核权威仍在开放平台。
     *
     * @param array<string,mixed> $payload 插件发布资料、计费规则和可选编码文件。
     * @return array<string,mixed> 远程发布受理结果。
     */
    public function publish(array $payload = []): array
    {
        $params = $this->withOpenUser($this->normalizePublishPayload($payload));
        return $this->requestJson('POST', '/openapi/plugin-market/publish', $params);
    }

    /**
     * 重新提交当前用户的指定插件审核。
     *
     * @param array<string,mixed> $payload 待重新审核的插件编码。
     * @return array<string,mixed> 远程审核提交结果。
     */
    public function publishSubmit(array $payload = []): array
    {
        $params = $this->withOpenUser([
            'code' => $this->normalizePluginCode($payload['code'] ?? ''),
        ]);
        return $this->requestJson('POST', '/openapi/plugin-market/publish/submit', $params);
    }

    /**
     * 修改当前用户发布插件的上下架状态。
     *
     * @param array<string,mixed> $payload 插件编码和目标上下架状态。
     * @return array<string,mixed> 远程上下架处理结果。
     */
    public function publishStatus(array $payload = []): array
    {
        $params = $this->withOpenUser([
            'code' => $this->normalizePluginCode($payload['code'] ?? ''),
            'status' => $this->normalizeUnsignedInt($payload['status'] ?? 1, 1, 0, 1),
        ]);
        return $this->requestJson('POST', '/openapi/plugin-market/publish/status', $params);
    }

    /**
     * 提交当前绑定用户的个人实名认证。
     *
     * @param array<string,mixed> $payload 真实姓名和身份证号。
     * @return array<string,mixed> 远程个人认证结果。
     */
    public function verifyPersonal(array $payload = []): array
    {
        $params = $this->withOpenUser([
            'name' => $this->normalizeText($payload['name'] ?? '', 50),
            'id_number' => $this->normalizeIdentityNumber($payload['id_number'] ?? ''),
        ]);
        return $this->requestJson('POST', '/openapi/plugin-market/verify/personal', $params);
    }

    /**
     * 提交当前绑定用户的企业实名认证。
     *
     * @param array<string,mixed> $payload 企业名称和统一社会信用代码。
     * @return array<string,mixed> 远程企业认证结果。
     */
    public function verifyEnterprise(array $payload = []): array
    {
        $params = $this->withOpenUser([
            'company_name' => $this->normalizeText($payload['company_name'] ?? '', 150),
            'credit_no' => $this->normalizeCreditCode($payload['credit_no'] ?? ''),
        ]);
        return $this->requestJson('POST', '/openapi/plugin-market/verify/enterprise', $params);
    }

    /**
     * 校验编码图片描述及大小，再代理远程图片上传。
     *
     * @param array<string,mixed> $payload 待上传图片的编码文件描述。
     * @return array<string,mixed> 远程图片上传结果。
     */
    public function uploadImage(array $payload = []): array
    {
        $params = $this->withOpenUser($this->normalizeFileRequestPayload($payload));
        $file = $params['file'] ?? null;
        if (!is_array($file)) {
            throw new AdminResponseException('图片文件无效');
        }
        $this->assertImageFile($file);
        return $this->requestJson('POST', '/openapi/plugin-market/upload/image', $params);
    }

    /**
     * 创建或更新指定构建的小程序上传任务。
     *
     * @param int|string $id 开放平台构建 ID。
     * @param array<string,mixed> $payload 上传模式、凭据、版本及任务状态字段。
     * @return array<string,mixed> 远程小程序上传任务结果。
     */
    public function mpUpload(int|string $id, array $payload = []): array
    {
        $buildId = $this->normalizeUnsignedInt($id, 0, 1, null);
        $params = $this->normalizeMpUploadPayload($payload);
        return $this->requestJson('POST', '/openapi/plugin-builds/' . $buildId . '/mp-upload', $params);
    }

    /**
     * 重试指定构建的小程序上传任务。
     *
     * @param int|string $id 开放平台构建 ID。
     * @param array<string,mixed> $payload 预留请求参数；当前重试接口不透传额外字段。
     * @return array<string,mixed> 远程重试受理结果。
     */
    public function mpUploadRetry(int|string $id, array $payload = []): array
    {
        $buildId = $this->normalizeUnsignedInt($id, 0, 1, null);
        return $this->requestJson('POST', '/openapi/plugin-builds/' . $buildId . '/mp-upload/retry', []);
    }

    /**
     * 获取指定小程序构建的制品下载地址。
     *
     * @param int|string $id 开放平台构建 ID。
     * @return array<string,mixed> 远程小程序制品地址信息。
     */
    public function mpArtifactUrl(int|string $id): array
    {
        $buildId = $this->normalizeUnsignedInt($id, 0, 1, null);
        return $this->requestJson('GET', '/openapi/plugin-builds/' . $buildId . '/mp-artifact-url');
    }

    /**
     * 直连没有加密映射的公开登录与验证码接口，并统一处理业务响应。
     *
     * @param string $method 公开接口的 HTTP 方法。
     * @param string $path 公开开放平台接口路径。
     * @param array<string,mixed> $body 已裁剪的请求 JSON 字段。
     * @return array<string,mixed> 成功响应中的业务数据字段。
     */
    private function requestPublicJson(string $method, string $path, array $body = []): array
    {
        $url = rtrim($this->resolveOpenApiBaseUrl(), '/') . '/' . ltrim($path, '/');
        $curl = curl_init($url);
        if ($curl === false) {
            throw new AdminResponseException('开放平台请求初始化失败');
        }
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
        ]);
        $raw = curl_exec($curl);
        if ($raw === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new AdminResponseException($error !== '' ? '开放平台请求失败：' . $error : '开放平台请求失败');
        }
        curl_close($curl);

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new AdminResponseException('开放平台响应无效');
        }
        if ((int)($decoded['status'] ?? 0) !== 200) {
            $message = trim((string)($decoded['message'] ?? '开放平台请求失败'));
            throw new AdminResponseException($message !== '' ? $message : '开放平台请求失败');
        }
        $data = $decoded['data'] ?? [];
        return is_array($data) ? $data : ['value' => $data];
    }

    /**
     * 优先使用本地开发配置，否则从插件网关地址推导公开接口基址。
     *
     * @return string 规范化的开放平台接口基址。
     */
    private function resolveOpenApiBaseUrl(): string
    {
        $base = trim((string)(getenv('PLUGIN_OPENAPI_BASE_URL') ?: ''));
        if ($base === '') {
            $endpoint = $this->resolveGatewayEndpoint();
            $parts = parse_url($endpoint);
            if (
                !is_array($parts)
                || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)
                || !isset($parts['host'])
            ) {
                throw new AdminResponseException('开放平台网关地址无效');
            }
            $base = strtolower((string)$parts['scheme']) . '://' . (string)$parts['host'];
            if (isset($parts['port'])) {
                $base .= ':' . (int)$parts['port'];
            }
        }
        return rtrim($base, '/');
    }

    /**
     * 按当前管理员 ID 生成隔离的开放平台登录缓存键。
     *
     * @return string 当前管理员的登录绑定缓存键。
     */
    private function openAuthKey(): string
    {
        return 'plugin_market_open_auth:' . (int)(request()->adminId ?? 0);
    }

    /**
     * 校验登录结果后按平台有效期缓存当前管理员的绑定身份。
     *
     * @param array<string,mixed> $result 开放平台返回的登录结果。
     * @return array<string,mixed> 原始有效登录结果。
     */
    private function bindOpenAuth(array $result): array
    {
        $user = is_array($result['user'] ?? null) ? $result['user'] : [];
        $uid = (int)($user['uid'] ?? $user['id'] ?? 0);
        $token = trim((string)($result['token'] ?? ''));
        if ($uid <= 0 || $token === '') {
            throw new AdminResponseException('开放平台登录响应无效');
        }
        // 缓存有效期跟随开放平台返回的登录有效期(秒);无返回时按 7 天兜底。
        $expiresIn = max(60, (int)($result['expiresIn'] ?? 604800));
        Cache::set($this->openAuthKey(), [
            'uid' => $uid,
            'token' => $token,
            'expires_in' => $expiresIn,
            'user' => $user,
        ], $expiresIn);
        return $result;
    }

    /**
     * 读取当前管理员的开放平台登录缓存。
     *
     * @return array<string,mixed> 已绑定用户与令牌信息；缓存无效时为空数组。
     */
    private function openAuthContext(): array
    {
        $value = Cache::get($this->openAuthKey());
        return is_array($value) ? $value : [];
    }

    /**
     * 读取当前绑定开放平台用户 ID。
     *
     * @return int 当前用户 ID；尚未绑定时为 0。
     */
    private function openUid(): int
    {
        return (int)($this->openAuthContext()['uid'] ?? 0);
    }

    /**
     * 只使用当前管理员绑定的用户身份，覆盖客户端 user_id。
     *
     * @param array<string,mixed> $params 已校验的业务参数。
     * @return array<string,mixed> 附加可信开放平台用户 ID 的参数。
     */
    private function withOpenUser(array $params = []): array
    {
        $uid = $this->openUid();
        if ($uid <= 0) {
            throw new AdminResponseException('请先登录开放平台账号');
        }
        $params['user_id'] = $uid;
        return $params;
    }

    /**
     * 规范化并验证大陆手机号格式。
     *
     * @param mixed $value 原始手机号字段。
     * @return string 已验证的手机号。
     */
    private function normalizePhone(mixed $value): string
    {
        $phone = $this->normalizeText($value, 20);
        if (preg_match('/^1[3-9]\d{9}$/', $phone) !== 1) {
            throw new AdminResponseException('请输入正确的手机号');
        }
        return $phone;
    }

    /**
     * 通过安全网关请求市场接口，先拦截业务失败再提取真实数据。
     *
     * @param string $method 逻辑接口 HTTP 方法。
     * @param string $path 远程开放平台逻辑路径。
     * @param array<string,mixed> $payload 已裁剪的业务请求字段。
     * @param ?string $idempotencyKey 可选操作幂等键。
     * @return array<string,mixed> 远程成功响应中的业务数据。
     */
    private function requestJson(string $method, string $path, array $payload = [], ?string $idempotencyKey = null): array
    {
        try {
            $response = $this->gatewayClient()->send($method, $path, $payload, $idempotencyKey);
        } catch (AdminResponseException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $message = trim($exception->getMessage());
            throw new AdminResponseException(
                $message !== '' ? '开发平台请求失败：' . $message : '开发平台请求失败',
                0,
                $exception
            );
        }

        // 远程网关返回空包时，继续向下解析只会把问题包装成假数据。
        if (!is_array($response) || $response === []) {
            throw new AdminResponseException('插件市场响应为空');
        }

        // 这里先拦截业务态错误，再决定是否继续拆 data。
        if (array_key_exists('status', $response) && (int)$response['status'] !== 200) {
            $message = trim((string)($response['message'] ?? '插件市场请求失败'));
            throw new AdminResponseException($message !== '' ? $message : '插件市场请求失败');
        }

        // 大多数市场接口都把真正数据包在 data 里，这里统一向外层收敛。
        if (array_key_exists('data', $response)) {
            $data = $response['data'];
            if (is_array($data)) {
                return $data;
            }
            return $data === null ? [] : ['value' => $data];
        }

        return $response;
    }

    /**
     * 统一远程分页结构，兼容旧版空结果页数和列表字段。
     *
     * @param array<string,mixed> $response 远程插件分页响应。
     * @return array<string,mixed> 列表、分页及分类字段一致的分页结果。
     */
    private function normalizePageResponse(array $response): array
    {
        $page = [
            'items' => $this->normalizeListResponse($response['items'] ?? $response['list'] ?? []),
            'total' => $this->normalizeUnsignedInt($response['total'] ?? 0, 0, 0, null),
            'page' => $this->normalizeUnsignedInt($response['page'] ?? 1, 1, 1, null),
            'limit' => $this->normalizeUnsignedInt($response['limit'] ?? 12, 12, 1, 48),
            // 兼容官网旧版本空结果 pages=0；非空结果仍必须有合法分页。
            'pages' => max(1, $this->normalizeUnsignedInt($response['pages'] ?? 1, 1, (int)($response['total'] ?? 0) === 0 ? 0 : 1, null)),
            'categories' => $this->normalizeListResponse($response['categories'] ?? []),
        ];

        if ($page['items'] === [] && isset($response['list']) && is_array($response['list'])) {
            $page['items'] = $this->normalizeListResponse($response['list']);
        }

        return $page;
    }

    /**
     * 从数组、items 或 list 结构中提取有效记录列表。
     *
     * @param array<int,mixed>|array<string,mixed> $response 远程列表或列表包装字段。
     * @return array<int,array<string,mixed>> 仅包含数组记录的顺序列表。
     */
    private function normalizeListResponse(array $response): array
    {
        if ($response === []) {
            return [];
        }
        $items = $response;
        if (array_key_exists('items', $response) && is_array($response['items'])) {
            $items = $response['items'];
        } elseif (array_key_exists('list', $response) && is_array($response['list'])) {
            $items = $response['list'];
        }

        $result = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $result[] = $item;
            }
        }
        return array_values($result);
    }

    /**
     * 为分类树补充插件数，并共享重复节点计数缓存。
     *
     * @param array<int,array<string,mixed>> $categories 远程分类节点列表。
     * @return array<int,array<string,mixed>> 补充插件数后的分类树。
     */
    private function appendCategoryCounts(array $categories): array
    {
        $counts = [];
        return array_values(array_map(function (array $category) use (&$counts): array {
            return $this->appendCategoryCount($category, $counts);
        }, $categories));
    }

    /**
     * 递归补充当前分类及子分类计数，重复节点不再请求远程接口。
     *
     * @param array<string,mixed> $category 当前分类节点。
     * @param array<int,int> $counts 按分类 ID 索引的本次计数缓存。
     * @return array<string,mixed> 包含插件计数的分类节点。
     */
    private function appendCategoryCount(array $category, array &$counts): array
    {
        $id = $this->normalizeUnsignedInt($category['id'] ?? 0, 0, 0, null);
        if ($id > 0) {
            // 同一分类树节点可能重复出现，缓存计数避免重复打远程 list 接口。
            if (!array_key_exists($id, $counts)) {
                $counts[$id] = $this->catalogCountByCategory($id);
            }
            $category['plugin_count'] = $counts[$id];
        }

        if (isset($category['children']) && is_array($category['children'])) {
            $children = [];
            foreach ($category['children'] as $child) {
                if (is_array($child)) {
                    $children[] = $this->appendCategoryCount($child, $counts);
                }
            }
            $category['children'] = $children;
        }

        return $category;
    }

    /**
     * 用最小分页请求获取指定分类的插件数。
     *
     * @param int $categoryId 远程插件分类 ID。
     * @return int 该分类的插件数量。
     */
    private function catalogCountByCategory(int $categoryId): int
    {
        $response = $this->requestJson('GET', '/openapi/plugin-market/list', [
            'category_id' => $categoryId,
            'keyword' => '',
            'sort' => 'default',
            'page' => 1,
            'limit' => 1,
            'is_feature' => 0,
        ]);
        if (isset($response['total'])) {
            return $this->normalizeUnsignedInt($response['total'], 0, 0, null);
        }
        if (isset($response['items']) && is_array($response['items'])) {
            return count($response['items']);
        }
        if (isset($response['list']) && is_array($response['list'])) {
            return count($response['list']);
        }
        return 0;
    }

    /**
     * 只接收经过编码校验的文件描述符。
     *
     * @param array<string,mixed> $payload 包含文件描述符的请求字段。
     * @return array<string,mixed> 仅包含规范化文件描述符的参数。
     */
    private function normalizeFileRequestPayload(array $payload): array
    {
        $file = $payload['file'] ?? null;
        if (is_array($file)) {
            return ['file' => $this->normalizeEncodedFile($file)];
        }

        if ($file === null) {
            throw new AdminResponseException('请先上传文件');
        }

        throw new AdminResponseException('文件参数无效');
    }

    /**
     * 按白名单校验发布资料、价格与可选编码插件包。
     *
     * @param array<string,mixed> $payload 原始插件发布请求。
     * @return array<string,mixed> 可提交开放平台的发布字段。
     */
    private function normalizePublishPayload(array $payload): array
    {
        $params = [
            'name' => $this->normalizeText($payload['name'] ?? '', 150),
            'code' => $this->normalizePluginCode($payload['code'] ?? ''),
            'category_id' => $this->normalizeUnsignedInt($payload['category_id'] ?? 0, 0, 1, null),
            'latest_version' => $this->normalizeText($payload['latest_version'] ?? '', 32),
            'core_min' => $this->normalizeText($payload['core_min'] ?? '', 32),
            'core_max' => $this->normalizeText($payload['core_max'] ?? '', 32),
            'summary' => $this->normalizeText($payload['summary'] ?? '', 500),
            'detail' => $this->normalizeText($payload['detail'] ?? '', 5000),
            'cover' => $this->normalizeText($payload['cover'] ?? '', 500),
            'surfaces' => $this->normalizeStringList($payload['surfaces'] ?? []),
            'is_feature' => $this->normalizeUnsignedInt($payload['is_feature'] ?? 0, 0, 0, 1),
            'enterprise_free' => $this->normalizeUnsignedInt($payload['enterprise_free'] ?? 0, 0, 0, 1),
            'is_vip_price' => $this->normalizeUnsignedInt($payload['is_vip_price'] ?? 0, 0, 0, 1),
            'rating' => $this->normalizeDecimal($payload['rating'] ?? 4.5, 4.5, 0, 5),
            'reviews' => $this->normalizeUnsignedInt($payload['reviews'] ?? 0, 0, 0, null),
            'downloads' => $this->normalizeUnsignedInt($payload['downloads'] ?? 0, 0, 0, null),
            'sort' => $this->normalizeUnsignedInt($payload['sort'] ?? 0, 0, 0, null),
            'price_free' => $this->normalizeDecimal($payload['price_free'] ?? 0, 0, 0, null),
            'price_buyout' => $this->normalizeDecimal($payload['price_buyout'] ?? 0, 0, 0, null),
            'price_subscription' => $this->normalizeDecimal($payload['price_subscription'] ?? 0, 0, 0, null),
            'subscription_days' => $this->normalizeUnsignedInt($payload['subscription_days'] ?? 0, 0, 0, null),
            'agreed' => $this->normalizeUnsignedInt($payload['agreed'] ?? 0, 0, 0, 1),
        ];

        if (isset($payload['file']) && is_array($payload['file'])) {
            // 发布时可携带 ZIP 包，按网关约定转成编码后的文件描述符。
            $params['file'] = $this->normalizeEncodedFile($payload['file']);
        }

        return array_filter($params, static fn (mixed $value): bool => $value !== '' && $value !== []);
    }

    /**
     * 验证托管与自助上传的凭据边界，并裁剪上传任务字段。
     *
     * @param array<string,mixed> $payload 原始小程序上传请求。
     * @return array<string,mixed> 符合上传模式约束的任务字段。
     */
    private function normalizeMpUploadPayload(array $payload): array
    {
        $mode = $this->normalizeMpUploadMode($payload['mode'] ?? 'hosted_cli');
        $credentialId = $this->normalizeUnsignedInt($payload['credential_id'] ?? 0, 0, 0, null);
        if ($mode === 'hosted_cli' && $credentialId <= 0) {
            throw new AdminResponseException('托管上传必须提供有效凭据');
        }
        if ($mode === 'self_upload' && $credentialId !== 0) {
            throw new AdminResponseException('自助上传不允许携带凭据');
        }

        // 这里保留结果和进度字段，便于远程任务状态机直接消费。
        $params = [
            'task_no' => $this->normalizeText($payload['task_no'] ?? '', 64),
            'artifact_id' => $this->normalizeUnsignedInt($payload['artifact_id'] ?? 0, 0, 0, null),
            'mode' => $mode,
            'credential_id' => $credentialId,
            'version' => $this->normalizeText($payload['version'] ?? '', 32),
            'description' => $this->normalizeText($payload['description'] ?? '', 500),
            'remote_request_id' => $this->normalizeText($payload['remote_request_id'] ?? '', 128),
            'wechat_request_id' => $this->normalizeText($payload['wechat_request_id'] ?? '', 128),
            'cli_version' => $this->normalizeText($payload['cli_version'] ?? '', 32),
            'result_json' => is_array($payload['result_json'] ?? null) ? $payload['result_json'] : [],
            'status' => $this->normalizeUnsignedInt($payload['status'] ?? 0, 0, 0, null),
            'progress' => $this->normalizeUnsignedInt($payload['progress'] ?? 0, 0, 0, 100),
        ];

        return array_filter($params, static fn (mixed $value): bool => $value !== '' && $value !== []);
    }

    /**
     * 验证文件名、Base64 内容与实际长度，并预留网关信封编码空间。
     *
     * @param array<string,mixed> $file 客户端编码文件描述。
     * @return array<string,mixed> 规范化文件名、类型、长度及编码内容。
     */
    private function normalizeEncodedFile(array $file): array
    {
        $name = $this->normalizeText($file['name'] ?? '', 255);
        $type = $this->normalizeText($file['type'] ?? $file['mime_type'] ?? 'application/octet-stream', 100);
        $base64 = $this->normalizeText($file['content'] ?? $file['base64'] ?? '', 8 * 1024 * 1024);
        $size = $this->normalizeUnsignedInt($file['size'] ?? 0, 0, 0, self::MAX_GATEWAY_FILE_BYTES);
        if ($name === '' || $base64 === '' || preg_match('~[\\\\/\x00-\x1F\x7F]~', $name) === 1) {
            throw new AdminResponseException('文件参数无效');
        }
        $content = base64_decode($base64, true);
        if ($content === false || $content === '') {
            throw new AdminResponseException('文件编码无效');
        }
        if (array_key_exists('size', $file) && $size !== strlen($content)) {
            throw new AdminResponseException('文件大小与内容不一致');
        }
        $size = strlen($content);
        if ($size > self::MAX_GATEWAY_FILE_BYTES) {
            throw new AdminResponseException('网关上传文件不能超过 6MiB');
        }
        return array_filter([
            'name' => $name,
            'type' => $type,
            'mime_type' => $type,
            'size' => $size,
            'content' => $base64,
        ], static fn (mixed $value): bool => $value !== '' && $value !== []);
    }

    /**
     * 验证上传图片的允许类型及 2MiB 大小约束。
     *
     * @param array<string,mixed> $file 经过编码校验的图片文件描述。
     * @return void 无返回值；类型或大小非法时抛出异常。
     */
    private function assertImageFile(array $file): void
    {
        $mime = strtolower((string)($file['mime_type'] ?? $file['type'] ?? ''));
        $name = strtolower((string)($file['name'] ?? ''));
        $size = $this->normalizeUnsignedInt($file['size'] ?? 0, 0, 0, 2 * 1024 * 1024);
        if (!in_array($mime, ['image/jpg', 'image/jpeg', 'image/png', 'image/webp'], true)
            && !preg_match('/\.(jpg|jpeg|png|webp)$/', $name)
        ) {
            throw new AdminResponseException('仅支持 JPG、PNG 或 WEBP 图片');
        }
        if ($size > 2 * 1024 * 1024) {
            throw new AdminResponseException('图片大小不能超过 2MB');
        }
    }

    /**
     * 校验市场插件编码格式与长度。
     *
     * @param mixed $code 原始市场插件编码。
     * @return string 已验证的插件编码。
     */
    private function normalizePluginCode(mixed $code): string
    {
        $value = $this->normalizeText($code, 64);
        if ($value === '' || preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $value) !== 1) {
            throw new AdminResponseException('插件编码无效');
        }
        return $value;
    }

    /**
     * 校验远程订单号格式与长度。
     *
     * @param mixed $orderNo 原始订单号。
     * @return string 已验证的订单号。
     */
    private function normalizeOrderNo(mixed $orderNo): string
    {
        $value = $this->normalizeText($orderNo, 64);
        if ($value === '' || preg_match('/^[A-Za-z0-9._:-]{1,64}$/', $value) !== 1) {
            throw new AdminResponseException('订单号无效');
        }
        return $value;
    }

    /**
     * 校验下载票据格式与长度。
     *
     * @param mixed $ticket 原始下载票据。
     * @return string 已验证的下载票据。
     */
    private function normalizeDownloadTicket(mixed $ticket): string
    {
        $value = $this->normalizeText($ticket, 128);
        if ($value === '' || preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $value) !== 1) {
            throw new AdminResponseException('下载票据无效');
        }
        return $value;
    }

    /**
     * 仅允许声明的插件计费类型，空值表示不筛选。
     *
     * @param mixed $billingType 原始计费类型字段。
     * @return string 已验证的计费类型或空字符串。
     */
    private function normalizeBillingType(mixed $billingType): string
    {
        $value = $this->normalizeText($billingType, 20);
        if ($value === '') {
            return '';
        }
        if (!in_array($value, ['free', 'paid', 'buyout', 'subscription'], true)) {
            throw new AdminResponseException('计费类型无效');
        }
        return $value;
    }

    /**
     * 验证市场列表排序白名单。
     *
     * @param mixed $sort 原始排序方式。
     * @return string 已允许的排序方式。
     */
    private function normalizeSort(mixed $sort): string
    {
        $value = $this->normalizeText($sort, 20);
        if (!in_array($value, ['default', 'rating', 'downloads', 'latest'], true)) {
            throw new AdminResponseException('排序方式无效');
        }
        return $value;
    }

    /**
     * 验证插件购买支付方式白名单。
     *
     * @param mixed $payType 原始支付方式。
     * @return string 余额、微信或支付宝支付方式。
     */
    private function normalizePayType(mixed $payType): string
    {
        $value = $this->normalizeText($payType, 20);
        if (!in_array($value, ['balance', 'wechat', 'alipay'], true)) {
            throw new AdminResponseException('支付方式无效');
        }
        return $value;
    }

    /**
     * 验证支付轮询渠道，空值按微信通道处理。
     *
     * @param mixed $payType 原始支付轮询渠道。
     * @return string 微信或支付宝轮询渠道。
     */
    private function normalizePollType(mixed $payType): string
    {
        $value = $this->normalizeText($payType, 20);
        if ($value === '') {
            return 'wechat';
        }
        if (!in_array($value, ['wechat', 'alipay'], true)) {
            throw new AdminResponseException('轮询渠道无效');
        }
        return $value;
    }

    /**
     * 验证资产流水查询类型白名单。
     *
     * @param mixed $type 原始资产类型。
     * @return string 余额、积分或金币资产类型。
     */
    private function normalizeAssetType(mixed $type): string
    {
        $value = $this->normalizeText($type, 20);
        if (!in_array($value, ['balance', 'points', 'coins'], true)) {
            throw new AdminResponseException('资产类型无效');
        }
        return $value;
    }

    /**
     * 验证资产收支方向，空值表示不筛选。
     *
     * @param mixed $type 原始资产变动类型。
     * @return string 收入、支出类型或空字符串。
     */
    private function normalizeChangeType(mixed $type): string
    {
        $value = $this->normalizeText($type, 10);
        if ($value === '') {
            return '';
        }
        if (!in_array($value, ['in', 'out'], true)) {
            throw new AdminResponseException('变动类型无效');
        }
        return $value;
    }

    /**
     * 验证小程序上传模式白名单。
     *
     * @param mixed $mode 原始上传模式。
     * @return string 托管命令行或自助上传模式。
     */
    private function normalizeMpUploadMode(mixed $mode): string
    {
        $value = $this->normalizeText($mode, 20);
        if (!in_array($value, ['hosted_cli', 'self_upload'], true)) {
            throw new AdminResponseException('上传模式无效');
        }
        return $value;
    }

    /**
     * 校验身份证号长度与格式，并统一末位字母大小写。
     *
     * @param mixed $value 原始身份证号。
     * @return string 格式有效的身份证号。
     */
    private function normalizeIdentityNumber(mixed $value): string
    {
        $text = $this->normalizeText($value, 32);
        if ($text === '' || preg_match('/^(?:\d{15}|\d{17}[0-9Xx])$/', $text) !== 1) {
            throw new AdminResponseException('身份证号无效');
        }
        return strtoupper($text);
    }

    /**
     * 校验统一社会信用代码格式。
     *
     * @param mixed $value 原始企业信用代码。
     * @return string 大写的有效信用代码。
     */
    private function normalizeCreditCode(mixed $value): string
    {
        $text = strtoupper($this->normalizeText($value, 32));
        if ($text === '' || preg_match('/^[0-9A-Z]{18}$/', $text) !== 1) {
            throw new AdminResponseException('统一社会信用代码无效');
        }
        return $text;
    }

    /**
     * 仅接受可转换标量并验证文本长度，不静默裁掉超长字段。
     *
     * @param mixed $value 原始文本字段。
     * @param int $maxLength 最大允许字符数；非正数表示不限制。
     * @return string 去除首尾空白的有效文本。
     */
    private function normalizeText(mixed $value, int $maxLength): string
    {
        if ($value !== null && !is_scalar($value)) {
            throw new AdminResponseException('文本参数类型无效');
        }
        $text = trim((string)$value);
        if ($maxLength > 0 && mb_strlen($text) > $maxLength) {
            throw new AdminResponseException('参数长度超出限制');
        }
        return $text;
    }

    /**
     * 严格校验十进制整数与范围，避免小数或科学计数法被静默转换。
     *
     * @param mixed $value 原始整数参数。
     * @param int $default 空值使用的默认整数。
     * @param int $min 允许的最小值。
     * @param ?int $max 允许的最大值；null 表示不设上限。
     * @return int 符合边界要求的整数。
     */
    private function normalizeUnsignedInt(mixed $value, int $default, int $min, ?int $max): int
    {
        if ($value === '' || $value === null) {
            $value = $default;
        }

        if (is_int($value)) {
            $number = $value;
        } elseif (is_string($value)
            && preg_match('/^\d+$/D', $value) === 1
        ) {
            // 仅接受十进制整数字符串，避免把小数和科学计数法静默转换。
            $normalized = ltrim($value, '0');
            $normalized = $normalized === '' ? '0' : $normalized;
            $validated = filter_var($normalized, FILTER_VALIDATE_INT);
            if ($validated === false) {
                throw new AdminResponseException('整数参数无效');
            }
            $number = $validated;
        } elseif (is_float($value)
            && is_finite($value)
            && floor($value) === $value
            && $value <= PHP_INT_MAX
        ) {
            $number = (int)$value;
        } else {
            throw new AdminResponseException('整数参数无效');
        }

        if ($number < $min || ($max !== null && $number > $max)) {
            throw new AdminResponseException('整数参数超出范围');
        }
        return $number;
    }

    /**
     * 验证数字参数并限制价格或评分范围。
     *
     * @param mixed $value 原始数字字段。
     * @param float|int $default 空值使用的默认数字。
     * @param float $min 允许的最小值。
     * @param ?float $max 允许的最大值；null 表示不设上限。
     * @return float 符合边界要求的浮点值。
     */
    private function normalizeDecimal(mixed $value, float|int $default, float $min, ?float $max): float
    {
        if ($value === '' || $value === null) {
            $value = $default;
        }
        if (!is_int($value) && !is_float($value) && !is_numeric($value)) {
            throw new AdminResponseException('数字参数无效');
        }
        $number = (float)$value;
        if ($number < $min || ($max !== null && $number > $max)) {
            throw new AdminResponseException('数字参数超出范围');
        }
        return $number;
    }

    /**
     * 校验并去重字符串列表，忽略空文本。
     *
     * @param mixed $value 原始列表字段。
     * @return array<int,string> 去重后的非空字符串列表。
     */
    private function normalizeStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $result = [];
        foreach ($value as $item) {
            $text = $this->normalizeText($item, 64);
            if ($text !== '') {
                $result[] = $text;
            }
        }
        return array_values(array_unique($result));
    }

    /**
     * 从容器解析并复用安全网关客户端。
     *
     * @return PluginSecureGatewayClient 当前服务使用的安全网关客户端。
     */
    private function gatewayClient(): PluginSecureGatewayClient
    {
        if ($this->gatewayClient instanceof PluginSecureGatewayClient) {
            return $this->gatewayClient;
        }
        $client = app()->make(PluginSecureGatewayClient::class);
        if (!$client instanceof PluginSecureGatewayClient) {
            throw new AdminResponseException('插件安全客户端不可用');
        }
        $this->gatewayClient = $client;
        return $this->gatewayClient;
    }

    /**
     * 将成功的二进制下载转换为文件响应，JSON 内容视为业务失败。
     *
     * @param string $ticket 已签发的下载票据。
     * @return Response 原始二进制文件响应。
     */
    private function downloadResponse(string $ticket): Response
    {
        $url = $this->buildOpenApiUrl('/plugin-market/download', ['ticket' => $ticket]);
        [$body, $headers, $status] = $this->performBinaryRequest($url);
        if ($status < 200 || $status >= 300) {
            $message = $this->extractErrorMessage($body, $headers, '下载失败');
            throw new AdminResponseException($message);
        }

        $contentType = strtolower((string)($headers['content-type'] ?? 'application/octet-stream'));
        if (str_contains($contentType, 'json')) {
            $message = $this->extractErrorMessage($body, $headers, '下载失败');
            throw new AdminResponseException($message);
        }

        $response = Response::create($body, File::class);
        // 文件响应必须保持原始二进制，不走 success() 的 JSON 包装。
        $response->isContent(true)->mimeType($contentType);
        $response->name($this->resolveDownloadFilename($headers, $ticket), false);

        return $response;
    }

    /**
     * 执行有限重定向与超时的文件请求，并收集响应头和状态。
     *
     * @param string $url 已组装的下载地址。
     * @return array{0:string,1:array<string,string>,2:int} 响应体、响应头和 HTTP 状态码。
     */
    private function performBinaryRequest(string $url): array
    {
        if (!function_exists('curl_init')) {
            throw new AdminResponseException('下载组件不可用');
        }
        $headers = [];
        $responseHeaders = [];
        $curl = curl_init($url);
        if ($curl === false) {
            throw new AdminResponseException('下载请求初始化失败');
        }
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $trimmed = trim($line);
                if ($trimmed === '' || str_starts_with($trimmed, 'HTTP/')) {
                    return strlen($line);
                }
                $parts = explode(':', $trimmed, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($line);
            },
        ]);
        $body = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if ($body === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new AdminResponseException($error !== '' ? $error : '下载请求失败');
        }
        curl_close($curl);

        return [(string)$body, $responseHeaders, $status];
    }

    /**
     * 解析标准下载文件名响应头，缺失时用票据前缀生成默认名。
     *
     * @param array<string,string> $headers 下载响应头。
     * @param string $ticket 用于默认文件名的下载票据。
     * @return string 文件下载名称。
     */
    private function resolveDownloadFilename(array $headers, string $ticket): string
    {
        $disposition = (string)($headers['content-disposition'] ?? '');
        if ($disposition !== '') {
            if (preg_match('/filename\\*=UTF-8\'\'([^;]+)/i', $disposition, $matches) === 1) {
                return rawurldecode(trim($matches[1], '"\''));
            }
            if (preg_match('/filename="?([^";]+)"?/i', $disposition, $matches) === 1) {
                return trim($matches[1], '"\'');
            }
        }

        return 'plugin-download-' . substr($ticket, 0, 12) . '.zip';
    }

    /**
     * 从安全网关基址组装公开下载地址，避免重复 openapi 前缀。
     *
     * @param string $path 目标开放平台路径。
     * @param array $query 待编码的查询参数。
     * @return string 完整开放平台请求地址。
     */
    private function buildOpenApiUrl(string $path, array $query = []): string
    {
        $endpoint = $this->resolveGatewayEndpoint();
        $parts = parse_url($endpoint);
        if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https') {
            throw new AdminResponseException('插件网关地址无效');
        }

        $basePath = $this->resolveGatewayBasePath((string)($parts['path'] ?? ''));
        $targetPath = $this->normalizeUrlPath($path);
        if (!str_starts_with($targetPath, '/openapi/')) {
            // 兼容下载等直挂 openapi 的路径，避免把已有前缀重复拼接。
            $targetPath = rtrim($basePath, '/') . '/' . ltrim($targetPath, '/');
        }

        $url = strtolower((string)$parts['scheme']) . '://' . (string)($parts['host'] ?? '');
        if (isset($parts['port'])) {
            $url .= ':' . $parts['port'];
        }
        $url .= $targetPath;
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }
        return $url;
    }

    /**
     * 按配置优先、环境变量兜底读取服务器网关地址。
     *
     * @return string 已配置的插件安全网关地址。
     */
    private function resolveGatewayEndpoint(): string
    {
        $endpoint = trim((string)config('plugin.gateway.endpoint', ''));
        if ($endpoint === '') {
            $endpoint = trim((string)(getenv('PLUGIN_GATEWAY_ENDPOINT') ?: ''));
        }
        if ($endpoint === '') {
            throw new AdminResponseException('未配置插件网关地址');
        }
        return $endpoint;
    }

    /**
     * 从安全网关路径提取开放平台公共前缀。
     *
     * @param string $path 网关地址中的路径部分。
     * @return string 开放平台公共路径前缀。
     */
    private function resolveGatewayBasePath(string $path): string
    {
        $path = trim($path);
        if ($path === '' || $path === '/') {
            return '/openapi';
        }

        $normalized = '/' . trim($path, '/');
        if (str_ends_with($normalized, '/secure-gateway')) {
            $normalized = substr($normalized, 0, -strlen('/secure-gateway'));
        }
        $normalized = rtrim($normalized, '/');
        return $normalized === '' ? '/openapi' : $normalized;
    }

    /**
     * 验证绝对逻辑路径，不允许父级穿越或混入查询参数。
     *
     * @param string $path 原始逻辑路径。
     * @return string 通过安全校验的逻辑路径。
     */
    private function normalizeUrlPath(string $path): string
    {
        $path = trim($path);
        if ($path === '' || $path[0] !== '/' || str_contains($path, '..') || str_contains($path, '?')) {
            throw new AdminResponseException('路径无效');
        }
        return $path;
    }

    /**
     * 从远程 JSON 错误中提取可显示消息，否则保留调用方默认提示。
     *
     * @param string $body 远程响应体。
     * @param array $headers 远程响应头。
     * @param string $fallback 无法解析时的默认错误提示。
     * @return string 远程业务错误消息或默认提示。
     */
    private function extractErrorMessage(string $body, array $headers, string $fallback): string
    {
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            $message = trim((string)($decoded['message'] ?? $decoded['msg'] ?? $fallback));
            if ($message !== '') {
                return $message;
            }
        }

        $contentType = strtolower((string)($headers['content-type'] ?? ''));
        if (str_contains($contentType, 'json')) {
            return $fallback;
        }

        return $fallback;
    }
}
