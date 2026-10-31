import request from '@/utils/request'

/**
 * 获取敏感词列表（分页）
 * @param {Object} params - 查询参数：word, status, page, page_size
 */
export function getSensitiveWordList(params) {
  return request({
    url: '/setting/sensitive_words',
    method: 'get',
    params
  })
}

/**
 * 获取敏感词详情
 * @param {Number} id
 */
export function getSensitiveWordDetail(id) {
  return request({
    url: `/setting/sensitive_words/${id}`,
    method: 'get'
  })
}

/**
 * 新增敏感词
 * @param {Object} data - { word, remark, status }
 */
export function addSensitiveWord(data) {
  return request({
    url: '/setting/sensitive_words',
    method: 'post',
    data
  })
}

/**
 * 修改敏感词
 * @param {Number} id
 * @param {Object} data - { word, remark, status }
 */
export function updateSensitiveWord(id, data) {
  return request({
    url: `/setting/sensitive_words/${id}`,
    method: 'put',
    data
  })
}

/**
 * 删除敏感词
 * @param {Number} id
 */
export function deleteSensitiveWord(id) {
  return request({
    url: `/setting/sensitive_words/${id}`,
    method: 'delete'
  })
}

/**
 * 启用/禁用敏感词（切换状态）
 * @param {Number} id
 */
export function toggleSensitiveWordStatus(id) {
  return request({
    url: `/setting/sensitive_words/${id}/show`,
    method: 'get'
  })
}

/**
 * 批量添加敏感词
 * @param {Array<string>} words - 词语数组
 */
export function batchAddSensitiveWords(words) {
  return request({
    url: '/setting/sensitive_words/batch_save',
    method: 'post',
    data: { words }
  })
}

/**
 * 批量删除敏感词
 * @param {Array<number>} ids - ID 数组
 */
export function batchDeleteSensitiveWords(ids) {
  return request({
    url: '/setting/sensitive_words/batch_delete',
    method: 'post',
    data: { ids }
  })
}

/**
 * 导出敏感词（返回数据列表）
 * @param {Object} params - { word, status }
 */
export function exportSensitiveWords(params) {
  return request({
    url: '/setting/sensitive_words/export',
    method: 'get',
    params
  })
}

/**
 * 导入敏感词（文件上传，每行一词）
 * @param {FormData} formData - 包含 file 字段
 */
export function importSensitiveWords(formData) {
  return request({
    url: '/setting/sensitive_words/import',
    method: 'post',
    data: formData,
    headers: { 'Content-Type': 'multipart/form-data' }
  })
}

/**
 * 清除敏感词缓存
 */
export function clearSensitiveWordsCache() {
  return request({
    url: '/setting/sensitive_words/clear_cache',
    method: 'post'
  })
}

/**
 * 检测文本是否命中敏感词
 * @param {string} content - 待检测文本
 */
export function checkSensitiveWords(content) {
  return request({
    url: '/setting/sensitive_words/check',
    method: 'post',
    data: { content }
  })
}
