import { get, post } from './request'
import { AUTH_MODES } from '@/utils/auth'

const OPTIONS = {
  authMode: AUTH_MODES.REQUIRED
}

/**
 * 获取用户余额及月度统计数据。
 *
 * 返回 `{ balance, month_recharge, month_consume, total_income }`。
 *
 * @param {Object} params  查询参数，通常留空。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>}
 */
export function getBalanceStatistics(params = {}, options = {}) {
  return get('user/asset/statistics', params, {
    ...OPTIONS,
    ...options
  })
}

/**
 * 获取用户余额变动记录列表。
 *
 * 返回 `{ list, total, page, limit }`。
 * 每条记录含 `id, amount, change_type, remark, source_type, order_no, create_time` 等字段。
 *
 * @param {Object} params  查询参数，可选 `change_type`(in/out)、`page`、`limit`。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>}
 */
export function getBalanceLogs(params = {}, options = {}) {
  return get('user/asset/logs', params, {
    ...OPTIONS,
    ...options
  })
}

/**
 * 获取用户积分及月度统计数据。
 *
 * 返回 `{ points, month_income, month_expense, total_income }`。
 * 需要登录，积分页进入和下拉刷新时使用。
 *
 * @param {Object} params 查询参数，内部固定传 `type=points`。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时返回统一响应对象。
 */
export function getPointsStatistics(params = {}, options = {}) {
  return get('user/asset/statistics', {
    ...params,
    type: 'points'
  }, {
    ...OPTIONS,
    ...options
  })
}

/**
 * 获取用户积分变动记录列表。
 *
 * 返回 `{ list, total, page, limit }`。
 * 需要登录，支持分页和 `change_type` 收支筛选。
 *
 * @param {Object} params 查询参数，可选 `change_type`(in/out)、`page`、`limit`。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时返回统一响应对象。
 */
export function getPointsLogs(params = {}, options = {}) {
  return get('user/asset/logs', {
    ...params,
    type: 'points'
  }, {
    ...OPTIONS,
    ...options
  })
}

/**
 * 获取充值页初始化数据。
 *
 * @param {Object} params 查询参数，通常无需传参；终端类型由 `X-Terminal-Type` 请求头传递。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时返回 amounts、balance、coins、coin_balance、coin_exchange_rate 等充值配置。
 */
export function getRechargeOptions(params = {}, options = {}) {
  return get('user/recharge/options', params, {
    ...OPTIONS,
    ...options
  })
}

/**
 * 创建充值单并发起支付。
 *
 * @param {Object} data 充值参数。
 * @param {number|string} data.amount 充值金额；余额充值表示支付金额。
 * @param {number|string} [data.coin_amount] 直播金币充值数量；`order_type=live_coin` 时后端按兑换比例重新计算支付金额。
 * @param {string} data.pay_type 支付方式：wechat/alipay；`order_type=live_coin` 时额外支持 balance/yue。
 * @param {string} [data.order_type] 订单类型：recharge/live_coin。live_coin 表示直播金币充值。
 * @param {string} [data.trade_type] 微信交易类型。
 * @param {string} [data.return_url] H5 支付回跳地址。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 支付发起结果；金币充值返回 order_type、coin_amount、exchange_rate。
 */
export function createRechargeOrder(data = {}, options = {}) {
  return post('user/recharge/create', data, {
    ...OPTIONS,
    ...options
  })
}

/**
 * 获取充值单详情。
 *
 * @param {Object} params 查询参数，需包含 order_sn。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 充值单状态；金币充值返回 order_type、coin_amount、exchange_rate。
 */
export function getRechargeDetail(params = {}, options = {}) {
  return get('user/recharge/detail', params, {
    ...OPTIONS,
    ...options
  })
}
