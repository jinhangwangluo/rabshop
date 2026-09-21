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

namespace app\controller\merchant\setting;

use app\common\enum\ConfigKeyEnum;
use app\common\enum\UploaderConfigKeyEnum;
use app\common\service\setting\FileService;
use app\common\util\FileTypeService;
use app\controller\merchant\AuthController;
use OSS\Core\OssException;
use Rabbit\service\uploader\UploaderService;
use think\annotation\Inject;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Response;

/**
 * 商户端附件管理
 * Class File
 * @package app\controller\merchant\setting
 */
class File extends AuthController
{
    /**
     * @var FileService
     */
    #[Inject]
    protected FileService $service;

    /**
     * 获取文件列表
     * @return Response
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function list(): Response
    {
        $where = $this->request->getMore([
            ['name', '', 'name_like'],
            ['cate_id', ''],
        ]);
        
        $list = $this->service->getMerchantList($this->merchantId, $where)
            ->each(function ($item) {
                if ($item['update_type'] === 1) {
                    $item['path'] = db_config(ConfigKeyEnum::WEB_HOST) . $item['path'];
                }
                $item['file_type_name'] = FileTypeService::getFileTypeName((int)$item['file_type']);
                return $item;
            });
        
        return $this->success($list);
    }

    /**
     * 上传文件
     * @return Response
     * @throws OssException
     */
    public function upload(): Response
    {
        $cateId = $this->request->post('cate_id', 0);
        $file = $this->request->post('fileName', 'file');

        $upload = app('uploader')->storage();
        $res = $upload->to('file/merchant/' . $this->merchantId . '/' . date('Y'))->validate()->move($file);

        if (false === $res) {
            return $this->fail($upload->getError());
        }

        $updateType = match ($res['updateType']) {
            UploaderService::LOCAL_UPLOADER => 1,
            UploaderService::COS_UPLOADER => 3,
            UploaderService::OSS_UPLOADER => 4,
            UploaderService::QINIU_UPLOADER => 2,
            default => 1
        };

        $fileType = FileTypeService::getFileTypeByExtension($res['fileName']);

        $fileRecord = $this->service->saveWithMerchant([
            'path'        => $res['filePath'],
            'name'        => $res['fileName'],
            'real_name'   => $res['realName'],
            'cate_id'     => $cateId,
            'update_type' => $updateType,
            'file_type'   => $fileType,
        ], $this->merchantId);

        $res['id'] = (int)$fileRecord->id;
        $res['file_id'] = (int)$fileRecord->id;
        $res['path'] = $res['filePath'];
        $res['name'] = $res['fileName'];
        $res['real_name'] = $res['realName'];

        if (1 === $updateType) {
            $res['filePath'] = db_config(ConfigKeyEnum::WEB_HOST) . $res['filePath'];
            $res['path'] = $res['filePath'];
        }

        return $this->success($res);
    }

    /**
     * 获取上传配置（上传类型及对应签名配置）。
     */
    public function getUploadConfig(): mixed
    {
        $uploaderType = db_config(UploaderConfigKeyEnum::UPLOADER_TYPE) ?: UploaderService::LOCAL_UPLOADER;

        $config = [
            'type'      => $uploaderType,
            'chunk_url' => '/api/merchant/setting/file/chunk_upload',
        ];

        switch ($uploaderType) {
            case UploaderService::COS_UPLOADER:
                $config['cos'] = [
                    'bucket'     => db_config(UploaderConfigKeyEnum::COS_NAME),
                    'region'     => db_config(UploaderConfigKeyEnum::COS_REGION),
                    'cdn'        => db_config(UploaderConfigKeyEnum::COS_CDN),
                    'url'        => db_config(UploaderConfigKeyEnum::COS_URL),
                    'upload_url' => 'https://' . db_config(UploaderConfigKeyEnum::COS_NAME) . '.cos.' . db_config(UploaderConfigKeyEnum::COS_REGION) . '.myqcloud.com',
                ];
                break;

            case UploaderService::OSS_UPLOADER:
                $host = 'https://' . db_config(UploaderConfigKeyEnum::OSS_NAME) . '.' . db_config(UploaderConfigKeyEnum::OSS_REGION);
                $expire = time() + 3600;
                $expiration = gmdate('Y-m-d\TH:i:s\Z', $expire);
                $dir = 'file/media/' . date('Y/m') . '/';
                $policy = base64_encode(json_encode([
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
                $auth = new \Qiniu\Auth(db_config(UploaderConfigKeyEnum::QINIU_ACCESS_KEY), db_config(UploaderConfigKeyEnum::QINIU_SECRET_KEY));
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
                break;
        }

        return $this->success($config);
    }

    /**
     * 本地分片上传（支持断点续传）。
     */
    public function chunkUpload(): mixed
    {
        $fileMd5 = $this->request->post('file_md5', '');
        $chunkIndex = (int)$this->request->post('chunk_index', 0);
        $totalChunks = (int)$this->request->post('total_chunks', 1);
        $fileName = $this->request->post('file_name', '');
        $fileExt = strtolower($this->request->post('file_ext', ''));

        if (!$fileMd5) {
            return $this->fail('缺少必要参数 file_md5');
        }

        /** @var \Rabbit\service\uploader\driver\Local $local */
        $local = app('uploader')->storage(UploaderService::LOCAL_UPLOADER);

        if ($chunkIndex === -1) {
            return $this->success([
                'done'            => false,
                'uploaded_chunks' => $local->queryChunks($fileMd5, $totalChunks),
            ]);
        }

        $chunkData = $this->request->file('chunk_data');
        if (!$chunkData) {
            return $this->fail('分片数据不能为空');
        }
        $local->saveChunk($fileMd5, $chunkIndex, $totalChunks, $chunkData);

        if ($chunkIndex + 1 < $totalChunks) {
            return $this->success([
                'done'            => false,
                'uploaded_chunks' => $local->queryChunks($fileMd5, $totalChunks),
                'chunk_index'     => $chunkIndex,
            ]);
        }

        if (!$fileName) {
            $fileName = $fileMd5 . ($fileExt ? '.' . $fileExt : '');
        }

        $res = $local->mergeChunks($fileMd5, $totalChunks, $fileName, $fileExt);
        $fileRecord = $this->service->saveWithMerchant([
            'path'        => $res['relativePath'],
            'name'        => $res['fileName'],
            'real_name'   => $res['realName'],
            'cate_id'     => 0,
            'update_type' => 1,
            'file_type'   => FileTypeService::getFileTypeByExtension($res['fileName']),
        ], (int)$this->merchantId);

        $fileUrl = db_config(ConfigKeyEnum::WEB_HOST) . $res['relativePath'];

        return $this->success([
            'done'      => true,
            'file_url'  => $fileUrl,
            'file_path' => $res['relativePath'],
            'file_name' => $res['fileName'],
            'id'        => (int)$fileRecord->id,
            'file_id'   => (int)$fileRecord->id,
            'path'      => $fileUrl,
            'name'      => $res['fileName'],
            'real_name' => $res['realName'],
        ]);
    }

    /**
     * 删除附件
     * @return Response
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function delete(): Response
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
        
        foreach ($deleteIds as $fileId) {
            $fileInfo = $this->service->getModel()
                ->where('id', $fileId)
                ->where('merchant_id', $this->merchantId)
                ->find(['path', 'update_type', 'id']);
            
            if (!$fileInfo) {
                continue;
            }

            $upload = app('uploader')->storage($fileInfo->update_type);
            $upload->delete($fileInfo->path);
            $fileInfo->delete();
        }

        return $this->success('删除成功');
    }

    /**
     * 修改文件名
     * @param int $id
     * @return Response
     */
    public function update(int $id): Response
    {
        if (!$id) {
            return $this->fail('缺少参数');
        }
        
        $name = $this->request->put('real_name', null);
        if ($name === null) {
            $name = $this->request->param('real_name', $this->request->post('real_name', ''));
        }
        $name = (string)$name;

        // 校验文件所有权
        $file = $this->service->getModel()
            ->where('id', $id)
            ->where('merchant_id', $this->merchantId)
            ->find();
        
        if (!$file) {
            return $this->fail('文件不存在');
        }

        $this->service->update($id, ['real_name' => $name]);

        return $this->success('修改成功');
    }

    /**
     * 获取七牛云前端直传域名。
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
}
