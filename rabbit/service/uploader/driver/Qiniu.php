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
use Qiniu\Auth;
use Qiniu\Http\Client;
use Qiniu\Http\Error;
use Qiniu\Storage\BucketManager;
use Qiniu\Storage\UploadManager;
use Qiniu\Config;
use StdClass;
use think\exception\ValidateException;


/**
 * 七牛云上传
 * Class Qiniu
 * @package dengfenglai\service\uploader\driver
 */
class Qiniu extends BaseDriver
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
     * 实例化七牛云
     * @return object|Auth
     */
    protected function application()
    {
        if (!$this->accessKey || !$this->secretKey) {
            throw new UploaderException('请先配置 accessKey 和 secretKey');
        }
        return new Auth($this->accessKey, $this->secretKey);
    }

    /**
     * 上传文件
     * @param string $file
     * @return array|bool|mixed|StdClass|string
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
                    $file . '.fileMime' => '上传文件类型不正确'
                ];
                validate([$file => $this->validate], $error)->check([$file => $fileHandle]);
            } catch (ValidateException $e) {
                throw new UploaderException($e->getMessage());
            }
        }
        $key = $this->getSaveFileName($fileHandle->getRealPath(), $fileHandle->getOriginalExtension());
        $key = $this->getUploadPath($key);
        $token = $this->application()->uploadToken($this->name);

        $uploadMgr = new UploadManager();
        $preparedFile = $this->prepareWatermarkUploadFile($fileHandle);
        try {
            [$result, $error] = $uploadMgr->putFile($token, $key, (string)$preparedFile['path']);
        } finally {
            $this->cleanupWatermarkUploadFile($preparedFile);
        }
        if ($error !== null) {
            throw new UploaderException($error->message());
        }
        $fileInfo['uploadInfo'] = $result;
        $fileInfo['realName'] = $fileHandle->getOriginalName();
        $fileInfo['filePath'] = $this->url . '/' . $key;
        $fileInfo['fileName'] = $key;
        $fileInfo['updateType'] = UploaderService::QINIU_UPLOADER;
        return $fileInfo;

    }

    /**
     * 文件流上传
     * @param string $fileContent
     * @param string|null $key
     * @return array|bool
     */
    public function stream(string $fileContent, string $key = null): array|bool
    {
        if (!$key) {
            $key = $this->getSaveFileName();
        }
        $key = $this->getUploadPath($key);
        $token = $this->application()->uploadToken($this->name, $key);

        $uploadMgr = new UploadManager();
        [$result, $error] = $uploadMgr->put($token, $key, $fileContent);
        if ($error !== null) {
            throw new UploaderException($error->message());
        }
        $fileInfo['uploadInfo'] = $result;
        $fileInfo['realName'] = $key;
        $fileInfo['filePath'] = $this->url . '/' . $key;
        $fileInfo['fileName'] = $key;
        $fileInfo['updateType'] = UploaderService::QINIU_UPLOADER;
        return $fileInfo;
    }


    /**
     *  删除资源
     * @param $key
     * @param $bucket
     * @return mixed
     */
    public function delete(string $key)
    {
        $bucketManager = new BucketManager($this->application(), new Config());
        return $bucketManager->delete($this->name, $key);
    }


    /**
     * 获取当前所有桶列表
     * @param string|null $region
     * @param bool $line
     * @param bool $shared
     * @return bool|mixed
     */
    public function listbuckets(string $region = null, bool $line = false, bool $shared = false)
    {
        $bucket = new BucketManager($this->application());
        [$response, $error] = $bucket->listbuckets($region, $line ? 'true' : 'false', $shared ? 'true' : 'false');
        if ($error !== null) {
            throw new UploaderException($error->message());
        }
        return $response;
    }

    /**
     * @param string $name
     * @param string $region
     * @return bool|mixed
     */
    public function createBucket(string $name, string $region = 'z0')
    {
        $regionData = $this->getRegion();
        if (!in_array($region, array_column($regionData, 'value'))) {
            throw new UploaderException('七牛云:无效的区域');
        }
        $url = 'https://' . Config::UC_HOST . '/mkbucketv3/' . $name . '/region/' . $region;
        $body = null;
        $headers = $this->application()->authorizationV2($url, 'POST', $body, 'application/json');
        $headers["Content-Type"] = 'application/json';
        $ret = Client::post($url, $body, $headers);
        if (!$ret->ok()) {
            $error = new Error($url, $ret);
            if ('bucket exists' === $error->message()) {
                throw new UploaderException('七牛云：云空间已存在');
            }
            throw new UploaderException('七牛云：' . $error->message());
        }
        return ($ret->body === null) ? array() : $ret->json();
    }

    /**
     * 获取区域
     * @return mixed|\string[][]
     */
    public function getRegion()
    {
        return [
            [
                'value' => 'z0',
                'label' => '华东'
            ],
            [
                'value' => 'z1',
                'label' => '华北'
            ],
            [
                'value' => 'z2',
                'label' => '华南'
            ],
            [
                'value' => 'na0',
                'label' => '北美'
            ],
            [
                'value' => 'as0',
                'label' => '东南亚'
            ],
            [
                'value' => 'cn-east-2',
                'label' => '华东-浙江2'
            ],
        ];
    }

    /**
     * 删除空间
     * @param string $name
     * @return bool|mixed
     */
    public function deleteBucket(string $name)
    {
        $bucket = new BucketManager($this->application());
        [$response, $error] = $bucket->deleteBucket($name);
        if ($error !== null) {
            throw new UploaderException($error->message());
        }
        return $response;
    }

    /**
     * 获取七牛域名
     * @param string $name
     * @return array|bool|mixed|null
     */
    public function getDomian(string $name)
    {
        $url = 'https://' . Config::UC_HOST . '/v2/domains?tbl=' . $name;
        $body = null;
        $headers = $this->application()->authorizationV2($url, 'POST', $body, 'application/x-www-form-urlencoded');
        $headers["Content-Type"] = 'application/x-www-form-urlencoded';
        $ret = Client::post($url, $body, $headers);
        if (!$ret->ok()) {
            $error = new Error($url, $ret);
            throw new UploaderException('七牛云：' . $error->message());
        }
        return ($ret->body === null) ? array() : $ret->json();
    }

    public function getDomianInfo(string $host)
    {
        $url = 'https://' . Config::API_HOST . '/domain/' . $host;
        $headers = $this->application()->authorization($url, null, 'application/x-www-form-urlencoded');
        $headers["Content-Type"] = 'application/x-www-form-urlencoded';
        $ret = Client::get($url, $headers);
        if (!$ret->ok()) {
            $error = new Error($url, $ret);
            throw new UploaderException('七牛云：' . $error->message());
        }
        return ($ret->body === null) ? array() : $ret->json();
    }

    /**
     *
     * @param string $name
     * @param string $domain
     * @param string|null $region
     * @return array|bool|mixed|null
     */
    public function bindDomian(string $name, string $domain, string $region = null)
    {
        $parseDomin = parse_url($domain);
        $url = 'https://' . Config::API_HOST . '/domain/' . $parseDomin['host'];
        $body = [
            'type'     => 'normal',
            'platform' => 'web',
            'geocover' => 'china',
            'source'   => [
                'sourceType'        => 'qiniuBucket',
                'sourceQiniuBucket' => $name,
                'TestURLPath'       => 'qiniu_do_not_delete.gif',
            ],
            'protocol' => $parseDomin['scheme'],
            'cache'    => [
                'cacheControls' => [
                    [
                        'time'     => 1,
                        'timeunit' => 4,
                        'type'     => 'all',
                        'rule'     => '*'
                    ]
                ],
                'ignoreParam'   => false
            ]
        ];
        $bodyJson = json_encode($body);
        $headers = $this->application()->authorization($url, $bodyJson, 'application/json');
        $headers["Content-Type"] = 'application/json';
        $ret = Client::post($url, $bodyJson, $headers);
        if (!$ret->ok()) {
            $error = new Error($url, $ret);
            throw new UploaderException('七牛云：' . $error->message());
        }
        return ($ret->body === null) ? array() : $ret->json();
    }

    /**
     * 跨域
     * @param string $name
     * @param string $region
     * @return bool
     */
    public function setBucketCors(string $name, string $region)
    {
        return true;
    }
}
