import request from '@/utils/request'

/**
 * 商户端商品模块接口封装。
 *
 * baseURL 由 utils/request.js 统一拼接为 /api/merchant。
 * category_id 使用平台分类；category_ids 使用商户自定义分类。
 */

// ==================== 商品库 ====================

/** 获取商品库列表。 */
export function getProductLibraryList(params) {
  return request({ url: '/product/library', method: 'get', params })
}

/** 获取商品库详情。 */
export function getProductLibraryRead(id) {
  return request({ url: `/product/library/${id}`, method: 'get' })
}

/** 新增商品库商品，归属当前商户。 */
export function createProductLibrary(data) {
  return request({ url: '/product/library', method: 'post', data })
}

/** 修改当前商户自建商品库商品。 */
export function updateProductLibrary(id, data) {
  return request({ url: `/product/library/${id}`, method: 'put', data })
}

/** 删除当前商户自建商品库商品。 */
export function deleteProductLibrary(id) {
  return request({ url: `/product/library/${id}`, method: 'delete' })
}

/** 修改当前商户自建商品库商品状态。 */
export function setProductLibraryStatus(id, status) {
  return request({ url: `/product/library/status/${id}`, method: 'get', params: { status }})
}

/** 设置当前商户自建商品库商品共享状态。 */
export function setProductLibraryShare(id, isShare) {
  return request({ url: `/product/library/share/${id}`, method: 'post', data: { is_share: isShare }})
}

/** 获取可选商品库商品。 */
export function getProductLibraryOptions(params) {
  return request({ url: '/product/library/options', method: 'get', params })
}

// ==================== 店铺商品 ====================

/** 获取店铺商品列表。 */
export function getStoreProductList(params) {
  return request({ url: '/product', method: 'get', params })
}

/** 获取店铺商品详情。 */
export function getStoreProductRead(id) {
  return request({ url: `/product/${id}`, method: 'get' })
}

/** 新增店铺商品。 */
export function createStoreProduct(data) {
  return request({ url: '/product', method: 'post', data })
}

/** 修改店铺商品。 */
export function updateStoreProduct(id, data) {
  return request({ url: `/product/${id}`, method: 'put', data })
}

/** 删除店铺商品。 */
export function deleteStoreProduct(id) {
  return request({ url: `/product/${id}`, method: 'delete' })
}

/** 修改店铺商品状态。 */
export function setStoreProductStatus(id, status) {
  return request({ url: `/product/status/${id}`, method: 'get', params: { status }})
}

/** 上架组合商品。 */
export function assembleStoreProduct(data) {
  return request({ url: '/product/assemble', method: 'post', data })
}

/** 获取店铺商品 SKU 列表。 */
export function getStoreProductSkuList(id) {
  return request({ url: `/product/${id}/sku_list`, method: 'get' })
}

/** 保存店铺商品 SKU。 */
export function saveStoreProductSku(id, data) {
  return request({ url: `/product/${id}/sku`, method: 'post', data })
}

/** 删除店铺商品 SKU。 */
export function deleteStoreProductSku(id, skuId) {
  return request({ url: `/product/${id}/sku/delete/${skuId}`, method: 'post' })
}

/** 修改店铺商品 SKU 状态。 */
export function setStoreProductSkuStatus(id, skuId, status) {
  return request({ url: `/product/${id}/sku/status/${skuId}`, method: 'post', data: { status }})
}

/** 修改店铺商品 SKU 排序。 */
export function sortStoreProductSku(id, skuId, sort) {
  return request({ url: `/product/${id}/sku/sort/${skuId}`, method: 'post', data: { sort }})
}

/** 设置店铺商品虚拟展示统计。 */
export function setStoreProductVirtualStats(id, data) {
  return request({ url: `/product/${id}/virtual_stats`, method: 'post', data })
}

// ==================== 商品分类 ====================

/** 获取平台商品分类树，用于商品 category_id。 */
export function getPlatformProductCategoryTree(params) {
  return request({ url: '/product/category/platform_tree', method: 'get', params })
}

/** 获取平台商品分类列表，用于商品 category_id。 */
export function getPlatformProductCategoryList(params) {
  return request({ url: '/product/category/platform_list', method: 'get', params })
}

/** 获取商户自定义商品分类树，用于 category_ids。 */
export function getProductCategoryTree(params) {
  return request({ url: '/product/category/tree', method: 'get', params })
}

/** 获取商户自定义商品分类列表。 */
export function getProductCategoryList(params) {
  return request({ url: '/product/category/list', method: 'get', params })
}

/** 新增商户自定义商品分类。 */
export function createProductCategory(data) {
  return request({ url: '/product/category', method: 'post', data })
}

/** 修改商户自定义商品分类。 */
export function updateProductCategory(id, data) {
  return request({ url: `/product/category/${id}`, method: 'put', data })
}

/** 删除商户自定义商品分类。 */
export function deleteProductCategory(id) {
  return request({ url: `/product/category/${id}`, method: 'delete' })
}

/** 修改商户自定义商品分类状态。 */
export function setProductCategoryStatus(id, status) {
  return request({ url: `/product/category/status/${id}`, method: 'get', params: { status }})
}

// ==================== 商品标签 ====================

/** 获取商品标签分组树。 */
export function getProductTagCategoryTree(params) {
  return request({ url: '/product/tag_category/tree', method: 'get', params })
}

/** 获取商品标签分组列表。 */
export function getProductTagCategoryList(params) {
  return request({ url: '/product/tag_category', method: 'get', params })
}

/** 新增商品标签分组。 */
export function createProductTagCategory(data) {
  return request({ url: '/product/tag_category', method: 'post', data })
}

/** 快捷新增商品标签分组。 */
export function quickCreateProductTagCategory(data) {
  return request({ url: '/product/tag_category/quick_add', method: 'post', data })
}

/** 修改商品标签分组。 */
export function updateProductTagCategory(id, data) {
  return request({ url: `/product/tag_category/${id}`, method: 'put', data })
}

/** 删除商品标签分组。 */
export function deleteProductTagCategory(id) {
  return request({ url: `/product/tag_category/${id}`, method: 'delete' })
}

/** 修改商品标签分组状态。 */
export function setProductTagCategoryStatus(id, status) {
  return request({ url: `/product/tag_category/${id}/show`, method: 'get', params: { status }})
}

/** 获取商品标签列表。 */
export function getProductTagList(params) {
  return request({ url: '/product/tag', method: 'get', params })
}

/** 获取商品标签选项。 */
export function getProductTagOptions(params) {
  return request({ url: '/product/tag/options', method: 'get', params })
}

/** 新增商品标签。 */
export function createProductTag(data) {
  return request({ url: '/product/tag', method: 'post', data })
}

/** 快捷新增商品标签。 */
export function quickCreateProductTag(data) {
  return request({ url: '/product/tag/quick_add', method: 'post', data })
}

/** AI 导入商品标签。 */
export function importProductTagsByAi(data) {
  return request({ url: '/product/tag/ai_import', method: 'post', data })
}

/** 修改商品标签。 */
export function updateProductTag(id, data) {
  return request({ url: `/product/tag/${id}`, method: 'put', data })
}

/** 删除商品标签。 */
export function deleteProductTag(id) {
  return request({ url: `/product/tag/${id}`, method: 'delete' })
}

/** 修改商品标签状态。 */
export function setProductTagStatus(id, status) {
  return request({ url: `/product/tag/${id}/show`, method: 'get', params: { status }})
}

/** 获取商品已绑定标签。 */
export function getProductTagRelation(params) {
  return request({ url: '/product/tag/relation', method: 'get', params })
}

/** 保存商品标签绑定。 */
export function saveProductTagRelation(data) {
  return request({ url: '/product/tag/relation', method: 'put', data })
}

// ==================== 品牌 ====================

/** 获取品牌列表。 */
export function getBrandList(params) {
  return request({ url: '/product/brand', method: 'get', params })
}

/** 新增品牌。 */
export function createBrand(data) {
  return request({ url: '/product/brand', method: 'post', data })
}

/** 修改品牌。 */
export function updateBrand(id, data) {
  return request({ url: `/product/brand/${id}`, method: 'put', data })
}

/** 删除品牌。 */
export function deleteBrand(id) {
  return request({ url: `/product/brand/${id}`, method: 'delete' })
}

/** 修改品牌状态。 */
export function setBrandStatus(id, status) {
  return request({ url: `/product/brand/status/${id}`, method: 'get', params: { status }})
}

/** 获取品牌选项。 */
export function getBrandOptions(params) {
  return request({ url: '/product/brand/options', method: 'get', params })
}

// ==================== 规格 ====================

/** 获取规格模板列表。 */
export function getSpecList(params) {
  return request({ url: '/product/spec', method: 'get', params })
}

/** 新增规格模板。 */
export function createSpec(data) {
  return request({ url: '/product/spec', method: 'post', data })
}

/** 修改规格模板。 */
export function updateSpec(id, data) {
  return request({ url: `/product/spec/${id}`, method: 'put', data })
}

/** 删除规格模板。 */
export function deleteSpec(id) {
  return request({ url: `/product/spec/${id}`, method: 'delete' })
}

/** 修改规格模板状态。 */
export function setSpecStatus(id, status) {
  return request({ url: `/product/spec/status/${id}`, method: 'get', params: { status }})
}

/** 获取规格模板选项。 */
export function getSpecOptions(params) {
  return request({ url: '/product/spec/options', method: 'get', params })
}

// ==================== 商品服务 ====================

/** 获取商品服务列表。 */
export function getServiceList(params) {
  return request({ url: '/product/service', method: 'get', params })
}

/** 新增商品服务。 */
export function createService(data) {
  return request({ url: '/product/service', method: 'post', data })
}

/** 修改商品服务。 */
export function updateService(id, data) {
  return request({ url: `/product/service/${id}`, method: 'put', data })
}

/** 删除商品服务。 */
export function deleteService(id) {
  return request({ url: `/product/service/${id}`, method: 'delete' })
}

/** 修改商品服务状态。 */
export function setServiceStatus(id, status) {
  return request({ url: `/product/service/status/${id}`, method: 'get', params: { status }})
}

/** 获取商品服务选项。 */
export function getServiceOptions(params) {
  return request({ url: '/product/service/options', method: 'get', params })
}

// ==================== 商品分组 ====================

/** 获取商品分组列表。 */
export function getGroupList(params) {
  return request({ url: '/product/group', method: 'get', params })
}

/** 新增商品分组。 */
export function createGroup(data) {
  return request({ url: '/product/group', method: 'post', data })
}

/** 修改商品分组。 */
export function updateGroup(id, data) {
  return request({ url: `/product/group/${id}`, method: 'put', data })
}

/** 删除商品分组。 */
export function deleteGroup(id) {
  return request({ url: `/product/group/${id}`, method: 'delete' })
}

/** 修改商品分组状态。 */
export function setGroupStatus(id, status) {
  return request({ url: `/product/group/status/${id}`, method: 'get', params: { status }})
}

/** 获取商品分组选项。 */
export function getGroupOptions(params) {
  return request({ url: '/product/group/options', method: 'get', params })
}

// ==================== 商品属性 ====================

/** 获取商品参数模板列表。 */
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

/** 获取商品参数模板选项。 */
export function getAttributeOptions(params) {
  return request({ url: '/product/attribute/options', method: 'get', params })
}

/** 按分类获取商品参数模板。 */
export function getAttributeTemplateByCategory(categoryId, params) {
  return request({ url: `/product/attribute/template/${categoryId}`, method: 'get', params })
}

// ==================== 运费模板 ====================

/** 获取运费模板列表。 */
export function getFreightList(params) {
  return request({ url: '/product/freight', method: 'get', params })
}

/** 获取运费模板详情。 */
export function getFreightRead(id) {
  return request({ url: `/product/freight/${id}`, method: 'get' })
}

/** 新增运费模板。 */
export function createFreight(data) {
  return request({ url: '/product/freight', method: 'post', data })
}

/** 修改运费模板。 */
export function updateFreight(id, data) {
  return request({ url: `/product/freight/${id}`, method: 'put', data })
}

/** 删除运费模板。 */
export function deleteFreight(id) {
  return request({ url: `/product/freight/${id}`, method: 'delete' })
}

/** 修改运费模板状态。 */
export function setFreightStatus(id, status) {
  return request({ url: `/product/freight/status/${id}`, method: 'get', params: { status }})
}

/** 设为默认运费模板。 */
export function setFreightDefault(id) {
  return request({ url: `/product/freight/default/${id}`, method: 'post' })
}

/** 获取发货时间选项。 */
export function getDeliveryTimeOptions() {
  return request({ url: '/product/freight/delivery_time_options', method: 'get' })
}

/** 获取地区树。 */
export function getCityTree() {
  return request({ url: '/product/freight/city_tree', method: 'get' })
}

/** 获取指定地区运费规则。 */
export function getFreightRules(id) {
  return request({ url: `/product/freight/rules/${id}`, method: 'get' })
}

/** 获取运费模板选项。 */
export function getFreightOptions(params) {
  return request({ url: '/product/freight/options', method: 'get', params })
}
