<template>
  <view class="video-panel">
    <view v-if="loading" class="video-panel__loading">
      <six-petal-spiral
        :active="true"
        :size="84"
        text=""
        :color="themePrimaryColor"
        :stroke-width="1.3"
        :particle-count="36"
        :trail-span="0.28"
      />
      <text class="video-panel__loading-text">视频内容加载中...</text>
    </view>

    <template v-else>
      <view v-if="videos.length" class="video-panel__grid">
        <view
          v-for="item in videos"
          :key="item.id"
          class="video-card"
          @click="handleSelect(item)"
        >
          <view class="video-card__cover-wrap">
            <image class="video-card__cover" :src="item.cover" mode="aspectFill"></image>
            <view class="video-card__play">
              <image class="video-card__play-icon" src="/static/images/play.png" mode="aspectFit"></image>
            </view>
            <text class="video-card__duration">{{ item.duration }}</text>
          </view>

          <view class="video-card__body">
            <text class="video-card__title">{{ item.title }}</text>
            <view class="video-card__tag-row">
              <text class="video-card__tag">{{ item.tagText }}</text>
            </view>
            <view class="video-card__meta">
              <text class="video-card__price" :class="{ 'is-free': item.priceLabel === '免费' }">{{ item.priceLabel }}</text>
              <text class="video-card__plays">{{ item.viewText }}</text>
            </view>
          </view>
        </view>
      </view>

      <view v-else class="video-panel__empty">
        <image class="video-panel__empty-image" :src="emptyImage" mode="aspectFit"></image>
        <text class="video-panel__empty-title">暂未找到相关视频</text>
        <text class="video-panel__empty-desc">可以试试更具体的主题词，或者看看热门测评</text>
      </view>
    </template>
  </view>
</template>

<script setup>
import { ref } from 'vue'
import { getStatusImageUrl } from '@/utils/status_images'

defineOptions({
  name: 'SearchVideoPanel'
})

defineProps({
  videos: {
    type: Array,
    default: () => []
  },
  loading: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['select'])

const emptyImage = ref(getStatusImageUrl('no_result.png'))

function handleSelect(item) {
  emit('select', item)
}
</script>

<style lang="scss" scoped>
.video-panel__loading,
.video-panel__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 560rpx;
  padding: 48rpx 32rpx;
  border-radius: 28rpx;
  background: #ffffff;
}

.video-panel__loading-text {
  margin-top: 20rpx;
  font-size: 24rpx;
  line-height: 34rpx;
  color: #848484;
}

.video-panel__empty-image {
  width: 300rpx;
  height: 220rpx;
}

.video-panel__empty-title {
  margin-top: 18rpx;
  font-size: 30rpx;
  line-height: 42rpx;
  font-weight: 700;
  color: #1a1c1c;
}

.video-panel__empty-desc {
  margin-top: 10rpx;
  font-size: 22rpx;
  line-height: 32rpx;
  color: #6b7280;
  text-align: center;
}

.video-panel__grid {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
}

.video-card {
  width: 48.2%;
  margin-bottom: 24rpx;
  overflow: hidden;
  border-radius: 28rpx;
  background: #ffffff;
  box-shadow: 0 12rpx 36rpx rgba(0, 0, 0, 0.04);
}

.video-card__cover-wrap {
  position: relative;
  width: 100%;
  height: 320rpx;
  background: #ececec;
}

.video-card__cover {
  width: 100%;
  height: 100%;
}

.video-card__play {
  position: absolute;
  top: 50%;
  left: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 88rpx;
  height: 88rpx;
  border-radius: 50%;
  background: rgba(0, 0, 0, 0.25);
  transform: translate(-50%, -50%);
}

.video-card__play-icon {
  width: 36rpx;
  height: 36rpx;
}

.video-card__duration {
  position: absolute;
  right: 16rpx;
  bottom: 16rpx;
  padding: 6rpx 12rpx;
  border-radius: 12rpx;
  background: rgba(0, 0, 0, 0.58);
  font-size: 18rpx;
  line-height: 24rpx;
  color: #ffffff;
}

.video-card__body {
  padding: 20rpx;
}

.video-card__title {
  display: -webkit-box;
  overflow: hidden;
  font-size: 28rpx;
  line-height: 40rpx;
  font-weight: 700;
  color: #1a1c1c;
  text-overflow: ellipsis;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-clamp: 2;
}

.video-card__tag-row {
  margin-top: 12rpx;
}

.video-card__tag {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 6rpx 14rpx;
  border-radius: 10rpx;
  background: rgba(243, 90, 0, 0.1);
  font-size: 18rpx;
  line-height: 24rpx;
  font-weight: 600;
  color: var(--brand-primary, #ea580c);
}

.video-card__meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 16rpx;
}

.video-card__price,
.video-card__plays {
  font-size: 20rpx;
  line-height: 28rpx;
}

.video-card__price {
  color: var(--brand-primary, #ea580c);
  font-weight: 700;
}

.video-card__price.is-free {
  color: #6b7280;
}

.video-card__plays {
  color: #6b7280;
}
</style>
