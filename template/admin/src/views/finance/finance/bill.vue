<template>
  <div class="finance-bill-page">
    <!-- 搜索区域 -->
    <el-card shadow="never" class="search-card">
      <el-form ref="searchForm" :model="searchForm" inline class="search-form" @submit.prevent>
        <el-form-item label="关键词">
          <el-input
            v-model="searchForm.keyword"
            clearable
            placeholder="用户ID/昵称/商户名"
            class="search-input"
            prefix-icon="el-icon-search"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="收支类型">
          <el-select v-model="searchForm.pm" placeholder="全部" clearable class="search-select-sm">
            <el-option label="收入" :value="1" />
            <el-option label="支出" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item label="账单类型">
          <el-select v-model="searchForm.category" placeholder="全部" clearable class="search-select">
            <el-option label="订单支付" value="order_pay" />
            <el-option label="订单退款" value="order_refund" />
            <el-option label="余额充值" value="recharge" />
            <el-option label="提现" value="withdraw" />
            <el-option label="推广返佣" value="brokerage" />
            <el-option label="平台收入" value="platform" />
          </el-select>
        </el-form-item>
        <el-form-item label="时间范围">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="yyyy-MM-dd"
            style="width: 240px;"
            @change="handleDateChange"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" icon="el-icon-search" @click="handleSearch">查询</el-button>
          <el-button icon="el-icon-refresh" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="stat-row">
      <el-col :span="6">
        <el-card shadow="never" body-style="padding:16px">
          <div class="stat-item">
            <span class="stat-label">总收入</span>
            <span class="stat-value income">¥{{ stats.total_income || '0.00' }}</span>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" body-style="padding:16px">
          <div class="stat-item">
            <span class="stat-label">总支出</span>
            <span class="stat-value expense">¥{{ stats.total_expense || '0.00' }}</span>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" body-style="padding:16px">
          <div class="stat-item">
            <span class="stat-label">今日收入</span>
            <span class="stat-value primary">¥{{ stats.today_income || '0.00' }}</span>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" body-style="padding:16px">
          <div class="stat-item">
            <span class="stat-label">本月收入</span>
            <span class="stat-value">¥{{ stats.month_income || '0.00' }}</span>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 表格 -->
    <el-card shadow="never">
      <el-table v-loading="loading" :data="list" class="mt14">
        <el-table-column prop="id" label="ID" width="80" align="center" />
        <el-table-column label="相关用户" min-width="130">
          <template #default="scope">
            <div v-if="scope.row.user">
              <div>{{ scope.row.user.nickname || '-' }}</div>
              <div class="uid-text">UID: {{ scope.row.user.id }}</div>
            </div>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column label="账单类型" width="110" align="center">
          <template #default="scope">
            <el-tag>{{ getCategoryLabel(scope.row.category) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="金额" width="120" align="center">
          <template #default="scope">
            <span :class="scope.row.pm === 1 ? 'income-text' : 'expense-text'">
              {{ scope.row.pm === 1 ? '+' : '-' }}¥{{ scope.row.number }}
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="title" label="账单标题" min-width="150" show-overflow-tooltip />
        <el-table-column prop="link_id" label="关联单号" width="160" show-overflow-tooltip />
        <el-table-column label="余额快照" width="120" align="center">
          <template #default="scope">
            <span>¥{{ scope.row.balance || '0.00' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="mark" label="备注" min-width="120" show-overflow-tooltip />
        <el-table-column prop="create_time" label="创建时间" width="160" align="center" />
      </el-table>

      <div class="pagination-wrap">
        <el-pagination
          :current-page="page"
          :page-sizes="[15, 20, 50, 100]"
          :page-size="limit"
          layout="total, sizes, prev, pager, next, jumper"
          :total="total"
          @size-change="handleSizeChange"
          @current-change="handleCurrentChange"
        />
      </div>
    </el-card>
  </div>
</template>

<script setup>
import { onBeforeMount, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import request from '@/utils/request'

function getFinanceRecordList(params) {
  return request({ url: '/finance_record', method: 'get', params })
}

const CATEGORY_MAP = {
  order_pay: '订单支付',
  order_refund: '订单退款',
  recharge: '余额充值',
  withdraw: '提现',
  brokerage: '推广返佣',
  platform: '平台收入',
  gift: '礼物打赏',
  other: '其他'
}

defineOptions({
  name: 'FinanceBill'
})

const route = useRoute()
const loading = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(15)
const dateRange = ref([])
const stats = reactive({})
const searchForm = reactive({
  keyword: '',
  pm: '',
  category: '',
  start_time: '',
  end_time: ''
})

function assignStats(nextStats) {
  Object.keys(stats).forEach((key) => delete stats[key])
  Object.assign(stats, nextStats || {})
}

async function getList() {
  loading.value = true
  try {
    const res = await getFinanceRecordList({ page: page.value, limit: limit.value, ...searchForm })
    list.value = res.data.list || []
    total.value = res.data.total || 0
    if (res.data.stats) assignStats(res.data.stats)
  } finally {
    loading.value = false
  }
}

function handleDateChange(val) {
  if (val) {
    searchForm.start_time = val[0]
    searchForm.end_time = val[1]
  } else {
    searchForm.start_time = ''
    searchForm.end_time = ''
  }
}

function handleSearch() {
  page.value = 1
  void getList()
}

function handleReset() {
  Object.assign(searchForm, { keyword: '', pm: '', category: '', start_time: '', end_time: '' })
  dateRange.value = []
  page.value = 1
  void getList()
}

function handleSizeChange(val) {
  limit.value = val
  page.value = 1
  void getList()
}

function handleCurrentChange(val) {
  page.value = val
  void getList()
}

function getCategoryLabel(cat) {
  return CATEGORY_MAP[cat] || cat || '-'
}

onBeforeMount(() => {
  const { category = '', keyword = '' } = route.query
  if (category) {
    searchForm.category = category
  }
  if (keyword) {
    searchForm.keyword = keyword
  }
  void getList()
})
</script>

<style lang="scss" scoped>
.finance-bill-page {
  --finance-border: var(--dashboard-border);
  --finance-border-soft: var(--dashboard-border-soft);

  :deep(.el-card) {
    color: var(--dashboard-text-primary);
    background: var(--dashboard-surface);
    border-color: var(--finance-border) !important;
    box-shadow: none;
  }

  :deep(.el-card__body) {
    background: var(--dashboard-surface);
  }

  :deep(.el-table) {
    color: var(--dashboard-text-primary);
    background: var(--dashboard-surface);
    border-color: var(--finance-border) !important;

    &::before,
    &::after {
      background-color: var(--finance-border) !important;
    }
  }
  :deep(.el-table th) {
    color: var(--dashboard-text-secondary);
    background: var(--dashboard-surface-muted);
  }

  :deep(.el-table__fixed-right-patch),
  :deep(.el-table__border-left-patch) {
    background-color: var(--finance-border) !important;
  }

  :deep(.el-table__empty-block) {
    background: var(--dashboard-surface);
  }

  :deep(.el-table__empty-text) {
    color: var(--dashboard-text-muted);
  }
}

.search-card {
  margin-bottom: 16px;
  :deep(.el-card__body) { padding: 20px 20px 4px; }
}
.search-form {
  :deep(.el-form-item) { margin-bottom: 16px; margin-right: 12px; }
  .search-input { width: 180px; }
  .search-select-sm { width: 100px; }
  .search-select { width: 130px; }
}
.stat-row {
  margin-bottom: 16px;
  .stat-item { display: flex; flex-direction: column; align-items: center; }
  .stat-label { font-size: 12px; color: var(--dashboard-text-muted); margin-bottom: 6px; }
  .stat-value {
    font-size: 22px; font-weight: 700; color: var(--dashboard-text-primary);
    &.income { color: var(--dashboard-success); }
    &.expense { color: var(--dashboard-danger); }
    &.primary { color: var(--dashboard-accent); }
  }
}
.mt14 { margin-top: 14px; }
.income-text { color: var(--dashboard-success); font-weight: 600; }
.expense-text { color: var(--dashboard-danger); font-weight: 600; }
.uid-text { font-size: 11px; color: var(--dashboard-text-muted); }
.pagination-wrap { padding: 16px 0 0; display: flex; justify-content: flex-end; }
</style>
