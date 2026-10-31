import request from '@/utils/request'

/**
 * 获取商户标签分组列表。
 * 登录要求：需要商户端登录态。
 * @param {Object} params 查询参数。
 * @param {string=} params.name 分组名称关键词。
 * @param {number=} params.tag_type 标签类型：1=内容/专题标签，2=商品标签。
 * @param {number|string=} params.status 状态：0=禁用，1=启用，空字符串=全部。
 * @returns {AxiosPromise} 返回 data.list 树形分组列表和 data.total 总数。
 */
export function getTagCategoryList(params) {
  return request({ url: '/content/tag_category', method: 'get', params })
}

/**
 * 获取商户标签分组树。
 * 登录要求：需要商户端登录态。
 * @param {Object} params 查询参数。
 * @param {number=} params.tag_type 标签类型：1=内容/专题标签，2=商品标签。
 * @returns {AxiosPromise} 返回树形分组数组，节点包含 id、pid、name、path、label、value、children。
 */
export function getTagCategoryTree(params) {
  return request({ url: '/content/tag_category/tree', method: 'get', params })
}

/**
 * 新增商户标签分组。
 * 登录要求：需要商户端登录态。
 * @param {Object} data 分组数据。
 * @param {string} data.name 分组名称。
 * @param {number} data.tag_type 标签类型：1=内容/专题标签，2=商品标签。
 * @param {Array<number>=} data.path 上级分组级联路径。
 * @param {number=} data.pid 父级分组 ID。
 * @param {number=} data.sort 排序值。
 * @param {number=} data.status 状态：0=禁用，1=启用。
 * @returns {AxiosPromise} 返回保存后的分组数据。
 */
export function createTagCategory(data) {
  return request({ url: '/content/tag_category', method: 'post', data })
}

/**
 * 更新商户标签分组。
 * 登录要求：需要商户端登录态。
 * @param {number} id 分组 ID。
 * @param {Object} data 分组数据，字段同 createTagCategory。
 * @returns {AxiosPromise} 返回修改结果。
 */
export function updateTagCategory(id, data) {
  return request({ url: `/content/tag_category/${id}`, method: 'put', data })
}

/**
 * 删除商户标签分组。
 * 登录要求：需要商户端登录态。
 * @param {number} id 分组 ID。
 * @returns {AxiosPromise} 返回删除结果；存在子分组或标签时后端会拒绝删除。
 */
export function deleteTagCategory(id) {
  return request({ url: `/content/tag_category/${id}`, method: 'delete' })
}

/**
 * 快捷新增商户标签分组。
 * 登录要求：需要商户端登录态。
 * @param {Object} data 分组数据。
 * @param {string} data.name 分组名称。
 * @param {number} data.tag_type 标签类型：1=内容/专题标签，2=商品标签。
 * @param {number=} data.pid 父级分组 ID。
 * @returns {AxiosPromise} 返回可直接追加到选择器的分组选项：id、value、label、pid。
 */
export function quickAddTagCategory(data) {
  return request({ url: '/content/tag_category/quick_add', method: 'post', data })
}

/**
 * 启用或禁用商户标签分组。
 * 登录要求：需要商户端登录态。
 * @param {number} id 分组 ID。
 * @param {number} status 状态：0=禁用，1=启用。
 * @returns {AxiosPromise} 返回状态修改结果。
 */
export function setTagCategoryStatus(id, status) {
  return request({ url: `/content/tag_category/${id}/show`, method: 'get', params: { status }})
}

/**
 * 获取商户标签分页列表。
 * 登录要求：需要商户端登录态。
 * @param {Object} params 查询参数。
 * @param {string=} params.name 标签名称关键词。
 * @param {number=} params.tag_type 标签类型：1=内容/专题标签，2=商品标签。
 * @param {number|string=} params.category_id 标签分组 ID，空字符串=全部。
 * @param {number|string=} params.status 状态：0=禁用，1=启用，空字符串=全部。
 * @param {number=} params.page 页码。
 * @param {number=} params.limit 每页数量。
 * @returns {AxiosPromise} 返回 data.list 标签列表和分页字段 total、page、limit、totalPage。
 */
export function getTagList(params) {
  return request({ url: '/content/tag', method: 'get', params })
}

/**
 * 获取商户可选标签。
 * 登录要求：需要商户端登录态。
 * @param {Object} params 查询参数。
 * @param {number=} params.tag_type 标签类型：1=内容/专题标签，2=商品标签。
 * @param {number=} params.category_id 标签分组 ID。
 * @param {string=} params.name 标签名称关键词。
 * @returns {AxiosPromise} 返回 data.list，可用于多选组件下拉选项。
 */
export function getTagOptions(params) {
  return request({ url: '/content/tag/options', method: 'get', params })
}

/**
 * 新增商户标签。
 * 登录要求：需要商户端登录态。
 * @param {Object} data 标签数据。
 * @param {string} data.name 标签名称。
 * @param {number} data.tag_type 标签类型：1=内容/专题标签，2=商品标签。
 * @param {number} data.category_id 标签分组 ID。
 * @param {number=} data.effect_type 展示效果：1=自定义颜色，2=图片。
 * @param {string=} data.text_color 文字颜色。
 * @param {string=} data.bg_color 背景颜色。
 * @param {string=} data.border_color 边框颜色。
 * @param {string=} data.icon 标签图片。
 * @param {number=} data.sort 排序值。
 * @param {number=} data.status 状态：0=禁用，1=启用。
 * @returns {AxiosPromise} 返回保存后的标签数据，包含 value、label、category_name。
 */
export function createTag(data) {
  return request({ url: '/content/tag', method: 'post', data })
}

/**
 * 更新商户标签。
 * 登录要求：需要商户端登录态。
 * @param {number} id 标签 ID。
 * @param {Object} data 标签数据，字段同 createTag。
 * @returns {AxiosPromise} 返回修改结果。
 */
export function updateTag(id, data) {
  return request({ url: `/content/tag/${id}`, method: 'put', data })
}

/**
 * 删除商户标签。
 * 登录要求：需要商户端登录态。
 * @param {number} id 标签 ID。
 * @returns {AxiosPromise} 返回删除结果，后端会同步清理该标签关联。
 */
export function deleteTag(id) {
  return request({ url: `/content/tag/${id}`, method: 'delete' })
}

/**
 * 快捷新增商户标签。
 * 登录要求：需要商户端登录态。
 * @param {Object} data 标签数据，至少包含 name、tag_type、category_id。
 * @returns {AxiosPromise} 返回可直接追加到选择器的标签选项：id、value、label、name。
 */
export function quickAddTag(data) {
  return request({ url: '/content/tag/quick_add', method: 'post', data })
}

/**
 * 导入 AI 建议标签。
 * 登录要求：需要商户端登录态。
 * @param {Object} data 导入参数。
 * @param {Array<Object|string>} data.tag_suggestions AI 建议标签，支持 name、reason、text_color、bg_color、border_color。
 * @param {number=} data.tag_type 标签类型：1=内容/专题标签，2=商品标签。
 * @param {number=} data.category_id 标签分组 ID，不传时后端自动使用或创建“AI推荐”分组。
 * @returns {AxiosPromise} 返回 tag_ids、tag_list、created_count、exists_count、category_id。
 */
export function importAiTags(data) {
  return request({ url: '/content/tag/ai_import', method: 'post', data })
}

/**
 * 启用或禁用商户标签。
 * 登录要求：需要商户端登录态。
 * @param {number} id 标签 ID。
 * @param {number} status 状态：0=禁用，1=启用。
 * @returns {AxiosPromise} 返回状态修改结果。
 */
export function setTagStatus(id, status) {
  return request({ url: `/content/tag/${id}/show`, method: 'get', params: { status }})
}

/**
 * 获取商户资源已绑定标签。
 * 登录要求：需要商户端登录态。
 * @param {Object} params 查询参数。
 * @param {'article'|'special'|'product'} params.relation_type 关联资源类型。
 * @param {number} params.relation_id 关联资源 ID。
 * @returns {AxiosPromise} 返回 data.tag_ids 与 data.tag_list。
 */
export function getTagRelation(params) {
  return request({ url: '/content/tag/relation', method: 'get', params })
}

/**
 * 保存商户资源标签。
 * 登录要求：需要商户端登录态。
 * @param {Object} data 保存参数。
 * @param {'article'|'special'|'product'} data.relation_type 关联资源类型。
 * @param {number} data.relation_id 关联资源 ID。
 * @param {number} data.tag_type 标签类型：1=内容/专题标签，2=商品标签。
 * @param {Array<number>} data.tag_ids 标签 ID 数组。
 * @returns {AxiosPromise} 返回同步后的 data.tag_ids 与 data.tag_list。
 */
export function saveTagRelation(data) {
  return request({ url: '/content/tag/relation', method: 'put', data })
}
