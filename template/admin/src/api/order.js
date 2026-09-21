import request from '@/utils/request'

const PREFIX = '/order'

export function getOrderList(params) {
  return request({
    url: PREFIX,
    method: 'get',
    params
  })
}

/**
 * 获取订单退款记录列表
 * @param {Object} params - 筛选和分页参数
 * @returns {Promise<Object>} 后端响应，data 中包含 list/total/stats
 */
export function getOrderRefundList(params) {
  return request({
    url: `${PREFIX}/refund`,
    method: 'get',
    params
  })
}

export function getOrderBadge(params) {
  return request({
    url: `${PREFIX}/getBadge`,
    method: 'get',
    params
  })
}

export function getOrderStats(params) {
  return getOrderBadge(params).catch(() => Promise.resolve({ data: {}}))
}

export function approveOrderRefund(params) {
  const id = params.id || params.order_id || ''
  return request({
    url: `${PREFIX}/${id}/approve_refund`,
    method: 'post'
  })
}

export function rejectOrderRefund(params) {
  const id = params.id || params.order_id || ''
  const reason = params.reason || params.refund_reason || params.fail_msg || params.remark || ''
  return request({
    url: `${PREFIX}/${id}/reject_refund`,
    method: 'post',
    data: { reason }
  })
}

/**
 * 发起订单退款申请
 * @param {Object} params - { id|order_id, refund_amount, refund_reason }
 * @returns {Promise<Object>} 后端响应
 */
export function applyOrderRefund(params) {
  const id = params.id || params.order_id || ''
  return request({
    url: `${PREFIX}/${id}/apply_refund`,
    method: 'post',
    data: {
      refund_amount: params.refund_amount,
      refund_reason: params.refund_reason || params.reason || ''
    }
  })
}

/**
 * 后台订单发货
 * @param {Object} params - { id|order_id, express_name, express_sn, express_code }
 * @returns {Promise<Object>} 后端响应
 */
export function deliverOrder(params) {
  const id = params.id || params.order_id || ''
  return request({
    url: `${PREFIX}/${id}/delivery`,
    method: 'post',
    data: {
      express_name: params.express_name || params.delivery_name || '',
      express_sn: params.express_sn || params.delivery_id || '',
      express_code: params.express_code || params.delivery_code || ''
    }
  })
}

/**
 * 更新订单备注
 * @param {Object} params - { id|order_id, remark }
 * @returns {Promise<Object>} 后端响应
 */
export function updateOrderRemark(params) {
  const id = params.id || params.order_id || ''
  return request({
    url: `${PREFIX}/${id}/remark`,
    method: 'post',
    data: {
      remark: params.remark || ''
    }
  })
}

export function deleteOrder(params) {
  const id = params.id || params.order_id || ''
  return request({
    url: `${PREFIX}/${id}`,
    method: 'delete'
  })
}

export function getOrderDetail(params) {
  const id = params.id || params.order_id || ''
  return request({
    url: `${PREFIX}/${id}`,
    method: 'get'
  })
}

/**
 * 查询订单物流轨迹
 * @param {Object} params - { id|order_id }
 * @returns {Promise<Object>} 后端响应，data 中包含快递公司、单号和轨迹列表
 */
export function getOrderExpressTrace(params) {
  const id = params.id || params.order_id || ''
  return request({
    url: `${PREFIX}/${id}/express`,
    method: 'get'
  })
}

// 旧接口已废弃，后端无对应路由，返回空数据保持组件兼容
export function getOrderRecord() {
  return Promise.resolve({ data: [] })
}

export function getRefundReason() {
  return Promise.resolve({ data: [] })
}
