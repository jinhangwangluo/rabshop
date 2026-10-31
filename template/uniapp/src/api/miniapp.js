import { get, post } from './request'
import { AUTH_MODES } from '@/utils/auth'

/**
 * 小程序登录
 * 使用场景：微信小程序端提交登录凭证（如 code、用户信息等）。
 * @param {Object} data 登录数据，例如 { code:string, userInfo?:Object }
 * @param {Object} options 额外请求选项
 * @returns {Promise<{code:number,msg:string,data:{token:string,user:Object}}>} 统一响应
 */
export function miniappLogin(data = {}, options = {}) {
  return post('miniapp/login', data, {
    authMode: AUTH_MODES.NONE,
    ...options
  })
}

/**
 * 小程序获取手机号
 * 使用场景：解密并绑定小程序手机号能力。
 * @param {Object} data 请求数据，通常包含 iv、encryptedData 等。
 * @param {Object} options 额外请求选项
 * @returns {Promise<{code:number,msg:string,data:{phone:string}}>} 统一响应
 */
export function getMiniappPhoneNumber(data = {}, options = {}) {
  return post('miniapp/phone', data, {
    authMode: AUTH_MODES.REQUIRED,
    ...options
  })
}

/**
 * 绑定小程序手机号
 * 使用场景：在已登录前提下，绑定/更新账号手机号。
 * @param {Object} data 请求数据，形如 { phone:string, code?:string }
 * @param {Object} options 额外请求选项
 * @returns {Promise<{code:number,msg:string,data:boolean}>} code===200 且 data=true 表示成功
 */
export function bindMiniappPhone(data = {}, options = {}) {
  return post('miniapp/bindPhone', data, {
    authMode: AUTH_MODES.REQUIRED,
    ...options
  })
}

/**
 * 获取微信小程序订阅消息模板列表。
 *
 * 使用场景：小程序端初始化或按钮点击时一次拉取全部可订阅模板，前端缓存后按类型使用。
 *
 * @param {Object} params 查询参数。
 * @param {string} [params.type] 可选，订阅业务类型；不传时一次返回全部类型。
 * @param {Object} options 额外请求选项。
 * @returns {Promise<Object>} 成功时 data.list 为类型数组，data.map 可按类型快速查找。
 */
export function getMiniappSubscribeMessages(params = {}, options = {}) {
  return get('miniapp/subscribe/messages', params, {
    authMode: AUTH_MODES.REQUIRED,
    ...options
  })
}
