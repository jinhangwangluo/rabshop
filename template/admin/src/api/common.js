import request from '@/utils/request'

export function tableDelApi(data) {
  return request({
    url: data.url,
    method: data.method,
    data: data.ids
  })
}

export function ajCaptcha(params) {
  return request({
    url: '/captcha',
    method: 'get',
    params: params
  });
}

export function ajCaptchaCheck(data) {
  return request({
    url: '/captcha/check',
    method: 'post',
    data: data
  });
}

/**
 *
 * @return {AxiosPromise}
 */
export function getIndexApi() {
  return request({
    url: '/common/index',
    method: 'get'
  });
}

export function getOrderStatistic(params) {
  return request({
    url: '/common/order',
    method: 'get',
    params
  });
}
