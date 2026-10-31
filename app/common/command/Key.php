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

declare (strict_types=1);

namespace app\common\command;

use Rabbit\util\Encrypter;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

/**
 * Class Key
 * @package app\common\command
 */
class Key extends Command
{

    protected function configure()
    {
        $this->setName('key')
            ->addOption('show', null, Option::VALUE_OPTIONAL, '为应用生成密码', true)
            ->addOption('force', null, Option::VALUE_OPTIONAL, '生成KEY')
            ->setDescription('生成KEY');
    }

    /**
     * 执行命令。
     *
     * @param Input $input 输入。
     * @param Output $output 输出。
     * @return int 成功返回 0，未写入环境文件返回 1
     */
    protected function execute(Input $input, Output $output)
    {
        $key = $this->generateRandomKey();

        if ($input->hasOption('show') && $input->getOption('show')) {
            $this->output->writeln('<comment>' . $key . '</comment>');
            return 0;
        }

        if (!$this->setKeyInEnvironmentFile($key)) {
            // 未真正写入时报错退出，避免调用方把失败当成成功
            return 1;
        }

        $this->app->config->set(['app.key' => $key]);

        $this->output->info('Application key set successfully.');

        return 0;
    }

    /**
     * Generate a random key for the application.
     *
     * @return string
     */
    protected function generateRandomKey()
    {
        return 'base64:' . base64_encode(
                Encrypter::generateKey($this->app->config->get('app.cipher'))
            );
    }

    /**
     * 将应用密钥写入环境文件。
     *
     * @param string $key 应用密钥
     * @return bool 用户取消或写入失败时返回 false
     */
    protected function setKeyInEnvironmentFile($key)
    {
        $currentKey = $this->app->config->get('app.key');

        if (!is_null($currentKey) && strlen($currentKey) !== 0 && (!$this->output->confirm($this->input, '在生产中的应用！是否重置?', false))) {
            $this->output->warning('已取消，APP_KEY 未被修改。');
            return false;
        }

        return $this->writeNewEnvironmentFileWith($key);
    }

    /**
     * @return string
     */
    protected function environmentFilePath()
    {
        return root_path() . '.env';
    }

    /**
     * 用新密钥替换环境文件中的 APP_KEY 行。
     *
     * @param string $key 应用密钥
     * @return bool 写入成功返回 true
     */
    protected function writeNewEnvironmentFileWith($key)
    {
        $envFile = $this->environmentFilePath();
        $content = file_get_contents($envFile);

        if (false === $content) {
            $this->output->error('读取环境文件失败：' . $envFile);
            return false;
        }

        if (0 === preg_match($this->keyReplacementPattern(), $content)) {
            $this->output->error('环境文件中未找到 APP_KEY 配置行，请先补充 “APP_KEY = ” 后重试');
            return false;
        }

        // 提前拦截权限问题，避免 file_put_contents 告警被转成异常后输出堆栈
        if (!is_writable($envFile)) {
            $this->output->error('环境文件不可写，请检查文件权限：' . $envFile);
            return false;
        }

        $newContent = preg_replace($this->keyReplacementPattern(), 'APP_KEY = ' . $key, $content);

        if (false === file_put_contents($envFile, $newContent)) {
            $this->output->error('环境文件写入失败，请检查文件权限：' . $envFile);
            return false;
        }

        return true;
    }

    /**
     * 匹配环境文件中的 APP_KEY 配置行。
     *
     * 整行匹配，兼容空值、已有值以及等号两侧空格差异，避免旧值对不上时替换静默失效。
     *
     * @return string
     */
    protected function keyReplacementPattern()
    {
        return '/^APP_KEY[ \t]*=[^\r\n]*/m';
    }
}
