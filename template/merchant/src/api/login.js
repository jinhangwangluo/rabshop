import request from '@/utils/request'

/**
 * 登录验证
 * @param data 登录数据
 * @return {AxiosPromise}
 */
export function login(data) {
  return request({
    url: '/login',
    method: 'post',
    data
  })
}

/**
 * 退出登录
 * @return {AxiosPromise}
 */
export function logout() {
  return request({
    url: '/auth/logout',
    method: 'post'
  })
}

/**
 * 获取用户信息
 * @return {AxiosPromise}
 */
export function getInfo() {
  return request({
    url: '/login/getInfo',
    method: 'get'
  })
}

/**
 * 主动刷新 token
 * @return {AxiosPromise}
 */
export function refreshTokenApi() {
  return request({
    url: '/auth/refresh_token',
    method: 'post'
  })
}

/**
 * 获取版权信息
 * @return {AxiosPromise}
 */
export function getCopyright() {
  return request({
    url: '/login/get_copyright',
    method: 'get'
  })
}

/**
 * 获取站点信息（含版权、备案号等）
 * @return {AxiosPromise}
 */
export function getSiteInfo() {
  return request({
    url: '/site_info',
    method: 'get'
  })
}
