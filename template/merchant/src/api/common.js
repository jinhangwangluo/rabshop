import request from '@/utils/request'

export function tableDelApi(data) {
  const config = {
    url: data.url,
    method: data.method
  }

  if (String(data.method).toLowerCase() === 'delete') {
    if (data.ids) {
      config.params = data.ids
    }
  } else {
    config.data = data.ids
  }

  return request(config)
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
 * 获取平台公共配置（腾讯地图 Key 等）
 * @return {AxiosPromise}
 */
export function getPublicConfigApi() {
  return request({
    url: '/common/config',
    method: 'get'
  })
}
