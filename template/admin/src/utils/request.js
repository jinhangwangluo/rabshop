import axios from 'axios'
import { ElMessage } from 'element-plus'
import { getToken, removeToken, getTokenExpiry, removeTokenExpiry } from '@/utils/auth'
import setting from '@/settings'
import router from '@/router'
import { useUserStore } from '@/store/modules/user'

// create an axios instance
const service = axios.create({
  baseURL: setting.baseUri,
  timeout: 20000
})

// 是否正在刷新 token（防止并发请求同时触发多次刷新）
let isRefreshing = false
// token 刷新期间，等待重试的请求队列
let pendingRequests = []

/**
 * 将等待中的请求全部以新 token 重新发起
 * @param {string|null} newToken 刷新成功传入新 token，失败传 null
 */
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

/**
 * 强制退出登录：清除 token 并跳转登录页
 */
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

    // 根据本地存储的过期时间戳判断是否需要主动刷新
    const expiry = getTokenExpiry()
    const now = new Date().getTime()
    const totalTimeout = 864000 * 1000 // 10天，单位毫秒
    const remaining = expiry - now

    // token 存在、有过期时间、且剩余时间不足总有效期 20%，主动刷新
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
    console.log(error)
    return Promise.reject(error)
  }
)

// response interceptor
service.interceptors.response.use(
  response => {
    // 检测响应头中的新 token，自动无感续期（被动刷新，加节流）
    const newToken = response.headers['x-new-token']
    const userStore = useUserStore()
    if (!response.config?.skipAuth && newToken && userStore.token && !isRefreshing) {
      isRefreshing = true
      userStore.refreshToken(newToken).finally(() => {
        isRefreshing = false
      })
    }

    const responseType = String(response.config?.responseType || '')
    if (responseType === 'blob' || responseType === 'arraybuffer') {
      return response
    }

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
      // token 过期或无效：尝试静默刷新，刷新成功则重试原请求
      case 41001:
      case 41002:
      case 41003:
        if (!userStore.token) {
          // 本来就没有 token，直接跳登录
          forceLogout()
          return Promise.reject(res)
        }
        if (isRefreshing) {
          // 刷新中：将当前请求加入等待队列，刷新完成后重试
          return new Promise((resolve, reject) => {
            pendingRequests.push({ config: response.config, resolve, reject })
          })
        }
        // 发起静默刷新
        isRefreshing = true
        return userStore.refreshToken(null).then(newToken => {
          if (!newToken) {
            // 刷新失败，强制退出
            resolvePendingRequests(null)
            forceLogout()
            return Promise.reject(res)
          }
          // 刷新成功：重试等待队列中的请求，并重试当前请求
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
      case 41004:
        // 账号被禁用，不重试，直接退出
        forceLogout()
        ElMessage({ message: res.message || '账号已被禁用', type: 'error', duration: 3 * 1000 })
        return Promise.reject(res)
      default:
        return Promise.reject(res)
    }
  },
  error => {
    if (!error.config?.silent) {
      console.log('err' + error)
      ElMessage({ message: error.message || '网络错误', type: 'error', duration: 5 * 1000 })
    }
    return Promise.reject({ msg: error })
  }
)

export default service
