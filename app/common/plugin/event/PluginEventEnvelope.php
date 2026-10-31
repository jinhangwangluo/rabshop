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

namespace app\common\plugin\event;

use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;

/**
 * 同步和异步插件处理器共享的不可变事件信封。
 *
 * 在插件事件体系里，它是事件载荷的唯一表示：宿主在 publish/dispatch 时构造
 * 一次，之后同步 guard/filter、异步 observer、Outbox 落库、投递日志、重放
 * 和订阅快照都读同一份字段，避免各环节自行拼装事件结构导致契约漂移。
 * 主要使用方为 PluginEventBus、PluginRegistry、PluginEventOutboxService、
 * PluginEventLogService 和 PluginEventDispatchJob。
 *
 * 关键不变量：
 * - 构造完成后不可再修改，with()/forPlugin() 都返回新实例，原实例保持不变；
 * - name、version、event_id、trace_id、idempotency_key 由构造阶段严格校验
 *   （长度、字符集、控制字符），非法输入直接抛 \InvalidArgumentException，
 *   调用方必须在入口拦住脏数据，而不是依赖后续环节兜底；
 * - actor 允许为 null（系统触发的无登录态事件），subject 允许为空数组
 *   （暂未定位到业务对象的事件），merchant_id 最小钳制为 0；
 * - payload 与 actor 进入信封前统一经过 sanitiseMap() 清洗（subject 只保留
 *   受校验的 type/id 两个分量），疑似凭据字段不会跨出插件边界，插件拿到的
 *   永远是可安全外发的数据。
 */
final class PluginEventEnvelope implements JsonSerializable
{
    /**
     * 未显式声明事件版本时使用的默认版本号。
     */
    public const DEFAULT_VERSION = '1.0';

    /**
     * 绝不允许跨越插件事件边界的键名特征。
     *
     * 命中该模式的键会在 sanitiseMap() 中被整体丢弃，用于兜底防止口令、
     * 令牌、签名等凭据随载荷落入 Outbox 表和插件侧。
     */
    private const SENSITIVE_KEY_PATTERN = '/(?:^|_)(?:password|passwd|secret|token|authorization|cookie|private_key|access_key|refresh_token|sign|signature|signing_key)(?:$|_)/i';

    /** 事件唯一标识，重放、日志关联和幂等键默认值都依赖它。 */
    private string $eventId;
    /** 事件名，订阅匹配、快照和审计检索的主键之一。 */
    private string $name;
    /** 事件版本，用于同一事件名的结构演进与订阅兼容判断。 */
    private string $version;
    /** 事件发生时间，ATOM 格式，与投递时间区分。 */
    private string $occurredAt;
    /** 链路追踪标识，用于把宿主请求与插件投递日志串起来。 */
    private string $traceId;
    /**
     * 触发者信息；无登录态或系统触发时为 null。
     *
     * @var array<string, mixed>|null
     */
    private ?array $actor;
    /** 事件归属商户，0 表示全局事件。 */
    private int $merchantId;
    /**
     * 事件主体（type + id），标识事件对应的业务对象。
     *
     * @var array<string, mixed>
     */
    private array $subject;
    /**
     * 清洗后的事件载荷，插件实际消费的数据。
     *
     * @var array<string, mixed>
     */
    private array $payload;
    /** 幂等键，投递日志据此识别同一事件的重复投递。 */
    private string $idempotencyKey;

    /**
     * 构造事件信封，并在这里完成全部字段的归一化与校验。
     *
     * 支持两种入参形态：一种是事件名 + 载荷（宿主新建事件），另一种是单个
     * 数组（从 Outbox 记录、投递快照或旧序列化结构还原事件）。第三个参数
     * 是选项映射，调用方可以直接传命名字段，而不用拼长的位置参数列表。
     *
     * 归一化之所以必须严格：信封是插件唯一的数据来源，一旦放过空事件名、
     * 超长标识或控制字符，后续的订阅匹配、幂等去重和审计检索都会失真，
     * 所以这里对非法输入直接抛异常，而不是静默纠正成“看起来能用”的值。
     *
     * @param string|array<string, mixed> $nameOrData 事件名，或序列化后的信封数组。
     * @param array<string, mixed> $payload 事件载荷，仅在 $nameOrData 为事件名时作为载荷使用。
     * @param array<string, mixed> $options 命名选项，如 version/event_id/trace_id/actor/merchant_id/subject/idempotency_key。
     * @throws \InvalidArgumentException 事件名、版本、标识、occurred_at 或 subject 非法时抛出。
     */
    public function __construct(string|array $nameOrData, array $payload = [], array $options = [])
    {
        if (is_array($nameOrData)) {
            // 兼容序列化后的旧结构：从数组和外部选项里一起恢复字段。
            $data = array_merge($nameOrData, $options);
            $name = (string)($data['name'] ?? '');
            $rawPayload = $data['payload'] ?? $data['payload_json'] ?? $payload;
            if (is_string($rawPayload)) {
                $decodedPayload = json_decode($rawPayload, true);
                $rawPayload = is_array($decodedPayload) ? $decodedPayload : [];
            }
            $payload = is_array($rawPayload) ? $rawPayload : [];
            $options = $data;
            if (!array_key_exists('actor', $options) && isset($data['actor_json'])) {
                // 老记录可能把 actor 存成 JSON 字符串，这里统一还原成数组。
                $actor = is_string($data['actor_json']) ? json_decode($data['actor_json'], true) : $data['actor_json'];
                $options['actor'] = is_array($actor) ? $actor : null;
            }
            if (!array_key_exists('subject', $options) && (array_key_exists('subject_type', $data) || array_key_exists('subject_id', $data))) {
                // 旧字段只分散存 subject_type/subject_id 时，重建统一 subject 结构。
                $options['subject'] = [
                    'type' => (string)($data['subject_type'] ?? ''),
                    'id' => (string)($data['subject_id'] ?? ''),
                ];
            }
            if (!array_key_exists('occurred_at', $options) && isset($data['create_time'])) {
                // 没有显式发生时间时，退回到记录创建时间。
                $options['occurred_at'] = $data['create_time'];
            }
        } else {
            $name = $nameOrData;
        }

        // 事件名和版本是订阅匹配的入口，必须先归一化再落任何其他字段。
        $this->name = self::normaliseName($name);
        $this->version = self::normaliseVersion((string)($options['version'] ?? $options['event_version'] ?? self::DEFAULT_VERSION));
        // event_id 允许缺省，缺省时自动生成，保证每条事件都能被日志和重放定位。
        $this->eventId = self::normaliseIdentifier(
            (string)($options['event_id'] ?? $options['eventId'] ?? self::uuid4()),
            36,
            'event_id'
        );
        $this->occurredAt = self::normaliseOccurredAt($options['occurred_at'] ?? $options['occurredAt'] ?? null);
        $this->traceId = self::normaliseIdentifier(
            (string)($options['trace_id'] ?? $options['traceId'] ?? self::makeTraceId()),
            64,
            'trace_id'
        );
        // 无登录态的系统事件允许 actor 为空，非数组（如脏字符串）也按空处理，
        // 避免把不可信结构透传给插件。
        $actor = $options['actor'] ?? null;
        $this->actor = is_array($actor) ? self::sanitiseMap($actor) : null;
        // 商户号必须非负：负数会被当成越权范围传播，这里直接钳制为全局事件。
        $this->merchantId = max(0, (int)($options['merchant_id'] ?? $options['merchantId'] ?? 0));
        $this->subject = self::normaliseSubject($options['subject'] ?? null, $options);
        $this->payload = self::sanitiseMap($payload);
        $idempotencyKey = trim((string)($options['idempotency_key'] ?? $options['idempotencyKey'] ?? ''));
        // 未显式传幂等键时退回 event_id：同一条事件重放会得到同一个幂等键，
        // 投递日志才能识别重复投递。
        $this->idempotencyKey = self::normaliseIdentifier(
            $idempotencyKey !== '' ? $idempotencyKey : $this->eventId,
            128,
            'idempotency_key'
        );
    }

    /**
     * 由事件名和载荷创建信封。
     *
     * 宿主侧发布事件的首选入口（PluginEventBus 与投递任务都走这里），
     * 语义等同于 new self()，保留独立工厂方法是为了让调用点意图更清晰。
     *
     * @param array<string, mixed> $payload 事件载荷。
     * @param array<string, mixed> $options 命名选项，见构造函数。
     * @throws \InvalidArgumentException 传入的事件名或选项非法时抛出。
     */
    public static function create(string $name, array $payload = [], array $options = []): self
    {
        return new self($name, $payload, $options);
    }

    /**
     * 由序列化后的数组还原信封。
     *
     * 用于消费已落库的 Outbox 记录、投递快照或调用方传来的数组，
     * 这些数据可能来自旧结构，因此兼容逻辑统一收敛在构造函数里。
     *
     * @param array<string, mixed> $data 序列化后的信封字段。
     * @throws \InvalidArgumentException 还原出的字段不满足约束时抛出。
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /**
     * 返回事件唯一标识。
     *
     * 投递日志按 event_id 建索引，重放接口也用它定位事件，
     * 因此该值在整条投递链路上必须保持稳定。
     */
    public function getEventId(): string
    {
        return $this->eventId;
    }

    /**
     * 返回事件名。
     *
     * 注册表据此匹配订阅方，快照和审计检索也以它作为主要筛选条件。
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * 返回事件版本。
     *
     * 快照和 Outbox 记录都会冗余一份，用于同事件名的结构演进兼容。
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * 返回事件发生时间（ATOM 格式字符串）。
     *
     * 与投递时间区分：延迟投递或重放时它仍是业务发生时刻。
     */
    public function getOccurredAt(): string
    {
        return $this->occurredAt;
    }

    /**
     * 返回链路追踪标识。
     *
     * 宿主请求、Outbox 记录和插件投递日志共用它，用于跨层排查一次事件。
     */
    public function getTraceId(): string
    {
        return $this->traceId;
    }

    /**
     * 返回触发者信息。
     *
     * 无登录态或系统触发的事件返回 null，插件必须容忍该情况；
     * 落库时由 Outbox 服务转成 actor_json 字段。
     *
     * @return array<string, mixed>|null 已清洗的触发者数据，或 null。
     */
    public function getActor(): ?array
    {
        return $this->actor;
    }

    /**
     * 返回事件归属商户号。
     *
     * 订阅作用域校验依赖它判断插件是否对该商户启用，0 表示全局事件。
     */
    public function getMerchantId(): int
    {
        return $this->merchantId;
    }

    /**
     * 返回事件主体（type + id）。
     *
     * 由发布方声明（如 order、product、merchant、config），落库时拆成
     * Outbox 的 subject_type/subject_id 列，用于审计检索和定位业务对象；
     * 无明确业务对象时返回空数组，调用方读取子键前需自行判空。
     *
     * @return array<string, mixed> 含 type/id 的数组，可能为空数组。
     */
    public function getSubject(): array
    {
        return $this->subject;
    }

    /**
     * 返回清洗后的载荷。
     *
     * 这是插件实际消费的数据：已剔除疑似凭据字段并限制嵌套深度与广度，
     * 不再包含宿主原始入参中的敏感内容。
     *
     * @return array<string, mixed> 已清洗的载荷。
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * 返回幂等键。
     *
     * 投递日志用它拼接 delivery_key/idempotency_key 识别重复投递；
     * 未显式设置时等于 event_id，保证同一事件始终得到同一幂等键。
     */
    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    /**
     * 导出信封的数组表示（下划线字段名）。
     *
     * 该结构是落库和跨进程传递的契约，PluginEventBus 的返回值、Outbox
     * 入库字段和响应里的 envelope 都基于它，字段名不可随意改动。
     *
     * @return array<string, mixed> 可直接序列化的信封字段。
     */
    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'name' => $this->name,
            'version' => $this->version,
            'occurred_at' => $this->occurredAt,
            'trace_id' => $this->traceId,
            'actor' => $this->actor,
            'merchant_id' => $this->merchantId,
            'subject' => $this->subject,
            'payload' => $this->payload,
            'idempotency_key' => $this->idempotencyKey,
        ];
    }

    /**
     * 复制一个新信封，并替换指定字段。
     *
     * 信封不可变，所有“修改”都必须走这里拿到新实例；PluginEventBus 在
     * filter 改写载荷、publish 合并同步结果时都依赖它，因此原实例以及
     * 已经分发给上一个处理器的数据不会受影响。
     *
     * @param array<string, mixed> $changes 需要覆盖的字段（键名与 toArray() 一致）。
     * @throws \InvalidArgumentException 覆盖后的字段不满足约束时抛出。
     */
    public function with(array $changes): self
    {
        // 先导出再重建，使 with() 的结果同样经过构造阶段的校验与清洗，
        // 不会绕过 sanitiseMap/normalise* 得到一个“半成品”信封。
        $data = $this->toArray();
        $data = array_merge($data, $changes);
        return self::fromArray($data);
    }

    /**
     * 返回一个副本，其载荷只保留处理器显式声明的字段。
     *
     * 这是插件最小可见数据原则的落点：PluginEventBus 在调用每个
     * guard/filter/observer 之前按订阅描述符裁一次载荷，插件看不到未声明
     * 的字段，避免跨插件读取宿主数据。
     *
     * 支持 `payload_fields` 和 `allowed_payload_fields` 两种声明方式，
     * 每个字段都使用点号路径表示。
     *
     * @param array<string, mixed> $descriptor 订阅描述符，包含白名单字段声明。
     * @throws \InvalidArgumentException 裁剪结果不满足信封约束时抛出。
     */
    public function forPlugin(array $descriptor = []): self
    {
        $fields = $descriptor['payload_fields'] ?? $descriptor['allowed_payload_fields'] ?? null;
        if (!is_array($fields) || $fields === []) {
            // 没声明白名单时不裁剪字段，但仍返回副本，保证插件拿不到原实例。
            return $this->with([]);
        }

        $filtered = [];
        foreach ($fields as $field) {
            // 白名单来自插件配置，可能混入非字符串或首尾带点号的写法：
            // 这类项直接跳过而不是抛异常，避免一条配置笔误阻断整条事件链路。
            if (!is_string($field)) {
                continue;
            }
            $field = trim($field, " .\t\n\r\0\x0B");
            if ($field === '') {
                continue;
            }
            // 点号路径按层级读取，只把命中的子树写回新载荷。
            $value = self::readPath($this->payload, $field);
            if ($value['found']) {
                self::writePath($filtered, $field, $value['value']);
            }
        }

        // 裁剪结果同样经过信封构造，白名单里写入敏感键也会被再次清洗掉。
        return $this->with(['payload' => $filtered]);
    }

    /**
     * 返回规范化载荷的 SHA-256 摘要。
     *
     * 供投递日志记录载荷指纹，用于判断同一条事件是否带着相同数据重复投递；
     * 计算前先做 canonicalise 排序，保证键顺序不同但内容相同的载荷得到
     * 同一个摘要。
     */
    public function payloadHash(): string
    {
        $json = json_encode(self::canonicalise($this->payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        // 载荷含无法编码的值时退化为空对象摘要，保证哈希始终可比较而不抛异常。
        return hash('sha256', $json === false ? '{}' : $json);
    }

    /**
     * 按 JSON 序列化协议导出信封（等价于 toArray()）。
     *
     * 让信封可以直接作为接口响应或队列消息体序列化，调用方不必再手动转换。
     *
     * @return array<string, mixed> 可直接序列化的信封字段。
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * 校验并归一化事件名。
     *
     * 事件名是订阅匹配的主键，必须限制为字母数字开头、最长 100 字符的
     * 固定字符集，避免出现只有大小写或空白差异、无法稳定匹配的名字。
     *
     * @param string $name 原始事件名。
     * @return string 去除首尾空白后的事件名。
     * @throws \InvalidArgumentException 事件名为空、超长或含非法字符时抛出。
     */
    private static function normaliseName(string $name): string
    {
        $name = trim($name);
        if ($name === '' || strlen($name) > 100 || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,99}$/', $name)) {
            throw new \InvalidArgumentException('Invalid plugin event name');
        }
        return $name;
    }

    /**
     * 校验并归一化事件版本。
     *
     * 版本会写进 Outbox 与快照并被订阅方比对，因此限定为允许小版本号
     * （点、加号、连字符）的短字符串，避免插件按版本号做错误的兼容判断。
     *
     * @param string $version 原始版本号。
     * @return string 去除首尾空白后的版本号。
     * @throws \InvalidArgumentException 版本为空、超长或含非法字符时抛出。
     */
    private static function normaliseVersion(string $version): string
    {
        $version = trim($version);
        if ($version === '' || strlen($version) > 20 || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._+\-]{0,19}$/', $version)) {
            throw new \InvalidArgumentException('Invalid plugin event version');
        }
        return $version;
    }

    /**
     * 校验并归一化通用标识字段（event_id、trace_id、idempotency_key 等）。
     *
     * 这些标识会被写进数据库并作为查询条件，所以统一要求非空、不超长
     * 且不含控制字符；$field 仅用于拼出可定位的错误信息。
     *
     * @param string $value 原始标识值。
     * @param int $maxLength 允许的最大字节长度。
     * @param string $field 字段名，用于异常信息。
     * @return string 去除首尾空白后的标识值。
     * @throws \InvalidArgumentException 标识为空、超长或含控制字符时抛出。
     */
    private static function normaliseIdentifier(string $value, int $maxLength, string $field): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > $maxLength || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new \InvalidArgumentException('Invalid ' . $field);
        }
        return $value;
    }

    /**
     * 把多种时间入参统一成 ATOM 格式字符串。
     *
     * 兼容 DateTimeInterface、可解析的时间字符串和缺省三种情况；缺省时取
     * 当前时间，使“实时发布”的事件不必显式传时间。传入字符串却无法解析时
     * 直接抛异常，避免明细里的发生时间被悄悄换成 now 而失真。
     *
     * @param mixed $value DateTimeInterface、时间字符串或 null。
     * @return string ATOM 格式的发生时间。
     * @throws \InvalidArgumentException 时间字符串无法解析时抛出。
     */
    private static function normaliseOccurredAt(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }
        if (is_string($value) && trim($value) !== '') {
            try {
                return (new DateTimeImmutable($value))->format(DateTimeInterface::ATOM);
            } catch (\Throwable) {
                throw new \InvalidArgumentException('Invalid occurred_at');
            }
        }
        return (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM);
    }

    /**
     * 归一化事件主体，统一为 type + id 结构。
     *
     * 兼容三种历史写法：直接给业务 id（字符串/整数）、给 subject 数组、
     * 或把 subject_type/subject_id 平铺在选项里。主体允许为空数组，因为
     * 部分事件确实没有对应业务对象；但一旦给了值就限制长度并禁止控制
     * 字符，防止脏数据进入订阅匹配和日志检索。
     *
     * @param mixed $subject subject 数组、业务 id 或 null。
     * @param array<string, mixed> $options 选项映射，用于兜底读取 subject_type/subject_id。
     * @return array<string, mixed> 含 type/id 的数组，或空数组表示无主体。
     * @throws \InvalidArgumentException subject_type/subject_id 非法时抛出。
     */
    private static function normaliseSubject(mixed $subject, array $options): array
    {
        if (is_string($subject) || is_int($subject)) {
            // 只给业务 id 时，类型取选项中声明的 subject_type，缺省记为 generic。
            $type = trim((string)($options['subject_type'] ?? $options['subjectType'] ?? 'generic'));
            $id = trim((string)$subject);
            self::assertSubjectPart($type, 'subject_type');
            self::assertSubjectPart($id, 'subject_id');
            return [
                'type' => $type,
                'id' => $id,
            ];
        }
        if (is_array($subject)) {
            $type = trim((string)($subject['type'] ?? $subject['subject_type'] ?? ''));
            $id = trim((string)($subject['id'] ?? $subject['subject_id'] ?? ''));
            if ($type === '' && $id === '') {
                // 显式给了空 subject 视为“无主体”，而不是非法数据。
                return [];
            }
            if ($type === '') {
                // 有 id 没类型时补 generic，保证结构稳定、消费方不必判空 type。
                $type = 'generic';
            }
            self::assertSubjectPart($type, 'subject_type');
            self::assertSubjectPart($id, 'subject_id');
            return self::sanitiseMap(['type' => $type, 'id' => $id]);
        }

        // 既没给 subject 也没给 subject 数组时，退回选项里平铺的字段。
        $type = trim((string)($options['subject_type'] ?? $options['subjectType'] ?? ''));
        $id = trim((string)($options['subject_id'] ?? $options['subjectId'] ?? ''));
        self::assertSubjectPart($type, 'subject_type');
        self::assertSubjectPart($id, 'subject_id');
        return $type !== '' || $id !== '' ? ['type' => $type ?: 'generic', 'id' => $id] : [];
    }

    /**
     * 校验主体的单个组成部分。
     *
     * 空值是允许的（表示未声明），但非空值必须不超过 64 字节且不含控制
     * 字符：主体值会参与订阅匹配并写入日志，脏数据会影响匹配准确性。
     *
     * @param string $value 待校验的值。
     * @param string $field 字段名，用于异常信息。
     * @throws \InvalidArgumentException 值超长或含控制字符时抛出。
     */
    private static function assertSubjectPart(string $value, string $field): void
    {
        if ($value !== '' && (strlen($value) > 64 || preg_match('/[\x00-\x1F\x7F]/', $value))) {
            throw new \InvalidArgumentException('Invalid event ' . $field);
        }
    }

    /**
     * 持久化前移除疑似凭据的键，并限制嵌套输入规模。
     *
     * 信封是插件边界上唯一可信的数据出口，这里做两道兜底：按键名丢弃
     * 口令、令牌、签名等敏感字段（清洗后连同子层一起消失），以及限制
     * 递归深度与同级条目数，防止插件或调用方塞入超大/超深结构拖垮
     * 序列化和落库。
     *
     * @param array<string, mixed> $data 待清洗的数据。
     * @param int $depth 当前递归深度，内部使用。
     * @return array<string, mixed> 清洗后的数据，超限部分被丢弃。
     */
    private static function sanitiseMap(array $data, int $depth = 0): array
    {
        // 超过深度上限直接返回空数组丢掉整棵子树，比抛异常更适合兜底场景。
        if ($depth > 8) {
            return [];
        }
        $result = [];
        $count = 0;
        foreach ($data as $key => $value) {
            // 同层条目数封顶，避免无界集合拖慢事件入库。
            if (++$count > 500) {
                break;
            }
            $keyString = is_int($key) ? (string)$key : trim((string)$key);
            // 命中敏感键名的整条数据直接丢弃，包括其下所有子层。
            if ($keyString !== '' && self::isSensitiveKey($keyString)) {
                continue;
            }
            if (is_array($value)) {
                $result[$key] = self::sanitiseMap($value, $depth + 1);
            } elseif (is_object($value)) {
                if ($value instanceof \JsonSerializable) {
                    // 可序列化对象按其数组表示展开继续清洗，其余对象不透传内容。
                    $serialised = $value->jsonSerialize();
                    $result[$key] = is_array($serialised) ? self::sanitiseMap($serialised, $depth + 1) : (string)$serialised;
                } else {
                    $result[$key] = '[object]';
                }
            } elseif (is_resource($value)) {
                // 资源无法序列化，用占位符代替，保证 JSON 编码不失败。
                $result[$key] = '[resource]';
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    /**
     * 判断键名是否属于敏感凭据。
     *
     * 先按驼峰边界插入下划线再统一转小写，使 accessToken 与 access_token
     * 命中同一条规则，避免只按长蛇命名匹配而被驼峰写法绕过。
     *
     * @param string $key 原始键名。
     * @return bool 命中敏感键特征时返回 true。
     */
    private static function isSensitiveKey(string $key): bool
    {
        $normalised = strtolower((string)preg_replace('/([a-z])([A-Z])/', '$1_$2', $key));
        return preg_match(self::SENSITIVE_KEY_PATTERN, $normalised) === 1;
    }

    /**
     * 计算摘要前把数据整理成稳定形态。
     *
     * 关联数组按键名排序，消除键顺序差异导致的摘要不一致；列表保持原顺序，
     * 因为顺序本身是业务语义（如商品明细行序），重排会改变载荷含义。
     *
     * @param mixed $value 任意待规范化数据。
     * @return mixed 键顺序稳定的等价数据。
     */
    private static function canonicalise(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            // 列表只逐项递归，不改顺序。
            return array_map(static fn(mixed $item): mixed => self::canonicalise($item), $value);
        }
        $result = [];
        $keys = array_keys($value);
        usort($keys, static fn($left, $right): int => strcmp((string)$left, (string)$right));
        foreach ($keys as $key) {
            $result[$key] = self::canonicalise($value[$key]);
        }
        return $result;
    }

    /**
     * 按点号路径读取载荷中的值。
     *
     * forPlugin() 用点号路径描述插件可读字段，这里只做读取、不抛异常：
     * 白名单允许指向不存在的字段（配置超前于事件结构是常态），用 found
     * 标记命中情况，由调用方决定是否写回结果。
     *
     * @param array<string, mixed> $source 源载荷。
     * @param string $path 点号分隔的字段路径。
     * @return array{found: bool, value: mixed} found 为 false 时 value 恒为 null。
     */
    private static function readPath(array $source, string $path): array
    {
        $current = $source;
        foreach (explode('.', $path) as $segment) {
            // 中途遇到非数组或缺失键即视为未命中，避免越界访问。
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return ['found' => false, 'value' => null];
            }
            $current = $current[$segment];
        }
        return ['found' => true, 'value' => $current];
    }

    /**
     * 把命中字段按原路径写回裁剪后的载荷。
     *
     * 保持与 readPath 对称的路径结构，插件拿到的字段层次与声明一致；
     * 路径非法（空段）时静默跳过，因为白名单校验已在上游完成，
     * 这里只负责重建结构。
     *
     * @param array<string, mixed> $target 目标数组（引用传递，就地写入）。
     * @param string $path 点号分隔的字段路径。
     * @param mixed $value 要写入的值。
     */
    private static function writePath(array &$target, string $path, mixed $value): void
    {
        $segments = explode('.', $path);
        $last = array_pop($segments);
        if ($last === null || $last === '') {
            return;
        }
        $cursor =& $target;
        foreach ($segments as $segment) {
            // 中间层缺失或被占成非数组时重建为空数组，保证路径可继续下钻。
            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }
            $cursor =& $cursor[$segment];
        }
        $cursor[$last] = $value;
    }

    /**
     * 生成 v4 格式的 UUID。
     *
     * 用作 event_id 的缺省值，让每条事件即使调用方没传标识也可被唯一引用。
     *
     * @return string 形如 8-4-4-4-12 的 UUID 字符串。
     */
    private static function uuid4(): string
    {
        try {
            $bytes = random_bytes(16);
            // 按 RFC 4122 写入版本位（4）和变体位（10xx）。
            $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
            $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
        } catch (\Throwable) {
            // 系统随机源不可用时降级为 mt_rand：唯一性弱于 v4，但仍避免事件因
            // 缺少标识而无法落库，因此不向上抛异常中断发布流程。
            return sprintf(
                '%08x-%04x-4%03x-%04x-%012x',
                mt_rand(0, 0xffffffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0xfff),
                mt_rand(0x8000, 0xbfff),
                mt_rand(0, 0xffffffffffff)
            );
        }
    }

    /**
     * 生成缺省的链路追踪标识。
     *
     * 调用方未传 trace_id 时使用，取 UUID 与微秒时间的哈希前 32 位，
     * 保证同一毫秒内批量发布的事件也不会撞号，便于按 trace_id 追踪链路。
     *
     * @return string 32 位十六进制追踪标识。
     */
    private static function makeTraceId(): string
    {
        return substr(hash('sha256', self::uuid4() . microtime(true)), 0, 32);
    }
}
