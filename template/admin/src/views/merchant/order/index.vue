<template>
  <div class="merchant-order-page">
    <header class="page-header">
      <div class="page-header__text">
        <h1 class="page-title">商户订单管理</h1>
        <p class="page-subtitle">查看本商户下的所有订单，支持发货和申请退款操作。</p>
      </div>
    </header>

    <!-- 统计卡片 -->
    <div class="stats-bar">
      <el-row :gutter="16">
        <el-col :xs="12" :sm="8" :md="4">
          <div class="stat-card">
            <div class="stat-card__label">待支付</div>
            <div class="stat-card__value">{{ stats.pending_pay || 0 }}</div>
          </div>
        </el-col>
        <el-col :xs="12" :sm="8" :md="4">
          <div class="stat-card">
            <div class="stat-card__label">已支付</div>
            <div class="stat-card__value primary">{{ stats.paid || 0 }}</div>
          </div>
        </el-col>
        <el-col :xs="12" :sm="8" :md="4">
          <div class="stat-card">
            <div class="stat-card__label">已完成</div>
            <div class="stat-card__value success">{{ stats.completed || 0 }}</div>
          </div>
        </el-col>
        <el-col :xs="12" :sm="8" :md="4">
          <div class="stat-card">
            <div class="stat-card__label">退款中</div>
            <div class="stat-card__value warning">{{ stats.refunding || 0 }}</div>
          </div>
        </el-col>
        <el-col :xs="12" :sm="8" :md="4">
          <div class="stat-card">
            <div class="stat-card__label">今日订单</div>
            <div class="stat-card__value">{{ stats.today_count || 0 }}</div>
          </div>
        </el-col>
        <el-col :xs="12" :sm="8" :md="4">
          <div class="stat-card">
            <div class="stat-card__label">今日金额</div>
            <div class="stat-card__value danger">¥{{ formatMoney(stats.today_amount) }}</div>
          </div>
        </el-col>
      </el-row>
    </div>

    <div class="work-surface">
      <!-- 搜索表单 -->
      <el-form ref="searchFormRef" :model="searchForm" inline class="search-form" @submit.prevent>
        <el-form-item label="订单号">
          <el-input
            v-model="searchForm.order_sn"
            clearable
            placeholder="请输入订单号"
            class="search-input"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="订单状态">
          <el-select v-model="searchForm.status" clearable placeholder="全部" class="search-select">
            <el-option label="待支付" :value="0" />
            <el-option label="已支付" :value="1" />
            <el-option label="已完成" :value="2" />
            <el-option label="已取消" :value="3" />
            <el-option label="待评价" :value="6" />
            <el-option label="退款中" :value="5" />
            <el-option label="已退款" :value="4" />
          </el-select>
        </el-form-item>
        <el-form-item label="下单时间">
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

      <!-- 列表 -->
      <el-table
        v-loading="loading"
        :data="orderList"
        class="order-table"
        :row-key="row => row.rowKey"
      >
        <el-table-column label="订单号" min-width="180" show-overflow-tooltip>
          <template #default="scope">
            <div class="order-sn">{{ scope.row.orderSn }}</div>
            <div class="table-sub">{{ scope.row.createTime }}</div>
          </template>
        </el-table-column>
        <el-table-column label="用户信息" min-width="140">
          <template #default="scope">
            <div>{{ scope.row.nickname }}</div>
            <div class="table-sub">UID: {{ scope.row.userId || '-' }}</div>
          </template>
        </el-table-column>
        <el-table-column label="订单类型" width="110" align="center">
          <template #default="scope">
            <el-tag :type="scope.row.orderTypeTagType">{{ scope.row.orderTypeText }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="商品摘要" min-width="200" show-overflow-tooltip>
          <template #default="scope">
            {{ scope.row.goodsSummary }}
          </template>
        </el-table-column>
        <el-table-column label="实付金额" width="110" align="center">
          <template #default="scope">
            <span class="price-text">¥{{ formatMoney(scope.row.payAmount) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="退款状态" width="100" align="center">
          <template #default="scope">
            <el-tag :type="scope.row.refundTagType">{{ scope.row.refundStatusText }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="订单状态" width="100" align="center">
          <template #default="scope">
            <el-tag :type="scope.row.statusTagType">{{ scope.row.statusText }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" fixed="right" align="center">
          <template #default="scope">
            <a @click="handleDetail(scope.row)">详情</a>
            <template v-if="scope.row.canDelivery">
              <el-divider direction="vertical" />
              <a class="success-link" @click="handleDelivery(scope.row)">发货</a>
            </template>
            <template v-if="scope.row.canRefundApply">
              <el-divider direction="vertical" />
              <a class="warning-link" @click="handleRefundApply(scope.row)">申请退款</a>
            </template>
          </template>
        </el-table-column>
      </el-table>

      <el-empty v-if="!loading && !orderList.length" description="暂无订单数据" :image-size="90" />

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

    <!-- 订单详情抽屉（merchant mode） -->
    <MerchantOrderDetailDrawer
      v-model:visible="detailVisible"
      :order-id="currentOrderId"
      :order-data="currentOrder"
      @delivery="handleDelivery"
      @refund-apply="handleRefundApply"
      @close="handleDetailClose"
    />

    <!-- 发货弹窗 -->
    <DeliveryDialog
      v-model:visible="deliveryVisible"
      :order="deliveryOrder"
      @success="handleDeliverySuccess"
    />

    <!-- 申请退款弹窗 -->
    <RefundApplyDialog
      v-model:visible="refundVisible"
      :order="refundOrder"
      @success="handleRefundSuccess"
    />
  </div>
</template>

<script setup>
import { onBeforeMount, reactive, ref } from 'vue'
import {
  getMerchantOrderList,
  getMerchantOrderStatistics
} from '@/api/merchantOrder'
import {
  extractListPayload,
  normalizeOrderList
} from '@/views/order/shared'
import MerchantOrderDetailDrawer from './components/MerchantOrderDetailDrawer'
import DeliveryDialog from './components/DeliveryDialog'
import RefundApplyDialog from './components/RefundApplyDialog'

defineOptions({
  name: 'MerchantOrderList'
})

const createSearchForm = () => ({
  order_sn: '',
  status: '',
  start_time: '',
  end_time: ''
})

const createDefaultStats = () => ({
  pending_pay: 0,
  paid: 0,
  completed: 0,
  refunding: 0,
  refunded: 0,
  today_count: 0,
  today_amount: 0
})

const loading = ref(false)
const orderList = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(15)
const dateRange = ref([])
const stats = reactive(createDefaultStats())
const searchFormRef = ref(null)
const searchForm = reactive(createSearchForm())
const detailVisible = ref(false)
const currentOrderId = ref('')
const currentOrder = ref(null)
const deliveryVisible = ref(false)
const deliveryOrder = ref(null)
const refundVisible = ref(false)
const refundOrder = ref(null)

function getList() {
  loading.value = true
  const params = {
    page: page.value,
    limit: limit.value,
    ...searchForm
  }
  getMerchantOrderList(params).then(res => {
    const payload = extractListPayload(res)
    orderList.value = normalizeOrderList(payload.list).map(item => ({
      ...item,
      canRefundApply: [1, 6].includes(item.status) && item.refundStatus === 0
    }))
    total.value = payload.total || orderList.value.length
  }).catch(() => {
    orderList.value = []
    total.value = 0
  }).finally(() => {
    loading.value = false
  })
}

function getStats() {
  getMerchantOrderStatistics().then(res => {
    const data = res && res.data ? res.data : {}
    Object.assign(stats, createDefaultStats(), data)
  }).catch(() => {})
}

function handleDateChange(val) {
  if (val && val.length === 2) {
    searchForm.start_time = val[0]
    searchForm.end_time = val[1]
  } else {
    searchForm.start_time = ''
    searchForm.end_time = ''
  }
}

function handleSearch() {
  page.value = 1
  getList()
}

function handleReset() {
  Object.assign(searchForm, createSearchForm())
  dateRange.value = []
  page.value = 1
  searchFormRef.value?.clearValidate?.()
  getList()
}

function handleSizeChange(val) {
  limit.value = val
  page.value = 1
  getList()
}

function handleCurrentChange(val) {
  page.value = val
  getList()
}

function handleDetail(row) {
  currentOrderId.value = row.id || row.orderId
  currentOrder.value = row
  detailVisible.value = true
}

function handleDetailClose() {
  currentOrder.value = null
}

function handleDelivery(row) {
  deliveryOrder.value = row
  deliveryVisible.value = true
}

function handleDeliverySuccess() {
  deliveryVisible.value = false
  getList()
}

function handleRefundApply(row) {
  refundOrder.value = row
  refundVisible.value = true
}

function handleRefundSuccess() {
  refundVisible.value = false
  getList()
  getStats()
}

function formatMoney(value) {
  return Number(value || 0).toFixed(2)
}

onBeforeMount(() => {
  getList()
  getStats()
})
</script>

<style lang="scss" scoped>
.merchant-order-page {
  min-height: 100%;
  box-sizing: border-box;
}

.page-header {
  margin-bottom: 20px;
}

.page-title {
  margin: 0 0 8px;
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--curator-on-surface);
}

.page-subtitle {
  margin: 0;
  font-size: 0.875rem;
  color: var(--curator-on-surface-variant);
  line-height: 1.5;
}

.stats-bar {
  margin-bottom: 20px;
}

.stat-card {
  background: var(--curator-surface-card, #fff);
  border-radius: 10px;
  padding: 14px 16px;
  text-align: center;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);

  &__label {
    font-size: 12px;
    color: #909399;
    margin-bottom: 6px;
  }

  &__value {
    font-size: 22px;
    font-weight: 700;
    color: #303133;

    &.primary { color: #409eff; }
    &.success { color: #67c23a; }
    &.warning { color: #e6a23c; }
    &.danger  { color: #f56c6c; }
  }
}

.work-surface {
  background: var(--curator-surface-card, #fff);
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
}

.search-form {
  margin-bottom: 8px;

  :deep(.el-form-item) {
    margin-bottom: 16px;
    margin-right: 16px;
  }
}

.search-input { width: 190px; }
.search-select { width: 120px; }

.order-table {
  margin-top: 8px;
}

.order-sn {
  font-weight: 600;
  color: #303133;
}

.table-sub {
  font-size: 12px;
  color: #909399;
  line-height: 1.5;
}

.price-text {
  color: #f56c6c;
  font-weight: 600;
}

.pagination-wrap {
  padding: 16px 0 0;
  display: flex;
  justify-content: flex-end;
}

a {
  color: #409eff;
  cursor: pointer;
  font-size: 13px;

  &:hover { opacity: 0.8; }
}

.success-link { color: #67c23a; }
.warning-link { color: #e6a23c; }
</style>
