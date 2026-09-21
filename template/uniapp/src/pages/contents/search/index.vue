<template>
	<page-meta :page-style="themePageStyle"></page-meta>
	<view class="search-page">
		<view class="search-page__header" :class="{ 'is-result': hasSearched }">
			<view class="search-toolbar">
				<view v-if="hasSearched" class="search-toolbar__back" @click="handleBack">
					<text class="iconfont icon-back search-toolbar__back-icon"></text>
				</view>

				<view class="search-toolbar__field" :class="{ 'is-result': hasSearched }">
					<text class="iconfont icon-search search-toolbar__search-icon"></text>
					<input v-model="keyword" class="search-toolbar__input" placeholder="搜索商品、专题、内容、商户"
						placeholder-class="search-toolbar__placeholder" confirm-type="search" @confirm="handleSearch" />
				</view>

				<text v-if='!hasSearched' class="search-toolbar__cancel" @click="handleCancel">取消</text>
			</view>

			<search-tabs v-if="hasSearched" :tabs="resultTabs" :active-value="activeTab"
				@change="handleTabChange"></search-tabs>
		</view>

		<scroll-view scroll-y class="search-page__scroll" :show-scrollbar="false">
			<view class="search-page__body">
				<search-discover-panel v-if="!hasSearched" :history="displaySearchHistory"
					:hot-keywords="displayHotKeywords" :discover-items="discoverItems"
					@clear-history="handleClearHistory" @history-click="handleHistoryClick"
					@hot-click="handleHotKeywordClick" @discover-click="handleResultSelect"></search-discover-panel>

				<search-goods-panel v-else-if="isGoodsTab" :summary-text="currentSummaryText" :items="currentGoodsItems"
					:sort-options="activeSortOptions" :active-sort="activeSortValue" :loading="searchLoading"
					:empty-title="currentGoodsEmptyTitle" :empty-desc="currentGoodsEmptyDesc"
					@sort-change="handleSortChange" @select="handleResultSelect"></search-goods-panel>

				<search-shop-panel v-else-if="activeTab === 'merchant'" :summary-text="currentSummaryText"
					:shops="displayMerchantResults" :sort-options="activeSortOptions" :active-sort="activeSortValue"
					:loading="searchLoading" @sort-change="handleSortChange" @enter="handleShopEnter"
					@select="handleShopSelect"></search-shop-panel>
			</view>
		</scroll-view>
	</view>
</template>

<script setup>
	import { computed, ref } from 'vue'
	import { onLoad, onShow } from '@dcloudio/uni-app'
	import {
		clearLocalSearchHistory,
		clearSearchHistory,
		getLocalSearchHistory,
		getSearchMeta,
		recordSearchKeyword as apiRecordSearchKeyword,
		saveLocalSearchHistory,
		searchContentResources
	} from '@/api/search'
	import {
		isLoggedIn
	} from '@/utils/auth'
	import SearchDiscoverPanel from './components/SearchDiscoverPanel.vue'
	import SearchGoodsPanel from './components/SearchGoodsPanel.vue'
	import SearchShopPanel from './components/SearchShopPanel.vue'
	import SearchTabs from './components/SearchTabs.vue'

	const RESULT_TABS = [{
			label: '全部',
			value: 'all'
		},
		{
			label: '商品',
			value: 'goods'
		},
		{
			label: '专题',
			value: 'special'
		},
		{
			label: '内容',
			value: 'content'
		},
		{
			label: '商户',
			value: 'merchant'
		}
	]

	const RESOURCE_SORT_OPTIONS = [{
			label: '综合',
			value: 'default'
		},
		{
			label: '热度',
			value: 'credit'
		},
		{
			label: '销量',
			value: 'sales'
		}
	]

	const MERCHANT_SORT_OPTIONS = [{
			label: '综合',
			value: 'default'
		},
		{
			label: '粉丝数',
			value: 'fans'
		},
		{
			label: '成交量',
			value: 'sales'
		}
	]

	const NATIVE_SEARCH_KEYWORD = 'function search() { [native code] }'

	function sortResourceList(list = [], sortType = 'default') {
		const source = Array.isArray(list) ? list.slice() : []
		if (sortType === 'sales') {
			return source.sort((left, right) => Number(right.sales || 0) - Number(left.sales || 0))
		}
		if (sortType === 'credit') {
			return source.sort((left, right) => Number(right.credit || 0) - Number(left.credit || 0))
		}
		return source
	}

	function sortMerchantList(list = [], sortType = 'default') {
		const source = Array.isArray(list) ? list.slice() : []
		if (sortType === 'sales') {
			return source.sort((left, right) => Number(right.sales || 0) - Number(left.sales || 0))
		}
		if (sortType === 'fans') {
			return source.sort((left, right) => Number(right.fansValue || 0) - Number(left.fansValue || 0))
		}
		return source
	}

	function normalizeKeyword(value = '') {
		const keyword = String(value || '').trim()
		return keyword === NATIVE_SEARCH_KEYWORD ? '' : keyword
	}

	function normalizeKeywordItem(item = '') {
		if (typeof item === 'string' || typeof item === 'number') {
			return normalizeKeyword(item)
		}
		if (!item || typeof item !== 'object') {
			return ''
		}
		return normalizeKeyword(item.keyword || item.search || item.name || item.title || '')
	}

	function createEmptySearchResults() {
		return {
			all: [],
			goods: [],
			special: [],
			content: [],
			merchant: []
		}
	}

	function createEmptySearchSummary() {
		return {
			all: 0,
			goods: 0,
			special: 0,
			content: 0,
			merchant: 0
		}
	}

	defineOptions({
		name: 'SearchPage'
	})

	const keyword = ref('')
	const lishi = ref([])
	const keyuser = ref([])
	const discoverItems = ref([])
	const searchResults = ref(createEmptySearchResults())
	const searchSummary = ref(createEmptySearchSummary())
	const activeTab = ref('all')
	const sortState = ref({
		all: 'default',
		goods: 'default',
		special: 'default',
		content: 'default',
		merchant: 'default'
	})
	const initialTab = ref('all')
	const autoSearchOnShow = ref(false)
	const hasSearched = ref(false)
	const searchLoading = ref(false)
	const resultTabs = computed(() => {
		return RESULT_TABS
	})
	const displaySearchHistory = computed(() => {
		return Array.isArray(lishi.value) ? lishi.value.slice(0, 8) : []
	})
	const displayHotKeywords = computed(() => {
		const source = Array.isArray(keyuser.value) && keyuser.value.length ? keyuser.value : []
		const groupedKeywords = []
		const groupedIndex = {}

		source.forEach((item, sourceIndex) => {
			const keyword = normalizeKeywordItem(item)
			if (!keyword) {
				return
			}

			const searchCount = Math.max(0, Number(item.search_count || item.searchCount || item.count ||
				0))
			if (groupedIndex[keyword] === undefined) {
				groupedIndex[keyword] = groupedKeywords.length
				groupedKeywords.push({
					keyword,
					searchCount,
					sourceIndex
				})
				return
			}

			groupedKeywords[groupedIndex[keyword]].searchCount += searchCount
		})

		return groupedKeywords
			.sort((left, right) => {
				return Number(right.searchCount || 0) - Number(left.searchCount || 0) ||
					Number(left.sourceIndex || 0) - Number(right.sourceIndex || 0)
			})
			.slice(0, 6)
			.map((item, index) => ({
				keyword: item.keyword,
				search_count: item.searchCount,
				searchCountText: item.searchCount > 1 ? `${item.searchCount}次` : '',
				badgeClassName: index === 0 ? 'is-hot' : index === 1 ? 'is-trend' : index === 2 ?
					'is-new' : ''
			}))
	})
	const isGoodsTab = computed(() => {
		return ['all', 'goods', 'special', 'content'].includes(activeTab.value)
	})
	const activeSortOptions = computed(() => {
		return activeTab.value === 'merchant' ? MERCHANT_SORT_OPTIONS : RESOURCE_SORT_OPTIONS
	})
	const activeSortValue = computed(() => {
		return sortState.value[activeTab.value] || 'default'
	})
	const currentSummaryText = computed(() => {
		if (activeTab.value === 'special') {
			return `找到相关专题 ${searchSummary.value.special} 个`
		}
		if (activeTab.value === 'goods') {
			return `找到相关商品 ${searchSummary.value.goods} 个`
		}
		if (activeTab.value === 'content') {
			return `找到相关内容 ${searchSummary.value.content} 篇`
		}
		if (activeTab.value === 'merchant') {
			return `找到相关商户 ${searchSummary.value.merchant} 个`
		}
		return `找到 ${searchSummary.value.all} 个结果`
	})
	const currentGoodsItems = computed(() => {
		if (activeTab.value === 'goods') {
			return sortResourceList(searchResults.value.goods, sortState.value.goods)
		}
		if (activeTab.value === 'special') {
			return sortResourceList(searchResults.value.special, sortState.value.special)
		}
		if (activeTab.value === 'content') {
			return sortResourceList(searchResults.value.content, sortState.value.content)
		}
		return sortResourceList(searchResults.value.all, sortState.value.all)
	})
	const currentGoodsEmptyTitle = computed(() => {
		if (activeTab.value === 'goods') {
			return '未找到相关商品'
		}
		return activeTab.value === 'special' ? '未找到相关专题' : '未找到相关内容'
	})
	const currentGoodsEmptyDesc = computed(() => {
		return `没有找到“${keyword.value || '当前关键词'}”相关结果，换个关键词试试`
	})
	const displayMerchantResults = computed(() => {
		return sortMerchantList(searchResults.value.merchant, sortState.value.merchant)
	})
	onLoad((options = {}) => {
		const rawKeyword = decodeURIComponent(options.keyword || '').trim()
		const tabValue = normalizeResultTab(options.type || options.tab || '')
		if (tabValue) {
			initialTab.value = tabValue
			activeTab.value = tabValue
			autoSearchOnShow.value = true
		}

		if (rawKeyword) {
			keyword.value = rawKeyword
			autoSearchOnShow.value = true
		}
	})
	onShow(async () => {
		await fetchBaseData()
		if (autoSearchOnShow.value) {
			autoSearchOnShow.value = false
			handleSearch({
				allowEmpty: true,
				targetTab: initialTab.value
			})
		}
	})
	function createEmptyResults() {
		return createEmptySearchResults()
	}
	function createEmptySummary() {
		return createEmptySearchSummary()
	}

	/**
	 * 拉取搜索页默认态所需数据。
	 *
	 * @returns {Promise<void>} 初始化完成后结束。
	 */
	async function fetchBaseData() {
		try {
			const res = await getSearchMeta({
				limit: 8
			}, {
				showError: false
			})
			if (res.code !== 200) {
				throw new Error(res.msg || '搜索数据加载失败')
			}

			const data = res.data || {}
			const serverHistory = normalizeHistoryList(data.history)
			lishi.value = isLoggedIn() ? serverHistory : (serverHistory.length ? serverHistory :
				getLocalSearchHistory())
			keyuser.value = Array.isArray(data.hot_keywords) ? data.hot_keywords : []
			discoverItems.value = normalizeResultList(data.discover)
		} catch (error) {
			lishi.value = getLocalSearchHistory()
			keyuser.value = []
			discoverItems.value = []
		}
	}

	/**
	 * 清空搜索历史。
	 *
	 * @returns {Promise<void>} 清空完成后结束。
	 */
	async function handleClearHistory() {
		uni.showModal({
			title: '提示',
			content: '确认清空全部搜索记录吗？',
			success: async ({
				confirm
			}) => {
				if (!confirm) {
					return
				}

				try {
					await clearSearchHistory({}, {
						showError: false
					})
					clearLocalSearchHistory()
					lishi.value = []
				} catch (error) {
					uni.showToast({
						title: '清空失败，请稍后重试',
						icon: 'none'
					})
				}
			}
		})
	}

	/**
	 * 执行搜索操作。
	 *
	 * @param {Object} options 搜索选项。
	 * @param {boolean} options.allowEmpty 是否允许空关键词搜索。
	 * @param {string} options.targetTab 搜索完成后默认选中的标签。
	 * @returns {Promise<void>} 搜索完成后结束。
	 */
	async function handleSearch(options = {}) {
		const searchKeyword = normalizeKeyword(keyword.value)
		const allowEmpty = Boolean(options.allowEmpty)
		const targetTab = normalizeResultTab(options.targetTab || '')
		if (!searchKeyword && !allowEmpty) {
			uni.showToast({
				title: '请输入搜索内容',
				icon: 'none'
			})
			return
		}

		keyword.value = searchKeyword
		hasSearched.value = true
		searchLoading.value = true
		activeTab.value = targetTab || 'all'
		sortState.value = {
			all: 'default',
			goods: 'default',
			special: 'default',
			content: 'default',
			merchant: 'default'
		}

		try {
			const res = await searchContentResources({
				keyword: searchKeyword,
				type: 'all',
				page: 1,
				limit: 12,
				sort: 'default'
			})
			if (res.code !== 200) {
				throw new Error(res.msg || '搜索失败')
			}

			const data = res.data || {}
			const results = data.results || {}
			searchResults.value = {
				all: normalizeResultList(results.all),
				goods: normalizeResultList(results.goods),
				special: normalizeResultList(results.special),
				content: normalizeResultList(results.content),
				merchant: normalizeResultList(results.merchant)
			}
			searchSummary.value = {
				all: Number(data.summary && data.summary.all) || 0,
				goods: Number(data.summary && data.summary.goods) || 0,
				special: Number(data.summary && data.summary.special) || 0,
				content: Number(data.summary && data.summary.content) || 0,
				merchant: Number(data.summary && data.summary.merchant) || 0
			}
			await recordSearchKeyword(searchKeyword, searchSummary.value.all)
		} catch (error) {
			searchResults.value = createEmptyResults()
			searchSummary.value = createEmptySummary()
			uni.showToast({
				title: '搜索失败，请稍后再试',
				icon: 'none'
			})
		} finally {
			searchLoading.value = false
		}
	}

	/**
	 * 记录搜索关键词。
	 *
	 * @param {string} keyword 搜索关键词。
	 * @param {number} resultCount 结果数量。
	 * @returns {Promise<void>}
	 */
	async function recordSearchKeyword(keyword = '', resultCount = 0) {
		if (!keyword) {
			return
		}

		if (!isLoggedIn()) {
			lishi.value = saveLocalSearchHistory(keyword)
		}

		try {
			const res = await apiRecordSearchKeyword({
				keyword,
				result_count: resultCount
			}, {
				showError: false
			})
			if (res.code === 200) {
				const data = res.data || {}
				const history = normalizeHistoryList(data.history)
				if (isLoggedIn() && history.length) {
					lishi.value = history
				}
				if (Array.isArray(data.hot_keywords)) {
					keyuser.value = data.hot_keywords
				}
			}
		} catch (error) {
			// 记录失败不影响搜索结果展示。
		}
	}

	/**
	 * 处理默认态取消按钮点击。
	 *
	 * @returns {void} 关闭搜索页或回到首页。
	 */
	function handleCancel() {
		if (keyword.value) {
			keyword.value = ''
			return
		}

		handleBack(true)
	}

	/**
	 * 处理搜索页返回动作。
	 *
	 * @param {boolean} forceLeave 是否强制离开当前页面。
	 * @returns {void} 优先恢复默认发现态，否则执行页面返回兜底。
	 */
	function handleBack(forceLeave = false) {
		if (!forceLeave && (hasSearched.value || keyword.value)) {
			keyword.value = ''
			hasSearched.value = false
			activeTab.value = 'all'
			initialTab.value = 'all'
			searchLoading.value = false
			return
		}

		uni.navigateBack({
			fail: () => {
				uni.switchTab({
					url: '/pages/index/index'
				})
			}
		})
	}

	/**
	 * 切换搜索结果标签。
	 *
	 * @param {string} value 标签值。
	 * @returns {void} 更新当前结果页签。
	 */
	function handleTabChange(value) {
		activeTab.value = value
	}

	/**
	 * 归一化外部传入的搜索结果标签。
	 *
	 * @param {string} value 标签值。
	 * @returns {string} 可用标签值，空字符串表示未指定。
	 */
	function normalizeResultTab(value = '') {
		const tab = String(value || '').trim()
		const alias = {
			product: 'goods',
			goods: 'goods',
			shop: 'merchant',
			video: 'content'
		}
		const normalized = alias[tab] || tab
		return RESULT_TABS.some((item) => item.value === normalized) ? normalized : ''
	}

	/**
	 * 切换排序方式。
	 *
	 * @param {string} value 排序值。
	 * @returns {void} 更新当前标签排序状态。
	 */
	function handleSortChange(value) {
		sortState.value[activeTab.value] = value
	}

	/**
	 * 点击历史搜索项后直接搜索。
	 *
	 * @param {string} searchText 历史关键词。
	 * @returns {void} 更新关键词并触发搜索。
	 */
	function handleHistoryClick(searchText = '') {
		keyword.value = normalizeKeyword(searchText)
		handleSearch()
	}

	/**
	 * 点击热门搜索项后直接搜索。
	 *
	 * @param {string} searchText 热门关键词。
	 * @returns {void} 更新关键词并触发搜索。
	 */
	function handleHotKeywordClick(searchText = '') {
		keyword.value = normalizeKeyword(searchText)
		handleSearch()
	}

	/**
	 * 处理结果卡片点击。
	 *
	 * @param {Object} item 当前结果项。
	 * @returns {void} 跳转到对应详情页。
	 */
	function handleResultSelect(item = {}) {
		const targetUrl = String(item.routeUrl || item.route_url || '').trim()
		if (!targetUrl) {
			return
		}
		uni.navigateTo({
			url: targetUrl
		})
	}

	/**
	 * 处理商户卡片点击。
	 *
	 * @param {Object} shop 商户数据。
	 * @returns {void} 跳转到商户页。
	 */
	function handleShopSelect(shop = {}) {
		handleShopEnter(shop)
	}

	/**
	 * 进入商户详情。
	 *
	 * @param {Object} shop 商户数据。
	 * @returns {void} 跳转到商户中心店铺页。
	 */
	function handleShopEnter(shop = {}) {
		const merchantId = Number(shop.id || shop.merchant_id || 0)
		const fallbackUrl = merchantId > 0 ?
			`/pages/merchant/user/index?merchant_id=${merchantId}` :
			'/pages/merchant/user/index'
		uni.navigateTo({
			url: String(shop.routeUrl || shop.route_url || fallbackUrl)
		})
	}

	/**
	 * 归一化搜索历史列表。
	 *
	 * @param {Array} list 原始历史。
	 * @returns {Array<Object>}
	 */
	function normalizeHistoryList(list = []) {
		if (!Array.isArray(list)) {
			return []
		}

		return list
			.map((item) => {
				const keyword = normalizeKeywordItem(item)
				return keyword ? {
					search: keyword,
					keyword
				} : null
			})
			.filter(Boolean)
			.slice(0, 10)
	}

	/**
	 * 批量归一化搜索结果。
	 *
	 * @param {Array} list 原始结果。
	 * @returns {Array<Object>}
	 */
	function normalizeResultList(list = []) {
		return Array.isArray(list) ?
			list.map((item) => normalizeSearchItem(item)).filter((item) => item.id) : []
	}

	/**
	 * 归一化搜索结果为卡片字段。
	 *
	 * @param {Object} item 原始结果项。
	 * @returns {Object}
	 */
	function normalizeSearchItem(item = {}) {
		const rawResultType = String(item.result_type || item.resultType || 'content')
		const resultType = rawResultType === 'product' ? 'goods' : rawResultType
		const id = Number(item.id || item.merchant_id || 0)
		const title = String(item.title || item.name || '').trim()
		const routeUrl = String(item.routeUrl || item.route_url || '').trim()
		const tags = Array.isArray(item.tags) ? item.tags : []

		return {
			...item,
			id,
			result_type: resultType,
			cover: String(item.cover || item.image || item.logo || item.avatar || '').trim(),
			title,
			subtitle: String(item.subtitle || item.description || '').trim(),
			pricePrefix: String(item.pricePrefix || item.price_prefix || '').trim(),
			price: String(item.price !== undefined ? item.price : '').trim(),
			originalPrice: String(item.originalPrice || item.original_price || '').replace(/^¥/, ''),
			badge: String(item.badge || getResultTypeText(resultType)).trim(),
			badgeClassName: String(item.badgeClassName || item.badge_class_name || (resultType === 'merchant' ?
				'is-dark' : 'is-brand')),
			tags,
			hotText: String(item.hotText || item.hot_text || '').trim(),
			routeUrl: routeUrl || buildResultRoute(resultType, id),
			sales: Number(item.sales || item.buy_count || item.total_orders || 0),
			credit: Number(item.credit || item.view_count || item.follow_count || 0),
			logoText: String(item.logoText || item.logo_text || getInitial(title || '商')),
			logoStyle: String(item.logoStyle || item.logo_style ||
				'background: linear-gradient(135deg, #52525b 0%, #18181b 100%);'),
			label: String(item.label || getResultTypeText(resultType)),
			ratingText: String(item.ratingText || item.rating_text || item.business_status_text || ''),
			fansText: String(item.fansText || item.fans_text || item.follow_count_text || ''),
			ratingValue: Number(item.ratingValue || item.rating_value || item.business_status || 0),
			fansValue: Number(item.fansValue || item.fans_value || item.follow_count || 0),
			products: Array.isArray(item.products) ? item.products : []
		}
	}

	function getResultTypeText(type = '') {
		if (type === 'special') {
			return '专题'
		}
		if (type === 'merchant') {
			return '商户'
		}
		if (type === 'goods' || type === 'product') {
			return '商品'
		}
		return '内容'
	}

	function buildResultRoute(type = '', id = 0) {
		if (!id) {
			return ''
		}
		if (type === 'special') {
			return `/pages/contents/special/detail/index?id=${id}`
		}
		if (type === 'merchant') {
			return `/pages/merchant/user/index?merchant_id=${id}`
		}
		if (type === 'goods' || type === 'product') {
			return `/pages/goods/detail/index?id=${id}`
		}
		return `/pages/contents/content/detail/index?id=${id}`
	}

	function getInitial(title = '') {
		const normalized = normalizeKeyword(title)
		return normalized ? normalized.slice(0, 1) : '商'
	}
</script>

<style lang="scss">
	page {
		background: #f5f5f5;
	}
</style>

<style lang="scss" scoped>
	.search-page {
		min-height: 100%;
		display: flex;
		flex-direction: column;
		background: #f5f5f5;
	}

	.search-page__header {
		background: #f5f5f5;
	}

	/* #ifdef MP-WEIXIN */
	.search-page__header {
		padding-top: var(--status-bar-height, 44px);
	}

	/* #endif */

	.search-page__header.is-result {
		background: rgba(245, 245, 245, 0.96);
	}

	.search-toolbar {
		display: flex;
		align-items: center;
		padding: 18rpx 32rpx 14rpx;
	}

	.search-toolbar__back {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 72rpx;
		height: 72rpx;
		margin-right: 12rpx;
		border-radius: 50%;
	}

	.search-toolbar__back-icon {
		font-size: 34rpx;
		color: #1a1c1c;
	}

	.search-toolbar__field {
		flex: 1;
		min-width: 0;
		height: 76rpx;
		display: flex;
		align-items: center;
		padding: 0 24rpx;
		border: 1rpx solid rgba(0, 0, 0, 0.04);
		border-radius: 999rpx;
		background: #f3f3f3;
	}

	.search-toolbar__field.is-result {
		background: #ffffff;
	}

	.search-toolbar__search-icon {
		flex-shrink: 0;
		font-size: 30rpx;
		line-height: 1;
		color: #5b5f64;
	}

	.search-toolbar__input {
		flex: 1;
		min-width: 0;
		height: 100%;
		margin-left: 16rpx;
		font-size: 28rpx;
		color: #1a1c1c;
	}

	.search-toolbar__placeholder {
		font-size: 28rpx;
		color: rgba(91, 95, 100, 0.6);
	}

	.search-toolbar__cancel {
		flex-shrink: 0;
		margin-left: 20rpx;
		font-size: 28rpx;
		line-height: 40rpx;
		font-weight: 700;
		color: #1a1c1c;
	}


	.search-page__scroll {
		flex: 1;
		min-height: 0;
		background: #f5f5f5;
	}

	.search-page__body {
		min-height: 100%;
		box-sizing: border-box;
		padding: 24rpx 32rpx 40rpx;
		background: #f5f5f5;
	}

	.search-page__body.is-result {
		padding-top: 20rpx;
	}
</style>
