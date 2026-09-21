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

namespace Rabbit\service\collector;

use Rabbit\interfaces\ConfigInterface;

/**
 * 商品采集驱动基类。
 */
abstract class BaseDriver implements CollectorInterface
{
    /**
     * 配置服务。
     */
    protected ?ConfigInterface $configServe = null;

    /**
     * 设置配置服务。
     *
     * @param ConfigInterface $configService 配置服务。
     * @return static
     */
    public function setConfigServe(ConfigInterface $configService): static
    {
        $this->configServe = $configService;
        return $this;
    }

    /**
     * 获取配置值。
     *
     * @param string $key 配置键。
     * @param mixed $default 默认值。
     * @return mixed
     */
    protected function config(string $key, mixed $default = ''): mixed
    {
        if ($this->configServe === null) {
            return $default;
        }

        return $this->configServe->getValue($key) ?? $default;
    }

    /**
     * 规范化媒体 URL。
     *
     * @param mixed $url 原始 URL。
     * @return string
     */
    protected function normalizeUrl(mixed $url): string
    {
        $url = trim((string) $url);
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }
        return $url;
    }
}
