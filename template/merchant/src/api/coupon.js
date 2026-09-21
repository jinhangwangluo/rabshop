import request from '@/utils/request'

/**
 * 获取优惠券列表
 * @param params 查询参数
 * @returns {*}
 */
export function getCouponList(params) {
  return request({
    url: '/coupon',
    method: 'get',
    params
  })
}

/**
 * 获取优惠券详情
 * @param id
 * @returns {*}
 */
export function getCouponDetail(id) {
  return request({
    url: `/coupon/${id}`,
    method: 'get'
  })
}

/**
 * 创建优惠券
 * @param data
 * @returns {*}
 */
export function createCoupon(data) {
  return request({
    url: '/coupon',
    method: 'post',
    data
  })
}

/**
 * 更新优惠券
 * @param id
 * @param data
 * @returns {*}
 */
export function updateCoupon(id, data) {
  return request({
    url: `/coupon/${id}`,
    method: 'put',
    data
  })
}

/**
 * 删除优惠券
 * @param id
 * @returns {*}
 */
export function deleteCoupon(id) {
  return request({
    url: `/coupon/${id}`,
    method: 'delete'
  })
}

/**
 * 切换优惠券状态
 * @param id
 * @param status
 * @returns {*}
 */
export function setCouponStatus(id, status) {
  return request({
    url: `/coupon/status/${id}`,
    method: 'get',
    params: { status }
  })
}

/**
 * 获取适用范围类型
 * @returns {*}
 */
export function getCouponScopeTypes() {
  return request({
    url: '/coupon/scope_types',
    method: 'get'
  })
}

/**
 * 获取有效期类型
 * @returns {*}
 */
export function getCouponExpireTypes() {
  return request({
    url: '/coupon/expire_types',
    method: 'get'
  })
}

/**
 * 获取发放类型
 * @returns {*}
 */
export function getCouponDistributeTypes() {
  return request({
    url: '/coupon/distribute_types',
    method: 'get'
  })
}

/**
 * 获取关联子类型
 * @returns {*}
 */
export function getCouponRelationTypes() {
  return request({
    url: '/coupon/relation_types',
    method: 'get'
  })
}

/**
 * 获取用户优惠券记录列表
 * @param params
 * @returns {*}
 */
export function getCouponUserList(params) {
  return request({
    url: '/coupon/user',
    method: 'get',
    params
  })
}

/**
 * 获取领取状态枚举
 * @returns {*}
 */
export function getCouponUserStatusList() {
  return request({
    url: '/coupon/user/status_list',
    method: 'get'
  })
}

/**
 * 获取领取方式枚举
 * @returns {*}
 */
export function getCouponUserReceiveTypes() {
  return request({
    url: '/coupon/user/receive_types',
    method: 'get'
  })
}
