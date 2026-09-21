<template>
  <div class="merchant-order-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">订单管理</h1>
        <p class="page-curator-subtitle">查看本商户订单，支持筛选、详情查看、发货、退款申请与退款跟踪。</p>
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
        <el-form-item label="订单类型">
          <el-select v-model="searchForm.order_type" clearable placeholder="全部" class="search-select-sm">
            <el-option label="内容订单" :value="1" />
            <el-option label="商品订单" :value="2" />
            <el-option label="专题订单" :value="3" />
          </el-select>
        </el-form-item>
        <el-form-item label="订单状态">
          <el-select v-model="searchForm.status" clearable placeholder="全部" class="search-select-sm">
            <el-option label="待支付" :value="0" />
            <el-option label="已支付" :value="1" />
            <el-option label="已完成" :value="2" />
            <el-option label="已取消" :value="3" />
            <el-option label="待评价" :value="6" />
            <el-option label="退款中" :value="5" />
            <el-option label="已退款" :value="4" />
          </el-select>
        </el-form-item>
        <el-form-item label="支付方式">
          <el-select v-model="searchForm.pay_type" clearable placeholder="全部" class="search-select-sm">
            <el-option label="微信支付" value="wechat" />
            <el-option label="支付宝" value="alipay" />
            <el-option label="余额支付" value="yue" />
            <el-option label="免支付" value="free" />
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

            style="width: 240px"
            @change="handleDateChange"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" class="btn-curator-primary" :icon="Search" @click="handleSearch">查询</el-button>
          <el-button class="btn-curator-reset" :icon="Refresh" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <el-row :gutter="16" class="mb16">
      <el-col :span="6">
        <div class="stat-badge stat-badge--green">
          <span class="stat-badge__label">订单总数</span>
          <div class="stat-badge__body">
            <span class="stat-badge__value">{{ stats.total || 0 }}</span>
            <span class="stat-badge__sub stat-badge__sub--up">全部</span>
          </div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-badge stat-badge--purple">
          <span class="stat-badge__label">已支付订单</span>
          <div class="stat-badge__body">
            <span class="stat-badge__value">{{ stats.paidCount || 0 }}</span>
            <span class="stat-badge__sub stat-badge__sub--tag">已支付</span>
          </div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-badge stat-badge--gold">
          <span class="stat-badge__label">退款处理中</span>
          <div class="stat-badge__body">
            <span class="stat-badge__value">{{ stats.refundingCount || 0 }}</span>
            <span class="stat-badge__sub stat-badge__sub--warn">处理中</span>
          </div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-badge stat-badge--gray">
          <span class="stat-badge__label">支付金额</span>
          <div class="stat-badge__body">
            <span class="stat-badge__value">¥{{ formatMoney(stats.paidAmount) }}</span>
            <span class="stat-badge__sub stat-badge__sub--down">累计</span>
          </div>
        </div>
      </el-col>
    </el-row>

    <div v-loading="loading" class="panel panel--table">
      <div class="order-list-container">
        <div class="order-thead">
          <div class="thead-grid">
            <div class="col-goods">商品信息</div>
            <div class="col-price">单价/数量</div>
            <div class="col-pay">实付金额</div>
            <div class="col-user">用户信息</div>
            <div class="col-delivery">配送信息</div>
            <div class="col-refund">退款状态</div>
            <div class="col-status">订单状态</div>
            <div class="col-action">操作</div>
          </div>
        </div>

        <el-empty v-if="!loading && !orderList.length" description="暂无订单数据" :image-size="80" style="padding: 40px 0;" />

        <div v-for="row in orderList" :key="row.rowKey" class="order-group">
          <div class="order-group__head">
            <span class="og-sn">订单号：{{ row.orderSn }}</span>
            <span class="og-type">订单类型：</span>
            <el-tag size="mini" :type="row.orderTypeTagType || 'info'" class="og-type-tag">
              {{ row.orderTypeText }}
            </el-tag>
            <span class="og-time">下单时间：{{ row.createTime }}</span>
            <div class="og-actions">
              <a @click="handleDetail(row)">详情</a>
            </div>
          </div>

          <div class="order-group__body">
            <div class="ogb-grid">
              <div class="ogb-col col-goods">
                <div class="goods-thumb-wrap">
                  <el-image :src="getGoodsImage(row)" class="goods-thumb">
                    <template #error>
                      <div class="goods-thumb-placeholder">
                        <LegacyIcon name="el-icon-picture-outline" />
                      </div>
                    </template>
                  </el-image>
                </div>
                <div class="goods-info">
                  <div class="goods-name">{{ row.goodsSummary }}</div>
                  <div class="goods-spec">{{ getGoodsSpec(row) }}</div>
                </div>
              </div>

              <div class="ogb-col col-price">
                <div class="col-text">¥{{ formatMoney(getGoodsPrice(row)) }}</div>
                <div class="col-sub">x{{ getGoodsQuantity(row) }}</div>
              </div>

              <div class="ogb-col col-pay">
                <div class="col-text">¥{{ formatMoney(row.payAmount) }}</div>
                <div class="col-sub">{{ row.payTypeText }}</div>
              </div>

              <div class="ogb-col col-user">
                <div class="user-name">{{ row.nickname }}</div>
                <div class="col-sub">UID: {{ row.userId || '-' }}</div>
                <div class="col-sub">{{ row.userPhone || '-' }}</div>
              </div>

              <div class="ogb-col col-delivery">
                <div class="col-text">{{ getDeliveryText(row) }}</div>
                <div v-if="row.expressNo && row.expressNo !== '-'" class="col-sub">{{ row.expressNo }}</div>
              </div>

              <div class="ogb-col col-refund">
                <el-tag  effect="plain" :type="row.refundTagType || 'info'">{{ row.refundStatusText }}</el-tag>
              </div>

              <div class="ogb-col col-status">
                <el-tag  effect="plain" :type="row.statusTagType || 'info'">{{ row.statusText }}</el-tag>
              </div>

              <div class="ogb-col col-action">
                <div class="action-links">
                  <a v-if="row.refundStatus > 0" class="warning-link" @click="handleGoRefund(row)">退款跟踪</a>
                  <a v-if="row.canDelivery" class="success-link" @click="handleDelivery(row)">发货</a>
                  <a v-if="canRefundApply(row)" class="danger-link" @click="handleRefundApply(row)">申请退款</a>
                  <span v-if="!hasRowActions(row)" class="empty-action">-</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

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
      @delivery="handleDelivery"
      @refund-apply="handleRefundApply"
      @go-refund="handleGoRefund"
      @close="handleDetailClose"
    />

    <DeliveryDialog
      v-model="deliveryVisible"
      :order="deliveryOrder"
      @success="handleDeliverySuccess"
    />

    <RefundApplyDialog
      v-model="refundVisible"
      :order="refundOrder"
      @success="handleRefundSuccess"
    />
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Refresh, Search } from '@element-plus/icons-vue'
import { getOrderList, getOrderStatistics } from '@/api/order'
import { extractListPayload, normalizeOrderList } from '../shared'
import OrderDetailDrawer from './components/OrderDetailDrawer'
import DeliveryDialog from './components/DeliveryDialog'
import RefundApplyDialog from './components/RefundApplyDialog'

defineOptions({
  name: 'MerchantOrderList'
})

const createSearchForm = () => ({
  order_sn: '',
  user_keyword: '',
  goods_name: '',
  order_type: '',
  status: '',
  pay_type: '',
  start_time: '',
  end_time: ''
})

const route = useRoute()
const router = useRouter()

const loading = ref(false)
const orderList = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(15)
const dateRange = ref([])
const stats = ref({
  total: 0,
  paidCount: 0,
  refundingCount: 0,
  paidAmount: 0
})
const searchForm = ref(createSearchForm())
const detailVisible = ref(false)
const currentOrderId = ref('')
const currentOrder = ref(null)
const deliveryVisible = ref(false)
const deliveryOrder = ref(null)
const refundVisible = ref(false)
const refundOrder = ref(null)

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
    getOrderList(params),
    getOrderStatistics(params)
  ]).then(([listRes, statsRes]) => {
    const payload = extractListPayload(listRes)
    orderList.value = normalizeOrderList(payload.list)
    total.value = payload.total || orderList.value.length
    const statsData = statsRes && statsRes.data ? statsRes.data : statsRes || {}
    stats.value = {
      total: Number(statsData.total || 0),
      paidCount: Number(statsData.paidCount || 0),
      refundingCount: Number(statsData.refundingCount || 0),
      paidAmount: Number(statsData.paidAmount || 0)
    }
  }).catch(() => {
    orderList.value = []
    total.value = 0
    stats.value = {
      total: 0,
      paidCount: 0,
      refundingCount: 0,
      paidAmount: 0
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
    router.replace({ name: 'MerchantOrderList' })
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
  currentOrderId.value = row.orderId || row.order_id || row.id
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
  detailVisible.value = false
  fetchData()
}

function handleRefundApply(row) {
  refundOrder.value = row
  refundVisible.value = true
}

function handleRefundSuccess() {
  refundVisible.value = false
  detailVisible.value = false
  fetchData()
}

function handleGoRefund(row) {
  const orderSn = row.orderSn || row.order_sn || ''
  router.push({
    name: 'MerchantOrderRefundList',
    query: orderSn ? { order_sn: orderSn } : {}
  })
}

function canRefundApply(row) {
  return [1, 6].includes(Number(row.status || 0)) && Number(row.refundStatus || 0) === 0
}

function hasRowActions(row) {
  return Number(row.refundStatus || 0) > 0 || row.canDelivery || canRefundApply(row)
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

function getGoodsPrice(row) {
  return getFirstGoods(row).price || row.payAmount || 0
}

function getGoodsQuantity(row) {
  return getFirstGoods(row).quantity || 1
}

function getDeliveryText(row) {
  if (!row.hasPhysicalGoods) {
    return '无需配送'
  }
  if (row.expressName && row.expressName !== '-') {
    return row.expressName
  }
  return row.canDelivery ? '待发货' : '商家配送'
}

function formatMoney(value) {
  return Number(value || 0).toFixed(2)
}
</script>

<style lang="scss" scoped>
.merchant-order-page {
  --order-page-bg: #f7f8fa;
  --order-surface-card: #ffffff;
  --order-surface-muted: #f5f7fa;
  --order-surface-soft: #fafafa;
  --order-border: #ebeef5;
  --order-border-strong: #dcdfe6;
  --order-shadow: rgba(25, 28, 30, 0.04);

  min-height: 100%;
  box-sizing: border-box;
  background: var(--order-page-bg);
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
}

.page-curator-subtitle {
  margin: 0;
  font-size: 0.875rem;
  color: var(--curator-on-surface-variant);
  line-height: 1.5;
  max-width: 680px;
}

.panel {
  background: var(--order-surface-card);
  border: 1px solid var(--order-border);
  border-radius: 12px;
  box-shadow: 0 1px 2px var(--order-shadow);
  margin-bottom: 12px;

  &--search {
    padding: 20px 24px 4px;
  }

  &--table {
    padding: 16px 24px 16px;
    overflow-x: auto;
  }
}

:deep(.btn-curator-primary.el-button--primary) {
  border: none;
  border-radius: 6px;
  color: var(--curator-on-primary);
  font-weight: 500;
  padding-left: 18px;
  padding-right: 18px;
  box-shadow: 0 2px 8px var(--brand-shadow);
}

:deep(.btn-curator-reset) {
  border-radius: 6px;
  background: var(--order-surface-card);
  color: var(--ds-primary);
  border: 1px solid var(--ds-primary);

  &:hover,
  &:focus {
    color: var(--ds-primary-hover);
    border-color: var(--ds-primary-hover);
    background: var(--prev-color-hover);
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

.mb16 {
  margin-bottom: 16px;
}

.stat-badge {
  position: relative;
  background: var(--order-surface-card);
  border: 1px solid var(--order-border);
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
    background: var(--ds-primary);
  }

  &--purple::before {
    background: var(--ds-primary-container);
  }

  &--gold::before {
    background: var(--text-warning);
  }

  &--gray::before {
    background: var(--text-placeholder);
  }
}

.stat-badge__label {
  display: block;
  font-size: 13px;
  color: var(--text-secondary);
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
  color: var(--text-title);
  line-height: 1;
}

.stat-badge__sub {
  font-size: 13px;
  font-weight: 500;
  line-height: 1;

  &--up,
  &--tag {
    color: var(--ds-primary);
  }

  &--warn {
    color: var(--text-warning);
  }

  &--down {
    color: var(--text-danger);
  }
}

$col-goods: minmax(260px, 1fr);
$col-price: 110px;
$col-pay: 130px;
$col-user: 150px;
$col-delivery: 120px;
$col-refund: 100px;
$col-status: 100px;
$col-action: 110px;
$grid: $col-goods $col-price $col-pay $col-user $col-delivery $col-refund $col-status $col-action;

.order-list-container {
  min-width: 1080px;
}

.order-thead {
  display: flex;
  align-items: center;
  padding: 12px 0;
  margin-bottom: 12px;
  border-radius: 4px;
  background: var(--order-surface-muted);
  color: var(--text-secondary);
  font-size: 13px;
  font-weight: 500;

  .thead-grid {
    flex: 1;
    display: grid;
    grid-template-columns: $grid;

    div {
      padding: 0 12px;
    }
  }
}

.order-group {
  border: 1px solid var(--order-border);
  border-radius: 4px;
  margin-bottom: 16px;
  background: var(--order-surface-card);

  &__head {
    display: flex;
    align-items: center;
    min-height: 44px;
    padding: 10px 16px;
    border-bottom: 1px solid var(--order-border);
    background: var(--order-surface-muted);
    font-size: 13px;
    color: var(--text-secondary);

    .og-sn {
      margin-right: 24px;
      color: var(--text-primary);
      font-weight: 500;
    }

    .og-type {
      margin-right: 8px;
    }

    .og-type-tag {
      margin-right: 24px;
    }

    .og-time {
      margin-right: auto;
    }

    .og-actions {
      a {
        margin-left: 16px;
      }
    }
  }

  &__body {
    .ogb-grid {
      display: grid;
      grid-template-columns: $grid;
    }

    .el-tag {
      width: fit-content;
    }
  }
}

.ogb-col {
  padding: 16px 12px;
  font-size: 13px;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.col-goods {
  flex-direction: row;
  align-items: flex-start;
  gap: 12px;
}

.goods-thumb-wrap {
  flex-shrink: 0;
}

.goods-thumb {
  width: 60px;
  height: 60px;
  border-radius: 4px;
  background: var(--order-surface-soft);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-secondary);
  font-size: 24px;
}

.goods-thumb-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.goods-info {
  flex: 1;
  min-width: 0;

  .goods-name {
    color: var(--text-primary);
    line-height: 1.4;
    margin-bottom: 4px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }

  .goods-spec {
    color: var(--text-secondary);
    font-size: 12px;
    line-height: 1.4;
  }
}

.col-text {
  color: var(--text-primary);
  line-height: 1.5;
}

.col-sub {
  color: var(--text-secondary);
  font-size: 12px;
  line-height: 1.5;
  margin-top: 4px;
}

.user-name {
  color: var(--text-primary);
  margin-bottom: 4px;
}

.action-links {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 6px;
}

.pagination-wrap {
  padding: 16px 0 0;
  display: flex;
  justify-content: flex-end;
}

.success-link {
  color: var(--text-success);
}

.warning-link {
  color: var(--text-warning);
}

.danger-link {
  color: var(--text-danger);
}

.empty-action {
  color: var(--text-secondary);
}

a {
  color: var(--text-link);
  cursor: pointer;
  font-size: 13px;
  text-decoration: none;

  &:hover {
    color: var(--text-link-hover);
  }
}
</style>
