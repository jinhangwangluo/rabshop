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

declare(strict_types=1);

namespace app\controller\mobile;

use app\BaseController;
use app\common\enum\ConfigKeyEnum;
use app\common\enum\UploaderConfigKeyEnum;
use app\common\service\setting\FileService;
use app\common\util\FileTypeService;
use OSS\Core\OssException;
use Rabbit\service\uploader\UploaderService;
use think\annotation\Inject;
use think\Response;

/**
 * 移动端文件上传控制器.
 */
class File extends BaseController
{
    /**
     * 附件服务.
     *
     * @var FileService
     */
    #[Inject]
    protected FileService $service;

    /**
     * 上传移动端评价图片.
     *
     * POST 参数：
     * - fileName string 文件表单字段名，默认 file。
     *
     * @return Response 返回图片地址与上传信息。
     * @throws OssException
     */
    public function upload(): Response
    {
        $userId = (int)($this->request->user_id ?? 0);
        if (!$userId) {
            return $this->fail('请登录');
        }
        $file = trim((string)$this->request->post('fileName', 'file'));
        if ($file === '') {
            $file = 'file';
        }

        $upload = app('uploader')->storage();
        $res = $upload->to('file/mobile/review/' . $userId . '/' . date('Y'))->validate()->move($file);

        if (false === $res) {
            return $this->fail($upload->getError());
        }

        $updateType = match ($res['updateType']) {
            UploaderService::LOCAL_UPLOADER => 1,
            UploaderService::COS_UPLOADER => 3,
            UploaderService::OSS_UPLOADER => 4,
            UploaderService::QINIU_UPLOADER => 2,
            default => 1,
        };

        $path = (string)($res['filePath'] ?? '');
        $url = $path;
        if ($updateType === 1) {
            $url = db_config(ConfigKeyEnum::WEB_HOST) . $path;
        }

        $this->service->save([
            'path' => $path,
            'name' => (string)($res['fileName'] ?? ''),
            'real_name' => (string)($res['realName'] ?? ''),
            'cate_id' => 0,
            'update_type' => $updateType,
            'file_type' => FileTypeService::getFileTypeByExtension((string)($res['fileName'] ?? '')),
        ]);

        return $this->success('上传成功', [
            'url' => $url,
            'path' => $path,
            'filePath' => $url,
            'name' => (string)($res['fileName'] ?? ''),
            'realName' => (string)($res['realName'] ?? ''),
            'updateType' => $updateType,
            'uploaderType' => db_config(UploaderConfigKeyEnum::UPLOADER_TYPE) ?: UploaderService::LOCAL_UPLOADER,
        ]);
    }
}
