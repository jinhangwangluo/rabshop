<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="order-detail-page">
    <image class="order-detail-page__hero-bg" src="./../static/order-detail-hero-bg.png" mode="aspectFill"></image>
    <view class="order-detail-page__hero-mask"></view>
    <view class="order-detail-page__safe-top">
      <app-safe-top title="退款详情" background="transparent" title-color="#171717" icon-color="#171717" />
    </view>

    <scroll-view
      scroll-y
      class="order-detail-scroll"
      refresher-enabled
      :refresher-triggered="refreshing"
      @refresherrefresh="handleRefresh"
    >
      <view class="order-detail-content">
        <view v-if="loading && !detailReady" class="page-loading">
          <six-petal-spiral />
        </view>

        <view v-else-if="loadError && !detailReady" class="page-error">
          <text class="page-error__title">退款详情加载失败</text>
          <text class="page-error__desc">{{ loadError }}</text>
          <view class="page-error__btn" @click="handleRefresh">重新加载</view>
        </view>

        <block v-else-if="detailReady">
          <view class="status-card">
            <view class="status-card__main">
              <text class="status-card__title">{{ detail.displayStatusText }}</text>
              <text
                v-for="line in statusHeroDescriptions"
                :key="line"
                class="status-card__desc"
              >
                {{ line }}
              </text>
            </view>
            <image class="status-card__illustration" :src="statusHeroImage" mode="aspectFit"></image>
            <view class="status-card__progress">
              <text class="iconfont icon-refund status-card__progress-icon"></text>
              <text class="status-card__progress-text">{{ latestRefundText }}</text>
            </view>
          </view>

          <view class="goods-card">
            <view class="goods-card__header">
              <view class="goods-card__shop-wrap" @click.stop="handleGoShop(detail.merchantId)">
                <text class="iconfont icon-shop goods-card__shop-icon"></text>
                <text class="goods-card__shop-name">{{ detail.merchantName }}</text>
              </view>
            </view>

            <view class="goods-list">
              <view
                v-for="(goods, index) in detail.goodsList"
                :key="goods.renderKey"
                class="goods-item"
                hover-class="goods-item--hover"
                @click="handleGoodsClick(goods, detail.orderType)"
              >
                <view class="goods-item__image-wrap">
                  <image
                    v-if="goods.image"
                    class="goods-item__image"
                    :src="goods.image"
                    mode="aspectFill"
                  ></image>
                  <view v-else class="goods-item__image-placeholder">
                    <text class="iconfont icon-pic goods-item__image-placeholder-icon"></text>
                  </view>
                  <view class="goods-item__type-badge">
                    <text class="goods-item__type-text">{{ goodsTypeLabel }}</text>
                  </view>
                </view>

                <view class="goods-item__body">
                  <text class="goods-item__title">{{ goods.name }}</text>
                  <text v-if="goods.subtitle" class="goods-item__subtitle">{{ goods.subtitle }}</text>
                  <text v-if="goods.skuText" class="goods-item__sku">{{ goods.skuText }}</text>
                  <view class="goods-item__meta">
                    <text class="goods-item__price">¥ {{ formatAmount(goods.price) }}</text>
                    <text class="goods-item__qty">x {{ goods.quantity }}</text>
                  </view>
                </view>
              </view>
            </view>
          </view>

          <view class="fee-card">
            <view
              v-for="(item, index) in feeRows"
              :key="item.label"
              class="fee-row"
              :class="{ 'fee-row--last': index === feeRows.length - 1 }"
            >
              <text :class="['fee-row__label', { 'fee-row__label--total': item.isTotal }]">{{ item.label }}</text>
              <text
                :class="[
                  'fee-row__value',
                  {
                    'fee-row__value--highlight': item.highlight,
                    'fee-row__value--total': item.isTotal
                  }
                ]"
              >
                {{ item.value }}
              </text>
            </view>
          </view>

          <view class="info-card">
            <view class="info-row">
              <text class="info-row__label">退款编号</text>
              <view class="info-row__value-wrap">
                <text class="info-row__value">{{ detail.refundSnText }}</text>
                <view v-if="detail.refundSn" class="info-row__copy" @click="handleCopy(detail.refundSn, '退款编号已复制')">复制</view>
              </view>
            </view>
            <view class="info-row">
              <text class="info-row__label">订单编号</text>
              <view class="info-row__value-wrap">
                <text class="info-row__value">{{ detail.orderSn }}</text>
                <view class="info-row__copy" @click="handleCopy(detail.orderSn, '订单编号已复制')">复制</view>
              </view>
            </view>
            <view class="info-row">
              <text class="info-row__label">申请时间</text>
              <text class="info-row__value">{{ detail.applyTimeText }}</text>
            </view>
            <view class="info-row">
              <text class="info-row__label">退款原因</text>
              <text class="info-row__value">{{ detail.reasonText }}</text>
            </view>
            <view class="info-row">
              <text class="info-row__label">退款方式</text>
              <text class="info-row__value">{{ detail.refundMethodText }}</text>
            </view>
          </view>
        </block>
      </view>
    </scroll-view>

    <view v-if="detailReady" class="footer-bar">
      <view class="footer-bar__inner">
        <view
          v-for="action in footerActions"
          :key="action.key"
          :class="['footer-action', { 'footer-action--primary': action.primary }]"
          @click="handleFooterAction(action)"
        >
          <text :class="['iconfont', action.icon, 'footer-action__icon']"></text>
          <text class="footer-action__text">{{ action.text }}</text>
        </view>
      </view>
    </view>
  </view>
</template>

<script setup>
import { computed, ref } from 'vue'
import { onLoad, onPullDownRefresh } from '@dcloudio/uni-app'
import { cancelRefundOrder, getOrderRefundDetail } from '@/api/order'
import { resolveAssetUrl } from '@/utils/runtime_config'
import { navigateToMerchantHome } from '@/utils/merchant'
import { navigateToOrderGoods } from '@/utils/order_goods'
import statusRefundingImage from '../static/order-detail-status-refunding.png'
import statusRefundedImage from '../static/order-detail-status-refunded.png'
import statusCancelledImage from '../static/order-detail-status-cancelled.png'
import { themeState } from '@/utils/theme'

const STATUS_HERO_IMAGE_MAP = Object.freeze({
  refunding: statusRefundingImage,
  partial: statusRefundedImage,
  refunded: statusRefundedImage,
  rejected: statusCancelledImage,
  cancelled: statusCancelledImage
})

defineOptions({ name: 'OrderRefundDetailPage' })

const loading = ref(false)
const refreshing = ref(false)
const actionLoading = ref(false)
const loadError = ref('')
const refundSn = ref('')
const orderSn = ref('')
const orderId = ref(0)
const detail = ref(null)

const detailReady = computed(() => {
  return !!(detail.value && Object.keys(detail.value).length)
})
const goodsTypeLabel = computed(() => {
  if (!detail.value) {
    return ''
  }

  const map = { 1: '内容', 2: '商品', 3: '专题' }
  return map[detail.value.orderType] || '商品'
})
const statusDescription = computed(() => {
  if (!detail.value) {
    return ''
  }

  const descriptionMap = {
    refunding: '退款申请已提交，请等待平台审核处理',
    partial: '该订单存在部分退款，请留意售后进度',
    refunded: '退款已完成，资金将按原路径退回',
    rejected: '退款申请未通过，如有疑问请联系客服',
    cancelled: '退款申请已取消，如有需要可重新申请'
  }

  return descriptionMap[detail.value.refundStatus] || '售后状态更新中'
})
const statusHeroDescriptions = computed(() => {
  if (!detail.value) {
    return []
  }

  const descriptionMap = {
    refunding: ['退款审核中', '请等待平台处理'],
    partial: ['订单已部分退款'],
    refunded: ['退款将原路退回'],
    rejected: ['可联系客服处理'],
    cancelled: ['申请已取消']
  }

  return descriptionMap[detail.value.refundStatus] || [statusDescription.value]
})
const statusHeroImage = computed(() => {
  if (!detail.value) {
    return STATUS_HERO_IMAGE_MAP.refunding
  }

  return STATUS_HERO_IMAGE_MAP[detail.value.refundStatus] || STATUS_HERO_IMAGE_MAP.refunding
})
const latestRefundText = computed(() => {
  if (!detail.value) {
    return ''
  }

  const textMap = {
    refunding: '最新售后进度：退款申请已提交，平台正在审核',
    partial: '最新售后进度：订单已完成部分退款',
    refunded: '最新售后进度：退款已完成，请留意资金到账',
    rejected: '最新售后进度：退款申请未通过',
    cancelled: '最新售后进度：退款申请已取消'
  }

  return textMap[detail.value.refundStatus] || '最新售后进度同步中'
})
const feeRows = computed(() => {
  if (!detail.value) {
    return []
  }

  return [
    {
      label: '实付金额',
      value: `¥ ${formatAmount(detail.value.payAmount)}`,
      highlight: false,
      isTotal: false
    },
    {
      label: '退款方式',
      value: detail.value.refundMethod || '--',
      highlight: false,
      isTotal: false
    },
    {
      label: '退款金额',
      value: `¥ ${formatAmount(detail.value.refundAmount)}`,
      highlight: true,
      isTotal: true
    }
  ]
})
const footerActions = computed(() => {
  if (!detail.value) {
    return []
  }

  const actions = [
    { key: 'service', text: '联系客服', icon: 'icon-comment', primary: false },
  ]

  if (detail.value.canCancelRefund) {
    actions.push({ key: 'cancel', text: '取消申请', icon: 'icon-roundclose', primary: true })
  }

  return actions
})

onLoad((options = {}) => {
  initQuery(options)
  fetchRefundDetail()
})

onPullDownRefresh(() => {
  handleRefresh()
})

/**
 * 解析页面入参。
 *
 * @param {Object} options 页面路由参数。
 * @returns {void}
 */
function initQuery(options = {}) {
  const parsedOrderId = Number(options.id || options.order_id || 0)
  refundSn.value = String(options.refund_sn || options.refundSn || '').trim()
  orderSn.value = String(options.order_sn || options.orderSn || '').trim()
  orderId.value = Number.isNaN(parsedOrderId) ? 0 : parsedOrderId
}

/**
 * 下拉刷新或主动重试。
 *
 * @returns {void}
 */
function handleRefresh() {
  refreshing.value = true
  fetchRefundDetail()
}

/**
 * 获取退款详情。
 *
 * @returns {Promise<void>}
 */
async function fetchRefundDetail() {
  if (loading.value) {
    refreshing.value = false
    uni.stopPullDownRefresh()
    return
  }

  const params = buildQueryParams()
  if (!params) {
    loadError.value = '缺少退款单号或订单号'
    refreshing.value = false
    uni.stopPullDownRefresh()
    return
  }

  loading.value = true
  loadError.value = ''
  try {
    const res = await getOrderRefundDetail(params)
    if (res.code === 200 && res.data) {
      detail.value = normalizeRefundDetail(res.data)
      return
    }

    loadError.value = res.msg || '获取退款详情失败'
  } catch (error) {
    console.error('获取退款详情失败', error)
    loadError.value = '获取退款详情失败，请稍后重试'
  } finally {
    loading.value = false
    refreshing.value = false
    uni.stopPullDownRefresh()
  }
}

/**
 * 构建详情查询参数。
 *
 * @returns {Object|null} 查询参数。
 */
function buildQueryParams() {
  if (refundSn.value) {
    return { refund_sn: refundSn.value }
  }
  if (orderSn.value) {
    return { order_sn: orderSn.value }
  }
  if (orderId.value > 0) {
    return { order_id: orderId.value }
  }

  return null
}

/**
 * 规范化退款详情数据。
 *
 * @param {Object} payload 接口返回退款详情。
 * @returns {Object} 页面渲染数据。
 */
function normalizeRefundDetail(payload = {}) {
  const goods = Array.isArray(payload.goods) ? payload.goods : []
  const orderType = Number(payload.order_type || 0)
  const goodsList = goods.map((item, index) => ({
    orderGoodsId: Number(item.order_goods_id || 0),
    goodsId: Number(item.goods_id || 0),
    orderType: Number(item.order_type || orderType || 0),
    goodsType: Number(item.goods_type || 0),
    contentType: Number(item.content_type || 0),
    detailUrl: String(item.detail_url || ''),
    renderKey: item.order_goods_id || `refund-goods-${index}`,
    name: String(item.goods_name || ''),
    image: resolveGoodsImage(item.goods_image),
    skuText: resolveGoodsSkuText(item),
    subtitle: String(item.subtitle || item.sub_title || item.description || '').trim(),
    quantity: Number(item.num || 0),
    price: Number(item.price || 0)
  }))

  const refundNo = String(payload.refund_sn || '')
  const applyTime = String(payload.apply_time || payload.refund_application_time || '')
  const reason = String(payload.reason || payload.refund_reason || '')
  const refundMethod = String(payload.refund_method || payload.pay_type_text || '原支付路径退回')

  return {
    refundSn: refundNo,
    refundSnText: refundNo || '--',
    orderSn: String(payload.order_sn || ''),
    merchantId: Number(payload.merchant_id || 0),
    merchantName: String(payload.merchant_name || '平台自营'),
    refundStatus: resolveRefundUiStatus(payload),
    displayStatusText: resolveRefundStatusText(payload),
    refundAmount: Number(payload.refund_amount || 0),
    payAmount: Number(payload.pay_amount || 0),
    refundMethod,
    refundMethodText: refundMethod || '--',
    applyTime,
    applyTimeText: applyTime || '--',
    reason,
    reasonText: reason || '--',
    orderType,
    canCancelRefund: Boolean(payload.can_cancel_refund),
    goodsList
  }
}

/**
 * 解析退款 UI 状态。
 *
 * @param {Object} payload 接口返回退款详情。
 * @returns {string} UI 状态。
 */
function resolveRefundUiStatus(payload = {}) {
  const refundStatus = Number(payload.refund_status || 0)
  const statusText = String(payload.refund_status_text || payload.refund_record_status_text || '')

  if (statusText.indexOf('取消') !== -1) {
    return 'cancelled'
  }
  if (statusText.indexOf('失败') !== -1 || statusText.indexOf('拒') !== -1) {
    return 'rejected'
  }
  if (refundStatus === 1 || payload.can_cancel_refund) {
    return 'refunding'
  }
  if (refundStatus === 3) {
    return 'partial'
  }
  if (refundStatus === 2) {
    return 'refunded'
  }

  return 'refunding'
}

/**
 * 解析退款状态文案。
 *
 * @param {Object} payload 接口返回退款详情。
 * @returns {string} 状态文案。
 */
function resolveRefundStatusText(payload = {}) {
  return String(payload.refund_status_text || payload.refund_record_status_text || '退款处理中')
}

/**
 * 解析商品规格文案。
 *
 * @param {Object} goods 商品数据。
 * @returns {string}
 */
function resolveGoodsSkuText(goods = {}) {
  const skuName = String(goods.sku_name || '').trim()
  return skuName ? `规格：${skuName}` : ''
}

/**
 * 解析商品图片地址。
 *
 * @param {string} image 商品图片。
 * @returns {string}
 */
function resolveGoodsImage(image = '') {
  const resolved = resolveAssetUrl(image)
  return resolved || ''
}

/**
 * 复制指定文本。
 *
 * @param {string} value 待复制文本。
 * @param {string} message 成功提示。
 * @returns {void}
 */
function handleCopy(value = '', message = '已复制') {
  const text = String(value || '').trim()
  if (!text) {
    return
  }

  uni.setClipboardData({
    data: text,
    success: () => {
      uni.showToast({ title: message, icon: 'none' })
    }
  })
}

/**
 * 处理底部按钮点击。
 *
 * @param {Object} action 按钮配置。
 * @returns {void}
 */
function handleFooterAction(action = {}) {
  if (action.key === 'cancel') {
    confirmCancelRefund()
    return
  }

  if (action.key === 'service') {
    uni.navigateTo({ url: '/pages/users/customer/index' })
    return
  }

  if (action.key === 'list') {
    uni.navigateTo({ url: '/pages/orders/refund/index' })
  }
}

/**
 * 进入商户首页。
 *
 * @param {number|string} merchantId 商户 ID。
 * @returns {void}
 */
function handleGoShop(merchantId) {
  if (Number(merchantId || 0) <= 0) {
    uni.switchTab({ url: '/pages/index/index' })
    return
  }

  navigateToMerchantHome(merchantId)
}

/**
 * 进入退款商品对应的内容或专题详情。
 *
 * @param {Object} goods 当前退款商品。
 * @param {number|string} fallbackOrderType 订单类型兜底值。
 * @returns {void}
 */
function handleGoodsClick(goods = {}, fallbackOrderType = 0) {
  navigateToOrderGoods(goods, { fallbackOrderType })
}

/**
 * 二次确认取消退款申请。
 *
 * @returns {void}
 */
function confirmCancelRefund() {
  uni.showModal({
    title: '取消退款申请',
    content: '确定要取消该订单的退款申请吗？',
    confirmColor: themeState.primary_color,
    success: async (res) => {
      if (!res.confirm) {
        return
      }

      await cancelRefund()
    }
  })
}

/**
 * 取消退款申请。
 *
 * @returns {Promise<void>}
 */
async function cancelRefund() {
  if (actionLoading.value || !detail.value) {
    return
  }

  const detailOrderSn = String(detail.value.orderSn || '').trim()
  const detailRefundSn = String(detail.value.refundSn || '').trim()
  if (!detailOrderSn && !detailRefundSn) {
    uni.showToast({ title: '订单号不能为空', icon: 'none' })
    return
  }

  actionLoading.value = true
  try {
    await cancelRefundOrder({
      order_sn: detailOrderSn,
      refund_sn: detailRefundSn
    })
    uni.showToast({ title: '已取消退款申请', icon: 'success' })
    await fetchRefundDetail()
  } catch (error) {
    console.error('取消退款申请失败', error)
  } finally {
    actionLoading.value = false
  }
}

/**
 * 金额格式化。
 *
 * @param {number|string} value 金额值。
 * @returns {string} 固定两位小数金额。
 */
function formatAmount(value) {
  const amount = Number(value || 0)
  return Number.isNaN(amount) ? '0.00' : amount.toFixed(2)
}
</script>


<style scoped lang="scss">
.order-detail-page {
  position: relative;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background: var(--order-page-bg, #f6f7fb);
}

.order-detail-page__hero-bg {
  position: absolute;
  left: 0;
  top: 0;
  z-index: 0;
  display: block;
  width: 100%;
  height: 720rpx;
}

.order-detail-page__hero-mask {
  position: absolute;
  left: 0;
  top: 520rpx;
  z-index: 0;
  width: 100%;
  height: 260rpx;
  background: linear-gradient(180deg, rgba(246, 247, 251, 0) 0%, rgba(246, 247, 251, 0.72) 58%, #f6f7fb 100%);
}

.order-detail-page__safe-top {
  position: relative;
  z-index: 2;
}

.order-detail-scroll {
  position: relative;
  z-index: 1;
  flex: 1;
  min-height: 0;
}

.order-detail-content {
  position: relative;
  z-index: 1;
  padding: 0 28rpx 44rpx;
  padding-bottom: calc(180rpx + env(safe-area-inset-bottom));
  box-sizing: border-box;
}

/* #ifdef H5 */
.order-detail-content {
  padding-bottom: calc(180rpx + var(--window-bottom, 0));
}
/* #endif */

.page-loading,
.page-error {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding-top: 160rpx;
}

.page-error {
  &__image {
    width: 220rpx;
    height: 220rpx;
  }

  &__title {
    margin-top: 24rpx;
    font-size: 30rpx;
    font-weight: 700;
    color: var(--text-title, #1a1c1c);
  }

  &__desc {
    margin-top: 14rpx;
    font-size: 24rpx;
    line-height: 36rpx;
    color: var(--text-secondary, #8b8b97);
    text-align: center;
  }

  &__btn {
    margin-top: 32rpx;
    min-width: 220rpx;
    height: 80rpx;
    padding: 0 32rpx;
    border-radius: 999rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--brand-primary, #ea580c);
    color: #ffffff;
    font-size: 28rpx;
    font-weight: 700;
  }
}

.status-card {
  position: relative;
  min-height: 344rpx;
  padding: 48rpx 20rpx 72rpx;
  overflow: visible;
  box-sizing: border-box;

  &__main {
    position: relative;
    z-index: 2;
    flex: 1;
    min-width: 0;
    width: 56%;
  }

  &__title {
    display: block;
    font-size: 56rpx;
    line-height: 70rpx;
    font-weight: 800;
    color: #242a31;
  }

  &__desc {
    display: block;
    margin-top: 14rpx;
    font-size: 28rpx;
    line-height: 42rpx;
    color: #30343a;
  }

  &__illustration {
    position: absolute;
    right: -42rpx;
    top: -8rpx;
    z-index: 1;
    width: 390rpx;
    height: 330rpx;
  }

  &__progress {
    position: absolute;
    left: 20rpx;
    right: 20rpx;
    bottom: 0;
    z-index: 2;
    height: 64rpx;
    padding: 0 24rpx;
    border-radius: 999rpx;
    background: rgba(255, 255, 255, 0.58);
    display: flex;
    align-items: center;
    box-sizing: border-box;
  }

  &__progress-icon {
    flex-shrink: 0;
    margin-right: 14rpx;
    font-size: 24rpx;
    color: var(--brand-primary, #ea580c);
  }

  &__progress-text {
    flex: 1;
    min-width: 0;
    font-size: 24rpx;
    line-height: 34rpx;
    color: #4b5563;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
}

.address-card,
.goods-card,
.fee-card,
.info-card {
  margin-top: 24rpx;
  border-radius: 20rpx;
  background: #ffffff;
  box-shadow: 0 12rpx 28rpx rgba(17, 24, 39, 0.05);
}

.address-card {
  padding: 24rpx;
  display: flex;
  align-items: flex-start;

  &__icon-wrap {
    width: 72rpx;
    height: 72rpx;
    border-radius: 50%;
    background: #f3f3f4;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  &__icon {
    font-size: 32rpx;
    color: #6b6b73;
  }

  &__body {
    flex: 1;
    min-width: 0;
    margin-left: 20rpx;
  }

  &__contact {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
  }

  &__name {
    font-size: 32rpx;
    font-weight: 700;
    color: var(--text-title, #1a1c1c);
    line-height: 44rpx;
  }

  &__phone {
    margin-left: 16rpx;
    font-size: 30rpx;
    color: #6b6f76;
    line-height: 44rpx;
  }

  &__text {
    display: block;
    margin-top: 12rpx;
    font-size: 28rpx;
    line-height: 42rpx;
    color: #4c4546;
  }
}

.goods-card {
  overflow: hidden;

  &__header {
    padding: 22rpx 24rpx;
    border-bottom: 1rpx solid #f1f1f2;
  }

  &__shop-wrap {
    display: flex;
    align-items: center;
  }

  &__shop-icon {
    font-size: 26rpx;
    color: #9797a0;
  }

  &__shop-name {
    margin-left: 12rpx;
    font-size: 30rpx;
    font-weight: 700;
    color: var(--text-title, #1a1c1c);
    line-height: 42rpx;
  }
}

.goods-list {
  padding: 0 24rpx;
}

.goods-item {
  padding: 28rpx 0;
  display: flex;
  align-items: flex-start;

  &--hover {
    opacity: 0.86;
  }

  & + & {
    border-top: 1rpx solid #f3f3f4;
  }

  &__image-wrap {
    position: relative;
    width: 168rpx;
    height: 168rpx;
    border-radius: 16rpx;
    overflow: hidden;
    background: linear-gradient(135deg, #020202 0%, #191919 100%);
    border: 1rpx solid #f1f1f2;
    flex-shrink: 0;
  }

  &__image,
  &__image-placeholder {
    width: 100%;
    height: 100%;
  }

  &__image-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #0f1118 0%, #272a34 100%);
  }

  &__image-placeholder-icon {
    font-size: 44rpx;
    color: rgba(255, 255, 255, 0.58);
  }

  &__type-badge {
    position: absolute;
    top: 0;
    left: 0;
    max-width: 120rpx;
    padding: 6rpx 12rpx;
    border-radius: 16rpx 0 16rpx 0;
    background: var(--brand-primary, #ea580c);
  }

  &__type-text {
    display: block;
    max-width: 96rpx;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    font-size: 20rpx;
    line-height: 1.3;
    color: #ffffff;
  }

  &__body {
    flex: 1;
    min-width: 0;
    margin-left: 20rpx;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 168rpx;
  }

  &__title {
    display: -webkit-box;
    font-size: 36rpx;
    line-height: 48rpx;
    font-weight: 700;
    color: var(--text-title, #1a1c1c);
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    overflow: hidden;
  }

  &__subtitle {
    display: block;
    margin-top: 8rpx;
    max-width: 100%;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    font-size: 22rpx;
    line-height: 32rpx;
    color: #71717a;
  }

  &__sku {
    display: block;
    margin-top: 8rpx;
    font-size: 24rpx;
    line-height: 34rpx;
    color: var(--text-secondary, #8b8b97);
  }

  &__meta {
    margin-top: 20rpx;
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
  }

  &__price {
    font-size: 28rpx;
    line-height: 1.1;
    font-weight: 700;
    color: var(--text-title, #1a1c1c);
  }

  &__qty {
    font-size: 24rpx;
    line-height: 32rpx;
    color: #a3a3ab;
  }
}

.fee-card,
.info-card {
  padding: 12rpx 24rpx;
}

.fee-row,
.info-row {
  min-height: 74rpx;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.fee-row {
  &--last {
    min-height: 92rpx;
    margin-top: 12rpx;
    padding-top: 18rpx;
    border-top: 1rpx solid #f1f1f2;
  }

  &__label {
    font-size: 28rpx;
    line-height: 40rpx;
    color: #6b6f76;

    &--total {
      font-weight: 700;
      color: var(--text-title, #1a1c1c);
    }
  }

  &__value {
    font-size: 28rpx;
    line-height: 40rpx;
    font-weight: 600;
    color: var(--text-title, #1a1c1c);

    &--highlight {
      color: var(--brand-primary, #ea580c);
    }

    &--total {
      font-size: 48rpx;
      line-height: 1;
      font-weight: 700;
    }
  }
}

.info-row {
  &__label {
    font-size: 26rpx;
    color: #b1b1ba;
    line-height: 38rpx;
  }

  &__value-wrap {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex: 1;
    min-width: 0;
    margin-left: 24rpx;
  }

  &__value {
    font-size: 26rpx;
    line-height: 38rpx;
    color: #5a5d64;
    text-align: right;
  }

  &__copy {
    margin-left: 14rpx;
    font-size: 26rpx;
    line-height: 38rpx;
    color: var(--brand-primary, #ea580c);
    flex-shrink: 0;
  }
}

.footer-bar {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 20;
  padding: 16rpx 24rpx;
  padding-bottom: calc(16rpx + env(safe-area-inset-bottom));
  background: rgba(255, 255, 255, 0.96);
  border-top: 1rpx solid #ececee;
  box-shadow: 0 -4rpx 12rpx rgba(0, 0, 0, 0.04);

  &__inner {
    display: flex;
    align-items: center;
    justify-content: flex-end;
  }
}

/* #ifdef H5 */
.footer-bar {
  bottom: var(--window-bottom, 0);
}
/* #endif */

.footer-action {
  height: 60rpx;
  padding: 0 28rpx;
  border-radius: 999rpx;
  border: 1rpx solid #e3e3e6;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #ffffff;
  flex-shrink: 0;

  & + & {
    margin-left: 16rpx;
  }

  &--primary {
    background: var(--brand-primary, #ea580c);
    border-color: var(--brand-primary, #ea580c);
    box-shadow: 0 10rpx 22rpx rgba(233, 89, 15, 0.18);

    .footer-action__icon,
    .footer-action__text {
      color: #ffffff;
    }
  }

  &__icon {
    font-size: 24rpx;
    color: #5c6067;
  }

  &__text {
    margin-left: 10rpx;
    font-size: 24rpx;
    line-height: 34rpx;
    font-weight: 600;
    color: #5c6067;
    white-space: nowrap;
  }
}
</style>
