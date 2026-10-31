<template>
  <div class="order-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">订单列表</h1>
        <p class="page-curator-subtitle">
          统一查看平台订单，支持订单号、用户、商品、支付方式、订单状态、下单时间筛选，并从详情进入退款审核。
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
            <el-option label="待发货" value="unsent" />
            <el-option label="待收货" value="undelivered" />
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
            <el-option label="余额支付" value="yue" />
            <el-option label="支付宝" value="alipay" />
            <el-option label="线下支付" value="offline" />
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
          <el-button type="primary" class="btn-curator-primary" icon="el-icon-search" @click="handleSearch">查询</el-button>
          <el-button class="btn-curator-reset" icon="el-icon-refresh" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <!-- 统计模块 -->

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
          <span class="stat-badge__label">已支付金额</span>
          <div class="stat-badge__body">
            <span class="stat-badge__value">¥{{ formatMoney(stats.paidAmount) }}</span>
            <span class="stat-badge__sub stat-badge__sub--down">累计</span>
          </div>
        </div>
      </el-col>
    </el-row>

    <!-- 列表模块 -->
    <div v-loading="loading" class="panel panel--table">
      <div class="order-list-container">
        <!-- 表头 -->
        <div class="order-thead">
          <div class="col-check">
            <div style="display: flex; align-items: center;">
              <el-checkbox
                v-model="checkAll"
                :indeterminate="checkIndeterminate"
                style="margin-right: 4px;"
                @change="handleCheckAll"
              />
              <el-dropdown trigger="click">
                <span class="el-dropdown-link" style="cursor: pointer; user-select: none;">
                  全选<LegacyIcon name="el-icon-arrow-down" class=" el-icon--right" style="margin-left:2px;" />
                </span>
                <template #dropdown>
                  <el-dropdown-menu>
                    <el-dropdown-item>批量操作</el-dropdown-item>
                  </el-dropdown-menu>
                </template>
              </el-dropdown>
            </div>
          </div>
          <div class="thead-grid">
            <div class="col-goods">商品信息</div>
            <div class="col-price">单价/数量</div>
            <div class="col-pay">实付金额</div>
            <div class="col-user">用户信息</div>
            <div class="col-delivery">配送信息</div>
            <div class="col-status">订单状态</div>
            <div class="col-action">操作</div>
          </div>
        </div>

        <!-- 空状态 -->
        <el-empty v-if="!loading && !orderList.length" description="暂无订单数据" :image-size="80" style="padding: 40px 0;" />

        <!-- 订单分组列表 -->
        <div v-for="row in orderList" :key="row.rowKey" class="order-group">
          <!-- 订单号行 -->
          <div class="order-group__head">
            <el-checkbox v-model="row.checked" class="og-check" @change="handleRowCheckChange" />
            <span class="og-sn">订单号：{{ row.orderSn }}</span>
            <span class="og-type">订单类型：</span>
            <el-tag size="mini" :type="row.orderTypeTagType || 'info'" class="og-type-tag">
              {{ row.orderTypeText }}
            </el-tag>
            <span class="og-time">下单时间：{{ row.createTime }}</span>
            <div class="og-actions">
              <a @click="handleDetail(row)">详情</a>
              <a @click="handleRemark(row)">备注</a>
            </div>
          </div>

          <!-- 内容行 -->
          <div class="order-group__body">
            <div class="ogb-spacer" />
            <div class="ogb-grid">
              <!-- 商品信息 -->
              <div class="ogb-col col-goods goods-stack">
                <div
                  v-for="item in row.displayGoods"
                  :key="item.displayRowKey"
                  class="goods-row"
                >
                  <div class="goods-thumb-wrap">
                    <el-image :src="item.image" class="goods-thumb">
                      <template #error>
                        <div class="goods-thumb-placeholder">
                          <LegacyIcon name="el-icon-picture-outline" />
                        </div>
                      </template>
                    </el-image>
                  </div>
                  <div class="goods-info">
                    <div class="goods-name">{{ item.goodsName }}</div>
                    <div class="goods-spec">{{ item.skuName || '-' }}</div>
                  </div>
                </div>
              </div>
              <!-- 单价/数量 -->
              <div class="ogb-col col-price price-stack">
                <div
                  v-for="item in row.displayGoods"
                  :key="item.displayPriceRowKey"
                  class="price-row"
                >
                  <div class="col-text">¥{{ formatMoney(item.price) }}</div>
                  <div class="col-sub">x{{ item.quantity || 1 }}</div>
                </div>
              </div>
              <!-- 实付金额 -->
              <div class="ogb-col col-pay">
                <div class="col-text">¥{{ formatMoney(row.payAmount) }} <span class="freight">(包含运费：¥0.00)</span></div>
                <div class="col-sub">{{ row.payTypeText }}</div>
              </div>
              <!-- 用户信息 -->
              <div class="ogb-col col-user">
                <div class="user-name">
                  <a class="primary-link" @click="handleUserDetail(row)">{{ row.nickname }}</a>
                </div>
                <div class="col-sub">{{ row.userPhone || '-' }}</div>
              </div>
              <!-- 配送信息 -->
              <div class="ogb-col col-delivery">
                <div class="col-text">{{ row.hasPhysicalGoods ? (row.deliveryMethod || '商家配送') : '无需配送' }}</div>
              </div>
              <!-- 订单状态 -->
              <div class="ogb-col col-status">
                <el-tag effect="plain" :type="row.statusTagType || 'info'">{{ row.statusText }}</el-tag>
              </div>
              <!-- 操作 -->
              <div class="ogb-col col-action">
                <div class="action-links">
                  <a v-if="row.canDelivery" class="primary-link" @click="handleDelivery(row)">发货</a>
                  <a v-else-if="row.canEditDelivery" class="primary-link" @click="handleDelivery(row)">修改物流</a>
                  <span v-else class="empty-action">-</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

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

    <OrderDetailDrawer
      ref="detailDrawerRef"
      v-model:visible="detailVisible"
      :order-id="currentOrderId"
      :order-sn="currentOrderSn"
      :order-data="currentOrder"
      @go-refund="handleGoRefund"
      @apply-refund="handleApplyRefund"
      @delivery="handleDelivery"
      @refresh="getList"
      @close="handleDetailClose"
    />

    <DeliveryDialog
      v-model:visible="deliveryVisible"
      :order="deliveryTarget"
      @success="handleDeliverySuccess"
    />

    <UserDetailDrawer
      v-model:visible="userDetailVisible"
      :user-data="currentUser"
      init-tab="info"
      @close="handleUserDetailClose"
    />

    <el-dialog
      v-model="remarkVisible"
      title="订单备注"
      width="460px"
      :close-on-click-modal="false"
      @close="handleRemarkClose"
    >
      <el-form ref="remarkFormRef" :model="remarkForm" label-width="72px">
        <el-form-item label="订单号">
          <span class="dialog-order-sn">{{ remarkTarget ? remarkTarget.orderSn : '-' }}</span>
        </el-form-item>
        <el-form-item label="备注">
          <el-input
            v-model="remarkForm.remark"
            type="textarea"
            :rows="4"
            maxlength="200"
            show-word-limit
            placeholder="请输入订单备注"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <span>
          <el-button @click="remarkVisible = false">取 消</el-button>
          <el-button type="primary" :loading="remarkLoading" @click="handleRemarkSubmit">保 存</el-button>
        </span>
      </template>
    </el-dialog>

    <el-dialog
      v-model="refundVisible"
      title="发起退款"
      width="480px"
      :close-on-click-modal="false"
      @close="handleRefundClose"
    >
      <el-form
        ref="refundFormRef"
        :model="refundForm"
        :rules="refundRules"
        label-width="88px"
      >
        <el-form-item label="订单号">
          <span class="dialog-order-sn">{{ refundTarget ? refundTarget.orderSn : '-' }}</span>
        </el-form-item>
        <el-form-item label="退款金额" prop="refund_amount">
          <el-input-number
            v-model="refundForm.refund_amount"
            :precision="2"
            :min="0.01"
            :max="refundMaxAmount"
            :step="0.01"
            style="width: 100%;"
          />
          <div class="dialog-help-text">订单实付：¥{{ formatMoney(refundMaxAmount) }}</div>
        </el-form-item>
        <el-form-item label="退款原因" prop="refund_reason">
          <el-input
            v-model="refundForm.refund_reason"
            type="textarea"
            :rows="3"
            maxlength="200"
            show-word-limit
            placeholder="请输入退款原因"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <span>
          <el-button @click="refundVisible = false">取 消</el-button>
          <el-button type="primary" :loading="refundLoading" @click="handleRefundSubmit">提交退款</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeMount, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getOrderList, getOrderStats, deleteOrder, applyOrderRefund, updateOrderRemark } from '@/api/order'
import OrderDetailDrawer from './components/OrderDetailDrawer'
import DeliveryDialog from './components/DeliveryDialog'
import UserDetailDrawer from '@/views/user/user/components/UserDetailDrawer'
import {
  buildOrderActionPayload,
  computeOrderStats,
  extractListPayload,
  extractResponseData,
  normalizeOrderList
} from '../shared'

defineOptions({
  name: 'OrderList'
})

const route = useRoute()
const router = useRouter()

const searchFormRef = ref(null)
const remarkFormRef = ref(null)
const refundFormRef = ref(null)
const loading = ref(false)
const orderList = ref([])
const checkAll = ref(false)
const checkIndeterminate = ref(false)
const total = ref(0)
const page = ref(1)
const limit = ref(15)
const dateRange = ref([])
const stats = reactive({
  total: 0,
  paidCount: 0,
  refundingCount: 0,
  paidAmount: 0
})
const detailVisible = ref(false)
const detailDrawerRef = ref(null)
const currentOrderId = ref('')
const currentOrderSn = ref('')
const currentOrder = ref(null)
const routeDetailId = ref('')
const userDetailVisible = ref(false)
const currentUser = ref(null)
const remarkVisible = ref(false)
const remarkLoading = ref(false)
const remarkTarget = ref(null)
const remarkForm = reactive({
  remark: ''
})
const refundVisible = ref(false)
const refundLoading = ref(false)
const refundTarget = ref(null)
const deliveryVisible = ref(false)
const deliveryTarget = ref(null)
const refundForm = reactive({
  refund_amount: 0,
  refund_reason: ''
})
const refundRules = {
  refund_amount: [{ required: true, type: 'number', min: 0.01, message: '退款金额必须大于0', trigger: 'blur' }],
  refund_reason: [{ required: true, message: '请输入退款原因', trigger: 'blur' }]
}

const defaultSearchForm = {
  order_sn: '',
  user_keyword: '',
  goods_name: '',
  order_type: '',
  status: '',
  pay_type: '',
  start_time: '',
  end_time: ''
}

const refundMaxAmount = computed(() => {
  return refundTarget.value ? Number(refundTarget.value.payAmount || 0) : 0
})

watch(() => route.query.detail_id, (value) => {
  applyRouteDetailQuery(value)
})

onBeforeMount(() => {
  applyRouteDetailQuery(route.query.detail_id)
  const routeOrderSn = route.query.order_sn
  if (routeOrderSn) {
    searchForm.order_sn = String(routeOrderSn)
  }
  void getList()
})

const searchForm = reactive({ ...defaultSearchForm })

function handleCheckAll(val) {
  orderList.value.forEach(row => {
    row.checked = val
  })
  checkIndeterminate.value = false
}

function handleRowCheckChange() {
  const checkedCount = orderList.value.filter(row => row.checked).length
  checkAll.value = checkedCount > 0 && checkedCount === orderList.value.length
  checkIndeterminate.value = checkedCount > 0 && checkedCount < orderList.value.length
}

function getDisplayGoods(row) {
  if (Array.isArray(row.goods) && row.goods.length) {
    return row.goods.map((item, index) => {
      const rowKey = item.rowKey || item.goodsId || index

      return {
        ...item,
        displayRowKey: `${row.rowKey}-goods-${rowKey}-${index}`,
        displayPriceRowKey: `${row.rowKey}-price-${rowKey}-${index}`
      }
    })
  }

  return [{
    rowKey: `${row.rowKey}-summary`,
    displayRowKey: `${row.rowKey}-goods-summary`,
    displayPriceRowKey: `${row.rowKey}-price-summary`,
    goodsName: row.goodsSummary || '-',
    image: row.goodsImage || '',
    skuName: row.goodsSpec || '',
    price: Number(row.goodsPrice || 0),
    quantity: Number(row.goodsQuantity || 1)
  }]
}

async function getList() {
  loading.value = true
  const params = {
    page: page.value,
    limit: limit.value,
    ...searchForm
  }

  try {
    const [listRes, statsRes] = await Promise.all([
      getOrderList(params),
      getOrderStats(params)
    ])
    const payload = extractListPayload(listRes)
    const list = normalizeOrderList(payload.list).map(row => ({
      ...row,
      checked: false,
      displayGoods: getDisplayGoods(row)
    }))
    const remoteStats = extractResponseData(statsRes)
    const fallbackStats = computeOrderStats(list)

    orderList.value = list
    checkAll.value = false
    checkIndeterminate.value = false
    total.value = payload.total || list.length
    Object.assign(stats, {
      total: Number(remoteStats.total || fallbackStats.total || 0),
      paidCount: Number(remoteStats.paidCount || remoteStats.paid || fallbackStats.paidCount || 0),
      refundingCount: Number(remoteStats.refundingCount || remoteStats.refunding || fallbackStats.refundingCount || 0),
      paidAmount: Number(remoteStats.paidAmount || remoteStats.paid_amount || fallbackStats.paidAmount || 0)
    })
  } catch {
    orderList.value = []
    total.value = 0
    Object.assign(stats, computeOrderStats([]))
  } finally {
    loading.value = false
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
  Object.assign(searchForm, defaultSearchForm)
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
  currentOrderId.value = row.id || row.orderId
  currentOrderSn.value = row.orderSn
  currentOrder.value = row
  detailVisible.value = true
}

function applyRouteDetailQuery(detailId) {
  const value = String(detailId || '').trim()
  if (!value) {
    routeDetailId.value = ''
    return
  }
  if (value === routeDetailId.value) {
    return
  }

  routeDetailId.value = value
  currentOrderId.value = value
  currentOrderSn.value = ''
  currentOrder.value = null
  detailVisible.value = false
  nextTick(() => {
    detailVisible.value = true
  })
}

function handleDetailClose() {
  currentOrder.value = null
}

function handleUserDetail(row) {
  currentUser.value = {
    id: row.userId,
    uid: row.userId,
    nickname: row.nickname,
    phone: row.userPhone,
    status: 1,
    asset: {},
    recentOrders: []
  }
  userDetailVisible.value = true
}

function handleUserDetailClose() {
  currentUser.value = null
}

function handleRemark(row) {
  remarkTarget.value = row
  Object.assign(remarkForm, {
    remark: row.remark || ''
  })
  remarkVisible.value = true
}

function handleRemarkClose() {
  remarkTarget.value = null
  Object.assign(remarkForm, {
    remark: ''
  })
}

async function handleRemarkSubmit() {
  if (!remarkTarget.value) return

  remarkLoading.value = true
  try {
    const res = await updateOrderRemark({
      id: remarkTarget.value.id || remarkTarget.value.orderId,
      remark: remarkForm.remark
    })
    ElMessage.success(res.message || '备注已保存')
    remarkVisible.value = false
    await getList()
  } finally {
    remarkLoading.value = false
  }
}

function handleApplyRefund(row) {
  refundTarget.value = row
  Object.assign(refundForm, {
    refund_amount: Number(row.payAmount || 0),
    refund_reason: ''
  })
  refundVisible.value = true
  nextTick(() => {
    refundFormRef.value && refundFormRef.value.clearValidate()
  })
}

function handleRefundClose() {
  refundTarget.value = null
  Object.assign(refundForm, {
    refund_amount: 0,
    refund_reason: ''
  })
}

async function handleRefundSubmit() {
  if (!refundFormRef.value) return
  try {
    await refundFormRef.value.validate()
  } catch {
    return
  }
  if (!refundTarget.value) return

  refundLoading.value = true
  try {
    const res = await applyOrderRefund({
      id: refundTarget.value.id || refundTarget.value.orderId,
      refund_amount: refundForm.refund_amount,
      refund_reason: refundForm.refund_reason
    })
    ElMessage.success(res.message || '退款申请已提交')
    refundVisible.value = false
    await getList()
  } finally {
    refundLoading.value = false
  }
}

function handleDelivery(row) {
  deliveryTarget.value = row
  deliveryVisible.value = true
}

async function handleDeliverySuccess() {
  deliveryVisible.value = false
  deliveryTarget.value = null
  await getList()
  if (detailVisible.value) {
    await detailDrawerRef.value?.refresh?.()
  }
}

function handleGoRefund(row) {
  const orderSn = row.orderSn || row.order_sn || ''
  router.push({
    name: 'OrderRefundList',
    query: orderSn ? { order_sn: orderSn } : {}
  })
}

function handleDelete(row) {
  ElMessageBox.confirm(`确定删除订单 ${row.orderSn} 吗？`, '删除提示', { type: 'warning' }).then(() => {
    deleteOrder(buildOrderActionPayload(row)).then(res => {
      ElMessage.success(res.message || '删除成功')
      void getList()
    })
  }).catch(() => {})
}

function getRowKey(row) {
  return row.rowKey
}

function formatMoney(value) {
  return Number(value || 0).toFixed(2)
}
</script>

<style lang="scss" scoped>
.order-curator-page {
  min-height: 100%;
  box-sizing: border-box;
}
.mb16{
  margin-bottom: 12px;
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

.order-work-surface {
  background: var(--curator-surface-card);
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
}

.panel--table{
  padding: 16px 24px 16px !important;
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

  &--stats {
    padding: 20px 24px;
  }

  &--table {
    padding: 0 24px 16px;
  }
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
  background: var(--dashboard-surface);
  border: 1px solid var(--dashboard-border);
  border-radius: 8px;
  padding: 18px 20px 16px 24px;
  min-height: 80px;
  overflow: hidden;

  /* 左侧彩色竖线 */
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
  &--purple::before { background: linear-gradient(180deg, #a78bfa 0%, #7c3aed 100%); }
  &--gold::before   { background: linear-gradient(180deg, #fbbf24 0%, #d97706 100%); }
  &--gray::before   { background: linear-gradient(180deg, #d1d5db 0%, #9ca3af 100%); }
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

/* ---- 列布局变量 ---- */
$col-goods: minmax(260px, 1fr);
$col-price: 110px;
$col-pay:   220px;
$col-user:  160px;
$col-delivery: 120px;
$col-status: 100px;
$col-action: 100px;
$grid: $col-goods $col-price $col-pay $col-user $col-delivery $col-status $col-action;

.order-list-container {
  min-width: 1000px;
}

/* ---- 表头 ---- */
.order-thead {
  display: flex;
  background: var(--dashboard-surface-muted);
  padding: 12px 0;
  color: var(--dashboard-text-secondary);
  font-size: 13px;
  font-weight: 500;
  margin-bottom: 12px;
  align-items: center;
  border-radius: 4px;

  .col-check {
    width: 100px;
    padding-left: 16px;
    flex-shrink: 0;
  }

  .thead-grid {
    flex: 1;
    display: grid;
    grid-template-columns: $grid;

    div {
      padding: 0 12px;
    }
  }
}

/* ---- 订单分组 ---- */
.order-group {
  border: 1px solid var(--dashboard-border);
  border-radius: 4px;
  margin-bottom: 16px;
  background: var(--dashboard-surface);

  &__head {
    display: flex;
    align-items: center;
    background: var(--dashboard-surface-muted);
    padding: 10px 16px;
    border-bottom: 1px solid var(--dashboard-border);
    font-size: 13px;
    color: var(--dashboard-text-secondary);

    .og-check {
      margin-right: 12px;
    }

    .og-sn {
      margin-right: 24px;
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
        color: var(--curator-primary);
        cursor: pointer;
        &:hover { opacity: 0.8; }
      }
    }
  }

  &__body {
    display: flex;

    .ogb-spacer {
      flex-shrink: 0;
    }

    .ogb-grid {
      flex: 1;
      display: grid;
      grid-template-columns: $grid;
      align-items: stretch;
    }

    .el-tag{
      width: fit-content;
    }
  }
}

/* ---- 列内容 ---- */
.ogb-col {
  box-sizing: border-box;
  padding: 16px 12px;
  font-size: 13px;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

/* 商品信息列 */
.col-goods {
  align-items: stretch;
}

.goods-stack,
.price-stack {
  padding: 0;
  justify-content: initial;
}

.goods-stack {
  display: flex;
  flex-direction: column;
}

.goods-row,
.price-row {
  flex: 1;
  min-height: 84px;
  box-sizing: border-box;
  border-bottom: 1px solid var(--dashboard-border);
}

.goods-row {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px;
}

.price-stack {
  display: flex;
  flex-direction: column;
}

.price-row {
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 12px;
}

.goods-row:last-child,
.price-row:last-child {
  border-bottom: 0;
}

.goods-thumb-wrap {
  flex-shrink: 0;
}

.goods-thumb {
  width: 60px;
  height: 60px;
  border-radius: 4px;
  background: var(--dashboard-surface-muted);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--dashboard-text-muted);
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
    color: var(--dashboard-text-primary);
    line-height: 1.4;
    margin-bottom: 4px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }

  .goods-spec {
    color: var(--dashboard-text-muted);
    font-size: 12px;
    line-height: 1.4;
  }
}

/* 其他列 */
.col-text {
  color: var(--dashboard-text-primary);
  line-height: 1.5;
}
.col-sub {
  color: var(--dashboard-text-muted);
  font-size: 12px;
  line-height: 1.5;
  margin-top: 4px;
}
.freight {
  color: var(--dashboard-text-muted);
  font-size: 12px;
  margin-left: 4px;
}

.user-name {
  margin-bottom: 4px;
}

.primary-link {
  color: var(--curator-primary);
  cursor: pointer;
  text-decoration: none;
  &:hover {
    opacity: 0.8;
  }
}

.empty-action {
  color: var(--dashboard-text-muted);
}

.action-links {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 6px;
}

.dialog-order-sn {
  font-weight: 600;
  color: var(--dashboard-text-primary);
  word-break: break-all;
}

.dialog-help-text {
  margin-top: 4px;
  font-size: 12px;
  color: var(--dashboard-text-muted);
}

/* ---- 分页 ---- */
.pagination-wrap {
  padding: 16px 0 0;
  display: flex;
  justify-content: flex-end;
}

/* ---- 链接 ---- */
.warning-link { color: #e6a23c; }
.danger-link  { color: var(--dashboard-danger); }
.success-link { color: var(--dashboard-success); }

a {
  color: var(--curator-primary);
  cursor: pointer;
  font-size: 13px;

  &:hover {
    opacity: 0.8;
  }
}
</style>
