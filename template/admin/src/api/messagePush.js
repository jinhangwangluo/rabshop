import request from '@/utils/request'

/**
 * 获取消息推送事件、渠道和变量选项。
 * @returns {AxiosPromise}
 */
export function getMessagePushEvents() {
  return request({
    url: '/setting/message_push/events',
    method: 'get'
  })
}

/**
 * 保存自定义消息推送事件。
 * @param {Object} data 自定义事件数据
 * @returns {AxiosPromise}
 */
export function saveMessagePushEvent(data) {
  return request({
    url: '/setting/message_push/events',
    method: 'post',
    data
  })
}

/**
 * 获取消息推送配置列表。
 * @returns {AxiosPromise}
 */
export function getMessagePushList() {
  return request({
    url: '/setting/message_push/list',
    method: 'get'
  })
}

/**
 * 获取消息推送事件详情。
 * @param {string} eventKey 事件标识
 * @returns {AxiosPromise}
 */
export function getMessagePushDetail(eventKey) {
  return request({
    url: `/setting/message_push/detail/${eventKey}`,
    method: 'get'
  })
}

/**
 * 保存消息推送渠道配置。
 * @param {Object} data 配置数据
 * @returns {AxiosPromise}
 */
export function saveMessagePushConfig(data) {
  return request({
    url: '/setting/message_push/save',
    method: 'post',
    data
  })
}

/**
 * 切换消息推送渠道开关。
 * @param {number} id 配置ID
 * @param {number} status 开关状态
 * @returns {AxiosPromise}
 */
export function setMessagePushStatus(id, status) {
  return request({
    url: `/setting/message_push/channel/${id}/status/${status}`,
    method: 'put'
  })
}

/**
 * 按渠道同步全部微信模板。
 * @param {string} channel 渠道：official_account / miniapp
 * @returns {AxiosPromise}
 */
export function syncMessagePushChannel(channel) {
  return request({
    url: `/setting/message_push/sync/${channel}`,
    method: 'post'
  })
}

/**
 * 测试发送消息推送。
 * @param {number} id 配置ID
 * @param {Object} data 测试数据
 * @returns {AxiosPromise}
 */
export function testMessagePushTemplate(id, data) {
  return request({
    url: `/setting/message_push/channel/${id}/test`,
    method: 'post',
    data
  })
}

/**
 * 获取消息推送发送日志。
 * @param {Object} params 查询参数
 * @returns {AxiosPromise}
 */
export function getMessagePushLogs(params) {
  return request({
    url: '/setting/message_push/logs',
    method: 'get',
    params
  })
}
