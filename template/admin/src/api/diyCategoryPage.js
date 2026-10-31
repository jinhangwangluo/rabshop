import request from '@/utils/request'

/**
 * 获取 DIY 分类页面当前配置。
 */
export function getDiyCategoryPageConfig() {
  return request({ url: '/diy/category_page/config', method: 'get' })
}

/**
 * 保存 DIY 分类页面配置。
 *
 * @param {{style_type: string, config: {category_ids: number[]}}} data 配置数据
 */
export function saveDiyCategoryPageConfig(data) {
  return request({ url: '/diy/category_page/config', method: 'put', data })
}
