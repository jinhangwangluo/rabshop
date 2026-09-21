<template>
	<page-meta :page-style="themePageStyle"></page-meta>
	<view class="topic-list-page">
		<view class="topic-list-page__header" :style="miniProgramHeaderStyle">
			<view class="topic-topbar" :style="miniProgramTopbarStyle">
				<view class="topic-topbar__back" @click="handleGoBack">
					<text class="iconfont icon-back topic-topbar__back-icon"></text>
				</view>

				<view class="topic-topbar__search" :class="{ 'topic-topbar__search--filled': searchKeyword }"
					:style="miniProgramSearchStyle">
					<text class="iconfont icon-search topic-topbar__search-icon"></text>
					<input v-model="searchKeyword" class="topic-topbar__search-input" confirm-type="search"
						placeholder="搜索专题内容..." placeholder-class="topic-topbar__search-placeholder"
						@confirm="handleKeywordConfirm" />
				</view>
			</view>

			<view class="topic-tabs">
				<scroll-view scroll-x class="topic-tabs__scroll scrollbar-hidden" :show-scrollbar="false">
					<view class="topic-tabs__track">
						<view v-for="tab in categoryTabs" :key="tab.id" class="topic-tabs__item"
							:class="{ 'topic-tabs__item--active': activeRootCategoryId === tab.id }"
							@click="handleTabChange(tab.id)">
							<text class="topic-tabs__label">{{ tab.name }}</text>
						</view>
					</view>
				</scroll-view>
			</view>

			<view class="topic-sort-bar">
				<view class="topic-sort-bar__left">
					<view class="topic-sort-bar__sort-btn" @click="handleSortSelect">
						<text class="topic-sort-bar__sort-label">{{ currentSortLabel }}</text>
						<text class="iconfont icon-triangledownfill topic-sort-bar__sort-arrow"></text>
					</view>
					<view v-if="filterTagLabel" class="topic-sort-bar__filter-tag" @click="openFilterDrawer">
						<text>{{ filterTagLabel }}</text>
					</view>
				</view>
				<view class="topic-sort-bar__right">
					<view class="topic-sort-bar__icon-btn"
						:class="{ 'topic-sort-bar__icon-btn--active': drawerVisible }" @click="openFilterDrawer">
						<text class="iconfont icon-shaixuan topic-sort-bar__icon"></text>
					</view>
					<view class="topic-sort-bar__icon-btn"
						:class="{ 'topic-sort-bar__icon-btn--active': displayMode === 'compact' }"
						@click="toggleDisplayMode">
						<text class="iconfont icon-liebiao1 topic-sort-bar__icon"></text>
					</view>
				</view>
			</view>
		</view>

		<scroll-view scroll-y class="topic-list-page__scroll" :show-scrollbar="false" lower-threshold="80"
			@scrolltolower="handleLoadMore">
			<view class="topic-list-page__body" :style="miniProgramBodyStyle">
				<view v-if="loading && !topicList.length" class="topic-loading">
					<six-petal-spiral :active="true" :size="96" text="" :color="themePrimaryColor"
						:stroke-width="1.4" :particle-count="36" :trail-span="0.28" />
					<text class="topic-loading__text">专题加载中...</text>
				</view>

				<template v-else-if="normalizedTopicList.length">
					<template v-if="displayMode === 'large'">
						<view v-for="topic in normalizedTopicList" :key="topic.id" class="topic-feature-card"
							@click="handleTopicClick(topic)">
							<view class="topic-feature-card__cover">
								<image v-if="topic.cover" class="topic-feature-card__image" :src="topic.cover"
									mode="aspectFill" />
								<view v-else class="topic-feature-card__cover-fallback">
									<text>{{ getTopicInitial(topic.title) }}</text>
								</view>
								<view v-if="topic.badgeText" class="topic-badge" :class="topic.badgeClass">
									<text>{{ topic.badgeText }}</text>
								</view>
							</view>
							<view class="topic-feature-card__body">
								<text class="topic-feature-card__title">{{ topic.title }}</text>
								<text class="topic-feature-card__desc">{{ topic.summary }}</text>
								<view v-if="topic.tagList.length" class="topic-tag-row">
									<tag-preview v-for="tag in topic.tagList" :key="tag.renderKey" :tag="tag" />
								</view>
								<view class="topic-feature-card__price-row">
									<view class="topic-feature-card__price-group">
										<text class="topic-feature-card__price"
											:class="{ 'topic-feature-card__price--free': topic.isFree }">{{ topic.priceText }}</text>
										<view v-if="topic.memberPriceText"
											class="topic-feature-card__original-price topic-feature-card__original-price--vip">
											<image class="vip-price-icon" src="/static/images/vip.png"
												mode="aspectFill" />
											<text>{{ topic.memberPriceText }}</text>
										</view>
										<text v-else-if="topic.originalPriceText"
											class="topic-feature-card__original-price">{{ topic.originalPriceText }}</text>
									</view>
									<view v-if="topic.canCart" class="topic-cart-button topic-cart-button--inline"
										hover-class="topic-cart-button--hover" @click.stop="handleCartClick(topic)">
										<text class="iconfont icon-daohanggouwuche"></text>
									</view>
								</view>
								<view class="topic-feature-card__meta">
									<view class="topic-feature-card__meta-item">
										<text class="iconfont icon-liebiao1"></text>
										<text>{{ topic.buyCountText }} 销量</text>
									</view>
									<view class="topic-feature-card__meta-item">
										<text class="iconfont icon-order"></text>
										<text>{{ topic.contentCount }} 内容</text>
									</view>
								</view>
							</view>
						</view>
					</template>

					<view v-else class="topic-grid">
						<view v-for="topic in normalizedTopicList" :key="topic.id" class="topic-grid__item">
							<view class="topic-card" @click="handleTopicClick(topic)">
								<view class="topic-card__cover">
									<image v-if="topic.cover" class="topic-card__cover-img" :src="topic.cover"
										mode="aspectFill" />
									<view v-else class="topic-card__cover-fallback">
										<text>{{ getTopicInitial(topic.title) }}</text>
									</view>
									<view v-if="topic.badgeText" class="topic-card__tag" :class="topic.badgeClass">
										<text>{{ topic.badgeText }}</text>
									</view>
								</view>
								<view class="topic-card__body">
									<text class="topic-card__title">{{ topic.title }}</text>
									<text class="topic-card__desc">{{ topic.summary }}</text>
									<view v-if="topic.tagList.length" class="topic-tag-row topic-tag-row--small">
										<tag-preview v-for="tag in topic.tagList" :key="tag.renderKey" :tag="tag"
											 />
									</view>
									<view class="topic-card__price-row">
										<view class="topic-card__price-group">
											<text class="topic-card__price"
												:class="{ 'topic-card__price--free': topic.isFree }">{{ topic.priceText }}</text>
											<view v-if="topic.memberPriceText"
												class="topic-card__original-price topic-card__original-price--vip">
												<image class="vip-price-icon vip-price-icon--small"
													src="/static/images/vip.png" mode="aspectFill" />
												<text>{{ topic.memberPriceText }}</text>
											</view>
											<text v-else-if="topic.originalPriceText"
												class="topic-card__original-price">{{ topic.originalPriceText }}</text>
										</view>
										<view v-if="topic.canCart"
											class="topic-cart-button topic-cart-button--inline topic-cart-button--small"
											hover-class="topic-cart-button--hover" @click.stop="handleCartClick(topic)">
											<text class="iconfont icon-daohanggouwuche"></text>
										</view>
									</view>
									<view class="topic-card__meta">
										<view class="topic-card__meta-item">
											<text class="iconfont icon-liebiao1"></text>
											<text>{{ topic.buyCountText }} 销量</text>
										</view>
										<view class="topic-card__meta-item">
											<text class="iconfont icon-order"></text>
											<text>{{ topic.contentCount }} 内容</text>
										</view>
									</view>
								</view>
							</view>
						</view>
					</view>
				</template>

				<view v-else class="topic-empty">
					<image class="topic-empty__image" :src="emptyImage" mode="aspectFit"></image>
				</view>

				<view v-if="normalizedTopicList.length" class="load-more">
					<text v-if="loadingMore" class="load-more__text">加载中...</text>
					<text v-else-if="finished" class="load-more__text">没有更多了</text>
				</view>
			</view>
		</scroll-view>

		<view class="topic-filter-mask" :class="{ 'topic-filter-mask--visible': drawerVisible }"
			@click="closeFilterDrawer"></view>

		<view class="topic-filter-drawer" :class="{ 'topic-filter-drawer--visible': drawerVisible }" @click.stop>
			<view class="topic-filter-drawer__header">
				<text class="topic-filter-drawer__title">筛选条件</text>
				<view class="topic-filter-drawer__close" @click="closeFilterDrawer">
					<text class="iconfont icon-close"></text>
				</view>
			</view>

			<scroll-view scroll-y class="topic-filter-drawer__body" :show-scrollbar="false">
				<view class="filter-section">
					<view class="filter-section__head">
						<text class="filter-section__title">一级分类</text>
					</view>
					<view class="filter-option-wrap">
						<view class="filter-option-chip"
							:class="{ 'filter-option-chip--active': draftFilters.rootCategoryId === 0 }"
							@click="selectDraftRootCategory(0)">
							<text>全部分类</text>
						</view>
						<view v-for="item in rootCategoryOptions" :key="item.id" class="filter-option-chip"
							:class="{ 'filter-option-chip--active': draftFilters.rootCategoryId === item.id }"
							@click="selectDraftRootCategory(item.id)">
							<text>{{ item.name }}</text>
						</view>
					</view>
				</view>

				<view v-if="shouldShowDraftChildren" class="filter-section filter-section--spaced">
					<view class="filter-section__head">
						<text class="filter-section__title">二级分类</text>
					</view>
					<view v-if="draftChildCategories.length" class="filter-option-wrap">
						<view class="filter-option-chip"
							:class="{ 'filter-option-chip--active': draftFilters.categoryId === draftFilters.rootCategoryId }"
							@click="selectDraftChildCategory(draftFilters.rootCategoryId)">
							<text>全部{{ draftRootCategoryName }}</text>
						</view>
						<view v-for="child in draftChildCategories" :key="child.id" class="filter-option-chip"
							:class="{ 'filter-option-chip--active': draftFilters.categoryId === child.id }"
							@click="selectDraftChildCategory(child.id)">
							<text>{{ child.name }}</text>
						</view>
					</view>
					<view v-else class="filter-empty-text">
						<text>当前一级分类暂无二级分类</text>
					</view>
				</view>

				<view class="filter-section filter-section--spaced">
					<view class="filter-section__head">
						<text class="filter-section__title">金额区间 (元)</text>
					</view>
					<view class="filter-price-row">
						<input v-model="draftFilters.minPrice" class="filter-price-row__input" type="number"
							placeholder="最低价" placeholder-class="filter-price-row__placeholder" />
						<view class="filter-price-row__divider"></view>
						<input v-model="draftFilters.maxPrice" class="filter-price-row__input" type="number"
							placeholder="最高价" placeholder-class="filter-price-row__placeholder" />
					</view>
				</view>
			</scroll-view>

			<view class="topic-filter-drawer__footer">
				<view class="topic-filter-drawer__button topic-filter-drawer__button--ghost"
					@click="handleResetFilters">
					<text>重置</text>
				</view>
				<view class="topic-filter-drawer__button topic-filter-drawer__button--primary"
					@click="handleConfirmFilters">
					<text>确定</text>
				</view>
			</view>
		</view>
	</view>
</template>

<script setup>
	import {
		computed,
		ref
	} from 'vue'
	import {
		onLoad
	} from '@dcloudio/uni-app'
	import {
		addCart
	} from '@/api/cart'
	import {
		getSpecialCategoryTree
	} from '@/api/category'
	import {
		getSpecialList
	} from '@/api/special'
	import {
		resolveAssetUrl
	} from '@/utils/runtime_config'
	import {
		normalizeDisplayTags
	} from '@/utils/tag'
	import {
		getMiniProgramSearchNavMetrics,
		rpxToPx
	} from '@/utils/mini_program_nav'

	const SPECIAL_ORDER_TYPE = 3
	const MINI_PROGRAM_HEADER_FALLBACK = {
		topGap: 6,
		menuHeight: 32,
		searchHeight: 38,
		contentGap: 12,
		leftInset: 12,
		rightInset: 104
	}
	const HEADER_REST_HEIGHT_RPX = 206

	const ALL_CATEGORY_TAB = {
		id: 0,
		name: '全部'
	}

	const SORT_OPTIONS = [{
			key: 'default',
			label: '综合排序'
		},
		{
			key: 'sales',
			label: '销量优先'
		},
		{
			key: 'latest',
			label: '最新发布'
		}
	]

	function createDefaultFilters() {
		return {
			rootCategoryId: 0,
			categoryId: 0,
			minPrice: '',
			maxPrice: '',
			sort: 'default'
		}
	}

	defineOptions({
		name: 'ContentTopicListPage'
	})

	const loading = ref(false)
	const loadingMore = ref(false)
	const finished = ref(false)
	const page = ref(1)
	const limit = ref(10)
	const total = ref(0)
	const drawerVisible = ref(false)
	const displayMode = ref('compact')
	const merchantId = ref(0)
	const searchKeyword = ref('')
	const activeRootCategoryId = ref(0)
	const activeCategoryId = ref(0)
	const categoryTree = ref([])
	const categoryTabs = ref([ALL_CATEGORY_TAB])
	const emptyImage = ref(resolveAssetUrl('/static/images/empty/special-empty.png'))
	const topicList = ref([])
	const appliedFilters = ref(createDefaultFilters())
	const draftFilters = ref(createDefaultFilters())
	const miniProgramNavMetrics = ref({
		...MINI_PROGRAM_HEADER_FALLBACK,
		statusBarHeight: 0,
		windowWidth: 375
	})

	const miniProgramHeaderHeightPx = computed(() => {
		const metrics = miniProgramNavMetrics.value
		return metrics.statusBarHeight + metrics.topGap + metrics.searchHeight + metrics.contentGap +
			rpxToPx(HEADER_REST_HEIGHT_RPX, metrics.windowWidth)
	})
	const miniProgramHeaderStyle = computed(() => {
		// #ifdef MP-WEIXIN
		return `padding-top: ${miniProgramNavMetrics.value.statusBarHeight}px;`
		// #endif
		return ''
	})
	const miniProgramTopbarStyle = computed(() => {
		// #ifdef MP-WEIXIN
		const metrics = miniProgramNavMetrics.value
		return [
			`height: ${metrics.topGap + metrics.searchHeight + metrics.contentGap}px`,
			`padding-top: ${metrics.topGap}px`,
			`padding-bottom: ${metrics.contentGap}px`,
			`padding-right: ${metrics.rightInset}px`
		].join(';')
		// #endif
		return ''
	})
	const miniProgramSearchStyle = computed(() => {
		// #ifdef MP-WEIXIN
		return `height: ${miniProgramNavMetrics.value.searchHeight}px;`
		// #endif
		return ''
	})
	const miniProgramBodyStyle = computed(() => {
		// #ifdef MP-WEIXIN
		return `padding-top: ${miniProgramHeaderHeightPx.value}px;`
		// #endif
		return ''
	})
	const currentSortLabel = computed(() => {
		const current = SORT_OPTIONS.find((item) => item.key === appliedFilters.value.sort)
		return current ? current.label : '综合排序'
	})
	const hasPriceFilter = computed(() => {
		return !!(String(appliedFilters.value.minPrice || '').trim() || String(appliedFilters.value.maxPrice || '')
			.trim())
	})
	const filterTagLabel = computed(() => {
		const categoryLabel = getCategorySelectionLabel(activeCategoryId.value)
		if (categoryLabel && hasPriceFilter.value) {
			return `${categoryLabel} · 价格筛选`
		}
		if (categoryLabel) {
			return `分类：${categoryLabel}`
		}
		if (hasPriceFilter.value) {
			return '价格筛选已启用'
		}
		return ''
	})
	const rootCategoryOptions = computed(() => {
		return Array.isArray(categoryTree.value) ?
			categoryTree.value.filter((item) => Number(item.id || 0) > 0 && Number(item.pid || 0) === 0) : []
	})
	const draftRootCategory = computed(() => {
		return getCategoryBranch(draftFilters.value.rootCategoryId).root
	})
	const draftChildCategories = computed(() => {
		const root = draftRootCategory.value
		return root && Array.isArray(root.children) ? root.children.filter((item) => Number(item.id || 0) > 0) : []
	})
	const draftRootCategoryName = computed(() => {
		return draftRootCategory.value ? String(draftRootCategory.value.name || '一级分类').trim() : '一级分类'
	})
	const shouldShowDraftChildren = computed(() => {
		return Number(draftFilters.value.rootCategoryId || 0) > 0
	})
	const normalizedTopicList = computed(() => {
		return topicList.value
			.map((item) => normalizeTopicItem(item))
			.filter((item) => item.id > 0)
	})

	onLoad((options) => {
		syncMiniProgramNavMetrics()
		initPage(options)
		loadPageData()
	})

	function syncMiniProgramNavMetrics() {
		// #ifdef MP-WEIXIN
		miniProgramNavMetrics.value = getMiniProgramSearchNavMetrics(MINI_PROGRAM_HEADER_FALLBACK)
		// #endif
	}
	async function loadPageData() {
		await Promise.all([
			fetchCategoryTabs(),
			fetchTopicList(true)
		])
	}
	function initPage(options = {}) {
		const layout = String(options.layout || '').trim()
		const keyword = decodeURIComponent(options.keyword || '').trim()
		const categoryId = Number(options.category_id || options.categoryId || 0)
		const merchantIdValue = Number(options.merchant_id || options.merchantId || 0)

		if (layout === 'large') {
			displayMode.value = 'large'
		}

		if (keyword) {
			searchKeyword.value = keyword
		}

		if (categoryId > 0) {
			activeCategoryId.value = categoryId
		}

		if (merchantIdValue > 0) {
			merchantId.value = merchantIdValue
		}
	}
	async function fetchCategoryTabs() {
		try {
			const res = await getSpecialCategoryTree({}, {
				showError: false
			})
			const list = Array.isArray(res.data) ? res.data : []
			categoryTree.value = list
			const tabs = list
				.map((item) => ({
					id: Number(item.id || 0),
					name: String(item.name || item.label || '').trim()
				}))
				.filter((item) => item.id > 0 && item.name)

			categoryTabs.value = [ALL_CATEGORY_TAB].concat(tabs)
			syncActiveCategorySelection()
		} catch (error) {
			console.error('[special-list] fetchCategoryTabs failed', error)
			categoryTree.value = []
			categoryTabs.value = [ALL_CATEGORY_TAB]
			activeRootCategoryId.value = 0
			if (activeCategoryId.value > 0 && !categoryTabs.value.some((item) => item.id === activeCategoryId
					.value)) {
				activeCategoryId.value = 0
			}
		}
	}
	async function fetchTopicList(isRefresh = false) {
		if (loading.value || loadingMore.value) {
			return
		}

		if (!isRefresh && finished.value) {
			return
		}

		const requestPage = isRefresh ? 1 : page.value

		if (isRefresh) {
			finished.value = false
		}

		if (requestPage === 1) {
			loading.value = true
		} else {
			loadingMore.value = true
		}

		try {
			const res = await getSpecialList(buildListParams(requestPage), {
				showError: false
			})

			if (!res || res.code !== 200 || !res.data) {
				if (requestPage === 1) {
					topicList.value = []
					total.value = 0
					finished.value = true
				}
				return
			}

			const data = res.data || {}
			const list = Array.isArray(data.list) ? data.list : []
			const safeList = list.filter((item) => Number(item.id || 0) > 0)

			topicList.value = requestPage === 1 ? safeList : topicList.value.concat(safeList)
			total.value = Number(data.total || 0)

			const currentPage = Number(data.page || requestPage)
			const totalPage = Number(data.totalPage || 0)
			if (totalPage > 0) {
				finished.value = currentPage >= totalPage
			} else {
				finished.value = safeList.length < limit.value
			}

			page.value = finished.value ? currentPage : currentPage + 1
		} catch (error) {
			console.error('[special-list] fetchTopicList failed', error)
			if (requestPage === 1) {
				topicList.value = []
				total.value = 0
				finished.value = true
			}
		} finally {
			loading.value = false
			loadingMore.value = false
		}
	}
	function buildListParams(page = 1) {
		return {
			page,
			limit: limit.value,
			keyword: String(searchKeyword.value || '').trim(),
			category_id: activeCategoryId.value || '',
			merchant_id: merchantId.value || '',
			min_price: normalizePriceInput(appliedFilters.value.minPrice),
			max_price: normalizePriceInput(appliedFilters.value.maxPrice),
			sort: appliedFilters.value.sort || 'default'
		}
	}
	function normalizeTopicItem(item = {}) {
		const id = Number(item.id || 0)
		const price = Number(item.price || 0)
		const originalPrice = Number(item.original_price || 0)
		const memberPrice = Number(item.member_price || item.vip_price || item.memberPrice || item.vipPrice || 0)
		const memberPriceText = String(item.member_price_text || '').trim()
		const memberPriceAvailable = item.member_price_available === true ||
			Number(item.member_price_available || 0) === 1 || Boolean(memberPriceText)
		const specialType = Number(item.special_type || 0)
		const isFree = specialType === 0 || price === 0
		const isHot = Number(item.is_hot || 0) === 1
		const summary = String(item.description || '').trim() || '专题内容持续更新中'
		const buyCount = Number(item.buy_count || 0)
		const viewCount = Number(item.view_count || 0)
		const contentCount = Number(item.content_count || 0)
		const tagList = buildTopicTagList(item)

		return {
			id,
			title: String(item.title || '专题标题待完善').trim(),
			cover: String(item.cover || '').trim(),
			summary,
			price,
			originalPrice,
			memberPrice,
			isFree,
			priceText: isFree ? '免费' : `¥${formatMoney(price)}`,
			originalPriceText: !isFree && originalPrice > price ? `¥${formatMoney(originalPrice)}` : '',
			memberPriceText: !isFree && memberPriceAvailable && memberPrice > 0 ?
				(memberPriceText || `¥${formatMoney(memberPrice)}`) :
				'',
			tagList,
			buyCountText: formatCount(buyCount),
			viewCountText: formatCount(viewCount),
			contentCount,
			canCart: !isFree && price > 0,
			badgeText: isHot ? '热门' : (isFree ? '免费' : ''),
			badgeClass: isHot ? 'topic-badge--hot' : 'topic-badge--free'
		}
	}
	function buildTopicTagList(item = {}) {
		return normalizeDisplayTags(item.tag_list, 3)
	}
	function handleKeywordConfirm() {
		searchKeyword.value = String(searchKeyword.value || '').trim()
		uni.hideKeyboard()
		fetchTopicList(true)
	}
	function handleTabChange(categoryId) {
		const nextId = Number(categoryId || 0)
		if (nextId === activeRootCategoryId.value && activeCategoryId.value === nextId) {
			return
		}
		activeRootCategoryId.value = nextId > 0 ? nextId : 0
		activeCategoryId.value = nextId
		fetchTopicList(true)
	}
	function handleSortSelect() {
		uni.showActionSheet({
			itemList: SORT_OPTIONS.map((item) => item.label),
			success: ({
				tapIndex
			}) => {
				const target = SORT_OPTIONS[tapIndex]
				if (!target || target.key === appliedFilters.value.sort) {
					return
				}
				appliedFilters.value.sort = target.key
				fetchTopicList(true)
			}
		})
	}
	function openFilterDrawer() {
		const branch = getCategoryBranch(activeCategoryId.value || activeRootCategoryId.value)
		const rootId = Number(branch.root && branch.root.id ? branch.root.id : activeRootCategoryId.value || 0)
		const categoryId = Number(activeCategoryId.value || rootId || 0)
		draftFilters.value = {
			rootCategoryId: rootId,
			categoryId: rootId > 0 ? (categoryId > 0 ? categoryId : rootId) : 0,
			minPrice: appliedFilters.value.minPrice,
			maxPrice: appliedFilters.value.maxPrice,
			sort: appliedFilters.value.sort
		}
		drawerVisible.value = true
	}
	function closeFilterDrawer() {
		drawerVisible.value = false
	}
	function handleResetFilters() {
		draftFilters.value = {
			...createDefaultFilters(),
			sort: appliedFilters.value.sort
		}
	}
	function selectDraftRootCategory(categoryId) {
		const nextRootId = Number(categoryId || 0)
		draftFilters.value.rootCategoryId = nextRootId
		draftFilters.value.categoryId = nextRootId > 0 ? nextRootId : 0
	}
	function selectDraftChildCategory(categoryId) {
		const nextCategoryId = Number(categoryId || 0)
		if (nextCategoryId <= 0 || Number(draftFilters.value.rootCategoryId || 0) <= 0) {
			return
		}
		draftFilters.value.categoryId = nextCategoryId
	}
	function handleConfirmFilters() {
		const minPrice = normalizePriceInput(draftFilters.value.minPrice)
		const maxPrice = normalizePriceInput(draftFilters.value.maxPrice)
		const rootCategoryId = Number(draftFilters.value.rootCategoryId || 0)
		const categoryId = rootCategoryId > 0 ?
			Number(draftFilters.value.categoryId || rootCategoryId) :
			0
		const normalizedDraft = {
			rootCategoryId,
			categoryId: rootCategoryId > 0 ? (categoryId > 0 ? categoryId : rootCategoryId) : 0,
			minPrice: minPrice === null ? '' : minPrice,
			maxPrice: maxPrice === null ? '' : maxPrice,
			sort: appliedFilters.value.sort
		}

		if (normalizedDraft.minPrice !== '' && normalizedDraft.maxPrice !== '' && normalizedDraft.minPrice >
			normalizedDraft.maxPrice) {
			const currentMin = normalizedDraft.minPrice
			normalizedDraft.minPrice = normalizedDraft.maxPrice
			normalizedDraft.maxPrice = currentMin
		}

		appliedFilters.value = normalizedDraft
		activeRootCategoryId.value = normalizedDraft.rootCategoryId
		activeCategoryId.value = normalizedDraft.categoryId
		drawerVisible.value = false
		fetchTopicList(true)
	}
	function resetAllFilters() {
		searchKeyword.value = ''
		activeRootCategoryId.value = 0
		activeCategoryId.value = 0
		appliedFilters.value = createDefaultFilters()
		draftFilters.value = createDefaultFilters()
		fetchTopicList(true)
	}
	function handleLoadMore() {
		if (loading.value || loadingMore.value || finished.value) {
			return
		}
		fetchTopicList(false)
	}
	function toggleDisplayMode() {
		displayMode.value = displayMode.value === 'large' ? 'compact' : 'large'
	}
	function handleTopicClick(topic = {}) {
		const topicId = Number(topic.id || 0)
		if (!topicId) {
			return
		}

		uni.navigateTo({
			url: `/pages/contents/special/detail/index?id=${topicId}`
		})
	}
	async function handleCartClick(topic = {}) {
		const topicId = Number(topic.id || 0)
		if (!topicId) {
			return
		}

		if (topic.isFree || Number(topic.price || 0) <= 0) {
			handleTopicClick(topic)
			return
		}

		try {
			const res = await addCart({
				goods_id: topicId,
				cart_num: 1,
				order_type: SPECIAL_ORDER_TYPE,
				product_attr_unique: ''
			})

			if (res && res.code === 200) {
				uni.showToast({
					title: '已加入购物车',
					icon: 'success'
				})
				return
			}

			uni.showToast({
				title: (res && res.msg) || '加入购物车失败',
				icon: 'none'
			})
		} catch (error) {
			uni.showToast({
				title: '请先登录后操作',
				icon: 'none'
			})
		}
	}
	function handleGoBack() {
		uni.navigateBack({
			delta: 1,
			fail() {
				uni.switchTab({
					url: '/pages/index/index'
				})
			}
		})
	}
	function formatMoney(value) {
		const amount = Number(value || 0)
		return amount.toFixed(2)
	}
	function formatCount(value) {
		const count = Number(value || 0)
		if (count >= 10000) {
			return `${(count / 10000).toFixed(count % 10000 === 0 ? 0 : 1).replace(/\.0$/, '')}w`
		}
		if (count >= 1000) {
			return `${(count / 1000).toFixed(count % 1000 === 0 ? 0 : 1).replace(/\.0$/, '')}k`
		}
		return String(count)
	}
	function normalizePriceInput(value) {
		const normalized = String(value || '').trim()
		if (!normalized) {
			return null
		}

		const parsed = Number(normalized)
		if (!Number.isFinite(parsed)) {
			return null
		}

		return parsed < 0 ? 0 : parsed
	}
	function getTopicInitial(title = '') {
		const normalized = String(title || '').trim()
		return normalized ? normalized.slice(0, 1) : '专'
	}
	function syncActiveCategorySelection() {
		const categoryId = Number(activeCategoryId.value || 0)
		if (categoryId <= 0) {
			activeRootCategoryId.value = 0
			return
		}

		const branch = getCategoryBranch(categoryId)
		if (branch.root) {
			const rootId = Number(branch.root.id || 0)
			activeRootCategoryId.value = rootId
			appliedFilters.value.rootCategoryId = rootId
			appliedFilters.value.categoryId = categoryId
			return
		}

		const root = rootCategoryOptions.value.find((item) => Number(item.id || 0) === categoryId)
		activeRootCategoryId.value = root ? Number(root.id || 0) : 0
		appliedFilters.value.rootCategoryId = activeRootCategoryId.value
		appliedFilters.value.categoryId = activeRootCategoryId.value > 0 ? categoryId : 0
	}
	function getCategoryBranch(categoryId) {
		const targetId = Number(categoryId || 0)
		if (targetId <= 0) {
			return {
				root: null,
				child: null
			}
		}

		const roots = Array.isArray(categoryTree.value) ? categoryTree.value : []
		for (let index = 0; index < roots.length; index += 1) {
			const root = roots[index]
			if (Number(root.id || 0) === targetId) {
				return {
					root,
					child: null
				}
			}

			const children = Array.isArray(root.children) ? root.children : []
			const child = children.find((item) => Number(item.id || 0) === targetId)
			if (child) {
				return {
					root,
					child
				}
			}
		}

		return {
			root: null,
			child: null
		}
	}
	function getCategorySelectionLabel(categoryId) {
		const branch = getCategoryBranch(categoryId)
		if (branch.child && branch.root) {
			const rootName = String(branch.root.name || '').trim()
			const childName = String(branch.child.name || '').trim()
			if (rootName && childName) {
				return `${rootName} / ${childName}`
			}
			return childName || rootName
		}

		if (branch.root) {
			return String(branch.root.name || '').trim()
		}

		return ''
	}
</script>

<style lang="scss" scoped>
	.topic-list-page {
		min-height: 100vh;
		background: #f8f8f8;

		&__header {
			position: fixed;
			top: 0;
			left: 0;
			right: 0;
			z-index: 60;
			background: #ffffff;
			border-bottom: 1rpx solid rgba(15, 23, 42, 0.06);
		}

		&__scroll {
			height: 100vh;
		}

		&__body {
			padding: 244rpx 24rpx calc(48rpx + env(safe-area-inset-bottom));
		}
	}

	.topic-topbar {
		display: flex;
		align-items: center;
		padding: 14rpx 24rpx 12rpx;

		&__back {
			display: flex;
			align-items: center;
			justify-content: center;
			width: 56rpx;
			height: 56rpx;
			flex-shrink: 0;
		}

		&__back-icon {
			font-size: 32rpx;
			line-height: 1;
			color: var(--text-title, #1a1c1c);
		}

		&__search {
			display: flex;
			align-items: center;
			flex: 1;
			min-width: 0;
			height: 68rpx;
			margin-left: 16rpx;
			padding: 0 24rpx;
			border-radius: 999rpx;
			background: #f3f3f5;

			&--filled {
				background: #ffffff;
				box-shadow: 0 0 0 2rpx var(--brand-primary, #ea580c);
			}
		}

		&__search-icon {
			flex-shrink: 0;
			font-size: 26rpx;
			color: var(--text-placeholder, #b2b2b7);
		}

		&__search-input {
			flex: 1;
			min-width: 0;
			height: 100%;
			margin-left: 14rpx;
			font-size: 26rpx;
			color: var(--text-title, #1a1c1c);
		}

		&__search-placeholder {
			font-size: 26rpx;
			color: var(--text-placeholder, #b2b2b7);
		}
	}

	.topic-tabs {
		padding: 2rpx 24rpx 14rpx;

		&__scroll {
			white-space: nowrap;
		}

		&__track {
			display: inline-flex;
			align-items: center;
			padding-right: 24rpx;
		}

		&__item {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			height: 56rpx;
			margin-right: 16rpx;
			padding: 0 24rpx;
			border-radius: 14rpx;
			background: #f3f3f5;

			&--active {
				background: var(--brand-primary, #ea580c);

				.topic-tabs__label {
					color: #ffffff;
				}
			}
		}

		&__label {
			font-size: 22rpx;
			line-height: 1;
			font-weight: 600;
			color: var(--text-secondary, #6b7280);
			white-space: nowrap;
		}
	}

	.topic-sort-bar {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 8rpx 24rpx 14rpx;
		border-top: 1rpx solid rgba(15, 23, 42, 0.03);

		&__left {
			display: flex;
			align-items: center;
		}

		&__sort-btn {
			display: inline-flex;
			align-items: center;
		}

		&__sort-label {
			font-size: 22rpx;
			font-weight: 600;
			color: var(--text-title, #1a1c1c);
		}

		&__sort-arrow {
			margin-left: 8rpx;
			font-size: 20rpx;
			line-height: 1;
			color: var(--text-secondary, #6b7280);
		}

		&__filter-tag {
			margin-left: 18rpx;
			display: inline-flex;
			align-items: center;
			max-width: 320rpx;
			height: 44rpx;
			padding: 0 16rpx;
			border-radius: 999rpx;
			background: rgba(233, 89, 15, 0.08);
			font-size: 22rpx;
			color: var(--brand-primary, #ea580c);
			overflow: hidden;

			text {
				white-space: nowrap;
				overflow: hidden;
				text-overflow: ellipsis;
			}
		}

		&__right {
			display: flex;
			align-items: center;
		}

		&__icon-btn {
			display: flex;
			align-items: center;
			justify-content: center;
			width: 56rpx;
			height: 56rpx;
			margin-left: 12rpx;
			border-radius: 12rpx;

			&--active {
				background: #f3f3f5;
			}
		}

		&__icon {
			font-size: 28rpx;
			color: var(--text-secondary, #6b7280);
		}
	}

	.topic-loading,
	.topic-empty {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		min-height: 420rpx;
		padding: 72rpx 32rpx;
		text-align: center;
	}

	.topic-loading {
		&__text {
			margin-top: 18rpx;
			font-size: 24rpx;
			color: var(--text-secondary, #6b7280);
		}
	}



	.topic-grid {
		display: flex;
		flex-wrap: wrap;
		margin: 0 -9rpx;

		&__item {
			width: 50%;
			padding: 0 9rpx;
			box-sizing: border-box;
			margin-bottom: 18rpx;
		}
	}

	.topic-card {
		border-radius: 14rpx;
		background: #ffffff;
		overflow: hidden;
		box-shadow: 0 4rpx 12rpx rgba(15, 23, 42, 0.03);

		&__cover {
			position: relative;
			height: 186rpx;
			background: linear-gradient(180deg, #e4e4e7, #cfcfd4);
			overflow: hidden;
		}

		&__cover-img,
		&__cover-fallback {
			width: 100%;
			height: 100%;
		}

		&__cover-fallback {
			display: flex;
			align-items: center;
			justify-content: center;
			font-size: 48rpx;
			font-weight: 700;
			color: #ffffff;
			background: var(--theme-gradient, linear-gradient(135deg, #ea580c 0%, #fb923c 100%));
		}

		&__tag {
			position: absolute;
			top: 12rpx;
			right: 12rpx;
			z-index: 2;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			min-width: 58rpx;
			height: 34rpx;
			padding: 0 10rpx;
			border-radius: 8rpx;
			font-size: 18rpx;
			font-weight: 700;
			line-height: 1;
			color: #ffffff;
		}

		&__body {
			display: flex;
			flex-direction: column;
			min-height: 176rpx;
			padding: 16rpx 14rpx 14rpx;
			box-sizing: border-box;
		}

		&__title {
			display: -webkit-box;
			font-size: 26rpx;
			line-height: 1.4;
			font-weight: 700;
			color: var(--text-title, #1a1c1c);
			overflow: hidden;
			line-clamp: 2;
			-webkit-line-clamp: 2;
			-webkit-box-orient: vertical;
		}

		&__desc {
			display: -webkit-box;
			min-height: 60rpx;
			margin-top: 8rpx;
			font-size: 20rpx;
			line-height: 1.5;
			color: var(--text-secondary, #6b7280);
			overflow: hidden;
			line-clamp: 2;
			-webkit-line-clamp: 2;
			-webkit-box-orient: vertical;
		}

		&__price-row {
			display: flex;
			align-items: center;
			justify-content: space-between;
			margin-top: 12rpx;
		}

		&__price-group {
			display: flex;
			align-items: baseline;
			min-width: 0;
		}

		&__price {
			font-size: 32rpx;
			line-height: 1;
			font-weight: 700;
			color: var(--brand-primary, #ea580c);

			&--free {
				color: #16a34a;
			}
		}

		&__original-price {
			margin-left: 10rpx;
			font-size: 20rpx;
			color: var(--text-placeholder, #b2b2b7);
			text-decoration: line-through;
		}

		&__meta {
			display: flex;
			align-items: center;
			justify-content: space-between;
			margin-top: auto;
			padding-top: 10rpx;
		}

		&__meta-text {
			font-size: 18rpx;
			color: var(--text-placeholder, #9ca3af);
		}
	}

	.topic-feature-card {
		margin-bottom: 24rpx;
		border-radius: 18rpx;
		background: #ffffff;
		overflow: hidden;
		box-shadow: 0 8rpx 24rpx rgba(15, 23, 42, 0.04);

		&__cover {
			position: relative;
			height: 340rpx;
			background: linear-gradient(135deg, #e6e6ea, #cacad0);
			overflow: hidden;
		}

		&__image,
		&__cover-fallback {
			width: 100%;
			height: 100%;
		}

		&__cover-fallback {
			display: flex;
			align-items: center;
			justify-content: center;
			font-size: 76rpx;
			font-weight: 700;
			color: #ffffff;
			background: var(--theme-gradient, linear-gradient(135deg, #ea580c 0%, #fb923c 100%));
		}

		&__body {
			padding: 24rpx 24rpx 20rpx;
		}

		&__summary {
			display: flex;
			align-items: flex-start;
			justify-content: space-between;
		}

		&__title {
			flex: 1;
			min-width: 0;
			padding-right: 24rpx;
			font-size: 31rpx;
			line-height: 1.45;
			font-weight: 700;
			color: var(--text-title, #1a1c1c);
		}

		&__price-block {
			flex-shrink: 0;
			padding: 8rpx 18rpx;
			border-radius: 999rpx;
			background: rgba(233, 89, 15, 0.08);
		}

		&__price {
			font-size: 34rpx;
			line-height: 1;
			font-weight: 700;
			color: var(--brand-primary, #ea580c);

			&--free {
				color: #16a34a;
			}
		}

		&__desc {
			display: -webkit-box;
			margin-top: 16rpx;
			font-size: 24rpx;
			line-height: 1.7;
			color: var(--text-secondary, #6b7280);
			overflow: hidden;
			line-clamp: 2;
			-webkit-line-clamp: 2;
			-webkit-box-orient: vertical;
		}

		&__meta {
			display: flex;
			flex-wrap: wrap;
			margin-top: 18rpx;
			padding-top: 18rpx;
			border-top: 1rpx solid rgba(15, 23, 42, 0.05);
		}

		&__meta-item {
			display: inline-flex;
			align-items: center;
			margin-right: 24rpx;
			margin-bottom: 12rpx;
			font-size: 18rpx;
			color: #8a8a93;

			.iconfont {
				margin-right: 8rpx;
				font-size: 20rpx;
			}
		}
	}

	.topic-badge,
	.topic-card__tag {
		&.topic-badge--hot {
			background: linear-gradient(135deg, #ff7a18, #ff4d00);
		}

		&.topic-badge--free {
			background: linear-gradient(135deg, #23c16b, #16a34a);
		}
	}

	.topic-list-page {
		background: var(--surface-page, #f8f8f8);

		&__header {
			background: var(--surface-card, #ffffff);
			border-bottom: 1rpx solid rgba(15, 23, 42, 0.05);
		}

		&__body {
			padding: 320rpx 26rpx calc(48rpx + env(safe-area-inset-bottom));
		}
	}

	.topic-topbar {
		height: 114rpx;
		padding: 20rpx 24rpx 18rpx;
		box-sizing: border-box;

		&__back {
			width: 56rpx;
			height: 76rpx;
		}

		&__back-icon {
			font-size: 36rpx;
			color: var(--text-title, #333333);
		}

		&__search {
			height: 76rpx;
			margin-left: 16rpx;
			padding: 0 24rpx;
			border-radius: 20rpx;
			background: #f0f1f4;

			&--filled {
				background: var(--surface-card, #ffffff);
				box-shadow: 0 0 0 2rpx var(--brand-primary, #ea580c);
			}
		}

		&__search-icon {
			font-size: 30rpx;
			color: var(--text-secondary, #999999);
		}

		&__search-input,
		&__search-placeholder {
			font-size: 26rpx;
		}

		&__search-input {
			margin-left: 16rpx;
			color: var(--text-title, #333333);
		}
	}

	.topic-tabs {
		padding: 0 32rpx 28rpx;

		&__track {
			padding-right: 32rpx;
		}

		&__item {
			position: relative;
			height: 64rpx;
			margin-right: 18rpx;
			padding: 0 24rpx;
			border: 2rpx solid var(--border-default, #e5e7eb);
			border-radius: 32rpx;
			background: var(--surface-card, #ffffff);
			box-sizing: border-box;

			&--active {
				border-color: var(--brand-primary, #ea580c);
				background: var(--brand-primary, #ea580c);
				box-shadow: 0 8rpx 18rpx var(--brand-shadow, rgba(234, 88, 12, 0.18));

				.topic-tabs__label {
					color: var(--text-inverse, #ffffff);
				}
			}
		}

		&__label {
			font-size: 28rpx;
			line-height: 64rpx;
			font-weight: 700;
			color: var(--text-regular, #333333);
		}
	}

	.topic-sort-bar {
		height: 88rpx;
		padding: 0 32rpx;
		box-sizing: border-box;

		&__sort-label {
			font-size: 28rpx;
			line-height: 40rpx;
			color: var(--text-title, #333333);
		}

		&__sort-arrow {
			margin-left: 10rpx;
			font-size: 22rpx;
			color: var(--text-regular, #333333);
		}

		&__filter-tag {
			max-width: 390rpx;
			height: 56rpx;
			margin-left: 22rpx;
			padding: 0 26rpx;
			font-size: 26rpx;
			font-weight: 700;
			color: var(--brand-primary, #ea580c);
			background: var(--brand-primary-pale, #fff7ed);
		}

		&__icon-btn {
			width: 56rpx;
			height: 56rpx;
			margin-left: 24rpx;
			border-radius: 50%;

			&--active {
				background: var(--brand-primary-pale, #fff7ed);
			}
		}

		&__icon {
			font-size: 40rpx;
			color: var(--text-title, #333333);
		}
	}

	.topic-feature-card,
	.topic-card {
		border-radius: 32rpx;
		background: var(--surface-card, #ffffff);
		box-shadow: 0 8rpx 32rpx rgba(0, 0, 0, 0.06);
		box-sizing: border-box;
	}

	.topic-feature-card {
		margin-bottom: 24rpx;
		padding: 16rpx;
		overflow: visible;

		&__cover {
			height: 360rpx;
			border-radius: 32rpx;
		}

		&__body {
			padding: 26rpx 8rpx 4rpx;
		}

		&__title {
			display: block;
			height: 48rpx;
			padding-right: 0;
			font-size: 36rpx;
			line-height: 48rpx;
			font-weight: 800;
			color: var(--text-title, #333333);
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}

		&__desc {
			display: -webkit-box;
			height: 80rpx;
			margin-top: 16rpx;
			font-size: 28rpx;
			line-height: 40rpx;
			color: var(--text-secondary, #999999);
		}

		&__price-row {
			display: flex;
			align-items: center;
			justify-content: space-between;
			margin-top: 24rpx;
		}

		&__price-group {
			display: flex;
			align-items: baseline;
			min-width: 0;
		}

		&__price {
			flex-shrink: 0;
			font-size: 48rpx;
			line-height: 58rpx;
			font-weight: 900;
			color: var(--brand-primary, #ea580c);

			&--free {
				color: var(--text-success, #22c55e);
			}
		}

		&__original-price {
			display: flex;
			align-items: center;
			flex-shrink: 0;
			margin-left: 16rpx;
			font-size: 24rpx;
			line-height: 34rpx;
			color: var(--text-placeholder, #a1a1aa);
			text-decoration: line-through;
			white-space: nowrap;

			&--vip {
				font-weight: 700;
				color: var(--brand-primary, #ea580c);
				text-decoration: none;
			}
		}

		&__meta {
			display: flex;
			justify-content: space-between;
			margin-top: 26rpx;
			padding-top: 0;
			border-top: 0;
		}

		&__meta-item {
			margin-right: 0;
			margin-bottom: 0;
			font-size: 24rpx;
			line-height: 32rpx;
			color: var(--text-secondary, #999999);

			.iconfont {
				margin-right: 12rpx;
				font-size: 32rpx;
			}
		}
	}

	.topic-grid {
		margin: 0 -6rpx;

		&__item {
			width: 100%;
			padding: 0 6rpx;
			margin-bottom: 24rpx;
			box-sizing: border-box;
		}
	}

	.topic-card {
		display: flex;
		align-items: stretch;
		min-height: 0;
		padding: 12rpx;
		overflow: visible;

		&__cover {
			flex-shrink: 0;
			width: 220rpx;
			height: 260rpx;
			border-radius: 24rpx;
		}

		&__body {
			flex: 1;
			min-width: 0;
			min-height: 0;
			padding: 2rpx 4rpx 2rpx 18rpx;
		}

		&__title {
			display: block;
			height: 40rpx;
			font-size: 28rpx;
			line-height: 40rpx;
			font-weight: 800;
			color: var(--text-title, #333333);
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}

		&__desc {
			display: -webkit-box;
			height: 32rpx;
			min-height: 0;
			margin-top: 8rpx;
			font-size: 24rpx;
			line-height: 32rpx;
			color: var(--text-secondary, #999999);
			-webkit-line-clamp: 1;
		}

		&__price-row {
			display: flex;
			align-items: center;
			justify-content: space-between;
			margin-top: 12rpx;
		}

		&__price-group {
			display: flex;
			align-items: baseline;
			min-width: 0;
			overflow: hidden;
		}

		&__price {
			flex-shrink: 0;
			font-size: 34rpx;
			line-height: 42rpx;
			font-weight: 900;
			color: var(--brand-primary, #ea580c);

			&--free {
				color: var(--text-success, #22c55e);
			}
		}

		&__original-price {
			display: flex;
			align-items: center;
			flex-shrink: 0;
			margin-left: 12rpx;
			font-size: 22rpx;
			line-height: 30rpx;
			color: var(--text-placeholder, #a1a1aa);
			text-decoration: line-through;
			white-space: nowrap;

			&--vip {
				font-weight: 700;
				color: var(--brand-primary, #ea580c);
				text-decoration: none;
			}
		}

		&__meta {
			margin-top: 12rpx;
			padding-top: 0;
		}

		&__meta-item {
			display: flex;
			align-items: center;
			min-width: 0;
			font-size: 20rpx;
			line-height: 28rpx;
			color: var(--text-secondary, #999999);

			.iconfont {
				flex-shrink: 0;
				margin-right: 6rpx;
				font-size: 26rpx;
			}
		}
	}

	.topic-cart-button {
		position: absolute;
		top: 22rpx;
		right: 22rpx;
		z-index: 3;
		display: flex;
		align-items: center;
		justify-content: center;
		width: 64rpx;
		height: 64rpx;
		border-radius: 50%;
		color: var(--brand-primary, #ea580c);
		background: var(--surface-card, #ffffff);
		box-shadow: 0 8rpx 20rpx rgba(0, 0, 0, 0.12);

		.iconfont {
			font-size: 38rpx;
			line-height: 1;
		}

		&--small {
			top: 12rpx;
			right: 12rpx;
			width: 56rpx;
			height: 56rpx;

			.iconfont {
				font-size: 32rpx;
			}
		}

		&--inline {
			position: static;
			top: auto;
			right: auto;
			flex-shrink: 0;
			margin-left: 18rpx;
			box-shadow: 0 8rpx 18rpx var(--brand-focus-shadow, rgba(234, 88, 12, 0.16));
		}

		&--hover {
			opacity: 0.86;
		}
	}

	.topic-tag-row {
		display: flex;
		align-items: center;
		min-height: 44rpx;
		margin-top: 18rpx;
		overflow: hidden;

		&--small {
			min-height: 36rpx;
			margin-top: 14rpx;
		}
	}

	.vip-price-icon {
		display: block;
		flex-shrink: 0;
		width: 48rpx;
		height: 26rpx;
		margin-right: 8rpx;

		&--small {
			width: $vip-price-icon-width;
			height: $vip-price-icon-height;
			margin-right: $vip-price-icon-margin;
		}
	}

	.topic-badge,
	.topic-card__tag {
		left: 16rpx;
		top: 16rpx;
		right: auto;
		min-width: 64rpx;
		height: 38rpx;
		padding: 0 12rpx;
		border-radius: 8rpx;
		font-size: 20rpx;
		line-height: 38rpx;
	}

	.load-more {
		display: flex;
		align-items: center;
		justify-content: center;
		min-height: 80rpx;

		&__text {
			font-size: 22rpx;
			color: var(--text-placeholder, #9ca3af);
		}
	}

	.topic-filter-mask {
		position: fixed;
		inset: 0;
		z-index: 79;
		background: rgba(0, 0, 0, 0);
		pointer-events: none;
		transition: background 0.24s ease;

		&--visible {
			background: rgba(0, 0, 0, 0.4);
			pointer-events: auto;
		}
	}

	.topic-filter-drawer {
		position: fixed;
		top: 0;
		right: 0;
		bottom: 0;
		z-index: 80;
		display: flex;
		flex-direction: column;
		width: 86%;
		max-width: 620rpx;
		background: #ffffff;
		transform: translateX(100%);
		transition: transform 0.24s ease;
		box-shadow: -18rpx 0 36rpx rgba(15, 23, 42, 0.14);

		&--visible {
			transform: translateX(0);
		}

		&__header {
			display: flex;
			align-items: center;
			justify-content: space-between;
			height: calc(var(--status-bar-height, 0px) + 92rpx);
			padding: var(--status-bar-height, 0px) 32rpx 0;
			border-bottom: 1rpx solid rgba(15, 23, 42, 0.06);
		}

		&__title {
			font-size: 36rpx;
			font-weight: 700;
			color: var(--text-title, #1a1c1c);
		}

		&__close {
			display: flex;
			align-items: center;
			justify-content: center;
			width: 56rpx;
			height: 56rpx;
			border-radius: 50%;
			color: var(--text-placeholder, #9ca3af);

			.iconfont {
				font-size: 28rpx;
			}
		}

		&__body {
			flex: 1;
			min-height: 0;
			padding: 32rpx;
			box-sizing: border-box;
		}

		&__footer {
			display: flex;
			padding: 24rpx 32rpx calc(24rpx + env(safe-area-inset-bottom));
			border-top: 1rpx solid rgba(15, 23, 42, 0.06);
			background: #ffffff;
		}

		&__button {
			display: flex;
			align-items: center;
			justify-content: center;
			height: 84rpx;
			border-radius: 12rpx;
			font-size: 28rpx;
			font-weight: 700;

			&--ghost {
				flex: 1;
				border: 1rpx solid rgba(15, 23, 42, 0.14);
				color: var(--text-title, #1a1c1c);
			}

			&--primary {
				flex: 2;
				margin-left: 20rpx;
				background: var(--brand-primary, #ea580c);
				color: #ffffff;
			}
		}
	}

	.filter-section {
		&__head {
			margin-bottom: 20rpx;
		}

		&__title {
			font-size: 32rpx;
			font-weight: 700;
			color: var(--text-title, #1a1c1c);
		}

		&--spaced {
			margin-top: 28rpx;
		}
	}

	.filter-option-wrap {
		display: flex;
		flex-wrap: wrap;
		margin: -12rpx 0 0 -12rpx;
	}

	.filter-option-chip {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		min-height: 64rpx;
		margin: 12rpx 0 0 12rpx;
		padding: 0 22rpx;
		border-radius: 999rpx;
		background: #f3f3f5;
		font-size: 24rpx;
		line-height: 1.35;
		color: var(--text-title, #1a1c1c);

		&--active {
			background: var(--brand-primary, #ea580c);
			color: #ffffff;
		}
	}

	.filter-empty-text {
		margin-top: 20rpx;
		font-size: 24rpx;
		line-height: 1.6;
		color: var(--text-placeholder, #9ca3af);
	}

	.filter-price-row {
		display: flex;
		align-items: center;

		&__input {
			flex: 1;
			height: 76rpx;
			padding: 0 22rpx;
			border-radius: 12rpx;
			background: #f7f7f8;
			border: 1rpx solid rgba(15, 23, 42, 0.08);
			font-size: 26rpx;
			color: var(--text-title, #1a1c1c);
		}

		&__placeholder {
			font-size: 24rpx;
			color: var(--text-placeholder, #b2b2b7);
		}

		&__divider {
			width: 18rpx;
			height: 2rpx;
			margin: 0 18rpx;
			background: #d5d5d8;
		}
	}
</style>
