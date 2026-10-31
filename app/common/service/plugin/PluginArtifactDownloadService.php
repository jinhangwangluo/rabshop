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

use app\common\enum\plugin\PluginArtifactStatusEnum;
use app\common\enum\plugin\PluginTaskStatusEnum;
use app\common\enum\plugin\PluginTaskTypeEnum;
use app\common\exception\PluginLifecycleException;
use app\common\model\plugin\PluginTask;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\plugin\contract\PluginArtifactSourceInterface;
use app\common\service\plugin\contract\PluginArtifactVerifierInterface;
use app\common\service\plugin\contract\PluginLifecycleEventPublisherInterface;
use app\common\service\plugin\contract\PluginLifecycleRepositoryInterface;
use app\common\service\plugin\dto\PluginArtifactDownloadRequest;
use app\common\service\plugin\dto\PluginArtifactDownloadResult;
use app\common\service\plugin\dto\PluginLifecycleRequest;
use Throwable;

/**
 * 管理插件制品下载任务、真实文件摘要与签名校验，验证通过后才保存持久引用。
 */
class PluginArtifactDownloadService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    private PluginLifecycleRepositoryInterface $repository;
    private PluginLifecycleEventPublisherInterface $eventPublisher;
    private PluginArtifactSourceInterface $source;
    private PluginArtifactVerifierInterface $verifier;
    private string $temporaryDirectory;
    private PluginArtifactStore $artifactStore;

    /**
     * 初始化下载仓储、事件、来源、验证器及隔离文件目录。
     *
     * @param ?PluginLifecycleRepositoryInterface $repository 生命周期任务仓储。
     * @param ?PluginLifecycleEventPublisherInterface $eventPublisher 插件生命周期事件发布器。
     * @param ?PluginArtifactSourceInterface $source 已授权的制品下载来源。
     * @param ?PluginArtifactVerifierInterface $verifier 制品摘要与签名验证器。
     * @param ?string $temporaryDirectory 下载临时目录；为空时使用默认隔离目录。
     * @param ?PluginArtifactStore $artifactStore 已验证制品持久存储。
     * @return void 无返回值。
     */
    public function __construct(
        ?PluginLifecycleRepositoryInterface $repository = null,
        ?PluginLifecycleEventPublisherInterface $eventPublisher = null,
        ?PluginArtifactSourceInterface $source = null,
        ?PluginArtifactVerifierInterface $verifier = null,
        ?string $temporaryDirectory = null,
        ?PluginArtifactStore $artifactStore = null
    ) {
        $this->repository = $repository ?? new DatabasePluginLifecycleRepository();
        $this->eventPublisher = $eventPublisher ?? new PluginEventBusPublisher();
        $this->source = $source ?? new UnavailablePluginArtifactSource();
        $this->verifier = $verifier ?? new OpenSslPluginArtifactVerifier();
        $this->temporaryDirectory = $temporaryDirectory
            ?? (defined('ROOT_PATH') ? ROOT_PATH . 'runtime/plugin_downloads' : sys_get_temp_dir() . '/knowledge-payment-plugin-downloads');
        $this->artifactStore = $artifactStore ?? new PluginArtifactStore(
            defined('ROOT_PATH') ? ROOT_PATH . 'runtime/plugin_artifacts' : sys_get_temp_dir() . '/knowledge-payment-plugin-artifacts'
        );
    }

    /**
     * 指定制品下载服务的任务模型。
     *
     * @return string 插件任务模型完整类名。
     */
    protected function setModel(): string
    {
        return PluginTask::class;
    }

    /**
     * 只创建下载任务并发布请求事件，不读取远程响应体。
     *
     * @param array<string,mixed>|PluginArtifactDownloadRequest $input 下载请求字段或已校验请求对象。
     * @return array<string,mixed> 新建任务或身份一致的已有幂等任务。
     */
    public function requestDownload(array|PluginArtifactDownloadRequest $input): array
    {
        $request = $input instanceof PluginArtifactDownloadRequest
            ? $input
            : PluginArtifactDownloadRequest::fromArray($input);
        $existing = $this->repository->findTaskByIdempotency($request->idempotencyKey);
        if ($existing !== null) {
            $this->assertTask($existing, $request);
            return $existing;
        }

        $task = $this->createTask($request);
        $this->publishEvent('plugin.download.requested', $task, $request);
        return $task;
    }

    /**
     * 执行下载、真实文件摘要和签名校验，失败时保留任务审计结果。
     *
     * @param array<string,mixed>|PluginArtifactDownloadRequest $input 下载请求字段或已校验请求对象。
     * @return array<string,mixed> 下载并验证完成的任务结果。
     */
    public function download(array|PluginArtifactDownloadRequest $input): array
    {
        $request = $input instanceof PluginArtifactDownloadRequest
            ? $input
            : PluginArtifactDownloadRequest::fromArray($input);
        $task = $this->requestDownload($request);
        $taskStatus = (string)($task['status'] ?? '');
        if ($taskStatus === PluginTaskStatusEnum::SUCCEEDED
            && (($task['result_json']['phase'] ?? '') === PluginArtifactStatusEnum::VERIFIED)
        ) {
            return $task;
        }

        $temporaryPath = '';
        try {
            $this->ensureTemporaryDirectory();
            $temporaryPath = tempnam($this->temporaryDirectory, 'plugin-artifact-') ?: '';
            if ($temporaryPath === '') {
                throw new PluginLifecycleException('无法创建制品临时文件', 'ARTIFACT_TEMPFILE_FAILED');
            }

            $task = $this->updateTask($task, [
                'status' => PluginTaskStatusEnum::RUNNING,
                'progress' => 10,
                'started_at' => date('Y-m-d H:i:s'),
                'result_json' => ['phase' => PluginArtifactStatusEnum::DOWNLOADING],
            ]);
            $this->publishEvent('plugin.download.started', $task, $request);

            $result = $this->source->download($request, $temporaryPath);
            if (!$result instanceof PluginArtifactDownloadResult) {
                throw new PluginLifecycleException('下载器返回结构无效', 'ARTIFACT_SOURCE_INVALID_RESULT');
            }
            $path = $this->assertLocalArtifactPath($result->path, $temporaryPath);
            // 下载源的摘要和长度仅是声明，任务审计必须基于本地真实文件。
            $bytes = (int)(filesize($path) ?: 0);
            $sha256 = strtolower((string)hash_file('sha256', $path));
            $result = new PluginArtifactDownloadResult($path, $bytes, $sha256, $result->metadata);

            $task = $this->updateTask($task, [
                'progress' => 60,
                'artifact_sha256' => $sha256,
                'result_json' => [
                    'phase' => PluginArtifactStatusEnum::DOWNLOADED,
                    'bytes' => $bytes,
                    'sha256' => $sha256,
                ],
            ]);
            $this->publishEvent('plugin.download.succeeded', $task, $request);

            $verification = $this->verifier->verify($request, $result);
            if (($verification['verified'] ?? false) !== true) {
                throw new PluginLifecycleException('制品验证器未确认可信制品', 'ARTIFACT_VERIFICATION_REQUIRED');
            }
            $persistentReference = $this->artifactStore->retain($path, $sha256);
            $task = $this->updateTask($task, [
                'status' => PluginTaskStatusEnum::SUCCEEDED,
                'progress' => 100,
                'artifact_sha256' => $sha256,
                'result_json' => [
                    'phase' => PluginArtifactStatusEnum::VERIFIED,
                    'bytes' => $bytes,
                    'sha256' => $sha256,
                    'verification' => $this->sanitizeResult($verification),
                    'persistent_artifact_ref' => $persistentReference,
                ],
                'finished_at' => date('Y-m-d H:i:s'),
                'error_code' => '',
                'error_message' => '',
            ]);
            $this->publishEvent('plugin.download.verified', $task, $request);
            return $task;
        } catch (Throwable $failure) {
            $task = $this->markFailed($task, $request, $failure);
            throw $this->asLifecycleException($failure);
        } finally {
            if ($temporaryPath !== '' && is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    /**
     * 提供语义化的制品下载调用入口。
     *
     * @param array<string,mixed>|PluginArtifactDownloadRequest $input 下载请求字段或已校验请求对象。
     * @return array 完整下载流程的任务结果。
     */
    public function downloadArtifact(array|PluginArtifactDownloadRequest $input): array
    {
        return $this->download($input);
    }

    /**
     * 创建下载任务，唯一键竞争时仅复用身份一致的任务。
     *
     * @param PluginArtifactDownloadRequest $request 已校验的制品下载请求。
     * @return array 新建或并发创建的同身份任务。
     */
    private function createTask(PluginArtifactDownloadRequest $request): array
    {
        $taskNo = 'pt_' . date('YmdHis') . '_' . bin2hex(random_bytes(6));
        $data = [
            'task_no' => $taskNo,
            'task_type' => PluginTaskTypeEnum::DOWNLOAD,
            'plugin_code' => $request->pluginCode,
            'release_id' => $request->releaseId,
            'from_version' => '',
            'to_version' => $request->toVersion,
            'remote_task_ref' => $request->artifactRef,
            'status' => PluginTaskStatusEnum::PENDING,
            'progress' => 0,
            'idempotency_key' => $request->idempotencyKey,
            'artifact_sha256' => $request->artifactSha256,
            'data_policy' => 'retain',
            'actor_type' => $request->actor->type,
            'actor_id' => $request->actor->id,
            'payload_json' => $this->taskPayload($request),
            'result_json' => ['phase' => PluginArtifactStatusEnum::PENDING],
            'error_code' => '',
            'error_message' => '',
        ];
        try {
            return $this->repository->createTask($data);
        } catch (Throwable $exception) {
            $existing = $this->repository->findTaskByIdempotency($request->idempotencyKey);
            if ($existing !== null) {
                $this->assertTask($existing, $request);
                return $existing;
            }
            throw $exception;
        }
    }

    /**
     * 按任务唯一标识更新下载进度和验证结果。
     *
     * @param array<string,mixed> $task 原下载任务记录。
     * @param array $data 待更新的任务字段。
     * @return array 更新后的下载任务。
     */
    private function updateTask(array $task, array $data): array
    {
        $id = $task['id'] ?? $task['task_no'] ?? '';
        if ($id === '') {
            throw new PluginLifecycleException('下载任务缺少唯一标识', 'TASK_IDENTIFIER_MISSING');
        }
        return $this->repository->updateTask($id, $data);
    }

    /**
     * 核对幂等任务与当前下载身份完全一致。
     *
     * @param array<string,mixed> $task 幂等键命中的已有任务。
     * @param PluginArtifactDownloadRequest $request 当前已校验的下载请求。
     * @return void 无返回值；身份冲突时抛出异常。
     */
    private function assertTask(array $task, PluginArtifactDownloadRequest $request): void
    {
        $payload = is_array($task['payload_json'] ?? null) ? $task['payload_json'] : [];
        if ((string)($task['task_type'] ?? '') !== PluginTaskTypeEnum::DOWNLOAD
            || (string)($task['plugin_code'] ?? '') !== $request->pluginCode
            || (string)($task['to_version'] ?? '') !== $request->toVersion
            || (int)($task['release_id'] ?? 0) !== $request->releaseId
            || (string)($payload['artifact_ref'] ?? '') !== $request->artifactRef
            || (string)($payload['artifact_url'] ?? '') !== $request->artifactUrl
            || (string)($payload['expected_artifact_sha256'] ?? $task['artifact_sha256'] ?? '') !== $request->artifactSha256
            || (string)($payload['signing_key_id'] ?? '') !== $request->signingKeyId
        ) {
            throw new PluginLifecycleException('下载幂等键已用于其他任务', 'TASK_IDEMPOTENCY_CONFLICT');
        }
    }

    /**
     * 生成不含签名材料的下载任务审计载荷。
     *
     * @param PluginArtifactDownloadRequest $request 已校验的制品下载请求。
     * @return array<string,mixed> 可持久化的制品引用和非敏感校验字段。
     */
    private function taskPayload(PluginArtifactDownloadRequest $request): array
    {
        return array_filter([
            'artifact_ref' => $request->artifactRef,
            'artifact_url' => $request->artifactUrl,
            'signing_key_id' => $request->signingKeyId,
            'expected_artifact_sha256' => $request->artifactSha256,
            'payload' => $request->payload,
        ], static fn (mixed $value): bool => $value !== '' && $value !== []);
    }

    /**
     * 发布只观察模式的下载事件，并按任务与事件生成幂等身份。
     *
     * @param string $eventName 已声明的下载生命周期事件名。
     * @param array<string,mixed> $task 当前下载任务。
     * @param PluginArtifactDownloadRequest $request 原始已校验下载请求。
     * @param string $errorCode 失败错误码；成功事件为空。
     * @return void 无返回值。
     */
    private function publishEvent(
        string $eventName,
        array $task,
        PluginArtifactDownloadRequest $request,
        string $errorCode = ''
    ): void {
        $taskId = (string)($task['id'] ?? $task['task_no'] ?? '');
        $payload = [
            'task_id' => $taskId,
            'plugin_code' => $request->pluginCode,
            'from_version' => '',
            'to_version' => $request->toVersion,
            'artifact_sha256' => (string)($task['artifact_sha256'] ?? $request->artifactSha256),
            'data_policy' => 'retain',
            'actor' => $request->actor->toArray(),
            'error_code' => $errorCode,
        ];
        $this->eventPublisher->publish(
            $eventName,
            $payload,
            'plugin-task:' . $taskId . ':' . $eventName,
            ['mode' => 'observer']
        );
    }

    /**
     * 记录下载失败并尽力发布失败事件，事件异常不能掩盖原始错误。
     *
     * @param array<string,mixed> $task 失败时的下载任务。
     * @param PluginArtifactDownloadRequest $request 原始已校验下载请求。
     * @param Throwable $failure 下载或验证失败的原始异常。
     * @return array 保存后的失败任务。
     */
    private function markFailed(
        array $task,
        PluginArtifactDownloadRequest $request,
        Throwable $failure
    ): array {
        $errorCode = $failure instanceof PluginLifecycleException && $failure->getErrorCode() !== ''
            ? strtoupper((string)$failure->getErrorCode())
            : 'PLUGIN_DOWNLOAD_FAILED';
        $errorCode = preg_replace('/[^A-Z0-9_]/', '_', $errorCode) ?: 'PLUGIN_DOWNLOAD_FAILED';
        $message = mb_substr($failure->getMessage() !== '' ? $failure->getMessage() : '插件制品下载失败', 0, 500);
        $failed = $this->updateTask($task, [
            'status' => PluginTaskStatusEnum::FAILED,
            'progress' => 100,
            'finished_at' => date('Y-m-d H:i:s'),
            'error_code' => $errorCode,
            'error_message' => $message,
            'result_json' => ['phase' => PluginArtifactStatusEnum::FAILED],
        ]);
        try {
            $this->publishEvent('plugin.download.failed', $failed, $request, $errorCode);
        } catch (Throwable) {
            // 保持原始失败可见；事件内核负责后续重试/死信。
        }
        return $failed;
    }

    /**
     * 确保隔离下载目录存在且可写，避免临时文件落入非预期位置。
     *
     * @return void 无返回值；目录不可用时抛出异常。
     */
    private function ensureTemporaryDirectory(): void
    {
        if (is_dir($this->temporaryDirectory)) {
            if (!is_writable($this->temporaryDirectory)) {
                throw new PluginLifecycleException('制品临时目录不可写', 'ARTIFACT_TEMP_DIR_UNWRITABLE');
            }
            return;
        }
        if (!@mkdir($this->temporaryDirectory, 0700, true) && !is_dir($this->temporaryDirectory)) {
            throw new PluginLifecycleException('制品临时目录创建失败', 'ARTIFACT_TEMP_DIR_FAILED');
        }
    }

    /**
     * 限制下载结果为临时目录内的可读文件。
     *
     * @param string $path 下载器返回的临时文件路径。
     * @param string $fallback 下载器未提供路径时使用的预分配文件。
     * @return string 经过路径边界校验的实际文件路径。
     */
    private function assertLocalArtifactPath(string $path, string $fallback): string
    {
        $path = $path !== '' ? $path : $fallback;
        if (!is_file($path) || !is_readable($path)) {
            throw new PluginLifecycleException('制品文件不存在', 'ARTIFACT_FILE_MISSING');
        }
        $real = realpath($path);
        if ($real === false) {
            throw new PluginLifecycleException('制品路径无效', 'ARTIFACT_PATH_INVALID');
        }
        $root = realpath($this->temporaryDirectory);
        if ($root === false || ($real !== $root && !str_starts_with($real, $root . DIRECTORY_SEPARATOR))) {
            throw new PluginLifecycleException('制品路径超出临时目录', 'ARTIFACT_PATH_OUTSIDE_TEMP');
        }
        return $real;
    }

    /**
     * 限制验证结果快照大小，避免任务持久化过大的外部数据。
     *
     * @param array<string,mixed> $value 制品验证器返回的结果字段。
     * @return array 原验证字段或已截断标记。
     */
    private function sanitizeResult(array $value): array
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json !== false && strlen($json) <= 4000 ? $value : ['truncated' => true];
    }

    /**
     * 保留已知生命周期异常，其他失败转换为统一下载异常。
     *
     * @param Throwable $failure 原始下载或验证异常。
     * @return PluginLifecycleException 可由生命周期入口统一处理的异常。
     */
    private function asLifecycleException(Throwable $failure): PluginLifecycleException
    {
        if ($failure instanceof PluginLifecycleException) {
            return $failure;
        }
        return new PluginLifecycleException(
            mb_substr($failure->getMessage() ?: '插件制品下载失败', 0, 500),
            'PLUGIN_DOWNLOAD_FAILED',
            (int)$failure->getCode(),
            $failure
        );
    }
}
