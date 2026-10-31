<template>
	<view class="product-expand">
		<view class="expand-header">
			<view class="expand-title-row">
				<text class="expand-title-row__title">分类</text>
				<text class="iconfont iconsousuo expand-title-row__search" @click="openSearch"></text>
			</view>
			<view class="category-search">
				<text class="iconfont iconsousuo category-search__icon"></text>
				<input
					v-model="keyword"
					class="category-search__input"
					placeholder="搜索商品"
					placeholder-class="category-search__placeholder"
					confirm-type="search"
					@confirm="handleSearch"
				/>
			</view>
		</view>

		<scroll-view scroll-x class="root-icons" :show-scrollbar="false">
			<view class="root-icons__track">
				<view
					v-for="item in rootCategories"
					:key="item.id"
					class="root-icons__item"
					:class="{ 'root-icons__item--active': item.id === activeRootId }"
					@click="handleRootChange(item)"
				>
					<view class="root-icons__image-box">
						<image v-if="item.image" class="root-icons__image" :src="item.image" mode="aspectFill" />
						<view v-else class="root-icons__fallback">
							<text>{{ item.initial }}</text>
						</view>
					</view>
					<text class="root-icons__name">{{ item.name }}</text>
				</view>
			</view>
		</scroll-view>

		<scroll-view scroll-x class="second-tabs" :show-scrollbar="false">
			<view class="second-tabs__track">
				<view
					v-for="item in secondCategories"
					:key="item.id"
					class="second-tabs__item"
					:class="{ 'second-tabs__item--active': item.id === activeSecondId }"
					@click="handleSecondChange(item)"
				>
					<text>{{ item.name }}</text>
					<view v-if="item.id === activeSecondId" class="second-tabs__line"></view>
				</view>
			</view>
		</scroll-view>

		<view class="product-expand__body">
			<scroll-view scroll-y class="third-list" :show-scrollbar="false">
				<view
					v-for="item in thirdCategories"
					:key="item.id"
					class="third-list__item"
					:class="{ 'third-list__item--active': item.id === activeThirdId }"
					@click="handleThirdChange(item)"
				>
					<text>{{ item.name }}</text>
				</view>
			</scroll-view>

			<scroll-view
				scroll-y
				refresher-enabled
				class="goods-scroll"
				:show-scrollbar="false"
				:refresher-triggered="refreshing"
				@refresherrefresh="handleRefresh"
				@scrolltolower="loadMore"
			>
				<view class="goods-scroll__inner">
					<view v-if="goodsLoading && page === 1" class="category-state">
						<six-petal-spiral :active="true" :size="80" text="" :color="themePrimaryColor" />
					</view>

					<view v-else-if="!goodsList.length" class="category-state">
						<text class="category-state__title">暂无商品</text>
						<text class="category-state__text">换个分类看看</text>
					</view>

					<view v-else>
						<view
							v-for="item in goodsList"
							:key="item.id"
							class="goods-card"
							@click="openProductDetail(item)"
						>
							<view class="goods-card__cover-box">
								<image v-if="item.cover" class="goods-card__cover" :src="item.cover" mode="aspectFill" />
								<view v-else class="goods-card__cover-placeholder">
									<text>{{ item.initial }}</text>
								</view>
							</view>
							<view class="goods-card__info">
								<text class="goods-card__name">{{ item.name }}</text>
								<view class="goods-card__tags">
									<text v-if="item.shippingText" class="goods-card__tag">{{ item.shippingText }}</text>
									<text v-if="item.stockText" class="goods-card__tag">{{ item.stockText }}</text>
								</view>
								<view class="goods-card__bottom">
									<text class="goods-card__price">¥{{ item.priceText }}</text>
									<view class="goods-card__cart">
										<text class="iconfont icon-daohanggouwuche"></text>
									</view>
								</view>
							</view>
						</view>

						<view class="goods-more">
							<text>{{ hasMore ? '上拉加载更多' : '没有更多了' }}</text>
						</view>
					</view>
				</view>
			</scroll-view>
		</view>
	</view>
</template>

<script setup>
	import { computed, ref, watch } from 'vue'
	import { getProductCategories, getProductList } from '@/api/product'

	defineOptions({
		name: 'ProductCategoryExpand'
	})

	const props = defineProps({
		categoryIds: {
			type: Array,
			default: () => []
		}
	})

	const limit = 10
	const keyword = ref('')
	const rootCategories = ref([])
	const activeRootId = ref(0)
	const activeSecondId = ref(0)
	const activeThirdId = ref(0)
	const goodsList = ref([])
	const page = ref(1)
	const totalPage = ref(0)
	const goodsLoading = ref(false)
	const refreshing = ref(false)
	const DEFAULT_CATEGORY_IMAGE = '/static/images/category-default.png'
	const ALL_CATEGORY_ID = 0
	const ALL_CATEGORY_NAME = '全部'

	const categoryIdsParam = computed(() => props.categoryIds.join(','))
	const activeRoot = computed(() => rootCategories.value.find((item) => item.id === activeRootId.value) || rootCategories.value[0] || null)
	const secondCategories = computed(() => {
		if (!activeRoot.value) {
			return []
		}
		return activeRoot.value.children.length ? activeRoot.value.children : [activeRoot.value]
	})
	const activeSecond = computed(() => secondCategories.value.find((item) => item.id === activeSecondId.value) || secondCategories.value[0] || null)
	const thirdCategories = computed(() => {
		if (!activeRoot.value) {
			return []
		}
		if (!activeSecond.value) {
			return [createAllCategory()]
		}
		return [
			createAllCategory(),
			...activeSecond.value.children
		]
	})
	const activeCategoryId = computed(() => {
		if (activeThirdId.value > ALL_CATEGORY_ID) {
			return Number(activeThirdId.value)
		}
		if (activeSecondId.value > ALL_CATEGORY_ID) {
			return Number(activeSecondId.value)
		}
		return Number(activeRootId.value || 0)
	})
	const hasMore = computed(() => totalPage.value === 0 ? false : page.value < totalPage.value)

	watch(categoryIdsParam, () => {
		fetchCategories()
	}, { immediate: true })

	async function fetchCategories() {
		try {
			const res = await getProductCategories({ category_ids: categoryIdsParam.value }, { showError: false })
			rootCategories.value = res.code === 200 && Array.isArray(res.data) ? normalizeCategoryList(res.data) : []
			resetSelections()
			await fetchGoods(true)
		} catch (error) {
			rootCategories.value = []
			resetSelections()
			goodsList.value = []
			totalPage.value = 0
		}
	}

	async function fetchGoods(reset = false) {
		if (!activeCategoryId.value && !rootCategories.value.length) {
			goodsList.value = []
			totalPage.value = 0
			return
		}

		if (goodsLoading.value) {
			return
		}

		goodsLoading.value = true
		if (reset) {
			page.value = 1
			totalPage.value = 0
		}

		try {
			const res = await getProductList({
				category_id: activeCategoryId.value,
				include_child_categories: activeCategoryId.value > 0 ? 1 : 0,
				page: page.value,
				limit,
				sort: ''
			}, { showError: false })
			const data = res.data || {}
			const nextList = res.code === 200 && Array.isArray(data.list) ? normalizeGoodsList(data.list) : []
			goodsList.value = reset ? nextList : goodsList.value.concat(nextList)
			totalPage.value = Number(data.totalPage || data.total_page || 0)
		} catch (error) {
			if (reset) {
				goodsList.value = []
			}
			totalPage.value = 0
		} finally {
			goodsLoading.value = false
			refreshing.value = false
		}
	}

	function normalizeCategoryList(list = []) {
		return list.map((item) => {
			const name = String(item.name || item.label || '').trim()
			return {
				id: Number(item.id || item.value || 0),
				pid: Number(item.pid || 0),
				name,
				image: normalizeCategoryImage(item.image),
				banner_image: String(item.banner_image || '').trim(),
				description: String(item.description || '').trim(),
				initial: name ? name.slice(0, 1) : '商',
				children: Array.isArray(item.children) ? normalizeCategoryList(item.children) : []
			}
		}).filter((item) => item.id > 0)
	}

	function normalizeCategoryImage(value = '') {
		const image = String(value || '').trim()
		return image || DEFAULT_CATEGORY_IMAGE
	}

	function normalizeGoodsList(list = []) {
		return list.map((item) => {
			const name = String(item.name || '').trim()
			const stock = Number(item.stock || 0)
			return {
				id: Number(item.id || 0),
				name,
				cover: String(item.cover || item.image || '').trim(),
				priceText: formatPrice(item.price),
				stockText: stock > 0 ? `库存${stock}${item.unit || ''}` : '',
				shippingText: Number(item.is_free_shipping || 0) === 1 ? '包邮' : '',
				initial: name ? name.slice(0, 1) : '商'
			}
		}).filter((item) => item.id > 0)
	}

	function resetSelections() {
		const root = rootCategories.value[0]
		activeRootId.value = root ? root.id : 0
		syncSecondSelection()
	}

	function syncSecondSelection() {
		const second = secondCategories.value[0]
		activeSecondId.value = second ? second.id : 0
		syncThirdSelection()
	}

	function syncThirdSelection() {
		activeThirdId.value = ALL_CATEGORY_ID
	}

	function handleRootChange(item) {
		if (!item || item.id === activeRootId.value) {
			return
		}
		activeRootId.value = item.id
		syncSecondSelection()
		fetchGoods(true)
	}

	function handleSecondChange(item) {
		if (!item || item.id === activeSecondId.value) {
			return
		}
		activeSecondId.value = item.id
		syncThirdSelection()
		fetchGoods(true)
	}

	function handleThirdChange(item) {
		if (!item || item.id === activeThirdId.value) {
			return
		}
		activeThirdId.value = item.id
		fetchGoods(true)
	}

	function handleRefresh() {
		refreshing.value = true
		fetchGoods(true)
	}

	function loadMore() {
		if (!hasMore.value || goodsLoading.value) {
			return
		}
		page.value += 1
		fetchGoods(false)
	}

	function handleSearch() {
		const searchKeyword = String(keyword.value || '').trim()
		if (!searchKeyword) {
			uni.showToast({
				title: '请输入搜索内容',
				icon: 'none'
			})
			return
		}
		uni.navigateTo({
			url: `/pages/contents/search/index?keyword=${encodeURIComponent(searchKeyword)}&type=all`
		})
	}

	function openSearch() {
		uni.navigateTo({
			url: '/pages/contents/search/index'
		})
	}

	function openProductDetail(item) {
		if (!item || !item.id) {
			return
		}
		uni.navigateTo({
			url: `/pages/goods/detail/index?id=${item.id}`
		})
	}

	function formatPrice(value) {
		const price = Number(value || 0)
		return price > 0 ? price.toFixed(2) : '0.00'
	}

	function createAllCategory() {
		return {
			id: ALL_CATEGORY_ID,
			pid: 0,
			name: ALL_CATEGORY_NAME,
			image: '',
			banner_image: '',
			description: '',
			initial: ALL_CATEGORY_NAME,
			children: []
		}
	}
</script>

<style lang="scss" scoped>
	.product-expand {
		display: flex;
		flex: 1;
		flex-direction: column;
		min-height: 0;
		background: #ffffff;
	}

	.expand-header {
		flex-shrink: 0;
		padding: 14rpx 32rpx 18rpx;
		background: #ffffff;
	}

	.expand-title-row {
		position: relative;
		display: flex;
		align-items: center;
		justify-content: center;
		height: 56rpx;
	}

	.expand-title-row__search {
		position: absolute;
		top: 0;
		display: flex;
		align-items: center;
		justify-content: center;
		width: 56rpx;
		height: 56rpx;
		font-size: 44rpx;
		line-height: 56rpx;
		color: $text-title;
	}

	.expand-title-row__search {
		right: 0;
		font-size: 32rpx;
	}

	.expand-title-row__title {
		font-size: 34rpx;
		line-height: 48rpx;
		font-weight: 700;
		color: $text-title;
	}

	.category-search {
		display: flex;
		align-items: center;
		height: 72rpx;
		margin-top: 18rpx;
		padding: 0 26rpx;
		border-radius: 999rpx;
		background: #f2f2f3;
	}

	.category-search__icon {
		flex-shrink: 0;
		font-size: 30rpx;
		color: $text-placeholder;
	}

	.category-search__input {
		flex: 1;
		min-width: 0;
		height: 72rpx;
		margin-left: 14rpx;
		font-size: 26rpx;
		color: $text-title;
	}

	.category-search__placeholder {
		font-size: 26rpx;
		color: $text-placeholder;
	}

	.root-icons {
		flex-shrink: 0;
		height: 142rpx;
		background: #ffffff;
	}

	.root-icons__track {
		display: flex;
		align-items: center;
		height: 142rpx;
		padding: 0 24rpx;
	}

	.root-icons__item {
		display: flex;
		flex-shrink: 0;
		flex-direction: column;
		align-items: center;
		width: 120rpx;
		margin-right: 24rpx;
		color: $text-title;
	}

	.root-icons__item--active {
		color: $brand-primary;
		font-weight: 700;
	}

	.root-icons__image-box {
		width: 76rpx;
		height: 76rpx;
		border-radius: 28rpx;
		background: #f7f7f7;
		overflow: hidden;
	}

	.root-icons__image,
	.root-icons__fallback {
		width: 100%;
		height: 100%;
	}

	.root-icons__fallback {
		display: flex;
		align-items: center;
		justify-content: center;
		background: $brand-primary-pale;
		color: $brand-primary;
		font-size: 28rpx;
	}

	.root-icons__name {
		display: block;
		width: 120rpx;
		height: 34rpx;
		margin-top: 8rpx;
		font-size: 24rpx;
		line-height: 34rpx;
		text-align: center;
		overflow: hidden;
		white-space: nowrap;
		text-overflow: ellipsis;
	}

	.second-tabs {
		flex-shrink: 0;
		height: 72rpx;
		background: #ffffff;
		border-bottom: 1rpx solid #f2f2f2;
		white-space: nowrap;
	}

	.second-tabs__track {
		display: flex;
		align-items: center;
		height: 72rpx;
		padding: 0 32rpx;
	}

	.second-tabs__item {
		position: relative;
		display: flex;
		flex-shrink: 0;
		align-items: center;
		justify-content: center;
		height: 72rpx;
		margin-right: 42rpx;
		font-size: 28rpx;
		color: $text-title;
	}

	.second-tabs__item--active {
		color: $brand-primary;
		font-weight: 700;
	}

	.second-tabs__line {
		position: absolute;
		left: 6rpx;
		right: 6rpx;
		bottom: 2rpx;
		height: 4rpx;
		border-radius: 999rpx;
		background: $brand-primary;
	}

	.product-expand__body {
		display: flex;
		flex: 1;
		min-height: 0;
		background: #ffffff;
	}

	.third-list {
		width: 160rpx;
		flex-shrink: 0;
		height: 100%;
		background: #f5f5f5;
	}

	.third-list__item {
		position: relative;
		display: flex;
		align-items: center;
		min-height: 92rpx;
		padding: 0 24rpx;
		font-size: 26rpx;
		line-height: 36rpx;
		color: $text-title;
		background: #f5f5f5;
	}

	.third-list__item--active {
		color: $brand-primary;
		font-weight: 700;
		background: #ffffff;
	}

	.third-list__item--active::before {
		content: '';
		position: absolute;
		left: 0;
		top: 32rpx;
		width: 6rpx;
		height: 30rpx;
		border-radius: 999rpx;
		background: $brand-primary;
	}

	.goods-scroll {
		flex: 1;
		min-width: 0;
		height: 100%;
		background: #ffffff;
	}

	.goods-scroll__inner {
		padding: 10rpx 28rpx 36rpx;
	}

	.goods-card {
		display: flex;
		min-height: 190rpx;
		padding: 22rpx 0;
		border-bottom: 1rpx solid #f1f1f1;
	}

	.goods-card__cover-box {
		flex-shrink: 0;
		width: 126rpx;
		height: 150rpx;
		border-radius: 12rpx;
		background: #f7f7f7;
		overflow: hidden;
	}

	.goods-card__cover,
	.goods-card__cover-placeholder {
		width: 100%;
		height: 100%;
	}

	.goods-card__cover-placeholder {
		display: flex;
		align-items: center;
		justify-content: center;
		background: $brand-primary-pale;
		color: $brand-primary;
		font-size: 34rpx;
		font-weight: 700;
	}

	.goods-card__info {
		display: flex;
		flex: 1;
		min-width: 0;
		flex-direction: column;
		margin-left: 28rpx;
	}

	.goods-card__name {
		height: 42rpx;
		font-size: 28rpx;
		line-height: 42rpx;
		font-weight: 700;
		color: $text-title;
		overflow: hidden;
		white-space: nowrap;
		text-overflow: ellipsis;
	}

	.goods-card__tags {
		display: flex;
		align-items: center;
		height: 34rpx;
		margin-top: 10rpx;
	}

	.goods-card__tag {
		height: 34rpx;
		margin-right: 10rpx;
		padding: 0 10rpx;
		border-radius: 6rpx;
		background: #f2f2f2;
		font-size: 20rpx;
		line-height: 34rpx;
		color: $text-secondary;
	}

	.goods-card__bottom {
		display: flex;
		align-items: flex-end;
		justify-content: space-between;
		margin-top: auto;
	}

	.goods-card__price {
		font-size: 30rpx;
		line-height: 42rpx;
		font-weight: 800;
		color: $brand-primary;
	}

	.goods-card__cart {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 54rpx;
		height: 54rpx;
		border-radius: 50%;
		color: $brand-primary;
		font-size: 34rpx;
	}

	.goods-more {
		display: flex;
		align-items: center;
		justify-content: center;
		height: 64rpx;
		font-size: 22rpx;
		color: $text-secondary;
	}

	.category-state {
		display: flex;
		min-height: 360rpx;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		padding: 48rpx 24rpx;
		text-align: center;
	}

	.category-state__title {
		font-size: 28rpx;
		line-height: 40rpx;
		font-weight: 700;
		color: $text-title;
	}

	.category-state__text {
		margin-top: 10rpx;
		font-size: 24rpx;
		line-height: 34rpx;
		color: $text-secondary;
	}
</style>
