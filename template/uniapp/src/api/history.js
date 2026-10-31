import { get, post } from './request'
import { AUTH_MODES } from '@/utils/auth'

const HISTORY_REQUIRED_AUTH = {
  authMode: AUTH_MODES.REQUIRED
}

/**
 * 获取当前用户浏览足迹列表。
 *
 * 使用场景：我的足迹页进入、下拉刷新或触底加载更多时调用；需要登录。
 *
 * @param {Object} params 查询参数。
 * @param {number} params.page 页码。
 * @param {number} params.limit 每页条数。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data` 中包含 `list`、`total`、`page`、`limit`、`totalPage`。
 */
export function getBrowseHistoryList(params = {}, options = {}) {
  return get('user/browse/list', params, {
    ...HISTORY_REQUIRED_AUTH,
    ...options
  })
}

/**
 * 删除当前用户选中的浏览足迹。
 *
 * 使用场景：我的足迹页管理模式下勾选记录后点击“清除选中”；需要登录。
 *
 * @param {Object} data 请求参数。
 * @param {Array<number>|string} data.ids 浏览记录 ID 集合。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data.deleted` 返回实际删除数量。
 */
export function deleteBrowseHistory(data = {}, options = {}) {
  return post('user/browse/delete', data, {
    ...HISTORY_REQUIRED_AUTH,
    ...options
  })
}

/**
 * 记录当前用户浏览行为。
 *
 * 使用场景：内容、专题或商品详情页进入时上报浏览行为；需要登录。
 *
 * @param {Object} data 请求参数。
 * @param {'content'|'special'|'product'} data.target_type 浏览对象类型。
 * @param {number|string} data.target_id 浏览对象 ID。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data.browse_count` 返回当前用户对该对象的累计浏览次数。
 */
export function recordBrowseHistory(data = {}, options = {}) {
  return post('user/browse/record', data, {
    ...HISTORY_REQUIRED_AUTH,
    ...options
  })
}
