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
 *  删除文件
 * @param id
 * @returns {AxiosPromise}
 */
export function deleteFileApi(id) {
  return request({
    url: '/setting/file/delete',
    method: 'delete',
    params: { id }
  })
}

/**
 * 更新文件
 * @param id
 * @param data
 * @returns {AxiosPromise}
 */
export function fileUpdateApi(id, data) {
  return request({
    url: `/setting/file/${id}`,
    method: 'put',
    params: data
  })
}

/**
 * 文件上传
 * @param data
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
