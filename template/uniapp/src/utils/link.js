/**
 * 判断目标链接是否为 UniApp 内部页面路径。
 *
 * @param {string} url 待判断的目标链接。
 * @returns {boolean} 命中内部页面路径时返回 true。
 */
export function isInternalPageUrl(url = '') {
  return /^\/pages\//.test(String(url || '').trim())
}

/**
 * 按链接类型执行跳转。
 *
 * - 内部页面：统一走 `uni.navigateTo`
 * - H5 外链：直接跳转浏览器地址
 * - 微信小程序等非 H5 端外链：复制链接并提示用户
 *
 * @param {string} url 目标链接。
 * @param {Object} options 附加配置。
 * @param {string} options.copyTip 非 H5 端复制成功后的提示文案。
 * @returns {boolean} 触发了跳转或兜底处理时返回 true。
 */
export function navigateByLink(url = '', options = {}) {
  const targetUrl = String(url || '').trim()
  if (!targetUrl) {
    return false
  }

  if (isInternalPageUrl(targetUrl)) {
    uni.navigateTo({ url: targetUrl })
    return true
  }

  if (/^https?:\/\//i.test(targetUrl)) {
    // #ifdef H5
    window.location.href = targetUrl
    return true
    // #endif

    // #ifndef H5
    uni.setClipboardData({
      data: targetUrl,
      success: () => {
        uni.showToast({
          title: options.copyTip || '当前端不支持直接打开外链，链接已复制',
          icon: 'none'
        })
      }
    })
    return true
    // #endif
  }

  uni.navigateTo({ url: targetUrl })
  return true
}
