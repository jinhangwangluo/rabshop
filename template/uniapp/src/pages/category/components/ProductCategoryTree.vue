<template>
	<view class="product-category" :class="productCategoryClass">
		<view class="category-header">
			<text class="category-header__title">分类</text>
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

		<scroll-view
			v-if="showTopTabs"
			scroll-x
			class="top-tabs"
			:show-scrollbar="false"
		>
			<view class="top-tabs__track">
				<view
					v-for="item in rootCategories"
					:key="item.id"
					class="top-tabs__item"
					:class="{ 'top-tabs__item--active': item.id === activeRootId }"
					@click="handleRootChange(item)"
				>
					<text class="top-tabs__text">{{ item.name }}</text>
					<view v-if="item.id === activeRootId" class="top-tabs__line"></view>
				</view>
			</view>
		</scroll-view>

		<view class="product-category__body">
			<scroll-view scroll-y class="category-sidebar" :show-scrollbar="false">
				<view class="category-sidebar__inner">
					<view
						v-for="item in sidebarCategories"
						:key="item.id"
						class="category-sidebar__item"
						:class="{ 'category-sidebar__item--active': item.id === activeSidebarId }"
						@click="handleSidebarChange(item)"
					>
						<text class="category-sidebar__text">{{ item.name }}</text>
					</view>
				</view>
			</scroll-view>

			<scroll-view scroll-y class="category-main" :show-scrollbar="false">
				<view v-if="loading" class="category-state">
					<six-petal-spiral :active="true" :size="80" text="" :color="themePrimaryColor" />
				</view>

				<view v-else-if="!rootCategories.length" class="category-state">
					<text class="category-state__title">暂无分类</text>
					<text class="category-state__text">后台配置商品分类后将在这里展示</text>
				</view>

				<view v-else class="category-main__inner">
					<view v-if="isTabsMode" class="category-group">
						<view class="category-group__head">
							<text class="category-group__title">{{ activeSidebarName }}</text>
						</view>
						<view v-if="tabsGridItems.length" class="category-grid">
							<view
								v-for="item in tabsGridItems"
								:key="item.id"
								class="category-grid__item"
								@click="openProductCategory(item.id)"
							>
								<view class="category-grid__image-box">
									<image v-if="item.image" class="category-grid__image" :src="item.image" mode="aspectFill" />
									<view v-else class="category-grid__fallback">
										<text>{{ item.initial }}</text>
									</view>
								</view>
								<text class="category-grid__name">{{ item.name }}</text>
							</view>
						</view>
						<view v-else class="category-state category-state--panel">
							<text class="category-state__text">当前分类暂无子分类</text>
						</view>
					</view>

					<template v-else>
						<view
							v-for="group in gridGroups"
							:key="group.id"
							class="category-group"
						>
							<view class="category-group__head">
								<text class="category-group__title">{{ group.name }}</text>
								<text v-if="group.showMore" class="category-group__more" @click="openProductCategory(group.id)">查看更多 ></text>
							</view>
							<view class="category-grid">
								<view
									v-for="item in group.items"
									:key="item.id"
									class="category-grid__item"
									@click="openProductCategory(item.id)"
								>
									<view class="category-grid__image-box">
										<image v-if="item.image" class="category-grid__image" :src="item.image" mode="aspectFill" />
										<view v-else class="category-grid__fallback">
											<text>{{ item.initial }}</text>
										</view>
									</view>
									<text class="category-grid__name">{{ item.name }}</text>
								</view>
							</view>
						</view>
					</template>
				</view>
			</scroll-view>
		</view>
	</view>
</template>

<script setup>
	import { computed, ref, watch } from 'vue'
	import { getProductCategories } from '@/api/product'

	defineOptions({
		name: 'ProductCategoryTree'
	})

	const props = defineProps({
		mode: {
			type: String,
			default: 'grid'
		},
		categoryIds: {
			type: Array,
			default: () => []
		}
	})

	const loading = ref(false)
	const keyword = ref('')
	const rootCategories = ref([])
	const activeRootId = ref(0)
	const activeSidebarId = ref(0)
	const DEFAULT_CATEGORY_IMAGE = '/static/images/category-default.png'

	const isTabsMode = computed(() => props.mode === 'tabs')
	const productCategoryClass = computed(() => ({
		'product-category--grid': !isTabsMode.value,
		'product-category--tabs': isTabsMode.value
	}))
	const categoryIdsParam = computed(() => props.categoryIds.join(','))
	const showTopTabs = computed(() => rootCategories.value.length > 0)
	const activeRoot = computed(() => rootCategories.value.find((item) => item.id === activeRootId.value) || rootCategories.value[0] || null)
	const activeRootName = computed(() => activeRoot.value ? activeRoot.value.name : '推荐分类')
	const secondCategories = computed(() => activeRoot.value && Array.isArray(activeRoot.value.children) ? activeRoot.value.children : [])
	const sidebarCategories = computed(() => secondCategories.value.length ? secondCategories.value : rootCategories.value)
	const activeSidebar = computed(() => sidebarCategories.value.find((item) => item.id === activeSidebarId.value) || sidebarCategories.value[0] || null)
	const activeSidebarName = computed(() => activeSidebar.value ? activeSidebar.value.name : activeRootName.value)
	const tabsGridItems = computed(() => {
		if (!activeSidebar.value) {
			return []
		}
		return activeSidebar.value.children.length ? activeSidebar.value.children : [activeSidebar.value]
	})
	const gridGroups = computed(() => {
		const groups = secondCategories.value.length ? secondCategories.value : (activeRoot.value ? [activeRoot.value] : [])
		return groups.map((group) => ({
			id: group.id,
			name: group.name,
			showMore: group.id !== activeSidebarId.value,
			items: group.children.length ? group.children : [group]
		}))
	})

	watch(categoryIdsParam, () => {
		fetchCategories()
	}, { immediate: true })

	async function fetchCategories() {
		loading.value = true
		try {
			const res = await getProductCategories({ category_ids: categoryIdsParam.value }, { showError: false })
			rootCategories.value = res.code === 200 && Array.isArray(res.data) ? normalizeCategoryList(res.data) : []
			activeRootId.value = rootCategories.value[0] ? rootCategories.value[0].id : 0
			resetSidebarSelection()
		} catch (error) {
			rootCategories.value = []
			activeRootId.value = 0
			activeSidebarId.value = 0
		} finally {
			loading.value = false
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

	function resetSidebarSelection() {
		const first = sidebarCategories.value[0]
		activeSidebarId.value = first ? first.id : 0
	}

	function handleRootChange(item) {
		if (!item || item.id === activeRootId.value) {
			return
		}
		activeRootId.value = item.id
		resetSidebarSelection()
	}

	function handleSidebarChange(item) {
		if (!item || item.id === activeSidebarId.value) {
			return
		}

		if (isTabsMode.value || item.pid === activeRootId.value) {
			activeSidebarId.value = item.id
			return
		}

		activeRootId.value = item.id
		resetSidebarSelection()
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

	function openProductCategory(categoryId = 0) {
		const id = Number(categoryId || 0)
		if (id <= 0) {
			return
		}
		uni.navigateTo({
			url: `/pages/goods/list/index?category_id=${id}`
		})
	}
</script>

<style lang="scss" scoped>
	.product-category {
		display: flex;
		flex: 1;
		flex-direction: column;
		min-height: 0;
		background: #ffffff;
	}

	.category-header {
		flex-shrink: 0;
		padding: 18rpx 32rpx 18rpx;
		background: #ffffff;
	}

	.category-header__title {
		display: block;
		height: 48rpx;
		font-size: 34rpx;
		line-height: 48rpx;
		font-weight: 700;
		color: $text-title;
		text-align: center;
	}

	.category-search {
		display: flex;
		align-items: center;
		height: 72rpx;
		margin-top: 22rpx;
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

	.top-tabs {
		flex-shrink: 0;
		height: 86rpx;
		background: #ffffff;
		border-bottom: 1rpx solid #f4f4f4;
		white-space: nowrap;
	}

	.top-tabs__track {
		display: flex;
		align-items: center;
		height: 86rpx;
		padding: 0 32rpx;
	}

	.top-tabs__item {
		position: relative;
		display: flex;
		flex-shrink: 0;
		align-items: center;
		justify-content: center;
		height: 86rpx;
		margin-right: 50rpx;
		color: $text-title;
	}

	.top-tabs__item--active {
		color: $brand-primary;
		font-weight: 700;
	}

	.top-tabs__text {
		font-size: 28rpx;
		line-height: 40rpx;
	}

	.top-tabs__line {
		position: absolute;
		left: 10rpx;
		right: 10rpx;
		bottom: 6rpx;
		height: 4rpx;
		border-radius: 999rpx;
		background: $brand-primary;
	}

	.product-category__body {
		display: flex;
		flex: 1;
		min-height: 0;
		background: #ffffff;
	}

	.category-sidebar {
		width: 184rpx;
		flex-shrink: 0;
		height: 100%;
		background: #f5f5f5;
	}

	.category-sidebar__inner {
		padding-bottom: 32rpx;
		background: #f5f5f5;
	}

	.category-sidebar__item {
		position: relative;
		display: flex;
		align-items: center;
		min-height: 98rpx;
		padding: 0 28rpx;
		background: #f5f5f5;
		color: $text-title;
	}

	.category-sidebar__item--active {
		background: #ffffff;
		color: $brand-primary;
		font-weight: 700;
	}

	.category-sidebar__item--active::before {
		content: '';
		position: absolute;
		left: 0;
		top: 32rpx;
		width: 6rpx;
		height: 30rpx;
		border-radius: 999rpx;
		background: $brand-primary;
	}

	.category-sidebar__text {
		font-size: 26rpx;
		line-height: 38rpx;
	}

	.category-main {
		flex: 1;
		min-width: 0;
		height: 100%;
		background: #ffffff;
	}

	.category-main__inner {
		padding: 22rpx 28rpx 40rpx;
	}

	.category-group {
		margin-bottom: 38rpx;
	}

	.category-group__head {
		display: flex;
		align-items: center;
		justify-content: space-between;
		height: 44rpx;
		margin-bottom: 20rpx;
	}

	.category-group__title {
		font-size: 30rpx;
		line-height: 44rpx;
		font-weight: 700;
		color: $text-title;
	}

	.category-group__more {
		font-size: 24rpx;
		line-height: 34rpx;
		color: $text-secondary;
	}

	.category-grid {
		display: flex;
		flex-wrap: wrap;
		margin: -8rpx -10rpx 0;
	}

	.category-grid__item {
		width: 33.3333%;
		padding: 8rpx 10rpx 22rpx;
		box-sizing: border-box;
	}

	.category-grid__image-box {
		width: 108rpx;
		height: 108rpx;
		margin: 0 auto;
		border-radius: 30rpx;
		background: #fdf5ec;
		overflow: hidden;
	}

	.category-grid__image,
	.category-grid__fallback {
		width: 100%;
		height: 100%;
	}

	.category-grid__fallback {
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 34rpx;
		font-weight: 700;
		color: $brand-primary;
		background: $brand-primary-pale;
	}

	.category-grid__name {
		display: block;
		height: 36rpx;
		margin-top: 12rpx;
		font-size: 24rpx;
		line-height: 36rpx;
		color: $text-title;
		text-align: center;
		overflow: hidden;
		white-space: nowrap;
		text-overflow: ellipsis;
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

	.category-state--panel {
		min-height: 240rpx;
		background: #ffffff;
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
