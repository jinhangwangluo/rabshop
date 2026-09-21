<template>
	<page-meta :page-style="themePageStyle"></page-meta>
	<view class="store-product-detail">
		<view class="hero-section">
			<view v-if="loading" class="hero-skeleton skeleton-block"></view>
			<swiper v-else class="hero-section__swiper" :style="heroSectionStyle" :current="heroCurrentIndex"
				:duration="260" :circular="heroCanLoop" @change="changeHeroPreview">
				<swiper-item v-for="(item, index) in heroImages" :key="item.id" class="hero-section__slide">
					<image class="hero-section__image" :src="item.url" mode="aspectFill"
						@load="handleHeroImageLoad($event, index)" @click="openGalleryPreview"></image>
				</swiper-item>
			</swiper>
			<product-detail-navigation :pinned="navPinned" :menu-visible="showTopMenu"
				@back="handleBack" @toggle="toggleTopMenu" @close="closeTopMenu" @share="shareProduct" />
		</view>


		<view class="detail-scroll">
			<view v-if="loading" class="detail-content detail-content--skeleton">
				<view class="goods-card skeleton-card">
					<view class="skeleton-thumb-row">
						<view v-for="item in skeletonThumbs" :key="item" class="skeleton-thumb skeleton-block"></view>
						<view class="skeleton-thumb-more">
							<view class="skeleton-more-line skeleton-block"></view>
							<view class="skeleton-more-line skeleton-more-line--short skeleton-block"></view>
						</view>
					</view>
					<view class="skeleton-price-row">
						<view class="skeleton-price skeleton-block"></view>
						<view class="skeleton-tag skeleton-block"></view>
						<view class="skeleton-origin skeleton-block"></view>
					</view>
					<view class="skeleton-coupon-row">
						<view v-for="item in skeletonCoupons" :key="item" class="skeleton-coupon skeleton-block"></view>
					</view>
					<view class="skeleton-title skeleton-block"></view>
					<view class="skeleton-title skeleton-title--short skeleton-block"></view>
					<view class="skeleton-meta-row">
						<view class="skeleton-badge skeleton-block"></view>
						<view class="skeleton-badge skeleton-block"></view>
						<view class="skeleton-meta skeleton-block"></view>
						<view class="skeleton-meta skeleton-meta--right skeleton-block"></view>
					</view>
				</view>

				<view class="info-card skeleton-card skeleton-info-card">
					<view v-for="item in skeletonInfoRows" :key="item" class="skeleton-info-row">
						<view class="skeleton-info-label skeleton-block"></view>
						<view class="skeleton-info-value skeleton-block"></view>
					</view>
				</view>

				<view class="review-card skeleton-card">
					<view class="skeleton-section-row">
						<view class="skeleton-section-title skeleton-block"></view>
						<view class="skeleton-section-more skeleton-block"></view>
					</view>
					<view class="skeleton-review-row">
						<view v-for="item in skeletonReviews" :key="item" class="skeleton-review skeleton-block"></view>
					</view>
				</view>

				<view class="detail-card skeleton-card">
					<view class="skeleton-detail-title skeleton-block"></view>
					<view class="skeleton-detail-image skeleton-block"></view>
				</view>
			</view>

			<view v-else class="detail-content">
				<view class="goods-card">
					<view class="thumb-row">
						<scroll-view scroll-x class="thumb-list" :show-scrollbar="false">
							<view class="thumb-list__inner">
								<view v-for="item in specThumbImages" :key="item.id" class="thumb-item"
									:class="{ 'thumb-item--active': item.selected }" @click="handlePreviewThumbClick(item)">
									<image class="thumb-item__image" :src="item.url" mode="aspectFill"></image>
								</view>
							</view>
						</scroll-view>
						<view class="thumb-more" @click="openSkuPopup('select')">
							<text class="thumb-more__count">{{ specCountText }}</text>
							<text class="thumb-more__label">可选</text>
						</view>
					</view>

					<view class="price-row">
						<text class="price-row__symbol">¥</text>
						<text class="price-row__integer">{{ priceInteger }}</text>
						<text class="price-row__decimal">.{{ priceDecimal }}</text>
						<text class="price-row__tag">到手价</text>
						<text v-if="originalPriceText"
							class="price-row__origin">¥{{ originalPriceText }}</text>
					</view>

					<view class="coupon-row" hover-class="coupon-row--hover" @click.stop="openCouponPopup"
						@tap.stop="openCouponPopup">
						<text v-for="item in couponTags" :key="item.id" class="coupon-row__item"
							@click.stop="openCouponPopup" @tap.stop="openCouponPopup">{{ item.text }}</text>
						<text v-if="!couponTags.length" class="coupon-row__empty" @click.stop="openCouponPopup"
							@tap.stop="openCouponPopup">暂无可用优惠券</text>
						<view class="coupon-row__more" @click.stop="openCouponPopup" @tap.stop="openCouponPopup">
							<text>查看更多优惠</text>
							<text class="iconfont icon-right coupon-row__more-icon"></text>
						</view>
					</view>

					<text class="goods-title">{{ detail.name }}</text>
					<text v-if="detail.description" class="goods-description">{{ detail.description }}</text>
					<view class="goods-meta">
						<view v-if="productTags.length" class="goods-meta__tags">
							<tag-preview v-for="tag in productTags" :key="tag.renderKey" :tag="tag" size="small" />
						</view>
						<text class="goods-meta__stock">库存：{{ maxQuantity }}件</text>
						<text class="goods-meta__sales">销量：{{ detail.sales }}件</text>
					</view>
				</view>

				<view class="info-card">
					<view class="info-row" @click="openSkuPopup('select')">
						<text class="info-row__label">规格</text>
						<text class="info-row__value">{{ selectedSpecName }}</text>
						<text class="iconfont icon-right info-row__arrow"></text>
					</view>
					<view v-if="serviceItems.length" class="info-row" @click="openServicePopup">
						<text class="info-row__label">服务</text>
						<text class="info-row__value">{{ serviceSummaryText }}</text>
						<text class="iconfont icon-right info-row__arrow"></text>
					</view>
					<view v-if="paramItems.length" class="info-row info-row--last" @click="openParamPopup">
						<text class="info-row__label">参数</text>
						<text class="info-row__value">{{ paramSummaryText }}</text>
						<text class="iconfont icon-right info-row__arrow"></text>
					</view>
				</view>

				<PluginSlot
					name="mobile.goods.detail.sections"
					:context="{ item: detail, plugin_payload: pluginPayload, setPluginPayload }"
				/>

				<view v-if="storeProfile.visible" class="store-card">
					<view class="store-card__main">
						<view class="store-card__avatar">
							<image v-if="storeProfile.avatar" class="store-card__avatar-image" :src="storeProfile.avatar"
								mode="aspectFill"></image>
							<text v-else class="store-card__avatar-text">{{ storeProfile.initial }}</text>
						</view>
						<view class="store-card__body">
							<view class="store-card__title-row">
								<text class="store-card__name">{{ storeProfile.name }}</text>
								<text v-if="storeProfile.badge" class="store-card__badge">{{ storeProfile.badge }}</text>
							</view>
							<view class="store-card__rating">
								<text class="store-card__rating-label">综合体验</text>
								<view class="store-card__stars">
									<text v-for="star in storeProfile.stars" :key="star.id"
										class="iconfont icon-favorfill store-card__star"
										:class="{ 'store-card__star--active': star.active }"></text>
								</view>
								<text class="store-card__rating-score">{{ storeProfile.ratingText }}</text>
							</view>
							<text class="store-card__desc">{{ storeProfile.metaText }}</text>
						</view>
						<view v-if="storeProfile.canEnter" class="store-card__button" hover-class="store-card__button--hover"
							@click="enterStoreProfile">
							<text class="iconfont icon-shop store-card__button-icon"></text>
							<text>进店逛逛</text>
						</view>
					</view>
				</view>

				<view class="review-card">
					<view class="section-title">
						<text class="section-title__text">{{ reviewTotalText }}</text>
						<view class="section-title__right"
							@click="navigateTo('/pages/goods/commen/index?id='+productId)">
							<text>{{ reviewRateText }}</text>
							<text class="iconfont icon-right section-title__arrow"></text>
						</view>
					</view>
					<view v-if="!reviews.length" class="review-empty">暂无评价</view>
					<scroll-view v-else scroll-x class="review-list" :show-scrollbar="false">
						<view class="review-list__inner">
							<view v-for="item in reviews" :key="item.id" class="review-item">
								<view class="review-item__top">
									<image v-if="item.avatar" class="review-item__avatar" :src="item.avatar" mode="aspectFill"></image>
									<view v-else class="review-item__avatar review-item__avatar--empty"></view>
									<view class="review-item__user">
										<text class="review-item__name">{{ item.name }}</text>
										<view class="review-item__stars">
											<text v-for="star in item.stars" :key="star"
												class="iconfont icon-favorfill review-item__star"></text>
										</view>
									</view>
								</view>
								<text class="review-item__content">{{ item.content }}</text>
							</view>
						</view>
					</scroll-view>
				</view>

				<view class="detail-card">
					<view class="detail-card__title">
						<text class="iconfont icon-link detail-card__decoration"></text>
						<text>商品详情</text>
						<text class="iconfont icon-link detail-card__decoration detail-card__decoration--right"></text>
					</view>
					<rich-text v-if="detail.detail" class="detail-card__rich" :nodes="detail.detail"></rich-text>
					<text v-else class="detail-card__empty">暂无商品详情</text>
				</view>
			</view>
		</view>

		<view class="bottom-bar">
			<view class="bottom-action" @click="openCustomerService">
				<text class="iconfont icon-bangzhu bottom-action__icon"></text>
				<text class="bottom-action__text">客服</text>
			</view>
			<view class="bottom-action" :class="{ 'bottom-action--disabled': favoriteLoading }" @click="toggleFavorite">
				<text :class="['iconfont', favoriteIconClass, 'bottom-action__icon', isFavorite ? 'bottom-action__icon--active' : '']"></text>
				<text class="bottom-action__text">收藏</text>
			</view>
			<view class="bottom-action" @click="openCart">
				<text class="iconfont icon-cart bottom-action__icon"></text>
				<text class="bottom-action__text">购物车</text>
			</view>
			<view class="buy-button buy-button--cart" :class="{ 'buy-button--disabled': submitLoading }"
				@click="openSkuPopup('cart')">
				<text>加入购物车</text>
			</view>
			<view class="buy-button buy-button--buy"
				:class="{ 'buy-button--disabled': submitLoading || receivingCouponId > 0 }"
				@click="openPrimaryBuy">
				<text>{{ primaryBuyButtonText }}</text>
			</view>
		</view>

		<product-sku-popup :visible="showSkuPopup" :product="skuPopupProduct" :groups="specGroups" :selected="selected"
			:quantity="quantity" :max-quantity="maxQuantity" :submit-loading="submitLoading" :actions="skuPopupActions"
			@close="closeSkuPopup" @select="selectOption" @quantity-change="setQuantity" @submit="submitSkuAction" />

		<product-image-preview :visible="showGalleryPreview" :images="heroImages" :current="galleryPreviewIndex"
			:show-close="false" :close-on-tap="true" @close="closeGalleryPreview" @change="changeGalleryPreview" />

		<product-image-preview :visible="showSpecImagePreview" :images="specPreviewItems"
			:current="specImagePreviewIndex" counter-separator="/" :show-close="false" :close-on-tap="true"
			@close="closeSpecImagePreview" @change="changeSpecImagePreview" />

		<view v-if="showServicePopup" class="service-popup" @click="closeServicePopup">
			<view class="service-popup__sheet" @click.stop>
				<text class="service-popup__title">服务</text>
				<view class="service-popup__list">
					<view v-for="item in serviceItems" :key="item.id" class="service-popup__item">
						<view class="service-popup__icon-wrap">
							<text class="iconfont icon-favorfill service-popup__icon"></text>
						</view>
						<view class="service-popup__item-content">
							<text class="service-popup__item-title">{{ item.title }}</text>
							<text class="service-popup__item-desc">{{ item.desc }}</text>
						</view>
					</view>
				</view>
				<view class="service-popup__button" hover-class="service-popup__button--hover"
					@click="closeServicePopup">
					<text>确定</text>
				</view>
			</view>
		</view>

		<view v-if="showParamPopup" class="param-popup" @click="closeParamPopup">
			<view class="param-popup__sheet" @click.stop>
				<text class="param-popup__title">参数</text>
				<view class="param-popup__list">
					<view v-for="item in paramItems" :key="item.id" class="param-popup__item">
						<text class="param-popup__label">{{ item.label }}</text>
						<text v-if="item.value" class="param-popup__value">{{ item.value }}</text>
					</view>
				</view>
				<view class="param-popup__button" hover-class="param-popup__button--hover" @click="closeParamPopup">
					<text>确定</text>
				</view>
			</view>
		</view>

		<coupon-popup :visible="showCouponPopup" :items="productCouponOptions" title="可用优惠券"
			empty-title="暂无可用优惠券" empty-desc="当前商品暂时没有可领取或可使用的优惠券"
			@close="closeCouponPopup" @action="handleProductCouponAction" />

		<product-share-popup :visible="showSharePopup" :name="shareProductName" :image="shareProductImage"
			:price-text="priceText" :page-path="sharePagePath" @close="closeSharePopup" />


	</view>
</template>

<script setup>
	import {
		computed,
		ref
	} from 'vue'
	import {
		onHide,
		onLoad,
		onPageScroll,
		onShow,
		onShareAppMessage,
		onShareTimeline,
		onUnload
	} from '@dcloudio/uni-app'
	import {
		getProductCoupons,
		getProductDetail,
		getProductFavoriteStatus,
		getProductReviews,
		toggleProductFavorite
	} from '@/api/product'
	import {
		addCart,
		buyNow
	} from '@/api/cart'
	import { getHotelByProduct } from '@/plugins/hotel/api/hotel'
	import PluginSlot from '@/components/plugin-slot/index.vue'
	import ProductDetailNavigation from '@/components/product-detail-navigation/index.vue'
	import ProductSharePopup from '@/components/product-share-popup/index.vue'
	import {
		receiveCoupon
	} from '@/api/coupon'
	import {
		resolveAssetUrl
	} from '@/utils/runtime_config'
	import {
		normalizeDisplayTags
	} from '@/utils/tag'
	import {
		getUserInfo
	} from '@/utils/auth'
	import {
		extractSpreadUid,
		extractSpreadUidFromScene,
		saveSpreadUid
	} from '@/utils/spread'

	defineOptions({
		name: 'StoreProductDetailPage'
	})

	const productId = ref(0)
	const pluginPayload = ref({})
	const loading = ref(true)
	const heroCurrentIndex = ref(0)
	const heroImageHeights = ref({})
	const navPinned = ref(false)
	const showTopMenu = ref(false)
	const selectedSpecImageId = ref('')
	const showGalleryPreview = ref(false)
	const galleryPreviewIndex = ref(0)
	const showSpecImagePreview = ref(false)
	const specImagePreviewIndex = ref(0)
	const showSkuPopup = ref(false)
	const showCouponPopup = ref(false)
	const showServicePopup = ref(false)
	const showParamPopup = ref(false)
	const showSharePopup = ref(false)
	const currentShareUserId = ref(0)
	const pendingAction = ref('select')
	const submitLoading = ref(false)
	const receivingCouponId = ref(0)
	const checkoutCoupon = ref(null)
	const favoriteLoading = ref(false)
	const isFavorite = ref(false)
	const quantity = ref(1)
	const selected = ref({})
	const detail = ref(createEmptyDetail())
	const productCoupons = ref([])
	const reviews = ref([])
	const reviewSummary = ref({
		total: 0,
		good_count: 0,
		good_rate: 0
	})

	const skeletonThumbs = [1, 2, 3, 4, 5]
	const skeletonCoupons = [1, 2, 3, 4]
	const skeletonInfoRows = [1, 2, 3]
	const skeletonReviews = [1, 2, 3]


	const heroImages = computed(() => {
		return detail.value.images.map((url, index) => ({
			id: index + 1,
			url
		}))
	})
	const heroCanLoop = computed(() => heroImages.value.length > 1)
	const heroHeight = computed(() => heroImageHeights.value[heroCurrentIndex.value] || 750)
	const heroSectionStyle = computed(() => `height: ${heroHeight.value}rpx`)
	const navLayerBackground = computed(() => (navPinned.value ? '#ffffff' : 'transparent'))
	const productTags = computed(() => Array.isArray(detail.value.tag_list) ? detail.value.tag_list.slice(0, 2) : [])
	const storeProfile = computed(() => detail.value.store_profile || normalizeStoreProfile())
	const skuList = computed(() => {
		const data = detail.value.sku_data
		return Array.isArray(data.sku_list) ? data.sku_list : []
	})
	const selectedSpecKey = computed(() => {
		return specGroups.value.map((group) => String(selected.value[group.key] || '')).filter(Boolean).join('||')
	})
	const selectedSpecComplete = computed(() => {
		return specGroups.value.length > 0 && specGroups.value.every((group) => String(selected.value[group.key] || '') !== '')
	})
	const selectedSku = computed(() => {
		const currentKey = selectedSpecKey.value
		if (selectedSpecComplete.value && currentKey) {
			const matched = skuList.value.find((item) => String(item.spec_key || '') === currentKey)
			return matched || null
		}

		const defaultSku = detail.value.sku_data.default_sku
		return defaultSku && typeof defaultSku === 'object' ? defaultSku : null
	})
	const specThumbImages = computed(() => {
		return skuList.value.map((item, index) => {
			const id = String(item.id || item.uniqid || index + 1)
			const url = String(item.pics || '').trim() || detail.value.thumb || detail.value.cover || ''
			return {
				id,
				url,
				sku: String(item.sku || '').trim(),
				title: selectedSpecName.value,
				priceText: formatMoney(Number(item.price || detail.value.price || 0)),
				selected: selectedSpecImageId.value === id,
				specKey: String(item.spec_key || '')
			}
		}).filter((item) => item.url)
	})
	const specPreviewItems = computed(() => {
		return specThumbImages.value.map((item) => ({
			id: item.id,
			url: item.url,
			title: String(item.sku || selectedSpecName.value || ''),
			priceText: item.priceText
		}))
	})
	const specGroups = computed(() => {
		const data = detail.value.sku_data
		const specList = Array.isArray(data.spec_list) ? data.spec_list : []
		return specList.map((group, index) => ({
			key: String(group.spec_id || ''),
			title: String(group.name || ''),
			options: normalizeSpecValues(group.values),
			small: index > 0
		})).filter((group) => group.key && group.title && group.options.length)
	})
	const maxQuantity = computed(() => Math.max(1, Number(selectedSku.value ? selectedSku.value.stock : detail.value.stock)))
	const priceText = computed(() => {
		const price = selectedSku.value ? Number(selectedSku.value.price) : Number(detail.value.price)
		return formatMoney(price)
	})
	const priceInteger = computed(() => priceText.value.split('.')[0])
	const priceDecimal = computed(() => (priceText.value.split('.')[1] || '00').padEnd(2, '0').slice(0, 2))
	const originalPriceText = computed(() => {
		const originPrice = selectedSku.value ? Number(selectedSku.value.ot_price) : Number(detail.value.ot_price)
		const price = Number(priceText.value)
		return originPrice > price ? formatMoney(originPrice) : ''
	})
	const skuPopupProduct = computed(() => ({
		...detail.value,
		priceText: priceText.value,
		originalPriceText: originalPriceText.value,
		stock: maxQuantity.value,
		thumb: selectedSkuImage.value
	}))
	const selectedSkuImage = computed(() => {
		const skuImage = selectedSku.value ? String(selectedSku.value.pics || '').trim() : ''
		return skuImage || detail.value.thumb
	})
	const shareProductImage = computed(() => detail.value.cover)
	const shareProductName = computed(() => {
		const name = String(detail.value.name || '').trim()
		return name || '商品详情'
	})
	const sharePagePath = computed(() => {
		return buildSharePagePath()
	})
	const favoriteIconClass = computed(() => (isFavorite.value ? 'icon-favorfill' : 'icon-favor'))
	const specCountText = computed(() => `${skuList.value.length || specThumbImages.value.length}项`)
	const serviceItems = computed(() => {
		const services = Array.isArray(detail.value.services) ? detail.value.services : []
		return services.map((item) => ({
			id: Number(item.id || 0),
			title: String(item.name || ''),
			desc: String(item.description || '')
		})).filter((item) => item.id > 0 && item.title)
	})
	const serviceSummaryText = computed(() => {
		const names = serviceItems.value.map((item) => item.title)
		return names.join(' · ')
	})
	const paramItems = computed(() => {
		const attributes = Array.isArray(detail.value.attributes) ? detail.value.attributes : []
		return attributes.map((item, index) => ({
			id: String(index),
			label: String(item.name || ''),
			value: String(item.value || '')
		})).filter((item) => item.label && item.value)
	})
	const paramSummaryText = computed(() => {
		const items = paramItems.value.slice(0, 3).map((item) => `${item.label}：${item.value}`)
		return items.join(' · ')
	})
	const couponTags = computed(() => productCoupons.value.slice(0, 4).map((item) => ({
		id: Number(item.id || 0),
		text: buildCouponTag(item)
	})).filter((item) => item.id > 0 && item.text))
	const normalizedProductCoupons = computed(() => {
		const list = Array.isArray(productCoupons.value) ? productCoupons.value : []
		return list.map((item) => normalizeProductCoupon(item)).filter((item) => item.id > 0)
	})
	const currentCouponOrderAmount = computed(() => {
		const unitPrice = selectedSku.value ? Number(selectedSku.value.price || 0) : Number(detail.value.price || 0)
		return Math.max(0, unitPrice) * Math.max(1, Number(quantity.value || 1))
	})
	const bestOrderCoupon = computed(() => resolveBestOrderCoupon(normalizedProductCoupons.value))
	const hasReceivableBestCoupon = computed(() => {
		const coupon = bestOrderCoupon.value
		return Boolean(coupon && !coupon.received)
	})
	const primaryBuyButtonText = computed(() => (hasReceivableBestCoupon.value ? '领取并下单' : '立即购买'))
	const skuPopupActions = computed(() => {
		if (pendingAction.value === 'couponBuy') {
			return [{
				type: 'couponBuy',
				text: primaryBuyButtonText.value,
				className: 'sku-submit--buy'
			}]
		}

		return [{
				type: 'cart',
				text: '加入购物车',
				className: 'sku-submit--cart'
			},
			{
				type: bestOrderCoupon.value ? 'couponBuy' : 'buy',
				text: primaryBuyButtonText.value,
				className: 'sku-submit--buy'
			}
		]
	})
	const productCouponOptions = computed(() => {
		const bestCouponId = Number(bestOrderCoupon.value && bestOrderCoupon.value.id || 0)
		return normalizedProductCoupons.value.map((item) => ({
			...item,
			actionText: item.actionDisabled ? item.actionText : resolveCouponActionText(item, bestCouponId)
		}))
	})
	const reviewTotalText = computed(() => `评价（${Number(reviewSummary.value.total || 0)}）`)
	const reviewRateText = computed(() => `${Number(reviewSummary.value.good_rate || 0)}%好评率`)
	const selectedSpecName = computed(() => {
		return specGroups.value.map((group) => {
			const item = group.options.find((option) => option.value === selected.value[group.key])
			return item ? item.label : ''
		}).filter(Boolean).join(' ')
	})
	const selectedUnique = computed(() => selectedSku.value ? String(selectedSku.value.uniqid || '') : '')

	/**
	 * 从页面参数中捕获分销上级 ID。
	 *
	 * @param {Object} options 页面进入参数。
	 * @returns {string} 保存成功的上级 ID。
	 */
	function captureSpreadFromPageOptions(options = {}) {
		const params = options && typeof options === 'object' ? options : {}
		const spreadUid = extractSpreadUid(params) || extractSpreadUidFromScene(params.scene)
		return saveSpreadUid(spreadUid)
	}

	/**
	 * 读取当前登录用户 ID，用于分享链路绑定下级。
	 *
	 * @returns {number} 当前登录用户 ID。
	 */
	function resolveCurrentShareUserId() {
		const user = getUserInfo()
		return Number(user.uid ?? user.user_id ?? user.userId ?? user.id ?? 0)
	}

	/**
	 * 刷新当前分享用户 ID。
	 *
	 * @returns {number} 当前登录用户 ID。
	 */
	function refreshCurrentShareUserId() {
		const userId = resolveCurrentShareUserId()
		currentShareUserId.value = userId > 0 ? userId : 0
		return currentShareUserId.value
	}

	/**
	 * 构建商品详情分享路径。
	 *
	 * @returns {string} 带商品 ID 和分销上级 ID 的分享路径。
	 */
	function buildSharePagePath() {
		const id = Number(detail.value.id || productId.value || 0)
		const params = []
		if (id > 0) {
			params.push(`id=${encodeURIComponent(id)}`)
		}
		if (currentShareUserId.value > 0) {
			params.push(`spread_uid=${encodeURIComponent(currentShareUserId.value)}`)
		}

		return `/pages/goods/detail/index${params.length ? `?${params.join('&')}` : ''}`
	}

	onLoad((options = {}) => {
		captureSpreadFromPageOptions(options)
		refreshCurrentShareUserId()
		productId.value = Number(options.id || 0)
		fetchDetail()
	})

	onShow(() => {
		refreshCurrentShareUserId()
		fetchFavoriteStatus()
	})

	onPageScroll((event = {}) => {
		const scrollTop = Number(event.scrollTop || 0)
		navPinned.value = scrollTop > 16
	})

	onHide(() => { closeTopMenu(); closeSharePopup() })
	onUnload(() => { closeTopMenu(); closeSharePopup() })

	onShareAppMessage(() => {
		refreshCurrentShareUserId()
		return {
			title: shareProductName.value,
			path: sharePagePath.value,
			imageUrl: shareProductImage.value
		}
	})

	onShareTimeline(() => {
		const id = Number(detail.value.id || 0)
		const params = []
		if (id > 0) {
			params.push(`id=${encodeURIComponent(id)}`)
		}
		const spreadUid = resolveCurrentShareUserId()
		if (spreadUid > 0) {
			params.push(`spread_uid=${encodeURIComponent(spreadUid)}`)
		}

		return {
			title: shareProductName.value,
			query: params.join('&'),
			imageUrl: shareProductImage.value
		}
	})

	/**
	 * 拉取商品详情与详情页关联数据。
	 *
	 * @returns {Promise<void>} 无返回值。
	 */
	function fetchDetail() {
		if (productId.value <= 0) {
			detail.value = createEmptyDetail()
			isFavorite.value = false
			loading.value = false
			return
		}

		loading.value = true
		getProductDetail({
			id: productId.value
		}).then((res) => {
			if (res.code !== 200) {
				detail.value = createEmptyDetail()
				return
			}
			if (String(res.data?.plugin_code || '').toLowerCase() === 'hotel') {
				getHotelByProduct(productId.value).then(response => {
					if (response.code !== 200 || !response.data?.id) throw new Error(response.msg || '酒店已下架')
					uni.redirectTo({ url: `/plugins/hotel/pages/detail/index?id=${response.data.id}` })
				}).catch(failure => showToast(resolveErrorMessage(failure)))
				return
			}

			detail.value = normalizeDetail(res.data)
			isFavorite.value = detail.value.is_favorite
			applyDefaultSkuSelection(detail.value.sku_data)
			fetchFavoriteStatus()
			fetchProductCoupons()
			fetchProductReviews()
		}).catch((res) => {
			showToast(resolveErrorMessage(res))
			detail.value = createEmptyDetail()
			productCoupons.value = []
			reviews.value = []
			isFavorite.value = false
			reviewSummary.value = {
				total: 0,
				good_count: 0,
				good_rate: 0
			}
		}).finally(() => {
			loading.value = false
		})
	}

	/**
	 * 拉取当前商品收藏状态。
	 *
	 * @returns {Promise<void>} 无返回值。
	 */
	function fetchFavoriteStatus() {
		if (productId.value <= 0) {
			isFavorite.value = false
			return Promise.resolve()
		}

		return getProductFavoriteStatus({
			product_id: productId.value
		}, {
			loginRedirect: false
		}).then((res) => {
			if (res.code === 200 && res.data) {
				isFavorite.value = Boolean(res.data.is_favorite)
			}
		}).catch(() => {
			isFavorite.value = false
		})
	}

	/**
	 * 规范化商品详情商户/平台展示信息。
	 *
	 * @param {Object} data 接口返回的店铺资料。
	 * @returns {Object} 可直接渲染的店铺资料。
	 */
	function normalizeStoreProfile(data = {}) {
		const profile = data && typeof data === 'object' ? data : {}
		const merchantId = Math.max(0, Number(profile.merchant_id || profile.id || 0))
		const name = String(profile.name || profile.mer_name || '').trim()
		const avatar = String(profile.avatar || profile.logo || '').trim()
		const description = String(profile.description || '').trim()
		const rawRating = Number(profile.rating || profile.rating_text || 0)
		const rating = rawRating > 0 ? Math.max(0, Math.min(5, rawRating)) : 4
		const ratingText = rating.toFixed(1)
		const roundedRating = Math.round(rating)
		const followText = String(profile.follow_count_text || profile.follow_count || '0')
		const prefixText = merchantId > 0 ? `粉丝数 ${followText}` : '平台自营'
		const metaText = description ? `${prefixText} | ${description}` : prefixText
		const fallbackInitial = merchantId > 0 ? '店' : '自'
		const initial = name ? name.slice(0, 1) : fallbackInitial

		return {
			visible: Boolean(name || avatar || description),
			id: merchantId,
			merchant_id: merchantId,
			type: String(profile.type || (merchantId > 0 ? 'merchant' : 'platform')),
			name: name || (merchantId > 0 ? '店铺' : '平台自营'),
			avatar,
			initial,
			description,
			metaText,
			rating,
			ratingText,
			badge: String(profile.badge || '').trim(),
			canEnter: Boolean(profile.can_enter) && merchantId > 0,
			detailUrl: String(profile.detail_url || '').trim(),
			stars: [1, 2, 3, 4, 5].map((item) => ({
				id: item,
				active: item <= roundedRating
			}))
		}
	}

	/**
	 * 规范化商品详情接口返回，补齐页面展示所需字段。
	 *
	 * @param {Object} data 商品详情接口数据。
	 * @returns {Object} 页面详情数据。
	 */
	function normalizeDetail(data = {}) {
		const imageList = normalizeImages(data.pics, data.cover)
		const displaySales = data.display_sales ?? (Number(data.sales || 0) + Number(data.virtual_sales || 0))
		const rawTagList = Array.isArray(data.tag_list) && data.tag_list.length ? data.tag_list : (data.tag_list || data.tags || [])
		return {
			id: Number(data.id || 0),
			merchant_id: Math.max(0, Number(data.merchant_id || data.mer_id || 0)),
			name: String(data.name || '').trim(),
			cover: String(data.cover || '').trim(),
			thumb: imageList[0] || String(data.cover || '').trim(),
			images: imageList,
			description: String(data.description || '').trim(),
			detail: String(data.detail || '').trim(),
			price: Number(data.price || 0),
			ot_price: Number(data.ot_price || 0),
			priceText: formatMoney(Number(data.price || 0)),
			originalPriceText: Number(data.ot_price || 0) > Number(data.price || 0) ? formatMoney(Number(data.ot_price || 0)) : '',
			stock: Number(data.stock || 0),
			sales: Number(displaySales || 0),
			view_count: Number(data.display_view_count || 0),
			is_favorite: Boolean(data.is_favorite),
			tag_ids: Array.isArray(data.tag_ids) ? data.tag_ids : [],
			tag_list: normalizeDisplayTags(rawTagList, 2),
			store_profile: normalizeStoreProfile(data.store_profile),
			services: Array.isArray(data.services) ? data.services : [],
			attributes: Array.isArray(data.attributes) ? data.attributes : [],
			sku_data: normalizeSkuData(data.sku_data)
		}
	}
	/**
	 * 生成空商品详情数据。
	 *
	 * @returns {Object} 空商品详情。
	 */
	function createEmptyDetail() {
		return {
			id: 0,
			merchant_id: 0,
			name: '',
			cover: '',
			thumb: '',
			images: [],
			description: '',
			detail: '',
			price: 0,
			ot_price: 0,
			priceText: '0.00',
			originalPriceText: '',
			stock: 0,
			sales: 0,
			view_count: 0,
			is_favorite: false,
			tag_ids: [],
			tag_list: [],
			store_profile: normalizeStoreProfile(),
			services: [],
			attributes: [],
			sku_data: {
				spec_list: [],
				sku_list: [],
				default_sku: null
			}
		}
	}

	/**
	 * 解析详情接口中的图片列表。
	 *
	 * @param {Array|string} rawImages 接口返回的图片数组或 JSON 字符串。
	 * @param {string} cover 兜底封面。
	 * @returns {Array<string>} 可渲染图片列表。
	 */
	function normalizeImages(rawImages, cover = '') {
		let images = []
		if (Array.isArray(rawImages)) {
			images = rawImages
		} else if (typeof rawImages === 'string' && rawImages.trim()) {
			try {
				const parsed = JSON.parse(rawImages)
				images = Array.isArray(parsed) ? parsed : [rawImages]
			} catch (error) {
				images = [rawImages]
			}
		}

		const coverValue = String(cover || '').trim()
		if (coverValue) {
			images.unshift(coverValue)
		}

		return images.map((item) => String(item || '').trim()).filter(Boolean)
	}

	/**
	 * 规范化规格接口数据。
	 *
	 * @param {Object} data 商品详情内 sku_data。
	 * @returns {Object} 可消费规格数据。
	 */
	function normalizeSkuData(data = {}) {
		const source = data && typeof data === 'object' ? data : {}
		return {
			product_id: Number(source.product_id || 0),
			spec_type: Number(source.spec_type || 0),
			spec_list: Array.isArray(source.spec_list) ? source.spec_list : [],
			sku_list: Array.isArray(source.sku_list) ? source.sku_list : [],
			default_sku: source.default_sku && typeof source.default_sku === 'object' ? source.default_sku : null
		}
	}

	/**
	 * 根据默认 SKU 初始化规格选中态。
	 *
	 * @param {Object} skuData 规格数据。
	 * @returns {void}
	 */
	function applyDefaultSkuSelection(skuData = {}) {
		const defaultSku = skuData.default_sku && typeof skuData.default_sku === 'object' ? skuData.default_sku : null
		const specKey = defaultSku ? String(defaultSku.spec_key || '') : ''
		const valueIds = specKey ? specKey.split('||') : []
		const nextSelected = {}
		const specList = Array.isArray(skuData.spec_list) ? skuData.spec_list : []
		specList.forEach((group, index) => {
			const groupKey = String(group.spec_id || '')
			const valueId = String(valueIds[index] || '')
			if (groupKey && valueId) {
				nextSelected[groupKey] = valueId
			}
		})

		selected.value = nextSelected
		const firstSku = Array.isArray(skuData.sku_list) && skuData.sku_list.length > 0 ? skuData.sku_list[0] : null
		selectedSpecImageId.value = String(defaultSku && (defaultSku.id || defaultSku.uniqid) || (firstSku && (firstSku.id || firstSku.uniqid)) || '')
		quantity.value = 1
	}

	/**
	 * 规范化规格值列表。
	 *
	 * @param {Array<Object>} values 后端规格值。
	 * @returns {Array<Object>} 弹窗规格值。
	 */
	function normalizeSpecValues(values = []) {
		if (!Array.isArray(values)) {
			return []
		}

		return values.map((item) => {
			const value = String(item.id || '')
			return {
				label: String(item.value || ''),
				value,
				image: findImageForSpecValue(value, String(item.pic || '').trim()),
				priceText: findPriceTextForSpecValue(value),
				disabled: item.available === false
			}
		}).filter((item) => item.label && item.value)
	}

	/**
	 * 根据规格值 ID 查找后端 SKU 图片。
	 *
	 * @param {string} valueId 规格值 ID。
	 * @param {string} explicitImage 规格值图片。
	 * @returns {string} 图片地址。
	 */
	function findImageForSpecValue(valueId, explicitImage = '') {
		if (explicitImage) {
			return explicitImage
		}

		const target = String(valueId || '')
		if (!target) {
			return ''
		}

		const sku = skuList.value.find((item) => String(item.spec_key || '').split('||').includes(target) && String(item.pics || '').trim())
		return sku ? String(sku.pics || '').trim() : ''
	}

	/**
	 * 根据规格值 ID 查找对应 SKU 售价。
	 *
	 * @param {string} valueId 规格值 ID。
	 * @returns {string} 价格文案。
	 */
	function findPriceTextForSpecValue(valueId) {
		const target = String(valueId || '')
		const sku = skuList.value.find((item) => String(item.spec_key || '').split('||').includes(target))
		return sku ? formatMoney(Number(sku.price || 0)) : priceText.value
	}

	/**
	 * 拉取当前商品可用优惠券。
	 *
	 * @returns {Promise<void>} 无返回值。
	 */
	function fetchProductCoupons() {
		return getProductCoupons({
			id: productId.value
		}, {
			loginRedirect: false
		}).then((res) => {
			if (res.code !== 200) {
				productCoupons.value = []
				return
			}

			productCoupons.value = Array.isArray(res.data && res.data.list) ? res.data.list : []
		}).catch((res) => {
			productCoupons.value = []
			showToast(resolveErrorMessage(res))
		})
	}

	/**
	 * 拉取当前商品高分评价。
	 *
	 * @returns {Promise<void>} 无返回值。
	 */
	function fetchProductReviews() {
		return getProductReviews({
			id: productId.value,
			page: 1,
			limit: 3,
			star_min: 4
		}).then((res) => {
			if (res.code !== 200) {
				reviews.value = []
				reviewSummary.value = {
					total: 0,
					good_count: 0,
					good_rate: 0
				}
				return
			}

			reviews.value = normalizeReviews(res.data.list)
			reviewSummary.value = normalizeReviewSummary(res.data.summary)
		}).catch((res) => {
			reviews.value = []
			reviewSummary.value = {
				total: 0,
				good_count: 0,
				good_rate: 0
			}
			showToast(resolveErrorMessage(res))
		})
	}

	/**
	 * 规范化评价统计。
	 *
	 * @param {Object} summary 评价统计。
	 * @returns {Object} 页面评价统计。
	 */
	function normalizeReviewSummary(summary = {}) {
		return {
			total: Number(summary.total || 0),
			good_count: Number(summary.good_count || 0),
			good_rate: Number(summary.good_rate || 0)
		}
	}

	/**
	 * 规范化评价列表。
	 *
	 * @param {Array<Object>} list 评价列表。
	 * @returns {Array<Object>} 页面评价列表。
	 */
	function normalizeReviews(list = []) {
		return list.map((item) => {
			const user = item.user && typeof item.user === 'object' ? item.user : {}
			const star = Math.max(0, Math.min(5, Number(item.star || 0)))
			return {
				id: Number(item.id || 0),
				name: String(user.nickname || ''),
				avatar: String(user.avatar || '').trim(),
				star,
				stars: Array.from({ length: star }, (_, index) => index + 1),
				content: String(item.content || ''),
				images: Array.isArray(item.images) ? item.images : [],
				sku_name: String(item.sku_name || ''),
				create_time: String(item.create_time || '')
			}
		}).filter((item) => item.id > 0)
	}

	/**
	 * 从请求异常对象中解析错误提示。
	 *
	 * @param {Object|string|Error} error 请求异常或错误响应。
	 * @param {string} fallbackMessage 默认提示。
	 * @returns {string} 错误提示。
	 */
	function resolveErrorMessage(error, fallbackMessage = '请求失败') {
		if (typeof error === 'string' && error.trim()) {
			return error.trim()
		}

		if (!error || typeof error !== 'object') {
			return fallbackMessage
		}

		if (error.msg) {
			return String(error.msg)
		}

		if (error.message) {
			return String(error.message)
		}

		if (error.errMsg) {
			return String(error.errMsg)
		}

		if (error.error && error.error.errMsg) {
			return String(error.error.errMsg)
		}

		return fallbackMessage
	}

	/**
	 * 构建优惠券短标签。
	 *
	 * @param {Object} coupon 优惠券数据。
	 * @returns {string} 短标签。
	 */
	function buildCouponTag(coupon = {}) {
		const amount = Number(coupon.discount_amount || 0)
		const threshold = Number(coupon.min_order_amount || 0)
		if (amount <= 0) {
			return ''
		}

		return threshold > 0 ? `满${formatAmount(threshold)}减${formatAmount(amount)}` : `减${formatAmount(amount)}`
	}

	/**
	 * 规范化商品详情优惠券弹窗展示项。
	 *
	 * @param {Object} coupon 商品优惠券接口返回项。
	 * @returns {Object} 可直接渲染的优惠券展示项。
	 */
	function normalizeProductCoupon(coupon = {}) {
		const id = Number(coupon.coupon_id || coupon.id || 0)
		const userCouponId = Number(coupon.user_coupon_id || coupon.userCouponId || 0)
		const merchantId = Math.max(0, Number(coupon.merchant_id || 0))
		const received = Boolean(coupon.received)
		const loading = receivingCouponId.value === id
		const threshold = Number(coupon.min_order_amount || 0)
		const discount = Number(coupon.discount_amount || 0)
		const scopeText = String(coupon.scope_type_text || coupon.tag || '当前商品可用')
		const expireText = String(coupon.expire_text || buildProductCouponExpireText(coupon))
		const title = String(coupon.name || coupon.title || '商品优惠券')
		return {
			id,
			coupon_id: id,
			user_coupon_id: userCouponId,
			merchant_id: merchantId,
			name: title,
			title,
			amount: formatAmount(discount),
			condition: threshold > 0 ? `满${formatAmount(threshold)}可用` : '无门槛',
			discount_amount: discount,
			min_order_amount: threshold,
			discountText: formatAmount(discount),
			thresholdText: threshold > 0 ? `满 ¥${formatAmount(threshold)} 可用` : '无门槛可用',
			scopeText,
			expireText,
			expireTime: expireText,
			tag: received ? '已领取' : '限时领取',
			desc: scopeText || '当前商品可用',
			buttonClass: 'coupon-card__button--red',
			iconClass: 'coupon-card__time-icon--red',
			received,
			actionText: loading ? '领取中' : (received ? '立即使用' : '领取'),
			actionDisabled: loading
		}
	}

	/**
	 * 根据当前规格和数量筛选最省钱的下单优惠券。
	 *
	 * @param {Array<Object>} coupons 优惠券展示项。
	 * @returns {Object|null} 最优优惠券。
	 */
	function resolveBestOrderCoupon(coupons = []) {
		const orderAmount = Number(currentCouponOrderAmount.value || 0)
		if (orderAmount <= 0) {
			return null
		}

		return coupons.map((item) => {
			const discountAmount = Number(item.discount_amount || 0)
			const threshold = Number(item.min_order_amount || 0)
			const discountValue = threshold <= orderAmount ? Math.min(orderAmount, discountAmount) : 0
			return {
				...item,
				discountValue
			}
		}).filter((item) => item.discountValue > 0).sort((left, right) => {
			return Number(right.discountValue || 0) - Number(left.discountValue || 0)
				|| Number(right.min_order_amount || 0) - Number(left.min_order_amount || 0)
				|| Number(right.id || 0) - Number(left.id || 0)
		})[0] || null
	}

	/**
	 * 解析商品详情优惠券按钮文案。
	 *
	 * @param {Object} coupon 优惠券展示项。
	 * @param {number} bestCouponId 当前最优券 ID。
	 * @returns {string} 操作文案。
	 */
	function resolveCouponActionText(coupon = {}, bestCouponId = 0) {
		if (Number(coupon.id || 0) === Number(bestCouponId || 0)) {
			return coupon.received ? '立即使用' : '领取并下单'
		}

		return coupon.received ? '立即使用' : '领取'
	}

	/**
	 * 构建优惠券有效期文案兜底。
	 *
	 * @param {Object} coupon 商品优惠券接口返回项。
	 * @returns {string} 有效期文案。
	 */
	function buildProductCouponExpireText(coupon = {}) {
		const validDays = Number(coupon.valid_days || 0)
		const validStartTime = String(coupon.valid_start_time || '').trim()
		const validEndTime = String(coupon.valid_end_time || '').trim()
		if (validDays > 0) {
			return `领取后${validDays}天内有效`
		}
		if (validStartTime || validEndTime) {
			return `${validStartTime || '即刻'} 至 ${validEndTime || '长期'}`
		}
		return '有效期以券面为准'
	}

	/**
	 * 格式化金额。
	 *
	 * @param {number} value 金额。
	 * @returns {string} 两位小数字符串。
	 */
	function formatMoney(value) {
		const number = Number(value || 0)
		return number > 0 ? number.toFixed(2) : '0.00'
	}

	/**
	 * 格式化优惠券金额标签。
	 *
	 * @param {number} value 金额。
	 * @returns {string} 去尾零金额。
	 */
	function formatAmount(value) {
		const text = formatMoney(value)
		return text.replace(/\.00$/, '').replace(/(\.\d)0$/, '$1')
	}

	/**
	 * 切换 SKU 缩略图选中态。
	 *
	 * @param {Object} item 缩略图数据。
	 * @returns {void}
	 */
	function selectPreview(item = {}) {
		const skuId = String(item.id || '')
		const sku = skuList.value.find((entry) => String(entry.id || '') === skuId) || null
		const specKey = String(item.specKey || (sku ? sku.spec_key || '' : ''))
		const valueIds = specKey ? specKey.split('||') : []
		const nextSelected = {}
		specGroups.value.forEach((group, index) => {
			const groupKey = String(group.key || '')
			const valueId = String(valueIds[index] || '')
			if (groupKey && valueId) {
				nextSelected[groupKey] = valueId
			}
		})

		selectedSpecImageId.value = skuId
		if (!Object.keys(nextSelected).length) {
			return
		}

		selected.value = {
			...selected.value,
			...nextSelected
		}
	}

	/**
	 * 同步顶部主图轮播滑动后的索引。
	 *
	 * @param {Object} event swiper change 事件。
	 * @returns {void}
	 */
	function changeHeroPreview(event) {
		const index = Number(event.detail && event.detail.current ? event.detail.current : 0)
		heroCurrentIndex.value = index
	}

	/**
	 * 根据轮播图原始比例同步顶部区域高度。
	 *
	 * @param {Object} event 图片加载事件。
	 * @param {number} index 轮播图索引。
	 * @returns {void}
	 */
	function handleHeroImageLoad(event, index) {
		const detailData = event.detail || {}
		const width = Number(detailData.width || 0)
		const height = Number(detailData.height || 0)
		if (width <= 0 || height <= 0) {
			return
		}

		heroImageHeights.value = {
			...heroImageHeights.value,
			[index]: Math.round((750 * height) / width)
		}
	}

	/**
	 * 点击规格缩略图，未选中时切换规格，已选中时打开规格图全屏遮罩。
	 *
	 * @param {Object} item 缩略图数据。
	 * @returns {void}
	 */
	function handlePreviewThumbClick(item = {}) {
		if (!item.selected) {
			selectPreview(item)
			return
		}

		openSpecImagePreviewById(item.id)
	}

	/**
	 * 打开商品轮播图全屏预览。
	 *
	 * @returns {void}
	 */
	function openGalleryPreview() {
		if (!heroImages.value.length) {
			return
		}

		closeTopMenu()
		galleryPreviewIndex.value = Math.max(0, heroCurrentIndex.value)
		showSpecImagePreview.value = false
		showGalleryPreview.value = true
	}

	/**
	 * 关闭商品轮播图全屏预览。
	 *
	 * @returns {void}
	 */
	function closeGalleryPreview() {
		showGalleryPreview.value = false
	}

	/**
	 * 同步全屏预览滑动后的主图索引。
	 *
	 * @param {number} index 当前图片索引。
	 * @returns {void}
	 */
	function changeGalleryPreview(index) {
		const nextIndex = Math.max(0, Number(index || 0))
		galleryPreviewIndex.value = nextIndex
		heroCurrentIndex.value = nextIndex
	}

	/**
	 * 按规格图标识打开规格图全屏遮罩。
	 *
	 * @param {string} id 规格图标识。
	 * @returns {void}
	 */
	function openSpecImagePreviewById(id) {
		const index = specPreviewItems.value.findIndex((item) => item.id === String(id || ''))
		closeTopMenu()
		specImagePreviewIndex.value = Math.max(0, index)
		showGalleryPreview.value = false
		showSpecImagePreview.value = true
	}

	/**
	 * 关闭规格图全屏遮罩。
	 *
	 * @returns {void}
	 */
	function closeSpecImagePreview() {
		showSpecImagePreview.value = false
	}

	/**
	 * 同步规格图全屏遮罩滑动后的缩略图索引。
	 *
	 * @param {number} index 当前图片索引。
	 * @returns {void}
	 */
	function changeSpecImagePreview(index) {
		const nextIndex = Math.max(0, Number(index || 0))
		const item = specPreviewItems.value[nextIndex]
		specImagePreviewIndex.value = nextIndex
		if (item) {
			selectPreview(item)
		}
	}

	/**
	 * 打开规格弹窗并记录后续动作。
	 *
	 * @param {string} action 触发动作：select/cart/buy/couponBuy。
	 * @returns {void}
	 */
	function openSkuPopup(action = 'select') {
		closeTopMenu()
		showCouponPopup.value = false
		if (action !== 'couponBuy') {
			checkoutCoupon.value = null
		}
		pendingAction.value = action
		showSkuPopup.value = true
	}

	/**
	 * 关闭规格弹窗。
	 *
	 * @returns {void}
	 */
	function closeSkuPopup() {
		showSkuPopup.value = false
		pendingAction.value = 'select'
		checkoutCoupon.value = null
	}

	/**
	 * 打开主购买规格弹窗。
	 *
	 * @returns {void}
	 */
	function openPrimaryBuy() {
		if (submitLoading.value || receivingCouponId.value > 0) {
			return
		}

		checkoutCoupon.value = null
		openSkuPopup(bestOrderCoupon.value ? 'couponBuy' : 'buy')
	}

	/**
	 * 使用指定优惠券进入下单规格弹窗。
	 *
	 * @param {Object} coupon 优惠券展示项。
	 * @returns {void}
	 */
	function openCouponBuy(coupon = {}) {
		const couponId = Number(coupon.id || coupon.coupon_id || 0)
		if (couponId <= 0) {
			return
		}

		checkoutCoupon.value = {
			...coupon
		}
		closeCouponPopup()
		openSkuPopup('couponBuy')
	}

	/**
	 * 打开服务说明弹窗。
	 *
	 * @returns {void}
	 */
	function openServicePopup() {
		closeTopMenu()
		showCouponPopup.value = false
		showParamPopup.value = false
		showServicePopup.value = true
	}

	/**
	 * 关闭服务说明弹窗。
	 *
	 * @returns {void}
	 */
	function closeServicePopup() {
		showServicePopup.value = false
	}

	/**
	 * 打开参数说明弹窗。
	 *
	 * @returns {void}
	 */
	function openParamPopup() {
		closeTopMenu()
		showCouponPopup.value = false
		showServicePopup.value = false
		showParamPopup.value = true
	}

	/**
	 * 关闭参数说明弹窗。
	 *
	 * @returns {void}
	 */
	function closeParamPopup() {
		showParamPopup.value = false
	}

	/**
	 * 打开商品优惠券弹窗。
	 *
	 * @returns {void}
	 */
	function openCouponPopup() {
		if (showCouponPopup.value) {
			return
		}

		closeTopMenu()
		showServicePopup.value = false
		showParamPopup.value = false
		showSharePopup.value = false
		showCouponPopup.value = true
	}

	/**
	 * 关闭商品优惠券弹窗。
	 *
	 * @returns {void}
	 */
	function closeCouponPopup() {
		showCouponPopup.value = false
	}

	/**
	 * 处理商品详情优惠券操作。
	 *
	 * @param {Object} coupon 优惠券展示项。
	 * @returns {void}
	 */
	function handleProductCouponAction(coupon = {}) {
		if (coupon.actionDisabled) {
			return
		}
		if (coupon.received) {
			openCouponBuy(coupon)
			return
		}

		if (Number(coupon.id || 0) === Number(bestOrderCoupon.value && bestOrderCoupon.value.id || 0)) {
			openCouponBuy(coupon)
			return
		}

		receiveProductCoupon(coupon)
	}

	/**
	 * 领取商品详情优惠券。
	 *
	 * @param {Object} coupon 优惠券展示项。
	 * @param {Object} options 领取选项。
	 * @returns {Promise<Object|null>} 领取成功后的用户券数据。
	 */
	function receiveProductCoupon(coupon = {}, options = {}) {
		const couponId = Number(coupon.coupon_id || coupon.id || 0)
		if (couponId <= 0 || receivingCouponId.value > 0) {
			return Promise.resolve(null)
		}

		const showSuccess = options.showSuccess !== false
		receivingCouponId.value = couponId
		return receiveCoupon({
			coupon_id: couponId
		}).then((res) => {
			if (res.code !== 200) {
				showToast(resolveErrorMessage(res, '领取失败'))
				return null
			}

			if (showSuccess) {
				showToast('领取成功')
			}
			const receivedCoupon = normalizeReceivedCoupon(coupon, res.data)
			productCoupons.value = productCoupons.value.map((item) => {
				const itemId = Number(item.coupon_id || item.id || 0)
				return itemId === couponId ? {
					...item,
					received: true,
					user_coupon_id: receivedCoupon.user_coupon_id,
					merchant_id: receivedCoupon.merchant_id
				} : item
			})
			fetchProductCoupons()
			return receivedCoupon
		}).catch((res) => {
			showToast(resolveErrorMessage(res, '领取失败'))
			return null
		}).finally(() => {
			receivingCouponId.value = 0
		})
	}

	/**
	 * 规范化领取接口返回的用户券数据。
	 *
	 * @param {Object} coupon 商品券展示项。
	 * @param {Object} data 领取接口返回 data。
	 * @returns {Object} 可用于订单确认的优惠券数据。
	 */
	function normalizeReceivedCoupon(coupon = {}, data = {}) {
		const payload = data && typeof data === 'object' ? data : {}
		return {
			...coupon,
			received: true,
			user_coupon_id: Number(payload.id || payload.user_coupon_id || coupon.user_coupon_id || 0),
			merchant_id: Math.max(0, Number(payload.merchant_id ?? coupon.merchant_id ?? 0))
		}
	}

	/**
	 * 打开商品分享弹窗，并在 H5 端生成当前商品详情二维码。
	 *
	 * @returns {void}
	 */
	function openSharePopup() {
		closeTopMenu()
		showSkuPopup.value = false
		showCouponPopup.value = false
		showServicePopup.value = false
		showParamPopup.value = false
		showGalleryPreview.value = false
		showSpecImagePreview.value = false
		showSharePopup.value = true
		refreshCurrentShareUserId()
	}

	/**
	 * 关闭商品分享弹窗。
	 *
	 * @returns {void}
	 */
	function closeSharePopup() {
		showSharePopup.value = false
	}

	/**
	 * 切换顶部快捷菜单展示状态。
	 *
	 * @returns {void}
	 */
	function toggleTopMenu() {
		showTopMenu.value = !showTopMenu.value
	}

	/**
	 * 关闭顶部快捷菜单。
	 *
	 * @returns {void}
	 */
	function closeTopMenu() {
		showTopMenu.value = false
	}


	function navigateTo(url) {
		uni.navigateTo({
			url
		})
	}

	/**
	 * 同步规格弹窗回传的规格选择。
	 *
	 * @param {Object} payload 规格选择事件参数。
	 * @param {string} payload.type 规格类型。
	 * @param {string} payload.value 规格值。
	 * @returns {void}
	 */
	function selectOption(payload = {}) {
		const type = payload.type
		const value = payload.value
		selected.value = {
			...selected.value,
			[type]: value
		}
		selectedSpecImageId.value = String(selectedSku.value && (selectedSku.value.id || selectedSku.value.uniqid) || '')
		quantity.value = Math.min(maxQuantity.value, quantity.value)
	}

	/**
	 * 同步规格弹窗回传的购买数量。
	 *
	 * @param {number} value 购买数量。
	 * @returns {void}
	 */
	function setQuantity(value) {
		quantity.value = Math.min(maxQuantity.value, Math.max(1, Number(value || 1)))
	}

	/**
	 * 提交当前规格动作。
	 *
	 * @param {string} action 提交动作：cart/buy/couponBuy。
	 * @returns {Promise<void>} 无返回值。
	 */
	function submitSkuAction(action = pendingAction.value) {
		if (submitLoading.value) {
			return
		}

		if (!validateBeforeSubmit()) {
			return
		}

		submitLoading.value = true
		const submitTask = resolveSubmitTask(action)
		submitTask.finally(() => {
			submitLoading.value = false
		})
	}

	/**
	 * 根据规格弹窗动作分发提交任务。
	 *
	 * @param {string} action 提交动作。
	 * @returns {Promise<void>} 提交任务。
	 */
	function resolveSubmitTask(action = 'buy') {
		if (action === 'cart') {
			return handleAddCart()
		}
		if (action === 'couponBuy') {
			return handleCouponBuyNow()
		}

		return handleBuyNow()
	}

	/**
	 * 加入购物车。
	 *
	 * @returns {Promise<void>} 无返回值。
	 */
	function handleAddCart() {
		return addCart(buildCartPayload()).then((res) => {
			if (res.code !== 200) {
				return
			}

			closeSkuPopup()
			showToast('已加入购物车')
		}).catch((res) => {
			showToast(resolveErrorMessage(res))
		})
	}

	/**
	 * 立即购买并跳转确认订单。
	 *
	 * @returns {Promise<void>} 无返回值。
	 */
	function handleBuyNow(options = {}) {
		return buyNow(buildCartPayload()).then((res) => {
			if (res.code !== 200) {
				return
			}

			const cartIds = collectCartIds(res.data)
			if (!cartIds.length) {
				showToast('未获取到结算商品')
				return
			}

			closeSkuPopup()
			uni.navigateTo({
				url: buildConfirmOrderUrl(cartIds, options.coupon || null)
			})
		}).catch((res) => {
			showToast(resolveErrorMessage(res))
		})
	}

	/**
	 * 领取最优优惠券后立即购买。
	 *
	 * @returns {Promise<void>} 无返回值。
	 */
	async function handleCouponBuyNow() {
		let coupon = checkoutCoupon.value || bestOrderCoupon.value
		if (!coupon) {
			return handleBuyNow()
		}

		coupon = {
			...coupon
		}
		if (!coupon.received || Number(coupon.user_coupon_id || 0) <= 0) {
			const receivedCoupon = await receiveProductCoupon(coupon, {
				showSuccess: false
			})
			if (!receivedCoupon || Number(receivedCoupon.user_coupon_id || 0) <= 0) {
				return
			}
			coupon = receivedCoupon
		}

		return handleBuyNow({
			coupon
		})
	}

	/**
	 * 构建确认订单跳转地址。
	 *
	 * @param {Array<number>} cartIds 购物车 ID。
	 * @param {Object|null} coupon 可使用的用户券数据。
	 * @returns {string} 确认订单页面地址。
	 */
	function buildConfirmOrderUrl(cartIds = [], coupon = null) {
		const params = [
			`cart_ids=${encodeURIComponent(cartIds.join(','))}`,
			'is_new=1'
		]
		const userCouponId = Number(coupon && coupon.user_coupon_id || 0)
		if (userCouponId > 0) {
			const merchantId = Math.max(0, Number(coupon && coupon.merchant_id || 0))
			params.push(`${merchantId > 0 ? 'merchant_coupon_id' : 'platform_coupon_id'}=${encodeURIComponent(userCouponId)}`)
		}

		return `/pages/orders/confirm/index?${params.join('&')}`
	}

	/**
	 * 构建购物车接口请求体。
	 *
	 * @returns {Object} 购物车请求体。
	 */
	function buildCartPayload() {
		return {
			goods_id: detail.value.id,
			cart_num: quantity.value,
			order_type: 2,
			product_attr_unique: selectedUnique.value,
			plugin_payload: pluginPayload.value
		}
	}

	/**
	 * 由插件详情插槽更新自己的商品载荷命名空间。
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

	/**
	 * 提交前校验商品、规格、库存与数量。
	 *
	 * @returns {boolean} 是否允许提交。
	 */
	function validateBeforeSubmit() {
		if (Number(detail.value.id || 0) <= 0) {
			showToast('商品信息缺失')
			return false
		}

		const missingSpec = specGroups.value.some((group) => !selected.value[group.key])
		if (missingSpec) {
			showToast('请选择完整规格')
			return false
		}

		if (!selectedSku.value || !selectedUnique.value) {
			showToast('当前规格不可购买')
			return false
		}

		if (quantity.value <= 0 || quantity.value > maxQuantity.value) {
			showToast('购买数量不正确')
			return false
		}

		return true
	}

	/**
	 * 从立即购买接口响应中提取购物车 ID。
	 *
	 * @param {Object} payload 接口响应 data。
	 * @returns {Array<number>} 购物车 ID 列表。
	 */
	function collectCartIds(payload = {}) {
		const cart = payload.cart && typeof payload.cart === 'object' ? payload.cart : {}
		const cartId = Number(cart.cart_id || 0)
		return cartId > 0 ? [cartId] : []
	}

	/**
	 * 返回上一页，失败时回到分类页。
	 *
	 * @returns {void}
	 */
	function handleBack() {
		closeTopMenu()
		uni.navigateBack({
			fail() {
				uni.switchTab({
					url: '/pages/category/index'
				})
			}
		})
	}

	/**
	 * 打开购物车。
	 *
	 * @returns {void}
	 */
	function openCart() {
		closeTopMenu()
		uni.switchTab({
			url: '/pages/cart/index'
		})
	}

	/**
	 * 进入在线客服页，商户商品携带商户 ID，平台商品传 0。
	 *
	 * @returns {void}
	 */
	function openCustomerService() {
		closeTopMenu()
		const merchantId = Math.max(0, Number(detail.value.merchant_id || 0))
		uni.navigateTo({
			url: `/pages/users/customer/index?merchant_id=${merchantId}`
		})
	}

	/**
	 * 进入当前商品所属商户店铺。
	 *
	 * @returns {void}
	 */
	function enterStoreProfile() {
		const url = String(storeProfile.value.detailUrl || '').trim()
		if (!storeProfile.value.canEnter || !url) {
			return
		}

		navigateTo(url)
	}

	/**
	 * 切换收藏状态。
	 *
	 * @returns {void}
	 */
	function toggleFavorite() {
		if (favoriteLoading.value) {
			return
		}
		if (productId.value <= 0) {
			showToast('商品信息缺失')
			return
		}

		favoriteLoading.value = true
		toggleProductFavorite({
			product_id: productId.value
		}).then((res) => {
			if (res.code !== 200) {
				showToast(resolveErrorMessage(res, '收藏操作失败'))
				return
			}

			const nextStatus = Boolean(res.data && res.data.is_favorite)
			isFavorite.value = nextStatus
			detail.value = {
				...detail.value,
				is_favorite: nextStatus
			}
			showToast(nextStatus ? '已收藏' : '已取消收藏')
		}).catch((res) => {
			showToast(resolveErrorMessage(res, '收藏操作失败'))
		}).finally(() => {
			favoriteLoading.value = false
		})
	}

	/**
	 * 触发商品分享弹窗。
	 *
	 * @returns {void}
	 */
	function shareProduct() {
		openSharePopup()
	}




































	/**
	 * 展示轻提示。
	 *
	 * @param {string} title 提示文案。
	 * @returns {void}
	 */
	function showToast(title) {
		uni.showToast({
			title,
			icon: 'none'
		})
	}
</script>

<style lang="scss">
	page {
		height: 100%;
		background: #f5f5f5;
	}
</style>

<style lang="scss" scoped>
	.store-product-detail {
		position: relative;
		min-height: 100%;
		padding-bottom: 154rpx;
		background: #f5f5f5;
	}

	.hero-section {
		position: relative;
		width: 100%;
		min-height: 360rpx;
		overflow: visible;
		background: #d9eefb;
	}

	.hero-section__swiper,
	.hero-section__slide,
	.hero-section__image {
		width: 100%;
		height: 100%;
	}

	.hero-skeleton {
		width: 100%;
		height: 750rpx;
	}

	.detail-scroll {
		position: relative;
		z-index: 3;
		margin-top: -38rpx;
		overflow: visible;
		border-top-left-radius: 34rpx;
		border-top-right-radius: 34rpx;
		background: #f5f5f5;
	}

	.detail-content {
		padding: 0 20rpx 34rpx;
	}

	.detail-content--skeleton {
		pointer-events: none;
	}

	.info-card,
	.review-card,
	.detail-card {
		background: #ffffff;
		box-shadow: 0 4rpx 12rpx rgba(15, 23, 42, 0.03);
	}

	.goods-card {
		padding: 22rpx 26rpx 28rpx;
		border-radius: 34rpx 34rpx 18rpx 18rpx;
	}

	.skeleton-block {
		background: #eef0f3;
		animation: skeleton-pulse 1200ms ease-in-out infinite;
	}

	@keyframes skeleton-pulse {
		0% {
			opacity: 0.62;
		}

		50% {
			opacity: 1;
		}

		100% {
			opacity: 0.62;
		}
	}

	.skeleton-card {
		overflow: hidden;
	}

	.skeleton-thumb-row,
	.skeleton-price-row,
	.skeleton-coupon-row,
	.skeleton-meta-row,
	.skeleton-section-row,
	.skeleton-review-row {
		display: flex;
		align-items: center;
	}

	.skeleton-thumb-row {
		height: 92rpx;
	}

	.skeleton-thumb {
		width: 76rpx;
		height: 76rpx;
		margin-right: 20rpx;
		border-radius: 12rpx;
	}

	.skeleton-thumb-more {
		width: 78rpx;
		margin-left: 4rpx;
	}

	.skeleton-more-line {
		width: 52rpx;
		height: 20rpx;
		margin-left: 10rpx;
		border-radius: 999rpx;
	}

	.skeleton-more-line--short {
		width: 42rpx;
		margin-top: 10rpx;
		margin-left: 14rpx;
	}

	.skeleton-price-row {
		margin-top: 30rpx;
	}

	.skeleton-price {
		width: 176rpx;
		height: 58rpx;
		border-radius: 10rpx;
	}

	.skeleton-tag {
		width: 92rpx;
		height: 38rpx;
		margin-left: 18rpx;
		border-radius: 999rpx;
	}

	.skeleton-origin {
		width: 94rpx;
		height: 28rpx;
		margin-left: 18rpx;
		border-radius: 8rpx;
	}

	.skeleton-coupon-row {
		flex-wrap: wrap;
		margin-top: 22rpx;
	}

	.skeleton-coupon {
		width: 112rpx;
		height: 42rpx;
		margin-right: 18rpx;
		margin-bottom: 12rpx;
		border-radius: 8rpx;
	}

	.skeleton-title {
		width: 620rpx;
		height: 36rpx;
		margin-top: 8rpx;
		border-radius: 8rpx;
	}

	.skeleton-title--short {
		width: 420rpx;
		margin-top: 18rpx;
	}

	.skeleton-meta-row {
		margin-top: 24rpx;
	}

	.skeleton-badge {
		width: 78rpx;
		height: 30rpx;
		margin-right: 18rpx;
		border-radius: 6rpx;
	}

	.skeleton-meta {
		width: 128rpx;
		height: 30rpx;
		margin-left: 16rpx;
		border-radius: 8rpx;
	}

	.skeleton-meta--right {
		margin-left: auto;
	}

	.skeleton-info-card {
		padding-top: 18rpx;
		padding-bottom: 18rpx;
	}

	.skeleton-info-row {
		display: flex;
		align-items: center;
		min-height: 82rpx;
	}

	.skeleton-info-label {
		width: 64rpx;
		height: 32rpx;
		border-radius: 8rpx;
	}

	.skeleton-info-value {
		flex: 1;
		height: 34rpx;
		margin-left: 28rpx;
		border-radius: 8rpx;
	}

	.skeleton-section-row {
		justify-content: space-between;
	}

	.skeleton-section-title {
		width: 154rpx;
		height: 40rpx;
		border-radius: 8rpx;
	}

	.skeleton-section-more {
		width: 142rpx;
		height: 36rpx;
		border-radius: 8rpx;
	}

	.skeleton-review-row {
		margin-top: 24rpx;
	}

	.skeleton-review {
		width: 210rpx;
		height: 140rpx;
		margin-right: 20rpx;
		border-radius: 14rpx;
	}

	.skeleton-detail-title {
		width: 190rpx;
		height: 42rpx;
		margin: 4rpx auto 0;
		border-radius: 8rpx;
	}

	.skeleton-detail-image {
		width: 100%;
		height: 430rpx;
		margin-top: 28rpx;
		border-radius: 12rpx;
	}

	.thumb-row {
		display: flex;
		align-items: center;
		height: 92rpx;
	}

	.thumb-list {
		flex: 1;
		width: 0;
		min-width: 0;
		height: 92rpx;
	}

	.thumb-list__inner {
		display: flex;
		flex-wrap: nowrap;
		align-items: center;
		height: 92rpx;
		white-space: nowrap;
	}

	.thumb-item {
		display: flex;
		flex-shrink: 0;
		width: 76rpx;
		height: 76rpx;
		margin-right: 20rpx;
		overflow: hidden;
		border: 2rpx solid #f0f2f5;
		border-radius: 12rpx;
		background: var(--surface-card-soft, #f4f4f5);
	}

	.thumb-item--active {
		border-color: var(--brand-primary, #ea580c);
		box-shadow: 0 4rpx 12rpx var(--brand-shadow, rgba(234, 88, 12, 0.18));
	}

	.thumb-item__image {
		width: 100%;
		height: 100%;
	}

	.thumb-more {
		display: flex;
		flex-shrink: 0;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		width: 78rpx;
		height: 76rpx;
		margin-left: 4rpx;
		color: var(--brand-primary, #ea580c);
	}

	.thumb-more__count,
	.thumb-more__label {
		font-size: 22rpx;
		line-height: 28rpx;
		font-weight: 700;
	}

	.price-row {
		display: flex;
		align-items: flex-end;
		margin-top: 30rpx;
	}

	.price-row__symbol,
	.price-row__integer,
	.price-row__decimal {
		color: var(--brand-primary, #ea580c);
		font-weight: 800;
	}

	.price-row__symbol {
		margin-bottom: 4rpx;
		font-size: 28rpx;
		line-height: 32rpx;
	}

	.price-row__integer {
		margin-left: 8rpx;
		font-size: 54rpx;
		line-height: 60rpx;
	}

	.price-row__decimal {
		margin-bottom: 3rpx;
		font-size: 30rpx;
		line-height: 34rpx;
	}

	.price-row__tag {
		display: flex;
		align-items: center;
		justify-content: center;
		height: 38rpx;
		margin-bottom: 5rpx;
		margin-left: 18rpx;
		padding: 0 18rpx;
		border-radius: 999rpx;
		background: var(--brand-primary-pale, #fff7ed);
		color: var(--text-primary, #111111);
		font-size: 24rpx;
		line-height: 36rpx;
		font-weight: 700;
	}

	.price-row__origin {
		margin-bottom: 5rpx;
		margin-left: 16rpx;
		color: #3f3f46;
		font-size: 24rpx;
		line-height: 34rpx;
		text-decoration: line-through;
	}

	.coupon-row {
		position: relative;
		z-index: 2;
		display: flex;
		align-items: center;
		flex-wrap: wrap;
		width: 100%;
		min-height: 42rpx;
		margin-top: 22rpx;
		padding: 0;
		border: 0;
		background: transparent;
		text-align: left;
		line-height: 1;
	}

	.coupon-row--hover {
		opacity: 0.82;
	}

	.coupon-row__item {
		margin-right: 18rpx;
		margin-bottom: 12rpx;
		padding: 6rpx 12rpx;
		border-radius: 8rpx;
		background: var(--brand-primary-pale, #fff7ed);
		color: var(--brand-primary, #ea580c);
		font-size: 22rpx;
		line-height: 30rpx;
	}

	.coupon-row__empty {
		margin-bottom: 12rpx;
		color: #999999;
		font-size: 22rpx;
		line-height: 30rpx;
	}

	.coupon-row__more {
		display: flex;
		align-items: center;
		margin-left: auto;
		margin-bottom: 12rpx;
		color: var(--text-link, #ea580c);
		font-size: 22rpx;
		line-height: 30rpx;
	}

	.coupon-row__more-icon {
		margin-left: 6rpx;
		font-size: 20rpx;
		line-height: 1;
	}

	.goods-title {
		display: block;
		color: #111111;
		font-size: 31rpx;
		line-height: 46rpx;
		font-weight: 800;
	}

	.goods-description {
		display: block;
		margin-top: 12rpx;
		color: #696969;
		font-size: 24rpx;
		line-height: 36rpx;
	}

	.goods-meta {
		display: flex;
		align-items: center;
		flex-wrap: wrap;
		margin-top: 20rpx;
	}

	.goods-meta__tags {
		display: flex;
		align-items: center;
		flex-wrap: wrap;
		max-width: 320rpx;
		margin-right: 18rpx;
	}

	.goods-meta__tags :deep(.tag-preview) {
		margin-right: 12rpx;
		margin-bottom: 8rpx;
	}

	.goods-meta__stock,
	.goods-meta__sales {
		color: #696969;
		font-size: 24rpx;
		line-height: 34rpx;
	}

	.goods-meta__stock {
		margin-left: 12rpx;
	}

	.goods-meta__sales {
		margin-left: auto;
	}

	.info-card,
	.store-card,
	.review-card,
	.detail-card {
		margin-top: 18rpx;
		border-radius: 18rpx;
	}

	.info-card {
		padding: 18rpx 26rpx;
	}

	.info-row {
		display: flex;
		align-items: center;
		min-height: 58rpx;
		padding: 12rpx 0;
		border-bottom: 1px solid transparent;
	}

	.info-row__label {
		width: 92rpx;
		color: #696969;
		font-size: 26rpx;
		line-height: 36rpx;
	}

	.info-row__value {
		flex: 1;
		min-width: 0;
		color: #111111;
		font-size: 25rpx;
		line-height: 36rpx;
		font-weight: 700;
		overflow: hidden;
		white-space: nowrap;
		text-overflow: ellipsis;
	}

	.info-row__arrow,
	.section-title__arrow {
		margin-left: 12rpx;
		color: #111111;
		font-size: 28rpx;
		line-height: 1;
		font-weight: 400;
	}

	.store-card {
		padding: 24rpx 26rpx;
		background: #ffffff;
	}

	.store-card__main {
		display: flex;
		align-items: center;
	}

	.store-card__avatar {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 96rpx;
		height: 96rpx;
		border-radius: 50%;
		overflow: hidden;
		background: #6b1f1f;
		color: #ffffff;
		flex-shrink: 0;
	}

	.store-card__avatar-image {
		width: 96rpx;
		height: 96rpx;
		border-radius: 50%;
	}

	.store-card__avatar-text {
		font-size: 34rpx;
		line-height: 44rpx;
		font-weight: 700;
	}

	.store-card__body {
		flex: 1;
		min-width: 0;
		margin-left: 22rpx;
	}

	.store-card__title-row,
	.store-card__rating,
	.store-card__stars,
	.store-card__button {
		display: flex;
		align-items: center;
	}

	.store-card__name {
		max-width: 310rpx;
		color: #111111;
		font-size: 30rpx;
		line-height: 42rpx;
		font-weight: 700;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.store-card__badge {
		margin-left: 10rpx;
		padding: 0 8rpx;
		border-radius: 6rpx;
		background: #ff3b30;
		color: #ffffff;
		font-size: 20rpx;
		line-height: 30rpx;
		font-weight: 700;
		flex-shrink: 0;
	}

	.store-card__rating {
		margin-top: 8rpx;
	}

	.store-card__rating-label,
	.store-card__desc {
		color: #7b7677;
		font-size: 24rpx;
		line-height: 34rpx;
	}

	.store-card__stars {
		margin-left: 12rpx;
	}

	.store-card__star {
		margin-right: 4rpx;
		color: #e4e4e4;
		font-size: 24rpx;
		line-height: 28rpx;
	}

	.store-card__star--active,
	.store-card__rating-score {
		color: #ff2f2f;
	}

	.store-card__rating-score {
		margin-left: 8rpx;
		font-size: 26rpx;
		line-height: 34rpx;
		font-weight: 700;
	}

	.store-card__desc {
		display: block;
		margin-top: 8rpx;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.store-card__button {
		justify-content: center;
		width: 154rpx;
		height: 58rpx;
		margin-left: 18rpx;
		border: 1px solid #ff3b30;
		border-radius: 999rpx;
		color: #ff2f2f;
		font-size: 24rpx;
		line-height: 34rpx;
		font-weight: 700;
		flex-shrink: 0;
	}

	.store-card__button-icon {
		margin-right: 6rpx;
		font-size: 26rpx;
		line-height: 1;
	}

	.store-card__button--hover {
		opacity: 0.82;
	}

	.review-card {
		padding: 24rpx 26rpx 28rpx;
	}

	.section-title {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 0 2rpx;
	}

	.section-title__text {
		color: #111111;
		font-size: 30rpx;
		line-height: 42rpx;
		font-weight: 800;
	}

	.section-title__right {
		display: flex;
		align-items: center;
		color: var(--text-link, #ea580c);
		font-size: 28rpx;
		line-height: 40rpx;
	}

	.review-list {
		height: 158rpx;
		margin-top: 24rpx;
	}

	.review-empty {
		display: flex;
		align-items: center;
		justify-content: center;
		height: 120rpx;
		margin-top: 18rpx;
		color: #999999;
		font-size: 24rpx;
		line-height: 34rpx;
	}

	.review-list__inner {
		display: flex;
		white-space: nowrap;
	}

	.review-item {
		display: flex;
		flex-direction: column;
		width: 210rpx;
		height: 140rpx;
		margin-right: 20rpx;
		padding: 18rpx;
		border-radius: 14rpx;
		background: #fbfbfb;
	}

	.review-item__top {
		display: flex;
		align-items: center;
	}

	.review-item__avatar {
		width: 48rpx;
		height: 48rpx;
		border-radius: 50%;
		background: var(--surface-card-soft, #f4f4f5);
	}

	.review-item__avatar--empty {
		background: #e5e7eb;
	}

	.review-item__user {
		display: flex;
		flex-direction: column;
		margin-left: 12rpx;
	}

	.review-item__name {
		color: #111111;
		font-size: 20rpx;
		line-height: 26rpx;
	}

	.review-item__stars {
		display: flex;
		align-items: center;
		height: 24rpx;
	}

	.review-item__star {
		color: #ff261f;
		font-size: 18rpx;
		line-height: 1;
	}

	.review-item__content {
		display: block;
		margin-top: 10rpx;
		color: #111111;
		font-size: 22rpx;
		line-height: 32rpx;
		white-space: normal;
	}

	.detail-card {
		overflow: hidden;
		padding: 22rpx 26rpx 30rpx;
	}

	.detail-card__title {
		display: flex;
		align-items: center;
		justify-content: center;
		height: 50rpx;
		color: #111111;
		font-size: 30rpx;
		line-height: 42rpx;
		font-weight: 800;
	}

	.detail-card__decoration {
		margin: 0 20rpx;
		color: #d88d8a;
		font-size: 24rpx;
		line-height: 1;
	}

	.detail-card__decoration--right {
		transform: rotate(180deg);
	}

	.detail-card__image {
		display: block;
		width: 100%;
		margin-top: 20rpx;
	}

	.detail-card__rich {
		display: block;
		margin-top: 20rpx;
		color: #333333;
		font-size: 26rpx;
		line-height: 42rpx;
	}

	.detail-card__empty {
		display: block;
		padding: 52rpx 0 28rpx;
		color: #999999;
		font-size: 24rpx;
		line-height: 34rpx;
		text-align: center;
	}

	.bottom-bar {
		position: fixed;
		right: 0;
		bottom: 0;
		left: 0;
		z-index: 20;
		display: flex;
		align-items: center;
		height: 100rpx;
		padding: 12rpx 26rpx 18rpx;
		background: rgba(255, 255, 255, 0.96);
		box-shadow: 0 -8rpx 24rpx rgba(0, 0, 0, 0.04);
	}

	/* #ifdef H5 */
	.bottom-bar {
		bottom: var(--window-bottom);
	}

	/* #endif */

	/* #ifndef H5 */
	.bottom-bar {
		padding-bottom: 34rpx;
	}

	/* #endif */

	.bottom-action {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		width: 74rpx;
		height: 88rpx;
		margin-right: 8rpx;
		color: #111111;
	}

	.bottom-action--disabled {
		opacity: 0.65;
	}

	.bottom-action__icon {
		height: 42rpx;
		font-size: 39rpx;
		line-height: 42rpx;
	}

	.bottom-action__icon--active {
		color: #ff2f2f;
	}

	.bottom-action__text {
		margin-top: 3rpx;
		font-size: 20rpx;
		line-height: 28rpx;
	}

	.buy-button {
		display: flex;
		align-items: center;
		justify-content: center;
		flex: 1;
		height: 74rpx;
		margin-left: 14rpx;
		border-radius: 999rpx;
		color: #ffffff;
		font-size: 27rpx;
		line-height: 36rpx;
		font-weight: 700;
	}

	.buy-button--cart {
		background: linear-gradient(90deg, var(--brand-primary-light, #fb923c) 0%, var(--brand-primary, #ea580c) 100%);
	}

	.buy-button--buy {
		background: var(--theme-gradient, linear-gradient(135deg, #ea580c 0%, #fb923c 100%));
	}

	.buy-button--disabled {
		opacity: 0.64;
	}

	.service-popup {
		position: fixed;
		top: 0;
		right: 0;
		bottom: 0;
		left: 0;
		z-index: 55;
		display: flex;
		align-items: flex-end;
		background: rgba(0, 0, 0, 0.58);
		animation: service-popup-mask-in 180ms ease-out both;
	}

	.service-popup__sheet {
		width: 100%;
		min-height: 690rpx;
		padding: 36rpx 32rpx 32rpx;
		border-top-left-radius: 34rpx;
		border-top-right-radius: 34rpx;
		background: #ffffff;
		animation: service-popup-sheet-in 240ms cubic-bezier(0.22, 0.61, 0.36, 1) both;
	}

	/* #ifndef H5 */
	.service-popup__sheet {
		padding-bottom: 48rpx;
	}

	/* #endif */

	@keyframes service-popup-mask-in {
		0% {
			opacity: 0;
		}

		100% {
			opacity: 1;
		}
	}

	@keyframes service-popup-sheet-in {
		0% {
			transform: translateY(100%);
		}

		100% {
			transform: translateY(0);
		}
	}

	.service-popup__title {
		display: block;
		color: #2f3033;
		font-size: 34rpx;
		line-height: 48rpx;
		font-weight: 700;
		text-align: center;
	}

	.service-popup__list {
		margin-top: 42rpx;
	}

	.service-popup__item {
		display: flex;
		align-items: flex-start;
		margin-top: 40rpx;
	}

	.service-popup__item:first-child {
		margin-top: 0;
	}

	.service-popup__icon-wrap {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 34rpx;
		height: 34rpx;
		margin-top: 5rpx;
		flex-shrink: 0;
		border-radius: 50%;
		background: #ffae3d;
	}

	.service-popup__icon {
		color: #ffffff;
		font-size: 19rpx;
		line-height: 22rpx;
	}

	.service-popup__item-content {
		flex: 1;
		min-width: 0;
		margin-left: 14rpx;
	}

	.service-popup__item-title {
		display: block;
		color: #2f3033;
		font-size: 31rpx;
		line-height: 44rpx;
		font-weight: 700;
	}

	.service-popup__item-desc {
		display: block;
		margin-top: 8rpx;
		color: #9a9a9a;
		font-size: 25rpx;
		line-height: 36rpx;
	}

	.service-popup__button {
		display: flex;
		align-items: center;
		justify-content: center;
		height: 76rpx;
		margin-top: 176rpx;
		border-radius: 999rpx;
		background: #ef3028;
		color: #ffffff;
		font-size: 29rpx;
		line-height: 40rpx;
		font-weight: 700;
	}

	.service-popup__button--hover {
		opacity: 0.86;
	}

	.param-popup {
		position: fixed;
		top: 0;
		right: 0;
		bottom: 0;
		left: 0;
		z-index: 55;
		display: flex;
		align-items: flex-end;
		background: rgba(0, 0, 0, 0.58);
		animation: param-popup-mask-in 180ms ease-out both;
	}

	.param-popup__sheet {
		width: 100%;
		min-height: 692rpx;
		padding: 36rpx 32rpx 32rpx;
		border-top-left-radius: 34rpx;
		border-top-right-radius: 34rpx;
		background: #ffffff;
		animation: param-popup-sheet-in 240ms cubic-bezier(0.22, 0.61, 0.36, 1) both;
	}

	/* #ifndef H5 */
	.param-popup__sheet {
		padding-bottom: 48rpx;
	}

	/* #endif */

	@keyframes param-popup-mask-in {
		0% {
			opacity: 0;
		}

		100% {
			opacity: 1;
		}
	}

	@keyframes param-popup-sheet-in {
		0% {
			transform: translateY(100%);
		}

		100% {
			transform: translateY(0);
		}
	}

	.param-popup__title {
		display: block;
		color: #2f3033;
		font-size: 34rpx;
		line-height: 48rpx;
		font-weight: 700;
		text-align: center;
	}

	.param-popup__list {
		margin-top: 38rpx;
	}

	.param-popup__item {
		display: flex;
		align-items: center;
		min-height: 48rpx;
		margin-top: 44rpx;
	}

	.param-popup__item:first-child {
		margin-top: 0;
	}

	.param-popup__label {
		min-width: 180rpx;
		color: #999999;
		font-size: 30rpx;
		line-height: 42rpx;
		font-weight: 700;
	}

	.param-popup__value {
		flex: 1;
		min-width: 0;
		margin-left: 24rpx;
		color: #2f3033;
		font-size: 28rpx;
		line-height: 40rpx;
		font-weight: 700;
		text-align: right;
	}

	.param-popup__button {
		display: flex;
		align-items: center;
		justify-content: center;
		height: 76rpx;
		margin-top: 168rpx;
		border-radius: 999rpx;
		background: #ef3028;
		color: #ffffff;
		font-size: 29rpx;
		line-height: 40rpx;
		font-weight: 700;
	}

	.param-popup__button--hover {
		opacity: 0.86;
	}

</style>
