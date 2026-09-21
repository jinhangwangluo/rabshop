<template>
  <view
    v-if="visible"
    class="gift-recharge-panel"
    :class="panelClass"
    @click.stop
    @touchmove.stop.prevent="noop"
  >
    <view class="gift-recharge-panel__close" @click="$emit('close')">
      <text class="iconfont icon-close"></text>
    </view>

    <view class="gift-recharge-panel__head">
      <view class="gift-recharge-panel__title-group">
        <text class="gift-recharge-panel__title">充值金币</text>
        <text class="gift-recharge-panel__rate">1 元 = {{ exchangeRateText }} 金币</text>
      </view>
      <text class="gift-recharge-panel__balance">当前余额：{{ coinBalanceText }} 金币</text>
    </view>

    <scroll-view scroll-y class="gift-recharge-panel__scroll">
      <view v-if="rechargeOptions.length" class="gift-recharge-grid">
        <view
          v-for="item in displayRechargeOptions"
          :key="item.key"
          class="gift-recharge-card"
          :class="item.activeClass"
          @click="handleAmountSelect(item)"
        >
          <template v-if="item.isCustom">
            <view class="gift-recharge-card__coin-row gift-recharge-card__coin-row--custom" @click.stop="handleCustomFocus(item)">
              <input
                class="gift-recharge-card__input gift-recharge-card__input--custom"
                type="number"
                :value="customCoinAmount"
                placeholder="自定义"
                placeholder-class="gift-recharge-card__input-placeholder"
                @focus="handleCustomFocus(item)"
                @input="handleCustomCoinAmountInput"
              />
              <text v-if="customCoinAmountValue > 0" class="gift-recharge-card__coin-unit">金币</text>
            </view>
            <view class="gift-recharge-card__divider"></view>
            <text class="gift-recharge-card__price">¥{{ payableAmountText }}</text>
          </template>
          <template v-else>
            <view v-if="item.tag" class="gift-recharge-card__tag">
              <text>{{ item.tag }}</text>
            </view>
            <view class="gift-recharge-card__coin-row">
              <text class="gift-recharge-card__coin-num">{{ item.coinAmountText }}</text>
              <text class="gift-recharge-card__coin-unit">金币</text>
            </view>
            <view class="gift-recharge-card__divider"></view>
            <text class="gift-recharge-card__price">¥{{ item.payAmountText }}</text>
          </template>
        </view>
      </view>
      <view v-else class="gift-recharge-empty">
        <text class="gift-recharge-empty__title">暂无充值档位</text>
        <text class="gift-recharge-empty__desc">请稍后再试或联系平台配置直播金币档位。</text>
      </view>

      <view class="gift-recharge-pay-row" @click="openPaymentModal">
        <view class="gift-recharge-pay-row__icon">
          <text class="iconfont icon-pay"></text>
        </view>
        <view class="gift-recharge-pay-row__main">
          <text class="gift-recharge-pay-row__label">{{ selectedPayMethodTitle }}</text>
          <text class="gift-recharge-pay-row__desc">{{ selectedPayMethodDesc }}</text>
        </view>
        <text class="iconfont icon-right gift-recharge-pay-row__arrow"></text>
      </view>
    </scroll-view>

    <view class="gift-recharge-panel__footer">
      <view
        class="gift-recharge-submit"
        :class="!canSubmitRecharge ? 'gift-recharge-submit--disabled' : ''"
        @click="handleRechargeSubmit"
      >
        <text>{{ rechargeSubmitText }}</text>
      </view>
    </view>

    <view v-if="paymentModalVisible" class="gift-payment-modal" @click.stop="closePaymentModal">
      <view class="gift-payment-modal__panel" @click.stop>
        <view class="gift-payment-modal__head">
          <text class="gift-payment-modal__title">选择支付方式</text>
          <text class="gift-payment-modal__close" @click="closePaymentModal">关闭</text>
        </view>
        <payment-method-list
          v-if="rechargeMethods.length"
          class="gift-payment-modal__methods"
          :title="''"
          :methods="rechargeMethods"
          :value="rechargePayMethod"
          :balance="rechargeBalance"
          @input="handlePaymentMethodSelect"
        />
        <view v-else class="gift-recharge-methods__empty">暂无可用支付方式</view>
      </view>
    </view>
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
import PaymentMethodList from '@/components/payment-method-list/index.vue'
import { computed, ref, watch } from 'vue'

defineOptions({ name: 'LiveGiftRechargeSheet' })

const props = defineProps({
  visible: Boolean,
  animationState: {
    type: String,
    default: ''
  },
  landscape: Boolean,
  rechargeOptions: {
    type: Array,
    default: () => []
  },
  selectedOptionKey: {
    type: [String, Number],
    default: ''
  },
  coinBalance: {
    type: [String, Number],
    default: 0
  },
  exchangeRate: {
    type: [String, Number],
    default: 10
  },
  payableAmount: {
    type: [String, Number],
    default: 0
  },
  customCoinAmount: {
    type: [String, Number],
    default: ''
  },
  rechargeMethods: {
    type: Array,
    default: () => []
  },
  rechargePayMethod: {
    type: String,
    default: ''
  },
  rechargeBalance: {
    type: [String, Number],
    default: 0
  },
  rechargeSubmitting: Boolean,
  canSubmitRecharge: Boolean,
  rechargeLoading: Boolean
})

const emit = defineEmits(['amount-select', 'custom-coin-input', 'pay-method-change', 'submit', 'close'])

const paymentModalVisible = ref(false)

watch(() => props.visible, (value) => {
  if (!value) {
    paymentModalVisible.value = false
  }
})

const panelClass = computed(() => {
  const classNames = []
  if (props.animationState === 'enter') classNames.push('gift-recharge-panel--enter')
  if (props.landscape) classNames.push('gift-recharge-panel--landscape')
  return classNames.join(' ')
})

const displayRechargeOptions = computed(() => {
  return props.rechargeOptions.map((item) => ({
    ...item,
    activeClass: String(item.key) === String(props.selectedOptionKey) ? 'gift-recharge-card--active' : ''
  }))
})

const exchangeRateText = computed(() => {
  return formatNumber(props.exchangeRate, 0)
})

const coinBalanceText = computed(() => {
  return formatNumber(props.coinBalance, 0)
})

const payableAmountText = computed(() => {
  return formatMoney(props.payableAmount)
})

const customCoinAmountValue = computed(() => {
  const integerPart = String(props.customCoinAmount || '').split('.')[0]
  const amount = Number.parseInt(integerPart.replace(/[^\d]/g, ''), 10)
  return Number.isFinite(amount) && amount > 0 ? amount : 0
})

const rechargeSubmitText = computed(() => {
  if (props.rechargeSubmitting) return '支付中...'
  if (props.rechargeLoading) return '加载中...'
  return `立即支付 ¥${payableAmountText.value}`
})

const selectedPayMethodTitle = computed(() => {
  if (!props.rechargePayMethod) return '选择支付方式'
  const matchedMethod = props.rechargeMethods.find((item) => {
    const payType = String(item.pay_type || '')
    if (props.rechargePayMethod === 'balance') return payType === 'yue'
    return payType === props.rechargePayMethod
  })
  if (matchedMethod) {
    return matchedMethod.title || matchedMethod.name || '支付方式'
  }
  const titleMap = {
    balance: '余额支付',
    yue: '余额支付',
    wechat: '微信支付',
    alipay: '支付宝支付'
  }
  return titleMap[props.rechargePayMethod] || '支付方式'
})

const selectedPayMethodDesc = computed(() => {
  const payMethod = String(props.rechargePayMethod || '')
  if (payMethod === 'balance' || payMethod === 'yue') {
    return `当前余额：¥${formatMoney(props.rechargeBalance)}`
  }
  return '点击可更换支付方式'
})

function noop() {}

function handleAmountSelect(item = {}) {
  emit('amount-select', item)
}

function handleCustomFocus(item = {}) {
  emit('amount-select', item)
}

function handleCustomCoinAmountInput(event) {
  const value = event && event.detail ? event.detail.value : ''
  emit('custom-coin-input', value)
}

function openPaymentModal() {
  if (!props.rechargeMethods.length) {
    uni.showToast({ title: '暂无可用支付方式', icon: 'none' })
    return
  }
  paymentModalVisible.value = true
}

function closePaymentModal() {
  paymentModalVisible.value = false
}

function handlePaymentMethodSelect(value = '') {
  emit('pay-method-change', value)
  closePaymentModal()
}

function handleRechargeSubmit() {
  if (!props.canSubmitRecharge) {
    uni.showToast({ title: '请选择充值档位或输入整数金币', icon: 'none' })
    return
  }
  emit('submit')
}

function formatNumber(value, digits = 0) {
  const number = Number(value || 0)
  if (!Number.isFinite(number)) return '0'
  return number.toLocaleString(undefined, {
    minimumFractionDigits: digits,
    maximumFractionDigits: digits
  })
}

function formatMoney(value) {
  const number = Number(value || 0)
  if (!Number.isFinite(number)) return '0'
  if (Math.round(number) === number) return `${number}`
  return number.toFixed(2)
}
</script>

<style lang="scss" src="./style.scss"></style>
