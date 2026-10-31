import { get, post } from './request'
import { AUTH_MODES } from '@/utils/auth'

const PRODUCT_OPTIONAL_AUTH = {
  authMode: AUTH_MODES.OPTIONAL,
  loginRedirect: false
}

/**
 * 获取移动端商品分类树。
 *
 * 使用场景：分类页商品分类宫格、顶部 Tab 分类、层级展开商品组件初始化时调用。
 * 匿名可访问；`category_ids` 为空表示展示全部分类，非空表示只返回选中分类及其子级。
 *
 * @param {Object} params 查询参数。
 * @param {Array<number>|string} params.category_ids 分类 ID 数组或逗号串。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data` 为商品分类树数组。
 */
export function getProductCategories(params = {}, options = {}) {
  return get('product/categories', params, {
    ...PRODUCT_OPTIONAL_AUTH,
    ...options
  })
}

/**
 * 按分类获取移动端商品参数模板。
 *
 * 使用场景：商品列表筛选面板或商品详情相关联动模块，需要根据分类动态展示参数筛选项。
 * 匿名可访问；分类 ID 为空时返回空模板。
 *
 * @param {Object} params 查询参数。
 * @param {number|string} params.category_id 分类 ID。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data` 为参数模板对象。
 */
export function getProductAttributeTemplate(params = {}, options = {}) {
  return get('product/attribute/template/' + (params.category_id || params.categoryId || 0), params, {
    ...PRODUCT_OPTIONAL_AUTH,
    ...options
  })
}

/**
 * 获取移动端品牌筛选选项。
 *
 * 使用场景：商品列表筛选面板中展示品牌筛选项，默认返回平台与商户已启用品牌。
 * 匿名可访问，登录后不影响结果。
 *
 * @param {Object} params 查询参数，当前无需传参。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data` 为品牌选项数组。
 */
export function getProductBrandOptions(params = {}, options = {}) {
  return get('product/brand/options', params, {
    ...PRODUCT_OPTIONAL_AUTH,
    ...options
  })
}

/**
 * 获取移动端商品属性筛选选项。
 *
 * 使用场景：商品列表筛选面板中展示属性名称与属性值，默认返回平台与商户已启用属性模板。
 * 匿名可访问，登录后不影响结果。
 *
 * @param {Object} params 查询参数，当前无需传参。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data` 为属性模板数组。
 */
export function getProductAttributeOptions(params = {}, options = {}) {
  return get('product/attribute/options', params, {
    ...PRODUCT_OPTIONAL_AUTH,
    ...options
  })
}

/**
 * 获取移动端商品分页列表。
 *
 * 使用场景：分类页层级展开商品组件按当前分类加载商品、下拉刷新、触底加载更多时调用。
 * 匿名可访问，登录后可附带用户态。
 *
 * @param {Object} params 查询参数。
 * @param {number|string} params.category_id 商品分类 ID。
 * @param {number|string} params.merchant_id 商户 ID，传入后仅查询该商户商品。
 * @param {string} params.keyword 商品名称或关键词搜索。
 * @param {number|string} params.page 页码。
 * @param {number|string} params.limit 每页条数。
 * @param {string} params.sort 排序方式：`sales`、`price_asc`、`price_desc`、`view` 或空。
 * @param {number|string} params.include_child_categories 是否包含当前分类的子孙分类，1 表示包含。
 * @param {number|string} params.brand_id 品牌 ID。
 * @param {number|string} params.price_min 最低价格。
 * @param {number|string} params.price_max 最高价格。
 * @param {string} params.merchant_type 商户类型：`self`、`non_self` 或空。
 * @param {number|string} params.is_free_shipping 是否包邮，1 表示包邮。
 * @param {Array<Object>|string} params.param_filters 参数筛选条件，按 `param_key + values[]` 传入。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data` 返回 `list/total/page/limit/totalPage`。
 */
export function getProductList(params = {}, options = {}) {
  return get('product/list', params, {
    ...PRODUCT_OPTIONAL_AUTH,
    ...options
  })
}

/**
 * 获取移动端商品详情。
 *
 * 使用场景：商品详情页进入时获取商品主信息、规格数据与展示统计。
 * 匿名可访问，登录后可附带用户态。
 *
 * @param {Object} params 查询参数。
 * @param {number|string} params.id 商品 ID。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data` 为商品详情对象。
 */
export function getProductDetail(params = {}, options = {}) {
  return get('product/detail', params, {
    ...PRODUCT_OPTIONAL_AUTH,
    ...options
  })
}

/**
 * 获取移动端商品规格数据。
 *
 * 使用场景：商品详情页规格弹窗初始化、切换规格时读取可选规格与 SKU 唯一值。
 * 匿名可访问，登录后可附带用户态。
 *
 * @param {Object} params 查询参数。
 * @param {number|string} params.id 商品 ID。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data` 为 `spec_list/sku_list/default_sku`。
 */
export function getProductSku(params = {}, options = {}) {
  return get('product/sku', params, {
    ...PRODUCT_OPTIONAL_AUTH,
    ...options
  })
}

/**
 * 获取当前商品可用优惠券。
 *
 * 使用场景：商品详情页优惠券区域展示当前商品可领取或可使用的优惠券。
 * 匿名可访问，登录后返回当前用户领取状态；不自动跳转登录页。
 *
 * @param {Object} params 查询参数。
 * @param {number|string} params.id 商品 ID。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data.list` 为优惠券列表。
 */
export function getProductCoupons(params = {}, options = {}) {
  return get('product/coupons', params, {
    ...PRODUCT_OPTIONAL_AUTH,
    loginRedirect: false,
    ...options
  })
}

/**
 * 获取当前商品收藏状态。
 *
 * 使用场景：商品详情页进入时查询当前用户是否已收藏该商品；匿名状态下返回未收藏。
 *
 * @param {Object} params 查询参数。
 * @param {number|string} params.product_id 商品 ID，兼容传 id。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data.is_favorite` 表示是否已收藏。
 */
export function getProductFavoriteStatus(params = {}, options = {}) {
  return get('product/favorite/status', params, {
    ...PRODUCT_OPTIONAL_AUTH,
    loginRedirect: false,
    ...options
  })
}

/**
 * 切换当前商品收藏状态。
 *
 * 使用场景：商品详情页点击底部收藏按钮；需要登录。
 *
 * @param {Object} data 请求参数。
 * @param {number|string} data.product_id 商品 ID，兼容传 id。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data.is_favorite` 返回切换后的收藏状态。
 */
export function toggleProductFavorite(data = {}, options = {}) {
  return post('product/favorite/toggle', data, {
    authMode: AUTH_MODES.REQUIRED,
    ...options
  })
}

/**
 * 获取当前商品高分评价。
 *
 * 使用场景：商品详情页评价模块展示高分评价与好评率统计。
 * 匿名可访问，登录后可附带用户态。
 *
 * @param {Object} params 查询参数。
 * @param {number|string} params.id 商品 ID。
 * @param {number|string} params.page 页码。
 * @param {number|string} params.limit 每页数量。
 * @param {number|string} params.star_min 最低星级。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data` 为 `list/summary`。
 */
export function getProductReviews(params = {}, options = {}) {
  return get('product/reviews', params, {
    ...PRODUCT_OPTIONAL_AUTH,
    ...options
  })
}
