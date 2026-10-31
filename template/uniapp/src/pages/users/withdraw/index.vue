<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="withdraw-page">
    <image class="withdraw-page__hero-bg" src="../static/withdraw-commission-top-bg.jpg" mode="aspectFill"></image>
    <view class="withdraw-page__hero-mask"></view>
    <view class="withdraw-page__safe-top">
      <app-safe-top title="佣金提现" background="transparent" title-color="#4b2d1c" icon-color="#4b2d1c" />
    </view>

    <view v-if="pageLoading" class="withdraw-page__loading">
      <six-petal-spiral />
    </view>

    <view v-else-if="pageError" class="withdraw-page__error">
      <text class="withdraw-page__error-text">{{ pageError }}</text>
      <view class="withdraw-page__error-btn" @click="fetchPageData">重新加载</view>
    </view>

    <view v-else class="withdraw-page__content">
      <view class="hero-card">
        <view class="hero-card__body">
          <text class="hero-card__label">{{ summaryLabel }}</text>
          <view class="hero-card__amount-row">
            <text class="hero-card__currency">¥</text>
            <text class="hero-card__amount">{{ displaySummary.available }}</text>
          </view>
          <view class="hero-card__divider"></view>
          <view class="hero-card__stats">
            <view class="hero-stat hero-stat--left">
              <text class="hero-stat__label">总资产</text>
              <text class="hero-stat__value">¥{{ displaySummary.total }}</text>
            </view>
            <view class="hero-stat hero-stat--right">
              <text class="hero-stat__label">待结算</text>
              <text class="hero-stat__value">¥{{ displaySummary.pending }}</text>
            </view>
          </view>
        </view>
      </view>

      <view class="bank-card" @click="handleChooseBank">
        <view class="bank-card__main">
          <view class="bank-card__icon">
            <uni-icons type="shop-filled" size="26" color="#72727b"></uni-icons>
          </view>
          <view v-if="hasBankCard" class="bank-card__info">
            <text class="bank-card__name">{{ bankCard.bankName }}</text>
            <text class="bank-card__desc">储蓄卡 (尾号 {{ bankCard.tailNumber }})</text>
          </view>
          <view v-else class="bank-card__info">
            <text class="bank-card__name">添加银行卡</text>
            <text class="bank-card__desc">用于提现到账</text>
          </view>
        </view>
        <text class="bank-card__arrow">›</text>
      </view>

      <view class="amount-card">
        <text class="amount-card__title">提现金额</text>
        <view class="amount-input-row">
          <text class="amount-input-row__currency">¥</text>
          <input
            class="amount-input-row__input"
            type="digit"
            :value="form.amountInput"
            placeholder="输入金额"
            placeholder-class="amount-input-row__placeholder"
            @input="handleAmountInput"
          />
          <text class="amount-input-row__action" @click.stop="handleWithdrawAll">全部提现</text>
        </view>

        <view class="amount-detail amount-detail--first">
          <text class="amount-detail__label">服务费 ({{ (config.fee_rate * 100).toFixed(1) }}%)</text>
          <text class="amount-detail__value">¥ {{ displayServiceFee }}</text>
        </view>
        <view class="amount-detail">
          <text class="amount-detail__label">预计到账时间</text>
          <text class="amount-detail__value">{{ config.arrival_time }}</text>
        </view>
      </view>

      <view class="quick-amounts">
        <view
          v-for="item in quickOptionItems"
          :key="item.value"
          :class="['quick-amounts__item', { 'quick-amounts__item--active': item.active }]"
          @click="handleSelectQuickAmount(item.value)"
        >
          <text :class="['quick-amounts__text', { 'quick-amounts__text--active': item.active }]">{{ item.label }}</text>
        </view>
      </view>

      <view class="notice-section">
        <text class="notice-section__title">提现须知</text>
        <view v-for="(item, index) in config.notes" :key="index" class="notice-item">
          <view class="notice-item__dot"></view>
          <text class="notice-item__text">{{ item }}</text>
        </view>
      </view>
    </view>

    <view v-if="!pageLoading && !pageError" class="footer-bar">
      <view class="footer-bar__summary">
        <text class="footer-bar__summary-label">实际到账金额</text>
        <text class="footer-bar__summary-value">¥ {{ displayActualAmount }}</text>
      </view>
      <view :class="['footer-bar__button', { 'footer-bar__button--disabled': submitButtonDisabled }]" @click="handleSubmit">
        <text class="footer-bar__button-text">{{ submitButtonText }}</text>
      </view>
    </view>

    <app-floating-button />
  </view>
</template>

<script setup>
import { getWithdrawConfig, submitWithdraw as submitWithdrawRequest } from '@/api/withdraw'
import { getBankCard } from '@/api/user'
import { isLoggedIn, navigateToLogin } from '@/utils/auth'
import { getCachedMiniappSubscribeMessages, requestMiniappSubscribeMessagesByEvents } from '@/utils/miniapp_subscribe'
import { computed, ref } from 'vue'
import { onLoad, onPullDownRefresh, onShow, onUnload } from '@dcloudio/uni-app'
import { themeState } from '@/utils/theme'

defineOptions({ name: 'UserWithdrawPage' })

const source = 'commission'
const initialized = ref(false)
const pageLoading = ref(false)
const pageError = ref('')
const submitLoading = ref(false)
const submitTimer = ref(null)
const config = ref({
  enabled: true,
  fee_rate: 0.001,
  min_fee: 0.1,
  min_amount: 0,
  max_amount: 0,
  arrival_time: '',
  notes: []
})
const summary = ref({
  available: 0,
  total: 0,
  pending: 0
})
const bankCard = ref({
  hasCard: false,
  bankName: '',
  holderName: '',
  cardNo: '',
  tailNumber: ''
})
const form = ref({
  amountInput: ''
})
const quickAmounts = ref([])

/**
 * 当前页面标题。
 *
 * @returns {string} 顶部导航展示标题。
 */
const pageTitle = computed(() => {
  return '佣金提现'
})
/**
 * 顶部金额说明文案。
 *
 * @returns {string} 当前来源对应的摘要标签。
 */
const summaryLabel = computed(() => {
  return '可提现佣金 (元)'
})
/**
 * 当前输入金额的数值形式。
 *
 * @returns {number} 金额为空或异常时返回 0。
 */
const amountValue = computed(() => {
  return parseAmount(form.value.amountInput, 0)
})
/**
 * 顶部摘要展示文本。
 *
 * @returns {{available: string, total: string, pending: string}} 格式化后的摘要数据。
 */
const displaySummary = computed(() => {
  return {
    available: formatCurrency(summary.value.available),
    total: formatCurrency(summary.value.total),
    pending: formatCurrency(summary.value.pending)
  }
})
/**
 * 当前输入金额对应的手续费。
 *
 * @returns {number} 手续费金额。
 */
const serviceFee = computed(() => {
  if (amountValue.value <= 0) {
    return 0
  }

  return Math.max(roundCurrency(amountValue.value * config.value.fee_rate), config.value.min_fee)
})
/**
 * 当前提现后预计到账金额。
 *
 * @returns {number} 到账金额，最小为 0。
 */
const actualAmount = computed(() => {
  if (amountValue.value <= 0) {
    return 0
  }

  return Math.max(roundCurrency(amountValue.value - serviceFee.value), 0)
})
/**
 * 佣金提现是否已关闭。
 *
 * @returns {boolean} 关闭时返回 true。
 */
const isWithdrawClosed = computed(() => {
  return !config.value.enabled
})
/**
 * 提交按钮是否处于不可用态。
 *
 * @returns {boolean} 不可用时返回 true。
 */
const submitButtonDisabled = computed(() => {
  return submitLoading.value || isWithdrawClosed.value
})
/**
 * 提交按钮文案。
 *
 * @returns {string} 当前状态对应的按钮文案。
 */
const submitButtonText = computed(() => {
  if (submitLoading.value) {
    return '提交中...'
  }

  return isWithdrawClosed.value ? '暂未开放' : '确认提现'
})
/**
 * 手续费展示文案。
 *
 * @returns {string} 保留两位小数的手续费文本。
 */
const displayServiceFee = computed(() => {
  return formatCurrency(serviceFee.value)
})
/**
 * 实际到账金额展示文案。
 *
 * @returns {string} 保留两位小数的到账文本。
 */
const displayActualAmount = computed(() => {
  return formatCurrency(actualAmount.value)
})
/**
 * 快捷金额按钮配置。
 *
 * @returns {Array<Object>} 带激活态的快捷金额数组。
 */
const quickOptionItems = computed(() => {
  const currentAmount = roundCurrency(amountValue.value)

  return quickAmounts.value.map((item) => ({
    value: item,
    label: `¥${item}`,
    active: roundCurrency(item) === currentAmount
  }))
})
/**
 * 是否已绑定提现银行卡。
 *
 * @returns {boolean} 已绑定返回 true。
 */
const hasBankCard = computed(() => {
  return Boolean(bankCard.value.hasCard && bankCard.value.bankName && bankCard.value.tailNumber)
})

onLoad((options = {}) => {
  applyRouteOptions(options)
  fetchPageData()
  prefetchSubscribeMessages()
})

onShow(() => {
  if (initialized.value && isLoggedIn()) {
    fetchBankCard()
  }
})

onPullDownRefresh(() => {
  fetchPageData()
})

onUnload(() => {
  clearSubmitTimer()
})

/**
 * 根据路由参数初始化页面来源与默认金额。
 *
 * @param {Object} options 路由参数。
 * @returns {void}
 */
function applyRouteOptions(options = {}) {
  const routeAmount = parseAmount(options.amount, 0)
  if (routeAmount > 0) {
    form.value.amountInput = formatInputAmount(routeAmount)
  }
}
/**
 * 获取提现页配置及摘要数据。
 *
 * @returns {Promise<void>}
 */
async function fetchPageData() {
  if (!isLoggedIn()) {
    pageError.value = '请先登录'
    pageLoading.value = false
    return
  }

  pageLoading.value = true
  pageError.value = ''
  try {
    const [res, bankRes] = await Promise.all([
      getWithdrawConfig({ source: 'commission' }),
      getBankCard({}, { showError: false, loginRedirect: false })
    ])
    if (res.code === 200 && res.data) {
      applyPageData(res.data)
      if (bankRes && bankRes.code === 200 && bankRes.data) {
        applyBankCard(bankRes.data)
      }
    } else {
      pageError.value = res.msg || '加载失败'
    }
  } catch (error) {
    pageError.value = '加载失败，请下拉刷新重试'
  } finally {
    pageLoading.value = false
    initialized.value = true
    uni.stopPullDownRefresh()
  }
}
/**
 * 刷新当前用户提现银行卡。
 *
 * @returns {Promise<void>}
 */
async function fetchBankCard() {
  try {
    const res = await getBankCard({}, { showError: false, loginRedirect: false })
    if (res && res.code === 200 && res.data) {
      applyBankCard(res.data)
    }
  } catch (error) {
    // 银行卡信息刷新失败不影响提现页其它数据展示。
  }
}
/**
 * 预取小程序订阅消息配置，后续确认提现直接读前端缓存。
 *
 * @returns {void}
 */
function prefetchSubscribeMessages() {
  getCachedMiniappSubscribeMessages({
    showError: false,
    loginRedirect: false
  }).catch(() => {})
}
/**
 * 将银行卡接口数据映射到页面状态。
 *
 * @param {Object} data 银行卡接口数据。
 * @returns {void}
 */
function applyBankCard(data = {}) {
  bankCard.value = {
    hasCard: Boolean(data.has_card || data.hasCard),
    bankName: String(data.bank_name || data.bankName || ''),
    holderName: String(data.holder_name || data.holderName || ''),
    cardNo: String(data.card_no || data.cardNo || ''),
    tailNumber: String(data.tail_number || data.tailNumber || '')
  }
}
/**
 * 将 API 返回数据映射到页面状态。
 *
 * @param {Object} data API 返回数据。
 * @returns {void}
 */
function applyPageData(data = {}) {
  if (data.config) {
    const configData = data.config
    config.value = {
      enabled: parseBoolean(configData.enabled, true),
      fee_rate: configData.fee_rate === undefined || configData.fee_rate === null ? 0.001 : configData.fee_rate,
      min_fee: configData.min_fee === undefined || configData.min_fee === null ? 0.1 : configData.min_fee,
      min_amount: configData.min_amount === undefined || configData.min_amount === null ? 0 : configData.min_amount,
      max_amount: configData.max_amount === undefined || configData.max_amount === null ? 0 : configData.max_amount,
      arrival_time: configData.arrival_time || '',
      notes: Array.isArray(configData.notes) ? configData.notes : []
    }
  }
  if (data.summary) {
    summary.value = {
      available: parseAmount(data.summary.available, 0),
      total: parseAmount(data.summary.total, 0),
      pending: parseAmount(data.summary.pending, 0)
    }
  }
  if (Array.isArray(data.quickAmounts) && data.quickAmounts.length) {
    quickAmounts.value = data.quickAmounts.map(v => Number(v) || 0).filter(v => v > 0)
  } else {
    quickAmounts.value = [100, 500, 1000, 5000]
  }
}
/**
 * 打开银行卡选择入口。
 *
 * @returns {void}
 */
function handleChooseBank() {
  uni.navigateTo({
    url: '/pages/users/card/index'
  })
}
/**
 * 处理金额输入，限制为两位小数。
 *
 * @param {Object} event 输入事件对象。
 * @returns {void}
 */
function handleAmountInput(event = {}) {
  const value = event.detail && event.detail.value ? event.detail.value : ''
  form.value.amountInput = sanitizeAmountInput(value)
}
/**
 * 选择快捷金额。
 *
 * @param {number} amount 快捷金额。
 * @returns {void}
 */
function handleSelectQuickAmount(amount) {
  form.value.amountInput = formatInputAmount(amount)
}
/**
 * 一键填充全部可提金额。
 *
 * @returns {void}
 */
function handleWithdrawAll() {
  if (isWithdrawClosed.value) {
    showToast('佣金提现暂未开放')
    return
  }

  if (summary.value.available <= 0) {
    showToast('暂无可提现金额')
    return
  }

  const availableAmount = Math.min(summary.value.available, config.value.max_amount)
  form.value.amountInput = formatInputAmount(availableAmount)
}
/**
 * 处理确认提现按钮点击。
 *
 * @returns {void}
 */
function handleSubmit() {
  if (submitLoading.value) {
    return
  }

  if (!validateSubmit()) {
    return
  }

  if (!isLoggedIn()) {
    navigateToLogin()
    return
  }

  uni.showModal({
    title: '确认提现',
    content: `确认将 ¥${formatCurrency(amountValue.value)} 提现到${bankCard.value.bankName}储蓄卡(尾号${bankCard.value.tailNumber})吗？`,
    confirmColor: themeState.primary_color,
    success: ({ confirm }) => {
      if (!confirm) {
        return
      }

      submitWithdraw()
    }
  })
}
/**
 * 执行提现提交。
 *
 * @returns {Promise<void>}
 */
async function submitWithdraw() {
  submitLoading.value = true
  clearSubmitTimer()
  try {
    await requestMiniappSubscribeMessagesByEvents(['withdraw_approved', 'withdraw_failed'])
    const res = await submitWithdrawRequest({
      source: 'commission',
      amount: formatInputAmount(amountValue.value)
    })

    if (!res || res.code !== 200) {
      showToast((res && res.msg) || '提现申请失败')
      return
    }

    form.value.amountInput = ''
    showToast('提现申请已提交')
    fetchPageData()
  } catch (error) {
    showToast('提现申请失败，请稍后重试')
  } finally {
    submitLoading.value = false
  }
}
/**
 * 校验提现表单是否合法。
 *
 * @returns {boolean} 校验通过返回 true。
 */
function validateSubmit() {
  if (isWithdrawClosed.value) {
    showToast('佣金提现暂未开放')
    return false
  }

  if (!form.value.amountInput) {
    showToast('请输入提现金额')
    return false
  }

  if (amountValue.value < config.value.min_amount) {
    showToast(`单笔最低提现 ${formatCurrency(config.value.min_amount)} 元`)
    return false
  }

  if (amountValue.value > config.value.max_amount) {
    showToast(`单笔最高提现 ${formatCurrency(config.value.max_amount)} 元`)
    return false
  }

  if (amountValue.value > summary.value.available) {
    showToast('提现金额不能超过可提现金额')
    return false
  }

  if (actualAmount.value <= 0) {
    showToast('到账金额需大于 0 元')
    return false
  }

  if (!hasBankCard.value) {
    showToast('请先添加提现银行卡')
    return false
  }

  return true
}
/**
 * 清理金额输入，只保留两位小数。
 *
 * @param {string|number} value 原始输入值。
 * @returns {string} 处理后的输入字符串。
 */
function sanitizeAmountInput(value = '') {
  const source = String(value || '').replace(/[^\d.]/g, '')
  if (!source) {
    return ''
  }

  const normalized = source.startsWith('.') ? `0${source}` : source
  const segments = normalized.split('.')
  const integerPart = (segments[0] || '0').replace(/^0+(?=\d)/, '') || '0'

  if (segments.length === 1) {
    return integerPart
  }

  const decimalPart = segments.slice(1).join('').slice(0, 2)
  return `${integerPart}.${decimalPart}`
}
/**
 * 将金额格式化为输入框可展示的两位小数字符串。
 *
 * @param {number|string} value 原始金额。
 * @returns {string} 两位小数字符串。
 */
function formatInputAmount(value) {
  return roundCurrency(parseAmount(value, 0)).toFixed(2)
}
/**
 * 格式化金额展示。
 *
 * @param {number|string} value 原始金额。
 * @param {string} fallback 兜底展示文案。
 * @returns {string} 本地化后的金额文本。
 */
function formatCurrency(value, fallback = '0.00') {
  const amount = parseAmount(value, Number.NaN)
  if (!Number.isFinite(amount)) {
    return fallback
  }

  return amount.toLocaleString('zh-CN', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
}
/**
 * 将原始金额转换为数字。
 *
 * @param {number|string|null} value 原始金额。
 * @param {number} fallback 转换失败时的兜底值。
 * @returns {number} 标准化后的金额数字。
 */
function parseAmount(value, fallback = 0) {
  const normalized = String(value === undefined || value === null ? '' : value)
    .replace(/,/g, '')
    .replace(/[¥￥]/g, '')
    .trim()
  const amount = Number(normalized)

  return Number.isFinite(amount) ? amount : fallback
}
/**
 * 将接口布尔值转换为标准 boolean。
 *
 * @param {boolean|number|string|null} value 原始值。
 * @param {boolean} fallback 为空时的默认值。
 * @returns {boolean} 标准布尔值。
 */
function parseBoolean(value, fallback = false) {
  if (value === undefined || value === null || value === '') {
    return fallback
  }

  if (typeof value === 'boolean') {
    return value
  }

  return !['0', 'false', 'off'].includes(String(value).toLowerCase())
}
/**
 * 按货币精度进行四舍五入。
 *
 * @param {number} value 原始数值。
 * @returns {number} 保留两位小数后的数值。
 */
function roundCurrency(value) {
  return Math.round(Number(value || 0) * 100) / 100
}
/**
 * 清理提交中的定时器。
 *
 * @returns {void}
 */
function clearSubmitTimer() {
  if (!submitTimer.value) {
    return
  }

  clearTimeout(submitTimer.value)
  submitTimer.value = null
}
/**
 * 统一展示提示文案。
 *
 * @param {string} title 提示内容。
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
  background: #f3f3f3;
}
</style>

<style lang="scss" scoped>
.withdraw-page {
  position: relative;
  overflow: hidden;
  min-height: 100vh;
  background: #f3f3f3;
  color: var(--text-title, #1a1c1c);

  &__hero-bg {
    position: absolute;
    top: 0;
    left: 0;
    z-index: 0;
    width: 100%;
    height: 690rpx;
  }

  &__hero-mask {
    position: absolute;
    left: 0;
    right: 0;
    top: 430rpx;
    z-index: 1;
    height: 300rpx;
    background: linear-gradient(180deg, rgba(243, 243, 243, 0) 0%, #f3f3f3 82%);
  }

  &__safe-top {
    position: relative;
    z-index: 2;
  }
}

.withdraw-page__loading {
  position: relative;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: center;
  padding-top: 200rpx;
}

.withdraw-page__error {
  position: relative;
  z-index: 2;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding-top: 200rpx;

  &-text {
    font-size: 28rpx;
    color: #8b8b95;
    margin-bottom: 32rpx;
  }

  &-btn {
    min-width: 220rpx;
    height: 80rpx;
    padding: 0 32rpx;
    border-radius: 999rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--brand-primary, #ea580c);
    color: #ffffff;
    font-size: 28rpx;
    font-weight: 700;
  }
}

.withdraw-page__content {
  position: relative;
  z-index: 2;
  padding: 24rpx 20rpx 252rpx;
  box-sizing: border-box;
}

.hero-card {
  position: relative;
  min-height: 372rpx;
  margin-bottom: 20rpx;
  padding: 48rpx 32rpx 42rpx;
  box-sizing: border-box;

  &__body {
    position: relative;
    z-index: 2;
  }

  &__label {
    display: block;
    font-size: 24rpx;
    line-height: 1.3;
    color: rgba(83, 49, 28, 0.76);
  }

  &__amount-row {
    display: flex;
    align-items: flex-end;
    margin-top: 14rpx;
  }

  &__currency {
    margin-right: 10rpx;
    margin-bottom: 8rpx;
    font-size: 46rpx;
    line-height: 1;
    font-weight: 700;
    color: #4b2d1c;
  }

  &__amount {
    font-size: 76rpx;
    line-height: 0.95;
    font-weight: 800;
    letter-spacing: -3rpx;
    color: #4b2d1c;
  }

  &__divider {
    height: 1rpx;
    margin-top: 30rpx;
    background: rgba(83, 49, 28, 0.12);
  }

  &__stats {
    display: flex;
    margin-top: 24rpx;
  }
}

.hero-stat {
  flex: 1;

  &--left {
    padding-right: 32rpx;
    border-right: 1rpx solid rgba(83, 49, 28, 0.12);
  }

  &--right {
    padding-left: 32rpx;
  }

  &__label {
    display: block;
    font-size: 22rpx;
    line-height: 1.3;
    color: rgba(83, 49, 28, 0.58);
  }

  &__value {
    display: block;
    margin-top: 10rpx;
    font-size: 26rpx;
    line-height: 1.2;
    font-weight: 700;
    color: #5d3821;
  }
}

.bank-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16rpx;
  padding: 22rpx 28rpx 22rpx 24rpx;
  border-radius: 22rpx;
  background: #ffffff;
  box-shadow: 0 10rpx 20rpx rgba(15, 23, 42, 0.04);

  &__main {
    display: flex;
    align-items: center;
    flex: 1;
    min-width: 0;
  }

  &__icon {
    width: 76rpx;
    height: 76rpx;
    border-radius: 38rpx;
    background: #efeff3;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  &__icon-text {
    font-size: 28rpx;
    line-height: 1;
    font-weight: 700;
    color: #666670;
  }

  &__info {
    flex: 1;
    min-width: 0;
    margin-left: 20rpx;
  }

  &__name {
    display: block;
    font-size: 38rpx;
    line-height: 1.2;
    font-weight: 700;
    color: #1c1c1f;
  }

  &__desc {
    display: block;
    margin-top: 12rpx;
    font-size: 26rpx;
    line-height: 1.3;
    color: #8b8b95;
  }

  &__arrow {
    margin-left: 20rpx;
    font-size: 44rpx;
    line-height: 1;
    color: #c7c7cd;
  }
}

.amount-card {
  margin-bottom: 20rpx;
  padding: 34rpx 24rpx 28rpx;
  border-radius: 22rpx;
  background: #ffffff;
  box-shadow: 0 10rpx 20rpx rgba(15, 23, 42, 0.04);

  &__title {
    display: block;
    font-size: 38rpx;
    line-height: 1.2;
    font-weight: 700;
    color: #1c1c1f;
  }
}

.amount-input-row {
  display: flex;
  align-items: flex-end;
  padding: 20rpx 0 18rpx;
  border-bottom: 1rpx solid #dddddf;

  &__currency {
    margin-right: 12rpx;
    font-size: 56rpx;
    line-height: 1;
    font-weight: 700;
    color: #1c1c1f;
  }

  &__input {
    flex: 1;
    min-width: 0;
    height: 76rpx;
    font-size: 42rpx;
    line-height: 76rpx;
    font-weight: 700;
    color: #1c1c1f;
  }

  &__action {
    margin-left: 20rpx;
    padding-bottom: 8rpx;
    font-size: 34rpx;
    line-height: 1.2;
    font-weight: 700;
    color: var(--brand-primary, #ea580c);
  }
}

.amount-input-row__placeholder {
  color: #d3d3d8;
}

.amount-detail {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 18rpx;

  &--first {
    margin-top: 24rpx;
  }

  &__label {
    font-size: 28rpx;
    line-height: 1.3;
    color: #7f7f89;
  }

  &__value {
    font-size: 28rpx;
    line-height: 1.3;
    color: #26262c;
  }
}

.quick-amounts {
  display: flex;
  justify-content: space-between;
  margin-bottom: 28rpx;

  &__item {
    width: 164rpx;
    height: 78rpx;
    border-radius: 18rpx;
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;

    &--active {
      border-color: var(--brand-primary, #ea580c);
      background: rgba(243, 65, 0, 0.05);
    }
  }

  &__text {
    font-size: 28rpx;
    line-height: 1.2;
    font-weight: 500;
    color: #7f7f89;

    &--active {
      font-weight: 700;
      color: var(--brand-primary, #ea580c);
    }
  }
}

.notice-section {
  padding: 0 6rpx;

  &__title {
    display: block;
    margin-bottom: 18rpx;
    font-size: 24rpx;
    line-height: 1.3;
    font-weight: 700;
    color: #a1a1aa;
  }
}

.notice-item {
  display: flex;
  align-items: flex-start;

  & + & {
    margin-top: 14rpx;
  }

  &__dot {
    width: 10rpx;
    height: 10rpx;
    margin-top: 12rpx;
    border-radius: 10rpx;
    background: #c7c7cd;
    flex-shrink: 0;
  }

  &__text {
    flex: 1;
    min-width: 0;
    margin-left: 14rpx;
    font-size: 24rpx;
    line-height: 1.8;
    color: #8b8b95;
  }
}

.footer-bar {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 20;
  padding: 16rpx 20rpx 18rpx;
  border-top: 1rpx solid rgba(0, 0, 0, 0.05);
  background: rgba(255, 255, 255, 0.98);
  box-shadow: 0 -8rpx 24rpx rgba(15, 23, 42, 0.06);

  &__summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14rpx;
    padding: 0 4rpx;
  }

  &__summary-label {
    font-size: 26rpx;
    line-height: 1.3;
    color: #6b7280;
  }

  &__summary-value {
    font-size: 52rpx;
    line-height: 1;
    font-weight: 800;
    letter-spacing: -2rpx;
    color: #1c1c1f;
  }

  &__button {
    height: 88rpx;
    border-radius: 44rpx;
    background: var(--brand-primary, #ea580c);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 20rpx 30rpx rgba(243, 65, 0, 0.2);

    &--disabled {
      opacity: 0.75;
    }
  }

  &__button-text {
    font-size: 36rpx;
    line-height: 1.2;
    font-weight: 700;
    color: #ffffff;
  }
}

/* #ifdef H5 */
.withdraw-page__content {
  padding-bottom: calc(232rpx + var(--window-bottom, 0px));
}

.footer-bar {
  bottom: var(--window-bottom, 0px);
}
/* #endif */

/* #ifdef MP-WEIXIN */
.withdraw-page__content {
  padding-bottom: calc(232rpx + env(safe-area-inset-bottom));
}

.footer-bar {
  padding-bottom: calc(18rpx + env(safe-area-inset-bottom));
}
/* #endif */
</style>
