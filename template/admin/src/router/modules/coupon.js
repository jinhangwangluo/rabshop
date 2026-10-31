import settings from '@/settings'

const prefix = '/' + (settings.prefix ? settings.prefix + '/' : '')

const couponRouter = {
  path: prefix + 'coupon',
  component: () => import('@/layout'),
  name: 'Coupon',
  meta: {
    title: '优惠券管理',
    icon: 'el-icon-ticket'
  },
  children: [
    {
      path: 'list',
      component: () => import('@/views/coupon/index'),
      name: 'CouponList',
      meta: {
        title: '优惠券列表',
        noCache: true
      }
    },
    {
      path: 'create',
      component: () => import('@/views/coupon/create'),
      name: 'CouponCreate',
      meta: {
        title: '创建优惠券',
        noCache: true,
        activeMenu: prefix + 'coupon/list'
      }
    },
    {
      path: 'edit/:id',
      component: () => import('@/views/coupon/create'),
      name: 'CouponEdit',
      meta: {
        title: '编辑优惠券',
        noCache: true,
        activeMenu: prefix + 'coupon/list'
      }
    },
    {
      path: 'record',
      component: () => import('@/views/coupon/record/index'),
      name: 'CouponRecord',
      meta: {
        title: '领取记录',
        noCache: true
      }
    }
  ]
}

export default couponRouter
