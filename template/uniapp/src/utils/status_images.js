import { resolveAssetUrl } from './runtime_config'

const STATUS_IMAGE_BASE_PATH = '/storage/status/images'

/**
 * 获取迁移到 public/storage/status/images 下的状态图片地址。
 *
 * @param {string} filename 图片文件名。
 * @returns {string} 当前运行域名下的状态图片完整地址。
 */
export function getStatusImageUrl(filename = '') {
  const normalizedName = String(filename || '').trim().replace(/^\/+/, '')
  if (!normalizedName) {
    return ''
  }

  return resolveAssetUrl(`${STATUS_IMAGE_BASE_PATH}/${normalizedName}`)
}

export default {
  getStatusImageUrl
}
