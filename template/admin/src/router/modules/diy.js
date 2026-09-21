import settings from '@/settings';

const prefix = '/' + (settings.prefix ? settings.prefix + '/' : '')

const diyRouter = {
  path: prefix + 'diy',
  component: () => import('@/layout'),
  name: 'Diy',
  meta: {
    title: 'DIY装修管理',
    icon: 'el-icon-s-tools'
  },
  children: [
    {
      path: 'index',
      component: () => import('@/views/diy/index'),
      name: 'DiyPageList',
      meta: {
        title: '页面列表',
        noCache: true
      }
    },
    {
      path: 'user-center',
      component: () => import('@/views/diy/userCenter'),
      name: 'UserCenterDiy',
      meta: {
        title: '个人中心装修',
        noCache: true
      }
    },
    {
      path: 'category-page',
      component: () => import('@/views/diy/categoryPage'),
      name: 'DiyCategoryPage',
      meta: {
        title: '分类装修',
        noCache: true
      }
    },
    {
      path: 'theme_config',
      component: () => import('@/views/diy/themeConfig/index'),
      name: 'DiyThemeConfig',
      meta: {
        title: '主题换色',
        noCache: true
      }
    },
    {
      path: 'links',
      component: () => import('@/views/diy/link/index'),
      name: 'DiyLinkManage',
      meta: {
        title: '链接管理',
        noCache: true
      }
    }
  ]
}

// 独立全屏路由，不套 Layout（无侧边栏/Header）
const diyEditorRouter = {
  path: prefix + 'diy/editor',
  component: () => import('@/views/diy/editor'),
  name: 'DiyEditor',
  meta: {
    title: '页面装修',
    noCache: true
  }
}

export default [diyRouter, diyEditorRouter]
