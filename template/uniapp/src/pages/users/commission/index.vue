<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="commission-page">
    <image class="commission-page__hero-bg" src="../static/commission-hero-gradient-bg.jpg" mode="aspectFill"></image>
    <image class="commission-page__wallet-illustration" src="../static/commission-wallet-illustration.png" mode="aspectFit"></image>
    <view class="commission-page__hero-mask"></view>
    <view class="commission-page__safe-top">
      <app-safe-top title="佣金管理" background="transparent" title-color="#111111" icon-color="#111111" />
    </view>

    <view class="commission-page__content">
      <view class="hero-card">
        <view class="hero-card__label-row">
          <text class="hero-card__label">可提现余额（元）</text>
          <uni-icons type="eye-filled" size="19" color="#5f626b"></uni-icons>
        </view>
        <view class="hero-card__amount-row">
          <text class="hero-card__currency">¥</text>
          <text class="hero-card__amount">{{ displaySummary.withdrawable }}</text>
        </view>
        <text class="hero-card__badge">{{ loading ? '同步中' : '实时更新' }}</text>

        <view :class="['hero-card__button', { 'hero-card__button--disabled': loading }]" @click="handleWithdraw">
          <uni-icons type="wallet-filled" size="16" color="#ffffff"></uni-icons>
          <text class="hero-card__button-text">{{ loading ? '同步中...' : '立即提现' }}</text>
        </view>

        <view class="hero-card__stats">
          <view class="hero-stat hero-stat--left">
            <view class="hero-stat__icon hero-stat__icon--total">
              <image class="hero-stat__icon-image" src="../static/commission-total-icon.png" mode="aspectFit"></image>
            </view>
            <view class="hero-stat__body">
              <text class="hero-stat__label">累计总佣金（元）</text>
              <text class="hero-stat__value">¥{{ displaySummary.total }}</text>
            </view>
          </view>
          <view class="hero-stat">
            <view class="hero-stat__icon hero-stat__icon--pending">
              <image class="hero-stat__icon-image" src="../static/commission-pending-icon.png" mode="aspectFit"></image>
            </view>
            <view class="hero-stat__body">
              <text class="hero-stat__label">待入账佣金（元）</text>
              <text class="hero-stat__value">¥{{ displaySummary.pending }}</text>
            </view>
          </view>
        </view>
      </view>

      <view class="stats-row">
        <view v-for="item in statsCards" :key="item.key" class="stat-card">
          <view class="stat-card__icon">
            <uni-icons :type="item.icon" size="18" :color="item.iconColor"></uni-icons>
          </view>
          <text class="stat-card__label">{{ item.label }}</text>
          <text :class="['stat-card__value', { 'stat-card__value--accent': item.accent }]">{{ item.value }}</text>
        </view>
      </view>

      <view v-if="advertisement.image" class="promotion-ad" @click="handleInvite">
        <image class="promotion-ad__image" :src="advertisement.image" mode="widthFix"></image>
      </view>

      <view class="section-header">
        <text class="section-header__title">收益明细</text>
        <uni-datetime-picker
          class="section-header__picker"
          v-model="dateRange"
          type="daterange"
          :border="false"
          :clear-icon="true"
          start-placeholder="开始日期"
          end-placeholder="结束日期"
          range-separator="至"
          @change="handleDateRangeChange"
        >
          <view class="section-header__filter">
            <text class="section-header__filter-text">{{ dateFilterText }}</text>
            <uni-icons
              v-if="hasDateFilter"
              type="clear"
              size="14"
              color="#8b8b97"
              @click.stop="clearDateRange"
            ></uni-icons>
            <uni-icons type="calendar" size="15" color="#8b8b97"></uni-icons>
          </view>
        </uni-datetime-picker>
      </view>

      <view class="record-list">
        <view v-if="records.length">
          <view v-for="record in records" :key="record.id" class="record-card">
            <view :class="['record-card__left', { 'record-card__left--muted': record.dimmed }]">
              <view :class="['record-card__icon', { 'record-card__icon--muted': record.dimmed }]">
                <uni-icons :type="record.icon" size="18" :color="record.iconColor"></uni-icons>
              </view>
              <view class="record-card__meta">
                <view class="record-card__title-row">
                  <text class="record-card__title">{{ record.title }}</text>
                  <text v-if="record.tag" :class="['record-card__tag', record.tagThemeClass]">{{ record.tag }}</text>
                </view>
                <text class="record-card__desc">{{ record.desc }}</text>
              </view>
            </view>
            <view class="record-card__right">
              <text :class="['record-card__amount', record.amountTypeClass]">{{ record.amount }}</text>
              <text class="record-card__time">{{ record.time }}</text>
            </view>
          </view>
        </view>

        <view v-if="listLoading" class="load-state">
          <text class="load-state__text">加载中...</text>
        </view>
        <view v-else-if="records.length && !hasMore" class="load-state">
          <text class="load-state__text">没有更多了</text>
        </view>
        <view v-else-if="listError" class="load-state" @click="loadCommissionLogs">
          <text class="load-state__text">{{ listError }}</text>
        </view>

        <view v-if="!listLoading && !records.length" class="empty-state">
          <uni-icons type="folder-add" size="48" color="#d1d5db"></uni-icons>
          <text class="empty-state__text">{{ listError || '暂无佣金记录' }}</text>
        </view>
      </view>
    </view>

    <view class="safe-bottom"></view>

    <app-floating-button />
  </view>
</template>

<script setup>
import { getCommissionIndex, getCommissionLogs, getCommissionStatistics } from '@/api/commission'
import { computed, ref } from 'vue'
import { onLoad, onPullDownRefresh, onReachBottom } from '@dcloudio/uni-app'

defineOptions({ name: 'UserCommissionPage' })

const loading = ref(false)
const listLoading = ref(false)
const listError = ref('')
const page = ref(1)
const limit = 10
const total = ref(0)
const hasMore = ref(true)
const dateRange = ref([])
const summary = ref({
  withdrawable: 0,
  total: 0,
  pending: 0
})
const stats = ref({
  directCount: '0',
  orderCount: '0',
  monthEstimate: '¥0.00'
})
const records = ref([])
const advertisement = ref({
  image: '',
  route: '/pages/users/distribute/index'
})

/**
 * 页面顶部金额展示。
 *
 * @returns {{withdrawable: string, total: string, pending: string}} 格式化后的佣金汇总。
 */
const displaySummary = computed(() => {
  return {
    withdrawable: formatAmount(summary.value.withdrawable),
    total: formatAmount(summary.value.total),
    pending: formatAmount(summary.value.pending)
  }
})

/**
 * 页面统计卡片配置。
 *
 * @returns {Array<Object>} 统计卡片展示列表。
 */
const statsCards = computed(() => {
  return [
    {
      key: 'direct',
      label: '直推人数',
      value: stats.value.directCount,
      icon: 'person-filled',
      iconColor: '#8b8b97',
      accent: false
    },
    {
      key: 'order',
      label: '转化订单',
      value: stats.value.orderCount,
      icon: 'shop-filled',
      iconColor: '#8b8b97',
      accent: false
    },
    {
      key: 'estimate',
      label: '本月预估',
      value: stats.value.monthEstimate,
      icon: 'paperplane-filled',
      iconColor: '#8b8b97',
      accent: true
    }
  ]
})

/**
 * 收益明细日期筛选展示文案。
 *
 * @returns {string} 已选日期范围或全部日期。
 */
const dateFilterText = computed(() => {
  if (Array.isArray(dateRange.value) && dateRange.value.length === 2 && dateRange.value[0] && dateRange.value[1]) {
    return dateRange.value[0] + ' 至 ' + dateRange.value[1]
  }

  return '全部日期'
})

/**
 * 是否启用了日期筛选。
 *
 * @returns {boolean} 已选择完整日期范围时返回 true。
 */
const hasDateFilter = computed(() => {
  return Array.isArray(dateRange.value) && dateRange.value.length === 2 && Boolean(dateRange.value[0]) && Boolean(dateRange.value[1])
})

onLoad(() => {
  loadCommissionData()
})

onReachBottom(() => {
  if (hasMore.value && !listLoading.value) {
    loadCommissionLogs()
  }
})

onPullDownRefresh(() => {
  loadCommissionData().finally(() => {
    uni.stopPullDownRefresh()
  })
})

/**
 * 初始化佣金页面数据。
 *
 * 分别拉取首页汇总、统计卡片和收益明细第一页。
 *
 * @returns {Promise<void>} 加载完成后刷新汇总数据。
 */
async function loadCommissionData() {
  loading.value = true
  listError.value = ''
  try {
    const indexRes = await getCommissionIndex({}, { showError: false })
    if (indexRes && indexRes.code === 200 && indexRes.data) {
      applyIndexData(indexRes.data)
    } else {
      showToast((indexRes && indexRes.msg) || '佣金数据加载失败')
    }

    const statsRes = await getCommissionStatistics({}, { showError: false })
    if (statsRes && statsRes.code === 200 && statsRes.data) {
      applyStatisticsData(statsRes.data)
    }

    resetLogs()
    await loadCommissionLogs()
  } catch (error) {
    listError.value = '佣金数据加载失败'
  } finally {
    loading.value = false
  }
}

/**
 * 应用佣金首页数据。
 *
 * @param {Object} data 接口返回数据。
 * @returns {void}
 */
function applyIndexData(data = {}) {
  const rawSummary = data.summary || {}
  const rawAdvertisement = data.advertisement || {}
  summary.value = {
    withdrawable: parseAmount(rawSummary.withdrawable, 0),
    total: parseAmount(rawSummary.total, 0),
    pending: parseAmount(rawSummary.pending, 0)
  }
  advertisement.value = {
    image: String(rawAdvertisement.image || ''),
    route: String(rawAdvertisement.route || '/pages/users/distribute/index')
  }
}

/**
 * 应用佣金统计数据。
 *
 * @param {Object} data 接口返回数据。
 * @returns {void}
 */
function applyStatisticsData(data = {}) {
  stats.value = {
    directCount: formatNumber(data.direct_count, '0'),
    orderCount: formatNumber(data.order_count, '0'),
    monthEstimate: '¥' + formatAmount(data.month_estimate, '0.00')
  }
}

/**
 * 重置收益明细分页。
 *
 * @returns {void}
 */
function resetLogs() {
  page.value = 1
  total.value = 0
  hasMore.value = true
  records.value = []
  listError.value = ''
}

/**
 * 加载收益明细分页。
 *
 * @returns {Promise<void>}
 */
async function loadCommissionLogs() {
  if (listLoading.value || !hasMore.value) {
    return
  }

  listLoading.value = true
  listError.value = ''
  try {
    const params = {
      page: page.value,
      limit
    }

    if (Array.isArray(dateRange.value) && dateRange.value.length === 2 && dateRange.value[0] && dateRange.value[1]) {
      params.start_date = dateRange.value[0]
      params.end_date = dateRange.value[1]
    }

    const res = await getCommissionLogs(params, {
      showError: false
    })

    if (!res || res.code !== 200 || !res.data) {
      listError.value = (res && res.msg) || '收益明细加载失败'
      return
    }

    const list = Array.isArray(res.data.list) ? res.data.list : []
    const normalized = list.map((item) => normalizeRecord(item))
    records.value = records.value.concat(normalized)
    total.value = Number(res.data.total || records.value.length)
    hasMore.value = records.value.length < total.value && list.length > 0
    if (hasMore.value) {
      page.value += 1
    }
  } catch (error) {
    listError.value = '收益明细加载失败'
  } finally {
    listLoading.value = false
  }
}

/**
 * 规范化收益明细记录。
 *
 * @param {Object} record 后端记录。
 * @returns {Object}
 */
function normalizeRecord(record = {}) {
  const tagTheme = String(record.tag_theme || '')
  const amountType = String(record.amount_type || 'positive')
  return {
    id: String(record.id || ''),
    title: String(record.title || '佣金收益'),
    tag: String(record.tag || ''),
    tagThemeClass: tagTheme ? 'record-card__tag--' + tagTheme : '',
    desc: String(record.desc || ''),
    amount: String(record.amount || '+ ¥0.00'),
    amountTypeClass: 'record-card__amount--' + amountType,
    time: String(record.time || ''),
    icon: String(record.icon || 'person-filled'),
    iconColor: String(record.icon_color || '#666666'),
    dimmed: Boolean(record.dimmed)
  }
}

/**
 * 处理提现按钮点击。
 *
 * @returns {void}
 */
function handleWithdraw() {
  if (loading.value) {
    return
  }

  uni.navigateTo({
    url: '/pages/users/withdraw/index?source=commission'
  })
}

/**
 * 处理收益明细日期范围变更。
 *
 * @param {Array<string>} value 日期范围，格式为 [YYYY-MM-DD, YYYY-MM-DD]。
 * @returns {void}
 */
function handleDateRangeChange(value = []) {
  dateRange.value = Array.isArray(value) ? value : []
  resetLogs()
  loadCommissionLogs()
}

/**
 * 清空收益明细日期筛选。
 *
 * @returns {void}
 */
function clearDateRange() {
  if (!hasDateFilter.value) {
    return
  }

  dateRange.value = []
  resetLogs()
  loadCommissionLogs()
}

/**
 * 处理邀请活动入口点击。
 *
 * @returns {void}
 */
function handleInvite() {
  uni.navigateTo({
    url: advertisement.value.route || '/pages/users/distribute/index',
    fail: () => {
      showToast('推广海报页暂未开放')
    }
  })
}

/**
 * 统一展示功能提示。
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

/**
 * 格式化金额数值文本。
 *
 * @param {number|string} value 原始金额。
 * @param {string} fallback 兜底展示。
 * @returns {string} 保留两位小数的金额文本。
 */
function formatAmount(value, fallback = '0.00') {
  const amount = Number(String(value === undefined || value === null ? '' : value).replace(/,/g, '').trim())

  if (!Number.isFinite(amount)) {
    return fallback
  }

  return amount.toLocaleString('zh-CN', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
}

/**
 * 格式化普通计数展示。
 *
 * @param {number|string} value 原始数值。
 * @param {string} fallback 兜底文本。
 * @returns {string} 格式化后的整数文本。
 */
function formatNumber(value, fallback = '0') {
  const normalized = String(value === undefined || value === null ? '' : value).replace(/,/g, '').trim()

  if (!normalized) {
    return fallback
  }

  const amount = Number(normalized)

  if (!Number.isFinite(amount)) {
    return fallback
  }

  return amount.toLocaleString('zh-CN')
}

/**
 * 格式化百分比展示。
 *
 * @param {number|string|null} value 原始比率。
 * @param {string} fallback 兜底文本。
 * @returns {string} 百分比文本。
 */
/**
 * 将原始金额转为数字，异常时回退到默认值。
 *
 * @param {number|string|null} value 原始金额。
 * @param {number} fallback 兜底金额。
 * @returns {number} 规范化后的金额。
 */
function parseAmount(value, fallback = 0) {
  const normalized = String(value === undefined || value === null ? '' : value).replace(/,/g, '').replace(/^¥/, '').trim()
  const amount = Number(normalized)

  return Number.isFinite(amount) ? amount : fallback
}
</script>

<style lang="scss">
page {
  background: #ececec;
}
</style>

<style lang="scss" scoped>
.commission-page {
  position: relative;
  overflow: hidden;
  min-height: 100vh;
  background: #f5f5f5;
  color: var(--text-title, #1a1c1c);

  &__hero-bg {
    position: absolute;
    top: 0;
    left: 0;
    z-index: 0;
    width: 100%;
    height: 660rpx;
  }

  &__wallet-illustration {
    position: absolute;
    top: 218rpx;
    right: -64rpx;
    z-index: 1;
    width: 360rpx;
    height: 344rpx;
  }

  &__hero-mask {
    position: absolute;
    left: 0;
    right: 0;
    top: 500rpx;
    z-index: 1;
    height: 220rpx;
    background: linear-gradient(180deg, rgba(245, 245, 245, 0) 0%, #f5f5f5 82%);
  }

  &__safe-top {
    position: relative;
    z-index: 2;
  }
}

.commission-page__content {
  position: relative;
  z-index: 2;
  padding: 44rpx 24rpx 0;
}

.hero-card {
  position: relative;
  min-height: 580rpx;
  padding: 54rpx 0 0;
  box-sizing: border-box;

  &__watermark {
    position: absolute;
    top: 12rpx;
    right: -8rpx;
    opacity: 0.22;
    transform: scale(1.75);
  }

  &__label {
    margin-right: 18rpx;
    font-size: 30rpx;
    line-height: 42rpx;
    font-weight: 700;
    color: #4b5563;
  }

  &__label-row {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
  }

  &__amount-row {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: baseline;
    flex-wrap: wrap;
    margin-top: 38rpx;
  }

  &__currency {
    font-size: 54rpx;
    line-height: 60rpx;
    font-weight: 800;
    color: var(--brand-primary, #ea580c);
  }

  &__amount {
    margin-left: 4rpx;
    font-size: 88rpx;
    line-height: 96rpx;
    font-weight: 800;
    color: var(--brand-primary, #ea580c);
  }

  &__badge {
    position: relative;
    z-index: 1;
    display: block;
    margin-top: 20rpx;
    font-size: 28rpx;
    line-height: 40rpx;
    font-weight: 600;
    color: #6b7280;
  }

  &__stats {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    min-height: 164rpx;
    margin-top: 48rpx;
    padding: 26rpx 0;
    border-radius: 20rpx;
    background: rgba(255, 255, 255, 0.96);
    box-shadow: 0 8rpx 24rpx rgba(17, 24, 39, 0.04);
  }

  &__button {
    position: relative;
    z-index: 1;
    width: 300rpx;
    height: 72rpx;
    margin-top: 36rpx;
    border-radius: 999rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--theme-gradient, linear-gradient(135deg, #ea580c 0%, #fb923c 100%));
    box-shadow: 0 12rpx 24rpx var(--brand-shadow, rgba(234, 88, 12, 0.18));

    &--disabled {
      opacity: 0.7;
    }
  }

  &__button-text {
    margin-left: 10rpx;
    font-size: 30rpx;
    line-height: 42rpx;
    font-weight: 700;
    color: #ffffff;
  }
}

.hero-stat {
  position: relative;
  width: 50%;
  min-width: 0;
  display: flex;
  align-items: center;
  padding-left: 34rpx;
  padding-right: 12rpx;
  box-sizing: border-box;

  &--left {
    border-right: 1px solid rgba(17, 24, 39, 0.08);
  }

  &__body {
    min-width: 0;
    margin-left: 18rpx;
  }

  &__icon {
    width: 74rpx;
    height: 74rpx;
    border-radius: 50%;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  &__icon--total {
    background: #ffe5dc;
  }

  &__icon--pending {
    background: #eadcff;
  }

  &__icon-image {
    width: 48rpx;
    height: 48rpx;
  }

  &__label {
    display: block;
    font-size: 24rpx;
    line-height: 34rpx;
    font-weight: 700;
    color: #1f2937;
    white-space: nowrap;
  }

  &__value {
    display: block;
    margin-top: 14rpx;
    font-size: 34rpx;
    line-height: 42rpx;
    font-weight: 700;
    color: #374151;
    white-space: nowrap;
  }
}

.stats-row {
  display: flex;
  margin-top: 26rpx;
  margin-left: -6rpx;
  margin-right: -6rpx;
}

.stat-card {
  flex: 1;
  min-width: 0;
  margin-left: 6rpx;
  margin-right: 6rpx;
  padding: 26rpx 12rpx 22rpx;
  border-radius: 18rpx;
  text-align: center;
  background: #ffffff;
  box-shadow: 0 10rpx 24rpx rgba(17, 24, 39, 0.05);

  &__icon {
    width: 54rpx;
    height: 54rpx;
    margin: 0 auto;
    border-radius: 27rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f4f4f5;
  }

  &__label {
    display: block;
    margin-top: 12rpx;
    font-size: 20rpx;
    line-height: 1.2;
    color: var(--text-secondary, #8b8b97);
  }

  &__value {
    display: block;
    margin-top: 10rpx;
    font-size: 32rpx;
    line-height: 1.2;
    font-weight: 700;
    color: var(--text-title, #1a1c1c);

    &--accent {
      color: var(--brand-primary, #ea580c);
    }
  }
}

.promotion-ad {
  overflow: hidden;
  margin-top: 32rpx;
  border-radius: 18rpx;
  background: #ffffff;
  box-shadow: 0 10rpx 24rpx rgba(17, 24, 39, 0.05);
}

.promotion-ad__image {
  display: block;
  width: 100%;
}

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 52rpx;
  padding-left: 4rpx;
  padding-right: 4rpx;

  &__title {
    flex-shrink: 0;
    font-size: 38rpx;
    line-height: 1.2;
    font-weight: 700;
    color: var(--text-title, #1a1c1c);
  }

  &__filter {
    display: flex;
    align-items: center;
    padding-top: 8rpx;
    padding-bottom: 8rpx;
  }

  &__filter-text {
    margin-right: 8rpx;
    max-width: 430rpx;
    font-size: 22rpx;
    line-height: 1.2;
    text-align: right;
    color: var(--text-secondary, #8b8b97);
  }

  &__picker {
    margin-left: auto;
    flex-shrink: 0;
    display: flex;
    justify-content: flex-end;
  }
}

.record-list {
  margin-top: 20rpx;
}

.record-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 26rpx 28rpx;
  margin-top: 16rpx;
  border-radius: 18rpx;
  background: #ffffff;
  box-shadow: 0 10rpx 24rpx rgba(17, 24, 39, 0.05);

  &:first-child {
    margin-top: 0;
  }

  &__left {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;

    &--muted {
      opacity: 0.68;
    }
  }

  &__icon {
    width: 72rpx;
    height: 72rpx;
    border-radius: 36rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f1f1f3;

    &--muted {
      background: #e5e7eb;
    }
  }

  &__meta {
    flex: 1;
    min-width: 0;
    margin-left: 20rpx;
  }

  &__title-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
  }

  &__title {
    max-width: 100%;
    font-size: 30rpx;
    line-height: 1.3;
    font-weight: 700;
    color: var(--text-title, #1a1c1c);
  }

  &__tag {
    margin-left: 12rpx;
    padding: 5rpx 10rpx;
    border-radius: 999rpx;
    font-size: 18rpx;
    line-height: 1.2;
    font-weight: 700;

    &--settled {
      color: #18a058;
      background: rgba(24, 160, 88, 0.12);
    }

    &--pending {
      color: #8b8b97;
      background: #f3f4f6;
    }

    &--hot {
      color: var(--brand-primary, #ea580c);
      background: var(--brand-focus-shadow, rgba(234, 88, 12, 0.16));
    }
  }

  &__desc {
    display: block;
    margin-top: 8rpx;
    font-size: 22rpx;
    line-height: 1.3;
    color: var(--text-secondary, #8b8b97);
  }

  &__right {
    margin-left: 18rpx;
    text-align: right;
    flex-shrink: 0;
  }

  &__amount {
    display: block;
    font-size: 32rpx;
    line-height: 1.2;
    font-weight: 700;

    &--positive {
      color: var(--text-title, #1a1c1c);
    }

    &--pending {
      color: #a1a1aa;
    }

    &--negative {
      color: #7e7576;
    }
  }

  &__time {
    display: block;
    margin-top: 10rpx;
    font-size: 20rpx;
    line-height: 1.2;
    color: #b4b4bc;
  }
}

.load-state {
  min-height: 88rpx;
  margin-top: 16rpx;
  display: flex;
  align-items: center;
  justify-content: center;
}

.load-state__text {
  font-size: 24rpx;
  line-height: 1.2;
  color: var(--text-secondary, #8b8b97);
}

.empty-state {
  padding-top: 80rpx;
  padding-bottom: 96rpx;
  display: flex;
  align-items: center;
  flex-direction: column;
}

.empty-state__text {
  margin-top: 18rpx;
  font-size: 24rpx;
  line-height: 1.3;
  color: var(--text-secondary, #8b8b97);
}

.safe-bottom {
  height: calc(32rpx + var(--window-bottom, 0px));
}
</style>
