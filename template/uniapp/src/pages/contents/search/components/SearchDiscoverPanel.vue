<template>
	<view class="discover-panel">
		<view class="discover-panel__card">
			<view class="discover-panel__section-head">
				<text class="discover-panel__title">最近搜索</text>
				<view class="discover-panel__clear" @click="$emit('clear-history')" v-if="history.length">
					<image class="discover-panel__clear-icon" src="/static/images/trash.png" mode="aspectFit"></image>
				</view>
			</view>

			<view v-if="history.length" class="discover-panel__history-list">
				<view v-for="item in history" :key="item.search" class="discover-panel__history-item"
					@click="$emit('history-click', item.search)">
					<text class="discover-panel__history-text">{{ item.search }}</text>
				</view>
			</view>
			<view v-else class="discover-panel__empty-box">
				<text class="discover-panel__empty-text">暂无搜索记录</text>
			</view>
		</view>

		<view class="discover-panel__card discover-panel__card--hot">
			<view class="discover-panel__section-head discover-panel__section-head--tight">
				<view class="discover-panel__title-wrap">
					<text class="discover-panel__title">热门搜索</text>
					<view class="discover-panel__fire">
						<image class="discover-panel__hot-badge-icon" src="../../static/search-hot.png"
							mode="aspectFit"></image>
					</view>
				</view>
			</view>

			<view class="discover-panel__hot-list">
				<view v-for="(item, index) in displayHotKeywords" :key="item.keyword" class="discover-panel__hot-item"
					@click="$emit('hot-click', item.keyword)">
					<text class="discover-panel__hot-rank" :class="{ 'is-top': index < 3 }">{{ index + 1 }}</text>
					<text class="discover-panel__hot-keyword">{{ item.keyword }}</text>
					<text v-if="item.searchCountText"
						class="discover-panel__hot-count">{{ item.searchCountText }}</text>
					<image v-if="item.badgeIcon" class="discover-panel__hot-badge-icon" :src="item.badgeIcon"
						mode="aspectFit"></image>
				</view>
			</view>
		</view>

		<view class="discover-panel__discover">
			<view class="discover-panel__section-head discover-panel__section-head--discover">
				<text class="discover-panel__title">发现内容</text>
			</view>
			<search-waterfall-grid :items="discoverItems" @select="handleDiscoverSelect"></search-waterfall-grid>
		</view>
	</view>
</template>

<script setup>
	import { computed } from 'vue'
	import SearchWaterfallGrid from './SearchWaterfallGrid.vue'
	import hotBadgeIconHot from '../../static/search-hot.png'
	import hotBadgeIconTrend from '../../static/search-trend.png'
	import hotBadgeIconNew from '../../static/search-new.png'

	defineOptions({
		name: 'SearchDiscoverPanel'
	})

	const HOT_BADGE_ICONS = {
		'is-hot': hotBadgeIconHot,
		'is-trend': hotBadgeIconTrend,
		'is-new': hotBadgeIconNew
	}

	const props = defineProps({
		history: {
			type: Array,
			default: () => []
		},
		hotKeywords: {
			type: Array,
			default: () => []
		},
		discoverItems: {
			type: Array,
			default: () => []
		}
	})

	const emit = defineEmits(['clear-history', 'history-click', 'hot-click', 'discover-click'])

	const displayHotKeywords = computed(() => {
		return props.hotKeywords.map(item => ({
			...item,
			badgeIcon: HOT_BADGE_ICONS[item.badgeClassName] || ''
		}))
	})

	function handleDiscoverSelect(item) {
		emit('discover-click', item)
	}
</script>

<style lang="scss" scoped>
	.discover-panel__card {
		margin-bottom: 40rpx;
		padding: 0;
		background: transparent;
		box-shadow: none;
	}

	.discover-panel__section-head {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin-bottom: 22rpx;
	}

	.discover-panel__section-head--tight {
		margin-bottom: 16rpx;
	}

	.discover-panel__section-head--discover {
		margin-bottom: 20rpx;
	}

	.discover-panel__title-wrap {
		display: flex;
		align-items: center;
	}

	.discover-panel__title {
		font-size: 32rpx;
		line-height: 44rpx;
		font-weight: 700;
		color: #1a1c1c;
	}

	.discover-panel__fire {
		margin-left: 10rpx;
		font-size: 28rpx;
		line-height: 1;
	}

	.discover-panel__clear {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 48rpx;
		height: 48rpx;
	}

	.discover-panel__clear-icon {
		width: 28rpx;
		height: 28rpx;
	}

	.discover-panel__history-list {
		display: flex;
		flex-wrap: wrap;
		margin: -8rpx;
	}

	.discover-panel__history-item {
		margin: 8rpx;
		padding: 12rpx 24rpx;
		border: 1rpx solid rgba(207, 196, 197, 0.2);
		border-radius: 999rpx;
		background: #e8e8e8;
		display: flex;
	}

	.discover-panel__history-text {
		font-size: 22rpx;
		line-height: 30rpx;
		color: #4c4546;
	}

	.discover-panel__empty-box {
		display: flex;
		align-items: center;
		justify-content: center;
		border-radius: 20rpx;
	}

	.discover-panel__empty-text {
		font-size: 24rpx;
		line-height: 34rpx;
		color: #848484;
	}

	.discover-panel__hot-item {
		display: flex;
		align-items: center;
		min-height: 84rpx;
		border-top: 1rpx solid #f3f4f6;
	}

	.discover-panel__hot-item:first-child {
		border-top: 0;
	}

	.discover-panel__hot-rank {
		width: 34rpx;
		font-size: 28rpx;
		line-height: 40rpx;
		font-weight: 700;
		color: #5b5f64;
	}

	.discover-panel__hot-rank.is-top {
		color: var(--brand-primary, #ea580c);
	}

	.discover-panel__hot-keyword {
		flex: 1;
		margin-left: 16rpx;
		font-size: 28rpx;
		line-height: 40rpx;
		color: #1a1c1c;
	}

	.discover-panel__hot-count {
		flex-shrink: 0;
		margin-left: 16rpx;
		font-size: 22rpx;
		line-height: 32rpx;
		color: #848484;
	}

	.discover-panel__hot-badge-icon {
		flex-shrink: 0;
		width: 40rpx;
		height: 40rpx;
		margin-left: 16rpx;
	}
</style>
