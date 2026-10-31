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
use app\common\plugin\security\PluginJoseCodec;
use app\common\service\plugin\contract\PluginArtifactVerifierInterface;
use app\common\service\plugin\dto\PluginArtifactDownloadRequest;
use app\common\service\plugin\dto\PluginArtifactDownloadResult;

/**
 * 通过 SHA-256 摘要和 ES256 JWS 验证制品，不接受无签名或明文回退。
 */
final class OpenSslPluginArtifactVerifier implements PluginArtifactVerifierInterface
{
    /**
     * 注入制品签名使用的 JOSE 编解码器。
     *
     * @param ?PluginJoseCodec $codec JOSE 编解码器；未提供时使用默认实现。
     * @return void 无返回值。
     */
    public function __construct(private readonly ?PluginJoseCodec $codec = null)
    {
    }

    /**
     * 校验下载文件摘要、签名发布方及插件版本身份。
     *
     * @param PluginArtifactDownloadRequest $request 包含预期摘要、签名和公钥的下载请求。
     * @param PluginArtifactDownloadResult $result 实际下载结果及临时文件路径。
     * @return array 验证状态、实际摘要和签名公钥身份信息。
     */
    public function verify(
        PluginArtifactDownloadRequest $request,
        PluginArtifactDownloadResult $result
    ): array {
        if (!is_file($result->path) || !is_readable($result->path)) {
            throw new PluginLifecycleException('制品临时文件不可读', 'ARTIFACT_FILE_UNREADABLE');
        }
        $actualHash = strtolower((string)hash_file('sha256', $result->path));
        if ($actualHash === '' || !hash_equals($request->artifactSha256, $actualHash)) {
            throw new PluginLifecycleException('制品摘要校验失败', 'ARTIFACT_HASH_MISMATCH');
        }
        if ($request->signature === '' || $request->signingPublicKey === '' || $request->signingKeyId === '') {
            throw new PluginLifecycleException('制品缺少签名材料', 'ARTIFACT_SIGNATURE_REQUIRED');
        }

        $codec = $this->codec ?? new PluginJoseCodec();
        try {
            $payload = $codec->verify(
                $request->signature,
                $request->signingPublicKey,
                $request->signingKeyId
            );
        } catch (PluginLifecycleException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new PluginLifecycleException('制品签名校验失败', 'ARTIFACT_SIGNATURE_INVALID', 0, $exception);
        }
        if (!is_array($payload)
            || !hash_equals($actualHash, strtolower((string)($payload['artifact_sha256'] ?? '')))
            || (string)($payload['plugin_code'] ?? '') !== $request->pluginCode
            || (string)($payload['version'] ?? $payload['to_version'] ?? '') !== $request->toVersion
        ) {
            throw new PluginLifecycleException('制品签名载荷不匹配', 'ARTIFACT_SIGNATURE_MISMATCH');
        }

        return [
            'verified' => true,
            'sha256' => $actualHash,
            'signing_key_id' => $request->signingKeyId,
            'signing_public_key_sha256' => hash('sha256', (string)(openssl_pkey_get_details(openssl_pkey_get_public($request->signingPublicKey))['key'] ?? '')),
        ];
    }
}
