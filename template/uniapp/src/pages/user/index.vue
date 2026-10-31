<template>
	<page-meta :page-style="themePageStyle"></page-meta>
	<view class="user-center-page">
		<image class="user-center-page__top-bg" src="/static/images/user_background.png" mode="aspectFill" />
		<view class="user-center-page__safe">
			<app-safe-top mp-extra="28rpx" background="transparent" />
		</view>

		<view class="hero-section">
			<view class="hero-profile">
				<view class="hero-profile__main" hover-class="hero-profile__main--hover" @click="handleProfileClick">
					<view class="avatar-shell">
						<image class="avatar-shell__image" :src="userInfo.avatar || defaultAvatar" mode="aspectFill" />
					</view>
					<view class="hero-profile__info">
						<text class="hero-profile__name">{{ userInfo.nickname }}</text>
						<text v-if="isUserLoggedIn" class="hero-profile__id">ID: {{ userInfo.userId }}</text>
					</view>
				</view>

				<view class="hero-profile__actions">
					<view class="hero-action" hover-class="hero-action--hover" @click="handleSettingsClick">
						<uni-icons type="settings" size="20" color="#52525b" />
					</view>
					<!-- <view class="hero-action" hover-class="hero-action--hover" @click="handleNotificationClick">
						<uni-icons type="notification" size="20" color="var(--text-subtitle)" />
					</view> -->
				</view>
			</view>

			<view class="asset-card">
				<view class="asset-list">
					<view v-for="(item, index) in assetCards" :key="item.key"
						:class="['asset-item', { 'asset-item--border': index < assetCards.length - 1 }]"
						hover-class="asset-item--hover" @click="handleMenuClick(item)">
						<text class="asset-item__value">{{ item.value }}</text>
						<text class="asset-item__label">{{ item.label }}</text>
					</view>
				</view>

				<view v-if="memberEnabled" class="vip-strip" hover-class="vip-strip--hover" @click="openMembershipChannel">
					<view class="vip-strip__main">
						<uni-icons type="vip-filled" size="16" color="#fde68a" />
						<text class="vip-strip__text">{{ membershipText }}</text>
					</view>
					<view class="vip-strip__button">
						<text class="vip-strip__button-text">{{ membershipButtonText }}</text>
					</view>
				</view>
			</view>
		</view>

		<view class="content-section">
			<view class="section-card section-card--order">
				<view class="section-header">
					<text class="section-title">{{ orderMenuTitle }}</text>
					<view class="section-link" hover-class="section-link--hover"
						@click="navigateTo('/pages/orders/order/list/index', '订单列表开发中')">
						<text class="section-link__text">全部订单</text>
						<text class="iconfont icon-right" />
					</view>
				</view>

				<view class="order-list">
					<view v-for="item in displayOrderMenus" :key="item.key" class="order-item"
						hover-class="order-item--hover" @click="handleMenuClick(item)">
						<view class="order-item__icon">
							<image v-if="item.image" class="order-item__image" :src="item.image" mode="aspectFit" />
							<text v-else-if="item.icon" class="iconfont" :class="item.icon"></text>
							<image v-else-if="item.defaultImage" class="order-item__image" :src="item.defaultImage"
								mode="aspectFit" />
							<text v-else-if="item.defaultIcon" class="iconfont" :class="item.defaultIcon"></text>
							<text v-if="item.badgeText" class="order-item__badge">{{ item.badgeText }}</text>
						</view>
						<text class="order-item__text">{{ item.text }}</text>
					</view>
				</view>
				<view v-if="hotelAvailable || hotelEvidence" class="hotel-order-links">
					<view v-if="hotelAvailable" @click="navigateTo('/plugins/hotel/pages/index/index')">酒店预订 ›</view>
					<view v-if="hotelEvidence" @click="navigateTo('/plugins/hotel/pages/booking/index')">我的预订 ›</view>
				</view>
			</view>

			<view class="section-card section-card--service">
				<view class="section-header section-header--single">
					<text class="section-title">{{ serviceMenusTitle }}</text>
				</view>

				<view class="service-list">
					<view v-for="item in serviceMenus" :key="item.key" class="service-item"
						hover-class="service-item--hover" @click="handleMenuClick(item)">
						<view class="service-item__icon">
							<image v-if="item.image" class="service-item__image" :src="item.image" mode="aspectFill" />
							<uni-icons v-else type="circle-filled" size="22" :color="themePrimaryColor" />
						</view>
						<text class="service-item__text">{{ item.text }}</text>
					</view>
				</view>
			</view>

			<view v-if="bannerInfo.image" class="promo-banner" hover-class="promo-banner--hover"
				@click="handleBannerClick">
				<image class="promo-banner__image" :src="bannerInfo.image" mode="aspectFill" />
				<view class="promo-banner__mask">
					<text class="promo-banner__title">{{ bannerInfo.title }}</text>
					<text class="promo-banner__desc">{{ bannerInfo.desc }}</text>
				</view>
			</view>

			<view class="user-center-page__recommend">
				<guess-you-like
					title="为你推荐"
					:recommend-type="recommendType"
					:show-more="false"
					:limit="4"
				/>
			</view>

			<view class="page-bottom-space"></view>
		</view>
	</view>
</template>

<script setup>
	import GuessYouLike from '@/components/guess-you-like/index.vue'
	import {
		getCurrentUser
	} from '@/api/auth'
	import {
		getDiyUserCenterConfig
	} from '@/api/home'
	import {
		getOrderBadge
	} from '@/api/order'
	import {
		getUserInfo as getStoredUserInfo,
		isLoggedIn,
		navigateToLogin
	} from '@/utils/auth'
	import { computed, ref } from 'vue'
	import { onLoad, onShow } from '@dcloudio/uni-app'
	import { getPluginState } from '@/plugins/registry'
	const hotelAvailable = computed(() => getPluginState('hotel').enabled === true)
	const hotelEvidence = computed(() => {
		const state = getPluginState('hotel')
		return state.enabled === true || state.lifecycle_status === 'disabled' && /^[a-f0-9]{32}$/.test(String(state.install_generation || ''))
	})

	const DEFAULT_BANNER_INFO = {
		image: '',
		title: '',
		desc: '',
		url: '',
		toastText: '活动详情开发中'
	}

	const DEFAULT_MEMBER_TITLE = '普通用户'
	const DEFAULT_MEMBERSHIP = {
		isMember: false,
		memberVisible: false,
		endTime: '',
		status: 0
	}
	const DEFAULT_USER_INFO = {
		avatar: '',
		nickname: '点击登录',
		userId: '',
		memberTitle: DEFAULT_MEMBER_TITLE
	}
	const DEFAULT_ASSET_SUMMARY = {
		balance: '¥0.00',
		points: '0',
		commission: '¥0.00'
	}
	const DEFAULT_ORDER_BADGE = {
		pending: 0,
		paid: 0,
		completed: 0,
		refunding: 0
	}
	const DEFAULT_ORDER_MENU_TITLE = '我的订单'
	const DEFAULT_ORDER_MENU_IMAGES = {
		'pending-payment': '/static/images/order_no_pay.png',
		'pending-shipment': '/static/images/order_no_ship.png',
		'pending-receipt': '/static/images/order_no_adderss.png',
		'pending-review': '/static/images/order_no_assess.png',
		'after-sale': '/static/images/order_no_refund.png'
	}
	const DEFAULT_ORDER_MENUS = [{
			key: 'pending-payment',
			badgeKey: 'pending',
			text: '待付款',
			icon: 'icon-moreandroid',
			image: DEFAULT_ORDER_MENU_IMAGES['pending-payment'],
			url: '/pages/orders/order/list/index?status=unpaid'
		},
		{
			key: 'pending-shipment',
			badgeKey: 'paid',
			text: '待发货',
			icon: 'icon-send',
			image: DEFAULT_ORDER_MENU_IMAGES['pending-shipment'],
			url: '/pages/orders/order/list/index?status=unsent'
		},
		{
			key: 'pending-receipt',
			badgeKey: '',
			text: '待收货',
			icon: 'icon-order',
			image: DEFAULT_ORDER_MENU_IMAGES['pending-receipt'],
			url: '/pages/orders/order/list/index?status=undelivered'
		},
		{
			key: 'pending-review',
			badgeKey: 'completed',
			text: '待评价',
			icon: 'icon-evaluate',
			image: DEFAULT_ORDER_MENU_IMAGES['pending-review'],
			url: '/pages/orders/order/list/index?status=unevaluated'
		},
		{
			key: 'after-sale',
			badgeKey: 'refunding',
			text: '售后',
			icon: 'icon-refund',
			image: DEFAULT_ORDER_MENU_IMAGES['after-sale'],
			url: '/pages/orders/refund/index'
		}
	]
	const DEFAULT_SERVICE_MENUS = [{
			key: 'default-favorite',
			text: '我的收藏',
			image: '',
			url: '/pages/users/collect/index'
		},
		{
			key: 'default-address',
			text: '收货地址',
			image: '',
			url: '/pages/users/address/list/index'
		},
		{
			key: 'default-customer',
			text: '在线客服',
			image: '',
			url: '/pages/users/customer/index'
		},
		{
			key: 'default-wallet',
			text: '我的钱包',
			image: '',
			url: '/pages/users/balance/index'
		},
		{
			key: 'default-coupon',
			text: '领券中心',
			image: '',
			url: '/pages/users/usecoupon/index'
		},
		{
			key: 'default-about',
			text: '关于我们',
			image: '',
			url: '/pages/users/about/index'
		}
	]

	function cloneDefaultOrderMenus() {
		return DEFAULT_ORDER_MENUS.map(item => ({
			...item
		}))
	}

	function cloneDefaultServiceMenus() {
		return DEFAULT_SERVICE_MENUS.map(item => ({
			...item
		}))
	}

	function isIconClass(value) {
		const text = String(value || '').trim()
		if (!text) {
			return false
		}

		return /^(el-icon-|icon[-_])[A-Za-z0-9_-]+$/.test(text)
	}

	defineOptions({ name: 'UserCenterPage' })

	const defaultAvatar = ref('/static/images/avatar_default.jpg')
	const bannerInfo = ref({
		...DEFAULT_BANNER_INFO
	})
	const userInfo = ref({
		...DEFAULT_USER_INFO
	})
	const membership = ref({
		...DEFAULT_MEMBERSHIP
	})
	const assetSummary = ref({
		...DEFAULT_ASSET_SUMMARY
	})
	const orderBadge = ref({
		...DEFAULT_ORDER_BADGE
	})
	const serviceMenusTitle = ref('我的服务')
	const orderMenuTitle = ref(DEFAULT_ORDER_MENU_TITLE)
	const orderMenus = ref(cloneDefaultOrderMenus())
	const serviceMenus = ref([])
	const activitySigninEnabled = ref(false)
	const memberConfigEnabled = ref(false)
	const recommendType = ref('special')

	/**
	 * 当前是否已登录。
	 *
	 * @returns {boolean} 已登录返回 true。
	 */
	const isUserLoggedIn = computed(() => {
		return isLoggedIn()
	})

	/**
	 * 会员权益条文案。
	 *
	 * @returns {string} 当前用户会员文案。
	 */
	const membershipText = computed(() => {
		if (!isUserLoggedIn.value) {
			return '登录后开通 VIP 会员'
		}

		if (membership.value.isMember) {
			return membership.value.endTime ?
				`VIP 会员有效期至 ${membership.value.endTime}` :
				'VIP 会员权益生效中'
		}

		return '开通 VIP 会员，解锁专属权益'
	})

	/**
	 * 会员入口按钮文案。
	 *
	 * @returns {string} 根据购买状态展示的操作文案。
	 */
	const membershipButtonText = computed(() => {
		return membership.value.isMember ? '查看权益' : '立即开通'
	})

	/**
	 * 会员入口是否展示。
	 *
	 * @returns {boolean} 后台开启或当前用户仍是有效会员时返回 true。
	 */
	const memberEnabled = computed(() => {
		return memberConfigEnabled.value || membership.value.isMember || membership.value.memberVisible
	})

	/**
	 * 资产面板展示项。
	 *
	 * @returns {Array<Object>} 余额、佣金，以及签到开启时的积分数据。
	 */
	const assetCards = computed(() => {
		const cards = [
			{
				key: 'balance',
				label: '余额',
				value: assetSummary.value.balance,
				url: '/pages/users/balance/index'
			}
		]

		if (activitySigninEnabled.value) {
			cards.push({
				key: 'points',
				label: '积分',
				value: assetSummary.value.points,
				url: '/pages/users/profile/index'
			})
		}

		cards.push(
			{
				key: 'commission',
				label: '佣金',
				value: assetSummary.value.commission,
				url: '/pages/users/commission/index'
			}
		)

		return cards
	})

	/**
	 * 带角标文案的订单菜单。
	 *
	 * @returns {Array<Object>} 可直接渲染的订单入口。
	 */
	const displayOrderMenus = computed(() => {
		return orderMenus.value.map((item) => {
			const count = item.badgeKey ? formatBadgeCount(orderBadge.value[item.badgeKey]) : 0
			return {
				...item,
				badgeText: count > 0 ? String(count > 99 ? '99+' : count) : ''
			}
		})
	})

	onLoad(() => {
		loadUserCenterData()
	})

	onShow(() => {
		loadUserCenterData()
	})

	/**
	 * 初始化个人中心展示数据。
	 *
	 * @returns {Promise<void>} 加载完成后更新页面展示。
	 */
	async function loadUserCenterData() {
		await Promise.all([
			loadDecorateConfig(),
			loadUserProfile()
		])
	}

	/**
	 * 加载个人中心装修配置。
	 *
	 * @returns {Promise<void>} 成功时覆盖服务菜单、广告和推荐类型，失败时使用空配置。
	 */
	async function loadDecorateConfig() {
		try {
			const res = await getDiyUserCenterConfig()
			const data = res && res.data ? res.data : {}
			const rawServiceMenus = Array.isArray(data.service_menus) ? data.service_menus : []
			const rawOrderMenus = Array.isArray(data.order_menus) ? data.order_menus : []
			const banner = data.banner && typeof data.banner === 'object' ? data.banner : null

			serviceMenus.value = rawServiceMenus.map((item, index) => normalizeServiceMenu(item, index))
			serviceMenusTitle.value = String(data.service_menus_title || '我的服务').trim() || '我的服务'
			activitySigninEnabled.value = resolveActivitySigninEnabled(data)
			memberConfigEnabled.value = resolveMemberEnabled(data)
			orderMenuTitle.value = String(data.order_menu_title || DEFAULT_ORDER_MENU_TITLE).trim() ||
				DEFAULT_ORDER_MENU_TITLE
			orderMenus.value = normalizeOrderMenus(rawOrderMenus)
			bannerInfo.value = banner ? normalizeBannerInfo(banner) : {
				...DEFAULT_BANNER_INFO
			}
			recommendType.value = normalizeRecommendType(data.recommend_type)
		} catch (error) {
			serviceMenus.value = cloneDefaultServiceMenus()
			serviceMenusTitle.value = '我的服务'
			activitySigninEnabled.value = false
			memberConfigEnabled.value = false
			orderMenuTitle.value = DEFAULT_ORDER_MENU_TITLE
			orderMenus.value = cloneDefaultOrderMenus()
			bannerInfo.value = {
				...DEFAULT_BANNER_INFO
			}
			recommendType.value = 'special'
		}
	}

	/**
	 * 判断签到积分入口是否开启。
	 *
	 * @param {Object} data 个人中心装修接口返回 data。
	 * @returns {boolean} 签到活动开启时返回 true。
	 */
	function resolveActivitySigninEnabled(data = {}) {
		return data.activity_signin_enabled === true ||
			Number(data.activity_signin_enabled || 0) === 1 ||
			Number(data.activity_signin_switch || 0) === 1
	}

	/**
	 * 判断 VIP 会员入口是否开启。
	 *
	 * @param {Object} data 个人中心装修接口返回 data。
	 * @returns {boolean} 当前用户应展示会员入口时返回 true。
	 */
	function resolveMemberEnabled(data = {}) {
		return data.member_visible === true ||
			Number(data.member_visible || 0) === 1 ||
			data.member_enabled === true ||
			Number(data.member_enabled || 0) === 1 ||
			Number(data.member_switch || 0) === 1
	}

	/**
	 * 加载用户资料和订单角标。
	 *
	 * @returns {Promise<void>} 成功时更新用户信息、资产信息与订单角标。
	 */
	async function loadUserProfile() {
		resetUserCenterState()

		if (!isLoggedIn()) {
			return
		}

		const storedUserInfo = getStoredUserInfo()
		if (storedUserInfo && Object.keys(storedUserInfo).length) {
			applyUserProfile(storedUserInfo)
		}

		await Promise.all([
			loadCurrentUserProfile(),
			loadOrderBadge()
		])
	}

	/**
	 * 请求当前登录用户资料。
	 *
	 * @returns {Promise<void>} 成功时写入页面用户资料。
	 */
	async function loadCurrentUserProfile() {
		try {
			const res = await getCurrentUser({}, {
				showError: false,
				loginRedirect: false
			})
			const profile = extractUserProfile(res && res.data ? res.data : {})

			if (profile && Object.keys(profile).length) {
				applyUserProfile(profile)
			}
		} catch (error) {
			// 用户资料失败时保留本地缓存或空态。
		}
	}

	/**
	 * 规范化后台下发的推荐展示类型。
	 *
	 * @param {string} value 后台推荐类型配置。
	 * @returns {string} 仅返回 product 或 special，非法值回退专题。
	 */
	function normalizeRecommendType(value) {
		return String(value || '').toLowerCase() === 'product' ? 'product' : 'special'
	}

	/**
	 * 请求当前用户订单角标。
	 *
	 * @returns {Promise<void>} 成功时写入订单角标统计。
	 */
	async function loadOrderBadge() {
		try {
			const res = await getOrderBadge({}, {
				showError: false,
				loginRedirect: false
			})
			const badge = res && res.data && typeof res.data === 'object' ? res.data : {}
			orderBadge.value = {
				pending: formatBadgeCount(badge.pending),
				paid: formatBadgeCount(badge.paid),
				completed: formatBadgeCount(badge.completed),
				refunding: formatBadgeCount(badge.refunding)
			}
		} catch (error) {
			orderBadge.value = {
				...DEFAULT_ORDER_BADGE
			}
		}
	}

	/**
	 * 重置为未登录安全空态。
	 *
	 * @returns {void}
	 */
	function resetUserCenterState() {
		userInfo.value = {
			...DEFAULT_USER_INFO
		}
		membership.value = {
			...DEFAULT_MEMBERSHIP
		}
		assetSummary.value = {
			...DEFAULT_ASSET_SUMMARY
		}
		orderBadge.value = {
			...DEFAULT_ORDER_BADGE
		}
	}

	/**
	 * 标准化服务菜单。
	 *
	 * @param {Object} item 后台菜单配置。
	 * @param {number} index 菜单序号。
	 * @returns {Object} 页面可渲染菜单。
	 */
	function normalizeServiceMenu(item = {}, index = 0) {
		const text = String(item.text || item.name || '').trim()
		return {
			key: String(item.key || ('service-' + index)),
			text,
			image: item.image || item.icon || '',
			url: item.url || item.link_url || ''
		}
	}

	/**
	 * 合并后台订单入口配置与移动端固定业务信息。
	 *
	 * @param {Array<Object>} items 后台订单入口配置。
	 * @returns {Array<Object>} 页面可渲染订单入口。
	 */
	function normalizeOrderMenus(items = []) {
		const configItems = Array.isArray(items) ? items : []
		const itemsByKey = {}

		configItems.forEach((item) => {
			if (item && item.key) {
				itemsByKey[String(item.key)] = item
			}
		})

		return DEFAULT_ORDER_MENUS.map((defaultItem, index) => {
			const current = itemsByKey[defaultItem.key] || configItems[index] || {}
			const text = String(current.text || current.name || current.title || defaultItem.text)
				.trim() || defaultItem.text
			const rawIcon = String(current.icon || '').trim()
			const rawImage = String(current.image || '').trim()
			const icon = rawIcon || (isIconClass(rawImage) ? rawImage : '')
			const image = isIconClass(rawImage) ? '' : rawImage

			return {
				key: defaultItem.key,
				badgeKey: defaultItem.badgeKey,
				text,
				icon,
				image,
				defaultIcon: defaultItem.icon,
				defaultImage: defaultItem.image,
				url: defaultItem.url,
			}
		})
	}

	/**
	 * 标准化 Banner 配置。
	 *
	 * @param {Object} banner 后台 Banner 配置。
	 * @returns {Object} 页面可渲染 Banner。
	 */
	function normalizeBannerInfo(banner = {}) {
		return {
			image: banner.image || '',
			title: banner.title || '',
			desc: banner.desc || '',
			url: banner.url || banner.link_url || '',
			toastText: banner.toastText || DEFAULT_BANNER_INFO.toastText
		}
	}

	/**
	 * 从接口响应中提取用户主体。
	 *
	 * @param {Object} payload 用户接口响应 data。
	 * @returns {Object} 用户资料对象。
	 */
	function extractUserProfile(payload = {}) {
		if (payload.user && typeof payload.user === 'object') {
			return payload.user
		}

		if (payload.userinfo && typeof payload.userinfo === 'object') {
			return payload.userinfo
		}

		return payload && typeof payload === 'object' ? payload : {}
	}

	/**
	 * 将接口或缓存中的用户资料映射到页面展示字段。
	 *
	 * @param {Object} profile 用户资料对象。
	 * @returns {void}
	 */
	function applyUserProfile(profile = {}) {
		const asset = profile.asset && typeof profile.asset === 'object' ? profile.asset : {}
		const member = profile.member && typeof profile.member === 'object' ? profile.member : {}
		const memberTitle = String(
			profile.level_name ||
			profile.levelName ||
			profile.member_level_name ||
			profile.memberLevelName ||
			profile.vipLevel ||
			DEFAULT_MEMBER_TITLE
		).trim() || DEFAULT_MEMBER_TITLE

		userInfo.value = {
			avatar: String(profile.avatar || profile.headimgurl || profile.head_img || profile.headimg || '')
				.trim(),
			nickname: String(profile.nickname || profile.user_nickname || profile.userNickname || profile
					.phone || profile.account || DEFAULT_USER_INFO.nickname).trim() || DEFAULT_USER_INFO
				.nickname,
			userId: formatUserId(profile.uid ?? profile.user_id ?? profile.userId ?? profile.id ?? profile
				.phone),
			memberTitle
		}
		membership.value = normalizeMembership(member)

		assetSummary.value = {
			balance: formatMoney(asset.balance ?? profile.now_money ?? profile.balance ?? profile
				.user_money, DEFAULT_ASSET_SUMMARY.balance),
			points: formatNumber(asset.points ?? profile.integral ?? profile.points, DEFAULT_ASSET_SUMMARY
				.points),
			commission: formatMoney(asset.commission ?? profile.brokerage_price ?? profile.commission,
				DEFAULT_ASSET_SUMMARY.commission)
		}
	}

	/**
	 * 点击用户资料区域。
	 *
	 * @returns {void}
	 */
	function handleProfileClick() {
		if (!isLoggedIn()) {
			openLoginPage()
		}
	}

	/**
	 * 统一处理菜单点击。
	 *
	 * @param {Object} item 菜单项配置。
	 * @returns {void}
	 */
	function handleMenuClick(item = {}) {
		navigateTo(item.url)
	}

	/**
	 * 进入消息中心占位入口。
	 *
	 * @returns {void}
	 */
	function handleNotificationClick() {
		navigateTo('', '消息中心开发中')
	}

	/**
	 * 跳转到个人设置页面。
	 *
	 * @returns {void}
	 */
	function handleSettingsClick() {
		navigateTo('/pages/users/userinfo/index', '个人设置页面开发中')
	}

	/**
	 * 打开会员权益说明。
	 *
	 * @returns {void}
	 */
	function openMembershipChannel() {
		navigateTo('/pages/users/member/index')
	}

	/**
	 * 标准化会员购买状态。
	 *
	 * @param {Object} member 后端返回的会员信息。
	 * @returns {Object} 页面可消费的会员状态。
	 */
	function normalizeMembership(member = {}) {
		return {
			isMember: Boolean(member.is_member || member.isMember),
			memberVisible: member.member_visible === true || Number(member.member_visible || 0) === 1,
			endTime: String(member.end_time || member.endTime || ''),
			status: Number(member.status || 0)
		}
	}

	/**
	 * 处理 Banner 点击。
	 *
	 * @returns {void}
	 */
	function handleBannerClick() {
		navigateTo(bannerInfo.value.url, bannerInfo.value.toastText || '活动详情开发中')
	}

	/**
	 * 跳转到指定页面。
	 *
	 * @param {string} url 页面路径。
	 * @param {string} fallbackText 跳转失败时的提示文案。
	 * @returns {void}
	 */
	function navigateTo(url, fallbackText = '页面开发中') {
		if (!isLoggedIn() && url !== '/pages/users/about/index') {
			openLoginPage()
			return
		}

		if (!url) {
			showFeatureToast(fallbackText)
			return
		}

		uni.navigateTo({
			url,
			fail: () => {
				showFeatureToast(fallbackText)
			}
		})
	}

	/**
	 * 跳转到登录页。
	 *
	 * @returns {void}
	 */
	function openLoginPage() {
		navigateToLogin()
	}

	/**
	 * 格式化角标数量。
	 *
	 * @param {number|string} value 原始角标数量。
	 * @returns {number} 非负整数角标数量。
	 */
	function formatBadgeCount(value) {
		const count = Number(value)
		return Number.isFinite(count) && count > 0 ? Math.floor(count) : 0
	}

	/**
	 * 格式化金额展示。
	 *
	 * @param {number|string} value 原始金额。
	 * @param {string} fallback 兜底展示值。
	 * @returns {string} 格式化后的金额字符串。
	 */
	function formatMoney(value, fallback = '¥0.00') {
		const normalized = String(value === undefined || value === null ? '' : value).replace(/,/g, '').trim()

		if (!normalized) {
			return fallback
		}

		if (normalized.startsWith('¥')) {
			return normalized
		}

		const amount = Number(normalized)

		if (!Number.isFinite(amount)) {
			return fallback
		}

		return `¥${amount.toLocaleString('zh-CN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      })}`
	}

	/**
	 * 格式化整数展示。
	 *
	 * @param {number|string} value 原始数值。
	 * @param {string} fallback 兜底展示值。
	 * @returns {string} 格式化后的整数文案。
	 */
	function formatNumber(value, fallback = '0') {
		const normalized = String(value === undefined || value === null ? '' : value).replace(/,/g, '').trim()

		if (!normalized) {
			return fallback
		}

		const amount = Number(normalized)

		if (!Number.isFinite(amount)) {
			return fallback
		}

		return amount.toLocaleString('zh-CN')
	}

	/**
	 * 格式化用户 ID 展示。
	 *
	 * @param {number|string} value 原始用户标识。
	 * @returns {string} 可展示的用户 ID。
	 */
	function formatUserId(value) {
		return String(value === undefined || value === null ? '' : value).trim()
	}

	/**
	 * 展示统一的功能占位提示。
	 *
	 * @param {string} title 提示文案。
	 * @returns {void}
	 */
	function showFeatureToast(title = '功能开发中') {
		uni.showToast({
			title,
			icon: 'none'
		})
	}
</script>

<style lang="scss">
	page {
		background: var(--surface-page);
	}
</style>

<style lang="scss" scoped>
	.user-center-page {
		position: relative;
		min-height: 100%;
		background: var(--surface-page);
	}

	.user-center-page__top-bg {
		position: absolute;
		left: 0;
		top: 0;
		right: 0;
		z-index: 0;
		width: 100%;
		height: 520rpx;
	}

	.user-center-page__safe {
		position: relative;
		z-index: 1;
	}

	.hero-section {
		position: relative;
		z-index: 1;
		padding: 0 32rpx 44rpx;
		overflow: hidden;
	}

	.hero-profile {
		position: relative;
		z-index: 1;
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding-top: 12rpx;
	}

	.hero-profile__main {
		display: flex;
		align-items: center;
		min-width: 0;
	}

	.avatar-shell {
		width: 128rpx;
		height: 128rpx;
		border-radius: 50%;
		overflow: hidden;
		border: 4rpx solid var(--surface-card);
		box-shadow: 0 4rpx 12rpx var(--shadow-soft);
		box-sizing: border-box;
		flex-shrink: 0;
	}

	.avatar-shell__image {
		display: block;
		width: 100%;
		height: 100%;
	}

	.hero-profile__info {
		flex: 1;
		min-width: 0;
		margin-left: 24rpx;
	}

	.hero-profile__name {
		display: block;
		overflow: hidden;
		font-size: 32rpx;
		line-height: 48rpx;
		font-weight: 700;
		color: var(--text-title);
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.hero-profile__id {
		display: block;
		margin-top: 4rpx;
		font-size: 20rpx;
		line-height: 28rpx;
		color: var(--text-secondary);
	}

	.hero-profile__actions {
		display: flex;
		align-items: center;
		margin-left: 20rpx;
	}

	.hero-action {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 56rpx;
		height: 56rpx;
		margin-left: 12rpx;
		border-radius: 50%;
	}

	.hero-action--hover {
		background: var(--mask-light);
	}

	.asset-card {
		position: relative;
		z-index: 2;
		margin-top: 28rpx;
		padding: 24rpx;
		border-radius: 48rpx;
		background: var(--surface-card);
		box-shadow: 0 12rpx 28rpx rgba(17, 24, 39, 0.05);
	}

	.asset-list {
		display: flex;
		align-items: stretch;
	}

	.asset-item {
		flex: 1;
		padding: 4rpx 12rpx;
		text-align: center;
	}

	.asset-item--border {
		border-right: 1rpx solid var(--border-light);
	}

	.asset-item--hover {
		opacity: 0.88;
	}

	.asset-item__value {
		display: block;
		font-size: 36rpx;
		line-height: 1.1;
		font-weight: 700;
		color: var(--text-title);
	}

	.asset-item__label {
		display: block;
		margin-top: 8rpx;
		font-size: 20rpx;
		line-height: 28rpx;
		color: var(--text-secondary);
	}

	.vip-strip {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin-top: 28rpx;
		padding: 20rpx 24rpx;
		border-radius: 24rpx;
		background: linear-gradient(90deg, var(--vip-bg-start) 0%, var(--vip-bg-end) 100%);
		border: 1rpx solid var(--vip-border);
	}

	.vip-strip--hover {
		opacity: 0.94;
	}

	.vip-strip__main {
		display: flex;
		align-items: center;
		flex: 1;
		min-width: 0;
	}

	.vip-strip__text {
		flex: 1;
		margin-left: 12rpx;
		font-size: 24rpx;
		line-height: 34rpx;
		font-weight: 700;
		color: var(--vip-text);
	}

	.vip-strip__button {
		margin-left: 16rpx;
		padding: 10rpx 20rpx;
		border-radius: 999rpx;
		background: linear-gradient(90deg, var(--vip-button-start) 0%, var(--vip-button-end) 100%);
		display: flex;
	}

	.vip-strip__button-text {
		font-size: 18rpx;
		line-height: 1;
		font-weight: 700;
		color: var(--vip-button-text);
	}

	.content-section {
		position: relative;
		z-index: 1;
		margin-top: -20rpx;
		padding: 0 32rpx 32rpx;
	}

	.section-card {
		padding: 28rpx 24rpx;
		border-radius: 48rpx;
		background: var(--surface-card);
		box-shadow: 0 12rpx 28rpx rgba(17, 24, 39, 0.05);

		&--order {
			position: relative;
		}
	}

	.section-card--service,
	.promo-banner {
		margin-top: 24rpx;
	}

	.user-center-page__recommend {
		margin-top: 28rpx;
	}

	.section-header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin-bottom: 24rpx;
		padding: 0 8rpx;
	}

	.section-header--single {
		margin-bottom: 28rpx;
	}

	.section-title {
		font-size: 32rpx;
		line-height: 48rpx;
		font-weight: 700;
		color: var(--text-title);
	}

	.section-link {
		display: inline-flex;
		align-items: center;
	}

	.section-link--hover {
		opacity: 0.82;
	}

	.section-link__text {
		font-size: 20rpx;
		line-height: 28rpx;
		color: var(--text-placeholder);
	}

	.section-link .icon-right {
		font-size: 20rpx;
		line-height: 28rpx;
		color: var(--text-placeholder);
	}

	.order-list,
	.service-list {
		display: flex;
		flex-wrap: wrap;
	}

	.hotel-order-links {
		display: flex;
		margin-top: 28rpx;
		padding-top: 22rpx;
		border-top: 1px solid var(--border-light);
		font-size: 24rpx;
		color: var(--brand-primary);
	}

	.hotel-order-links view + view { margin-left: 40rpx; }

	.order-item {
		width: 20%;
		padding: 4rpx 0;
		text-align: center;
	}

	.order-item--hover,
	.service-item--hover,
	.promo-banner--hover {
		opacity: 0.92;
	}

	.order-item__icon {
		position: relative;
		display: flex;
		align-items: center;
		justify-content: center;
		height: 44rpx;
	}

	.order-item__badge {
		position: absolute;
		left: 50%;
		top: -12rpx;
		min-width: 28rpx;
		height: 28rpx;
		padding: 0 8rpx;
		border-radius: 999rpx;
		background: var(--brand-primary, #ea580c);
		font-size: 18rpx;
		line-height: 28rpx;
		color: var(--text-inverse);
		box-sizing: border-box;
	}

	.order-item__icon .iconfont {
		font-size: 36rpx;
		line-height: 1;
		color: var(--text-regular);
	}

	.order-item__image {
		display: block;
		width: 44rpx;
		height: 44rpx;
	}

	.order-item__text {
		display: block;
		margin-top: 14rpx;
		font-size: 18rpx;
		line-height: 26rpx;
		color: var(--text-subtitle);
		text-align: center;
	}

	.service-item {
		width: 25%;
		padding-bottom: 28rpx;
		text-align: center;
	}

	.service-item:nth-last-child(-n + 4) {
		padding-bottom: 0;
	}

	.service-item__icon {
		display: flex;
		align-items: center;
		justify-content: center;
		height: 44rpx;
	}

	.service-item__image {
		display: block;
		width: 60rpx;
		height: 60rpx;
		border-radius: 16rpx;
	}

	.service-item__text {
		display: block;
		margin-top: 12rpx;
		font-size: 18rpx;
		line-height: 26rpx;
		color: var(--text-subtitle);
		text-align: center;
	}

	.promo-banner {
		position: relative;
		height: 192rpx;
		border-radius: 48rpx;
		overflow: hidden;
	}

	.promo-banner__image {
		display: block;
		width: 100%;
		height: 100%;
	}

	.promo-banner__mask {
		position: absolute;
		left: 0;
		top: 0;
		right: 0;
		bottom: 0;
		padding: 32rpx 28rpx;
		background: var(--mask-dark);
	}

	.promo-banner__title {
		display: block;
		font-size: 32rpx;
		line-height: 44rpx;
		font-weight: 700;
		color: var(--text-inverse);
	}

	.promo-banner__desc {
		display: block;
		margin-top: 8rpx;
		font-size: 18rpx;
		line-height: 26rpx;
		color: var(--text-inverse-soft);
	}

	.page-bottom-space {
		height: 24rpx;
	}
</style>
