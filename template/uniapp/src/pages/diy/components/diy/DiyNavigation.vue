<template>
	<view class="diy-navigation" :style="componentStyleText">
		<view class="nav-grid">
			<view v-for="(item, index) in visibleList" :key="index" class="nav-item" :style="itemStyleText"
				@click="handleClick(item)">
				<view class="nav-icon-wrap" :style="item.iconWrapStyle">
					<image v-if="item.icon" class="nav-icon" :src="item.icon" mode="aspectFill"
						:style="imageRadiusStyleText" />
					<text v-else class="nav-icon-fallback"
						:style="item.fallbackIconStyle">{{ item.fallbackIcon }}</text>
				</view>
				<text v-if="showTitle" class="nav-text" :style="item.textStyle">{{ item.name }}</text>
				<text v-if="item.badge_text" class="nav-badge">{{ item.badge_text }}</text>
			</view>
		</view>
	</view>
</template>

<script setup>
	import { computed } from 'vue'
	import {
		navigateByLink
	} from '@/utils/link'
	import {
		getImageRadiusStyle,
		toRpxUnit
	} from '@/utils/diy_adapter'

	defineOptions({
		name: 'DiyNavigation'
	})

	const props = defineProps({
		config: {
			type: Object,
			default: () => ({})
		},
		data: {
			type: Array,
			default: () => []
		}
	})

	const navList = computed(() => {
		const source = props.data.length > 0 ? props.data : (props.config.items || [])
		return Array.isArray(source) ? source.filter((item) => item && (item.name || item.icon)) : []
	})

	const columns = computed(() => {
		const value = Number(props.config.columns || 5)
		if (!Number.isFinite(value)) return 5
		return Math.min(Math.max(value, 1), 6)
	})

	const maxRows = computed(() => {
		const value = Number(props.config.max_rows || 2)
		return Number.isFinite(value) && value > 0 ? value : 2
	})

	const visibleList = computed(() => {
		const styleConfig = props.config.style_config || {}
		const iconBgColor = styleConfig.icon_bg_color || props.config.icon_bg_color || ''
		const textColor = props.config.text_color || ''

		return navList.value.slice(0, columns.value * maxRows.value).map((item) => ({
			...item,
			iconWrapStyle: buildBackgroundStyleText(item.icon_bg_color || iconBgColor),
			fallbackIcon: getFallbackIcon(item.name),
			fallbackIconStyle: item.icon_color ? `color: ${item.icon_color};` : '',
			textStyle: textColor ? `color: ${textColor};` : ''
		}))
	})

	const showTitle = computed(() => {
		const value = props.config.show_title !== undefined ? props.config.show_title : props.config.show_text
		if (value === undefined || value === null || value === '') return true
		if (value === true || value === 'true' || value === 1 || value === '1') return true
		if (value === false || value === 'false' || value === 0 || value === '0') return false
		return Boolean(value)
	})

	const componentStyleText = computed(() => {
		return buildBackgroundStyleText(props.config.bg_color)
	})

	const itemStyleText = computed(() => {
		const width = `${100 / columns.value}%`
		return `width: ${width}; flex-basis: ${width}; max-width: ${width}; border-radius: ${toRpxUnit(props.config.card_radius, 0)};`
	})

	const imageRadiusStyleText = computed(() => {
		return toStyleText(getImageRadiusStyle(props.config.style_config))
	})

	function buildBackgroundStyleText(color = '') {
		return color ? `background-color: ${color};` : ''
	}

	function getFallbackIcon(name = '') {
		return String(name || '导').slice(0, 1)
	}

	function toStyleText(style = {}) {
		return Object.keys(style).map((key) => {
			const cssKey = key.replace(/[A-Z]/g, (match) => `-${match.toLowerCase()}`)
			return `${cssKey}: ${style[key]};`
		}).join(' ')
	}

	function handleClick(item = {}) {
		if (!navigateByLink(item.link_url)) {
			uni.showToast({
				title: '链接未配置',
				icon: 'none'
			})
		}
	}
</script>

<style lang="scss" scoped>
	.diy-navigation {
		width: auto;
		box-sizing: border-box;
	}

	.nav-grid {
		display: flex;
		flex-wrap: wrap;
		width: 100%;
	}

	.nav-item {
		position: relative;
		display: flex;
		flex-direction: column;
		align-items: center;
		min-width: 0;
		padding-top: 14rpx;
		padding-bottom: 16rpx;
		box-sizing: border-box;
	}

	.nav-icon-wrap {
		width: 96rpx;
		height: 96rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		overflow: hidden;
	}

	.nav-icon {
		width: 100%;
		height: 100%;
	}

	.nav-icon-fallback {
		font-size: 30rpx;
		font-weight: 600;
		line-height: 96rpx;
	}

	.nav-text {
		width: 100%;
		margin-top: 12rpx;
		padding-left: 6rpx;
		padding-right: 6rpx;
		box-sizing: border-box;
		font-size: 22rpx;
		line-height: 32rpx;
		text-align: center;
		overflow: hidden;
		white-space: nowrap;
		text-overflow: ellipsis;
	}

	.nav-badge {
		margin-top: 6rpx;
		padding-left: 10rpx;
		padding-right: 10rpx;
		border-radius: 18rpx;
		background: #ffefe7;
		color: var(--brand-primary, #ea580c);
		font-size: 18rpx;
		line-height: 28rpx;
	}
</style>
