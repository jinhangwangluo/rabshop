import Cookies from 'js-cookie'

const TokenKey = 'merrabbit-Admin-Token'
const TokenExpiryKey = 'merrabbit-Admin-Token-Expiry'

export function getToken() {
  return Cookies.get(TokenKey)
}

export function setToken(token, expires) {
  return Cookies.set(TokenKey, token, { expires })
}

export function removeToken() {
  return Cookies.remove(TokenKey)
}

/**
 * 保存 token 过期时间戳（毫秒）
 * @param {number} expiryMs 过期时间戳，单位毫秒
 */
export function setTokenExpiry(expiryMs) {
  localStorage.setItem(TokenExpiryKey, String(expiryMs))
}

/**
 * 读取 token 过期时间戳（毫秒），未设置返回 0
 * @return {number}
 */
export function getTokenExpiry() {
  return parseInt(localStorage.getItem(TokenExpiryKey) || '0', 10)
}

/**
 * 删除 token 过期时间戳
 */
export function removeTokenExpiry() {
  localStorage.removeItem(TokenExpiryKey)
}
