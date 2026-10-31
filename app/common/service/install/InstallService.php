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

namespace app\common\service\install;

use app\common\enum\ConfigKeyEnum;
use app\common\model\setting\system\Admin;
use app\common\service\BaseService;
use PDO;
use Rabbit\util\Encrypter;
use Redis;
use RuntimeException;
use think\facade\Config;
use think\facade\Console;
use think\facade\Env;

/**
 * 系统安装服务。
 *
 * 安装阶段数据库尚未初始化，因此本服务使用临时 PDO 完成连接测试、建库和安装后初始化。
 */
class InstallService extends BaseService
{
    private const PHP_MIN_VERSION = '8.2.0';
    private const MYSQL_MIN_VERSION = '5.7';
    private const REDIS_MIN_VERSION = '5.0';
    private const DEFAULT_DB_CHARSET = 'utf8mb4';
    private const DEFAULT_DB_PREFIX = 'lf_';
    private const DEFAULT_REDIS_SELECT = 0;
    private const INSTALL_LOCK = 'install/install.lock';
    private const START_LOCK = 'install_start.lock';
    private const RUN_LOCK = 'install_run.lock';
    private const TASK_FILE = 'install_task.json';
    private const PROCESS_LOG_FILE = 'install_process.log';
    private const PROGRESS_FILE = 'install_progress.json';
    private const PROGRESS_LOG_LIMIT = 320;
    private const MIGRATION_PROGRESS_START = 60;
    private const MIGRATION_PROGRESS_END = 80;
    private const UPGRADE_PROGRESS_START = 80;
    private const UPGRADE_PROGRESS_END = 90;

    /**
     * 设置模型。
     *
     * @return string 安装完成后管理员资源对应的模型完整类名。
     */
    protected function setModel(): string
    {
        return Admin::class;
    }

    /**
     * 获取安装状态。
     *
     * @param string $domain 本次安装访问的站点域名，允许为空并使用默认本地主机地址。
     * @return array 安装完成标志、后台入口、安装时间及锁文件绝对路径。
     */
    public function getStatus(string $domain = ''): array
    {
        $lock = $this->readInstallLock();

        return [
            'installed' => $lock !== [],
            'admin_url' => $lock['admin_url'] ?? $this->buildAdminUrl($domain),
            'installed_at' => $lock['installed_at'] ?? '',
            'lock_file' => $this->installLockPath(),
        ];
    }

    /**
     * 检测安装环境。
     *
     * @return array 必要环境项是否全部通过及各版本、扩展、权限和容量检查结果。
     */
    public function checkEnvironment(): array
    {
        $items = [];
        $items[] = $this->buildCheckItem(
            'php',
            'PHP 版本',
            PHP_VERSION,
            '>=' . self::PHP_MIN_VERSION,
            version_compare(PHP_VERSION, self::PHP_MIN_VERSION, '>='),
            true
        );

        foreach ($this->requiredExtensions() as $extension => $label) {
            $items[] = $this->buildCheckItem(
                'ext_' . $extension,
                $label,
                extension_loaded($extension) ? '已安装' : '未安装',
                '已安装',
                extension_loaded($extension),
                true
            );
        }

        foreach ($this->writablePaths() as $key => $path) {
            $items[] = $this->buildCheckItem(
                'write_' . $key,
                $path['label'],
                $this->isPathWritable($path['path'], (bool)$path['file']) ? '可写' : '不可写',
                '可写',
                $this->isPathWritable($path['path'], (bool)$path['file']),
                true
            );
        }

        $items[] = $this->buildCheckItem(
            'disk',
            '磁盘空间',
            $this->formatBytes((int)@disk_free_space(root_path())),
            '建议 1GB+',
            ((int)@disk_free_space(root_path())) >= 1024 * 1024 * 1024,
            false
        );
        $items[] = $this->buildCheckItem(
            'memory_limit',
            'PHP 内存限制',
            (string)ini_get('memory_limit'),
            '建议 128M+',
            $this->memoryLimitToBytes((string)ini_get('memory_limit')) >= 128 * 1024 * 1024,
            false
        );

        return [
            'passed' => $this->allRequiredPassed($items),
            'items' => $items,
        ];
    }

    /**
     * 测试数据库或 Redis 配置。
     *
     * @param array $payload 需要测试的类型及数据库、Redis 连接参数，不执行建库或迁移。
     * @return array 所选数据库或 Redis 测试是否通过、失败原因及分项连接结果。
     */
    public function testConfig(array $payload): array
    {
        $type = (string)($payload['type'] ?? 'all');
        if (!in_array($type, ['all', 'database', 'redis'], true)) {
            return $this->failedResult('测试类型不正确');
        }

        $results = [];
        if ($type === 'all' || $type === 'database') {
            $database = is_array($payload['database'] ?? null) ? $payload['database'] : [];
            $databaseConfig = $this->normalizeDatabaseConfig($database);
            if (!$databaseConfig['valid']) {
                return $this->failedResult($databaseConfig['message']);
            }
            $results['database'] = $this->testDatabase($databaseConfig['database'], false);
        }
        if ($type === 'all' || $type === 'redis') {
            $redis = is_array($payload['redis'] ?? null) ? $payload['redis'] : [];
            $redisConfig = $this->normalizeRedisConfig($redis);
            if (!$redisConfig['valid']) {
                return $this->failedResult($redisConfig['message']);
            }
            $results['redis'] = $this->testRedis($redisConfig['redis']);
        }

        $passed = true;
        $message = '测试通过';
        foreach ($results as $result) {
            if (!($result['passed'] ?? false)) {
                $passed = false;
                $message = (string)($result['message'] ?? '测试失败');
                break;
            }
        }

        return [
            'passed' => $passed,
            'message' => $message,
            'results' => $results,
        ];
    }

    /**
     * 执行安装流程。
     *
     * @param array $payload 需要校验并用于实际安装的数据库、Redis 及管理员配置。
     * @param string $domain 本次安装访问的站点域名，允许为空并使用默认本地主机地址。
     * @return array 安装成功时返回后台入口、管理员账号及迁移输出，失败时返回原因和分项信息。
     */
    public function runInstall(array $payload, string $domain = ''): array
    {
        if ($this->isInstalled()) {
            return $this->failedResult('系统已安装，请勿重复安装');
        }

        $config = $this->normalizeInstallConfig($payload, true);
        if (!$config['valid']) {
            return $this->failedResult($config['message']);
        }

        $lock = fopen(runtime_path() . self::RUN_LOCK, 'c');
        if (!$lock) {
            return $this->failedResult('无法创建安装锁');
        }

        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            return $this->failedResult('已有安装任务正在执行');
        }

        try {
            $this->resetProgress();
            $this->writeProgress('checking', 5, '正在检查安装状态');
            if ($this->isInstalled()) {
                return $this->failedResult('系统已安装，请勿重复安装');
            }

            $this->writeProgress('testing', 15, '正在测试数据库连接');
            $dbTest = $this->testDatabase($config['database'], true);
            if (!$dbTest['passed']) {
                $this->writeProgress('failed', 15, (string)$dbTest['message']);
                return $this->failedResult((string)$dbTest['message'], ['database' => $dbTest]);
            }

            $this->writeProgress('testing', 25, '正在测试 Redis 连接');
            $redisTest = $this->testRedis($config['redis']);
            if (!$redisTest['passed']) {
                $this->writeProgress('failed', 25, (string)$redisTest['message']);
                return $this->failedResult((string)$redisTest['message'], ['redis' => $redisTest]);
            }

            $pdo = $this->connectDatabaseServer($config['database']);
            $this->writeProgress('database', 35, '正在准备数据库');
            $this->createDatabase($pdo, $config['database']);

            $databasePdo = $this->connectDatabase($config['database']);
            $this->assertDatabaseIsEmptyForInstall($databasePdo, $config['database']['prefix']);

            $this->writeProgress('env', 45, '正在写入环境配置');
            $this->backupEnvironmentFile();
            $appUrl = $this->normalizeDomain($domain);
            $appKey = $this->writeEnvironmentFile($config, $appUrl);
            $this->applyRuntimeConfig($config, $appUrl, $appKey);

            $this->writeProgress('migration', self::MIGRATION_PROGRESS_START, '正在执行数据库迁移');
            $migrationOutput = $this->runMigrations();

            $upgradeOutput = $this->runUpgradeSql($databasePdo, $config['database']['prefix']);

            $this->writeProgress('admin', 92, '正在初始化管理员账号');
            $this->initializeAdmin($databasePdo, $config['database']['prefix'], $config['admin']);
            $this->initializeSiteConfig($databasePdo, $config['database']['prefix'], $appUrl);

            $adminUrl = $this->buildAdminUrl($appUrl);
            $this->writeProgress('lock', 97, '正在写入安装完成标记');
            $this->writeInstallLock([
                'installed_at' => date('Y-m-d H:i:s'),
                'admin_url' => $adminUrl,
                'database' => $config['database']['database'],
                'prefix' => $config['database']['prefix'],
                'php_version' => PHP_VERSION,
            ]);

            $result = [
                'admin_url' => $adminUrl,
                'admin_account' => $config['admin']['account'],
                'migration_output' => $migrationOutput,
                'upgrade_output' => $upgradeOutput,
            ];
            $this->writeProgress('finished', 100, '安装完成', $result);

            return [
                'passed' => true,
                'message' => '安装完成',
                'data' => $result,
            ];
        } catch (\Throwable $e) {
            $currentProgress = $this->getProgress();
            $this->writeProgress('failed', (int)($currentProgress['percent'] ?? 0), $e->getMessage());
            return $this->failedResult($e->getMessage());
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * 创建后台安装任务。
     *
     * @param array $payload 待校验并写入后台任务文件的完整安装参数。
     * @param string $domain 本次安装访问的站点域名，允许为空并使用默认本地主机地址。
     * @return array 后台安装任务是否启动、进程编号及失败原因。
     */
    public function startInstall(array $payload, string $domain = ''): array
    {
        if ($this->isInstalled()) {
            return $this->failedResult('系统已安装，请勿重复安装');
        }

        $config = $this->normalizeInstallConfig($payload, true);
        if (!$config['valid']) {
            return $this->failedResult($config['message']);
        }

        $lock = fopen(runtime_path() . self::START_LOCK, 'c');
        if (!$lock) {
            return $this->failedResult('无法创建安装启动锁');
        }

        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);

            return $this->failedResult('已有安装任务正在启动');
        }

        try {
            if ($this->isInstallTaskRunning()) {
                return $this->failedResult('已有安装任务正在执行');
            }

            $this->resetProgress();
            $this->writeProgress('starting', 1, '安装任务已创建，正在启动后台进程');
            $this->writeInstallTask($payload, $domain);
            $processId = $this->startBackgroundInstallProcess();

            return [
                'passed' => true,
                'message' => '安装任务已启动',
                'data' => [
                    'started' => true,
                    'process_id' => $processId,
                ],
            ];
        } catch (\Throwable $e) {
            $this->clearInstallTask();
            $this->writeProgress('failed', 0, '启动安装任务失败：' . $e->getMessage());

            return $this->failedResult('启动安装任务失败：' . $e->getMessage());
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * 执行已创建的后台安装任务。
     *
     * @return array 已读取后台任务的安装成功资料或失败原因。
     */
    public function runPendingInstall(): array
    {
        try {
            $task = $this->readInstallTask();
            $this->clearInstallTask();
            $result = $this->runInstall($task['payload'], $task['domain']);
            if (!$result['passed'] && ($this->getProgress()['step'] ?? '') !== 'failed') {
                $progress = $this->getProgress();
                $this->writeProgress(
                    'failed',
                    (int)($progress['percent'] ?? 0),
                    (string)$result['message']
                );
            }

            return $result;
        } catch (\Throwable $e) {
            $progress = $this->getProgress();
            $this->writeProgress(
                'failed',
                (int)($progress['percent'] ?? 0),
                '后台安装任务执行失败：' . $e->getMessage()
            );

            return $this->failedResult('后台安装任务执行失败：' . $e->getMessage());
        }
    }

    /**
     * 获取安装进度。
     *
     * @return array 默认字段已补齐且结果资料已脱敏的当前安装进度及日志。
     */
    public function getProgress(): array
    {
        return $this->readProgress();
    }

    /**
     * 读取安装进度文件。
     *
     * @return array 读取并补齐默认字段的安装进度，文件缺失或无效时返回默认等待状态。
     */
    private function readProgress(): array
    {
        $path = runtime_path() . self::PROGRESS_FILE;
        if (!is_file($path)) {
            return $this->defaultProgress();
        }

        $content = (string)file_get_contents($path);
        $data = json_decode($content, true);
        if (!is_array($data)) {
            return $this->defaultProgress();
        }

        $progress = array_merge($this->defaultProgress(), $data);
        $progress['data'] = is_array($progress['data'] ?? null)
            ? $this->sanitizeProgressData($progress['data'])
            : [];
        $progress['logs'] = is_array($progress['logs'] ?? null) ? $progress['logs'] : [];

        return $progress;
    }

    /**
     * 规范化安装参数。
     *
     * @param array $payload 需要统一校验的原始数据库、Redis 及管理员安装参数。
     * @param bool $withAdmin 是否要求校验管理员账号及确认密码，并在未输入密码时生成密码。
     * @return array 校验标志、失败原因及归一化后的数据库、Redis 和管理员配置。
     */
    private function normalizeInstallConfig(array $payload, bool $withAdmin): array
    {
        $database = is_array($payload['database'] ?? null) ? $payload['database'] : [];
        $redis = is_array($payload['redis'] ?? null) ? $payload['redis'] : [];
        $admin = is_array($payload['admin'] ?? null) ? $payload['admin'] : [];

        $databaseConfig = $this->normalizeDatabaseConfig($database);
        if (!$databaseConfig['valid']) {
            return $databaseConfig;
        }

        $redisConfig = $this->normalizeRedisConfig($redis);
        if (!$redisConfig['valid']) {
            return $redisConfig;
        }

        $adminConfig = $this->normalizeAdminConfig($admin, $withAdmin);
        if (!$adminConfig['valid']) {
            return $adminConfig;
        }

        return [
            'valid' => true,
            'message' => '',
            'database' => $databaseConfig['database'],
            'redis' => $redisConfig['redis'],
            'admin' => $adminConfig['admin'],
        ];
    }

    /**
     * 规范化数据库参数。
     *
     * @param array $database 待归一化的数据库连接参数，包含库名、地址、账号和表前缀。
     * @return array 校验标志、失败原因及固定驱动和字符集的数据库连接配置。
     */
    private function normalizeDatabaseConfig(array $database): array
    {
        $dbName = trim((string)($database['database'] ?? ''));
        $dbHost = trim((string)($database['hostname'] ?? '127.0.0.1'));
        $dbPort = (int)($database['hostport'] ?? 3306);
        $dbUser = trim((string)($database['username'] ?? ''));
        $dbPrefix = trim((string)($database['prefix'] ?? self::DEFAULT_DB_PREFIX));
        if ($dbHost === '') {
            return ['valid' => false, 'message' => '请输入数据库地址'];
        }
        if ($dbPort < 1 || $dbPort > 65535) {
            return ['valid' => false, 'message' => '数据库端口不正确'];
        }
        if ($dbName === '') {
            return ['valid' => false, 'message' => '请输入数据库名称'];
        }
        if (!preg_match('/^[A-Za-z0-9_]+$/', $dbName)) {
            return ['valid' => false, 'message' => '数据库名称只允许字母、数字和下划线'];
        }
        if ($dbUser === '') {
            return ['valid' => false, 'message' => '请输入数据库账号'];
        }
        if ($dbPrefix === '') {
            return ['valid' => false, 'message' => '请输入数据表前缀'];
        }
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{0,31}$/', $dbPrefix)) {
            return ['valid' => false, 'message' => '数据表前缀需以字母开头，只允许字母、数字和下划线，长度 1-32 位'];
        }

        return [
            'valid' => true,
            'message' => '',
            'database' => [
                'type' => 'mysql',
                'hostname' => $dbHost,
                'hostport' => $dbPort,
                'database' => $dbName,
                'username' => $dbUser,
                'password' => (string)($database['password'] ?? ''),
                'charset' => self::DEFAULT_DB_CHARSET,
                'prefix' => $dbPrefix,
            ],
        ];
    }

    /**
     * 规范化 Redis 参数。
     *
     * @param array $redis 待归一化的 Redis 连接参数，安装逻辑固定使用零号数据库。
     * @return array 校验标志、失败原因及使用零号库和新隔离缓存、队列名称的 Redis 配置。
     */
    private function normalizeRedisConfig(array $redis): array
    {
        $redisHost = trim((string)($redis['host'] ?? '127.0.0.1'));
        $redisPort = (int)($redis['port'] ?? 6379);
        $redisSelect = $redis['select'] ?? self::DEFAULT_REDIS_SELECT;
        if ($redisHost === '') {
            return ['valid' => false, 'message' => '请输入 Redis 地址'];
        }
        if ($redisPort < 1 || $redisPort > 65535) {
            return ['valid' => false, 'message' => 'Redis 端口不正确'];
        }

        $existing = $this->readEnvironmentValues();
        $reservedNames = array_values(array_filter([
            $existing['REDIS_PREFIX'] ?? '',
            $existing['REDIS_TAG_PREFIX'] ?? '',
            $existing['QUEUE_NAME'] ?? '',
        ]));
        $prefix = $this->generateRuntimeName('manage_cache', $reservedNames);
        $queue = $this->generateRuntimeName('manage_queue', array_merge($reservedNames, [$prefix]));

        return [
            'valid' => true,
            'message' => '',
            'redis' => [
                'host' => $redisHost,
                'port' => $redisPort,
                'password' => (string)($redis['password'] ?? ''),
                'select' => self::DEFAULT_REDIS_SELECT,
                'prefix' => $prefix,
                'queue' => $queue,
            ],
        ];
    }

    /**
     * 规范化管理员参数。
     *
     * @param array $admin 管理员账号、密码及确认密码的原始输入。
     * @param bool $withAdmin 是否要求校验管理员账号及确认密码，并在未输入密码时使用默认密码。
     * @return array 校验标志、失败原因及管理员账号和原输入或默认密码。
     */
    private function normalizeAdminConfig(array $admin, bool $withAdmin): array
    {
        $account = trim((string)($admin['account'] ?? ''));
        $password = (string)($admin['password'] ?? '');
        $confirmPassword = (string)($admin['confirm_password'] ?? '');
        if ($withAdmin) {
            if ($account === '') {
                $account = 'demo';
            }
            if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{2,31}$/', $account)) {
                return ['valid' => false, 'message' => '管理员账号需以字母开头，支持字母、数字、下划线，长度 3-32 位'];
            }
            if ($password !== '' || $confirmPassword !== '') {
                if ($password !== $confirmPassword) {
                    return ['valid' => false, 'message' => '两次输入的管理员密码不一致'];
                }
                if (strlen($password) < 6) {
                    return ['valid' => false, 'message' => '管理员密码至少 6 位'];
                }
            } else {
                $password = 'demo';
            }
        }

        return [
            'valid' => true,
            'message' => '',
            'admin' => [
                'account' => $account,
                'password' => $password,
            ],
        ];
    }

    /**
     * 测试数据库连接。
     *
     * @param array $config 已经归一化校验的数据库连接配置。
     * @param bool $strictEmpty 是否额外检查已存在目标库中没有同前缀表。
     * @return array 数据库连通、最低版本、目标库存在情况及可选同前缀空表检查结果。
     */
    private function testDatabase(array $config, bool $strictEmpty): array
    {
        try {
            $pdo = $this->connectDatabaseServer($config);
            $version = (string)$pdo->query('SELECT VERSION()')->fetchColumn();
            if (!version_compare($this->normalizeServerVersion($version), self::MYSQL_MIN_VERSION, '>=')) {
                return $this->failedResult('MySQL 版本需不低于 ' . self::MYSQL_MIN_VERSION, ['version' => $version]);
            }

            $exists = $this->databaseExists($pdo, $config['database']);
            $message = $exists ? '数据库连接成功' : '数据库连接成功，安装时将自动创建数据库';
            if ($exists && $strictEmpty) {
                $databasePdo = $this->connectDatabase($config);
                $this->assertDatabaseIsEmptyForInstall($databasePdo, $config['prefix']);
            }

            return [
                'passed' => true,
                'message' => $message,
                'version' => $version,
                'database_exists' => $exists,
            ];
        } catch (\Throwable $e) {
            return $this->failedResult('数据库连接失败：' . $e->getMessage());
        }
    }

    /**
     * 测试 Redis 连接。
     *
     * @param array $config 已经归一化的 Redis 地址、认证、数据库编号及隔离前缀。
     * @return array Redis 连接、认证、数据库选择、最低版本及短期测试键读写结果。
     */
    private function testRedis(array $config): array
    {
        if (!class_exists(Redis::class)) {
            return $this->failedResult('Redis 扩展未安装');
        }

        $redis = new Redis();
        try {
            if (!$redis->connect($config['host'], (int)$config['port'], 3.0)) {
                return $this->failedResult('Redis 连接失败');
            }
            if ($config['password'] !== '' && !$redis->auth($config['password'])) {
                return $this->failedResult('Redis 密码错误');
            }
            if (!$redis->select((int)$config['select'])) {
                return $this->failedResult('Redis 数据库编号不可用');
            }
            $info = $redis->info();
            $version = (string)($info['redis_version'] ?? '');
            if ($version !== '' && !version_compare($this->normalizeServerVersion($version), self::REDIS_MIN_VERSION, '>=')) {
                return $this->failedResult('Redis 版本需不低于 ' . self::REDIS_MIN_VERSION, ['version' => $version]);
            }

            $key = $config['prefix'] . ':install:test:' . bin2hex(random_bytes(4));
            $redis->set($key, 'ok', 10);
            if ($redis->get($key) !== 'ok') {
                return $this->failedResult('Redis 读写测试失败');
            }
            $redis->del($key);

            return [
                'passed' => true,
                'message' => 'Redis 连接成功',
                'version' => $version,
            ];
        } catch (\Throwable $e) {
            return $this->failedResult('Redis 连接失败：' . $e->getMessage());
        } finally {
            try {
                $redis->close();
            } catch (\Throwable) {
            }
        }
    }

    /**
     * 连接 MySQL 服务端。
     *
     * @param array $config 已经归一化校验的数据库连接配置。
     * @return PDO 尚未指定目标数据库且启用异常模式的临时 MySQL 连接。
     */
    private function connectDatabaseServer(array $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;charset=%s',
            $config['hostname'],
            (int)$config['hostport'],
            $config['charset']
        );

        return new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    /**
     * 连接指定数据库。
     *
     * @param array $config 已经归一化校验的数据库连接配置。
     * @return PDO 已指定目标数据库且启用异常模式的临时 MySQL 连接。
     */
    private function connectDatabase(array $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['hostname'],
            (int)$config['hostport'],
            $config['database'],
            $config['charset']
        );

        return new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    /**
     * 判断数据库是否存在。
     *
     * @param PDO $pdo 未指定目标库的 MySQL 服务端临时连接。
     * @param string $database 需要检查是否存在的数据库名称。
     * @return bool MySQL 服务端是否存在指定名称的数据库。
     */
    private function databaseExists(PDO $pdo, string $database): bool
    {
        $statement = $pdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ? LIMIT 1');
        $statement->execute([$database]);
        return (bool)$statement->fetchColumn();
    }

    /**
     * 创建数据库。
     *
     * @param PDO $pdo 未指定目标库的 MySQL 服务端临时连接。
     * @param array $config 已经归一化校验的数据库连接配置。
     * @return void 无返回值；目标数据库不存在时按安装字符集创建。
     */
    private function createDatabase(PDO $pdo, array $config): void
    {
        $database = $this->quoteIdentifier($config['database']);
        $charset = $config['charset'];
        $pdo->exec("CREATE DATABASE IF NOT EXISTS {$database} DEFAULT CHARACTER SET {$charset} COLLATE utf8mb4_general_ci");
    }

    /**
     * 确保目标库没有同前缀表。
     *
     * @param PDO $pdo 连接指定目标数据库的安装阶段临时连接。
     * @param string $prefix 本次安装使用的数据表前缀。
     * @return void 无返回值；目标库已有同前缀表时抛出异常，避免覆盖原有系统。
     */
    private function assertDatabaseIsEmptyForInstall(PDO $pdo, string $prefix): void
    {
        $statement = $pdo->prepare('SHOW TABLES LIKE ?');
        $statement->execute([$prefix . '%']);
        $tables = $statement->fetchAll(PDO::FETCH_COLUMN);
        if ($tables) {
            throw new RuntimeException('目标数据库已存在同前缀数据表，请更换数据库或表前缀后再安装');
        }
    }

    /**
     * 写入 .env 文件。
     *
     * @param array $config 包含数据库、Redis 和管理员的已校验安装配置。
     * @param string $appUrl 归一化后的站点地址，用于环境配置、后台入口和默认通知地址。
     * @return string 本次生成并写入环境文件的应用密钥，用于同步当前进程配置。
     */
    private function writeEnvironmentFile(array $config, string $appUrl): string
    {
        $this->ensureEnvironmentFile();
        $existing = $this->readEnvironmentValues();
        $appKey = $this->generateAppKey();
        $websocketUrl = (string)($existing['WEBSOCKET_SERVER_URL'] ?? '');
        if ($websocketUrl === '') {
            $websocketUrl = $this->buildWebsocketUrl($appUrl);
        }

        $content = implode("\n", [
            'APP_DEBUG = ' . ($existing['APP_DEBUG'] ?? 'false'),
            'DEFAULT_LANG = ' . ($existing['DEFAULT_LANG'] ?? 'zh-cn'),
            'HOST = ' . $appUrl,
            'APP_KEY = ' . $appKey,
            'WEBSOCKET_SERVER_URL = ' . $websocketUrl,
            '',
            '[APP]',
            'DEFAULT_TIMEZONE = ' . ($existing['APP_DEFAULT_TIMEZONE'] ?? 'Asia/Shanghai'),
            '',
            '[DATABASE]',
            'DRIVER = mysql',
            'TYPE = mysql',
            'HOSTNAME = ' . $config['database']['hostname'],
            'DATABASE = ' . $config['database']['database'],
            'USERNAME = ' . $config['database']['username'],
            'PASSWORD = ' . $config['database']['password'],
            'HOSTPORT = ' . $config['database']['hostport'],
            'CHARSET = ' . $config['database']['charset'],
            'PREFIX = ' . $config['database']['prefix'],
            'DEBUG = false',
            '',
            '[REDIS]',
            'HOSTNAME = ' . $config['redis']['host'],
            'PORT = ' . $config['redis']['port'],
            'PASSWORD = ' . $config['redis']['password'],
            'SELECT = ' . $config['redis']['select'],
            'PREFIX = ' . $config['redis']['prefix'],
            'TAG_PREFIX = ' . $config['redis']['prefix'] . '_tag_',
            '',
            '[QUEUE]',
            'NAME = ' . $config['redis']['queue'],
            '',
        ]);

        $path = root_path() . '.env';
        if (@file_put_contents($path, $content, LOCK_EX) === false) {
            throw new RuntimeException('写入 .env 文件失败');
        }

        return $appKey;
    }

    /**
     * 确保 .env 文件可以被安装流程创建。
     *
     * @return void 无返回值；环境文件不存在时创建空文件，权限不足时抛出异常。
     */
    private function ensureEnvironmentFile(): void
    {
        $path = root_path() . '.env';
        if (is_file($path)) {
            return;
        }

        $directory = dirname($path);
        if (!is_dir($directory) || !is_writable($directory)) {
            throw new RuntimeException('无法创建 .env 文件，请检查项目根目录写入权限');
        }

        if (@file_put_contents($path, '', LOCK_EX) === false) {
            throw new RuntimeException('无法创建 .env 文件');
        }
    }

    /**
     * 备份 .env 文件。
     *
     * @return void 无返回值；已有环境文件按时间戳备份，备份失败时抛出异常。
     */
    private function backupEnvironmentFile(): void
    {
        $path = root_path() . '.env';
        if (!is_file($path)) {
            return;
        }

        $backup = root_path() . '.env.install_backup.' . date('YmdHis');
        if (!@copy($path, $backup)) {
            throw new RuntimeException('备份 .env 文件失败');
        }
    }

    /**
     * 将安装配置同步到当前进程。
     *
     * @param array $config 已校验的数据库和 Redis 安装配置。
     * @param string $appUrl 安装后的应用地址。
     * @param string $appKey 写入环境文件的新应用密钥。
     * @return void 无返回值；同步当前进程环境、数据库、缓存、队列与应用配置并清除旧加密器实例。
     */
    private function applyRuntimeConfig(array $config, string $appUrl, string $appKey): void
    {
        Env::set([
            'APP_KEY' => $appKey,
            'APP_HOST' => $appUrl,
            'DATABASE_DRIVER' => 'mysql',
            'DATABASE_TYPE' => 'mysql',
            'DATABASE_HOSTNAME' => $config['database']['hostname'],
            'DATABASE_DATABASE' => $config['database']['database'],
            'DATABASE_USERNAME' => $config['database']['username'],
            'DATABASE_PASSWORD' => $config['database']['password'],
            'DATABASE_HOSTPORT' => (string)$config['database']['hostport'],
            'DATABASE_CHARSET' => $config['database']['charset'],
            'DATABASE_PREFIX' => $config['database']['prefix'],
            'REDIS_HOSTNAME' => $config['redis']['host'],
            'REDIS_PORT' => (string)$config['redis']['port'],
            'REDIS_PASSWORD' => $config['redis']['password'],
            'REDIS_SELECT' => (string)$config['redis']['select'],
            'REDIS_PREFIX' => $config['redis']['prefix'],
            'REDIS_TAG_PREFIX' => $config['redis']['prefix'] . '_tag_',
            'QUEUE_NAME' => $config['redis']['queue'],
        ]);

        $databaseConfig = Config::get('database', []);
        $databaseConfig['default'] = 'mysql';
        $databaseConfig['connections']['mysql'] = array_merge(
            $databaseConfig['connections']['mysql'] ?? [],
            [
                'type' => 'mysql',
                'hostname' => $config['database']['hostname'],
                'database' => $config['database']['database'],
                'username' => $config['database']['username'],
                'password' => $config['database']['password'],
                'hostport' => (string)$config['database']['hostport'],
                'charset' => $config['database']['charset'],
                'prefix' => $config['database']['prefix'],
            ]
        );
        Config::set($databaseConfig, 'database');

        $cacheConfig = Config::get('cache', []);
        $cacheConfig['default'] = 'redis';
        $cacheConfig['stores']['redis'] = array_merge(
            $cacheConfig['stores']['redis'] ?? [],
            [
                'host' => $config['redis']['host'],
                'port' => $config['redis']['port'],
                'password' => $config['redis']['password'],
                'select' => $config['redis']['select'],
                'prefix' => $config['redis']['prefix'],
                'tag_prefix' => $config['redis']['prefix'] . '_tag_',
            ]
        );
        Config::set($cacheConfig, 'cache');

        $queueConfig = Config::get('queue', []);
        $queueConfig['default'] = 'redis';
        $queueConfig['connections']['redis'] = array_merge(
            $queueConfig['connections']['redis'] ?? [],
            [
                'queue' => $config['redis']['queue'],
                'host' => $config['redis']['host'],
                'port' => $config['redis']['port'],
                'password' => $config['redis']['password'],
                'select' => $config['redis']['select'],
            ]
        );
        Config::set($queueConfig, 'queue');

        $appConfig = Config::get('app', []);
        $appConfig['key'] = $appKey;
        $appConfig['app_key'] = $appKey;
        $appConfig['app_host'] = $appUrl;
        Config::set($appConfig, 'app');
        // 容器会缓存加密器，配置切换后必须重新解析，避免以旧密钥封装新私钥。
        app()->delete(Encrypter::class);
    }

    /**
     * 执行迁移。
     *
     * @return string 完整迁移控制台输出；进程失败时抛出包含末条有效日志的异常。
     */
    private function runMigrations(): string
    {
        $total = $this->getMigrationFileCount();
        $this->appendProgressLog('migration', "开始执行数据库迁移，共 {$total} 个迁移文件");

        if (!function_exists('proc_open')) {
            return $this->runMigrationsWithBuffer($total);
        }

        $command = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg(root_path() . 'think')
            . ' migrate:run';
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, root_path());

        if (!is_resource($process)) {
            $this->appendProgressLog('migration', '无法启动实时迁移进程，已切换为缓冲执行', 'warning');

            return $this->runMigrationsWithBuffer($total);
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $output = '';
        $lineBuffer = '';
        $completed = 0;
        $exitCode = null;

        try {
            while (true) {
                $status = proc_get_status($process);
                $read = [];
                foreach ([1, 2] as $index) {
                    if (is_resource($pipes[$index]) && !feof($pipes[$index])) {
                        $read[] = $pipes[$index];
                    }
                }

                if ($read !== []) {
                    $write = null;
                    $except = null;
                    $changed = @stream_select($read, $write, $except, 0, 200000);
                    if ($changed !== false && $changed > 0) {
                        foreach ($read as $stream) {
                            $chunk = stream_get_contents($stream);
                            if (is_string($chunk) && $chunk !== '') {
                                $this->consumeMigrationOutput($chunk, $output, $lineBuffer, $total, $completed);
                            }
                        }
                    } else {
                        usleep(100000);
                    }
                } elseif ($status['running']) {
                    usleep(200000);
                }

                if (!$status['running']) {
                    $exitCode = (int)($status['exitcode'] ?? 1);
                    foreach ([1, 2] as $index) {
                        $chunk = is_resource($pipes[$index]) ? stream_get_contents($pipes[$index]) : '';
                        if (is_string($chunk) && $chunk !== '') {
                            $this->consumeMigrationOutput($chunk, $output, $lineBuffer, $total, $completed);
                        }
                    }
                    break;
                }
            }
        } finally {
            foreach ([1, 2] as $index) {
                if (is_resource($pipes[$index])) {
                    fclose($pipes[$index]);
                }
            }
        }

        $closeCode = proc_close($process);
        if ($exitCode === null || $exitCode < 0) {
            $exitCode = $closeCode;
        }

        $this->flushMigrationOutput($lineBuffer, $total, $completed);
        if ($exitCode !== 0) {
            $this->appendProgressLog('migration', '数据库迁移执行失败', 'error');
            throw new RuntimeException($this->buildMigrationFailureMessage($output));
        }

        $this->writeProgress(
            'migration',
            self::MIGRATION_PROGRESS_END,
            "数据库迁移执行完成（{$completed}/{$total}）",
            [],
            false
        );
        $this->appendProgressLog('migration', '数据库迁移执行完成', 'success');

        return trim($output);
    }

    /**
     * 使用原有控制台缓冲方式执行迁移，供不支持 proc_open 的环境降级。
     *
     * @param int $total 用于迁移阶段进度计算的文件总计数，分母至少按一处理。
     * @return string 缓冲执行所得迁移输出，并同步日志及阶段进度。
     */
    private function runMigrationsWithBuffer(int $total): string
    {
        $output = Console::call('migrate:run');
        $content = method_exists($output, 'fetch') ? (string)$output->fetch() : '';
        $lineBuffer = '';
        $completed = 0;
        $capturedOutput = '';
        $this->consumeMigrationOutput($content, $capturedOutput, $lineBuffer, $total, $completed);
        $this->flushMigrationOutput($lineBuffer, $total, $completed);
        $this->writeProgress(
            'migration',
            self::MIGRATION_PROGRESS_END,
            "数据库迁移执行完成（{$completed}/{$total}）",
            [],
            false
        );
        $this->appendProgressLog('migration', '数据库迁移执行完成', 'success');

        return trim($capturedOutput);
    }

    /**
     * 执行迁移版本目录下的初始数据 SQL（database/migrations/vX.Y/upgrade.sql）。
     *
     * @param PDO $pdo 连接指定目标数据库的安装阶段临时连接。
     * @param string $prefix 本次安装使用的数据表前缀。
     * @return string 已执行初始 SQL 的目标表或语句序号日志；无数据文件时为空字符串。
     */
    private function runUpgradeSql(PDO $pdo, string $prefix): string
    {
        $files = $this->findUpgradeSqlFiles();
        if ($files === []) {
            $this->appendProgressLog('migration', '未找到初始数据 SQL 文件，已跳过', 'warning');

            return '';
        }

        $statements = [];
        foreach ($files as $file) {
            $content = (string)file_get_contents($file);
            $statements = array_merge(
                $statements,
                $this->splitSqlStatements($this->normalizeUpgradeSql($content, $prefix))
            );
        }
        $statements = array_values(array_filter(
            $statements,
            static fn (string $statement): bool => trim($statement) !== ''
        ));

        $total = count($statements);
        $this->appendProgressLog('migration', "开始导入初始数据，共 {$total} 条 SQL");

        $completed = 0;
        $output = [];
        foreach ($statements as $statement) {
            $table = $this->extractUpgradeTableName($statement);
            try {
                $pdo->exec($statement);
            } catch (\Throwable $e) {
                throw new RuntimeException(
                    '导入初始数据失败（'
                    . ($table !== '' ? "表 {$table}" : '第 ' . ($completed + 1) . ' 条 SQL')
                    . '）：' . $e->getMessage(),
                    0,
                    $e
                );
            }

            $completed++;
            $percent = self::UPGRADE_PROGRESS_START + (int)floor(
                ($completed / max(1, $total)) * (self::UPGRADE_PROGRESS_END - self::UPGRADE_PROGRESS_START)
            );
            $this->appendProgressLog(
                'migration',
                $table !== ''
                    ? "初始数据：{$table}（{$completed}/{$total}）"
                    : "初始数据：第 {$completed}/{$total} 条",
                'info'
            );
            $this->writeProgress(
                'migration',
                $percent,
                "正在导入初始数据（{$completed}/{$total}）",
                [],
                false
            );
            $output[] = $table !== '' ? "INSERT INTO {$table}" : "SQL #{$completed}";
        }

        $this->writeProgress(
            'migration',
            self::UPGRADE_PROGRESS_END,
            "初始数据导入完成（{$completed}/{$total}）",
            [],
            false
        );
        $this->appendProgressLog('migration', '初始数据导入完成', 'success');

        return implode("\n", $output);
    }

    /**
     * 获取迁移版本目录下的初始数据 SQL 文件（database/migrations/vX.Y/upgrade.sql）。
     *
     * @return array 按路径排序的版本目录初始数据 SQL 文件列表。
     */
    private function findUpgradeSqlFiles(): array
    {
        $files = glob(root_path() . 'database/migrations/v*/upgrade.sql');
        if (!is_array($files)) {
            return [];
        }

        sort($files);

        return $files;
    }

    /**
     * 规范化初始数据 SQL：数据文件固定使用 ll_ 表前缀，需替换为安装配置的前缀。
     *
     * @param string $sql 需要分析或归一化的初始数据 SQL 脚本文本。
     * @param string $prefix 本次安装使用的数据表前缀。
     * @return string 将初始数据脚本中的固定表前缀替换为本次安装前缀后的 SQL。
     */
    private function normalizeUpgradeSql(string $sql, string $prefix): string
    {
        return str_replace('`ll_', '`' . $prefix, $sql);
    }

    /**
     * 将 SQL 脚本拆分为独立语句，兼容字符串、转义与注释中包含分号的情况。
     *
     * @param string $sql 需要分析或归一化的初始数据 SQL 脚本文本。
     * @return array 仅按代码状态分号切分的独立 SQL 语句列表，保留字符串与注释内容。
     */
    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $length = strlen($sql);
        $state = 'code';
        $escaped = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($state === 'line') {
                if ($char === "\n") {
                    $state = 'code';
                }
                $current .= $char;
                continue;
            }

            if ($state === 'block') {
                if ($char === '*' && $next === '/') {
                    $state = 'code';
                    $current .= '*/';
                    $i++;
                    continue;
                }
                $current .= $char;
                continue;
            }

            if ($state === 'code') {
                if ($char === '-' && $next === '-') {
                    $state = 'line';
                    $current .= '--';
                    $i++;
                    continue;
                }
                if ($char === '#') {
                    $state = 'line';
                    $current .= $char;
                    continue;
                }
                if ($char === '/' && $next === '*') {
                    $state = 'block';
                    $current .= '/*';
                    $i++;
                    continue;
                }
                if ($char === "'" || $char === '"' || $char === '`') {
                    $state = $char;
                    $current .= $char;
                    continue;
                }
                if ($char === ';') {
                    $statement = trim($current);
                    if ($statement !== '') {
                        $statements[] = $statement;
                    }
                    $current = '';
                    continue;
                }
                $current .= $char;
                continue;
            }

            if ($escaped) {
                $current .= $char;
                $escaped = false;
                continue;
            }
            if ($char === '\\') {
                $current .= $char;
                $escaped = true;
                continue;
            }
            if ($char === $state) {
                if ($next === $state) {
                    $current .= $state . $state;
                    $i++;
                    continue;
                }
                $state = 'code';
            }
            $current .= $char;
        }

        if (trim($current) !== '') {
            $statements[] = trim($current);
        }

        return $statements;
    }

    /**
     * 提取 INSERT 语句的目标表名。
     *
     * @param string $statement 需要提取目标表名的独立初始数据 SQL 语句。
     * @return string 插入语句的目标表名；未识别为带反引号表名的插入语句时为空字符串。
     */
    private function extractUpgradeTableName(string $statement): string
    {
        $statement = preg_replace(
            '/^(?:\s*(?:--[^\n]*(?:\n|$)|#[^\n]*(?:\n|$))|\s*\/\*.*?\*\/\s*)+/s',
            '',
            $statement
        );
        $statement = is_string($statement) ? trim($statement) : '';

        if (preg_match('/^INSERT\s+INTO\s+`([^`]+)`/i', $statement, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * 消费迁移命令输出，并写入安装进度日志。
     *
     * @param string $chunk 本次读取到的迁移进程标准输出或错误输出片段。
     * @param string $output 按引用累计的完整原始迁移输出。
     * @param string $lineBuffer 按引用累计的未结束行缓冲，完整行消费后仅保留末段。
     * @param int $total 用于迁移阶段进度计算的文件总计数，分母至少按一处理。
     * @param int $completed 按引用更新的已识别迁移启动行计数，用于阶段进度。
     * @return void 无返回值；按引用累计完整输出与未结束行缓冲，并更新已识别迁移计数。
     */
    private function consumeMigrationOutput(
        string $chunk,
        string &$output,
        string &$lineBuffer,
        int $total,
        int &$completed
    ): void {
        $output .= $chunk;
        $lineBuffer .= str_replace("\r\n", "\n", $chunk);

        while (($position = strpos($lineBuffer, "\n")) !== false) {
            $line = substr($lineBuffer, 0, $position);
            $lineBuffer = substr($lineBuffer, $position + 1);
            $this->recordMigrationOutputLine($line, $total, $completed);
        }
    }

    /**
     * 处理最后一段未以换行符结束的迁移输出。
     *
     * @param string $lineBuffer 按引用累计的未结束行缓冲，完整行消费后仅保留末段。
     * @param int $total 用于迁移阶段进度计算的文件总计数，分母至少按一处理。
     * @param int $completed 按引用更新的已识别迁移启动行计数，用于阶段进度。
     * @return void 无返回值；将末段缓冲作为日志处理后按引用清空。
     */
    private function flushMigrationOutput(string &$lineBuffer, int $total, int &$completed): void
    {
        if (trim($lineBuffer) === '') {
            return;
        }

        $this->recordMigrationOutputLine($lineBuffer, $total, $completed);
        $lineBuffer = '';
    }

    /**
     * 记录一条迁移输出。
     *
     * @param string $line 迁移控制台输出的一条完整日志文本。
     * @param int $total 用于迁移阶段进度计算的文件总计数，分母至少按一处理。
     * @param int $completed 按引用更新的已识别迁移启动行计数，用于阶段进度。
     * @return void 无返回值；记录规范化输出，并按迁移启动行更新引用计数与阶段进度。
     */
    private function recordMigrationOutputLine(string $line, int $total, int &$completed): void
    {
        $line = $this->normalizeProgressLogMessage($line);
        if ($line === '') {
            return;
        }

        $level = preg_match('/\b(error|exception|failed)\b/i', $line)
            ? 'error'
            : (preg_match('/:\s+migrated\b/i', $line) ? 'success' : 'info');
        $this->appendProgressLog('migration', $line, $level);

        if (!preg_match('/^==\s+\d+\s+.+:\s+migrating$/i', $line)) {
            return;
        }

        $completed = min($total, $completed + 1);
        $percent = self::MIGRATION_PROGRESS_START + (int)floor(
            ($completed / max(1, $total)) * (self::MIGRATION_PROGRESS_END - self::MIGRATION_PROGRESS_START)
        );
        $this->writeProgress(
            'migration',
            $percent,
            "正在执行数据库迁移（{$completed}/{$total}）",
            [],
            false
        );
    }

    /**
     * 获取本次安装需要执行的迁移文件数量（迁移文件按版本目录 database/migrations/vX.Y 组织）。
     *
     * @return int 至少为一的迁移文件计数，用于避免进度计算分母为零。
     */
    private function getMigrationFileCount(): int
    {
        $files = glob(root_path() . 'database/migrations/v*/*.php');

        return max(1, is_array($files) ? count($files) : 0);
    }

    /**
     * 构造迁移执行失败信息。
     *
     * @param string $output 需要提取末条有效日志的完整迁移控制台输出。
     * @return string 带最后一条有效控制台日志的迁移失败文案；无有效日志时使用默认原因。
     */
    private function buildMigrationFailureMessage(string $output): string
    {
        $lines = array_values(array_filter(array_map(
            fn (string $line): string => $this->normalizeProgressLogMessage($line),
            preg_split('/\r\n|\r|\n/', $output) ?: []
        )));
        $detail = $lines === [] ? '' : end($lines);

        return $detail === '' ? '数据库迁移执行失败' : '数据库迁移执行失败：' . $detail;
    }

    /**
     * 初始化管理员。
     *
     * @param PDO $pdo 连接指定目标数据库的安装阶段临时连接。
     * @param string $prefix 本次安装使用的数据表前缀。
     * @param array $admin 已校验的管理员账号及需要散列保存的明文密码。
     * @return void 无返回值；更新首条种子管理员或新建管理员，密码仅以散列形式入库。
     */
    private function initializeAdmin(PDO $pdo, string $prefix, array $admin): void
    {
        $table = $this->quoteIdentifier($prefix . 'admin');
        $password = password_hash($admin['password'], PASSWORD_BCRYPT, ['cost' => 10]);
        $now = date('Y-m-d H:i:s');

        $count = (int)$pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        if ($count > 0) {
            $statement = $pdo->prepare(
                "UPDATE {$table} SET `account` = ?, `password` = ?, `real_name` = ?, `status` = 1, `platform_type` = 1, `update_time` = ? ORDER BY `id` ASC LIMIT 1"
            );
            $statement->execute([$admin['account'], $password, '超级管理员', $now]);
            return;
        }

        $statement = $pdo->prepare(
            "INSERT INTO {$table} (`account`, `password`, `real_name`, `roles`, `status`, `platform_type`, `create_time`, `update_time`) VALUES (?, ?, ?, ?, 1, 1, ?, ?)"
        );
        $statement->execute([$admin['account'], $password, '超级管理员', json_encode([]), $now, $now]);
    }

    /**
     * 初始化站点基础配置。
     *
     * @param PDO $pdo 连接指定目标数据库的安装阶段临时连接。
     * @param string $prefix 本次安装使用的数据表前缀。
     * @param string $appUrl 归一化后的站点地址，用于环境配置、后台入口和默认通知地址。
     * @return void 无返回值；写入当前网站域名，并仅在名称为空时补默认站点名称。
     */
    private function initializeSiteConfig(PDO $pdo, string $prefix, string $appUrl): void
    {
        $this->upsertConfig($pdo, $prefix, ConfigKeyEnum::WEB_HOST, $appUrl, '网站根域名', true);
        $this->upsertConfig($pdo, $prefix, 'site_name', '乖乖兔多商户系统', '网站名称');
    }

    /**
     * 写入或更新配置表。
     *
     * @param PDO $pdo 连接指定目标数据库的安装阶段临时连接。
     * @param string $prefix 本次安装使用的数据表前缀。
     * @param string $key 需要写入站点配置表的配置键名。
     * @param string $value 需要持久化并进行 JSON 编码的配置值。
     * @param string $title 配置记录的中文标题及说明。
     * @param bool $overwrite 已有非空配置是否允许被本次初始化覆盖。
     * @return void 无返回值；新增配置，或按覆盖策略更新已有配置值。
     */
    private function upsertConfig(
        PDO $pdo,
        string $prefix,
        string $key,
        string $value,
        string $title,
        bool $overwrite = false
    ): void {
        $table = $this->quoteIdentifier($prefix . 'config');
        $statement = $pdo->prepare("SELECT `id`, `value` FROM {$table} WHERE `key_name` = ? LIMIT 1");
        $statement->execute([$key]);
        $row = $statement->fetch();
        $encodedValue = json_encode($value, JSON_UNESCAPED_UNICODE);
        $now = date('Y-m-d H:i:s');
        if ($row) {
            $current = json_decode((string)$row['value'], true);
            $currentValue = is_scalar($current) ? trim((string)$current) : '';
            if (!$overwrite && $currentValue !== '') {
                return;
            }
            $update = $pdo->prepare("UPDATE {$table} SET `value` = ?, `update_time` = ? WHERE `id` = ?");
            $update->execute([$encodedValue, $now, (int)$row['id']]);
            return;
        }

        $insert = $pdo->prepare(
            "INSERT INTO {$table} (`key_name`, `value`, `title`, `into`, `cate_id`, `sort`, `status`, `create_time`, `update_time`) VALUES (?, ?, ?, ?, 0, 0, 0, ?, ?)"
        );
        $insert->execute([$key, $encodedValue, $title, $title, $now, $now]);
    }

    /**
     * 写入安装锁。
     *
     * @param array $data 需要持久化的安装完成摘要。
     * @return void 无返回值；将完成时间、后台入口及安装环境摘要持久化到安装标记。
     */
    private function writeInstallLock(array $data): void
    {
        $path = $this->installLockPath();
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('无法创建安装目录');
        }

        if (@file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) === false) {
            throw new RuntimeException('写入安装锁失败');
        }
    }

    /**
     * 读取安装锁。
     *
     * @return array 已解析的安装完成资料，文件缺失或内容无效时为空数组。
     */
    private function readInstallLock(): array
    {
        $path = $this->installLockPath();
        if (!is_file($path)) {
            return [];
        }

        $data = json_decode((string)file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    /**
     * 是否已安装。
     *
     * @return bool 安装完成标记文件是否存在。
     */
    private function isInstalled(): bool
    {
        return is_file($this->installLockPath());
    }

    /**
     * 判断是否已有运行中的安装任务。
     *
     * @return bool 当前进度阶段是否表示安装仍在启动或运行。
     */
    private function isInstallTaskRunning(): bool
    {
        $step = (string)($this->readProgress()['step'] ?? 'waiting');

        return !in_array($step, ['waiting', 'finished', 'failed'], true);
    }

    /**
     * 写入后台安装任务参数。
     *
     * @param array $payload 包含连接及管理员凭据的原始后台安装参数。
     * @param string $domain 本次安装访问的站点域名，允许为空并使用默认本地主机地址。
     * @return void 无返回值；将后台任务参数写入文件并限制文件权限。
     */
    private function writeInstallTask(array $payload, string $domain): void
    {
        $content = json_encode([
            'payload' => $payload,
            'domain' => $domain,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (!is_string($content) || @file_put_contents($this->installTaskPath(), $content, LOCK_EX) === false) {
            throw new RuntimeException('写入安装任务参数失败');
        }

        @chmod($this->installTaskPath(), 0600);
    }

    /**
     * 读取后台安装任务参数。
     *
     * @return array 包含原始安装参数和站点域名的后台任务，格式无效时抛出异常。
     */
    private function readInstallTask(): array
    {
        $content = @file_get_contents($this->installTaskPath());
        $task = is_string($content) ? json_decode($content, true) : null;
        if (!is_array($task) || !is_array($task['payload'] ?? null)) {
            throw new RuntimeException('安装任务参数不存在或格式不正确');
        }

        return [
            'payload' => $task['payload'],
            'domain' => is_string($task['domain'] ?? null) ? $task['domain'] : '',
        ];
    }

    /**
     * 清空后台安装任务参数，避免凭据在任务完成后继续保留。
     *
     * @return void 无返回值；清空存在的任务参数文件以缩短安装凭据保留时间。
     */
    private function clearInstallTask(): void
    {
        $path = $this->installTaskPath();
        if (is_file($path)) {
            @file_put_contents($path, '', LOCK_EX);
        }
    }

    /**
     * 启动后台 CLI 安装进程。
     *
     * @return int 成功启动的后台安装进程编号，启动不可用或失败时抛出异常。
     */
    private function startBackgroundInstallProcess(): int
    {
        if (!function_exists('proc_open')) {
            throw new RuntimeException('当前 PHP 环境不支持启动后台安装进程');
        }

        $logPath = runtime_path() . self::PROCESS_LOG_FILE;
        @file_put_contents($logPath, '', LOCK_EX);
        @chmod($logPath, 0600);

        $command = escapeshellarg($this->phpCliBinary())
            . ' '
            . escapeshellarg(root_path() . 'think')
            . ' install:run'
            . ' > ' . escapeshellarg($logPath)
            . ' 2>&1 & echo $!';
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, root_path());
        if (!is_resource($process)) {
            throw new RuntimeException('无法启动后台安装进程');
        }

        fclose($pipes[0]);
        $output = (string)stream_get_contents($pipes[1]);
        $error = (string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        if ($exitCode !== 0 || !preg_match('/^\s*(\d+)\s*$/', $output, $matches)) {
            throw new RuntimeException(trim($error) !== '' ? trim($error) : '后台安装进程启动失败');
        }

        return (int)$matches[1];
    }

    /**
     * 获取可用于执行 Think 命令的 PHP CLI。
     *
     * @return string 可执行 Think 命令的 PHP 路径，当前为进程管理器时优先选择同目录命令行版本。
     */
    private function phpCliBinary(): string
    {
        $phpBinary = PHP_BINARY;
        $cliBinary = PHP_BINDIR . DIRECTORY_SEPARATOR . 'php';
        if (
            stripos(basename($phpBinary), 'php-fpm') !== false
            && is_file($cliBinary)
            && is_executable($cliBinary)
        ) {
            return $cliBinary;
        }

        return $phpBinary;
    }

    /**
     * 写安装进度。
     *
     * @param string $step 当前安装阶段编码，用于进度状态及默认日志级别。
     * @param int $percent 当前阶段整体进度百分比，保存时限制在零到一百。
     * @param string $message 当前安装阶段说明或需要追加的控制台日志。
     * @param array $data 当前阶段输出的结果资料，保存前递归移除敏感字段。
     * @param bool $appendLog 是否同时向现有进度日志追加当前阶段说明。
     * @return void 无返回值；保存归一化百分比、脱敏资料和可选追加日志后的安装进度。
     */
    private function writeProgress(
        string $step,
        int $percent,
        string $message,
        array $data = [],
        bool $appendLog = true
    ): void
    {
        $current = $this->readProgress();
        $logs = is_array($current['logs'] ?? null) ? $current['logs'] : [];
        if ($appendLog) {
            $this->appendProgressLogItem($logs, $step, $message, $this->getProgressLogLevel($step));
        }

        $this->saveProgress([
            'step' => $step,
            'status' => $this->getProgressStatus($step),
            'percent' => max(0, min(100, $percent)),
            'message' => $message,
            'data' => $this->sanitizeProgressData($data),
            'logs' => $logs,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 重置本次安装的进度与日志。
     *
     * @return void 无返回值；用默认等待状态覆盖本次安装进度和日志。
     */
    private function resetProgress(): void
    {
        $this->saveProgress($this->defaultProgress());
    }

    /**
     * 追加一条安装进度日志。
     *
     * @param string $step 当前安装阶段编码，用于进度状态及默认日志级别。
     * @param string $message 当前安装阶段说明或需要追加的控制台日志。
     * @param string $level 日志级别，支持错误、警告、成功或普通信息。
     * @return void 无返回值；向当前进度追加规范化日志并更新保存时间。
     */
    private function appendProgressLog(string $step, string $message, string $level = 'info'): void
    {
        $progress = $this->readProgress();
        $logs = is_array($progress['logs'] ?? null) ? $progress['logs'] : [];
        $this->appendProgressLogItem($logs, $step, $message, $level);

        $progress['logs'] = $logs;
        $progress['updated_at'] = date('Y-m-d H:i:s');
        $this->saveProgress($progress);
    }

    /**
     * 追加日志项并控制日志数量。
     *
     * @param array $logs 按引用追加并裁剪数量的安装进度日志。
     * @param string $step 当前安装阶段编码，用于进度状态及默认日志级别。
     * @param string $message 当前安装阶段说明或需要追加的控制台日志。
     * @param string $level 日志级别，支持错误、警告、成功或普通信息。
     * @return void 无返回值；按引用追加非重复日志，并仅保留最近限定数量的记录。
     */
    private function appendProgressLogItem(array &$logs, string $step, string $message, string $level = 'info'): void
    {
        $message = $this->normalizeProgressLogMessage($message);
        if ($message === '') {
            return;
        }

        $last = $logs === [] ? [] : $logs[count($logs) - 1];
        if (
            is_array($last)
            && (string)($last['step'] ?? '') === $step
            && (string)($last['message'] ?? '') === $message
        ) {
            return;
        }

        $logs[] = [
            'time' => date('H:i:s'),
            'step' => $step,
            'level' => in_array($level, ['error', 'warning', 'success'], true) ? $level : 'info',
            'message' => $message,
        ];

        if (count($logs) > self::PROGRESS_LOG_LIMIT) {
            $logs = array_slice($logs, -self::PROGRESS_LOG_LIMIT);
        }
    }

    /**
     * 将控制台输出转换为适合页面展示的单行日志。
     *
     * @param string $message 当前安装阶段说明或需要追加的控制台日志。
     * @return string 已去除 HTML 并合并空白的单行日志文本，无效转换时为空字符串。
     */
    private function normalizeProgressLogMessage(string $message): string
    {
        $message = strip_tags($message);
        $message = preg_replace('/\s+/', ' ', trim($message));

        return is_string($message) ? $message : '';
    }

    /**
     * 移除进度结果中可能被旧版本或扩展写入的敏感字段。
     *
     * @param array $data 需要递归移除已知敏感字段的安装阶段资料。
     * @return array 递归移除已知密码、密钥和令牌字段后的进度结果资料。
     */
    private function sanitizeProgressData(array $data): array
    {
        $sensitiveKeys = [
            'password',
            'admin_password',
            'adminpassword',
            'secret',
            'api_key',
            'access_key',
            'secret_key',
            'private_key',
            'token',
        ];

        foreach ($data as $key => $value) {
            if (in_array(strtolower((string)$key), $sensitiveKeys, true)) {
                unset($data[$key]);
                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->sanitizeProgressData($value);
            }
        }

        return $data;
    }

    /**
     * 保存安装进度文件。
     *
     * @param array $progress 包含阶段、状态、百分比、脱敏资料和日志的完整进度数据。
     * @return void 无返回值；将完整安装进度以独占写锁保存为 JSON 文件。
     */
    private function saveProgress(array $progress): void
    {
        $path = runtime_path() . self::PROGRESS_FILE;
        @file_put_contents(
            $path,
            json_encode($progress, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    /**
     * 默认安装进度。
     *
     * @return array 等待安装阶段的零百分比、空资料、空日志和空更新时间。
     */
    private function defaultProgress(): array
    {
        return [
            'step' => 'waiting',
            'status' => 'waiting',
            'percent' => 0,
            'message' => '等待安装',
            'data' => [],
            'logs' => [],
            'updated_at' => '',
        ];
    }

    /**
     * 获取进度状态。
     *
     * @param string $step 当前安装阶段编码，用于进度状态及默认日志级别。
     * @return string 与阶段对应的等待、运行、成功或失败状态编码。
     */
    private function getProgressStatus(string $step): string
    {
        if ($step === 'finished') {
            return 'success';
        }
        if ($step === 'failed') {
            return 'failed';
        }
        if ($step === 'waiting') {
            return 'waiting';
        }

        return 'running';
    }

    /**
     * 获取进度日志级别。
     *
     * @param string $step 当前安装阶段编码，用于进度状态及默认日志级别。
     * @return string 与结束或失败阶段对应的成功、错误或普通信息日志级别。
     */
    private function getProgressLogLevel(string $step): string
    {
        if ($step === 'finished') {
            return 'success';
        }
        if ($step === 'failed') {
            return 'error';
        }

        return 'info';
    }

    /**
     * 后台安装任务参数文件路径。
     *
     * @return string 运行目录中后台安装任务参数文件的绝对路径。
     */
    private function installTaskPath(): string
    {
        return runtime_path() . self::TASK_FILE;
    }

    /**
     * 安装锁路径。
     *
     * @return string 公开安装目录中安装完成标记文件的绝对路径。
     */
    private function installLockPath(): string
    {
        return public_path() . self::INSTALL_LOCK;
    }

    /**
     * 构造失败结果。
     *
     * @param string $message 本次安装或环境检测失败的原因。
     * @param array $data 失败时需要返回的分项连接或其他诊断资料。
     * @return array 标记失败的统一响应，包含原因及调用方提供的分项资料。
     */
    private function failedResult(string $message, array $data = []): array
    {
        return [
            'passed' => false,
            'message' => $message,
            'data' => $data,
        ];
    }

    /**
     * 必要扩展列表。
     *
     * @return array 必要 PHP 扩展名与检查页面中文标签的映射。
     */
    private function requiredExtensions(): array
    {
        return [
            'pdo_mysql' => 'PDO MySQL 扩展',
            'redis' => 'Redis 扩展',
            'swoole' => 'Swoole 扩展',
            'curl' => 'cURL 扩展',
            'bcmath' => 'BCMath 扩展',
            'ctype' => 'Ctype 扩展',
            'zip' => 'Zip 扩展',
            'gd' => 'GD 扩展',
            'mbstring' => 'Mbstring 扩展',
            'openssl' => 'OpenSSL 扩展',
            'fileinfo' => 'Fileinfo 扩展',
            'json' => 'JSON 扩展',
        ];
    }

    /**
     * 可写路径列表。
     *
     * @return array 安装所需环境文件和运行、存储、安装目录的检查定义。
     */
    private function writablePaths(): array
    {
        return [
            'env' => ['label' => '.env 文件', 'path' => root_path() . '.env', 'file' => true],
            'runtime' => ['label' => 'runtime 目录', 'path' => runtime_path(), 'file' => false],
            'storage' => ['label' => 'public/storage 目录', 'path' => public_path() . 'storage', 'file' => false],
            'install' => ['label' => 'public/install 目录', 'path' => public_path() . 'install', 'file' => false],
        ];
    }

    /**
     * 构造检测项。
     *
     * @param string $key 当前环境检测项的稳定标识。
     * @param string $label 安装检测页面显示的项目中文名称。
     * @param string $current 当前环境检测得到的版本、权限或容量文本。
     * @param string $require 此检测项目必须满足或建议满足的条件文本。
     * @param bool $passed 当前环境值是否满足该项目要求。
     * @param bool $required 此检测项是否属于阻止安装的必要项。
     * @return array 包含当前值、要求、必需标志、通过或警告状态及文案的单项检测数据。
     */
    private function buildCheckItem(string $key, string $label, string $current, string $require, bool $passed, bool $required): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'current' => $current,
            'require' => $require,
            'required' => $required,
            'status' => $passed ? 'passed' : ($required ? 'failed' : 'warning'),
            'message' => $passed ? '通过' : ($required ? '未通过' : '建议优化'),
        ];
    }

    /**
     * 必要项是否全部通过。
     *
     * @param array $items 已经组装的环境检测结果列表。
     * @return bool 所有标记为必需的检测项是否均已通过，建议项失败不影响结果。
     */
    private function allRequiredPassed(array $items): bool
    {
        foreach ($items as $item) {
            if (($item['required'] ?? false) && ($item['status'] ?? '') !== 'passed') {
                return false;
            }
        }

        return true;
    }

    /**
     * 判断路径是否可写。
     *
     * @param string $path 需要检测写权限的安装文件或目录路径。
     * @param bool $isFile 目标是否为文件，目标不存在时用于计算可创建的父目录。
     * @return bool 目标已存在时是否可写，否则是否可在现有可写父目录中创建。
     */
    private function isPathWritable(string $path, bool $isFile): bool
    {
        if (file_exists($path)) {
            return is_writable($path);
        }

        $parent = $isFile ? dirname($path) : dirname(rtrim($path, DIRECTORY_SEPARATOR));
        return is_dir($parent) && is_writable($parent);
    }

    /**
     * 读取 .env 中的已存在值。
     *
     * @return array 按大写节名与字段名组合建立的已有环境配置映射，环境文件不存在时为空数组。
     */
    private function readEnvironmentValues(): array
    {
        $path = root_path() . '.env';
        if (!is_file($path)) {
            return [];
        }

        $values = [];
        $section = '';
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (preg_match('/^\[([A-Za-z0-9_]+)]$/', $line, $matches)) {
                $section = strtoupper($matches[1]);
                continue;
            }
            if (!preg_match('/^([A-Za-z0-9_]+)\s*=\s*(.*)$/', $line, $matches)) {
                continue;
            }
            $key = strtoupper($matches[1]);
            $value = trim($matches[2], " \t\n\r\0\x0B\"'");
            $values[$section !== '' ? $section . '_' . $key : $key] = $value;
        }

        return $values;
    }

    /**
     * 生成应用密钥。
     *
     * @return string 与已有应用密钥不同且使用当前加密算法的新编码密钥。
     */
    private function generateAppKey(): string
    {
        $existing = $this->readEnvironmentValues()['APP_KEY'] ?? '';
        do {
            $appKey = 'base64:' . base64_encode(Encrypter::generateKey(Config::get('app.cipher')));
        } while ($appKey === $existing);

        return $appKey;
    }

    /**
     * 生成运行时隔离名称。
     *
     * @param string $prefix 缓存或队列隔离名称的可识别前缀。
     * @param array $excluded 必须避开的已有缓存、标签或队列隔离名称列表。
     * @return string 使用指定前缀且不在排除列表中的随机隔离名称。
     */
    private function generateRuntimeName(string $prefix, array $excluded = []): string
    {
        do {
            $name = $prefix . '_' . bin2hex(random_bytes(8));
        } while (in_array($name, $excluded, true));

        return $name;
    }

    /**
     * 标识符加反引号。
     *
     * @param string $value 需要安全包裹为 SQL 标识符的库名或表名。
     * @return string 已转义内部反引号并包裹反引号的 SQL 标识符。
     */
    private function quoteIdentifier(string $value): string
    {
        return '`' . str_replace('`', '``', $value) . '`';
    }

    /**
     * 规范化服务版本号。
     *
     * @param string $version MySQL 或 Redis 返回的原始服务版本文本。
     * @return string 提取出的服务版本数字；无法识别时保留原始版本文本。
     */
    private function normalizeServerVersion(string $version): string
    {
        if (preg_match('/\d+(?:\.\d+){1,2}/', $version, $matches)) {
            return $matches[0];
        }

        return $version;
    }

    /**
     * 格式化字节数。
     *
     * @param int $bytes 需要展示的磁盘或内存容量字节数。
     * @return string 以吉字节为单位并保留两位小数的容量文案，非正值显示未知。
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '未知';
        }

        return round($bytes / 1024 / 1024 / 1024, 2) . ' GB';
    }

    /**
     * PHP 内存限制转换为字节。
     *
     * @param string $value PHP 内存限制配置文本，可包含容量单位或无限制标记。
     * @return int 内存限制对应的字节数，无限制配置返回平台最大整数。
     */
    private function memoryLimitToBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '-1') {
            return PHP_INT_MAX;
        }
        $unit = strtolower(substr($value, -1));
        $number = (int)$value;
        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /**
     * 规范化当前访问域名。
     *
     * @param string $domain 本次安装访问的站点域名，允许为空并使用默认本地主机地址。
     * @return string 去除首尾空白及末尾斜线的站点地址，未提供时使用本地主机地址。
     */
    private function normalizeDomain(string $domain): string
    {
        $domain = trim($domain);
        if ($domain === '') {
            return 'http://localhost';
        }

        return rtrim($domain, '/');
    }

    /**
     * 后台地址。
     *
     * @param string $domain 本次安装访问的站点域名，允许为空并使用默认本地主机地址。
     * @return string 当前站点域名下管理后台入口的绝对地址。
     */
    private function buildAdminUrl(string $domain): string
    {
        return rtrim($this->normalizeDomain($domain), '/') . '/admin';
    }

    /**
     * WebSocket 默认地址。
     *
     * @param string $domain 本次安装访问的站点域名，允许为空并使用默认本地主机地址。
     * @return string 按站点安全协议选择普通或安全连接协议的默认实时通信地址。
     */
    private function buildWebsocketUrl(string $domain): string
    {
        $domain = $this->normalizeDomain($domain);
        if (str_starts_with($domain, 'https://')) {
            return 'wss://' . substr($domain, 8);
        }
        if (str_starts_with($domain, 'http://')) {
            return 'ws://' . substr($domain, 7);
        }

        return 'ws://' . $domain;
    }
}
