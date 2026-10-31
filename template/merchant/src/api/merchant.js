import request from '@/utils/request'

/**
 * 获取店铺信息
 * @return {AxiosPromise}
 */
export function getStoreInfoApi() {
  return request({
    url: '/store/info',
    method: 'get'
  })
}

/**
 * 更新店铺信息
 * @param data
 * @return {AxiosPromise}
 */
export function updateStoreInfoApi(data) {
  return request({
    url: '/store/info',
    method: 'put',
    data
  })
}

/**
 * 更新营业状态
 * @param data
 * @return {AxiosPromise}
 */
export function updateBusinessStatusApi(data) {
  return request({
    url: '/store/business_status',
    method: 'put',
    data
  })
}
