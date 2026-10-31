import request from '@/utils/request'

const PREFIX = '/api/merchant/order'

/**
 * 获取商户订单列表
 * @param {Object} params - { order_sn, status, start_time, end_time, page, limit }
 */
export function getMerchantOrderList(params) {
  return request({
    url: `${PREFIX}/list`,
    method: 'get',
    params
  })
}

/**
 * 获取商户订单详情
 * @param {number|string} id
 */
export function getMerchantOrderDetail(id) {
  return request({
    url: `${PREFIX}/detail/${id}`,
    method: 'get'
  })
}

/**
 * 获取商户订单统计
 */
export function getMerchantOrderStatistics() {
  return request({
    url: `${PREFIX}/statistics`,
    method: 'get'
  })
}

/**
 * 订单发货
 * @param {number|string} id
 * @param {Object} data - { express_name, express_sn, express_code }
 */
export function merchantDelivery(id, data) {
  return request({
    url: `${PREFIX}/delivery/${id}`,
    method: 'put',
    data
  })
}

/**
 * 获取商户发货可选快递公司
 * @param {Object} params - 查询参数：keyword
 */
export function getMerchantExpressCompanyOptions(params = {}) {
  return request({
    url: `${PREFIX}/express_options`,
    method: 'get',
    params
  })
}

/**
 * 申请退款
 * @param {Object} data - { order_id, refund_reason, refund_amount }
 */
export function merchantRefundApply(data) {
  return request({
    url: `${PREFIX}/refund_apply`,
    method: 'post',
    data
  })
}
