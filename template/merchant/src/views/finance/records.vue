<template>
  <div class="finance-records-page">
    <header class="page-header">
      <div>
        <h1 class="page-title">资金流水</h1>
        <p class="page-subtitle">查看当前商户全部资金变动、冻结释放与提现扣减记录。</p>
      </div>
      <el-button type="primary" :icon="CreditCard" @click="goWithdraw">提现申请</el-button>
    </header>

    <section class="summary-grid">
      <article v-for="item in summaryCards" :key="item.key" class="summary-card">
        <div class="summary-card__top">
          <span class="summary-card__icon">
            <LegacyIcon :name="item.icon" />
          </span>
          <span>{{ item.label }}</span>
        </div>
        <strong>¥{{ formatMoney(item.value) }}</strong>
      </article>
    </section>

    <section class="panel panel--search">
      <el-form :model="searchForm" inline class="search-form" @submit.prevent>
        <el-form-item label="流水类型">
          <el-select v-model="searchForm.type" clearable placeholder="全部类型" class="search-select">
            <el-option
              v-for="item in typeOptions"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="收支方向">
          <el-select v-model="searchForm.change_type" clearable placeholder="全部" class="search-select-sm">
            <el-option label="收入" value="in" />
            <el-option label="支出" value="out" />
          </el-select>
        </el-form-item>
        <el-form-item label="资金状态">
          <el-select v-model="searchForm.fund_status" clearable placeholder="全部" class="search-select-sm">
            <el-option label="可提现" value="available" />
            <el-option label="冻结中" value="frozen" />
            <el-option label="已冲正" value="reversed" />
          </el-select>
        </el-form-item>
        <el-form-item label="创建时间">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="yyyy-MM-dd"

            class="date-picker"
            @change="handleDateChange"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
          <el-button :icon="Refresh" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </section>

    <section class="panel panel--table">
      <el-table
        v-loading="loading"
        :data="recordList"
        row-key="id"
        empty-text="暂无资金流水"
        class="finance-table"
      >
        <el-table-column prop="id" label="ID" width="80" align="center" />
        <el-table-column label="流水类型" min-width="130">
          <template #default="{ row }">
            <div class="table-main">{{ row.type_text || getTypeLabel(row.type) }}</div>
            <div class="table-sub">{{ row.type || '-' }}</div>
          </template>
        </el-table-column>
        <el-table-column label="收支金额" width="140" align="right">
          <template #default="{ row }">
            <span :class="['amount-text', row.change_type === 'in' ? 'is-income' : 'is-expense']">
              {{ row.change_type === 'in' ? '+' : '-' }}¥{{ formatMoney(row.amount) }}
            </span>
          </template>
        </el-table-column>
        <el-table-column label="资金状态" width="110" align="center">
          <template #default="{ row }">
            <el-tag :type="getFundStatusTag(row.fund_status)" >
              {{ row.fund_status_text || getFundStatusLabel(row.fund_status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="关联订单" min-width="150" show-overflow-tooltip>
          <template #default="{ row }">
            {{ row.order_sn || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="备注" min-width="220" show-overflow-tooltip>
          <template #default="{ row }">
            {{ row.remark || '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="create_time" label="创建时间" width="170" align="center" />
      </el-table>

      <div class="pagination-wrap">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="limit"
          :page-sizes="[15, 20, 50, 100]"
          layout="total, sizes, prev, pager, next, jumper"
          :total="total"
          @size-change="handleSizeChange"
          @current-change="handleCurrentChange"
        />
      </div>
    </section>
  </div>
</template>

<script setup>
import {
  getMerchantFinanceOverview,
  getMerchantFinanceRecords
} from '@/api/finance'
import { CreditCard, Refresh, Search } from '@element-plus/icons-vue'
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

defineOptions({
  name: 'MerchantFinanceRecords'
})

const router = useRouter()

const createSearchForm = () => ({
  type: '',
  change_type: '',
  fund_status: '',
  start_time: '',
  end_time: ''
})

const loading = ref(false)
const overviewLoading = ref(false)
const recordList = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(15)
const dateRange = ref([])
const overview = ref({
  balance: 0,
  frozen_amount: 0,
  total_income: 0,
  total_expense: 0,
  today_income: 0,
  today_expense: 0
})
const searchForm = ref(createSearchForm())
const typeOptions = [
  { label: '订单收入', value: 'order' },
  { label: '提现', value: 'withdraw' },
  { label: '退款', value: 'refund' },
  { label: '平台抽佣', value: 'commission' },
  { label: '平台券补贴', value: 'coupon_subsidy' },
  { label: '直播礼物商户抽成', value: 'live_gift_merchant' }
]

const summaryCards = computed(() => {
  return [
    { key: 'balance', label: '可提现余额', value: overview.value.balance, icon: 'el-icon-wallet' },
    { key: 'frozen_amount', label: '冻结金额', value: overview.value.frozen_amount, icon: 'el-icon-lock' },
    { key: 'total_income', label: '累计收入', value: overview.value.total_income, icon: 'el-icon-top-right' },
    { key: 'total_expense', label: '累计支出', value: overview.value.total_expense, icon: 'el-icon-bottom-left' },
    { key: 'today_income', label: '今日收入', value: overview.value.today_income, icon: 'el-icon-s-data' },
    { key: 'today_expense', label: '今日支出', value: overview.value.today_expense, icon: 'el-icon-remove-outline' }
  ]
})

onMounted(() => {
  fetchOverview()
  fetchRecords()
})

function fetchOverview() {
  overviewLoading.value = true
  getMerchantFinanceOverview().then(res => {
    overview.value = Object.assign({}, overview.value, res.data || {})
  }).finally(() => {
    overviewLoading.value = false
  })
}

function fetchRecords() {
  loading.value = true
  getMerchantFinanceRecords({
    page: page.value,
    limit: limit.value,
    ...searchForm.value
  }).then(res => {
    const data = res && res.data ? res.data : {}
    recordList.value = Array.isArray(data.list) ? data.list : []
    total.value = Number(data.total || 0)
  }).finally(() => {
    loading.value = false
  })
}

function handleSearch() {
  page.value = 1
  fetchRecords()
}

function handleReset() {
  searchForm.value = createSearchForm()
  dateRange.value = []
  page.value = 1
  fetchRecords()
}

function handleDateChange(value) {
  if (Array.isArray(value) && value.length === 2) {
    searchForm.value.start_time = value[0]
    searchForm.value.end_time = value[1]
  } else {
    searchForm.value.start_time = ''
    searchForm.value.end_time = ''
  }
}

function handleSizeChange(value) {
  limit.value = value
  page.value = 1
  fetchRecords()
}

function handleCurrentChange(value) {
  page.value = value
  fetchRecords()
}

function goWithdraw() {
  router.push({ name: 'MerchantFinanceWithdraw' })
}

function getTypeLabel(value) {
  const found = typeOptions.find(item => item.value === value)
  return found ? found.label : '其他'
}

function getFundStatusLabel(value) {
  const map = { available: '可提现', frozen: '冻结中', reversed: '已冲正' }
  return map[value] || '可提现'
}

function getFundStatusTag(value) {
  const map = { available: 'success', frozen: 'warning', reversed: 'info' }
  return map[value] || 'info'
}

function formatMoney(value) {
  const number = Number(value)
  const safeNumber = Number.isFinite(number) ? number : 0
  return safeNumber.toLocaleString('zh-CN', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
}
</script>

<style lang="scss" scoped>
.finance-records-page {
  min-height: calc(100vh - 84px);
  color: var(--text-primary);
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 16px;
}

.page-title {
  margin: 0 0 8px;
  color: var(--text-title);
  font-size: 24px;
  font-weight: 700;
  line-height: 1.3;
  letter-spacing: 0;
}

.page-subtitle {
  margin: 0;
  color: var(--text-secondary);
  font-size: 14px;
  line-height: 1.5;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 14px;
}

.summary-card {
  min-height: 104px;
  padding: 16px;
  border: 1px solid var(--layout-outline);
  border-radius: 8px;
  background: var(--layout-surface-card);
  box-shadow: 0 8px 20px var(--brand-shadow-card);

  strong {
    display: block;
    margin-top: 16px;
    color: var(--text-title);
    font-size: 22px;
    line-height: 1.2;
  }
}

.summary-card__top {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--text-secondary);
  font-size: 13px;
}

.summary-card__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: var(--brand-primary-pale);
  color: var(--brand-primary);
}

.panel {
  margin-bottom: 14px;
  border: 1px solid var(--layout-outline);
  border-radius: 8px;
  background: var(--layout-surface-card);
  box-shadow: 0 8px 20px var(--brand-shadow-card);

  &--search {
    padding: 18px 20px 2px;
  }

  &--table {
    padding: 16px 20px;
  }
}

.search-form {
  :deep(.el-form-item) {
    margin-right: 16px;
    margin-bottom: 16px;
  }
}

.search-select {
  width: 170px;
}

.search-select-sm {
  width: 128px;
}

.date-picker {
  width: 240px;
}

.finance-table {
  :deep(.el-table__header th) {
    background: var(--layout-surface-low);
    color: var(--text-secondary);
    font-weight: 600;
  }
}

.table-main {
  color: var(--text-primary);
  font-weight: 600;
}

.table-sub {
  margin-top: 4px;
  color: var(--text-placeholder);
  font-size: 12px;
}

.amount-text {
  font-weight: 700;

  &.is-income {
    color: var(--text-success);
  }

  &.is-expense {
    color: var(--text-danger);
  }
}

.pagination-wrap {
  display: flex;
  justify-content: flex-end;
  padding-top: 16px;
}

@media (max-width: 1280px) {
  .summary-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
  }

  .summary-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
