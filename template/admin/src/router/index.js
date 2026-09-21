import { createRouter, createWebHistory } from 'vue-router'
import settings from '@/settings'

/* Layout */
import Layout from '@/layout'
import { getPluginRoutes } from '@/plugins/registry'

const modules = import.meta.glob('./modules/*.js', { eager: true })

const routers = []
// 将扫描到的路由信息加入路由数组中
Object.values(modules).forEach((module) => {
  const value = module
  if (Array.isArray(value.default)) {
    routers.push(...value.default)
  } else if (value.default) {
    routers.push(value.default)
  }
})

const prefix = '/' + (settings.prefix ? settings.prefix + '/' : '')

/**
 * Note: sub-menu only appear when route children.length >= 1
 * Detail see: https://panjiachen.github.io/vue-element-admin-site/guide/essentials/router-and-nav.html
 *
 * hidden: true                   if set true, item will not show in the sidebar(default is false)
 * alwaysShow: true               if set true, will always show the root menu
 *                                if not set alwaysShow, when item has more than one children route,
 *                                it will becomes nested mode, otherwise not show the root menu
 * redirect: noRedirect           if set noRedirect will no redirect in the breadcrumb
 * name:'router-name'             the name is used by <keep-alive> (must set!!!)
 * meta : {
    roles: ['admin','editor']    control the page roles (you can set multiple roles)
    title: 'title'               the name show in sidebar and breadcrumb (recommend set)
    icon: 'svg-name'/'el-icon-x' the icon show in the sidebar
    noCache: true                if set true, the page will no be cached(default is false)
    affix: true                  if set true, the tag will affix in the tags-view
    breadcrumb: false            if set false, the item will hidden in breadcrumb(default is true)
    activeMenu: '/example/list'  if set path, the sidebar will highlight the path you set
  }
 */

/**
 * constantRoutes
 * a base page that does not have permission requirements
 * all roles can be accessed
 */
export const constantRoutes = [
  {
    path: prefix + 'redirect',
    component: Layout,
    hidden: true,
    children: [
      {
        path: '/redirect/:path(.*)',
        name: 'redirect',
        component: () => import('@/views/redirect/index')
      }
    ]
  },
  {
    path: prefix + 'system/file',
    component: () => import('@/components/UploadPicture/widgetImg.vue'),
    name: 'file',
    meta: { title: '图片管理' }
  },
  {
    path: prefix + 'system/user-select',
    component: () => import('@/views/auth/admin/UserSelectFrame.vue'),
    name: 'userSelectFrame',
    meta: { title: '选择用户' }
  },
  {
    path: prefix + 'login',
    component: () => import('@/views/login/index'),
    name: 'login',
    hidden: true
  },
  {
    path: prefix + '404',
    component: () => import('@/views/error-page/404'),
    name: '404',
    hidden: true
  },
  {
    path: prefix + '401',
    component: () => import('@/views/error-page/401'),
    hidden: true,
    name: '401',
    meta: { title: '404' }
  },
  {
    path: prefix,
    component: Layout,
    redirect: {
      name: 'index'
    },
    children: [
      {
        path: prefix + 'home',
        component: () => import('@/views/index/index'),
        name: 'index',
        meta: { title: '主页' }
      }
    ]
  },
  /** when your routing map is too long, you can split it into small modules **/
  ...routers,
  ...getPluginRoutes(),
  // 404 page must be placed at the end !!!
  { path: prefix + ':pathMatch(.*)*', redirect: prefix + '404', hidden: true }
]

const createAppRouter = () => createRouter({
  history: createWebHistory(),
  scrollBehavior: () => ({ left: 0, top: 0 }),
  routes: constantRoutes
})

const router = createAppRouter()

// 拦截所有绝对路径跳转，若路径不以 prefix 开头则自动补上，无需逐页修改。
if (settings.prefix) {
  const prefixPath = '/' + settings.prefix
  const wrapLocation = (location) => {
    if (typeof location === 'string' && location.startsWith('/') && !location.startsWith(prefixPath)) {
      return prefixPath + location
    }
    return location
  }
  const _push = router.push.bind(router)
  const _replace = router.replace.bind(router)
  router.push = (location, ...args) => _push(wrapLocation(location), ...args)
  router.replace = (location, ...args) => _replace(wrapLocation(location), ...args)
}

export function resetRouter() {
  const getRouteNames = (routes, names = new Set()) => {
    routes.forEach(route => {
      if (route.name) {
        names.add(route.name)
      }
      if (route.children) {
        getRouteNames(route.children, names)
      }
    })
    return names
  }

  const constantRouteNames = getRouteNames(constantRoutes)
  router.getRoutes().forEach(route => {
    if (route.name && !constantRouteNames.has(route.name)) {
      router.removeRoute(route.name)
    }
  })
}

export default router
