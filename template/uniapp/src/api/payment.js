import { get } from './request'
import { AUTH_MODES } from '@/utils/auth'

/**
 * 获取当前终端可用支付方式配置。
 *
 * 使用场景：订单支付、余额充值、直播金币充值等支付弹窗打开前加载支付方式。
 *
 * @param {Object} params 查询参数。
 * @param {string} [params.terminal] 当前终端，通常无需传参；默认由 `X-Terminal-Type` 请求头传递。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时返回 `{ methods, balance }`。
 */
export function getPaymentMethods(params = {}, options = {}) {
  return get('payment/methods', params, {
    authMode: AUTH_MODES.REQUIRED,
    ...options
  })
}
