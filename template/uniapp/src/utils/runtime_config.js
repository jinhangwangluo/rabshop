import { API_PREFIX, DEFAULT_RUNTIME_CONFIG, STORAGE_KEYS, WS_PATH } from './config'

/**
 * 运行时配置工具（去过度封装版）。
 * 仅暴露 4 个方法，内部逻辑尽量就地实现，避免额外小工具函数：
 * - getRuntimeConfig()
 * - setRuntimeConfig(cfg)
 * - resolveAssetUrl(url)
 * - clearRuntimeConfig()
 */

/**
 * 统一获取运行时配置。
 * @returns {{ origin: string, apiBaseUrl: string, wsBaseUrl: string }}
 */
export function getRuntimeConfig() {
  // 1) origin
  let origin = ''
  {
    const stored = String(uni.getStorageSync(STORAGE_KEYS.runtimeOrigin) || '').trim().replace(/\/+$/, '')
    if (stored) {
      origin = stored
    } else {
      const defOrigin = String(DEFAULT_RUNTIME_CONFIG.runtimeOrigin || '').trim().replace(/\/+$/, '')
      if (defOrigin) {
        origin = defOrigin
      } else {
        const defApi = String(DEFAULT_RUNTIME_CONFIG.apiBaseUrl || '').trim().replace(/\/+$/, '')
        if (defApi) {
          origin = defApi.endsWith(API_PREFIX)
            ? String(defApi.slice(0, -API_PREFIX.length)).trim().replace(/\/+$/, '')
            : defApi
        } else {
          // #ifdef H5
          if (typeof window !== 'undefined' && window.location && window.location.origin) {
            origin = String(window.location.origin).trim().replace(/\/+$/, '')
          }
          // #endif
        }
      }
    }
  }

  // 2) apiBaseUrl
  let apiBaseUrl = ''
  {
    const storedApi = String(uni.getStorageSync(STORAGE_KEYS.apiBaseUrl) || '').trim().replace(/\/+$/, '')
    if (storedApi) {
      apiBaseUrl = storedApi.endsWith(API_PREFIX) ? storedApi : `${storedApi}${API_PREFIX}`
    } else {
      const defApi = String(DEFAULT_RUNTIME_CONFIG.apiBaseUrl || '').trim().replace(/\/+$/, '')
      if (defApi) {
        apiBaseUrl = defApi.endsWith(API_PREFIX) ? defApi : `${defApi}${API_PREFIX}`
      } else {
        apiBaseUrl = origin ? `${origin}${API_PREFIX}` : API_PREFIX
      }
    }
  }

  // 3) wsBaseUrl
  let wsBaseUrl = ''
  {
    const storedWs = String(uni.getStorageSync(STORAGE_KEYS.wsBaseUrl) || '').trim().replace(/\/+$/, '')
    if (storedWs) {
      wsBaseUrl = storedWs
    } else {
      const defWs = String(DEFAULT_RUNTIME_CONFIG.wsBaseUrl || '').trim().replace(/\/+$/, '')
      if (defWs) {
        wsBaseUrl = defWs
      } else {
        if (!origin) {
          wsBaseUrl = WS_PATH
        } else {
          const wsOrigin = origin.startsWith('https://')
            ? origin.replace(/^https:\/\//i, 'wss://')
            : origin.replace(/^http:\/\//i, 'ws://')
          wsBaseUrl = `${wsOrigin}${WS_PATH}`
        }
      }
    }
  }

  return { origin, apiBaseUrl, wsBaseUrl }
}

/**
 * 一次性设置运行时配置。
 * @param {{ origin?: string, apiBaseUrl?: string, wsBaseUrl?: string }} cfg
 * @returns {{ origin: string, apiBaseUrl: string, wsBaseUrl: string }} 最新配置
 */
export function setRuntimeConfig(cfg = {}) {
  const { origin, apiBaseUrl, wsBaseUrl } = cfg || {}

  if (origin) {
    const n = String(origin || '').trim().replace(/\/+$/, '')
    uni.setStorageSync(STORAGE_KEYS.runtimeOrigin, n)
    // 设定域名后，清除单独缓存的 API/WS，统一按域名推导
    uni.removeStorageSync(STORAGE_KEYS.apiBaseUrl)
    uni.removeStorageSync(STORAGE_KEYS.wsBaseUrl)
  }

  if (apiBaseUrl) {
    const base = String(apiBaseUrl || '').trim().replace(/\/+$/, '')
    const resolved = base.endsWith(API_PREFIX) ? base : `${base}${API_PREFIX}`
    uni.setStorageSync(STORAGE_KEYS.apiBaseUrl, resolved)
  }

  if (wsBaseUrl) {
    const n = String(wsBaseUrl || '').trim().replace(/\/+$/, '')
    uni.setStorageSync(STORAGE_KEYS.wsBaseUrl, n)
  }

  return getRuntimeConfig()
}

/**
 * 解析资源地址（相对路径 → 绝对 URL）。
 * @param {string} url 原始资源地址
 * @returns {string} 可直接访问的 URL
 */
export function resolveAssetUrl(url = '') {
  const source = String(url || '').trim()
  if (!source || /^(https?:)?\/\//i.test(source) || /^wss?:\/\//i.test(source) || source.startsWith('data:')) {
    return source
  }
  const { origin } = getRuntimeConfig()
  if (!origin) return source
  return source.startsWith('/') ? `${origin}${source}` : `${origin}/${source}`
}

/** 清空运行时配置缓存，恢复默认自动推导。 */
export function clearRuntimeConfig() {
  uni.removeStorageSync(STORAGE_KEYS.apiBaseUrl)
  uni.removeStorageSync(STORAGE_KEYS.wsBaseUrl)
  uni.removeStorageSync(STORAGE_KEYS.runtimeOrigin)
}

// 插件注入（只暴露 4 个方法）
const runtimeConfigPlugin = {
  install(Vue) {
    Vue.prototype.$config = {
      getRuntimeConfig,
      setRuntimeConfig,
      resolveAssetUrl,
      clearRuntimeConfig
    }
  }
}

export default runtimeConfigPlugin
