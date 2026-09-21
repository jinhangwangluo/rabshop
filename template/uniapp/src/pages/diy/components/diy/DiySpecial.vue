<template>
  <view class="diy-special" :style="componentStyle">
    <view v-if="!isCategoryTabsStyle && (config.title || showMore)" class="special-header">
      <view class="special-header__main">
        <text v-if="config.title" class="special-title">{{ config.title }}</text>
        <text v-if="config.subtitle" class="special-subtitle">{{ config.subtitle }}</text>
      </view>
      <view v-if="showMore" class="special-more" hover-class="special-more--hover" @click="handleMoreClick">
        <text>{{ config.more_text || '查看全部' }}</text>
        <uni-icons class="special-more__icon" type="right" size="13" color="#999999"></uni-icons>
      </view>
    </view>

    <view v-if="isCategoryTabsStyle" class="special-category-tabs">
      <scroll-view v-if="categoryTabs.length > 0" class="category-tabs-scroll" scroll-x :show-scrollbar="false">
        <view class="category-tabs-track">
          <view
            v-for="item in categoryTabs"
            :key="item.id"
            class="category-tab"
            :class="{ 'category-tab--active': item.id === currentCategoryId }"
            hover-class="category-tab--hover"
            @click="handleCategoryChange(item.id)"
          >
            <view class="category-tab__cover">
              <image
                v-if="item.image"
                class="category-tab__image"
                :src="item.image"
                mode="aspectFill"
              />
              <view v-else class="category-tab__placeholder">
                <text>{{ item.name }}</text>
              </view>
            </view>
            <text class="category-tab__name">{{ item.name }}</text>
            <view class="category-tab__line"></view>
          </view>
        </view>
      </scroll-view>

      <view v-if="categorySpecialList.length === 0" class="special-empty" @click="handleRetryClick">
        <text class="special-empty__title">{{ emptyText }}</text>
      </view>

      <view v-else class="category-card-list">
        <view
          v-for="item in categorySpecialList"
          :key="item.renderKey"
          class="category-card-item"
          hover-class="category-card-item--hover"
          @click="handleClick(item)"
        >
          <view class="category-card">
            <view class="category-card-cover" :style="imageRadiusStyle">
              <image
                v-if="item.image"
                class="category-card-cover__img"
                :src="item.image"
                mode="aspectFill"
              />
              <view v-else class="special-cover-placeholder">
                <text>{{ item.title }}</text>
              </view>
              <view v-if="item.mediaTag" class="category-card-media">
                <text>{{ item.mediaTag }}</text>
              </view>
              <view v-if="item.isVideo" class="category-card-play">
                <text class="category-card-play__icon"></text>
              </view>
            </view>
            <view class="category-card-body">
              <view class="category-card-title">{{ item.title }}</view>
              <view v-if="showDesc && item.subtitle" class="category-card-desc">{{ item.subtitle }}</view>
              <view v-if="showCountInfo" class="category-card-sales">{{ item.saleText }}</view>
              <view v-if="item.tagList.length" class="category-card-tags">
                <tag-preview
                  v-for="tag in item.tagList"
                  :key="item.renderKey + '-' + tag.renderKey"
                  :tag="tag"

                />
              </view>
              <view class="category-card-footer">
                <view class="category-price">
                  <text class="category-price__main">{{ item.priceText }}</text>
                  <view v-if="item.memberPriceText" class="category-price__vip">
                    <image class="vip-price-icon" src="/static/images/vip.png" mode="aspectFill" />
                    <text class="category-price__vip-amount">{{ item.memberPriceText }}</text>
                  </view>
                </view>
                <view class="category-card-cart">
                  <uni-icons type="cart" size="20" color="#111111"></uni-icons>
                </view>
              </view>
            </view>
          </view>
        </view>
      </view>
    </view>

    <view v-else-if="specialList.length === 0" class="special-empty" @click="handleRetryClick">
      <text class="special-empty__title">{{ emptyText }}</text>
    </view>

    <scroll-view v-else-if="displayStyle === 'scroll'" class="special-scroll" scroll-x :show-scrollbar="false">
      <view class="special-track">
        <view
          v-for="item in specialList"
          :key="item.renderKey"
          class="special-scroll-card"
          hover-class="special-scroll-card--hover"
          @click="handleClick(item)"
        >
          <view class="special-scroll-cover" :style="imageRadiusStyle">
            <image
              v-if="item.image"
              class="special-cover__img"
              :src="item.image"
              mode="aspectFill"
            />
            <view v-else class="special-cover-placeholder">
              <text>{{ item.title }}</text>
            </view>
            <view v-if="showKeywords && item.tagList.length" class="special-hot-tag">
              <tag-preview :tag="item.tagList[0]"  />
            </view>
          </view>
          <view class="special-card-body">
            <text class="special-card-title">{{ item.title }}</text>
            <text v-if="showDesc && item.subtitle" class="special-card-desc">{{ item.subtitle }}</text>
            <view v-if="showCountInfo" class="special-row-footer">
              <text>{{ item.countText }}</text>
              <text class="special-price">{{ item.priceText }}</text>
            </view>
          </view>
        </view>
      </view>
    </scroll-view>

    <view v-else-if="displayStyle === 'card'" class="special-card-list">
      <view
        v-for="item in specialList"
        :key="item.renderKey"
        class="special-card-item"
        hover-class="special-card-item--hover"
        @click="handleClick(item)"
      >
        <view class="special-grid-card">
          <view class="special-grid-cover" :style="imageRadiusStyle">
            <image
              v-if="item.image"
              class="special-cover__img"
              :src="item.image"
              mode="aspectFill"
            />
            <view v-else class="special-cover-placeholder">
              <text>{{ item.title }}</text>
            </view>
          </view>
          <view class="special-card-body">
            <view v-if="showKeywords && item.tagList.length" class="special-keywords">
              <tag-preview
                v-for="tag in item.tagList"
                :key="item.renderKey + '-' + tag.renderKey"
                :tag="tag"

              />
            </view>
            <text class="special-card-title">{{ item.title }}</text>
            <text v-if="showDesc && item.subtitle" class="special-card-desc">{{ item.subtitle }}</text>
            <view v-if="showCountInfo" class="special-card-footer">
              <text>{{ item.countText }}</text>
              <text class="special-price">{{ item.priceText }}</text>
            </view>
          </view>
        </view>
      </view>
    </view>

    <view v-else class="special-hero-list">
      <view
        v-for="item in specialList"
        :key="item.renderKey"
        class="special-hero-card"
        hover-class="special-hero-card--hover"
        @click="handleClick(item)"
      >
        <view class="special-hero-cover" :style="imageRadiusStyle">
          <image
            v-if="item.image"
            class="special-cover__img"
            :src="item.image"
            mode="aspectFill"
          />
          <view v-else class="special-cover-placeholder special-cover-placeholder--dark">
            <text>{{ item.title }}</text>
          </view>
        </view>
        <view class="special-hero-mask"></view>
        <view v-if="showKeywords && item.tagList.length" class="special-hot-tag">
          <tag-preview :tag="item.tagList[0]"  />
        </view>
        <view class="special-hero-info">
          <text class="special-hero-title">{{ item.title }}</text>
          <text v-if="showDesc && item.subtitle" class="special-hero-desc">{{ item.subtitle }}</text>
        </view>
        <view class="special-action-bar">
          <view class="special-action-price-wrap">
            <text class="special-action-price">{{ item.priceText }}</text>
            <view v-if="item.memberPriceText" class="special-action-price__vip">
              <image class="vip-price-icon" src="/static/images/vip.png" mode="aspectFill" />
              <text class="special-action-price__vip-amount">{{ item.memberPriceText }}</text>
            </view>
          </view>
          <view class="special-action-btn">
            <text>立即查看</text>
          </view>
        </view>
      </view>
    </view>

    <view v-if="loadMoreText" class="special-load-more">
      <text>{{ loadMoreText }}</text>
    </view>
  </view>
</template>

<script setup>
import { computed, ref } from 'vue'
import { navigateByLink } from '@/utils/link'
import { getImageRadiusStyle } from '@/utils/diy_adapter'
import { normalizeDisplayTags } from '@/utils/tag'

defineOptions({
  name: 'DiySpecial'
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

const specialList = computed(() => {
  const source = Array.isArray(props.data)
    ? props.data
    : (props.data && Array.isArray(props.data.list) ? props.data.list : [])
  const limit = isLoadMoreMode.value ? source.length : Number(props.config.show_count || source.length ||
    6)
  return source.slice(0, limit).map((item, index) => mapItem(item, index))
})

const isLoadMoreMode = computed(() => {
  return !isCategoryTabsStyle.value &&
    (props.config.load_more === true || Number(props.config.load_more || 0) === 1)
})

const displayStyle = computed(() => {
  const style = String(props.config.display_style || props.config.style || 'scroll').trim()
  if (style === 'category_tabs') return 'category_tabs'
  if (style === 'card') return 'card'
  if (style === 'row') return 'row'
  return 'scroll'
})

const isCategoryTabsStyle = computed(() => {
  return displayStyle.value === 'category_tabs' || (props.data && props.data.mode === 'category_tabs')
})

const categoryTabs = computed(() => {
  if (props.data && typeof props.data === 'object' && !Array.isArray(props.data)) {
    const categories = Array.isArray(props.data.categories) ? props.data.categories : []
    if (categories.length > 0) {
      return categories.map(item => ({
        id: Number(item.id || 0),
        name: item.name || item.label || '分类',
        image: item.image || '',
        items: []
      })).filter(item => item.id > 0)
    }
  }

  const tabs = normalizeConfigTabs()
  if (tabs.length === 0) {
    return []
  }

  return tabs.map((item) => ({
    ...item,
    items: []
  }))
})

const currentCategoryId = computed(() => {
  const exists = categoryTabs.value.some(item => item.id === activeCategoryId.value)
  if (exists) {
    return activeCategoryId.value
  }
  return categoryTabs.value.length > 0 ? categoryTabs.value[0].id : 0
})

const activeCategoryItems = computed(() => {
  const source = Array.isArray(props.data)
    ? props.data
    : (props.data && Array.isArray(props.data.list) ? props.data.list : [])
  const limit = Number(props.config.show_count || source.length || 4)
  return source.slice(0, limit)
})

const categorySpecialList = computed(() => {
  return activeCategoryItems.value.map((item, index) => mapItem(item, index))
})

const showDesc = computed(() => {
  return props.config.show_desc !== false
})

const showKeywords = computed(() => {
  return props.config.show_keywords === true
})

const showCountInfo = computed(() => {
  return props.config.show_count_info !== false
})

const showMore = computed(() => {
  return !isCategoryTabsStyle.value && props.config.show_more !== false
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
    return '专题加载中...'
  }

  if (props.error) {
    return `${props.error}，点击重试`
  }

  return '暂无专题数据'
})

const loadMoreStatus = computed(() => {
  if (!isLoadMoreMode.value || specialList.value.length === 0) {
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

  const totalPage = Number(data.totalPage || data.total_page || 0)
  if (totalPage > 0 && Number(data.page || 1) >= totalPage) {
    return 'finished'
  }

  return 'ready'
})

const loadMoreText = computed(() => {
  if (loadMoreStatus.value === 'loading') {
    return '加载中...'
  }
  return loadMoreStatus.value === 'finished' ? '没有数据了' : ''
})

function normalizeConfigTabs() {
  const rawTabs = Array.isArray(props.config.category_tabs) && props.config.category_tabs.length > 0
    ? props.config.category_tabs
    : props.config.category_ids
  let tabs = rawTabs
  if (typeof tabs === 'string') {
    tabs = tabs.split(',')
  }
  if (!Array.isArray(tabs)) {
    tabs = [tabs]
  }
  return tabs.map(item => {
    if (item && typeof item === 'object') {
      const id = Number(item.id || item.value || 0)
      return {
        id,
        name: item.name || item.label || `分类 #${id}`,
        image: item.image || '',
        items: []
      }
    }
    const id = Number(item || 0)
    return {
      id,
      name: `分类 #${id}`,
      image: '',
      items: []
    }
  }).filter(item => item.id > 0)
}

function handleCategoryChange(categoryId) {
  activeCategoryId.value = Number(categoryId || 0)
  emit('category-change', activeCategoryId.value)
}

function mapItem(item = {}, index = 0) {
  const tagList = parseTags(item)
  const priceValue = Number(item.price || item.money || item.pay_price || 0)
  const originalPrice = Number(item.original_price || item.market_price || 0)
  const memberPrice = Number(item.member_price || item.vip_price || 0)
  const memberPriceAvailable = item.member_price_available === true ||
    Number(item.member_price_available || 0) === 1
  const countValue = Number(item.course_count || item.content_count || item.article_count || 0)
  const buyCount = Number(item.buy_count || item.sales || item.sale_count || 0)
  const contentType = String(item.content_type || item.media_type || '').toLowerCase()
  const mediaTag = item.media_tag || item.content_type_text || item.media_type_text || ''
  const isVideo = ['2', '3', 'video', 'audio'].indexOf(contentType) > -1
  const isPurchased = item.is_purchased === true || Number(item.is_purchased || item.has_buy || item.is_bought || item.is_subscribed || 0) > 0
  return {
    ...item,
    renderKey: `${item.id || item.special_id || item.topic_id || item.title || 'special'}_${index}`,
    title: item.title || item.name || '专题标题',
    image: item.cover || item.image || '',
    subtitle: item.description || item.intro || item.subtitle || '',
    tagList,
    countText: countValue > 0 ? `${countValue} 课` : '专题',
    saleText: buyCount > 0 ? `已售 ${formatWanCount(buyCount)}` : (countValue > 0 ? `${countValue} 节内容` : '精选专题'),
    isPurchased,
    priceText: isPurchased ? '已购买' : (priceValue > 0 ? `¥${formatPrice(priceValue)}` : '免费'),
    originalPriceText: !isPurchased && originalPrice > priceValue && priceValue > 0 ? `¥${formatPrice(originalPrice)}` : '',
    memberPriceText: !isPurchased && memberPriceAvailable && memberPrice > 0 ? `¥${formatPrice(memberPrice)}` : '',
    mediaTag,
    isVideo
  }
}

function parseTags(item = {}) {
  return normalizeDisplayTags(item.tag_list, 3)
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

function buildSpecialListUrl(keyword = '') {
  const normalizedKeyword = String(keyword || '').trim()
  return normalizedKeyword
    ? `/pages/contents/special/index?keyword=${encodeURIComponent(normalizedKeyword)}`
    : '/pages/contents/special/index'
}

function buildSpecialDetailUrl(item = {}) {
  const topicId = Number(item.id || item.special_id || item.topic_id || 0)
  if (topicId <= 0) {
    return ''
  }

  const detailState = item.is_purchased === true || Number(item.is_purchased || item.is_subscribed || item.has_buy || item.is_bought || 0) > 0 ? 'learning' : 'purchase'
  return `/pages/contents/special/detail/index?id=${topicId}&state=${detailState}`
}

function handleClick(item = {}) {
  if (navigateByLink(item.link_url)) {
    return
  }

  const detailUrl = buildSpecialDetailUrl(item)
  if (detailUrl) {
    uni.navigateTo({ url: detailUrl })
    return
  }

  const keyword = String(item.title || item.name || '').trim()
  if (keyword) {
    uni.navigateTo({ url: buildSpecialListUrl(keyword) })
    return
  }

  uni.showToast({ title: '专题详情待接入', icon: 'none' })
}

function handleMoreClick() {
  if (navigateByLink(props.config.more_link_url, {
    copyTip: '小程序暂不支持直接打开外链，链接已复制'
  })) {
    return
  }

  uni.navigateTo({ url: buildSpecialListUrl() })
}

function handleRetryClick() {
  if (props.error) {
    emit('retry')
  }
}
</script>

<style lang="scss" scoped>
.diy-special {
  width: auto;
  box-sizing: border-box;
}

.special-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16rpx 20rpx 18rpx;
}

.special-header__main {
  flex: 1;
  min-width: 0;
}

.special-title {
  display: block;
  font-size: 32rpx;
  line-height: 44rpx;
  font-weight: 600;
  color: #333333;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.special-subtitle {
  display: block;
  margin-top: 6rpx;
  font-size: 22rpx;
  line-height: 32rpx;
  color: #777777;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.special-more {
  display: flex;
  align-items: center;
  flex-shrink: 0;
  margin-left: 20rpx;
  color: #999999;
  font-size: 24rpx;
  line-height: 34rpx;
}

.special-more--hover {
  opacity: 0.86;
}

.special-more__icon {
  margin-left: 6rpx;
}

.special-empty {
  padding-top: 48rpx;
  padding-bottom: 48rpx;
  text-align: center;
}

.special-empty__title {
  font-size: 24rpx;
  color: #999999;
}

.special-category-tabs {
  padding-top: 18rpx;
  background: #fffdf9;
}

.category-tabs-scroll {
  width: 100%;
  overflow: hidden;
  white-space: nowrap;
}

/* #ifdef H5 */
.category-tabs-scroll {
  scrollbar-width: none;
}

.category-tabs-scroll::-webkit-scrollbar {
  display: none;
  width: 0;
  height: 0;
}

.category-tabs-scroll ::v-deep .uni-scroll-view,
.category-tabs-scroll ::v-deep .uni-scroll-view-content {
  overflow-x: auto !important;
  overflow-y: hidden !important;
  scrollbar-width: none !important;
  -ms-overflow-style: none !important;
}

.category-tabs-scroll ::v-deep .uni-scroll-view::-webkit-scrollbar,
.category-tabs-scroll ::v-deep .uni-scroll-view-content::-webkit-scrollbar {
  display: none !important;
  width: 0 !important;
  height: 0 !important;
  background: transparent !important;
}
/* #endif */

.category-tabs-track {
  display: flex;
  align-items: center;
  padding-left: 28rpx;
  padding-right: 28rpx;
  padding-bottom: 22rpx;
}

.category-tab {
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-start;
  width: 132rpx;
  height: 184rpx;
  margin-right: 16rpx;
  padding: 16rpx 12rpx 14rpx;
  border: 2rpx solid #f0e6dc;
  border-radius: 28rpx;
  background: #ffffff;
  color: #2b2b2b;
  font-size: 24rpx;
  font-weight: 700;
  line-height: 34rpx;
  box-shadow: 0 10rpx 28rpx rgba(87, 61, 40, 0.05);
  box-sizing: border-box;
}

.category-tab--active {
  border-color: #ff7a2f;
  background: #fffaf5;
  color: #ed4a1a;
  box-shadow: 0 12rpx 32rpx rgba(255, 100, 32, 0.12);
}

.category-tab--hover {
  opacity: 0.88;
}

.category-tab__cover {
  width: 96rpx;
  height: 96rpx;
  overflow: hidden;
  border-radius: 26rpx;
  background: #f5efe8;
}

.category-tab__image {
  width: 100%;
  height: 100%;
}

.category-tab__placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  color: #c8b8a8;
  font-size: 20rpx;
  font-weight: 700;
  text-align: center;
}

.category-tab__name {
  display: block;
  width: 100%;
  height: 34rpx;
  margin-top: 16rpx;
  overflow: hidden;
  text-align: center;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.category-tab__line {
  width: 28rpx;
  height: 6rpx;
  margin-top: 8rpx;
  border-radius: 6rpx;
  background: transparent;
}

.category-tab--active .category-tab__line {
  background: var(--brand-primary, #ea580c);
}

.category-card-list {
  display: flex;
  flex-wrap: wrap;
  padding-left: 18rpx;
  padding-right: 18rpx;
  padding-bottom: 12rpx;
}

.category-card-item {
  width: 50%;
  padding-left: 10rpx;
  padding-right: 10rpx;
  padding-bottom: 18rpx;
  box-sizing: border-box;
}

.category-card {
  overflow: hidden;
  border-radius: 28rpx;
  background: #ffffff;
  box-shadow: 0 16rpx 44rpx rgba(38, 20, 8, 0.08);
}

.category-card-item--hover {
  opacity: 0.9;
}

.category-card-cover {
  position: relative;
  width: 100%;
  height: 188rpx;
  overflow: hidden;
  background: #f3eee8;
}

.category-card-cover__img {
  width: 100%;
  height: 100%;
}

.category-card-body {
  padding: 14rpx 16rpx 16rpx;
  box-sizing: border-box;
}

.category-card-title {
  display: block;
  height: 40rpx;
  color: #1f1f1f;
  font-size: 28rpx;
  line-height: 40rpx;
  font-weight: 800;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.category-card-desc {
  display: block;
  height: 56rpx;
  margin-top: 6rpx;
  color: #55504c;
  font-size: 22rpx;
  line-height: 28rpx;
  overflow: hidden;
  text-overflow: ellipsis;
  word-break: break-all;
}

.category-card-sales {
  display: block;
  margin-top: 8rpx;
  color: #766f68;
  font-size: 20rpx;
  line-height: 28rpx;
}

.category-card-tags {
  display: flex;
  flex-wrap: nowrap;
  align-items: center;
  height: 40rpx;
  margin-top: 8rpx;
  overflow: hidden;
}

.category-card-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 10rpx;
}

.category-price {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  flex-wrap: nowrap;
}

.category-price__main {
  display: block;
  flex-shrink: 0;
  margin-right: 8rpx;
  color: #f04418;
  font-size: 28rpx;
  font-weight: 800;
  line-height: 36rpx;
}

.category-price__vip {
  min-width: 0;
  display: flex;
  align-items: center;
  margin-top: 0;
  overflow: hidden;
  white-space: nowrap;
}

.vip-price-icon {
  display: block;
  flex-shrink: 0;
  width: $vip-price-icon-width;
  height: $vip-price-icon-height;
  margin-right: $vip-price-icon-margin;
}

.category-price__vip-amount {
  display: block;
  min-width: 0;
  color: $vip-price-color;
  font-size: $vip-price-font-size;
  line-height: $vip-price-line-height;
  font-weight: $vip-price-font-weight;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.category-card-cart {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 58rpx;
  height: 58rpx;
  border: 2rpx solid #efe4db;
  border-radius: 50%;
  background: #ffffff;
  box-shadow: 0 10rpx 28rpx rgba(37, 22, 9, 0.06);
}

.special-category-tabs .special-empty {
  padding-top: 20rpx;
  padding-bottom: 24rpx;
}

.category-card-media {
  position: absolute;
  left: 14rpx;
  top: 14rpx;
  height: 40rpx;
  padding-left: 14rpx;
  padding-right: 14rpx;
  border-radius: 20rpx;
  background: rgba(37, 37, 37, 0.78);
  color: #ffffff;
  font-size: 20rpx;
  line-height: 40rpx;
}

.category-card-play {
  position: absolute;
  left: 50%;
  top: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 68rpx;
  height: 68rpx;
  margin-left: -34rpx;
  margin-top: -34rpx;
  border: 6rpx solid #ffffff;
  border-radius: 50%;
  box-sizing: border-box;
}

.category-card-play__icon {
  width: 0;
  height: 0;
  margin-left: 6rpx;
  border-top: 12rpx solid transparent;
  border-bottom: 12rpx solid transparent;
  border-left: 18rpx solid #ffffff;
}

.special-scroll {
  width: 100%;
  overflow: hidden;
  white-space: nowrap;
}

/* #ifdef H5 */
.special-scroll {
  scrollbar-width: none;
}

.special-scroll::-webkit-scrollbar {
  display: none;
  width: 0;
  height: 0;
}

.special-scroll ::v-deep .uni-scroll-view,
.special-scroll ::v-deep .uni-scroll-view-content {
  overflow-x: auto !important;
  overflow-y: hidden !important;
  scrollbar-width: none !important;
  -ms-overflow-style: none !important;
}

.special-scroll ::v-deep .uni-scroll-view::-webkit-scrollbar,
.special-scroll ::v-deep .uni-scroll-view-content::-webkit-scrollbar {
  display: none !important;
  width: 0 !important;
  height: 0 !important;
  background: transparent !important;
}
/* #endif */

.special-track {
  display: flex;
  padding-left: 20rpx;
  padding-right: 20rpx;
}

.special-scroll-card {
  width: 540rpx;
  flex-shrink: 0;
  margin-right: 20rpx;
  overflow: hidden;
  background: #ffffff;
}

.special-scroll-card:last-child {
  margin-right: 0;
}

.special-scroll-card--hover,
.special-card-item--hover,
.special-row-card--hover {
  opacity: 0.9;
}

.special-scroll-cover {
  position: relative;
  width: 540rpx;
  height: 300rpx;
  overflow: hidden;
  background: #f3f3f3;
}

.special-hero-list {
  padding: 0 20rpx 20rpx;
}

.special-hero-card {
  position: relative;
  height: 550rpx;
  margin-bottom: 20rpx;
  overflow: hidden;
  border-radius: 16rpx;
  background: #2f3131;
  box-shadow: 0 4rpx 18rpx rgba(0, 0, 0, 0.08);
}

.special-hero-card:last-child {
  margin-bottom: 0;
}

.special-hero-card--hover {
  opacity: 0.94;
}

.special-hero-cover {
  width: 100%;
  height: 100%;
  overflow: hidden;
  background: #2f3131;
}

.special-hero-mask {
  position: absolute;
  left: 0;
  right: 0;
  top: 0;
  bottom: 0;
  background: linear-gradient(180deg, rgba(0, 0, 0, 0.04), rgba(0, 0, 0, 0.68));
}

.special-hero-info {
  position: absolute;
  left: 18rpx;
  right: 18rpx;
  bottom: 82rpx;
  z-index: 2;
}

.special-hero-title {
  display: block;
  color: #ffffff;
  font-size: 32rpx;
  line-height: 44rpx;
  font-weight: 700;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.special-hero-desc {
  display: -webkit-box;
  height: 72rpx;
  margin-top: 8rpx;
  color: rgba(255, 255, 255, 0.9);
  font-size: 22rpx;
  line-height: 34rpx;
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
  height: 64rpx;
  padding: 0 18rpx;
  background: rgba(0, 0, 0, 0.34);
  box-sizing: border-box;
}

.special-action-price-wrap {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  overflow: hidden;
  white-space: nowrap;
}

.special-action-price {
  display: block;
  flex-shrink: 0;
  margin-right: 8rpx;
  color: #ffffff;
  font-size: 28rpx;
  font-weight: 700;
  line-height: 36rpx;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.special-action-price__vip {
  min-width: 0;
  display: flex;
  align-items: center;
  overflow: hidden;
  white-space: nowrap;
}

.special-action-price__vip-amount {
  display: block;
  min-width: 0;
  color: $vip-price-color;
  font-size: $vip-price-font-size;
  line-height: $vip-price-line-height;
  font-weight: $vip-price-font-weight;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.special-action-btn {
  flex-shrink: 0;
  height: 34rpx;
  padding: 0 16rpx;
  border-radius: 8rpx;
  background: var(--brand-primary, #ea580c);
  color: #ffffff;
  font-size: 22rpx;
  font-weight: 700;
  line-height: 34rpx;
}

.special-card-list {
  display: flex;
  flex-wrap: wrap;
  padding-left: 10rpx;
  padding-right: 10rpx;
}

.special-card-item {
  width: 50%;
  padding-left: 10rpx;
  padding-right: 10rpx;
  padding-bottom: 20rpx;
  box-sizing: border-box;
}

.special-grid-card {
  overflow: hidden;
  background: #ffffff;
}

.special-grid-cover {
  position: relative;
  width: 100%;
  height: 220rpx;
  overflow: hidden;
  background: #f3f3f3;
}

.special-cover__img {
  width: 100%;
  height: 100%;
}

.special-cover-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  color: #c6c6c6;
  background: #f3f3f3;
  font-size: 24rpx;
  font-weight: 700;
}

.special-hot-tag {
  position: absolute;
  left: 16rpx;
  top: 16rpx;
  z-index: 2;
}

.special-card-body {
  padding: 18rpx;
  box-sizing: border-box;
}

.special-card-title {
  display: block;
  color: #1a1c1c;
  font-size: 28rpx;
  line-height: 40rpx;
  font-weight: 700;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.special-card-desc {
  display: block;
  height: 68rpx;
  margin-top: 8rpx;
  color: #4c4546;
  font-size: 22rpx;
  line-height: 34rpx;
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.special-keywords {
  display: flex;
  flex-wrap: wrap;
  margin-top: 12rpx;
}

.special-card-footer,
.special-row-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-width: 0;
  margin-top: 14rpx;
  color: #848484;
  font-size: 20rpx;
  line-height: 30rpx;
}

.special-load-more {
  padding-top: 18rpx;
  padding-bottom: 18rpx;
  text-align: center;
  color: #999999;
  font-size: 22rpx;
}
</style>

<style lang="scss">
/* #ifdef H5 */
.category-tabs-scroll,
.special-scroll,
.category-tabs-scroll .uni-scroll-view,
.special-scroll .uni-scroll-view,
.category-tabs-scroll .uni-scroll-view-content,
.special-scroll .uni-scroll-view-content,
.category-tabs-scroll > div,
.special-scroll > div {
  scrollbar-width: none !important;
  -ms-overflow-style: none !important;
}

.category-tabs-scroll::-webkit-scrollbar,
.special-scroll::-webkit-scrollbar,
.category-tabs-scroll .uni-scroll-view::-webkit-scrollbar,
.special-scroll .uni-scroll-view::-webkit-scrollbar,
.category-tabs-scroll .uni-scroll-view-content::-webkit-scrollbar,
.special-scroll .uni-scroll-view-content::-webkit-scrollbar,
.category-tabs-scroll > div::-webkit-scrollbar,
.special-scroll > div::-webkit-scrollbar {
  display: none !important;
  width: 0 !important;
  height: 0 !important;
  background: transparent !important;
}
/* #endif */
</style>
