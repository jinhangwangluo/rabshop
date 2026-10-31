import request from '@/utils/request'

/**
 * 获取上传配置（上传类型及云存储签名）
 * 返回示例：{ type: 'local'|'cos'|'oss'|'qiniu', chunk_url: '...', cos: {...}, oss: {...}, qiniu: {...} }
 */
export function getUploadConfig() {
  return request({
    url: '/setting/file/upload_config',
    method: 'get'
  })
}

/**
 * 本地分片上传接口
 * @param {FormData} data - chunk_data, file_md5, chunk_index, total_chunks, file_name, file_ext
 */
export function chunkUpload(data) {
  return request({
    url: '/setting/file/chunk_upload',
    method: 'post',
    data,
    timeout: 60000
  })
}

/**
 * @description 上传（旧接口，保留兼容）
 * @param {Object} param data {Object} 传値参数
 */
export function upload(data, config) {
  return request({
    url: '/setting/file/video_upload',
    method: 'post',
    file: true,
    data
  })
}
