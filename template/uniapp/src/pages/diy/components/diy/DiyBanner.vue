<template>
  <view class="diy-banner">
    <swiper
      class="banner-swiper"
      :style="swiperStyleText"
      :autoplay="config.autoplay !== false"
      :interval="config.interval || 3000"
      circular
      @change="handleChange"
    >
      <swiper-item
        v-for="(item, index) in bannerList"
        :key="index"
      >
        <view class="banner-card" :style="bannerCardStyleText" @click="handleClick(item)">
          <image
            class="banner-image"
            :src="item.image"
            mode="aspectFill"
            @load="handleImageLoad($event, index)"
          />
        </view>
      </swiper-item>
    </swiper>

    <view v-if="config.show_indicator !== false && bannerList.length > 1" class="banner-indicator" :class="indicatorClass">
      <template v-if="indicatorStyle === 'number'">
        <text class="banner-indicator__number" :style="numberIndicatorStyleText">{{ currentIndex + 1 }} / {{ bannerList.length }}</text>
      </template>
      <template v-else>
        <view
          v-for="(item, index) in bannerIndicators"
          :key="index"
          class="banner-indicator__item"
          :class="item.activeClass"
          :style="item.indicatorStyle"
        ></view>
      </template>
    </view>
  </view>
</template>

<script setup>
import {
  computed,
  getCurrentInstance,
  nextTick,
  onBeforeUnmount,
  onMounted,
  ref,
  watch
} from 'vue'
import { DEFAULT_BANNER_HEIGHT_PX, getImageRadiusStyle, toRpxUnit } from '@/utils/diy_adapter'
import { navigateByLink } from '@/utils/link'

defineOptions({
  name: 'DiyBanner'
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

const emit = defineEmits(['change'])

const instance = getCurrentInstance()

const currentIndex = ref(0)
const firstImageAspectRatio = ref(0)
const swiperHeightPx = ref(0)

const bannerList = computed(() => {
  const source = props.data.length > 0 ? props.data : (props.config.items || [])
  return Array.isArray(source) ? source.filter((item) => item && item.image) : []
})

const firstBannerImage = computed(() => {
  return bannerList.value.length > 0 ? bannerList.value[0].image : ''
})

const swiperStyleText = computed(() => {
  if (swiperHeightPx.value > 0) {
    return `height: ${swiperHeightPx.value}px;`
  }
  return `height: ${toRpxUnit(props.config.height, DEFAULT_BANNER_HEIGHT_PX)};`
})

const bannerCardStyleText = computed(() => {
  const imageRadiusStyle = getImageRadiusStyle(props.config.style_config)
  const cardStyle = Object.keys(imageRadiusStyle).length > 0
    ? imageRadiusStyle
    : {
        borderRadius: toRpxUnit(props.config.card_radius, 16),
        overflow: 'hidden'
      }

  return toStyleText(cardStyle)
})

const indicatorClass = computed(() => {
  return `style-${indicatorStyle.value} pos-${indicatorPosition.value}`
})

const indicatorStyle = computed(() => {
  const style = String(props.config.indicator_style || 'dot').trim()
  return ['dot', 'line', 'number'].indexOf(style) > -1 ? style : 'dot'
})

const indicatorPosition = computed(() => {
  const position = String(props.config.indicator_position || 'left').trim()
  return ['left', 'center', 'right'].indexOf(position) > -1 ? position : 'left'
})

const activeIndicatorColor = computed(() => {
  const styleConfig = props.config.style_config || {}
  return styleConfig.indicator_color || props.config.indicator_active_color || ''
})

const indicatorColor = computed(() => {
  return props.config.indicator_color || ''
})

const numberIndicatorStyleText = computed(() => {
  return activeIndicatorColor.value ? `color: ${activeIndicatorColor.value};` : ''
})

const bannerIndicators = computed(() => {
  return bannerList.value.map((item, index) => {
    const isActive = index === currentIndex.value
    return {
      ...item,
      activeClass: isActive ? 'is-active' : '',
      indicatorStyle: buildIndicatorStyleText(isActive)
    }
  })
})

watch(firstBannerImage, () => {
  currentIndex.value = 0
  firstImageAspectRatio.value = 0
  swiperHeightPx.value = 0
}, {
  immediate: true
})

onMounted(() => {
  if (typeof uni.onWindowResize === 'function') {
    uni.onWindowResize(handleWindowResize)
  }
})

onBeforeUnmount(() => {
  if (typeof uni.offWindowResize === 'function') {
    uni.offWindowResize(handleWindowResize)
  }
})

function handleImageLoad(event, index) {
  if (index !== 0) return

  const detail = event && event.detail ? event.detail : {}
  const imageWidth = Number(detail.width || 0)
  const imageHeight = Number(detail.height || 0)
  if (imageWidth <= 0 || imageHeight <= 0) return

  firstImageAspectRatio.value = imageHeight / imageWidth
  updateSwiperHeight()
}

function updateSwiperHeight() {
  if (firstImageAspectRatio.value <= 0) return

  nextTick(() => {
    const query = uni.createSelectorQuery().in(instance.proxy)
    query.select('.diy-banner').boundingClientRect((rect) => {
      const containerWidth = Number(rect && rect.width ? rect.width : 0)
      if (containerWidth > 0) {
        swiperHeightPx.value = Math.round(containerWidth * firstImageAspectRatio.value)
      }
    }).exec()
  })
}

function handleWindowResize() {
  updateSwiperHeight()
}

function handleChange(event) {
  currentIndex.value = Number(event?.detail?.current || 0)
  emit('change', currentIndex.value)
}

function buildIndicatorStyleText(isActive) {
  const backgroundColor = isActive ? activeIndicatorColor.value : indicatorColor.value
  return backgroundColor ? `background-color: ${backgroundColor};` : ''
}

function toStyleText(style = {}) {
  return Object.keys(style).map((key) => {
    const cssKey = key.replace(/[A-Z]/g, (match) => `-${match.toLowerCase()}`)
    return `${cssKey}: ${style[key]};`
  }).join(' ')
}

function handleClick(item = {}) {
  navigateByLink(item.link_url, {
    copyTip: '小程序暂不支持直接打开外链，链接已复制'
  })
}
</script>

<style lang="scss" scoped>
.diy-banner {
  position: relative;
}

.banner-swiper {
  width: 100%;
}

.banner-card {
  position: relative;
  width: 100%;
  height: 100%;
  overflow: hidden;
  border-radius: 32rpx;
}

.banner-image,
.banner-overlay {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  width: 100%;
  height: 100%;
}

.banner-overlay {
  background: linear-gradient(180deg, rgba(15, 23, 42, 0.1), rgba(15, 23, 42, 0.68));
}

.banner-content {
  position: absolute;
  left: 32rpx;
  right: 32rpx;
  bottom: 32rpx;
  z-index: 2;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
}

.banner-tag {
  padding: 10rpx 18rpx;
  border-radius: 999rpx;
  margin-bottom: 18rpx;
  background: rgba(255, 120, 82, 0.22);
  color: #ffe7de;
  font-size: 20rpx;
  font-weight: 600;
  letter-spacing: 2rpx;
}

.banner-title {
  font-size: 42rpx;
  line-height: 1.24;
  font-weight: 700;
  color: #ffffff;
}

.banner-subtitle {
  margin-top: 14rpx;
  font-size: 24rpx;
  line-height: 1.7;
  color: rgba(255, 255, 255, 0.82);
}

.banner-cta {
  margin-top: 22rpx;
  padding: 16rpx 26rpx;
  border-radius: 999rpx;
  background: linear-gradient(135deg, #ac2c00, #ff7852);
  box-shadow: 0 16rpx 32rpx rgba(172, 44, 0, 0.24);

  text {
    font-size: 24rpx;
    font-weight: 700;
    color: #ffffff;
  }
}

.banner-indicator {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 18rpx;
  z-index: 5;
  display: flex;
  align-items: center;
  padding-left: 24rpx;
  padding-right: 24rpx;
  box-sizing: border-box;

  &.pos-left {
    justify-content: flex-start;
  }

  &.pos-center {
    justify-content: center;
  }

  &.pos-right {
    justify-content: flex-end;
  }
}

.banner-indicator__item {
  width: 12rpx;
  height: 12rpx;
  margin-left: 5rpx;
  margin-right: 5rpx;
  border-radius: 6rpx;
}

.banner-indicator.style-dot .banner-indicator__item.is-active {
  width: 32rpx;
  border-radius: 6rpx;
}

.banner-indicator.style-line .banner-indicator__item {
  width: 32rpx;
  height: 6rpx;
  border-radius: 3rpx;
}

.banner-indicator.style-line .banner-indicator__item.is-active {
  width: 32rpx;
}

.banner-indicator__number {
  padding-left: 16rpx;
  padding-right: 16rpx;
  border-radius: 18rpx;
  background: rgba(0, 0, 0, 0.35);
  color: #ffffff;
  font-size: 22rpx;
  line-height: 36rpx;
}
</style>
