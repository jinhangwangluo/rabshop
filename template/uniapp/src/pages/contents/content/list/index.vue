<template>
	<page-meta :page-style="themePageStyle"></page-meta>
	<view class="topic-list-page content-list-page">
		<view class="topic-list-page__header" :style="miniProgramHeaderStyle">


			<view class="topic-topbar" :style="miniProgramTopbarStyle">
				<view class="topic-topbar__back" @click="handleBack">
					<text class="iconfont icon-back topic-topbar__back-icon"></text>
				</view>

				<view class="topic-topbar__search" :class="{ 'topic-topbar__search--filled': searchKeyword }"
					:style="miniProgramSearchStyle">
					<text class="iconfont icon-search topic-topbar__search-icon"></text>
					<input v-model="searchKeyword" class="topic-topbar__search-input" confirm-type="search"
						placeholder="搜索你感兴趣的内容" placeholder-class="topic-topbar__search-placeholder"
						@confirm="handleKeywordConfirm" />
				</view>
			</view>

			<view class="topic-tabs">
				<scroll-view scroll-x class="topic-tabs__scroll scrollbar-hidden" scroll-with-animation
					:scroll-into-view="categoryTabIntoView" :scroll-left="categoryTabScrollLeft"
					:show-scrollbar="false">
					<view class="topic-tabs__track">
						<view v-for="tab in categoryTabs" :key="tab.id" :id="tab.anchorId" class="topic-tabs__item"
							:class="{ 'topic-tabs__item--active': activeRootCategoryId === tab.id }"
							@click="handleCategoryTabChange(tab.id)">
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
				<view v-if="loading && !contentList.length" class="topic-loading">
					<six-petal-spiral :active="true" :size="96" text="" :color="themePrimaryColor"
						:stroke-width="1.4" :particle-count="36" :trail-span="0.28" />
					<text class="topic-loading__text">内容加载中...</text>
				</view>

				<view v-else-if="errorMessage" class="topic-empty">
					<text class="topic-empty__title">加载失败</text>
					<text class="topic-empty__desc">{{ errorMessage }}</text>
					<view class="topic-empty__button" @click="reloadPage">
						<text>重新加载</text>
					</view>
				</view>

				<view v-else-if="!normalizedContentList.length" class="topic-empty">
					<image class="topic-empty__image" :src="emptyImage" mode="aspectFit"></image>
					<text class="topic-empty__title">暂无内容</text>
					<text class="topic-empty__desc">换个关键词或筛选条件再试试。</text>
					<view class="topic-empty__button" @click="resetAllFilters">
						<text>重置筛选</text>
					</view>
				</view>

				<template v-else>
					<template v-if="displayMode === 'large'">
						<view v-for="content in normalizedContentList" :key="content.renderKey"
							class="topic-feature-card" @click="handleContentClick(content)">
							<view class="topic-feature-card__cover">
								<image v-if="content.cover" class="topic-feature-card__image" :src="content.cover"
									mode="aspectFill" />
								<view v-else class="topic-feature-card__cover-fallback">
									<text>{{ getContentInitial(content.title) }}</text>
								</view>
								<view v-if="content.firstTag" class="content-cover-tag">
									<tag-preview :tag="content.firstTag"  />
								</view>
							</view>
							<view class="topic-feature-card__body">
								<text class="topic-feature-card__title">{{ content.title }}</text>
								<text class="topic-feature-card__desc">{{ content.summary }}</text>
								<view class="topic-feature-card__price-row">
									<view class="topic-feature-card__price-group">
										<text class="topic-feature-card__price"
											:class="{ 'topic-feature-card__price--free': content.isFree }">{{ content.priceText }}</text>
										<text v-if="content.originalPriceText"
											class="topic-feature-card__original-price">{{ content.originalPriceText }}</text>
										<text v-if="content.isPurchased"
											class="topic-feature-card__purchase-status">{{ content.purchaseText }}</text>
									</view>
									<view v-if="content.canCart" class="topic-cart-button topic-cart-button--inline"
										hover-class="topic-cart-button--hover" @click.stop="handleCartClick(content)">
										<text class="iconfont icon-daohanggouwuche"></text>
									</view>
								</view>
								<view v-if="content.memberPriceText || content.savedPriceText" class="topic-deal-row">
									<view v-if="content.memberPriceText" class="topic-deal-row__vip">
										<image class="vip-price-icon" src="/static/images/vip.png" mode="aspectFill" />
										<text class="topic-deal-row__vip-price">{{ content.memberPriceText }}</text>
									</view>
									<text v-if="content.savedPriceText"
										class="topic-deal-row__saved">{{ content.savedPriceText }}</text>
								</view>
								<view class="topic-feature-card__learn">
									<text class="iconfont icon-hotfill"></text>
									<text>{{ content.learnCountText }}</text>
								</view>
								<view class="topic-feature-card__meta">
									<view class="topic-feature-card__meta-item">
										<text class="iconfont icon-liebiao1"></text>
										<text>{{ content.typeLabel }}</text>
									</view>
									<view class="topic-feature-card__meta-item">
										<text class="iconfont icon-liulan"></text>
										<text>{{ content.viewCountText }} 浏览</text>
									</view>
								</view>
							</view>
						</view>
					</template>

					<template v-else>
						<view class="topic-grid">
							<view v-for="content in normalizedContentList" :key="content.renderKey"
								class="topic-grid__item">
								<view class="topic-card" @click="handleContentClick(content)">
									<view class="topic-card__cover">
										<image v-if="content.cover" class="topic-card__cover-img" :src="content.cover"
											mode="aspectFill" />
										<view v-else class="topic-card__cover-fallback">
											<text>{{ getContentInitial(content.title) }}</text>
										</view>
										<view v-if="content.firstTag"
											class="content-cover-tag content-cover-tag--small">
											<tag-preview :tag="content.firstTag"  />
										</view>
										<view v-if="content.canCart" class="topic-cart-button topic-cart-button--small"
											hover-class="topic-cart-button--hover"
											@click.stop="handleCartClick(content)">
											<text class="iconfont icon-daohanggouwuche"></text>
										</view>
									</view>
									<view class="topic-card__body">
										<view class="topic-card__price-row">
											<view class="topic-card__price-group">
												<text v-if="content.showPurchaseStatus" class="topic-card__purchase-status">
													{{ content.purchaseText }}
												</text>
												<text v-else class="topic-card__price"
													:class="{ 'topic-card__price--free': content.isFree }">{{ content.priceText }}</text>
												<view v-if="!content.showPurchaseStatus && content.memberPriceText"
													class="topic-card__original-price topic-card__original-price--vip">
													<image class="vip-price-icon vip-price-icon--small"
														src="/static/images/vip.png" mode="aspectFill" />
													<text>{{ content.memberPriceText }}</text>
												</view>
												<text v-else-if="!content.showPurchaseStatus && content.originalPriceText"
													class="topic-card__original-price">{{ content.originalPriceText }}</text>
											</view>
										</view>
										<text class="topic-card__title">{{ content.title }}</text>
										<text class="topic-card__desc">{{ content.summary }}</text>
										<view class="topic-card__meta">
											<view class="topic-card__meta-item">
												<text class="iconfont icon-hotfill"></text>
												<text>{{ content.learnCountText }}</text>
											</view>
										</view>
									</view>
								</view>
							</view>
						</view>
					</template>
				</template>

				<view v-if="normalizedContentList.length" class="load-more">
					<text class="load-more__text">{{ loadMoreText }}</text>
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
							<text>全部</text>
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
							<text>{{ draftRootCategoryName }}</text>
						</view>
						<view v-for="child in draftChildCategories" :key="child.id" class="filter-option-chip"
							:class="{ 'filter-option-chip--active': draftFilters.categoryId === child.id }"
							@click="selectDraftChildCategory(child.id)">
							<text>{{ child.name }}</text>
						</view>
					</view>
					<view v-else class="filter-empty-text">
						<text>当前分类暂无二级分类</text>
					</view>
				</view>

				<view class="filter-section filter-section--spaced">
					<view class="filter-section__head">
						<text class="filter-section__title">内容类型</text>
					</view>
					<view class="filter-option-wrap">
						<view class="filter-option-chip"
							:class="{ 'filter-option-chip--active': draftFilters.contentType === 0 }"
							@click="selectDraftContentType(0)">
							<text>全部类型</text>
						</view>
						<view v-for="item in contentTypeOptions" :key="item.value" class="filter-option-chip"
							:class="{ 'filter-option-chip--active': draftFilters.contentType === item.value }"
							@click="selectDraftContentType(item.value)">
							<text>{{ item.label }}</text>
						</view>
					</view>
				</view>

				<view class="filter-section filter-section--spaced">
					<view class="filter-section__head">
						<text class="filter-section__title">金额区间 (元)</text>
					</view>
					<view class="filter-price-row">
						<input v-model="draftFilters.minPrice" class="filter-price-row__input" type="digit"
							placeholder="最低价" placeholder-class="filter-price-row__placeholder" />
						<view class="filter-price-row__divider"></view>
						<input v-model="draftFilters.maxPrice" class="filter-price-row__input" type="digit"
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
		getCurrentInstance,
		nextTick,
		ref
	} from 'vue'
	import {
		onLoad
	} from '@dcloudio/uni-app'
	import {
		addCart
	} from '@/api/cart'
	import {
		getContentCategoryTree,
		getContentList
	} from '@/api/content'
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

	const ORDER_TYPE_CONTENT = 1
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
		name: '全部',
		anchorId: 'content-list-category-0'
	}

	const CONTENT_TYPE_OPTIONS = [{
			label: '图文',
			value: 1
		},
		{
			label: '文字',
			value: 2
		},
		{
			label: '小说',
			value: 3
		},
		{
			label: '图片',
			value: 4
		},
		{
			label: '音频',
			value: 5
		},
		{
			label: '视频',
			value: 6
		}
	]

	const CONTENT_TYPE_META = {
		1: {
			label: '图文'
		},
		2: {
			label: '文字'
		},
		3: {
			label: '小说'
		},
		4: {
			label: '图片'
		},
		5: {
			label: '音频'
		},
		6: {
			label: '视频'
		}
	}

	const SORT_OPTIONS = [{
			key: 'default',
			label: '综合排序'
		},
		{
			key: 'hot',
			label: '热门优先'
		},
		{
			key: 'view',
			label: '浏览优先'
		},
		{
			key: 'price_asc',
			label: '价格从低到高'
		},
		{
			key: 'price_desc',
			label: '价格从高到低'
		}
	]

	function createDefaultFilters() {
		return {
			rootCategoryId: 0,
			categoryId: 0,
			contentType: 0,
			minPrice: '',
			maxPrice: '',
			sort: 'default'
		}
	}

	function buildTabAnchorId(prefix, value) {
		return `${prefix}-${String(value || 0).replace(/[^A-Za-z0-9_-]/g, '-')}`
	}

	defineOptions({
		name: 'ContentListPage'
	})

	const categoryTabs = ref([ALL_CATEGORY_TAB])
	const contentTypeOptions = ref(CONTENT_TYPE_OPTIONS)
	const activeContentType = ref(0)
	const categoryTabIntoView = ref('')
	const categoryTabScrollLeft = ref(0)
	const loading = ref(false)
	const loadingMore = ref(false)
	const finished = ref(false)
	const errorMessage = ref('')
	const page = ref(1)
	const limit = ref(10)
	const total = ref(0)
	const drawerVisible = ref(false)
	const displayMode = ref('compact')
	const searchKeyword = ref('')
	const merchantId = ref(0)
	const activeRootCategoryId = ref(0)
	const activeCategoryId = ref(0)
	const categoryTree = ref([])
	const emptyImage = ref(resolveAssetUrl('/static/images/empty/special-empty.png'))
	const contentList = ref([])
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
		const labels = []
		const categoryLabel = getCategorySelectionLabel(activeCategoryId.value)
		const contentTypeLabel = getContentTypeLabel(activeContentType.value)
		if (categoryLabel) {
			labels.push(`分类：${categoryLabel}`)
		}
		if (contentTypeLabel) {
			labels.push(contentTypeLabel)
		}
		if (hasPriceFilter.value) {
			labels.push('价格筛选')
		}
		return labels.join(' · ')
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
	const normalizedContentList = computed(() => {
		return contentList.value
			.map((item, index) => normalizeContentItem(item, index))
			.filter((item) => item.id > 0)
	})
	const loadMoreText = computed(() => {
		if (loadingMore.value) {
			return '加载中...'
		}
		return finished.value ? '没有更多内容了' : '上拉加载更多'
	})

	onLoad((options = {}) => {
		syncMiniProgramNavMetrics()
		initPageOptions(options)
		loadPageData()
	})

	function syncMiniProgramNavMetrics() {
		// #ifdef MP-WEIXIN
		miniProgramNavMetrics.value = getMiniProgramSearchNavMetrics(MINI_PROGRAM_HEADER_FALLBACK)
		// #endif
	}
	function initPageOptions(options = {}) {
		const layout = String(options.layout || '').trim()
		const keyword = decodeURIComponent(options.keyword || '').trim()
		const categoryId = Number(options.category_id || options.categoryId || 0)
		const contentType = normalizeContentType(options.content_type || options.contentType || 0)
		const merchantIdValue = Math.max(0, Number(options.merchant_id || options.merchantId || 0))

		if (layout === 'large') {
			displayMode.value = 'large'
		}
		merchantId.value = merchantIdValue
		if (keyword) {
			searchKeyword.value = keyword
		}
		if (categoryId > 0) {
			activeCategoryId.value = categoryId
			appliedFilters.value.categoryId = categoryId
		}
		if (contentType > 0) {
			activeContentType.value = contentType
			appliedFilters.value.contentType = contentType
		}
	}
	async function loadPageData() {
		await Promise.all([
			fetchCategoryTabs(),
			fetchContentList(true)
		])
	}
	async function fetchCategoryTabs() {
		try {
			const res = await getContentCategoryTree({}, {
				showError: false
			})
			const list = Array.isArray(res.data) ? res.data : []
			categoryTree.value = list
			const tabs = list
				.map((item) => {
					const id = Number(item.id || 0)
					return {
						id,
						name: String(item.name || item.label || '').trim(),
						anchorId: buildTabAnchorId('content-list-category', id)
					}
				})
				.filter((item) => item.id > 0 && item.name)

			categoryTabs.value = [ALL_CATEGORY_TAB].concat(tabs)
			syncActiveCategorySelection()
		} catch (error) {
			console.error('[content-list] fetchCategoryTabs failed', error)
			categoryTree.value = []
			categoryTabs.value = [ALL_CATEGORY_TAB]
			activeRootCategoryId.value = 0
		}
	}
	async function fetchContentList(isRefresh = false) {
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
		errorMessage.value = ''

		try {
			const res = await getContentList(buildListParams(requestPage), {
				showError: false
			})
			if (!res || res.code !== 200 || !res.data) {
				throw new Error((res && res.msg) || '内容加载失败')
			}

			const data = res.data || {}
			const list = Array.isArray(data.list) ? data.list.filter((item) => Number(item.id || 0) > 0) : []
			contentList.value = requestPage === 1 ? list : contentList.value.concat(list)
			total.value = Number(data.total || 0)
			finished.value = data.has_more !== undefined ? !Boolean(data.has_more) : list.length < limit.value
			page.value = finished.value ? requestPage : requestPage + 1
		} catch (error) {
			console.error('[content-list] fetchContentList failed', error)
			if (requestPage === 1) {
				contentList.value = []
				total.value = 0
				finished.value = true
				errorMessage.value = '内容加载失败'
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
			merchant_id: merchantId.value || '',
			category_id: activeCategoryId.value || '',
			content_type: activeContentType.value || '',
			min_price: normalizePriceInput(appliedFilters.value.minPrice),
			max_price: normalizePriceInput(appliedFilters.value.maxPrice),
			sort: appliedFilters.value.sort || 'default'
		}
	}
	function normalizeContentItem(item = {}, index = 0) {
		const id = Number(item.id || item.content_id || item.article_id || 0)
		const type = Number(item.content_type || 0)
		const meta = CONTENT_TYPE_META[type] || {
			label: item.content_type_text || item.type_text || '内容'
		}
		const price = Number(item.price || 0)
		const originalPrice = Number(item.original_price || 0)
		const memberPrice = Number(item.member_price || item.vip_price || item.memberPrice || item.vipPrice || 0)
		const memberPriceText = String(item.member_price_text || '').trim()
		const memberPriceAvailable = item.member_price_available === true ||
			Number(item.member_price_available || 0) === 1 || memberPrice > 0 || Boolean(memberPriceText)
		const hasMemberPrice = memberPrice > 0 || Boolean(memberPriceText)
		const isPurchased = [item.is_purchased, item.has_buy, item.is_bought].some((value) =>
			value === true || Number(value || 0) === 1
		)
		const isFree = price <= 0
		const showPurchaseStatus = isPurchased && !isFree
		const savedPrice = !isFree && originalPrice > price ? originalPrice - price : 0
		const title = String(item.title || item.name || '内容标题').trim()
		const tagList = normalizeDisplayTags(item.tag_list, 3)
		const learnCount = Number(item.learn_count || item.study_count || 0)
		const learnCountText = item.learn_count_text || `${formatCount(learnCount)}人已学`
		const priceText = isFree ? '免费' : `¥${formatMoney(price)}`
		const originalPriceText = !isFree && originalPrice > price ?
			`¥${formatMoney(originalPrice)}` : ''

		return {
			...item,
			renderKey: `${id || 'content'}-${index}-${page.value}`,
			id,
			title,
			cover: String(item.cover || item.image || '').trim(),
			summary: String(item.description || item.desc || '').trim() || '精彩内容持续更新中',
			type,
			typeLabel: item.content_type_text || item.type_text || meta.label,
			price,
			originalPrice,
			memberPrice,
			isFree,
			isPurchased,
			showPurchaseStatus,
			priceText,
			originalPriceText,
			purchaseText: showPurchaseStatus ? '已购买' : '',
			memberPriceText: !isFree && memberPriceAvailable && hasMemberPrice ?
				(memberPriceText || `¥${formatMoney(memberPrice)}`) : '',
			savedPriceText: savedPrice > 0 ? `已省 ¥${formatMoney(savedPrice)}` : '',
			tagList,
			firstTag: tagList[0] || null,
			viewCountText: formatCount(item.view_count || item.views || 0),
			likeCountText: formatCount(item.like_count || item.likes || 0),
			learnCount,
			learnCountText,
			canCart: !isFree && price > 0 && !isPurchased,
			detailUrl: item.detail_url || buildContentUrl(item)
		}
	}
	function buildContentUrl(item = {}) {
		const id = Number(item.id || item.content_id || item.article_id || 0)
		const type = Number(item.content_type || item.type || 0)
		if (id <= 0) {
			return ''
		}
		if (type === 5) {
			return `/pages/contents/content/aud/index?id=${id}`
		}
		if (type === 6) {
			return `/pages/contents/content/vid/index?id=${id}`
		}
		if (type === 3) {
			return `/pages/contents/content/novel/index?id=${id}`
		}
		return `/pages/contents/content/detail/index?id=${id}`
	}
	function handleKeywordConfirm() {
		searchKeyword.value = String(searchKeyword.value || '').trim()
		uni.hideKeyboard()
		fetchContentList(true)
	}
	function handleCategoryTabChange(categoryId) {
		const nextId = Number(categoryId || 0)
		scrollCategoryTabIntoView(nextId)
		if (nextId === activeRootCategoryId.value && activeCategoryId.value === nextId) {
			return
		}
		activeRootCategoryId.value = nextId > 0 ? nextId : 0
		activeCategoryId.value = nextId
		appliedFilters.value.rootCategoryId = activeRootCategoryId.value
		appliedFilters.value.categoryId = activeCategoryId.value
		fetchContentList(true)
	}
	function scrollCategoryTabIntoView(categoryId) {
		const id = Number(categoryId || 0)
		const index = categoryTabs.value.findIndex((item) => item.id === id)
		scrollHorizontalTabIntoView(buildTabAnchorId('content-list-category', id), '.topic-tabs__scroll',
			categoryTabIntoView, categoryTabScrollLeft, index, 168)
	}
	function scrollHorizontalTabIntoView(anchorId, containerSelector, intoViewRef, scrollLeftRef, index = 0,
		fallbackWidth = 160) {
		if (!anchorId) {
			return
		}

		const estimatedLeft = Math.max(0, (Number(index || 0) - 1) * fallbackWidth)
		intoViewRef.value = ''
		scrollLeftRef.value = estimatedLeft > 0 ? estimatedLeft - 1 : 0

		nextTick(() => {
			setTimeout(() => {
				scrollLeftRef.value = estimatedLeft
				intoViewRef.value = anchorId
				if (typeof uni === 'undefined' || typeof uni.createSelectorQuery !== 'function') {
					return
				}

				const query = uni.createSelectorQuery().in(getCurrentInstance().proxy)
				query.select(containerSelector).fields({
					rect: true,
					size: true,
					scrollOffset: true
				})
				query.select(`#${anchorId}`).boundingClientRect()
				query.exec((res = []) => {
					const scrollRect = res[0] || {}
					const tabRect = res[1] || {}
					const scrollLeft = Number(scrollRect.scrollLeft || 0)
					const scrollWidth = Number(scrollRect.width || 0)
					const tabWidth = Number(tabRect.width || 0)
					const tabLeft = scrollLeft + Number(tabRect.left || 0) - Number(
						scrollRect.left || 0)
					const targetLeft = tabWidth >= scrollWidth ?
						tabLeft :
						tabLeft - ((scrollWidth - tabWidth) / 2)
					if (Number.isFinite(targetLeft)) {
						scrollLeftRef.value = Math.max(0, Math.round(targetLeft))
					}
				})
			}, 30)
		})
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
				fetchContentList(true)
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
			contentType: activeContentType.value,
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
	function selectDraftContentType(contentType) {
		draftFilters.value.contentType = normalizeContentType(contentType)
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
			contentType: normalizeContentType(draftFilters.value.contentType),
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
		activeContentType.value = normalizedDraft.contentType
		drawerVisible.value = false
		scrollCategoryTabIntoView(activeRootCategoryId.value)
		fetchContentList(true)
	}
	function resetAllFilters() {
		searchKeyword.value = ''
		activeRootCategoryId.value = 0
		activeCategoryId.value = 0
		activeContentType.value = 0
		appliedFilters.value = createDefaultFilters()
		draftFilters.value = createDefaultFilters()
		scrollCategoryTabIntoView(0)
		fetchContentList(true)
	}
	function handleLoadMore() {
		if (loading.value || loadingMore.value || finished.value) {
			return
		}
		fetchContentList(false)
	}
	function reloadPage() {
		fetchContentList(true)
	}
	function toggleDisplayMode() {
		displayMode.value = displayMode.value === 'large' ? 'compact' : 'large'
	}
	function handleContentClick(content = {}) {
		const url = content.detailUrl || buildContentUrl(content)
		if (!url) {
			uni.showToast({
				title: '内容暂不可访问',
				icon: 'none'
			})
			return
		}
		uni.navigateTo({
			url
		})
	}
	async function handleCartClick(content = {}) {
		const contentId = Number(content.id || 0)
		if (!contentId) {
			return
		}
		if (content.isFree || Number(content.price || 0) <= 0) {
			handleContentClick(content)
			return
		}

		try {
			const res = await addCart({
				goods_id: contentId,
				cart_num: 1,
				order_type: ORDER_TYPE_CONTENT,
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
	function handleBack() {
		uni.navigateBack({
			delta: 1,
			fail() {
				uni.switchTab({
					url: '/pages/category/index'
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
	function normalizeContentType(value) {
		const contentType = Number(value || 0)
		return contentType > 0 && Object.prototype.hasOwnProperty.call(CONTENT_TYPE_META, contentType) ?
			contentType : 0
	}
	function getContentTypeLabel(value) {
		const contentType = normalizeContentType(value)
		const meta = CONTENT_TYPE_META[contentType]
		return meta ? `类型：${meta.label}` : ''
	}
	function getContentInitial(title = '') {
		const normalized = String(title || '').trim()
		return normalized ? normalized.slice(0, 1) : '内'
	}
	function syncActiveCategorySelection() {
		const categoryId = Number(activeCategoryId.value || 0)
		if (categoryId <= 0) {
			activeRootCategoryId.value = 0
			appliedFilters.value.rootCategoryId = 0
			appliedFilters.value.categoryId = 0
			scrollCategoryTabIntoView(0)
			return
		}

		const branch = getCategoryBranch(categoryId)
		if (branch.root) {
			const rootId = Number(branch.root.id || 0)
			activeRootCategoryId.value = rootId
			appliedFilters.value.rootCategoryId = rootId
			appliedFilters.value.categoryId = categoryId
			scrollCategoryTabIntoView(rootId)
			return
		}

		const root = rootCategoryOptions.value.find((item) => Number(item.id || 0) === categoryId)
		activeRootCategoryId.value = root ? Number(root.id || 0) : 0
		appliedFilters.value.rootCategoryId = activeRootCategoryId.value
		appliedFilters.value.categoryId = activeRootCategoryId.value > 0 ? categoryId : 0
		scrollCategoryTabIntoView(activeRootCategoryId.value)
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
		background: var(--surface-page, #f8f8f8);

		&__header {
			position: fixed;
			top: 0;
			left: 0;
			right: 0;
			z-index: 60;
			background: var(--surface-card, #ffffff);
			border-bottom: 1rpx solid rgba(15, 23, 42, 0.05);
		}

		&__scroll {
			height: 100vh;
		}

		&__body {
			padding: 320rpx 26rpx calc(48rpx + env(safe-area-inset-bottom));
		}
	}

	.topic-topbar {
		display: flex;
		align-items: center;
		height: 114rpx;
		padding: 20rpx 24rpx 18rpx;
		box-sizing: border-box;

		&__back {
			display: flex;
			align-items: center;
			justify-content: center;
			width: 56rpx;
			height: 76rpx;
			flex-shrink: 0;
		}

		&__back-icon {
			font-size: 36rpx;
			line-height: 1;
			color: var(--text-title, #333333);
		}

		&__search {
			display: flex;
			align-items: center;
			flex: 1;
			min-width: 0;
			height: 76rpx;
			margin-left: 16rpx;
			padding: 0 24rpx;
			border-radius: 20rpx;
			background: #f0f1f4;
			box-sizing: border-box;

			&--filled {
				background: var(--surface-card, #ffffff);
				box-shadow: 0 0 0 2rpx var(--brand-primary, #ea580c);
			}
		}

		&__search-icon {
			flex-shrink: 0;
			font-size: 30rpx;
			color: var(--text-secondary, #999999);
		}

		&__search-input {
			flex: 1;
			min-width: 0;
			height: 100%;
			margin-left: 16rpx;
			font-size: 26rpx;
			color: var(--text-title, #333333);
		}

		&__search-placeholder {
			font-size: 26rpx;
			color: var(--text-secondary, #999999);
		}
	}

	.topic-tabs {
		padding: 0 32rpx 28rpx;

		&__scroll {
			white-space: nowrap;
		}

		&__track {
			display: inline-flex;
			align-items: center;
			padding-right: 32rpx;
		}

		&__item {
			position: relative;
			display: inline-flex;
			align-items: center;
			justify-content: center;
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
			white-space: nowrap;
		}
	}

	.topic-sort-bar {
		display: flex;
		align-items: center;
		justify-content: space-between;
		height: 88rpx;
		padding: 0 32rpx;
		border-top: 1rpx solid rgba(15, 23, 42, 0.03);
		box-sizing: border-box;

		&__left,
		&__right,
		&__sort-btn,
		&__icon-btn,
		&__filter-tag {
			display: flex;
			align-items: center;
		}

		&__sort-label {
			font-size: 28rpx;
			line-height: 40rpx;
			font-weight: 600;
			color: var(--text-title, #333333);
		}

		&__sort-arrow {
			margin-left: 10rpx;
			font-size: 22rpx;
			line-height: 1;
			color: var(--text-regular, #333333);
		}

		&__filter-tag {
			max-width: 390rpx;
			height: 56rpx;
			margin-left: 22rpx;
			padding: 0 26rpx;
			border-radius: 999rpx;
			font-size: 26rpx;
			font-weight: 700;
			color: var(--brand-primary, #ea580c);
			background: var(--brand-primary-pale, #fff7ed);
			overflow: hidden;

			text {
				white-space: nowrap;
				overflow: hidden;
				text-overflow: ellipsis;
			}
		}

		&__icon-btn {
			justify-content: center;
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

	.topic-loading__text,
	.topic-empty__desc {
		margin-top: 18rpx;
		font-size: 24rpx;
		line-height: 36rpx;
		color: var(--text-secondary, #6b7280);
	}

	.topic-empty__image {
		width: 320rpx;
		height: 240rpx;
		margin-bottom: 24rpx;
	}

	.topic-empty__title {
		font-size: 32rpx;
		line-height: 44rpx;
		font-weight: 700;
		color: var(--text-title, #333333);
	}

	.topic-empty__button {
		display: flex;
		align-items: center;
		justify-content: center;
		height: 64rpx;
		margin-top: 28rpx;
		padding: 0 32rpx;
		border-radius: 999rpx;
		background: var(--brand-primary, #ea580c);
		color: #ffffff;
		font-size: 24rpx;
		font-weight: 700;
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
			position: relative;
			height: 360rpx;
			border-radius: 32rpx;
			background: linear-gradient(135deg, #e6e6ea, #cacad0);
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
			padding: 26rpx 8rpx 4rpx;
		}

		&__title {
			display: block;
			height: 48rpx;
			font-size: 36rpx;
			line-height: 48rpx;
			font-weight: 800;
			color: var(--text-title, #333333);
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}

		&__desc {
			display: block;
			height: 80rpx;
			margin-top: 16rpx;
			font-size: 28rpx;
			line-height: 40rpx;
			color: var(--text-secondary, #999999);
			overflow: hidden;
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
			flex-wrap: nowrap;
			min-width: 0;
		}

		&__price {
			font-size: 48rpx;
			line-height: 58rpx;
			font-weight: 900;
			color: var(--brand-primary, #ea580c);

			&--free {
				color: var(--text-success, #22c55e);
			}
		}

		&__original-price {
			flex-shrink: 0;
			margin-left: 16rpx;
			font-size: 24rpx;
			line-height: 34rpx;
			color: var(--text-placeholder, #a1a1aa);
			text-decoration: line-through;
			white-space: nowrap;
		}

		&__purchase-status {
			flex-shrink: 0;
			margin-left: 16rpx;
			padding: 0 12rpx;
			border-radius: 8rpx;
			font-size: 22rpx;
			line-height: 34rpx;
			color: var(--text-success, #22c55e);
			background: rgba(34, 197, 94, 0.1);
			white-space: nowrap;
		}

		&__meta {
			display: flex;
			justify-content: space-between;
			margin-top: 26rpx;
		}

		&__learn {
			display: flex;
			align-items: center;
			margin-top: 18rpx;
			font-size: 24rpx;
			line-height: 32rpx;
			color: var(--text-secondary, #999999);

			.iconfont {
				margin-right: 8rpx;
				font-size: 28rpx;
				color: var(--brand-primary, #ea580c);
			}
		}

		&__meta-item {
			display: inline-flex;
			align-items: center;
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
		display: flex;
		flex-wrap: wrap;
		margin: 0 -12rpx;

		&__item {
			width: 50%;
			padding: 0 12rpx;
			margin-bottom: 26rpx;
			box-sizing: border-box;
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

	.topic-card {
		display: block;
		overflow: hidden;

		&__cover {
			position: relative;
			width: 100%;
			height: 236rpx;
			border-radius: 32rpx 32rpx 0 0;
			background: linear-gradient(180deg, #e4e4e7, #cfcfd4);
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

		&__body {
			min-width: 0;
			min-height: 278rpx;
			padding: 24rpx 18rpx 20rpx;
			box-sizing: border-box;
		}

		&__title {
			display: block;
			height: 40rpx;
			margin-top: 12rpx;
			font-size: 30rpx;
			line-height: 40rpx;
			font-weight: 800;
			color: var(--text-title, #333333);
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}

		&__desc {
			display: block;
			height: 72rpx;
			margin-top: 10rpx;
			font-size: 24rpx;
			line-height: 36rpx;
			color: var(--text-secondary, #999999);
			overflow: hidden;
		}

		&__price-row {
			display: flex;
			align-items: center;
			justify-content: space-between;
			min-height: 48rpx;
		}

		&__price-group {
			display: flex;
			align-items: baseline;
			flex: 1;
			min-width: 0;
			overflow: hidden;
		}

		&__price {
			flex-shrink: 0;
			font-size: 36rpx;
			line-height: 46rpx;
			font-weight: 900;
			color: var(--brand-primary, #ea580c);

			&--free {
				color: var(--text-success, #22c55e);
			}
		}

		&__original-price {
			flex-shrink: 0;
			margin-left: 8rpx;
			font-size: 22rpx;
			line-height: 32rpx;
			color: var(--text-placeholder, #a1a1aa);
			text-decoration: line-through;
			white-space: nowrap;

			&--vip {
				display: flex;
				align-items: center;
				font-size: 22rpx;
				font-weight: 700;
				color: var(--brand-primary, #ea580c);
				text-decoration: none;
			}
		}

		&__purchase-status {
			flex-shrink: 0;
			min-height: 34rpx;
			padding: 0 12rpx;
			border-radius: 8rpx;
			font-size: 22rpx;
			line-height: 34rpx;
			color: var(--text-success, #22c55e);
			background: rgba(34, 197, 94, 0.1);
		}

		&__meta {
			display: flex;
			align-items: center;
			justify-content: space-between;
			margin-top: 16rpx;
		}

		&__meta-item {
			display: flex;
			align-items: center;
			min-width: 0;
			font-size: 24rpx;
			line-height: 32rpx;
			color: var(--text-secondary, #999999);

			.iconfont {
				flex-shrink: 0;
				margin-right: 8rpx;
				font-size: 26rpx;
				color: var(--brand-primary, #ea580c);
			}
		}
	}

	.content-cover-tag {
		position: absolute;
		top: 18rpx;
		left: 18rpx;
		z-index: 2;
		display: inline-flex;
		align-items: center;
		max-width: 190rpx;
		overflow: hidden;

		&--small {
			top: 16rpx;
			left: 16rpx;
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
			top: auto;
			right: 18rpx;
			bottom: -38rpx;
			width: 78rpx;
			height: 78rpx;
			background: #ffffff;
			box-shadow: 0 10rpx 24rpx rgba(0, 0, 0, 0.12);

			.iconfont {
				font-size: 44rpx;
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

	.topic-deal-row {
		display: flex;
		align-items: center;
		min-height: 50rpx;
		margin-top: 16rpx;

		&__vip,
		&__saved {
			flex-shrink: 0;
			max-width: 280rpx;
			height: 48rpx;
			padding: 0 20rpx;
			border-radius: 10rpx;
			font-size: 28rpx;
			line-height: 48rpx;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}

		&__vip {
			display: flex;
			align-items: center;
			color: var(--brand-primary, #ea580c);
			background: var(--brand-primary-pale, #fff7ed);
		}

		&__vip-price {
			display: block;
			min-width: 0;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}

		&__saved {
			margin-left: 20rpx;
			color: var(--text-danger, #ff3b30);
			background: rgba(255, 59, 48, 0.1);
		}

		&--small {
			min-height: 40rpx;
			margin-top: 12rpx;

			.topic-deal-row__vip,
			.topic-deal-row__saved {
				max-width: 132rpx;
				height: 40rpx;
				padding: 0 12rpx;
				border-radius: 8rpx;
				font-size: 22rpx;
				line-height: 40rpx;
			}

			.topic-deal-row__saved {
				margin-left: 8rpx;
			}
		}
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
		top: 0;
		right: 0;
		bottom: 0;
		left: 0;
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
			box-sizing: border-box;
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
