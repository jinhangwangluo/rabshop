import { get } from './request'
import { AUTH_MODES } from '@/utils/auth'

/**
 * 获取微信网页授权跳转地址
 * 使用场景：H5/公众号内唤起微信授权，后端返回可跳转的 OAuth URL。
 * @param {Object} params 查询参数，例如 { redirect_uri:string, scope?:'snsapi_base'|'snsapi_userinfo' }。
 * @param {Object} options 额外请求选项，透传给内部 request。
 * @returns {Promise<Object>} 统一响应对象，成功时 data.redirect_url 为微信 OAuth 地址。
 */
export function getWechatOauthRedirect(params = {}, options = {}) {
  return get('wechat/oauth/redirect', params, {
    authMode: AUTH_MODES.NONE,
    ...options
  })
}

/**
 * 解析微信公众号网页授权回调。
 *
 * 使用场景：微信授权回跳到前端登录页后，前端携带 code/state 调后端换取登录态。
 *
 * @param {Object} params 查询参数。
 * @param {string} params.code 微信 OAuth 回调 code。
 * @param {string} params.state 后端生成并缓存的 OAuth state。
 * @param {Object} options 额外请求选项，透传给内部 request。
 * @returns {Promise<Object>} 统一响应对象，成功时 data 包含 token、用户信息和认证配置；无需登录。
 */
export function resolveWechatOauthCallback(params = {}, options = {}) {
  return get('wechat/oauth/callback', params, {
    authMode: AUTH_MODES.NONE,
    ...options
  })
}

/**
 * 获取微信 JS-SDK 鉴权配置。
 *
 * 使用场景：公众号 H5 页面调用分享、扫码、定位、微信支付等 JS 接口前调用。
 *
 * @param {Object} params 查询参数。
 * @param {string} params.url 当前页面完整 URL，不包含 hash。
 * @param {string[]|string} [params.jsApiList] 需要鉴权的 JS API 列表，未传时使用后端默认常用能力。
 * @param {string[]|string} [params.openTagList] 需要鉴权的开放标签列表。
 * @param {boolean} [params.debug] 是否开启微信 JS-SDK 调试。
 * @param {Object} options 额外请求选项，透传给内部 request。
 * @returns {Promise<Object>} 统一响应对象，成功时 data 可直接传给 wx.config。
 */
export function getWechatJsSdkConfig(params = {}, options = {}) {
  return get('wechat/jsSdkConfig', params, {
    authMode: AUTH_MODES.NONE,
    ...options
  })
}
