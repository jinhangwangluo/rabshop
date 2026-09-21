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

namespace app\common\service\user;

use app\common\enum\ConfigKeyEnum;
use app\common\exception\AdminResponseException;
use app\common\model\user\User;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;

/**
 * 用户分销配置、权限、绑定与订单佣金快照服务。
 *
 * 本服务集中处理分销相关规则，避免在登录、用户管理、下单和佣金中心中重复判断：
 * 1. 读取全局分销开关、等级、模式和返佣比例。
 * 2. 按“人人分销 / 指定分销 + 用户三态权限覆盖”计算用户是否可作为上级分销人。
 * 3. 登录或授权时只给没有上级的用户绑定有效推广人，后台手动绑定由 UserService 调用校验后覆盖。
 * 4. 下单时生成订单与订单商品的佣金快照，后续支付、冻结、发放都只读取快照，不再读取实时配置重算。
 */
class UserDistributionService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /** 人人分销：正常用户默认都有分销资格，仍可被单独设置为禁止。 */
    public const MODE_ALL = 'all';

    /** 指定分销：只有用户权限覆盖为“指定允许”时才有分销资格。 */
    public const MODE_SPECIFIED = 'specified';

    /** 跟随全局模式，人人分销下允许，指定分销下不允许。 */
    public const PERMISSION_FOLLOW = -1;

    /** 单用户禁止分销，优先级高于人人分销模式。 */
    public const PERMISSION_FORBID = 0;

    /** 单用户指定允许分销，指定分销模式下依赖该值放行。 */
    public const PERMISSION_ALLOW = 1;

    /**
     * 设置资源服务对应模型。
     *
     * @return string 用户模型类名。
     */
    protected function setModel(): string
    {
        return User::class;
    }

    /**
     * 获取并规范化全局分销配置。
     *
     * 配置值来自 config 表，后台保存时都是字符串；这里统一转成业务层使用的布尔、整数和金额比例。
     * 比例按百分比口径保存，5 表示 5%。非法等级、模式会回退到保守默认值。
     *
     * @return array{enabled: bool, level: int, mode: string, first_rate: float, second_rate: float}
     */
    public function getConfig(): array
    {
        $enabled = $this->normalizeBoolean(db_config(ConfigKeyEnum::DISTRIBUTION_ENABLED, false));
        $level = (int)db_config(ConfigKeyEnum::DISTRIBUTION_LEVEL, 2);
        $mode = (string)db_config(ConfigKeyEnum::DISTRIBUTION_MODE, self::MODE_SPECIFIED);

        return [
            'enabled' => $enabled,
            'level' => in_array($level, [1, 2], true) ? $level : 2,
            'mode' => in_array($mode, [self::MODE_ALL, self::MODE_SPECIFIED], true) ? $mode : self::MODE_SPECIFIED,
            'first_rate' => $this->normalizeRate(db_config(ConfigKeyEnum::DISTRIBUTION_FIRST_RATE, 0)),
            'second_rate' => $this->normalizeRate(db_config(ConfigKeyEnum::DISTRIBUTION_SECOND_RATE, 0)),
        ];
    }

    /**
     * 判断分销总开关是否启用。
     *
     * @return bool 开启返回 true，关闭返回 false。
     */
    public function isDistributionEnabled(): bool
    {
        return (bool)$this->getConfig()['enabled'];
    }

    /**
     * 获取用户最终分销权限。
     *
     * 三态权限含义：
     * - -1 跟随模式：人人分销下可分销，指定分销下不可分销。
     * - 0 禁止：无论全局是否人人分销，都不可分销。
     * - 1 指定允许：全局开关开启且用户正常时可分销。
     *
     * @param array|User|int $user 用户数组、模型或 UID。
     * @return array<string, mixed> 分销配置、用户权限覆盖和最终有效权限。
     */
    public function getEffectivePermission(array|User|int $user): array
    {
        $userData = $this->normalizeUserData($user);
        $config = $this->getConfig();
        $permission = $this->normalizePermission($userData['distribution_permission'] ?? self::PERMISSION_FOLLOW);
        $status = (int)($userData['status'] ?? 0);
        $canDistribute = false;

        // 全局关闭或用户禁用时直接不可分销，单用户配置不再参与计算。
        if ($config['enabled'] && $status === 1) {
            if ($permission === self::PERMISSION_FORBID) {
                $canDistribute = false;
            } elseif ($config['mode'] === self::MODE_ALL) {
                $canDistribute = true;
            } else {
                $canDistribute = $permission === self::PERMISSION_ALLOW;
            }
        }

        return [
            'distribution_enabled' => (bool)$config['enabled'],
            'distribution_level' => (int)$config['level'],
            'distribution_mode' => (string)$config['mode'],
            'distribution_first_rate' => (float)$config['first_rate'],
            'distribution_second_rate' => (float)$config['second_rate'],
            'distribution_permission' => $permission,
            'effective_distribution_enabled' => $canDistribute,
            'effective_distribution_text' => $canDistribute ? '可分销' : '不可分销',
        ];
    }

    /**
     * 判断用户是否具备作为返佣上级的资格。
     *
     * @param int $userId 待判断用户 UID。
     * @return bool 用户存在、状态正常且最终权限允许时返回 true。
     */
    public function canUserDistribute(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $user = $this->getModel()->where('uid', $userId)->find();
        if (!$user instanceof User) {
            return false;
        }

        return (bool)$this->getEffectivePermission($user)['effective_distribution_enabled'];
    }

    /**
     * 下单时解析一二级上级和返佣配置快照。
     *
     * 只在下单创建订单时调用。返回值会落到 order / order_goods 快照字段，支付和发放阶段不能再重新读取实时配置。
     * 当分销关闭、买家无上级、上级无资格、二级等级关闭或二级上级无资格时，对应 ID 和比例返回 0。
     *
     * @param int $buyerId 下单用户 UID。
     * @return array<string, mixed> 一二级上级 ID、分销等级/模式和一二级比例快照。
     */
    public function resolveReferrersForOrder(int $buyerId): array
    {
        $config = $this->getConfig();
        $empty = $this->emptyReferrerSnapshot($config);
        if (!$config['enabled'] || $buyerId <= 0) {
            return $this->emptyReferrerSnapshot($config, true);
        }

        $buyer = $this->getModel()->where('uid', $buyerId)->find();
        if (!$buyer instanceof User) {
            return $empty;
        }

        // 一级上级必须存在且具备分销资格，否则整单不返佣。
        $firstReferrerId = (int)($buyer->spread_uid ?? 0);
        if ($firstReferrerId <= 0 || $firstReferrerId === $buyerId || !$this->canUserDistribute($firstReferrerId)) {
            return $empty;
        }

        $firstReferrer = $this->getModel()->where('uid', $firstReferrerId)->find();
        $secondReferrerId = 0;
        if ($config['level'] >= 2 && $firstReferrer instanceof User) {
            $candidate = (int)($firstReferrer->spread_uid ?? 0);
            // 二级上级同样要防止自环、重复上级和无分销资格的情况。
            if ($candidate > 0
                && $candidate !== $buyerId
                && $candidate !== $firstReferrerId
                && $this->canUserDistribute($candidate)
            ) {
                $secondReferrerId = $candidate;
            }
        }

        return [
            'first_referrer_id' => $firstReferrerId,
            'second_referrer_id' => $secondReferrerId,
            'commission_level' => (int)$config['level'],
            'commission_mode' => (string)$config['mode'],
            'first_commission_rate' => $firstReferrerId > 0 ? (float)$config['first_rate'] : 0.0,
            'second_commission_rate' => $secondReferrerId > 0 ? (float)$config['second_rate'] : 0.0,
        ];
    }

    /**
     * 下单时按商品行实付金额生成订单与订单商品返佣快照。
     *
     * 计算口径：商品行基数使用确认单服务最终分摊后的 pay_amount；订单基数使用订单最终 pay_amount。
     * 佣金在下单时直接算好并保存，支付成功后只推进冻结状态，不再重算金额或比例。
     *
     * @param int $buyerId 下单用户 UID。
     * @param array $items 已由确认单服务计算完实付分摊的商品行。
     * @param float $orderPayAmount 订单最终实付金额。
     * @return array{order: array<string, mixed>, items: array, first_commission_amount: float, second_commission_amount: float}
     */
    public function buildOrderCommissionSnapshot(int $buyerId, array $items, float $orderPayAmount): array
    {
        $referrerSnapshot = $this->resolveReferrersForOrder($buyerId);
        $firstReferrerId = (int)($referrerSnapshot['first_referrer_id'] ?? 0);
        $secondReferrerId = (int)($referrerSnapshot['second_referrer_id'] ?? 0);
        $firstRate = (float)($referrerSnapshot['first_commission_rate'] ?? 0);
        $secondRate = (float)($referrerSnapshot['second_commission_rate'] ?? 0);
        $firstTotal = 0.0;
        $secondTotal = 0.0;
        $snapshotItems = [];

        foreach ($items as $item) {
            // 商品行返佣基数必须使用服务端重算后的实付分摊金额，不能使用前端提交金额或原价。
            $baseAmount = $this->normalizeMoney((float)($item['pay_amount'] ?? 0));
            $firstCommission = $firstReferrerId > 0 && $firstRate > 0
                ? $this->calculateCommission($baseAmount, $firstRate)
                : 0.0;
            $secondCommission = $secondReferrerId > 0 && $secondRate > 0
                ? $this->calculateCommission($baseAmount, $secondRate)
                : 0.0;

            $item['first_referrer_id'] = $firstReferrerId;
            $item['second_referrer_id'] = $secondReferrerId;
            $item['commission_base_amount'] = $baseAmount;
            $item['first_commission_rate'] = $firstReferrerId > 0 ? $firstRate : 0.0;
            $item['second_commission_rate'] = $secondReferrerId > 0 ? $secondRate : 0.0;
            $item['commission_level'] = (int)($referrerSnapshot['commission_level'] ?? 0);
            $item['commission_mode'] = (string)($referrerSnapshot['commission_mode'] ?? '');
            $item['first_commission'] = $firstCommission;
            $item['second_commission'] = $secondCommission;

            $firstTotal = $this->moneyAdd($firstTotal, $firstCommission);
            $secondTotal = $this->moneyAdd($secondTotal, $secondCommission);
            $snapshotItems[] = $item;
        }

        // 订单主表只保存汇总快照；商品行保存逐行快照，便于退款后按商品冲正。
        $orderSnapshot = [
            'referrer_id' => $firstReferrerId,
            'second_referrer_id' => $secondReferrerId,
            'commission_base_amount' => $this->normalizeMoney($orderPayAmount),
            'first_commission_rate' => $firstReferrerId > 0 ? $firstRate : 0.0,
            'second_commission_rate' => $secondReferrerId > 0 ? $secondRate : 0.0,
            'commission_level' => (int)($referrerSnapshot['commission_level'] ?? 0),
            'commission_mode' => (string)($referrerSnapshot['commission_mode'] ?? ''),
            'first_commission_amount' => $firstTotal,
            'second_commission_amount' => $secondTotal,
        ];

        return [
            'order' => $orderSnapshot,
            'items' => $snapshotItems,
            'first_commission_amount' => $firstTotal,
            'second_commission_amount' => $secondTotal,
        ];
    }

    /**
     * 老用户登录/授权时，如果尚未绑定上级且传入有效 spread_uid，则完成绑定。
     *
     * 自动绑定只补空缺关系，不会覆盖已有上级；后台手动绑定仍可通过 UserService 覆盖。
     *
     * @param int $userId 当前登录用户 UID。
     * @param int $spreadUid 请求中携带的推广人 UID。
     * @return bool 实际完成绑定返回 true，否则返回 false。
     */
    public function bindIfMissing(int $userId, int $spreadUid): bool
    {
        if ($userId <= 0 || $spreadUid <= 0) {
            return false;
        }

        $user = $this->getModel()->where('uid', $userId)->find();
        if (!$user instanceof User || (int)($user->spread_uid ?? 0) > 0) {
            return false;
        }

        $validSpreadUid = $this->normalizeValidSpreadUid($spreadUid, $userId);
        if ($validSpreadUid <= 0) {
            return false;
        }

        $user->spread_uid = $validSpreadUid;
        $user->spread_time = date('Y-m-d H:i:s');
        $user->save();

        return true;
    }

    /**
     * 校验上级关系，禁止自绑和完整链路循环绑定。
     *
     * 这里不仅检查直接父子关系，还会沿待绑定上级的整条 spread_uid 链路向上查找，
     * 防止 A -> B -> C 后再把 C 的上级设为 A 形成循环。
     *
     * @param int $spreadUid 待绑定的上级 UID。
     * @param int $selfUid 当前用户 UID，新建用户时可为 0。
     * @return void
     * @throws AdminResponseException
     */
    public function assertValidSpreadUser(int $spreadUid, int $selfUid = 0): void
    {
        if ($spreadUid <= 0) {
            throw new AdminResponseException('上级分销人不存在');
        }

        if ($selfUid > 0 && $spreadUid === $selfUid) {
            throw new AdminResponseException('不能将自己设为上级分销人');
        }

        $spreadUser = $this->getModel()->where('uid', $spreadUid)->find();
        if (!$spreadUser instanceof User || (int)($spreadUser->status ?? 0) !== 1) {
            throw new AdminResponseException('上级分销人不存在或已禁用');
        }

        if ($selfUid <= 0) {
            return;
        }

        // 使用 visited 防止历史脏数据中已存在循环时造成死循环。
        $visited = [];
        $currentUid = $spreadUid;
        while ($currentUid > 0) {
            if ($currentUid === $selfUid) {
                throw new AdminResponseException('不能绑定自己的下级为上级分销人');
            }

            if (isset($visited[$currentUid])) {
                break;
            }
            $visited[$currentUid] = true;

            $current = $this->getModel()->where('uid', $currentUid)->field(['uid', 'spread_uid'])->find();
            if (!$current instanceof User) {
                break;
            }
            $currentUid = (int)($current->spread_uid ?? 0);
        }
    }

    /**
     * 规范化后台三态分销权限。
     *
     * @param int|string|null $permission 原始权限值。
     * @return int 仅返回 -1、0、1；非法值回退为跟随模式。
     */
    public function normalizePermission(int|string|null $permission): int
    {
        $permission = (int)$permission;
        return in_array($permission, [self::PERMISSION_FOLLOW, self::PERMISSION_FORBID, self::PERMISSION_ALLOW], true)
            ? $permission
            : self::PERMISSION_FOLLOW;
    }

    /**
     * 返回合法上级 UID；无效推广参数静默忽略。
     *
     * 移动端推广参数可能来自二维码、URL 或历史缓存，不能因为无效推广人阻断登录/注册，所以这里静默返回 0。
     *
     * @param mixed $spreadUid 原始推广人 UID。
     * @param int $selfUid 当前用户 UID，新用户注册时可为 0。
     * @return int 合法上级 UID，无效时返回 0。
     */
    public function normalizeValidSpreadUid(mixed $spreadUid, int $selfUid = 0): int
    {
        $spreadUid = (int)$spreadUid;
        if ($spreadUid <= 0) {
            return 0;
        }

        try {
            $this->assertValidSpreadUser($spreadUid, $selfUid);
        } catch (\Throwable $throwable) {
            unset($throwable);

            return 0;
        }

        return $spreadUid;
    }

    /**
     * 构造空返佣快照。
     *
     * @param array $config 已规范化的分销配置。
     * @param bool $disabled 是否为分销关闭场景，关闭时等级和模式也清空。
     * @return array<string, mixed>
     */
    private function emptyReferrerSnapshot(array $config, bool $disabled = false): array
    {
        return [
            'first_referrer_id' => 0,
            'second_referrer_id' => 0,
            'commission_level' => $disabled ? 0 : (int)($config['level'] ?? 0),
            'commission_mode' => $disabled ? '' : (string)($config['mode'] ?? ''),
            'first_commission_rate' => 0.0,
            'second_commission_rate' => 0.0,
        ];
    }

    /**
     * 将数组、模型或 UID 统一转换成用户数组。
     *
     * @param array|User|int $user 用户数组、模型或 UID。
     * @return array 用户数据，不存在时返回空数组。
     */
    private function normalizeUserData(array|User|int $user): array
    {
        if (is_int($user)) {
            if ($user <= 0) {
                return [];
            }
            $model = $this->getModel()->where('uid', $user)->find();
            return $model instanceof User ? $model->toArray() : [];
        }

        if ($user instanceof User) {
            return $user->toArray();
        }

        return $user;
    }

    /**
     * 规范化配置布尔值。
     *
     * @param mixed $value 原始配置值。
     * @return bool 标准布尔值。
     */
    private function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int)$value === 1;
        }

        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * 规范化返佣比例。
     *
     * @param mixed $value 原始比例值，百分比口径。
     * @return float 0 到 100 之间的两位小数比例。
     */
    private function normalizeRate(mixed $value): float
    {
        $rate = (float)$value;
        if ($rate < 0) {
            return 0.0;
        }

        return $rate > 100 ? 100.0 : $this->normalizeMoney($rate);
    }

    /**
     * 按百分比计算佣金金额。
     *
     * @param float $baseAmount 返佣基数。
     * @param float $rate 返佣比例，百分比口径。
     * @return float 两位小数佣金金额。
     */
    private function calculateCommission(float $baseAmount, float $rate): float
    {
        if ($baseAmount <= 0 || $rate <= 0) {
            return 0.0;
        }

        return $this->normalizeMoney(round($baseAmount * $rate / 100, 2));
    }

    /**
     * 金额标准化为两位小数。
     *
     * @param float|string $amount 原始金额。
     * @return float 两位小数金额。
     */
    private function normalizeMoney(float|string $amount): float
    {
        return (float)bcadd((string)$amount, '0', 2);
    }

    /**
     * 两位小数金额相加。
     *
     * @param float|string $left 左侧金额。
     * @param float|string $right 右侧金额。
     * @return float 相加后的两位小数金额。
     */
    private function moneyAdd(float|string $left, float|string $right): float
    {
        return (float)bcadd((string)$left, (string)$right, 2);
    }
}
