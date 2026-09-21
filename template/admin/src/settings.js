const VITE_APP_BASE_API = 'http://127.0.0.1:25780/api/admin' || import.meta.env.VITE_APP_BASE_API || `${location.origin}/api/admin`

export default {
  /**
   * 请求基础uri
   */
  baseUri: VITE_APP_BASE_API,

  prefix: 'admin',

  title: '乖乖兔多商户商城系统',

  /**
   * @type {boolean} true | false
   * @description Whether show the settings right-panel
   */
  showSettings: true,

  /**
   * @type {boolean} true | false
   * @description Whether need tagsView
   */
  tagsView: true,

  /**
   * @type {boolean} true | false
   * @description Whether fix the header
   */
  fixedHeader: false,

  /**
   * @type {boolean | string} true | false | string
   * @description Whether show the logo in sidebar
   */
  sidebarLogo: true,

  /**
   * @type {string | array} 'production' | ['production', 'development']
   * @description Need show err logs component.
   * The default is only used in the production env
   * If you want to also use it in dev, you can pass ['production', 'development']
   */
  errorLog: 'production',

  /**
   * 商户端地址（正式环境用当前域名相对路径，本地开发在 settings.env.js 中覆盖）
   */
  merchantUrl: '/merchant'

}
