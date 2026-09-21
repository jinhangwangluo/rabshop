import settings from '@/settings'

const prefix = '/' + (settings.prefix ? settings.prefix + '/' : '')

const financeRouter = {
  path: prefix + 'finance',
  component: () => import('@/layout'),
  redirect: prefix + 'finance/records',
  name: 'MerchantFinance',
  meta: {
    title: '资金管理',
    icon: 'el-icon-s-finance'
  },
  children: [
    {
      path: 'records',
      component: () => import('@/views/finance/records'),
      name: 'MerchantFinanceRecords',
      meta: {
        title: '资金流水',
        noCache: true
      }
    },
    {
      path: 'withdraw',
      component: () => import('@/views/finance/withdraw'),
      name: 'MerchantFinanceWithdraw',
      meta: {
        title: '提现申请',
        noCache: true
      }
    }
  ]
}

export default financeRouter
