<template>
  <view class="shop-panel">
    <view class="shop-panel__summary">
      <text class="shop-panel__summary-text">{{ summaryText }}</text>
      <view class="shop-panel__sort-list">
        <text
          v-for="item in sortOptions"
          :key="item.value"
          class="shop-panel__sort-item"
          :class="{ 'is-active': activeSort === item.value }"
          @click="handleSortChange(item.value)"
        >{{ item.label }}</text>
      </view>
    </view>

    <view v-if="loading" class="shop-panel__loading">
      <six-petal-spiral
        :active="true"
        :size="84"
        text=""
        :color="themePrimaryColor"
        :stroke-width="1.3"
        :particle-count="36"
        :trail-span="0.28"
      />
      <text class="shop-panel__loading-text">搜索中...</text>
    </view>

    <template v-else>
      <view v-if="shops.length">
        <view v-for="shop in shops" :key="shop.id" class="shop-card" @click="handleSelect(shop)">
          <view class="shop-card__head">
            <view class="shop-card__merchant">
              <view class="shop-card__logo" :style="shop.logoStyle">
                <text class="shop-card__logo-text">{{ shop.logoText }}</text>
              </view>
              <view class="shop-card__info">
                <view class="shop-card__name-row">
                  <text class="shop-card__name">{{ shop.name }}</text>
                  <text class="shop-card__label">{{ shop.label }}</text>
                </view>
                <view class="shop-card__meta">
                  <text class="shop-card__rating">★ {{ shop.ratingText }}</text>
                  <text class="shop-card__fans">{{ shop.fansText }}</text>
                </view>
              </view>
            </view>
            <view class="shop-card__enter" @click.stop="handleEnter(shop)">
              <text class="shop-card__enter-text">进店</text>
            </view>
          </view>

          <view v-if="shop.products && shop.products.length" class="shop-card__goods-list">
            <view v-for="goods in shop.products" :key="goods.id" class="shop-card__goods-item">
              <image class="shop-card__goods-cover" :src="goods.cover" mode="aspectFill"></image>
              <text class="shop-card__goods-title">{{ goods.title }}</text>
              <view class="shop-card__goods-bottom">
                <text class="shop-card__goods-price">¥{{ goods.price }}</text>
                <text class="shop-card__goods-sales">{{ goods.salesText }}</text>
              </view>
            </view>
          </view>
        </view>
      </view>

      <view v-else class="shop-panel__empty">
        <image class="shop-panel__empty-image" :src="emptyImage" mode="aspectFit"></image>
        <text class="shop-panel__empty-title">没有找到匹配店铺</text>
        <text class="shop-panel__empty-desc">换个关键词试试，或者先看看推荐店铺</text>
      </view>
    </template>
  </view>
</template>

<script setup>
import { ref } from 'vue'
import { getStatusImageUrl } from '@/utils/status_images'

defineOptions({
  name: 'SearchShopPanel'
})

defineProps({
  summaryText: {
    type: String,
    default: ''
  },
  shops: {
    type: Array,
    default: () => []
  },
  sortOptions: {
    type: Array,
    default: () => []
  },
  activeSort: {
    type: String,
    default: 'default'
  },
  loading: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['sort-change', 'enter', 'select'])

const emptyImage = ref(getStatusImageUrl('no_store.png'))

function handleSortChange(value) {
  emit('sort-change', value)
}

function handleEnter(shop) {
  emit('enter', shop)
}

function handleSelect(shop) {
  emit('select', shop)
}
</script>

<style lang="scss" scoped>
.shop-panel__summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24rpx;
  padding: 0 4rpx;
}

.shop-panel__summary-text {
  font-size: 20rpx;
  line-height: 28rpx;
  color: #6b7280;
}

.shop-panel__sort-list {
  display: flex;
  align-items: center;
}

.shop-panel__sort-item {
  margin-left: 24rpx;
  font-size: 20rpx;
  line-height: 28rpx;
  color: #6b7280;
}

.shop-panel__sort-item:first-child {
  margin-left: 0;
}

.shop-panel__sort-item.is-active {
  color: #1a1c1c;
  font-weight: 600;
}

.shop-panel__loading,
.shop-panel__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 520rpx;
  padding: 48rpx 32rpx;
  border-radius: 28rpx;
  background: #ffffff;
}

.shop-panel__loading-text {
  margin-top: 20rpx;
  font-size: 24rpx;
  line-height: 34rpx;
  color: #848484;
}

.shop-panel__empty-image {
  width: 300rpx;
  height: 220rpx;
}

.shop-panel__empty-title {
  margin-top: 18rpx;
  font-size: 30rpx;
  line-height: 42rpx;
  font-weight: 700;
  color: #1a1c1c;
}

.shop-panel__empty-desc {
  margin-top: 10rpx;
  font-size: 22rpx;
  line-height: 32rpx;
  color: #6b7280;
}

.shop-card {
  margin-bottom: 28rpx;
  padding: 24rpx;
  border-radius: 28rpx;
  background: #ffffff;
  box-shadow: 0 12rpx 36rpx rgba(0, 0, 0, 0.04);
}

.shop-card__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24rpx;
}

.shop-card__merchant {
  display: flex;
  align-items: center;
  flex: 1;
  min-width: 0;
}

.shop-card__logo {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 88rpx;
  height: 88rpx;
  border-radius: 50%;
}

.shop-card__logo-text {
  font-size: 34rpx;
  line-height: 1;
  font-weight: 700;
  color: #ffffff;
}

.shop-card__info {
  flex: 1;
  min-width: 0;
  margin-left: 20rpx;
}

.shop-card__name-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
}

.shop-card__name {
  font-size: 34rpx;
  line-height: 44rpx;
  font-weight: 700;
  color: #1a1c1c;
}

.shop-card__label {
  margin-left: 12rpx;
  padding: 4rpx 10rpx;
  border-radius: 10rpx;
  background: var(--brand-primary, #ea580c);
  font-size: 18rpx;
  line-height: 24rpx;
  font-weight: 700;
  color: #ffffff;
}

.shop-card__meta {
  display: flex;
  align-items: center;
  margin-top: 8rpx;
}

.shop-card__rating,
.shop-card__fans {
  font-size: 22rpx;
  line-height: 32rpx;
}

.shop-card__rating {
  color: var(--brand-primary, #ea580c);
}

.shop-card__fans {
  margin-left: 16rpx;
  color: #6b7280;
}

.shop-card__enter {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 120rpx;
  height: 64rpx;
  border: 2rpx solid var(--brand-primary, #ea580c);
  border-radius: 999rpx;
}

.shop-card__enter-text {
  font-size: 26rpx;
  line-height: 36rpx;
  font-weight: 700;
  color: var(--brand-primary, #ea580c);
}

.shop-card__goods-list {
  display: flex;
  justify-content: space-between;
}

.shop-card__goods-item {
  width: 31.2%;
}

.shop-card__goods-cover {
  width: 100%;
  height: 176rpx;
  border-radius: 18rpx;
  background: #ececec;
}

.shop-card__goods-title {
  display: -webkit-box;
  overflow: hidden;
  margin-top: 14rpx;
  font-size: 22rpx;
  line-height: 32rpx;
  font-weight: 600;
  color: #1a1c1c;
  text-overflow: ellipsis;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-clamp: 2;
}

.shop-card__goods-bottom {
  margin-top: 8rpx;
}

.shop-card__goods-price {
  display: block;
  font-size: 30rpx;
  line-height: 38rpx;
  font-weight: 700;
  color: var(--brand-primary, #ea580c);
}

.shop-card__goods-sales {
  display: block;
  margin-top: 2rpx;
  font-size: 18rpx;
  line-height: 24rpx;
  color: #6b7280;
}

</style>
