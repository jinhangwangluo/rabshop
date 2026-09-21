<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="balance-page">
    <image class="balance-page__hero-bg" src="../static/balance-funds-top-bg.jpg" mode="aspectFill"></image>
    <view class="balance-page__hero-mask"></view>
    <view class="balance-page__safe-top">
      <app-safe-top title="资产" background="transparent" title-color="#4b2d1c" icon-color="#4b2d1c" />
    </view>

    <view class="balance-page__content">
      <view class="hero-card">
        <view class="hero-card__main">
          <text class="hero-card__label">当前余额 (CNY)</text>
          <view class="hero-card__amount-row">
            <text class="hero-card__currency">¥</text>
            <text class="hero-card__amount">{{ summary.balanceAmount }}</text>
          </view>
        </view>

        <view class="hero-card__actions">
          <view class="hero-action hero-action--primary" @click="handleRecharge">
            <view class="hero-action__badge hero-action__badge--primary">
              <text class="iconfont icon-add hero-action__badge-icon"></text>
            </view>
            <text class="hero-action__text hero-action__text--primary">充值</text>
          </view>
          <view class="hero-action hero-action--secondary" @click="handleWithdraw">
            <view class="hero-action__badge hero-action__badge--secondary">
              <text class="iconfont icon-tixian hero-action__badge-icon hero-action__badge-icon--secondary"></text>
            </view>
            <text class="hero-action__text">佣金提现</text>
          </view>
        </view>
      </view>

      <view class="stats-row">
        <view v-for="item in statsList" :key="item.key" class="stat-card">
          <text class="stat-card__label">{{ item.label }}</text>
          <text :class="['stat-card__value', { 'stat-card__value--accent': item.accent }]">{{ item.value }}</text>
        </view>
      </view>

      <view class="section-header">
        <text class="section-header__title">收支明细</text>
        <view class="section-header__action" @click="handleFilter">
          <text class="section-header__action-text">{{ currentFilterText }}</text>
          <text class="iconfont icon-filter section-header__action-icon"></text>
        </view>
      </view>

      <view class="record-card">
        <block v-if="displayRecords.length">
          <view
            v-for="(item, index) in displayRecords"
            :key="item.id"
            :class="['record-item', { 'record-item--border': index !== displayRecords.length - 1 }]"
          >
            <view class="record-item__left">
              <view :class="item.iconWrapClass">
                <text v-if="item.iconClass" :class="item.iconClass"></text>
                <text v-else :class="item.iconTextClass">{{ item.iconText }}</text>
              </view>
              <view class="record-item__meta">
                <text class="record-item__title">{{ item.title }}</text>
                <text class="record-item__time">{{ item.time }}</text>
              </view>
            </view>
            <view class="record-item__right">
              <text :class="item.amountClass">{{ item.amount }}</text>
              <text class="record-item__status">{{ item.status }}</text>
            </view>
          </view>
        </block>

        <view v-else-if="!recordsLoading" class="empty-state">
          <image class="empty-state__image" :src="emptyImage" mode="aspectFit"></image>
          <text class="empty-state__text">暂无记录</text>
        </view>
      </view>

      <view v-if="recordsLoading" class="record-more">
        <six-petal-spiral active />
      </view>

      <view v-else-if="!hasMoreRecords && recordsTotal > 0" class="record-more">
        <text class="record-more__text">没有更多了</text>
      </view>

      <view class="safe-bottom"></view>
    </view>

    <app-floating-button />

  </view>
</template>

<script setup>
import { getBalanceStatistics, getBalanceLogs } from '@/api/balance'
import { getStatusImageUrl } from '@/utils/status_images'
import appFloatingButton from '@/components/app-floating-button'
import { computed, ref } from 'vue'
import { onLoad, onPullDownRefresh, onReachBottom, onShow } from '@dcloudio/uni-app'

const FILTER_OPTIONS = [
  {
    label: '全部明细',
    value: 'all'
  },
  {
    label: '收入明细',
    value: 'in'
  },
  {
    label: '支出明细',
    value: 'out'
  }
]

defineOptions({ name: 'UserBalancePage' })

const activeFilter = ref('all')
const summaryLoading = ref(false)
const recordsLoading = ref(false)
const summary = ref({
  balanceAmount: '0.00'
})
const statsList = ref([
  { key: 'month-recharge', label: '本月充值', value: '0', accent: false },
  { key: 'month-consume', label: '本月消费', value: '0', accent: false },
  { key: 'total-income', label: '累计收益', value: '0', accent: true }
])
const records = ref([])
const recordsPage = ref(1)
const recordsLimit = ref(10)
const recordsTotal = ref(0)
const emptyImage = getStatusImageUrl('empty.png')
const hasLoaded = ref(false)

/**
 * 当前筛选按钮展示文案。
 *
 * @returns {string}
 */
const currentFilterText = computed(() => {
  if (activeFilter.value === 'all') {
    return '筛选'
  }

  return activeFilter.value === 'in' ? '收入' : '支出'
})

const displayRecords = computed(() => {
  return records.value.map((item) => mapRecordItem(item))
})

/**
 * 是否还有下一页余额记录。
 *
 * @returns {boolean}
 */
const hasMoreRecords = computed(() => {
  return records.value.length < Number(recordsTotal.value || 0)
})

onLoad(() => {
  loadBalanceSummary()
  loadRecords()
})

onShow(() => {
  if (hasLoaded.value) {
    loadBalanceSummary()
    loadRecords()
  }
  hasLoaded.value = true
})

onPullDownRefresh(() => {
  loadBalanceSummary()
  loadRecords().finally(() => {
    uni.stopPullDownRefresh()
  })
})

onReachBottom(() => {
  handleReachBottom()
})

/**
 * 加载余额及月度统计。
 */
async function loadBalanceSummary() {
  summaryLoading.value = true

  try {
    const res = await getBalanceStatistics()
    if (res && res.code === 200 && res.data) {
      const data = res.data
      summary.value.balanceAmount = formatAmount(data.balance)

      statsList.value = [
        { key: 'month-recharge', label: '本月充值', value: formatStatAmount(data.month_recharge, '+'), accent: false },
        { key: 'month-consume', label: '本月消费', value: formatStatAmount(data.month_consume, '-'), accent: false },
        { key: 'total-income', label: '累计收益', value: formatStatAmount(data.total_income, '+'), accent: true }
      ]
    }
  } catch (error) {
    // 接口失败时保持占位
  } finally {
    summaryLoading.value = false
  }
}

/**
 * 加载余额变动记录。
 *
 * @param {boolean} append 是否追加到现有列表。
 */
async function loadRecords(append = false) {
  if (recordsLoading.value) return

  if (append && !hasMoreRecords.value) {
    return
  }

  recordsLoading.value = true

  try {
    const params = {
      page: append ? recordsPage.value + 1 : 1,
      limit: recordsLimit.value
    }

    if (activeFilter.value !== 'all') {
      params.change_type = activeFilter.value
    }

    const res = await getBalanceLogs(params)
    if (res && res.code === 200 && res.data) {
      const data = res.data
      recordsTotal.value = data.total || 0
      recordsPage.value = data.page || 1

      if (append) {
        records.value = [...records.value, ...(data.list || [])]
      } else {
        records.value = data.list || []
      }
    }
  } catch (error) {
    // 接口失败时保持已有数据
  } finally {
    recordsLoading.value = false
  }
}

/**
 * 页面触底时加载下一页余额记录。
 *
 * @returns {void}
 */
function handleReachBottom() {
  if (recordsLoading.value || !hasMoreRecords.value) {
    return
  }

  loadRecords(true)
}

/**
 * 后端 source_type 映射为前端展示字段。
 */
function getSourceTypeMeta(sourceType = '') {
  const metaMap = {
    order_balance_pay: { title: '消费', iconClass: 'iconfont icon-cart', theme: 'orange' },
    live_coin_balance_pay: { title: '余额购买直播金币', iconClass: 'iconfont icon-record', theme: 'orange' },
    recharge: { title: '充值', iconClass: 'iconfont icon-roundaddfill', theme: 'green' },
    order_refund_balance: { title: '退款', iconClass: 'iconfont icon-refund', theme: 'green' },
    manual: { title: '调整', iconClass: 'iconfont icon-edit', theme: 'gray' },
    withdraw: { title: '提现', iconClass: 'iconfont icon-tixian', theme: 'gray' }
  }
  return metaMap[sourceType] || { title: sourceType || '变动', iconClass: 'iconfont icon-record', theme: 'gray' }
}

/**
 * 将后端记录映射为前端展示格式。
 */
function mapRecordItem(item = {}) {
  const meta = getSourceTypeMeta(item.source_type)
  const amount = parseAmount(item.amount)
  const isIn = item.change_type === 'in'

  return {
    id: item.id,
    title: meta.title,
    time: item.create_time || '',
    amount: isIn ? `+${formatAmount(amount)}` : `-${formatAmount(amount)}`,
    amountType: isIn ? 'plus' : 'minus',
    amountClass: `record-item__amount record-item__amount--${isIn ? 'plus' : 'minus'}`,
    status: item.remark || (isIn ? '入账' : '支出'),
    iconClass: meta.iconClass ? `${meta.iconClass} record-item__icon-glyph record-item__icon-glyph--${meta.theme}` : '',
    iconText: meta.iconText || '',
    iconTextClass: `record-item__icon-text record-item__icon-text--${meta.theme}`,
    iconWrapClass: `record-item__icon record-item__icon--${meta.theme}`,
    theme: meta.theme
  }
}

/**
 * 格式化金额。
 */
function formatAmount(value) {
  const amount = Number(value || 0)
  return Number.isFinite(amount) ? amount.toFixed(2) : '0.00'
}

/**
 * 格式化统计金额。
 */
function formatStatAmount(value, prefix = '') {
  const formatted = formatAmount(value)
  return `${prefix}${formatted}`
}

/**
 * 解析金额。
 */
function parseAmount(value) {
  const normalized = String(value === undefined || value === null ? '' : value).replace(/,/g, '').trim()
  const amount = Number(normalized)
  return Number.isFinite(amount) ? amount : 0
}

/**
 * 切换筛选条件。
 */
function handleFilter() {
  uni.showActionSheet({
    itemList: FILTER_OPTIONS.map((item) => item.label),
    success: ({ tapIndex }) => {
      const selected = FILTER_OPTIONS[tapIndex]

      if (!selected) {
        return
      }

      if (activeFilter.value !== selected.value) {
        activeFilter.value = selected.value
        loadRecords()
      }
    }
  })
}

function handleRecharge() {
  uni.navigateTo({
    url: '/pages/users/recharge/index'
  })
}

function handleWithdraw() {
  uni.navigateTo({
    url: '/pages/users/withdraw/index?source=commission'
  })
}

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
.balance-page {
  position: relative;
  overflow: hidden;
  min-height: 100vh;
  background: #f3f3f3;

  &__hero-bg {
    position: absolute;
    top: 0;
    left: 0;
    z-index: 0;
    width: 100%;
    height: 620rpx;
  }

  &__hero-mask {
    position: absolute;
    left: 0;
    right: 0;
    top: 390rpx;
    z-index: 1;
    height: 260rpx;
    background: linear-gradient(180deg, rgba(243, 243, 243, 0) 0%, #f3f3f3 82%);
  }

  &__safe-top {
    position: relative;
    z-index: 2;
  }
}

.top-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10rpx 32rpx 20rpx;

  &__title-wrap {
    min-width: 120rpx;
  }

  &__title {
    font-size: 48rpx;
    line-height: 1.2;
    font-weight: 700;
    color: #18181b;
  }

  &__action {
    width: 64rpx;
    height: 64rpx;
    border-radius: 32rpx;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  &__action-text {
    margin-top: -10rpx;
    font-size: 40rpx;
    line-height: 1;
    color: #52525b;
    letter-spacing: 4rpx;
  }
}

.balance-page__content {
  position: relative;
  z-index: 2;
  padding: 24rpx 20rpx 0;
}

.hero-card {
  position: relative;
  min-height: 334rpx;
  padding: 48rpx 28rpx 34rpx 32rpx;
  box-sizing: border-box;

  &__watermark {
    position: absolute;
    top: -10rpx;
    right: 18rpx;
    font-size: 220rpx;
    line-height: 1;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.12);
  }

  &__main {
    position: relative;
    z-index: 1;
  }

  &__label {
    display: block;
    font-size: 20rpx;
    line-height: 1.3;
    letter-spacing: 2rpx;
    color: rgba(75, 45, 28, 0.72);
  }

  &__amount-row {
    margin-top: 18rpx;
    display: flex;
    align-items: flex-end;
  }

  &__currency {
    margin-right: 8rpx;
    margin-bottom: 4rpx;
    font-size: 40rpx;
    line-height: 1;
    font-weight: 700;
    color: #4b2d1c;
  }

  &__amount {
    font-size: 48rpx;
    line-height: 0.92;
    font-weight: 900;
    letter-spacing: -2rpx;
    color: #4b2d1c;
  }

  &__actions {
    position: relative;
    z-index: 1;
    margin-top: 48rpx;
    display: flex;
    justify-content: space-between;
  }
}

.hero-action {
  width: 304rpx;
  height: 92rpx;
  border-radius: 999rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;

  &--primary {
    background: var(--brand-primary, #ea580c);
  }

  &--secondary {
    background: #f5f5f5;
  }

  &__badge {
    width: 34rpx;
    height: 34rpx;
    border-radius: 17rpx;
    display: flex;
    align-items: center;
    justify-content: center;

    &--primary {
      background: rgba(255, 255, 255, 0.22);
    }

    &--secondary {
      background: rgba(255, 255, 255, 0.18);
    }
  }

  &__badge-text {
    font-size: 24rpx;
    line-height: 1;
    font-weight: 700;


    &--secondary {
      font-size: 20rpx;
    }
  }

  &__badge-icon {
    font-size: 22rpx;
    line-height: 1;
    color: #ffffff;

    &--secondary {
      color: #52525b;
    }
  }

  &__text {
    margin-left: 10rpx;
    font-size: 28rpx;
    line-height: 1;
    font-weight: 700;
  

    &--primary {
      color: #ffffff;
    }
  }
}

.stats-row {
  margin-top: 30rpx;
  display: flex;
  justify-content: space-between;
}

.stat-card {
  width: 220rpx;
  padding: 28rpx 12rpx 26rpx;
  border-radius: 16rpx;
  background: #ffffff;
  box-shadow: 0 4rpx 12rpx rgba(15, 23, 42, 0.03);
  text-align: center;
  box-sizing: border-box;

  &__label {
    display: block;
    font-size: 20rpx;
    line-height: 1.3;
    color: #a1a1aa;
  }

  &__value {
    display: block;
    margin-top: 10rpx;
    font-size: 28rpx;
    line-height: 1.2;
    font-weight: 700;
    color: #18181b;

    &--accent {
      color: var(--brand-primary, #ea580c);
    }
  }
}

.section-header {
  margin-top: 34rpx;
  padding: 0 2rpx;
  display: flex;
  align-items: center;
  justify-content: space-between;

  &__title {
    font-size: 34rpx;
    line-height: 1.2;
    font-weight: 700;
    color: #18181b;
  }

  &__action {
    display: flex;
    align-items: center;
  }

  &__action-text {
    font-size: 20rpx;
    line-height: 1;
    color: #8b8b95;
  }

  &__action-icon {
    margin-left: 8rpx;
    font-size: 22rpx;
    line-height: 1;
    color: #8b8b95;
  }
}

.record-card {
  margin-top: 22rpx;
  padding-top: 6rpx;
  border-radius: 16rpx;
  background: #ffffff;
  box-shadow: 0 4rpx 12rpx rgba(15, 23, 42, 0.03);
  overflow: hidden;
}

.record-more {
  display: flex;
  justify-content: center;
  padding: 38rpx 0 6rpx;

  &__text {
    font-size: 22rpx;
    line-height: 1.3;
    color: #b4b4bb;
  }
}

.record-item {
  padding: 30rpx 28rpx 28rpx;
  display: flex;
  align-items: center;
  justify-content: space-between;

  &--border {
    border-bottom: 1rpx solid #f2f2f4;
  }

  &__left {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
  }

  &__icon {
    width: 64rpx;
    height: 64rpx;
    border-radius: 32rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;

    &--orange {
      background: #fff3ea;
    }

    &--green {
      background: #edf9f1;
    }

    &--gray {
      background: #f1f1f3;
    }
  }

  &__icon-text {
    font-size: 24rpx;
    line-height: 1;
    font-weight: 700;

    &--orange {
      color: var(--brand-primary, #ea580c);
    }

    &--green {
      color: #16a34a;
    }

    &--gray {
      color: #5b5f64;
    }
  }

  &__icon-glyph {
    font-size: 30rpx;
    line-height: 1;

    &--orange {
      color: var(--brand-primary, #ea580c);
    }

    &--green {
      color: #16a34a;
    }

    &--gray {
      color: #5b5f64;
    }
  }

  &__meta {
    flex: 1;
    min-width: 0;
    margin-left: 18rpx;
  }

  &__title {
    display: block;
    font-size: 28rpx;
    line-height: 1.25;
    font-weight: 700;
    color: #18181b;
  }

  &__time {
    display: block;
    margin-top: 6rpx;
    font-size: 20rpx;
    line-height: 1.3;
    color: #a1a1aa;
  }

  &__right {
    margin-left: 16rpx;
    text-align: right;
  }

  &__amount {
    display: block;
    font-size: 28rpx;
    line-height: 1;
    font-weight: 700;

    &--plus {
      color: #16a34a;
    }

    &--minus {
      color: #18181b;
    }
  }

  &__status {
    display: block;
    margin-top: 8rpx;
    font-size: 20rpx;
    line-height: 1.3;
    color: #b2b2b8;
  }
}

.empty-state {
  padding: 96rpx 32rpx 88rpx;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;

  &__image {
    width: 200rpx;
    height: 200rpx;
  }

  &__text {
    margin-top: 22rpx;
    font-size: 24rpx;
    line-height: 1.3;
    color: #a1a1aa;
  }
}

.safe-bottom {
  height: 36rpx;
}

/* #ifdef H5 */
.safe-bottom {
  height: calc(36rpx + var(--window-bottom, 0px));
}
/* #endif */

/* #ifdef MP-WEIXIN */
.safe-bottom {
  height: calc(36rpx + env(safe-area-inset-bottom));
}
/* #endif */
</style>
