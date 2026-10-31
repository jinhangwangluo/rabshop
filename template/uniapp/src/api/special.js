import { get, post } from './request'
import { AUTH_MODES } from '@/utils/auth'

const SPECIAL_OPTIONAL_AUTH = {
  authMode: AUTH_MODES.OPTIONAL
}

const SPECIAL_REQUIRED_AUTH = {
  authMode: AUTH_MODES.REQUIRED
}

/**
 * 获取移动端专题列表。
 *
 * 使用场景：专题列表页进入、切换顶部分类、修改价格筛选或触底加载更多时调用；
 * 匿名用户可访问，登录用户会随请求自动带上登录态。
 *
 * @param {Object} params 查询参数。
 * @param {number} params.page 页码。
 * @param {number} params.limit 每页条数。
 * @param {string} params.keyword 关键词，按标题/关键词/描述搜索。
 * @param {number|string} params.category_id 专题分类 ID。
 * @param {number|string} params.merchant_id 商户 ID；传入后只返回该商户发布的专题。
 * @param {number|string} params.min_price 最低价格。
 * @param {number|string} params.max_price 最高价格。
 * @param {'default'|'sales'|'latest'} params.sort 排序方式。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data` 中包含 `list`、`total`、`page`、`limit`、`totalPage`。
 */
export function getSpecialList(params = {}, options = {}) {
  return get('special/list', params, {
    ...SPECIAL_OPTIONAL_AUTH,
    ...options
  })
}

/**
 * 获取移动端专题详情。
 *
 * 使用场景：专题详情页进入时获取专题主信息、内容列表、收藏状态与购买状态；
 * 匿名用户返回公共详情，登录用户额外返回已购/收藏状态。
 *
 * @param {Object} params 查询参数。
 * @param {number|string} params.id 专题 ID。
 * @param {number|string} params.special_id 专题 ID，兼容后端入参。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data` 中包含专题详情、`contents`、`is_purchased`、`can_buy`、`access_state`。
 */
export function getSpecialDetail(params = {}, options = {}) {
  return get('special/detail', params, {
    ...SPECIAL_OPTIONAL_AUTH,
    ...options
  })
}

/**
 * 获取专题下的音频播放列表。
 *
 * 使用场景：音频详情页从专题进入且专题音频数量达到 2 个及以上时，打开音乐列表半屏弹窗；
 * 匿名用户可访问，登录用户会随请求自动带上登录态。
 *
 * @param {Object} params 查询参数。
 * @param {number|string} params.special_id 专题 ID。
 * @param {number|string} params.current_id 当前音频内容 ID。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data` 返回 special_id、title、total、current_id、list。
 */
export function getSpecialAudioList(params = {}, options = {}) {
  return get('special/audio/list', params, {
    ...SPECIAL_OPTIONAL_AUTH,
    ...options
  })
}

/**
 * 获取猜你喜欢专题推荐列表。
 *
 * 使用场景：专题详情页、内容详情页中的猜你喜欢组件初始化时调用；
 * 匿名用户返回公共热门专题推荐，登录用户返回个性化推荐。
 *
 * @param {Object} params 查询参数。
 * @param {number} params.limit 推荐数量。
 * @param {number|string} params.exclude_id 需要排除的专题 ID。
 * @param {Array<number>|string} params.exclude_ids 需要批量排除的专题 ID。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data.list` 中返回猜你喜欢组件直渲染字段。
 */
export function getSpecialRecommendList(params = {}, options = {}) {
  return get('special/recommend', params, {
    ...SPECIAL_OPTIONAL_AUTH,
    ...options
  })
}

/**
 * 获取专题收藏状态。
 *
 * 使用场景：专题详情页进入时查询当前用户是否已收藏该专题；
 * 匿名状态下返回未收藏。
 *
 * @param {Object} params 查询参数。
 * @param {number|string} params.special_id 专题 ID。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data.is_favorite` 表示是否已收藏。
 */
export function getSpecialFavoriteStatus(params = {}, options = {}) {
  return get('special/favorite/status', params, {
    ...SPECIAL_OPTIONAL_AUTH,
    ...options
  })
}

/**
 * 切换专题收藏状态。
 *
 * 使用场景：专题详情页点击收藏按钮时调用；需要登录。
 *
 * @param {Object} data 请求参数。
 * @param {number|string} data.special_id 专题 ID。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data.is_favorite` 返回切换后的收藏状态。
 */
export function toggleSpecialFavorite(data = {}, options = {}) {
  return post('special/favorite/toggle', data, {
    ...SPECIAL_REQUIRED_AUTH,
    ...options
  })
}

/**
 * 记录专题预览行为。
 *
 * 使用场景：专题详情页进入时上报预览行为；
 * 匿名状态下仅透传成功响应，不写入用户预览画像。
 *
 * @param {Object} data 请求参数。
 * @param {number|string} data.special_id 专题 ID。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data.preview_count` 返回当前用户对该专题的累计预览次数。
 */
export function recordSpecialPreview(data = {}, options = {}) {
  return post('special/preview/record', data, {
    ...SPECIAL_OPTIONAL_AUTH,
    ...options
  })
}

/**
 * 获取专题关联商品列表。
 *
 * 使用场景：专题详情页「关联商品」区块初始化及触底加载更多时调用；
 * 匿名用户可访问，登录后返回会员价相关字段。
 *
 * @param {Object} params 查询参数。
 * @param {number|string} params.id 专题 ID。
 * @param {number} params.page 页码，默认 1。
 * @param {number} params.limit 每页数量，最大 20。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data` 返回 list、total、page、limit、totalPage、has_more。
 */
export function getSpecialProducts(params = {}, options = {}) {
  return get('special/products', params, {
    ...SPECIAL_OPTIONAL_AUTH,
    ...options
  })
}
