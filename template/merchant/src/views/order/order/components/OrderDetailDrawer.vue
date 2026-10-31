<template>
  <el-drawer
    v-model="localVisible"
    title="订单详情"
    direction="rtl"
    size="920px"
    class="order-detail-drawer-shell"
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
              <el-tag  :type="detail.statusTagType">{{ detail.statusText }}</el-tag>
              <el-tag  :type="detail.refundTagType">{{ detail.refundStatusText }}</el-tag>
              <el-tag  :type="detail.orderTypeTagType">{{ detail.orderTypeText }}</el-tag>
              <el-tag  :type="detail.payTypeTagType">{{ detail.payTypeText }}</el-tag>
            </div>
          </div>
          <div class="order-detail-drawer__amount-wrap">
            <div class="order-detail-drawer__amount-label">实付金额</div>
            <div class="order-detail-drawer__amount">¥{{ formatMoney(detail.payAmount) }}</div>
          </div>
        </div>

        <el-tabs v-model="activeTab" class="order-detail-drawer__tabs">
          <el-tab-pane label="基本信息" name="base">
            <div class="detail-sections">
              <section class="detail-section">
                <h3 class="detail-section__title">订单信息</h3>
                <div class="info-grid">
                  <div class="info-item info-item--wide">
                    <span class="info-label">订单号</span>
                    <span class="info-value">{{ detail.orderSn || '-' }}</span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">订单类型</span>
                    <el-tag size="mini" :type="detail.orderTypeTagType">{{ detail.orderTypeText || '-' }}</el-tag>
                  </div>
                  <div class="info-item">
                    <span class="info-label">订单状态</span>
                    <el-tag size="mini" :type="detail.statusTagType">{{ detail.statusText || '-' }}</el-tag>
                  </div>
                  <div class="info-item">
                    <span class="info-label">退款状态</span>
                    <el-tag size="mini" :type="detail.refundTagType">{{ detail.refundStatusText || '-' }}</el-tag>
                  </div>
                  <div class="info-item">
                    <span class="info-label">支付方式</span>
                    <span class="info-value">{{ detail.payTypeText || '-' }}</span>
                  </div>
                  <div v-if="hasCommissionInfo" class="info-item">
                    <span class="info-label">佣金状态</span>
                    <span class="info-value">{{ detail.commissionStatusText || '-' }}</span>
                  </div>
                  <div v-for="item in commissionInfoItems" :key="item.label" class="info-item">
                    <span class="info-label">{{ item.label }}</span>
                    <span class="info-value" :class="{ 'price-text': item.isMoney }">{{ item.value }}</span>
                  </div>
                </div>
              </section>

              <section class="detail-section">
                <h3 class="detail-section__title">用户与商户</h3>
                <div class="info-grid">
                  <div class="info-item">
                    <span class="info-label">用户昵称</span>
                    <span class="info-value">{{ detail.nickname || '-' }}</span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">用户ID</span>
                    <span class="info-value">{{ detail.userId || '-' }}</span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">手机号</span>
                    <span class="info-value">{{ detail.userPhone || '-' }}</span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">商户</span>
                    <span class="info-value">{{ detail.merchantName || '-' }}</span>
                  </div>
                </div>
              </section>

              <section class="detail-section">
                <h3 class="detail-section__title">金额信息</h3>
                <div class="amount-grid">
                  <div class="amount-card">
                    <span class="amount-card__label">原始金额</span>
                    <strong>¥{{ formatMoney(detail.originalAmount) }}</strong>
                  </div>
                  <div class="amount-card amount-card--primary">
                    <span class="amount-card__label">实付金额</span>
                    <strong>¥{{ formatMoney(detail.payAmount) }}</strong>
                  </div>
                  <div class="amount-card">
                    <span class="amount-card__label">退款金额</span>
                    <strong>¥{{ formatMoney(detail.refundAmount) }}</strong>
                  </div>
                </div>
              </section>

              <section class="detail-section">
                <h3 class="detail-section__title">物流信息</h3>
                <div class="info-grid">
                  <div class="info-item">
                    <span class="info-label">物流公司</span>
                    <span class="info-value">{{ detail.expressName || '-' }}</span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">物流单号</span>
                    <span class="info-value">{{ detail.expressNo || '-' }}</span>
                  </div>
                  <div class="info-item info-item--wide">
                    <span class="info-label">收货信息</span>
                    <span class="info-value">{{ detail.address || '-' }}</span>
                  </div>
                  <div class="info-item info-item--wide">
                    <span class="info-label">订单备注</span>
                    <span class="info-value">{{ detail.remark || '-' }}</span>
                  </div>
                </div>
              </section>

              <section class="detail-section">
                <h3 class="detail-section__title">时间节点</h3>
                <div class="time-grid">
                  <div v-for="item in timeNodes" :key="item.label" class="time-item">
                    <span class="time-item__label">{{ item.label }}</span>
                    <span class="time-item__value">{{ item.value || '-' }}</span>
                  </div>
                </div>
              </section>
            </div>
          </el-tab-pane>

          <el-tab-pane label="商品信息" name="goods">
            <div v-if="detail.goods.length" class="goods-list">
              <div v-for="item in detail.goods" :key="item.rowKey" class="goods-card">
                <el-image :src="item.image" class="goods-card__image">
                  <template #error>
                    <div class="goods-card__placeholder">
                      <LegacyIcon name="el-icon-picture-outline" />
                    </div>
                  </template>
                </el-image>
                <div class="goods-card__main">
                  <div class="goods-card__name">{{ item.goodsName }}</div>
                  <div class="goods-card__tags">
                    <el-tag size="mini" :type="item.goodsTypeTagType">{{ item.goodsTypeText }}</el-tag>
                    <el-tag size="mini" :type="item.orderTypeTagType">{{ item.orderTypeText }}</el-tag>
                  </div>
                  <div class="goods-card__meta">
                    <span>规格：{{ item.skuName || item.sku_name || '-' }}</span>
                    <span>商户：{{ item.merchantName || '-' }}</span>
                  </div>
                </div>
                <div class="goods-card__amount">
                  <strong>¥{{ formatMoney(item.price) }}</strong>
                  <span>x{{ item.quantity }}</span>
                </div>
              </div>
            </div>
            <el-empty v-if="!detail.goods.length" description="暂无商品信息" :image-size="70" />
          </el-tab-pane>

          <el-tab-pane label="操作记录" name="records">
            <div v-loading="recordLoading" class="record-list">
              <div v-for="item in recordList" :key="item.rowKey" class="record-item">
                <div class="record-item__dot" />
                <div class="record-item__body">
                  <div class="record-item__head">
                    <strong>{{ item.title }}</strong>
                    <span>{{ item.createTime }}</span>
                  </div>
                  <div class="record-item__content">{{ item.content }}</div>
                  <div class="record-item__operator">操作人：{{ item.operator }}</div>
                </div>
              </div>
            </div>
            <el-empty v-if="!recordLoading && !recordList.length" description="暂无操作记录" :image-size="70" />
          </el-tab-pane>

          <el-tab-pane label="退款信息" name="refund">
            <div class="refund-section">
              <section class="detail-section">
                <h3 class="detail-section__title">退款概览</h3>
                <div class="info-grid">
                  <div class="info-item">
                    <span class="info-label">退款状态</span>
                    <el-tag size="mini" :type="detail.refundTagType">{{ detail.refundStatusText || '-' }}</el-tag>
                  </div>
                  <div class="info-item">
                    <span class="info-label">退款金额</span>
                    <span class="info-value price-text">¥{{ formatMoney(detail.refundAmount) }}</span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">申请时间</span>
                    <span class="info-value">{{ detail.refundApplyTime || '-' }}</span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">订单状态</span>
                    <span class="info-value">{{ detail.statusText || '-' }}</span>
                  </div>
                  <div class="info-item info-item--wide">
                    <span class="info-label">退款原因</span>
                    <span class="info-value">{{ detail.refundReason || '-' }}</span>
                  </div>
                </div>
              </section>
              <el-empty
                v-if="detail.refundStatus === 0 && !detail.refundReason"
                class="refund-empty"
                description="当前订单暂无退款记录"
                :image-size="70"
              />
            </div>
          </el-tab-pane>
        </el-tabs>
      </div>
    </div>

    <div class="order-detail-drawer__footer">
      <div class="order-detail-drawer__footer-actions">
        <el-button
          v-if="showRefundEntry && detail.refundStatus > 0"
          type="warning"
          plain
          @click="handleGoRefund"
        >
          查看退款
        </el-button>
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
import { getOrderDetail, getOrderRecord } from '@/api/order'
import { normalizeOrder, normalizeRecordList } from '../../shared'

defineOptions({
  name: 'OrderDetailDrawer'
})

const props = defineProps({
  // 通过 v-model 绑定（Vue3 默认 prop 名为 modelValue，而非 Vue2 的 visible）
  modelValue: {
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
  },
  showRefundEntry: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits(['update:modelValue', 'close', 'delivery', 'refund-apply', 'go-refund'])

const localVisible = ref(false)
const loading = ref(false)
const recordLoading = ref(false)
const activeTab = ref('base')
const detail = ref(normalizeOrder({ goods: [] }))
const recordList = ref([])

const timeNodes = computed(() => {
  return [
    { label: '下单时间', value: detail.value.createTime },
    { label: '支付时间', value: detail.value.payTime },
    { label: '发货时间', value: detail.value.deliveryTime },
    { label: '确认收货', value: detail.value.receiveTime },
    { label: '完成时间', value: detail.value.finishTime },
    { label: '退款申请', value: detail.value.refundApplyTime }
  ]
})

const canRefundApply = computed(() => {
  return [1, 6].includes(detail.value.status) && detail.value.refundStatus === 0
})

const commissionInfoItems = computed(() => {
  const items = []

  if (hasPositiveMoney(detail.value.commissionAmount)) {
    items.push({ label: '佣金金额', value: `¥${formatMoney(detail.value.commissionAmount)}`, isMoney: true })
  }
  if (detail.value.referrerText) {
    items.push({ label: '推广人', value: formatPromoter(detail.value.referrerText, detail.value.referrerId) })
  }
  if (detail.value.secondReferrerText) {
    items.push({ label: '二级推广人', value: formatPromoter(detail.value.secondReferrerText, detail.value.secondReferrerId) })
  }
  if (hasPositiveMoney(detail.value.firstCommissionAmount)) {
    items.push({ label: '一级返佣金额', value: `¥${formatMoney(detail.value.firstCommissionAmount)}`, isMoney: true })
  }
  if (hasPositiveMoney(detail.value.secondCommissionAmount)) {
    items.push({ label: '二级返佣金额', value: `¥${formatMoney(detail.value.secondCommissionAmount)}`, isMoney: true })
  }

  return items
})

const hasCommissionInfo = computed(() => {
  return commissionInfoItems.value.length > 0
})

watch(() => props.modelValue, (val) => {
  localVisible.value = val
})

watch(localVisible, (val) => {
  emit('update:modelValue', val)
})

watch(() => props.orderData, (val) => {
  if (val && Object.keys(val).length) {
    detail.value = normalizeOrder(val)
  }
}, { deep: true })

function handleOpen() {
  activeTab.value = 'base'
  recordList.value = []

  if (props.orderData && Object.keys(props.orderData).length) {
    detail.value = normalizeOrder(props.orderData)
  }

  loadDetail()
  loadRecords()
}

function handleClose() {
  emit('close')
}

function loadDetail() {
  const id = props.orderId || detail.value.id
  if (!id) return

  loading.value = true
  getOrderDetail(id).then(res => {
    const nextDetail = normalizeOrder(res)
    if (!nextDetail.goods.length && detail.value.goods.length) {
      nextDetail.goods = detail.value.goods
    }
    detail.value = nextDetail
  }).finally(() => {
    loading.value = false
  })
}

function loadRecords() {
  const id = props.orderId || detail.value.id
  if (!id) return

  recordLoading.value = true
  getOrderRecord(id).then(res => {
    recordList.value = normalizeRecordList(res)
  }).finally(() => {
    recordLoading.value = false
  })
}

function handleDelivery() {
  emit('delivery', detail.value)
}

function handleRefundApply() {
  emit('refund-apply', detail.value)
}

function handleGoRefund() {
  emit('go-refund', detail.value)
}

function hasPositiveMoney(value) {
  return Number(value || 0) > 0
}

function formatPromoter(text, id) {
  const name = String(text || '').trim()
  const promoterId = Number(id || 0)
  if (!name) {
    return ''
  }
  return promoterId > 0 ? `${name}（ID:${promoterId}）` : name
}

function formatMoney(value) {
  return Number(value || 0).toFixed(2)
}
</script>

<style lang="scss" scoped>
.order-detail-drawer {
  --order-detail-bg: #f7f8fa;
  --order-detail-surface: #ffffff;
  --order-detail-muted: #f5f7fa;
  --order-detail-soft: #fafafa;
  --order-detail-border: #ebeef5;
  --order-detail-border-strong: #dcdfe6;
  --order-detail-shadow: rgba(25, 28, 30, 0.04);

  position: relative;
  height: 100%;
  background: var(--order-detail-bg);

  &__body {
    height: calc(100% - 76px);
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
    background: linear-gradient(135deg, var(--order-detail-muted), var(--order-detail-surface));
    border: 1px solid var(--order-detail-border);
    box-shadow: 0 1px 2px var(--order-detail-shadow);
  }

  &__hero-main {
    min-width: 0;
    flex: 1;
  }

  &__sn {
    font-size: 20px;
    font-weight: 700;
    color: var(--text-title);
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
    color: var(--text-secondary);
  }

  &__amount {
    margin-top: 8px;
    font-size: 28px;
    font-weight: 700;
    color: var(--text-price);
  }

  &__tabs {
    background: var(--order-detail-surface);
    border-radius: 16px;
    padding: 0 18px 18px;
    border: 1px solid var(--order-detail-border);
    box-shadow: 0 1px 2px var(--order-detail-shadow);

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
    background: var(--order-detail-surface);
    border-top: 1px solid var(--order-detail-border);
  }

  &__footer-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
  }
}

.price-text {
  color: var(--text-price);
  font-weight: 600;
}

.detail-sections {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.detail-section {
  padding: 16px;
  border-radius: 12px;
  background: var(--order-detail-muted);
  border: 1px solid var(--order-detail-border);

  &__title {
    margin: 0 0 14px;
    font-size: 14px;
    font-weight: 700;
    color: var(--text-title);
  }
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px 16px;
}

.info-item {
  min-width: 0;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 6px;

  &--wide {
    grid-column: span 2;
  }

  :deep(.el-tag) {
    align-self: flex-start;
    width: auto;
    max-width: 100%;
  }
}

.info-label {
  font-size: 12px;
  color: var(--text-secondary);
}

.info-value {
  min-height: 20px;
  display: inline-block;
  max-width: 100%;
  font-size: 13px;
  line-height: 1.5;
  color: var(--text-primary);
  word-break: break-all;
}

.amount-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}

.amount-card {
  padding: 14px;
  border-radius: 10px;
  background: var(--order-detail-surface);
  border: 1px solid var(--order-detail-border);

  &__label {
    display: block;
    margin-bottom: 8px;
    font-size: 12px;
    color: var(--text-secondary);
  }

  strong {
    font-size: 18px;
    color: var(--text-primary);
  }

  &--primary strong {
    color: var(--text-price);
  }
}

.time-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}

.time-item {
  padding: 12px;
  border-radius: 10px;
  background: var(--order-detail-surface);
  border: 1px solid var(--order-detail-border);

  &__label {
    display: block;
    margin-bottom: 6px;
    font-size: 12px;
    color: var(--text-secondary);
  }

  &__value {
    font-size: 13px;
    color: var(--text-primary);
  }
}

.goods-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.goods-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px;
  border-radius: 12px;
  background: var(--order-detail-muted);
  border: 1px solid var(--order-detail-border);

  &__image {
    width: 72px;
    height: 72px;
    flex-shrink: 0;
    border-radius: 8px;
    background: var(--order-detail-soft);
  }

  &__placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-secondary);
    font-size: 24px;
  }

  &__main {
    flex: 1;
    min-width: 0;
  }

  &__name {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
    line-height: 1.5;
  }

  &__tags,
  &__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
  }

  &__meta {
    font-size: 12px;
    color: var(--text-secondary);
  }

  &__amount {
    width: 110px;
    text-align: right;

    strong,
    span {
      display: block;
    }

    strong {
      color: var(--text-price);
      font-size: 16px;
    }

    span {
      margin-top: 6px;
      color: var(--text-secondary);
      font-size: 12px;
    }
  }
}

.record-list {
  min-height: 120px;
}

.record-item {
  position: relative;
  display: flex;
  gap: 12px;
  padding-bottom: 18px;

  &:last-child {
    padding-bottom: 0;
  }

  &__dot {
    width: 9px;
    height: 9px;
    margin-top: 8px;
    border-radius: 50%;
    background: var(--ds-primary);
    flex-shrink: 0;
  }

  &__body {
    flex: 1;
    min-width: 0;
    padding: 12px 14px;
    border-radius: 12px;
    background: var(--order-detail-muted);
    border: 1px solid var(--order-detail-border);
  }

  &__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;

    strong {
      font-size: 14px;
      color: var(--text-primary);
    }

    span {
      font-size: 12px;
      color: var(--text-secondary);
      white-space: nowrap;
    }
  }

  &__content,
  &__operator {
    margin-top: 8px;
    font-size: 13px;
    line-height: 1.5;
    color: var(--text-regular);
  }

  &__operator {
    color: var(--text-secondary);
  }
}

.refund-section {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.refund-empty {
  margin-top: 12px;
}

:global(.order-detail-drawer-shell) {
  --order-detail-bg: #f7f8fa;
  --order-detail-surface: #ffffff;
  --order-detail-muted: #f5f7fa;
  --order-detail-soft: #fafafa;
  --order-detail-border: #ebeef5;
  --order-detail-shadow: rgba(25, 28, 30, 0.04);

  color: var(--text-primary);
  background: var(--order-detail-surface) !important;

  .el-drawer__header,
  .el-drawer__body {
    color: var(--text-primary);
    background: var(--order-detail-surface) !important;
    border-color: var(--order-detail-border);
  }

  .order-detail-drawer,
  .order-detail-drawer__body {
    background: var(--order-detail-bg) !important;
  }

  .order-detail-drawer__hero,
  .order-detail-drawer__tabs,
  .order-detail-drawer__footer,
  .amount-card,
  .time-item {
    color: var(--text-primary);
    background-color: var(--order-detail-surface) !important;
    border-color: var(--order-detail-border) !important;
  }

  .detail-section,
  .goods-card,
  .record-item__body {
    color: var(--text-primary);
    background-color: var(--order-detail-muted) !important;
    border-color: var(--order-detail-border) !important;
  }

  .goods-card__image {
    background-color: var(--order-detail-soft) !important;
  }

  .order-detail-drawer__sn,
  .detail-section__title,
  .info-value,
  .amount-card strong,
  .time-item__value,
  .goods-card__name,
  .record-item__head strong {
    color: var(--text-primary) !important;
  }

  .order-detail-drawer__amount-label,
  .info-label,
  .amount-card__label,
  .time-item__label,
  .goods-card__meta,
  .goods-card__amount span,
  .record-item__head span,
  .record-item__operator {
    color: var(--text-secondary) !important;
  }

  .record-item__content {
    color: var(--text-regular) !important;
  }

  .price-text,
  .order-detail-drawer__amount,
  .amount-card--primary strong,
  .goods-card__amount strong {
    color: var(--text-price) !important;
  }

  .el-tabs__nav-wrap::after {
    background-color: var(--order-detail-border) !important;
  }

  .el-tabs__item.is-active,
  .el-tabs__item:hover {
    color: var(--ds-primary) !important;
  }

  .el-tabs__active-bar {
    background-color: var(--ds-primary) !important;
  }
}
</style>
