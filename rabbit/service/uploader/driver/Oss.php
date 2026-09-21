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

namespace Rabbit\service\uploader\driver;

use Rabbit\service\uploader\BaseDriver;
use Rabbit\service\uploader\UploaderException;
use Rabbit\service\uploader\UploaderService;
use GuzzleHttp\Psr7\Utils;
use OSS\Core\OssException;
use OSS\Model\BucketListInfo;
use OSS\Model\CorsConfig;
use OSS\Model\CorsRule;
use OSS\OssClient;
use think\exception\ValidateException;

/**
 * 阿里云OSS上传
 * Class Oss
 * @package dengfenglai\service\uploader\driver
 */
class Oss extends BaseDriver
{
    /**
     * accessKey
     * @var mixed
     */
    protected mixed $accessKey;

    /**
     * secretKey
     * @var mixed
     */
    protected mixed $secretKey;

    /**
     * 空间域名 Domain
     * @var mixed
     */
    protected mixed $url;

    /**
     * 存储空间名称  公开空间
     * @var mixed
     */
    protected mixed $name;

    /**
     * COS使用  所属地域
     * @var mixed|null
     */
    protected mixed $region;


    /**
     * 初始化
     * @param array $config
     * @return mixed|void
     */
    public function __construct(array $config)
    {
        $this->accessKey = $config['accessKey'] ?? null;
        $this->secretKey = $config['secretKey'] ?? null;
        $this->url = $this->setUrl($config['url'] ?? '');
        $this->name = $config['name'] ?? null;
        $this->region = $config['region'] ?? null;
    }

    /**
     * 初始化oss
     * @return OssClient
     * @throws OssException
     */
    protected function application()
    {
        if (!$this->accessKey || !$this->secretKey) {
            throw new UploaderException('请先配置 accessKey 和 secretKey');
        }
        return new OssClient($this->accessKey, $this->secretKey, $this->region);
    }

    /**
     * 上传文件
     * @param string $file
     * @return array|bool|mixed|\StdClass
     * @throws OssException
     */
    public function move(string $file = 'file')
    {
        $fileHandle = app()->request->file($file);
        if (!$fileHandle) {
            throw new UploaderException('上传的文件不存在');
        }
        if ($this->validate) {
            try {
                $error = [
                    $file . '.filesize' => '上传文件大小超出限制',
                    $file . '.fileExt'  => '上传文件后缀不符合类型',
                    $file . '.fileMime' => 'U上传文件类型不正确'
                ];
                validate([$file => $this->validate], $error)->check([$file => $fileHandle]);
            } catch (ValidateException $e) {
                throw new UploaderException($e->getMessage());
            }
        }
        $key = $this->getSaveFileName($fileHandle->getRealPath(), $fileHandle->getOriginalExtension());
        $key = $this->getUploadPath($key);

        $preparedFile = $this->prepareWatermarkUploadFile($fileHandle);
        try {
            $uploadInfo = $this->application()->putObject($this->name, $key, file_get_contents((string)$preparedFile['path']));
        } finally {
            $this->cleanupWatermarkUploadFile($preparedFile);
        }
        if (!isset($uploadInfo['info']['url'])) {
            throw new UploaderException('Upload failure');
        }
        $fileInfo['uploadInfo'] = $uploadInfo;
        $fileInfo['realName'] = $fileHandle->getOriginalName();
        $fileInfo['filePath'] = $this->url . '/' . $key;
        $fileInfo['fileName'] = $key;
        $fileInfo['updateType'] = UploaderService::OSS_UPLOADER;
        return $fileInfo;

    }

    /**
     * 文件流上传
     * @param string $fileContent
     * @param string|null $key
     * @return bool|array
     */
    public function stream(string $fileContent, string $key = null): array|bool
    {
        try {
            if (!$key) {
                $key = $this->getSaveFileName();
            }
            $key = $this->getUploadPath($key);
            $fileContent = (string)Utils::streamFor($fileContent);
            $uploadInfo = $this->application()->putObject($this->name, $key, $fileContent);
            if (!isset($uploadInfo['info']['url'])) {
                return $this->setError('Upload failure');
            }
            $fileInfo['uploadInfo'] = $uploadInfo;
            $fileInfo['realName'] = $key;
            $fileInfo['filePath'] = $this->url . '/' . $key;
            $fileInfo['fileName'] = $key;
            $fileInfo['updateType'] = UploaderService::OSS_UPLOADER;
            return $fileInfo;
        } catch (UploaderException $e) {
            return $this->setError($e->getMessage());
        }
    }


    /**
     * 删除资源
     * @param $key
     * @return mixed
     */
    public function delete(string $key)
    {
        try {
            return $this->application()->deleteObject($this->name, $key);
        } catch (OssException $e) {
            return $this->setError($e->getMessage());
        }
    }

    /**
     * 获取当前管理下的所有桶
     * @param string|null $region
     * @return mixed|BucketListInfo
     * @throws OssException
     */
    public function listbuckets(string $region = 'oss-cn-hangzhou.aliyuncs.com')
    {
        $handle = new OssClient($this->accessKey, $this->secretKey, $region);
        $response = $handle->listBuckets();
        $data = $response->getBucketList();
        $list = [];
        foreach ($data as $item) {
            $list[] = [
                'location'   => $item->getLocation(),
                'name'       => $item->getName(),
                'createTime' => $item->getCreateDate()
            ];
        }
        return $list;
    }

    /**
     * @param string $name
     * @param string $region
     * @param string $acl
     * @return bool|null
     */
    public function createBucket(string $name, string $region = '', string $acl = OssClient::OSS_ACL_TYPE_PUBLIC_READ)
    {
        $regionData = $this->getRegion();
        if (!in_array($region, array_column($regionData, 'value'))) {
            throw new UploaderException('OSS:无效的区域');
        }
        try {
            $handle = new OssClient($this->accessKey, $this->secretKey, $region);
            if ($handle->doesBucketExist($name)) {
                throw new UploaderException('OSS:空间已经存在，请更换空间名');
            }
            return $handle->createBucket($name, $acl);
        } catch (\Throwable $e) {
            if (strstr('The bucket you access does not belong to you', $e->getMessage())) {
                throw new UploaderException('OSS:空间已被使用，请更换空间名');
            }
            throw new UploaderException('OSS:' . $e->getMessage());
        }
    }

    /**
     * @param string $name
     * @param string $region
     * @return bool|mixed|null
     */
    public function deleteBucket(string $name, string $region = ''): mixed
    {
        try {
            $handle = new OssClient($this->accessKey, $this->secretKey, $region);
            return $handle->deleteBucket($name);
        } catch (\Throwable $e) {
            throw new UploaderException($e->getMessage());
        }
    }

    /**
     * @param string $name
     * @param string|null $region
     * @return array|bool
     */
    public function getDomian(string $name, string $region = null)
    {
        try {
            $handle = new OssClient($this->accessKey, $this->secretKey, $region);
            $res = $handle->getBucketCname($name);
            $data = $res->getCnames();
            return array_column($data, 'Domain');
        } catch (\Throwable $e) {
        }
        return [];
    }

    /**
     * 绑定域名
     * @param string $name
     * @param string $domain
     * @param string|null $region
     * @return bool|mixed
     */
    public function bindDomian(string $name, string $domain, string $region = null)
    {
        $parseDomin = parse_url($domain);
        try {
            $handle = new OssClient($this->accessKey, $this->secretKey, $region);
            $res = $handle->getBucketCname($name);
            $data = $res->getCnames();
            if (in_array($parseDomin['host'], array_column($data, 'Domain'))) {
                return true;
            }
            return $handle->addBucketCname($name, $parseDomin['host']);
        } catch (\Throwable $e) {
            throw new UploaderException($e->getMessage());
        }
    }

    /**
     * 设置跨域
     * @param string $name
     * @param string $region
     * @return bool|null
     */
    public function setBucketCors(string $name, string $region): ?bool
    {
        try {
            $handle = new OssClient($this->accessKey, $this->secretKey, $region);
            $corsConfig = new CorsConfig();
            $rule = new CorsRule();
            // 设置允许跨域请求的响应头。AllowedHeader可以设置多个，每个AllowedHeader中最多只能使用一个通配符星号（*）。
            // 建议无特殊需求时设置AllowedHeader为星号（*）。
            $rule->addAllowedHeader("*");
            // 设置允许用户从应用程序中访问的响应头。ExposeHeader可以设置多个，ExposeHeader中不支持使用通配符星号（*）。
            $rule->addExposeHeader("ETag");
            // 设置允许的跨域请求的来源。AllowedOrigin可以设置多个，每个AllowedOrigin中最多只能使用一个通配符星号（*）。
            // 设置AllowedOrigin为星号（*）时，表示允许所有域的来源。
            $rule->addAllowedOrigin("*");
            // 设置允许的跨域请求方法。
            $rule->addAllowedMethod("POST");
            $rule->addAllowedMethod("GET");
            $rule->addAllowedMethod("DELETE");
            $rule->addAllowedMethod("PUT");
            $rule->addAllowedMethod("HEAD");
            // 设置浏览器对特定资源的预取（OPTIONS）请求返回结果的缓存时间，单位为秒。
            $rule->setMaxAgeSeconds(600);
            // 每个Bucket最多支持添加10条规则。
            $corsConfig->addRule($rule);
            return $handle->putBucketCors($name, $corsConfig);
        } catch (\Throwable $e) {
            throw new UploaderException($e->getMessage());
        }
    }

    /**
     * 数据中心
     * @return array
     */
    public function getRegion(): array
    {
        return [
            [
                'value' => 'oss-cn-hangzhou.aliyuncs.com',
                'label' => '华东1（杭州）'
            ],
            [
                'value' => 'oss-cn-shanghai.aliyuncs.com',
                'label' => '华东2（上海）'
            ],
            [
                'value' => 'oss-cn-qingdao.aliyuncs.com',
                'label' => '华北1（青岛）'
            ],
            [
                'value' => 'oss-cn-beijing.aliyuncs.com',
                'label' => '华北2（北京）'
            ],
            [
                'value' => 'oss-cn-zhangjiakou.aliyuncs.com',
                'label' => '华北 3（张家口）'
            ],
            [
                'value' => 'oss-cn-huhehaote.aliyuncs.com',
                'label' => '华北5（呼和浩特）'
            ],
            [
                'value' => 'oss-cn-wulanchabu.aliyuncs.com',
                'label' => '华北6（乌兰察布）'
            ],
            [
                'value' => 'oss-cn-shenzhen.aliyuncs.com',
                'label' => '华南1（深圳）'
            ],
            [
                'value' => 'oss-cn-heyuan.aliyuncs.com',
                'label' => '华南2（河源）'
            ],
            [
                'value' => 'oss-cn-guangzhou.aliyuncs.com',
                'label' => '华南3（广州）'
            ],
            [
                'value' => 'oss-cn-chengdu.aliyuncs.com',
                'label' => '西南1（成都）'
            ],
            [
                'value' => 'oss-cn-hongkong.aliyuncs.com',
                'label' => '中国（香港）'
            ],
            [
                'value' => 'oss-us-west-1.aliyuncs.com',
                'label' => '美国（硅谷）*'
            ],
            [
                'value' => 'oss-us-east-1.aliyuncs.com',
                'label' => '美国（弗吉尼亚）*'
            ],
            [
                'value' => 'oss-ap-southeast-1.aliyuncs.com',
                'label' => '新加坡*'
            ],
            [
                'value' => 'oss-ap-southeast-2.aliyuncs.com',
                'label' => '澳大利亚（悉尼）*'
            ],
            [
                'value' => 'oss-ap-southeast-3.aliyuncs.com',
                'label' => '马来西亚（吉隆坡）*'
            ],
            [
                'value' => 'oss-ap-southeast-5.aliyuncs.com',
                'label' => '印度尼西亚（雅加达）*'
            ],
            [
                'value' => 'oss-ap-northeast-1.aliyuncs.com',
                'label' => '日本（东京）*'
            ],
            [
                'value' => 'oss-ap-south-1.aliyuncs.com',
                'label' => '印度（孟买）*'
            ],
            [
                'value' => 'oss-eu-central-1.aliyuncs.com',
                'label' => '德国（法兰克福）*'
            ],
            [
                'value' => 'oss-eu-west-1.aliyuncs.com',
                'label' => '英国（伦敦）'
            ],
            [
                'value' => 'oss-me-east-1.aliyuncs.com',
                'label' => '阿联酋（迪拜）*'
            ],
            [
                'value' => 'oss-ap-southeast-6.aliyuncs.com',
                'label' => '菲律宾（马尼拉）'
            ]
        ];
    }
}
