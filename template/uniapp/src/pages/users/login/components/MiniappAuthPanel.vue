<template>
  <view class="miniapp-panel">
    <view class="miniapp-panel__brand">
      <view class="miniapp-panel__logo">
        <image
          v-if="logoSrc"
          class="miniapp-panel__logo-image"
          :src="logoSrc"
          mode="aspectFill"
        ></image>
        <text v-else class="miniapp-panel__logo-text">{{ logoText }}</text>
      </view>
      <text class="miniapp-panel__app-name">{{ appName }}</text>
      <text class="miniapp-panel__desc">授权登录后可同步学习进度、订单与账户资产</text>
    </view>

    <view v-if="hasUserInfo" class="miniapp-panel__user-info">
      <text class="miniapp-panel__user-text">当前账号：{{ userInfo.nickname || userInfo.phone || '微信用户' }}</text>
      <text class="miniapp-panel__user-text">手机号：{{ userInfo.phone || '暂未绑定' }}</text>
    </view>

    <view class="miniapp-panel__spacer"></view>

    <view class="miniapp-panel__btns">
      <button v-if="!loggedIn" class="miniapp-panel__primary-btn" :loading="loginLoading" @tap="handleLoginTap">
        <view class="miniapp-panel__primary-content">
          <uni-icons type="locked-filled" size="18" color="#ffffff"></uni-icons>
          <text class="miniapp-panel__primary-text">微信一键授权登录</text>
        </view>
      </button>

      <button class="miniapp-panel__cancel-btn" @click="$emit('cancel')">取消</button>
    </view>

    <view v-if="showPhonePrompt" class="miniapp-phone-popup" @touchmove.stop.prevent="noop">
      <view class="miniapp-phone-popup__mask" @tap="handlePromptCancel"></view>
      <view class="miniapp-phone-popup__panel" @tap.stop="noop">
        <view class="miniapp-phone-popup__header">
          <text class="miniapp-phone-popup__title">授权手机号</text>
        </view>

        <view class="miniapp-phone-popup__account">
          <text class="miniapp-phone-popup__account-text">当前账号：{{ userInfo.nickname || userInfo.phone || '微信用户' }}</text>
          <text class="miniapp-phone-popup__account-text">手机号：{{ userInfo.phone || '暂未绑定' }}</text>
        </view>

        <button
          class="miniapp-phone-popup__primary"
          :loading="phoneBinding"
          open-type="getPhoneNumber"
          @getphonenumber="handlePhoneEvent"
        >
          授权手机号
        </button>
        <button class="miniapp-phone-popup__secondary" @tap="handlePromptCancel">
          {{ phonePromptRequired ? '返回' : '稍后再说' }}
        </button>
      </view>
    </view>
  </view>
</template>

<script setup>
import { computed } from 'vue'

defineOptions({ name: 'MiniappAuthPanel' })

const props = defineProps({
  loginLoading: {
    type: Boolean,
    default: false
  },
  phoneBinding: {
    type: Boolean,
    default: false
  },
  userInfo: {
    type: Object,
    default: () => ({})
  },
  loggedIn: {
    type: Boolean,
    default: false
  },
  showPhonePrompt: {
    type: Boolean,
    default: false
  },
  phonePromptRequired: {
    type: Boolean,
    default: false
  },
  appName: {
    type: String,
    default: '乖乖兔'
  },
  logoSrc: {
    type: String,
    default: '/static/logo.png'
  }
})

const emit = defineEmits(['login', 'bind-phone', 'phone-prompt-cancel', 'cancel'])

const hasUserInfo = computed(() => {
  return !!props.userInfo && Object.keys(props.userInfo).length > 0
})

const logoText = computed(() => {
  return String(props.appName || '乖乖兔').trim().slice(0, 1)
})

function handleLoginTap() {
  if (props.loginLoading) {
    return
  }
  emit('login')
}

/**
 * 透传微信小程序手机号授权事件。
 * @param {Object} event 微信 getPhoneNumber 回调事件。
 * @returns {void}
 */
function handlePhoneEvent(event) {
  emit('bind-phone', event)
}

/**
 * 关闭或取消手机号授权弹窗。
 * @returns {void}
 */
function handlePromptCancel() {
  emit('phone-prompt-cancel')
}

/**
 * 阻止弹窗打开时页面滚动。
 * @returns {boolean} 固定返回 false。
 */
function noop() {
  return false
}
</script>

<style scoped lang="scss">
.miniapp-panel {
  min-height: 100%;
  display: flex;
  flex-direction: column;
}

.miniapp-panel__brand {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding-top: 40rpx;
}

.miniapp-panel__logo {
  width: 160rpx;
  height: 160rpx;
  border-radius: 50%;
  background: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 12rpx 34rpx rgba(15, 23, 42, 0.08);
  border: 1rpx solid rgba(226, 232, 240, 0.9);
  margin-bottom: 40rpx;
  overflow: hidden;
}

.miniapp-panel__logo-image {
  width: 100%;
  height: 100%;
}

.miniapp-panel__logo-text {
  font-size: 64rpx;
  line-height: 1;
  font-weight: 700;
  color: var(--brand-primary, #ea580c);
}

.miniapp-panel__app-name {
  max-width: 600rpx;
  font-size: 56rpx;
  font-weight: 700;
  line-height: 1.2;
  color: #1b1c1c;
  margin-bottom: 20rpx;
  text-align: center;
}

.miniapp-panel__desc {
  max-width: 560rpx;
  font-size: 28rpx;
  line-height: 1.7;
  color: #6b6b6b;
  text-align: center;
}

.miniapp-panel__user-info {
  margin-top: 48rpx;
  padding: 28rpx 32rpx;
  border-radius: 24rpx;
  background: #ffffff;
  border: 1rpx solid #ece7e6;
}

.miniapp-panel__user-text {
  display: block;
  font-size: 26rpx;
  line-height: 1.8;
  color: #636262;
}

.miniapp-panel__spacer {
  flex: 1;
  min-height: 240rpx;
}

.miniapp-panel__btns {
  width: 100%;
}

.miniapp-panel__primary-btn,
.miniapp-panel__cancel-btn {
  width: 100%;
  height: 108rpx;
  border-radius: 999rpx;
  font-size: 32rpx;
  font-weight: 600;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
}

.miniapp-panel__primary-btn {
  background: var(--brand-primary, #ea580c);
  color: #ffffff;
  box-shadow: 0 14rpx 32rpx var(--brand-shadow, rgba(234, 88, 12, 0.18));
}

.miniapp-panel__primary-btn::after,
.miniapp-panel__cancel-btn::after {
  border: none;
}

.miniapp-panel__primary-content {
  display: flex;
  align-items: center;
}

.miniapp-panel__primary-text {
  margin-left: 16rpx;
  color: #ffffff;
  font-size: 32rpx;
  font-weight: 600;
}

.miniapp-panel__cancel-btn {
  margin-top: 24rpx;
  background: #ffffff;
  color: #6d6d6d;
  border: 2rpx solid #e5e7eb;
}

.miniapp-phone-popup {
  position: fixed;
  left: 0;
  right: 0;
  top: 0;
  bottom: 0;
  z-index: 30;
  display: flex;
  align-items: flex-end;
}

.miniapp-phone-popup__mask {
  position: absolute;
  left: 0;
  right: 0;
  top: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.48);
}

.miniapp-phone-popup__panel {
  position: relative;
  z-index: 1;
  width: 100%;
  padding: 40rpx 40rpx calc(40rpx + env(safe-area-inset-bottom));
  border-radius: 32rpx 32rpx 0 0;
  background: #ffffff;
  box-sizing: border-box;
}

.miniapp-phone-popup__header {
  margin-bottom: 28rpx;
}

.miniapp-phone-popup__title {
  display: block;
  font-size: 36rpx;
  line-height: 1.4;
  font-weight: 700;
  color: var(--text-title, #1b1c1c);
}

.miniapp-phone-popup__account {
  padding: 24rpx 28rpx;
  border-radius: 20rpx;
  background: #f8fafc;
  margin-bottom: 32rpx;
}

.miniapp-phone-popup__account-text {
  display: block;
  font-size: 26rpx;
  line-height: 1.8;
  color: var(--text-regular, #636262);
}

.miniapp-phone-popup__primary,
.miniapp-phone-popup__secondary {
  width: 100%;
  height: 100rpx;
  border-radius: 999rpx;
  font-size: 30rpx;
  font-weight: 600;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
}

.miniapp-phone-popup__primary {
  background: var(--brand-primary, #ea580c);
  color: #ffffff;
}

.miniapp-phone-popup__secondary {
  margin-top: 20rpx;
  background: #f8fafc;
  color: var(--text-secondary, #6b6b6b);
}

.miniapp-phone-popup__primary::after,
.miniapp-phone-popup__secondary::after {
  border: none;
}
</style>
