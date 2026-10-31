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

use Rabbit\service\uploader\UploaderException;
use Rabbit\service\uploader\UploaderService;
use Qcloud\Cos\Client;
use Rabbit\service\uploader\BaseDriver;

/**
 * COS文件上传
 * Class COS
 * @package dengfenglai\service\uploader\driver
 */
class Cos extends BaseDriver
{

    /**
     * 应用id
     * @var string
     */
    protected mixed $appid;

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
     * 句柄
     * @var Client
     */
    protected Client $handle;

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
     * @var mixed|null
     */
    protected mixed $cdn;

    /**
     * 初始化
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->accessKey = $config['accessKey'] ?? null;
        $this->secretKey = $config['secretKey'] ?? null;
        $this->appid = $config['appid'] ?? '';
        $this->url = $this->setUrl($config['url'] ?? '');
        $this->name = $config['name'] ?? null;
        $this->region = $config['region'] ?? null;
        $this->cdn = $config['cdn'] ?? null;
    }

    /**
     * 实例化cos
     * @return Client
     */
    protected function application(): Client
    {
        if (!$this->accessKey || !$this->secretKey) {
            throw new UploaderException('请先配置 accessKey 和 secretKey');
        }
        return new Client(['region' => $this->region ?: 'ap-chengdu', 'credentials' => [
            'secretId' => $this->accessKey, 'secretKey' => $this->secretKey
        ]]);
    }

    /**
     * 上传文件
     * @param string|null $file
     * @param bool $isStream 是否为流上传
     * @param string|null $fileContent 流内容
     * @return array|bool
     */
    protected function upload(string $file = null, bool $isStream = false, string $fileContent = null): bool|array
    {
        if (!$isStream) {
            $fileHandle = app()->request->file($file);
            if (!$fileHandle) {
                throw new UploaderException('上传的文件不存在');
            }
            if ($this->validate) {
                $error = [
                    $file . '.filesize' => '上传文件大小超出限制',
                    $file . '.fileExt'  => '上传文件后缀不符合类型',
                    $file . '.fileMime' => 'U上传文件类型不正确'
                ];
                validate([$file => $this->validate], $error)->check([$file => $fileHandle]);
            }
            $key = $this->getSaveFileName($fileHandle->getRealPath(), $fileHandle->getOriginalExtension());
            $preparedFile = $this->prepareWatermarkUploadFile($fileHandle);
            $body = fopen((string)$preparedFile['path'], 'rb');
        } else {
            $key = $file;
            $body = $fileContent;
            $preparedFile = ['temp' => false, 'path' => ''];
        }

        $key = $this->getUploadPath($key);
        try {
            $fileInfo['uploadInfo'] = $this->application()->putObject([
                'Bucket' => $this->name,
                'Key'    => $key,
                'Body'   => $body
            ]);
        } finally {
            if (is_resource($body)) {
                fclose($body);
            }
            $this->cleanupWatermarkUploadFile($preparedFile);
        }
        $fileInfo['filePath'] = ($this->cdn ?: $this->url) . '/' . $key;
        $fileInfo['realName'] = isset($fileHandle) ? $fileHandle->getOriginalName() : $key;
        $fileInfo['fileName'] = $key;
        $fileInfo['updateType'] = UploaderService::COS_UPLOADER;
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
        return $this->upload($key, true, $fileContent);
    }

    /**
     * 文件上传
     * @param string $file
     * @return array|bool
     */
    public function move(string $file = 'file')
    {
        return $this->upload($file);
    }


    /**
     *  删除资源
     * @param string $filePath
     * @return mixed
     */
    public function delete(string $filePath)
    {
        try {
            return $this->application()->deleteObject(['Bucket' => $this->name, 'Key' => $filePath]);
        } catch (\Exception $e) {
            throw new UploaderException($e->getMessage());
        }
    }

    /**
     * 桶列表
     * @return array|mixed
     */
    public function listbuckets(): mixed
    {
        try {
            $res = $this->application()->listBuckets();
            $res = $res->toArray();
            $bucket = [];
            if (isset($res['Buckets'][0]['Bucket'])) {
                if (isset($res['Buckets'][0]['Bucket']['Name'])) {
                    $bucket[] = $res['Buckets'][0]['Bucket'];
                } else {
                    $bucket = $res['Buckets'][0]['Bucket'];
                }
            }
            return $bucket;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * 创建桶
     * @param string $name
     * @param string $region
     * @param string $acl public-read=公共独写
     * @return bool|mixed
     */
    public function createBucket(string $name, string $region = '', string $acl = 'public-read'): mixed
    {
        $regionData = $this->getRegion();
        $regionData = array_column($regionData, 'value');
        if (!in_array($region, $regionData)) {
            throw new UploaderException('COS:无效的区域!');
        }
        $this->region = $region;
        $app = $this->application();
        //检测桶
        try {
            $app->headBucket(['Bucket' => $name . '-' . $this->appid]);
        } catch (\Throwable $e) {
            //桶不存在返回404
            if (strstr('404', $e->getMessage())) {
                throw new UploaderException('COS:' . $e->getMessage());
            }
        }
        //创建桶
        try {
            $res = $app->createBucket(['Bucket' => $name . '-' . $this->appid, 'ACL' => $acl]);
        } catch (\Throwable $e) {
            if (strstr('[curl] 6', $e->getMessage())) {
                throw new UploaderException('COS:无效的区域!!');
            } else if (strstr('Access Denied.', $e->getMessage())) {
                throw new UploaderException('COS:无权访问');
            }
            throw new UploaderException('COS:' . $e->getMessage());
        }
        return $res;
    }

    /**
     * 删除桶
     * @param string $name
     * @return bool
     */
    public function deleteBucket(string $name): bool
    {
        try {
            $res = $this->application()->deleteBucket(['Bucket' => $name]);
            if ($res->get('RequestId')) {
                return true;
            }
        } catch (\Throwable $e) {
            throw new UploaderException($e->getMessage());
        }
        return false;
    }

    /**
     * @param string $name
     * @param string|null $region
     * @return array|object
     */
    public function getDomian(string $name, string $region = null): object|array
    {
        $this->region = $region;
        try {
            $res = $this->application()->GetBucketDomain([
                'Bucket' => $name,
            ]);
            $domainRules = $res->toArray()['DomainRules'];
            return array_column($domainRules, 'Name');
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
    public function bindDomian(string $name, string $domain, string $region = null): mixed
    {
        $this->region = $region;
        $parseDomin = parse_url($domain);
        try {
            $res = $this->application()->putBucketDomain([
                'Bucket'      => $name,
                'DomainRules' => [
                    [
                        'Name'              => $parseDomin['host'],
                        'Status'            => 'ENABLED',
                        'Type'              => 'REST',
                        'ForcedReplacement' => 'CNAME'
                    ]
                ]
            ]);
            $res = $res->toArray();
            if ($res['RequestId'] ?? null) {
                return true;
            }
        } catch (\Throwable $e) {
            if ($message = $this->setMessage($e->getMessage())) {
                throw new UploaderException($message);
            }
            throw new UploaderException($e->getMessage());
        }
        return false;
    }

    /**
     * 处理
     * @param string $message
     * @return string
     */
    protected function setMessage(string $message): string
    {
        $data = [
            'The specified bucket does not exist.'                                                                              => '指定的存储桶不存在。',
            'Please add CNAME/TXT record to DNS then try again later. Please allow up to 10 mins before your DNS takes effect.' => '请将CNAME记录添加到DNS，然后稍后重试。在DNS生效前，请等待最多10分钟。'
        ];
        $msg = $data[$message] ?? '';
        if ($msg) {
            return $msg;
        }
        foreach ($data as $item) {
            if (strstr($message, $item)) {
                return $item;
            }
        }
        return '';
    }

    /**
     * 设置跨域
     * @param string $name
     * @param string $region
     * @return bool
     */
    public function setBucketCors(string $name, string $region): bool
    {
        $this->region = $region;
        try {
            $res = $this->application()->PutBucketCors([
                'Bucket'    => $name,
                'CORSRules' => [
                    [
                        'AllowedHeaders' => ['*'],
                        'AllowedMethods' => ['PUT', 'GET', 'POST', 'DELETE', 'HEAD'],
                        'AllowedOrigins' => ['*'],
                        'ExposeHeaders'  => ['ETag', 'Content-Length', 'x-cos-request-id'],
                        'MaxAgeSeconds'  => 12
                    ]
                ]
            ]);
            if (isset($res['RequestId'])) {
                return true;
            }
        } catch (\Throwable $e) {
            throw new UploaderException($e->getMessage());
        }
        return false;
    }

    /**
     * 地域
     * @return array
     */
    public function getRegion(): array
    {
        return [
            [
                'value' => 'ap-chengdu',
                'label' => '成都'
            ],
            [
                'value' => 'ap-shanghai',
                'label' => '上海'
            ],
            [
                'value' => 'ap-nanjing',
                'label' => '南京'
            ],
            [
                'value' => 'ap-beijing',
                'label' => '北京'
            ],
            [
                'value' => 'ap-chongqing',
                'label' => '重庆'
            ],
            [
                'value' => 'ap-shenzhen-fsi',
                'label' => '深圳金融'
            ],
            [
                'value' => 'ap-shanghai-fsi',
                'label' => '上海金融'
            ],
            [
                'value' => 'ap-beijing-fsi',
                'label' => '北京金融'
            ],
            [
                'value' => 'ap-hongkong',
                'label' => '中国香港'
            ],
            [
                'value' => 'ap-singapore',
                'label' => '新加坡'
            ],
            [
                'value' => 'ap-mumbai',
                'label' => '孟买'
            ],
            [
                'value' => 'ap-jakarta',
                'label' => '雅加达'
            ],
            [
                'value' => 'ap-seoul',
                'label' => '首尔'
            ],
            [
                'value' => 'ap-bangkok',
                'label' => '曼谷'
            ],
            [
                'value' => 'ap-tokyo',
                'label' => '东京'
            ],
            [
                'value' => 'na-siliconvalley',
                'label' => '硅谷（美西）'
            ],
            [
                'value' => 'na-ashburn',
                'label' => '弗吉尼亚（美东）'
            ],
            [
                'value' => 'na-toronto',
                'label' => '多伦多'
            ],
            [
                'value' => 'sa-saopaulo',
                'label' => '圣保罗'
            ],
            [
                'value' => 'eu-frankfurt',
                'label' => '法兰克福'
            ],
            [
                'value' => 'eu-moscow',
                'label' => '莫斯科'
            ]
        ];
    }
}
