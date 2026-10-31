import settings from '@/settings';
const prefix = '/' + (settings.prefix ? settings.prefix + '/' : '')

export default {
  path: prefix + 'merchant',
  component: () => import('@/layout'),
  name: 'Merchant',
  meta: {
    title: '商户管理',
    icon: 'el-icon-s-shop'
  },
  children: [
    {
      path: 'list',
      component: () => import('@/views/merchant/index'),
      name: 'MerchantList',
      meta: { title: '商户列表', noCache: true }
    },
    {
      path: 'apply',
      component: () => import('@/views/merchant/apply'),
      name: 'MerchantApply',
      meta: { title: '入驻申请', noCache: true }
    },
    {
      path: 'withdraw',
      component: () => import('@/views/merchant/withdraw'),
      name: 'MerchantWithdraw',
      meta: { title: '提现管理', noCache: true }
    },
    {
      path: 'menu',
      component: () => import('@/views/merchant/menus'),
      name: 'MerchantMenu',
      meta: { title: '菜单管理', noCache: true }
    },
    {
      path: 'order',
      component: () => import('@/views/merchant/order/index'),
      name: 'MerchantOrderList',
      meta: { title: '订单管理', noCache: true }
    }
  ]
}
