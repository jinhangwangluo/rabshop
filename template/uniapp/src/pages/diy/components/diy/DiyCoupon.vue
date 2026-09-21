<template>
  <view class="diy-coupon" :style="componentStyle">
    <view v-if="!isLargeImageMode && (config.title || couponList.length > 0)" class="coupon-header">
      <view class="coupon-header__main">
        <text class="coupon-title">{{ config.title || '优惠券' }}</text>
      </view>
      <view
        v-if="couponList.length > 0"
        class="coupon-more"
        hover-class="coupon-more--hover"
        @click="handleMoreClick"
      >
        <text class="coupon-more__text">更多</text>
        <text class="coupon-more__icon">›</text>
      </view>
    </view>

    <view v-if="couponList.length === 0" class="coupon-empty" @click="handleRetryClick">
      <text class="coupon-empty__title">{{ emptyText }}</text>
    </view>

    <view v-else-if="isLargeImageMode" class="coupon-poster">
      <image class="coupon-poster-bg" :src="posterBgSrc" mode="widthFix"></image>
      <view class="coupon-poster-hero" hover-class="coupon-poster-hero--hover" @click="handleMoreClick">
        <view class="coupon-poster-hero-content">
          <view class="coupon-poster-title-wrap">
            <view class="coupon-title-decor coupon-title-decor--left">
              <view class="coupon-title-dot-pair">
                <view class="coupon-title-light-dot coupon-title-light-dot--small"></view>
                <view class="coupon-title-dot coupon-title-dot--small"></view>
              </view>
              <view class="coupon-title-dot-pair">
                <view class="coupon-title-light-dot coupon-title-light-dot--middle"></view>
                <view class="coupon-title-dot coupon-title-dot--middle"></view>
              </view>
              <view class="coupon-title-dot-pair">
                <view class="coupon-title-light-dot coupon-title-light-dot--large"></view>
                <view class="coupon-title-dot coupon-title-dot--large"></view>
              </view>
            </view>
            <text class="coupon-poster-title">{{ config.title || '优惠券' }}</text>
            <view class="coupon-title-decor coupon-title-decor--right">
              <view class="coupon-title-dot-pair">
                <view class="coupon-title-dot coupon-title-dot--large"></view>
                <view class="coupon-title-light-dot coupon-title-light-dot--large"></view>
              </view>
              <view class="coupon-title-dot-pair">
                <view class="coupon-title-dot coupon-title-dot--middle"></view>
                <view class="coupon-title-light-dot coupon-title-light-dot--middle"></view>
              </view>
              <view class="coupon-title-dot-pair">
                <view class="coupon-title-dot coupon-title-dot--small"></view>
                <view class="coupon-title-light-dot coupon-title-light-dot--small"></view>
              </view>
            </view>
          </view>
          <text class="coupon-poster-subtitle">领券购物更优惠</text>
        </view>
      </view>
      <view class="coupon-poster-list">
        <view
          v-for="item in posterCouponList"
          :key="item.renderKey"
          class="coupon-poster-card"
          :class="{ 'coupon-poster-card--disabled': item.disabled }"
          hover-class="coupon-poster-card--hover"
          @click="handleMoreClick"
        >
          <image
            class="coupon-poster-orange"
            :src="orangeBgSrc"
            mode="widthFix"
          ></image>
          <view class="coupon-poster-value">
            <view class="coupon-poster-amount">
              <text v-if="item.amountPrefix" class="coupon-poster-symbol">{{ item.amountPrefix }}</text>
              <text class="coupon-poster-number">{{ item.value }}</text>
              <text v-if="item.amountSuffix" class="coupon-poster-symbol coupon-poster-symbol--suffix">{{ item.amountSuffix }}</text>
            </view>
            <text class="coupon-poster-condition">{{ item.condition }}</text>
            <text class="coupon-poster-source">{{ item.sourceTag }}</text>
          </view>
          <view class="coupon-poster-info">
            <view class="coupon-poster-title-row">
              <text class="coupon-poster-tag">{{ item.tag }}</text>
              <text class="coupon-poster-name">{{ item.name }}</text>
            </view>
            <text class="coupon-poster-time">有效期至：{{ item.time || '长期有效' }}</text>
            <view
              v-if="showReceiveBtn"
              class="coupon-poster-btn"
              :class="{ 'coupon-poster-btn--disabled': item.disabled }"
              @click.stop="handleReceive(item)"
            >
              <text>{{ item.buttonText }}</text>
            </view>
          </view>
          <view class="coupon-poster-notch coupon-poster-notch--left"></view>
          <view class="coupon-poster-notch coupon-poster-notch--right"></view>
        </view>
      </view>
    </view>

    <view v-else-if="isGridMode" class="coupon-grid">
      <view
        v-for="item in couponList"
        :key="item.renderKey"
        class="coupon-poster-card"
        :class="{ 'coupon-poster-card--disabled': item.disabled }"
        hover-class="coupon-poster-card--hover"
        @click="handleClick(item)"
      >
        <image
          class="coupon-poster-orange"
          :src="orangeBgSrc"
          mode="widthFix"
        ></image>
        <view class="coupon-poster-value">
          <view class="coupon-poster-amount">
            <text v-if="item.amountPrefix" class="coupon-poster-symbol">{{ item.amountPrefix }}</text>
            <text class="coupon-poster-number">{{ item.value }}</text>
            <text v-if="item.amountSuffix" class="coupon-poster-symbol coupon-poster-symbol--suffix">{{ item.amountSuffix }}</text>
          </view>
          <text class="coupon-poster-condition">{{ item.condition }}</text>
          <text class="coupon-poster-source">{{ item.sourceTag }}</text>
        </view>
        <view class="coupon-poster-info">
          <view class="coupon-poster-title-row">
            <text class="coupon-poster-tag">{{ item.tag }}</text>
            <text class="coupon-poster-name">{{ item.name }}</text>
          </view>
          <text class="coupon-poster-time">有效期至：{{ item.time || '长期有效' }}</text>
          <view
            v-if="showReceiveBtn"
            class="coupon-poster-btn"
            :class="{ 'coupon-poster-btn--disabled': item.disabled }"
            @click.stop="handleReceive(item)"
          >
            <text>{{ item.buttonText }}</text>
          </view>
        </view>
        <view class="coupon-poster-notch coupon-poster-notch--left"></view>
        <view class="coupon-poster-notch coupon-poster-notch--right"></view>
      </view>
    </view>

    <scroll-view v-else class="coupon-scroll" scroll-x :show-scrollbar="false">
      <view class="coupon-track">
        <view
          v-for="item in couponList"
          :key="item.renderKey"
          class="coupon-poster-card coupon-poster-card--scroll"
          :class="{ 'coupon-poster-card--disabled': item.disabled }"
          hover-class="coupon-poster-card--hover"
          @click="handleClick(item)"
        >
          <image
            class="coupon-poster-orange"
            :src="orangeBgSrc"
            mode="widthFix"
          ></image>
          <view class="coupon-poster-value">
            <view class="coupon-poster-amount">
              <text v-if="item.amountPrefix" class="coupon-poster-symbol">{{ item.amountPrefix }}</text>
              <text class="coupon-poster-number">{{ item.value }}</text>
              <text v-if="item.amountSuffix" class="coupon-poster-symbol coupon-poster-symbol--suffix">{{ item.amountSuffix }}</text>
            </view>
            <text class="coupon-poster-condition">{{ item.condition }}</text>
            <text class="coupon-poster-source">{{ item.sourceTag }}</text>
          </view>
          <view class="coupon-poster-info">
            <view class="coupon-poster-title-row">
              <text class="coupon-poster-tag">{{ item.tag }}</text>
              <text class="coupon-poster-name">{{ item.name }}</text>
            </view>
            <text class="coupon-poster-time">有效期至：{{ item.time || '长期有效' }}</text>
            <view
              v-if="showReceiveBtn"
              class="coupon-poster-btn"
              :class="{ 'coupon-poster-btn--disabled': item.disabled }"
              @click.stop="handleReceive(item)"
            >
              <text>{{ item.buttonText }}</text>
            </view>
          </view>
          <view class="coupon-poster-notch coupon-poster-notch--left"></view>
          <view class="coupon-poster-notch coupon-poster-notch--right"></view>
        </view>
      </view>
    </scroll-view>
  </view>
</template>

<script setup>
import { computed, ref } from 'vue'
import { receiveCoupon } from '@/api/coupon'
import POSTER_BG_SRC from '../../static/coupon-large-bg.png'
import ORANGE_BG_SRC from '../../static/coupon-orange-bg.png'

defineOptions({
  name: 'DiyCoupon'
})

const props = defineProps({
  config: {
    type: Object,
    default: () => ({})
  },
  data: {
    type: Array,
    default: () => []
  },
  loading: {
    type: Boolean,
    default: false
  },
  error: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['receive', 'retry'])

const defaultPosterBgSrc = POSTER_BG_SRC
const orangeBgSrc = ORANGE_BG_SRC
const receivingCouponId = ref(0)
const receivedCouponIds = ref([])

const couponList = computed(() => {
  const source = Array.isArray(props.data) ? props.data : []
  if (props.config.data_source === 'manual') {
    return source.map((item, index) => mapItem(item, index))
  }

  const limit = Number(props.config.show_count || source.length || 4)
  return source.slice(0, limit).map((item, index) => mapItem(item, index))
})

const isGridMode = computed(() => {
  return (props.config.display_style || 'scroll') === 'grid'
})

const isLargeImageMode = computed(() => {
  return (props.config.display_style || 'scroll') === 'large_image'
})

const posterCouponList = computed(() => {
  return couponList.value
})

const showReceiveBtn = computed(() => {
  return props.config.show_receive_btn !== false
})

const posterBgSrc = computed(() => {
  return props.config.background_image || defaultPosterBgSrc
})

const componentStyle = computed(() => {
  const style = {}
  if (props.config.bg_color) {
    style.backgroundColor = props.config.bg_color
  }
  if (posterBgSrc.value) {
    style.backgroundImage = `url(${posterBgSrc.value})`
    style.backgroundSize = 'cover'
    style.backgroundPosition = 'center top'
    style.backgroundRepeat = 'no-repeat'
  }
  return style
})

const emptyText = computed(() => {
  if (props.loading) {
    return '优惠券加载中...'
  }

  if (props.error) {
    return `${props.error}，点击重试`
  }

  return '暂无优惠券数据'
})

function getCouponPageUrl() {
  return '/pages/activity/coupon/index'
}

function mapItem(item = {}, index = 0) {
  const couponId = Number(item.coupon_id || item.id || 0)
  const value = Number(item.reduce_price || item.discount_amount || item.price || 0)
  const minPrice = Number(item.full_price || item.min_order_amount || item.min_price || 0)
  const received = item.received === true || item.received === 1 || receivedCouponIds.value.indexOf(couponId) > -1
  const receiving = couponId > 0 && receivingCouponId.value === couponId
  const unavailable = item.disabled === true || item.disabled === 1 || Number(item.status) === 0
  const disabled = unavailable || received
  const buttonText = receiving
    ? '领取中'
    : (received ? (item.button_text || '已领取') : (unavailable ? (item.button_text || '不可领取') : (item.button_text || '领取')))
  return {
    ...item,
    renderKey: `${item.id || item.coupon_id || item.name || 'coupon'}_${index}`,
    couponId,
    name: item.name || '优惠券',
    value,
    amountPrefix: '¥',
    amountSuffix: '',
    condition: minPrice > 0 ? `满${minPrice}元可用` : '无门槛',
    tag: item.scope_type_text || item.tag || '通用',
    sourceTag: Number(item.merchant_id || item.merchantId || 0) > 0 ? '商户卷' : '平台卷',
    time: item.valid_text || (item.expire_day > 0 ? `领取后${item.expire_day}天有效` : (item.use_end_time || '')),
    buttonText,
    received,
    receiving,
    disabled
  }
}

function getConditionText(item) {
  return item.condition
}

function handleClick(item) {
  if (item.link_url) {
    uni.navigateTo({ url: item.link_url })
    return
  }
  uni.navigateTo({ url: getCouponPageUrl() })
}

function handleMoreClick() {
  uni.navigateTo({ url: getCouponPageUrl() })
}

async function handleReceive(item) {
  if (item.disabled || item.received) {
    uni.showToast({ title: item.received ? '该优惠券已领取' : '该优惠券暂不可领取', icon: 'none' })
    return
  }

  const couponId = Number(item.couponId || item.coupon_id || item.id || 0)
  if (couponId <= 0 || receivingCouponId.value > 0) {
    return
  }

  receivingCouponId.value = couponId
  try {
    const res = await receiveCoupon({ coupon_id: couponId })
    if (res.code !== 200) {
      return
    }

    if (receivedCouponIds.value.indexOf(couponId) === -1) {
      receivedCouponIds.value = receivedCouponIds.value.concat([couponId])
    }

    emit('receive', { ...item, received: true, disabled: true, receiveRecord: res.data || null })
    uni.showToast({ title: res.msg || '领取成功', icon: 'success' })
  } finally {
    receivingCouponId.value = 0
  }
}

function handleRetryClick() {
  if (props.error) {
    emit('retry')
  }
}
</script>

<style lang="scss" scoped>
.diy-coupon {
  width: auto;
  overflow: hidden;
  border-radius: 28rpx;
  box-sizing: border-box;
}

.coupon-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16rpx 20rpx 18rpx;
}

.coupon-header__main {
  flex: 1;
  min-width: 0;
}

.coupon-title {
  display: block;
  color: #1a1c1c;
  font-size: 32rpx;
  line-height: 44rpx;
  font-weight: 700;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.coupon-more {
  display: flex;
  align-items: center;
  flex-shrink: 0;
  height: 48rpx;
  border-radius: 24rpx;
  font-size: 22rpx;
  font-weight: 700;
  line-height: 48rpx;
}

.coupon-more--hover {
  opacity: 0.86;
}

.coupon-more__text,
.coupon-more__icon {
  display: block;
}

.coupon-more__icon {
  margin-left: 4rpx;
  font-size: 24rpx;
  line-height: 48rpx;
}

.coupon-empty {
  padding: 48rpx 0;
  text-align: center;
}

.coupon-empty__title {
  color: #999999;
  font-size: 24rpx;
}

.coupon-poster {
  position: relative;
  overflow: hidden;
  border-radius: 28rpx;
  background: #ffffff;
}

.coupon-poster-hero {
  position: relative;
  z-index: 1;
  height: 260rpx;
  overflow: hidden;
}

.coupon-poster-hero--hover,
.coupon-poster-card--hover {
  opacity: 0.9;
}

.coupon-poster-bg {
  display: block;
  position: absolute;
  left: 0;
  top: 0;
  z-index: 0;
  width: 100%;
  height: auto;
  min-height: 896rpx;
}

.coupon-poster-hero-content {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding-top: 54rpx;
}

.coupon-poster-title-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
}

.coupon-poster-title {
  display: block;
  max-width: 420rpx;
  color: #4a2111;
  font-size: 52rpx;
  font-weight: 800;
  line-height: 66rpx;
  text-align: center;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.coupon-title-decor {
  display: flex;
  align-items: center;
  width: 132rpx;
  height: 24rpx;
  flex-shrink: 0;
}

.coupon-title-decor--left {
  justify-content: flex-end;
  margin-right: 18rpx;
}

.coupon-title-decor--right {
  justify-content: flex-start;
  margin-left: 18rpx;
}

.coupon-title-dot,
.coupon-title-light-dot {
  display: block;
  flex-shrink: 0;
  border-radius: 50%;
}

.coupon-title-dot {
  background: #ff6a2a;
  box-shadow: 0 4rpx 10rpx var(--brand-shadow, rgba(234, 88, 12, 0.18));
}

.coupon-title-light-dot {
  background: rgba(255, 198, 156, 0.8);
}

.coupon-title-dot-pair {
  display: flex;
  align-items: center;
  flex-shrink: 0;
}

.coupon-title-decor--left .coupon-title-dot-pair {
  margin-left: 10rpx;
}

.coupon-title-decor--right .coupon-title-dot-pair {
  margin-right: 10rpx;
}

.coupon-title-decor--left .coupon-title-light-dot {
  margin-right: 2rpx;
}

.coupon-title-decor--right .coupon-title-light-dot {
  margin-left: 2rpx;
}

.coupon-title-dot--small {
  width: 8rpx;
  height: 8rpx;
  opacity: 0.5;
}

.coupon-title-dot--middle {
  width: 12rpx;
  height: 12rpx;
  opacity: 0.72;
}

.coupon-title-dot--large {
  width: 18rpx;
  height: 18rpx;
}

.coupon-title-light-dot--small {
  width: 8rpx;
  height: 8rpx;
}

.coupon-title-light-dot--middle {
  width: 12rpx;
  height: 12rpx;
}

.coupon-title-light-dot--large {
  width: 18rpx;
  height: 18rpx;
}

.coupon-poster-subtitle {
  display: block;
  margin-top: 12rpx;
  color: #6e3a22;
  font-size: 24rpx;
  font-weight: 700;
  line-height: 34rpx;
}

.coupon-poster-list {
  position: relative;
  z-index: 1;
  margin-top: -40rpx;
  padding: 0 22rpx 24rpx;
}

.coupon-poster-card {
  position: relative;
  z-index: 1;
  display: flex;
  min-height: 188rpx;
  margin-bottom: 20rpx;
  overflow: hidden;
  border-radius: 22rpx;
  border: 2rpx solid rgba(255, 168, 112, 0.26);
  background: linear-gradient(135deg, #fffdf8 0%, #fff3e8 48%, #fff9f2 100%);
  box-shadow:
    0 18rpx 42rpx rgba(255, 91, 30, 0.16),
    0 4rpx 14rpx rgba(124, 45, 18, 0.06);
  box-sizing: border-box;
}

.coupon-poster-card:last-child {
  margin-bottom: 0;
}

.coupon-poster-card--disabled {
  opacity: 0.62;
}

.coupon-poster-orange {
  position: absolute;
  left: -32rpx;
  top: -68rpx;
  width: 240rpx;
  opacity: 0.72;
  z-index: 0;
}

.coupon-poster-value,
.coupon-poster-info,
.coupon-poster-notch {
  position: relative;
  z-index: 1;
}

.coupon-poster-notch {
  position: absolute;
  width: 34rpx;
  height: 34rpx;
  top: 50%;
  margin-top: -17rpx;
  border-radius: 50%;
  background: var(--brand-primary-pale, #fff7ed);
  z-index: 2;
}

.coupon-poster-notch--left {
  left: -17rpx;
}

.coupon-poster-notch--right {
  right: -17rpx;
}

.coupon-poster-value {
  width: 224rpx;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  border-right: 2rpx dashed #ffd1ad;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.78) 0%, rgba(255, 242, 229, 0.52) 100%);
  box-sizing: border-box;
}

.coupon-poster-amount {
  display: flex;
  align-items: baseline;
  justify-content: center;
  color: #ff3f12;
  font-weight: 800;
  line-height: 1;
}

.coupon-poster-symbol {
  margin-right: 6rpx;
  font-size: 32rpx;
  line-height: 1;
}

.coupon-poster-symbol--suffix {
  margin-left: 6rpx;
  margin-right: 0;
}

.coupon-poster-number {
  font-size: 76rpx;
  line-height: 1;
}

.coupon-poster-condition {
  display: block;
  margin-top: 12rpx;
  color: #2d1c15;
  font-size: 22rpx;
  line-height: 32rpx;
}

.coupon-poster-source {
  display: block;
  height: 36rpx;
  margin-top: 10rpx;
  padding: 0 16rpx;
  border: 2rpx solid rgba(255, 112, 39, 0.36);
  border-radius: 8rpx;
  background: linear-gradient(90deg, #fff4ea 0%, #ffe7d2 100%);
  color: #ff5b1e;
  font-size: 18rpx;
  font-weight: 700;
  line-height: 36rpx;
}

.coupon-poster-info {
  flex: 1;
  min-width: 0;
  padding: 34rpx 24rpx 26rpx 32rpx;
  box-sizing: border-box;
}

.coupon-poster-title-row {
  display: flex;
  align-items: center;
  min-width: 0;
}

.coupon-poster-tag {
  display: block;
  flex-shrink: 0;
  height: 36rpx;
  padding: 0 12rpx;
  border-radius: 8rpx;
  background: linear-gradient(90deg, #ff6a2a 0%, #ff3f12 100%);
  color: #ffffff;
  font-size: 20rpx;
  font-weight: 800;
  line-height: 36rpx;
}

.coupon-poster-name {
  display: block;
  flex: 1;
  min-width: 0;
  margin-left: 12rpx;
  color: #2b160d;
  font-size: 30rpx;
  font-weight: 800;
  line-height: 40rpx;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.coupon-poster-time {
  display: block;
  margin-top: 20rpx;
  color: #2f2f2f;
  font-size: 22rpx;
  line-height: 32rpx;
}

.coupon-poster-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 80%;
  height: 54rpx;
  margin: 16rpx auto 0;
  border-radius: 27rpx;
  background: linear-gradient(90deg, #ff7a2f 0%, #ff3f12 100%);
  color: #ffffff;
  font-size: 26rpx;
  font-weight: 800;
  line-height: 54rpx;
  box-shadow: 0 10rpx 22rpx rgba(255, 63, 18, 0.22);
}

.coupon-poster-btn--disabled {
  background: #d1d5db;
  box-shadow: none;
}

.coupon-grid {
  padding: 0 22rpx 24rpx;
}

.coupon-large-card {
  position: relative;
  display: flex;
  min-height: 184rpx;
  margin-bottom: 24rpx;
  overflow: hidden;
  border-radius: 32rpx;
  background: #ffffff;
  box-shadow: 0 8rpx 48rpx rgba(0, 0, 0, 0.04), 0 2rpx 8rpx rgba(0, 0, 0, 0.02);
}

.coupon-large-card:last-child {
  margin-bottom: 0;
}

.coupon-large-card--disabled {
  opacity: 0.62;
}

.coupon-value {
  position: relative;
  width: 224rpx;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  border-right: 1rpx dashed #cfc4c5;
  background-color: #ffffff;
  background-image: radial-gradient(rgba(0, 0, 0, 0.04) 2rpx, transparent 2rpx);
  background-size: 16rpx 16rpx;
}

.coupon-amount,
.coupon-mini-amount {
  display: flex;
  align-items: baseline;
  justify-content: center;
  color: var(--brand-primary, #ea580c);
  font-weight: 700;
  line-height: 1;
}

.coupon-symbol {
  margin-right: 4rpx;
  font-size: 28rpx;
  line-height: 1;
}

.coupon-symbol--suffix {
  margin-left: 4rpx;
  margin-right: 0;
}

.coupon-number {
  font-size: 72rpx;
  line-height: 1;
}

.coupon-condition {
  margin-top: 12rpx;
  color: #4c4546;
  font-size: 20rpx;
  line-height: 28rpx;
}

.coupon-cut {
  position: absolute;
  right: -16rpx;
  width: 32rpx;
  height: 32rpx;
  border-radius: 50%;
  background: #f5f5f5;
}

.coupon-cut--top {
  top: -16rpx;
}

.coupon-cut--bottom {
  bottom: -16rpx;
}

.coupon-info {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 28rpx;
  box-sizing: border-box;
}

.coupon-main {
  flex: 1;
  min-width: 0;
  margin-right: 20rpx;
}

.coupon-title-row {
  display: flex;
  align-items: center;
  min-width: 0;
}

.coupon-tag {
  display: flex;
  align-items: center;
  flex-shrink: 0;
  height: 36rpx;
  padding: 0 12rpx;
  border-radius: 8rpx;
  background: #fff3ed;
  color: var(--brand-primary, #ea580c);
  font-size: 20rpx;
  font-weight: 700;
  line-height: 36rpx;
}

.coupon-name {
  flex: 1;
  min-width: 0;
  margin-left: 12rpx;
  color: #1a1c1c;
  font-size: 28rpx;
  line-height: 40rpx;
  font-weight: 700;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.coupon-time {
  display: block;
  margin-top: 12rpx;
  color: #848484;
  font-size: 20rpx;
  line-height: 28rpx;
}

.coupon-btn,
.coupon-mini-btn {
  flex-shrink: 0;
  height: 60rpx;
  padding: 0 32rpx;
  border-radius: 30rpx;
  background: var(--brand-primary, #ea580c);
  color: #ffffff;
  font-size: 24rpx;
  font-weight: 700;
  line-height: 60rpx;
}

.coupon-btn--disabled,
.coupon-mini-btn--disabled {
  background: #d1d5db;
  color: #ffffff;
}

.coupon-scroll {
  width: 100%;
}

.coupon-track {
  display: flex;
  padding: 0 22rpx 24rpx;
}

.coupon-poster-card--scroll {
  width: 640rpx;
  flex-shrink: 0;
  margin-right: 24rpx;
  margin-bottom: 0;
}

.coupon-poster-card--scroll:last-child {
  margin-right: 0;
}

.coupon-mini-card {
  position: relative;
  flex-shrink: 0;
  width: 272rpx;
  min-height: 300rpx;
  margin-right: 24rpx;
  padding: 36rpx 28rpx 28rpx;
  overflow: hidden;
  border-radius: 32rpx;
  background-color: #ffffff;
  background-image: radial-gradient(rgba(0, 0, 0, 0.04) 2rpx, transparent 2rpx);
  background-size: 16rpx 16rpx;
  box-shadow: 0 8rpx 48rpx rgba(0, 0, 0, 0.04), 0 2rpx 8rpx rgba(0, 0, 0, 0.02);
  box-sizing: border-box;
  text-align: center;
}

.coupon-mini-card:last-child {
  margin-right: 0;
}

.coupon-mini-card--disabled {
  opacity: 0.62;
}

.coupon-mini-bar {
  position: absolute;
  left: 0;
  right: 0;
  top: 0;
  height: 12rpx;
  background: var(--brand-primary, #ea580c);
}

.coupon-mini-amount {
  margin-top: 16rpx;
}

.coupon-mini-amount .coupon-number {
  font-size: 68rpx;
}

.coupon-mini-condition {
  margin-top: 16rpx;
  color: #4c4546;
  font-size: 20rpx;
  line-height: 28rpx;
}

.coupon-mini-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 28rpx auto 0;
  padding: 0 30rpx;
}

/* #ifdef MP-WEIXIN */
.coupon-poster-card {
  box-sizing: border-box;
}
/* #endif */
</style>
