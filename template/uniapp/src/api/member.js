import { get, post } from './request'
import { AUTH_MODES } from '@/utils/auth'

const OPTIONS = {
  authMode: AUTH_MODES.REQUIRED
}

/**
 * 获取会员信息（会员状态 + 权益 + 协议入口）。
 *
 * @param {Object} params 查询参数，通常留空。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 需要登录；data 内含 is_member、end_time、benefits、agreement 等字段。
 */
export function getMemberInfo(params = {}, options = {}) {
  return get('user/member/info', params, {
    ...OPTIONS,
    ...options
  })
}

/**
 * 获取会员套餐列表。
 *
 * @param {Object} params 查询参数，通常留空。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 需要登录；套餐金额来自后台组合数据 member_package。
 */
export function getMemberPackages(params = {}, options = {}) {
  return get('user/member/packages', params, {
    ...OPTIONS,
    ...options
  })
}

/**
 * 创建会员订单并发起支付。
 *
 * @param {Object} data 请求参数。
 * @param {number} data.package_id 套餐 ID。
 * @param {string} [data.pay_type] 支付方式，默认 wechat。
 * @param {string} [data.trade_type] 微信交易类型。
 * @param {string} [data.return_url] H5 支付回跳地址。
 * @param {string} [data.terminal] 当前终端。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 需要登录。
 */
export function createMemberOrder(data = {}, options = {}) {
  return post('user/member/create', data, {
    ...OPTIONS,
    ...options
  })
}
