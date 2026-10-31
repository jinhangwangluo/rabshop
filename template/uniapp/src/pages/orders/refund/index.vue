<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="refund-list-page">
    <app-safe-top mp-extra="0" background="#ffffff" title="退款列表" show-back />

    <scroll-view
      scroll-y
      class="refund-scroll"
      refresher-enabled
      :refresher-triggered="refreshing"
      @refresherrefresh="onPullDownRefresh"
      @scrolltolower="handleLoadMore"
    >
      <view class="refund-content">
        <scroll-view scroll-x class="tab-bar" :show-scrollbar="false">
          <view class="tab-bar__inner">
            <view
              v-for="(tab, index) in tabList"
              :key="tab.status"
              :class="['tab-item', { 'tab-item--active': activeTab === index }]"
              @click="handleSwitchTab(index)"
            >
              <text class="tab-item__text">{{ tab.name }}</text>
            </view>
          </view>
        </scroll-view>

        <view v-for="order in refundList" :key="order.render_key" class="order-card" @click="handleGoDetail(order)">
          <view class="order-card__header">
            <view class="order-card__header-left">
              <view class="order-card__shop" @click.stop="handleGoShop(order.merchant_id)">
                <uni-icons type="shop" size="16" color="#9ca3af"></uni-icons>
                <text class="order-card__shop-name">{{ order.merchant_name }}</text>
                <uni-icons v-if="order.merchant_id > 0" type="right" size="12" color="#9ca3af"></uni-icons>
              </view>
              <text v-if="order.order_sn" class="order-card__sn">订单号：{{ order.order_sn }}</text>
            </view>
            <text :class="['order-card__status', { 'order-card__status--active': order.can_cancel_refund }]">
              {{ order.refund_display_text }}
            </text>
          </view>

          <block v-if="order.goods_count > 1">
            <view class="order-card__goods-img-wrap">
              <text class="order-card__type-tag">{{ order.order_type_text }}</text>
              <scroll-view scroll-x class="order-card__goods-multi" :show-scrollbar="false">
                <view class="order-card__goods-multi-inner">
                  <view
                    v-for="(goods, goodsIndex) in order.goods_preview"
                    :key="goods.order_goods_id || goods.goods_id || goodsIndex"
                    class="order-card__goods-img-touch"
                  >
                    <image
                      class="order-card__goods-img-sm"
                      :src="goods.goods_image"
                      mode="aspectFill"
                    ></image>
                  </view>
                </view>
              </scroll-view>
            </view>
          </block>
          <block v-else>
            <view class="order-card__goods-single">
              <view class="order-card__goods-img-wrap">
                <text class="order-card__type-tag">{{ order.order_type_text }}</text>
                <image class="order-card__goods-img" :src="order.first_goods.goods_image" mode="aspectFill"></image>
              </view>
              <view class="order-card__goods-info">
                <text class="order-card__goods-title">{{ order.first_goods.goods_name }}</text>
                <text v-if="order.first_goods.sku_name" class="order-card__goods-sku">{{ order.first_goods.sku_name }}</text>
                <view class="order-card__goods-bottom">
                  <text class="order-card__price">¥{{ order.first_goods.price_text }}</text>
                  <text class="order-card__qty">x{{ order.first_goods.num }}</text>
                </view>
              </view>
            </view>
          </block>

          <view class="order-card__summary">
            <text class="order-card__summary-label">共{{ order.goods_total_num }}件商品</text>
            <text class="order-card__summary-pay">退款金额:</text>
            <text class="order-card__summary-amount">¥{{ order.refund_amount_text }}</text>
          </view>

          <view v-if="order.refund_reason" class="order-card__refund-reason">
            <text class="order-card__refund-reason-label">退款原因</text>
            <text class="order-card__refund-reason-text">{{ order.refund_reason }}</text>
          </view>

          <view class="order-card__actions">
            <view class="action-btn" @click.stop="handleGoDetail(order)">
              <text class="action-btn__text">查看详情</text>
            </view>
            <view
              v-if="order.can_cancel_refund"
              class="action-btn action-btn--primary"
              @click.stop="confirmCancelRefund(order)"
            >
              <text class="action-btn__text">取消申请退款</text>
            </view>
          </view>
        </view>

        <view v-if="!loading && refundList.length === 0" class="empty-state">
          <uni-icons type="folder-add" size="60" color="#d1d5db"></uni-icons>
          <text class="empty-state__text">{{ emptyText }}</text>
          <view class="empty-state__btn" @click="handleGoOrderList">查看订单</view>
        </view>

        <view v-if="refundList.length > 0" class="load-more">
          <text v-if="loading" class="load-more__text">加载中...</text>
          <text v-else-if="finished" class="load-more__text">没有更多了</text>
        </view>
      </view>
    </scroll-view>
  </view>
</template>

<script setup>
import { computed, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { cancelRefundOrder, getOrderRefundList } from '@/api/order'
import { navigateToMerchantHome } from '@/utils/merchant'
import { themeState } from '@/utils/theme'

/**
 * 金额数字转换为固定两位小数价格文本。
 *
 * @param {number|string} value 金额。
 * @returns {string} 格式化后的金额。
 */
function formatPrice(value) {
  if (value === undefined || value === null || isNaN(Number(value))) {
    return '0.00'
  }

  return Number(value).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
}

/**
 * 退款售后列表页面。
 * 展示当前用户退款中和已退款订单，支持下拉刷新、加载更多与取消退款申请。
 */
defineOptions({ name: 'OrderRefundList' })

const loading = ref(false)
const refreshing = ref(false)
const finished = ref(false)
const page = ref(1)
const limit = ref(10)
const activeTab = ref(0)
const tabList = ref([
  { name: '待处理', status: 'pending', emptyText: '暂无待处理售后' },
  { name: '已退款', status: 'refunded', emptyText: '暂无已退款记录' },
  { name: '申请记录', status: 'record', emptyText: '暂无退款记录' }
])
const refundList = ref([])

const currentTab = computed(() => {
  return tabList.value[activeTab.value] || tabList.value[0]
})
const emptyText = computed(() => {
  return currentTab.value.emptyText || '暂无退款记录'
})

onLoad(() => {
  loadRefundList(true)
})

/**
 * 加载退款列表。
 *
 * @param {boolean} isRefresh 是否刷新并重置分页。
 * @returns {Promise<void>}
 */
async function loadRefundList(isRefresh = false) {
  if (loading.value) {
    refreshing.value = false
    uni.stopPullDownRefresh()
    return
  }

  if (isRefresh) {
    page.value = 1
    finished.value = false
  }

  loading.value = true
  try {
    const res = await getOrderRefundList({
      page: page.value,
      limit: limit.value,
      status: currentTab.value.status
    })

    if (res.code === 200 && res.data) {
      const sourceList = Array.isArray(res.data.list) ? res.data.list : []
      const list = sourceList.map((order) => normalizeRefundOrder(order))
      if (isRefresh || page.value === 1) {
        refundList.value = list
      } else {
        refundList.value = refundList.value.concat(list)
      }
      finished.value = list.length < limit.value
      page.value += 1
    }
  } catch (error) {
    console.error('加载退款列表失败', error)
  } finally {
    loading.value = false
    refreshing.value = false
    uni.stopPullDownRefresh()
  }
}

/**
 * 下拉刷新退款列表。
 *
 * @returns {void}
 */
function onPullDownRefresh() {
  refreshing.value = true
  loadRefundList(true)
}

/**
 * 触底加载下一页。
 *
 * @returns {void}
 */
function handleLoadMore() {
  if (loading.value || finished.value) {
    return
  }

  loadRefundList(false)
}

/**
 * 切换退款列表状态。
 *
 * @param {number} index Tab 索引。
 * @returns {void}
 */
function handleSwitchTab(index) {
  if (activeTab.value === index || loading.value) {
    return
  }

  activeTab.value = index
  refundList.value = []
  loadRefundList(true)
}

/**
 * 进入退款详情。
 *
 * @param {Object} order 当前订单。
 * @returns {void}
 */
function handleGoDetail(order) {
  const refundSn = String(order.refund_sn || '').trim()
  const orderSn = String(order.order_sn || '').trim()
  if (refundSn) {
    uni.navigateTo({ url: `/pages/orders/refund/detail?refund_sn=${encodeURIComponent(refundSn)}` })
    return
  }
  if (orderSn) {
    uni.navigateTo({ url: `/pages/orders/refund/detail?order_sn=${encodeURIComponent(orderSn)}` })
    return
  }

  uni.navigateTo({ url: `/pages/orders/refund/detail?order_id=${order.order_id}` })
}

/**
 * 返回订单列表。
 *
 * @returns {void}
 */
function handleGoOrderList() {
  uni.navigateTo({ url: '/pages/orders/order/list/index?status=refund' })
}

/**
 * 进入商户首页；平台自营（无商户）时返回首页。
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
 * 规范化退款订单，提前生成模板可直接绑定的字段。
 *
 * @param {Object} order 接口返回订单。
 * @returns {Object} 页面渲染订单。
 */
function normalizeRefundOrder(order = {}) {
  const orderType = Number(order.order_type || 0)
  const list = Array.isArray(order.goods_preview) ? order.goods_preview : []
  const goodsPreview = list.map((goods) => normalizeRefundGoods(goods, orderType))
  const firstGoods = goodsPreview[0] || normalizeRefundGoods({
    goods_image: '',
    goods_name: '',
    sku_name: '',
    price: 0,
    num: 0
  }, orderType)
  const refundAmount = resolveRefundAmount(order)

  return {
    ...order,
    render_key: order.refund_sn || order.order_sn || `refund-${order.order_id || 0}`,
    merchant_id: Number(order.merchant_id || 0),
    order_type: orderType,
    goods_preview: goodsPreview,
    first_goods: firstGoods,
    refund_display_text: order.refund_status_text || order.status_text || '',
    refund_amount_text: formatPrice(refundAmount),
    can_cancel_refund: canCancelRefund(order)
  }
}

/**
 * 规范化退款列表商品字段。
 *
 * @param {Object} goods 接口返回商品行。
 * @param {number} fallbackOrderType 订单类型兜底值。
 * @returns {Object} 页面渲染商品。
 */
function normalizeRefundGoods(goods = {}, fallbackOrderType = 0) {
  const price = Number(goods.price || 0)

  return {
    ...goods,
    goods_id: Number(goods.goods_id || goods.goodsId || 0),
    order_type: Number(goods.order_type || goods.orderType || fallbackOrderType || 0),
    goods_type: Number(goods.goods_type || goods.goodsType || 0),
    content_type: Number(goods.content_type || goods.contentType || 0),
    detail_url: String(goods.detail_url || goods.detailUrl || ''),
    price,
    price_text: formatPrice(price),
    num: Number(goods.num || 0)
  }
}

/**
 * 解析退款金额；未返回退款金额时兜底显示实付金额。
 *
 * @param {Object} order 当前订单。
 * @returns {number} 退款金额。
 */
function resolveRefundAmount(order = {}) {
  const refundAmount = Number(order.refund_amount || 0)
  return refundAmount > 0 ? refundAmount : Number(order.pay_amount || 0)
}

/**
 * 判断当前订单是否允许取消退款申请。
 *
 * @param {Object} order 当前订单。
 * @returns {boolean} 是否显示取消按钮。
 */
function canCancelRefund(order = {}) {
  return Boolean(order.can_cancel_refund)
}

/**
 * 二次确认取消退款申请。
 *
 * @param {Object} order 当前订单。
 * @returns {void}
 */
function confirmCancelRefund(order = {}) {
  uni.showModal({
    title: '取消退款申请',
    content: '确定要取消该订单的退款申请吗？',
    confirmColor: themeState.primary_color,
    success: async (res) => {
      if (!res.confirm) {
        return
      }

      await cancelRefund(order)
    }
  })
}

/**
 * 调用接口取消退款申请。
 *
 * @param {Object} order 当前订单。
 * @returns {Promise<void>}
 */
async function cancelRefund(order = {}) {
  const orderSn = String(order.order_sn || '').trim()
  if (!orderSn) {
    uni.showToast({ title: '订单号不能为空', icon: 'none' })
    return
  }

  try {
    await cancelRefundOrder({ order_sn: orderSn })
    uni.showToast({ title: '已取消退款申请', icon: 'success' })
    loadRefundList(true)
  } catch (error) {
    console.error('取消退款申请失败', error)
  }
}
</script>


<style lang="scss">
page {
  height: 100%;
  background-color: #f5f5f5;
}
</style>

<style scoped lang="scss">
.refund-list-page {
  display: flex;
  flex-direction: column;
  height: 100%;
  background-color: #f5f5f5;
}

.refund-scroll {
  flex: 1;
  min-height: 0;
}

.refund-content {
  padding: 20rpx 24rpx 40rpx;
}

.tab-bar {
  white-space: nowrap;
  margin-bottom: 20rpx;
  background-color: #ffffff;
  border-radius: 16rpx;
  box-shadow: 0 4rpx 12rpx rgba(15, 23, 42, 0.03);

  ::-webkit-scrollbar {
    display: none;
    width: 0;
    height: 0;
  }

  &__inner {
    display: inline-flex;
    align-items: center;
    min-width: 100%;
    padding: 0 6rpx;
    white-space: nowrap;
    box-sizing: border-box;
  }
}

.tab-item {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 1;
  min-width: 196rpx;
  padding: 22rpx 24rpx 20rpx;
  box-sizing: border-box;

  &:active {
    opacity: 0.7;
  }

  &--active {
    .tab-item__text {
      color: var(--brand-primary, #ea580c);
      font-weight: 700;
    }

    &::after {
      content: '';
      position: absolute;
      left: 50%;
      bottom: 0;
      transform: translateX(-50%);
      width: 52rpx;
      height: 4rpx;
      background-color: var(--brand-primary, #ea580c);
      border-radius: 2rpx;
    }
  }

  &__text {
    display: block;
    font-size: 28rpx;
    line-height: 36rpx;
    color: #7e7576;
    white-space: nowrap;
  }
}

.order-card {
  background-color: #ffffff;
  border-radius: 16rpx;
  padding: 24rpx;
  margin-bottom: 20rpx;
  box-shadow: 0 4rpx 12rpx rgba(15, 23, 42, 0.03);

  &__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 20rpx;
  }

  &__header-left {
    display: flex;
    flex-direction: column;
    min-width: 0;
    flex: 1;
  }

  &__shop {
    display: flex;
    align-items: center;
  }

  &__shop-name {
    font-size: 28rpx;
    font-weight: 700;
    color: #1a1c1c;
    margin: 0 8rpx;
  }

  &__sn {
    margin-top: 8rpx;
    font-size: 22rpx;
    line-height: 30rpx;
    color: #9ca3af;
  }

  &__status {
    flex-shrink: 0;
    margin-left: 20rpx;
    font-size: 26rpx;
    font-weight: 700;
    color: #9ca3af;

    &--active {
      color: var(--brand-primary, #ea580c);
    }
  }

  &__goods-img-wrap {
    position: relative;
  }

  &__type-tag {
    position: absolute;
    top: 0;
    left: 0;
    z-index: 2;
    max-width: 200rpx;
    padding: 4rpx 12rpx;
    border-radius: 16rpx 0 16rpx 0;
    background: var(--brand-primary, #ea580c);
    font-size: 20rpx;
    line-height: 1.4;
    color: #ffffff;
  }

  &__goods-multi {
    margin-bottom: 20rpx;
  }

  &__goods-multi-inner {
    display: flex;
    flex-shrink: 0;
  }

  &__goods-img-sm {
    width: 128rpx;
    height: 128rpx;
    border-radius: 12rpx;
    background-color: #f9f9f9;
    flex-shrink: 0;
  }

  &__goods-img-touch {
    display: inline-flex;
    flex-shrink: 0;

    & + & {
      margin-left: 16rpx;
    }

    &:active {
      opacity: 0.85;
    }
  }

  &__goods-single {
    display: flex;
    margin-bottom: 20rpx;

    &:active {
      opacity: 0.85;
    }
  }

  &__goods-img {
    width: 160rpx;
    height: 160rpx;
    border-radius: 12rpx;
    background-color: #f9f9f9;
    flex-shrink: 0;
  }

  &__goods-info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    margin-left: 20rpx;
  }

  &__goods-title {
    font-size: 26rpx;
    font-weight: 700;
    color: #1a1c1c;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    overflow: hidden;
  }

  &__goods-sku {
    font-size: 20rpx;
    color: #9ca3af;
    background-color: #f9f9f9;
    padding: 4rpx 10rpx;
    border-radius: 4rpx;
    align-self: flex-start;
    margin-top: 8rpx;
  }

  &__goods-bottom {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    margin-top: 12rpx;
  }

  &__price {
    font-size: 32rpx;
    font-weight: 700;
    color: #1a1c1c;
  }

  &__qty {
    font-size: 24rpx;
    color: #9ca3af;
  }

  &__summary {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding-top: 16rpx;
    border-top: 1rpx solid #f3f3f3;
    margin-bottom: 12rpx;
  }

  &__summary-label {
    font-size: 24rpx;
    color: #7e7576;
    margin-right: 12rpx;
  }

  &__summary-pay {
    font-size: 24rpx;
    color: #7e7576;
    margin-left: 12rpx;
  }

  &__summary-amount {
    font-size: 28rpx;
    font-weight: 700;
    color: #1a1c1c;
  }

  &__refund-reason {
    display: flex;
    align-items: flex-start;
    padding: 16rpx 18rpx;
    margin-bottom: 16rpx;
    border-radius: 12rpx;
    background-color: var(--brand-primary-pale, #fff7ed);
    box-sizing: border-box;
  }

  &__refund-reason-label {
    flex-shrink: 0;
    margin-right: 16rpx;
    font-size: 24rpx;
    line-height: 34rpx;
    color: #9a3412;
  }

  &__refund-reason-text {
    flex: 1;
    min-width: 0;
    font-size: 24rpx;
    line-height: 34rpx;
    color: #7c2d12;
  }

  &__actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
  }
}

.action-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 132rpx;
  height: 56rpx;
  padding: 0 24rpx;
  border: 1rpx solid #e2e2e2;
  border-radius: 999rpx;
  margin-left: 12rpx;
  box-sizing: border-box;

  &:first-child {
    margin-left: 0;
  }

  &:active {
    opacity: 0.7;
  }

  &--primary {
    border-color: var(--brand-primary, #ea580c);

    .action-btn__text {
      color: var(--brand-primary, #ea580c);
      font-weight: 700;
    }
  }

  &__text {
    display: block;
    font-size: 24rpx;
    line-height: 34rpx;
    color: #5f6368;
    white-space: nowrap;
  }
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding-top: 200rpx;

  &__text {
    font-size: 28rpx;
    color: #9ca3af;
    margin-top: 24rpx;
  }

  &__btn {
    margin-top: 32rpx;
    padding: 16rpx 56rpx;
    background-color: var(--brand-primary, #ea580c);
    color: #ffffff;
    font-size: 28rpx;
    font-weight: 600;
    border-radius: 44rpx;

    &:active {
      opacity: 0.85;
    }
  }
}

.load-more {
  display: flex;
  justify-content: center;
  padding: 30rpx 0 20rpx;

  &__text {
    font-size: 24rpx;
    color: #9ca3af;
  }
}
</style>
