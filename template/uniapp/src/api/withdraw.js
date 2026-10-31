import { get, post } from './request'
import { AUTH_MODES } from '@/utils/auth'

const OPTIONS = {
  authMode: AUTH_MODES.REQUIRED
}

/**
 * 获取提现页面配置数据。
 *
 * 返回 `{ config, summary, quickAmounts }`，summary 中的可提现金额固定为佣金。
 *
 * @param {Object} params  查询参数，兼容传入 `source`，后端固定按佣金提现处理。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>}
 */
export function getWithdrawConfig(params = {}, options = {}) {
  return get('user/withdraw/index', params, {
    ...OPTIONS,
    ...options
  })
}

/**
 * 提交提现申请。
 *
 * @param {Object} params  包含 `amount`；`source` 兼容传入但后端固定按佣金处理。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>}
 */
export function submitWithdraw(params = {}, options = {}) {
  return post('user/withdraw/submit', params, {
    ...OPTIONS,
    ...options
  })
}
