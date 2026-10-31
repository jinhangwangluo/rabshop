<template>
  <view v-if="visible" class="coupon-popup-sheet" @click="handleClose" @tap="handleClose" @touchmove.stop.prevent="noop">
    <view class="coupon-popup-sheet__panel" @click.stop @tap.stop>
      <view class="coupon-popup">
        <view class="coupon-popup__header">
          <text class="coupon-popup__title">{{ title }}</text>
          <view class="coupon-popup__close" @click="handleClose" @tap="handleClose">
            <uni-icons type="closeempty" size="23" color="#777777"></uni-icons>
          </view>
        </view>

        <scroll-view scroll-y class="coupon-popup__scroll">
          <view v-if="showNone" class="coupon-popup__list">
            <view class="coupon-none" :class="{ 'coupon-none--active': noneActive }" @click="handleNoneSelect" @tap="handleNoneSelect">
              <view class="coupon-none__body">
                <text class="coupon-none__title">{{ noneTitle }}</text>
                <text class="coupon-none__desc">{{ noneDesc }}</text>
              </view>
              <view class="coupon-radio" :class="{ 'coupon-radio--active': noneActive }">
                <uni-icons v-if="noneActive" type="checkmarkempty" size="18" color="#ffffff"></uni-icons>
              </view>
            </view>
          </view>

          <view v-if="!displayItems.length" class="coupon-popup__empty">
            <text class="coupon-popup__empty-title">{{ emptyTitle }}</text>
            <text class="coupon-popup__empty-desc">{{ emptyDesc }}</text>
          </view>
          <view v-else class="coupon-popup__list" :class="{ 'coupon-popup__list--with-none': showNone }">
            <view
              v-for="item in displayItems"
              :key="item.id"
              class="coupon-card"
              :class="{
                'coupon-card--selected': selectable && item.selected,
                'coupon-card--muted': item.disabled
              }"
              hover-class="coupon-card--hover"
              @click="handleCardSelect(item)"
              @tap="handleCardSelect(item)"
            >
              <image class="coupon-card__bg" :src="couponCardBg" mode="scaleToFill"></image>
              <view class="coupon-card__value">
                <view class="coupon-card__amount-row">
                  <text class="coupon-card__currency">¥</text>
                  <text class="coupon-card__amount">{{ item.amount }}</text>
                </view>
                <text class="coupon-card__condition">{{ item.condition }}</text>
                <text class="coupon-card__scope">{{ item.scopeText }}</text>
              </view>
              <view class="coupon-card__main">
                <text class="coupon-card__tag" :class="{ 'coupon-card__tag--muted': item.tagMuted }">
                  {{ item.tag }}
                </text>
                <text class="coupon-card__title">{{ item.title }}</text>
                <view class="coupon-card__time-row">
                  <view class="coupon-card__time-icon" :class="item.iconClass"></view>
                  <text class="coupon-card__time">{{ item.expireTime }}</text>
                </view>
                <text class="coupon-card__desc">{{ item.desc }}</text>
                <view
                  v-if="showActions"
                  class="coupon-card__button"
                  :class="[item.buttonClass, { 'coupon-card__button--disabled': item.actionDisabled }]"
                  hover-class="coupon-card__button--hover"
                  @click.stop="handleAction(item)"
                  @tap.stop="handleAction(item)"
                >
                  <text>{{ item.actionText }}</text>
                </view>
                <view v-if="selectable" class="coupon-radio coupon-card__radio" :class="{ 'coupon-radio--active': item.selected }">
                  <uni-icons v-if="item.selected" type="checkmarkempty" size="18" color="#ffffff"></uni-icons>
                </view>
              </view>
            </view>
          </view>
        </scroll-view>
      </view>
    </view>
  </view>
</template>

<script setup>
import { computed } from 'vue'
import couponCardBg from '@/pages/activity/static/coupon-card-bg.png'

defineOptions({
  name: 'CouponPopup'
})

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  title: {
    type: String,
    default: '可用优惠券'
  },
  items: {
    type: Array,
    default: () => []
  },
  emptyTitle: {
    type: String,
    default: '暂无可用优惠券'
  },
  emptyDesc: {
    type: String,
    default: '当前暂时没有可领取或可使用的优惠券'
  },
  showActions: {
    type: Boolean,
    default: true
  },
  selectable: {
    type: Boolean,
    default: false
  },
  selectedId: {
    type: Number,
    default: 0
  },
  showNone: {
    type: Boolean,
    default: false
  },
  noneActive: {
    type: Boolean,
    default: false
  },
  noneTitle: {
    type: String,
    default: '不使用优惠券'
  },
  noneDesc: {
    type: String,
    default: '本次不抵扣优惠券'
  }
})

const emit = defineEmits(['close', 'action', 'select'])

const displayItems = computed(() => {
  return props.items.map((item) => normalizeCouponItem(item)).filter((item) => item.id > 0)
})

/**
 * 空函数，用于阻止弹窗遮罩滚动穿透。
 *
 * @returns {void}
 */
function noop() {}

/**
 * 规范化优惠券卡片展示字段。
 *
 * @param {Object} item 优惠券数据。
 * @returns {Object} 组件渲染数据。
 */
function normalizeCouponItem(item = {}) {
  const id = Number(item.id || item.user_coupon_id || item.coupon_id || 0)
  const amount = String(item.amount || item.discountText || item.discount_amount || '0')
  const condition = String(item.condition || item.thresholdText || buildCondition(item))
  const scopeText = String(item.scopeText || item.scope_type_text || item.tag || '')
  const tag = String(item.tag || scopeText || '优惠券')
  const selected = Boolean(item.selected) || Number(props.selectedId || 0) === id

  return {
    ...item,
    id,
    amount,
    condition,
    scopeText,
    tag,
    title: String(item.title || item.name || '优惠券'),
    expireTime: String(item.expireTime || item.expireText || item.valid_time_text || ''),
    desc: String(item.desc || scopeText || ''),
    iconClass: String(item.iconClass || 'coupon-card__time-icon--red'),
    buttonClass: String(item.buttonClass || 'coupon-card__button--red'),
    actionText: String(item.actionText || '领取'),
    actionDisabled: Boolean(item.actionDisabled),
    tagMuted: Boolean(item.tagMuted || item.received || item.disabled),
    disabled: Boolean(item.disabled),
    selected
  }
}

/**
 * 构建优惠券门槛文案。
 *
 * @param {Object} item 优惠券数据。
 * @returns {string} 门槛文案。
 */
function buildCondition(item = {}) {
  const threshold = Number(item.min_order_amount || 0)
  return threshold > 0 ? `满${formatAmount(threshold)}可用` : '无门槛'
}

/**
 * 格式化金额。
 *
 * @param {number|string} value 金额。
 * @returns {string} 金额文案。
 */
function formatAmount(value) {
  const number = Number(value || 0)
  if (!Number.isFinite(number)) {
    return '0'
  }

  return Number.isInteger(number) ? String(number) : number.toFixed(2).replace(/\.?0+$/, '')
}

/**
 * 关闭弹窗。
 *
 * @returns {void}
 */
function handleClose() {
  emit('close')
}

/**
 * 选择不使用优惠券。
 *
 * @returns {void}
 */
function handleNoneSelect() {
  emit('select', 0, null)
}

/**
 * 选择优惠券卡片。
 *
 * @param {Object} item 优惠券展示项。
 * @returns {void}
 */
function handleCardSelect(item = {}) {
  if (!props.selectable || item.disabled) {
    return
  }

  emit('select', Number(item.id || 0), item)
}

/**
 * 点击优惠券动作按钮。
 *
 * @param {Object} item 优惠券展示项。
 * @returns {void}
 */
function handleAction(item = {}) {
  if (item.actionDisabled) {
    return
  }

  emit('action', item)
}
</script>

<style scoped lang="scss">
.coupon-popup-sheet {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  z-index: 58;
  display: flex;
  align-items: flex-end;
  background: rgba(0, 0, 0, 0.58);
  animation: coupon-popup-mask-in 180ms ease-out both;
}

.coupon-popup-sheet__panel {
  width: 100%;
  animation: coupon-popup-sheet-in 240ms cubic-bezier(0.22, 0.61, 0.36, 1) both;
}

@keyframes coupon-popup-mask-in {
  0% {
    opacity: 0;
  }

  100% {
    opacity: 1;
  }
}

@keyframes coupon-popup-sheet-in {
  0% {
    transform: translateY(100%);
  }

  100% {
    transform: translateY(0);
  }
}

.coupon-popup {
  display: flex;
  flex-direction: column;
  width: 100%;
  max-height: 920rpx;
  border-top-left-radius: 32rpx;
  border-top-right-radius: 32rpx;
  overflow: hidden;
  background: #ffffff;
}

.coupon-popup__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 24rpx 28rpx 22rpx;
  background: #ffffff;
}

.coupon-popup__title {
  color: #1a1c1c;
  font-size: 34rpx;
  line-height: 48rpx;
  font-weight: 700;
}

.coupon-popup__close {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 56rpx;
  height: 56rpx;
  flex-shrink: 0;
}

.coupon-popup__scroll {
  height: 620rpx;
  background: #f9f9f9;
}

.coupon-popup__list {
  padding: 24rpx 24rpx 32rpx;
}

.coupon-popup__list--with-none {
  padding-top: 0;
}

.coupon-popup__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 420rpx;
  padding: 64rpx 32rpx;
  text-align: center;
}

.coupon-popup__empty-title {
  color: #1a1c1c;
  font-size: 30rpx;
  line-height: 42rpx;
  font-weight: 700;
}

.coupon-popup__empty-desc {
  margin-top: 12rpx;
  color: #999999;
  font-size: 24rpx;
  line-height: 36rpx;
}

.coupon-none {
  display: flex;
  align-items: center;
  min-height: 104rpx;
  padding: 22rpx 24rpx;
  border-radius: 18rpx;
  background: #ffffff;
  box-sizing: border-box;
}

.coupon-none--active {
  box-shadow: 0 8rpx 20rpx rgba(0, 0, 0, 0.08);
}

.coupon-none__body {
  flex: 1;
  min-width: 0;
}

.coupon-none__title {
  display: block;
  color: #1a1c1c;
  font-size: 30rpx;
  line-height: 42rpx;
  font-weight: 700;
}

.coupon-none__desc {
  display: block;
  margin-top: 8rpx;
  color: #7b7677;
  font-size: 24rpx;
  line-height: 34rpx;
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

.coupon-card--selected {
  box-shadow: 0 10rpx 28rpx rgba(255, 64, 56, 0.18);
}

.coupon-card--muted {
  opacity: 0.68;
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
  color: #ffffff;
  font-size: 30rpx;
  line-height: 1;
  font-weight: 800;
}

.coupon-card__amount {
  color: #ffffff;
  font-size: 72rpx;
  line-height: 1;
  font-weight: 900;
}

.coupon-card__condition {
  width: 178rpx;
  margin-top: 4rpx;
  overflow: hidden;
  white-space: nowrap;
  text-align: center;
  text-overflow: ellipsis;
  color: #ffffff;
  font-size: 24rpx;
  line-height: 1.4;
  font-weight: 700;
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
  color: #ff4a34;
  font-size: 22rpx;
  line-height: 1;
  font-weight: 700;
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
  color: #ffffff;
  font-size: 22rpx;
  line-height: 1;
  font-weight: 700;
}

.coupon-card__tag--muted {
  background: #a8a8ad;
}

.coupon-card__title {
  margin-top: 14rpx;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  color: #111111;
  font-size: 32rpx;
  line-height: 1.5;
  font-weight: 700;
}

.coupon-card__time-row {
  display: flex;
  align-items: center;
  min-width: 0;
  margin-top: 2rpx;
}

.coupon-card__time-icon {
  width: 14rpx;
  height: 14rpx;
  flex-shrink: 0;
  border: 3rpx solid #ff4038;
  border-radius: 50%;
  box-sizing: border-box;
}

.coupon-card__time-icon--red {
  border-color: #ff4038;
}

.coupon-card__time {
  margin-left: 8rpx;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  color: #66666c;
  font-size: 22rpx;
  line-height: 1.5;
}

.coupon-card__desc {
  margin-top: 4rpx;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  color: #66666c;
  font-size: 24rpx;
  line-height: 1.4;
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
  color: #ffffff;
  font-size: 26rpx;
  line-height: 1;
  font-weight: 700;
}

.coupon-card__button--red {
  background: #ff4038;
  box-shadow: 0 8rpx 18rpx rgba(255, 64, 56, 0.2);
}

.coupon-card__button--disabled {
  background: #d8d8dd;
  box-shadow: none;
  color: #ffffff;
}

.coupon-card__button--hover {
  opacity: 0.88;
}

.coupon-radio {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40rpx;
  height: 40rpx;
  border: 3rpx solid #d8d8dd;
  border-radius: 20rpx;
  background: #ffffff;
  box-sizing: border-box;
  flex-shrink: 0;
}

.coupon-radio--active {
  border-color: var(--brand-primary, #ea580c);
  background: var(--brand-primary, #ea580c);
}

.coupon-card__radio {
  position: absolute;
  right: 28rpx;
  top: 80rpx;
}
</style>
