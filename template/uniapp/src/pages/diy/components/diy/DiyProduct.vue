<template>
  <view class="diy-product" :style="componentStyle">
    <view v-if="isWithNav" class="product-nav">
      <scroll-view
        v-if="categoryTabs.length > 0"
        class="product-tabs-scroll"
        scroll-x
        scroll-with-animation
        :scroll-into-view="categoryTabIntoView"
        :show-scrollbar="false"
      >
        <view :class="productTabsTrackClass">
          <view
            v-for="item in categoryTabs"
            :key="item.id"
            :id="item.anchorId"
            class="product-tab"
            :class="{ 'product-tab--active': item.id === currentCategoryId }"
            hover-class="product-tab--hover"
            @click="handleCategoryChange(item.id)"
          >
            <view v-if="tabStyle === 'image'" class="product-tab__image-wrap">
              <image v-if="item.image" class="product-tab__image" :src="item.image" mode="aspectFill" />
              <view v-else class="product-tab__image-placeholder"></view>
            </view>
            <text class="product-tab__title">{{ item.name }}</text>
            <text v-if="tabStyle === 'subtitle' && item.subtitle" class="product-tab__subtitle">
              {{ item.subtitle }}
            </text>
            <view v-if="tabStyle === 'arc' && item.id === currentCategoryId" class="product-tab__arc"></view>
          </view>
        </view>
      </scroll-view>
    </view>

    <view v-else-if="config.title" class="product-header">
      <text class="product-header__title">{{ config.title }}</text>
    </view>

    <view v-if="productList.length === 0" class="product-empty" @click="handleRetryClick">
      <text class="product-empty__title">{{ emptyText }}</text>
    </view>

    <view v-else-if="layout === 'list'" class="product-list">
      <view
        v-for="item in productList"
        :key="item.renderKey"
        class="product-card product-card--list"
        hover-class="product-card--hover"
        @click="handleProductClick(item)"
      >
        <view class="product-card__cover product-card__cover--list" :style="imageRadiusStyle">
          <image v-if="item.image" class="product-card__image" :src="item.image" mode="aspectFill" />
          <view v-else class="product-card__placeholder">
            <text>商品</text>
          </view>
        </view>
        <view class="product-card__body product-card__body--list">
          <text class="product-card__title product-card__title--list"><text
            v-if="item.firstTagText"
            class="product-card__title-badge"
            :style="item.firstTagStyle"
          >{{ item.firstTagText }}</text>{{ item.title }}</text>
          <view class="product-card__price-row product-card__price-row--list">
            <view class="product-card__price-wrap">
              <text class="product-card__price">{{ item.priceText }}</text>
              <view v-if="item.memberPriceText" class="product-card__vip product-card__vip--list">
                <image class="vip-price-icon" src="/static/images/vip.png" mode="aspectFill" />
                <text class="product-card__vip-price">{{ item.memberPriceText }}</text>
              </view>
            </view>
          </view>
          <view class="product-card__meta product-card__meta--list">
            <text class="product-card__sales">{{ item.salesText }}</text>
            <view class="product-card__cart" hover-class="product-card__cart--hover" @click.stop="handleCartClick(item)">
              <text class="iconfont icon-daohanggouwuche product-card__cart-icon"></text>
            </view>
          </view>
        </view>
      </view>
    </view>

    <view v-else class="product-grid">
      <view v-for="item in productList" :key="item.renderKey" class="product-grid__item">
        <view class="product-card product-card--grid" hover-class="product-card--hover" @click="handleProductClick(item)">
          <view class="product-card__cover product-card__cover--grid" :style="imageRadiusStyle">
            <image v-if="item.image" class="product-card__image" :src="item.image" mode="aspectFill" />
            <view v-else class="product-card__placeholder">
              <text>商品</text>
            </view>
          </view>
          <view class="product-card__body product-card__body--grid">
            <text class="product-card__title product-card__title--grid"><text
              v-if="item.firstTagText"
              class="product-card__title-badge"
              :style="item.firstTagStyle"
            >{{ item.firstTagText }}</text>{{ item.title }}</text>
            <view class="product-card__price-row product-card__price-row--grid">
              <text class="product-card__price">{{ item.priceText }}</text>
              <view v-if="item.memberPriceText" class="product-card__vip product-card__vip--grid">
                <image class="vip-price-icon" src="/static/images/vip.png" mode="aspectFill" />
                <text class="product-card__vip-price">{{ item.memberPriceText }}</text>
              </view>
            </view>
            <view class="product-card__meta">
              <text class="product-card__sales">{{ item.salesText }}</text>
              <view class="product-card__cart" hover-class="product-card__cart--hover" @click.stop="handleCartClick(item)">
                <text class="iconfont icon-daohanggouwuche product-card__cart-icon"></text>
              </view>
            </view>
          </view>
        </view>
      </view>
    </view>

    <view v-if="loadMoreText" class="product-load-more">
      <text>{{ loadMoreText }}</text>
    </view>
  </view>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { getImageRadiusStyle } from '@/utils/diy_adapter'
import { normalizeDisplayTags } from '@/utils/tag'

const categoryTabAnchorPrefix = `diy-product-category-${Date.now()}-${Math.floor(Math.random() * 100000)}`

defineOptions({
  name: 'DiyProduct'
})

const props = defineProps({
  config: {
    type: Object,
    default: () => ({})
  },
  data: {
    type: [Array, Object],
    default: () => []
  },
  loading: {
    type: Boolean,
    default: false
  },
  error: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['category-change', 'retry'])

const activeCategoryId = ref(0)
const categoryTabIntoView = ref('')

const isWithNav = computed(() => {
  return props.config.with_nav === true || Number(props.config.with_nav || 0) === 1 ||
    (props.data && props.data.mode === 'category_tabs')
})

const layout = computed(() => {
  return props.config.layout === 'list' ? 'list' : 'grid'
})

const tabStyle = computed(() => {
  const style = String(props.config.tab_style || 'subtitle').trim()
  return ['subtitle', 'arc', 'pill', 'image'].indexOf(style) > -1 ? style : 'subtitle'
})

const productTabsTrackClass = computed(() => {
  return ['product-tabs-track', `product-tabs-track--${tabStyle.value}`]
})

const categoryTabs = computed(() => {
  const categories = props.data && typeof props.data === 'object' && !Array.isArray(props.data) &&
    Array.isArray(props.data.categories)
    ? props.data.categories
    : (Array.isArray(props.config.category_tabs) ? props.config.category_tabs : [])

  return categories.map((item) => {
    const id = Number(item.id || 0)
    return {
      id,
      anchorId: buildTabAnchorId(categoryTabAnchorPrefix, id),
      name: item.name || `分类 #${id}`,
      subtitle: item.subtitle || '',
      image: item.image || ''
    }
  }).filter((item) => item.id > 0)
})

const currentCategoryId = computed(() => {
  const localId = Number(activeCategoryId.value || 0)
  if (categoryTabs.value.some((item) => item.id === localId)) {
    return localId
  }

  const dataId = Number((props.data && props.data.active_category_id) || 0)
  if (categoryTabs.value.some((item) => item.id === dataId)) {
    return dataId
  }

  return categoryTabs.value.length > 0 ? categoryTabs.value[0].id : 0
})

const isLoadMoreMode = computed(() => {
  return props.config.load_more === true || Number(props.config.load_more || 0) === 1
})

const productList = computed(() => {
  const source = Array.isArray(props.data)
    ? props.data
    : (props.data && Array.isArray(props.data.list) ? props.data.list : [])
  const limit = isLoadMoreMode.value ? source.length : Number(props.config.show_count || source.length || 6)
  return source.slice(0, limit).map((item, index) => mapProduct(item, index))
})

const componentStyle = computed(() => {
  const style = {}
  if (props.config.bg_color) {
    style.backgroundColor = props.config.bg_color
  }
  return style
})

const imageRadiusStyle = computed(() => {
  return getImageRadiusStyle(props.config.style_config)
})

const emptyText = computed(() => {
  if (props.loading) {
    return '商品加载中...'
  }

  if (props.error) {
    return `${props.error}，点击重试`
  }

  return '暂无商品'
})

const loadMoreStatus = computed(() => {
  if (!isLoadMoreMode.value || productList.value.length === 0) {
    return ''
  }

  const data = props.data && typeof props.data === 'object' ? props.data : {}
  if (data.loading_more) {
    return 'loading'
  }

  if (Object.prototype.hasOwnProperty.call(data, 'has_more')) {
    const hasMore = data.has_more === true || Number(data.has_more || 0) === 1
    if (!hasMore) {
      return 'finished'
    }
  }

  return 'ready'
})

const loadMoreText = computed(() => {
  if (loadMoreStatus.value === 'loading') {
    return '加载中...'
  }
  return loadMoreStatus.value === 'finished' ? '没有数据了' : ''
})

watch(categoryTabs, (tabs) => {
  if (tabs.length === 0) {
    activeCategoryId.value = 0
    return
  }

  if (!tabs.some((item) => item.id === activeCategoryId.value)) {
    activeCategoryId.value = tabs[0].id
  }
}, {
  immediate: true
})

watch(() => props.data && props.data.active_category_id, (value) => {
  const nextId = Number(value || 0)
  if (nextId > 0) {
    activeCategoryId.value = nextId
  }
})

function mapProduct(item = {}, index = 0) {
  const id = toSafeNumber(item.id, 0)
  const title = item.title || item.name || '商品'
  const priceValue = toSafeNumber(item.price, 0)
  const realSales = toSafeNumber(item.sales, 0)
  const virtualSales = toSafeNumber(item.virtual_sales, 0)
  const displaySales = toSafeNumber(
    item.display_sales !== undefined ? item.display_sales : (realSales + virtualSales),
    realSales + virtualSales
  )
  const priceText = String(item.price_text || '').trim() || `¥${priceValue.toFixed(2)}`
  const salesText = String(item.sales_text || '').trim() || `已售${formatCount(displaySales)}件`
  const memberPrice = toSafeNumber(item.member_price, 0)
  const memberPriceAvailable = item.member_price_available === true ||
    Number(item.member_price_available || 0) === 1
  const memberPriceText = String(item.member_price_text || '').trim() ||
    (memberPriceAvailable && memberPrice > 0 ? `¥${memberPrice.toFixed(2)}` : '')
  const displayTags = normalizeDisplayTags(item.tag_list, 1)
  const firstTag = displayTags.length > 0 ? displayTags[0] : null

  return {
    ...item,
    id,
    title,
    image: item.image || item.cover || '',
    priceText,
    salesText,
    memberPriceText,
    firstTagText: firstTag ? firstTag.name : '',
    firstTagStyle: firstTag ? buildInlineTagStyle(firstTag) : '',
    renderKey: `product-${id || index}-${index}`
  }
}

function toSafeNumber(value, fallback = 0) {
  const number = Number(value)
  return Number.isFinite(number) ? number : fallback
}

function formatCount(value) {
  const count = toSafeNumber(value, 0)
  return count >= 10000 ? `${(count / 10000).toFixed(1)}w` : `${count}`
}

function buildTabAnchorId(prefix, value) {
  return `${prefix}-${String(value || 0).replace(/[^A-Za-z0-9_-]/g, '-')}`
}

function buildInlineTagStyle(tag = {}) {
  const style = []
  const textColor = String(tag.text_color || '').trim()
  const bgColor = String(tag.bg_color || '').trim()
  const borderColor = String(tag.border_color || '').trim()

  if (textColor) {
    style.push(`color: ${textColor};`)
  }
  if (bgColor) {
    style.push(`background-color: ${bgColor};`)
  }
  if (borderColor) {
    style.push('border-style: solid;')
    style.push(`border-color: ${borderColor};`)
  }

  return style.join(' ')
}

function scrollCategoryTabIntoView(categoryId) {
  const anchorId = buildTabAnchorId(categoryTabAnchorPrefix, categoryId)
  if (!anchorId) {
    return
  }

  categoryTabIntoView.value = ''
  nextTick(() => {
    categoryTabIntoView.value = anchorId
  })
}

function handleCategoryChange(categoryId) {
  const nextCategoryId = Number(categoryId || 0)
  if (nextCategoryId <= 0) {
    return
  }

  activeCategoryId.value = nextCategoryId
  scrollCategoryTabIntoView(nextCategoryId)
  emit('category-change', nextCategoryId)
}

function handleProductClick(item = {}) {
  const id = Number(item.id || 0)
  if (id <= 0) {
    uni.showToast({
      title: '商品详情待接入',
      icon: 'none'
    })
    return
  }

  uni.navigateTo({
    url: `/pages/goods/detail/index?id=${id}`
  })
}

function handleCartClick(item = {}) {
  handleProductClick(item)
}

function handleRetryClick() {
  if (props.error) {
    emit('retry')
  }
}
</script>

<style lang="scss" scoped>
.diy-product {
  --product-theme-primary: var(--brand-primary, #ea580c);
  --product-theme-shadow: var(--brand-shadow, rgba(234, 88, 12, 0.18));
  width: auto;
  box-sizing: border-box;
  padding: 20rpx;
}

.product-header {
  padding-bottom: 18rpx;
}

.product-header__title {
  color: #1f1f1f;
  font-size: 32rpx;
  font-weight: 700;
  line-height: 44rpx;
}

.product-nav {
  padding-bottom: 20rpx;
}

.product-tabs-scroll {
  width: 100%;
  white-space: nowrap;
}

.product-tabs-track {
  display: flex;
  align-items: flex-end;
  min-height: 66rpx;
}

.product-tabs-track--image {
  align-items: flex-start;
  min-height: 142rpx;
}

.product-tabs-track--pill {
  align-items: center;
  min-height: 58rpx;
}

.product-tab {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  flex-shrink: 0;
  min-width: 120rpx;
  margin-right: 22rpx;
  color: #222222;
}

.product-tabs-track--image .product-tab {
  min-width: 128rpx;
}

.product-tabs-track--pill .product-tab {
  min-width: auto;
  padding: 8rpx 22rpx;
  border-radius: 999rpx;
  margin-right: 18rpx;
}

.product-tab--hover {
  opacity: 0.82;
}

.product-tab--active .product-tab__title {
  color: var(--product-theme-primary);
  font-weight: 700;
}

.product-tabs-track--pill .product-tab--active {
  background: var(--product-theme-primary);
}

.product-tabs-track--pill .product-tab--active .product-tab__title {
  color: #ffffff;
}

.product-tab__title {
  max-width: 176rpx;
  font-size: 30rpx;
  line-height: 42rpx;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.product-tab__subtitle {
  max-width: 176rpx;
  margin-top: 2rpx;
  color: #8b8b8b;
  font-size: 20rpx;
  line-height: 28rpx;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.product-tab__arc {
  width: 32rpx;
  height: 16rpx;
  margin-top: -3rpx;
  border: 6rpx solid var(--product-theme-primary);
  border-top: 0;
  border-radius: 0 0 32rpx 32rpx;
  box-sizing: border-box;
}

.product-tabs-track--arc .product-tab {
  padding-bottom: 10rpx;
}

.product-tab__image-wrap {
  width: 86rpx;
  height: 86rpx;
  margin-bottom: 8rpx;
  border-radius: 50%;
  overflow: hidden;
  background: #f4f6f8;
  border: 2rpx solid transparent;
}

.product-tab--active .product-tab__image-wrap {
  border-color: var(--product-theme-primary);
}

.product-tab__image,
.product-tab__image-placeholder {
  width: 100%;
  height: 100%;
}

.product-tab__image-placeholder {
  background: #eef3f7;
}

.product-empty {
  padding-top: 56rpx;
  padding-bottom: 56rpx;
  text-align: center;
}

.product-empty__title {
  color: #999999;
  font-size: 26rpx;
  line-height: 36rpx;
}

.product-list {
  width: 100%;
}

.product-grid {
  display: flex;
  flex-wrap: wrap;
  margin-left: -8rpx;
  margin-right: -8rpx;
}

.product-grid__item {
  width: 50%;
  box-sizing: border-box;
  padding-left: 8rpx;
  padding-right: 8rpx;
  padding-bottom: 16rpx;
}

.product-card {
  position: relative;
  overflow: hidden;
  background: #ffffff;
}

.product-card--hover {
  opacity: 0.92;
}

.product-card--list {
  display: flex;
  min-height: 188rpx;
  margin-bottom: 18rpx;
  border-radius: 18rpx;
}

.product-card--grid {
  border-radius: 18rpx;
}

.product-card__cover {
  position: relative;
  flex-shrink: 0;
  overflow: hidden;
  background: #f3f5f7;
}

.product-card__cover--list {
  width: 188rpx;
  height: 188rpx;
}

.product-card__cover--grid {
  width: 100%;
  height: 322rpx;
}

.product-card__image,
.product-card__placeholder {
  width: 100%;
  height: 100%;
}

.product-card__placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  color: #b6bdc6;
  font-size: 24rpx;
}

.product-card__body {
  position: relative;
  display: flex;
  flex-direction: column;
  box-sizing: border-box;
  min-width: 0;
}

.product-card__body--list {
  flex: 1;
  padding: 12rpx 12rpx 10rpx 18rpx;
}

.product-card__body--grid {
  padding: 14rpx 14rpx 16rpx;
}

.product-card__title {
  display: -webkit-box;
  color: #1f1f1f;
  font-size: 26rpx;
  font-weight: 600;
  line-height: 36rpx;
  overflow: hidden;
  text-overflow: ellipsis;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.product-card__title-badge {
  display: inline-block;
  max-width: 92rpx;
  height: 32rpx;
  margin-right: 8rpx;
  padding: 0 10rpx;
  border-width: 1rpx;
  border-style: none;
  border-color: transparent;
  border-radius: 6rpx;
  box-sizing: border-box;
  color: #ffffff;
  background: var(--product-theme-primary);
  font-size: 20rpx;
  line-height: 32rpx;
  font-weight: 700;
  overflow: hidden;
  text-overflow: ellipsis;
  vertical-align: middle;
  white-space: nowrap;
}

.product-card__title--list {
  height: 72rpx;
}

.product-card__title--grid {
  height: 72rpx;
}

.product-card__price-row,
.product-card__meta {
  display: flex;
  align-items: center;
}

.product-card__price-row {
  justify-content: flex-start;
  min-width: 0;
  min-height: 42rpx;
  margin-top: 12rpx;
}

.product-card__meta {
  justify-content: space-between;
  min-height: 54rpx;
  margin-top: 8rpx;
}

.product-card__price-wrap {
  display: flex;
  align-items: center;
  min-width: 0;
}

.product-card__vip {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  min-width: 0;
  margin-left: 14rpx;
  white-space: nowrap;
}

.product-card__vip--grid {
  height: 34rpx;
  padding: 0;
  border-radius: 0;
  color: var(--brand-primary, #ea580c);
  background: transparent;
}

.vip-price-icon {
  display: block;
  flex-shrink: 0;
  width: $vip-price-icon-width;
  height: $vip-price-icon-height;
  margin-right: $vip-price-icon-margin;
}

.product-card__vip-price {
  flex-shrink: 0;
  color: $vip-price-color;
  font-size: $vip-price-font-size;
  line-height: $vip-price-line-height;
  font-weight: $vip-price-font-weight;
  white-space: nowrap;
}

.product-card__price {
  color: var(--product-theme-primary);
  font-size: 34rpx;
  font-weight: 700;
  line-height: 42rpx;
}

.product-card__sales {
  flex-shrink: 0;
  color: #8b8b8b;
  font-size: 22rpx;
  line-height: 30rpx;
}

.product-card__cart {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 54rpx;
  height: 54rpx;
  color: #ffffff;
  font-size: 28rpx;
  border-radius: 50%;
  background: var(--product-theme-primary);
}

.product-card__cart--hover {
  opacity: 0.86;
}

.product-card__cart-icon {
  display: block;
  color: #ffffff;
  font-size: 34rpx;
  line-height: 34rpx;
}

.product-load-more {
  padding-top: 12rpx;
  padding-bottom: 24rpx;
  color: #999999;
  font-size: 24rpx;
  line-height: 34rpx;
  text-align: center;
}
</style>
