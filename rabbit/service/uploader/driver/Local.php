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
use Rabbit\service\uploader\BaseDriver;
use Rabbit\service\uploader\UploaderService;
use think\exception\ValidateException;
use think\facade\Config;
use think\facade\Filesystem;
use think\File;

/**
 * 本地上传
 * Class Local
 * @package dengfenglai\service\uploader\driver
 */
class Local extends BaseDriver
{
    /**
     * 允许的分片上传文件后缀白名单
     */
    private const CHUNK_ALLOW_EXT = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'mp3', 'wav', 'aac', 'flac', 'm4a', 'ogg', 'webm'];

    /**
     * 单次合并文件最大体积（字节），默认 4 GB
     */
    private const CHUNK_MAX_MERGED_SIZE = 4 * 1024 * 1024 * 1024;

    /**
     * 默认存放路径
     * @var string
     */
    protected string $defaultPath;


    public function __construct()
    {
        $this->defaultPath = Config::get('filesystem.disks.' . Config::get('filesystem.default') . '.url');
    }

    /**
     * 生成上传文件目录
     * @param $path
     * @param null $root
     * @return string
     */
    public function uploadDir($path, $root = null)
    {
        if ($root === null)
            $root = app()->getRootPath() . 'public/';
        return str_replace('\\', '/', $root . 'storage/' . $path);
    }

    /**
     * 检查上传目录不存在则生成
     * @param $dir
     * @return bool
     */
    protected function validDir($dir)
    {
        return is_dir($dir) == true || mkdir($dir, 0777, true) == true;
    }


    /**
     * 文件上传
     * @param string $file
     * @return array|bool
     */
    public function move(string $file = 'file'): array|bool
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

        $preparedFile = $this->prepareWatermarkUploadFile($fileHandle);
        try {
            $fileName = Filesystem::putFileAs(
                $this->path,
                new File((string)$preparedFile['path']),
                strtolower($fileHandle->hashName())
            );
        } finally {
            $this->cleanupWatermarkUploadFile($preparedFile);
        }

        if (!$fileName) {
            throw new UploaderException('Upload failure');
        }
        $filePath = Filesystem::path($fileName);
        $fileInfo['uploadInfo'] = new File($filePath);
        $fileInfo['realName'] = $fileHandle->getOriginalName();
        $fileInfo['updateType'] = UploaderService::LOCAL_UPLOADER;
        $fileInfo['fileName'] = $fileInfo['uploadInfo']->getFilename();
        $fileInfo['filePath'] = $this->defaultPath . '/' . str_replace('\\', '/', $fileName);
        return $fileInfo;
    }

    /**
     * 文件流上传
     * @param string $fileContent
     * @param string|null $key
     * @return array
     */
    public function stream(string $fileContent, string $key = null): array|bool
    {
        if (!$key) {
            $key = $this->getSaveFileName();
        }
        $dir = $this->uploadDir($this->path);
        if (!$this->validDir($dir)) {
            throw new UploaderException('Failed to generate upload directory, please check the permission!');
        }
        $fileName = $dir . '/' . $key;
        if (file_put_contents($fileName, $fileContent) === false) {
            throw new UploaderException('Upload failure');
        }
        $fileInfo['uploadInfo'] = new File($fileName);
        $fileInfo['realName'] = $key;
        $fileInfo['fileName'] = $key;
        $fileInfo['updateType'] = UploaderService::LOCAL_UPLOADER;
        $fileInfo['filePath'] = $this->defaultPath . '/' . $this->path . '/' . $key;
        return $fileInfo;
    }


    /**
     * 删除文件
     * @param string $filePath
     * @return bool|mixed
     */
    public function delete(string $filePath)
    {
        $filePath = public_path() . $filePath;
        if (file_exists($filePath)) {
            try {
                unlink($filePath);
                return true;
            } catch (UploaderException $e) {
                throw new UploaderException($e->getMessage());
            }
        }
        throw new UploaderException('文件不存在无法删除');
    }

    /**
     * 查询已上传的分片列表（断点续传探测）
     *
     * @param string $fileMd5    文件 MD5（仅允许 [a-f0-9] 32位，防目录穿越）
     * @param int    $totalChunks 总分片数
     * @return int[]             已上传的分片索引列表
     * @throws UploaderException
     */
    public function queryChunks(string $fileMd5, int $totalChunks): array
    {
        $this->validateFileMd5($fileMd5);

        $chunkDir = $this->getChunkDir($fileMd5);
        $uploaded = [];

        if (is_dir($chunkDir)) {
            for ($i = 0; $i < $totalChunks; $i++) {
                if (file_exists($chunkDir . '/chunk_' . $i)) {
                    $uploaded[] = $i;
                }
            }
        }

        return $uploaded;
    }

    /**
     * 保存单个分片
     *
     * @param string    $fileMd5     文件 MD5
     * @param int       $chunkIndex  当前分片索引（0 起）
     * @param int       $totalChunks 总分片数
     * @param \think\File $chunkData 分片文件对象
     * @throws UploaderException
     */
    public function saveChunk(string $fileMd5, int $chunkIndex, int $totalChunks, \think\File $chunkData): void
    {
        $this->validateFileMd5($fileMd5);
        $this->validateChunkIndex($chunkIndex, $totalChunks);

        $chunkDir  = $this->getChunkDir($fileMd5);
        $chunkFile = $chunkDir . '/chunk_' . $chunkIndex;

        if (!is_dir($chunkDir)) {
            mkdir($chunkDir, 0755, true);
        }

        // 分片已存在则跳过（断点续传）
        if (file_exists($chunkFile)) {
            return;
        }

        $chunkData->move($chunkDir, 'chunk_' . $chunkIndex);
    }

    /**
     * 合并所有分片，生成最终文件
     *
     * @param string $fileMd5     文件 MD5
     * @param int    $totalChunks 总分片数
     * @param string $fileName    原始文件名（用于记录）
     * @param string $fileExt     文件后缀（仅白名单内允许）
     * @return array{filePath:string, fileName:string, realName:string, relativePath:string}
     * @throws UploaderException
     */
    public function mergeChunks(string $fileMd5, int $totalChunks, string $fileName, string $fileExt): array
    {
        $this->validateFileMd5($fileMd5);
        $fileExt = strtolower($fileExt) ?: 'mp4';
        $this->validateChunkExt($fileExt);

        $chunkDir = $this->getChunkDir($fileMd5);

        // 检查所有分片是否存在
        for ($i = 0; $i < $totalChunks; $i++) {
            if (!file_exists($chunkDir . '/chunk_' . $i)) {
                throw new UploaderException('分片不完整，缺少第 ' . $i . ' 片');
            }
        }

        $mergedDir    = app()->getRootPath() . 'public/storage/media/' . date('Y/m');
        $saveKey      = md5($fileMd5 . time()) . '.' . $fileExt;
        $savePath     = $mergedDir . '/' . $saveKey;
        $relativePath = '/storage/media/' . date('Y/m') . '/' . $saveKey;

        if (!is_dir($mergedDir)) {
            mkdir($mergedDir, 0755, true);
        }

        // 合并前预估总大小，防止超限
        $totalSize = 0;
        for ($i = 0; $i < $totalChunks; $i++) {
            $totalSize += filesize($chunkDir . '/chunk_' . $i);
            if ($totalSize > self::CHUNK_MAX_MERGED_SIZE) {
                throw new UploaderException('文件总大小超出限制（最大 4 GB）');
            }
        }

        $fp = fopen($savePath, 'wb');
        if ($fp === false) {
            throw new UploaderException('无法创建目标文件，请检查目录权限');
        }

        try {
            for ($i = 0; $i < $totalChunks; $i++) {
                $chunkContent = file_get_contents($chunkDir . '/chunk_' . $i);
                if ($chunkContent === false) {
                    throw new UploaderException('读取分片失败：chunk_' . $i);
                }
                fwrite($fp, $chunkContent);
            }
        } finally {
            fclose($fp);
        }

        // 清理临时分片目录
        array_map('unlink', glob($chunkDir . '/chunk_*') ?: []);
        @rmdir($chunkDir);

        return [
            'filePath'     => $this->defaultPath . $relativePath,
            'fileName'     => $saveKey,
            'realName'     => $fileName ?: $saveKey,
            'relativePath' => $relativePath,
            'updateType'   => UploaderService::LOCAL_UPLOADER,
        ];
    }

    /**
     * 校验 fileMd5 格式（32 位小写十六进制），防止目录穿越攻击
     *
     * @throws UploaderException
     */
    private function validateFileMd5(string $fileMd5): void
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $fileMd5)) {
            throw new UploaderException('非法的 file_md5 参数');
        }
    }

    /**
     * 校验分片索引合法性
     *
     * @throws UploaderException
     */
    private function validateChunkIndex(int $chunkIndex, int $totalChunks): void
    {
        if ($chunkIndex < 0 || $chunkIndex >= $totalChunks) {
            throw new UploaderException('非法的分片索引：' . $chunkIndex);
        }
    }

    /**
     * 校验文件后缀是否在白名单内
     *
     * @throws UploaderException
     */
    private function validateChunkExt(string $ext): void
    {
        if (!in_array($ext, self::CHUNK_ALLOW_EXT, true)) {
            throw new UploaderException('不支持的文件类型：' . $ext);
        }
    }

    /**
     * 获取临时分片目录绝对路径
     */
    private function getChunkDir(string $fileMd5): string
    {
        return app()->getRootPath() . 'public/storage/chunks/' . $fileMd5;
    }
}
