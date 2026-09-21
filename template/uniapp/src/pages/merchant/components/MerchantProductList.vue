<template>
  <view class="merchant-product-list" :style="pageHeightStyle">
    <app-safe-top :auto-back="false" />
    <view class="merchant-product-list__header" :style="headerStyle">
      <view class="merchant-product-list__back" hover-class="merchant-product-list__pressed" @click="emit('go-back')">
        <text class="iconfont icon-back"></text>
      </view>
      <view class="merchant-product-list__search">
        <text class="iconfont icon-search"></text>
        <input v-model="keyword" :maxlength="50" confirm-type="search" placeholder="搜索商品" @confirm="handleKeywordConfirm" />
      </view>
    </view>
    <view class="merchant-product-list__sort">
      <view
        v-for="item in sortOptions"
        :key="item.key"
        class="merchant-product-list__sort-item"
        :class="{ 'merchant-product-list__sort-item--active': sortKey === item.key }"
        hover-class="merchant-product-list__pressed"
        @click="handleSortChange(item.key)"
      >
        <text>{{ item.label }}</text>
        <text v-if="item.key === 'price'" class="iconfont icon-triangledownfill" :class="{ 'merchant-product-list__price-up': priceAscending }"></text>
      </view>
    </view>
    <scroll-view
      scroll-y
      class="merchant-product-list__scroll"
      :show-scrollbar="false"
      :refresher-enabled="true"
      :refresher-triggered="refreshing"
      lower-threshold="80"
      @refresherrefresh="handleRefresh"
      @scrolltolower="fetchProducts(false)"
    >
      <view class="merchant-product-list__body">
        <diy-product :config="productConfig" :data="productData" :loading="loading" :error="listError" @retry="fetchProducts(true)" />
        <view v-if="listError && products.length" class="merchant-product-list__error">
          <text>{{ listError }}</text>
          <button class="merchant-product-list__retry" @click="fetchProducts(false)">重试</button>
        </view>
      </view>
    </scroll-view>
  </view>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { getProductList } from '@/api/product'
import { normalizePublicErrorMessage } from '@/utils/error'
import { getMiniProgramSearchNavMetrics } from '@/utils/mini_program_nav'
import DiyProduct from '@/pages/diy/components/diy/DiyProduct.vue'

defineOptions({ name: 'MerchantProductList' })

const props = defineProps({ merchantId: { type: Number, required: true }})
const emit = defineEmits(['go-back'])
const PAGE_LIMIT = 10
const sortOptions = [
  { key: 'default', label: '综合' },
  { key: 'sales', label: '销量' },
  { key: 'price', label: '价格' }
]
const keyword = ref('')
const appliedKeyword = ref('')
const sortKey = ref('default')
const priceAscending = ref(false)
const products = ref([])
const nextPage = ref(1)
const hasMore = ref(false)
const loading = ref(false)
const loadingMore = ref(false)
const refreshing = ref(false)
const listError = ref('')
const windowHeight = ref(uni.getSystemInfoSync().windowHeight)
const pageHeightStyle = computed(() => `height: ${windowHeight.value}px;`)
const headerStyle = ref('')
const productConfig = { layout: 'grid', with_nav: false, load_more: true, show_count: PAGE_LIMIT }
const productData = computed(() => ({
  list: products.value,
  has_more: hasMore.value,
  loading_more: loadingMore.value
}))
let listRequestId = 0

watch(() => props.merchantId, () => fetchProducts(true), { immediate: true })
handleWindowResize()
onMounted(() => {
  if (typeof uni.onWindowResize === 'function') uni.onWindowResize(handleWindowResize)
})
onUnmounted(() => {
  listRequestId += 1
  if (typeof uni.offWindowResize === 'function') uni.offWindowResize(handleWindowResize)
})

/**
 * 同步滚动容器高度，避免旋转屏幕或软键盘改变窗口后产生双层滚动。
 *
 * @param {Object} event UniApp 窗口尺寸变化事件。
 * @returns {void}
 */
function handleWindowResize(event = {}) {
  const height = Number(event.size?.windowHeight || uni.getSystemInfoSync().windowHeight)
  if (height > 0) windowHeight.value = height
  // #ifdef MP-WEIXIN
  // 状态栏已由 app-safe-top 占位，搜索行只需要避让右侧系统胶囊。
  const navMetrics = getMiniProgramSearchNavMetrics()
  headerStyle.value = `padding-top: 0; padding-right: ${navMetrics.rightInset}px;`
  // #endif
}

/**
 * 查询当前商户商品；刷新使旧请求失效，失败分页保留原列表和页码。
 *
 * @param {boolean} isRefresh 是否重新从第一页查询。
 * @returns {Promise<void>} 更新 DIY 商品数据、分页和加载状态。
 */
async function fetchProducts(isRefresh = false) {
  if (!isRefresh && (loading.value || loadingMore.value || !hasMore.value)) return
  const requestId = ++listRequestId
  const requestPage = isRefresh ? 1 : nextPage.value
  listError.value = ''
  loading.value = isRefresh
  loadingMore.value = !isRefresh
  if (isRefresh) {
    products.value = []
    hasMore.value = false
  }
  try {
    // 缺失商户 ID 时禁止调用通用商品接口，避免退化为平台列表。
    if (!Number.isSafeInteger(props.merchantId) || props.merchantId <= 0) {
      throw new Error('商户ID不能为空')
    }
    const sort = sortKey.value === 'price'
      ? (priceAscending.value ? 'price_asc' : 'price_desc')
      : (sortKey.value === 'sales' ? 'sales' : '')
    const res = await getProductList({
      merchant_id: props.merchantId,
      keyword: appliedKeyword.value,
      sort,
      page: requestPage,
      limit: PAGE_LIMIT
    }, { showError: false })
    if (requestId !== listRequestId) return
    if (!res || res.code !== 200 || !res.data || !Array.isArray(res.data.list)) {
      throw new Error(res && res.msg || '商品加载失败')
    }
    const data = res.data
    // 销量/价格变化会使 offset 分页重叠；按 ID 合并并更新已有商品字段。
    const rows = isRefresh ? data.list : products.value.concat(data.list)
    products.value = Array.from(new Map(rows.map(item => [Number(item.id), item])).values())
    const currentPage = Number(data.page) || requestPage
    hasMore.value = data.has_more === undefined
      ? currentPage < Number(data.totalPage || 0)
      : data.has_more === true || Number(data.has_more) === 1
    nextPage.value = currentPage + 1
  } catch (error) {
    if (requestId === listRequestId) {
      listError.value = normalizePublicErrorMessage(error.message, '商品加载失败')
    }
  } finally {
    if (requestId === listRequestId) {
      loading.value = false
      loadingMore.value = false
      refreshing.value = false
    }
  }
}

/**
 * 更换排序，价格再次点击时切换升降序。
 *
 * @param {string} key 综合、销量或价格排序键。
 * @returns {void}
 */
function handleSortChange(key) {
  if (key === sortKey.value && key !== 'price') return
  priceAscending.value = key === 'price' && (sortKey.value !== 'price' || !priceAscending.value)
  sortKey.value = key
  fetchProducts(true)
}

/** 提交搜索条件，输入中的关键词不影响原列表的分页。@returns {void} */
function handleKeywordConfirm() {
  appliedKeyword.value = keyword.value.trim().slice(0, 50)
  fetchProducts(true)
}

/** 下拉刷新当前排序和关键词下的商户商品。@returns {void} */
function handleRefresh() {
  refreshing.value = true
  fetchProducts(true)
}
</script>

<style lang="scss" scoped>
.merchant-product-list {
  display: flex;
  flex-direction: column;
  box-sizing: border-box;
  background: var(--surface-page, #f9f9f9);
}

.merchant-product-list__header {
  display: flex;
  align-items: center;
  padding: 16rpx 24rpx;
  background: var(--surface-card, #ffffff);
}

.merchant-product-list__back {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 64rpx;
  height: 72rpx;
  color: var(--text-primary, #18181b);
  font-size: 40rpx;
}

.merchant-product-list__search {
  display: flex;
  align-items: center;
  flex: 1;
  min-width: 0;
  height: 72rpx;
  padding: 0 24rpx;
  border-radius: 16rpx;
  background: var(--surface-card-muted, #f3f4f6);
  color: var(--text-secondary, #71717a);

  input {
    flex: 1;
    min-width: 0;
    margin-left: 12rpx;
    font-size: 24rpx;
    color: var(--text-primary, #18181b);
  }
}

.merchant-product-list__sort {
  display: flex;
  flex-shrink: 0;
  background: var(--surface-card, #ffffff);
  border-bottom: 1px solid var(--border-light, #f4f4f5);
}

.merchant-product-list__sort-item {
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 1;
  height: 80rpx;
  font-size: 24rpx;
  color: var(--text-secondary, #71717a);

  .iconfont {
    margin-left: 8rpx;
    font-size: 20rpx;
  }
}

.merchant-product-list__sort-item--active {
  color: var(--brand-primary, #ea580c);
  font-weight: 700;
}

.merchant-product-list__price-up {
  transform: rotate(180deg);
}

.merchant-product-list__scroll {
  flex: 1;
  min-height: 0;
  height: 0;
}

.merchant-product-list__body {
  padding: 16rpx 12rpx calc(132rpx + env(safe-area-inset-bottom));
}

.merchant-product-list__error {
  padding: 24rpx;
  text-align: center;
  font-size: 24rpx;
  color: var(--text-secondary, #71717a);
}

.merchant-product-list__retry {
  width: 160rpx;
  margin-top: 16rpx;
  font-size: 24rpx;
  color: var(--brand-primary, #ea580c);
  background: var(--surface-card, #ffffff);
}

.merchant-product-list__pressed {
  opacity: 0.86;
}
</style>
