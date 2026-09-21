<template>
  <el-drawer
    v-model="localVisible"
    title="订单详情"
    direction="rtl"
    size="920px"
    custom-class="order-detail-drawer-shell"
    :wrapper-closable="true"
    @open="handleOpen"
    @close="handleClose"
  >
    <div class="order-detail-drawer">
      <div v-if="localVisible" v-loading="loading" class="order-detail-drawer__body">
        <section class="detail-card detail-card--overview">
          <div class="overview-meta">
            <span>订单编号：{{ detail.orderSn || '-' }}</span>
            <span>下单时间：{{ detail.createTime || '-' }}</span>
          </div>
          <div class="overview-divider" />
          <div class="overview-main">
            <div>
              <h3>{{ detail.statusText || '-' }}</h3>
              <p>{{ orderStatusDescription }}</p>
              <div class="overview-tags">
                <el-tag :type="detail.orderTypeTagType">{{ detail.orderTypeText }}</el-tag>
                <el-tag :type="detail.statusTagType">{{ detail.statusText }}</el-tag>
                <el-tag :type="detail.refundTagType">{{ detail.refundStatusText }}</el-tag>
                <el-tag :type="detail.payTypeTagType">{{ detail.payTypeText }}</el-tag>
              </div>
            </div>
            <div class="overview-side">
              <div class="overview-amount">
                <span>实付金额</span>
                <strong>¥{{ formatMoney(detail.payAmount) }}</strong>
              </div>
            </div>
          </div>
          <div v-if="hasOverviewActions" class="overview-actions">
            <el-button
              v-if="detail.canApplyRefund"
              class="overview-action"
              type="warning"
              plain
              @click="handleApplyRefund"
            >
              发起退款
            </el-button>
            <el-button
              v-if="showRefundEntry && detail.canAuditRefund"
              class="overview-action"
              type="warning"
              plain
              @click="handleGoRefund"
            >
              退款审核
            </el-button>
            <el-button
              v-if="detail.canDelivery"
              class="overview-action"
              type="primary"
              plain
              @click="handleDelivery"
            >
              发货
            </el-button>
            <el-button
              v-if="detail.canEditDelivery"
              class="overview-action"
              type="primary"
              plain
              @click="handleDelivery"
            >
              修改物流
            </el-button>
            <el-button
              v-if="canViewExpress"
              class="overview-action"
              type="primary"
              plain
              :loading="expressLoading"
              @click="handleViewExpress"
            >
              查看物流
            </el-button>
            <el-button
              v-if="detail.canConfirmReceipt"
              class="overview-action"
              plain
              disabled
              title="买家端确认收货"
            >
              确认收货
            </el-button>
          </div>
        </section>

        <el-alert
          v-if="detail.canAuditRefund"
          type="warning"
          show-icon
          :closable="false"
          class="order-detail-drawer__notice"
          title="该订单存在待审核的退款申请，可在退款信息区域中审核通过或拒绝。"
        />

        <section class="detail-card">
          <h3 class="detail-card__title">订单明细</h3>
          <div class="detail-columns">
            <div class="detail-column">
              <h4>收货人信息</h4>
              <div v-for="item in receiverInfoRows" :key="item.label" class="detail-row">
                <span>{{ item.label }}</span>
                <strong>{{ item.value }}</strong>
              </div>
            </div>
            <div class="detail-column">
              <h4>配送信息</h4>
              <div v-for="item in deliveryInfoRows" :key="item.label" class="detail-row">
                <span>{{ item.label }}</span>
                <strong>{{ item.value }}</strong>
              </div>
            </div>
            <div class="detail-column">
              <h4>支付信息</h4>
              <div v-for="item in paymentInfoRows" :key="item.label" class="detail-row">
                <span>{{ item.label }}</span>
                <strong>{{ item.value }}</strong>
              </div>
            </div>
          </div>
        </section>

        <section class="detail-card">
          <h3 class="detail-card__title">商品信息</h3>
          <el-table
            v-if="detail.goods.length"
            :data="detail.goods"
            row-key="rowKey"
            class="goods-table"
          >
            <el-table-column label="商品名称" min-width="260">
              <template #default="{ row }">
                <div class="goods-cell">
                  <el-image :src="row.image" class="goods-cell__image">
                    <template #error>
                      <div class="goods-cell__placeholder">
                        <LegacyIcon name="el-icon-picture-outline" />
                      </div>
                    </template>
                  </el-image>
                  <span>{{ row.goodsName }}</span>
                </div>
              </template>
            </el-table-column>
            <el-table-column label="属性" min-width="150">
              <template #default="{ row }">{{ row.skuName || '-' }}</template>
            </el-table-column>
            <el-table-column label="商品编号" width="120">
              <template #default="{ row }">{{ row.goodsSn || row.goods_sn || row.goodsId || '-' }}</template>
            </el-table-column>
            <el-table-column label="价格" width="110">
              <template #default="{ row }">¥{{ formatMoney(row.price) }}</template>
            </el-table-column>
            <el-table-column label="数量" width="50">
              <template #default="{ row }">{{ row.quantity }}</template>
            </el-table-column>
            <el-table-column label="小计" width="120" align="right">
              <template #default="{ row }">¥{{ formatMoney(row.price * row.quantity) }}</template>
            </el-table-column>
          </el-table>
          <el-empty v-else description="暂无商品信息" :image-size="70" />
          <div v-if="detail.goods.length" class="goods-summary">
            <span>商品总数量：{{ goodsTotalQuantity }}</span>
            <strong>合计：¥{{ formatMoney(goodsSubtotalAmount) }}</strong>
          </div>
        </section>

        <section class="detail-card">
          <h3 class="detail-card__title">订单金额</h3>
          <div class="amount-formula">
            <div>
              商品总金额：
              <strong>¥{{ formatMoney(goodsSubtotalAmount) }}</strong>
              + 订单运费：
              <strong>¥{{ formatMoney(postageAmount) }}</strong>
              + 附加费用：
              <strong>¥{{ formatMoney(extraAmount) }}</strong>
              = 订单总金额：
              <strong>¥{{ formatMoney(orderTotalAmount) }}</strong>
            </div>
            <div>
              实收金额：
              <strong>¥{{ formatMoney(actualReceiptAmount) }}</strong>
            </div>
          </div>
        </section>

        <section v-if="showRefundPanel" class="detail-card">
          <div class="detail-card__head">
            <h3 class="detail-card__title">退款信息</h3>
            <div v-if="detail.canAuditRefund" class="detail-card__actions">
              <el-button
                type="success"
                :loading="approveLoading"
                @click="handleApproveRefund"
              >审核通过</el-button>
              <el-button
                type="danger"
                plain
                :loading="rejectLoading"
                @click="handleRejectRefund"
              >拒绝退款</el-button>
            </div>
          </div>
          <div class="detail-columns detail-columns--refund">
            <div v-for="item in refundInfoRows" :key="item.label" class="detail-row">
              <span>{{ item.label }}</span>
              <strong>{{ item.value }}</strong>
            </div>
          </div>
          <div v-if="refundReasonOptions.length" class="reason-tags">
            <el-tag v-for="item in refundReasonOptions" :key="item.rowKey" size="mini" effect="plain">
              {{ item.label }}
            </el-tag>
          </div>
        </section>

        <section class="detail-card">
          <h3 class="detail-card__title">操作日志</h3>
          <el-table
            v-loading="recordLoading"
            :data="recordList"
            row-key="rowKey"
            empty-text="暂无操作记录"
            class="record-table"
          >
            <el-table-column prop="operator" label="操作者" width="140" />
            <el-table-column prop="createTime" label="操作时间" width="180" />
            <el-table-column prop="content" label="备注" min-width="260" />
          </el-table>
        </section>
      </div>
    </div>

    <el-dialog
      v-model="expressVisible"
      title="物流轨迹"
      width="620px"
      append-to-body
      :close-on-click-modal="false"
    >
      <div v-loading="expressLoading" class="express-dialog">
        <div class="express-summary">
          <div>
            <span>快递公司</span>
            <strong>{{ expressInfo.expressName }}</strong>
          </div>
          <div>
            <span>快递单号</span>
            <strong>{{ expressInfo.expressNo }}</strong>
          </div>
          <div>
            <span>查询状态</span>
            <strong>{{ expressInfo.message }}</strong>
          </div>
        </div>
        <el-timeline v-if="expressInfo.traces.length" class="express-timeline">
          <el-timeline-item
            v-for="item in expressInfo.traces"
            :key="item.rowKey"
            :timestamp="item.time"
            placement="top"
          >
            <div class="express-trace">
              <div>{{ item.content }}</div>
              <span v-if="item.location">{{ item.location }}</span>
            </div>
          </el-timeline-item>
        </el-timeline>
        <el-empty v-else description="暂无物流轨迹" :image-size="70" />
      </div>
    </el-dialog>
  </el-drawer>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getOrderDetail, getOrderRecord, getRefundReason, approveOrderRefund, rejectOrderRefund, getOrderExpressTrace } from '@/api/order'
import {
  buildOrderActionPayload,
  extractResponseData,
  normalizeOrder,
  normalizeRecordList,
  normalizeRefundReasons
} from '../../shared'

defineOptions({
  name: 'OrderDetailDrawer'
})

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  orderId: {
    type: [Number, String],
    default: ''
  },
  orderSn: {
    type: String,
    default: ''
  },
  orderData: {
    type: Object,
    default: () => ({})
  },
  showRefundEntry: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits(['update:visible', 'close', 'go-refund', 'apply-refund', 'delivery', 'refresh'])

const localVisible = ref(false)
const loading = ref(false)
const recordLoading = ref(false)
const approveLoading = ref(false)
const rejectLoading = ref(false)
const expressVisible = ref(false)
const expressLoading = ref(false)
const detail = ref(normalizeOrder({ goods: [] }))
const recordList = ref([])
const refundReasonOptions = ref([])
const expressInfo = ref(createEmptyExpressInfo())

const orderStatusDescription = computed(() => {
  const status = detail.value.status
  if (status === 0) return '买家尚未支付，请关注支付超时时间'
  if (status === 1 && detail.value.canDelivery) return '买家已支付，等待商家发货'
  if (status === 1 && detail.value.canConfirmReceipt) return '商家已发货，等待买家确认收货'
  if (status === 6) return '买家已收货，等待评价'
  if (status === 2) return '订单已完成'
  if (status === 3 || status === -1) return '订单已取消'
  if (detail.value.canAuditRefund) return '订单存在待处理退款申请'
  return detail.value.refundReason || detail.value.remark || '订单信息已同步'
})

const receiverInfoRows = computed(() => [
  { label: '会员名称：', value: displayText(detail.value.nickname) },
  { label: '用户ID：', value: displayText(detail.value.userId) },
  { label: '收货人：', value: displayText(detail.value.address_real_name || detail.value.real_name || detail.value.consignee) },
  { label: '联系电话：', value: displayText(detail.value.userPhone) },
  { label: '收货地址：', value: displayText(detail.value.address) },
  { label: '订单备注：', value: displayText(detail.value.remark) }
])

const deliveryInfoRows = computed(() => [
  { label: '配送方式：', value: detail.value.hasPhysicalGoods ? '快递物流' : '无需配送' },
  { label: '发货时间：', value: displayText(detail.value.deliveryTime) },
  { label: '物流名称：', value: displayText(detail.value.expressName) },
  { label: '发货单号：', value: displayText(detail.value.expressNo) },
  { label: '确认收货：', value: displayText(detail.value.receiveTime) },
  { label: '完成时间：', value: displayText(detail.value.finishTime) }
])

const paymentInfoRows = computed(() => [
  { label: '已支付金额：', value: `¥${formatMoney(detail.value.payAmount)}` },
  { label: '支付方式：', value: displayText(detail.value.payTypeText) },
  { label: '支付时间：', value: displayText(detail.value.payTime) },
  { label: '退款状态：', value: displayText(detail.value.refundStatusText) },
  { label: '佣金状态：', value: displayText(detail.value.commissionStatusText) }
])

const refundInfoRows = computed(() => [
  { label: '退款订单号：', value: displayText(detail.value.refundSn) },
  { label: '原订单号：', value: displayText(detail.value.originalOrderSn || detail.value.orderSn) },
  { label: '退款状态：', value: displayText(detail.value.refundStatusText) },
  { label: '退款金额：', value: `¥${formatMoney(detail.value.refundAmount)}` },
  { label: '申请时间：', value: displayText(detail.value.refundApplyTime) },
  { label: '退款原因：', value: displayText(detail.value.refundReason) }
])

const goodsSubtotalAmount = computed(() => {
  return detail.value.goods.reduce((total, item) => {
    return total + Number(item.price || 0) * Number(item.quantity || 0)
  }, 0)
})

const goodsTotalQuantity = computed(() => {
  return detail.value.goods.reduce((total, item) => total + Number(item.quantity || 0), 0)
})

const postageAmount = computed(() => Number(firstFilled(detail.value.postage, detail.value.freight_amount, 0)))
const extraAmount = computed(() => Number(firstFilled(detail.value.extra_amount, detail.value.attach_amount, 0)))
const orderTotalAmount = computed(() => Number(firstFilled(detail.value.total_amount, detail.value.totalAmount, detail.value.payAmount, 0)))
const actualReceiptAmount = computed(() => displayText(detail.value.payTime) === '-' ? 0 : detail.value.payAmount)
const showRefundPanel = computed(() => {
  return detail.value.canAuditRefund || detail.value.refundSn || detail.value.refundAmount > 0 || detail.value.refundReason
})
const hasOverviewActions = computed(() => {
  return detail.value.canApplyRefund ||
    (props.showRefundEntry && detail.value.canAuditRefund) ||
    detail.value.canDelivery ||
    detail.value.canEditDelivery ||
    canViewExpress.value ||
    detail.value.canConfirmReceipt
})
const canViewExpress = computed(() => {
  return detail.value.hasPhysicalGoods &&
    hasFilledValue(detail.value.deliveryTime) &&
    hasFilledValue(detail.value.expressNo) &&
    hasFilledValue(detail.value.delivery_code || detail.value.express_code)
})

watch(() => props.visible, (val) => {
  localVisible.value = val
}, { immediate: true })

watch(localVisible, (val) => {
  emit('update:visible', val)
})

watch(() => props.orderData, (val) => {
  if (val && Object.keys(val).length) {
    detail.value = normalizeOrder(val)
  }
}, { deep: true, immediate: true })

function handleOpen() {
  recordList.value = []
  refundReasonOptions.value = []

  if (props.orderData && Object.keys(props.orderData).length) {
    detail.value = normalizeOrder(props.orderData)
  }

  void loadDetail()
  void loadRecords()
  void loadRefundReasons()
}

function handleClose() {
  expressVisible.value = false
  emit('close')
}

async function loadDetail() {
  const payload = buildOrderActionPayload({
    id: props.orderId || detail.value.id,
    order_id: props.orderId || detail.value.orderId,
    order_sn: props.orderSn || detail.value.orderSn
  })

  if (!payload.id && !payload.order_sn) return

  loading.value = true
  try {
    const res = await getOrderDetail(payload)
    const currentDetail = detail.value
    const currentRefundSn = currentDetail.refundSn || currentDetail.refund_sn || ''
    const currentOrderSn = currentDetail.originalOrderSn || currentDetail.orderSn || currentDetail.order_sn || ''
    const nextDetail = normalizeOrder(res)
    if (!nextDetail.refundSn && currentRefundSn) {
      nextDetail.refundSn = currentRefundSn
      nextDetail.refund_sn = currentRefundSn
    }
    if ((!nextDetail.originalOrderSn || nextDetail.originalOrderSn === '-') && currentOrderSn) {
      nextDetail.originalOrderSn = currentOrderSn
    }
    if ((!nextDetail.orderSn || nextDetail.orderSn === '-') && currentOrderSn) {
      nextDetail.orderSn = currentOrderSn
      nextDetail.order_sn = currentOrderSn
    }
    if (!nextDetail.goods.length && currentDetail.goods.length) {
      nextDetail.goods = currentDetail.goods
    }
    setDetail(nextDetail)
  } finally {
    loading.value = false
  }
}

async function loadRecords() {
  const payload = buildOrderActionPayload({
    id: props.orderId || detail.value.id,
    order_id: props.orderId || detail.value.orderId,
    order_sn: props.orderSn || detail.value.orderSn
  })

  if (!payload.id && !payload.order_sn) return

  recordLoading.value = true
  try {
    const res = await getOrderRecord(payload)
    recordList.value = normalizeRecordList(res)
  } finally {
    recordLoading.value = false
  }
}

async function loadRefundReasons() {
  const payload = buildOrderActionPayload({
    id: props.orderId || detail.value.id,
    order_id: props.orderId || detail.value.orderId,
    order_sn: props.orderSn || detail.value.orderSn
  })

  try {
    const res = await getRefundReason(payload)
    refundReasonOptions.value = normalizeRefundReasons(res)
  } catch {
    refundReasonOptions.value = []
  }
}

function handleGoRefund() {
  emit('go-refund', detail.value)
}

function handleApplyRefund() {
  emit('apply-refund', detail.value)
}

function handleDelivery() {
  emit('delivery', detail.value)
}

async function handleViewExpress() {
  expressVisible.value = true
  expressInfo.value = createEmptyExpressInfo()
  expressLoading.value = true
  try {
    const res = await getOrderExpressTrace(buildOrderActionPayload(detail.value))
    expressInfo.value = normalizeExpressInfo(extractResponseData(res))
  } catch (error) {
    ElMessage.error(error.message || '物流查询失败')
  } finally {
    expressLoading.value = false
  }
}

function setDetail(nextDetail) {
  detail.value = nextDetail
}

async function refresh() {
  await Promise.all([
    loadDetail(),
    loadRecords(),
    loadRefundReasons()
  ])
}

defineExpose({
  refresh
})

async function handleApproveRefund() {
  try {
    await ElMessageBox.confirm('确认同意该笔退款申请？退款将直接到账，不可撤销。', '退款审核通过', { type: 'warning' })
  } catch {
    return
  }

  approveLoading.value = true
  try {
    const payload = buildOrderActionPayload(detail.value)
    await approveOrderRefund(payload)
    ElMessage.success('退款审核已通过')
    await loadDetail()
    emit('refresh')
  } finally {
    approveLoading.value = false
  }
}

async function handleRejectRefund() {
  let value = ''
  try {
    const result = await ElMessageBox.prompt('请输入拒绝原因（可选）', '拒绝退款', {
      confirmButtonText: '确认拒绝',
      cancelButtonText: '取消',
      inputPlaceholder: '拒绝原因...',
      type: 'warning'
    })
    value = result.value || ''
  } catch {
    return
  }

  rejectLoading.value = true
  try {
    const payload = { ...buildOrderActionPayload(detail.value), reason: value || '' }
    await rejectOrderRefund(payload)
    ElMessage.success('已拒绝退款申请')
    await loadDetail()
    emit('refresh')
  } finally {
    rejectLoading.value = false
  }
}

function formatMoney(value) {
  return Number(value || 0).toFixed(2)
}

function displayText(value) {
  if (value === undefined || value === null || value === '') {
    return '-'
  }
  return String(value)
}

function firstFilled(...values) {
  const target = values.find(item => item !== undefined && item !== null && item !== '')
  return target === undefined ? 0 : target
}

function hasFilledValue(value) {
  return value !== undefined && value !== null && value !== '' && value !== '-'
}

function createEmptyExpressInfo() {
  return {
    expressName: '-',
    expressNo: '-',
    message: '-',
    traces: []
  }
}

function normalizeExpressInfo(data = {}) {
  const traces = Array.isArray(data.traces)
    ? data.traces.map((item, index) => ({
      rowKey: item.row_key || item.rowKey || index,
      time: displayText(item.time),
      content: displayText(item.content),
      location: item.location || ''
    }))
    : []

  return {
    expressName: displayText(data.express_name || data.expressName || detail.value.expressName),
    expressNo: displayText(data.express_no || data.expressNo || detail.value.expressNo),
    message: displayText(data.message || data.state || '查询成功'),
    traces
  }
}

</script>

<style lang="scss" scoped>
.order-detail-drawer {
  height: 100%;

  &__body {
    height: 100%;
    overflow-y: auto;
  }

  &__notice {
    margin-bottom: 18px;
  }
}

.detail-card {
  padding: 24px 26px;
  margin-bottom: 18px;
  background: var(--dashboard-surface);
  border: 1px solid var(--dashboard-border);
  border-radius: 4px;
  box-shadow: 0 2px 8px rgba(25, 28, 30, 0.04);

  &__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 18px;
  }

  &__title {
    margin: 0 0 18px;
    font-size: 16px;
    line-height: 1.4;
    font-weight: 700;
    color: var(--dashboard-text-primary);
  }

  &__head &__title {
    margin-bottom: 0;
  }

  &__actions {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;

    :deep(.el-button + .el-button) {
      margin-left: 0;
    }
  }

  &--overview {
    padding-top: 20px;
  }
}

.overview-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 10px 18px;
  font-size: 13px;
  line-height: 1.5;
  color: var(--dashboard-text-secondary);
}

.overview-divider {
  height: 1px;
  margin: 16px 0 20px;
  background: var(--dashboard-border);
}

.overview-main {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;

  h3 {
    margin: 0;
    font-size: 18px;
    line-height: 1.4;
    font-weight: 700;
    color: var(--dashboard-text-primary);
  }

  p {
    margin: 10px 0 0;
    font-size: 13px;
    line-height: 1.6;
    color: var(--dashboard-text-muted);
  }
}

.overview-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 14px;
}

.overview-side {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  min-width: 180px;
}

.overview-amount {
  text-align: right;

  span {
    display: block;
    font-size: 12px;
    color: var(--dashboard-text-muted);
  }

  strong {
    display: block;
    margin-top: 6px;
    font-size: 24px;
    line-height: 1.2;
    color: var(--dashboard-danger);
  }
}

.overview-action {
  min-width: 88px;
}

.overview-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  min-height: 44px;
  margin-top: 22px;
  padding-top: 16px;

  :deep(.el-button + .el-button) {
    margin-left: 0;
  }
}

.detail-columns {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 28px;

  &--refund {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px 28px;
  }
}

.detail-column {
  min-width: 0;

  h4 {
    margin: 0 0 18px;
    font-size: 14px;
    line-height: 1.4;
    font-weight: 700;
    color: var(--dashboard-text-secondary);
  }
}

.detail-row {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  margin-bottom: 13px;
  font-size: 13px;
  line-height: 1.5;

  &:last-child {
    margin-bottom: 0;
  }

  span {
    flex-shrink: 0;
    color: var(--dashboard-text-muted);
  }

  strong {
    min-width: 0;
    font-weight: 400;
    color: var(--dashboard-text-primary);
    word-break: break-all;
  }
}

.goods-table,
.record-table {
  width: 100%;

  :deep(.el-table__header-wrapper th) {
    height: 48px;
    background: var(--dashboard-surface-muted);
    color: var(--dashboard-text-primary);
    font-weight: 700;
  }

  :deep(.el-table__body td) {
    padding: 16px 0;
  }
}

.goods-cell {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;

  span {
    min-width: 0;
    overflow: hidden;
    color: var(--dashboard-text-primary);
    line-height: 1.5;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  &__image {
    width: 56px;
    height: 56px;
    flex-shrink: 0;
    border-radius: 4px;
    background: var(--dashboard-surface-muted);
  }

  &__placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--dashboard-text-muted);
    font-size: 22px;
  }
}

.goods-summary {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 18px;
  padding-top: 16px;
  font-size: 13px;
  color: var(--dashboard-text-muted);

  strong {
    font-size: 14px;
    color: var(--dashboard-text-primary);
  }
}

.amount-formula {
  padding: 18px 22px;
  background: var(--dashboard-surface-muted);
  text-align: right;
  font-size: 13px;
  line-height: 2;
  color: var(--dashboard-text-primary);

  strong {
    margin: 0 4px;
    font-size: 15px;
    color: var(--dashboard-danger);
  }
}

.reason-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 14px;
}

.express-dialog {
  min-height: 180px;
}

.express-summary {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
  padding: 16px;
  margin-bottom: 18px;
  background: var(--dashboard-surface-muted);
  border: 1px solid var(--dashboard-border);
  border-radius: 6px;

  span {
    display: block;
    margin-bottom: 6px;
    font-size: 12px;
    color: var(--dashboard-text-muted);
  }

  strong {
    display: block;
    min-width: 0;
    overflow: hidden;
    font-size: 13px;
    font-weight: 600;
    color: var(--dashboard-text-primary);
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}

.express-timeline {
  padding: 4px 4px 0;
}

.express-trace {
  font-size: 13px;
  line-height: 1.6;
  color: var(--dashboard-text-primary);

  span {
    display: block;
    margin-top: 4px;
    color: var(--dashboard-text-muted);
  }
}

:deep(.order-detail-drawer-shell) {
  background: var(--dashboard-bg) !important;

  .el-drawer__header {
    margin-bottom: 0;
    padding: 18px 22px;
    color: var(--dashboard-text-primary);
    background: var(--dashboard-surface);
    border-bottom: 1px solid var(--dashboard-border);
  }

  .el-drawer__body {
    background: var(--dashboard-bg);
  }
}

@media (max-width: 1180px) {
  .overview-main,
  .detail-card__head {
    flex-direction: column;
    align-items: stretch;
  }

  .overview-side {
    align-items: flex-start;
  }

  .overview-amount {
    text-align: left;
  }

  .overview-actions {
    justify-content: flex-start;
    flex-wrap: wrap;
  }

  .detail-columns {
    grid-template-columns: 1fr;
  }

  .detail-columns--refund {
    grid-template-columns: 1fr;
  }

}
</style>
