/** When your routing table is too long, you can split it into small modules **/

import settings from '@/settings'

const prefix = '/' + (settings.prefix ? settings.prefix + '/' : '')

// 店铺相关路由已移至 setting.js
const componentsRouter = {
  path: prefix + 'auth',
  component: () => import('@/layout'),
  name: 'auth',
  meta: {
    title: '权限管理',
    icon: 'el-icon-lock'
  },
  children: [
    {
      path: 'menu',
      component: () => import('@/views/auth/menus/index'),
      name: 'menu',
      meta: { title: '菜单管理' }
    }
  ]
}

export default componentsRouter
