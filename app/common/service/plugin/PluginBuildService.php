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
use CURLFile;

/**
 * 打包服务（build-service）薄代理。
 *
 * 源码包、构建任务、日志与产物都保留在打包服务侧，本服务只负责把后台请求翻译成远端
 * HTTP 调用，并做参数白名单与响应整形；调用身份直接复用当前管理员绑定的官网账号
 * token（见 PluginMarketService::openAuthToken），因此远端能审计到发起人、按用户限流，
 * 上传包也只能被同一个账号引用。
 */
class PluginBuildService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 创建构建任务时允许传出的参数；未知参数直接报错，避免拼写错误被静默忽略。
     */
    private const BUILD_PARAMS = [
        'target',
        'source',
        'upload_id',
        'appid',
        'version',
        'desc',
        'robot',
        'backend',
        'install',
        'wait',
        'timeout',
        'artifact',
        'skip_build',
        'keep_workspace',
    ];

    /**
     * 打包目标与别名，取值需与打包服务 config/targets.js 保持一致；
     * 远端新增目标时同步这里（GET targets 接口始终返回远端实时可用列表）。
     */
    private const TARGETS = [
        'vue3', 'vue', 'vite', 'vue3-admin', 'admin',
        'uniapp-h5', 'h5', 'uniapp', 'web', 'uniapp-web',
        'uniapp-mp', 'mp', 'mp-weixin', 'miniprogram', 'mini', 'uniapp-mp-weixin',
        'mp-upload', 'upload', 'cli-upload', 'mp-cli-upload',
    ];

    /**
     * 任务状态白名单，用于任务列表筛选。
     */
    private const BUILD_STATUSES = ['pending', 'running', 'succeeded', 'failed', 'canceled'];

    /**
     * 依赖安装策略，对应打包服务的 install 三态。
     */
    private const INSTALL_MODES = ['auto', 'true', 'false'];

    /**
     * uniapp 目标的构建后端。
     */
    private const UNIAPP_BACKENDS = ['auto', 'cli', 'hbuilderx'];

    /**
     * 任务编号：打包服务形如 b20260910212025a6d7，严格校验后才允许拼进请求路径。
     */
    private const TASK_ID_PATTERN = '/^[0-9a-z]{4,64}$/i';

    /**
     * 上传编号：打包服务形如 u2026091021495345ce。
     */
    private const UPLOAD_ID_PATTERN = '/^u[0-9a-z]{4,64}$/i';

    /**
     * 小程序 appid 格式。
     */
    private const APPID_PATTERN = '/^wx[0-9a-f]{16}$/i';

    /**
     * 版本号规则，与打包服务 mp-upload 目标一致。
     */
    private const VERSION_PATTERN = '/^[0-9A-Za-z][0-9A-Za-z._-]{0,63}$/';

    /**
     * 远端错误信息允许回传的最大长度。
     */
    private const REMOTE_MESSAGE_LIMIT = 200;

    private ?PluginMarketService $marketService = null;

    /**
     * 本服务不读写本地表，模型仅用于满足资源化服务契约。
     */
    protected function setModel(): string
    {
        return Plugin::class;
    }

    /**
     * 可用打包目标与探测结果。
     *
     * @return array<string,mixed>
     */
    public function targets(): array
    {
        return $this->requestJson('GET', '/api/targets');
    }

    /**
     * 上传源码包。
     *
     * multipart 与原始字节流两种方式都支持，二选一；本地先做体积与 zip 魔数校验，
     * 远端还会按 zip slip、解压体积、文件数再校验一次，两层都保留。
     *
     * @param string $filePath 本地临时文件路径，原始字节流模式传空字符串
     * @param string $fileName 传给打包服务的文件名
     * @param string $rawBody 原始字节流内容，multipart 模式传空字符串
     * @return array<string,mixed>
     */
    public function uploadSource(string $filePath, string $fileName = '', string $rawBody = ''): array
    {
        if ($filePath === '' && $rawBody === '') {
            throw new AdminResponseException('请选择要上传的源码压缩包');
        }

        $this->assertSourceUploadEnabled();
        $maxBytes = $this->uploadMaxBytes();
        if ($filePath !== '') {
            $size = is_file($filePath) ? (int)filesize($filePath) : 0;
            $magic = $size > 0 ? (string)file_get_contents($filePath, false, null, 0, 2) : '';
            $this->assertZipPayload($size, $magic, $maxBytes);
            $name = $this->normalizeUploadName($fileName);

            return $this->requestJson('POST', '/api/uploads', [
                'file' => new CURLFile($filePath, 'application/zip', $name),
            ], $this->longTimeout());
        }

        $this->assertZipPayload(strlen($rawBody), substr($rawBody, 0, 2), $maxBytes);

        return $this->requestJson('POST', '/api/uploads', [
            'raw' => $rawBody,
            'content_type' => 'application/octet-stream',
        ], $this->longTimeout());
    }

    /**
     * 创建构建任务。
     *
     * @param array<string,mixed> $payload 请求参数
     * @return array<string,mixed>
     */
    public function create(array $payload = []): array
    {
        $params = $this->normalizeBuildPayload($payload);
        // wait=true 会在远端同步等构建结束，超时必须覆盖构建耗时。
        $timeout = !empty($params['wait']) ? $this->longTimeout() : $this->timeout();

        return $this->requestJson('POST', '/api/builds', $params, $timeout);
    }

    /**
     * 当前官网账号的构建任务列表。
     *
     * 打包服务默认返回所有调用方的任务，这里显式带上官网账号 uid，
     * 只展示当前管理员自己发起的任务，避免不同账号之间互相看到任务。
     *
     * @param array<string,mixed> $params 业务筛选参数
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        [$page, $limit, $defaultLimit] = $this->getPagesValue();
        $query = [
            'page' => max(1, (int)$page),
            'page_size' => $limit > 0 ? (int)$limit : (int)$defaultLimit,
            'user_id' => $this->marketUid(),
        ];

        $status = strtolower($this->normalizeText($params['status'] ?? '', 20));
        if ($status !== '') {
            if (!in_array($status, self::BUILD_STATUSES, true)) {
                throw new AdminResponseException('任务状态不正确');
            }
            $query['status'] = $status;
        }

        $target = strtolower($this->normalizeText($params['target'] ?? '', 32));
        if ($target !== '') {
            $query['target'] = $this->normalizeTarget($target);
        }

        return $this->requestJson('GET', '/api/builds', [], $this->timeout(), $query);
    }

    /**
     * 构建任务详情。
     *
     * @param int|string $id 任务编号
     * @param bool $withLog 是否附带日志尾部
     * @return array<string,mixed>
     */
    public function detail(int|string $id, bool $withLog = false): array
    {
        $taskId = $this->normalizeTaskId($id);
        $query = $withLog ? ['log' => 1] : [];

        return $this->requestJson('GET', '/api/builds/' . $taskId, [], $this->timeout(), $query);
    }

    /**
     * 构建日志（远端为 text/plain，这里包装成结构化字段）。
     *
     * @param int|string $id 任务编号
     * @param int $tail 读取的日志尾部行数
     * @return array<string,mixed>
     */
    public function log(int|string $id, int $tail = 200): array
    {
        $taskId = $this->normalizeTaskId($id);
        $tail = max(1, min(2000, $tail));

        $response = $this->send('GET', '/api/builds/' . $taskId . '/log', [
            'query' => ['tail' => $tail],
            'timeout' => $this->timeout(),
        ]);
        $this->assertSuccess($response);

        return [
            'task_id' => $taskId,
            'tail' => $tail,
            'content' => (string)$response['body'],
        ];
    }

    /**
     * 下载产物 zip 的二进制内容。
     *
     * @param int|string $id 任务编号
     * @return array<string,mixed> content/name/mime
     */
    public function artifact(int|string $id): array
    {
        $taskId = $this->normalizeTaskId($id);
        $maxBytes = $this->artifactMaxBytes();

        $response = $this->send('GET', '/api/builds/' . $taskId . '/artifact', [
            'timeout' => $this->longTimeout(),
            'max_bytes' => $maxBytes,
        ]);
        $this->assertSuccess($response);

        $content = (string)$response['body'];
        if ($content === '') {
            throw new AdminResponseException('打包服务返回的产物内容为空');
        }

        return [
            'task_id' => $taskId,
            'content' => $content,
            'name' => $this->normalizeArtifactName((string)$response['filename'], $taskId),
            'mime' => $response['content_type'] !== '' ? $response['content_type'] : 'application/zip',
        ];
    }

    /**
     * 取消任务。
     *
     * @param int|string $id 任务编号
     * @return array<string,mixed>
     */
    public function cancel(int|string $id): array
    {
        $taskId = $this->normalizeTaskId($id);

        return $this->requestJson('POST', '/api/builds/' . $taskId . '/cancel', [], $this->timeout());
    }

    /**
     * 上传小程序私钥（一次性凭据，仅 mp-upload 目标需要）。
     *
     * 私钥只在内存中转发，不落盘、不回显、不写日志。
     *
     * @param array<string,mixed> $payload 表单或 JSON 参数
     * @param string $keyFilePath 私钥临时文件路径（multipart 模式）
     * @return array<string,mixed>
     */
    public function uploadMpCredential(array $payload = [], string $keyFilePath = ''): array
    {
        $unknown = array_values(array_diff(array_keys($payload), ['appid', 'description', 'private_key']));
        if ($unknown !== []) {
            throw new AdminResponseException('不支持的参数：' . implode('、', $unknown));
        }

        $appid = trim((string)($payload['appid'] ?? ''));
        if (preg_match(self::APPID_PATTERN, $appid) !== 1) {
            throw new AdminResponseException('小程序 appid 格式不正确');
        }
        $description = $this->normalizeText($payload['description'] ?? '', 100);

        if ($keyFilePath === '') {
            $privateKey = trim((string)($payload['private_key'] ?? ''));
            if ($privateKey === '') {
                throw new AdminResponseException('请上传小程序私钥文件');
            }
            if (strlen($privateKey) > 64 * 1024) {
                throw new AdminResponseException('小程序私钥内容过大');
            }

            return $this->requestJson('POST', '/api/mp-credentials', array_filter([
                'appid' => $appid,
                'description' => $description,
                'private_key' => $privateKey,
            ], static fn (mixed $value): bool => $value !== ''), $this->longTimeout());
        }

        $keyName = $this->normalizeUploadName(basename($keyFilePath));
        if (preg_match('/\.(key|pem|txt)$/i', $keyName) !== 1) {
            $keyName = 'private.key';
        }

        return $this->requestJson('POST', '/api/mp-credentials', array_filter([
            'appid' => $appid,
            'description' => $description,
            'file' => new CURLFile($keyFilePath, 'application/octet-stream', $keyName),
        ], static fn (mixed $value): bool => $value !== ''), $this->longTimeout());
    }

    /**
     * 校验并整形创建构建任务的参数。
     *
     * @param array<string,mixed> $payload 原始请求参数
     * @return array<string,mixed>
     */
    private function normalizeBuildPayload(array $payload): array
    {
        $unknown = array_values(array_diff(array_keys($payload), self::BUILD_PARAMS));
        if ($unknown !== []) {
            throw new AdminResponseException(
                '不支持的参数：' . implode('、', $unknown)
                . '。可用参数：' . implode(', ', self::BUILD_PARAMS)
            );
        }

        $params = ['target' => $this->normalizeTarget((string)($payload['target'] ?? ''))];

        $source = $this->normalizeSource($payload);
        if ($source !== '') {
            $params['source'] = $source;
        }

        $appid = trim((string)($payload['appid'] ?? ''));
        if ($appid !== '') {
            if (preg_match(self::APPID_PATTERN, $appid) !== 1) {
                throw new AdminResponseException('小程序 appid 格式不正确');
            }
            $params['appid'] = $appid;
        }

        $version = $this->normalizeText($payload['version'] ?? '', 64);
        if ($version !== '') {
            if (preg_match(self::VERSION_PATTERN, $version) !== 1) {
                throw new AdminResponseException('version 仅允许字母、数字、点、下划线、短横线，且以字母或数字开头，最长 64 位');
            }
            $params['version'] = $version;
        }

        $desc = $this->normalizeText($payload['desc'] ?? '', 200);
        if ($desc !== '') {
            $params['desc'] = $desc;
        }

        if (array_key_exists('robot', $payload) && $payload['robot'] !== '' && $payload['robot'] !== null) {
            $robot = $this->normalizeInt($payload['robot'], 1, 30, 'robot 取值需为 1-30 的整数');
            $params['robot'] = $robot;
        }

        $backend = strtolower($this->normalizeText($payload['backend'] ?? '', 16));
        if ($backend !== '') {
            if (!in_array($backend, self::UNIAPP_BACKENDS, true)) {
                throw new AdminResponseException('backend 取值需为 auto / cli / hbuilderx');
            }
            $params['backend'] = $backend;
        }

        if (array_key_exists('install', $payload) && $payload['install'] !== '' && $payload['install'] !== null) {
            $params['install'] = $this->normalizeInstallMode($payload['install']);
        }

        if (array_key_exists('wait', $payload)) {
            $params['wait'] = $this->normalizeBool($payload['wait'], 'wait');
        }

        if (array_key_exists('timeout', $payload) && $payload['timeout'] !== '' && $payload['timeout'] !== null) {
            $params['timeout'] = $this->normalizeInt($payload['timeout'], 1, 600, 'timeout 取值需为 1-600 的整数');
        }

        foreach (['artifact', 'skip_build', 'keep_workspace'] as $boolKey) {
            if (array_key_exists($boolKey, $payload)) {
                $params[$boolKey] = $this->normalizeBool($payload[$boolKey], $boolKey);
            }
        }

        // wait=true 时必须有超时兜底，否则远端会用自身默认值同步等待。
        if (!empty($params['wait']) && !isset($params['timeout'])) {
            $params['timeout'] = min(600, $this->longTimeout());
        }

        return $params;
    }

    /**
     * 规范化打包目标（含别名）。
     */
    private function normalizeTarget(string $target): string
    {
        $target = strtolower(trim($target));
        if ($target === '') {
            return 'vue3';
        }
        if (!in_array($target, self::TARGETS, true)) {
            throw new AdminResponseException('不支持的打包目标：' . $target);
        }

        return $target;
    }

    /**
     * 规范化源码包引用；未传 source 时返回空字符串。
     *
     * @param array<string,mixed> $payload 请求参数
     */
    private function normalizeSource(array $payload): string
    {
        $source = $payload['source'] ?? ($payload['upload_id'] ?? '');
        if (is_array($source)) {
            $source = $source['upload_id'] ?? ($source['uploadId'] ?? ($source['id'] ?? ''));
        }
        if (is_bool($source) || is_object($source)) {
            throw new AdminResponseException('source 需要是上传接口返回的 upload_id');
        }

        $source = trim((string)$source);
        if ($source === '') {
            return '';
        }

        if (preg_match(self::UPLOAD_ID_PATTERN, $source) !== 1) {
            throw new AdminResponseException('源码包编号格式不正确，请使用上传接口返回的 upload_id');
        }
        $this->assertSourceUploadEnabled();

        return $source;
    }

    /**
     * 任务编号白名单校验，避免拼接进请求路径。
     */
    private function normalizeTaskId(int|string $id): string
    {
        $taskId = trim((string)$id);
        if (preg_match(self::TASK_ID_PATTERN, $taskId) !== 1) {
            throw new AdminResponseException('任务编号不正确');
        }

        return strtolower($taskId);
    }

    /**
     * install 三态：auto / true / false。
     */
    private function normalizeInstallMode(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $mode = strtolower(trim((string)$value));
        if ($mode === 'default') {
            $mode = 'auto';
        } elseif (in_array($mode, ['1', 'yes', 'on', 'force'], true)) {
            $mode = 'true';
        } elseif (in_array($mode, ['0', 'no', 'off', 'never'], true)) {
            $mode = 'false';
        }
        if (!in_array($mode, self::INSTALL_MODES, true)) {
            throw new AdminResponseException('install 取值需为 auto / true / false');
        }

        return $mode;
    }

    /**
     * 宽松布尔解析，接受 true/false、1/0、yes/no、on/off。
     */
    private function normalizeBool(mixed $value, string $name): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string)$value));
        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false', 'no', 'off', ''], true)) {
            return false;
        }

        throw new AdminResponseException($name . ' 需要是布尔值（true/false）');
    }

    /**
     * 整数范围校验。
     */
    private function normalizeInt(mixed $value, int $min, int $max, string $message): int
    {
        if (is_bool($value) || (is_string($value) && preg_match('/^\d+$/', trim($value)) !== 1)) {
            throw new AdminResponseException($message);
        }
        if (!is_int($value) && !is_numeric($value)) {
            throw new AdminResponseException($message);
        }

        $number = (int)$value;
        if ($number < $min || $number > $max) {
            throw new AdminResponseException($message);
        }

        return $number;
    }

    /**
     * 文本规范化：去控制字符、按长度截断校验。
     */
    private function normalizeText(mixed $value, int $maxLength): string
    {
        if (is_array($value) || is_object($value)) {
            return '';
        }

        $text = trim((string)$value);
        $text = preg_replace('/[\x00-\x1F\x7F]/', '', $text) ?? '';
        if (mb_strlen($text) > $maxLength) {
            throw new AdminResponseException('参数长度超出限制（最多 ' . $maxLength . ' 个字符）');
        }

        return $text;
    }

    /**
     * 上传文件名规范化，避免把本地路径或控制字符带到远端。
     */
    private function normalizeUploadName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\]/', '', trim($name)) ?? '';
        $name = trim($name);
        if ($name === '') {
            return 'source.zip';
        }

        return mb_substr($name, -120);
    }

    /**
     * 产物文件名规范化，只能取文件名本身，不能带路径。
     */
    private function normalizeArtifactName(string $name, string $taskId): string
    {
        $name = $this->normalizeUploadName($name);
        if (preg_match('/\.zip$/i', $name) !== 1) {
            return 'build-' . $taskId . '.zip';
        }

        return $name;
    }

    /**
     * 源码包体积与 zip 魔数校验。
     */
    private function assertZipPayload(int $size, string $magic, int $maxBytes): void
    {
        if ($size <= 0) {
            throw new AdminResponseException('源码压缩包内容为空');
        }
        if ($size > $maxBytes) {
            throw new AdminResponseException(
                '源码压缩包超过上限（' . $this->megabytes($maxBytes) . 'MB），请拆分或调整 PLUGIN_BUILD_UPLOAD_MAX_MB'
            );
        }
        // zip 以 PK 开头；上传的必须是 zip，非 zip 内容不转发给远端。
        if ($magic !== 'PK') {
            throw new AdminResponseException('源码包必须是 zip 压缩包');
        }
    }

    /**
     * 上传源码包开关。
     */
    private function assertSourceUploadEnabled(): void
    {
        if (!(bool)config('plugin.build_service.allow_source_upload', true)) {
            throw new AdminResponseException('本实例已关闭上传源码包打包（PLUGIN_BUILD_ALLOW_SOURCE_UPLOAD=0）');
        }
    }

    /**
     * 发起远端请求并返回数据段。
     *
     * @param array<string,mixed> $body 请求体参数
     * @param array<string,mixed> $query 查询参数
     * @return array<string,mixed>
     */
    private function requestJson(string $method, string $path, array $body = [], ?int $timeout = null, array $query = []): array
    {
        $options = ['timeout' => $timeout ?? $this->timeout()];
        if ($query !== []) {
            $options['query'] = $query;
        }
        if ($body !== []) {
            if (isset($body['raw'])) {
                $options['raw'] = (string)$body['raw'];
                $options['content_type'] = (string)($body['content_type'] ?? 'application/octet-stream');
            } elseif ($this->hasCurlFile($body)) {
                $options['multipart'] = $body;
            } else {
                $options['json'] = $body;
            }
        }

        $response = $this->send($method === 'GET' ? 'GET' : 'POST', $path, $options);
        $this->assertSuccess($response);

        return $this->decodeEnvelope($response);
    }

    /**
     * 请求是否包含文件上传。
     *
     * @param array<string,mixed> $body 请求体
     */
    private function hasCurlFile(array $body): bool
    {
        foreach ($body as $value) {
            if ($value instanceof CURLFile) {
                return true;
            }
        }

        return false;
    }

    /**
     * 解析响应信封（与官网一致：message/status/data）。
     *
     * @param array<string,mixed> $response 传输层结果
     * @return array<string,mixed>
     */
    private function decodeEnvelope(array $response): array
    {
        $body = (string)$response['body'];
        if ($body === '') {
            return [];
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new AdminResponseException(
                '打包服务返回格式错误（HTTP ' . $response['status'] . '），请检查打包服务地址与网关配置'
            );
        }

        $data = $decoded['data'] ?? [];
        if (is_array($data)) {
            return $data;
        }
        if (is_scalar($data) || $data === null) {
            return $data === null || $data === [] ? [] : ['value' => $data];
        }

        return [];
    }

    /**
     * 校验远端响应为成功：HTTP 与信封状态都必须是 2xx。
     *
     * @param array<string,mixed> $response 传输层结果
     */
    private function assertSuccess(array $response): void
    {
        $status = (int)$response['status'];
        $bodyStatus = (int)($response['body_status'] ?? 0);
        $failed = $status < 200 || $status >= 300
            || ($bodyStatus !== 0 && ($bodyStatus < 200 || $bodyStatus >= 300));

        if (!$failed) {
            return;
        }

        $message = (string)($response['message'] ?? '');
        if ($message !== '') {
            throw new AdminResponseException($message);
        }
        if ($status === 404) {
            throw new AdminResponseException('打包服务接口不存在（HTTP 404），请检查 PLUGIN_BUILD_ENDPOINT 是否正确');
        }
        if ($status === 413) {
            // 常见于 Nginx client_max_body_size 小于源码包体积，调用方看到的是网关错误页而不是服务端 JSON。
            throw new AdminResponseException('源码包被网关拦下（HTTP 413），请确认打包服务侧的 client_max_body_size 不小于 PLUGIN_BUILD_UPLOAD_MAX_MB');
        }

        throw new AdminResponseException('打包服务返回异常状态（HTTP ' . $status . '）');
    }

    /**
     * 传输层：一次 cURL 请求。
     *
     * @param array<string,mixed> $options json/multipart/raw/content_type/query/timeout/max_bytes
     * @return array<string,mixed> status/body/body_status/message/content_type/filename
     */
    private function send(string $method, string $path, array $options = []): array
    {
        $url = $this->buildUrl($path, is_array($options['query'] ?? null) ? $options['query'] : []);
        // 真正发起请求前再校验一次最终地址，避免配置或拼接意外把 token 发到明文地址。
        $this->assertSecureUrl($url);
        $timeout = max(3, (int)($options['timeout'] ?? $this->timeout()));
        $maxBytes = max(0, (int)($options['max_bytes'] ?? 0));
        $token = $this->authToken();

        $headers = [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ];
        if (isset($options['raw'])) {
            $headers[] = 'Content-Type: ' . (string)($options['content_type'] ?? 'application/octet-stream');
        }

        $curl = curl_init($url);
        if ($curl === false) {
            throw new AdminResponseException('打包服务请求初始化失败');
        }

        $body = '';
        $responseHeaders = [];
        $overflow = false;

        $payload = null;
        if (isset($options['json'])) {
            $payload = json_encode($options['json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($payload === false) {
                curl_close($curl);
                throw new AdminResponseException('打包服务请求参数编码失败');
            }
            $headers[] = 'Content-Type: application/json';
        } elseif (isset($options['multipart'])) {
            // 交给 cURL 组装 multipart，不能手工指定 Content-Type。
            $payload = $options['multipart'];
        } elseif (isset($options['raw'])) {
            $payload = (string)$options['raw'];
        }

        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method === 'GET' ? 'GET' : 'POST',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $headers,
            // 回传内容不进内存无上限堆积：超过上限直接中断下载。
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, &$overflow, $maxBytes): int {
                if ($maxBytes > 0 && strlen($body) + strlen($chunk) > $maxBytes) {
                    $overflow = true;

                    return 0;
                }
                $body .= $chunk;

                return strlen($chunk);
            },
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$responseHeaders): int {
                $position = strpos($line, ':');
                if ($position !== false) {
                    $name = strtolower(trim(substr($line, 0, $position)));
                    $value = trim(substr($line, $position + 1));
                    if ($name !== '') {
                        $responseHeaders[$name] = $value;
                    }
                }

                return strlen($line);
            },
        ]);
        if ($payload !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        }

        curl_exec($curl);
        $errorNo = curl_errno($curl);
        $errorMessage = curl_error($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if ($errorNo !== 0) {
            if ($overflow) {
                throw new AdminResponseException(
                    '产物超过下载上限（' . $this->megabytes($maxBytes) . 'MB），请调整 PLUGIN_BUILD_ARTIFACT_MAX_MB 或到打包服务直接下载'
                );
            }
            if ($errorNo === CURLE_OPERATION_TIMEDOUT) {
                throw new AdminResponseException('打包服务请求超时（超过 ' . $timeout . ' 秒）');
            }
            // 不回显 token 与请求体；这里只带 cURL 给出的连接层原因。
            throw new AdminResponseException('打包服务请求失败：' . $this->sanitizeMessage($errorMessage));
        }

        $contentType = strtolower(trim(explode(';', (string)($responseHeaders['content-type'] ?? ''), 2)[0]));
        $bodyStatus = 0;
        $message = '';
        if ($contentType === 'application/json' || $contentType === '' || str_ends_with($contentType, '+json')) {
            $decoded = json_decode($body, true);
            if (is_array($decoded)) {
                $bodyStatus = (int)($decoded['status'] ?? 0);
                $message = $this->sanitizeMessage((string)($decoded['message'] ?? ''));
            }
        }

        return [
            'status' => $status,
            'body' => $body,
            'body_status' => $bodyStatus,
            'message' => $message,
            'content_type' => $contentType,
            'filename' => $this->parseDispositionFilename((string)($responseHeaders['content-disposition'] ?? '')),
        ];
    }

    /**
     * 解析响应头里的下载文件名（支持 filename* 与 filename）。
     */
    private function parseDispositionFilename(string $disposition): string
    {
        if ($disposition === '') {
            return '';
        }
        if (preg_match("/filename\*\s*=\s*(?:UTF-8'')?([^;]+)/i", $disposition, $matches) === 1) {
            $name = urldecode(trim($matches[1], " \t\"'"));
            if ($name !== '') {
                return $name;
            }
        }
        if (preg_match('/filename\s*=\s*"([^"]+)"/i', $disposition, $matches) === 1) {
            return trim($matches[1]);
        }

        return '';
    }

    /**
     * 远端错误信息过滤：去控制字符并限制长度。
     */
    private function sanitizeMessage(string $message): string
    {
        $message = preg_replace('/[\x00-\x1F\x7F]/', '', trim($message)) ?? '';

        return mb_substr($message, 0, self::REMOTE_MESSAGE_LIMIT);
    }

    /**
     * 拼接远端地址；endpoint 由配置提供，路径与查询都来自本地白名单参数。
     *
     * @param array<string,mixed> $query 查询参数
     */
    private function buildUrl(string $path, array $query = []): string
    {
        $endpoint = $this->resolveEndpoint();
        if (preg_match('/^\/[A-Za-z0-9._\/-]*$/', $path) !== 1 || str_contains($path, '..')) {
            throw new AdminResponseException('打包服务请求路径无效');
        }

        $url = $endpoint . '/' . ltrim($path, '/');
        if ($query !== []) {
            $filtered = array_filter($query, static fn (mixed $value): bool => $value !== '' && $value !== null && $value !== []);
            if ($filtered !== []) {
                $url .= '?' . http_build_query($filtered);
            }
        }

        return $url;
    }

    /**
     * 读取并校验打包服务地址。
     */
    private function resolveEndpoint(): string
    {
        $endpoint = trim((string)config('plugin.build_service.endpoint', ''));
        if ($endpoint === '') {
            $endpoint = trim((string)(getenv('PLUGIN_BUILD_ENDPOINT') ?: ''));
        }
        if ($endpoint === '') {
            throw new AdminResponseException('未配置打包服务地址（PLUGIN_BUILD_ENDPOINT）');
        }

        $parts = parse_url($endpoint);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if (!is_array($parts)
            || !in_array($scheme, ['http', 'https'], true)
            || trim((string)($parts['host'] ?? '')) === ''
            || isset($parts['query'])
            || isset($parts['fragment'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new AdminResponseException('打包服务地址无效，请填写完整的域名地址（不能携带查询参数）');
        }

        return rtrim($endpoint, '/');
    }

    /**
     * 传输前的 HTTPS 兜底校验。
     */
    private function assertSecureUrl(string $url): void
    {
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        if ($scheme !== 'https' && !$this->isLoopbackEndpoint($url)) {
            throw new AdminResponseException('打包服务地址必须使用 HTTPS（只有 127.0.0.1 / localhost 允许 http）');
        }
    }

    private function isLoopbackEndpoint(string $url): bool
    {
        return $this->isLoopbackHost((string)parse_url($url, PHP_URL_HOST));
    }

    private function isLoopbackHost(string $host): bool
    {
        $host = strtolower(trim($host));

        return in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
    }

    /**
     * 调用身份：当前管理员绑定的官网账号 token。
     */
    private function authToken(): string
    {
        $token = $this->marketService()->openAuthToken();
        if ($token === '') {
            throw new AdminResponseException('请先在插件市场登录官网账号');
        }

        return $token;
    }

    /**
     * 当前管理员绑定的官网账号 uid。
     */
    private function marketUid(): int
    {
        return (int)($this->marketService()->openAuthInfo()['uid'] ?? 0);
    }

    private function marketService(): PluginMarketService
    {
        if (!$this->marketService instanceof PluginMarketService) {
            $this->marketService = app()->make(PluginMarketService::class);
        }

        return $this->marketService;
    }

    private function timeout(): int
    {
        return max(3, (int)config('plugin.build_service.timeout', 20));
    }

    private function longTimeout(): int
    {
        return max($this->timeout(), (int)config('plugin.build_service.long_timeout', 600));
    }

    private function uploadMaxBytes(): int
    {
        return $this->megabytesToBytes((int)config('plugin.build_service.upload_max_mb', 200));
    }

    private function artifactMaxBytes(): int
    {
        return $this->megabytesToBytes((int)config('plugin.build_service.artifact_max_mb', 64));
    }

    private function megabytesToBytes(int $megabytes): int
    {
        return max(1, $megabytes) * 1024 * 1024;
    }

    private function megabytes(int $bytes): int
    {
        return (int)round($bytes / 1024 / 1024);
    }
}
