import request from '@/utils/request'

/**
 * 获取支付配置详情。
 * @returns {AxiosPromise}
 */
export function getPaymentConfig() {
  return request({
    url: '/setting/payment_config',
    method: 'get'
  })
}

/**
 * 保存支付配置。
 * @param {Object} data 支付配置数据
 * @returns {AxiosPromise}
 */
export function savePaymentConfig(data) {
  return request({
    url: '/setting/payment_config',
    method: 'post',
    data
  })
}

/**
 * 获取支付配置选项。
 * @returns {AxiosPromise}
 */
export function getPaymentConfigOptions() {
  return request({
    url: '/setting/payment_config/options',
    method: 'get'
  })
}
