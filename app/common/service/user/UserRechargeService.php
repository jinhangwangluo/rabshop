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
use app\common\enum\PaymentConfigKeyEnum;
use app\common\enum\PayTypeEnum;
use app\common\enum\PaymentTerminalEnum;
use app\common\exception\AdminResponseException;
use app\common\model\combination\CombinationData;
use app\common\model\finance\FinanceRecord;
use app\common\model\user\UserRechargeOrder;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\message\BackendMessageEventService;
use app\common\service\setting\ConfigService;
use app\common\service\setting\PaymentConfigService;
use Rabbit\service\payment\PaymentInterface;
use Rabbit\service\payment\PaymentService;
use think\facade\Log;
use Throwable;

/**
 * 用户余额充值服务.
 *
 * 处理用户余额充值、会员购买的完整流程，包括订单创建、微信/支付宝支付发起、
 * 支付异步通知处理、余额入账及会员开通。
 */
class UserRechargeService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    private const PAY_BODY = '余额充值';
    private const LIVE_COIN_PAY_BODY = '直播金币充值';
    private const LIVE_COIN_BALANCE_PAY_BODY = '余额购买直播金币';
    private const FINANCE_TYPE_RECHARGE = 'recharge';
    private const ASSET_TYPE_BALANCE = 'balance';
    private const ASSET_TYPE_COINS = 'coins';
    private const CHANGE_TYPE_IN = 'in';
    private const CHANGE_TYPE_OUT = 'out';
    private const WECHAT_NOTIFY_PATH = '/api/mobile/user/recharge/wechatNotify';
    private const ALIPAY_NOTIFY_PATH = '/api/mobile/user/recharge/alipayNotify';
    private const ALIPAY_TRADE_TYPE = 'wap';

    /**
     * 设置当前服务对应的模型类.
     *
     * @return string 模型类名
     */
    protected function setModel(): string
    {
        return UserRechargeOrder::class;
    }

    /**
     * 获取充值页初始化数据.
     *
     * 返回充值金额选项列表、用户当前余额及可用的支付方式。
     *
     * @param int    $userId   用户ID
     * @param string $terminal 终端类型，见 {@see PaymentTerminalEnum}
     * @return array{amounts: array, balance: float, coins: int, coin_balance: int, coin_exchange_rate: int, methods: array}
     */
    public function getRechargeOptions(int $userId, string $terminal): array
    {
        $asset = app()->make(UserAssetService::class)->getInfo($userId);
        $methods = $this->getRechargePaymentMethods($terminal);
        $coins = (int)($asset['coins'] ?? 0);

        return [
            'amounts' => db_combination_data(CombinationDataTypeEnum::RECHARGE),
            'balance' => (float)($asset['balance'] ?? 0),
            'coins' => $coins,
            'coin_balance' => $coins,
            'coin_exchange_rate' => $this->getLiveCoinExchangeRate(),
            'methods' => $methods,
        ];
    }

    /**
     * 获取直播金币快捷充值档位。
     *
     * 组合数据只维护金币数量，支付金额统一按当前兑换比例实时计算，避免管理员调整
     * live_coin_exchange_rate 后还需要逐条修改快捷档位金额。
     *
     * @return array{list: array, exchange_rate: int} 快捷档位和当前兑换比例
     */
    public function getLiveCoinRechargeOptions(): array
    {
        $exchangeRate = $this->getLiveCoinExchangeRate();
        $items = db_combination_data(CombinationDataTypeEnum::LIVE_COIN_RECHARGE);
        $list = [];

        foreach ($items as $item) {
            $coinAmount = (int)($item['coin_amount'] ?? 0);
            if ($coinAmount <= 0) {
                continue;
            }

            $list[] = [
                'id' => (int)($item['id'] ?? 0),
                'combination_data_id' => (int)($item['combination_data_id'] ?? $item['id'] ?? 0),
                'sort' => (int)($item['sort'] ?? 0),
                'coin_amount' => $coinAmount,
                'pay_amount' => $this->normalizeMoney($coinAmount / $exchangeRate),
            ];
        }

        return [
            'list' => $list,
            'exchange_rate' => $exchangeRate,
        ];
    }

    /**
     * 创建充值单并发起支付.
     *
     * 根据充值金额和支付方式创建订单，随后调用微信或支付宝支付获取前端调起支付所需的配置参数。
     *
     * @param int    $userId  用户ID
     * @param float  $amount  充值金额（自定义金额时使用，有 recharge_id 时忽略）
     * @param string $payType 支付方式，见 {@see PayTypeEnum}
     * @param array  $options 支付选项：
     *                        - recharge_id: int 充值档位ID，0 表示自定义金额
     *                        - order_type: string 订单类型，live_coin 表示直播金币充值
     *                        - coin_amount: int 直播金币充值数量，live_coin 订单按此值重新计算支付金额
     *                        - trade_type: string 微信交易类型（oa_jsapi/h5/miniapp_jsapi）
     *                        - return_url: string 支付宝支付后回跳地址
     *                        - terminal: string 终端类型
     * @return array 支付配置数据，包含 order_sn、pay_type、config 等字段
     */
    public function createAndPay(int $userId, float $amount, string $payType, array $options = []): array
    {
        $rechargeId = (int)($options['recharge_id'] ?? 0);
        $orderType = $this->normalizeRechargeOrderType((string)($options['order_type'] ?? ''));
        $rechargePlan = $orderType === UserRechargeOrder::ORDER_TYPE_LIVE_COIN
            ? $this->resolveLiveCoinPlan((int)($options['coin_amount'] ?? 0), $amount)
            : $this->resolveRechargePlan($rechargeId, $amount);
        if ($orderType === UserRechargeOrder::ORDER_TYPE_LIVE_COIN) {
            $rechargeId = 0;
        }
        $payType = $this->normalizePayType($payType);
        $this->assertRechargePayType($payType, $orderType);
        $orderData = $this->createRechargeOrder($userId, $rechargeId, $rechargePlan, $payType, [
            ...$options,
            'order_type' => $orderType,
        ]);
        $payBody = $orderType === UserRechargeOrder::ORDER_TYPE_LIVE_COIN ? self::LIVE_COIN_PAY_BODY : self::PAY_BODY;

        if ($orderType === UserRechargeOrder::ORDER_TYPE_LIVE_COIN && $payType === PayTypeEnum::PAY_TYPE_YUE) {
            return $this->payLiveCoinByBalance($orderData);
        }

        if ($payType === PayTypeEnum::PAY_TYPE_WECHAT) {
            return $this->payByWechat($orderData, $options, $payBody);
        }

        return $this->payByAlipay($orderData, $options, $payBody);
    }

    /**
     * 获取充值单详情.
     *
     * 根据用户ID和订单号查询充值单，返回订单状态、金额等格式化数据。
     *
     * @param int    $userId  用户ID
     * @param string $orderSn 充值单号
     * @return array 订单详情，包含 order_sn、amount、pay_amount、pay_type、status 等字段
     */
    public function detail(int $userId, string $orderSn): array
    {
        $order = $this->getModel()
            ->where('user_id', $userId)
            ->where('order_sn', $orderSn)
            ->find();
        if (!$order) {
            throw new AdminResponseException('充值单不存在');
        }

        return $this->buildStatePayload($order->toArray());
    }

    /**
     * 获取后台充值订单详情.
     *
     * @param int $id 充值订单ID
     * @return array
     * @throws AdminResponseException
     */
    public function getAdminRechargeOrderDetail(int $id): array
    {
        $order = $this->get($id);
        if (!$order) {
            throw new AdminResponseException('充值订单不存在');
        }

        $data = $order->toArray();
        $state = $this->buildStatePayload($data);
        $orderType = (string)($data['order_type'] ?? UserRechargeOrder::ORDER_TYPE_RECHARGE);
        $payType = (string)($data['pay_type'] ?? '');

        return array_merge($data, $state, [
            'id' => (int)($data['id'] ?? 0),
            'user_id' => (int)($data['user_id'] ?? 0),
            'order_type' => $orderType,
            'order_type_text' => $this->getRechargeOrderTypeText($orderType),
            'pay_type_text' => PayTypeEnum::PAY_TYPE_ALL[$payType] ?? ($payType ?: '-'),
            'transaction_id' => (string)($data['transaction_id'] ?? ''),
            'create_time' => (string)($data['create_time'] ?? ''),
            'update_time' => (string)($data['update_time'] ?? ''),
        ]);
    }

    /**
     * 处理微信支付异步回调.
     *
     * 解密并验证微信支付回调通知，根据交易状态标记订单为已支付或失败。
     * 支付成功后触发余额入账或会员开通。
     *
     * @param string $rawBody 微信回调原始请求体（XML/JSON）
     * @param array  $headers 回调请求头
     * @return array{order_sn: string, trade_type: string, trade_state: string}
     */
    public function handleWechatNotify(string $rawBody, array $headers): array
    {
        [$result, $tradeType] = $this->decryptWechatNotify($rawBody, $headers);
        $orderSn = (string)($result['out_trade_no'] ?? '');
        $tradeState = strtoupper((string)($result['trade_status'] ?? ''));
        $rawPayload = is_array($result['raw'] ?? null) ? $result['raw'] : $result;
        if ($orderSn === '') {
            throw new AdminResponseException('支付回调缺少充值单号');
        }

        if (!($result['is_success'] ?? false)) {
            $this->markFailed($orderSn, $rawPayload);
            return ['order_sn' => $orderSn, 'trade_type' => $tradeType, 'trade_state' => $tradeState];
        }

        $this->markPaid($orderSn, PayTypeEnum::PAY_TYPE_WECHAT, [
            'transaction_id' => (string)($result['transaction_id'] ?? ''),
            'pay_time' => $this->resolveWechatSuccessTime((string)($result['pay_time'] ?? '')),
            'notify_payload' => $rawPayload,
        ]);

        return ['order_sn' => $orderSn, 'trade_type' => $tradeType, 'trade_state' => $tradeState];
    }

    /**
     * 处理支付宝支付异步回调.
     *
     * 验证支付宝回调签名，根据交易状态标记订单为已支付或失败。
     * 支付成功后触发余额入账或会员开通。
     *
     * @param array $payload 支付宝回调参数
     * @return array{order_sn: string, trade_status: string} 回调处理结果
     */
    public function handleAlipayNotify(array $payload): array
    {
        $notify = $this->makeAlipayPay()->notify($payload);
        $orderSn = (string)($notify['out_trade_no'] ?? '');
        $tradeStatus = strtoupper((string)($notify['trade_status'] ?? ''));
        $rawPayload = is_array($notify['raw'] ?? null) ? $notify['raw'] : $payload;
        if ($orderSn === '') {
            throw new AdminResponseException('支付宝回调缺少充值单号');
        }

        if (!($notify['is_success'] ?? false)) {
            $this->markFailed($orderSn, $rawPayload);
            return ['order_sn' => $orderSn, 'trade_status' => $tradeStatus];
        }

        $this->markPaid($orderSn, PayTypeEnum::PAY_TYPE_ALIPAY, [
            'transaction_id' => (string)($notify['transaction_id'] ?? ''),
            'pay_time' => $this->resolveAlipaySuccessTime((string)($notify['pay_time'] ?? '')),
            'notify_payload' => $rawPayload,
        ]);

        return ['order_sn' => $orderSn, 'trade_status' => $tradeStatus];
    }

    /**
     * 会员订单微信支付.
     *
     * 供 {@see UserMemberService} 调用，使用「会员购买」作为支付商品描述发起微信支付。
     *
     * @param array $order   订单数据，需包含 order_sn、user_id、pay_amount 等字段
     * @param array $options 支付选项，同 {@see payByWechat()}
     * @return array 支付配置数据
     */
    public function payByWechatMember(array $order, array $options = []): array
    {
        return $this->payByWechat($order, $options, '会员购买');
    }

    /**
     * 会员订单支付宝支付.
     *
     * 供 {@see UserMemberService} 调用，使用「会员购买」作为支付商品描述发起支付宝支付。
     *
     * @param array $order   订单数据，需包含 order_sn、user_id、pay_amount 等字段
     * @param array $options 支付选项，同 {@see payByAlipay()}
     * @return array 支付配置数据
     */
    public function payByAlipayMember(array $order, array $options = []): array
    {
        return $this->payByAlipay($order, $options, '会员购买');
    }

    /**
     * 根据订单号查询订单.
     *
     * 可用于充值订单和会员订单的查询，支持行级锁防止并发问题。
     *
     * @param string $orderSn 订单号
     * @param bool   $lock    是否加行锁（用于支付回调等并发场景）
     * @return array|null 订单数据，不存在时返回 null
     */
    public function findOrderBySn(string $orderSn, bool $lock = false): ?array
    {
        $query = $this->getModel()->where('order_sn', $orderSn);
        if ($lock) {
            $query->lock(true);
        }
        $order = $query->find();

        return $order ? $order->toArray() : null;
    }

    /**
     * 创建会员订单记录.
     *
     * 供 {@see UserMemberService} 调用，在会员下单时创建订单记录。
     *
     * @param array $data 订单数据，需包含 user_id、amount、pay_amount、pay_type、order_type 等字段
     * @return array 创建后的订单数据
     */
    public function createMemberOrderRecord(array $data): array
    {
        $order = $this->create($data);

        return $order->toArray();
    }

    /**
     * 发起微信支付.
     *
     * 根据 trade_type 选择小程序支付、H5 支付或公众号 JSAPI 支付，
     * 向微信支付下单并记录请求/响应数据。
     *
     * @param array  $order   订单数据，需包含 id、order_sn、user_id、pay_amount 等字段
     * @param array  $options 支付选项：
     *                        - trade_type: string 交易类型（oa_jsapi/h5/miniapp_jsapi）
     * @param string $body    支付商品描述，空则使用默认值「余额充值」
     * @return array 支付配置数据，包含 order_sn、pay_type、trade_type、config 等字段
     */
    protected function payByWechat(array $order, array $options = [], string $body = ''): array
    {
        $payBody = $body !== '' ? $body : self::PAY_BODY;
        $tradeType = $this->normalizeWechatTradeType((string)($options['trade_type'] ?? 'oa_jsapi'));
        $openid = $tradeType === 'h5' ? '' : $this->resolveWechatOpenid((int)$order['user_id'], $tradeType);
        $wechatConfig = $this->getWechatPayConfigByTradeType($tradeType);
        $notifyUrl = $this->resolveNotifyUrl((string)($wechatConfig['notify_url'] ?? ''), self::WECHAT_NOTIFY_PATH);
        $amountFen = $this->yuanToFen((float)$order['pay_amount']);
        $wechatPay = $this->makeWechatPay($wechatConfig);

        try {
            $response = $wechatPay->pay([
                'trade_type' => $tradeType,
                'out_trade_no' => (string)$order['order_sn'],
                'amount' => (float)$order['pay_amount'],
                'amount_fen' => $amountFen,
                'subject' => $payBody,
                'body' => $payBody,
                'notify_url' => $notifyUrl,
                'openid' => $openid,
                'client_ip' => (string)request()->ip(),
            ]);

            $this->markPayInitiated((int)$order['id'], $tradeType, [
                'trade_type' => $tradeType,
                'notify_url' => $notifyUrl,
                'amount_fen' => $amountFen,
                'body' => $payBody,
                'openid' => $openid,
            ], $response);
        } catch (Throwable $throwable) {
            Log::error('[user-recharge] wechat pay create failed', [
                'order_sn' => $order['order_sn'] ?? '',
                'trade_type' => $tradeType,
                'message' => $throwable->getMessage(),
            ]);
            throw new AdminResponseException($throwable->getMessage() ?: '微信支付发起失败');
        }

        return array_merge($this->buildStatePayload($order), [
            'pay_type' => PayTypeEnum::PAY_TYPE_WECHAT,
            'trade_type' => $tradeType,
            'config' => $response,
        ]);
    }

    /**
     * 发起支付宝支付.
     *
     * 调用支付宝 WAP 支付下单，生成支付跳转链接并记录请求/响应数据。
     *
     * @param array  $order   订单数据，需包含 id、order_sn、user_id、pay_amount 等字段
     * @param array  $options 支付选项：
     *                        - return_url: string 支付完成后回跳地址
     * @param string $body    支付商品描述，空则使用默认值「余额充值」
     * @return array 支付配置数据，包含 order_sn、pay_type、trade_type、config 等字段
     */
    protected function payByAlipay(array $order, array $options = [], string $body = ''): array
    {
        $payBody = $body !== '' ? $body : self::PAY_BODY;
        $alipayConfig = $this->getAlipayConfig();
        $notifyUrl = $this->resolveNotifyUrl((string)($alipayConfig['notify_url'] ?? ''), self::ALIPAY_NOTIFY_PATH);
        $returnUrl = trim((string)($options['return_url'] ?? ''));

        try {
            $response = $this->makeAlipayPay($alipayConfig)->pay([
                'trade_type' => 'wap',
                'out_trade_no' => (string)$order['order_sn'],
                'amount' => (float)$order['pay_amount'],
                'subject' => $payBody,
                'body' => $payBody,
                'notify_url' => $notifyUrl,
                'return_url' => $returnUrl,
            ]);
            $this->markPayInitiated((int)$order['id'], self::ALIPAY_TRADE_TYPE, [
                'notify_url' => $notifyUrl,
                'return_url' => $returnUrl,
                'amount' => (float)$order['pay_amount'],
                'body' => $payBody,
            ], $response);
        } catch (Throwable $throwable) {
            Log::error('[user-recharge] alipay pay create failed', [
                'order_sn' => $order['order_sn'] ?? '',
                'message' => $throwable->getMessage(),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
            ]);
            throw new AdminResponseException($throwable->getMessage() ?: '支付宝支付发起失败');
        }

        return array_merge($this->buildStatePayload($order), [
            'pay_type' => PayTypeEnum::PAY_TYPE_ALIPAY,
            'trade_type' => self::ALIPAY_TRADE_TYPE,
            'config' => $response,
        ]);
    }

    /**
     * 标记订单为已支付并完成后续业务.
     *
     * 在数据库事务中加锁更新订单状态，防止重复回调。
     * - 充值订单：调用 UserAssetService 增加用户余额，写入财务流水
     * - 会员订单：仅更新支付状态，事务外由 UserMemberService 完成会员开通
     *
     * @param string $orderSn 订单号
     * @param string $payType 支付方式，见 {@see PayTypeEnum}
     * @param array  $payload 支付回调数据：
     *                        - transaction_id: string 第三方交易号
     *                        - pay_time: string 支付完成时间
     *                        - notify_payload: array 完整的回调参数
     */
    protected function markPaid(string $orderSn, string $payType, array $payload = []): void
    {
        $isMemberOrder = $this->transaction(function () use ($orderSn, $payType, $payload): bool {
            $order = $this->getModel()->where('order_sn', $orderSn)->lock(true)->find();
            if (!$order) {
                throw new AdminResponseException('充值单不存在');
            }

            $orderData = $order->toArray();
            if ((int)$orderData['status'] === UserRechargeOrder::STATUS_PAID) {
                return false;
            }

            $amount = (float)$orderData['amount'];
            $orderType = $orderData['order_type'] ?? UserRechargeOrder::ORDER_TYPE_RECHARGE;
            $isMember = $orderType === UserRechargeOrder::ORDER_TYPE_MEMBER;
            $isLiveCoin = $orderType === UserRechargeOrder::ORDER_TYPE_LIVE_COIN;

            // 会员订单只写支付状态，开通/续期在事务外由 UserMemberService 处理
            if ($isMember) {
                $order->save([
                    'status' => UserRechargeOrder::STATUS_PAID,
                    'pay_type' => $payType,
                    'transaction_id' => (string)($payload['transaction_id'] ?? ''),
                    'pay_time' => $payload['pay_time'] ?? date('Y-m-d H:i:s'),
                    'notify_payload' => $payload['notify_payload'] ?? [],
                ]);

                return true;
            }

            if ($isLiveCoin) {
                app()->make(UserAssetService::class)->changeAsset(
                    (int)$orderData['user_id'],
                    self::ASSET_TYPE_COINS,
                    (string)(int)$amount,
                    self::CHANGE_TYPE_IN,
                    self::LIVE_COIN_PAY_BODY,
                    [
                        'source_type' => UserRechargeOrder::ORDER_TYPE_LIVE_COIN,
                        'order_no' => $orderSn,
                        'source_desc' => self::LIVE_COIN_PAY_BODY,
                    ]
                );

                $order->save([
                    'status' => UserRechargeOrder::STATUS_PAID,
                    'pay_type' => $payType,
                    'transaction_id' => (string)($payload['transaction_id'] ?? ''),
                    'pay_time' => $payload['pay_time'] ?? date('Y-m-d H:i:s'),
                    'notify_payload' => $payload['notify_payload'] ?? [],
                ]);

                return false;
            }

            // 充值订单：余额入账
            app()->make(UserAssetService::class)->changeAsset(
                (int)$orderData['user_id'],
                self::ASSET_TYPE_BALANCE,
                $amount,
                self::CHANGE_TYPE_IN,
                self::PAY_BODY,
                [
                    'source_type' => self::FINANCE_TYPE_RECHARGE,
                    'order_no' => $orderSn,
                    'source_desc' => self::PAY_BODY,
                ]
            );

            $this->createRechargeFinanceRecord((int)$orderData['user_id'], $amount, $orderSn);

            $order->save([
                'status' => UserRechargeOrder::STATUS_PAID,
                'pay_type' => $payType,
                'transaction_id' => (string)($payload['transaction_id'] ?? ''),
                'pay_time' => $payload['pay_time'] ?? date('Y-m-d H:i:s'),
                'notify_payload' => $payload['notify_payload'] ?? [],
            ]);

            app()->make(BackendMessageEventService::class)->balanceRecharged(array_merge($orderData, [
                'status' => UserRechargeOrder::STATUS_PAID,
                'pay_type' => $payType,
                'pay_time' => $payload['pay_time'] ?? date('Y-m-d H:i:s'),
            ]));

            return false;
        });

        if ($isMemberOrder) {
            app()->make(UserMemberService::class)->activateMember($orderSn);
        }
    }

    /**
     * 标记订单为支付失败.
     *
     * 仅在订单非已支付状态下更新为失败，避免覆盖已成功的回调。
     *
     * @param string $orderSn 订单号
     * @param array  $payload 回调通知数据，用于问题排查
     */
    protected function markFailed(string $orderSn, array $payload = []): void
    {
        $order = $this->getModel()->where('order_sn', $orderSn)->find();
        if (!$order || (int)$order['status'] === UserRechargeOrder::STATUS_PAID) {
            return;
        }

        $order->save([
            'status' => UserRechargeOrder::STATUS_FAILED,
            'notify_payload' => $payload,
        ]);
    }

    /**
     * 获取指定终端可用的充值支付方式.
     *
     * 从支付配置中筛选出微信和支付宝两种支付方式。
     *
     * @param string $terminal 终端类型，见 {@see PaymentTerminalEnum}
     * @return array 支付方式列表，每项包含 pay_type、name 等字段
     */
    protected function getRechargePaymentMethods(string $terminal): array
    {
        $terminal = $this->resolveTerminal($terminal);
        $methods = app()->make(PaymentConfigService::class)->getEnabledMethodsByTerminal($terminal);

        return array_values(array_filter($methods, static function (array $method) {
            return in_array((string)($method['pay_type'] ?? ''), [PayTypeEnum::PAY_TYPE_WECHAT, PayTypeEnum::PAY_TYPE_ALIPAY], true);
        }));
    }

    /**
     * 构建订单状态格式化数据.
     *
     * 将订单原始数据转换为前端可用的标准化结构，包含状态文本映射。
     *
     * @param array $order 订单原始数据
     * @return array{order_sn: string, order_type: string, amount: float, pay_amount: float, pay_type: string, status: int, status_text: string, pay_time: string|null, coin_amount: int, exchange_rate: int}
     */
    protected function buildStatePayload(array $order): array
    {
        $status = (int)($order['status'] ?? UserRechargeOrder::STATUS_PENDING);
        $orderType = (string)($order['order_type'] ?? UserRechargeOrder::ORDER_TYPE_RECHARGE);
        $requestPayload = (array)($order['request_payload'] ?? []);
        $createPayload = is_array($requestPayload['create'] ?? null) ? $requestPayload['create'] : [];
        $exchangeRate = $orderType === UserRechargeOrder::ORDER_TYPE_LIVE_COIN
            ? max(1, (int)($createPayload['exchange_rate'] ?? $this->getLiveCoinExchangeRate()))
            : 0;
        $coinAmount = $orderType === UserRechargeOrder::ORDER_TYPE_LIVE_COIN
            ? (int)($order['amount'] ?? 0)
            : 0;

        return [
            'order_sn' => (string)($order['order_sn'] ?? ''),
            'order_type' => $orderType,
            'amount' => (float)($order['amount'] ?? 0),
            'pay_amount' => (float)($order['pay_amount'] ?? 0),
            'pay_type' => (string)($order['pay_type'] ?? ''),
            'status' => $status,
            'status_text' => match ($status) {
                UserRechargeOrder::STATUS_PAID => '已支付',
                UserRechargeOrder::STATUS_FAILED => '支付失败',
                default => '待支付',
            },
            'pay_time' => $order['pay_time'] ?? null,
            'coin_amount' => $coinAmount,
            'exchange_rate' => $exchangeRate,
        ];
    }

    /**
     * 获取充值订单类型文案.
     */
    protected function getRechargeOrderTypeText(string $orderType): string
    {
        return match ($orderType) {
            UserRechargeOrder::ORDER_TYPE_MEMBER => '会员购买',
            UserRechargeOrder::ORDER_TYPE_LIVE_COIN => '直播金币充值',
            default => '余额充值',
        };
    }

    /**
     * 金额标准化处理.
     *
     * 将金额转为保留两位小数的正数浮点数。
     *
     * @param float|string $value 原始金额
     * @return float 标准化后的金额
     */
    protected function normalizeMoney(float|string $value): float
    {
        return (float)number_format(max(0, (float)$value), 2, '.', '');
    }

    /**
     * 规范化充值订单类型。
     *
     * @param string $orderType 原始订单类型。
     * @return string 标准订单类型。
     */
    protected function normalizeRechargeOrderType(string $orderType): string
    {
        return $orderType === UserRechargeOrder::ORDER_TYPE_LIVE_COIN
            ? UserRechargeOrder::ORDER_TYPE_LIVE_COIN
            : UserRechargeOrder::ORDER_TYPE_RECHARGE;
    }

    /**
     * 校验支付方式是否为充值支持的支付类型.
     *
     * @param string $payType 支付方式标识
     * @throws AdminResponseException 不支持的支付方式时抛出
     */
    protected function assertRechargePayType(string $payType, string $orderType = UserRechargeOrder::ORDER_TYPE_RECHARGE): void
    {
        $supportedTypes = [PayTypeEnum::PAY_TYPE_WECHAT, PayTypeEnum::PAY_TYPE_ALIPAY];
        if ($orderType === UserRechargeOrder::ORDER_TYPE_LIVE_COIN) {
            $supportedTypes[] = PayTypeEnum::PAY_TYPE_YUE;
        }

        if (!in_array($payType, $supportedTypes, true)) {
            $message = $orderType === UserRechargeOrder::ORDER_TYPE_LIVE_COIN
                ? '直播金币充值仅支持余额、微信或支付宝支付'
                : '充值仅支持微信或支付宝支付';
            throw new AdminResponseException($message);
        }
    }

    /**
     * 使用账户余额购买直播金币。
     *
     * @param array $order 充值订单数据
     * @return array 支付完成后的订单状态
     */
    protected function payLiveCoinByBalance(array $order): array
    {
        $orderSn = (string)($order['order_sn'] ?? '');
        if ($orderSn === '') {
            throw new AdminResponseException('充值单号不能为空');
        }

        $paidOrder = $this->transaction(function () use ($orderSn): array {
            $lockedOrder = $this->getModel()->where('order_sn', $orderSn)->lock(true)->find();
            if (!$lockedOrder) {
                throw new AdminResponseException('充值单不存在');
            }

            $orderData = $lockedOrder->toArray();
            if ((string)($orderData['order_type'] ?? '') !== UserRechargeOrder::ORDER_TYPE_LIVE_COIN) {
                throw new AdminResponseException('订单类型不支持余额支付');
            }
            if ((int)$orderData['status'] === UserRechargeOrder::STATUS_PAID) {
                return $orderData;
            }

            $userId = (int)$orderData['user_id'];
            $payAmount = $this->normalizeMoney((float)$orderData['pay_amount']);
            $coinAmount = (int)$orderData['amount'];
            if ($payAmount <= 0 || $coinAmount <= 0) {
                throw new AdminResponseException('直播金币充值金额不正确');
            }

            $assetService = app()->make(UserAssetService::class);
            try {
                $assetService->changeAsset(
                    $userId,
                    self::ASSET_TYPE_BALANCE,
                    $payAmount,
                    self::CHANGE_TYPE_OUT,
                    self::LIVE_COIN_BALANCE_PAY_BODY,
                    [
                        'source_type' => 'live_coin_balance_pay',
                        'order_no' => $orderSn,
                        'source_desc' => self::LIVE_COIN_BALANCE_PAY_BODY,
                    ]
                );
            } catch (AdminResponseException) {
                throw new AdminResponseException('余额不足，请更换支付方式或先充值余额');
            }

            $assetService->changeAsset(
                $userId,
                self::ASSET_TYPE_COINS,
                (string)$coinAmount,
                self::CHANGE_TYPE_IN,
                self::LIVE_COIN_PAY_BODY,
                [
                    'source_type' => UserRechargeOrder::ORDER_TYPE_LIVE_COIN,
                    'order_no' => $orderSn,
                    'source_desc' => self::LIVE_COIN_PAY_BODY,
                ]
            );

            $lockedOrder->save([
                'status' => UserRechargeOrder::STATUS_PAID,
                'pay_type' => PayTypeEnum::PAY_TYPE_YUE,
                'transaction_id' => '',
                'pay_time' => date('Y-m-d H:i:s'),
                'notify_payload' => [
                    'pay_type' => PayTypeEnum::PAY_TYPE_YUE,
                    'source' => 'balance',
                ],
            ]);

            return $lockedOrder->toArray();
        });

        return array_merge($this->buildStatePayload($paidOrder), [
            'pay_type' => PayTypeEnum::PAY_TYPE_YUE,
            'trade_type' => 'balance',
            'config' => [],
        ]);
    }

    /**
     * 创建充值订单记录.
     *
     * 生成订单号、记录请求参数，创建状态为待支付的订单。
     *
     * @param int    $userId      用户ID
     * @param int    $rechargeId  充值档位ID，0 表示自定义金额
     * @param array  $rechargePlan 充值方案，包含 amount、pay_amount、raw
     * @param string $payType     支付方式
     * @param array  $options     附加选项（terminal 等）
     * @return array 创建的订单数据
     */
    protected function createRechargeOrder(int $userId, int $rechargeId, array $rechargePlan, string $payType, array $options): array
    {
        $amount = (float)$rechargePlan['amount'];
        $payAmount = (float)$rechargePlan['pay_amount'];
        if ($amount <= 0 || $payAmount <= 0) {
            throw new AdminResponseException('充值金额必须大于0');
        }

        $order = $this->create([
            'order_sn' => get_sn('RC'),
            'user_id' => $userId,
            'recharge_id' => $rechargeId,
            'order_type' => $this->normalizeRechargeOrderType((string)($options['order_type'] ?? '')),
            'amount' => $amount,
            'pay_amount' => $payAmount,
            'pay_type' => $payType,
            'status' => UserRechargeOrder::STATUS_PENDING,
            'request_payload' => [
                'create' => [
                    'amount' => $amount,
                    'coin_amount' => (int)($rechargePlan['amount'] ?? 0),
                    'pay_amount' => $payAmount,
                    'pay_type' => $payType,
                    'recharge_id' => $rechargeId,
                    'recharge_plan' => $rechargePlan['raw'],
                    'exchange_rate' => (int)($rechargePlan['exchange_rate'] ?? 0),
                    'terminal' => (string)($options['terminal'] ?? ''),
                ],
            ],
        ]);

        return $order->toArray();
    }

    /**
     * 记录支付发起时的请求/响应数据.
     *
     * 在订单上追加支付请求参数和第三方支付返回的响应数据，用于问题排查。
     *
     * @param int    $orderId        订单主键ID
     * @param string $tradeType      交易类型
     * @param array  $requestPayload 支付请求参数
     * @param array  $responsePayload 第三方支付返回的响应
     */
    protected function markPayInitiated(int $orderId, string $tradeType, array $requestPayload, array $responsePayload): void
    {
        try {
            $order = $this->get($orderId);
            $currentPayload = $order ? (array)$order->request_payload : [];
            $this->update($orderId, [
                'trade_type' => $tradeType,
                'request_payload' => array_merge($currentPayload, [
                    'pay' => $requestPayload,
                ]),
                'response_payload' => $responsePayload,
            ]);
        } catch (Throwable $throwable) {
            Log::error('[user-recharge] pay initiated record failed', [
                'order_id' => $orderId,
                'trade_type' => $tradeType,
                'message' => $throwable->getMessage(),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
            ]);
        }
    }

    /**
     * 写入充值财务流水记录.
     *
     * @param int    $userId  用户ID
     * @param float  $amount  充值金额
     * @param string $orderSn 充值单号
     */
    protected function createRechargeFinanceRecord(int $userId, float $amount, string $orderSn): void
    {
        FinanceRecord::create([
            'user_id' => $userId,
            'merchant_id' => 0,
            'type' => self::FINANCE_TYPE_RECHARGE,
            'amount' => $amount,
            'change_type' => self::CHANGE_TYPE_IN,
            'remark' => '用户余额充值',
            'order_sn' => $orderSn,
        ]);
    }

    /**
     * 解析充值方案.
     *
     * 有 rechargeId 时从组合数据表查询预设档位；无则使用自定义金额。
     *
     * @param int   $rechargeId  充值档位ID（0 表示自定义金额）
     * @param float $customAmount 自定义充值金额
     * @return array{amount: float, pay_amount: float, raw: array} 充值方案
     * @throws AdminResponseException 档位不存在或金额配置不正确时抛出
     */
    protected function resolveRechargePlan(int $rechargeId, float $customAmount): array
    {
        if ($rechargeId <= 0) {
            $amount = $this->normalizeMoney($customAmount);

            return [
                'amount' => $amount,
                'pay_amount' => $amount,
                'raw' => [],
            ];
        }

        $record = CombinationData::where('id', $rechargeId)
            ->where('merchant_id', 0)
            ->where('data_type', CombinationDataTypeEnum::RECHARGE)
            ->where('status', 1)
            ->find();
        if (!$record) {
            throw new AdminResponseException('充值金额配置不存在或已禁用');
        }

        $data = $record->data_json;
        $amount = $this->normalizeMoney(
            $data['amount']
            ?? $data['value']
            ?? $data['money']
            ?? $data['off_amt']
            ?? $data['price']
            ?? 0
        );
        $payAmount = $this->normalizeMoney($data['pay_amount'] ?? $data['payAmount'] ?? $data['sale_price'] ?? $data['salePrice'] ?? $data['price'] ?? $amount);
        if ($amount <= 0 || $payAmount <= 0) {
            throw new AdminResponseException('充值金额配置不正确');
        }

        return [
            'amount' => $amount,
            'pay_amount' => $payAmount,
            'raw' => $data,
        ];
    }

    /**
     * 解析直播金币充值方案。
     *
     * @param int   $coinAmount 直播金币数量。
     * @param float $fallbackPayAmount 兼容旧前端的支付金额，缺少 coin_amount 时按兑换比例反推金币数。
     * @return array{amount: int, pay_amount: float, raw: array, exchange_rate: int}
     */
    protected function resolveLiveCoinPlan(int $coinAmount, float $fallbackPayAmount = 0): array
    {
        $exchangeRate = $this->getLiveCoinExchangeRate();
        if ($coinAmount <= 0 && $fallbackPayAmount > 0) {
            $coinAmount = (int)round($this->normalizeMoney($fallbackPayAmount) * $exchangeRate);
        }
        if ($coinAmount <= 0) {
            throw new AdminResponseException('直播金币数量必须大于 0');
        }

        $payAmount = $this->normalizeMoney($coinAmount / $exchangeRate);
        if ($payAmount <= 0) {
            throw new AdminResponseException('直播金币数量过小，无法生成支付金额');
        }

        return [
            'amount' => $coinAmount,
            'pay_amount' => $payAmount,
            'raw' => [
                'pay_amount' => $payAmount,
                'coin_amount' => $coinAmount,
                'exchange_rate' => $exchangeRate,
            ],
            'exchange_rate' => $exchangeRate,
        ];
    }

    /**
     * 获取直播金币兑换比例。
     *
     * @return int 1 元可兑换金币数量。
     */
    protected function getLiveCoinExchangeRate(): int
    {
        return max(1, (int)db_config(ConfigKeyEnum::LIVE_COIN_EXCHANGE_RATE, 10));
    }

    /**
     * 支付方式别名标准化.
     *
     * 将各种支付方式别名（如 weixin、zhifubao）统一转换为 {@see PayTypeEnum} 常量值。
     *
     * @param string $payType 原始支付方式字符串
     * @return string 标准化后的支付方式标识
     */
    protected function normalizePayType(string $payType): string
    {
        return match (strtolower(trim($payType))) {
            'weixin', 'wxpay', PayTypeEnum::PAY_TYPE_WECHAT => PayTypeEnum::PAY_TYPE_WECHAT,
            'zhifubao', PayTypeEnum::PAY_TYPE_ALIPAY => PayTypeEnum::PAY_TYPE_ALIPAY,
            'balance', PayTypeEnum::PAY_TYPE_YUE => PayTypeEnum::PAY_TYPE_YUE,
            default => strtolower(trim($payType)),
        };
    }

    /**
     * 终端类型标准化.
     *
     * 将前端传入的终端标识映射为 {@see PaymentTerminalEnum} 常量值。
     *
     * @param string $terminal 前端终端标识（如 miniapp、wechat-h5、pc）
     * @return string 标准化后的终端类型
     */
    protected function resolveTerminal(string $terminal): string
    {
        return match ($terminal) {
            'miniapp', 'mp_weixin' => PaymentTerminalEnum::TERMINAL_MP_WEIXIN,
            'wechat-h5', 'official_account', 'wechat_h5' => PaymentTerminalEnum::TERMINAL_WECHAT_H5,
            'pc' => PaymentTerminalEnum::TERMINAL_PC,
            default => PaymentTerminalEnum::TERMINAL_H5,
        };
    }

    /**
     * 微信交易类型标准化.
     *
     * 将前端传入的交易类型别名统一为内部使用的标准值（miniapp_jsapi/h5/oa_jsapi）。
     *
     * @param string $tradeType 前端交易类型标识
     * @return string 标准化后的交易类型
     */
    protected function normalizeWechatTradeType(string $tradeType): string
    {
        $tradeType = strtolower(trim($tradeType));

        return match ($tradeType) {
            'miniapp', 'mini_program', 'miniapp_jsapi', 'wechat_mp' => 'miniapp_jsapi',
            'h5', 'mweb', 'wap', 'wechat_wap' => 'h5',
            'weixinh5', 'wechat_h5', 'official_account', 'wechat_oa', 'oa_jsapi' => 'oa_jsapi',
            default => 'oa_jsapi',
        };
    }

    /**
     * 获取用户的微信 OpenID.
     *
     * 根据交易类型从对应平台（小程序/公众号）的授权记录中获取 OpenID。
     *
     * @param int    $userId    用户ID
     * @param string $tradeType 交易类型（miniapp_jsapi/oa_jsapi）
     * @return string 微信 OpenID
     * @throws AdminResponseException 未完成授权时抛出
     */
    protected function resolveWechatOpenid(int $userId, string $tradeType): string
    {
        $platform = $tradeType === 'miniapp_jsapi' ? 'wechat_mp' : 'wechat_oa';
        $auth = app()->make(PlatformAuthService::class)->getByUidAndPlatform($userId, $platform);
        $openid = trim((string)($auth['openid'] ?? ''));
        if ($openid === '') {
            throw new AdminResponseException($tradeType === 'miniapp_jsapi' ? '请先完成小程序登录后再支付' : '请先完成公众号授权后再支付');
        }

        return $openid;
    }

    /**
     * 获取并校验微信支付配置.
     *
     * 根据交易类型获取对应支付配置，校验必填字段完整性。
     *
     * @param string $tradeType 交易类型
     * @return array 微信支付配置（app_id、mch_id、private_key、mch_api_v3_key 等）
     * @throws AdminResponseException 配置缺失时抛出
     */
    protected function getWechatPayConfigByTradeType(string $tradeType): array
    {
        $config = $this->buildWechatPayConfig($tradeType);
        if ($config === []) {
            throw new AdminResponseException('微信支付配置未完善');
        }

        foreach (['app_id', 'mch_id', 'mch_serial_no', 'private_key', 'mch_api_v3_key'] as $field) {
            if (trim((string)($config[$field] ?? '')) === '') {
                throw new AdminResponseException('微信支付配置缺少：' . $field);
            }
        }

        return $config;
    }

    /**
     * 构建微信支付完整配置.
     *
     * 合并本地配置文件与数据库动态配置，优先使用数据库中存储的值。
     *
     * @param string $tradeType 交易类型（用于区分小程序/公众号配置来源）
     * @return array 微信支付配置数组
     */
    protected function buildWechatPayConfig(string $tradeType): array
    {
        $config = [];
        $appIdKey = $tradeType === 'miniapp_jsapi' ? ConfigKeyEnum::WECHAT_MINI_APPID : ConfigKeyEnum::WECHAT_APPID;
        $values = app()->make(ConfigService::class)->getMultiConfigVlaue([
            $appIdKey,
            ConfigKeyEnum::WECHAT_PAY_MCH_ID,
            ConfigKeyEnum::WECHAT_PAY_MCH_SERIAL_NO,
            ConfigKeyEnum::WECHAT_PAY_API_V3_KEY,
            ConfigKeyEnum::WECHAT_PAY_CERT_PATH,
            ConfigKeyEnum::WECHAT_PAY_KEY_PATH,
            ConfigKeyEnum::WECHAT_PAY_PRIVATE_KEY,
            ConfigKeyEnum::WECHAT_PAY_PUBLIC_KEY_ID,
            ConfigKeyEnum::WECHAT_PAY_PUBLIC_KEY,
            ConfigKeyEnum::WECHAT_PAY_RECHARGE_NOTIFY_URL,
        ], false);

        $mapping = [
            $appIdKey => 'app_id',
            ConfigKeyEnum::WECHAT_PAY_MCH_ID => 'mch_id',
            ConfigKeyEnum::WECHAT_PAY_PUBLIC_KEY_ID => 'public_key_id',
            ConfigKeyEnum::WECHAT_PAY_PUBLIC_KEY => 'public_key',
            ConfigKeyEnum::WECHAT_PAY_MCH_SERIAL_NO => 'mch_serial_no',
        ];
        foreach ($mapping as $sourceKey => $targetKey) {
            $value = trim((string)($values[$sourceKey] ?? ''));
            if ($value !== '') {
                $config[$targetKey] = $value;
            }
        }

        $keyPath = trim((string)($values[ConfigKeyEnum::WECHAT_PAY_KEY_PATH] ?? ''));
        if ($keyPath !== '') {
            $config['private_key'] = $keyPath;
            $config['ssl_key'] = $keyPath;
        } else {
            $legacyPrivateKey = trim((string)($values[ConfigKeyEnum::WECHAT_PAY_PRIVATE_KEY] ?? ''));
            if ($legacyPrivateKey !== '') {
                $config['private_key'] = $legacyPrivateKey;
            }
        }

        $certPath = trim((string)($values[ConfigKeyEnum::WECHAT_PAY_CERT_PATH] ?? ''));
        if ($certPath !== '') {
            $config['cert'] = $certPath;
        }

        $apiV3Key = trim((string)($values[ConfigKeyEnum::WECHAT_PAY_API_V3_KEY] ?? ''));
        if ($apiV3Key !== '') {
            $config['mch_api_v3_key'] = $apiV3Key;
            $config['secret_key'] = $apiV3Key;
        } elseif (trim((string)($config['mch_api_v3_key'] ?? '')) !== '' && trim((string)($config['secret_key'] ?? '')) === '') {
            $config['secret_key'] = (string)$config['mch_api_v3_key'];
        }

        $notifyUrl = trim((string)($values[ConfigKeyEnum::WECHAT_PAY_RECHARGE_NOTIFY_URL] ?? ''));
        if ($notifyUrl !== '') {
            $config['notify_url'] = $notifyUrl;
        }

        return $config;
    }

    /**
     * 解密微信支付回调通知.
     *
     * 由于可能同时配置了公众号和小程序支付，需要依次尝试两组配置进行验签解密，
     * 成功后返回解密数据及匹配的交易类型。
     *
     * @param string $rawBody 微信回调原始请求体
     * @param array  $headers 回调请求头
     * @return array{0: array, 1: string} [解密后的数据, 匹配的交易类型]
     * @throws AdminResponseException 所有配置均验签失败时抛出
     */
    protected function decryptWechatNotify(string $rawBody, array $headers): array
    {
        $errors = [];
        $configMap = [
            'oa_jsapi' => $this->buildWechatPayConfig('oa_jsapi'),
            'miniapp_jsapi' => $this->buildWechatPayConfig('miniapp_jsapi'),
        ];

        foreach ($configMap as $type => $config) {
            if ($config === []) {
                continue;
            }

            try {
                return [$this->makeWechatPay($config)->notify($rawBody, $headers), $type];
            } catch (Throwable $throwable) {
                $errors[] = $throwable->getMessage();
            }
        }

        throw new AdminResponseException($errors !== [] ? implode('；', $errors) : '微信回调验签失败');
    }

    /**
     * 实例化微信支付服务.
     *
     * @param array $config 微信支付配置
     * @return PaymentInterface
     */
    protected function makeWechatPay(array $config): PaymentInterface
    {
        return app()->make(PaymentService::class)->channel(PaymentService::CHANNEL_WECHAT, $config);
    }

    /**
     * 获取并校验支付宝支付配置.
     *
     * 从数据库动态配置中读取支付宝 app_id、公私钥等参数，校验必填字段。
     *
     * @return array 支付宝配置（app_id、private_key、public_key、notify_url）
     * @throws AdminResponseException 必填配置缺失时抛出
     */
    protected function getAlipayConfig(): array
    {
        $values = app()->make(ConfigService::class)->getMultiConfigVlaue(
            array_column(PaymentConfigKeyEnum::ALIPAY_CONFIG_FIELDS, 'key'),
            false
        );
        $notifyUrl = trim((string)($values['alipay_recharge_notify_url'] ?? ''));
        $config = [
            'app_id' => trim((string)($values['alipay_app_id'] ?? '')),
            'private_key' => trim((string)($values['alipay_private_key'] ?? '')),
            'public_key' => trim((string)($values['alipay_public_key'] ?? '')),
            'notify_url' => $notifyUrl,
        ];

        foreach (['app_id', 'private_key', 'public_key'] as $field) {
            if ($config[$field] === '') {
                throw new AdminResponseException('支付宝支付配置缺少：' . $field);
            }
        }

        return $config;
    }

    /**
     * 实例化支付宝支付服务.
     *
     * @return PaymentInterface
     */
    protected function makeAlipayPay(?array $config = null): PaymentInterface
    {
        $config ??= $this->getAlipayConfig();

        return app()->make(PaymentService::class)->channel(PaymentService::CHANNEL_ALIPAY, $config);
    }

    /**
     * 将相对路径拼接为当前域名的完整 URL.
     *
     * @param string $path 相对路径（如 /api/mobile/user/recharge/wechatNotify）
     * @return string 完整 URL
     */
    protected function buildAbsoluteUrl(string $path): string
    {
        $domain = trim((string)db_config(ConfigKeyEnum::WEB_HOST, ''));
        if ($domain === '') {
            throw new AdminResponseException('网站根域名未配置，无法生成支付回调地址');
        }

        return rtrim($domain, '/') . '/' . ltrim($path, '/');
    }

    /**
     * 解析支付回调地址，优先使用数据库配置。
     *
     * @param string $configuredUrl 数据库配置的回调地址。
     * @param string $defaultPath   默认回调路径。
     * @return string 完整回调地址。
     */
    protected function resolveNotifyUrl(string $configuredUrl, string $defaultPath): string
    {
        $configuredUrl = trim($configuredUrl);
        if ($configuredUrl === '') {
            return $this->buildAbsoluteUrl($defaultPath);
        }

        if (preg_match('/^https?:\/\//i', $configuredUrl)) {
            return $configuredUrl;
        }

        return $this->buildAbsoluteUrl($configuredUrl);
    }

    /**
     * 元转分.
     *
     * 将人民币金额（元）转换为微信支付所需的分（整数）。
     *
     * @param float $amount 金额（元）
     * @return int 金额（分）
     */
    protected function yuanToFen(float $amount): int
    {
        return (int)round($amount * 100);
    }

    /**
     * 解析微信支付成功时间.
     *
     * 将微信回调中的 ISO 8601 时间格式转为数据库存储格式，解析失败时使用当前时间。
     *
     * @param string $successTime 微信回调的 success_time 字段
     * @return string Y-m-d H:i:s 格式的时间字符串
     */
    protected function resolveWechatSuccessTime(string $successTime): string
    {
        if ($successTime === '') {
            return date('Y-m-d H:i:s');
        }

        $timestamp = strtotime($successTime);

        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : date('Y-m-d H:i:s');
    }

    /**
     * 解析支付宝支付成功时间.
     *
     * 将支付宝回调中的时间格式转为数据库存储格式，解析失败时使用当前时间。
     *
     * @param string $successTime 支付宝回调的 gmt_payment 字段
     * @return string Y-m-d H:i:s 格式的时间字符串
     */
    protected function resolveAlipaySuccessTime(string $successTime): string
    {
        if ($successTime === '') {
            return date('Y-m-d H:i:s');
        }

        $timestamp = strtotime($successTime);

        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : date('Y-m-d H:i:s');
    }
}
