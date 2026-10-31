<template>
  <view class="merchant-bottom-nav">
    <view
      v-for="item in navItems"
      :key="item.key"
      :class="['merchant-bottom-nav__item', { 'merchant-bottom-nav__item--active': item.key === activeKey }]"
      hover-class="merchant-bottom-nav__item--hover"
      @click="handleNavigate(item)"
    >
      <text :class="['iconfont', item.icon, 'merchant-bottom-nav__icon']"></text>
      <text class="merchant-bottom-nav__text">{{ item.text }}</text>
    </view>
  </view>
</template>

<script setup>
import { computed } from 'vue'

const NAV_ITEMS = [
  { key: 'home', text: '首页', icon: 'icon-home', action: 'redirectTo', url: '/pages/merchant/index/index' },
  { key: 'category', text: '分类', icon: 'icon-liebiao1', action: 'redirectTo', url: '/pages/merchant/category/index' },
  { key: 'topic', text: '专题', icon: 'icon-record', action: 'redirectTo', url: '/pages/merchant/special/index' },
  { key: 'account', text: '账户', icon: 'icon-profile', action: 'redirectTo', url: '/pages/merchant/user/index' }
]

defineOptions({ name: 'MerchantBottomNav' })

const props = defineProps({
  activeKey: {
    type: String,
    default: 'home'
  },
  merchantId: {
    type: [Number, String],
    default: 0
  },
  menuMode: {
    type: String,
    default: 'special'
  },
  accountText: {
    type: String,
    default: '账户'
  }
})

const navItems = computed(() => {
  return NAV_ITEMS.map((item) => ({
    ...item,
    text: item.key === 'topic' && props.menuMode === 'product' ? '商品' : item.text,
    icon: item.key === 'topic' && props.menuMode === 'product' ? 'icon-cart' : item.icon,
    url: appendMerchantId(item.url)
  }))
})

/**
 * 为导航地址追加商户ID。
 *
 * @param {string} url 原始地址。
 * @returns {string} 带商户ID的地址。
 */
function appendMerchantId(url = '') {
  const merchantId = Number(props.merchantId || 0)
  const targetUrl = String(url || '').trim()
  if (!targetUrl || merchantId <= 0) {
    return targetUrl
  }

  return targetUrl.indexOf('?') > -1
    ? `${targetUrl}&merchant_id=${merchantId}`
    : `${targetUrl}?merchant_id=${merchantId}`
}

/**
 * 处理底部导航跳转。
 *
 * @param {Object} item 导航项配置。
 * @returns {void}
 */
function handleNavigate(item = {}) {
  if (!item.key || item.key === props.activeKey || !item.url) {
    return
  }

  if (item.action === 'switchTab') {
    uni.switchTab({ url: item.url })
    return
  }

  if (item.action === 'redirectTo') {
    uni.redirectTo({
      url: item.url,
      fail: () => {
        uni.navigateTo({
          url: item.url,
          fail: () => {
            uni.showToast({ title: '页面待接入', icon: 'none' })
          }
        })
      }
    })
    return
  }

  uni.navigateTo({
    url: item.url,
    fail: () => {
      uni.showToast({ title: '页面待接入', icon: 'none' })
    }
  })
}
</script>

<style lang="scss" scoped>
.merchant-bottom-nav {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  height: 96rpx;
  padding: 8rpx 32rpx 12rpx;
  border-top: 1rpx solid #ddd8d5;
  background: #ffffff;
  box-shadow: 0 -4rpx 16rpx rgba(15, 23, 42, 0.06);
  display: flex;
  align-items: center;
  justify-content: space-between;
  box-sizing: border-box;
  z-index: 20;
}

/* #ifdef MP-WEIXIN */
.merchant-bottom-nav {
  height: calc(96rpx + env(safe-area-inset-bottom));
  padding-bottom: calc(12rpx + env(safe-area-inset-bottom));
}
/* #endif */

/* #ifdef H5 */
.merchant-bottom-nav {
  bottom: var(--window-bottom, 0);
}
/* #endif */

.merchant-bottom-nav__item {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.merchant-bottom-nav__item--hover {
  opacity: 0.84;
}

.merchant-bottom-nav__icon {
  font-size: 40rpx;
  line-height: 40rpx;
  color: #888992;
}

.merchant-bottom-nav__text {
  margin-top: 4rpx;
  font-size: 20rpx;
  line-height: 24rpx;
  font-weight: 500;
  color: #888992;
}

.merchant-bottom-nav__item--active .merchant-bottom-nav__icon,
.merchant-bottom-nav__item--active .merchant-bottom-nav__text {
  color: var(--brand-primary, #ea580c);
}

.merchant-bottom-nav__item--active .merchant-bottom-nav__text {
  font-weight: 700;
}
</style>
