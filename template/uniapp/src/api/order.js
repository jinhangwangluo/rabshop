import { get, post } from './request'
import { AUTH_MODES } from '@/utils/auth'

const ORDER_REQUEST_OPTIONS = {
  authMode: AUTH_MODES.REQUIRED
}

/**
 * 获取订单确认结算数据。
 *
 * @param {Object} data 确认单参数，需要登录。
 * @param {Array<number>|string} data.cart_ids 购物车 ID 列表。
 * @param {number} [data.is_new] 是否立即购买。
 * @param {number} [data.platform_coupon_id] 平台优惠券用户券 ID。
 * @param {Array<Object>} [data.merchant_coupon_ids] 多商户优惠券选择，每项包含 merchant_id、coupon_id。
 * @param {Array<Object>} [data.merchant_remarks] 多商户备注，每项包含 merchant_id、remark。
 * @param {Object} [data.plugin_payload] 插件下单载荷，按插件编码命名空间传递。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时返回确认单分组、优惠与汇总金额。
 */
export function confirmOrder(data = {}, options = {}) {
  return post('order/confirm', data, {
    ...ORDER_REQUEST_OPTIONS,
    ...options
  })
}

/**
 * 创建订单。
 *
 * @param {Object} data 下单参数，需要登录。
 * @param {Array<number>|string} data.cart_ids 购物车 ID 列表。
 * @param {number} [data.is_new] 是否立即购买。
 * @param {number} [data.platform_coupon_id] 平台优惠券用户券 ID。
 * @param {Array<Object>} [data.merchant_coupon_ids] 多商户优惠券选择，每项包含 merchant_id、coupon_id。
 * @param {Array<Object>} [data.merchant_remarks] 多商户备注，每项包含 merchant_id、remark。
 * @param {Object} [data.plugin_payload] 插件下单载荷，按插件编码命名空间传递。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时返回主订单和子订单信息。
 */
export function createOrder(data = {}, options = {}) {
  return post('order/create', data, {
    ...ORDER_REQUEST_OPTIONS,
    ...options
  })
}

/**
 * 获取当前用户订单列表。
 *
 * @param {Object} params 查询参数。
 * @param {number} [params.page] 页码。
 * @param {number} [params.limit] 每页条数。
 * @param {string} [params.status] 订单状态筛选。
 * @param {string} [params.keyword] 订单号关键词。
 * @param {number} [params.merchant_id] 商户 ID，大于 0 时仅返回该商户订单。
 * @param {Object} options 透传给请求封装的附加选项，需要登录。
 * @returns {Promise<Object>} 成功时返回订单分页列表。
 */
export function getOrderList(params = {}, options = {}) {
  return get('order/list', params, {
    ...ORDER_REQUEST_OPTIONS,
    ...options
  })
}

/**
 * 获取当前用户订单详情。
 *
 * @param {Object} params 查询参数。
 * @param {string} [params.order_sn] 订单号。
 * @param {number} [params.order_id] 订单 ID。
 * @param {Object} options 透传给请求封装的附加选项，需要登录。
 * @returns {Promise<Object>} 成功时返回订单详情。
 */
export function getOrderDetail(params = {}, options = {}) {
  return get('order/detail', params, {
    ...ORDER_REQUEST_OPTIONS,
    ...options
  })
}

/**
 * 获取当前用户订单物流详情。
 *
 * @param {Object} params 查询参数。
 * @param {number} [params.order_id] 订单 ID。
 * @param {string} [params.order_sn] 订单号；未传订单 ID 时使用。
 * @param {Object} options 透传给请求封装的附加选项，需要登录。
 * @returns {Promise<Object>} 成功时返回物流公司、收货信息和轨迹列表。
 */
export function getOrderExpress(params = {}, options = {}) {
  return get('order/express', params, {
    ...ORDER_REQUEST_OPTIONS,
    ...options
  })
}

/**
 * 获取订单支付结果及本次到账赠券。
 *
 * @param {Object} params 查询参数。
 * @param {string} [params.order_sn] 订单号。
 * @param {number} [params.order_id] 订单 ID；未传订单号时使用。
 * @param {Object} options 透传给请求封装的附加选项，需要登录。
 * @returns {Promise<Object>} 成功时返回支付状态、订单信息和实际到账赠券列表。
 */
export function getOrderPayResult(params = {}, options = {}) {
  return get('order/payResult', params, {
    ...ORDER_REQUEST_OPTIONS,
    ...options
  })
}

/**
 * 获取当前用户退款列表。
 *
 * @param {Object} params 查询参数。
 * @param {number} [params.page] 页码。
 * @param {number} [params.limit] 每页条数。
 * @param {string} [params.order_sn] 订单号关键词。
 * @param {string} [params.status] 退款筛选：pending 待处理，refunded 已退款，record 申请记录。
 * @param {Object} options 透传给请求封装的附加选项，需要登录。
 * @returns {Promise<Object>} 成功时返回退款分页列表。
 */
export function getOrderRefundList(params = {}, options = {}) {
  return get('order/refund/list', params, {
    ...ORDER_REQUEST_OPTIONS,
    ...options
  })
}

/**
 * 获取当前用户退款详情。
 *
 * @param {Object} params 查询参数。
 * @param {string} [params.refund_sn] 退款单号。
 * @param {string} [params.order_sn] 订单号。
 * @param {number} [params.order_id] 订单 ID。
 * @param {Object} options 透传给请求封装的附加选项，需要登录。
 * @returns {Promise<Object>} 成功时返回退款详情。
 */
export function getOrderRefundDetail(params = {}, options = {}) {
  return get('order/refund/detail', params, {
    ...ORDER_REQUEST_OPTIONS,
    ...options
  })
}

/**
 * 获取订单评价页数据。
 *
 * @param {Object} params 查询参数。
 * @param {number} params.order_id 订单 ID。
 * @param {number} [params.order_goods_id] 订单商品 ID；用于单个商品评价。
 * @param {Object} options 透传给请求封装的附加选项，需要登录。
 * @returns {Promise<Object>} 成功时返回订单、目标商品、评价、可评价状态。
 */
export function getOrderReviewPage(params = {}, options = {}) {
  return get('order/review/page', params, {
    ...ORDER_REQUEST_OPTIONS,
    ...options
  })
}

/**
 * 提交订单评价。
 *
 * @param {Object} data 评价表单。
 * @param {number} data.order_id 订单 ID。
 * @param {number} data.order_goods_id 订单商品 ID，用于单个商品评价。
 * @param {number} data.star 整体评分，1-5。
 * @param {string} data.content 评价内容。
 * @param {Array<string>} data.images 评价图片 URL 列表。
 * @param {number} data.description_star 描述相符评分，1-5。
 * @param {number} data.logistics_star 物流服务评分，1-5。
 * @param {number} data.service_star 服务态度评分，1-5。
 * @param {number} data.is_anonymous 是否匿名，1 是，0 否。
 * @param {Object} options 透传给请求封装的附加选项，需要登录。
 * @returns {Promise<Object>} 成功时返回评价提交结果。
 */
export function submitOrderReview(data = {}, options = {}) {
  return post('order/review', data, {
    ...ORDER_REQUEST_OPTIONS,
    ...options
  })
}

/**
 * 获取当前用户订单状态角标统计。
 *
 * @param {Object} params 查询参数，通常留空。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时返回 pending、paid、completed（待评价角标）、refunding 等统计字段。
 */
export function getOrderBadge(params = {}, options = {}) {
  return get('order/badge', params, {
    ...ORDER_REQUEST_OPTIONS,
    ...options
  })
}

export function cancelOrder(data = {}, options = {}) {
  return post('order/cancel', data, {
    ...ORDER_REQUEST_OPTIONS,
    ...options
  })
}

/**
 * 发起订单支付。
 *
 * @param {Object} data 支付参数。
 * @param {string} data.order_sn 订单号。
 * @param {string} data.pay_type 支付方式：wechat/alipay/yue。
 * @param {string} [data.trade_type] 微信交易类型：h5/official_account/miniapp。
 * @param {string} [data.return_url] H5 支付完成后的同步回跳地址。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 支付发起结果。
 */
export function payOrder(data = {}, options = {}) {
  return post('order/pay', data, {
    ...ORDER_REQUEST_OPTIONS,
    ...options
  })
}

/**
 * 发起订单重付。
 *
 * @param {Object} data 支付参数。
 * @param {string} data.order_sn 订单号。
 * @param {string} data.pay_type 支付方式：wechat/alipay/yue。
 * @param {string} [data.trade_type] 微信交易类型：h5/official_account/miniapp。
 * @param {string} [data.return_url] H5 支付完成后的同步回跳地址。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 支付发起结果。
 */
export function repayOrder(data = {}, options = {}) {
  return post('order/repay', data, {
    ...ORDER_REQUEST_OPTIONS,
    ...options
  })
}

/**
 * 申请订单退款。
 *
 * @param {Object} data 退款参数。
 * @param {string} data.order_sn 订单号。
 * @param {string} [data.reason] 退款原因。
 * @param {Array<Object>} [data.items] 退款商品明细；每项包含 order_goods_id、refund_num。
 * @param {Object} options 透传给请求封装的附加选项，需要登录。
 * @returns {Promise<Object>} 成功时返回退款申请结果。
 */
export function refundOrder(data = {}, options = {}) {
  return post('order/refund', data, {
    ...ORDER_REQUEST_OPTIONS,
    ...options
  })
}

/**
 * 取消当前用户订单退款申请。
 *
 * @param {Object} data 提交参数。
 * @param {string} data.order_sn 订单号。
 * @param {Object} options 透传给请求封装的附加选项，需要登录。
 * @returns {Promise<Object>} 成功时返回取消后的订单详情。
 */
export function cancelRefundOrder(data = {}, options = {}) {
  return post('order/refund/cancel', data, {
    ...ORDER_REQUEST_OPTIONS,
    ...options
  })
}

export function receiptOrder(data = {}, options = {}) {
  return post('order/receipt', data, {
    ...ORDER_REQUEST_OPTIONS,
    ...options
  })
}

export function deleteOrder(data = {}, options = {}) {
  return post('order/delete', data, {
    ...ORDER_REQUEST_OPTIONS,
    ...options
  })
}

/**
 * 获取当前终端可用的支付方式列表及用户余额。
 *
 * @param {Object} params 查询参数，需包含 terminal 字段。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时返回 methods 和 balance。
 */
export function getPaymentMethods(params = {}, options = {}) {
  return get('order/paymentMethods', params, {
    ...ORDER_REQUEST_OPTIONS,
    ...options
  })
}
