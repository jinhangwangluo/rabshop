export const ORDER_STATUS_MAP = {
  0: { label: '待支付', type: 'warning' },
  1: { label: '已支付', type: 'primary' },
  2: { label: '已完成', type: 'success' },
  3: { label: '已取消', type: 'info' },
  4: { label: '已退款', type: 'danger' },
  5: { label: '退款中', type: 'warning' },
  6: { label: '待评价', type: 'warning' },
  '-1': { label: '已取消', type: 'info' }
}

export const REFUND_STATUS_MAP = {
  0: { label: '未退款', type: 'info' },
  1: { label: '退款中', type: 'warning' },
  2: { label: '已退款', type: 'success' }
}

export const PAY_TYPE_MAP = {
  wechat: { label: '微信支付', type: 'success' },
  weixin: { label: '微信支付', type: 'success' },
  wx: { label: '微信支付', type: 'success' },
  yue: { label: '余额支付', type: 'warning' },
  balance: { label: '余额支付', type: 'warning' },
  alipay: { label: '支付宝', type: 'primary' },
  offline: { label: '线下支付', type: 'info' },
  free: { label: '免支付', type: '' }
}

export const ORDER_TYPE_MAP = {
  1: { label: '内容订单', type: '' },
  2: { label: '商品订单', type: 'success' },
  3: { label: '专题订单', type: 'warning' }
}

export const GOODS_TYPE_MAP = {
  1: { label: '实物商品', type: 'success' },
  2: { label: '虚拟商品', type: 'warning' },
  3: { label: '内容商品', type: 'primary' }
}

function toNumber(value, fallback = 0) {
  const num = Number(value)
  return Number.isFinite(num) ? num : fallback
}

function pickFirst(...values) {
  const target = values.find(item => item !== undefined && item !== null && item !== '')
  return target === undefined ? '' : target
}

function normalizeText(value, fallback = '-') {
  if (value === undefined || value === null || value === '') {
    return fallback
  }
  return String(value)
}

function normalizeMoney(value) {
  return Number(toNumber(value, 0).toFixed(2))
}

function toBoolean(value, fallback = false) {
  if (value === undefined || value === null || value === '') {
    return fallback
  }

  return value === true || value === 1 || value === '1'
}

function inferRefundStatus(raw, status) {
  if (raw.refund_status !== undefined && raw.refund_status !== null && raw.refund_status !== '') {
    return toNumber(raw.refund_status, 0)
  }

  if (status === 5) return 1
  if (status === 4) return 2
  if (toNumber(raw.refund_amount, 0) > 0 || raw.refund_reason) return 1
  return 0
}

function normalizeGoods(goods = []) {
  if (!Array.isArray(goods)) return []

  return goods.map((item, index) => {
    const goodsType = toNumber(pickFirst(item.goods_type, item.goodsType), 0)
    const orderType = toNumber(pickFirst(item.order_type, item.orderType), 0)
    const goodsInfo = item.goods_info || {}

    return {
      ...item,
      rowKey: pickFirst(item.id, item.order_goods_id, item.goods_id, item.product_id, index),
      goodsId: pickFirst(item.goods_id, item.id, item.product_id, 0),
      goodsName: normalizeText(pickFirst(item.goods_name, item.store_name, goodsInfo.name, goodsInfo.goods_name, item.name), '-'),
      goodsType,
      goodsTypeText: normalizeText(pickFirst(item.goods_type_text, GOODS_TYPE_MAP[goodsType] && GOODS_TYPE_MAP[goodsType].label), '-'),
      goodsTypeTagType: GOODS_TYPE_MAP[goodsType] ? GOODS_TYPE_MAP[goodsType].type : 'info',
      orderType,
      orderTypeText: normalizeText(pickFirst(item.order_type_text, ORDER_TYPE_MAP[orderType] && ORDER_TYPE_MAP[orderType].label), '-'),
      orderTypeTagType: ORDER_TYPE_MAP[orderType] ? ORDER_TYPE_MAP[orderType].type : 'info',
      price: normalizeMoney(pickFirst(item.price, item.pay_price, item.goods_amount, item.total_price, 0)),
      quantity: toNumber(pickFirst(item.cart_num, item.num, item.quantity, 1), 1),
      merchantName: normalizeText(pickFirst(item.mer_name, item.merchant_name, item.store_name, goodsInfo.merchant_name), '-'),
      image: pickFirst(item.goods_image, item.image, item.pic, goodsInfo.goods_image, goodsInfo.image, ''),
      raw: item
    }
  })
}

export function extractResponseData(response) {
  if (response && response.data !== undefined) {
    return response.data
  }
  return response || {}
}

export function extractListPayload(response) {
  const data = extractResponseData(response)
  const list = Array.isArray(data.list)
    ? data.list
    : Array.isArray(data.data)
      ? data.data
      : Array.isArray(data)
        ? data
        : []

  return {
    raw: data,
    list,
    total: toNumber(pickFirst(data.total, data.count, list.length), list.length),
    stats: data.stats || {}
  }
}

export function normalizeOrder(raw = {}) {
  const data = extractResponseData(raw)
  const status = toNumber(pickFirst(data.status, data.order_status), 0)
  const goods = normalizeGoods(pickFirst(data.goods, data.order_goods, data.goods_preview, []))
  const orderType = toNumber(pickFirst(data.order_type, goods[0] && goods[0].orderType), 0)
  const refundStatus = inferRefundStatus(data, status)
  const payType = normalizeText(pickFirst(data.pay_type, data.payType), '')
  const payAmount = normalizeMoney(pickFirst(data.pay_amount, data.pay_price, data.total_amount, 0))
  const originalAmount = normalizeMoney(pickFirst(data.original_amount, data.total_amount, data.pay_amount, data.pay_price, 0))
  const refundAmount = normalizeMoney(pickFirst(data.refund_amount, 0))
  const firstCommissionAmount = normalizeMoney(pickFirst(data.first_commission_amount, 0))
  const secondCommissionAmount = normalizeMoney(pickFirst(data.second_commission_amount, 0))
  const commissionAmount = normalizeMoney(pickFirst(data.commission_amount, firstCommissionAmount + secondCommissionAmount, 0))
  const platformCommissionAmount = normalizeMoney(pickFirst(data.platform_commission_amount, 0))
  const referrer = data.referrer || data.first_referrer || {}
  const secondReferrer = data.second_referrer || {}
  const referrerId = pickFirst(data.referrer_id, referrer.uid, referrer.id, 0)
  const secondReferrerId = pickFirst(data.second_referrer_id, secondReferrer.uid, secondReferrer.id, 0)
  const user = data.user || {}
  const merchant = data.merchant || {}
  const orderId = pickFirst(data.order_id, data.orderId, data.id, 0)
  const refundRecordId = pickFirst(data.refund_record_id, data.refundRecordId, 0)
  const rowKey = normalizeText(pickFirst(data.row_key, refundRecordId ? `refund-${refundRecordId}` : '', orderId, data.order_sn), `${Math.random()}`)
  const nickname = normalizeText(pickFirst(data.nickname, data.user_nickname, user.nickname), '-')
  const userPhone = normalizeText(pickFirst(data.user_phone, data.phone, user.phone), '-')
  const userId = pickFirst(data.user_id, data.uid, user.id, user.uid, 0)
  const merchantName = normalizeText(pickFirst(data.merchant_name, data.mer_name, merchant.mer_name, goods[0] && goods[0].merchantName), '-')
  const orderSn = normalizeText(pickFirst(data.order_sn, data.order_id, data.trade_no), '-')
  const goodsSummary = normalizeText(pickFirst(data.goods_summary), goods.length
    ? goods.slice(0, 2).map(item => item.goodsName).join('、') + (goods.length > 2 ? ` 等${goods.length}件` : '')
    : normalizeText(pickFirst(data.goods_name, data.product_name), '-'))
  const hasPhysicalGoods = goods.some(item => item.goodsType === 1)

  return {
    ...data,
    id: orderId,
    orderId,
    refundRecordId,
    rowKey,
    orderSn,
    nickname,
    userPhone,
    userId,
    merchantName,
    goods,
    goodsSummary,
    status,
    statusText: normalizeText(pickFirst(data.status_text, ORDER_STATUS_MAP[String(status)] && ORDER_STATUS_MAP[String(status)].label), '-'),
    statusTagType: ORDER_STATUS_MAP[String(status)] ? ORDER_STATUS_MAP[String(status)].type : 'info',
    refundStatus,
    refundStatusText: normalizeText(pickFirst(data.refund_status_text, REFUND_STATUS_MAP[String(refundStatus)] && REFUND_STATUS_MAP[String(refundStatus)].label), '-'),
    refundTagType: REFUND_STATUS_MAP[String(refundStatus)] ? REFUND_STATUS_MAP[String(refundStatus)].type : 'info',
    payType,
    payTypeText: normalizeText(pickFirst(data.pay_type_text, PAY_TYPE_MAP[payType] && PAY_TYPE_MAP[payType].label), '-'),
    payTypeTagType: PAY_TYPE_MAP[payType] ? PAY_TYPE_MAP[payType].type : 'info',
    orderType,
    orderTypeText: normalizeText(pickFirst(data.order_type_text, ORDER_TYPE_MAP[String(orderType)] && ORDER_TYPE_MAP[String(orderType)].label), '-'),
    orderTypeTagType: ORDER_TYPE_MAP[String(orderType)] ? ORDER_TYPE_MAP[String(orderType)].type : 'info',
    payAmount,
    originalAmount,
    refundAmount,
    commissionAmount,
    firstCommissionAmount,
    secondCommissionAmount,
    platformCommissionAmount,
    referrerId,
    referrerText: normalizeText(pickFirst(data.referrer_name, referrer.label, referrer.nickname, referrer.phone), ''),
    secondReferrerId,
    secondReferrerText: normalizeText(pickFirst(data.second_referrer_name, secondReferrer.label, secondReferrer.nickname, secondReferrer.phone), ''),
    refundReason: normalizeText(pickFirst(data.refund_reason, data.fail_msg), ''),
    remark: normalizeText(pickFirst(data.remark, data.mark), ''),
    createTime: normalizeText(data.create_time, '-'),
    payTime: normalizeText(data.pay_time, '-'),
    deliveryTime: normalizeText(data.delivery_time, '-'),
    receiveTime: normalizeText(data.receive_time, '-'),
    finishTime: normalizeText(data.finish_time, '-'),
    refundApplyTime: normalizeText(pickFirst(data.refund_application_time, data.refund_reason_time, data.update_time), '-'),
    address: normalizeText(pickFirst(data.user_address, data.address, data.detail), '-'),
    expressName: normalizeText(pickFirst(data.express_name, data.delivery_name), '-'),
    expressNo: normalizeText(pickFirst(data.delivery_id, data.express_no, data.tracking_no), '-'),
    commissionStatusText: normalizeText(pickFirst(data.commission_status_text), '-'),
    canAuditRefund: toBoolean(data.can_audit_refund, refundStatus === 1 || status === 5),
    hasPhysicalGoods,
    canDelivery: orderType === 2 && hasPhysicalGoods && status === 1 && !data.delivery_time,
    canConfirmReceipt: orderType === 2 && hasPhysicalGoods && status === 1 && !!data.delivery_time && !data.receive_time
  }
}

export function normalizeOrderList(list = []) {
  if (!Array.isArray(list)) return []
  return list.map(item => normalizeOrder(item))
}

export function normalizeRecordList(response) {
  const data = extractResponseData(response)
  const list = Array.isArray(data.list)
    ? data.list
    : Array.isArray(data)
      ? data
      : Array.isArray(data.data)
        ? data.data
        : []

  return list.map((item, index) => ({
    ...item,
    rowKey: pickFirst(item.id, item.order_status_id, index),
    title: normalizeText(pickFirst(item.title, item.status_name, item.change_message, item.type_text, item.action), '订单记录'),
    content: normalizeText(pickFirst(item.remark, item.message, item.change_message, item.description, item.content), '-'),
    operator: normalizeText(pickFirst(item.admin_name, item.operator_name, item.nickname, item.username, item.user_name, item.operator), '-'),
    createTime: normalizeText(pickFirst(item.create_time, item.add_time, item.time), '-')
  }))
}

export function computeRefundStats(orderList = []) {
  return orderList.reduce((stats, item) => {
    stats.total += 1
    stats.totalRefundAmount += item.refundAmount || item.payAmount

    if (item.refundStatus === 1 || item.status === 5) {
      stats.pending += 1
    }

    if (item.refundStatus === 2 || item.status === 4) {
      stats.success += 1
    }

    return stats
  }, {
    total: 0,
    pending: 0,
    success: 0,
    totalRefundAmount: 0
  })
}
