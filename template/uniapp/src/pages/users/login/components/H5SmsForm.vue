<template>
  <view class="sms-form">
    <view class="form-field">
      <view class="form-field__input-wrap">
        <uni-icons class="form-field__icon" type="person" size="24" color="#555555"></uni-icons>
        <input
          v-model="form.phone"
          class="form-field__input"
          type="number"
          maxlength="11"
          placeholder="手机号/账号"
          placeholder-class="form-field__placeholder"
        />
      </view>
    </view>

    <view class="form-field">
      <view class="form-field__input-wrap">
        <uni-icons class="form-field__icon" type="locked" size="24" color="#555555"></uni-icons>
        <input
          v-model="form.code"
          class="form-field__input form-field__input--code"
          type="number"
          maxlength="6"
          placeholder="请输入验证码"
          placeholder-class="form-field__placeholder"
        />
        <text
          class="form-field__code-btn"
          :class="{ 'form-field__code-btn--disabled': countdown > 0 || loading }"
          @click="handleRequestCaptcha"
        >{{ countdown > 0 ? `${countdown}s后重试` : '获取验证码' }}</text>
      </view>
    </view>

    <button
      class="submit-btn"
      :loading="loading"
      :disabled="loading"
      @click="handleSubmit"
    >
      <text class="submit-btn__text">登录/注册</text>
    </button>
  </view>
</template>

<script setup>
import { ref } from 'vue'

defineOptions({ name: 'H5SmsForm' })

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

const emit = defineEmits(['request-captcha', 'submit'])

const form = ref({
  phone: '',
  code: ''
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
    code: String(form.value.code || '').trim()
  })
}
</script>

<style scoped lang="scss">
.sms-form {
  display: flex;
  flex-direction: column;
}

.form-field {
  display: flex;
  flex-direction: column;
  margin-bottom: 32rpx;
}

.form-field__input-wrap {
  display: flex;
  align-items: center;
  background: rgba(246, 247, 249, 0.92);
  border-radius: 24rpx;
  height: 96rpx;
  padding: 0 34rpx;
}

.form-field__icon {
  margin-right: 26rpx;
  flex-shrink: 0;
}

.form-field__input {
  flex: 1;
  height: 96rpx;
  font-size: 30rpx;
  color: var(--text-primary, #18181b);
  background: transparent;
  border: none;
}

.form-field__input--code {
  flex: 1;
}

.form-field__placeholder {
  color: var(--text-secondary, #71717a);
  font-size: 30rpx;
}

.form-field__code-btn {
  flex-shrink: 0;
  font-size: 24rpx;
  line-height: 34rpx;
  font-weight: 700;
  color: var(--brand-primary, #ea580c);
  padding-left: 16rpx;
}

.form-field__code-btn--disabled {
  color: var(--text-disabled, #d4d4d8);
}

.submit-btn {
  width: 100%;
  height: 104rpx;
  border-radius: 18rpx;
  background: var(--theme-gradient, linear-gradient(135deg, #ea580c 0%, #fb923c 100%));
  color: var(--text-inverse, #ffffff);
  font-size: 34rpx;
  font-weight: 700;
  border: none;
  box-shadow: 0 12rpx 24rpx var(--brand-shadow, rgba(234, 88, 12, 0.18));
  margin-top: 62rpx;
  display: flex;
  justify-content: center;
  align-items: center;
}

.submit-btn::after {
  border: none;
}

.submit-btn__text {
  color: var(--text-inverse, #ffffff);
  font-size: 34rpx;
  font-weight: 700;
  line-height: 1;
}
</style>
