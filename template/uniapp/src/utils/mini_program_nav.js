const DEFAULT_MINI_PROGRAM_NAV_METRICS = {
  statusBarHeight: 24,
  windowWidth: 375,
  topGap: 6,
  menuHeight: 32,
  searchHeight: 38,
  contentGap: 12,
  leftInset: 12,
  rightInset: 104,
  rightExtra: 8
}

function toFiniteNumber(value, fallback = 0) {
  const normalized = Number(value)
  return Number.isFinite(normalized) ? normalized : fallback
}

function toPositiveNumber(value, fallback = 0) {
  const normalized = toFiniteNumber(value, fallback)
  return normalized > 0 ? normalized : fallback
}

function getSystemInfoSafe() {
  try {
    return typeof uni !== 'undefined' && typeof uni.getSystemInfoSync === 'function'
      ? uni.getSystemInfoSync()
      : {}
  } catch (e) {
    return {}
  }
}

function getMenuButtonSafe() {
  try {
    if (typeof uni === 'undefined' || typeof uni.getMenuButtonBoundingClientRect !== 'function') {
      return null
    }

    const rect = uni.getMenuButtonBoundingClientRect()
    const top = toPositiveNumber(rect && rect.top, 0)
    const left = toPositiveNumber(rect && rect.left, 0)
    const height = toPositiveNumber(rect && rect.height, 0)

    return top > 0 && left > 0 && height > 0 ? rect : null
  } catch (e) {
    return null
  }
}

/**
 * 获取微信小程序胶囊按钮相关导航尺寸。
 *
 * @param {Object} options 自定义兜底尺寸。
 * @returns {Object} 导航、搜索框和胶囊避让尺寸，单位为 px。
 */
export function getMiniProgramSearchNavMetrics(options = {}) {
  const fallback = {
    ...DEFAULT_MINI_PROGRAM_NAV_METRICS,
    ...options
  }
  const systemInfo = getSystemInfoSafe()
  const menuButton = getMenuButtonSafe()
  const statusBarHeight = Math.ceil(toPositiveNumber(systemInfo.statusBarHeight, fallback.statusBarHeight))
  const windowWidth = toPositiveNumber(systemInfo.windowWidth || systemInfo.screenWidth, fallback.windowWidth || 375)
  const menuTop = toPositiveNumber(menuButton && menuButton.top, statusBarHeight + fallback.topGap)
  const menuHeight = toPositiveNumber(menuButton && menuButton.height, fallback.menuHeight)
  const topGap = Math.max(Math.ceil(menuTop - statusBarHeight), fallback.topGap)
  const searchHeight = Math.max(Math.ceil(menuHeight), fallback.searchHeight)
  const rightInset = menuButton && menuButton.left
    ? Math.max(Math.ceil(windowWidth - toFiniteNumber(menuButton.left, 0) + fallback.rightExtra), fallback.rightInset)
    : fallback.rightInset

  return {
    statusBarHeight,
    windowWidth,
    topGap,
    menuHeight: Math.ceil(menuHeight),
    searchHeight,
    contentGap: fallback.contentGap,
    leftInset: fallback.leftInset,
    rightInset
  }
}

/**
 * 将 rpx 设计尺寸转换为当前设备 px。
 *
 * @param {number|string} value 需要转换的 rpx 数值。
 * @param {number} windowWidth 当前窗口宽度，单位 px。
 * @returns {number} 转换后的 px。
 */
export function rpxToPx(value, windowWidth = 375) {
  const size = Number(value || 0)
  const width = Number(windowWidth || 375)

  if (!size || !width) {
    return 0
  }

  return Math.ceil((size * width) / 750)
}
