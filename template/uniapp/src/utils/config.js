/**
 * 当前站点域名。
 *
 * 说明：
 * - H5 端未手动配置域名时，默认使用当前访问域名；
 * - 微信小程序没有浏览器域名上下文，仍需要手动填写 `currentDomain`。
 */
let currentDomain = 'http://127.0.0.1:25780'

//-----------以下部分不是开发者无需修改-----------
//-----------以下部分不是开发者无需修改-----------
//-----------以下部分不是开发者无需修改-----------

// #ifdef H5
currentDomain = currentDomain || location.origin
// #endif
export const CURRENT_DOMAIN = currentDomain

/**
 * 移动端静态配置。
 *
 * 说明：
 * - 本文件仅存放配置常量；
 * - 运行时方法（资源地址解析、Vue 注入等）统一放在 `runtime_config.js`。
 */

export const API_PREFIX = '/api/mobile'
export const WS_PATH = '/ws'

/**
 * 运行时默认配置。
 *
 * 使用说明：
 * - `apiBaseUrl`：默认接口基础地址；未手动缓存时，请求会优先使用该值。
 *   示例值：`https://demo.com/api/mobile`
 * - `wsBaseUrl`：默认 WebSocket 基础地址；未手动缓存时会优先使用该值。
 *   示例值：`wss://demo.com/ws`
 * - `runtimeOrigin`：默认站点域名；当未单独配置 API / WS 地址时，可用于统一推导。
 *   示例值：`https://demo.com`
 */
export const DEFAULT_RUNTIME_CONFIG = Object.freeze({
	apiBaseUrl: CURRENT_DOMAIN ? `${CURRENT_DOMAIN}${API_PREFIX}` : '',
	wsBaseUrl: '',
	runtimeOrigin: CURRENT_DOMAIN
})


/**
 * 本地缓存 key 定义。
 *
 * 说明：
 * - 这些值仅作为 `uni.setStorageSync` / `uni.getStorageSync` 的缓存键名使用；
 * - 如需配置默认请求地址，请修改上方 `DEFAULT_RUNTIME_CONFIG`。
 */
export const STORAGE_KEYS = Object.freeze({
	apiBaseUrl: 'mobile_api_base_url',
	wsBaseUrl: 'mobile_ws_base_url',
	runtimeOrigin: 'mobile_runtime_origin',
	themeConfig: 'mobile_theme_config'
})

export default Object.freeze({
	STORAGE_KEYS,
	DEFAULT_RUNTIME_CONFIG,
	CURRENT_DOMAIN,
	API_PREFIX,
	WS_PATH
})
