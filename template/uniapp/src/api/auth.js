import { get, post } from './request'
import { AUTH_MODES } from '@/utils/auth'

/**
 * 认证接口封装。
 * 使用方式示例：
 * - 验证码登录：`phoneLogin({ phone, code })`
 * - 密码登录：`accountLogin({ account, password })`
 */

/**
 * 获取短信点击验证场景。
 *
 * @param {Object} data 请求参数。
 * @param {string} data.phone 手机号。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时返回场景令牌、验证码图片、目标文字与画布尺寸。
 */
export function getSmsCaptchaScene(data = {}, options = {}) {
  return post('auth/smsCaptchaScene', data, {
    authMode: AUTH_MODES.NONE,
    ...options
  })
}

/**
 * 校验短信点击验证并发送验证码。
 *
 * @param {Object} data 提交参数。
 * @param {string} data.phone 手机号。
 * @param {string} data.scene_token 场景令牌。
 * @param {{x:number,y:number}[]} data.selected_points 用户按顺序点击的图片坐标。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时返回发码结果与倒计时秒数。
 */
export function verifySmsCaptchaAndSendCode(data = {}, options = {}) {
  return post('auth/smsCaptchaVerifySend', data, {
    authMode: AUTH_MODES.NONE,
    ...options
  })
}

/**
 * 手机号 + 验证码登录。
 *
 * @param {Object} data 登录参数。
 * @param {string} data.phone 手机号。
 * @param {string} data.code 短信验证码。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时返回 token 与用户信息。
 */
export function phoneLogin(data = {}, options = {}) {
  return post('auth/phoneLogin', data, {
    authMode: AUTH_MODES.NONE,
    ...options
  })
}

/**
 * 账号密码登录。
 *
 * @param {Object} data 登录参数。
 * @param {string} data.account 登录账号，当前页面通常传手机号。
 * @param {string} data.password 登录密码。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时返回 token 与用户信息。
 */
export function accountLogin(data = {}, options = {}) {
  return post('auth/accountLogin', data, {
    authMode: AUTH_MODES.NONE,
    ...options
  })
}

/**
 * 获取当前登录用户信息。
 *
 * 使用方式：页面进入后调用本方法校验 token 是否仍然有效，并同步最新资料。
 *
 * @param {Object} params 查询参数，通常留空。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data` 中包含用户信息。
 */
export function getCurrentUser(params = {}, options = {}) {
  return get('auth/me', params, {
    authMode: AUTH_MODES.REQUIRED,
    ...options
  })
}

/**
 * 登录后绑定或修改手机号。
 *
 * 使用方式：个人设置中的修改手机号页提交新手机号和短信验证码后调用。
 *
 * @param {Object} data 提交参数。
 * @param {string} data.phone 新手机号。
 * @param {string} data.code 短信验证码。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data` 中返回更新后的用户信息。
 */
export function bindPhoneWithCode(data = {}, options = {}) {
  return post('auth/bindPhone', data, {
    authMode: AUTH_MODES.REQUIRED,
    ...options
  })
}

/**
 * 重置密码（忘记密码）。
 *
 * @param {Object} data 提交参数。
 * @param {string} data.phone 手机号。
 * @param {string} data.code 短信验证码。
 * @param {string} data.password 新密码。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `code === 200`。
 */
export function resetPassword(data = {}, options = {}) {
  return post('auth/resetPassword', data, {
    authMode: AUTH_MODES.NONE,
    ...options
  })
}

/**
 * 刷新移动端登录 token。
 *
 * @param {Object} data 提交参数，通常留空。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时返回新 token 和 expiresIn。
 */
export function refreshToken(data = {}, options = {}) {
  return post('auth/refreshToken', data, {
    authMode: AUTH_MODES.REQUIRED,
    skipTokenRefresh: true,
    loginRedirect: false,
    showError: false,
    ...options
  })
}

/**
 * 退出登录。
 *
 * @param {Object} data 提交参数，通常留空。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时后端会注销当前 token。
 */
export function logout(data = {}, options = {}) {
  return post('auth/logout', data, {
    authMode: AUTH_MODES.REQUIRED,
    ...options
  })
}
