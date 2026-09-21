import request from '@/utils/request'

export function getUserListApi(params) {
  return request({ url: '/user', method: 'get', params })
}

/**
 * 获取用户详情。
 *
 * @param {number|string} id 用户 UID。
 * @returns {Promise}
 */
export function getUserDetail(id) {
  return request({ url: `/user/${id}`, method: 'get' })
}

export function setUserStatus(id, status) {
  return request({ url: `/user/${id}/status`, method: 'put', params: { status }})
}

/**
 * 设置用户分销权限覆盖。
 *
 * @param {Object} data 请求参数
 * @param {number} data.user_id 用户 UID
 * @param {number} data.distribution_permission 分销权限：-1跟随模式，0禁止，1指定允许
 * @returns {Promise}
 */
export function setUserDistribution(data) {
  return request({ url: '/user/set_distribution', method: 'post', data })
}

export function createUser(data) {
  return request({ url: '/user', method: 'post', data })
}

export function getAssetByUser(userId) {
  return request({ url: `/user/user_asset/by_user/${userId}`, method: 'get' })
}

export function changeUserAsset(data) {
  return request({ url: '/user/user_asset/change', method: 'post', data })
}

export function getUserAssetLogs(userId, params) {
  return request({ url: `/user/user_asset_log/user/${userId}`, method: 'get', params })
}

export function getSubordinates(userId, params) {
  return request({ url: `/user/${userId}/subordinates`, method: 'get', params })
}

export function searchUsers(params) {
  return request({ url: '/user', method: 'get', params })
}

/**
 * 更新用户基本信息，支持只传入需要修改的单个字段。
 *
 * @param {number} id 用户 UID
 * @param {Object} data 基本信息字段
 * @param {string} [data.nickname] 昵称
 * @param {number} [data.sex] 性别 0未知 1男 2女
 * @param {string} [data.birthday] 生日
 * @param {number} [data.age] 年龄
 * @param {string} [data.education] 学历
 * @param {string} [data.occupation] 职业
 * @param {string} [data.interests] 兴趣
 * @param {string} [data.hobbies] 爱好
 * @param {string} [data.address] 地址
 * @returns {Promise}
 */
export function updateUserBasic(id, data) {
  return request({ url: `/user/${id}/basic`, method: 'put', data })
}

/**
 * 修改用户手机号。
 *
 * @param {number} id 用户 UID
 * @param {Object} data { phone: '手机号' }
 * @returns {Promise}
 */
export function updateUserPhone(id, data) {
  return request({ url: `/user/${id}/phone`, method: 'put', data })
}

/**
 * 修改用户密码。
 *
 * @param {number} id 用户 UID
 * @param {Object} data { password: '新密码', confirm_password: '确认密码' }
 * @returns {Promise}
 */
export function updateUserPassword(id, data) {
  return request({ url: `/user/${id}/password`, method: 'put', data })
}
