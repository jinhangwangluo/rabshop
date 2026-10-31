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

namespace app\common\plugin;

use app\common\plugin\event\PluginEventEnvelope;
use InvalidArgumentException;

/**
 * 已安装插件元数据和后台事件处理器的运行时注册表。
 *
 * 注册表回答两类问题：某个插件当前是否可用（元数据、启用开关、生命周期、
 * 商户范围、投放端 surfaces），以及某个事件当前该交给哪些处理器、按什么
 * 顺序执行（订阅者 + 明确的跳过原因）。宿主侧的同步 guard/filter/calculator/
 * transaction、Outbox 投递任务、订阅快照和测试都从同一个实例取数据，
 * 保证宿主与插件对"谁在监听、监听什么"有同一份认知。
 *
 * 这个注册表刻意保持进程内局部状态。安装逻辑应在重载后根据已签名
 * 的 manifest 重新构建它；请求处理中不允许直接修改共享的 Swoole
 * 监听表。
 *
 * 关键不变量：
 * - 注册表只是"安装结果 + manifest 声明"的运行时视图，不负责持久化；
 *   安装、启停、卸载必须先落库，再由 PluginRegistryBootstrap 在 worker
 *   启动（或重载）后 clear() + registerManifest() 重建，请求处理中不允许
 *   增删共享监听表；
 * - 可用性不跨请求缓存：每次 match() 都按当次 context（商户、投放端）现算，
 *   避免上一个请求的判定结果泄漏给下一个请求；
 * - 所有外部输入（插件编码、事件名、优先级、surfaces、payload_fields）在入口
 *   归一化或拒绝，注册表内部只保留可信结构，下游可直接读取字段；
 * - 匹配失败一律失败关闭（skip）：注册信息缺失或不合法永远解释为"不执行"，
 *   绝不能因为拿不准就把插件放行到下单等关键路径上。
 */
final class PluginRegistry
{
    /**
     * 插件处理器类型白名单，供 Provider/测试和运行时注册器共同使用。
     *
     * @var list<string>
     */
    public const HANDLER_TYPES = ['guard', 'filter', 'calculator', 'transaction', 'observer'];

    /** 未显式指定优先级时使用的默认值，数值越小越先执行。 */
    private const DEFAULT_PRIORITY = 1000;

    /**
     * 同步处理器未配置超时时的兜底毫秒数。
     *
     * calculator/transaction 跑在下单等关键路径上，必须有有限超时兜底，
     * 不允许无限等待插件返回。
     */
    private const DEFAULT_SYNC_TIMEOUT_MS = 2000;

    /**
     * 已注册插件，键为规范化插件编码，值为元数据 + manifest 快照。
     *
     * @var array<string, array<string, mixed>>
     */
    private array $plugins = [];

    /**
     * 事件处理器表，第一层键为事件名，值为已按优先级排序的描述符列表。
     *
     * @var array<string, list<array<string, mixed>>>
     */
    private array $handlers = [];

    /**
     * 进程内 callable 索引，键由 callableKey() 生成。
     *
     * 描述符里的 callable 无法跨进程传递（闭包、容器实例等），这份索引只在
     * 当前 worker 有效；写入快照前必须剥离，消费端再通过 resolveHandler()
     * 重新解析。
     *
     * @var array<string, callable>
     */
    private array $callables = [];

    /**
     * 最近一次 match() 的跳过明细，键为事件名。
     *
     * 只服务于"为什么这个插件没执行"的排查与后台展示，不参与匹配判定。
     *
     * @var array<string, list<array<string, mixed>>>
     */
    private array $skipped = [];

    /**
     * 用一批插件声明初始化注册表。
     *
     * 批量入口，供 Provider、引导流程和测试使用，兼容三种写法：字符串值
     * （键为别名、值为插件编码）、自带 code 的 manifest 数组、以及
     * 「插件编码 => 元数据」的映射。无法识别的项被忽略而不是抛异常，
     * 但编码本身非法时会交由 registerPlugin() 抛异常：安装阶段的问题应当
     * 显式暴露，而不是让插件"静默消失"。
     *
     * @param array<int|string, mixed> $plugins 插件编码或 manifest 元数据集合。
     */
    public function __construct(array $plugins = [])
    {
        foreach ($plugins as $key => $plugin) {
            if (is_string($plugin) && !is_int($key)) {
                // 键为别名、值为插件编码：只有编码可信，直接按编码注册。
                $this->registerPlugin($plugin);
                continue;
            }
            if (is_array($plugin)) {
                if (isset($plugin['code'])) {
                    // 自带 code 的 manifest 快照：整份作为元数据注册。
                    $this->registerPlugin($plugin);
                } elseif (is_string($key)) {
                    // 「插件编码 => 元数据」映射：键即编码。
                    $this->registerPlugin($key, $plugin);
                }
            }
        }
    }

    /**
     * 注册一个已安装插件或一个 manifest 快照。
     *
     * 注册是"合并覆盖"语义：在已有记录上叠加本次元数据，所以 worker 重载时
     * 用最新 manifest 加数据库安装状态反复注册是安全的，不会被上一次的旧字段
     * 黏住。取值优先级统一为「显式元数据 > manifest > 既有记录」，缺字段时
     * 才继续向后回退，保证局部更新不会把已有声明抹成空。
     *
     * 生命周期的处理刻意单向收紧：uninstalled/disabled/disabling/uninstalling
     * 一律把 enabled 强制为 false，反向则不成立——启用必须走 enablePlugin()，
     * 否则 manifest 里写一个 enabled 字段就能绕过安装状态。
     *
     * @param string|array<string, mixed> $plugin 插件编码，或包含 code 的 manifest 快照。
     * @param array<string, mixed> $metadata 安装态元数据（enabled、lifecycle_status、merchant_scope 等）。
     * @throws InvalidArgumentException 插件编码非法时抛出。
     */
    public function registerPlugin(string|array $plugin, array $metadata = []): self
    {
        if (is_array($plugin)) {
            $metadata = array_merge($plugin, $metadata);
            $code = (string)($metadata['code'] ?? '');
        } else {
            $code = trim($plugin);
        }
        $code = $this->normalisePluginCode($code);

        $existing = $this->plugins[$code] ?? [];
        // 安装态记录通常把 manifest 放在独立字段里；直接传 manifest 本身时
        // 退回用整份元数据作为 manifest，两种入口共用同一套字段解析。
        $manifest = is_array($metadata['manifest'] ?? null) ? $metadata['manifest'] : $metadata;
        $events = is_array($manifest['events'] ?? null)
            ? $manifest['events']
            : ($existing['events'] ?? []);
        // 开关取显式元数据优先，其次 manifest，都没有时默认开启（调用方保证
        // 只在已安装场景注册）；后置的生命周期校验会把不该开启的再次关掉。
        $enabled = array_key_exists('enabled', $metadata)
            ? (bool)$metadata['enabled']
            : (array_key_exists('enabled', $manifest) ? (bool)$manifest['enabled'] : true);
        $lifecycleStatus = strtolower(trim((string)($metadata['lifecycle_status'] ?? $manifest['lifecycle_status'] ?? '')));
        if (in_array($lifecycleStatus, ['uninstalled', 'disabled', 'disabling', 'uninstalling'], true)) {
            // 过渡态（disabling/uninstalling）也按不可用处理：卸载/禁用过程中
            // 不允许再产生新的投递，避免"卸载了一半还在执行"。
            $enabled = false;
        }

        $pluginData = array_merge($existing, $metadata, [
            'code' => $code,
            'version' => trim((string)($metadata['version'] ?? $manifest['version'] ?? $existing['version'] ?? '')),
            'enabled' => $enabled,
            'lifecycle_status' => $lifecycleStatus,
            'surfaces' => $this->normaliseSurfaces($metadata['surfaces'] ?? $manifest['surfaces'] ?? $existing['surfaces'] ?? []),
            'events' => $events,
            'events_consumes' => $this->normaliseEventList(
                $metadata['events_consumes']
                    ?? $metadata['consumes']
                    ?? $events['consumes']
                    ?? $existing['events_consumes']
                    ?? []
            ),
            'merchant_scope' => $metadata['merchant_scope']
                ?? $metadata['merchant_ids']
                ?? $manifest['merchant_scope']
                ?? $existing['merchant_scope']
                ?? null,
            'scope_checker' => is_callable($metadata['scope_checker'] ?? null)
                ? $metadata['scope_checker']
                : ($existing['scope_checker'] ?? null),
        ]);
        $this->plugins[$code] = $pluginData;

        // 每次注册都重新解析 manifest 处理器，保证"先注册插件、后补声明"时
        // 处理器跟着最新声明走；重复注册由 registerManifestHandlerDefinition() 兜底幂等。
        $this->registerManifestHandlers($code, $events, $metadata['handlers'] ?? $manifest['handlers'] ?? []);
        return $this;
    }

    /**
     * registerPlugin() 的语义化别名，供插件 Provider 引导 manifest 时调用。
     *
     * 行为与 registerPlugin($manifest) 完全一致，单独保留一个名字是为了让
     * "装载一份 manifest"这个意图在调用点更直观。
     *
     * @param array<string, mixed> $manifest 插件清单数组，需包含 code 字段。
     */
    public function registerManifest(array $manifest): self
    {
        return $this->registerPlugin($manifest);
    }

    /**
     * 注册后台处理器，是订阅关系的唯一落点。
     *
     * 标准调用顺序是 (plugin, event, type, handler, options)。为了兼容早期
     * 草案，也接受 (plugin, event, handler, type, options)。
     *
     * 注册成功后返回描述符，它同时是：进程内 handlers 表的表项、写入 Outbox
     * 的订阅快照项、以及投递日志里的处理器标识，因此描述符字段（含
     * timeout_ms、plugin_version、declared）必须在注册阶段一次性定型，不在
     * 投递时再计算。
     *
     * 拒绝而不是容错的几种情况：处理器类型不在白名单、同一插件在同一事件上
     * 登记同名处理器、处理器既不是 callable 也不是类/方法字符串。这些都会让
     * 运行期行为不可预期，宁可注册阶段直接失败。
     *
     * @param string $pluginCode 插件编码，未注册时会先补一份空记录。
     * @param string $eventName 事件名。
     * @param mixed $type 处理器类型，或旧版 handler 值。
     * @param mixed $handler 可调用对象或类/方法字符串。
     * @param array<string, mixed> $options 注册选项（priority、surfaces、merchant_scope、payload_fields、timeout_ms 等）。
     * @return array<string, mixed> 已登记并排序后的处理器描述符。
     * @throws InvalidArgumentException 插件编码/事件名非法、类型非法、处理器重复或无法识别时抛出。
     */
    public function registerHandler(
        string $pluginCode,
        string $eventName,
        mixed $type,
        mixed $handler,
        array $options = []
    ): array {
        // 先按标准顺序解析；如果第三个参数不是类型，则尝试兼容旧版
        // (plugin, event, handler, type, options) 传法。
        if (!is_string($type) || !in_array(strtolower(trim($type)), self::HANDLER_TYPES, true)) {
            if (is_string($handler) && in_array(strtolower(trim($handler)), self::HANDLER_TYPES, true)) {
                [$type, $handler] = [$handler, $type];
            }
        }
        $pluginCode = $this->normalisePluginCode($pluginCode);
        $eventName = $this->normaliseEventName($eventName);
        $type = is_string($type) ? strtolower(trim($type)) : '';
        if (!in_array($type, self::HANDLER_TYPES, true)) {
            throw new InvalidArgumentException('Unsupported plugin event handler type');
        }
        if (!isset($this->plugins[$pluginCode])) {
            // 允许"先订阅后注册"：先补一份空元数据占位，后续 registerPlugin()
            // 会合并覆盖，避免订阅因为时序问题被静默丢弃。
            $this->registerPlugin($pluginCode);
        }

        // 处理器名是去重、快照和日志的键，必须先归一化再判重。
        $handlerName = $this->normaliseHandlerName($handler, $options);
        foreach ($this->handlers[$eventName] ?? [] as $registered) {
            if ($registered['plugin_code'] === $pluginCode && $registered['handler'] === $handlerName) {
                // 同一插件在同一事件上重复登记同一处理器属于配置错误：留下两份
                // 会让处理器被重复执行，因此直接拒绝而不是去重。
                throw new InvalidArgumentException('Duplicate plugin event handler: ' . $pluginCode . '/' . $eventName . '/' . $handlerName);
            }
        }

        $descriptor = [
            'plugin_code' => $pluginCode,
            'event' => $eventName,
            'name' => $eventName,
            'type' => $type,
            'handler' => $handlerName,
            'priority' => $this->normalisePriority($options['priority'] ?? self::DEFAULT_PRIORITY),
            'enabled' => array_key_exists('enabled', $options) ? (bool)$options['enabled'] : true,
            'surfaces' => $this->normaliseSurfaces($options['surfaces'] ?? $options['platforms'] ?? []),
            'merchant_scope' => $options['merchant_scope'] ?? $options['merchant_ids'] ?? null,
            'payload_fields' => $this->normalisePayloadFields($options['payload_fields'] ?? $options['allowed_payload_fields'] ?? []),
            // calculator/transaction 属于同步关键路径；未显式配置或传 0 时采用
            // 默认超时并失败关闭，避免插件无限阻塞下单事务。
            'timeout_ms' => $this->normaliseTimeoutMs($type, $options),
            'plugin_version' => (string)($options['plugin_version'] ?? $this->plugins[$pluginCode]['version'] ?? ''),
            'declared' => array_key_exists('declared', $options) ? (bool)$options['declared'] : true,
        ];

        if (is_callable($handler)) {
            $this->callables[$this->callableKey($pluginCode, $eventName, $handlerName)] = $handler;
            $descriptor['callable'] = $handler;
        } elseif (is_string($handler)) {
            // 把类/方法字符串保留下来，方便新进程 worker 重新解析。
            $descriptor['callable'] = null;
        } else {
            throw new InvalidArgumentException('Plugin event handler must be callable or a class method string');
        }

        // 追加后立即重排，保证任何时刻读到的 handlers 表都是最终顺序，
        // 读取方（match/subscribers）不需要自己再做一次排序。
        $this->handlers[$eventName][] = $descriptor;
        $this->sortHandlers($eventName);
        return $descriptor;
    }

    /**
     * registerHandler() 的语义化别名，供插件以"订阅"语义登记处理器。
     *
     * 兼容三种参数形态：(type, handler)、(handler, type) 和只传 handler
     * （按 observer 处理）。之所以容忍顺序，是因为早期插件草案里两种顺序
     * 都出现过，别名层负责把历史写法收敛到同一份描述符结构，而不是让每种
     * 顺序各自写入一套数据。
     *
     * @param mixed $typeOrHandler 处理器类型或处理器。
     * @param mixed $handlerOrType 处理器或处理器类型，缺省 observer。
     * @param array<string, mixed> $options 注册选项，见 registerHandler()。
     * @return array<string, mixed> 处理器描述符。
     * @throws InvalidArgumentException 参数无法解析为合法处理器时抛出。
     */
    public function subscribe(
        string $pluginCode,
        string $eventName,
        mixed $typeOrHandler,
        mixed $handlerOrType = 'observer',
        array $options = []
    ): array {
        $types = self::HANDLER_TYPES;
        $firstIsType = is_string($typeOrHandler)
            && in_array(strtolower(trim($typeOrHandler)), $types, true);
        $secondIsType = is_string($handlerOrType)
            && in_array(strtolower(trim($handlerOrType)), $types, true);

        if ($firstIsType) {
            $type = strtolower(trim($typeOrHandler));
            $handler = $handlerOrType;
        } elseif ($secondIsType) {
            $type = strtolower(trim($handlerOrType));
            $handler = $typeOrHandler;
        } else {
            // 三参数形式 (plugin, event, handler) 默认按 observer 处理。
            // 只有第三个值确实是合法处理器时，第四个参数才会被视为 handler。
            $type = 'observer';
            $handler = $typeOrHandler;
            if ($handlerOrType !== 'observer' && $handlerOrType !== null) {
                // 第四参数既不是合法类型也不是默认值：它对本方法的语义没有
                // 定义，因此忽略它、继续用第三个参数当处理器，避免把占位值
                // 误当成处理器名写进描述符。
                $handler = $typeOrHandler;
            }
        }
        return $this->registerHandler($pluginCode, $eventName, $type, $handler, $options);
    }

    /**
     * 在当前 worker 注册表内启用插件（只影响本进程内存，不写库）。
     *
     * 启用是比禁用更强的意图，因此这里同时把生命周期从禁用/卸载态校正回
     * installed：否则匹配时会被生命周期校验再次拦下，出现"已经启用了却不
     * 生效"的矛盾状态。持久化（数据库里的 enabled / lifecycle_status）由调用
     * 方负责，注册表只反映本进程的当前视图。
     *
     * @param string $pluginCode 插件编码，未注册时先补一份空记录。
     * @throws InvalidArgumentException 插件编码非法时抛出。
     */
    public function enablePlugin(string $pluginCode): self
    {
        $pluginCode = $this->normalisePluginCode($pluginCode);
        if (!isset($this->plugins[$pluginCode])) {
            $this->registerPlugin($pluginCode);
        }
        $this->plugins[$pluginCode]['enabled'] = true;
        if (in_array(
            strtolower((string)($this->plugins[$pluginCode]['lifecycle_status'] ?? '')),
            ['uninstalled', 'disabled', 'disabling', 'uninstalling'],
            true
        )) {
            $this->plugins[$pluginCode]['lifecycle_status'] = 'installed';
        }
        return $this;
    }

    /**
     * 在当前 worker 注册表内禁用插件（只影响本进程内存，不写库）。
     *
     * 只把开关和生命周期标记为不可用，不删除处理器描述符：已进入 Outbox 的
     * 投递会由分发任务按可用性判定跳过并记录原因，重新启用后原订阅关系仍在，
     * 不需要重新注册。插件未注册时静默返回，不做隐式注册。
     *
     * @param string $pluginCode 插件编码。
     * @throws InvalidArgumentException 插件编码非法时抛出。
     */
    public function disablePlugin(string $pluginCode): self
    {
        $pluginCode = $this->normalisePluginCode($pluginCode);
        if (!isset($this->plugins[$pluginCode])) {
            return $this;
        }
        $this->plugins[$pluginCode]['enabled'] = false;
        $this->plugins[$pluginCode]['lifecycle_status'] = 'disabled';
        return $this;
    }

    /**
     * 从当前 Worker 注册表移除插件及其处理器。
     *
     * 卸载要同时清三处，保证不会留下"孤儿订阅"：插件元数据、按事件分组的
     * 处理器表、以及进程内 callable 索引（索引不清理会让闭包一直持有引用，
     * 既占内存也可能在插件已卸载后仍被解析执行）。
     * 这里只改本进程内存，持久化卸载仍由数据库和 worker 重载完成。
     *
     * @param string $pluginCode 插件编码。
     * @return self 当前实例，便于链式调用。
     * @throws InvalidArgumentException 插件编码非法时抛出。
     */
    public function unregisterPlugin(string $pluginCode): self
    {
        $pluginCode = $this->normalisePluginCode($pluginCode);
        unset($this->plugins[$pluginCode]);
        foreach ($this->handlers as $event => $descriptors) {
            // 先按事件整体过滤掉该插件的描述符，array_values 保证列表键连续，
            // 下游可以安全地按 list 语义使用。
            $this->handlers[$event] = array_values(array_filter(
                $descriptors,
                static fn(array $descriptor): bool => $descriptor['plugin_code'] !== $pluginCode
            ));
            foreach ($descriptors as $descriptor) {
                if (($descriptor['plugin_code'] ?? '') === $pluginCode) {
                    unset($this->callables[$this->callableKey($pluginCode, (string)$event, (string)$descriptor['handler'])]);
                }
            }
        }
        return $this;
    }

    /**
     * 判断插件是否处于启用状态。
     *
     * 开关与生命周期必须同时满足：只有 enabled 为真、且生命周期不在
     * 禁用/卸载集合内才算可用；未注册或任一字段缺省都按不可用处理
     * （fail closed），因为"状态读不出来"不应该被解释成"可以执行"。
     *
     * @param string $pluginCode 插件编码。
     * @return bool 启用返回 true；未注册、被禁用或处于卸载流程中返回 false。
     */
    public function isPluginEnabled(string $pluginCode): bool
    {
        // 查询接口用宽松归一化而不是 normalisePluginCode()：脏编码在这里只应
        // 表现为"查不到"，不该抛异常影响调用方的状态判断。
        $plugin = $this->plugins[strtolower(trim($pluginCode))] ?? null;
        if (!is_array($plugin) || !(bool)($plugin['enabled'] ?? false)) {
            return false;
        }
        return !in_array(
            strtolower((string)($plugin['lifecycle_status'] ?? '')),
            ['disabled', 'disabling', 'uninstalled', 'uninstalling'],
            true
        );
    }

    /**
     * 读取单个插件的注册信息。
     *
     * 返回的是数组值拷贝，调用方修改不会影响注册表内部状态；未注册或编码
     * 不合法时返回 null 而不抛异常，方便调用方直接做"缺数据兜底"判断。
     *
     * @param string $pluginCode 插件编码。
     * @return array<string, mixed>|null 插件元数据；未注册时为 null。
     */
    public function getPlugin(string $pluginCode): ?array
    {
        return $this->plugins[strtolower(trim($pluginCode))] ?? null;
    }

    /**
     * 返回全部已注册插件的元数据。
     *
     * 供后台插件列表、诊断脚本和测试读取整体视图；返回值同为数组拷贝，
     * 调用方不能借此修改注册表状态。
     *
     * @return array<string, array<string, mixed>> 键为插件编码，值为插件元数据。
     */
    public function allPlugins(): array
    {
        return $this->plugins;
    }

    /**
     * 获取某个事件的活跃且已排序的处理器。
     *
     * 是 match() 的简化视图：只关心"能跑的订阅者"，跳过原因被丢弃。需要向
     * 调用方解释"某个插件为什么没执行"时应改用 match() 读 skipped 明细。
     * 不传上下文时按全局事件（merchant_id=0）和 backend 投放端匹配。
     *
     * @param PluginEventEnvelope|array<string, mixed>|null $eventOrContext 事件信封或匹配上下文。
     * @param string|null $type 只取指定类型的处理器；null 表示不过滤。
     * @return list<array<string, mixed>> 已按优先级排序的处理器描述符列表。
     */
    public function getSubscribers(string $eventName, PluginEventEnvelope|array|null $eventOrContext = null, ?string $type = null): array
    {
        $eventName = $this->normaliseEventName($eventName);
        $context = $this->normaliseContext($eventOrContext);
        $result = $this->match($eventName, $context, $type);
        return $result['subscribers'];
    }

    /**
     * getSubscribers() 的兼容别名，供仍使用 handlers 术语的 provider 调用。
     *
     * 语义与 getSubscribers() 完全一致，保留独立名字只为兼容历史调用点，
     * 不额外做任何过滤或排序差异。
     *
     * @param PluginEventEnvelope|array<string, mixed>|null $eventOrContext 事件信封或匹配上下文。
     * @param string|null $type 只取指定类型的处理器；null 表示不过滤。
     * @return list<array<string, mixed>> 已按优先级排序的处理器描述符列表。
     */
    public function getHandlers(string $eventName, PluginEventEnvelope|array|null $eventOrContext = null, ?string $type = null): array
    {
        return $this->getSubscribers($eventName, $eventOrContext, $type);
    }

    /**
     * 返回事件的活跃订阅者和明确的跳过原因。
     *
     * 每个处理器要么进入 subscribers，要么进入 skipped 且带一个机器可读的
     * reason，不存在"既没执行也没有记录"的第三种情况——这正是排查插件静默
     * 失效的依据，也是后台展示"插件已安装但没生效"时唯一可信的数据来源。
     *
     * 匹配严格按当次 context 现算（商户范围、投放端、事件声明都在这里判定），
     * 结果不跨请求复用，避免把上一个请求的商户或投放端判定泄漏给下一个请求。
     * skipped 明细按事件名留在实例上供 getSkipped() 读取，仅用于诊断。
     *
     * @param string $eventName 事件名。
     * @param array<string, mixed> $context 匹配上下文（merchant_id、surface 等）。
     * @param string|null $type 只匹配指定处理器类型；null 表示不过滤。
     * @return array{subscribers: list<array<string, mixed>>, skipped: list<array<string, mixed>>}
     *         订阅者（已排序）与跳过记录（含 reason）。
     */
    public function match(string $eventName, array $context = [], ?string $type = null): array
    {
        $eventName = $this->normaliseEventName($eventName);
        $context = $this->normaliseContext($context);
        $subscribers = [];
        $skipped = [];
        foreach ($this->handlers[$eventName] ?? [] as $descriptor) {
            $code = (string)$descriptor['plugin_code'];
            $plugin = $this->plugins[$code] ?? null;
            $reason = $this->skipReason($plugin, $descriptor, $eventName, $context, $type);
            if ($reason !== null) {
                $skipped[] = [
                    'plugin_code' => $code,
                    'event' => $eventName,
                    'handler' => (string)$descriptor['handler'],
                    'type' => (string)$descriptor['type'],
                    'reason' => $reason,
                ];
                continue;
            }
            // 用插件当前版本覆盖描述符里的历史值：快照可能来自旧版本，而投递
            // 日志要记录"实际执行时"的插件版本，便于回溯版本漂移。
            $descriptor['plugin_version'] = (string)($plugin['version'] ?? $descriptor['plugin_version'] ?? '');
            $subscribers[] = $descriptor;
        }
        $this->skipped[$eventName] = $skipped;
        $this->sortDescriptorList($subscribers);
        // 统一转成 list 输出：匹配结果会直接序列化进快照和日志，键连续才能
        // 保证 JSON 是数组而不是对象。
        return ['subscribers' => array_values($subscribers), 'skipped' => $skipped];
    }

    /**
     * 为 Outbox 记录构建可 JSON 序列化的订阅快照。
     *
     * 快照是投递侧的"冻结名单"：事件写入 Outbox 的那一刻就确定了收件人、
     * 顺序和插件版本，之后即使注册表重建、插件被禁用或处理器改名，这条事件
     * 原本该投给谁也不会改变，重放和重试才能与首次投递保持一致。
     *
     * 快照同时承担裁剪职责（见 snapshotDescriptor()）：去掉 callable、闭包
     * 等无法落库或不该外泄的运行时值，保证 json_encode 一定成功、Outbox 与
     * 投递日志里不会出现插件实例；可执行体由消费端重新解析。
     *
     * @param PluginEventEnvelope|array<string, mixed> $event 事件信封或其数组形式。
     * @param array<string, mixed> $context 匹配上下文；缺 merchant_id/surface 时用信封与 backend 补齐。
     * @return array<string, mixed> 含 schema_version、event、event_version、created_at、subscribers、skipped 的快照。
     */
    public function buildSnapshot(PluginEventEnvelope|array $event, array $context = []): array
    {
        $envelope = $event instanceof PluginEventEnvelope ? $event : PluginEventEnvelope::fromArray($event);
        // 补齐上下文再匹配：入 Outbox 时的判定依据必须与投递时一致，
        // 否则快照会记下一批"当时其实不该收"的订阅者。
        $context = array_merge($context, [
            'merchant_id' => $context['merchant_id'] ?? $envelope->getMerchantId(),
            'surface' => $context['surface'] ?? $context['platform'] ?? 'backend',
        ]);
        $matched = $this->match($envelope->getName(), $context);
        $subscribers = [];
        foreach ($matched['subscribers'] as $descriptor) {
            $subscribers[] = $this->snapshotDescriptor($descriptor);
        }
        return [
            'schema_version' => '1.0',
            'event' => $envelope->getName(),
            'event_version' => $envelope->getVersion(),
            'created_at' => date('c'),
            'subscribers' => $subscribers,
            'skipped' => $matched['skipped'],
        ];
    }

    /**
     * 把描述符解析为当前 worker 内可执行的 callable。
     *
     * 解析优先级：进程内 callables 索引 > 描述符自带的 callable > 处理器
     * 类/方法字符串。前两者只在同进程执行（同步路径、测试）时命中；异步投递
     * 读到的是 Outbox 快照，其中的 callable 已被裁剪，只能靠字符串重新构造，
     * 所以字符串形式必须同时支持 'Class@method'、'Class::method' 和只有类名
     * （按 __invoke）三种写法。
     *
     * 解析失败一律抛异常而不返回 null：调用方（投递任务）会把它记成一次明确的
     * 失败，绝不能因为解析不出来就静默跳过，否则"没执行"会被误判成"执行成功"。
     *
     * @param array<string, mixed> $descriptor 处理器描述符，可能来自快照。
     * @return callable 当前进程内可执行的处理器。
     * @throws InvalidArgumentException 处理器的类或方法无法解析时抛出。
     */
    public function resolveHandler(array $descriptor): callable
    {
        $pluginCode = (string)($descriptor['plugin_code'] ?? '');
        $eventName = (string)($descriptor['event'] ?? $descriptor['name'] ?? '');
        $handlerName = (string)($descriptor['handler'] ?? '');
        $key = $this->callableKey($pluginCode, $eventName, $handlerName);
        if (isset($this->callables[$key])) {
            return $this->callables[$key];
        }
        $candidate = $descriptor['callable'] ?? $handlerName;
        if (is_callable($candidate)) {
            // 描述符自带可调用对象（同进程注册）：直接复用，避免重复构造实例。
            return $candidate;
        }
        if (!is_string($candidate) || trim($candidate) === '') {
            // 既没有可用索引也没有字符串线索：无法恢复出可执行体，显式失败。
            throw new InvalidArgumentException('Plugin event handler is not resolvable: ' . $handlerName);
        }

        $candidate = trim($candidate);
        // 三种写法统一拆成「类 + 方法」：'Class@method'、'Class::method'，
        // 只有类名时按可调用类约定用 __invoke。
        [$class, $method] = str_contains($candidate, '@')
            ? explode('@', $candidate, 2)
            : (str_contains($candidate, '::') ? explode('::', $candidate, 2) : [$candidate, '__invoke']);
        if (!class_exists($class)) {
            // 类不存在通常意味着插件源码未随该 worker 加载（或快照来自已移除的
            // 插件），必须显式报错而不是悄悄跳过。
            throw new InvalidArgumentException('Plugin event handler class not found: ' . $class);
        }
        $instance = $this->makeInstance($class);
        if (!method_exists($instance, $method)) {
            // 类在但方法不在：插件升级或改名导致的契约漂移，同样失败关闭。
            throw new InvalidArgumentException('Plugin event handler method not found: ' . $candidate);
        }
        return [$instance, $method];
    }

    /**
     * 根据当前启用状态和作用域校验快照处理器。
     *
     * 异步投递执行的是历史快照，动手前必须用当前注册表复核一次：插件是否
     * 仍然注册且启用、版本有没有漂移、商户范围与投放端是否仍然允许。任一环节
     * 不满足都返回 false（fail closed），由投递任务记录为跳过而不是执行。
     * 与 match() 的差别是不产出原因文本，只回答"现在能不能跑"。
     *
     * @param array<string, mixed> $descriptor 快照中的处理器描述符。
     * @param array<string, mixed> $context 当前投递上下文。
     * @param bool $requireVersion 是否要求插件版本与快照一致。
     * @return bool 可执行返回 true；插件缺失、禁用、版本不一致或范围不符返回 false。
     */
    public function isHandlerAvailable(array $descriptor, array $context = [], bool $requireVersion = false): bool
    {
        $code = (string)($descriptor['plugin_code'] ?? '');
        $plugin = $this->plugins[$code] ?? null;
        if ($plugin === null || !(bool)($plugin['enabled'] ?? false)) {
            return false;
        }
        // 版本漂移时按不可用处理：快照里的处理器可能已随插件升级被删除或改了
        // 签名，强行执行比重投一次更危险。只在这条快照确实记录了版本时才比较。
        if ($requireVersion && (string)($descriptor['plugin_version'] ?? '') !== ''
            && (string)($plugin['version'] ?? '') !== (string)$descriptor['plugin_version']) {
            return false;
        }
        // 其余判定复用 skipReason()：保证同步匹配与异步投递走同一套规则，
        // 不会出现"入队时算订阅者、投递时按另一套标准执行"的偏差。
        // 事件名取自描述符本身，快照缺该字段时为空串：此时若插件声明了
        // events_consumes，空名不会命中任何声明，处理器同样被判为不可用。
        return $this->skipReason($plugin, $descriptor, (string)($descriptor['event'] ?? ''), $this->normaliseContext($context), null) === null;
    }

    /**
     * 返回上一次 match 产生的跳过原因。
     *
     * 只有调用过 match()/getSubscribers() 之后才有内容，且只保留最近一次
     * 的结果（按事件名覆盖）。它服务于诊断和后台展示，不参与匹配判定，
     * 因此读取不到时应按"暂无记录"处理，而不是推断插件不可用。
     *
     * @param string $eventName 事件名。
     * @return list<array<string, mixed>> 跳过记录，每项含 plugin_code/event/handler/type/reason。
     */
    public function getSkipped(string $eventName): array
    {
        return $this->skipped[strtolower(trim($eventName))] ?? [];
    }

    /**
     * 清空所有运行时注册，适合 worker 重载和测试场景。
     *
     * 安装状态在数据库里、声明在 manifest 里，注册表只是这两者的进程内缓存，
     * 因此重载时必须先清空再重建，避免上一个进程残留的处理器继续被匹配到
     * （尤其是闭包和旧版实例，它们无法通过重新加载被自动更新）。
     *
     * @return self 当前实例，便于链式调用。
     */
    public function clear(): self
    {
        $this->plugins = [];
        $this->handlers = [];
        $this->callables = [];
        $this->skipped = [];
        return $this;
    }

    /**
     * 解析 manifest 声明的事件处理器并逐个注册。
     *
     * 兼容两种声明位置（events.handlers 与顶层 handlers，两者合并，后者覆盖
     * 同名键）和两种形态：每项自带 event 的定义数组，以及
     * {"order.created": [{...}, {...}]} 这种以事件名为键的简写。
     *
     * 纯字符串项直接跳过：一个字符串无法确定它属于哪个事件、是哪种处理器，
     * 与其猜一个默认值，不如留给 manifest 校验环节报"声明不完整"。
     *
     * @param string $pluginCode 插件编码。
     * @param array<string, mixed> $events manifest 的 events 段。
     * @param mixed $declaredHandlers 顶层 handlers 声明，与 events.handlers 合并。
     */
    private function registerManifestHandlers(string $pluginCode, array $events, mixed $declaredHandlers): void
    {
        $definitions = [];
        if (is_array($events['handlers'] ?? null)) {
            // events 段内嵌的处理器声明。
            $definitions = array_merge($definitions, $events['handlers']);
        }
        if (is_array($declaredHandlers)) {
            // 顶层 handlers 声明，排在后面，同名键以顶层为准。
            $definitions = array_merge($definitions, $declaredHandlers);
        }
        foreach ($definitions as $key => $definition) {
            if (is_string($definition)) {
                continue;
            }
            if (is_array($definition) && isset($definition['event'])) {
                // 标准形态：定义自带事件名。
                $this->registerManifestHandlerDefinition($pluginCode, $definition);
                continue;
            }
            if (is_string($key) && is_array($definition)) {
                // 简写形态 {"order.created": [{"type": ..., "handler": ...}]}：
                // 事件名来自键，逐项补进定义后再走统一注册路径。
                foreach ($definition as $item) {
                    if (is_array($item)) {
                        $this->registerManifestHandlerDefinition($pluginCode, array_merge(['event' => $key], $item));
                    }
                }
            }
        }
    }

    /**
     * 注册单条 manifest 处理器定义。
     *
     * 定义里的其余字段（priority、surfaces、merchant_scope、payload_fields 等）
     * 原样作为注册选项透传，manifest 因此不必为每个新选项改代码；
     * event/name/type/handler/callable 属于结构字段，先剔除再传，避免它们
     * 混进描述符选项里被后续当成业务字段使用。
     *
     * 缺少事件名或处理器时静默跳过：完整性由 manifest 校验负责报错，注册表
     * 只处理结构完整的定义，避免把"半个定义"变成运行期的不确定行为。
     *
     * @param string $pluginCode 插件编码。
     * @param array<string, mixed> $definition 单条处理器定义，至少含 event 与 handler/callable。
     */
    private function registerManifestHandlerDefinition(string $pluginCode, array $definition): void
    {
        $handler = $definition['handler'] ?? $definition['callable'] ?? null;
        $event = (string)($definition['event'] ?? $definition['name'] ?? '');
        // 未声明类型时按 observer 处理：manifest 应当显式写类型，这里只是兜底。
        $type = (string)($definition['type'] ?? 'observer');
        if ($handler === null || $event === '') {
            return;
        }
        $options = $definition;
        unset($options['event'], $options['name'], $options['type'], $options['handler'], $options['callable']);
        try {
            $this->registerHandler($pluginCode, $event, $type, $handler, $options);
        } catch (InvalidArgumentException $exception) {
            // 重载时 manifest 会被反复注册，重复的处理器按幂等处理：保留首次
            // 登记的定义、丢弃重复项，保证重建过程不会因为"已经注册过"而失败。
            // 其余异常（类型非法、处理器不可识别等）原样抛出，避免把真正的
            // 配置错误也一并吞掉。
            if (!str_starts_with($exception->getMessage(), 'Duplicate plugin event handler:')) {
                throw $exception;
            }
        }
    }

    /**
     * 判定单个处理器为什么不能在该上下文执行，可执行时返回 null。
     *
     * 判定顺序固定为：插件是否注册 -> 插件开关与生命周期 -> 处理器开关 ->
     * 声明标记 -> 类型过滤 -> 事件声明 -> 投放端 -> 商户范围 -> 插件的
     * scope_checker。顺序即优先级，靠前的结论覆盖靠后的，保证同样的输入永远
     * 得到同一句原因，测试和排查才有确定性。
     *
     * 所有分支都失败关闭：注册缺失、状态字段读不出来、外部 checker 抛异常，
     * 统一解释为"不执行"，绝不因为拿不准就把插件放行到下单等关键路径上。
     *
     * @param array<string, mixed>|null $plugin 插件元数据；null 表示未注册。
     * @param array<string, mixed> $descriptor 处理器描述符。
     * @param string $eventName 事件名。
     * @param array<string, mixed> $context 已归一化的匹配上下文。
     * @param string|null $type 要求的处理器类型；null 表示不做类型过滤。
     * @return string|null 跳过原因（机器可读的固定字符串）；可执行时返回 null。
     */
    private function skipReason(?array $plugin, array $descriptor, string $eventName, array $context, ?string $type): ?string
    {
        if ($plugin === null) {
            // 处理器仍挂在某个事件下，但插件已不在注册表里（通常是注册表重建后
            // 插件被卸载）。按未注册处理，绝不继续往下走。
            return 'plugin_not_registered';
        }
        if (!(bool)($plugin['enabled'] ?? false) || in_array(
            strtolower((string)($plugin['lifecycle_status'] ?? '')),
            ['disabled', 'disabling', 'uninstalled', 'uninstalling'],
            true
        )) {
            // 开关关闭或生命周期停在禁用/卸载流程中（含过渡态）都视为不可用；
            // 缺 enabled 字段按 false 处理，即"读不出来就不执行"。
            return 'skipped_disabled';
        }
        if (!(bool)($descriptor['enabled'] ?? true)) {
            // 处理器级开关：插件整体启用时，仍可单独停掉某个处理器；
            // 缺省视为开启，避免历史描述符因为没有该字段而被整体停用。
            return 'handler_disabled';
        }
        if (!(bool)($descriptor['declared'] ?? true)) {
            // declared=false 表示该处理器未在 manifest 中声明（例如运行时临时注册），
            // 不参与正式投递；同样缺省视为已声明。
            return 'event_not_declared';
        }
        if ($type !== null && (string)$descriptor['type'] !== $type) {
            // 调用方按类型取订阅者时，类型不符的不算它的订阅者。
            return 'handler_type_mismatch';
        }
        $declared = $plugin['events_consumes'] ?? [];
        if (is_array($declared) && $declared !== [] && !$this->eventIsDeclared($eventName, $declared)) {
            // 插件用 events_consumes 声明自己消费哪些事件：声明为空表示不做限制
            // （兼容早期 manifest），声明非空则按白名单匹配，未声明的事件不投递。
            return 'event_not_declared';
        }
        $surface = strtolower(trim((string)($context['surface'] ?? 'backend')));
        $surfaces = $descriptor['surfaces'] ?? [];
        if (!is_array($surfaces) || $surfaces === []) {
            // 处理器没写 surfaces（或写成了非法类型）时回落到插件级 surfaces。
            $surfaces = $plugin['surfaces'] ?? [];
        }
        if (is_array($surfaces) && $surfaces !== [] && !in_array($surface, array_map('strtolower', $surfaces), true)) {
            // 两级都为空表示不限投放端；非空则要求当前投放端在声明内。
            // 比较前统一转小写，避免大小写差异导致误判。
            return 'surface_not_supported';
        }
        $merchantId = max(0, (int)($context['merchant_id'] ?? 0));
        // 商户范围：处理器级 merchant_scope 优先于插件级；显式为 null 时回落到插件配置。
        $scope = array_key_exists('merchant_scope', $descriptor) && $descriptor['merchant_scope'] !== null
            ? $descriptor['merchant_scope']
            : ($plugin['merchant_scope'] ?? null);
        if (!$this->merchantAllowed($scope, $merchantId)) {
            return 'merchant_scope_denied';
        }
        $checker = $plugin['scope_checker'] ?? null;
        if (is_callable($checker)) {
            // 插件可提供自定义 checker 做更细的范围判断（如按商户分组或开关表）。
            try {
                if (!(bool)$checker($merchantId, $context, $plugin)) {
                    return 'merchant_scope_denied';
                }
            } catch (\Throwable) {
                // checker 抛异常时按拒绝处理：插件侧的异常既不能把事件放行，
                // 也不能把宿主的分发流程打断。
                return 'merchant_scope_denied';
            }
        }
        return null;
    }

    /**
     * 判断商户范围配置是否允许指定商户命中。
     *
     * 范围配置形态很多（null/空串/空数组=不限、布尔、逗号或空格分隔的 ID 串、
     * ID 数组、带 enabled/all/merchant_ids 的映射、可调用对象），统一在这里
     * 收敛，避免每个调用点各自解释一遍。约定 merchant_id=0 表示全局事件：
     * 只要范围里出现 0 就放行，保证全局事件不会被商户白名单挡掉。
     * 非数组的未知形态按"允许"处理，与"未配置即不限"保持一致；数组形态则统一
     * 按 ID 列表解析，解析后列表为空即不放行任何商户（fail closed）。
     * 范围本身的可信度由 manifest 与安装流程保证，这里不做来源校验。
     *
     * @param mixed $scope 商户范围配置。
     * @param int $merchantId 当前商户号，0 表示全局事件。
     * @return bool 允许返回 true。
     */
    private function merchantAllowed(mixed $scope, int $merchantId): bool
    {
        if ($scope === null || $scope === '' || $scope === []) {
            // 未声明范围即表示不限商户：这是插件什么都不配置时的默认行为，
            // 也是绝大部分全局插件的实际语义。
            return true;
        }
        if (is_callable($scope)) {
            // 可调用范围：由配置方自行判断，返回值按布尔解释。
            return (bool)$scope($merchantId);
        }
        if (is_bool($scope)) {
            // 显式布尔范围：直接按字面量判断，false 表示所有商户都不允许。
            return $scope;
        }
        if (is_int($scope) || is_string($scope)) {
            // 整数或字符串统一按 ID 列表解析，允许逗号、空格混合分隔。
            $scope = preg_split('/[\s,]+/', (string)$scope, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }
        if (is_array($scope)) {
            if (isset($scope['enabled']) && !(bool)$scope['enabled']) {
                // 映射形式里 enabled=false 表示整段范围关闭。
                return false;
            }
            if (isset($scope['all']) && (bool)$scope['all']) {
                // all=true 表示对全部商户放行，无需再看 ID 列表。
                return true;
            }
            $ids = $scope['merchant_ids'] ?? $scope['ids'] ?? $scope;
            if (is_array($ids)) {
                // 只保留数值项并转成整数，脏值被丢弃而不是当成 0，
                // 否则一个非法项就会意外变成"全局放行"。
                $ids = array_values(array_map('intval', array_filter($ids, static fn($id): bool => is_numeric($id))));
                // 列表含 0 视为全局放行；否则要求当前商户在列表内。
                return in_array(0, $ids, true) || in_array($merchantId, $ids, true);
            }
        }
        // 形态无法识别时按不限处理，与"未配置即不限"保持同一语义。
        return true;
    }

    /**
     * 判断事件是否落在插件声明消费的事件集合内。
     *
     * 支持精确事件名、通配 '*'（消费全部事件）和前缀通配 'order.*'（消费该
     * 命名空间下的事件）。声明校验的意义是把"插件能收到哪些事件"限定在
     * manifest 明示的范围内：宿主发布未声明的事件时插件不会被隐式拉起，
     * 避免插件因为宿主新增事件而被动扩大可触达范围。
     *
     * @param string $eventName 已归一化的事件名。
     * @param list<string> $declared 插件声明消费的事件名或通配模式。
     * @return bool 命中声明返回 true。
     */
    private function eventIsDeclared(string $eventName, array $declared): bool
    {
        foreach ($declared as $item) {
            $item = trim((string)$item);
            // '*' 表示订阅全部事件；'order.*' 去掉尾部 '*' 后按前缀比较，
            // 因此能命中 order.created，但不会命中 orders.x。
            if ($item === $eventName || $item === '*' || (str_ends_with($item, '.*') && str_starts_with($eventName, substr($item, 0, -1)))) {
                return true;
            }
        }
        return false;
    }

    /**
     * 把各种上下文入参整理成统一的匹配上下文。
     *
     * 只保留匹配真正需要的两个字段：surface（投放端，缺省 backend）和
     * merchant_id（非负整数，缺省 0 表示全局）。传入事件信封时按信封取值；
     * 数组入参则依次兼容嵌套的 event 信封、surface/platform/runtime 三种
     * 别名写法，以及 merchant_id/merchantId 两种键名。
     * 非法值在这里被钳制而不是抛异常：上下文只参与过滤，钳制后最坏结果是
     * "匹配不上"，不会让脏数据穿透到插件侧。
     *
     * @param PluginEventEnvelope|array<string, mixed>|null $context 事件信封、上下文数组或 null。
     * @return array<string, mixed> 含 surface 与 merchant_id 的规范化上下文。
     */
    private function normaliseContext(PluginEventEnvelope|array|null $context): array
    {
        if ($context instanceof PluginEventEnvelope) {
            // 直接拿信封当上下文时按系统触发处理：商户取信封，投放端按后台。
            return [
                'merchant_id' => $context->getMerchantId(),
                'surface' => 'backend',
            ];
        }
        $context ??= [];
        if (isset($context['event']) && $context['event'] instanceof PluginEventEnvelope) {
            // 兼容「上下文里嵌事件信封」的写法：商户号优先取显式 context，
            // 缺省时回落到信封上的归属商户。
            $event = $context['event'];
            $context['merchant_id'] = $context['merchant_id'] ?? $event->getMerchantId();
        }
        // 投放端统一小写去空白，并兼容 platform/runtime 两种历史叫法。
        $context['surface'] = strtolower(trim((string)($context['surface'] ?? $context['platform'] ?? $context['runtime'] ?? 'backend')));
        // 商户号必须非负：负数会被当成越权范围向外传播，这里直接钳制为全局。
        $context['merchant_id'] = max(0, (int)($context['merchant_id'] ?? $context['merchantId'] ?? 0));
        return $context;
    }

    /**
     * 裁剪单个处理器描述符，得到可安全落库的订阅快照项。
     *
     * 显式去掉 callable（闭包/对象实例）和 scope_checker（可能是闭包，也可能
     * 携带宿主内部状态），再递归处理剩余值。这一步同时保证两件事：快照一定
     * 能 json_encode 成功，且插件侧的运行时对象不会出现在 Outbox 和投递日志里。
     *
     * @param array<string, mixed> $descriptor 处理器描述符。
     * @return array<string, mixed> 可 JSON 序列化的描述符。
     */
    private function snapshotDescriptor(array $descriptor): array
    {
        unset($descriptor['callable'], $descriptor['scope_checker']);
        return $this->snapshotValue($descriptor);
    }

    /**
     * 把仅供运行时使用的值转换为可 JSON 序列化的快照表示。
     *
     * 规则：闭包/可调用对象与资源记为 null；数组逐项递归；实现了
     * JsonSerializable 的对象取其序列化结果；其他对象（插件实例、连接、DTO）
     * 一律丢弃为 null。宁可让快照少一个字段，也不能因为一个不可序列化的值
     * 让整条事件的 Outbox 写入失败。
     *
     * @param mixed $value 任意运行时值。
     * @return mixed 可安全 json_encode 的值。
     */
    private function snapshotValue(mixed $value): mixed
    {
        if (is_callable($value) && !is_string($value)) {
            // 闭包、[对象, 方法] 这类可调用对象无法跨进程恢复，直接丢弃；
            // 字符串形式的可调用体（类名/方法名）必须保留，它是消费端
            // 重新解析处理器的唯一线索。
            return null;
        }
        if (is_resource($value)) {
            // 资源（句柄、连接）没有任何可落库的表示。
            return null;
        }
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $result[$key] = $this->snapshotValue($item);
            }
            return $result;
        }
        if (is_object($value)) {
            if ($value instanceof \JsonSerializable) {
                return $this->snapshotValue($value->jsonSerialize());
            }
            // 未知对象不保留：无法保证可序列化，且可能携带敏感内部状态。
            return null;
        }
        return $value;
    }

    /**
     * 重排指定事件的处理器列表。
     *
     * 注册新处理器后立刻调用，保证 handlers 表在任何读取时刻都是有序的：
     * match() 只需对结果做一次排序，也不必担心注册顺序影响执行顺序。
     * 事件不存在时静默返回。
     *
     * @param string $eventName 已归一化的事件名。
     */
    private function sortHandlers(string $eventName): void
    {
        if (isset($this->handlers[$eventName])) {
            $this->sortDescriptorList($this->handlers[$eventName]);
        }
    }

    /**
     * 按优先级和命名键对处理器描述符列表做就地排序。
     *
     * 排序键依次是 priority（升序，数值越小越先执行）、plugin_code、handler。
     * 后两个键专门用来打破平局：只有给出确定的次级排序键，同一批处理器在
     * 任何 worker、任何注册顺序下才会得到完全一致的结果，快照比对和重放才
     * 有意义——否则同级处理器的顺序会随注册顺序漂移，同步 guard/filter 的
     * 执行次序也跟着变。使用 usort 就地重排，入参是数组引用。
     *
     * @param list<array<string, mixed>> $descriptors 处理器描述符列表，就地排序。
     */
    private function sortDescriptorList(array &$descriptors): void
    {
        usort($descriptors, static function (array $left, array $right): int {
            $priority = ((int)$left['priority']) <=> ((int)$right['priority']);
            if ($priority !== 0) {
                // 优先级不同，直接按数值升序（小的先执行）。
                return $priority;
            }
            $plugin = strcmp((string)$left['plugin_code'], (string)$right['plugin_code']);
            if ($plugin !== 0) {
                // 同优先级先按插件编码分组，让同一插件的处理器保持相邻且顺序固定。
                return $plugin;
            }
            // 最后按处理器名兜底，消除"同插件同优先级"仅剩的不确定性。
            return strcmp((string)$left['handler'], (string)$right['handler']);
        });
    }

    /**
     * 校验并归一化插件编码。
     *
     * 编码会进入事件名、日志、数据库和跨进程快照，所以必须是可安全落库的
     * 小写标识：小写字母开头，只允许 a-z0-9_-，长度不超过 64。不合规直接抛
     * 异常，不做大小写转换或静默裁剪——插件是显式安装的，写错编码就应该在
     * 安装阶段失败，而不是变成一个"看起来装上了但匹配不到"的隐性偏差。
     *
     * @param string $code 原始插件编码。
     * @return string 去除首尾空白后的合法编码。
     * @throws InvalidArgumentException 编码为空、超长或含非法字符时抛出。
     */
    private function normalisePluginCode(string $code): string
    {
        $code = trim($code);
        if ($code === '' || strlen($code) > 64 || !preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $code)) {
            throw new InvalidArgumentException('Invalid plugin code');
        }
        return $code;
    }

    /**
     * 校验并归一化事件名。
     *
     * 事件名是订阅匹配和投递日志的主键，同样不允许模糊处理：非空、不超过
     * 100 字符、首字符为字母或数字、其余只允许字母数字与 . _ : -。
     * 这里刻意不做大小写转换——事件名在宿主和插件之间必须逐字节一致，
     * 否则会出现"明明订阅了却匹配不到"的静默失效。
     *
     * @param string $eventName 原始事件名。
     * @return string 去除首尾空白后的合法事件名。
     * @throws InvalidArgumentException 事件名为空、超长或含非法字符时抛出。
     */
    private function normaliseEventName(string $eventName): string
    {
        $eventName = trim($eventName);
        if ($eventName === '' || strlen($eventName) > 100 || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,99}$/', $eventName)) {
            throw new InvalidArgumentException('Invalid plugin event name');
        }
        return $eventName;
    }

    /**
     * 归一化处理器优先级。
     *
     * 非数值输入回退到默认优先级而不是当成 0，避免一个脏值把处理器意外提到
     * 最前面执行；数值统一钳制在正负 100 万之间，既防止极端值压过所有处理器，
     * 也避免不同平台整型转换差异造成排序抖动。
     *
     * @param mixed $priority 原始优先级。
     * @return int 归一化后的优先级，数值越小越先执行。
     */
    private function normalisePriority(mixed $priority): int
    {
        if (!is_numeric($priority)) {
            return self::DEFAULT_PRIORITY;
        }
        return max(-1000000, min(1000000, (int)$priority));
    }

    /**
     * 规范化同步处理器超时。
     *
     * observer 继续允许 0 表示由异步投递器控制；calculator/transaction
     * 必须有有限超时，未配置或传入非正数时回退到系统默认值。
     *
     * @param string $type 处理器类型。
     * @param array<string, mixed> $options 注册选项。
     */
    private function normaliseTimeoutMs(string $type, array $options): int
    {
        $configured = (int)($options['timeout_ms'] ?? $options['timeout'] ?? 0);
        if ($configured > 0) {
            return min(60000, $configured);
        }
        if (!in_array($type, ['calculator', 'transaction'], true)) {
            return 0;
        }
        try {
            $default = function_exists('config')
                ? (int)config('plugin.event.sync_timeout_ms', self::DEFAULT_SYNC_TIMEOUT_MS)
                : self::DEFAULT_SYNC_TIMEOUT_MS;
        } catch (\Throwable) {
            $default = self::DEFAULT_SYNC_TIMEOUT_MS;
        }
        return max(100, min(60000, $default > 0 ? $default : self::DEFAULT_SYNC_TIMEOUT_MS));
    }

    /**
     * 归一化投放端（surfaces）声明。
     *
     * 字符串按逗号/空白切分，逐项小写去空白，只保留白名单内的取值
     * （backend/admin/merchant/mobile/h5/mp/wechat）并去重。白名单过滤保证只
     * 接受宿主认识的投放端，拼错的取值被丢弃而不会留在声明里被当成有效配置。
     * 注意：过滤后为空表示"不限投放端"，因此声明写错时结果是放宽而不是收紧，
     * 拼写正确性应由 manifest 校验负责拦截。
     *
     * @param mixed $surfaces 数组或分隔字符串。
     * @return list<string> 去重后的合法投放端列表。
     */
    private function normaliseSurfaces(mixed $surfaces): array
    {
        if (is_string($surfaces)) {
            // 字符串声明按逗号或空白切分，兼容 'backend,merchant' 与 'backend merchant' 两种写法。
            $surfaces = preg_split('/[\s,]+/', $surfaces, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }
        if (!is_array($surfaces)) {
            // 非数组非字符串（如被写成了对象）视为未声明，交由上层回落处理。
            return [];
        }
        $allowed = ['backend', 'admin', 'merchant', 'mobile', 'h5', 'mp', 'wechat'];
        $result = [];
        foreach ($surfaces as $surface) {
            $surface = strtolower(trim((string)$surface));
            if ($surface !== '' && in_array($surface, $allowed, true) && !in_array($surface, $result, true)) {
                $result[] = $surface;
            }
        }
        return $result;
    }

    /**
     * 归一化插件声明消费的事件名列表。
     *
     * 字符串按逗号/空白切分；每项去空白，丢弃空值、超过 100 字符的值和字符集
     * 不合法的值（允许前缀通配 'order.*'，校验时去掉尾部 '.*' 再匹配），最后
     * 去重。对非法项选择丢弃而非抛异常是有意为之：一个写错的事件名不该让
     * 整个插件安装失败，但也不能把非法值留在声明表里被后续当成有效通配使用。
     *
     * @param mixed $events 数组或分隔字符串。
     * @return list<string> 去重后的合法事件名/通配模式列表。
     */
    private function normaliseEventList(mixed $events): array
    {
        if (is_string($events)) {
            // 兼容 'order.created,order.paid' 与空格分隔的声明写法。
            $events = preg_split('/[\s,]+/', $events, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }
        if (!is_array($events)) {
            return [];
        }
        $result = [];
        foreach ($events as $event) {
            $event = trim((string)$event);
            if ($event === '' || strlen($event) > 100) {
                continue;
            }
            // 通配声明 'order.*' 去掉尾部 '*.' 后校验前缀部分是否合法，
            // 保留原始写法（含通配符）供 eventIsDeclared() 做前缀匹配。
            $pattern = str_ends_with($event, '.*') ? substr($event, 0, -2) : $event;
            if ($pattern === '' || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,99}$/', $pattern)) {
                continue;
            }
            $result[] = $event;
        }
        return array_values(array_unique($result));
    }

    /**
     * 归一化允许下发给插件的载荷字段白名单。
     *
     * 每项去掉首尾空白和点号（兼容 'order.id'、'.order.id' 这类配置习惯），
     * 丢弃空串和超过 190 字符（数据库列长度上限）的项，最后去重。
     * 白名单直接决定插件能看到哪些载荷字段，因此宁可少给不可多给：非法项被
     * 丢弃的结果是字段不放行，插件最坏拿不到数据，而不是拿到不该看的字段。
     *
     * @param mixed $fields 数组或分隔字符串。
     * @return list<string> 去重后的字段名列表。
     */
    private function normalisePayloadFields(mixed $fields): array
    {
        if (is_string($fields)) {
            // 兼容 'order_id,price' 与空格分隔的声明写法。
            $fields = preg_split('/[\s,]+/', $fields, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }
        if (!is_array($fields)) {
            return [];
        }
        return array_values(array_unique(array_filter(array_map(
            fn($field): string => trim((string)$field, " .\t\n\r\0\x0B"),
            $fields,
        ), static fn(string $field): bool => $field !== '' && strlen($field) <= 190)));
    }

    /**
     * 从处理器入参推导稳定的处理器名字。
     *
     * 名字是注册表内部索引、快照、投递日志和去重判断共同的键，必须可复现：
     * 字符串按原文去空白；[对象/类名, 方法] 拼成 'Class@method'；闭包用
     * spl_object_id 生成 'closure:N'（同进程内唯一，重启后不复现，因此闭包只
     * 适合同步路径和测试）；单对象按 '__invoke' 约定处理；都无法判断时才回落到
     * options.handler_name。名字为空、超过 190 字符（数据库列长度）或含控制
     * 字符时直接抛异常，避免把不可打印内容写进日志和快照。
     *
     * @param mixed $handler 处理器：字符串、[目标, 方法]、闭包或可调用对象。
     * @param array<string, mixed> $options 注册选项，可提供 handler_name 兜底。
     * @return string 归一化后的处理器名。
     * @throws InvalidArgumentException 无法得到合法名称时抛出。
     */
    private function normaliseHandlerName(mixed $handler, array $options): string
    {
        if (is_string($handler)) {
            $name = trim($handler);
        } elseif (is_array($handler) && count($handler) === 2) {
            $target = $handler[0];
            $method = (string)$handler[1];
            $name = (is_object($target) ? $target::class : (string)$target) . '@' . $method;
        } elseif ($handler instanceof \Closure) {
            $name = 'closure:' . spl_object_id($handler);
        } elseif (is_object($handler)) {
            $name = $handler::class . '@__invoke';
        } else {
            // 兜底路径：前面几种形态都识别不了时，允许调用方显式指定名字。
            $name = trim((string)($options['handler_name'] ?? ''));
        }
        if ($name === '' || strlen($name) > 190 || preg_match('/[\x00-\x1F\x7F]/', $name)) {
            // 控制字符会污染日志与快照，超长值也无法落库（列长 190），
            // 因此这类名字一律拒绝，不做截断或转义。
            throw new InvalidArgumentException('Invalid plugin event handler name');
        }
        return $name;
    }

    /**
     * 生成进程内 callable 索引的键。
     *
     * 用「插件|事件|处理器」三段拼接，保证同一插件在不同事件上注册同名处理器、
     * 或不同插件注册同名处理器时互不覆盖。分隔符取 '|'，它在插件编码、事件名和
     * 处理器名的合法字符集之外，不会与任何一段产生歧义。
     *
     * @param string $pluginCode 插件编码。
     * @param string $eventName 事件名。
     * @param string $handlerName 处理器名。
     * @return string callable 索引键。
     */
    private function callableKey(string $pluginCode, string $eventName, string $handlerName): string
    {
        return $pluginCode . '|' . $eventName . '|' . $handlerName;
    }

    /**
     * 构造处理器类实例。
     *
     * 优先走容器（app()->make()），让处理器能正常注入依赖；容器不可用或构造
     * 失败时（独立 worker、PHPUnit、纯命令行场景）回退到无参 new，保证解析
     * 过程不会因为环境差异直接中断。由此对插件类有一个隐含约束：处理器必须
     * 支持无参构造，需要依赖时应通过容器获取，而不是要求必填构造参数。
     *
     * @param string $class 已确认存在的类名。
     * @return object 处理器实例。
     */
    private function makeInstance(string $class): object
    {
        try {
            if (function_exists('app')) {
                $instance = app()->make($class);
                if (is_object($instance)) {
                    return $instance;
                }
            }
        } catch (\Throwable) {
            // 独立 worker 或测试环境里，容器不可用时回退到普通构造。
        }
        return new $class();
    }
}
