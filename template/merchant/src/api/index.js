import request from '@/utils/request'

/**
 * 获取统计数据
 * @param params
 * @returns {AxiosPromise}
 */
export function statisticsApi(params) {
  return request({
    url: '/statistics',
    method: 'get',
    params
  })
}

/**
 * 趋势图
 * @param params
 * @returns {*}
 */
export function turnoverApi(params){
  return request({
    url: '/turnover',
    method: 'get',
    params
  })
}
