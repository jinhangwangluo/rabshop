import settings from '@/settings'

const prefix = '/' + (settings.prefix ? settings.prefix + '/' : '')

const distributionRouter = {
  path: prefix + 'distribution',
  component: () => import('@/layout'),
  name: 'Distribution',
  meta: {
    title: '分销管理',
    icon: 'el-icon-share'
  },
  children: [
    {
      path: 'user',
      component: () => import('@/views/distribution/user/index'),
      name: 'DistributionUser',
      meta: { title: '分销员列表', noCache: true }
    }
  ]
}

export default distributionRouter
