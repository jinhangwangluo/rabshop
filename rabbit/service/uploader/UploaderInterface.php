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

namespace Rabbit\service\uploader;

use Rabbit\interfaces\ConfigInterface;

/**
 * 上传接口
 * Interface UploaderInterface
 * @package dengfenglai\service\uploader
 */
interface UploaderInterface
{

    /**
     * 获取配置
     * @param string $key
     * @return mixed
     */
    public function getConfig(string $key);

    /**
     * 获取多个配置
     * @param array $keys
     * @return array
     */
    public function getConfigs(array $keys): array;

    /**
     * 设置获取config服务
     * @param ConfigInterface $service
     * @return $this
     */
    public function setConfigServe(ConfigInterface $service): self;

    /**
     * 上传
     * @param string|null $type
     * @param array $options
     * @return mixed
     */
    public function storage(string $type = null, array $options = []);

}
