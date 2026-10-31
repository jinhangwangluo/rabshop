import settings from '@/settings'

const prefix = '/' + (settings.prefix ? settings.prefix + '/' : '')

const marketingRouter = {
  path: prefix + 'marketing',
  component: () => import('@/layout'),
  name: 'Marketing',
  meta: {
    title: '营销设置',
    icon: 'el-icon-present'
  },
  children: [
    {
      path: 'signin',
      component: () => import('@/views/marketing/signin/index'),
      name: 'MarketingSignin',
      meta: {
        title: '签到设置',
        noCache: true
      }
    }
  ]
}

export default marketingRouter
