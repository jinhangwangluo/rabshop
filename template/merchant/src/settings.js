const VITE_APP_BASE_API = import.meta.env.VITE_APP_BASE_API || `${location.origin}/api/merchant`

export default {

  /**
   * 请求基础uri
   */
  baseUri: VITE_APP_BASE_API,

  prefix: 'merchant',

  title: '多商户乖兔阅读商户端',

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
   * token 前缀
   */
  tokenPrefix: 'rabbit-merchant-token',

  /**
   * Pinia 本地缓存前缀
   */
  storePrefix: 'rabbit-merchant-store'
}
