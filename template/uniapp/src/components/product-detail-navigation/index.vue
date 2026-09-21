<template>
  <view class="product-detail-navigation">
    <view class="nav-layer" :class="{ 'nav-layer--fixed': pinned }">
      <view class="nav-layer__bar" :style="barStyle">
        <view class="nav-pill nav-pill--left">
          <view
            class="nav-pill__icon"
            hover-class="nav-pill__icon--hover"
            @click="handleBack"
          >
            <text class="iconfont icon-back nav-pill__font"></text>
          </view>
          <view class="nav-pill__divider"></view>
          <view
            class="nav-pill__icon"
            hover-class="nav-pill__icon--hover"
            @click="toggleTopMenu"
          >
            <text
              class="iconfont icon-liebiao nav-pill__font nav-pill__font--menu"
            ></text>
          </view>
        </view>
        <view class="nav-pill nav-pill--right">
          <view
            class="nav-pill__icon"
            hover-class="nav-pill__icon--hover"
            @click="shareProduct"
          >
            <text class="iconfont icon-fenxiang nav-pill__font"></text>
          </view>
        </view>
      </view>
      <view v-if="menuVisible" class="top-menu">
        <view class="top-menu__arrow"></view>
        <view
          v-for="item in topMenuItems"
          :key="item.id"
          class="top-menu__item"
          :class="{
            'top-menu__item--first': item.first,
            'top-menu__item--last': item.last
          }"
          hover-class="top-menu__item--hover"
          @click="handleTopMenuSelect(item)"
        >
          <text :class="item.iconClass"></text>
          <text class="top-menu__text">{{ item.title }}</text>
        </view>
      </view>
    </view>
    <view
      v-if="menuVisible"
      class="top-menu__mask"
      @click="closeTopMenu"
    ></view>
  </view>
</template>

<script setup>
import { computed } from 'vue'
import { getMiniProgramSearchNavMetrics } from '@/utils/mini_program_nav'

defineOptions({ name: 'ProductDetailNavigation' })
defineProps({
  pinned: { type: Boolean, default: false },
  menuVisible: { type: Boolean, default: false }
})
const emit = defineEmits(['back', 'share', 'toggle', 'close'])
const topMenuItems = [
  {
    id: 'home',
    title: '首页',
    iconClass: 'iconfont icon-shop top-menu__icon',
    url: '/pages/index/index',
    tab: true,
    first: true
  },
  {
    id: 'search',
    title: '搜索',
    iconClass: 'iconfont icon-search top-menu__icon',
    url: '/pages/contents/search/index',
    tab: false
  },
  {
    id: 'cart',
    title: '购物车',
    iconClass: 'iconfont icon-cart top-menu__icon',
    url: '/pages/cart/index',
    tab: true
  },
  {
    id: 'favorite',
    title: '我的收藏',
    iconClass: 'iconfont icon-favor top-menu__icon',
    url: '/pages/users/collect/index',
    tab: false
  },
  {
    id: 'user',
    title: '个人中心',
    iconClass: 'iconfont icon-profile top-menu__icon',
    url: '/pages/user/index',
    tab: true,
    last: true
  }
]

const barStyle = computed(() => {
  // #ifdef MP-WEIXIN
  const metrics = getMiniProgramSearchNavMetrics()
  return `padding-top: ${
    metrics.statusBarHeight + metrics.topGap
  }px; padding-right: ${metrics.rightInset}px; padding-bottom: ${
    metrics.topGap
  }px;`
  // #endif
  // #ifndef MP-WEIXIN
  return ''
  // #endif
})

/** 返回上一页，先收起菜单。
 * @returns {void}
 */
function handleBack() {
  closeTopMenu()
  emit('back')
}
/** 打开当前商品分享弹窗。
 * @returns {void}
 */
function shareProduct() {
  closeTopMenu()
  emit('share')
}
/** 切换快捷菜单。
 * @returns {void}
 */
function toggleTopMenu() {
  emit('toggle')
}
/** 关闭快捷菜单。
 * @returns {void}
 */
function closeTopMenu() {
  emit('close')
}
/** 复用普通商品详情的快捷菜单及路由类型。
 * @param {Object} item 当前菜单项。
 * @returns {void}
 */
function handleTopMenuSelect(item) {
  closeTopMenu()
  if (item.tab) uni.switchTab({ url: item.url })
  else uni.navigateTo({ url: item.url })
}
</script>

<style lang="scss" scoped>
.nav-layer {
  position: absolute;
  top: 0;
  right: 0;
  left: 0;
  z-index: 45;
}

.nav-layer--fixed {
  position: fixed;
  background: var(--surface-card, #ffffff);
  box-shadow: 0 4rpx 12rpx rgba(15, 23, 42, 0.03);
}

.nav-layer__bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16rpx 24rpx;
}

.nav-pill {
  display: flex;
  align-items: center;
  height: 56rpx;
  border-radius: 999rpx;
  background: rgba(255, 255, 255, 0.82);
  border: 1rpx solid rgba(0, 0, 0, 0.1);
}

.nav-pill--left {
  width: 138rpx;
}

.nav-pill--right {
  width: 56rpx;
  height: 56rpx;
  border-radius: 50%;
}

.nav-pill--right .nav-pill__icon {
  width: 56rpx;
  height: 56rpx;
}

.nav-pill__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 56rpx;
  height: 56rpx;
  color: var(--text-title, #111111);
}

.nav-pill__icon--hover {
  opacity: 0.72;
}

.nav-pill__divider {
  width: 1px;
  height: 32rpx;
  background: rgba(0, 0, 0, 0.12);
}

.nav-pill__font {
  color: var(--text-title, #111111);
  font-size: 36rpx;
  line-height: 1;
}

.nav-pill__font--menu {
  font-size: 38rpx;
}

.nav-pill__font--more {
  font-size: 34rpx;
}

.nav-pill__font--home {
  font-size: 34rpx;
}

.top-menu__mask {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  z-index: 40;
  background: rgba(0, 0, 0, 0);
}

.top-menu {
  position: absolute;
  top: 100%;
  left: 60rpx;
  z-index: 46;
  width: 272rpx;
  border-radius: 14rpx;
  background: var(--surface-card, #ffffff);
  box-shadow: 0 12rpx 34rpx rgba(15, 23, 42, 0.1);
}

.top-menu__arrow {
  position: absolute;
  top: -12rpx;
  left: 126rpx;
  width: 28rpx;
  height: 28rpx;
  border-radius: 4rpx;
  background: var(--surface-card, #ffffff);
  transform: rotate(45deg);
}

.top-menu__item {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  height: 92rpx;
  padding: 0 24rpx;
  background: var(--surface-card, #ffffff);
}

.top-menu__item--first {
  border-top-left-radius: 14rpx;
  border-top-right-radius: 14rpx;
}

.top-menu__item--last {
  border-bottom-right-radius: 14rpx;
  border-bottom-left-radius: 14rpx;
}

.top-menu__item + .top-menu__item {
  border-top: 1px solid var(--border-light, #eeeeee);
}

.top-menu__item--hover {
  background: var(--surface-card-muted, #f7f7f7);
}

.top-menu__icon {
  width: 64rpx;
  flex-shrink: 0;
  color: var(--text-regular, #2f3033);
  font-size: 34rpx;
  line-height: 38rpx;
}

.top-menu__text {
  color: var(--text-regular, #2f3033);
  font-size: 30rpx;
  line-height: 42rpx;
  font-weight: 700;
}
</style>
