const SPREAD_STORAGE_KEY = 'mobile_spread_uid'
const SPREAD_PARAM_NAMES = ['spread_uid', 'spreadUid', 'spid', 'invite_uid', 'inviteUid']

/**
 * 安全解码字符串。
 *
 * @param {*} value 原始值。
 * @returns {string} 解码后的字符串。
 */
function safeDecode(value = '') {
  const source = String(value || '').trim()
  if (!source) {
    return ''
  }

  try {
    return decodeURIComponent(source)
  } catch (e) {
    return source
  }
}

/**
 * 将查询字符串解析为对象。
 *
 * @param {string} queryString 查询字符串，支持带或不带问号。
 * @returns {Object} 查询参数对象。
 */
function parseQueryString(queryString = '') {
  const source = String(queryString || '').trim().replace(/^\?/, '')
  if (!source) {
    return {}
  }

  return source.split('&').reduce((query, part) => {
    const item = String(part || '').trim()
    if (!item) {
      return query
    }

    const [rawKey, ...rest] = item.split('=')
    const key = safeDecode(rawKey)
    if (!key) {
      return query
    }

    query[key] = safeDecode(rest.join('='))
    return query
  }, {})
}

/**
 * 从 URL 中提取查询参数。
 *
 * @param {string} url 页面地址或路径。
 * @returns {Object} 查询参数对象。
 */
function parseQueryFromUrl(url = '') {
  const source = String(url || '').trim()
  if (!source) {
    return {}
  }

  const queryPart = source.includes('?') ? source.split('?').slice(1).join('?').split('#')[0] : ''
  return parseQueryString(queryPart)
}

/**
 * 规范化分销上级 ID。
 *
 * @param {*} value 原始分销参数。
 * @returns {string} 合法分销上级 ID，不合法时返回空字符串。
 */
export function normalizeSpreadUid(value = '') {
  const normalized = String(value || '').trim()
  return /^[1-9]\d*$/.test(normalized) ? normalized : ''
}

/**
 * 从参数对象中读取分销上级 ID。
 *
 * @param {Object} params 查询或场景参数。
 * @returns {string} 分销上级 ID。
 */
export function extractSpreadUid(params = {}) {
  if (!params || typeof params !== 'object') {
    return ''
  }

  for (let i = 0; i < SPREAD_PARAM_NAMES.length; i += 1) {
    const value = normalizeSpreadUid(params[SPREAD_PARAM_NAMES[i]])
    if (value) {
      return value
    }
  }

  return ''
}

/**
 * 解析小程序 scene 中的分销参数。
 *
 * @param {*} scene 小程序码 scene，常见格式为 spread_uid=123。
 * @returns {string} 分销上级 ID。
 */
export function extractSpreadUidFromScene(scene = '') {
  const decoded = safeDecode(scene)
  if (!decoded) {
    return ''
  }

  const querySpreadUid = extractSpreadUid(parseQueryString(decoded))
  if (querySpreadUid) {
    return querySpreadUid
  }

  return normalizeSpreadUid(decoded)
}

/**
 * 保存分销上级 ID。
 *
 * @param {*} spreadUid 分销上级 ID。
 * @returns {string} 已保存的分销上级 ID。
 */
export function saveSpreadUid(spreadUid = '') {
  const normalized = normalizeSpreadUid(spreadUid)
  if (!normalized) {
    return ''
  }

  uni.setStorageSync(SPREAD_STORAGE_KEY, normalized)
  return normalized
}

/**
 * 读取本地保存的分销上级 ID。
 *
 * @returns {string} 分销上级 ID。
 */
export function getStoredSpreadUid() {
  return normalizeSpreadUid(uni.getStorageSync(SPREAD_STORAGE_KEY))
}

/**
 * 清理本地保存的分销上级 ID。
 *
 * @returns {void}
 */
export function clearStoredSpreadUid() {
  uni.removeStorageSync(SPREAD_STORAGE_KEY)
}

/**
 * 从 App 生命周期参数中捕获分销上级 ID。
 *
 * 同时兼容 H5 查询参数、微信小程序普通 query、微信小程序码 scene 和 referrerInfo.extraData。
 *
 * @param {Object} launchOptions App onLaunch/onShow 参数。
 * @returns {string} 捕获到的分销上级 ID。
 */
export function captureSpreadFromLaunchOptions(launchOptions = {}) {
  const options = launchOptions && typeof launchOptions === 'object' ? launchOptions : {}
  const query = options.query && typeof options.query === 'object' ? options.query : {}
  const referrerInfo = options.referrerInfo && typeof options.referrerInfo === 'object' ? options.referrerInfo : {}
  const extraData = referrerInfo.extraData && typeof referrerInfo.extraData === 'object' ? referrerInfo.extraData : {}

  let spreadUid = extractSpreadUid(query)
  if (!spreadUid) {
    spreadUid = extractSpreadUidFromScene(query.scene)
  }
  if (!spreadUid) {
    spreadUid = extractSpreadUid(extraData)
  }
  if (!spreadUid) {
    spreadUid = extractSpreadUid(parseQueryFromUrl(options.path || ''))
  }

  // #ifdef H5
  if (!spreadUid && typeof window !== 'undefined' && window.location) {
    spreadUid = extractSpreadUid(parseQueryString(window.location.search || ''))
  }
  // #endif

  return saveSpreadUid(spreadUid)
}

/**
 * 给登录或授权请求附加分销上级 ID。
 *
 * @param {Object} payload 原请求参数。
 * @returns {Object} 附带 spread_uid 的请求参数。
 */
export function appendSpreadToPayload(payload = {}) {
  const spreadUid = getStoredSpreadUid()
  if (!spreadUid) {
    return payload
  }

  return {
    ...payload,
    spread_uid: spreadUid
  }
}
