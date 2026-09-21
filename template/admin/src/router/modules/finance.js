import settings from '@/settings';

const prefix = '/' + (settings.prefix ? settings.prefix + '/' : '')
const financeRouter = {
  path: prefix + 'finance',
  component: () => import('@/layout'),
  name: 'Finance',
  meta: {
    title: '财务管理',
    icon: 'el-icon-money'
  },
  children: [
    {
      path: 'user_extract',
      component: () => import('@/views/finance/user_extract/index'),
      name: 'UserExtract',
      meta: {
        title: '提现申请',
        noCache: true
      }
    },
    {
      path: 'user_recharge',
      component: () => import('@/views/finance/user_recharge/index'),
      name: 'UserRecharge',
      meta: {
        title: '用户充值',
        noCache: true
      }
    },
    {
      path: 'finance',
      component: () => import('@/views/finance/finance/bill'),
      name: 'FinanceBill',
      meta: {
        title: '财务账单',
        noCache: true
      }
    }
  ]
}

export default financeRouter
