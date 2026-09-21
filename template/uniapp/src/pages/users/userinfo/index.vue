<template>
	<page-meta :page-style="themePageStyle"></page-meta>
	<view class="userinfo-page">
		<app-safe-top title="个人设置" background="#ffffff" />

		<!-- 主内容区 -->
		<scroll-view scroll-y class="userinfo-scroll">
			<view class="userinfo-content">
				<!-- 头像设置卡片（单行横排） -->
				<!-- #ifdef MP-WEIXIN -->
				<button class="info-card info-card--row avatar-card-button" open-type="chooseAvatar"
					:disabled="avatarUploading" @chooseavatar="handleChooseAvatar">
					<text class="info-card__label">头像</text>
					<view class="info-card__right">
						<view class="avatar-wrap">
							<image class="avatar-wrap__img"
								:src="avatarUrl" mode="aspectFill" />
							<view class="avatar-wrap__overlay">
								<uni-icons type="compose" size="14" color="#ffffff"></uni-icons>
							</view>
						</view>
						<uni-icons type="right" size="14" color="#bbbbbb"></uni-icons>
					</view>
				</button>
				<!-- #endif -->
				<!-- #ifndef MP-WEIXIN -->
				<view class="info-card info-card--row" @click="handleChooseAvatar">
					<text class="info-card__label">头像</text>
					<view class="info-card__right">
						<view class="avatar-wrap">
							<image class="avatar-wrap__img"
								:src="avatarUrl" mode="aspectFill" />
							<view class="avatar-wrap__overlay">
								<uni-icons type="compose" size="14" color="#ffffff"></uni-icons>
							</view>
						</view>
						<uni-icons type="right" size="14" color="#bbbbbb"></uni-icons>
					</view>
				</view>
				<!-- #endif -->

				<!-- 基本信息列表卡片（多行纵排） -->
				<view class="info-card info-card--list">
					<!-- 昵称 -->
					<view class="info-card__row">
						<text class="info-card__label">昵称</text>
						<view class="info-card__right">
							<!-- #ifdef MP-WEIXIN -->
							<input class="info-card__input" type="nickname" v-model="form.nickname" placeholder="请输入昵称"
								placeholder-class="info-card__placeholder" @blur="handleNicknameBlur" />
							<!-- #endif -->
							<!-- #ifndef MP-WEIXIN -->
							<input class="info-card__input" type="text" v-model="form.nickname" placeholder="请输入昵称"
								placeholder-class="info-card__placeholder" />
							<!-- #endif -->
							<uni-icons type="compose" size="14" color="#bbbbbb"></uni-icons>
						</view>
					</view>
					<view class="info-card__divider"></view>

					<!-- 手机号 -->
					<view class="info-card__row info-card__row--clickable" @click="handlePhoneClick">
						<text class="info-card__label">手机号</text>
						<view class="info-card__right">
							<text class="info-card__value">{{ maskedPhone }}</text>
							<uni-icons type="right" size="14" color="#bbbbbb"></uni-icons>
						</view>
					</view>
					<view class="info-card__divider"></view>

					<!-- 性别切换 -->
					<view class="info-card__row">
						<text class="info-card__label">性别</text>
						<view class="info-card__right">
							<view class="gender-switch">
								<view class="gender-switch__item"
									:class="{ 'gender-switch__item--active': form.gender === 1 }"
									@click="handleGenderChange(1)">
									<text class="gender-switch__text"
										:class="{ 'gender-switch__text--active': form.gender === 1 }">男</text>
								</view>
								<view class="gender-switch__item"
									:class="{ 'gender-switch__item--active': form.gender === 2 }"
									@click="handleGenderChange(2)">
									<text class="gender-switch__text"
										:class="{ 'gender-switch__text--active': form.gender === 2 }">女</text>
								</view>
							</view>
						</view>
					</view>
				</view>

				<!-- 收货地址管理（单行横排） -->
				<view class="info-card info-card--row info-card--clickable" @click="handleAddressClick">
					<text class="info-card__label">收货地址管理</text>
					<uni-icons type="right" size="14" color="#bbbbbb"></uni-icons>
				</view>
			</view>
		</scroll-view>

		<!-- 底部操作区 -->
		<view class="userinfo-footer">
			<button class="btn btn--primary" :loading="saveLoading" @click="handleSave">
				保存
			</button>
			<button class="btn btn--logout" @click="handleLogout">
				退出登录
			</button>
		</view>

		<app-floating-button />
	</view>
</template>

<script setup>
	import {
		getUserInfo as fetchUserInfo,
		updateUserInfo
	} from '@/api/user'
	import {
		uploadMobileImage
	} from '@/api/file'
	import {
		logout
	} from '@/api/auth'
	import {
		buildLoginPageUrl,
		clearAuthStorage,
		getCurrentPageUrl,
		savePostLoginRedirect,
		setUserInfo as setLocalUserInfo
	} from '@/utils/auth'
	import { computed, ref } from 'vue'
	import { onLoad } from '@dcloudio/uni-app'

	const DEFAULT_AVATAR = '/static/logo.png'

	defineOptions({ name: 'UserinfoIndex' })

	const saveLoading = ref(false)
	const avatarUploading = ref(false)
	const userInfo = ref({})
	const form = ref({
		nickname: '',
		gender: 0,
		avatar: ''
	})

	/**
	 * 当前头像展示地址。
	 * @returns {string} 头像地址。
	 */
	const avatarUrl = computed(() => {
		return form.value.avatar || userInfo.value.avatar || DEFAULT_AVATAR
	})

	/**
	 * 脱敏手机号显示
	 * @returns {string} 格式：138 **** 8888
	 */
	const maskedPhone = computed(() => {
		const phone = userInfo.value.phone || ''
		if (phone.length >= 11) {
			return phone.substring(0, 3) + ' **** ' + phone.substring(7)
		}
		return phone
	})

	onLoad(() => {
		loadUserInfo()
	})

	/**
	 * 加载用户信息
	 */
	async function loadUserInfo() {
		try {
			const res = await fetchUserInfo()
			const data = res && res.data && typeof res.data === 'object' ? res.data : {}
			if (!data.user || typeof data.user !== 'object') {
				uni.showToast({
					title: '用户信息格式异常',
					icon: 'none'
				})
				return
			}
			applyUserProfile(data.user)
		} catch (e) {
			console.error('加载用户信息失败', e)
		}
	}

	/**
	 * 应用接口返回的用户资料。
	 * @param {Object} user 后端 `data.user` 用户资料。
	 */
	function applyUserProfile(user = {}) {
		const normalizedUser = {
			...user,
			nickname: String(user.nickname || '').trim(),
			avatar: String(user.avatar || '').trim(),
			sex: Number(user.sex || 0)
		}
		userInfo.value = normalizedUser
		form.value.nickname = normalizedUser.nickname
		form.value.gender = normalizedUser.sex
		form.value.avatar = normalizedUser.avatar
		setLocalUserInfo(normalizedUser)
	}

	/**
	 * 应用头像上传结果。
	 * @param {string} avatarUrlValue 后端 `file/upload` 返回的 `data.url`。
	 */
	function applyAvatarUrl(avatarUrlValue = '') {
		const normalizedAvatar = String(avatarUrlValue || '').trim()
		form.value.avatar = normalizedAvatar
		userInfo.value = {
			...userInfo.value,
			avatar: normalizedAvatar
		}
	}

	/**
	 * 选择头像（H5 选择图片，小程序接收 chooseAvatar 返回路径）
	 * @param {Object} event 小程序 chooseAvatar 事件。
	 */
	function handleChooseAvatar(event) {
		const detail = event && event.detail && typeof event.detail === 'object' ? event.detail : {}
		const chosenAvatarUrl = detail.avatarUrl !== undefined ? String(detail.avatarUrl).trim() : ''
		if (chosenAvatarUrl) {
			uploadAvatar(chosenAvatarUrl)
			return
		}

		// #ifdef MP-WEIXIN
		return
		// #endif

		chooseAvatarImage()
	}

	/**
	 * 从相册或相机选择头像。
	 */
	function chooseAvatarImage() {
		uni.chooseImage({
			count: 1,
			sizeType: ['compressed'],
			sourceType: ['album', 'camera'],
			success: (res) => {
				const tempFilePaths = Array.isArray(res.tempFilePaths) ? res.tempFilePaths : []
				if (tempFilePaths.length > 0) {
					uploadAvatar(tempFilePaths[0])
				}
			}
		})
	}

	/**
	 * 小程序昵称输入框失焦时同步昵称。
	 * @param {Object} event 输入框事件。
	 */
	function handleNicknameBlur(event) {
		const detail = event && event.detail && typeof event.detail === 'object' ? event.detail : {}
		if (detail.value !== undefined) {
			form.value.nickname = String(detail.value).trim()
		}
	}

	/**
	 * 上传头像
	 * @param {string} filePath 文件路径
	 */
	async function uploadAvatar(filePath) {
		if (avatarUploading.value) {
			return
		}

		avatarUploading.value = true
		uni.showLoading({
			title: '上传中...',
			mask: true
		})
		try {
			const uploadRes = await uploadMobileImage(filePath, {
				showError: false
			})
			const uploadData = uploadRes && uploadRes.data && typeof uploadRes.data === 'object' ? uploadRes.data : {}
			const uploadedAvatarUrl = uploadData.url !== undefined ? String(uploadData.url).trim() : ''
			if (uploadRes.code !== 200 || !uploadedAvatarUrl) {
				uni.showToast({
					title: uploadRes.msg || '头像上传失败',
					icon: 'none'
				})
				return
			}

			applyAvatarUrl(uploadedAvatarUrl)
			uni.showToast({
				title: '头像已上传，请保存',
				icon: 'none'
			})
		} catch (e) {
			uni.showToast({
				title: '头像上传失败',
				icon: 'none'
			})
		} finally {
			avatarUploading.value = false
			uni.hideLoading()
		}
	}

	/** 点击手机号行 */
	function handlePhoneClick() {
		uni.navigateTo({
			url: '/pages/users/phoneedit/index'
		})
	}

	/**
	 * 切换性别
	 * @param {number} gender 1-男 2-女
	 */
	function handleGenderChange(gender) {
		form.value.gender = gender
	}

	/** 点击收货地址管理 */
	function handleAddressClick() {
		uni.navigateTo({
			url: '/pages/users/address/list/index'
		})
	}

	/** 保存个人信息 */
	async function handleSave() {
		const nickname = String(form.value.nickname || '').trim()
		if (!nickname) {
			uni.showToast({
				title: '请输入昵称',
				icon: 'none'
			})
			return
		}

		saveLoading.value = true
		try {
			const res = await updateUserInfo({
				nickname,
				gender: Number(form.value.gender || 0),
				avatar: String(form.value.avatar || '').trim()
			})
			if (res.code === 200) {
				const data = res && res.data && typeof res.data === 'object' ? res.data : {}
				if (data.user && typeof data.user === 'object') {
					applyUserProfile(data.user)
				}
				uni.showToast({
					title: '保存成功',
					icon: 'success'
				})
			}
		} catch (e) {
			console.error('保存失败', e)
		} finally {
			saveLoading.value = false
		}
	}

	/** 退出登录 */
	async function handleLogout() {
		const confirmed = await new Promise((resolve) => {
			uni.showModal({
				title: '提示',
				content: '确定要退出登录吗？',
				confirmColor: '#ff4d4f',
				success: (res) => resolve(!!res.confirm),
				fail: () => resolve(false)
			})
		})

		if (!confirmed) {
			return
		}

		await logout({}, {
			showError: false,
			loginRedirect: false
		})
		const redirectPath = savePostLoginRedirect(getCurrentPageUrl() || '/pages/users/userinfo/index')
		clearAuthStorage()
		uni.reLaunch({
			url: buildLoginPageUrl(redirectPath || '/pages/users/userinfo/index')
		})
	}
</script>

<style scoped lang="scss">
	.userinfo-page {
		display: flex;
		flex-direction: column;
		min-height: 100vh;
		background-color: #f9f9f9;
	}

	/* ========== 滚动区域 ========== */
	.userinfo-scroll {
		flex: 1;
		height: calc(100vh - 88rpx - var(--status-bar-height, 44px) - 240rpx);
	}

	.userinfo-content {
		padding: 24rpx 24rpx;
	}

	/* ========== 信息卡片 ========== */
	.info-card {
		margin-bottom: 20rpx;
		background-color: #ffffff;
		border-radius: 16rpx;
		overflow: hidden;
		box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);

		.uni-icons {
			padding-left: 10rpx;
		}

		/* 单行横排布局 — 用于头像、地址等只有一行的卡片 */
		&--row {
			display: flex;
			align-items: center;
			justify-content: space-between;
			padding: 28rpx 32rpx;
		}

		/* 多行纵排布局 — 用于包含多行的表单卡片 */
		&--list {
			display: flex;
			flex-direction: column;
			padding: 0 32rpx;
		}

		&--clickable {
			&:active {
				background-color: #f5f5f5;
			}
		}

		&__label {
			font-size: 28rpx;
			font-weight: 600;
			color: #1a1c1c;
			flex-shrink: 0;
		}

		&__right {
			display: flex;
			align-items: center;
			margin-left: 24rpx;
		}

		&__value {
			font-size: 28rpx;
			color: #999999;
		}

		&__input {
			flex: 1;
			text-align: right;
			font-size: 28rpx;
			color: #666666;
			background: transparent;
			border: none;
			padding: 0;
		}

		&__placeholder {
			color: #cccccc;
		}

		/* 行内每一项 */
		&__row {
			display: flex;
			align-items: center;
			justify-content: space-between;
			width: 100%;
			padding: 28rpx 0;

			&--clickable {
				&:active {
					background-color: #f5f5f5;
				}
			}

			.info-card__right {
				flex: 1;
				justify-content: flex-end;
			}
		}

		&__divider {
			height: 1rpx;
			background-color: #f0f0f0;
		}
	}

	.avatar-card-button {
		width: 100%;
		line-height: normal;
		text-align: left;
		border: 0;

		&::after {
			border: 0;
		}

		&[disabled] {
			opacity: 0.75;
		}
	}

	/* ========== 头像区域 ========== */
	.avatar-wrap {
		width: 96rpx;
		height: 96rpx;
		border-radius: 50%;
		overflow: hidden;
		border: 2rpx solid #f0f0f0;
		position: relative;
		flex-shrink: 0;

		&__img {
			width: 100%;
			height: 100%;
		}

		&__overlay {
			position: absolute;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background-color: rgba(0, 0, 0, 0.2);
			display: flex;
			align-items: center;
			justify-content: center;
			opacity: 0;
			transition: opacity 0.2s;
		}

		&:active &__overlay {
			opacity: 1;
		}
	}

	/* ========== 性别切换 ========== */
	.gender-switch {
		display: flex;
		align-items: center;
		background-color: #f3f3f3;
		border-radius: 8rpx;
		padding: 4rpx;
		flex-shrink: 0;

		&__item {
			padding: 12rpx 28rpx;
			border-radius: 6rpx;
			transition: all 0.2s;

			&--active {
				background-color: #ffffff;
				box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.08);
			}
		}

		&__text {
			font-size: 26rpx;
			color: #999999;
			transition: color 0.2s;

			&--active {
				font-weight: 500;
				color: #1a1c1c;
			}
		}
	}

	/* ========== 底部操作区 ========== */
	.userinfo-footer {
		position: fixed;
		left: 0;
		right: 0;
		bottom: 0;
		padding: 20rpx 32rpx;
		padding-bottom: calc(env(safe-area-inset-bottom) + 20rpx);
		background-color: #f9f9f9;
	}

	/* #ifdef H5 */
	.userinfo-footer {
		bottom: var(--window-bottom, 0);
	}

	/* #endif */

	/* ========== 按钮 ========== */
	.btn {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 100%;
		height: 96rpx;
		border-radius: 56rpx;
		font-size: 30rpx;
		font-weight: 600;
		border: none;
		margin-bottom: 20rpx;
		box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);

		&::after {
			border: none;
		}

		&--primary {
			background-color: var(--brand-primary, #ea580c);
			color: #ffffff;

			&:active {
				opacity: 0.9;
				transform: scale(0.98);
			}
		}

		&--logout {
			background-color: #ffffff;
			color: #ff4d4f;

			&:active {
				background-color: #fafafa;
			}
		}
	}
</style>
