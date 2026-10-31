<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="index-diy" :class="{ 'index-diy--mini-nav': showMiniProgramSearchNav }" :style="pageStyle">
    <view v-if="diyGlobalConfig.shell_overlay !== false" class="index-diy__glow"></view>

    <!-- #ifdef MP-WEIXIN -->
    <view
      v-if="showMiniProgramSearchNav"
      class="index-mini-nav"
      :style="miniProgramSearchNavStyle"
    >
      <diy-search
        class="index-mini-nav__search"
        :config="miniProgramSearchConfig"
      />
    </view>
    <!-- #endif -->

    <view v-if="diyLoading" class="index-loading">
      <view class="loading-hero loading-panel">
        <view class="loading-hero__cover skeleton-block"></view>
        <view class="loading-hero__body">
          <view class="loading-line loading-line--title skeleton-block"></view>
          <view class="loading-line loading-line--desc skeleton-block"></view>
          <view class="loading-chip-row">
            <view v-for="item in skeletonChipItems" :key="item" class="loading-chip skeleton-block"></view>
          </view>
        </view>
      </view>

      <view class="loading-card loading-panel">
        <view class="loading-section-head">
          <view class="loading-line loading-line--section skeleton-block"></view>
          <view class="loading-line loading-line--more skeleton-block"></view>
        </view>
        <view class="loading-grid">
          <view v-for="item in skeletonGridItems" :key="item" class="loading-grid__item skeleton-block"></view>
        </view>
      </view>

      <view class="loading-card loading-card--feed loading-panel">
        <view class="loading-section-head">
          <view class="loading-line loading-line--section skeleton-block"></view>
          <view class="loading-line loading-line--more skeleton-block"></view>
        </view>
        <view class="loading-feed">
          <view v-for="item in skeletonFeedItems" :key="item" class="loading-feed__item">
            <view class="loading-feed__thumb skeleton-block"></view>
            <view class="loading-feed__body">
              <view class="loading-line loading-line--card-title skeleton-block"></view>
              <view class="loading-line loading-line--desc loading-line--desc-short skeleton-block"></view>
              <view class="loading-tag-row">
                <view class="loading-tag skeleton-block"></view>
                <view class="loading-tag skeleton-block"></view>
              </view>
            </view>
          </view>
        </view>
      </view>
    </view>

    <view v-else-if="diyComponents.length === 0" class="index-empty">
      <text class="index-empty__title">首页内容准备中</text>
      <text class="index-empty__desc">后台尚未发布首页装修，稍后再来看看吧。</text>
    </view>

    <!-- #ifdef MP-WEIXIN -->
    <diy-page
      v-else
      :components="miniProgramDiyComponents"
      :global-config="diyGlobalConfig"
      :reach-bottom-token="diyReachBottomToken"
    />
    <!-- #endif -->

    <!-- #ifndef MP-WEIXIN -->
    <DiyPageCompat
      v-else
      :components="diyComponents"
      :global-config="diyGlobalConfig"
      :reach-bottom-token="diyReachBottomToken"
    />
    <!-- #endif -->
  </view>
</template>

<script setup>
// #ifndef MP-WEIXIN
import DiyPageCompat from '@/pages/diy/components/diy/DiyPage.vue'
// #endif
import DiySearch from '@/pages/diy/components/diy/DiySearch.vue'
import { computed, ref } from 'vue'
import { onLoad, onPullDownRefresh, onReady, onReachBottom, onShow } from '@dcloudio/uni-app'
import { useDiyPage } from '@/composables/useDiyPage'
import { normalizeDiyComponentType } from '@/utils/diy_adapter'
import { getMiniProgramSearchNavMetrics } from '@/utils/mini_program_nav'

const MINI_PROGRAM_NAV_FALLBACK = {
  statusBarHeight: 24,
  windowWidth: 375,
  navHeight: 44,
  topGap: 6,
  menuHeight: 32,
  searchHeight: 38,
  contentGap: 12,
  leftInset: 12,
  rightInset: 104
}

const skeletonChipItems = [1, 2, 3, 4]
const skeletonGridItems = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]
const skeletonFeedItems = [1, 2, 3]

const {
  diyLoading,
  diyGlobalConfig,
  diyComponents,
  diyReachBottomToken,
  loadDiyPage,
  getDiyBackgroundStyle
} = useDiyPage()

const miniProgramNavMetrics = ref({ ...MINI_PROGRAM_NAV_FALLBACK })

const pageStyle = computed(() => {
  return getDiyBackgroundStyle()
})

const firstDiyComponent = computed(() => {
  return Array.isArray(diyComponents.value) && diyComponents.value.length ? diyComponents.value[0] : null
})

const showMiniProgramSearchNav = computed(() => {
  // #ifdef MP-WEIXIN
  return isSearchComponent(firstDiyComponent.value)
  // #endif
  return false
})

const miniProgramDiyComponents = computed(() => {
  // #ifdef MP-WEIXIN
  if (showMiniProgramSearchNav.value) {
    return diyComponents.value.slice(1)
  }
  // #endif
  return diyComponents.value
})

const miniProgramSearchConfig = computed(() => {
  const component = firstDiyComponent.value || {}
  const config = component.config && typeof component.config === 'object' ? component.config : {}
  return {
    ...config,
    nav_compact: true,
    box_height_px: miniProgramNavMetrics.value.searchHeight
  }
})

const miniProgramSearchNavStyle = computed(() => {
  const metrics = miniProgramNavMetrics.value
  const navHeight = metrics.statusBarHeight + metrics.topGap + metrics.searchHeight
  return [
    `height: ${navHeight + metrics.contentGap}px`,
    `padding-top: ${metrics.statusBarHeight + metrics.topGap}px`,
    `padding-bottom: ${metrics.contentGap}px`,
    `padding-left: ${metrics.leftInset}px`,
    `padding-right: ${metrics.rightInset}px`,
    `background-color: ${miniProgramNavBackgroundColor.value}`
  ].join(';')
})

const miniProgramNavBackgroundColor = computed(() => {
  const background = diyGlobalConfig.value && diyGlobalConfig.value.background ? diyGlobalConfig.value.background : {}
  return background.color || '#f6f6f6'
})

async function loadHomePage() {
  await loadDiyPage('home')
}

async function refreshHomePage() {
  await loadHomePage()
  uni.stopPullDownRefresh()
}

function isSearchComponent(component = {}) {
  if (!component || typeof component !== 'object') {
    return false
  }

  return normalizeDiyComponentType(component.component_type || component.type || '') === 'search'
}

function syncMiniProgramNavMetrics() {
  // #ifdef MP-WEIXIN
  const metrics = getMiniProgramSearchNavMetrics(MINI_PROGRAM_NAV_FALLBACK)
  miniProgramNavMetrics.value = {
    ...metrics,
    navHeight: Math.ceil(metrics.statusBarHeight + metrics.topGap + metrics.searchHeight)
  }
  // #endif
}

onLoad(() => {
  syncMiniProgramNavMetrics()
  loadHomePage()
})

onReady(() => {
  syncMiniProgramNavMetrics()
})

onShow(() => {
  syncMiniProgramNavMetrics()
})

onPullDownRefresh(() => {
  refreshHomePage()
})

onReachBottom(() => {
  diyReachBottomToken.value += 1
})
</script>

<style scoped lang="scss">
.index-diy {
  position: relative;
  overflow: hidden;
}

.index-diy__glow {
  position: absolute;
  top: -180rpx;
  left: -60rpx;
  right: -60rpx;
  height: 520rpx;
  background: radial-gradient(circle at top, rgba(255, 120, 82, 0.22), rgba(255, 120, 82, 0) 68%);
  pointer-events: none;
}

/* #ifdef MP-WEIXIN */
.index-mini-nav {
  position: relative;
  z-index: 4;
  display: flex;
  align-items: flex-start;
  width: 100%;
  min-height: 56px;
  box-sizing: border-box;
}

.index-mini-nav__search {
  display: block;
  flex: 1;
  width: 100%;
  min-height: 38px;
  min-width: 0;
}
/* #endif */

.index-loading,
.index-empty {
  position: relative;
  z-index: 2;
  padding: 32rpx 24rpx 120rpx;
}

.index-loading {
  .skeleton-block {
    background: #eef0f3;
    animation: loading-pulse 1200ms ease-in-out infinite;
  }

  .loading-panel {
    margin-bottom: 24rpx;
    padding: 24rpx;
    border-radius: 32rpx;
    background: rgba(255, 255, 255, 0.74);
    box-shadow: 0 8rpx 24rpx rgba(15, 23, 42, 0.04);
    box-sizing: border-box;
  }

  .loading-hero {
    min-height: 306rpx;
  }

  .loading-hero__cover {
    height: 196rpx;
    border-radius: 26rpx;
  }

  .loading-hero__body {
    margin-top: 22rpx;
  }

  .loading-line {
    height: 26rpx;
    border-radius: 999rpx;
  }

  .loading-line--title {
    width: 46%;
    height: 30rpx;
  }

  .loading-line--desc {
    width: 74%;
    margin-top: 16rpx;
  }

  .loading-chip-row {
    display: flex;
    flex-wrap: wrap;
    margin-top: 18rpx;
  }

  .loading-chip {
    width: 112rpx;
    height: 40rpx;
    margin-right: 12rpx;
    margin-top: 12rpx;
    border-radius: 999rpx;
  }

  .loading-section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20rpx;
  }

  .loading-line--section {
    width: 182rpx;
    height: 28rpx;
  }

  .loading-line--more {
    width: 84rpx;
    height: 22rpx;
  }

  .loading-grid {
    display: flex;
    flex-wrap: wrap;
    margin: -8rpx -8rpx 0;

    &__item {
      width: 122rpx;
      height: 108rpx;
      margin: 8rpx;
      border-radius: 26rpx;
    }
  }

  .loading-feed {
    display: flex;
    flex-direction: column;
  }

  .loading-feed__item {
    display: flex;
    margin-top: 22rpx;

    &:first-child {
      margin-top: 0;
    }
  }

  .loading-feed__thumb {
    flex-shrink: 0;
    width: 168rpx;
    height: 168rpx;
    border-radius: 24rpx;
  }

  .loading-feed__body {
    flex: 1;
    min-width: 0;
    margin-left: 16rpx;
    padding-top: 8rpx;
  }

  .loading-line--card-title {
    width: 84%;
    height: 28rpx;
  }

  .loading-line--desc-short {
    width: 62%;
    margin-top: 18rpx;
  }

  .loading-tag-row {
    display: flex;
    align-items: center;
    margin-top: 18rpx;
  }

  .loading-tag {
    width: 88rpx;
    height: 36rpx;
    margin-right: 10rpx;
    border-radius: 999rpx;
  }

  .loading-card--feed {
    padding-bottom: 28rpx;
  }
}

.index-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 60vh;
  text-align: center;

  &__title {
    font-size: 34rpx;
    font-weight: 700;
    color: #2d2f2f;
  }

  &__desc {
    margin-top: 16rpx;
    font-size: 24rpx;
    line-height: 1.7;
    color: #7b7d7d;
  }
}

@keyframes loading-pulse {
  0% {
    opacity: 0.68;
  }

  50% {
    opacity: 1;
  }

  100% {
    opacity: 0.68;
  }
}
</style>
