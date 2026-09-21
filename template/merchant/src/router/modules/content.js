import settings from '@/settings'

const prefix = '/' + (settings.prefix ? settings.prefix + '/' : '')

const contentRouter = {
  path: prefix + 'content',
  component: () => import('@/layout'),
  redirect: prefix + 'content/article/article',
  name: 'Content',
  meta: {
    title: '内容',
    icon: 'el-icon-document'
  },
  children: [
    // 内容列表
    {
      path: 'article/article',
      component: () => import('@/views/content/article/index'),
      name: 'ContentArticleList',
      meta: {
        title: '内容列表',
        noCache: true
      }
    },
    // 添加/编辑内容（隐藏菜单）
    {
      path: 'article/article/create',
      component: () => import('@/views/content/article/create'),
      name: 'ContentArticleCreate',
      hidden: true,
      meta: {
        title: '添加内容',
        noCache: true,
        activeMenu: prefix + 'content/article/article'
      }
    },
    // 专题列表
    {
      path: 'special/manage',
      component: () => import('@/views/content/special/index'),
      name: 'SpecialManage',
      meta: {
        title: '专题列表',
        noCache: true
      }
    },
    // 添加/编辑专题（隐藏菜单）
    {
      path: 'special/manage/create',
      component: () => import('@/views/content/special/create'),
      name: 'SpecialCreate',
      hidden: true,
      meta: {
        title: '添加专题',
        noCache: true,
        activeMenu: prefix + 'content/special/manage'
      }
    },
    // 标签管理
    {
      path: 'tag',
      component: () => import('@/views/content/tag/index'),
      name: 'ContentTagManage',
      meta: {
        title: '标签管理',
        noCache: true
      }
    }
  ]
}

export default contentRouter
