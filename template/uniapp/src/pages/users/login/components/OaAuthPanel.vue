<template>
  <view class="oa-panel">
    <view class="oa-panel__brand">
      <view class="oa-panel__logo">
        <image
          v-if="logoSrc"
          class="oa-panel__logo-image"
          :src="logoSrc"
          mode="aspectFill"
        ></image>
        <text v-else class="oa-panel__logo-text">{{ logoText }}</text>
      </view>
      <text class="oa-panel__app-name">微信公众号授权登录</text>
      <text class="oa-panel__desc">授权登录后可继续购买课程、观看直播与管理订单</text>
    </view>

    <view class="oa-panel__spacer"></view>

    <view class="oa-panel__btns">
      <button class="oa-panel__auth-btn" :loading="loading" @tap="handleAuthorizeTap">
        <view class="oa-panel__auth-content">
          <uni-icons type="locked-filled" size="18" color="#ffffff"></uni-icons>
          <text class="oa-panel__auth-text">公众号授权登录</text>
        </view>
      </button>
    </view>
  </view>
</template>

<script setup>
import { computed } from 'vue'

defineOptions({ name: 'OaAuthPanel' })

const props = defineProps({
  /** 授权加载状态 */
  loading: {
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

const emit = defineEmits(['authorize'])

const logoText = computed(() => {
  return String(props.appName || '乖乖兔').trim().slice(0, 1)
})

/**
 * 发起公众号网页授权登录。
 * @returns {void}
 */
function handleAuthorizeTap() {
  if (props.loading) {
    return
  }
  emit('authorize')
}
</script>

<style scoped lang="scss">
.oa-panel {
  min-height: 100%;
  display: flex;
  flex-direction: column;
}

.oa-panel__brand {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding-top: 40rpx;
}

.oa-panel__logo {
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

.oa-panel__logo-image {
  width: 100%;
  height: 100%;
}

.oa-panel__logo-text {
  font-size: 64rpx;
  line-height: 1;
  font-weight: 700;
  color: var(--brand-primary, #ea580c);
}

.oa-panel__app-name {
  max-width: 600rpx;
  font-size: 56rpx;
  font-weight: 700;
  line-height: 1.2;
  color: var(--text-title, #1b1c1c);
  margin-bottom: 20rpx;
  text-align: center;
}

.oa-panel__desc {
  max-width: 560rpx;
  font-size: 28rpx;
  line-height: 1.7;
  color: var(--text-secondary, #6b6b6b);
  text-align: center;
}

.oa-panel__spacer {
  flex: 1;
  min-height: 240rpx;
}

.oa-panel__btns {
  width: 100%;
}

.oa-panel__auth-btn {
  width: 100%;
  height: 108rpx;
  border-radius: 999rpx;
  background: var(--brand-primary, #ea580c);
  color: #ffffff;
  font-size: 32rpx;
  font-weight: 600;
  border: none;
  box-shadow: 0 14rpx 32rpx var(--brand-shadow, rgba(234, 88, 12, 0.18));
  display: flex;
  align-items: center;
  justify-content: center;
}

.oa-panel__auth-btn::after {
  border: none;
}

.oa-panel__auth-content {
  display: flex;
  align-items: center;
}

.oa-panel__auth-text {
  margin-left: 16rpx;
  color: #ffffff;
  font-size: 32rpx;
  font-weight: 600;
}
</style>
