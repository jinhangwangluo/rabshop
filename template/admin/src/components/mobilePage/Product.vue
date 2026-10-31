<template>
  <common-wrapper :config="config">
    <div class="product-area" :style="{ background: bgColor }">
      <div v-if="!withNav && title" class="diy-section-header">
        <span class="diy-section-title">{{ title }}</span>
      </div>

      <div v-if="withNav" class="product-tabs" :class="'product-tabs--' + tabStyle">
        <button
          v-for="item in displayTabs"
          :key="item.id"
          type="button"
          class="product-tab"
          :class="{ 'product-tab--active': item.id === currentCategoryId }"
          @click="handleTabClick(item.id)"
        >
          <span v-if="tabStyle === 'image'" class="product-tab__image-wrap">
            <img v-if="item.image" class="product-tab__image" :src="item.image" alt="">
            <LegacyIcon v-else name="el-icon-picture-outline" />
          </span>
          <span class="product-tab__title">{{ item.name }}</span>
          <span v-if="tabStyle === 'subtitle' && item.subtitle" class="product-tab__subtitle">{{ item.subtitle }}</span>
          <span v-if="tabStyle === 'arc' && item.id === currentCategoryId" class="product-tab__arc" />
        </button>
      </div>

      <div v-if="previewList.length === 0" class="diy-empty">
        <LegacyIcon name="el-icon-goods" />
        <span>暂无商品数据</span>
      </div>

      <div v-else class="product-list" :class="'product-list--' + layout">
        <div
          v-for="item in previewList"
          :key="item.renderKey"
          class="product-card"
          :class="'product-card--' + layout"
          @click.prevent
        >
          <div class="product-card__cover" :style="imageRadiusStyle">
            <img v-if="item.image" class="product-card__image" :src="item.image" :alt="item.title">
            <div v-else class="product-cover-placeholder">
              <LegacyIcon name="el-icon-picture-outline" />
            </div>
          </div>
          <div class="product-card__body">
            <div class="product-card__title">{{ item.title }}</div>
            <div v-if="item.tags.length" class="product-card__tags">
              <tag-preview
                v-for="tag in item.tags"
                :key="item.renderKey + '-' + tag.renderKey"
                :tag="tag"
                size="small"
                class="product-card-tag-preview"
              />
            </div>
            <div class="product-card__footer">
              <div class="product-card__price-wrap">
                <span class="product-card__price">{{ item.priceText }}</span>
                <span class="product-card__sales">{{ item.salesText }}</span>
              </div>
              <span class="product-card__cart">
                <img class="product-card__cart-icon" :src="cartIcon" alt="购物车">
              </span>
            </div>
          </div>
        </div>
      </div>

      <div v-if="loadMore" class="load-more-tip">触底加载更多</div>
    </div>
  </common-wrapper>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import CommonWrapper from './CommonWrapper'
import { getStoreProductList } from '@/api/product'
import TagPreview from '@/components/TagPreview'
import { getImageRadiusStyle } from './imageRadius'
import cartIcon from '@/assets/images/diy/product-cart.png'

const PRODUCT_THEME_PRIMARY = 'var(--brand-primary, var(--theme-primary, var(--prev-color-primary, #ea580c)))'
const PRODUCT_THEME_LIGHT = 'var(--brand-primary-light, var(--theme-secondary, var(--dashboard-accent-light, #fb923c)))'
const PRODUCT_THEME_PALE = 'var(--brand-primary-pale, var(--prev-color-primary-light-9, #fff7ed))'

const DATA_FIELDS = [
  'layout',
  'with_nav',
  'tab_style',
  'source_type',
  'category_id',
  'tag_id',
  'brand_id',
  'show_count',
  'sort_by',
  'manual_ids',
  'category_ids',
  'category_tabs'
]

const DEFAULT_TABS = [
  { id: 101, name: '首发新品', subtitle: '最新出炉', image: '' },
  { id: 102, name: '外事礼品', subtitle: '精选好礼', image: '' }
]

const DEFAULT_PREVIEW_LIST = [
  {
    id: 1001,
    name: '蓝牙音乐手表 Jeep智能表蓝牙通话健康管理',
    price: 77,
    ot_price: 129,
    sales: 6,
    virtual_sales: 20,
    tag_list: [{ name: '包邮' }, { name: '领券' }]
  },
  {
    id: 1002,
    name: '轻奢香氛礼盒 质感家居香薰套装',
    price: 98,
    ot_price: 168,
    sales: 12,
    virtual_sales: 42,
    tag_list: [{ name: '精选' }, { name: '热销' }]
  }
]

defineOptions({
  name: 'Product'
})

const props = defineProps({
  config: {
    type: Object,
    default: null
  }
})

const title = ref('好物推荐')
const list = ref([])
const tabs = ref([])
const activeCategoryId = ref(0)
const layout = ref('grid')
const withNav = ref(true)
const tabStyle = ref('subtitle')
const bgColor = ref('#ffffff')
const showCount = ref(6)
const loadMore = ref(false)
const lastDataKey = ref('')
const categoryConfig = ref(null)

const imageRadiusStyle = computed(() => getImageRadiusStyle(props.config && props.config.style_config))
const displayTabs = computed(() => tabs.value.length > 0 ? tabs.value : DEFAULT_TABS)
const currentCategoryId = computed(() => {
  const exists = displayTabs.value.some(item => item.id === activeCategoryId.value)
  return exists ? activeCategoryId.value : (displayTabs.value[0] ? displayTabs.value[0].id : 0)
})
const sourceList = computed(() => {
  if (!withNav.value) {
    return list.value
  }
  const activeTab = displayTabs.value.find(item => item.id === currentCategoryId.value)
  return activeTab && Array.isArray(activeTab.items) ? activeTab.items : []
})
const previewList = computed(() => {
  const source = sourceList.value.length > 0 ? sourceList.value : DEFAULT_PREVIEW_LIST
  return sortProducts(source)
    .slice(0, Number(showCount.value || source.length || 6))
    .map((item, index) => normalizeProductItem(item, index))
})

watch(() => props.config, setConfig, { deep: true, immediate: true })

function setConfig(data) {
  if (!data) return

  title.value = data.title || '好物推荐'
  layout.value = data.layout === 'list' ? 'list' : 'grid'
  withNav.value = data.with_nav !== false
  tabStyle.value = ['subtitle', 'arc', 'pill', 'image'].includes(data.tab_style) ? data.tab_style : 'subtitle'
  bgColor.value = data.bg_color || '#ffffff'
  showCount.value = Number(data.show_count || 6)
  loadMore.value = data.load_more === true || Number(data.load_more || 0) === 1

  if (withNav.value) {
    checkAndFetchTabs(data)
    return
  }

  tabs.value = []
  activeCategoryId.value = 0
  categoryConfig.value = null
  checkAndFetchList(data)
}

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

function normalizeTabs(data) {
  const configuredTabs = Array.isArray(data.category_tabs) && data.category_tabs.length
    ? data.category_tabs
    : normalizeIds(data.category_ids)

  return configuredTabs.map(item => {
    if (item && typeof item === 'object') {
      const id = Number(item.id || item.value || 0)
      return {
        id,
        name: item.name || item.label || ('分类 #' + id),
        subtitle: item.subtitle || '',
        image: item.image || '',
        items: [],
        loaded: false,
        loading: false
      }
    }

    const id = Number(item || 0)
    return {
      id,
      name: '分类 #' + id,
      subtitle: '',
      image: '',
      items: [],
      loaded: false,
      loading: false
    }
  }).filter(item => item.id > 0)
}

function checkAndFetchTabs(data) {
  const nextTabs = normalizeTabs(data)
  const oldMap = tabs.value.reduce((map, item) => {
    map[item.id] = {
      items: item.items || [],
      loaded: item.loaded === true
    }
    return map
  }, {})

  tabs.value = nextTabs.map(item => {
    const old = oldMap[item.id] || {}
    return {
      ...item,
      items: old.items || [],
      loaded: old.loaded === true,
      loading: false
    }
  })
  if (!activeCategoryId.value || !tabs.value.some(item => item.id === activeCategoryId.value)) {
    activeCategoryId.value = tabs.value.length ? tabs.value[0].id : 0
  }
  categoryConfig.value = data
  loadTabProducts(activeCategoryId.value)
}

function handleTabClick(categoryId) {
  activeCategoryId.value = Number(categoryId || 0)
  loadTabProducts(activeCategoryId.value)
}

function loadTabProducts(categoryId) {
  const id = Number(categoryId || 0)
  const activeTab = tabs.value.find(item => item.id === id)
  if (!activeTab || activeTab.loaded === true || activeTab.loading === true) return

  updateTabState(id, { loading: true })
  fetchProductList({
    category_id: id,
    include_child_categories: 1,
    show_count: categoryConfig.value ? categoryConfig.value.show_count : showCount.value,
    sort_by: categoryConfig.value ? categoryConfig.value.sort_by : 'default'
  }).then(items => {
    updateTabState(id, {
      items,
      loaded: true,
      loading: false
    })
  }).catch(() => {
    updateTabState(id, {
      items: [],
      loaded: true,
      loading: false
    })
  })
}

function updateTabState(categoryId, patch) {
  tabs.value = tabs.value.map(item => item.id === categoryId ? { ...item, ...patch } : item)
}

function checkAndFetchList(data) {
  if (data.source_type === 'manual') {
    list.value = normalizeManualProducts(data.manual_ids || [])
    return
  }

  if (
    (data.source_type === 'category' && !Number(data.category_id || 0)) ||
    (data.source_type === 'tag' && !Number(data.tag_id || 0)) ||
    (data.source_type === 'brand' && !Number(data.brand_id || 0))
  ) {
    list.value = []
    lastDataKey.value = ''
    return
  }

  const key = JSON.stringify(DATA_FIELDS.reduce((obj, field) => {
    obj[field] = data[field]
    return obj
  }, {}))
  if (key === lastDataKey.value) return
  lastDataKey.value = key
  fetchProductList(data).then(items => {
    list.value = items
  }).catch(() => {
    list.value = []
  })
}

function fetchProductList(data) {
  const params = {
    page: 1,
    limit: Number(data.show_count || showCount.value || 6),
    status: 1,
    audit_status: 1
  }
  if (data.category_id) {
    params.category_id = data.category_id
    params.include_child_categories = 1
  }
  if (data.tag_id) {
    params.tag_id = data.tag_id
  }
  if (data.brand_id) {
    params.brand_id = data.brand_id
  }

  return getStoreProductList(params).then(res => {
    const dataBody = res.data || {}
    const rows = Array.isArray(dataBody.list) ? dataBody.list : []
    return sortProducts(rows)
  })
}

function normalizeManualProducts(value) {
  if (!Array.isArray(value)) {
    value = value ? [value] : []
  }
  return value.map(item => {
    if (item && typeof item === 'object') {
      return {
        ...item,
        name: item.name || item.title || '',
        cover: item.cover || item.image || '',
        image: item.image || item.cover || ''
      }
    }
    return {
      id: item,
      name: '商品 #' + item,
      cover: '',
      image: ''
    }
  })
}

function sortProducts(rows) {
  const sortBy = props.config && props.config.sort_by ? props.config.sort_by : 'default'
  const list = Array.isArray(rows) ? rows.slice() : []
  if (sortBy === 'sales') {
    return list.sort((a, b) => getDisplaySales(b) - getDisplaySales(a))
  }
  if (sortBy === 'price') {
    return list.sort((a, b) => Number(a.price || 0) - Number(b.price || 0))
  }
  return list
}

function normalizeProductItem(item, index) {
  const price = Number(item.price || 0)
  const displaySales = getDisplaySales(item)
  return {
    ...item,
    renderKey: `${item.id || item.name || 'product'}_${index}`,
    title: item.title || item.name || '商品名称',
    image: item.image || item.cover || '',
    priceText: item.price_text || `¥${Number.isFinite(price) ? price.toFixed(2) : '0.00'}`,
    salesText: item.sales_text || `已售${displaySales}件`,
    tags: normalizePreviewTags(item.tag_list || item.tags || item.keywords || [])
  }
}

function getDisplaySales(item) {
  const displaySales = Number(item.display_sales || 0)
  if (displaySales > 0) {
    return displaySales
  }
  return Number(item.sales || 0) + Number(item.virtual_sales || 0)
}

function normalizePreviewTags(value) {
  let list = value
  if (typeof list === 'string') {
    list = list
      .split(/[,，、\s]+/)
      .map(tag => tag.trim())
      .filter(Boolean)
  }
  if (!Array.isArray(list)) {
    list = list ? [list] : []
  }
  return list
    .map((tag, index) => buildPreviewTag(tag, index))
    .filter(Boolean)
    .slice(0, 2)
}

function buildPreviewTag(tag, index = 0) {
  const source = tag && typeof tag === 'object' ? tag : { name: tag }
  const name = String(source.name || source.label || source.title || source.text || '').trim()
  if (!name && !source.icon) {
    return null
  }

  const palette = [
    { text_color: PRODUCT_THEME_PRIMARY, bg_color: PRODUCT_THEME_PALE, border_color: PRODUCT_THEME_LIGHT },
    { text_color: PRODUCT_THEME_PRIMARY, bg_color: PRODUCT_THEME_PALE, border_color: PRODUCT_THEME_LIGHT }
  ][index % 2]

  return {
    ...source,
    id: Number(source.id || source.value || index + 1),
    name,
    label: String(source.label || name).trim(),
    effect_type: Number(source.effect_type || source.effectType || (source.icon ? 2 : 1)) === 2 ? 2 : 1,
    text_color: source.text_color || source.textColor || palette.text_color,
    bg_color: source.bg_color || source.bgColor || palette.bg_color,
    border_color: source.border_color || source.borderColor || palette.border_color,
    icon: source.icon || '',
    renderKey: String(source.renderKey || source.render_key || source.id || source.value || `${name}-${index}`)
  }
}
</script>

<style scoped lang="scss">
.product-area {
  --product-theme-primary: var(--brand-primary, var(--theme-primary, var(--prev-color-primary, #ea580c)));
  --product-theme-light: var(--brand-primary-light, var(--theme-secondary, var(--dashboard-accent-light, #fb923c)));
  --product-theme-pale: var(--brand-primary-pale, var(--prev-color-primary-light-9, #fff7ed));
  --product-theme-shadow: var(--brand-shadow, rgba(234, 88, 12, 0.18));
  width: auto;
  padding-bottom: 8px;
  box-sizing: border-box;
}

.diy-section-header {
  display: flex;
  align-items: center;
  padding: 8px 10px 9px;
}

.diy-section-title {
  min-width: 0;
  color: #1a1c1c;
  font-size: 16px;
  font-weight: 800;
  line-height: 22px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.product-tabs {
  display: flex;
  align-items: center;
  min-height: 48px;
  padding: 8px 10px;
  overflow-x: auto;
  overflow-y: hidden;
  box-sizing: border-box;
  background: #ffffff;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
}

.product-tabs::-webkit-scrollbar {
  display: none;
}

.product-tab {
  position: relative;
  flex-shrink: 0;
  min-width: 72px;
  margin-right: 10px;
  padding: 0;
  border: 0;
  color: #22252a;
  background: transparent;
  font-size: 15px;
  font-weight: 700;
  line-height: 20px;
  cursor: pointer;
  outline: none;
  text-align: center;
}

.product-tab__title,
.product-tab__subtitle {
  display: block;
  max-width: 96px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.product-tab__subtitle {
  margin-top: 2px;
  color: #9ca0a8;
  font-size: 10px;
  line-height: 14px;
  font-weight: 500;
}

.product-tab--active {
  color: var(--product-theme-primary);
}

.product-tabs--arc .product-tab {
  padding-bottom: 10px;
}

.product-tab__arc {
  position: absolute;
  left: 50%;
  bottom: 0;
  width: 22px;
  height: 11px;
  border: 3px solid var(--product-theme-primary);
  border-top: 0;
  border-radius: 0 0 22px 22px;
  box-sizing: border-box;
  transform: translateX(-50%);
}

.product-tabs--pill .product-tab {
  min-width: auto;
  padding: 6px 13px;
  border-radius: 999px;
  color: #22252a;
  background: transparent;
}

.product-tabs--pill .product-tab--active {
  color: #ffffff;
  background: var(--product-theme-primary);
}

.product-tabs--image {
  align-items: flex-start;
  min-height: 82px;
}

.product-tabs--image .product-tab {
  min-width: 66px;
}

.product-tab__image-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 52px;
  height: 52px;
  margin: 0 auto 6px;
  border-radius: 50%;
  border: 1px solid #eceff5;
  color: #cdd4df;
  overflow: hidden;
  background: #f6f9fd;
}

.product-tab--active .product-tab__image-wrap {
  border-color: var(--product-theme-primary);
}

.product-tab__image {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.diy-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 120px;
  color: #c0c4cc;
  font-size: 12px;
}

.diy-empty i {
  margin-bottom: 8px;
  font-size: 28px;
}

.product-list {
  box-sizing: border-box;
  padding: 0 10px;
}

.product-list--grid {
  display: flex;
  flex-wrap: wrap;
  margin-left: -5px;
  margin-right: -5px;
}

.product-card {
  background: #ffffff;
  box-shadow: 0 5px 15px rgba(15, 23, 42, 0.06);
  overflow: hidden;
  box-sizing: border-box;
}

.product-card--list {
  display: flex;
  min-height: 118px;
  margin-bottom: 10px;
  padding: 8px;
  border-radius: 10px;
}

.product-card--grid {
  width: calc(50% - 10px);
  margin: 0 5px 10px;
  border-radius: 10px;
}

.product-card__cover {
  position: relative;
  flex-shrink: 0;
  overflow: hidden;
  background: #eff3f7;
}

.product-card--list .product-card__cover {
  width: 96px;
  height: 102px;
  border-radius: 9px;
}

.product-card--grid .product-card__cover {
  width: 100%;
  height: 132px;
}

.product-card__image,
.product-cover-placeholder {
  display: block;
  width: 100%;
  height: 100%;
}

.product-card__image {
  object-fit: cover;
}

.product-cover-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  color: #cdd4df;
  font-size: 28px;
}

.product-card__body {
  min-width: 0;
  box-sizing: border-box;
}

.product-card--list .product-card__body {
  flex: 1;
  display: flex;
  flex-direction: column;
  padding-left: 10px;
}

.product-card--grid .product-card__body {
  padding: 8px 8px 9px;
}

.product-card__title {
  color: #15171a;
  font-weight: 800;
  overflow: hidden;
}

.product-card--list .product-card__title {
  height: 38px;
  font-size: 14px;
  line-height: 19px;
}

.product-card--grid .product-card__title {
  height: 36px;
  font-size: 13px;
  line-height: 18px;
}

.product-card__tags {
  display: flex;
  align-items: center;
  min-height: 18px;
  margin-top: 4px;
  overflow: hidden;
}

.product-card-tag-preview {
  margin-right: 4px;
}

.product-card__footer {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  margin-top: 8px;
}

.product-card--list .product-card__footer {
  margin-top: auto;
}

.product-card__price-wrap {
  flex: 1;
  min-width: 0;
}

.product-card__price {
  color: var(--product-theme-primary);
  font-size: 17px;
  font-weight: 900;
  line-height: 22px;
}

.product-card__sales {
  display: block;
  margin-top: 2px;
  color: #9ca0a8;
  font-size: 10px;
  line-height: 14px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.product-card__cart {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  margin-left: 8px;
  border-radius: 50%;
  color: #ffffff;
  background: var(--product-theme-primary);
  box-shadow: 0 4px 10px var(--product-theme-shadow);
}

.product-card__cart-icon {
  display: block;
  width: 18px;
  height: 18px;
  object-fit: contain;
}

.load-more-tip {
  padding: 8px 0 0;
  color: #999999;
  font-size: 12px;
  line-height: 18px;
  text-align: center;
}
</style>
