import request from '@/utils/request'

const PREFIX = '/setting/express_company'

/**
 * 获取快递公司列表
 * @param {Object} params - 查询参数：keyword, status, page, limit
 */
export function getExpressCompanyList(params) {
  return request({
    url: PREFIX,
    method: 'get',
    params
  })
}

/**
 * 获取启用的快递公司选项
 * @param {Object} params - 查询参数：keyword
 */
export function getExpressCompanyOptions(params = {}) {
  return request({
    url: `${PREFIX}/options`,
    method: 'get',
    params
  })
}

/**
 * 新增快递公司
 * @param {Object} data - { name, code, is_common, sort, status }
 */
export function addExpressCompany(data) {
  return request({
    url: PREFIX,
    method: 'post',
    data
  })
}

/**
 * 修改快递公司
 * @param {number|string} id
 * @param {Object} data - { name, code, is_common, sort, status }
 */
export function updateExpressCompany(id, data) {
  return request({
    url: `${PREFIX}/${id}`,
    method: 'put',
    data
  })
}

/**
 * 删除快递公司
 * @param {number|string} id
 */
export function deleteExpressCompany(id) {
  return request({
    url: `${PREFIX}/${id}`,
    method: 'delete'
  })
}

/**
 * 启用或禁用快递公司
 * @param {number|string} id
 * @param {number} status - 1 启用，0 禁用
 */
export function toggleExpressCompanyStatus(id, status) {
  return request({
    url: `${PREFIX}/${id}/show`,
    method: 'get',
    params: { status }
  })
}

/**
 * 获取物流查询配置状态
 */
export function getExpressQueryConfig() {
  return request({
    url: `${PREFIX}/query_config`,
    method: 'get'
  })
}

/**
 * 保存物流查询 AppCode
 * @param {Object} data - { appcode }
 */
export function saveExpressQueryConfig(data) {
  return request({
    url: `${PREFIX}/query_config`,
    method: 'post',
    data
  })
}
