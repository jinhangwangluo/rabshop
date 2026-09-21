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

namespace app\common\service\plugin;

use app\common\exception\PluginLifecycleException;
use app\common\service\plugin\contract\PluginArtifactSourceInterface;
use app\common\service\plugin\dto\PluginArtifactDownloadRequest;
use app\common\service\plugin\dto\PluginArtifactDownloadResult;

/**
 * 官网 API 尚未接入时的显式占位实现。
 */
final class UnavailablePluginArtifactSource implements PluginArtifactSourceInterface
{
    public function download(
        PluginArtifactDownloadRequest $request,
        string $targetPath
    ): PluginArtifactDownloadResult {
        throw new PluginLifecycleException(
            '官网制品下载端口尚未配置',
            'ARTIFACT_SOURCE_UNAVAILABLE'
        );
    }
}
