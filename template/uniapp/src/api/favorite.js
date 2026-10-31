import { get, post } from './request'
import { AUTH_MODES } from '@/utils/auth'

const FAVORITE_REQUIRED_AUTH = {
  authMode: AUTH_MODES.REQUIRED
}

/**
 * 获取当前用户收藏列表。
 *
 * 使用场景：我的收藏页进入、切换内容/专题 tab、下拉刷新或触底加载时调用；需要登录。
 *
 * @param {Object} params 查询参数。
 * @param {string} params.type 收藏类型，仅支持 content/special。
 * @param {number} params.page 页码。
 * @param {number} params.limit 每页条数。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data` 中包含 `list`、`total`、`page`、`limit`、`totalPage`。
 */
export function getFavoriteList(params = {}, options = {}) {
  return get('user/favorite/list', params, {
    ...FAVORITE_REQUIRED_AUTH,
    ...options
  })
}

/**
 * 取消当前用户的指定收藏。
 *
 * 使用场景：我的收藏页点击卡片收藏按钮并确认移除时调用；需要登录。
 *
 * @param {Object} data 请求参数。
 * @param {string} data.type 收藏类型，仅支持 content/special。
 * @param {number|string} data.target_id 收藏对象 ID。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data.is_favorite` 固定为 false。
 */
export function cancelFavorite(data = {}, options = {}) {
  return post('user/favorite/cancel', data, {
    ...FAVORITE_REQUIRED_AUTH,
    ...options
  })
}
