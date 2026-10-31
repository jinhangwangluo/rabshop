import request from '@/utils/request'

const PREFIX = '/order'

export function getOrderList(params) {
  return request({
    url: `${PREFIX}/list`,
    method: 'get',
    params
  })
}

export function getRefundList(params) {
  return request({
    url: `${PREFIX}/refund_list`,
    method: 'get',
    params
  })
}

export function getOrderDetail(id) {
  return request({
    url: `${PREFIX}/detail/${id}`,
    method: 'get'
  })
}

export function getOrderRecord(id) {
  return request({
    url: `${PREFIX}/record/${id}`,
    method: 'get'
  })
}

export function getOrderStatistics(params) {
  return request({
    url: `${PREFIX}/statistics`,
    method: 'get',
    params
  })
}

export function getRefundStatistics(params) {
  return request({
    url: `${PREFIX}/refund_statistics`,
    method: 'get',
    params
  })
}

export function deliveryOrder(id, data) {
  return request({
    url: `${PREFIX}/delivery/${id}`,
    method: 'put',
    data
  })
}

export function applyOrderRefund(data) {
  return request({
    url: `${PREFIX}/refund_apply`,
    method: 'post',
    data
  })
}

/**
 * Approve a pending refund request for the current merchant order.
 * Requires merchant authentication.
 * @param {number|string} id - Order ID.
 * @returns {Promise<Object>} Backend response.
 */
export function approveOrderRefund(id) {
  return request({
    url: `${PREFIX}/refund_approve/${id}`,
    method: 'post'
  })
}

/**
 * Reject a pending refund request for the current merchant order.
 * Requires merchant authentication.
 * @param {number|string} id - Order ID.
 * @param {Object} data - Reject reason payload.
 * @returns {Promise<Object>} Backend response.
 */
export function rejectOrderRefund(id, data) {
  return request({
    url: `${PREFIX}/refund_reject/${id}`,
    method: 'post',
    data
  })
}
