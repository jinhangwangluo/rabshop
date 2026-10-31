<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="recharge-page">
    <app-safe-top
      title="余额充值"
      background="#fff"
      title-color="#18181b"
      :icon-color="themePrimaryColor"
    >

    </app-safe-top>

    <view class="container">
      <view class="balance-card">
        <view class="balance-card__main">
          <text class="balance-card__label">当前余额（元）</text>
          <view class="balance-card__amount-row">
            <text class="balance-card__currency">¥</text>
            <text class="balance-card__amount">{{ balanceDisplay }}</text>
          </view>
        </view>
        <view class="balance-card__badge">
          <view class="balance-card__badge-inner">
            <text class="balance-card__badge-symbol">¥</text>
          </view>
        </view>
      </view>

      <view class="section">
        <text class="section__title">选择充值金额</text>
        <view class="amount-list">
          <view
            v-for="item in amountPresets"
            :key="item.key"
            :class="['amount-item', { 'amount-item--active': selectedAmountType === 'preset' && selectedAmount === item.value }]"
            @click="selectPreset(item)"
          >
            <text class="amount-item__text">{{ item.payAmount }}</text>
            <text v-if="item.label" class="amount-item__tag">{{ item.label }}</text>
          </view>

          <view :class="['amount-item', 'amount-item--custom', { 'amount-item--active': selectedAmountType === 'custom' }]">
            <view class="amount-item__input-shell">
              <input
                class="amount-input"
                type="digit"
                :value="customAmount"
                placeholder="其他金额"
                placeholder-class="amount-input__placeholder"
                @focus="selectCustom"
                @input="onCustomInput"
              />
            </view>
          </view>
        </view>
      </view>

      <view class="section">
        <text class="section__title">支付方式</text>
        <payment-method-list
          v-model="payMethod"
          :title="''"
          :methods="paymentMethods"
          :balance="balance"
          :disabled-types="disabledPayTypes"
        />
      </view>

      <view class="tip-card">
        <view class="tip-card__icon">
          <text class="tip-card__icon-text">i</text>
        </view>
        <text class="tip-card__text">自定义充值金额无法获得优惠；请谨慎选择！充值后无法退款，退款还需联系平台客服</text>
      </view>

      <view class="footer-placeholder"></view>
    </view>

    <view class="footer-bar">
      <view class="footer-bar__summary">
        <text class="footer-bar__label">应付金额</text>
        <view class="footer-bar__price-row">
          <text class="footer-bar__currency">¥</text>
          <text class="footer-bar__amount">{{ payableDisplay }}</text>
        </view>
      </view>
      <button class="footer-bar__button" :disabled="!canSubmit" @click="handleSubmit">立即充值</button>
    </view>

    <app-floating-button />
  </view>
</template>

<script setup>
import PaymentMethodList from '@/components/payment-method-list/index.vue'
import { createRechargeOrder, getRechargeDetail, getRechargeOptions } from '@/api/balance'
import { getPaymentMethods } from '@/api/payment'
import { normalizePublicErrorMessage } from '@/utils/error'
import { invokePayment } from '@/utils/payment'
import { PLATFORM_SCENES, getPlatformScene, getTerminalType } from '@/utils/platform'
import { computed, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'

defineOptions({ name: 'UsersRechargeIndex' })

const balance = ref(0)
const amountPresets = ref([])
const selectedAmountType = ref('preset')
const selectedAmount = ref(0)
const selectedRechargeId = ref(0)
const customAmount = ref('')
const payMethod = ref('')
const paymentMethods = ref([])
const disabledPayTypes = ['yue', 'offline']
const scene = ref(PLATFORM_SCENES.H5)
const submitting = ref(false)
const loading = ref(false)

const actualAmount = computed(() => {
  if (selectedAmountType.value === 'custom') {
    const normalized = String(customAmount.value || '').replace(/[^\d.]/g, '')
    const amount = Number(normalized)
    return Number.isFinite(amount) && amount > 0 ? Math.round(amount * 100) / 100 : 0
  }

  return selectedAmount.value || 0
})

const payableAmount = computed(() => {
  if (selectedAmountType.value !== 'preset') {
    return actualAmount.value
  }

  const current = amountPresets.value.find((item) => item.id === selectedRechargeId.value)
  return current ? current.payAmount : actualAmount.value
})

const payableDisplay = computed(() => {
  return payableAmount.value.toFixed(2)
})

const balanceDisplay = computed(() => {
  return balance.value.toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
})

const canSubmit = computed(() => {
  return actualAmount.value > 0 && Boolean(payMethod.value) && !submitting.value
})

onLoad((options = {}) => {
  scene.value = getPlatformScene()
  const returnOrderSn = String(options.order_sn || options.orderSn || '').trim()
  loadRechargeOptions().then(() => {
    if (returnOrderSn) {
      checkRechargeReturn(returnOrderSn)
    }
  })
})

function goHistory() {
  uni.navigateTo({ url: '/pages/users/history/index' })
}

function selectPreset(item) {
  selectedAmountType.value = 'preset'
  selectedAmount.value = item.value
  selectedRechargeId.value = item.id
}

function selectCustom() {
  selectedAmountType.value = 'custom'
  selectedRechargeId.value = 0
}

function onCustomInput(event) {
  const value = event && event.detail ? event.detail.value : ''
  customAmount.value = value
  selectedAmountType.value = 'custom'
}

async function handleSubmit() {
  if (!canSubmit.value) {
    return
  }

  submitting.value = true
  try {
    const payload = {
      amount: actualAmount.value,
      pay_type: payMethod.value,
      terminal: getTerminalType(),
      return_url: buildPaymentReturnUrl()
    }
    if (selectedAmountType.value === 'preset' && selectedRechargeId.value > 0) {
      payload.recharge_id = selectedRechargeId.value
    }
    if (payMethod.value === 'wechat') {
      payload.trade_type = resolveTradeType()
    }

    const res = await createRechargeOrder(payload)
    if (!res || res.code !== 200 || !res.data) {
      showToast((res && res.msg) || '充值支付发起失败')
      return
    }

    await handlePayResponse(res.data)
  } catch (error) {
    showToast(extractErrorMessage(error, '充值支付发起失败，请稍后重试'))
  } finally {
    submitting.value = false
  }
}

async function loadRechargeOptions() {
  loading.value = true
  try {
    const terminal = getTerminalType()
    const [res, paymentRes] = await Promise.all([
      getRechargeOptions({ terminal }, { showError: false, loginRedirect: true }),
      getPaymentMethods({ terminal }, { showError: false, loginRedirect: true })
    ])
    if (!res || res.code !== 200 || !res.data) {
      showToast((res && res.msg) || '充值配置加载失败')
      return
    }

    const data = res.data || {}
    const paymentData = paymentRes && paymentRes.code === 200 && paymentRes.data ? paymentRes.data : {}
    balance.value = parseAmount(paymentData.balance ?? data.balance, 0)
    amountPresets.value = normalizeAmountPresets(data.amounts || [])
    paymentMethods.value = normalizeRechargeMethods(paymentData.methods || [])
    if (amountPresets.value.length > 0) {
      selectedAmount.value = amountPresets.value[0].value
      selectedRechargeId.value = amountPresets.value[0].id
      selectedAmountType.value = 'preset'
    } else {
      selectedAmount.value = 0
      selectedRechargeId.value = 0
      selectedAmountType.value = 'custom'
    }
    if (paymentMethods.value.length > 0) {
      payMethod.value = mapPayType(paymentMethods.value[0].pay_type)
    } else {
      payMethod.value = ''
    }
  } finally {
    loading.value = false
  }
}

function normalizeAmountPresets(list = []) {
  return (Array.isArray(list) ? list : []).map((item, index) => {
    const amount = parseAmount(item.off_amt, 0)
    const payAmount = parseAmount(item.price, amount)
    if (amount <= 0) {
      return null
    }

    return {
      id: Number(item.id || item.combination_data_id || 0),
      value: amount,
      payAmount,
      label:item.title,
      key: String(item.id || item.key || `${amount}-${index}`)
    }
  }).filter(Boolean)
}

function normalizeRechargeMethods(list = []) {
  return (Array.isArray(list) ? list : []).filter((item) => {
    return ['wechat', 'alipay'].includes(String(item.pay_type || ''))
  })
}

async function handlePayResponse(payload = {}) {
  const orderSn = String(payload.order_sn || '')
  const payType = normalizePayType(payload.pay_type || payMethod.value)
  const statusCode = Number(payload.status || 0)
  const statusText = String(payload.status_text || '')
  const hasPaid = statusCode === 1 || statusText === '已支付'

  if (hasPaid) {
    await handleRechargeSuccess(orderSn)
    return
  }

  const paymentConfig = payload.config && typeof payload.config === 'object' ? payload.config : null
  if (!paymentConfig) {
    showToast(statusText || '支付参数获取失败')
    return
  }

  await invokePayment({
    payType,
    config: paymentConfig,
    scene: scene.value,
    returnUrl: buildPaymentReturnUrl(orderSn)
  })

  const paid = await pollRechargeStatus(orderSn)
  if (paid) {
    await handleRechargeSuccess(orderSn)
    return
  }

  showToast('支付已提交，请稍后查看余额')
}

async function pollRechargeStatus(orderSn = '') {
  if (!orderSn) {
    return false
  }
  for (let index = 0; index < 3; index += 1) {
    await wait(800)
    const res = await getRechargeDetail({ order_sn: orderSn }, { showError: false, loginRedirect: false })
    if (res && res.code === 200 && res.data && Number(res.data.status || 0) === 1) {
      return true
    }
  }
  return false
}

async function handleRechargeSuccess() {
  uni.showToast({ title: '充值成功', icon: 'success' })
  await loadRechargeOptions()
}

async function checkRechargeReturn(orderSn = '') {
  const paid = await pollRechargeStatus(orderSn)
  if (paid) {
    await handleRechargeSuccess(orderSn)
  }
}

function resolveTradeType() {
  if (scene.value === PLATFORM_SCENES.MINIAPP) {
    return 'miniapp'
  }
  if (scene.value === PLATFORM_SCENES.H5) {
    return 'h5'
  }
  return 'official_account'
}

function buildPaymentReturnUrl(orderSn = '') {
  // #ifdef H5
  if (typeof window === 'undefined' || !window.location) {
    return ''
  }
  const baseUrl = `${window.location.origin}${window.location.pathname}`
  const query = orderSn ? `order_sn=${encodeURIComponent(orderSn)}` : ''
  const pages = getCurrentPages()
  const currentPage = pages[pages.length - 1]
  const currentRoute = currentPage && currentPage.route ? `/${currentPage.route}` : '/pages/users/recharge/index'
  return `${baseUrl}#${currentRoute}${query ? `?${query}` : ''}`
  // #endif

  // #ifndef H5
  return ''
  // #endif
}

function normalizePayType(payType = '') {
  const normalized = String(payType || '').trim().toLowerCase()
  if (['weixin', 'wxpay', 'wechat'].includes(normalized)) {
    return 'wechat'
  }
  if (['zhifubao', 'alipay'].includes(normalized)) {
    return 'alipay'
  }
  return normalized
}

function mapPayType(payType = '') {
  const map = { wechat: 'wechat', alipay: 'alipay' }
  return map[payType] || payType
}

function parseAmount(value, fallback = 0) {
  const normalized = String(value === undefined || value === null ? '' : value).replace(/,/g, '').replace('¥', '').trim()
  const parsed = Number(normalized)
  return Number.isFinite(parsed) ? Math.max(parsed, 0) : fallback
}

function wait(ms = 300) {
  return new Promise((resolve) => {
    setTimeout(() => resolve(), ms)
  })
}

function extractErrorMessage(error, fallback = '操作失败') {
  if (!error) {
    return fallback
  }
  if (typeof error === 'string') {
    return error || fallback
  }
  return normalizePublicErrorMessage(error.msg || error.message || error.errMsg, fallback)
}

function showToast(title) {
  uni.showToast({
    title: normalizePublicErrorMessage(title, '操作失败'),
    icon: 'none'
  })
}
</script>

<style lang="scss">
page {
  background: #f9f9f9;
}
</style>

<style lang="scss" scoped>
.recharge-page {
  --recharge-bg: #f9f9f9;
  --recharge-surface: #ffffff;
  --recharge-surface-low: #f3f3f3;
  --recharge-border: #e2e2e2;
  --recharge-divider: #f4f4f5;
  --recharge-text: #1a1c1c;
  --recharge-text-strong: #18181b;
  --recharge-text-muted: #4c4546;
  --recharge-icon-muted: #71717a;
  --recharge-accent: var(--brand-primary, #ea580c);
  --recharge-accent-strong: var(--brand-primary, #ea580c);
  --recharge-accent-light: var(--brand-primary-light, #fb923c);
  --recharge-accent-soft: var(--brand-primary-pale, #fff7ed);
  --recharge-accent-border: #ffedd5;
  --recharge-accent-dark: #9a3412;
  min-height: 100%;
  background: var(--recharge-bg);
}

.top-action {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 64rpx;
  min-height: 64rpx;
  color: var(--recharge-icon-muted);

  &__icon {
    font-size: 38rpx;
    line-height: 1;
    color: inherit;
  }
}

.top-action--hover {
  opacity: 0.72;
}

.container {
  padding: 24rpx 24rpx 0;
}

.balance-card,
.tip-card {
  background: var(--recharge-surface);
  border-radius: 18rpx;
  box-shadow: 0 6rpx 18rpx rgba(15, 23, 42, 0.04);
}

.balance-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 28rpx 28rpx 28rpx 26rpx;

  &__label {
    display: block;
    font-size: 22rpx;
    line-height: 1.2;
    color: var(--recharge-text-muted);
  }

  &__amount-row {
    display: flex;
    align-items: baseline;
    margin-top: 12rpx;
  }

  &__currency {
    margin-right: 6rpx;
    font-size: 28rpx;
    font-weight: 600;
    color: var(--recharge-text);
  }

  &__amount {
    font-size: 56rpx;
    line-height: 1;
    font-weight: 700;
    color: var(--recharge-text);
    letter-spacing: -1rpx;
  }

  &__badge {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 82rpx;
    height: 82rpx;
    border-radius: 50%;
    background: var(--recharge-surface-low);
  }

  &__badge-inner {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 46rpx;
    height: 46rpx;
    border-radius: 14rpx;
    background: var(--recharge-accent-strong);
    box-shadow: none;
  }

  &__badge-symbol {
    font-size: 24rpx;
    font-weight: 700;
    color: #ffffff;
    line-height: 1;
  }
}

.section {
  margin-top: 34rpx;

  &__title {
    display: block;
    margin-bottom: 18rpx;
    font-size: 38rpx;
    line-height: 1.2;
    font-weight: 700;
    color: var(--recharge-text-strong);
  }
}

.amount-list {
  display: flex;
  flex-wrap: wrap;
  margin: -12rpx;
}

.amount-item {
  box-sizing: border-box;
  width: calc(33.333% - 24rpx);
  min-height: 118rpx;
  margin: 12rpx;
  padding: 0 10rpx;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: var(--recharge-surface);
  border-radius: 16rpx;

  &__text {
    font-size: 34rpx;
    line-height: 1.2;
    font-weight: 700;
    color: var(--recharge-text-strong);
  }

  &__tag {
    margin-top: 10rpx;
    font-size: 21rpx;
    line-height: 1.2;
    color: var(--recharge-accent-light);
  }

  &__input-shell {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 10rpx 0;
    border-radius: 12rpx;
    background: var(--recharge-surface-low);
  }

  &--custom {
    padding: 10rpx;
  }

  &--active {
    border-color: var(--brand-shadow-hover, rgba(234, 88, 12, 0.26));
    background: var(--recharge-surface);
    box-shadow: 0 0 0 2rpx var(--brand-focus-shadow, rgba(234, 88, 12, 0.16));
  }

  &--active .amount-item__text {
    color: var(--recharge-accent);
  }
}

.amount-input {
  width: 100%;
  height: 72rpx;
  text-align: center;
  font-size: 28rpx;
  font-weight: 600;
  color: var(--recharge-text);
}

.amount-input__placeholder {
  color: var(--recharge-text-muted);
  font-size: 28rpx;
  font-weight: 500;
}

::v-deep .pay-methods-card {
  margin-top: 0;
  background: var(--recharge-surface);
  border-radius: 18rpx;
  box-shadow: 0 6rpx 18rpx rgba(15, 23, 42, 0.04);
}

.tip-card {
  display: flex;
  align-items: flex-start;
  margin-top: 28rpx;
  padding: 18rpx 20rpx;
  background: var(--recharge-accent-soft);
  border-color: var(--recharge-accent-border);

  &__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28rpx;
    height: 28rpx;
    margin-right: 12rpx;
    border-radius: 50%;
    background: var(--brand-primary-pale, #fff7ed);
    flex-shrink: 0;
  }

  &__icon-text {
    font-size: 20rpx;
    line-height: 1;
    font-weight: 700;
    color: var(--recharge-accent);
  }

  &__text {
    font-size: 24rpx;
    line-height: 1.7;
    color: var(--recharge-accent-dark);
  }
}

.footer-placeholder {
  height: 208rpx;
}

.footer-bar {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 10;
  display: flex;
  align-items: center;
  padding: 18rpx 24rpx;
  background: rgba(255, 255, 255, 0.9);
  box-shadow: 0 -8rpx 24rpx rgba(234, 88, 12, 0.05);
  padding-bottom: calc(18rpx + env(safe-area-inset-bottom));

  /* #ifdef H5 */
  bottom: var(--window-bottom);
  /* #endif */

  &__summary {
    flex: 1;
    padding-right: 20rpx;
  }

  &__label {
    display: block;
    font-size: 22rpx;
    line-height: 1.2;
    color: var(--recharge-text-muted);
  }

  &__price-row {
    display: flex;
    align-items: baseline;
    margin-top: 10rpx;
  }

  &__currency {
    margin-right: 6rpx;
    font-size: 26rpx;
    line-height: 1;
    font-weight: 700;
    color: var(--recharge-accent);
  }

  &__amount {
    font-size: 54rpx;
    line-height: 1;
    font-weight: 700;
    color: var(--recharge-accent);
  }

  &__button {
    min-width: 236rpx;
    height: 88rpx;
    margin: 0;
    padding: 0 34rpx;
    border: 0;
    border-radius: 999rpx;
    background: var(--recharge-accent-strong);
    box-shadow: 0 10rpx 22rpx var(--brand-shadow, rgba(234, 88, 12, 0.18));
    font-size: 30rpx;
    line-height: 88rpx;
    font-weight: 700;
    color: #ffffff;
  }

  &__button::after {
    border: 0;
  }

  &__button:disabled {
    opacity: 0.55;
    box-shadow: none;
  }
}
</style>
