import request from '@/utils/request'

const PLUGIN_PREFIX = '/plugins/seckill'

function normalizeId(id) {
  const value = String(id || '').trim()
  if (!/^[1-9]\d*$/.test(value)) throw new Error('秒杀资源 ID 无效')
  return value
}

/** 查询活动分页；需要后台登录。
 * @param {Object} params 搜索、状态、审核和分页。
 * @returns {Promise<Object>} list/count/page/limit。
 */
export function getActivityList(params = {}) {
  return request({ url: `${PLUGIN_PREFIX}/activities`, method: 'get', params })
}

/** 获取活动与商品 SKU 配置；需要后台登录。
 * @param {number|string} id 活动 ID。
 * @param {string} installGeneration 当前安装代次，防止旧配置引用重装后的活动。
 * @returns {Promise<Object>} 编辑回填数据。
 */
export function getActivityDetail(id, installGeneration = '') {
  return request({ url: `${PLUGIN_PREFIX}/activities/${normalizeId(id)}`, method: 'get', params: installGeneration ? { install_generation: installGeneration } : {}})
}

/** 保存活动；商户活动须平台审核。
 * @param {Object} data 日期、时段、商品和 SKU。
 * @param {number|string} id 编辑 ID，新增留空。
 * @returns {Promise<Object>} 保存响应。
 */
export function saveActivity(data, id = '') {
  return request({ url: id ? `${PLUGIN_PREFIX}/activities/${normalizeId(id)}` : `${PLUGIN_PREFIX}/activities`, method: id ? 'put' : 'post', data })
}

/** 删除未开始活动。
 * @param {number|string} id 活动 ID。
 * @param {string} installGeneration 列表中的安装代次。
 * @returns {Promise<Object>} 删除响应。
 */
export function deleteActivity(id, installGeneration) {
  return request({ url: `${PLUGIN_PREFIX}/activities/${normalizeId(id)}`, method: 'delete', params: { install_generation: installGeneration }})
}

/** 发布或暂停活动。
 * @param {number|string} id 活动 ID。
 * @param {number} status 0 暂停，1 发布。
 * @param {string} installGeneration 列表中的安装代次。
 * @returns {Promise<Object>} 状态响应。
 */
export function setActivityStatus(id, status, installGeneration) {
  return request({ url: `${PLUGIN_PREFIX}/activities/${normalizeId(id)}/status`, method: 'put', data: { status, install_generation: installGeneration }})
}

/** 查询可参与的普通实物商品；商户范围由服务端鉴权限制。
 * @param {Object} params 搜索、商户和分页。
 * @returns {Promise<Object>} list/count/page/limit。
 */
export function getProductOptions(params = {}) {
  return request({ url: `${PLUGIN_PREFIX}/products/options`, method: 'get', params })
}

/** 查询普通商品真实 SKU。
 * @param {number|string} id 普通商品 ID。
 * @returns {Promise<Object>} product_id/skus。
 */
export function getProductSkus(id) {
  return request({ url: `${PLUGIN_PREFIX}/products/${normalizeId(id)}/skus`, method: 'get' })
}

/** 查询活动日期场次，用于后台 DIY 固定场次选择；需要后台登录。
 * @param {Object} params activity_id、date。
 * @returns {Promise<Object>} date/dates/list/server_time。
 */
export function getSessions(params = {}) {
  return request({ url: `${PLUGIN_PREFIX}/sessions`, method: 'get', params })
}

/** 平台审核商户活动。
 * @param {number|string} id 活动 ID。
 * @param {Object} data audit_status/audit_reason。
 * @returns {Promise<Object>} 审核响应。
 */
export function auditActivity(id, data) {
  return request({ url: `${PLUGIN_PREFIX}/activities/${normalizeId(id)}/audit`, method: 'put', data })
}

/** 读取秒杀支付保留设置；仅平台管理员。
 * @returns {Promise<Object>} unpaid_cancel_minutes。
 */
export function getSettings() {
  return request({ url: `${PLUGIN_PREFIX}/settings`, method: 'get' })
}

/** 保存秒杀支付保留分钟数；仅影响新订单。
 * @param {Object} data unpaid_cancel_minutes，1..1440。
 * @returns {Promise<Object>} 设置响应。
 */
export function saveSettings(data) {
  return request({ url: `${PLUGIN_PREFIX}/settings`, method: 'put', data })
}

/** 后台 DIY 预览的活动商品聚合；需要平台登录。
 * @param {Object} params 活动、场次、数量和活动商品 ID。
 * @returns {Promise<Object>} 商品列表和场次。
 */
export function getDiyProducts(params = {}) {
  return request({ url: `${PLUGIN_PREFIX}/diy`, method: 'get', params })
}
