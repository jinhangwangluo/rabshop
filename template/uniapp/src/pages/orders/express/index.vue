<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="express-page">
    <view :class="['express-hero', { 'express-hero--empty': !detailReady }]">
      <app-safe-top
        title="物流详情"
        background="transparent"
        title-color="#171717"
        icon-color="#171717"
        show-back
        emit-back
        @back="handleBack"
      />

      <view v-if="detailReady" class="express-hero__content">
        <view class="express-hero__copy">
          <text class="express-hero__title">{{ detail.statusText }}</text>
          <text v-if="detail.orderSn" class="express-hero__desc">订单号 {{ detail.orderSn }}</text>
        </view>
        <image class="express-hero__image" :src="heroImage" mode="aspectFit"></image>
      </view>
    </view>

    <view v-if="loading && !detailReady" class="page-state">
      <six-petal-spiral />
    </view>

    <view v-else-if="loadError && !detailReady" class="page-state page-state--error">
      <image class="page-state__image" :src="errorImage" mode="aspectFit"></image>
      <text class="page-state__title">物流详情加载失败</text>
      <text class="page-state__desc">{{ loadError }}</text>
      <view class="page-state__button" hover-class="page-state__button--hover" @click="loadExpressDetail">
        重新加载
      </view>
    </view>

    <view v-else-if="detailReady" class="express-content">
      <view class="express-card carrier-card">
        <view class="card-icon card-icon--carrier">
          <uni-icons type="gift" size="22" color="#666666"></uni-icons>
        </view>
        <view class="carrier-card__body">
          <view class="carrier-card__main">
            <text class="carrier-card__name">{{ detail.expressName }}</text>
            <text class="carrier-card__number">{{ detail.expressNo }}</text>
          </view>
          <view v-if="detail.officialPhone" class="carrier-card__phone-row">
            <text class="carrier-card__phone-label">官方电话</text>
            <text class="carrier-card__phone">{{ detail.officialPhone }}</text>
          </view>
        </view>
        <view class="carrier-card__copy" hover-class="carrier-card__copy--hover" @click="handleCopyExpressNo">
          复制
        </view>
      </view>

      <view class="express-card address-card">
        <view class="card-icon">
          <uni-icons type="location-filled" size="22" color="#6d6d72"></uni-icons>
        </view>
        <view class="address-card__body">
          <text class="address-card__title">收货地址</text>
          <view class="address-card__contact">
            <text class="address-card__name">{{ detail.receiver.name }}</text>
            <text class="address-card__phone">{{ detail.receiver.phone }}</text>
          </view>
          <text class="address-card__address">{{ detail.receiver.address }}</text>
        </view>
      </view>

      <view class="express-card trace-card">
        <view class="trace-card__header">
          <text class="trace-card__title">物流轨迹</text>
          <view class="trace-card__toggle" hover-class="trace-card__toggle--hover" @click="toggleTraces">
            <text class="trace-card__toggle-text">{{ tracesCollapsed ? '展开' : '收起' }}</text>
            <uni-icons :type="tracesCollapsed ? 'down' : 'up'" size="16" color="#3d3d42"></uni-icons>
          </view>
        </view>

        <view v-if="visibleTraces.length" class="trace-list">
          <view
            v-for="trace in visibleTraces"
            :key="trace.key"
            :class="['trace-item', { 'trace-item--current': trace.isCurrent }]"
          >
            <view class="trace-item__axis">
              <view class="trace-item__dot">
                <text v-if="trace.isCurrent" class="iconfont icon-send trace-item__icon"></text>
              </view>
              <view v-if="!trace.isLast" class="trace-item__line"></view>
            </view>
            <view class="trace-item__body">
              <view class="trace-item__heading">
                <text class="trace-item__status">{{ trace.statusText }}</text>
                <text class="trace-item__time">{{ trace.displayTime }}</text>
              </view>
              <text class="trace-item__content">{{ trace.content }}</text>
              <text v-if="trace.location" class="trace-item__location">{{ trace.location }}</text>
            </view>
          </view>
        </view>

        <view v-else class="trace-empty">
          <text class="trace-empty__text">暂无物流轨迹</text>
        </view>
      </view>
    </view>

    <view v-if="detailReady" class="express-footer">
      <view class="express-footer__inner">
        <view class="footer-button" hover-class="footer-button--hover" @click="handleCustomerService">
          <uni-icons type="headphones" size="20" color="#4f4f54"></uni-icons>
          <text class="footer-button__text">联系客服</text>
        </view>
        <view class="footer-button" hover-class="footer-button--hover" @click="handleViewOrder">
          <uni-icons type="shop" size="20" color="#4f4f54"></uni-icons>
          <text class="footer-button__text">查看订单</text>
        </view>
      </view>
    </view>
  </view>
</template>

<script setup>
import { computed, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getOrderExpress } from '@/api/order'
import { getStatusImageUrl } from '@/utils/status_images'
import heroImage from '../static/express-hero-illustration.png'

defineOptions({
  name: 'OrderExpressPage'
})

const loading = ref(false)
const loadError = ref('')
const orderId = ref(0)
const orderSn = ref('')
const detail = ref(null)
const tracesCollapsed = ref(false)
const errorImage = ref(getStatusImageUrl('no_order.png'))

const detailReady = computed(() => Boolean(detail.value))
const visibleTraces = computed(() => {
  if (!detail.value || !Array.isArray(detail.value.traces)) {
    return []
  }
  return tracesCollapsed.value ? detail.value.traces.slice(0, 1) : detail.value.traces
})

onLoad((options = {}) => {
  orderId.value = Number(options.order_id || options.orderId || options.id || 0)
  orderSn.value = String(options.order_sn || options.orderSn || '').trim()
  if (!orderId.value && !orderSn.value) {
    loadError.value = '缺少订单参数，无法查询物流'
    return
  }
  loadExpressDetail()
})

/**
 * 加载订单物流详情。
 *
 * @returns {Promise<void>}
 */
async function loadExpressDetail() {
  if (loading.value) {
    return
  }

  loading.value = true
  loadError.value = ''
  try {
    const params = orderId.value > 0 ? { order_id: orderId.value } : { order_sn: orderSn.value }
    const response = await getOrderExpress(params)
    if (response.code !== 200 || !response.data) {
      loadError.value = response.msg || '获取物流详情失败'
      return
    }

    detail.value = normalizeExpressDetail(response.data)
  } catch (error) {
    console.error('获取物流详情失败', error)
    loadError.value = '获取物流详情失败，请稍后重试'
  } finally {
    loading.value = false
  }
}

/**
 * 标准化物流详情接口数据。
 *
 * @param {Object} payload 接口返回数据。
 * @returns {Object} 页面物流详情。
 */
function normalizeExpressDetail(payload = {}) {
  const rawTraces = Array.isArray(payload.traces) ? payload.traces : []
  const traces = rawTraces.map((item, index) => ({
    key: String(item.row_key || `trace-${index}`),
    statusText: String(item.status_text || '物流更新'),
    displayTime: formatTraceTime(item.time),
    content: String(item.content || '物流信息已更新'),
    location: String(item.location || ''),
    isCurrent: Boolean(item.is_current || index === 0),
    isLast: index === rawTraces.length - 1
  }))
  const receiver = payload.receiver && typeof payload.receiver === 'object' ? payload.receiver : {}

  return {
    orderId: Number(payload.order_id || orderId.value || 0),
    orderSn: String(payload.order_sn || orderSn.value || ''),
    status: String(payload.status || ''),
    statusText: String(payload.status_text || '物流更新中'),
    expressName: String(payload.express_name || '快递公司'),
    expressNo: String(payload.express_no || ''),
    expressCode: String(payload.express_code || ''),
    officialPhone: String(payload.official_phone || ''),
    receiver: {
      name: String(receiver.name || '收货人'),
      phone: String(receiver.phone || ''),
      address: String(receiver.address || '暂无收货地址')
    },
    traces
  }
}

/**
 * 格式化轨迹时间。
 *
 * @param {string|number} value 原始时间。
 * @returns {string} 页面时间文本。
 */
function formatTraceTime(value) {
  const text = String(value || '').trim()
  if (!text) {
    return '--'
  }
  return text.replace(/\//g, '-').slice(0, 16)
}

/** 切换轨迹展开状态。 */
function toggleTraces() {
  tracesCollapsed.value = !tracesCollapsed.value
}

/** 复制快递单号。 */
function handleCopyExpressNo() {
  if (!detail.value || !detail.value.expressNo) {
    uni.showToast({ title: '暂无快递单号', icon: 'none' })
    return
  }
  uni.setClipboardData({ data: detail.value.expressNo })
}

/** 返回上一页，无历史页面时回到订单列表。 */
function handleBack() {
  const pages = getCurrentPages()
  if (Array.isArray(pages) && pages.length > 1) {
    uni.navigateBack()
    return
  }
  uni.reLaunch({ url: '/pages/orders/order/list/index' })
}

/** 进入在线客服。 */
function handleCustomerService() {
  uni.navigateTo({ url: '/pages/users/customer/index' })
}

/** 返回当前订单详情。 */
function handleViewOrder() {
  if (!detail.value) {
    return
  }
  const query = detail.value.orderId > 0
    ? `id=${detail.value.orderId}`
    : `order_sn=${encodeURIComponent(detail.value.orderSn)}`
  uni.navigateTo({ url: `/pages/orders/order/detail/index?${query}` })
}
</script>

<style lang="scss" scoped>
.express-page {
  position: relative;
  min-height: 100vh;
  padding-bottom: 152rpx;
  box-sizing: border-box;
  background: var(--surface-page, #f7f7f8);
  color: var(--text-primary, #18181b);
}

.express-hero {
  position: relative;
  overflow: hidden;
  background: #fff8f1;
  padding-bottom: 40rpx;
}

.express-hero--empty {
  min-height: 112rpx;
}

.express-hero__content {
  position: relative;
  padding: 52rpx 48rpx 22rpx;
  box-sizing: border-box;
}

.express-hero__copy {
  position: relative;
  z-index: 2;
  width: 48%;
  display: flex;
  flex-direction: column;
}

.express-hero__title {
  font-size: 48rpx;
  line-height: 60rpx;
  font-weight: 800;
  color: var(--text-title, #202124);
}

.express-hero__desc {
  margin-top: 16rpx;
  font-size: 24rpx;
  line-height: 34rpx;
  color: var(--text-secondary, #71717a);
}

.express-hero__image {
  position: absolute;
  right: 6rpx;
  bottom: 0;
  z-index: 1;
  width: 330rpx;
  height: 230rpx;
}

.express-content {
  position: relative;
  z-index: 3;
  margin-top: -30rpx;
  padding: 0 28rpx 36rpx;
}

.express-card {
  display: flex;
  box-sizing: border-box;
  background: var(--surface-card, #ffffff);
  border-radius: 24rpx;
  box-shadow: 0 8rpx 24rpx rgba(15, 23, 42, 0.04);
}

.express-card + .express-card {
  margin-top: 16rpx;
}

.card-icon {
  width: 64rpx;
  height: 64rpx;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  background: var(--surface-card-soft, #f4f4f5);
}

.card-icon--carrier {
  border-radius: 14rpx;
  background: var(--brand-primary-pale, #fff7ed);
}

.carrier-card {
  position: relative;
  min-height: 128rpx;
  padding: 24rpx 92rpx 24rpx 28rpx;
  align-items: center;
}

.carrier-card__body {
  min-width: 0;
  flex: 1;
  margin-left: 20rpx;
}

.carrier-card__main {
  display: flex;
  align-items: baseline;
  flex-wrap: wrap;
}

.carrier-card__name {
  margin-right: 18rpx;
  font-size: 28rpx;
  line-height: 42rpx;
  font-weight: 700;
  color: var(--text-title, #18181b);
}

.carrier-card__number {
  font-size: 28rpx;
  line-height: 42rpx;
  color: var(--text-regular, #3f3f46);
}

.carrier-card__phone-row {
  margin-top: 10rpx;
  display: flex;
  align-items: center;
}

.carrier-card__phone-label,
.carrier-card__phone {
  font-size: 24rpx;
  line-height: 34rpx;
}

.carrier-card__phone-label {
  color: var(--text-secondary, #71717a);
}

.carrier-card__phone {
  margin-left: 14rpx;
  color: var(--brand-primary, #ea580c);
}

.carrier-card__copy {
  position: absolute;
  top: 50%;
  right: 28rpx;
  min-width: 56rpx;
  height: 48rpx;
  margin-top: -24rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24rpx;
  color: var(--brand-primary, #ea580c);
}

.carrier-card__copy--hover {
  opacity: 0.7;
}

.address-card {
  min-height: 176rpx;
  padding: 30rpx 28rpx;
  align-items: flex-start;
}

.address-card__body {
  min-width: 0;
  margin-left: 20rpx;
  display: flex;
  flex-direction: column;
}

.address-card__title {
  font-size: 28rpx;
  line-height: 40rpx;
  font-weight: 700;
  color: var(--text-title, #18181b);
}

.address-card__contact {
  margin-top: 10rpx;
  display: flex;
  flex-wrap: wrap;
}

.address-card__name,
.address-card__phone,
.address-card__address {
  font-size: 24rpx;
  line-height: 36rpx;
  color: var(--text-regular, #3f3f46);
}

.address-card__phone {
  margin-left: 16rpx;
}

.address-card__address {
  margin-top: 4rpx;
}

.trace-card {
  min-height: 300rpx;
  padding: 28rpx;
  display: block;
}

.trace-card__header {
  height: 56rpx;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.trace-card__title {
  font-size: 28rpx;
  line-height: 40rpx;
  font-weight: 700;
  color: var(--text-title, #18181b);
}

.trace-card__toggle {
  min-width: 116rpx;
  height: 56rpx;
  padding: 0 18rpx;
  border-radius: 999rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  background: var(--surface-card-soft, #f4f4f5);
}

.trace-card__toggle--hover {
  opacity: 0.76;
}

.trace-card__toggle-text {
  margin-right: 8rpx;
  font-size: 24rpx;
  color: var(--text-regular, #3f3f46);
}

.trace-list {
  margin-top: 28rpx;
}

.trace-item {
  position: relative;
  display: flex;
  min-height: 150rpx;
}

.trace-item__axis {
  position: relative;
  width: 56rpx;
  flex-shrink: 0;
}

.trace-item__dot {
  position: relative;
  z-index: 2;
  width: 18rpx;
  height: 18rpx;
  margin: 10rpx auto 0;
  border-radius: 50%;
  background: #d4d4d8;
}

.trace-item__line {
  position: absolute;
  top: 28rpx;
  bottom: 0;
  left: 27rpx;
  width: 2rpx;
  background: #e4e4e7;
}

.trace-item__body {
  min-width: 0;
  flex: 1;
  padding: 0 0 28rpx 16rpx;
}

.trace-item__heading {
  display: flex;
  align-items: baseline;
  flex-wrap: wrap;
}

.trace-item__status {
  margin-right: 18rpx;
  font-size: 28rpx;
  line-height: 40rpx;
  font-weight: 700;
  color: var(--text-secondary, #71717a);
}

.trace-item__time,
.trace-item__location {
  font-size: 24rpx;
  line-height: 36rpx;
  color: var(--text-placeholder, #a1a1aa);
}

.trace-item__content {
  display: block;
  margin-top: 4rpx;
  font-size: 24rpx;
  line-height: 36rpx;
  color: var(--text-regular, #3f3f46);
}

.trace-item__location {
  display: block;
  margin-top: 2rpx;
}

.trace-item--current .trace-item__dot {
  width: 56rpx;
  height: 56rpx;
  margin-top: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--brand-primary, #ea580c);
  box-shadow: 0 0 0 10rpx var(--brand-primary-pale, #fff7ed);
}

.trace-item--current .trace-item__status {
  color: var(--brand-primary, #ea580c);
}

.trace-item__icon {
  font-size: 28rpx;
  color: #ffffff;
}

.trace-empty {
  min-height: 180rpx;
  display: flex;
  align-items: center;
  justify-content: center;
}

.trace-empty__text {
  font-size: 24rpx;
  color: var(--text-placeholder, #a1a1aa);
}

.page-state {
  min-height: 620rpx;
  padding: 120rpx 32rpx 48rpx;
  display: flex;
  flex-direction: column;
  align-items: center;
  box-sizing: border-box;
}

.page-state__image {
  width: 240rpx;
  height: 240rpx;
}

.page-state__title {
  margin-top: 24rpx;
  font-size: 30rpx;
  line-height: 42rpx;
  font-weight: 700;
}

.page-state__desc {
  margin-top: 12rpx;
  font-size: 24rpx;
  line-height: 36rpx;
  color: var(--text-secondary, #71717a);
  text-align: center;
}

.page-state__button {
  min-width: 220rpx;
  height: 72rpx;
  margin-top: 32rpx;
  padding: 0 28rpx;
  border-radius: 999rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  background: var(--brand-primary, #ea580c);
  color: #ffffff;
  font-size: 26rpx;
}

.page-state__button--hover,
.footer-button--hover {
  opacity: 0.82;
}

.express-footer {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 20;
  padding: 18rpx 28rpx;
  padding-bottom: calc(18rpx + env(safe-area-inset-bottom));
  background: rgba(255, 255, 255, 0.98);
  border-top: 1px solid var(--border-light, #f4f4f5);
  box-shadow: 0 -8rpx 24rpx rgba(0, 0, 0, 0.04);
}

.express-footer__inner {
  display: flex;
  align-items: center;
  justify-content: flex-end;
}

.footer-button {
  width: 220rpx;
  height: 72rpx;
  border: 1px solid var(--border-default, #e4e4e7);
  border-radius: 999rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  background: #ffffff;
}

.footer-button + .footer-button {
  margin-left: 18rpx;
}

.footer-button__text {
  margin-left: 12rpx;
  font-size: 26rpx;
  line-height: 36rpx;
  font-weight: 600;
  color: var(--text-regular, #3f3f46);
}

/* #ifdef H5 */
.express-page {
  padding-bottom: calc(152rpx + var(--window-bottom, 0));
}

.express-footer {
  bottom: var(--window-bottom, 0);
  padding-bottom: 18rpx;
}
/* #endif */
</style>
