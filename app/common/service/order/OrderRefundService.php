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

use app\common\exception\AdminResponseException;
use app\common\model\order\Order;
use app\common\model\order\OrderRefund;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 订单退款记录服务。
 *
 * 负责退款主记录的创建、查询、移动端退款展示数据组装，以及退款成功、失败、
 * 取消等状态标记；订单状态流转和实际支付退款由订单/支付服务完成。
 */
class OrderRefundService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 待处理：退款申请已提交，等待审核或等待调用支付退款。
     */
    public const STATUS_PENDING = 0;

    /**
     * 成功：支付渠道已确认退款成功。
     */
    public const STATUS_SUCCESS = 1;

    /**
     * 失败：支付渠道退款失败或审核拒绝。
     */
    public const STATUS_FAILED = 2;

    /**
     * 已取消：用户主动取消退款申请。
     */
    public const STATUS_CANCELLED = 3;

    /**
     * 设置当前服务对应的退款记录模型。
     *
     * @return string 退款记录模型类名。
     */
    protected function setModel(): string
    {
        return OrderRefund::class;
    }

    /**
     * 获取后台退款记录列表。
     *
     * 以 order_refund 为主表分页，确保已退款、失败或已取消的历史退款记录
     * 不会因为订单当前状态变化而从退款列表消失。
     *
     * @param array $params 查询参数。
     * @return array 分页退款记录，list 项兼容后台订单列表展示结构。
     */
    public function getAdminRefundList(array $params = []): array
    {
        [$requestPage, $requestLimit, $defaultLimit] = $this->getPagesValue();
        $page = $requestPage > 0 ? $requestPage : max(1, (int)($params['page'] ?? 1));
        $limit = $requestLimit > 0 ? $requestLimit : (int)($params['limit'] ?? $defaultLimit);
        $limit = max(1, min(100, $limit > 0 ? $limit : 15));

        $query = $this->getModel()->where('id', '>', 0);
        $this->applyAdminRefundRecordFilters($query, $params);

        $orderRefs = app()->make(OrderService::class)->getAdminRefundOrderReferences($params);
        if ($orderRefs !== null) {
            $filterOrderIds = $orderRefs['ids'] ?? [];
            $filterOrderSns = $orderRefs['order_sns'] ?? [];
            if ($filterOrderIds === [] && $filterOrderSns === []) {
                return $this->emptyAdminRefundResult($page, $limit);
            }

            $query->where(function ($subQuery) use ($filterOrderIds, $filterOrderSns): void {
                if ($filterOrderIds !== []) {
                    $subQuery->whereIn('order_id', $filterOrderIds);
                    if ($filterOrderSns !== []) {
                        $subQuery->whereOr('order_sn', 'in', $filterOrderSns);
                    }
                    return;
                }

                $subQuery->whereIn('order_sn', $filterOrderSns);
            });
        }

        $count = (int)(clone $query)->count();
        $stats = $this->buildAdminRefundStats($query, $count);
        $records = $query->order('id desc')->page($page, $limit)->select()->toArray();
        if ($records === []) {
            return [
                'list' => [],
                'total' => $count,
                'page' => $page,
                'limit' => $limit,
                'totalPage' => $limit > 0 ? (int)ceil($count / $limit) : 0,
                'stats' => $stats,
            ];
        }

        $orderIds = array_values(array_unique(array_filter(array_map('intval', array_column($records, 'order_id')))));
        $orderSns = array_values(array_unique(array_filter(array_map('strval', array_column($records, 'order_sn')))));
        $orderMap = app()->make(OrderService::class)->getAdminOrderDisplayMapByRefs($orderIds, $orderSns);

        $list = [];
        foreach ($records as $record) {
            $orderId = (int)($record['order_id'] ?? 0);
            $orderSn = (string)($record['order_sn'] ?? '');
            $order = $orderMap['ids'][$orderId] ?? ($orderMap['order_sns'][$orderSn] ?? []);
            $list[] = $this->buildAdminRefundListItem($record, $order);
        }

        return [
            'list' => $list,
            'total' => $count,
            'page' => $page,
            'limit' => $limit,
            'totalPage' => $limit > 0 ? (int)ceil($count / $limit) : 0,
            'stats' => $stats,
        ];
    }

    /**
     * 应用后台退款记录主表筛选。
     *
     * @param mixed $query 需要追加筛选条件的查询对象。
     * @param array $params 已校验的列表筛选或业务操作参数。
     * @return void 无返回值；仅追加后台退款记录筛选条件。
     */
    protected function applyAdminRefundRecordFilters($query, array $params): void
    {
        $orderSn = trim((string)($params['order_sn'] ?? ''));
        $refundSn = trim((string)($params['refund_sn'] ?? ''));
        $payType = trim((string)($params['pay_type'] ?? ''));
        $userId = (int)($params['user_id'] ?? 0);
        $startTime = trim((string)($params['start_time'] ?? ''));
        $endTime = trim((string)($params['end_time'] ?? ''));

        if ($orderSn !== '') {
            $query->where('order_sn', 'like', '%' . $orderSn . '%');
        }
        if ($refundSn !== '') {
            $query->where('refund_sn', 'like', '%' . $refundSn . '%');
        }
        if ($payType !== '') {
            $query->where('pay_type', $payType);
        }
        if ($userId > 0) {
            $query->where('user_id', $userId);
        }

        $recordStatus = trim((string)($params['refund_record_status'] ?? ''));
        if ($recordStatus !== '') {
            $query->where('status', (int)$recordStatus);
        } else {
            $statuses = $this->resolveAdminRefundStatusFilter((string)($params['refund_status'] ?? ''));
            if ($statuses !== []) {
                $query->whereIn('status', $statuses);
            }
        }

        if ($startTime !== '' && $endTime !== '') {
            $query->whereBetweenTime(
                'create_time',
                $this->normalizeAdminRefundStartTime($startTime),
                $this->normalizeAdminRefundEndTime($endTime)
            );
        } elseif ($startTime !== '') {
            $query->where('create_time', '>=', $this->normalizeAdminRefundStartTime($startTime));
        } elseif ($endTime !== '') {
            $query->where('create_time', '<=', $this->normalizeAdminRefundEndTime($endTime));
        }
    }

    /**
     * 构建后台退款统计。
     *
     * @param mixed $query 需要追加筛选条件的查询对象。
     * @param int $count 当前筛选条件下的退款记录总数。
     * @return array 当前筛选下退款总数、待处理、成功、失败及取消数量。
     */
    protected function buildAdminRefundStats($query, int $count): array
    {
        return [
            'total' => $count,
            'pending' => (int)(clone $query)->where('status', self::STATUS_PENDING)->count(),
            'success' => (int)(clone $query)->where('status', self::STATUS_SUCCESS)->count(),
            'failed' => (int)(clone $query)->where('status', self::STATUS_FAILED)->count(),
            'cancelled' => (int)(clone $query)->where('status', self::STATUS_CANCELLED)->count(),
            'totalRefundAmount' => (float)(clone $query)->sum('refund_amount'),
        ];
    }

    /**
     * 构建后台退款列表项。
     *
     * @param array $record 已加载的退款或永久实收补偿记录。
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @return array 合并订单、用户、店铺快照后的后台退款列表项。
     */
    protected function buildAdminRefundListItem(array $record, array $order = []): array
    {
        $recordId = (int)($record['id'] ?? 0);
        $recordStatus = (int)($record['status'] ?? self::STATUS_PENDING);
        $orderId = (int)($order['order_id'] ?? ($order['id'] ?? ($record['order_id'] ?? 0)));
        $orderSn = trim((string)($record['order_sn'] ?? ''));
        if ($orderSn === '') {
            $orderSn = (string)($order['order_sn'] ?? '');
        }

        $payload = $order;
        $payload['id'] = $orderId;
        $payload['order_id'] = $orderId;
        $payload['order_sn'] = $orderSn;
        $payload['row_key'] = 'refund-' . $recordId;
        $payload['refund_record_id'] = $recordId;
        $payload['refund_sn'] = (string)($record['refund_sn'] ?? '');
        $payload['refund_record_status'] = $recordStatus;
        $payload['refund_record_status_text'] = $this->resolveRecordStatusText($recordStatus);
        $payload['refund_status'] = $this->mapRecordStatusToAdminRefundStatus($recordStatus);
        $payload['refund_status_text'] = $this->resolveAdminRefundStatusText($recordStatus);
        $payload['refund_amount'] = (float)($record['refund_amount'] ?? 0);
        $payload['refund_reason'] = $this->resolveAdminRefundReason($record, $order);
        $payload['refund_application_time'] = $record['create_time'] ?? ($order['refund_application_time'] ?? null);
        $payload['refund_reason_time'] = $record['refund_time']
            ?? ($record['notify_time'] ?? ($record['update_time'] ?? ($order['refund_reason_time'] ?? null)));
        $payload['refund_time'] = $record['refund_time'] ?? null;
        $payload['notify_time'] = $record['notify_time'] ?? null;
        $payload['fail_msg'] = (string)($record['error_message'] ?? '');
        $payload['pay_type'] = (string)($order['pay_type'] ?? ($record['pay_type'] ?? ''));
        $payload['user_id'] = (int)($order['user_id'] ?? ($record['user_id'] ?? 0));
        $payload['can_audit_refund'] = $recordStatus === self::STATUS_PENDING;

        if (!isset($payload['create_time'])) {
            $payload['create_time'] = $record['create_time'] ?? null;
        }

        return $payload;
    }

    /**
     * 空后台退款分页结果。
     *
     * @param int $page 当前列表页码。
     * @param int $limit 每页或当前批次最多返回的记录数。
     * @return array 保留当前页码和每页数量的空退款分页及零统计。
     */
    protected function emptyAdminRefundResult(int $page, int $limit): array
    {
        return [
            'list' => [],
            'total' => 0,
            'page' => $page,
            'limit' => $limit,
            'totalPage' => 0,
            'stats' => [
                'total' => 0,
                'pending' => 0,
                'success' => 0,
                'failed' => 0,
                'cancelled' => 0,
                'totalRefundAmount' => 0,
            ],
        ];
    }

    /**
     * 将后台页面退款状态筛选映射到退款记录状态。
     *
     * @param string $value 待解析的原始字段值或筛选值。
     * @return array 前端退款筛选值对应的宿主退款记录状态集合。
     */
    protected function resolveAdminRefundStatusFilter(string $value): array
    {
        $value = trim($value);

        return match ($value) {
            '', 'all', 'refund' => [],
            '1', 'pending', 'refunding' => [self::STATUS_PENDING],
            '2', 'success', 'refunded' => [self::STATUS_SUCCESS],
            'failed', 'fail' => [self::STATUS_FAILED],
            'cancelled', 'cancel' => [self::STATUS_CANCELLED],
            default => [],
        };
    }

    /**
     * 将退款记录状态映射为后台页面使用的退款状态。
     *
     * @param int $status 退款记录状态编码或前端退款状态筛选值。
     * @return int 后台页面使用的退款状态编码。
     */
    protected function mapRecordStatusToAdminRefundStatus(int $status): int
    {
        return match ($status) {
            self::STATUS_PENDING => 1,
            self::STATUS_SUCCESS => 2,
            self::STATUS_FAILED => 4,
            self::STATUS_CANCELLED => 5,
            default => 0,
        };
    }

    /**
     * 获取后台页面退款状态文案。
     *
     * @param int $status 退款记录状态编码或前端退款状态筛选值。
     * @return string 后台页面显示的退款状态文案。
     */
    protected function resolveAdminRefundStatusText(int $status): string
    {
        return match ($status) {
            self::STATUS_PENDING => '退款中',
            self::STATUS_SUCCESS => '已退款',
            self::STATUS_FAILED => '退款失败',
            self::STATUS_CANCELLED => '已取消',
            default => '未退款',
        };
    }

    /**
     * 获取后台退款原因。
     *
     * @param array $record 已加载的退款或永久实收补偿记录。
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @return string 当前退款记录优先、订单资料兜底的退款原因。
     */
    protected function resolveAdminRefundReason(array $record, array $order = []): string
    {
        $reason = trim((string)($record['reason'] ?? ''));
        if ($reason !== '') {
            return $reason;
        }

        $reason = trim((string)($order['refund_reason'] ?? ''));
        if ($reason !== '') {
            return $reason;
        }

        return trim((string)($record['error_message'] ?? ''));
    }

    /**
     * 标准化退款申请开始时间。
     *
     * @param string $time 待标准化的业务时间或物流时间值。
     * @return string 补齐当日开始时间后的退款筛选时间。
     */
    protected function normalizeAdminRefundStartTime(string $time): string
    {
        return strlen($time) <= 10 ? $time . ' 00:00:00' : $time;
    }

    /**
     * 标准化退款申请结束时间。
     *
     * @param string $time 待标准化的业务时间或物流时间值。
     * @return string 补齐当日结束时间后的退款筛选时间。
     */
    protected function normalizeAdminRefundEndTime(string $time): string
    {
        return strlen($time) <= 10 ? $time . ' 23:59:59' : $time;
    }

    /**
     * 创建退款记录。
     *
     * 会先补齐退款记录的默认字段，再写入数据库；调用方只需要传入本次退款
     * 真实产生或需要覆盖的字段。
     *
     * @param array $data 退款记录字段数据，支持 order_id、order_sn、refund_sn、refund_amount 等字段。
     * @return array 创建后的退款记录数组。
     */
    public function createRecord(array $data): array
    {
        $payload = array_merge([
            'order_id' => 0,
            'order_sn' => '',
            'refund_sn' => '',
            'user_id' => 0,
            'pay_type' => '',
            'refund_amount' => 0,
            'status' => self::STATUS_PENDING,
            'reason' => '',
            'transaction_id' => '',
            'refund_id' => '',
            'request_payload' => [],
            'response_payload' => [],
            'notify_payload' => [],
            'error_message' => '',
            'refund_time' => null,
            'notify_time' => null,
        ], $data);

        $record = $this->create($payload);

        return $record->toArray();
    }

    /**
     * 创建或更新订单退款申请记录。
     *
     * 同一个订单在待处理状态下重复提交时复用最近的待处理记录，避免生成多个
     * 待审核退款单；正式审核通过退款时也可以复用该记录的 refund_sn。
     *
     * @param array $order 订单数据。
     * @param float $refundAmount 退款金额。
     * @param string $refundReason 退款原因。
     * @param string $source 申请来源，例如 user（用户）、merchant（商户）、admin（平台管理员）。
     * @return array 创建或更新后的退款记录数组。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function createOrUpdatePending(array $order, float $refundAmount, string $refundReason = '', string $source = ''): array
    {
        $orderSn = (string)($order['order_sn'] ?? '');
        $pending = $this->getPendingByOrderSn($orderSn);
        $payload = [
            'order_id' => (int)($order['id'] ?? 0),
            'order_sn' => $orderSn,
            'user_id' => (int)($order['user_id'] ?? 0),
            'pay_type' => (string)($order['pay_type'] ?? ''),
            'refund_amount' => $refundAmount,
            'status' => self::STATUS_PENDING,
            'reason' => $refundReason,
            'transaction_id' => '',
            'request_payload' => [
                'order_sn' => $orderSn,
                'refund_amount' => $refundAmount,
                'reason' => $refundReason,
                'source' => $source,
            ],
        ];

        if ($pending) {
            $this->update((int)$pending['id'], $payload);
            $fresh = $this->get((int)$pending['id']);

            return $fresh ? $fresh->toArray() : array_merge($pending, $payload);
        }

        return $this->createRecord(array_merge($payload, [
            'refund_sn' => get_sn('RF'),
        ]));
    }

    /**
     * 根据退款单号获取退款记录。
     *
     * @param string $refundSn 退款单号。
     * @return array|null 存在时返回退款记录数组，不存在时返回 null。
     */
    public function getByRefundSn(string $refundSn): ?array
    {
        $record = $this->get(['refund_sn' => $refundSn]);

        return $record ? $record->toArray() : null;
    }

    /**
     * 获取订单最近一条退款中的记录。
     *
     * @param string $orderSn 订单号。
     * @return array|null 存在待处理记录时返回退款记录数组，否则返回空值。
     */
    public function getPendingByOrderSn(string $orderSn): ?array
    {
        $record = $this->getModel()
            ->where('order_sn', $orderSn)
            ->where('status', self::STATUS_PENDING)
            ->order('id desc')
            ->find();

        return $record ? $record->toArray() : null;
    }

    /**
     * 获取移动端退款列表。
     *
     * 当 status 为 record 时返回退款申请历史；其他状态会先查询订单侧退款状态，
     * 再合并最新退款记录，保证前端列表能同时展示订单信息和退款记录信息。
     *
     * @param int $userId 用户ID。
     * @param array $params 查询参数，支持 order_sn、keyword、status。
     * @return array 分页列表数据，list 中包含退款状态、退款单号、可取消标识等字段。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getMobileRefundList(int $userId, array $params = []): array
    {
        $keyword = trim((string)($params['order_sn'] ?? ($params['keyword'] ?? '')));
        $status = trim((string)($params['status'] ?? 'record'));
        if ($status === 'record') {
            return $this->getMobileRefundRecordList($userId, $keyword);
        }

        $data = app()->make(OrderService::class)->getUserRefundOrderList($userId, [
            'order_sn' => $keyword,
            'status' => $status,
        ]);

        $orderSns = array_values(array_filter(array_map(
            static fn(array $item): string => (string)($item['order_sn'] ?? ''),
            $data['list'] ?? []
        )));
        $recordMap = $this->getLatestRecordMapByOrderSns($orderSns, $userId);
        $list = [];
        foreach (($data['list'] ?? []) as $order) {
            $orderSn = (string)($order['order_sn'] ?? '');
            $list[] = $this->buildMobileRefundListItem($order, $recordMap[$orderSn] ?? null);
        }

        $data['list'] = $list;

        return $data;
    }

    /**
     * 获取移动端退款申请记录列表，包含已取消或失败的历史申请。
     *
     * @param int $userId 用户ID。
     * @param string $keyword 订单号关键词。
     * @return array 分页后的退款申请历史列表。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    protected function getMobileRefundRecordList(int $userId, string $keyword = ''): array
    {
        [$page, $limit] = $this->getPagesValue();
        $recordQuery = $this->getModel()
            ->where('user_id', $userId)
            ->order('id desc');
        if ($keyword !== '') {
            $recordQuery->where('order_sn', 'like', '%' . $keyword . '%');
        }

        $records = $recordQuery->select()->toArray();
        $recordMap = [];
        $recordOrderSns = [];
        foreach ($records as $record) {
            $orderSn = (string)($record['order_sn'] ?? '');
            if ($orderSn === '') {
                continue;
            }
            if (!isset($recordMap[$orderSn])) {
                $recordMap[$orderSn] = $record;
            }
            $recordOrderSns[] = $orderSn;
        }

        $orderService = app()->make(OrderService::class);
        $stateOrderSns = $orderService->getUserRefundStateOrderSns($userId, $keyword);
        $orderSns = array_values(array_unique(array_merge($recordOrderSns, $stateOrderSns)));
        if ($orderSns === []) {
            return [
                'list' => [],
                'total' => 0,
                'page' => $page,
                'limit' => $limit,
                'totalPage' => 0,
            ];
        }

        $orders = $orderService->getUserRefundOrdersByOrderSns($userId, $orderSns);
        $orderMap = [];
        foreach ($orders as $order) {
            $orderMap[(string)($order['order_sn'] ?? '')] = $order;
        }

        $list = [];
        foreach ($orderSns as $orderSn) {
            if (!isset($orderMap[$orderSn])) {
                continue;
            }
            $item = $this->buildMobileRefundListItem($orderMap[$orderSn], $recordMap[$orderSn] ?? null);
            $item['_refund_sort_time'] = $this->resolveMobileRefundSortTime($orderMap[$orderSn], $recordMap[$orderSn] ?? null);
            $list[] = $item;
        }

        usort($list, static function (array $left, array $right): int {
            return ($right['_refund_sort_time'] ?? 0) <=> ($left['_refund_sort_time'] ?? 0);
        });
        foreach ($list as &$item) {
            unset($item['_refund_sort_time']);
        }
        unset($item);

        $total = count($list);
        $offset = max(0, ($page - 1) * $limit);
        $pagedList = $limit > 0 ? array_slice($list, $offset, $limit) : $list;

        return [
            'list' => $pagedList,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPage' => $limit > 0 ? (int)ceil($total / $limit) : 0,
        ];
    }

    /**
     * 获取移动端退款记录排序时间。
     *
     * 优先使用退款记录创建时间，其次使用订单退款申请/原因时间，最后兜底订单创建时间。
     *
     * @param array $order 订单数据。
     * @param array|null $record 退款记录数据。
     * @return int 排序用时间戳，解析失败返回 0。
     */
    protected function resolveMobileRefundSortTime(array $order, ?array $record = null): int
    {
        $time = (string)($record['create_time'] ?? '');
        if ($time === '') {
            $time = (string)($order['refund_application_time'] ?? '');
        }
        if ($time === '') {
            $time = (string)($order['refund_reason_time'] ?? '');
        }
        if ($time === '') {
            $time = (string)($order['create_time'] ?? '');
        }

        $timestamp = strtotime($time);

        return $timestamp === false ? 0 : $timestamp;
    }

    /**
     * 获取移动端退款详情。
     *
     * 可通过 refund_sn、order_sn 或 order_id 定位退款详情；如果没有独立退款记录，
     * 但订单本身仍处于退款状态，也会按订单状态构建详情数据。
     *
     * @param int $userId 用户ID。
     * @param array $params 查询参数，支持 refund_sn、order_sn、order_id。
     * @return array 移动端退款详情数据。
     * @throws AdminResponseException
     */
    public function getMobileRefundDetail(int $userId, array $params = []): array
    {
        $refundSn = trim((string)($params['refund_sn'] ?? ''));
        $orderSn = trim((string)($params['order_sn'] ?? ''));
        $orderId = (int)($params['order_id'] ?? 0);
        $record = null;

        if ($refundSn !== '') {
            $record = $this->getByRefundSn($refundSn);
            if (!$record || (int)($record['user_id'] ?? 0) !== $userId) {
                throw new AdminResponseException('退款记录不存在');
            }
            $orderSn = (string)($record['order_sn'] ?? '');
        }

        $orderService = app()->make(OrderService::class);
        if ($orderSn !== '') {
            $order = $orderService->getUserOrderDetail($userId, $orderSn);
        } elseif ($orderId > 0) {
            $order = $orderService->getUserOrderDetailById($userId, $orderId);
        } else {
            throw new AdminResponseException('请传入退款单号或订单号');
        }

        if (!$record) {
            $record = $this->getLatestByOrderSn((string)($order['order_sn'] ?? ''), $userId);
        }
        if (!$record && !$this->hasRefundState($order)) {
            throw new AdminResponseException('退款记录不存在');
        }

        return $this->buildMobileRefundDetail($order, $record);
    }

    /**
     * 取消移动端退款申请。
     *
     * 仅允许取消待处理状态的退款申请；实际订单退款状态回滚由 OrderService
     * 的 cancelUserRefund 负责。
     *
     * @param int $userId 用户ID。
     * @param string $orderSn 订单号，可为空。
     * @param string $refundSn 退款单号，可为空；当订单号为空时用于反查订单。
     * @return array 取消后的移动端退款详情数据。
     * @throws AdminResponseException
     */
    public function cancelMobileRefund(int $userId, string $orderSn = '', string $refundSn = ''): array
    {
        if ($orderSn === '' && $refundSn !== '') {
            $record = $this->getByRefundSn($refundSn);
            if (!$record || (int)($record['user_id'] ?? 0) !== $userId) {
                throw new AdminResponseException('退款记录不存在');
            }
            if ((int)($record['status'] ?? self::STATUS_FAILED) !== self::STATUS_PENDING) {
                throw new AdminResponseException('当前退款申请不支持取消');
            }
            $orderSn = (string)($record['order_sn'] ?? '');
        }

        if ($orderSn === '') {
            throw new AdminResponseException('订单号不能为空');
        }

        $order = app()->make(OrderService::class)->cancelUserRefund($userId, $orderSn);
        $record = $this->getLatestByOrderSn($orderSn, $userId);

        return $this->buildMobileRefundDetail($order, $record);
    }

    /**
     * 获取订单最新退款记录。
     *
     * @param string $orderSn 订单号。
     * @param int $userId 用户ID；大于 0 时会限制只能查询该用户的记录。
     * @return array|null 存在时返回最新退款记录数组，否则返回 null。
     */
    public function getLatestByOrderSn(string $orderSn, int $userId = 0): ?array
    {
        if ($orderSn === '') {
            return null;
        }

        $query = $this->getModel()
            ->where('order_sn', $orderSn)
            ->order('id desc');
        if ($userId > 0) {
            $query->where('user_id', $userId);
        }

        $record = $query->find();

        return $record ? $record->toArray() : null;
    }

    /**
     * 获取订单最新退款记录映射。
     *
     * @param array $orderSns 订单号列表。
     * @param int $userId 用户ID。
     * @return array<string,array> 以订单业务单号为键的最新退款记录映射。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    protected function getLatestRecordMapByOrderSns(array $orderSns, int $userId): array
    {
        $orderSns = array_values(array_unique(array_filter(array_map('strval', $orderSns))));
        if ($orderSns === []) {
            return [];
        }

        $records = $this->getModel()
            ->where('user_id', $userId)
            ->whereIn('order_sn', $orderSns)
            ->order('id desc')
            ->select()
            ->toArray();

        $map = [];
        foreach ($records as $record) {
            $orderSn = (string)($record['order_sn'] ?? '');
            if ($orderSn !== '' && !isset($map[$orderSn])) {
                $map[$orderSn] = $record;
            }
        }

        return $map;
    }

    /**
     * 构建移动端退款列表项。
     *
     * @param array $order 订单数据。
     * @param array|null $record 最新退款记录数据。
     * @return array 合并退款元数据后的列表项。
     */
    protected function buildMobileRefundListItem(array $order, ?array $record = null): array
    {
        $meta = $this->buildRefundMeta($order, $record);

        return array_merge($order, [
            'refund_sn' => $meta['refund_sn'],
            'refund_status' => $meta['refund_status'],
            'refund_status_text' => $meta['refund_status_text'],
            'refund_amount' => $meta['refund_amount'],
            'refund_reason' => $meta['reason'],
            'refund_method' => $meta['refund_method'],
            'apply_time' => $meta['apply_time'],
            'can_cancel_refund' => $meta['can_cancel_refund'],
        ]);
    }

    /**
     * 构建移动端退款详情。
     *
     * @param array $order 订单详情数据。
     * @param array|null $record 最新退款记录数据。
     * @return array 合并退款元数据后的详情数据。
     */
    protected function buildMobileRefundDetail(array $order, ?array $record = null): array
    {
        $meta = $this->buildRefundMeta($order, $record);

        return array_merge($order, [
            'refund_sn' => $meta['refund_sn'],
            'refund_status' => $meta['refund_status'],
            'refund_status_text' => $meta['refund_status_text'],
            'refund_amount' => $meta['refund_amount'],
            'refund_reason' => $meta['reason'],
            'reason' => $meta['reason'],
            'refund_method' => $meta['refund_method'],
            'apply_time' => $meta['apply_time'],
            'can_cancel_refund' => $meta['can_cancel_refund'],
            'refund_record_status' => $meta['refund_record_status'],
            'refund_record_status_text' => $meta['refund_record_status_text'],
        ]);
    }

    /**
     * 构建退款状态元数据。
     *
     * 退款记录状态优先于订单退款状态，用于处理订单状态已回滚但仍需要展示
     * 失败、取消等历史申请结果的场景。
     *
     * @param array $order 订单数据。
     * @param array|null $record 退款记录数据。
     * @return array 退款状态、金额、原因、退款方式、申请时间和可取消标识。
     */
    protected function buildRefundMeta(array $order, ?array $record = null): array
    {
        $refundStatus = (int)($order['refund_status'] ?? 0);
        $recordStatus = $record !== null ? (int)($record['status'] ?? self::STATUS_FAILED) : null;
        $refundStatusText = (string)($order['refund_status_text'] ?? '');

        if ($recordStatus === self::STATUS_PENDING) {
            $refundStatus = 1;
            $refundStatusText = '退款中';
        } elseif ($recordStatus === self::STATUS_SUCCESS && $refundStatus <= 0) {
            $refundStatus = 2;
            $refundStatusText = '已退款';
        } elseif ($recordStatus === self::STATUS_FAILED && $refundStatus <= 0) {
            $refundStatusText = '退款失败';
        } elseif ($recordStatus === self::STATUS_CANCELLED && $refundStatus <= 0) {
            $refundStatusText = '已取消';
        }

        if ($refundStatusText === '') {
            $refundStatusText = match ($refundStatus) {
                3 => '部分退款',
                2 => '已退款',
                1 => '退款中',
                default => '未退款',
            };
        }

        $recordAmount = $record !== null ? (float)($record['refund_amount'] ?? 0) : 0;
        $orderRefundAmount = (float)($order['refund_amount'] ?? 0);
        $payAmount = (float)($order['pay_amount'] ?? 0);
        $reason = trim((string)($record['reason'] ?? ''));
        if ($reason === '') {
            $reason = trim((string)($order['refund_reason'] ?? ''));
        }

        return [
            'refund_sn' => (string)($record['refund_sn'] ?? ''),
            'refund_status' => $refundStatus,
            'refund_status_text' => $refundStatusText,
            'refund_record_status' => $recordStatus,
            'refund_record_status_text' => $this->resolveRecordStatusText($recordStatus),
            'refund_amount' => $recordAmount > 0 ? $recordAmount : ($orderRefundAmount > 0 ? $orderRefundAmount : $payAmount),
            'reason' => $reason,
            'refund_method' => $this->resolveRefundMethod($order),
            'apply_time' => $record['create_time'] ?? ($order['refund_application_time'] ?? null),
            'can_cancel_refund' => $this->canCancelRefund($order, $record),
        ];
    }

    /**
     * 判断订单是否仍处于退款相关状态。
     *
     * @param array $order 订单数据。
     * @return bool 订单退款状态或订单主状态命中退款中/已退款时返回 true。
     */
    protected function hasRefundState(array $order): bool
    {
        return (int)($order['refund_status'] ?? 0) > 0
            || in_array((int)($order['status'] ?? Order::STATUS_PENDING), [
                Order::STATUS_REFUNDING,
                Order::STATUS_REFUNDED,
            ], true);
    }

    /**
     * 判断退款申请是否可取消。
     *
     * @param array $order 订单数据。
     * @param array|null $record 退款记录数据。
     * @return bool 退款记录仍为待处理且订单仍处于退款中时返回 true。
     */
    protected function canCancelRefund(array $order, ?array $record = null): bool
    {
        if ($record !== null && (int)($record['status'] ?? self::STATUS_FAILED) !== self::STATUS_PENDING) {
            return false;
        }

        return (int)($order['refund_status'] ?? 0) === 1
            || (int)($order['status'] ?? Order::STATUS_PENDING) === Order::STATUS_REFUNDING;
    }

    /**
     * 获取退款方式文案。
     *
     * @param array $order 订单数据。
     * @return string 移动端展示的退款路径文案。
     */
    protected function resolveRefundMethod(array $order): string
    {
        $payTypeText = trim((string)($order['pay_type_text'] ?? ''));
        if ($payTypeText === '' || $payTypeText === '未支付') {
            return '原支付路径退回';
        }

        return $payTypeText . '原路退回';
    }

    /**
     * 获取退款记录状态文案。
     *
     * @param int|null $status 退款记录状态。
     * @return string 状态文案；未知状态返回空字符串。
     */
    protected function resolveRecordStatusText(?int $status): string
    {
        return match ($status) {
            self::STATUS_PENDING => '退款中',
            self::STATUS_SUCCESS => '退款成功',
            self::STATUS_FAILED => '退款失败',
            self::STATUS_CANCELLED => '已取消',
            default => '',
        };
    }

    /**
     * 标记退款成功。
     *
     * @param string $refundSn 退款单号。
     * @param array $data 支付渠道退款结果数据，可覆盖 refund_time、notify_time、refund_id 等字段。
     * @return void 无返回值；登记已确认成功的退款记录及渠道凭证。
     */
    public function markSuccess(string $refundSn, array $data = []): void
    {
        $record = $this->get(['refund_sn' => $refundSn]);
        if (!$record) {
            return;
        }

        $this->update((int) $record['id'], array_merge([
            'status' => self::STATUS_SUCCESS,
            'refund_time' => $data['refund_time'] ?? date('Y-m-d H:i:s'),
            'notify_time' => $data['notify_time'] ?? date('Y-m-d H:i:s'),
            'refund_id' => (string) ($data['refund_id'] ?? ($record['refund_id'] ?? '')),
            'notify_payload' => $data['notify_payload'] ?? ($record['notify_payload'] ?? []),
            'response_payload' => $data['response_payload'] ?? ($record['response_payload'] ?? []),
            'error_message' => '',
        ], $data));
    }

    /**
     * 标记退款记录已取消。
     *
     * @param string $orderSn 订单号。
     * @param string $message 取消原因或备注。
     * @return void 无返回值；仅取消当前订单待处理的退款记录。
     */
    public function markCancelledByOrderSn(string $orderSn, string $message = '退款申请已取消'): void
    {
        $record = $this->getPendingByOrderSn($orderSn);
        if (!$record) {
            return;
        }

        $this->update((int)$record['id'], [
            'status' => self::STATUS_CANCELLED,
            'error_message' => $message,
        ]);
    }

    /**
     * 更新退款记录。
     *
     * @param string $refundSn 退款单号。
     * @param array $data 需要更新的退款记录字段。
     * @return void 无返回值；更新指定退款单号对应的业务字段。
     */
    public function updateRecord(string $refundSn, array $data): void
    {
        $record = $this->get(['refund_sn' => $refundSn]);
        if (!$record) {
            return;
        }

        $this->update((int)$record['id'], $data);
    }

    /**
     * 标记退款失败。
     *
     * @param string $refundSn 退款单号。
     * @param string $message 失败原因。
     * @param array $data 支付渠道返回或业务补充数据。
     * @return void 无返回值；按订单号登记支付失败状态和原因。
     */
    public function markFailed(string $refundSn, string $message, array $data = []): void
    {
        $record = $this->get(['refund_sn' => $refundSn]);
        if (!$record) {
            return;
        }

        $this->update((int) $record['id'], array_merge([
            'status' => self::STATUS_FAILED,
            'error_message' => $message,
            'response_payload' => $data['response_payload'] ?? ($record['response_payload'] ?? []),
        ], $data));
    }
}
