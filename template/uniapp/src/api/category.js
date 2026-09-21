import { get } from './request'
import { AUTH_MODES } from '@/utils/auth'

/**
 * 获取移动端分类页装修配置。
 *
 * 使用场景：tabBar 分类页首屏进入时调用，根据 `style_type` 决定渲染专题分类、商品分类宫格、顶部选项卡或层级展开商品列表。
 * 匿名可访问，登录状态下会随请求自动附带登录态。
 *
 * @param {Object} params 查询参数，通常留空。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data` 返回 `style_type` 和 `config.category_ids`。
 */
export function getCategoryPageConfig(params = {}, options = {}) {
  return get('diy/category_page/config', params, {
    authMode: AUTH_MODES.OPTIONAL,
    loginRedirect: false,
    ...options
  })
}

/**
 * 获取移动端专题分类树。
 *
 * 使用场景：分类 tab 页首屏加载时调用，返回一级分类及其二级子分类，支持图标、分类大图与 Banner 展示。
 *
 * @param {Object} params 附加查询参数，支持 `category_ids` 逗号串或数组。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data` 为专题分类树数组，一级分类可包含 `banner_image`。
 */
export function getSpecialCategoryTree(params = {}, options = {}) {
  return get('special/category/tree', params, {
    authMode: AUTH_MODES.NONE,
    ...options
  })
}
