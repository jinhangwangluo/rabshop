<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="pay-result-page">
    <app-safe-top
      title="支付结果"
      background="#ffffff"
      :show-back="true"
      emit-back
      mp-extra="8rpx"
      @back="handleBack"
    >
    </app-safe-top>

    <view class="pay-result-page__content">
      <view v-if="loading" class="page-loading">
        <six-petal-spiral active text="正在确认支付结果" :color="brandPrimaryColor" />
      </view>

      <block v-else>
        <view class="result-hero">
          <view v-if="isSuccess" class="result-confetti result-confetti--left">
            <view class="result-confetti__piece result-confetti__piece--green"></view>
            <view class="result-confetti__piece result-confetti__piece--orange"></view>
          </view>
          <view v-if="isSuccess" class="result-confetti result-confetti--right">
            <view class="result-confetti__piece result-confetti__piece--green"></view>
            <view class="result-confetti__piece result-confetti__piece--orange"></view>
          </view>

          <view class="result-hero__icon" :class="{ 'result-hero__icon--failed': !isSuccess }">
            <uni-icons :type="statusIconType" size="58" color="#ffffff"></uni-icons>
          </view>
          <text class="result-hero__title">{{ resultInfo.title }}</text>
          <text class="result-hero__subtitle">{{ resultInfo.subtitle }}</text>
        </view>

        <view class="order-result-card">
          <view class="order-result-row order-result-row--amount">
            <view class="order-result-row__label-wrap">
              <view class="order-result-row__icon order-result-row__icon--amount">
                <text class="order-result-row__currency-icon">¥</text>
              </view>
              <text class="order-result-row__label">支付金额</text>
            </view>
            <view class="order-result-row__amount-wrap">
              <text class="order-result-row__amount-currency">¥</text>
              <text class="order-result-row__amount">{{ payableDisplay }}</text>
            </view>
          </view>

          <view class="order-result-row">
            <view class="order-result-row__label-wrap">
              <view class="order-result-row__icon">
                <uni-icons type="compose" size="20" color="#92959b"></uni-icons>
              </view>
              <text class="order-result-row__label">订单编号</text>
            </view>
            <view class="order-result-row__value-wrap">
              <text class="order-result-row__value order-result-row__value--sn">{{ resultInfo.orderSn || '--' }}</text>
              <view v-if="resultInfo.orderSn" class="order-result-row__copy" @click="handleCopyOrderSn">
                <text class="iconfont icon-copy"></text>
              </view>
            </view>
          </view>

          <view class="order-result-row">
            <view class="order-result-row__label-wrap">
              <view class="order-result-row__icon">
                <uni-icons type="wallet" size="20" color="#92959b"></uni-icons>
              </view>
              <text class="order-result-row__label">支付方式</text>
            </view>
            <text class="order-result-row__value">{{ resultInfo.payTypeText }}</text>
          </view>

          <view class="order-result-row order-result-row--last">
            <view class="order-result-row__label-wrap">
              <view class="order-result-row__icon">
                <uni-icons type="calendar" size="20" color="#92959b"></uni-icons>
              </view>
              <text class="order-result-row__label">下单时间</text>
            </view>
            <text class="order-result-row__value">{{ resultInfo.createTime || '--' }}</text>
          </view>
        </view>

        <view v-if="showGiftCoupons" class="gift-section">
          <view class="gift-section__header">
            <view class="gift-section__heading">
              <view class="gift-section__icon">
                <uni-icons type="gift-filled" size="24" color="#ffffff"></uni-icons>
              </view>
              <view class="gift-section__title-wrap">
                <text class="gift-section__title">恭喜获得优惠券</text>
                <text class="gift-section__subtitle">感谢您的下单，优惠券已发放到账</text>
              </view>
            </view>
            <text class="gift-section__badge">已到账</text>
          </view>

          <view class="gift-coupon-list">
            <view
              v-for="coupon in resultInfo.giftCoupons"
              :key="coupon.id"
              class="gift-coupon"
              @click="handleViewCoupon(coupon)"
            >
              <view class="gift-coupon__amount-area">
                <text class="gift-coupon__source">{{ coupon.sourceText }}</text>
                <view class="gift-coupon__amount-row">
                  <text class="gift-coupon__currency">¥</text>
                  <text class="gift-coupon__amount">{{ coupon.amountText }}</text>
                </view>
                <text class="gift-coupon__threshold">{{ coupon.thresholdText }}</text>
              </view>

              <view class="gift-coupon__detail">
                <view class="gift-coupon__name-row">
                  <text class="gift-coupon__name">{{ coupon.name }}</text>
                  <text class="gift-coupon__tag">{{ coupon.tagText }}</text>
                </view>
                <text class="gift-coupon__expire">{{ coupon.expireText }}</text>
              </view>

              <view class="gift-coupon__action">
                <text class="gift-coupon__action-text">立即查看</text>
                <view class="gift-coupon__arrow">
                  <uni-icons type="right" size="20" :color="brandPrimaryColor"></uni-icons>
                </view>
              </view>
            </view>
          </view>
        </view>

        <view v-if="showGiftCoupons" class="coupon-tip">
          <uni-icons type="info" size="16" color="#92959b"></uni-icons>
          <text class="coupon-tip__text">赠券以实际到账为准，可在“我的优惠券”中查看</text>
        </view>
      </block>
    </view>

    <view v-if="!loading" class="result-footer">
      <button class="result-footer__button result-footer__button--outline" @click="handleViewOrder">
        查看订单
      </button>
      <button class="result-footer__button result-footer__button--primary" @click="handlePrimaryAction">
        {{ primaryActionText }}
      </button>
    </view>
  </view>
</template>

<script setup>
import { computed, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getOrderPayResult } from '@/api/order'
import { normalizePublicErrorMessage } from '@/utils/error'
import { themeState } from '@/utils/theme'

const RESULT_SUCCESS = 'success'
const RESULT_FAILED = 'failed'

defineOptions({ name: 'OrderPayResultPage' })

const loading = ref(true)
const orderId = ref(0)
const orderSn = ref('')
const clientResult = ref(RESULT_FAILED)
const clientMessage = ref('')
const brandPrimaryColor = computed(() => themeState.primary_color)
const resultInfo = ref({
  resultStatus: RESULT_FAILED,
  title: '支付失败',
  subtitle: '支付未完成，请重新尝试',
  orderId: 0,
  orderSn: '',
  payAmount: 0,
  payTypeText: '未完成支付',
  createTime: '',
  retryable: true,
  giftCoupons: []
})

const isSuccess = computed(() => {
  return resultInfo.value.resultStatus === RESULT_SUCCESS
})
const statusIconType = computed(() => {
  return isSuccess.value ? 'checkmarkempty' : 'closeempty'
})
const payableDisplay = computed(() => {
  return formatAmount(resultInfo.value.payAmount)
})
const showGiftCoupons = computed(() => {
  return isSuccess.value && resultInfo.value.giftCoupons.length > 0
})
const primaryActionText = computed(() => {
  if (!isSuccess.value && resultInfo.value.retryable) {
    return '重新支付'
  }

  return '继续购物'
})

onLoad((options = {}) => {
  applyRouteOptions(options)
  loadPayResult()
})

/**
 * 解析支付结果页路由参数。
 *
 * @param {Object} options 页面参数。
 * @returns {void}
 */
function applyRouteOptions(options = {}) {
  orderSn.value = normalizeDisplayText(options.order_sn || options.orderSn || '', '')
  orderId.value = parseId(options.order_id || options.orderId || options.id)
  clientResult.value = normalizeDisplayText(options.client_result || RESULT_FAILED, RESULT_FAILED) === RESULT_SUCCESS
    ? RESULT_SUCCESS
    : RESULT_FAILED
  clientMessage.value = normalizeDisplayText(options.message || options.msg || '', '')
}

/**
 * 查询并确认真实支付结果。
 *
 * 客户端已完成渠道支付时短暂轮询，给异步回调留出同步订单状态的时间。
 *
 * @returns {Promise<void>}
 */
async function loadPayResult() {
  const attempts = clientResult.value === RESULT_SUCCESS ? 5 : 1
  let lastPayload = null

  try {
    for (let index = 0; index < attempts; index += 1) {
      const payload = await fetchPayResult()
      if (payload) {
        lastPayload = payload
        if (payload.is_paid || index === attempts - 1) {
          break
        }
      }

      if (index < attempts - 1) {
        await wait(700)
      }
    }

    if (lastPayload) {
      applyPayResult(lastPayload)
      return
    }

    applyFallbackResult()
  } catch (error) {
    applyFallbackResult(extractErrorMessage(error, '支付结果加载失败'))
  } finally {
    loading.value = false
  }
}

/**
 * 请求支付结果接口。
 *
 * @returns {Promise<Object|null>} 支付结果数据。
 */
async function fetchPayResult() {
  const params = orderSn.value ? { order_sn: orderSn.value } : { order_id: orderId.value }
  const res = await getOrderPayResult(params, {
    showError: false,
    loginRedirect: true
  })

  if (!res || res.code !== 200 || !res.data) {
    return null
  }

  return res.data
}

/**
 * 同步接口支付结果。
 *
 * @param {Object} payload 接口数据。
 * @returns {void}
 */
function applyPayResult(payload = {}) {
  const isPaid = Boolean(payload.is_paid) || String(payload.result_status || '') === RESULT_SUCCESS
  const parsedOrderSn = String(payload.order_sn || orderSn.value || '').trim()
  const failureText = normalizeDisplayText(
    clientMessage.value || payload.failure_reason || payload.subtitle || payload.status_text,
    '支付未完成，请重新尝试'
  )

  orderId.value = parseId(payload.order_id || orderId.value)
  orderSn.value = parsedOrderSn
  resultInfo.value = {
    resultStatus: isPaid ? RESULT_SUCCESS : RESULT_FAILED,
    title: normalizeDisplayText(payload.title, isPaid ? '支付成功' : '支付失败'),
    subtitle: isPaid
      ? normalizeDisplayText(payload.subtitle, '订单已提交，商家将尽快发货')
      : failureText,
    orderId: orderId.value,
    orderSn: parsedOrderSn,
    payAmount: parseAmount(payload.pay_amount),
    payTypeText: normalizeDisplayText(payload.pay_type_text, isPaid ? '支付完成' : '未完成支付'),
    createTime: String(payload.create_time || ''),
    retryable: Boolean(payload.retryable),
    giftCoupons: normalizeGiftCoupons(payload.gift_coupons)
  }
}

/**
 * 接口不可用时根据客户端结果展示兜底状态。
 *
 * @param {string} message 失败提示。
 * @returns {void}
 */
function applyFallbackResult(message = '') {
  const fallbackSubtitle = normalizeDisplayText(message || clientMessage.value, '')

  resultInfo.value = {
    resultStatus: RESULT_FAILED,
    title: '支付失败',
    subtitle: fallbackSubtitle || (clientResult.value === RESULT_SUCCESS
      ? '暂未确认支付成功，请在订单中查看最新状态'
      : '支付未完成，请重新尝试'),
    orderId: orderId.value,
    orderSn: orderSn.value,
    payAmount: 0,
    payTypeText: clientResult.value === RESULT_SUCCESS ? '支付结果待确认' : '未完成支付',
    createTime: '',
    retryable: clientResult.value !== RESULT_SUCCESS,
    giftCoupons: []
  }
}

/**
 * 规范化赠券展示字段。
 *
 * @param {Array<Object>} coupons 接口赠券列表。
 * @returns {Array<Object>} 页面赠券列表。
 */
function normalizeGiftCoupons(coupons) {
  if (!Array.isArray(coupons)) {
    return []
  }

  return coupons.map((coupon, index) => ({
    id: Number(coupon.id || coupon.coupon_id || index + 1),
    name: String(coupon.name || '满减券'),
    amountText: formatCouponAmount(coupon.discount_amount),
    thresholdText: String(coupon.threshold_text || '无门槛可用'),
    sourceText: String(coupon.receive_type_text || '下单赠送'),
    tagText: String(coupon.tag_text || '赠送券'),
    expireText: String(coupon.expire_text || '有效期以券面为准'),
    actionUrl: String(coupon.action_url || '/pages/users/usecoupon/index')
  }))
}

/**
 * 返回订单详情，避免回到已完成的收银台。
 *
 * @returns {void}
 */
function handleBack() {
  handleViewOrder()
}

/**
 * 点击右上角完成。
 *
 * @returns {void}
 */
function handleComplete() {
  handleContinueShopping()
}

/**
 * 查看当前订单。
 *
 * @returns {void}
 */
function handleViewOrder() {
  const query = orderSn.value
    ? `order_sn=${encodeURIComponent(orderSn.value)}`
    : `id=${orderId.value}`
  const url = `/pages/orders/order/detail/index?${query}`

  uni.redirectTo({
    url,
    fail: () => {
      uni.navigateTo({ url })
    }
  })
}

/**
 * 执行主操作。
 *
 * @returns {void}
 */
function handlePrimaryAction() {
  if (!isSuccess.value && resultInfo.value.retryable) {
    handleRetryPay()
    return
  }

  handleContinueShopping()
}

/**
 * 重新进入收银台支付。
 *
 * @returns {void}
 */
function handleRetryPay() {
  const query = orderSn.value
    ? `order_sn=${encodeURIComponent(orderSn.value)}`
    : `orderId=${orderId.value}`
  uni.redirectTo({ url: `/pages/orders/pay/index?${query}` })
}

/**
 * 返回首页继续购物。
 *
 * @returns {void}
 */
function handleContinueShopping() {
  uni.switchTab({
    url: '/pages/index/index',
    fail: () => {
      uni.reLaunch({ url: '/pages/index/index' })
    }
  })
}

/**
 * 查看到账优惠券。
 *
 * @param {Object} coupon 优惠券。
 * @returns {void}
 */
function handleViewCoupon(coupon = {}) {
  uni.navigateTo({
    url: coupon.actionUrl || '/pages/users/usecoupon/index'
  })
}

/**
 * 复制订单编号。
 *
 * @returns {void}
 */
function handleCopyOrderSn() {
  if (!resultInfo.value.orderSn) {
    return
  }

  uni.setClipboardData({
    data: resultInfo.value.orderSn,
    success: () => {
      uni.showToast({ title: '订单编号已复制', icon: 'none' })
    }
  })
}

/**
 * 解析金额。
 *
 * @param {number|string} value 原始金额。
 * @returns {number}
 */
function parseAmount(value) {
  const amount = Number(value || 0)
  return Number.isFinite(amount) ? Math.max(amount, 0) : 0
}

/**
 * 解析订单 ID。
 *
 * @param {number|string} value 原始 ID。
 * @returns {number}
 */
function parseId(value) {
  const id = Number(value || 0)
  return Number.isFinite(id) ? Math.max(0, id) : 0
}

/**
 * 安全解码路由或接口中的展示文案，兼容小程序端未自动解码的查询参数。
 *
 * @param {*} value 原始展示值。
 * @param {string} fallback 空值时的兜底文案。
 * @returns {string}
 */
function normalizeDisplayText(value = '', fallback = '') {
  const source = String(value === undefined || value === null ? '' : value).trim()
  if (!source) {
    return fallback
  }

  let decoded = source.replace(/\+/g, ' ')
  for (let index = 0; index < 2; index += 1) {
    if (!/%[0-9a-fA-F]{2}/.test(decoded)) {
      break
    }

    try {
      const next = decodeURIComponent(decoded)
      if (next === decoded) {
        break
      }
      decoded = next
    } catch (error) {
      break
    }
  }

  return decoded.trim() || fallback
}

/**
 * 格式化订单金额。
 *
 * @param {number|string} value 金额。
 * @returns {string}
 */
function formatAmount(value) {
  return parseAmount(value).toFixed(2)
}

/**
 * 格式化优惠券面额。
 *
 * @param {number|string} value 金额。
 * @returns {string}
 */
function formatCouponAmount(value) {
  return formatAmount(value).replace(/\.00$/, '').replace(/(\.\d)0$/, '$1')
}

/**
 * 提取异常消息。
 *
 * @param {*} error 异常对象。
 * @param {string} fallback 默认文案。
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
 * 等待指定时长。
 *
 * @param {number} ms 毫秒。
 * @returns {Promise<void>}
 */
function wait(ms = 300) {
  return new Promise((resolve) => {
    setTimeout(resolve, ms)
  })
}
</script>


<style lang="scss">
page {
  background: #ffffff;
}
</style>

<style lang="scss" scoped>
.pay-result-page {
  min-height: 100%;
  background: $surface-card;
}

.pay-result-page__content {
  padding: 18rpx 26rpx 224rpx;
  box-sizing: border-box;
}

.nav-finish {
  color: $text-primary;
  font-size: 28rpx;
  line-height: 40rpx;
  white-space: nowrap;
}

.page-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 720rpx;
}

.result-hero {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 38rpx 24rpx 46rpx;
}

.result-hero__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 120rpx;
  height: 120rpx;
  border-radius: 50%;
  background: $uni-color-success;
  box-shadow: 0 14rpx 30rpx rgba(22, 163, 74, 0.18);
}

.result-hero__icon--failed {
  background: $uni-color-error;
  box-shadow: 0 14rpx 30rpx rgba(220, 38, 38, 0.16);
}

.result-hero__title {
  margin-top: 28rpx;
  color: #111827;
  font-size: 48rpx;
  font-weight: 700;
  line-height: 66rpx;
}

.result-hero__subtitle {
  display: block;
  width: 100%;
  margin-top: 10rpx;
  color: $text-secondary;
  font-size: 26rpx;
  font-weight: 400;
  line-height: 38rpx;
  text-align: center;
  word-break: break-all;
}

.result-confetti {
  position: absolute;
  top: 82rpx;
  width: 76rpx;
  height: 64rpx;
}

.result-confetti--left {
  left: 150rpx;
}

.result-confetti--right {
  right: 150rpx;
  transform: scaleX(-1);
}

.result-confetti__piece {
  position: absolute;
  width: 12rpx;
  height: 28rpx;
  border-radius: 8rpx;
  transform: rotate(42deg);
}

.result-confetti__piece--green {
  top: 4rpx;
  left: 4rpx;
  background: #ccefd8;
}

.result-confetti__piece--orange {
  right: 4rpx;
  bottom: 4rpx;
  background: #ffd4b8;
  transform: rotate(-38deg);
}

.order-result-card {
  padding: 12rpx 32rpx;
  border-radius: 24rpx;
  background: $surface-card;
  box-shadow: 0 14rpx 36rpx rgba(15, 23, 42, 0.07);
}

.order-result-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 82rpx;
  border-bottom: 1rpx solid $border-light;
}

.order-result-row--amount {
  min-height: 94rpx;
}

.order-result-row--last {
  border-bottom: 0;
}

.order-result-row__label-wrap,
.order-result-row__value-wrap,
.order-result-row__amount-wrap {
  display: flex;
  align-items: center;
}

.order-result-row__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 44rpx;
  height: 44rpx;
  margin-right: 18rpx;
}

.order-result-row__icon--amount {
  border-radius: 50%;
  background: #ff8a16;
}

.order-result-row__currency-icon {
  color: #ffffff;
  font-size: 30rpx;
  font-weight: 700;
  line-height: 40rpx;
}

.order-result-row__label {
  color: $text-primary;
  font-size: 28rpx;
  line-height: 40rpx;
}

.order-result-row__value {
  max-width: 410rpx;
  overflow: hidden;
  color: $text-primary;
  font-size: 26rpx;
  line-height: 38rpx;
  text-align: right;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.order-result-row__value--sn {
  max-width: 340rpx;
}

.order-result-row__copy {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 50rpx;
  height: 50rpx;
  margin-left: 6rpx;
  color: #92959b;
  font-size: 32rpx;
}

.order-result-row__amount-wrap {
  align-items: baseline;
  color: $brand-primary;
}

.order-result-row__amount-currency {
  margin-right: 4rpx;
  font-size: 32rpx;
  font-weight: 700;
  line-height: 44rpx;
}

.order-result-row__amount {
  font-size: 44rpx;
  font-weight: 700;
  line-height: 58rpx;
}

.gift-section {
  margin-top: 28rpx;
  padding: 28rpx 24rpx 30rpx;
  border-radius: 24rpx;
  background: #fff8f2;
}

.gift-section__header,
.gift-section__heading,
.gift-section__title-wrap,
.gift-coupon__amount-area,
.gift-coupon__detail,
.gift-coupon__action {
  display: flex;
}

.gift-section__header {
  align-items: flex-start;
  justify-content: space-between;
}

.gift-section__heading {
  align-items: flex-start;
}

.gift-section__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 52rpx;
  height: 52rpx;
  margin-right: 16rpx;
  border-radius: 12rpx;
  background: $brand-primary;
}

.gift-section__title-wrap {
  flex-direction: column;
}

.gift-section__title {
  color: #18181b;
  font-size: 30rpx;
  font-weight: 700;
  line-height: 42rpx;
}

.gift-section__subtitle {
  margin-top: 4rpx;
  color: $text-secondary;
  font-size: 22rpx;
  line-height: 32rpx;
}

.gift-section__badge {
  flex-shrink: 0;
  margin-top: 4rpx;
  padding: 8rpx 14rpx;
  border-radius: 999rpx;
  background: #ffeadc;
  color: $brand-primary;
  font-size: 20rpx;
  line-height: 28rpx;
}

.gift-coupon-list {
  margin-top: 24rpx;
}

.gift-coupon {
  position: relative;
  display: flex;
  min-height: 188rpx;
  overflow: hidden;
  border: 2rpx solid $brand-primary-light;
  border-radius: 20rpx;
  background: $surface-card;
  box-shadow: 0 8rpx 20rpx var(--brand-primary-pale, #fff7ed);
}

.gift-coupon + .gift-coupon {
  margin-top: 18rpx;
}

.gift-coupon__amount-area {
  position: relative;
  flex: 0 0 190rpx;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  border-right: 1rpx dashed #ffd2b7;
}

.gift-coupon__source {
  position: absolute;
  top: 0;
  left: 0;
  padding: 6rpx 14rpx;
  border-radius: 16rpx 0 16rpx 0;
  background: $brand-primary;
  color: #ffffff;
  font-size: 18rpx;
  line-height: 26rpx;
}

.gift-coupon__amount-row {
  display: flex;
  align-items: baseline;
  color: $brand-primary;
}

.gift-coupon__currency {
  margin-right: 2rpx;
  font-size: 30rpx;
  font-weight: 700;
  line-height: 40rpx;
}

.gift-coupon__amount {
  font-size: 58rpx;
  font-weight: 700;
  line-height: 70rpx;
}

.gift-coupon__threshold {
  color: $brand-primary;
  font-size: 22rpx;
  line-height: 32rpx;
}

.gift-coupon__detail {
  min-width: 0;
  flex: 1;
  flex-direction: column;
  justify-content: center;
  padding: 24rpx 18rpx;
}

.gift-coupon__name-row {
  display: flex;
  align-items: center;
  min-width: 0;
}

.gift-coupon__name {
  max-width: 180rpx;
  overflow: hidden;
  color: $text-primary;
  font-size: 28rpx;
  font-weight: 700;
  line-height: 40rpx;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.gift-coupon__tag {
  flex-shrink: 0;
  margin-left: 10rpx;
  padding: 4rpx 10rpx;
  border-radius: 8rpx;
  background: #ffeadc;
  color: $brand-primary;
  font-size: 18rpx;
  line-height: 26rpx;
}

.gift-coupon__expire {
  margin-top: 14rpx;
  color: $text-secondary;
  font-size: 20rpx;
  line-height: 30rpx;
}

.gift-coupon__action {
  flex: 0 0 128rpx;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: #fff1e7;
}

.gift-coupon__action-text {
  color: $brand-primary;
  font-size: 24rpx;
  font-weight: 700;
  line-height: 34rpx;
}

.gift-coupon__arrow {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 50rpx;
  height: 50rpx;
  margin-top: 12rpx;
  border-radius: 50%;
  background: #ffffff;
}

.coupon-tip {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: 22rpx;
}

.coupon-tip__text {
  margin-left: 10rpx;
  color: #92959b;
  font-size: 20rpx;
  line-height: 30rpx;
}

.result-footer {
  position: fixed;
  right: 0;
  bottom: 0;
  left: 0;
  z-index: 20;
  display: flex;
  padding: 20rpx 32rpx 24rpx;
  background: rgba(255, 255, 255, 0.98);
  box-shadow: 0 -8rpx 24rpx rgba(0, 0, 0, 0.04);
}

.result-footer__button {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 88rpx;
  margin: 0;
  padding: 0 24rpx;
  border-radius: 999rpx;
  font-size: 28rpx;
  font-weight: 700;
  line-height: 40rpx;
}

.result-footer__button::after {
  border: 0;
}

.result-footer__button--outline {
  flex: 1;
  border: 2rpx solid $brand-primary;
  background: #ffffff;
  color: $brand-primary;
}

.result-footer__button--primary {
  flex: 1;
  margin-left: 18rpx;
  background: $brand-primary;
  color: #ffffff;
  box-shadow: 0 12rpx 24rpx $brand-shadow;
}

/* #ifdef H5 */
.pay-result-page__content {
  padding-bottom: calc(224rpx + var(--window-bottom, 0px));
}

.result-footer {
  bottom: var(--window-bottom, 0px);
}
/* #endif */

/* #ifdef MP-WEIXIN */
.pay-result-page__content {
  padding-bottom: calc(224rpx + env(safe-area-inset-bottom));
}

.result-footer {
  padding-bottom: calc(24rpx + env(safe-area-inset-bottom));
}
/* #endif */
</style>
