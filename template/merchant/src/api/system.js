import request from '@/utils/request'

/**
 * 获取菜单
 * @param params
 * @return {AxiosPromise}
 */
export function getMenusListApi(params) {
  return request({
    url: '/setting/menu/list',
    method: 'get',
    params
  })
}

/**
 * 获取菜单规则地址
 * @param params
 * @returns {AxiosPromise}
 */
export function getMenusRuleApi(params) {
  return request({
    url: '/setting/menu/get_rule',
    method: 'get',
    params
  })
}

/**
 * 添加菜单
 * @param data
 * @return {AxiosPromise}
 */
export function addMenusApi(id, data) {
  return request({
    url: `/setting/menu/${id}`,
    method: 'post',
    data
  })
}

/**
 * 修改状态
 * @param id
 * @param status
 * @return {AxiosPromise}
 */
export function putStatusApi(id, status) {
  return request({
    url: `/setting/menu/${id}/status/${status}`,
    method: 'put'
  })
}

/**
 * 查看菜单详情
 * @param id
 * @return {AxiosPromise}
 */
export function getMenuDetailApi(id) {
  return request({
    url: `/setting/menu/${id}`,
    method: 'get'
  })
}

/**
 * 角色列表
 * @param params
 * @return {AxiosPromise}
 */
export function roleListApi(params) {
  return request({
    url: `/setting/role/list`,
    method: 'get',
    params
  })
}

/**
 * 获取启用角色下拉列表
 * @return {AxiosPromise}
 */
export function roleSelectApi() {
  return request({
    url: `/setting/role/select`,
    method: 'get'
  })
}

/**
 * 保存修改
 * @param id
 * @param status
 * @return {AxiosPromise}
 */
export function roleSetStatusApi(id, status) {
  return request({
    url: `/setting/role/status/${id}/${status}`,
    method: 'put'
  })
}

/**
 * 查看详情
 * @param id
 * @return {AxiosPromise}
 */
export function roleInfoApi(id) {
  return request({
    url: `/setting/role/detail/${id}`,
    method: 'get'
  })
}

/**
 * 修改或者创建
 * @param id
 * @param data
 * @return {AxiosPromise}
 */
export function roleCreateApi(id, data) {
  return request({
    url: `/setting/role/save/${id}`,
    method: 'post',
    data
  })
}

/** *
 * 获取管理员列表
 * @param params
 * @return {AxiosPromise}
 */
export function adminListApi(params) {
  return request({
    url: `/setting/admin/list`,
    method: 'get',
    params
  })
}

/**
 * 保存管理员
 * @param {number} id 管理员 ID，新增传 0
 * @param {Object} data 管理员表单数据
 * @return {AxiosPromise}
 */
export function adminSaveApi(id, data) {
  return request({
    url: `/setting/admin/save/${id || 0}`,
    method: 'post',
    data
  })
}

/**
 * 修改管理员状态
 * @param id
 * @param status
 * @return {AxiosPromise}
 */
export function setShowApi(id, status) {
  return request({
    url: `/setting/admin/status/${id}/${status}`,
    method: 'put'
  })
}

/**
 * 管理员
 * @return {AxiosPromise}
 */
export function searchAdminApi() {
  return request({
    url: `/setting/log/admin`,
    method: 'get'
  })
}

/**
 * 管理员日志
 * @param params
 * @return {AxiosPromise}
 */
export function systemListApi(params) {
  return request({
    url: `/setting/log/list`,
    method: 'get',
    params
  })
}

/**
 * 保存配置
 * @param data
 * @return {AxiosPromise}
 */
export function systemConfigSaveApi(data) {
  return request({
    url: `/setting/config`,
    method: 'post',
    data
  })
}

/**
 * 按字段获取配置。
 * @param {Array<string>} keys 前端需要的配置键列表
 * @return {AxiosPromise}
 */
export function systemGetConfigApi(keys = []) {
  return request({
    url: `/setting/config`,
    method: 'get',
    params: { keys }
  })
}

/**
 * 获取管理员
 * @return {AxiosPromise}
 */
export function getAdminUserInfoApi() {
  return request({
    url: `/setting/admin/info`,
    method: 'get'
  })
}

/**
 * 修改管理员
 * @param data
 * @return {AxiosPromise}
 */
export function putAdminUserApi(data) {
  return request({
    url: `/setting/admin/info`,
    method: 'put',
    data
  })
}

/**
 * 获取系统城市下拉
 * @returns {*}
 */
export function getSystemCityOptionApi() {
  return request({
    url: '/setting/city/options',
    method: 'get'
  })
}

/**
 * 获取渠道商配置
 * @returns {AxiosPromise}
 */
export function getChannelConfigApi() {
  return request({
    url: '/setting/config/channel',
    method: 'get'
  })
}

// ==================== 配置分类管理 ====================

/**
 * 获取配置分类列表
 * @param {Object} params 查询参数
 * @returns {AxiosPromise}
 */
export function getConfigCategoryListApi(params) {
  return request({
    url: '/setting/config_category',
    method: 'get',
    params
  })
}

/**
 * 获取配置分类详情
 * @param {Number} id
 * @returns {AxiosPromise}
 */
export function getConfigCategoryReadApi(id) {
  return request({
    url: `/setting/config_category/${id}`,
    method: 'get'
  })
}

/**
 * 添加配置分类
 * @param {Object} data
 * @returns {AxiosPromise}
 */
export function configCategorySaveApi(data) {
  return request({
    url: '/setting/config_category',
    method: 'post',
    data
  })
}

/**
 * 修改配置分类
 * @param {Number} id
 * @param {Object} data
 * @returns {AxiosPromise}
 */
export function configCategoryUpdateApi(id, data) {
  return request({
    url: `/setting/config_category/${id}`,
    method: 'put',
    data
  })
}

/**
 * 删除配置分类
 * @param {Number} id
 * @returns {AxiosPromise}
 */
export function configCategoryDeleteApi(id) {
  return request({
    url: `/setting/config_category/${id}`,
    method: 'delete'
  })
}

/**
 * 修改配置分类状态
 * @param {Number} id
 * @returns {AxiosPromise}
 */
export function configCategoryShowApi(id) {
  return request({
    url: `/setting/config_category/${id}/show`,
    method: 'get'
  })
}

// ==================== 配置管理 ====================

/**
 * 获取配置列表（按分类）
 * @param {Object} params
 * @returns {AxiosPromise}
 */
export function getConfigListApi(params) {
  return request({
    url: '/setting/config',
    method: 'get',
    params
  })
}

/**
 * 获取配置分类下拉列表
 * @returns {AxiosPromise}
 */
export function getConfigCateApi() {
  return request({
    url: '/setting/config/cate',
    method: 'get'
  })
}

/**
 * 保存配置
 * @param {Object} data
 * @returns {AxiosPromise}
 */
export function configSaveApi(data) {
  return request({
    url: '/setting/config',
    method: 'post',
    data
  })
}

/**
 * 获取配置模板
 * @returns {AxiosPromise}
 */
export function getConfigTemplateApi(params) {
  return request({
    url: '/setting/config_template',
    method: 'get',
    params
  })
}

/**
 * 保存配置模板
 * @param data
 * @returns {AxiosPromise}
 */
export function configTemplateSaveApi(data) {
  return request({
    url: '/setting/config_template',
    method: 'post',
    data
  })
}

/**
 * 修改模板
 * @param id
 * @param data
 * @returns {AxiosPromise}
 */
export function configTemplateUpdateApi(id, data) {
  return request({
    url: '/setting/config_template/' + id,
    method: 'put',
    data
  })
}

/**
 * 删除模板
 * @param id
 * @returns {AxiosPromise}
 */
export function configTemplateDeleteApi(id) {
  return request({
    url: `/setting/config_template/${id}`,
    method: 'delete'
  })
}

/**
 * 获取状态开启的配置模板下拉选项
 * @returns {AxiosPromise}
 */
export function getConfigTemplateOptionsApi() {
  return request({
    url: '/setting/config_template/options',
    method: 'get'
  })
}

/**
 * 修改模板状态
 * @param id
 * @param status
 * @returns {AxiosPromise}
 */
export function configTemplateStatusApi(id, status) {
  return request({
    url: `/setting/config_template/${id}/status/${status}`,
    method: 'get'
  })
}

// ==================== 商户菜单管理 ====================

/**
 * 获取商户菜单列表
 * @param params
 * @return {AxiosPromise}
 */
export function getMerchantMenusListApi(params) {
  return request({
    url: '/merchant_menu',
    method: 'get',
    params
  })
}

/**
 * 获取商户菜单规则地址
 * @param params
 * @returns {AxiosPromise}
 */
export function getMerchantMenusRuleApi(params) {
  return request({
    url: '/merchant_menu/get_rule',
    method: 'get',
    params
  })
}

/**
 * 添加商户菜单
 * @param data
 * @return {AxiosPromise}
 */
export function addMerchantMenusApi(id, data) {
  return request({
    url: `/merchant_menu/${id}`,
    method: 'post',
    data
  })
}

/**
 * 修改商户菜单状态
 * @param id
 * @param status
 * @return {AxiosPromise}
 */
export function putMerchantMenuStatusApi(id, status) {
  return request({
    url: `/merchant_menu/${id}/status/${status}`,
    method: 'put'
  })
}

/**
 * 查看商户菜单详情
 * @param id
 * @return {AxiosPromise}
 */
export function getMerchantMenuDetailApi(id) {
  return request({
    url: `/merchant_menu/${id}`,
    method: 'get'
  })
}
