import { get, post } from '@/api/request'
import { AUTH_MODES } from '@/utils/auth'

/**
 * 获取领券中心可领取优惠券列表。
 *
 * @param {Object} params 查询参数。
 * @param {number} params.page 页码。
 * @param {number} params.limit 每页数量。
 * @param {number|string} [params.scope_type] 优惠券适用范围，商品券暂不传入。
 * @param {Object} options 附加请求选项。
 * @returns {Promise<Object>} 成功时返回统一响应对象，data 内含 list、total、page、limit、totalPage。
 */
export function getCouponReceiveList(params = {}, options = {}) {
  return get('/api/mobile/coupon/list', params, {
    authMode: AUTH_MODES.REQUIRED,
    ...options
  })
}

/**
 * 获取我的优惠券列表。
 *
 * @param {Object} params 查询参数。
 * @param {number} params.page 页码。
 * @param {number} params.limit 每页数量。
 * @param {number|string} [params.status] 优惠券状态：0 未使用，1 已使用，2 已过期；空值表示全部。
 * @param {Object} options 附加请求选项。
 * @returns {Promise<Object>} 成功时返回统一响应对象，data 内含 list、total、page、limit、totalPage；需要登录，用于“我的优惠券”页面。
 */
export function getUserCouponList(params = {}, options = {}) {
  return get('/api/mobile/coupon/user_list', params, {
    authMode: AUTH_MODES.REQUIRED,
    ...options
  })
}

/**
 * 领取指定优惠券。
 *
 * @param {Object} data 请求数据。
 * @param {number} data.coupon_id 优惠券 ID。
 * @param {Object} options 附加请求选项。
 * @returns {Promise<Object>} 成功时返回统一响应对象，data 为用户优惠券领取记录。
 */
export function receiveCoupon(data = {}, options = {}) {
  return post('/api/mobile/coupon/receive', data, {
    authMode: AUTH_MODES.REQUIRED,
    ...options
  })
}
