import settings from '@/settings';

const prefix = '/' + (settings.prefix ? settings.prefix + '/' : '')

export default {
  path: prefix + 'special',
  component: () => import('@/layout'),
  name: 'special',
  meta: {
    title: '专题管理',
    icon: 'el-icon-collection'
  },
  children: [
    {
      path: 'manage',
      component: () => import('@/views/special/index'),
      name: 'SpecialManage',
      meta: {
        title: '专题管理',
        noCache: true
      }
    },
    {
      path: 'manage/create',
      component: () => import('@/views/special/create'),
      name: 'SpecialCreate',
      hidden: true,
      meta: {
        title: '添加专题',
        noCache: true,
        activeMenu: prefix + 'special/manage'
      }
    },
    {
      path: 'category',
      component: () => import('@/views/special/special_category/index'),
      name: 'SpecialCategory',
      meta: {
        title: '专题分类',
        noCache: true
      }
    }
  ]
}
