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
use app\common\service\plugin\contract\PluginLifecycleExecutorInterface;
use app\common\service\plugin\contract\PluginLifecycleHookInterface;
use app\common\service\plugin\contract\PluginLifecycleRepositoryInterface;
use Phinx\Db\Adapter\AdapterFactory;
use think\migration\Migrator;
use Rabbit\util\CacheTag;
use Throwable;

/**
 * 执行已部署且来源受信插件的迁移和资源安装卸载，保存安装代次并保留永久交易证据。
 */
final class InstalledPluginLifecycleExecutor implements PluginLifecycleExecutorInterface
{
    private bool $ddlTouched = false;
    private bool $cleanupCommitted = false;

    /**
     * 注入执行仓储、受信制品和服务器限定的开发来源策略。
     *
     * @param PluginLifecycleRepositoryInterface $repository 生命周期插件与任务仓储。
     * @param DatabasePluginInstallRepository $resources 宿主自有资源和迁移账本适配器。
     * @param PluginArtifactStore $artifacts 受信制品存储验证器。
     * @param string $pluginDirectory 服务器配置的部署插件目录。
     * @param array $trustedSigningKeys 服务器信任的签名公钥目录。
     * @param ?\Closure $cacheInvalidator 测试或宿主提供的缓存失效回调。
     * @param ?DeveloperPluginSourceService $developerSources 仅由服务器注入的固定开发来源验证器。
     * @return void 无返回值。
     */
    public function __construct(
        private readonly PluginLifecycleRepositoryInterface $repository,
        private readonly DatabasePluginInstallRepository $resources,
        private readonly PluginArtifactStore $artifacts,
        private readonly string $pluginDirectory,
        private readonly array $trustedSigningKeys = [],
        private readonly ?\Closure $cacheInvalidator = null,
        private readonly ?DeveloperPluginSourceService $developerSources = null
    ) {
    }

    /**
     * 在既有生命周期锁下执行资源变更，并明确提示前端构建和工作进程重载。
     *
     * @param string $operation 已授权的安装、升级、禁用或卸载操作。
     * @param array $context 任务、来源证明和数据策略等可信执行上下文。
     * @return array<string,mixed> 资源执行结果；不代表已发布前端或重载工作进程。
     */
    public function execute(string $operation, array $context): array
    {
        $this->ddlTouched = false;
        $this->cleanupCommitted = false;
        $code = (string)($context['plugin_code'] ?? '');
        if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/', $code)) {
            throw new PluginLifecycleException('插件编码无效', 'INVALID_PLUGIN_CODE');
        }
        if ($operation === 'disable') {
            $this->invalidateCaches();
            return ['reload_required' => true];
        }
        $root = $this->pluginDirectory . '/' . $code;
        $manifestFile = (new PluginInstallManifest())->safeFile($root, 'plugin.json');
        $manifest = json_decode((string)file_get_contents($manifestFile), true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($manifest) || ($manifest['code'] ?? '') !== $code) {
            throw new PluginLifecycleException('插件清单无效', 'ARTIFACT_MANIFEST_MISMATCH');
        }
        if ($operation === 'rollback') {
            throw new PluginLifecycleException('数据库降级与前端回滚需独立受审发布，不支持自动降级', 'PLUGIN_ROLLBACK_REQUIRES_RELEASE');
        }
        $installed = $this->repository->findPlugin($code);
        $installedManifest = is_array($installed['manifest_json'] ?? null) ? $installed['manifest_json'] : [];
        $previousState = (string)($context['previous_state']['lifecycle_status'] ?? $installed['lifecycle_status'] ?? 'uninstalled');
        if (in_array($operation, ['install', 'upgrade'], true)) {
            $version = (string)($context['to_version'] ?? '');
            if ($this->developerSources !== null) {
                if ($operation !== 'install') {
                    throw new PluginLifecycleException('开发来源不支持市场升级操作', 'DEVELOPER_OPERATION_UNSUPPORTED');
                }
                if ($previousState !== 'uninstalled' && $installedManifest !== []
                    && ($installedManifest['installation_source'] ?? '') !== 'developer') {
                    throw new PluginLifecycleException('不能覆盖同编码的市场或其他来源安装', 'DEVELOPER_SOURCE_CONFLICT');
                }
                if ($previousState !== 'uninstalled') {
                    throw new PluginLifecycleException('已安装开发插件不能通过重复安装重置代次', 'INVALID_LIFECYCLE_TRANSITION');
                }
                $source = $this->developerSources->resolve($code);
                $expectedHash = (string)($context['payload']['developer_source_sha256'] ?? '');
                if (realpath($root) !== $source['root'] || ($source['manifest']['version'] ?? '') !== $version
                    || !hash_equals($source['source_sha256'], $expectedHash)) {
                    throw new PluginLifecycleException('开发源码在任务创建后发生变化', 'DEVELOPER_SOURCE_CHANGED');
                }
                $manifest = $source['manifest'];
                unset($manifest['artifact_sha256'], $manifest['install_resources'], $manifest['installation_id']);
                $manifest['installation_source'] = 'developer';
                $manifest['developer_source_sha256'] = $source['source_sha256'];
            } else {
                if ($previousState !== 'uninstalled' && ($installedManifest['installation_source'] ?? '') === 'developer') {
                    throw new PluginLifecycleException('开发来源安装不能被市场制品静默覆盖', 'DEVELOPER_SOURCE_CONFLICT');
                }
                $manifest = $this->verifiedManifest($root, $code, $version, $context);
                $manifest['installation_source'] = 'market';
                unset($manifest['developer_source_sha256']);
                $manifest['artifact_sha256'] = (string)$context['artifact_sha256'];
            }
            $identity = $operation === 'upgrade' ? (string)($installedManifest['installation_id'] ?? '') : '';
            $manifest['installation_id'] = $identity !== '' ? $identity : bin2hex(random_bytes(16));
            $declaration = (new PluginInstallManifest())->read($root, $code);
            $manifest['install_resources'] = $declaration;
            if ($this->developerSources !== null) {
                // 首次 DDL 前保存服务器验证证明，失败恢复仍能识别原执行源码。
                $this->repository->updatePlugin($code, ['manifest_json' => $manifest]);
            }
            $this->migrate($root, $code, $version, $declaration, 'up');
            $this->resources->locked($code, function () use ($code, $root, $declaration, $manifest) {
                $this->developerSources?->assertInstalled($code, $manifest);
                $this->installResources($code, $root, $declaration, $manifest['installation_id']);
                $this->repository->updatePlugin($code, ['manifest_json' => $manifest]);
            });
            $this->cleanupCommitted = true;
            $this->invalidateCaches();
            return ['version' => $version, 'manifest_json' => $manifest, 'installation_id' => $manifest['installation_id'], 'reload_required' => true, 'frontend_rebuild_required' => true];
        }
        if ($operation !== 'uninstall') {
            throw new PluginLifecycleException('生命周期操作不支持', 'UNSUPPORTED_LIFECYCLE_OPERATION');
        }
        if ($this->developerSources !== null) {
            $source = $this->developerSources->resolveInstalled($code, $installedManifest);
            if (!in_array($previousState, ['active', 'disabled', 'failed'], true)) {
                throw new PluginLifecycleException('当前开发插件状态不允许卸载，请先核对生命周期任务与迁移账本', 'INVALID_LIFECYCLE_TRANSITION');
            }
            if ($source['root'] !== realpath($root)) {
                throw new PluginLifecycleException('开发源码执行目录与固定来源不一致', 'DEVELOPER_PATH_UNSAFE');
            }
            $manifest = $source['manifest'];
        } else {
            if (($installedManifest['installation_source'] ?? '') === 'developer' || empty($installedManifest['artifact_sha256'])) {
                throw new PluginLifecycleException('已安装清单缺少可信制品引用，禁止执行未知卸载脚本', 'ARTIFACT_VERIFICATION_REQUIRED');
            }
            $manifest = $this->artifacts->assertDeployed((string)$installedManifest['artifact_sha256'], $root, $code, (string)($installed['version'] ?? ''));
        }
        $trustedDeclaration = (new PluginInstallManifest())->read($root, $code);
        $declaration = $installedManifest['install_resources'] ?? $trustedDeclaration;
        if ($this->developerSources !== null && !empty($context['payload']['reinstall_base_key'])) {
            /*
             * 重装包含后续安装，先核对全部迁移，避免卸载完成后才发现不能安装。
             * 标记由开发重装入口生成，普通卸载不因此受未完成迁移限制。
             */
            $this->assertReinstallMigrationsReady($root, $code, $declaration);
            if ($declaration !== $trustedDeclaration) {
                $this->assertReinstallMigrationsReady($root, $code, $trustedDeclaration);
            }
        }
        // 受信清理要求只能拒绝保留策略，不能擅自替调用方改为删除。
        if (($trustedDeclaration['requires_operational_cleanup'] ?? false) && ($context['data_policy'] ?? 'retain') !== 'delete') {
            throw new PluginLifecycleException('此插件卸载必须选择清理运营数据；订单、支付、退款及财务等永久数据仍保留', 'PLUGIN_UNINSTALL_CLEANUP_REQUIRED');
        }
        $context['installation_id'] = (string)($installedManifest['installation_id'] ?? '');
        $hook = $this->hook($manifest, $root);
        // 前置检查改善错误提示；资源行锁内复查才是最终卸载依据。
        $hook?->assertUninstallable($context);
        $this->resources->locked($code, function () use ($hook, $context, $code, $declaration, $installedManifest) {
            if ($this->developerSources !== null) {
                $this->developerSources->resolveInstalled($code, $installedManifest);
            }
            $hook?->assertUninstallable($context);
            if (($context['data_policy'] ?? 'retain') === 'delete') {
                $hook?->cleanup($context);
            }
            $this->resources->removeResources($code, $declaration);
        });
        $this->cleanupCommitted = true;
        $unresolvedMigrations = [];
        if (($context['data_policy'] ?? 'retain') === 'delete') {
            $unresolvedMigrations = $this->migrate($root, $code, (string)($installed['version'] ?? ''), $declaration, 'down');
        }
        $this->invalidateCaches();
        return [
            'installation_id' => $context['installation_id'],
            'reload_required' => true,
            'frontend_rebuild_required' => true,
            'retained_order_evidence' => true,
            'unresolved_migrations' => $unresolvedMigrations,
        ];
    }

    /**
     * 重装前核对全部迁移，包括后续安装仍需检查的永久迁移。
     *
     * @param string $root 已校验的开发源码目录。
     * @param string $code 插件编码。
     * @param array $declaration 安装时保存或当前源码声明的迁移与保留规则。
     * @return void 无返回值；迁移未完成时抛出异常。
     */
    private function assertReinstallMigrationsReady(string $root, string $code, array $declaration): void
    {
        foreach ($declaration['migrations'] ?? [] as $entry) {
            $file = (new PluginInstallManifest())->safeFile($root, $entry['file']);
            $this->assertMigrationReady(
                $this->resources->migration($code, basename($file)),
                (string)hash_file('sha256', $file)
            );
        }
    }

    /**
     * 按安装来源核对迁移账本，开发源码可变更，未完成迁移仍需恢复。
     *
     * @param array|null $record 已保存的迁移账本；首次执行时为 null。
     * @param string $checksum 当前迁移文件摘要。
     * @return void 无返回值；账本不允许执行时抛出异常。
     */
    private function assertMigrationReady(?array $record, string $checksum): void
    {
        /*
         * 开发来源已验证当前源码，历史摘要不能阻止卸载、安装或重装。
         * 摘要仅在实际执行迁移时更新；跳过的已完成迁移保留原执行证据。
         * 市场来源仍须保持已登记迁移与受信制品一致，来源由服务器依赖决定。
         */
        if ($record && $this->developerSources === null && !hash_equals((string)$record['checksum'], $checksum)) {
            throw new PluginLifecycleException('已登记迁移校验和变更', 'PLUGIN_MIGRATION_CHECKSUM_MISMATCH');
        }
        if ($record && in_array($record['status'], ['pending', 'failed'], true)) {
            throw new PluginLifecycleException('迁移上次未完成，须先核对账本与实际结构', 'MIGRATION_RECOVERY_REQUIRED');
        }
    }

    /**
     * 存在独立提交的 DDL 或资源清理时阻止恢复启用，要求核对账本。
     *
     * @param string $operation 失败的生命周期操作。
     * @param array $context 失败操作的执行上下文。
     * @param Throwable $failure 原始执行异常。
     * @return void 无返回值；无法自动恢复时抛出恢复异常。
     */
    public function recover(string $operation, array $context, Throwable $failure): void
    {
        if ($this->ddlTouched || $this->cleanupCommitted || $operation === 'upgrade') {
            // 升级使用已部署的新源码，恢复旧状态行并不能恢复旧制品。
            throw new PluginLifecycleException('存在独立提交的迁移或卸载清理，保持禁用并按迁移账本恢复', 'MIGRATION_RECOVERY_REQUIRED', 0, $failure);
        }
    }

    /**
     * 核对成功下载任务、可信签名发布方及实际部署制品一致。
     *
     * @param string $root 插件实际部署根目录。
     * @param string $code 插件编码。
     * @param string $version 待安装的目标版本。
     * @param array $context 包含制品摘要和下载任务引用的上下文。
     * @return array 已验证的部署插件清单。
     */
    private function verifiedManifest(string $root, string $code, string $version, array $context): array
    {
        $task = $this->repository->findTask((string)($context['payload']['download_task_id'] ?? ''));
        $result = $task['result_json'] ?? [];
        $verification = $result['verification'] ?? [];
        if (!$task || ($task['task_type'] ?? '') !== 'download' || ($task['status'] ?? '') !== 'succeeded'
            || ($task['plugin_code'] ?? '') !== $code || ($task['to_version'] ?? '') !== $version
            || ($verification['verified'] ?? false) !== true || ($result['phase'] ?? '') !== 'verified'
            || ($task['artifact_sha256'] ?? '') !== ($context['artifact_sha256'] ?? '')
            || ($result['persistent_artifact_ref'] ?? '') !== ($context['artifact_sha256'] ?? '')) {
            throw new PluginLifecycleException('安装需要有效已验证下载任务', 'ARTIFACT_VERIFICATION_REQUIRED');
        }
        $trustedKey = $this->trustedSigningKeys[(string)($verification['signing_key_id'] ?? '')] ?? '';
        $key = $trustedKey !== '' ? openssl_pkey_get_public($trustedKey) : false;
        $fingerprint = $key ? hash('sha256', (string)(openssl_pkey_get_details($key)['key'] ?? '')) : '';
        if ($fingerprint === '' || !hash_equals($fingerprint, (string)($verification['signing_public_key_sha256'] ?? ''))) {
            throw new PluginLifecycleException('制品签名发布方未列入服务器信任目录', 'ARTIFACT_PUBLISHER_UNTRUSTED');
        }
        return $this->artifacts->assertDeployed((string)$result['persistent_artifact_ref'], $root, $code, $version);
    }

    /**
     * 按受审顺序执行迁移并保存账本，开发卸载保留未完成迁移供下次安装核对。
     *
     * @param string $root 插件实际部署根目录。
     * @param string $code 插件编码。
     * @param string $version 迁移关联的插件版本。
     * @param array $declaration 包含迁移顺序与卸载保留策略的声明。
     * @param string $direction 安装 up 或撤销 down 方向。
     * @return array<array{migration:string,status:string,direction:string}> 开发卸载保留的未完成迁移摘要。
     */
    private function migrate(string $root, string $code, string $version, array $declaration, string $direction): array
    {
        $migrations = $declaration['migrations'] ?? [];
        $unresolvedMigrations = [];
        usort($migrations, fn ($left, $right) => strcmp($left['file'], $right['file']));
        if ($direction === 'down') {
            $migrations = array_reverse($migrations);
        }
        foreach ($migrations as $entry) {
            if ($direction === 'down' && ($entry['retain_on_uninstall'] ?? false)) {
                continue;
            }
            $file = (new PluginInstallManifest())->safeFile($root, $entry['file']);
            $name = basename($file);
            $checksum = (string)hash_file('sha256', $file);
            $before = $this->resources->migration($code, $name);
            if ($direction === 'down' && $this->developerSources !== null
                && in_array($before['status'] ?? '', ['pending', 'failed'], true)) {
                /*
                 * 未完成的 DDL 可能已经部分提交，不能猜测其结构或盲目重跑 down。
                 * 普通卸载继续撤销其余资源，原账本保持不变，下次安装仍须核对。
                 */
                $unresolvedMigrations[] = [
                    'migration' => $name,
                    'status' => $before['status'],
                    'direction' => (string)($before['direction'] ?? ''),
                ];
                continue;
            }
            $this->assertMigrationReady($before, $checksum);
            if (($direction === 'up' && ($before['status'] ?? '') === 'applied')
                || ($direction === 'down' && (!$before || ($before['status'] ?? '') === 'rolled_back'))) {
                continue;
            }
            $this->resources->saveMigration($code, $name, ['plugin_version' => $version, 'checksum' => $checksum, 'direction' => $direction, 'status' => 'pending', 'error_message' => '']);
            $this->ddlTouched = true;
            $adapter = $this->adapter();
            try {
                require_once $file;
                $class = $entry['class'];
                $reflection = new \ReflectionClass($class);
                if (!is_subclass_of($class, Migrator::class) || realpath((string)$reflection->getFileName()) !== $file
                    || $reflection->getMethod($direction)->getDeclaringClass()->getName() !== $class) {
                    throw new PluginLifecycleException('迁移必须在受审文件显式实现 up/down', 'PLUGIN_MIGRATION_INVALID');
                }
                $migration = new $class('plugin', (int)substr($name, 0, 14));
                $migration->setAdapter($adapter);
                $migration->setMigratingUp($direction === 'up');
                $migration->$direction();
                $this->resources->saveMigration($code, $name, ['status' => $direction === 'up' ? 'applied' : 'rolled_back', $direction === 'up' ? 'executed_at' : 'rolled_back_at' => date('Y-m-d H:i:s')]);
            } catch (Throwable $failure) {
                // 底层连接或 SQL 报错必须带出来，否则只能看到笼统的“插件迁移失败”，无法定位实际结构问题。
                $detail = mb_substr(trim($failure->getMessage()), 0, 400);
                $this->resources->saveMigration($code, $name, [
                    'status' => 'failed',
                    'error_message' => $detail === '' ? '迁移失败，请核对实际结构后恢复' : '迁移失败，请核对实际结构后恢复：' . $detail,
                ]);
                throw new PluginLifecycleException(
                    $detail === '' ? '插件迁移失败' : '插件迁移失败：' . $detail,
                    'PLUGIN_MIGRATION_FAILED',
                    0,
                    $failure
                );
            } finally {
                /*
                 * 远端 MySQL 可能在连续 DDL 间回收空闲连接；每个迁移独立连接，
                 * 并在迁移结束后释放，避免下一条迁移复用失效 PDO。
                 */
                try {
                    $adapter->disconnect();
                } catch (Throwable) {
                    // 迁移结果已经落账时，连接释放失败不能覆盖真实执行结果。
                }
            }
        }
        return $unresolvedMigrations;
    }

    /**
     * 创建单主 MySQL 迁移适配器并沿用宿主表前缀。
     *
     * @return object 包含宿主表前缀的迁移适配器。
     */
    private function adapter(): object
    {
        $configuration = config('database.connections.' . config('database.default'));
        if (($configuration['type'] ?? '') !== 'mysql' || !empty($configuration['deploy'])) {
            throw new PluginLifecycleException('安装迁移要求单主 MySQL', 'PLUGIN_MIGRATION_INVALID');
        }
        $factory = AdapterFactory::instance();
        $adapter = $factory->getAdapter('mysql', [
            'adapter' => 'mysql', 'host' => $configuration['hostname'], 'port' => $configuration['hostport'], 'name' => $configuration['database'],
            'user' => $configuration['username'], 'pass' => $configuration['password'], 'charset' => $configuration['charset'],
            'table_prefix' => $configuration['prefix'],
        ]);
        $adapter->setInput(new \think\console\Input());
        $adapter->setOutput(new \think\console\Output());
        return $factory->getWrapper('prefix', $adapter);
    }

    /**
     * 安装归属受控的菜单、权限及带安装代次的移动页面资源。
     *
     * @param string $code 插件编码。
     * @param string $root 受信插件根目录。
     * @param array $declaration 移动页面等安装资源声明。
     * @param string $generation 本次安装代次。
     * @return void 无返回值。
     */
    private function installResources(string $code, string $root, array $declaration, string $generation): void
    {
        $menus = json_decode((string)file_get_contents((new PluginInstallManifest())->safeFile($root, 'resources/menus.json')), true, 64, JSON_THROW_ON_ERROR);
        $permissions = json_decode((string)file_get_contents((new PluginInstallManifest())->safeFile($root, 'resources/permissions.json')), true, 64, JSON_THROW_ON_ERROR);
        if (($menus['plugin_code'] ?? '') !== $code || ($permissions['plugin_code'] ?? '') !== $code) {
            throw new PluginLifecycleException('资源声明归属不一致', 'PLUGIN_RESOURCE_INVALID');
        }
        $parents = [];
        $parentPaths = [];
        $installedMenus = [];
        foreach ($menus['items'] ?? [] as $item) {
            $surface = (string)($item['surface'] ?? '');
            $route = (string)($item['route'] ?? '');
            $permission = (string)($item['permission'] ?? '');
            $parentId = $item['parent_id'] ?? 0;
            $parentRoute = (string)($item['parent_route'] ?? '');
            // 顶级纯分组菜单允许恰好 /{code}，供侧边栏渲染可展开但不跳转的分组节点。
            $isGroupRoute = $route === '/' . $code;
            if (!in_array($surface, ['admin', 'merchant'], true)
                || (!str_starts_with($route, '/' . $code . '/') && !$isGroupRoute)
                || !str_starts_with($permission, 'plugin:' . $code . ':') || !is_int($parentId) || $parentId < 0) {
                throw new PluginLifecycleException('菜单声明越界', 'PLUGIN_RESOURCE_INVALID');
            }
            if ($parentRoute !== '') {
                // 子项通过先前声明项的 route 引用本插件父级菜单；两种引用方式互斥。
                if ($parentId !== 0 || !isset($installedMenus[$surface][$parentRoute])) {
                    throw new PluginLifecycleException('菜单声明越界', 'PLUGIN_RESOURCE_INVALID');
                }
                $parentId = $installedMenus[$surface][$parentRoute]['id'];
                $parentPath = $installedMenus[$surface][$parentRoute]['path'];
            } else {
                $parentPath = $parentId > 0 ? $this->menuParentPath($parentId, $surface) : '';
            }
            $menu = $this->menuData($surface, (string)$item['title'], $route, '', 0, $permission, $parentId);
            $menu['path'] = $parentPath;
            $menu['icon'] = mb_substr((string)($item['icon'] ?? ''), 0, 50);
            $menu['sort'] = max(0, (int)($item['sort'] ?? 0));
            $menuId = $this->resources->installResource($code, 'menu', $surface . ':menu:' . $route, $menu);
            $installedMenus[$surface][$route] = ['id' => $menuId, 'path' => $parentPath];
            if ($parentId === 0 && $parentRoute === '') {
                // 接口权限行统一挂在顶级菜单下；声明多个顶级时取最后一个。
                $parents[$surface] = $menuId;
                $parentPaths[$surface] = $parentPath;
            }
        }
        foreach ($permissions['items'] ?? [] as $item) {
            $surface = (string)($item['surface'] ?? '');
            $path = ltrim((string)($item['path'] ?? ''), '/');
            $method = strtoupper((string)($item['method'] ?? ''));
            $permission = (string)($item['permission'] ?? '');
            if (!in_array($surface, ['admin', 'merchant'], true) || !str_starts_with($path, 'api/' . $surface . '/plugins/' . $code . '/')
                || !in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true) || !str_starts_with($permission, 'plugin:' . $code . ':')) {
                throw new PluginLifecycleException('权限声明越界', 'PLUGIN_RESOURCE_INVALID');
            }
            $path = preg_replace('/:([a-zA-Z_][a-zA-Z0-9_]*)/', '<$1>', $path);
            // 显示名称与鉴权标识分离；旧插件未声明标题时保持兼容。
            $title = $item['title'] ?? $permission;
            if (!is_string($title) || trim($title) === '') {
                throw new PluginLifecycleException('权限显示名称无效', 'PLUGIN_RESOURCE_INVALID');
            }
            $menuId = $parents[$surface] ?? 0;
            $resource = $this->menuData($surface, trim($title), $path, $method, 1, $permission, $menuId);
            if ($menuId > 0) {
                $resource['path'] = rtrim($parentPaths[$surface], '/') . '/' . $menuId . '/';
            }
            $this->resources->installResource($code, 'menu', $surface . ':api:' . $method . ':' . $path, $resource);
        }
        foreach ($declaration['mobile_pages'] ?? [] as $page) {
            $path = '/' . $page['path'];
            $url = $path . '?install_generation=' . rawurlencode($generation);
            $this->resources->installResource($code, 'page_link', $page['key'], [
                'cate_id' => 0, 'link_name' => mb_substr((string)$page['title'], 0, 100), 'link_url' => $url, 'link_type' => 'page', 'platform_type' => 'common',
                'status' => 1, 'sort' => 0, 'remark' => 'plugin:' . $code, 'param_desc' => '安装代次固定，卸载后旧链接失效', 'example' => $url,
            ]);
            $this->resources->installResource($code, 'page', $page['key'], [
                'page_name' => mb_substr((string)$page['title'], 0, 50), 'page_title' => mb_substr((string)$page['title'], 0, 100),
                'page_type' => 'plg_' . substr(hash('sha256', $code . ':' . $page['key']), 0, 16), 'global_config' => ['native_path' => $path, 'plugin_code' => $code, 'install_generation' => $generation],
                'is_default' => 0, 'status' => 1,
            ]);
        }
    }

    /**
     * 按宿主真实父级链生成完整祖先路径，不信任历史菜单可能缺失的 path 字段。
     *
     * @param int $parentId 已声明的宿主上级菜单 ID。
     * @param string $surface 平台或商户入口。
     * @return string 包含父菜单本身的完整路径。
     */
    private function menuParentPath(int $parentId, string $surface): string
    {
        $platformType = $surface === 'admin' ? 1 : 2;
        $ancestors = [];
        $seen = [];
        $id = $parentId;
        while ($id > 0) {
            if (isset($seen[$id]) || count($ancestors) >= 32) {
                throw new PluginLifecycleException('父菜单层级无效', 'PLUGIN_RESOURCE_INVALID');
            }
            $seen[$id] = true;
            $menu = $this->resources->parentMenu($id);
            if ($menu === null || (int)$menu['platform_type'] !== $platformType || (int)$menu['auth_type'] !== 0
                || (int)$menu['status'] !== 1 || (int)$menu['id'] !== $id) {
                throw new PluginLifecycleException('父菜单不存在或不属于当前端', 'PLUGIN_RESOURCE_INVALID');
            }
            $ancestors[] = $id;
            $id = (int)$menu['pid'];
        }
        $path = '/' . implode('/', array_reverse($ancestors)) . '/';
        if (strlen($path) > 235) {
            throw new PluginLifecycleException('父菜单路径超出宿主字段长度', 'PLUGIN_RESOURCE_INVALID');
        }
        return $path;
    }

    /**
     * 组装符合宿主权限类型与字段长度要求的菜单记录。
     *
     * @param string $surface 平台端或商户端标识。
     * @param string $title 菜单显示名称。
     * @param string $url 菜单路由或权限接口路径。
     * @param string $method 权限接口的 HTTP 方法；菜单为空。
     * @param int $type 菜单或功能权限类型。
     * @param string $permission 插件权限标识。
     * @param int $parent 父菜单 ID。
     * @return array 宿主菜单与权限字段。
     */
    private function menuData(string $surface, string $title, string $url, string $method, int $type, string $permission, int $parent): array
    {
        if (strlen($url) > 100 || strlen($permission) > 50) {
            throw new PluginLifecycleException('菜单或权限标识超过宿主字段长度', 'PLUGIN_RESOURCE_INVALID');
        }
        // 平台功能类型 2 才会进入宿主按 HTTP 方法校验权限的分支。
        $authType = $surface === 'admin' && $type === 1 ? 2 : $type;
        return ['pid' => $parent, 'icon' => '', 'name' => mb_substr($title, 0, 32), 'url' => $url, 'methods' => $method, 'sort' => 0,
            'access' => 0, 'path' => $parent ? '/' . $parent . '/' : '', 'auth_type' => $authType, 'auth_iden' => $permission,
            'status' => 1, 'is_hidden' => $type, 'platform_type' => $surface === 'admin' ? 1 : 2];
    }

    /**
     * 从已验证源码解析生命周期钩子，不支持钩子的插件返回空。
     *
     * @param array $manifest 受信插件清单。
     * @param string $root 受信插件部署根目录。
     * @return ?PluginLifecycleHookInterface 插件生命周期钩子实例；未实现接口时为 null。
     */
    private function hook(array $manifest, string $root): ?PluginLifecycleHookInterface
    {
        $provider = (string)($manifest['entrypoints']['provider'] ?? '');
        $namespace = (string)($manifest['namespace'] ?? '');
        if ($provider === '' || !str_starts_with($provider, $namespace . '\\')) {
            throw new PluginLifecycleException('插件 Provider 声明无效', 'PLUGIN_RESOURCE_INVALID');
        }
        $relative = substr($provider, strlen($namespace) + 1);
        require_once (new PluginInstallManifest())->safeFile($root, 'backend/src/' . str_replace('\\', '/', $relative) . '.php');
        $instance = app()->make($provider);
        return $instance instanceof PluginLifecycleHookInterface ? $instance : null;
    }

    /**
     * 清除插件、菜单权限和装修相关缓存，或调用注入的失效回调。
     *
     * @return void 无返回值。
     */
    private function invalidateCaches(): void
    {
        if ($this->cacheInvalidator !== null) {
            ($this->cacheInvalidator)();
            return;
        }
        foreach (['menus', 'admin_role', 'diy_page', 'diy_page_link', 'diy_component', 'config', 'plugin'] as $tag) {
            (new CacheTag($tag))->clear();
        }
    }
}
