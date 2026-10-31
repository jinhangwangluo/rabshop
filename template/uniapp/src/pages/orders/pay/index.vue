<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="order-pay-page">
    <app-safe-top title="确认支付" background="#f5f5f5" mp-extra="8rpx"></app-safe-top>

    <view class="order-pay-page__content">
      <view v-if="loading && !pageReady" class="page-loading">
        <six-petal-spiral />
      </view>

      <block v-else>
        <view class="pay-summary-card">
          <text class="pay-summary-card__label">支付金额</text>
          <view class="pay-summary-card__amount-row">
            <text class="pay-summary-card__currency">¥</text>
            <text class="pay-summary-card__amount">{{ payableDisplay }}</text>
          </view>

          <view class="pay-summary-card__divider"></view>

          <view class="pay-summary-card__meta-row">
            <text class="pay-summary-card__meta-label">订单编号</text>
            <text class="pay-summary-card__meta-value">{{ orderInfo.orderSn || '--' }}</text>
          </view>

          <view v-if="showCountdown" class="pay-summary-card__countdown">
            <uni-icons type="clock" size="16" :color="themePrimaryColor"></uni-icons>
            <text class="pay-summary-card__countdown-text">支付剩余时间 {{ countdownText }}</text>
          </view>

          <view v-if="orderPaid" class="pay-summary-card__countdown pay-summary-card__countdown--success">
            <uni-icons type="checkbox-filled" size="16" color="#16a34a"></uni-icons>
            <text class="pay-summary-card__countdown-text pay-summary-card__countdown-text--success">订单已完成支付</text>
          </view>

          <view v-else-if="isExpired" class="pay-summary-card__countdown pay-summary-card__countdown--expired">
            <uni-icons type="info-filled" size="16" color="#dc2626"></uni-icons>
            <text class="pay-summary-card__countdown-text pay-summary-card__countdown-text--expired">支付时间已过期，请重新下单或联系客服</text>
          </view>
        </view>

        <payment-method-list
          v-model="selectedPayMethod"
          :methods="paymentMethods"
          :balance="balanceAmount"
        />

      </block>
    </view>

    <view class="pay-footer">
      <view class="pay-footer__summary">
        <text class="pay-footer__label">应付总额</text>
        <view class="pay-footer__amount-row">
          <text class="pay-footer__currency">¥</text>
          <text class="pay-footer__amount">{{ payableDisplay }}</text>
        </view>
      </view>
      <button class="pay-footer__button" :loading="submitting" :disabled="!canSubmit" @click="handleSubmit">
        {{ submitButtonText }}
      </button>
    </view>
  </view>
</template>

<script setup>
import { computed, ref } from 'vue'
import { onLoad, onUnload } from '@dcloudio/uni-app'
import { getOrderDetail, repayOrder, getPaymentMethods } from '@/api/order'
import PaymentMethodList from '@/components/payment-method-list/index.vue'
import { getToken } from '@/utils/auth'
import { normalizePublicErrorMessage } from '@/utils/error'
import { invokePayment } from '@/utils/payment'
import { PLATFORM_SCENES, getPlatformScene, getTerminalType } from '@/utils/platform'

defineOptions({ name: 'OrderPayPage' })

const ORDER_PAY_EXPIRE_MINUTES = 30

const loading = ref(false)
const submitting = ref(false)
const scene = ref(PLATFORM_SCENES.H5)
const orderId = ref(0)
const orderSn = ref('')
const orderInfo = ref({
  orderSn: '',
  payAmount: 0,
  createTime: '',
  statusText: '待支付',
  statusCode: 0,
  payType: '',
  payTime: '',
  merchantName: ''
})
const selectedPayMethod = ref('')
const paymentMethods = ref([])
const balanceAmount = ref(0)
const countdownSeconds = ref(0)
const countdownTimer = ref(null)

const pageReady = computed(() => {
  return Boolean(orderInfo.value.orderSn) || Number(orderInfo.value.payAmount) > 0
})
const hasOrderQuery = computed(() => {
  return Boolean(orderSn.value) || Number(orderId.value) > 0
})
const payableAmount = computed(() => {
  const amount = Number(orderInfo.value.payAmount || 0)
  return Number.isFinite(amount) && amount > 0 ? amount : 0
})
const payableDisplay = computed(() => {
  return payableAmount.value.toFixed(2)
})
const countdownText = computed(() => {
  const totalSeconds = Math.max(Number(countdownSeconds.value || 0), 0)
  const minutes = Math.floor(totalSeconds / 60)
  const seconds = totalSeconds % 60
  return `${padNumber(minutes)}:${padNumber(seconds)}`
})
const isExpired = computed(() => {
  return pageReady.value && !orderPaid.value && countdownSeconds.value <= 0
})
const orderPaid = computed(() => {
  const paidStatuses = [1, 2, 6]
  const statusText = String(orderInfo.value.statusText || '')
  return paidStatuses.includes(Number(orderInfo.value.statusCode)) || ['已支付', '待评价', '已完成'].includes(statusText)
})
const showCountdown = computed(() => {
  return !orderPaid.value && countdownSeconds.value > 0
})
const canSubmit = computed(() => {
  if (submitting.value || loading.value) {
    return false
  }
  if (!hasOrderQuery.value) {
    return false
  }
  if (!orderPaid.value && isExpired.value) {
    return false
  }
  return true
})
const submitButtonText = computed(() => {
  if (orderPaid.value) {
    return '查看订单'
  }
  if (isExpired.value) {
    return '订单已过期'
  }
  return '立即支付'
})

onLoad((options = {}) => {
  scene.value = getPlatformScene()
  applyRouteOptions(options)
  bootstrapPage()
})

onUnload(() => {
  clearCountdownTimer()
})

/**
 * 初始化页面。
 *
 * @returns {Promise<void>}
 */
async function bootstrapPage() {
  loading.value = true

  try {
    const tasks = [loadPaymentMethods()]

    if (orderId.value || orderSn.value) {
      tasks.unshift(fetchOrderDetail())
    }

    await Promise.all(tasks)
    if (orderPaid.value) {
      redirectToPayResult('success')
    }
  } finally {
    loading.value = false
  }
}

/**
 * 解析路由参数。
 *
 * @param {Object} options 页面参数。
 * @returns {void}
 */
function applyRouteOptions(options = {}) {
  const parsedOrderId = Number(options.orderId || options.order_id || options.id || 0)
  const parsedOrderSn = String(options.orderSn || options.order_sn || '').trim()
  const routeAmount = parseAmount(options.amount || options.pay_amount || options.payAmount, orderInfo.value.payAmount)

  orderId.value = Number.isNaN(parsedOrderId) ? 0 : parsedOrderId
  orderSn.value = parsedOrderSn
  orderInfo.value.payAmount = routeAmount
  if (parsedOrderSn) {
    orderInfo.value.orderSn = parsedOrderSn
  }
}

/**
 * 获取订单详情并同步支付页数据。
 *
 * @returns {Promise<void>}
 */
async function fetchOrderDetail() {
  const params = orderSn.value ? { order_sn: orderSn.value } : { order_id: orderId.value }
  const res = await getOrderDetail(params, { showError: false, loginRedirect: true })

  if (!res || res.code !== 200 || !res.data) {
    showToast((res && res.msg) || '订单信息加载失败')
    return
  }

  applyOrderDetail(res.data)
}

/**
 * 同步订单详情到页面状态。
 *
 * @param {Object} payload 订单详情。
 * @returns {void}
 */
function applyOrderDetail(payload = {}) {
  const payType = normalizePayType(payload.pay_type || payload.payType || '')
  const normalizedOrderSn = String(payload.order_sn || payload.orderSn || orderInfo.value.orderSn || '').trim()
  const payAmount = parseAmount(payload.pay_amount || payload.payAmount || payload.total_amount, orderInfo.value.payAmount)

  orderId.value = Number(payload.order_id || payload.orderId || orderId.value || 0)
  orderSn.value = normalizedOrderSn || orderSn.value
  orderInfo.value = {
    orderSn: normalizedOrderSn || orderInfo.value.orderSn,
    payAmount,
    createTime: String(payload.create_time || payload.createTime || ''),
    statusText: String(payload.status_text || payload.statusText || orderInfo.value.statusText || '待支付'),
    statusCode: Number(payload.status || orderInfo.value.statusCode || 0),
    payType,
    payTime: String(payload.pay_time || payload.payTime || ''),
    merchantName: String(payload.merchant_name || payload.merchantName || orderInfo.value.merchantName || '平台自营')
  }

  if (payType) {
    selectedPayMethod.value = payType
  }

  syncCountdown(payload)
}

/**
 * 加载可用支付方式及用户余额。
 *
 * @returns {Promise<void>}
 */
async function loadPaymentMethods() {
  if (!getToken()) {
    return
  }

  const terminal = getTerminalType()
  const res = await getPaymentMethods({ terminal }, { showError: false, loginRedirect: false })
  if (!res || res.code !== 200 || !res.data) {
    return
  }

  const data = res.data || {}
  paymentMethods.value = data.methods || []
  balanceAmount.value = parseAmount(data.balance, 0)

  if (!selectedPayMethod.value && paymentMethods.value.length > 0) {
    selectedPayMethod.value = mapPayType(paymentMethods.value[0].pay_type)
  }
}

/**
 * 后端 pay_type 映射为前端支付方式 key。
 *
 * @param {string} payType 后端支付类型。
 * @returns {string}
 */
function mapPayType(payType = '') {
  const map = { wechat: 'wechat', alipay: 'alipay', yue: 'balance', offline: 'offline' }
  return map[payType] || payType
}

/**
 * 根据订单创建时间同步倒计时。
 *
 * @param {Object} payload 订单数据。
 * @returns {void}
 */
function syncCountdown(payload = {}) {
  if (orderPaid.value) {
    countdownSeconds.value = 0
    clearCountdownTimer()
    return
  }

  const expireSeconds = Number(payload.pay_expire_seconds || payload.payExpireSeconds || 0)
  if (Number.isFinite(expireSeconds) && expireSeconds > 0) {
    countdownSeconds.value = expireSeconds
    restartCountdown()
    return
  }

  const createdAt = String(payload.create_time || payload.createTime || orderInfo.value.createTime || '')
  const remainSeconds = resolveRemainingSeconds(createdAt)
  countdownSeconds.value = remainSeconds
  restartCountdown()
}

/**
 * 计算剩余支付秒数。
 *
 * @param {string} createdAt 下单时间。
 * @returns {number}
 */
function resolveRemainingSeconds(createdAt = '') {
  if (!createdAt) {
    return 0
  }

  const parsedTime = new Date(String(createdAt).replace(/-/g, '/')).getTime()
  if (!parsedTime) {
    return 0
  }

  const expireAt = parsedTime + ORDER_PAY_EXPIRE_MINUTES * 60 * 1000
  const remainMs = expireAt - Date.now()
  if (remainMs <= 0) {
    return 0
  }

  return Math.floor(remainMs / 1000)
}

/**
 * 启动倒计时。
 *
 * @returns {void}
 */
function restartCountdown() {
  clearCountdownTimer()
  if (countdownSeconds.value <= 0 || orderPaid.value) {
    return
  }

  countdownTimer.value = setInterval(() => {
    if (countdownSeconds.value <= 1) {
      countdownSeconds.value = 0
      clearCountdownTimer()
      return
    }

    countdownSeconds.value -= 1
  }, 1000)
}

/**
 * 清理倒计时。
 *
 * @returns {void}
 */
function clearCountdownTimer() {
  if (countdownTimer.value) {
    clearInterval(countdownTimer.value)
    countdownTimer.value = null
  }
}

/**
 * 返回上一页。
 *
 * @returns {void}
 */
function handleBack() {
  uni.navigateBack({
    fail: () => {
      uni.switchTab({ url: '/pages/user/index' })
    }
  })
}

/**
 * 提交支付。
 *
 * @returns {Promise<void>}
 */
async function handleSubmit() {
  if (orderPaid.value) {
    redirectToOrderDetail()
    return
  }

  if (!canSubmit.value) {
    return
  }

  if (!orderInfo.value.orderSn) {
    await fetchOrderDetail()
    if (!orderInfo.value.orderSn) {
      showToast('订单号不存在，暂无法发起支付')
      return
    }
  }

  submitting.value = true
  try {
    const payload = {
      order_sn: orderInfo.value.orderSn,
      pay_type: selectedPayMethod.value,
      return_url: buildPaymentReturnUrl()
    }

    if (selectedPayMethod.value === 'wechat') {
      payload.trade_type = resolveTradeType()
    }

    const res = await repayOrder(payload)
    if (!res || res.code !== 200 || !res.data) {
      redirectToPayResult('failed', 0, (res && res.msg) || '支付发起失败')
      return
    }

    await handlePayResponse(res.data)
  } catch (error) {
    redirectToPayResult(
      'failed',
      0,
      extractErrorMessage(error, '支付发起失败，请稍后重试')
    )
  } finally {
    submitting.value = false
  }
}

/**
 * 处理支付接口返回。
 *
 * @param {Object} payload 支付结果。
 * @returns {Promise<void>}
 */
async function handlePayResponse(payload = {}) {
  const normalizedPayType = normalizePayType(payload.pay_type || selectedPayMethod.value)
  const statusCode = Number(payload.status || 0)
  const statusText = String(payload.status_text || '')
  const hasPaid = [1, 2, 6].includes(statusCode) || ['已支付', '待评价', '已完成'].includes(statusText)

  if (hasPaid || normalizedPayType === 'balance') {
    orderInfo.value.statusCode = hasPaid ? statusCode || 1 : 1
    orderInfo.value.statusText = hasPaid ? statusText || '已支付' : '已支付'
    countdownSeconds.value = 0
    clearCountdownTimer()
    redirectToPayResult('success')
    return
  }

  const paymentConfig = payload.config && typeof payload.config === 'object' ? payload.config : null
  if (!paymentConfig) {
    redirectToPayResult('failed', 0, statusText || '支付参数获取失败')
    return
  }

  const paymentInvokeResult = await invokePayment({
    payType: normalizedPayType,
    config: paymentConfig,
    scene: scene.value,
    returnUrl: buildPaymentReturnUrl()
  })

  if (paymentInvokeResult && paymentInvokeResult.redirected) {
    return
  }

  redirectToPayResult('success')
}

/**
 * 跳转到订单详情。
 *
 * @param {number} delay 跳转延迟。
 * @returns {void}
 */
function redirectToOrderDetail(delay = 0) {
  const query = orderInfo.value.orderSn
    ? `order_sn=${encodeURIComponent(orderInfo.value.orderSn)}`
    : `id=${orderId.value}`
  const url = `/pages/orders/order/detail/index?${query}`

  setTimeout(() => {
    uni.redirectTo({
      url,
      fail: () => {
        uni.navigateTo({ url })
      }
    })
  }, delay)
}

/**
 * 跳转到支付结果页。
 *
 * @param {string} clientResult 客户端支付结果：success/failed。
 * @param {number} delay 跳转延迟。
 * @param {string} message 支付失败提示。
 * @returns {void}
 */
function redirectToPayResult(clientResult = 'failed', delay = 0, message = '') {
  const query = buildPayResultQuery(clientResult, message)
  const url = `/pages/orders/pay/result?${query}`

  setTimeout(() => {
    uni.redirectTo({
      url,
      fail: () => {
        uni.navigateTo({ url })
      }
    })
  }, delay)
}

/**
 * 构建支付结果页查询参数。
 *
 * @param {string} clientResult 客户端支付结果。
 * @param {string} message 支付失败提示。
 * @returns {string}
 */
function buildPayResultQuery(clientResult = 'failed', message = '') {
  const query = []
  if (orderInfo.value.orderSn) {
    query.push(`order_sn=${encodeURIComponent(orderInfo.value.orderSn)}`)
  } else if (orderId.value) {
    query.push(`order_id=${orderId.value}`)
  }
  query.push(`client_result=${encodeURIComponent(clientResult)}`)
  if (message) {
    query.push(`message=${encodeURIComponent(message)}`)
  }

  return query.join('&')
}

/**
 * 解析当前微信交易类型。
 *
 * @returns {string}
 */
function resolveTradeType() {
  if (scene.value === PLATFORM_SCENES.MINIAPP) {
    return 'miniapp'
  }

  if (scene.value === PLATFORM_SCENES.H5) {
    return 'h5'
  }

  return 'official_account'
}

/**
 * 构建 H5 支付同步回跳地址。
 *
 * @returns {string}
 */
function buildPaymentReturnUrl() {
  // #ifdef H5
  if (typeof window === 'undefined' || !window.location) {
    return ''
  }

  const currentPath = String(window.location.pathname || '')
  const routePath = '/pages/orders/pay/index'
  const routeIndex = currentPath.indexOf(routePath)
  const appBase = routeIndex >= 0 ? currentPath.slice(0, routeIndex) : ''
  const resultPath = `${appBase}/pages/orders/pay/result`
  const query = buildPayResultQuery('success')

  return `${window.location.origin}${resultPath}?${query}`
  // #endif

  // #ifndef H5
  return ''
  // #endif
}

/**
 * 统一支付方式标识。
 *
 * @param {string} payType 支付方式。
 * @returns {string}
 */
function normalizePayType(payType = '') {
  const normalized = String(payType || '').trim().toLowerCase()
  if (!normalized) {
    return ''
  }

  if (['weixin', 'wxpay', 'wechat'].includes(normalized)) {
    return 'wechat'
  }
  if (['zhifubao', 'alipay'].includes(normalized)) {
    return 'alipay'
  }
  if (['balance', 'yue'].includes(normalized)) {
    return 'balance'
  }

  return normalized
}

/**
 * 解析金额。
 *
 * @param {number|string} value 原始值。
 * @param {number} fallback 兜底值。
 * @returns {number}
 */
function parseAmount(value, fallback = 0) {
  const normalized = String(value === undefined || value === null ? '' : value).replace(/,/g, '').replace('¥', '').trim()
  const parsed = Number(normalized)
  return Number.isFinite(parsed) ? Math.max(parsed, 0) : fallback
}

/**
 * 格式化金额。
 *
 * @param {number|string} value 金额。
 * @returns {string}
 */
function formatAmount(value) {
  const amount = Number(value || 0)
  return Number.isFinite(amount) ? amount.toFixed(2) : '0.00'
}

/**
 * 补零。
 *
 * @param {number} value 数值。
 * @returns {string}
 */
function padNumber(value) {
  return value < 10 ? `0${value}` : String(value)
}

/**
 * 提取支付错误文案。
 *
 * @param {Object} error 错误对象。
 * @param {string} fallback 默认文案。
 * @returns {string}
 */
function resolvePaymentErrorMessage(error = {}, fallback = '支付未完成') {
  const errMsg = String((error && (error.errMsg || error.err_msg || error.message)) || '')
  if (errMsg.indexOf('cancel') !== -1) {
    return '您已取消支付'
  }
  return normalizePublicErrorMessage(errMsg, fallback)
}

/**
 * 提取异常消息。
 *
 * @param {*} error 异常对象。
 * @param {string} fallback 兜底文案。
 * @returns {string}
 */
function extractErrorMessage(error, fallback = '操作失败') {
  if (!error) {
    return fallback
  }

  if (typeof error === 'string') {
    return error || fallback
  }

  return normalizePublicErrorMessage(error.msg || error.message || error.errMsg, fallback)
}

/**
 * 展示轻提示。
 *
 * @param {string} title 文案。
 * @returns {void}
 */
function showToast(title) {
  uni.showToast({
    title: normalizePublicErrorMessage(title, '操作失败'),
    icon: 'none'
  })
}
</script>


<style lang="scss">
page {
  background: #f5f5f5;
}
</style>

<style lang="scss" scoped>
$pay-radius-card: 24rpx;
$pay-radius-panel: 28rpx;
$pay-radius-badge: 16rpx;
$pay-radius-pill: 999rpx;

.order-pay-page {
  min-height: 100%;
  display: flex;
  flex-direction: column;
  background: $surface-page;
}

.order-pay-page__content {
  padding: 18rpx 32rpx 224rpx;
  box-sizing: border-box;
}

/* #ifdef H5 */
.order-pay-page__content {
  padding-bottom: calc(224rpx + var(--window-bottom, 0px));
}
/* #endif */

/* #ifdef MP-WEIXIN */
.order-pay-page__content {
  padding-bottom: calc(224rpx + env(safe-area-inset-bottom));
}
/* #endif */

.page-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 520rpx;
}

.pay-summary-card,
.order-pay-page ::v-deep .pay-methods-card {
  background: $surface-card;
  border-radius: $pay-radius-panel;
  box-shadow: 0 8rpx 24rpx rgba(15, 23, 42, 0.04);
}

.pay-summary-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 54rpx 36rpx 40rpx;

  &__label {
    font-size: 24rpx;
    line-height: 34rpx;
    font-weight: 400;
    color: $text-secondary;
  }

  &__amount-row {
    display: flex;
    align-items: baseline;
    margin-top: 18rpx;
  }

  &__currency {
    margin-right: 8rpx;
    font-size: 40rpx;
    line-height: 1;
    font-weight: 700;
    color: $brand-primary;
  }

  &__amount {
    font-size: 48rpx;
    line-height: 1;
    font-weight: 900;
    color: $brand-primary;
  }

  &__divider {
    width: 100%;
    height: 1rpx;
    margin-top: 42rpx;
    background: $border-light;
  }

  &__meta-row {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: 42rpx;
  }

  &__meta-label {
    font-size: 24rpx;
    line-height: 34rpx;
    font-weight: 400;
    color: $text-secondary;
  }

  &__meta-value {
    margin-left: 22rpx;
    font-size: 28rpx;
    line-height: 40rpx;
    font-weight: 700;
    color: $text-title;
  }

  &__countdown {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 64rpx;
    margin-top: 34rpx;
    padding: 0 26rpx;
    border-radius: $pay-radius-pill;
    background: $brand-primary-pale;
  }

  &__countdown--success {
    background: #edfdf2;
  }

  &__countdown--expired {
    background: #fff1f2;
  }

  &__countdown-text {
    margin-left: 12rpx;
    font-size: 24rpx;
    line-height: 34rpx;
    font-weight: 700;
    color: $brand-primary;
  }

  &__countdown-text--success {
    color: #15803d;
  }

  &__countdown-text--expired {
    color: #b91c1c;
  }
}

.pay-footer {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 20;
  display: flex;
  align-items: center;
  padding: 22rpx 32rpx;
  background: rgba(255, 255, 255, 0.96);
  border-top: 1rpx solid rgba(226, 226, 226, 0.9);
  box-shadow: 0 -8rpx 24rpx rgba(0, 0, 0, 0.04);
}

/* #ifdef H5 */
.pay-footer {
  bottom: var(--window-bottom, 0px);
  padding-bottom: calc(22rpx + var(--window-bottom, 0px));
}
/* #endif */

/* #ifdef MP-WEIXIN */
.pay-footer {
  padding-bottom: calc(22rpx + env(safe-area-inset-bottom));
}
/* #endif */

.pay-footer__summary {
  flex: 1;
  min-width: 0;
}

.pay-footer__label {
  display: block;
  font-size: 24rpx;
  line-height: 34rpx;
  font-weight: 400;
  color: $text-secondary;
}

.pay-footer__amount-row {
  display: flex;
  align-items: baseline;
  margin-top: 10rpx;
}

.pay-footer__currency {
  margin-right: 6rpx;
  font-size: 28rpx;
  line-height: 1;
  font-weight: 700;
  color: $brand-primary;
}

.pay-footer__amount {
  font-size: 48rpx;
  line-height: 1;
  font-weight: 900;
  color: $brand-primary;
}

.pay-footer__button {
  width: 286rpx;
  height: 96rpx;
  margin: 0;
  border: 0;
  border-radius: $pay-radius-pill;
  background: $brand-primary;
  box-shadow: 0 12rpx 24rpx $brand-shadow;
  font-size: 34rpx;
  line-height: 96rpx;
  font-weight: 700;
  color: $text-inverse;
}

.pay-footer__button::after {
  border: 0;
}

.pay-footer__button:disabled {
  opacity: 0.55;
  box-shadow: none;
}
</style>
