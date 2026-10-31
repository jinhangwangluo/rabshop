

import { getToken } from '@/utils/auth'
import { normalizePublicErrorMessage } from '@/utils/error'
import { getTerminalType } from '@/utils/platform'
import { getRuntimeConfig } from '@/utils/runtime_config'

const MAX_COMPRESS_SIZE = 2 * 1024 * 1024
const COMPRESS_QUALITY = 80

/**
 * 统一提示上传相关错误。
 *
 * @param {string} title 提示文案。
 */
function showUploadToast(title = '') {
  const normalizedTitle = normalizePublicErrorMessage(title, '')
  if (!normalizedTitle) {
    return
  }
  uni.showToast({
    title: normalizedTitle,
    icon: 'none',
    duration: 2000
  })
}

/**
 * 统一整理上传参数。
 *
 * @param {Object|string} opt 上传配置或上传地址。
 * @returns {Object} 规范化后的上传配置。
 */
function normalizeUploadOptions(opt) {
  const options = typeof opt === 'string' ? {
    url: opt
  } : (opt || {})

  return {
    count: options.count || 1,
    sizeType: options.sizeType || ['compressed'],
    sourceType: options.sourceType || ['album', 'camera'],
    isLoad: options.is_load !== false,
    uploadUrl: options.url || '',
    inputName: options.name || 'pics',
    fileType: options.fileType || 'image'
  }
}

/**
 * 生成完整上传地址。
 *
 * @param {string} uploadUrl 业务上传路径。
 * @returns {string} 完整接口地址。
 */
function buildUploadUrl(uploadUrl) {
  const normalizedUrl = String(uploadUrl || '').trim()
  if (/^(https?:)?\/\//i.test(normalizedUrl)) {
    return normalizedUrl
  }

  const runtimeConfig = getRuntimeConfig()
  const normalizedPath = normalizedUrl.replace(/^\/+/, '')
  const baseUrl = normalizedUrl.startsWith('/api/')
    ? String(runtimeConfig.origin || '').replace(/\/+$/, '')
    : String(runtimeConfig.apiBaseUrl || '').replace(/\/+$/, '')

  return `${baseUrl}/${normalizedPath}`
}

/**
 * 生成上传请求头。
 *
 * @returns {Object} 上传请求头。
 */
function buildUploadHeader() {
  const token = getToken()
  const header = {
    // #ifdef MP
    'Content-Type': 'multipart/form-data',
    // #endif
    'X-Terminal-Type': getTerminalType()
  }
  if (token) {
    header.Authorization = `Bearer ${token}`
  }

  return header
}

/**
 * 判断是否为取消选择图片。
 *
 * @param {Object} error chooseImage 错误对象。
 * @returns {boolean} 是否为取消操作。
 */
function isChooseCancel(error) {
  const message = error && error.errMsg ? error.errMsg : ''
  return /cancel/i.test(message)
}

/**
 * 安全解析上传响应。
 *
 * @param {Object} response 上传接口原始响应。
 * @returns {Object} 解析后的响应对象。
 */
function parseUploadResponse(response) {
  if (!response || response.data === undefined || response.data === null || response.data === '') {
    return {}
  }
  if (typeof response.data === 'object') {
    return response.data
  }

  try {
    return JSON.parse(response.data)
  } catch (error) {
    return {
      status: 0,
      msg: '上传响应解析失败',
      raw: response.data
    }
  }
}

/**
 * 统一发起单张图片上传。
 *
 * @param {string} filePath 待上传文件路径。
 * @param {Object} options 规范化后的上传配置。
 * @returns {Promise<Object>} 上传成功后的业务响应。
 */
function uploadSingleFile(filePath, options) {
  return new Promise((resolve, reject) => {
    uni.uploadFile({
      url: buildUploadUrl(options.uploadUrl),
      filePath,
      fileType: options.fileType,
      name: options.inputName,
      formData: {
        filename: options.inputName
      },
      header: buildUploadHeader(),
      success(res) {
        if (res.statusCode === 403) {
          reject({
            message: res.data || '无权限上传',
            data: res
          })
          return
        }

        if (res.statusCode === 413) {
          reject({
            message: '上传图片失败,请重新上传小尺寸图片',
            data: res
          })
          return
        }

        const data = parseUploadResponse(res)
        const code = Number(data.code !== undefined ? data.code : data.status)
        if (code === 200) {
          resolve(data)
          return
        }

        reject({
          message: data.msg || '上传图片失败',
          data
        })
      },
      fail(error) {
        reject({
          message: '上传图片失败',
          data: error
        })
      }
    })
  })
}

/**
 * 选择图片。
 *
 * @param {Object} options 规范化后的上传配置。
 * @returns {Promise<Object>} 选择图片结果。
 */
function chooseImages(options) {
  return new Promise((resolve, reject) => {
    uni.chooseImage({
      count: options.count,
      sizeType: options.sizeType,
      sourceType: options.sourceType,
      success: resolve,
      fail: reject
    })
  })
}

/**
 * 顺序执行多图上传，保证回调顺序稳定。
 *
 * @param {Array<string>} filePaths 待处理图片路径列表。
 * @param {Array<Object>} tempFiles 选择图片时返回的文件信息列表。
 * @param {Object} options 规范化后的上传配置。
 * @param {Function} successCallback 单张上传成功回调。
 * @param {Function} errorCallback 单张上传失败回调。
 * @param {Function|null} beforeUpload 上传前处理函数。
 * @returns {Promise<void>} 全部处理完成。
 */
async function uploadFileList(filePaths, tempFiles, options, successCallback, errorCallback, beforeUpload = null) {
  for (let index = 0; index < filePaths.length; index++) {
    const filePath = filePaths[index]
    const fileInfo = tempFiles[index] || {}

    try {
      const finalFilePath = beforeUpload ? await beforeUpload(filePath, fileInfo, index) : filePath
      const data = await uploadSingleFile(finalFilePath, options)
      successCallback && successCallback(data)
    } catch (error) {
      errorCallback && errorCallback(error.data !== undefined ? error.data : error)
      showUploadToast(error.message || '上传图片失败')
    }
  }
}

/**
 * 获取图片信息。
 *
 * @param {string} filePath 图片路径。
 * @returns {Promise<Object>} 图片信息。
 */
function getImageInfo(filePath) {
  return new Promise((resolve, reject) => {
    uni.getImageInfo({
      src: filePath,
      success: resolve,
      fail: reject
    })
  })
}

/**
 * 计算压缩参考尺寸，用于回调告知当前图片宽高。
 *
 * @param {number} width 原图宽度。
 * @param {number} height 原图高度。
 * @param {number} maxWidth 最大参考宽度。
 * @returns {Object} 参考宽高。
 */
function getCompressSize(width, height, maxWidth = 750) {
  if (!width || !height) {
    return {
      w: width || 0,
      h: height || 0
    }
  }

  if (width <= maxWidth) {
    return {
      w: width,
      h: height
    }
  }

  return {
    w: maxWidth,
    h: Math.round(maxWidth / (width / height))
  }
}

/**
 * 压缩大图；若压缩不可用或失败则回退原图，保证上传流程不中断。
 *
 * @param {string} filePath 原始图片路径。
 * @param {Object} fileInfo 选择图片时返回的文件信息。
 * @param {number} index 当前图片序号。
 * @param {Function} sizeCallback 压缩尺寸回调。
 * @returns {Promise<string>} 最终用于上传的图片路径。
 */
async function compressImageIfNeeded(filePath, fileInfo, index, sizeCallback) {
  const originalSize = Number(fileInfo.size || 0)
  if (originalSize > 0 && originalSize <= MAX_COMPRESS_SIZE) {
    return filePath
  }

  let imageInfo = null
  try {
    imageInfo = await getImageInfo(filePath)
    const size = getCompressSize(imageInfo.width, imageInfo.height)
    sizeCallback && sizeCallback({
      ...size,
      index,
      filePath
    })
  } catch (error) {
    imageInfo = null
  }

  if (typeof uni.compressImage !== 'function') {
    return filePath
  }

  return new Promise((resolve) => {
    uni.compressImage({
      src: filePath,
      quality: COMPRESS_QUALITY,
      success(res) {
        resolve(res.tempFilePath || filePath)
      },
      fail() {
        resolve(filePath)
      }
    })
  })
}

/**
 * 统一执行带 loading 的图片处理流程。
 *
 * @param {boolean} isLoad 是否展示 loading。
 * @param {Function} runner 具体执行函数。
 * @returns {Promise<*>} 执行结果。
 */
async function runWithLoading(isLoad, runner) {
  if (isLoad) {
    uni.showLoading({
      title: '图片上传中',
      mask: true
    })
  }

  try {
    return await runner()
  } finally {
    if (isLoad) {
      uni.hideLoading()
    }
  }
}

export default {
  /**
   * 普通图片上传，支持一次选择多张图片并逐张回调。
   *
   * @param {Object|string} opt 上传配置或上传地址。
   * @param {Function} successCallback 单张上传成功回调，逐张触发。
   * @param {Function} errorCallback 单张上传失败回调。
   * @returns {Promise<void>} 上传流程 Promise。
   */
  uploadImageOne(opt, successCallback, errorCallback) {
    const options = normalizeUploadOptions(opt)

    return chooseImages(options)
      .then((res) => runWithLoading(options.isLoad, async () => {
        const filePaths = res.tempFilePaths || []
        const tempFiles = res.tempFiles || []
        await uploadFileList(filePaths, tempFiles, options, successCallback, errorCallback)
      }))
      .catch((error) => {
        if (!isChooseCancel(error)) {
          errorCallback && errorCallback(error)
        }
      })
  },

  /**
   * 压缩上传图片，支持多图串行压缩并逐张上传回调。
   *
   * @param {Object|string} opt 上传配置或上传地址。
   * @param {Function} successCallback 单张上传成功回调，逐张触发。
   * @param {Function} errorCallback 单张上传失败回调。
   * @param {Function} sizeCallback 当前图片压缩参考尺寸回调。
   * @returns {Promise<void>} 上传流程 Promise。
   */
  uploadImageChange(opt, successCallback, errorCallback, sizeCallback) {
    const options = normalizeUploadOptions(opt)

    return chooseImages(options)
      .then((res) => runWithLoading(options.isLoad, async () => {
        const filePaths = res.tempFilePaths || []
        const tempFiles = res.tempFiles || []

        await uploadFileList(
          filePaths,
          tempFiles,
          options,
          successCallback,
          errorCallback,
          (filePath, fileInfo, index) => compressImageIfNeeded(filePath, fileInfo, index, sizeCallback)
        )
      }))
      .catch((error) => {
        if (!isChooseCancel(error)) {
          errorCallback && errorCallback(error)
        }
      })
  }
}
