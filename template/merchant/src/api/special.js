import request from '@/utils/request'

/**
 * 获取专题列表
 * @param params title, special_type, status, is_hot, price, create_time
 */
export function getSpecialList(params) {
  return request({
    url: '/content/special/list',
    method: 'get',
    params
  })
}

/**
 * 添加专题
 * @param data title, keywords, description, cover, special_type, price, original_price, sort, is_hot, status, contents[]
 */
export function specialAdd(data) {
  return request({
    url: '/content/special/create',
    method: 'post',
    data
  })
}

/**
 * 获取专题详情（含内容列表）
 * @param id
 */
export function specialRead(id) {
  return request({
    url: `/content/special/detail/${id}`,
    method: 'get'
  })
}

/**
 * 修改专题
 * @param id
 * @param data
 */
export function specialSave(id, data) {
  return request({
    url: `/content/special/update/${id}`,
    method: 'put',
    data
  })
}

/**
 * 删除专题
 * @param id
 */
export function specialDelete(id) {
  return request({
    url: `/content/special/delete/${id}`,
    method: 'delete'
  })
}

/**
 * 启用/禁用专题
 * @param id
 * @param status 0|1
 */
export function specialSetShow(id, status) {
  return request({
    url: `/content/special/status/${id}/${status}`,
    method: 'put'
  })
}

/**
 * 获取专题内容列表（携带内容详情）
 * @param id 专题ID
 */
export function getSpecialContentList(id) {
  return request({
    url: `/content/special/content_list/${id}`,
    method: 'get'
  })
}

/**
 * 搜索内容列表（专题添加内容选择器）
 * @param params title, content_type, page, limit
 */
export function searchSpecialContent(params) {
  return request({
    url: '/content/special/search_content',
    method: 'get',
    params
  })
}

/**
 * AI润色生成专题发布建议。
 * @param {Object} data 当前专题表单上下文
 * @param {string} data.title 专题标题，必填
 * @param {string} [data.keywords] 当前关键词
 * @param {string} [data.description] 当前专题介绍
 * @param {number} [data.price] 当前售价
 * @param {number} [data.special_type] 专题类型，0免费、1付费
 * @returns {AxiosPromise} 返回 description、keywords、price、tag_suggestions 和 model
 */
export function aiGenerateSpecial(data) {
  return request({
    url: '/content/special/ai_generate',
    method: 'post',
    data,
    timeout: 120000
  })
}

/**
 * 获取平台专题分类树（商户专题发布选择分类使用，需登录）
 * @returns {*}
 */
export function getSpecialCategoryTree() {
  return request({
    url: '/content/special_category/tree',
    method: 'get'
  })
}
