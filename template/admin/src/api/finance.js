import request from '@/utils/request'

// 获取用户提现列表
export function getUserExtractList(params) {
  return request({
    url: '/user_withdraw',
    method: 'get',
    params
  })
}

// 通过提现申请
export function passExtract(params) {
  return request({
    url: `/user_withdraw/${params.id}/audit`,
    method: 'put',
    data: { status: 1, remark: params.remark || '' }
  })
}

// 拒绝提现申请
export function rejectExtract(params) {
  return request({
    url: `/user_withdraw/${params.id}/audit`,
    method: 'put',
    data: { status: 2, remark: params.remark || '' }
  })
}

// 获取用户充值列表
export function getUserRechargeList(params) {
  return request({
    url: '/admin/finance/user_recharge/index',
    method: 'get',
    params
  })
}

// 获取充值订单详情
export function getRechargeOrderDetail(params) {
  return request({
    url: '/admin/finance/user_recharge/order_info',
    method: 'get',
    params
  })
}

// 获取用户充值订单详情
export function getUserRechargeOrderDetail(id) {
  return request({
    url: `/user_recharge_order/${id}`,
    method: 'get'
  })
}

// 获取财务账单列表
export function getBillList(params) {
  return request({
    url: '/admin/finance/finance/billlist',
    method: 'get',
    params
  })
}

// 导出财务账单
export function exportBillList(params) {
  return request({
    url: '/admin/finance/finance/save_bell_export',
    method: 'get',
    params,
    responseType: 'blob'
  })
}
