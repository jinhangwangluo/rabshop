import request from '@/utils/request'

// ===================== DIY页面管理 =====================

// 获取页面列表
export function getDiyPageList(params) {
  return request({ url: '/diy/page', method: 'get', params })
}

// 获取页面详情
export function getDiyPageDetail(id) {
  return request({ url: `/diy/page/${id}`, method: 'get' })
}

// 创建页面
export function createDiyPage(data) {
  return request({ url: '/diy/page', method: 'post', data })
}

// 编辑页面
export function updateDiyPage(id, data) {
  return request({ url: `/diy/page/${id}`, method: 'put', data })
}

// 删除页面
export function deleteDiyPage(id) {
  return request({ url: `/diy/page/${id}`, method: 'delete' })
}

// 设置为默认页面
export function setDefaultPage(id) {
  return request({ url: `/diy/page/set_default/${id}`, method: 'get' })
}

// 获取页面详情（含组件）
export function getDiyPageDetailWithComponents(id) {
  return request({ url: `/diy/page/detail/${id}`, method: 'get' })
}

// 预览页面
export function previewDiyPage(id) {
  return request({ url: `/diy/page/preview/${id}`, method: 'get' })
}

// 切换页面状态
export function toggleDiyPageStatus(id) {
  return request({ url: `/diy/page/status/${id}`, method: 'get' })
}

// 获取组件类型选项
export function getComponentTypes() {
  return request({ url: '/diy/page/component_types', method: 'get' })
}

/**
 * 获取移动端主题换色配置。
 * @returns {AxiosPromise}
 */
export function getThemeConfigApi() {
  return request({ url: '/diy/theme_config', method: 'get' })
}

/**
 * 保存移动端主题换色配置。
 * @param {Object} data 主题配置
 * @param {string} data.theme_key 主题预设标识
 * @param {string} data.primary_color 主色
 * @param {string} data.secondary_color 辅助色
 * @param {string} data.gradient_color 渐变色
 * @returns {AxiosPromise}
 */
export function saveThemeConfigApi(data) {
  return request({ url: '/diy/theme_config', method: 'post', data })
}

// 获取个人中心装修配置
export function getUserCenterDiyConfig() {
  return request({ url: '/diy/page/user_center', method: 'get' })
}

// 保存个人中心装修配置
export function saveUserCenterDiyConfig(data) {
  return request({ url: '/diy/page/user_center/save', method: 'post', data })
}

// ===================== DIY分类管理 =====================

// 获取 DIY 分类列表
export function getDiyCategoryList(params) {
  return request({ url: '/diy/category', method: 'get', params })
}

// 获取 DIY 分类树
export function getDiyCategoryTree(params) {
  return request({ url: '/diy/category/tree', method: 'get', params })
}

// 获取 DIY 分类详情
export function getDiyCategoryDetail(id) {
  return request({ url: `/diy/category/${id}`, method: 'get' })
}

// 新增 DIY 分类
export function createDiyCategory(data) {
  return request({ url: '/diy/category', method: 'post', data })
}

// 编辑 DIY 分类
export function updateDiyCategory(id, data) {
  return request({ url: `/diy/category/${id}`, method: 'put', data })
}

// 删除 DIY 分类
export function deleteDiyCategory(id) {
  return request({ url: `/diy/category/${id}`, method: 'delete' })
}

// 快捷新增 DIY 分类
export function quickAddDiyCategory(data) {
  return request({ url: '/diy/category/quick_add', method: 'post', data })
}

// ===================== DIY页面链接管理 =====================

// 获取 DIY 链接列表
export function getDiyLinkList(params) {
  return request({ url: '/diy/link', method: 'get', params })
}

// 获取 DIY 链接详情
export function getDiyLinkDetail(id) {
  return request({ url: `/diy/link/${id}`, method: 'get' })
}

// 新增 DIY 链接
export function createDiyLink(data) {
  return request({ url: '/diy/link', method: 'post', data })
}

// 编辑 DIY 链接
export function updateDiyLink(id, data) {
  return request({ url: `/diy/link/${id}`, method: 'put', data })
}

// 删除 DIY 链接
export function deleteDiyLink(id) {
  return request({ url: `/diy/link/${id}`, method: 'delete' })
}

// 修改 DIY 链接状态
export function toggleDiyLinkStatus(id, status) {
  return request({ url: `/diy/link/status/${id}`, method: 'get', params: { status }})
}

// 获取选链弹窗列表
export function getDiyLinkOptions(params) {
  return request({ url: '/diy/link/options', method: 'get', params })
}

// ===================== DIY组件管理 =====================

// 获取组件列表
export function getDiyComponentList(params) {
  return request({ url: '/diy/component', method: 'get', params })
}

// 获取组件详情
export function getDiyComponentDetail(id) {
  return request({ url: `/diy/component/${id}`, method: 'get' })
}

// 创建组件
export function createDiyComponent(data) {
  return request({ url: '/diy/component', method: 'post', data })
}

// 编辑组件
export function updateDiyComponent(id, data) {
  return request({ url: `/diy/component/${id}`, method: 'put', data })
}

// 删除组件
export function deleteDiyComponent(id) {
  return request({ url: `/diy/component/${id}`, method: 'delete' })
}

// 批量更新排序
export function batchSortDiyComponent(data) {
  return request({ url: '/diy/component/batch_sort', method: 'post', data })
}

// 全量保存页面所有组件（一次性提交，后端先删旧再插新）
export function saveAllComponents(data) {
  return request({ url: '/diy/component/save_all', method: 'post', data })
}

// 切换组件状态
export function toggleDiyComponentStatus(id) {
  return request({ url: `/diy/component/status/${id}`, method: 'get' })
}

// 复制组件
export function copyDiyComponent(data) {
  return request({ url: '/diy/component/copy', method: 'post', data })
}

// 获取页面下的组件列表
export function getComponentsByPageId(diyPageId) {
  return request({ url: '/diy/component/list_by_page', method: 'get', params: { diy_page_id: diyPageId }})
}

// 获取组件类型选项
export function getDiyComponentTypes() {
  return request({ url: '/diy/component/types', method: 'get' })
}

// 另存为模板
export function saveAsTemplate(id, data) {
  return request({ url: `/diy/page/save_as_template/${id}`, method: 'post', data })
}
