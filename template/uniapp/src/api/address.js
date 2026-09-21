import { get, post } from './request'
import { AUTH_MODES } from '@/utils/auth'

const ADDRESS_REQUEST_OPTIONS = {
  authMode: AUTH_MODES.REQUIRED
}

/**
 * 获取 bubble-address 组件需要的省市区地址数据。
 *
 * @param {Object} params 查询参数，通常留空。
 * @param {Object} options 透传给请求封装的附加选项；该接口无需登录。
 * @returns {Promise<Object>} 成功时 data 包含 provinceList、cityList、areaList。
 */
export function getAddressCityArea(params = {}, options = {}) {
  return get('address/city_area', params, {
    authMode: AUTH_MODES.NONE,
    ...options
  })
}

/**
 * 获取当前登录用户收货地址列表。
 *
 * @param {Object} params 查询参数，通常留空。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 data.list 为地址列表，data.default_address 为默认地址。
 */
export function getAddressList(params = {}, options = {}) {
  return get('user/address/list', params, {
    ...ADDRESS_REQUEST_OPTIONS,
    ...options
  })
}

/**
 * 获取当前登录用户指定收货地址详情。
 *
 * @param {Object} params 查询参数。
 * @param {number|string} params.id 地址 ID。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 data 为地址详情。
 */
export function getAddressDetail(params = {}, options = {}) {
  return get('user/address/detail', params, {
    ...ADDRESS_REQUEST_OPTIONS,
    ...options
  })
}

/**
 * 新增当前登录用户收货地址。
 *
 * @param {Object} data 地址字段。
 * @param {string} data.real_name 收货人姓名。
 * @param {string} data.phone 收货人手机号。
 * @param {string} data.province 省。
 * @param {string} data.city 市。
 * @param {string} data.district 区。
 * @param {string} data.detail 详细地址。
 * @param {string} [data.user_address] 完整收货地址。
 * @param {number} [data.is_default] 是否默认地址：1 是，0 否。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 data 为新增后的地址详情。
 */
export function createAddress(data = {}, options = {}) {
  return post('user/address/create', data, {
    ...ADDRESS_REQUEST_OPTIONS,
    ...options
  })
}

/**
 * 修改当前登录用户收货地址。
 *
 * @param {Object} data 地址字段。
 * @param {number|string} data.id 地址 ID。
 * @param {string} data.real_name 收货人姓名。
 * @param {string} data.phone 收货人手机号。
 * @param {string} data.province 省。
 * @param {string} data.city 市。
 * @param {string} data.district 区。
 * @param {string} data.detail 详细地址。
 * @param {string} [data.user_address] 完整收货地址。
 * @param {number} [data.is_default] 是否默认地址：1 是，0 否。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 data 为修改后的地址详情。
 */
export function updateAddress(data = {}, options = {}) {
  return post('user/address/update', data, {
    ...ADDRESS_REQUEST_OPTIONS,
    ...options
  })
}

/**
 * 删除当前登录用户收货地址。
 *
 * @param {Object} data 删除参数。
 * @param {number|string} data.id 地址 ID。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时表示删除完成。
 */
export function deleteAddress(data = {}, options = {}) {
  return post('user/address/delete', data, {
    ...ADDRESS_REQUEST_OPTIONS,
    ...options
  })
}
