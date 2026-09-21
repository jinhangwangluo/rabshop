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

use app\common\service\plugin\contract\PluginArtifactSourceInterface;
use app\common\service\plugin\dto\PluginArtifactDownloadRequest;
use app\common\service\plugin\dto\PluginArtifactDownloadResult;
use InvalidArgumentException;

/**
 * 测试/本地模拟下载源。生产环境应由官网安全网关适配器替换。
 */
final class CallbackPluginArtifactSource implements PluginArtifactSourceInterface
{
    /**
     * @param callable(PluginArtifactDownloadRequest,string):PluginArtifactDownloadResult|array|string $callback
     */
    public function __construct(private readonly mixed $callback)
    {
        if (!is_callable($callback)) {
            throw new InvalidArgumentException('下载回调必须可调用');
        }
    }

    public function download(
        PluginArtifactDownloadRequest $request,
        string $targetPath
    ): PluginArtifactDownloadResult {
        $result = ($this->callback)($request, $targetPath);
        if ($result instanceof PluginArtifactDownloadResult) {
            return $result;
        }
        if (is_string($result)) {
            return new PluginArtifactDownloadResult(
                $result,
                is_file($result) ? (int)filesize($result) : 0,
                is_file($result) ? (string)hash_file('sha256', $result) : ''
            );
        }
        if (is_array($result)) {
            return new PluginArtifactDownloadResult(
                (string)($result['path'] ?? $targetPath),
                max(0, (int)($result['bytes'] ?? 0)),
                strtolower((string)($result['sha256'] ?? '')),
                is_array($result['metadata'] ?? null) ? $result['metadata'] : []
            );
        }
        throw new InvalidArgumentException('下载回调返回结构无效');
    }
}
