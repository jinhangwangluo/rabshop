<template>
	<page-meta :page-style="themePageStyle"></page-meta>
	<view class="category-page">
		<app-safe-top background="#ffffff" :auto-back="false" />

		<view v-if="pageLoading" class="category-page__state">
			<six-petal-spiral
				:active="true"
				:size="92"
				text=""
				:color="loadingColor"
				:stroke-width="1.3"
				:particle-count="36"
				:trail-span="0.28"
			/>
			<text class="category-page__state-text">加载中...</text>
		</view>

		<view v-else-if="loadFailed" class="category-page__state">
			<text class="category-page__state-title">分类配置加载失败</text>
			<text class="category-page__state-text">请稍后重试</text>
			<view class="category-page__retry" @click="fetchConfig">
				<text>重新加载</text>
			</view>
		</view>

		<template v-else>
			<category-special-style
				v-if="resolvedStyleType === 'special'"
				:category-ids="categoryIds"
			/>
			<product-category-tree
				v-else-if="resolvedStyleType === 'product_tabs'"
				mode="tabs"
				:category-ids="categoryIds"
			/>
			<product-category-expand
				v-else-if="resolvedStyleType === 'product_expand'"
				:category-ids="categoryIds"
			/>
			<product-category-tree
				v-else
				mode="grid"
				:category-ids="categoryIds"
			/>
		</template>
	</view>
</template>

<script setup>
import { themeState } from '@/utils/theme'
	import { computed, ref } from 'vue'
	import { onLoad } from '@dcloudio/uni-app'
	import { getCategoryPageConfig } from '@/api/category'
	import CategorySpecialStyle from './components/CategorySpecialStyle.vue'
	import ProductCategoryTree from './components/ProductCategoryTree.vue'
	import ProductCategoryExpand from './components/ProductCategoryExpand.vue'

	defineOptions({
		name: 'CategoryTabPage'
	})

	const VALID_STYLE_TYPES = ['special', 'product_grid', 'product_tabs', 'product_expand']
	const loadingColor = computed(() => themeState.primary_color)
	const pageLoading = ref(true)
	const loadFailed = ref(false)
	const styleType = ref('product_grid')
	const categoryIds = ref([])

	const resolvedStyleType = computed(() => {
		return VALID_STYLE_TYPES.includes(styleType.value) ? styleType.value : 'product_grid'
	})

	onLoad(() => {
		fetchConfig()
	})

	async function fetchConfig() {
		pageLoading.value = true
		loadFailed.value = false

		try {
			const res = await getCategoryPageConfig({}, { showError: false })
			if (res.code !== 200) {
				throw new Error(res.msg || '分类配置加载失败')
			}

			const data = res.data || {}
			const config = data.config || {}
			styleType.value = VALID_STYLE_TYPES.includes(data.style_type) ? data.style_type : 'product_grid'
			categoryIds.value = normalizeCategoryIds(config.category_ids)
		} catch (error) {
			loadFailed.value = true
			styleType.value = 'product_grid'
			categoryIds.value = []
		} finally {
			pageLoading.value = false
		}
	}

	function normalizeCategoryIds(value = []) {
		const source = Array.isArray(value) ? value : String(value || '').split(',')
		return source
			.map((item) => Number(item || 0))
			.filter((id, index, list) => id > 0 && list.indexOf(id) === index)
	}
</script>

<style lang="scss">
	page {
		height: 100%;
		background: #ffffff;
	}
</style>

<style lang="scss" scoped>
	.category-page {
		display: flex;
		flex-direction: column;
		height: 100%;
		min-height: 100%;
		background: #ffffff;
	}

	.category-page__state {
		display: flex;
		flex: 1;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		padding: 64rpx 48rpx;
		text-align: center;
	}

	.category-page__state-title {
		font-size: 30rpx;
		line-height: 42rpx;
		font-weight: 700;
		color: $text-title;
	}

	.category-page__state-text {
		margin-top: 16rpx;
		font-size: 24rpx;
		line-height: 34rpx;
		color: $text-secondary;
	}

	.category-page__retry {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 176rpx;
		height: 64rpx;
		margin-top: 28rpx;
		border-radius: 999rpx;
		background: $brand-primary;
		color: #ffffff;
		font-size: 24rpx;
		font-weight: 700;
	}
</style>
