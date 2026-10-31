import request from '@/utils/request'

/**
 * 获取商户列表
 */
export function getMerchantList(params) {
  return request({ url: '/merchant', method: 'get', params })
}

/**
 * 获取商户详情
 */
export function merchantRead(id) {
  return request({ url: `/merchant/${id}`, method: 'get' })
}

/**
 * 新增商户
 */
export function merchantSave(data) {
  return request({ url: '/merchant', method: 'post', data })
}

/**
 * 更新商户
 */
export function merchantUpdate(id, data) {
  return request({ url: `/merchant/${id}`, method: 'put', data })
}

/**
 * 更新商户平台抽佣比例
 */
export function updateMerchantCommissionRate(id, commissionRate) {
  return request({ url: `/merchant/${id}/commission_rate`, method: 'put', data: { commission_rate: commissionRate }})
}

/**
 * 删除商户
 */
export function merchantDelete(id) {
  return request({ url: `/merchant/${id}`, method: 'delete' })
}

/**
 * 切换商户启用/禁用状态
 */
export function merchantSetStatus(id, status) {
  return request({
    url: `/merchant/${id}/status/${status}`,
    method: 'get',
    params: { status }
  })
}

/**
 * 一键登录商户后台（生成商户 token 与后台配置的商户端登录地址）
 */
export function merchantLoginAs(id) {
  return request({ url: `/merchant/${id}/login_as`, method: 'post' })
}

/**
 * 修改商户管理员密码
 */
export function merchantChangePassword(id, data) {
  return request({ url: `/merchant/${id}/change_password`, method: 'post', data })
}

/**
 * 获取商户提现列表
 */
export function getWithdrawList(params) {
  return request({ url: '/merchant_withdraw', method: 'get', params })
}

/**
 * 审核商户提现
 */
export function auditWithdraw(id, data) {
  return request({ url: `/merchant_withdraw/${id}/audit`, method: 'put', data })
}

/**
 * 获取商户入驻申请列表
 */
export function getApplyList(params) {
  return request({ url: '/merchant_apply', method: 'get', params })
}

/**
 * 获取商户入驻申请详情
 */
export function merchantApplyRead(id) {
  return request({ url: `/merchant_apply/${id}`, method: 'get' })
}

/**
 * 审核商户入驻申请
 */
export function auditApply(id, data) {
  return request({ url: `/merchant_apply/${id}`, method: 'put', data })
}
