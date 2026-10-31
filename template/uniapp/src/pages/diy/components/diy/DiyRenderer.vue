<template>
  <view class="diy-renderer">
    <DiySearch
      v-if="viewName === 'DiySearch'"
      :config="config"
      :data="renderData"
      :is-banner-adjacent="isBannerAdjacent"
    />
    <DiyBanner
      v-else-if="viewName === 'DiyBanner'"
      :config="config"
      :data="renderData"
      @change="handleBannerChange"
    />
    <DiyNavigation
      v-else-if="viewName === 'DiyNavigation'"
      :config="config"
      :data="renderData"
    />
    <DiyNotice
      v-else-if="viewName === 'DiyNotice'"
      :config="config"
      :data="renderData"
    />
    <DiyImageCube
      v-else-if="viewName === 'DiyImageCube'"
      :config="config"
      :data="renderData"
    />
    <DiyLive
      v-else-if="viewName === 'DiyLive'"
      :config="config"
      :data="renderData"
      :loading="componentLoading"
      :error="componentError"
      @retry="reloadComponentData"
    />
    <DiyContent
      v-else-if="viewName === 'DiyContent'"
      :config="config"
      :data="renderData"
      :loading="componentLoading"
      :error="componentError"
      @category-change="handleCategoryChange"
      @retry="reloadComponentData"
    />
    <DiySpecial
      v-else-if="viewName === 'DiySpecial'"
      :config="config"
      :data="renderData"
      :loading="componentLoading"
      :error="componentError"
      @category-change="handleCategoryChange"
      @retry="reloadComponentData"
    />
    <DiyProduct
      v-else-if="viewName === 'DiyProduct'"
      :config="config"
      :data="renderData"
      :loading="componentLoading"
      :error="componentError"
      @category-change="handleCategoryChange"
      @retry="reloadComponentData"
    />
    <DiyCoupon
      v-else-if="viewName === 'DiyCoupon'"
      :config="config"
      :data="renderData"
      :loading="componentLoading"
      :error="componentError"
      @retry="reloadComponentData"
    />
    <DiySeckill
      v-else-if="viewName === 'DiySeckill'"
      :config="config"
      :data="renderData"
      @visibility-change="handleSeckillVisibility"
    />
    <DiyHotZone
      v-else-if="viewName === 'DiyHotZone'"
      :config="config"
      :data="renderData"
    />
    <DiyTitle
      v-else-if="viewName === 'DiyTitle'"
      :config="config"
      :data="renderData"
    />
    <DiyRichText
      v-else-if="viewName === 'DiyRichText'"
      :config="config"
      :data="renderData"
    />
    <DiyBlank
      v-else
      :config="config"
      :data="renderData"
    />
  </view>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import DiySearch from './DiySearch.vue'
import DiyBanner from './DiyBanner.vue'
import DiyNavigation from './DiyNavigation.vue'
import DiyNotice from './DiyNotice.vue'
import DiyImageCube from './DiyImageCube.vue'
import DiyLive from './DiyLive.vue'
import DiyContent from './DiyContent.vue'
import DiySpecial from './DiySpecial.vue'
import DiyProduct from './DiyProduct.vue'
import DiySeckill from '@/plugins/seckill/components/SeckillDiy.vue'
import DiyCoupon from './DiyCoupon.vue'
import DiyHotZone from './DiyHotZone.vue'
import DiyTitle from './DiyTitle.vue'
import DiyRichText from './DiyRichText.vue'
import DiyBlank from './DiyBlank.vue'
import { getDiyComponentData } from '@/api/home'
import { normalizeDiyComponent } from '@/utils/diy_adapter'

const LAZY_VIEW_NAMES = ['DiyContent', 'DiySpecial', 'DiyProduct', 'DiyLive', 'DiyCoupon']

defineOptions({
  name: 'DiyRenderer'
})

const props = defineProps({
  componentId: {
    type: [Number, String],
    default: 0
  },
  componentType: {
    type: String,
    default: ''
  },
  viewName: {
    type: String,
    default: 'DiyBlank'
  },
  config: {
    type: Object,
    default: () => ({})
  },
  data: {
    type: [Array, Object],
    default: () => []
  },
  isBannerAdjacent: {
    type: Boolean,
    default: false
  },
  reachBottomToken: {
    type: Number,
    default: 0
  }
})

const emit = defineEmits(['banner-change', 'visibility-change'])

function handleSeckillVisibility(visible) {
  emit('visibility-change', visible)
}

const asyncData = ref(null)
const componentLoading = ref(false)
const componentLoadingMore = ref(false)
const componentError = ref('')
const activeCategoryId = ref(0)
const requestSeq = ref(0)

const isLazyComponent = computed(() => {
  return LAZY_VIEW_NAMES.indexOf(props.viewName) > -1 && Number(props.componentId || 0) > 0
})

const isLoadMoreComponent = computed(() => {
  return isLazyComponent.value &&
    ['DiyContent', 'DiySpecial', 'DiyProduct'].indexOf(props.viewName) > -1 &&
    (props.config.load_more === true || Number(props.config.load_more || 0) === 1)
})

const renderData = computed(() => {
  const data = asyncData.value !== null ? asyncData.value : props.data

  // 加载更多类组件的数据若缺少 has_more，统一补上推断结果，
  // 让子组件在确实没有更多数据时能给出提示，而不是一直停留在可加载状态。
  if (!isLoadMoreComponent.value || data === null || typeof data !== 'object' || Array.isArray(data)) {
    return data
  }

  if (Object.prototype.hasOwnProperty.call(data, 'has_more')) {
    return data
  }

  return {
    ...data,
    has_more: hasMoreLoadMoreData(data)
  }
})

watch(() => props.componentId, () => {
  resetAsyncState()
  loadInitialComponentData()
})

watch(() => props.data, () => {
  resetAsyncState()
  loadInitialComponentData()
}, {
  deep: true
})

watch(() => props.reachBottomToken, (nextToken, prevToken) => {
  if (Number(nextToken || 0) <= Number(prevToken || 0)) {
    return
  }

  handleReachBottom()
})

onMounted(() => {
  loadInitialComponentData()
})

/**
 * 将 Banner 当前页上报给 DIY 页面容器。
 *
 * @param {number} index 当前轮播索引。
 * @returns {void}
 */
function handleBannerChange(index = 0) {
  emit('banner-change', Math.max(Number(index || 0), 0))
}

function resetAsyncState() {
  asyncData.value = null
  componentLoading.value = false
  componentLoadingMore.value = false
  componentError.value = ''
  activeCategoryId.value = 0
  requestSeq.value += 1
  emitComponentVisibility(true)
}

function hasLoadedData(value = renderData.value) {
  if (Array.isArray(value)) {
    return value.length > 0
  }

  if (value && typeof value === 'object') {
    if (Array.isArray(value.list) && value.list.length > 0) {
      return true
    }

    if (
      Object.prototype.hasOwnProperty.call(value, 'page') ||
      Object.prototype.hasOwnProperty.call(value, 'has_more') ||
      Object.prototype.hasOwnProperty.call(value, 'total') ||
      Object.prototype.hasOwnProperty.call(value, 'totalPage')
    ) {
      return true
    }
  }

  return false
}

function hasRenderableData(value = renderData.value) {
  if (Array.isArray(value)) {
    return value.length > 0
  }

  if (value && typeof value === 'object' && Array.isArray(value.list)) {
    return value.list.length > 0
  }

  return false
}

function emitComponentVisibility(visible = true) {
  if (LAZY_VIEW_NAMES.indexOf(props.viewName) === -1) {
    return
  }

  emit('visibility-change', visible)
}

function isCategoryTabsData(value = renderData.value) {
  return value && typeof value === 'object' && !Array.isArray(value) && value.mode === 'category_tabs'
}

function getFirstCategoryId(value = renderData.value) {
  const categories = value && typeof value === 'object' && Array.isArray(value.categories)
    ? value.categories
    : []
  const first = categories.find((item) => Number(item.id || 0) > 0)
  return first ? Number(first.id || 0) : 0
}

function getActiveCategoryId(value = renderData.value) {
  const activeCategoryIdValue = Number((value && value.active_category_id) || activeCategoryId.value || 0)
  return activeCategoryIdValue > 0 ? activeCategoryIdValue : getFirstCategoryId(value)
}

function loadInitialComponentData() {
  if (!isLazyComponent.value) {
    return
  }

  if (hasLoadedData(props.data)) {
    asyncData.value = props.data
    emitComponentVisibility(hasRenderableData(props.data) || isLoadMoreComponent.value)
    return
  }

  emitComponentVisibility(true)
  const categoryId = isCategoryTabsData(props.data) ? getActiveCategoryId(props.data) : 0
  loadComponentData(
    categoryId,
    1,
    isLoadMoreComponent.value ? getLoadMorePageLimit(props.data) : 0
  )
}

function reloadComponentData() {
  const categoryId = isCategoryTabsData(renderData.value) ? getActiveCategoryId(renderData.value) : 0
  loadComponentData(
    categoryId,
    1,
    isLoadMoreComponent.value ? getLoadMorePageLimit(renderData.value) : 0
  )
}

function handleCategoryChange(categoryId) {
  const normalizedCategoryId = Number(categoryId || 0)
  if (!isLazyComponent.value || normalizedCategoryId <= 0) {
    return
  }

  activeCategoryId.value = normalizedCategoryId
  loadComponentData(normalizedCategoryId, 1, 0, false, false)
}

function mergeCategoryData(currentData, nextData, categoryId) {
  if (!isCategoryTabsData(currentData) || !isCategoryTabsData(nextData)) {
    return nextData
  }

  const currentCategories = Array.isArray(currentData.categories) ? currentData.categories : []
  const nextCategories = Array.isArray(nextData.categories) ? nextData.categories : []

  return {
    ...currentData,
    ...nextData,
    categories: nextCategories.length > 0 ? nextCategories : currentCategories,
    list: Array.isArray(nextData.list) ? nextData.list : [],
    active_category_id: Number(categoryId || 0)
  }
}

function getLoadMorePageLimit(value = renderData.value) {
  const explicitLimit = Number(value && (value.limit || value.page_size) ? (value.limit || value.page_size) : 0)
  if (explicitLimit > 0) {
    return Math.max(1, Math.min(50, explicitLimit))
  }

  return Math.max(1, Math.min(50, Number(props.config.show_count || 1)))
}

function getComponentDataList(value = renderData.value) {
  if (Array.isArray(value)) {
    return value
  }

  if (value && typeof value === 'object' && Array.isArray(value.list)) {
    return value.list
  }

  return []
}

function normalizeLoadMoreData(value = renderData.value) {
  if (Array.isArray(value)) {
    const list = value.slice()
    const limit = getLoadMorePageLimit(value)

    return {
      list,
      page: 1,
      limit,
      page_size: limit,
      total: list.length,
      totalPage: 0,
      has_more: list.length >= limit,
      loading_more: false
    }
  }

  if (value && typeof value === 'object') {
    const limit = getLoadMorePageLimit(value)

    return {
      ...value,
      list: getComponentDataList(value).slice(),
      limit: Number(value.limit || value.page_size || limit),
      page_size: Number(value.page_size || value.limit || limit),
      loading_more: Boolean(value.loading_more)
    }
  }

  const limit = getLoadMorePageLimit()
  return {
    list: [],
    page: 1,
    limit,
    page_size: limit,
    total: 0,
    totalPage: 0,
    has_more: false,
    loading_more: false
  }
}

function hasMoreLoadMoreData(value = renderData.value) {
  if (!isLoadMoreComponent.value) {
    return false
  }

  const data = normalizeLoadMoreData(value)
  const list = getComponentDataList(data)

  if (Object.prototype.hasOwnProperty.call(data, 'has_more')) {
    return data.has_more === true || Number(data.has_more || 0) === 1
  }

  if (list.length === 0) {
    return false
  }

  const totalPage = Number(data.totalPage || data.total_page || 0)
  if (totalPage > 0) {
    return Number(data.page || 1) < totalPage
  }

  const total = Number(data.total || 0)
  if (total > 0) {
    return list.length < total
  }

  return list.length >= getLoadMorePageLimit(data)
}

function getNextLoadMorePage(value = renderData.value) {
  const data = normalizeLoadMoreData(value)
  const currentPage = Number(data.page || 0)
  if (currentPage > 0) {
    return currentPage + 1
  }

  const limit = getLoadMorePageLimit(data)
  return Math.max(2, Math.ceil(getComponentDataList(data).length / limit) + 1)
}

function setLoadMoreRuntimeState(loading = false) {
  if (!isLoadMoreComponent.value) {
    return
  }

  const data = normalizeLoadMoreData(renderData.value)
  asyncData.value = {
    ...data,
    loading_more: loading
  }
}

function mergeLoadMoreData(currentData, nextData) {
  const current = normalizeLoadMoreData(currentData)
  const next = normalizeLoadMoreData(nextData)
  const list = getComponentDataList(current).concat(getComponentDataList(next))
  const limit = Number(next.limit || next.page_size || current.limit || current.page_size || getLoadMorePageLimit(current))

  return {
    ...current,
    ...next,
    list,
    limit,
    page_size: Number(next.page_size || next.limit || current.page_size || current.limit || limit),
    page: Number(next.page || current.page || 1),
    total: Number(next.total || current.total || list.length),
    totalPage: Number(next.totalPage || next.total_page || current.totalPage || current.total_page || 0),
    has_more:
      Object.prototype.hasOwnProperty.call(next, 'has_more')
        ? (next.has_more === true || Number(next.has_more || 0) === 1)
        : list.length >= limit,
    loading_more: false
  }
}

async function handleReachBottom() {
  if (!isLoadMoreComponent.value || componentLoading.value || componentLoadingMore.value) {
    return
  }

  if (!hasMoreLoadMoreData(renderData.value)) {
    return
  }

  await loadMoreComponentData()
}

async function loadMoreComponentData() {
  const currentData = normalizeLoadMoreData(renderData.value)
  if (!isLoadMoreComponent.value || componentLoading.value || componentLoadingMore.value) {
    return
  }

  if (!hasMoreLoadMoreData(currentData)) {
    return
  }

  const categoryId = isCategoryTabsData(currentData) ? getActiveCategoryId(currentData) : 0
  const nextPage = getNextLoadMorePage(currentData)
  const limit = getLoadMorePageLimit(currentData)

  componentLoadingMore.value = true
  setLoadMoreRuntimeState(true)
  await loadComponentData(categoryId, nextPage, limit, true)
}

function normalizeLoadedData(rawData) {
  return normalizeDiyComponent({
    component_type: props.componentType,
    type: props.componentType,
    config: props.config,
    data: rawData
  }).data
}

async function loadComponentData(categoryId = 0, page = 1, limit = 0, append = false, hideWhenEmpty = true) {
  const componentId = Number(props.componentId || 0)
  if (!isLazyComponent.value || componentId <= 0) {
    return
  }

  const normalizedCategoryId = Number(categoryId || 0)
  if (normalizedCategoryId > 0) {
    activeCategoryId.value = normalizedCategoryId
  }

  const seq = requestSeq.value + 1
  requestSeq.value = seq
  if (append) {
    componentLoadingMore.value = true
    setLoadMoreRuntimeState(true)
  } else {
    componentLoading.value = true
    componentError.value = ''
  }

  const params = { component_id: componentId }
  if (normalizedCategoryId > 0) {
    params.category_id = normalizedCategoryId
  }
  if (isLoadMoreComponent.value || append || page > 1 || limit > 0) {
    params.page = Math.max(1, Number(page || 1))
    params.limit = Math.max(1, Math.min(50, Number(limit || getLoadMorePageLimit(renderData.value))))
  }

  const res = await getDiyComponentData(params).catch((error) => {
    console.error('加载DIY组件数据失败:', error)
    return null
  })

  if (seq !== requestSeq.value) {
    return
  }

  if (append) {
    componentLoadingMore.value = false
  } else {
    componentLoading.value = false
  }

  if (!res || res.code !== 200) {
    if (!append) {
      componentError.value = '加载失败'
      emitComponentVisibility(true)
    } else {
      setLoadMoreRuntimeState(false)
    }
    return
  }

  const nextData = normalizeLoadedData(res.data || [])
  if (append) {
    asyncData.value = mergeLoadMoreData(renderData.value, nextData)
  } else if (normalizedCategoryId > 0 && isCategoryTabsData(renderData.value) && isCategoryTabsData(nextData)) {
    asyncData.value = mergeCategoryData(renderData.value, nextData, normalizedCategoryId)
  } else {
    asyncData.value = nextData
  }

  const hasData = hasRenderableData(asyncData.value)
  emitComponentVisibility(hasData || !hideWhenEmpty || isLoadMoreComponent.value)
}
</script>

<style lang="scss" scoped>
.diy-renderer {
  width: auto;
}
</style>
