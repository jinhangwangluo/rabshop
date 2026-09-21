import request from '@/utils/request'

/**
 * 获取内容列表（支持图文/文字/小说/图片/音频/视频）
 * @param params 查询参数：title, content_type, status, audit_status, is_hot, is_banner, price, is_trial, mer_id, create_time
 * @return {AxiosPromise}
 */
export function getArticleList(params) {
  return request({
    url: '/article',
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
    url: '/article/content_types',
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
    url: '/article/search_list',
    method: 'get',
    params
  })
}

/**
 * URL采集内容
 * @param data 参数：url
 * @returns {*}
 */
export function collectArticle(data) {
  return request({
    url: '/article/collect',
    method: 'post',
    data
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
    url: '/article/ai_generate',
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
    url: '/article/ai_continue',
    method: 'post',
    data,
    timeout: 180000
  })
}

/**
 * 添加内容
 * @param data 内容数据：title, article_category_id, content_type, cover, price, original_price, is_trial,
 *             content(图文/文字/小说), media_url(音频/视频), images(图片), ...
 * @returns {*}
 */
export function articleAdd(data) {
  return request({
    url: '/article',
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
    url: `/article/${id}`,
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
    url: `/article/${id}`,
    method: 'put',
    data
  })
}

/**
 * 快捷编辑内容字段。
 * @param id 内容ID
 * @param data 支持 field/value、fields 对象或直接传多个字段；平台端不触发审核
 * @returns {*}
 */
export function articleQuickUpdate(id, data) {
  return request({
    url: `/article/${id}/quick_update`,
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
    url: `/article/${id}`,
    method: 'delete'
  })
}

/**
 * 启用/禁用内容
 * @param id
 * @returns {*}
 */
export function articleSetShow(id) {
  return request({
    url: `/article/status/${id}`,
    method: 'get'
  })
}

/**
 * 审核商户提交的内容
 * @param id
 * @param data
 * @returns {*}
 */
export function auditArticle(id, data) {
  return request({
    url: `/audit/article/${id}`,
    method: 'post',
    data
  })
}

/**
 * 获取内容待审核统计（文章页顶部 Tab 角标使用）
 * @returns {*}
 */
export function getArticlePendingCount() {
  return request({
    url: '/audit/pending_count',
    method: 'get'
  })
}

/**
 * 获取文章分类列表
 * @param params
 * @return {AxiosPromise}
 */
export function getArticleCategoryList(params) {
  return request({
    url: '/article_category',
    method: 'get',
    params
  })
}

/**
 * 添加文章分类
 * @param data
 * @returns {*}
 */
export function articleCategoryAdd(data) {
  return request({
    url: '/article_category',
    method: 'post',
    data
  })
}

/**
 * 获取文章分类详情
 * @param id
 * @returns {*}
 */
export function articleCategoryRead(id) {
  return request({
    url: `/article_category/${id}`,
    method: 'get'
  })
}

/**
 * 保存文章分类编辑
 * @param id
 * @param data
 * @returns {*}
 */
export function articleCategorySave(id, data) {
  return request({
    url: `/article_category/${id}`,
    method: 'put',
    data
  })
}

/**
 * 删除文章分类
 * @param id
 * @return {AxiosPromise}
 */
export function articleCategoryDelete(id) {
  return request({
    url: `/article_category/${id}`,
    method: 'delete'
  })
}

/**
 * 设置文章分类状态
 * @param id
 * @returns {*}
 */
export function articleCategorySetStatus(id) {
  return request({
    url: `/article_category/${id}/show`,
    method: 'get'
  })
}

/**
 * 获取分类二级树（级联选择器使用）
 * @returns {*}
 */
export function getArticleCategoryTree() {
  return request({
    url: '/article_category_tree',
    method: 'get'
  })
}

/**
 * 快捷添加分类
 * @param data { name: string, pid: number }
 * @returns {*}
 */
export function articleCategoryQuickAdd(data) {
  return request({
    url: '/article_category/quick_add',
    method: 'post',
    data
  })
}
