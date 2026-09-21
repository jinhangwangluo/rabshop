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

use app\common\exception\PluginSecurityException;
use app\common\service\plugin\contract\PluginKeyCipherInterface;
use Rabbit\util\Encrypter;
use Throwable;

/**
 * 使用应用密钥封装插件私钥的加密器。
 */
final class AppPluginKeyCipher implements PluginKeyCipherInterface
{
    public function __construct(private readonly ?Encrypter $encrypter = null)
    {
    }

    public function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            throw new PluginSecurityException('私钥不能为空', 0);
        }
        $encrypter = $this->resolve();
        try {
            $ciphertext = $encrypter->encryptString($plaintext);
        } catch (Throwable $exception) {
            throw new PluginSecurityException('私钥加密失败', 0, $exception);
        }
        if (!is_string($ciphertext) || $ciphertext === '') {
            throw new PluginSecurityException('私钥加密失败');
        }
        return $ciphertext;
    }

    public function decrypt(string $ciphertext): string
    {
        if ($ciphertext === '') {
            throw new PluginSecurityException('私钥密文为空');
        }
        $encrypter = $this->resolve();
        try {
            $plaintext = $encrypter->decryptString($ciphertext);
        } catch (Throwable $exception) {
            throw new PluginSecurityException('私钥解密失败', 0, $exception);
        }
        if (!is_string($plaintext) || $plaintext === '') {
            throw new PluginSecurityException('私钥解密失败');
        }
        return $plaintext;
    }

    private function resolve(): Encrypter
    {
        if ($this->encrypter !== null) {
            return $this->encrypter;
        }
        if (!function_exists('app')) {
            throw new PluginSecurityException('应用加密器不可用');
        }
        try {
            $encrypter = app()->make(Encrypter::class);
        } catch (Throwable $exception) {
            throw new PluginSecurityException('应用加密器不可用', 0, $exception);
        }
        if (!$encrypter instanceof Encrypter) {
            throw new PluginSecurityException('应用加密器不可用');
        }
        return $encrypter;
    }
}
