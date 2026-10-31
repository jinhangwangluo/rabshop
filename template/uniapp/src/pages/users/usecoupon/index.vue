<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="coupon-page">
    <app-safe-top title="我的优惠券" background="#fff">
    </app-safe-top>

    <view class="coupon-page__content">

      <scroll-view scroll-x class="coupon-tab-scroll" :show-scrollbar="false">
        <view class="coupon-tab-scroll__inner">
          <view
            v-for="tab in couponTabs"
            :key="tab.value"
            :class="['coupon-tab', { 'coupon-tab--active': activeStatus === tab.value }]"
            hover-class="coupon-tab--hover"
            @click="handleSelectStatus(tab.value)"
          >
            <text :class="['coupon-tab__text', { 'coupon-tab__text--active': activeStatus === tab.value }]">
              {{ tab.label }}
            </text>
          </view>
        </view>
      </scroll-view>

      <view v-if="refreshing" class="coupon-refreshing">
        <text class="coupon-refreshing__text">刷新中</text>
      </view>

      <view class="coupon-list">
        <view v-if="pageLoading" class="coupon-loading">
          <text class="iconfont icon-loading coupon-loading__icon"></text>
          <text class="coupon-loading__text">加载优惠券中</text>
        </view>

        <template v-else-if="coupons.length">
          <view
            v-for="coupon in coupons"
            :key="coupon.id"
            :class="['coupon-card', { 'coupon-card--disabled': coupon.disabled }]"
            hover-class="coupon-card--hover"
            @click="handleCouponClick(coupon)"
          >
            <view class="coupon-card__notch coupon-card__notch--left"></view>
            <view class="coupon-card__notch coupon-card__notch--right"></view>

            <view class="coupon-card__amount-column">
              <text class="coupon-card__amount-symbol">¥</text>
              <text class="coupon-card__amount-value">{{ coupon.amount }}</text>
              <text class="coupon-card__condition">{{ coupon.condition }}</text>
            </view>

            <view class="coupon-card__main">
              <view class="coupon-card__header">
                <text :class="['coupon-card__badge', { 'coupon-card__badge--muted': coupon.disabled }]">{{ coupon.statusText }}</text>
                <text class="coupon-card__source">{{ coupon.receiveTypeText }}</text>
              </view>
              <text class="coupon-card__title">{{ coupon.title }}</text>
              <text class="coupon-card__desc">{{ coupon.expireText }}</text>
            </view>

            <view class="coupon-card__action-wrap">
              <view
                :class="['coupon-card__action', { 'coupon-card__action--disabled': coupon.disabled }]"
                hover-class="coupon-card__action--hover"
                @click.stop="handleUseCoupon(coupon)"
              >
                <text class="coupon-card__action-text">{{ coupon.actionText }}</text>
                <text v-if="!coupon.disabled" class="iconfont icon-right coupon-card__action-icon"></text>
              </view>
            </view>
          </view>

          <view v-if="loadingMore" class="coupon-list__footer">
            <text class="coupon-list__footer-text">加载更多优惠券</text>
          </view>
          <view v-else-if="!hasMore" class="coupon-list__footer">
            <text class="coupon-list__footer-text">没有更多优惠券了</text>
          </view>
        </template>

        <view v-else class="empty-state">
          <image class="empty-state__image" src="/static/images/no_coupon.png" mode="aspectFit"></image>
        </view>
      </view>
    </view>

    <view class="safe-bottom"></view>

    <app-floating-button />
  </view>
</template>

<script setup>
import { getUserCouponList } from '@/api/coupon'
import { computed, ref } from 'vue'
import { onLoad, onPullDownRefresh, onReachBottom } from '@dcloudio/uni-app'

const STATUS_UNUSED = 0
const STATUS_USED = 1
const STATUS_EXPIRED = 2

function getCouponTabs() {
  return [
    { label: '未使用', value: STATUS_UNUSED },
    { label: '已使用', value: STATUS_USED },
    { label: '已过期', value: STATUS_EXPIRED }
  ]
}

function formatMoney(value) {
  const numberValue = Number(value || 0)
  if (!numberValue) return '0'
  return Number.isInteger(numberValue) ? String(numberValue) : numberValue.toFixed(2)
}

function formatCondition(value) {
  const numberValue = Number(value || 0)
  if (numberValue <= 0) return '无门槛'
  return `满 ${Number.isInteger(numberValue) ? numberValue : numberValue.toFixed(2)} 可用`
}

function normalizeCoupon(item = {}) {
  const status = Number(item.status || STATUS_UNUSED)
  const disabled = status !== STATUS_UNUSED
  const title = item.title || item.name || '优惠券'

  return {
    id: Number(item.id || 0),
    couponId: Number(item.coupon_id || 0),
    title,
    amount: formatMoney(item.discount_amount),
    condition: formatCondition(item.min_order_amount),
    status,
    statusText: item.status_text || (disabled ? '不可用' : '未使用'),
    receiveTypeText: item.receive_type_text || '领取',
    expireText: item.expire_text || '有效期以券面为准',
    actionText: disabled ? (status === STATUS_USED ? '已使用' : '已过期') : '去使用',
    disabled,
    raw: item
  }
}

defineOptions({ name: 'UserUseCouponPage' })

const activeStatus = ref(STATUS_UNUSED)
const couponTabs = ref(getCouponTabs())
const coupons = ref([])
const page = ref(1)
const limit = ref(10)
const total = ref(0)
const totalPage = ref(0)
const pageLoading = ref(false)
const refreshing = ref(false)
const loadingMore = ref(false)

const activeTabLabel = computed(() => {
  const tab = couponTabs.value.find(item => item.value === activeStatus.value)
  return tab ? tab.label + '优惠券' : '我的优惠券'
})

const summaryText = computed(() => {
  if (pageLoading.value) return '正在整理你的优惠券'
  return `当前共 ${total.value} 张`
})

const hasMore = computed(() => {
  return totalPage.value > 0 && page.value < totalPage.value
})

onLoad(() => {
  fetchCouponList(true)
})

onPullDownRefresh(() => {
  refreshing.value = true
  fetchCouponList(true).finally(() => {
    refreshing.value = false
    uni.stopPullDownRefresh()
  })
})

onReachBottom(() => {
  if (hasMore.value && !loadingMore.value && !pageLoading.value) {
    fetchCouponList(false)
  }
})

/**
 * 获取我的优惠券列表。
 *
 * @param {boolean} reset 是否重置分页。
 * @returns {Promise<void>}
 */
async function fetchCouponList(reset = false) {
  const requestPage = reset ? 1 : page.value + 1
  if (reset) {
    page.value = 1
    pageLoading.value = !refreshing.value
  } else {
    loadingMore.value = true
  }

  try {
    const res = await getUserCouponList({
      page: requestPage,
      limit: limit.value,
      status: activeStatus.value
    })

    if (res.code !== 200) {
      showToast(res.msg || '优惠券加载失败')
      return
    }

    const data = res.data || {}
    const list = Array.isArray(data.list) ? data.list.map(item => normalizeCoupon(item)) : []
    coupons.value = reset ? list : coupons.value.concat(list)
    total.value = Number(data.total || 0)
    totalPage.value = Number(data.totalPage || 0)
    page.value = Number(data.page || requestPage)
  } catch (error) {
    showToast('优惠券加载失败，请稍后重试')
  } finally {
    pageLoading.value = false
    loadingMore.value = false
  }
}

/**
 * 打开个人设置页。
 *
 * @returns {void}
 */
function handleOpenSettings() {
  uni.navigateTo({
    url: '/pages/users/userinfo/index',
    fail: () => {
      showToast('设置页开发中')
    }
  })
}

/**
 * 切换优惠券状态标签。
 *
 * @param {number} status 当前选中的状态。
 * @returns {void}
 */
function handleSelectStatus(status) {
  if (activeStatus.value === status) return
  activeStatus.value = status
  fetchCouponList(true)
}

/**
 * 点击优惠券卡片主体时展示详情占位。
 *
 * @param {Object} coupon 当前点击的优惠券。
 * @returns {void}
 */
function handleCouponClick(coupon = {}) {
  showToast(coupon.title || '优惠券')
}

/**
 * 使用当前优惠券。
 *
 * @param {Object} coupon 当前点击的优惠券。
 * @returns {void}
 */
function handleUseCoupon(coupon = {}) {
  if (coupon.disabled) {
    showToast(coupon.actionText || '该优惠券暂不可用')
    return
  }

  uni.switchTab({
    url: '/pages/index/index',
    fail: () => {
      showToast('首页暂时不可用')
    }
  })
}

/**
 * 展示轻提示。
 *
 * @param {string} title 提示文案。
 * @returns {void}
 */
function showToast(title) {
  uni.showToast({
    title,
    icon: 'none'
  })
}
</script>

<style lang="scss">
page {
  background: var(--surface-page);
}
</style>

<style lang="scss" scoped>
.coupon-page {
  min-height: 100%;
  background: var(--surface-page);
}


.coupon-page__content {
  padding: 24rpx 32rpx 0;
  box-sizing: border-box;
}

.coupon-tab-scroll {
  white-space: nowrap;

  &__inner {
    display: inline-flex;
    align-items: center;
    padding-right: 12rpx;
  }
}

.coupon-tab {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 132rpx;
  height: 64rpx;
  padding: 0 32rpx;
  margin-right: 16rpx;
  border-radius: 999rpx;
  background: var(--surface-card);
  box-shadow: 0 4rpx 12rpx var(--shadow-card);
  box-sizing: border-box;

  &--active {
    background: var(--brand-primary);
    box-shadow: 0 10rpx 20rpx var(--brand-shadow);
  }

  &--hover {
    opacity: 0.86;
  }

  &__text {
    font-size: 28rpx;
    line-height: 1;
    color: var(--text-secondary);
    white-space: nowrap;

    &--active {
      font-weight: 700;
      color: var(--text-inverse);
    }
  }
}

.coupon-refreshing {
  padding: 24rpx 0 0;
  text-align: center;

  &__text {
    font-size: 24rpx;
    line-height: 1.4;
    color: var(--text-secondary);
  }
}

.coupon-list {
  margin-top: 28rpx;

  &__footer {
    padding: 28rpx 0 40rpx;
    text-align: center;
  }

  &__footer-text {
    font-size: 24rpx;
    line-height: 1.3;
    color: var(--text-placeholder);
  }
}

.coupon-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 320rpx;
  border-radius: 24rpx;
  background: var(--surface-card);

  &__icon {
    margin-right: 12rpx;
    font-size: 30rpx;
    line-height: 1;
    color: var(--brand-primary);
  }

  &__text {
    font-size: 24rpx;
    line-height: 1.4;
    color: var(--text-secondary);
  }
}

.coupon-card + .coupon-card {
  margin-top: 24rpx;
}

.coupon-card {
  position: relative;
  display: flex;
  align-items: stretch;
  min-height: 188rpx;
  padding: 24rpx 22rpx 24rpx 0;
  border: 2rpx solid var(--border-light);
  border-radius: 24rpx;
  background: var(--surface-card);
  box-shadow: 0 4rpx 12rpx var(--shadow-card);
  overflow: hidden;
  box-sizing: border-box;

  &--hover {
    opacity: 0.98;
  }

  &--disabled {
    opacity: 0.72;
  }

  &__notch {
    position: absolute;
    top: 50%;
    width: 24rpx;
    height: 24rpx;
    border-radius: 50%;
    background: var(--surface-page);
    transform: translateY(-50%);

    &--left {
      left: -12rpx;
    }

    &--right {
      right: -12rpx;
    }
  }

  &__amount-column {
    flex-shrink: 0;
    width: 176rpx;
    padding: 12rpx 22rpx 12rpx 28rpx;
    border-right: 2rpx dashed var(--border-default);
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
  }

  &__amount-symbol {
    font-size: 26rpx;
    line-height: 1;
    font-weight: 700;
    color: var(--brand-primary);
  }

  &__amount-value {
    margin-top: 4rpx;
    font-size: 56rpx;
    line-height: 1;
    font-weight: 700;
    color: var(--brand-primary);
  }

  &__condition {
    margin-top: 12rpx;
    font-size: 22rpx;
    line-height: 1.2;
    color: var(--text-secondary);
    text-align: center;
  }

  &__main {
    flex: 1;
    min-width: 0;
    padding: 6rpx 0 6rpx 24rpx;
    box-sizing: border-box;
  }

  &__header {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 10rpx;
  }

  &__badge {
    margin-right: 10rpx;
    margin-bottom: 8rpx;
    padding: 6rpx 14rpx;
    border-radius: 8rpx;
    background: var(--brand-primary);
    font-size: 20rpx;
    line-height: 1;
    font-weight: 700;
    color: var(--text-inverse);
  }

  &__badge--muted {
    background: var(--surface-card-soft);
    color: var(--text-secondary);
  }

  &__source {
    margin-bottom: 8rpx;
    font-size: 22rpx;
    line-height: 1.2;
    color: var(--text-secondary);
  }

  &__title {
    display: block;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    font-size: 30rpx;
    line-height: 1.3;
    font-weight: 700;
    color: var(--text-title);
  }

  &__desc {
    display: block;
    margin-top: 10rpx;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    font-size: 22rpx;
    line-height: 1.4;
    color: var(--text-secondary);
  }

  &__action-wrap {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    padding-left: 18rpx;
  }

  &__action {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 132rpx;
    height: 64rpx;
    padding: 0 20rpx;
    border-radius: 999rpx;
    background: var(--brand-primary);
    box-shadow: 0 8rpx 20rpx var(--brand-shadow);
    box-sizing: border-box;
    color: var(--text-inverse);
  }

  &__action--disabled {
    background: var(--surface-card-soft);
    box-shadow: none;
    color: var(--text-secondary);
  }

  &__action--hover {
    opacity: 0.88;
  }

  &__action-text {
    font-size: 24rpx;
    line-height: 1;
    font-weight: 700;
  }

  &__action-icon {
    margin-left: 6rpx;
    font-size: 22rpx;
    line-height: 1;
  }
}

.empty-state {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: 40rpx;
  padding: 96rpx 40rpx;
  box-sizing: border-box;

  &__image {
    width: 420rpx;
    height: 280rpx;
  }
}

.safe-bottom {
  height: 32rpx;
}

/* #ifdef H5 */
.safe-bottom {
  height: calc(32rpx + var(--window-bottom, 0px));
}
/* #endif */

/* #ifdef MP-WEIXIN */
.safe-bottom {
  height: calc(32rpx + env(safe-area-inset-bottom));
}
/* #endif */
</style>
