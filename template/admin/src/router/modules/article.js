import settings from '@/settings';

const prefix = '/' + (settings.prefix ? settings.prefix + '/' : '')
const articleRouter = {
  path: prefix + 'article',
  component: () => import('@/layout'),
  name: 'Article',
  meta: {
    title: '内容管理',
    icon: 'el-icon-document'
  },
  children: [
    {
      path: 'article',
      component: () => import('@/views/article/article/index'),
      name: 'ArticleList',
      meta: {
        title: '内容列表',
        noCache: true
      }
    },
    {
      path: 'article/create',
      component: () => import('@/views/article/article/create'),
      name: 'ArticleCreate',
      meta: {
        title: '添加内容',
        noCache: true,
        activeMenu: '/article/article'
      }
    },
    {
      path: 'article_category',
      component: () => import('@/views/article/article_category/index'),
      name: 'ArticleCategory',
      meta: {
        title: '内容分类',
        noCache: true
      }
    },
    {
      path: 'tag',
      component: () => import('@/views/content/tag/index'),
      name: 'ContentTagManage',
      meta: {
        title: '标签管理',
        noCache: true
      }
    },
    {
      path: 'comment',
      component: () => import('@/views/content/comment/index'),
      name: 'ContentComment',
      meta: {
        title: '评论管理',
        noCache: true
      }
    }
  ]
}

export default articleRouter
