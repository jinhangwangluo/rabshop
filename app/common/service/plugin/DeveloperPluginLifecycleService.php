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

use app\common\exception\PluginLifecycleException;
use app\common\model\plugin\Plugin;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\plugin\contract\PluginLifecycleRepositoryInterface;

/**
 * 提供本地开发插件发现与安装卸载入口，禁止市场购买及签名制品回退到开发来源。
 */
class DeveloperPluginLifecycleService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    private readonly PluginLifecycleRepositoryInterface $repository;
    private readonly DeveloperPluginSourceService $sources;
    private readonly PluginLifecycleService $lifecycle;

    /**
     * 初始化开发来源验证和共用生命周期依赖。
     *
     * @param PluginLifecycleRepositoryInterface|null $repository 生命周期持久化或隔离测试仓储。
     * @param DeveloperPluginSourceService|null $sources 服务器固定开发目录验证器。
     * @param PluginLifecycleService|null $lifecycle 共用锁、幂等及任务机制的生命周期服务。
     * @return void 无返回值。
     */
    public function __construct(
        ?PluginLifecycleRepositoryInterface $repository = null,
        ?DeveloperPluginSourceService $sources = null,
        ?PluginLifecycleService $lifecycle = null
    ) {
        $this->repository = $repository ?? new DatabasePluginLifecycleRepository();
        $this->sources = $sources ?? new DeveloperPluginSourceService();
        $this->lifecycle = $lifecycle ?? new PluginLifecycleService(
            $this->repository,
            null,
            new InstalledPluginLifecycleExecutor(
                $this->repository,
                new DatabasePluginInstallRepository(),
                new PluginArtifactStore(root_path('runtime/plugin_artifacts')),
                root_path('plugins'),
                [],
                null,
                $this->sources
            )
        );
    }

    /**
     * 指定开发插件管理使用的宿主插件模型。
     *
     * @return string 插件模型完整类名。
     */
    protected function setModel(): string
    {
        return Plugin::class;
    }

    /**
     * 分页展示本地开发插件及不可操作原因，安装能力仅来自服务器认证上下文。
     *
     * @param array<string,mixed> $query 仅包含关键词及分页参数的查询条件。
     * @param array<string,mixed> $context 服务器认证的开发插件管理能力，不读取客户端操作人。
     * @return array{items:list<array<string,mixed>>,total:int,page:int,limit:int,pages:int} 插件列表、总数及分页信息。
     */
    public function listDeveloper(array $query = [], array $context = []): array
    {
        $params = ['page' => is_scalar($query['page'] ?? null) ? $query['page'] : 1, 'limit' => is_scalar($query['limit'] ?? null) ? $query['limit'] : 20];
        [$page, $limit] = $this->getPagesValue(true, true, $params, 100);
        $keyword = is_string($query['keyword'] ?? null) ? mb_substr(trim($query['keyword']), 0, 100) : '';
        $items = array_values(array_filter($this->sources->discover(), static fn (array $item): bool => $keyword === ''
            || mb_stripos($item['code'] . ' ' . $item['name'] . ' ' . $item['description'], $keyword) !== false));
        $total = count($items);
        $items = array_slice($items, ($page - 1) * $limit, $limit);
        $codes = array_column($items, 'code');
        $installed = [];
        if ($codes !== []) {
            foreach ($this->repository->listPlugins(['codes' => $codes, 'page' => 1, 'limit' => 100])['list'] as $plugin) {
                $installed[$plugin['code']] = $plugin;
            }
        }
        foreach ($items as &$item) {
            $plugin = $installed[$item['code']] ?? null;
            $state = (string)($plugin['lifecycle_status'] ?? 'uninstalled');
            $manifest = is_array($plugin['manifest_json'] ?? null) ? $plugin['manifest_json'] : [];
            $developer = ($manifest['installation_source'] ?? '') === 'developer';
            $item['lifecycle_status'] = $state;
            $item['enabled'] = (bool)($plugin['enabled'] ?? false);
            // 源码验证失败与安装状态无关，下载和重装都必须建立在已验证源码之上。
            $sourceInvalid = $item['blocked_reason'] !== '';
            $sourceChanged = $developer && $state !== 'uninstalled'
                && (!is_string($manifest['developer_source_sha256'] ?? null)
                    || !hash_equals($manifest['developer_source_sha256'], $item['source_sha256']));
            $reason = $item['blocked_reason'];
            if ($state !== 'uninstalled' && $plugin !== null && !$developer) {
                $reason = '同编码插件已通过市场或其他来源安装，请先从原入口安全卸载';
            } elseif (!in_array($state, ['uninstalled', 'active', 'disabled', 'failed'], true)) {
                $reason = '插件生命周期任务进行中，请等待任务完成或处理失败恢复';
            }
            $canManage = ($context['can_manage_developer'] ?? false) === true;
            $item['can_install'] = $canManage && $reason === '' && $state === 'uninstalled';
            $item['can_uninstall'] = $canManage && $reason === '' && $developer && in_array($state, ['active', 'disabled', 'failed'], true);
            /*
             * 开发源码改动与失败状态均允许发起恢复，实际执行仍核对迁移账本。
             */
            $item['can_reinstall'] = $canManage && !$sourceInvalid && $developer && in_array($state, ['active', 'disabled', 'failed'], true);
            // 下载只导出已验证源码，与安装状态和源码改动无关。
            $item['can_download'] = $canManage && !$sourceInvalid;
            $item['source_changed'] = $sourceChanged;
            if (!$canManage && $reason === '') {
                $reason = '仅超级管理员可安装、重装、下载或卸载开发者插件';
            }
            $item['blocked_reason'] = $reason;
            if ($developer && $state !== 'uninstalled' && $sourceInvalid) {
                // 源码可验证时以当前源码声明为准（重装与卸载都执行当前源码）；仅源码不可验证时回退展示安装时记录。
                $item['requires_operational_cleanup'] = (bool)($manifest['install_resources']['requires_operational_cleanup'] ?? $item['requires_operational_cleanup']);
            }
            unset($item['source_sha256']);
        }
        unset($item);
        return ['items' => $items, 'total' => $total, 'page' => $page, 'limit' => $limit, 'pages' => (int)ceil($total / $limit)];
    }

    /**
     * 安装固定开发目录源码，版本与来源摘要由服务器重新验证。
     *
     * @param string $code 插件目录编码。
     * @param array<string,mixed> $payload 仅包含幂等键的客户端请求，不接受路径或制品证明。
     * @param array<string,mixed> $context 已认证管理员与加密信封的幂等上下文。
     * @return array<string,mixed> 共用生命周期任务结果。
     */
    public function installDeveloper(string $code, array $payload, array $context): array
    {
        $existing = $this->existingRequest($code, $payload, $context, 'install');
        if ($existing !== null) {
            return $this->lifecycle->install($existing);
        }
        $source = $this->sources->resolve($code);
        $plugin = $this->repository->findPlugin($code);
        $manifest = is_array($plugin['manifest_json'] ?? null) ? $plugin['manifest_json'] : [];
        if ($plugin !== null && ($plugin['lifecycle_status'] ?? 'uninstalled') !== 'uninstalled'
            && ($manifest['installation_source'] ?? '') !== 'developer') {
            throw new PluginLifecycleException('不能覆盖同编码的市场或其他来源安装', 'DEVELOPER_SOURCE_CONFLICT');
        }
        if ($plugin !== null && ($plugin['lifecycle_status'] ?? 'uninstalled') !== 'uninstalled') {
            throw new PluginLifecycleException('已安装开发插件须先安全卸载，不能通过重复安装重置代次', 'INVALID_LIFECYCLE_TRANSITION');
        }
        return $this->lifecycle->install($this->request($code, $payload, $context, $source, 'install'));
    }

    /**
     * 卸载受信开发安装，沿用运营清理及永久交易证据保留规则。
     *
     * @param string $code 插件编码。
     * @param array<string,mixed> $payload 幂等键和明确的数据保留策略。
     * @param array<string,mixed> $context 已认证管理员与加密信封的幂等上下文。
     * @return array<string,mixed> 共用生命周期任务结果。
     */
    public function uninstallDeveloper(string $code, array $payload, array $context): array
    {
        $existing = $this->existingRequest($code, $payload, $context, 'uninstall');
        if ($existing !== null) {
            return $this->lifecycle->uninstall($existing);
        }
        $plugin = $this->repository->findPlugin($code);
        $manifest = is_array($plugin['manifest_json'] ?? null) ? $plugin['manifest_json'] : [];
        $source = $this->sources->resolveInstalled($code, $manifest);
        if (!in_array($plugin['lifecycle_status'] ?? 'uninstalled', ['active', 'disabled', 'failed'], true)) {
            throw new PluginLifecycleException('当前开发插件状态不允许卸载，请先核对生命周期任务与迁移账本', 'INVALID_LIFECYCLE_TRANSITION');
        }
        return $this->lifecycle->uninstall($this->request($code, $payload, $context, $source, 'uninstall', $plugin));
    }

    /**
     * 用当前受信源码重新安装开发插件，卸载与安装各是一次真实生命周期任务。
     *
     * 重装即“按当前源码生效”：卸载与安装均重新验证服务器固定开发目录源码；
     * 卸载未成功时保持原状态并返回该任务错误，不在未知状态下继续安装。
     *
     * @param string $code 插件目录编码。
     * @param array<string,mixed> $payload 幂等键和明确的数据保留策略。
     * @param array<string,mixed> $context 已认证管理员上下文。
     * @return array<string,mixed> 安装任务结果，附带本次卸载任务与源码代次信息。
     */
    public function reinstallDeveloper(string $code, array $payload, array $context): array
    {
        $keys = $this->reinstallKeys($payload, $context);
        $replayed = $this->replayReinstall($code, $keys, $context);
        if ($replayed !== null) {
            return $replayed;
        }
        $source = $this->sources->resolve($code);
        $plugin = $this->repository->findPlugin($code);
        $manifest = is_array($plugin['manifest_json'] ?? null) ? $plugin['manifest_json'] : [];
        if ($plugin === null || !in_array((string)($plugin['lifecycle_status'] ?? ''), ['active', 'disabled', 'failed'], true)) {
            throw new PluginLifecycleException('仅已安装、已停用或异常的开发插件可以重新安装', 'INVALID_LIFECYCLE_TRANSITION');
        }
        if (($manifest['installation_source'] ?? '') !== 'developer') {
            throw new PluginLifecycleException('同编码插件不是开发来源安装，请先从原入口卸载', 'DEVELOPER_INSTALLATION_REQUIRED');
        }
        $sourceChanged = !is_string($manifest['developer_source_sha256'] ?? null)
            || !hash_equals($manifest['developer_source_sha256'], $source['source_sha256']);
        $removed = $this->lifecycle->uninstall($this->request(
            $code,
            $payload,
            $context,
            $source,
            'uninstall',
            $plugin,
            $keys['uninstall'],
            ['reinstall_base_key' => $keys['base'], 'reinstall_source_changed' => $sourceChanged]
        ));
        if ((string)($removed['status'] ?? '') !== 'succeeded') {
            return $removed;
        }
        $installed = $this->lifecycle->install($this->request(
            $code,
            $payload,
            $context,
            $source,
            'install',
            null,
            $keys['install'],
            ['reinstall_base_key' => $keys['base'], 'reinstall_source_changed' => $sourceChanged]
        ));
        $installed['reinstall'] = [
            'base_key' => $keys['base'],
            'uninstall_task_id' => (int)($removed['id'] ?? 0),
            'uninstall_task_no' => (string)($removed['task_no'] ?? ''),
            'source_changed' => $sourceChanged,
            'source_sha256' => $source['source_sha256'],
        ];
        return $installed;
    }

    /**
     * 导出整个开发插件源码包，用于本地备份或迁移，不改变任何生命周期状态。
     *
     * @param string $code 插件目录编码。
     * @return array{path:string,name:string,bytes:int,sha256:string,source_sha256:string,files:int} 压缩包路径、下载文件名、包字节数、包摘要、源码摘要及文件数量。
     */
    public function exportDeveloperSource(string $code): array
    {
        return $this->sources->package($code);
    }

    /**
     * 组装白名单字段及服务器来源证明，可信上下文始终优先。
     *
     * @param string $code 插件编码。
     * @param array<string,mixed> $payload 客户端请求字段。
     * @param array<string,mixed> $context 服务器认证上下文。
     * @param array<string,mixed> $source 固定目录源码验证结果。
     * @param string $operation 安装或卸载操作类型。
     * @param array<string,mixed>|null $installed 当前已安装插件记录；安装时可为空。
     * @param string $idempotencyKey 服务器派生的任务幂等键；为空时使用客户端键。
     * @param array<string,mixed> $extraPayload 服务器生成的附加任务载荷，不接受客户端字段。
     * @return array<string,mixed> 提交给宿主的生命周期请求。
     */
    private function request(
        string $code,
        array $payload,
        array $context,
        array $source,
        string $operation,
        ?array $installed = null,
        string $idempotencyKey = '',
        array $extraPayload = []
    ): array {
        $fields = $this->operationFields($payload, $context, $operation);
        if ($idempotencyKey !== '') {
            $fields['idempotency_key'] = $idempotencyKey;
        }
        return array_merge($fields, [
            'plugin_code' => $code, 'task_type' => $operation,
            'from_version' => $operation === 'uninstall' ? (string)($installed['version'] ?? '') : '',
            'to_version' => $operation === 'install' ? $source['manifest']['version'] : '',
            'payload' => array_merge([
                'name' => $source['manifest']['name'],
                'installation_source' => 'developer',
                'developer_source_sha256' => $source['source_sha256'],
            ], $extraPayload),
        ]);
    }

    /**
     * 重新安装包含卸载与安装两个真实任务，键位在客户端幂等键上确定性派生。
     *
     * @param array<string,mixed> $payload 客户端白名单字段。
     * @param array<string,mixed> $context 服务器认证上下文。
     * @return array{base:string,uninstall:string,install:string} 基础键与两个派生子键。
     */
    private function reinstallKeys(array $payload, array $context): array
    {
        $key = $context['idempotency_key'] ?? $payload['idempotency_key'] ?? '';
        if (!is_string($key) || ($key !== '' && preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $key) !== 1)) {
            throw new PluginLifecycleException('幂等键格式无效', 'INVALID_IDEMPOTENCY_KEY');
        }
        // 未提供幂等键的调用按新操作处理，避免所有重装共用同一任务身份。
        $base = $key !== '' ? $key : bin2hex(random_bytes(16));
        $suffix = substr(hash('sha256', $base), 0, 16);
        return ['base' => $base, 'uninstall' => 'dev-reinstall-u:' . $suffix, 'install' => 'dev-reinstall-i:' . $suffix];
    }

    /**
     * 已执行过的重新安装沿用原安装任务，源码变化不触发旧任务重跑。
     *
     * @param string $code 插件编码。
     * @param array{base:string,uninstall:string,install:string} $keys 本次重新安装的任务键。
     * @param array<string,mixed> $context 服务器认证上下文。
     * @return array<string,mixed>|null 原安装任务结果；本次重新安装未执行过时为 null。
     */
    private function replayReinstall(string $code, array $keys, array $context): ?array
    {
        $task = $this->repository->findTaskByIdempotency($keys['install']);
        if ($task === null) {
            return null;
        }
        $payload = is_array($task['payload_json'] ?? null) ? $task['payload_json'] : [];
        $actor = is_array($context['actor'] ?? null) ? $context['actor'] : [];
        if ((string)($task['task_type'] ?? '') !== 'install' || (string)($task['plugin_code'] ?? '') !== $code
            || (string)($payload['reinstall_base_key'] ?? '') !== $keys['base']
            || (string)($task['actor_type'] ?? '') !== (string)($actor['type'] ?? '')
            || (int)($task['actor_id'] ?? 0) !== (int)($actor['id'] ?? 0)) {
            throw new PluginLifecycleException('重新安装幂等键已用于其他任务', 'TASK_IDEMPOTENCY_CONFLICT');
        }
        $removed = $this->repository->findTaskByIdempotency($keys['uninstall']);
        $task['reinstall'] = [
            'base_key' => $keys['base'],
            'uninstall_task_id' => (int)($removed['id'] ?? 0),
            'uninstall_task_no' => (string)($removed['task_no'] ?? ''),
            'source_changed' => (bool)($payload['reinstall_source_changed'] ?? false),
            'source_sha256' => (string)($payload['developer_source_sha256'] ?? ''),
            'replayed' => true,
        ];
        return $task;
    }

    /**
     * 重复请求沿用原任务身份，源码变化或删除不触发旧任务再次执行。
     *
     * @param string $code 当前逻辑路径的插件编码。
     * @param array<string,mixed> $payload 客户端白名单字段。
     * @param array<string,mixed> $context 服务器认证上下文。
     * @param string $operation 安装或卸载操作类型。
     * @return array<string,mixed>|null 包含原版本及来源证明的幂等请求；无旧任务时为 null。
     */
    private function existingRequest(string $code, array $payload, array $context, string $operation): ?array
    {
        $fields = $this->operationFields($payload, $context, $operation);
        if ($fields['idempotency_key'] === '') {
            return null;
        }
        $task = $this->repository->findTaskByIdempotency($fields['idempotency_key']);
        if ($task === null) {
            return null;
        }
        $proof = is_array($task['payload_json'] ?? null) ? $task['payload_json'] : [];
        if (($proof['installation_source'] ?? '') !== 'developer') {
            throw new PluginLifecycleException('幂等键已用于其他来源任务', 'TASK_IDEMPOTENCY_CONFLICT');
        }
        return array_merge($fields, [
            'plugin_code' => $code, 'task_type' => $operation,
            'from_version' => (string)($task['from_version'] ?? ''),
            'to_version' => (string)($task['to_version'] ?? ''),
            'payload' => $proof,
        ]);
    }

    /**
     * 归一化幂等键和数据策略，只从可信上下文读取操作人。
     *
     * 开发来源只用于本地开发与测试，卸载与重装统一按清理运营数据执行，
     * 不再要求调用方判断清理策略；订单、支付、退款及财务等永久数据仍由
     * 迁移声明的 retain_on_uninstall 保留。
     *
     * @param array<string,mixed> $payload 客户端白名单字段。
     * @param array<string,mixed> $context 服务器认证上下文。
     * @param string $operation 安装或卸载操作类型。
     * @return array<string,mixed> 幂等键、数据策略及可信操作人信息。
     */
    private function operationFields(array $payload, array $context, string $operation): array
    {
        $key = $context['idempotency_key'] ?? $payload['idempotency_key'] ?? '';
        if (!is_string($key)) {
            throw new PluginLifecycleException('幂等键格式无效', 'INVALID_IDEMPOTENCY_KEY');
        }
        $policy = $operation === 'uninstall' ? 'delete' : 'retain';
        if (array_key_exists('data_policy', $payload) && !is_string($payload['data_policy'])) {
            throw new PluginLifecycleException('数据策略格式无效', 'INVALID_DATA_POLICY');
        }
        return [
            'data_policy' => $policy, 'idempotency_key' => $key,
            'request_id' => $context['request_id'] ?? null, 'actor' => $context['actor'] ?? null,
        ];
    }
}
