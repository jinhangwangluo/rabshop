<template>
  <view class="forgot-form">
    <view class="forgot-form__brand">
      <view class="forgot-form__icon-wrap">
        <uni-icons type="locked" size="30" :color="themePrimaryColor"></uni-icons>
      </view>
      <text class="forgot-form__desc">验证身份以设置您的新访问密码</text>
    </view>

    <view class="forgot-form__card">
      <view class="form-field">
        <text class="form-field__label">手机号码</text>
        <view class="form-field__input-wrap">
          <text class="form-field__prefix">+86</text>
          <view class="form-field__divider"></view>
          <input
            v-model="form.phone"
            class="form-field__input"
            type="number"
            maxlength="11"
            placeholder="请输入您的手机号"
            placeholder-class="form-field__placeholder"
          />
        </view>
      </view>

      <view class="form-field">
        <text class="form-field__label">短信验证码</text>
        <view class="form-field__input-wrap">
          <input
            v-model="form.code"
            class="form-field__input"
            type="number"
            maxlength="6"
            placeholder="6位验证码"
            placeholder-class="form-field__placeholder"
          />
          <view class="form-field__code-divider"></view>
          <text
            class="form-field__code-btn"
            :class="{ 'form-field__code-btn--disabled': countdown > 0 || loading }"
            @click="handleRequestCaptcha"
          >{{ countdown > 0 ? `${countdown}s后重试` : '获取验证码' }}</text>
        </view>
      </view>

      <view class="form-field">
        <text class="form-field__label">新密码</text>
        <view class="form-field__input-wrap">
          <input
            v-model="form.password"
            class="form-field__input"
            :password="!passwordVisible"
            placeholder="请输入新密码"
            placeholder-class="form-field__placeholder"
          />
          <view class="form-field__eye" @click="passwordVisible = !passwordVisible">
            <uni-icons :type="passwordVisible ? 'eye-filled' : 'eye'" size="18" color="#6b7280"></uni-icons>
          </view>
        </view>
      </view>

      <view class="form-field">
        <text class="form-field__label">确认新密码</text>
        <view class="form-field__input-wrap">
          <input
            v-model="form.confirmPassword"
            class="form-field__input"
            :password="!confirmPasswordVisible"
            placeholder="请再次输入新密码"
            placeholder-class="form-field__placeholder"
          />
          <view class="form-field__eye" @click="confirmPasswordVisible = !confirmPasswordVisible">
            <uni-icons :type="confirmPasswordVisible ? 'eye-filled' : 'eye'" size="18" color="#6b7280"></uni-icons>
          </view>
        </view>
      </view>

      <button
        class="submit-btn"
        :loading="loading"
        :disabled="loading"
        @click="handleSubmit"
      >
        <text class="submit-btn__text">确认重置</text>
      </button>

      <text class="forgot-form__tip">重置成功后，您可以使用新密码登录您的账户 <text class="forgot-form__back" @click="$emit('back')">返回登录</text></text>
    </view>

  </view>
</template>

<script setup>
import { ref } from 'vue'

defineOptions({ name: 'ForgotPasswordForm' })

const props = defineProps({
  loading: {
    type: Boolean,
    default: false
  },
  countdown: {
    type: Number,
    default: 0
  }
})

const emit = defineEmits(['request-captcha', 'submit', 'back'])

const passwordVisible = ref(false)
const confirmPasswordVisible = ref(false)
const form = ref({
  phone: '',
  code: '',
  password: '',
  confirmPassword: ''
})

function handleRequestCaptcha() {
  if (props.countdown > 0 || props.loading) return
  emit('request-captcha', {
    phone: String(form.value.phone || '').trim()
  })
}

function handleSubmit() {
  emit('submit', {
    phone: String(form.value.phone || '').trim(),
    code: String(form.value.code || '').trim(),
    password: String(form.value.password || '').trim(),
    confirmPassword: String(form.value.confirmPassword || '').trim()
  })
}
</script>

<style scoped lang="scss">
.forgot-form {
  display: flex;
  flex-direction: column;
}

.forgot-form__brand {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-bottom: 48rpx;
}

.forgot-form__icon-wrap {
  width: 128rpx;
  height: 128rpx;
  background: #ffffff;
  border-radius: 32rpx;
  display: flex;
  justify-content: center;
  align-items: center;
  box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.06);
  margin-bottom: 24rpx;
  border: 1rpx solid #f0f0f0;
}

.forgot-form__desc {
  font-size: 26rpx;
  color: #636262;
  text-align: center;
}

.forgot-form__card {
  background: #ffffff;
  border-radius: 48rpx;
  padding: 48rpx 40rpx;
}


.forgot-form__back {
  color: var(--brand-primary, #ea580c);
}

.form-field {
  display: flex;
  flex-direction: column;
  margin-bottom: 32rpx;
}

.form-field__label {
  font-size: 22rpx;
  color: #4c4546;
  margin-bottom: 12rpx;
  padding-left: 4rpx;
}

.form-field__input-wrap {
  display: flex;
  align-items: center;
  background: #f3f3f3;
  border-radius: 24rpx;
  height: 96rpx;
  padding: 0 24rpx;
}

.form-field__prefix {
  font-size: 28rpx;
  font-weight: 700;
  color: #1a1c1c;
  flex-shrink: 0;
}

.form-field__divider {
  width: 1rpx;
  height: 40rpx;
  background: #cfc4c5;
  margin: 0 20rpx;
  flex-shrink: 0;
}

.form-field__code-divider {
  width: 1rpx;
  height: 40rpx;
  background: #cfc4c5;
  margin: 0 16rpx;
  flex-shrink: 0;
}

.form-field__input {
  flex: 1;
  height: 96rpx;
  font-size: 28rpx;
  color: #1a1c1c;
  background: transparent;
  border: none;
}

.form-field__placeholder {
  color: #bdbdbd;
  font-size: 28rpx;
}

.form-field__code-btn {
  flex-shrink: 0;
  font-size: 26rpx;
  font-weight: 700;
  color: var(--brand-primary, #ea580c);
}

.form-field__code-btn--disabled {
  color: #bbbbbb;
}

.form-field__eye {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  padding-left: 16rpx;
  min-width: 40rpx;
}

.submit-btn {
  width: 100%;
  height: 96rpx;
  border-radius: 999rpx;
  background: var(--brand-primary, #ea580c);
  color: #ffffff;
  font-size: 30rpx;
  font-weight: 700;
  border: none;
  box-shadow: 0 12rpx 32rpx var(--brand-shadow-hover, rgba(234, 88, 12, 0.26));
  margin-top: 16rpx;
  display: flex;
  justify-content: center;
  align-items: center;
}

.submit-btn::after {
  border: none;
}

.submit-btn__text {
  color: #ffffff;
  font-size: 30rpx;
  font-weight: 700;
  line-height: 1;
}

.forgot-form__tip {
  display: block;
  font-size: 24rpx;
  color: #888888;
  text-align: center;
  margin-top: 32rpx;
  line-height: 1.6;
}

.forgot-form__security {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: 48rpx;
  opacity: 0.5;
}

.forgot-form__security-badge {
  width: 40rpx;
  height: 40rpx;
  border-radius: 8rpx;
  background: linear-gradient(180deg, #bdbdbd 0%, #8b8b8b 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 12rpx;
}

.forgot-form__security-text {
  font-size: 22rpx;
  color: #888888;
  letter-spacing: 2rpx;
}
</style>
