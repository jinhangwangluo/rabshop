import { get, post } from './request'
import { AUTH_MODES } from '@/utils/auth'

/**
 * 获取商家中心首页聚合数据。
 *
 * 使用场景：商家中心首页进入时，获取商户信息、幻灯片与按装修顺序返回的首页模块；匿名可访问。
 *
 * @param {Object} params 查询参数。
 * @param {number|string} params.merchant_id 商户 ID。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} `data` 包含 `merchant/banners/home_config/sections`，`home_config.menu.mode` 为底部菜单类型；模块为 `type/list/total/page/limit/has_more`。
 */
export function getMerchantHome(params = {}, options = {}) {
  return get('merchant/home', params, {
    authMode: AUTH_MODES.NONE,
    ...options
  })
}

/**
 * 获取商户首页末尾模块的下一页。
 *
 * 匿名可访问；模块来源与每页数量由商户配置控制，仅最后一个启用模块允许继续加载。
 *
 * @param {Object} params 查询参数。
 * @param {number|string} params.merchant_id 商户 ID。
 * @param {'content'|'special'|'product'} params.type 首页模块类型。
 * @param {number|string} params.page 页码。
 * @param {Object} options 请求附加选项。
 * @returns {Promise<Object>} `data` 返回 `type/list/total/page/limit/has_more`。
 */
export function getMerchantHomeSection(params = {}, options = {}) {
  return get('merchant/home/section', params, {
    authMode: AUTH_MODES.NONE,
    ...options
  })
}

/**
 * 获取商户移动端分类页配置与分类树。
 *
 * 匿名可访问；商品分类使用商户分类，不与平台商品分类 ID 混用。
 *
 * @param {Object} params 查询参数。
 * @param {number|string} params.merchant_id 商户 ID。
 * @param {Object} options 请求附加选项。
 * @returns {Promise<Object>} `data` 返回分类 `mode: special|product`、`categories` 分类树与独立的底部菜单配置 `menu.mode`。
 */
export function getMerchantCategory(params = {}, options = {}) {
  return get('merchant/category', params, {
    authMode: AUTH_MODES.NONE,
    ...options
  })
}

/**
 * 获取商户分类下的专题或商品分页列表。
 *
 * 匿名可访问；分类范围与展示类型由商户移动端配置控制。
 *
 * @param {Object} params 查询参数。
 * @param {number|string} params.merchant_id 商户 ID。
 * @param {number|string} params.category_id 当前商户分类 ID。
 * @param {number|string} params.page 页码。
 * @param {number|string} params.limit 每页数量，页面默认 10。
 * @param {Object} options 请求附加选项。
 * @returns {Promise<Object>} `data` 返回 `mode/list/total/page/limit/has_more`。
 */
export function getMerchantCategoryList(params = {}, options = {}) {
  return get('merchant/category/list', params, {
    authMode: AUTH_MODES.NONE,
    ...options
  })
}

/**
 * 获取商家店铺公开详情。
 *
 * 使用场景：商家店铺资料页进入时，根据 URL 中的 merchant_id 获取商户基础信息、资质、联系信息和位置。
 *
 * @param {Object} params 查询参数。
 * @param {number|string} params.merchant_id 商户 ID。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data` 返回商户详情及 `menu.mode: special|product`，用于同步底部菜单名称与列表类型。
 */
export function getMerchantDetail(params = {}, options = {}) {
  return get('merchant/detail', params, {
    authMode: AUTH_MODES.NONE,
    ...options
  })
}

/**
 * 获取当前用户对店铺/平台的关注状态。
 *
 * 使用场景：商家店铺页、内容详情页加载后静默同步关注按钮状态；需要登录。
 *
 * @param {Object} params 查询参数。
 * @param {number|string} params.merchant_id 商户 ID；0 表示平台。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时返回 is_followed、follow_count 和 follow_count_text。
 */
export function getMerchantFollowStatus(params = {}, options = {}) {
  return get('merchant/follow/status', params, {
    authMode: AUTH_MODES.REQUIRED,
    ...options
  })
}

/**
 * 切换当前用户对店铺/平台的关注状态。
 *
 * 使用场景：商家店铺页、内容详情页点击关注/取消关注时调用；需要登录。
 *
 * @param {Object} data 请求参数。
 * @param {number|string} data.merchant_id 商户 ID；0 表示平台。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时返回 is_followed、follow_count 和 follow_count_text。
 */
export function toggleMerchantFollow(data = {}, options = {}) {
  return post('merchant/follow/toggle', data, {
    authMode: AUTH_MODES.REQUIRED,
    ...options
  })
}
