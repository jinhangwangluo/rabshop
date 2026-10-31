import request from '@/utils/request'

/**
 * 获取上传存储与水印配置。
 * @returns {AxiosPromise}
 */
export function getUploaderWatermarkConfigApi() {
  return request({
    url: '/setting/uploader_watermark',
    method: 'get'
  })
}

/**
 * 保存上传存储与水印配置。
 * @param {Object} data 配置表单数据。
 * @returns {AxiosPromise}
 */
export function saveUploaderWatermarkConfigApi(data) {
  return request({
    url: '/setting/uploader_watermark',
    method: 'post',
    data
  })
}
