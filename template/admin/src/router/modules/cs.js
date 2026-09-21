import settings from '@/settings'

const prefix = '/' + (settings.prefix ? settings.prefix + '/' : '')

const csRouter = {
  path: prefix + 'cs',
  component: () => import('@/layout'),
  redirect: prefix + 'cs/session',
  name: 'Cs',
  meta: {
    title: '客服中心',
    icon: 'el-icon-service'
  },
  children: [
    {
      path: 'session',
      component: () => import('@/views/cs/session/index'),
      name: 'CsSession',
      meta: {
        title: '会话管理',
        noCache: true
      }
    },
    {
      path: 'knowledge',
      component: () => import('@/views/cs/knowledge/index'),
      name: 'CsKnowledge',
      meta: {
        title: '知识库管理',
        noCache: true
      }
    },
    {
      path: 'knowledge/detail/:id',
      component: () => import('@/views/cs/knowledge/KnowledgeBaseDetail'),
      name: 'CsKnowledgeDetail',
      meta: {
        title: '知识库详情',
        noCache: true,
        activeMenu: '/cs/knowledge'
      }
    },
    {
      path: 'script',
      component: () => import('@/views/cs/script/index'),
      name: 'CsScript',
      meta: {
        title: '话术管理',
        noCache: true
      }
    },
    {
      path: 'staff',
      component: () => import('@/views/cs/staff/index'),
      name: 'CsStaff',
      meta: {
        title: '客服人员',
        noCache: true
      }
    },
    {
      path: 'ai-model',
      component: () => import('@/views/cs/ai_model/index'),
      name: 'CsAiModelConfig',
      meta: {
        title: 'AI模型配置',
        noCache: true
      }
    }
  ]
}

export default csRouter
