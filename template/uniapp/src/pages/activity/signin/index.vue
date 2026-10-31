<template>
	<page-meta :page-style="themePageStyle"></page-meta>
	<view class="signin-page">
		<image class="signin-page__bg" src="../static/signin-bg.png" mode="aspectFill"></image>
		<view class="signin-page__safe-top">
			<app-safe-top title="签到" background="transparent" title-color="#ffffff" icon-color="#ffffff" />
		</view>

		<view class="signin-hero">
			<image class="signin-hero__title-image" src="../static/signin-title.png" mode="aspectFit"></image>
		</view>

		<view class="signin-content">
			<view class="signin-panel signin-checkin">
				<view class="signin-checkin__header">
					<view class="signin-streak">
						<text class="signin-streak__label">已连续签到</text>
						<text class="signin-streak__value">{{ streakDays }}</text>
						<text class="signin-streak__unit">天</text>
					</view>
					<view class="signin-record" hover-class="signin-record--hover" @click="handleRecord">
						<uni-icons type="list" size="18" color="#fb3c12"></uni-icons>
						<text>签到记录</text>
					</view>
				</view>

				<view class="signin-reward-board">
					<view class="signin-reward-card">
						<text class="signin-reward-card__title">{{ todayReward.title }}</text>
						<image class="signin-reward-card__icon" :src="todayReward.icon" mode="aspectFit"></image>
						<text class="signin-reward-card__main">{{ todayReward.mainText }}</text>
						<text class="signin-reward-card__sub">{{ todayReward.subText }}</text>
					</view>
					<view class="signin-reward-board__divider"></view>
					<view class="signin-reward-card">
						<text class="signin-reward-card__title">{{ nextReward.title }}</text>
						<image class="signin-reward-card__icon signin-reward-card__icon--coupon" :src="nextReward.icon" mode="aspectFit"></image>
						<text class="signin-reward-card__main">{{ nextReward.mainText }}</text>
						<text class="signin-reward-card__sub">{{ nextReward.subText }}</text>
					</view>
				</view>

				<view
					class="signin-button"
					:class="{ 'signin-button--disabled': signedToday || signing || !signinEnabled }"
					hover-class="signin-button--hover"
					@click="handleSignIn"
				>
					<text>{{ signing ? '签到中' : summary.button_text }}</text>
				</view>
				<text class="signin-checkin__tip">{{ summary.desc }}</text>
			</view>

			<view class="signin-panel signin-week">
				<view class="section-title">
					<view class="section-title__mark"></view>
					<text class="section-title__text">7天签到领好礼</text>
				</view>

				<scroll-view scroll-x class="signin-week__scroll" :show-scrollbar="false">
					<view class="signin-week__list">
						<view
							v-for="day in signDays"
							:key="day.key"
							class="signin-day-card"
							:class="{ 'signin-day-card--checked': day.checked, 'signin-day-card--active': day.active }"
						>
							<text class="signin-day-card__week">{{ day.week }}</text>
							<view class="signin-day-card__image-wrap">
								<image class="signin-day-card__image" :src="day.rewardIcon" mode="aspectFit"></image>
								<text v-if="day.hasMixedReward" class="signin-day-card__badge">券</text>
							</view>
							<text class="signin-day-card__reward" :class="day.rewardTextClass">{{ day.rewardText }}</text>
							<text class="signin-day-card__status">{{ day.statusText }}</text>
						</view>
					</view>
				</scroll-view>

				<view class="signin-week__progress">
					<view v-for="day in signDays" :key="day.key + '_node'" class="signin-node">
						<view class="signin-node__dot" :class="{ 'signin-node__dot--checked': day.checked, 'signin-node__dot--active': day.active }">
							<uni-icons v-if="day.checked" type="checkmarkempty" size="15" color="#ffffff"></uni-icons>
						</view>
						<view v-if="day.showLine" class="signin-node__line" :class="{ 'signin-node__line--checked': day.lineChecked }"></view>
					</view>
				</view>
			</view>

			<view class="signin-panel signin-tasks">
				<view class="signin-tasks__header">
					<view class="section-title">
						<view class="section-title__mark"></view>
						<text class="section-title__text">做任务</text>
					</view>
				</view>

				<scroll-view v-if="tasks.length" scroll-x class="signin-task-scroll" :show-scrollbar="false">
					<view class="signin-task-list">
						<view
							v-for="task in tasks"
							:key="task.key"
							class="signin-task-card"
							:class="{ 'signin-task-card--ready': task.completed && !task.claimed, 'signin-task-card--claimed': task.claimed }"
						>
							<view class="signin-task-card__icon">
								<image class="signin-task-card__icon-image" :src="task.iconImage" mode="aspectFit"></image>
							</view>
							<text class="signin-task-card__title">{{ task.title }}</text>
							<text class="signin-task-card__points">+{{ task.points }}积分</text>
							<view
								class="signin-task-card__button"
								:class="{ 'signin-task-card__button--claimed': task.claimed || taskLoadingKey === task.key }"
								hover-class="signin-task-card__button--hover"
								@click="handleTask(task)"
							>
								<text>{{ taskLoadingKey === task.key ? '领取中' : task.button_text }}</text>
							</view>
						</view>
					</view>
				</scroll-view>
				<view v-else class="task-empty">
					<text>暂无可完成任务</text>
				</view>
			</view>
		</view>

		<view v-if="loading" class="signin-loading">
			<six-petal-spiral :active="true" :size="84" text="" color="#ff4b18" :stroke-width="1.4"
				:particle-count="30" :trail-span="0.28" />
		</view>
	</view>
</template>

<script setup>
	import { ref } from 'vue'
	import { onShow } from '@dcloudio/uni-app'
	import {
		claimSigninTask,
		getSigninIndex,
		signInToday
	} from '@/api/activity'

	const REWARD_IMAGES = {
		points: '../static/signin-points.png',
		coupon: '../static/signin-coupon.png',
		gift: '../static/signin-box.png'
	}

	const TASK_IMAGES = {
		follow_merchant: '../static/signin-task-calendar.png',
		add_cart: '../static/signin-task-cart.png',
		order_goods: '../static/signin-task-order.png',
		browse_special: '../static/signin-task-chat.png',
		share_goods: '../static/signin-task-share.png',
		calendar: '../static/signin-task-calendar.png',
		shop: '../static/signin-task-calendar.png',
		cart: '../static/signin-task-cart.png',
		compose: '../static/signin-task-order.png',
		order: '../static/signin-task-order.png',
		eye: '../static/signin-task-chat.png',
		chat: '../static/signin-task-chat.png',
		share: '../static/signin-task-share.png'
	}

	/**
	 * 创建7天签到默认骨架。
	 *
	 * 接口加载前先用稳定节点占位，避免页面渲染时 signDays 为空导致布局跳动。
	 *
	 * @returns {Array<Object>} 默认签到进度节点。
	 */
	function createDefaultSignDays() {
		return Array.from({
			length: 7
		}).map((_, index) => ({
			key: `day_${index + 1}`,
			day: index + 1,
			week: `第${index + 1}天`,
			reward: 0,
			reward_type: 'points',
			reward_types: ['points'],
			reward_points: 0,
			coupon_id: 0,
			coupon_name: '',
			reward_label: '待配置',
			rewardIcon: index === 6 ? REWARD_IMAGES.gift : REWARD_IMAGES.points,
			rewardText: '待配置',
			rewardTextClass: '',
			statusText: '待签到',
			hasMixedReward: false,
			active: false,
			checked: false,
			showLine: index < 6,
			lineChecked: false
		}))
	}

	/**
	 * 创建奖励卡默认值。
	 *
	 * @param {string} title 奖励卡标题。
	 * @returns {Object} 页面奖励卡数据。
	 */
	function createRewardCard(title) {
		return {
			title,
			icon: REWARD_IMAGES.points,
			mainText: '待配置',
			subText: '奖励到账',
			reward_label: '待配置'
		}
	}

	defineOptions({
		name: 'ActivitySigninPage'
	})

	const loading = ref(false)
	const signing = ref(false)
	const taskLoadingKey = ref('')
	const signedToday = ref(false)
	const signinEnabled = ref(true)
	const pointsEnabled = ref(true)
	const streakDays = ref(0)
	const cycleDay = ref(0)
	const nextCycleDay = ref(1)
	const points = ref(0)
	const summary = ref({
		title: '开启今日签到',
		desc: '每日0点更新，记得每天来哦~',
		button_text: '立即签到'
	})
	const todayReward = ref(createRewardCard('今日可领'))
	const nextReward = ref(createRewardCard('明天签到可领'))
	const signDays = ref(createDefaultSignDays())
	const tasks = ref([])

	onShow(() => {
		loadSigninData()
	})

	/**
	 * 加载签到页数据。
	 *
	 * @returns {Promise<void>}
	 */
	async function loadSigninData() {
		if (loading.value) {
			return
		}

		loading.value = true
		try {
			const res = await getSigninIndex({}, {
				showError: false
			})
			if (!res || res.code !== 200 || !res.data) {
				uni.showToast({
					title: (res && res.msg) || '签到数据加载失败',
					icon: 'none'
				})
				return
			}

			applySigninData(res.data)
		} catch (error) {
			console.error('[activity-signin] loadSigninData failed', error)
			uni.showToast({
				title: '签到数据加载失败',
				icon: 'none'
			})
		} finally {
			loading.value = false
		}
	}

	/**
	 * 应用接口返回的签到页数据。
	 *
	 * 这里集中做字段兜底和类型转换，模板只消费稳定字段，减少小程序端模板表达式复杂度。
	 *
	 * @param {Object} data 接口返回数据。
	 * @returns {void}
	 */
	function applySigninData(data = {}) {
		const summary = data.summary && typeof data.summary === 'object' ? data.summary : {}
		signedToday.value = Boolean(data.signed_today)
		signinEnabled.value = data.activity_signin_enabled !== false && Number(data.activity_signin_switch === undefined ? 1 : data.activity_signin_switch) === 1
		pointsEnabled.value = data.points_enabled !== false && Number(data.points_switch === undefined ? 1 : data.points_switch) === 1
		streakDays.value = Number(data.streak_days || 0)
		cycleDay.value = Number(data.cycle_day || 0)
		nextCycleDay.value = Number(data.next_cycle_day || 1)
		points.value = Number(data.points || 0)
		summary.value = {
			title: String(summary.title || '').trim() || '开启今日签到',
			desc: String(summary.desc || '').trim() || '每日0点更新，记得每天来哦~',
			button_text: String(summary.button_text || '').trim() || (signedToday.value ? '今日已签到' : '立即签到')
		}

		const activeDay = signedToday.value ? cycleDay.value : nextCycleDay.value
		signDays.value = normalizeSignDays(data.sign_days, activeDay)
		const todayRewardSource = data.today_reward && typeof data.today_reward === 'object' ? {
			...data.today_reward,
			day: activeDay
		} : findRewardByDay(activeDay)
		todayReward.value = normalizeRewardCard(todayRewardSource, signedToday.value ? '今日已领' : '今日可领')
		nextReward.value = normalizeRewardCard(resolveNextReward(activeDay), '明天签到可领')
		tasks.value = normalizeTasks(data.tasks)
	}

	/**
	 * 标准化7天签到数据。
	 *
	 * @param {Array} list 接口返回的签到日历。
	 * @param {number} activeDay 当前高亮的签到周期天数。
	 * @returns {Array} 页面可渲染数据。
	 */
	function normalizeSignDays(list = [], activeDay = 1) {
		const source = Array.isArray(list) && list.length ? list : createDefaultSignDays()
		return source.slice(0, 7).map((item, index) => {
			const day = Number(item.day || index + 1)
			const normalized = normalizeRewardNode(item, day)
			const checked = Boolean(item.checked)

			return {
				...normalized,
				key: String(item.key || `day_${day}`),
				day,
				week: String(item.week || `第${day}天`),
				checked,
				active: Number(activeDay || 0) === day,
				showLine: index < 6,
				lineChecked: checked && source[index + 1] && Boolean(source[index + 1].checked)
			}
		})
	}

	/**
	 * 标准化单个签到日奖励节点。
	 *
	 * @param {Object} item 接口返回的奖励项。
	 * @param {number} day 签到周期天数。
	 * @returns {Object} 页面奖励节点。
	 */
	function normalizeRewardNode(item = {}, day = 1) {
		const rewardPoints = Number(item.reward_points === undefined ? (item.points === undefined ? item.reward : item.points) : item.reward_points) || 0
		const couponId = Number(item.coupon_id || 0)
		const rewardTypes = resolveRewardTypes(item, rewardPoints, couponId)
		const hasPoints = rewardTypes.indexOf('points') !== -1
		const hasCoupon = rewardTypes.indexOf('coupon') !== -1
		const rewardLabel = String(item.reward_label || '').trim() || buildRewardLabel(rewardPoints, couponId, item.coupon_name)
		const rewardText = buildRewardText(rewardPoints, couponId, item.coupon_name)

		return {
			reward: rewardPoints,
			reward_type: String(item.reward_type || (hasPoints && hasCoupon ? 'mixed' : (hasCoupon ? 'coupon' : 'points'))),
			reward_types: rewardTypes,
			reward_points: rewardPoints,
			coupon_id: couponId,
			coupon_name: String(item.coupon_name || ''),
			reward_label: rewardLabel,
			rewardIcon: resolveRewardIcon(day, hasPoints, hasCoupon),
			rewardText,
			rewardTextClass: resolveRewardTextClass(rewardText),
			statusText: item.checked ? '已签到' : (Number(item.day || day) === nextCycleDay.value && !signedToday.value ? '今日可领' : '待签到'),
			hasMixedReward: hasPoints && hasCoupon
		}
	}

	/**
	 * 标准化任务列表。
	 *
	 * 后端会根据任务开关过滤列表，前端保留 enabled 作为二次保护。
	 *
	 * @param {Array} list 接口返回任务。
	 * @returns {Array} 页面任务列表。
	 */
	function normalizeTasks(list = []) {
		return Array.isArray(list) ?
			list.map((task) => ({
				key: String(task.key || ''),
				title: String(task.title || ''),
				desc: String(task.desc || ''),
				points: Number(task.points || 0),
				icon: String(task.icon || 'compose'),
				iconImage: resolveTaskImage(task),
				action_url: String(task.action_url || ''),
				enabled: task.enabled !== false,
				completed: Boolean(task.completed),
				claimed: Boolean(task.claimed),
				status: String(task.status || ''),
				button_text: String(task.button_text || '')
			})).filter((task) => task.key && task.title && task.enabled) :
			[]
	}

	/**
	 * 根据任务 key 或后端 icon 字段获取本地任务图标。
	 *
	 * @param {Object} task 接口返回任务。
	 * @returns {string} 本地任务图标路径。
	 */
	function resolveTaskImage(task = {}) {
		const key = String(task.key || '')
		const icon = String(task.icon || '')
		return TASK_IMAGES[key] || TASK_IMAGES[icon] || TASK_IMAGES.calendar
	}

	/**
	 * 根据接口奖励类型和奖励内容推导奖励类型数组。
	 *
	 * @param {Object} item 接口奖励项。
	 * @param {number} rewardPoints 积分数量。
	 * @param {number} couponId 优惠券ID。
	 * @returns {Array<string>} 奖励类型数组。
	 */
	function resolveRewardTypes(item = {}, rewardPoints = 0, couponId = 0) {
		let types = Array.isArray(item.reward_types) ? item.reward_types.slice(0) : []
		const rewardType = String(item.reward_type || '')
		if (!types.length && rewardType === 'mixed') {
			types = ['points', 'coupon']
		} else if (!types.length && rewardType) {
			types = [rewardType]
		}

		if (rewardPoints > 0 && types.indexOf('points') === -1) {
			types.push('points')
		}
		if (couponId > 0 && types.indexOf('coupon') === -1) {
			types.push('coupon')
		}
		if (!types.length) {
			types.push('points')
		}

		return types.filter((type, index) => type && types.indexOf(type) === index)
	}

	/**
	 * 根据奖励内容获取图标资源。
	 *
	 * @param {number} day 签到周期天数。
	 * @param {boolean} hasPoints 是否有积分。
	 * @param {boolean} hasCoupon 是否有优惠券。
	 * @returns {string} 本地奖励图路径。
	 */
	function resolveRewardIcon(day, hasPoints, hasCoupon) {
		if (Number(day) === 7) {
			return REWARD_IMAGES.gift
		}
		if (hasCoupon && !hasPoints) {
			return REWARD_IMAGES.coupon
		}
		return REWARD_IMAGES.points
	}

	/**
	 * 生成奖励卡数据。
	 *
	 * @param {Object} reward 奖励配置。
	 * @param {string} title 奖励卡标题。
	 * @returns {Object} 奖励卡展示数据。
	 */
	function normalizeRewardCard(reward = {}, title = '今日可领') {
		const normalized = normalizeRewardNode(reward, Number(reward.day || nextCycleDay.value || 1))
		const hasPoints = normalized.reward_types.indexOf('points') !== -1
		const hasCoupon = normalized.reward_types.indexOf('coupon') !== -1
		let subText = '奖励到账'
		if (hasPoints && hasCoupon) {
			subText = '双重奖励到账'
		} else if (hasCoupon) {
			subText = '优惠券到账'
		} else if (hasPoints) {
			subText = '积分到账'
		}

		return {
			title,
			icon: normalized.rewardIcon,
			mainText: normalized.rewardText,
			subText,
			reward_label: normalized.reward_label
		}
	}

	/**
	 * 查找指定天数的签到奖励。
	 *
	 * @param {number} day 签到周期天数。
	 * @returns {Object} 奖励节点。
	 */
	function findRewardByDay(day) {
		return signDays.value.find((item) => Number(item.day) === Number(day)) || signDays.value[0] || {}
	}

	/**
	 * 获取明天可领的奖励节点。
	 *
	 * @param {number} activeDay 当前高亮的签到周期天数。
	 * @returns {Object} 明天奖励节点。
	 */
	function resolveNextReward(activeDay) {
		const day = Number(activeDay || 0)
		const nextDay = day > 0 ? (day % 7) + 1 : 1
		return findRewardByDay(nextDay)
	}

	/**
	 * 生成奖励标签文案。
	 *
	 * @param {number} rewardPoints 积分数量。
	 * @param {number} couponId 优惠券ID。
	 * @param {string} couponName 优惠券名称。
	 * @returns {string} 奖励文案。
	 */
	function buildRewardLabel(rewardPoints, couponId, couponName) {
		const labels = []
		if (Number(rewardPoints || 0) > 0) {
			labels.push(`${Number(rewardPoints)}积分`)
		}
		if (Number(couponId || 0) > 0) {
			labels.push(String(couponName || '').trim() || '优惠券')
		}
		return labels.length ? labels.join(' + ') : '待配置'
	}

	/**
	 * 生成短奖励文案。
	 *
	 * @param {number} rewardPoints 积分数量。
	 * @param {number} couponId 优惠券ID。
	 * @param {string} couponName 优惠券名称。
	 * @returns {string} 页面短文案。
	 */
	function buildRewardText(rewardPoints, couponId, couponName) {
		const points = Number(rewardPoints || 0)
		if (points > 0 && Number(couponId || 0) > 0) {
			return `+${points}积分+券`
		}
		if (points > 0) {
			return `+${points}积分`
		}
		if (Number(couponId || 0) > 0) {
			const name = String(couponName || '').trim()
			return name ? name : '优惠券'
		}
		return '待配置'
	}

	/**
	 * 根据奖励文案长度获取字号样式类。
	 *
	 * @param {string} rewardText 页面短奖励文案。
	 * @returns {string} 奖励文案字号类名。
	 */
	function resolveRewardTextClass(rewardText) {
		const text = String(rewardText || '')
		const length = Array.from(text).length
		const visualLength = Array.from(text).reduce((total, char) => total + (/^[\x00-\x7F]$/.test(char) ? 0.55 : 1), 0)
		if (length >= 10 || visualLength > 6.4) {
			return 'signin-day-card__reward--micro'
		}
		if (length >= 8 || visualLength > 5.7) {
			return 'signin-day-card__reward--tiny'
		}
		if (length >= 7 || visualLength > 5) {
			return 'signin-day-card__reward--mini'
		}
		if (length >= 6 || visualLength > 4.2) {
			return 'signin-day-card__reward--small'
		}
		return ''
	}

	/**
	 * 格式化签到成功提示。
	 *
	 * @param {Object} result 签到结果。
	 * @returns {string} toast 文案。
	 */
	function formatSignToast(result = {}) {
		const label = String(result.reward_label || '').trim() || buildRewardLabel(
			Number(result.reward_points || 0),
			Number(result.coupon_id || 0),
			result.coupon_name
		)
		return label.length > 18 ? `签到成功 ${label.slice(0, 18)}` : `签到成功 ${label}`
	}

	/**
	 * 处理签到按钮点击。
	 *
	 * @returns {void}
	 */
	async function handleSignIn() {
		if (signing.value) {
			return
		}
		if (!signinEnabled.value) {
			uni.showToast({
				title: summary.value.desc || '签到功能已关闭',
				icon: 'none'
			})
			return
		}
		if (signedToday.value) {
			uni.showToast({
				title: '今日已签到',
				icon: 'none'
			})
			return
		}

		signing.value = true
		try {
			const res = await signInToday({}, {
				showError: false
			})
			if (!res || res.code !== 200 || !res.data) {
				uni.showToast({
					title: (res && res.msg) || '签到失败',
					icon: 'none'
				})
				return
			}

			applySigninData(res.data)
			const result = res.data.sign_result || {}
			uni.showToast({
				title: formatSignToast(result),
				icon: 'none'
			})
		} catch (error) {
			console.error('[activity-signin] handleSignIn failed', error)
			uni.showToast({
				title: '签到失败',
				icon: 'none'
			})
		} finally {
			signing.value = false
		}
	}

	/**
	 * 处理签到规则入口。
	 *
	 * @returns {void}
	 */
	function handleRule() {
		uni.showToast({
			title: '连续签到可获得积分或优惠券奖励',
			icon: 'none'
		})
	}

	/**
	 * 处理签到记录入口。
	 *
	 * @returns {void}
	 */
	function handleRecord() {
		uni.navigateTo({
			url: '/pages/users/profile/index'
		})
	}

	/**
	 * 处理全部任务入口。
	 *
	 * @returns {void}
	 */
	function handleOpenTasks() {
		uni.showToast({
			title: '完成任务后返回本页领取积分',
			icon: 'none'
		})
	}

	/**
	 * 处理任务项操作。
	 *
	 * 未完成时跳转任务入口；已完成但未领取时调用领奖接口；已领取时只提示状态。
	 *
	 * @param {Object} task 当前任务。
	 * @returns {void}
	 */
	function handleTask(task = {}) {
		if (taskLoadingKey.value) {
			return
		}
		if (task.enabled === false) {
			uni.showToast({
				title: '任务已关闭',
				icon: 'none'
			})
			return
		}
		if (task.claimed) {
			uni.showToast({
				title: '今日已领取',
				icon: 'none'
			})
			return
		}
		if (task.completed) {
			claimTaskReward(task)
			return
		}

		const url = String(task.action_url || '').trim()
		if (!url) {
			uni.showToast({
				title: '任务入口暂不可用',
				icon: 'none'
			})
			return
		}

		uni.navigateTo({
			url
		})
	}

	/**
	 * 领取任务积分。
	 *
	 * 领取成功后服务端返回最新签到页聚合数据，页面直接刷新整体状态。
	 *
	 * @param {Object} task 当前任务。
	 * @returns {Promise<void>}
	 */
	async function claimTaskReward(task = {}) {
		const taskKey = String(task.key || '').trim()
		if (!taskKey || taskLoadingKey.value) {
			return
		}

		taskLoadingKey.value = taskKey
		try {
			const res = await claimSigninTask({
				task_key: taskKey
			}, {
				showError: false
			})
			if (!res || res.code !== 200 || !res.data) {
				uni.showToast({
					title: (res && res.msg) || '领取失败',
					icon: 'none'
				})
				return
			}

			applySigninData(res.data)
			const result = res.data.task_result || {}
			uni.showToast({
				title: `领取成功 +${Number(result.reward_points || 0)}积分`,
				icon: 'none'
			})
		} catch (error) {
			console.error('[activity-signin] claimTaskReward failed', error)
			uni.showToast({
				title: '领取失败',
				icon: 'none'
			})
		} finally {
			taskLoadingKey.value = ''
		}
	}
</script>

<style lang="scss">
	page {
		background: #ff7200;
	}
</style>

<style lang="scss" scoped>
	.signin-page {
		position: relative;
		min-height: 100%;
		overflow: hidden;
		background: #ff7200;
		color: var(--text-primary, #18181b);
		--signin-primary: #fb3c12;
		--signin-primary-dark: #d7240d;
		--signin-orange: var(--brand-primary, #ea580c);
		--signin-cream: #fff8ef;
		--signin-card: rgba(255, 252, 246, 0.96);
		--signin-border: #ffd8bd;
		--signin-brown: #6d2d1d;
	}

	.signin-page__bg {
		position: absolute;
		left: 0;
		top: 0;
		z-index: 0;
		width: 100%;
		height: 1622rpx;
	}

	.signin-page__safe-top,
	.signin-hero,
	.signin-content {
		position: relative;
		z-index: 2;
	}

	.signin-hero {
		display: flex;
		align-items: flex-start;
		justify-content: center;
		height: 332rpx;
		padding-left: 32rpx;
		padding-right: 32rpx;
		box-sizing: border-box;
	}

	.signin-hero__rule {
		position: absolute;
		top: 8rpx;
		right: 32rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		height: 52rpx;
		padding-left: 18rpx;
		padding-right: 22rpx;
		border: 1px solid rgba(255, 255, 255, 0.72);
		border-radius: 999rpx;
		background: rgba(255, 255, 255, 0.12);
		color: #ffffff;
	}

	.signin-hero__rule--hover {
		opacity: 0.84;
	}

	.signin-hero__rule-text {
		margin-left: 8rpx;
		font-size: 24rpx;
		line-height: 34rpx;
		font-weight: 600;
	}

	.signin-hero__title-image {
		display: block;
		width: 650rpx;
		height: 311rpx;
	}

	.signin-content {
		padding: 0 22rpx 48rpx;
		box-sizing: border-box;
	}

	.signin-panel {
		width: 100%;
		border: 1px solid rgba(255, 211, 176, 0.8);
		border-radius: 26rpx;
		background: var(--signin-card);
		box-shadow: 0 12rpx 24rpx rgba(146, 38, 7, 0.14);
		box-sizing: border-box;
	}

	.signin-panel + .signin-panel {
		margin-top: 24rpx;
	}

	.signin-checkin {
		padding: 30rpx 26rpx 28rpx;
	}

	.signin-checkin__header {
		display: flex;
		align-items: center;
		justify-content: space-between;
	}

	.signin-streak {
		display: flex;
		align-items: flex-end;
		min-width: 0;
		color: #1f1712;
	}

	.signin-streak__label,
	.signin-streak__unit {
		font-size: 30rpx;
		line-height: 44rpx;
		font-weight: 700;
	}

	.signin-streak__value {
		margin-left: 12rpx;
		margin-right: 10rpx;
		font-size: 52rpx;
		line-height: 58rpx;
		font-weight: 900;
		color: var(--signin-primary);
	}

	.signin-record {
		display: flex;
		align-items: center;
		justify-content: center;
		height: 58rpx;
		padding-left: 18rpx;
		padding-right: 20rpx;
		border: 1px solid var(--signin-border);
		border-radius: 999rpx;
		background: #fff9f3;
		color: var(--signin-primary);
		font-size: 24rpx;
		line-height: 34rpx;
		font-weight: 600;
		flex-shrink: 0;
	}

	.signin-record text {
		margin-left: 6rpx;
	}

	.signin-record--hover {
		opacity: 0.82;
	}

	.signin-reward-board {
		display: flex;
		align-items: stretch;
		margin-top: 24rpx;
		padding: 22rpx 12rpx;
		border: 1px solid rgba(255, 219, 191, 0.9);
		border-radius: 24rpx;
		background: rgba(255, 250, 244, 0.82);
		box-sizing: border-box;
	}

	.signin-reward-board__divider {
		width: 1px;
		margin: 18rpx 4rpx;
		background: #ffb089;
	}

	.signin-reward-card {
		display: flex;
		flex: 1;
		min-width: 0;
		flex-direction: column;
		align-items: center;
		text-align: center;
	}

	.signin-reward-card__title {
		font-size: 28rpx;
		line-height: 40rpx;
		font-weight: 700;
		color: var(--signin-brown);
	}

	.signin-reward-card__icon {
		width: 132rpx;
		height: 108rpx;
		margin-top: 14rpx;
	}

	.signin-reward-card__icon--coupon {
		width: 150rpx;
	}

	.signin-reward-card__main {
		display: block;
		width: 100%;
		margin-top: 8rpx;
		overflow: hidden;
		color: var(--signin-primary);
		font-size: 30rpx;
		line-height: 40rpx;
		font-weight: 800;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.signin-reward-card__sub {
		display: block;
		width: 100%;
		margin-top: 6rpx;
		overflow: hidden;
		color: #7b3b27;
		font-size: 22rpx;
		line-height: 32rpx;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.signin-button {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 520rpx;
		height: 86rpx;
		margin: 24rpx auto 0;
		border-radius: 999rpx;
		border: 2rpx solid rgba(255, 244, 224, 0.88);
		background: linear-gradient(180deg, #ff6d22 0%, #f6160c 100%);
		box-shadow: 0 10rpx 0 #ca1d09, 0 16rpx 28rpx rgba(222, 39, 13, 0.32);
		color: #ffffff;
		font-size: 38rpx;
		line-height: 48rpx;
		font-weight: 900;
	}

	.signin-button--hover {
		opacity: 0.9;
	}

	.signin-button--disabled {
		background: linear-gradient(180deg, #d3d5dc 0%, #aeb4c0 100%);
		box-shadow: 0 10rpx 0 #969da9;
	}

	.signin-checkin__tip {
		display: block;
		margin-top: 22rpx;
		color: var(--signin-brown);
		font-size: 24rpx;
		line-height: 34rpx;
		text-align: center;
	}

	.signin-week {
		padding: 28rpx 20rpx 26rpx;
	}

	.section-title {
		display: flex;
		align-items: center;
		min-width: 0;
	}

	.section-title__mark {
		width: 16rpx;
		height: 16rpx;
		margin-right: 12rpx;
		border-radius: 3rpx;
		background: var(--signin-primary);
		transform: rotate(45deg);
		flex-shrink: 0;
	}

	.section-title__text {
		color: var(--signin-brown);
		font-size: 34rpx;
		line-height: 46rpx;
		font-weight: 800;
	}

	.signin-week__scroll {
		width: 100%;
		margin-top: 22rpx;
		white-space: nowrap;
	}

	.signin-week__list {
		display: flex;
		align-items: stretch;
		padding-bottom: 4rpx;
	}

	.signin-day-card {
		display: flex;
		flex-direction: column;
		align-items: center;
		width: 104rpx;
		min-height: 174rpx;
		margin-right: 18rpx;
		padding: 14rpx 8rpx 12rpx;
		border: 1px solid #ffd8bd;
		border-radius: 16rpx;
		background: #fff9f2;
		box-sizing: border-box;
		flex-shrink: 0;
	}

	.signin-day-card:last-child {
		margin-right: 0;
	}

	.signin-day-card--active {
		border-color: #ff4118;
		background: linear-gradient(180deg, #ff4222 0%, #fff8ef 46%, #fff8ef 100%);
		box-shadow: 0 8rpx 18rpx rgba(249, 55, 18, 0.16);
	}

	.signin-day-card--checked {
		border-color: #ffb27a;
	}

	.signin-day-card__week {
		color: var(--signin-brown);
		font-size: 23rpx;
		line-height: 32rpx;
		font-weight: 700;
		white-space: nowrap;
	}

	.signin-day-card--active .signin-day-card__week {
		color: #ffffff;
	}

	.signin-day-card__image-wrap {
		position: relative;
		display: flex;
		align-items: center;
		justify-content: center;
		width: 78rpx;
		height: 64rpx;
		margin-top: 10rpx;
	}

	.signin-day-card__image {
		width: 78rpx;
		height: 64rpx;
	}

	.signin-day-card__badge {
		position: absolute;
		right: -6rpx;
		top: -4rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		width: 28rpx;
		height: 28rpx;
		border-radius: 14rpx;
		background: var(--signin-primary);
		color: #ffffff;
		font-size: 18rpx;
		line-height: 28rpx;
		font-weight: 700;
	}

	.signin-day-card__reward {
		display: block;
		width: 104rpx;
		margin-top: 10rpx;
		margin-left: -8rpx;
		margin-right: -8rpx;
		overflow: hidden;
		color: #4b2418;
		font-size: 22rpx;
		line-height: 30rpx;
		font-weight: 700;
		text-align: center;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.signin-day-card__reward--small {
		font-size: 20rpx;
		line-height: 30rpx;
	}

	.signin-day-card__reward--mini {
		font-size: 18rpx;
		line-height: 28rpx;
	}

	.signin-day-card__reward--tiny {
		font-size: 16rpx;
		line-height: 26rpx;
	}

	.signin-day-card__reward--micro {
		font-size: 14rpx;
		line-height: 24rpx;
	}

	.signin-day-card__status {
		display: flex;
		align-items: center;
		justify-content: center;
		min-width: 72rpx;
		height: 36rpx;
		margin-top: 10rpx;
		padding-left: 10rpx;
		padding-right: 10rpx;
		border: 1px solid #ffc6a2;
		border-radius: 999rpx;
		color: #8a4a26;
		font-size: 20rpx;
		line-height: 28rpx;
		background: #fff4ea;
		box-sizing: border-box;
		white-space: nowrap;
	}

	.signin-day-card--active .signin-day-card__status {
		border-color: #f72113;
		background: #f72113;
		color: #ffffff;
	}

	.signin-day-card--checked .signin-day-card__status {
		border-color: #ffad74;
		background: #ffad74;
		color: #ffffff;
	}

	.signin-week__progress {
		display: flex;
		align-items: center;
		margin-top: 22rpx;
		padding-left: 24rpx;
		padding-right: 18rpx;
	}

	.signin-node {
		position: relative;
		display: flex;
		align-items: center;
		flex: 1;
		min-width: 0;
	}

	.signin-node:last-child {
		flex: 0 0 32rpx;
	}

	.signin-node__dot {
		position: relative;
		z-index: 1;
		display: flex;
		align-items: center;
		justify-content: center;
		width: 32rpx;
		height: 32rpx;
		border: 6rpx solid #c8cdd2;
		border-radius: 50%;
		background: #ffffff;
		box-sizing: border-box;
	}

	.signin-node__dot--checked {
		border-color: var(--signin-orange);
		background: var(--signin-orange);
	}

	.signin-node__dot--active {
		border-color: var(--signin-orange);
		box-shadow: 0 0 0 6rpx var(--brand-shadow, rgba(234, 88, 12, 0.18));
	}

	.signin-node__line {
		height: 2rpx;
		flex: 1;
		border-top: 2rpx dashed #c8cdd2;
	}

	.signin-node__line--checked {
		border-color: var(--signin-orange);
	}

	.signin-tasks {
		padding: 28rpx 20rpx 30rpx;
	}

	.signin-tasks__header {
		display: flex;
		align-items: center;
		justify-content: space-between;
	}

	.signin-tasks__more {
		display: flex;
		align-items: center;
		color: #8a4a26;
		font-size: 24rpx;
		line-height: 34rpx;
		flex-shrink: 0;
	}

	.signin-tasks__more--hover {
		opacity: 0.76;
	}

	.signin-task-scroll {
		width: 100%;
		margin-top: 24rpx;
		white-space: nowrap;
	}

	.signin-task-list {
		display: flex;
		align-items: stretch;
		padding-bottom: 4rpx;
	}

	.signin-task-card {
		display: flex;
		flex-direction: column;
		align-items: center;
		width: 124rpx;
		margin-right: 22rpx;
		padding-left: 4rpx;
		padding-right: 4rpx;
		box-sizing: border-box;
		flex-shrink: 0;
	}

	.signin-task-card:last-child {
		margin-right: 0;
	}

	.signin-task-card__icon {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 76rpx;
		height: 76rpx;
		border-radius: 38rpx;
		background: #ffe9d9;
	}

	.signin-task-card__icon-image {
		width: 68rpx;
		height: 68rpx;
	}

	.signin-task-card__title {
		display: block;
		width: 100%;
		margin-top: 14rpx;
		overflow: hidden;
		color: #7a371f;
		font-size: 22rpx;
		line-height: 32rpx;
		text-align: center;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.signin-task-card__points {
		display: block;
		width: 100%;
		margin-top: 4rpx;
		overflow: hidden;
		color: var(--signin-primary);
		font-size: 22rpx;
		line-height: 32rpx;
		font-weight: 700;
		text-align: center;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.signin-task-card__button {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 110rpx;
		height: 44rpx;
		margin-top: 12rpx;
		border: 1px solid #ff7b45;
		border-radius: 999rpx;
		background: #fff8f2;
		color: var(--signin-primary);
		font-size: 22rpx;
		line-height: 30rpx;
		font-weight: 700;
		box-sizing: border-box;
	}

	.signin-task-card__button--hover {
		opacity: 0.84;
	}

	.signin-task-card--ready .signin-task-card__button {
		background: var(--signin-primary);
		color: #ffffff;
	}

	.signin-task-card__button--claimed {
		border-color: #d8d8dc;
		background: #f1f2f4;
		color: #8f949d;
	}

	.task-empty {
		display: flex;
		align-items: center;
		justify-content: center;
		min-height: 132rpx;
		margin-top: 24rpx;
		border-radius: 18rpx;
		background: rgba(255, 244, 234, 0.8);
		color: #8a4a26;
		font-size: 24rpx;
		line-height: 34rpx;
	}

	.signin-loading {
		position: fixed;
		left: 0;
		right: 0;
		top: 0;
		bottom: 0;
		z-index: 30;
		display: flex;
		align-items: center;
		justify-content: center;
		background: rgba(255, 255, 255, 0.52);
	}
</style>
