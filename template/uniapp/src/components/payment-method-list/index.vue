<template>
  <view class="pay-methods-card">
    <text v-if="title" class="pay-methods-card__title">{{ title }}</text>

    <view
      v-for="(item, index) in displayMethods"
      :key="item.key"
      :class="['pay-method', { 'pay-method--last': index === displayMethods.length - 1, 'pay-method--active': selectedValue === item.key }]"
      @click="handleSelect(item)"
    >
      <view class="pay-method__left">
        <view :class="['pay-method__badge', item.badgeClass]">
          <image v-if="item.image" class="pay-method__badge-img" :src="item.image" mode="aspectFit"></image>
          <text v-else :class="['pay-method__badge-text', { 'pay-method__badge-text--tiny': item.badgeText.length > 1 }]">{{ item.badgeText }}</text>
        </view>

        <view class="pay-method__info">
          <view class="pay-method__title-row">
            <text class="pay-method__title">{{ item.title }}</text>
            <text v-if="item.tag" class="pay-method__tag">{{ item.tag }}</text>
          </view>
          <text v-if="item.desc" class="pay-method__desc">{{ item.desc }}</text>
        </view>
      </view>

      <view :class="['pay-method__radio', { 'pay-method__radio--checked': selectedValue === item.key }]">
        <view v-if="selectedValue === item.key" class="pay-method__radio-dot"></view>
      </view>
    </view>
  </view>
</template>

<script setup>
import { computed } from 'vue'

defineOptions({
  name: 'PaymentMethodList'
})

const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  },
  methods: {
    type: Array,
    default: () => []
  },
  value: {
    type: String,
    default: ''
  },
  balance: {
    type: [Number, String],
    default: 0
  },
  title: {
    type: String,
    default: '选择支付方式'
  },
  disabledTypes: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['update:modelValue', 'input', 'change'])

const selectedValue = computed(() => {
  return props.modelValue || props.value || ''
})

const displayMethods = computed(() => {
  return props.methods
    .filter((method) => props.disabledTypes.indexOf(String(method.pay_type || '')) === -1)
    .map((method) => {
      const frontendKey = mapPayType(method.pay_type)
      const badge = getPayTypeBadge(method.pay_type)
      const desc = method.pay_type === 'yue'
        ? `可用余额 ¥${formatAmount(props.balance)}`
        : (method.subtitle || '')

      return {
        key: frontendKey,
        title: method.title,
        desc,
        tag: method.pay_type === 'wechat' ? '推荐' : '',
        theme: method.pay_type,
        image: method.image || '',
        badgeClass: badge.class,
        badgeText: badge.text
      }
    })
})

function handleSelect(item = {}) {
  if (!item.key) {
    return
  }

  emit('update:modelValue', item.key)
  emit('input', item.key)
  emit('change', item.key)
}

function mapPayType(payType = '') {
  const map = { wechat: 'wechat', alipay: 'alipay', yue: 'balance', offline: 'offline' }
  return map[payType] || payType
}

function getPayTypeBadge(payType = '') {
  const badgeMap = {
    wechat: { class: 'pay-method__badge--wechat', text: '¥' },
    alipay: { class: 'pay-method__badge--alipay', text: '宝' },
    yue: { class: 'pay-method__badge--balance', text: '余' },
    offline: { class: 'pay-method__badge--offline', text: '线' }
  }
  return badgeMap[payType] || { class: 'pay-method__badge--balance', text: '付' }
}

function formatAmount(value) {
  const amount = Number(value || 0)
  return Number.isFinite(amount) ? amount.toFixed(2) : '0.00'
}
</script>

<style lang="scss" scoped>
.pay-methods-card {
  margin-top: 26rpx;
  padding: 38rpx 28rpx 16rpx;
  border-radius: 24rpx;
  background: #ffffff;
  box-shadow: 0 8rpx 24rpx rgba(15, 23, 42, 0.04);

  &__title {
    display: block;
    margin-bottom: 16rpx;
    font-size: 34rpx;
    line-height: 48rpx;
    font-weight: 700;
    color: #1a1c1c;
  }
}

.pay-method {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 144rpx;
  border-bottom: 1rpx solid #f1f1f3;

  &--last {
    border-bottom: 0;
  }

  &__left {
    display: flex;
    align-items: center;
    min-width: 0;
    flex: 1;
  }

  &__badge {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 82rpx;
    height: 82rpx;
    border-radius: 16rpx;
    flex-shrink: 0;
    overflow: hidden;
  }

  &__badge-img {
    width: 100%;
    height: 100%;
  }

  &__badge-text {
    font-size: 34rpx;
    line-height: 1;
    font-weight: 700;
    color: #ffffff;
  }

  &__badge--wechat .pay-method__badge-text {
    color: #07c160;
  }

  &__badge--balance .pay-method__badge-text,
  &__badge--offline .pay-method__badge-text {
    color: #6f7176;
  }

  &__badge--alipay .pay-method__badge-text {
    color: #2f6dff;
  }

  &__badge-text--tiny {
    font-size: 24rpx;
  }

  &__info {
    min-width: 0;
    margin-left: 24rpx;
    flex: 1;
  }

  &__title-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
  }

  &__title {
    font-size: 28rpx;
    line-height: 40rpx;
    font-weight: 700;
    color: #1a1c1c;
  }

  &__tag {
    margin-left: 14rpx;
    padding: 4rpx 12rpx;
    border-radius: 999rpx;
    background: #eaf8ef;
    font-size: 20rpx;
    line-height: 28rpx;
    font-weight: 700;
    color: #07a34c;
  }

  &__desc {
    display: block;
    margin-top: 8rpx;
    font-size: 24rpx;
    line-height: 34rpx;
    font-weight: 400;
    color: #6f7176;
  }

  &__radio {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 42rpx;
    height: 42rpx;
    margin-left: 24rpx;
    border: 4rpx solid #d8d8dc;
    border-radius: 50%;
    box-sizing: border-box;
    flex-shrink: 0;
  }

  &__radio--checked {
    border-color: var(--brand-primary, #ea580c);
  }

  &__radio-dot {
    width: 20rpx;
    height: 20rpx;
    border-radius: 50%;
    background: var(--brand-primary, #ea580c);
  }
}
</style>
