<template>
  <div class="refund-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">退款审核</h1>
        <p class="page-curator-subtitle">
          独立处理订单退款，支持按订单号、用户、商品、退款状态筛选，并联动订单详情查看完整上下文。
        </p>
      </div>
    </header>

    <!-- 搜索模块 -->
    <div class="panel panel--search">
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
        <el-form-item label="用户信息">
          <el-input
            v-model="searchForm.user_keyword"
            clearable
            placeholder="昵称/手机号/用户ID"
            class="search-input"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="商品名称">
          <el-input
            v-model="searchForm.goods_name"
            clearable
            placeholder="请输入商品名称"
            class="search-input"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="退款状态">
          <el-select v-model="searchForm.refund_status" clearable placeholder="全部" class="search-select-sm">
            <el-option label="退款中" :value="1" />
            <el-option label="已退款" :value="2" />
          </el-select>
        </el-form-item>
        <el-form-item label="订单类型">
          <el-select v-model="searchForm.order_type" clearable placeholder="全部" class="search-select-sm">
            <el-option label="内容订单" :value="1" />
            <el-option label="商品订单" :value="2" />
            <el-option label="专题订单" :value="3" />
          </el-select>
        </el-form-item>
        <el-form-item label="申请时间">
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
          <el-button type="primary" class="btn-curator-primary" icon="el-icon-search" @click="handleSearch">查询</el-button>
          <el-button class="btn-curator-reset" icon="el-icon-refresh" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <!-- 统计模块 -->
    <el-row :gutter="16" class="mb12">
      <el-col :span="6">
        <div class="stat-badge stat-badge--green">
          <span class="stat-badge__label">退款单数</span>
          <div class="stat-badge__body">
            <span class="stat-badge__value">{{ stats.total || 0 }}</span>
            <span class="stat-badge__sub stat-badge__sub--up">全部</span>
          </div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-badge stat-badge--gold">
          <span class="stat-badge__label">待审核</span>
          <div class="stat-badge__body">
            <span class="stat-badge__value">{{ stats.pending || 0 }}</span>
            <span class="stat-badge__sub stat-badge__sub--warn">待处理</span>
          </div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-badge stat-badge--purple">
          <span class="stat-badge__label">已退款</span>
          <div class="stat-badge__body">
            <span class="stat-badge__value">{{ stats.success || 0 }}</span>
            <span class="stat-badge__sub stat-badge__sub--tag">已完成</span>
          </div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-badge stat-badge--red">
          <span class="stat-badge__label">退款金额</span>
          <div class="stat-badge__body">
            <span class="stat-badge__value">¥{{ formatMoney(stats.totalRefundAmount) }}</span>
            <span class="stat-badge__sub stat-badge__sub--down">累计</span>
          </div>
        </div>
      </el-col>
    </el-row>

    <!-- 列表模块 -->
    <div class="panel panel--table">
      <el-table
        v-loading="loading"
        :data="refundList"
        class="refund-curator-table"
        :row-key="getRowKey"
      >
        <el-table-column label="退款订单号" min-width="200" show-overflow-tooltip>
          <template #default="scope">
            <div class="order-sn">{{ scope.row.refund_sn }}</div>
            <div class="table-sub">订单号：{{ scope.row.order_sn }}</div>
            <div class="table-sub">{{ scope.row.refundApplyTime || scope.row.createTime }}</div>
          </template>
        </el-table-column>
        <el-table-column label="用户信息" min-width="130">
          <template #default="scope">
            <div class="user-name">{{ scope.row.nickname }}</div>
            <div class="table-sub">UID: {{ scope.row.userId || '-' }}</div>
            <div class="table-sub">{{ scope.row.userPhone || '-' }}</div>
          </template>
        </el-table-column>
        <el-table-column label="商品信息" min-width="260">
          <template #default="scope">
            <div class="goods-cell">
              <el-image :src="scope.row.goodsImage" fit="cover" class="goods-cell__image">
                <template #error>
                  <div class="goods-cell__placeholder">
                    <LegacyIcon name="el-icon-picture-outline" />
                  </div>
                </template>
              </el-image>
              <div class="goods-cell__main">
                <div class="goods-cell__name">{{ scope.row.goodsSummary }}</div>
                <div class="table-sub">
                  <span>{{ scope.row.goodsSpec || '默认规格' }}</span>
                  <span>x{{ scope.row.goodsQuantity || 1 }}</span>
                </div>
                <div class="table-sub">{{ scope.row.orderTypeText || '-' }}</div>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="商户信息" min-width="180">
          <template #default="scope">
            <div class="merchant-cell">
              <el-avatar
                :size="34"
                :src="scope.row.merchantAvatar"
                icon="el-icon-s-shop"
                class="merchant-cell__avatar"
              />
              <div class="merchant-cell__main">
                <div class="merchant-cell__name">{{ scope.row.merchantName || '-' }}</div>
                <div class="table-sub">ID: {{ scope.row.merchantId || '-' }}</div>
                <div class="table-sub">{{ scope.row.merchantPhone || scope.row.merchantContactName || '-' }}</div>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="退款金额" width="110" align="center">
          <template #default="scope">
            <span class="price-text">¥{{ formatMoney(scope.row.refundAmount || scope.row.payAmount) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="退款原因" min-width="170" show-overflow-tooltip>
          <template #default="scope">
            <span class="refund-reason">{{ scope.row.refundReason || '-' }}</span>
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
        <el-table-column label="操作" width="180" fixed="right" align="center">
          <template #default="scope">
            <div class="table-actions">
              <a class="table-actions__link" @click="handleDetail(scope.row)">详情</a>
              <template v-if="scope.row.canAuditRefund">
                <a
                  class="table-actions__link table-actions__link--success"
                  @click="handleApprove(scope.row)"
                >
                  通过
                </a>
                <a
                  class="table-actions__link table-actions__link--danger"
                  @click="handleReject(scope.row)"
                >
                  拒绝
                </a>
              </template>

            </div>
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

    <el-dialog
      v-model="rejectVisible"
      title="拒绝退款"
      width="460px"
      :close-on-click-modal="false"
    >
      <el-form ref="rejectFormRef" :model="rejectForm" :rules="rejectRules" label-width="90px">
        <el-form-item label="拒绝原因" prop="reason">
          <el-input
            v-model="rejectForm.reason"
            type="textarea"
            :rows="4"
            placeholder="请填写拒绝原因"
            maxlength="200"
            show-word-limit
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <span>
          <el-button @click="rejectVisible = false">取 消</el-button>
          <el-button
            type="danger"
            class="reject-confirm-btn"
            :loading="rejectLoading"
            @click="handleRejectSubmit"
          >确认拒绝</el-button>
        </span>
      </template>
    </el-dialog>

    <OrderDetailDrawer
      v-model:visible="detailVisible"
      :order-id="currentOrderId"
      :order-sn="currentOrderSn"
      :order-data="currentOrder"
      :show-refund-entry="false"
      @refresh="getList"
      @close="handleDetailClose"
    />
  </div>
</template>

<script setup>
import { onBeforeMount, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { approveOrderRefund, getOrderRefundList, rejectOrderRefund } from '@/api/order'
import OrderDetailDrawer from '../order/components/OrderDetailDrawer'
import {
  buildOrderActionPayload,
  computeRefundStats,
  extractListPayload,
  normalizeOrderList
} from '../shared'

defineOptions({
  name: 'OrderRefundList'
})

const route = useRoute()

const searchFormRef = ref(null)
const rejectFormRef = ref(null)
const loading = ref(false)
const refundList = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(15)
const dateRange = ref([])
const stats = reactive({
  total: 0,
  pending: 0,
  success: 0,
  totalRefundAmount: 0
})
const searchForm = reactive({
  order_sn: '',
  user_keyword: '',
  goods_name: '',
  refund_status: '',
  order_type: '',
  start_time: '',
  end_time: ''
})
const detailVisible = ref(false)
const currentOrderId = ref('')
const currentOrderSn = ref('')
const currentOrder = ref(null)
const rejectVisible = ref(false)
const rejectLoading = ref(false)
const rejectTarget = ref(null)
const rejectForm = reactive({
  reason: ''
})
const rejectRules = {
  reason: [{ required: true, message: '请填写拒绝原因', trigger: 'blur' }]
}

onBeforeMount(() => {
  const routeOrderSn = route.query.order_sn
  const routeRefundStatus = route.query.refund_status
  if (routeOrderSn) {
    searchForm.order_sn = String(routeOrderSn)
  }
  if (routeRefundStatus !== undefined && routeRefundStatus !== '') {
    searchForm.refund_status = Number(routeRefundStatus)
  }
  void getList()
})

async function getList() {
  loading.value = true
  const params = {
    page: page.value,
    limit: limit.value,
    ...searchForm
  }

  try {
    const res = await getOrderRefundList(params)
    const payload = extractListPayload(res)
    const list = normalizeOrderList(payload.list)

    refundList.value = list
    total.value = payload.total || list.length
    Object.assign(stats, resolveRefundStats(payload.stats, list))
  } catch {
    refundList.value = []
    total.value = 0
    Object.assign(stats, computeRefundStats([]))
  } finally {
    loading.value = false
  }
}

function resolveRefundStats(remoteStats, list) {
  const fallback = computeRefundStats(list)
  if (!remoteStats || typeof remoteStats !== 'object') {
    return fallback
  }

  return {
    total: Number(remoteStats.total || fallback.total || 0),
    pending: Number(remoteStats.pending || fallback.pending || 0),
    success: Number(remoteStats.success || fallback.success || 0),
    totalRefundAmount: Number(remoteStats.totalRefundAmount || fallback.totalRefundAmount || 0)
  }
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
  void getList()
}

function handleReset() {
  Object.assign(searchForm, {
    order_sn: '',
    user_keyword: '',
    goods_name: '',
    refund_status: '',
    order_type: '',
    start_time: '',
    end_time: ''
  })
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

function handleDetail(row) {
  currentOrderId.value = row.orderId || row.order_id || row.id
  currentOrderSn.value = row.orderSn
  currentOrder.value = row
  detailVisible.value = true
}

function handleDetailClose() {
  currentOrder.value = null
}

async function handleApprove(row) {
  try {
    await ElMessageBox.confirm(`确定通过订单 ${row.orderSn} 的退款申请吗？`, '退款审核', { type: 'warning' })
  } catch {
    return
  }

  await approveOrderRefund(buildOrderActionPayload(row)).then(res => {
    ElMessage.success(res.message || '退款已通过')
    void getList()
  })
}

function handleReject(row) {
  rejectTarget.value = row
  Object.assign(rejectForm, { reason: '' })
  rejectVisible.value = true
}

async function handleRejectSubmit() {
  if (!rejectFormRef.value) return
  try {
    const valid = await rejectFormRef.value.validate()
    if (!valid || !rejectTarget.value) return
  } catch {
    return
  }

  rejectLoading.value = true
  try {
    const res = await rejectOrderRefund(buildOrderActionPayload(rejectTarget.value, {
      reason: rejectForm.reason,
      refund_reason: rejectForm.reason,
      fail_msg: rejectForm.reason,
      remark: rejectForm.reason
    }))
    ElMessage.success(res.message || '已拒绝退款申请')
    rejectVisible.value = false
    await getList()
  } finally {
    rejectLoading.value = false
  }
}

function getRowKey(row) {
  return row.rowKey
}

function formatMoney(value) {
  return Number(value || 0).toFixed(2)
}
</script>

<style lang="scss" scoped>
.refund-curator-page {
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
  max-width: 620px;
}

/* ---- 三模块 panel ---- */
.panel {
  background: var(--curator-surface-card);
  border-radius: 12px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
  margin-bottom: 12px;

  &--search {
    padding: 20px 24px 4px;
  }

  &--table {
    padding: 16px 24px;
  }
}

.mb12 {
  margin-bottom: 12px;
}

.page-alert {
  margin-bottom: 16px;
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

.search-form {
  margin-bottom: 8px;

  :deep(.el-form-item) {
    margin-bottom: 16px;
    margin-right: 16px;
  }
}

.search-input {
  width: 190px;
}

.search-select-sm {
  width: 120px;
}

/* ---- 统计徽章卡片 ---- */
.stat-badge {
  position: relative;
  background: var(--curator-surface-card);
  border: 1px solid var(--curator-outline);
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

  &--green::before  { background: linear-gradient(180deg, #43d79a 0%, #1db87b 100%); }
  &--gold::before   { background: linear-gradient(180deg, #fbbf24 0%, #d97706 100%); }
  &--purple::before { background: linear-gradient(180deg, #a78bfa 0%, #7c3aed 100%); }
  &--red::before    { background: linear-gradient(180deg, #fc8181 0%, #e53e3e 100%); }
}

.stat-badge__label {
  display: block;
  font-size: 13px;
  color: var(--curator-on-surface-variant);
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
  color: var(--curator-on-surface);
  line-height: 1;
  letter-spacing: -0.5px;
}

.stat-badge__sub {
  font-size: 13px;
  font-weight: 500;
  line-height: 1;

  &--up   { color: #1db87b; }
  &--down { color: #f56c6c; }
  &--tag  { color: #7c3aed; }
  &--warn { color: #d97706; }
}

.refund-curator-table {
  margin-top: 8px;

  :deep(.el-table__header th) {
    color: var(--curator-on-surface-variant);
    font-weight: 500;
    background: rgba(242, 244, 247, 0.5);
  }

  :deep(.el-table__row:hover > td) {
    background: var(--curator-surface-low) !important;
  }
}

.order-sn {
  font-weight: 600;
  color: #303133;
  line-height: 1.5;
}

.user-name,
.refund-reason {
  color: #303133;
  line-height: 1.6;
}

.goods-cell,
.merchant-cell {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.goods-cell__image {
  width: 54px;
  height: 54px;
  flex-shrink: 0;
  border-radius: 4px;
  background: var(--dashboard-surface-muted);
  color: var(--dashboard-text-muted);
  font-size: 22px;
}

.goods-cell__placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.goods-cell__main,
.merchant-cell__main {
  flex: 1;
  min-width: 0;
}

.goods-cell__name,
.merchant-cell__name {
  color: #303133;
  font-weight: 600;
  line-height: 1.5;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.goods-cell__name {
  white-space: normal;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
}

.merchant-cell__avatar {
  flex-shrink: 0;
  background: var(--dashboard-surface-muted);
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

.table-actions {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-wrap: wrap;
  gap: 6px;
  max-width: 150px;

  &__link {
    min-width: 34px;
    line-height: 20px;
    text-align: center;
  }

  &__link--success {
    color: #13b887;
  }

  &__link--danger {
    color: #d93026;
  }
}

:deep(.reject-confirm-btn.el-button--danger) {
  color: #fff;
  background-color: #d93026;
  border-color: #d93026;

  &:hover,
  &:focus {
    color: #fff;
    background-color: #c5221f;
    border-color: #c5221f;
  }

  &.is-disabled,
  &.is-disabled:hover,
  &.is-disabled:focus {
    color: #fff;
    background-color: #f1a5a0;
    border-color: #f1a5a0;
  }
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

  &:hover {
    opacity: 0.8;
  }
}
</style>
