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

namespace app\common\service\order;

use app\common\enum\ConfigKeyEnum;
use app\common\enum\ContentTypeEnum;
use app\common\enum\OrderConfigKeyEnum;
use app\common\enum\PayTypeEnum;
use app\common\exception\AdminResponseException;
use app\common\model\order\Order;
use app\common\model\order\OrderGoods;
use app\common\model\store\StoreCouponUser;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\content\SpecialService;
use app\common\service\finance\FinanceService;
use app\common\service\merchant\MerchantService;
use app\common\service\message\BackendMessageEventService;
use app\common\service\message\MessagePushDefinitionService;
use app\common\service\message\MessagePushEventService;
use app\common\service\plugin\PluginOrderLifecycleService;
use app\common\service\plugin\PluginCheckoutCapabilityService;
use app\common\service\setting\ExpressCompanyService;
use app\common\service\store\StoreCouponIssueService;
use app\common\service\store\StoreCouponUserService;
use app\common\service\store\StoreRefundProductService;
use app\common\service\user\UserAssetService;
use app\common\service\user\UserAssetLogService;
use app\common\service\user\UserMemberService;
use app\common\service\user\UserService;
use Rabbit\service\express\ExpressException;
use Rabbit\service\express\ExpressService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Model;

/**
 * 订单服务
 */
class OrderService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait {
        resourceUpdate as protected updateOrdinaryResource;
        resourceShowUpdate as protected showOrdinaryResource;
        resourceDelete as protected deleteOrdinaryResource;
    }

    /**
     * 指定当前服务管理的持久化模型。
     *
     * @return string 当前服务对应的模型完整类名。
     */
    protected function setModel(): string
    {
        return Order::class;
    }

    /**
     * 更新普通订单；活动订单的修改必须通过受控业务操作。
     *
     * @param int|array $id 订单编号或资源筛选条件。
     * @param array $data 待保存的业务字段。
     * @return bool 普通订单资源更新是否成功；活动订单会被拒绝。
     */
    public function resourceUpdate(int|array $id, array $data)
    {
        return $this->transaction(function () use ($id, $data) {
            $this->assertOrdinaryResource($id);
            return $this->updateOrdinaryResource($id, $data);
        });
    }

    /**
     * 修改普通资源状态，禁止绕过活动订单的库存预留及支付锁。
     *
     * @param int|array $id 订单编号或资源筛选条件。
     * @param array $data 待保存的业务字段。
     * @return bool 普通订单资源状态变更是否成功；活动订单会被拒绝。
     */
    public function resourceShowUpdate(int|array $id, array $data)
    {
        return $this->transaction(function () use ($id, $data) {
            $this->assertOrdinaryResource($id);
            return $this->showOrdinaryResource($id, $data);
        });
    }

    /**
     * 保留活动订单，作为永久的交易及库存凭证。
     *
     * @param int $id 订单编号或资源筛选条件。
     * @return int 实际删除的普通订单记录数量；活动订单会被拒绝。
     */
    public function resourceDelete(int $id)
    {
        return $this->transaction(function () use ($id) {
            $this->assertOrdinaryResource($id);
            return $this->deleteOrdinaryResource($id);
        });
    }

    /**
     * 让旧版状态按钮沿用相同的活动订单保护逻辑。
     *
     * @param int $id 订单编号或资源筛选条件。
     * @param int $status 当前业务状态或列表状态筛选值。
     * @return bool 受控资源状态修改是否成功。
     */
    public function updateStatus(int $id, int $status): bool
    {
        return $this->resourceShowUpdate($id, ['status' => $status]);
    }

    /**
     * 按固定顺序锁定所有选中订单，禁止直接编辑或删除活动订单。
     *
     * @param int|array $id 订单编号或资源筛选条件。
     * @return void 无返回值；选中范围含活动订单时整体拒绝直接资源变更。
     */
    protected function assertOrdinaryResource(int|array $id): void
    {
        $orders = $this->getModel()->where($this->handleData($id))->order('id asc')->lock(true)->select();
        foreach ($orders as $order) {
            if (!empty($order['pay_expire_time'])) {
                throw new AdminResponseException('活动订单不可直接编辑、改状态或删除，请使用支付、取消、退款等业务操作');
            }
        }
    }

    /**
     * 获取订单列表
     *
     * @param array $where 需要操作的记录编号或筛选条件。
     * @param int $page 当前列表页码。
     * @param int $limit 每页或当前批次最多返回的记录数。
     * @return array 按当前条件加载的购物车分组或订单分页数据。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getList(array $where = [], int $page = 1, int $limit = 20): array
    {
        return $this->getDefaultList($where, ['*'], [], 'create_time desc')->toArray();
    }

    /**
     * 获取平台订单资源列表
     *
     * @param array $params 已校验的列表筛选或业务操作参数。
     * @param array|string $fields 需要返回的订单资源字段列表。
     * @param array $with 需要预加载的关联资源声明。
     * @param string|array|null $sort 当前列表已校验的排序规则。
     * @return array 按平台条件筛选并补齐商品、用户及店铺展示资料的订单分页。
     */
    public function getResourceList(array $params = [], array|string $fields = [], array $with = [], string|array|null $sort = null): array
    {
        if (!isset($params['order_role']) || $params['order_role'] === '') {
            $params['order_role'] = 'business';
        }

        $result = $this->getDefaultList($params, $fields ?: ['*'], $with, $sort ?: ['create_time' => 'desc'])->toArray();
        $orderList = $result['list'] ?? [];
        if ($orderList === []) {
            return $result;
        }

        $orderIds = array_values(array_unique(array_filter(array_map('intval', array_column($orderList, 'id')))));
        $userIds = array_values(array_unique(array_filter(array_map('intval', array_column($orderList, 'user_id')))));
        $merchantIds = array_values(array_unique(array_filter(array_map('intval', array_column($orderList, 'merchant_id')))));
        $goodsMap = $this->getOrderGoodsMap($orderIds);
        $userMap = $this->getUserMap($userIds);
        $merchantMap = $this->getMerchantMap($merchantIds);

        foreach ($orderList as $index => $order) {
            $orderId = (int)($order['id'] ?? 0);
            $userId = (int)($order['user_id'] ?? 0);
            $merchantId = (int)($order['merchant_id'] ?? 0);
            $orderList[$index] = $this->buildMerchantOrderListItem(
                $order,
                $goodsMap[$orderId] ?? [],
                $userMap[$userId] ?? [],
                $merchantMap[$merchantId] ?? []
            );
        }

        $result['list'] = $orderList;

        return $result;
    }

    /**
     * 获取平台订单资源详情
     *
     * @param int|array $id 需要查询的平台业务订单编号。
     * @return array 平台订单详情及商品、用户、商户快照。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function resourceRead(int|array $id): array
    {
        $order = $this->get($id);
        if (!$order) {
            throw new ModelNotFoundException('查询的数据不存在');
        }

        $orderData = $order->toArray();
        $orderId = (int)($orderData['id'] ?? 0);
        $userId = (int)($orderData['user_id'] ?? 0);
        $merchantId = (int)($orderData['merchant_id'] ?? 0);
        $goodsList = app()->make(OrderGoodsService::class)->getByOrderId($orderId);
        $userMap = $this->getUserMap([$userId]);
        $merchantMap = $this->getMerchantMap([$merchantId]);

        return $this->buildMerchantOrderDetailItem(
            $orderData,
            $goodsList,
            $userMap[$userId] ?? [],
            $merchantMap[$merchantId] ?? []
        );
    }

    /**
     * 批量获取后台订单展示数据映射。
     *
     * 退款列表以退款记录为主表分页，但页面仍需要订单商品、用户和商户快照；
     * 这里集中复用订单列表输出结构，避免退款服务重复组装跨资源数据。
     *
     * @param array $orderIds 订单标识列表。
     * @param array $orderSns 订单号列表。
     * @return array{ids:array<int,array>,order_sns:array<string,array>} 分别按内部编号和业务单号建立的后台订单展示映射。
     */
    public function getAdminOrderDisplayMapByRefs(array $orderIds = [], array $orderSns = []): array
    {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
        $orderSns = array_values(array_unique(array_filter(array_map('strval', $orderSns))));
        if ($orderIds === [] && $orderSns === []) {
            return [
                'ids' => [],
                'order_sns' => [],
            ];
        }

        $query = $this->getModel()->where('id', '>', 0);
        $query->where(function ($subQuery) use ($orderIds, $orderSns): void {
            if ($orderIds !== []) {
                $subQuery->whereIn('id', $orderIds);
                if ($orderSns !== []) {
                    $subQuery->whereOr('order_sn', 'in', $orderSns);
                }
                return;
            }

            $subQuery->whereIn('order_sn', $orderSns);
        });

        $orderList = $query->select()->toArray();
        if ($orderList === []) {
            return [
                'ids' => [],
                'order_sns' => [],
            ];
        }

        $listOrderIds = array_values(array_unique(array_filter(array_map('intval', array_column($orderList, 'id')))));
        $userIds = array_values(array_unique(array_filter(array_map('intval', array_column($orderList, 'user_id')))));
        $merchantIds = array_values(array_unique(array_filter(array_map('intval', array_column($orderList, 'merchant_id')))));
        $goodsMap = $this->getOrderGoodsMap($listOrderIds);
        $userMap = $this->getUserMap($userIds);
        $merchantMap = $this->getMerchantMap($merchantIds);

        $result = [
            'ids' => [],
            'order_sns' => [],
        ];
        foreach ($orderList as $order) {
            $orderId = (int)($order['id'] ?? 0);
            $userId = (int)($order['user_id'] ?? 0);
            $merchantId = (int)($order['merchant_id'] ?? 0);
            $payload = $this->buildMerchantOrderListItem(
                $order,
                $goodsMap[$orderId] ?? [],
                $userMap[$userId] ?? [],
                $merchantMap[$merchantId] ?? []
            );

            if ($orderId > 0) {
                $result['ids'][$orderId] = $payload;
            }

            $orderSn = (string)($order['order_sn'] ?? '');
            if ($orderSn !== '') {
                $result['order_sns'][$orderSn] = $payload;
            }
        }

        return $result;
    }

    /**
     * 按后台退款列表的订单维度筛选条件获取订单引用。
     *
     * @param array $params 筛选参数，支持 user_keyword、goods_name、order_type、merchant_id。
     * @return array{ids:array<int>,order_sns:array<string>}|null 没有订单维度筛选时返回 null。
     */
    public function getAdminRefundOrderReferences(array $params = []): ?array
    {
        $userKeyword = trim((string)($params['user_keyword'] ?? ''));
        $goodsName = trim((string)($params['goods_name'] ?? ''));
        $orderType = trim((string)($params['order_type'] ?? ''));
        $merchantId = max(0, (int)($params['merchant_id'] ?? 0));
        if ($userKeyword === '' && $goodsName === '' && $orderType === '' && $merchantId <= 0) {
            return null;
        }

        $query = $this->getModel()->where('order_role', '<>', Order::ROLE_MAIN);
        if ($orderType !== '') {
            $query->where('order_type', (int)$orderType);
        }
        if ($merchantId > 0) {
            $query->where('merchant_id', $merchantId);
        }
        if ($userKeyword !== '') {
            $this->applyMerchantUserKeywordFilter($query, $userKeyword);
        }
        if ($goodsName !== '') {
            $orderIds = $this->findAdminOrderIdsByGoodsName($goodsName, $merchantId);
            if ($orderIds === []) {
                return [
                    'ids' => [],
                    'order_sns' => [],
                ];
            }
            $query->whereIn('id', $orderIds);
        }

        $orders = $query->field(['id', 'order_sn'])->select()->toArray();

        return [
            'ids' => array_values(array_unique(array_filter(array_map('intval', array_column($orders, 'id'))))),
            'order_sns' => array_values(array_unique(array_filter(array_map('strval', array_column($orders, 'order_sn'))))),
        ];
    }

    /**
     * 获取订单详情
     *
     * @param int $id 订单编号或资源筛选条件。
     * @return ?array 指定订单及商品成交明细；订单不存在时为空。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getInfo(int $id): ?array
    {
        $order = $this->get($id);
        if (!$order) {
            return null;
        }

        $result = $order->toArray();
        $result['goods'] = app()->make(OrderGoodsService::class)->getByOrderId($id);
        $result['commission_status_text'] = $this->resolveCommissionStatusText((int)($result['commission_status'] ?? 0));

        return $result;
    }

    /**
     * 根据订单号获取订单
     *
     * @param string $orderSn 宿主订单的业务单号。
     * @return ?array 指定业务单号的订单快照；不存在时为空。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getByOrderSn(string $orderSn): ?array
    {
        $order = $this->get(['order_sn' => $orderSn]);

        return $order ? $order->toArray() : null;
    }

    /**
     * 获取用户订单列表
     *
     * @param int $userId 当前业务用户编号。
     * @param array $params 已校验的列表筛选或业务操作参数。
     * @return array 符合当前用户状态筛选的主支付单或业务订单分页。
     */
    public function getUserOrderList(int $userId, array $params = []): array
    {
        [$page, $limit] = $this->getPagesValue();
        $status = trim((string)($params['status'] ?? ''));
        $orderSn = trim((string)($params['order_sn'] ?? ''));
        $merchantId = max(0, (int)($params['merchant_id'] ?? 0));

        $query = $this->getModel()
            ->where('user_id', $userId)
            ->order('id desc');

        if ($merchantId > 0) {
            $query->where('merchant_id', $merchantId);
        }

        if ($status !== '' && $status !== 'all') {
            if (is_numeric($status)) {
                $query->where('status', (int)$status);
                $this->applyUserOrderRoleFilter($query, (int)$status);
            } else {
                match ($status) {
                    'unpaid' => $query->where('status', Order::STATUS_PENDING)
                        ->whereIn('order_role', [Order::ROLE_NORMAL, Order::ROLE_MAIN]),
                    'unsent' => $query->where('status', Order::STATUS_PAID)
                        ->where('order_role', '<>', Order::ROLE_MAIN)
                        ->whereNull('delivery_time'),
                    'undelivered' => $query->where('status', Order::STATUS_PAID)
                        ->where('order_role', '<>', Order::ROLE_MAIN)
                        ->whereNotNull('delivery_time'),
                    'unevaluated' => $query->where('status', Order::STATUS_WAIT_REVIEW)
                        ->where('order_role', '<>', Order::ROLE_MAIN),
                    'complete' => $query->where('status', Order::STATUS_COMPLETED)
                        ->where('order_role', '<>', Order::ROLE_MAIN),
                    'refund' => $query->where('order_role', '<>', Order::ROLE_MAIN)
                        ->whereIn('status', [Order::STATUS_REFUNDING, Order::STATUS_REFUNDED]),
                    default => null,
                };
            }
        } else {
            $query->where(function ($roleQuery) {
                $roleQuery->where('order_role', Order::ROLE_NORMAL)
                    ->whereOr(function ($childQuery) {
                        $childQuery->where('order_role', Order::ROLE_CHILD)
                            ->whereNotIn('status', [Order::STATUS_PENDING, Order::STATUS_CANCELLED]);
                    })
                    ->whereOr(function ($mainQuery) {
                        $mainQuery->where('order_role', Order::ROLE_MAIN)
                            ->where('status', Order::STATUS_PENDING);
                    });
            });
        }

        if ($orderSn !== '') {
            $query->where('order_sn', 'like', '%' . $orderSn . '%');
        }

        $count = (int)(clone $query)->count();
        $orderList = $query->page($page, $limit)->select()->toArray();
        if ($orderList === []) {
            return [
                'list' => [],
                'total' => $count,
                'page' => $page,
                'limit' => $limit,
                'totalPage' => $limit > 0 ? (int)ceil($count / $limit) : 0,
            ];
        }

        $orderIds = array_values(array_unique(array_filter(array_map('intval', array_column($orderList, 'id')))));
        $goodsMap = $this->getOrderGoodsMap($orderIds);
        $list = [];
        foreach ($orderList as $order) {
            $list[] = $this->buildUserOrderListItem($order, $goodsMap[(int)($order['id'] ?? 0)] ?? []);
        }

        return [
            'list' => $list,
            'total' => $count,
            'page' => $page,
            'limit' => $limit,
            'totalPage' => $limit > 0 ? (int)ceil($count / $limit) : 0,
        ];
    }

    /**
     * 获取用户退款订单列表
     *
     * @param int $userId 当前业务用户编号。
     * @param array $params 已校验的列表筛选或业务操作参数。
     * @return array 符合当前用户退款状态筛选的业务订单分页。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getUserRefundOrderList(int $userId, array $params = []): array
    {
        [$page, $limit] = $this->getPagesValue();
        $orderSn = trim((string)($params['order_sn'] ?? ''));
        $status = trim((string)($params['status'] ?? 'record'));

        $query = $this->getModel()
            ->where('user_id', $userId)
            ->where('order_role', '<>', Order::ROLE_MAIN)
            ->order('refund_application_time desc, id desc');

        $this->applyUserRefundStatusFilter($query, $status);

        if ($orderSn !== '') {
            $query->where('order_sn', 'like', '%' . $orderSn . '%');
        }

        $count = (int)(clone $query)->count();
        $orderList = $query->page($page, $limit)->select()->toArray();
        if ($orderList === []) {
            return [
                'list' => [],
                'total' => $count,
                'page' => $page,
                'limit' => $limit,
                'totalPage' => $limit > 0 ? (int)ceil($count / $limit) : 0,
            ];
        }

        $orderIds = array_values(array_unique(array_filter(array_map('intval', array_column($orderList, 'id')))));
        $goodsMap = $this->getOrderGoodsMap($orderIds);
        $list = [];
        foreach ($orderList as $order) {
            $list[] = $this->buildUserOrderListItem($order, $goodsMap[(int)($order['id'] ?? 0)] ?? []);
        }

        return [
            'list' => $list,
            'total' => $count,
            'page' => $page,
            'limit' => $limit,
            'totalPage' => $limit > 0 ? (int)ceil($count / $limit) : 0,
        ];
    }

    /**
     * 应用用户端退款列表状态筛选。
     *
     * @param mixed $query 需要追加筛选条件的查询对象。
     * @param string $status 当前业务状态或列表状态筛选值。
     * @return void 无返回值；追加当前用户退款列表的订单状态筛选。
     */
    protected function applyUserRefundStatusFilter($query, string $status): void
    {
        if ($status === 'pending') {
            $query->where(function ($subQuery) {
                $subQuery->where('refund_status', 1)
                    ->whereOr('status', Order::STATUS_REFUNDING);
            });
            return;
        }

        if ($status === 'refunded') {
            $query->where(function ($subQuery) {
                $subQuery->whereIn('refund_status', [2, 3])
                    ->whereOr('status', Order::STATUS_REFUNDED);
            });
            return;
        }

        $query->where(function ($subQuery) {
            $subQuery->where('refund_status', '>', 0)
                ->whereOr('status', Order::STATUS_REFUNDING)
                ->whereOr('status', Order::STATUS_REFUNDED);
        });
    }

    /**
     * 根据订单号批量获取用户订单列表项。
     *
     * @param int $userId 用户标识。
     * @param array $orderSns 订单号列表。
     * @return array 当前用户指定单号的业务订单展示列表。
     */
    public function getUserRefundOrdersByOrderSns(int $userId, array $orderSns): array
    {
        $orderSns = array_values(array_unique(array_filter(array_map('strval', $orderSns))));
        if ($orderSns === []) {
            return [];
        }

        $orderList = $this->getModel()
            ->where('user_id', $userId)
            ->where('order_role', '<>', Order::ROLE_MAIN)
            ->whereIn('order_sn', $orderSns)
            ->select()
            ->toArray();
        if ($orderList === []) {
            return [];
        }

        $orderIds = array_values(array_unique(array_filter(array_map('intval', array_column($orderList, 'id')))));
        $goodsMap = $this->getOrderGoodsMap($orderIds);
        $list = [];
        foreach ($orderList as $order) {
            $list[] = $this->buildUserOrderListItem($order, $goodsMap[(int)($order['id'] ?? 0)] ?? []);
        }

        return $list;
    }

    /**
     * 获取用户当前仍带退款状态的订单号。
     *
     * @param int $userId 用户标识。
     * @param string $keyword 订单号关键词。
     * @return array 当前用户仍带退款状态且命中关键词的订单号列表。
     */
    public function getUserRefundStateOrderSns(int $userId, string $keyword = ''): array
    {
        $query = $this->getModel()
            ->where('user_id', $userId)
            ->where('order_role', '<>', Order::ROLE_MAIN);
        $this->applyUserRefundStatusFilter($query, 'record');

        if ($keyword !== '') {
            $query->where('order_sn', 'like', '%' . $keyword . '%');
        }

        return array_values(array_filter(array_map('strval', $query->column('order_sn'))));
    }

    /**
     * 获取用户订单详情
     *
     * @param int $userId 当前业务用户编号。
     * @param string $orderSn 宿主订单的业务单号。
     * @return array 已校验用户归属的业务订单详情及商品明细。
     * @throws AdminResponseException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getUserOrderDetail(int $userId, string $orderSn): array
    {
        $order = $this->getUserOrderByOrderSn($userId, $orderSn);

        return $this->buildUserOrderDetailItem(
            $order,
            app()->make(OrderGoodsService::class)->getByOrderId((int)($order['id'] ?? 0))
        );
    }

    /**
     * 获取移动端订单支付结果。
     *
     * @param int $userId 用户标识。
     * @param string $orderSn 订单号。
     * @param int $orderId 订单标识；未传订单号时使用。
     * @return array 支付状态、订单信息和本次实际到账赠券。
     * @throws AdminResponseException
     */
    public function getUserOrderPaymentResult(int $userId, string $orderSn = '', int $orderId = 0): array
    {
        if ($orderSn !== '') {
            $order = $this->getUserOrderByOrderSn($userId, $orderSn);
        } elseif ($orderId > 0) {
            $orderModel = $this->get(['id' => $orderId, 'user_id' => $userId]);
            if (!$orderModel) {
                throw new AdminResponseException('订单不存在');
            }
            $order = $orderModel->toArray();
        } else {
            throw new AdminResponseException('请传入订单号或订单ID');
        }

        $orderSn = (string)($order['order_sn'] ?? '');
        $status = (int)($order['status'] ?? Order::STATUS_PENDING);
        $payRecordModel = app()->make(OrderPayRecordService::class)->get(['order_sn' => $orderSn]);
        $payRecord = $payRecordModel ? $payRecordModel->toArray() : [];
        $isPaid = $this->hasOrderPaymentSucceeded($order, $payRecord);
        $payType = trim((string)($order['pay_type'] ?? ''));
        if ($payType === '') {
            $payType = trim((string)($payRecord['pay_type'] ?? ''));
        }

        $giftCoupons = [];
        if ($isPaid) {
            $giftCoupons = app()->make(StoreCouponIssueService::class)->getGrantedOrderGiftCoupons(
                $userId,
                $this->getPaymentResultOrderSns($order)
            );
        }

        $failureReason = trim((string)($payRecord['error_message'] ?? ''));
        if ($failureReason === '') {
            $failureReason = $status === Order::STATUS_CANCELLED
                ? '订单已取消'
                : '支付未完成，请重新尝试';
        }

        return [
            'result_status' => $isPaid ? 'success' : 'failed',
            'is_paid' => $isPaid,
            'title' => $isPaid ? '支付成功' : '支付失败',
            'subtitle' => $isPaid
                ? $this->getPaymentSuccessSubtitle($order)
                : $failureReason,
            'order_id' => (int)($order['id'] ?? 0),
            'order_sn' => $orderSn,
            'main_order_sn' => (string)($order['main_order_sn'] ?? ''),
            'order_role' => (int)($order['order_role'] ?? Order::ROLE_NORMAL),
            'status' => $status,
            'status_text' => $this->resolveStatusText($status),
            'pay_amount' => (float)($order['pay_amount'] ?? 0),
            'pay_type' => $payType,
            'pay_type_text' => $this->resolvePayTypeText($payType),
            'create_time' => $order['create_time'] ?? null,
            'pay_expire_seconds' => $this->resolvePayExpireSeconds($order),
            'pay_time' => $order['pay_time'] ?? ($payRecord['pay_time'] ?? null),
            'retryable' => !$isPaid && $status === Order::STATUS_PENDING,
            'failure_reason' => $isPaid ? '' : $failureReason,
            'coupon_count' => count($giftCoupons),
            'gift_coupons' => $giftCoupons,
        ];
    }

    /**
     * 获取用户订单
     *
     * @param int $userId 当前业务用户编号。
     * @param string $orderSn 宿主订单的业务单号。
     * @return array 已校验用户归属的订单数据快照。
     * @throws AdminResponseException
     */
    public function getUserOrderByOrderSn(int $userId, string $orderSn): array
    {
        $order = $this->get([
            'user_id' => $userId,
            'order_sn' => $orderSn,
        ]);
        if (!$order) {
            throw new AdminResponseException('订单不存在');
        }

        return $order->toArray();
    }

    /**
     * 余额支付
     *
     * @param int $userId 当前业务用户编号。
     * @param string $orderSn 宿主订单的业务单号。
     * @return array 余额扣减并完成统一订单支付后的业务结果。
     * @throws AdminResponseException
     */
    public function payByBalance(int $userId, string $orderSn): array
    {
        return $this->transaction(function () use ($userId, $orderSn) {
            $order = $this->loadUserOrderForUpdate($userId, $orderSn);
            $status = (int)$order['status'];
            if ($this->isPaidStatus($status)) {
                return $this->buildPaidOrderPayload($order->toArray());
            }

            if ($status !== Order::STATUS_PENDING) {
                throw new AdminResponseException('当前订单状态不支持支付');
            }
            app()->make(OrderPaymentDeadlineService::class)->assertPayable($order->toArray());
            if (!empty($order['pay_expire_time'])) {
                $attempt = app()->make(OrderPayRecordService::class)->get(['order_sn' => $orderSn]);
                if ($attempt && in_array((string) $attempt['pay_type'], [PayTypeEnum::PAY_TYPE_WECHAT, PayTypeEnum::PAY_TYPE_ALIPAY], true)) {
                    throw new AdminResponseException('该订单已发起第三方支付，请使用原支付渠道或取消后重新抢购');
                }
            }
            if ((int)($order['order_role'] ?? Order::ROLE_NORMAL) === Order::ROLE_CHILD
                && (int)($order['pid'] ?? 0) > 0
            ) {
                throw new AdminResponseException('合单子订单请通过主支付单支付');
            }

            $payAmount = (float)($order['pay_amount'] ?? 0);
            if ($payAmount <= 0) {
                return $this->finishPaidOrder($order, PayTypeEnum::PAY_TYPE_FREE);
            }

            app()->make(UserAssetService::class)->changeAsset(
                $userId,
                'balance',
                $payAmount,
                'out',
                '订单余额支付',
                [
                    'source_type' => 'order_balance_pay',
                    'order_no' => (string)($order['order_sn'] ?? ''),
                    'source_desc' => '订单余额支付',
                ]
            );

            return $this->finishPaidOrder($order, PayTypeEnum::PAY_TYPE_YUE);
        });
    }

    /**
     * 根据订单号标记支付成功
     *
     * @param string $orderSn 宿主订单的业务单号。
     * @param string $payType 已选择或验证的支付渠道编码。
     * @param ?int $paidAt 已验证的实际支付时间戳；为空时使用服务端时间。
     * @return array 按业务单号完成支付后的订单状态与权益结果。
     * @throws AdminResponseException
     */
    public function markOrderPaidByOrderSn(string $orderSn, string $payType = '', ?int $paidAt = null): array
    {
        $order = $this->get(['order_sn' => $orderSn], ['id']);
        if (!$order) {
            throw new AdminResponseException('订单不存在');
        }

        return $this->markOrderPaid((int)$order['id'], $payType, $paidAt);
    }

    /**
     * 标记订单支付成功
     *
     * @param int $orderId 当前业务订单的内部编号。
     * @param string $payType 已选择或验证的支付渠道编码。
     * @param ?int $paidAt 已验证的实际支付时间戳；为空时使用服务端时间。
     * @return array 共享订单锁内完成支付后的订单状态与权益结果。
     * @throws AdminResponseException
     */
    public function markOrderPaid(int $orderId, string $payType = '', ?int $paidAt = null): array
    {
        return $this->transaction(function () use ($orderId, $payType, $paidAt) {
            $order = $this->loadOrderForUpdate($orderId);

            return $this->finishPaidOrder($order, $payType, 0, false, null, $paidAt);
        });
    }

    /**
     * 核实逾期到账后释放已过期活动订单，无须再向外部支付渠道关单。
     *
     * @param int $orderId 需锁定的支付订单标识。
     * @return array 已关闭的订单数据。
     */
    public function rejectExpiredReceipt(int $orderId): array
    {
        return $this->transaction(function () use ($orderId): array {
            $order = $this->loadOrderForUpdate($orderId);
            if (empty($order['pay_expire_time'])) {
                throw new AdminResponseException('普通订单不能使用活动实收释放流程');
            }
            if ((int) $order['status'] === Order::STATUS_PENDING) {
                $order->save(['status' => Order::STATUS_CANCELLED]);
                app()->make(PluginOrderLifecycleService::class)->sync($order->toArray(), 'cancelled');
                $this->restorePendingOrderResources($order);
            }
            return $order->toArray();
        });
    }

    /**
     * 按订单确认实际退回库存，累计记录退货收货凭证。
     *
     * @param int $orderId 业务订单标识。
     * @param array $lines 包含 order_goods_id（订单商品标识）及 returned_quantity（累计退回数量）的商品行。
     * @param int $merchantId 商户范围，0 表示平台。
     * @return array 累计的永久库存凭证。
     */
    public function receiveReturnedInventory(int $orderId, array $lines, int $merchantId = 0): array
    {
        if ($lines === [] || count($lines) > 100) {
            throw new AdminResponseException('请提交有效的退货商品');
        }
        return $this->transaction(function () use ($orderId, $lines, $merchantId): array {
            $order = $this->loadOrderForUpdate($orderId);
            if ($merchantId > 0 && (int) $order['merchant_id'] !== $merchantId) {
                throw new AdminResponseException('无权接收此订单退货');
            }
            if (empty($order['pay_expire_time']) || empty($order['delivery_time'])) {
                throw new AdminResponseException('仅已发货的活动订单可确认实际退货');
            }
            // 已批准的部分退款仅允许退回对应实物商品行的数量，不能根据退款金额推算件数。
            $approved = app()->make(StoreRefundProductService::class)->getModel()
                ->where('order_id', $orderId)->where('status', StoreRefundProductService::STATUS_SUCCESS)
                ->field(['order_goods_id', 'refund_num'])->select()->toArray();
            $quantities = [];
            foreach ($approved as $line) {
                $goodsId = (int) $line['order_goods_id'];
                $quantities[$goodsId] = ($quantities[$goodsId] ?? 0) + max(0, (int) $line['refund_num']);
            }
            app()->make(OrderInventoryService::class)->restoreReturned($orderId, $lines, $quantities);
            $evidence = $this->inventoryEvidence($orderId);
            app()->make(OrderPromotionService::class)->refund($orderId, $evidence);
            return $evidence;
        });
    }

    /**
     * 用户是否已有已支付业务订单。
     *
     * @param int $userId 用户标识。
     * @param int $merchantId 商户标识；0 表示全平台首单。
     * @return bool 用户在指定商户是否已有支付成功的业务订单。
     */
    public function hasPaidBusinessOrder(int $userId, int $merchantId = 0): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $query = $this->getModel()
            ->where('user_id', $userId)
            ->where('order_role', '<>', Order::ROLE_MAIN)
            ->whereIn('status', $this->paidBusinessStatuses());

        if ($merchantId > 0) {
            $query->where('merchant_id', $merchantId);
        }

        return (int)$query->count() > 0;
    }

    /**
     * 用户是否已有更早支付成功的业务订单。
     *
     * @param int $userId 用户标识。
     * @param int $merchantId 商户标识；0 表示全平台首单。
     * @param array $currentOrder 当前支付成功订单快照。
     * @return bool 用户在指定商户是否存在早于当前订单的已支付业务订单。
     */
    public function hasEarlierPaidBusinessOrder(int $userId, int $merchantId, array $currentOrder): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $currentOrderId = (int)($currentOrder['id'] ?? 0);
        $payTime = (string)($currentOrder['pay_time'] ?? '');

        $query = $this->getModel()
            ->where('user_id', $userId)
            ->where('order_role', '<>', Order::ROLE_MAIN)
            ->whereIn('status', $this->paidBusinessStatuses());

        if ($merchantId > 0) {
            $query->where('merchant_id', $merchantId);
        }

        if ($payTime === '') {
            if ($currentOrderId > 0) {
                $query->where('id', '<>', $currentOrderId);
            }
            return (int)$query->count() > 0;
        }

        $query->where(function ($query) use ($payTime, $currentOrderId) {
            $query->whereNull('pay_time')
                ->whereOr('pay_time', '')
                ->whereOr('pay_time', '<', $payTime);

            if ($currentOrderId > 0) {
                $query->whereOr(function ($subQuery) use ($payTime, $currentOrderId) {
                    $subQuery->where('pay_time', $payTime)
                        ->where('id', '<', $currentOrderId);
                });
            }
        });

        return (int)$query->count() > 0;
    }

    /**
     * 确认收货
     *
     * @param int $userId 当前业务用户编号。
     * @param string $orderSn 宿主订单的业务单号。
     * @return array 确认收货并处理结算、佣金后的业务订单详情。
     * @throws AdminResponseException
     */
    public function confirmReceipt(int $userId, string $orderSn): array
    {
        return $this->transaction(function () use ($userId, $orderSn) {
            $order = $this->loadUserOrderForUpdate($userId, $orderSn);
            $orderData = $order->toArray();
            $status = (int)($orderData['status'] ?? Order::STATUS_PENDING);

            if (app()->make(PluginCheckoutCapabilityService::class)->isHotelOrder($orderData)) {
                throw new AdminResponseException('酒店预订无需确认收货');
            }

            if ((int)($orderData['order_type'] ?? Order::ORDER_TYPE_GOODS) !== Order::ORDER_TYPE_GOODS) {
                throw new AdminResponseException('当前订单不需要确认收货');
            }

            if ($status === Order::STATUS_COMPLETED) {
                return $this->buildPaidOrderPayload($orderData);
            }

            if ($status !== Order::STATUS_PAID) {
                throw new AdminResponseException('当前订单状态不支持确认收货');
            }

            if (empty($orderData['delivery_time'])) {
                throw new AdminResponseException('订单尚未发货，不能确认收货');
            }

            $now = date('Y-m-d H:i:s');
            $order->save([
                'status' => Order::STATUS_WAIT_REVIEW,
                'receive_time' => $now,
                'finish_time' => null,
            ]);

            $fresh = $this->get((int)$order['id']);
            $freshData = $fresh ? $fresh->toArray() : array_merge($orderData, [
                'status' => Order::STATUS_WAIT_REVIEW,
                'receive_time' => $now,
                'finish_time' => null,
            ]);
            app()->make(OrderMerchantSettlementService::class)->markOrderCompleted($freshData);
            app()->make(BackendMessageEventService::class)->orderConfirmed($freshData);

            return $this->buildPaidOrderPayload($freshData);
        });
    }

    /**
     * 标记订单退款中
     *
     * @param string $orderSn 宿主订单的业务单号。
     * @param float $refundAmount 本次申请或确认退款的金额，单位为元。
     * @param string $refundReason 本次退款原因。
     * @return array 进入退款中的当前订单业务状态。
     * @throws AdminResponseException
     */
    public function markOrderRefundingByOrderSn(string $orderSn, float $refundAmount, string $refundReason = ''): array
    {
        return $this->transaction(function () use ($orderSn, $refundAmount, $refundReason) {
            $order = $this->loadOrderByOrderSnForUpdate($orderSn);

            return $this->markRefundingOrder($order, $refundAmount, $refundReason);
        });
    }

    /**
     * 标记订单退款中（当前事务内）
     *
     * @param Model $order 当前业务订单的模型或不可变数据快照。
     * @param float $refundAmount 本次申请或确认退款的金额，单位为元。
     * @param string $refundReason 本次退款原因。
     * @return array 共享事务内更新后的退款申请金额及订单状态。
     * @throws AdminResponseException
     */
    public function markRefundingOrder(Model $order, float $refundAmount, string $refundReason = ''): array
    {
        $orderData = $order->toArray();
        $status = (int)($orderData['status'] ?? Order::STATUS_PENDING);
        $refundStatus = (int)($orderData['refund_status'] ?? 0);

        if ($refundStatus === 2 || $status === Order::STATUS_REFUNDED) {
            return $this->buildPaidOrderPayload($orderData);
        }

        if ($refundStatus === 1 || $status === Order::STATUS_REFUNDING) {
            throw new AdminResponseException('订单正在退款处理中');
        }

        if (!$this->isRefundableStatus($status)) {
            throw new AdminResponseException('当前订单状态不支持退款');
        }

        $order->save([
            'status' => Order::STATUS_REFUNDING,
            'refund_status' => 1,
            'refund_amount' => $refundAmount,
            'refund_reason' => $refundReason,
            'refund_application_time' => date('Y-m-d H:i:s'),
        ]);

        return $this->buildPaidOrderPayload(array_merge($orderData, [
            'status' => Order::STATUS_REFUNDING,
            'refund_status' => 1,
            'refund_amount' => $refundAmount,
            'refund_reason' => $refundReason,
        ]));
    }

    /**
     * 标记订单退款成功
     *
     * @param string $orderSn 宿主订单的业务单号。
     * @param float $refundAmount 本次申请或确认退款的金额，单位为元。
     * @param string $refundReason 本次退款原因。
     * @param string $refundSn 固定的退款业务单号。
     * @return array 退款成功并处理资源及财务冲正后的业务状态。
     * @throws AdminResponseException
     */
    public function markOrderRefundedByOrderSn(
        string $orderSn,
        float $refundAmount,
        string $refundReason = '',
        string $refundSn = ''
    ): array
    {
        return $this->transaction(function () use ($orderSn, $refundAmount, $refundReason, $refundSn) {
            $order = $this->loadOrderByOrderSnForUpdate($orderSn);

            return $this->markRefundedOrder($order, $refundAmount, $refundReason, $refundSn);
        });
    }

    /**
     * 标记订单退款成功（当前事务内）
     *
     * @param Model $order 当前业务订单的模型或不可变数据快照。
     * @param float $refundAmount 本次申请或确认退款的金额，单位为元。
     * @param string $refundReason 本次退款原因。
     * @param string $refundSn 固定的退款业务单号。
     * @return array 共享事务内登记成功退款后的订单业务状态。
     * @throws AdminResponseException
     */
    public function markRefundedOrder(
        Model $order,
        float $refundAmount,
        string $refundReason = '',
        string $refundSn = ''
    ): array
    {
        $orderData = $order->toArray();
        $status = (int)($orderData['status'] ?? Order::STATUS_PENDING);
        $refundStatus = (int)($orderData['refund_status'] ?? 0);

        if ($refundStatus === 2 || $status === Order::STATUS_REFUNDED) {
            return $this->buildPaidOrderPayload($orderData);
        }

        if (!in_array($status, [Order::STATUS_REFUNDING, Order::STATUS_PAID, Order::STATUS_WAIT_REVIEW], true)) {
            throw new AdminResponseException('当前订单状态不支持退款完成处理');
        }

        $orderId = (int)($orderData['id'] ?? 0);
        $payAmount = (float)($orderData['pay_amount'] ?? 0);
        $totalRefundedAmount = 0.0;
        /*
         * 退款也先创建聚合流水，再把商户入账、平台抽佣、平台券补贴冲正等明细流水挂到 pid 下。
         * 财务既能看到一笔退款的总额，也能追踪每个商户/商品行的实际冲正明细。
         */
        $refundSummary = $this->createRefundFinanceSummary($orderData, $refundAmount, $refundSn);
        $financePid = (int)($refundSummary['id'] ?? 0);
        if ($refundSn !== '') {
            app()->make(OrderMerchantSettlementService::class)->reverseRefundProducts($refundSn, $orderData, $financePid);
            $totalRefundedAmount = app()->make(StoreRefundProductService::class)->sumActiveRefundAmountByOrder($orderId);
        }

        if ($refundSn === '' || $totalRefundedAmount <= 0) {
            app()->make(OrderMerchantSettlementService::class)->reverseRefundedOrder($orderData, $financePid);
            $totalRefundedAmount = $refundAmount > 0 ? $refundAmount : $payAmount;
        }

        $totalRefundedAmount = $this->normalizeMoney($totalRefundedAmount);
        $isFullRefund = $payAmount <= 0
            || $this->moneyCompare($totalRefundedAmount, $this->moneySub($payAmount, 0.01)) >= 0;
        $newStatus = $isFullRefund ? Order::STATUS_REFUNDED : $this->resolveRefundRollbackStatus($orderData);
        $newRefundStatus = $isFullRefund ? 2 : 3;

        $order->save([
            'status' => $newStatus,
            'refund_status' => $newRefundStatus,
            'refund_amount' => $totalRefundedAmount,
            'refund_reason' => $refundReason,
            'refund_reason_time' => date('Y-m-d H:i:s'),
        ]);
        app()->make(PluginOrderLifecycleService::class)->sync($order->toArray(), 'refunded', $refundSn);

        if ($isFullRefund) {
            app()->make(OrderGiftPointsRecordService::class)->rollbackByOrder($orderData);
            $this->restoreRefundResources($order);
        }
        $this->reverseReleasedOrderCommission($orderData, $refundSn, $totalRefundedAmount, $payAmount);
        app()->make(OrderPromotionService::class)->refund($orderId, $this->inventoryEvidence($orderId));

        $fresh = $this->get((int)$order['id']);
        $freshData = $fresh ? $fresh->toArray() : array_merge($orderData, [
            'status' => $newStatus,
            'refund_status' => $newRefundStatus,
            'refund_amount' => $totalRefundedAmount,
            'refund_reason' => $refundReason,
        ]);

        return $this->buildPaidOrderPayload($freshData);
    }

    /**
     * 平台管理员审核通过退款
     *
     * @param int $orderId 当前业务订单的内部编号。
     * @return void 无返回值；平台审核通过后交由统一渠道流程执行退款。
     * @throws AdminResponseException
     */
    public function adminApproveRefund(int $orderId): void
    {
        app()->make(OrderPayService::class)->approveRefund($orderId);
    }

    /**
     * 平台管理员拒绝退款
     *
     * @param int $orderId 当前业务订单的内部编号。
     * @param string $reason 当前业务操作原因。
     * @return void 无返回值；平台拒绝申请并保留历史成功退款状态。
     * @throws AdminResponseException
     */
    public function adminRejectRefund(int $orderId, string $reason = '', bool $hotelCancellation = false): void
    {
        $this->transaction(function () use ($orderId, $reason, $hotelCancellation) {
            $order = $this->loadOrderForUpdate($orderId);
            $orderData = $order->toArray();
            if (app()->make(PluginCheckoutCapabilityService::class)->isHotelOrder($orderData) && !$hotelCancellation) {
                throw new AdminResponseException('酒店取消请在酒店预订审核页面处理');
            }
            $status = (int)($orderData['status'] ?? Order::STATUS_PENDING);
            $refundStatus = (int)($orderData['refund_status'] ?? 0);

            if ($refundStatus === 0 && $status !== Order::STATUS_REFUNDING) {
                return;
            }

            $rollbackStatus = $this->resolveRefundRollbackStatus($orderData);
            $orderSn = (string)($orderData['order_sn'] ?? '');
            $failedRefundAmount = (float)($orderData['refund_amount'] ?? 0);
            $failedRefundReason = $reason !== '' ? $reason : (string)($orderData['refund_reason'] ?? '');
            $this->cancelPendingRefundProductsByOrderSn($orderSn);
            $existingRefundState = $this->resolveExistingRefundState($orderId);
            $order->save([
                'status' => $rollbackStatus,
                'refund_status' => $existingRefundState['refund_status'],
                'refund_amount' => $existingRefundState['refund_amount'],
                'refund_reason' => $failedRefundReason,
                'refund_application_time' => null,
            ]);
            app()->make(OrderRefundService::class)->markCancelledByOrderSn(
                $orderSn,
                $reason !== '' ? $reason : '平台拒绝退款申请'
            );

            $fresh = $this->get($orderId);
            app()->make(BackendMessageEventService::class)->refundRejected($fresh ? $fresh->toArray() : array_merge($orderData, [
                'status' => $rollbackStatus,
                'refund_status' => $existingRefundState['refund_status'],
                'refund_amount' => $existingRefundState['refund_amount'],
            ]));
            app()->make(MessagePushEventService::class)->dispatch(
                MessagePushDefinitionService::EVENT_REFUND_FAILED,
                array_merge($fresh ? $fresh->toArray() : $orderData, [
                    'refund_amount' => $failedRefundAmount,
                    'refund_reason' => $failedRefundReason,
                    'fail_reason' => $reason !== '' ? $reason : '平台拒绝退款申请',
                    'relation_type' => 'order',
                    'relation_id' => $orderId,
                ])
            );
        });
    }

    /**
     * 平台管理员发起退款申请
     *
     * @param int $orderId 当前业务订单的内部编号。
     * @param float $refundAmount 本次申请或确认退款的金额，单位为元。
     * @param string $refundReason 本次退款原因。
     * @param array $items 当前结算或订单中的商品明细列表。
     * @return array 平台发起申请后的订单、退款记录及商品退款状态。
     * @throws AdminResponseException
     */
    public function adminApplyRefund(int $orderId, float $refundAmount, string $refundReason = '', array $items = []): array
    {
        if ($refundAmount <= 0 && $items === []) {
            throw new AdminResponseException('退款金额必须大于0');
        }

        return $this->transaction(function () use ($orderId, $refundAmount, $refundReason, $items) {
            $order = $this->loadOrderForUpdate($orderId);
            $orderData = $order->toArray();
            if (app()->make(PluginCheckoutCapabilityService::class)->isHotelOrder($orderData)) {
                throw new AdminResponseException('酒店取消请在酒店预订审核页面处理');
            }
            $refundPreview = app()->make(StoreRefundProductService::class)
                ->previewRefundProducts($orderData, $items, $refundAmount);
            $refundAmount = (float)($refundPreview['refund_amount'] ?? 0);

            $result = $this->markRefundingOrder($order, $refundAmount, $refundReason);
            $refundRecord = app()->make(OrderRefundService::class)->createOrUpdatePending(
                array_merge($orderData, [
                    'refund_amount' => $refundAmount,
                    'refund_reason' => $refundReason,
                ]),
                $refundAmount,
                $refundReason,
                'admin_apply'
            );
            app()->make(StoreRefundProductService::class)->createOrUpdateForRefund(
                $orderData,
                $refundRecord,
                $items,
                $refundAmount
            );
            $fresh = $this->get($orderId);
            app()->make(BackendMessageEventService::class)->refundApplied($fresh ? $fresh->toArray() : $result);

            return $result;
        });
    }

    /**
     * 用户发起退款申请。
     *
     * 移动端只创建待审核退款记录并把订单切到退款中，不执行实际退款。
     *
     * @param int $userId 用户标识。
     * @param string $orderSn 订单号。
     * @param string $refundReason 退款原因。
     * @param array $items 退款商品明细。
     * @return array{order_sn:string,refund_sn:string,refund_status:int,refund_amount:float,status_text:string} 用户提交待审核退款后的业务状态，不执行渠道退款。
     * @throws AdminResponseException
     */
    public function userApplyRefund(int $userId, string $orderSn, string $refundReason = '', array $items = [], bool $hotelCancellation = false): array
    {
        return $this->transaction(function () use ($userId, $orderSn, $refundReason, $items, $hotelCancellation): array {
            $order = $this->loadUserOrderForUpdate($userId, $orderSn);
            $orderData = $order->toArray();
            if (app()->make(PluginCheckoutCapabilityService::class)->isHotelOrder($orderData) && !$hotelCancellation) {
                throw new AdminResponseException('酒店预订请在预订详情申请取消');
            }
            $orderId = (int)($orderData['id'] ?? 0);
            $orderSnValue = (string)($orderData['order_sn'] ?? '');
            $status = (int)($orderData['status'] ?? Order::STATUS_PENDING);
            $refundStatus = (int)($orderData['refund_status'] ?? 0);

            if ((int)($orderData['order_role'] ?? Order::ROLE_NORMAL) === Order::ROLE_MAIN) {
                throw new AdminResponseException('主支付单请在子订单中申请退款');
            }

            if (!$this->isRefundableStatus($status)) {
                throw new AdminResponseException('当前订单状态不支持退款');
            }

            $refundRecordService = app()->make(OrderRefundService::class);
            $pendingRecord = $refundRecordService->getPendingByOrderSn($orderSnValue);
            if ($pendingRecord && ($refundStatus === 1 || $status === Order::STATUS_REFUNDING)) {
                return [
                    'order_sn' => $orderSnValue,
                    'refund_sn' => (string)($pendingRecord['refund_sn'] ?? ''),
                    'refund_status' => 1,
                    'refund_amount' => (float)($pendingRecord['refund_amount'] ?? $orderData['refund_amount'] ?? 0),
                    'status_text' => '退款处理中',
                ];
            }

            $refundReason = trim($refundReason) !== '' ? trim($refundReason) : '用户申请退款';
            $refundPreview = app()->make(StoreRefundProductService::class)
                ->previewRefundProducts($orderData, $items, 0);
            $refundAmount = (float)($refundPreview['refund_amount'] ?? 0);

            $result = $this->markRefundingOrder($order, $refundAmount, $refundReason);
            $refundRecord = $refundRecordService->createOrUpdatePending(
                array_merge($orderData, [
                    'refund_amount' => $refundAmount,
                    'refund_reason' => $refundReason,
                ]),
                $refundAmount,
                $refundReason,
                'user_apply'
            );
            app()->make(StoreRefundProductService::class)->createOrUpdateForRefund(
                $orderData,
                $refundRecord,
                $items,
                $refundAmount
            );

            $fresh = $this->get($orderId);
            app()->make(BackendMessageEventService::class)->refundApplied($fresh ? $fresh->toArray() : $result);

            return [
                'order_sn' => $orderSnValue,
                'refund_sn' => (string)($refundRecord['refund_sn'] ?? ''),
                'refund_status' => 1,
                'refund_amount' => $refundAmount,
                'status_text' => '退款处理中',
            ];
        });
    }

    /**
     * 酒店过期取消审核调整原待审退款单金额，不创建第二张退款单。
     *
     * @param int $orderId 宿主酒店订单编号。
     * @param string $refundSn 用户申请产生的退款单号。
     * @param float $amount 平台审核的本次渠道退款金额。
     * @param string $reason 平台审核意见。
     * @return void 原退款单和商品退款明细在同一事务内更新。
     */
    public function adjustPendingHotelRefund(int $orderId, string $refundSn, float $amount, string $reason): void
    {
        $this->transaction(function () use ($orderId, $refundSn, $amount, $reason): void {
            $order = $this->loadOrderForUpdate($orderId);
            $data = $order->toArray();
            if (!app()->make(PluginCheckoutCapabilityService::class)->isHotelOrder($data)
                || (int)($data['refund_status'] ?? 0) !== 1
                || (int)($data['status'] ?? 0) !== Order::STATUS_REFUNDING) {
                throw new AdminResponseException('酒店预订没有待审核退款');
            }
            $refundService = app()->make(OrderRefundService::class);
            $pending = $refundService->getPendingByOrderSn((string)$data['order_sn']);
            if (!$pending || $refundSn === '' || (string)$pending['refund_sn'] !== $refundSn) {
                throw new AdminResponseException('原退款申请已变更，请刷新预订');
            }
            $goodsService = app()->make(StoreRefundProductService::class);
            $preview = $goodsService->previewRefundProducts($data, [], $amount, $refundSn);
            $adjusted = (float)$preview['refund_amount'];
            $order->save(['refund_amount' => $adjusted, 'refund_reason' => $reason]);
            $record = $refundService->createOrUpdatePending($data, $adjusted, $reason, 'hotel_review');
            $goodsService->createOrUpdateForRefund($data, $record, [], $adjusted);
        });
    }

    /**
     * 平台管理员更新订单备注
     *
     * @param int $orderId 当前业务订单的内部编号。
     * @param string $remark 平台管理员提交的订单备注。
     * @return array 订单备注更新后的业务订单快照。
     * @throws AdminResponseException
     */
    public function adminUpdateRemark(int $orderId, string $remark): array
    {
        $order = $this->get($orderId);
        if (!$order) {
            throw new AdminResponseException('订单不存在');
        }

        $this->update($orderId, ['remark' => $remark]);
        $fresh = $this->get($orderId);

        return $fresh ? $fresh->toArray() : array_merge($order->toArray(), ['remark' => $remark]);
    }

    /**
     * 重置退款状态
     *
     * @param string $orderSn 宿主订单的业务单号。
     * @param string $failedRefundSn 已验签的渠道退款单号，空值保持原调用行为。
     * @return void 无返回值；按业务单号回滚失败或取消申请的退款状态。
     * @throws AdminResponseException
     */
    public function resetRefundStatusByOrderSn(string $orderSn, string $failedRefundSn = ''): void
    {
        $this->transaction(function () use ($orderSn, $failedRefundSn) {
            $order = $this->loadOrderByOrderSnForUpdate($orderSn);
            $orderData = $order->toArray();

            if ((int)($orderData['refund_status'] ?? 0) === 0 && (int)($orderData['status'] ?? 0) !== Order::STATUS_REFUNDING) {
                return;
            }

            $orderId = (int)($orderData['id'] ?? 0);
            $pendingRefund = $failedRefundSn !== ''
                ? app()->make(OrderRefundService::class)->getByRefundSn($failedRefundSn)
                : app()->make(OrderRefundService::class)->getPendingByOrderSn($orderSn);
            $this->cancelPendingRefundProductsByOrderSn($orderSn);
            $existingRefundState = $this->resolveExistingRefundState($orderId);
            $order->save([
                'status' => $this->resolveRefundRollbackStatus($orderData),
                'refund_status' => $existingRefundState['refund_status'],
                'refund_amount' => $existingRefundState['refund_amount'],
                'refund_reason' => '',
                'refund_application_time' => null,
            ]);
            app()->make(OrderRefundService::class)->markCancelledByOrderSn($orderSn, '退款失败，订单退款状态已重置');
            if ($pendingRefund && (string)$pendingRefund['order_sn'] === $orderSn) {
                app()->make(PluginOrderLifecycleService::class)->sync(
                    $order->toArray(), 'refund_failed', (string)$pendingRefund['refund_sn']
                );
            }

            $fresh = $this->get($orderId);
            app()->make(BackendMessageEventService::class)->refundCancelled($fresh ? $fresh->toArray() : array_merge($orderData, [
                'refund_status' => $existingRefundState['refund_status'],
                'refund_amount' => $existingRefundState['refund_amount'],
            ]));
        });
    }

    /**
     * 取消用户待支付订单
     *
     * @param int $userId 当前业务用户编号。
     * @param string $orderSn 宿主订单的业务单号。
     * @return array 取消成功或支付竞争胜出后的最新用户订单详情。
     * @throws AdminResponseException
     */
    public function cancelUserOrder(int $userId, string $orderSn): array
    {
        return $this->transaction(function () use ($userId, $orderSn) {
            $order = $this->loadUserOrderForUpdate($userId, $orderSn);
            $orderData = $order->toArray();
            $status = (int)($orderData['status'] ?? Order::STATUS_PENDING);
            $orderId = (int)($orderData['id'] ?? 0);

            if ($status === Order::STATUS_CANCELLED) {
                return $this->buildUserOrderDetailItem($orderData, app()->make(OrderGoodsService::class)->getByOrderId($orderId));
            }

            $this->cancelPendingOrder($order, '用户取消订单');

            $fresh = $this->get($orderId);
            $freshData = $fresh ? $fresh->toArray() : array_merge($orderData, [
                'status' => Order::STATUS_CANCELLED,
            ]);
            if ((int) $freshData['status'] === Order::STATUS_CANCELLED) {
                app()->make(BackendMessageEventService::class)->orderCancelled($freshData);
            }

            return $this->buildUserOrderDetailItem($freshData, app()->make(OrderGoodsService::class)->getByOrderId($orderId));
        });
    }

    /**
     * 取消当前用户退款申请
     *
     * @param int $userId 当前业务用户编号。
     * @param string $orderSn 宿主订单的业务单号。
     * @return array 取消当前申请并保留历史退款证据后的订单详情。
     * @throws AdminResponseException
     */
    public function cancelUserRefund(int $userId, string $orderSn): array
    {
        return $this->transaction(function () use ($userId, $orderSn) {
            $order = $this->loadUserOrderForUpdate($userId, $orderSn);
            $orderData = $order->toArray();
            $status = (int)($orderData['status'] ?? Order::STATUS_PENDING);
            $refundStatus = (int)($orderData['refund_status'] ?? 0);

            if ($refundStatus !== 1 && $status !== Order::STATUS_REFUNDING) {
                throw new AdminResponseException('当前订单没有待取消的退款申请');
            }

            $rollbackStatus = $this->resolveRefundRollbackStatus($orderData);
            $orderSnValue = (string)($orderData['order_sn'] ?? '');
            $orderId = (int)($orderData['id'] ?? 0);
            $this->cancelPendingRefundProductsByOrderSn($orderSnValue);
            $existingRefundState = $this->resolveExistingRefundState($orderId);
            $order->save([
                'status' => $rollbackStatus,
                'refund_status' => $existingRefundState['refund_status'],
                'refund_amount' => $existingRefundState['refund_amount'],
                'refund_reason' => '',
                'refund_application_time' => null,
            ]);
            app()->make(OrderRefundService::class)->markCancelledByOrderSn($orderSnValue, '用户取消退款申请');

            $fresh = $this->get($orderId);
            $freshData = $fresh ? $fresh->toArray() : array_merge($orderData, [
                'status' => $rollbackStatus,
                'refund_status' => $existingRefundState['refund_status'],
                'refund_amount' => $existingRefundState['refund_amount'],
                'refund_reason' => '',
                'refund_application_time' => null,
            ]);
            app()->make(BackendMessageEventService::class)->refundCancelled($freshData);

            return $this->buildUserOrderDetailItem($freshData, app()->make(OrderGoodsService::class)->getByOrderId($orderId));
        });
    }

    /**
     * 获取商户订单列表
     *
     * @param int $merchantId 操作或结算所属商户编号，零表示平台。
     * @param array $params 已校验的列表筛选或业务操作参数。
     * @return array 仅当前商户可见的业务订单分页。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getMerchantOrderList(int $merchantId, array $params = []): array
    {
        return $this->getMerchantPagedResult($merchantId, $params, false);
    }

    /**
     * 获取商户退款列表
     *
     * @param int $merchantId 操作或结算所属商户编号，零表示平台。
     * @param array $params 已校验的列表筛选或业务操作参数。
     * @return array 仅当前商户可见的退款业务订单分页。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getMerchantRefundList(int $merchantId, array $params = []): array
    {
        return $this->getMerchantPagedResult($merchantId, $params, true);
    }

    /**
     * 获取商户订单详情
     *
     * @param int $merchantId 操作或结算所属商户编号，零表示平台。
     * @param int $orderId 当前业务订单的内部编号。
     * @return array 已校验商户归属的业务订单详情。
     * @throws AdminResponseException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getMerchantOrderDetail(int $merchantId, int $orderId): array
    {
        $order = $this->getMerchantOrderModel($merchantId, $orderId);
        $orderData = $order->toArray();
        $goodsList = app()->make(OrderGoodsService::class)->getByOrderId($orderId);
        $userId = (int)($orderData['user_id'] ?? 0);
        $merchantId = (int)($orderData['merchant_id'] ?? 0);
        $userMap = $this->getUserMap([$userId]);
        $merchantMap = $this->getMerchantMap([$merchantId]);

        return $this->buildMerchantOrderDetailItem(
            $orderData,
            $goodsList,
            $userMap[$userId] ?? [],
            $merchantMap[$merchantId] ?? []
        );
    }

    /**
     * 获取商户订单记录
     *
     * @param int $merchantId 操作或结算所属商户编号，零表示平台。
     * @param int $orderId 当前业务订单的内部编号。
     * @return array 当前商户所属订单的真实业务时间线。
     * @throws AdminResponseException
     */
    public function getMerchantOrderRecord(int $merchantId, int $orderId): array
    {
        $order = $this->getMerchantOrderModel($merchantId, $orderId)->toArray();

        return $this->buildMerchantOrderTimeline($order);
    }

    /**
     * 订单发货
     *
     * @param int $merchantId 操作或结算所属商户编号，零表示平台。
     * @param int $orderId 当前业务订单的内部编号。
     * @param array $data 待保存的业务字段。
     * @return void 无返回值；校验商户归属后登记订单发货资料。
     * @throws AdminResponseException
     */
    public function delivery(int $merchantId, int $orderId, array $data): void
    {
        $order = $this->get($orderId);
        if (!$order) {
            throw new AdminResponseException('订单不存在');
        }
        if ((int)$order['merchant_id'] !== $merchantId) {
            throw new AdminResponseException('无权操作该订单');
        }

        $this->submitDelivery($order->toArray(), $orderId, $data);
    }

    /**
     * 后台订单发货
     *
     * @param int $orderId 当前业务订单的内部编号。
     * @param array $data 待保存的业务字段。
     * @return void 无返回值；由平台登记订单发货资料。
     * @throws AdminResponseException
     */
    public function adminDelivery(int $orderId, array $data): void
    {
        $order = $this->get($orderId);
        if (!$order) {
            throw new AdminResponseException('订单不存在');
        }

        $this->submitDelivery($order->toArray(), $orderId, $data, true);
    }

    /**
     * 后台查询订单物流轨迹
     *
     * @param int $orderId 当前业务订单的内部编号。
     * @return array 平台订单的物流单号、轨迹及查询结果。
     * @throws AdminResponseException
     */
    public function adminExpressTrace(int $orderId): array
    {
        $order = $this->get($orderId);
        if (!$order) {
            throw new AdminResponseException('订单不存在');
        }

        return $this->queryOrderExpressTrace($order->toArray());
    }

    /**
     * 查询当前用户订单物流详情。
     *
     * @param int $userId 当前用户标识。
     * @param int $orderId 订单标识。
     * @param string $orderSn 订单号。
     * @return array 物流公司、收货信息和轨迹列表。
     * @throws AdminResponseException
     */
    public function getUserExpressTrace(int $userId, int $orderId = 0, string $orderSn = ''): array
    {
        if ($orderId > 0) {
            $order = $this->get(['id' => $orderId, 'user_id' => $userId]);
        } else {
            $order = $this->get(['order_sn' => trim($orderSn), 'user_id' => $userId]);
        }
        if (!$order) {
            throw new AdminResponseException('订单不存在');
        }

        $orderData = $order->toArray();
        $result = $this->queryOrderExpressTrace($orderData);
        $traces = $this->decorateUserExpressTraces($result['traces'] ?? []);
        $traceSource = 'provider';
        if ($traces === []) {
            $traces = $this->buildOrderExpressFallbackTraces($orderData);
            $traceSource = 'order';
        }

        $status = $this->resolveUserExpressStatus($orderData);
        $addressParts = array_filter([
            trim((string)($orderData['province'] ?? '')),
            trim((string)($orderData['city'] ?? '')),
            trim((string)($orderData['district'] ?? '')),
            trim((string)($orderData['detail'] ?? '')),
        ]);
        $address = trim((string)($orderData['user_address'] ?? ''));
        if ($address === '') {
            $address = implode(' ', $addressParts);
        }

        return array_merge($result, [
            'status' => $status,
            'status_text' => $this->resolveUserExpressStatusText($status),
            'status_desc' => $this->resolveUserExpressStatusDesc($status, $traces),
            'official_phone' => '',
            'receiver' => [
                'name' => (string)($orderData['real_name'] ?? ''),
                'phone' => $this->maskExpressPhone((string)($orderData['user_phone'] ?? '')),
                'address' => $address,
            ],
            'trace_source' => $traceSource,
            'traces' => $traces,
        ]);
    }

    /**
     * 查询并标准化订单物流轨迹。
     *
     * @param array $orderData 订单数据。
     * @return array 标准物流轨迹。
     * @throws AdminResponseException
     */
    protected function queryOrderExpressTrace(array $orderData): array
    {
        $expressNo = trim((string)($orderData['delivery_id'] ?? ''));
        $expressCode = strtoupper(trim((string)($orderData['delivery_code'] ?? '')));
        if ($expressNo === '' || $expressCode === '') {
            throw new AdminResponseException('订单暂无物流信息');
        }

        try {
            $result = app()->make(ExpressService::class)->query(
                $expressNo,
                $expressCode,
                trim((string)($orderData['user_phone'] ?? ''))
            );
        } catch (ExpressException $e) {
            throw new AdminResponseException($e->getMessage());
        }

        return [
            'order_id' => (int)($orderData['id'] ?? 0),
            'order_sn' => (string)($orderData['order_sn'] ?? ''),
            'express_name' => (string)($orderData['delivery_name'] ?? ''),
            'express_no' => $expressNo,
            'express_code' => $expressCode,
            'provider' => (string)($result['provider'] ?? ''),
            'state' => (string)($result['state'] ?? ''),
            'message' => (string)($result['message'] ?? ''),
            'cached' => (bool)($result['cached'] ?? false),
            'buffer_seconds' => (int)($result['buffer_seconds'] ?? 0),
            'traces' => $this->formatExpressTraces($result['traces'] ?? []),
        ];
    }

    /**
     * 为移动端物流时间线补充状态文案。
     *
     * @param array $traces 标准物流轨迹。
     * @return array 移动端轨迹列表。
     */
    protected function decorateUserExpressTraces(array $traces): array
    {
        $list = [];
        foreach (array_values($traces) as $index => $trace) {
            if (!is_array($trace)) {
                continue;
            }
            $content = trim((string)($trace['content'] ?? ''));
            $list[] = array_merge($trace, [
                'status_text' => $this->resolveExpressTraceStatusText($content),
                'is_current' => $index === 0,
            ]);
        }

        return $list;
    }

    /**
     * 第三方暂无轨迹时，使用订单真实时间生成基础节点。
     *
     * @param array $orderData 订单数据。
     * @return array 基础轨迹列表。
     */
    protected function buildOrderExpressFallbackTraces(array $orderData): array
    {
        $list = [];
        $deliveryTime = trim((string)($orderData['delivery_time'] ?? ''));
        if ($deliveryTime !== '') {
            $list[] = [
                'row_key' => 'order-delivery',
                'time' => $deliveryTime,
                'content' => '商家已发货，等待快递公司更新物流信息',
                'location' => '',
                'status_text' => '已发出',
                'is_current' => true,
            ];
        }

        $createTime = trim((string)($orderData['create_time'] ?? ''));
        if ($createTime !== '') {
            $list[] = [
                'row_key' => 'order-created',
                'time' => $createTime,
                'content' => '订单已生成',
                'location' => '',
                'status_text' => '已下单',
                'is_current' => $list === [],
            ];
        }

        return $list;
    }

    /**
     * 解析物流轨迹节点状态文案。
     *
     * @param string $content 轨迹内容。
     * @return string 状态文案。
     */
    protected function resolveExpressTraceStatusText(string $content): string
    {
        if (preg_match('/签收|已取件|妥投/u', $content)) {
            return '已签收';
        }
        if (preg_match('/派送|派件/u', $content)) {
            return '派送中';
        }
        if (preg_match('/到达|到件|转运中心/u', $content)) {
            return '已到达';
        }
        if (preg_match('/揽收|揽件|收件/u', $content)) {
            return '已揽件';
        }
        if (preg_match('/发出|发往|运输/u', $content)) {
            return '运输中';
        }

        return '物流更新';
    }

    /**
     * 解析移动端物流状态。
     *
     * @param array $orderData 订单数据。
     * @return string 状态标识。
     */
    protected function resolveUserExpressStatus(array $orderData): string
    {
        if (!empty($orderData['receive_time']) || in_array((int)($orderData['status'] ?? 0), [Order::STATUS_COMPLETED, Order::STATUS_WAIT_REVIEW], true)) {
            return 'delivered';
        }
        if (!empty($orderData['delivery_time'])) {
            return 'in_transit';
        }

        return 'pending_pickup';
    }

    /**
     * 获取移动端物流状态文案。
     *
     * @param string $status 状态标识。
     * @return string 状态文案。
     */
    protected function resolveUserExpressStatusText(string $status): string
    {
        return match ($status) {
            'delivered' => '已签收',
            'in_transit' => '运输中',
            default => '待揽收',
        };
    }

    /**
     * 获取移动端物流状态说明。
     *
     * @param string $status 状态标识。
     * @param array $traces 物流轨迹。
     * @return string 状态说明。
     */
    protected function resolveUserExpressStatusDesc(string $status, array $traces): string
    {
        if ($status === 'delivered') {
            return '商品已送达';
        }
        if ($status === 'pending_pickup') {
            return '等待快递公司揽收';
        }

        $latestContent = trim((string)($traces[0]['content'] ?? ''));
        return $latestContent !== '' ? $latestContent : '物流信息持续更新中';
    }

    /**
     * 脱敏物流页收货手机号。
     *
     * @param string $phone 手机号。
     * @return string 脱敏手机号。
     */
    protected function maskExpressPhone(string $phone): string
    {
        $phone = trim($phone);
        if (strlen($phone) < 7) {
            return $phone;
        }

        return substr($phone, 0, 3) . ' **** ' . substr($phone, -4);
    }

    /**
     * 提交订单物流信息
     *
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @param int $orderId 当前业务订单的内部编号。
     * @param array $data 待保存的业务字段。
     * @param bool $allowDeliveredUpdate 是否允许修改已经发货订单的物流资料。
     * @return void 无返回值；更新订单的快递公司、单号及发货时间。
     * @throws AdminResponseException
     */
    protected function submitDelivery(array $order, int $orderId, array $data, bool $allowDeliveredUpdate = false): void
    {
        if ((int)$order['order_type'] !== Order::ORDER_TYPE_GOODS) {
            throw new AdminResponseException('当前订单不需要发货');
        }
        $goodsList = app()->make(OrderGoodsService::class)->getByOrderId($orderId);
        if (!$this->hasPhysicalOrderGoods($this->normalizeOrderGoodsList($goodsList))) {
            throw new AdminResponseException('当前订单不需要发货');
        }
        if ((int)$order['status'] !== Order::STATUS_PAID) {
            throw new AdminResponseException('订单状态不正确，无法发货');
        }
        $hasDelivered = !empty($order['delivery_time']);
        if ($hasDelivered && !$allowDeliveredUpdate) {
            throw new AdminResponseException('订单已发货');
        }

        $deliveryCode = strtoupper(trim((string)($data['express_code'] ?? $data['delivery_code'] ?? '')));
        $deliveryId = trim((string)($data['express_sn'] ?? $data['delivery_id'] ?? ''));
        if ($deliveryId === '') {
            throw new AdminResponseException('请填写物流单号');
        }
        $expressCompany = app()->make(ExpressCompanyService::class)->findEnabledByCode($deliveryCode);

        $updateData = [
            'delivery_name' => (string)$expressCompany['name'],
            'delivery_id' => $deliveryId,
            'delivery_code' => (string)$expressCompany['code'],
        ];
        if (!$hasDelivered) {
            $updateData['delivery_time'] = date('Y-m-d H:i:s');
        }

        $this->update($orderId, $updateData);
    }

    /**
     * 格式化物流轨迹。
     *
     * @param array $traces 第三方或宿主生成的物流轨迹列表。
     * @return array 字段及时间统一后的物流轨迹列表。
     */
    protected function formatExpressTraces(array $traces): array
    {
        $list = [];
        foreach ($traces as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $time = $this->normalizeExpressTraceTime(trim((string)($item['time'] ?? $item['ftime'] ?? $item['acceptTime'] ?? $item['AcceptTime'] ?? $item['date'] ?? $item['datetime'] ?? $item['createTime'] ?? '')));
            $content = trim((string)($item['context'] ?? $item['status'] ?? $item['acceptStation'] ?? $item['AcceptStation'] ?? $item['desc'] ?? $item['description'] ?? $item['remark'] ?? $item['message'] ?? $item['content'] ?? ''));
            if ($time === '' && $content === '') {
                continue;
            }
            $location = trim((string)($item['location'] ?? $item['areaName'] ?? $item['area'] ?? $item['city'] ?? ''));
            $list[] = [
                'row_key' => (string)($item['id'] ?? $index),
                'time' => $time,
                'content' => $content,
                'location' => $location,
            ];
        }

        return $list;
    }

    /**
     * 标准化物流轨迹时间。
     *
     * @param string $time 待标准化的业务时间或物流时间值。
     * @return string 兼容秒、毫秒时间戳后的标准轨迹时间。
     */
    protected function normalizeExpressTraceTime(string $time): string
    {
        if (preg_match('/^\d{13}$/', $time)) {
            return date('Y-m-d H:i:s', (int)floor(((int)$time) / 1000));
        }
        if (preg_match('/^\d{10}$/', $time)) {
            return date('Y-m-d H:i:s', (int)$time);
        }

        return $time;
    }

    /**
     * 申请退款
     *
     * @param int $merchantId 操作或结算所属商户编号，零表示平台。
     * @param int $orderId 当前业务订单的内部编号。
     * @param array $data 待保存的业务字段。
     * @return void 无返回值；由商户登记当前订单退款申请。
     * @throws AdminResponseException
     */
    public function refundApply(int $merchantId, int $orderId, array $data): void
    {
        $this->transaction(function () use ($merchantId, $orderId, $data): void {
            $order = $this->loadOrderForUpdate($orderId);
            $orderData = $order->toArray();
            if ((int)$orderData['merchant_id'] !== $merchantId) {
                throw new AdminResponseException('无权操作该订单');
            }
            if (!$this->isRefundableStatus((int)$orderData['status'])) {
                throw new AdminResponseException('订单状态不正确，无法申请退款');
            }

            $items = is_array($data['items'] ?? null) ? $data['items'] : [];
            $refundReason = trim((string)($data['refund_reason'] ?? ''));
            $refundPreview = app()->make(StoreRefundProductService::class)
                ->previewRefundProducts($orderData, $items, (float)($data['refund_amount'] ?? 0));
            $refundAmount = (float)($refundPreview['refund_amount'] ?? 0);

            $order->save([
                'status' => Order::STATUS_REFUNDING,
                'refund_status' => 1,
                'refund_amount' => $refundAmount,
                'refund_reason' => $refundReason,
                'refund_application_time' => date('Y-m-d H:i:s'),
            ]);

            $refundRecord = app()->make(OrderRefundService::class)->createOrUpdatePending(
                array_merge($orderData, [
                    'refund_amount' => $refundAmount,
                    'refund_reason' => $refundReason,
                ]),
                $refundAmount,
                $refundReason,
                'merchant_apply'
            );
            app()->make(StoreRefundProductService::class)->createOrUpdateForRefund(
                $orderData,
                $refundRecord,
                $items,
                $refundAmount
            );
        });
    }

    /**
     * 商户通过退款申请。
     *
     * @param int $merchantId 操作或结算所属商户编号，零表示平台。
     * @param int $orderId 当前业务订单的内部编号。
     * @return void 无返回值；校验商户归属后审核并执行统一退款流程。
     * @throws AdminResponseException
     */
    public function merchantApproveRefund(int $merchantId, int $orderId): void
    {
        $this->assertMerchantPendingRefundOrder($merchantId, $orderId);

        app()->make(OrderPayService::class)->approveRefund($orderId);
    }

    /**
     * 商户拒绝退款申请。
     *
     * @param int $merchantId 操作或结算所属商户编号，零表示平台。
     * @param int $orderId 当前业务订单的内部编号。
     * @param string $reason 当前业务操作原因。
     * @return void 无返回值；校验商户归属后拒绝当前退款申请。
     * @throws AdminResponseException
     */
    public function merchantRejectRefund(int $merchantId, int $orderId, string $reason = ''): void
    {
        $this->assertMerchantPendingRefundOrder($merchantId, $orderId);

        $this->adminRejectRefund($orderId, $reason);
    }

    /**
     * 获取商户订单统计
     *
     * @param int $merchantId 操作或结算所属商户编号，零表示平台。
     * @param array $params 已校验的列表筛选或业务操作参数。
     * @return array 当前商户订单数量、已付数量、退款数量及支付金额汇总。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getMerchantOrderStatistics(int $merchantId, array $params = []): array
    {
        $query = $this->buildMerchantOrderQuery($merchantId, $params, false);
        $total = (int) (clone $query)->count();
        $paidCount = (int) (clone $query)->whereIn('status', $this->paidBusinessStatuses())->count();
        $refundingCount = (int) (clone $query)->where(function ($subQuery) {
            $subQuery->where('refund_status', 1)
                ->whereOr('status', Order::STATUS_REFUNDING);
        })->count();
        $paidAmount = $this->normalizeMoney((clone $query)->sum('pay_amount'));

        return [
            'total' => $total,
            'paidCount' => $paidCount,
            'refundingCount' => $refundingCount,
            'paidAmount' => $paidAmount,
        ];
    }

    /**
     * 获取商户退款统计
     *
     * @param int $merchantId 操作或结算所属商户编号，零表示平台。
     * @param array $params 已校验的列表筛选或业务操作参数。
     * @return array 当前商户退款总量、待处理量、成功量及成功退款金额。
     */
    public function getMerchantRefundStatistics(int $merchantId, array $params = []): array
    {
        $query = $this->buildMerchantOrderQuery($merchantId, $params, true);
        $total = (int) (clone $query)->count();
        $pending = (int) (clone $query)->where(function ($subQuery) {
            $subQuery->where('refund_status', 1)
                ->whereOr('status', Order::STATUS_REFUNDING);
        })->count();
        $success = (int) (clone $query)->where(function ($subQuery) {
            $subQuery->whereIn('refund_status', [2, 3])
                ->whereOr('status', Order::STATUS_REFUNDED);
        })->count();

        $amountList = (clone $query)
            ->field(['refund_amount', 'pay_amount'])
            ->select()
            ->toArray();

        $totalRefundAmount = 0.0;
        foreach ($amountList as $item) {
            $refundAmount = (float) ($item['refund_amount'] ?? 0);
            $payAmount = (float) ($item['pay_amount'] ?? 0);
            $totalRefundAmount = $this->moneyAdd($totalRefundAmount, $refundAmount > 0 ? $refundAmount : $payAmount);
        }

        return [
            'total' => $total,
            'pending' => $pending,
            'success' => $success,
            'totalRefundAmount' => $this->normalizeMoney($totalRefundAmount),
        ];
    }

    /**
     * 获取用户已支付订单标识列表
     *
     * @param int $userId 当前业务用户编号。
     * @return array 当前用户支付成功业务订单的去重内部编号。
     */
    public function getPaidOrderIdsByUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $orderIds = $this->getModel()
            ->where('user_id', $userId)
            ->where('order_role', '<>', Order::ROLE_MAIN)
            ->whereIn('status', $this->paidBusinessStatuses())
            ->where('refund_status', 0)
            ->column('id');

        return array_values(array_unique(array_filter(array_map('intval', $orderIds))));
    }

    /**
     * 自动取消超时未支付订单。
     *
     * @return array<string, int> 本批超时订单的扫描、取消、未取消及失败统计。
     */
    public function cancelExpiredPendingOrders(): array
    {
        $minutes = max(1, (int) db_config(OrderConfigKeyEnum::UNPAID_CANCEL_MINUTES, 30));
        $limit = max(1, min(500, (int) db_config(OrderConfigKeyEnum::UNPAID_CANCEL_BATCH_LIMIT, 50)));
        $deadline = date('Y-m-d H:i:s', time() - $minutes * 60);

        $orderIds = $this->getModel()
            ->where('status', Order::STATUS_PENDING)
            ->whereIn('order_role', [Order::ROLE_NORMAL, Order::ROLE_MAIN])
            ->where(function ($query) use ($deadline) {
                $query->where(function ($frozen) {
                    $frozen->whereNotNull('pay_expire_time')->where('pay_expire_time', '<=', date('Y-m-d H:i:s'));
                })->whereOr(function ($legacy) use ($deadline) {
                    $legacy->whereNull('pay_expire_time')->whereTime('create_time', '<=', $deadline);
                });
            })
            ->order('id asc')
            ->limit($limit)
            ->column('id');

        $result = [
            'scanned' => count($orderIds),
            'success' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        foreach ($orderIds as $orderId) {
            $cancelled = $this->cancelExpiredPendingOrder((int) $orderId);
            if ($cancelled === true) {
                $result['success']++;
            } elseif ($cancelled === false) {
                $result['skipped']++;
            } else {
                $result['failed']++;
            }
        }

        return $result;
    }

    /**
     * 释放到期冻结佣金。
     *
     * 商品订单按确认收货时间起算，内容/专题订单按支付时间起算。
     *
     * @return array<string, int> 本批到期佣金的扫描、发放及失败统计。
     */
    public function releaseDueFrozenCommissions(): array
    {
        $freezeDays = max(0, (int) db_config(ConfigKeyEnum::COMMISSION_FREEZE_DAYS, 0));
        $limit = 50;
        $deadline = date('Y-m-d H:i:s', time() - $freezeDays * 86400);

        $orderIds = $this->getModel()
            ->where('commission_status', Order::COMMISSION_STATUS_FROZEN)
            ->where('order_role', '<>', Order::ROLE_MAIN)
            ->where('refund_status', 0)
            ->whereIn('status', [Order::STATUS_PAID, Order::STATUS_COMPLETED])
            ->where(function ($query) use ($deadline) {
                $query->where(function ($goodsQuery) use ($deadline) {
                    $goodsQuery->where('order_type', Order::ORDER_TYPE_GOODS)
                        ->whereNotNull('receive_time')
                        ->where('receive_time', '<=', $deadline);
                })->whereOr(function ($virtualQuery) use ($deadline) {
                    $virtualQuery->whereIn('order_type', [Order::ORDER_TYPE_CONTENT, Order::ORDER_TYPE_SPECIAL])
                        ->whereNotNull('pay_time')
                        ->where('pay_time', '<=', $deadline);
                });
            })
            ->order('id asc')
            ->limit($limit)
            ->column('id');

        $result = [
            'scanned' => count($orderIds),
            'success' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        foreach ($orderIds as $orderId) {
            try {
                $this->issueOrderCommission((int) $orderId);
                $result['success']++;
            } catch (\Throwable $throwable) {
                $result['failed']++;
                trace('release due frozen commission failed: ' . json_encode([
                    'order_id' => (int) $orderId,
                    'message' => $throwable->getMessage(),
                ], JSON_UNESCAPED_UNICODE), 'error');
            }
        }

        return $result;
    }

    /**
     * 释放到期商户冻结资金。
     *
     * @return array<string, int> 本批到期商户冻结资金的处理统计。
     */
    public function releaseDueFrozenMerchantFunds(): array
    {
        return app()->make(OrderMerchantSettlementService::class)->releaseDueFrozenFunds(100);
    }

    /**
     * 释放到期冻结赠送积分。
     *
     * @return array<string, int> 本批到期赠送积分的处理统计。
     */
    public function releaseDueGiftPoints(): array
    {
        return app()->make(OrderGiftPointsRecordService::class)->releaseDue(100);
    }

    /**
     * 取消单个超时未支付订单。
     *
     * @param int $orderId 订单标识。
     * @return bool|null true=已取消，false=跳过，null=失败。
     */
    protected function cancelExpiredPendingOrder(int $orderId): ?bool
    {
        try {
            return $this->transaction(function () use ($orderId) {
                $order = $this->loadOrderForUpdate($orderId);
                $orderData = $order->toArray();
                if ((int) ($orderData['status'] ?? Order::STATUS_PENDING) !== Order::STATUS_PENDING) {
                    return false;
                }
                if (app()->make(OrderPaymentDeadlineService::class)->deadline($orderData) > time()) {
                    return false;
                }

                $this->cancelPendingOrder($order, '超时未支付自动取消');
                // 支付渠道查单后的结算可能在同一锁内更新另一个数据模型实例，因此需要重新查询状态。
                $fresh = $this->get($orderId, ['id', 'status']);
                $cancelled = $fresh && (int) $fresh['status'] === Order::STATUS_CANCELLED;
                if ($cancelled) {
                    trace('expired pending order cancelled: ' . json_encode([
                        'order_id' => $orderId,
                        'order_sn' => (string) ($orderData['order_sn'] ?? ''),
                    ], JSON_UNESCAPED_UNICODE), 'info');
                }

                return $cancelled;
            });
        } catch (\Throwable $e) {
            trace('expired pending order cancel failed: ' . json_encode([
                'order_id' => $orderId,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE), 'error');

            return null;
        }
    }

    /**
     * 获取订单商品映射
     *
     * @param array $orderIds 待批量查询的业务订单内部编号列表。
     * @return array 以订单内部编号为键的商品成交明细列表。
     */
    protected function getOrderGoodsMap(array $orderIds): array
    {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
        if ($orderIds === []) {
            return [];
        }

        $goodsList = app()->make(OrderGoodsService::class)->getModel()
            ->whereIn('order_id', $orderIds)
            ->order('id asc')
            ->select()
            ->toArray();

        $result = [];
        foreach ($goodsList as $goods) {
            $result[(int)($goods['order_id'] ?? 0)][] = $goods;
        }

        return $result;
    }

    /**
     * 构建主支付单子订单列表。
     *
     * 用户端支付后查看主单时需要返回聚合的 children（子单）数据；商户端/后台列表默认展示子单，
     * 因此这里统一复用普通订单列表结构，避免主单详情和子单列表出现字段差异。
     *
     * @param int $mainOrderId 合并支付主订单的内部编号。
     * @return array 主支付单下规范化的业务子订单展示列表。
     */
    protected function buildOrderChildrenPayload(int $mainOrderId): array
    {
        if ($mainOrderId <= 0) {
            return [];
        }

        $children = $this->getModel()
            ->where('pid', $mainOrderId)
            ->where('order_role', Order::ROLE_CHILD)
            ->order('id asc')
            ->select()
            ->toArray();
        if ($children === []) {
            return [];
        }

        $orderIds = array_values(array_unique(array_filter(array_map('intval', array_column($children, 'id')))));
        $goodsMap = $this->getOrderGoodsMap($orderIds);
        $result = [];

        foreach ($children as $child) {
            $result[] = $this->buildUserOrderListItem($child, $goodsMap[(int)($child['id'] ?? 0)] ?? []);
        }

        return $result;
    }

    /**
     * 获取商户订单分页结果
     *
     * @param int $merchantId 操作或结算所属商户编号，零表示平台。
     * @param array $params 已校验的列表筛选或业务操作参数。
     * @param bool $refundOnly 是否仅查询具有退款状态的订单。
     * @return array 当前商户订单或退款订单的规范分页及展示数据。
     */
    protected function getMerchantPagedResult(int $merchantId, array $params = [], bool $refundOnly = false): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = max(1, min(100, (int) ($params['limit'] ?? 20)));
        $query = $this->buildMerchantOrderQuery($merchantId, $params, $refundOnly);
        $count = (int) (clone $query)->count();
        $sort = $refundOnly ? 'refund_application_time desc, create_time desc' : 'create_time desc';
        $orderList = $query->orderRaw($sort)->page($page, $limit)->select()->toArray();

        if ($orderList === []) {
            return [
                'list' => [],
                'total' => $count,
                'page' => $page,
                'limit' => $limit,
                'totalPage' => $limit > 0 ? (int) ceil($count / $limit) : 0,
            ];
        }

        $orderIds = array_values(array_unique(array_filter(array_map('intval', array_column($orderList, 'id')))));
        $userIds = array_values(array_unique(array_filter(array_map('intval', array_column($orderList, 'user_id')))));
        $merchantIds = array_values(array_unique(array_filter(array_map('intval', array_column($orderList, 'merchant_id')))));
        $goodsMap = $this->getOrderGoodsMap($orderIds);
        $userMap = $this->getUserMap($userIds);
        $merchantMap = $this->getMerchantMap($merchantIds);
        $list = [];

        foreach ($orderList as $order) {
            $orderId = (int) ($order['id'] ?? 0);
            $userId = (int) ($order['user_id'] ?? 0);
            $merchantId = (int) ($order['merchant_id'] ?? 0);
            $list[] = $this->buildMerchantOrderListItem(
                $order,
                $goodsMap[$orderId] ?? [],
                $userMap[$userId] ?? [],
                $merchantMap[$merchantId] ?? []
            );
        }

        return [
            'list' => $list,
            'total' => $count,
            'page' => $page,
            'limit' => $limit,
            'totalPage' => $limit > 0 ? (int) ceil($count / $limit) : 0,
        ];
    }

    /**
     * 构建商户订单查询
     *
     * @param int $merchantId 操作或结算所属商户编号，零表示平台。
     * @param array $params 已校验的列表筛选或业务操作参数。
     * @param bool $refundOnly 是否仅查询具有退款状态的订单。
     * @return \think\db\Query 已限定商户归属、订单角色和筛选条件的订单查询。
     */
    protected function buildMerchantOrderQuery(int $merchantId, array $params = [], bool $refundOnly = false)
    {
        $query = $this->getModel()
            ->where('merchant_id', $merchantId)
            ->where('order_role', '<>', Order::ROLE_MAIN);
        $orderSn = trim((string) ($params['order_sn'] ?? ''));
        $userKeyword = trim((string) ($params['user_keyword'] ?? ''));
        $goodsName = trim((string) ($params['goods_name'] ?? ''));
        $orderType = trim((string) ($params['order_type'] ?? ''));
        $status = trim((string) ($params['status'] ?? ''));
        $payType = trim((string) ($params['pay_type'] ?? ''));
        $refundStatus = trim((string) ($params['refund_status'] ?? ''));
        $startTime = trim((string) ($params['start_time'] ?? ''));
        $endTime = trim((string) ($params['end_time'] ?? ''));

        if ($orderSn !== '') {
            $query->where('order_sn', 'like', '%' . $orderSn . '%');
        }
        if ($orderType !== '') {
            $query->where('order_type', (int) $orderType);
        }
        if ($status !== '') {
            $query->where('status', (int) $status);
        }
        if ($payType !== '') {
            $query->where('pay_type', $payType);
        }
        if ($refundStatus !== '') {
            $query->where('refund_status', (int) $refundStatus);
        }
        if ($refundOnly) {
            $query->where(function ($subQuery) {
                $subQuery->where('refund_status', '>', 0)
                    ->whereOr('status', Order::STATUS_REFUNDING)
                    ->whereOr('status', Order::STATUS_REFUNDED);
            });
        }
        if ($userKeyword !== '') {
            $this->applyMerchantUserKeywordFilter($query, $userKeyword);
        }
        if ($goodsName !== '') {
            $orderIds = $this->findMerchantOrderIdsByGoodsName($merchantId, $goodsName);
            if ($orderIds === []) {
                $query->where('id', 0);
            } else {
                $query->whereIn('id', $orderIds);
            }
        }

        $timeField = $refundOnly ? 'refund_application_time' : 'create_time';
        if ($startTime !== '' && $endTime !== '') {
            $query->whereBetweenTime($timeField, $startTime . ' 00:00:00', $endTime . ' 23:59:59');
        } elseif ($startTime !== '') {
            $query->where($timeField, '>=', $startTime . ' 00:00:00');
        } elseif ($endTime !== '') {
            $query->where($timeField, '<=', $endTime . ' 23:59:59');
        }

        return $query;
    }

    /**
     * 应用商户订单用户关键字筛选
     *
     * @param mixed $query 需要追加筛选条件的查询对象。
     * @param string $userKeyword 用户昵称、手机号或编号的检索关键词。
     * @return void 无返回值；追加匹配用户身份的商户订单查询条件。
     */
    protected function applyMerchantUserKeywordFilter($query, string $userKeyword): void
    {
        $userIds = $this->findUserIdsByKeyword($userKeyword);
        $exactUserId = ctype_digit($userKeyword) ? (int) $userKeyword : 0;

        $query->where(function ($subQuery) use ($userIds, $exactUserId, $userKeyword) {
            $hasCondition = false;

            if ($userIds !== []) {
                $subQuery->whereIn('user_id', $userIds);
                $hasCondition = true;
            }

            if ($exactUserId > 0) {
                if ($hasCondition) {
                    $subQuery->whereOr('user_id', $exactUserId);
                } else {
                    $subQuery->where('user_id', $exactUserId);
                    $hasCondition = true;
                }
            }

            if ($hasCondition) {
                $subQuery->whereOr('user_phone', 'like', '%' . $userKeyword . '%');
            } else {
                $subQuery->where('user_phone', 'like', '%' . $userKeyword . '%');
            }
        });
    }

    /**
     * 获取商品名称命中的订单标识
     *
     * @param int $merchantId 操作或结算所属商户编号，零表示平台。
     * @param string $goodsName 订单商品名称的检索关键词。
     * @return array 当前商户商品名称命中的去重订单编号。
     */
    protected function findMerchantOrderIdsByGoodsName(int $merchantId, string $goodsName): array
    {
        if ($goodsName === '') {
            return [];
        }

        $orderIds = app()->make(OrderGoodsService::class)->getModel()
            ->where('merchant_id', $merchantId)
            ->where('goods_name', 'like', '%' . $goodsName . '%')
            ->column('order_id');

        return array_values(array_unique(array_filter(array_map('intval', $orderIds))));
    }

    /**
     * 获取后台订单商品名称命中的订单标识。
     *
     * @param string $goodsName 订单商品名称的检索关键词。
     * @param int $merchantId 操作或结算所属商户编号，零表示平台。
     * @return array 平台商品名称及可选商户筛选命中的去重订单编号。
     */
    protected function findAdminOrderIdsByGoodsName(string $goodsName, int $merchantId = 0): array
    {
        if ($goodsName === '') {
            return [];
        }

        $query = app()->make(OrderGoodsService::class)->getModel()
            ->where('goods_name', 'like', '%' . $goodsName . '%');
        if ($merchantId > 0) {
            $query->where('merchant_id', $merchantId);
        }

        $orderIds = $query->column('order_id');

        return array_values(array_unique(array_filter(array_map('intval', $orderIds))));
    }

    /**
     * 根据关键字获取用户标识列表
     *
     * @param string $userKeyword 用户昵称、手机号或编号的检索关键词。
     * @return array 用户昵称、手机号或编号命中的去重用户编号。
     */
    protected function findUserIdsByKeyword(string $userKeyword): array
    {
        if ($userKeyword === '') {
            return [];
        }

        $query = app()->make(UserService::class)->getModel()->field(['uid']);
        $query->where(function ($subQuery) use ($userKeyword) {
            $subQuery->where('nickname', 'like', '%' . $userKeyword . '%')
                ->whereOr('phone', 'like', '%' . $userKeyword . '%');

            if (ctype_digit($userKeyword)) {
                $subQuery->whereOr('uid', (int) $userKeyword);
            }
        });

        $userIds = $query->column('uid');

        return array_values(array_unique(array_filter(array_map('intval', $userIds))));
    }

    /**
     * 获取用户映射
     *
     * @param array $userIds 待批量加载的用户编号列表。
     * @return array 以用户编号为键的订单展示资料。
     */
    protected function getUserMap(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if ($userIds === []) {
            return [];
        }

        $userList = app()->make(UserService::class)->getModel()
            ->whereIn('uid', $userIds)
            ->field(['uid', 'nickname', 'phone'])
            ->select()
            ->toArray();

        $result = [];
        foreach ($userList as $item) {
            $result[(int) ($item['uid'] ?? 0)] = $item;
        }

        return $result;
    }

    /**
     * 获取商户展示信息映射。
     *
     * @param array $merchantIds 参与结算的商户编号列表。
     * @return array 以商户编号为键的安全店铺展示资料。
     */
    protected function getMerchantMap(array $merchantIds): array
    {
        return app()->make(MerchantService::class)->getOrderDisplayMapByIds($merchantIds);
    }

    /**
     * 应用用户订单角色过滤。
     *
     * @param mixed $query 需要追加筛选条件的查询对象。
     * @param int $status 当前业务状态或列表状态筛选值。
     * @return void 无返回值；根据用户状态页追加主支付单或业务子单范围。
     */
    protected function applyUserOrderRoleFilter($query, int $status): void
    {
        if ($status === Order::STATUS_PENDING) {
            $query->whereIn('order_role', [Order::ROLE_NORMAL, Order::ROLE_MAIN]);
            return;
        }

        $query->where('order_role', '<>', Order::ROLE_MAIN);
    }

    /**
     * 获取商户订单模型
     *
     * @param int $merchantId 操作或结算所属商户编号，零表示平台。
     * @param int $orderId 当前业务订单的内部编号。
     * @return Model 已校验商户归属的订单模型。
     * @throws AdminResponseException
     */
    protected function getMerchantOrderModel(int $merchantId, int $orderId): Model
    {
        $order = $this->get($orderId);
        if (!$order) {
            throw new AdminResponseException('订单不存在');
        }
        if ((int) ($order['merchant_id'] ?? 0) !== $merchantId) {
            throw new AdminResponseException('无权查看该订单');
        }

        return $order;
    }

    /**
     * 校验商户是否可处理当前退款申请。
     *
     * 普通未拆单订单的 pid 为 0，应允许处理；只禁止合单主支付单直接审核退款。
     *
     * @param int $merchantId 操作或结算所属商户编号，零表示平台。
     * @param int $orderId 当前业务订单的内部编号。
     * @return void 无返回值；商户无权处理或直接处理主支付单时抛出异常。
     * @throws AdminResponseException
     */
    protected function assertMerchantPendingRefundOrder(int $merchantId, int $orderId): void
    {
        $order = $this->get($orderId);
        if (!$order) {
            throw new AdminResponseException('订单不存在');
        }

        $orderData = $order->toArray();
        if ((int)($orderData['merchant_id'] ?? 0) !== $merchantId) {
            throw new AdminResponseException('无权操作该订单');
        }

        if ((int)($orderData['order_role'] ?? Order::ROLE_NORMAL) === Order::ROLE_MAIN) {
            throw new AdminResponseException('主支付单请在子订单中处理退款');
        }

        $status = (int)($orderData['status'] ?? Order::STATUS_PENDING);
        $refundStatus = (int)($orderData['refund_status'] ?? 0);
        if ($refundStatus !== 1 && $status !== Order::STATUS_REFUNDING) {
            throw new AdminResponseException('当前订单没有待处理退款申请');
        }
    }

    /**
     * 构建商户订单列表项
     *
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @param array $goodsList 当前业务订单的商品成交明细列表。
     * @param array $user 已加载的用户或推广人展示资料。
     * @param array $merchant 已加载的商户店铺展示资料。
     * @return array 当前商户列表需要的订单摘要和操作状态。
     */
    protected function buildMerchantOrderListItem(
        array $order,
        array $goodsList = [],
        array $user = [],
        array $merchant = []
    ): array
    {
        return $this->buildMerchantOrderPayload($order, $goodsList, $user, false, $merchant);
    }

    /**
     * 构建商户订单详情项
     *
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @param array $goodsList 当前业务订单的商品成交明细列表。
     * @param array $user 已加载的用户或推广人展示资料。
     * @param array $merchant 已加载的商户店铺展示资料。
     * @return array 当前商户详情需要的订单、商品、用户及佣金资料。
     */
    protected function buildMerchantOrderDetailItem(
        array $order,
        array $goodsList = [],
        array $user = [],
        array $merchant = []
    ): array
    {
        return $this->appendMerchantOrderCommissionInfo(
            $this->buildMerchantOrderPayload($order, $goodsList, $user, true, $merchant),
            $order
        );
    }

    /**
     * 构建商户订单输出
     *
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @param array $goodsList 当前业务订单的商品成交明细列表。
     * @param array $user 已加载的用户或推广人展示资料。
     * @param bool $includeGoods 是否在输出结构中携带完整商品明细。
     * @param array $merchant 已加载的商户店铺展示资料。
     * @return array 统一的商户订单展示结构及可选商品明细。
     */
    protected function buildMerchantOrderPayload(
        array $order,
        array $goodsList = [],
        array $user = [],
        bool $includeGoods = true,
        array $merchant = []
    ): array
    {
        $payload = $this->buildUserOrderPayload($order, $goodsList, $includeGoods);
        $status = (int)($order['status'] ?? Order::STATUS_PENDING);
        $refundStatus = (int)($order['refund_status'] ?? 0);
        $normalizedGoods = $this->normalizeOrderGoodsList($goodsList);
        $merchantId = (int)($order['merchant_id'] ?? 0);
        $merchantInfo = $this->formatOrderMerchantInfo($merchantId, $merchant);
        $normalizedGoods = $this->appendMerchantInfoToGoods($normalizedGoods, $merchantInfo);
        $userId = (int) ($order['user_id'] ?? 0);
        $nickname = trim((string) ($user['nickname'] ?? ''));
        $merchantName = $this->resolveOrderMerchantName($order, $normalizedGoods, $merchantInfo);
        $merchantInfo['mer_name'] = $merchantName;
        $merchantInfo['name'] = $merchantName;

        return array_merge($order, $payload, [
            'id' => (int) ($order['id'] ?? 0),
            'order_id' => (int) ($order['id'] ?? 0),
            'user_id' => $userId,
            'merchant_id' => $merchantId,
            'merchant_name' => $merchantName,
            'merchant' => $merchantInfo,
            'nickname' => $nickname !== '' ? $nickname : '用户#' . $userId,
            'user_phone' => (string) ($order['user_phone'] ?? ($user['phone'] ?? '')),
            'goods_summary' => $this->resolveGoodsSummary($normalizedGoods),
            'address' => $this->resolveOrderAddressText($order),
            'refund_application_time' => $order['refund_application_time'] ?? null,
            'refund_reason_time' => $order['refund_reason_time'] ?? null,
            'delivery_name' => (string) ($order['delivery_name'] ?? ''),
            'delivery_id' => (string) ($order['delivery_id'] ?? ''),
            'delivery_code' => (string) ($order['delivery_code'] ?? ''),
            'can_audit_refund' => $refundStatus === 1 || $status === Order::STATUS_REFUNDING,
            'goods_preview' => array_slice($normalizedGoods, 0, 2),
            'goods' => $includeGoods ? $normalizedGoods : array_slice($normalizedGoods, 0, 2),
        ]);
    }

    /**
     * 追加商户订单佣金展示信息。
     *
     * @param array $payload 待处理的插件载荷。
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @return array 追加佣金状态与推广人信息后的商户订单展示数据。
     */
    protected function appendMerchantOrderCommissionInfo(array $payload, array $order): array
    {
        $firstCommissionAmount = $this->normalizeMoney((float)($order['first_commission_amount'] ?? 0));
        $secondCommissionAmount = $this->normalizeMoney((float)($order['second_commission_amount'] ?? 0));
        $firstReferrerId = (int)($order['referrer_id'] ?? 0);
        $secondReferrerId = (int)($order['second_referrer_id'] ?? 0);
        $userMap = $this->getUserMap([$firstReferrerId, $secondReferrerId]);

        $payload['commission_amount'] = $this->moneyAdd($firstCommissionAmount, $secondCommissionAmount);
        $payload['first_commission_amount'] = $firstCommissionAmount;
        $payload['second_commission_amount'] = $secondCommissionAmount;
        $payload['commission_base_amount'] = $this->normalizeMoney((float)($order['commission_base_amount'] ?? 0));
        $payload['first_commission_rate'] = (float)($order['first_commission_rate'] ?? 0);
        $payload['second_commission_rate'] = (float)($order['second_commission_rate'] ?? 0);
        $payload['commission_level'] = (int)($order['commission_level'] ?? 0);
        $payload['commission_mode'] = (string)($order['commission_mode'] ?? '');
        $payload['platform_commission_amount'] = $this->normalizeMoney(
            (float)($order['platform_commission_amount'] ?? 0)
        );

        if ($firstReferrerId > 0) {
            $firstReferrer = $this->formatOrderReferrer($firstReferrerId, $userMap[$firstReferrerId] ?? []);
            $payload['referrer'] = $firstReferrer;
            $payload['referrer_name'] = $firstReferrer['label'];
        }

        if ($secondReferrerId > 0) {
            $secondReferrer = $this->formatOrderReferrer($secondReferrerId, $userMap[$secondReferrerId] ?? []);
            $payload['second_referrer_id'] = $secondReferrerId;
            $payload['second_referrer'] = $secondReferrer;
            $payload['second_referrer_name'] = $secondReferrer['label'];
        }

        return $payload;
    }

    /**
     * 格式化订单推广人展示信息。
     *
     * @param int $userId 当前业务用户编号。
     * @param array $user 已加载的用户或推广人展示资料。
     * @return array 推广人编号、昵称、手机号及列表标签。
     */
    protected function formatOrderReferrer(int $userId, array $user = []): array
    {
        $nickname = trim((string)($user['nickname'] ?? ''));
        $phone = trim((string)($user['phone'] ?? ''));
        $label = $nickname !== '' ? $nickname : ($phone !== '' ? $phone : '用户#' . $userId);

        return [
            'uid' => $userId,
            'nickname' => $nickname,
            'phone' => $phone,
            'label' => $label,
        ];
    }

    /**
     * 解析商品摘要
     *
     * @param array $goodsList 当前业务订单的商品成交明细列表。
     * @return string 单商品名称或多商品合并摘要。
     */
    protected function resolveGoodsSummary(array $goodsList): string
    {
        if ($goodsList === []) {
            return '-';
        }

        $goodsNames = array_values(array_filter(array_map(static function (array $goods): string {
            return trim((string) ($goods['goods_name'] ?? ''));
        }, $goodsList)));

        if ($goodsNames === []) {
            return '-';
        }

        $summary = implode('、', array_slice($goodsNames, 0, 2));
        if (count($goodsNames) > 2) {
            $summary .= ' 等' . count($goodsNames) . '件';
        }

        return $summary;
    }

    /**
     * 解析订单地址文案
     *
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @return string 当前订单完整收货地址文案。
     */
    protected function resolveOrderAddressText(array $order): string
    {
        $userAddress = trim((string) ($order['user_address'] ?? ''));
        if ($userAddress !== '') {
            return $userAddress;
        }

        $parts = array_filter([
            trim((string) ($order['province'] ?? '')),
            trim((string) ($order['city'] ?? '')),
            trim((string) ($order['district'] ?? '')),
            trim((string) ($order['detail'] ?? '')),
        ]);

        return $parts !== [] ? implode('', $parts) : '-';
    }

    /**
     * 构建商户订单时间线
     *
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @return array 按真实业务时间排序的商户订单记录。
     */
    protected function buildMerchantOrderTimeline(array $order): array
    {
        $recordList = [];
        $refundStatus = (int) ($order['refund_status'] ?? 0);
        $refundReason = trim((string) ($order['refund_reason'] ?? ''));

        $this->appendMerchantOrderRecord($recordList, '创建订单', '买家提交订单', '买家', $order['create_time'] ?? null);
        $this->appendMerchantOrderRecord($recordList, '支付完成', '订单支付成功', '买家', $order['pay_time'] ?? null);
        $this->appendMerchantOrderRecord($recordList, '商户发货', '商户已录入物流并发货', '商户', $order['delivery_time'] ?? null);
        $this->appendMerchantOrderRecord($recordList, '确认收货', '买家确认收货', '买家', $order['receive_time'] ?? null);
        $this->appendMerchantOrderRecord($recordList, '订单完成', '订单流程已完成', '系统', $order['finish_time'] ?? null);

        if (!empty($order['refund_application_time'])) {
            $content = $refundReason !== '' ? '退款原因：' . $refundReason : '商户已提交退款申请';
            $this->appendMerchantOrderRecord($recordList, '发起退款', $content, '商户', $order['refund_application_time']);
        }

        if ($refundStatus === 2) {
            $content = $refundReason !== '' ? '退款已完成，原因：' . $refundReason : '平台已完成退款处理';
            $this->appendMerchantOrderRecord(
                $recordList,
                '退款完成',
                $content,
                '平台',
                $order['refund_reason_time'] ?? ($order['update_time'] ?? null)
            );
        }

        usort($recordList, static function (array $left, array $right): int {
            return strcmp((string) ($right['create_time'] ?? ''), (string) ($left['create_time'] ?? ''));
        });

        return $recordList;
    }

    /**
     * 追加商户订单记录
     *
     * @param array $recordList 当前订单已组装的时间线记录列表。
     * @param string $title 订单时间线节点标题。
     * @param string $content 当前订单时间线节点的业务说明。
     * @param string $operator 订单时间线节点操作方名称。
     * @param mixed $time 待标准化的业务时间或物流时间值。
     * @return void 无返回值；按引用追加有效时间的真实业务节点。
     */
    protected function appendMerchantOrderRecord(
        array &$recordList,
        string $title,
        string $content,
        string $operator,
        mixed $time
    ): void {
        if (!$time) {
            return;
        }

        $recordList[] = [
            'title' => $title,
            'content' => $content,
            'operator' => $operator,
            'create_time' => $time,
        ];
    }

    /**
     * 构建用户订单列表项
     *
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @param array $goodsList 当前业务订单的商品成交明细列表。
     * @return array 当前用户订单列表需要的商品摘要及操作状态。
     */
    protected function buildUserOrderListItem(array $order, array $goodsList = []): array
    {
        return $this->buildUserOrderPayload($order, $goodsList, false);
    }

    /**
     * 构建用户订单详情项
     *
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @param array $goodsList 当前业务订单的商品成交明细列表。
     * @return array 当前用户订单详情及完整商品明细。
     */
    protected function buildUserOrderDetailItem(array $order, array $goodsList = []): array
    {
        return $this->buildUserOrderPayload($order, $goodsList, true);
    }

    /**
     * 构建用户订单输出
     *
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @param array $goodsList 当前业务订单的商品成交明细列表。
     * @param bool $includeGoods 是否在输出结构中携带完整商品明细。
     * @return array 统一的用户订单结构、角色、状态及可操作标志。
     */
    protected function buildUserOrderPayload(array $order, array $goodsList = [], bool $includeGoods = true): array
    {
        $status = (int)($order['status'] ?? Order::STATUS_PENDING);
        $refundStatus = (int)($order['refund_status'] ?? 0);
        $orderType = (int)($order['order_type'] ?? Order::ORDER_TYPE_GOODS);
        $orderRole = (int)($order['order_role'] ?? Order::ROLE_NORMAL);
        $commissionStatus = (int)($order['commission_status'] ?? Order::COMMISSION_STATUS_PENDING);
        $normalizedGoods = $this->normalizeOrderGoodsList($goodsList);
        $actions = $this->resolveOrderActions($order);
        $hasPhysicalGoods = $this->hasPhysicalOrderGoods($normalizedGoods);
        $actions['can_delivery'] = !empty($actions['can_delivery']) && $hasPhysicalGoods;
        $actions['can_receipt'] = !empty($actions['can_receipt']) && $hasPhysicalGoods;
        $normalizedGoods = $this->appendOrderGoodsActions($normalizedGoods, $order, $actions);
        $goodsTotalNum = 0;
        foreach ($normalizedGoods as $goods) {
            $goodsTotalNum += (int)($goods['num'] ?? 0);
        }

        $payload = [
            'order_id' => (int)($order['id'] ?? 0),
            'order_sn' => (string)($order['order_sn'] ?? ''),
            'pid' => (int)($order['pid'] ?? 0),
            'main_order_sn' => (string)($order['main_order_sn'] ?? ''),
            'order_role' => $orderRole,
            'merchant_id' => (int)($order['merchant_id'] ?? 0),
            'merchant_name' => $this->resolveOrderMerchantName($order, $normalizedGoods),
            'order_type' => $orderType,
            'order_types' => [$orderType],
            'order_type_text' => $this->resolveOrderTypeText($orderType),
            'status' => $status,
            'status_text' => $this->resolveOrderDisplayStatusText($order, $normalizedGoods, $actions),
            'pay_type' => (string)($order['pay_type'] ?? ''),
            'pay_type_text' => $this->resolvePayTypeText((string)($order['pay_type'] ?? '')),
            'pay_amount' => (float)($order['pay_amount'] ?? 0),
            'original_amount' => (float)($order['original_amount'] ?? 0),
            'total_amount' => (float)($order['total_amount'] ?? 0),
            'discount_amount' => (float)($order['discount_amount'] ?? 0),
            'platform_coupon_amount' => (float)($order['platform_coupon_amount'] ?? 0),
            'merchant_coupon_amount' => (float)($order['merchant_coupon_amount'] ?? 0),
            'points_deduct_amount' => (float)($order['points_deduct_amount'] ?? 0),
            'use_points' => (int)($order['use_points'] ?? 0),
            'member_discount_amount' => (float)($order['member_discount_amount'] ?? 0),
            'member_discount_rate' => (float)($order['member_discount_rate'] ?? 1.00),
            'postage' => (float)($order['postage'] ?? 0),
            'refund_status' => $refundStatus,
            'refund_status_text' => $this->resolveRefundStatusText($refundStatus),
            'refund_amount' => (float)($order['refund_amount'] ?? 0),
            'refund_reason' => (string)($order['refund_reason'] ?? ''),
            'refund_application_time' => $order['refund_application_time'] ?? null,
            'refund_reason_time' => $order['refund_reason_time'] ?? null,
            'referrer_id' => (int)($order['referrer_id'] ?? 0),
            'second_referrer_id' => (int)($order['second_referrer_id'] ?? 0),
            'commission_base_amount' => (float)($order['commission_base_amount'] ?? 0),
            'first_commission_rate' => (float)($order['first_commission_rate'] ?? 0),
            'second_commission_rate' => (float)($order['second_commission_rate'] ?? 0),
            'commission_level' => (int)($order['commission_level'] ?? 0),
            'commission_mode' => (string)($order['commission_mode'] ?? ''),
            'first_commission_amount' => (float)($order['first_commission_amount'] ?? 0),
            'second_commission_amount' => (float)($order['second_commission_amount'] ?? 0),
            'commission_status' => $commissionStatus,
            'commission_status_text' => $this->resolveCommissionStatusText($commissionStatus),
            'create_time' => $order['create_time'] ?? null,
            'pay_expire_seconds' => $this->resolvePayExpireSeconds($order),
            'pay_time' => $order['pay_time'] ?? null,
            'delivery_time' => $order['delivery_time'] ?? null,
            'delivery_name' => (string)($order['delivery_name'] ?? ''),
            'delivery_id' => (string)($order['delivery_id'] ?? ''),
            'delivery_code' => (string)($order['delivery_code'] ?? ''),
            'receive_time' => $order['receive_time'] ?? null,
            'finish_time' => $order['finish_time'] ?? null,
            'remark' => (string)($order['remark'] ?? ''),
            'goods_count' => count($normalizedGoods),
            'goods_total_num' => $goodsTotalNum,
            'address' => [
                'address_id' => (int)($order['address_id'] ?? 0),
                'real_name' => (string)($order['real_name'] ?? ''),
                'user_phone' => (string)($order['user_phone'] ?? ''),
                'province' => (string)($order['province'] ?? ''),
                'city' => (string)($order['city'] ?? ''),
                'district' => (string)($order['district'] ?? ''),
                'detail' => (string)($order['detail'] ?? ''),
                'user_address' => (string)($order['user_address'] ?? ''),
            ],
            'actions' => $actions,
        ];

        if ($includeGoods) {
            $payload['goods'] = $normalizedGoods;
        } else {
            $payload['goods_preview'] = array_slice($normalizedGoods, 0, 2);
        }

        if ($orderRole === Order::ROLE_MAIN) {
            $payload['merchant_name'] = '合并支付订单';
            $children = $this->buildOrderChildrenPayload((int)($order['id'] ?? 0));
            $payload['order_types'] = $this->collectOrderTypesFromChildrenPayload($children, $orderType);
            $payload['order_type_text'] = $this->resolveOrderTypesText($payload['order_types'], $orderType);
            if ($includeGoods) {
                $payload['children'] = $children;
            } else {
                $this->fillMainOrderListGoodsPreview($payload, $children);
            }
        }

        return $payload;
    }

    /**
     * 将主支付单的子单商品摘要合并到列表项自身，列表接口不直接返回 children。
     *
     * @param array $payload 待处理的插件载荷。
     * @param array $children 当前合并支付主单的业务子订单展示数据。
     * @return void 无返回值；按引用补齐主单商品摘要，不展开子单列表。
     */
    protected function fillMainOrderListGoodsPreview(array &$payload, array $children): void
    {
        if ($children === []) {
            return;
        }

        $goodsPreview = [];
        $goodsCount = 0;
        $goodsTotalNum = 0;

        foreach ($children as $child) {
            $childGoods = is_array($child['goods_preview'] ?? null) ? $child['goods_preview'] : [];
            $goodsCount += (int)($child['goods_count'] ?? count($childGoods));
            $goodsTotalNum += (int)($child['goods_total_num'] ?? 0);

            foreach ($childGoods as $goods) {
                if (count($goodsPreview) < 2) {
                    $goodsPreview[] = $goods;
                }
            }
        }

        if ($goodsCount > 0) {
            $payload['goods_count'] = $goodsCount;
        }
        if ($goodsTotalNum > 0) {
            $payload['goods_total_num'] = $goodsTotalNum;
        }
        if ($goodsPreview !== []) {
            $payload['goods_preview'] = $goodsPreview;
        }
    }

    /**
     * 规范化订单商品列表
     *
     * @param array $goodsList 当前业务订单的商品成交明细列表。
     * @return array 从永久成交快照补齐并统一类型的商品明细列表。
     */
    protected function normalizeOrderGoodsList(array $goodsList): array
    {
        $result = [];
        foreach ($goodsList as $goods) {
            $goodsInfo = is_array($goods['goods_info'] ?? null) ? $goods['goods_info'] : [];
            $productInfo = is_array($goodsInfo['product'] ?? null) ? $goodsInfo['product'] : [];
            $goodsId = (int)($goods['goods_id'] ?? 0);
            $orderType = (int)($goods['order_type'] ?? Order::ORDER_TYPE_GOODS);
            $contentType = (int)($productInfo['content_type'] ?? ($goodsInfo['content_type'] ?? 0));
            $price = (float)($goods['price'] ?? 0);
            $totalPrice = (float)($goods['total_price'] ?? 0);
            $quantity = max(1, (int)($goods['num'] ?? 0));
            $memberDiscountAmount = (float)($goods['member_discount_amount'] ?? 0);
            $memberLineTotal = (float)($goodsInfo['member_line_total'] ?? max(0, $totalPrice - $memberDiscountAmount));
            $memberPrice = (float)($goodsInfo['member_price'] ?? ($memberLineTotal / $quantity));
            $useMemberPrice = (bool)($goodsInfo['use_member_price'] ?? ($memberDiscountAmount > 0));
            $result[] = [
                'order_goods_id' => (int)($goods['id'] ?? 0),
                'goods_id' => $goodsId,
                'merchant_id' => (int)($goods['merchant_id'] ?? 0),
                'merchant_name' => (string)($goodsInfo['merchant_name'] ?? ''),
                'goods_name' => (string)($goods['goods_name'] ?? ($goodsInfo['goods_name'] ?? '')),
                'goods_image' => (string)($goodsInfo['goods_image'] ?? ''),
                'order_type' => $orderType,
                'goods_type' => (int)($goods['goods_type'] ?? OrderGoods::GOODS_TYPE_PHYSICAL),
                'content_type' => $contentType,
                'detail_url' => $this->buildOrderGoodsDetailUrl($goodsId, $orderType, $contentType),
                'sku_id' => (int)($goodsInfo['sku_id'] ?? 0),
                'sku_name' => (string)($goodsInfo['sku_name'] ?? ''),
                'num' => (int)($goods['num'] ?? 0),
                'price' => $price,
                'member_price' => $useMemberPrice ? $memberPrice : $price,
                'effective_price' => $useMemberPrice ? $memberPrice : $price,
                'member_line_total' => $useMemberPrice ? $memberLineTotal : $totalPrice,
                'use_member_price' => $useMemberPrice,
                'total_price' => $totalPrice,
                'goods_amount' => (float)($goods['goods_amount'] ?? 0),
                'points_deduct_amount' => (float)($goods['points_deduct_amount'] ?? 0),
                'platform_coupon_amount' => (float)($goods['platform_coupon_amount'] ?? 0),
                'merchant_coupon_amount' => (float)($goods['merchant_coupon_amount'] ?? 0),
                'member_discount_amount' => $memberDiscountAmount,
                'member_discount_rate' => (float)($goods['member_discount_rate'] ?? 1.00),
                'first_referrer_id' => (int)($goods['first_referrer_id'] ?? 0),
                'second_referrer_id' => (int)($goods['second_referrer_id'] ?? 0),
                'commission_base_amount' => (float)($goods['commission_base_amount'] ?? 0),
                'first_commission_rate' => (float)($goods['first_commission_rate'] ?? 0),
                'second_commission_rate' => (float)($goods['second_commission_rate'] ?? 0),
                'commission_level' => (int)($goods['commission_level'] ?? 0),
                'commission_mode' => (string)($goods['commission_mode'] ?? ''),
                'first_commission' => (float)($goods['first_commission'] ?? 0),
                'second_commission' => (float)($goods['second_commission'] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * 判断订单商品是否包含实物商品
     *
     * @param array $goodsList 当前业务订单的商品成交明细列表。
     * @return bool 成交商品明细是否包含实物商品。
     */
    protected function hasPhysicalOrderGoods(array $goodsList): bool
    {
        foreach ($goodsList as $goods) {
            if ((int)($goods['goods_type'] ?? 0) === OrderGoods::GOODS_TYPE_PHYSICAL) {
                return true;
            }
        }

        return false;
    }

    /**
     * 构建订单商品对应的移动端详情页地址。
     *
     * @param int $goodsId 原商品、内容或专题的资源编号。
     * @param int $orderType 订单资源类型，区分商品、内容与专题。
     * @param int $contentType 内容资源类型，用于选择对应移动详情入口。
     * @return string 当前内容或专题的移动详情入口；不可跳转时为空字符串。
     */
    protected function buildOrderGoodsDetailUrl(int $goodsId, int $orderType, int $contentType = 0): string
    {
        if ($goodsId <= 0) {
            return '';
        }

        if ($orderType === Order::ORDER_TYPE_GOODS) {
            return '';
        }

        if ($orderType === Order::ORDER_TYPE_SPECIAL) {
            return '/pages/contents/special/detail/index?id=' . $goodsId;
        }

        return match ($contentType) {
            ContentTypeEnum::NOVEL => '/pages/contents/content/novel/index?id=' . $goodsId,
            ContentTypeEnum::AUDIO => '/pages/contents/content/aud/index?id=' . $goodsId,
            ContentTypeEnum::VIDEO => '/pages/contents/content/vid/index?id=' . $goodsId,
            default => '/pages/contents/content/detail/index?id=' . $goodsId,
        };
    }

    /**
     * 给订单商品追加退款与评价动作状态.
     *
     * @param array $goodsList 当前业务订单的商品成交明细列表。
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @param array $orderActions 订单级可支付、退款或评价的操作状态。
     * @return array 追加商品级退款和评价动作后的成交明细列表。
     */
    protected function appendOrderGoodsActions(array $goodsList, array $order, array $orderActions = []): array
    {
        if ($goodsList === []) {
            return [];
        }

        $orderId = (int)($order['id'] ?? ($order['order_id'] ?? 0));
        $reviewMap = app()->make(OrderReviewService::class)->getOrderReviewMap($orderId);
        $canRefund = !empty($orderActions['can_refund']);
        $canOrderReview = !empty($orderActions['can_review']);

        foreach ($goodsList as $index => $goods) {
            $orderGoodsId = (int)($goods['order_goods_id'] ?? 0);
            $review = $reviewMap[$orderGoodsId] ?? null;
            $hasReviewed = !empty($review);
            $canReview = $canOrderReview && !$hasReviewed;

            $goodsList[$index]['can_refund'] = $canRefund;
            $goodsList[$index]['can_review'] = $canReview;
            $goodsList[$index]['has_reviewed'] = $hasReviewed;
            $goodsList[$index]['review_id'] = $hasReviewed ? (int)($review['id'] ?? 0) : 0;
            $goodsList[$index]['actions'] = [
                'can_refund' => $canRefund,
                'can_review' => $canReview,
                'has_reviewed' => $hasReviewed,
            ];
        }

        return $goodsList;
    }

    /**
     * 格式化订单展示用商户信息。
     *
     * @param int $merchantId 操作或结算所属商户编号，零表示平台。
     * @param array $merchant 已加载的商户店铺展示资料。
     * @return array 统一的安全店铺展示资料；零商户使用平台自营信息。
     */
    protected function formatOrderMerchantInfo(int $merchantId, array $merchant = []): array
    {
        if ($merchantId <= 0) {
            return [
                'id' => 0,
                'mer_name' => '平台自营',
                'name' => '平台自营',
                'mer_phone' => '',
                'phone' => '',
                'contact_name' => '',
                'contact_phone' => '',
                'logo' => '',
                'mer_avatar' => '',
                'avatar' => '',
                'business_status' => 1,
                'business_status_text' => '平台自营',
                'status' => 1,
            ];
        }

        $name = trim((string)($merchant['mer_name'] ?? ($merchant['name'] ?? '')));
        $avatar = trim((string)($merchant['mer_avatar'] ?? ($merchant['avatar'] ?? '')));
        $logo = trim((string)($merchant['logo'] ?? ''));
        $phone = trim((string)($merchant['phone'] ?? ''));
        if ($phone === '') {
            $phone = trim((string)($merchant['contact_phone'] ?? ($merchant['mer_phone'] ?? '')));
        }
        $businessStatus = (int)($merchant['business_status'] ?? 1);

        return [
            'id' => $merchantId,
            'mer_name' => $name,
            'name' => $name,
            'mer_phone' => trim((string)($merchant['mer_phone'] ?? '')),
            'phone' => $phone,
            'contact_name' => trim((string)($merchant['contact_name'] ?? '')),
            'contact_phone' => trim((string)($merchant['contact_phone'] ?? '')),
            'logo' => $logo,
            'mer_avatar' => $avatar,
            'avatar' => $avatar !== '' ? $avatar : $logo,
            'business_status' => $businessStatus,
            'business_status_text' => trim((string)($merchant['business_status_text'] ?? '')) !== ''
                ? (string)$merchant['business_status_text']
                : ($businessStatus === 1 ? '营业中' : '休息中'),
            'status' => (int)($merchant['status'] ?? 1),
        ];
    }

    /**
     * 旧订单商品快照可能缺少商户名称，列表展示时用订单商户信息补齐。
     *
     * @param array $goodsList 当前业务订单的商品成交明细列表。
     * @param array $merchant 已加载的商户店铺展示资料。
     * @return array 按当前订单店铺资料补齐商户名称后的商品快照。
     */
    protected function appendMerchantInfoToGoods(array $goodsList, array $merchant): array
    {
        if ($goodsList === [] || (int)($merchant['id'] ?? 0) <= 0) {
            return $goodsList;
        }

        $merchantId = (int)$merchant['id'];
        $merchantName = trim((string)($merchant['mer_name'] ?? ($merchant['name'] ?? '')));
        if ($merchantName === '') {
            return $goodsList;
        }

        foreach ($goodsList as $index => $goods) {
            $goodsMerchantId = (int)($goods['merchant_id'] ?? 0);
            $goodsMerchantName = trim((string)($goods['merchant_name'] ?? ''));
            if ($goodsMerchantName === '' && $goodsMerchantId === $merchantId) {
                $goodsList[$index]['merchant_name'] = $merchantName;
            }
        }

        return $goodsList;
    }

    /**
     * 获取订单主体名称
     *
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @param array $goodsList 当前业务订单的商品成交明细列表。
     * @param array $merchant 已加载的商户店铺展示资料。
     * @return string 主支付单、平台自营或商户店铺的展示名称。
     */
    protected function resolveOrderMerchantName(array $order, array $goodsList = [], array $merchant = []): string
    {
        if ((int)($order['order_role'] ?? Order::ROLE_NORMAL) === Order::ROLE_MAIN) {
            return '合并支付订单';
        }

        if ((int)($order['merchant_id'] ?? 0) <= 0) {
            return '平台自营';
        }

        $merchantName = trim((string)($merchant['mer_name'] ?? ($merchant['name'] ?? '')));
        if ($merchantName !== '') {
            return $merchantName;
        }

        $merchantName = trim((string)($goodsList[0]['merchant_name'] ?? ''));

        return $merchantName !== '' ? $merchantName : '商户店铺';
    }

    /**
     * 获取订单类型文案
     *
     * @param int $orderType 订单资源类型，区分商品、内容与专题。
     * @return string 当前订单资源类型的中文名称。
     */
    protected function resolveOrderTypeText(int $orderType): string
    {
        return match ($orderType) {
            Order::ORDER_TYPE_GOODS => '商品订单',
            Order::ORDER_TYPE_SPECIAL => '专题订单',
            default => '内容订单',
        };
    }

    /**
     * 从主单的 children（子单）载荷中汇总订单类型。
     *
     * @param array $children 当前合并支付主单的业务子订单展示数据。
     * @param int $fallbackOrderType 未汇总到有效类型时使用的备用订单类型。
     * @return array 主单子订单中去重后的订单类型列表。
     */
    protected function collectOrderTypesFromChildrenPayload(array $children, int $fallbackOrderType): array
    {
        $orderTypes = [];
        foreach ($children as $child) {
            $orderType = (int)($child['order_type'] ?? 0);
            if ($orderType > 0) {
                $orderTypes[$orderType] = $orderType;
            }
        }

        return $this->normalizeOrderTypeList(array_values($orderTypes), $fallbackOrderType);
    }

    /**
     * 查询主单下业务子单类型。
     *
     * @param int $mainOrderId 合并支付主订单的内部编号。
     * @return array 指定主支付单下有效业务子订单的类型列表。
     */
    protected function getMainChildOrderTypes(int $mainOrderId): array
    {
        if ($mainOrderId <= 0) {
            return [];
        }

        $orderTypes = $this->getModel()
            ->where('pid', $mainOrderId)
            ->where('order_role', Order::ROLE_CHILD)
            ->order('id asc')
            ->column('order_type');

        return $this->normalizeOrderTypeList($orderTypes, 0);
    }

    /**
     * 规范化订单类型列表。
     *
     * @param array $orderTypes 参与结算或展示的订单类型列表。
     * @param int $fallbackOrderType 未汇总到有效类型时使用的备用订单类型。
     * @return array 去重的有效订单类型，必要时使用备用类型。
     */
    protected function normalizeOrderTypeList(array $orderTypes, int $fallbackOrderType): array
    {
        $result = [];
        foreach ($orderTypes as $orderType) {
            $orderType = (int)$orderType;
            if ($orderType > 0) {
                $result[$orderType] = $orderType;
            }
        }

        if ($result === [] && $fallbackOrderType > 0) {
            $result[$fallbackOrderType] = $fallbackOrderType;
        }

        return array_values($result);
    }

    /**
     * 根据多个订单类型生成展示文案。
     *
     * @param array $orderTypes 参与结算或展示的订单类型列表。
     * @param int $fallbackOrderType 未汇总到有效类型时使用的备用订单类型。
     * @return string 单一资源类型或多类型合单的中文文案。
     */
    protected function resolveOrderTypesText(array $orderTypes, int $fallbackOrderType): string
    {
        $orderTypes = $this->normalizeOrderTypeList($orderTypes, $fallbackOrderType);
        if (count($orderTypes) > 1) {
            return '合单订单';
        }

        return $this->resolveOrderTypeText((int)($orderTypes[0] ?? $fallbackOrderType));
    }

    /**
     * 获取支付方式文案
     *
     * @param string $payType 已选择或验证的支付渠道编码。
     * @return string 当前系统支付渠道的中文文案。
     */
    protected function resolvePayTypeText(string $payType): string
    {
        return match ($payType) {
            PayTypeEnum::PAY_TYPE_WECHAT => '微信支付',
            PayTypeEnum::PAY_TYPE_ALIPAY => '支付宝支付',
            PayTypeEnum::PAY_TYPE_YUE => '余额支付',
            PayTypeEnum::PAY_TYPE_FREE => '免支付',
            default => '未支付',
        };
    }

    /**
     * 取消订单当前待处理退款商品明细。
     *
     * @param string $orderSn 宿主订单的业务单号。
     * @return void 无返回值；仅取消当前订单待处理退款商品明细。
     */
    protected function cancelPendingRefundProductsByOrderSn(string $orderSn): void
    {
        if ($orderSn === '') {
            return;
        }

        $pendingRecord = app()->make(OrderRefundService::class)->getPendingByOrderSn($orderSn);
        if (!$pendingRecord) {
            return;
        }

        app()->make(StoreRefundProductService::class)->markCancelledByRefundSn(
            (string)($pendingRecord['refund_sn'] ?? '')
        );
    }

    /**
     * 获取取消当前退款申请后订单应保留的历史退款状态。
     *
     * @param int $orderId 订单标识。
     * @return array{refund_status:int,refund_amount:float} 保留历史成功退款金额的订单退款状态及累计金额。
     */
    protected function resolveExistingRefundState(int $orderId): array
    {
        $successRefundAmount = app()->make(StoreRefundProductService::class)
            ->sumSuccessRefundAmountByOrder($orderId);

        return [
            'refund_status' => $successRefundAmount > 0 ? 3 : 0,
            'refund_amount' => $successRefundAmount,
        ];
    }

    /**
     * 获取退款状态文案
     *
     * @param int $refundStatus 订单当前退款状态编码。
     * @return string 当前订单退款状态的中文文案。
     */
    protected function resolveRefundStatusText(int $refundStatus): string
    {
        return match ($refundStatus) {
            3 => '部分退款',
            2 => '已退款',
            1 => '退款中',
            default => '未退款',
        };
    }

    /**
     * 获取订单操作状态
     *
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @return array 当前订单可支付、取消、退款、收货和评价的操作状态。
     */
    protected function resolveOrderActions(array $order): array
    {
        $status = (int)($order['status'] ?? Order::STATUS_PENDING);
        $refundStatus = (int)($order['refund_status'] ?? 0);
        $orderRole = (int)($order['order_role'] ?? Order::ROLE_NORMAL);
        $isGoodsOrder = (int)($order['order_type'] ?? Order::ORDER_TYPE_GOODS) === Order::ORDER_TYPE_GOODS;
        $isHotelOrder = app()->make(PluginCheckoutCapabilityService::class)->isHotelOrder($order);
        $hasDelivery = !empty($order['delivery_time']);
        $reviewState = $isHotelOrder
            ? ['can_review' => false, 'has_reviewed' => false, 'reason' => '酒店预订不支持普通评价']
            : app()->make(OrderReviewService::class)->resolveReviewState($order);

        if ($orderRole === Order::ROLE_MAIN) {
            return [
                'can_pay' => $status === Order::STATUS_PENDING,
                'can_cancel' => $status === Order::STATUS_PENDING,
                'can_refund' => false,
                'can_delivery' => false,
                'can_receipt' => false,
                'can_review' => false,
                'has_reviewed' => false,
                'review_reason' => '合并支付主单不直接评价',
            ];
        }

        if ($orderRole === Order::ROLE_CHILD
            && (int)($order['pid'] ?? 0) > 0
            && $status === Order::STATUS_PENDING
        ) {
            return [
                'can_pay' => false,
                'can_cancel' => false,
                'can_refund' => false,
                'can_delivery' => false,
                'can_receipt' => false,
                'can_review' => false,
                'has_reviewed' => false,
                'review_reason' => '请先支付主支付单',
            ];
        }

        return [
            'can_pay' => $status === Order::STATUS_PENDING,
            'can_cancel' => $status === Order::STATUS_PENDING,
            'can_refund' => !$isHotelOrder && $this->isRefundableStatus($status)
                && in_array($refundStatus, [0, 3], true),
            'can_delivery' => !$isHotelOrder && $status === Order::STATUS_PAID && $isGoodsOrder && !$hasDelivery,
            'can_receipt' => !$isHotelOrder && $status === Order::STATUS_PAID && $isGoodsOrder && $hasDelivery,
            'can_review' => $reviewState['can_review'],
            'has_reviewed' => $reviewState['has_reviewed'],
            'review_reason' => $reviewState['reason'],
        ];
    }

    /**
     * 回滚待支付订单占用资源
     *
     * @param bool $restorePoints 是否退回积分。取消主支付单时积分只在主单退，子单只退商户券和库存。
     * @param Model $order 当前业务订单的模型或不可变数据快照。
     * @return void 无返回值；按主子单规则归还库存、优惠券及可选积分。
     * @throws AdminResponseException
     */
    protected function restorePendingOrderResources(Model $order, bool $restorePoints = true): void
    {
        $orderData = $order->toArray();
        $orderId = (int)($orderData['id'] ?? 0);
        $userId = (int)($orderData['user_id'] ?? 0);
        $orderSn = (string)($orderData['order_sn'] ?? '');

        if ($restorePoints && (int)($orderData['use_points'] ?? 0) > 0) {
            app()->make(UserAssetService::class)->changeAsset(
                $userId,
                'points',
                (float)($orderData['use_points'] ?? 0),
                'in',
                '取消订单退回积分',
                [
                    'source_type' => 'order_cancel_points',
                    'order_no' => $orderSn,
                    'source_desc' => '取消订单退回积分',
                ]
            );
        }

        if ((int)($orderData['platform_coupon_id'] ?? 0) > 0) {
            app()->make(StoreCouponUserService::class)->returnCoupon((int)$orderData['platform_coupon_id']);
        }

        if ((int)($orderData['merchant_coupon_id'] ?? 0) > 0) {
            app()->make(StoreCouponUserService::class)->returnCoupon((int)$orderData['merchant_coupon_id']);
        }

        app()->make(StoreCouponIssueService::class)->cancelOrderGiftCoupons($orderSn);
        $isHotelOrder = app()->make(PluginCheckoutCapabilityService::class)->isHotelOrder($orderData);
        if (!$isHotelOrder) {
            app()->make(OrderPromotionService::class)->release($orderId);
            $this->restoreGoodsStock($orderId);
        }
    }

    /**
     * 取消待支付订单并回滚占用资源。
     *
     * @param Model $order 已加锁的订单模型。
     * @param string $reason 取消原因。
     * @return void 无返回值；确认外部关单后关闭订单并恢复占用资源。
     * @throws AdminResponseException
     */
    protected function cancelPendingOrder(Model $order, string $reason): void
    {
        $orderData = $order->toArray();
        if ((int)($orderData['status'] ?? Order::STATUS_PENDING) !== Order::STATUS_PENDING) {
            throw new AdminResponseException('当前订单状态不支持取消');
        }
        // 外部支付渠道关单结果不确定时必须保留库存预留，以便后续重试。
        if (!app()->make(OrderPayService::class)->closePromotionPayment($orderData)) {
            return;
        }

        if ((int)($orderData['order_role'] ?? Order::ROLE_NORMAL) === Order::ROLE_MAIN) {
            /*
             * 合单取消必须从主单进入：主单退平台券/积分，子单逐张取消并退商户券、库存。
             * 子单调用 restorePendingOrderResources(..., false)，避免重复退积分。
             */
            $order->save([
                'status' => Order::STATUS_CANCELLED,
            ]);
            app()->make(PluginOrderLifecycleService::class)->sync($order->toArray(), 'cancelled');
            $this->restorePendingOrderResources($order);

            $children = $this->getModel()
                ->where('pid', (int)($orderData['id'] ?? 0))
                ->where('order_role', Order::ROLE_CHILD)
                ->where('status', Order::STATUS_PENDING)
                ->select();
            foreach ($children as $child) {
                $child->save([
                    'status' => Order::STATUS_CANCELLED,
                ]);
                app()->make(PluginOrderLifecycleService::class)->sync($child->toArray(), 'cancelled');
                $this->restorePendingOrderResources($child, false);
            }

            trace('pending main order cancelled: ' . json_encode([
                'order_id' => (int)($orderData['id'] ?? 0),
                'order_sn' => (string)($orderData['order_sn'] ?? ''),
                'reason' => $reason,
            ], JSON_UNESCAPED_UNICODE), 'info');

            return;
        }

        if ((int)($orderData['order_role'] ?? Order::ROLE_NORMAL) === Order::ROLE_CHILD
            && (int)($orderData['pid'] ?? 0) > 0
        ) {
            throw new AdminResponseException('合单子订单请取消主支付单');
        }

        $order->save([
            'status' => Order::STATUS_CANCELLED,
        ]);
        app()->make(PluginOrderLifecycleService::class)->sync($order->toArray(), 'cancelled');
        $this->restorePendingOrderResources($order);

        trace('pending order cancelled: ' . json_encode([
            'order_id' => (int)($orderData['id'] ?? 0),
            'order_sn' => (string)($orderData['order_sn'] ?? ''),
            'reason' => $reason,
        ], JSON_UNESCAPED_UNICODE), 'info');
    }

    /**
     * 完成订单支付
     *
     * @param Model $order 当前业务订单的模型或不可变数据快照。
     * @param string $payType 已选择或验证的支付渠道编码。
     * @param int $financePid 当前支付对应的聚合资金流水编号。
     * @param bool $fromMainOrder 是否由合并支付主单推进当前业务子单。
     * @param ?float $memberPointsMultiplier 本次支付固化使用的会员积分倍数。
     * @param ?int $paidAt 已验证的实际支付时间戳；为空时使用服务端时间。
     * @return array 完成商品权益、结算及通知后的统一支付结果。
     * @throws AdminResponseException
     */
    protected function finishPaidOrder(
        Model $order,
        string $payType = '',
        int $financePid = 0,
        bool $fromMainOrder = false,
        ?float $memberPointsMultiplier = null,
        ?int $paidAt = null
    ): array
    {
        if ((int)($order['order_role'] ?? Order::ROLE_NORMAL) === Order::ROLE_MAIN) {
            // 主支付单支付成功后再逐个激活子单，普通单和子单的实际完成逻辑仍复用本方法。
            return $this->finishMainPaidOrder($order, $payType);
        }

        $status = (int)$order['status'];
        if ($this->isPaidStatus($status)) {
            $paidData = $order->toArray();
            app()->make(StoreCouponIssueService::class)->grantOrderGiftCoupons($paidData);
            if ($this->isVirtualOrderType((int)($paidData['order_type'] ?? Order::ORDER_TYPE_GOODS))) {
                app()->make(OrderGiftPointsRecordService::class)->freezeByOrder($paidData);
            }
            return $this->buildPaidOrderPayload($paidData);
        }

        if ($status !== Order::STATUS_PENDING) {
            throw new AdminResponseException('当前订单状态不支持支付成功处理');
        }
        if (!$fromMainOrder
            && (int)($order['order_role'] ?? Order::ROLE_NORMAL) === Order::ROLE_CHILD
            && (int)($order['pid'] ?? 0) > 0
        ) {
            // 子单不能绕过主单单独支付，否则会出现用户支付金额和子单拆分金额不一致。
            throw new AdminResponseException('合单子订单请通过主支付单支付');
        }

        $orderData = $order->toArray();
        $isHotelOrder = app()->make(PluginCheckoutCapabilityService::class)->isHotelOrder($orderData);
        app()->make(OrderPaymentDeadlineService::class)->assertPayable($orderData, $paidAt);
        app()->make(OrderPromotionService::class)->paid((int) $orderData['id']);
        $now = date('Y-m-d H:i:s', $paidAt ?? time());
        $isVirtualOrder = $this->isVirtualOrderType((int)$orderData['order_type']);
        $updateData = [
            'status' => $this->resolvePaidStatus((int)$orderData['order_type'], $isHotelOrder),
            'pay_time' => $now,
        ];

        if ($payType !== '') {
            $updateData['pay_type'] = $payType;
        }

        if ($isVirtualOrder && !$isHotelOrder) {
            $updateData['delivery_time'] = $now;
        }

        $order->save($updateData);
        $freshData = array_merge($orderData, $updateData);
        app()->make(PluginOrderLifecycleService::class)->sync($freshData, 'paid');
        if (!$isHotelOrder) {
            $this->grantPurchaseBenefits($freshData);
        }
        app()->make(StoreCouponIssueService::class)->grantOrderGiftCoupons($freshData);
        if ($isVirtualOrder && !$isHotelOrder) {
            app()->make(OrderGiftPointsRecordService::class)->freezeByOrder($freshData);
        }

        if (!$isVirtualOrder) {
            // 商品订单保留原有“按支付金额会员积分加成”逻辑。
            $this->grantMemberPoints(
                (int)$freshData['user_id'],
                (float)$freshData['pay_amount'],
                (string)$freshData['order_sn'],
                $memberPointsMultiplier
            );
        }

        $freshData = array_merge($freshData, $this->freezeOrderCommission($order, $freshData));

        $merchantSettlementService = app()->make(OrderMerchantSettlementService::class);
        // financePid 来自主单聚合流水；普通单为 0，合单子单流水会挂到主流水下。
        $merchantSettlementService->settlePaidOrder($freshData, $financePid);
        $this->createPlatformCouponSubsidyRecord($freshData, $financePid);
        if ((int)($freshData['status'] ?? Order::STATUS_PENDING) === Order::STATUS_COMPLETED) {
            $merchantSettlementService->markOrderCompleted($freshData);
        }
        app()->make(BackendMessageEventService::class)->orderPaid($freshData);

        return $this->buildPaidOrderPayload($freshData);
    }

    /**
     * 完成主支付单及其子订单支付。
     *
     * @param Model $order 当前业务订单的模型或不可变数据快照。
     * @param string $payType 已选择或验证的支付渠道编码。
     * @return array 主支付单与全部业务子单完成支付后的统一结果。
     * @throws AdminResponseException
     */
    protected function finishMainPaidOrder(Model $order, string $payType = ''): array
    {
        $orderData = $order->toArray();
        $status = (int)($orderData['status'] ?? Order::STATUS_PENDING);
        if ($this->isPaidStatus($status)) {
            app()->make(StoreCouponIssueService::class)->grantOrderGiftCoupons($orderData);
            return $this->buildPaidOrderPayload($orderData);
        }

        if ($status !== Order::STATUS_PENDING) {
            throw new AdminResponseException('当前订单状态不支持支付成功处理');
        }

        $now = date('Y-m-d H:i:s');
        $childOrderTypes = $this->getMainChildOrderTypes((int)$order['id']);
        $isVirtualOrder = $childOrderTypes !== []
            ? !in_array(Order::ORDER_TYPE_GOODS, $childOrderTypes, true)
            : $this->isVirtualOrderType((int)$order['order_type']);
        $updateData = [
            'status' => $isVirtualOrder ? Order::STATUS_WAIT_REVIEW : Order::STATUS_PAID,
            'pay_time' => $now,
        ];
        if ($payType !== '') {
            $updateData['pay_type'] = $payType;
        }
        if ($isVirtualOrder) {
            $updateData['delivery_time'] = $now;
        }

        /*
         * 主单先标记支付成功并创建聚合收款流水。重复回调再次进入时会在上面的已支付分支直接返回，
         * 避免重复激活子单、重复发权益或重复写财务流水。
        */
        $order->save($updateData);
        $freshData = array_merge($orderData, $updateData);
        $summaryRecord = $this->createMainOrderFinanceSummary($freshData);
        $financePid = (int)($summaryRecord['id'] ?? 0);
        app()->make(StoreCouponIssueService::class)->grantOrderGiftCoupons($freshData);
        // 所有子单属于同一用户，会员状态和积分倍数只需在主单支付时读取一次。
        $memberPointsMultiplier = $this->resolveMemberPointsMultiplier((int)($freshData['user_id'] ?? 0));

        $children = $this->getModel()
            ->where('pid', (int)$order['id'])
            ->where('order_role', Order::ROLE_CHILD)
            ->order('id asc')
            ->select();
        foreach ($children as $child) {
            // fromMainOrder=true 允许子单复用普通支付完成逻辑，同时保留“子单不可单独支付”的保护。
            $this->finishPaidOrder($child, $payType, $financePid, true, $memberPointsMultiplier);
        }

        app()->make(BackendMessageEventService::class)->orderPaid($freshData);

        return $this->buildPaidOrderPayload($freshData);
    }

    /**
     * 构建支付成功结果
     *
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @return array 包含主子单信息及支付后业务状态的统一结果。
     */
    protected function buildPaidOrderPayload(array $order): array
    {
        $status = (int)($order['status'] ?? Order::STATUS_PENDING);
        $commissionStatus = (int)($order['commission_status'] ?? Order::COMMISSION_STATUS_PENDING);
        $orderType = (int)($order['order_type'] ?? Order::ORDER_TYPE_GOODS);

        $payload = [
            'order_id' => (int)($order['id'] ?? 0),
            'order_sn' => (string)($order['order_sn'] ?? ''),
            'pid' => (int)($order['pid'] ?? 0),
            'main_order_sn' => (string)($order['main_order_sn'] ?? ''),
            'order_role' => (int)($order['order_role'] ?? Order::ROLE_NORMAL),
            'order_type' => $orderType,
            'order_types' => [$orderType],
            'order_type_text' => $this->resolveOrderTypeText($orderType),
            'status' => $status,
            'status_text' => $this->resolveStatusText($status),
            'pay_type' => (string)($order['pay_type'] ?? ''),
            'pay_amount' => (float)($order['pay_amount'] ?? 0),
            'platform_coupon_amount' => (float)($order['platform_coupon_amount'] ?? 0),
            'merchant_coupon_amount' => (float)($order['merchant_coupon_amount'] ?? 0),
            'member_discount_amount' => (float)($order['member_discount_amount'] ?? 0),
            'member_discount_rate' => (float)($order['member_discount_rate'] ?? 1.00),
            'pay_time' => $order['pay_time'] ?? null,
            'delivery_time' => $order['delivery_time'] ?? null,
            'receive_time' => $order['receive_time'] ?? null,
            'finish_time' => $order['finish_time'] ?? null,
            'refund_status' => (int)($order['refund_status'] ?? 0),
            'refund_status_text' => $this->resolveRefundStatusText((int)($order['refund_status'] ?? 0)),
            'refund_amount' => (float)($order['refund_amount'] ?? 0),
            'refund_reason' => (string)($order['refund_reason'] ?? ''),
            'commission_status' => $commissionStatus,
            'commission_status_text' => $this->resolveCommissionStatusText($commissionStatus),
            'commission_time' => $order['commission_time'] ?? null,
            'referrer_id' => (int)($order['referrer_id'] ?? 0),
            'second_referrer_id' => (int)($order['second_referrer_id'] ?? 0),
            'commission_base_amount' => (float)($order['commission_base_amount'] ?? 0),
            'first_commission_rate' => (float)($order['first_commission_rate'] ?? 0),
            'second_commission_rate' => (float)($order['second_commission_rate'] ?? 0),
            'commission_level' => (int)($order['commission_level'] ?? 0),
            'commission_mode' => (string)($order['commission_mode'] ?? ''),
            'first_commission_amount' => (float)($order['first_commission_amount'] ?? 0),
            'second_commission_amount' => (float)($order['second_commission_amount'] ?? 0),
            'platform_commission_amount' => (float)($order['platform_commission_amount'] ?? 0),
            'merchant_income_amount' => (float)($order['merchant_income_amount'] ?? 0),
        ];

        if ((int)($order['order_role'] ?? Order::ROLE_NORMAL) === Order::ROLE_MAIN) {
            // 主单详情返回聚合子单，支付页可继续使用主单号，支付后业务页可跳到子单流程。
            $payload['children'] = $this->buildOrderChildrenPayload((int)($order['id'] ?? 0));
            $payload['order_types'] = $this->collectOrderTypesFromChildrenPayload($payload['children'], $orderType);
            $payload['order_type_text'] = $this->resolveOrderTypesText($payload['order_types'], $orderType);
        }

        return $payload;
    }

    /**
     * 创建主支付单聚合流水。
     *
     * 聚合流水只记录用户对主单的总支付金额，merchant_id 固定为 0。
     * 子单的商户入账、平台抽佣、平台券补贴等明细流水通过 pid 指向这条记录。
     *
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @return array 新建或复用的主支付聚合流水编号及资料。
     */
    protected function createMainOrderFinanceSummary(array $order): array
    {
        $orderSn = (string)($order['order_sn'] ?? '');
        $payAmount = $this->normalizeMoney((float)($order['pay_amount'] ?? 0));
        if ($orderSn === '' || $payAmount <= 0) {
            return [];
        }

        $financeService = app()->make(FinanceService::class);
        $existsId = (int)$financeService->getModel()
            ->where('order_sn', $orderSn)
            ->where('type', FinanceService::TYPE_ORDER_SUMMARY)
            ->value('id');
        if ($existsId > 0) {
            // 支付回调可能重复到达，聚合流水按主单号和类型保持幂等。
            return ['id' => $existsId];
        }

        return $financeService->createRecord([
            'pid' => 0,
            'user_id' => (int)($order['user_id'] ?? 0),
            'merchant_id' => 0,
            'type' => FinanceService::TYPE_ORDER_SUMMARY,
            'change_type' => 'in',
            'fund_status' => FinanceService::FUND_STATUS_AVAILABLE,
            'amount' => $payAmount,
            'remark' => '合单支付聚合收款',
            'order_sn' => $orderSn,
        ]);
    }

    /**
     * 创建退款聚合流水。
     *
     * 聚合退款流水是本次退款的父流水；商品级或整单级冲正流水挂到 pid 下，统计时默认排除聚合类型。
     *
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @param float $refundAmount 本次申请或确认退款的金额，单位为元。
     * @param string $refundSn 固定的退款业务单号。
     * @return array 新建或复用的本次退款聚合流水编号及资料。
     */
    protected function createRefundFinanceSummary(array $order, float $refundAmount, string $refundSn = ''): array
    {
        $orderSn = (string)($order['order_sn'] ?? '');
        $amount = $this->normalizeMoney($refundAmount > 0 ? $refundAmount : (float)($order['pay_amount'] ?? 0));
        if ($orderSn === '' || $amount <= 0) {
            return [];
        }

        $remark = $refundSn !== '' ? '订单退款聚合流水：' . $refundSn : '订单退款聚合流水';
        $financeService = app()->make(FinanceService::class);
        $existsId = (int)$financeService->getModel()
            ->where('order_sn', $orderSn)
            ->where('type', FinanceService::TYPE_REFUND_SUMMARY)
            ->where('remark', $remark)
            ->value('id');
        if ($existsId > 0) {
            // 同一个退款单号重复通知时复用已有父流水，避免重复冲正明细挂到不同父节点。
            return ['id' => $existsId];
        }

        return $financeService->createRecord([
            'pid' => 0,
            'user_id' => (int)($order['user_id'] ?? 0),
            'merchant_id' => 0,
            'type' => FinanceService::TYPE_REFUND_SUMMARY,
            'change_type' => 'out',
            'fund_status' => FinanceService::FUND_STATUS_AVAILABLE,
            'amount' => $amount,
            'remark' => $remark,
            'order_sn' => $orderSn,
        ]);
    }

    /**
     * 创建平台券补贴支出流水。
     *
     * 平台券默认由平台承担。子单用户实付已经扣减平台券，商户结算又把平台券加回，
     * 因此这里单独记录平台营销补贴支出，并通过 pid 挂到主支付聚合流水下。
     *
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @param int $financePid 当前支付对应的聚合资金流水编号。
     * @return void 无返回值；将平台券补贴支出挂到主支付聚合流水。
     */
    protected function createPlatformCouponSubsidyRecord(array $order, int $financePid = 0): void
    {
        $orderSn = (string)($order['order_sn'] ?? '');
        $merchantId = (int)($order['merchant_id'] ?? 0);
        $amount = $this->normalizeMoney((float)($order['platform_coupon_amount'] ?? 0));
        if ($orderSn === '' || $merchantId <= 0 || $amount <= 0) {
            return;
        }

        $financeService = app()->make(FinanceService::class);
        $existsId = (int)$financeService->getModel()
            ->where('order_sn', $orderSn)
            ->where('type', FinanceService::TYPE_COUPON_SUBSIDY)
            ->where('change_type', 'out')
            ->value('id');
        if ($existsId > 0) {
            return;
        }

        $financeService->createRecord([
            'pid' => $financePid,
            'user_id' => (int)($order['user_id'] ?? 0),
            'merchant_id' => 0,
            'type' => FinanceService::TYPE_COUPON_SUBSIDY,
            'change_type' => 'out',
            'fund_status' => FinanceService::FUND_STATUS_AVAILABLE,
            'amount' => $amount,
            'remark' => '平台优惠券补贴商户订单',
            'order_sn' => $orderSn,
        ]);
    }

    /**
     * 解析支付后的订单状态
     *
     * @param int $orderType 订单资源类型，区分商品、内容与专题。
     * @return int 支付成功后对应的待发货或待评价状态。
     */
    protected function resolvePaidStatus(int $orderType, bool $isHotelOrder = false): int
    {
        if ($isHotelOrder) {
            return Order::STATUS_PAID;
        }

        return $this->isVirtualOrderType($orderType)
            ? Order::STATUS_WAIT_REVIEW
            : Order::STATUS_PAID;
    }

    /**
     * 发放购买权益
     *
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @return void 无返回值；按真实已支付业务订单发放资源权益。
     */
    protected function grantPurchaseBenefits(array $order): void
    {
        $orderId = (int)($order['id'] ?? 0);
        $orderType = (int)($order['order_type'] ?? Order::ORDER_TYPE_GOODS);
        if ($orderId <= 0 || !$this->isVirtualOrderType($orderType)
            || app()->make(PluginCheckoutCapabilityService::class)->isHotelOrder($order)) {
            return;
        }

        // 虚拟订单的专题销量和购买权益共用同一批订单商品，避免分别查询两次。
        $orderGoodsList = app()->make(OrderGoodsService::class)->getByOrderId($orderId);
        if ($orderType === Order::ORDER_TYPE_SPECIAL) {
            $buyCountMap = [];
            foreach ($orderGoodsList as $orderGoods) {
                $specialId = (int)($orderGoods['goods_id'] ?? 0);
                if ($specialId <= 0) {
                    continue;
                }
                $buyCountMap[$specialId] = ($buyCountMap[$specialId] ?? 0)
                    + max(1, (int)($orderGoods['num'] ?? 1));
            }
            foreach ($buyCountMap as $specialId => $buyNum) {
                app()->make(SpecialService::class)->incrementBuyCount((int)$specialId, $buyNum);
            }
        }

        app()->make(UserPurchaseRecordService::class)->recordOrderRights($order, $orderGoodsList);
    }

    /**
     * 发放会员积分加成
     *
     * @param int $userId 当前业务用户编号。
     * @param float $payAmount 订单实际支付金额，单位为元。
     * @param string $orderSn 宿主订单的业务单号。
     * @param ?float $multiplier 会员积分倍数；为空时读取当前可用会员配置。
     * @return void 无返回值；按实际支付金额发放会员积分加成。
     */
    protected function grantMemberPoints(
        int $userId,
        float $payAmount,
        string $orderSn,
        ?float $multiplier = null
    ): void
    {
        if ($userId <= 0 || $payAmount <= 0) {
            return;
        }

        $multiplier ??= $this->resolveMemberPointsMultiplier($userId);
        if ($multiplier <= 1) {
            return;
        }

        // 基础积分 = 支付金额 × 1（1元=1积分），会员额外积分 = 基础积分 × (倍数 - 1)
        $basePoints = (int) floor($payAmount);
        $bonusPoints = (int) floor($basePoints * ($multiplier - 1));
        if ($bonusPoints <= 0) {
            return;
        }

        try {
            app()->make(UserAssetService::class)->changeAsset(
                $userId,
                'points',
                (float) $bonusPoints,
                'in',
                '会员积分加成',
                [
                    'source_type' => 'member_points_bonus',
                    'order_no' => $orderSn,
                    'source_desc' => '会员积分加成 x' . $multiplier,
                ]
            );
        } catch (\Throwable $e) {
            \think\facade\Log::error('[member] grantMemberPoints failed', [
                'user_id' => $userId,
                'pay_amount' => $payAmount,
                'order_sn' => $orderSn,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 获取当前用户可用的会员积分倍数。
     *
     * @param int $userId 当前业务用户编号。
     * @return float 当前用户有效会员积分倍数；无有效会员时为一。
     */
    protected function resolveMemberPointsMultiplier(int $userId): float
    {
        if ($userId <= 0) {
            return 1.0;
        }

        $memberService = app()->make(UserMemberService::class);
        if (!$memberService->isActiveMember($userId)) {
            return 1.0;
        }

        return $memberService->getPointsMultiplier();
    }

    /**
     * 冻结订单佣金入口
     *
     * @param Model $order 当前支付订单模型。
     * @param array $orderData 下单时已计算完成的订单金额快照。
     * @return array 实际写入订单的佣金状态字段。
     */
    protected function freezeOrderCommission(Model $order, array $orderData): array
    {
        if ((int)($orderData['commission_status'] ?? 0) !== Order::COMMISSION_STATUS_PENDING) {
            return [];
        }

        // 佣金已在创建订单时按商品行汇总到订单主表，支付时直接使用快照，无需再次查询订单商品。
        $firstCommissionAmount = $this->normalizeMoney((float)($orderData['first_commission_amount'] ?? 0));
        $secondCommissionAmount = $this->normalizeMoney((float)($orderData['second_commission_amount'] ?? 0));
        if ($firstCommissionAmount <= 0 && $secondCommissionAmount <= 0) {
            return [];
        }

        $updateData = [
            'first_commission_amount' => $firstCommissionAmount,
            'second_commission_amount' => $secondCommissionAmount,
            'commission_status' => Order::COMMISSION_STATUS_FROZEN,
        ];
        $order->save($updateData);

        return $updateData;
    }

    /**
     * 发放订单佣金
     *
     * @param int $orderId 当前业务订单的内部编号。
     * @return void 无返回值；按永久商品佣金快照发放到期佣金。
     * @throws AdminResponseException
     */
    protected function issueOrderCommission(int $orderId): void
    {
        $this->transaction(function () use ($orderId) {
            $order = $this->loadOrderForUpdate($orderId);
            $orderData = $order->toArray();
            if ((int)($orderData['commission_status'] ?? 0) === Order::COMMISSION_STATUS_GRANTED) {
                return;
            }

            $firstCommissionAmount = $this->normalizeMoney((float)($orderData['first_commission_amount'] ?? 0));
            $secondCommissionAmount = $this->normalizeMoney((float)($orderData['second_commission_amount'] ?? 0));
            $now = date('Y-m-d H:i:s');
            $firstReferrerId = (int)($orderData['referrer_id'] ?? 0);
            $secondReferrerId = (int)($orderData['second_referrer_id'] ?? 0);
            $orderSn = (string)($orderData['order_sn'] ?? '');
            $sourceUserId = (int)($orderData['user_id'] ?? 0);

            if ($firstCommissionAmount > 0 && $firstReferrerId > 0) {
                app()->make(UserAssetService::class)->addCommission(
                    $firstReferrerId,
                    $firstCommissionAmount,
                    $sourceUserId,
                    $orderSn,
                    '订单返佣（一级）'
                );
            }

            if ($secondCommissionAmount > 0 && $secondReferrerId > 0) {
                app()->make(UserAssetService::class)->addCommission(
                    $secondReferrerId,
                    $secondCommissionAmount,
                    $sourceUserId,
                    $orderSn,
                    '订单返佣（二级）'
                );
            }

            $updateData = [
                'first_commission_amount' => $firstCommissionAmount,
                'second_commission_amount' => $secondCommissionAmount,
                'commission_status' => ($firstCommissionAmount > 0 || $secondCommissionAmount > 0)
                    ? Order::COMMISSION_STATUS_GRANTED
                    : Order::COMMISSION_STATUS_PENDING,
                'commission_time' => ($firstCommissionAmount > 0 || $secondCommissionAmount > 0) ? $now : null,
            ];

            $order->save($updateData);
        });
    }

    /**
     * 还原退款相关资源
     *
     * @param Model $order 当前业务订单的模型或不可变数据快照。
     * @return void 无返回值；按退款业务规则回补权益、实物库存及优惠资源。
     * @throws AdminResponseException
     */
    protected function restoreRefundResources(Model $order): void
    {
        $orderData = $order->toArray();
        $orderId = (int)($orderData['id'] ?? 0);
        $userId = (int)($orderData['user_id'] ?? 0);
        $orderSn = (string)($orderData['order_sn'] ?? '');

        if ((int)($orderData['use_points'] ?? 0) > 0) {
            app()->make(UserAssetService::class)->changeAsset(
                $userId,
                'points',
                (float)($orderData['use_points'] ?? 0),
                'in',
                '订单退款退回积分',
                [
                    'source_type' => 'order_refund_points',
                    'order_no' => $orderSn,
                    'source_desc' => '订单退款退回积分',
                ]
            );
        }

        if ((int)($orderData['platform_coupon_id'] ?? 0) > 0) {
            app()->make(StoreCouponUserService::class)->returnCoupon((int)$orderData['platform_coupon_id']);
        }

        $this->returnMerchantCouponIfReady($orderData);

        if (app()->make(PluginCheckoutCapabilityService::class)->isHotelOrder($orderData)) {
            // 酒店房晚由插件生命周期释放，不能再走宿主普通库存或内容权益回补。
            return;
        }

        if ($this->isVirtualOrderType((int)($orderData['order_type'] ?? Order::ORDER_TYPE_GOODS))) {
            app()->make(UserPurchaseRecordService::class)->revokeOrderRights($orderId);
            return;
        }

        // 退款金额不能作为实际退货凭证；只有未发货订单的整单撤销才回补全部库存。
        if (empty($orderData['delivery_time'])) {
            $this->restoreGoodsStock($orderId);
        }
    }

    /**
     * 同商户拆成多张子单时，只有该商户子单全部结束后才退回商户券。
     *
     * @param array $orderData 已加载的业务订单数据及成交金额快照。
     * @return void 无返回值；仅同商户子单均结束后归还已使用商户券。
     */
    protected function returnMerchantCouponIfReady(array $orderData): void
    {
        $couponId = (int)($orderData['merchant_coupon_id'] ?? 0);
        $orderRole = (int)($orderData['order_role'] ?? Order::ROLE_NORMAL);
        $merchantId = (int)($orderData['merchant_id'] ?? 0);
        $mainOrderSn = (string)($orderData['main_order_sn'] ?? '');

        if ($orderRole !== Order::ROLE_CHILD || (int)($orderData['pid'] ?? 0) <= 0) {
            $this->returnCouponIfUsed($couponId);
            return;
        }

        if ($merchantId <= 0 || $mainOrderSn === '') {
            $this->returnCouponIfUsed($couponId);
            return;
        }

        if (!$this->isMerchantChildOrderGroupFinished($mainOrderSn, $merchantId)) {
            return;
        }

        $boundCouponId = (int)$this->getModel()
            ->where('main_order_sn', $mainOrderSn)
            ->where('order_role', Order::ROLE_CHILD)
            ->where('merchant_id', $merchantId)
            ->where('merchant_coupon_id', '>', 0)
            ->value('merchant_coupon_id');

        $this->returnCouponIfUsed($boundCouponId);
    }

    /**
     * 判断同一主单、同一商户下是否仍有未完成退款或未取消的子单。
     *
     * @param string $mainOrderSn 同商户业务子单所属的合并支付主单号。
     * @param int $merchantId 操作或结算所属商户编号，零表示平台。
     * @return bool 同主单同商户的所有子订单是否均已取消或已全额退款。
     */
    protected function isMerchantChildOrderGroupFinished(string $mainOrderSn, int $merchantId): bool
    {
        if ($mainOrderSn === '' || $merchantId <= 0) {
            return true;
        }

        $activeCount = (int)$this->getModel()
            ->where('main_order_sn', $mainOrderSn)
            ->where('order_role', Order::ROLE_CHILD)
            ->where('merchant_id', $merchantId)
            ->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_REFUNDED])
            ->count();

        return $activeCount === 0;
    }

    /**
     * 优惠券退回非幂等，先确认仍是已使用状态。
     *
     * @param int $couponId 已使用或需要归还的用户优惠券记录编号。
     * @return void 无返回值；仅用户券仍为已使用状态时归还。
     */
    protected function returnCouponIfUsed(int $couponId): void
    {
        if ($couponId <= 0) {
            return;
        }

        $couponService = app()->make(StoreCouponUserService::class);
        $coupon = $couponService->get($couponId, ['id', 'status']);
        if (!$coupon) {
            $couponService->returnCoupon($couponId);
            return;
        }

        if ((int)$coupon['status'] !== StoreCouponUser::STATUS_USED) {
            return;
        }

        $couponService->returnCoupon($couponId);
    }

    /**
     * 已发放佣金在退款成功后按订单商品行快照冲正。
     *
     * 未发放的订单本来不会释放佣金；已发放后退款则按 order_goods 中保存的佣金快照扣回。
     *
     * @param array $orderData 已加载的业务订单数据及成交金额快照。
     * @param string $refundSn 固定的退款业务单号。
     * @param float $totalRefundedAmount 当前订单累计成功退款金额，单位为元。
     * @param float $payAmount 订单实际支付金额，单位为元。
     * @return void 无返回值；按商品成交快照冲正已发放的退款佣金。
     */
    protected function reverseReleasedOrderCommission(
        array $orderData,
        string $refundSn,
        float $totalRefundedAmount,
        float $payAmount
    ): void {
        if ((int)($orderData['commission_status'] ?? 0) !== Order::COMMISSION_STATUS_GRANTED) {
            return;
        }

        $orderId = (int)($orderData['id'] ?? 0);
        $orderSn = (string)($orderData['order_sn'] ?? '');
        if ($orderId <= 0 || $orderSn === '') {
            return;
        }

        $goodsList = app()->make(OrderGoodsService::class)->getByOrderId($orderId);
        if ($goodsList === []) {
            return;
        }

        $firstAmounts = [];
        $secondAmounts = [];
        $refundKey = $refundSn !== '' ? $refundSn : 'order-' . $orderId;
        $refundItems = $refundSn !== ''
            ? app()->make(StoreRefundProductService::class)->getActiveByRefundSn($refundSn)
            : [];

        if ($refundItems !== []) {
            $goodsMap = [];
            foreach ($goodsList as $goods) {
                $goodsMap[(int)($goods['id'] ?? 0)] = $goods;
            }

            foreach ($refundItems as $refundItem) {
                $orderGoodsId = (int)($refundItem['order_goods_id'] ?? 0);
                if (!isset($goodsMap[$orderGoodsId])) {
                    continue;
                }
                $ratio = $this->resolveCommissionRefundRatio(
                    $goodsMap[$orderGoodsId],
                    (float)($refundItem['refund_amount'] ?? 0)
                );
                $this->collectCommissionReverseAmounts($goodsMap[$orderGoodsId], $ratio, $firstAmounts, $secondAmounts);
            }
        } else {
            $ratio = $payAmount > 0 ? min(1.0, $this->normalizeMoney($totalRefundedAmount) / $payAmount) : 1.0;
            foreach ($goodsList as $goods) {
                $this->collectCommissionReverseAmounts($goods, $ratio, $firstAmounts, $secondAmounts);
            }
        }

        $sourceUserId = (int)($orderData['user_id'] ?? 0);
        foreach ($firstAmounts as $userId => $amount) {
            $this->reverseUserCommission((int)$userId, (float)$amount, $sourceUserId, $orderSn, '一级', $refundKey);
        }
        foreach ($secondAmounts as $userId => $amount) {
            $this->reverseUserCommission((int)$userId, (float)$amount, $sourceUserId, $orderSn, '二级', $refundKey);
        }
    }

    /**
     * 汇总单个商品行需要冲正的一级/二级佣金。
     *
     * @param array $goods 需要处理的永久订单商品明细。
     * @param float $ratio 商品已成功退款金额相对返佣基数的冲正比例。
     * @param array $firstAmounts 按推广人编号累计的一级佣金冲正金额。
     * @param array $secondAmounts 按推广人编号累计的二级佣金冲正金额。
     * @return void 无返回值；按引用累计各推广人的一级及二级佣金冲正金额。
     */
    protected function collectCommissionReverseAmounts(
        array $goods,
        float $ratio,
        array &$firstAmounts,
        array &$secondAmounts
    ): void {
        if ($ratio <= 0) {
            return;
        }

        $firstReferrerId = (int)($goods['first_referrer_id'] ?? 0);
        $secondReferrerId = (int)($goods['second_referrer_id'] ?? 0);
        $firstAmount = $this->normalizeMoney((float)($goods['first_commission'] ?? 0) * $ratio);
        $secondAmount = $this->normalizeMoney((float)($goods['second_commission'] ?? 0) * $ratio);

        if ($firstReferrerId > 0 && $firstAmount > 0) {
            $firstAmounts[$firstReferrerId] = $this->moneyAdd($firstAmounts[$firstReferrerId] ?? 0, $firstAmount);
        }
        if ($secondReferrerId > 0 && $secondAmount > 0) {
            $secondAmounts[$secondReferrerId] = $this->moneyAdd($secondAmounts[$secondReferrerId] ?? 0, $secondAmount);
        }
    }

    /**
     * 根据商品行返佣基数和退款金额计算本次冲正比例。
     *
     * @param array $goods 需要处理的永久订单商品明细。
     * @param float $refundAmount 本次申请或确认退款的金额，单位为元。
     * @return float 商品退款相对返佣基数的冲正比例，范围为零到一。
     */
    protected function resolveCommissionRefundRatio(array $goods, float $refundAmount): float
    {
        $baseAmount = $this->normalizeMoney((float)($goods['commission_base_amount'] ?? 0));
        $refundAmount = $this->normalizeMoney($refundAmount);
        if ($baseAmount <= 0 || $refundAmount <= 0) {
            return 0.0;
        }

        return min(1.0, $refundAmount / $baseAmount);
    }

    /**
     * 扣减已发放佣金，并按订单号、退款单号和层级保持幂等。
     *
     * @param int $userId 当前业务用户编号。
     * @param float $amount 待计算或标准化的金额，单位为元。
     * @param int $sourceUserId 产生原佣金的下单用户编号。
     * @param string $orderSn 宿主订单的业务单号。
     * @param string $level 需要冲正的分销佣金层级。
     * @param string $refundKey 由退款单和商品明细组成的幂等标识。
     * @return void 无返回值；按退款幂等标识扣回已发放的用户佣金。
     */
    protected function reverseUserCommission(
        int $userId,
        float $amount,
        int $sourceUserId,
        string $orderSn,
        string $level,
        string $refundKey
    ): void {
        $amount = $this->normalizeMoney($amount);
        if ($userId <= 0 || $amount <= 0 || $orderSn === '') {
            return;
        }

        $sourceDesc = '订单退款返佣冲正（' . $level . '）：' . $refundKey;
        $exists = app()->make(UserAssetLogService::class)->getModel()
            ->where('user_id', $userId)
            ->where('type', 'commission')
            ->where('change_type', 'out')
            ->where('source_type', 'order_commission_refund')
            ->where('order_no', $orderSn)
            ->where('source_desc', $sourceDesc)
            ->count();
        if ($exists > 0) {
            return;
        }

        app()->make(UserAssetService::class)->changeAsset(
            $userId,
            'commission',
            $amount,
            'out',
            '订单退款返佣冲正（' . $level . '）',
            [
                'source_type' => 'order_commission_refund',
                'order_no' => $orderSn,
                'source_user_id' => $sourceUserId,
                'source_desc' => $sourceDesc,
            ]
        );
    }

    /**
     * 回补商品库存
     *
     * @param int $orderId 当前业务订单的内部编号。
     * @return void 无返回值；按永久库存凭证回补取消订单库存。
     */
    protected function restoreGoodsStock(int $orderId): void
    {
        app()->make(OrderInventoryService::class)->restoreCancelled($orderId);
    }

    /**
     * 读取累计的永久库存凭证，供活动订单财务状态流转使用。
     *
     * @param int $orderId 当前业务订单的内部编号。
     * @return array 以订单商品编号携带累计已回补件数的永久库存凭证。
     */
    protected function inventoryEvidence(int $orderId): array
    {
        return array_map(static fn (array $goods): array => [
            'order_goods_id' => (int) $goods['id'],
            'stock_restored_num' => (int) ($goods['stock_restored_num'] ?? 0),
        ], app()->make(OrderGoodsService::class)->getByOrderId($orderId));
    }

    /**
     * 汇总订单佣金金额
     *
     * @param int $orderId 当前业务订单的内部编号。
     * @return array 订单商品累计的一级和二级佣金金额，单位为元。
     */
    protected function sumOrderCommissionAmounts(int $orderId): array
    {
        $amounts = app()->make(OrderGoodsService::class)->sumCommissionAmounts($orderId);

        return [(float)($amounts['first'] ?? 0), (float)($amounts['second'] ?? 0)];
    }

    /**
     * 金额标准化。
     *
     * @param float|string $amount 待计算或标准化的金额，单位为元。
     * @return float 保留两位小数的标准金额，单位为元。
     */
    protected function normalizeMoney(float|string $amount): float
    {
        return (float)bcadd((string)$amount, '0', 2);
    }

    /**
     * 高精度金额相加。
     *
     * @param float|string $left 参与高精度运算的左侧金额，单位为元。
     * @param float|string $right 参与高精度运算的右侧金额，单位为元。
     * @return float 两侧金额相加后的标准金额，单位为元。
     */
    protected function moneyAdd(float|string $left, float|string $right): float
    {
        return (float)bcadd((string)$left, (string)$right, 2);
    }

    /**
     * 高精度金额相减。
     *
     * @param float|string $left 参与高精度运算的左侧金额，单位为元。
     * @param float|string $right 参与高精度运算的右侧金额，单位为元。
     * @return float 左侧金额减去右侧金额的标准结果，单位为元。
     */
    protected function moneySub(float|string $left, float|string $right): float
    {
        return (float)bcsub((string)$left, (string)$right, 2);
    }

    /**
     * 高精度金额比较。
     *
     * @param float|string $left 参与高精度运算的左侧金额，单位为元。
     * @param float|string $right 参与高精度运算的右侧金额，单位为元。
     * @return int 左侧小于、等于或大于右侧时分别返回负一、零或一。
     */
    protected function moneyCompare(float|string $left, float|string $right): int
    {
        return bccomp((string)$left, (string)$right, 2);
    }

    /**
     * 获取二级推广人标识
     *
     * @param int $userId 当前业务用户编号。
     * @param int $firstReferrerId 当前订单一级推广人编号。
     * @return int 当前用户有效的二级推广人编号；不存在时为零。
     */
    protected function resolveSecondReferrerId(int $userId, int $firstReferrerId): int
    {
        if ($userId <= 0 || $firstReferrerId <= 0) {
            return 0;
        }

        $firstReferrer = app()->make(UserService::class)->get($firstReferrerId);
        if (!$firstReferrer) {
            return 0;
        }

        return (int)($firstReferrer['spread_uid'] ?? 0);
    }

    /**
     * 获取订单状态文案
     *
     * @param int $status 当前业务状态或列表状态筛选值。
     * @return string 系统订单状态对应的中文文案。
     */
    protected function resolveStatusText(int $status): string
    {
        return match ($status) {
            Order::STATUS_PAID => '已支付',
            Order::STATUS_COMPLETED => '已完成',
            Order::STATUS_CANCELLED => '已取消',
            Order::STATUS_REFUNDED => '已退款',
            Order::STATUS_REFUNDING => '退款中',
            Order::STATUS_WAIT_REVIEW => '待评价',
            default => '待支付',
        };
    }

    /**
     * 获取订单业务节点展示文案。
     *
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @param array $goodsList 当前业务订单的商品成交明细列表。
     * @param array|null $actions 已解析的订单操作状态；为空时重新计算。
     * @return string 结合配送和评价节点计算的业务展示状态文案。
     */
    protected function resolveOrderDisplayStatusText(array $order, array $goodsList = [], array|null $actions = null): string
    {
        $status = (int)($order['status'] ?? Order::STATUS_PENDING);
        if (!in_array($status, [Order::STATUS_PAID, Order::STATUS_WAIT_REVIEW, Order::STATUS_COMPLETED], true)) {
            return $this->resolveStatusText($status);
        }

        if ($status === Order::STATUS_PAID && $this->hasPhysicalOrderGoods($goodsList)) {
            if (empty($order['delivery_time'])) {
                return '待发货';
            }

            return empty($order['receive_time']) ? '待收货' : '待评价';
        }

        if ($status === Order::STATUS_WAIT_REVIEW) {
            return '待评价';
        }

        $actions = $actions ?? $this->resolveOrderActions($order);
        if (!empty($actions['can_review'])) {
            return '待评价';
        }

        return $this->resolveStatusText($status);
    }

    /**
     * 获取佣金状态文案
     *
     * @param int $status 订单佣金冻结、待处理或已发放状态编码。
     * @return string 当前订单佣金处理状态的中文文案。
     */
    protected function resolveCommissionStatusText(int $status): string
    {
        return match ($status) {
            Order::COMMISSION_STATUS_FROZEN => '冻结中',
            Order::COMMISSION_STATUS_GRANTED => '已发放',
            default => '待处理',
        };
    }

    /**
     * 退款失败回滚到原状态
     *
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @return int 退款申请失败或取消后应恢复的原业务状态。
     */
    protected function resolveRefundRollbackStatus(array $order): int
    {
        if (app()->make(PluginCheckoutCapabilityService::class)->isHotelOrder($order)) {
            return !empty($order['receive_time']) || !empty($order['finish_time'])
                ? Order::STATUS_COMPLETED
                : Order::STATUS_PAID;
        }

        if ($this->isVirtualOrderType((int)($order['order_type'] ?? Order::ORDER_TYPE_GOODS))) {
            return !empty($order['finish_time'])
                ? Order::STATUS_COMPLETED
                : Order::STATUS_WAIT_REVIEW;
        }

        return !empty($order['receive_time']) || !empty($order['finish_time'])
            ? Order::STATUS_COMPLETED
            : Order::STATUS_PAID;
    }

    /**
     * 是否虚拟订单
     *
     * @param int $orderType 订单资源类型，区分商品、内容与专题。
     * @return bool 当前订单类型是否为内容或专题等虚拟资源。
     */
    protected function isVirtualOrderType(int $orderType): bool
    {
        return in_array($orderType, [Order::ORDER_TYPE_CONTENT, Order::ORDER_TYPE_SPECIAL], true);
    }

    /**
     * 已支付业务订单状态集合。
     *
     * @return array 可视为已成功支付的业务订单状态集合。
     */
    protected function paidBusinessStatuses(): array
    {
        return [Order::STATUS_PAID, Order::STATUS_WAIT_REVIEW, Order::STATUS_COMPLETED];
    }

    /**
     * 是否已经支付成功。
     *
     * @param int $status 当前业务状态或列表状态筛选值。
     * @return bool 当前订单状态是否表示支付已成功。
     */
    protected function isPaidStatus(int $status): bool
    {
        return in_array($status, $this->paidBusinessStatuses(), true);
    }

    /**
     * 计算待支付订单剩余支付秒数。
     *
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @return int 当前待支付订单剩余支付秒数。
     */
    protected function resolvePayExpireSeconds(array $order): int
    {
        return app()->make(OrderPaymentDeadlineService::class)->remaining($order);
    }

    /**
     * 判断订单是否曾支付成功。
     *
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @param array $payRecord 当前订单已加载的支付记录。
     * @return bool 订单状态、成功时间或支付记录是否证明支付已成功。
     */
    protected function hasOrderPaymentSucceeded(array $order, array $payRecord = []): bool
    {
        if ($this->isPaidStatus((int)($order['status'] ?? Order::STATUS_PENDING))) {
            return true;
        }

        if (!empty($order['pay_time'])) {
            return true;
        }

        return (int)($payRecord['status'] ?? OrderPayRecordService::STATUS_PENDING)
            === OrderPayRecordService::STATUS_SUCCESS;
    }

    /**
     * 获取支付结果需要聚合的主子订单号。
     *
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @return array 支付结果需要聚合查询的主单及子单业务单号。
     */
    protected function getPaymentResultOrderSns(array $order): array
    {
        $orderSn = trim((string)($order['order_sn'] ?? ''));
        $mainOrderSn = trim((string)($order['main_order_sn'] ?? ''));
        $orderRole = (int)($order['order_role'] ?? Order::ROLE_NORMAL);

        if ($orderRole === Order::ROLE_NORMAL || $mainOrderSn === '') {
            return $orderSn !== '' ? [$orderSn] : [];
        }

        $orderSns = $this->getModel()
            ->where('user_id', (int)($order['user_id'] ?? 0))
            ->where('main_order_sn', $mainOrderSn)
            ->order('order_role asc, id asc')
            ->column('order_sn');

        return array_values(array_unique(array_filter(array_map('strval', $orderSns))));
    }

    /**
     * 获取支付成功副标题。
     *
     * @param array|int $order 业务订单快照或历史订单类型编号。
     * @return string 与主子单及资源类型匹配的支付成功业务文案。
     */
    protected function getPaymentSuccessSubtitle(array|int $order): string
    {
        $orderType = is_array($order)
            ? (int)($order['order_type'] ?? Order::ORDER_TYPE_GOODS)
            : (int)$order;

        if (is_array($order) && (int)($order['order_role'] ?? Order::ROLE_NORMAL) === Order::ROLE_MAIN) {
            $orderTypes = $this->normalizeOrderTypeList(
                $this->getMainChildOrderTypes((int)($order['id'] ?? 0)),
                $orderType
            );
            if (count($orderTypes) > 1) {
                return '支付完成，商品已分别处理';
            }

            $orderType = (int)($orderTypes[0] ?? $orderType);
        }

        if ($this->isVirtualOrderType($orderType)) {
            return '支付完成，内容权益已到账';
        }

        return '订单已提交，商家将尽快发货';
    }

    /**
     * 是否允许发起退款。
     *
     * @param int $status 当前业务状态或列表状态筛选值。
     * @return bool 当前业务状态是否允许提交退款申请。
     */
    protected function isRefundableStatus(int $status): bool
    {
        return in_array($status, [Order::STATUS_PAID, Order::STATUS_WAIT_REVIEW], true);
    }

    /**
     * 加锁获取订单
     *
     * @param int $orderId 当前业务订单的内部编号。
     * @return Model 通过行锁读取的业务订单模型。
     * @throws AdminResponseException
     */
    protected function loadOrderForUpdate(int $orderId): Model
    {
        $order = $this->getModel()->where('id', $orderId)->lock(true)->find();
        if (!$order) {
            throw new AdminResponseException('订单不存在');
        }

        return $order;
    }

    /**
     * 根据订单号加锁获取订单
     *
     * @param string $orderSn 宿主订单的业务单号。
     * @return Model 按业务单号通过行锁读取的订单模型。
     * @throws AdminResponseException
     */
    protected function loadOrderByOrderSnForUpdate(string $orderSn): Model
    {
        $order = $this->getModel()
            ->where('order_sn', $orderSn)
            ->lock(true)
            ->find();

        if (!$order) {
            throw new AdminResponseException('订单不存在');
        }

        return $order;
    }

    /**
     * 加锁获取用户订单
     *
     * @param int $userId 当前业务用户编号。
     * @param string $orderSn 宿主订单的业务单号。
     * @return Model 已校验用户归属并加行锁的订单模型。
     * @throws AdminResponseException
     */
    protected function loadUserOrderForUpdate(int $userId, string $orderSn): Model
    {
        $order = $this->getModel()
            ->where('user_id', $userId)
            ->where('order_sn', $orderSn)
            ->lock(true)
            ->find();

        if (!$order) {
            throw new AdminResponseException('订单不存在');
        }

        return $order;
    }

    /**
     * 获取用户各状态订单角标统计
     *
     * @param int $userId 当前业务用户编号。
     * @return array 当前用户各业务状态的订单角标数量。
     */
    public function getUserOrderBadge(int $userId): array
    {
        $model = $this->getModel();

        return [
            'pending'   => (int)$model->where('user_id', $userId)
                ->where('status', Order::STATUS_PENDING)
                ->whereIn('order_role', [Order::ROLE_NORMAL, Order::ROLE_MAIN])
                ->count(),
            'paid'      => (int)$model->where('user_id', $userId)
                ->where('status', Order::STATUS_PAID)
                ->where('order_role', '<>', Order::ROLE_MAIN)
                ->count(),
            'completed' => (int)$model->where('user_id', $userId)
                ->where('status', Order::STATUS_WAIT_REVIEW)
                ->where('order_role', '<>', Order::ROLE_MAIN)
                ->count(),
            'refunding' => (int)$model->where('user_id', $userId)
                ->where('status', Order::STATUS_REFUNDING)
                ->where('order_role', '<>', Order::ROLE_MAIN)
                ->count(),
        ];
    }

    /**
     * 根据 order_id 获取用户订单详情
     *
     * @param int $userId 当前业务用户编号。
     * @param int $orderId 当前业务订单的内部编号。
     * @return array 按内部编号校验用户归属后的订单及商品详情。
     * @throws AdminResponseException
     */
    public function getUserOrderDetailById(int $userId, int $orderId): array
    {
        $order = $this->get(['id' => $orderId, 'user_id' => $userId]);
        if (!$order) {
            throw new AdminResponseException('订单不存在');
        }

        return $this->buildUserOrderDetailItem(
            $order->toArray(),
            app()->make(OrderGoodsService::class)->getByOrderId($orderId)
        );
    }

    /**
     * 用户删除订单（仅已完成或已取消可删，软删除）
     *
     * @param int $userId 当前业务用户编号。
     * @param string $orderSn 宿主订单的业务单号。
     * @return void 无返回值；仅将允许删除的用户订单软删除。
     * @throws AdminResponseException
     */
    public function deleteUserOrder(int $userId, string $orderSn): void
    {
        $order = $this->get(['user_id' => $userId, 'order_sn' => $orderSn]);
        if (!$order) {
            throw new AdminResponseException('订单不存在');
        }

        $status = (int)$order['status'];
        if (!in_array($status, [Order::STATUS_COMPLETED, Order::STATUS_CANCELLED], true)) {
            throw new AdminResponseException('仅已完成或已取消的订单可以删除');
        }

        if ((int)($order['order_role'] ?? Order::ROLE_NORMAL) === Order::ROLE_MAIN) {
            $this->getModel()
                ->where('pid', (int)$order['id'])
                ->where('order_role', Order::ROLE_CHILD)
                ->delete();
        }

        $order->delete();
    }

}
