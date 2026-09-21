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

namespace app\middleware;

use app\Request;
use app\common\exception\PluginSecurityException;
use app\common\plugin\security\PluginRequestCrypto;
use app\common\plugin\security\PluginRequestEnvelope;
use app\common\service\plugin\PluginKeyService;
use Closure;
use OpenSSLAsymmetricKey;
use Throwable;

/**
 * 插件域应用层加密请求中间件。
 *
 * 请求体必须是 application/jose。中间件先解密 JWE，再按受保护头中的
 * `kid` 选择验签公钥（若载荷是嵌套 JWS），最后校验信封时效、nonce 和
 * 路由绑定，并把已验证信封挂到 Request；任何失败都在进入 Controller
 * 前以统一 PluginSecurityException 终止。
 */
final class PluginEncryptedRequestMiddleware
{
    /** @var list<string>|array<string,mixed> */
    private array $allowedRoutes;
    private PluginKeyService $keys;
    private PluginRequestCrypto $crypto;
    private bool $requireSignature;
    private string|OpenSSLAsymmetricKey|null $decryptionPrivateKey;
    private ?string $decryptionKeyId;
    private string|OpenSSLAsymmetricKey|null $senderPublicKey;

    /**
     * @param list<string>|array<string,mixed> $allowedRoutes 路由白名单，元素可为 `METHOD /path` 或 `/path`。
     * @param string|OpenSSLAsymmetricKey|null $decryptionPrivateKey 测试或专用网关注入的解密私钥。
     * @param string|OpenSSLAsymmetricKey|null $senderPublicKey 固定发送端公钥；为空时按 JWS kid 从密钥服务读取。
     */
    public function __construct(
        ?PluginKeyService $keys = null,
        ?PluginRequestCrypto $crypto = null,
        array $allowedRoutes = [],
        bool $requireSignature = false,
        string|OpenSSLAsymmetricKey|null $decryptionPrivateKey = null,
        ?string $decryptionKeyId = null,
        string|OpenSSLAsymmetricKey|null $senderPublicKey = null
    ) {
        $this->keys = $keys ?? new PluginKeyService();
        $this->crypto = $crypto ?? new PluginRequestCrypto();
        $this->allowedRoutes = $allowedRoutes;
        $this->requireSignature = $requireSignature;
        $this->decryptionPrivateKey = $decryptionPrivateKey;
        $this->decryptionKeyId = $decryptionKeyId !== null ? trim($decryptionKeyId) : null;
        $this->senderPublicKey = $senderPublicKey;
    }

    /**
     * 解密、验签并注入已验证插件请求。
     *
     * 第三个及之后的中间件参数只支持 `signed`，用于为单一路由强制要求
     * 嵌套 JWS；不支持通过参数关闭协议校验或改用明文。
     *
     * @return mixed 下游中间件/控制器响应。
     * @throws PluginSecurityException 任一协议、密钥、时效或防重放校验失败。
     */
    public function handle(Request $request, Closure $next, string ...$parameters): mixed
    {
        try {
            $raw = (string)$request->getContent();
            if ($raw === '') {
                throw new PluginSecurityException('安全请求体为空');
            }
            $this->assertContentType((string)$request->header('content-type', ''));

            $jweHeader = $this->crypto->peekHeader($raw);
            $headerRequestId = trim((string)$request->header('x-plugin-request-id', ''));
            if ($headerRequestId !== ''
                && !hash_equals($headerRequestId, (string)($jweHeader['request_id'] ?? ''))
            ) {
                throw new PluginSecurityException('安全请求 ID 不匹配');
            }
            $encryptionKid = (string)($jweHeader['kid'] ?? '');
            if ($encryptionKid === '') {
                throw new PluginSecurityException('安全请求缺少加密 kid');
            }
            $decryptionKey = $this->resolveDecryptionKey($encryptionKid);
            $aad = $this->crypto->aadFromHeader($jweHeader);
            $decrypted = $this->crypto->decrypt(
                $raw,
                $decryptionKey,
                $encryptionKid,
                $aad
            );

            $payload = $this->resolvePayload($decrypted, $parameters);
            $envelope = PluginRequestEnvelope::fromArray($payload);
            $this->assertHeaderBinding($envelope, $jweHeader);
            $this->assertAllowedRoute($envelope);
            if (!$this->keys->consumeNonce($envelope)) {
                throw new PluginSecurityException('安全请求 nonce 已使用');
            }

            // 仅向下游暴露已验证的最小字段；不复制原始 JWE 或私钥。
            $request->pluginEnvelope = $envelope;
            $request->pluginPayload = $envelope->body();
            $request->pluginRequestId = $envelope->getRequestId();
            $request->pluginClientId = $envelope->getClientId();
            return $next($request);
        } catch (PluginSecurityException $exception) {
            // 协议错误发生在控制器之前，也必须保持 API 的统一 JSON 错误契约。
            return d_json()->code(400)->fail($exception->getMessage(), [], 400);
        } catch (Throwable $exception) {
            return d_json()->code(400)->fail('安全请求校验失败', [], 400);
        }
    }

    private function assertContentType(string $contentType): void
    {
        $mediaType = strtolower(trim(explode(';', $contentType, 2)[0] ?? ''));
        if ($mediaType !== 'application/jose') {
            throw new PluginSecurityException('安全请求必须使用 application/jose');
        }
    }

    /** @return string|OpenSSLAsymmetricKey */
    private function resolveDecryptionKey(string $kid): string|OpenSSLAsymmetricKey
    {
        if ($this->decryptionKeyId !== null && !hash_equals($this->decryptionKeyId, $kid)) {
            throw new PluginSecurityException('安全请求加密 kid 不匹配');
        }
        if ($this->decryptionPrivateKey !== null) {
            return $this->decryptionPrivateKey;
        }
        return $this->keys->getPrivateKey(
            $kid,
            PluginKeyService::KEY_USE_ENCRYPTION,
            $this->keys->instanceId()
        );
    }

    /** @param mixed $decrypted @param list<string> $parameters @return array<string,mixed> */
    private function resolvePayload(mixed $decrypted, array $parameters): array
    {
        $requiresSignature = $this->requireSignature || in_array('signed', $parameters, true);
        if (is_string($decrypted) && $this->looksLikeJws($decrypted)) {
            $jwsHeader = $this->crypto->peekJwsHeader($decrypted);
            $signingKid = (string)($jwsHeader['kid'] ?? '');
            if ($signingKid === '') {
                throw new PluginSecurityException('签名请求缺少 kid');
            }
            $publicKey = $this->resolveSenderPublicKey($signingKid);
            $payload = $this->crypto->verify(
                $decrypted,
                $publicKey,
                $signingKid,
                ['required_cty' => 'JWT']
            );
            if (!is_array($payload)) {
                throw new PluginSecurityException('签名请求载荷必须为对象');
            }
            return $payload;
        }
        if ($requiresSignature) {
            throw new PluginSecurityException('安全请求必须包含 JWS 签名');
        }
        if (!is_array($decrypted)) {
            throw new PluginSecurityException('安全请求载荷必须为对象');
        }
        return $decrypted;
    }

    /** @return string|OpenSSLAsymmetricKey */
    private function resolveSenderPublicKey(string $kid): string|OpenSSLAsymmetricKey
    {
        if ($this->senderPublicKey !== null) {
            return $this->senderPublicKey;
        }
        $metadata = $this->keys->getPublicKey($kid);
        $key = $metadata['public_key_pem'] ?? null;
        if (!is_string($key) || $key === '') {
            throw new PluginSecurityException('签名请求公钥不存在');
        }
        return $key;
    }

    private function looksLikeJws(string $value): bool
    {
        $parts = explode('.', $value);
        return count($parts) === 3
            && $parts[0] !== ''
            && $parts[1] !== ''
            && $parts[2] !== ''
            && preg_match('/^[A-Za-z0-9_-]+$/', $parts[0]) === 1
            && preg_match('/^[A-Za-z0-9_-]+$/', $parts[1]) === 1
            && preg_match('/^[A-Za-z0-9_-]+$/', $parts[2]) === 1;
    }

    /** @param array<string,mixed> $header */
    private function assertHeaderBinding(PluginRequestEnvelope $envelope, array $header): void
    {
        foreach ($envelope->publicHeader() as $field => $value) {
            if (!isset($header[$field]) || !is_string($header[$field])
                || !hash_equals($value, $header[$field])
            ) {
                throw new PluginSecurityException('安全请求路由绑定校验失败');
            }
        }
    }

    private function assertAllowedRoute(PluginRequestEnvelope $envelope): void
    {
        if ($this->allowedRoutes === []) {
            return;
        }
        $method = $envelope->logicalMethod;
        $path = $envelope->logicalPath;
        foreach ($this->allowedRoutes as $key => $allowed) {
            if (is_int($key) && is_string($allowed)) {
                if ($this->matchesAllowedRoute($allowed, $method, $path)) {
                    return;
                }
                continue;
            }
            if (is_string($key) && is_bool($allowed) && $allowed) {
                if ($this->matchesAllowedRoute($key, $method, $path)) {
                    return;
                }
            }
        }
        throw new PluginSecurityException('逻辑请求路径未注册');
    }

    /**
     * 匹配路由白名单；方法名不区分大小写，逻辑路径保持精确匹配。
     */
    private function matchesAllowedRoute(string $candidate, string $method, string $path): bool
    {
        $candidate = trim($candidate);
        if ($candidate === $path) {
            return true;
        }

        $parts = preg_split('/\s+/', $candidate, 2);
        if (!is_array($parts) || count($parts) !== 2) {
            return false;
        }

        return strtoupper(trim((string)$parts[0])) === strtoupper($method)
            && trim((string)$parts[1]) === $path;
    }
}
