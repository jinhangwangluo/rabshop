<template>
  <page-meta :page-style="themePageStyle"></page-meta>
  <view class="merchant-category-page">
    <app-safe-top background="#ffffff" :auto-back="false" />

    <view class="merchant-category-header">
      <text class="merchant-category-header__title">{{ mode === 'product' ? '商品分类' : '专题分类' }}</text>
    </view>

    <view v-if="pageLoading" class="merchant-category-state merchant-category-state--page">
      <six-petal-spiral :active="true" :size="80" text="" :color="themePrimaryColor" />
    </view>
    <view v-else-if="pageError" class="merchant-category-state merchant-category-state--page">
      <text class="merchant-category-state__text">{{ pageError }}</text>
      <view class="merchant-category-retry" hover-class="is-hover" @click="loadCategoryConfig">
        <text>重新加载</text>
      </view>
    </view>
    <view v-else-if="!rootCategories.length" class="merchant-category-state merchant-category-state--page">
      <text class="merchant-category-state__title">暂无分类</text>
    </view>

    <template v-else>
      <scroll-view scroll-x class="merchant-root-tabs" :show-scrollbar="false">
        <view class="merchant-root-tabs__track">
          <view
            v-for="item in rootCategories"
            :key="item.id"
            class="merchant-root-tabs__item"
            :class="{ 'is-active': item.id === activeRootId }"
            hover-class="is-hover"
            @click="handleRootChange(item)"
          >
            <view class="merchant-root-tabs__image-box">
              <image v-if="item.image" class="merchant-root-tabs__image" :src="item.image" mode="aspectFill" />
              <text v-else class="merchant-root-tabs__initial">{{ item.initial }}</text>
            </view>
            <text class="merchant-root-tabs__name">{{ item.name }}</text>
          </view>
        </view>
      </scroll-view>

      <view class="merchant-category-body">
        <scroll-view scroll-y class="merchant-sidebar" :show-scrollbar="false">
          <view
            v-for="item in sidebarCategories"
            :key="item.id"
            class="merchant-sidebar__item"
            :class="{ 'is-active': item.id === activeCategoryId }"
            hover-class="is-hover"
            @click="handleCategoryChange(item)"
          >
            <text>{{ item.name }}</text>
          </view>
        </scroll-view>

        <scroll-view
          scroll-y
          class="merchant-category-scroll"
          refresher-enabled
          :show-scrollbar="false"
          :refresher-triggered="refreshing"
          @refresherrefresh="handleRefresh"
          @scrolltolower="loadMore"
        >
          <view v-if="listLoading && !listItems.length" class="merchant-category-state">
            <six-petal-spiral :active="true" :size="72" text="" :color="themePrimaryColor" />
          </view>
          <view v-else-if="listError && !listItems.length" class="merchant-category-state">
            <text class="merchant-category-state__text">{{ listError }}</text>
            <view class="merchant-category-retry" hover-class="is-hover" @click="retryList">
              <text>重新加载</text>
            </view>
          </view>
          <view v-else-if="!listItems.length" class="merchant-category-state">
            <text class="merchant-category-state__title">{{ mode === 'product' ? '暂无商品' : '暂无专题' }}</text>
          </view>
          <diy-product
            v-else-if="mode === 'product'"
            class="merchant-category-products"
            :config="productConfig"
            :data="productData"
            :loading="listLoading"
            :error="listError"
            @retry="retryList"
          />
          <view v-else class="merchant-category-specials">
            <view
              v-for="item in specialItems"
              :key="item.id"
              class="merchant-special-card"
              hover-class="is-hover"
              @click="openSpecial(item)"
            >
              <view class="merchant-special-card__cover-box">
                <image v-if="item.cover" class="merchant-special-card__cover" :src="item.cover" mode="aspectFill" />
                <view v-else class="merchant-special-card__placeholder"></view>
              </view>
              <view class="merchant-special-card__body">
                <text class="merchant-special-card__title">{{ item.title }}</text>
                <text v-if="item.description" class="merchant-special-card__description">{{ item.description }}</text>
                <view class="merchant-special-card__meta">
                  <text class="merchant-special-card__count">{{ item.countText }}</text>
                  <text class="merchant-special-card__price">{{ item.priceText }}</text>
                </view>
              </view>
            </view>
          </view>

          <view v-if="listError && listItems.length" class="merchant-category-state merchant-category-state--footer">
            <text class="merchant-category-state__text">{{ listError }}</text>
            <view class="merchant-category-retry" hover-class="is-hover" @click="retryList">
              <text>重新加载</text>
            </view>
          </view>
          <view v-else-if="mode === 'special' && listItems.length" class="merchant-category-more">
            <text>{{ listLoading ? '加载中...' : (hasMore ? '' : '没有更多了') }}</text>
          </view>
        </scroll-view>
      </view>
    </template>

    <merchant-bottom-nav active-key="category" :merchant-id="merchantId" :menu-mode="menuMode" />
    <app-floating-button />
  </view>
</template>

<script setup>
import { computed, ref } from 'vue'
import { onLoad, onUnload } from '@dcloudio/uni-app'
import { getMerchantCategory, getMerchantCategoryList } from '@/api/merchant'
import { normalizePublicErrorMessage } from '@/utils/error'
import DiyProduct from '@/pages/diy/components/diy/DiyProduct.vue'
import MerchantBottomNav from '../components/MerchantBottomNav.vue'

defineOptions({ name: 'MerchantCategoryIndex' })

const PAGE_LIMIT = 10
const merchantId = ref(0)
const menuMode = ref('special')
const mode = ref('product')
const pageLoading = ref(false)
const pageError = ref('')
const rootCategories = ref([])
const activeRootId = ref(0)
const activeCategoryId = ref(0)
const listItems = ref([])
const currentPage = ref(1)
const hasMore = ref(false)
const listLoading = ref(false)
const listError = ref('')
const refreshing = ref(false)
let configRequestId = 0
let listRequestId = 0

const activeRoot = computed(() => rootCategories.value.find((item) => item.id === activeRootId.value))
const sidebarCategories = computed(() => {
  const root = activeRoot.value
  return root ? [{ id: root.id, name: '全部' }, ...root.children] : []
})
const productConfig = { layout: 'list', with_nav: false, load_more: true, show_count: PAGE_LIMIT }
const productData = computed(() => ({
  list: listItems.value,
  has_more: hasMore.value,
  loading_more: listLoading.value && listItems.value.length > 0
}))
const specialItems = computed(() => listItems.value.map((item) => ({
  id: Number(item.id),
  title: String(item.title || '').trim(),
  cover: String(item.cover || '').trim(),
  description: String(item.description || '').trim(),
  countText: Number(item.content_count || 0) + '节内容',
  priceText: String(item.price_text || '').trim() ||
    (Number(item.price || 0) > 0 ? '¥' + Number(item.price).toFixed(2) : '免费')
})))

onLoad((options = {}) => {
  merchantId.value = Math.max(0, Number(options.merchant_id || options.merchantId || 0))
  loadCategoryConfig()
})
onUnload(() => {
  configRequestId += 1
  listRequestId += 1
})

/**
 * 加载商户配置与分类树，并初始化当前分类列表。
 *
 * @returns {Promise<void>}
 */
async function loadCategoryConfig() {
  const requestId = ++configRequestId
  listRequestId += 1
  pageLoading.value = true
  pageError.value = ''
  listLoading.value = false
  refreshing.value = false
  if (merchantId.value <= 0) {
    pageError.value = '商户ID不能为空'
    pageLoading.value = false
    return
  }

  try {
    const res = await getMerchantCategory({ merchant_id: merchantId.value }, { showError: false })
    if (requestId !== configRequestId) {
      return
    }
    if (!res || res.code !== 200 || !res.data ||
      !['special', 'product'].includes(res.data.mode) || !Array.isArray(res.data.categories)) {
      throw new Error(res && res.msg || '分类配置加载失败')
    }
    mode.value = res.data.mode
    menuMode.value = res.data.menu?.mode || 'special'
    rootCategories.value = normalizeCategories(res.data.categories)
    activeRootId.value = rootCategories.value.length ? rootCategories.value[0].id : 0
    activeCategoryId.value = activeRootId.value
    listItems.value = []
    hasMore.value = false
    if (activeCategoryId.value > 0) {
      await loadCategoryItems(true)
    }
  } catch (error) {
    if (requestId === configRequestId) {
      pageError.value = normalizePublicErrorMessage(error.message, '分类配置加载失败')
    }
  } finally {
    if (requestId === configRequestId) {
      pageLoading.value = false
    }
  }
}

/**
 * 规范化真实分类树，不把平台分类映射成商户分类。
 *
 * @param {Array<Object>} categories 接口分类树。
 * @returns {Array<Object>} 可展示的分类树。
 */
function normalizeCategories(categories = []) {
  return categories.filter((item) => item && Number(item.id) > 0).map((item) => ({
    id: Number(item.id),
    name: String(item.name || '').trim(),
    initial: String(item.name || '').trim().slice(0, 1),
    image: String(item.image || '').trim(),
    children: normalizeCategories(Array.isArray(item.children) ? item.children : [])
  }))
}

/**
 * 按当前分类读取分页。重置请求可取代正在执行的旧分类查询。
 *
 * @param {boolean} reset 是否清空旧列表并加载第一页。
 * @returns {Promise<void>}
 */
async function loadCategoryItems(reset = false) {
  if (!activeCategoryId.value || (!reset && listLoading.value)) {
    return
  }
  const requestId = ++listRequestId
  const categoryId = activeCategoryId.value
  const nextPage = reset ? 1 : currentPage.value + 1
  listLoading.value = true
  listError.value = ''
  if (reset) {
    listItems.value = []
    currentPage.value = 1
    hasMore.value = false
  }

  try {
    const res = await getMerchantCategoryList({
      merchant_id: merchantId.value,
      category_id: categoryId,
      page: nextPage,
      limit: PAGE_LIMIT
    }, { showError: false })
    if (requestId !== listRequestId || categoryId !== activeCategoryId.value) {
      return
    }
    if (!res || res.code !== 200 || !res.data || res.data.mode !== mode.value || !Array.isArray(res.data.list)) {
      throw new Error(res && res.msg || '分类内容加载失败')
    }
    const nextItems = res.data.list.filter((item) => item && Number(item.id) > 0)
    const merged = new Map((reset ? [] : listItems.value).map((item) => [Number(item.id), item]))
    nextItems.forEach((item) => merged.set(Number(item.id), item))
    listItems.value = Array.from(merged.values())
    currentPage.value = Number(res.data.page || nextPage)
    hasMore.value = res.data.has_more === true || Number(res.data.has_more) === 1
  } catch (error) {
    if (requestId === listRequestId) {
      listError.value = normalizePublicErrorMessage(error.message, '分类内容加载失败')
    }
  } finally {
    if (requestId === listRequestId) {
      listLoading.value = false
      refreshing.value = false
    }
  }
}

/**
 * 切换一级分类，左侧重置为全部并重新加载列表。
 *
 * @param {Object} item 一级分类。
 * @returns {void}
 */
function handleRootChange(item) {
  if (item.id === activeRootId.value) {
    return
  }
  activeRootId.value = item.id
  activeCategoryId.value = item.id
  loadCategoryItems(true)
}

/**
 * 切换左侧二级分类。
 *
 * @param {Object} item 当前分类。
 * @returns {void}
 */
function handleCategoryChange(item) {
  if (item.id === activeCategoryId.value) {
    return
  }
  activeCategoryId.value = item.id
  loadCategoryItems(true)
}

/**
 * 刷新当前分类列表。
 *
 * @returns {void}
 */
function handleRefresh() {
  if (refreshing.value) {
    return
  }
  refreshing.value = true
  loadCategoryItems(true)
}

/**
 * 加载当前分类的下一页。
 *
 * @returns {void}
 */
function loadMore() {
  if (hasMore.value && !listLoading.value) {
    loadCategoryItems(false)
  }
}

/**
 * 保留成功页码重试失败分页，空列表时重新请求第一页。
 *
 * @returns {void}
 */
function retryList() {
  loadCategoryItems(listItems.value.length === 0)
}

/**
 * 进入专题详情并保留商户上下文。
 *
 * @param {Object} item 专题项。
 * @returns {void}
 */
function openSpecial(item) {
  uni.navigateTo({
    url: '/pages/contents/special/detail/index?id=' + item.id + '&merchant_id=' + merchantId.value
  })
}
</script>

<style lang="scss">
page {
  height: 100%;
  background: #ffffff;
}
</style>

<style lang="scss" scoped>
.merchant-category-page {
  height: 100%;
  min-height: 100%;
  padding-bottom: 96rpx;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  background: var(--surface-card, #ffffff);
}

/* #ifdef MP-WEIXIN */
.merchant-category-page {
  padding-bottom: calc(96rpx + env(safe-area-inset-bottom));
}
/* #endif */

/* #ifdef H5 */
.merchant-category-page {
  padding-bottom: calc(96rpx + var(--window-bottom, 0px));
}
/* #endif */

.merchant-category-header {
  flex-shrink: 0;
  padding: 18rpx 32rpx;
  text-align: center;
}

.merchant-category-header__title {
  font-size: 32rpx;
  line-height: 44rpx;
  font-weight: 700;
  color: var(--text-title, #18181b);
}

.merchant-root-tabs {
  flex-shrink: 0;
  height: 152rpx;
  border-bottom: 1px solid var(--border-light, #f4f4f5);
  white-space: nowrap;
}

.merchant-root-tabs__track {
  display: flex;
  align-items: center;
  height: 152rpx;
  padding: 0 24rpx;
}

.merchant-root-tabs__item {
  flex-shrink: 0;
  width: 128rpx;
  margin-right: 16rpx;
  text-align: center;
  color: var(--text-regular, #3f3f46);
}

.merchant-root-tabs__image-box {
  width: 72rpx;
  height: 72rpx;
  margin: 0 auto 8rpx;
  border-radius: 12rpx;
  overflow: hidden;
  background: var(--surface-card-muted, #f3f4f6);
  display: flex;
  align-items: center;
  justify-content: center;
}

.merchant-root-tabs__image {
  width: 100%;
  height: 100%;
}

.merchant-root-tabs__initial {
  font-size: 28rpx;
  line-height: 40rpx;
}

.merchant-root-tabs__name {
  display: block;
  font-size: 24rpx;
  line-height: 34rpx;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.merchant-root-tabs__item.is-active {
  font-weight: 700;
  color: var(--brand-primary, #ea580c);
}

.merchant-root-tabs__item.is-active .merchant-root-tabs__image-box {
  background: var(--brand-primary-pale, #fff7ed);
}

.merchant-category-body {
  flex: 1;
  min-height: 0;
  display: flex;
}

.merchant-sidebar {
  flex-shrink: 0;
  width: 160rpx;
  height: 100%;
  background: var(--surface-card-soft, #f4f4f5);
}

.merchant-sidebar__item {
  min-height: 92rpx;
  padding: 24rpx 18rpx;
  box-sizing: border-box;
  font-size: 24rpx;
  line-height: 34rpx;
  color: var(--text-regular, #3f3f46);
  word-break: break-all;
}

.merchant-sidebar__item.is-active {
  font-weight: 700;
  color: var(--brand-primary, #ea580c);
  background: var(--surface-card, #ffffff);
  border-left: 6rpx solid var(--brand-primary, #ea580c);
}

.merchant-category-scroll {
  flex: 1;
  min-width: 0;
  height: 100%;
}

.merchant-category-products :deep(.product-card__cover--list) {
  width: 140rpx;
  height: 160rpx;
}

.merchant-category-products :deep(.product-card__price-wrap) {
  flex-wrap: wrap;
}

.merchant-category-state {
  min-height: 320rpx;
  padding: 48rpx 28rpx;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.merchant-category-state--page {
  flex: 1;
}

.merchant-category-state--footer {
  min-height: 160rpx;
}

.merchant-category-state__title {
  font-size: 28rpx;
  line-height: 40rpx;
  font-weight: 700;
  color: var(--text-title, #18181b);
}

.merchant-category-state__text {
  font-size: 24rpx;
  line-height: 34rpx;
  color: var(--text-secondary, #71717a);
  text-align: center;
  word-break: break-all;
}

.merchant-category-retry {
  min-width: 164rpx;
  height: 64rpx;
  margin-top: 20rpx;
  padding: 0 24rpx;
  box-sizing: border-box;
  border-radius: 12rpx;
  background: var(--brand-primary, #ea580c);
  color: #ffffff;
  font-size: 24rpx;
  line-height: 64rpx;
  text-align: center;
}

.is-hover {
  opacity: 0.84;
}

.merchant-category-specials {
  padding: 20rpx;
}

.merchant-special-card {
  overflow: hidden;
  margin-bottom: 24rpx;
  border-radius: 16rpx;
  background: var(--surface-card, #ffffff);
  border: 1px solid var(--border-light, #f4f4f5);
}

.merchant-special-card__cover-box {
  height: 220rpx;
  background: var(--surface-card-muted, #f3f4f6);
}

.merchant-special-card__cover,
.merchant-special-card__placeholder {
  width: 100%;
  height: 100%;
}

.merchant-special-card__body {
  padding: 20rpx;
}

.merchant-special-card__title,
.merchant-special-card__description {
  display: -webkit-box;
  overflow: hidden;
  text-overflow: ellipsis;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.merchant-special-card__title {
  height: 80rpx;
  font-size: 28rpx;
  line-height: 40rpx;
  font-weight: 700;
  color: var(--text-title, #18181b);
}

.merchant-special-card__description {
  height: 68rpx;
  margin-top: 8rpx;
  font-size: 24rpx;
  line-height: 34rpx;
  color: var(--text-secondary, #71717a);
}

.merchant-special-card__meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  margin-top: 16rpx;
}

.merchant-special-card__count {
  margin-right: 12rpx;
  font-size: 22rpx;
  line-height: 32rpx;
  color: var(--text-secondary, #71717a);
}

.merchant-special-card__price {
  font-size: 28rpx;
  line-height: 40rpx;
  font-weight: 700;
  color: var(--brand-primary, #ea580c);
}

.merchant-category-more {
  min-height: 64rpx;
  text-align: center;
  font-size: 24rpx;
  line-height: 34rpx;
  color: var(--text-secondary, #71717a);
}
</style>
