import request from '@/utils/request'

/**
 * 商品模块平台端接口封装（商品库/店铺商品/品牌/规格/服务/分组/属性/分类/运费模板）
 * URL 均为相对路径，baseURL 由 utils/request.js 统一拼接 /api/admin。
 * 服务端接口定义见 docs/api/product-api.md。
 */

// ==================== 商品库 ====================

/** 商品库列表 */
export function getProductLibraryList(params) {
  return request({ url: '/product/library', method: 'get', params })
}

/** 商品库详情 */
export function getProductLibraryRead(id) {
  return request({ url: `/product/library/${id}`, method: 'get' })
}

/** 新增商品库商品 */
export function createProductLibrary(data) {
  return request({ url: '/product/library', method: 'post', data })
}

/** 修改商品库商品 */
export function updateProductLibrary(id, data) {
  return request({ url: `/product/library/${id}`, method: 'put', data })
}

/** 删除商品库商品（软删，已上架店铺商品副本不受影响） */
export function deleteProductLibrary(id) {
  return request({ url: `/product/library/${id}`, method: 'delete' })
}

/** 修改商品库商品状态（status=1 上架 / 0 下架） */
export function setProductLibraryStatus(id, status) {
  return request({ url: `/product/library/status/${id}`, method: 'get', params: { status }})
}

/** 设置商品库商品共享状态（is_share=0 私有 / 1 共享） */
export function setProductLibraryShare(id, isShare) {
  return request({ url: `/product/library/share/${id}`, method: 'post', data: { is_share: isShare }})
}

/** 商品库选品选项（最多 200 条） */
export function getProductLibraryOptions(params) {
  return request({ url: '/product/library/options', method: 'get', params })
}

/**
 * 采集第三方商品并写入平台商品库。
 *
 * @param {{url: string}} data 淘宝商品链接。
 * @returns {Promise} 返回新建并规范化后的商品库商品。
 * @description 需要管理员登录；当前服务商为 OneBound 淘宝商品详情接口。
 */
export function collectProduct(data) {
  return request({ url: '/product/collect', method: 'post', data })
}

// ==================== 店铺商品 ====================

/** 店铺商品选项（选择器数据源；platform_only=1 时只返回平台商品） */
export function getStoreProductOptions(params) {
  return request({ url: '/product/options', method: 'get', params })
}

/** 店铺商品列表 */
export function getStoreProductList(params) {
  return request({ url: '/product', method: 'get', params })
}

/** 店铺商品详情 */
export function getStoreProductRead(id) {
  return request({ url: `/product/${id}`, method: 'get' })
}

/** 新增店铺商品（商品库可传多个，library_skus 传 SKU 店铺覆盖值） */
export function createStoreProduct(data) {
  return request({ url: '/product', method: 'post', data })
}

/** 修改店铺商品（library_product_ids 传多个时自动走重新选品组合） */
export function updateStoreProduct(id, data) {
  return request({ url: `/product/${id}`, method: 'put', data })
}

/** 删除店铺商品 */
export function deleteStoreProduct(id) {
  return request({ url: `/product/${id}`, method: 'delete' })
}

/** 修改店铺商品状态（status=1 上架 / 0 下架） */
export function setStoreProductStatus(id, status) {
  return request({ url: `/product/status/${id}`, method: 'get', params: { status }})
}

/** 审核商户商品（audit_status=1 通过 / 2 驳回） */
export function auditStoreProduct(id, data) {
  return request({ url: `/product/${id}/audit`, method: 'post', data })
}

/** 上架组合（1 个库商品=单规格；N 个=自动组合多规格） */
export function assembleStoreProduct(data) {
  return request({ url: '/product/assemble', method: 'post', data })
}

/** SKU 列表（含隐藏，按 sort desc, id asc） */
export function getStoreProductSkuList(id) {
  return request({ url: `/product/${id}/sku_list`, method: 'get' })
}

/** 保存 SKU（批量，uniqid 不可修改） */
export function saveStoreProductSku(id, data) {
  return request({ url: `/product/${id}/sku`, method: 'post', data })
}

/** 删除 SKU（软删，唯一值不释放） */
export function deleteStoreProductSku(id, skuId) {
  return request({ url: `/product/${id}/sku/delete/${skuId}`, method: 'post' })
}

/** SKU 隐藏/显示（status=0 隐藏 / 1 正常） */
export function setStoreProductSkuStatus(id, skuId, status) {
  return request({ url: `/product/${id}/sku/status/${skuId}`, method: 'post', data: { status }})
}

/** SKU 排序（sort 越大越靠前） */
export function sortStoreProductSku(id, skuId, sort) {
  return request({ url: `/product/${id}/sku/sort/${skuId}`, method: 'post', data: { sort }})
}

/** 虚拟统计维护（仅前台展示用） */
export function setStoreProductVirtualStats(id, data) {
  return request({ url: `/product/${id}/virtual_stats`, method: 'post', data })
}

// ==================== 商品分类 ====================

/** 商品分类树（type=10 通用分类表） */
export function getProductCategoryTree(params) {
  return request({ url: '/product/category/tree', method: 'get', params })
}

/** 商品分类列表（下拉选项） */
export function getProductCategoryList(params) {
  return request({ url: '/product/category/list', method: 'get', params })
}

/** 新增商品分类 */
export function createProductCategory(data) {
  return request({ url: '/product/category', method: 'post', data })
}

/** 修改商品分类 */
export function updateProductCategory(id, data) {
  return request({ url: `/product/category/${id}`, method: 'put', data })
}

/** 删除商品分类（级联删除子分类） */
export function deleteProductCategory(id) {
  return request({ url: `/product/category/${id}`, method: 'delete' })
}

/** 修改商品分类状态（status=1 显示 / 0 隐藏） */
export function setProductCategoryStatus(id, status) {
  return request({ url: `/product/category/status/${id}`, method: 'get', params: { status }})
}

// ==================== 品牌 ====================

/** 品牌列表 */
export function getBrandList(params) {
  return request({ url: '/product/brand', method: 'get', params })
}

/** 新增品牌 */
export function createBrand(data) {
  return request({ url: '/product/brand', method: 'post', data })
}

/** 修改品牌 */
export function updateBrand(id, data) {
  return request({ url: `/product/brand/${id}`, method: 'put', data })
}

/** 删除品牌 */
export function deleteBrand(id) {
  return request({ url: `/product/brand/${id}`, method: 'delete' })
}

/** 修改品牌状态（status=1 显示 / 0 隐藏） */
export function setBrandStatus(id, status) {
  return request({ url: `/product/brand/status/${id}`, method: 'get', params: { status }})
}

/** 品牌选项 */
export function getBrandOptions(params) {
  return request({ url: '/product/brand/options', method: 'get', params })
}

// ==================== 规格 ====================

/** 规格模板列表（附带 specs 规格维度及其 values 规格值） */
export function getSpecList(params) {
  return request({ url: '/product/spec', method: 'get', params })
}

/** 新增规格模板（一次提交多个规格维度和规格值） */
export function createSpec(data) {
  return request({ url: '/product/spec', method: 'post', data })
}

/** 修改规格模板（一次同步多个规格维度和规格值） */
export function updateSpec(id, data) {
  return request({ url: `/product/spec/${id}`, method: 'put', data })
}

/** 删除规格模板 */
export function deleteSpec(id) {
  return request({ url: `/product/spec/${id}`, method: 'delete' })
}

/** 修改规格状态 */
export function setSpecStatus(id, status) {
  return request({ url: `/product/spec/status/${id}`, method: 'get', params: { status }})
}

/** 规格选项 */
export function getSpecOptions(params) {
  return request({ url: '/product/spec/options', method: 'get', params })
}

// ==================== 商品服务 ====================

/** 商品服务列表 */
export function getServiceList(params) {
  return request({ url: '/product/service', method: 'get', params })
}

/** 新增商品服务 */
export function createService(data) {
  return request({ url: '/product/service', method: 'post', data })
}

/** 修改商品服务 */
export function updateService(id, data) {
  return request({ url: `/product/service/${id}`, method: 'put', data })
}

/** 删除商品服务 */
export function deleteService(id) {
  return request({ url: `/product/service/${id}`, method: 'delete' })
}

/** 修改商品服务状态 */
export function setServiceStatus(id, status) {
  return request({ url: `/product/service/status/${id}`, method: 'get', params: { status }})
}

/** 商品服务选项 */
export function getServiceOptions(params) {
  return request({ url: '/product/service/options', method: 'get', params })
}

// ==================== 商品分组 ====================

/** 商品分组列表 */
export function getGroupList(params) {
  return request({ url: '/product/group', method: 'get', params })
}

/** 新增商品分组 */
export function createGroup(data) {
  return request({ url: '/product/group', method: 'post', data })
}

/** 修改商品分组 */
export function updateGroup(id, data) {
  return request({ url: `/product/group/${id}`, method: 'put', data })
}

/** 删除商品分组 */
export function deleteGroup(id) {
  return request({ url: `/product/group/${id}`, method: 'delete' })
}

/** 修改商品分组状态 */
export function setGroupStatus(id, status) {
  return request({ url: `/product/group/status/${id}`, method: 'get', params: { status }})
}

/** 商品分组选项 */
export function getGroupOptions(params) {
  return request({ url: '/product/group/options', method: 'get', params })
}

// ==================== 商品属性 ====================

/** 商品参数模板列表。 */
export function getAttributeList(params) {
  return request({ url: '/product/attribute', method: 'get', params })
}

/** 新增商品参数模板。 */
export function createAttribute(data) {
  return request({ url: '/product/attribute', method: 'post', data })
}

/** 修改商品参数模板。 */
export function updateAttribute(id, data) {
  return request({ url: `/product/attribute/${id}`, method: 'put', data })
}

/** 删除商品参数模板。 */
export function deleteAttribute(id) {
  return request({ url: `/product/attribute/${id}`, method: 'delete' })
}

/** 修改商品参数模板状态。 */
export function setAttributeStatus(id, status) {
  return request({ url: `/product/attribute/status/${id}`, method: 'get', params: { status }})
}

/** 商品参数模板选项。 */
export function getAttributeOptions(params) {
  return request({ url: '/product/attribute/options', method: 'get', params })
}

/** 按分类获取商品参数模板。 */
export function getAttributeTemplateByCategory(categoryId, params) {
  return request({ url: `/product/attribute/template/${categoryId}`, method: 'get', params })
}

// ==================== 运费模板 ====================

/** 运费模板列表（附带 rule_count） */
export function getFreightList(params) {
  return request({ url: '/product/freight', method: 'get', params })
}

/** 运费模板详情（基础信息 + 默认运费） */
export function getFreightRead(id) {
  return request({ url: `/product/freight/${id}`, method: 'get' })
}

/** 新增运费模板 */
export function createFreight(data) {
  return request({ url: '/product/freight', method: 'post', data })
}

/** 修改运费模板 */
export function updateFreight(id, data) {
  return request({ url: `/product/freight/${id}`, method: 'put', data })
}

/** 删除运费模板 */
export function deleteFreight(id) {
  return request({ url: `/product/freight/${id}`, method: 'delete' })
}

/** 修改运费模板状态 */
export function setFreightStatus(id, status) {
  return request({ url: `/product/freight/status/${id}`, method: 'get', params: { status }})
}

/** 设为默认模板（同商户互斥） */
export function setFreightDefault(id) {
  return request({ url: `/product/freight/default/${id}`, method: 'post' })
}

/** 发货时间选项 */
export function getDeliveryTimeOptions() {
  return request({ url: '/product/freight/delivery_time_options', method: 'get' })
}

/** 地区树（city_area，节点含 pid/label/value） */
export function getCityTree() {
  return request({ url: '/product/freight/city_tree', method: 'get' })
}

/** 指定地区规则列表（附带 city_names） */
export function getFreightRules(id) {
  return request({ url: `/product/freight/rules/${id}`, method: 'get' })
}

/** 运费模板选项 */
export function getFreightOptions(params) {
  return request({ url: '/product/freight/options', method: 'get', params })
}
