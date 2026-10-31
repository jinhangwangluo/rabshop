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
 * 制品摘要和签名校验端口。
 */
interface PluginArtifactVerifierInterface
{
    /**
     * @return array<string,mixed> 脱敏校验结果
     */
    public function verify(
        PluginArtifactDownloadRequest $request,
        PluginArtifactDownloadResult $result
    ): array;
}
