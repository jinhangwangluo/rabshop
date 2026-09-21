import { get, post } from './request'
import { AUTH_MODES } from '@/utils/auth'

const CART_REQUEST_OPTIONS = {
  authMode: AUTH_MODES.REQUIRED
}

/**
 * 获取购物车列表。
 *
 * 使用场景：购物车页初始化、刷新购物车商品时调用。
 *
 * @param {Object} params 查询参数。
 * @param {number|string} params.include_new 是否包含立即购买临时购物车。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时返回购物车分组、失效商品与结算规则。
 */
export function getCartList(params = {}, options = {}) {
  return get('cart/list', params, {
    ...CART_REQUEST_OPTIONS,
    ...options
  })
}

/**
 * 加入购物车。
 *
 * 使用场景：商品、内容或专题详情页点击加入购物车时调用；专题订单使用 `order_type=3`。
 *
 * @param {Object} data 请求参数。
 * @param {number|string} data.goods_id 资源 ID。
 * @param {number|string} data.cart_num 购买数量。
 * @param {number|string} data.order_type 订单类型：1-内容，2-商品，3-专题。
 * @param {string} data.product_attr_unique 商品规格唯一值，虚拟资源传空。
 * @param {Object} [data.plugin_payload] 插件载荷，必须按插件编码命名空间传递（如 `{ hotel: {...} }`）。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时返回购物车项详情。
 */
export function addCart(data = {}, options = {}) {
  return post('cart/add', data, {
    ...CART_REQUEST_OPTIONS,
    ...options
  })
}

/**
 * 立即购买。
 *
 * 使用场景：详情页点击立即购买时创建临时购物车并返回结算预览；专题订单使用 `order_type=3`。
 *
 * @param {Object} data 请求参数。
 * @param {number|string} data.goods_id 资源 ID。
 * @param {number|string} data.cart_num 购买数量。
 * @param {number|string} data.order_type 订单类型：1-内容，2-商品，3-专题。
 * @param {string} data.product_attr_unique 商品规格唯一值，虚拟资源传空。
 * @param {Object} [data.plugin_payload] 插件载荷，必须按插件编码命名空间传递（如 `{ hotel: {...} }`）。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时返回 `cart` 与 `preview`。
 */
export function buyNow(data = {}, options = {}) {
  return post('cart/buyNow', data, {
    ...CART_REQUEST_OPTIONS,
    ...options
  })
}

/**
 * 购物车结算预览。
 *
 * 使用场景：订单确认页根据购物车 ID 获取可提交商品、金额汇总与结算规则。
 *
 * @param {Object} data 请求参数。
 * @param {Array<number>|string} data.cart_ids 购物车 ID 列表。
 * @param {number|string} data.is_new 是否立即购买临时购物车。
 * @param {Object} [data.plugin_payload] 插件载荷，服务端以购物车商品归属为准重新校验。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时返回购物车分组、金额汇总与结算规则。
 */
export function settlementCart(data = {}, options = {}) {
  return post('cart/settlement', data, {
    ...CART_REQUEST_OPTIONS,
    ...options
  })
}

/**
 * 修改购物车商品数量。
 *
 * 使用场景：购物车页调整实物商品数量时调用；内容和专题数量固定为 1，不应调用该接口。
 *
 * @param {Object} data 请求参数。
 * @param {number|string} data.cart_id 购物车 ID。
 * @param {number|string} data.cart_num 调整后的购买数量。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时返回更新后的购物车商品详情。
 */
export function changeCartNum(data = {}, options = {}) {
  return post('cart/changeNum', data, {
    ...CART_REQUEST_OPTIONS,
    ...options
  })
}

/**
 * 修改购物车商品规格。
 *
 * 使用场景：购物车页点击商品规格后，在规格弹窗中切换普通商品 SKU。
 *
 * @param {Object} data 请求参数。
 * @param {number|string} data.cart_id 购物车 ID。
 * @param {string} data.product_attr_unique 商品规格唯一值。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时返回更新后的购物车商品详情。
 */
export function changeCartSku(data = {}, options = {}) {
  return post('cart/changeSku', data, {
    ...CART_REQUEST_OPTIONS,
    ...options
  })
}

/**
 * 删除购物车商品。
 *
 * 使用场景：购物车页单个删除或编辑态批量删除时调用。
 *
 * @param {Object} data 请求参数。
 * @param {Array<number>|string} data.ids 购物车 ID 列表。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时返回删除数量。
 */
export function deleteCart(data = {}, options = {}) {
  return post('cart/delete', data, {
    ...CART_REQUEST_OPTIONS,
    ...options
  })
}
