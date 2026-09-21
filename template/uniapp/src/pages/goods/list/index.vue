<template>
	<page-meta :page-style="themePageStyle"></page-meta>
	<view class="goods-list-page">
		<app-safe-top background="var(--brand-primary-dark, #c2410c)" />

		<view class="goods-list-page__shell">
			<view class="goods-list-page__hero">
				<view class="goods-list-page__searchbar">
					<view class="goods-list-page__back" @click="handleBack">
						<text class="iconfont icon-back goods-list-page__back-icon"></text>
					</view>
					<view class="goods-list-page__searchbox">
						<text class="iconfont icon-search goods-list-page__search-icon"></text>
						<input v-model="keyword" class="goods-list-page__search-input" placeholder="搜索商品名称"
							placeholder-class="goods-list-page__search-placeholder" confirm-type="search"
							@confirm="handleSearchConfirm" />
					</view>
				</view>

				<view class="goods-list-page__sort-card">
					<view class="goods-list-page__sort-row">
						<view v-for="item in sortTabs" :key="item.key" class="goods-list-page__sort-item"
							:class="{ 'is-active': activeSortKey === item.key }" @click="handleSortTap(item.key)">
							<text class="goods-list-page__sort-text">{{ item.label }}</text>
							<text v-if="item.showArrow" class="iconfont icon-triangledownfill goods-list-page__sort-arrow"
								:class="{ 'is-up': item.key === 'price' && activeSortKey === 'price' && sortOrder === 'asc' }"></text>
							<text v-if="item.key === 'filter'" class="iconfont icon-filter goods-list-page__sort-filter"></text>
						</view>
					</view>
				</view>
			</view>

			<view class="goods-list-page__chip-row">
				<view v-for="chip in quickChips" :key="chip.key" class="goods-list-page__chip" :class="{
					'is-active': isChipActive(chip),
					'is-arrow': chip.hasArrow
				}" @click="handleChipTap(chip)">
					<text>{{ chip.label }}</text>
					<text v-if="chip.hasArrow"
						class="iconfont icon-triangledownfill goods-list-page__chip-arrow"></text>
				</view>
			</view>

			<scroll-view scroll-y class="goods-list-page__scroll" :show-scrollbar="false" lower-threshold="96"
				@scrolltolower="loadMore">
				<view class="goods-list-page__body">
					<view v-if="loading && page === 1" class="goods-list-page__loading">
						<six-petal-spiral :active="true" :size="88" text="" :color="themePrimaryColor" />
					</view>

					<view v-else-if="!goodsList.length" class="goods-list-page__empty">
						<text class="goods-list-page__empty-title">暂无商品</text>
						<text class="goods-list-page__empty-desc">换个关键词或筛选条件再试试</text>
					</view>

					<view v-else class="goods-grid">
						<view v-for="item in goodsList" :key="item.id" class="goods-card" @click="openDetail(item)">
							<view class="goods-card__cover-wrap">
								<image v-if="item.cover" class="goods-card__cover" :src="item.cover"
									mode="aspectFill" />
								<view v-else class="goods-card__cover goods-card__cover--fallback">
									<text>{{ item.initial }}</text>
								</view>
							</view>
							<view class="goods-card__body">
								<view class="goods-card__title-row">
									<tag-preview v-if="item.firstTag" :tag="item.firstTag" size="small"
										class="goods-card__tag" />
									<view class="goods-card__title"
										:class="{ 'goods-card__title--tagged': item.firstTag }">{{ item.name }}</view>
								</view>
								<view class="goods-card__price-row">
									<text class="goods-card__price">{{ item.priceText }}</text>
									<view v-if="item.vipPriceText" class="goods-card__vip">
										<image class="vip-price-icon" src="/static/images/vip.png" mode="aspectFill" />
										<text class="goods-card__vip-price">{{ item.vipPriceText }}</text>
									</view>
								</view>
								<text class="goods-card__sold">{{ item.soldText }}</text>
							</view>
						</view>
					</view>

					<view v-if="hasMore && !(loading && page === 1)" class="goods-list-page__footer-tip">
						<text>上拉加载更多</text>
					</view>
				</view>
			</scroll-view>
		</view>

		<view class="goods-filter-mask" :class="{ 'goods-filter-mask--visible': drawerVisible }"
			@click="closeFilterDrawer"></view>

		<view class="goods-filter-drawer" :class="{ 'goods-filter-drawer--visible': drawerVisible }" @click.stop="noop">
			<view class="goods-filter-drawer__header">
				<text class="goods-filter-drawer__title">筛选条件</text>
				<view class="goods-filter-drawer__close" @click="closeFilterDrawer">
					<text class="iconfont icon-close"></text>
				</view>
			</view>

			<scroll-view scroll-y class="goods-filter-drawer__scroll" :show-scrollbar="false"
				:scroll-into-view="drawerScrollInto" scroll-with-animation>
				<view class="goods-filter-drawer__body">
					<view id="drawer-price" class="filter-section">
						<text class="filter-section__title">价格区间</text>
						<view class="filter-price-row">
							<input v-model="drawerDraft.priceMin" type="digit" class="filter-price-row__input"
								placeholder="最低价" placeholder-class="filter-price-row__placeholder" />
							<view class="filter-price-row__divider">-</view>
							<input v-model="drawerDraft.priceMax" type="digit" class="filter-price-row__input"
								placeholder="最高价" placeholder-class="filter-price-row__placeholder" />
						</view>
					</view>

					<view id="drawer-merchant" class="filter-section">
						<text class="filter-section__title">商户类别</text>
						<view class="filter-chip-wrap">
							<view v-for="item in merchantTypeOptions" :key="item.value" class="filter-chip"
								:class="{ 'filter-chip--active': drawerDraft.merchantType === item.value }"
								@click="drawerDraft.merchantType = item.value">
								<text>{{ item.label }}</text>
							</view>
						</view>
					</view>

					<view id="drawer-brand" class="filter-section">
						<text class="filter-section__title">品牌</text>
						<view v-if="brandOptions.length" class="filter-chip-wrap">
							<view v-for="item in brandOptions" :key="item.value" class="filter-chip"
								:class="{ 'filter-chip--active': drawerDraft.brandId === item.value }"
								@click="drawerDraft.brandId = item.value">
								<text>{{ item.label }}</text>
							</view>
						</view>
					</view>

					<view id="drawer-store" class="filter-section">
						<text class="filter-section__title">店铺类型</text>
						<view class="filter-chip-wrap">
							<view v-for="item in storeTypeOptions" :key="item.value" class="filter-chip"
								:class="{ 'filter-chip--active': drawerDraft.storeType === item.value }"
								@click="drawerDraft.storeType = item.value">
								<text>{{ item.label }}</text>
							</view>
						</view>
					</view>

					<view v-for="group in paramGroups" :id="`drawer-param-${group.key}`" :key="group.key"
						class="filter-section filter-section--compact">
						<view class="filter-section__head" @click="toggleParamExpand(group.key)">
							<text class="filter-section__title">{{ group.name }}</text>
							<view class="filter-section__more">
								<text>{{ paramExpanded[group.key] ? '收起' : '展开' }}</text>
								<text class="iconfont icon-triangledownfill filter-section__more-icon"
									:class="{ 'is-up': paramExpanded[group.key] }"></text>
							</view>
						</view>
						<view v-if="paramExpanded[group.key]" class="filter-chip-wrap filter-chip-wrap--param">
							<view v-for="value in group.values" :key="value" class="filter-chip filter-chip--param"
								:class="{ 'filter-chip--active': isParamSelected(group.key, value) }"
								@click="toggleParamValue(group.key, value)">
								<text>{{ value }}</text>
							</view>
						</view>
					</view>
				</view>
			</scroll-view>

			<view class="goods-filter-drawer__footer">
				<view class="goods-filter-drawer__button goods-filter-drawer__button--ghost" @click="handleResetDrawer">
					<text>重置</text>
				</view>
				<view class="goods-filter-drawer__button goods-filter-drawer__button--primary"
					@click="handleConfirmDrawer">
					<text>确定</text>
				</view>
			</view>
		</view>
	</view>
</template>

<script setup>
import { computed, nextTick, reactive, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getProductAttributeOptions, getProductBrandOptions, getProductList } from '@/api/product'
import { normalizeDisplayTags } from '@/utils/tag'

defineOptions({
	name: 'GoodsListPage'
})

const limit = 10
const keyword = ref('')
const page = ref(1)
const total = ref(0)
const goodsList = ref([])
const loading = ref(false)
const categoryId = ref(0)
const merchantId = ref(0)
const drawerVisible = ref(false)
const drawerScrollInto = ref('drawer-price')
const activeSortKey = ref('default')
const sortOrder = ref('desc')

const paramGroups = ref([])
const paramExpanded = reactive({})

const quickChips = [
	{ key: 'shipping', label: '包邮', type: 'toggle' },
	{ key: 'brand', label: '品牌', type: 'drawer', anchor: 'drawer-brand', hasArrow: true },
	{ key: 'category', label: '类别', type: 'drawer', anchor: 'drawer-merchant', hasArrow: true },
	{ key: 'merchant', label: '商户类型', type: 'drawer', anchor: 'drawer-merchant', hasArrow: true }
]

const sortTabs = [
	{ key: 'default', label: '综合', showArrow: true },
	{ key: 'sales', label: '销量', showArrow: true },
	{ key: 'price', label: '价格', showArrow: true },
	{ key: 'filter', label: '筛选', showArrow: false }
]

const merchantTypeOptions = [
	{ value: 'all', label: '全部' },
	{ value: 'self', label: '自营' },
	{ value: 'non_self', label: '非自营' }
]

const storeTypeOptions = [
	{ value: 'online', label: '线上店' },
	{ value: '001', label: '001分类' }
]

const brandOptions = ref([])

const appliedFilters = reactive(getDefaultFilterState())
const drawerDraft = reactive(getDefaultFilterState())

const hasMore = computed(() => total.value > 0 && page.value * limit < total.value)

onLoad((options = {}) => {
	categoryId.value = Number(options.category_id || options.categoryId || 0)
	merchantId.value = Math.max(0, Number(options.merchant_id || options.merchantId || 0))
	keyword.value = String(options.keyword || '').trim()
	loadFilterOptions()
	loadGoods(true)
})

function getDefaultFilterState() {
	return {
		priceMin: '',
		priceMax: '',
		merchantType: 'all',
		storeType: '',
		brandId: 0,
		shippingOnly: false,
		paramSelections: {}
	}
}

function cloneFilterState(source) {
	return {
		priceMin: String(source.priceMin || ''),
		priceMax: String(source.priceMax || ''),
		merchantType: String(source.merchantType || 'all'),
		storeType: String(source.storeType || ''),
		brandId: Number(source.brandId || 0),
		shippingOnly: Boolean(source.shippingOnly),
		paramSelections: Object.keys(source.paramSelections || {}).reduce((result, key) => {
			result[key] = Array.isArray(source.paramSelections[key]) ? [...source.paramSelections[key]] : []
			return result
		}, {})
	}
}

function resetFilterState(target, source = getDefaultFilterState()) {
	const snapshot = cloneFilterState(source)
	target.priceMin = snapshot.priceMin
	target.priceMax = snapshot.priceMax
	target.merchantType = snapshot.merchantType
	target.storeType = snapshot.storeType
	target.brandId = snapshot.brandId
	target.shippingOnly = snapshot.shippingOnly
	target.paramSelections = snapshot.paramSelections
}

async function loadFilterOptions() {
	brandOptions.value = []
	paramGroups.value = []

	try {
		const [brandRes, attributeRes] = await Promise.all([
			getProductBrandOptions({}, { showError: false }).catch(() => null),
			getProductAttributeOptions({}, { showError: false }).catch(() => null)
		])
		brandOptions.value = normalizeBrandOptions(brandRes?.data || [])
		paramGroups.value = normalizeAttributeGroups(attributeRes?.data || [])
		Object.keys(paramExpanded).forEach((key) => {
			delete paramExpanded[key]
		})
		paramGroups.value.forEach((group) => {
			paramExpanded[group.key] = false
		})
	} catch (error) {
		brandOptions.value = []
		paramGroups.value = []
	}
}

async function loadGoods(reset = false) {
	if (loading.value) {
		return
	}

	if (reset) {
		page.value = 1
	}

	loading.value = true
	try {
		const res = await getProductList(buildRequestParams(), { showError: false })
		const data = res.data || {}
		const list = Array.isArray(data.list) ? data.list : []
		const normalized = list.map((item) => normalizeProductItem(item)).filter((item) => item.id > 0)
		goodsList.value = reset ? normalized : goodsList.value.concat(normalized)
		total.value = Number(data.total || 0)
	} catch (error) {
		if (reset) {
			goodsList.value = []
		}
		total.value = 0
	} finally {
		loading.value = false
	}
}

function buildRequestParams() {
	return {
		category_id: categoryId.value,
		merchant_id: merchantId.value || '',
		include_child_categories: categoryId.value > 0 ? 1 : 0,
		keyword: keyword.value,
		sort: buildSortParam(),
		page: page.value,
		limit,
		param_filters: JSON.stringify(buildParamFilters()),
		price_min: appliedFilters.priceMin,
		price_max: appliedFilters.priceMax,
		brand_id: appliedFilters.brandId,
		merchant_type: appliedFilters.merchantType,
		store_type: appliedFilters.storeType,
		is_free_shipping: appliedFilters.shippingOnly ? 1 : ''
	}
}

function normalizeBrandOptions(list = []) {
	return list.map((item) => {
		const label = String(item.name || item.label || '').trim()
		return {
			value: Number(item.id || item.value || 0),
			label
		}
	}).filter((item) => item.value > 0 && item.label)
}

function normalizeAttributeGroups(list = []) {
	const groups = []
	// 单独取出待遍历数组，避免上一行以 [] 结尾、本行以 ( 开头被解析成“空数组当函数调用”
	const templates = Array.isArray(list) ? list : []
	templates.forEach((template) => {
		const attributeId = Number(template.id || template.attribute_id || 0)
		const templateName = String(template.name || template.label || '').trim()
		const params = Array.isArray(template.params) ? template.params : []
		params.forEach((item, index) => {
			const paramKey = String(item.row_key || item.key || `param_${attributeId || index + 1}`).trim()
			const name = String(item.name || templateName).trim()
			const values = Array.isArray(item.values)
				? item.values.map((value) => String(value).trim()).filter(Boolean)
				: []
			if (!attributeId || !paramKey || !name || !values.length) {
				return
			}

			groups.push({
				key: `${attributeId}_${paramKey}`,
				attributeId,
				paramKey,
				name,
				values
			})
		})
	})

	return groups
}

function buildSortParam() {
	if (activeSortKey.value === 'sales') {
		return 'sales'
	}
	if (activeSortKey.value === 'price') {
		return sortOrder.value === 'asc' ? 'price_asc' : 'price_desc'
	}
	return ''
}

function normalizeProductItem(item) {
	const price = Number(item.price || 0)
	const vipPrice = Number(item.vip_price || item.member_price || (price * 0.85))
	const displayTags = normalizeDisplayTags(item.tag_list, 1)
	const firstTag = displayTags.length > 0 ? displayTags[0] : null
	return {
		id: Number(item.id || 0),
		name: String(item.name || '').trim(),
		cover: String(item.cover || item.image || '').trim(),
		priceText: formatMoney(price),
		vipPriceText: formatMoney(vipPrice),
		soldText: `已售 ${formatSoldCount(Number(item.display_sales || item.sales || 0))}`,
		firstTag,
		initial: String(item.name || '').trim().slice(0, 1) || '商'
	}
}

function formatMoney(value) {
	return `¥${Number(value || 0).toFixed(2)}`
}

function formatSoldCount(value) {
	const count = Math.max(0, Number(value || 0))
	if (count >= 10000) {
		const num = (count / 10000).toFixed(count % 10000 === 0 ? 0 : 1)
		return `${num}万件`
	}
	return `${count}件`
}

function handleSearchConfirm() {
	page.value = 1
	loadGoods(true)
}

function handleBack() {
	uni.navigateBack({
		delta: 1
	})
}

function handleSortTap(key) {
	if (key === 'filter') {
		openFilterDrawer()
		return
	}

	if (key === 'price') {
		if (activeSortKey.value === 'price') {
			sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
		} else {
			activeSortKey.value = 'price'
			sortOrder.value = 'desc'
		}
	} else {
		activeSortKey.value = key
	}

	page.value = 1
	loadGoods(true)
}

function handleChipTap(chip) {
	if (chip.type === 'toggle') {
		appliedFilters.shippingOnly = !appliedFilters.shippingOnly
		page.value = 1
		loadGoods(true)
		return
	}

	openFilterDrawer(chip.anchor || 'drawer-price')
}

function isChipActive(chip) {
	if (chip.type === 'toggle') {
		return appliedFilters.shippingOnly
	}
	return false
}

function openFilterDrawer(anchor = 'drawer-price') {
	resetFilterState(drawerDraft, appliedFilters)
	drawerVisible.value = true
	nextTick(() => {
		drawerScrollInto.value = anchor || 'drawer-price'
	})
}

function closeFilterDrawer() {
	drawerVisible.value = false
}

function handleResetDrawer() {
	resetFilterState(drawerDraft)
	drawerScrollInto.value = 'drawer-price'
}

function handleConfirmDrawer() {
	resetFilterState(appliedFilters, drawerDraft)
	closeFilterDrawer()
	page.value = 1
	loadGoods(true)
}

function toggleParamExpand(key) {
	paramExpanded[key] = !paramExpanded[key]
	if (paramExpanded[key]) {
		drawerScrollInto.value = `drawer-param-${key}`
	}
}

function isParamSelected(key, value) {
	const values = drawerDraft.paramSelections[key] || []
	return values.includes(value)
}

function toggleParamValue(key, value) {
	const current = Array.isArray(drawerDraft.paramSelections[key]) ? [...drawerDraft.paramSelections[key]] : []
	const index = current.indexOf(value)
	if (index >= 0) {
		current.splice(index, 1)
	} else {
		current.push(value)
	}
	drawerDraft.paramSelections = {
		...drawerDraft.paramSelections,
		[key]: current.filter(Boolean)
	}
}

function buildParamFilters() {
	return paramGroups.value.map((group) => ({
		attribute_id: group.attributeId,
		param_key: group.paramKey,
		values: Array.isArray(appliedFilters.paramSelections[group.key]) ? appliedFilters.paramSelections[group.key] : []
	})).filter((item) => item.values.length)
}

function loadMore() {
	if (!hasMore.value || loading.value) {
		return
	}
	page.value += 1
	loadGoods(false)
}

function openDetail(item) {
	if (!item || !item.id) {
		return
	}

	uni.navigateTo({
		url: `/pages/goods/detail/index?id=${item.id}`
	})
}

function noop() { }
</script>

<style lang="scss">
page {
	height: 100%;
	background: var(--page-bg, #f5f5f5);
}
</style>

<style lang="scss" scoped>
.goods-list-page {
	display: flex;
	flex-direction: column;
	height: 100%;
	min-height: 100%;
	background: var(--page-bg, #f5f5f5);
	overflow: hidden;
}

.goods-list-page__shell {
	display: flex;
	flex-direction: column;
	flex: 1;
	min-height: 0;
	padding: 0 24rpx 24rpx;
	box-sizing: border-box;
}

.goods-list-page__hero {
	margin: 0 -24rpx;
	padding: 0 24rpx 18rpx;
	background: var(--brand-primary-dark);
}

.goods-list-page__searchbar {
	display: flex;
	align-items: center;
	padding: 18rpx 0 18rpx;
}

.goods-list-page__back {
	display: flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
	width: 72rpx;
	height: 72rpx;
	margin-right: 10rpx;
}

.goods-list-page__back-icon {
	font-size: 34rpx;
	color: var(--text-inverse, #ffffff);
}

.goods-list-page__searchbox {
	flex: 1;
	min-width: 0;
	display: flex;
	align-items: center;
	height: 76rpx;
	padding: 0 22rpx 0 24rpx;
	border-radius: 999rpx;
	background: rgba(255, 255, 255, 0.98);
	box-shadow: 0 10rpx 24rpx rgba(15, 23, 42, 0.12);
}

.goods-list-page__search-icon {
	flex-shrink: 0;
	font-size: 36rpx;
	color: var(--text-placeholder, #a1a1aa);
}

.goods-list-page__search-input {
	flex: 1;
	min-width: 0;
	height: 84rpx;
	margin-left: 12rpx;
	font-size: 28rpx;
	color: var(--text-title, #18181b);
}

.goods-list-page__search-placeholder {
	font-size: 28rpx;
	color: var(--text-placeholder, #a1a1aa);
}

.goods-list-page__sort-card {
	background: transparent;
	box-shadow: none;
	overflow: visible;
}

.goods-list-page__sort-row {
	display: flex;
	align-items: center;
	min-height: 92rpx;
	padding: 6rpx 8rpx 20rpx;
}

.goods-list-page__sort-item {
	display: flex;
	align-items: center;
	justify-content: center;
	flex: 1;
	padding-bottom: 12rpx;
	border-bottom: 4rpx solid transparent;
	font-size: 32rpx;
	font-weight: 500;
	color: rgba(255, 255, 255, 0.82);
}

.goods-list-page__sort-item.is-active {
	color: #ffffff;
	font-weight: 600;
	border-bottom-color: #ffffff;
}

.goods-list-page__sort-text {
	line-height: 1;
}

.goods-list-page__sort-arrow {
	margin-left: 8rpx;
	font-size: 22rpx;
	color: inherit;
	transform: translateY(1rpx);
}

.goods-list-page__sort-arrow.is-up {
	transform: translateY(1rpx) rotate(180deg);
}

.goods-list-page__sort-filter {
	margin-left: 8rpx;
	font-size: 26rpx;
	color: inherit;
}

.goods-list-page__chip-row {
	display: flex;
	align-items: center;
	margin: -36rpx -24rpx 18rpx;
	padding: 18rpx 24rpx 18rpx;
	border-radius: 30rpx 30rpx 0 0;
	background: var(--surface-card, #ffffff);
	box-shadow: 0 10rpx 28rpx rgba(15, 23, 42, 0.06);
	overflow: hidden;
}

.goods-list-page__chip {
	display: flex;
	align-items: center;
	justify-content: center;
	flex: 1;
	height: 64rpx;
	margin: 0 8rpx;
	padding: 0 18rpx;
	border-radius: 999rpx;
	background: var(--surface-card-soft, #f3f4f6);
	font-size: 24rpx;
	color: var(--text-title, #18181b);
	box-sizing: border-box;
}

.goods-list-page__chip.is-arrow {
	padding-right: 12rpx;
}

.goods-list-page__chip.is-active {
	background: var(--brand-primary, #ea580c);
	color: var(--text-inverse, #ffffff);
}

.goods-list-page__chip-arrow {
	margin-left: 8rpx;
	font-size: 20rpx;
}

.goods-list-page__scroll {
	flex: 1;
	min-height: 0;
}

.goods-list-page__body {
	padding-bottom: 24rpx;
}

.goods-list-page__loading,
.goods-list-page__empty {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	min-height: 520rpx;
	padding: 80rpx 0;
}

.goods-list-page__empty-title {
	font-size: 30rpx;
	font-weight: 600;
	color: #111111;
}

.goods-list-page__empty-desc {
	margin-top: 12rpx;
	font-size: 24rpx;
	color: #8e8e93;
}

.goods-grid {
	display: flex;
	flex-wrap: wrap;
	justify-content: space-between;
}

.goods-card {
	width: 49%;
	padding: 0 0 18rpx;
	box-sizing: border-box;
	background-color: #fff;
	border-radius: 22rpx;
	margin-top: 20rpx;
}

.goods-card__cover-wrap {
	overflow: hidden;
	border-radius: 18rpx 18rpx 0 0;
	background: #f7f7f7;
}

.goods-card__cover {
	display: block;
	width: 100%;
	height: 336rpx;
	background: #f7f7f7;
}

.goods-card__cover--fallback {
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 64rpx;
	font-weight: 700;
	color: var(--brand-primary, #ea580c);
	background: var(--brand-primary-pale, #fff7ed);
}

.goods-card__body {
	padding: 16rpx 8rpx 2rpx;
}

.goods-card__title-row {
	position: relative;
	min-height: 80rpx;
}

.goods-card__tag {
	position: absolute;
	left: 0;
	top: 0;
	z-index: 1;
	margin-right: 0;
}

.goods-card__title {
	width: 100%;
	height: 80rpx;
	display: -webkit-box;
	font-size: 28rpx;
	line-height: 40rpx;
	font-weight: 500;
	color: var(--text-title, #1a1c1c);
	overflow: hidden;
	text-overflow: ellipsis;
	-webkit-line-clamp: 2;
	-webkit-box-orient: vertical;
	word-break: break-all;
}

.goods-card__title--tagged {
	text-indent: 80rpx;
}

.goods-card__price-row {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	margin-top: 10rpx;
}

.goods-card__price {
	font-size: 32rpx;
	line-height: 1;
	font-weight: 700;
	color: var(--brand-primary, #ea580c);
}

.goods-card__vip {
	flex-shrink: 0;
	min-width: 0;
	display: flex;
	align-items: center;
	margin-left: 14rpx;
	overflow: hidden;
	white-space: nowrap;
}

.goods-card__vip-price {
	min-width: 0;
	color: var(--brand-primary, #ea580c);
	font-size: 24rpx;
	line-height: 1.2;
	font-weight: 600;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.vip-price-icon {
	display: block;
	flex-shrink: 0;
	width: 36rpx;
	height: 24rpx;
	margin-right: 4rpx;
}

.goods-card__sold {
	display: block;
	margin-top: 12rpx;
	font-size: 24rpx;
	line-height: 1.4;
	color: #7a7a7a;
}

.goods-list-page__footer-tip {
	padding: 18rpx 0 10rpx;
	text-align: center;
	font-size: 22rpx;
	color: #8e8e93;
}

.goods-filter-mask {
	position: fixed;
	left: 0;
	top: 0;
	right: 0;
	bottom: 0;
	z-index: 50;
	background: rgba(0, 0, 0, 0.28);
	opacity: 0;
	pointer-events: none;
	transition: opacity 0.24s ease;
}

.goods-filter-mask--visible {
	opacity: 1;
	pointer-events: auto;
}

.goods-filter-drawer {
	position: fixed;
	top: 0;
	right: 0;
	bottom: 0;
	z-index: 60;
	display: flex;
	flex-direction: column;
	width: 682rpx;
	background: #ffffff;
	box-shadow: -8rpx 0 28rpx rgba(17, 24, 39, 0.12);
	transform: translateX(100%);
	opacity: 0;
	pointer-events: none;
	transition: transform 0.28s ease, opacity 0.24s ease;
	will-change: transform, opacity;
}

.goods-filter-drawer--visible {
	transform: translateX(0);
	opacity: 1;
	pointer-events: auto;
}

.goods-filter-drawer__header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 24rpx 24rpx 20rpx 28rpx;
}

.goods-filter-drawer__title {
	font-size: 30rpx;
	font-weight: 600;
	color: var(--text-title, #1a1c1c);
}

.goods-filter-drawer__close {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 52rpx;
	height: 52rpx;
	font-size: 34rpx;
	color: var(--text-secondary, #6b7280);
}

.goods-filter-drawer__scroll {
	flex: 1;
	min-height: 0;
}

.goods-filter-drawer__body {
	padding: 0 24rpx 24rpx 28rpx;
	box-sizing: border-box;
}

.filter-section {
	margin-bottom: 28rpx;
}

.filter-section--compact {
	margin-bottom: 20rpx;
}

.filter-section__title {
	display: block;
	font-size: 26rpx;
	font-weight: 600;
	color: var(--text-title, #1a1c1c);
}

.filter-section__head {
	display: flex;
	align-items: center;
	justify-content: space-between;
}

.filter-section__more {
	display: flex;
	align-items: center;
	font-size: 22rpx;
	color: var(--text-secondary, #6b7280);
}

.filter-section__more-icon {
	margin-left: 6rpx;
	font-size: 18rpx;
	transform: rotate(0deg);
	transition: transform 0.2s ease;
}

.filter-section__more-icon.is-up {
	transform: rotate(180deg);
}

.filter-price-row {
	display: flex;
	align-items: center;
	margin-top: 18rpx;
}

.filter-price-row__input {
	flex: 1;
	height: 64rpx;
	padding: 0 20rpx;
	border-radius: 999rpx;
	background: var(--brand-primary-pale, #fff7ed);
	font-size: 24rpx;
	color: var(--text-title, #1a1c1c);
	box-sizing: border-box;
}

.filter-price-row__divider {
	flex-shrink: 0;
	padding: 0 16rpx;
	font-size: 26rpx;
	color: var(--text-secondary, #6b7280);
}

.filter-price-row__placeholder {
	font-size: 24rpx;
	color: var(--text-placeholder, #b2b2b7);
}

.filter-chip-wrap {
	display: flex;
	flex-wrap: wrap;
	margin: 18rpx -8rpx 0;
}

.filter-chip-wrap--param {
	margin-top: 14rpx;
}

.filter-chip {
	display: flex;
	align-items: center;
	justify-content: center;
	min-width: 132rpx;
	height: 60rpx;
	margin: 0 8rpx 16rpx;
	padding: 0 18rpx;
	border-radius: 999rpx;
	background: var(--brand-primary-pale, #fff7ed);
	font-size: 24rpx;
	color: var(--text-title, #1a1c1c);
	box-sizing: border-box;
}

.filter-chip--param {
	min-width: 112rpx;
}

.filter-chip--active {
	background: var(--brand-primary, #ea580c);
	color: var(--text-inverse, #ffffff);
}

.goods-filter-drawer__footer {
	display: flex;
	align-items: center;
	padding: 18rpx 24rpx 24rpx 28rpx;
	border-top: 1rpx solid #f0f0f0;
	box-sizing: border-box;

	/* #ifdef MP-WEIXIN */
	padding-bottom: calc(env(safe-area-inset-bottom) + 24rpx);
	/* #endif */
}

.goods-filter-drawer__button {
	display: flex;
	align-items: center;
	justify-content: center;
	flex: 1;
	height: 72rpx;
	border-radius: 999rpx;
	font-size: 28rpx;
	font-weight: 600;
	box-sizing: border-box;
}

.goods-filter-drawer__button--ghost {
	margin-right: 18rpx;
	border: 2rpx solid var(--brand-primary, #ea580c);
	color: var(--brand-primary, #ea580c);
	background: #ffffff;
}

.goods-filter-drawer__button--primary {
	background: var(--theme-gradient, linear-gradient(135deg, #ea580c 0%, #fb923c 100%));
	color: #ffffff;
}
</style>
