import {
	getRuntimeConfig
} from '@/utils/runtime_config'
import {
	AUTH_MODES,
	clearAuthStorage,
	getToken,
	getTokenExpiry,
	handleUnauthorized,
	setToken,
	setTokenExpiry
} from '@/utils/auth'
import {
	getTerminalType
} from '@/utils/platform'
import { normalizePublicErrorMessage } from '@/utils/error'

/**
 * UniApp 通用请求封装。
 *
 * 使用方式示例：
 * - 匿名接口：`get('diy/home', {}, { authMode: AUTH_MODES.NONE })`
 * - 可登录可匿名：`get('content/list')`
 * - 必须登录：`post('auth/logout', {}, { authMode: AUTH_MODES.REQUIRED })`
 */

const DEFAULT_TIMEOUT = 15000
const TERMINAL_HEADER_NAME = 'X-Terminal-Type'
const REFRESH_TOKEN_PATH = 'auth/refreshToken'
const MOBILE_TOKEN_TIMEOUT_SECONDS = 1296000
const TOKEN_REFRESH_THRESHOLD = 0.2
const DEFAULT_HEADERS = {
	'Content-Type': 'application/json'
}

let isRefreshing = false
let pendingRequests = []

/**
 * 判断是否为绝对地址。
 * 绝对地址会跳过 baseUrl 拼接逻辑，直接发起请求。
 *
 * @param {string} url 请求地址。
 * @returns {boolean}
 */
function isAbsoluteUrl(url = '') {
	return /^(https?:)?\/\//i.test(String(url || ''))
}

/**
 * 清洗 URL 字符串，统一去掉两端空白。
 *
 * @param {string} url 原始地址。
 * @returns {string}
 */
function normalizeUrl(url = '') {
	return String(url || '').trim()
}

/**
 * 将相对路径统一转成以 `/` 开头的路径。
 *
 * @param {string} path 相对路径。
 * @returns {string}
 */
function normalizePath(path = '') {
	const normalized = normalizeUrl(path)
	if (!normalized) {
		return ''
	}

	return normalized.startsWith('/') ? normalized : `/${normalized}`
}

/**
 * 拼接基础地址和路径。
 *
 * @param {string} base 基础地址。
 * @param {string} path 路径。
 * @returns {string}
 */
function joinUrl(base = '', path = '') {
	const normalizedBase = String(base || '').replace(/\/+$/, '')
	const normalizedPath = normalizePath(path)

	if (!normalizedBase) {
		return normalizedPath || '/'
	}

	if (!normalizedPath) {
		return normalizedBase
	}

	return `${normalizedBase}${normalizedPath}`
}

/**
 * 判断返回码字段是否可被安全转换为数字。
 *
 * @param {*} value 后端返回的 code 或 status 字段。
 * @returns {boolean}
 */
function isNumericCode(value) {
	return value !== '' && value !== null && value !== undefined && !Number.isNaN(Number(value))
}

/**
 * 规范化鉴权模式。
 * 未传或传入非法值时，默认按 `OPTIONAL` 处理。
 *
 * @param {string} authMode 鉴权模式。
 * @returns {string}
 */
function resolveAuthMode(authMode = '') {
	return Object.values(AUTH_MODES).includes(authMode) ? authMode : AUTH_MODES.OPTIONAL
}

/**
 * 查找请求头中已存在的键名（忽略大小写）。
 *
 * @param {Object} headers 请求头对象。
 * @param {string} headerName 目标请求头名称。
 * @returns {string}
 */
function findHeaderKey(headers = {}, headerName = '') {
	const target = String(headerName || '').trim().toLowerCase()
	if (!target) {
		return ''
	}

	return Object.keys(headers).find((key) => String(key || '').toLowerCase() === target) || ''
}

/**
 * 构建请求头。
 * 当接口允许附带登录态且本地已有 token 时，会自动补充 `Authorization: Bearer xxx`；
 * 同时会自动补充 `X-Terminal-Type`，用于标记当前访问终端。
 *
 * 终端类型取值：
 * - h5：普通 H5
 * - official_account：公众号
 * - miniapp：微信小程序
 * - app：原生 App
 *
 * @param {Object} header 自定义请求头。
 * @param {string} authMode 鉴权模式。
 * @returns {Object}
 */
function buildHeaders(header = {}, authMode = AUTH_MODES.OPTIONAL) {
	const headers = {
		...DEFAULT_HEADERS,
		...header
	}

	const token = getToken()
	if (token && authMode !== AUTH_MODES.NONE && !findHeaderKey(headers, 'Authorization')) {
		headers.Authorization = token.startsWith('Bearer ') ? token : `Bearer ${token}`
	}

	if (!findHeaderKey(headers, TERMINAL_HEADER_NAME)) {
		headers[TERMINAL_HEADER_NAME] = getTerminalType()
	}

	return headers
}

/**
 * 生成最终请求地址。
 *
 * 规则如下：
 * 1. 绝对地址直接使用；
 * 2. 以 `/api/` 开头的地址，按当前运行域名拼接；
 * 3. 其余地址默认拼接到 `getApiBaseUrl()` 返回值后面。
 *
 * @param {string} url 业务侧传入的地址。
 * @returns {string}
 */
export function buildRequestUrl(url = '') {
	const requestUrl = normalizeUrl(url)
	if (!requestUrl) {
		return getRuntimeConfig().apiBaseUrl
	}

	if (isAbsoluteUrl(requestUrl)) {
		return requestUrl
	}

	if (requestUrl.startsWith('/api/')) {
		const {
			origin
		} = getRuntimeConfig()
		return origin ? joinUrl(origin, requestUrl) : requestUrl
	}

	return joinUrl(getRuntimeConfig().apiBaseUrl, requestUrl)
}

/**
 * 统一后端响应结构。
 * 同时兼容 `{ code, msg, data }` 与 `{ status, message, ... }` 这两类返回格式。
 *
 * @param {Object} response `uni.request` 原始响应对象。
 * @returns {{code:number,msg:string,data:*,raw:Object}}
 */
function normalizeResponse(response = {}) {
	const {
		statusCode = 500, data
	} = response

	if (data && typeof data === 'object' && !Array.isArray(data)) {
		const code = isNumericCode(data.code) ?
			Number(data.code) :
			(isNumericCode(data.status) ? Number(data.status) : statusCode)

		return {
			code,
			msg: normalizePublicErrorMessage(
				data.msg || data.message,
				statusCode === 200 ? '请求成功' : '请求失败'
			),
			data: data.data !== undefined ? data.data : data,
			raw: response
		}
	}

	return {
		code: statusCode,
		msg: statusCode === 200 ? '请求成功' : '请求失败',
		data: data !== undefined ? data : null,
		raw: response
	}
}

/**
 * 弹出请求错误提示。
 *
 * @param {string} message 要展示的错误文案。
 */
function showRequestError(message = '') {
	const title = normalizePublicErrorMessage(message, '请求失败')
	uni.showToast({
		title,
		icon: 'none'
	})
}

/**
 * 构造“未登录”统一返回对象。
 *
 * @param {string} message 错误提示。
 * @returns {{code:number,msg:string,data:null}}
 */
function buildUnauthorizedResult(message = '请先登录') {
	return {
		code: 401,
		msg: message,
		data: null
	}
}

/**
 * 读取响应头字段，兼容不同端大小写处理。
 *
 * @param {Object} headers 响应头对象。
 * @param {string} headerName 目标响应头名称。
 * @returns {string}
 */
function getHeaderValue(headers = {}, headerName = '') {
	const key = findHeaderKey(headers || {}, headerName)
	return key ? String(headers[key] || '').trim() : ''
}

/**
 * 保存刷新后的 token 与过期时间。
 *
 * @param {string} token 新 token。
 * @param {number} expiresIn 有效期秒数。
 * @returns {string}
 */
function saveRefreshedToken(token = '', expiresIn = MOBILE_TOKEN_TIMEOUT_SECONDS) {
	const normalized = setToken(token)
	if (normalized) {
		setTokenExpiry(Date.now() + Number(expiresIn || MOBILE_TOKEN_TIMEOUT_SECONDS) * 1000)
	}

	return normalized
}

/**
 * 处理等待刷新完成的请求。
 *
 * @param {string} newToken 新 token，刷新失败为空。
 */
function resolvePendingRequests(newToken = '', shouldRejectOnFailure = true) {
	const queue = pendingRequests
	pendingRequests = []

	queue.forEach(({
		options,
		resolve
	}) => {
		if (!newToken && shouldRejectOnFailure) {
			resolve(buildUnauthorizedResult('token刷新失败'))
			return
		}

		request({
			...options,
			skipTokenRefresh: true
		}).then(resolve)
	})
}

/**
 * 判断当前请求是否需要在请求前主动刷新 token。
 *
 * @param {string} authMode 鉴权模式。
 * @param {boolean} skipTokenRefresh 是否跳过刷新。
 * @returns {boolean}
 */
function shouldRefreshBeforeRequest(authMode = AUTH_MODES.OPTIONAL, skipTokenRefresh = false) {
	if (skipTokenRefresh || authMode === AUTH_MODES.NONE || !getToken()) {
		return false
	}

	const expiry = getTokenExpiry()
	if (!expiry) {
		return false
	}

	const remaining = expiry - Date.now()
	return remaining > 0 && remaining < MOBILE_TOKEN_TIMEOUT_SECONDS * 1000 * TOKEN_REFRESH_THRESHOLD
}

/**
 * 调用移动端刷新 token 接口。
 *
 * @returns {Promise<string>} 成功返回新 token，失败返回空字符串。
 */
function refreshMobileToken() {
	const token = getToken()
	if (!token) {
		return Promise.resolve('')
	}

	return new Promise((resolve) => {
		uni.request({
			url: buildRequestUrl(REFRESH_TOKEN_PATH),
			method: 'POST',
			data: {},
			timeout: DEFAULT_TIMEOUT,
			header: buildHeaders({}, AUTH_MODES.REQUIRED),
			success: (response) => {
				const result = normalizeResponse(response)
				if (result.code !== 200 || !result.data || !result.data.token) {
					resolve('')
					return
				}

				resolve(saveRefreshedToken(result.data.token, result.data.expiresIn))
			},
			fail: () => {
				resolve('')
			}
		})
	})
}

/**
 * 确保当前 token 可继续使用。
 *
 * @param {Object} options 当前请求配置。
 * @param {Function} resolve Promise resolve。
 * @returns {Promise<boolean>} true 表示当前请求应等待队列重试。
 */
function ensureTokenFresh(options = {}, resolve) {
	const authMode = resolveAuthMode(options.authMode)
	if (!shouldRefreshBeforeRequest(authMode, options.skipTokenRefresh === true)) {
		return Promise.resolve(false)
	}

	if (isRefreshing) {
		pendingRequests.push({
			options,
			resolve
		})
		return Promise.resolve(true)
	}

	isRefreshing = true
	return refreshMobileToken()
		.then((newToken) => {
			resolvePendingRequests(newToken, false)
			return false
		})
		.finally(() => {
			isRefreshing = false
		})
}

/**
 * 尝试刷新 token 并重试当前请求。
 *
 * @param {Object} options 当前请求配置。
 * @param {Function} resolve Promise resolve。
 * @param {Object} fallbackResult 刷新失败时返回的响应。
 * @returns {boolean} 是否已接管当前请求。
 */
function retryAfterRefresh(options = {}, resolve, fallbackResult = buildUnauthorizedResult()) {
	if (options.skipTokenRefresh === true || !getToken()) {
		return false
	}

	if (isRefreshing) {
		pendingRequests.push({
			options,
			resolve
		})
		return true
	}

	isRefreshing = true
	refreshMobileToken()
		.then((newToken) => {
			resolvePendingRequests(newToken)
			if (!newToken) {
				clearAuthStorage()
				if (options.loginRedirect !== false) {
					handleUnauthorized({
						redirect: options.redirect
					})
				}
				resolve(fallbackResult)
				return
			}

			request({
				...options,
				skipTokenRefresh: true
			}).then(resolve)
		})
		.finally(() => {
			isRefreshing = false
		})

	return true
}

/**
 * 发起统一请求。
 *
 * 使用建议：
 * - 需要匿名访问时传 `authMode: AUTH_MODES.NONE`；
 * - 需要登录时传 `authMode: AUTH_MODES.REQUIRED`；
 * - 不想自动跳登录页时传 `loginRedirect: false`。
 *
 * @param {Object} options 请求配置。
 * @param {string} options.url 请求地址。
 * @param {string} [options.method='GET'] 请求方法。
 * @param {Object} [options.data={}] 请求参数。
 * @param {Object} [options.header={}] 自定义请求头。
 * @param {number} [options.timeout=15000] 超时时间，单位毫秒。
 * @param {boolean} [options.showError=true] 是否自动 toast 错误提示。
 * @param {string} [options.authMode=AUTH_MODES.OPTIONAL] 鉴权模式。
 * @param {boolean} [options.loginRedirect=true] 401 时是否自动跳转登录页。
 * @param {boolean} [options.skipTokenRefresh=false] 是否跳过 token 刷新。
 * @param {string} [options.redirect] 登录成功后的回跳地址。
 * @returns {Promise<Object>} 统一响应对象。
 */
export function request(options = {}) {
	const {
		url,
		method = 'GET',
		data = {},
		header = {},
		timeout = DEFAULT_TIMEOUT,
		showError = true,
		authMode = AUTH_MODES.OPTIONAL,
		loginRedirect = true,
		skipTokenRefresh = false,
		redirect
	} = options

	const normalizedAuthMode = resolveAuthMode(authMode)
	const token = getToken()
	const requestOptions = {
		...options,
		url,
		method,
		data,
		header,
		timeout,
		showError,
		authMode,
		loginRedirect,
		skipTokenRefresh,
		redirect
	}

	return new Promise((resolve) => {
		if (normalizedAuthMode === AUTH_MODES.REQUIRED && !token) {
			if (loginRedirect !== false) {
				handleUnauthorized({
					redirect
				})
			} else if (showError) {
				showRequestError('请先登录')
			}

			resolve(buildUnauthorizedResult())
			return
		}

		ensureTokenFresh(requestOptions, resolve).then((isQueued) => {
			if (isQueued) {
				return
			}

			uni.request({
				url: buildRequestUrl(url),
				method: String(method || 'GET').toUpperCase(),
				data,
				timeout,
				header: buildHeaders(header, normalizedAuthMode),
				success: (response) => {
					const result = normalizeResponse(response)
					const newToken = getHeaderValue(response.header || response.headers ||
						{}, 'X-New-Token')
					if (newToken && normalizedAuthMode !== AUTH_MODES.NONE) {
						saveRefreshedToken(newToken)
					}

					if (result.code === 401) {
						const handled = normalizedAuthMode !== AUTH_MODES.NONE &&
							retryAfterRefresh(requestOptions, resolve, result)
						if (!handled) {
							clearAuthStorage()
							if (normalizedAuthMode !== AUTH_MODES.NONE && loginRedirect !==
								false) {
								handleUnauthorized({
									redirect
								})
							} else if (showError) {
								showRequestError(result.msg)
							}

							resolve(result)
						}

						return
					}

					if (showError && result.code !== 200) {
						showRequestError(result.msg)
					}

					resolve(result)
				},
				fail: (error) => {
					const message = normalizePublicErrorMessage(
						error && (error.errMsg || error.message),
						'网络请求失败'
					)
					const result = {
						code: 500,
						msg: message,
						data: null,
						error
					}

					if (showError) {
						showRequestError(result.msg)
					}

					resolve(result)
				}
			})
		})
	})
}

/**
 * GET 请求快捷方法。
 *
 * @param {string} url 请求地址。
 * @param {Object} params 查询参数。
 * @param {Object} options 附加请求选项。
 * @returns {Promise<Object>}
 */
export function get(url, params = {}, options = {}) {
	return request({
		url,
		method: 'GET',
		data: params,
		...options
	})
}

/**
 * POST 请求快捷方法。
 *
 * @param {string} url 请求地址。
 * @param {Object} data 请求体。
 * @param {Object} options 附加请求选项。
 * @returns {Promise<Object>}
 */
export function post(url, data = {}, options = {}) {
	return request({
		url,
		method: 'POST',
		data,
		...options
	})
}

/**
 * PUT 请求快捷方法。
 *
 * @param {string} url 请求地址。
 * @param {Object} data 请求体。
 * @param {Object} options 附加请求选项。
 * @returns {Promise<Object>}
 */
export function put(url, data = {}, options = {}) {
	return request({
		url,
		method: 'PUT',
		data,
		...options
	})
}

/**
 * DELETE 请求快捷方法。
 *
 * @param {string} url 请求地址。
 * @param {Object} data 请求体。
 * @param {Object} options 附加请求选项。
 * @returns {Promise<Object>}
 */
export function del(url, data = {}, options = {}) {
	return request({
		url,
		method: 'DELETE',
		data,
		...options
	})
}

export default request
