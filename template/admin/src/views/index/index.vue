<template>
  <div class="dashboard-page">
    <el-alert
      v-if="errorText"
      :title="errorText"
      type="error"
      show-icon
      class="dashboard-page__alert"
    />

    <section v-loading="loading" class="dashboard-metrics">
      <div class="dashboard-metrics__grid">
        <metric-card
          v-for="card in cards"
          :key="card.key"
          v-bind="card"
          :clickable="hasRouteTarget(getMetricRoute(card))"
          class="dashboard-metrics__item"
          @click="handleMetricCardClick(card)"
        />
      </div>
    </section>

    <section class="dashboard-section dashboard-section--chart">
      <div class="dashboard-section__header dashboard-section__header--chart">
        <div>
          <h3 class="dashboard-section__title">交易趋势分析</h3>
          <p class="dashboard-section__desc">{{ dateRangeText }}</p>
        </div>
        <div class="dashboard-section__tools">
          <el-radio-group v-model="query.range" size="mini" class="dashboard-range" @change="handleRangeChange">
            <el-radio-button v-for="item in rangeOptions" :key="item.value" :label="item.value">
              {{ item.label }}
            </el-radio-button>
          </el-radio-group>

          <el-date-picker
            v-if="query.range === 'custom'"
            v-model="customDate"
            :editable="false"
            type="daterange"
            size="mini"
            value-format="yyyy-MM-dd"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            class="dashboard-custom-date"
            @change="handleCustomDateChange"
          />

          <div class="dashboard-switcher" role="tablist" aria-label="趋势指标切换">
            <button
              type="button"
              :class="['dashboard-switcher__btn', { 'is-active': trendMetric === 'amount' }]"
              @click="handleTrendMetricChange('amount')"
            >
              金额
            </button>
            <button
              type="button"
              :class="['dashboard-switcher__btn', { 'is-active': trendMetric === 'orders' }]"
              @click="handleTrendMetricChange('orders')"
            >
              订单
            </button>
          </div>

          <el-button
            :loading="loading"
            type="primary"
            size="mini"
            icon="el-icon-refresh"
            class="dashboard-refresh"
            @click="handleRefresh"
          >
            刷新
          </el-button>
        </div>
      </div>

      <el-empty v-if="isTrendEmpty" :image-size="100" description="当前时间范围暂无趋势数据" />
      <div v-show="!isTrendEmpty" ref="trendChartRef" class="dashboard-chart" />
    </section>

    <section class="dashboard-ranking-grid">
      <ranking-list
        v-for="item in rankingSections"
        :key="item.key"
        :title="item.title"
        :list="item.list"
        :unit="item.unit"
        :extra-label="item.extraLabel"
        :value-label="item.valueLabel"
        :name-label="item.nameLabel"
        :action-text="item.actionText"
        :clickable="hasRouteTarget(getRankingRowRoute(item))"
        :action-clickable="hasRouteTarget(getRankingActionRoute(item))"
        @action-click="handleRankingActionClick(item)"
        @row-click="handleRankingRowClick(item, $event)"
      />
    </section>

    <section v-loading="pendingLoading" class="dashboard-section dashboard-section--alerts">
      <div class="dashboard-section__header dashboard-section__header--alerts">
        <div class="dashboard-alert-title">
          <span class="dashboard-alert-title__icon"><LegacyIcon name="el-icon-warning-outline" /></span>
          <div>
            <h3 class="dashboard-section__title">系统实时预警</h3>
            <p class="dashboard-section__desc">更新于 {{ pendingUpdatedAt || '--' }} · 每 30 秒自动刷新</p>
          </div>
        </div>
        <div class="dashboard-alert-summary">
          <span class="dashboard-alert-summary__tag is-danger">紧急：{{ urgentPendingCount }}</span>
          <span class="dashboard-alert-summary__tag is-warning">待处理：{{ totalPendingCount }}</span>
        </div>
      </div>

      <div v-if="pendingCards.length" class="dashboard-alert-grid">
        <article
          v-for="item in pendingCards"
          :key="item.key"
          :class="['dashboard-alert-card', { 'is-clickable': hasRouteTarget(getPendingRoute(item)) }]"
          :role="hasRouteTarget(getPendingRoute(item)) ? 'button' : null"
          :tabindex="hasRouteTarget(getPendingRoute(item)) ? 0 : -1"
          @click="handlePendingCardClick(item)"
          @keyup.enter="handlePendingCardClick(item)"
        >
          <div class="dashboard-alert-card__icon-wrap">
            <span class="dashboard-alert-card__icon"><LegacyIcon :name="item.icon" /></span>
            <span class="dashboard-alert-card__badge">{{ item.value }}</span>
          </div>
          <div class="dashboard-alert-card__content">
            <div class="dashboard-alert-card__title">{{ item.label }}</div>
            <div class="dashboard-alert-card__meta">{{ item.hint }}</div>
          </div>
        </article>
      </div>
      <el-empty v-else :image-size="80" description="暂无待处理事项" />
    </section>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import echarts from 'echarts'
import { getPlatformDashboardOverview, getPlatformDashboardPending } from '@/api/dashboard'
import MetricCard from './components/MetricCard.vue'
import RankingList from './components/RankingList.vue'

defineOptions({
  name: 'Index'
})

const router = useRouter()
const loading = ref(false)
const errorText = ref('')
const trendMetric = ref('amount')
const query = reactive({ range: '30day', start_time: '', end_time: '' })
const customDate = ref([])
const rangeOptions = [
  { label: '近7天', value: '7day' },
  { label: '近30天', value: '30day' },
  { label: '本月', value: 'month' },
  { label: '本年', value: 'year' },
  { label: '自定义', value: 'custom' }
]
const cards = ref([])
const trend = reactive({ xAxis: [], series: [] })
const rankings = ref([])
const pending = ref([])
const pendingLoading = ref(false)
const pendingUpdatedAt = ref('')
const range = reactive({})
const trendChartRef = ref(null)
const pendingPollTimer = ref(null)

const activeTrendSeries = computed(() => {
  const series = trend.series || []
  const targetName = trendMetric.value === 'amount' ? '成交额' : '订单数'
  return series.find(item => item.name === targetName) || series[0] || { name: targetName, data: [] }
})

const isTrendEmpty = computed(() => {
  const data = activeTrendSeries.value.data || []
  return !data.length || data.every(value => Number(value || 0) === 0)
})

const dateRangeText = computed(() => {
  if (!range.start_date || !range.end_date) return '当前统计周期'
  return `${range.start_date} 至 ${range.end_date}`
})

const rankingSections = computed(() => (rankings.value || []).map(item => ({
  ...item,
  extraLabel: item.key === 'merchant' ? '订单数' : '点赞数',
  valueLabel: item.key === 'merchant' ? '交易额' : '浏览量',
  nameLabel: item.key === 'merchant' ? '商户名称' : '内容名称',
  actionText: item.key === 'merchant' ? '查看商户' : '查看内容'
})))

const pendingCards = computed(() => {
  const hintMap = {
    merchant_audit: '待审核入驻资质',
    content_audit: '内容审核等待平台确认',
    refund_pending: '超过 24 小时未响应的退款申请'
  }
  const iconMap = {
    merchant_audit: 'el-icon-s-check',
    content_audit: 'el-icon-document',
    refund_pending: 'el-icon-refresh'
  }
  return (pending.value || []).map(item => ({
    ...item,
    hint: hintMap[item.key] || '需要平台管理员尽快处理',
    icon: iconMap[item.key] || 'el-icon-warning-outline'
  }))
})

const urgentPendingCount = computed(() => pendingCards.value
  .filter(item => item.type === 'danger')
  .reduce((total, item) => total + Number(item.value || 0), 0))

const totalPendingCount = computed(() => pendingCards.value
  .reduce((total, item) => total + Number(item.value || 0), 0))

function assignReactiveObject(target, source) {
  Object.keys(target).forEach((key) => delete target[key])
  Object.assign(target, source)
}

async function fetchDashboard() {
  loading.value = true
  errorText.value = ''
  try {
    const res = await getPlatformDashboardOverview(query)
    const data = res && res.data ? res.data : {}
    cards.value = data.cards || []
    assignReactiveObject(trend, data.trend || { xAxis: [], series: [] })
    rankings.value = data.rankings || []
    assignReactiveObject(range, data.range || {})
    nextTick(() => renderTrendChart())
  } catch (error) {
    errorText.value = error && error.message ? error.message : '首页统计数据加载失败，请稍后重试'
  } finally {
    loading.value = false
  }
}

async function fetchPending() {
  if (pendingLoading.value) return
  pendingLoading.value = true
  try {
    const res = await getPlatformDashboardPending()
    const data = res && res.data ? res.data : {}
    pending.value = data.pending || []
    pendingUpdatedAt.value = data.updated_at || ''
  } catch (error) {
    if (!pending.value.length) {
      pending.value = []
    }
  } finally {
    pendingLoading.value = false
  }
}

async function handleRefresh() {
  await Promise.all([fetchDashboard(), fetchPending()])
}

function startPendingPolling() {
  clearPendingPolling()
  pendingPollTimer.value = setInterval(() => {
    void fetchPending()
  }, 30000)
}

function clearPendingPolling() {
  if (!pendingPollTimer.value) return
  clearInterval(pendingPollTimer.value)
  pendingPollTimer.value = null
}

function hasRouteTarget(target) {
  return !!(target && (target.name || target.path))
}

function navigateTo(target) {
  if (!hasRouteTarget(target)) return
  router.push(target).catch(() => {})
}

function getMetricRoute(card) {
  const routeMap = {
    total_revenue: { name: 'OrderList' },
    today_revenue: { name: 'OrderList' },
    month_orders: { name: 'OrderList' },
    platform_commission: { name: 'FinanceBill', query: { category: 'platform' }},
    merchant_count: { name: 'MerchantList' },
    user_count: { name: 'UserList' },
    content_count: { name: 'ArticleList' },
    product_count: { name: 'product_list' }
  }
  return routeMap[card.key] || null
}

function handleMetricCardClick(card) {
  navigateTo(getMetricRoute(card))
}

function getRankingActionRoute(section) {
  const routeMap = {
    merchant: { name: 'MerchantList' },
    content: { name: 'ArticleList' }
  }
  return routeMap[section.key] || null
}

function getRankingRowRoute(section, row) {
  if (section.key === 'merchant') {
    return row && row.name
      ? { name: 'MerchantList', query: { mer_name: row.name }}
      : { name: 'MerchantList' }
  }
  if (section.key === 'content') {
    return row && row.name
      ? { name: 'ArticleList', query: { title: row.name }}
      : { name: 'ArticleList' }
  }
  return null
}

function handleRankingActionClick(section) {
  navigateTo(getRankingActionRoute(section))
}

function handleRankingRowClick(section, row) {
  navigateTo(getRankingRowRoute(section, row))
}

function getPendingRoute(item) {
  const routeMap = {
    merchant_audit: { name: 'MerchantApply', query: { audit_status: '0' }},
    content_audit: { name: 'ArticleList', query: { tab: 'merchant', audit_status: '0' }},
    refund_pending: { name: 'OrderRefundList', query: { refund_status: '1' }}
  }
  return routeMap[item.key] || null
}

function handlePendingCardClick(item) {
  navigateTo(getPendingRoute(item))
}

function handleRangeChange(value) {
  if (value !== 'custom') {
    customDate.value = []
    query.start_time = ''
    query.end_time = ''
    void fetchDashboard()
  }
}

function handleCustomDateChange(value) {
  if (!value || value.length !== 2) return
  query.start_time = value[0]
  query.end_time = value[1]
  void fetchDashboard()
}

function handleTrendMetricChange(type) {
  if (trendMetric.value === type) return
  trendMetric.value = type
  nextTick(() => renderTrendChart())
}

function getThemeColor(name, fallback) {
  if (typeof window === 'undefined' || typeof document === 'undefined') return fallback
  const value = window.getComputedStyle(document.documentElement).getPropertyValue(name)
  return value ? value.trim() : fallback
}

function renderTrendChart() {
  if (!trendChartRef.value || isTrendEmpty.value) return
  if (!trendChartRef.value.__echarts_instance__) {
    trendChartRef.value.__echarts_instance__ = echarts.init(trendChartRef.value)
  }

  const chart = trendChartRef.value.__echarts_instance__
  const chartData = activeTrendSeries.value.data || []
  const xAxis = (trend.xAxis || []).map(item => item.slice(5))
  const maxValue = Math.max(...chartData, 0)
  const accentColor = getThemeColor('--dashboard-accent', '#f34100')
  const barColor = getThemeColor('--dashboard-surface-muted', '#ece8e4')
  const borderColor = getThemeColor('--dashboard-border-soft', '#f0ece8')
  const textMuted = getThemeColor('--dashboard-text-muted', '#8f8a86')
  const tooltipBg = getThemeColor('--dashboard-surface', '#ffffff')
  const valueLabel = trendMetric.value === 'amount' ? '成交额' : '订单数'
  const unit = trendMetric.value === 'amount' ? '元' : '单'

  chart.setOption({
    animationDuration: 300,
    grid: {
      top: 16,
      left: 8,
      right: 8,
      bottom: 26,
      containLabel: true
    },
    tooltip: {
      trigger: 'axis',
      backgroundColor: tooltipBg,
      borderColor: borderColor,
      borderWidth: 1,
      textStyle: { color: getThemeColor('--dashboard-text-primary', '#1a1c1c') },
      formatter: params => {
        const target = Array.isArray(params) ? params[0] : params
        if (!target) return ''
        return `${target.axisValue}<br/>${valueLabel}：${Number(target.value || 0).toLocaleString('zh-CN')}${unit}`
      }
    },
    xAxis: {
      type: 'category',
      data: xAxis,
      axisTick: { show: false },
      axisLine: { lineStyle: { color: borderColor }},
      axisLabel: {
        color: textMuted,
        margin: 12,
        interval: xAxis.length > 10 ? Math.ceil(xAxis.length / 5) - 1 : 0
      }
    },
    yAxis: {
      type: 'value',
      show: false
    },
    series: [
      {
        name: valueLabel,
        type: 'bar',
        data: chartData.map(value => ({
          value,
          itemStyle: {
            color: value === maxValue && maxValue > 0 ? accentColor : barColor,
            borderRadius: [8, 8, 0, 0]
          }
        })),
        barMaxWidth: 32,
        emphasis: {
          itemStyle: {
            color: accentColor
          }
        }
      }
    ]
  }, true)
}

function resizeCharts() {
  const chart = trendChartRef.value && trendChartRef.value.__echarts_instance__
  if (chart) chart.resize()
}

onMounted(() => {
  void fetchDashboard()
  void fetchPending()
  startPendingPolling()
  window.addEventListener('resize', resizeCharts)
})

onBeforeUnmount(() => {
  clearPendingPolling()
  window.removeEventListener('resize', resizeCharts)
  const chart = trendChartRef.value && trendChartRef.value.__echarts_instance__
  if (chart) chart.dispose()
})
</script>

<style lang="scss" scoped>
.dashboard-page {
  min-height: calc(100vh - 84px);
  background: var(--dashboard-bg, #f9f9f9);

  &__alert {
    margin-bottom: 16px;
  }
}

.dashboard-metrics {
  margin-bottom: 16px;

  &__grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
  }
}

.dashboard-section {
  margin-bottom: 16px;
  padding: 16px;
  border: 1px solid var(--dashboard-border, #e2e2e2);
  border-radius: 12px;
  background: var(--dashboard-surface, #ffffff);

  &__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 16px;
  }

  &__header--chart {
    align-items: center;
  }

  &__header--alerts {
    align-items: center;
  }

  &__title {
    margin: 0;
    color: var(--dashboard-text-primary, #1a1c1c);
    font-size: 16px;
    font-weight: 700;
    line-height: 24px;
  }

  &__desc {
    margin: 4px 0 0;
    color: var(--dashboard-text-muted, #8f8a86);
    font-size: 12px;
    line-height: 18px;
  }

  &__tools {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 8px;
  }
}

.dashboard-range {
  :deep(.el-radio-button__inner) {
    min-width: 52px;
    border: 1px solid var(--dashboard-border, #e2e2e2);
    background: var(--dashboard-surface, #ffffff);
    color: var(--dashboard-text-secondary, #6f6965);
    box-shadow: none;
  }

  :deep(.el-radio-button:first-child .el-radio-button__inner) {
    border-left: 1px solid var(--dashboard-border, #e2e2e2);
    border-radius: 8px 0 0 8px;
  }

  :deep(.el-radio-button:last-child .el-radio-button__inner) {
    border-radius: 0 8px 8px 0;
  }

  :deep(.el-radio-button__original-radio:checked + .el-radio-button__inner) {
    background: var(--dashboard-accent);
    border-color: var(--dashboard-accent);
    color: var(--dashboard-on-accent, #ffffff);
  }

  :deep(.el-radio-button__inner:hover) {
    color: var(--dashboard-accent, #f34100);
  }
}

.dashboard-custom-date {
  width: 240px;
}

.dashboard-switcher {
  display: inline-flex;
  align-items: center;
  padding: 3px;
  border-radius: 9px;
  background: var(--dashboard-surface-muted, #f3f3f3);

  &__btn {
    min-width: 54px;
    padding: 6px 12px;
    border: none;
    border-radius: 7px;
    background: transparent;
    color: var(--dashboard-text-secondary, #6f6965);
    font-size: 12px;
    font-weight: 700;
    line-height: 16px;
    cursor: pointer;
    transition: background-color 0.2s ease, color 0.2s ease;

    &.is-active {
      background: var(--dashboard-surface, #ffffff);
      color: var(--dashboard-text-primary, #1a1c1c);
      box-shadow: 0 1px 2px rgba(26, 28, 28, 0.08);
    }
  }
}

.dashboard-refresh {
  border-radius: 8px;
}

.dashboard-chart {
  height: 240px;
}

.dashboard-ranking-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 16px;
}

.dashboard-alert-title {
  display: flex;
  align-items: center;
  gap: 10px;

  &__icon {
    width: 28px;
    height: 28px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    color: var(--dashboard-accent, #f34100);
    background: rgba(243, 65, 0, 0.08);
  }
}

.dashboard-alert-summary {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 8px;

  &__tag {
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    line-height: 16px;

    &.is-danger {
      color: var(--dashboard-danger, #ba1a1a);
      background: rgba(186, 26, 26, 0.08);
    }

    &.is-warning {
      color: var(--dashboard-accent, #f34100);
      background: rgba(243, 65, 0, 0.08);
    }
  }
}

.dashboard-alert-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}

.dashboard-alert-card {
  position: relative;
  display: flex;
  align-items: center;
  gap: 14px;
  min-height: 84px;
  padding: 14px;
  border: 1px solid var(--dashboard-border, #e2e2e2);
  border-radius: 10px;
  background: var(--dashboard-surface-muted, #f3f3f3);
  transition: border-color 0.2s ease, background-color 0.2s ease, transform 0.2s ease;

  &.is-clickable:hover {
    border-color: var(--dashboard-accent, #f34100);
    background: var(--dashboard-surface, #ffffff);
    transform: translateY(-1px);
  }

  &__icon-wrap {
    position: relative;
    flex-shrink: 0;
  }

  &__icon {
    width: 44px;
    height: 44px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    border: 1px solid var(--dashboard-border, #e2e2e2);
    background: var(--dashboard-bg, #f9f9f9);
    color: var(--dashboard-text-secondary, #6f6965);
    font-size: 18px;
  }

  &__badge {
    position: absolute;
    top: -6px;
    right: -6px;
    min-width: 20px;
    height: 20px;
    padding: 0 5px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: var(--dashboard-accent, #f34100);
    color: #ffffff;
    font-size: 10px;
    font-weight: 700;
    line-height: 1;
    border: 2px solid var(--dashboard-surface-muted, #f3f3f3);
  }

  &__content {
    min-width: 0;
  }

  &__title {
    color: var(--dashboard-text-primary, #1a1c1c);
    font-size: 14px;
    font-weight: 700;
    line-height: 20px;
  }

  &__meta {
    margin-top: 4px;
    color: var(--dashboard-text-muted, #8f8a86);
    font-size: 11px;
    line-height: 16px;
  }
}

@media (max-width: 1280px) {
  .dashboard-metrics__grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .dashboard-alert-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 992px) {
  .dashboard-ranking-grid {
    grid-template-columns: 1fr;
  }

  .dashboard-section__header,
  .dashboard-section__header--chart,
  .dashboard-section__header--alerts {
    flex-direction: column;
    align-items: stretch;
  }

  .dashboard-section__tools,
  .dashboard-alert-summary {
    justify-content: flex-start;
  }
}

@media (max-width: 768px) {
  .dashboard-page {
    padding: 12px;
  }

  .dashboard-metrics__grid,
  .dashboard-alert-grid {
    grid-template-columns: 1fr;
  }

  .dashboard-custom-date {
    width: 100%;
  }

  .dashboard-section {
    padding: 14px;
  }

  .dashboard-chart {
    height: 220px;
  }
}
</style>
