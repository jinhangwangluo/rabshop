import { getWechatJsSdkConfig } from '@/api/wechat'
import { isWechatBrowser } from '@/utils/platform'

const WECHAT_JS_SDK_URL = 'https://res.wx.qq.com/open/js/jweixin-1.6.0.js'

/**
 * 标准化 JS-SDK API 权限列表。
 *
 * @param {*} value 后端或调用方传入的权限列表。
 * @returns {string[]} 可传入 wx.config 的权限列表。
 */
function normalizeJsApiList(value) {
  if (Array.isArray(value)) {
    return value.map((item) => String(item || '').trim()).filter(Boolean)
  }

  if (typeof value === 'string') {
    return value.split(',').map((item) => item.trim()).filter(Boolean)
  }

  return []
}

/**
 * 组装 JS-SDK 配置接口参数。
 *
 * @param {string} url 当前页面签名 URL。
 * @param {Object} options 配置参数。
 * @returns {Object} 接口查询参数。
 */
function buildJsSdkConfigParams(url = '', options = {}) {
  const params = {
    url,
    debug: !!options.debug
  }

  if (options.jsApiList !== undefined) {
    params.jsApiList = options.jsApiList
  }

  if (options.openTagList !== undefined) {
    params.openTagList = options.openTagList
  }

  return params
}

/**
 * 规范化后端返回的 JS-SDK 配置。
 *
 * @param {Object} config 后端签名配置。
 * @returns {Object} 可直接传给 wx.config 的配置。
 */
function normalizeWxConfig(config = {}) {
  const source = config && typeof config === 'object' ? config : {}
  const jsApiList = normalizeJsApiList(source.jsApiList && source.jsApiList.length ? source.jsApiList : source.apiList)

  return {
    ...source,
    jsApiList
  }
}

/**
 * 获取当前页面用于 JS-SDK 签名的 URL。
 *
 * @returns {string} H5 微信浏览器当前页面 URL，不包含 hash。
 */
export function getCurrentWechatJsSdkUrl() {
  // #ifdef H5
  if (typeof window !== 'undefined' && window.location) {
    return String(window.location.href || '').split('#')[0]
  }
  // #endif

  return ''
}

/**
 * 加载微信 JS-SDK 脚本。
 *
 * @returns {Promise<Object|null>} H5 微信浏览器中返回 window.wx，非 H5 或加载失败返回 null。
 */
export function loadWechatJsSdk() {
  // #ifdef H5
  if (typeof window === 'undefined' || typeof document === 'undefined') {
    return Promise.resolve(null)
  }

  if (window.wx && typeof window.wx.config === 'function') {
    return Promise.resolve(window.wx)
  }

  if (window.__wechatJsSdkLoading) {
    return window.__wechatJsSdkLoading
  }

  window.__wechatJsSdkLoading = new Promise((resolve) => {
    const existingScript = document.querySelector(`script[src="${WECHAT_JS_SDK_URL}"]`)
    const script = existingScript || document.createElement('script')

    script.onload = () => resolve(window.wx || null)
    script.onerror = () => resolve(null)

    if (!existingScript) {
      script.src = WECHAT_JS_SDK_URL
      script.async = true
      document.head.appendChild(script)
    }
  })

  return window.__wechatJsSdkLoading
  // #endif

  return Promise.resolve(null)
}

/**
 * 配置微信 JS-SDK 鉴权。
 *
 * @param {Object} options 配置参数。
 * @param {string} [options.url] 当前页面 URL，不传时自动取当前 H5 URL。
 * @param {string[]} [options.jsApiList] 需要鉴权的 JS API 列表。
 * @param {string[]} [options.openTagList] 需要鉴权的开放标签列表。
 * @param {boolean} [options.debug=false] 是否开启调试。
 * @param {boolean} [options.showError=false] 接口失败时是否展示错误提示。
 * @returns {Promise<{configured:boolean,reason?:string,config?:Object,error?:Object}>}
 */
export async function configWechatJsSdk(options = {}) {
  if (!isWechatBrowser()) {
    return {
      configured: false,
      reason: 'not_wechat_browser'
    }
  }

  const url = String(options.url || getCurrentWechatJsSdkUrl()).trim()
  if (!url) {
    return {
      configured: false,
      reason: 'missing_url'
    }
  }

  const result = await getWechatJsSdkConfig(buildJsSdkConfigParams(url, options), {
    showError: options.showError === true
  })

  if (result.code !== 200 || !result.data) {
    return {
      configured: false,
      reason: 'config_request_failed',
      error: result
    }
  }

  const wx = await loadWechatJsSdk()
  if (!wx || typeof wx.config !== 'function') {
    return {
      configured: false,
      reason: 'sdk_load_failed'
    }
  }

  const wxConfig = normalizeWxConfig(result.data)

  return new Promise((resolve) => {
    wx.ready(() => {
      resolve({
        configured: true,
        config: wxConfig
      })
    })

    wx.error((error) => {
      resolve({
        configured: false,
        reason: 'sdk_config_failed',
        config: wxConfig,
        error
      })
    })

    wx.config(wxConfig)
  })
}

export default {
  getCurrentWechatJsSdkUrl,
  loadWechatJsSdk,
  normalizeJsApiList,
  configWechatJsSdk
}
