<template>
  <view class="goods-panel">
    <view class="goods-panel__summary">
      <text class="goods-panel__summary-text">{{ summaryText }}</text>
      <view class="goods-panel__sort-list">
        <text
          v-for="item in sortOptions"
          :key="item.value"
          class="goods-panel__sort-item"
          :class="{ 'is-active': activeSort === item.value }"
          @click="handleSortChange(item.value)"
        >{{ item.label }}</text>
      </view>
    </view>

    <view v-if="loading" class="goods-panel__loading">
      <six-petal-spiral
        :active="true"
        :size="84"
        text=""
        :color="themePrimaryColor"
        :stroke-width="1.3"
        :particle-count="36"
        :trail-span="0.28"
      />
      <text class="goods-panel__loading-text">搜索中...</text>
    </view>

    <template v-else>
      <search-waterfall-grid
        v-if="items.length"
        :items="items"
        @select="handleSelect"
      ></search-waterfall-grid>

      <view v-else class="goods-panel__empty">
        <image class="goods-panel__empty-image" :src="emptyImage" mode="aspectFit"></image>
        <text class="goods-panel__empty-title">{{ emptyTitle }}</text>
        <text class="goods-panel__empty-desc">{{ emptyDesc }}</text>
      </view>
    </template>
  </view>
</template>

<script setup>
import SearchWaterfallGrid from './SearchWaterfallGrid.vue'
import { ref } from 'vue'
import { getStatusImageUrl } from '@/utils/status_images'

defineOptions({
  name: 'SearchGoodsPanel'
})

defineProps({
  summaryText: {
    type: String,
    default: ''
  },
  items: {
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
  },
  emptyTitle: {
    type: String,
    default: '未找到相关内容'
  },
  emptyDesc: {
    type: String,
    default: '换个关键词试试吧'
  }
})

const emit = defineEmits(['sort-change', 'select'])

const emptyImage = ref(getStatusImageUrl('no_search.png'))

function handleSortChange(value) {
  emit('sort-change', value)
}

function handleSelect(item) {
  emit('select', item)
}
</script>

<style lang="scss" scoped>
.goods-panel__summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24rpx;
  padding: 0 4rpx;
}

.goods-panel__summary-text {
  font-size: 20rpx;
  line-height: 28rpx;
  color: #6b7280;
}

.goods-panel__sort-list {
  display: flex;
  align-items: center;
}

.goods-panel__sort-item {
  margin-left: 24rpx;
  font-size: 20rpx;
  line-height: 28rpx;
  color: #6b7280;
}

.goods-panel__sort-item:first-child {
  margin-left: 0;
}

.goods-panel__sort-item.is-active {
  color: #1a1c1c;
  font-weight: 600;
}

.goods-panel__loading,
.goods-panel__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 520rpx;
  padding: 48rpx 32rpx;
  border-radius: 28rpx;
  background: #ffffff;
}

.goods-panel__loading-text {
  margin-top: 20rpx;
  font-size: 24rpx;
  line-height: 34rpx;
  color: #848484;
}

.goods-panel__empty-image {
  width: 320rpx;
  height: 240rpx;
}

.goods-panel__empty-title {
  margin-top: 20rpx;
  font-size: 30rpx;
  line-height: 42rpx;
  font-weight: 700;
  color: #1a1c1c;
}

.goods-panel__empty-desc {
  margin-top: 10rpx;
  font-size: 22rpx;
  line-height: 32rpx;
  color: #6b7280;
  text-align: center;
}

</style>
