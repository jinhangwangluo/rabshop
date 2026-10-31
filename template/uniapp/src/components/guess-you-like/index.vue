<template>
  <view v-if="shouldRender" class="guess-you-like">
    <view class="section-header section-header--recommend">
      <view class="section-title-wrap section-title-wrap--recommend">
        <image
          class="section-title-bg"
          src="/static/images/guess-you-like-title-bg.png"
          mode="aspectFit"
        />
        <text class="section-title section-title--recommend">{{ title }}</text>
      </view>
      <text
        v-if="showMore"
        class="section-more section-more--recommend"
        @click="handleMoreClick"
      >{{ moreText }}</text>
    </view>

    <view v-if="loading" class="guess-you-like__loading">
      <six-petal-spiral
        :active="true"
        :size="92"
        text=""
        color="#facc15"
        :stroke-width="1.4"
        :particle-count="36"
        :trail-span="0.28"
      />
      <text class="guess-you-like__loading-text">{{ isProductMode ? '商品加载中...' : '专题加载中...' }}</text>
    </view>

    <view v-else-if="displayRecommendList.length" class="product-grid">
      <view
        v-for="item in displayRecommendList"
        :key="item.id"
        class="product-card"
        @click="handleRecommendClick(item)"
      >
        <view class="product-card__inner">
          <image class="product-image" :src="item.cover" mode="aspectFill" />
          <view class="product-info">
            <text class="product-name">{{ item.title }}</text>
            <text v-if="item.description" class="product-desc">{{ item.description }}</text>
            <view v-if="item.tags.length" class="product-tags">
              <text
                v-for="tag in item.tags"
                :key="tag.key"
                class="product-tag"
                :class="tag.className"
              >{{ tag.text }}</text>
            </view>
            <view class="product-price">
              <text class="price-value">{{ item.priceText }}</text>
              <text v-if="item.originalPriceText" class="origin-price-value">{{ item.originalPriceText }}</text>
            </view>
          </view>
        </view>
      </view>
    </view>

    <view v-else-if="!hideWhenEmpty" class="guess-you-like__empty">
      <view class="guess-you-like__empty-icon">
        <uni-icons type="info" size="28" color="#d4d4d8"></uni-icons>
      </view>
      <text class="guess-you-like__empty-title">{{ isProductMode ? '暂无推荐商品' : '暂无推荐专题' }}</text>
      <text class="guess-you-like__empty-desc">
        {{ isProductMode ? '暂时没有匹配的商品，去商品列表看看更多商品。' : '暂时没有匹配的专题，去专题广场看看更多内容。' }}
      </text>
      <view class="guess-you-like__empty-button" hover-class="guess-you-like__empty-button--hover" @click="handleMoreClick">
        {{ isProductMode ? '浏览商品' : '浏览专题' }}
      </view>
    </view>
  </view>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { getProductList } from '@/api/product'
import { getSpecialRecommendList } from '@/api/special'

defineOptions({
  name: 'GuessYouLike'
})

const props = defineProps({
  title: {
    type: String,
    default: '猜你喜欢'
  },
  moreText: {
    type: String,
    default: '更多'
  },
  showMore: {
    type: Boolean,
    default: true
  },
  limit: {
    type: Number,
    default: 4
  },
  excludeId: {
    type: Number,
    default: 0
  },
  recommendType: {
    type: String,
    default: 'special'
  },
  hideWhenEmpty: {
    type: Boolean,
    default: false
  }
})

const loading = ref(true)
const recommendList = ref([])

const normalizedRecommendType = computed(() => {
  return String(props.recommendType || '').toLowerCase() === 'product' ? 'product' : 'special'
})

const isProductMode = computed(() => normalizedRecommendType.value === 'product')

const shouldRender = computed(() => {
  return !props.hideWhenEmpty || loading.value || displayRecommendList.value.length > 0
})

const displayRecommendList = computed(() => {
  return recommendList.value
    .map((item) => normalizeRecommendItem(item))
    .filter((item) => item.id > 0 && item.cover && item.title)
})

watch(() => props.limit, () => {
  fetchRecommendations()
})

watch(() => props.excludeId, () => {
  if (!isProductMode.value) {
    fetchRecommendations()
  }
})

watch(() => props.recommendType, () => {
  fetchRecommendations()
})

onMounted(() => {
  fetchRecommendations()
})

/**
 * 拉取猜你喜欢数据，并维护加载状态。
 *
 * @returns {Promise<void>}
 */
async function fetchRecommendations() {
  loading.value = true
  recommendList.value = []

  try {
    const params = {
      limit: props.limit
    }
    const res = isProductMode.value
      ? await getProductList({
        ...params,
        sort: 'sales'
      }, {
        showError: false,
        loginRedirect: false
      })
      : await getSpecialRecommendList({
        ...params,
        exclude_id: props.excludeId || ''
      }, {
        showError: false,
        loginRedirect: false
      })

    if (!res || res.code !== 200 || !res.data) {
      recommendList.value = []
      return
    }

    recommendList.value = Array.isArray(res.data.list) ? res.data.list : []
  } catch (error) {
    console.error('[guess-you-like] fetchRecommendations failed', error)
    recommendList.value = []
  } finally {
    loading.value = false
  }
}

/**
 * 统一商品和专题卡片字段，避免展示层依赖两个接口的原始命名差异。
 *
 * @param {Object} item 商品或专题原始数据。
 * @returns {Object} 卡片可直接渲染的数据。
 */
function normalizeRecommendItem(item = {}) {
  const id = Number(item.id || item.product_id || item.special_id || 0)
  const price = Number(item.price || 0)
  const originalPrice = Number(item.original_price || item.ot_price || item.market_price || item.line_price || 0)
  const hasSpecialType = item.special_type !== undefined && item.special_type !== null && item.special_type !== ''
  // 专题推荐接口可能不返回 special_type，价格为正时应按付费兜底。
  const isFree = price <= 0 || (
    !isProductMode.value &&
    hasSpecialType &&
    Number(item.special_type) === 0
  )
  const originalPriceText = String(item.original_price_text || '').trim() ||
    (!isFree && originalPrice > price ? `¥${formatMoney(originalPrice)}` : '')

  return {
    id,
    cover: String(item.cover || item.image || '').trim(),
    title: String(item.title || item.name || '').trim(),
    description: String(item.description || item.subtitle || '').trim(),
    priceText: String(item.price_text || '').trim() || (isFree ? '免费' : `¥${formatMoney(price)}`),
    originalPriceText,
    tags: normalizeRecommendTags(item, id)
  }
}

/**
 * 规范化商品/专题标签为当前卡片样式使用的字段。
 *
 * @param {Object} item 商品或专题原始数据。
 * @param {number} id 资源 ID。
 * @returns {Array<Object>} 可渲染标签列表。
 */
function normalizeRecommendTags(item = {}, id = 0) {
  const source = Array.isArray(item.tags) && item.tags.length
    ? item.tags
    : (Array.isArray(item.tag_list) ? item.tag_list : [])

  return source.map((tag, tagIndex) => {
    const text = String(tag && (tag.text || tag.name || tag.label) || '').trim()
    const rawType = String(tag && (tag.type || tag.tag_type) || 'default').trim().toLowerCase()
    const type = ['hot', 'free', 'paid'].includes(rawType) ? rawType : 'default'
    return {
      key: `${id}-${tagIndex}`,
      text,
      className: `tag-${type}`
    }
  }).filter((tag) => tag.text)
}

/**
 * 处理“更多”点击事件。
 *
 * @returns {void}
 */
function handleMoreClick() {
  uni.navigateTo({
    url: isProductMode.value ? '/pages/goods/list/index' : '/pages/contents/special/index'
  })
}

/**
 * 格式化金额文本。
 *
 * @param {number|string} value 金额值。
 * @returns {string} 金额展示文本。
 */
function formatMoney(value) {
  const amount = Number(value || 0)
  if (!Number.isFinite(amount)) {
    return '0.00'
  }

  return amount.toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
}

/**
 * 处理商品或专题卡片点击事件。
 *
 * @param {Object} item 当前推荐对象。
 * @returns {void}
 */
function handleRecommendClick(item) {
  const id = Number(item.id || 0)
  if (id <= 0) {
    uni.showToast({
      title: isProductMode.value ? '商品信息不存在' : '专题信息不存在',
      icon: 'none'
    })
    return
  }

  uni.navigateTo({
    url: isProductMode.value
      ? `/pages/goods/detail/index?id=${id}`
      : `/pages/contents/special/detail/index?id=${id}`
  })
}
</script>

<style lang="scss" scoped>
.guess-you-like {
  padding: 0 0 40rpx;

  &__loading,
  &__empty {
    min-height: 360rpx;
    border-radius: 16rpx;
    background: #ffffff;
    box-shadow: 0 4rpx 10rpx rgba(0, 0, 0, 0.03);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
  }

  &__loading-text {
    margin-top: 20rpx;
    font-size: 22rpx;
    color: #a1a1aa;
  }

  &__empty {
    padding: 44rpx 32rpx;
  }

  &__empty-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 88rpx;
    height: 88rpx;
    border-radius: 50%;
    background: #f4f4f5;
  }

  &__empty-title {
    margin-top: 22rpx;
    font-size: 28rpx;
    font-weight: 700;
    color: #1a1c1c;
  }

  &__empty-desc {
    margin-top: 12rpx;
    font-size: 22rpx;
    line-height: 1.6;
    text-align: center;
    color: #a1a1aa;
  }

  &__empty-button {
    margin-top: 28rpx;
    padding: 16rpx 40rpx;
    border-radius: 999rpx;
    font-size: 24rpx;
    font-weight: 700;
    color: #ffffff;
    background: var(--brand-primary, #ea580c);
    box-shadow: 0 10rpx 24rpx rgba(233, 89, 15, 0.16);
  }

  &__empty-button--hover {
    opacity: 0.9;
  }
}

.section-header {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 98rpx;
  margin-bottom: 20rpx;
}

.section-header--recommend {
  margin-bottom: 20rpx;
}

.section-title-wrap {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 420rpx;
  height: 98rpx;
}

.section-title-bg {
  position: absolute;
  left: 0;
  top: 0;
  width: 420rpx;
  height: 98rpx;
}

.section-title {
  position: relative;
  z-index: 1;
  font-size: 32rpx;
  font-weight: 700;
  color: #1a1c1c;
  text-align: center;
}

.section-title--recommend {
  font-size: 34rpx;
}

.section-more {
  position: absolute;
  right: 0;
  top: 50%;
  z-index: 2;
  font-size: 22rpx;
  color: #a1a1aa;
  display: flex;
  align-items: center;
  transform: translateY(-50%);
}

.section-more--recommend {
  font-size: 22rpx;
}

.product-grid {
  display: flex;
  flex-wrap: wrap;
  margin: -12rpx;
}

.product-card {
  width: 50%;
  padding: 12rpx;
  box-sizing: border-box;

  &:active {
    opacity: 0.92;
  }
}

.product-card__inner {
  display: flex;
  flex-direction: column;
  height: 100%;
  border-radius: 16rpx;
  background: #ffffff;
  overflow: hidden;
  box-shadow: 0 4rpx 10rpx rgba(0, 0, 0, 0.03);
}

.product-image {
  display: block;
  width: 100%;
  height: 320rpx;
}

.product-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  padding: 24rpx;
}

.product-name {
  height: 80rpx;
  font-size: 28rpx;
  font-weight: 700;
  color: #1a1c1c;
  line-height: 1.4;
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  overflow: hidden;
}

.product-desc {
  height: 60rpx;
  font-size: 20rpx;
  color: #9e9e9e;
  margin-top: 8rpx;
  line-height: 1.5;
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  overflow: hidden;
}

.product-tags {
  display: flex;
  flex-wrap: wrap;
  margin: 8rpx -4rpx -4rpx;
}

.product-tag {
  margin: 8rpx 4rpx 4rpx;
  padding: 4rpx 10rpx;
  border-radius: 6rpx;
  font-size: 17rpx;
  font-weight: 700;

  &.tag-hot {
    background: var(--brand-primary-pale, #fff7ed);
    color: var(--brand-primary, #ea580c);
  }

  &.tag-free {
    background: #ecfdf5;
    color: #10b981;
  }

  &.tag-paid {
    background: #eff6ff;
    color: #2563eb;
  }

  &.tag-default {
    background: #f4f4f5;
    color: #71717a;
  }
}

.product-price {
  margin-top: auto;
  padding-top: 18rpx;
  display: flex;
  align-items: baseline;
}

.price-value {
  font-size: 32rpx;
  font-weight: 700;
  color: var(--brand-primary, #ea580c);
}

.origin-price-value {
  margin-left: 10rpx;
  font-size: 22rpx;
  line-height: 1.2;
  color: #a1a1aa;
  text-decoration: line-through;
}
</style>
