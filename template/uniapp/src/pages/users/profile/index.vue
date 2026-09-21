<template>
	<page-meta :page-style="themePageStyle"></page-meta>
	<view class="points-page">
		<image class="points-page__hero-bg" src="../static/profile-points-hero-bg.jpg" mode="aspectFill"></image>

		<view class="points-page__safe-top">
			<app-safe-top background="transparent" title="积分资产" title-color="#ffffff" icon-color="#ffffff" />
		</view>

		<view class="points-hero">
			<text class="points-hero__label">当前可用积分</text>
			<view class="points-hero__value-row">
				<text class="points-hero__value">{{ summary.availablePoints }}</text>
				<text class="points-hero__unit">分</text>
			</view>
		</view>

		<view class="points-content">
			<view class="quick-card">
				<view v-for="action in quickActions" :key="action.key" class="quick-card__item"
					hover-class="quick-card__item--hover" @click="handleQuickAction(action)">
					<view class="quick-card__icon-wrap">
						<image class="quick-card__icon" :src="action.icon" mode="aspectFit"></image>
					</view>
					<text class="quick-card__title">{{ action.title }}</text>
					<text class="quick-card__desc">{{ action.desc }}</text>
				</view>
			</view>

			<view class="member-card" hover-class="member-card--hover" @click="handleOpenMember">
				<view class="member-card__content">
					<text class="member-card__title">VIP黑卡会员多倍积分</text>
					<text class="member-card__desc">升级黑卡 购物享多倍积分</text>
					<view class="member-card__button">
						<text class="member-card__button-text">立即升级</text>
					</view>
				</view>
				<view class="member-card__vip">
					<image class="member-card__vip-image" src="../static/profile-member-vip-card.jpg" mode="aspectFill"></image>
				</view>
				<view class="member-card__badge">
					<text class="member-card__badge-text">多倍积分</text>
				</view>
			</view>

			<view class="stats-row">
				<view v-for="item in statsList" :key="item.key" :class="['stat-card', 'stat-card--' + item.theme]">
					<view class="stat-card__main">
						<view class="stat-card__label-row">
							<text class="stat-card__label">{{ item.label }}</text>
							<uni-icons type="info" size="15" color="#a6adbc"></uni-icons>
						</view>
						<text :class="['stat-card__value', 'stat-card__value--' + item.theme]">{{ item.value }}</text>
					</view>
					<view :class="['stat-card__decor', 'stat-card__decor--' + item.theme]">
						<uni-icons :type="item.icon" size="30" :color="item.iconColor"></uni-icons>
					</view>
				</view>
			</view>

			<view class="records-heading">
				<text class="records-heading__title">积分明细</text>
			</view>

			<view class="records-card">
				<view class="filter-tabs">
					<view v-for="(tab, index) in tabs" :key="tab.value"
						:class="['filter-tab', { 'filter-tab--active': activeTab === index }]"
						hover-class="filter-tab--hover" @click="handleSwitchTab(index)">
						<text
							:class="['filter-tab__text', { 'filter-tab__text--active': activeTab === index }]">{{ tab.label }}</text>
					</view>
				</view>

				<block v-if="displayRecords.length">
					<view v-for="(record, index) in displayRecords" :key="record.id"
						:class="['record-item', { 'record-item--border': index !== displayRecords.length - 1 }]"
						hover-class="record-item--hover" @click="handleRecordClick(record)">
						<view class="record-item__left">
							<view :class="['record-item__icon', 'record-item__icon--' + record.theme]">
								<uni-icons type="wallet-filled" size="18" :color="record.iconColor"></uni-icons>
							</view>
							<view class="record-item__meta">
								<text class="record-item__title">{{ record.title }}</text>
								<text class="record-item__time">{{ record.time }}</text>
							</view>
						</view>
						<text
							:class="['record-item__amount', 'record-item__amount--' + record.amountType]">{{ record.amount }}</text>
					</view>
				</block>

				<view v-else-if="!recordsLoading" class="empty-state">
					<view class="empty-state__icon">
						<uni-icons type="wallet" size="34" color="#d8dce5"></uni-icons>
					</view>
					<text class="empty-state__text">当前筛选下暂无积分记录</text>
				</view>
			</view>

			<view v-if="recordsLoading" class="record-more">
				<six-petal-spiral active />
			</view>

			<view v-else-if="!hasMoreRecords && recordsTotal > 0" class="record-more">
				<text class="record-more__text">没有更多了</text>
			</view>

		</view>

		<view class="safe-bottom"></view>

		<app-floating-button />
	</view>
</template>

<script setup>
import { getPointsStatistics, getPointsLogs } from '@/api/balance'
import profilePointsMallIcon from '../static/profile-points-mall-icon.png'
import profilePointsCouponIcon from '../static/profile-points-coupon-icon.png'
import profilePointsSigninIcon from '../static/profile-points-signin-icon.png'
import profilePointsLotteryIcon from '../static/profile-points-lottery-icon.png'
import { computed, ref } from 'vue'
import { onLoad, onPullDownRefresh, onReachBottom } from '@dcloudio/uni-app'

defineOptions({ name: 'UserPointsPage' })

const activeTab = ref(0)
const summary = ref({
  availablePoints: '0'
})
const quickActions = [{
    key: 'mall',
    title: '商城',
    desc: '好物兑不停',
    icon: profilePointsMallIcon,
    route: '/pages/index/index',
    openType: 'switchTab'
  },
  {
    key: 'coupon',
    title: '优惠券',
    desc: '超值优惠',
    icon: profilePointsCouponIcon,
    route: '/pages/activity/coupon/index',
    openType: 'navigateTo'
  },
  {
    key: 'signin',
    title: '签到有礼',
    desc: '每日领积分',
    icon: profilePointsSigninIcon,
    route: '/pages/activity/signin/index',
    openType: 'navigateTo'
  },
  {
    key: 'lottery',
    title: '积分抵扣',
    desc: '积分花不停',
    icon: profilePointsLotteryIcon,
    route: '/pages/category/index',
    openType: 'navigateTo'
  }
]
const statsList = ref([{
    key: 'income',
    label: '本月获得',
    value: '+0',
    icon: 'gift-filled',
    iconColor: '#ffb34f',
    theme: 'income'
  },
  {
    key: 'expense',
    label: '本月使用',
    value: '-0',
    icon: 'wallet-filled',
    iconColor: '#5d7df5',
    theme: 'expense'
  }
])
const tabs = [{
    label: '全部',
    value: 'all'
  },
  {
    label: '积分收入',
    value: 'in'
  },
  {
    label: '积分支出',
    value: 'out'
  }
]
const records = ref([])
const recordsLoading = ref(false)
const recordsPage = ref(1)
const recordsLimit = 10
const recordsTotal = ref(0)

const displayRecords = computed(() => {
  return records.value.map((item) => mapRecordItem(item))
})
const hasMoreRecords = computed(() => {
  return records.value.length < Number(recordsTotal.value || 0)
})

onLoad(() => {
  loadPageData()
})
onPullDownRefresh(() => {
  loadPageData().finally(() => {
    uni.stopPullDownRefresh()
  })
})
onReachBottom(() => {
  handleReachBottom()
})

async function loadPageData() {
  await Promise.all([
    loadPointsSummary(),
    loadRecords()
  ])
}

async function loadPointsSummary() {
  try {
    const res = await getPointsStatistics()
    if (res && res.code === 200 && res.data) {
      const data = res.data
      summary.value.availablePoints = formatPoints(data.points)
      statsList.value = [{
          key: 'income',
          label: '本月获得',
          value: formatSignedPoints(data.month_income, '+'),
          icon: 'gift-filled',
          iconColor: '#ffb34f',
          theme: 'income'
        },
        {
          key: 'expense',
          label: '本月使用',
          value: formatSignedPoints(data.month_expense, '-'),
          icon: 'wallet-filled',
          iconColor: '#5d7df5',
          theme: 'expense'
        }
      ]
    }
  } catch (error) {
    // 统计失败时保留占位数据
  }
}

async function loadRecords(append = false) {
  if (recordsLoading.value) return

  if (append && !hasMoreRecords.value) {
    return
  }

  recordsLoading.value = true

  try {
    const currentTab = tabs[activeTab.value] || tabs[0]
    const params = {
      page: append ? recordsPage.value + 1 : 1,
      limit: recordsLimit
    }

    if (currentTab.value !== 'all') {
      params.change_type = currentTab.value
    }

    const res = await getPointsLogs(params)
    if (res && res.code === 200 && res.data) {
      const data = res.data
      recordsTotal.value = Number(data.total || 0)
      recordsPage.value = Number(data.page || 1)

      if (append) {
        records.value = records.value.concat(data.list || [])
      } else {
        records.value = data.list || []
      }
    }
  } catch (error) {
    // 列表失败时保留已有数据
  } finally {
    recordsLoading.value = false
  }
}

function handleSwitchTab(index) {
  if (activeTab.value === index) {
    return
  }

  activeTab.value = index
  recordsPage.value = 1
  recordsTotal.value = 0
  records.value = []
  loadRecords()
}

function handleReachBottom() {
  if (recordsLoading.value || !hasMoreRecords.value) {
    return
  }

  loadRecords(true)
}

function handleQuickAction(action = {}) {
  if (action.toast) {
    showToast(action.toast)
    return
  }

  if (!action.route) {
    return
  }

  if (action.openType === 'switchTab') {
    uni.switchTab({
      url: action.route
    })
    return
  }

  uni.navigateTo({
    url: action.route
  })
}

function handleOpenMall() {
  uni.switchTab({
    url: '/pages/index/index'
  })
}

function handleOpenMember() {
  uni.navigateTo({
    url: '/pages/users/member/index'
  })
}

function handleOpenAllRecords() {
  handleSwitchTab(0)
}

function handleRecordClick(record) {
  showToast(record.title)
}

function getSourceTypeMeta(sourceType = '') {
  const metaMap = {
    order_points: {
      title: '订单积分抵扣',
      theme: 'expense'
    },
    order_cancel_points: {
      title: '取消订单退回积分',
      theme: 'income'
    },
    order_refund_points: {
      title: '订单退款退回积分',
      theme: 'income'
    },
    member_points_bonus: {
      title: '会员积分加成',
      theme: 'income'
    },
    manual: {
      title: '积分调整',
      theme: 'income'
    }
  }

  return metaMap[sourceType] || {
    title: sourceType || '积分变动',
    theme: 'income'
  }
}

function mapRecordItem(item = {}) {
  const meta = getSourceTypeMeta(item.source_type)
  const isIn = item.change_type === 'in'
  const title = item.source_desc || item.remark || meta.title
  const time = item.create_time || ''

  return {
    id: item.id,
    title,
    time,
    amount: formatSignedPoints(item.amount, isIn ? '+' : '-'),
    amountType: isIn ? 'plus' : 'minus',
    iconColor: isIn ? 'var(--brand-primary, #ea580c)' : '#6b7280',
    theme: isIn ? 'income' : 'expense'
  }
}

function formatPoints(value) {
  const amount = Number(String(value === undefined || value === null ? '' : value).replace(/,/g, '').trim())
  if (!Number.isFinite(amount)) {
    return '0'
  }

  return String(Math.trunc(amount))
}

function formatSignedPoints(value, prefix = '') {
  return `${prefix}${formatPoints(value)}`
}

function showToast(title) {
  uni.showToast({
    title,
    icon: 'none'
  })
}
</script>

<style lang="scss">
	page {
		background: #f6f6f6;
	}
</style>

<style lang="scss" scoped>
	.points-page {
		position: relative;
		overflow: hidden;
		min-height: 100%;
		background: #f6f6f6;
	}

	.points-page__hero-bg {
		position: absolute;
		top: 0;
		left: 0;
		z-index: 0;
		width: 100%;
		height: 574rpx;
	}

	.points-page__safe-top {
		position: relative;
		z-index: 2;
	}

	.points-hero {
		position: relative;
		z-index: 1;
		padding: 64rpx 32rpx 142rpx;
		box-sizing: border-box;
		text-align: center;
	}

	.points-hero__label {
		display: block;
		font-size: 28rpx;
		line-height: 40rpx;
		font-weight: 500;
		color: rgba(255, 255, 255, 0.94);
	}

	.points-hero__value-row {
		margin-top: 20rpx;
		display: flex;
		align-items: baseline;
		justify-content: center;
	}

	.points-hero__value {
		font-size: 72rpx;
		line-height: 78rpx;
		font-weight: 800;
		color: #ffffff;
	}

	.points-hero__unit {
		margin-left: 12rpx;
		font-size: 34rpx;
		line-height: 40rpx;
		color: #ffe08a;
	}

	.points-content {
		position: relative;
		z-index: 2;
		margin-top: -94rpx;
		padding: 0 26rpx;
		box-sizing: border-box;
	}

	.quick-card {
		padding: 34rpx 16rpx 32rpx;
		border-radius: 24rpx;
		background: #ffffff;
		box-shadow: 0 14rpx 36rpx rgba(97, 62, 34, 0.08);
		display: flex;
		align-items: flex-start;
		box-sizing: border-box;
	}

	.quick-card__item {
		width: 25%;
		display: flex;
		flex-direction: column;
		align-items: center;
		box-sizing: border-box;
	}

	.quick-card__item--hover {
		opacity: 0.86;
	}

	.quick-card__icon-wrap {
		width: 72rpx;
		height: 72rpx;
		border-radius: 50%;
		background: #fff6f1;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.quick-card__icon {
		width: 54rpx;
		height: 54rpx;
	}

	.quick-card__title {
		display: block;
		margin-top: 18rpx;
		max-width: 132rpx;
		font-size: 28rpx;
		line-height: 36rpx;
		font-weight: 700;
		color: #111318;
		text-align: center;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.quick-card__desc {
		display: block;
		margin-top: 8rpx;
		max-width: 132rpx;
		font-size: 22rpx;
		line-height: 30rpx;
		color: #9196a3;
		text-align: center;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.member-card {
		position: relative;
		margin-top: 24rpx;
		min-height: 154rpx;
		padding: 24rpx 26rpx 22rpx 30rpx;
		border-radius: 16rpx;
		background: linear-gradient(116deg, #ff473b 0%, #ff7653 48%, #ff2c24 100%);
		box-shadow: 0 14rpx 30rpx rgba(255, 82, 43, 0.18);
		overflow: hidden;
		display: flex;
		align-items: center;
		justify-content: space-between;
		box-sizing: border-box;
	}

	.member-card--hover {
		opacity: 0.9;
	}

	.member-card__content {
		position: relative;
		z-index: 1;
		flex: 1;
		min-width: 0;
	}

	.member-card__title {
		display: block;
		font-size: 34rpx;
		line-height: 44rpx;
		font-weight: 800;
		color: #ffffff;
	}

	.member-card__desc {
		display: block;
		margin-top: 6rpx;
		font-size: 24rpx;
		line-height: 34rpx;
		color: rgba(255, 255, 255, 0.9);
	}

	.member-card__button {
		margin-top: 16rpx;
		width: 128rpx;
		height: 40rpx;
		border-radius: 999rpx;
		background: rgba(255, 255, 255, 0.95);
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.member-card__button-text {
		font-size: 22rpx;
		line-height: 28rpx;
		font-weight: 700;
		color: #ff4a25;
	}

	.member-card__vip {
		position: relative;
		z-index: 1;
		width: 254rpx;
		height: 160rpx;
		margin-left: 18rpx;
		border-radius: 18rpx;
		overflow: hidden;
		flex-shrink: 0;
		box-sizing: border-box;
	}

	.member-card__vip-image {
		display: block;
		width: 100%;
		height: 100%;
	}

	.member-card__badge {
		position: absolute;
		top: 0;
		right: 0;
		z-index: 2;
		width: 96rpx;
		height: 42rpx;
		border-radius: 0 16rpx 0 20rpx;
		background: #fff5cf;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.member-card__badge-text {
		font-size: 22rpx;
		line-height: 28rpx;
		font-weight: 700;
		color: #ff4a25;
	}

	.stats-row {
		margin-top: 18rpx;
		display: flex;
		align-items: center;
		justify-content: space-between;
	}

	.stat-card {
		position: relative;
		width: 318rpx;
		height: 138rpx;
		padding: 24rpx 24rpx 20rpx;
		border-radius: 16rpx;
		overflow: hidden;
		box-sizing: border-box;
		display: flex;
		align-items: center;
		justify-content: space-between;
	}

	.stat-card--income {
		background: linear-gradient(135deg, #fffaf3 0%, #fff1d9 100%);
	}

	.stat-card--expense {
		background: linear-gradient(135deg, #f6f8ff 0%, #edf2ff 100%);
	}

	.stat-card__main {
		position: relative;
		z-index: 1;
		flex: 1;
		min-width: 0;
	}

	.stat-card__label-row {
		display: flex;
		align-items: center;
	}

	.stat-card__label {
		margin-right: 8rpx;
		font-size: 28rpx;
		line-height: 36rpx;
		font-weight: 700;
		color: #15161a;
	}

	.stat-card__value {
		display: block;
		margin-top: 20rpx;
		font-size: 38rpx;
		line-height: 44rpx;
		font-weight: 800;
	}

	.stat-card__value--income {
		color: #62230b;
	}

	.stat-card__value--expense {
		color: #15161a;
	}

	.stat-card__decor {
		width: 82rpx;
		height: 82rpx;
		border-radius: 24rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		flex-shrink: 0;
	}

	.stat-card__decor--income {
		background: rgba(255, 183, 80, 0.2);
	}

	.stat-card__decor--expense {
		background: rgba(92, 125, 245, 0.14);
	}

	.records-heading {
		margin-top: 34rpx;
		display: flex;
		align-items: center;
		justify-content: space-between;
	}

	.records-heading__title {
		font-size: 34rpx;
		line-height: 44rpx;
		font-weight: 800;
		color: #15161a;
	}

	.records-heading__more {
		min-height: 52rpx;
		padding-left: 20rpx;
		display: flex;
		align-items: center;
	}

	.records-heading__more--hover {
		opacity: 0.78;
	}

	.records-heading__more-text {
		font-size: 26rpx;
		line-height: 34rpx;
		color: #8e94a3;
	}

	.records-card {
		margin-top: 18rpx;
		padding: 20rpx 16rpx 12rpx;
		border-radius: 20rpx;
		background: #ffffff;
		box-shadow: 0 10rpx 30rpx rgba(15, 23, 42, 0.04);
		box-sizing: border-box;
	}

	.filter-tabs {
		height: 64rpx;
		padding: 0 4rpx;
		border-radius: 999rpx;
		background: #f7f7f7;
		display: flex;
		align-items: center;
		box-sizing: border-box;
	}

	.filter-tab {
		flex: 1;
		min-width: 0;
		height: 56rpx;
		border-radius: 999rpx;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.filter-tab--active {
		background: var(--theme-gradient, linear-gradient(135deg, #ea580c 0%, #fb923c 100%));
		box-shadow: 0 8rpx 18rpx var(--brand-shadow, rgba(234, 88, 12, 0.18));
	}

	.filter-tab--hover {
		opacity: 0.86;
	}

	.filter-tab__text {
		font-size: 28rpx;
		line-height: 36rpx;
		font-weight: 600;
		color: #8d92a0;
		white-space: nowrap;
	}

	.filter-tab__text--active {
		color: #ffffff;
		font-weight: 700;
	}

	.record-item {
		padding: 28rpx 4rpx;
		display: flex;
		align-items: center;
		justify-content: space-between;
		box-sizing: border-box;
	}

	.record-item--hover {
		opacity: 0.86;
	}

	.record-item--border {
		border-bottom: 1rpx solid #f1f2f5;
	}

	.record-item__left {
		flex: 1;
		min-width: 0;
		display: flex;
		align-items: center;
	}

	.record-item__icon {
		width: 58rpx;
		height: 58rpx;
		border-radius: 14rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		flex-shrink: 0;
	}

	.record-item__icon--income {
		background: #fff4eb;
	}

	.record-item__icon--expense {
		background: #f4f5f7;
	}

	.record-item__meta {
		flex: 1;
		min-width: 0;
		margin-left: 20rpx;
	}

	.record-item__title {
		display: block;
		font-size: 28rpx;
		line-height: 38rpx;
		font-weight: 700;
		color: #111318;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.record-item__time {
		display: block;
		margin-top: 6rpx;
		font-size: 24rpx;
		line-height: 32rpx;
		color: #979dab;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.record-item__amount {
		margin-left: 18rpx;
		font-size: 34rpx;
		line-height: 42rpx;
		font-weight: 800;
		white-space: nowrap;
	}

	.record-item__amount--plus {
		color: var(--brand-primary, #ea580c);
	}

	.record-item__amount--minus {
		color: #111318;
	}

	.empty-state {
		padding: 76rpx 32rpx 70rpx;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
	}

	.empty-state__icon {
		width: 96rpx;
		height: 96rpx;
		border-radius: 50%;
		background: #f7f8fb;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.empty-state__text {
		margin-top: 20rpx;
		font-size: 24rpx;
		line-height: 34rpx;
		color: #9ca3af;
	}

	.record-more {
		display: flex;
		justify-content: center;
		padding: 34rpx 0 4rpx;
	}

	.record-more__text {
		font-size: 26rpx;
		line-height: 34rpx;
		color: #a9acb8;
	}

	.gift-banner {
		position: relative;
		margin-top: 24rpx;
		min-height: 136rpx;
		padding: 28rpx 148rpx 24rpx 30rpx;
		border-radius: 16rpx;
		overflow: hidden;
		background: linear-gradient(105deg, #ff3468 0%, #ff643b 48%, #ff8b39 100%);
		box-shadow: 0 12rpx 28rpx rgba(255, 82, 43, 0.16);
		box-sizing: border-box;
	}

	.gift-banner--hover {
		opacity: 0.9;
	}

	.gift-banner__content {
		position: relative;
		z-index: 2;
	}

	.gift-banner__title {
		display: block;
		font-size: 34rpx;
		line-height: 44rpx;
		font-weight: 800;
		color: #ffffff;
	}

	.gift-banner__desc {
		display: block;
		margin-top: 8rpx;
		font-size: 24rpx;
		line-height: 34rpx;
		color: rgba(255, 255, 255, 0.9);
	}

	.gift-banner__products {
		position: absolute;
		right: 92rpx;
		bottom: 16rpx;
		z-index: 1;
		width: 170rpx;
		height: 86rpx;
	}

	.gift-banner__product {
		position: absolute;
		bottom: 0;
		border-radius: 18rpx;
		box-shadow: 0 8rpx 18rpx rgba(148, 47, 10, 0.18);
	}

	.gift-banner__product--bag {
		left: 0;
		width: 58rpx;
		height: 72rpx;
		background: #b2312a;
	}

	.gift-banner__product--box {
		left: 44rpx;
		width: 72rpx;
		height: 82rpx;
		background: #fff3dd;
	}

	.gift-banner__product--bottle {
		right: 0;
		width: 48rpx;
		height: 78rpx;
		background: #ffd4be;
	}

	.gift-banner__go {
		position: absolute;
		right: 28rpx;
		top: 34rpx;
		z-index: 2;
		width: 78rpx;
		height: 78rpx;
		border-radius: 50%;
		background: #fff0c7;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.gift-banner__go-text {
		font-size: 32rpx;
		line-height: 40rpx;
		font-weight: 800;
		color: #ff6130;
	}

	.safe-bottom {
		height: 32rpx;
	}

	/* #ifdef H5 */
	.safe-bottom {
		height: calc(32rpx + var(--window-bottom, 0px));
	}

	/* #endif */

	/* #ifdef MP-WEIXIN */
	.safe-bottom {
		height: calc(32rpx + env(safe-area-inset-bottom));
	}

	/* #endif */
</style>
