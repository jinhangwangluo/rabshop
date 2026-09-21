<template>
  <div class="refund-page">
    <header class="page-header">
      <div class="page-header__text">
        <h1 class="page-title">退款工作台</h1>
        <p class="page-subtitle">集中查看退款申请进度，支持按订单号、用户、商品、订单类型和退款状态筛选。</p>
      </div>
    </header>

    <div class="panel panel--search">
      <el-form ref="searchForm" :model="searchForm" inline class="search-form" @submit.prevent>
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

            style="width: 240px"
            @change="handleDateChange"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
          <el-button :icon="Refresh" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <div class="stats-bar">
      <el-row :gutter="16">
        <el-col :xs="12" :sm="12" :md="6">
          <div class="stat-card stat-card--green">
            <div class="stat-card__label">退款单数</div>
            <div class="stat-card__value">{{ stats.total || 0 }}</div>
          </div>
        </el-col>
        <el-col :xs="12" :sm="12" :md="6">
          <div class="stat-card stat-card--gold">
            <div class="stat-card__label">退款处理中</div>
            <div class="stat-card__value warning">{{ stats.pending || 0 }}</div>
          </div>
        </el-col>
        <el-col :xs="12" :sm="12" :md="6">
          <div class="stat-card stat-card--purple">
            <div class="stat-card__label">已退款</div>
            <div class="stat-card__value primary">{{ stats.success || 0 }}</div>
          </div>
        </el-col>
        <el-col :xs="12" :sm="12" :md="6">
          <div class="stat-card stat-card--red">
            <div class="stat-card__label">退款金额</div>
            <div class="stat-card__value danger">¥{{ formatMoney(stats.totalRefundAmount) }}</div>
          </div>
        </el-col>
      </el-row>
    </div>

    <div class="panel panel--table">
      <el-table
        v-loading="loading"
        :data="refundList"
        class="refund-table"
        :row-key="row => row.rowKey"
      >
        <el-table-column label="订单号" min-width="180" show-overflow-tooltip>
          <template #default="{ row }">
            <div class="order-sn">{{ row.orderSn }}</div>
            <div class="table-sub">{{ row.refundApplyTime || row.createTime }}</div>
          </template>
        </el-table-column>
        <el-table-column label="用户信息" min-width="150">
          <template #default="{ row }">
            <div>{{ row.nickname }}</div>
            <div class="table-sub">UID: {{ row.userId || '-' }}</div>
            <div class="table-sub">{{ row.userPhone || '-' }}</div>
          </template>
        </el-table-column>
        <el-table-column label="商品信息" min-width="260">
          <template #default="{ row }">
            <div class="goods-cell">
              <el-image :src="getGoodsImage(row)" fit="cover" class="goods-cell__image">
                <template #error>
                  <div class="goods-cell__placeholder">
                    <LegacyIcon name="el-icon-picture-outline" />
                  </div>
                </template>
              </el-image>
              <div class="goods-cell__main">
                <div class="goods-cell__name">{{ row.goodsSummary }}</div>
                <div class="table-sub">
                  <span>{{ getGoodsSpec(row) }}</span>
                  <span>x{{ getGoodsQuantity(row) }}</span>
                </div>
                <div class="table-sub">{{ row.orderTypeText || '-' }}</div>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="退款金额" width="120" align="center">
          <template #default="{ row }">
            <span class="price-text">¥{{ formatMoney(row.refundAmount || row.payAmount) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="退款原因" min-width="180" show-overflow-tooltip>
          <template #default="{ row }">
            {{ row.refundReason || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="退款状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag  :type="row.refundTagType">{{ row.refundStatusText }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="订单状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag  :type="row.statusTagType">{{ row.statusText }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="220" fixed="right" align="center">
          <template #default="{ row }">
            <a @click="handleDetail(row)">详情</a>
            <template v-if="row.canAuditRefund">
              <el-divider direction="vertical" />
              <a class="success-link" @click="handleApprove(row)">通过</a>
              <el-divider direction="vertical" />
              <a class="danger-link" @click="handleReject(row)">拒绝</a>
            </template>
            <el-divider direction="vertical" />
            <a class="warning-link" @click="handleGoOrder(row)">订单页</a>
          </template>
        </el-table-column>
      </el-table>

      <el-empty v-if="!loading && !refundList.length" description="暂无退款数据" :image-size="90" />

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
    </div>

    <OrderDetailDrawer
      v-model="detailVisible"
      :order-id="currentOrderId"
      :order-data="currentOrder"
      :show-refund-entry="false"
      @close="handleDetailClose"
    />

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
        <el-button @click="rejectVisible = false">取 消</el-button>
        <el-button type="danger" :loading="rejectLoading" @click="handleRejectSubmit">确认拒绝</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Refresh, Search } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { approveOrderRefund, getRefundList, getRefundStatistics, rejectOrderRefund } from '@/api/order'
import { extractListPayload, normalizeOrderList } from '../shared'
import OrderDetailDrawer from '../order/components/OrderDetailDrawer'

defineOptions({
  name: 'MerchantOrderRefundList'
})

const createSearchForm = () => ({
  order_sn: '',
  user_keyword: '',
  goods_name: '',
  refund_status: '',
  order_type: '',
  start_time: '',
  end_time: ''
})

const route = useRoute()
const router = useRouter()

const loading = ref(false)
const refundList = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(15)
const dateRange = ref([])
const stats = ref({
  total: 0,
  pending: 0,
  success: 0,
  totalRefundAmount: 0
})
const searchForm = ref(createSearchForm())
const detailVisible = ref(false)
const currentOrderId = ref('')
const currentOrder = ref(null)
const rejectVisible = ref(false)
const rejectLoading = ref(false)
const rejectTarget = ref(null)
const rejectForm = ref({
  reason: ''
})
const rejectRules = ref({
  reason: [{ required: true, message: '请填写拒绝原因', trigger: 'blur' }]
})
const rejectFormRef = ref(null)

watch(() => route.query.order_sn, () => {
  syncRouteOrderSn()
  fetchData()
})

onMounted(() => {
  syncRouteOrderSn()
  fetchData()
})

function syncRouteOrderSn() {
  const routeOrderSn = route.query.order_sn || ''
  searchForm.value.order_sn = routeOrderSn
}

function fetchData() {
  loading.value = true
  const params = {
    page: page.value,
    limit: limit.value,
    ...searchForm.value
  }

  Promise.all([
    getRefundList(params),
    getRefundStatistics(params)
  ]).then(([listRes, statsRes]) => {
    const payload = extractListPayload(listRes)
    refundList.value = normalizeOrderList(payload.list)
    total.value = payload.total || refundList.value.length
    const statsData = statsRes && statsRes.data ? statsRes.data : statsRes || {}
    stats.value = {
      total: Number(statsData.total || 0),
      pending: Number(statsData.pending || 0),
      success: Number(statsData.success || 0),
      totalRefundAmount: Number(statsData.totalRefundAmount || 0)
    }
  }).catch(() => {
    refundList.value = []
    total.value = 0
    stats.value = {
      total: 0,
      pending: 0,
      success: 0,
      totalRefundAmount: 0
    }
  }).finally(() => {
    loading.value = false
  })
}

function handleDateChange(val) {
  if (val && val.length === 2) {
    searchForm.value.start_time = val[0]
    searchForm.value.end_time = val[1]
  } else {
    searchForm.value.start_time = ''
    searchForm.value.end_time = ''
  }
}

function handleSearch() {
  page.value = 1
  fetchData()
}

function handleReset() {
  searchForm.value = createSearchForm()
  dateRange.value = []
  page.value = 1
  if (route.query.order_sn) {
    router.replace({ name: 'MerchantOrderRefundList' })
    return
  }
  fetchData()
}

function handleSizeChange(val) {
  limit.value = val
  page.value = 1
  fetchData()
}

function handleCurrentChange(val) {
  page.value = val
  fetchData()
}

function handleDetail(row) {
  currentOrderId.value = resolveOrderId(row)
  currentOrder.value = row
  detailVisible.value = true
}

function handleDetailClose() {
  currentOrder.value = null
}

function handleApprove(row) {
  const orderId = resolveOrderId(row)
  if (!orderId) {
    ElMessage.error('订单信息异常，无法处理退款')
    return
  }

  ElMessageBox.confirm(`确定通过订单 ${row.orderSn || row.order_sn || '-'} 的退款申请吗？`, '退款审核', { type: 'warning' }).then(() => {
    approveOrderRefund(orderId).then(res => {
      ElMessage.success((res && res.message) || '退款已通过')
      fetchData()
    })
  }).catch(() => {})
}

function handleReject(row) {
  rejectTarget.value = row
  rejectForm.value = { reason: '' }
  rejectVisible.value = true
}

function handleRejectSubmit() {
  rejectFormRef.value.validate(valid => {
    if (!valid || !rejectTarget.value) return

    const orderId = resolveOrderId(rejectTarget.value)
    if (!orderId) {
      ElMessage.error('订单信息异常，无法处理退款')
      return
    }

    rejectLoading.value = true
    rejectOrderRefund(orderId, {
      reason: rejectForm.value.reason
    }).then(res => {
      ElMessage.success((res && res.message) || '已拒绝退款申请')
      rejectVisible.value = false
      fetchData()
    }).finally(() => {
      rejectLoading.value = false
    })
  })
}

function handleGoOrder(row) {
  const orderSn = row.orderSn || row.order_sn || ''
  router.push({
    name: 'MerchantOrderList',
    query: orderSn ? { order_sn: orderSn } : {}
  })
}

function resolveOrderId(row) {
  return row ? (row.orderId || row.order_id || row.id || 0) : 0
}

function getFirstGoods(row) {
  return row && Array.isArray(row.goods) && row.goods.length ? row.goods[0] : {}
}

function getGoodsImage(row) {
  return getFirstGoods(row).image || ''
}

function getGoodsSpec(row) {
  const goods = getFirstGoods(row)
  return goods.goodsTypeText || goods.goodsSpec || '-'
}

function getGoodsQuantity(row) {
  return getFirstGoods(row).quantity || 1
}

function formatMoney(value) {
  return Number(value || 0).toFixed(2)
}
</script>

<style lang="scss" scoped>
.refund-page {
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
  color: var(--text-title);
}

.page-subtitle {
  margin: 0;
  font-size: 0.875rem;
  color: var(--text-secondary);
  line-height: 1.5;
}

.panel {
  background: var(--prev-bg-white);
  border-radius: 12px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
  margin-bottom: 16px;

  &--search {
    padding: 20px 24px 4px;
  }

  &--table {
    padding: 16px 24px;
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

.stats-bar {
  margin-bottom: 16px;
}

.stat-card {
  position: relative;
  background: var(--prev-bg-white);
  border: 1px solid var(--ds-outline-ghost);
  border-radius: 12px;
  padding: 18px 20px;
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
  &--purple::before { background: linear-gradient(180deg, #a78bfa 0%, #7c3aed 100%); }
  &--red::before { background: linear-gradient(180deg, #fc8181 0%, #e53e3e 100%); }

  &__label {
    font-size: 12px;
    color: var(--text-secondary);
    margin-bottom: 8px;
  }

  &__value {
    font-size: 24px;
    font-weight: 700;
    color: var(--text-title);

    &.primary { color: var(--ds-primary); }
    &.warning { color: #d97706; }
    &.danger { color: #f56c6c; }
  }
}

.refund-table {
  margin-top: 8px;
}

.order-sn {
  font-weight: 600;
  color: var(--text-title);
}

.goods-cell {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.goods-cell__image {
  width: 54px;
  height: 54px;
  flex-shrink: 0;
  border: 1px solid var(--ds-outline-ghost);
  border-radius: 6px;
  background: var(--prev-bg-white);
  color: var(--text-secondary);
  font-size: 22px;
}

.goods-cell__placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.goods-cell__main {
  flex: 1;
  min-width: 0;
}

.goods-cell__name {
  color: var(--text-title);
  font-weight: 600;
  line-height: 1.5;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.table-sub {
  font-size: 12px;
  color: var(--text-secondary);
  line-height: 1.5;
}

.price-text {
  color: var(--text-price);
  font-weight: 600;
}

.pagination-wrap {
  padding: 16px 0 0;
  display: flex;
  justify-content: flex-end;
}

.warning-link {
  color: #e6a23c;
}

.success-link {
  color: var(--text-success);
}

.danger-link {
  color: var(--text-danger);
}

a {
  color: var(--ds-primary);
  cursor: pointer;
  font-size: 13px;

  &:hover {
    opacity: 0.8;
  }
}
</style>
