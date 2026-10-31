import request from '@/utils/request'

/**
 * 获取商户资金概览。
 *
 * @returns {Promise} 商户登录后可访问，返回余额、冻结金额、累计收入等统计。
 */
export function getMerchantFinanceOverview() {
  return request({
    url: '/finance/overview',
    method: 'get'
  })
}

/**
 * 获取商户资金流水。
 *
 * @param {Object} params 查询参数。
 * @param {string=} params.type 流水类型。
 * @param {string=} params.change_type 收支方向：in 或 out。
 * @param {string=} params.fund_status 资金状态：available、frozen、reversed。
 * @param {string=} params.start_time 开始日期，格式 yyyy-MM-dd。
 * @param {string=} params.end_time 结束日期，格式 yyyy-MM-dd。
 * @param {number=} params.page 页码。
 * @param {number=} params.limit 每页条数。
 * @returns {Promise} 商户登录后可访问，返回分页流水。
 */
export function getMerchantFinanceRecords(params) {
  return request({
    url: '/finance/records',
    method: 'get',
    params
  })
}

/**
 * 提交商户提现申请。
 *
 * @param {Object} data 提现申请数据。
 * @param {string} data.withdraw_type 提现方式：bank、wechat、alipay。
 * @param {number|string} data.amount 提现金额。
 * @param {string=} data.bank_name 银行名称，银行卡提现必填。
 * @param {string=} data.bank_account 银行卡号，银行卡提现必填。
 * @param {string} data.account_name 收款人姓名。
 * @param {string=} data.payment_qrcode 微信/支付宝收款码图片。
 * @returns {Promise} 商户登录后可访问，返回提现申请记录。
 */
export function submitMerchantWithdraw(data) {
  return request({
    url: '/finance/withdraw',
    method: 'post',
    data
  })
}

/**
 * 获取商户提现申请记录。
 *
 * @param {Object} params 查询参数。
 * @param {number|string=} params.status 审核状态：0、1、2。
 * @param {string=} params.withdraw_type 提现方式。
 * @param {string=} params.start_time 开始日期，格式 yyyy-MM-dd。
 * @param {string=} params.end_time 结束日期，格式 yyyy-MM-dd。
 * @param {number=} params.page 页码。
 * @param {number=} params.limit 每页条数。
 * @returns {Promise} 商户登录后可访问，返回分页提现记录。
 */
export function getMerchantWithdrawRecords(params) {
  return request({
    url: '/finance/withdraw_records',
    method: 'get',
    params
  })
}
