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

namespace app\common\service\live;

use app\common\exception\AdminResponseException;
use app\common\model\live\LiveGiftRecord;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\finance\FinanceService;
use app\common\service\merchant\MerchantService;
use app\common\service\user\UserAssetService;
use app\common\service\user\UserService;
use Rabbit\service\websocket\PushNotification;

/**
 * 直播间礼物赠送流水服务
 */
class LiveGiftRecordService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    protected function setModel(): string
    {
        return LiveGiftRecord::class;
    }

    /**
     * 获取直播间礼物统计。
     *
     * @return array{reward_count: int, reward_amount: string}
     */
    public function getRoomGiftSummary(int $roomId): array
    {
        return [
            'reward_count' => (int)$this->getModel()
                ->where('room_id', $roomId)
                ->count(),
            'reward_amount' => (string)$this->getModel()
                ->where('room_id', $roomId)
                ->sum('exchange_amount'),
        ];
    }

    /**
     * 获取管理后台直播打赏流水分页。
     *
     * @param array $params 查询参数，支持 live_room_id、nickname、gift_name、start_date、end_date、page、limit。
     * @return array{list: array, total: int, page: int, limit: int, live_room_id: int, summary: array}
     */
    public function getAdminRewardPage(array $params): array
    {
        $page = max(1, (int)($params['page'] ?? 1));
        $limit = max(1, min(100, (int)($params['limit'] ?? 20)));
        $liveRoomId = (int)($params['live_room_id'] ?? $params['room_id'] ?? 0);

        $total = (int)$this->buildAdminRewardQuery($params, $liveRoomId)->count();
        $totalCoins = (string)$this->buildAdminRewardQuery($params, $liveRoomId)->sum('total_amount');
        $totalExchangeAmount = (string)$this->buildAdminRewardQuery($params, $liveRoomId)->sum('exchange_amount');

        $list = $this->buildAdminRewardQuery($params, $liveRoomId)
            ->order('send_time', 'desc')
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        $list = $this->appendAdminRewardExtra($list);

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'live_room_id' => $liveRoomId,
            'summary' => [
                'total_count' => $total,
                'total_coins' => (int)$totalCoins,
                'total_amount' => $this->formatMoney($totalExchangeAmount),
            ],
        ];
    }

    /**
     * 构建后台打赏流水查询。
     */
    protected function buildAdminRewardQuery(array $params, int $liveRoomId): mixed
    {
        $query = $this->getModel();

        if ($liveRoomId > 0) {
            $query = $query->where('room_id', $liveRoomId);
        }

        $giftName = trim((string)($params['gift_name'] ?? ''));
        if ($giftName !== '') {
            $query = $query->where('gift_name', 'like', '%' . $giftName . '%');
        }

        $nickname = trim((string)($params['nickname'] ?? ''));
        if ($nickname !== '') {
            $userIds = app()->make(UserService::class)
                ->getModel()
                ->where('nickname', 'like', '%' . $nickname . '%')
                ->column('uid');
            if ($userIds) {
                $query = $query->whereIn('user_id', array_map('intval', $userIds));
            } else {
                $query = $query->where('user_id', 0);
            }
        }

        $startDate = trim((string)($params['start_date'] ?? ''));
        $endDate = trim((string)($params['end_date'] ?? ''));
        if ($startDate !== '' && $endDate !== '') {
            $query = $query->whereBetweenTime('send_time', $startDate . ' 00:00:00', $endDate . ' 23:59:59');
        } elseif ($startDate !== '') {
            $query = $query->whereTime('send_time', '>=', $startDate . ' 00:00:00');
        } elseif ($endDate !== '') {
            $query = $query->whereTime('send_time', '<=', $endDate . ' 23:59:59');
        }

        return $query;
    }

    /**
     * 补齐后台展示所需的用户、直播间和兼容字段。
     */
    protected function appendAdminRewardExtra(array $list): array
    {
        if (!$list) {
            return [];
        }

        $userIds = array_values(array_unique(array_filter(array_map('intval', array_column($list, 'user_id')))));
        $roomIds = array_values(array_unique(array_filter(array_map('intval', array_column($list, 'room_id')))));

        $userMap = [];
        if ($userIds) {
            $users = app()->make(UserService::class)
                ->getModel()
                ->whereIn('uid', $userIds)
                ->field(['uid', 'nickname', 'avatar'])
                ->select()
                ->toArray();
            foreach ($users as $user) {
                $userMap[(int)$user['uid']] = $user;
            }
        }

        $roomMap = [];
        if ($roomIds) {
            $rooms = app()->make(LiveRoomService::class)
                ->getModel()
                ->whereIn('id', $roomIds)
                ->field(['id', 'title'])
                ->select()
                ->toArray();
            foreach ($rooms as $room) {
                $roomMap[(int)$room['id']] = $room;
            }
        }

        foreach ($list as &$item) {
            $userId = (int)($item['user_id'] ?? 0);
            $roomId = (int)($item['room_id'] ?? 0);
            $user = $userMap[$userId] ?? [];
            $room = $roomMap[$roomId] ?? [];
            $giftPrice = $this->formatMoney((string)($item['gift_price'] ?? '0'));
            $totalCoins = $this->formatMoney((string)($item['total_amount'] ?? '0'));
            $exchangeAmount = $this->formatMoney((string)($item['exchange_amount'] ?? '0'));
            $sendTime = (string)($item['send_time'] ?? '');

            $item['room_title'] = (string)($room['title'] ?? '');
            $item['nickname'] = (string)($user['nickname'] ?? '');
            $item['avatar'] = (string)($user['avatar'] ?? '');
            $item['gift_price'] = $giftPrice;
            $item['total_coins'] = $totalCoins;
            $item['total_amount'] = $exchangeAmount;
            $item['price'] = $giftPrice;
            $item['exchange_amount'] = $exchangeAmount;
            $item['create_time'] = (string)($item['create_time'] ?? $sendTime);
            $item['send_time'] = $sendTime;
        }
        unset($item);

        return $list;
    }

    /**
     * 格式化金额字段。
     */
    protected function formatMoney(string|int|float|null $value): string
    {
        return number_format((float)$value, 2, '.', '');
    }

    /**
     * 移动端赠送直播间礼物。
     */
    public function sendGift(int $roomId, int $userId, int $giftId, int $quantity = 1): array
    {
        if ($roomId <= 0) {
            throw new AdminResponseException('直播间 ID 不能为空');
        }
        if ($userId <= 0) {
            throw new AdminResponseException('请先登录后赠送礼物');
        }
        if ($giftId <= 0) {
            throw new AdminResponseException('请选择礼物');
        }

        $quantity = max(1, min(999, $quantity));
        $room = app()->make(LiveRoomService::class)->get(
            ['id' => $roomId],
            ['id', 'title', 'status', 'merchant_id', 'live_anchor_id']
        );
        if (!$room) {
            throw new AdminResponseException('直播间不存在');
        }
        if ((int)($room['status'] ?? 0) !== LiveRoomService::STATUS_LIVING) {
            throw new AdminResponseException('直播未开始或已结束，暂不可赠送礼物');
        }
        $roomData = is_array($room) ? $room : $room->toArray();

        $gift = app()->make(LiveGiftService::class)->get(['id' => $giftId, 'status' => 1], ['id', 'name', 'icon', 'effect_url', 'price', 'exchange_amount', 'score']);
        if (!$gift) {
            throw new AdminResponseException('礼物不存在或已下架');
        }

        $priceValue = (float)($gift['price'] ?? 0);
        if ($priceValue <= 0 || floor($priceValue) !== $priceValue) {
            throw new AdminResponseException('礼物金币价格配置不正确');
        }
        $price = (string)(int)$priceValue;
        $totalAmount = bcmul($price, (string)$quantity, 0);
        $exchangeAmount = bcmul((string)($gift['exchange_amount'] ?? '0'), (string)$quantity, 2);
        $split = $this->calculateGiftSplit($roomData, $exchangeAmount);

        return $this->transaction(function () use ($roomId, $userId, $gift, $quantity, $price, $totalAmount, $exchangeAmount, $split) {
            $sendTime = date('Y-m-d H:i:s');
            try {
                app()->make(UserAssetService::class)->changeAsset(
                    $userId,
                    UserAssetService::TYPE_COINS,
                    $totalAmount,
                    'out',
                    '直播间赠送礼物扣金币',
                    [
                        'source_type' => 'live_gift',
                        'source_desc' => '直播间ID：' . $roomId . '，礼物：' . ($gift['name'] ?? '') . '，扣金币：' . $totalAmount,
                    ]
                );
            } catch (AdminResponseException $exception) {
                if (str_contains($exception->getMessage(), 'coins')) {
                    throw new AdminResponseException('金币不足，请先充值');
                }
                throw $exception;
            }

            $record = $this->create([
                'room_id' => $roomId,
                'user_id' => $userId,
                'merchant_id' => $split['merchant_id'],
                'live_anchor_id' => $split['live_anchor_id'],
                'gift_id' => (int)$gift['id'],
                'gift_name' => (string)($gift['name'] ?? ''),
                'gift_icon' => (string)($gift['icon'] ?? ''),
                'gift_price' => $price,
                'quantity' => $quantity,
                'total_amount' => $totalAmount,
                'exchange_amount' => $exchangeAmount,
                'platform_commission_rate' => $split['platform_commission_rate'],
                'merchant_commission_rate' => $split['merchant_commission_rate'],
                'platform_amount' => $split['platform_amount'],
                'merchant_amount' => $split['merchant_amount'],
                'anchor_amount' => $split['anchor_amount'],
                'score' => (int)($gift['score'] ?? 0),
                'send_time' => $sendTime,
            ]);

            $this->createGiftFinanceRecords($record->toArray(), $userId, $split);

            $levelResult = app()->make(LiveRoomUserLevelService::class)
                ->handleGiftContribution($userId, $roomId, (float)$price, $quantity);
            $user = app()->make(UserService::class)->get(['uid' => $userId], ['nickname', 'avatar']);
            $asset = app()->make(UserAssetService::class)->getInfo($userId);
            $coins = (int)($asset['coins'] ?? 0);

            $data = [
                'id' => (int)($record['id'] ?? 0),
                'room_id' => $roomId,
                'user_id' => $userId,
                'nickname' => (string)($user['nickname'] ?? ''),
                'avatar' => (string)($user['avatar'] ?? ''),
                'gift_id' => (int)$gift['id'],
                'gift_name' => (string)($gift['name'] ?? ''),
                'gift_icon' => (string)($gift['icon'] ?? ''),
                'gift_effect_url' => (string)($gift['effect_url'] ?? ''),
                'gift_price' => (string)$price,
                'quantity' => $quantity,
                'total_amount' => (string)$totalAmount,
                'exchange_amount' => (string)$exchangeAmount,
                'merchant_id' => $split['merchant_id'],
                'live_anchor_id' => $split['live_anchor_id'],
                'platform_amount' => $split['platform_amount'],
                'merchant_amount' => $split['merchant_amount'],
                'anchor_amount' => $split['anchor_amount'],
                'send_time' => $sendTime,
                'timestamp' => time(),
                'balance' => (float)($asset['balance'] ?? 0),
                'coins' => $coins,
                'coin_balance' => $coins,
                'level' => $levelResult,
            ];

            PushNotification::broadcastGiftMessage($roomId, $data);
            app()->make(LiveRoomService::class)->clearMobileRoomCaches($roomId);

            return $data;
        });
    }

    /**
     * 计算商户直播间礼物收益分账。
     *
     * @return array{
     *     merchant_id:int,
     *     live_anchor_id:int,
     *     platform_commission_rate:string,
     *     merchant_commission_rate:string,
     *     platform_amount:string,
     *     merchant_amount:string,
     *     anchor_amount:string
     * }
     */
    protected function calculateGiftSplit(array $room, string $exchangeAmount): array
    {
        $merchantId = (int)($room['merchant_id'] ?? 0);
        $liveAnchorId = (int)($room['live_anchor_id'] ?? 0);
        $totalAmount = $this->normalizeMoney($exchangeAmount);
        $default = [
            'merchant_id' => $merchantId,
            'live_anchor_id' => $liveAnchorId,
            'platform_commission_rate' => '0.00',
            'merchant_commission_rate' => '0.00',
            'platform_amount' => '0.00',
            'merchant_amount' => '0.00',
            'anchor_amount' => '0.00',
        ];

        if ($liveAnchorId <= 0 || $this->moneyCompare($totalAmount, '0') <= 0) {
            return $default;
        }

        $platformRate = $merchantId > 0
            ? $this->getMerchantCommissionRate($merchantId)
            : $this->getAnchorCommissionRate($liveAnchorId);
        $merchantRate = $merchantId > 0 ? $this->getAnchorCommissionRate($liveAnchorId) : 0.0;
        $platformAmount = $this->moneyPercent($totalAmount, $platformRate);
        $remainingAfterPlatform = $this->moneyMax('0', $this->moneySub($totalAmount, $platformAmount));
        $merchantAmount = $this->moneyMin(
            $remainingAfterPlatform,
            $this->moneyPercent($totalAmount, $merchantRate)
        );
        $anchorAmount = $this->moneyMax('0', $this->moneySub($this->moneySub($totalAmount, $platformAmount), $merchantAmount));

        return [
            'merchant_id' => $merchantId,
            'live_anchor_id' => $liveAnchorId,
            'platform_commission_rate' => $this->formatMoney($platformRate),
            'merchant_commission_rate' => $this->formatMoney($merchantRate),
            'platform_amount' => $this->formatMoney($platformAmount),
            'merchant_amount' => $this->formatMoney($merchantAmount),
            'anchor_amount' => $this->formatMoney($anchorAmount),
        ];
    }

    /**
     * 获取商户平台抽成比例。
     */
    protected function getMerchantCommissionRate(int $merchantId): float
    {
        if ($merchantId <= 0) {
            return 0.0;
        }

        $platformRateMap = app()->make(MerchantService::class)->getCommissionRateMapByIds([$merchantId]);

        return $this->normalizeRate($platformRateMap[$merchantId] ?? 0);
    }

    /**
     * 获取主播商户抽成比例。
     */
    protected function getAnchorCommissionRate(int $liveAnchorId): float
    {
        if ($liveAnchorId <= 0) {
            return 0.0;
        }

        $anchor = app()->make(LiveAnchorService::class)->get($liveAnchorId, ['id', 'commission_rate']);

        return $this->normalizeRate((float)($anchor['commission_rate'] ?? 0));
    }

    /**
     * 写入直播间礼物分账财务流水。
     */
    protected function createGiftFinanceRecords(array $record, int $userId, array $split): void
    {
        $merchantId = (int)($split['merchant_id'] ?? 0);
        $exchangeAmount = $this->normalizeMoney((string)($record['exchange_amount'] ?? '0'));
        $roomId = (int)($record['room_id'] ?? 0);
        $recordId = (int)($record['id'] ?? 0);
        $liveAnchorId = (int)($split['live_anchor_id'] ?? 0);
        if (
            $this->moneyCompare($exchangeAmount, '0') <= 0
            || $roomId <= 0
            || $recordId <= 0
            || $liveAnchorId <= 0
        ) {
            return;
        }

        $financeService = app()->make(FinanceService::class);
        $baseData = [
            'user_id' => $userId,
            'live_room_id' => $roomId,
            'live_gift_record_id' => $recordId,
            'live_anchor_id' => $liveAnchorId,
            'change_type' => 'in',
            'fund_status' => FinanceService::FUND_STATUS_AVAILABLE,
        ];
        $giftName = (string)($record['gift_name'] ?? '');
        $summary = $financeService->createRecord(array_merge($baseData, [
            'merchant_id' => 0,
            'type' => FinanceService::TYPE_LIVE_GIFT_SUMMARY,
            'amount' => $exchangeAmount,
            'remark' => '直播礼物收益聚合：直播间ID ' . $roomId . '，礼物：' . $giftName,
        ]));
        $financePid = (int)($summary['id'] ?? 0);

        $this->createGiftFinanceDetail(
            $financeService,
            $baseData,
            $financePid,
            0,
            FinanceService::TYPE_LIVE_GIFT_PLATFORM,
            (string)($split['platform_amount'] ?? '0'),
            '直播礼物平台抽成：直播间ID ' . $roomId . '，礼物：' . $giftName
        );
        $this->createGiftFinanceDetail(
            $financeService,
            $baseData,
            $financePid,
            $merchantId,
            FinanceService::TYPE_LIVE_GIFT_MERCHANT,
            (string)($split['merchant_amount'] ?? '0'),
            '直播礼物商户抽成：直播间ID ' . $roomId . '，礼物：' . $giftName
        );
        $this->createGiftFinanceDetail(
            $financeService,
            $baseData,
            $financePid,
            0,
            FinanceService::TYPE_LIVE_GIFT_ANCHOR,
            (string)($split['anchor_amount'] ?? '0'),
            '直播礼物主播所得：直播间ID ' . $roomId . '，礼物：' . $giftName
        );
    }

    /**
     * 写入单条礼物分账明细流水。
     */
    protected function createGiftFinanceDetail(
        FinanceService $financeService,
        array $baseData,
        int $financePid,
        int $merchantId,
        string $type,
        string $amount,
        string $remark
    ): void {
        $amount = $this->normalizeMoney($amount);
        if ($this->moneyCompare($amount, '0') <= 0) {
            return;
        }

        $financeService->createRecord(array_merge($baseData, [
            'pid' => $financePid,
            'merchant_id' => $merchantId,
            'type' => $type,
            'amount' => $amount,
            'remark' => $remark,
        ]));
    }

    /**
     * 规范化比例。
     */
    protected function normalizeRate(float|string $rate): float
    {
        return (float)bcadd((string)max(0, min(100, (float)$rate)), '0', 2);
    }

    /**
     * 规范化金额。
     */
    protected function normalizeMoney(float|string $amount): string
    {
        return bcadd((string)$amount, '0', 2);
    }

    /**
     * 高精度百分比金额。
     */
    protected function moneyPercent(float|string $amount, float|string $rate): string
    {
        return bcdiv(bcmul((string)$amount, (string)$rate, 4), '100', 2);
    }

    /**
     * 高精度金额相减。
     */
    protected function moneySub(float|string $left, float|string $right): string
    {
        return bcsub((string)$left, (string)$right, 2);
    }

    /**
     * 高精度金额比较。
     */
    protected function moneyCompare(float|string $left, float|string $right): int
    {
        return bccomp((string)$left, (string)$right, 2);
    }

    /**
     * 高精度金额最小值。
     */
    protected function moneyMin(float|string $left, float|string $right): string
    {
        return $this->moneyCompare($left, $right) <= 0
            ? $this->normalizeMoney($left)
            : $this->normalizeMoney($right);
    }

    /**
     * 高精度金额最大值。
     */
    protected function moneyMax(float|string $left, float|string $right): string
    {
        return $this->moneyCompare($left, $right) >= 0
            ? $this->normalizeMoney($left)
            : $this->normalizeMoney($right);
    }
}
