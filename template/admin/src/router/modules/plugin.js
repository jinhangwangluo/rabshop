import settings from '@/settings'

const prefix = '/' + (settings.prefix ? settings.prefix + '/' : '')

export default {
  path: prefix + 'plugin',
  component: () => import('@/layout'),
  name: 'PluginMarketRoot',
  meta: {
    title: '插件市场',
    icon: 'el-icon-box'
  },
  children: [
    {
      path: '',
      component: () => import('@/views/plugin/market/index'),
      name: 'PluginMarket',
      meta: {
        title: '插件市场',
        noCache: true
      },
      hidden: true
    }
  ]
}
