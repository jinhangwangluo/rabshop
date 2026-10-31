<template>
  <section class="activity-form-page">
    <div class="form-sticky">
      <header class="form-header">
        <div class="form-header__title">
          <el-tooltip content="返回活动列表"><el-button :icon="Close" circle aria-label="返回活动列表" @click="handleCancel" /></el-tooltip>
          <h1>{{ isEdit ? '编辑秒杀活动' : '新建秒杀活动' }}</h1>
        </div>
        <div class="form-header__actions">
          <el-button @click="handleCancel">取消</el-button>
          <el-button type="primary" :loading="saving" :disabled="editorLoading || !!editorError || isLocked" @click="submitForm">保存并提交审核</el-button>
        </div>
      </header>
      <nav class="form-steps" aria-label="活动配置导航">
        <button v-for="(step, index) in steps" :key="step" type="button" class="form-steps__item" :class="{ 'is-active': activeStep === index }" @click="handleStepSelect(index)">{{ step }}</button>
      </nav>
    </div>

    <div v-loading="editorLoading" class="form-content">
      <el-alert v-if="editorError" :title="editorError" type="error" show-icon :closable="false"><el-button text @click="fetchEditor">重试</el-button></el-alert>
      <el-alert v-if="saveError" :title="saveError" type="error" show-icon :closable="false" />
      <el-alert v-if="isLocked" title="活动已开始或已有订单，价格、额度和时段不可修改" type="warning" show-icon :closable="false" />
      <el-form :model="form" label-width="110px" :disabled="isLocked || editorLoading || !!editorError || saving">
        <section ref="basicSection" class="form-section">
          <h2>基本信息</h2>
          <el-form-item label="活动名称" required :error="fieldErrors.title"><el-input v-model="form.title" maxlength="80" show-word-limit placeholder="填写活动名称" /></el-form-item>
          <el-form-item label="活动封面"><Upload v-model="form.cover" :limit="1" /></el-form-item>
          <el-form-item label="活动日期" required :error="fieldErrors.date_range"><el-date-picker v-model="form.date_range" type="daterange" value-format="YYYY-MM-DD" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期" /></el-form-item>
          <el-form-item label="发布状态"><el-switch v-model="form.status" :active-value="1" :inactive-value="0" active-text="发布" inactive-text="暂停" /></el-form-item>
        </section>

        <section ref="slotsSection" class="form-section">
          <h2>每日时段</h2>
          <el-form-item label="活动时段" required :error="fieldErrors.slots">
            <div class="slot-list">
              <div v-for="(slot, index) in form.slots" :key="index" class="slot-row">
                <span class="slot-number">{{ index + 1 }}</span>
                <el-time-picker v-model="slot.start_time" format="HH:mm" value-format="HH:mm" placeholder="开始时间" />
                <span>至</span>
                <el-time-picker v-model="slot.end_time" format="HH:mm" value-format="HH:mm" placeholder="结束时间" />
                <el-tooltip content="删除时段"><el-button :icon="Delete" circle aria-label="删除时段" :disabled="form.slots.length <= 1" @click="form.slots.splice(index, 1)" /></el-tooltip>
              </div>
              <el-button :icon="Plus" :disabled="form.slots.length >= 12" @click="form.slots.push({ start_time: '', end_time: '' })">添加时段</el-button>
            </div>
          </el-form-item>
        </section>

        <section ref="productsSection" class="form-section">
          <div class="section-heading"><h2>参与商品 <small>{{ form.products.length }}/50</small></h2><el-button type="primary" plain :icon="Plus" :disabled="form.products.length >= 50" @click="handleOpenPicker">选择普通商品</el-button></div>
          <p v-if="fieldErrors.productSelection" class="field-error">{{ fieldErrors.productSelection }}</p>
          <el-empty v-if="!form.products.length" description="尚未选择商品" :image-size="80" />
          <article v-for="(product, index) in form.products" :key="product.product_id" class="selected-product">
            <div class="product-heading">
              <el-image v-if="product.image" :src="product.image" fit="cover" />
              <div class="product-heading__name"><strong>{{ product.store_name || '商品资料已变更' }}</strong><span>商品 #{{ product.product_id }}</span></div>
              <el-tooltip content="上移商品"><el-button :icon="ArrowUp" circle aria-label="上移商品" :disabled="index === 0" @click="handleMoveProduct(index, -1)" /></el-tooltip>
              <el-tooltip content="下移商品"><el-button :icon="ArrowDown" circle aria-label="下移商品" :disabled="index === form.products.length - 1" @click="handleMoveProduct(index, 1)" /></el-tooltip>
              <el-tooltip content="移除商品"><el-button :icon="Delete" circle type="danger" aria-label="移除商品" @click="handleRemoveProduct(index)" /></el-tooltip>
              <el-tooltip content="刷新普通商品规格"><el-button :icon="Refresh" circle aria-label="刷新普通商品规格" :loading="skuLoading[product.product_id]" @click="refreshProductSkus(product)" /></el-tooltip>
              <el-tooltip :content="expanded[product.product_id] ? '收起规格' : '展开规格'"><el-button :icon="expanded[product.product_id] ? ArrowUp : ArrowDown" circle :aria-label="expanded[product.product_id] ? '收起规格' : '展开规格'" @click="handleExpandProduct(product)" /></el-tooltip>
            </div>
            <p v-if="fieldErrors.products?.[product.product_id]?.general" class="field-error">{{ fieldErrors.products[product.product_id].general }}</p>
            <div class="product-limit"><span>每用户每场限购</span><el-input-number v-model="product.user_limit" :min="1" :max="9999" :precision="0" /><span>件</span><span class="field-error">{{ fieldErrors.products?.[product.product_id]?.user_limit }}</span></div>
            <div v-if="expanded[product.product_id]" v-loading="skuLoading[product.product_id]" class="sku-section">
              <el-alert v-if="skuErrors[product.product_id]" :title="skuErrors[product.product_id]" type="error" :closable="false" />
              <div class="batch-row">
                <strong>批量设置</strong>
                <span>秒杀价</span><el-input-number v-model="batchValues[product.product_id].seckill_price" :min="0.01" :precision="2" :controls="false" placeholder="不修改" />
                <span>每场额度</span><el-input-number v-model="batchValues[product.product_id].quota" :min="1" :max="999999" :precision="0" :controls="false" placeholder="不修改" />
                <el-button type="primary" plain :disabled="!product.skus.some(sku => sku.selected && Number(sku.stock) > 0)" @click="handleBatch(product)">应用到已勾选 SKU</el-button>
                <el-tooltip content="清空批量输入"><el-button :icon="RefreshLeft" circle aria-label="清空批量输入" @click="clearBatch(product)" /></el-tooltip>
              </div>
              <el-table :data="product.skus" row-key="sku_id" size="small" table-layout="fixed">
                <el-table-column label="参与" width="65"><template #default="{ row }"><el-checkbox v-model="row.selected" :disabled="Number(row.stock) <= 0 && !row.selected" /></template></el-table-column>
                <el-table-column prop="sku_name" label="商品规格" min-width="160" show-overflow-tooltip />
                <el-table-column label="普通价格" width="105"><template #default="{ row }">¥{{ money(row.price) }}</template></el-table-column>
                <el-table-column prop="stock" label="真实库存" width="90" />
                <el-table-column label="秒杀价" min-width="145"><template #default="{ row }"><el-input-number v-model="row.seckill_price" :min="0.01" :precision="2" :controls="false" :disabled="!row.selected" /><span v-if="fieldErrors.products?.[product.product_id]?.skus?.[row.sku_id]?.seckill_price" class="field-error">{{ fieldErrors.products[product.product_id].skus[row.sku_id].seckill_price }}</span></template></el-table-column>
                <el-table-column label="每场额度" min-width="145"><template #default="{ row }"><el-input-number v-model="row.quota" :min="1" :max="999999" :precision="0" :controls="false" :disabled="!row.selected" /><span v-if="fieldErrors.products?.[product.product_id]?.skus?.[row.sku_id]?.quota" class="field-error">{{ fieldErrors.products[product.product_id].skus[row.sku_id].quota }}</span></template></el-table-column>
              </el-table>
              <span class="field-note">每场额度为秒杀库存；真实库存由普通商品维护。</span>
            </div>
          </article>
        </section>
      </el-form>
    </div>

    <el-dialog v-model="pickerVisible" title="选择普通商品" width="min(94%, 840px)" :close-on-click-modal="false" append-to-body>
      <div class="picker-filter"><el-input v-model="pickerQuery.q" placeholder="搜索商品名称" clearable :prefix-icon="Search" @keyup.enter="handlePickerSearch" /><el-button type="primary" :icon="Search" @click="handlePickerSearch">查询</el-button></div>
      <el-alert v-if="pickerError" :title="pickerError" type="error" :closable="false" />
      <el-table v-loading="pickerLoading" :data="pickerList" row-key="id">
        <el-table-column label="选择" width="65"><template #default="{ row }"><el-checkbox :model-value="pickerSelected.includes(row.id)" :disabled="form.products.some(product => product.product_id === row.id)" @change="value => handlePick(row.id, value)" /></template></el-table-column>
        <el-table-column label="商品" min-width="220"><template #default="{ row }"><div class="picker-product"><el-image v-if="row.image" :src="row.image" fit="cover" /><strong>{{ row.store_name }}</strong></div></template></el-table-column>
        <el-table-column label="价格" width="110"><template #default="{ row }">¥{{ money(row.price) }}</template></el-table-column>
        <el-table-column prop="stock" label="真实库存" width="95" />
      </el-table>
      <el-pagination v-model:current-page="pickerQuery.page" :total="pickerTotal" :page-size="pickerQuery.limit" layout="total, prev, pager, next" @current-change="fetchPicker" />
      <template #footer><span class="field-note">已选 {{ pickerSelected.length }} 件</span><el-button @click="pickerVisible = false">取消</el-button><el-button type="primary" :loading="pickerAdding" :disabled="!pickerSelected.length || !!pickerError" @click="handleAddProducts">添加商品</el-button></template>
    </el-dialog>
  </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { ArrowDown, ArrowUp, Close, Delete, Plus, Refresh, RefreshLeft, Search } from '@element-plus/icons-vue'
import Upload from '@/components/Upload/index.vue'
import * as api from '../../api/seckill'
import { activityFieldErrors, applyBatchSkuValues, buildActivityPayload, createActivityDraft, draftFromActivity, mergeActivitySkus, validateActivityDraft } from '../../model'

defineOptions({ name: 'SeckillMerchantActivityForm' })
const route = useRoute()
const router = useRouter()
const isEdit = computed(() => route.name === 'seckillMerchantActivityEdit')
const steps = ['基本信息', '每日时段', '参与商品']
const activeStep = ref(0)
const basicSection = ref(null)
const slotsSection = ref(null)
const productsSection = ref(null)
const form = reactive(createActivityDraft())
const fieldErrors = ref({ products: {}})
const showErrors = ref(false)
const editorLoading = ref(false)
const editorError = ref('')
const saveError = ref('')
const saving = ref(false)
const isLocked = ref(false)
const expanded = reactive({})
const batchValues = reactive({})
const skuLoading = reactive({})
const skuErrors = reactive({})
const skuLoaded = reactive({})
const pickerVisible = ref(false)
const pickerLoading = ref(false)
const pickerAdding = ref(false)
const pickerError = ref('')
const pickerList = ref([])
const pickerTotal = ref(0)
const pickerSelected = ref([])
const pickerRows = new Map()
const pickerQuery = reactive({ q: '', page: 1, limit: 10 })
let pickerSequence = 0
let editorSequence = 0
let cleanSnapshot = ''
let allowExit = false
const isDirty = computed(() => !!cleanSnapshot && JSON.stringify(buildActivityPayload(form)) !== cleanSnapshot)

function errorText(error, fallback) { return error?.message || error?.msg || fallback }
function money(value) { return Number(value || 0).toFixed(2) }
function handleStepSelect(index) {
  activeStep.value = index
  ;[basicSection, slotsSection, productsSection][index]?.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}
function prepareProducts() {
  for (const state of [expanded, batchValues, skuLoading, skuErrors, skuLoaded]) {
    Object.keys(state).forEach(id => delete state[id])
  }
  for (const product of form.products) {
    batchValues[product.product_id] = { seckill_price: null, quota: null }
    expanded[product.product_id] = product === form.products[0]
    skuLoaded[product.product_id] = false
  }
}
function handleExpandProduct(product) {
  const id = product.product_id
  expanded[id] = !expanded[id]
  if (expanded[id] && !skuLoaded[id] && !skuLoading[id]) refreshProductSkus(product)
}

/** 读取最新普通规格并保留活动价格；失败时不会删掉已参与 SKU。
 * @param {Object} product 当前活动商品。
 * @returns {Promise<void>} 已合并的最新可选规格。
 */
async function refreshProductSkus(product) {
  const id = product.product_id
  if (skuLoading[id]) return
  const sequence = editorSequence
  skuLoading[id] = true
  skuErrors[id] = ''
  try {
    const { data } = await api.getProductSkus(id)
    if (sequence !== editorSequence) return
    if (!Array.isArray(data.skus)) throw new Error('商品规格返回无效')
    mergeActivitySkus(product, data.skus)
    skuLoaded[id] = true
  } catch (error) { if (sequence === editorSequence) skuErrors[id] = errorText(error, '普通商品规格加载失败') } finally {
    // Only the request that started this refresh may release its loading state.
    // eslint-disable-next-line require-atomic-updates
    if (sequence === editorSequence) skuLoading[id] = false
  }
}

/** 按当前路由重新读取活动或安装代次，防止旧页面误用失效的代次。
 * @returns {Promise<void>} 加载完成后保存可编辑快照。
 */
async function fetchEditor() {
  const sequence = ++editorSequence
  editorLoading.value = true
  editorError.value = ''
  saveError.value = ''
  saving.value = false
  showErrors.value = false
  fieldErrors.value = { products: {}}
  cleanSnapshot = ''
  Object.assign(form, createActivityDraft())
  try {
    if (isEdit.value) {
      const { data } = await api.getActivityDetail(route.params.id, String(route.query.install_generation || ''))
      if (sequence !== editorSequence) return
      Object.assign(form, draftFromActivity(data))
      isLocked.value = !!data.is_locked
    } else {
      const { data } = await api.getActivityList({ page: 1, limit: 1 })
      if (sequence !== editorSequence) return
      Object.assign(form, { install_generation: String(data.install_generation || '') })
      isLocked.value = false
      if (!form.install_generation) throw new Error('无法取得当前秒杀安装代次')
    }
    prepareProducts()
    cleanSnapshot = JSON.stringify(buildActivityPayload(form))
    if (isEdit.value && !isLocked.value && form.products[0]) refreshProductSkus(form.products[0])
  } catch (error) { if (sequence === editorSequence) editorError.value = errorText(error, '活动加载失败') } finally { if (sequence === editorSequence) editorLoading.value = false }
}

/** 保存当前活动字段，失败时保持原页面和输入不变。
 * @returns {Promise<void>|undefined} 完成请求后才允许离开表单。
 */
async function submitForm() {
  if (saving.value || editorLoading.value || editorError.value || isLocked.value || !form.install_generation) return
  fieldErrors.value = activityFieldErrors(form)
  showErrors.value = true
  const message = validateActivityDraft(form)
  if (message) {
    if (fieldErrors.value.title || fieldErrors.value.date_range) handleStepSelect(0)
    else if (fieldErrors.value.slots) handleStepSelect(1)
    else handleStepSelect(2)
    ElMessage.warning(message)
    return
  }
  saving.value = true
  saveError.value = ''
  const sequence = editorSequence
  try {
    await api.saveActivity(buildActivityPayload(form), form.id)
    if (sequence !== editorSequence) return
    ElMessage.success('活动已提交审核')
    cleanSnapshot = JSON.stringify(buildActivityPayload(form))
    allowExit = true
    await router.push({ name: 'seckillMerchantActivity' })
  } catch (error) { if (sequence === editorSequence) saveError.value = errorText(error, '保存失败') } finally {
    // A route change starts a different form; the old request must not affect it.
    // eslint-disable-next-line require-atomic-updates
    if (sequence === editorSequence) saving.value = false
  }
}
function handleCancel() { router.push({ name: 'seckillMerchantActivity' }) }
function handleMoveProduct(index, direction) {
  const next = index + direction
  if (next < 0 || next >= form.products.length) return
  form.products.splice(next, 0, form.products.splice(index, 1)[0])
}
function handleRemoveProduct(index) {
  const id = form.products[index].product_id
  form.products.splice(index, 1)
  delete batchValues[id]
  delete expanded[id]
  delete skuLoading[id]
  delete skuErrors[id]
  delete skuLoaded[id]
}
function clearBatch(product) { batchValues[product.product_id] = { seckill_price: null, quota: null } }
function handleBatch(product) {
  try { ElMessage.success('已更新 ' + applyBatchSkuValues(product, batchValues[product.product_id]) + ' 个规格') } catch (error) { ElMessage.warning(errorText(error, '批量设置失败')) }
}
function handleOpenPicker() {
  pickerSelected.value = []
  pickerRows.clear()
  Object.assign(pickerQuery, { q: '', page: 1 })
  pickerVisible.value = true
  fetchPicker()
}
function handlePick(id, value) { pickerSelected.value = value ? [...new Set([...pickerSelected.value, id])] : pickerSelected.value.filter(item => item !== id) }
function handlePickerSearch() { pickerQuery.page = 1; fetchPicker() }
async function fetchPicker() {
  const sequence = ++pickerSequence
  pickerLoading.value = true
  pickerError.value = ''
  try {
    const { data } = await api.getProductOptions({ ...pickerQuery })
    if (sequence !== pickerSequence) return
    pickerList.value = Array.isArray(data.list) ? data.list : []
    pickerTotal.value = Number(data.count || 0)
    pickerList.value.forEach(row => pickerRows.set(row.id, row))
  } catch (error) { if (sequence === pickerSequence) { pickerError.value = errorText(error, '商品加载失败'); pickerList.value = []; pickerTotal.value = 0 } } finally { if (sequence === pickerSequence) pickerLoading.value = false }
}
async function handleAddProducts() {
  if (pickerAdding.value || !pickerSelected.value.length) return
  if (form.products.length + pickerSelected.value.length > 50) { ElMessage.warning('最多选择50个商品'); return }
  pickerAdding.value = true
  const editorRequest = editorSequence
  const pickerRequest = pickerSequence
  try {
    const products = await Promise.all(pickerSelected.value.map(async id => {
      const ordinary = pickerRows.get(id)
      if (!ordinary) throw new Error('所选商品已失效，请重新查询')
      const { data } = await api.getProductSkus(id)
      if (!Array.isArray(data.skus) || !data.skus.length) throw new Error('商品 #' + id + ' 没有可参与的规格')
      return { product_id: id, store_name: ordinary.store_name, image: ordinary.image, user_limit: 1, skus: data.skus.map(sku => ({ ...sku, sku_id: sku.id, selected: Number(sku.stock) > 0, seckill_price: Number(sku.price), quota: 1 })) }
    }))
    if (editorRequest !== editorSequence || pickerRequest !== pickerSequence || !pickerVisible.value) return
    form.products.push(...products)
    for (const product of products) { batchValues[product.product_id] = { seckill_price: null, quota: null }; expanded[product.product_id] = true; skuLoaded[product.product_id] = true }
    pickerVisible.value = false
  } catch (error) { if (editorRequest === editorSequence) ElMessage.error(errorText(error, '规格加载失败')) } finally {
    // Picker submissions are single-flight; a stale editor request cannot reset it.
    // eslint-disable-next-line require-atomic-updates
    if (editorRequest === editorSequence) pickerAdding.value = false
  }
}
function handleBeforeUnload(event) {
  if (!isDirty.value || allowExit) return
  event.preventDefault()
  event.returnValue = ''
}
onBeforeRouteLeave(async() => {
  if (!isDirty.value || allowExit) return true
  try { await ElMessageBox.confirm('当前修改尚未保存，确认离开？', '离开编辑页面', { type: 'warning' }); return true } catch { return false }
})
watch(form, () => { if (showErrors.value) fieldErrors.value = activityFieldErrors(form) }, { deep: true })
watch(() => [route.name, route.params.id, route.query.install_generation], fetchEditor)
watch(pickerVisible, visible => { if (!visible) { pickerSequence++; pickerLoading.value = false } })
onMounted(() => { fetchEditor(); if (typeof window !== 'undefined') window.addEventListener('beforeunload', handleBeforeUnload) })
onBeforeUnmount(() => { editorSequence++; if (typeof window !== 'undefined') window.removeEventListener('beforeunload', handleBeforeUnload) })
</script>

<style scoped lang="scss">
.activity-form-page { min-width: 0; padding: 0 24px 30px; color: var(--text-primary, var(--el-text-color-primary)); }
.form-sticky { position: sticky; top: 0; z-index: 10; background: var(--surface-card, var(--el-bg-color)); }
.form-header, .form-header__title, .form-header__actions, .section-heading, .product-heading, .slot-row, .batch-row, .product-limit, .picker-filter, .picker-product { display: flex; align-items: center; }
.form-header, .section-heading { justify-content: space-between; }
.form-header { min-height: 64px; gap: 16px; border-bottom: 1px solid var(--el-border-color-lighter); }
.form-header__title { gap: 12px; min-width: 0; }
.form-header h1 { font-size: 17px; margin: 0; white-space: nowrap; }
.form-header__actions { gap: 8px; flex-shrink: 0; }
.form-steps { display: flex; margin: 0 0 18px; border-bottom: 1px solid var(--el-border-color-lighter); }
.form-steps__item { flex: 1; min-width: 0; height: 44px; border: 0; border-bottom: 3px solid transparent; background: var(--el-fill-color-light); color: var(--el-text-color-regular); cursor: pointer; font-size: 13px; }
.form-steps__item + .form-steps__item { border-left: 1px solid var(--el-border-color-lighter); }
.form-steps__item.is-active { color: var(--el-color-primary); background: var(--el-color-primary-light-9); border-bottom-color: var(--el-color-primary); font-weight: 600; }
.form-steps__item:focus-visible { outline: 2px solid var(--el-color-primary); outline-offset: -2px; }
.form-content { max-width: 1140px; min-height: 340px; margin: auto; }
.form-content > .el-alert { margin-bottom: 16px; }
.form-section { padding: 10px 0 24px; scroll-margin-top: 126px; border-bottom: 1px solid var(--el-border-color-lighter); }
.form-section h2 { font-size: 16px; line-height: 26px; margin: 0 0 20px; }
.form-section h2 small, .field-note { color: var(--el-text-color-secondary); font-size: 12px; font-weight: 400; }
.field-note { margin-left: 10px; }
.field-error { color: var(--el-color-danger); font-size: 12px; line-height: 18px; margin: 5px 0; }
.form-content :deep(.el-form-item__content) { min-width: 0; }
.slot-list { width: 100%; }
.slot-row { gap: 12px; flex-wrap: wrap; margin-bottom: 10px; }
.slot-number { width: 20px; flex-shrink: 0; color: var(--el-text-color-secondary); }
.slot-row :deep(.el-date-editor) { width: 165px; max-width: 100%; }
.selected-product { border: 1px solid var(--el-border-color-lighter); border-radius: 6px; padding: 16px; margin-bottom: 16px; }
.product-heading { gap: 8px; }
.product-heading .el-image, .picker-product .el-image { width: 44px; height: 44px; flex-shrink: 0; border-radius: 4px; }
.product-heading__name { display: flex; flex-direction: column; gap: 4px; min-width: 0; flex: 1; }
.product-heading__name strong, .picker-product strong { overflow-wrap: anywhere; font-size: 14px; }
.product-heading__name span { color: var(--el-text-color-secondary); font-size: 12px; }
.product-limit { flex-wrap: wrap; gap: 8px; margin: 14px 0; font-size: 13px; }
.batch-row { flex-wrap: wrap; gap: 10px; padding: 12px; background: var(--el-fill-color-light); font-size: 13px; }
.batch-row :deep(.el-input-number) { width: 135px; }
.sku-section { overflow-x: auto; }
.sku-section :deep(.el-table) { min-width: 720px; }
.sku-section :deep(.el-input-number) { width: 100%; max-width: 135px; }
.sku-section > .field-note { display: block; margin: 8px 0 0; }
.picker-filter { gap: 10px; margin-bottom: 16px; }
.picker-filter .el-input { max-width: 360px; }
.picker-product { gap: 10px; }
.el-pagination { justify-content: flex-end; margin-top: 16px; }
@media (max-width: 700px) { .activity-form-page { padding: 0 12px 24px; } .form-header__actions { gap: 4px; } .form-header h1 { font-size: 15px; white-space: normal; } .form-content :deep(.el-form-item) { display: block; } .form-content :deep(.el-form-item__label) { display: block; text-align: left; } .form-content :deep(.el-date-editor) { max-width: 100%; } .product-heading { flex-wrap: wrap; } .product-heading__name { flex-basis: calc(100% - 52px); } }
</style>
