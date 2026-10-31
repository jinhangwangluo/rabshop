import request from '@/utils/request'

/**
 * 获取关于我们富文本配置。
 * @returns {Promise<Object>} 后台统一响应，data 返回 id、content、update_time。
 */
export function getAboutSchema() {
  return request({
    url: '/setting/about/schema',
    method: 'get'
  })
}

/**
 * 保存关于我们富文本内容。
 * @param {Object} data 关于我们数据，包含 content。
 * @returns {Promise<Object>} 后台统一响应，data 返回保存后的内容。
 */
export function saveAboutContent(data) {
  return request({
    url: '/setting/about/save_content',
    method: 'post',
    data
  })
}
