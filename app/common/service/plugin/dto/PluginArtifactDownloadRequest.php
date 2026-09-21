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

use app\common\exception\PluginLifecycleException;

/**
 * 封装受信制品下载输入，裁剪字段并避免签名材料进入任务与事件快照。
 */
final class PluginArtifactDownloadRequest extends PluginLifecycleRequest
{
    public readonly string $artifactRef;
    public readonly string $artifactUrl;
    public readonly string $signature;
    public readonly string $signingKeyId;
    public readonly string $signingPublicKey;

    /**
     * 校验制品下载引用及签名信息，生成稳定幂等身份。
     *
     * @param string $pluginCode 插件编码。
     * @param string $toVersion 目标插件版本。
     * @param string $artifactRef 开放平台制品引用。
     * @param string $artifactUrl 已授权的制品下载地址。
     * @param string $artifactSha256 预期制品 SHA-256 摘要。
     * @param string $signature 用于本地验证的 JWS 签名。
     * @param string $signingKeyId 制品签名公钥标识。
     * @param string $signingPublicKey 服务器受信的签名公钥。
     * @param string $idempotencyKey 请求幂等键；为空时按下载身份生成。
     * @param array<string,mixed> $payload 下载任务的补充非敏感载荷。
     * @param array<string,mixed>|PluginActor|null $actor 已认证操作人身份。
     * @param int $releaseId 开放平台发布记录 ID。
     * @return void 无返回值。
     */
    public function __construct(
        string $pluginCode,
        string $toVersion = '',
        string $artifactRef = '',
        string $artifactUrl = '',
        string $artifactSha256 = '',
        string $signature = '',
        string $signingKeyId = '',
        string $signingPublicKey = '',
        string $idempotencyKey = '',
        array $payload = [],
        array|PluginActor|null $actor = null,
        int $releaseId = 0
    ) {
        if ($artifactRef === '' && $artifactUrl === '') {
            throw new PluginLifecycleException('缺少制品引用', 'ARTIFACT_REFERENCE_REQUIRED');
        }
        if ($artifactUrl !== '' && !filter_var($artifactUrl, FILTER_VALIDATE_URL)) {
            throw new PluginLifecycleException('制品地址无效', 'INVALID_ARTIFACT_URL');
        }
        if ($signature !== '' && strlen($signature) > 20000) {
            throw new PluginLifecycleException('制品签名过长', 'INVALID_ARTIFACT_SIGNATURE');
        }
        parent::__construct(
            $pluginCode,
            '',
            $toVersion,
            $releaseId,
            $artifactSha256,
            'retain',
            $idempotencyKey !== '' ? $idempotencyKey : 'download:' . substr(hash('sha256', implode('|', [
                $pluginCode, $toVersion, trim($artifactRef), trim($artifactUrl), strtolower($artifactSha256),
            ])), 0, 48),
            $payload,
            $actor
        );
        $this->artifactRef = mb_substr(trim($artifactRef), 0, 190);
        $this->artifactUrl = mb_substr(trim($artifactUrl), 0, 500);
        $this->signature = $signature;
        $this->signingKeyId = mb_substr(trim($signingKeyId), 0, 64);
        $this->signingPublicKey = $signingPublicKey;
    }

    /**
     * 将字段数组转换为下载请求，调用方固定的插件编码优先。
     *
     * @param array<string,mixed> $data 待归一化的下载请求字段。
     * @param string|null $pluginCode 由调用方固定的插件编码。
     * @return self 已校验的制品下载请求实例。
     */
    public static function fromArray(array $data, ?string $pluginCode = null): self
    {
        return new self(
            $pluginCode ?? (string)($data['plugin_code'] ?? ''),
            (string)($data['to_version'] ?? $data['version'] ?? ''),
            (string)($data['artifact_ref'] ?? ''),
            (string)($data['artifact_url'] ?? ''),
            strtolower(trim((string)($data['artifact_sha256'] ?? ''))),
            (string)($data['signature'] ?? ''),
            (string)($data['signing_key_id'] ?? ''),
            (string)($data['signing_public_key'] ?? ''),
            (string)($data['idempotency_key'] ?? ''),
            is_array($data['payload'] ?? null) ? $data['payload'] : [],
            $data['actor'] ?? null,
            max(0, (int)($data['release_id'] ?? 0))
        );
    }

    /**
     * 导出可持久化任务字段，排除仅用于本地校验的签名材料。
     *
     * @return array<string,mixed> 下载任务安全快照字段。
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'artifact_ref' => $this->artifactRef,
            'artifact_url' => $this->artifactUrl,
            // 签名仅用于本地校验，不进入任务/事件快照。
            'signing_key_id' => $this->signingKeyId,
        ]);
    }
}
