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

namespace app\controller\admin\plugin;

use app\common\enum\plugin\PluginTaskTypeEnum;
use app\common\exception\AdminResponseException;
use app\common\plugin\security\PluginRequestEnvelope;
use app\common\service\plugin\PluginMarketService;
use app\common\service\plugin\PluginKeyService;
use app\common\service\plugin\PluginLifecycleService;
use app\common\service\plugin\DeveloperPluginLifecycleService;
use app\common\service\plugin\dto\PluginLifecycleRequest;
use app\controller\admin\AuthController;
use Throwable;
use think\annotation\Inject;
use think\Response;
use think\response\File;

/**
 * 提供后台插件与市场操作入口，内部安全网关复用时仍校验认证身份和逻辑路由绑定。
 */
class Plugin extends AuthController
{
    #[Inject]
    protected PluginLifecycleService $lifecycleService;

    #[Inject]
    protected PluginKeyService $keyService;

    #[Inject]
    protected PluginMarketService $marketService;

    #[Inject]
    protected DeveloperPluginLifecycleService $developerService;

    /**
     * 返回当前实例供浏览器加密请求使用的公开密钥。
     *
     * @return Response 包含实例公钥与协议元数据的成功响应。
     */
    public function publicKey(): Response
    {
        return $this->success($this->buildPublicKeyPayload());
    }

    /**
     * 查询当前系统已安装插件。
     *
     * @param array<string,mixed> $query 分页、关键词及生命周期状态筛选字段。
     * @return Response 包含插件列表与分页结果的成功响应。
     */
    public function installed(array $query = []): Response
    {
        return $this->success($this->lifecycleService->listInstalled($query));
    }

    /**
     * 本地 HTTP 查询入口，不让框架绑定嵌套 query 载荷。
     *
     * @return Response 开发者插件分页与当前管理员操作能力。
     */
    public function developerHttpList(): Response
    {
        return $this->developerList($this->request->get(['keyword', 'page', 'limit']));
    }

    /**
     * 本地 HTTP 安装入口，只从 POST 读取操作字段。
     *
     * @param string $code 路由限定的插件目录编码。
     * @return Response 本地安装任务结果。
     */
    public function developerHttpInstall(string $code): Response
    {
        return $this->developerInstall($code, $this->request->post(['idempotency_key']));
    }

    /**
     * 本地 HTTP 卸载入口，查询字段不能覆盖确认后的数据策略。
     *
     * @param string $code 路由限定的插件目录编码。
     * @return Response 本地卸载任务结果。
     */
    public function developerHttpUninstall(string $code): Response
    {
        return $this->developerUninstall($code, $this->request->post(['idempotency_key', 'data_policy']));
    }

    /**
     * 本地 HTTP 重新安装入口，用当前源码替换已安装的开发源码。
     *
     * @param string $code 路由限定的插件目录编码。
     * @return Response 本地重装任务结果。
     */
    public function developerHttpReinstall(string $code): Response
    {
        return $this->developerReinstall($code, $this->request->post(['idempotency_key', 'data_policy']));
    }

    /**
     * 本地 HTTP 下载入口，返回整个插件源码包，不套用 JSON 成功包装。
     *
     * @param string $code 路由限定的插件目录编码。
     * @return Response 开发插件源码压缩包下载响应。
     */
    public function developerHttpDownload(string $code): Response
    {
        $this->assertDeveloperAdministrator();
        $package = $this->developerService->exportDeveloperSource($code);
        $name = (string)$package['name'];
        $content = file_get_contents((string)$package['path']);
        // 压缩包只用于本次传输，读取后立即清理，源码本身仍在固定开发目录中。
        @unlink((string)$package['path']);
        if ($content === false) {
            throw new AdminResponseException('开发插件压缩包不可读');
        }

        // 下载接口属于文件协议例外，必须直接返回文件响应，不能交给 JSON 包装器。
        $response = Response::create($content, File::class);
        $response->isContent(true)->mimeType('application/zip')->name($name, false);
        // Swoole 在文件响应计算头部之前就已写入响应头，必须提前显式声明，否则丢失类型与文件名。
        $response->header([
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
        ]);
        return $response;
    }

    /**
     * 展示服务器固定目录中的开发插件，不暴露源码路径。
     *
     * @param array<string,mixed> $query 仅包含关键词、页码和每页数量的查询条件。
     * @return Response 包含安全展示信息与当前用户操作能力的成功响应。
     */
    public function developerList(array $query = []): Response
    {
        return $this->success($this->developerService->listDeveloper(
            array_intersect_key($query, array_flip(['keyword', 'page', 'limit'])),
            ['can_manage_developer' => $this->isDeveloperAdministrator()]
        ));
    }

    /**
     * 仅允许认证超级管理员安装固定开发目录源码。
     *
     * @param string $code 插件目录编码。
     * @param array<string,mixed> $payload 仅包含操作幂等键的请求字段。
     * @return Response 包含共用生命周期任务结果的成功响应。
     */
    public function developerInstall(string $code, array $payload = []): Response
    {
        $this->assertDeveloperAdministrator();
        return $this->success($this->developerService->installDeveloper(
            $code,
            array_intersect_key($payload, array_flip(['idempotency_key'])),
            $this->buildContext()
        ));
    }

    /**
     * 仅允许认证超级管理员卸载受信开发插件。
     *
     * @param string $code 插件目录编码。
     * @param array<string,mixed> $payload 操作幂等键及已确认数据保留策略。
     * @return Response 包含共用生命周期任务结果的成功响应。
     */
    public function developerUninstall(string $code, array $payload = []): Response
    {
        $this->assertDeveloperAdministrator();
        return $this->success($this->developerService->uninstallDeveloper(
            $code,
            array_intersect_key($payload, array_flip(['idempotency_key', 'data_policy'])),
            $this->buildContext()
        ));
    }

    /**
     * 仅允许认证超级管理员用当前源码重新安装开发插件。
     *
     * @param string $code 插件目录编码。
     * @param array<string,mixed> $payload 操作幂等键及已确认数据保留策略。
     * @return Response 包含共用生命周期任务结果的成功响应。
     */
    public function developerReinstall(string $code, array $payload = []): Response
    {
        $this->assertDeveloperAdministrator();
        return $this->success($this->developerService->reinstallDeveloper(
            $code,
            array_intersect_key($payload, array_flip(['idempotency_key', 'data_policy'])),
            $this->buildContext()
        ));
    }

    /**
     * 查询生命周期任务详情，不执行任务。
     *
     * @param int|string $id 任务 ID 或任务编号。
     * @return Response 包含任务详情的成功响应；任务不存在时抛出异常。
     */
    public function task(int|string $id): Response
    {
        $task = $this->lifecycleService->getTask($id);
        if ($task === null) {
            throw new AdminResponseException('任务不存在');
        }
        return $this->success($task);
    }

    /**
     * 调用统一生命周期服务安装受信插件。
     *
     * @param string $pluginCode 插件编码。
     * @param array<string,mixed> $payload 生命周期版本、幂等键和明确数据策略字段。
     * @return Response 包含安装任务结果的成功响应。
     */
    public function install(string $pluginCode, array $payload = []): Response
    {
        return $this->success($this->lifecycleService->install(
            $this->buildRequest($pluginCode, $payload, PluginTaskTypeEnum::INSTALL),
            null,
            $this->buildContext()
        ));
    }

    /**
     * 调用统一生命周期服务升级受信插件。
     *
     * @param string $pluginCode 插件编码。
     * @param array<string,mixed> $payload 生命周期版本、幂等键和明确数据策略字段。
     * @return Response 包含升级任务结果的成功响应。
     */
    public function upgrade(string $pluginCode, array $payload = []): Response
    {
        return $this->success($this->lifecycleService->upgrade(
            $this->buildRequest($pluginCode, $payload, PluginTaskTypeEnum::UPGRADE),
            null,
            $this->buildContext()
        ));
    }

    /**
     * 调用统一生命周期服务禁用插件。
     *
     * @param string $pluginCode 插件编码。
     * @param array<string,mixed> $payload 生命周期版本、幂等键和明确数据策略字段。
     * @return Response 包含禁用任务结果的成功响应。
     */
    public function disable(string $pluginCode, array $payload = []): Response
    {
        return $this->success($this->lifecycleService->disable(
            $this->buildRequest($pluginCode, $payload, PluginTaskTypeEnum::DISABLE),
            null,
            $this->buildContext()
        ));
    }

    /**
     * 按明确数据策略调用统一生命周期服务卸载插件。
     *
     * @param string $pluginCode 插件编码。
     * @param array<string,mixed> $payload 生命周期版本、幂等键和明确数据策略字段。
     * @return Response 包含卸载任务结果的成功响应。
     */
    public function uninstall(string $pluginCode, array $payload = []): Response
    {
        return $this->success($this->lifecycleService->uninstall(
            $this->buildRequest($pluginCode, $payload, PluginTaskTypeEnum::UNINSTALL),
            null,
            $this->buildContext()
        ));
    }

    /**
     * 提交插件回滚请求，实际回滚能力由执行器限定。
     *
     * @param string $pluginCode 插件编码。
     * @param array<string,mixed> $payload 生命周期版本、幂等键和明确数据策略字段。
     * @return Response 包含回滚任务结果的成功响应。
     */
    public function rollback(string $pluginCode, array $payload = []): Response
    {
        return $this->success($this->lifecycleService->rollback(
            $this->buildRequest($pluginCode, $payload, PluginTaskTypeEnum::ROLLBACK),
            null,
            $this->buildContext()
        ));
    }

    /**
     * 查询远程插件市场分类树。
     *
     * @param array<string,mixed> $payload 预留分类查询参数。
     * @return Response 包含分类树的成功响应。
     */
    public function catalogCategories(array $payload = []): Response
    {
        return $this->success($this->marketService->catalogCategories($payload));
    }

    /**
     * 查询远程市场插件分页列表。
     *
     * @param array<string,mixed> $payload 分类、关键词、计费类型、排序及分页条件。
     * @return Response 包含市场插件分页结果的成功响应。
     */
    public function catalogList(array $payload = []): Response
    {
        return $this->success($this->marketService->catalogList($payload));
    }

    /**
     * 查询远程市场精选插件。
     *
     * @param array<string,mixed> $payload 分类与展示数量筛选字段。
     * @return Response 包含精选插件列表的成功响应。
     */
    public function catalogFeatured(array $payload = []): Response
    {
        return $this->success($this->marketService->catalogFeatured($payload));
    }

    /**
     * 查询指定市场插件详情。
     *
     * @param string $code 市场插件编码。
     * @return Response 包含远程插件详情的成功响应。
     */
    public function catalogDetail(string $code): Response
    {
        return $this->success($this->marketService->catalogDetail($code));
    }

    /**
     * 获取开放平台短信行为验证场景。
     *
     * @param array<string,mixed> $payload 待验证手机号字段。
     * @return Response 包含行为验证场景的成功响应。
     */
    public function authSmsScene(array $payload = []): Response
    {
        return $this->success($this->marketService->authSmsScene($payload));
    }

    /**
     * 校验行为验证凭证并请求发送短信。
     *
     * @param array<string,mixed> $payload 手机号及点击场景或云验证凭证。
     * @return Response 包含短信发送结果的成功响应。
     */
    public function authSmsSend(array $payload = []): Response
    {
        return $this->success($this->marketService->authSmsSend($payload));
    }

    /**
     * 使用短信验证码登录开放平台并绑定当前管理员。
     *
     * @param array<string,mixed> $payload 手机号、验证码及可选推荐用户字段。
     * @return Response 包含开放平台登录结果的成功响应。
     */
    public function phoneLogin(array $payload = []): Response
    {
        return $this->success($this->marketService->phoneLogin($payload));
    }

    /**
     * 使用账号密码登录开放平台并绑定当前管理员。
     *
     * @param array<string,mixed> $payload 账号、密码及可选推荐用户字段。
     * @return Response 包含开放平台登录结果的成功响应。
     */
    public function accountLogin(array $payload = []): Response
    {
        return $this->success($this->marketService->accountLogin($payload));
    }

    /**
     * 读取当前管理员绑定的开放平台登录状态。
     *
     * @return Response 包含登录标记与用户资料的成功响应。
     */
    public function openAuthInfo(): Response
    {
        return $this->success($this->marketService->openAuthInfo());
    }

    /**
     * 解除当前管理员的开放平台登录绑定。
     *
     * @return Response 包含退出标记的成功响应。
     */
    public function openAuthLogout(): Response
    {
        return $this->success($this->marketService->openAuthLogout());
    }

    /**
     * 查询当前绑定开放平台用户的插件订单。
     *
     * @param array<string,mixed> $payload 预留查询参数；用户身份由登录绑定决定。
     * @return Response 包含当前用户订单列表的成功响应。
     */
    public function orders(array $payload = []): Response
    {
        return $this->success($this->marketService->orders($payload));
    }

    /**
     * 创建当前开放平台用户的插件购买订单。
     *
     * @param array<string,mixed> $payload 市场商品 ID 和 SKU ID。
     * @return Response 包含远程订单创建结果的成功响应。
     */
    public function orderCreate(array $payload = []): Response
    {
        return $this->success($this->marketService->orderCreate($payload));
    }

    /**
     * 提交当前用户的插件订单支付。
     *
     * @param array<string,mixed> $payload 订单号与允许的支付方式。
     * @return Response 包含远程支付结果的成功响应。
     */
    public function orderPay(array $payload = []): Response
    {
        return $this->success($this->marketService->orderPay($payload));
    }

    /**
     * 查询指定订单的远程支付状态。
     *
     * @param array<string,mixed> $payload 订单号和支付轮询渠道。
     * @return Response 包含支付状态的成功响应。
     */
    public function orderStatus(array $payload = []): Response
    {
        return $this->success($this->marketService->orderStatus($payload));
    }

    /**
     * 取消当前用户的待支付插件订单。
     *
     * @param array<string,mixed> $payload 待取消订单号。
     * @return Response 包含远程取消结果的成功响应。
     */
    public function orderCancel(array $payload = []): Response
    {
        return $this->success($this->marketService->orderCancel($payload));
    }

    /**
     * 查询当前绑定开放平台用户的插件授权。
     *
     * @param array<string,mixed> $payload 预留查询参数；用户身份由登录绑定决定。
     * @return Response 包含插件授权列表的成功响应。
     */
    public function licenses(array $payload = []): Response
    {
        return $this->success($this->marketService->licenses($payload));
    }

    /**
     * 查询当前绑定开放平台用户可下载的插件。
     *
     * @param array<string,mixed> $payload 预留查询参数；用户身份由登录绑定决定。
     * @return Response 包含可下载列表的成功响应。
     */
    public function downloads(array $payload = []): Response
    {
        return $this->success($this->marketService->downloads($payload));
    }

    /**
     * 为当前用户的插件授权申请下载票据。
     *
     * @param array<string,mixed> $payload 插件授权记录 ID。
     * @return Response 包含下载票据的成功响应。
     */
    public function downloadTicket(array $payload = []): Response
    {
        return $this->success($this->marketService->downloadTicket($payload));
    }

    /**
     * 直接返回插件制品文件，不能套用 JSON 成功包装。
     *
     * @param array<string,mixed> $payload 已签发的插件下载票据。
     * @return Response 原始插件文件下载响应。
     */
    public function download(array $payload = []): Response
    {
        // 下载接口属于文件协议例外，必须直接透传文件响应，不能交给 JSON 包装器。
        return $this->marketService->download($payload);
    }

    /**
     * 读取当前绑定开放平台用户的个人资料。
     *
     * @param array<string,mixed> $payload 预留查询参数；用户身份由登录绑定决定。
     * @return Response 包含个人资料的成功响应。
     */
    public function userInfo(array $payload = []): Response
    {
        return $this->success($this->marketService->userInfo($payload));
    }

    /**
     * 更新当前绑定开放平台用户昵称。
     *
     * @param array<string,mixed> $payload 待更新昵称。
     * @return Response 包含远程资料更新结果的成功响应。
     */
    public function userUpdate(array $payload = []): Response
    {
        return $this->success($this->marketService->userUpdate($payload));
    }

    /**
     * 分页读取当前用户的资产流水。
     *
     * @param array<string,mixed> $payload 资产类型、收支类型及分页条件。
     * @return Response 包含资产流水的成功响应。
     */
    public function assetLogs(array $payload = []): Response
    {
        return $this->success($this->marketService->assetLogs($payload));
    }

    /**
     * 读取当前绑定开放平台用户余额。
     *
     * @return Response 包含余额字段的成功响应。
     */
    public function balance(): Response
    {
        return $this->success($this->marketService->balance());
    }

    /**
     * 读取插件发布可选分类。
     *
     * @return Response 包含发布分类的成功响应。
     */
    public function publishCategories(): Response
    {
        return $this->success($this->marketService->publishCategories());
    }

    /**
     * 读取当前用户的插件发布记录。
     *
     * @return Response 包含发布列表的成功响应。
     */
    public function publishMyList(): Response
    {
        return $this->success($this->marketService->publishMyList());
    }

    /**
     * 提交编码插件包进行远程解析。
     *
     * @param array<string,mixed> $payload 已上传插件包的编码文件描述。
     * @return Response 包含远程包解析结果的成功响应。
     */
    public function publishInspect(array $payload = []): Response
    {
        return $this->success($this->marketService->publishInspect($payload));
    }

    /**
     * 提交插件资料和可选源码包进行远程发布审核。
     *
     * @param array<string,mixed> $payload 插件资料、计费规则及可选编码文件。
     * @return Response 包含远程发布受理结果的成功响应。
     */
    public function publish(array $payload = []): Response
    {
        return $this->success($this->marketService->publish($payload));
    }

    /**
     * 重新提交当前用户的指定插件审核。
     *
     * @param array<string,mixed> $payload 待重新审核的插件编码。
     * @return Response 包含审核受理结果的成功响应。
     */
    public function publishSubmit(array $payload = []): Response
    {
        return $this->success($this->marketService->publishSubmit($payload));
    }

    /**
     * 修改当前用户发布插件的上下架状态。
     *
     * @param array<string,mixed> $payload 插件编码和目标上下架状态。
     * @return Response 包含状态处理结果的成功响应。
     */
    public function publishStatus(array $payload = []): Response
    {
        return $this->success($this->marketService->publishStatus($payload));
    }

    /**
     * 提交当前绑定用户的个人实名认证。
     *
     * @param array<string,mixed> $payload 真实姓名和身份证号。
     * @return Response 包含个人认证结果的成功响应。
     */
    public function verifyPersonal(array $payload = []): Response
    {
        return $this->success($this->marketService->verifyPersonal($payload));
    }

    /**
     * 提交当前绑定用户的企业实名认证。
     *
     * @param array<string,mixed> $payload 企业名称和统一社会信用代码。
     * @return Response 包含企业认证结果的成功响应。
     */
    public function verifyEnterprise(array $payload = []): Response
    {
        return $this->success($this->marketService->verifyEnterprise($payload));
    }

    /**
     * 请求远程图片上传，图片约束由市场服务验证。
     *
     * @param array<string,mixed> $payload 图片编码文件描述。
     * @return Response 包含远程图片上传结果的成功响应。
     */
    public function uploadImage(array $payload = []): Response
    {
        return $this->success($this->marketService->uploadImage($payload));
    }

    /**
     * 创建或更新指定构建的小程序上传任务。
     *
     * @param int|string $id 开放平台构建 ID。
     * @param array<string,mixed> $payload 上传模式、凭据、版本及任务状态字段。
     * @return Response 包含小程序上传任务结果的成功响应。
     */
    public function mpUpload(int|string $id, array $payload = []): Response
    {
        return $this->success($this->marketService->mpUpload($id, $payload));
    }

    /**
     * 重试指定构建的小程序上传任务。
     *
     * @param int|string $id 开放平台构建 ID。
     * @param array<string,mixed> $payload 预留重试请求参数。
     * @return Response 包含远程重试受理结果的成功响应。
     */
    public function mpUploadRetry(int|string $id, array $payload = []): Response
    {
        return $this->success($this->marketService->mpUploadRetry($id, $payload));
    }

    /**
     * 查询指定构建的小程序制品下载地址。
     *
     * @param int|string $id 开放平台构建 ID。
     * @return Response 包含制品下载地址信息的成功响应。
     */
    public function mpArtifactUrl(int|string $id): Response
    {
        return $this->success($this->marketService->mpArtifactUrl($id));
    }

    /**
     * 分发已解密且经过认证的逻辑请求，文件协议响应直接返回。
     *
     * @return Response 逻辑接口响应或统一网关失败响应。
     */
    public function handle(): Response
    {
        try {
            $envelope = $this->resolveEnvelope();
            $payload = $this->resolvePayload();
            // 用户身份由本地后台鉴权中间件注入，不能信任浏览器业务体中的同名字段。
            if (isset($this->request->user_id)) {
                $payload['user_id'] = (int)$this->request->user_id;
            }
            $response = $this->dispatch($envelope, $payload);
            // 下载接口会返回原始文件响应，这里不能再包一层 success()。
            if ($response instanceof Response) {
                return $response;
            }
            return $this->success($response);
        } catch (AdminResponseException $exception) {
            return d_json()->code(400)->fail($exception->getMessage(), [], 400);
        } catch (Throwable $exception) {
            $message = trim($exception->getMessage());
            return d_json()->code(400)->fail(
                $message !== '' ? $message : '插件安全网关处理失败',
                [],
                400
            );
        }
    }

    /**
     * 读取安全中间件已经验证的业务载荷。
     *
     * @return array<string,mixed> 已验证的业务载荷数组。
     */
    private function resolvePayload(): array
    {
        $payload = $this->request->pluginPayload ?? [];
        if (!is_array($payload)) {
            throw new AdminResponseException('安全请求载荷无效');
        }
        return $payload;
    }

    /**
     * 读取安全中间件已经验证的请求信封。
     *
     * @return PluginRequestEnvelope 已验证的请求信封对象。
     */
    private function resolveEnvelope(): PluginRequestEnvelope
    {
        $envelope = $this->request->pluginEnvelope ?? null;
        if (!$envelope instanceof PluginRequestEnvelope) {
            throw new AdminResponseException('安全请求信封缺失');
        }
        return $envelope;
    }

    /**
     * 按固定动作白名单分发请求，动作与已认证逻辑路径必须绑定。
     *
     * @param PluginRequestEnvelope $envelope 中间件验证通过的请求信封。
     * @param array<string,mixed> $payload 已解密业务载荷。
     * @return Response 对应逻辑接口的响应。
     */
    private function dispatch(PluginRequestEnvelope $envelope, array $payload): Response
    {
        $method = $this->resolveMethod($payload);
        $routeParams = $this->resolveRouteParams($method, $envelope);
        return match ($method) {
            'installed' => $this->installed($payload),
            'developer_list' => $this->developerList($payload),
            'developer_install' => $this->developerInstall($routeParams['code'], $payload),
            'developer_uninstall' => $this->developerUninstall($routeParams['code'], $payload),
            'catalog_categories' => $this->catalogCategories($payload),
            'catalog_list' => $this->catalogList($payload),
            'catalog_featured' => $this->catalogFeatured($payload),
            'catalog_detail' => $this->catalogDetail($routeParams['code']),
            'auth_sms_scene' => $this->authSmsScene($payload),
            'auth_sms_send' => $this->authSmsSend($payload),
            'auth_phone_login' => $this->phoneLogin($payload),
            'auth_account_login' => $this->accountLogin($payload),
            'open_auth_info' => $this->openAuthInfo(),
            'open_auth_logout' => $this->openAuthLogout(),
            'order_list' => $this->orders($payload),
            'order_create' => $this->orderCreate($payload),
            'order_pay' => $this->orderPay($payload),
            'order_status' => $this->orderStatus($payload),
            'order_cancel' => $this->orderCancel($payload),
            'license_list' => $this->licenses($payload),
            'download_list' => $this->downloads($payload),
            'download_ticket' => $this->downloadTicket($payload),
            'download_file' => $this->download($payload),
            'user_info' => $this->userInfo($payload),
            'user_update' => $this->userUpdate($payload),
            'asset_logs' => $this->assetLogs($payload),
            'balance' => $this->balance(),
            'publish_categories' => $this->publishCategories(),
            'publish_list' => $this->publishMyList(),
            'publish_inspect' => $this->publishInspect($payload),
            'publish' => $this->publish($payload),
            'publish_submit' => $this->publishSubmit($payload),
            'publish_status' => $this->publishStatus($payload),
            'verify_personal' => $this->verifyPersonal($payload),
            'verify_enterprise' => $this->verifyEnterprise($payload),
            'upload_image' => $this->uploadImage($payload),
            'build_mp_upload' => $this->mpUpload($routeParams['id'], $payload),
            'build_mp_upload_retry' => $this->mpUploadRetry($routeParams['id'], $payload),
            'build_mp_artifact_url' => $this->mpArtifactUrl($routeParams['id']),
            'task' => $this->task($routeParams['id']),
            'plugin_install' => $this->dispatchLifecycleAction($this, 'install', $routeParams['code'], $payload),
            'plugin_upgrade' => $this->dispatchLifecycleAction($this, 'upgrade', $routeParams['code'], $payload),
            'plugin_disable' => $this->dispatchLifecycleAction($this, 'disable', $routeParams['code'], $payload),
            'plugin_uninstall' => $this->dispatchLifecycleAction($this, 'uninstall', $routeParams['code'], $payload),
            'plugin_rollback' => $this->dispatchLifecycleAction($this, 'rollback', $routeParams['code'], $payload),
            default => throw new AdminResponseException('未注册的插件网关方法'),
        };
    }

    /**
     * 提取并移除路由控制动作，避免控制字段继续透传到业务层。
     *
     * @param array<string,mixed> $payload 业务载荷；会移除 method 字段。
     * @return string 格式通过校验的网关动作名。
     */
    private function resolveMethod(array &$payload): string
    {
        $method = is_string($payload['method'] ?? null) ? trim($payload['method']) : '';
        unset($payload['method']);
        if (preg_match('/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/', $method) !== 1 || strlen($method) > 64) {
            throw new AdminResponseException('插件网关方法无效');
        }
        return $method;
    }

    /**
     * 核对动作与已认证逻辑路径的绑定，并提取动态参数。
     *
     * @param string $method 经过格式校验的网关动作名。
     * @param PluginRequestEnvelope $envelope 已认证的逻辑请求信封。
     * @return array<string,string> 匹配的动态路由参数；静态路由返回空数组。
     */
    private function resolveRouteParams(string $method, PluginRequestEnvelope $envelope): array
    {
        $routeKey = $envelope->logicalMethod . ' ' . $envelope->logicalPath;

        return match ($method) {
            'installed' => $this->assertStaticRoute($routeKey, 'GET /api/admin/plugins/installed'),
            'developer_list' => $this->assertStaticRoute($routeKey, 'GET /api/admin/plugins/developer'),
            'developer_install' => $this->matchRoute($routeKey, '#^POST /api/admin/plugins/developer/(?<code>[a-z][a-z0-9_]{1,63})/install$#'),
            'developer_uninstall' => $this->matchRoute($routeKey, '#^POST /api/admin/plugins/developer/(?<code>[a-z][a-z0-9_]{1,63})/uninstall$#'),
            'catalog_categories' => $this->assertStaticRoute($routeKey, 'GET /api/admin/plugins/catalog/categories'),
            'catalog_list' => $this->assertStaticRoute($routeKey, 'GET /api/admin/plugins/catalog/list'),
            'catalog_featured' => $this->assertStaticRoute($routeKey, 'GET /api/admin/plugins/catalog/featured'),
            'catalog_detail' => $this->matchRoute($routeKey, '#^GET /api/admin/plugins/catalog/detail/(?<code>[a-z][a-z0-9_-]{0,63})$#'),
            'auth_sms_scene' => $this->assertStaticRoute($routeKey, 'POST /api/admin/plugins/auth/sms-scene'),
            'auth_sms_send' => $this->assertStaticRoute($routeKey, 'POST /api/admin/plugins/auth/sms-send'),
            'auth_phone_login' => $this->assertStaticRoute($routeKey, 'POST /api/admin/plugins/auth/phone-login'),
            'auth_account_login' => $this->assertStaticRoute($routeKey, 'POST /api/admin/plugins/auth/account-login'),
            'open_auth_info' => $this->assertStaticRoute($routeKey, 'GET /api/admin/plugins/auth/info'),
            'open_auth_logout' => $this->assertStaticRoute($routeKey, 'POST /api/admin/plugins/auth/logout'),
            'order_list' => $this->assertStaticRoute($routeKey, 'GET /api/admin/plugins/orders'),
            'order_create' => $this->assertStaticRoute($routeKey, 'POST /api/admin/plugins/orders/create'),
            'order_pay' => $this->assertStaticRoute($routeKey, 'POST /api/admin/plugins/orders/pay'),
            'order_status' => $this->assertStaticRoute($routeKey, 'GET /api/admin/plugins/orders/status'),
            'order_cancel' => $this->assertStaticRoute($routeKey, 'POST /api/admin/plugins/orders/cancel'),
            'license_list' => $this->assertStaticRoute($routeKey, 'GET /api/admin/plugins/orders/licenses'),
            'download_list' => $this->assertStaticRoute($routeKey, 'GET /api/admin/plugins/orders/downloads'),
            'download_ticket' => $this->assertStaticRoute($routeKey, 'POST /api/admin/plugins/orders/download/ticket'),
            'download_file' => $this->assertStaticRoute($routeKey, 'GET /api/admin/plugins/download'),
            'user_info' => $this->assertStaticRoute($routeKey, 'GET /api/admin/plugins/user-info'),
            'user_update' => $this->assertStaticRoute($routeKey, 'POST /api/admin/plugins/user-update'),
            'asset_logs' => $this->assertStaticRoute($routeKey, 'GET /api/admin/plugins/asset-logs'),
            'balance' => $this->assertStaticRoute($routeKey, 'GET /api/admin/plugins/balance'),
            'publish_categories' => $this->assertStaticRoute($routeKey, 'GET /api/admin/plugins/publish/categories'),
            'publish_list' => $this->assertStaticRoute($routeKey, 'GET /api/admin/plugins/publish/my-list'),
            'publish_inspect' => $this->assertStaticRoute($routeKey, 'POST /api/admin/plugins/publish/inspect'),
            'publish' => $this->assertStaticRoute($routeKey, 'POST /api/admin/plugins/publish'),
            'publish_submit' => $this->assertStaticRoute($routeKey, 'POST /api/admin/plugins/publish/submit'),
            'publish_status' => $this->assertStaticRoute($routeKey, 'POST /api/admin/plugins/publish/status'),
            'verify_personal' => $this->assertStaticRoute($routeKey, 'POST /api/admin/plugins/verify/personal'),
            'verify_enterprise' => $this->assertStaticRoute($routeKey, 'POST /api/admin/plugins/verify/enterprise'),
            'upload_image' => $this->assertStaticRoute($routeKey, 'POST /api/admin/plugins/upload/image'),
            'build_mp_upload' => $this->matchRoute($routeKey, '#^POST /api/admin/plugins/plugin-builds/(?<id>\d+)/mp-upload$#'),
            'build_mp_upload_retry' => $this->matchRoute($routeKey, '#^POST /api/admin/plugins/plugin-builds/(?<id>\d+)/mp-upload/retry$#'),
            'build_mp_artifact_url' => $this->matchRoute($routeKey, '#^GET /api/admin/plugins/plugin-builds/(?<id>\d+)/mp-artifact-url$#'),
            'task' => $this->matchRoute($routeKey, '#^GET /api/admin/plugins/tasks/(?<id>[A-Za-z0-9._:-]{1,64})$#'),
            'plugin_install' => $this->matchRoute($routeKey, '#^POST /api/admin/plugins/(?<code>[a-z][a-z0-9_-]{0,63})/install$#'),
            'plugin_upgrade' => $this->matchRoute($routeKey, '#^POST /api/admin/plugins/(?<code>[a-z][a-z0-9_-]{0,63})/upgrade$#'),
            'plugin_disable' => $this->matchRoute($routeKey, '#^POST /api/admin/plugins/(?<code>[a-z][a-z0-9_-]{0,63})/disable$#'),
            'plugin_uninstall' => $this->matchRoute($routeKey, '#^POST /api/admin/plugins/(?<code>[a-z][a-z0-9_-]{0,63})/uninstall$#'),
            'plugin_rollback' => $this->matchRoute($routeKey, '#^POST /api/admin/plugins/(?<code>[a-z][a-z0-9_-]{0,63})/rollback$#'),
            default => throw new AdminResponseException('未注册的插件网关方法'),
        };
    }

    /**
     * 要求静态动作与逻辑方法、路径完全一致。
     *
     * @param string $routeKey 信封中的逻辑方法与路径组合。
     * @param string $expectedRoute 白名单允许的完整逻辑路由。
     * @return array<string,string> 空路由参数；不一致时抛出异常。
     */
    private function assertStaticRoute(string $routeKey, string $expectedRoute): array
    {
        if ($routeKey !== $expectedRoute) {
            throw new AdminResponseException('插件网关方法与逻辑路径不匹配');
        }
        return [];
    }

    /**
     * 按固定模式匹配逻辑路由并提取动态参数。
     *
     * @param string $routeKey 信封中的逻辑方法与路径组合。
     * @param string $pattern 服务器定义的完整路由匹配模式。
     * @return array<string,string> 匹配结果及命名动态参数。
     */
    private function matchRoute(string $routeKey, string $pattern): array
    {
        $matches = [];
        if (preg_match($pattern, $routeKey, $matches) !== 1) {
            throw new AdminResponseException('插件网关方法与逻辑路径不匹配');
        }
        return $matches;
    }

    /**
     * 按生命周期动作白名单调用内部控制器，不允许任意方法执行。
     *
     * @param Plugin $controller 内部复用的插件控制器。
     * @param string $action 已允许的生命周期动作。
     * @param string $pluginCode 已认证逻辑路径中的插件编码。
     * @param array<string,mixed> $payload 经过验证的生命周期请求字段。
     * @return Response 对应生命周期动作的响应。
     */
    private function dispatchLifecycleAction(Plugin $controller, string $action, string $pluginCode, array $payload): Response
    {
        return match ($action) {
            'install' => $controller->install($pluginCode, $payload),
            'upgrade' => $controller->upgrade($pluginCode, $payload),
            'disable' => $controller->disable($pluginCode, $payload),
            'uninstall' => $controller->uninstall($pluginCode, $payload),
            'rollback' => $controller->rollback($pluginCode, $payload),
            default => throw new AdminResponseException('未注册的插件逻辑路由'),
        };
    }

    /**
     * 组装当前实例浏览器加密公钥元数据，不包含私钥。
     *
     * @return array<string,mixed> 协议、实例身份和公开密钥字段。
     */
    private function buildPublicKeyPayload(): array
    {
        // 首次打开时自动补一把实例加密钥，避免新环境还要手工跑初始化脚本。
        $metadata = $this->keyService->ensureLocalKeyPair(
            PluginKeyService::KEY_USE_ENCRYPTION,
            $this->keyService->instanceId()
        );
        return [
            'protocol_version' => config('plugin.security.protocol_version', '1.0'),
            'owner_type' => (string)($metadata['owner_type'] ?? PluginKeyService::OWNER_INSTANCE),
            'owner_id' => (string)($metadata['owner_id'] ?? $this->keyService->instanceId()),
            'key_use' => (string)($metadata['key_use'] ?? PluginKeyService::KEY_USE_ENCRYPTION),
            'algorithm' => (string)($metadata['algorithm'] ?? PluginKeyService::ALGORITHM_RSA_OAEP_256),
            'kid' => (string)($metadata['key_id'] ?? ''),
            'public_key_pem' => (string)($metadata['public_key_pem'] ?? ''),
            'not_before' => (string)($metadata['not_before'] ?? ''),
            'expires_at' => $metadata['expires_at'] ?? null,
        ];
    }

    /**
     * 合并可信操作人和固定插件编码，生成统一生命周期请求。
     *
     * @param string $pluginCode 由逻辑路径固定的插件编码。
     * @param array<string,mixed> $payload 经过白名单约束的生命周期字段。
     * @param string $taskType 本次生命周期操作类型。
     * @return PluginLifecycleRequest 已校验的生命周期请求对象。
     */
    private function buildRequest(string $pluginCode, array $payload, string $taskType): PluginLifecycleRequest
    {
        $payload = array_merge($payload, $this->buildContext(), [
            'plugin_code' => $pluginCode,
            'task_type' => $taskType,
        ]);
        return PluginLifecycleRequest::fromArray($payload, $pluginCode);
    }

    /**
     * 从后台认证和安全信封补充可信操作人与请求幂等上下文。
     *
     * @return array<string,mixed> 平台来源、操作人、请求 ID 及可选幂等键。
     */
    private function buildContext(): array
    {
        $requestId = '';
        if (isset($this->request->pluginRequestId)) {
            $requestId = trim((string)$this->request->pluginRequestId);
        }
        if ($requestId === '') {
            $requestId = trim((string)($this->request->header('x-plugin-request-id', '') ?? ''));
        }

        $context = [
            'surface' => 'admin',
            'request_id' => $requestId,
            'actor' => [
                'type' => 'admin',
                'id' => (int)($this->request->adminId ?? 0),
            ],
        ];
        $envelope = $this->request->pluginEnvelope ?? null;
        if ($envelope instanceof PluginRequestEnvelope && $envelope->getIdempotencyKey() !== '') {
            $context['idempotency_key'] = $envelope->getIdempotencyKey();
        }
        return $context;
    }

    /**
     * 仅信任后台鉴权中间件注入的超级管理员，不读取客户端操作人字段。
     *
     * @return void 无返回值；不具备权限时抛出异常。
     */
    private function assertDeveloperAdministrator(): void
    {
        if (!$this->isDeveloperAdministrator()) {
            throw new AdminResponseException('仅超级管理员可安装、重装、下载或卸载开发者插件');
        }
    }

    /**
     * 检查真实认证管理员 ID 和根级管理员身份。
     *
     * @return bool 当前请求是否来自已认证超级管理员。
     */
    private function isDeveloperAdministrator(): bool
    {
        $admin = $this->request->adminInfo ?? null;
        return (int)($this->request->adminId ?? 0) > 0 && is_array($admin) && ($admin['pid'] ?? null) === 0;
    }
}
