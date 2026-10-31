import request from '@/utils/request'

/**
 * 获取专题列表
 * @param params title, merchant_id, special_type, status, is_hot, price, create_time
 */
export function getSpecialList(params) {
  return request({
    url: '/special',
    method: 'get',
    params
  })
}

/**
 * 添加专题
 * @param data title, merchant_id, keywords, description, cover, special_type, price, original_price, sort, is_hot, status, contents[]
 */
export function specialAdd(data) {
  return request({
    url: '/special',
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
    url: `/special/${id}`,
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
    url: `/special/${id}`,
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
    url: `/special/${id}`,
    method: 'delete'
  })
}

/**
 * 启用/禁用专题
 * @param id
 */
export function specialSetShow(id) {
  return request({
    url: `/special/${id}/show`,
    method: 'get'
  })
}

/**
 * 获取专题内容列表（携带内容详情）
 * @param id 专题ID
 */
export function getSpecialContentList(id) {
  return request({
    url: `/special/${id}/content_list`,
    method: 'get'
  })
}

/**
 * 审核商户提交的专题
 * @param id 专题ID
 * @param data { audit_status, audit_remark }
 */
export function auditSpecial(id, data) {
  return request({
    url: `/special/${id}/audit`,
    method: 'post',
    data
  })
}

/**
 * 获取专题待审核统计（专题页商户 Tab 角标使用）
 */
export function getSpecialPendingCount() {
  return request({
    url: '/audit/pending_count',
    method: 'get'
  })
}

/**
 * 搜索内容列表（专题添加内容选择器）
 * @param params title, content_type, page, limit
 */
export function searchSpecialContent(params) {
  return request({
    url: '/special/search_content',
    method: 'get',
    params
  })
}

/**
 * 获取专题分类树（DIY 按分类选择器使用）
 * @param params name, status
 */
export function getSpecialCategoryTree(params) {
  return request({
    url: '/special_category_tree',
    method: 'get',
    params
  })
}

/**
 * 获取专题分类列表
 * @param params name, pid, status
 */
export function getSpecialCategoryList(params) {
  return request({
    url: '/special_category',
    method: 'get',
    params
  })
}

/**
 * 添加专题分类
 * @param data name, pid, sort, status, image, banner_image
 */
export function specialCategoryAdd(data) {
  return request({
    url: '/special_category',
    method: 'post',
    data
  })
}

/**
 * 编辑专题分类
 * @param id
 * @param data name, pid, sort, status, image, banner_image
 */
export function specialCategorySave(id, data) {
  return request({
    url: `/special_category/${id}`,
    method: 'put',
    data
  })
}

/**
 * 删除专题分类
 * @param id
 */
export function specialCategoryDelete(id) {
  return request({
    url: `/special_category/${id}`,
    method: 'delete'
  })
}

/**
 * 快捷添加专题分类
 * @param data name, pid, image
 */
export function specialCategoryQuickAdd(data) {
  return request({
    url: '/special_category/quick_add',
    method: 'post',
    data
  })
}

/**
 * 启用/禁用专题分类
 * @param id
 */
export function specialCategorySetStatus(id) {
  return request({
    url: `/special_category/${id}/show`,
    method: 'get'
  })
}
