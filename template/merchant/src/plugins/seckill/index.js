import routes from './routes'

export const manifest = {
  schema_version: '1.0',
  code: 'seckill',
  name: '秒杀',
  version: '0.1.0',
  publisher: 'local',
  description: '限时秒杀活动插件',
  surfaces: ['backend', 'admin', 'merchant', 'mobile'],
  events: {
    consumes: [
      'product.updated',
      'product.stock.changed',
      'order.created',
      'order.paid',
      'order.cancelled',
      'refund.approved'
    ],
    publishes: [
      'plugin.seckill.activity.started',
      'plugin.seckill.stock.locked',
      'plugin.seckill.order.confirmed',
      'plugin.seckill.stock.released'
    ],
    frontend_consumes: {
      admin: [
        'admin.product.form.submit.before',
        'admin.product.form.submit.after'
      ],
      merchant: [
        'merchant.store.form.submit.before',
        'merchant.store.form.submit.after'
      ],
      mobile: [
        'mobile.order.submit.before',
        'mobile.order.submit.after'
      ]
    }
  }
}

/** 返回插件路由，不向普通商品或店铺表单注入秒杀校验。 */
export function register() { return { routes } }

export { routes }

export default {
  manifest,
  register,
  routes
}
