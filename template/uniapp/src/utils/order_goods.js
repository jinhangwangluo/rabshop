import { navigateByLink } from '@/utils/link'

export const ORDER_TYPE_CONTENT = 1
export const ORDER_TYPE_GOODS = 2
export const ORDER_TYPE_SPECIAL = 3

export const CONTENT_TYPE_NOVEL = 3
export const CONTENT_TYPE_AUDIO = 5
export const CONTENT_TYPE_VIDEO = 6

/**
 * 根据内容类型构建内容详情地址。
 *
 * @param {Object} goods 订单商品行。
 * @returns {string} 内容详情页地址。
 */
export function buildContentDetailUrl(goods = {}) {
  const goodsId = Number(goods.goodsId || goods.goods_id || goods.id || 0)
  if (goodsId <= 0) {
    return ''
  }

  const contentType = Number(goods.contentType || goods.content_type || 0)
  if (contentType === CONTENT_TYPE_NOVEL) {
    return `/pages/contents/content/novel/index?id=${goodsId}`
  }
  if (contentType === CONTENT_TYPE_AUDIO) {
    return `/pages/contents/content/aud/index?id=${goodsId}`
  }
  if (contentType === CONTENT_TYPE_VIDEO) {
    return `/pages/contents/content/vid/index?id=${goodsId}`
  }

  return `/pages/contents/content/detail/index?id=${goodsId}`
}

/**
 * 根据订单商品类型构建详情地址。
 *
 * @param {Object} goods 订单商品行。
 * @param {number|string} fallbackOrderType 订单类型兜底值。
 * @returns {string} 商品、内容或专题详情页地址。
 */
export function buildOrderGoodsDetailUrl(goods = {}, fallbackOrderType = 0) {
  const detailUrl = String(goods.detailUrl || goods.detail_url || '').trim()
  if (detailUrl) {
    return detailUrl
  }

  const goodsId = Number(goods.goodsId || goods.goods_id || goods.id || 0)
  if (goodsId <= 0) {
    return ''
  }

  const orderType = Number(goods.orderType || goods.order_type || fallbackOrderType || 0)
  if (orderType === ORDER_TYPE_GOODS) {
    return `/pages/goods/detail/index?id=${goodsId}`
  }
  if (orderType === ORDER_TYPE_SPECIAL) {
    return `/pages/contents/special/detail/index?id=${goodsId}`
  }

  return buildContentDetailUrl(goods)
}

/**
 * 跳转到订单商品对应详情页。
 *
 * @param {Object} goods 订单商品行。
 * @param {Object} options 附加配置。
 * @param {number|string} options.fallbackOrderType 订单类型兜底值。
 * @returns {boolean} 是否触发跳转。
 */
export function navigateToOrderGoods(goods = {}, options = {}) {
  const url = buildOrderGoodsDetailUrl(goods, options.fallbackOrderType || 0)
  if (!url) {
    uni.showToast({ title: '详情暂不可用', icon: 'none' })
    return false
  }

  return navigateByLink(url, options)
}
