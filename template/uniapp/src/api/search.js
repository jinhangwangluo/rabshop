import { get, post } from './request'
import { AUTH_MODES } from '@/utils/auth'

const SEARCH_HISTORY_KEY = 'mobile_search_history'

const SEARCH_OPTIONAL_AUTH = {
  authMode: AUTH_MODES.OPTIONAL
}

const NATIVE_SEARCH_KEYWORD = 'function search() { [native code] }'

function normalizeKeyword(keyword = '') {
  const normalized = String(keyword || '').trim().replace(/\s+/g, ' ').slice(0, 50)
  return normalized === NATIVE_SEARCH_KEYWORD ? '' : normalized
}

function normalizeHistoryItem(item = {}) {
  if (typeof item === 'string' || typeof item === 'number') {
    const keyword = normalizeKeyword(item)
    return keyword ? { search: keyword, keyword } : null
  }

  if (!item || typeof item !== 'object') {
    return null
  }

  const keyword = normalizeKeyword(item.search || item.keyword || item.name || item.title || '')
  return keyword ? { search: keyword, keyword } : null
}

/**
 * 读取匿名用户本地最近搜索。
 *
 * 使用场景：未登录用户进入搜索页时展示最近搜索；不发起网络请求。
 *
 * @returns {Array<Object>} 最近搜索数组，每项包含 search/keyword。
 */
export function getLocalSearchHistory() {
  const history = uni.getStorageSync(SEARCH_HISTORY_KEY)
  if (!Array.isArray(history)) {
    return []
  }

  return history
    .map((item) => normalizeHistoryItem(item))
    .filter(Boolean)
    .slice(0, 10)
}

/**
 * 保存匿名用户本地搜索关键词。
 *
 * 使用场景：未登录用户搜索成功后更新本地最近搜索；不发起网络请求。
 *
 * @param {string} keyword 搜索关键词。
 * @returns {Array<Object>} 更新后的最近搜索数组。
 */
export function saveLocalSearchHistory(keyword = '') {
  const normalized = normalizeKeyword(keyword)
  if (!normalized) {
    return getLocalSearchHistory()
  }

  const nextHistory = [
    { search: normalized, keyword: normalized },
    ...getLocalSearchHistory().filter((item) => normalizeKeyword(item.search) !== normalized)
  ].slice(0, 10)

  uni.setStorageSync(SEARCH_HISTORY_KEY, nextHistory)
  return nextHistory
}

/**
 * 清空匿名用户本地最近搜索。
 *
 * 使用场景：未登录用户点击清空搜索历史；不发起网络请求。
 *
 * @returns {Array} 空历史数组。
 */
export function clearLocalSearchHistory() {
  uni.setStorageSync(SEARCH_HISTORY_KEY, [])
  return []
}

/**
 * 获取搜索页默认态数据。
 *
 * 使用场景：搜索页进入时获取最近搜索、热门搜索、发现内容；匿名可访问，登录时返回服务端最近搜索。
 *
 * @param {Object} params 查询参数。
 * @param {number|string} params.limit 热门搜索与发现内容数量。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} data 返回 history、hot_keywords、discover，hot_keywords 每项包含 keyword/search_count。
 */
export function getSearchMeta(params = {}, options = {}) {
  return get('search/meta', params, {
    ...SEARCH_OPTIONAL_AUTH,
    ...options
  })
}

/**
 * 执行移动端统一搜索。
 *
 * 使用场景：搜索页提交关键词后获取商品、专题、内容、商户和全部聚合结果；匿名可访问。
 *
 * @param {Object} params 查询参数。
 * @param {string} params.keyword 搜索关键词。
 * @param {'all'|'goods'|'product'|'special'|'content'|'merchant'} params.type 结果类型，兼容旧入口 product。
 * @param {number|string} params.page 页码。
 * @param {number|string} params.limit 每页数量。
 * @param {string} params.sort 排序方式。
 * @param {number|string} params.category_id 商品分类 ID。
 * @param {number|string} params.brand_id 品牌 ID。
 * @param {number|string} params.price_min 最低价格。
 * @param {number|string} params.price_max 最高价格。
 * @param {string} params.merchant_type 商户类型：`self`、`non_self` 或空。
 * @param {number|string} params.is_free_shipping 是否包邮，1 表示包邮。
 * @param {Array<Object>|string} params.param_filters 商品参数筛选条件。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} data 返回 summary 与 results。
 */
export function searchContentResources(params = {}, options = {}) {
  return get('search/result', params, {
    ...SEARCH_OPTIONAL_AUTH,
    ...options
  })
}

/**
 * 记录搜索关键词。
 *
 * 使用场景：搜索成功后记录关键词热度；登录用户同时写入服务端最近搜索，匿名用户由页面保留本地最近搜索。
 *
 * @param {Object} data 请求参数。
 * @param {string} data.keyword 搜索关键词。
 * @param {number|string} data.result_count 搜索结果数量。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} data 返回 history 与 hot_keywords，hot_keywords 每项包含 keyword/search_count。
 */
export function recordSearchKeyword(data = {}, options = {}) {
  return post('search/record', data, {
    ...SEARCH_OPTIONAL_AUTH,
    ...options
  })
}

/**
 * 清空服务端搜索历史。
 *
 * 使用场景：搜索页点击清空最近搜索；匿名状态下后端返回成功，页面同时清空本地缓存。
 *
 * @param {Object} data 请求参数，当前无需传参。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} data 返回空 history。
 */
export function clearSearchHistory(data = {}, options = {}) {
  return post('search/history/clear', data, {
    ...SEARCH_OPTIONAL_AUTH,
    ...options
  })
}
