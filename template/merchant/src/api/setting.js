import request from '@/utils/request'

/**
 * 获取单个配置模板详情
 * @param id
 * @returns {*}
 */
export function getConfigTemplateFindApi(id) {
  return request({
    url: '/setting/config_template/' + id,
    method: 'get'
  })
}
