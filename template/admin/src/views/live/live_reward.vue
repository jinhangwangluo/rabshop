<template>
  <div class="reward-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">直播统计</h1>
        <p class="page-curator-subtitle">
          查看直播间礼物打赏记录与商品订单统计数据，支持按时间、直播间筛选。
        </p>
      </div>
    </header>

    <div class="reward-work-surface reward-search-panel">
      <!-- Tab 切换 -->
      <el-tabs v-model="activeTab" class="reward-curator-tabs" @tab-click="handleTabChange">
        <el-tab-pane label="礼物打赏记录" name="reward" />
        <el-tab-pane label="商品订单记录" name="order" />
      </el-tabs>

      <!-- 搜索区域 -->
      <el-form
        ref="searchFormRef"
        :model="searchForm"
        inline
        class="search-form reward-search-form"
        @submit.prevent
      >
        <el-form-item label="直播间">
          <el-select
            v-model="searchForm.live_room_id"
            placeholder="请选择直播间"
            filterable
            clearable
            class="search-select"
          >
            <el-option
              v-for="item in roomOptions"
              :key="item.id"
              :label="item.title"
              :value="item.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="用户昵称">
          <el-input
            v-model="searchForm.nickname"
            clearable
            placeholder="请输入用户昵称"
            class="search-input"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item v-if="activeTab === 'reward'" label="礼物名称">
          <el-input
            v-model="searchForm.gift_name"
            clearable
            placeholder="请输入礼物名称"
            class="search-input"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="时间">
          <el-date-picker
            v-model="searchForm.date_range"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="yyyy-MM-dd"
            class="search-date"
          />
        </el-form-item>
        <el-form-item>
          <el-button
            type="primary"
            class="btn-curator-primary"
            icon="el-icon-search"
            @click="handleSearch"
          >
            查询
          </el-button>
          <el-button
            class="btn-curator-reset"
            icon="el-icon-refresh"
            @click="handleReset"
          >
            重置
          </el-button>
        </el-form-item>
      </el-form>
    </div>

    <!-- 统计概要 -->
    <el-row :gutter="16" class="summary-card">
      <el-col :span="6">
        <div class="stat-badge stat-badge--green">
          <span class="stat-badge__label">总记录数</span>
          <div class="stat-badge__body">
            <span class="stat-badge__value">{{ total || 0 }}</span>
            <span class="stat-badge__sub stat-badge__sub--up">全部</span>
          </div>
        </div>
      </el-col>
      <el-col v-if="activeTab === 'reward'" :span="6">
        <div class="stat-badge stat-badge--gold">
          <span class="stat-badge__label">总消耗金币</span>
          <div class="stat-badge__body">
            <span class="stat-badge__value">{{ formatCoins(summaryData.total_coins) }}</span>
            <span class="stat-badge__sub stat-badge__sub--warn">金币</span>
          </div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-badge stat-badge--gray">
          <span class="stat-badge__label">总能兑换金额</span>
          <div class="stat-badge__body">
            <span class="stat-badge__value">¥{{ formatMoney(summaryData.total_amount) }}</span>
            <span class="stat-badge__sub stat-badge__sub--down">元</span>
          </div>
        </div>
      </el-col>
    </el-row>

    <div class="reward-work-surface reward-table-panel">
      <!-- 打赏记录表格 -->
      <el-table
        v-if="activeTab === 'reward'"
        v-loading="loading"
        :data="dataList"
        class="reward-curator-table"
      >
        <el-table-column prop="id" label="ID" width="70" align="center" />
        <el-table-column label="用户" width="130" align="center">
          <template #default="scope">
            <div class="user-cell">
              <el-avatar :size="28" :src="scope.row.avatar" icon="el-icon-user-solid" />
              <span class="ml6">{{ scope.row.nickname || scope.row.user_id }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="直播间" min-width="120" show-overflow-tooltip>
          <template #default="scope">{{ scope.row.room_title || '—' }}</template>
        </el-table-column>
        <el-table-column prop="gift_name" label="礼物名称" min-width="120" align="center" show-overflow-tooltip />
        <el-table-column label="礼物图标" width="70" align="center">
          <template #default="scope">
            <el-image v-if="scope.row.gift_icon" :src="scope.row.gift_icon" class="gift-icon" fit="contain" />
            <LegacyIcon v-else name="el-icon-present" />
          </template>
        </el-table-column>
        <el-table-column label="礼物单价(金币)" width="120" align="center">
          <template #default="scope">
            <span class="coin-text">{{ formatCoins(scope.row.gift_price || scope.row.price) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="数量" width="80" align="center">
          <template #default="scope">
            <span>{{ scope.row.quantity || 1 }}</span>
          </template>
        </el-table-column>
        <el-table-column label="能兑换金额" width="120" align="center">
          <template #default="scope">
            <span class="price-text">￥{{ scope.row.exchange_amount || scope.row.total_amount || '0.00' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="打赏时间" width="160" align="center">
          <template #default="scope">{{ scope.row.send_time || scope.row.create_time || '—' }}</template>
        </el-table-column>
      </el-table>
      <!-- 订单记录表格 -->
      <el-table
        v-if="activeTab === 'order'"
        v-loading="loading"
        :data="dataList"
        class="reward-curator-table"
      >
        <el-table-column prop="id" label="订单ID" width="90" align="center" />
        <el-table-column label="用户" width="130" align="center">
          <template #default="scope">
            <div class="user-cell">
              <el-avatar :size="28" :src="scope.row.avatar" icon="el-icon-user-solid" />
              <span class="ml6">{{ scope.row.nickname || scope.row.user_id }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="直播间" min-width="120" show-overflow-tooltip>
          <template #default="scope">{{ scope.row.room_title || '—' }}</template>
        </el-table-column>
        <el-table-column prop="goods_title" label="商品名称" min-width="140" show-overflow-tooltip />
        <el-table-column label="商品类型" width="100" align="center">
          <template #default="scope">
            <el-tag :type="scope.row.goods_type === 'special' ? 'warning' : 'primary'">
              {{ scope.row.goods_type === 'special' ? '付费专题' : '单篇内容' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="订单金额" width="110" align="center">
          <template #default="scope">
            <span class="price-text">￥{{ scope.row.pay_price }}</span>
          </template>
        </el-table-column>
        <el-table-column label="订单状态" width="100" align="center">
          <template #default="scope">
            <el-tag :type="getOrderStatusType(scope.row.status)">
              {{ getOrderStatusLabel(scope.row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="create_time" label="下单时间" width="160" align="center" />
      </el-table>
      <div class="pagination-wrap">
        <el-pagination
          class="reward-curator-pagination"
          :current-page="page"
          :page-sizes="[10, 20, 50, 100]"
          :page-size="limit"
          layout="total, sizes, prev, pager, next, jumper"
          :total="total"
          @size-change="handleSizeChange"
          @current-change="handleCurrentChange"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { onBeforeMount, reactive, ref } from 'vue'
import { getRoomList, getLiveRewardList, getLiveOrderList } from '@/api/live'

defineOptions({
  name: 'LiveOrderStats'
})

const searchFormRef = ref(null)
const activeTab = ref('reward')
const loading = ref(false)
const dataList = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(20)
const roomOptions = ref([])
const summaryData = ref({})
const searchForm = reactive({
  live_room_id: '',
  nickname: '',
  gift_name: '',
  date_range: []
})

const DEFAULT_SEARCH_FORM = {
  live_room_id: '',
  nickname: '',
  gift_name: '',
  date_range: []
}

onBeforeMount(() => {
  loadRoomOptions()
  loadData()
})

function loadRoomOptions() {
  getRoomList({ page: 1, limit: 200 }).then(res => {
    roomOptions.value = res.data.list || res.data || []
  })
}

function buildParams() {
  const params = { page: page.value, limit: limit.value }
  if (searchForm.live_room_id) params.live_room_id = searchForm.live_room_id
  if (searchForm.nickname) params.nickname = searchForm.nickname
  if (activeTab.value === 'reward' && searchForm.gift_name) params.gift_name = searchForm.gift_name
  if (searchForm.date_range && searchForm.date_range.length === 2) {
    params.start_date = searchForm.date_range[0]
    params.end_date = searchForm.date_range[1]
  }
  return params
}

function loadData() {
  loading.value = true
  const api = activeTab.value === 'reward' ? getLiveRewardList : getLiveOrderList
  api(buildParams()).then(res => {
    dataList.value = res.data.list || res.data || []
    total.value = res.data.total || 0
    summaryData.value = res.data.summary || {}
  }).finally(() => {
    loading.value = false
  })
}

function handleTabChange() {
  page.value = 1
  dataList.value = []
  summaryData.value = {}
  loadData()
}

function handleSearch() {
  page.value = 1
  loadData()
}

function handleReset() {
  searchFormRef.value?.resetFields?.()
  Object.assign(searchForm, DEFAULT_SEARCH_FORM)
  handleSearch()
}

function getOrderStatusLabel(status) {
  const map = { 0: '待支付', 1: '已完成', 2: '已退款' }
  return map[status] || '未知'
}

function getOrderStatusType(status) {
  const map = { 0: 'warning', 1: 'success', 2: 'info' }
  return map[status] || 'info'
}

function formatMoney(value) {
  return Number(value || 0).toFixed(2)
}

function formatCoins(value) {
  return parseInt(value || 0, 10) || 0
}

function handleSizeChange(val) {
  limit.value = val
  page.value = 1
  loadData()
}

function handleCurrentChange(val) {
  page.value = val
  loadData()
}
</script>

<style lang="scss" scoped>
/* material/stitch/DESIGN.md */
.reward-curator-page {
  min-height: 100%;
  box-sizing: border-box;
}

.page-curator-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 20px;
}

.page-curator-title {
  margin: 0 0 8px;
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--curator-on-surface);
  letter-spacing: 0.02em;
}

.page-curator-subtitle {
  margin: 0;
  font-size: 0.875rem;
  color: var(--curator-on-surface-variant);
  line-height: 1.5;
  max-width: 520px;
}

.reward-work-surface {
  background: var(--curator-surface-card);
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
}

.reward-search-panel {
  margin-bottom: 12px;
}

.reward-table-panel {
  padding: 16px 24px;
}

:deep(.btn-curator-primary.el-button--primary) {
  border: none;
  border-radius: 6px;
  color: var(--curator-on-primary);
  font-weight: 500;
  padding-left: 18px;
  padding-right: 18px;
  box-shadow: 0 2px 8px rgba(0, 87, 194, 0.2);
}

:deep(.btn-curator-reset) {
  border-radius: 6px;
  background: var(--curator-surface-card);
  color: var(--curator-primary);
  border: 1px solid var(--curator-primary);

  &:hover,
  &:focus {
    background: rgba(0, 87, 194, 0.06);
    color: var(--curator-primary-container);
    border-color: var(--curator-primary-container);
  }
}

.reward-curator-tabs {
  :deep(.el-tabs__header) {
    margin-bottom: 16px;
  }

  :deep(.el-tabs__item) {
    color: var(--curator-on-surface-variant);
    font-size: 14px;

    &.is-active {
      color: var(--curator-primary);
      font-weight: 600;
    }
  }

  :deep(.el-tabs__active-bar) {
    height: 3px;
    background-color: var(--curator-primary);
    border-radius: 2px 2px 0 0;
  }

  :deep(.el-tabs__nav-wrap::after) {
    height: 1px;
    background-color: var(--curator-outline-variant);
  }
}

.reward-search-form {
  margin-bottom: 8px;
}

.reward-curator-table {
  margin-top: 8px;

  :deep(.el-table__header th) {
    color: var(--curator-on-surface-variant);
    font-weight: 500;
    background: var(--curator-surface-low);
  }

  :deep(.el-table__row:hover > td) {
    background: var(--curator-surface-low) !important;
  }
}

.pagination-wrap {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}

.reward-curator-pagination {
  :deep(.el-pager li.active) {
    background: var(--curator-primary);
    color: var(--curator-on-primary);
    border-radius: 4px;
  }

  :deep(.btn-next),
  :deep(.btn-prev),
  :deep(.el-pager li) {
    &:hover {
      color: var(--curator-primary);
    }
  }
}

.search-input {
  width: 160px;
}

.search-select {
  width: 200px;
}

.search-date {
  width: 240px;
}

.summary-card {
  margin-bottom: 12px;
}

.stat-badge {
  position: relative;
  background: var(--dashboard-surface);
  border: 1px solid var(--dashboard-border);
  border-radius: 8px;
  padding: 18px 20px 16px 24px;
  min-height: 80px;
  overflow: hidden;

  &::before {
    content: '';
    position: absolute;
    left: 0;
    top: 12px;
    bottom: 12px;
    width: 4px;
    border-radius: 0 4px 4px 0;
  }

  &--green::before { background: linear-gradient(180deg, #43d79a 0%, #1db87b 100%); }
  &--gold::before { background: linear-gradient(180deg, #fbbf24 0%, #d97706 100%); }
  &--gray::before { background: linear-gradient(180deg, #d1d5db 0%, #9ca3af 100%); }
}

.stat-badge__label {
  display: block;
  font-size: 13px;
  color: var(--dashboard-text-muted);
  margin-bottom: 10px;
  line-height: 1;
}

.stat-badge__body {
  display: flex;
  align-items: baseline;
  gap: 8px;
}

.stat-badge__value {
  font-size: 26px;
  font-weight: 700;
  color: var(--dashboard-text-primary);
  line-height: 1;
  letter-spacing: 0;
}

.stat-badge__sub {
  font-size: 13px;
  font-weight: 500;
  line-height: 1;

  &--up { color: #1db87b; }
  &--down { color: #f56c6c; }
  &--warn { color: #d97706; }
}

.user-cell {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}

.gift-icon {
  width: 32px;
  height: 32px;
}

.coin-text {
  color: var(--curator-primary);
  font-weight: 600;
}

.price-text {
  color: var(--curator-primary);
  font-weight: 600;
}
</style>
