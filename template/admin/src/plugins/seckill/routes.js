import settings from '@/settings'
import Activity from './views/activity/index.vue'
import Form from './views/activity/form.vue'

const prefix = '/' + (settings.prefix ? settings.prefix + '/' : '')

export default [
  {
    path: prefix + 'seckill',
    name: 'seckillLayout',
    component: () => import('@/layout'),
    meta: {
      title: '秒杀管理',
      pluginCode: 'seckill'
    },
    children: [
      {
        path: 'activity',
        name: 'seckillActivity',
        component: Activity,
        meta: { title: '秒杀管理', pluginCode: 'seckill' }
      },
      {
        path: 'activity/create',
        name: 'seckillActivityCreate',
        component: Form,
        hidden: true,
        meta: { title: '新建秒杀活动', noCache: true, activeMenu: prefix + 'seckill/activity', pluginCode: 'seckill' }
      },
      {
        path: 'activity/:id/edit',
        name: 'seckillActivityEdit',
        component: Form,
        hidden: true,
        meta: { title: '编辑秒杀活动', noCache: true, activeMenu: prefix + 'seckill/activity', pluginCode: 'seckill' }
      }
    ]
  }
]
