import { get } from './request'
import { AUTH_MODES } from '@/utils/auth'

/**
 * 获取协议详情。
 *
 * @param {string} type 协议类型：user/privacy/member/service。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 data 返回 id、type、title、content、version；无需登录。
 */
export function getAgreementDetail(type, options = {}) {
  return get('agreement/detail', { type }, {
    authMode: AUTH_MODES.NONE,
    ...options
  })
}
