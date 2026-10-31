import settings from '@/settings';

const prefix = '/' + (settings.prefix ? settings.prefix + '/' : '')

const userRouter = {
  path: prefix + 'user',
  component: () => import('@/layout'),
  name: 'User',
  meta: {
    title: '用户管理',
    icon: 'user'
  },
  children: [
    {
      path: 'user',
      component: () => import('@/views/user/user/index'),
      name: 'UserList',
      meta: { title: '用户列表', noCache: true }
    },
    {
      path: 'member_config',
      component: () => import('@/views/user/member_config/index'),
      name: 'MemberConfig',
      meta: { title: '会员配置', noCache: true }
    },
    {
      path: 'distribution_config',
      component: () => import('@/views/user/distribution_config/index'),
      name: 'DistributionConfig',
      meta: { title: '分销配置', noCache: true }
    }
  ]
}

export default userRouter
