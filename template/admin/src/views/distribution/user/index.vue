<template>
  <div class="distribution-user-page">
    <header class="page-header">
      <div>
        <h1 class="page-title">分销员列表</h1>
        <p class="page-subtitle">查看分销资格、一级/二级订单与返佣统计。</p>
      </div>
      <div class="mode-state">
        <el-tag :type="distributionEnabled ? 'success' : 'info'" size="medium">
          {{ distributionEnabled ? '分销已开启' : '分销已关闭' }}
        </el-tag>
        <el-tag :type="isAllMode ? 'warning' : 'primary'" size="medium">
          {{ modeText }}
        </el-tag>
      </div>
    </header>

    <div class="panel panel--search">
      <el-form
        :model="searchForm"
        inline
        class="search-form"
        @submit.prevent
      >
        <el-form-item label="关键词">
          <el-input
            v-model="searchForm.keyword"
            clearable
            class="search-input"
            placeholder="UID/昵称/手机号"
            @keyup.enter="handleSearch"
            @clear="handleSearch"
          />
        </el-form-item>
        <el-form-item label="用户状态">
          <el-select v-model="searchForm.status" clearable placeholder="全部" class="search-select-sm">
            <el-option label="正常" :value="1" />
            <el-option label="禁用" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item label="分销资格">
          <el-select v-model="searchForm.qualification" clearable placeholder="全部" class="search-select">
            <el-option label="有效资格" value="qualified" />
            <el-option label="无有效资格" value="unqualified" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" icon="el-icon-search" @click="handleSearch">查询</el-button>
          <el-button icon="el-icon-refresh" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <el-row :gutter="16" class="stats-row">
      <el-col :span="6">
        <div class="stat-card stat-card--green">
          <span class="stat-card__label">有效分销员</span>
          <strong class="stat-card__value">{{ stats.qualified_count || 0 }}</strong>
          <span class="stat-card__sub">{{ levelText }}</span>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-card stat-card--blue">
          <span class="stat-card__label">一级订单</span>
          <strong class="stat-card__value">{{ stats.first_order_count || 0 }}</strong>
          <span class="stat-card__sub">返佣 ¥{{ formatMoney(stats.first_commission_amount) }}</span>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-card stat-card--gold">
          <span class="stat-card__label">二级订单</span>
          <strong class="stat-card__value">{{ stats.second_order_count || 0 }}</strong>
          <span class="stat-card__sub">返佣 ¥{{ formatMoney(stats.second_commission_amount) }}</span>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-card stat-card--red">
          <span class="stat-card__label">总返佣</span>
          <strong class="stat-card__value">¥{{ formatMoney(stats.total_commission_amount) }}</strong>
          <span class="stat-card__sub">订单 {{ stats.total_order_count || 0 }}</span>
        </div>
      </el-col>
    </el-row>

    <div class="panel panel--table">
      <el-table
        v-loading="loading"
        :data="userList"
        row-key="uid"
        class="distribution-user-table"
      >
        <el-table-column prop="uid" label="UID" width="80" align="center" />
        <el-table-column label="用户" min-width="210">
          <template #default="{ row }">
            <div class="user-cell">
              <el-avatar :src="row.avatar" :size="38" icon="el-icon-user-solid" />
              <div class="user-cell__body">
                <div class="user-cell__name">{{ row.nickname || '-' }}</div>
                <div class="user-cell__meta">{{ row.phone || '-' }}</div>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="分销资格" width="150" align="center">
          <template #default="{ row }">
            <el-tag :type="row.effective_distribution_enabled ? 'success' : 'info'">
              {{ row.effective_distribution_text || (row.effective_distribution_enabled ? '可分销' : '不可分销') }}
            </el-tag>
            <div class="permission-text">{{ getPermissionText(row.distribution_permission) }}</div>
          </template>
        </el-table-column>
        <el-table-column label="一级订单" width="150" align="center">
          <template #default="{ row }">
            <div class="metric-value">{{ row.first_order_count || 0 }}</div>
            <div class="metric-sub">¥{{ formatMoney(row.first_commission_amount) }}</div>
          </template>
        </el-table-column>
        <el-table-column label="二级订单" width="150" align="center">
          <template #default="{ row }">
            <div class="metric-value">{{ row.second_order_count || 0 }}</div>
            <div class="metric-sub">¥{{ formatMoney(row.second_commission_amount) }}</div>
          </template>
        </el-table-column>
        <el-table-column label="总订单/返佣" width="150" align="center">
          <template #default="{ row }">
            <div class="metric-value">{{ row.total_order_count || 0 }}</div>
            <div class="metric-sub metric-sub--strong">¥{{ formatMoney(row.total_commission_amount) }}</div>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="Number(row.status) === 1 ? 'success' : 'danger'">
              {{ Number(row.status) === 1 ? '正常' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="create_time" label="注册时间" width="165" align="center" />
        <el-table-column label="操作" width="190" fixed="right" align="center">
          <template #default="{ row }">
            <el-button type="text" @click="handleDetail(row)">详情</el-button>
            <el-divider direction="vertical" />
            <el-tooltip
              :disabled="!isAllMode"
              content="人人分销模式下无需手动调整资格"
              placement="top"
            >
              <span>
                <el-button
                  type="text"

                  :disabled="isAllMode || row.permissionChanging"
                  :class="row.effective_distribution_enabled ? 'text-danger' : 'text-success'"
                  @click="handlePermission(row)"
                >
                  {{ row.effective_distribution_enabled ? '取消资格' : '增加资格' }}
                </el-button>
              </span>
            </el-tooltip>
          </template>
        </el-table-column>
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
    </div>

    <el-drawer
      v-model="drawerVisible"
      :title="drawerTitle"
      size="760px"
      custom-class="distribution-order-drawer"
      append-to-body
      @close="handleDrawerClose"
    >
      <div class="drawer-content">
        <div v-if="currentUser" class="drawer-summary">
          <div class="drawer-summary__user">
            <el-avatar :src="currentUser.avatar" :size="44" icon="el-icon-user-solid" />
            <div>
              <div class="drawer-summary__name">{{ currentUser.nickname || '-' }}</div>
              <div class="drawer-summary__meta">UID {{ currentUser.uid }} / {{ currentUser.phone || '-' }}</div>
            </div>
          </div>
          <div class="drawer-summary__stats">
            <span>一级 {{ currentUser.first_order_count || 0 }} 单 / ¥{{ formatMoney(currentUser.first_commission_amount) }}</span>
            <span>二级 {{ currentUser.second_order_count || 0 }} 单 / ¥{{ formatMoney(currentUser.second_commission_amount) }}</span>
          </div>
        </div>

        <el-form
          :model="orderSearch"
          inline
          class="drawer-search-form"
          @submit.prevent
        >
          <el-form-item label="订单层级">
            <el-radio-group v-model="orderSearch.level" @change="handleOrderLevelChange">
              <el-radio-button :label="1">一级订单</el-radio-button>
              <el-radio-button :label="2">二级订单</el-radio-button>
            </el-radio-group>
          </el-form-item>
          <el-form-item label="订单号">
            <el-input
              v-model="orderSearch.order_sn"
              clearable
              class="drawer-search-input"
              placeholder="请输入订单号"
              @keyup.enter="handleOrderSearch"
              @clear="handleOrderSearch"
            />
          </el-form-item>
          <el-form-item label="买家">
            <el-input
              v-model="orderSearch.buyer_keyword"
              clearable
              class="drawer-search-input"
              placeholder="UID/昵称/手机号"
              @keyup.enter="handleOrderSearch"
              @clear="handleOrderSearch"
            />
          </el-form-item>
          <el-form-item label="下单时间">
            <el-date-picker
              v-model="orderDateRange"
              type="daterange"
              range-separator="至"
              start-placeholder="开始日期"
              end-placeholder="结束日期"
              value-format="yyyy-MM-dd"

              class="drawer-date-range"
              @change="handleOrderDateChange"
            />
          </el-form-item>
          <el-form-item>
            <el-button type="primary" icon="el-icon-search" @click="handleOrderSearch">筛选</el-button>
            <el-button icon="el-icon-refresh" @click="handleOrderReset">重置</el-button>
          </el-form-item>
        </el-form>

        <el-table
          v-loading="orderLoading"
          :data="orderList"
          row-key="id"
          class="order-detail-table"
        >
          <el-table-column prop="order_sn" label="订单号" min-width="160" show-overflow-tooltip />
          <el-table-column label="买家" min-width="150">
            <template #default="{ row }">
              <div class="buyer-cell">
                <span>{{ row.buyer_nickname || '-' }}</span>
                <small>{{ row.buyer_phone || ('UID ' + row.buyer_id) }}</small>
              </div>
            </template>
          </el-table-column>
          <el-table-column label="支付金额" width="110" align="right">
            <template #default="{ row }">
              <span class="price-text">¥{{ formatMoney(row.pay_amount) }}</span>
            </template>
          </el-table-column>
          <el-table-column label="返佣金额" width="110" align="right">
            <template #default="{ row }">
              <span class="commission-text">¥{{ formatMoney(row.commission_amount) }}</span>
            </template>
          </el-table-column>
          <el-table-column label="状态" width="110" align="center">
            <template #default="{ row }">
              <el-tag size="mini" type="success">{{ row.commission_status_text || '-' }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="create_time" label="下单时间" width="160" align="center" />
        </el-table>

        <div class="pagination-wrap drawer-pagination">
          <el-pagination
            :current-page="orderPage"
            :page-sizes="[10, 20, 50]"
            :page-size="orderLimit"
            layout="total, sizes, prev, pager, next"
            :total="orderTotal"
            @size-change="handleOrderSizeChange"
            @current-change="handleOrderCurrentChange"
          />
        </div>
      </div>
    </el-drawer>
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, onBeforeMount, reactive, ref } from 'vue'
import {
  getDistributionUserList,
  getDistributionUserOrders,
  setDistributionUserPermission
} from '@/api/distribution'

defineOptions({
  name: 'DistributionUser'
})

const { proxy } = getCurrentInstance()
const message = proxy.$message
const confirm = proxy.$confirm

const loading = ref(false)
const userList = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(15)
const searchForm = reactive({
  keyword: '',
  status: '',
  qualification: ''
})
const distributionConfig = reactive({
  enabled: false,
  level: 2,
  mode: 'specified'
})
const stats = ref({})
const drawerVisible = ref(false)
const currentUser = ref(null)
const orderLoading = ref(false)
const orderList = ref([])
const orderTotal = ref(0)
const orderPage = ref(1)
const orderLimit = ref(10)
const orderDateRange = ref([])
const orderSearch = reactive({
  level: 1,
  order_sn: '',
  buyer_keyword: '',
  start_time: '',
  end_time: ''
})

const distributionEnabled = computed(() => Boolean(distributionConfig.enabled))
const isAllMode = computed(() => distributionConfig.mode === 'all')
const modeText = computed(() => (isAllMode.value ? '人人分销' : '指定分销'))
const levelText = computed(() => (
  Number(distributionConfig.level) === 1 ? '一级分销' : '一二级分销'
))
const drawerTitle = computed(() => {
  if (!currentUser.value) return '分销订单详情'
  return `${currentUser.value.nickname || '用户'}的分销订单`
})

function fetchList() {
  loading.value = true
  getDistributionUserList({
    page: page.value,
    limit: limit.value,
    ...searchForm
  }).then(res => {
    const data = res.data || {}
    userList.value = data.list || []
    total.value = data.total || 0
    stats.value = data.stats || {}
    Object.assign(distributionConfig, data.distribution_config || {})
  }).finally(() => {
    loading.value = false
  })
}

function handleSearch() {
  page.value = 1
  fetchList()
}

function handleReset() {
  Object.assign(searchForm, {
    keyword: '',
    status: '',
    qualification: ''
  })
  page.value = 1
  fetchList()
}

function handleSizeChange(val) {
  limit.value = val
  page.value = 1
  fetchList()
}

function handleCurrentChange(val) {
  page.value = val
  fetchList()
}

async function handlePermission(row) {
  if (isAllMode.value) {
    message.info('人人分销模式下无需手动调整资格')
    return
  }

  const targetPermission = row.effective_distribution_enabled ? 0 : 1
  const actionText = targetPermission === 1 ? '增加' : '取消'

  try {
    await confirm(`确认${actionText}该用户的分销资格？`, '提示', {
      type: targetPermission === 1 ? 'success' : 'warning'
    })
    setPermissionChanging(row, true)
    const res = await setDistributionUserPermission({
      user_id: row.uid,
      distribution_permission: targetPermission
    })
    message.success(res.message || `分销资格已${targetPermission === 1 ? '增加' : '取消'}`)
    await fetchList()
  } catch (error) {
    // 取消确认或请求失败时保持当前列表状态。
  } finally {
    setPermissionChanging(row, false)
  }
}

function setPermissionChanging(row, value) {
  row.permissionChanging = value
}

function handleDetail(row) {
  currentUser.value = row
  drawerVisible.value = true
  resetOrderSearch()
  fetchOrders()
}

function fetchOrders() {
  if (!currentUser.value) return
  orderLoading.value = true
  getDistributionUserOrders({
    user_id: currentUser.value.uid,
    level: orderSearch.level,
    page: orderPage.value,
    limit: orderLimit.value,
    order_sn: orderSearch.order_sn,
    buyer_keyword: orderSearch.buyer_keyword,
    start_time: orderSearch.start_time,
    end_time: orderSearch.end_time
  }).then(res => {
    const data = res.data || {}
    orderList.value = data.list || []
    orderTotal.value = data.total || 0
  }).finally(() => {
    orderLoading.value = false
  })
}

function handleOrderLevelChange() {
  orderPage.value = 1
  fetchOrders()
}

function handleOrderSearch() {
  orderPage.value = 1
  fetchOrders()
}

function handleOrderReset() {
  resetOrderSearch()
  fetchOrders()
}

function handleOrderDateChange(val) {
  if (val && val.length === 2) {
    orderSearch.start_time = val[0]
    orderSearch.end_time = val[1]
  } else {
    orderSearch.start_time = ''
    orderSearch.end_time = ''
  }
}

function handleOrderSizeChange(val) {
  orderLimit.value = val
  orderPage.value = 1
  fetchOrders()
}

function handleOrderCurrentChange(val) {
  orderPage.value = val
  fetchOrders()
}

function handleDrawerClose() {
  currentUser.value = null
  orderList.value = []
  orderTotal.value = 0
}

function resetOrderSearch() {
  orderPage.value = 1
  orderDateRange.value = []
  Object.assign(orderSearch, {
    level: 1,
    order_sn: '',
    buyer_keyword: '',
    start_time: '',
    end_time: ''
  })
}

function getPermissionText(permission) {
  const value = Number(permission === undefined || permission === null ? -1 : permission)
  if (value === 1) return '指定允许'
  if (value === 0) return '禁止分销'
  return '跟随模式'
}

function formatMoney(value) {
  const amount = Number(value || 0)
  return amount.toFixed(2)
}

onBeforeMount(() => {
  fetchList()
})
</script>

<style lang="scss" scoped>
.distribution-user-page {
  background: #f5f7fb;
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 16px;
}

.page-title {
  margin: 0;
  color: #1f2f3d;
  font-size: 24px;
  font-weight: 600;
  line-height: 32px;
}

.page-subtitle {
  margin: 6px 0 0;
  color: #7b8794;
  font-size: 13px;
  line-height: 20px;
}

.mode-state {
  display: flex;
  align-items: center;
  gap: 8px;
  padding-top: 4px;
}

.panel {
  background: #fff;
  border: 1px solid #edf0f5;
  border-radius: 8px;
  box-shadow: 0 8px 18px rgba(34, 47, 62, 0.04);
}

.panel--search {
  padding: 18px 18px 4px;
  margin-bottom: 16px;
}

.panel--table {
  padding: 16px;
}

.search-input {
  width: 220px;
}

.search-select {
  width: 150px;
}

.search-select-sm {
  width: 120px;
}

.stats-row {
  margin-bottom: 16px;
}

.stat-card {
  min-height: 112px;
  padding: 18px;
  border-radius: 8px;
  background: #fff;
  border: 1px solid #edf0f5;
  box-shadow: 0 8px 18px rgba(34, 47, 62, 0.04);
}

.stat-card__label,
.stat-card__sub {
  display: block;
  color: #8492a6;
  font-size: 13px;
  line-height: 20px;
}

.stat-card__value {
  display: block;
  margin: 8px 0 6px;
  color: #1f2f3d;
  font-size: 26px;
  line-height: 34px;
}

.stat-card--green {
  border-top: 3px solid #36b37e;
}

.stat-card--blue {
  border-top: 3px solid #409eff;
}

.stat-card--gold {
  border-top: 3px solid #e6a23c;
}

.stat-card--red {
  border-top: 3px solid #f56c6c;
}

.user-cell {
  display: flex;
  align-items: center;
  gap: 10px;
}

.user-cell__body {
  min-width: 0;
}

.user-cell__name {
  overflow: hidden;
  color: #1f2f3d;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.user-cell__meta,
.permission-text,
.metric-sub {
  margin-top: 4px;
  color: #8c98a8;
  font-size: 12px;
  line-height: 18px;
}

.metric-value {
  color: #1f2f3d;
  font-size: 16px;
  font-weight: 600;
  line-height: 22px;
}

.metric-sub--strong,
.commission-text {
  color: #36b37e;
  font-weight: 600;
}

.price-text {
  color: #f56c6c;
  font-weight: 600;
}

.text-danger {
  color: #f56c6c;
}

.text-success {
  color: #36b37e;
}

.pagination-wrap {
  display: flex;
  justify-content: flex-end;
  padding-top: 16px;
}

.drawer-content {
  padding: 0 20px 20px;
}

.drawer-summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 14px;
  margin-bottom: 16px;
  border: 1px solid #edf0f5;
  border-radius: 8px;
  background: #fafbfc;
}

.drawer-summary__user {
  display: flex;
  align-items: center;
  gap: 10px;
}

.drawer-summary__name {
  color: #1f2f3d;
  font-size: 16px;
  font-weight: 600;
  line-height: 24px;
}

.drawer-summary__meta,
.drawer-summary__stats {
  color: #7b8794;
  font-size: 12px;
  line-height: 20px;
}

.drawer-summary__stats {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}

.drawer-search-form {
  padding: 14px 14px 0;
  margin-bottom: 12px;
  border: 1px solid #edf0f5;
  border-radius: 8px;
  background: #fff;
}

.drawer-search-input {
  width: 180px;
}

.drawer-date-range {
  width: 240px;
}

.buyer-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.buyer-cell small {
  color: #8c98a8;
}

.drawer-pagination {
  padding-bottom: 6px;
}

:deep(.distribution-order-drawer .el-drawer__header) {
  margin-bottom: 14px;
  padding: 18px 20px 0;
  color: #1f2f3d;
  font-weight: 600;
}

@media (max-width: 1200px) {
  .stats-row :deep(.el-col) {
    width: 50%;
    margin-bottom: 12px;
  }
}
</style>
