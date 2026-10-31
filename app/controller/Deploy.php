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

namespace app\controller;

use app\BaseController;
use think\facade\Log;
use think\Response;

/**
 * 部署钩子控制器。
 *
 * 通过独立部署密码保护 Git 拉取入口，并在拉取前自动 checkout 本地改动。
 */
class Deploy extends BaseController
{
    private const LOCK_FILE = 'deploy_git_pull.lock';
    private const TIMEOUT_SECONDS = 120;

    /**
     * 自动拉取代码。
     */
    public function gitPull(): Response
    {
        $password = $this->getEnvValue('deploy.password', 'DEPLOY_PASSWORD');
        if ($password === '') {
            Log::warning('[deploy] git pull rejected: DEPLOY_PASSWORD missing');
            return $this->fail('未配置部署密码');
        }

        $inputPassword = (string)($this->request->header('X-Deploy-Password') ?: $this->request->param('password', ''));
        if ($inputPassword === '' || !hash_equals($password, $inputPassword)) {
            Log::warning('[deploy] git pull rejected: invalid password');
            return $this->fail('密码错误');
        }

        $lockPath = runtime_path() . self::LOCK_FILE;
        $lock = fopen($lockPath, 'c');
        if (!$lock) {
            return $this->fail('无法创建部署锁');
        }

        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            return $this->fail('已有拉取任务正在执行');
        }

        try {
            $result = $this->runDeploy(root_path());
            if ((int)$result['exit_code'] === 0) {
                return $this->success('拉取完成', $result);
            }

            return $this->fail('拉取失败', $result);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * 执行部署流程：checkout 非环境文件改动，再拉取远端代码。
     */
    private function runDeploy(string $path): array
    {
        $safePath = rtrim($path, DIRECTORY_SEPARATOR);
        $start = time();

        $checkoutResult = $this->checkoutLocalChanges($safePath);
        if ((int)$checkoutResult['exit_code'] !== 0) {
            $result = [
                'exit_code' => (int)$checkoutResult['exit_code'],
                'output' => trim((string)$checkoutResult['output']),
                'duration' => time() - $start,
                'checkout' => $this->checkoutSummary($checkoutResult),
            ];
            Log::error('[deploy] checkout failed', $result);
            return $result;
        }

        $pullResult = $this->runGitCommand($safePath, 'pull --ff-only');
        $statusResult = $this->runGitCommand($safePath, 'status --short');

        $result = [
            'exit_code' => (int)$pullResult['exit_code'],
            'output' => trim((string)$checkoutResult['output'] . "\n" . (string)$pullResult['output']),
            'duration' => time() - $start,
            'checkout' => $this->checkoutSummary($checkoutResult),
            'status' => trim((string)$statusResult['output']),
        ];

        Log::info('[deploy] git pull result', $result);
        return $result;
    }

    /**
     * 自动 checkout 可从 HEAD 恢复的本地改动，但保护 .env 与 .env.*。
     */
    private function checkoutLocalChanges(string $path): array
    {
        $diffResult = $this->runGitCommand($path, 'diff --name-only -z --no-renames --diff-filter=DMRTUXB HEAD --');
        if ((int)$diffResult['exit_code'] !== 0) {
            return [
                'exit_code' => (int)$diffResult['exit_code'],
                'output' => $diffResult['output'],
                'checked_out' => [],
                'protected' => [],
            ];
        }

        $changedFiles = $this->parseNullSeparatedList((string)$diffResult['output']);
        $checkoutFiles = [];
        $protectedFiles = [];

        foreach ($changedFiles as $file) {
            if ($this->isProtectedCheckoutFile($file)) {
                $protectedFiles[] = $file;
                continue;
            }

            $checkoutFiles[] = $file;
        }

        $outputs = [];
        foreach (array_chunk($checkoutFiles, 20) as $chunk) {
            $arguments = implode(' ', array_map('escapeshellarg', $chunk));
            $checkoutResult = $this->runGitCommand($path, 'checkout HEAD -- ' . $arguments);
            if ((int)$checkoutResult['exit_code'] !== 0) {
                return [
                    'exit_code' => (int)$checkoutResult['exit_code'],
                    'output' => $checkoutResult['output'],
                    'checked_out' => $checkoutFiles,
                    'protected' => $protectedFiles,
                ];
            }

            if (trim((string)$checkoutResult['output']) !== '') {
                $outputs[] = trim((string)$checkoutResult['output']);
            }
        }

        $summary = [];
        $summary[] = count($checkoutFiles) > 0
            ? '已 checkout ' . count($checkoutFiles) . ' 个本地改动文件'
            : '没有需要 checkout 的本地改动文件';

        if (count($protectedFiles) > 0) {
            $summary[] = '已保护环境文件：' . implode('、', $protectedFiles);
        }

        return [
            'exit_code' => 0,
            'output' => implode("\n", array_filter(array_merge($summary, $outputs))),
            'checked_out' => $checkoutFiles,
            'protected' => $protectedFiles,
        ];
    }

    /**
     * 执行 Git 命令。
     */
    private function runGitCommand(string $path, string $arguments): array
    {
        $command = 'git -c safe.directory=' . escapeshellarg($path)
            . ' -C ' . escapeshellarg($path)
            . ' ' . $arguments . ' 2>&1';

        return $this->runCommand($command, $this->gitEnvironment());
    }

    /**
     * 执行 shell 命令并带超时控制。
     */
    private function runCommand(string $command, array $environment): array
    {
        $descriptorSpec = [
            1 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, null, $environment);
        if (!is_resource($process)) {
            return [
                'exit_code' => 1,
                'output' => '无法启动命令',
                'duration' => 0,
            ];
        }

        stream_set_blocking($pipes[1], false);
        $start = time();
        $output = '';
        $timedOut = false;
        $exitCode = null;

        while (true) {
            $output .= stream_get_contents($pipes[1]);
            $status = proc_get_status($process);

            if (!$status['running']) {
                $exitCode = $status['exitcode'];
                break;
            }

            if (time() - $start >= self::TIMEOUT_SECONDS) {
                $timedOut = true;
                proc_terminate($process);
                break;
            }

            usleep(100000);
        }

        $output .= stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $closeCode = proc_close($process);
        if ($exitCode === null || $exitCode < 0) {
            $exitCode = $closeCode;
        }

        if ($timedOut) {
            $exitCode = 124;
            $output .= "\n执行超时，已终止命令";
        }

        return [
            'exit_code' => (int)$exitCode,
            'output' => $output,
            'duration' => time() - $start,
        ];
    }

    /**
     * Git 执行环境，支持 HTTP 账号密码拉取。
     */
    private function gitEnvironment(): array
    {
        $env = [
            'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
            'HOME' => getenv('HOME') ?: root_path(),
            'GIT_TERMINAL_PROMPT' => '0',
        ];

        $username = $this->getEnvValue('deploy.git_username', 'DEPLOY_GIT_USERNAME');
        $password = $this->getEnvValue('deploy.git_password', 'DEPLOY_GIT_PASSWORD');

        if ($username !== '' && $password !== '') {
            $askPass = $this->prepareGitAskPass();
            if ($askPass !== '') {
                $env['GIT_ASKPASS'] = $askPass;
                $env['DEPLOY_GIT_USERNAME'] = $username;
                $env['DEPLOY_GIT_PASSWORD'] = $password;
            }
        }

        return $env;
    }

    /**
     * 生成 Git askpass 脚本。
     */
    private function prepareGitAskPass(): string
    {
        $path = runtime_path() . 'deploy_git_askpass.sh';
        $script = <<<'SH'
#!/bin/sh
case "$1" in
    *Username*) printf '%s\n' "$DEPLOY_GIT_USERNAME" ;;
    *Password*) printf '%s\n' "$DEPLOY_GIT_PASSWORD" ;;
    *) printf '\n' ;;
esac
SH;

        if (@file_put_contents($path, $script) === false) {
            Log::error('[deploy] git askpass write failed: ' . $path);
            return '';
        }

        @chmod($path, 0700);
        return $path;
    }

    /**
     * 兼容 DEPLOY_PASSWORD 与 deploy.password 两种环境变量写法。
     */
    private function getEnvValue(string $dotKey, string $upperKey): string
    {
        $value = env($dotKey, '');
        if ($value === '' || $value === null) {
            $value = env($upperKey, '');
        }

        return trim((string)$value);
    }

    /**
     * 解析 Git -z 输出。
     */
    private function parseNullSeparatedList(string $output): array
    {
        $items = explode("\0", $output);
        $items = array_filter($items, static function (string $item): bool {
            return $item !== '';
        });

        return array_values($items);
    }

    /**
     * 环境文件不允许被自动 checkout 回退。
     */
    private function isProtectedCheckoutFile(string $file): bool
    {
        $file = str_replace('\\', '/', $file);
        if (str_starts_with($file, './')) {
            $file = substr($file, 2);
        }

        return $file === '.env' || str_starts_with($file, '.env.');
    }

    /**
     * 生成 checkout 结果摘要，避免泄露命令细节。
     */
    private function checkoutSummary(array $checkoutResult): array
    {
        return [
            'checked_out' => $checkoutResult['checked_out'] ?? [],
            'protected' => $checkoutResult['protected'] ?? [],
        ];
    }
}
