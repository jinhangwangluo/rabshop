<template>
	<page-meta :page-style="themePageStyle"></page-meta>
	<view class="login-page"
		:class="{ 'login-page--h5': isDefaultH5Login, 'login-page--miniapp': isMiniappLoginScene, 'login-page--wechat': isWechatLoginScene, 'login-page--forgot': activeView === 'forgot' }">
		<image class="login-page__bg" src="/static/images/login-bg.png" mode="aspectFill"></image>
		<app-safe-top v-if="shouldShowTopbar" :title="topbarTitle" :show-back="true"
			:emit-back="true" :back-icon="topbarBackIcon" background="transparent"
			title-color="#1a1c1c" icon-color="#1a1c1c" @back="handleTopbarBack" />

		<template v-if="isMiniappLoginScene">
			<view class="miniapp-login-page">
				<MiniappAuthPanel class="miniapp-login-page__panel" :app-name="miniappAppName" :logo-src="appLogo"
					:login-loading="miniappLoading" :phone-binding="bindPhoneLoading" :user-info="userInfo"
					:logged-in="loggedIn"
					:show-phone-prompt="miniappPhonePromptVisible" :phone-prompt-required="miniappForceBindPhone"
					@login="handleMiniappLogin"
					@bind-phone="handleMiniappBindPhone" @phone-prompt-cancel="handleMiniappPhonePromptCancel"
					@cancel="handleTopbarBack" />

				<view class="miniapp-login-page__footer">
					<checkbox-group class="miniapp-login-page__agreement" @change="handleAgreementChange">
						<label class="agreement-checker">
							<checkbox class="agreement-checker__checkbox" value="agreement" :checked="agreementChecked"
								:color="themePrimaryColor" />
							<view class="agreement-checker__content">
								<text class="agreement-checker__text">我已阅读并同意</text>
								<text class="agreement-checker__link"
									@click.stop="handleViewAgreement('user')">《用户协议》</text>
								<text class="agreement-checker__text">与</text>
								<text class="agreement-checker__link"
									@click.stop="handleViewAgreement('privacy')">《隐私政策》</text>
							</view>
						</label>
					</checkbox-group>
					<view class="miniapp-login-page__dots">
						<view class="miniapp-login-page__dot"></view>
						<view class="miniapp-login-page__dot"></view>
						<view class="miniapp-login-page__dot"></view>
					</view>
				</view>
			</view>
		</template>

		<template v-else-if="isWechatLoginScene">
			<view class="wechat-login-page">
				<OaAuthPanel class="wechat-login-page__panel" :app-name="appName" :logo-src="appLogo"
					:loading="wechatLoading" @authorize="handleWechatAuthorize" />

				<view class="wechat-login-page__footer">
					<checkbox-group class="wechat-login-page__agreement" @change="handleAgreementChange">
						<label class="agreement-checker">
							<checkbox class="agreement-checker__checkbox" value="agreement" :checked="agreementChecked"
								:color="themePrimaryColor" />
							<view class="agreement-checker__content">
								<text class="agreement-checker__text">我已阅读并同意</text>
								<text class="agreement-checker__link"
									@click.stop="handleViewAgreement('user')">《用户协议》</text>
								<text class="agreement-checker__text">与</text>
								<text class="agreement-checker__link"
									@click.stop="handleViewAgreement('privacy')">《隐私政策》</text>
							</view>
						</label>
					</checkbox-group>
				</view>
			</view>
		</template>

		<template v-else>
			<scroll-view v-if="activeView === 'login'" scroll-y class="auth-page__scroll">
				<view class="auth-page__inner">
					<view class="auth-brand">
						<view class="auth-brand__logo">
							<image class="auth-brand__logo-image" :src="appLogo" mode="aspectFill"></image>
						</view>
						<text class="auth-brand__name">{{ appName }}</text>
						<text class="auth-brand__desc">{{ brandDesc }}</text>
					</view>

					<view class="auth-card">
						<view class="auth-card__panel">
							<OaAuthPanel v-if="activeMode === 'wechat'" :loading="wechatLoading"
								@authorize="handleWechatAuthorize" />
							<MiniappAuthPanel v-else-if="activeMode === 'miniapp'" :app-name="miniappAppName"
								:logo-src="appLogo" :login-loading="miniappLoading" :phone-binding="bindPhoneLoading"
								:user-info="userInfo" :logged-in="loggedIn"
								:show-phone-prompt="miniappPhonePromptVisible"
								:phone-prompt-required="miniappForceBindPhone" @login="handleMiniappLogin"
								@bind-phone="handleMiniappBindPhone"
								@phone-prompt-cancel="handleMiniappPhonePromptCancel" @cancel="handleTopbarBack" />
							<H5SmsForm v-else-if="activeMode === 'sms'" :loading="smsLoading" :countdown="countdown"
								@request-captcha="handleRequestCaptcha('sms', $event)" @submit="handlePhoneLogin" />
							<H5PasswordForm v-else :loading="passwordLoading" @submit="handlePasswordLogin"
								@forgot-password="handleForgotPassword" />
						</view>

						<view class="auth-mode-switch" hover-class="auth-mode-switch--hover" @click="handleSwitchLoginMode">
							<text class="auth-mode-switch__text">{{ authModeSwitchText }}</text>
						</view>
					</view>

					<view class="auth-footer">
						<checkbox-group class="auth-footer__agreement" @change="handleAgreementChange">
							<label class="agreement-checker">
								<checkbox class="agreement-checker__checkbox" value="agreement"
									:checked="agreementChecked" :color="themePrimaryColor" />
								<view class="agreement-checker__content">
									<text class="agreement-checker__text">我已阅读并同意</text>
									<text class="agreement-checker__link"
										@click.stop="handleViewAgreement('user')">《用户协议》</text>
									<text class="agreement-checker__text">与</text>
									<text class="agreement-checker__link"
										@click.stop="handleViewAgreement('privacy')">《隐私政策》</text>
								</view>
							</label>
						</checkbox-group>
					</view>
				</view>
			</scroll-view>

			<scroll-view v-else scroll-y class="auth-page__scroll">
				<view class="auth-page__inner">
					<ForgotPasswordForm :loading="forgotLoading" :countdown="forgotCountdown"
						@request-captcha="handleRequestCaptcha('forgot', $event)" @submit="handleForgotSubmit"
						@back="handleForgotBack" />
				</view>
			</scroll-view>
		</template>

		<sms-captcha-popup :visible="captchaVisible" :phone="captchaPhone" @sent="handleCaptchaSent"
			@close="captchaVisible = false" />
	</view>
</template>

<script setup>
import SmsCaptchaPopup from '@/components/sms-captcha-popup/index.vue'
import ForgotPasswordForm from './components/ForgotPasswordForm.vue'
import H5PasswordForm from './components/H5PasswordForm.vue'
import H5SmsForm from './components/H5SmsForm.vue'
import MiniappAuthPanel from './components/MiniappAuthPanel.vue'
import OaAuthPanel from './components/OaAuthPanel.vue'
import { accountLogin, getCurrentUser, phoneLogin, resetPassword } from '@/api/auth'
import { bindMiniappPhone, miniappLogin } from '@/api/miniapp'
import { getWechatOauthRedirect, resolveWechatOauthCallback } from '@/api/wechat'
import { clearAuthStorage, getUserInfo, isLoggedIn, redirectAfterLogin, resolveOauthTokenFromUrl, saveAuthResult } from '@/utils/auth'
import { buildAgreementUrl } from '@/utils/agreement'
import { PLATFORM_SCENES, getPlatformScene } from '@/utils/platform'
import { appendSpreadToPayload, clearStoredSpreadUid, captureSpreadFromLaunchOptions } from '@/utils/spread'
import { computed, onBeforeUnmount, ref } from 'vue'
import { onLoad, onUnload } from '@dcloudio/uni-app'

defineOptions({ name: 'LoginIndex' })

const DEFAULT_APP_NAME = '乖乖兔'
const DEFAULT_APP_LOGO = '/static/logo.png'

const activeView = ref('login')
const scene = ref(PLATFORM_SCENES.H5)
const activeMode = ref('password')
const countdown = ref(0)
const countdownTimer = ref(null)
const forgotCountdown = ref(0)
const forgotCountdownTimer = ref(null)
const smsLoading = ref(false)
const passwordLoading = ref(false)
const wechatLoading = ref(false)
const miniappLoading = ref(false)
const bindPhoneLoading = ref(false)
const forgotLoading = ref(false)
const loggedIn = ref(false)
const userInfo = ref({})
const captchaVisible = ref(false)
const captchaPhone = ref('')
const captchaScene = ref('sms')
const agreementChecked = ref(false)
const appName = ref(DEFAULT_APP_NAME)
const appLogo = ref(DEFAULT_APP_LOGO)
const miniappAppName = ref(DEFAULT_APP_NAME)
const miniappForceBindPhone = ref(false)
const miniappPhonePromptVisible = ref(false)
const redirectPath = ref('')
const wechatOauthResolving = ref(false)
const wechatOauthHandled = ref(false)

const isMiniappLoginScene = computed(() => {
  return activeView.value === 'login' && scene.value === PLATFORM_SCENES.MINIAPP
})
const isWechatLoginScene = computed(() => {
  return activeView.value === 'login' && scene.value === PLATFORM_SCENES.WECHAT_H5
})
const isDefaultH5Login = computed(() => {
  return activeView.value === 'login' && !isMiniappLoginScene.value && !isWechatLoginScene.value
})
const shouldShowTopbar = computed(() => {
  return !isDefaultH5Login.value
})
const topbarTitle = computed(() => {
  if (isMiniappLoginScene.value) {
    return ''
  }
  return activeView.value === 'forgot' ? '重置密码' : '登录'
})
const topbarBackIcon = computed(() => {
  return activeView.value === 'forgot' ? 'left' : 'closeempty'
})
const authModeSwitchText = computed(() => {
  return activeMode.value === 'password' ? '使用验证码登录/注册' : '使用密码登录'
})
const brandDesc = computed(() => {
  if (scene.value === PLATFORM_SCENES.WECHAT_H5) {
    return '完成授权后可继续购买课程、观看直播与管理订单'
  }

  return '登录后可继续学习课程、参与直播与管理个人资产'
})

onLoad(async (options = {}) => {
  const pageOptions = options || {}
  captureSpreadFromLaunchOptions({
    query: pageOptions
  })
  resolveOauthTokenFromUrl()
  scene.value = resolveLoginScene(pageOptions.scene)
  if (hasWechatOauthParams(pageOptions)) {
    scene.value = PLATFORM_SCENES.WECHAT_H5
  }
  if (pageOptions.redirect) {
    try {
      redirectPath.value = decodeURIComponent(pageOptions.redirect)
    } catch (e) {
      redirectPath.value = String(pageOptions.redirect || '')
    }
  }
  if (pageOptions.appName) {
    miniappAppName.value = decodeURIComponent(pageOptions.appName)
    appName.value = miniappAppName.value
  }
  if (pageOptions.logo) {
    appLogo.value = decodeURIComponent(pageOptions.logo)
  }
  syncActiveMode()
  if (await handleWechatOauthCallback(pageOptions)) {
    return
  }
  bootstrapPage()
})
onUnload(() => {
  clearCountdownTimer()
  clearForgotCountdownTimer()
})
onBeforeUnmount(() => {
  clearCountdownTimer()
  clearForgotCountdownTimer()
})

/**
 * 解析登录页终端场景。
 *
 * 小程序扫码进入时 options.scene 可能是二维码业务参数，不能直接作为登录终端场景。
 *
 * @param {string} scene 页面参数中的场景值。
 * @returns {string} 规范化后的登录终端场景。
 */
function resolveLoginScene(scene = '') {
  const normalized = String(scene || '').trim()
  return Object.values(PLATFORM_SCENES).includes(normalized) ? normalized : getPlatformScene()
}
function handleSwitchLoginMode() {
  activeMode.value = activeMode.value === 'password' ? 'sms' : 'password'
}
async function bootstrapPage() {
  if (!isLoggedIn()) return
  const loaded = await loadCurrentUser(true)
  if (loaded && scene.value === PLATFORM_SCENES.MINIAPP && shouldRequireMiniappPhoneBind()) {
    openMiniappPhonePrompt()
    return
  }
  if (loaded) {
    finishRedirect()
  }
}
function syncActiveMode() {
  const defaultModeMap = {
    [PLATFORM_SCENES.H5]: 'password',
    [PLATFORM_SCENES.WECHAT_H5]: 'wechat',
    [PLATFORM_SCENES.MINIAPP]: 'miniapp'
  }
  activeMode.value = defaultModeMap[scene.value] || 'sms'
}
async function loadCurrentUser(silent = false) {
  const result = await getCurrentUser({}, {
    showError: !silent
  })
  if (result.code !== 200 || !result.data) {
    loggedIn.value = false
    userInfo.value = {}
    return false
  }
  saveAuthResult(result.data)
  syncAuthConfig(result.data)
  userInfo.value = getUserInfo()
  loggedIn.value = true
  return true
}
function handleForgotPassword() {
  activeView.value = 'forgot'
}
function handleForgotBack() {
  activeView.value = 'login'
}
function handleTopbarBack() {
  if (activeView.value === 'forgot') {
    handleForgotBack()
    return
  }
  const pages = getCurrentPages()
  if (Array.isArray(pages) && pages.length > 1) {
    uni.navigateBack({
      fail: () => {
        uni.switchTab({
          url: '/pages/index/index'
        })
      }
    })
    return
  }
  uni.switchTab({
    url: '/pages/index/index'
  })
}
function handleAgreementChange(event) {
  const values = event && event.detail && Array.isArray(event.detail.value) ? event.detail.value : []
  agreementChecked.value = values.includes('agreement')
}
function handleViewAgreement(type) {
  uni.navigateTo({
    url: buildAgreementUrl(type)
  })
}
async function ensureAgreementAccepted() {
  if (agreementChecked.value) {
    return true
  }

  uni.showToast({
    title: '请先勾选用户协议和隐私政策',
    icon: 'none'
  })

  return false
}
function isValidPhone(phone = '') {
  return /^1\d{10}$/.test(String(phone || '').trim())
}
function startCountdown() {
  clearCountdownTimer()
  countdown.value = 60
  countdownTimer.value = setInterval(() => {
    if (countdown.value <= 1) {
      clearCountdownTimer()
      return
    }
    countdown.value -= 1
  }, 1000)
}
function clearCountdownTimer() {
  if (countdownTimer.value) {
    clearInterval(countdownTimer.value)
    countdownTimer.value = null
  }
  countdown.value = 0
}
function startForgotCountdown() {
  clearForgotCountdownTimer()
  forgotCountdown.value = 60
  forgotCountdownTimer.value = setInterval(() => {
    if (forgotCountdown.value <= 1) {
      clearForgotCountdownTimer()
      return
    }
    forgotCountdown.value -= 1
  }, 1000)
}
function clearForgotCountdownTimer() {
  if (forgotCountdownTimer.value) {
    clearInterval(forgotCountdownTimer.value)
    forgotCountdownTimer.value = null
  }
  forgotCountdown.value = 0
}
function handleRequestCaptcha(scene, payload) {
  const phone = String((payload && payload.phone) || '').trim()
  if (!isValidPhone(phone)) {
    uni.showToast({
      title: '请输入正确的手机号',
      icon: 'none'
    })
    return
  }
  captchaScene.value = scene
  captchaPhone.value = phone
  captchaVisible.value = true
}
function handleCaptchaSent() {
  captchaVisible.value = false
  if (captchaScene.value === 'sms') {
    startCountdown()
  } else if (captchaScene.value === 'forgot') {
    startForgotCountdown()
  }
}
async function handlePhoneLogin(form) {
  if (!isValidPhone(form.phone)) {
    uni.showToast({
      title: '请输入正确的手机号',
      icon: 'none'
    })
    return
  }
  if (!/^\d{4,6}$/.test(String(form.code || ''))) {
    uni.showToast({
      title: '请输入正确的验证码',
      icon: 'none'
    })
    return
  }
  if (!(await ensureAgreementAccepted())) {
    return
  }
  smsLoading.value = true
  const result = await phoneLogin(appendSpreadToPayload(form))
  smsLoading.value = false
  handleLoginResult(result, '登录成功')
}
async function handlePasswordLogin(form) {
  const account = String(form.account || '').trim()
  if (!isValidPhone(account)) {
    uni.showToast({
      title: '请输入正确的手机号',
      icon: 'none'
    })
    return
  }
  if (!String(form.password || '').trim()) {
    uni.showToast({
      title: '请输入登录密码',
      icon: 'none'
    })
    return
  }
  if (!(await ensureAgreementAccepted())) {
    return
  }
  passwordLoading.value = true
  const result = await accountLogin(appendSpreadToPayload({
    account,
    password: form.password
  }))
  passwordLoading.value = false
  handleLoginResult(result, '登录成功')
}
async function handleForgotSubmit(form) {
  if (!isValidPhone(form.phone)) {
    uni.showToast({
      title: '请输入正确的手机号',
      icon: 'none'
    })
    return
  }
  if (!form.code) {
    uni.showToast({
      title: '请输入短信验证码',
      icon: 'none'
    })
    return
  }
  if (!form.password) {
    uni.showToast({
      title: '请输入新密码',
      icon: 'none'
    })
    return
  }
  if (String(form.password).length < 6 || String(form.password).length > 20) {
    uni.showToast({
      title: '密码长度为6-20位',
      icon: 'none'
    })
    return
  }
  if (form.password !== form.confirmPassword) {
    uni.showToast({
      title: '两次密码输入不一致',
      icon: 'none'
    })
    return
  }
  forgotLoading.value = true
  const result = await resetPassword({
    phone: form.phone,
    code: form.code,
    password: form.password
  })
  forgotLoading.value = false
  if (result && result.code === 200) {
    uni.showToast({
      title: '密码重置成功',
      icon: 'none'
    })
    clearForgotCountdownTimer()
    setTimeout(() => {
      activeView.value = 'login'
    }, 800)
  }
}
function handleLoginResult(result, message) {
  if (result.code !== 200 || !result.data) return
  saveAuthResult(result.data)
  syncAuthConfig(result.data)
  userInfo.value = getUserInfo()
  loggedIn.value = true
  clearStoredSpreadUid()
  uni.showToast({
    title: message,
    icon: 'none'
  })
  finishRedirect()
}
/**
 * 同步后端返回的移动端登录策略配置。
 * @param {Object} payload 登录或用户信息接口返回的数据。
 * @returns {void}
 */
function syncAuthConfig(payload = {}) {
  const authConfig = payload && payload.auth_config && typeof payload.auth_config === 'object' ?
    payload.auth_config :
    {}
  miniappForceBindPhone.value = normalizeBoolean(authConfig.miniapp_force_bind_phone)
}
/**
 * 将后台配置值标准化为布尔值。
 * @param {*} value 后台配置原始值。
 * @returns {boolean} 是否开启。
 */
function normalizeBoolean(value) {
  if (typeof value === 'boolean') {
    return value
  }
  if (typeof value === 'number') {
    return value === 1
  }
  return ['1', 'true', 'yes', 'on'].includes(String(value || '').trim().toLowerCase())
}
/**
 * 判断当前用户是否已绑定手机号。
 * @param {Object} userInfo 用户信息。
 * @returns {boolean} 是否存在手机号。
 */
function hasBoundPhone(currentUserInfo = userInfo.value) {
  return String(currentUserInfo && currentUserInfo.phone || '').trim() !== ''
}
/**
 * 判断小程序端是否必须先绑定手机号。
 * @returns {boolean} 是否需要强制绑定。
 */
function shouldRequireMiniappPhoneBind() {
  return scene.value === PLATFORM_SCENES.MINIAPP && miniappForceBindPhone.value && !hasBoundPhone()
}
/**
 * 打开小程序手机号授权弹窗。
 * @returns {void}
 */
function openMiniappPhonePrompt() {
  if (!loggedIn.value) {
    return
  }
  miniappPhonePromptVisible.value = true
}
/**
 * 处理手机号授权弹窗取消动作。
 * @returns {void}
 */
function handleMiniappPhonePromptCancel() {
  if (shouldRequireMiniappPhoneBind()) {
    clearMiniappPendingLoginState()
    handleTopbarBack()
    return
  }
  miniappPhonePromptVisible.value = false
  finishRedirect()
}
/**
 * 清理小程序授权登录后、手机号未绑定前产生的临时登录态。
 * @returns {void}
 */
function clearMiniappPendingLoginState() {
  clearAuthStorage()
  loggedIn.value = false
  userInfo.value = {}
  miniappPhonePromptVisible.value = false
  bindPhoneLoading.value = false
}
/**
 * 处理小程序微信授权登录结果。
 * @param {Object} result 登录接口返回值。
 * @returns {void}
 */
function handleMiniappLoginResult(result) {
  if (result.code !== 200 || !result.data) return
  saveAuthResult(result.data)
  syncAuthConfig(result.data)
  userInfo.value = getUserInfo()
  loggedIn.value = true
  clearStoredSpreadUid()
  uni.showToast({
    title: '微信登录成功',
    icon: 'none'
  })
  if (shouldRequireMiniappPhoneBind()) {
    openMiniappPhonePrompt()
    return
  }
  finishRedirect()
}
function getMiniappLoginCode() {
  return new Promise((resolve) => {
    uni.login({
      provider: 'weixin',
      success: (res) => resolve(String(res.code || '')),
      fail: () => resolve('')
    })
  })
}
function getMiniappUserInfo() {
  return new Promise((resolve) => {
    // #ifdef MP-WEIXIN
    if (typeof uni.getUserProfile === 'function') {
      uni.getUserProfile({
        desc: '用于完善会员资料',
        success: (res) => resolve(res.userInfo || {}),
        fail: () => resolve({})
      })
      return
    }
    // #endif
    resolve({})
  })
}
function getMiniappAppId() {
  // #ifdef MP-WEIXIN
  if (typeof uni.getAccountInfoSync === 'function') {
    const accountInfo = uni.getAccountInfoSync()
    return String(accountInfo && accountInfo.miniProgram && accountInfo.miniProgram.appId || '').trim()
  }
  // #endif

  return ''
}
async function handleMiniappLogin() {
  if (!(await ensureAgreementAccepted())) {
    return
  }
  miniappLoading.value = true
  const rawUserInfo = await getMiniappUserInfo()
  const code = await getMiniappLoginCode()
  if (!code) {
    miniappLoading.value = false
    uni.showToast({
      title: '获取登录凭证失败',
      icon: 'none'
    })
    return
  }
  const result = await miniappLogin(appendSpreadToPayload({
    code,
    userInfo: rawUserInfo,
    appid: getMiniappAppId()
  }))
  miniappLoading.value = false
  handleMiniappLoginResult(result)
}
async function handleMiniappBindPhone(event) {
  const detail = event && event.detail ? event.detail : {}
  const code = String(detail.code || '').trim()
  if (!code) {
    uni.showToast({
      title: '请先授权手机号',
      icon: 'none'
    })
    return
  }
  if (!(await ensureAgreementAccepted())) {
    return
  }
  bindPhoneLoading.value = true
  const result = await bindMiniappPhone({
    code
  })
  bindPhoneLoading.value = false
  if (result.code !== 200 || !result.data) return
  saveAuthResult(result.data)
  syncAuthConfig(result.data)
  userInfo.value = getUserInfo()
  loggedIn.value = true
  miniappPhonePromptVisible.value = false
  uni.showToast({
    title: '手机号绑定成功',
    icon: 'none'
  })
  finishRedirect()
}
/**
 * 判断页面参数是否包含微信公众号 OAuth 回调参数。
 * @param {Object} options 页面启动参数。
 * @returns {boolean} 包含 code 和 state 时返回 true。
 */
function hasWechatOauthParams(options = {}) {
  const params = getWechatOauthParams(options)
  return params.code !== '' && params.state !== ''
}
/**
 * 从页面参数或 H5 地址栏中提取微信公众号 OAuth 回调参数。
 * @param {Object} options 页面启动参数。
 * @returns {{code:string,state:string}} 回调参数。
 */
function getWechatOauthParams(options = {}) {
  const params = {
    code: String(options.code || '').trim(),
    state: String(options.state || '').trim()
  }

  // #ifdef H5
  if (typeof window !== 'undefined' && window.location) {
    const searchParams = new URLSearchParams(String(window.location.search || ''))
    if (!params.code) {
      params.code = String(searchParams.get('code') || '').trim()
    }
    if (!params.state) {
      params.state = String(searchParams.get('state') || '').trim()
    }
    const hashParams = getWechatOauthHashParams()
    if (!params.code) {
      params.code = hashParams.code
    }
    if (!params.state) {
      params.state = hashParams.state
    }
  }
  // #endif

  return params
}
/**
 * 从 H5 hash 路由中提取 OAuth 参数。
 * @returns {{code:string,state:string}} 回调参数。
 */
function getWechatOauthHashParams() {
  const params = {
    code: '',
    state: ''
  }

  // #ifdef H5
  if (typeof window === 'undefined' || !window.location) {
    return params
  }

  const hash = String(window.location.hash || '')
  const queryIndex = hash.indexOf('?')
  if (queryIndex === -1) {
    return params
  }

  const hashQuery = hash.slice(queryIndex + 1).split('#')[0]
  const searchParams = new URLSearchParams(hashQuery)
  params.code = String(searchParams.get('code') || '').trim()
  params.state = String(searchParams.get('state') || '').trim()
  // #endif

  return params
}
/**
 * 清理 hash 路由中的 OAuth 临时参数。
 * @param {string} hash 原始 hash。
 * @returns {string} 清理后的 hash。
 */
function cleanWechatOauthHash(hash = '') {
  const source = String(hash || '')
  const queryIndex = source.indexOf('?')
  if (queryIndex === -1) {
    return source
  }

  const path = source.slice(0, queryIndex)
  const queryWithFragment = source.slice(queryIndex + 1)
  const fragmentIndex = queryWithFragment.indexOf('#')
  const query = fragmentIndex === -1 ? queryWithFragment : queryWithFragment.slice(0, fragmentIndex)
  const tail = fragmentIndex === -1 ? '' : queryWithFragment.slice(fragmentIndex)
  const params = new URLSearchParams(query)
  params.delete('code')
  params.delete('state')
  const nextQuery = params.toString()

  return `${path}${nextQuery ? `?${nextQuery}` : ''}${tail}`
}
/**
 * 清理 H5 地址栏中的 OAuth 临时参数。
 * @returns {void}
 */
function cleanWechatOauthParamsFromUrl() {
  // #ifdef H5
  if (typeof window === 'undefined' || !window.location || !window.history) {
    return
  }

  const params = new URLSearchParams(String(window.location.search || ''))
  const nextHash = cleanWechatOauthHash(window.location.hash || '')
  if (!params.has('code') && !params.has('state') && nextHash === window.location.hash) {
    return
  }

  params.delete('code')
  params.delete('state')
  const nextQuery = params.toString()
  const nextUrl = `${window.location.pathname}${nextQuery ? `?${nextQuery}` : ''}${nextHash}`
  window.history.replaceState({}, document.title, nextUrl)
  // #endif
}
/**
 * 处理微信回跳到前端登录页后的授权解析。
 * @param {Object} options 页面启动参数。
 * @returns {Promise<boolean>} 成功解析并保存登录态时返回 true。
 */
async function handleWechatOauthCallback(options = {}) {
  const params = getWechatOauthParams(options)
  if (!params.code || !params.state || wechatOauthHandled.value || wechatOauthResolving.value) {
    return false
  }

  wechatOauthResolving.value = true
  wechatLoading.value = true
  const result = await resolveWechatOauthCallback(params, {
    showError: true
  })
  wechatLoading.value = false
  wechatOauthResolving.value = false
  wechatOauthHandled.value = true
  cleanWechatOauthParamsFromUrl()
  handleLoginResult(result, '授权登录成功')
  return result.code === 200 && !!result.data
}
function getWechatRedirectUri() {
  // #ifdef H5
  if (typeof window !== 'undefined' && window.location) {
    const params = new URLSearchParams(String(window.location.search || ''))
    params.delete('code')
    params.delete('state')
    const nextQuery = params.toString()
    const nextHash = cleanWechatOauthHash(window.location.hash || '')
    return `${window.location.origin}${window.location.pathname}${nextQuery ? `?${nextQuery}` : ''}${nextHash}`
  }
  // #endif
  return ''
}
async function handleWechatAuthorize() {
  const redirectUri = getWechatRedirectUri()
  if (!redirectUri) {
    uni.showToast({
      title: '当前环境不支持公众号授权',
      icon: 'none'
    })
    return
  }
  if (!(await ensureAgreementAccepted())) {
    return
  }
  wechatLoading.value = true
  const result = await getWechatOauthRedirect(appendSpreadToPayload({
    redirect_uri: redirectUri,
    scope: 'snsapi_userinfo'
  }))
  wechatLoading.value = false
  if (result.code !== 200 || !result.data || !result.data.redirect_url) return
  // #ifdef H5
  window.location.href = result.data.redirect_url
  // #endif
}
function finishRedirect() {
  redirectAfterLogin(redirectPath.value)
}
</script>

<style lang="scss">
	page {
		min-height: 100%;
		background-color: var(--brand-primary-pale, #fff7ed);
	}
</style>

<style scoped lang="scss">
	.login-page {
		min-height: 100vh;
		background: var(--brand-primary-pale, #fff7ed);
		display: flex;
		flex-direction: column;
		position: relative;
	}

	.login-page__bg {
		position: absolute;
		left: 0;
		top: 0;
		right: 0;
		bottom: 0;
		width: 100%;
		height: 100%;
		z-index: 0;
	}

	/* #ifdef H5 */
	.login-page--wechat .login-page__bg {
		position: fixed;
	}
	/* #endif */

	.login-page--miniapp {
		padding-bottom: env(safe-area-inset-bottom);
	}

	.login-page--wechat {
		padding-bottom: env(safe-area-inset-bottom);
	}

	/* #ifdef H5 */
	.login-page--wechat {
		padding-bottom: calc(env(safe-area-inset-bottom) + var(--window-bottom, 0px));
	}
	/* #endif */

	.auth-page__scroll {
		flex: 1;
		min-height: 0;
		position: relative;
		z-index: 1;
	}

	.auth-page__inner {
		padding: 24rpx 32rpx 80rpx;
	}

	.login-page--h5 .auth-page__inner {
		box-sizing: border-box;
		min-height: 100%;
		padding: 128rpx 70rpx 88rpx;
	}

	.login-page--forgot .auth-page__inner {
		position: relative;
		z-index: 1;
	}

	.miniapp-login-page {
		flex: 1;
		display: flex;
		flex-direction: column;
		padding: 8rpx 32rpx 24rpx;
		position: relative;
		z-index: 1;
	}

	.wechat-login-page {
		flex: 1;
		display: flex;
		flex-direction: column;
		padding: 8rpx 32rpx 24rpx;
		position: relative;
		z-index: 1;
	}

	.miniapp-login-page__panel {
		flex: 1;
	}

	.wechat-login-page__panel {
		flex: 1;
	}

	.miniapp-login-page__footer {
		padding: 32rpx 0 8rpx;
	}

	.wechat-login-page__footer {
		padding: 32rpx 0 8rpx;
	}

	.miniapp-login-page__agreement {
		display: flex;
		justify-content: center;
	}

	.wechat-login-page__agreement {
		display: flex;
		justify-content: center;
	}

	.agreement-checker {
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.agreement-checker__checkbox {
		width: 30rpx;
		height: 30rpx;
		margin-right: 8rpx;
		margin-top: 0;
		display: flex;
		align-items: center;
		justify-content: center;
		line-height: 30rpx;
		flex-shrink: 0;
		// overflow: hidden;
	}

	.agreement-checker__checkbox ::v-deep .uni-checkbox-input,
	.agreement-checker__checkbox ::v-deep .wx-checkbox-input {
		width: 30rpx !important;
		height: 30rpx !important;
		border-radius: 50%;
	}

	.agreement-checker__content {
		display: flex;
		align-items: center;
		justify-content: flex-start;
		flex-wrap: wrap;
	}

	.agreement-checker__text,
	.agreement-checker__link {
		font-size: 24rpx;
		line-height: 1.8;
	}

	.agreement-checker__text {
		color: #777777;
		margin-right: 6rpx;
	}

	.agreement-checker__link {
		color: var(--brand-primary, #ea580c);
		font-weight: 700;
		margin: 0 6rpx;
	}

	.miniapp-login-page__dots {
		display: flex;
		justify-content: center;
		align-items: center;
		margin-top: 28rpx;
	}

	.miniapp-login-page__dot {
		width: 12rpx;
		height: 12rpx;
		border-radius: 50%;
		background: #c6c6c6;
		margin: 0 8rpx;
	}

	.auth-brand {
		display: flex;
		flex-direction: column;
		align-items: center;
		padding: 28rpx 0 44rpx;
	}

	.login-page--h5 .auth-brand {
		padding: 0 0 104rpx;
	}

	.auth-brand__logo {
		width: 128rpx;
		height: 128rpx;
		background: #ffffff;
		border-radius: 50%;
		display: flex;
		justify-content: center;
		align-items: center;
		box-shadow: 0 12rpx 32rpx rgba(15, 23, 42, 0.08);
		border: 1rpx solid rgba(226, 232, 240, 0.9);
		margin-bottom: 24rpx;
		overflow: hidden;
	}

	.login-page--h5 .auth-brand__logo {
		width: 112rpx;
		height: 112rpx;
		border-radius: 28rpx;
		background: transparent;
		box-shadow: none;
		border: none;
		margin-bottom: 30rpx;
	}

	.auth-brand__logo-image {
		width: 100%;
		height: 100%;
	}

	.auth-brand__name {
		max-width: 560rpx;
		font-size: 46rpx;
		font-weight: 700;
		line-height: 56rpx;
		color: var(--text-title, #18181b);
		text-align: center;
		margin-bottom: 14rpx;
	}

	.auth-brand__desc {
		max-width: 600rpx;
		font-size: 26rpx;
		line-height: 36rpx;
		color: var(--text-secondary, #71717a);
		text-align: center;
	}

	.auth-card {
		background: #ffffff;
		border-radius: 32rpx;
		padding: 48rpx 40rpx;
		box-shadow: 0 8rpx 40rpx rgba(0, 0, 0, 0.04);
	}

	.login-page--h5 .auth-card {
		background: transparent;
		border-radius: 0;
		padding: 0;
		box-shadow: none;
	}

	.auth-mode-switch {
		display: flex;
		justify-content: center;
		align-items: center;
		min-height: 80rpx;
		margin-top: 26rpx;
	}

	.auth-mode-switch--hover {
		opacity: 0.86;
	}

	.auth-mode-switch__text {
		font-size: 28rpx;
		line-height: 40rpx;
		font-weight: 700;
		color: var(--brand-primary, #ea580c);
	}

	.auth-footer {
		display: flex;
		flex-direction: column;
		align-items: center;
		margin-top: 48rpx;
	}

	.login-page--h5 .auth-footer {
		margin-top: 54rpx;
	}

	.auth-footer__agreement {
		display: flex;
		justify-content: center;
		max-width: 560rpx;
	}

	.login-page--h5 .auth-footer__agreement {
		max-width: 100%;
	}

	.auth-footer .agreement-checker__text,
	.auth-footer .agreement-checker__link {
		font-size: 24rpx;
		line-height: 36rpx;
	}

	.login-page--miniapp ::v-deep .app-safe-top__nav {
		min-height: 88rpx;
		padding: 0 20rpx;
	}

	.login-page--miniapp ::v-deep .app-safe-top__back {
		left: 12rpx;
	}

	::v-deep .app-safe-top__nav {
		min-height: 88rpx;
		padding: 0 96rpx;
	}

	::v-deep .app-safe-top__back {
		left: 20rpx;
	}
</style>
