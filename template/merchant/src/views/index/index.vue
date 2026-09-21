<template>
  <div v-loading="pageLoading" class="merchant-dashboard">
    <section class="dashboard-hero">
      <div class="dashboard-hero__content">
        <div class="dashboard-hero__eyebrow">
          <span :class="['status-dot', merchant.business_status === 1 ? 'is-open' : 'is-rest']" />
          <span>{{ merchant.business_status_text || '--' }}</span>
        </div>
        <h1>欢迎回来，{{ merchant.name || '商户' }}</h1>
        <div class="dashboard-hero__meta">
          <span>商户ID：{{ merchant.id || '--' }}</span>
          <span>{{ merchant.mer_class_text || '--' }}</span>
          <span>营业时间：{{ merchant.business_hours_text || '--' }}</span>
        </div>
        <p>{{ merchant.description || '暂无店铺简介' }}</p>
      </div>

      <div class="dashboard-hero__art">
        <img
          :src="shopIllustration"
          class="shop-illustration"
          alt=""
          aria-hidden="true"
        >
      </div>
    </section>

    <section class="metric-grid">
      <article v-for="card in dashboard.cards" :key="card.key" class="metric-card">
        <div class="metric-card__top">
          <span class="metric-card__icon">
            <LegacyIcon :name="getCardIcon(card.key)" />
          </span>
          <span class="metric-card__title">{{ card.title }}</span>
        </div>
        <div class="metric-card__value">
          <span v-if="card.unit === '¥'" class="metric-card__currency">¥</span>
          <span>{{ formatCardValue(card) }}</span>
          <span v-if="card.unit && card.unit !== '¥'" class="metric-card__unit">{{ card.unit }}</span>
        </div>
        <div :class="['metric-card__compare', getTrendClass(card.trend)]">
          {{ card.compare_text || '较昨日 --' }}
        </div>
      </article>
    </section>

    <section class="quick-actions">
      <button
        v-for="item in dashboard.quick_actions"
        :key="item.title"
        type="button"
        :class="['quick-action', 'is-' + (item.type || 'info')]"
        @click="handleQuickAction(item)"
      >
        <span class="quick-action__icon">
          <img
            v-if="getQuickActionImage(item)"
            :src="getQuickActionImage(item)"
            alt=""
            aria-hidden="true"
          >
          <LegacyIcon v-else :name="item.icon" />
        </span>
        <span>{{ item.title }}</span>
      </button>
    </section>

    <section v-loading="rangeLoading" class="dashboard-panel trend-panel" element-loading-text="加载趋势中">
      <header class="dashboard-panel__header">
        <div>
          <h2>成交趋势</h2>
          <p>{{ rangeTitle }}</p>
        </div>
        <div class="trend-toolbar">
          <el-button-group class="range-switch">
            <el-button
              :type="!isCustomRange && range === 7 ? 'primary' : 'default'"
              :disabled="rangeLoading"
              @click="handleRangeChange(7)"
            >
              近7天
            </el-button>
            <el-button
              :type="!isCustomRange && range === 30 ? 'primary' : 'default'"
              :disabled="rangeLoading"
              @click="handleRangeChange(30)"
            >
              近30天
            </el-button>
          </el-button-group>
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="~"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="yyyy-MM-dd"

            class="custom-range-picker"
            :picker-options="pickerOptions"
            :disabled="rangeLoading"
            @change="handleDateChange"
          />
        </div>
      </header>
      <div ref="orderChartEl" class="order-chart" />
    </section>

    <section class="overview-grid">
      <article v-loading="rangeLoading" class="dashboard-panel user-panel" element-loading-text="加载用户数据中">
        <header class="dashboard-panel__header">
          <div>
            <h2>用户数据概览</h2>
            <p>{{ rangeTitle }}</p>
          </div>
        </header>
        <div class="user-overview">
          <div v-for="item in userOverviewItems" :key="item.key" class="user-overview__item">
            <span>{{ item.label }}</span>
            <strong>{{ formatOverviewValue(item) }}</strong>
          </div>
        </div>
        <div ref="userChartEl" class="user-chart" />
      </article>

      <article class="dashboard-panel content-panel">
        <header class="dashboard-panel__header">
          <div>
            <h2>专题与优惠券数据</h2>
            <p>运营转化</p>
          </div>
        </header>
        <div class="content-coupon-layout">
          <div class="content-coupon-block">
            <div class="block-title">专题活动</div>
            <div class="block-stat">
              <span>活动数量</span>
              <strong>{{ formatNumber(contentCoupon.special_count) }}</strong>
            </div>
            <div class="block-stat">
              <span>专题购买</span>
              <strong>{{ formatNumber(contentCoupon.special_buy_count) }}</strong>
            </div>
            <div class="ring-row">
              <div class="ring" :style="circleStyle(contentCoupon.special_conversion_rate)">
                <span>{{ formatPercent(contentCoupon.special_conversion_rate) }}</span>
              </div>
              <div>
                <strong>专题转化率</strong>
                <p>{{ formatNumber(contentCoupon.special_buy_count) }} 次购买</p>
              </div>
            </div>
          </div>

          <div class="content-coupon-divider" />

          <div class="content-coupon-block">
            <div class="block-title">优惠券</div>
            <div class="block-stat">
              <span>领取量</span>
              <strong>{{ formatNumber(contentCoupon.coupon_received) }}</strong>
            </div>
            <div class="block-stat">
              <span>核销量</span>
              <strong>{{ formatNumber(contentCoupon.coupon_used) }}</strong>
            </div>
            <div class="ring-row">
              <div class="ring" :style="circleStyle(contentCoupon.coupon_writeoff_rate)">
                <span>{{ formatPercent(contentCoupon.coupon_writeoff_rate) }}</span>
              </div>
              <div>
                <strong>核销率</strong>
                <p>¥{{ formatMoney(contentCoupon.coupon_writeoff_amount) }}</p>
              </div>
            </div>
          </div>
        </div>
      </article>
    </section>
  </div>
</template>

<script setup>
import echarts from 'echarts'
import { getMerchantDashboardHome } from '@/api/dashboard'
import { ElMessage } from 'element-plus'
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import shopIllustration from '@/assets/images/merchant-home/shop-illustration.webp'
import icon01 from '@/assets/images/merchant-home/icon_01.png'
import icon02 from '@/assets/images/merchant-home/icon_02.png'
import icon03 from '@/assets/images/merchant-home/icon_03.png'
import icon04 from '@/assets/images/merchant-home/icon_04.png'
import icon05 from '@/assets/images/merchant-home/icon_05.png'
import icon06 from '@/assets/images/merchant-home/icon_06.png'
import icon07 from '@/assets/images/merchant-home/icon_07.png'

defineOptions({
  name: 'Index'
})

const router = useRouter()

const CARD_ICONS = {
  visitors: 'el-icon-user',
  buyers: 'el-icon-user-solid',
  orders: 'el-icon-document-checked',
  amount: 'el-icon-wallet',
  refund: 'el-icon-refresh-left',
  avg: 'el-icon-data-line'
}

const QUICK_ACTION_IMAGES = {
  ContentArticleCreate: icon01,
  SpecialManage: icon02,
  MerchantOrderList: icon03,
  MerchantFinanceWithdraw: icon04,
  MerchantCouponList: icon05,
  MerchantLiveRoomList: icon06,
  SettingMerchant: icon07
}

function padDate(value) {
  return value < 10 ? '0' + value : String(value)
}

function createDateRange(days) {
  const result = []
  const end = new Date()
  const start = new Date(end.getFullYear(), end.getMonth(), end.getDate())
  start.setDate(start.getDate() - Math.max(0, days - 1))

  for (let index = 0; index < days; index++) {
    const current = new Date(start.getFullYear(), start.getMonth(), start.getDate())
    current.setDate(start.getDate() + index)
    result.push([
      current.getFullYear(),
      padDate(current.getMonth() + 1),
      padDate(current.getDate())
    ].join('-'))
  }

  return result
}

function parseDateString(value) {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value || ''))
  if (!match) {
    return null
  }

  const year = Number(match[1])
  const month = Number(match[2])
  const day = Number(match[3])
  const date = new Date(year, month - 1, day)
  if (
    date.getFullYear() !== year ||
    date.getMonth() !== month - 1 ||
    date.getDate() !== day
  ) {
    return null
  }

  return date
}

function createDateRangeBetween(startValue, endValue) {
  let start = parseDateString(startValue)
  let end = parseDateString(endValue)
  if (!start || !end) {
    return []
  }

  if (start.getTime() > end.getTime()) {
    const temp = start
    start = end
    end = temp
  }

  const result = []
  const maxDays = 366
  for (let index = 0; index < maxDays; index++) {
    const current = new Date(start.getFullYear(), start.getMonth(), start.getDate())
    current.setDate(start.getDate() + index)
    if (current.getTime() > end.getTime()) {
      break
    }

    result.push([
      current.getFullYear(),
      padDate(current.getMonth() + 1),
      padDate(current.getDate())
    ].join('-'))
  }

  return result
}

function createDefaultDashboard(range, dateRange) {
  const customDates = Array.isArray(dateRange) && dateRange.length === 2
    ? createDateRangeBetween(dateRange[0], dateRange[1])
    : []
  const dates = customDates.length ? customDates : createDateRange(range || 7)
  return {
    merchant: {
      id: 0,
      name: '',
      description: '',
      business_status: 1,
      business_status_text: '营业中',
      business_hours_text: '09:00-18:00',
      mer_class_text: '--'
    },
    cards: [
      { key: 'visitors', title: '访客数', value: 0, unit: '', compare_text: '较昨日 --', trend: 'flat' },
      { key: 'buyers', title: '下单买家数', value: 0, unit: '', compare_text: '较昨日 --', trend: 'flat' },
      { key: 'orders', title: '订单数量', value: 0, unit: '', compare_text: '较昨日 --', trend: 'flat' },
      { key: 'amount', title: '成交金额', value: 0, unit: '¥', compare_text: '较昨日 --', trend: 'flat' },
      { key: 'refund', title: '退款金额', value: 0, unit: '¥', compare_text: '较昨日 --', trend: 'flat' },
      { key: 'avg', title: '客单价', value: 0, unit: '¥', compare_text: '较昨日 --', trend: 'flat' }
    ],
    quick_actions: [
      { title: '发布内容', icon: 'el-icon-edit-outline', type: 'primary', route: 'ContentArticleCreate' },
      { title: '专题管理', icon: 'el-icon-notebook-2', type: 'primary', route: 'SpecialManage' },
      { title: '订单管理', icon: 'el-icon-s-order', type: 'info', route: 'MerchantOrderList' },
      { title: '资金提现', icon: 'el-icon-s-finance', type: 'warning', route: 'MerchantFinanceWithdraw' },
      { title: '优惠券', icon: 'el-icon-ticket', type: 'success', route: 'MerchantCouponList' },
      { title: '直播管理', icon: 'el-icon-video-camera', type: 'danger', route: 'MerchantLiveRoomList' },
      { title: '店铺设置', icon: 'el-icon-setting', type: 'info', route: 'SettingMerchant' }
    ],
    order_trend: {
      dates,
      amounts: dates.map(() => 0),
      counts: dates.map(() => 0)
    },
    user_overview: {
      new_users: 0,
      active_users: 0,
      repeat_users: 0,
      conversion_rate: 0,
      trend: dates.map(date => ({ date, value: 0 }))
    },
    content_coupon: {
      special_count: 0,
      special_buy_count: 0,
      special_conversion_rate: 0,
      coupon_received: 0,
      coupon_used: 0,
      coupon_writeoff_rate: 0,
      coupon_writeoff_amount: 0
    }
  }
}

const pageLoading = ref(false)
const rangeLoading = ref(false)
const dashboardLoaded = ref(false)
let dashboardRequestId = 0
const range = ref(7)
const dateRange = ref([])
const pickerOptions = {
  disabledDate(time) {
    return time.getTime() > Date.now()
  }
}
const dashboard = ref(createDefaultDashboard(7))
const orderChartEl = ref(null)
const userChartEl = ref(null)
let orderChart = null
let userChart = null

const merchant = computed(() => {
  return dashboard.value.merchant || {}
})
const contentCoupon = computed(() => {
  return dashboard.value.content_coupon || {}
})
const isCustomRange = computed(() => {
  return Array.isArray(dateRange.value) && dateRange.value.length === 2
})
const rangeTitle = computed(() => {
  if (isCustomRange.value) {
    return dateRange.value[0] + ' ~ ' + dateRange.value[1]
  }
  return range.value === 30 ? '近30天' : '近7天'
})
const userOverviewItems = computed(() => {
  const overview = dashboard.value.user_overview || {}
  return [
    { key: 'new_users', label: '新增用户', value: overview.new_users, format: 'number' },
    { key: 'active_users', label: '活跃用户', value: overview.active_users, format: 'number' },
    { key: 'repeat_users', label: '复购用户', value: overview.repeat_users, format: 'number' },
    { key: 'conversion_rate', label: '用户转化率', value: overview.conversion_rate, format: 'percent' }
  ]
})

onMounted(() => {
  loadDashboard()
  window.addEventListener('resize', handleResize)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', handleResize)
  if (orderChart) {
    orderChart.dispose()
    orderChart = null
  }
  if (userChart) {
    userChart.dispose()
    userChart = null
  }
})

function loadDashboard(options = {}) {
  const requestId = dashboardRequestId + 1
  const useRangeLoading = options.rangeOnly === true && dashboardLoaded.value
  dashboardRequestId = requestId

  if (useRangeLoading) {
    pageLoading.value = false
    rangeLoading.value = true
  } else {
    pageLoading.value = true
    rangeLoading.value = false
  }

  getMerchantDashboardHome(getDashboardParams()).then(res => {
    if (requestId !== dashboardRequestId) {
      return
    }

    const data = res && res.data ? res.data : {}
    dashboard.value = useRangeLoading
      ? mergeRangeDashboard(data)
      : normalizeDashboard(data)
    nextTick(() => {
      renderCharts()
    })
  }).catch(() => {
    if (requestId !== dashboardRequestId) {
      return
    }

    dashboard.value = useRangeLoading
      ? createRangeFallbackDashboard()
      : createDefaultDashboard(range.value, dateRange.value)
    nextTick(() => {
      renderCharts()
    })
  }).finally(() => {
    if (requestId !== dashboardRequestId) {
      return
    }

    pageLoading.value = false
    rangeLoading.value = false
    dashboardLoaded.value = true
  })
}

function normalizeDashboard(data) {
  const defaults = createDefaultDashboard(range.value, dateRange.value)
  const source = data && typeof data === 'object' ? data : {}
  const orderTrend = source.order_trend && typeof source.order_trend === 'object'
    ? source.order_trend
    : defaults.order_trend
  const userOverview = source.user_overview && typeof source.user_overview === 'object'
    ? source.user_overview
    : defaults.user_overview

  return {
    merchant: Object.assign({}, defaults.merchant, source.merchant || {}),
    cards: Array.isArray(source.cards) ? source.cards : defaults.cards,
    quick_actions: Array.isArray(source.quick_actions) ? source.quick_actions : defaults.quick_actions,
    order_trend: {
      dates: Array.isArray(orderTrend.dates) ? orderTrend.dates : defaults.order_trend.dates,
      amounts: Array.isArray(orderTrend.amounts) ? orderTrend.amounts : defaults.order_trend.amounts,
      counts: Array.isArray(orderTrend.counts) ? orderTrend.counts : defaults.order_trend.counts
    },
    user_overview: Object.assign({}, defaults.user_overview, userOverview),
    content_coupon: Object.assign({}, defaults.content_coupon, source.content_coupon || {})
  }
}

function mergeRangeDashboard(data) {
  const normalized = normalizeDashboard(data)

  return Object.assign({}, dashboard.value, {
    order_trend: normalized.order_trend,
    user_overview: normalized.user_overview
  })
}

function createRangeFallbackDashboard() {
  const defaults = createDefaultDashboard(range.value, dateRange.value)

  return Object.assign({}, dashboard.value, {
    order_trend: defaults.order_trend,
    user_overview: defaults.user_overview
  })
}

function getDashboardParams() {
  if (isCustomRange.value) {
    return {
      start_time: dateRange.value[0],
      end_time: dateRange.value[1]
    }
  }

  return {
    range: range.value
  }
}

function handleRangeChange(value) {
  if (!isCustomRange.value && range.value === value) {
    return
  }
  range.value = value
  dateRange.value = []
  loadDashboard({ rangeOnly: true })
}

function handleDateChange(value) {
  if (Array.isArray(value) && value.length === 2) {
    dateRange.value = value
  } else {
    dateRange.value = []
  }

  loadDashboard({ rangeOnly: true })
}

function handleQuickAction(item) {
  if (!item.route) {
    ElMessage.info('该入口暂未配置页面')
    return
  }

  const resolved = router.resolve({ name: item.route })
  if (!resolved.route || !resolved.route.matched || resolved.route.matched.length === 0) {
    ElMessage.info('该入口暂未配置页面')
    return
  }

  router.push({ name: item.route }).catch(error => {
    if (error && error.name !== 'NavigationDuplicated') {
      console.error(error)
    }
  })
}

function renderCharts() {
  renderOrderChart()
  renderUserChart()
  handleResize()
}

function renderOrderChart() {
  if (!orderChartEl.value) {
    return
  }
  if (!orderChart) {
    orderChart = echarts.init(orderChartEl.value)
  }

  const trend = dashboard.value.order_trend || {}
  const dates = Array.isArray(trend.dates) ? trend.dates : []
  const amounts = Array.isArray(trend.amounts) ? trend.amounts : []
  const counts = Array.isArray(trend.counts) ? trend.counts : []
  const primary = getThemeColor('--brand-primary')
  const secondary = getThemeColor('--text-placeholder')
  const textSecondary = getThemeColor('--text-secondary')
  const outline = getThemeColor('--layout-outline')

  orderChart.setOption({
    color: [primary, secondary],
    grid: {
      top: 42,
      right: 42,
      bottom: 36,
      left: 56
    },
    tooltip: {
      trigger: 'axis',
      axisPointer: {
        type: 'line',
        lineStyle: {
          color: outline
        }
      },
      formatter: params => {
        const lines = params.map(item => {
          const value = item.seriesName === '成交金额'
            ? '¥' + formatMoney(item.value)
            : formatNumber(item.value)
          return item.marker + item.seriesName + '：' + value
        })
        return [params[0].axisValue].concat(lines).join('<br/>')
      }
    },
    legend: {
      top: 0,
      data: ['成交金额', '订单数量'],
      textStyle: {
        color: textSecondary
      }
    },
    xAxis: {
      type: 'category',
      boundaryGap: false,
      data: dates,
      axisLine: {
        lineStyle: {
          color: outline
        }
      },
      axisTick: {
        show: false
      },
      axisLabel: {
        color: textSecondary,
        formatter: value => String(value).slice(5)
      }
    },
    yAxis: [
      {
        type: 'value',
        name: '金额',
        axisLine: {
          show: false
        },
        axisTick: {
          show: false
        },
        splitLine: {
          lineStyle: {
            color: outline
          }
        },
        axisLabel: {
          color: textSecondary
        },
        nameTextStyle: {
          color: textSecondary
        }
      },
      {
        type: 'value',
        name: '订单',
        axisLine: {
          show: false
        },
        axisTick: {
          show: false
        },
        splitLine: {
          show: false
        },
        axisLabel: {
          color: textSecondary
        },
        nameTextStyle: {
          color: textSecondary
        }
      }
    ],
    series: [
      {
        name: '成交金额',
        type: 'line',
        smooth: true,
        symbol: 'circle',
        symbolSize: 6,
        yAxisIndex: 0,
        data: amounts,
        areaStyle: {
          color: primary,
          opacity: 0.14
        },
        lineStyle: {
          width: 3
        }
      },
      {
        name: '订单数量',
        type: 'line',
        smooth: true,
        symbol: 'circle',
        symbolSize: 5,
        yAxisIndex: 1,
        data: counts,
        lineStyle: {
          width: 2
        }
      }
    ]
  }, true)
}

function renderUserChart() {
  if (!userChartEl.value) {
    return
  }
  if (!userChart) {
    userChart = echarts.init(userChartEl.value)
  }

  const overview = dashboard.value.user_overview || {}
  const trend = Array.isArray(overview.trend) ? overview.trend : []
  const dates = trend.map(item => item.date)
  const values = trend.map(item => item.value)
  const primary = getThemeColor('--brand-primary')
  const textSecondary = getThemeColor('--text-secondary')
  const outline = getThemeColor('--layout-outline')

  userChart.setOption({
    color: [primary],
    grid: {
      top: 20,
      right: 12,
      bottom: 28,
      left: 42
    },
    tooltip: {
      trigger: 'axis',
      formatter: params => {
        const item = params[0] || {}
        return item.axisValue + '<br/>' + item.marker + '活跃用户：' + formatNumber(item.value)
      }
    },
    xAxis: {
      type: 'category',
      boundaryGap: false,
      data: dates,
      axisLine: {
        lineStyle: {
          color: outline
        }
      },
      axisTick: {
        show: false
      },
      axisLabel: {
        color: textSecondary,
        formatter: value => String(value).slice(5)
      }
    },
    yAxis: {
      type: 'value',
      axisLine: {
        show: false
      },
      axisTick: {
        show: false
      },
      splitLine: {
        lineStyle: {
          color: outline
        }
      },
      axisLabel: {
        color: textSecondary
      }
    },
    series: [
      {
        name: '活跃用户',
        type: 'line',
        smooth: true,
        symbol: 'circle',
        symbolSize: 5,
        data: values,
        areaStyle: {
          color: primary,
          opacity: 0.12
        },
        lineStyle: {
          width: 3
        }
      }
    ]
  }, true)
}

function handleResize() {
  if (orderChart) {
    orderChart.resize()
  }
  if (userChart) {
    userChart.resize()
  }
}

function getThemeColor(name) {
  const value = window.getComputedStyle(document.documentElement).getPropertyValue(name)
  return value ? value.trim() : '#999999'
}

function getCardIcon(key) {
  return CARD_ICONS[key] || 'el-icon-data-analysis'
}

function getQuickActionImage(item) {
  return QUICK_ACTION_IMAGES[item.route] || ''
}

function getTrendClass(trend) {
  if (trend === 'up') {
    return 'is-up'
  }
  if (trend === 'down') {
    return 'is-down'
  }
  return 'is-flat'
}

function formatCardValue(card) {
  if (card.unit === '¥') {
    return formatMoney(card.value)
  }
  return formatNumber(card.value)
}

function formatOverviewValue(item) {
  if (item.format === 'percent') {
    return formatPercent(item.value)
  }
  return formatNumber(item.value)
}

function formatMoney(value) {
  const number = toNumber(value)
  return number.toLocaleString('zh-CN', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
}

function formatNumber(value) {
  const number = toNumber(value)
  return Math.round(number).toLocaleString('zh-CN')
}

function formatPercent(value) {
  const number = toNumber(value)
  const text = number.toFixed(1)
  return text.replace('.0', '') + '%'
}

function circleStyle(value) {
  return {
    '--ring-value': Math.max(0, Math.min(100, toNumber(value))) + '%'
  }
}

function toNumber(value) {
  const number = Number(value)
  return Number.isFinite(number) ? number : 0
}
</script>

<style lang="scss" scoped>
.merchant-dashboard {
  min-height: calc(100vh - 84px);
  background: var(--layout-background);
  color: var(--text-primary);
}

.dashboard-hero {
  position: relative;
  display: flex;
  overflow: hidden;
  border: 1px solid var(--layout-outline);
  border-radius: 8px;
  background:
    linear-gradient(135deg, var(--layout-surface-card) 0%, var(--brand-primary-pale) 100%);
  box-shadow: 0 12px 28px var(--brand-shadow-card);
}

.dashboard-hero__content {
  position: relative;
  z-index: 1;
  flex: 1;
  min-width: 0;
  padding: 10px 48px;

  h1 {
    margin: 0 0 18px;
    color: var(--text-title);
    font-size: 28px;
    font-weight: 700;
    line-height: 1.3;
    letter-spacing: 0;
  }

  p {
    max-width: 640px;
    margin: 18px 0 0;
    color: var(--text-regular);
    font-size: 14px;
    line-height: 1.8;
  }
}

.dashboard-hero__eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  height: 28px;
  margin-bottom: 12px;
  padding: 0 12px;
  border: 1px solid var(--layout-outline);
  border-radius: 14px;
  background: var(--layout-surface-card);
  color: var(--text-secondary);
  font-size: 13px;
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--text-placeholder);

  &.is-open {
    background: var(--text-success);
  }

  &.is-rest {
    background: var(--text-warning);
  }
}

.dashboard-hero__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;

  span {
    display: inline-flex;
    align-items: center;
    min-height: 28px;
    padding: 0 12px;
    border: 1px solid var(--layout-outline-ghost);
    border-radius: 14px;
    background: var(--layout-surface-card);
    color: var(--text-secondary);
    font-size: 13px;
    line-height: 1.4;
  }
}

.dashboard-hero__art {
  position: relative;
  flex: 0 0 320px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 10px 42px 10px 12px;
}

.shop-illustration {
  display: block;
  width: 100%;
  max-width: 340px;
  height: auto;
  object-fit: contain;
  filter: drop-shadow(0 14px 24px var(--brand-shadow-card));
}

.metric-grid {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 16px;
  margin-top: 10px;
}

.metric-card {
  min-width: 0;
  min-height: 128px;
  padding: 20px;
  border: 1px solid var(--layout-outline);
  border-radius: 8px;
  background: var(--layout-surface-card);
  box-shadow: 0 10px 24px var(--brand-shadow-card);
}

.metric-card__top {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.metric-card__icon {
  display: inline-flex;
  flex: 0 0 34px;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border-radius: 8px;
  background: var(--brand-primary-pale);
  color: var(--brand-primary-dark);
  font-size: 18px;
}

.metric-card__title {
  overflow: hidden;
  color: var(--text-secondary);
  font-size: 14px;
  line-height: 20px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.metric-card__value {
  display: flex;
  align-items: baseline;
  min-height: 38px;
  margin-top: 16px;
  color: var(--text-title);
  font-size: 28px;
  font-weight: 700;
  line-height: 1;
  letter-spacing: 0;
}

.metric-card__currency {
  margin-right: 4px;
  font-size: 18px;
}

.metric-card__unit {
  margin-left: 4px;
  color: var(--text-secondary);
  font-size: 13px;
  font-weight: 500;
}

.metric-card__compare {
  margin-top: 14px;
  color: var(--text-secondary);
  font-size: 13px;

  &.is-up {
    color: var(--text-success);
  }

  &.is-down {
    color: var(--text-danger);
  }
}

.quick-actions {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 12px;
  margin-top: 10px;
  padding: 14px;
  border: 1px solid var(--layout-outline);
  border-radius: 8px;
  background: var(--layout-surface-card);
  box-shadow: 0 10px 24px var(--brand-shadow-card);
}

.quick-action {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: 8px;
  min-width: 0;
  height: 112px;
  padding: 10px 8px;
  border: 1px solid transparent;
  border-radius: 8px;
  background: var(--layout-background);
  color: var(--text-primary);
  font-size: 14px;
  line-height: 20px;
  cursor: pointer;
  transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;

  &:hover {
    border-color: var(--brand-primary);
    box-shadow: 0 8px 18px var(--brand-shadow-card);
    transform: translateY(-1px);

    .quick-action__icon img {
      transform: scale(1.04);
    }
  }
}

.quick-action__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 64px;
  width: 64px;
  height: 64px;
  overflow: hidden;
  border-radius: 14px;
  background: var(--brand-primary-pale);
  color: var(--brand-primary-dark);
  font-size: 24px;

  img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: contain;
    transition: transform 0.2s ease;
  }
}

.quick-action > span:last-child {
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dashboard-panel {
  border: 1px solid var(--layout-outline);
  border-radius: 8px;
  background: var(--layout-surface-card);
  box-shadow: 0 10px 24px var(--brand-shadow-card);
}

.dashboard-panel__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 22px 24px 0;

  h2 {
    margin: 0;
    color: var(--text-title);
    font-size: 18px;
    font-weight: 700;
    line-height: 1.4;
    letter-spacing: 0;
  }

  p {
    margin: 4px 0 0;
    color: var(--text-secondary);
    font-size: 13px;
  }
}

.trend-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
}

.range-switch {
  flex: 0 0 auto;
}

.custom-range-picker {
  flex: 0 0 260px;
  width: 260px;

  :deep(.el-range-input) {
    color: var(--text-primary);
  }

  :deep(.el-range-separator),
  :deep(.el-range__icon) {
    color: var(--text-secondary);
  }
}

.trend-panel {
  margin-top: 10px;
}

.order-chart {
  width: 100%;
  height: 360px;
  padding: 0 12px 8px;
}

.overview-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1.12fr);
  gap: 18px;
  margin-top: 10px;
}

.user-overview {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
  padding: 18px 24px 0;
}

.user-overview__item {
  min-width: 0;
  padding-right: 12px;
  border-right: 1px solid var(--layout-outline);

  &:last-child {
    border-right: 0;
  }

  span {
    display: block;
    color: var(--text-secondary);
    font-size: 13px;
  }

  strong {
    display: block;
    margin-top: 10px;
    color: var(--text-title);
    font-size: 22px;
    line-height: 1.2;
  }
}

.user-chart {
  width: 100%;
  height: 220px;
  padding: 4px 12px 10px;
}

.content-coupon-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 1px minmax(0, 1fr);
  gap: 24px;
  padding: 22px 24px 24px;
}

.content-coupon-divider {
  background: var(--layout-outline);
}

.content-coupon-block {
  min-width: 0;
}

.block-title {
  margin-bottom: 18px;
  color: var(--text-primary);
  font-size: 15px;
  font-weight: 700;
}

.block-stat {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
  color: var(--text-secondary);
  font-size: 13px;

  strong {
    color: var(--text-title);
    font-size: 22px;
    line-height: 1.2;
  }
}

.ring-row {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-top: 18px;

  strong {
    display: block;
    color: var(--text-title);
    font-size: 15px;
    line-height: 1.4;
  }

  p {
    margin: 6px 0 0;
    color: var(--text-secondary);
    font-size: 13px;
  }
}

.ring {
  position: relative;
  display: flex;
  flex: 0 0 92px;
  align-items: center;
  justify-content: center;
  width: 92px;
  height: 92px;
  border-radius: 50%;
  background:
    conic-gradient(var(--brand-primary) var(--ring-value), var(--layout-outline) 0);

  &::after {
    position: absolute;
    inset: 12px;
    border-radius: 50%;
    background: var(--layout-surface-card);
    content: '';
  }

  span {
    position: relative;
    z-index: 1;
    color: var(--text-title);
    font-size: 18px;
    font-weight: 700;
  }
}

@media (max-width: 1440px) {
  .metric-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .quick-actions {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}

@media (max-width: 1100px) {
  .dashboard-hero {
    flex-direction: column;
  }

  .dashboard-hero__art {
    flex-basis: auto;
    width: 100%;
    padding-top: 0;
  }

  .overview-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .dashboard-hero__content {
    padding: 28px 22px;

    h1 {
      font-size: 22px;
    }
  }

  .dashboard-hero__art {
    display: none;
  }

  .metric-grid,
  .user-overview,
  .content-coupon-layout {
    grid-template-columns: 1fr;
  }

  .quick-actions {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .dashboard-panel__header {
    align-items: flex-start;
    flex-direction: column;
  }

  .trend-toolbar {
    justify-content: flex-start;
    width: 100%;
  }

  .custom-range-picker {
    flex: 1 1 100%;
    width: 100%;
  }

  .content-coupon-divider {
    display: none;
  }

  .user-overview__item {
    border-right: 0;
    border-bottom: 1px solid var(--layout-outline);
    padding: 0 0 12px;

    &:last-child {
      border-bottom: 0;
    }
  }
}

@media (max-width: 480px) {
  .quick-actions {
    grid-template-columns: 1fr;
  }
}
</style>
