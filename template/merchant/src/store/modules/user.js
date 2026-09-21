import { login as loginApi, logout as logoutApi, getInfo as getInfoApi, refreshTokenApi } from '@/api/login'
import { defineStore } from 'pinia'
import { reactive, toRefs } from 'vue'
import { getToken, setToken, removeToken, setTokenExpiry, removeTokenExpiry } from '@/utils/auth'
import router, { resetRouter } from '@/router'
import localStorage from '@/utils/store/localStorage'
import {
  getMenusListApi
} from '@/api/system'
import defaultSettings from '@/settings.js'
import { useTagsViewStore } from './tagsView'

const key = defaultSettings.storePrefix + '-Menus'
const appIdKey = defaultSettings.storePrefix + '-AppId'

// appId 初始为 0，由 setAppId/getAppMenus 在运行时设置
// （模块顶层读取 router 会与 router/index.js 形成循环依赖，导致 TDZ 报错；
//   与 admin 端实现保持一致，且实际视图均直接读 route.query.appId）
const appId = 0

const adminIdKey = defaultSettings.storePrefix + '-Id'
const adminId = localStorage.get(adminIdKey) || 0
const adminInfoKey = defaultSettings.storePrefix + '-Info-' + adminId

export const useUserStore = defineStore('user', () => {
  const state = reactive({
    token: getToken(),
    menus: localStorage.get(key) || [],
    appMenus: [],
    appId,
    adminId: 0,
    adminInfo: localStorage.get(adminInfoKey) || {}
  })

  function setAppMenus(menus) {
    state.appMenus = menus
  }

  function setAppId(data) {
    state.appId = data.appId
    state.appMenus = data.menus
    localStorage.set(appIdKey, data.appId)
    localStorage.set(key + '-' + data.appId, data.menus)
  }

  function setTokenValue(token) {
    state.token = token
  }

  function setMenus(menus) {
    state.menus = menus
    localStorage.set(key, menus)
  }

  function setAdminInfo(data) {
    state.adminInfo = data
    localStorage.set(adminIdKey, data.id)
    localStorage.set(defaultSettings.storePrefix + '-Info-' + data.id, data)
  }

  async function login(userInfo) {
    const { username, password, captchaVerification, captchaType } = userInfo
    const response = await loginApi({
      account: username.trim(),
      password,
      captchaVerification,
      captchaType
    })
    const { data } = response
    if (data.isLogin) {
      setTokenValue(data.token)
      setAdminInfo(data.adminInfo)
      const expiresIn = parseInt(data.expiresIn) || 864000
      const expiryMs = new Date().getTime() + expiresIn * 1000
      setToken(data.token, new Date(expiryMs))
      setTokenExpiry(expiryMs)
      localStorage.set(key, data.menus)
      state.menus = data.menus || []
    }
    return data
  }

  async function getInfo() {
    const response = await getInfoApi()
    setAdminInfo(response.data)
    return response.data
  }

  async function logout() {
    await logoutApi()
    setTokenValue('')
    removeToken()
    removeTokenExpiry()
    resetRouter()
    setMenus([])
    localStorage.remove(key)
    const tagsViewStore = useTagsViewStore()
    await tagsViewStore.delAllViews(null)
  }

  function removeMenus() {
    setMenus([])
    localStorage.remove(key)
  }

  function resetToken() {
    setTokenValue('')
    removeToken()
    removeTokenExpiry()
  }

  async function fetchMenus() {
    const response = await getMenusListApi({ auth_type: '0,2', status: 1 })
    setMenus(response.data)
    return response
  }

  async function getAppMenus(currentAppId) {
    const cachedMenus = localStorage.get(key + '-' + currentAppId)
    if (cachedMenus && cachedMenus.length) {
      setAppId({ appId: currentAppId, menus: cachedMenus })
      return cachedMenus
    }
    const response = await getMenusListApi({ auth_type: '0,2', status: 1, app_id: currentAppId })
    setAppId({ appId: currentAppId, menus: response.data })
    return response.data
  }

  async function refreshToken(newToken) {
    const saveTokenValue = (token, expiresIn) => {
      const expiryMs = new Date().getTime() + expiresIn * 1000
      setTokenValue(token)
      setToken(token, new Date(expiryMs))
      setTokenExpiry(expiryMs)
    }

    if (newToken) {
      saveTokenValue(newToken, 864000)
      return newToken
    }

    try {
      const response = await refreshTokenApi()
      const token = response.data.token
      const expiresIn = response.data.expiresIn || 864000
      saveTokenValue(token, expiresIn)
      return token
    } catch (error) {
      return null
    }
  }

  return {
    ...toRefs(state),
    setAppMenus,
    setAppId,
    setToken: setTokenValue,
    setMenus,
    setAdminInfo,
    login,
    getInfo,
    logout,
    removeMenus,
    resetToken,
    fetchMenus,
    getAppMenus,
    refreshToken
  }
})

export default useUserStore
