import settings from '@/settings'

const prefix = '/' + (settings.prefix ? settings.prefix + '/' : '')

const orderRouter = {
  path: prefix + 'order',
  component: () => import('@/layout'),
  name: 'Order',
  meta: {
    title: '订单管理',
    icon: 'el-icon-s-order'
  },
  children: [
    {
      path: 'list',
      component: () => import('@/views/order/order/index'),
      name: 'OrderList',
      meta: {
        title: '订单列表',
        noCache: true
      }
    },
    {
      path: 'refund',
      component: () => import('@/views/order/refund/index'),
      name: 'OrderRefundList',
      meta: {
        title: '退款审核',
        noCache: true
      }
    }
  ]
}

export default orderRouter
