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

namespace app\common\service\setting;

use app\common\enum\ConfigKeyEnum;
use app\common\enum\UploaderConfigKeyEnum;
use app\common\model\setting\system\File;
use app\common\service\BaseService;
use app\common\util\FileTypeService;
use Rabbit\service\uploader\UploaderService;
use think\Model;
use think\model\contract\Modelable;

/**
 * 附件管理
 * Class FileService
 * @package app\common\service\setting
 */
class FileService extends BaseService
{

    /**
     * 设置模型
     * @return string
     */
    protected function setModel(): string
    {
        return File::class;
    }

    /**
     * 将上传器标识转换为 file.update_type.
     *
     * @param string|int $updateType 上传器标识或已有数字类型.
     * @return int
     */
    public function resolveUpdateType(string|int $updateType): int
    {
        if (is_numeric($updateType)) {
            return (int)$updateType;
        }

        return match ($updateType) {
            UploaderService::LOCAL_UPLOADER => 1,
            UploaderService::COS_UPLOADER => 3,
            UploaderService::OSS_UPLOADER => 4,
            UploaderService::QINIU_UPLOADER => 2,
            default => 1,
        };
    }

    /**
     * 登记已上传文件，path + update_type + merchant_id 相同时复用原记录.
     *
     * @param array $data 上传结果字段.
     * @param int $merchantId 商户ID，平台端固定 0.
     * @return File
     */
    public function recordUploadedFile(array $data, int $merchantId = 0): File
    {
        $updateType = $this->resolveUpdateType($data['update_type'] ?? $data['updateType'] ?? 1);
        $path = $this->normalizeStoredPath(
            (string)($data['path'] ?? $data['file_path'] ?? $data['filePath'] ?? $data['url'] ?? ''),
            $updateType
        );
        $name = trim((string)($data['name'] ?? $data['file_name'] ?? ''));
        $realName = trim((string)($data['real_name'] ?? $data['realName'] ?? ''));

        if ($name === '') {
            $name = $this->basenameFromPath($path);
        }

        if ($path === '' && $name !== '') {
            $path = $this->normalizeStoredPath($name, $updateType);
        }

        if ($realName === '') {
            $realName = $name;
        }

        $fileType = isset($data['file_type']) && $data['file_type'] !== ''
            ? (int)$data['file_type']
            : FileTypeService::getFileTypeByExtension($realName ?: $name ?: $path);

        $exists = $this->getModel()
            ->where('path', $path)
            ->where('update_type', $updateType)
            ->where('merchant_id', $merchantId)
            ->find();

        if ($exists) {
            return $exists;
        }

        return $this->create([
            'path'        => $path,
            'name'        => $name,
            'real_name'   => $realName,
            'file_size'   => (string)($data['file_size'] ?? ''),
            'cate_id'     => (int)($data['cate_id'] ?? 0),
            'merchant_id' => $merchantId,
            'update_type' => $updateType,
            'file_type'   => $fileType,
        ]);
    }

    /**
     * 格式化附件记录，返回前端通用字段.
     *
     * @param File|Model|array $file 附件记录.
     * @return array
     */
    public function formatFileRecord(File|Model|array $file): array
    {
        $data = $file instanceof Model ? $file->toArray() : $file;
        $id = (int)($data['id'] ?? 0);
        $updateType = (int)($data['update_type'] ?? 1);
        $storedPath = (string)($data['path'] ?? '');
        $displayPath = $this->buildDisplayPath($storedPath, $updateType);

        $data['id'] = $id;
        $data['file_id'] = $id;
        $data['path'] = $displayPath;
        $data['filePath'] = $displayPath;
        $data['file_url'] = $displayPath;
        $data['file_path'] = $storedPath;
        $data['update_type'] = $updateType;
        $data['file_type'] = (int)($data['file_type'] ?? FileTypeService::TYPE_IMAGE);
        $data['file_type_name'] = FileTypeService::getFileTypeName((int)$data['file_type']);

        return $data;
    }

    /**
     * 获取平台端后台附件列表，隐藏移动端用户上传但保留记录.
     *
     * @param array $where 查询条件.
     * @param string|null $sort 排序.
     * @return FileService
     */
    public function getAdminVisibleList(array $where = [], string|null $sort = null): self
    {
        $where['hide_user_upload'] = 1;

        return $this->getDefaultList(where: $where, sort: $sort);
    }

    /**
     * 移动图片
     * @param array $imageIds
     * @param int $cateId
     * @return Modelable
     */
    public function move(array $imageIds, int $cateId)
    {
        return $this->getModel()->whereIn('id', $imageIds)->update(['cate_id' => $cateId]);
    }

    /**
     * 获取商户文件列表
     * @param int $merchantId
     * @param array $where
     * @param string|null $sort
     * @return FileService
     */
    public function getMerchantList(int $merchantId, array $where = [], string|null $sort = null)
    {
        // 强制过滤商户ID
        $where['merchant_id'] = $merchantId;
        return $this->getDefaultList(where: $where, sort: $sort ?: 'id');
    }

    /**
     * 保存文件（带商户ID）
     * @param array $data
     * @param int $merchantId
     * @return File
     */
    public function saveWithMerchant(array $data, int $merchantId): File
    {
        $data['merchant_id'] = $merchantId;
        return $this->create($data);
    }

    /**
     * 删除商户文件（校验所有权）
     * @param int $id
     * @param int $merchantId
     * @return bool
     */
    public function deleteMerchantFile(int $id, int $merchantId): bool
    {
        $file = $this->getModel()->where('id', $id)
            ->where('merchant_id', $merchantId)
            ->find();
        
        if (!$file) {
            return false;
        }
        
        $file->delete();
        return true;
    }

    /**
     * 生成前端可访问地址.
     *
     * @param string $path 数据库存储路径.
     * @param int $updateType 上传类型.
     * @return string
     */
    protected function buildDisplayPath(string $path, int $updateType): string
    {
        if ($path === '') {
            return $path;
        }

        if (str_starts_with($path, '//')) {
            return 'https:' . $path;
        }

        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }

        if ($updateType === 1) {
            $baseUrl = $this->normalizeDisplayBaseUrl((string)db_config(ConfigKeyEnum::WEB_HOST));
            return $baseUrl ? $baseUrl . '/' . ltrim($path, '/') : $path;
        }

        $baseUrl = $this->normalizeDisplayBaseUrl(match ($updateType) {
            2 => (string)db_config(UploaderConfigKeyEnum::QINIU_URL),
            3 => (string)(db_config(UploaderConfigKeyEnum::COS_CDN) ?: db_config(UploaderConfigKeyEnum::COS_URL)),
            4 => (string)db_config(UploaderConfigKeyEnum::OSS_URL),
            default => '',
        });

        return $baseUrl ? rtrim($baseUrl, '/') . '/' . ltrim($path, '/') : $path;
    }

    /**
     * 规范化前端展示地址的协议.
     *
     * 裸域名和协议相对地址默认使用 HTTPS，已显式配置 http/https 的地址保持原样。
     *
     * @param string $baseUrl 文件访问域名.
     * @return string
     */
    protected function normalizeDisplayBaseUrl(string $baseUrl): string
    {
        $baseUrl = trim($baseUrl);
        if ($baseUrl === '') {
            return '';
        }

        if (str_starts_with($baseUrl, '//')) {
            return 'https:' . rtrim($baseUrl, '/');
        }

        if (!preg_match('/^https?:\/\//i', $baseUrl)) {
            return 'https://' . ltrim(rtrim($baseUrl, '/'), '/');
        }

        return rtrim($baseUrl, '/');
    }

    /**
     * 云直传可能传入完整访问地址，本地地址保存相对路径，云存储保存对象 Key.
     *
     * @param string $path 原始路径.
     * @return string
     */
    protected function normalizeStoredPath(string $path, int $updateType = 1): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, '//')) {
            $path = 'https:' . $path;
        }

        foreach ($this->getStorageBaseUrls($updateType) as $baseUrl) {
            if (str_starts_with($path, $baseUrl . '/')) {
                $relativePath = substr($path, strlen($baseUrl));
                return $this->formatStoredPath($relativePath, $updateType);
            }
        }

        if (preg_match('/^https?:\/\//i', $path)) {
            $urlPath = (string)(parse_url($path, PHP_URL_PATH) ?: '');
            return $this->formatStoredPath($urlPath, $updateType);
        }

        return $this->formatStoredPath($path, $updateType);
    }

    /**
     * 获取当前上传类型可能配置的访问根地址.
     *
     * @param int $updateType 上传类型.
     * @return array
     */
    protected function getStorageBaseUrls(int $updateType): array
    {
        $urls = match ($updateType) {
            2 => [(string)db_config(UploaderConfigKeyEnum::QINIU_URL)],
            3 => [
                (string)db_config(UploaderConfigKeyEnum::COS_CDN),
                (string)db_config(UploaderConfigKeyEnum::COS_URL),
            ],
            4 => [(string)db_config(UploaderConfigKeyEnum::OSS_URL)],
            default => [(string)db_config(ConfigKeyEnum::WEB_HOST)],
        };

        $result = [];
        foreach ($urls as $url) {
            $url = trim($url);
            if ($url === '') {
                continue;
            }
            $result[] = rtrim($url, '/');
            if (str_starts_with($url, '//')) {
                $result[] = 'https:' . rtrim($url, '/');
            } elseif (!preg_match('/^https?:\/\//i', $url)) {
                $result[] = 'https://' . rtrim($url, '/');
                $result[] = 'http://' . rtrim($url, '/');
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * 本地文件保留 /storage 前导斜杠，云存储保存对象 key.
     *
     * @param string $path 路径或对象 key.
     * @param int $updateType 上传类型.
     * @return string
     */
    protected function formatStoredPath(string $path, int $updateType): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        if ($updateType === 1) {
            return '/' . ltrim($path, '/');
        }

        return ltrim($path, '/');
    }

    /**
     * 从路径中提取文件名.
     *
     * @param string $path 文件路径.
     * @return string
     */
    protected function basenameFromPath(string $path): string
    {
        $urlPath = (string)(parse_url($path, PHP_URL_PATH) ?: $path);
        return basename($urlPath);
    }
}
