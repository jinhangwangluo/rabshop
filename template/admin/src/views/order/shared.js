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
  2: { label: '已退款', type: 'success' },
  3: { label: '部分退款', type: 'success' },
  4: { label: '退款失败', type: 'danger' },
  5: { label: '已取消', type: 'info' }
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
  1: { label: '内容订单', type: 'primary' },
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

function hasValue(value) {
  return value !== undefined && value !== null && value !== ''
}

function resolveOrderStatusMeta(data, status, hasPhysicalGoods, actions = {}) {
  const base = ORDER_STATUS_MAP[String(status)] || { label: '-', type: 'info' }
  const deliveryTime = pickFirst(data.delivery_time, data.deliveryTime)
  const receiveTime = pickFirst(data.receive_time, data.receiveTime)

  if (status === 1 && hasPhysicalGoods) {
    return hasValue(deliveryTime)
      ? { label: hasValue(receiveTime) ? '待评价' : '待收货', type: 'warning' }
      : { label: '待发货', type: 'primary' }
  }

  if (status === 6) {
    return { label: '待评价', type: 'warning' }
  }

  if (status === 2 && actions.can_review) {
    return { label: '待评价', type: 'warning' }
  }

  return {
    label: normalizeText(pickFirst(data.status_text, base.label), '-'),
    type: base.type
  }
}

function normalizeGoods(goods = []) {
  if (!Array.isArray(goods)) return []

  return goods.map((item, index) => {
    const goodsType = toNumber(pickFirst(item.goods_type, item.goodsType), 0)
    const orderType = toNumber(pickFirst(item.order_type, item.orderType), 0)
    const goodsInfo = item.goods_info || {}
    const merchantInfo = item.merchant || {}

    return {
      ...item,
      rowKey: pickFirst(item.id, item.goods_id, item.product_id, index),
      goodsId: pickFirst(item.goods_id, item.id, item.product_id, 0),
      merchantId: pickFirst(item.merchant_id, merchantInfo.id, goodsInfo.merchant_id, 0),
      goodsName: normalizeText(pickFirst(item.goods_name, item.store_name, goodsInfo.name, item.name), '-'),
      goodsType,
      goodsTypeText: normalizeText(pickFirst(item.goods_type_text, GOODS_TYPE_MAP[goodsType] && GOODS_TYPE_MAP[goodsType].label), '-'),
      goodsTypeTagType: GOODS_TYPE_MAP[goodsType] ? GOODS_TYPE_MAP[goodsType].type : 'info',
      orderType,
      orderTypeText: normalizeText(pickFirst(item.order_type_text, ORDER_TYPE_MAP[orderType] && ORDER_TYPE_MAP[orderType].label), '-'),
      orderTypeTagType: ORDER_TYPE_MAP[orderType] ? ORDER_TYPE_MAP[orderType].type : 'info',
      price: normalizeMoney(pickFirst(item.price, item.pay_price, item.goods_amount, item.total_price, 0)),
      quantity: toNumber(pickFirst(item.cart_num, item.num, item.quantity, 1), 1),
      merchantName: normalizeText(pickFirst(item.mer_name, item.merchant_name, merchantInfo.mer_name, merchantInfo.name, item.store_name), '-'),
      skuName: normalizeText(pickFirst(item.sku_name, item.skuName, goodsInfo.sku_name), ''),
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
  const goods = normalizeGoods(pickFirst(data.goods, data.order_goods, []))
  const orderType = toNumber(pickFirst(data.order_type, goods[0] && goods[0].orderType), 0)
  const refundStatus = inferRefundStatus(data, status)
  const payType = normalizeText(pickFirst(data.pay_type, data.payType), '')
  const payAmount = normalizeMoney(pickFirst(data.pay_amount, data.pay_price, data.total_amount, 0))
  const originalAmount = normalizeMoney(pickFirst(data.original_amount, data.total_amount, data.pay_amount, data.pay_price, 0))
  const refundAmount = normalizeMoney(pickFirst(data.refund_amount, 0))
  const user = data.user || {}
  const merchant = data.merchant || {}
  const rowKey = normalizeText(pickFirst(data.row_key, data.refund_record_id, data.id, data.order_id, data.order_sn), `${Math.random()}`)
  const nickname = normalizeText(pickFirst(data.nickname, data.user_nickname, user.nickname), '-')
  const userPhone = normalizeText(pickFirst(data.user_phone, data.phone, user.phone), '-')
  const userId = pickFirst(data.user_id, data.uid, user.id, user.uid, 0)
  const merchantId = pickFirst(data.merchant_id, merchant.id, goods[0] && goods[0].merchantId, 0)
  const merchantName = normalizeText(pickFirst(data.merchant_name, data.mer_name, merchant.mer_name, merchant.name, goods[0] && goods[0].merchantName), '-')
  const merchantPhone = normalizeText(pickFirst(data.merchant_phone, data.mer_phone, merchant.phone, merchant.contact_phone, merchant.mer_phone), '')
  const merchantContactName = normalizeText(pickFirst(data.merchant_contact_name, merchant.contact_name), '')
  const merchantAvatar = pickFirst(data.merchant_avatar, data.mer_avatar, merchant.avatar, merchant.mer_avatar, merchant.logo, '')
  const orderId = pickFirst(data.order_id, data.orderId, data.id, 0)
  const refundRecordId = pickFirst(data.refund_record_id, data.refundRecordId, 0)
  const refundSn = normalizeText(pickFirst(data.refund_sn, data.refundSn, data.refund_order_sn, data.refundOrderSn), '')
  const orderSn = normalizeText(pickFirst(data.order_sn, data.orderSn, data.original_order_sn, data.source_order_sn, data.order_id, data.trade_no), '-')
  const originalOrderSn = normalizeText(pickFirst(data.original_order_sn, data.originalOrderSn, data.source_order_sn, data.sourceOrderSn, orderSn), '-')
  const goodsSummary = goods.length
    ? goods.slice(0, 2).map(item => item.goodsName).join('、') + (goods.length > 2 ? ` 等${goods.length}件` : '')
    : normalizeText(pickFirst(data.goods_summary, data.goods_name, data.product_name), '-')
  const firstGoods = goods[0] || {}
  const hasPhysicalGoods = goods.some(item => item.goodsType === 1)
  const actions = data.actions && typeof data.actions === 'object' ? data.actions : {}
  const statusMeta = resolveOrderStatusMeta(data, status, hasPhysicalGoods, actions)
  const rawDeliveryTime = pickFirst(data.delivery_time, data.deliveryTime)
  const rawReceiveTime = pickFirst(data.receive_time, data.receiveTime)

  return {
    ...data,
    id: orderId,
    orderId,
    refundRecordId,
    rowKey,
    refundSn,
    orderSn,
    originalOrderSn,
    nickname,
    userPhone,
    userId,
    merchantId,
    merchantName,
    merchantPhone,
    merchantContactName,
    merchantAvatar,
    merchantInfo: {
      ...merchant,
      id: merchantId,
      name: merchantName,
      mer_name: merchantName,
      phone: merchantPhone,
      contact_name: merchantContactName,
      avatar: merchantAvatar
    },
    goods,
    goodsSummary,
    goodsImage: pickFirst(firstGoods.image, data.goods_image, ''),
    goodsSpec: normalizeText(pickFirst(firstGoods.skuName, data.sku_name), ''),
    goodsPrice: normalizeMoney(pickFirst(firstGoods.price, data.goods_price, data.price, 0)),
    goodsQuantity: toNumber(pickFirst(firstGoods.quantity, data.goods_num, data.num, 1), 1),
    status,
    statusText: statusMeta.label,
    statusTagType: statusMeta.type,
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
    canAuditRefund: toBoolean(pickFirst(data.can_audit_refund, actions.can_audit_refund), refundStatus === 1 || status === 5),
    canApplyRefund: toBoolean(pickFirst(data.can_refund, actions.can_refund), refundStatus === 0 && (status === 1 || status === 6)),
    hasPhysicalGoods,
    canDelivery: hasPhysicalGoods && toBoolean(pickFirst(data.can_delivery, actions.can_delivery), orderType === 2 && status === 1 && !hasValue(rawDeliveryTime)),
    canEditDelivery: hasPhysicalGoods && toBoolean(pickFirst(data.can_edit_delivery, actions.can_edit_delivery), orderType === 2 && status === 1 && hasValue(rawDeliveryTime)),
    canConfirmReceipt: hasPhysicalGoods && toBoolean(pickFirst(data.can_receipt, actions.can_receipt), orderType === 2 && status === 1 && hasValue(rawDeliveryTime) && !hasValue(rawReceiveTime))
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
    content: normalizeText(pickFirst(item.remark, item.message, item.change_message, item.description), '-'),
    operator: normalizeText(pickFirst(item.admin_name, item.operator_name, item.nickname, item.username, item.user_name), '-'),
    createTime: normalizeText(pickFirst(item.create_time, item.add_time, item.time), '-')
  }))
}

export function normalizeRefundReasons(response) {
  const data = extractResponseData(response)
  const list = Array.isArray(data.list)
    ? data.list
    : Array.isArray(data)
      ? data
      : data && typeof data === 'object'
        ? Object.values(data)
        : []

  return list.map((item, index) => {
    if (typeof item === 'string') {
      return {
        rowKey: `${index}-${item}`,
        label: item,
        value: item
      }
    }

    return {
      rowKey: pickFirst(item.id, index),
      label: normalizeText(pickFirst(item.label, item.title, item.reason, item.value), '-'),
      value: normalizeText(pickFirst(item.value, item.reason, item.label, item.title), '-')
    }
  })
}

function isToday(dateTime) {
  const value = normalizeText(dateTime, '')
  if (!value) return false
  const today = new Date()
  const year = today.getFullYear()
  const month = String(today.getMonth() + 1).padStart(2, '0')
  const day = String(today.getDate()).padStart(2, '0')
  return value.indexOf(`${year}-${month}-${day}`) === 0
}

export function computeOrderStats(orderList = []) {
  return orderList.reduce((stats, item) => {
    stats.total += 1
    stats.paidAmount += item.payAmount

    if (item.status === 1 || item.status === 2 || item.status === 6) {
      stats.paidCount += 1
    }

    if (item.status === 5 || item.refundStatus === 1) {
      stats.refundingCount += 1
    }

    if (item.status === 4 || item.refundStatus === 2 || item.refundStatus === 3) {
      stats.refundedAmount += item.refundAmount || item.payAmount
    }

    if (isToday(item.createTime)) {
      stats.todayCount += 1
    }

    return stats
  }, {
    total: 0,
    paidCount: 0,
    refundingCount: 0,
    refundedAmount: 0,
    todayCount: 0,
    paidAmount: 0
  })
}

export function computeRefundStats(orderList = []) {
  return orderList.reduce((stats, item) => {
    stats.total += 1
    stats.totalRefundAmount += item.refundAmount || item.payAmount

    if (item.refundStatus === 1 || item.status === 5) {
      stats.pending += 1
    }

    if (item.refundStatus === 2 || item.refundStatus === 3 || item.status === 4) {
      stats.success += 1
    }

    if (isToday(item.refundApplyTime)) {
      stats.today += 1
    }

    return stats
  }, {
    total: 0,
    pending: 0,
    success: 0,
    today: 0,
    totalRefundAmount: 0
  })
}

export function buildOrderActionPayload(order, extra = {}) {
  const item = normalizeOrder(order)
  const orderId = pickFirst(item.orderId, item.order_id, item.id, 0)

  return {
    id: orderId,
    order_id: orderId,
    refund_record_id: pickFirst(item.refundRecordId, item.refund_record_id, 0),
    order_sn: item.orderSn,
    refund_amount: item.refundAmount || item.payAmount,
    pay_amount: item.payAmount,
    price: item.refundAmount || item.payAmount,
    ...extra
  }
}
