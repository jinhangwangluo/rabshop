<template>
  <view v-if="visible" class="product-image-preview" @click.stop="handlePreviewTap">
    <view class="product-image-preview__top" :style="previewTopStyle" @click.stop>
      <view v-if="showClose" class="product-image-preview__close" hover-class="product-image-preview__close--hover" @click="handleClose">
        <text class="iconfont icon-close product-image-preview__close-icon"></text>
      </view>
      <text class="product-image-preview__count">{{ counterText }}</text>
    </view>

    <swiper
      class="product-image-preview__swiper"
      :current="safeCurrent"
      :duration="260"
      :circular="canLoop"
      @change="handleChange"
    >
      <swiper-item v-for="item in normalizedImages" :key="item.id" class="product-image-preview__slide">
        <image class="product-image-preview__image" :src="item.url" mode="aspectFit"></image>
      </swiper-item>
    </swiper>

    <view v-if="showMetaPanel" class="product-image-preview__meta" @click.stop>
      <text v-if="currentItem.title" class="product-image-preview__title">{{ currentItem.title }}</text>
      <text v-if="currentItem.priceText" class="product-image-preview__price">¥{{ currentItem.priceText }}</text>
    </view>
  </view>
</template>

<script setup>
import { computed } from 'vue'

defineOptions({
  name: 'ProductImagePreview'
})

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  images: {
    type: Array,
    default: () => []
  },
  current: {
    type: Number,
    default: 0
  },
  counterSeparator: {
    type: String,
    default: ' / '
  },
  showClose: {
    type: Boolean,
    default: true
  },
  closeOnTap: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['close', 'change'])

const normalizedImages = computed(() => {
  return props.images.map((item, index) => {
    const image = typeof item === 'string' ? { url: item } : item || {}
    return {
      id: String(image.id || index + 1),
      url: String(image.url || image.image || '').trim(),
      title: String(image.title || image.label || '').trim(),
      priceText: String(image.priceText || '').trim()
    }
  }).filter((item) => item.url)
})
const safeCurrent = computed(() => {
  const maxIndex = Math.max(0, normalizedImages.value.length - 1)
  return Math.min(maxIndex, Math.max(0, Number(props.current || 0)))
})
const currentItem = computed(() => normalizedImages.value[safeCurrent.value] || {})
const canLoop = computed(() => normalizedImages.value.length > 1)
const showMetaPanel = computed(() => Boolean(currentItem.value.title || currentItem.value.priceText))
const counterText = computed(() => {
  const total = normalizedImages.value.length
  if (!total) {
    return `0${props.counterSeparator}0`
  }
  return `${safeCurrent.value + 1}${props.counterSeparator}${total}`
})
const previewTopRpx = getPreviewTopRpx()
const previewTopStyle = computed(() => {
  return `top: ${previewTopRpx}rpx;`
})

/**
 * 获取全屏预览顶部计数区域位置。
 *
 * @returns {number}
 */
function getPreviewTopRpx() {
  let top = 64
  // #ifndef H5
  const systemInfo = uni.getSystemInfoSync()
  const windowWidth = Number(systemInfo.windowWidth || 375)
  const statusBarHeight = Number(systemInfo.statusBarHeight || 0)
  const statusBarHeightRpx = windowWidth > 0 ? Math.round(statusBarHeight * 750 / windowWidth) : 0
  top = statusBarHeightRpx + 24
  // #endif

  return top
}

/**
 * 关闭全屏图片预览。
 *
 * @returns {void}
 */
function handleClose() {
  emit('close')
}

/**
 * 处理预览区域点击。
 *
 * @returns {void}
 */
function handlePreviewTap() {
  if (props.closeOnTap) {
    emit('close')
  }
}

/**
 * 同步 swiper 当前索引。
 *
 * @param {Object} event swiper change 事件。
 * @returns {void}
 */
function handleChange(event) {
  const index = Number(event.detail && event.detail.current ? event.detail.current : 0)
  emit('change', index)
}
</script>

<style lang="scss" scoped>
.product-image-preview {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  z-index: 60;
  background: #000000;
  animation: product-preview-fade-in 180ms ease-out both;
}

@keyframes product-preview-fade-in {
  0% {
    opacity: 0;
  }

  100% {
    opacity: 1;
  }
}

.product-image-preview__top {
  position: absolute;
  top: 64rpx;
  right: 0;
  left: 0;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: center;
  height: 72rpx;
}

.product-image-preview__close {
  position: absolute;
  left: 32rpx;
  top: 9rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 54rpx;
  height: 54rpx;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.16);
}

.product-image-preview__close--hover {
  opacity: 0.72;
}

.product-image-preview__close-icon {
  color: #ffffff;
  font-size: 28rpx;
  line-height: 32rpx;
}

.product-image-preview__count {
  color: #ffffff;
  font-size: 40rpx;
  line-height: 56rpx;
  font-weight: 500;
}

.product-image-preview__swiper {
  position: absolute;
  top: 50%;
  left: 50%;
  width: 100%;
  height: 100%;
  transform: translate(-50%, -50%);
}

.product-image-preview__slide {
  display: flex;
  align-items: center;
  justify-content: center;
}

.product-image-preview__image {
  width: 100%;
  height: 100%;
}

.product-image-preview__meta {
  position: absolute;
  right: 32rpx;
  bottom: 190rpx;
  left: 32rpx;
  z-index: 2;
  display: flex;
  flex-direction: column;
  align-items: center;
}

/* #ifdef H5 */
.product-image-preview__meta {
  bottom: calc(var(--window-bottom) + 190rpx);
}
/* #endif */

.product-image-preview__title {
  display: flex;
  align-items: center;
  justify-content: center;
  max-width: 620rpx;
  min-height: 64rpx;
  padding: 0 42rpx;
  border: 2rpx solid rgba(255, 255, 255, 0.86);
  border-radius: 999rpx;
  color: #ffffff;
  font-size: 30rpx;
  line-height: 42rpx;
  text-align: center;
}

.product-image-preview__price {
  margin-top: 34rpx;
  color: #ffffff;
  font-size: 42rpx;
  line-height: 56rpx;
  font-weight: 700;
}
</style>
