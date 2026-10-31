<template>
  <div class="live-reward-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">直播收益记录</h1>
        <p class="page-curator-subtitle">
          查看礼物打赏与商品订单转化数据，快速掌握直播间收益与互动表现。
        </p>
      </div>
    </header>

    <div class="live-reward-work-surface">
      <!-- Tab 切换 -->
      <el-card shadow="never" class="tab-top-card">
        <el-tabs v-model="activeTab" @tab-click="handleTabChange">
          <el-tab-pane label="礼物打赏记录" name="reward" />
          <el-tab-pane label="商品订单记录" name="order" />
        </el-tabs>
      </el-card>

      <!-- 搜索区域 -->
      <el-card shadow="never" class="search-card">
        <el-form
          ref="searchFormRef"
          :model="searchForm"
          inline
          class="search-form"
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
            <el-button type="primary" class="btn-curator-primary" :icon="Search" @click="handleSearch">查询</el-button>
            <el-button class="btn-curator-reset" :icon="Refresh" @click="handleReset">重置</el-button>
          </el-form-item>
        </el-form>
      </el-card>

      <!-- 统计概要 -->
      <el-card shadow="never" class="summary-card">
        <el-row :gutter="20">
          <el-col :span="6">
            <div class="summary-item">
              <div class="summary-icon bg-primary"><LegacyIcon name="el-icon-data-line" /></div>
              <div class="summary-info">
                <div class="summary-num">{{ total }}</div>
                <div class="summary-label">总记录数</div>
              </div>
            </div>
          </el-col>
          <el-col v-if="activeTab === 'reward'" :span="6">
            <div class="summary-item">
              <div class="summary-icon bg-warning"><LegacyIcon name="el-icon-present" /></div>
              <div class="summary-info">
                <div class="summary-num">{{ summaryData.total_coins || 0 }}</div>
                <div class="summary-label">总消耗金币</div>
              </div>
            </div>
          </el-col>
          <el-col :span="6">
            <div class="summary-item">
              <div class="summary-icon bg-success"><LegacyIcon name="el-icon-money" /></div>
              <div class="summary-info">
                <div class="summary-num price-num">￥{{ summaryData.total_amount || '0.00' }}</div>
                <div class="summary-label">总金额（元）</div>
              </div>
            </div>
          </el-col>
        </el-row>
      </el-card>

      <!-- 打赏记录表格 -->
      <el-card v-if="activeTab === 'reward'" shadow="never" class="table-card">
        <el-table v-loading="loading" :data="dataList" class="mt14">
          <el-table-column prop="id" label="ID" width="70" align="center" />
          <el-table-column label="用户" width="130" align="center">
            <template #default="{ row }">
              <div class="user-cell">
                <el-avatar :size="28" :src="row.avatar" :icon="UserFilled" />
                <span class="ml6">{{ row.nickname || row.user_id }}</span>
              </div>
            </template>
          </el-table-column>
          <el-table-column label="直播间" min-width="120" show-overflow-tooltip>
            <template #default="{ row }">{{ row.room_title || '—' }}</template>
          </el-table-column>
          <el-table-column prop="gift_name" label="礼物名称" width="110" align="center" />
          <el-table-column label="礼物图标" width="70" align="center">
            <template #default="{ row }">
              <el-image v-if="row.gift_icon" :src="row.gift_icon" class="gift-icon" fit="contain" />
              <LegacyIcon v-else name="el-icon-present" />
            </template>
          </el-table-column>
          <el-table-column label="消耗金币" width="100" align="center">
            <template #default="{ row }">
              <span class="coin-text">{{ row.price }}</span>
            </template>
          </el-table-column>
          <el-table-column label="兑换金额" width="110" align="center">
            <template #default="{ row }">
              <span class="price-text">￥{{ row.exchange_amount }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="create_time" label="打赏时间" width="160" align="center" />
        </el-table>
        <div class="pagination-wrap">
          <el-pagination
            v-model:current-page="page"
            v-model:page-size="limit"
            :page-sizes="[10, 20, 50, 100]"
            layout="total, sizes, prev, pager, next, jumper"
            :total="total"
            @size-change="handleSizeChange"
            @current-change="handleCurrentChange"
          />
        </div>
      </el-card>

      <!-- 订单记录表格 -->
      <el-card v-if="activeTab === 'order'" shadow="never" class="table-card">
        <el-table v-loading="loading" :data="dataList" class="mt14">
          <el-table-column prop="id" label="订单ID" width="90" align="center" />
          <el-table-column label="用户" width="130" align="center">
            <template #default="{ row }">
              <div class="user-cell">
                <el-avatar :size="28" :src="row.avatar" :icon="UserFilled" />
                <span class="ml6">{{ row.nickname || row.user_id }}</span>
              </div>
            </template>
          </el-table-column>
          <el-table-column label="直播间" min-width="120" show-overflow-tooltip>
            <template #default="{ row }">{{ row.room_title || '—' }}</template>
          </el-table-column>
          <el-table-column prop="goods_title" label="商品名称" min-width="140" show-overflow-tooltip />
          <el-table-column label="商品类型" width="100" align="center">
            <template #default="{ row }">
              <el-tag :type="row.goods_type === 'special' ? 'warning' : 'primary'" >
                {{ row.goods_type === 'special' ? '付费专题' : '单篇内容' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="订单金额" width="110" align="center">
            <template #default="{ row }">
              <span class="price-text">￥{{ row.pay_price }}</span>
            </template>
          </el-table-column>
          <el-table-column label="订单状态" width="100" align="center">
            <template #default="{ row }">
              <el-tag :type="getOrderStatusType(row.status)" >
                {{ getOrderStatusLabel(row.status) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="create_time" label="下单时间" width="160" align="center" />
        </el-table>
        <div class="pagination-wrap">
          <el-pagination
            v-model:current-page="page"
            v-model:page-size="limit"
            :page-sizes="[10, 20, 50, 100]"
            layout="total, sizes, prev, pager, next, jumper"
            :total="total"
            @size-change="handleSizeChange"
            @current-change="handleCurrentChange"
          />
        </div>
      </el-card>
    </div>
  </div>
</template>

<script setup>
import { getRoomList, getLiveRewardList, getLiveOrderList } from '@/api/live'
import { Refresh, Search, UserFilled } from '@element-plus/icons-vue'
import { onMounted, reactive, ref } from 'vue'

defineOptions({
  name: 'LiveOrderStats'
})

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

const searchFormRef = ref(null)

onMounted(() => {
  loadRoomOptions()
  loadData()
})

function loadRoomOptions() {
  getRoomList({ page: 1, limit: 200 }).then(res => {
    roomOptions.value = res.data.list || res.data || []
  })
}

function buildParams() {
  const p = { page: page.value, limit: limit.value }
  if (searchForm.live_room_id) p.live_room_id = searchForm.live_room_id
  if (searchForm.nickname) p.nickname = searchForm.nickname
  if (activeTab.value === 'reward' && searchForm.gift_name) p.gift_name = searchForm.gift_name
  if (searchForm.date_range && searchForm.date_range.length === 2) {
    p.start_date = searchForm.date_range[0]
    p.end_date = searchForm.date_range[1]
  }
  return p
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
  searchFormRef.value.resetFields()
  Object.assign(searchForm, { live_room_id: '', nickname: '', gift_name: '', date_range: [] })
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
.live-reward-curator-page {
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
  color: var(--ds-on-surface);
  letter-spacing: 0.02em;
}

.page-curator-subtitle {
  margin: 0;
  font-size: 0.875rem;
  color: var(--ds-on-surface-variant);
  line-height: 1.5;
  max-width: 560px;
}

.live-reward-work-surface {
  background: var(--ds-surface-card);
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
}

.tab-top-card,
.search-card,
.summary-card,
.table-card {
  border: none;
  background: transparent;

  :deep(.el-card__body) {
    padding: 0;
  }
}

.tab-top-card {
  margin-bottom: 0;

  :deep(.el-tabs__header) {
    margin-bottom: 0;
  }

  :deep(.el-tabs__item) {
    color: var(--ds-on-surface-variant);

    &.is-active {
      color: var(--ds-primary);
      font-weight: 600;
    }
  }

  :deep(.el-tabs__active-bar) {
    background: var(--ds-primary);
    border-radius: 2px 2px 0 0;
  }

  :deep(.el-tabs__nav-wrap::after) {
    background: var(--ds-outline-ghost);
  }
}

.search-card {
  margin: 14px 0;
}

.summary-card {
  margin-bottom: 14px;
}

:deep(.btn-curator-primary.el-button--primary) {
  border: none;
  border-radius: 6px;
  color: var(--ds-on-primary);
  font-weight: 500;
  padding-left: 18px;
  padding-right: 18px;
  box-shadow: 0 2px 8px var(--brand-shadow);
}

:deep(.btn-curator-reset) {
  border-radius: 6px;
  background: var(--ds-surface-card);
  color: var(--ds-primary);
  border: 1px solid var(--ds-primary);

  &:hover,
  &:focus {
    background: var(--brand-primary-pale);
    color: var(--ds-primary-hover);
    border-color: var(--ds-primary-hover);
  }
}

.table-card {
  :deep(.el-table__header th) {
    color: var(--ds-on-surface-variant);
    font-weight: 500;
    background: rgba(var(--ds-surface-low-rgb), 0.5);
  }

  :deep(.el-table__row:hover > td) {
    background: var(--ds-surface-low) !important;
  }
}

.live-reward-work-surface {
  :deep(.el-pager li.active) {
    background: var(--ds-primary);
    color: var(--ds-on-primary);
    border-radius: 4px;
  }

  :deep(.btn-next:hover),
  :deep(.btn-prev:hover),
  :deep(.el-pager li:hover) {
    color: var(--ds-primary);
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

.mt14 {
  margin-top: 14px;
}

.summary-item {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px 0;
}

.summary-icon {
  width: 48px;
  height: 48px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  color: #fff;
  flex-shrink: 0;

  &.bg-primary { background: var(--ds-primary); }
  &.bg-warning { background: var(--text-warning); }
  &.bg-success { background: #67c23a; }
}

.summary-num {
  font-size: 22px;
  font-weight: 700;
  color: var(--ds-on-surface);
  line-height: 1.2;
}

.price-num {
  color: var(--text-warning);
}

.summary-label {
  font-size: 13px;
  color: var(--text-secondary);
  margin-top: 2px;
}

.user-cell {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}

.ml6 {
  margin-left: 4px;
}

.gift-icon {
  width: 32px;
  height: 32px;
}

.coin-text {
  color: var(--text-warning);
  font-weight: 600;
}

.price-text {
  color: var(--text-price);
  font-weight: 600;
}

.pagination-wrap {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}
</style>
