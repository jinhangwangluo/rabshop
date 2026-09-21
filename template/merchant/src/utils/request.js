import axios from 'axios'
import { ElMessage } from 'element-plus'
import { getToken, removeToken, getTokenExpiry, removeTokenExpiry } from '@/utils/auth'
import setting from '@/settings'
import router from '@/router'
import { useUserStore } from '@/store/modules/user'

// create an axios instance
const service = axios.create({
  baseURL: setting.baseUri, // url = base url + request url
  // withCredentials: true, // send cookies when cross-domain requests
  timeout: 20000
})

let isRefreshing = false
let pendingRequests = []

function resolvePendingRequests(newToken) {
  pendingRequests.forEach(({ config, resolve, reject }) => {
    if (newToken) {
      config.headers['Authorization'] = 'Bearer ' + newToken
      resolve(service(config))
    } else {
      reject(new Error('token刷新失败'))
    }
  })
  pendingRequests = []
}

function forceLogout() {
  removeToken()
  removeTokenExpiry()
  useUserStore().setToken('')
  pendingRequests = []
  isRefreshing = false
  router.replace({ name: 'login' })
}

// request interceptor
service.interceptors.request.use(
  async config => {
    // Public plugin state must not wait for a token refresh during page startup.
    const userStore = config.skipAuth ? null : useUserStore()
    const token = userStore?.token
    if (!token) return config
    const headers = config.headers

    const expiry = getTokenExpiry()
    const now = new Date().getTime()
    const totalTimeout = 864000 * 1000
    const remaining = expiry - now

    if (expiry && remaining > 0 && remaining < totalTimeout * 0.2 && !isRefreshing) {
      isRefreshing = true
      const newToken = await userStore.refreshToken(null).finally(() => {
        isRefreshing = false
      })
      if (newToken) {
        headers['Authorization'] = 'Bearer ' + newToken
        return config
      }
    }

    headers['Authorization'] = 'Bearer ' + getToken()
    return config
  },
  error => {
    // do something with request error
    console.log(error) // for debug
    return Promise.reject(error)
  }
)

// response interceptor
service.interceptors.response.use(
  /**
   * If you want to get http information such as headers or status
   * Please return  response => response
  */

  /**
   * Determine the request status by custom code
   * Here is just an example
   * You can also judge the status by HTTP Status Code
   */
  response => {
    const res = response.data

    if (response.config?.skipAuth && [41001, 41002, 41003, 41004].includes(res?.status)) {
      return Promise.reject(res)
    }

    switch (res.status) {
      case 400:
        if (!response.config?.silent) ElMessage({ message: res.message || 'Error', type: 'error', duration: 5 * 1000 })
        return Promise.reject(res)
      case 200:
        return res
      case 41001:
      case 41002:
      case 41003:
        return handleInvalidTokenResponse(response, res)
      case 41004:
        forceLogout()
        ElMessage({ message: res.message || '账号已被禁用', type: 'error', duration: 3 * 1000 })
        return Promise.reject(res)
      default:
        return Promise.reject(res)
    }
  },
  error => {
    if (!error.config?.silent) {
      console.log('err' + error) // for debug
      ElMessage({ message: error.message || '网络错误', type: 'error', duration: 5 * 1000 })
    }
    return Promise.reject({ msg: error })
  }
)

function handleInvalidTokenResponse(response, res) {
  const userStore = useUserStore()
  if (!userStore.token) {
    forceLogout()
    return Promise.reject(res)
  }
  if (isRefreshing) {
    return new Promise((resolve, reject) => {
      pendingRequests.push({ config: response.config, resolve, reject })
    })
  }
  isRefreshing = true
  return userStore.refreshToken(null).then(newToken => {
    if (!newToken) {
      resolvePendingRequests(null)
      forceLogout()
      return Promise.reject(res)
    }
    resolvePendingRequests(newToken)
    response.config.headers['Authorization'] = 'Bearer ' + newToken
    return service(response.config)
  }).catch(() => {
    resolvePendingRequests(null)
    forceLogout()
    return Promise.reject(res)
  }).finally(() => {
    isRefreshing = false
  })
}

export default service
