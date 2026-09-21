/** 创建空活动表单。
 * @returns {Object} 未发布活动草稿。
 */
export function createActivityDraft() {
  return { id: 0, install_generation: '', merchant_id: 0, title: '', cover: '', date_range: [], slots: [{ start_time: '09:00', end_time: '10:00' }], products: [], status: 0 }
}

/** 将有效批量价格和每场额度应用到该商品已勾选且有库存的规格。
 * @param {Object} product 已选普通商品与 SKU。
 * @param {Object} values 可选的 seckill_price、quota；不修改真实库存。
 * @returns {number} 更新的 SKU 数量。
 */
export function applyBatchSkuValues(product, values) {
  const price = values.seckill_price
  const quota = values.quota
  if (price == null && quota == null) throw new Error('请填写批量秒杀价或每场额度')
  if (price != null && (!Number.isFinite(Number(price)) || Number(price) <= 0 || Math.round(Number(price) * 100) !== Number(price) * 100)) throw new Error('批量秒杀价必须是有效金额')
  if (quota != null && (!Number.isInteger(Number(quota)) || Number(quota) < 1 || Number(quota) > 999999)) throw new Error('批量每场额度必须是正整数')
  const selected = product.skus.filter(sku => sku.selected && Number(sku.stock) > 0)
  if (!selected.length) throw new Error('请先勾选有真实库存的规格')
  if (price != null && selected.some(sku => Number(sku.price) > 0 && Number(price) > Number(sku.price))) throw new Error('秒杀价不能高于普通价格')
  for (const sku of selected) {
    if (price != null) sku.seckill_price = Number(price)
    if (quota != null) sku.quota = Number(quota)
  }
  return selected.length
}

/** 合并最新普通 SKU 与活动配置，保留已失效参与规格供用户明确取消。
 * @param {Object} product 当前活动商品。
 * @param {Object[]} ordinarySkus 最新普通商品规格。
 * @returns {void} 就地更新可编辑 SKU 行。
 */
export function mergeActivitySkus(product, ordinarySkus) {
  const existing = new Map(product.skus.map(sku => [Number(sku.sku_id), sku]))
  const currentIds = new Set()
  const rows = ordinarySkus.map(sku => {
    const id = Number(sku.id)
    currentIds.add(id)
    const configured = existing.get(id)
    return { ...sku, ...configured, sku_id: id, selected: !!configured?.selected, seckill_price: configured ? Number(configured.seckill_price) : Number(sku.price), quota: configured ? Number(configured.quota) : 1, price: sku.price, stock: sku.stock, available: true }
  })
  for (const sku of product.skus) {
    if (!currentIds.has(Number(sku.sku_id))) rows.push({ ...sku, stock: 0, available: false })
  }
  product.skus = rows
}

/** 提供对应字段的错误位置，供表单导航与 SKU 行就地展示。
 * @param {Object} form 活动草稿。
 * @returns {Object} 活动、时段、商品及 SKU 的字段错误。
 */
export function activityFieldErrors(form) {
  const errors = { products: {}}
  if (!String(form.title || '').trim()) errors.title = '请输入活动名称'
  if (!Array.isArray(form.date_range) || form.date_range.length !== 2) errors.date_range = '请选择活动日期'
  else {
    const start = Date.parse(form.date_range[0] + 'T00:00:00Z')
    const end = Date.parse(form.date_range[1] + 'T00:00:00Z')
    if (!Number.isFinite(start) || !Number.isFinite(end) || end < start || (end - start) / 86400000 >= 31) errors.date_range = '活动日期范围不能超过31天'
  }
  if (!Array.isArray(form.slots) || !form.slots.length || form.slots.length > 12) errors.slots = '请设置1到12个每日时段'
  else {
    const slots = form.slots.map(slot => ({ start: slot.start_time, end: slot.end_time })).sort((a, b) => String(a.start).localeCompare(String(b.start)))
    for (let index = 0; index < slots.length; index += 1) {
      const slot = slots[index]
      if (!/^([01]\d|2[0-3]):[0-5]\d$/.test(slot.start || '') || !/^([01]\d|2[0-3]):[0-5]\d$/.test(slot.end || '') || slot.end <= slot.start) { errors.slots = '时段结束时间必须晚于开始时间且不能跨日'; break }
      if (index && slot.start < slots[index - 1].end) { errors.slots = '每日时段不能重叠'; break }
    }
  }
  if (!Array.isArray(form.products) || !form.products.length || form.products.length > 50) { errors.productSelection = '请选择1到50个普通商品'; return errors }
  const productIds = new Set()
  let count = 0
  for (const product of form.products) {
    const id = Number(product.product_id)
    const productErrors = { skus: {}}
    errors.products[id] = productErrors
    if (productIds.has(id)) productErrors.general = '不能重复选择商品'
    productIds.add(id)
    if (!Number.isInteger(Number(product.user_limit)) || Number(product.user_limit) < 1) productErrors.user_limit = '每场商品限购必须为正整数'
    const skus = (product.skus || []).filter(sku => sku.selected)
    if (!skus.length) productErrors.general = '每个商品至少选择一个参与规格'
    count += skus.length
    for (const sku of skus) {
      const skuErrors = {}
      productErrors.skus[sku.sku_id] = skuErrors
      if (sku.available === false) skuErrors.seckill_price = '普通商品规格已失效，请取消参与'
      else if (!Number.isFinite(Number(sku.seckill_price)) || Number(sku.seckill_price) <= 0) skuErrors.seckill_price = '请填写有效的秒杀价格'
      else if (Number(sku.price) > 0 && Number(sku.seckill_price) > Number(sku.price)) skuErrors.seckill_price = '秒杀价不能高于普通价格'
      if (!Number.isInteger(Number(sku.quota)) || Number(sku.quota) < 1) skuErrors.quota = '每场秒杀额度必须为正整数'
    }
  }
  if (count > 100) errors.productSelection = '参与规格总数不能超过100个'
  return errors
}

/** 校验表单的活动上限和完整 SKU 配置。
 * @param {Object} form 活动草稿。
 * @returns {string} 校验失败文案，成功为空。
 */
export function validateActivityDraft(form) {
  const errors = activityFieldErrors(form)
  for (const field of ['title', 'date_range', 'slots', 'productSelection']) if (errors[field]) return errors[field]
  for (const product of form.products || []) {
    const productErrors = errors.products[product.product_id]
    if (productErrors?.general) return productErrors.general
    if (productErrors?.user_limit) return productErrors.user_limit
    for (const sku of product.skus || []) {
      const skuErrors = productErrors?.skus[sku.sku_id]
      if (skuErrors?.seckill_price) return skuErrors.seckill_price
      if (skuErrors?.quota) return skuErrors.quota
    }
  }
  return ''
}

/** 仅提交服务端活动字段，不传展示资料和未选择的 SKU。
 * @param {Object} form 活动草稿。
 * @returns {Object} 新增/编辑请求体。
 */
export function buildActivityPayload(form) {
  return {
    install_generation: form.install_generation,
    merchant_id: Number(form.merchant_id || 0),
    title: String(form.title).trim(),
    cover: form.cover,
    start_date: form.date_range[0],
    end_date: form.date_range[1],
    slots: form.slots.map(slot => ({ start_time: slot.start_time, end_time: slot.end_time })),
    products: form.products.map((product, index) => ({
      id: Number(product.id || 0),
      product_id: Number(product.product_id),
      user_limit: Number(product.user_limit),
      sort: form.products.length - index - 1,
      skus: product.skus.filter(sku => sku.selected).map(sku => ({ sku_id: Number(sku.sku_id), seckill_price: Number(sku.seckill_price).toFixed(2), quota: Number(sku.quota) }))
    })),
    status: Number(form.status)
  }
}

/** 将活动详情转为可编辑草稿，保留所有参与规格配置。
 * @param {Object} data 服务端活动详情。
 * @returns {Object} 表单数据。
 */
export function draftFromActivity(data) {
  return {
    ...createActivityDraft(),
    ...data,
    date_range: [data.start_date, data.end_date],
    slots: data.slots.map(slot => ({ ...slot })),
    products: data.products.map(product => ({ ...product, skus: product.skus.map(sku => ({ ...sku, selected: true, sku_id: Number(sku.sku_id || sku.id) })) }))
  }
}
