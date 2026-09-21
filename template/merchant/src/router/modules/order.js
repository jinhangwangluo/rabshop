import settings from '@/settings'

const prefix = '/' + (settings.prefix ? settings.prefix + '/' : '')

const orderRouter = {
  path: prefix + 'order',
  component: () => import('@/layout'),
  redirect: prefix + 'order/list',
  name: 'MerchantOrder',
  meta: {
    title: '订单管理',
    icon: 'el-icon-s-order'
  },
  children: [
    {
      path: 'list',
      component: () => import('@/views/order/order/index'),
      name: 'MerchantOrderList',
      meta: {
        title: '订单列表',
        noCache: true
      }
    },
    {
      path: 'refund',
      component: () => import('@/views/order/refund/index'),
      name: 'MerchantOrderRefundList',
      meta: {
        title: '退款工作台',
        noCache: true
      }
    }
  ]
}

export default orderRouter
