<template>
	<page-meta :page-style="themePageStyle"></page-meta>
	<view class="confirm-order-page">
		<app-safe-top background="#f4f4f4" title="确认订单" :show-back="true" emit-back icon-color="#1a1c1c"
			title-color="#1a1c1c" mp-extra="8rpx" @back="handleBack">
		</app-safe-top>

		<view class="confirm-order-page__content">
			<view v-if="!isVirtualOrder" class="address-card" @click="handleChooseAddress" @tap="handleChooseAddress">
				<view class="address-card__icon-wrap" @click.stop="handleChooseAddress" @tap.stop="handleChooseAddress">
					<text class="iconfont icon-locationfill address-card__icon"></text>
				</view>
				<view class="address-card__body" @click.stop="handleChooseAddress" @tap.stop="handleChooseAddress">
					<view class="address-card__head">
						<text class="address-card__name" @click.stop="handleChooseAddress"
							@tap.stop="handleChooseAddress">{{ address.name }}</text>
						<text class="address-card__phone" @click.stop="handleChooseAddress"
							@tap.stop="handleChooseAddress">{{ address.phone }}</text>
					</view>
					<text class="address-card__detail" @click.stop="handleChooseAddress"
						@tap.stop="handleChooseAddress">{{ address.fullAddress }}</text>
				</view>
				<text class="iconfont icon-right address-card__arrow" @click.stop="handleChooseAddress"
					@tap.stop="handleChooseAddress"></text>
			</view>

			<view v-for="group in groups" :key="group.key" class="goods-card">
				<view class="goods-card__header">
					<view class="goods-card__shop-wrap">
						<text class="iconfont icon-shop goods-card__shop-icon"></text>
						<text class="goods-card__shop-name">{{ group.merchantName }}</text>
					</view>
				</view>

				<view class="goods-card__body">
					<view v-for="item in group.items" :key="item.id" class="goods-item">
						<image class="goods-item__image" :src="item.image" mode="aspectFill"></image>
						<view class="goods-item__info">
							<text class="goods-item__title">{{ item.title }}</text>
							<text class="goods-item__sku">{{ item.sku }}</text>
							<view class="goods-item__footer">
								<view class="goods-item__price-wrap">
									<text v-if="item.useMemberPrice" class="goods-item__vip-tag">VIP</text>
									<text class="goods-item__price">¥{{ formatAmount(item.displayPrice) }}</text>
									<text v-if="item.useMemberPrice"
										class="goods-item__regular-price">¥{{ formatAmount(item.price) }}</text>
								</view>
								<text class="goods-item__count">x{{ item.quantity }}</text>
							</view>
						</view>
					</view>
				</view>

				<view class="goods-card__divider"></view>

				<view class="goods-card__extra">
					<view v-if="!isVirtualOrder" class="info-row" @click="handleChooseAddress" @tap="handleChooseAddress">
						<text class="info-row__label">配送方式</text>
						<view class="info-row__value-wrap">
							<text class="info-row__value" @click.stop="handleChooseAddress"
								@tap.stop="handleChooseAddress">{{ group.shippingText }}</text>
						</view>
					</view>

					<view v-if="Number(group.merchantId || 0) > 0" class="info-row summary-row--clickable"
						@click="handleChooseMerchantCoupon(group)" @tap="handleChooseMerchantCoupon(group)">
						<view class="summary-row__label-wrap">
							<text class="info-row__label">店铺优惠券</text>
							<text v-if="getMerchantCouponCountText(group)"
								class="summary-row__tag">{{ getMerchantCouponCountText(group) }}</text>
						</view>
						<view class="info-row__value-wrap">
							<text class="info-row__value"
								:class="{ 'summary-row__value--discount': getMerchantCouponAmount(group) > 0 }">{{ getMerchantCouponValueText(group) }}</text>
							<text class="iconfont icon-right info-row__arrow"></text>
						</view>
					</view>

					<view class="info-row info-row--remarks">
						<text class="info-row__label">店铺备注</text>
						<input v-model="merchantRemarks[group.merchantId]" class="info-row__input" type="text"
							maxlength="40" :placeholder="group.remarkPlaceholder"
							placeholder-class="info-row__input-placeholder" />
					</view>
				</view>
			</view>

			<view class="summary-card">
				<view class="summary-row">
					<text class="summary-row__label">商品总额</text>
					<text class="summary-row__value">¥{{ itemTotalText }}</text>
				</view>
				<view v-if="!isVirtualOrder" class="summary-row">
					<text class="summary-row__label">运费</text>
					<text class="summary-row__value">¥{{ shippingFeeText }}</text>
				</view>
				<view class="summary-row summary-row--clickable" @click="handleChooseCoupon" @tap="handleChooseCoupon">
					<view class="summary-row__label-wrap">
						<text class="summary-row__label">平台优惠券</text>
						<text v-if="platformCouponCountText"
							class="summary-row__tag">{{ platformCouponCountText }}</text>
					</view>
					<view class="summary-row__action">
						<text class="summary-row__value"
							:class="{ 'summary-row__value--discount': platformCouponAmount > 0 }">{{ platformCouponValueText }}</text>
						<text class="iconfont icon-right summary-row__arrow"></text>
					</view>
				</view>
				<view v-if="points.enabled" class="summary-row summary-row--clickable" @click="handleTogglePoints">
					<view class="summary-row__label-wrap">
						<text class="summary-row__label">积分抵扣</text>
						<text v-if="pointsStatusText"
							class="summary-row__tag summary-row__tag--points">{{ pointsStatusText }}</text>
					</view>
					<view class="summary-row__action summary-row__action--points">
						<view class="points-summary">
							<text class="summary-row__value"
								:class="{ 'summary-row__value--discount': pointsDeductAmount > 0 }">{{ pointsValueText }}</text>
							<text v-if="pointsDetailText" class="points-summary__desc">{{ pointsDetailText }}</text>
						</view>
						<view class="points-switch" :class="{ 'points-switch--active': usePointsEnabled }">
							<view class="points-switch__dot"></view>
						</view>
					</view>
				</view>
				<view v-if="memberDiscountAmount > 0" class="summary-row">
					<view class="summary-row__label-wrap">
						<text class="summary-row__label">VIP会员优惠</text>
						<text class="summary-row__tag">会员价</text>
					</view>
					<text
						class="summary-row__value summary-row__value--discount">-¥{{ memberDiscountAmountText }}</text>
				</view>
			</view>

			<PluginSlot
				name="mobile.order.confirm.sections"
				:context="{ groups, summary, merchant, isVirtualOrder, plugin_payload: pluginPayload, setPluginPayload }"
			/>
		</view>

		<view class="submit-bar">
			<view class="submit-bar__summary">
				<view class="submit-bar__amount-row">
					<text class="submit-bar__label">实付款</text>
					<text class="submit-bar__amount">¥{{ payableAmountText }}</text>
				</view>
				<text class="submit-bar__tip">已优惠 ¥{{ discountAmountText }}</text>
			</view>
			<button class="submit-bar__button" :disabled="submitLoading || loading" @click="handleSubmit">
				{{ submitLoading ? '提交中...' : '提交订单' }}
			</button>
		</view>

		<coupon-popup :visible="showCouponPopup" title="选择优惠券" :items="couponPopupItems"
			:show-actions="false" :selectable="true" :selected-id="currentCouponPickerSelectedId"
			:show-none="true" :none-active="currentCouponPickerSelectedId === 0"
			:none-desc="couponPopupNoneDesc" empty-title="暂无可用优惠券" empty-desc="当前订单暂无可用优惠券"
			@close="handleCloseCouponPopup" @select="handleSelectCoupon" />

		<view v-if="showAddressPopup" class="address-popup-sheet">
			<view class="address-popup-sheet__mask" @click="handleCloseAddressPopup" @tap="handleCloseAddressPopup"></view>
			<view class="address-popup-sheet__panel" @click.stop @tap.stop>
				<view class="address-popup">
					<view class="address-popup__header">
						<text class="address-popup__title">选择收货地址</text>
						<view class="address-popup__close" @click="handleCloseAddressPopup"
							@tap="handleCloseAddressPopup">
							<uni-icons type="closeempty" size="23" color="#777777"></uni-icons>
						</view>
					</view>

					<view class="address-popup__search">
						<view class="address-popup__search-box">
							<uni-icons type="search" size="18" color="#666666"></uni-icons>
							<input v-model="addressKeyword" class="address-popup__search-input" type="text" maxlength="20"
								placeholder="搜索已保存的地址" placeholder-class="address-popup__search-placeholder" />
						</view>
					</view>

					<scroll-view scroll-y class="address-popup__scroll">
						<view class="address-popup__list">
							<view v-for="item in filteredAddressOptions" :key="item.id" class="address-option"
								:class="{ 'address-option--active': item.id === selectedAddressId }"
								@click="handleSelectAddress(item.id)">
								<view class="address-option__top">
									<view class="address-option__name-wrap">
										<text class="address-option__name">{{ item.name }}</text>
										<text v-if="item.isDefault" class="address-option__tag">默认</text>
									</view>
									<view v-if="item.id === selectedAddressId" class="address-option__check">
										<uni-icons type="checkmarkempty" size="18" color="#ffffff"></uni-icons>
									</view>
								</view>

								<view class="address-option__body">
									<text class="address-option__phone">{{ item.phone }}</text>
									<text class="address-option__detail">{{ item.fullAddress }}</text>
								</view>

								<view class="address-option__actions">
									<view class="address-option__action" @click.stop="handleEditAddress(item)">
										<uni-icons type="compose" size="14" color="#4c4546"></uni-icons>
										<text class="address-option__action-text">编辑</text>
									</view>
									<view v-if="item.canShare" class="address-option__action"
										@click.stop="handleShareAddress(item)">
										<uni-icons type="redo" size="14" color="#4c4546"></uni-icons>
										<text class="address-option__action-text">分享</text>
									</view>
								</view>
							</view>

							<view class="address-popup__add" @click="handleAddAddress">
								<uni-icons type="plusempty" size="18" color="#666666"></uni-icons>
								<text class="address-popup__add-text">新增收货地址</text>
							</view>
						</view>
					</scroll-view>

					<view class="address-popup__footer">
						<view class="address-popup__links">
							<text class="address-popup__link"
								@click="handleManageAddress">管理全部{{ addressOptions.length }}个地址</text>
						</view>
						<button class="address-popup__submit" @click="handleConfirmAddress">确认选择</button>
					</view>
				</view>
			</view>
		</view>
	</view>
</template>

<script setup>
import { computed, ref } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import {
	confirmOrder,
	createOrder
} from '@/api/order'
import {
	getAddressList
} from '@/api/address'
import {
	normalizePublicErrorMessage
} from '@/utils/error'
import CouponPopup from '@/components/coupon-popup/index.vue'
import PluginSlot from '@/components/plugin-slot/index.vue'
import {
	createRequestId,
	emitPluginSubmitAfter,
	runPluginSubmitBefore
} from '@/plugins/submit-events.js'

const ORDER_TYPE_GOODS = 2

function parseIds(value = '') {
	if (Array.isArray(value)) {
		return value.map((item) => Number(item || 0)).filter((item) => item > 0)
	}

	return String(value || '')
		.split(',')
		.map((item) => Number(item || 0))
		.filter((item) => item > 0)
}

function normalizeGoodsItem(item = {}) {
	const price = Number(item.price || 0)
	const memberPrice = Number(item.member_price || item.effective_price || price)
	const useMemberPrice = (item.use_member_price === true || Number(item.use_member_price || 0) === 1) &&
		memberPrice >= 0 &&
		memberPrice < price

	return {
		id: Number(item.cart_id || item.id || item.goods_id || 0),
		title: String(item.goods_name || '').trim() || '商品名称待完善',
		sku: String(item.sku_name || item.resource_type_text || item.resource_type || '').trim() || '默认规格',
		price,
		memberPrice,
		displayPrice: useMemberPrice ? memberPrice : price,
		useMemberPrice,
		quantity: Number(item.cart_num || 1),
		image: String(item.goods_image || '').trim(),
		pluginCode: String(item.plugin_code || '').trim().toLowerCase(),
		pluginPayload: item.plugin_payload && typeof item.plugin_payload === 'object' ? item.plugin_payload : {}
	}
}

function normalizeAddress(address = {}) {
	const fullAddress = String(address.user_address || '').trim()
	const detailAddress = fullAddress || [
		address.province,
		address.city,
		address.district,
		address.detail
	].filter(Boolean).join(' ')
	const addressId = Number(address.address_id || address.id || 0)
	return {
		id: addressId,
		name: String(address.real_name || '').trim() || (addressId > 0 ? '收货人' : '请选择收货地址'),
		phone: String(address.user_phone || address.phone || '').trim(),
		fullAddress: detailAddress || (addressId > 0 ? '' : '选择收货地址后计算运费'),
		shortAddress: detailAddress,
		isDefault: Number(address.is_default || 0) === 1,
		canShare: false
	}
}

defineOptions({ name: 'OrderConfirmPage' })

const cartIds = ref([])
const isNew = ref(false)
const platformCouponId = ref(0)
const merchantCouponId = ref(0)
const loading = ref(false)
const submitLoading = ref(false)
const selectedAddressId = ref(0)
const addressOptions = ref([normalizeAddress()])
const groups = ref([])
const merchant = ref({
	name: '平台自营',
	shippingText: '虚拟商品免配送',
	shippingInsurance: '无需运费险',
	remarkPlaceholder: '选填，填写订单备注'
})
const goodsList = ref([])
const summary = ref({
	goods_amount: 0,
	postage: 0,
	discount_amount: 0,
	coupon_amount: 0,
	member_discount_amount: 0,
	points_deduct_amount: 0,
	pay_amount: 0
})
const coupon = ref({
	selected_coupon_id: 0,
	platform_coupon_list: [],
	merchant_coupon_list: [],
	platform_coupon_count: 0,
	merchant_coupon_count: 0
})
const platformCoupon = ref({
	selected_coupon_id: 0,
	platform_coupon_list: [],
	platform_coupon_count: 0,
	coupon_amount: 0
})
const merchantCouponIds = ref({})
const merchantRemarks = ref({})
const couponPickerType = ref('platform')
const couponPickerMerchantId = ref(0)
const points = ref({
	enabled: false,
	available_points: 0,
	max_use_points: 0,
	use_points: 0,
	deduct_amount: 0,
	message: ''
})
const usePointsEnabled = ref(false)
const isVirtualOrder = ref(true)
const hasLoaded = ref(false)
const remark = ref('')
const showCouponPopup = ref(false)
const showAddressPopup = ref(false)
const addressKeyword = ref('')
const pluginPayload = ref({})

const address = computed(() => {
	return addressOptions.value.find((item) => item.id === selectedAddressId.value) || addressOptions.value[0] ||
		normalizeAddress()
})
const filteredAddressOptions = computed(() => {
	const keyword = String(addressKeyword.value || '').trim().toLowerCase()
	if (!keyword) {
		return addressOptions.value
	}

	return addressOptions.value.filter((item) => {
		return [item.name, item.phone, item.fullAddress].some((field) => String(field || '')
			.toLowerCase().includes(keyword))
	})
})
const itemTotal = computed(() => {
	return Number(summary.value.goods_amount || 0)
})
const payableAmount = computed(() => {
	return Number(summary.value.pay_amount || 0)
})
const itemTotalText = computed(() => {
	return formatAmount(itemTotal.value)
})
const shippingFeeText = computed(() => {
	return formatAmount(summary.value.postage || 0)
})
const discountAmountText = computed(() => {
	return formatAmount(summary.value.discount_amount || 0)
})
const discountAmount = computed(() => {
	return Number(summary.value.discount_amount || 0)
})
const memberDiscountAmount = computed(() => {
	return Number(summary.value.member_discount_amount || 0)
})
const memberDiscountAmountText = computed(() => {
	return formatAmount(memberDiscountAmount.value)
})
const selectedCouponId = computed(() => {
	return Number(platformCouponId.value || merchantCouponId.value || 0)
})
const availableCoupons = computed(() => {
	if (couponPickerType.value === 'merchant') {
		const group = findGroupByMerchantId(couponPickerMerchantId.value)
		const coupon = group && group.coupon ? group.coupon : {}
		return Array.isArray(coupon.merchant_coupon_list) ? coupon.merchant_coupon_list : []
	}

	return Array.isArray(platformCoupon.value.platform_coupon_list) ? platformCoupon.value.platform_coupon_list :
		[]
})
const couponPopupItems = computed(() => {
	return availableCoupons.value.map((item) => normalizeCouponPopupItem(item))
})
const couponPopupNoneDesc = computed(() => {
	return couponPickerType.value === 'platform' ? '本次不抵扣平台优惠券' : '本次不抵扣店铺优惠券'
})
const availableCouponCount = computed(() => {
	return Number(coupon.value.platform_coupon_count || 0) + Number(coupon.value.merchant_coupon_count || 0)
})
const couponCountText = computed(() => {
	return availableCouponCount.value > 0 ? availableCouponCount.value + '张可用' : ''
})
const selectedCouponAmount = computed(() => {
	return Number(summary.value.coupon_amount || 0)
})
const couponValueText = computed(() => {
	if (selectedCouponAmount.value > 0) {
		return '-¥' + formatAmount(selectedCouponAmount.value)
	}
	return availableCouponCount.value > 0 ? '请选择' : '暂无可用'
})
const currentCouponPickerSelectedId = computed(() => {
	if (couponPickerType.value === 'merchant') {
		return Number(merchantCouponIds.value[couponPickerMerchantId.value] || 0)
	}

	return Number(platformCouponId.value || 0)
})
const platformCouponCountText = computed(() => {
	const count = Number(platformCoupon.value.platform_coupon_count || 0)
	return count > 0 ? count + '张可用' : ''
})
const platformCouponAmount = computed(() => {
	return Number(summary.value.platform_coupon_amount || platformCoupon.value.coupon_amount || 0)
})
const platformCouponValueText = computed(() => {
	if (platformCouponAmount.value > 0) {
		return '-¥' + formatAmount(platformCouponAmount.value)
	}

	return Number(platformCoupon.value.platform_coupon_count || 0) > 0 ? '请选择' : '暂无可用'
})
const pointsDeductAmount = computed(() => {
	return Number(summary.value.points_deduct_amount || points.value.deduct_amount || 0)
})
const pointsStatusText = computed(() => {
	if (!points.value.enabled) {
		return ''
	}
	return Number(points.value.max_use_points || 0) > 0 ? '可用' : ''
})
const pointsValueText = computed(() => {
	if (!points.value.enabled) {
		return points.value.message || '不可用'
	}

	const usePoints = Number(points.value.use_points || 0)
	const maxUsePoints = Number(points.value.max_use_points || 0)
	if (usePointsEnabled.value && usePoints > 0) {
		return '本次使用 ' + usePoints + ' 积分'
	}

	return maxUsePoints > 0 ? '本次使用 0 积分' : (points.value.message || '暂无可用')
})
const pointsDetailText = computed(() => {
	if (!points.value.enabled) {
		return ''
	}

	const availablePoints = Number(points.value.available_points || 0)
	const usePoints = Number(points.value.use_points || 0)
	const maxUsePoints = Number(points.value.max_use_points || 0)
	if (availablePoints <= 0 && maxUsePoints <= 0) {
		return ''
	}

	if (usePointsEnabled.value && usePoints > 0 && pointsDeductAmount.value > 0) {
		return '当前 ' + availablePoints + ' 积分，抵扣 ¥' + formatAmount(pointsDeductAmount.value)
	}

	return maxUsePoints > 0 ?
		'当前 ' + availablePoints + ' 积分，可用 ' + maxUsePoints + ' 积分' :
		'当前 ' + availablePoints + ' 积分'
})
const isMerchantOrder = computed(() => {
	return Number(coupon.value.merchant_coupon_count || 0) > 0 || Number(merchantCouponId.value || 0) > 0
})
const payableAmountText = computed(() => {
	return formatAmount(payableAmount.value)
})

onLoad((options = {}) => {
	initPage(options)
	initConfirmPage()
})

onShow(() => {
	if (!hasLoaded.value) {
		return
	}

	initConfirmPage()
})

function initPage(options = {}) {
	cartIds.value = parseIds(options.cart_ids || options.cartIds || '')
	isNew.value = Number(options.is_new || options.isNew || 0) === 1
	platformCouponId.value = Number(options.platform_coupon_id || options.platformCouponId || 0)
	merchantCouponId.value = Number(options.merchant_coupon_id || options.merchantCouponId || 0)
}
async function initConfirmPage() {
	await fetchAddressOptions()
	await fetchConfirmOrder()
	hasLoaded.value = true
}
async function fetchAddressOptions() {
	try {
		const res = await getAddressList({}, {
			showError: false
		})
		if (!res || res.code !== 200 || !res.data) {
			return
		}

		const list = Array.isArray(res.data.list) ? res.data.list.map((item) => normalizeAddress(item)) : []
		const defaultAddress = res.data.default_address ? normalizeAddress(res.data.default_address) : null
		addressOptions.value = list
		const selectedExists = addressOptions.value.some((item) => item.id === selectedAddressId.value)
		if (selectedAddressId.value <= 0 || !selectedExists) {
			const selected = defaultAddress || addressOptions.value.find((item) => item.id > 0) || null
			selectedAddressId.value = selected ? selected.id : 0
		}

		if (!isVirtualOrder.value && selectedAddressId.value <= 0) {
			showAddressPopup.value = true
		}
	} catch (error) {
		console.error('[order-confirm] fetchAddressOptions failed', error)
	}
}
function buildOrderPayload() {
	const merchantCouponIdsPayload = groups.value
		.filter((group) => Number(group.merchantId || 0) > 0)
		.map((group) => ({
			merchant_id: Number(group.merchantId || 0),
			coupon_id: Number(merchantCouponIds.value[group.merchantId] || 0)
		}))
	const merchantRemarksPayload = groups.value.map((group) => ({
		merchant_id: Number(group.merchantId || 0),
		remark: String(merchantRemarks.value[group.merchantId] || '').trim()
	}))
	const legacyRemark = merchantRemarksPayload.length === 1 ? merchantRemarksPayload[0].remark : String(remark.value || '')
		.trim()

	return {
		cart_ids: cartIds.value,
		is_new: isNew.value ? 1 : 0,
		platform_coupon_id: platformCouponId.value,
		merchant_coupon_id: merchantCouponId.value,
		merchant_coupon_ids: merchantCouponIdsPayload,
		merchant_remarks: merchantRemarksPayload,
		use_points: usePointsEnabled.value ? Number(points.value.max_use_points || 0) : 0,
		address_id: selectedAddressId.value,
		remark: legacyRemark,
		plugin_payload: pluginPayload.value
	}
}

/**
 * 由插件插槽更新自身命名空间的订单载荷。
 *
 * @param {string} pluginCode 插件编码。
 * @param {Object} value 插件载荷。
 * @returns {void}
 */
function setPluginPayload(pluginCode, value) {
	const code = String(pluginCode || '').trim().toLowerCase()
	if (!code || !value || typeof value !== 'object') return
	pluginPayload.value = { ...pluginPayload.value, [code]: { ...value } }
}
async function fetchConfirmOrder() {
	if (!cartIds.value.length && !isNew.value) {
		uni.showToast({
			title: '请选择要结算的商品',
			icon: 'none'
		})
		return
	}

	loading.value = true
	try {
		const res = await confirmOrder(buildOrderPayload(), {
			showError: false
		})
		if (!res || res.code !== 200 || !res.data) {
			const message = normalizePublicErrorMessage((res && res.msg) || '', '订单信息加载失败')
			if (message.includes('收货地址')) {
				showAddressPopup.value = true
			}
			uni.showToast({
				title: message,
				icon: 'none'
			})
			return
		}

		applyConfirmData(res.data)
	} catch (error) {
		console.error('[order-confirm] fetchConfirmOrder failed', error)
	} finally {
		loading.value = false
	}
}
function applyConfirmData(data = {}) {
	const confirm = data.confirm && typeof data.confirm === 'object' ? data.confirm : {}
	const settlement = data.settlement && typeof data.settlement === 'object' ? data.settlement : {}
	const rawMerchant = confirm.merchant || {}
	const rawSummary = confirm.summary || {}
	const items = Array.isArray(confirm.items) ? confirm.items : []
	const rawGroups = Array.isArray(confirm.groups) && confirm.groups.length ?
		confirm.groups :
		(Array.isArray(settlement.groups) ? settlement.groups : [])
	const firstGroup = rawGroups[0] || {}
	const rawAddress = normalizeAddress(confirm.address || {})
	const rawCoupon = confirm.coupon && typeof confirm.coupon === 'object' ? confirm.coupon : {}
	const rawPlatformCoupon = confirm.platform_coupon && typeof confirm.platform_coupon === 'object' ?
		confirm.platform_coupon :
		{}
	const rawPoints = confirm.points && typeof confirm.points === 'object' ? confirm.points : {}
	const normalizedGroups = rawGroups.map((group, index) => normalizeGroup(group, index))
	const normalizedItems = normalizedGroups.reduce((result, group) => result.concat(group.items), [])
	const rawItems = normalizedItems.length ? flattenRawGroupItems(rawGroups) : items
	const initialPluginPayload = {}
	rawItems.forEach((item) => {
		const itemPayload = item && typeof item.plugin_payload === 'object' ? item.plugin_payload : {}
		Object.keys(itemPayload).forEach((code) => {
			if (itemPayload[code] && typeof itemPayload[code] === 'object') initialPluginPayload[code] = itemPayload[code]
		})
	})
	if (confirm.plugin_payload && typeof confirm.plugin_payload === 'object') {
		Object.assign(initialPluginPayload, confirm.plugin_payload)
	}
	pluginPayload.value = initialPluginPayload

	merchant.value = {
		name: String(rawMerchant.merchant_name || firstGroup.merchant_name || '平台自营').trim(),
		shippingText: buildShippingText(rawSummary, rawItems),
		shippingInsurance: '按订单规则计算',
		remarkPlaceholder: '选填，填写订单备注'
	}
	groups.value = normalizedGroups
	goodsList.value = normalizedItems.length ? normalizedItems : items.map((item) => normalizeGoodsItem(item))
	isVirtualOrder.value = resolveIsVirtualOrder(rawItems)
	summary.value = {
		goods_amount: Number(rawSummary.goods_amount || firstGroup.total_amount || 0),
		postage: Number(rawSummary.postage || 0),
		discount_amount: Number(rawSummary.discount_amount || 0),
		coupon_amount: Number(rawSummary.coupon_amount || 0),
		member_discount_amount: Number((rawSummary.member_discount || {}).discount_amount || rawSummary
			.member_discount_amount || 0),
		platform_coupon_amount: Number(rawSummary.platform_coupon_amount || 0),
		merchant_coupon_amount: Number(rawSummary.merchant_coupon_amount || 0),
		points_deduct_amount: Number(rawSummary.points_deduct_amount || 0),
		pay_amount: Number(rawSummary.pay_amount || 0)
	}
	coupon.value = {
		selected_coupon_id: Number(rawCoupon.selected_coupon_id || 0),
		platform_coupon_list: Array.isArray(rawCoupon.platform_coupon_list) ? rawCoupon.platform_coupon_list :
		[],
		merchant_coupon_list: Array.isArray(rawCoupon.merchant_coupon_list) ? rawCoupon.merchant_coupon_list :
		[],
		platform_coupon_count: Number(rawCoupon.platform_coupon_count || 0),
		merchant_coupon_count: Number(rawCoupon.merchant_coupon_count || 0)
	}
	platformCoupon.value = {
		selected_coupon_id: Number(rawPlatformCoupon.selected_coupon_id || 0),
		platform_coupon_list: Array.isArray(rawPlatformCoupon.platform_coupon_list) ? rawPlatformCoupon
			.platform_coupon_list : [],
		platform_coupon_count: Number(rawPlatformCoupon.platform_coupon_count || 0),
		coupon_amount: Number(rawPlatformCoupon.coupon_amount || 0)
	}
	platformCouponId.value = Number(platformCoupon.value.selected_coupon_id || 0)
	syncMerchantCouponState(normalizedGroups)
	points.value = {
		enabled: Boolean(rawPoints.enabled),
		available_points: Number(rawPoints.available_points || 0),
		max_use_points: Number(rawPoints.max_use_points || 0),
		use_points: Number(rawPoints.use_points || 0),
		deduct_amount: Number(rawPoints.deduct_amount || 0),
		message: String(rawPoints.message || '')
	}
	usePointsEnabled.value = Number(rawPoints.use_points || 0) > 0
	if (rawAddress.id > 0) {
		const exists = addressOptions.value.some((item) => item.id === rawAddress.id)
		addressOptions.value = exists ?
			addressOptions.value.map((item) => item.id === rawAddress.id ? rawAddress : item) :
			[rawAddress].concat(addressOptions.value.filter((item) => item.id > 0))
		selectedAddressId.value = rawAddress.id
	}
}
function normalizeGroup(group = {}, index = 0) {
	const summary = group.summary && typeof group.summary === 'object' ? group.summary : {}
	const coupon = group.coupon && typeof group.coupon === 'object' ? group.coupon : {}
	const merchantId = Number(group.merchant_id || 0)
	const remark = String(group.remark || merchantRemarks.value[merchantId] || '').trim()

	return {
		key: merchantId + '-' + index,
		merchantId,
		merchantName: String(group.merchant_name || (merchantId > 0 ? '商户店铺' : '平台自营')).trim(),
		merchantType: String(group.merchant_type || (merchantId > 0 ? 'merchant' : 'platform')),
		items: Array.isArray(group.items) ? group.items.map((item) => normalizeGoodsItem(item)) : [],
		coupon,
		summary,
		shippingText: buildShippingText(summary, group.items),
		shippingInsurance: '按订单规则计算',
		remarkPlaceholder: '选填，填写订单备注',
		remark
	}
}
function flattenRawGroupItems(groups = []) {
	return groups.reduce((result, group) => {
		const items = Array.isArray(group.items) ? group.items : []
		return result.concat(items)
	}, [])
}
function syncMerchantCouponState(groups = []) {
	let legacyMerchantCouponId = 0
	groups.forEach((group) => {
		const merchantId = Number(group.merchantId || 0)
		const selectedCouponId = Number((group.coupon || {}).selected_coupon_id || 0)
		merchantCouponIds.value[merchantId] = selectedCouponId
		merchantRemarks.value[merchantId] = group.remark || ''
		if (merchantId > 0 && selectedCouponId > 0 && legacyMerchantCouponId === 0) {
			legacyMerchantCouponId = selectedCouponId
		}
	})

	merchantCouponId.value = groups.filter((group) => Number(group.merchantId || 0) > 0).length === 1 ?
		legacyMerchantCouponId :
		0
}
function resolveIsVirtualOrder(items = []) {
	if (!Array.isArray(items) || !items.length) {
		return true
	}

	return items.every((item) => Number(item.order_type || ORDER_TYPE_GOODS) !== ORDER_TYPE_GOODS)
}
function hasDeliveryItems(items = []) {
	if (!Array.isArray(items) || !items.length) {
		return false
	}

	return items.some((item) => Number(item.order_type || ORDER_TYPE_GOODS) === ORDER_TYPE_GOODS)
}
function handleBack() {
	uni.navigateBack({
		fail: () => {
			uni.switchTab({
				url: '/pages/cart/index'
			})
		}
	})
}
function handleHelp() {
	uni.showToast({
		title: '可在此确认商品、优惠与实付金额',
		icon: 'none'
	})
}
function handleChooseAddress() {
	if (isVirtualOrder.value) {
		uni.showToast({
			title: '虚拟商品无需选择地址',
			icon: 'none'
		})
		return
	}

	showAddressPopup.value = true
}
function handleCloseAddressPopup() {
	showAddressPopup.value = false
}
function handleSelectAddress(id) {
	selectedAddressId.value = id
}
function handleConfirmAddress() {
	if (Number(selectedAddressId.value || 0) <= 0) {
		uni.showToast({
			title: '请先新增收货地址',
			icon: 'none'
		})
		return
	}

	showAddressPopup.value = false
	fetchConfirmOrder()
}
function handleManageAddress() {
	uni.navigateTo({
		url: '/pages/users/address/list/index'
	})
}
function handleAddAddress() {
	uni.navigateTo({
		url: '/pages/users/address/add/index'
	})
}
function handleAddressPrivacy() {
	uni.showToast({
		title: '地址隐私政策开发中',
		icon: 'none'
	})
}
function handleEditAddress(item) {
	uni.showToast({
		title: item.name || '当前地址不可编辑',
		icon: 'none'
	})
}
function handleShareAddress(item) {
	uni.showToast({
		title: item.name || '当前地址不可分享',
		icon: 'none'
	})
}
function handleChooseCoupon() {
	const count = Number(platformCoupon.value.platform_coupon_count || 0)
	if (!count) {
		uni.showToast({
			title: '暂无可用优惠券',
			icon: 'none'
		})
		return
	}

	couponPickerType.value = 'platform'
	couponPickerMerchantId.value = 0
	showCouponPopup.value = true
}
function handleChooseMerchantCoupon(group = {}) {
	const merchantId = Number(group.merchantId || 0)
	const count = Number((group.coupon || {}).merchant_coupon_count || 0)
	if (merchantId <= 0 || count <= 0) {
		uni.showToast({
			title: '暂无可用店铺优惠券',
			icon: 'none'
		})
		return
	}

	couponPickerType.value = 'merchant'
	couponPickerMerchantId.value = merchantId
	showCouponPopup.value = true
}
function handleCloseCouponPopup() {
	showCouponPopup.value = false
}
function handleSelectCoupon(id) {
	const couponId = Number(id || 0)
	if (couponPickerType.value === 'merchant') {
		merchantCouponIds.value[couponPickerMerchantId.value] = couponId
		const merchantGroups = groups.value.filter((group) => Number(group.merchantId || 0) > 0)
		merchantCouponId.value = merchantGroups.length === 1 ? couponId : 0
	} else {
		platformCouponId.value = couponId
	}
	showCouponPopup.value = false
	fetchConfirmOrder()
}
function findGroupByMerchantId(merchantId) {
	return groups.value.find((group) => Number(group.merchantId || 0) === Number(merchantId || 0)) || null
}
function normalizeCouponPopupItem(item = {}) {
	const couponMeta = item.coupon && typeof item.coupon === 'object' ? item.coupon : {}
	const id = Number(item.id || 0)
	const minAmount = Number(item.min_order_amount || 0)
	const scopeText = resolveOrderCouponScopeText(item, couponMeta)
	const expireTime = resolveOrderCouponExpireTime(item)

	return {
		id,
		coupon_id: Number(item.coupon_id || 0),
		merchant_id: Number(item.merchant_id || 0),
		amount: formatAmount(item.discount_amount || 0),
		condition: minAmount > 0 ? `满${formatAmount(minAmount)}可用` : '无门槛',
		scopeText,
		tag: scopeText,
		title: String(item.name || '订单优惠券'),
		expireTime,
		desc: expireTime,
		selected: id === currentCouponPickerSelectedId.value,
		tagMuted: false
	}
}
function resolveOrderCouponScopeText(item = {}, couponMeta = {}) {
	const scopeType = Number(couponMeta.scope_type ?? item.scope_type ?? -1)
	if (scopeType === 10) {
		return '平台券'
	}
	if (scopeType === 11) {
		return '品类券'
	}
	if (scopeType === 1) {
		return '商品券'
	}

	return couponPickerType.value === 'platform' ? '平台券' : '店铺券'
}
function resolveOrderCouponExpireTime(item = {}) {
	const start = String(item.valid_start_time || '').trim()
	const end = String(item.valid_end_time || '').trim()
	if (start && end) {
		return `${start.slice(0, 10)} 至 ${end.slice(0, 10)}`
	}
	if (end) {
		return `有效期至 ${end.slice(0, 10)}`
	}

	return '有效期以券面为准'
}
function getMerchantCouponCountText(group = {}) {
	const count = Number((group.coupon || {}).merchant_coupon_count || 0)
	return count > 0 ? count + '张可用' : ''
}
function getMerchantCouponAmount(group = {}) {
	const summary = group.summary || {}
	return Number(summary.merchant_coupon_amount || 0)
}
function getMerchantCouponValueText(group = {}) {
	const amount = getMerchantCouponAmount(group)
	if (amount > 0) {
		return '-¥' + formatAmount(amount)
	}

	return Number((group.coupon || {}).merchant_coupon_count || 0) > 0 ? '请选择' : '暂无可用'
}
function handleTogglePoints() {
	if (!points.value.enabled) {
		uni.showToast({
			title: points.value.message || '积分抵扣暂不可用',
			icon: 'none'
		})
		return
	}

	if (Number(points.value.max_use_points || 0) <= 0) {
		uni.showToast({
			title: points.value.message || '暂无可抵扣积分',
			icon: 'none'
		})
		return
	}

	usePointsEnabled.value = !usePointsEnabled.value
	fetchConfirmOrder()
}
async function handleSubmit() {
	if (submitLoading.value) {
		return
	}

	submitLoading.value = true
	try {
		const requestId = createRequestId()
		const payload = buildOrderPayload()
		const before = await runPluginSubmitBefore('mobile.order.submit.before', {
			request_id: requestId,
			payload,
			groups: groups.value.map((group) => ({ ...group })),
			is_virtual_order: isVirtualOrder.value
		})
		if (!before.allowed) {
			uni.showToast({
				title: before.message || '插件校验失败，请稍后重试',
				icon: 'none'
			})
			return
		}

		const res = await createOrder(before.payload, {
			showError: false
		})
		if (!res || res.code !== 200 || !res.data) {
			const message = String((res && res.msg) || '').trim()
			if (message.includes('收货地址')) {
				showAddressPopup.value = true
			}
			if (message) {
				uni.showToast({
					title: message,
					icon: 'none'
				})
			}
			return
		}

		const orderId = Number(res.data.order_id || 0)
		const orderSn = String(res.data.order_sn || '').trim()
		await emitPluginSubmitAfter('mobile.order.submit.after', {
			request_id: requestId,
			payload: {
				order_id: orderId,
				order_sn: orderSn
			}
		})
		if (Number(res.data.pay_amount || 0) <= 0) {
			const resultQuery = orderSn ?
				'order_sn=' + encodeURIComponent(orderSn) :
				'order_id=' + orderId
			uni.redirectTo({
				url: '/pages/orders/pay/result?' + resultQuery + '&client_result=success'
			})
			return
		}

		const query = orderSn ?
			'order_sn=' + encodeURIComponent(orderSn) :
			'orderId=' + orderId
		uni.redirectTo({
			url: '/pages/orders/pay/index?' + query
		})
	} catch (error) {
		console.error('[order-confirm] handleSubmit failed', error)
	} finally {
		submitLoading.value = false
	}
}
function formatAmount(value) {
	const amount = Number(value || 0)
	return Number.isFinite(amount) ? amount.toFixed(2) : '0.00'
}
function buildShippingText(summary = {}, items = []) {
	const freightMessage = String(summary.freight_message || '').trim()
	if (freightMessage) {
		return freightMessage
	}

	return hasDeliveryItems(items) ? '普通配送' : '免配送'
}
</script>


<style lang="scss">
	page {
		background: #f4f4f4;
	}
</style>

<style lang="scss" scoped>
	.confirm-order-page {
		min-height: 100%;
		background: #f4f4f4;
	}

	.confirm-order-page__content {
		padding: 0 24rpx 220rpx;
		box-sizing: border-box;
	}

	.confirm-order-page__help {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 44rpx;
		height: 44rpx;
		border-radius: 50%;
		background: #1a1c1c;
	}

	.confirm-order-page__help-text {
		font-size: 24rpx;
		line-height: 1;
		font-weight: 700;
		color: #ffffff;
	}

	.address-card,
	.goods-card,
	.summary-card {
		background: #ffffff;
		border-radius: 20rpx;
		box-sizing: border-box;
	}

	.address-card {
		display: flex;
		align-items: flex-start;
		padding: 32rpx 28rpx;
	}

	.address-card__icon-wrap {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 44rpx;
		margin-top: 6rpx;
		flex-shrink: 0;
	}

	.address-card__icon {
		font-size: 34rpx;
		line-height: 1;
		color: var(--brand-primary, #ea580c);
	}

	.address-card__body {
		flex: 1;
		min-width: 0;
		margin-left: 20rpx;
	}

	.address-card__head {
		display: flex;
		align-items: center;
		flex-wrap: wrap;
	}

	.address-card__name {
		font-size: 34rpx;
		line-height: 48rpx;
		font-weight: 700;
		color: #1a1c1c;
	}

	.address-card__phone {
		margin-left: 16rpx;
		font-size: 34rpx;
		line-height: 48rpx;
		color: #6e6a6b;
	}

	.address-card__detail {
		display: block;
		margin-top: 10rpx;
		font-size: 30rpx;
		line-height: 42rpx;
		color: #5f5f5f;
	}

	.address-card__arrow,
	.info-row__arrow,
	.summary-row__arrow {
		font-size: 24rpx;
		line-height: 1;
		color: #b9b9bf;
	}

	.address-card__arrow {
		align-self: center;
		margin-left: 16rpx;
	}

	.goods-card,
	.summary-card {
		margin-top: 20rpx;
	}

	.goods-card__header {
		padding: 32rpx 28rpx 0;
	}

	.goods-card__shop-wrap {
		display: flex;
		align-items: center;
	}

	.goods-card__shop-icon {
		font-size: 30rpx;
		line-height: 1;
		color: #5a5a5f;
	}

	.goods-card__shop-name {
		margin-left: 12rpx;
		font-size: 34rpx;
		line-height: 48rpx;
		font-weight: 700;
		color: #1a1c1c;
	}

	.goods-card__body {
		padding: 20rpx 28rpx 0;
	}

	.goods-item {
		display: flex;
		align-items: flex-start;
	}

	.goods-item+.goods-item {
		margin-top: 32rpx;
	}

	.goods-item__image {
		width: 160rpx;
		height: 160rpx;
		border-radius: 16rpx;
		background: #f5f5f5;
		flex-shrink: 0;
	}

	.goods-item__info {
		flex: 1;
		min-width: 0;
		margin-left: 22rpx;
		padding-top: 2rpx;
	}

	.goods-item__title {
		display: block;
		width: 100%;
		overflow: hidden;
		font-size: 28rpx;
		line-height: 42rpx;
		color: #1a1c1c;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.goods-item__sku {
		display: block;
		margin-top: 8rpx;
		font-size: 24rpx;
		line-height: 34rpx;
		color: #7b7677;
	}

	.goods-item__footer {
		display: flex;
		align-items: flex-end;
		justify-content: space-between;
		margin-top: 16rpx;
	}

	.goods-item__price-wrap {
		display: flex;
		align-items: flex-end;
		min-width: 0;
	}

	.goods-item__vip-tag {
		margin-right: 8rpx;
		padding: 3rpx 6rpx;
		border-radius: 8rpx;
		background: #1a1c1c;
		font-size: 20rpx;
		line-height: 24rpx;
		font-weight: 700;
		color: #ffd76a;
	}

	.goods-item__price {
		font-size: 36rpx;
		line-height: 1;
		font-weight: 700;
		color: var(--brand-primary, #ea580c);
	}

	.goods-item__regular-price {
		margin-left: 10rpx;
		font-size: 22rpx;
		line-height: 28rpx;
		color: #aaa5a6;
		text-decoration: line-through;
	}

	.goods-item__count {
		font-size: 24rpx;
		line-height: 34rpx;
		color: #6a6667;
	}

	.goods-card__divider {
		height: 1rpx;
		margin: 28rpx 28rpx 0;
		background: #efefef;
	}

	.goods-card__extra {
		padding: 10rpx 28rpx 24rpx;
	}

	.info-row {
		display: flex;
		align-items: center;
		justify-content: space-between;
		min-height: 72rpx;
	}

	.info-row__label {
		font-size: 28rpx;
		line-height: 44rpx;
		color: #5b5b60;
	}

	.info-row__value-wrap {
		display: flex;
		align-items: center;
		justify-content: flex-end;
		margin-left: 24rpx;
		flex: 1;
	}

	.info-row__value {
		font-size: 28rpx;
		line-height: 44rpx;
		color: #1a1c1c;
	}

	.info-row__value--muted {
		color: #adadb4;
	}

	.info-row__arrow {
		margin-left: 10rpx;
	}

	.info-row--remarks {
		align-items: flex-start;
		padding-top: 8rpx;
	}

	.info-row__input {
		height: 44rpx;
		margin-left: 24rpx;
		flex: 1;
		font-size: 30rpx;
		line-height: 44rpx;
		text-align: right;
		color: #1a1c1c;
	}

	.info-row__input-placeholder {
		color: #c4c4ca;
	}

	.summary-card {
		padding: 28rpx;
	}

	.summary-row {
		display: flex;
		align-items: center;
		justify-content: space-between;
	}

	.summary-row+.summary-row {
		margin-top: 22rpx;
	}

	.summary-row--clickable {
		min-height: 52rpx;
	}

	.summary-row__action {
		display: flex;
		align-items: center;
		justify-content: flex-end;
		margin-left: 24rpx;
		flex: 1;
	}

	.summary-row__action--points {
		align-items: center;
		min-width: 0;
	}

	.points-summary {
		display: flex;
		flex-direction: column;
		align-items: flex-end;
		flex: 1;
		min-width: 0;
		text-align: right;
	}

	.points-summary__desc {
		display: block;
		margin-top: 4rpx;
		font-size: 20rpx;
		line-height: 28rpx;
		color: #8b8788;
	}

	.summary-row__arrow {
		margin-left: 10rpx;
	}

	.summary-row__tag--points {
		background: #fff7e6;
		color: var(--brand-primary, #ea580c);
	}

	.points-switch {
		width: 72rpx;
		height: 40rpx;
		margin-left: 16rpx;
		border-radius: 999rpx;
		background: #d8d8dd;
		box-sizing: border-box;
		transition: background-color 0.2s ease;
	}

	.points-switch__dot {
		width: 36rpx;
		height: 36rpx;
		margin: 2rpx;
		border-radius: 50%;
		background: #ffffff;
		transition: margin-left 0.2s ease;
	}

	.points-switch--active {
		background: var(--brand-primary, #ea580c);
	}

	.points-switch--active .points-switch__dot {
		margin-left: 34rpx;
	}

	.summary-row__label-wrap {
		display: flex;
		align-items: center;
	}

	.summary-row__label {
		font-size: 28rpx;
		line-height: 44rpx;
		color: #5b5b60;
	}

	.summary-row__tag {
		margin-left: 10rpx;
		padding: 2rpx 10rpx;
		border-radius: 999rpx;
		background: #fff1f0;
		font-size: 18rpx;
		line-height: 28rpx;
		font-weight: 700;
		color: #ff4d4f;
	}

	.summary-row__value {
		font-size: 28rpx;
		line-height: 44rpx;
		color: #1a1c1c;
	}

	.summary-row__value--discount {
		color: #ff4d4f;
	}

	.summary-total {
		display: flex;
		align-items: baseline;
		justify-content: flex-end;
		margin-top: 26rpx;
	}

	.summary-total__label {
		font-size: 28rpx;
		line-height: 40rpx;
		color: #6a6667;
	}

	.summary-total__value {
		margin-left: 14rpx;
		font-size: 54rpx;
		line-height: 1;
		font-weight: 700;
		color: var(--brand-primary, #ea580c);
	}

	.submit-bar {
		position: fixed;
		left: 0;
		right: 0;
		bottom: 0;
		z-index: 20;
		display: flex;
		align-items: center;
		padding: 18rpx 32rpx;
		background: rgba(255, 255, 255, 0.98);
		border-top: 1rpx solid #dddddf;
		box-sizing: border-box;
	}

	.submit-bar__summary {
		flex: 1;
		min-width: 0;
	}

	.submit-bar__amount-row {
		display: flex;
		align-items: baseline;
	}

	.submit-bar__label {
		font-size: 26rpx;
		line-height: 36rpx;
		color: #7c7778;
	}

	.submit-bar__amount {
		margin-left: 8rpx;
		font-size: 48rpx;
		line-height: 1;
		font-weight: 700;
		color: var(--brand-primary, #ea580c);
	}

	.submit-bar__tip {
		display: block;
		margin-top: 8rpx;
		font-size: 20rpx;
		line-height: 28rpx;
		color: #8b8788;
	}

	.submit-bar__button {
		width: 232rpx;
		height: 88rpx;
		margin: 0;
		border: 0;
		border-radius: 999rpx;
		background: var(--brand-primary, #ea580c);
		font-size: 32rpx;
		line-height: 88rpx;
		font-weight: 700;
		color: #ffffff;
	}

	.submit-bar__button::after {
		border: 0;
	}

	.address-popup {
		display: flex;
		flex-direction: column;
		width: 100%;
		max-height: 1080rpx;
		border-top-left-radius: 24rpx;
		border-top-right-radius: 24rpx;
		overflow: hidden;
		background: #ffffff;
	}

	.address-popup-sheet {
		position: fixed;
		top: 0;
		right: 0;
		bottom: 0;
		left: 0;
		z-index: 1000;
	}

	@keyframes address-popup-mask-in {
		0% {
			opacity: 0;
		}

		100% {
			opacity: 1;
		}
	}

	@keyframes address-popup-sheet-in {
		0% {
			transform: translateY(100%);
		}

		100% {
			transform: translateY(0);
		}
	}

	.address-popup-sheet__mask {
		position: absolute;
		top: 0;
		right: 0;
		bottom: 0;
		left: 0;
		background: rgba(0, 0, 0, 0.4);
		animation: address-popup-mask-in 180ms ease-out both;
	}

	.address-popup-sheet__panel {
		position: absolute;
		right: 0;
		bottom: 0;
		left: 0;
		animation: address-popup-sheet-in 240ms ease-out both;
	}

	.address-popup__header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 22rpx 22rpx 20rpx;
		background: #ffffff;
	}

	.address-popup__close {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 56rpx;
		height: 56rpx;
		flex-shrink: 0;
	}

	.address-popup__title {
		font-size: 34rpx;
		line-height: 48rpx;
		font-weight: 700;
		color: #1a1c1c;
	}

	.address-popup__more {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 56rpx;
		height: 56rpx;
		font-size: 34rpx;
		line-height: 1;
		color: var(--brand-primary, #ea580c);
		flex-shrink: 0;
	}

	.address-popup__search {
		padding: 0 24rpx 18rpx;
		background: #ffffff;
	}

	.address-popup__search-box {
		display: flex;
		align-items: center;
		height: 78rpx;
		padding: 0 20rpx;
		border-radius: 16rpx;
		background: #f3f3f3;
	}

	.address-popup__search-input {
		margin-left: 12rpx;
		flex: 1;
		font-size: 28rpx;
		line-height: 40rpx;
		color: #1a1c1c;
	}

	.address-popup__search-placeholder {
		color: #8e8b8c;
	}

	.address-popup__scroll {
		flex: 1;
		min-height: 0;
		background: #f9f9f9;
	}

	.address-popup__list {
		padding: 18rpx 24rpx 24rpx;
	}

	.address-option {
		padding: 24rpx;
		border-radius: 18rpx;
		background: #f3f3f3;
	}

	.address-option+.address-option {
		margin-top: 20rpx;
	}

	.address-option--active {
		background: #ffffff;
		box-shadow: 0 8rpx 20rpx rgba(0, 0, 0, 0.08);
	}

	.address-option__top {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
	}

	.address-option__name-wrap {
		display: flex;
		align-items: center;
		flex-wrap: wrap;
		min-width: 0;
	}

	.address-option__name {
		font-size: 32rpx;
		line-height: 44rpx;
		font-weight: 700;
		color: #1a1c1c;
	}

	.address-option__tag {
		margin-left: 12rpx;
		padding: 4rpx 12rpx;
		border-radius: 999rpx;
		background: var(--brand-primary, #ea580c);
		font-size: 18rpx;
		line-height: 24rpx;
		font-weight: 700;
		color: #ffffff;
	}

	.address-option__check {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 40rpx;
		height: 40rpx;
		border-radius: 20rpx;
		background: var(--brand-primary, #ea580c);
		flex-shrink: 0;
	}

	.address-option__body {
		margin-top: 12rpx;
	}

	.address-option__phone,
	.address-option__detail {
		display: block;
		font-size: 24rpx;
		line-height: 36rpx;
		color: #5f6368;
	}

	.address-option__detail {
		margin-top: 8rpx;
		font-size: 26rpx;
		line-height: 38rpx;
	}

	.address-option__actions {
		display: flex;
		align-items: center;
		margin-top: 18rpx;
		padding-top: 16rpx;
		border-top: 1rpx solid rgba(207, 196, 197, 0.5);
	}

	.address-option__action {
		display: flex;
		align-items: center;
	}

	.address-option__action+.address-option__action {
		margin-left: 28rpx;
	}

	.address-option__action-text {
		margin-left: 8rpx;
		font-size: 20rpx;
		line-height: 28rpx;
		color: #4c4546;
	}

	.address-popup__add {
		display: flex;
		align-items: center;
		justify-content: center;
		height: 96rpx;
		margin-top: 22rpx;
		border-radius: 18rpx;
		background: #ececec;
	}

	.address-popup__add-text {
		margin-left: 12rpx;
		font-size: 30rpx;
		line-height: 40rpx;
		font-weight: 700;
		color: #5f6368;
	}

	.address-popup__footer {
		padding: 18rpx 24rpx 24rpx;
		background: #ffffff;
		box-shadow: 0 -6rpx 20rpx rgba(0, 0, 0, 0.03);
	}

	.address-popup__links {
		display: flex;
		align-items: center;
		justify-content: space-between;
	}

	.address-popup__link {
		font-size: 20rpx;
		line-height: 28rpx;
		color: #7e7576;
		text-decoration: underline;
	}

	.address-popup__submit {
		width: 100%;
		height: 88rpx;
		margin: 24rpx 0 0;
		border: 0;
		border-radius: 999rpx;
		background: var(--brand-primary, #ea580c);
		box-shadow: 0 12rpx 24rpx rgba(243, 90, 0, 0.2);
		font-size: 34rpx;
		line-height: 88rpx;
		font-weight: 700;
		color: #ffffff;
	}

	.address-popup__submit::after {
		border: 0;
	}

	/* #ifdef H5 */
	.confirm-order-page__content {
		padding-bottom: calc(220rpx + var(--window-bottom, 0px));
	}

	.submit-bar {
		bottom: var(--window-bottom, 0px);
		padding-bottom: calc(18rpx + var(--window-bottom, 0px));
	}

	.address-popup__footer {
		padding-bottom: calc(24rpx + var(--window-bottom, 0px));
	}

	/* #endif */

	/* #ifdef MP-WEIXIN */
	.confirm-order-page__content {
		padding-bottom: calc(220rpx + env(safe-area-inset-bottom));
	}

	.submit-bar {
		padding-bottom: calc(18rpx + env(safe-area-inset-bottom));
	}

	.address-popup__footer {
		padding-bottom: calc(24rpx + env(safe-area-inset-bottom));
	}

	/* #endif */

	::v-deep .app-safe-top__nav {
		min-height: 88rpx;
		padding: 0 96rpx;
	}

	::v-deep .app-safe-top__back {
		left: 24rpx;
	}

	::v-deep .app-safe-top__right {
		right: 24rpx;
		width: 52rpx;
		min-height: 52rpx;
	}
</style>
