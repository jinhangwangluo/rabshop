import request from '@/utils/request'

export function login(data) {
  return request({
    url: '/login',
    method: 'post',
    data
  })
}

export function getInfo() {
  return request({
    url: '/system/admin/info',
    method: 'get'
  })
}

export function logout() {
  return request({
    url: '/system/admin/logout',
    method: 'post'
  })
}

/**
 * 获取用户列表
 * @param params
 * @return {AxiosPromise}
 */
export function getUserList(params) {
  return request({
    url: '/admin/user/user/get_user_list',
    method: 'get',
    params
  })
}

/**
 * 设置用户状态
 * @param params
 * @return {AxiosPromise}
 */
export function setUserStatus(params) {
  return request({
    url: '/admin/user/user/set_status',
    method: 'get',
    params
  })
}

/**
 * 清除会员身份
 * @param params
 * @return {AxiosPromise}
 */
export function clearMember(params) {
  return request({
    url: '/admin/user/user/del_vip',
    method: 'get',
    params
  })
}

/**
 * 获取用户详情
 * @param params
 */
export function getUserInfo(params) {
  return request({
    url: '/admin/user/user/get_user_info',
    method: 'get',
    params
  })
}

/**
 * 获取会员记录
 * @param params
 */
export function getMemberRecord(params) {
  return request({
    url: '/admin/user/get_member_record',
    method: 'get',
    params
  })
}

/**
 * 获取支付记录 (我的课程)
 * @param params
 */
export function getPayList(params) {
  return request({
    url: '/admin/user/user/get_pay_list',
    method: 'get',
    params
  })
}

/**
 * 获取佣金记录
 * @param params
 */
export function getBillList(params) {
  return request({
    url: '/admin/user/user/get_bill_list',
    method: 'get',
    params
  })
}

/**
 * 获取直推订单
 * @param params
 */
export function getOrderList(params) {
  return request({
    url: '/admin/user/user/get_order_list',
    method: 'get',
    params
  })
}

/**
 * 获取下级列表
 * @param params
 */
export function getSpreadList(params) {
  return request({
    url: '/admin/user/user/get_spread_list',
    method: 'get',
    params
  })
}

/**
 * 获取用户发票
 * @param params
 * @return {AxiosPromise}
 */
export function getInvoiceListApi(params) {
  return request({
    url: '/user/invoice',
    method: 'get',
    params
  })
}

/**
 * 获取发票信息
 * @param id
 * @return {AxiosPromise}
 */
export function getInvoiceInfoApi(id) {
  return request({
    url: '/user/invoice/' + id,
    method: 'get'
  })
}

/**
 * 获取在线开票uri
 * @param id
 * @return {AxiosPromise}
 */
export function getInvoiceUriApi(id) {
  return request({
    url: '/user/invoice/' + id + '/uri',
    method: 'get'
  })
}

/**
 * 审核发票
 * @param id
 * @param data
 * @return {AxiosPromise}
 */
export function putInvoiceApi(id, data) {
  return request({
    url: '/user/invoice/' + id,
    method: 'put',
    data
  })
}

/**
 * 在线开票修改发票信息
 * @param id
 * @param data
 * @return {AxiosPromise}
 */
export function putInvoiceUpdateApi(id, data) {
  return request({
    url: '/user/invoice/' + id + '/update',
    method: 'post',
    data
  })
}

/**
 * 修改用户状态
 * @param id
 * @param status
 * @return {AxiosPromise}
 */
export function putUserStatusApi(id, status) {
  return request({
    url: `/user/${id}/${status}`,
    method: 'put'
  })
}

/**
 *
 * @param id
 * @return {AxiosPromise}
 */
export function amountUpdateCreateApi(id) {
  return request({
    url: `/user/${id}/create`,
    method: 'get'
  })
}

export function getUserEdit(params) {
  return request({
    url: '/admin/user/user/edit',
    method: 'get',
    params
  })
}

export function getMemberCardCreate(params) {
  return request({
    url: '/user/member_card/create',
    method: 'get',
    params
  })
}

export function getMemberShipCreate(params) {
  return request({
    url: '/user/member_ship/create',
    method: 'get',
    params
  })
}

export function getBatchList(params) {
  return request({
    url: '/user/member_card/batch_list',
    method: 'get',
    params
  })
}

export function getCardList(params) {
  return request({
    url: '/user/member_card/card_list',
    method: 'get',
    params
  })
}

export function setMemberCardValue(params) {
  return request({
    url: '/user/member_card/set_value',
    method: 'get',
    params
  })
}

export function deleteBatch(params) {
  return request({
    url: '/user/member_card/delete',
    method: 'get',
    params
  })
}

export function getMemberShipList(params) {
  return request({
    url: '/admin/user/membership_vip_list',
    method: 'get',
    params
  })
}

export function setMemberShipStatus(params) {
  return request({
    url: '/admin/user/set_publish',
    method: 'get',
    params
  })
}

export function getUserNoticeList(params) {
  return request({
    url: '/admin/user/user_notice/index',
    method: 'get',
    params
  })
}

export function sendUserNotice(params) {
  return request({
    url: '/admin/user/user_notice/send',
    method: 'get',
    params
  })
}

export function deleteUserNotice(params) {
  return request({
    url: '/admin/user/user_notice/delete',
    method: 'get',
    params
  })
}

export function getUserSignList(params) {
  return request({
    url: '/admin/user/user_sign/getUserSignList',
    method: 'get',
    params
  })
}

export function getSpreadBadge(data) {
  return request({
    url: '/admin/user/user_spread/get_badge_list',
    method: 'post',
    data
  })
}

export function lookCode(params) {
  return request({
    url: '/admin/user/user_spread/look_code',
    method: 'get',
    params
  })
}

export function deleteSpread(params) {
  return request({
    url: '/admin/user/user_spread/empty_spread',
    method: 'get',
    params
  })
}

export function getMemberRecordList(params) {
  return request({
    url: '/admin/user/member_record_list',
    method: 'get',
    params
  })
}

export function getSignPosterList(params) {
  return request({
    url: '/admin/user/getSignPosterList',
    method: 'get',
    params
  })
}

export function deleteUserCommon(params) {
  return request({
    url: '/admin/user/delete',
    method: 'get',
    params
  })
}

export function getUserListApi(){

}
