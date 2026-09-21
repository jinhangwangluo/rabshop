import request from '@/utils/request'

/**
 * Get WeChat menus
 */
export function getMenus() {
  return request({
    url: '/wechat/menus/index',
    method: 'get'
  })
}

/**
 * Save WeChat menus
 * @param data
 */
export function saveMenus(data) {
  return request({
    url: '/wechat/menus/save',
    method: 'post',
    data
  })
}

/**
 * 获取关键字回复分页列表
 * @param {Object} params - { page, limit, key, type }
 */
export function getKeywordList(params) {
  return request({
    url: '/wechat/wechat_reply/index',
    method: 'get',
    params
  })
}

/**
 * 保存关键字回复
 * @param {Object} data - { key, type, status, data, sort }
 */
export function saveKeyword(data) {
  return request({
    url: '/wechat/wechat_reply/save_keyword',
    method: 'post',
    data
  })
}

/**
 * 按ID获取关键字回复详情
 * @param {Number} id
 */
export function getKeywordInfo(id) {
  return request({
    url: '/wechat/wechat_reply/info_keyword',
    method: 'get',
    params: { id }
  })
}

/**
 * 删除关键字回复
 * @param {Number} id
 */
export function deleteKeyword(id) {
  return request({
    url: '/wechat/wechat_reply/delete',
    method: 'get',
    params: { id }
  })
}

export function changeKeywordStatus(id, data) {
  return request({
    url: '/wechat/wechat_reply/status/' + id,
    method: 'put',
    data: data
  })
}

/**
 * 保存subscribe/default回复
 * @param {Object} data - { key, type, status, data, sort }
 */
export function saveReply(data) {
  return request({
    url: '/wechat/wechat_reply/save',
    method: 'post',
    data
  })
}

/**
 * 按key获取回复详情(subscribe/default)
 * @param {String} key
 */
export function getReplyInfo(key) {
  return request({
    url: '/wechat/wechat_reply/keyword',
    method: 'get',
    params: { key }
  })
}

/**
 * 上传回复图片
 * @param {FormData} data
 */
export function uploadReplyImg(data) {
  return request({
    url: '/wechat/wechat_reply/upload_img',
    method: 'post',
    data
  })
}

/**
 * 上传回复语音文件
 * @param {FormData} data
 */
export function uploadReplyFile(data) {
  return request({
    url: '/wechat/wechat_reply/upload_file',
    method: 'post',
    data
  })
}

/**
 * 获取回复类型选项
 */
export function getReplyTypes() {
  return request({
    url: '/wechat/wechat_reply/get_reply_types',
    method: 'get'
  })
}

/**
 * 获取匹配类型选项
 */
export function getMatchTypes() {
  return request({
    url: '/wechat/wechat_reply/get_match_types',
    method: 'get'
  })
}
