<template>
  <view class="diy-title">
    <view class="title-wrap" :class="titleWrapClass">
      <view class="title-main">
        <text v-if="config.eyebrow" class="title-eyebrow">{{ config.eyebrow }}</text>
        <text class="title-text" :style="titleTextStyle">{{ config.title }}</text>
        <text v-if="config.subtitle" class="title-subtitle" :style="subtitleTextStyle">{{ config.subtitle }}</text>
      </view>
      <view v-if="config.show_more" class="title-more" hover-class="title-more--hover" @click="handleMoreClick">
        <text>{{ config.more_text || '查看全部' }}</text>
        <uni-icons class="title-more__icon" type="right" size="13" color="#ac2c00"></uni-icons>
      </view>
    </view>
  </view>
</template>

<script setup>
import { computed } from 'vue'
import { navigateByLink } from '@/utils/link'

defineOptions({
  name: 'DiyTitle'
})

const props = defineProps({
  config: {
    type: Object,
    default: () => ({})
  },
  data: {
    type: Array,
    default: () => []
  }
})

const titleWrapClass = computed(() => {
  const align = String(props.config.align || 'left').trim()
  if (align === 'center') {
    return 'align-center'
  }
  if (align === 'right') {
    return 'align-right'
  }
  return ''
})

const titleTextStyle = computed(() => {
  return props.config.text_color ? `color: ${props.config.text_color};` : ''
})

const subtitleTextStyle = computed(() => {
  const color = props.config.subtitle_color || props.config.text_color || ''
  return color ? `color: ${color};` : ''
})

function handleMoreClick() {
  navigateByLink(props.config.more_link_url, {
    copyTip: '小程序暂不支持直接打开外链，链接已复制'
  })
}
</script>

<style lang="scss" scoped>
.diy-title {
  width: auto;
}

.title-wrap {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;

  &.align-center {
    justify-content: center;

    .title-main {
      text-align: center;
      align-items: center;
    }
  }

  &.align-right {
    justify-content: flex-end;

    .title-main {
      text-align: right;
      align-items: flex-end;
    }
  }
}

.title-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
}

.title-eyebrow {
  display: inline-flex;
  align-items: center;
  padding: 8rpx 16rpx;
  margin-bottom: 14rpx;
  border-radius: 999rpx;
  background: rgba(255, 215, 9, 0.18);
  color: #8a5600;
  font-size: 18rpx;
  font-weight: 700;
  letter-spacing: 2rpx;
}

.title-text {
  max-width: 100%;
  font-size: 36rpx;
  line-height: 1.3;
  font-weight: 700;
  color: #2d2f2f;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.title-subtitle {
  max-width: 100%;
  margin-top: 10rpx;
  font-size: 24rpx;
  line-height: 1.7;
  color: #707272;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.title-more {
  display: flex;
  align-items: center;
  margin-left: 24rpx;
  padding: 14rpx 20rpx;
  border-radius: 999rpx;
  background: rgba(255, 255, 255, 0.74);
  color: #ac2c00;
  font-size: 22rpx;
  font-weight: 600;
  white-space: nowrap;

  &__icon {
    margin-left: 8rpx;
  }
}

.title-more--hover {
  opacity: 0.86;
}
</style>
