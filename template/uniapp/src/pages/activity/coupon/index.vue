<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="coupon-page">
    <view class="coupon-hero">
      <image class="coupon-hero__bg" src="../static/coupon-hero-bg.jpg" mode="aspectFill"></image>
      <view class="coupon-hero__fade"></view>
      <app-safe-top
        title="领取优惠券"
        background="transparent"
        title-color="#ffffff"
        icon-color="#ffffff"
      >
      </app-safe-top>
      <view class="coupon-hero__copy">
        <text class="coupon-hero__title">领取优惠券</text>
        <text class="coupon-hero__desc">先领券 再购物 更优惠</text>
      </view>
    </view>

    <view class="coupon-panel">
      <view class="coupon-tabs">
        <view v-for="tab in tabs" :key="tab.value" class="coupon-tabs__item" @click="handleSelectTab(tab.value)">
          <text :class="['coupon-tabs__text', { 'coupon-tabs__text--active': activeTab === tab.value }]">{{ tab.label }}</text>
          <view v-if="activeTab === tab.value" class="coupon-tabs__line"></view>
        </view>
      </view>

      <view v-if="refreshing" class="coupon-refreshing">
        <six-petal-spiral active text="刷新中" :color="brandPrimaryColor" />
      </view>

      <view class="coupon-list">
        <six-petal-spiral v-if="pageLoading" active text="加载优惠券中" :color="brandPrimaryColor" />

        <view v-else-if="displayCoupons.length === 0" class="coupon-empty">
          <image class="coupon-empty__bg" src="../static/coupon-empty-bg.png" mode="aspectFit"></image>
          <text class="coupon-empty__title">暂无可领取优惠券</text>
        </view>

        <template v-else>
          <view v-for="coupon in displayCoupons" :key="coupon.id" class="coupon-card" hover-class="coupon-card--hover">
            <image class="coupon-card__bg" src="../static/coupon-card-bg.png" mode="scaleToFill"></image>
            <view class="coupon-card__value">
              <view class="coupon-card__amount-row">
                <text class="coupon-card__currency">¥</text>
                <text class="coupon-card__amount">{{ coupon.amount }}</text>
              </view>
              <text class="coupon-card__condition">{{ coupon.condition }}</text>
              <text class="coupon-card__scope">{{ coupon.scopeText }}</text>
            </view>
            <view class="coupon-card__main">
              <text :class="['coupon-card__tag', { 'coupon-card__tag--muted': coupon.received }]">{{ coupon.tag }}</text>
              <text class="coupon-card__title">{{ coupon.title }}</text>
              <view class="coupon-card__time-row">
                <view :class="['coupon-card__time-icon', coupon.iconClass]"></view>
                <text class="coupon-card__time">{{ coupon.expireTime }}</text>
              </view>
              <text class="coupon-card__desc">{{ coupon.desc }}</text>
              <view
                :class="['coupon-card__button', coupon.buttonClass, { 'coupon-card__button--disabled': coupon.received || receivingCouponId === coupon.id }]"
                hover-class="coupon-card__button--hover"
                @click.stop="handleReceiveCoupon(coupon)"
              >
                <text>{{ getReceiveButtonText(coupon) }}</text>
              </view>
            </view>
          </view>
        </template>

        <view v-if="loadingMore" class="coupon-list__loading">
          <six-petal-spiral active text="加载更多" :color="brandPrimaryColor" />
        </view>
      </view>

    </view>
    <view class="coupon-page__bottom"></view>
  </view>
</template>

<script setup>
import { computed, ref } from 'vue'
import { onLoad, onPullDownRefresh, onReachBottom } from '@dcloudio/uni-app'
import SixPetalSpiral from '@/components/six-petal-spiral/index.vue'
import { getCouponReceiveList, receiveCoupon } from '@/api/coupon'
import { themeState } from '@/utils/theme'

const SCOPE_TYPE_SHOP = 0
const SCOPE_TYPE_PLATFORM = 10
const SCOPE_TYPE_CATEGORY = 11

function getTabs() {
  return [
    { label: '通用券', value: 'general', scopeType: SCOPE_TYPE_PLATFORM },
    // 商品券暂不展示，后端领取中心也已过滤 scope_type = 1
    // { label: '商品券', value: 'product', scopeType: 1 },
    { label: '品类券', value: 'category', scopeType: SCOPE_TYPE_CATEGORY },
    { label: '店铺券', value: 'store', scopeType: SCOPE_TYPE_SHOP }
  ]
}

function formatMoney(value) {
  const numberValue = Number(value || 0)
  if (!numberValue) return '0'
  return Number.isInteger(numberValue) ? `${numberValue}` : `${numberValue.toFixed(2)}`
}

function formatCondition(value) {
  const numberValue = Number(value || 0)
  if (numberValue <= 0) return '无门槛'
  return `满${Number.isInteger(numberValue) ? numberValue : numberValue.toFixed(2)}可用`
}

function hasValue(value) {
  return value !== undefined && value !== null && value !== ''
}

function resolveCategory(scopeType) {
  if (scopeType === SCOPE_TYPE_SHOP) return 'store'
  if (scopeType === SCOPE_TYPE_CATEGORY) return 'category'
  return 'general'
}

function resolveTone(category) {
  if (category === 'category') {
    return {
      buttonClass: 'coupon-card__button--orange',
      iconClass: 'coupon-card__time-icon--orange'
    }
  }
  if (category === 'store') {
    return {
      buttonClass: 'coupon-card__button--pink',
      iconClass: 'coupon-card__time-icon--pink'
    }
  }
  return {
    buttonClass: 'coupon-card__button--red',
    iconClass: 'coupon-card__time-icon--red'
  }
}

function resolveDesc(category) {
  if (category === 'category') return '指定品类可用'
  if (category === 'store') return '指定店铺可用'
  return '全场商品可用'
}

function normalizeCoupon(item = {}) {
  const scopeType = hasValue(item.scope_type) ? Number(item.scope_type) : SCOPE_TYPE_PLATFORM
  const category = resolveCategory(scopeType)
  const tone = resolveTone(category)
  const title = hasValue(item.title) ? String(item.title) : '优惠券'
  const scopeText = hasValue(item.scope_type_text) ? String(item.scope_type_text) : '优惠券'
  return {
    id: Number(hasValue(item.coupon_id) ? item.coupon_id : 0),
    category,
    amount: formatMoney(item.discount_amount),
    condition: formatCondition(item.min_order_amount),
    tag: item.received ? '已领取' : (hasValue(item.tag) ? String(item.tag) : '限时领取'),
    title,
    scopeText,
    desc: resolveDesc(category),
    expireTime: hasValue(item.expire_text) ? String(item.expire_text) : '有效期以券面为准',
    received: !!item.received,
    ...tone,
    raw: item
  }
}

defineOptions({
  name: 'ActivityCouponPage'
})

const activeTab = ref('general')
const tabs = ref(getTabs())
const coupons = ref([])
const page = ref(1)
const limit = ref(10)
const totalPage = ref(0)
const pageLoading = ref(false)
const refreshing = ref(false)
const loadingMore = ref(false)
const receivingCouponId = ref(0)
const brandPrimaryColor = computed(() => themeState.primary_color)

const displayCoupons = computed(() => {
  return coupons.value.filter(item => item.category === activeTab.value)
})

const activeScopeType = computed(() => {
  const tab = tabs.value.find(item => item.value === activeTab.value)
  return tab ? tab.scopeType : SCOPE_TYPE_PLATFORM
})

const hasMore = computed(() => {
  return totalPage.value > 0 && page.value < totalPage.value
})

async function fetchCouponList(reset = false) {
  const requestPage = reset ? 1 : page.value + 1
  if (reset) {
    page.value = 1
    pageLoading.value = !refreshing.value
  } else {
    loadingMore.value = true
  }

  try {
    const res = await getCouponReceiveList({
      page: requestPage,
      limit: limit.value,
      scope_type: activeScopeType.value
    })
    if (res.code !== 200) return

    const data = res.data || {}
    const list = Array.isArray(data.list) ? data.list.map(item => normalizeCoupon(item)) : []
    totalPage.value = Number(data.totalPage || 0)
    coupons.value = reset ? list : coupons.value.concat(list)
    page.value = Number(data.page || requestPage)
  } finally {
    pageLoading.value = false
    loadingMore.value = false
  }
}

function handleSelectTab(tabValue) {
  if (!tabValue || activeTab.value === tabValue) return
  activeTab.value = tabValue
  fetchCouponList(true)
}

function handleOpenRule() {
  uni.showModal({
    title: '领券规则',
    content: '优惠券数量有限，领取后可在下单结算时使用，具体门槛与有效期以券面展示为准。',
    showCancel: false,
    confirmText: '知道了',
    confirmColor: '#ff4038'
  })
}

async function handleReceiveCoupon(coupon = {}) {
  if (coupon.received) {
    showToast('该优惠券已领取')
    return
  }
  if (!coupon.id || receivingCouponId.value) return

  receivingCouponId.value = coupon.id
  try {
    const res = await receiveCoupon({ coupon_id: coupon.id })
    if (res.code !== 200) return
    coupons.value = coupons.value.map(item => item.id === coupon.id ? Object.assign({}, item, {
      received: true,
      tag: '已领取'
    }) : item)
    showToast(res.msg || '领取成功')
  } finally {
    receivingCouponId.value = 0
  }
}

function getReceiveButtonText(coupon = {}) {
  if (coupon.received) return '已领取'
  if (receivingCouponId.value === coupon.id) return '领取中'
  return '立即领取'
}

function showToast(title) {
  uni.showToast({ title, icon: 'none' })
}

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
</script>

<style lang="scss">
page {
  background: #f5f5f5;
}
</style>

<style lang="scss" scoped>
.coupon-page {
  min-height: 100%;
  background: linear-gradient(180deg, #fff0d8 0, #f7f7f7 430rpx, #f5f5f5 620rpx);
}

.coupon-hero {
  position: relative;
  height: 392rpx;
  overflow: hidden;
  background: linear-gradient(180deg, #ff4038 0%, #ffe3bc 74%, #f7f7f7 100%);
}

.coupon-hero__bg {
  position: absolute;
  left: 0;
  top: 0;
  z-index: 0;
  width: 100%;
  height: 100%;
}

.coupon-hero__fade {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 1;
  height: 128rpx;
  background: linear-gradient(180deg, rgba(247, 247, 247, 0) 0%, rgba(247, 247, 247, 0.62) 70%, #f7f7f7 100%);
}

.coupon-hero__copy {
  position: absolute;
  left: 48rpx;
  bottom: 64rpx;
  z-index: 2;
  display: flex;
  flex-direction: column;
}

.coupon-hero__title {
  font-size: 58rpx;
  line-height: 1.1;
  font-weight: 900;
  color: #ffffff;
}

.coupon-hero__desc {
  margin-top: 18rpx;
  font-size: 30rpx;
  line-height: 1.4;
  color: rgba(255, 255, 255, 0.92);
}

.coupon-rule {
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 88rpx;
  height: 64rpx;
  padding: 0 16rpx;
  box-sizing: border-box;
  font-size: 30rpx;
  line-height: 1;
  color: #ffffff;
}

.coupon-rule--hover {
  opacity: 0.78;
}

.coupon-panel {
  position: relative;
  z-index: 3;
  margin: -54rpx 26rpx 0;
  border-radius: 28rpx 28rpx 0 0;
  background: #f7f7f7;
  box-sizing: border-box;
  overflow: hidden;
}

.coupon-tabs {
  display: flex;
  align-items: center;
  height: 112rpx;
  background: #ffffff;
  border-bottom: 1rpx solid rgba(245, 245, 245, 0.92);
}

.coupon-tabs__item {
  position: relative;
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
}

.coupon-tabs__text {
  font-size: 30rpx;
  line-height: 1;
  font-weight: 700;
  color: #4c4c4c;
}

.coupon-tabs__text--active {
  color: #ff4038;
}

.coupon-tabs__line {
  position: absolute;
  left: 50%;
  bottom: 20rpx;
  width: 36rpx;
  height: 4rpx;
  margin-left: -18rpx;
  border-radius: 999rpx;
  background: #ff4038;
}

.coupon-refreshing {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24rpx 0 0;
}

.coupon-list {
  padding: 20rpx 0 0;
  box-sizing: border-box;
}

.coupon-list__loading {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 32rpx 0;
}

.coupon-empty {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-start;
  height: 452rpx;
  padding: 382rpx 32rpx 0;
  box-sizing: border-box;
  overflow: hidden;
}

.coupon-empty__bg {
  position: absolute;
  left: 50%;
  top: 0;
  z-index: 0;
  width: 488rpx;
  height: 382rpx;
  margin-left: -244rpx;
}

.coupon-empty__title {
  position: relative;
  z-index: 1;
  font-size: 28rpx;
  line-height: 1.5;
  font-weight: 600;
  color: #74777f;
}

.coupon-card+.coupon-card {
  margin-top: 24rpx;
}

.coupon-card {
  position: relative;
  height: 202rpx;
  border-radius: 24rpx;
  box-shadow: 0 10rpx 28rpx rgba(25, 25, 25, 0.08);
  overflow: hidden;
}

.coupon-card--hover {
  opacity: 0.98;
}

.coupon-card__bg {
  position: absolute;
  left: 0;
  top: 0;
  z-index: 0;
  width: 100%;
  height: 100%;
}

.coupon-card__value {
  position: absolute;
  left: 0;
  top: 0;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: 30%;
  height: 100%;
  padding-top: 8rpx;
  box-sizing: border-box;
}

.coupon-card__amount-row {
  display: flex;
  align-items: baseline;
  justify-content: center;
}

.coupon-card__currency {
  margin-right: 4rpx;
  font-size: 30rpx;
  line-height: 1;
  font-weight: 800;
  color: #ffffff;
}

.coupon-card__amount {
  font-size: 72rpx;
  line-height: 1;
  font-weight: 900;
  color: #ffffff;
}

.coupon-card__condition {
  width: 178rpx;
  margin-top: 4rpx;
  overflow: hidden;
  white-space: nowrap;
  text-align: center;
  text-overflow: ellipsis;
  font-size: 24rpx;
  line-height: 1.4;
  font-weight: 700;
  color: #ffffff;
}

.coupon-card__scope {
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 120rpx;
  max-width: 170rpx;
  height: 34rpx;
  margin-top: 8rpx;
  padding: 0 16rpx;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  border-radius: 999rpx;
  background: rgba(255, 255, 255, 0.72);
  box-sizing: border-box;
  font-size: 22rpx;
  line-height: 1;
  font-weight: 700;
  color: #ff4a34;
}

.coupon-card__main {
  position: absolute;
  left: 30%;
  right: 0;
  top: 0;
  z-index: 1;
  display: flex;
  flex-direction: column;
  height: 100%;
  padding: 30rpx 156rpx 24rpx 32rpx;
  box-sizing: border-box;
}

.coupon-card__tag {
  display: flex;
  align-items: center;
  justify-content: center;
  align-self: flex-start;
  min-width: 88rpx;
  height: 34rpx;
  padding: 0 12rpx;
  border-radius: 8rpx;
  background: #ff4038;
  box-sizing: border-box;
  font-size: 22rpx;
  line-height: 1;
  font-weight: 700;
  color: #ffffff;
}

.coupon-card__tag--muted {
  background: #a8a8ad;
}

.coupon-card__title {
  margin-top: 14rpx;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  font-size: 32rpx;
  line-height: 1.5;
  font-weight: 700;
  color: #111111;
}

.coupon-card__time-row {
  display: flex;
  align-items: center;
  margin-top: 2rpx;
  min-width: 0;
}

.coupon-card__time-icon {
  flex-shrink: 0;
  width: 14rpx;
  height: 14rpx;
  border: 3rpx solid #ff4038;
  border-radius: 50%;
  box-sizing: border-box;
}

.coupon-card__time-icon--orange {
  border-color: #ff8a2a;
}

.coupon-card__time-icon--red {
  border-color: #ff4038;
}

.coupon-card__time-icon--pink {
  border-color: #ff3d75;
}

.coupon-card__time {
  margin-left: 8rpx;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  font-size: 22rpx;
  line-height: 1.5;
  color: #66666c;
}

.coupon-card__desc {
  margin-top: 4rpx;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  font-size: 24rpx;
  line-height: 1.4;
  color: #66666c;
}

.coupon-card__button {
  position: absolute;
  right: 22rpx;
  top: 74rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 134rpx;
  height: 58rpx;
  padding: 0 20rpx;
  border-radius: 999rpx;
  background: #ff4038;
  box-shadow: 0 8rpx 18rpx rgba(255, 64, 56, 0.2);
  box-sizing: border-box;
  font-size: 26rpx;
  line-height: 1;
  font-weight: 700;
  color: #ffffff;
}

.coupon-card__button--orange {
  background: #ff8a2a;
  box-shadow: 0 8rpx 18rpx rgba(255, 138, 42, 0.18);
}

.coupon-card__button--red {
  background: #ff4038;
  box-shadow: 0 8rpx 18rpx rgba(255, 64, 56, 0.2);
}

.coupon-card__button--pink {
  background: #ff3d75;
  box-shadow: 0 8rpx 18rpx rgba(255, 61, 117, 0.18);
}

.coupon-card__button--disabled {
  background: #d8d8dd;
  box-shadow: none;
  color: #ffffff;
}

.coupon-card__button--hover {
  opacity: 0.88;
}

.coupon-page__bottom {
  height: 40rpx;
}

/* #ifdef H5 */
.coupon-page__bottom {
  height: calc(40rpx + var(--window-bottom, 0px));
}

/* #endif */
/* #ifdef MP-WEIXIN */
.coupon-page__bottom {
  height: calc(40rpx + env(safe-area-inset-bottom));
}

/* #endif */
</style>
