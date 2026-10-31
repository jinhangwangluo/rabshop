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

use app\common\service\plugin\contract\PluginKeyCipherInterface;
use InvalidArgumentException;

/**
 * 测试用密钥加密回调。
 */
final class CallbackPluginKeyCipher implements PluginKeyCipherInterface
{
    public function __construct(
        private readonly mixed $encryptCallback,
        private readonly mixed $decryptCallback
    ) {
        if (!is_callable($encryptCallback) || !is_callable($decryptCallback)) {
            throw new InvalidArgumentException('密钥回调必须可调用');
        }
    }

    public function encrypt(string $plaintext): string
    {
        return (string)($this->encryptCallback)($plaintext);
    }

    public function decrypt(string $ciphertext): string
    {
        return (string)($this->decryptCallback)($ciphertext);
    }
}
