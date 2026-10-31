import { get } from './request'
import { AUTH_MODES } from '@/utils/auth'

/**
 * DIY 页面接口封装。
 * 装修内容可匿名访问；登录状态下会附带 token，以便后端返回用户相关状态。
 */

/**
 * 获取首页 DIY 装修数据。
 *
 * 使用方式：首页进入时调用 `getDiyHomePage()`，返回结果交给 `normalizeDiyPagePayload` 处理。
 *
 * @param {Object} params 查询参数，通常留空。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时返回首页页面配置和组件列表。
 */
export function getDiyHomePage(params = {}, options = {}) {
  return get('diy/home', params, {
    authMode: AUTH_MODES.OPTIONAL,
    loginRedirect: false,
    ...options
  })
}

/**
 * 获取普通 DIY 页面数据。
 *
 * 使用方式：当页面需要根据页面 ID、标识或路由查询装修内容时调用。
 *
 * @param {Object} params 页面查询参数，例如页面标识。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时返回页面配置和组件列表。
 */
export function getDiyPage(params = {}, options = {}) {
  return get('diy/page', params, {
    authMode: AUTH_MODES.OPTIONAL,
    loginRedirect: false,
    ...options
  })
}

/**
 * 获取装修预览数据。
 *
 * 使用方式：装修后台或预览页联调时调用，用于查看未正式发布的页面内容。
 *
 * @param {Object} params 预览参数，例如页面 ID、草稿标识等。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时返回预览态页面数据。
 */
export function previewDiyPage(params = {}, options = {}) {
  return get('diy/preview', params, {
    authMode: AUTH_MODES.OPTIONAL,
    loginRedirect: false,
    ...options
  })
}

/**
 * 获取单个 DIY 组件按需数据。
 *
 * 使用方式：移动端 DIY 渲染器按组件实例调用，用于内容、专题、商品、直播、优惠券等动态组件懒加载。
 * 当组件启用触底加载时，可额外传入 `page` / `limit` 获取分页结果。
 *
 * @param {Object} params 查询参数。
 * @param {number} params.component_id DIY 组件 ID。
 * @param {number} [params.category_id] 分类标签样式下当前要加载的分类 ID。
 * @param {number} [params.page] 分页页码，触底加载时使用。
 * @param {number} [params.limit] 每页条数，通常与组件显示数量一致。
 * @param {Object} options 透传给请求封装的附加选项；可匿名访问，登录后会附带用户状态。
 * @returns {Promise<Object>} 成功时返回组件真实数据。
 */
export function getDiyComponentData(params = {}, options = {}) {
  return get('diy/component/data', params, {
    authMode: AUTH_MODES.OPTIONAL,
    loginRedirect: false,
    ...options
  })
}

/**
 * 获取个人中心 DIY 装修配置。
 *
 * 使用方式：个人中心进入时调用，用返回的 service_menus、banner、recommend_type 与会员入口状态覆盖默认展示；
 * 匿名用户可访问，登录用户会随请求自动带上登录态。
 *
 * @param {Object} params 查询参数，通常留空。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时返回个人中心菜单、广告和推荐展示类型配置。
 */
export function getDiyUserCenterConfig(params = {}, options = {}) {
  return get('diy/user_center', params, {
    authMode: AUTH_MODES.OPTIONAL,
    loginRedirect: false,
    ...options
  })
}
