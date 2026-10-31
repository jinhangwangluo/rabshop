const AGREEMENT_TITLE_MAP = {
  user: '用户协议',
  privacy: '隐私政策',
  member: '会员服务协议',
  service: '商家服务协议'
}

/**
 * 规范化协议类型。
 *
 * @param {string} type 协议类型。
 * @returns {string} 可用于协议详情接口的类型。
 */
export function normalizeAgreementType(type = '') {
  const value = String(type || '').trim()
  return value || 'member'
}

/**
 * 获取协议默认标题。
 *
 * @param {string} type 协议类型。
 * @returns {string} 协议标题。
 */
export function getAgreementTitle(type = '') {
  const agreementType = normalizeAgreementType(type)
  return AGREEMENT_TITLE_MAP[agreementType] || '协议详情'
}

/**
 * 构建主包协议页跳转地址。
 *
 * @param {string} type 协议类型。
 * @param {string} title 协议标题，可为空。
 * @returns {string} UniApp 页面地址。
 */
export function buildAgreementUrl(type = 'member', title = '') {
  const agreementType = normalizeAgreementType(type)
  const agreementTitle = String(title || getAgreementTitle(agreementType)).trim()
  const query = [`type=${encodeURIComponent(agreementType)}`]
  if (agreementTitle) {
    query.push(`title=${encodeURIComponent(agreementTitle)}`)
  }

  return `/pages/agreement/index?${query.join('&')}`
}
