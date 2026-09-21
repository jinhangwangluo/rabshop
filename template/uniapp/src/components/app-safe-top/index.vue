<template>
  <view class="app-safe-top" :style="containerStyle">
    <!-- #ifdef MP-WEIXIN -->
    <view class="app-safe-top__block" :style="miniProgramStyle"></view>
    <!-- #endif -->

    <!-- #ifdef APP-PLUS -->
    <view class="app-safe-top__block" :style="appStyle"></view>
    <!-- #endif -->

    <view v-if="showHeader" class="app-safe-top__nav">
      <view
        v-if="shouldShowBack"
        class="app-safe-top__back"
        hover-class="app-safe-top__back--hover"
        @click="handleBack"
      >
        <uni-icons :type="backIcon" size="18" :color="iconColor"></uni-icons>
      </view>
      <text v-if="title" class="app-safe-top__title" :style="titleStyle">{{ title }}</text>
      <view v-if="showRightSlot" class="app-safe-top__right">
        <slot name="right"></slot>
      </view>
    </view>

    <slot></slot>
  </view>
</template>

<script setup>
import { computed, onMounted, ref, useSlots } from 'vue'
import { isWechatBrowser } from '@/utils/platform'

defineOptions({
  name: 'AppSafeTop'
})

const props = defineProps({
  background: {
    type: String,
    default: 'transparent'
  },
  mpExtra: {
    type: String,
    default: '0rpx'
  },
  appExtra: {
    type: String,
    default: '0rpx'
  },
  title: {
    type: String,
    default: ''
  },
  titleColor: {
    type: String,
    default: 'var(--text-title, #1a1c1c)'
  },
  iconColor: {
    type: String,
    default: 'var(--text-title, #1a1c1c)'
  },
  showBack: {
    type: Boolean,
    default: false
  },
  emitBack: {
    type: Boolean,
    default: false
  },
  autoBack: {
    type: Boolean,
    default: true
  },
  backIcon: {
    type: String,
    default: 'left'
  }
})

const emit = defineEmits(['back'])

const slots = useSlots()
const canGoBack = ref(false)
const currentRoute = ref('')
const statusBarHeightRpx = ref(0)
const tabBarPages = [
  'pages/index/index',
  'pages/category/index',
  'pages/cart/index',
  'pages/user/index'
]

const showRightSlot = computed(() => Boolean(slots.right))
const isTabBarPage = computed(() => tabBarPages.indexOf(currentRoute.value) !== -1)
const shouldShowBack = computed(() => {
  if (isTabBarPage.value) {
    return false
  }

  return props.showBack || (props.autoBack && canGoBack.value)
})
const isWechatH5 = computed(() => isWechatBrowser())
const showHeader = computed(() => {
  if (isWechatH5.value) {
    return false
  }

  return Boolean(props.title) || shouldShowBack.value || showRightSlot.value
})
const containerStyle = computed(() => `background: ${props.background};`)
const miniProgramStyle = computed(() => buildStyle(props.mpExtra))
const appStyle = computed(() => buildStyle(props.appExtra))
const titleStyle = computed(() => `color: ${props.titleColor};`)

/**
 * 生成当前端的安全区占位样式。
 *
 * @param {string} extraHeight 额外补充的顶部高度。
 * @returns {string} 内联样式字符串。
 */
function buildStyle(extraHeight) {
  return `height: calc(${statusBarHeightRpx.value}rpx + ${normalizeHeightValue(extraHeight)}); background: ${props.background};`
}

/**
 * 同步当前设备的状态栏高度，并转换为 rpx 供小程序样式使用。
 *
 * @returns {void}
 */
function syncStatusBarHeight() {
  const systemInfo = typeof uni.getSystemInfoSync === 'function' ? uni.getSystemInfoSync() : {}
  let statusBarHeight = Number(systemInfo.statusBarHeight || 0)

  // #ifdef MP-WEIXIN
  if (!statusBarHeight) {
    statusBarHeight = 44
  }
  // #endif

  statusBarHeightRpx.value = pxToRpx(statusBarHeight, systemInfo)
}

/**
 * 将平台 API 返回的 px 高度转换为 rpx。
 *
 * @param {number|string} value 需要转换的 px 数值。
 * @param {Object} systemInfo 设备信息，优先使用 windowWidth 计算换算比例。
 * @returns {number} 转换后的 rpx 数值。
 */
function pxToRpx(value, systemInfo = {}) {
  const px = Number(value || 0)
  const windowWidth = Number(systemInfo.windowWidth || systemInfo.screenWidth || 375)

  if (!px || !windowWidth) {
    return 0
  }

  return Math.ceil((px * 750) / windowWidth)
}

/**
 * 归一化额外顶部高度，避免 calc() 中出现无单位数值导致小程序解析不稳定。
 *
 * @param {number|string} value 额外高度，支持数字、数字字符串或带单位字符串。
 * @returns {string} 可直接用于内联样式的高度值。
 */
function normalizeHeightValue(value) {
  if (value === null || value === undefined || value === '') {
    return '0rpx'
  }

  if (typeof value === 'number') {
    return `${value}rpx`
  }

  const height = String(value).trim()
  if (/^-?\d+(\.\d+)?$/.test(height)) {
    return `${height}rpx`
  }

  return height
}

/**
 * 同步当前页面是否可返回上一页。
 *
 * @returns {void}
 */
function syncCanGoBack() {
  const pages = getCurrentPages()
  const currentPage = Array.isArray(pages) && pages.length ? pages[pages.length - 1] : null
  currentRoute.value = currentPage && currentPage.route ? currentPage.route : ''
  canGoBack.value = Array.isArray(pages) && pages.length > 1
}

/**
 * 返回上一页。
 *
 * @returns {void}
 */
function handleBack() {
  emit('back')
  if (props.emitBack) {
    return
  }
  if (!canGoBack.value) {
    return
  }

  uni.navigateBack()
}

syncStatusBarHeight()
onMounted(() => {
  syncCanGoBack()
})
</script>

<style lang="scss" scoped>
.app-safe-top {
  width: 100%;
  flex-shrink: 0;
}

.app-safe-top__block {
  width: 100%;
}

.app-safe-top__nav {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 74rpx;
  padding: 0 112rpx;
}

.app-safe-top__back,
.app-safe-top__right {
  position: absolute;
  top: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 64rpx;
  min-height: 64rpx;
  transform: translateY(-50%);
}

.app-safe-top__back {
  left: 24rpx;
  height: 64rpx;
}

.app-safe-top__right {
  right: 24rpx;
}

.app-safe-top__back--hover {
  opacity: 0.7;
}

.app-safe-top__title {
  width: 100%;
  overflow: hidden;
  font-size: 32rpx;
  font-weight: 600;
  line-height: 44rpx;
  text-align: center;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
