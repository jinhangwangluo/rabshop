import App from './App'

/**
 * UniApp 移动端应用入口。
 *
 * 启动流程：
 * 1. 注册全局 UI 库与公共组件；
 * 2. 预处理登录态（例如 H5 OAuth 回跳带回的 token）；
 * 3. 挂载全局能力到 Vue3 globalProperties，供页面通过 `$auth`、`$request`、`$socket` 调用；
 * 4. 创建并挂载应用实例。
 */
import {
  createSSRApp
} from 'vue'
import auth from './utils/auth.js'
import socketClient from './utils/socket.js'
import {
  getRuntimeConfig,
  setRuntimeConfig,
  resolveAssetUrl,
  clearRuntimeConfig
} from './utils/runtime_config.js'
import { createThemeMixin } from './utils/theme.js'
import { request } from './api/request.js'
import { initializePluginRegistry } from './plugins/registry.js'
import { registerSubmitHandler } from './plugins/submit-events.js'
import PluginSlot from './components/plugin-slot/index.vue'
// #ifdef H5
import flvjs from 'flv.js'
// #endif

/**
 * VUE3 入口工厂（UniApp Vue3 标准导出）。
 * 注册登录态引导、$config/$auth/$request/$socket（H5 额外 $flvjs）等全局能力。
 */
export function createApp() {
  auth.bootstrapAuth()
  const app = createSSRApp(App)
  initializePluginRegistry({ registerSubmitHandler })
  app.component('PluginSlot', PluginSlot)
  app.mixin(createThemeMixin())

  app.config.globalProperties.$config = {
    getRuntimeConfig,
    setRuntimeConfig,
    resolveAssetUrl,
    clearRuntimeConfig
  }
  app.config.globalProperties.$auth = auth
  app.config.globalProperties.$request = request
  app.config.globalProperties.$socket = socketClient
  // #ifdef H5
  app.config.globalProperties.$flvjs = flvjs
  // #endif

  return {
    app
  }
}
