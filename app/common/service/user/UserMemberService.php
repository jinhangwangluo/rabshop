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

use app\common\enum\CombinationDataTypeEnum;
use app\common\enum\ConfigKeyEnum;
use app\common\enum\PayTypeEnum;
use app\common\exception\AdminResponseException;
use app\common\model\user\UserMember;
use app\common\model\user\UserRechargeOrder;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\message\BackendMessageEventService;
use app\common\service\merchant\MerchantService;
use think\facade\Log;

/**
 * 管理用户会员身份、价格权益和套餐购买，并通过充值服务保存会员单及发起支付。
 */
class UserMemberService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    private const PAY_BODY = '会员购买';

    /**
     * 指定会员身份和有效期记录使用的资源模型。
     *
     * @return string 用户会员模型的完整类名。
     */
    protected function setModel(): string
    {
        return UserMember::class;
    }

    /**
     * 判断会员功能是否开启。
     *
     * @return bool 后台是否允许会员套餐购买和续费，不代表已有会员身份失效。
     */
    public function isMemberEnabled(): bool
    {
        return (int) db_config(ConfigKeyEnum::MEMBER_ENABLED, '1') === 1;
    }

    /**
     * 读取后台配置的会员套餐，关闭购买开关时不提供套餐。
     *
     * @return array 可供购买的会员套餐列表；功能关闭时为空数组。
     */
    public function getPackages(): array
    {
        if (!$this->isMemberEnabled()) {
            return [];
        }

        return db_combination_data(CombinationDataTypeEnum::MEMBER_PACKAGE, 0, []);
    }

    /**
     * 读取会员权益文案，按调用场景决定是否受购买开关限制。
     *
     * @param bool $ignoreSwitch 是否忽略购买开关，为已有会员继续展示权益。
     * @return array 后台配置的权益列表；不忽略开关且功能关闭时为空数组。
     */
    public function getBenefits(bool $ignoreSwitch = false): array
    {
        if (!$ignoreSwitch && !$this->isMemberEnabled()) {
            return [];
        }

        return db_combination_data(CombinationDataTypeEnum::MEMBER_BENEFIT, 0, []);
    }

    /**
     * 根据身份记录和到期时间判断会员有效性，并修正仍标记有效的过期记录。
     *
     * @param int $userId 待查询的用户标识。
     * @return array 是否有效会员、起止时间和状态；没有记录时返回非会员默认值。
     */
    public function getMemberInfo(int $userId): array
    {
        $record = $this->getModel()->where('user_id', $userId)->find();
        if (!$record) {
            return [
                'is_member' => false,
                'start_time' => null,
                'end_time' => null,
                'status' => UserMember::STATUS_EXPIRED,
            ];
        }

        $data = $record->toArray();
        $now = date('Y-m-d H:i:s');
        $isActive = (int)($data['status'] ?? 0) === UserMember::STATUS_ACTIVE
            && !empty($data['end_time'])
            && $data['end_time'] > $now;

        // 到期时间优先于存储状态，查询时修正过期标记，避免继续授予有效会员身份。
        if (!$isActive && (int)($data['status'] ?? 0) === UserMember::STATUS_ACTIVE) {
            $this->update((int)$data['id'], ['status' => UserMember::STATUS_EXPIRED]);
            $data['status'] = UserMember::STATUS_EXPIRED;
        }

        return [
            'is_member' => $isActive,
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'status' => (int)($data['status'] ?? 0),
        ];
    }

    /**
     * 合并购买开关与真实会员身份，供价格和页面统一判断权益可见性。
     *
     * 购买开关关闭后，仍有效的会员继续看见身份与价格，但不能购买或续费。
     * enabled 兼容价格可见标识，不能用来代替 member_enabled 的购买开关。
     *
     * @param int $userId 当前用户标识，零表示匿名，仅判断后台开关。
     * @return array 会员有效期、身份、购买开关、权益可见性及购买和续费许可标识。
     */
    public function getMemberRuntimeState(int $userId = 0): array
    {
        $memberEnabled = $this->isMemberEnabled();
        $memberInfo = $userId > 0 ? $this->getMemberInfo($userId) : [
            'is_member' => false,
            'start_time' => null,
            'end_time' => null,
            'status' => UserMember::STATUS_EXPIRED,
        ];
        $isMember = (bool)($memberInfo['is_member'] ?? false);
        $memberVisible = $memberEnabled || $isMember;

        return array_merge($memberInfo, [
            'enabled' => $memberVisible,
            'member_enabled' => $memberEnabled,
            'member_visible' => $memberVisible,
            'can_purchase' => $memberEnabled,
            'can_renew' => $memberEnabled && $isMember,
        ]);
    }

    /**
     * 组装移动会员中心的运行态、权益配置、协议和积分页广告入口。
     *
     * @param int $userId 当前用户标识，零表示匿名。
     * @return array 会员运行态及可见权益配置；不可见时使用原价、单倍积分和空权益。
     */
    public function getMobileMemberInfo(int $userId): array
    {
        $memberState = $this->getMemberRuntimeState($userId);
        $memberVisible = (bool)($memberState['member_visible'] ?? false);

        return array_merge($memberState, [
            'discount_rate' => $memberVisible ? $this->getConfiguredDiscountRate() : 1.00,
            'points_multiplier' => $memberVisible ? $this->getPointsMultiplier() : 1,
            'benefits' => $memberVisible ? $this->getBenefits(true) : [],
            'agreement' => $this->getAgreementConfig(),
            'banner' => $this->getPointsBannerConfig(),
        ]);
    }

    /**
     * 按会员记录的状态和到期时间判断当前是否仍为有效会员。
     *
     * @param int $userId 待核验会员身份的用户标识。
     * @return bool 会员记录是否有效且尚未到期，不受套餐购买开关影响。
     */
    public function isActiveMember(int $userId): bool
    {
        return $this->getMemberInfo($userId)['is_member'];
    }

    /**
     * 校验可售套餐与支付方式，创建共用充值记录的会员单并发起第三方支付。
     *
     * @param int $userId 购买会员套餐的用户标识。
     * @param int $packageId 后台当前可售的会员套餐标识。
     * @param array $options 支付方式、终端、微信交易类型或支付宝回跳地址等支付选项。
     * @return array 会员单号、应付金额、状态及对应支付方式的调起参数，不代表已支付。
     */
    public function createMemberOrder(int $userId, int $packageId, array $options): array
    {
        if (!$this->isMemberEnabled()) {
            throw new AdminResponseException('会员功能暂未开放');
        }

        $packages = $this->getPackages();
        $package = null;
        foreach ($packages as $pkg) {
            if ((int)($pkg['id'] ?? 0) === $packageId) {
                $package = $pkg;
                break;
            }
        }

        if (!$package) {
            throw new AdminResponseException('会员套餐不存在或已下架');
        }

        $packageKey = (string)($package['key'] ?? '');
        $durationDays = (int)($package['duration_days'] ?? 0);
        $price = (float)($package['price'] ?? 0);
        $payType = $this->normalizePayType((string)($options['pay_type'] ?? ''));

        if ($durationDays <= 0 || $price <= 0) {
            throw new AdminResponseException('会员套餐配置不正确');
        }

        $this->assertMemberPayType($payType);

        // 下单时保存当前积分倍数和折扣配置；开通入口还会生成其执行时的权益快照。
        $benefitSnapshot = [
            'points_multiplier' => (float) db_config(ConfigKeyEnum::MEMBER_POINTS_MULTIPLIER, 2),
            'discount_rate' => (float) db_config(ConfigKeyEnum::MEMBER_DISCOUNT_RATE, 1.00),
        ];

        // 会员单与余额充值共用订单存储，但用会员类型区分，不能按余额充值履约。
        $orderData = $this->createMemberRechargeOrder($userId, $packageId, $packageKey, $durationDays, $price, $payType, $benefitSnapshot, $options);

        // 复用充值服务的会员支付入口，保持其支付参数、记录和回调协议一致。
        $rechargeService = app()->make(UserRechargeService::class);

        if ($payType === PayTypeEnum::PAY_TYPE_WECHAT) {
            return $this->buildMemberOrderResult($orderData, $rechargeService->payByWechatMember($orderData, $options));
        }

        return $this->buildMemberOrderResult($orderData, $rechargeService->payByAlipayMember($orderData, $options));
    }

    /**
     * 在充值单锁内按订单状态处理会员开通或续期，回写会员周期和开通时权益快照。
     *
     * @param string $orderSn 调用支付处理链提供的会员充值单号。
     * @return void 无返回值；找不到订单或套餐时长无效时抛出业务异常。
     */
    public function activateMember(string $orderSn): void
    {
        $this->transaction(function () use ($orderSn) {
            $orderData = app()->make(UserRechargeService::class)->findOrderBySn($orderSn, true);
            if (!$orderData) {
                throw new AdminResponseException('会员订单不存在');
            }

            // 此入口按已支付状态直接结束，不在这里再次写入会员周期或权益。
            if ((int)$orderData['status'] === UserRechargeOrder::STATUS_PAID) {
                return true;
            }

            $userId = (int)$orderData['user_id'];
            $durationDays = (int)($orderData['duration_days'] ?? 0);
            if ($durationDays <= 0) {
                Log::error('[member] activateMember duration_days invalid', ['order_sn' => $orderSn]);
                throw new AdminResponseException('会员时长无效');
            }

            $now = date('Y-m-d H:i:s');
            $memberRecord = $this->getModel()->where('user_id', $userId)->find();
            $memberInfo = $this->getMemberInfo($userId);

            if ($memberInfo['is_member'] && $memberInfo['end_time']) {
                // 有效会员从原到期时间续期，不丢失尚未使用的剩余天数。
                $startTime = $memberInfo['end_time'];
            } else {
                // 新开通或已过期时从当前时间起算，避免追溯补算失效期间。
                $startTime = $now;
            }

            $endTime = date('Y-m-d H:i:s', strtotime($startTime . ' + ' . $durationDays . ' days'));

            // 权益倍数和折扣取开通时配置，套餐编码及天数沿用订单，不读取下单权益快照。
            $snapshot = [
                'points_multiplier' => (float) db_config(ConfigKeyEnum::MEMBER_POINTS_MULTIPLIER, 2),
                'discount_rate' => (float) db_config(ConfigKeyEnum::MEMBER_DISCOUNT_RATE, 1.00),
                'package_key' => (string)($orderData['package_key'] ?? ''),
                'duration_days' => $durationDays,
            ];

            if ($memberRecord) {
                $this->update((int)$memberRecord['id'], [
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'status' => UserMember::STATUS_ACTIVE,
                    'last_order_sn' => $orderSn,
                    'benefit_snapshot' => $snapshot,
                ]);
            } else {
                $this->create([
                    'user_id' => $userId,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'status' => UserMember::STATUS_ACTIVE,
                    'last_order_sn' => $orderSn,
                    'benefit_snapshot' => $snapshot,
                ]);
            }

            // 将本次会员周期和权益写回共用充值单，供订单追溯和后台通知使用。
            app()->make(UserRechargeService::class)->update((int)$orderData['id'], [
                'member_start_time' => $startTime,
                'member_end_time' => $endTime,
                'benefit_snapshot' => $snapshot,
            ]);

            app()->make(BackendMessageEventService::class)->vipBought(array_merge($orderData, [
                'member_start_time' => $startTime,
                'member_end_time' => $endTime,
                'benefit_snapshot' => $snapshot,
            ]));

            return true;
        });
    }

    /**
     * 计算指定商户商品金额的实际会员优惠，委托批量路径统一身份和金额规则。
     *
     * @param int $userId 当前购买用户标识。
     * @param int $merchantId 商品所属商户标识，零表示平台自营。
     * @param float $goodsAmount 此商户参与会员优惠计算的商品金额。
     * @return array{enabled: bool, is_member: bool, merchant_joined: bool, discount_rate: float, discount_amount: float, member_end_time: ?string} 权益可见性、有效会员身份、商户参与情况、实际折扣及到期时间。
     */
    public function getMemberDiscountInfo(int $userId, int $merchantId, float $goodsAmount): array
    {
        $map = $this->getMemberDiscountInfoMap($userId, [$merchantId => $goodsAmount]);

        return $map[$merchantId] ?? [
            'enabled' => false,
            'member_enabled' => false,
            'member_visible' => false,
            'can_purchase' => false,
            'can_renew' => false,
            'is_member' => false,
            'merchant_joined' => false,
            'discount_rate' => 1.00,
            'discount_amount' => 0.0,
            'member_end_time' => null,
        ];
    }

    /**
     * 批量计算多商户会员折扣，避免合单确认时重复查询同一用户会员状态和商户配置。
     *
     * @param int $userId 当前购买用户标识。
     * @param array<int, float> $merchantAmounts 商户标识与商品金额映射，平台自营标识为零。
     * @return array<int, array{enabled: bool, is_member: bool, merchant_joined: bool, discount_rate: float, discount_amount: float, member_end_time: ?string}> 按商户标识返回身份、参与状态与实际优惠金额；仅有效会员享受折扣。
     */
    public function getMemberDiscountInfoMap(int $userId, array $merchantAmounts): array
    {
        $amounts = [];
        foreach ($merchantAmounts as $merchantId => $amount) {
            $amounts[max(0, (int)$merchantId)] = $this->normalizeMemberMoney((float)$amount);
        }
        if ($amounts === []) {
            return [];
        }

        $memberState = $this->getMemberRuntimeState($userId);
        $memberVisible = (bool)($memberState['member_visible'] ?? false);
        if (!$memberVisible) {
            // 保留每个商户键，避免关闭权益展示后合单调用方丢失金额映射。
            return array_map(static fn(float $_amount): array => [
                'enabled' => false,
                'member_enabled' => false,
                'member_visible' => false,
                'can_purchase' => false,
                'can_renew' => false,
                'is_member' => false,
                'merchant_joined' => false,
                'discount_rate' => 1.00,
                'discount_amount' => 0.0,
                'member_end_time' => null,
            ], $amounts);
        }

        $merchantIds = array_values(array_filter(array_keys($amounts), static fn(int $id): bool => $id > 0));
        $joinedMap = app()->make(MerchantService::class)->getMemberDiscountJoinedMapByIds($merchantIds);
        $discountRate = $this->getConfiguredDiscountRate();
        $result = [];

        foreach ($amounts as $merchantId => $amount) {
            $merchantJoined = $merchantId === 0 ? true : ($joinedMap[$merchantId] ?? false);
            $discountAmount = $memberState['is_member']
                ? $this->calculateMemberDiscountAmount($amount, $discountRate, $merchantJoined)
                : 0.0;
            $result[$merchantId] = [
                'enabled' => true,
                'member_enabled' => (bool)($memberState['member_enabled'] ?? false),
                'member_visible' => true,
                'can_purchase' => (bool)($memberState['can_purchase'] ?? false),
                'can_renew' => (bool)($memberState['can_renew'] ?? false),
                'is_member' => (bool)$memberState['is_member'],
                'merchant_joined' => $merchantJoined,
                'discount_rate' => $memberState['is_member'] && $merchantJoined ? $discountRate : 1.00,
                'discount_amount' => $discountAmount,
                'member_end_time' => $memberState['is_member'] ? ($memberState['end_time'] ?? null) : null,
            ];
        }

        return $result;
    }

    /**
     * 预览商品满足会员权益后的价格，不把非会员预览价当作当前实际优惠。
     *
     * @param int $merchantId 商品所属商户标识，平台自营传零。
     * @param float $goodsAmount 用于预览的商品原价金额。
     * @param int $userId 当前用户标识，零表示匿名，仍可按购买开关展示会员价。
     * @return array{enabled: bool, member_enabled: bool, member_visible: bool, merchant_joined: bool, discount_rate: float, discount_amount: float, member_price: float, member_price_available: bool} 购买开关、权益可见性、商户参与、预览折扣和会员价可用标识。
     */
    public function getMemberPricePreview(int $merchantId, float $goodsAmount, int $userId = 0): array
    {
        $amount = $this->normalizeMemberMoney($goodsAmount);
        $memberState = $this->getMemberRuntimeState($userId);
        $memberVisible = (bool)($memberState['member_visible'] ?? false);
        return $this->buildMemberPricePreview(
            $amount,
            $memberState,
            $memberVisible && $this->resolveMerchantMemberDiscountJoined($merchantId),
            $memberVisible ? $this->getConfiguredDiscountRate() : 1.00
        );
    }

    /**
     * 批量预览商品会员价，共用一次运行态和商户参与状态查询。
     *
     * @param array<int|string, array{merchant_id?: int, price?: float}> $goods 当前页商品的商户与原价，保留输入索引，缺失字段按平台自营和零金额处理。
     * @param int $userId 当前用户标识，零表示匿名。
     * @return array<int|string, array> 按原输入索引返回与单条预览一致的会员价和权益标识。
     */
    public function getMemberPricePreviewMap(array $goods, int $userId = 0): array
    {
        if ($goods === []) {
            return [];
        }
        $memberState = $this->getMemberRuntimeState($userId);
        $memberVisible = (bool)($memberState['member_visible'] ?? false);
        $joinedMap = $memberVisible
            ? app()->make(MerchantService::class)->getMemberDiscountJoinedMapByIds(array_column($goods, 'merchant_id'))
            : [];
        $discountRate = $memberVisible ? $this->getConfiguredDiscountRate() : 1.00;
        $result = [];
        foreach ($goods as $index => $item) {
            $merchantId = (int)($item['merchant_id'] ?? 0);
            $result[$index] = $this->buildMemberPricePreview(
                $this->normalizeMemberMoney((float)($item['price'] ?? 0)),
                $memberState,
                $merchantId <= 0 || (bool)($joinedMap[$merchantId] ?? false),
                $discountRate
            );
        }
        return $result;
    }

    /**
     * 根据已读取的运行态和商户参与配置计算会员价预览，统一单条与批量金额规则。
     *
     * @param float $amount 已归一化到两位小数的非负商品金额。
     * @param array $memberState 已读取的会员身份、购买开关及价格可见性标识。
     * @param bool $merchantJoined 当前商户是否参加会员优惠，平台自营默认参加。
     * @param float $discountRate 已按配置范围归一化的会员折扣率。
     * @return array 会员运行标识、商户参与、优惠金额及预览价；无有效优惠时价格为零且不可用。
     */
    private function buildMemberPricePreview(float $amount, array $memberState, bool $merchantJoined, float $discountRate): array
    {
        $memberVisible = (bool)($memberState['member_visible'] ?? false);
        if (!$memberVisible) {
            return [
                'enabled' => false,
                'member_enabled' => false,
                'member_visible' => false,
                'can_purchase' => false,
                'can_renew' => false,
                'is_member' => false,
                'merchant_joined' => false,
                'discount_rate' => 1.00,
                'discount_amount' => 0.0,
                'member_price' => 0.0,
                'member_price_available' => false,
            ];
        }

        // 预览用于展示可享权益，不要求当前已是会员；实际结算优惠走身份校验路径。
        $discountAmount = $this->calculateMemberDiscountAmount($amount, $discountRate, $merchantJoined);
        $memberPrice = max(0, (float) bcsub((string) $amount, (string) $discountAmount, 2));
        $memberPrice = $this->normalizeMemberMoney($memberPrice);
        $available = $merchantJoined && $discountAmount > 0 && $memberPrice < $amount;

        return [
            'enabled' => true,
            'member_enabled' => (bool)($memberState['member_enabled'] ?? false),
            'member_visible' => true,
            'can_purchase' => (bool)($memberState['can_purchase'] ?? false),
            'can_renew' => (bool)($memberState['can_renew'] ?? false),
            'is_member' => (bool)($memberState['is_member'] ?? false),
            'merchant_joined' => $merchantJoined,
            'discount_rate' => $discountRate,
            'discount_amount' => $discountAmount,
            'member_price' => $available ? $memberPrice : 0.0,
            'member_price_available' => $available,
        ];
    }

    /**
     * 判断商户是否参与会员折扣，平台自营默认参与。
     *
     * @param int $merchantId 待核验商户标识，非正值表示平台自营。
     * @return bool 当前商户是否允许其商品参加会员优惠。
     */
    private function resolveMerchantMemberDiscountJoined(int $merchantId): bool
    {
        if ($merchantId <= 0) {
            return true;
        }

        $joinedMap = app()->make(MerchantService::class)->getMemberDiscountJoinedMapByIds([$merchantId]);

        return $joinedMap[$merchantId] ?? false;
    }

    /**
     * 标准化会员折扣率，异常配置按原价处理。
     *
     * @param float $discountRate 后台配置的价格折扣率，合法范围为大于零且不超过一。
     * @return float 两位小数的折扣率；超出允许范围时返回一，表示原价。
     */
    private function normalizeMemberDiscountRate(float $discountRate): float
    {
        if ($discountRate <= 0 || $discountRate > 1) {
            return 1.00;
        }

        return (float) sprintf('%.2f', $discountRate);
    }

    /**
     * 根据商户参与状态计算可减免金额，按两位小数截取且不超过原商品金额。
     *
     * @param float $amount 已归一化的非负商品金额。
     * @param float $discountRate 用于计算的会员价格折扣率。
     * @param bool $merchantJoined 商品商户是否参加会员优惠。
     * @return float 可减免的非负金额；商户未参加或商品金额非正时为零。
     */
    private function calculateMemberDiscountAmount(float $amount, float $discountRate, bool $merchantJoined): float
    {
        if (!$merchantJoined || $amount <= 0) {
            return 0.0;
        }

        $discountAmount = max(0, (float) bcmul((string) $amount, (string) (1 - $discountRate), 4));
        return min($amount, $this->normalizeMemberMoney($discountAmount));
    }

    /**
     * 将会员权益计算金额限制为非负值，并按十进制定点规则截取到两位小数。
     *
     * @param float $amount 需要统一精度的商品金额或优惠金额。
     * @return float 非负且保留两位小数计算精度的金额，不进行四舍五入。
     */
    private function normalizeMemberMoney(float $amount): float
    {
        return (float) bcadd((string) max(0, $amount), '0', 2);
    }

    /**
     * 读取后台配置的会员积分倍数，最低按单倍积分计算。
     *
     * @return float 不小于一的积分倍数，不在此方法中判断用户会员身份或购买开关。
     */
    public function getPointsMultiplier(): float
    {
        return max(1, (float) db_config(ConfigKeyEnum::MEMBER_POINTS_MULTIPLIER, 2));
    }

    /**
     * 读取受购买开关控制的原始会员折扣配置，不进行折扣范围归一化。
     *
     * @return float 功能开启时的后台配置折扣率；功能关闭时返回一，表示原价。
     */
    public function getDiscountRate(): float
    {
        if (!$this->isMemberEnabled()) {
            return 1.00;
        }

        return (float) db_config(ConfigKeyEnum::MEMBER_DISCOUNT_RATE, 1.00);
    }

    /**
     * 获取标准化后的会员折扣率配置。
     *
     * @return float 经范围和两位小数归一化的配置折扣率，不直接受购买开关限制。
     */
    private function getConfiguredDiscountRate(): float
    {
        return $this->normalizeMemberDiscountRate((float) db_config(ConfigKeyEnum::MEMBER_DISCOUNT_RATE, 1.00));
    }

    /**
     * 获取积分页会员广告配置。
     *
     * @return array 购买开关与广告开关共同决定的展示标识，以及标题、描述、图片和会员页路由。
     */
    public function getPointsBannerConfig(): array
    {
        $enabled = $this->isMemberEnabled() && (bool) db_config(ConfigKeyEnum::MEMBER_POINTS_BANNER_ENABLED, true);

        return [
            'enabled' => $enabled,
            'title' => (string) db_config(ConfigKeyEnum::MEMBER_POINTS_BANNER_TITLE, '黑卡会员多倍积分'),
            'desc' => (string) db_config(ConfigKeyEnum::MEMBER_POINTS_BANNER_DESC, '立即升级，专享购物多倍积分'),
            'image' => (string) db_config(ConfigKeyEnum::MEMBER_POINTS_BANNER_IMAGE, '/static/images/coin.png'),
            'route' => '/pages/users/member/index',
        ];
    }

    /**
     * 获取会员协议入口配置。
     *
     * @return array 会员协议类型、标题和协议页面路由，不是协议正文。
     */
    public function getAgreementConfig(): array
    {
        return [
            'type' => 'member',
            'title' => '会员服务协议',
            'route' => '/pages/agreement/index?type=member',
        ];
    }

    /**
     * 在共用充值订单存储中创建待支付的会员类型记录，保存套餐与下单权益快照。
     *
     * @param int $userId 购买套餐的用户标识。
     * @param int $packageId 当前套餐配置标识。
     * @param string $packageKey 套餐业务编码，写入订单供后续开通和追溯使用。
     * @param int $durationDays 下单时确定的会员套餐天数。
     * @param float $price 下单时确定的套餐价格，同时作为应付金额。
     * @param string $payType 已归一化并通过会员支付白名单的支付方式。
     * @param array $benefitSnapshot 下单时的积分倍数与折扣率配置快照。
     * @param array $options 支付选项，此处仅保存终端标识用于下单追溯。
     * @return array 已保存的会员充值单记录，包含主键、单号、套餐快照、金额和待支付状态。
     */
    private function createMemberRechargeOrder(int $userId, int $packageId, string $packageKey, int $durationDays, float $price, string $payType, array $benefitSnapshot, array $options): array
    {
        return app()->make(UserRechargeService::class)->createMemberOrderRecord([
            'order_sn' => get_sn('RC'),
            'user_id' => $userId,
            'recharge_id' => 0,
            'order_type' => UserRechargeOrder::ORDER_TYPE_MEMBER,
            'package_id' => $packageId,
            'package_key' => $packageKey,
            'duration_days' => $durationDays,
            'amount' => $price,
            'pay_amount' => $price,
            'pay_type' => $payType,
            'status' => UserRechargeOrder::STATUS_PENDING,
            'benefit_snapshot' => $benefitSnapshot,
            'request_payload' => [
                'create' => [
                    'package_id' => $packageId,
                    'package_key' => $packageKey,
                    'duration_days' => $durationDays,
                    'price' => $price,
                    'pay_type' => $payType,
                    'terminal' => (string)($options['terminal'] ?? ''),
                ],
            ],
        ]);
    }

    /**
     * 合并会员单基础字段与第三方预支付返回，供前端调起支付并跟踪订单。
     *
     * @param array $orderData 创建会员充值单时保存的原始订单记录，不是格式化详情响应。
     * @param array $payResult 充值服务会员支付入口返回的订单状态和支付调起配置，同名字段优先。
     * @return array 合并后的会员单号、金额、支付方式、状态文本及第三方支付参数。
     */
    private function buildMemberOrderResult(array $orderData, array $payResult): array
    {
        $status = (int)($orderData['status'] ?? UserRechargeOrder::STATUS_PENDING);

        return array_merge([
            'order_sn' => (string)($orderData['order_sn'] ?? ''),
            'amount' => (float)($orderData['amount'] ?? 0),
            'pay_amount' => (float)($orderData['pay_amount'] ?? 0),
            'pay_type' => (string)($orderData['pay_type'] ?? ''),
            'status' => $status,
            'status_text' => match ($status) {
                UserRechargeOrder::STATUS_PAID => '已支付',
                UserRechargeOrder::STATUS_FAILED => '支付失败',
                default => '待支付',
            },
        ], $payResult);
    }

    /**
     * 统一微信、支付宝的历史支付别名，未知输入只去空白和转小写，交给白名单拒绝。
     *
     * @param string $payType 请求提供的支付方式或历史别名。
     * @return string 归一化的微信或支付宝支付编码，或待进一步校验的未知编码。
     */
    private function normalizePayType(string $payType): string
    {
        return match (strtolower(trim($payType))) {
            'weixin', 'wxpay', PayTypeEnum::PAY_TYPE_WECHAT => PayTypeEnum::PAY_TYPE_WECHAT,
            'zhifubao', PayTypeEnum::PAY_TYPE_ALIPAY => PayTypeEnum::PAY_TYPE_ALIPAY,
            default => strtolower(trim($payType)),
        };
    }

    /**
     * 限制会员套餐只允许微信和支付宝支付，拒绝余额及其他未支持方式。
     *
     * @param string $payType 已完成别名归一化的支付方式。
     * @return void 无返回值；不在会员支付白名单内时抛出业务异常。
     */
    private function assertMemberPayType(string $payType): void
    {
        if (!in_array($payType, [PayTypeEnum::PAY_TYPE_WECHAT, PayTypeEnum::PAY_TYPE_ALIPAY], true)) {
            throw new AdminResponseException('会员购买仅支持微信或支付宝支付');
        }
    }
}
