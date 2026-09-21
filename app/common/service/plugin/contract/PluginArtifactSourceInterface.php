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

namespace app\common\service\plugin\contract;

use app\common\service\plugin\dto\PluginArtifactDownloadRequest;
use app\common\service\plugin\dto\PluginArtifactDownloadResult;

/**
 * 官网制品读取端口。
 */
interface PluginArtifactSourceInterface
{
    /**
     * 下载器必须将响应写入调用方提供的临时路径，不返回长期下载令牌。
     */
    public function download(
        PluginArtifactDownloadRequest $request,
        string $targetPath
    ): PluginArtifactDownloadResult;
}
