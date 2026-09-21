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
      name: 'MerchantCsSession',
      meta: {
        title: '会话管理',
        noCache: true
      }
    },
    {
      path: 'knowledge',
      component: () => import('@/views/cs/knowledge/index'),
      name: 'MerchantCsKnowledge',
      meta: {
        title: '知识库管理',
        noCache: true
      }
    },
    {
      path: 'knowledge/detail/:id',
      component: () => import('@/views/cs/knowledge/KnowledgeBaseDetail'),
      name: 'MerchantCsKnowledgeDetail',
      meta: {
        title: '知识库详情',
        noCache: true,
        activeMenu: prefix + 'cs/knowledge'
      }
    },
    {
      path: 'script',
      component: () => import('@/views/cs/script/index'),
      name: 'MerchantCsScript',
      meta: {
        title: '话术管理',
        noCache: true
      }
    },
    {
      path: 'staff',
      component: () => import('@/views/cs/staff/index'),
      name: 'MerchantCsStaff',
      meta: {
        title: '客服人员',
        noCache: true
      }
    },
    {
      path: 'ai-model',
      component: () => import('@/views/cs/ai_model/index'),
      name: 'MerchantCsAiModelConfig',
      meta: {
        title: 'AI模型配置',
        noCache: true
      }
    }
  ]
}

export default csRouter
