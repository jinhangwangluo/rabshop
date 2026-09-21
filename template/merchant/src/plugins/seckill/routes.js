import Activity from './views/activity/index.vue'
import Form from './views/activity/form.vue'
import settings from '@/settings'

const prefix = '/' + (settings.prefix ? settings.prefix + '/' : '')

export default [
  {
    path: prefix + 'seckill',
    name: 'seckillMerchantLayout',
    component: () => import('@/layout'),
    meta: { title: '秒杀', pluginCode: 'seckill' },
    children: [
      {
        path: 'activity',
        name: 'seckillMerchantActivity',
        component: Activity,
        meta: { title: '秒杀', pluginCode: 'seckill' }
      },
      {
        path: 'activity/create',
        name: 'seckillMerchantActivityCreate',
        component: Form,
        hidden: true,
        meta: { title: '新建秒杀活动', noCache: true, activeMenu: prefix + 'seckill/activity', pluginCode: 'seckill' }
      },
      {
        path: 'activity/:id/edit',
        name: 'seckillMerchantActivityEdit',
        component: Form,
        hidden: true,
        meta: { title: '编辑秒杀活动', noCache: true, activeMenu: prefix + 'seckill/activity', pluginCode: 'seckill' }
      }
    ]
  }
]
