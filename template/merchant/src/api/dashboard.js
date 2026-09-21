import request from '@/utils/request'

/**
 * 获取商户首页聚合数据。
 *
 * @param {Object} params 查询参数。
 * @param {number|string=} params.range 趋势范围：7 或 30，默认 7。
 * @param {string=} params.start_time 自定义开始日期，格式 yyyy-MM-dd。
 * @param {string=} params.end_time 自定义结束日期，格式 yyyy-MM-dd。
 * @returns {Promise} 商户首页首屏数据，需商户登录。
 */
export function getMerchantDashboardHome(params) {
  return request({
    url: '/dashboard/home',
    method: 'get',
    params
  })
}
