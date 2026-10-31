<template>
  <view
    v-if="visible"
    class="sheet-panel sheet-panel--goods"
    :class="panelClass"
    :style="panelStyleText"
    @click.stop
    @touchmove.stop.prevent="noop"
  >
    <view
      class="sheet-panel__drag-area"
      @touchstart.stop="handleSheetTouchStart"
      @touchmove.stop.prevent="handleSheetTouchMove"
      @touchend.stop="handleSheetTouchEnd"
      @touchcancel.stop="handleSheetTouchEnd"
    >
      <view class="sheet-panel__handle"></view>
    </view>
    <view class="sheet-panel__header sheet-panel__header--tabs">
      <view class="sheet-tabs">
        <view class="sheet-tabs__item" :class="activeTab === 'goods' ? 'sheet-tabs__item--active' : ''" @click="handleTabChange('goods')">
          <text>商品</text>
        </view>
        <view class="sheet-tabs__item" :class="activeTab === 'special' ? 'sheet-tabs__item--active' : ''" @click="handleTabChange('special')">
          <text>专题</text>
        </view>
        <view class="sheet-tabs__item" :class="activeTab === 'content' ? 'sheet-tabs__item--active' : ''" @click="handleTabChange('content')">
          <text>内容</text>
        </view>
      </view>
      <view class="sheet-panel__close" @click="$emit('close')">
        <text class="iconfont icon-close"></text>
      </view>
    </view>

    <scroll-view scroll-y class="sheet-panel__scroll sheet-panel__scroll--goods">
      <view v-if="loading" class="sheet-empty-state sheet-empty-state--popup">
        <text class="sheet-empty-state__title">商品加载中...</text>
      </view>
      <view v-else-if="error" class="sheet-empty-state sheet-empty-state--popup" hover-class="sheet-empty-state--pressed" @click="$emit('retry')">
        <text class="sheet-empty-state__title">{{ error }}，点击重试</text>
        <text class="sheet-empty-state__desc">请检查网络后重新加载。</text>
      </view>

      <template v-else-if="activeTab === 'goods'">
        <view v-if="productItems.length" class="goods-popup-list">
          <view
            v-for="item in productItems"
            :key="item.id"
            class="goods-popup-card"
            hover-class="goods-popup-card--pressed"
            @click="$emit('goods-action', item)"
          >
            <view class="goods-popup-card__media">
              <image v-if="item.image" class="goods-popup-card__image" :src="item.image" mode="aspectFill" />
              <view v-else class="goods-popup-card__image goods-popup-card__image--fallback">
                <text>{{ item.initial }}</text>
              </view>
              <view v-if="item.isExplaining" class="goods-popup-card__explain">
                <text>正在讲解</text>
              </view>
            </view>
            <view class="goods-popup-card__body">
              <text class="goods-popup-card__title"><text v-if="item.tag" class="goods-popup-card__title-badge">{{ item.tag }}</text>{{ item.name }}</text>
              <view class="goods-popup-card__price-row">
                <text class="goods-popup-card__price">{{ item.price }}</text>
                <text v-if="item.oldPrice" class="goods-popup-card__old">{{ item.oldPrice }}</text>
              </view>
              <view class="goods-popup-card__meta">
                <text class="goods-popup-card__sales">{{ item.salesText }}</text>
                <view class="goods-popup-card__action" hover-class="goods-popup-card__action--pressed" @click.stop="$emit('goods-action', item)">
                  <text :class="item.actionIconClass"></text>
                </view>
              </view>
            </view>
          </view>
        </view>
        <view v-else class="sheet-empty-state sheet-empty-state--popup">
          <text class="sheet-empty-state__title">当前没有挂载商品</text>
          <text class="sheet-empty-state__desc">直播间商品上架后会展示在这里。</text>
        </view>
      </template>

      <template v-else>
        <view class="content-searchbar">
          <text class="iconfont icon-search content-searchbar__icon"></text>
          <input
            :value="keyword"
            class="content-searchbar__input"
            :placeholder="searchPlaceholder"
            placeholder-class="content-searchbar__placeholder"
            @input="handleKeywordInput"
          />
        </view>

        <view v-if="filteredPopupItems.length" class="content-popup-list">
          <view v-for="item in filteredPopupItems" :key="item.id" class="content-popup-card" @click="$emit('content-action', item)">
            <view class="content-popup-card__media">
              <image v-if="item.cover" class="content-popup-card__image" :src="item.cover" mode="aspectFill" />
              <view v-else class="content-popup-card__image content-popup-card__image--fallback">
                <text class="iconfont icon-pic"></text>
              </view>
              <view class="content-popup-card__play">
                <text class="iconfont icon-playfill"></text>
              </view>
            </view>
            <view class="content-popup-card__body">
              <view class="content-popup-card__title-row">
                <text v-if="item.tag" class="content-popup-card__tag">{{ item.tag }}</text>
                <text class="content-popup-card__title">{{ item.title }}</text>
              </view>
              <text v-if="item.subtitle" class="content-popup-card__sub">{{ item.subtitle }}</text>
              <view class="content-popup-card__price-row">
                <text class="content-popup-card__price">{{ item.price }}</text>
                <text v-if="item.oldPrice" class="content-popup-card__old">{{ item.oldPrice }}</text>
              </view>
              <view class="content-popup-card__footer">
                <view></view>
                <view class="content-popup-card__buy" @click.stop="$emit('buy', item)">
                  <text class="iconfont icon-right"></text>
                </view>
              </view>
            </view>
          </view>
        </view>
        <view v-else class="sheet-empty-state sheet-empty-state--popup">
          <text class="sheet-empty-state__title">{{ emptyTitle }}</text>
          <text class="sheet-empty-state__desc">{{ emptyDesc }}</text>
        </view>
      </template>
    </scroll-view>
  </view>
</template>

<script>
// 小程序样式隔离选项：共享页面样式，需保留为普通 script 导出（script setup 无法声明组件选项）
export default {
  options: {
    styleIsolation: 'shared'
  }
}
</script>

<script setup>
import { computed, getCurrentInstance, nextTick, onMounted, ref, watch } from 'vue'

defineOptions({ name: 'LiveGoodsSheet' })

const props = defineProps({
  visible: Boolean,
  animationState: {
    type: String,
    default: ''
  },
  landscape: Boolean,
  goodsItems: {
    type: Array,
    default: () => []
  },
  loading: Boolean,
  error: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['close', 'goods-action', 'content-action', 'buy', 'retry'])

const instance = getCurrentInstance()

const activeTab = ref('goods')
const keyword = ref('')
const expanded = ref(false)
const maxHeight = ref(0)
const baseHeight = ref(0)
const dragHeight = ref(0)
const dragStartHeight = ref(0)
const dragging = ref(false)
const touchStartY = ref(0)
const touchDeltaY = ref(0)

const panelClass = computed(() => {
  const classNames = []
  if (props.animationState === 'enter') classNames.push('sheet-panel--enter')
  if (props.landscape) classNames.push('sheet-panel--landscape')
  return classNames.join(' ')
})

const productItems = computed(() => {
  return props.goodsItems.filter((item) => item.goodsType === 'product')
})

const specialItems = computed(() => {
  return props.goodsItems
    .filter((item) => item.targetType === 'special')
    .map((item) => ({
      ...item,
      title: item.name,
      subtitle: item.displaySubtitle,
      cover: item.image
    }))
})

const contentItems = computed(() => {
  return props.goodsItems
    .filter((item) => item.targetType === 'article')
    .map((item) => ({
      ...item,
      title: item.name,
      subtitle: item.displaySubtitle || item.subtitle,
      cover: item.image
    }))
})

const popupItems = computed(() => {
  return activeTab.value === 'special' ? specialItems.value : contentItems.value
})

const filteredPopupItems = computed(() => {
  const rawKeyword = String(keyword.value || '').trim().toLowerCase()
  if (!rawKeyword) return popupItems.value
  return popupItems.value.filter((item) => String(item.title || '').toLowerCase().includes(rawKeyword))
})

const searchPlaceholder = computed(() => {
  return activeTab.value === 'special' ? '搜索专题名称' : '搜索内容名称'
})

const emptyTitle = computed(() => {
  return activeTab.value === 'special' ? '当前没有关联专题' : '当前没有关联内容'
})

const emptyDesc = computed(() => {
  return activeTab.value === 'special' ? '直播间关联专题后会展示在这里。' : '直播间关联内容后会展示在这里。'
})

const panelStyleText = computed(() => {
  if (!maxHeight.value) return ''
  if (dragging.value && dragHeight.value) {
    return `height:${dragHeight.value}px;max-height:${maxHeight.value}px;`
  }
  if (expanded.value) {
    return `height:${maxHeight.value}px;max-height:${maxHeight.value}px;`
  }
  return `max-height:${maxHeight.value}px;`
})

watch(() => props.visible, (value) => {
  if (value) {
    activeTab.value = 'goods'
    resetDragState()
    initMaxHeight()
    measureBaseHeight()
    return
  }
  resetDragState()
  keyword.value = ''
})

onMounted(() => {
  if (props.visible) {
    initMaxHeight()
    measureBaseHeight()
  }
})

function noop() {}

function initMaxHeight() {
  try {
    const systemInfo = uni.getSystemInfoSync()
    const windowHeight = Number(systemInfo.windowHeight || systemInfo.screenHeight || 0)
    maxHeight.value = windowHeight > 0 ? Math.floor(windowHeight * 0.8) : 0
  } catch (error) {
    maxHeight.value = 0
  }
}

function resetDragState() {
  expanded.value = false
  baseHeight.value = 0
  dragHeight.value = 0
  dragStartHeight.value = 0
  dragging.value = false
  touchStartY.value = 0
  touchDeltaY.value = 0
}

function measureBaseHeight() {
  nextTick(() => {
    if (!props.visible || expanded.value || dragging.value) return
    const query = uni.createSelectorQuery().in(instance.proxy)
    query.select('.sheet-panel--goods').boundingClientRect((rect) => {
      if (!rect || !props.visible || expanded.value || dragging.value) return
      const height = Number(rect.height || 0)
      if (height > 0) {
        baseHeight.value = normalizeBaseHeight(height)
      }
    }).exec()
  })
}

function getTouchClientY(event = {}) {
  const touches = event.changedTouches && event.changedTouches.length ? event.changedTouches : event.touches
  const touch = touches && touches.length ? touches[0] : null
  return touch ? Number(touch.clientY || 0) : 0
}

function handleTabChange(tab) {
  activeTab.value = tab
  keyword.value = ''
  measureBaseHeight()
}

function handleKeywordInput(event = {}) {
  keyword.value = event.detail ? event.detail.value : ''
}

function getMinBaseHeight() {
  const maxHeightValue = Number(maxHeight.value || 0)
  return maxHeightValue > 0 ? Math.floor(maxHeightValue * 0.65) : 0
}

function normalizeBaseHeight(height) {
  const currentHeight = Number(height || 0)
  const minBaseHeight = getMinBaseHeight()
  if (!minBaseHeight) return currentHeight
  if (!currentHeight) return minBaseHeight
  return Math.min(currentHeight, minBaseHeight)
}

function handleSheetTouchStart(event) {
  if (!props.visible) return
  if (!maxHeight.value) initMaxHeight()
  if (!baseHeight.value) measureBaseHeight()
  if (!baseHeight.value) baseHeight.value = getMinBaseHeight()
  touchStartY.value = getTouchClientY(event)
  touchDeltaY.value = 0
  dragStartHeight.value = expanded.value ? maxHeight.value : (baseHeight.value || maxHeight.value)
  dragging.value = true
  dragHeight.value = dragStartHeight.value
}

function handleSheetTouchMove(event) {
  if (!props.visible || !dragging.value || !touchStartY.value) return
  touchDeltaY.value = getTouchClientY(event) - touchStartY.value
  const startHeight = dragStartHeight.value || (expanded.value ? maxHeight.value : (baseHeight.value || maxHeight.value))
  const nextHeight = startHeight - touchDeltaY.value
  dragHeight.value = Math.max(baseHeight.value || 0, Math.min(maxHeight.value || nextHeight, nextHeight))
}

function handleSheetTouchEnd() {
  if (!dragging.value) return
  const threshold = 48
  const deltaY = Number(touchDeltaY.value || 0)
  if (deltaY <= -threshold) {
    if (!maxHeight.value) initMaxHeight()
    expanded.value = true
  } else if (deltaY >= threshold) {
    expanded.value = false
  }
  dragging.value = false
  dragHeight.value = 0
  dragStartHeight.value = 0
  touchStartY.value = 0
  touchDeltaY.value = 0
}
</script>

<style lang="scss" src="./style.scss"></style>
