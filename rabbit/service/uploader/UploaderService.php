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
use Rabbit\traits\DriverRegistryTrait;
use Rabbit\service\uploader\driver\Cos;
use Rabbit\service\uploader\driver\Local;
use Rabbit\service\uploader\driver\Oss;
use Rabbit\service\uploader\driver\Qiniu;

/**
 * 上传服务类
 * Class UploaderService
 * @package dengfenglai\service\uploader
 */
class UploaderService implements UploaderInterface
{
    use DriverRegistryTrait;

    /** 插件 manifest 中的声明字段 */
    private const MANIFEST_KEY = 'uploader_drivers';


    //本地上传
    const LOCAL_UPLOADER = 'local';
    //腾讯云cos
    const COS_UPLOADER = 'cos';
    //阿里云oss
    const OSS_UPLOADER = 'oss';
    //七牛上传
    const QINIU_UPLOADER = 'qiniu';


    /**
     * 获取上传配置服务
     * @var ConfigInterface
     */
    protected ConfigInterface $serve;

    /**
     * 配置
     * @var array
     */
    protected array $config;

    /**
     * @param string $key
     * @return mixed
     */
    public function getConfig(string $key)
    {
        return $this->serve->getValue($key);
    }

    /**
     * 获取多个配置
     * @param array $keys
     * @return array
     */
    public function getConfigs(array $keys): array
    {
        return $this->serve->getMultiValue($keys);
    }

    /**
     * 设置服务
     * @param ConfigInterface $service
     * @return UploaderInterface
     */
    public function setConfigServe(ConfigInterface $service): UploaderInterface
    {
        $this->serve = $service;
        return $this;
    }

    /**
     * 上传
     * @param string|null $type
     * @param array $options
     * @return Cos|Local|Oss|Qiniu|object 内置上传驱动，或运行期注册的实现
     */
    public function storage(string|int|null $type = null, array $options = [])
    {
        if (null === $type) {
            $type = $this->getConfig('uploader_type');
        }

        if ($type === null || $type === '') {
            $type = self::LOCAL_UPLOADER;
        }

        if (is_numeric($type)) {
            $type = match ((int)$type) {
                1 => self::LOCAL_UPLOADER,
                2 => self::QINIU_UPLOADER,
                3 => self::COS_UPLOADER,
                4 => self::OSS_UPLOADER,
                default => self::LOCAL_UPLOADER,
            };
        }

        if ($type === self::LOCAL_UPLOADER) {
            $config = [];
        } else {
            $config['accessKey'] = $this->getConfig($type . '_access_key');
            $config['secretKey'] = $this->getConfig($type . '_secret_key');
            $config['appid'] = $this->getConfig($type . '_appid');
            $config['name'] = $this->getConfig($type . '_name');
            $config['url'] = $this->getConfig($type . '_url');
            $config['region'] = $this->getConfig($type . '_region');
            $config['cdn'] = $this->getConfig($type . '_cdn');
        }

        $config = array_merge($config, $options);

        // 运行期注册的上传实现优先（插件声明或宿主 extend），未命中再走内置实现。
        // 上传驱动本身没有接口约束（接口描述的是 UploaderService），因此只校验构造出对象。
        $registered = self::resolveRegistered((string)$type, $config);
        if ($registered !== null) {
            return $registered;
        }

        $handle = match ($type) {
            self::LOCAL_UPLOADER => new Local(),
            self::COS_UPLOADER => new Cos($config),
            self::OSS_UPLOADER => new Oss($config),
            self::QINIU_UPLOADER => new Qiniu($config),
            default => null
        };

        if (null === $handle) {
            throw new UploaderException('上传类型不存在');
        }

        return $handle;
    }
}
