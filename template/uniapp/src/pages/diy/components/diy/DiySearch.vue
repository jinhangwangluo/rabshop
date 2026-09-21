<template>
  <view class="diy-search" :class="searchClass" :style="componentStyle">
    <view class="diy-search__content">
      <view
        class="diy-search__box"
        :class="{ 'diy-search__box--square': isSquare }"
        :style="boxStyle"
        hover-class="diy-search__box--hover"
        @click="handleClick"
      >
        <view v-if="showLeftIcon" class="diy-search__icon-wrap">
          <image
            v-if="leftIconUrl"
            :src="leftIconUrl"
            class="diy-search__icon-img"
            mode="aspectFit"
          />
          <text
            v-else
            class="iconfont icon-search diy-search__icon"
            :style="textStyle"
          ></text>
        </view>
        <text class="diy-search__placeholder" :style="textStyle">
          {{ placeholderText }}
        </text>
      </view>
    </view>
  </view>
</template>

<script setup>
import { computed } from 'vue'
import { navigateByLink } from '@/utils/link'

defineOptions({
  name: 'DiySearch'
})

const props = defineProps({
  config: {
    type: Object,
    default: () => ({})
  },
  data: {
    type: Array,
    default: () => []
  },
  isBannerAdjacent: {
    type: Boolean,
    default: false
  }
})

const safeConfig = computed(() => {
  return props.config && typeof props.config === 'object' ? props.config : {}
})

const placeholderText = computed(() => {
  return safeConfig.value.placeholder || '搜索课程、专题、直播'
})

const showLeftIcon = computed(() => {
  return safeConfig.value.show_left_icon !== false
})

const leftIconUrl = computed(() => {
  const logoConfig = safeConfig.value.logoConfig && typeof safeConfig.value.logoConfig === 'object' ? safeConfig.value.logoConfig : {}
  return logoConfig.url || safeConfig.value.left_icon_url || safeConfig.value.icon_url || safeConfig.value.left_icon || ''
})

const isSquare = computed(() => {
  return safeConfig.value.shape === 'square' || safeConfig.value.search_style === 'square'
})

const searchClass = computed(() => {
  return props.isBannerAdjacent ? 'diy-search--banner-adjacent' : ''
})

const componentStyle = computed(() => {
  const style = ['background-color: transparent;']
  if (safeConfig.value.nav_compact === true) {
    style.push('padding-top: 0; padding-bottom: 0;')
  }

  return style.join('')
})

const boxStyle = computed(() => {
  const style = []
  if (props.isBannerAdjacent) {
    style.push('background-color: rgba(255, 255, 255, 0.68);')
  } else if (safeConfig.value.box_bg_color) {
    style.push(`background-color: ${safeConfig.value.box_bg_color};`)
  }
  const heightPx = Number(safeConfig.value.box_height_px || 0)
  if (heightPx > 0) {
    style.push(`height: ${heightPx}px; min-height: ${heightPx}px;`)
  }

  return style.join('')
})

const textStyle = computed(() => {
  return safeConfig.value.text_color ? `color: ${safeConfig.value.text_color};` : ''
})

function handleClick() {
  if (navigateByLink(safeConfig.value.link_url)) {
    return
  }
  uni.navigateTo({ url: '/pages/search/index' })
}
</script>

<style lang="scss" scoped>
.diy-search {
  display: block;
  width: 100%;
  min-height: 76rpx;
  padding-top: 20rpx;
  padding-bottom: 20rpx;
  box-sizing: border-box;
}

.diy-search__content {
  position: relative;
  width: 100%;
}

.diy-search__box {
  display: flex;
  align-items: center;
  width: 100%;
  min-height: 76rpx;
  padding-left: 28rpx;
  padding-right: 28rpx;
  border: 1px solid rgba(15, 23, 42, 0.08);
  border-radius: 999rpx;
  background: #ffffff;
  box-shadow: 0 8rpx 24rpx rgba(15, 23, 42, 0.04);
  box-sizing: border-box;
}

.diy-search--banner-adjacent .diy-search__box {
  border-color: rgba(255, 255, 255, 0.5);
  box-shadow: 0 8rpx 24rpx rgba(15, 23, 42, 0.08);
}

.diy-search__box--square {
  border-radius: 16rpx;
}

.diy-search__box--hover {
  opacity: 0.88;
}

.diy-search__icon-wrap {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36rpx;
  height: 36rpx;
  margin-right: 14rpx;
}

.diy-search__icon {
  font-size: 30rpx;
  line-height: 1;
}

.diy-search__icon-img {
  display: block;
  width: 36rpx;
  height: 36rpx;
}

.diy-search__placeholder {
  flex: 1;
  min-width: 0;
  font-size: 26rpx;
  font-weight: 500;
  line-height: 36rpx;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
