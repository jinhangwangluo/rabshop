import { get, post } from './request'
import { AUTH_MODES } from '@/utils/auth'

const CONTENT_OPTIONAL_AUTH = {
  authMode: AUTH_MODES.OPTIONAL
}

/**
 * 获取移动端内容列表。
 *
 * 使用场景：内容分类页、内容列表页、DIY 更多内容页；
 * 匿名用户可访问，登录用户会随请求自动带上登录态。
 *
 * @param {Object} params 查询参数。
 * @param {number|string} params.page 页码。
 * @param {number|string} params.limit 每页数量。
 * @param {string} params.keyword 搜索关键词。
 * @param {number|string} params.category_id 内容分类 ID。
 * @param {number|string} params.content_type 内容类型：1-图文 2-文字 3-小说 4-图片 5-音频 6-视频。
 * @param {number|string} params.merchant_id 商户 ID；传入后只返回该商户发布的内容。
 * @param {number|string} params.min_price 最低价格。
 * @param {number|string} params.max_price 最高价格。
 * @param {string} params.sort 排序：default/hot/view/price_asc/price_desc。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} data 返回分页列表、总数与 has_more。
 */
export function getContentList(params = {}, options = {}) {
  return get('content/list', params, {
    ...CONTENT_OPTIONAL_AUTH,
    ...options
  })
}

/**
 * 获取移动端内容分类树。
 *
 * 使用场景：内容分类页或内容列表页筛选文章分类；匿名用户可访问。
 *
 * @param {Object} params 查询参数，当前无需传参。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} data 返回内容分类树数组。
 */
export function getContentCategoryTree(params = {}, options = {}) {
  return get('content/category/tree', params, {
    ...CONTENT_OPTIONAL_AUTH,
    ...options
  })
}

/**
 * 获取内容详情（图文/图片类型）。
 *
 * 使用场景：内容详情页进入时获取内容主信息、购买状态与评论总数；
 * 匿名用户返回公共详情，登录用户额外返回已购状态。
 *
 * @param {Object} params 查询参数。
 * @param {number|string} params.id 内容 ID。
 * @param {number|string} params.special_id 专题 ID；从专题进入小说详情时传入，用于获取专题章节上下文。
 * @param {number|string} params.chapter_page 专题章节页码，默认 1。
 * @param {number|string} params.chapter_limit 专题章节每页数量，默认 20，最大 20。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} data 返回后端原始字段（snake_case）。
 */
export function getContentDetail(params = {}, options = {}) {
  return get('content/detail', params, {
    ...CONTENT_OPTIONAL_AUTH,
    ...options
  })
}

/**
 * 获取内容收藏状态。
 *
 * 使用场景：内容详情页进入时查询当前用户是否已收藏该内容；
 * 匿名状态下返回未收藏。
 *
 * @param {Object} params 查询参数。
 * @param {number|string} params.article_id 内容 ID。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data.is_favorite` 表示是否已收藏。
 */
export function getContentFavoriteStatus(params = {}, options = {}) {
  return get('content/favorite/status', params, {
    ...CONTENT_OPTIONAL_AUTH,
    ...options
  })
}

/**
 * 切换内容收藏状态。
 *
 * 使用场景：音频/视频/图文内容详情页点击收藏按钮；需要登录。
 *
 * @param {Object} data 请求参数。
 * @param {number|string} data.article_id 内容 ID。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data.is_favorite` 返回切换后的收藏状态。
 */
export function toggleContentFavorite(data = {}, options = {}) {
  return post('content/favorite/toggle', data, {
    authMode: AUTH_MODES.REQUIRED,
    ...options
  })
}

/**
 * 切换内容点赞状态。
 *
 * 使用场景：音频/视频/图文内容详情页点击点赞按钮；需要登录。
 *
 * @param {Object} data 请求参数。
 * @param {number|string} data.article_id 内容 ID。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 data 返回 is_liked 与 like_count。
 */
export function toggleContentLike(data = {}, options = {}) {
  return post('content/like/toggle', data, {
    authMode: AUTH_MODES.REQUIRED,
    ...options
  })
}
