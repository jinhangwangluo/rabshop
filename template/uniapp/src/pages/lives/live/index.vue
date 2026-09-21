<template>
	<page-meta :page-style="themePageStyle"></page-meta>
	<view class="live-list-page" :class="{ 'live-list-page--sheet-open': showFilterSheet }">
		<app-safe-top mp-extra="0" background="#f5f5f5" title="直播列表" show-back emit-back @back="handleBack">
			<view class="live-header">
				<view class="live-search">
					<uni-icons type="search" size="16" color="#4c4546"></uni-icons>
					<input v-model="keyword" class="live-search__input" placeholder="搜索直播内容、频道..."
						placeholder-class="live-search__placeholder" confirm-type="search" maxlength="30"
						@confirm="handleSearch" />
				</view>
			</view>

			<view class="category-wrap">
				<scroll-view scroll-x class="category-scroll" :show-scrollbar="false">
					<view class="category-track">
						<view v-for="item in categoryTabs" :key="item.key" class="category-tab"
							:class="{ 'category-tab--active': item.id === activeCategoryId }"
							@click="handleCategoryChange(item.id)">
							<text class="category-tab__text">{{ item.name }}</text>
						</view>
					</view>
				</scroll-view>
				<view class="category-filter">
					<view class="filter-entry" :class="{ 'filter-entry--active': hasActiveFilter }"
						@click="openFilterSheet">
						<uni-icons type="settings" size="18" color="#1a1c1c"></uni-icons>
						<view v-if="hasActiveFilter" class="filter-entry__dot"></view>
					</view>
				</view>
			</view>
		</app-safe-top>

		<scroll-view scroll-y class="content-scroll" refresher-enabled :refresher-triggered="refreshing"
			:show-scrollbar="false" lower-threshold="160" @refresherrefresh="handleRefresh"
			@scrolltolower="handleLoadMore">
			<view class="content-inner">
				<view v-if="featuredRoom" class="section section--first">
					<view class="section-head">
						<view class="section-head__left">
							<view class="section-head__pulse"></view>
							<text class="section-head__title">正在直播</text>
						</view>
						<text class="section-head__meta">FEATURED LIVE</text>
					</view>

					<view class="featured-card" @click="openRoom(featuredRoom)">
						<image v-if="featuredRoom.cover" class="featured-card__image" :src="featuredRoom.cover"
							mode="aspectFill" />
						<view v-else class="featured-card__image featured-card__image--fallback"></view>
						<view class="featured-card__mask"></view>
						<view class="featured-card__top">
							<view class="state-badge state-badge--live">
								<view class="state-badge__dot"></view>
								<text>直播中</text>
							</view>
							<view class="state-badge state-badge--dark">
								<text>{{ featuredRoom.viewerText }}</text>
							</view>
						</view>
						<view class="featured-card__body">
							<text class="featured-card__title">{{ featuredRoom.title }}</text>
							<view class="featured-card__anchor">
								<image v-if="featuredRoom.anchorAvatar" class="featured-card__avatar"
									:src="featuredRoom.anchorAvatar" mode="aspectFill" />
								<view v-else class="featured-card__avatar featured-card__avatar--fallback">
									<text>{{ featuredRoom.anchorInitial }}</text>
								</view>
								<text class="featured-card__anchor-name">{{ featuredRoom.anchorName }}</text>
							</view>
						</view>
					</view>
				</view>

				<view class="section">
					<view v-if="recommendList.length" class="room-grid">
						<view v-for="item in recommendList" :key="item.id" class="room-card"
							hover-class="room-card--hover" @click="openRoom(item)">
							<view class="room-card__inner">
								<view class="room-card__media">
									<image v-if="item.cover" class="room-card__image" :src="item.cover"
										mode="aspectFill" />
									<view v-else class="room-card__image room-card__image--fallback"></view>
									<view class="room-card__state" :class="item.stateClass">
										<view v-if="item.status === 1" class="room-card__signal">
											<view class="room-card__signal-bar room-card__signal-bar--short"></view>
											<view class="room-card__signal-bar room-card__signal-bar--mid"></view>
											<view class="room-card__signal-bar room-card__signal-bar--tall"></view>
										</view>
										<text>{{ item.stateText }}</text>
									</view>
									<view class="room-card__audience">
										<text class="iconfont icon-profile room-card__audience-icon"></text>
										<text>{{ item.audienceText }}</text>
									</view>
									<view class="room-card__heat">
										<text class="iconfont icon-aixin room-card__heat-icon"></text>
										<text>{{ item.heatText }}</text>
									</view>
									<view class="room-card__time">
										<text>{{ item.liveTimeText }}</text>
									</view>
								</view>
								<view class="room-card__body">
									<text class="room-card__title">{{ item.title }}</text>
									<view class="room-card__footer">
										<view class="room-card__anchor">
											<image class="room-card__anchor-avatar" :src="item.anchorAvatar"
												mode="aspectFill" />
											<text class="room-card__anchor-name">{{ item.anchorName }}</text>
											<view class="room-card__verified">
												<text class="iconfont icon-check"></text>
											</view>
										</view>
				<view class="room-card__follow"
					:class="{ 'room-card__follow--active': item.isFollowed, 'room-card__follow--disabled': item.isFollowSubmitting }"
					@click.stop="handleFollow(item)">
											<text>{{ item.isFollowSubmitting ? '关注中' : item.followText }}</text>
										</view>
									</view>
								</view>
							</view>
						</view>
					</view>

					<view v-if="loading && !recommendList.length" class="state-panel">
						<six-petal-spiral :active="true" :size="88" text="" :color="themePrimaryColor" :stroke-width="1.3"
							:particle-count="36" :trail-span="0.28" />
					</view>

					<view v-else-if="!loading && !recommendList.length" class="state-panel">
						<image class="state-panel__image" :src="emptyImage" mode="aspectFit"></image>
					</view>

					<view v-if="recommendList.length" class="load-more">
						<text v-if="loading" class="load-more__text">加载中...</text>
						<text v-else-if="finished" class="load-more__text">没有更多了</text>
					</view>
				</view>
			</view>
		</scroll-view>

		<view v-if="showFilterSheet" class="filter-mask" @click="closeFilterSheet"></view>
		<view v-if="showFilterSheet" class="filter-sheet">
			<view class="filter-sheet__handle"></view>
			<view class="filter-sheet__head">
				<text class="filter-sheet__title">筛选条件</text>
				<view class="filter-sheet__close" @click="closeFilterSheet">
					<uni-icons type="closeempty" size="24" color="#1a1c1c"></uni-icons>
				</view>
			</view>

			<scroll-view scroll-y class="filter-sheet__scroll" :show-scrollbar="false">
				<view class="filter-section">
					<text class="filter-section__title">直播状态</text>
					<view class="option-grid">
						<view v-for="item in statusOptions" :key="item.value" class="option-chip"
							:class="{ 'option-chip--active': draftFilter.status === item.status }"
							@click="selectDraftStatus(item.status)">
							<text>{{ item.label }}</text>
						</view>
					</view>
				</view>

				<view class="filter-section">
					<text class="filter-section__title">推荐筛选</text>
					<view class="option-grid option-grid--two">
						<view v-for="item in hotOptions" :key="item.value" class="option-chip"
							:class="{ 'option-chip--active': draftFilter.isHot === item.isHot }"
							@click="selectDraftHot(item.isHot)">
							<text>{{ item.label }}</text>
						</view>
					</view>
				</view>
			</scroll-view>

			<view class="filter-actions">
				<view class="filter-actions__reset" @click="resetDraftFilter">
					<text>重置</text>
				</view>
				<view class="filter-actions__submit" @click="applyFilter">
					<text>查看结果</text>
				</view>
			</view>
		</view>
	</view>
</template>

<script setup>
	import {
		getLiveCategoryOptions,
		getLiveRoomList,
		toggleLiveAnchorFollow
	} from '@/api/live'
	import {
		getStatusImageUrl
	} from '@/utils/status_images'
	import {
		resolveAssetUrl as resolveConfigAssetUrl
	} from '@/utils/runtime_config'
	import {
		computed,
		ref
	} from 'vue'
	import {
		onLoad,
		onReachBottom
	} from '@dcloudio/uni-app'

	const STATUS_OPTIONS = [{
			label: '全部直播',
			value: 'all',
			status: ''
		},
		{
			label: '正在直播',
			value: 'living',
			status: 1
		}
	]

	const HOT_OPTIONS = [{
			label: '全部内容',
			value: 'all',
			isHot: ''
		},
		{
			label: '热门推荐',
			value: 'hot',
			isHot: 1
		}
	]

	defineOptions({ name: 'LiveList' })

	const keyword = ref('')
	const activeCategoryId = ref(0)
	const activeStatus = ref('')
	const activeIsHot = ref('')
	const merchantId = ref(0)
	const showFilterSheet = ref(false)
	const draftFilter = ref({
		status: '',
		isHot: ''
	})
	const categoryList = ref([])
	const featuredList = ref([])
	const recommendList = ref([])
	const followSubmittingAnchorIds = ref([])
	const loading = ref(false)
	const categoryLoading = ref(false)
	const refreshing = ref(false)
	const finished = ref(false)
	const page = ref(1)
	const limit = ref(10)
	const total = ref(0)
	const emptyImage = ref(getStatusImageUrl('no_result.png'))

	const statusOptions = computed(() => {
		return STATUS_OPTIONS
	})

	const hotOptions = computed(() => {
		return HOT_OPTIONS
	})

	const categoryTabs = computed(() => {
		const tabs = [{
			id: 0,
			name: '全部',
			key: 'all'
		}]
		categoryList.value.forEach((item) => {
			tabs.push({
				id: Number(item.id || 0),
				name: item.name || '未命名',
				key: `category-${Number(item.id || 0)}`
			})
		})
		return tabs
	})

	const featuredRoom = computed(() => {
		return featuredList.value.length ? featuredList.value[0] : null
	})

	const hasActiveFilter = computed(() => {
		return Boolean(activeStatus.value !== '' || activeIsHot.value !== '')
	})

	onLoad((options = {}) => {
		const roomId = Number(options.id || 0)
		if (roomId > 0) {
			redirectToRoom(roomId)
			return
		}
		merchantId.value = Math.max(0, Number(options.merchant_id || options.merchantId || 0))
		initPageData()
	})

	onReachBottom(() => {
		handleLoadMore()
	})

	/**
	 * 初始化直播列表页所需数据。
	 *
	 * @returns {Promise<void>}
	 */
	async function initPageData() {
		await loadCategories()
		await Promise.all([
			loadFeaturedRooms(),
			loadRecommendRooms(true)
		])
	}

	/**
	 * 加载直播分类选项。
	 *
	 * @returns {Promise<void>}
	 */
	async function loadCategories() {
		if (categoryLoading.value) return
		categoryLoading.value = true
		try {
			const res = await getLiveCategoryOptions({}, {
				showError: false
			})
			categoryList.value = res && res.code === 200 && Array.isArray(res.data) ? res.data : []
		} catch (error) {
			console.error('[live-list] loadCategories failed', error)
			categoryList.value = []
		} finally {
			categoryLoading.value = false
		}
	}

	/**
	 * 加载顶部热门直播推荐。
	 *
	 * @returns {Promise<void>}
	 */
	async function loadFeaturedRooms() {
		try {
			const res = await getLiveRoomList({
				page: 1,
				limit: 3,
				status: 1,
				is_hot: 1,
				merchant_id: merchantId.value || '',
				category_id: activeCategoryId.value || '',
				keyword: String(keyword.value || '').trim()
			}, {
				showError: false
			})
			const list = extractRoomList(res)
			featuredList.value = list.map((item) => normalizeRoomItem(item)).filter((item) => item.id > 0)
		} catch (error) {
			console.error('[live-list] loadFeaturedRooms failed', error)
			featuredList.value = []
		}
	}

	/**
	 * 加载直播列表分页数据。
	 *
	 * @param {boolean} isRefresh 是否重置分页。
	 * @returns {Promise<void>}
	 */
	async function loadRecommendRooms(isRefresh = false) {
		if (loading.value) return

		if (isRefresh) {
			page.value = 1
			finished.value = false
		}

		const requestPage = page.value
		loading.value = true

		try {
			const res = await getLiveRoomList({
				page: requestPage,
				limit: limit.value,
				keyword: String(keyword.value || '').trim(),
				merchant_id: merchantId.value || '',
				category_id: activeCategoryId.value || '',
				status: activeStatus.value,
				is_hot: activeIsHot.value
			}, {
				showError: false
			})

			const data = res && res.code === 200 && res.data ? res.data : {}
			const list = Array.isArray(data.list) ?
				data.list.map((item) => normalizeRoomItem(item)).filter((item) => item.id > 0) : []

			recommendList.value = (isRefresh || requestPage === 1) ? list : recommendList.value.concat(list)
			total.value = Number(data.total || 0)

			const currentPage = Number(data.page || requestPage)
			const totalPage = Number(data.totalPage || 0)
			finished.value = totalPage > 0 ? currentPage >= totalPage : list.length < limit.value
			page.value = finished.value ? currentPage : currentPage + 1
		} catch (error) {
			console.error('[live-list] loadRecommendRooms failed', error)
			if (isRefresh) {
				recommendList.value = []
				total.value = 0
				finished.value = true
			}
		} finally {
			loading.value = false
			refreshing.value = false
			uni.stopPullDownRefresh()
		}
	}

	/**
	 * 从统一响应中取出直播列表数组。
	 *
	 * @param {Object} res 接口响应。
	 * @returns {Array<Object>} 原始直播间数组。
	 */
	function extractRoomList(res = {}) {
		if (!res || res.code !== 200 || !res.data) return []
		return Array.isArray(res.data.list) ? res.data.list : []
	}

	/**
	 * 规范化直播卡片数据。
	 *
	 * @param {Object} item 接口返回的直播间对象。
	 * @returns {Object} 页面可直接消费的数据。
	 */
	function normalizeRoomItem(item = {}) {
		const anchor = item.anchor && typeof item.anchor === 'object' ? item.anchor : {}
		const status = Number(item.status || 0)
		const cover = item.cover_image || item.cover || item.banner || item.poster || ''
		const startTime = String(item.start_time || '')
		const endTime = String(item.end_time || '')
		const viewerCount = Number(item.view_count || item.online_num || item.watch_num || item.viewer_count || 0)
		const likeCount = Number(item.like_count || item.like_num || item.favor_count || item.favorite_count || 0)
		const anchorId = Number(item.live_anchor_id || item.anchor_id || anchor.id || 0)
		const anchorName = item.anchor_name || anchor.name || '官方频道'
		const anchorAvatar = resolveAssetUrl(item.anchor_avatar || anchor.avatar || '') ||
			'/static/images/avatar_default.jpg'
		const title = item.title || '直播内容待完善'
		const followSource = item.is_followed !== undefined ? item.is_followed : item.follow_status
		const isFollowed = resolveFollowStatus(followSource)

		return {
			id: Number(item.id || 0),
			anchorId,
			status,
			title,
			cover: resolveAssetUrl(cover),
			anchorName,
			anchorAvatar,
			anchorInitial: getAnchorInitial(anchorName),
			viewerCount,
			likeCount,
			viewerText: buildViewerText(status, viewerCount),
			audienceText: buildAudienceText(viewerCount),
			heatText: buildHeatText(likeCount, viewerCount),
			stateText: buildStateText(status),
			stateClass: `room-card__state--${status}`,
			startTime,
			endTime,
			liveTimeText: buildLiveTimeText(startTime, endTime),
			isFollowed,
			followText: buildFollowText(isFollowed),
			isFollowSubmitting: false,
			tags: normalizeTagList(item.tag_list)
		}
	}

	/**
	 * 解析接口返回的关注状态。
	 *
	 * @param {*} value 接口返回的布尔值、数字或字符串。
	 * @returns {boolean} 是否已关注。
	 */
	function resolveFollowStatus(value) {
		if (value === true) return true
		if (typeof value === 'string') {
			const normalized = value.trim().toLowerCase()
			return normalized === '1' || normalized === 'true'
		}
		return Number(value || 0) === 1
	}

	/**
	 * 构建关注按钮文案。
	 *
	 * @param {boolean} isFollowed 是否已关注。
	 * @returns {string} 按钮文案。
	 */
	function buildFollowText(isFollowed) {
		return isFollowed ? '已关注' : '关注'
	}

	/**
	 * 搜索直播内容。
	 *
	 * @returns {void}
	 */
	function handleSearch() {
		reloadAllSections()
	}

	/**
	 * 切换顶部分类。
	 *
	 * @param {number} categoryId 分类 ID，0 表示全部。
	 * @returns {void}
	 */
	function handleCategoryChange(categoryId) {
		if (Number(categoryId || 0) === activeCategoryId.value) return
		activeCategoryId.value = Number(categoryId || 0)
		reloadAllSections()
	}

	/**
	 * 重新加载页面所有直播区块。
	 *
	 * @returns {Promise<void>}
	 */
	async function reloadAllSections() {
		await Promise.all([
			loadFeaturedRooms(),
			loadRecommendRooms(true)
		])
	}

	/**
	 * 触发下拉刷新。
	 *
	 * @returns {void}
	 */
	function handleRefresh() {
		refreshing.value = true
		loadCategories().finally(() => {
			reloadAllSections()
		})
	}

	/**
	 * 触发分页加载更多。
	 *
	 * @returns {void}
	 */
	function handleLoadMore() {
		if (loading.value || finished.value) return
		loadRecommendRooms(false)
	}

	/**
	 * 返回上一页；刷新直达无页面栈时回到首页。
	 *
	 * @returns {void}
	 */
	function handleBack() {
		const pages = getCurrentPages()
		if (Array.isArray(pages) && pages.length > 1) {
			uni.navigateBack({
				fail: () => {
					uni.switchTab({
						url: '/pages/index/index'
					})
				}
			})
			return
		}

		uni.switchTab({
			url: '/pages/index/index'
		})
	}

	/**
	 * 打开筛选弹窗。
	 *
	 * @returns {void}
	 */
	function openFilterSheet() {
		draftFilter.value = {
			status: activeStatus.value,
			isHot: activeIsHot.value
		}
		showFilterSheet.value = true
	}

	/**
	 * 关闭筛选弹窗。
	 *
	 * @returns {void}
	 */
	function closeFilterSheet() {
		showFilterSheet.value = false
	}

	/**
	 * 选择弹窗内状态。
	 *
	 * @param {number|string} status 直播状态。
	 * @returns {void}
	 */
	function selectDraftStatus(status) {
		draftFilter.value.status = status
	}

	/**
	 * 选择弹窗内热门筛选。
	 *
	 * @param {number|string} isHot 热门标识。
	 * @returns {void}
	 */
	function selectDraftHot(isHot) {
		draftFilter.value.isHot = isHot
	}

	/**
	 * 重置弹窗筛选条件。
	 *
	 * @returns {void}
	 */
	function resetDraftFilter() {
		draftFilter.value = {
			status: '',
			isHot: ''
		}
	}

	/**
	 * 应用筛选条件并刷新数据。
	 *
	 * @returns {void}
	 */
	function applyFilter() {
		activeStatus.value = draftFilter.value.status
		activeIsHot.value = draftFilter.value.isHot
		showFilterSheet.value = false
		reloadAllSections()
	}

	/**
	 * 进入直播间页面。
	 *
	 * @param {Object} item 直播间卡片数据。
	 * @returns {void}
	 */
	function openRoom(item = {}) {
		const roomId = Number(item.id || 0)
		if (roomId <= 0) {
			uni.showToast({
				title: '直播间信息缺失',
				icon: 'none'
			})
			return
		}
		uni.navigateTo({
			url: `/pages/lives/room/index?id=${roomId}`
		})
	}

	/**
	 * 处理直播列表卡片关注按钮点击。
	 *
	 * @param {Object} item 直播间卡片数据。
	 * @returns {Promise<void>}
	 */
	async function handleFollow(item = {}) {
		const anchorId = Number(item.anchorId || 0)
		if (anchorId <= 0) {
			uni.showToast({
				title: '主播信息缺失',
				icon: 'none'
			})
			return
		}
		if (item.isFollowSubmitting || followSubmittingAnchorIds.value.includes(anchorId)) {
			return
		}

		followSubmittingAnchorIds.value.push(anchorId)
		syncAnchorFollowSubmitting(anchorId, true)
		try {
			const res = await toggleLiveAnchorFollow({
				anchor_id: anchorId
			}, {
				showError: false
			})

			if (!res || res.code !== 200) {
				uni.showToast({
					title: (res && res.msg) || '操作失败',
					icon: 'none'
				})
				return
			}

			const isFollowed = resolveFollowStatus(res.data && res.data.is_followed)
			syncAnchorFollowState(anchorId, isFollowed)
			uni.showToast({
				title: isFollowed ? '已关注' : '已取消关注',
				icon: 'none'
			})
		} catch (error) {
			console.error('[live-list] toggleLiveAnchorFollow failed', error)
			uni.showToast({
				title: '操作失败，请稍后再试',
				icon: 'none'
			})
		} finally {
			removeFollowSubmittingAnchor(anchorId)
			syncAnchorFollowSubmitting(anchorId, false)
		}
	}

	/**
	 * 移除当前处于关注提交中的主播。
	 *
	 * @param {number} anchorId 主播 ID。
	 * @returns {void}
	 */
	function removeFollowSubmittingAnchor(anchorId) {
		const index = followSubmittingAnchorIds.value.indexOf(Number(anchorId || 0))
		if (index >= 0) {
			followSubmittingAnchorIds.value.splice(index, 1)
		}
	}

	/**
	 * 同步同一主播在所有直播卡片中的关注状态。
	 *
	 * @param {number} anchorId 主播 ID。
	 * @param {boolean} isFollowed 是否已关注。
	 * @returns {void}
	 */
	function syncAnchorFollowState(anchorId, isFollowed) {
		const normalizedAnchorId = Number(anchorId || 0)
		if (normalizedAnchorId <= 0) return

		featuredList.value = mapAnchorFollowState(featuredList.value, normalizedAnchorId, {
			isFollowed: Boolean(isFollowed)
		})
		recommendList.value = mapAnchorFollowState(recommendList.value, normalizedAnchorId, {
			isFollowed: Boolean(isFollowed)
		})
	}

	/**
	 * 同步同一主播在所有直播卡片中的提交状态。
	 *
	 * @param {number} anchorId 主播 ID。
	 * @param {boolean} isSubmitting 是否提交中。
	 * @returns {void}
	 */
	function syncAnchorFollowSubmitting(anchorId, isSubmitting) {
		const normalizedAnchorId = Number(anchorId || 0)
		if (normalizedAnchorId <= 0) return

		featuredList.value = mapAnchorFollowState(featuredList.value, normalizedAnchorId, {
			isFollowSubmitting: Boolean(isSubmitting)
		})
		recommendList.value = mapAnchorFollowState(recommendList.value, normalizedAnchorId, {
			isFollowSubmitting: Boolean(isSubmitting)
		})
	}

	/**
	 * 按主播 ID 更新直播卡片字段。
	 *
	 * @param {Array<Object>} list 直播卡片列表。
	 * @param {number} anchorId 主播 ID。
	 * @param {Object} patch 更新字段。
	 * @returns {Array<Object>} 更新后的列表。
	 */
	function mapAnchorFollowState(list = [], anchorId, patch = {}) {
		if (!Array.isArray(list) || list.length === 0) return []

		return list.map((room) => {
			if (Number(room.anchorId || 0) !== anchorId) {
				return room
			}

			const nextRoom = {
				...room,
				...patch
			}
			if (Object.prototype.hasOwnProperty.call(patch, 'isFollowed')) {
				nextRoom.followText = buildFollowText(Boolean(nextRoom.isFollowed))
			}
			return nextRoom
		})
	}

	/**
	 * 兼容旧入口：传入直播间 ID 时直接跳转直播间页。
	 *
	 * @param {number} roomId 直播间 ID。
	 * @returns {void}
	 */
	function redirectToRoom(roomId) {
		const url = `/pages/lives/room/index?id=${roomId}`
		uni.redirectTo({
			url,
			fail: () => {
				uni.navigateTo({
					url
				})
			}
		})
	}

	/**
	 * 构建直播观看文案。
	 *
	 * @param {number} status 直播状态。
	 * @param {number} viewerCount 观看人数。
	 * @returns {string} 展示文案。
	 */
	function buildViewerText(status, viewerCount) {
		if (status === 1) {
			return viewerCount > 0 ? `${formatCount(viewerCount)} 观众` : '热度上升中'
		}
		return viewerCount > 0 ? `${formatCount(viewerCount)} 预约` : '即将开播'
	}

	/**
	 * 构建卡片右上角观众数文案。
	 *
	 * @param {number} viewerCount 观看人数。
	 * @returns {string} 观众数文案。
	 */
	function buildAudienceText(viewerCount) {
		return Number(viewerCount || 0) > 0 ? formatCount(viewerCount) : '0'
	}

	/**
	 * 构建卡片左下角热度文案。
	 *
	 * @param {number} likeCount 点赞数。
	 * @param {number} viewerCount 观看人数。
	 * @returns {string} 热度文案。
	 */
	function buildHeatText(likeCount, viewerCount) {
		const count = Number(likeCount || 0) > 0 ? likeCount : viewerCount
		return Number(count || 0) > 0 ? formatCount(count) : '0'
	}

	/**
	 * 构建直播卡片时间段文案。
	 *
	 * @param {string} startTime 开播时间。
	 * @param {string} endTime 结束时间。
	 * @returns {string} 时间段文案。
	 */
	function buildLiveTimeText(startTime, endTime) {
		const startLabel = formatTimeLabel(startTime)
		const endLabel = formatTimeLabel(endTime)
		if (startLabel !== '--:--' && endLabel !== '--:--') {
			return `${startLabel}-${endLabel}`
		}
		if (startLabel !== '--:--') {
			return startLabel
		}
		return '直播中'
	}

	/**
	 * 构建直播状态标签。
	 *
	 * @param {number} status 直播状态。
	 * @returns {string} 状态文本。
	 */
	function buildStateText(status) {
		if (status === 1) return '直播中'
		return '预告'
	}

	/**
	 * 格式化数量文本。
	 *
	 * @param {number|string} value 数量值。
	 * @returns {string} 压缩展示文案。
	 */
	function formatCount(value) {
		const count = Number(value || 0)
		if (count >= 10000) return `${(count / 10000).toFixed(count >= 100000 ? 0 : 1)}万`
		return `${count}`
	}

	/**
	 * 解析时间字符串。
	 *
	 * @param {string} value 时间字符串。
	 * @returns {Date|null} 成功返回 Date，失败返回 null。
	 */
	function parseDateTime(value) {
		const source = String(value || '').trim()
		if (!source) return null
		const timestamp = Date.parse(source.replace(/-/g, '/'))
		if (Number.isNaN(timestamp)) return null
		return new Date(timestamp)
	}

	/**
	 * 格式化时间标签。
	 *
	 * @param {string} value 时间字符串。
	 * @returns {string} 时间文案。
	 */
	function formatTimeLabel(value) {
		const date = parseDateTime(value)
		if (!date) return '--:--'
		const hour = `${date.getHours()}`.padStart(2, '0')
		const minute = `${date.getMinutes()}`.padStart(2, '0')
		return `${hour}:${minute}`
	}


	/**
	 * 规范化标签数组。
	 *
	 * @param {Array|string} source 标签源数据。
	 * @returns {Array<string>} 标签数组。
	 */
	function normalizeTagList(source) {
		if (Array.isArray(source)) {
			return source.map((item) => String(item || '').trim()).filter(Boolean)
		}
		return String(source || '').split(',').map((item) => item.trim()).filter(Boolean)
	}

	/**
	 * 解析资源地址。
	 *
	 * @param {string} url 原始资源地址。
	 * @returns {string} 可直接展示的资源地址。
	 */
	function resolveAssetUrl(url) {
		const source = String(url || '').trim()
		if (!source) return ''
		return resolveConfigAssetUrl(source)
	}

	/**
	 * 获取主播头像占位首字。
	 *
	 * @param {string} name 主播名称。
	 * @returns {string} 首字占位文本。
	 */
	function getAnchorInitial(name) {
		return String(name || '官').slice(0, 1)
	}
</script>

<style lang="scss" scoped>
	.live-list-page {
		position: relative;
		display: flex;
		flex-direction: column;
	}

	.live-list-page--sheet-open {
		overflow: hidden;
	}

	.live-header {
		display: flex;
		align-items: center;
		padding: 24rpx 24rpx 0;
		background: #f5f5f5;
	}

	.live-search {
		flex: 1;
		min-width: 0;
		height: 64rpx;
		padding: 0 22rpx;
		border-radius: 999rpx;
		background: #ffffff;
		display: flex;
		align-items: center;
		box-shadow: 0 4rpx 12rpx rgba(15, 23, 42, 0.03);
	}

	.live-search__input {
		flex: 1;
		height: 64rpx;
		margin-left: 14rpx;
		font-size: 26rpx;
		color: #1a1c1c;
	}

	.live-search__placeholder {
		color: #7e7576;
	}

	.filter-entry {
		position: relative;
		width: 64rpx;
		height: 64rpx;
		border-radius: 16rpx;
		background: #ffffff;
		display: flex;
		align-items: center;
		justify-content: center;
		box-shadow: 0 4rpx 12rpx rgba(15, 23, 42, 0.03);
	}

	.filter-entry--active {
		border: 2rpx solid var(--brand-primary, #ea580c);
	}

	.filter-entry__dot {
		position: absolute;
		top: 10rpx;
		right: 10rpx;
		width: 12rpx;
		height: 12rpx;
		border-radius: 50%;
		background: var(--brand-primary, #ea580c);
	}

	.category-wrap {
		position: relative;
		width: 100%;
		height: 84rpx;
		overflow: hidden;
		background: #f5f5f5;
	}

	.category-scroll {
		width: 100%;
		height: 112rpx;
		padding-bottom: 28rpx;
		box-sizing: content-box;
		background: #f5f5f5;
		-ms-overflow-style: none;
		scrollbar-width: none;
	}

	.category-scroll::-webkit-scrollbar {
		width: 0;
		height: 0;
		display: none;
	}

	/* #ifdef H5 */
	.category-scroll {
		overflow: hidden;
	}

	.category-scroll ::v-deep .uni-scroll-view,
	.category-scroll ::v-deep .uni-scroll-view-content {
		height: 104rpx !important;
		padding-bottom: 20rpx !important;
		box-sizing: content-box !important;
		overflow-x: auto !important;
		overflow-y: hidden !important;
		-ms-overflow-style: none !important;
		scrollbar-width: none !important;
	}

	.category-scroll ::v-deep .uni-scroll-view::-webkit-scrollbar,
	.category-scroll ::v-deep .uni-scroll-view-content::-webkit-scrollbar {
		width: 0 !important;
		height: 0 !important;
		display: none !important;
		background: transparent !important;
	}

	/* #endif */

	.category-track {
		display: flex;
		align-items: center;
		height: 84rpx;
		padding: 0 100rpx 0 24rpx;
		white-space: nowrap;
	}

	.category-filter {
		position: absolute;
		top: 0;
		right: 0;
		z-index: 2;
		width: 96rpx;
		height: 84rpx;
		padding-right: 24rpx;
		display: flex;
		align-items: center;
		justify-content: flex-end;
		box-sizing: border-box;
		background: #f5f5f5;
	}

	.category-tab {
		flex-shrink: 0;
		min-width: 48rpx;
		height: 48rpx;
		padding: 0 20rpx;
		margin-right: 26rpx;
		border-radius: 16rpx;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.category-tab--active {
		background: var(--brand-primary, #ea580c);
	}

	.category-tab__text {
		font-size: 26rpx;
		line-height: 1;
		color: #4c4546;
	}

	.category-tab--active .category-tab__text {
		color: #ffffff;
		font-weight: 700;
	}

	.content-scroll {
		flex: 1;
		min-height: 0;
	}

	.content-inner {
		padding: 8rpx 24rpx 40rpx;
	}

	/* #ifdef H5 */
	.content-inner {
		padding-bottom: calc(40rpx + var(--window-bottom, 0px));
	}

	/* #endif */

	/* #ifdef MP-WEIXIN */
	.content-inner {
		padding-bottom: calc(40rpx + env(safe-area-inset-bottom));
	}

	/* #endif */

	.section {}

	.section--first {
		margin-top: 0;
		margin-bottom: 10rpx;
	}

	.section-head {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin-bottom: 24rpx;
	}

	.section-head__left {
		display: flex;
		align-items: center;
		min-width: 0;
	}

	.section-head__pulse {
		width: 14rpx;
		height: 14rpx;
		margin-right: 12rpx;
		border-radius: 50%;
		background: var(--brand-primary, #ea580c);
	}

	.section-head__title {
		font-size: 36rpx;
		line-height: 44rpx;
		color: #1a1c1c;
		font-weight: 700;
	}

	.section-head__meta {
		flex-shrink: 0;
		font-size: 20rpx;
		line-height: 24rpx;
		color: var(--brand-primary, #ea580c);
		font-weight: 700;
	}

	.section-head__meta--muted {
		color: #7e7576;
	}

	.featured-card {
		position: relative;
		overflow: hidden;
		width: 100%;
		height: 410rpx;
		border-radius: 16rpx;
		background: #2f3131;
	}

	.featured-card__image {
		width: 100%;
		height: 100%;
	}

	.featured-card__image--fallback {
		background: #2f3131;
	}

	.featured-card__mask {
		position: absolute;
		left: 0;
		top: 0;
		right: 0;
		bottom: 0;
		background: rgba(0, 0, 0, 0.28);
	}

	.featured-card__top {
		position: absolute;
		left: 16rpx;
		top: 16rpx;
		right: 16rpx;
		display: flex;
		align-items: center;
		justify-content: space-between;
	}

	.state-badge {
		height: 36rpx;
		padding: 0 12rpx;
		border-radius: 8rpx;
		display: flex;
		align-items: center;
	}

	.state-badge--live {
		background: var(--brand-primary, #ea580c);
	}

	.state-badge--dark {
		background: rgba(0, 0, 0, 0.56);
	}

	.state-badge__dot {
		width: 8rpx;
		height: 8rpx;
		margin-right: 8rpx;
		border-radius: 50%;
		background: #ffffff;
	}

	.state-badge text {
		font-size: 18rpx;
		line-height: 1;
		color: #ffffff;
		font-weight: 700;
	}

	.featured-card__body {
		position: absolute;
		left: 20rpx;
		right: 20rpx;
		bottom: 20rpx;
	}

	.featured-card__title {
		display: block;
		max-height: 96rpx;
		overflow: hidden;
		font-size: 32rpx;
		line-height: 48rpx;
		color: #ffffff;
		font-weight: 700;
	}

	.featured-card__anchor {
		display: flex;
		align-items: center;
		margin-top: 12rpx;
	}

	.featured-card__avatar {
		flex-shrink: 0;
		width: 40rpx;
		height: 40rpx;
		border-radius: 50%;
		background: rgba(255, 255, 255, 0.24);
	}

	.featured-card__avatar--fallback {
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.featured-card__avatar--fallback text {
		font-size: 20rpx;
		color: #ffffff;
		font-weight: 700;
	}

	.featured-card__anchor-name {
		margin-left: 12rpx;
		font-size: 22rpx;
		line-height: 32rpx;
		color: rgba(255, 255, 255, 0.84);
	}

	.room-grid {
		display: flex;
		flex-wrap: wrap;
		margin-left: -8rpx;
		margin-right: -8rpx;
	}

	.room-card {
		width: 50%;
		padding: 0 8rpx 18rpx;
		box-sizing: border-box;
	}

	.room-card__inner {
		overflow: hidden;
		border-radius: 22rpx;
		background: var(--surface-card, #ffffff);
		box-shadow: 0 8rpx 24rpx rgba(15, 23, 42, 0.06);
	}

	.room-card--hover .room-card__inner {
		opacity: 0.92;
	}

	.room-card__media {
		position: relative;
		overflow: hidden;
		width: 100%;
		height: 252rpx;
		background: var(--surface-card-muted, #f3f4f6);
	}

	.room-card__image {
		width: 100%;
		height: 100%;
	}

	.room-card__image--fallback {
		background: var(--surface-card-soft, #f4f4f5);
	}

	.room-card__state {
		position: absolute;
		left: 14rpx;
		top: 14rpx;
		height: 40rpx;
		padding: 0 12rpx;
		border-radius: 9rpx;
		display: flex;
		align-items: center;
		box-sizing: border-box;
		box-shadow: 0 6rpx 16rpx rgba(244, 63, 94, 0.18);
	}

	.room-card__state--0 {
		background: rgba(24, 24, 27, 0.72);
	}

	.room-card__state--1 {
		background: var(--live-status-primary, #ff3f86);
	}

	.room-card__state text {
		font-size: 20rpx;
		line-height: 28rpx;
		color: var(--text-inverse, #ffffff);
		font-weight: 700;
	}

	.room-card__signal {
		display: flex;
		align-items: flex-end;
		height: 22rpx;
		margin-right: 8rpx;
	}

	.room-card__signal-bar {
		width: 5rpx;
		margin-right: 4rpx;
		border-radius: 999rpx;
		background: var(--text-inverse, #ffffff);
	}

	.room-card__signal-bar--short {
		height: 12rpx;
	}

	.room-card__signal-bar--mid {
		height: 17rpx;
	}

	.room-card__signal-bar--tall {
		height: 22rpx;
		margin-right: 0;
	}

	.room-card__audience,
	.room-card__heat,
	.room-card__time {
		position: absolute;
		height: 36rpx;
		padding: 0 12rpx;
		border-radius: 999rpx;
		display: flex;
		align-items: center;
		box-sizing: border-box;
		background: rgba(24, 18, 14, 0.42);
	}

	.room-card__audience {
		top: 14rpx;
		right: 14rpx;
	}

	.room-card__heat {
		left: 14rpx;
		bottom: 14rpx;
	}

	.room-card__time {
		right: 14rpx;
		bottom: 14rpx;
	}

	.room-card__audience text,
	.room-card__heat text,
	.room-card__time text {
		font-size: 20rpx;
		line-height: 28rpx;
		color: var(--text-inverse, #ffffff);
		font-weight: 600;
	}

	.room-card__audience-icon {
		margin-right: 6rpx;
		font-size: 22rpx;
	}

	.room-card__heat-icon {
		margin-right: 6rpx;
		font-size: 22rpx;
	}

	.room-card__body {
		padding: 16rpx 16rpx 18rpx;
		background: var(--surface-card, #ffffff);
	}

	.room-card__title {
		display: block;
		height: 72rpx;
		overflow: hidden;
		font-size: 26rpx;
		line-height: 36rpx;
		color: var(--text-title, #18181b);
		font-weight: 700;
	}

	.room-card__footer {
		display: flex;
		align-items: center;
		justify-content: space-between;
		min-width: 0;
		margin-top: 14rpx;
	}

	.room-card__anchor {
		display: flex;
		align-items: center;
		min-width: 0;
		flex: 1;
	}

	.room-card__anchor-avatar {
		display: block;
		flex-shrink: 0;
		width: 38rpx;
		height: 38rpx;
		border-radius: 50%;
		background: var(--surface-card-muted, #f3f4f6);
	}

	.room-card__anchor-name {
		display: block;
		min-width: 0;
		margin-left: 8rpx;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
		font-size: 22rpx;
		line-height: 30rpx;
		color: var(--text-primary, #18181b);
		font-weight: 500;
	}

	.room-card__verified {
		flex-shrink: 0;
		width: 24rpx;
		height: 24rpx;
		margin-left: 6rpx;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		background: var(--live-verified-bg, #7256f6);
	}

	.room-card__verified text {
		font-size: 16rpx;
		line-height: 1;
		color: var(--text-inverse, #ffffff);
		font-weight: 700;
	}

	.room-card__follow {
		flex-shrink: 0;
		min-width: 72rpx;
		height: 40rpx;
		margin-left: 8rpx;
		padding: 0 16rpx;
		border-radius: 999rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		box-sizing: border-box;
		background: var(--live-follow-bg, #f2edff);
	}

	.room-card__follow--active {
		border: 2rpx solid var(--border-light, #e5e7eb);
		background: var(--surface-card-soft, #f4f4f5);
	}

	.room-card__follow--disabled {
		opacity: 0.62;
		pointer-events: none;
	}

	.room-card__follow text {
		font-size: 22rpx;
		line-height: 30rpx;
		color: var(--live-follow-text, #6d50f5);
		font-weight: 700;
	}

	.room-card__follow--active text {
		color: var(--text-secondary, #7e7576);
	}

	.state-panel {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		text-align: center;
		padding: 100rpx;
	}

	.state-panel__image {
		width: 300rpx;
		height: 220rpx;
	}

	.state-panel__title {
		display: block;
		margin-top: 18rpx;
		font-size: 30rpx;
		line-height: 40rpx;
		color: #1a1c1c;
		font-weight: 700;
	}

	.state-panel__desc {
		display: block;
		margin-top: 12rpx;
		font-size: 24rpx;
		line-height: 34rpx;
		color: #7e7576;
	}

	.state-panel__desc--loading {
		margin-top: 20rpx;
	}

	.load-more {
		padding: 20rpx 0 4rpx;
		text-align: center;
	}

	.load-more__text {
		font-size: 22rpx;
		line-height: 32rpx;
		color: #7e7576;
	}

	.filter-mask {
		position: fixed;
		left: 0;
		top: 0;
		right: 0;
		bottom: 0;
		z-index: 100;
		background: rgba(0, 0, 0, 0.42);
	}

	.filter-sheet {
		position: fixed;
		left: 0;
		right: 0;
		bottom: 0;
		z-index: 101;
		max-height: 78%;
		border-radius: 24rpx 24rpx 0 0;
		background: #f9f9f9;
		display: flex;
		flex-direction: column;
	}

	/* #ifdef H5 */
	.filter-sheet {
		bottom: var(--window-bottom);
	}

	/* #endif */

	.filter-sheet__handle {
		width: 80rpx;
		height: 12rpx;
		margin: 24rpx auto 20rpx;
		border-radius: 999rpx;
		background: #e2e2e2;
	}

	.filter-sheet__head {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 0 32rpx 24rpx;
	}

	.filter-sheet__title {
		font-size: 40rpx;
		line-height: 56rpx;
		color: #1a1c1c;
		font-weight: 700;
	}

	.filter-sheet__close {
		width: 56rpx;
		height: 56rpx;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.filter-sheet__scroll {
		flex: 1;
		min-height: 0;
		padding: 0 32rpx;
		box-sizing: border-box;
	}

	.filter-section {
		margin-bottom: 44rpx;
	}

	.filter-section__title {
		display: block;
		margin-bottom: 20rpx;
		font-size: 28rpx;
		line-height: 40rpx;
		color: #1a1c1c;
		font-weight: 700;
	}

	.option-grid {
		display: flex;
		flex-wrap: wrap;
		margin: -8rpx;
	}

	.option-chip {
		width: 50%;
		padding: 8rpx;
		box-sizing: border-box;
	}

	.option-grid--two .option-chip {
		width: 50%;
	}

	.option-chip text {
		display: block;
		height: 88rpx;
		border: 2rpx solid transparent;
		border-radius: 16rpx;
		background: #f3f3f3;
		color: #5b5f64;
		font-size: 26rpx;
		line-height: 88rpx;
		text-align: center;
	}

	.option-chip--active text {
		border-color: var(--brand-primary, #ea580c);
		background: #ffffff;
		color: var(--brand-primary, #ea580c);
		font-weight: 700;
	}

	.filter-actions {
		display: flex;
		padding: 24rpx 32rpx;
		border-top: 2rpx solid #e2e2e2;
		background: #f9f9f9;
	}

	/* #ifdef MP-WEIXIN */
	.filter-actions {
		padding-bottom: calc(24rpx + env(safe-area-inset-bottom));
	}

	/* #endif */

	.filter-actions__reset,
	.filter-actions__submit {
		height: 88rpx;
		border-radius: 16rpx;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.filter-actions__reset {
		flex: 1;
		background: #e2e2e2;
	}

	.filter-actions__submit {
		flex: 2;
		margin-left: 16rpx;
		background: var(--brand-primary, #ea580c);
	}

	.filter-actions__reset text,
	.filter-actions__submit text {
		font-size: 28rpx;
		line-height: 1;
		font-weight: 700;
	}

	.filter-actions__reset text {
		color: #5b5f64;
	}

	.filter-actions__submit text {
		color: #ffffff;
	}
</style>

<style lang="scss">
	.live-list-page .category-scroll,
	.live-list-page .category-scroll .uni-scroll-view,
	.live-list-page .category-scroll .uni-scroll-view-content {
		-ms-overflow-style: none;
		scrollbar-width: none;
	}

	.live-list-page .category-scroll::-webkit-scrollbar,
	.live-list-page .category-scroll .uni-scroll-view::-webkit-scrollbar,
	.live-list-page .category-scroll .uni-scroll-view-content::-webkit-scrollbar {
		width: 0;
		height: 0;
		display: none;
		background: transparent;
	}
</style>
