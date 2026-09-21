<template>
	<page-meta :page-style="themePageStyle"></page-meta>
	<view class="about-page">
		<app-safe-top title="关于我们" background="#ffffff" title-color="#1a1c1c" icon-color="#1a1c1c" />

		<view v-if="loading" class="about-state">
			<text class="about-state__text">加载中...</text>
		</view>

		<view v-else-if="errorMessage" class="about-state">
			<text class="about-state__text">{{ errorMessage }}</text>
			<view class="about-state__button" hover-class="about-state__button--hover" @click="fetchAbout">
				<text>重新加载</text>
			</view>
		</view>

		<scroll-view
			v-else
			class="about-scroll"
			:show-scrollbar="false"
			scroll-y
			@scroll="handleScroll"
		>
			<view class="about-scroll__body">
				<rich-text v-if="content" class="about-content" :nodes="content"></rich-text>
				<view v-else class="about-state about-state--empty">
					<text class="about-state__text">暂无关于我们内容</text>
				</view>
			</view>
		</scroll-view>

		<view v-if="hasScrolled && content" class="about-read-state">
			<text>阅读中</text>
		</view>
	</view>
</template>

<script setup>
import { getAboutDetail } from '@/api/about'
import { ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'

defineOptions({ name: 'AboutIndexPage' })

const content = ref('')
const loading = ref(true)
const errorMessage = ref('')
const hasScrolled = ref(false)

onLoad(() => {
	fetchAbout()
})

/**
 * 获取关于我们内容并更新页面状态。
 *
 * @returns {Promise<void>} 成功时展示富文本，失败时展示可重试空态。
 */
async function fetchAbout() {
	loading.value = true
	errorMessage.value = ''
	hasScrolled.value = false

	try {
		const res = await getAboutDetail({
			showError: false
		})
		if (!res || res.code !== 200 || !res.data) {
			errorMessage.value = '关于我们加载失败'
			return
		}

		content.value = String(res.data.content || '')
	} catch (error) {
		errorMessage.value = '关于我们加载失败'
	} finally {
		loading.value = false
	}
}

/**
 * 记录用户已开始滚动阅读。
 *
 * @param {Object} event scroll-view 滚动事件。
 * @returns {void}
 */
function handleScroll(event) {
	const scrollTop = Number(event && event.detail ? event.detail.scrollTop : 0)
	hasScrolled.value = scrollTop > 12
}
</script>

<style lang="scss">
page {
	background: #f7f8fa;
}
</style>

<style scoped lang="scss">
.about-page {
	display: flex;
	flex-direction: column;
	min-height: 100vh;
	background: #f7f8fa;
}

.about-scroll {
	flex: 1;
	height: 0;
	min-height: 0;

	&__body {
		padding: 24rpx 24rpx 48rpx;
	}
}

.about-content {
	display: block;
	padding: 28rpx;
	border-radius: 16rpx;
	background: #ffffff;
	box-shadow: 0 4rpx 12rpx rgba(15, 23, 42, 0.03);
	color: #3f4652;
	font-size: 28rpx;
	line-height: 1.8;
	overflow-wrap: anywhere;
}

.about-state {
	display: flex;
	flex: 1;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	min-height: 420rpx;
	padding: 64rpx 32rpx;

	&--empty {
		min-height: 520rpx;
		padding: 0;
		border-radius: 16rpx;
		background: #ffffff;
	}

	&__text {
		color: #8b8b92;
		font-size: 28rpx;
		line-height: 40rpx;
	}

	&__button {
		margin-top: 24rpx;
		padding: 16rpx 40rpx;
		border-radius: 999rpx;
		background: var(--brand-primary, #ea580c);

		&--hover {
			opacity: 0.86;
		}

		text {
			color: #ffffff;
			font-size: 26rpx;
			font-weight: 700;
			line-height: 1;
		}
	}
}

.about-read-state {
	position: fixed;
	right: 24rpx;
	bottom: calc(24rpx + env(safe-area-inset-bottom));
	padding: 10rpx 18rpx;
	border: 1rpx solid rgba(148, 163, 184, 0.18);
	border-radius: 999rpx;
	background: rgba(255, 255, 255, 0.92);
	box-shadow: 0 8rpx 24rpx rgba(15, 23, 42, 0.08);

	text {
		color: #8b8b92;
		font-size: 20rpx;
		line-height: 1;
	}
}
</style>
