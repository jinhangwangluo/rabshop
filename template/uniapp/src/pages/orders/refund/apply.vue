<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="refund-page">
    <app-safe-top background="#fff" title='申请退款'>
    </app-safe-top>

    <view class="refund-page__content">
      <view class="product-card">
        <image v-if="product.image" class="product-card__image" :src="product.image" mode="aspectFill"></image>
        <view v-else class="product-card__image product-card__image--empty"></view>
        <view class="product-card__body">
          <text class="product-card__title">{{ product.title || '--' }}</text>
          <text v-if="product.sku" class="product-card__sku">{{ product.sku }}</text>
          <view class="product-card__footer">
            <text class="product-card__count">数量：x{{ product.quantity }}</text>
            <text class="product-card__price">¥ {{ refundAmountText }}</text>
          </view>
        </view>
      </view>

      <view class="config-card">
        <view class="config-row config-row--tap" hover-class="config-row--hover" @click="handleOpenReason">
          <text class="config-row__label">退款原因</text>
          <view class="config-row__value-wrap">
            <text class="config-row__value" :class="{ 'config-row__value--selected': selectedReason }">{{ reasonText }}</text>
            <uni-icons type="right" size="18" color="#4c4546"></uni-icons>
          </view>
        </view>

        <view class="config-row">
          <text class="config-row__label">退款数量</text>
          <view class="quantity-stepper">
            <view class="quantity-stepper__button" hover-class="quantity-stepper__button--hover" @click="handleDecrease">
              <uni-icons type="minus" size="22" :color="decreaseIconColor"></uni-icons>
            </view>
            <text class="quantity-stepper__value">{{ quantity }}</text>
            <view class="quantity-stepper__button" hover-class="quantity-stepper__button--hover" @click="handleIncrease">
              <uni-icons type="plus" size="22" color="#4c4546"></uni-icons>
            </view>
          </view>
        </view>

        <view class="config-row">
          <text class="config-row__label">退款金额</text>
          <text class="config-row__amount">¥ {{ refundAmountText }}</text>
        </view>
      </view>

      <view class="form-card">
        <text class="form-card__title">退款说明</text>
        <textarea
          class="refund-textarea"
          v-model="refundRemark"
          placeholder="请输入详细退款理由"
          placeholder-class="refund-textarea__placeholder"
          :maxlength="200"
        ></textarea>
      </view>

      <view class="upload-card">
        <view class="upload-card__header">
          <text class="upload-card__title">上传凭证</text>
          <text class="upload-card__limit">（最多6张）</text>
        </view>

        <view class="proof-list">
          <view class="proof-upload" hover-class="proof-upload--hover" @click="handleAddProof">
            <text class="iconfont icon-camera proof-upload__icon"></text>
            <text class="proof-upload__text">添加图片</text>
          </view>

          <view v-for="item in proofs" :key="item.id" class="proof-item">
            <image class="proof-item__image" :src="item.url" mode="aspectFill"></image>
            <view class="proof-item__remove" hover-class="proof-item__remove--hover" @click="handleRemoveProof(item.id)">
              <uni-icons type="closeempty" size="15" color="#ffffff"></uni-icons>
            </view>
          </view>
        </view>
      </view>
    </view>

    <view class="refund-footer">
      <button class="refund-footer__button" :disabled="submitting" @click="handleSubmit">
        {{ submitting ? '提交中...' : '提交申请' }}
      </button>
    </view>

    <view v-if="showReasonSheet" class="reason-mask" @click="handleCloseReason">
      <view class="reason-sheet" @click.stop>
        <view class="reason-sheet__header">
          <text class="reason-sheet__title">选择退款原因</text>
          <view class="reason-sheet__close" hover-class="reason-sheet__close--hover" @click="handleCloseReason">
            <uni-icons type="closeempty" size="22" color="#4c4546"></uni-icons>
          </view>
        </view>
        <view class="reason-sheet__list">
          <view
            v-for="item in reasonOptions"
            :key="item.value"
            class="reason-option"
            :class="{ 'reason-option--active': selectedReason === item.value }"
            hover-class="reason-option--hover"
            @click="handleSelectReason(item)"
          >
            <text class="reason-option__text">{{ item.label }}</text>
            <view v-if="selectedReason === item.value" class="reason-option__check">
              <uni-icons type="checkmarkempty" size="16" color="#ffffff"></uni-icons>
            </view>
          </view>
        </view>
      </view>
    </view>
  </view>
</template>

<script setup>
import { computed, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { refundOrder } from '@/api/order'
import { getCachedMiniappSubscribeMessages, requestMiniappSubscribeMessagesByEvents } from '@/utils/miniapp_subscribe'
import { resolveAssetUrl } from '@/utils/runtime_config'
import { themeState } from '@/utils/theme'

const MAX_PROOF_COUNT = 6

function toNumber(value, fallback = 0) {
  const number = Number(value)
  return Number.isFinite(number) ? number : fallback
}

defineOptions({ name: 'OrderRefundPage' })

const orderSn = ref('')
const orderId = ref(0)
const orderGoodsId = ref(0)
const product = ref({
  title: '',
  sku: '',
  quantity: 1,
  price: 0,
  image: ''
})
const quantity = ref(1)
const selectedReason = ref('')
const refundRemark = ref('')
const submitting = ref(false)
const showReasonSheet = ref(false)
const proofs = ref([])
const reasonOptions = ref([
  { label: '商品质量问题', value: 'quality' },
  { label: '商品与描述不符', value: 'mismatch' },
  { label: '收到商品破损', value: 'damaged' },
  { label: '不想要了', value: 'changed_mind' }
])

const refundAmountText = computed(() => {
  const amount = Number(product.value.price || 0) * Number(quantity.value || 1)
  return formatAmount(amount)
})
const selectedReasonLabel = computed(() => {
  const option = reasonOptions.value.find(item => item.value === selectedReason.value)
  return option ? option.label : ''
})
const reasonText = computed(() => {
  return selectedReasonLabel.value || '请选择'
})
const decreaseIconColor = computed(() => {
  return quantity.value <= 1 ? '#c3c7cc' : '#4c4546'
})

onLoad((options = {}) => {
  initQuery(options)
  prefetchSubscribeMessages()
})

/**
 * 解析页面入参并预填退款商品信息。
 *
 * @param {Object} options 页面路由参数。
 * @returns {void}
 */
function initQuery(options = {}) {
  orderSn.value = String(options.order_sn || options.orderSn || '').trim()
  orderId.value = toNumber(options.order_id || options.orderId || options.id, 0)
  orderGoodsId.value = toNumber(options.order_goods_id || options.orderGoodsId, 0)

  const rawQuantity = Math.max(1, toNumber(options.quantity || options.num || 1, 1))
  const rawPrice = Math.max(0, toNumber(options.price || options.display_price || 0, 0))
  const rawImage = resolveAssetUrl(String(options.image || options.goods_image || ''))
  const rawSku = String(options.sku_text || options.sku_name || options.sku || '').trim()

  product.value = {
    title: String(options.goods_name || options.title || '').trim(),
    sku: rawSku,
    quantity: rawQuantity,
    price: rawPrice,
    image: rawImage
  }
  quantity.value = rawQuantity
}

/**
 * 预取小程序订阅消息配置，后续提交按钮直接读前端缓存。
 *
 * @returns {void}
 */
function prefetchSubscribeMessages() {
  getCachedMiniappSubscribeMessages({
    showError: false,
    loginRedirect: false
  }).catch(() => {})
}

/**
 * 返回上一页；无历史栈时回到来源订单页。
 *
 * @returns {void}
 */
function handleBack() {
  uni.navigateBack({
    fail: () => {
      const url = buildOrderDetailUrl()
      uni.navigateTo({ url })
    }
  })
}

/**
 * 构建订单详情页回跳地址。
 *
 * @returns {string}
 */
function buildOrderDetailUrl() {
  if (orderSn.value) {
    return `/pages/orders/order/detail/index?order_sn=${encodeURIComponent(orderSn.value)}`
  }
  if (orderId.value > 0) {
    return `/pages/orders/order/detail/index?id=${orderId.value}`
  }

  return '/pages/orders/order/list/index'
}

/**
 * 打开退款原因选择面板。
 *
 * @returns {void}
 */
function handleOpenReason() {
  showReasonSheet.value = true
}

/**
 * 关闭退款原因选择面板。
 *
 * @returns {void}
 */
function handleCloseReason() {
  showReasonSheet.value = false
}

/**
 * 选择退款原因。
 *
 * @param {Object} item 原因选项。
 * @returns {void}
 */
function handleSelectReason(item) {
  selectedReason.value = item.value
  showReasonSheet.value = false
}

/**
 * 减少退款数量，最低为 1。
 *
 * @returns {void}
 */
function handleDecrease() {
  if (quantity.value <= 1) {
    return
  }
  quantity.value -= 1
}

/**
 * 增加退款数量，最高不超过商品数量。
 *
 * @returns {void}
 */
function handleIncrease() {
  if (quantity.value >= product.value.quantity) {
    uni.showToast({ title: '已达到可退数量上限', icon: 'none' })
    return
  }
  quantity.value += 1
}

/**
 * 模拟添加凭证入口。
 *
 * @returns {void}
 */
function handleAddProof() {
  if (proofs.value.length >= MAX_PROOF_COUNT) {
    uni.showToast({ title: '最多上传6张凭证', icon: 'none' })
    return
  }
  uni.showToast({ title: '请选择图片', icon: 'none' })
}

/**
 * 删除上传凭证。
 *
 * @param {number} id 凭证 ID。
 * @returns {void}
 */
function handleRemoveProof(id) {
  uni.showModal({
    title: '删除凭证',
    content: '确认删除这张凭证吗？',
    confirmColor: themeState.primary_color,
    success: (res) => {
      if (res.confirm) {
        proofs.value = proofs.value.filter(item => item.id !== id)
      }
    }
  })
}

/**
 * 提交退款申请。
 *
 * @returns {void}
 */
async function handleSubmit() {
  if (submitting.value) {
    return
  }
  if (!orderSn.value) {
    uni.showToast({ title: '订单信息缺失，无法提交退款', icon: 'none' })
    return
  }
  if (!orderGoodsId.value) {
    uni.showToast({ title: '商品信息缺失，无法提交退款', icon: 'none' })
    return
  }
  if (!selectedReason.value) {
    uni.showToast({ title: '请选择退款原因', icon: 'none' })
    return
  }
  if (!refundRemark.value.trim()) {
    uni.showToast({ title: '请输入详细退款理由', icon: 'none' })
    return
  }

  submitting.value = true

  try {
    await requestMiniappSubscribeMessagesByEvents(['refund_success', 'refund_failed'])
    uni.showLoading({ title: '提交中' })
    const refundReason = [selectedReasonLabel.value, refundRemark.value.trim()]
      .filter(Boolean)
      .join('：')
    const res = await refundOrder({
      order_sn: orderSn.value,
      reason: refundReason,
      items: [
        {
          order_goods_id: orderGoodsId.value,
          refund_num: Math.max(1, Number(quantity.value || 1))
        }
      ]
    })

    if (res.code !== 200 || !res.data) {
      uni.showToast({ title: res.msg || '退款申请提交失败', icon: 'none' })
      return
    }

    const refundSn = String(res.data.refund_sn || '').trim()
    uni.showToast({ title: '退款申请已提交', icon: 'success' })
    setTimeout(() => {
      if (refundSn) {
        uni.redirectTo({
          url: `/pages/orders/refund/detail?refund_sn=${encodeURIComponent(refundSn)}`
        })
        return
      }

      uni.navigateBack({
        fail: () => {
          uni.navigateTo({ url: buildOrderDetailUrl() })
        }
      })
    }, 300)
  } catch (error) {
    console.error('退款申请提交失败', error)
    uni.showToast({ title: '退款申请提交失败，请稍后重试', icon: 'none' })
  } finally {
    submitting.value = false
    uni.hideLoading()
  }
}

/**
 * 格式化金额。
 *
 * @param {number|string} value 金额数值。
 * @returns {string} 两位小数字符串。
 */
function formatAmount(value) {
  const amount = Number(value || 0)
  return Number.isFinite(amount) ? amount.toFixed(2) : '0.00'
}
</script>


<style lang="scss">
page {
  background: #f5f5f5;
}
</style>

<style lang="scss" scoped>
.refund-page {
  min-height: 100%;
  background: #f5f5f5;
  color: #1a1c1c;
  --refund-orange: var(--brand-primary, #ea580c);
  --refund-orange-strong: var(--brand-primary, #ea580c);
  --refund-card: #ffffff;
  --refund-surface: #f5f5f5;
  --refund-surface-soft: #f3f3f3;
  --refund-border: #e2e2e2;
  --refund-text: #1a1c1c;
  --refund-muted: #4c4546;
  --refund-shadow: rgba(15, 23, 42, 0.05);
}

.refund-page__content {
  padding: 28rpx 24rpx 210rpx;
  box-sizing: border-box;
}

.product-card,
.config-card,
.form-card,
.upload-card {
  background: var(--refund-card);
  border: 1px solid #eeeeee;
  border-radius: 20rpx;
  box-shadow: 0 4rpx 14rpx var(--refund-shadow);
  box-sizing: border-box;
}

.product-card {
  display: flex;
  padding: 28rpx;
}

.product-card__image {
  width: 160rpx;
  height: 160rpx;
  border-radius: 8rpx;
  background: var(--refund-surface-soft);
  flex-shrink: 0;
}

.product-card__body {
  flex: 1;
  min-width: 0;
  margin-left: 24rpx;
}

.product-card__title {
  display: block;
  overflow: hidden;
  max-width: 100%;
  font-size: 30rpx;
  line-height: 40rpx;
  font-weight: 700;
  color: #000000;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.product-card__sku {
  display: block;
  overflow: hidden;
  margin-top: 12rpx;
  font-size: 24rpx;
  line-height: 34rpx;
  color: #6b6670;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.product-card__footer {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  margin-top: 32rpx;
}

.product-card__count {
  font-size: 22rpx;
  line-height: 30rpx;
  color: #4c4546;
}

.product-card__price {
  font-size: 34rpx;
  line-height: 40rpx;
  font-weight: 800;
  color: #000000;
}

.config-card,
.form-card,
.upload-card {
  margin-top: 18rpx;
}

.config-card {
  padding: 8rpx 28rpx;
}

.config-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 106rpx;
}

.config-row--tap {
  min-height: 90rpx;
}

.config-row--hover {
  opacity: 0.78;
}

.config-row__label,
.form-card__title,
.upload-card__title {
  font-size: 28rpx;
  line-height: 40rpx;
  font-weight: 700;
  color: #000000;
}

.config-row__value-wrap {
  display: flex;
  align-items: center;
  min-width: 0;
}

.config-row__value {
  max-width: 320rpx;
  overflow: hidden;
  margin-right: 14rpx;
  font-size: 28rpx;
  line-height: 38rpx;
  color: #4c4546;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.config-row__value--selected {
  color: #1a1c1c;
}

.config-row__amount {
  font-size: 34rpx;
  line-height: 42rpx;
  font-weight: 800;
  color: var(--refund-orange-strong);
}

.quantity-stepper {
  display: flex;
  align-items: center;
}

.quantity-stepper__button {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 58rpx;
  height: 58rpx;
}

.quantity-stepper__button--hover {
  opacity: 0.72;
}

.quantity-stepper__value {
  width: 74rpx;
  font-size: 28rpx;
  line-height: 58rpx;
  font-weight: 700;
  color: #000000;
  text-align: center;
}

.form-card {
  padding: 30rpx 28rpx 40rpx;
}

.refund-textarea {
  width: 100%;
  height: 214rpx;
  margin-top: 28rpx;
  padding: 24rpx;
  border-radius: 12rpx;
  background: #f5f5f5;
  box-sizing: border-box;
  font-size: 26rpx;
  line-height: 38rpx;
  color: #1a1c1c;
}

.refund-textarea__placeholder {
  color: #6b7280;
}

.upload-card {
  padding: 30rpx 28rpx;
}

.upload-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.upload-card__limit {
  font-size: 24rpx;
  line-height: 34rpx;
  color: #4c4546;
}

.proof-list {
  display: flex;
  flex-wrap: wrap;
  margin-top: 28rpx;
}

.proof-upload,
.proof-item {
  width: 196rpx;
  height: 196rpx;
  margin-right: 22rpx;
  margin-bottom: 22rpx;
  border-radius: 12rpx;
  box-sizing: border-box;
}

.proof-upload {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: #f3f3f3;
}

.proof-upload--hover {
  opacity: 0.78;
}

.proof-upload__icon {
  font-size: 58rpx;
  line-height: 1;
  color: #5f6368;
}

.proof-upload__text {
  margin-top: 12rpx;
  font-size: 24rpx;
  line-height: 32rpx;
  color: #5f6368;
}

.proof-item {
  position: relative;
  overflow: hidden;
  background: #eeeeee;
}

.proof-item__image {
  width: 196rpx;
  height: 196rpx;
  display: block;
}

.proof-item__remove {
  position: absolute;
  top: 8rpx;
  right: 8rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40rpx;
  height: 40rpx;
  border-radius: 20rpx;
  background: rgba(0, 0, 0, 0.62);
}

.proof-item__remove--hover {
  opacity: 0.76;
}

.refund-footer {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  padding: 30rpx 28rpx 28rpx;
  background: rgba(255, 255, 255, 0.94);
  box-sizing: border-box;
  z-index: 20;
}

.refund-footer__button {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 86rpx;
  margin: 0;
  border-radius: 20rpx;
  background: var(--refund-orange-strong);
  box-shadow: 0 10rpx 20rpx rgba(255, 69, 0, 0.2);
  font-size: 28rpx;
  line-height: 40rpx;
  font-weight: 700;
  color: #ffffff;
}

.refund-footer__button::after {
  border: 0;
}

.refund-footer__button[disabled] {
  background: #c3c7cc;
  color: #ffffff;
}

.reason-mask {
  position: fixed;
  left: 0;
  right: 0;
  top: 0;
  bottom: 0;
  z-index: 30;
  display: flex;
  align-items: flex-end;
  background: rgba(0, 0, 0, 0.38);
}

.reason-sheet {
  width: 100%;
  padding: 30rpx 28rpx 34rpx;
  border-radius: 32rpx 32rpx 0 0;
  background: #ffffff;
  box-sizing: border-box;
}

.reason-sheet__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.reason-sheet__title {
  font-size: 32rpx;
  line-height: 44rpx;
  font-weight: 700;
  color: #000000;
}

.reason-sheet__close {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 52rpx;
  height: 52rpx;
}

.reason-sheet__close--hover {
  opacity: 0.7;
}

.reason-sheet__list {
  margin-top: 20rpx;
}

.reason-option {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 88rpx;
}

.reason-option--hover {
  opacity: 0.78;
}

.reason-option__text {
  font-size: 28rpx;
  line-height: 40rpx;
  color: #1a1c1c;
}

.reason-option__check {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 38rpx;
  height: 38rpx;
  border-radius: 19rpx;
  background: var(--refund-orange-strong);
}

/* #ifdef H5 */
.refund-page__content {
  padding-bottom: calc(210rpx + var(--window-bottom, 0px));
}

.refund-footer {
  bottom: var(--window-bottom, 0px);
  padding-bottom: calc(28rpx + var(--window-bottom, 0px));
}
/* #endif */

/* #ifdef MP-WEIXIN */
.refund-page__content {
  padding-bottom: calc(210rpx + env(safe-area-inset-bottom));
}

.refund-footer {
  padding-bottom: calc(28rpx + env(safe-area-inset-bottom));
}
/* #endif */
</style>
