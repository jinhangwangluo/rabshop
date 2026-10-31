<template>
  <view class="password-form">
    <view class="form-field">
      <view class="form-field__input-wrap">
        <uni-icons class="form-field__icon" type="person" size="24" color="#555555"></uni-icons>
        <input
          v-model="form.account"
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
          v-model="form.password"
          class="form-field__input"
          :password="!passwordVisible"
          placeholder="请输入密码"
          placeholder-class="form-field__placeholder"
        />
        <view class="form-field__eye" @click="passwordVisible = !passwordVisible">
          <uni-icons :type="passwordVisible ? 'eye-filled' : 'eye'" size="22" color="#555555"></uni-icons>
        </view>
      </view>
    </view>

    <view class="password-form__forgot-row">
      <text class="password-form__forgot" @click="$emit('forgot-password')">忘记密码</text>
    </view>

    <button
      class="submit-btn"
      :loading="loading"
      :disabled="loading"
      @click="handleSubmit"
    >
      <text class="submit-btn__text">登录</text>
    </button>
  </view>
</template>

<script setup>
import { ref } from 'vue'

defineOptions({ name: 'H5PasswordForm' })

const props = defineProps({
  loading: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['submit', 'forgot-password'])

const passwordVisible = ref(false)
const form = ref({
  account: '',
  password: ''
})

function handleSubmit() {
  emit('submit', {
    account: String(form.value.account || '').trim(),
    password: String(form.value.password || '').trim()
  })
}
</script>

<style scoped lang="scss">
.password-form {
  display: flex;
  flex-direction: column;
}

.form-field {
  display: flex;
  flex-direction: column;
  margin-bottom: 32rpx;
}

.password-form__forgot-row {
  display: flex;
  justify-content: flex-end;
  margin-top: -8rpx;
  margin-bottom: 62rpx;
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

.form-field__placeholder {
  color: var(--text-secondary, #71717a);
  font-size: 30rpx;
}

.form-field__eye {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  padding-left: 16rpx;
  min-width: 40rpx;
}

.password-form__forgot {
  font-size: 26rpx;
  line-height: 36rpx;
  font-weight: 600;
  color: var(--text-secondary, #71717a);
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
  margin-top: 0;
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
