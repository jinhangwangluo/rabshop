import request from '@/utils/request'

/**
 * 获取内容列表（支持图文/文字/小说/图片/音频/视频）
 * @param params 查询参数：title, content_type, status, is_hot, is_banner, price, is_trial, create_time
 * @return {AxiosPromise}
 */
export function getArticleList(params) {
  return request({
    url: '/content/article/list',
    method: 'get',
    params
  })
}

/**
 * 获取内容类型列表
 * @returns {*}
 */
export function getContentTypes() {
  return request({
    url: '/content/article/content_types',
    method: 'get'
  })
}

/**
 * 搜索内容列表（专题选择内容时使用）
 * @param params 参数：title, content_type, page, limit
 * @returns {*}
 */
export function searchContentList(params) {
  return request({
    url: '/content/article/search',
    method: 'get',
    params
  })
}

/**
 * AI润色生成内容发布建议。
 * @param {Object} data 当前表单上下文
 * @param {string} data.title 内容标题，必填
 * @param {number} data.content_type 内容类型，仅支持 1图文、2文字、3小说
 * @param {string} [data.author] 作者
 * @param {string} [data.keywords] 关键词
 * @param {string} [data.description] 当前简介
 * @param {string} [data.content] 当前正文 HTML
 * @param {string} [data.trial_content] 当前试读 HTML
 * @param {number} [data.price] 当前售价
 * @param {number} [data.is_trial] 是否开启试读
 * @returns {AxiosPromise} 返回 description、content、trial_content、price、tag_suggestions 和 model
 */
export function aiGenerateArticle(data) {
  return request({
    url: '/content/article/ai_generate',
    method: 'post',
    data,
    timeout: 180000
  })
}

/**
 * AI续写正文或试读内容。
 * @param {Object} data
 * @param {string} data.title 内容标题，必填
 * @param {number} data.content_type 内容类型，仅支持 1图文、2文字、3小说
 * @param {'content'|'trial_content'} data.field 续写字段
 * @param {string} [data.content] 该字段当前 HTML（可为空，为空时 AI 从标题起笔）
 * @returns {AxiosPromise} 返回 content（续写 HTML）和 model
 */
export function aiContinueArticle(data) {
  return request({
    url: '/content/article/ai_continue',
    method: 'post',
    data,
    timeout: 180000
  })
}

/**
 * 添加内容
 * @param data 内容数据
 * @returns {*}
 */
export function articleAdd(data) {
  return request({
    url: '/content/article/create',
    method: 'post',
    data
  })
}

/**
 * 获取内容详情
 * @param id
 * @returns {*}
 */
export function articleRead(id) {
  return request({
    url: `/content/article/detail/${id}`,
    method: 'get'
  })
}

/**
 * 保存内容编辑
 * @param id
 * @param data
 * @returns {*}
 */
export function articleSave(id, data) {
  return request({
    url: `/content/article/update/${id}`,
    method: 'put',
    data
  })
}

/**
 * 快捷编辑内容字段。
 * @param id 内容ID
 * @param data 支持 field/value、fields 对象或直接传多个字段；标题/内容/图片字段会重新进入审核
 * @returns {*}
 */
export function articleQuickUpdate(id, data) {
  return request({
    url: `/content/article/quick_update/${id}`,
    method: 'put',
    data
  })
}

/**
 * 删除内容
 * @param id
 * @return {AxiosPromise}
 */
export function articleDelete(id) {
  return request({
    url: `/content/article/delete/${id}`,
    method: 'delete'
  })
}

/**
 * 启用/禁用内容（切换状态）
 * @param id
 * @param status 0|1
 * @returns {*}
 */
export function articleSetShow(id, status) {
  return request({
    url: `/content/article/status/${id}/${status}`,
    method: 'put'
  })
}

/**
 * 获取分类树（级联选择器使用，商户只读）
 * @returns {*}
 */
export function getArticleCategoryTree() {
  return request({
    url: '/content/article_category/tree',
    method: 'get'
  })
}
