import { getPlatformScene, isH5 } from '@/utils/platform'
import { clearStoredSpreadUid } from '@/utils/spread'

/**
 * 页面或接口的登录要求模式。
 *
 * NONE：不需要登录；OPTIONAL：可携带登录态；REQUIRED：必须登录。
 */
export const AUTH_MODES = Object.freeze({
  NONE: 'none',
  OPTIONAL: 'optional',
  REQUIRED: 'required'
})

/**
 * 统一登录页路径，登录跳转、页面识别和登录后回跳均使用该常量。
 */
export const LOGIN_PAGE_PATH = '/pages/users/login/index'

/**
 * 登录后没有可用回跳页时的默认首页。
 */
export const DEFAULT_AFTER_LOGIN_PATH = '/pages/index/index'

const STORAGE_KEYS = {
  token: 'token',
  tokenExpiry: 'mobile_token_expiry',
  userInfo: 'mobile_userinfo',
  redirect: 'mobile_auth_redirect'
}

const TAB_BAR_PATHS = [
  '/pages/index/index',
  '/pages/category/index',
  '/pages/cart/index',
  '/pages/user/index'
]

let loginJumpLocked = false
let loginJumpTimer = null

/**
 * 规范化页面路径，保证路由比较和页面跳转使用统一格式。
 *
 * @param {string} path 原始路径、相对路径或 H5 同源完整 URL。
 * @returns {string} 以 `/` 开头的页面路径；空值或外部 URL 返回空字符串。
 */
function normalizePath(path = '') {
  const source = String(path || '').trim()
  if (!source) {
    return ''
  }

  if (/^https?:\/\//i.test(source)) {
    // #ifdef H5
    if (typeof window !== 'undefined' && window.location && source.startsWith(window.location.origin)) {
      return source.replace(window.location.origin, '') || '/'
    }
    // #endif
    return ''
  }

  return source.startsWith('/') ? source : `/${source}`
}

/**
 * 将对象参数转换为 URL 查询字符串。
 *
 * @param {Object} query 查询参数对象。
 * @returns {string} 以 `?` 开头的查询字符串；没有有效参数时返回空字符串。
 */
function buildQueryString(query = {}) {
  const queryString = Object.keys(query)
    .filter((key) => query[key] !== undefined && query[key] !== null && query[key] !== '')
    .map((key) => `${encodeURIComponent(key)}=${encodeURIComponent(String(query[key]))}`)
    .join('&')

  return queryString ? `?${queryString}` : ''
}

/**
 * 解除登录页跳转锁，允许后续再次触发登录跳转。
 *
 * @returns {void}
 */
function resetLoginJumpLock() {
  loginJumpLocked = false
  if (loginJumpTimer) {
    clearTimeout(loginJumpTimer)
    loginJumpTimer = null
  }
}

/**
 * 设置短时间登录跳转锁，避免接口并发 401 时重复打开登录页。
 *
 * @returns {void}
 */
function setLoginJumpLock() {
  loginJumpLocked = true
  if (loginJumpTimer) {
    clearTimeout(loginJumpTimer)
  }
  loginJumpTimer = setTimeout(() => {
    resetLoginJumpLock()
  }, 800)
}

/**
 * 将当前页面 options 转成查询字符串，供页面栈路径还原使用。
 *
 * @param {Object} options 当前页面参数对象。
 * @returns {string} 以 `?` 开头的查询字符串；没有参数时返回空字符串。
 */
function parseCurrentPageOptions(options = {}) {
  const queryString = Object.keys(options)
    .filter((key) => options[key] !== undefined)
    .map((key) => `${encodeURIComponent(key)}=${encodeURIComponent(String(options[key]))}`)
    .join('&')

  return queryString ? `?${queryString}` : ''
}

/**
 * 从页面栈对象中生成完整页面地址。
 *
 * @param {Object|null} page UniApp 页面栈对象。
 * @returns {string} 页面路径，包含查询参数。
 */
function getPageUrl(page = null) {
  if (!page) {
    return ''
  }

  const route = normalizePath(page.route || '')
  if (!route) {
    return ''
  }

  return `${route}${parseCurrentPageOptions(page.options || {})}`
}

/**
 * 读取本地登录 token。
 *
 * @returns {string} 已去掉 `Bearer` 前缀的 token；未登录时返回空字符串。
 */
export function getToken() {
  return String(uni.getStorageSync(STORAGE_KEYS.token) || '')
    .replace(/^Bearer\s+/i, '')
    .trim()
}

/**
 * 保存登录 token，并统一去除可能存在的 `Bearer` 前缀。
 *
 * @param {string} token 登录接口或 OAuth 回调返回的 token。
 * @returns {string} 实际写入本地缓存的 token；空 token 会清空登录 token 并返回空字符串。
 */
export function setToken(token = '') {
  const normalized = String(token || '').replace(/^Bearer\s+/i, '').trim()
  if (!normalized) {
    clearToken()
    return ''
  }

  uni.setStorageSync(STORAGE_KEYS.token, normalized)
  return normalized
}

/**
 * 清除本地登录 token。
 *
 * @returns {void}
 */
export function clearToken() {
  uni.removeStorageSync(STORAGE_KEYS.token)
}

/**
 * 读取 token 过期时间。
 *
 * @returns {number} 毫秒时间戳；未设置时返回 0。
 */
export function getTokenExpiry() {
  return Number(uni.getStorageSync(STORAGE_KEYS.tokenExpiry) || 0)
}

/**
 * 保存 token 过期时间。
 *
 * @param {number} expiryMs 毫秒时间戳。
 * @returns {number} 实际写入的毫秒时间戳；无效值会清空过期时间并返回 0。
 */
export function setTokenExpiry(expiryMs = 0) {
  const normalized = Number(expiryMs || 0)
  if (!normalized || Number.isNaN(normalized)) {
    clearTokenExpiry()
    return 0
  }

  uni.setStorageSync(STORAGE_KEYS.tokenExpiry, normalized)
  return normalized
}

/**
 * 清除 token 过期时间。
 *
 * @returns {void}
 */
export function clearTokenExpiry() {
  uni.removeStorageSync(STORAGE_KEYS.tokenExpiry)
}

/**
 * 读取本地用户信息。
 *
 * @returns {Object} 用户信息对象；未设置或缓存异常时返回空对象。
 */
export function getUserInfo() {
  const userInfo = uni.getStorageSync(STORAGE_KEYS.userInfo)
  return userInfo && typeof userInfo === 'object' ? userInfo : {}
}

/**
 * 保存本地用户信息。
 *
 * @param {Object} userInfo 登录接口返回的用户信息。
 * @returns {Object} 实际写入本地缓存的用户信息对象。
 */
export function setUserInfo(userInfo = {}) {
  const normalized = userInfo && typeof userInfo === 'object' ? userInfo : {}
  uni.setStorageSync(STORAGE_KEYS.userInfo, normalized)
  return normalized
}

/**
 * 清除本地用户信息。
 *
 * @returns {void}
 */
export function clearUserInfo() {
  uni.removeStorageSync(STORAGE_KEYS.userInfo)
}

/**
 * 清除所有认证相关本地缓存。
 *
 * @returns {void}
 */
export function clearAuthStorage() {
  clearToken()
  clearTokenExpiry()
  clearUserInfo()
}

/**
 * 判断当前是否存在登录 token。
 *
 * @returns {boolean} 存在 token 时返回 true。
 */
export function isLoggedIn() {
  return getToken() !== ''
}

/**
 * 保存登录接口返回的认证结果。
 *
 * @param {Object} payload 登录、授权登录或 OAuth 回调返回的数据。
 * @param {string} [payload.token] token 字段，兼容 `access_token`、`accessToken`。
 * @param {Object} [payload.user] 用户信息字段，兼容 `userinfo`、`userInfo`。
 * @param {number} [payload.expiresIn] token 有效秒数，兼容 `expires_in`、`expires`。
 * @returns {{token: string, userInfo: Object}} 当前缓存中的 token 和用户信息。
 */
export function saveAuthResult(payload = {}) {
  const token = payload.token || payload.access_token || payload.accessToken || ''
  const userInfo = payload.user || payload.userinfo || payload.userInfo || {}
  const expiresIn = Number(payload.expiresIn || payload.expires_in || payload.expires || 0)

  if (token) {
    setToken(token)
    if (expiresIn > 0) {
      setTokenExpiry(Date.now() + expiresIn * 1000)
    }
  }

  if (userInfo && typeof userInfo === 'object' && Object.keys(userInfo).length > 0) {
    setUserInfo(userInfo)
  }

  return {
    token: getToken(),
    userInfo: getUserInfo()
  }
}

/**
 * 获取当前页面完整地址。
 *
 * @returns {string} 当前页面路径，包含查询参数；页面栈为空时返回空字符串。
 */
export function getCurrentPageUrl() {
  const pageStack = getCurrentPages()
  if (!pageStack || pageStack.length === 0) {
    return ''
  }

  return getPageUrl(pageStack[pageStack.length - 1])
}

/**
 * 判断给定路径是否为登录页。
 *
 * @param {string} path 待判断的页面路径，可包含查询参数。
 * @returns {boolean} 是登录页时返回 true。
 */
export function isLoginPagePath(path = '') {
  return normalizePath(String(path || '').split('?')[0]) === LOGIN_PAGE_PATH
}

/**
 * 判断给定路径是否为底部 TabBar 页面。
 *
 * @param {string} path 待判断的页面路径，可包含查询参数。
 * @returns {boolean} 是 TabBar 页面时返回 true。
 */
export function isTabBarPath(path = '') {
  return TAB_BAR_PATHS.includes(normalizePath(String(path || '').split('?')[0]))
}

/**
 * 保存登录后的回跳地址。
 *
 * @param {string} path 指定回跳地址；为空时使用当前页面地址。
 * @returns {string} 实际保存的回跳地址；登录页或无效路径返回空字符串。
 */
export function savePostLoginRedirect(path = '') {
  const redirectPath = normalizePath(path || getCurrentPageUrl())
  if (!redirectPath || isLoginPagePath(redirectPath)) {
    return ''
  }

  uni.setStorageSync(STORAGE_KEYS.redirect, redirectPath)
  return redirectPath
}

/**
 * 清除登录后回跳地址。
 *
 * @returns {void}
 */
function clearPostLoginRedirect() {
  uni.removeStorageSync(STORAGE_KEYS.redirect)
}

/**
 * 生成登录页地址，并携带当前运行场景和回跳路径。
 *
 * @param {string} redirect 登录成功后的目标回跳地址；为空时使用当前页面。
 * @returns {string} 登录页完整地址。
 */
export function buildLoginPageUrl(redirect = '') {
  const currentScene = getPlatformScene()
  const redirectPath = normalizePath(redirect || getCurrentPageUrl())
  const query = {
    scene: currentScene,
    redirect: redirectPath || ''
  }

  return `${LOGIN_PAGE_PATH}${buildQueryString(query)}`
}

/**
 * 跳转到登录页，并保存登录成功后的回跳地址。
 *
 * @param {Object} options 跳转选项。
 * @param {string} [options.redirect] 登录成功后的目标回跳地址。
 * @returns {boolean} 成功触发跳转时返回 true；已在登录页或跳转锁生效时返回 false。
 */
export function navigateToLogin(options = {}) {
  const redirectPath = options.redirect || getCurrentPageUrl()
  if (redirectPath) {
    savePostLoginRedirect(redirectPath)
  }

  if (loginJumpLocked || isLoginPagePath(getCurrentPageUrl())) {
    return false
  }

  setLoginJumpLock()
  uni.navigateTo({
    url: buildLoginPageUrl(redirectPath),
    fail: () => {
      uni.redirectTo({
        url: buildLoginPageUrl(redirectPath),
        fail: () => {
          uni.reLaunch({
            url: buildLoginPageUrl(redirectPath)
          })
        }
      })
    }
  })
  return true
}

/**
 * 处理未授权状态：清空本地认证缓存并跳转登录页。
 *
 * @param {Object} options 跳转登录页的附加选项。
 * @param {string} [options.redirect] 登录成功后的目标回跳地址。
 * @returns {boolean} 成功触发跳转时返回 true。
 */
export function handleUnauthorized(options = {}) {
  clearAuthStorage()
  return navigateToLogin(options)
}

/**
 * H5 OAuth 回调后从当前 URL 中解析 token。
 *
 * @returns {string} 成功解析并保存的 token；非 H5、无 token 或无效 token 时返回空字符串。
 */
export function resolveOauthTokenFromUrl() {
  // #ifdef H5
  if (!isH5() || typeof window === 'undefined' || !window.location) {
    return ''
  }

  const search = String(window.location.search || '')
  if (!search || !search.includes('token=')) {
    return ''
  }

  const params = new URLSearchParams(search)
  const token = String(params.get('token') || '').trim()
  const redirect = normalizePath(params.get('redirect') || '')

  if (!token) {
    return ''
  }

  setToken(token)
  clearStoredSpreadUid()
  if (redirect) {
    savePostLoginRedirect(redirect)
  }

  params.delete('token')
  const nextQuery = params.toString()
  const nextUrl = `${window.location.pathname}${nextQuery ? `?${nextQuery}` : ''}${window.location.hash || ''}`
  window.history.replaceState({}, document.title, nextUrl)
  return token
  // #endif

  return ''
}

/**
 * 应用启动时初始化认证状态。
 *
 * @returns {string} H5 OAuth 回调解析到的 token；其他场景返回空字符串。
 */
export function bootstrapAuth() {
  return resolveOauthTokenFromUrl()
}

/**
 * 登录成功后执行页面回跳。
 *
 * @param {string} redirect 指定回跳地址；为空时优先回到上一页，最后兜底到首页。
 * @returns {string} 实际跳转的页面地址。
 */
export function redirectAfterLogin(redirect = '') {
  resetLoginJumpLock()
  clearPostLoginRedirect()

  // #ifdef MP-WEIXIN
  const miniappPages = getCurrentPages()
  const miniappPreviousPage = Array.isArray(miniappPages) && miniappPages.length > 1 ? miniappPages[miniappPages.length - 2] : null
  const miniappPreviousUrl = getPageUrl(miniappPreviousPage)
  if (miniappPreviousUrl && !isLoginPagePath(miniappPreviousUrl)) {
    uni.navigateBack({ delta: 1 })
    return miniappPreviousUrl
  }
  // #endif

  const redirectPath = normalizePath(redirect)
  if (redirectPath && !isLoginPagePath(redirectPath)) {
    if (isTabBarPath(redirectPath)) {
      uni.switchTab({ url: normalizePath(String(redirectPath).split('?')[0]) })
    } else {
      uni.redirectTo({ url: redirectPath })
    }
    return redirectPath
  }

  const pages = getCurrentPages()
  const previousPage = Array.isArray(pages) && pages.length > 1 ? pages[pages.length - 2] : null
  const previousUrl = getPageUrl(previousPage)

  if (previousUrl && !isLoginPagePath(previousUrl)) {
    uni.navigateBack({
      delta: 1
    })
    return previousUrl
  }

  uni.switchTab({
    url: DEFAULT_AFTER_LOGIN_PATH,
    fail: () => {
      uni.reLaunch({ url: DEFAULT_AFTER_LOGIN_PATH })
    }
  })
  return DEFAULT_AFTER_LOGIN_PATH
}

export default {
  AUTH_MODES,
  LOGIN_PAGE_PATH,
  DEFAULT_AFTER_LOGIN_PATH,
  getToken,
  setToken,
  clearToken,
  getTokenExpiry,
  setTokenExpiry,
  clearTokenExpiry,
  getUserInfo,
  setUserInfo,
  clearUserInfo,
  clearAuthStorage,
  isLoggedIn,
  saveAuthResult,
  getCurrentPageUrl,
  isLoginPagePath,
  isTabBarPath,
  savePostLoginRedirect,
  buildLoginPageUrl,
  navigateToLogin,
  handleUnauthorized,
  resolveOauthTokenFromUrl,
  bootstrapAuth,
  redirectAfterLogin
}
