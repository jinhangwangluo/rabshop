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

use app\common\plugin\PluginRegistry;
use app\common\service\plugin\PluginEventLogService;
use app\common\service\plugin\PluginEventOutboxService;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * 宿主侧插件事件总线（插件事件派发内核）。
 *
 * 它把宿主业务事实翻译成插件可消费的事件，并按三类语义分别派发：
 * - dispatchSync()：在宿主请求事务内执行 guard/filter，先拒后改；任何拒绝、
 *   异常或超时都失败关闭（fail closed），绝不放行未经插件校验的业务操作；
 * - dispatchCalculation()：同步执行只读计算器，按插件编码收集报价，不允许
 *   改写宿主载荷，也不应产生写库副作用；
 * - dispatchTransactional()：跟随宿主事务执行事务处理器，允许写库；插件失败
 *   会让宿主事务一起回滚，保证业务数据与插件数据要么同时生效、要么同时撤销。
 *
 * observer 走 Outbox 异步链路：publish() 只做“先落库、后投递”，写入一条
 * Outbox 记录并固化订阅快照后即返回，真正调用 observer 由
 * PluginEventDispatchJob 在队列中按 dispatchDelivery() 逐条执行；投递失败只
 * 影响该条 delivery，由投递日志按尝试次数决定重试或转入死信，队列暂时不可用
 * 时 pending 记录仍可被扫描或重放捞出，事件不会丢失。
 *
 * 边界处理集中在本类：处理器返回值解析（guard 布尔、filter 改写载荷、
 * calculator 报价校验、observer 失败标记）、超时判定、错误码映射与错误信息
 * 脱敏。返回值无法解释时一律按失败关闭处理，不做“猜一个正常结果”的兼容，
 * 避免插件写错返回值却让宿主静默通过校验。
 *
 * 主要调用方为 PluginBusinessEventService（业务侧门面）与
 * PluginEventDispatchJob（队列消费者）；依赖未显式传入时由构造函数从容器
 * 解析，无容器环境（单元测试、纯 CLI）回退到进程内实现。
 */
class PluginEventBus
{
    private PluginRegistry $registry;
    private PluginEventOutboxService $outbox;
    private PluginEventLogService $logs;

    /**
     * 创建事件总线。
     *
     * 未显式传入依赖时从应用容器解析；无容器运行（例如单元测试）时
     * 使用进程内实现，调用方可通过构造参数注入共享实例。
     *
     * @param PluginRegistry|null $registry 插件注册表。
     * @param PluginEventOutboxService|null $outbox Outbox 服务。
     * @param PluginEventLogService|null $logs 投递日志服务。
     */
    public function __construct(
        ?PluginRegistry $registry = null,
        ?PluginEventOutboxService $outbox = null,
        ?PluginEventLogService $logs = null
    ) {
        $this->registry = $registry ?? $this->resolveRegistry();
        $this->outbox = $outbox ?? $this->resolveService(PluginEventOutboxService::class, new PluginEventOutboxService());
        $this->logs = $logs ?? $this->resolveService(PluginEventLogService::class, new PluginEventLogService());
    }

    /**
     * 获取当前总线使用的插件注册表。
     */
    public function getRegistry(): PluginRegistry
    {
        return $this->registry;
    }

    /**
     * 获取当前总线使用的 Outbox 服务。
     */
    public function getOutboxService(): PluginEventOutboxService
    {
        return $this->outbox;
    }

    /**
     * 获取当前总线使用的投递日志服务。
     */
    public function getLogService(): PluginEventLogService
    {
        return $this->logs;
    }

    /**
     * 发布一个事件：按模式跑同步链路并/或写入 Outbox。
     *
     * 支持三种标准入参：
     * - publish('order.created', $payload, $options)
     * - publish($envelope, $options)
     * - publish($serializedEnvelope, $options)
     *
     * 默认模式是 observer（异步投递）：此时本方法只把事件与订阅快照写进
     * Outbox 后返回，不直接调用任何 observer；设置 mode=sync/guard/filter
     * 则只跑同步链路并直接返回结果，不落库。
     *
     * 设置 mode=both 时会先跑同步 guard/filter，再用改写后的载荷入库，
     * 保证 Outbox 保存的是同步链路允准后的最终态，异步 observer 与宿主
     * 看到同一份数据。Outbox 写入遵循“先入库、后入队”：即使队列不可用也
     * 只把 queued 标记为 false，pending 记录仍可由扫描任务补投，事件不丢。
     *
     * @param string|PluginEventEnvelope|array<string, mixed> $event
     * @param array<string, mixed> $payloadOrOptions
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function publish(string|PluginEventEnvelope|array $event, array $payloadOrOptions = [], array $options = []): array
    {
        [$envelope, $options] = $this->normalisePublishInput($event, $payloadOrOptions, $options);
        // mode 决定走同步还是异步：type 是旧写法别名，sync=true 是布尔快捷
        // 方式；两者同时出现时以布尔快捷方式为准，避免配置冲突时行为不确定。
        $mode = strtolower(trim((string)($options['mode'] ?? $options['type'] ?? 'observer')));
        if (($options['sync'] ?? false) === true) {
            $mode = 'sync';
        }

        // 纯同步模式只跑 guard/filter 并立即返回，不写 Outbox、不投递 observer。
        if (in_array($mode, ['sync', 'guard', 'filter'], true)) {
            return $this->dispatchSync($envelope, [
                'surface' => $options['surface'] ?? $options['platform'] ?? 'backend',
                'merchant_id' => $options['merchant_id'] ?? $envelope->getMerchantId(),
                'trace_id' => $envelope->getTraceId(),
                'options' => $options,
                'mode' => $mode,
            ]);
        }

        // 异步链路的上下文同样要补齐 merchant_id/surface：订阅快照按这两项做
        // 商户与端侧作用域匹配，缺失时会导致本该生效的订阅被静默跳过。
        $context = is_array($options['context'] ?? null) ? $options['context'] : $options;
        $context['merchant_id'] = $context['merchant_id'] ?? $envelope->getMerchantId();
        $context['surface'] = $context['surface'] ?? $context['platform'] ?? 'backend';
        // both/run_sync 先跑同步链路：用 filter 改写后的载荷重建信封（with()
        // 会重新走一遍校验与清洗）；同步链路的拒绝会直接上抛，因此不会出现
        // “同步校验失败但仍然入库”的情况。
        if ($mode === 'both' || ($options['run_sync'] ?? false) === true) {
            $syncResult = $this->dispatchSync($envelope, $context, $options);
            $envelope = $envelope->with(['payload' => $syncResult['payload'] ?? $envelope->getPayload()]);
        } else {
            $syncResult = null;
        }

        // 先对同步链路后的结果做快照，确保写入 Outbox 的是最终允许态。
        $snapshot = $this->registry->buildSnapshot($envelope, $context);

        // 幂等键默认沿用信封自带的键：同一业务事实重复发布时 Outbox 会命中
        // 唯一键返回已有记录（duplicate=true），不会重复投递；dispatch 默认
        // 为 true，允许调用方只落库、交由外部扫描任务负责投递。
        $enqueueOptions = $options;
        $enqueueOptions['idempotency_key'] = $options['idempotency_key'] ?? $envelope->getIdempotencyKey();
        $enqueueOptions['dispatch'] = array_key_exists('dispatch', $options) ? (bool)$options['dispatch'] : true;
        $row = $this->outbox->enqueue($envelope, $snapshot, $enqueueOptions);
        return [
            'event_id' => $envelope->getEventId(),
            'event_version' => $envelope->getVersion(),
            'name' => $envelope->getName(),
            'status' => (string)($row['status'] ?? PluginEventOutboxService::STATUS_PENDING),
            'outbox_id' => (int)($row['id'] ?? 0),
            'idempotency_key' => $envelope->getIdempotencyKey(),
            'envelope' => $envelope->toArray(),
            'snapshot' => $snapshot,
            'outbox' => $row,
            'queued' => (bool)($row['queued'] ?? false),
            'sync' => $syncResult,
        ];
    }

    /**
     * 同步执行 guard 和 filter，返回处理后的事件载荷。
     *
     * 这是宿主“还能反悔”的阶段：guard 先按确定性顺序逐个判定是否放行，
     * 任一 guard 拒绝、报错或超时都会立刻抛 PluginEventRejectedException，
     * 让宿主在同一个事务里中止本次业务操作；只有全部 guard 通过后，filter
     * 才按顺序改写载荷，后一个 filter 看到的是前一个 filter 的结果。
     *
     * 之所以要求失败关闭（fail closed）：跳过校验比不校验更危险，异常被吞
     * 掉意味着宿主在没有插件保护的情况下继续提交数据。因此这里不提供
     * “忽略插件错误继续执行”的旁路，所有异常一律上抛（捕获分支只做异常
     * 归一，不做吞掉）。
     *
     * 返回的 handlers 只记录实际执行过的处理器、类型、终态与耗时，payload
     * 是 filter 链处理后的最终载荷，调用方应以它作为后续业务的数据来源。
     *
     * @param string|PluginEventEnvelope|array<string, mixed> $event
     * @param array<string, mixed> $contextOrPayload
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function dispatchSync(
        string|PluginEventEnvelope|array $event,
        array $contextOrPayload = [],
        array $options = []
    ): array {
        [$envelope, $context] = $this->normaliseSyncInput($event, $contextOrPayload, $options);
        $context['event'] = $envelope;
        $context['merchant_id'] = $context['merchant_id'] ?? $envelope->getMerchantId();
        $context['surface'] = $context['surface'] ?? $context['platform'] ?? 'backend';

        // 一次性取出两类订阅者：执行顺序由 Registry 按优先级排定，保证同类
        // 处理器顺序确定；每个处理器随后只会拿到按白名单裁剪后的信封副本。
        $guards = $this->registry->getSubscribers($envelope->getName(), $context, 'guard');
        $filters = $this->registry->getSubscribers($envelope->getName(), $context, 'filter');
        $executed = [];

        foreach ($guards as $descriptor) {
            $scopedEvent = $envelope->forPlugin($descriptor);
            $startedAt = microtime(true);
            try {
                $result = $this->invokeHandler($descriptor, $scopedEvent, $context);
                $this->assertWithinTimeout($descriptor, $startedAt);
                $allowed = $this->parseGuardResult($result);
                $executed[] = [
                    'plugin_code' => (string)$descriptor['plugin_code'],
                    'handler' => (string)$descriptor['handler'],
                    'type' => 'guard',
                    'status' => $allowed ? 'succeeded' : 'rejected',
                    'duration_ms' => $this->durationMs($startedAt),
                ];
                if (!$allowed) {
                    throw new PluginEventRejectedException(
                        'Plugin guard rejected event',
                        $this->handlerContext($descriptor, $envelope)
                    );
                }
            } catch (PluginEventRejectedException $exception) {
                // 插件已明确拒绝：原样上抛，保留其自定义消息与上下文。
                throw $exception;
            } catch (\Throwable $exception) {
                // 其他异常（含超时）统一包装成拒绝并保留原始异常：插件实现
                // 报错等同于拒绝，不能因为它有 bug 就让事件被放行。
                throw new PluginEventRejectedException(
                    'Plugin guard failed; event rejected',
                    $this->handlerContext($descriptor, $envelope),
                    0,
                    $exception
                );
            }
        }

        foreach ($filters as $descriptor) {
            // $before 只用于给 assertFilterMutation() 和失败上下文提供改写前
            // 的对照；$envelope 在每轮成功后替换为 filter 的新结果。
            $before = $envelope;
            $scopedEvent = $envelope->forPlugin($descriptor);
            $startedAt = microtime(true);
            try {
                // 同步链路中 context 始终指向当前处理器拿到的裁剪信封，
                // 插件无法借助上下文读到别人的载荷快照。
                $context['event'] = $scopedEvent;
                $result = $this->invokeHandler($descriptor, $scopedEvent, $context);
                $this->assertWithinTimeout($descriptor, $startedAt);
                $candidate = $this->parseFilterResult($result, $envelope);
                $candidate = $this->assertFilterMutation(
                    $before->getPayload(),
                    $candidate,
                    (string)$descriptor['plugin_code']
                );
                $envelope = $envelope->with(['payload' => $candidate]);
                $executed[] = [
                    'plugin_code' => (string)$descriptor['plugin_code'],
                    'handler' => (string)$descriptor['handler'],
                    'type' => 'filter',
                    'status' => 'succeeded',
                    'duration_ms' => $this->durationMs($startedAt),
                ];
            } catch (PluginEventRejectedException $exception) {
                // 拒绝异常原样上抛，不重复包装。
                throw $exception;
            } catch (\Throwable $exception) {
                // 其他异常（含改写后载荷校验不通过）按拒绝处理；上下文用
                // $before，便于定位是哪一个 filter 改坏了数据。
                throw new PluginEventRejectedException(
                    'Plugin filter failed; event rejected',
                    $this->handlerContext($descriptor, $before),
                    0,
                    $exception
                );
            }
        }

        return [
            'allowed' => true,
            'event_id' => $envelope->getEventId(),
            'name' => $envelope->getName(),
            'payload' => $envelope->getPayload(),
            'envelope' => $envelope->toArray(),
            'handlers' => $executed,
        ];
    }

    /**
     * 同步执行插件报价处理器（calculator）。
     *
     * 与 guard/filter 的区别在用途和副作用约定：计算器只读地取出自己接管的
     * 插件商品并返回一份报价，结果按插件编码收集进 quotes 供宿主比价，既不
     * 能通过该接口修改宿主原始载荷，也不应产生写库副作用，因此适合放在只读
     * 的试算或下单前比价阶段。
     *
     * 每个计算器只看到 scopePluginEvent() 裁剪后属于自己插件的商品行；同一
     * 插件返回重复报价、报价非法、主动拒绝或超时都会让整条计算链路失败关闭，
     * 避免宿主拿到不完整或被污染的价格集合。
     *
     * @param string|PluginEventEnvelope|array<string, mixed> $event
     * @param array<string, mixed> $contextOrPayload
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function dispatchCalculation(
        string|PluginEventEnvelope|array $event,
        array $contextOrPayload = [],
        array $options = []
    ): array {
        [$envelope, $context] = $this->normaliseSyncInput($event, $contextOrPayload, $options);
        $context['event'] = $envelope;
        $context['merchant_id'] = $context['merchant_id'] ?? $envelope->getMerchantId();
        $context['surface'] = $context['surface'] ?? $context['platform'] ?? 'backend';

        $calculators = $this->registry->getSubscribers($envelope->getName(), $context, 'calculator');
        $quotes = [];
        $executed = [];
        foreach ($calculators as $descriptor) {
            // 跳过与本次事件无关的插件（事件带商品行但不含该插件的商品），
            // 避免混合购物车里对无关插件触发报价。
            $scopedEvent = $this->scopePluginEvent($envelope, $descriptor);
            if ($scopedEvent === null) {
                continue;
            }
            $startedAt = microtime(true);
            try {
                $context['event'] = $scopedEvent;
                $result = $this->invokeHandler($descriptor, $scopedEvent, $context);
                $this->assertWithinTimeout($descriptor, $startedAt);
                $quote = $this->parseCalculationResult($result);
                $pluginCode = (string)$descriptor['plugin_code'];
                // 同一插件同一事件只允许一份报价：出现第二份说明注册或实现
                // 有误，取其中任何一个都可能算错钱，因此直接判为非法结果。
                if (array_key_exists($pluginCode, $quotes)) {
                    throw new PluginEventInvalidResultException('Plugin calculator returned duplicate quote');
                }
                $quotes[$pluginCode] = $quote;
                $executed[] = [
                    'plugin_code' => $pluginCode,
                    'handler' => (string)$descriptor['handler'],
                    'type' => 'calculator',
                    'status' => 'succeeded',
                    'duration_ms' => $this->durationMs($startedAt),
                ];
            } catch (PluginEventRejectedException|PluginEventInvalidResultException $exception) {
                throw $exception;
            } catch (\Throwable $exception) {
                throw new PluginEventRejectedException(
                    'Plugin calculator failed; event rejected',
                    $this->handlerContext($descriptor, $envelope),
                    0,
                    $exception
                );
            }
        }

        return [
            'allowed' => true,
            'event_id' => $envelope->getEventId(),
            'name' => $envelope->getName(),
            'quotes' => $quotes,
            'envelope' => $envelope->toArray(),
            'handlers' => $executed,
        ];
    }

    /**
     * 在宿主事务内执行插件事务处理器。
     *
     * 这是三类同步派发里唯一允许插件写库的模式：处理器与宿主业务共用同一个
     * 数据库事务，任何失败——返回 false、返回 ok/allowed 为 false、抛异常或
     * 超时——都会抛 PluginEventRejectedException，让调用方回滚整个事务，
     * 避免出现“宿主数据已写、插件数据没写”的半成品状态。
     *
     * 因此调用方必须在已开启的事务中调用，插件处理器也要保持可回滚：不要在
     * 其中发送通知、调用外部接口等无法回滚的副作用，这类动作应放到 observer
     * 异步链路里。返回值非法同样按失败关闭处理，与 guard 一致。
     *
     * @param string|PluginEventEnvelope|array<string, mixed> $event
     * @param array<string, mixed> $contextOrPayload
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function dispatchTransactional(
        string|PluginEventEnvelope|array $event,
        array $contextOrPayload = [],
        array $options = []
    ): array {
        [$envelope, $context] = $this->normaliseSyncInput($event, $contextOrPayload, $options);
        $context['event'] = $envelope;
        $context['merchant_id'] = $context['merchant_id'] ?? $envelope->getMerchantId();
        $context['surface'] = $context['surface'] ?? $context['platform'] ?? 'backend';

        $transactions = $this->registry->getSubscribers($envelope->getName(), $context, 'transaction');
        $results = [];
        $executed = [];
        foreach ($transactions as $descriptor) {
            // 与计算器一致：只对真正接管了当前事件商品行的插件执行，
            // 无关插件直接跳过，避免误改别人的数据。
            $scopedEvent = $this->scopePluginEvent($envelope, $descriptor);
            if ($scopedEvent === null) {
                continue;
            }
            $startedAt = microtime(true);
            try {
                $context['event'] = $scopedEvent;
                $result = $this->invokeHandler($descriptor, $scopedEvent, $context);
                $this->assertWithinTimeout($descriptor, $startedAt);
                // 事务处理器沿用 guard 风格的“显式拒绝”：false 或 ok/allowed
                // 为 false 都表示插件拒绝本次业务，触发宿主事务回滚；其余返回
                // 值只作为结果记录，不参与控制流判断。
                if ($result === false || (is_array($result) && (($result['ok'] ?? true) === false || ($result['allowed'] ?? true) === false))) {
                    throw new PluginEventRejectedException('Plugin transaction rejected event', $this->handlerContext($descriptor, $envelope));
                }
                $pluginCode = (string)$descriptor['plugin_code'];
                $results[$pluginCode] = $result;
                $executed[] = [
                    'plugin_code' => $pluginCode,
                    'handler' => (string)$descriptor['handler'],
                    'type' => 'transaction',
                    'status' => 'succeeded',
                    'duration_ms' => $this->durationMs($startedAt),
                ];
            } catch (PluginEventRejectedException $exception) {
                throw $exception;
            } catch (\Throwable $exception) {
                throw new PluginEventRejectedException(
                    'Plugin transaction failed; event rejected',
                    $this->handlerContext($descriptor, $envelope),
                    0,
                    $exception
                );
            }
        }

        return [
            'allowed' => true,
            'event_id' => $envelope->getEventId(),
            'name' => $envelope->getName(),
            'results' => $results,
            'envelope' => $envelope->toArray(),
            'handlers' => $executed,
        ];
    }

    /**
     * 执行一次 observer 投递，并只记录这一笔尝试。
     *
     * 由 PluginEventDispatchJob（或显式重放）在异步链路上调用，是真正触发
     * 插件 observer 的入口。流程是“先占位、后执行”：先经投递日志写入一条
     * processing 记录（delivery_key + attempt 唯一），若返回 claimed=false，
     * 说明另一 worker 已认领同一尝试，此时幂等返回而不重复执行插件代码。
     *
     * observer 的返回值只用于识别失败：返回 false，或返回数组中 status 为
     * failed/retrying/dead_letter、ok 为 false，都按投递失败处理；其余返回值
     * 一律忽略——observer 用于通知已发生的事实，不允许借返回值改写事件。
     * 失败时写出错误码与脱敏后的错误信息，由日志服务按尝试次数与 retryable
     * 决定进入 retrying 还是 dead_letter；所有异常都在这里兜住，单个 observer
     * 失败不会中断同事件其他 observer 的投递。
     *
     * 投递日志只写定位与排障所需的信息：event_id、delivery_key、attempt、状态、
     * 耗时、错误码、脱敏后的错误信息和载荷摘要（payload_hash），不落载荷明文与
     * 异常原始上下文；插件被禁用等“跳过”情况由任务侧标记为 skipped，不进入本
     * 方法，其跳过原因同样写在日志的错误码字段里。
     *
     * @param PluginEventEnvelope|array<string, mixed> $event
     * @param array<string, mixed> $delivery
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function dispatchDelivery(
        PluginEventEnvelope|array $event,
        array $delivery,
        array $options = []
    ): array {
        $envelope = $event instanceof PluginEventEnvelope ? $event : PluginEventEnvelope::fromArray($event);
        $attempt = max(1, (int)($options['attempt'] ?? 1));
        $maxAttempts = max(1, (int)($options['max_attempts'] ?? $options['maxAttempts'] ?? 3));
        $log = $this->logs->beginDelivery($envelope, $delivery, $attempt, $options);
        // 日志的唯一键（delivery_key + attempt）同时充当并发锁：抢不到认领权
        // 说明这次尝试已在别处执行或已完成，直接返回既有状态即可。
        if (!(bool)($log['claimed'] ?? true)) {
            return [
                'status' => (string)($log['status'] ?? PluginEventLogService::STATUS_PROCESSING),
                'delivery_key' => (string)($log['delivery_key'] ?? ''),
                'attempt' => (int)($log['attempt'] ?? $attempt),
                'log' => $log,
                'duplicate' => true,
            ];
        }

        $startedAt = microtime(true);
        try {
            $scopedEvent = $envelope->forPlugin($delivery);
            $context = is_array($options['context'] ?? null) ? $options['context'] : $options;
            $context['event'] = $scopedEvent;
            $result = $this->invokeHandler($delivery, $scopedEvent, $context);
            $this->assertWithinTimeout($delivery, $startedAt);
            // observer 的返回值只用于表达失败：返回 false，或返回带失败状态/
            // ok=false 的数组都算投递失败；其他返回值（null、true、业务数据）
            // 一律忽略，observer 不参与载荷改写。
            if ($result === false || (is_array($result) && (
                in_array((string)($result['status'] ?? ''), ['failed', 'retrying', 'dead_letter'], true)
                || ($result['ok'] ?? true) === false
            ))) {
                throw new PluginEventException('Plugin observer returned failure', [
                    'plugin_code' => (string)($delivery['plugin_code'] ?? ''),
                    'handler' => (string)($delivery['handler'] ?? ''),
                ]);
            }
            $saved = $this->logs->markSuccess($log, $this->durationMs($startedAt));
            return [
                'status' => PluginEventLogService::STATUS_SUCCEEDED,
                'delivery_key' => (string)$log['delivery_key'],
                'attempt' => (int)$log['attempt'],
                'log' => $saved ?? $log,
                'result' => $result,
                'duplicate' => false,
            ];
        } catch (\Throwable $exception) {
            // 一切异常都在这里收敛为一条失败日志：errorCode 做分类、错误信息
            // 先脱敏再落库；retryable=false（例如结果非法等不可重试的失败）
            // 会直接进入 failed/dead_letter，不再重试。
            $saved = $this->logs->markFailure(
                $log,
                $this->errorCode($exception),
                $exception->getMessage(),
                $this->durationMs($startedAt),
                $maxAttempts,
                (bool)($options['retryable'] ?? true)
            );
            return [
                'status' => (string)($saved['status'] ?? PluginEventLogService::STATUS_DEAD_LETTER),
                'delivery_key' => (string)$log['delivery_key'],
                'attempt' => (int)$log['attempt'],
                'log' => $saved ?? $log,
                'error_code' => $this->errorCode($exception),
                'error_message' => $this->safeErrorMessage($exception),
                'duplicate' => false,
            ];
        }
    }

    /**
     * dispatchDelivery 的兼容别名。
     *
     * 保留给仍使用 observer 术语的调用方，行为与 dispatchDelivery() 完全一致，
     * 不额外做转换，也不重复写日志。
     *
     * @param PluginEventEnvelope|array<string, mixed> $event
     * @param array<string, mixed> $delivery
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function dispatchObserver(PluginEventEnvelope|array $event, array $delivery, array $options = []): array
    {
        return $this->dispatchDelivery($event, $delivery, $options);
    }

    /**
     * 重新构建当前订阅集，并显式重放一条 Outbox 记录。
     *
     * 重放不是重发旧快照：这里按“现在”的插件启用状态、作用域和版本重新
     * buildSnapshot()，再交给 Outbox 服务重放，保证重放时生效的是当前订阅
     * 关系（例如期间被禁用的插件不会被重新唤起），随后由队列按新快照投递。
     * Outbox 中不存在该记录时返回 null，由调用方决定提示还是忽略。
     *
     * @param int|string $identifier
     * @param array<string, mixed> $options
     * @return array<string, mixed>|null
     */
    public function replay(int|string $identifier, array $options = []): ?array
    {
        $row = $this->outbox->get($identifier);
        if ($row === null) {
            return null;
        }
        $envelope = $this->envelopeFromOutbox($row);
        // 上下文优先取显式传入的 context，否则整块选项作为上下文；
        // merchant_id/surface 缺失时从信封和默认值补齐，供订阅作用域重新匹配。
        $context = is_array($options['context'] ?? null) ? $options['context'] : $options;
        $context['merchant_id'] = $context['merchant_id'] ?? $envelope->getMerchantId();
        $context['surface'] = $context['surface'] ?? 'backend';
        $snapshot = $this->registry->buildSnapshot($envelope, $context);
        return $this->outbox->replay($identifier, $snapshot, [
            'dispatch' => (bool)($options['dispatch'] ?? true),
            'available_at' => $options['available_at'] ?? null,
        ]);
    }

    /**
     * 规范化发布入参，统一成 [事件信封, 生效选项]。
     *
     * publish() 支持字符串、信封、数组三种入参，这里把差异收敛在一次转换里：
     * 字符串按“事件名 + 载荷”新建信封；信封直接复用并合并选项；数组按序列化
     * 信封处理（可能来自跨进程传递或快照还原），缺省字段由信封构造阶段补齐，
     * 使 publish() 主体只需处理一种内部结构。
     *
     * @param string|PluginEventEnvelope|array<string, mixed> $event
     * @param array<string, mixed> $payloadOrOptions
     * @param array<string, mixed> $options
     * @return array{0: PluginEventEnvelope, 1: array<string, mixed>}
     */
    private function normalisePublishInput(string|PluginEventEnvelope|array $event, array $payloadOrOptions, array $options): array
    {
        if (is_string($event)) {
            return [PluginEventEnvelope::create($event, $payloadOrOptions, $options), $options];
        }
        if ($event instanceof PluginEventEnvelope) {
            return [$event, array_merge($payloadOrOptions, $options)];
        }
        $merged = array_merge($event, $options);
        $envelope = PluginEventEnvelope::fromArray($merged);
        return [$envelope, array_merge($payloadOrOptions, $options)];
    }

    /**
     * 规范化同步执行入参，统一成 [事件信封, 运行上下文]。
     *
     * 与发布入参的区别在于第二参数的语义随形态变化：字符串形态下它是载荷
     * （选项本身作为上下文），数组形态下整块作为运行上下文返回，信封形态下
     * 则把第二参数与选项合并成上下文。三种形态都不做业务校验，交给随后的
     * 信封构造与处理器解析负责，保证构造阶段是唯一的校验入口。
     *
     * @param string|PluginEventEnvelope|array<string, mixed> $event
     * @param array<string, mixed> $contextOrPayload
     * @param array<string, mixed> $options
     * @return array{0: PluginEventEnvelope, 1: array<string, mixed>}
     */
    private function normaliseSyncInput(string|PluginEventEnvelope|array $event, array $contextOrPayload, array $options): array
    {
        if (is_string($event)) {
            $envelope = PluginEventEnvelope::create($event, $contextOrPayload, $options);
            return [$envelope, $options];
        }
        if ($event instanceof PluginEventEnvelope) {
            return [$event, array_merge($contextOrPayload, $options)];
        }
        return [PluginEventEnvelope::fromArray(array_merge($event, $options)), $contextOrPayload];
    }

    /**
     * 调用单个插件处理器，并按声明调整入参形态。
     *
     * 这里同时兼容两代处理器：实现 PluginEventHandlerInterface 的新式处理器
     * 首参收完整信封；旧式处理器只收载荷数组。判定依据是描述符的
     * expects_payload 或首参的 array 类型声明，命中就把首参换成 getPayload()
     * （即已清洗、已按白名单裁剪的载荷）。只有处理器声明了两个及以上参数时
     * 才追加运行上下文，避免给只收一个参数的旧处理器多传值导致报错。
     *
     * 反射仅用于优化传参，失败不影响是否调用；处理器抛出的异常由各派发方法
     * 统一分类（拒绝 / 非法结果 / 超时 / 其他错误）。
     *
     * @param array<string, mixed> $descriptor 订阅描述符（含插件编码、处理器名、类型等）。
     * @param PluginEventEnvelope $event 已按插件白名单裁剪的事件信封。
     * @param array<string, mixed> $context 运行上下文（merchant_id、surface、event 等）。
     * @return mixed 处理器原始返回值，解析规则由调用方决定。
     */
    private function invokeHandler(array $descriptor, PluginEventEnvelope $event, array $context): mixed
    {
        $callable = $this->registry->resolveHandler($descriptor);
        $arguments = [$event];
        // 反射可能失败（返回 null），此时按“参数未知”处理：仍按新式契约
        // 只传信封，而不是直接报错中断整条事件链路。
        $reflection = $this->reflectCallable($callable);
        $parameters = $reflection?->getParameters() ?? [];
        if (isset($parameters[0])) {
            $parameterType = $parameters[0]->getType();
            // 首参声明 array 或描述符显式声明 expects_payload，说明是旧式
            // “只吃载荷”的处理器，改成传清洗后的载荷数组。
            if (($descriptor['expects_payload'] ?? false) === true
                || ($parameterType instanceof ReflectionNamedType && $parameterType->isBuiltin() && $parameterType->getName() === 'array')) {
                $arguments[0] = $event->getPayload();
            }
        }
        if (count($parameters) > 1) {
            $arguments[] = $context;
        }
        return $callable(...$arguments);
    }

    /**
     * 只向插件处理器暴露其接管的商品行和分组。
     *
     * `items` 为空时保留调用，便于通用总线执行无商品上下文的契约检查；
     * 一旦事件带有商品行但不存在当前插件商品，则跳过该处理器，避免
     * 混合购物车中无关插件被错误触发。
     *
     * @param array<string, mixed> $descriptor
     */
    private function scopePluginEvent(PluginEventEnvelope $envelope, array $descriptor): ?PluginEventEnvelope
    {
        $scoped = $envelope->forPlugin($descriptor);
        $payload = $scoped->getPayload();
        $pluginCode = strtolower(trim((string)($descriptor['plugin_code'] ?? '')));
        // 描述符缺少归属信息时无法做归属判定，按“不裁剪”返回，宁可多给数据
        // 也不误伤（该分支只出现在手工构造描述符的场景）。
        if ($pluginCode === '') {
            return $scoped;
        }

        // 商品行可能平铺在 items，也可能按分组放在 groups[].items；两种形态都
        // 要展开成一份扁平清单，才能判断当前插件是否接管了其中任何一行。
        $items = is_array($payload['items'] ?? null) ? $payload['items'] : null;
        if ($items === null && is_array($payload['groups'] ?? null)) {
            $items = [];
            foreach ($payload['groups'] as $group) {
                if (!is_array($group) || !is_array($group['items'] ?? null)) {
                    continue;
                }
                foreach ($group['items'] as $item) {
                    if (is_array($item)) {
                        $items[] = $item;
                    }
                }
            }
        }

        // 事件带商品行但一行都不属于当前插件时返回 null，交由调用方跳过：
        // 无商品上下文（items 为空）则保留调用，便于做通用的契约检查。
        $hasItems = is_array($items) && $items !== [];
        $ownedItems = [];
        foreach ($items ?? [] as $item) {
            if (is_array($item) && strtolower(trim((string)($item['plugin_code'] ?? ''))) === $pluginCode) {
                $ownedItems[] = $item;
            }
        }
        if ($hasItems && $ownedItems === []) {
            return null;
        }

        // 裁剪时保持载荷原有形态：原来有 items 就回填 items，原来有 groups
        // 就整组重建成只含本插件商品的分组，空分组直接丢弃。
        if (is_array($payload['items'] ?? null)) {
            $payload['items'] = $ownedItems;
        }
        if (is_array($payload['groups'] ?? null)) {
            $groups = [];
            foreach ($payload['groups'] as $group) {
                if (!is_array($group) || !is_array($group['items'] ?? null)) {
                    continue;
                }
                $groupItems = [];
                foreach ($group['items'] as $item) {
                    if (is_array($item) && strtolower(trim((string)($item['plugin_code'] ?? ''))) === $pluginCode) {
                        $groupItems[] = $item;
                    }
                }
                if ($groupItems === []) {
                    continue;
                }
                $group['items'] = $groupItems;
                $groups[] = $group;
            }
            $payload['groups'] = $groups;
        }

        return $scoped->with(['payload' => $payload]);
    }

    /**
     * 反射处理器可调用对象，用于判断它的参数形态。
     *
     * 支持数组 [$object, 'method']、'Class::method' 字符串和函数/闭包三种写法；
     * 反射结果只用于 invokeHandler() 调整入参，不影响“是否调用”，因此这里把
     * 反射异常全部吞掉并返回 null，由调用方按“参数未知”的默认路径继续。
     *
     * @param callable $callable 已由注册表解析出的处理器。
     * @return ReflectionFunction|ReflectionMethod|null 反射结果，无法反射时为 null。
     */
    private function reflectCallable(callable $callable): ReflectionFunction|ReflectionMethod|null
    {
        try {
            if (is_array($callable) && count($callable) === 2) {
                return new ReflectionMethod($callable[0], (string)$callable[1]);
            }
            if (is_string($callable) && str_contains($callable, '::')) {
                return new ReflectionMethod(...explode('::', $callable, 2));
            }
            return new ReflectionFunction($callable);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * 解析 guard 返回值，得到是否放行。
     *
     * 约定：false 拒绝；true 与 null（处理器无返回值）放行；数组按
     * action=reject、reject=true、allowed=false、ok=false 判定为拒绝。其余
     * 类型（字符串、数字、对象等）无法解释，直接抛
     * PluginEventInvalidResultException 失败关闭——猜一个布尔值等于让写错
     * 返回值的插件静默获得放行，风险高于让整条事件报错。
     *
     * @param mixed $result guard 处理器的原始返回值。
     * @return bool true 表示放行；返回值非法时抛异常而不是返回 false。
     */
    private function parseGuardResult(mixed $result): bool
    {
        if ($result === false) {
            return false;
        }
        // 无返回值的 guard 视为“不反对”，保持与旧处理器的兼容；
        // 拒绝必须显式表达，不会因为漏写 return 而被当成拒绝。
        if ($result === true || $result === null) {
            return true;
        }
        if (is_array($result)) {
            if (($result['action'] ?? '') === 'reject' || ($result['reject'] ?? false) === true || ($result['allowed'] ?? true) === false || ($result['ok'] ?? true) === false) {
                return false;
            }
            return true;
        }
        throw new PluginEventInvalidResultException('Plugin guard must return bool or a guard result');
    }

    /**
     * 规范化计算器返回值，取出报价数组。
     *
     * 约定：null/true 表示没有报价（返回空数组），false 表示主动拒绝；数组才
     * 可能携带报价——action 为 reject/deny 或 allowed/ok 为 false 时按拒绝
     * 处理；带 quote 键时只取 quote 子树，否则剥离控制字段后把数组本身当作
     * 报价。非数组、quote 非数组等无法解释的返回值抛
     * PluginEventInvalidResultException，避免脏数据进入宿主比价逻辑。
     *
     * @param mixed $result 计算器处理器的原始返回值。
     * @return array<string, mixed> 报价数组，可能为空。
     */
    private function parseCalculationResult(mixed $result): array
    {
        if ($result === null || $result === true) {
            return [];
        }
        if ($result === false) {
            throw new PluginEventRejectedException('Plugin calculator rejected event');
        }
        if (!is_array($result)) {
            throw new PluginEventInvalidResultException('Plugin calculator must return a quote array');
        }
        $action = strtolower((string)($result['action'] ?? ''));
        if (in_array($action, ['reject', 'deny'], true) || ($result['allowed'] ?? true) === false || ($result['ok'] ?? true) === false) {
            throw new PluginEventRejectedException((string)($result['message'] ?? 'Plugin calculator rejected event'));
        }
        if (array_key_exists('quote', $result)) {
            if (!is_array($result['quote'])) {
                throw new PluginEventInvalidResultException('Plugin calculator quote must be an array');
            }
            return $result['quote'];
        }
        // 走到这里说明返回的是裸报价数组：控制字段不属于报价内容，统一剥离，
        // 避免宿主把 action 之类的键当成价格字段参与计算。
        unset($result['action'], $result['allowed'], $result['ok'], $result['message']);
        return $result;
    }

    /**
     * 解析 filter 返回值，得到改写后的载荷。
     *
     * 约定：null/true 表示不改写；返回信封时取其载荷；false 表示拒绝；数组按
     * 下列优先级解释——action 为 pass/allow/noop 视为不改写，reject/deny 或
     * allowed/ok 为 false 表示拒绝，显式 payload 键（含 action=replace）表示
     * 完整替换载荷，只含 plugin_payload 时合并进当前载荷的插件命名空间，其余
     * 数组按“部分字段”与当前载荷合并。
     *
     * 无论走哪条分支，候选载荷最终都要再经 assertFilterMutation() 校验：filter
     * 只能改自己命名空间的内容；返回值无法解释为载荷时抛
     * PluginEventInvalidResultException 失败关闭。
     *
     * @param mixed $result filter 处理器的原始返回值。
     * @param PluginEventEnvelope $current 本 filter 执行前的事件信封（对照基准）。
     * @return array<string, mixed> 改写后的载荷，未改写时等于当前载荷。
     */
    private function parseFilterResult(mixed $result, PluginEventEnvelope $current): array
    {
        // 不改写是 filter 最常见的形态，null/true 都按“保持原载荷”处理。
        if ($result === null || $result === true) {
            return $current->getPayload();
        }
        if ($result instanceof PluginEventEnvelope) {
            return $result->getPayload();
        }
        if ($result === false) {
            throw new PluginEventRejectedException('Plugin filter rejected event');
        }
        if (!is_array($result)) {
            throw new PluginEventInvalidResultException('Plugin filter must return a payload array');
        }
        $action = strtolower((string)($result['action'] ?? ''));
        if (in_array($action, ['reject', 'deny'], true) || ($result['allowed'] ?? true) === false || ($result['ok'] ?? true) === false) {
            throw new PluginEventRejectedException('Plugin filter rejected event');
        }
        // 显式的“放行”动作：语义等同于不改写，避免插件误以为必须返回替换载荷。
        if (in_array($action, ['pass', 'allow', 'noop'], true)) {
            return $current->getPayload();
        }
        if (array_key_exists('payload', $result)) {
            if (!is_array($result['payload'])) {
                throw new PluginEventInvalidResultException('Plugin filter payload must be an array');
            }
            return $result['payload'];
        }
        // 只有命名空间片段时，合并到当前载荷；完整替换必须显式写
        // {action: replace, payload: ...}，避免歧义。
        if (array_key_exists('plugin_payload', $result) && count(array_diff(array_keys($result), ['plugin_payload'])) === 0) {
            $payload = $current->getPayload();
            $payload['plugin_payload'] = $result['plugin_payload'];
            return $payload;
        }
        if ($action === 'replace') {
            throw new PluginEventInvalidResultException('Plugin filter replace result requires payload');
        }
        // 对后台处理器而言，直接返回完整 payload 等价于
        // {action: replace, payload: ...}，但下面的命名空间校验仍会阻止
        // 修改宿主字段。
        if ($action === '' && !array_key_exists('allowed', $result) && !array_key_exists('ok', $result)) {
            $currentPayload = $current->getPayload();
            $isPartial = true;
            foreach (array_keys($result) as $key) {
                if ($key !== 'plugin_payload' && !array_key_exists($key, $currentPayload)) {
                    $isPartial = false;
                    break;
                }
            }
            if ($isPartial) {
                return array_replace($currentPayload, $result);
            }
            return $result;
        }
        if (array_key_exists('allowed', $result) || array_key_exists('ok', $result)) {
            return $current->getPayload();
        }
        throw new PluginEventInvalidResultException('Plugin filter result must contain action and payload');
    }

    /**
     * 校验 filter 没有改动宿主字段或其他插件的数据。
     *
     * filter 只允许改写 plugin_payload 下自己插件编码对应的命名空间：宿主字段
     * （plugin_payload 之外的键）只要与改写前有任何差异，或者改动、新建了别的
     * 插件的命名空间，都抛 PluginEventInvalidResultException 让整条事件失败
     * 关闭，防止插件借 filter 越权篡改宿主数据或干扰其他插件。
     *
     * @param array<string, mixed> $before 改写前的载荷。
     * @param array<string, mixed> $candidate 改写后的候选载荷。
     * @param string $pluginCode 当前执行的插件编码，即允许改写的命名空间。
     * @return array<string, mixed> 校验通过的候选载荷。
     */
    private function assertFilterMutation(array $before, array $candidate, string $pluginCode): array
    {
        $beforePluginPayload = is_array($before['plugin_payload'] ?? null) ? $before['plugin_payload'] : [];
        $candidatePluginPayload = is_array($candidate['plugin_payload'] ?? null) ? $candidate['plugin_payload'] : [];
        $beforeCore = $before;
        $candidateCore = $candidate;
        unset($beforeCore['plugin_payload'], $candidateCore['plugin_payload']);
        if ($beforeCore !== $candidateCore) {
            throw new PluginEventInvalidResultException('Plugin filter attempted to modify host-owned payload');
        }
        foreach ($beforePluginPayload as $code => $value) {
            if ((string)$code !== $pluginCode && (!array_key_exists($code, $candidatePluginPayload) || $candidatePluginPayload[$code] !== $value)) {
                throw new PluginEventInvalidResultException('Plugin filter attempted to modify another plugin namespace');
            }
        }
        foreach ($candidatePluginPayload as $code => $_value) {
            if ((string)$code !== $pluginCode && !array_key_exists($code, $beforePluginPayload)) {
                throw new PluginEventInvalidResultException('Plugin filter attempted to create another plugin namespace');
            }
        }
        return $candidate;
    }

    /**
     * 组装异常与日志使用的定位上下文。
     *
     * 只携带事件 id、事件名、插件编码、处理器名和处理器类型这几项排查必需的
     * 标识，不含任何载荷内容，因此可以安全地写入异常上下文和日志，不会把
     * 插件数据或宿主敏感字段带出去。
     *
     * @param array<string, mixed> $descriptor 订阅描述符。
     * @param PluginEventEnvelope $event 事件信封（只读取标识字段）。
     * @return array<string, mixed> 可安全落日志的定位上下文。
     */
    private function handlerContext(array $descriptor, PluginEventEnvelope $event): array
    {
        return [
            'event_id' => $event->getEventId(),
            'event' => $event->getName(),
            'plugin_code' => (string)($descriptor['plugin_code'] ?? ''),
            'handler' => (string)($descriptor['handler'] ?? ''),
            'type' => (string)($descriptor['type'] ?? ''),
        ];
    }

    /**
     * 判定处理器是否超出其配置的时间预算，超时即抛异常。
     *
     * 超时取自订阅描述符的 timeout_ms（兼容 timeout 别名），0 表示不限制
     * （observer 场景由队列侧控制）。判定发生在“调用返回之后”，所以它只能
     * 把已经超时的处理器标记为失败，无法真正中断仍在运行的插件代码，插件
     * 内部仍需自行控制耗时。超时按失败关闭处理，错误码归类为 handler_timeout。
     *
     * @param array<string, mixed> $descriptor 订阅描述符（含 timeout_ms 配置）。
     * @param float $startedAt 调用处理器前的 microtime(true) 时间戳。
     * @throws PluginEventTimeoutException 超出配置的时间预算时抛出。
     */
    private function assertWithinTimeout(array $descriptor, float $startedAt): void
    {
        $timeout = max(0, (int)($descriptor['timeout_ms'] ?? $descriptor['timeout'] ?? 0));
        // 只有配置了正数超时才判定；未配置（0）表示信任插件自身耗时。
        if ($timeout > 0 && (microtime(true) - $startedAt) * 1000 > $timeout) {
            throw new PluginEventTimeoutException('Plugin event handler timed out', [
                'plugin_code' => (string)($descriptor['plugin_code'] ?? ''),
                'handler' => (string)($descriptor['handler'] ?? ''),
                'timeout_ms' => $timeout,
            ]);
        }
    }

    /**
     * 计算从 $startedAt 到当前时刻的毫秒数。
     *
     * 供 handlers 明细与投递日志记录耗时：结果统一取整并钳制为非负，避免
     * 时钟回拨或浮点误差让日志里出现负耗时，保证统计口径始终合法。
     *
     * @param float $startedAt 起始 microtime(true) 时间戳。
     * @return int 非负的耗时毫秒数。
     */
    private function durationMs(float $startedAt): int
    {
        return max(0, (int)round((microtime(true) - $startedAt) * 1000));
    }

    /**
     * 把异常映射为投递日志使用的错误码。
     *
     * 三类可识别失败分别对应 handler_timeout（超时）、handler_rejected
     * （插件拒绝）、invalid_result（返回值非法），其余异常统一归为
     * handler_error。错误码只做分类，具体原因由脱敏后的错误信息承载，
     * 便于按码统计、告警和区分“插件主动拒绝”与“插件实现出错”。
     *
     * 三类异常都是 PluginEventException 的兄弟子类，必须逐个精确匹配；不能
     * 只判断父类，否则超时、拒绝、非法结果会被压成同一个错误码，失去区分度。
     *
     * @param \Throwable $exception 处理器或投递链路抛出的异常。
     * @return string 机器可读的错误码。
     */
    private function errorCode(\Throwable $exception): string
    {
        if ($exception instanceof PluginEventTimeoutException) {
            return 'handler_timeout';
        }
        if ($exception instanceof PluginEventRejectedException) {
            return 'handler_rejected';
        }
        if ($exception instanceof PluginEventInvalidResultException) {
            return 'invalid_result';
        }
        return 'handler_error';
    }

    /**
     * 生成可安全写入日志的错误信息。
     *
     * 三步处理：先把换行、制表等控制字符压成空格，避免日志被拆行或注入伪造
     * 内容；再把 password/secret/token/authorization 后跟值的片段整体替换成
     * 占位文本（模板里的 $1 没有对应捕获组、会展开为空串，效果是键值一起
     * 抹掉），防止插件异常消息里夹带的凭据落库；最后截断到 500 字符控制日志
     * 体积。处理后的文本才允许写入投递日志和对外返回。
     *
     * @param \Throwable $exception 待脱敏的异常。
     * @return string 已去除控制字符、凭据片段并截断的错误信息。
     */
    private function safeErrorMessage(\Throwable $exception): string
    {
        $message = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $exception->getMessage()) ?: '';
        $message = preg_replace('/(?:password|secret|token|authorization)\s*[:=]\s*[^\s,;]+/i', '$1:[redacted]', $message) ?: $message;
        return mb_substr(trim($message), 0, 500);
    }

    /**
     * 从 Outbox 记录还原事件信封。
     *
     * 供 replay() 把 Outbox 行还原成可再次投递的事件：行里的 payload、actor、
     * 订阅快照可能来自数据库（JSON 字符串），也可能已被内存适配器预处理成
     * 数组，这里两种形态都接受；字段名
     * 同时兼容下划线表字段与旧写法（event_version/version、actor_json/actor
     * 等），缺失值回退到默认值，保证任意一行历史记录都能重建出可投递的信封。
     *
     * @param array<string, mixed> $row Outbox 记录行。
     * @return PluginEventEnvelope 还原出的事件信封。
     */
    private function envelopeFromOutbox(array $row): PluginEventEnvelope
    {
        // 快照可能是 JSON 字符串（数据库列）或数组（内存适配器）；解析失败时
        // 退回空数组，只影响 occurred_at 的回退取值，不阻断重放流程。
        $snapshot = $row['subscriber_snapshot_json'] ?? $row['subscriber_snapshot'] ?? [];
        if (is_string($snapshot)) {
            $decodedSnapshot = json_decode($snapshot, true);
            $snapshot = is_array($decodedSnapshot) ? $decodedSnapshot : [];
        }
        $actor = $row['actor_json'] ?? $row['actor'] ?? null;
        if (is_string($actor)) {
            $actor = json_decode($actor, true);
        }
        $payload = $row['payload_json'] ?? $row['payload'] ?? [];
        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }
        // 字段名同时兼容表结构与旧快照写法；occurred_at 缺失时依次回退到快照
        // 和建档时间，保证重放出来的事件仍有可用于排序的时间戳。
        return PluginEventEnvelope::create((string)$row['name'], is_array($payload) ? $payload : [], [
            'event_id' => (string)$row['event_id'],
            'version' => (string)($row['event_version'] ?? $row['version'] ?? PluginEventEnvelope::DEFAULT_VERSION),
            'occurred_at' => (string)($row['occurred_at'] ?? $snapshot['occurred_at'] ?? $row['create_time'] ?? ''),
            'trace_id' => (string)($row['trace_id'] ?? ''),
            'actor' => is_array($actor) ? $actor : null,
            'merchant_id' => (int)($row['merchant_id'] ?? 0),
            'subject' => [
                'type' => (string)($row['subject_type'] ?? ''),
                'id' => (string)($row['subject_id'] ?? ''),
            ],
            'idempotency_key' => (string)($row['idempotency_key'] ?? $row['event_id']),
        ]);
    }

    /**
     * 解析插件注册表，容器不可用时退回进程内实现。
     *
     * 有容器时从容器取共享注册表，保证同一进程内各调用方看到同一份订阅关系；
     * 无容器（单元测试、纯 CLI）或解析结果类型不符时返回一个空注册表，让总线
     * 降级为“没有任何订阅者”而不是直接崩溃——此时同步链路没有 guard/filter
     * 会直接放行，observer 也不会被投递，符合降级预期。
     *
     * @return PluginRegistry 注册表实例。
     */
    private function resolveRegistry(): PluginRegistry
    {
        try {
            if (function_exists('app')) {
                $instance = app()->make(PluginRegistry::class);
                if ($instance instanceof PluginRegistry) {
                    return $instance;
                }
            }
        } catch (\Throwable) {
            // 无容器或解析失败属于预期内的降级场景（单元测试、纯 CLI），
            // 吞掉异常改用进程内注册表，避免构造函数直接失败。
        }
        return new PluginRegistry();
    }

    /**
     * 从容器解析服务，失败时退回传入的进程内实例。
     *
     * 与 resolveRegistry() 相同的降级思路，但允许调用方注入共享实例：例如测试
     * 里让总线、Outbox、日志服务共用同一份内存适配器，保证无容器环境下三者
     * 仍能看到彼此写入的数据。容器解析结果类型不符时同样使用兜底实例，
     * 避免拿到不符合契约的对象。
     *
     * @template T of object
     * @param class-string<T> $class 目标服务类名。
     * @param T $fallback 容器不可用时使用的实例。
     * @return T 解析到的服务实例。
     */
    private function resolveService(string $class, object $fallback): object
    {
        try {
            if (function_exists('app')) {
                $instance = app()->make($class);
                if ($instance instanceof $class) {
                    return $instance;
                }
            }
        } catch (\Throwable) {
            // 同上：容器不可用时静默降级，由调用方注入的实例兜底。
        }
        return $fallback;
    }
}
