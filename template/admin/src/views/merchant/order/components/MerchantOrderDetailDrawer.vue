<template>
  <el-drawer
    v-model="localVisible"
    title="订单详情"
    direction="rtl"
    size="920px"
    :wrapper-closable="true"
    @open="handleOpen"
    @close="handleClose"
  >
    <div class="order-detail-drawer">
      <div v-if="localVisible" v-loading="loading" class="order-detail-drawer__body">
        <div class="order-detail-drawer__hero">
          <div class="order-detail-drawer__hero-main">
            <div class="order-detail-drawer__sn">{{ detail.orderSn || '-' }}</div>
            <div class="order-detail-drawer__meta">
              <el-tag :type="detail.statusTagType">{{ detail.statusText }}</el-tag>
              <el-tag :type="detail.refundTagType">{{ detail.refundStatusText }}</el-tag>
              <el-tag :type="detail.orderTypeTagType">{{ detail.orderTypeText }}</el-tag>
              <el-tag :type="detail.payTypeTagType">{{ detail.payTypeText }}</el-tag>
            </div>
          </div>
          <div class="order-detail-drawer__amount-wrap">
            <div class="order-detail-drawer__amount-label">实付金额</div>
            <div class="order-detail-drawer__amount">¥{{ formatMoney(detail.payAmount) }}</div>
          </div>
        </div>

        <el-tabs v-model="activeTab" class="order-detail-drawer__tabs">
          <el-tab-pane label="基本信息" name="base">
            <el-descriptions :column="2" border>
              <el-descriptions-item label="订单号" :span="2">{{ detail.orderSn || '-' }}</el-descriptions-item>
              <el-descriptions-item label="用户昵称">{{ detail.nickname || '-' }}</el-descriptions-item>
              <el-descriptions-item label="用户ID">{{ detail.userId || '-' }}</el-descriptions-item>
              <el-descriptions-item label="手机号">{{ detail.userPhone || '-' }}</el-descriptions-item>
              <el-descriptions-item label="订单类型">{{ detail.orderTypeText || '-' }}</el-descriptions-item>
              <el-descriptions-item label="支付方式">{{ detail.payTypeText || '-' }}</el-descriptions-item>
              <el-descriptions-item label="订单状态">{{ detail.statusText || '-' }}</el-descriptions-item>
              <el-descriptions-item label="退款状态">{{ detail.refundStatusText || '-' }}</el-descriptions-item>
              <el-descriptions-item label="原始金额">¥{{ formatMoney(detail.originalAmount) }}</el-descriptions-item>
              <el-descriptions-item label="实付金额">¥{{ formatMoney(detail.payAmount) }}</el-descriptions-item>
              <el-descriptions-item label="退款金额">¥{{ formatMoney(detail.refundAmount) }}</el-descriptions-item>
              <el-descriptions-item label="下单时间">{{ detail.createTime || '-' }}</el-descriptions-item>
              <el-descriptions-item label="支付时间">{{ detail.payTime || '-' }}</el-descriptions-item>
              <el-descriptions-item label="发货时间">{{ detail.deliveryTime || '-' }}</el-descriptions-item>
              <el-descriptions-item label="确认收货">{{ detail.receiveTime || '-' }}</el-descriptions-item>
              <el-descriptions-item label="物流公司">{{ detail.expressName || '-' }}</el-descriptions-item>
              <el-descriptions-item label="物流单号">{{ detail.expressNo || '-' }}</el-descriptions-item>
              <el-descriptions-item label="收货信息" :span="2">{{ detail.address || '-' }}</el-descriptions-item>
              <el-descriptions-item label="退款原因" :span="2">{{ detail.refundReason || '-' }}</el-descriptions-item>
              <el-descriptions-item label="订单备注" :span="2">{{ detail.remark || '-' }}</el-descriptions-item>
            </el-descriptions>
          </el-tab-pane>

          <el-tab-pane label="商品信息" name="goods">
            <el-table :data="detail.goods" border :row-key="(row, i) => row.rowKey || i">
              <el-table-column label="商品名称" min-width="180" show-overflow-tooltip>
                <template #default="scope">{{ scope.row.goodsName }}</template>
              </el-table-column>
              <el-table-column label="商品类型" width="110" align="center">
                <template #default="scope">
                  <el-tag size="mini" :type="scope.row.goodsTypeTagType">{{ scope.row.goodsTypeText }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column label="数量" width="80" align="center">
                <template #default="scope">{{ scope.row.quantity }}</template>
              </el-table-column>
              <el-table-column label="单价" width="100" align="center">
                <template #default="scope">
                  <span class="price-text">¥{{ formatMoney(scope.row.price) }}</span>
                </template>
              </el-table-column>
            </el-table>
            <el-empty v-if="!detail.goods.length" description="暂无商品信息" :image-size="70" />
          </el-tab-pane>
        </el-tabs>
      </div>
    </div>

    <div class="order-detail-drawer__footer">
      <div class="order-detail-drawer__footer-actions">
        <el-button
          v-if="detail.canDelivery"
          type="success"
          @click="handleDelivery"
        >发货</el-button>
        <el-button
          v-if="canRefundApply"
          type="warning"
          @click="handleRefundApply"
        >申请退款</el-button>
        <el-button type="primary" @click="localVisible = false">关 闭</el-button>
      </div>
    </div>
  </el-drawer>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { getMerchantOrderDetail } from '@/api/merchantOrder'
import { normalizeOrder } from '@/views/order/shared'

defineOptions({
  name: 'MerchantOrderDetailDrawer'
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
  orderData: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['update:visible', 'close', 'delivery', 'refund-apply'])

const localVisible = ref(props.visible)
const loading = ref(false)
const activeTab = ref('base')
const detail = ref(normalizeOrder({ goods: [] }))

const canRefundApply = computed(() => [1, 6].includes(detail.value.status) && detail.value.refundStatus === 0)

watch(() => props.visible, val => {
  localVisible.value = val
}, { immediate: true })

watch(localVisible, val => {
  emit('update:visible', val)
})

watch(() => props.orderData, val => {
  if (val && Object.keys(val).length) {
    detail.value = normalizeOrder(val)
  }
}, { deep: true, immediate: true })

function handleOpen() {
  activeTab.value = 'base'
  if (props.orderData && Object.keys(props.orderData).length) {
    detail.value = normalizeOrder(props.orderData)
  }
  loadDetail()
}

function handleClose() {
  emit('close')
}

function loadDetail() {
  const id = props.orderId || detail.value.id
  if (!id) return

  loading.value = true
  getMerchantOrderDetail(id).then(res => {
    const data = res && res.data ? res.data : res
    detail.value = normalizeOrder(data)
  }).finally(() => {
    loading.value = false
  })
}

function handleDelivery() {
  emit('delivery', detail.value)
}

function handleRefundApply() {
  emit('refund-apply', detail.value)
}

function formatMoney(value) {
  return Number(value || 0).toFixed(2)
}
</script>

<style lang="scss" scoped>
.order-detail-drawer {
  position: relative;
  height: 100%;
  background: #f7f9fc;

  &__body {
    height: calc(100% - 72px);
    padding: 0 20px 20px;
    overflow-y: auto;
  }

  &__hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 20px;
    margin: 12px 0 16px;
    border-radius: 16px;
    background: linear-gradient(135deg, rgba(64, 158, 255, 0.14), rgba(103, 194, 58, 0.08));
  }

  &__hero-main {
    min-width: 0;
    flex: 1;
  }

  &__sn {
    font-size: 20px;
    font-weight: 700;
    color: #303133;
    line-height: 1.4;
    word-break: break-all;
  }

  &__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
  }

  &__amount-wrap {
    flex-shrink: 0;
    text-align: right;
  }

  &__amount-label {
    font-size: 12px;
    color: #909399;
  }

  &__amount {
    margin-top: 8px;
    font-size: 28px;
    font-weight: 700;
    color: #f56c6c;
  }

  &__tabs {
    background: #fff;
    border-radius: 16px;
    padding: 0 18px 18px;
    box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);

    :deep(.el-tabs__header) {
      margin-bottom: 18px;
    }
  }

  &__footer {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding: 14px 20px;
    background: #fff;
    border-top: 1px solid #ebeef5;
  }

  &__footer-actions {
    display: flex;
    align-items: center;
    gap: 8px;
  }
}

.price-text {
  color: #f56c6c;
  font-weight: 600;
}
</style>
