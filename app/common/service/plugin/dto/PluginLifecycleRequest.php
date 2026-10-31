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
use app\common\enum\plugin\PluginDataPolicyEnum;

/**
 * 本地插件生命周期任务输入。
 */
class PluginLifecycleRequest
{
    /** @var array<string,mixed> */
    public readonly array $payload;

    public readonly PluginActor $actor;

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed>|PluginActor|null $actor
     */
    public function __construct(
        public readonly string $pluginCode,
        public readonly string $fromVersion = '',
        public readonly string $toVersion = '',
        public readonly int $releaseId = 0,
        public readonly string $artifactSha256 = '',
        public readonly string $dataPolicy = PluginDataPolicyEnum::RETAIN,
        public readonly string $idempotencyKey = '',
        array $payload = [],
        array|PluginActor|null $actor = null
    ) {
        self::assertPluginCode($pluginCode);
        self::assertVersion($fromVersion, 'from_version');
        self::assertVersion($toVersion, 'to_version');
        if ($releaseId < 0) {
            throw new PluginLifecycleException('发布 ID 无效', 'INVALID_RELEASE_ID');
        }
        if ($artifactSha256 !== '' && !preg_match('/^[a-f0-9]{64}$/i', $artifactSha256)) {
            throw new PluginLifecycleException('制品 SHA-256 无效', 'INVALID_ARTIFACT_HASH');
        }
        if (!PluginDataPolicyEnum::isValid($dataPolicy)) {
            throw new PluginLifecycleException('数据处理策略无效', 'INVALID_DATA_POLICY');
        }
        if ($idempotencyKey !== '' && !preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $idempotencyKey)) {
            throw new PluginLifecycleException('幂等键格式无效', 'INVALID_IDEMPOTENCY_KEY');
        }

        $this->payload = self::sanitizePayload($payload);
        $this->actor = PluginActor::from($actor);
    }

    /**
     * 从数组创建请求。
     *
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data, ?string $pluginCode = null): self
    {
        $code = $pluginCode ?? (string)($data['plugin_code'] ?? '');
        $payload = $data['payload'] ?? [];
        if (!is_array($payload)) {
            throw new PluginLifecycleException('任务载荷必须为对象', 'INVALID_TASK_PAYLOAD');
        }

        $key = (string)($data['idempotency_key'] ?? '');
        if ($key === '') {
            $key = self::generateIdempotencyKey(
                $code,
                (string)($data['task_type'] ?? ''),
                (string)($data['to_version'] ?? ''),
                $data['request_id'] ?? null
            );
        }

        return new self(
            $code,
            (string)($data['from_version'] ?? ''),
            (string)($data['to_version'] ?? ''),
            max(0, (int)($data['release_id'] ?? 0)),
            strtolower(trim((string)($data['artifact_sha256'] ?? ''))),
            (string)($data['data_policy'] ?? PluginDataPolicyEnum::RETAIN),
            $key,
            $payload,
            $data['actor'] ?? null
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'plugin_code' => $this->pluginCode,
            'from_version' => $this->fromVersion,
            'to_version' => $this->toVersion,
            'release_id' => $this->releaseId,
            'artifact_sha256' => $this->artifactSha256,
            'data_policy' => $this->dataPolicy,
            'idempotency_key' => $this->idempotencyKey,
            'payload' => $this->payload,
            'actor' => $this->actor->toArray(),
        ];
    }

    private static function assertPluginCode(string $value): void
    {
        if (!preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $value)) {
            throw new PluginLifecycleException('插件编码无效', 'INVALID_PLUGIN_CODE');
        }
    }

    private static function assertVersion(string $value, string $field): void
    {
        if ($value !== '' && !preg_match('/^[0-9A-Za-z][0-9A-Za-z.+_-]{0,31}$/', $value)) {
            throw new PluginLifecycleException($field . ' 无效', 'INVALID_VERSION');
        }
    }

    /**
     * 防止令牌、私钥和任意大对象进入任务/事件快照。
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private static function sanitizePayload(array $payload): array
    {
        $result = [];
        foreach ($payload as $key => $value) {
            $key = (string)$key;
            if (preg_match('/(?:token|secret|private|password|credential|authorization|signature)/i', $key)) {
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $result[$key] = is_string($value) ? mb_substr($value, 0, 500) : $value;
                continue;
            }
            if (is_array($value)) {
                $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($encoded !== false && strlen($encoded) <= 4000) {
                    $result[$key] = $value;
                }
            }
        }
        return $result;
    }

    private static function generateIdempotencyKey(
        string $pluginCode,
        string $taskType,
        string $version,
        mixed $requestId
    ): string {
        $seed = implode('|', [$pluginCode, $taskType, $version, (string)$requestId]);
        return 'plugin:' . substr(hash('sha256', $seed), 0, 48);
    }
}
