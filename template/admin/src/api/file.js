import request from '@/utils/request'

/**
 * 获取文件分类列表
 * @param params
 * @returns {AxiosPromise}
 */
export function getFileCategoryListApi(params) {
  return request({
    url: '/setting/file/category',
    method: 'get',
    params
  })
}

/**
 * 文件分类移动
 * @param data
 * @returns {AxiosPromise}
 */
export function fileMoveCategoryApi(data) {
  return request({
    url: '/setting/file/category/move',
    method: 'post',
    data
  })
}

/**
 * 获取文件列表
 * @param params
 * @returns {AxiosPromise}
 */
export function getFileListApi(params) {
  return request({
    url: '/setting/file/list',
    method: 'get',
    params
  })
}

/**
 * 获取文件分类表单
 * @param params
 * @returns {AxiosPromise}
 */
export function getFileCategoryFormApi(params) {
  return request({
    url: `/setting/file/category/create`,
    method: 'get',
    params
  })
}

/**
 * 保存文件分类
 * @param id
 * @param data
 * @returns {AxiosPromise}
 */
export function saveFileCategoryApi(id, data) {
  return request({
    url: `/setting/file/category/${id}`,
    method: 'post',
    data
  })
}

/**
 * 删除文件分类
 * @param id
 * @returns {AxiosPromise}
 */
export function deleteFileCategoryApi(id) {
  return request({
    url: `/setting/file/category/${id}`,
    method: 'delete'
  })
}

/**
 * 删除附件，支持单个 id 或 id 数组；需平台管理员登录。
 * @param {number|string|Array<number|string>} ids - 附件 id 或附件 id 列表
 * @returns {AxiosPromise}
 */
export function deleteFileApi(ids) {
  const isMultiple = Array.isArray(ids)
  return request({
    url: '/setting/file/delete',
    method: 'delete',
    data: isMultiple ? { ids } : { id: ids }
  })
}

/**
 * 更新附件名称；需平台管理员登录。
 * @param {number|string} id - 附件 id
 * @param {Object} data - 更新字段，当前使用 real_name
 * @returns {AxiosPromise}
 */
export function fileUpdateApi(id, data) {
  return request({
    url: `/setting/file/${id}`,
    method: 'put',
    data
  })
}

/**
 * 上传附件并自动写入附件库；需平台管理员登录。
 * @param {FormData} data - 上传表单，包含 file/cate_id/file_type
 * @returns {AxiosPromise}
 */
export function fileUploadApi(data) {
  return request({
    url: `/setting/file/upload`,
    method: 'post',
    data
  })
}

/**
 * 登记已上传附件，主要用于 COS/OSS/Qiniu 前端直传后入库；需平台管理员登录。
 * @param {Object} data - path/url/name/real_name/file_size/update_type/file_type/cate_id
 * @returns {AxiosPromise}
 */
export function fileRecordApi(data) {
  return request({
    url: '/setting/file/record',
    method: 'post',
    data
  })
}

/**
 * 获取 AI 生图任务列表
 * @param params
 * @returns {AxiosPromise}
 */
export function getFileAiGenerateTaskListApi(params) {
  return request({
    url: '/setting/file/ai-generate/tasks',
    method: 'get',
    params
  })
}

/**
 * 创建 AI 生图任务
 * @param data
 * @returns {AxiosPromise}
 */
export function createFileAiGenerateTaskApi(data) {
  return request({
    url: '/setting/file/ai-generate/tasks',
    method: 'post',
    data
  })
}

/**
 * 获取 AI 生图任务详情
 * @param id
 * @returns {AxiosPromise}
 */
export function getFileAiGenerateTaskDetailApi(id) {
  return request({
    url: `/setting/file/ai-generate/tasks/${id}`,
    method: 'get'
  })
}

/**
 * 取消 AI 生图任务
 * @param id
 * @returns {AxiosPromise}
 */
export function cancelFileAiGenerateTaskApi(id) {
  return request({
    url: `/setting/file/ai-generate/tasks/${id}/cancel`,
    method: 'post'
  })
}

/**
 * 重试 AI 生图任务
 * @param id
 * @returns {AxiosPromise}
 */
export function retryFileAiGenerateTaskApi(id) {
  return request({
    url: `/setting/file/ai-generate/tasks/${id}/retry`,
    method: 'post'
  })
}
