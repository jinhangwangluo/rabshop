import settings from '@/settings'

const prefix = '/' + (settings.prefix ? settings.prefix + '/' : '')
export default {
  path: prefix + 'setting',
  component: () => import('@/layout'),
  name: 'setting',
  meta: {
    title: '系统设置',
    icon: 'el-icon-setting'
  },
  children: [
    {
      path: 'config',
      component: () => import('@/views/setting/config/index'),
      name: 'system_config',
      meta: {
        title: '配置管理'
      }
    },
    {
      path: 'file/list',
      component: () => import('@/views/setting/file/index'),
      name: 'SettingFile',
      meta: {
        title: '附件管理',
        noCache: true
      }
    },
    {
      path: 'payment_config',
      component: () => import('@/views/setting/paymentConfig/index'),
      name: 'PaymentConfig',
      meta: {
        title: '支付配置',
        noCache: true
      }
    },
    {
      path: 'file/uploader_watermark',
      component: () => import('@/views/setting/uploaderWatermark/index'),
      name: 'UploaderWatermark',
      meta: {
        title: '水印配置',
        noCache: true
      }
    },
    {
      path: 'message_push',
      component: () => import('@/views/setting/messagePush/index'),
      name: 'MessagePush',
      meta: {
        title: '消息推送',
        noCache: true
      }
    },
    {
      path: 'express',
      component: () => import('@/views/setting/express/index'),
      name: 'ExpressCompany',
      meta: {
        title: '快递公司',
        noCache: true
      }
    },
    {
      path: 'combination_data',
      component: () => import('@/views/setting/combinationData/index'),
      name: 'CombinationData',
      meta: {
        title: '组合数据',
        noCache: true
      }
    },
    {
      path: 'combination_data/list',
      component: () => import('@/views/setting/combinationData/list'),
      name: 'CombinationDataList',
      meta: {
        title: '组合数据列表',
        noCache: true,
        activeMenu: '/setting/combination_data'
      },
      hidden: true
    },
    {
      path: 'config/designer',
      component: () => import('@/views/setting/config/designer'),
      name: 'ConfigFormDesigner',
      meta: {
        title: '表单设计器',
        noCache: true,
        activeMenu: '/setting/config'
      },
      hidden: true
    },
    {
      path: 'config/form/:id',
      name: 'ConfigFormRender',
      component: () => import('@/views/setting/config/form.vue'),
      meta: {
        title: '表单预览'
      }
    },
    {
      path: 'agreement',
      component: () => import('@/views/setting/agreement/index'),
      name: 'AgreementSetting',
      meta: {
        title: '协议管理',
        noCache: true
      }
    },
    {
      path: 'about',
      component: () => import('@/views/setting/about/index'),
      name: 'AboutSetting',
      meta: {
        title: '关于我们',
        noCache: true
      }
    },
    {
      path: 'sensitive_words',
      component: () => import('@/views/setting/sensitiveWords/index'),
      name: 'SensitiveWords',
      meta: {
        title: '敏感词管理',
        noCache: true
      }
    }
  ]
}
