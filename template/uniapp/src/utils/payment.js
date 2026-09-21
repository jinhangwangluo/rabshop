import { PLATFORM_SCENES, getPlatformScene } from '@/utils/platform'
import { normalizePublicErrorMessage } from '@/utils/error'

const PAY_TYPE_WECHAT = 'wechat'
const PAY_TYPE_ALIPAY = 'alipay'
const PAY_TYPE_BALANCE = 'balance'

/**
 * 拉起支付。
 *
 * @param {Object} options 支付参数。
 * @param {string} options.payType 支付方式：wechat/alipay/balance。
 * @param {Object} options.config 后端返回的支付配置。
 * @param {string} options.scene 当前平台场景。
 * @param {string} options.returnUrl H5 支付完成后的回跳地址。
 * @returns {Promise<Object>} 支付拉起结果。
 */
export function invokePayment(options = {}) {
  const payType = normalizePayType(options.payType || options.pay_type || '')
  const scene = options.scene || getPlatformScene()
  const config = options.config && typeof options.config === 'object' ? options.config : {}
  const returnUrl = String(options.returnUrl || options.return_url || '')

  if (payType === PAY_TYPE_BALANCE) {
    return Promise.resolve({ paid: true })
  }

  if (payType === PAY_TYPE_WECHAT) {
    return invokeWechatPayment({ config, scene, returnUrl })
  }

  if (payType === PAY_TYPE_ALIPAY) {
    return invokeAlipayPayment({ config, scene, returnUrl })
  }

  return Promise.reject(new Error('暂不支持该支付方式'))
}

export function invokeWechatPayment(options = {}) {
  const scene = options.scene || getPlatformScene()
  const config = options.config || {}

  if (scene === PLATFORM_SCENES.MINIAPP) {
    return invokeMiniappWechatPayment(config)
  }

  if (scene === PLATFORM_SCENES.WECHAT_H5) {
    return invokeWechatJsapiPayment(config)
  }

  if (scene === PLATFORM_SCENES.H5) {
    return invokeWechatH5Payment(config, options.returnUrl)
  }

  return Promise.reject(new Error('当前环境不支持微信支付'))
}

export function invokeAlipayPayment(options = {}) {
  const scene = options.scene || getPlatformScene()
  const config = options.config || {}

  if (scene === PLATFORM_SCENES.WECHAT_H5) {
    // TODO: 后续对接支付宝在微信内支付时，可在这里接入引导页或支付宝官方微信端方案。
    return Promise.reject(new Error('暂不支持在微信内使用支付宝支付，请在浏览器中打开后重试'))
  }

  if (scene !== PLATFORM_SCENES.H5) {
    return Promise.reject(new Error('当前环境不支持支付宝支付'))
  }

  return invokeAlipayH5Payment(config, options.returnUrl)
}

function invokeMiniappWechatPayment(config = {}) {
  const paymentPayload = buildWechatPayPayload(config)

  return new Promise((resolve, reject) => {
    uni.requestPayment({
      provider: 'wxpay',
      timeStamp: paymentPayload.timeStamp,
      nonceStr: paymentPayload.nonceStr,
      package: paymentPayload.package,
      signType: paymentPayload.signType,
      paySign: paymentPayload.paySign,
      success: () => resolve({ submitted: true }),
      fail: (error) => reject(new Error(resolvePaymentErrorMessage(error, '支付未完成')))
    })
  })
}

function invokeWechatJsapiPayment(config = {}) {
  return new Promise((resolve, reject) => {
    // #ifdef H5
    const paymentPayload = buildWechatPayPayload(config)
    let settled = false
    const onReady = () => {
      if (settled) {
        return
      }
      settled = true
      const bridge = typeof window !== 'undefined' ? window.WeixinJSBridge : undefined
      if (!bridge || typeof bridge.invoke !== 'function') {
        reject(new Error('微信支付桥接尚未就绪，请稍后重试'))
        return
      }

      bridge.invoke('getBrandWCPayRequest', paymentPayload, (result) => {
        const errorMessage = String((result && result.err_msg) || '')
        if (errorMessage.indexOf('ok') !== -1) {
          resolve({ submitted: true })
          return
        }
        reject(new Error(resolvePaymentErrorMessage(result, '支付未完成')))
      })
    }

    if (typeof window !== 'undefined' && window.WeixinJSBridge) {
      onReady()
      return
    }

    if (typeof document !== 'undefined') {
      document.addEventListener('WeixinJSBridgeReady', onReady, false)
      document.addEventListener('onWeixinJSBridgeReady', onReady, false)
      return
    }

    reject(new Error('当前环境无法调用微信支付'))
    // #endif

    // #ifndef H5
    reject(new Error('当前环境不支持公众号支付'))
    // #endif
  })
}

function invokeWechatH5Payment(config = {}, returnUrl = '') {
  const h5Url = String(config.h5_url || config.h5Url || '')
  if (!h5Url) {
    return Promise.reject(new Error('微信 H5 支付链接缺失'))
  }

  // #ifdef H5
  const redirectUrl = appendRedirectUrl(h5Url, returnUrl)
  window.location.href = redirectUrl
  return Promise.resolve({ redirected: true })
  // #endif

  // #ifndef H5
  return Promise.reject(new Error('当前环境不支持微信 H5 支付'))
  // #endif
}

function invokeAlipayH5Payment(config = {}, returnUrl = '') {
  const payUrl = String(config.pay_url || config.payUrl || '')
  const formHtml = String(config.form_html || config.formHtml || '')

  // #ifdef H5
  if (formHtml) {
    submitHtmlForm(formHtml)
    return Promise.resolve({ redirected: true })
  }

  if (payUrl) {
    window.location.href = appendReturnUrl(payUrl, returnUrl)
    return Promise.resolve({ redirected: true })
  }

  return Promise.reject(new Error('支付宝 H5 支付参数缺失'))
  // #endif

  // #ifndef H5
  return Promise.reject(new Error('当前环境不支持支付宝 H5 支付'))
  // #endif
}

function submitHtmlForm(formHtml = '') {
  const container = document.createElement('div')
  container.style.display = 'none'
  container.innerHTML = formHtml
  document.body.appendChild(container)

  const form = container.querySelector('form')
  if (!form) {
    throw new Error('支付宝支付表单无效')
  }

  form.submit()
}

function buildWechatPayPayload(config = {}) {
  return {
    appId: String(config.appId || config.appid || ''),
    timeStamp: String(config.timeStamp || config.timestamp || ''),
    nonceStr: String(config.nonceStr || config.noncestr || ''),
    package: String(config.package || config.packageValue || ''),
    signType: String(config.signType || 'RSA'),
    paySign: String(config.paySign || config.paysign || config.sign || '')
  }
}

function appendRedirectUrl(url = '', returnUrl = '') {
  if (!returnUrl || url.indexOf('redirect_url=') !== -1) {
    return url
  }

  return `${url}${url.indexOf('?') === -1 ? '?' : '&'}redirect_url=${encodeURIComponent(returnUrl)}`
}

function appendReturnUrl(url = '', returnUrl = '') {
  if (!returnUrl || url.indexOf('return_url=') !== -1) {
    return url
  }

  return `${url}${url.indexOf('?') === -1 ? '?' : '&'}return_url=${encodeURIComponent(returnUrl)}`
}

function normalizePayType(payType = '') {
  const normalized = String(payType || '').trim().toLowerCase()
  if (['weixin', 'wxpay', 'wechat'].includes(normalized)) {
    return PAY_TYPE_WECHAT
  }
  if (['zhifubao', 'alipay'].includes(normalized)) {
    return PAY_TYPE_ALIPAY
  }
  if (['balance', 'yue'].includes(normalized)) {
    return PAY_TYPE_BALANCE
  }

  return normalized
}

function resolvePaymentErrorMessage(error = {}, fallback = '支付未完成') {
  const errMsg = String((error && (error.errMsg || error.err_msg || error.message)) || '')
  if (errMsg.indexOf('cancel') !== -1) {
    return '您已取消支付'
  }
  return normalizePublicErrorMessage(errMsg, fallback)
}
