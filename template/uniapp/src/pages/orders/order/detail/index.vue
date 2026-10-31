<template>
	<page-meta :page-style="themePageStyle"></page-meta>
	<view class="order-detail-page">
		<image class="order-detail-page__hero-bg" src="./../../static/order-detail-hero-bg.png" mode="aspectFill">
		</image>
		<view class="order-detail-page__hero-mask"></view>
		<view class="order-detail-page__safe-top">
			<app-safe-top title="订单详情" background="transparent" title-color="#171717" icon-color="#171717">
			</app-safe-top>
		</view>

		<scroll-view scroll-y class="order-detail-scroll" refresher-enabled :refresher-triggered="refreshing"
			@refresherrefresh="handleRefresh">
			<view class="order-detail-content">
				<view v-if="loading && !detailReady" class="page-loading">
					<six-petal-spiral />
				</view>

				<view v-else-if="loadError && !detailReady" class="page-error">
					<image class="page-error__image" :src="errorImage" mode="aspectFit"></image>
					<text class="page-error__title">订单详情加载失败</text>
					<text class="page-error__desc">{{ loadError }}</text>
					<view class="page-error__btn" @click="handleRefresh">重新加载</view>
				</view>

				<block v-else-if="detailReady">
					<view class="status-card">
						<view class="status-card__main">
							<text class="status-card__title">{{ detail.displayStatusText }}</text>
							<text v-for="line in statusHeroDescriptions" :key="line" class="status-card__desc">
								{{ line }}
							</text>
						</view>
						<image class="status-card__illustration" :src="statusHeroImage" mode="aspectFit"></image>
					</view>

					<view v-if="!isVirtualOrder" class="address-card">
						<view class="address-card__icon-wrap">
							<text class="iconfont icon-locationfill address-card__icon"></text>
						</view>
						<view class="address-card__body">
							<view class="address-card__contact">
								<text class="address-card__name">{{ detail.address.realName || '暂无收货人' }}</text>
								<text class="address-card__phone">{{ maskedPhone }}</text>
							</view>
							<text class="address-card__text">{{ addressText }}</text>
						</view>
					</view>

					<view v-if="detail.childOrders && detail.childOrders.length" class="split-order-list">
						<view v-for="child in detail.childOrders" :key="child.orderId"
							class="goods-card split-order-card" @click="handleOpenChildOrder(child)">
							<view class="goods-card__header split-order-card__header">
								<view class="goods-card__shop-wrap" @click.stop="handleGoShop(child.merchantId)">
									<text class="iconfont icon-shop goods-card__shop-icon"></text>
									<text class="goods-card__shop-name">{{ child.merchantName }}</text>
								</view>
								<text class="split-order-card__status">{{ child.statusText }}</text>
							</view>

							<view class="goods-list">
								<view v-for="(goods, index) in child.goodsList" :key="goods.orderGoodsId || index"
									class="goods-item" hover-class="goods-item--hover"
									@click.stop="handleGoodsClick(goods, child.orderType)">
									<view class="goods-item__image-wrap">
										<image v-if="goods.image" class="goods-item__image" :src="goods.image"
											mode="aspectFill"></image>
										<view v-else class="goods-item__image-placeholder">
											<text class="iconfont icon-pic goods-item__image-placeholder-icon"></text>
										</view>
										<view class="goods-item__type-badge">
											<text class="goods-item__type-text">{{ child.orderTypeLabel }}</text>
										</view>
									</view>

									<view class="goods-item__body">
										<text class="goods-item__title">{{ goods.name }}</text>
										<text v-if="goods.skuText" class="goods-item__sku">{{ goods.skuText }}</text>
										<view class="goods-item__meta">
											<view class="goods-item__price-wrap">
												<text v-if="goods.useMemberPrice" class="goods-item__vip-tag">VIP</text>
												<text class="goods-item__price">¥
													{{ formatAmount(goods.displayPrice) }}</text>
												<text v-if="goods.useMemberPrice" class="goods-item__regular-price">¥
													{{ formatAmount(goods.price) }}</text>
											</view>
											<text class="goods-item__qty">x {{ goods.quantity }}</text>
										</view>
									</view>
								</view>
							</view>

							<view class="split-order-card__footer">
								<text class="split-order-card__summary">共{{ child.goodsTotalNum }}件</text>
								<view class="split-order-card__pay">
									<text class="split-order-card__pay-label">实付</text>
									<text class="split-order-card__pay-amount">¥
										{{ formatAmount(child.payAmount) }}</text>
								</view>
							</view>
						</view>
					</view>

					<view v-else class="goods-card">
						<view class="goods-card__header">
							<view class="goods-card__shop-wrap" @click.stop="handleGoShop(detail.merchantId)">
								<text class="iconfont icon-shop goods-card__shop-icon"></text>
								<text class="goods-card__shop-name">{{ detail.merchantName }}</text>
							</view>
						</view>

						<view class="goods-list">
							<view v-for="(goods, index) in detail.goodsList" :key="goods.orderGoodsId || index"
								class="goods-item" hover-class="goods-item--hover"
								@click="handleGoodsClick(goods, detail.orderType)">
								<view class="goods-item__image-wrap">
									<image v-if="goods.image" class="goods-item__image" :src="goods.image"
										mode="aspectFill"></image>
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
										<view class="goods-item__price-wrap">
											<text v-if="goods.useMemberPrice" class="goods-item__vip-tag">VIP</text>
											<text class="goods-item__price">¥
												{{ formatAmount(goods.displayPrice) }}</text>
											<text v-if="goods.useMemberPrice" class="goods-item__regular-price">¥
												{{ formatAmount(goods.price) }}</text>
										</view>
										<text class="goods-item__qty">x {{ goods.quantity }}</text>
									</view>
									<view v-if="goods.actionButtons.length" class="goods-item__actions">
										<view v-for="action in goods.actionButtons" :key="action.key"
											:class="['goods-item__action', { 'goods-item__action--primary': action.primary }]"
											hover-class="goods-item__action--hover"
											@click.stop="handleGoodsAction(goods, action.key)">
											<text class="goods-item__action-text">{{ action.text }}</text>
										</view>
									</view>
								</view>
							</view>
						</view>
					</view>

					<view class="fee-card">
						<view v-for="(item, index) in feeRows" :key="item.label" class="fee-row"
							:class="{ 'fee-row--last': index === feeRows.length - 1 }">
							<text
								:class="['fee-row__label', { 'fee-row__label--total': item.isTotal }]">{{ item.label }}</text>
							<text :class="[
                  'fee-row__value',
                  {
                    'fee-row__value--highlight': item.highlight,
                    'fee-row__value--total': item.isTotal
                  }
                ]">
								{{ item.value }}
							</text>
						</view>
					</view>

					<view class="info-card">
						<view class="info-row">
							<text class="info-row__label">订单编号</text>
							<view class="info-row__value-wrap">
								<text class="info-row__value">{{ detail.orderSn }}</text>
								<view class="info-row__copy" @click="handleCopyOrderSn">复制</view>
							</view>
						</view>
						<view class="info-row">
							<text class="info-row__label">下单时间</text>
							<text class="info-row__value">{{ detail.createTime || '--' }}</text>
						</view>
						<view class="info-row">
							<text class="info-row__label">支付方式</text>
							<text class="info-row__value">{{ detail.payTypeDisplay }}</text>
						</view>
					</view>

				</block>
			</view>
		</scroll-view>

		<view v-if="detailReady" class="footer-bar">
			<view class="footer-bar__inner">
				<view v-for="action in footerActions" :key="action.key"
					:class="['footer-action', { 'footer-action--primary': action.primary }]"
					@click="handleFooterAction(action)">
					<text :class="['iconfont', action.icon, 'footer-action__icon']"></text>
					<text class="footer-action__text">{{ action.text }}</text>
				</view>
			</view>
		</view>
	</view>
</template>

<script setup>
import { themeState } from '@/utils/theme'
	import {
		computed,
		ref
	} from 'vue'
	import {
		onLoad
	} from '@dcloudio/uni-app'
	import {
		cancelOrder,
		getOrderDetail,
		receiptOrder
	} from '@/api/order'
	import {
		resolveAssetUrl
	} from '@/utils/runtime_config'
	import {
		navigateToMerchantHome
	} from '@/utils/merchant'
	import {
		getStatusImageUrl
	} from '@/utils/status_images'
	import {
		navigateToOrderGoods
	} from '@/utils/order_goods'
	import statusPendingImage from '../../static/order-detail-status-pending.png'
	import statusUnshippedImage from '../../static/order-detail-status-unshipped.png'
	import statusShippedImage from '../../static/order-detail-status-shipped.png'
	import statusReviewImage from '../../static/order-detail-status-review.png'
	import statusCompletedImage from '../../static/order-detail-status-completed.png'
	import statusCancelledImage from '../../static/order-detail-status-cancelled.png'
	import statusRefundingImage from '../../static/order-detail-status-refunding.png'
	import statusRefundedImage from '../../static/order-detail-status-refunded.png'

	const STATUS_HERO_IMAGE_MAP = Object.freeze({
		pending: statusPendingImage,
		unshipped: statusUnshippedImage,
		shipped: statusShippedImage,
		review: statusReviewImage,
		completed: statusCompletedImage,
		cancelled: statusCancelledImage,
		refunding: statusRefundingImage,
		refunded: statusRefundedImage,
		refundRejected: statusCancelledImage
	})

	defineOptions({
		name: 'OrderDetailPage'
	})

	const loading = ref(false)
	const refreshing = ref(false)
	const actionLoading = ref(false)
	const loadError = ref('')
	const orderId = ref(0)
	const orderSn = ref('')
	const detail = ref(null)
	const errorImage = ref(getStatusImageUrl('no_order.png'))

	const detailReady = computed(() => {
		return !!(detail.value && Object.keys(detail.value).length)
	})
	const isVirtualOrder = computed(() => {
		if (!detail.value) {
			return false
		}
		return detail.value.orderType === 1 || detail.value.orderType === 3
	})
	const goodsTypeLabel = computed(() => {
		if (!detail.value) {
			return ''
		}
		const map = {
			1: '内容',
			2: '商品',
			3: '专题'
		}
		return map[detail.value.orderType] || ''
	})
	const isPlatformOrder = computed(() => {
		if (!detail.value) {
			return false
		}

		return Number(detail.value.merchantId || 0) <= 0
	})
	const orderProviderLabel = computed(() => {
		return isPlatformOrder.value ? '平台' : '商家'
	})
	const addressText = computed(() => {
		if (!detail.value || !detail.value.address) {
			return '暂无收货地址'
		}

		const address = detail.value.address
		const parts = [address.province, address.city, address.district, address.detail]
			.map((item) => String(item || '').trim())
			.filter(Boolean)

		return parts.join(' ') || address.userAddress || '暂无收货地址'
	})
	const maskedPhone = computed(() => {
		const phone = detail.value && detail.value.address ? String(detail.value.address.userPhone || '') : ''
		if (phone.length < 7) {
			return phone || '--'
		}

		return `${phone.slice(0, 3)} **** ${phone.slice(-4)}`
	})
	const feeRows = computed(() => {
		if (!detail.value) {
			return []
		}

		const rows = [{
			label: '商品总额',
			value: `¥ ${formatAmount(detail.value.goodsAmount)}`,
			highlight: false,
			isTotal: false
		}]

		if (!isVirtualOrder.value) {
			rows.push({
				label: '运费',
				value: `¥ ${formatAmount(detail.value.postage)}`,
				highlight: false,
				isTotal: false
			})
		}

		if (detail.value.memberDiscountAmount > 0) {
			rows.push({
				label: 'VIP会员优惠',
				value: `-¥ ${formatAmount(detail.value.memberDiscountAmount)}`,
				highlight: true,
				isTotal: false
			})
		}

		rows.push({
			label: '优惠券抵扣',
			value: `-¥ ${formatAmount(detail.value.couponAmount)}`,
			highlight: detail.value.couponAmount > 0,
			isTotal: false
		}, {
			label: '积分抵扣',
			value: `-¥ ${formatAmount(detail.value.pointsAmount)}`,
			highlight: detail.value.pointsAmount > 0,
			isTotal: false
		}, {
			label: '实付金额',
			value: `¥ ${formatAmount(detail.value.payAmount)}`,
			highlight: true,
			isTotal: true
		})

		return rows
	})
	const statusDescription = computed(() => {
		if (!detail.value) {
			return ''
		}

		const descriptionMap = {
			pending: '订单已提交成功，请尽快完成支付',
			unshipped: '您的订单正在进行出库准备',
			shipped: '商家已完成发货，请留意物流动态',
			review: '商品已签收，期待您的真实评价',
			completed: '订单已完成，感谢您的信任与支持',
			cancelled: '当前订单已取消，如有需要可重新下单',
			refunding: '退款申请已提交，平台正在处理中',
			refunded: '退款已完成，资金将按原路径退回',
			refundRejected: '退款申请未通过，如有疑问可联系客服'
		}

		return descriptionMap[detail.value.uiStatus] || '订单状态更新中'
	})
	const statusHeroDescriptions = computed(() => {
		if (!detail.value) {
			return []
		}

		const provider = orderProviderLabel.value
		const descriptionMap = {
			pending: ['请尽快完成支付'],
			unshipped: [`${provider}正在备货中`],
			shipped: ['请留意物流动态'],
			review: ['期待您的评价'],
			completed: ['感谢您的信任'],
			cancelled: ['可重新下单'],
			refunding: ['退款审核中'],
			refunded: ['退款将原路退回'],
			refundRejected: ['可联系客服处理']
		}

		return descriptionMap[detail.value.uiStatus] || ['订单状态更新中']
	})
	const statusHeroImage = computed(() => {
		if (!detail.value) {
			return STATUS_HERO_IMAGE_MAP.pending
		}

		return STATUS_HERO_IMAGE_MAP[detail.value.uiStatus] || STATUS_HERO_IMAGE_MAP.pending
	})
	const statusHeroAction = computed(() => {
		if (!detail.value) {
			return null
		}

		return getPrimaryAction(detail.value)
	})
	const statusHeroButtonText = computed(() => {
		if (!statusHeroAction.value) {
			return ''
		}

		const textMap = {
			pay: '去支付',
			receipt: '确认收货',
			review: detail.value && detail.value.actions && detail.value.actions.has_reviewed ? '查看评价' : '去评价',
			refund: '申请售后'
		}

		return textMap[statusHeroAction.value.key] || statusHeroAction.value.text
	})
	const latestLogisticsText = computed(() => {
		if (!detail.value) {
			return ''
		}

		const logisticsMap = {
			pending: '最新的订单信息：请尽快完成支付，系统将为您保留库存',
			unshipped: '最新的物流信息：商家正在分拣商品，预计今日揽收',
			shipped: '最新的物流信息：包裹已由快递揽收，请耐心等待派送',
			review: '最新的订单信息：商品已签收，欢迎分享真实体验',
			completed: '最新的订单信息：您已确认收货，欢迎再次选购',
			cancelled: '最新的订单信息：当前订单已取消，如有疑问可联系客服',
			refunding: '最新的售后信息：退款申请已提交，请等待平台审核',
			refunded: '最新的售后信息：退款已完成，预计 1-3 个工作日到账',
			refundRejected: '最新的售后信息：退款申请未通过，可联系客服了解原因'
		}

		return logisticsMap[detail.value.uiStatus] || '最新订单状态同步中'
	})
	const statusIconClass = computed(() => {
		if (!detail.value) {
			return 'icon-order'
		}

		const iconMap = {
			pending: 'icon-pay',
			unshipped: 'icon-countdown',
			shipped: 'icon-send',
			review: 'icon-evaluate',
			completed: 'icon-roundcheckfill',
			cancelled: 'icon-roundclosefill',
			refunding: 'icon-refund',
			refunded: 'icon-refund',
			refundRejected: 'icon-refund'
		}

		return iconMap[detail.value.uiStatus] || 'icon-order'
	})
	const footerActions = computed(() => {
		if (!detail.value) {
			return []
		}

		const actions = [{
			key: 'service',
			text: '联系客服',
			icon: 'icon-comment',
			primary: false
		}]

		// 物流仅可在发货后、订单无退款且未完成时查看
		const canViewLogistics = ['shipped', 'review'].includes(detail.value.uiStatus)
		if (!isVirtualOrder.value && canViewLogistics) {
			actions.push({
				key: 'logistics',
				text: '查看物流',
				icon: 'icon-send',
				primary: false
			})
		}

		const primaryAction = getPrimaryAction(detail.value)
		if (primaryAction) {
			actions.push(primaryAction)
		}

		return actions
	})

	onLoad((options = {}) => {
		initQuery(options)

		if (!hasOrderQuery()) {
			loadError.value = '缺少订单参数，无法加载订单详情'
			return
		}

		fetchOrderDetail()
	})

	/**
	 * 解析页面入参。
	 *
	 * @param {Object} options 页面路由参数。
	 */
	function initQuery(options = {}) {
		const parsedOrderId = Number(options.id || options.order_id || 0)
		const parsedOrderSn = String(options.order_sn || options.orderSn || '').trim()

		orderId.value = Number.isNaN(parsedOrderId) ? 0 : parsedOrderId
		orderSn.value = parsedOrderSn
	}

	/**
	 * 判断当前是否存在有效订单查询参数。
	 *
	 * @returns {boolean}
	 */
	function hasOrderQuery() {
		return !!(orderId.value || orderSn.value)
	}

	/**
	 * 下拉刷新 / 主动刷新。
	 */
	function handleRefresh() {
		refreshing.value = true
		if (!hasOrderQuery()) {
			loadError.value = '缺少订单参数，无法加载订单详情'
			refreshing.value = false
			return
		}

		fetchOrderDetail()
	}

	/**
	 * 获取订单详情。
	 */
	async function fetchOrderDetail() {
		if (loading.value) {
			return
		}

		loading.value = true
		loadError.value = ''

		try {
			const params = orderSn.value ? {
				order_sn: orderSn.value
			} : {
				order_id: orderId.value
			}
			const res = await getOrderDetail(params)

			if (res.code === 200 && res.data) {
				detail.value = normalizeOrderDetail(res.data)
				return
			}

			loadError.value = res.msg || '获取订单详情失败'
		} catch (error) {
			console.error('获取订单详情失败', error)
			loadError.value = '获取订单详情失败，请稍后重试'
		} finally {
			loading.value = false
			refreshing.value = false
		}
	}

	/**
	 * 规范化订单详情数据，统一前端字段结构。
	 *
	 * @param {Object} payload 接口返回原始订单详情。
	 * @returns {Object}
	 */
	function normalizeOrderDetail(payload = {}) {
		const goods = Array.isArray(payload.goods) ? payload.goods : []
		const orderType = Number(payload.order_type || 0)
		const goodsList = goods.map((item) => normalizeGoodsItem({
			...item,
			order_type: item.order_type || orderType
		}))
		const childOrders = Array.isArray(payload.children) ?
			payload.children.map((item) => normalizeChildOrder(item)) : []

		const address = payload.address && typeof payload.address === 'object' ? payload.address : {}
		const uiStatus = resolveUiStatus(payload)
		const memberDiscountAmount = Number(payload.member_discount_amount || 0)
		const pointsAmount = Number(payload.points_deduct_amount || 0)
		const explicitCouponAmount = Number(payload.platform_coupon_amount || 0) +
			Number(payload.merchant_coupon_amount || 0)
		const couponAmount = explicitCouponAmount > 0 ?
			explicitCouponAmount :
			Math.max(0, Number(payload.discount_amount || 0) - memberDiscountAmount - pointsAmount)

		return {
			orderId: Number(payload.order_id || 0),
			orderSn: String(payload.order_sn || ''),
			merchantId: Number(payload.merchant_id || 0),
			merchantName: String(payload.merchant_name || '平台自营'),
			uiStatus,
			displayStatusText: resolveUiStatusText(uiStatus, payload),
			payAmount: Number(payload.pay_amount || 0),
			goodsAmount: resolveGoodsAmount(payload, goodsList),
			couponAmount,
			memberDiscountAmount,
			pointsAmount,
			postage: Number(payload.postage || 0),
			payType: String(payload.pay_type || ''),
			payTypeText: String(payload.pay_type_text || '未支付'),
			payTypeDisplay: resolvePayTypeDisplay(payload),
			createTime: String(payload.create_time || ''),
			payTime: String(payload.pay_time || ''),
			deliveryTime: String(payload.delivery_time || ''),
			receiveTime: String(payload.receive_time || ''),
			finishTime: String(payload.finish_time || ''),
			remark: String(payload.remark || ''),
			actions: payload.actions && typeof payload.actions === 'object' ? payload.actions : {},
			goodsList,
			childOrders,
			goodsTotalNum: Number(payload.goods_total_num || goodsList.reduce((sum, item) => sum + item.quantity, 0)),
			orderType,
			address: {
				realName: String(address.real_name || ''),
				userPhone: String(address.user_phone || ''),
				province: String(address.province || ''),
				city: String(address.city || ''),
				district: String(address.district || ''),
				detail: String(address.detail || ''),
				userAddress: String(address.user_address || '')
			}
		}
	}

	/**
	 * 获取界面显示状态。
	 *
	 * @param {Object} payload 接口返回原始订单详情。
	 * @returns {string}
	 */
	function resolveUiStatus(payload = {}) {
		const rawStatusText = String(payload.status_text || '').trim()
		const hasDelivery = !!payload.delivery_time
		const hasReceive = !!payload.receive_time
		const orderType = Number(payload.order_type || 0)
		const isGoodsOrder = orderType === 2
		const actions = payload.actions && typeof payload.actions === 'object' ? payload.actions : {}

		if (rawStatusText.indexOf('拒绝退款') !== -1 || rawStatusText.indexOf('退款拒绝') !== -1) {
			return 'refundRejected'
		}
		if (rawStatusText.indexOf('已取消') !== -1 || rawStatusText.indexOf('取消') !== -1) {
			return 'cancelled'
		}
		if (rawStatusText.indexOf('退款审核') !== -1 || rawStatusText.indexOf('退款中') !== -1) {
			return 'refunding'
		}
		if (rawStatusText.indexOf('退款成功') !== -1 || rawStatusText.indexOf('已退款') !== -1) {
			return 'refunded'
		}
		if (rawStatusText.indexOf('待评价') !== -1 || actions.can_review) {
			return 'review'
		}
		if (rawStatusText.indexOf('已完成') !== -1 || rawStatusText.indexOf('订单完成') !== -1 || rawStatusText.indexOf(
				'交易完成') !== -1) {
			return 'completed'
		}
		if (rawStatusText.indexOf('待收货') !== -1 || rawStatusText.indexOf('已发货') !== -1) {
			return 'shipped'
		}
		if (rawStatusText.indexOf('发货中') !== -1 || rawStatusText.indexOf('待发货') !== -1) {
			return 'unshipped'
		}
		if (rawStatusText.indexOf('待支付') !== -1 || rawStatusText.indexOf('待付款') !== -1 || rawStatusText.indexOf('未支付') !==
			-1) {
			return 'pending'
		}
		if (rawStatusText === '已支付' && isGoodsOrder && hasDelivery && !hasReceive) {
			return 'shipped'
		}
		if (rawStatusText === '已支付' && isGoodsOrder) {
			return 'unshipped'
		}

		return 'pending'
	}
	/**
	 * 规范化订单商品。
	 *
	 * @param {Object} item 接口返回商品行。
	 * @returns {Object}
	 */
	function normalizeGoodsItem(item = {}) {
		const actions = item.actions && typeof item.actions === 'object' ? item.actions : {}
		const price = Number(item.price || 0)
		const memberPrice = Number(item.member_price || item.effective_price || price)
		const useMemberPrice = (item.use_member_price === true || Number(item.use_member_price || 0) === 1) &&
			memberPrice >= 0 &&
			memberPrice < price
		const normalized = {
			orderGoodsId: Number(item.order_goods_id || 0),
			goodsId: Number(item.goods_id || 0),
			orderType: Number(item.order_type || 0),
			goodsType: Number(item.goods_type || 0),
			contentType: Number(item.content_type || 0),
			detailUrl: String(item.detail_url || ''),
			name: String(item.goods_name || ''),
			image: resolveGoodsImage(item.goods_image),
			skuText: resolveGoodsSkuText(item),
			subtitle: String(item.subtitle || item.sub_title || item.description || '').trim(),
			quantity: Number(item.num || 0),
			price,
			memberPrice,
			displayPrice: useMemberPrice ? memberPrice : price,
			useMemberPrice,
			canRefund: !!(item.can_refund || actions.can_refund),
			canReview: !!(item.can_review || actions.can_review),
			hasReviewed: !!(item.has_reviewed || actions.has_reviewed)
		}
		normalized.actionButtons = buildGoodsActionButtons(normalized)

		return normalized
	}

	/**
	 * 规范化主支付单下的子订单。
	 *
	 * @param {Object} payload 子订单接口数据。
	 * @returns {Object}
	 */
	function normalizeChildOrder(payload = {}) {
		const goods = Array.isArray(payload.goods) ?
			payload.goods :
			(Array.isArray(payload.goods_preview) ? payload.goods_preview : [])
		const orderType = Number(payload.order_type || 0)
		const goodsList = goods.map((item) => normalizeGoodsItem({
			...item,
			order_type: item.order_type || orderType
		}))
		const typeMap = {
			1: '内容',
			2: '商品',
			3: '专题'
		}

		return {
			orderId: Number(payload.order_id || 0),
			orderSn: String(payload.order_sn || ''),
			merchantId: Number(payload.merchant_id || 0),
			merchantName: String(payload.merchant_name || '平台自营'),
			statusText: String(payload.status_text || ''),
			payAmount: Number(payload.pay_amount || 0),
			orderType,
			orderTypeLabel: typeMap[orderType] || '',
			goodsList,
			goodsTotalNum: Number(payload.goods_total_num || goodsList.reduce((sum, item) => sum + item.quantity, 0))
		}
	}

	/**
	 * 获取界面状态文案。
	 *
	 * @param {string} uiStatus 页面状态标识。
	 * @param {Object} payload 接口返回原始订单详情。
	 * @returns {string}
	 */
	function resolveUiStatusText(uiStatus = '', payload = {}) {
		const rawStatusText = String(payload.status_text || '').trim()
		if (rawStatusText && rawStatusText !== '已支付') {
			return rawStatusText
		}

		const statusMap = {
			pending: '待支付',
			unshipped: '待发货',
			shipped: '待收货',
			review: '待评价',
			completed: '已完成',
			cancelled: '已取消',
			refunding: '退款中',
			refunded: '已退款',
			refundRejected: '退款未通过'
		}

		return statusMap[uiStatus] || '订单状态'
	}

	/**
	 * 解析支付方式展示文案。
	 *
	 * @param {Object} payload 接口返回原始订单详情。
	 * @returns {string}
	 */
	function resolvePayTypeDisplay(payload = {}) {
		const payType = String(payload.pay_type || '')
		const payTypeText = String(payload.pay_type_text || '未支付')

		if (payType === 'wechat' || payTypeText.indexOf('微信') !== -1) {
			return '微信支付 (WeChat Pay)'
		}
		if (payType === 'alipay' || payTypeText.indexOf('支付宝') !== -1) {
			return '支付宝支付 (Alipay)'
		}

		return payTypeText || '--'
	}

	/**
	 * 解析商品规格文案。
	 *
	 * @param {Object} goods 单个商品数据。
	 * @returns {string}
	 */
	function resolveGoodsSkuText(goods = {}) {
		const skuName = String(goods.sku_name || '').trim()
		return skuName ? `规格：${skuName}` : ''
	}

	/**
	 * 解析商品图片地址。
	 *
	 * @param {string} image 图片路径。
	 * @returns {string}
	 */
	function resolveGoodsImage(image = '') {
		const resolved = resolveAssetUrl(image)
		return resolved || ''
	}

	/**
	 * 解析商品总额。
	 *
	 * @param {Object} payload 订单原始数据。
	 * @param {Array} goodsList 规范化商品数组。
	 * @returns {number}
	 */
	function resolveGoodsAmount(payload = {}, goodsList = []) {
		const originalAmount = Number(payload.original_amount || 0)
		const totalAmount = Number(payload.total_amount || 0)
		if (totalAmount > 0) {
			return totalAmount
		}
		if (originalAmount > 0) {
			return originalAmount
		}

		return goodsList.reduce((sum, item) => sum + item.price * item.quantity, 0)
	}

	/**
	 * 获取底部主按钮。
	 *
	 * @param {Object} detail 订单详情。
	 * @returns {{ key:string, text:string, icon:string, primary:boolean }|null}
	 */
	function getPrimaryAction(detail = {}) {
		const actions = detail.actions || {}
		if (actions.can_pay) {
			return {
				key: 'pay',
				text: '立即支付',
				icon: 'icon-pay',
				primary: true
			}
		}
		if (actions.can_receipt) {
			return {
				key: 'receipt',
				text: '确认收货',
				icon: 'icon-roundcheckfill',
				primary: true
			}
		}

		return null
	}

	/**
	 * 获取单个商品操作按钮。
	 *
	 * @param {Object} goods 规范化后的订单商品。
	 * @returns {Array<{key:string,text:string,primary:boolean}>}
	 */
	function buildGoodsActionButtons(goods = {}) {
		const buttons = []
		if (goods.canRefund) {
			buttons.push({
				key: 'refund',
				text: '申请退款',
				primary: false
			})
		}
		if (goods.canReview) {
			buttons.push({
				key: 'review',
				text: '评价',
				primary: true
			})
		} else if (goods.hasReviewed) {
			buttons.push({
				key: 'review',
				text: '查看评价',
				primary: false
			})
		}

		return buttons
	}

	/**
	 * 顶部更多操作。
	 */
	function handleMore() {
		if (!detailReady.value) {
			return
		}

		const itemList = ['复制订单编号']
		if (detail.value.actions.can_cancel) {
			itemList.push('取消订单')
		}
		if (detail.value.remark) {
			itemList.push('查看备注')
		}

		uni.showActionSheet({
			itemList,
			success: ({
				tapIndex
			}) => {
				const selected = itemList[tapIndex]
				if (selected === '复制订单编号') {
					handleCopyOrderSn()
					return
				}
				if (selected === '取消订单') {
					handleCancelOrder()
					return
				}
				if (selected === '查看备注') {
					uni.showModal({
						title: '订单备注',
						content: detail.value.remark,
						showCancel: false,
						confirmText: '知道了'
					})
				}
			}
		})
	}

	/**
	 * 复制订单编号。
	 */
	function handleCopyOrderSn() {
		if (!detail.value || !detail.value.orderSn) {
			return
		}

		uni.setClipboardData({
			data: detail.value.orderSn,
			success: () => {
				uni.showToast({
					title: '订单编号已复制',
					icon: 'none'
				})
			}
		})
	}

	/**
	 * 处理底部按钮点击。
	 *
	 * @param {Object} action 按钮配置。
	 */
	function handleFooterAction(action = {}) {
		switch (action.key) {
			case 'service':
				handleContactService()
				break
			case 'logistics':
				handleLogistics()
				break
			case 'pay':
				handlePayOrder()
				break
			case 'receipt':
				handleReceiptOrder()
				break
			case 'refund':
				handleRefundOrder()
				break
			case 'review':
				handleReviewOrder()
				break
			default:
				uni.showToast({
					title: '功能开发中',
					icon: 'none'
				})
		}
	}

	/**
	 * 处理订单商品内操作按钮。
	 *
	 * @param {Object} goods 当前订单商品。
	 * @param {string} actionKey 操作标识。
	 */
	function handleGoodsAction(goods = {}, actionKey = '') {
		if (actionKey === 'refund') {
			handleRefundOrder(goods)
			return
		}
		if (actionKey === 'review') {
			handleReviewOrder(goods)
		}
	}

	/**
	 * 处理顶部状态区主操作。
	 */
	function handleStatusHeroAction() {
		if (!statusHeroAction.value) {
			return
		}

		handleFooterAction(statusHeroAction.value)
	}

	/** 联系客服。 */
	function handleContactService() {
		if (!detail.value) {
			return
		}

		const merchantId = Math.max(0, Number(detail.value.merchantId || 0))
		uni.navigateTo({
			url: `/pages/users/customer/index?merchant_id=${merchantId}`
		})
	}

	/**
	 * 进入商户首页。
	 *
	 * @param {number|string} merchantId 商户 ID。
	 * @returns {void}
	 */
	function handleGoShop(merchantId) {
		navigateToMerchantHome(merchantId)
	}

	/**
	 * 进入订单商品对应的内容或专题详情。
	 *
	 * @param {Object} goods 当前订单商品。
	 * @param {number|string} fallbackOrderType 订单类型兜底值。
	 * @returns {void}
	 */
	function handleGoodsClick(goods = {}, fallbackOrderType = 0) {
		navigateToOrderGoods(goods, {
			fallbackOrderType
		})
	}

	/** 查看物流。 */
	function handleLogistics() {
		if (detail.value && detail.value.uiStatus === 'unshipped') {
			uni.showToast({
				title: '商家正在备货中',
				icon: 'none'
			})
			return
		}

		if (!detail.value) {
			return
		}

		const query = detail.value.orderId ?
			`order_id=${detail.value.orderId}` :
			`order_sn=${encodeURIComponent(detail.value.orderSn)}`
		uni.navigateTo({
			url: `/pages/orders/express/index?${query}`
		})
	}

	/** 立即支付。 */
	function handlePayOrder() {
		if (!detail.value) {
			return
		}

		const query = detail.value.orderSn ?
			`order_sn=${encodeURIComponent(detail.value.orderSn)}` :
			`id=${detail.value.orderId}`

		uni.navigateTo({
			url: `/pages/orders/pay/index?${query}`
		})
	}

	/**
	 * 打开子订单详情。
	 *
	 * @param {Object} child 子订单。
	 */
	function handleOpenChildOrder(child = {}) {
		const orderSn = String(child.orderSn || '').trim()
		const orderId = Number(child.orderId || 0)
		const query = orderSn ? `order_sn=${encodeURIComponent(orderSn)}` : `id=${orderId}`

		uni.navigateTo({
			url: `/pages/orders/order/detail/index?${query}`
		})
	}

	/**
	 * 取消订单。
	 */
	function handleCancelOrder() {
		if (!detail.value || !detail.value.orderSn || actionLoading.value) {
			return
		}

		uni.showModal({
			title: '取消订单',
			content: '确定要取消当前订单吗？',
			confirmColor: themeState.primary_color,
			success: async (res) => {
				if (!res.confirm) {
					return
				}

				await runOrderAction(() => cancelOrder({
					order_sn: detail.value.orderSn
				}), '订单已取消')
			}
		})
	}

	/**
	 * 确认收货。
	 */
	function handleReceiptOrder() {
		if (!detail.value || !detail.value.orderSn || actionLoading.value) {
			return
		}

		uni.showModal({
			title: '确认收货',
			content: '请确认您已收到该商品。',
			confirmColor: themeState.primary_color,
			success: async (res) => {
				if (!res.confirm) {
					return
				}

				await runOrderAction(() => receiptOrder({
					order_sn: detail.value.orderSn
				}), '确认收货成功')
			}
		})
	}

	/**
	 * 进入退款申请页。
	 *
	 * @param {Object} goods 当前订单商品。
	 * @returns {void}
	 */
	function handleRefundOrder(goods = null) {
		if (!detail.value || !detail.value.orderSn || actionLoading.value) {
			return
		}
		const targetGoods = goods || {}
		const orderGoodsId = Number(targetGoods.orderGoodsId || 0)
		if (orderGoodsId <= 0) {
			uni.showToast({
				title: '商品信息缺失，无法申请退款',
				icon: 'none'
			})
			return
		}

		const query = buildRefundApplyQuery(targetGoods)
		uni.navigateTo({
			url: `/pages/orders/refund/apply?${query}`
		})
	}

	/**
	 * 构建退款申请页跳转参数。
	 *
	 * @param {Object} goods 当前订单商品。
	 * @returns {string}
	 */
	function buildRefundApplyQuery(goods = {}) {
		const query = {
			order_sn: detail.value.orderSn,
			order_id: detail.value.orderId,
			order_goods_id: Number(goods.orderGoodsId || 0),
			goods_name: String(goods.name || ''),
			sku_text: String(goods.skuText || ''),
			quantity: Math.max(1, Number(goods.quantity || 1)),
			price: Number(goods.displayPrice || goods.price || 0),
			image: String(goods.image || ''),
			order_type: Number(goods.orderType || detail.value.orderType || 0)
		}

		return Object.entries(query)
			.filter(([, value]) => value !== '' && value !== null && value !== undefined)
			.map(([key, value]) => `${key}=${encodeURIComponent(String(value))}`)
			.join('&')
	}

	/**
	 * 跳转订单评价页。
	 */
	function handleReviewOrder(goods = null) {
		if (!detail.value || !detail.value.orderId) {
			return
		}
		const targetGoods = goods || {}
		const orderGoodsId = Number(targetGoods.orderGoodsId || 0)
		if (orderGoodsId <= 0) {
			uni.showToast({
				title: '商品信息缺失，无法评价',
				icon: 'none'
			})
			return
		}

		uni.navigateTo({
			url: `/pages/orders/review/index?orderId=${detail.value.orderId}&orderGoodsId=${orderGoodsId}`
		})
	}

	/**
	 * 执行订单操作并刷新详情。
	 *
	 * @param {Function} action 执行动作。
	 * @param {string} successMessage 成功提示。
	 */
	async function runOrderAction(action, successMessage = '操作成功') {
		if (typeof action !== 'function') {
			return
		}

		actionLoading.value = true
		try {
			const res = await action()
			if (res.code === 200) {
				uni.showToast({
					title: successMessage,
					icon: 'success'
				})
				setTimeout(() => {
					fetchOrderDetail()
				}, 300)
			}
		} catch (error) {
			console.error('订单操作失败', error)
			uni.showToast({
				title: '操作失败，请稍后重试',
				icon: 'none'
			})
		} finally {
			actionLoading.value = false
		}
	}


	/**
	 * 金额格式化。
	 *
	 * @param {number|string} value 金额值。
	 * @returns {string}
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

	.page-top-action {
		width: 64rpx;
		min-height: 64rpx;
		display: flex;
		align-items: center;
		justify-content: center;
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
		padding: 30rpx 20rpx 0rpx;
		overflow: visible;
		box-sizing: border-box;

		&__main {
			position: relative;
			z-index: 2;
			width: 55%;
			min-height: 296rpx;
			top: 40rpx;
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

		&__button {
			margin-top: 36rpx;
			width: 192rpx;
			height: 72rpx;
			border: 2rpx solid rgba(255, 101, 38, 0.58);
			border-radius: 999rpx;
			display: flex;
			align-items: center;
			justify-content: center;
			background: rgba(255, 255, 255, 0.52);
			box-sizing: border-box;
		}

		&__button--hover {
			opacity: 0.86;
		}

		&__button-text {
			font-size: 30rpx;
			line-height: 40rpx;
			font-weight: 700;
			color: var(--brand-primary, #ea580c);
		}

		&__button-icon {
			margin-left: 10rpx;
			font-size: 24rpx;
			line-height: 24rpx;
			color: var(--brand-primary, #ea580c);
		}

		&__illustration {
			position: absolute;
			right: -42rpx;
			top: -8rpx;
			z-index: 1;
			width: 430rpx;
			height: 352rpx;
			pointer-events: none;
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

	.split-order-list {
		margin-top: 24rpx;
	}

	.split-order-card {
		margin-top: 20rpx;

		&:first-child {
			margin-top: 0;
		}

		&__header {
			display: flex;
			align-items: center;
			justify-content: space-between;
		}

		&__status {
			margin-left: 20rpx;
			font-size: 24rpx;
			line-height: 34rpx;
			color: var(--brand-primary, #ea580c);
			flex-shrink: 0;
		}

		&__footer {
			min-height: 76rpx;
			padding: 0 24rpx;
			border-top: 1rpx solid #f1f1f2;
			display: flex;
			align-items: center;
			justify-content: space-between;
		}

		&__summary,
		&__pay-label {
			font-size: 24rpx;
			line-height: 34rpx;
			color: var(--text-secondary, #8b8b97);
		}

		&__pay {
			display: flex;
			align-items: baseline;
		}

		&__pay-amount {
			margin-left: 8rpx;
			font-size: 28rpx;
			line-height: 38rpx;
			font-weight: 700;
			color: var(--brand-primary, #ea580c);
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

		&+& {
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
			display: block;
			width: 100%;
			overflow: hidden;
			font-size: 36rpx;
			line-height: 48rpx;
			font-weight: 700;
			color: var(--text-title, #1a1c1c);
			white-space: nowrap;
			text-overflow: ellipsis;
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

		&__actions {
			margin-top: 22rpx;
			display: flex;
			align-items: center;
			justify-content: flex-end;
			flex-wrap: wrap;
		}

		&__action {
			min-width: 140rpx;
			height: 54rpx;
			margin-left: 16rpx;
			margin-top: 12rpx;
			padding: 0 22rpx;
			border: 1rpx solid #dedee3;
			border-radius: 999rpx;
			display: flex;
			align-items: center;
			justify-content: center;
			background: #ffffff;
			box-sizing: border-box;
		}

		&__action--primary {
			border-color: var(--brand-primary, #ea580c);
			background: var(--brand-primary, #ea580c);
		}

		&__action--hover {
			opacity: 0.78;
		}

		&__action-text {
			font-size: 24rpx;
			line-height: 34rpx;
			color: #4f5560;
		}

		&__action--primary &__action-text {
			color: #ffffff;
			font-weight: 700;
		}

		&__price {
			font-size: 28rpx;
			line-height: 1.1;
			font-weight: 700;
			color: var(--text-title, #1a1c1c);
		}

		&__price-wrap {
			display: flex;
			align-items: flex-end;
			min-width: 0;
		}

		&__vip-tag {
			margin-right: 8rpx;
			padding: 3rpx 6rpx;
			border-radius: 8rpx;
			background: #1a1c1c;
			font-size: 18rpx;
			line-height: 22rpx;
			font-weight: 700;
			color: #ffd76a;
		}

		&__regular-price {
			margin-left: 10rpx;
			font-size: 20rpx;
			line-height: 26rpx;
			color: #aaa5a6;
			text-decoration: line-through;
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

		&+& {
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
