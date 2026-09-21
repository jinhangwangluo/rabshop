export const PLATFORM_SCENES = Object.freeze({
  H5: 'h5',
  WECHAT_H5: 'wechat-h5',
  MINIAPP: 'miniapp',
  APP: 'app',
  UNKNOWN: 'unknown'
})

export const TERMINAL_TYPES = Object.freeze({
  H5: 'h5',
  MINIAPP: 'miniapp',
  OFFICIAL_ACCOUNT: 'official_account',
  APP: 'app',
  UNKNOWN: 'unknown'
})

/**
 * 是否为 H5 环境（编译时条件）
 * @returns {boolean}
 */
export function isH5() {
  // #ifdef H5
  return true
  // #endif
  return false
}

/**
 * 是否为微信小程序环境（编译时条件）
 * @returns {boolean}
 */
export function isMiniProgram() {
  // #ifdef MP-WEIXIN
  return true
  // #endif
  return false
}

/**
 * 是否为 App 环境（编译时条件）
 * @returns {boolean}
 */
export function isApp() {
  // #ifdef APP-PLUS
  return true
  // #endif
  return false
}

function getUserAgent() {
  // #ifdef H5
  if (typeof navigator !== 'undefined' && navigator.userAgent) {
    return String(navigator.userAgent).toLowerCase()
  }
  // #endif
  return ''
}

/**
 * 是否为微信内置浏览器（仅 H5 有效）
 * @returns {boolean}
 */
export function isWechatBrowser() {
  return isH5() && /micromessenger/i.test(getUserAgent())
}

/**
 * 获取当前平台场景标识
 * 可能值：'h5' | 'wechat-h5' | 'miniapp' | 'app' | 'unknown'
 * @returns {('h5'|'wechat-h5'|'miniapp'|'app'|'unknown')}
 */
export function getPlatformScene() {
  if (isMiniProgram()) {
    return PLATFORM_SCENES.MINIAPP
  }

  if (isWechatBrowser()) {
    return PLATFORM_SCENES.WECHAT_H5
  }

  if (isApp()) {
    return PLATFORM_SCENES.APP
  }

  if (isH5()) {
    return PLATFORM_SCENES.H5
  }

  return PLATFORM_SCENES.UNKNOWN
}

/**
 * 获取当前平台场景中文标签
 * @returns {string}
 */
export function getPlatformSceneLabel() {
  const labelMap = {
    [PLATFORM_SCENES.H5]: 'H5',
    [PLATFORM_SCENES.WECHAT_H5]: '微信公众号',
    [PLATFORM_SCENES.MINIAPP]: '微信小程序',
    [PLATFORM_SCENES.APP]: 'APP',
    [PLATFORM_SCENES.UNKNOWN]: '当前环境'
  }

  return labelMap[getPlatformScene()] || labelMap[PLATFORM_SCENES.UNKNOWN]
}

/**
 * 获取当前请求终端类型。
 *
 * 用途：
 * - 统一给请求头透传终端来源；
 * - 便于后端记录“当前接口访问终端”。
 *
 * 返回值：
 * - h5：普通 H5 页面
 * - official_account：微信公众号内 H5
 * - miniapp：微信小程序
 * - app：UniApp 原生 App
 * - unknown：未识别环境
 *
 * @returns {('h5'|'official_account'|'miniapp'|'app'|'unknown')}
 */
export function getTerminalType() {
  const scene = getPlatformScene()

  const terminalMap = {
    [PLATFORM_SCENES.H5]: TERMINAL_TYPES.H5,
    [PLATFORM_SCENES.WECHAT_H5]: TERMINAL_TYPES.OFFICIAL_ACCOUNT,
    [PLATFORM_SCENES.MINIAPP]: TERMINAL_TYPES.MINIAPP,
    [PLATFORM_SCENES.APP]: TERMINAL_TYPES.APP,
    [PLATFORM_SCENES.UNKNOWN]: TERMINAL_TYPES.UNKNOWN
  }

  return terminalMap[scene] || TERMINAL_TYPES.UNKNOWN
}

/**
 * 获取当前请求终端类型中文标签
 * @returns {string}
 */
export function getTerminalTypeLabel() {
  const labelMap = {
    [TERMINAL_TYPES.H5]: 'H5',
    [TERMINAL_TYPES.OFFICIAL_ACCOUNT]: '公众号',
    [TERMINAL_TYPES.MINIAPP]: '微信小程序',
    [TERMINAL_TYPES.APP]: 'APP',
    [TERMINAL_TYPES.UNKNOWN]: '当前终端'
  }

  return labelMap[getTerminalType()] || labelMap[TERMINAL_TYPES.UNKNOWN]
}

export default {
  PLATFORM_SCENES,
  TERMINAL_TYPES,
  isH5,
  isMiniProgram,
  isApp,
  isWechatBrowser,
  getPlatformScene,
  getPlatformSceneLabel,
  getTerminalType,
  getTerminalTypeLabel
}
