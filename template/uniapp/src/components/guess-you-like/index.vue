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
      <text class="guess-you-like__loading-text">内容加载中...</text>
    </view>

    <view v-else-if="displayProductList.length" class="product-grid">
      <view
        v-for="item in displayProductList"
        :key="item.id"
        class="product-card"
        @click="handleProductClick(item)"
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
      <text class="guess-you-like__empty-title">暂无推荐内容</text>
      <text class="guess-you-like__empty-desc">暂时没有匹配的专题，去专题广场看看更多内容。</text>
      <view class="guess-you-like__empty-button" hover-class="guess-you-like__empty-button--hover" @click="handleMoreClick">
        浏览专题
      </view>
    </view>
  </view>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
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
  hideWhenEmpty: {
    type: Boolean,
    default: false
  }
})

const loading = ref(true)
const productList = ref([])

const shouldRender = computed(() => {
  return !props.hideWhenEmpty || loading.value || displayProductList.value.length > 0
})

const displayProductList = computed(() => {
  return productList.value.map((item) => {
    const price = Number(item.price || 0)
    const originalPrice = Number(item.original_price || item.ot_price || item.market_price || item.line_price || 0)
    const isFree = price <= 0

    return {
      id: Number(item.id || 0),
      cover: String(item.cover || '').trim(),
      title: String(item.title || '').trim(),
      description: String(item.description || '').trim(),
      priceText: String(item.price_text || '').trim() || (isFree ? '免费' : `¥${formatMoney(price)}`),
      originalPriceText: !isFree && originalPrice > price ? `¥${formatMoney(originalPrice)}` : '',
      tags: Array.isArray(item.tags)
        ? item.tags.map((tag, tagIndex) => ({
          key: `${Number(item.id || 0)}-${tagIndex}`,
          text: String(tag.text || '').trim(),
          className: `tag-${String(tag.type || 'default').trim() || 'default'}`
        })).filter((tag) => tag.text)
        : []
    }
  }).filter((item) => item.id > 0 && item.cover && item.title)
})

watch(() => props.limit, () => {
  fetchRecommendProducts()
})

watch(() => props.excludeId, () => {
  fetchRecommendProducts()
})

onMounted(() => {
  fetchRecommendProducts()
})

/**
 * 拉取猜你喜欢专题推荐数据，并维护加载状态。
 *
 * @returns {Promise<void>}
 */
async function fetchRecommendProducts() {
  loading.value = true
  productList.value = []

  try {
    const res = await getSpecialRecommendList({
      limit: props.limit,
      exclude_id: props.excludeId || ''
    }, {
      showError: false,
      loginRedirect: false
    })

    if (!res || res.code !== 200 || !res.data) {
      productList.value = []
      return
    }

    productList.value = Array.isArray(res.data.list) ? res.data.list : []
  } catch (error) {
    console.error('[guess-you-like] fetchRecommendProducts failed', error)
    productList.value = []
  } finally {
    loading.value = false
  }
}

/**
 * 处理“更多”点击事件。
 *
 * @returns {void}
 */
function handleMoreClick() {
  uni.navigateTo({
    url: '/pages/contents/special/index'
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
 * 处理专题推荐点击事件。
 *
 * @param {Object} item 当前点击的专题对象。
 * @returns {void}
 */
function handleProductClick(item) {
  const specialId = Number(item.id || 0)
  if (specialId <= 0) {
    uni.showToast({
      title: '专题信息不存在',
      icon: 'none'
    })
    return
  }

  uni.navigateTo({
    url: `/pages/contents/special/detail/index?id=${specialId}`
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
