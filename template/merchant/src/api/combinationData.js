import request from '@/utils/request'

/**
 * 获取当前商户组合数据分类列表。
 * @param {Object} params 查询参数
 * @returns {AxiosPromise} 分类树列表
 */
export function getCombinationDataCategoryList(params) {
  return request({ url: '/setting/combination_data_category', method: 'get', params })
}

/**
 * 获取当前商户组合数据分类详情。
 * @param {Number} id 分类ID
 * @returns {AxiosPromise} 分类详情
 */
export function getCombinationDataCategory(id) {
  return request({ url: `/setting/combination_data_category/${id}`, method: 'get' })
}

/**
 * 新增当前商户组合数据分类。
 * @param {Object} data 分类表单数据
 * @returns {AxiosPromise} 保存结果
 */
export function saveCombinationDataCategory(data) {
  return request({ url: '/setting/combination_data_category', method: 'post', data })
}

/**
 * 更新当前商户组合数据分类。
 * @param {Number} id 分类ID
 * @param {Object} data 分类表单数据
 * @returns {AxiosPromise} 更新结果
 */
export function updateCombinationDataCategory(id, data) {
  return request({ url: `/setting/combination_data_category/${id}`, method: 'put', data })
}

/**
 * 删除当前商户组合数据分类。
 * @param {Number} id 分类ID
 * @returns {AxiosPromise} 删除结果
 */
export function deleteCombinationDataCategory(id) {
  return request({ url: `/setting/combination_data_category/${id}`, method: 'delete' })
}

/**
 * 获取当前商户组合数据配置分页列表。
 * @param {Object} params 查询参数，支持 category_id/status/keyword/page/limit
 * @returns {AxiosPromise} 配置分页数据
 */
export function getCombinationDataConfigList(params) {
  return request({ url: '/setting/combination_data_config', method: 'get', params })
}

/**
 * 获取当前商户组合数据配置详情。
 * @param {Number} id 配置ID
 * @returns {AxiosPromise} 配置详情，包含 content/field_keys/fields
 */
export function getCombinationDataConfig(id) {
  return request({ url: `/setting/combination_data_config/${id}`, method: 'get' })
}

/**
 * 新增当前商户组合数据配置。
 * @param {Object} data 配置表单数据
 * @returns {AxiosPromise} 保存结果
 */
export function saveCombinationDataConfig(data) {
  return request({ url: '/setting/combination_data_config', method: 'post', data })
}

/**
 * 更新当前商户组合数据配置。
 * @param {Number} id 配置ID
 * @param {Object} data 配置表单数据
 * @returns {AxiosPromise} 更新结果
 */
export function updateCombinationDataConfig(id, data) {
  return request({ url: `/setting/combination_data_config/${id}`, method: 'put', data })
}

/**
 * 删除当前商户组合数据配置。
 * @param {Number} id 配置ID
 * @returns {AxiosPromise} 删除结果
 */
export function deleteCombinationDataConfig(id) {
  return request({ url: `/setting/combination_data_config/${id}`, method: 'delete' })
}

/**
 * 修改当前商户组合数据配置状态。
 * @param {Number} id 配置ID
 * @param {Number} status 状态值：0 禁用，1 启用
 * @returns {AxiosPromise} 状态修改结果
 */
export function changeCombinationDataConfigStatus(id, status) {
  return request({ url: `/setting/combination_data_config/${id}/status/${status}`, method: 'get' })
}

/**
 * 获取指定配置的动态表单 schema 和列表列定义。
 * @param {Number} configId 配置ID
 * @returns {AxiosPromise} schema 数据，包含 content/columns
 */
export function getCombinationDataSchema(configId) {
  return request({ url: `/setting/combination_data/schema/${configId}`, method: 'get' })
}

/**
 * 获取当前商户组合数据分页列表。
 * @param {Object} params 查询参数，支持 category_id/config_id/data_type/status/page/limit
 * @returns {AxiosPromise} 数据分页列表和动态列
 */
export function getCombinationDataList(params) {
  return request({ url: '/setting/combination_data', method: 'get', params })
}

/**
 * 获取当前商户组合数据详情。
 * @param {Number} id 数据ID
 * @returns {AxiosPromise} 数据详情，包含 form_data 和 config
 */
export function getCombinationData(id) {
  return request({ url: `/setting/combination_data/${id}`, method: 'get' })
}

/**
 * 新增当前商户组合数据。
 * @param {Object} data 数据表单，包含 category_id/config_id/sort/status/form_data
 * @returns {AxiosPromise} 保存结果
 */
export function saveCombinationData(data) {
  return request({ url: '/setting/combination_data', method: 'post', data })
}

/**
 * 更新当前商户组合数据。
 * @param {Number} id 数据ID
 * @param {Object} data 数据表单，包含 category_id/config_id/sort/status/form_data
 * @returns {AxiosPromise} 更新结果
 */
export function updateCombinationData(id, data) {
  return request({ url: `/setting/combination_data/${id}`, method: 'put', data })
}

/**
 * 删除当前商户组合数据。
 * @param {Number} id 数据ID
 * @returns {AxiosPromise} 删除结果
 */
export function deleteCombinationData(id) {
  return request({ url: `/setting/combination_data/${id}`, method: 'delete' })
}
