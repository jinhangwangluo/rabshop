<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="phoneedit-page">
    <app-safe-top title="修改手机号" background="#fff" />

    <view class="phoneedit-content">
      <view class="hero-block">
        <text class="hero-block__title">修改手机号码</text>
        <text class="hero-block__desc">通过绑定新的手机号码来更新您的账户安全。我们将发送验证码以确认更改。</text>
      </view>

      <view class="form-card">
        <view class="field-block">
          <text class="field-block__label">新手机号码</text>
          <view class="field-input field-input--with-prefix" :class="{ 'field-input--focused': focusField === 'phone' }">
            <view class="field-prefix">
              <text class="field-prefix__icon">☎</text>
              <text class="field-prefix__code">+86</text>
            </view>
            <input
              v-model.trim="form.phone"
              class="field-input__control"
              type="number"
              maxlength="11"
              placeholder="请输入手机号"
              placeholder-class="field-input__placeholder"
              @focus="focusField = 'phone'"
              @blur="handleFieldBlur"
            />
          </view>
        </view>

        <view class="field-block field-block--last">
          <text class="field-block__label">验证码</text>
          <view class="code-row">
            <view class="field-input code-row__input" :class="{ 'field-input--focused': focusField === 'code' }">
              <input
                v-model.trim="form.code"
                class="field-input__control field-input__control--spaced"
                type="number"
                maxlength="6"
                placeholder="6位验证码"
                placeholder-class="field-input__placeholder"
                @focus="focusField = 'code'"
                @blur="handleFieldBlur"
              />
            </view>
            <button
              class="code-row__button"
              :class="{ 'code-row__button--disabled': countdown > 0 }"
              :disabled="countdown > 0"
              @click="handleRequestCode"
            >
              {{ countdown > 0 ? `${countdown}s` : '获取验证码' }}
            </button>
          </view>
          <text class="field-block__tip">
            未收到？
            <text class="field-block__tip-accent">{{ countdown > 0 ? `${countdown}s` : '重新发送' }}</text>
            <text>{{ countdown > 0 ? ' 后重新发送' : ' 验证码' }}</text>
          </text>
        </view>
      </view>

      <view class="notice-card">
        <view class="notice-card__icon">
          <uni-icons type="locked" size="18" color="#6b7280"></uni-icons>
        </view>
        <text class="notice-card__text">此操作将更改您的登录方式和主要联系方式。请确保您可以访问新号码。</text>
      </view>
    </view>

    <view class="footer-bar">
      <button class="submit-button" :loading="submitLoading" @click="handleSubmit">
        <text>确认修改</text>
        <text class="submit-button__icon">✔</text>
      </button>
    </view>

    <sms-captcha-popup
      :visible="showCaptchaPopup"
      :phone="form.phone"
      @close="handleCloseCaptcha"
      @sent="handleCaptchaSent"
    />

    <app-floating-button />
  </view>
</template>

<script setup>
import { bindPhoneWithCode } from '@/api/auth'
import SmsCaptchaPopup from '@/components/sms-captcha-popup/index.vue'
import { getUserInfo as getLocalUserInfo, setUserInfo } from '@/utils/auth'
import { onBeforeUnmount, ref } from 'vue'
import { onLoad, onUnload } from '@dcloudio/uni-app'

defineOptions({ name: 'PhoneEditIndex' })

const focusField = ref('')
const submitLoading = ref(false)
const countdown = ref(0)
const countdownTimer = ref(null)
const currentPhone = ref('')
const showCaptchaPopup = ref(false)
const form = ref({
  phone: '',
  code: ''
})

onLoad(() => {
  const userInfo = getLocalUserInfo()
  currentPhone.value = String(userInfo.phone || '').trim()
})

onUnload(() => {
  clearCountdownTimer()
})

onBeforeUnmount(() => {
  clearCountdownTimer()
})

function handleFieldBlur() {
  focusField.value = ''
}

function handleBack() {
  uni.navigateBack({ fail: () => uni.switchTab({ url: '/pages/user/index' }) })
}

function isValidPhone(phone = '') {
  return /^1\d{10}$/.test(String(phone || '').trim())
}

function validatePhone() {
  const phone = String(form.value.phone || '').trim()
  if (!isValidPhone(phone)) {
    uni.showToast({ title: '请输入正确的手机号', icon: 'none' })
    return false
  }

  if (currentPhone.value && phone === currentPhone.value) {
    uni.showToast({ title: '新手机号不能与当前号码一致', icon: 'none' })
    return false
  }

  return true
}

function handleRequestCode() {
  if (countdown.value > 0) {
    return
  }

  if (!validatePhone()) {
    return
  }

  showCaptchaPopup.value = true
}

function handleCloseCaptcha() {
  showCaptchaPopup.value = false
}

function handleCaptchaSent(payload = {}) {
  showCaptchaPopup.value = false
  startCountdown(Number(payload.countdown_seconds || 60))
  uni.showToast({ title: '验证码已发送', icon: 'none' })
}

function startCountdown(seconds = 60) {
  clearCountdownTimer()
  countdown.value = Math.max(1, Number(seconds || 60))
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

function validateSubmit() {
  if (!validatePhone()) {
    return false
  }

  if (!/^\d{4,6}$/.test(String(form.value.code || '').trim())) {
    uni.showToast({ title: '请输入正确的验证码', icon: 'none' })
    return false
  }

  return true
}

async function handleSubmit() {
  if (!validateSubmit()) {
    return
  }

  submitLoading.value = true
  const result = await bindPhoneWithCode({
    phone: String(form.value.phone || '').trim(),
    code: String(form.value.code || '').trim()
  })
  submitLoading.value = false

  if (result.code !== 200 || !result.data) {
    return
  }

  const normalizedUserInfo = result.data.userinfo || result.data.user || result.data
  const cachedUserInfo = getLocalUserInfo()
  setUserInfo({
    ...cachedUserInfo,
    ...normalizedUserInfo
  })

  uni.showToast({ title: '修改成功', icon: 'success' })
  form.value.code = ''
  currentPhone.value = String(normalizedUserInfo.phone || form.value.phone || '').trim()
  setTimeout(() => {
    handleBack()
  }, 500)
}
</script>

<style scoped lang="scss">
@mixin block-card-shell {
  border-radius: 28rpx;
  background-color: #ffffff;
  box-shadow: 0 14rpx 36rpx rgba(15, 23, 42, 0.06);
}

.phoneedit-page {
  min-height: 100vh;
  background-color: #f3f3f3;
}

.phoneedit-content {
  padding: 24rpx 23rpx 180rpx;
}

/* #ifdef MP-WEIXIN */
.phoneedit-content {
  padding-bottom: calc(180rpx + env(safe-area-inset-bottom));
}
/* #endif */

/* #ifdef H5 */
.phoneedit-content {
  padding-bottom: calc(180rpx + var(--window-bottom, 0));
}
/* #endif */

.hero-block {
  @include block-card-shell;
  margin-bottom: 24rpx;
  padding: 32rpx 28rpx;

  &__title {
    display: block;
    font-size: 40rpx;
    line-height: 56rpx;
    font-weight: 700;
    color: #1a1c1c;
  }

  &__desc {
    display: block;
    margin-top: 12rpx;
    font-size: 28rpx;
    line-height: 40rpx;
    color: #5f6368;
  }
}

.form-card {
  @include block-card-shell;
  padding: 28rpx;
}

.field-block {
  margin-bottom: 24rpx;

  &--last {
    margin-bottom: 0;
  }

  &__label {
    display: block;
    margin-bottom: 16rpx;
    font-size: 32rpx;
    line-height: 44rpx;
    font-weight: 700;
    color: #1a1c1c;
  }

  &__tip {
    display: block;
    margin-top: 12rpx;
    padding-left: 8rpx;
    font-size: 20rpx;
    line-height: 28rpx;
    color: #5f6368;
  }

  &__tip-accent {
    color: var(--brand-primary, #ea580c);
    font-weight: 700;
  }
}

.field-input {
  height: 96rpx;
  padding: 0 24rpx;
  border-radius: 20rpx;
  background-color: #f5f5f5;
  display: flex;
  align-items: center;
  box-sizing: border-box;

  &--with-prefix {
    padding-left: 0;
  }

  &--focused {
    border-color: var(--brand-primary, #ea580c);
    background-color: #ffffff;
    box-shadow: 0 0 0 4rpx rgba(243, 65, 0, 0.08);
  }

  &__control {
    flex: 1;
    height: 100%;
    font-size: 30rpx;
    color: #1a1c1c;
    line-height: 42rpx;
  }

  &__control--spaced {
    letter-spacing: 8rpx;
  }

  &__placeholder {
    color: #8b9097;
    font-size: 30rpx;
  }
}

.field-prefix {
  height: 100%;
  padding: 0 20rpx 0 24rpx;
  display: flex;
  align-items: center;
  flex-shrink: 0;

  &__icon {
    margin-right: 8rpx;
    font-size: 28rpx;
    color: #5f6368;
  }

  &__code {
    font-size: 28rpx;
    color: #5f6368;
  }
}

.code-row {
  display: flex;
  align-items: center;

  &__input {
    flex: 1;
  }

  &__button {
    width: 224rpx;
    height: 96rpx;
    margin-left: 16rpx;
    border: 1rpx solid var(--brand-primary, #ea580c);
    border-radius: 16rpx;
    background-color: #ffffff;
    color: var(--brand-primary, #ea580c);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 30rpx;
    font-weight: 700;
    padding: 0;

    &--disabled {
      border-color: #f6b59f;
      color: #f6b59f;
    }

    &::after {
      border: none;
    }
  }
}

.notice-card {
  @include block-card-shell;
  margin-top: 24rpx;
  padding: 24rpx;
  display: flex;
  align-items: flex-start;

  &__icon {
    width: 56rpx;
    height: 56rpx;
    margin-right: 16rpx;
    border-radius: 16rpx;
    background-color: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  &__text {
    flex: 1;
    font-size: 20rpx;
    line-height: 30rpx;
    color: #5f6368;
  }
}

.footer-bar {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  padding: 24rpx 32rpx;
  background-color: transparent;
}

/* #ifdef MP-WEIXIN */
.footer-bar {
  padding-bottom: calc(24rpx + env(safe-area-inset-bottom));
}
/* #endif */

/* #ifdef H5 */
.footer-bar {
  bottom: var(--window-bottom, 0);
  padding-bottom: 24rpx;
}
/* #endif */

.submit-button {
  width: 100%;
  height: 96rpx;
  border-radius: 999rpx;
  border: none;
  background-color: var(--brand-primary, #ea580c);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 34rpx;
  font-weight: 700;
  box-shadow: 0 16rpx 30rpx rgba(243, 65, 0, 0.22);

  &::after {
    border: none;
  }

  &__icon {
    margin-left: 12rpx;
    font-size: 24rpx;
  }
}

</style>
