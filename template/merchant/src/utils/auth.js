import Cookies from 'js-cookie'
import defaultSettings from '@/settings.js'

const TokenKey = defaultSettings.tokenPrefix
const TokenExpiryKey = defaultSettings.tokenPrefix + '-expiry'

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
 * 保存 token 过期时间戳（毫秒）。
 * @param {number} expiryMs 过期时间戳。
 */
export function setTokenExpiry(expiryMs) {
  localStorage.setItem(TokenExpiryKey, String(expiryMs))
}

/**
 * 读取 token 过期时间戳（毫秒）。
 * @returns {number} 未设置时返回 0。
 */
export function getTokenExpiry() {
  return parseInt(localStorage.getItem(TokenExpiryKey) || '0', 10)
}

/**
 * 清除 token 过期时间戳。
 */
export function removeTokenExpiry() {
  localStorage.removeItem(TokenExpiryKey)
}
