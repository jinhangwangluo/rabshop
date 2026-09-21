import { get, post } from './request'
import { AUTH_MODES } from '@/utils/auth'

/**
 * 用户相关接口封装。
 * 使用方式示例：
 * - 获取用户信息：`getUserInfo()`
 * - 更新用户信息：`updateUserInfo({ nickname, gender, avatar })`
 */

/**
 * 获取当前登录用户详细信息。
 *
 * 用于个人设置等需要展示/编辑用户资料的页面。
 *
 * @param {Object} params 查询参数，通常留空。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 data 包含用户信息对象（nickname、phone、gender、avatar 等）。
 */
export function getUserInfo(params = {}, options = {}) {
  return get('user/info', params, {
    authMode: AUTH_MODES.REQUIRED,
    ...options
  })
}

/**
 * 获取当前用户提现银行卡信息。
 *
 * @param {Object} params 查询参数，通常留空。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 data 返回银行卡信息。
 */
export function getBankCard(params = {}, options = {}) {
  return get('user/bank-card', params, {
    authMode: AUTH_MODES.REQUIRED,
    ...options
  })
}

/**
 * 保存当前用户提现银行卡信息。
 *
 * @param {Object} data 银行卡信息。
 * @param {string} data.holder_name 持卡人姓名。
 * @param {string} data.card_no 银行卡号。
 * @param {string} data.bank_name 开户银行。
 * @param {string} data.reserved_phone 银行预留手机号。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 data 返回保存后的银行卡信息。
 */
export function saveBankCard(data = {}, options = {}) {
  return post('user/bank-card', data, {
    authMode: AUTH_MODES.REQUIRED,
    ...options
  })
}

/**
 * 获取当前用户关注列表。
 *
 * 用于关注页按店铺或主播 Tab 拉取列表，同时返回平台关注信息；需要登录。
 *
 * @param {Object} params 查询参数。
 * @param {string} [params.type] 关注类型：all/shop/live。
 * @param {number|string} [params.limit] 每类返回条数。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 data 包含 platforms、shops、lives、platform_count、shop_count、live_count。
 */
export function getUserFollowList(params = {}, options = {}) {
  return get('user/follow/list', params, {
    authMode: AUTH_MODES.REQUIRED,
    ...options
  })
}

/**
 * 取消当前用户关注的店铺、主播或平台。
 *
 * 用于关注页点击取消关注后提交；需要登录。
 *
 * @param {Object} data 请求参数。
 * @param {string} data.type 关注类型：shop/live/platform。
 * @param {number|string} data.target_id 关注对象 ID，店铺为 merchant_id，主播为 anchor_id，平台固定为 0。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 data 返回取消后的关注状态。
 */
export function cancelUserFollow(data = {}, options = {}) {
  return post('user/follow/cancel', data, {
    authMode: AUTH_MODES.REQUIRED,
    ...options
  })
}

/**
 * 更新当前登录用户个人信息。
 *
 * @param {Object} data 提交的用户资料字段。
 * @param {string} [data.nickname] 昵称。
 * @param {number} [data.gender] 性别：1-男 2-女。
 * @param {string} [data.avatar] 头像 URL。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时返回更新后的用户信息。
 */
export function updateUserInfo(data = {}, options = {}) {
  return post('user/update', data, {
    authMode: AUTH_MODES.REQUIRED,
    ...options
  })
}
