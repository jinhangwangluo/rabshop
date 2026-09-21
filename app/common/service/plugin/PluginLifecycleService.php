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

use app\common\enum\plugin\PluginDataPolicyEnum;
use app\common\enum\plugin\PluginLifecycleStatusEnum;
use app\common\enum\plugin\PluginTaskStatusEnum;
use app\common\enum\plugin\PluginTaskTypeEnum;
use app\common\exception\AdminResponseException;
use app\common\exception\PluginLifecycleException;
use app\common\model\plugin\PluginTask;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\plugin\contract\PluginLifecycleEventPublisherInterface;
use app\common\service\plugin\contract\PluginLifecycleExecutorInterface;
use app\common\service\plugin\contract\PluginLifecycleLockInterface;
use app\common\service\plugin\contract\PluginLifecycleRepositoryInterface;
use app\common\service\plugin\dto\PluginActor;
use app\common\service\plugin\dto\PluginLifecycleRequest;
use Throwable;

/**
 * 编排插件安装、升级、禁用、卸载与回滚任务，共用仓储、执行器、独占锁和幂等规则。
 */
class PluginLifecycleService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    private const OPERATION_EVENTS = [
        PluginTaskTypeEnum::INSTALL => [
            'starting' => 'plugin.installing',
            'success' => 'plugin.installed',
            'failure' => 'plugin.install.failed',
        ],
        PluginTaskTypeEnum::UPGRADE => [
            'starting' => 'plugin.upgrading',
            'success' => 'plugin.upgraded',
            'failure' => 'plugin.upgrade.failed',
        ],
        PluginTaskTypeEnum::DISABLE => [
            'starting' => 'plugin.disabling',
            'success' => 'plugin.disabled',
            'failure' => 'plugin.disable.failed',
        ],
        PluginTaskTypeEnum::UNINSTALL => [
            'starting' => 'plugin.uninstalling',
            'success' => 'plugin.uninstalled',
            'failure' => 'plugin.uninstall.failed',
        ],
        PluginTaskTypeEnum::ROLLBACK => [
            'starting' => '',
            'success' => 'plugin.rollback.succeeded',
            'failure' => 'plugin.rollback.failed',
        ],
    ];

    /**
     * 宿主生命周期各操作允许的起始状态；开发来源另有更严格限制。
     *
     * @var array<string,list<string>>
     */
    private const ALLOWED_STATES = [
        PluginTaskTypeEnum::INSTALL => [
            PluginLifecycleStatusEnum::UNINSTALLED,
            PluginLifecycleStatusEnum::FAILED,
            PluginLifecycleStatusEnum::DISABLED,
        ],
        PluginTaskTypeEnum::UPGRADE => [
            PluginLifecycleStatusEnum::ACTIVE,
            PluginLifecycleStatusEnum::DISABLED,
        ],
        PluginTaskTypeEnum::DISABLE => [PluginLifecycleStatusEnum::ACTIVE],
        PluginTaskTypeEnum::UNINSTALL => [
            PluginLifecycleStatusEnum::ACTIVE,
            PluginLifecycleStatusEnum::DISABLED,
            PluginLifecycleStatusEnum::FAILED,
        ],
        PluginTaskTypeEnum::ROLLBACK => [
            PluginLifecycleStatusEnum::ACTIVE,
            PluginLifecycleStatusEnum::DISABLED,
            PluginLifecycleStatusEnum::FAILED,
        ],
    ];

    private PluginLifecycleRepositoryInterface $repository;
    private PluginLifecycleEventPublisherInterface $eventPublisher;
    private PluginLifecycleExecutorInterface $executor;
    private PluginLifecycleLockInterface $lock;

    /**
     * 初始化生命周期依赖；持久仓储使用受信安装器，测试仓储必须显式提供执行器。
     *
     * @param ?PluginLifecycleRepositoryInterface $repository 插件与任务生命周期仓储。
     * @param ?PluginLifecycleEventPublisherInterface $eventPublisher 生命周期观察事件发布器。
     * @param ?PluginLifecycleExecutorInterface $executor 真实资源执行器或隔离测试执行器。
     * @param ?PluginLifecycleLockInterface $lock 跨工作进程生命周期独占锁。
     * @return void 无返回值。
     */
    public function __construct(
        ?PluginLifecycleRepositoryInterface $repository = null,
        ?PluginLifecycleEventPublisherInterface $eventPublisher = null,
        ?PluginLifecycleExecutorInterface $executor = null,
        ?PluginLifecycleLockInterface $lock = null
    ) {
        $this->repository = $repository ?? $this->resolveRepository();
        $this->eventPublisher = $eventPublisher ?? $this->resolveEventPublisher();
        $persistent = $this->repository instanceof DatabasePluginLifecycleRepository;
        $this->executor = $executor ?? ($persistent ? new InstalledPluginLifecycleExecutor(
            $this->repository,
            new DatabasePluginInstallRepository(),
            new PluginArtifactStore(root_path('runtime/plugin_artifacts')),
            root_path('plugins'),
            (array)config('plugin.lifecycle.trusted_signing_keys', [])
        ) : new ContractOnlyPluginLifecycleExecutor());
        $this->lock = $lock ?? ($persistent ? new DatabasePluginLifecycleLock() : new InMemoryPluginLifecycleLock());
    }

    /**
     * 指定生命周期编排服务对应的任务模型。
     *
     * @return string 插件任务模型完整类名。
     */
    protected function setModel(): string
    {
        return PluginTask::class;
    }

    /**
     * 编排插件安装任务并验证当前状态。
     *
     * @param string|array<string,mixed>|PluginLifecycleRequest $pluginCodeOrRequest 插件编码、请求字段或已校验的请求对象。
     * @param array<string,mixed>|string|null $requestOrVersion 补充请求字段或目标版本。
     * @param array<string,mixed> $context 调用方提供的可信操作人与请求上下文。
     * @return array<string,mixed> 安装任务记录或已有幂等任务。
     */
    public function install(
        string|array|PluginLifecycleRequest $pluginCodeOrRequest,
        array|string|null $requestOrVersion = null,
        array $context = []
    ): array {
        return $this->runOperation(
            PluginTaskTypeEnum::INSTALL,
            $pluginCodeOrRequest,
            $requestOrVersion,
            $context
        );
    }

    /**
     * 编排插件升级任务并验证当前状态。
     *
     * @param string|array<string,mixed>|PluginLifecycleRequest $pluginCodeOrRequest 插件编码、请求字段或已校验的请求对象。
     * @param array<string,mixed>|string|null $requestOrVersion 补充请求字段或目标版本。
     * @param array<string,mixed> $context 调用方提供的可信操作人与请求上下文。
     * @return array<string,mixed> 升级任务记录或已有幂等任务。
     */
    public function upgrade(
        string|array|PluginLifecycleRequest $pluginCodeOrRequest,
        array|string|null $requestOrVersion = null,
        array $context = []
    ): array {
        return $this->runOperation(
            PluginTaskTypeEnum::UPGRADE,
            $pluginCodeOrRequest,
            $requestOrVersion,
            $context
        );
    }

    /**
     * 编排插件禁用任务并验证当前状态。
     *
     * @param string|array<string,mixed>|PluginLifecycleRequest $pluginCodeOrRequest 插件编码、请求字段或已校验的请求对象。
     * @param array<string,mixed>|string|null $requestOrVersion 补充请求字段或目标版本。
     * @param array<string,mixed> $context 调用方提供的可信操作人与请求上下文。
     * @return array<string,mixed> 禁用任务记录或已有幂等任务。
     */
    public function disable(
        string|array|PluginLifecycleRequest $pluginCodeOrRequest,
        array|string|null $requestOrVersion = null,
        array $context = []
    ): array {
        return $this->runOperation(
            PluginTaskTypeEnum::DISABLE,
            $pluginCodeOrRequest,
            $requestOrVersion,
            $context
        );
    }

    /**
     * 按明确数据策略编排卸载任务。
     *
     * @param string|array<string,mixed>|PluginLifecycleRequest $pluginCodeOrRequest 插件编码、请求字段或已校验的请求对象。
     * @param array<string,mixed>|string|null $requestOrVersion 补充请求字段或目标版本。
     * @param array<string,mixed> $context 调用方提供的可信操作人与请求上下文。
     * @return array<string,mixed> 卸载任务记录或已有幂等任务。
     */
    public function uninstall(
        string|array|PluginLifecycleRequest $pluginCodeOrRequest,
        array|string|null $requestOrVersion = null,
        array $context = []
    ): array {
        return $this->runOperation(
            PluginTaskTypeEnum::UNINSTALL,
            $pluginCodeOrRequest,
            $requestOrVersion,
            $context
        );
    }

    /**
     * 编排目标版本回滚请求，实际能力由受信执行器限定。
     *
     * @param string|array<string,mixed>|PluginLifecycleRequest $pluginCodeOrRequest 插件编码、请求字段或已校验的请求对象。
     * @param array<string,mixed>|string|null $requestOrVersion 补充请求字段或目标版本。
     * @param array<string,mixed> $context 调用方提供的可信操作人与请求上下文。
     * @return array<string,mixed> 回滚任务记录或已有幂等任务。
     */
    public function rollback(
        string|array|PluginLifecycleRequest $pluginCodeOrRequest,
        array|string|null $requestOrVersion = null,
        array $context = []
    ): array {
        return $this->runOperation(
            PluginTaskTypeEnum::ROLLBACK,
            $pluginCodeOrRequest,
            $requestOrVersion,
            $context
        );
    }

    // 语义化别名，方便 Controller/回调适配而不复制业务逻辑。
    /**
     * 提供插件安装的语义化别名，不复制生命周期逻辑。
     *
     * @param string|array|PluginLifecycleRequest $request 插件编码、请求字段或已校验的请求对象。
     * @param array|string|null $extra 补充请求字段或目标版本。
     * @return array 安装任务结果。
     */
    public function installPlugin(string|array|PluginLifecycleRequest $request, array|string|null $extra = null): array
    {
        return $this->install($request, $extra);
    }

    /**
     * 提供插件升级的语义化别名，不复制生命周期逻辑。
     *
     * @param string|array|PluginLifecycleRequest $request 插件编码、请求字段或已校验的请求对象。
     * @param array|string|null $extra 补充请求字段或目标版本。
     * @return array 升级任务结果。
     */
    public function upgradePlugin(string|array|PluginLifecycleRequest $request, array|string|null $extra = null): array
    {
        return $this->upgrade($request, $extra);
    }

    /**
     * 提供插件禁用的语义化别名，不复制生命周期逻辑。
     *
     * @param string|array|PluginLifecycleRequest $request 插件编码、请求字段或已校验的请求对象。
     * @param array|string|null $extra 补充请求字段或目标版本。
     * @return array 禁用任务结果。
     */
    public function disablePlugin(string|array|PluginLifecycleRequest $request, array|string|null $extra = null): array
    {
        return $this->disable($request, $extra);
    }

    /**
     * 提供插件卸载的语义化别名，不复制生命周期逻辑。
     *
     * @param string|array|PluginLifecycleRequest $request 插件编码、请求字段或已校验的请求对象。
     * @param array|string|null $extra 补充请求字段或目标版本。
     * @return array 卸载任务结果。
     */
    public function uninstallPlugin(string|array|PluginLifecycleRequest $request, array|string|null $extra = null): array
    {
        return $this->uninstall($request, $extra);
    }

    /**
     * 提供插件回滚的语义化别名，不复制生命周期逻辑。
     *
     * @param string|array|PluginLifecycleRequest $request 插件编码、请求字段或已校验的请求对象。
     * @param array|string|null $extra 补充请求字段或目标版本。
     * @return array 回滚任务结果。
     */
    public function rollbackPlugin(string|array|PluginLifecycleRequest $request, array|string|null $extra = null): array
    {
        return $this->rollback($request, $extra);
    }

    /**
     * 只查询本地任务，不执行生命周期操作。
     *
     * @param string|int $taskId 任务 ID 或任务编号。
     * @return array<string,mixed>|null 任务记录；不存在时为 null。
     */
    public function getTask(string|int $taskId): ?array
    {
        return $this->repository->findTask($taskId);
    }

    /**
     * 按统一分页边界读取已安装插件。
     *
     * @param array<string,mixed> $filters 分页和已允许的插件筛选字段。
     * @return array{list:array<int,array<string,mixed>>,total:int,page:int,limit:int} 插件列表、总数及分页信息。
     */
    public function listInstalled(array $filters = []): array
    {
        [$page, $limit] = $this->getPagesValue(true, true, $filters, 100);
        $filters['page'] = $page;
        $filters['limit'] = $limit;
        return $this->repository->listPlugins($filters);
    }

    /**
     * 在独占锁下编排任务、状态和事件，并按恢复结果记录失败。
     *
     * @param string $operation 已支持的生命周期操作类型。
     * @param string|array<string,mixed>|PluginLifecycleRequest $pluginCodeOrRequest 插件编码、请求字段或已校验的请求对象。
     * @param array<string,mixed>|string|null $requestOrVersion 补充请求字段或目标版本。
     * @param array<string,mixed> $context 调用方提供的可信操作人与请求上下文。
     * @return array<string,mixed> 执行后的任务或身份一致的已有幂等任务。
     */
    public function runOperation(
        string $operation,
        string|array|PluginLifecycleRequest $pluginCodeOrRequest,
        array|string|null $requestOrVersion = null,
        array $context = []
    ): array {
        if (!isset(self::OPERATION_EVENTS[$operation])) {
            throw new PluginLifecycleException('生命周期操作不受支持', 'UNSUPPORTED_LIFECYCLE_OPERATION');
        }

        $request = $this->normalizeRequest(
            $operation,
            $pluginCodeOrRequest,
            $requestOrVersion,
            $context
        );
        // 未接入安装器时必须在任何本地读写前失败，避免模拟动作启用未安装代码。
        if ($this->executor instanceof ContractOnlyPluginLifecycleExecutor) {
            throw new PluginLifecycleException('插件真实生命周期执行器尚未配置', 'LIFECYCLE_EXECUTOR_UNAVAILABLE');
        }

        // 幂等查询优先于发布锁，重复请求不应因已有任务占锁而失败。
        $existing = $this->repository->findTaskByIdempotency($request->idempotencyKey);
        if ($existing !== null) {
            $this->assertTaskMatches($existing, $operation, $request);
            return $existing;
        }

        $lockHandle = $this->lock->acquire($request->pluginCode);
        $task = null;
        $pluginBefore = null;

        try {
            // 并发请求可能在等待锁时创建了同一幂等任务，重新检查一次。
            $existing = $this->repository->findTaskByIdempotency($request->idempotencyKey);
            if ($existing !== null) {
                $this->assertTaskMatches($existing, $operation, $request);
                return $existing;
            }

            $pluginBefore = $this->repository->findPlugin($request->pluginCode);
            $this->assertCurrentState($operation, $pluginBefore);
            if ($operation === PluginTaskTypeEnum::INSTALL && $pluginBefore === null) {
                $pluginBefore = $this->repository->createPlugin([
                    'code' => $request->pluginCode,
                    'name' => (string)($request->payload['name'] ?? $request->pluginCode),
                    'version' => '',
                    'lifecycle_status' => PluginLifecycleStatusEnum::UNINSTALLED,
                    'enabled' => 0,
                    'license_status' => 'unknown',
                    'manifest_json' => [],
                ]);
            }

            $task = $this->createTask($operation, $request);
            $taskId = $this->taskIdentifier($task);
            $previousState = $this->snapshotPluginState($pluginBefore);
            $start = microtime(true);

            $task = $this->updateTask($task, [
                'status' => PluginTaskStatusEnum::RUNNING,
                'progress' => 5,
                'started_at' => date('Y-m-d H:i:s'),
            ]);

            $intermediate = $this->intermediatePluginData($operation);
            if ($intermediate !== []) {
                $this->repository->updatePlugin($request->pluginCode, $intermediate);
            }

            $eventIds = [];
            $startEvent = self::OPERATION_EVENTS[$operation]['starting'];
            if ($startEvent !== '') {
                $eventIds[$startEvent] = $this->publishLifecycleEvent(
                    $startEvent,
                    $task,
                    $request
                );
            }

            $execution = $this->executor->execute($operation, [
                'task' => $task,
                'task_id' => $taskId,
                'plugin_code' => $request->pluginCode,
                'from_version' => $request->fromVersion,
                'to_version' => $request->toVersion,
                'release_id' => $request->releaseId,
                'artifact_sha256' => $request->artifactSha256,
                'data_policy' => $request->dataPolicy,
                'actor' => $request->actor->toArray(),
                'previous_state' => $previousState,
                'payload' => $request->payload,
            ]);
            if (!is_array($execution)) {
                throw new PluginLifecycleException('安装器返回结构无效', 'EXECUTOR_INVALID_RESULT');
            }
            if (($execution['simulated'] ?? false) === true) {
                throw new PluginLifecycleException('模拟执行不能记录为真实生命周期成功', 'LIFECYCLE_EXECUTOR_SIMULATED');
            }

            $finalData = $this->finalPluginData($operation, $request, $execution);
            $pluginAfter = $this->repository->updatePlugin($request->pluginCode, $finalData);
            $taskResult = $this->sanitizeResult([
                'execution' => $execution,
                'plugin' => $pluginAfter,
                'event_ids' => $eventIds,
                'duration_ms' => (int)round((microtime(true) - $start) * 1000),
            ]);
            $task = $this->updateTask($task, [
                'status' => PluginTaskStatusEnum::SUCCEEDED,
                'progress' => 100,
                'result_json' => $taskResult,
                'finished_at' => date('Y-m-d H:i:s'),
                'error_code' => '',
                'error_message' => '',
            ]);

            $successEvent = self::OPERATION_EVENTS[$operation]['success'];
            if ($successEvent !== '') {
                $eventIds[$successEvent] = $this->publishLifecycleEvent(
                    $successEvent,
                    $task,
                    $request
                );
                // 事件 ID 仅为审计元数据，发布失败由统一异常路径处理。
                $task = $this->updateTask($task, [
                    'result_json' => $this->sanitizeResult([
                        'execution' => $execution,
                        'plugin' => $pluginAfter,
                        'event_ids' => $eventIds,
                        'duration_ms' => (int)round((microtime(true) - $start) * 1000),
                    ]),
                ]);
            }

            return $task;
        } catch (Throwable $failure) {
            $task = $this->handleFailure(
                $operation,
                $request,
                $task,
                $pluginBefore,
                $failure
            );
            throw $this->asLifecycleException($failure);
        } finally {
            $this->lock->release($lockHandle);
        }
    }

    /**
     * 将多种调用形式归一化为经过校验的生命周期请求。
     *
     * @param string $operation 当前生命周期操作类型。
     * @param string|array<string,mixed>|PluginLifecycleRequest $input 插件编码、请求字段或请求对象。
     * @param array<string,mixed>|string|null $extra 补充请求字段或目标版本。
     * @param array<string,mixed> $context 调用方提供的操作人与请求上下文。
     * @return PluginLifecycleRequest 归一化后的生命周期请求对象。
     */
    private function normalizeRequest(
        string $operation,
        string|array|PluginLifecycleRequest $input,
        array|string|null $extra,
        array $context
    ): PluginLifecycleRequest {
        if ($input instanceof PluginLifecycleRequest) {
            $data = $input->toArray();
            if (is_array($extra)) {
                $data = array_merge($data, $extra);
            }
            // Controller 注入的操作人和请求上下文优先于业务体中的同名字段。
            $data = array_merge($data, $context);
            $data['task_type'] = $operation;
            return PluginLifecycleRequest::fromArray($data);
        }

        if (is_array($input)) {
            $data = array_merge($input, $context);
            if (is_array($extra)) {
                $data = array_merge($data, $extra);
            } elseif (is_string($extra) && ($data['to_version'] ?? '') === '') {
                $data['to_version'] = $extra;
            }
            $data['task_type'] = $operation;
            return PluginLifecycleRequest::fromArray($data);
        }

        $data = $context;
        $data['plugin_code'] = $input;
        if (is_array($extra)) {
            $data = array_merge($data, $extra);
        } elseif (is_string($extra)) {
            $data['to_version'] = $extra;
        }
        $data['task_type'] = $operation;
        return PluginLifecycleRequest::fromArray($data);
    }

    /**
     * 校验插件当前状态是否允许执行指定生命周期操作。
     *
     * @param string $operation 已支持的操作类型。
     * @param array<string,mixed>|null $plugin 操作前的插件记录；首次安装可为空。
     * @return void 无返回值；状态不允许时抛出异常。
     */
    private function assertCurrentState(string $operation, ?array $plugin): void
    {
        if ($operation === PluginTaskTypeEnum::INSTALL && $plugin === null) {
            return;
        }
        if ($plugin === null) {
            throw new PluginLifecycleException('插件不存在', 'PLUGIN_NOT_FOUND');
        }
        $state = (string)($plugin['lifecycle_status'] ?? PluginLifecycleStatusEnum::UNINSTALLED);
        if (!in_array($state, self::ALLOWED_STATES[$operation] ?? [], true)) {
            throw new PluginLifecycleException(
                '当前插件状态不允许执行该操作',
                'INVALID_LIFECYCLE_TRANSITION'
            );
        }
    }

    /**
     * 核对幂等任务的操作、来源和操作人身份，避免复用其他请求。
     *
     * @param array<string,mixed> $task 幂等键命中的任务。
     * @param string $operation 当前生命周期操作类型。
     * @param PluginLifecycleRequest $request 当前已校验生命周期请求。
     * @return void 无返回值；幂等身份冲突时抛出异常。
     */
    private function assertTaskMatches(array $task, string $operation, PluginLifecycleRequest $request): void
    {
        if ((string)($task['task_type'] ?? '') !== $operation
            || (string)($task['plugin_code'] ?? '') !== $request->pluginCode
            || (string)($task['from_version'] ?? '') !== $request->fromVersion
            || (string)($task['to_version'] ?? '') !== $request->toVersion
            || (int)($task['release_id'] ?? 0) !== $request->releaseId
            || (string)($task['artifact_sha256'] ?? '') !== $request->artifactSha256
            || (string)($task['data_policy'] ?? '') !== $request->dataPolicy
            || (string)($task['actor_type'] ?? '') !== $request->actor->type
            || (int)($task['actor_id'] ?? 0) !== $request->actor->id
        ) {
            throw new PluginLifecycleException('幂等键已用于其他插件任务', 'TASK_IDEMPOTENCY_CONFLICT');
        }
    }

    /**
     * 创建生命周期审计任务，唯一索引竞争时仅复用同身份任务。
     *
     * @param string $operation 当前生命周期操作类型。
     * @param PluginLifecycleRequest $request 已校验的生命周期请求。
     * @return array<string,mixed> 新建或并发创建的同身份任务。
     */
    private function createTask(string $operation, PluginLifecycleRequest $request): array
    {
        $taskNo = 'pt_' . date('YmdHis') . '_' . bin2hex(random_bytes(6));
        $data = [
            'task_no' => $taskNo,
            'task_type' => $operation,
            'plugin_code' => $request->pluginCode,
            'release_id' => $request->releaseId,
            'from_version' => $request->fromVersion,
            'to_version' => $request->toVersion,
            'status' => PluginTaskStatusEnum::PENDING,
            'progress' => 0,
            'idempotency_key' => $request->idempotencyKey,
            'artifact_sha256' => $request->artifactSha256,
            'data_policy' => $request->dataPolicy,
            'actor_type' => $request->actor->type,
            'actor_id' => $request->actor->id,
            'payload_json' => $request->payload,
            'result_json' => [],
            'error_code' => '',
            'error_message' => '',
        ];

        try {
            return $this->repository->createTask($data);
        } catch (Throwable $exception) {
            // 唯一索引竞争时优先返回已成功创建的同一任务。
            $existing = $this->repository->findTaskByIdempotency($request->idempotencyKey);
            if ($existing !== null) {
                $this->assertTaskMatches($existing, $operation, $request);
                return $existing;
            }
            throw $exception;
        }
    }

    /**
     * 按唯一标识保存任务状态及执行结果。
     *
     * @param array<string,mixed> $task 当前任务记录。
     * @param array $data 待更新的任务字段。
     * @return array 保存后的任务记录。
     */
    private function updateTask(array $task, array $data): array
    {
        $id = $this->taskIdentifier($task);
        return $this->repository->updateTask($id, $data);
    }

    /**
     * 提取任务 ID 或编号，避免更新没有身份的任务。
     *
     * @param array<string,mixed> $task 当前生命周期任务。
     * @return string|int 可供仓储定位的任务 ID 或任务编号。
     */
    private function taskIdentifier(array $task): string|int
    {
        $id = $task['id'] ?? null;
        if (is_int($id) || (is_string($id) && $id !== '')) {
            return $id;
        }
        $taskNo = (string)($task['task_no'] ?? '');
        if ($taskNo !== '') {
            return $taskNo;
        }
        throw new PluginLifecycleException('任务缺少唯一标识', 'TASK_IDENTIFIER_MISSING');
    }

    /**
     * 只保存恢复所需的状态、启用位和版本，避免混入敏感字段。
     *
     * @param array<string,mixed>|null $plugin 操作前的插件记录；首次安装可为空。
     * @return array 最小恢复状态快照。
     */
    private function snapshotPluginState(?array $plugin): array
    {
        if ($plugin === null) {
            return [
                'lifecycle_status' => PluginLifecycleStatusEnum::UNINSTALLED,
                'enabled' => 0,
                'version' => '',
            ];
        }
        return [
            'lifecycle_status' => (string)($plugin['lifecycle_status'] ?? PluginLifecycleStatusEnum::UNINSTALLED),
            'enabled' => (int)($plugin['enabled'] ?? 0),
            'version' => (string)($plugin['version'] ?? ''),
        ];
    }

    /**
     * 组装执行中状态并停用插件，避免资源变更期间继续运行。
     *
     * @param string $operation 当前生命周期操作类型。
     * @return array<string,mixed> 执行中状态字段；未知操作为空数组。
     */
    private function intermediatePluginData(string $operation): array
    {
        return match ($operation) {
            PluginTaskTypeEnum::INSTALL => [
                'lifecycle_status' => PluginLifecycleStatusEnum::INSTALLING,
                'enabled' => 0,
            ],
            PluginTaskTypeEnum::UPGRADE => [
                'lifecycle_status' => PluginLifecycleStatusEnum::UPGRADING,
                'enabled' => 0,
            ],
            PluginTaskTypeEnum::DISABLE => [
                'lifecycle_status' => PluginLifecycleStatusEnum::DISABLING,
                'enabled' => 0,
            ],
            PluginTaskTypeEnum::UNINSTALL => [
                'lifecycle_status' => PluginLifecycleStatusEnum::UNINSTALLING,
                'enabled' => 0,
            ],
            PluginTaskTypeEnum::ROLLBACK => [
                'lifecycle_status' => PluginLifecycleStatusEnum::ROLLING_BACK,
                'enabled' => 0,
            ],
            default => [],
        };
    }

    /**
     * 根据真实执行结果组装操作完成后的插件状态。
     *
     * @param string $operation 当前生命周期操作类型。
     * @param PluginLifecycleRequest $request 已校验生命周期请求。
     * @param array<string,mixed> $execution 真实资源执行器返回的结果。
     * @return array<string,mixed> 待保存的插件最终状态字段。
     */
    private function finalPluginData(
        string $operation,
        PluginLifecycleRequest $request,
        array $execution
    ): array {
        $version = $request->toVersion !== ''
            ? $request->toVersion
            : (string)($execution['version'] ?? '');

        return match ($operation) {
            PluginTaskTypeEnum::INSTALL, PluginTaskTypeEnum::UPGRADE => array_filter([
                'lifecycle_status' => PluginLifecycleStatusEnum::ACTIVE,
                'enabled' => 1,
                'version' => $version !== '' ? $version : null,
                'installed_at' => date('Y-m-d H:i:s'),
                'manifest_json' => $execution['manifest_json'] ?? null,
                'enabled_at' => date('Y-m-d H:i:s'),
                'last_error_code' => '',
                'last_error_message' => '',
            ], static fn (mixed $value): bool => $value !== null),
            PluginTaskTypeEnum::DISABLE => [
                'lifecycle_status' => PluginLifecycleStatusEnum::DISABLED,
                'enabled' => 0,
                'disabled_at' => date('Y-m-d H:i:s'),
                'last_error_code' => '',
                'last_error_message' => '',
            ],
            PluginTaskTypeEnum::UNINSTALL => [
                'lifecycle_status' => PluginLifecycleStatusEnum::UNINSTALLED,
                'enabled' => 0,
                'disabled_at' => date('Y-m-d H:i:s'),
                'last_error_code' => '',
                'last_error_message' => '',
            ],
            PluginTaskTypeEnum::ROLLBACK => array_filter([
                'lifecycle_status' => PluginLifecycleStatusEnum::ACTIVE,
                'enabled' => 1,
                'version' => $version !== '' ? $version : null,
                'enabled_at' => date('Y-m-d H:i:s'),
                'last_error_code' => '',
                'last_error_message' => '',
            ], static fn (mixed $value): bool => $value !== null),
            default => [],
        };
    }

    /**
     * 以观察模式发布生命周期事件，并按任务与事件生成幂等键。
     *
     * @param string $eventName 已声明的生命周期事件名。
     * @param array<string,mixed> $task 当前任务记录。
     * @param PluginLifecycleRequest $request 已校验生命周期请求。
     * @param string $errorCode 失败错误码；非失败事件为空。
     * @return string 事件发布器返回的事件 ID。
     */
    private function publishLifecycleEvent(
        string $eventName,
        array $task,
        PluginLifecycleRequest $request,
        string $errorCode = ''
    ): string {
        $taskId = (string)$this->taskIdentifier($task);
        $payload = [
            'task_id' => $taskId,
            'plugin_code' => $request->pluginCode,
            'from_version' => $request->fromVersion,
            'to_version' => $request->toVersion,
            'artifact_sha256' => $request->artifactSha256,
            'data_policy' => $request->dataPolicy,
            'actor' => $request->actor->toArray(),
            'error_code' => $errorCode,
        ];
        $idempotencyKey = 'plugin-task:' . $taskId . ':' . $eventName;
        $result = $this->eventPublisher->publish(
            $eventName,
            $payload,
            $idempotencyKey,
            ['mode' => 'observer']
        );
        return (string)($result['event_id'] ?? '');
    }

    /**
     * 依次恢复副作用、恢复插件状态、保存失败任务，再发布失败事件。
     *
     * @param string $operation 已校验的生命周期操作。
     * @param PluginLifecycleRequest $request 当前任务请求和操作人。
     * @param array<string,mixed>|null $task 已创建的任务；预检失败时可为空。
     * @param array<string,mixed>|null $pluginBefore 操作前插件状态；首次安装可为空。
     * @param Throwable $failure 原始执行异常，恢复失败不得覆盖它。
     * @return array<string,mixed>|null 保存后的失败任务；任务尚未创建时为 null。
     */
    private function handleFailure(
        string $operation,
        PluginLifecycleRequest $request,
        ?array $task,
        ?array $pluginBefore,
        Throwable $failure
    ): ?array {
        // 任务创建前尚未进入执行阶段，预检失败不能改写插件状态或发布事件。
        if ($task === null) {
            return null;
        }

        $errorCode = $this->errorCode($failure);
        $recoveryError = null;
        try {
            $this->executor->recover($operation, [
                'task' => $task,
                'plugin_code' => $request->pluginCode,
                'previous_state' => $this->snapshotPluginState($pluginBefore),
                'data_policy' => $request->dataPolicy,
            ], $failure);
        } catch (Throwable $exception) {
            $recoveryError = $exception;
            $errorCode = 'RECOVERY_FAILED';
        }

        try {
            $plugin = $pluginBefore ?? $this->repository->findPlugin($request->pluginCode);
            if ($plugin !== null) {
                $state = $this->snapshotPluginState($pluginBefore);
                if ($recoveryError !== null) {
                    // 恢复失败时旧代码也可能不可用；保留版本信息但必须停止启用。
                    $state = $this->snapshotPluginState($plugin);
                    $state['lifecycle_status'] = PluginLifecycleStatusEnum::FAILED;
                    $state['enabled'] = 0;
                }
                $this->repository->updatePlugin(
                    $request->pluginCode,
                    $state + [
                        'last_error_code' => $errorCode,
                        'last_error_message' => $this->safeErrorMessage($failure),
                    ]
                );
            }
        } catch (Throwable $exception) {
            $recoveryError ??= $exception;
            $errorCode = 'STATE_RECOVERY_FAILED';
        }

        $task = $this->updateTask($task, [
            'status' => PluginTaskStatusEnum::FAILED,
            'progress' => 100,
            'finished_at' => date('Y-m-d H:i:s'),
            'error_code' => $errorCode,
            'error_message' => $this->safeErrorMessage($failure),
            'result_json' => $this->sanitizeResult([
                'recovery_failed' => $recoveryError !== null,
            ]),
        ]);

        // 失败事件必须在恢复动作和任务落账后发布；发布失败不覆盖原始错误。
        $failureEvent = self::OPERATION_EVENTS[$operation]['failure'];
        if ($failureEvent !== '') {
            try {
                $this->publishLifecycleEvent($failureEvent, $task, $request, $errorCode);
            } catch (Throwable) {
                // 事件内核自身会记录重试/死信；这里保持原任务错误可见。
            }
        }

        return $task;
    }

    /**
     * 将已知生命周期错误转换为可持久化的安全错误码。
     *
     * @param Throwable $failure 原始执行异常。
     * @return string 仅包含大写字母、数字和下划线的错误码。
     */
    private function errorCode(Throwable $failure): string
    {
        if ($failure instanceof PluginLifecycleException && $failure->getErrorCode() !== '') {
            return preg_replace('/[^A-Z0-9_]/', '_', strtoupper($failure->getErrorCode()))
                ?: 'PLUGIN_LIFECYCLE_ERROR';
        }
        return 'PLUGIN_LIFECYCLE_ERROR';
    }

    /**
     * 裁剪错误消息并脱敏令牌和密钥，不保存堆栈或完整敏感上下文。
     *
     * @param Throwable $failure 原始执行异常。
     * @return string 适合任务与事件快照的短错误消息。
     */
    private function safeErrorMessage(Throwable $failure): string
    {
        $message = trim($failure->getMessage());
        if ($message === '') {
            return '插件生命周期操作失败';
        }
        // 任务/事件只存短消息，不存堆栈、URL、令牌或私钥。
        $message = preg_replace(
            '/(?:Bearer\s+|token=|secret=|private_key=)[^\s&]+/i',
            '[REDACTED]',
            $message
        ) ?: '插件生命周期操作失败';
        return mb_substr($message, 0, 500);
    }

    /**
     * 限制任务结果快照大小，无法编码或过大时只保存截断标记。
     *
     * @param array<string,mixed> $value 待持久化的执行结果字段。
     * @return array 原结果或截断标记。
     */
    private function sanitizeResult(array $value): array
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || strlen($json) > 8000) {
            return ['truncated' => true];
        }
        return $value;
    }

    /**
     * 保留已知生命周期异常，并将宿主响应异常归一化。
     *
     * @param Throwable $failure 原始执行异常。
     * @return PluginLifecycleException 带安全错误消息的统一生命周期异常。
     */
    private function asLifecycleException(Throwable $failure): PluginLifecycleException
    {
        if ($failure instanceof PluginLifecycleException) {
            return $failure;
        }
        if ($failure instanceof AdminResponseException) {
            return new PluginLifecycleException(
                $this->safeErrorMessage($failure),
                'PLUGIN_LIFECYCLE_ERROR',
                (int)$failure->getCode(),
                $failure,
                $failure->getResponse()
            );
        }
        return new PluginLifecycleException(
            $this->safeErrorMessage($failure),
            'PLUGIN_LIFECYCLE_ERROR',
            (int)$failure->getCode(),
            $failure
        );
    }

    /**
     * 选择默认数据库生命周期仓储。
     *
     * @return PluginLifecycleRepositoryInterface 默认持久化仓储实例。
     */
    private function resolveRepository(): PluginLifecycleRepositoryInterface
    {
        return new DatabasePluginLifecycleRepository();
    }

    /**
     * 选择默认插件事件总线发布器。
     *
     * @return PluginLifecycleEventPublisherInterface 默认生命周期事件发布器。
     */
    private function resolveEventPublisher(): PluginLifecycleEventPublisherInterface
    {
        return new PluginEventBusPublisher();
    }
}
