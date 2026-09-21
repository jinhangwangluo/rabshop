<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="order-list-page">
    <!-- 自定义导航栏（含搜索） -->
    <app-safe-top mp-extra="0" background="#ffffff" title="我的订单" show-back>
      <view class="search-box">
        <uni-icons type="search" size="14" color="#9ca3af" class="search-box__icon"></uni-icons>
        <input
          class="search-box__input"
          v-model="keyword"
          placeholder="搜索我的订单"
          placeholder-class="search-placeholder"
          :maxlength="30"
          confirm-type="search"
          @confirm="handleSearch"
        />
      </view>

      <!-- Tab 标签栏（横向滚动） -->
      <scroll-view scroll-x class="tab-bar" :show-scrollbar="false">
        <view class="tab-bar__inner">
          <view
            v-for="(tab, index) in tabList"
            :key="index"
            :class="['tab-item', { 'tab-item--active': activeTab === index }]"
            @click="handleSwitchTab(index)"
          >
            <text class="tab-item__text">{{ tab.name }}</text>
          </view>
        </view>
      </scroll-view>
    </app-safe-top>

    <!-- 订单列表区域 -->
    <scroll-view
      scroll-y
      class="order-scroll"
      refresher-enabled
      :refresher-triggered="refreshing"
      @refresherrefresh="onPullDownRefresh"
    >
      <view class="order-content">
        <!-- 订单卡片 -->
        <view
          v-for="(order, index) in orderList"
          :key="order.order_id"
          class="order-card"
        >
          <!-- 卡片头部：店铺名 + 订单状态 -->
          <view class="order-card__header">
            <view class="order-card__header-left">
              <view class="order-card__shop" @click="handleGoShop(order.merchant_id)">
                <uni-icons type="shop" size="16" color="#9ca3af"></uni-icons>
                <text class="order-card__shop-name">{{ order.merchant_name }}</text>
                <uni-icons v-if="order.merchant_id > 0" type="right" size="12" color="#9ca3af"></uni-icons>
              </view>
            </view>
            <text :class="['order-card__status', { 'order-card__status--active': isStatusActive(getStatusKey(order.status, order.delivery_time)) }]">
              {{ order.status_text }}
            </text>
          </view>

          <!-- 商品列表：多商品模式 vs 单商品模式 -->
          <block v-if="order.goods_count > 1">
            <!-- 多商品：图片横排 -->
            <view class="order-card__goods-img-wrap">
              <text class="order-card__type-tag">{{ order.order_type_text }}</text>
              <scroll-view scroll-x class="order-card__goods-multi" :show-scrollbar="false">
                <view class="order-card__goods-multi-inner">
                  <image
                    v-for="(goods, gi) in order.goods_preview"
                    :key="gi"
                    class="order-card__goods-img-sm"
                    :src="goods.goods_image"
                    mode="aspectFill"
                  ></image>
                </view>
              </scroll-view>
            </view>
          </block>
          <block v-else>
            <!-- 单商品：图文详情 -->
            <view class="order-card__goods-single" @click="handleGoDetail(order.order_id)">
              <view class="order-card__goods-img-wrap">
                <text class="order-card__type-tag">{{ order.order_type_text }}</text>
                <image class="order-card__goods-img" :src="order.goods_preview[0].goods_image" mode="aspectFill"></image>
              </view>
              <view class="order-card__goods-info">
                <text class="order-card__goods-title">{{ order.goods_preview[0].goods_name }}</text>
                <text v-if="order.goods_preview[0].sku_name" class="order-card__goods-sku">{{ order.goods_preview[0].sku_name }}</text>
                <view class="order-card__goods-bottom">
                  <text class="order-card__price">¥{{ formatPrice(order.goods_preview[0].price) }}</text>
                  <text class="order-card__qty">x{{ order.goods_preview[0].num }}</text>
                </view>
              </view>
            </view>
          </block>

          <!-- 统计信息行 -->
          <view class="order-card__summary">
            <text class="order-card__summary-label">共{{ order.goods_total_num }}件商品</text>
            <text class="order-card__summary-pay">实付款:</text>
            <text class="order-card__summary-amount">¥{{ formatPrice(order.pay_amount) }}</text>
          </view>

          <!-- 操作按钮行 -->
          <view class="order-card__actions">
            <view
              v-for="(btn, bi) in getActionButtons(getStatusKey(order.status, order.delivery_time), order)"
              :key="bi"
              :class="['action-btn', { 'action-btn--primary': btn.primary }]"
              @click="handleAction(order, btn.action)"
            >
              <text class="action-btn__text">{{ btn.text }}</text>
            </view>
          </view>
        </view>

        <!-- 空状态 -->
        <view v-if="!loading && orderList.length === 0" class="empty-state">
          <uni-icons type="folder-add" size="60" color="#d1d5db"></uni-icons>
          <text class="empty-state__text">暂无订单记录</text>
          <view class="empty-state__btn" @click="handleGoShopping">去逛逛</view>
        </view>

        <!-- 加载更多提示 -->
        <view v-if="orderList.length > 0" class="load-more">
          <text v-if="loading" class="load-more__text">加载中...</text>
          <text v-else-if="finished" class="load-more__text">没有更多了</text>
        </view>
      </view>
    </scroll-view>
  </view>
</template>

<script setup>
import { ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getOrderList, cancelOrder, receiptOrder, deleteOrder } from '@/api/order'
import { navigateToMerchantHome } from '@/utils/merchant'
import { themeState } from '@/utils/theme'

/**
 * int status + delivery_time → 前端字符串状态键。
 * 用于 getActionButtons / isStatusActive 等方法。
 */
function getStatusKey(status, deliveryTime) {
  const s = Number(status)
  if (s === 0) return 'unpaid'
  if (s === 1 && deliveryTime) return 'undelivered'
  if (s === 1) return 'unsent'
  if (s === 2) return 'complete'
  if (s === 6) return 'unevaluated'
  if (s === 3 || s === 4 || s === 5) return 'refund'
  return 'complete'
}

/**
 * 金额数字 → 千分位价格字符串（如 "8,999.00"）。
 */
function formatPrice(value) {
  if (value === undefined || value === null || isNaN(Number(value))) return '0.00'
  return Number(value).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
}

const LEGACY_STATUS_TAB_MAP = {
  0: 'unpaid',
  1: 'unsent',
  2: 'undelivered',
  3: 'unevaluated',
  4: 'refund',
  6: 'unevaluated'
}

/**
 * 将页面入参中的状态值规范化为订单列表 Tab 使用的状态键。
 * 兼容旧入口传入的 0-4 数字状态，也支持直接传入字符串状态。
 */
function normalizeRouteStatus(status) {
  const value = String(status === undefined || status === null ? '' : status).trim()
  if (!value) return ''
  return LEGACY_STATUS_TAB_MAP[value] || value
}

/**
 * 我的订单列表页面。
 * 支持按 Tab 切换不同订单状态（全部/待付款/待发货/待收货/待评价/退款售后），
 * 下拉刷新、上拉加载更多、搜索订单等功能。
 */
defineOptions({ name: 'OrderList' })

/** 搜索关键词 */
const keyword = ref('')
/** 当前选中的 Tab 索引 */
const activeTab = ref(0)
/** Tab 列表配置 */
const tabList = ref([
  { name: '全部', status: '' },
  { name: '待付款', status: 'unpaid' },
  { name: '待发货', status: 'unsent' },
  { name: '待收货', status: 'undelivered' },
  { name: '待评价', status: 'unevaluated' }
])
/** 加载中状态 */
const loading = ref(false)
/** 下拉刷新状态 */
const refreshing = ref(false)
/** 是否加载完毕 */
const finished = ref(false)
/** 页码 */
const page = ref(1)
/** 每页数量 */
const limit = ref(10)
/** 订单列表数据 */
const orderList = ref([])

onLoad((options = {}) => {
  applyRouteTab(options)
  loadOrderList(true)
})

/**
 * 根据页面跳转参数选中初始 Tab。
 * @param {Object} options 页面 onLoad 入参，支持 status 或 tab。
 */
function applyRouteTab(options = {}) {
  const rawStatus = options.status !== undefined ? options.status : options.tab
  const status = normalizeRouteStatus(rawStatus)
  const tabIndex = tabList.value.findIndex(tab => tab.status === status)
  activeTab.value = tabIndex > -1 ? tabIndex : 0
}

/**
 * 加载订单列表。
 * @param {boolean} isRefresh 是否为下拉刷新操作（重置页码）。
 */
async function loadOrderList(isRefresh = false) {
  if (loading.value) return

  if (isRefresh) {
    page.value = 1
    finished.value = false
  }

  loading.value = true
  try {
    const params = {
      page: page.value,
      limit: limit.value,
      status: tabList.value[activeTab.value].status,
      keyword: keyword.value
    }

    const res = await getOrderList(params)
    if (res.code === 200 && res.data) {
      const list = res.data.list || []
      if (isRefresh || page.value === 1) {
        orderList.value = list
      } else {
        orderList.value = [...orderList.value, ...list]
      }
      finished.value = list.length < limit.value
      page.value++
    }
  } catch (e) {
    console.error('加载订单列表失败', e)
  } finally {
    loading.value = false
    refreshing.value = false
  }
}

/** 搜索订单 */
function handleSearch() {
  activeTab.value = 0
  loadOrderList(true)
}

/**
 * 切换 Tab 标签。
 * @param {number} index Tab 索引。
 */
function handleSwitchTab(index) {
  if (activeTab.value === index) return
  activeTab.value = index
  loadOrderList(true)
}

/** 下拉刷新回调 */
function onPullDownRefresh() {
  refreshing.value = true
  loadOrderList(true)
}

/** 进入店铺 */
function handleGoShop(merId) {
  navigateToMerchantHome(merId)
}

/** 进入订单详情 */
function handleGoDetail(orderId) {
  uni.navigateTo({ url: `/pages/orders/order/detail/index?id=${orderId}` })
}

/**
 * 判断状态是否为活跃（高亮显示）。
 * 活跃状态包括：等待付款、卖家已发货等未完成流程的状态。
 * @param {string} status 状态标识。
 * @returns {boolean}
 */
function isStatusActive(status) {
  const activeStatuses = ['unpaid', 'unsent', 'undelivered']
  return activeStatuses.includes(status)
}

/**
 * 根据后端 actions 解析评价按钮。
 * @param {Object} order 订单完整对象。
 * @returns {{ text: string, action: string, primary: boolean }|null}
 */
function resolveReviewButton(order = {}) {
  const actions = order && order.actions && typeof order.actions === 'object' ? order.actions : {}
  if (actions.can_review) {
    return { text: '评价', action: 'evaluate', primary: true }
  }
  if (actions.has_reviewed) {
    return { text: '查看评价', action: 'evaluate', primary: false }
  }

  return null
}

/**
 * 根据订单状态获取操作按钮列表。
 * @param {string} status 订单状态标识。
 * @param {Object} order 订单完整对象。
 * @returns {Array<{ text: string, action: string, primary: boolean }>} 按钮配置数组。
 */
function getActionButtons(status, order) {
  const reviewButton = resolveReviewButton(order)
  const buttonMap = {
    unpaid: [
      { text: '修改地址', action: 'editAddress', primary: false },
      { text: '立即付款', action: 'pay', primary: true }
    ],
    unsent: [
      { text: '查看详情', action: 'detail', primary: false },
      { text: '取消订单', action: 'cancel', primary: false },
      { text: '催发货', action: 'urge', primary: true }
    ],
    undelivered: [
      { text: '查看详情', action: 'detail', primary: false },
      { text: '查看物流', action: 'logistics', primary: false },
      { text: '确认收货', action: 'confirm', primary: true }
    ],
    unevaluated: [
      { text: '查看详情', action: 'detail', primary: false },
      reviewButton || { text: '评价', action: 'evaluate', primary: true }
    ],
    complete: [
      { text: '删除订单', action: 'delete', primary: false },
      reviewButton || { text: '查看评价', action: 'evaluate', primary: false },
      { text: '再次购买', action: 'rebuy', primary: true }
    ],
    refund: [
      { text: '查看详情', action: 'detail', primary: false },
      { text: '联系客服', action: 'service', primary: true }
    ]
  }
  let buttons = buttonMap[status] || [{ text: '查看详情', action: 'detail', primary: false }]

  // 虚拟商品不展示"修改地址"
  if (order && order.goods_preview && order.goods_preview.length) {
    const allVirtual = order.goods_preview.every(
      g => g.goods_type !== 1
    )
    if (allVirtual) {
      buttons = buttons.filter(b => b.action !== 'editAddress')
    }
  }

  return buttons
}

/**
 * 操作按钮点击处理。
 * @param {Object} order 订单完整对象。
 * @param {string} action 操作类型标识。
 */
function handleAction(order, action) {
  const orderId = order.order_id
  switch (action) {
    case 'detail':
      handleGoDetail(orderId)
      break
    case 'pay':
      uni.navigateTo({ url: `/pages/orders/pay/index?orderId=${orderId}&orderSn=${order.order_sn}` })
      break
    case 'cancel':
      confirmCancel(order)
      break
    case 'delete':
      confirmDelete(order)
      break
    case 'confirm':
      confirmReceive(order)
      break
    case 'logistics':
      uni.navigateTo({ url: `/pages/orders/express/index?order_id=${orderId}` })
      break
    case 'evaluate':
      handleGoDetail(orderId)
      break
    case 'rebuy':
      handleRebuy(order)
      break
    case 'editAddress':
      uni.navigateTo({ url: `/pages/users/address/list/index?from=order&orderId=${orderId}` })
      break
    default:
      uni.showToast({ title: '功能开发中', icon: 'none' })
  }
}

/** 确认取消订单 */
function confirmCancel(order) {
  uni.showModal({
    title: '提示',
    content: '确定要取消该订单吗？',
    confirmColor: themeState.primary_color,
    success: async (res) => {
      if (res.confirm) {
        try {
          await cancelOrder({ order_sn: order.order_sn })
          uni.showToast({ title: '取消成功', icon: 'success' })
          loadOrderList(true)
        } catch (e) {
          // 错误 toast 由 request.js 统一处理
        }
      }
    }
  })
}

/** 确认删除订单 */
function confirmDelete(order) {
  uni.showModal({
    title: '提示',
    content: '确定要删除该订单吗？',
    confirmColor: themeState.primary_color,
    success: async (res) => {
      if (res.confirm) {
        try {
          await deleteOrder({ order_sn: order.order_sn })
          uni.showToast({ title: '删除成功', icon: 'success' })
          loadOrderList(true)
        } catch (e) {
          // 错误 toast 由 request.js 统一处理
        }
      }
    }
  })
}

/** 确认收货 */
function confirmReceive(order) {
  uni.showModal({
    title: '确认收货',
    content: '请确认您已收到该商品？',
    confirmColor: themeState.primary_color,
    success: async (res) => {
      if (res.confirm) {
        try {
          await receiptOrder({ order_sn: order.order_sn })
          uni.showToast({ title: '已确认收货', icon: 'success' })
          loadOrderList(true)
        } catch (e) {
          // 错误 toast 由 request.js 统一处理
        }
      }
    }
  })
}

/** 再次购买 */
async function handleRebuy(order) {
  // TODO: 调用再次购买接口
  uni.showToast({ title: '已加入购物车', icon: 'success' })
}

/** 去购物 */
function handleGoShopping() {
  uni.switchTab({ url: '/pages/category/index' })
}
</script>


<style scoped lang="scss">
/* ========== 页面容器 ========== */
.order-list-page {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  background-color: #f5f5f5;
}

/* 搜索框 */
.search-box {
  display: flex;
  align-items: center;
  height: 64rpx;
  margin: 14rpx 20rpx 16rpx 18rpx;
  background-color: #f3f3f3;
  border-radius: 32rpx;
  padding: 0 22rpx;
  box-sizing: border-box;

  &__icon {
    margin-right: 12rpx;
    flex-shrink: 0;
  }

  &__input {
    flex: 1;
    height: 100%;
    font-size: 26rpx;
    color: #1a1c1c;
  }
}

/* 搜索框占位符文字 */
.search-placeholder {
  font-size: 26rpx;
  color: #c3c3c3;
}

/* ========== Tab 标签栏 ========== */
.tab-bar {
  white-space: nowrap;
  background-color: #ffffff;

  ::-webkit-scrollbar {
    display: none;
    width: 0;
    height: 0;
  }

  &__inner {
    display: inline-flex;
    align-items: center;
    padding: 0 6rpx;
    white-space: nowrap;
  }
}

.tab-item {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  padding: 20rpx 34rpx 18rpx;
  position: relative;
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
      width: 48rpx;
      height: 4rpx;
      background-color: var(--brand-primary, #ea580c);
      border-radius: 2rpx;
    }
  }

  &__text {
    display: block;
    font-size: 28rpx;
    color: #7e7576;
    line-height: 1.2;
    white-space: nowrap;
    word-break: keep-all;
	letter-spacing:3rpx
  }
}

/* ========== 订单滚动区域 ========== */
.order-scroll {
  flex: 1;
  min-height: 0;
}

.order-content {
  padding: 20rpx 24rpx;
  padding-bottom: 40rpx;
}

/* ========== 订单卡片 ========== */
.order-card {
  background-color: #ffffff;
  border-radius: 16rpx;
  padding: 24rpx;
  margin-bottom: 20rpx;
  box-shadow: 0 4rpx 12rpx rgba(15, 23, 42, 0.03);

  /* 卡片头部 */
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

  /* 商品图片容器（用于定位角标） */
  &__goods-img-wrap {
    position: relative;
  }

  /* 订单类型角标（覆盖在图片左上角） */
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

  &__shop {
    display: flex;
    align-items: center;

    &:active {
      opacity: 0.6;
    }
  }

  &__shop-name {
    font-size: 28rpx;
    font-weight: 700;
    color: #1a1c1c;
    margin: 0 8rpx;
  }

  &__status {
    font-size: 26rpx;
    font-weight: 700;
    color: #9ca3af;

    &--active {
      color: var(--brand-primary, #ea580c);
    }
  }

  /* 多商品模式 —— 图片横排 */
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

    & + & {
      margin-left: 16rpx;
    }
  }

  /* 单商品模式 —— 图文详情 */
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
    line-clamp: 2;
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

  /* 统计行 */
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

  /* 操作按钮行 */
  &__actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
  }
}

/* 操作按钮 */
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

/* ========== 空状态 ========== */
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

/* ========== 加载更多 ========== */
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
