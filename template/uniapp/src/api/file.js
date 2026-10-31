import { getRuntimeConfig } from '@/utils/runtime_config'
import { getToken, AUTH_MODES } from '@/utils/auth'
import { getTerminalType } from '@/utils/platform'
import { normalizePublicErrorMessage } from '@/utils/error'

const DEFAULT_TIMEOUT = 15000

/**
 * 拼接移动端上传接口地址。
 *
 * @param {string} url 上传接口路径，默认使用移动端文件上传接口。
 * @returns {string} 完整上传地址。
 */
function buildUploadUrl(url = 'file/upload') {
  const normalizedUrl = String(url || '').trim()
  if (/^(https?:)?\/\//i.test(normalizedUrl)) {
    return normalizedUrl
  }

  const runtimeConfig = getRuntimeConfig()
  const baseUrl = String(runtimeConfig.apiBaseUrl || '').replace(/\/+$/, '')
  const path = normalizedUrl.replace(/^\/+/, '')

  return `${baseUrl}/${path}`
}

/**
 * 生成上传请求头。
 *
 * @param {string} authMode 鉴权模式。
 * @returns {Object} 上传请求头。
 */
function buildUploadHeader(authMode = AUTH_MODES.REQUIRED) {
  const header = {
    // #ifdef MP
    'Content-Type': 'multipart/form-data',
    // #endif
    'X-Terminal-Type': getTerminalType()
  }
  const token = getToken()
  if (token && authMode !== AUTH_MODES.NONE) {
    header.Authorization = `Bearer ${token}`
  }

  return header
}

/**
 * 安全解析上传接口响应。
 *
 * @param {Object} response `uni.uploadFile` 原始响应。
 * @returns {Object} 解析后的响应体。
 */
function parseUploadBody(response = {}) {
  if (!response || response.data === undefined || response.data === null || response.data === '') {
    return {}
  }

  if (typeof response.data === 'object') {
    return response.data
  }

  try {
    return JSON.parse(response.data)
  } catch (error) {
    return {}
  }
}

/**
 * 规范化移动端上传响应。
 *
 * @param {Object} response `uni.uploadFile` 原始响应。
 * @returns {{code:number,msg:string,data:*,raw:Object}} 统一上传响应。
 */
function normalizeUploadResponse(response = {}) {
  const statusCode = Number(response.statusCode || 500)
  const body = parseUploadBody(response)
  const responseCode = body.code !== undefined && body.code !== '' ? body.code : body.status
  const code = responseCode !== undefined && responseCode !== '' ? Number(responseCode) : statusCode
  const responseMessage = body.msg !== undefined ? body.msg : body.message

  return {
    code: Number.isNaN(code) ? statusCode : code,
    msg: normalizePublicErrorMessage(responseMessage, statusCode === 200 ? '上传成功' : '上传失败'),
    data: body.data !== undefined ? body.data : null,
    raw: response
  }
}

/**
 * 展示上传错误提示。
 *
 * @param {string} message 错误文案。
 * @returns {void}
 */
function showUploadError(message = '') {
  uni.showToast({
    title: normalizePublicErrorMessage(message, '上传失败'),
    icon: 'none'
  })
}

/**
 * 上传移动端图片。
 *
 * 使用场景：个人资料头像、评价图片等移动端图片上传；需要登录。
 * 成功响应字段以移动端 `file/upload` 控制器为准：`data.url` 为图片访问地址。
 *
 * @param {string} filePath 待上传图片的本地临时路径。
 * @param {Object} options 上传配置。
 * @param {string} [options.name='file'] 文件表单字段名。
 * @param {Object} [options.formData] 额外表单参数。
 * @param {boolean} [options.showError=true] 是否自动展示错误提示。
 * @returns {Promise<Object>} 统一响应对象，成功时 `data.url` 为图片访问地址。
 */
export function uploadMobileImage(filePath = '', options = {}) {
  const normalizedFilePath = String(filePath || '').trim()
  const {
    name = 'file',
    formData = {},
    showError = true
  } = options

  if (!normalizedFilePath) {
    const result = {
      code: 400,
      msg: '请选择图片',
      data: null
    }
    if (showError) {
      showUploadError(result.msg)
    }
    return Promise.resolve(result)
  }

  if (!getToken()) {
    const result = {
      code: 401,
      msg: '请先登录',
      data: null
    }
    if (showError) {
      showUploadError(result.msg)
    }
    return Promise.resolve(result)
  }

  return new Promise((resolve) => {
    uni.uploadFile({
      url: buildUploadUrl('file/upload'),
      filePath: normalizedFilePath,
      fileType: 'image',
      name,
      formData: {
        fileName: name,
        ...formData
      },
      timeout: DEFAULT_TIMEOUT,
      header: buildUploadHeader(AUTH_MODES.REQUIRED),
      success(response) {
        const result = normalizeUploadResponse(response)
        if (showError && result.code !== 200) {
          showUploadError(result.msg)
        }
        resolve(result)
      },
      fail(error) {
        const result = {
          code: 500,
          msg: normalizePublicErrorMessage(error && error.errMsg, '头像上传失败'),
          data: null,
          error
        }
        if (showError) {
          showUploadError(result.msg)
        }
        resolve(result)
      }
    })
  })
}
