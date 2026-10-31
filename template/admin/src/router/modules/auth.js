/** When your routing table is too long, you can split it into small modules **/

import settings from '@/settings'

const prefix = '/' + (settings.prefix ? settings.prefix + '/' : '')

const componentsRouter = {
  path: '/' + prefix + 'auth',
  component: () => import('@/layout'),
  name: 'auth',
  children: [
    {
      path: 'logs',
      component: () => import('@/views/auth/logs/index'),
      name: 'logs',
      meta: { title: '网站日志' }
    },
    {
      path: 'menu',
      component: () => import('@/views/auth/menus/index'),
      name: 'menu',
      meta: { title: '菜单管理' }
    },
    {
      path: 'roles',
      component: () => import('@/views/auth/roles/index'),
      name: 'roles',
      meta: { title: '角色管理' }
    },
    {
      path: 'config',
      component: () => import('@/views/auth/config/index'),
      name: 'config',
      meta: { title: '系统配置' }
    },
    {
      path: 'city_area',
      component: () => import('@/views/auth/cityArea/index'),
      name: 'city_area',
      meta: { title: '地址管理' }
    },
    {
      path: 'admin',
      component: () => import('@/views/auth/permissions/index'),
      name: 'permissions',
      meta: { title: '权限配置' }
    },
    {
      path: 'admin/info',
      component: () => import('@/views/auth/admin/index'),
      name: 'admin_user',
      meta: { title: '权限配置', activeMenu: '/' + settings.prefix + '/auth/permissions' }
    }
  ]
}

export default componentsRouter
