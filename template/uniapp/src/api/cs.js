import { get, post, put } from './request'
import { AUTH_MODES } from '@/utils/auth'

/**
 * 客服系统接口封装。
 * 所有接口均需登录态，authMode 使用 REQUIRED。
 */

/**
 * 创建或获取客服会话。
 *
 * 使用方式：用户进入客服页面时调用，自动创建新会话或返回已有的进行中会话。
 *
 * @param {number} merchantId 商户 ID，默认 0。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data` 中包含 `session_id`、`status`、`service_type`。
 */
export function createCsSession(merchantId = 0, options = {}) {
  return post('cs/session', { merchant_id: merchantId }, {
    authMode: AUTH_MODES.REQUIRED,
    ...options
  })
}

/**
 * 查询客服会话状态。
 *
 * 使用方式：查询指定会话的当前状态、客服信息等。
 *
 * @param {string} sessionId 会话 ID。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data` 中包含会话详情和客服信息。
 */
export function getCsSessionStatus(sessionId, options = {}) {
  return get('cs/session/status', { session_id: sessionId }, {
    authMode: AUTH_MODES.REQUIRED,
    ...options
  })
}

/**
 * 发送客服消息（HTTP 回退）。
 *
 * 使用方式：WebSocket 发送失败时的降级路径，通过 HTTP 发送消息。
 *
 * @param {Object} data 请求参数。
 * @param {string} data.session_id 会话 ID。
 * @param {string} data.content 消息内容。
 * @param {string} [data.message_type] 消息类型，默认 'text'。
 * @param {string} [data.msg_uuid] 消息去重 UUID。
 * @param {Object} [data.extra] 历史消息扩展信息，可包含业务场景、转人工意图和订单快照。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data` 包含发送结果。
 */
export function sendCsMessage(data = {}, options = {}) {
  return post('cs/message', data, {
    authMode: AUTH_MODES.REQUIRED,
    ...options
  })
}

/**
 * 获取客服会话历史消息。
 *
 * 使用方式：进入客服页面时拉取首屏历史消息。
 *
 * @param {Object} params 查询参数。
 * @param {string} params.session_id 会话 ID。
 * @param {number} [params.page] 页码，默认 1。
 * @param {number} [params.limit] 每页条数。
 * @param {number} [params.after_id] 只拉取指定消息 ID 之后的新消息，用于断线补偿。
 * @param {number} [params.latest] 是否获取最近一页消息，1 是，0 否。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data` 中包含 `list`、`total`、`page`、`limit`。
 */
export function getCsHistory(params = {}, options = {}) {
  return get('cs/message/history', params, {
    authMode: AUTH_MODES.REQUIRED,
    ...options
  })
}

/**
 * 标记会话消息已读。
 *
 * 使用方式：进入客服页面时调用，清除客服侧的未读计数。
 *
 * @param {string} sessionId 会话 ID。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>}
 */
export function markCsSessionRead(sessionId, options = {}) {
  return put('cs/message/mark_session_read', { session_id: sessionId }, {
    authMode: AUTH_MODES.REQUIRED,
    ...options
  })
}

/**
 * 获取客服 WebSocket 连接令牌。
 *
 * 使用方式：建立客服 WebSocket 连接前调用，获取 token 用于认证。
 *
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data` 中包含 `token` 和 `user_id`。
 */
export function getCsWsToken(options = {}) {
  return get('cs/ws_token', {}, {
    authMode: AUTH_MODES.REQUIRED,
    ...options
  })
}
