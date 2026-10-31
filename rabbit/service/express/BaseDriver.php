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

namespace Rabbit\service\express;

use Rabbit\interfaces\ConfigInterface;
use Rabbit\util\Curl;

/**
 * 物流查询驱动基类
 */
abstract class BaseDriver implements ExpressInterface
{
    protected ?ConfigInterface $configServe = null;

    public function setConfigServe(ConfigInterface $configService): static
    {
        $this->configServe = $configService;
        return $this;
    }

    abstract public function getProvider(): string;

    protected function cfg(string $key, mixed $default = ''): mixed
    {
        if ($this->configServe !== null) {
            $value = $this->configServe->getValue($key);
            return $value ?? $default;
        }

        return function_exists('db_config') ? (db_config($key) ?? $default) : $default;
    }

    /**
     * 发起表单 POST 请求
     *
     * @throws ExpressException
     */
    protected function httpFormPost(string $url, array $data, array $headers = []): array
    {
        $curl = app()->make(Curl::class);
        $response = $curl->request($url, 'POST', $data, $headers);
        if ($response === false) {
            throw new ExpressException("物流查询请求失败：{$url}");
        }

        return $response ? (json_decode($response, true) ?? []) : [];
    }
}
