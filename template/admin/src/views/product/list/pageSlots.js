import { nextTick } from 'vue'
import { getProductLibraryRead } from '@/api/product'
import { usePageSlots, modelField, slotAction, PageSlotError } from '@/utils/pageSlots'

const text = maxLength => ({ type: 'string', maxLength })
const integer = (max, nullable = false) => ({ type: 'integer', min: 0, ...(max === undefined ? {} : { max }), nullable })
const ids = (values, maxItems = 200) => ({ type: 'array', unique: true, maxItems, items: { enum: values }})
const options = list => list.map(item => ({ value: item.id ?? item.value, label: item.name || item.label }))
const complete = fn => async(...args) => { await fn(...args); return { ok: true } }

/** 创建 ref、计算状态或自定义读写方法的字段声明。 */
function boundField(id, label, get, set, extra = {}) {
  return { id, label, type: extra.schema?.type || 'string', get, set, ...extra }
}

/** 排序只接受当前行标识的完整排列，不用数组下标定位业务行。 */
function reorder(rows, order, getKey) {
  if (order.length !== rows.length || new Set(order).size !== rows.length) throw new PageSlotError('INVALID_VALUE', '请提供全部行的唯一标识')
  const map = new Map(rows.map(row => [getKey(row), row]))
  return order.map(key => {
    if (!map.has(key)) throw new PageSlotError('INVALID_VALUE', '排序包含失效行')
    return map.get(key)
  })
}

/** 检查级联路径属于当前选项树；商品类目必须选择叶子。 */
function assertPath(nodes, path, leaf = false) {
  let node = null
  let list = nodes
  for (const id of path) {
    node = list.find(item => Number(item.value ?? item.id) === id)
    if (!node) throw new PageSlotError('INVALID_VALUE', '分类路径不在当前选项中')
    list = node.children || []
  }
  if (leaf && (!node || list.length)) throw new PageSlotError('INVALID_VALUE', '商品类目必须选择最后一级')
}

/** 校验一次规格修改后的组合数，先校验再赋值，防止无界生成 SKU。 */
function assertSpecCount(cards) {
  const dimensions = cards.filter(card => card.name.trim() && card.values.some(item => item.value.trim()))
  const count = dimensions.reduce((total, card) => total * card.values.filter(item => item.value.trim()).length, 1)
  if (count > 200) throw new PageSlotError('INVALID_VALUE', '规格组合最多支持 200 个 SKU')
}

/**
 * 接入商品主表单和内部操作弹窗，不公开内部身份与库存版本字段。
 * @param {object} b 页面已有状态和业务方法的引用。
 */
export function useProductPageSlots(b) {
  const identity = () => b.productId.value
  const page = (id, title, description) => () => ({ id: `product.${id}`, title: typeof title === 'function' ? title() : title, description, entityId: b.productId.value, mode: b.isEdit.value ? 'edit' : 'create' })
  const successOrThrow = result => {
    if (result?.ok === false) throw new PageSlotError(result.code || 'OPERATION_FAILED', result.message || '操作失败，请检查页面提示')
  }

  async function selectLibrary(value, context) {
    const current = new Map([...b.librarySelected.value, ...b.pickList.value].map(item => [Number(item.id), item]))
    // 未加载的条目只从受权限控制的详情接口获取，不信任外部传入的商品对象。
    const missing = value.filter(id => !current.has(id))
    for (let index = 0; index < missing.length; index += 5) {
      const responses = await Promise.all(missing.slice(index, index + 5).map(id => getProductLibraryRead(id)))
      context.assertCurrent()
      responses.forEach((response, offset) => {
        const item = response.data || {}
        if (Number(item.id) !== missing[index + offset] || Number(item.status) !== 1) throw new PageSlotError('INVALID_VALUE', '库商品不存在或不可选择')
        current.set(Number(item.id), item)
      })
    }
    context.assertCurrent()
    b.collectedFromExternal.value = false
    b.librarySelected.value = value.map(id => current.get(id))
    b.syncLibrarySkuRows()
    b.applyFirstLibrary()
    if (b.specEditorVisible.value) b.refreshSpecEditor()
  }

  function mainFields() {
    const form = b.form
    const fields = [
      modelField(form, 'name', '商品名称', { required: true, schema: text(100), set: value => { form.name = value; b.nameTouched.value = true } }),
      modelField(form, 'goods_type', '商品类型', { schema: { enum: [1, 2] }, options: [{ value: 1, label: '普通商品' }, { value: 2, label: '虚拟商品' }] }),
      modelField(form, 'pics', '商品图片', { schema: { type: 'array', maxItems: 9, items: text(2048) }}),
      modelField(form, 'video', '商品视频', { schema: text(2048) }),
      modelField(form, 'brand_id', '商品品牌', { schema: { enum: ['', 0, ...b.brandOptions.value.map(item => item.id)] }, options: options(b.brandOptions.value) }),
      modelField(form, 'tag_ids', '商品标签', { schema: ids(b.tagOptions.value.map(item => item.id)), options: options(b.tagOptions.value) }),
      modelField(form, 'status', '上下架设置', { schema: { enum: [0, 1] }, options: [{ value: 1, label: '立即上架' }, { value: 0, label: '放入仓库' }] }),
      modelField(form, 'is_free_shipping', '是否包邮', { schema: { enum: [0, 1] }, available: () => form.goods_type !== 2 }),
      modelField(form, 'freight_template_id', '运费模板', { schema: { enum: ['', 0, ...b.freightOptions.value.map(item => item.id)] }, options: options(b.freightOptions.value), available: () => form.goods_type !== 2 && Number(form.is_free_shipping) !== 1 }),
      modelField(form, 'description', '商品简介', { schema: text(200) }),
      modelField(form, 'detail', '商品详情', { type: 'html', schema: text(1000000) }),
      modelField(form, 'keyword', '关键词', { schema: text(100) }),
      modelField(form, 'group_id', '商品分组', { schema: { enum: ['', 0, ...b.groupOptions.value.map(item => item.id)] }, options: options(b.groupOptions.value) }),
      modelField(form, 'service_ids', '服务保障', { schema: ids(b.serviceOptions.value.map(item => item.id)), options: options(b.serviceOptions.value) }),
      modelField(form, 'gift_points', '赠送积分', { schema: integer() }),
      modelField(form, 'sort', '排序', { schema: integer(9999) }),
      boundField('category_paths', '商品类目', () => b.categoryPaths.value, async(value, context) => {
        value.forEach(path => assertPath(b.categoryOptions.value, path, true))
        b.categoryPaths.value = value
        successOrThrow(await b.applyCategoryPaths(context))
      }, { required: true, schema: { type: 'array', unique: true, maxItems: 200, items: { type: 'array', minItems: 1, maxItems: 10, items: integer() }}, options: b.categoryOptions.value }),
      boundField('library_product_ids', '商品库选择', () => b.librarySelected.value.map(item => Number(item.id)), selectLibrary, {
        schema: { type: 'array', unique: true, maxItems: 200, items: { type: 'integer', min: 1 }},
        options: options([...new Map([...b.librarySelected.value, ...b.pickList.value].map(item => [item.id, item])).values()]),
        description: '可填写库商品 ID；打开选品弹窗可搜索更多商品'
      }),
      boundField('spec_type', '规格类型', () => b.currentSpecType.value, value => b.handleSpecTypeChange(value), { schema: { enum: [0, 1] }, available: () => !b.isLibraryMode.value }),
      boundField('spec_template_id', '规格模板', () => b.selectedSpecTemplateId.value, value => {
        const template = b.specTemplates.value.find(item => item.id === value)
        if (template) {
          const count = (template.specs || []).filter(spec => (spec.values || []).some(item => Number(item.status ?? 1) === 1 && String(item.value).trim()))
            .reduce((total, spec) => total * spec.values.filter(item => Number(item.status ?? 1) === 1 && String(item.value).trim()).length, 1)
          if (count > 200) throw new PageSlotError('INVALID_VALUE', '规格模板超过 200 个 SKU')
        }
        b.selectedSpecTemplateId.value = value
        successOrThrow(b.handleSpecTemplateChange(value))
      }, { schema: { enum: ['', ...b.specTemplates.value.map(item => item.id)] }, options: options(b.specTemplates.value), available: () => !b.isLibraryMode.value && b.currentSpecType.value === 1 })
    ]
    b.paramRows.value.forEach(row => fields.push(boundField(`params.${row.row_key}`, row.name, () => row.selected_values, value => { row.selected_values = value }, {
      required: Number(row.is_required) === 1,
      schema: { type: 'array', unique: true, maxItems: 200, items: text(255) },
      options: row.values.map(value => ({ value, label: value })),
      available: () => !b.paramTemplateLoading.value
    })))
    if (!b.isLibraryMode.value && b.currentSpecType.value === 1) {
      fields.push(boundField('spec_order', '规格顺序', () => b.customSpecs.value.map(card => card.rowKey), value => {
        b.customSpecs.value = reorder(b.customSpecs.value, value, card => card.rowKey)
        b.syncCustomSkuRows()
      }, { schema: ids(b.customSpecs.value.map(card => card.rowKey)) }))
      b.customSpecs.value.forEach(card => {
        fields.push(boundField(`specs.${card.rowKey}.name`, '规格名称', () => card.name, value => {
          assertSpecCount(b.customSpecs.value.map(item => item === card ? { ...item, name: value } : item))
          card.name = value
          b.syncCustomSkuRows()
        }, { schema: text(30) }))
        fields.push(boundField(`specs.${card.rowKey}.order`, '规格值顺序', () => card.values.map(item => item.rowKey), value => {
          card.values = reorder(card.values, value, item => item.rowKey)
          b.syncCustomSkuRows()
        }, { schema: ids(card.values.map(item => item.rowKey)) }))
        card.values.forEach(item => fields.push(boundField(`specs.${card.rowKey}.values.${item.rowKey}`, `${card.name || '规格'}的规格值`, () => item.value, value => {
          const candidate = { ...card, values: card.values.map(row => row === item ? { ...row, value } : row) }
          assertSpecCount(b.customSpecs.value.map(row => row === card ? candidate : row))
          item.value = value
          b.handleCustomSpecValueBlur(card, item)
        }, { schema: text(30) })))
      })
    }
    const numbers = ['price', 'ot_price', 'store_price', 'stock', 'weight', 'sort']
    const labels = { price: '价格', ot_price: '划线价', store_price: '成本价', stock: '库存', weight: '重量', sort: '排序', pics: '图片' }
    const numericSchema = (key, nullable = false) => ['stock', 'weight', 'sort'].includes(key) ? integer(undefined, nullable) : { type: 'number', min: 0, precision: 2, nullable }
    b.skuRowsForDisplay.value.forEach(row => {
      const key = b.isLibraryMode.value ? `library-${row.library_product_id}` : `custom-${row.rowKey}`
      numbers.forEach(name => fields.push(boundField(`skus.${key}.${name}`, `${row.sku || key}：${labels[name]}`, () => row[name], value => { row[name] = value }, { schema: numericSchema(name) })))
      fields.push(boundField(`skus.${key}.pics`, `${row.sku || key}：图片`, () => row.pics, value => { row.pics = value }, { schema: text(2048) }))
      if (!b.skuSpecColumns.value.length) fields.push(boundField(`skus.${key}.sku`, 'SKU名称', () => row.sku, value => { row.sku = value }, { schema: text(255) }))
      fields.push(boundField(`skus.${key}.is_default`, '默认SKU', () => row.is_default, value => { row.is_default = value; b.handleDefaultChange(row) }, { schema: { enum: [0, 1] }}))
      fields.push(boundField(`skus.${key}.status`, 'SKU启用状态', () => row.status, async(value, context) => {
        if (Number(row.status) === value) return
        row.status = value
        successOrThrow(await b.handleSkuStatusChange(row, context))
      }, { schema: { enum: [0, 1] }, description: b.isEdit.value && b.isLibraryMode.value ? '修改后立即保存到后端' : '保存商品时持久化' }))
    })
    if (b.hasBatchSkuRow.value) {
      numbers.forEach(name => fields.push(modelField(b.batchSku, name, `批量设置：${labels[name]}`, { id: `batch_sku.${name}`, schema: numericSchema(name, true) })))
      fields.push(modelField(b.batchSku, 'pics', '批量设置：图片', { id: 'batch_sku.pics', schema: text(2048) }))
    }
    return fields
  }

  const specArgs = { type: 'object', required: ['rowKey'], properties: { rowKey: { type: 'integer', min: 1 }}}
  const findSpec = args => {
    const card = b.customSpecs.value.find(item => item.rowKey === args.rowKey)
    if (!card) throw new PageSlotError('INVALID_VALUE', '规格行已失效')
    return card
  }
  usePageSlots({
    page: page('form', () => b.isEdit.value ? '编辑商品' : '添加商品', '维护商品信息、规格库存、配送和详情'), identity,
    ready: () => b.pageReady.value && b.optionsReady.value && !!b.formRef.value,
    busy: () => b.pageLoading.value || b.submitLoading.value || b.skuSaving.value || b.paramTemplateLoading.value,
    fields: mainFields,
    actions: {
      validate: slotAction('校验商品及规格参数', b.validateProductForm),
      save: slotAction('保存商品', b.submitForm),
      close: slotAction('返回商品列表', complete(b.goBack)),
      'step.change': slotAction('切换编辑步骤，0基本信息、1规格、2配送、3详情、4高级信息', complete(({ step }) => {
        if (!b.visibleSteps.value.some(item => item.index === step)) throw new PageSlotError('INVALID_VALUE', '该步骤当前不可用')
        b.activeStep.value = step
      }), { schema: { type: 'object', required: ['step'], properties: { step: integer(4) }}}),
      'library.open': slotAction('打开商品库选品弹窗', complete(b.openPickDialog)),
      'library.clear': slotAction('清空已选库商品', complete(b.clearLibraryProducts)),
      'brand.open': slotAction('新增品牌', complete(b.openBrandNew)),
      'tag.open': slotAction('新增商品标签', complete(b.openTagNew)),
      'category.open': slotAction('新建商品分类', complete(b.openCategoryNew)),
      'collect.open': slotAction('打开商品采集弹窗', complete(b.openCollectDialog), { available: () => b.collectorEnabled.value }),
      'spec.editor.open': slotAction('打开库商品规格编辑弹窗', complete(b.handleOpenSpecStockEditor), { available: () => b.isLibraryMode.value }),
      'spec.add': slotAction('添加自定义规格', complete(b.addCustomSpec), { available: () => !b.isLibraryMode.value && b.customSpecs.value.length < 200 }),
      'spec.remove': slotAction('删除自定义规格', complete(args => { findSpec(args); b.removeCustomSpec(b.customSpecs.value.findIndex(item => item.rowKey === args.rowKey)) }), { schema: specArgs, available: () => !b.isLibraryMode.value }),
      'spec.value.remove': slotAction('删除自定义规格值', complete(args => {
        const card = findSpec(args)
        const item = card.values.find(value => value.rowKey === args.valueKey)
        if (!item) throw new PageSlotError('INVALID_VALUE', '规格值已失效')
        b.removeCustomSpecValue(card, item)
      }), { schema: { ...specArgs, required: ['rowKey', 'valueKey'], properties: { ...specArgs.properties, valueKey: { type: 'integer', min: 1 }}}, available: () => !b.isLibraryMode.value }),
      'sku.batch.apply': slotAction('应用SKU批量设置', b.applyBatchSku, { available: () => b.hasBatchSkuRow.value }),
      'sku.batch.clear': slotAction('清空SKU批量设置', complete(b.clearBatchSku), { available: () => b.hasBatchSkuRow.value }),
      'sku.save': slotAction('保存SKU', b.submitSku, { available: () => b.isEdit.value && b.isLibraryMode.value })
    }
  })

  function dialog(id, title, active, fields, actions, busy, ready = () => true) {
    usePageSlots({ kind: 'dialog', identity, page: page(id, title, title), active, fields, actions, busy, ready })
  }
  const close = active => slotAction('关闭弹窗', complete(() => { active.value = false }))
  dialog('brand', '新增品牌', b.brandNewVisible, () => [modelField(b.brandNewForm, 'name', '品牌名称', { required: true, schema: text(30) })], {
    save: slotAction('保存品牌', b.submitBrandNew), close: close(b.brandNewVisible)
  }, b.brandNewSaving, () => !!b.brandNewFormRef.value)

  dialog('tag', '新增商品标签', b.tagNewVisible, () => [
    modelField(b.tagNewForm, 'category_id', '标签分组', { required: true, schema: { enum: ['', ...b.flatTagCategories.value.map(item => item.id)] }, options: options(b.flatTagCategories.value) }),
    modelField(b.tagNewForm, 'name', '标签名称', { required: true, schema: text(30) }),
    modelField(b.tagNewForm, 'effect_type', '效果设置', { schema: { enum: [1, 2] }, options: [{ value: 1, label: '文字颜色' }, { value: 2, label: '图片' }] }),
    ...['text_color', 'bg_color', 'border_color'].map((key, index) => modelField(b.tagNewForm, key, ['文字颜色', '背景颜色', '边框颜色'][index], { schema: { ...text(50), nullable: true }, available: () => b.tagNewForm.effect_type === 1 })),
    modelField(b.tagNewForm, 'icon', '标签图片', { schema: text(2048), available: () => b.tagNewForm.effect_type === 2 }),
    modelField(b.tagNewForm, 'sort', '排序', { schema: integer() }),
    modelField(b.tagNewForm, 'status', '状态', { schema: { enum: [0, 1] }})
  ], { save: slotAction('保存标签', b.submitTagNew), close: close(b.tagNewVisible) }, () => b.tagNewSaving.value || b.tagCategoryLoading.value, () => !!b.tagNewFormRef.value)

  dialog('category', '新建商品分类', b.categoryNewVisible, () => [
    boundField('name', '分类名称', () => b.categoryNewName.value, value => { b.categoryNewName.value = value }, { required: true, schema: text(30) }),
    boundField('parent_path', '上级分类', () => b.categoryNewPath.value, value => {
      assertPath(b.categoryParentOptions.value, value)
      b.categoryNewPath.value = value
      b.handleCategoryParentChange(value)
    }, { schema: { type: 'array', maxItems: 10, items: integer() }, options: b.categoryParentOptions.value })
  ], { save: slotAction('保存分类', b.submitCategoryNew), close: close(b.categoryNewVisible) }, b.categoryNewSaving)

  dialog('collect', '采集商品', b.collectDialogVisible, () => [modelField(b.collectForm, 'url', '商品链接', { required: true, schema: { ...text(255), pattern: '^https?://' }})], {
    collect: slotAction('采集商品并写入商品库', b.submitCollect, { available: () => b.collectorEnabled.value }), close: close(b.collectDialogVisible)
  }, b.collectLoading, () => !!b.collectFormRef.value)

  dialog('pick', '选择库商品', b.pickDialogVisible, () => [
    modelField(b.pickSearch, 'name', '查询名称', { schema: text(100) }),
    boundField('category_path', '查询分类', () => b.pickCategoryPath.value, async(value, context) => {
      assertPath(b.categoryOptions.value, value)
      b.pickCategoryPath.value = value
      successOrThrow(await b.loadPickList(context))
    }, { schema: { type: 'array', maxItems: 10, items: integer() }, options: b.categoryOptions.value }),
    modelField(b.pickSearch, 'page', '页码', { schema: { type: 'integer', min: 1 }, set: async(value, context) => { b.pickSearch.page = value; successOrThrow(await b.loadPickList(context)) } }),
    modelField(b.pickSearch, 'limit', '每页项数', { schema: { enum: [10, 20, 50] }, set: async(value, context) => { b.pickSearch.limit = value; successOrThrow(await b.loadPickList(context)) } }),
    boundField('selected_ids', '待添加库商品', () => b.pickTempSelected.value.map(item => item.id), async value => {
      if (value.some(id => b.isAlreadySelected(id))) throw new PageSlotError('INVALID_VALUE', '该商品已经被选择')
      b.pickTempSelected.value = value.map(id => b.pickList.value.find(item => item.id === id))
      await nextTick()
      b.pickTableRef.value?.clearSelection()
      value.forEach(id => b.pickTableRef.value?.toggleRowSelection(b.pickList.value.find(item => item.id === id), true))
    }, { schema: ids(b.pickList.value.map(item => item.id)), options: options(b.pickList.value) })
  ], { search: slotAction('查询库商品', (args, context) => b.loadPickList(context)), confirm: slotAction('确认添加所选库商品', b.confirmPick), close: close(b.pickDialogVisible) }, b.pickLoading)

  dialog('spec.editor', '商品规格编辑', b.specEditorVisible, () => [
    modelField(b.form, 'name', '规格模板名称', { required: true, schema: text(30) }),
    ...b.specEditorCards.value.map(card => boundField(`cards.${card.specId}.order`, `${card.title}：规格值顺序`, () => card.items.map(item => item.rowKey), value => { card.items = reorder(card.items, value, item => item.rowKey) }, { schema: ids(card.items.map(item => item.rowKey)) }))
  ], {
    confirm: slotAction('确认规格编辑', complete(b.confirmSpecEditor)),
    'library.open': slotAction('添加规格值或新规格', complete(b.openPickDialog)),
    'item.remove': slotAction('移除规格值', complete(({ rowKey }) => {
      const card = b.specEditorCards.value.find(item => item.items.some(row => row.rowKey === rowKey))
      if (!card) throw new PageSlotError('INVALID_VALUE', '规格值已失效')
      b.removeSpecItem(card, card.items.find(item => item.rowKey === rowKey))
    }), { schema: specArgs }),
    'card.clear': slotAction('确认后清空规格卡片', ({ specId }, context) => {
      const card = b.specEditorCards.value.find(item => item.specId === specId)
      if (!card) throw new PageSlotError('INVALID_VALUE', '规格卡片已失效')
      return b.removeSpecCard(card, context)
    }, { schema: { type: 'object', required: ['specId'], properties: { specId: integer() }}}),
    close: close(b.specEditorVisible)
  }, () => false)
}
