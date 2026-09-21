import request from '@/utils/request'

/**
 * 获取平台后台消息分类与统计。
 * 登录要求：需要平台端登录态。
 * @returns {AxiosPromise} 返回 data.list，包含 value、label、icon、unread_count、total。
 */
export function getBackendMessageTypes() {
  return request({ url: '/backend_message/types', method: 'get' })
}

/**
 * 获取平台后台消息分页列表。
 * 登录要求：需要平台端登录态。
 * @param {Object} params 查询参数。
 * @param {number=} params.page 页码。
 * @param {number=} params.limit 每页数量。
 * @param {string=} params.message_group 消息分组：order、finance、user、audit，空字符串为全部。
 * @param {number|string=} params.read_status 已读状态：0=未读，1=已读，空字符串为全部。
 * @returns {AxiosPromise} 返回 data.list、total、page、limit、totalPage。
 */
export function getBackendMessageList(params) {
  return request({ url: '/backend_message/list', method: 'get', params })
}

/**
 * 获取平台后台消息未读总数。
 * 登录要求：需要平台端登录态。
 * @returns {AxiosPromise} 返回 data.count。
 */
export function getBackendMessageUnreadCount() {
  return request({ url: '/backend_message/unread_count', method: 'get' })
}

/**
 * 标记平台后台消息为已读。
 * 登录要求：需要平台端登录态。
 * @param {Object} data 标记参数。
 * @param {Array<number>} data.ids 消息 ID 列表。
 * @returns {AxiosPromise} 返回 data.updated 更新数量。
 */
export function readBackendMessage(data) {
  return request({ url: '/backend_message/read', method: 'put', data })
}

/**
 * 标记平台后台消息全部已读。
 * 登录要求：需要平台端登录态。
 * @param {Object} data 标记参数。
 * @param {string=} data.message_group 消息分组，空字符串为全部。
 * @returns {AxiosPromise} 返回 data.updated 更新数量。
 */
export function readAllBackendMessage(data) {
  return request({ url: '/backend_message/read_all', method: 'put', data })
}

/**
 * 一键软清理平台后台消息。
 * 登录要求：需要平台端登录态。
 * @param {Object} params 清理参数。
 * @param {string=} params.message_group 消息分组，空字符串为全部。
 * @returns {AxiosPromise} 返回 data.updated 清理数量。
 */
export function clearBackendMessage(params) {
  return request({ url: '/backend_message/clear', method: 'delete', params })
}

/**
 * 获取平台后台消息 WebSocket 令牌。
 * 登录要求：需要平台端登录态。
 * @returns {AxiosPromise} 返回 data.token 与 data.ws_url。
 */
export function getBackendMessageWsToken() {
  return request({ url: '/backend_message/ws_token', method: 'get' })
}
