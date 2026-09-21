<template>
  <common-wrapper :config="config">
    <div class="special-area" :style="{ background: bgColor }">
      <div v-if="!isCategoryTabsStyle && (title || showMore)" class="diy-section-header">
        <span v-if="title" class="diy-section-title">{{ title }}</span>
        <span v-if="showMore" class="diy-section-more">
          {{ moreText }} <LegacyIcon name="el-icon-arrow-right" />
        </span>
      </div>

      <div v-if="isCategoryTabsStyle" class="category-tabs-preview">
        <div v-if="categoryTabs.length > 0" class="category-tabs-scroll">
          <button
            v-for="item in categoryTabs"
            :key="item.id"
            type="button"
            class="category-tab"
            :class="{ 'category-tab--active': item.id === activeCategoryId }"
            @click="handleCategoryTabClick(item.id)"
          >
            <span class="category-tab__cover">
              <img v-if="getCategoryTabImage(item)" :src="getCategoryTabImage(item)" :alt="item.name">
              <LegacyIcon v-else name="el-icon-picture-outline" />
            </span>
            <span class="category-tab__name">{{ item.name }}</span>
            <span class="category-tab__line" />
          </button>
        </div>

        <div v-if="activeCategoryLoading" class="diy-empty">
          <LegacyIcon name="el-icon-loading" />
          <span>加载中...</span>
        </div>

        <div v-else-if="categorySpecialList.length === 0" class="diy-empty">
          <LegacyIcon name="el-icon-collection" />
          <span>暂无专题数据</span>
        </div>

        <div v-else class="category-card-list">
          <div
            v-for="item in categorySpecialList"
            :key="item.renderKey"
            class="category-card"
            @click.prevent
          >
            <div class="category-card-cover" :style="imageRadiusStyle">
              <img v-if="item.image" :src="item.image" :alt="item.title">
              <div v-else class="special-cover-placeholder">
                <LegacyIcon name="el-icon-picture-outline" />
              </div>
              <span v-if="item.mediaTag" class="category-card-media">{{ item.mediaTag }}</span>
              <span v-if="item.isVideo" class="category-card-play" />
            </div>
            <div class="category-card-body">
              <div class="category-card-title">{{ item.title || '专题标题' }}</div>
              <div v-if="showDesc && item.subtitle" class="category-card-desc">{{ item.subtitle }}</div>
              <div v-if="showCountInfo" class="category-card-sales">{{ item.saleText }}</div>
              <div v-if="item.keywordList.length" class="category-card-tags">
                <tag-preview
                  v-for="tag in item.keywordList"
                  :key="item.renderKey + '-' + tag.renderKey"
                  :tag="tag"
                />
              </div>
              <div class="category-card-footer">
                <div class="category-price">
                  <div class="category-price__main">{{ item.priceText }}</div>
                  <div v-if="item.originalPriceText" class="category-price__vip">
                    <span class="category-price__vip-icon">VIP</span>
                    <span>{{ item.originalPriceText }}</span>
                  </div>
                </div>
                <button type="button" class="category-card-cart" @click.prevent>
                  <LegacyIcon name="el-icon-shopping-cart-2" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-else-if="specialList.length === 0" class="diy-empty">
        <LegacyIcon name="el-icon-collection" />
        <span>暂无专题数据</span>
      </div>

      <div v-else-if="style === 'card'" class="special-card-grid">
        <div
          v-for="(item, index) in specialList"
          :key="item.renderKey || index"
          class="special-grid-card"
          @click.prevent
        >
          <div v-if="item.image" class="special-grid-cover" :style="imageRadiusStyle">
            <img :src="item.image" :alt="item.title">
          </div>
          <div v-else class="special-grid-cover special-cover-placeholder" :style="imageRadiusStyle">
            <LegacyIcon name="el-icon-picture-outline" />
          </div>
          <div class="special-grid-body">
            <div v-if="item.keywordList.length" class="special-keyword-row">
              <tag-preview
                v-for="tag in item.keywordList"
                :key="item.renderKey + '-' + tag.renderKey"
                :tag="tag"
              />
            </div>
            <div class="special-card-title">{{ item.title || '专题标题' }}</div>
            <div v-if="showDesc && item.subtitle" class="special-card-desc">{{ item.subtitle }}</div>
            <div v-if="showCountInfo" class="special-card-footer">
              <span>{{ item.countText }}</span>
              <span v-if="item.priceText">{{ item.priceText }}</span>
            </div>
          </div>
        </div>
      </div>

      <div v-else-if="style === 'scroll'" class="special-scroll">
        <div
          v-for="(item, index) in specialList"
          :key="item.renderKey || index"
          class="special-row-card"
          @click.prevent
        >
          <div v-if="item.image" class="special-row-cover" :style="imageRadiusStyle">
            <img :src="item.image" :alt="item.title">
          </div>
          <div v-else class="special-row-cover special-cover-placeholder" :style="imageRadiusStyle">
            <LegacyIcon name="el-icon-picture-outline" />
          </div>
          <div class="special-row-body">
            <div class="special-card-title">{{ item.title || '专题标题' }}</div>
            <div v-if="showDesc && item.subtitle" class="special-card-desc">{{ item.subtitle }}</div>
            <div class="special-row-footer">
              <span v-if="showCountInfo">{{ item.countText }}</span>
              <span v-if="item.priceText" class="special-price">{{ item.priceText }}</span>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="special-hero-list">
        <div
          v-for="(item, index) in specialList"
          :key="item.renderKey || index"
          class="special-hero-card"
          @click.prevent
        >
          <div v-if="item.image" class="special-hero-cover" :style="imageRadiusStyle">
            <img :src="item.image" :alt="item.title">
          </div>
          <div v-else class="special-hero-cover special-cover-placeholder" :style="imageRadiusStyle">
            <LegacyIcon name="el-icon-picture-outline" />
          </div>
          <div class="special-hero-mask" />
          <div v-if="showKeywords && item.keywordList.length" class="special-hot-tag">
            <tag-preview :tag="item.keywordList[0]" />
          </div>
          <div class="special-hero-info">
            <div class="special-hero-title">{{ item.title || '专题标题' }}</div>
            <div v-if="showDesc && item.subtitle" class="special-hero-desc">{{ item.subtitle }}</div>
          </div>
          <div class="special-action-bar">
            <span v-if="item.priceText" class="special-action-price">{{ item.priceText }}</span>
            <span v-else-if="showCountInfo" class="special-action-price">{{ item.countText }}</span>
            <span class="special-action-btn">立即查看</span>
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
import TagPreview from '@/components/TagPreview'
import { getSpecialList } from '@/api/special'
import { getImageRadiusStyle } from './imageRadius'

const SPECIAL_THEME_PRIMARY = 'var(--brand-primary, var(--theme-primary, var(--prev-color-primary, #FF3B30)))'
const SPECIAL_THEME_SECONDARY = 'var(--brand-primary-light, var(--theme-secondary, var(--dashboard-accent-light, #FF6A00)))'

defineOptions({
  name: 'Special'
})

const props = defineProps({
  config: {
    type: Object,
    default: null
  }
})

const title = ref('专题')
const list = ref([])
const showMore = ref(true)
const showDesc = ref(true)
const showKeywords = ref(false)
const showCountInfo = ref(true)
const loadMore = ref(false)
const style = ref('scroll')
const bgColor = ref('#ffffff')
const moreText = ref('查看全部')
const categoryTabs = ref([])
const activeCategoryId = ref(0)
const categoryTabsConfig = ref(null)
const lastDataKey = ref(null)
const lastCategoryTabsKey = ref(null)

const isCategoryTabsStyle = computed(() => style.value === 'category_tabs')
const specialList = computed(() => list.value.map((item, index) => mapItem(item, index)))
const activeCategoryItems = computed(() => {
  const activeTab = categoryTabs.value.find(item => item.id === activeCategoryId.value)
  return activeTab && Array.isArray(activeTab.items) ? activeTab.items : []
})
const activeCategoryLoading = computed(() => {
  const activeTab = categoryTabs.value.find(item => item.id === activeCategoryId.value)
  return activeTab ? activeTab.loading === true : false
})
const categorySpecialList = computed(() => activeCategoryItems.value.map((item, index) => mapItem(item, index)))
const imageRadiusStyle = computed(() => getImageRadiusStyle(props.config && props.config.style_config))

watch(() => props.config, (nVal) => {
  if (!nVal) return
  title.value = nVal.title || '专题'
  showMore.value = nVal.show_more !== undefined ? nVal.show_more : true
  showDesc.value = nVal.show_desc !== undefined ? nVal.show_desc : true
  showKeywords.value = nVal.show_keywords === true
  showCountInfo.value = nVal.show_count_info !== false
  loadMore.value = nVal.load_more === true
  style.value = normalizeStyle(nVal.display_style || nVal.style)
  bgColor.value = nVal.bg_color || '#ffffff'
  moreText.value = nVal.more_text || '查看全部'
  if (isCategoryTabsStyle.value) {
    showMore.value = false
    loadMore.value = false
    checkAndFetchCategoryTabs(nVal)
    return
  }
  categoryTabs.value = []
  activeCategoryId.value = 0
  categoryTabsConfig.value = null
  checkAndFetch(nVal)
}, {
  deep: true,
  immediate: true
})

function normalizeStyle(value) {
  if (value === 'category_tabs') return 'category_tabs'
  if (value === 'card') return 'card'
  if (value === 'row') return 'row'
  return 'scroll'
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
        name: item.name || item.label || ('分类 #' + (item.id || item.value)),
        image: item.image || ''
      }
    }
    return {
      id: Number(item || 0),
      name: '分类 #' + item,
      image: ''
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
    lastCategoryTabsKey.value = null
    return
  }

  const key = JSON.stringify({
    ids: preparedTabs.map(item => item.id),
    show_count: data.show_count || 4
  })
  const shouldReset = key !== lastCategoryTabsKey.value
  if (shouldReset) {
    preparedTabs = tabs.map(item => ({
      ...item,
      items: [],
      loaded: false,
      loading: false
    }))
  }
  lastCategoryTabsKey.value = key
  categoryTabsConfig.value = data
  categoryTabs.value = preparedTabs

  loadCategoryPreviewById(activeCategoryId.value, data, key)
}

function handleCategoryTabClick(categoryId) {
  activeCategoryId.value = Number(categoryId || 0)
  loadCategoryPreviewById(activeCategoryId.value, categoryTabsConfig.value, lastCategoryTabsKey.value)
}

function loadCategoryPreviewById(categoryId, data, key) {
  if (!data || !key) return
  const id = Number(categoryId || 0)
  const activeTab = categoryTabs.value.find(item => item.id === id)
  if (!activeTab || activeTab.loaded === true || activeTab.loading === true) return

  updateCategoryTabState(id, { loading: true })
  fetchCategoryPreviewItems(activeTab, data).then(items => {
    if (lastCategoryTabsKey.value !== key) return
    updateCategoryTabState(id, {
      items,
      loaded: true,
      loading: false
    })
  }).catch(() => {
    if (lastCategoryTabsKey.value !== key) return
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
  return getSpecialList({
    page: 1,
    limit: data.show_count || 4,
    status: 1,
    category_id: tab.id
  }).then(res => {
    return extractResponseList(res).slice(0, Number(data.show_count || 4))
  }).catch(() => [])
}

function getCategoryTabImage(tab) {
  if (tab.image) {
    return tab.image
  }
  const items = Array.isArray(tab.items) ? tab.items : []
  const first = items.find(item => item && (item.cover || item.image))
  return first ? (first.cover || first.image || '') : ''
}

function getDataKey(data) {
  const manualIds = data.manual_ids || []
  const stableIds = [...manualIds]
    .map(i => (typeof i === 'object' ? i.id : i))
    .sort()
    .join(',')
  return [data.data_source, data.category_id, data.show_count, stableIds].join('|')
}

function checkAndFetch(data) {
  if (data.list && data.list.length > 0) {
    list.value = data.list
    return
  }
  if (data.items && data.items.length > 0) {
    list.value = data.items
    return
  }

  const key = getDataKey(data)
  if (key === lastDataKey.value) return
  lastDataKey.value = key
  fetchPreviewData(data)
}

function fetchPreviewData(data) {
  if (data.data_source === 'manual') {
    const manualIds = data.manual_ids || []
    list.value = manualIds.map(item =>
      typeof item === 'object'
        ? item
        : { id: item, title: '专题 #' + item, image: '', subtitle: '', keywords: '' }
    )
    return
  }

  const params = {
    page: 1,
    limit: data.show_count || 6,
    status: 1
  }
  if (data.data_source === 'auto') {
    params.is_hot = 1
  }
  if (data.data_source === 'category' && data.category_id) {
    params.category_id = data.category_id
  }
  getSpecialList(params).then(res => {
    const items = (res.data && (res.data.list || res.data)) || []
    list.value = Array.isArray(items) ? items : []
  }).catch(() => {
    list.value = []
  })
}

function mapItem(item, index) {
  const keywordList = parseTags(item)
  const price = Number(item.price || item.money || item.pay_price || 0)
  const originalPrice = Number(item.original_price || item.market_price || 0)
  const contentCount = Number(item.content_count || item.course_count || item.article_count || 0)
  const buyCount = Number(item.buy_count || item.sales || item.sale_count || 0)
  const contentType = String(item.content_type || item.media_type || '').toLowerCase()
  const mediaTag = item.media_tag || item.content_type_text || item.media_type_text || ''
  const isVideo = ['2', '3', 'video', 'audio'].includes(contentType)
  return {
    ...item,
    renderKey: `${item.id || item.special_id || item.title || 'special'}_${index}`,
    title: item.title || item.name || '',
    image: item.cover || item.image || '',
    subtitle: item.description || item.desc || item.subtitle || '',
    keywordList,
    countText: contentCount > 0 ? contentCount + ' 课' : '专题',
    saleText: buyCount > 0 ? `已售 ${formatWanCount(buyCount)}` : (contentCount > 0 ? `${contentCount} 节内容` : '精选专题'),
    priceText: price > 0 ? `¥${formatPrice(price)}` : '免费',
    originalPriceText: originalPrice > price && price > 0 ? `¥${formatPrice(originalPrice)}` : '',
    mediaTag,
    isVideo
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

  const legacyKeywordList = normalizePreviewTags(item.keywordList)
  if (legacyKeywordList.length) {
    return legacyKeywordList
  }

  return normalizePreviewTags(item.keywords || item.keyword || item.tags)
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
    { text_color: '#FFFFFF', bg_color: SPECIAL_THEME_PRIMARY, border_color: SPECIAL_THEME_PRIMARY },
    { text_color: '#FFFFFF', bg_color: SPECIAL_THEME_SECONDARY, border_color: SPECIAL_THEME_SECONDARY },
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

function formatPrice(value) {
  const price = Number(value || 0)
  if (!price) return '0'
  return price % 1 === 0 ? String(price.toFixed(0)) : price.toFixed(2)
}

function formatWanCount(value) {
  const count = Number(value || 0)
  if (count >= 10000) {
    const wan = count / 10000
    return `${wan % 1 === 0 ? wan.toFixed(0) : wan.toFixed(1)}万+`
  }
  return count > 0 ? String(count) : '0'
}
</script>

<style scoped lang="scss">
.special-area {
  --special-theme-primary: var(--brand-primary, var(--theme-primary, var(--prev-color-primary, #ff4500)));
  --special-theme-secondary: var(--brand-primary-light, var(--theme-secondary, var(--dashboard-accent-light, #ff7a2f)));
  --special-theme-pale: var(--brand-primary-pale, var(--prev-color-primary-light-9, #fffaf5));
  --special-theme-shadow: var(--brand-shadow, var(--dashboard-accent-shadow, rgba(255, 100, 32, 0.12)));
  width: auto;
  padding-bottom: 4px;
}

.diy-section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 9px 11px 10px;
}

.diy-section-title {
  flex: 1;
  min-width: 0;
  color: #1a1c1c;
  font-size: 16px;
  font-weight: 700;
  line-height: 24px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.diy-section-more {
  flex-shrink: 0;
  margin-left: 12px;
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
  padding: 24px 0;
  color: #c0c4cc;
  font-size: 12px;

  i {
    margin-bottom: 8px;
    font-size: 28px;
  }
}

.special-card-grid {
  display: flex;
  flex-wrap: wrap;
  margin-left: -6px;
  margin-right: -6px;
  margin-bottom: -12px;
}

.category-tabs-preview {
  padding-top: 14px;
  background: #fffdf9;
}

.category-tabs-scroll {
  display: flex;
  align-items: center;
  padding: 0 14px 16px;
  overflow-x: auto;

  &::-webkit-scrollbar {
    display: none;
  }
}

.category-tab {
  flex-shrink: 0;
  width: 66px;
  height: 92px;
  margin-right: 8px;
  padding: 8px 6px 7px;
  border: 1px solid #f0e6dc;
  border-radius: 14px;
  background: #ffffff;
  color: #2b2b2b;
  font-size: 12px;
  font-weight: 700;
  line-height: 17px;
  cursor: pointer;
  box-shadow: 0 5px 14px rgba(87, 61, 40, 0.05);
}

.category-tab--active {
  border-color: var(--special-theme-secondary);
  background: var(--special-theme-pale);
  color: var(--special-theme-primary);
  box-shadow: 0 6px 16px var(--special-theme-shadow);
}

.category-tab__cover {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 48px;
  margin: 0 auto 8px;
  overflow: hidden;
  border-radius: 13px;
  background: #f5efe8;
  color: #c8b8a8;

  img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
}

.category-tab__name {
  display: block;
  height: 17px;
  overflow: hidden;
  text-align: center;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.category-tab__line {
  display: block;
  width: 14px;
  height: 3px;
  margin: 6px auto 0;
  border-radius: 3px;
  background: transparent;
}

.category-tab--active .category-tab__line {
  background: #ff5a1f;
}

.category-card-list {
  display: flex;
  flex-wrap: wrap;
  padding: 0 14px 16px;
  margin-left: -5px;
  margin-right: -5px;
  margin-bottom: -10px;
}

.category-card {
  width: calc(50% - 10px);
  margin: 0 5px 10px;
  overflow: hidden;
  border-radius: 14px;
  background: #ffffff;
  box-shadow: 0 8px 22px rgba(38, 20, 8, 0.08);
  box-sizing: border-box;
}

.category-card-cover {
  position: relative;
  width: 100%;
  height: 108px;
  overflow: hidden;
  background: #f3eee8;

  img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
}

.category-card-body {
  padding: 9px 9px 10px;
  box-sizing: border-box;
}

.category-card-title {
  height: 20px;
  color: #1f1f1f;
  font-size: 14px;
  font-weight: 800;
  line-height: 20px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.category-card-desc {
  display: -webkit-box;
  height: 34px;
  margin-top: 4px;
  color: #55504c;
  font-size: 11px;
  line-height: 17px;
  overflow: hidden;
  text-overflow: ellipsis;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.category-card-sales {
  margin-top: 6px;
  color: #766f68;
  font-size: 10px;
  line-height: 14px;
}

.category-card-tags {
  display: flex;
  flex-wrap: nowrap;
  height: 18px;
  margin-top: 6px;
  overflow: hidden;
}

.category-card-tags :deep(.tag-preview) {
  flex-shrink: 0;
  margin-right: 5px;
}

.category-card-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 8px;
}

.category-price {
  min-width: 0;
}

.category-price__main {
  color: var(--special-theme-primary);
  font-size: 15px;
  font-weight: 800;
  line-height: 19px;
}

.category-price__vip {
  display: flex;
  align-items: center;
  margin-top: 3px;
  color: #7a5532;
  font-size: 10px;
  line-height: 14px;
}

.category-price__vip-icon {
  height: 13px;
  margin-right: 3px;
  padding: 0 3px;
  border-radius: 7px;
  background: #18130f;
  color: #f8d778;
  font-size: 8px;
  font-weight: 800;
  line-height: 13px;
}

.category-card-cart {
  flex-shrink: 0;
  width: 32px;
  height: 32px;
  padding: 0;
  border: 1px solid #efe4db;
  border-radius: 50%;
  background: #ffffff;
  color: #111111;
  font-size: 18px;
  line-height: 30px;
  text-align: center;
  box-shadow: 0 5px 14px rgba(37, 22, 9, 0.06);
  cursor: pointer;
}

.category-card-media {
  position: absolute;
  left: 7px;
  top: 7px;
  height: 20px;
  padding: 0 7px;
  border-radius: 10px;
  background: rgba(37, 37, 37, 0.78);
  color: #ffffff;
  font-size: 10px;
  line-height: 20px;
}

.category-card-play {
  position: absolute;
  left: 50%;
  top: 50%;
  width: 34px;
  height: 34px;
  margin-left: -17px;
  margin-top: -17px;
  border: 3px solid #ffffff;
  border-radius: 50%;
  box-sizing: border-box;
}

.category-card-play::after {
  position: absolute;
  left: 12px;
  top: 8px;
  width: 0;
  height: 0;
  border-top: 6px solid transparent;
  border-bottom: 6px solid transparent;
  border-left: 9px solid #ffffff;
  content: '';
}

.special-grid-card {
  width: calc(50% - 12px);
  margin: 0 6px 12px;
  overflow: hidden;
  border-radius: 16px;
  background: #ffffff;
  box-shadow: 0 4px 18px rgba(0, 0, 0, 0.05);
}

.special-grid-cover {
  width: 100%;
  height: 142px;
  overflow: hidden;
  background: #f3f3f3;

  img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
}

.special-grid-body {
  padding: 10px;
  box-sizing: border-box;
}

.special-keyword-row {
  display: flex;
  flex-wrap: wrap;
  margin-bottom: 6px;
}

.special-keyword-row :deep(.tag-preview) {
  margin-right: 5px;
  margin-bottom: 5px;
}

.special-card-title {
  color: #2c2c2c;
  font-size: 14px;
  font-weight: 700;
  line-height: 20px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.special-card-desc,
.special-hero-desc {
  display: -webkit-box;
  height: 36px;
  margin-top: 6px;
  color: #4c4546;
  font-size: 12px;
  line-height: 18px;
  overflow: hidden;
  text-overflow: ellipsis;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.special-card-footer,
.special-row-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 8px;
  color: #848484;
  font-size: 10px;
  line-height: 14px;
}

.special-price,
.special-action-price {
  color: var(--special-theme-primary);
  font-size: 16px;
  font-weight: 700;
}

.special-scroll {
  display: flex;
  overflow-x: auto;
  padding-bottom: 8px;

  &::-webkit-scrollbar {
    display: none;
  }
}

.special-row-card {
  display: flex;
  flex-shrink: 0;
  width: 300px;
  margin-right: 12px;
  padding: 12px;
  border-radius: 16px;
  background: #ffffff;
  box-shadow: 0 4px 18px rgba(0, 0, 0, 0.05);
  box-sizing: border-box;
}

.special-row-card:last-child {
  margin-right: 0;
}

.special-row-cover {
  width: 96px;
  height: 96px;
  flex-shrink: 0;
  overflow: hidden;
  border-radius: 8px;
  background: #f3f3f3;

  img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
}

.special-row-body {
  flex: 1;
  min-width: 0;
  margin-left: 12px;
}

.special-hero-card {
  position: relative;
  height: 360px;
  margin-bottom: 14px;
  overflow: hidden;
  border-radius: 16px;
  background: #2f3131;
  box-shadow: 0 4px 18px rgba(0, 0, 0, 0.08);
}

.special-hero-card:last-child {
  margin-bottom: 0;
}

.special-hero-cover {
  width: 100%;
  height: 100%;

  img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
}

.special-hero-mask {
  position: absolute;
  left: 0;
  right: 0;
  top: 0;
  bottom: 0;
  background: linear-gradient(180deg, rgba(0, 0, 0, 0.04), rgba(0, 0, 0, 0.68));
}

.special-hot-tag {
  position: absolute;
  left: 8px;
  top: 8px;
  z-index: 2;
}

.special-hero-info {
  position: absolute;
  left: 9px;
  right: 9px;
  bottom: 41px;
  z-index: 2;
}

.special-hero-title {
  color: #ffffff;
  font-size: 16px;
  font-weight: 700;
  line-height: 22px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.special-hero-desc {
  display: -webkit-box;
  height: 36px;
  margin-top: 4px;
  color: rgba(255, 255, 255, 0.9);
  font-size: 11px;
  line-height: 17px;
  overflow: hidden;
  text-overflow: ellipsis;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.special-action-bar {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 32px;
  padding: 0 9px;
  background: rgba(0, 0, 0, 0.34);
  box-sizing: border-box;
}

.special-action-price {
  display: block;
  flex-shrink: 0;
  margin-right: 4px;
  color: #ffffff;
  font-size: 14px;
  font-weight: 700;
  line-height: 18px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.special-action-btn {
  flex-shrink: 0;
  height: 17px;
  padding: 0 8px;
  border-radius: 4px;
  background: var(--special-theme-primary);
  color: #ffffff;
  font-size: 11px;
  font-weight: 700;
  line-height: 17px;
}

.special-cover-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  color: #c0c4cc;
  background: #f3f3f3;
}

.load-more-tip {
  padding: 12px 0;
  color: #999999;
  font-size: 12px;
  text-align: center;
}
</style>
