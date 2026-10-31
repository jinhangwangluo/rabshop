import request from '@/utils/request'

/**
 * 获取固定协议配置。
 * @returns {Promise<Object>} 后台统一响应，data.agreements 为协议类型和内容列表。
 */
export function getAgreementSchema() {
  return request({
    url: '/setting/agreement/schema',
    method: 'get'
  })
}

/**
 * 保存固定协议内容。
 * @param {Object} data 协议数据：type、content。
 * @returns {Promise<Object>} 后台统一响应。
 */
export function saveAgreementContent(data) {
  return request({
    url: '/setting/agreement/save_content',
    method: 'post',
    data
  })
}

/**
 * 获取协议列表。
 * @param {Object} params 查询参数：type、title、status、page、limit。
 * @returns {Promise<Object>} 后台统一响应。
 */
export function getAgreementList(params) {
  return request({
    url: '/setting/agreement',
    method: 'get',
    params
  })
}

/**
 * 新增协议。
 * @param {Object} data 协议数据：type、title、content、version、status、sort。
 * @returns {Promise<Object>} 后台统一响应。
 */
export function addAgreement(data) {
  return request({
    url: '/setting/agreement',
    method: 'post',
    data
  })
}

/**
 * 修改协议。
 * @param {Number} id 协议 ID。
 * @param {Object} data 协议数据：type、title、content、version、status、sort。
 * @returns {Promise<Object>} 后台统一响应。
 */
export function updateAgreement(id, data) {
  return request({
    url: `/setting/agreement/${id}`,
    method: 'put',
    data
  })
}

/**
 * 删除协议。
 * @param {Number} id 协议 ID。
 * @returns {Promise<Object>} 后台统一响应。
 */
export function deleteAgreement(id) {
  return request({
    url: `/setting/agreement/${id}`,
    method: 'delete'
  })
}
