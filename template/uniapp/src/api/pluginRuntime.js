import { get } from './request'

/**
 * 获取已安装插件的公开运行状态，无需登录。
 * @returns {Promise<Object>} {code,msg,data:{plugins,server_time}}。
 */
export function getPluginRuntime() {
  return get('plugins/runtime', {}, { authMode: 'none', showError: false })
}
