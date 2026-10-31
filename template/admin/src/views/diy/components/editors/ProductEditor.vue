<template>
  <div class="product-editor">
    <el-form label-width="100px">
      <el-form-item label="展示样式">
        <el-radio-group v-model="localConfig.layout">
          <el-radio label="list">一行样式</el-radio>
          <el-radio label="grid">两列样式</el-radio>
        </el-radio-group>
      </el-form-item>

      <el-form-item label="商品数量">
        <template v-if="isLast">
          <span class="tip-text">当前组件在页面末尾，将启用触底加载更多</span>
        </template>
        <template v-else>
          <el-input-number
            v-model="localConfig.show_count"
            :min="1"
            :max="50"
            :step="1"
            controls-position="right"
            style="width: 120px"
          />
        </template>
      </el-form-item>

      <el-form-item label="商品排序">
        <el-radio-group v-model="localConfig.sort_by">
          <el-radio label="default">综合</el-radio>
          <el-radio label="sales">销量</el-radio>
          <el-radio label="price">价格</el-radio>
        </el-radio-group>
      </el-form-item>

      <el-form-item label="携带导航">
        <el-switch v-model="localConfig.with_nav" active-text="是" inactive-text="否" />
      </el-form-item>

      <template v-if="localConfig.with_nav">
        <el-form-item label="导航样式">
          <el-radio-group v-model="localConfig.tab_style" class="tab-style-radios">
            <el-radio label="subtitle">主标题+副标题</el-radio>
            <el-radio label="arc">主标题+圆弧</el-radio>
            <el-radio label="pill">圆角包裹</el-radio>
            <el-radio label="image">主标题+图片</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="导航分类">
          <el-select
            v-model="localConfig.category_ids"
            multiple
            collapse-tags
            clearable
            placeholder="请选择一级商品分类"
            style="width: 100%"
            @change="handleCategoryTabsChange"
          >
            <el-option
              v-for="item in topCategoryOptions"
              :key="item.id"
              :label="item.name"
              :value="item.id"
            />
          </el-select>
        </el-form-item>

        <div class="tab-config-list">
          <div class="tab-config-title">导航设置</div>
          <div v-if="localConfig.category_tabs.length === 0" class="tab-config-empty">
            请选择一级商品分类后配置导航
          </div>
          <draggable
            v-else
            v-model="localConfig.category_tabs"
            item-key="id"
            handle=".tab-drag-handle"
            class="tab-card-list"
            @change="handleCategoryTabsSortChange"
          >
            <template #item="{ element: tab, index }">
              <div :key="tab.id || index" class="tab-config-card">
                <span class="tab-drag-handle">
                  <LegacyIcon name="el-icon-rank" />
                </span>

                <div v-if="isImageTabStyle" class="tab-thumb">
                  <template v-if="tab.image">
                    <img :src="tab.image" :alt="tab.name || '导航图片'">
                    <div class="tab-thumb__delete" @click.stop="handleTabImageChange(index, '')">
                      <LegacyIcon name="el-icon-delete" />
                    </div>
                  </template>
                  <template v-else>
                    <div class="tab-thumb__placeholder">
                      <LegacyIcon name="el-icon-picture" />
                    </div>
                    <Upload
                      :model-value="tab.image"
                      :limit="1"
                      class="tab-thumb__upload"
                      @update:modelValue="val => handleTabImageChange(index, val)"
                    />
                  </template>
                </div>

                <div class="tab-card-fields">
                  <div class="tab-card-field">
                    <span class="tab-card-field__label">主标题</span>
                    <el-input v-model="tab.name" placeholder="请输入主标题" maxlength="12" show-word-limit />
                  </div>
                  <div v-if="isSubtitleTabStyle" class="tab-card-field">
                    <span class="tab-card-field__label">副标题</span>
                    <el-input v-model="tab.subtitle" placeholder="请输入副标题" maxlength="16" show-word-limit />
                  </div>
                </div>

                <LegacyIcon name="el-icon-circle-close" class="tab-card-remove" @click="removeCategoryTab(index)" />
              </div>
            </template>
          </draggable>
        </div>
      </template>

      <template v-else>
        <el-form-item label="标题">
          <el-input v-model="localConfig.title" placeholder="好物推荐" />
        </el-form-item>

        <el-form-item label="选择方式">
          <el-select v-model="localConfig.source_type" placeholder="请选择方式" style="width: 100%">
            <el-option label="指定商品" value="manual" />
            <el-option label="指定一级分类" value="category" />
            <el-option label="指定商品标签" value="tag" />
            <el-option label="指定品牌" value="brand" />
          </el-select>
        </el-form-item>

        <el-form-item v-if="localConfig.source_type === 'manual'" label="指定商品">
          <el-button type="primary" @click="openProductSelector">+ 添加商品</el-button>
          <div v-if="localConfig.manual_ids.length > 0" class="selected-list">
            <div
              v-for="(item, index) in localConfig.manual_ids"
              :key="item.id || index"
              class="selected-item"
            >
              <img v-if="item.cover || item.image" :src="item.cover || item.image" alt="" class="selected-cover">
              <span class="selected-title">{{ item.name || item.title || ('商品 #' + item.id) }}</span>
              <el-button type="text" size="mini" @click="removeManualProduct(index)">
                <LegacyIcon name="el-icon-close" />
              </el-button>
            </div>
          </div>
        </el-form-item>

        <el-form-item v-if="localConfig.source_type === 'category'" label="一级分类">
          <el-select
            v-model="localConfig.category_id"
            clearable
            placeholder="请选择一级商品分类"
            style="width: 100%"
          >
            <el-option
              v-for="item in topCategoryOptions"
              :key="item.id"
              :label="item.name"
              :value="item.id"
            />
          </el-select>
        </el-form-item>

        <el-form-item v-if="localConfig.source_type === 'tag'" label="商品标签">
          <el-select
            v-model="localConfig.tag_id"
            filterable
            clearable
            placeholder="请选择商品标签"
            style="width: 100%"
          >
            <el-option
              v-for="item in tagOptions"
              :key="item.id"
              :label="item.name"
              :value="item.id"
            />
          </el-select>
        </el-form-item>

        <el-form-item v-if="localConfig.source_type === 'brand'" label="商品品牌">
          <el-select
            v-model="localConfig.brand_id"
            filterable
            clearable
            placeholder="请选择商品品牌"
            style="width: 100%"
          >
            <el-option
              v-for="item in brandOptions"
              :key="item.id"
              :label="item.name"
              :value="item.id"
            />
          </el-select>
        </el-form-item>
      </template>
    </el-form>

    <el-dialog
      v-model="selectorVisible"
      title="选择商品"
      width="760px"
      append-to-body
      @open="loadProductList"
    >
      <div class="selector-search">
        <el-input
          v-model="searchKeyword"
          placeholder="搜索商品名称"
          style="width: 220px"
          clearable
          @keyup.enter="handleSearch"
          @change="handleSearch"
        />
        <el-button type="primary" style="margin-left: 8px" @click="handleSearch">搜索</el-button>
      </div>

      <el-table
        ref="productTable"
        v-loading="productLoading"
        :data="productList"
        row-key="id"
        style="margin-top: 12px"
        @selection-change="handleSelectionChange"
      >
        <el-table-column type="selection" width="45" />
        <el-table-column label="封面" width="74">
          <template #default="{ row }">
            <img v-if="row.cover || row.image" :src="row.cover || row.image" alt="" class="product-cover">
            <span v-else class="no-cover">无封面</span>
          </template>
        </el-table-column>
        <el-table-column label="商品名称" prop="name" show-overflow-tooltip />
        <el-table-column label="价格" width="100">
          <template #default="{ row }">¥{{ formatPrice(row.price) }}</template>
        </el-table-column>
        <el-table-column label="销量" width="90">
          <template #default="{ row }">{{ getDisplaySales(row) }}</template>
        </el-table-column>
      </el-table>

      <el-pagination
        small
        layout="prev, pager, next"
        :total="productTotal"
        :page-size="10"
        :current-page="productPage"
        style="margin-top: 10px; text-align: right"
        @current-change="handlePageChange"
      />

      <template #footer>
        <span>
          <el-button @click="selectorVisible = false">取消</el-button>
          <el-button type="primary" @click="confirmSelection">
            确认（已选 {{ tempSelected.length }} 个）
          </el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, nextTick, reactive, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import Upload from '@/components/Upload'
import draggable from 'vuedraggable'
import { getBrandOptions, getProductCategoryTree, getStoreProductList } from '@/api/product'
import { getTagOptions } from '@/api/tag'

defineOptions({
  name: 'ProductEditor'
})

const props = defineProps({
  config: {
    type: Object,
    default: () => ({})
  },
  isLast: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['change'])

const categoryOptions = ref([])
const tagOptions = ref([])
const brandOptions = ref([])
const selectorVisible = ref(false)
const productTable = ref(null)
const productList = ref([])
const productTotal = ref(0)
const productPage = ref(1)
const productLoading = ref(false)
const searchKeyword = ref('')
const tempSelected = ref([])
const fromProp = ref(false)
const lastEmit = ref('')

const localConfig = reactive({
  title: '好物推荐',
  layout: 'grid',
  with_nav: true,
  tab_style: 'subtitle',
  category_ids: [],
  category_tabs: [],
  source_type: 'manual',
  manual_ids: [],
  category_id: 0,
  tag_id: 0,
  brand_id: 0,
  show_count: 6,
  load_more: false,
  sort_by: 'default',
  bg_color: '#ffffff'
})

const topCategoryOptions = computed(() => {
  return categoryOptions.value
    .filter(item => Number(item.pid || 0) === 0 && Number(item.status) === 1)
    .map(item => ({
      id: Number(item.id),
      name: item.name || item.label || ('分类 #' + item.id),
      image: item.image || ''
    }))
})

const isImageTabStyle = computed(() => localConfig.tab_style === 'image')
const isSubtitleTabStyle = computed(() => localConfig.tab_style === 'subtitle')

function normalizeIds(value) {
  if (typeof value === 'string') {
    value = value.split(',')
  }
  if (!Array.isArray(value)) {
    value = [value]
  }

  const ids = value.map(item => {
    const id = item && typeof item === 'object' ? (item.id || item.value) : item
    return Number(id || 0)
  }).filter(id => id > 0)

  return Array.from(new Set(ids))
}

function normalizeManualProducts(value) {
  if (!Array.isArray(value)) {
    value = value ? [value] : []
  }
  const seen = new Set()
  return value.map(item => {
    const row = item && typeof item === 'object' ? item : { id: item }
    const id = Number(row.id || row.value || 0)
    return {
      id,
      name: row.name || row.title || '',
      title: row.title || row.name || '',
      cover: row.cover || row.image || '',
      image: row.image || row.cover || '',
      price: row.price || 0,
      sales: Number(row.sales || 0),
      virtual_sales: Number(row.virtual_sales || 0),
      display_sales: Number(row.display_sales || 0)
    }
  }).filter(item => {
    if (item.id <= 0 || seen.has(item.id)) {
      return false
    }
    seen.add(item.id)
    return true
  })
}

function normalizeCategoryTabs(tabs, fallbackIds) {
  const source = Array.isArray(tabs) && tabs.length ? tabs : normalizeIds(fallbackIds)
  return source.map(item => {
    if (item && typeof item === 'object') {
      const id = Number(item.id || item.value || 0)
      const option = topCategoryOptions.value.find(row => row.id === id)
      return {
        id,
        name: item.name || item.label || (option ? option.name : ''),
        subtitle: item.subtitle || '',
        image: item.image || (option ? option.image : '')
      }
    }
    const id = Number(item || 0)
    const option = topCategoryOptions.value.find(row => row.id === id)
    return {
      id,
      name: option ? option.name : '',
      subtitle: '',
      image: option ? option.image : ''
    }
  }).filter(item => item.id > 0)
}

function syncCategoryTabs() {
  const allowMap = topCategoryOptions.value.reduce((map, item) => {
    map[item.id] = item
    return map
  }, {})
  const selectedIds = normalizeIds(localConfig.category_ids).filter(id => !topCategoryOptions.value.length || allowMap[id])
  const oldMap = (localConfig.category_tabs || []).reduce((map, item) => {
    map[Number(item.id || 0)] = item
    return map
  }, {})
  const orderedOldIds = normalizeIds(localConfig.category_tabs).filter(id => selectedIds.includes(id))
  const ids = orderedOldIds.concat(selectedIds.filter(id => !orderedOldIds.includes(id)))
  const tabs = ids.map(id => {
    const old = oldMap[id] || {}
    const option = allowMap[id] || {}
    return {
      id,
      name: old.name || option.name || '',
      subtitle: old.subtitle || '',
      image: old.image || option.image || ''
    }
  })

  if (JSON.stringify(localConfig.category_ids) !== JSON.stringify(ids)) {
    localConfig.category_ids = ids
  }
  if (JSON.stringify(localConfig.category_tabs) !== JSON.stringify(tabs)) {
    localConfig.category_tabs = tabs
  }
}

function buildConfig() {
  const cfg = {
    ...localConfig,
    layout: localConfig.layout === 'list' ? 'list' : 'grid',
    with_nav: Boolean(localConfig.with_nav),
    tab_style: ['subtitle', 'arc', 'pill', 'image'].includes(localConfig.tab_style) ? localConfig.tab_style : 'subtitle',
    show_count: Math.max(1, Math.min(50, Number(localConfig.show_count || 6))),
    sort_by: ['default', 'sales', 'price'].includes(localConfig.sort_by) ? localConfig.sort_by : 'default',
    manual_ids: normalizeManualProducts(localConfig.manual_ids),
    category_id: Number(localConfig.category_id || 0),
    tag_id: Number(localConfig.tag_id || 0),
    brand_id: Number(localConfig.brand_id || 0)
  }
  cfg.load_more = props.isLast

  if (cfg.with_nav) {
    cfg.data_source = 'category_tabs'
    cfg.source_type = 'category'
    cfg.category_tabs = normalizeCategoryTabs(localConfig.category_tabs, localConfig.category_ids)
    cfg.category_ids = normalizeIds(cfg.category_tabs)
  } else {
    cfg.data_source = cfg.source_type
    cfg.category_ids = []
    cfg.category_tabs = []
  }

  return cfg
}

function emitConfig() {
  const cfg = buildConfig()
  lastEmit.value = JSON.stringify(cfg)
  emit('change', cfg)
}

function getConfig() {
  return buildConfig()
}

function applyConfig(val) {
  if (!val || Object.keys(val).length === 0) return
  if (lastEmit.value && JSON.stringify(val) === lastEmit.value) return
  const configuredTabs = normalizeCategoryTabs(val.category_tabs || [], [])
  const configuredCategoryIds = normalizeIds(val.category_ids || [])
  const categoryIds = configuredCategoryIds.length ? configuredCategoryIds : normalizeIds(configuredTabs)
  fromProp.value = true
  Object.assign(localConfig, {
    title: val.title || '好物推荐',
    layout: val.layout === 'list' ? 'list' : 'grid',
    with_nav: val.with_nav !== false,
    tab_style: ['subtitle', 'arc', 'pill', 'image'].includes(val.tab_style) ? val.tab_style : 'subtitle',
    category_ids: categoryIds,
    category_tabs: configuredTabs.length ? configuredTabs : normalizeCategoryTabs([], categoryIds),
    source_type: ['manual', 'category', 'tag', 'brand'].includes(val.source_type) ? val.source_type : 'manual',
    manual_ids: normalizeManualProducts(val.manual_ids || []),
    category_id: Number(val.category_id || 0),
    tag_id: Number(val.tag_id || 0),
    brand_id: Number(val.brand_id || 0),
    show_count: Number(val.show_count || 6),
    load_more: val.load_more === true || Number(val.load_more || 0) === 1,
    sort_by: ['default', 'sales', 'price'].includes(val.sort_by) ? val.sort_by : 'default',
    bg_color: val.bg_color || '#ffffff'
  })
  if (localConfig.with_nav) {
    syncCategoryTabs()
  }
  nextTick(() => {
    fromProp.value = false
  })
}

async function fetchOptions() {
  try {
    const [categoryRes, tagRes, brandRes] = await Promise.all([
      getProductCategoryTree({ owner_id: 0 }),
      getTagOptions({ tag_type: 2, status: 1 }),
      getBrandOptions({ status: 1 })
    ])
    categoryOptions.value = Array.isArray(categoryRes.data) ? categoryRes.data : []
    const tagData = tagRes.data || {}
    tagOptions.value = (Array.isArray(tagData.list) ? tagData.list : (Array.isArray(tagRes.data) ? tagRes.data : []))
      .map(item => ({
        id: Number(item.id || item.value || 0),
        name: item.name || item.label || ''
      }))
      .filter(item => item.id > 0)
    const brandData = brandRes.data || {}
    brandOptions.value = (Array.isArray(brandData.list) ? brandData.list : (Array.isArray(brandRes.data) ? brandRes.data : []))
      .map(item => ({
        id: Number(item.id || item.value || 0),
        name: item.name || item.label || ''
      }))
      .filter(item => item.id > 0)
    if (localConfig.with_nav) {
      syncCategoryTabs()
      emitConfig()
    }
  } catch (error) {
    console.error(error)
    ElMessage.warning('商品组件选项加载失败')
  }
}

function handleCategoryTabsChange() {
  syncCategoryTabs()
}

function removeCategoryTab(index) {
  const tabs = localConfig.category_tabs.slice()
  const removed = tabs.splice(index, 1)[0]
  localConfig.category_tabs = tabs
  localConfig.category_ids = normalizeIds(localConfig.category_ids).filter(id => id !== Number(removed && removed.id || 0))
}

function handleCategoryTabsSortChange() {
  localConfig.category_ids = normalizeIds(localConfig.category_tabs)
}

function handleTabImageChange(index, val) {
  if (!localConfig.category_tabs[index]) return
  localConfig.category_tabs[index] = {
    ...localConfig.category_tabs[index],
    image: val || ''
  }
}

function openProductSelector() {
  tempSelected.value = normalizeManualProducts(localConfig.manual_ids)
  productPage.value = 1
  searchKeyword.value = ''
  selectorVisible.value = true
}

async function loadProductList() {
  productLoading.value = true
  try {
    const res = await getStoreProductList({
      page: productPage.value,
      limit: 10,
      name: searchKeyword.value || undefined,
      status: 1,
      audit_status: 1
    })
    const data = res.data || {}
    productList.value = Array.isArray(data.list) ? data.list : []
    productTotal.value = Number(data.total || data.count || 0)
    nextTick(() => {
      syncTableSelection()
    })
  } catch (error) {
    console.error(error)
    productList.value = []
    productTotal.value = 0
  } finally {
    productLoading.value = false
  }
}

function syncTableSelection() {
  if (!productTable.value) return
  const selectedIds = normalizeIds(tempSelected.value)
  productList.value.forEach(row => {
    productTable.value.toggleRowSelection(row, selectedIds.includes(Number(row.id || 0)))
  })
}

function handleSearch() {
  productPage.value = 1
  loadProductList()
}

function handlePageChange(page) {
  productPage.value = page
  loadProductList()
}

function handleSelectionChange(rows) {
  const currentPageIds = normalizeIds(productList.value)
  const next = tempSelected.value.filter(item => !currentPageIds.includes(Number(item.id || 0)))
  tempSelected.value = normalizeManualProducts(next.concat(rows))
}

function confirmSelection() {
  localConfig.manual_ids = normalizeManualProducts(tempSelected.value)
  selectorVisible.value = false
}

function removeManualProduct(index) {
  const list = localConfig.manual_ids.slice()
  list.splice(index, 1)
  localConfig.manual_ids = list
}

function formatPrice(value) {
  const price = Number(value || 0)
  return Number.isFinite(price) ? price.toFixed(2) : '0.00'
}

function getDisplaySales(row) {
  const displaySales = Number(row.display_sales || 0)
  if (displaySales > 0) {
    return displaySales
  }
  return Number(row.sales || 0) + Number(row.virtual_sales || 0)
}

watch(() => props.config, applyConfig, {
  immediate: true,
  deep: true
})

watch(() => props.isLast, val => {
  if (fromProp.value) return
  localConfig.load_more = val
  emitConfig()
})

watch(localConfig, () => {
  if (fromProp.value) return
  if (localConfig.with_nav) {
    syncCategoryTabs()
  }
  emitConfig()
}, {
  deep: true
})

fetchOptions()

defineExpose({
  getConfig
})
</script>

<style lang="scss" scoped>
.product-editor {
  .tip-text {
    color: #909399;
    font-size: 12px;
    line-height: 32px;
  }

  .tab-style-radios {
    display: flex;
    flex-wrap: wrap;
    gap: 6px 12px;
  }

  .tab-config-list,
  .selected-list {
    margin-left: 100px;
    margin-bottom: 12px;
  }

  .tab-config-title {
    margin-bottom: 8px;
    color: var(--dashboard-text-primary);
    font-size: 13px;
    font-weight: 600;
    line-height: 20px;
  }

  .tab-config-empty {
    padding: 18px 12px;
    border: 1px dashed var(--dashboard-border);
    border-radius: 6px;
    color: var(--dashboard-text-secondary);
    background: var(--dashboard-surface-muted);
    font-size: 12px;
    text-align: center;
  }

  .tab-card-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .tab-config-card {
    position: relative;
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 74px;
    padding: 12px 34px 12px 10px;
    border: 1px solid var(--dashboard-border);
    border-radius: 6px;
    background: #fff;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);

    &:hover {
      border-color: var(--dashboard-accent-light);
    }
  }

  .tab-drag-handle {
    flex-shrink: 0;
    color: #c0c4cc;
    font-size: 16px;
    cursor: move;

    &:hover {
      color: #909399;
    }
  }

  .tab-thumb {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 58px;
    height: 58px;
    border: 1px dashed #dcdfe6;
    border-radius: 6px;
    background: #f5f7fa;
    overflow: hidden;

    &:hover {
      border-color: var(--prev-color-primary);
    }

    img {
      display: block;
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
  }

  .tab-thumb__placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    color: #c0c4cc;
    font-size: 20px;
  }

  .tab-thumb__upload {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;

    :deep(.el-upload),
    :deep(.el-upload-dragger),
    :deep(.el-upload--text) {
      display: block;
      width: 100%;
      height: 100%;
    }
  }

  .tab-thumb__delete {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    background: rgba(0, 0, 0, 0.45);
    font-size: 16px;
    opacity: 0;
    cursor: pointer;
    transition: opacity 0.2s;
  }

  .tab-thumb:hover .tab-thumb__delete {
    opacity: 1;
  }

  .tab-card-fields {
    display: flex;
    flex: 1;
    min-width: 0;
    flex-direction: column;
    gap: 8px;
  }

  .tab-card-field {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .tab-card-field__label {
    flex-shrink: 0;
    width: 42px;
    color: var(--dashboard-text-secondary);
    font-size: 12px;
    line-height: 22px;
  }

  .tab-card-remove {
    position: absolute;
    top: 8px;
    right: 8px;
    z-index: 2;
    color: #c0c4cc;
    font-size: 16px;
    cursor: pointer;
    transition: color 0.2s;

    &:hover {
      color: #f56c6c;
    }
  }

  .selected-list {
    border: 1px solid var(--dashboard-border);
    border-radius: 6px;
    padding: 4px 0;
    max-height: 180px;
    overflow-y: auto;
  }

  .selected-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 10px;
    font-size: 12px;

    &:hover {
      background: var(--dashboard-surface-muted);
    }
  }

  .selected-cover {
    width: 32px;
    height: 32px;
    border-radius: 4px;
    object-fit: cover;
    flex-shrink: 0;
  }

  .selected-title {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: var(--dashboard-text-primary);
  }
}

.selector-search {
  display: flex;
  align-items: center;
}

.product-cover {
  width: 48px;
  height: 48px;
  object-fit: cover;
  border-radius: 4px;
}

.no-cover {
  color: var(--dashboard-text-muted);
  font-size: 12px;
}
</style>
