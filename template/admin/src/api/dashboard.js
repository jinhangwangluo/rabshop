import request from '@/utils/request'

/**
 * 获取总平台首页统计概览。
 *
 * @param {Object} params 查询参数。
 * @param {string} params.range 时间范围：7day/30day/month/year/custom。
 * @param {string} params.start_time 自定义开始日期。
 * @param {string} params.end_time 自定义结束日期。
 * @returns {AxiosPromise} 成功时返回统一响应对象。
 */
export function getPlatformDashboardOverview(params) {
  return request({
    url: '/dashboard/overview',
    method: 'get',
    params
  })
}

/**
 * 获取总平台首页实时预警摘要。
 *
 * @returns {AxiosPromise} 成功时返回首页底部预警区轮询数据。
 */
export function getPlatformDashboardPending() {
  return request({
    url: '/dashboard/pending',
    method: 'get'
  })
}

export const getFirstLineData = getPlatformDashboardOverview
export const getUserChart = getPlatformDashboardOverview
export const getOrderChart = getPlatformDashboardOverview
