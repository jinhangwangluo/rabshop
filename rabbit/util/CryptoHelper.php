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

namespace Rabbit\util;

class CryptoHelper
{
    /**
     * @var string
     */
    private $key;

    /**
     * @var string
     */
    private $ciphering = "AES-256-CBC";

    /**
     * @var int
     */
    private $options = 0;

    /**
     * @var false|string
     */
    private $encryption_iv;

    /**
     * @var false|int
     */
    private $iv_length;

    /**
     * @param string $key
     */
    public function __construct(string $key)
    {
        // 密钥长度必须是 32 字节（256 位）
        $this->key = hash('sha256', $key, true);
        $this->iv_length = openssl_cipher_iv_length($this->ciphering);
        $this->encryption_iv = openssl_random_pseudo_bytes($this->iv_length);
    }

    /**
     * 加密数据
     * @param array $data
     * @return string
     */
    public function encrypt(array $data)
    {
        $encrypted = openssl_encrypt(json_encode($data), $this->ciphering, $this->key, $this->options, $this->encryption_iv);
        // 将初始化向量和加密数据一起返回，用分隔符分隔
        return base64_encode($this->encryption_iv . '::' . $encrypted);
    }

    /**
     * 解密数据
     * @param $encryptedData
     * @return false|string
     */
    public function decrypt($encryptedData)
    {
        $decoded = base64_decode($encryptedData);
        // 从加密数据中提取初始化向量和加密数据
        list($encryption_iv, $encrypted) = explode('::', $decoded, 2);
        return openssl_decrypt($encrypted, $this->ciphering, $this->key, $this->options, $encryption_iv);
    }
}
