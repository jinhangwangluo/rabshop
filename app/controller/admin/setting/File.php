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

namespace app\controller\admin\setting;


use app\common\enum\ConfigKeyEnum;
use app\common\enum\UploaderConfigKeyEnum;
use app\common\service\setting\FileService;
use app\common\util\FileTypeService;
use app\controller\admin\AuthController;
use OSS\Core\OssException;
use Rabbit\service\uploader\UploaderService;
use think\annotation\Inject;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Response;

/**
 * 附件管理
 * Class File
 * @package app\admin\controller\setting
 */
class File extends AuthController
{
    private const STORAGE_TYPE_PAYMENT_CERT = 'payment_cert';
    private const PAYMENT_CERT_BASE_PATH = 'storage/certs/wechat_pay';
    private const PAYMENT_CERT_MAX_SIZE = 2097152;
    private const PAYMENT_CERT_EXTENSIONS = ['pem'];

    /**
     * @var FileService
     */
    #[Inject]
    protected FileService $service;

    /**
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function list()
    {
        $where = $this->request->getMore([
            ['name', '', 'name_like'],
            ['cate_id', ''],
            ['file_type', 0],
        ]);
        return $this->success($this->service->getAdminVisibleList(where: $where, sort: 'id DESC')->each(function ($item) {
            return $this->service->formatFileRecord($item);
        }));
    }

    /**
     * 上传文件
     * @return mixed
     * @throws OssException
     */
    public function upload()
    {
        $cateId = $this->request->post('cate_id', 0);
        $file = $this->request->post('fileName', 'file');
        $fileType = $this->request->post('file_type', '');
        $storageType = (string)$this->request->post('storage_type', $this->request->post('upload_type', ''));

        if ($storageType === self::STORAGE_TYPE_PAYMENT_CERT) {
            return $this->uploadPaymentCert($file);
        }

        $fileHandle = $this->request->file($file);
        $fileSize = $fileHandle ? (string)$fileHandle->getSize() : '';

        $upload = app('uploader')->storage();
        $res = $upload->to('file/admin/' . date('Y'))->validate()->move($file);

        if (false === $res) {
            return $this->fail($upload->getError());
        }

        $fileRecord = $this->service->recordUploadedFile([
            'path'        => $res['filePath'] ?? '',
            'name'        => $res['fileName'] ?? '',
            'real_name'   => $res['realName'] ?? '',
            'file_size'   => $fileSize,
            'cate_id'     => $cateId,
            'update_type' => $res['updateType'] ?? 1,
            'file_type'   => $fileType,
        ]);

        $res = array_merge($res, $this->service->formatFileRecord($fileRecord));

        return $this->success('上传成功', $res);
    }

    /**
     * 登记云存储直传后的附件记录.
     *
     * @return mixed
     */
    public function record(): mixed
    {
        $data = $this->request->postMore([
            ['path', ''],
            ['url', ''],
            ['name', ''],
            ['real_name', ''],
            ['file_size', ''],
            ['cate_id', 0],
            ['update_type', 1],
            ['file_type', ''],
        ]);

        if (!$data['path'] && !$data['url']) {
            return $this->fail('缺少附件路径');
        }

        $fileRecord = $this->service->recordUploadedFile($data);

        return $this->success('记录成功', $this->service->formatFileRecord($fileRecord));
    }

    /**
     * 上传支付证书到非公开目录。
     *
     * @param string $file 上传表单字段名。
     * @return mixed
     */
    protected function uploadPaymentCert(string $file)
    {
        $fileHandle = $this->request->file($file);
        if (!$fileHandle) {
            return $this->fail('上传的文件不存在');
        }

        $extension = strtolower($fileHandle->extension());
        if (!in_array($extension, self::PAYMENT_CERT_EXTENSIONS, true)) {
            return $this->fail('支付证书文件仅支持 PEM 格式');
        }

        if ($fileHandle->getSize() > self::PAYMENT_CERT_MAX_SIZE) {
            return $this->fail('支付证书文件大小不能超过 2MB');
        }

        $content = (string)file_get_contents($fileHandle->getPathname());
        if (!str_contains($content, '-----BEGIN ') || !str_contains($content, '-----END ')) {
            return $this->fail('支付证书文件内容格式不正确');
        }

        $fileName = bin2hex(random_bytes(16)) . '.' . $extension;
        $relativeDirectory = $this->getPaymentCertRelativeDirectory();
        $directory = $this->getPaymentCertDirectory($relativeDirectory);
        $savedFile = $fileHandle->move($directory, $fileName);
        $relativePath = $relativeDirectory . '/' . $savedFile->getFilename();

        return $this->success('上传成功', [
            'filePath' => $relativePath,
            'path' => $relativePath,
            'relative_path' => $relativePath,
            'name' => $fileName,
            'real_name' => $fileHandle->getOriginalName(),
            'storage_type' => self::STORAGE_TYPE_PAYMENT_CERT,
            'is_private' => 1,
        ]);
    }

    /**
     * 获取支付证书项目相对保存目录。
     *
     * 目录位于 public 上一层，避免证书文件暴露在静态资源访问路径下。
     *
     * @return string
     */
    protected function getPaymentCertRelativeDirectory(): string
    {
        return self::PAYMENT_CERT_BASE_PATH . '/' . date('Y') . '/' . date('m');
    }

    /**
     * 获取支付证书服务器绝对保存目录。
     *
     * @param string $relativeDirectory 项目相对目录。
     * @return string
     */
    protected function getPaymentCertDirectory(string $relativeDirectory): string
    {
        $publicParent = dirname(rtrim(public_path(), DIRECTORY_SEPARATOR));

        return $publicParent . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
    }

    /**
     * 获取上传配置（上传类型及对应签名配置）
     * 前端根据返回的 type 决定使用本地分片上传还是云存储直传
     * @return mixed
     */
    public function getUploadConfig(): mixed
    {
        $uploaderType = db_config(UploaderConfigKeyEnum::UPLOADER_TYPE) ?: UploaderService::LOCAL_UPLOADER;

        $config = [
            'type'       => $uploaderType,
            'chunk_url'  => (string)url('file/chunk_upload'),
        ];

        switch ($uploaderType) {
            case UploaderService::COS_UPLOADER:
                // 腾讯云 COS 前端直传需要临时密鑰
                $config['cos'] = [
                    'bucket'     => db_config(UploaderConfigKeyEnum::COS_NAME),
                    'region'     => db_config(UploaderConfigKeyEnum::COS_REGION),
                    'cdn'        => db_config(UploaderConfigKeyEnum::COS_CDN),
                    'url'        => db_config(UploaderConfigKeyEnum::COS_URL),
                    'upload_url' => 'https://' . db_config(UploaderConfigKeyEnum::COS_NAME) . '.cos.' . db_config(UploaderConfigKeyEnum::COS_REGION) . '.myqcloud.com',
                ];
                break;

            case UploaderService::OSS_UPLOADER:
                // 阿里云 OSS 前端直传需要 Policy 签名
                $host      = 'https://' . db_config(UploaderConfigKeyEnum::OSS_NAME) . '.' . db_config(UploaderConfigKeyEnum::OSS_REGION);
                $expire    = time() + 3600;
                $expiration = gmdate('Y-m-d\TH:i:s\Z', $expire);
                $dir       = 'file/media/' . date('Y/m') . '/';
                $policy    = base64_encode(json_encode([
                    'expiration' => $expiration,
                    'conditions' => [
                        ['content-length-range', 0, 1024 * 1024 * 1024],
                        ['starts-with', '$key', $dir],
                    ],
                ]));
                $signature = base64_encode(hash_hmac('sha1', $policy, db_config(UploaderConfigKeyEnum::OSS_SECRET_KEY), true));
                $config['oss'] = [
                    'host'      => $host,
                    'url'       => db_config(UploaderConfigKeyEnum::OSS_URL),
                    'dir'       => $dir,
                    'policy'    => $policy,
                    'signature' => $signature,
                    'access_id' => db_config(UploaderConfigKeyEnum::OSS_ACCESS_KEY),
                    'expire'    => $expire,
                ];
                break;

            case UploaderService::QINIU_UPLOADER:
                // 七牛云上传 Token
                $auth  = new \Qiniu\Auth(db_config(UploaderConfigKeyEnum::QINIU_ACCESS_KEY), db_config(UploaderConfigKeyEnum::QINIU_SECRET_KEY));
                $token = $auth->uploadToken(db_config(UploaderConfigKeyEnum::QINIU_NAME), null, 3600);
                $region = (string)(db_config(UploaderConfigKeyEnum::QINIU_REGION, 'z2') ?: 'z2');
                $config['qiniu'] = [
                    'token'      => $token,
                    'url'        => db_config(UploaderConfigKeyEnum::QINIU_URL),
                    'region'     => $region,
                    'upload_url' => $this->getQiniuUploadUrl($region),
                ];
                break;

            default:
                // 本地分片上伦：不需要额外配置
                break;
        }

        return $this->success($config);
    }

    /**
     * 获取七牛云前端直传域名.
     *
     * 七牛不同 Bucket 区域必须使用对应上传域名，否则会返回
     * incorrect region。默认使用 z2（华南），兼容当前 shoprabbit bucket。
     *
     * @param string $region 七牛区域标识：z0/z1/z2/na0/as0
     * @return string
     */
    private function getQiniuUploadUrl(string $region): string
    {
        return match ($region) {
            'z0' => 'https://up.qiniup.com',
            'z1' => 'https://up-z1.qiniup.com',
            'z2' => 'https://up-z2.qiniup.com',
            'na0' => 'https://up-na0.qiniup.com',
            'as0' => 'https://up-as0.qiniup.com',
            default => 'https://up-z2.qiniup.com',
        };
    }

    /**
     * 本地分片上传（支持断点续传）
     *
     * 请求参数：
     *   file_md5       string  文件 MD5（32位小写十六进制，必填）
     *   chunk_index    int     当前分片索引，-1 表示仅查询已上传分片列表
     *   total_chunks   int     总分片数
     *   chunk_data     file    分片文件（chunk_index >= 0 时必须）
     *   file_name      string  原始文件名（可选）
     *   file_ext       string  文件后缀（可选，默认 mp4）
     *
     * @return mixed
     */
    public function chunkUpload(): mixed
    {
        $fileMd5     = $this->request->post('file_md5', '');
        $chunkIndex  = (int)$this->request->post('chunk_index', 0);
        $totalChunks = (int)$this->request->post('total_chunks', 1);
        $fileName    = $this->request->post('file_name', '');
        $fileExt     = strtolower($this->request->post('file_ext', ''));
        $fileSize    = (string)$this->request->post('file_size', '');

        if (!$fileMd5) {
            return $this->fail('缺少必要参数 file_md5');
        }

        /** @var \Rabbit\service\uploader\driver\Local $local */
        $local = app('uploader')->storage(UploaderService::LOCAL_UPLOADER);

        // chunk_index = -1：仅查询已上传的分片列表（断点续传探测）
        if ($chunkIndex === -1) {
            return $this->success([
                'done'            => false,
                'uploaded_chunks' => $local->queryChunks($fileMd5, $totalChunks),
            ]);
        }

        // 保存当前分片
        $chunkData = $this->request->file('chunk_data');
        if (!$chunkData) {
            return $this->fail('分片数据不能为空');
        }
        $local->saveChunk($fileMd5, $chunkIndex, $totalChunks, $chunkData);

        // 未到最后一片，返回已上传分片列表
        if ($chunkIndex + 1 < $totalChunks) {
            return $this->success([
                'done'            => false,
                'uploaded_chunks' => $local->queryChunks($fileMd5, $totalChunks),
                'chunk_index'     => $chunkIndex,
            ]);
        }

        // 最后一片：合并并写入文件记录
        // file_name 为空时用 file_md5 兜底
        if (!$fileName) {
            $fileName = $fileMd5 . ($fileExt ? '.' . $fileExt : '');
        }

        $res = $local->mergeChunks($fileMd5, $totalChunks, $fileName, $fileExt);

        $fileRecord = $this->service->recordUploadedFile([
            'path'        => $res['relativePath'] ?? '',
            'name'        => $res['fileName'] ?? '',
            'real_name'   => $res['realName'] ?? '',
            'file_size'   => $fileSize,
            'cate_id'     => 0,
            'update_type' => 1,
            'file_type'   => FileTypeService::getFileTypeByExtension($res['fileName']),
        ]);

        return $this->success(array_merge([
            'done'      => true,
            'file_url'  => db_config(ConfigKeyEnum::WEB_HOST) . $res['relativePath'],
            'file_path' => $res['relativePath'],
            'file_name' => $res['fileName'],
        ], $this->service->formatFileRecord($fileRecord)));
    }

    /**
     * 删除附件
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function delete()
    {
        $id = (int)($this->request->delete('id/d', 0) ?: $this->request->param('id/d', 0));
        $ids = $this->request->delete('ids', $this->request->param('ids', []));
        if (!$id && !$ids) {
            return $this->fail('缺少参数');
        }

        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }

        $deleteIds = $ids ?: [$id];
        $deleteIds = array_filter(array_map('intval', (array)$deleteIds));
        if (!$deleteIds) {
            return $this->fail('缺少参数');
        }

        foreach ($deleteIds as $fileId) {
            $fileInfo = $this->service->get($fileId, ['path', 'update_type', 'id']);
            if (!$fileInfo) {
                continue;
            }

            $upload = app('uploader')->storage($fileInfo->update_type);
            $res = $upload->delete($fileInfo->path);
            if (false === $res) {
                return $this->fail($upload->getError() ?: '删除失败');
            }

            $fileInfo->delete();
        }

        return $this->success('删除成功');
    }

    /**
     * 修改文件名
     * @param $id
     * @return Response
     */
    public function update($id)
    {
        if (!$id) {
            return $this->fail('缺少参数');
        }
        $name = $this->request->put('real_name', null);
        if ($name === null) {
            $name = $this->request->param('real_name', $this->request->post('real_name', ''));
        }

        $this->service->update($id, ['real_name' => (string)$name]);

        return $this->success('修改成功');
    }
}
