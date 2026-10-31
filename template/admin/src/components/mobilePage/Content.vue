<template>
  <common-wrapper :config="config">
    <div class="content-area" :style="{ background: bgColor }">
      <div v-if="!isCategoryTabsStyle && (title || showMore)" class="diy-section-header">
        <span v-if="title" class="diy-section-title">{{ title }}</span>
        <span v-if="showMore" class="diy-section-more">
          {{ moreText }} <LegacyIcon name="el-icon-arrow-right" />
        </span>
      </div>

      <div v-if="isCategoryTabsStyle" class="content-category-tabs">
        <div v-if="displayCategoryTabs.length > 0" class="category-tabs-scroll">
          <button
            v-for="item in displayCategoryTabs"
            :key="item.id"
            type="button"
            class="category-tab"
            :class="{ 'category-tab--active': item.id === currentCategoryId }"
            @click="handleCategoryTabClick(item.id)"
          >
            <span class="category-tab__text">{{ item.name }}</span>
            <span v-if="item.id === currentCategoryId" class="category-tab__line" />
          </button>
        </div>

        <div v-if="categoryPreviewList.length === 0" class="diy-empty">
          <LegacyIcon name="el-icon-reading" />
          <span>暂无内容数据</span>
        </div>

        <div v-else class="content-display-list content-display-list--tabs">
          <div
            v-for="item in categoryPreviewList"
            :key="item.renderKey"
            class="content-list-card"
            @click.prevent
          >
            <div v-if="showCover" class="content-list-card__cover" :style="imageRadiusStyle">
              <img v-if="item.image" class="content-list-card__image" :src="item.image" :alt="item.title">
              <div v-else class="content-cover-placeholder">
                <span>{{ item.typeLabel }}</span>
              </div>
              <div v-if="item.isVideo" class="content-list-card__play">
                <LegacyIcon name="el-icon-video-play" />
              </div>
            </div>
            <div class="content-list-card__body" :class="{ 'content-list-card__body--full': !showCover }">
              <div class="content-list-card__title">{{ item.title }}</div>
              <div v-if="showDesc && item.description" class="content-list-card__desc">{{ item.description }}</div>
              <div class="content-list-card__price-row">
                <span class="content-list-card__price">{{ item.priceText }}</span>
                <span v-if="item.memberPriceText" class="content-list-card__vip">VIP</span>
                <span v-if="item.memberPriceText" class="content-list-card__vip-price">{{ item.memberPriceText }}</span>
              </div>
              <div v-if="item.tags.length" class="content-list-card__tags">
                <tag-preview
                  v-for="tag in item.tags"
                  :key="item.renderKey + '-' + tag.renderKey"
                  :tag="tag"
                  class="content-card-tag-preview"
                />
              </div>
              <div class="content-list-card__foot">
                <span class="content-list-card__type">
                  <LegacyIcon :name="item.typeIcon" />
                  <span>{{ item.typeLabel }}</span>
                </span>
                <span v-if="showPrice" class="content-cart-btn content-cart-btn--outline">
                  <LegacyIcon name="el-icon-shopping-cart-2" />
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-else-if="previewList.length === 0" class="diy-empty">
        <LegacyIcon name="el-icon-reading" />
        <span>暂无内容数据</span>
      </div>

      <div v-else-if="style === 'card' || style === 'waterfall'" class="content-display-card">
        <div
          v-for="(item, index) in previewList"
          :key="item.renderKey || index"
          class="content-grid-item"
          @click.prevent
        >
          <div class="content-grid-card">
            <div v-if="showCover" class="content-grid-card__cover" :style="imageRadiusStyle">
              <img v-if="item.image" class="content-grid-card__image" :src="item.image" :alt="item.title">
              <div v-else class="content-cover-placeholder">
                <span>{{ item.typeLabel }}</span>
              </div>
              <span v-if="showPrice" class="content-cart-btn content-cart-btn--cover">
                <LegacyIcon name="el-icon-shopping-cart-2" />
              </span>
              <div v-if="item.isVideo" class="content-grid-card__play">
                <LegacyIcon name="el-icon-video-play" />
              </div>
            </div>
            <div class="content-grid-card__body">
              <div class="content-grid-card__title">{{ item.title }}</div>
              <div v-if="showDesc && item.description" class="content-grid-card__desc">{{ item.description }}</div>
              <div v-if="item.tags.length" class="content-grid-card__tags">
                <tag-preview
                  v-for="tag in item.tags"
                  :key="item.renderKey + '-' + tag.renderKey"
                  :tag="tag"
                  class="content-card-tag-preview"
                />
              </div>
              <div class="content-grid-card__price-row">
                <span class="content-grid-card__price">{{ item.priceText }}</span>
                <span v-if="item.originalPriceText" class="content-grid-card__original">{{ item.originalPriceText }}</span>
              </div>
              <div v-if="item.memberPriceText || item.savedPriceText" class="content-grid-card__deal">
                <span v-if="item.memberPriceText" class="content-grid-card__vip-price">VIP {{ item.memberPriceText }}</span>
                <span v-if="item.savedPriceText" class="content-grid-card__saved">{{ item.savedPriceText }}</span>
              </div>
              <div class="content-grid-card__stat-row">
                <span class="content-grid-card__stat">
                  <LegacyIcon name="el-icon-s-data" />
                  <span>{{ item.statText || '0 浏览' }}</span>
                </span>
                <span class="content-grid-card__stat">
                  <LegacyIcon name="el-icon-document" />
                  <span>{{ item.typeLabel }}</span>
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="content-display-list">
        <div
          v-for="(item, index) in previewList"
          :key="item.renderKey || index"
          class="content-list-card"
          @click.prevent
        >
          <div v-if="showCover" class="content-list-card__cover" :style="imageRadiusStyle">
            <img v-if="item.image" class="content-list-card__image" :src="item.image" :alt="item.title">
            <div v-else class="content-cover-placeholder">
              <span>{{ item.typeLabel }}</span>
            </div>
            <div v-if="item.isVideo" class="content-list-card__play">
              <LegacyIcon name="el-icon-video-play" />
            </div>
          </div>
          <div class="content-list-card__body" :class="{ 'content-list-card__body--full': !showCover }">
            <div class="content-list-card__title">{{ item.title }}</div>
            <div v-if="showDesc && item.description" class="content-list-card__desc">{{ item.description }}</div>
            <div class="content-list-card__price-row">
              <span class="content-list-card__price">{{ item.priceText }}</span>
              <span v-if="item.memberPriceText" class="content-list-card__vip">VIP</span>
              <span v-if="item.memberPriceText" class="content-list-card__vip-price">{{ item.memberPriceText }}</span>
            </div>
            <div v-if="item.tags.length" class="content-list-card__tags">
              <tag-preview
                v-for="tag in item.tags"
                :key="item.renderKey + '-' + tag.renderKey"
                :tag="tag"
                class="content-card-tag-preview"
              />
            </div>
            <div class="content-list-card__foot">
              <span class="content-list-card__type">
                <LegacyIcon :name="item.typeIcon" />
                <span>{{ item.typeLabel }}</span>
              </span>
              <span v-if="showPrice" class="content-cart-btn content-cart-btn--outline">
                <LegacyIcon name="el-icon-shopping-cart-2" />
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
import { getArticleList } from '@/api/article'
import TagPreview from '@/components/TagPreview'
import { getImageRadiusStyle } from './imageRadius'

const DATA_FIELDS = ['data_source', 'category_id', 'content_type', 'show_count', 'sort_by', 'sort_order', 'manual_ids']
const CONTENT_THEME_PRIMARY = 'var(--brand-primary, var(--theme-primary, var(--prev-color-primary, #FF3B30)))'
const CONTENT_THEME_SECONDARY = 'var(--brand-primary-light, var(--theme-secondary, var(--dashboard-accent-light, #FF6A00)))'

const CONTENT_TYPE_LABEL_MAP = {
  1: '图文',
  2: '文字',
  3: '小说',
  4: '图片',
  5: '音频',
  6: '视频',
  article: '图文',
  text: '文字',
  novel: '小说',
  image: '图片',
  audio: '音频',
  video: '视频'
}

const CONTENT_TYPE_ICON_MAP = {
  1: 'el-icon-document',
  2: 'el-icon-tickets',
  3: 'el-icon-notebook-2',
  4: 'el-icon-picture-outline',
  5: 'el-icon-headset',
  6: 'el-icon-video-camera',
  article: 'el-icon-document',
  text: 'el-icon-tickets',
  novel: 'el-icon-notebook-2',
  image: 'el-icon-picture-outline',
  audio: 'el-icon-headset',
  video: 'el-icon-video-camera'
}

const DEFAULT_CATEGORY_TABS = [
  { id: 101, name: '小说' },
  { id: 102, name: '音频' },
  { id: 103, name: '视频' },
  { id: 104, name: '图文' }
]

const DEFAULT_PREVIEW_LIST = [
  {
    id: 1001,
    title: '零基础系统课：从入门到进阶',
    description: '适合新手学习的精品内容，包含案例拆解和实操任务',
    content_type: 3,
    price: 59,
    original_price: 99,
    keywords: '精选,进阶,热门',
    view_count: 2380,
    is_hot: 1
  },
  {
    id: 1002,
    title: '音频专栏：高效学习方法',
    description: '每天十分钟，掌握高效学习节奏和复盘方法',
    content_type: 5,
    price: 29,
    keywords: '音频,专栏',
    view_count: 1260
  },
  {
    id: 1003,
    title: '视频课：内容运营实战',
    description: '用真实案例讲透内容选题、发布和增长链路',
    content_type: 6,
    price: 88,
    original_price: 128,
    keywords: '视频,运营,实战',
    view_count: 5600,
    is_hot: 1
  },
  {
    id: 1004,
    title: '图文教程：企业管理流程',
    description: '用清晰图文梳理企业管理中的关键节点',
    content_type: 1,
    price: 0,
    keywords: '图文,免费',
    view_count: 860
  }
]

defineOptions({
  name: 'Content'
})

const props = defineProps({
  config: {
    type: Object,
    default: null
  }
})

const title = ref('最新课程')
const list = ref([])
const showMore = ref(true)
const moreText = ref('更多内容')
const loadMore = ref(false)
const style = ref('list')
const bgColor = ref('#ffffff')
const showCover = ref(true)
const showDesc = ref(true)
const showPrice = ref(true)
const showCount = ref(3)
const categoryTabs = ref([])
const activeCategoryId = ref(0)
const categoryTabsConfig = ref(null)
const lastDataConfig = ref(null)
const lastCategoryTabsConfig = ref(null)

const isCategoryTabsStyle = computed(() => style.value === 'category_tabs')
const displayCategoryTabs = computed(() => (categoryTabs.value.length > 0 ? categoryTabs.value : DEFAULT_CATEGORY_TABS))
const currentCategoryId = computed(() => {
  const exists = displayCategoryTabs.value.some(item => item.id === activeCategoryId.value)
  return exists ? activeCategoryId.value : (displayCategoryTabs.value[0] ? displayCategoryTabs.value[0].id : 0)
})
const imageRadiusStyle = computed(() => getImageRadiusStyle(props.config && props.config.style_config))
const previewList = computed(() => {
  const source = Array.isArray(list.value) && list.value.length > 0 ? list.value : DEFAULT_PREVIEW_LIST
  return source
    .slice(0, Number(showCount.value || source.length || 3))
    .map((item, index) => normalizeItem(item, index))
})
const activeCategoryItems = computed(() => {
  const activeTab = displayCategoryTabs.value.find(item => item.id === currentCategoryId.value)
  if (activeTab && Array.isArray(activeTab.items) && activeTab.items.length > 0) {
    return activeTab.items
  }
  return DEFAULT_PREVIEW_LIST
})
const categoryPreviewList = computed(() => {
  const limit = Number(showCount.value || activeCategoryItems.value.length || 4)
  return activeCategoryItems.value.slice(0, limit).map((item, index) => normalizeItem(item, index))
})

watch(() => props.config, setConfig, { deep: true, immediate: true })

function setConfig(data) {
  if (!data) return
  title.value = data.title || '最新课程'
  bgColor.value = data.bg_color || '#ffffff'
  showCover.value = data.show_cover !== false
  showDesc.value = data.show_desc !== false
  showPrice.value = data.show_price !== false
  showMore.value = data.show_more !== undefined ? data.show_more : true
  moreText.value = data.more_text || '更多内容'
  loadMore.value = data.load_more === true
  showCount.value = Number(data.show_count || 3)
  style.value = normalizeStyle(data.display_style || data.style)

  if (isCategoryTabsStyle.value) {
    showMore.value = false
    loadMore.value = false
    checkAndFetchCategoryTabs(data)
    return
  }

  categoryTabs.value = []
  activeCategoryId.value = 0
  categoryTabsConfig.value = null

  if (data.list && data.list.length > 0) {
    list.value = data.list
    return
  }
  if (data.items && data.items.length > 0) {
    list.value = data.items
    return
  }

  const dataConfig = JSON.stringify(DATA_FIELDS.reduce((obj, key) => {
    obj[key] = data[key]
    return obj
  }, {}))
  if (dataConfig === lastDataConfig.value) return
  lastDataConfig.value = dataConfig
  fetchPreviewData(data)
}

function normalizeStyle(value) {
  return ['list', 'card', 'waterfall', 'category_tabs'].indexOf(value) > -1 ? value : 'list'
}

function normalizeCategoryIds(value) {
  if (typeof value === 'string') {
    value = value.split(',')
  }
  if (!Array.isArray(value)) {
    value = [value]
  }
  const ids = value.map(item => {
    const id = item && typeof item === 'object' ? item.id : item
    return Number(id || 0)
  }).filter(id => id > 0)
  return Array.from(new Set(ids))
}

function normalizeCategoryTabs(data) {
  const tabs = Array.isArray(data.category_tabs) && data.category_tabs.length
    ? data.category_tabs
    : normalizeCategoryIds(data.category_ids)
  return tabs.map(item => {
    if (item && typeof item === 'object') {
      return {
        id: Number(item.id || item.value || 0),
        name: item.name || item.label || ('分类 #' + (item.id || item.value))
      }
    }
    return {
      id: Number(item || 0),
      name: '分类 #' + item
    }
  }).filter(item => item.id > 0)
}

function checkAndFetchCategoryTabs(data) {
  const tabs = normalizeCategoryTabs(data)
  const oldMap = categoryTabs.value.reduce((map, item) => {
    map[item.id] = {
      items: item.items || [],
      loaded: item.loaded === true
    }
    return map
  }, {})
  let preparedTabs = tabs.map(item => {
    const old = oldMap[item.id] || {}
    return {
      ...item,
      items: old.items || [],
      loaded: old.loaded === true,
      loading: false
    }
  })
  if (!activeCategoryId.value || !preparedTabs.some(item => item.id === activeCategoryId.value)) {
    activeCategoryId.value = preparedTabs.length ? preparedTabs[0].id : 0
  }
  if (!preparedTabs.length) {
    categoryTabs.value = []
    categoryTabsConfig.value = null
    lastCategoryTabsConfig.value = null
    return
  }

  const key = JSON.stringify({
    ids: preparedTabs.map(item => item.id),
    content_type: data.content_type || 'all',
    show_count: data.show_count || 4,
    sort_by: resolvePreviewSortField(data.sort_by),
    sort_order: data.sort_order || 'desc'
  })
  const shouldReset = key !== lastCategoryTabsConfig.value
  if (shouldReset) {
    preparedTabs = tabs.map(item => ({
      ...item,
      items: [],
      loaded: false,
      loading: false
    }))
  }
  lastCategoryTabsConfig.value = key
  categoryTabsConfig.value = data
  categoryTabs.value = preparedTabs

  loadCategoryPreviewById(activeCategoryId.value, data, key)
}

function handleCategoryTabClick(categoryId) {
  activeCategoryId.value = Number(categoryId || 0)
  loadCategoryPreviewById(activeCategoryId.value, categoryTabsConfig.value, lastCategoryTabsConfig.value)
}

function loadCategoryPreviewById(categoryId, data, key) {
  if (!data || !key) return
  const id = Number(categoryId || 0)
  const activeTab = categoryTabs.value.find(item => item.id === id)
  if (!activeTab || activeTab.loaded === true || activeTab.loading === true) return

  updateCategoryTabState(id, { loading: true })
  fetchCategoryPreviewItems(activeTab, data).then(items => {
    if (lastCategoryTabsConfig.value !== key) return
    updateCategoryTabState(id, {
      items,
      loaded: true,
      loading: false
    })
  }).catch(() => {
    if (lastCategoryTabsConfig.value !== key) return
    updateCategoryTabState(id, {
      items: [],
      loaded: true,
      loading: false
    })
  })
}

function updateCategoryTabState(categoryId, patch) {
  const id = Number(categoryId || 0)
  categoryTabs.value = categoryTabs.value.map(tab => {
    return tab.id === id ? { ...tab, ...patch } : tab
  })
}

function extractResponseList(res) {
  const data = res && res.data ? res.data : []
  if (Array.isArray(data)) return data
  if (Array.isArray(data.list)) return data.list
  if (Array.isArray(data.data)) return data.data
  return []
}

function fetchCategoryPreviewItems(tab, data) {
  const baseParams = {
    page: 1,
    limit: data.show_count || 4,
    status: 1,
    sort_field: resolvePreviewSortField(data.sort_by),
    sort_type: data.sort_order || 'desc'
  }
  if (data.content_type && data.content_type !== 'all') {
    baseParams.content_type = data.content_type
  }

  return getArticleList({
    ...baseParams,
    article_category_id: tab.id
  }).then(res => {
    const items = extractResponseList(res)
    return items.map(item => ({
      ...item,
      image: item.cover || item.image || ''
    })).slice(0, Number(data.show_count || 4))
  }).catch(() => [])
}

function resolvePreviewSortField(sortBy) {
  return ['view_count', 'price', 'id'].indexOf(sortBy) > -1 ? sortBy : 'create_time'
}

function fetchPreviewData(data) {
  if (data.data_source === 'manual') {
    const manualIds = data.manual_ids || []
    list.value = manualIds.map(item =>
      typeof item === 'object'
        ? { ...item, image: item.cover || item.image || '' }
        : { id: item, title: '内容 #' + item, image: '' }
    )
    return
  }

  const params = {
    page: 1,
    limit: data.show_count || 6,
    sort_field: data.sort_by || 'create_time',
    sort_type: data.sort_order || 'desc'
  }
  if (data.content_type && data.content_type !== 'all') {
    params.content_type = data.content_type
  }
  if (data.data_source === 'category' && data.category_id) {
    params.article_category_id = data.category_id
  }
  getArticleList(params).then(res => {
    const resList = (res.data && (res.data.list || res.data)) || []
    list.value = Array.isArray(resList) ? resList.map(item => ({
      ...item,
      image: item.cover || item.image || ''
    })) : []
  }).catch(() => { list.value = [] })
}

function normalizeItem(item, index) {
  const type = item.content_type !== undefined && item.content_type !== null && item.content_type !== ''
    ? item.content_type
    : (item.type || 'article')
  const priceValue = Number(item.price || item.money || item.pay_price || 0)
  const originalPriceValue = Number(item.original_price || item.market_price || 0)
  const memberPriceValue = Number(item.member_price || item.vip_price || 0)
  const savedPriceValue = originalPriceValue > priceValue && priceValue > 0 ? originalPriceValue - priceValue : 0
  const articleCategory = item.articleCategory && item.articleCategory.name ? item.articleCategory.name : ''
  const views = Number(item.view_count || item.views || item.learn_count || 0)
  const hotValue = Number(item.is_hot || item.hot || 0)
  return {
    ...item,
    renderKey: `${item.id || item.content_id || item.title || 'content'}_${index}`,
    title: item.title || item.name || '内容标题',
    description: item.description || item.desc || item.intro || '',
    image: item.image || item.cover || '',
    categoryLabel: item.category_label || item.category_name || articleCategory || '内容',
    typeLabel: item.content_type_text || item.type_text || CONTENT_TYPE_LABEL_MAP[type] || '内容',
    typeIcon: CONTENT_TYPE_ICON_MAP[type] || 'el-icon-document',
    priceText: item.price_text || (priceValue > 0 ? `¥${priceValue.toFixed(2)}` : '免费'),
    originalPriceText: originalPriceValue > priceValue && priceValue > 0 ? `¥${originalPriceValue.toFixed(2)}` : '',
    memberPriceText: memberPriceValue > 0 ? `¥${memberPriceValue.toFixed(2)}` : '',
    savedPriceText: savedPriceValue > 0 ? `已省 ¥${savedPriceValue.toFixed(2)}` : '',
    tags: parseTags(item),
    views,
    statText: item.stat_text || (views > 0 ? `${formatCount(views)} 浏览` : '0 浏览'),
    isVideo: type === 6 || type === 'video',
    isHot: hotValue === 1,
    cornerText: hotValue === 1 ? '热销' : ''
  }
}

function parseTags(item = {}) {
  const realTags = normalizePreviewTags(item.tag_list)
  if (realTags.length) {
    return realTags
  }

  const tagList = normalizePreviewTags(item.tags)
  if (tagList.length) {
    return tagList
  }

  const keywordList = normalizePreviewTags(item.keyword_list)
  if (keywordList.length) {
    return keywordList
  }

  return normalizePreviewTags(item.keywords || item.tags)
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
    .slice(0, 3)
}

function buildPreviewTag(tag, index = 0) {
  const source = tag && typeof tag === 'object' ? tag : { name: tag }
  const palette = [
    { text_color: '#FFFFFF', bg_color: CONTENT_THEME_PRIMARY, border_color: CONTENT_THEME_PRIMARY },
    { text_color: '#FFFFFF', bg_color: CONTENT_THEME_SECONDARY, border_color: CONTENT_THEME_SECONDARY },
    { text_color: '#FFFFFF', bg_color: '#333333', border_color: '#333333' }
  ][index % 3]
  const name = String(source.name || source.label || source.title || source.text || '').trim()
  const icon = String(source.icon || '').trim()
  if (!name && !icon) {
    return null
  }
  return {
    ...source,
    id: Number(source.id || source.value || index + 1),
    name,
    label: String(source.label || name).trim(),
    effect_type: Number(source.effect_type || source.effectType || (icon ? 2 : 1)) === 2 ? 2 : 1,
    text_color: source.text_color || source.textColor || palette.text_color,
    bg_color: source.bg_color || source.bgColor || palette.bg_color,
    border_color: source.border_color || source.borderColor || palette.border_color,
    icon,
    renderKey: String(source.renderKey || source.render_key || source.id || source.value || `${name || icon}-${index}`)
  }
}

function formatCount(num) {
  const count = Number(num || 0)
  if (count >= 10000) {
    return (count / 10000).toFixed(1) + 'w'
  }
  return count
}
</script>

<style scoped lang="scss">
.content-area {
  --content-theme-primary: var(--brand-primary, var(--theme-primary, var(--prev-color-primary, #ef3333)));
  --content-theme-secondary: var(--brand-primary-light, var(--theme-secondary, var(--dashboard-accent-light, #ff8a5c)));
  --content-theme-pale: var(--brand-primary-pale, var(--prev-color-primary-light-9, #fff8f8));
  --content-theme-shadow: var(--brand-shadow, var(--dashboard-accent-shadow, rgba(239, 51, 51, 0.16)));
  width: auto;
  padding-bottom: 4px;
  box-sizing: border-box;
}

.diy-section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 10px 9px;
}

.diy-section-title {
  flex: 1;
  min-width: 0;
  color: #1a1c1c;
  font-size: 16px;
  font-weight: 800;
  line-height: 22px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.diy-section-more {
  flex-shrink: 0;
  margin-left: 10px;
  color: #848484;
  font-size: 12px;
  line-height: 18px;
  cursor: pointer;
}

.diy-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 120px;
  color: #c0c4cc;
  font-size: 12px;

  i {
    margin-bottom: 8px;
    font-size: 28px;
  }
}

.content-category-tabs {
  padding-top: 2px;
  background: #ffffff;
}

.category-tabs-scroll {
  display: flex;
  align-items: stretch;
  height: 44px;
  padding: 0 12px;
  border-bottom: 1px solid #eeeeef;
  white-space: nowrap;
  box-sizing: border-box;
}

.category-tab {
  position: relative;
  flex-shrink: 0;
  width: 78px;
  height: 44px;
  margin-right: 6px;
  padding: 0;
  border: 0;
  border-radius: 0;
  background: transparent;
  color: #303236;
  font-size: 16px;
  font-weight: 700;
  line-height: 22px;
  cursor: pointer;
  outline: none;
}

.category-tab__text {
  display: block;
  max-width: 68px;
  margin: 0 auto;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.category-tab--active {
  color: var(--content-theme-primary);
  font-weight: 800;
}

.category-tab__line {
  position: absolute;
  left: 6px;
  right: 6px;
  bottom: 0;
  height: 3px;
  border-radius: 999px;
  background: var(--content-theme-primary);
}

.content-display-list {
  padding-left: 10px;
  padding-right: 10px;
  box-sizing: border-box;
}

.content-display-list--tabs {
  padding-top: 1px;
}

.content-list-card {
  display: flex;
  min-height: 146px;
  margin-bottom: 11px;
  padding: 9px;
  border-radius: 11px;
  background: #ffffff;
  box-shadow: 0 5px 15px rgba(15, 23, 42, 0.06);
  box-sizing: border-box;
}

.content-list-card:last-child {
  margin-bottom: 0;
}

.content-list-card__cover {
  position: relative;
  flex-shrink: 0;
  width: 112px;
  height: 128px;
  overflow: hidden;
  border-radius: 9px;
  background: #eeeeef;
}

.content-list-card__image,
.content-grid-card__image {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.content-cover-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  color: #90949d;
  font-size: 12px;
  font-weight: 700;
  background: #f2f2f3;
}

.content-list-card__play,
.content-grid-card__play {
  position: absolute;
  right: 9px;
  bottom: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border: 2px solid rgba(255, 255, 255, 0.88);
  border-radius: 50%;
  color: #ffffff;
  background: rgba(0, 0, 0, 0.28);
  box-sizing: border-box;
}

.content-list-card__body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  padding: 3px 0 0 14px;
  box-sizing: border-box;
}

.content-list-card__body--full {
  padding-left: 0;
}

.content-list-card__title {
  height: 22px;
  color: #08090a;
  font-size: 17px;
  font-weight: 800;
  line-height: 22px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.content-list-card__desc {
  height: 18px;
  margin-top: 6px;
  color: #8a8d93;
  font-size: 12px;
  line-height: 18px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.content-list-card__price-row {
  display: flex;
  align-items: center;
  min-height: 26px;
  margin-top: 14px;
}

.content-list-card__price {
  flex-shrink: 0;
  color: var(--content-theme-primary);
  font-size: 21px;
  font-weight: 900;
  line-height: 26px;
}

.content-list-card__vip,
.content-grid-card__vip {
  flex-shrink: 0;
  height: 17px;
  margin-left: 7px;
  padding: 0 5px;
  border-radius: 3px;
  color: #ffffff;
  font-size: 10px;
  font-weight: 800;
  line-height: 17px;
  background: linear-gradient(90deg, #f4c268 0%, #e0a536 100%);
}

.content-list-card__vip-price {
  flex-shrink: 0;
  margin-left: 5px;
  color: #e5aa3c;
  font-size: 16px;
  font-weight: 800;
  line-height: 21px;
}

.content-list-card__tags,
.content-grid-card__tags {
  display: flex;
  align-items: center;
  min-height: 20px;
  margin-top: 9px;
  overflow: hidden;
}

.content-card-tag-preview {
  margin-right: 6px;
  margin-bottom: 4px;
  font-size: 10px;
}

.content-list-card__foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 33px;
  margin-top: auto;
}

.content-list-card__type {
  flex: 1;
  min-width: 0;
  display: inline-flex;
  align-items: center;
  color: #8a8d93;
  font-size: 13px;
  line-height: 18px;

  i {
    margin-right: 6px;
    color: #ef5555;
    font-size: 15px;
  }
}

.content-display-card {
  display: flex;
  flex-wrap: wrap;
  margin-left: -5px;
  margin-right: -5px;
  margin-bottom: -11px;
  padding-left: 10px;
  padding-right: 10px;
  box-sizing: border-box;
}

.content-grid-item {
  width: 50%;
  padding-left: 5px;
  padding-right: 5px;
  padding-bottom: 11px;
  box-sizing: border-box;
}

.content-grid-card {
  min-height: 248px;
  overflow: hidden;
  border-radius: 10px;
  background: #ffffff;
  box-shadow: 0 5px 15px rgba(15, 23, 42, 0.07);
}

.content-grid-card__cover {
  position: relative;
  width: 100%;
  height: 130px;
  overflow: hidden;
  background: #eeeeef;
}

.content-grid-card__badge,
.content-grid-card__corner {
  position: absolute;
  top: 7px;
  height: 21px;
  padding: 0 7px;
  border-radius: 5px;
  color: #ffffff;
  font-size: 11px;
  font-weight: 800;
  line-height: 21px;
}

.content-grid-card__badge {
  left: 7px;
  background: linear-gradient(135deg, #8b5cf6 0%, #6d5dfc 100%);
}

.content-grid-card__corner {
  right: 7px;
  background: #ff4f40;
}

.content-grid-card__body {
  position: relative;
  padding: 9px 9px 10px;
  box-sizing: border-box;
}

.content-grid-card__price-row {
  display: flex;
  align-items: center;
  min-height: 27px;
  padding-right: 29px;
}

.content-grid-card__original {
  flex-shrink: 1;
  min-width: 0;
  color: #9ca0a8;
  font-size: 11px;
  line-height: 15px;
  text-decoration: line-through;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.content-grid-card__price {
  flex-shrink: 0;
  margin-left: 4px;
  color: var(--content-theme-primary);
  font-size: 17px;
  font-weight: 900;
  line-height: 23px;
}

.content-grid-card__title {
  height: 36px;
  margin-top: 4px;
  color: #08090a;
  font-size: 14px;
  font-weight: 800;
  line-height: 18px;
  overflow: hidden;
}

.content-grid-card__desc {
  height: 17px;
  margin-top: 4px;
  color: #8a8d93;
  font-size: 11px;
  line-height: 17px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.content-grid-card__tags {
  min-height: 17px;
  margin-top: 7px;
}

.content-grid-card__stat {
  height: 15px;
  margin-top: 6px;
  color: #8a8d93;
  font-size: 11px;
  line-height: 15px;
  text-align: right;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.content-cart-btn {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  min-width: 34px;
  height: 34px;
  border-radius: 50%;
  box-sizing: border-box;

  i {
    font-size: 16px;
    line-height: 1;
  }
}

.content-cart-btn--outline {
  margin-left: 7px;
  border: 1px solid var(--content-theme-secondary);
  color: var(--content-theme-primary);
  background: var(--content-theme-pale);
  box-shadow: 0 4px 10px var(--content-theme-shadow);
}

.content-cart-btn--grid {
  position: absolute;
  right: 9px;
  top: 9px;
  width: 29px;
  min-width: 29px;
  height: 29px;
  color: #ffffff;
  background: var(--content-theme-primary);
  box-shadow: 0 5px 12px var(--content-theme-shadow);
}

.content-display-card {
  margin-left: -3px;
  margin-right: -3px;
  margin-bottom: -12px;
}

.content-grid-item {
  padding-left: 3px;
  padding-right: 3px;
  padding-bottom: 12px;
}

.content-grid-card {
  min-height: 0;
  padding: 6px;
  overflow: visible;
  border-radius: 16px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
}

.content-grid-card__cover {
  height: 90px;
  border-radius: 14px;
}

.content-grid-card__body {
  padding: 9px 2px 2px;
}

.content-grid-card__title {
  height: 21px;
  margin-top: 0;
  color: #333333;
  font-size: 15px;
  line-height: 21px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.content-grid-card__desc {
  display: -webkit-box;
  height: 34px;
  margin-top: 6px;
  color: #999999;
  font-size: 12px;
  line-height: 17px;
  white-space: normal;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.content-grid-card__tags {
  min-height: 18px;
  margin-top: 7px;
}

.content-grid-card__price-row {
  align-items: baseline;
  min-height: 22px;
  margin-top: 9px;
  padding-right: 0;
}

.content-grid-card__price {
  margin-left: 0;
  color: var(--content-theme-primary);
  font-size: 18px;
  line-height: 22px;
}

.content-grid-card__original {
  margin-left: 6px;
  color: #999999;
  font-size: 11px;
}

.content-grid-card__deal {
  display: flex;
  align-items: center;
  min-height: 20px;
  margin-top: 6px;
  overflow: hidden;
}

.content-grid-card__vip-price,
.content-grid-card__saved {
  flex-shrink: 0;
  max-width: 66px;
  height: 20px;
  padding: 0 6px;
  border-radius: 4px;
  font-size: 11px;
  line-height: 20px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.content-grid-card__vip-price {
  color: var(--content-theme-primary);
  background: var(--content-theme-pale);
}

.content-grid-card__saved {
  margin-left: 4px;
  color: #ff3b30;
  background: rgba(255, 59, 48, 0.1);
}

.content-grid-card__stat-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 9px;
}

.content-grid-card__stat {
  display: inline-flex;
  align-items: center;
  min-width: 0;
  height: 14px;
  margin-top: 0;
  color: #999999;
  font-size: 10px;
  line-height: 14px;
  text-align: left;
}

.content-grid-card__stat i {
  flex-shrink: 0;
  margin-right: 3px;
  font-size: 13px;
}

.content-cart-btn--cover {
  position: absolute;
  top: 6px;
  right: 6px;
  width: 28px;
  min-width: 28px;
  height: 28px;
  color: var(--content-theme-primary);
  background: #ffffff;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.12);
}

.content-cart-btn--cover i {
  font-size: 16px;
}

.content-cart-btn--outline {
  border-color: var(--content-theme-secondary);
  color: var(--content-theme-primary);
  background: var(--content-theme-pale);
  box-shadow: 0 4px 10px var(--content-theme-shadow);
}

.load-more-tip {
  padding: 12px 0;
  color: #999999;
  font-size: 12px;
  text-align: center;
}
</style>
