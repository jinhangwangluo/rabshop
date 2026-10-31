<template>
	<view v-if="visible" class="app-floating-button" :class="{
      'app-floating-button--expanded': expanded,
      'app-floating-button--compact': compact
    }" :style="floatingButtonStyle">
		<view class="app-floating-button__actions">
			<view class="app-floating-button__arc"></view>
			<view v-for="item in displayActions" :key="item.key" :class="[
          'app-floating-button__action',
          item.className,
          { 'app-floating-button__action--active': expanded }
        ]" hover-class="app-floating-button__action--hover" @click.stop="handleAction(item)">
				<view class="app-floating-button__label">
					<text class="app-floating-button__label-text">{{ item.text }}</text>
				</view>
				<view class="app-floating-button__icon">
					<image class="app-floating-button__icon-image" :src="item.image" mode="aspectFit"></image>
				</view>
			</view>
		</view>

		<view class="app-floating-button__main" hover-class="app-floating-button__main--hover"
			@touchstart.stop="handleMainTouchStart" @touchmove.stop.prevent="handleMainTouchMove"
			@touchend.stop="handleMainTouchEnd" @touchcancel.stop="handleMainTouchEnd" @click.stop="toggle">
			<image class="app-floating-button__main-icon" :src="mainIcon" mode="aspectFit"></image>
		</view>
	</view>
</template>

<script setup>
	import { computed, onBeforeUnmount, ref, watch } from 'vue'
	import FLOATING_COLLAPSE_ICON from '@/static/floating-collapse.png'
	import FLOATING_EXPAND_ICON from '@/static/floating-expand.png'
	import HOME_ACTION_ICON from '@/static/tarbar/home_select.png'
	import CATEGORY_ACTION_ICON from '@/static/tarbar/category_select.png'
	import CART_ACTION_ICON from '@/static/tarbar/cart_select.png'
	import USER_ACTION_ICON from '@/static/tarbar/user_select.png'

	const DEFAULT_ACTIONS = Object.freeze([{
			key: 'home',
			text: '首页',
			image: HOME_ACTION_ICON,
			url: '/pages/index/index'
		},
		{
			key: 'category',
			text: '分类',
			image: CATEGORY_ACTION_ICON,
			url: '/pages/category/index'
		},
		{
			key: 'cart',
			text: '购物车',
			image: CART_ACTION_ICON,
			url: '/pages/cart/index'
		},
		{
			key: 'user',
			text: '我的',
			image: USER_ACTION_ICON,
			url: '/pages/user/index'
		}
	])

	const TAB_BAR_URLS = Object.freeze([
		'/pages/index/index',
		'/pages/category/index',
		'/pages/cart/index',
		'/pages/user/index'
	])

	const DRAG_THRESHOLD = 6
	const COMPACT_SWIPE_THRESHOLD = 28
	const FLOATING_TYPE_STORAGE_KEY = 'app_floating_button_type'
	const FLOATING_TYPE_NORMAL = 'normal'
	const FLOATING_TYPE_COMPACT = 'compact'

	defineOptions({
		name: 'AppFloatingButton'
	})

	const props = defineProps({
		visible: {
			type: Boolean,
			default: true
		},
		defaultExpanded: {
			type: Boolean,
			default: false
		},
		autoNavigate: {
			type: Boolean,
			default: true
		},
		actions: {
			type: Array,
			default: () => []
		}
	})

	const emit = defineEmits(['change', 'select'])

	const expanded = ref(props.defaultExpanded)
	const compact = ref(false)
	const dragStartX = ref(0)
	const dragOffsetY = ref(0)
	const dragStartY = ref(0)
	const dragStartOffsetY = ref(0)
	const dragMinOffsetY = ref(0)
	const dragMaxOffsetY = ref(0)
	const dragging = ref(false)
	const dragMoved = ref(false)
	const suppressClick = ref(false)
	const suppressClickTimer = ref(null)

	const floatingButtonStyle = computed(() => {
		if (!dragOffsetY.value) {
			return ''
		}

		return `transform: translateY(${dragOffsetY.value}px);`
	})

	const mainIcon = computed(() => {
		return expanded.value ? FLOATING_COLLAPSE_ICON : FLOATING_EXPAND_ICON
	})

	const displayActions = computed(() => {
		const source = props.actions.length ? props.actions : DEFAULT_ACTIONS
		return source.slice(0, 4).map((item, index) => ({
			key: String(item.key || `floating-action-${index}`),
			text: String(item.text || ''),
			image: String(item.image || DEFAULT_ACTIONS[index].image),
			url: String(item.url || ''),
			className: `app-floating-button__action--${index}`
		}))
	})

	watch(() => props.defaultExpanded, (nextValue) => {
		expanded.value = Boolean(nextValue)
	})

	// 原 created 逻辑：读取用户上次选择的悬浮形态。
	restoreFloatingTypeMemory()

	onBeforeUnmount(() => {
		if (suppressClickTimer.value) {
			clearTimeout(suppressClickTimer.value)
		}
	})

	/**
	 * 切换展开和收起状态。
	 *
	 * @returns {void}
	 */
	function toggle() {
		if (suppressClick.value) {
			suppressClick.value = false
			return
		}

		dragging.value = false
		expanded.value = !expanded.value
		emit('change', expanded.value)
	}

	/**
	 * 收起悬浮按钮菜单。
	 *
	 * @returns {void}
	 */
	function collapse() {
		if (!expanded.value) {
			return
		}

		expanded.value = false
		emit('change', false)
	}

	/**
	 * 点击快捷入口。
	 *
	 * @param {Object} item 快捷入口配置。
	 * @returns {void}
	 */
	function handleAction(item = {}) {
		if (!expanded.value) {
			return
		}

		emit('select', item)
		collapse()
		if (!props.autoNavigate || !item.url) {
			return
		}

		openRoute(item.url)
	}

	/**
	 * 打开快捷入口路由。
	 *
	 * @param {string} url 页面路径。
	 * @returns {void}
	 */
	function openRoute(url = '') {
		if (TAB_BAR_URLS.indexOf(url) !== -1) {
			uni.switchTab({
				url
			})
			return
		}

		uni.navigateTo({
			url
		})
	}

	/**
	 * 收起状态下开始拖动主按钮。
	 *
	 * @param {Object} event 触摸事件。
	 * @returns {void}
	 */
	function handleMainTouchStart(event = {}) {
		if (expanded.value && !compact.value) {
			return
		}

		const touch = getTouchPoint(event)
		if (!touch) {
			return
		}

		const systemInfo = uni.getSystemInfoSync()
		const windowHeight = Number(systemInfo.windowHeight || systemInfo.screenHeight || touch.clientY)
		const safeMargin = uni.upx2px(72)

		dragging.value = true
		dragMoved.value = false
		dragStartX.value = touch.clientX
		dragStartY.value = touch.clientY
		dragStartOffsetY.value = dragOffsetY.value
		dragMinOffsetY.value = dragStartOffsetY.value + safeMargin - touch.clientY
		dragMaxOffsetY.value = Math.max(
			dragMinOffsetY.value,
			dragStartOffsetY.value + windowHeight - safeMargin - touch.clientY
		)
	}

	/**
	 * 收起状态下垂直拖动主按钮。
	 *
	 * @param {Object} event 触摸事件。
	 * @returns {void}
	 */
	function handleMainTouchMove(event = {}) {
		if (!dragging.value || (expanded.value && !compact.value)) {
			return
		}

		const touch = getTouchPoint(event)
		if (!touch) {
			return
		}

		const distanceX = touch.clientX - dragStartX.value
		const distanceY = touch.clientY - dragStartY.value

		if (Math.abs(distanceX) > DRAG_THRESHOLD || Math.abs(distanceY) > DRAG_THRESHOLD) {
			dragMoved.value = true
		}

		if (!expanded.value) {
			dragOffsetY.value = clampDragOffset(dragStartOffsetY.value + distanceY)
		}

		if (!compact.value && distanceX > COMPACT_SWIPE_THRESHOLD && Math.abs(distanceX) > Math.abs(distanceY)) {
			setFloatingType(true)
			return
		}

		if (compact.value && distanceX < -COMPACT_SWIPE_THRESHOLD && Math.abs(distanceX) > Math.abs(distanceY)) {
			setFloatingType(false, true)
		}
	}

	/**
	 * 结束主按钮拖动。
	 *
	 * @returns {void}
	 */
	function handleMainTouchEnd() {
		if (!dragging.value) {
			return
		}

		dragging.value = false
		if (!dragMoved.value) {
			return
		}

		suppressClick.value = true
		if (suppressClickTimer.value) {
			clearTimeout(suppressClickTimer.value)
		}

		suppressClickTimer.value = setTimeout(() => {
			suppressClick.value = false
			suppressClickTimer.value = null
		}, 320)
	}

	/**
	 * 获取当前触摸点坐标。
	 *
	 * @param {Object} event 触摸事件。
	 * @returns {Object|null}
	 */
	function getTouchPoint(event = {}) {
		const touches = event.touches || event.changedTouches || []
		if (
			!touches.length ||
			typeof touches[0].clientX !== 'number' ||
			typeof touches[0].clientY !== 'number'
		) {
			return null
		}

		return {
			clientX: touches[0].clientX,
			clientY: touches[0].clientY
		}
	}

	/**
	 * 限制拖动范围，避免按钮被拖出屏幕。
	 *
	 * @param {number} nextOffset 下一个纵向偏移。
	 * @returns {number}
	 */
	function clampDragOffset(nextOffset = 0) {
		return Math.min(dragMaxOffsetY.value, Math.max(dragMinOffsetY.value, nextOffset))
	}

	/**
	 * 切换悬浮按钮形态，并记住用户选择。
	 *
	 * @param {boolean} isCompact 是否为靠边吸附态。
	 * @param {boolean} shouldCollapse 是否同时收起展开菜单。
	 * @returns {void}
	 */
	function setFloatingType(isCompact, shouldCollapse = false) {
		const nextCompact = Boolean(isCompact)
		compact.value = nextCompact
		rememberFloatingType(nextCompact)

		if (!shouldCollapse || !expanded.value) {
			return
		}

		expanded.value = false
		emit('change', false)
	}

	/**
	 * 读取用户上次选择的悬浮形态。
	 *
	 * @returns {void}
	 */
	function restoreFloatingTypeMemory() {
		try {
			const storedType = uni.getStorageSync(FLOATING_TYPE_STORAGE_KEY)
			compact.value = storedType === FLOATING_TYPE_COMPACT
		} catch (error) {
			compact.value = false
		}
	}

	/**
	 * 记住当前悬浮形态。
	 *
	 * @param {boolean} isCompact 是否为靠边吸附态。
	 * @returns {void}
	 */
	function rememberFloatingType(isCompact) {
		try {
			uni.setStorageSync(
				FLOATING_TYPE_STORAGE_KEY,
				isCompact ? FLOATING_TYPE_COMPACT : FLOATING_TYPE_NORMAL
			)
		} catch (error) {
			// 存储失败不影响悬浮按钮继续使用。
		}
	}
</script>

<style scoped lang="scss">
	.app-floating-button {
		position: fixed;
		right: 0;
		bottom: 240rpx;
		z-index: 80;
		width: 452rpx;
		height: 424rpx;
		overflow: visible;
		pointer-events: none;
	}

	/* #ifdef H5 */
	.app-floating-button {
		bottom: calc(240rpx + var(--window-bottom, 0));
	}

	/* #endif */

	.app-floating-button__actions {
		position: absolute;
		right: 32rpx;
		bottom: 0;
		width: 420rpx;
		height: 424rpx;
	}

	.app-floating-button__arc {
		position: absolute;
		right: 46rpx;
		bottom: 40rpx;
		width: 214rpx;
		height: 324rpx;
		border-right: 2rpx dashed rgba(255, 90, 0, 0.52);
		border-radius: 0 220rpx 220rpx 0;
		opacity: 0;
		transform: scale(0.86);
		transform-origin: right center;
		transition: transform 200ms ease-out, opacity 160ms ease-out;
		pointer-events: none;
	}

	.app-floating-button--expanded .app-floating-button__arc {
		opacity: 1;
		transform: scale(1);
	}

	.app-floating-button__action {
		position: absolute;
		width: 246rpx;
		height: 96rpx;
		display: flex;
		align-items: center;
		justify-content: flex-end;
		opacity: 0;
		pointer-events: none;
		transform-origin: right center;
		transition: transform 200ms ease-out, opacity 160ms ease-out;

		&--0 {
			right: 78rpx;
			bottom: 318rpx;
			transform: translate(78rpx, 212rpx) scale(0.72);
		}

		&--1 {
			right: 132rpx;
			bottom: 212rpx;
			transform: translate(132rpx, 106rpx) scale(0.72);
		}

		&--2 {
			right: 132rpx;
			bottom: 106rpx;
			transform: translate(132rpx, 0) scale(0.72);
		}

		&--3 {
			right: 78rpx;
			bottom: 0;
			transform: translate(78rpx, -106rpx) scale(0.72);
		}

		&--active {
			opacity: 1;
			pointer-events: auto;
			transform: translate(0, 0) scale(1);
		}

		&--hover {
			opacity: 0.78;
		}
	}

	.app-floating-button__label {
		padding: 20rpx 26rpx;
		margin-right: -12rpx;
		border-radius: 50px 0 0 50px;
		display: flex;
		align-items: center;
		justify-content: center;
		background: #ffffff;
		box-shadow: 0 8rpx 24rpx rgba(15, 23, 42, 0.12);
		box-sizing: border-box;
		position: relative;
		z-index: 1;
	}

	.app-floating-button__label-text {
		display: block;
		font-size: 26rpx;
		line-height: 36rpx;
		font-weight: 700;
		color: var(--text-title, #18181b);
		white-space: nowrap;
	}

	.app-floating-button__icon {
		width: 96rpx;
		height: 96rpx;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		flex-shrink: 0;
		background: #ffffff;
		box-shadow: 0 12rpx 28rpx rgba(15, 23, 42, 0.14);
		position: relative;
		z-index: 2;
	}

	.app-floating-button__icon-image {
		width: 48rpx;
		height: 48rpx;
		display: block;
	}

	.app-floating-button__main {
		position: absolute;
		right: 32rpx;
		bottom: 150rpx;
		width: 96rpx;
		height: 96rpx;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		pointer-events: auto;
		box-shadow: 0 12rpx 26rpx rgba(255, 90, 0, 0.22);
		z-index: 3;
		transition: width 200ms ease-out, height 200ms ease-out, transform 200ms ease-out, border-radius 200ms ease-out;
		background-color: #fe5f00;
	}

	.app-floating-button--compact .app-floating-button__main {
		width: 72rpx;
		height: 112rpx;
		transform: translateX(56rpx);
		border-radius: 36rpx 0 0 36rpx;
		background: #fe6002;
		box-shadow: 0 8rpx 22rpx rgba(255, 90, 0, 0.2);
	}

	.app-floating-button__main--hover {
		opacity: 0.86;
	}

	.app-floating-button__main-icon {
		width: 96rpx;
		height: 96rpx;
		display: block;
		transition: width 200ms ease-out, height 200ms ease-out;
	}

	.app-floating-button--compact .app-floating-button__main-icon {
		width: 56rpx;
		height: 56rpx;
		margin-left: -12rpx;
	}
</style>