import request from '@/utils/request'

/**
 * 获取优惠券列表
 * @param params 查询参数：name, scope_type, distribute_type, status, create_time, page, page_size
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
 * 获取优惠券详情（含关联商品/品类数据）
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
 * 切换优惠券状态（启用/禁用）
 * @param id
 * @param status 0-禁用 1-启用
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
 * 获取签到奖励可选优惠券列表。
 * @param {Object} params 查询参数：name, scope_type, page, limit
 * @returns {*} 平台通用券和平台品类券分页列表
 */
export function getSigninRewardCouponOptions(params) {
  return request({
    url: '/coupon/signin_reward_options',
    method: 'get',
    params
  })
}

/**
 * 获取适用范围类型选项
 * @returns {*}
 */
export function getCouponScopeTypes() {
  return request({
    url: '/coupon/scope_types',
    method: 'get'
  })
}

/**
 * 获取有效期类型选项
 * @returns {*}
 */
export function getCouponExpireTypes() {
  return request({
    url: '/coupon/expire_types',
    method: 'get'
  })
}

/**
 * 获取发放类型选项
 * @returns {*}
 */
export function getCouponDistributeTypes() {
  return request({
    url: '/coupon/distribute_types',
    method: 'get'
  })
}

/**
 * 获取用户领取记录列表
 * @param params 查询参数：coupon_id, user_id, receive_type, status, create_time, page, page_size
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
 * 获取关联子类型选项（按适用范围分组）
 * @returns {*}
 */
export function getCouponRelationTypes() {
  return request({
    url: '/coupon/relation_types',
    method: 'get'
  })
}
