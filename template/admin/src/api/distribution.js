import request from '@/utils/request'

const PREFIX = '/distribution'

/**
 * 获取后台分销员列表。
 *
 * @param {Object} params 查询参数
 * @param {number} params.page 页码
 * @param {number} params.limit 每页条数
 * @param {string} [params.keyword] UID/昵称/手机号关键词
 * @param {number|string} [params.status] 用户状态
 * @param {string} [params.qualification] qualified/unqualified
 * @returns {Promise}
 */
export function getDistributionUserList(params) {
  return request({
    url: `${PREFIX}/user`,
    method: 'get',
    params
  })
}

/**
 * 增加或取消分销资格。
 *
 * @param {Object} data 请求参数
 * @param {number} data.user_id 用户 UID
 * @param {number} data.distribution_permission 分销权限：0取消资格，1增加资格
 * @returns {Promise}
 */
export function setDistributionUserPermission(data) {
  return request({
    url: `${PREFIX}/user/set_permission`,
    method: 'post',
    data
  })
}

/**
 * 获取分销员产生返佣的订单明细。
 *
 * @param {Object} params 查询参数
 * @param {number} params.user_id 分销员 UID
 * @param {number} params.level 佣金层级：1一级，2二级
 * @param {number} params.page 页码
 * @param {number} params.limit 每页条数
 * @param {string} [params.order_sn] 订单号
 * @param {string} [params.buyer_keyword] 买家 UID/昵称/手机号
 * @param {string} [params.start_time] 开始日期
 * @param {string} [params.end_time] 结束日期
 * @returns {Promise}
 */
export function getDistributionUserOrders(params) {
  return request({
    url: `${PREFIX}/user/orders`,
    method: 'get',
    params
  })
}
