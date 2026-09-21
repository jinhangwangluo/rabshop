import request from '@/utils/request'

/** @returns {Promise<Object>} Public installed/enable state; no credentials required. */
export function getPluginRuntime() {
  return request({ url: '/plugins/runtime', method: 'get', timeout: 3000, silent: true, skipAuth: true })
}
