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

use app\common\enum\UploaderConfigKeyEnum;
use app\common\enum\UploaderWatermarkEnum;
use app\common\service\setting\UploaderWatermarkConfigService;
use app\controller\admin\AuthController;
use think\annotation\Inject;

/**
 * 上传水印配置控制器。
 */
class UploaderWatermark extends AuthController
{
    /**
     * 上传水印配置服务。
     */
    #[Inject]
    protected UploaderWatermarkConfigService $service;

    /**
     * 获取上传与水印配置。
     */
    public function index()
    {
        return $this->success($this->service->detail());
    }

    /**
     * 保存上传与水印配置。
     */
    public function save()
    {
        $data = $this->request->postMore([
            [UploaderConfigKeyEnum::UPLOADER_TYPE, 'local'],
            [UploaderConfigKeyEnum::QINIU_ACCESS_KEY, ''],
            [UploaderConfigKeyEnum::QINIU_SECRET_KEY, ''],
            [UploaderConfigKeyEnum::QINIU_NAME, ''],
            [UploaderConfigKeyEnum::QINIU_REGION, ''],
            [UploaderConfigKeyEnum::QINIU_URL, ''],
            [UploaderConfigKeyEnum::OSS_ACCESS_KEY, ''],
            [UploaderConfigKeyEnum::OSS_SECRET_KEY, ''],
            [UploaderConfigKeyEnum::OSS_NAME, ''],
            [UploaderConfigKeyEnum::OSS_REGION, ''],
            [UploaderConfigKeyEnum::OSS_URL, ''],
            [UploaderConfigKeyEnum::COS_ACCESS_KEY, ''],
            [UploaderConfigKeyEnum::COS_SECRET_KEY, ''],
            [UploaderConfigKeyEnum::COS_APPID, ''],
            [UploaderConfigKeyEnum::COS_NAME, ''],
            [UploaderConfigKeyEnum::COS_REGION, ''],
            [UploaderConfigKeyEnum::COS_URL, ''],
            [UploaderConfigKeyEnum::COS_CDN, ''],
            [UploaderWatermarkEnum::WATERMARK_ENABLED, 0],
            [UploaderWatermarkEnum::WATERMARK_TYPE, 'image'],
            [UploaderWatermarkEnum::WATERMARK_POSITION, 'top_left'],
            [UploaderWatermarkEnum::WATERMARK_X_OFFSET, 0],
            [UploaderWatermarkEnum::WATERMARK_Y_OFFSET, 0],
            [UploaderWatermarkEnum::WATERMARK_IMAGE, ''],
            [UploaderWatermarkEnum::WATERMARK_OPACITY, 80],
            [UploaderWatermarkEnum::WATERMARK_ANGLE, 0],
            [UploaderWatermarkEnum::WATERMARK_TEXT, ''],
            [UploaderWatermarkEnum::WATERMARK_TEXT_SIZE, 16],
            [UploaderWatermarkEnum::WATERMARK_TEXT_COLOR, '#000000'],
            [UploaderWatermarkEnum::WATERMARK_TEXT_ANGLE, 0],
        ]);

        return $this->success('保存成功', $this->service->save($data));
    }
}
