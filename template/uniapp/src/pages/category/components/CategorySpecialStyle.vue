<template>
	<view class="category-special">
		<view class="category-header">
			<text class="category-header__title">分类</text>
			<view class="category-search">
				<text class="iconfont iconsousuo category-search__icon"></text>
				<input
					v-model="keyword"
					class="category-search__input"
					placeholder="搜索专题"
					placeholder-class="category-search__placeholder"
					confirm-type="search"
					@confirm="handleSearch"
				/>
			</view>
		</view>

		<view class="category-special__body">
			<scroll-view scroll-y class="category-sidebar" :show-scrollbar="false">
				<view class="category-sidebar__inner">
					<view
						v-for="item in rootCategories"
						:key="item.id"
						class="category-sidebar__item"
						:class="{ 'category-sidebar__item--active': item.id === activeRootId }"
						@click="handleRootChange(item)"
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
					<text class="category-state__text">后台配置分类后将在这里展示</text>
				</view>

				<view v-else class="category-main__inner">
					<view class="special-banner">
						<image v-if="activeRootBanner" class="special-banner__image" :src="activeRootBanner" mode="aspectFill" />
						<view v-else class="special-banner__placeholder">
							<text>{{ activeRootName }}</text>
						</view>
					</view>

					<view class="category-group">
						<view class="category-group__head">
							<text class="category-group__title">{{ activeRootName }}</text>
							<text class="category-group__more" @click="openSpecialList(activeRootId)">查看更多 ></text>
						</view>

						<view v-if="childCategories.length" class="category-grid">
							<view
								v-for="item in childCategories"
								:key="item.id"
								class="category-grid__item"
								@click="openSpecialList(item.id)"
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
				</view>
			</scroll-view>
		</view>
	</view>
</template>

<script setup>
	import { computed, ref, watch } from 'vue'
	import { getSpecialCategoryTree } from '@/api/category'

	defineOptions({
		name: 'CategorySpecialStyle'
	})

	const props = defineProps({
		categoryIds: {
			type: Array,
			default: () => []
		}
	})

	const loading = ref(false)
	const keyword = ref('')
	const rootCategories = ref([])
	const activeRootId = ref(0)
	const DEFAULT_CATEGORY_IMAGE = '/static/images/category-default.png'

	const categoryIdsParam = computed(() => props.categoryIds.join(','))
	const activeRoot = computed(() => rootCategories.value.find((item) => item.id === activeRootId.value) || rootCategories.value[0] || null)
	const activeRootName = computed(() => activeRoot.value ? activeRoot.value.name : '专题分类')
	const activeRootBanner = computed(() => activeRoot.value ? (activeRoot.value.banner_image || activeRoot.value.image || DEFAULT_CATEGORY_IMAGE) : DEFAULT_CATEGORY_IMAGE)
	const childCategories = computed(() => {
		return activeRoot.value && Array.isArray(activeRoot.value.children) ? activeRoot.value.children : []
	})

	watch(categoryIdsParam, () => {
		fetchCategories()
	}, { immediate: true })

	async function fetchCategories() {
		loading.value = true
		try {
			const res = await getSpecialCategoryTree({ category_ids: categoryIdsParam.value }, { showError: false })
			rootCategories.value = res.code === 200 && Array.isArray(res.data) ? normalizeCategoryList(res.data) : []
			activeRootId.value = rootCategories.value[0] ? rootCategories.value[0].id : 0
		} catch (error) {
			rootCategories.value = []
			activeRootId.value = 0
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
				initial: name ? name.slice(0, 1) : '专',
				children: Array.isArray(item.children) ? normalizeCategoryList(item.children) : []
			}
		}).filter((item) => item.id > 0)
	}

	function normalizeCategoryImage(value = '') {
		const image = String(value || '').trim()
		return image || DEFAULT_CATEGORY_IMAGE
	}

	function handleRootChange(item) {
		if (!item || item.id === activeRootId.value) {
			return
		}
		activeRootId.value = item.id
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
			url: `/pages/contents/special/index?keyword=${encodeURIComponent(searchKeyword)}`
		})
	}

	function openSpecialList(categoryId = 0) {
		const id = Number(categoryId || 0)
		const query = id > 0 ? `?category_id=${id}` : ''
		uni.navigateTo({
			url: `/pages/contents/special/index${query}`
		})
	}
</script>

<style lang="scss" scoped>
	.category-special {
		display: flex;
		flex: 1;
		flex-direction: column;
		min-height: 0;
		background: #ffffff;
	}

	.category-header {
		flex-shrink: 0;
		padding: 18rpx 32rpx 20rpx;
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

	.category-special__body {
		display: flex;
		flex: 1;
		min-height: 0;
		background: #ffffff;
	}

	.category-sidebar {
		width: 188rpx;
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
		min-height: 92rpx;
		padding: 0 26rpx;
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
		line-height: 36rpx;
	}

	.category-main {
		flex: 1;
		min-width: 0;
		height: 100%;
		background: #ffffff;
	}

	.category-main__inner {
		padding: 0 24rpx 36rpx;
	}

	.special-banner {
		height: 178rpx;
		margin-top: 18rpx;
		border-radius: 20rpx;
		background: $brand-primary-pale;
		overflow: hidden;
	}

	.special-banner__image,
	.special-banner__placeholder {
		width: 100%;
		height: 100%;
	}

	.special-banner__placeholder {
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 32rpx;
		font-weight: 700;
		color: $brand-primary;
	}

	.category-group {
		margin-top: 28rpx;
	}

	.category-group__head {
		display: flex;
		align-items: center;
		justify-content: space-between;
		height: 42rpx;
		margin-bottom: 22rpx;
	}

	.category-group__title {
		font-size: 30rpx;
		line-height: 42rpx;
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
		margin: -10rpx -8rpx 0;
	}

	.category-grid__item {
		width: 33.3333%;
		padding: 10rpx 8rpx 18rpx;
		box-sizing: border-box;
	}

	.category-grid__image-box {
		width: 104rpx;
		height: 104rpx;
		margin: 0 auto;
		border-radius: 28rpx;
		background: #f7f7f7;
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
		font-size: 32rpx;
		font-weight: 700;
		color: $brand-primary;
		background: $brand-primary-pale;
	}

	.category-grid__name {
		display: block;
		height: 34rpx;
		margin-top: 12rpx;
		font-size: 24rpx;
		line-height: 34rpx;
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
