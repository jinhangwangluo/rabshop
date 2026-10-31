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

namespace app\common\service\plugin\dto;

/**
 * 下载器返回的本地文件结果。
 */
final class PluginArtifactDownloadResult
{
    public function __construct(
        public readonly string $path,
        public readonly int $bytes = 0,
        public readonly string $sha256 = '',
        public readonly array $metadata = []
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'bytes' => $this->bytes,
            'sha256' => $this->sha256,
            'metadata' => $this->metadata,
        ];
    }
}
