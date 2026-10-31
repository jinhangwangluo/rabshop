<template>
  <view class="live-like-layer" @click="handleTap">
    <view
      v-for="item in likeItems"
      :key="item.id"
      class="live-like-heart"
      :style="item.styleText"
    >
      <image class="live-like-heart__image" :src="item.imageSrc" mode="aspectFit" />
    </view>
  </view>
</template>

<script setup>
import { onBeforeUnmount, ref } from 'vue'

import likeImage from '../static/like.png'
import musicImage from '../static/music.png'
import lightningImage from '../static/lightning.png'
import heartImage from '../static/heart.png'
import bananaImage from '../static/banana.png'

const LIKE_ANIMATION_DURATION = 900

defineOptions({ name: 'LiveLikeBurst' })

const emit = defineEmits(['like'])

const likeItems = ref([])
const likeSeed = ref(0)
const removeTimers = ref([])
const likeImageOptions = [likeImage, musicImage, lightningImage, heartImage, bananaImage]

onBeforeUnmount(() => {
  clearTimers()
})

/**
 * 处理直播画布点击，生成一个随机上浮的点赞动画。
 *
 * @param {Object} event 点击事件对象。
 * @returns {void}
 */
function handleTap(event = {}) {
  const point = resolveTapPoint(event)
  createLike(point)
  emit('like', { point })
}

/**
 * 外部播放器原生点击时调用，兼容小程序 video 层级。
 *
 * @param {Object} event 点击事件对象。
 * @returns {void}
 */
function trigger(event = {}) {
  const point = resolveTapPoint(event)
  createLike(point)
  emit('like', { point })
}

/**
 * 生成点赞动画项。
 *
 * @param {{x: number, y: number}} point 点击坐标。
 * @returns {void}
 */
function createLike(point) {
  const id = `like-${Date.now()}-${likeSeed.value}`
  likeSeed.value += 1
  const iconItem = getRandomLikeIcon()
  const driftX = Math.round((Math.random() * 60) - 30)
  const liftY = Math.round(92 + Math.random() * 48)
  const rotate = Math.round((Math.random() * 36) - 18)
  const delay = Math.round(Math.random() * 40)
  likeItems.value = [
    ...likeItems.value,
    {
      id,
      imageSrc: iconItem,
      styleText: `left:${point.x}px;top:${point.y}px;--like-x:${driftX}px;--like-y:-${liftY}px;--like-rotate:${rotate}deg;animation-delay:${delay}ms;`
    }
  ]
  const timer = setTimeout(() => {
    likeItems.value = likeItems.value.filter((item) => item.id !== id)
    removeTimers.value = removeTimers.value.filter((item) => item !== timer)
  }, LIKE_ANIMATION_DURATION + delay + 80)
  removeTimers.value.push(timer)
}

/**
 * 随机获取一个点赞图片。
 *
 * @returns {string} 图片路径。
 */
function getRandomLikeIcon() {
  const index = Math.floor(Math.random() * likeImageOptions.length)
  return likeImageOptions[index] || likeImageOptions[0]
}

/**
 * 从 H5 与小程序事件中提取点击坐标。
 *
 * @param {Object} event 点击事件对象。
 * @returns {{x: number, y: number}} 点击坐标。
 */
function resolveTapPoint(event = {}) {
  const detail = event.detail || {}
  const touches = event.changedTouches && event.changedTouches.length ? event.changedTouches : event.touches
  const touch = touches && touches.length ? touches[0] : null
  const detailX = Number(detail.x || 0)
  const detailY = Number(detail.y || 0)
  if (detailX && detailY) {
    return {
      x: detailX,
      y: detailY
    }
  }
  const clientX = Number(
    (touch ? touch.clientX : 0) ||
    event.clientX ||
    event.pageX ||
    0
  )
  const clientY = Number(
    (touch ? touch.clientY : 0) ||
    event.clientY ||
    event.pageY ||
    0
  )
  const layerRect = getLayerRect(event)
  return {
    x: clientX ? clientX - layerRect.left : 160,
    y: clientY ? clientY - layerRect.top : 260
  }
}

/**
 * 获取点赞层在视口中的位置，H5 点击坐标需要转为层内相对坐标。
 *
 * @param {Object} event 点击事件对象。
 * @returns {{left: number, top: number}} 点赞层偏移。
 */
function getLayerRect(event = {}) {
  const currentTarget = event.currentTarget
  if (currentTarget && typeof currentTarget.getBoundingClientRect === 'function') {
    const rect = currentTarget.getBoundingClientRect()
    return {
      left: Number(rect.left || 0),
      top: Number(rect.top || 0)
    }
  }
  return {
    left: 0,
    top: 0
  }
}

/**
 * 清理点赞动画定时器，页面销毁时避免残留异步任务。
 *
 * @returns {void}
 */
function clearTimers() {
  removeTimers.value.forEach((timer) => {
    clearTimeout(timer)
  })
  removeTimers.value = []
}

defineExpose({ trigger })
</script>

<style lang="scss">
.live-like-layer {
  position: absolute;
  left: 0;
  top: 0;
  right: 0;
  bottom: 0;
  z-index: 4;
}

.live-like-heart {
  position: absolute;
  width: 68rpx;
  height: 68rpx;
  margin-left: -34rpx;
  margin-top: -34rpx;
  opacity: 0;
  pointer-events: none;
  animation: live-like-float 900ms ease-out forwards;
}

.live-like-heart__image {
  display: block;
  width: 100%;
  height: 100%;
}

@keyframes live-like-float {
  0% {
    opacity: 0;
    transform: translate3d(0, 0, 0) scale(0.4) rotate(0deg);
  }
  16% {
    opacity: 1;
    transform: translate3d(0, -16rpx, 0) scale(1) rotate(0deg);
  }
  100% {
    opacity: 0;
    transform: translate3d(var(--like-x), var(--like-y), 0) scale(1.75) rotate(var(--like-rotate));
  }
}

.live-room--landscape .live-like-heart {
  width: 42px;
  height: 42px;
  margin-left: -21px;
  margin-top: -21px;
}

</style>
