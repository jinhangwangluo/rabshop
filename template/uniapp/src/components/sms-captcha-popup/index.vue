<template>
  <view v-if="visible" class="sms-captcha-popup" @touchmove.stop.prevent="noop">
    <view class="sms-captcha-popup__mask" @click="handleMaskClick"></view>
    <view class="sms-captcha-popup__panel" @click.stop>
      <view class="sms-captcha-popup__header">
        <view>
          <text class="sms-captcha-popup__title">安全验证</text>
          <text class="sms-captcha-popup__subtitle">验证通过后将自动发送短信验证码</text>
        </view>
        <text class="sms-captcha-popup__close" @click="handleClose">×</text>
      </view>

      <view class="sms-captcha-popup__body">
        <view class="sms-captcha-popup__tip-box">
          <text class="sms-captcha-popup__tip-label">点击提示</text>
          <text class="sms-captcha-popup__tip-text">{{ displayInstructionText }}</text>
        </view>

        <view v-if="sceneLoading" class="sms-captcha-popup__status">
          <text class="sms-captcha-popup__status-text">正在加载验证场景...</text>
        </view>

        <view v-else-if="!hasScene" class="sms-captcha-popup__status">
          <text class="sms-captcha-popup__status-text">验证场景加载失败，请刷新重试</text>
        </view>

        <view v-else>
          <view class="sms-captcha-popup__target-list">
            <view
              v-for="(word, index) in scene.targets"
              :key="`target-${index}`"
              class="sms-captcha-popup__target-item"
              :class="{ 'sms-captcha-popup__target-item--done': index < selectedPoints.length }"
            >
              <text class="sms-captcha-popup__target-index">{{ index + 1 }}</text>
              <text>{{ word }}</text>
            </view>
          </view>

          <view class="sms-captcha-popup__board" @tap="handleBoardTap">
            <image
              class="sms-captcha-popup__board-bg"
              :src="scene.background_url"
              mode="aspectFill"
            />
            <view class="sms-captcha-popup__board-overlay"></view>
            <view
              v-for="(point, index) in markerPoints"
              :key="`point-${index}`"
              class="sms-captcha-popup__marker"
              :style="point.markerStyle"
            >
              <text class="sms-captcha-popup__marker-text">{{ index + 1 }}</text>
            </view>
          </view>
        </view>
      </view>

      <view class="sms-captcha-popup__footer">
        <view class="sms-captcha-popup__actions">
          <view class="sms-captcha-popup__action" @click="handleRefresh">
            <text class="sms-captcha-popup__refresh-icon">↻</text>
            <text>换一张</text>
          </view>
          <view
            class="sms-captcha-popup__action"
            :class="{ 'sms-captcha-popup__action--disabled': selectedPoints.length === 0 }"
            @click="handleResetSelection"
          >
            <text>重选</text>
          </view>
        </view>
        <button
          class="sms-captcha-popup__confirm"
          :class="{ 'sms-captcha-popup__confirm--disabled': !canSubmit }"
          :disabled="!canSubmit"
          :loading="submitLoading"
          @click="handleSubmit"
        >
          验证并发送
        </button>
      </view>
    </view>
  </view>
</template>

<script setup>
import { computed, getCurrentInstance, ref, watch } from 'vue'
import { getSmsCaptchaScene, verifySmsCaptchaAndSendCode } from '@/api/auth'

const DEFAULT_SCENE = {
  instruction_text: '请依次点击以下文字',
  targets: [],
  background_url: '',
  canvas_width: 600,
  canvas_height: 320
}

defineOptions({
  name: 'SmsCaptchaPopup'
})

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  phone: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['close', 'sent'])

// 供 uni.createSelectorQuery().in() 等需要组件实例的 API 使用。
const { proxy } = getCurrentInstance() || {}

const sceneLoading = ref(false)
const submitLoading = ref(false)
const sceneToken = ref('')
const selectedPoints = ref([])
const scene = ref({ ...DEFAULT_SCENE })

const normalizedPhone = computed(() => {
  return String(props.phone || '').trim()
})

const hasScene = computed(() => {
  return !!sceneToken.value && !!scene.value.background_url && Array.isArray(scene.value.targets) && scene.value.targets.length > 0
})

const canSubmit = computed(() => {
  return hasScene.value && selectedPoints.value.length === scene.value.targets.length && !sceneLoading.value && !submitLoading.value
})

const displayInstructionText = computed(() => {
  if (scene.value.instruction_text) {
    const targetText = Array.isArray(scene.value.targets) ? scene.value.targets.join('、') : ''
    return targetText ? `${scene.value.instruction_text}：${targetText}` : scene.value.instruction_text
  }
  return '请依次点击以下文字'
})

const markerPoints = computed(() => {
  const canvasWidth = Number(scene.value.canvas_width || DEFAULT_SCENE.canvas_width)
  const canvasHeight = Number(scene.value.canvas_height || DEFAULT_SCENE.canvas_height)

  return selectedPoints.value.map((point) => {
    const leftPercent = Math.min(Math.max((Number(point.x || 0) / canvasWidth) * 100, 0), 100)
    const topPercent = Math.min(Math.max((Number(point.y || 0) / canvasHeight) * 100, 0), 100)

    return {
      markerStyle: `left: calc(${leftPercent}% - 19rpx); top: calc(${topPercent}% - 19rpx);`
    }
  })
})

watch(() => props.visible, (nextValue) => {
  if (nextValue) {
    openPopup()
    return
  }

  resetSceneState()
}, {
  immediate: true
})

watch(() => props.phone, (nextValue, previousValue) => {
  if (!props.visible) {
    return
  }

  if (String(nextValue || '').trim() !== String(previousValue || '').trim()) {
    fetchScene()
  }
})

function noop() {}

function openPopup() {
  fetchScene()
}

function resetSceneState() {
  sceneLoading.value = false
  submitLoading.value = false
  sceneToken.value = ''
  selectedPoints.value = []
  scene.value = { ...DEFAULT_SCENE }
}

async function fetchScene() {
  if (!/^1\d{10}$/.test(normalizedPhone.value)) {
    resetSceneState()
    return
  }

  sceneLoading.value = true
  submitLoading.value = false
  selectedPoints.value = []

  const result = await getSmsCaptchaScene({ phone: normalizedPhone.value })
  sceneLoading.value = false

  if (result.code !== 200 || !result.data) {
    sceneToken.value = ''
    scene.value = { ...DEFAULT_SCENE }
    return
  }

  sceneToken.value = String(result.data.scene_token || '')
  scene.value = {
    instruction_text: String(result.data.instruction_text || '请依次点击以下文字'),
    targets: Array.isArray(result.data.targets) ? result.data.targets : [],
    background_url: String(result.data.background_url || ''),
    canvas_width: Number(result.data.canvas_width || 600),
    canvas_height: Number(result.data.canvas_height || 320)
  }
}

function handleMaskClick() {
  if (submitLoading.value) {
    return
  }
  handleClose()
}

function handleClose() {
  if (submitLoading.value) {
    return
  }
  emit('close')
}

function handleRefresh() {
  if (sceneLoading.value || submitLoading.value) {
    return
  }
  fetchScene()
}

function handleResetSelection() {
  if (sceneLoading.value || submitLoading.value || selectedPoints.value.length === 0) {
    return
  }
  selectedPoints.value = []
}

async function handleBoardTap(event) {
  if (!hasScene.value || submitLoading.value || sceneLoading.value) {
    return
  }

  if (selectedPoints.value.length >= scene.value.targets.length) {
    uni.showToast({ title: '已完成选择，可直接提交', icon: 'none' })
    return
  }

  const clientPoint = getTapClientPoint(event)
  if (!clientPoint) {
    return
  }

  try {
    const rect = await getBoardRect()
    if (!rect || !rect.width || !rect.height) {
      return
    }

    const offsetX = Math.min(Math.max(clientPoint.x - rect.left, 0), rect.width)
    const offsetY = Math.min(Math.max(clientPoint.y - rect.top, 0), rect.height)
    const point = {
      x: Math.round((offsetX / rect.width) * Number(scene.value.canvas_width || 600)),
      y: Math.round((offsetY / rect.height) * Number(scene.value.canvas_height || 320))
    }

    selectedPoints.value = [...selectedPoints.value, point]
  } catch (error) {
    uni.showToast({ title: '点击失败，请重试', icon: 'none' })
  }
}

function getTapClientPoint(event) {
  const touch = Array.isArray(event.changedTouches) && event.changedTouches.length > 0 ? event.changedTouches[0] : null
  if (touch) {
    return {
      x: Number(touch.clientX || touch.pageX || 0),
      y: Number(touch.clientY || touch.pageY || 0)
    }
  }

  const detail = event.detail || {}
  if (typeof detail.x !== 'undefined' && typeof detail.y !== 'undefined') {
    return {
      x: Number(detail.x || 0),
      y: Number(detail.y || 0)
    }
  }

  return null
}

function getBoardRect() {
  return new Promise((resolve, reject) => {
    const query = uni.createSelectorQuery().in(proxy)
    query.select('.sms-captcha-popup__board').boundingClientRect((rect) => {
      if (!rect) {
        reject(new Error('board rect not found'))
        return
      }
      resolve(rect)
    }).exec()
  })
}

async function handleSubmit() {
  if (!canSubmit.value) {
    if (!hasScene.value) {
      uni.showToast({ title: '验证场景未准备好', icon: 'none' })
      return
    }

    uni.showToast({ title: '请先完成安全验证', icon: 'none' })
    return
  }

  submitLoading.value = true
  const result = await verifySmsCaptchaAndSendCode({
    phone: normalizedPhone.value,
    scene_token: sceneToken.value,
    selected_points: selectedPoints.value
  })
  submitLoading.value = false

  if (result.code !== 200) {
    fetchScene()
    return
  }

  emit('sent', result.data || {})
}
</script>

<style scoped lang="scss">
.sms-captcha-popup {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  z-index: 999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 32rpx;
  box-sizing: border-box;

  &__mask {
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;
    background: rgba(15, 23, 42, 0.56);
  }

  &__panel {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 680rpx;
    border-radius: 28rpx;
    background: #ffffff;
    overflow: hidden;
    box-shadow: 0 24rpx 64rpx rgba(15, 23, 42, 0.22);
  }

  &__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding: 28rpx 28rpx 20rpx;
    border-bottom: 1rpx solid rgba(226, 232, 240, 0.8);
  }

  &__title {
    display: block;
    font-size: 36rpx;
    line-height: 50rpx;
    font-weight: 700;
    color: var(--text-title, #1f2937);
  }

  &__subtitle {
    display: block;
    margin-top: 6rpx;
    font-size: 22rpx;
    line-height: 32rpx;
    color: var(--text-secondary, #64748b);
  }

  &__close {
    width: 52rpx;
    height: 52rpx;
    border-radius: 26rpx;
    background: rgba(148, 163, 184, 0.14);
    text-align: center;
    line-height: 52rpx;
    font-size: 34rpx;
    color: #64748b;
    flex-shrink: 0;
  }

  &__body {
    padding: 28rpx;
  }

  &__tip-box {
    padding: 20rpx 22rpx;
    border-radius: 20rpx;
    background: var(--brand-primary-pale, #fff7ed);
  }

  &__tip-label {
    display: block;
    font-size: 22rpx;
    line-height: 32rpx;
    color: var(--text-secondary, #64748b);
  }

  &__tip-text {
    display: block;
    margin-top: 8rpx;
    font-size: 28rpx;
    line-height: 40rpx;
    font-weight: 600;
    color: var(--text-title, #1f2937);
  }

  &__status {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 320rpx;
    margin-top: 24rpx;
    border-radius: 24rpx;
    background: #f8fafc;
  }

  &__status-text {
    font-size: 26rpx;
    line-height: 36rpx;
    color: var(--text-secondary, #64748b);
  }

  &__target-list {
    display: flex;
    flex-wrap: wrap;
    margin: 24rpx -8rpx 0;
  }

  &__target-item {
    display: flex;
    align-items: center;
    margin: 8rpx;
    padding: 10rpx 18rpx;
    border-radius: 999rpx;
    background: #f8fafc;
    color: var(--text-regular, #334155);
    font-size: 24rpx;
    line-height: 32rpx;

    &--done {
      background: var(--brand-primary-pale, #fff7ed);
      color: var(--brand-primary, #ea580c);
    }
  }

  &__target-index {
    width: 32rpx;
    height: 32rpx;
    margin-right: 10rpx;
    border-radius: 16rpx;
    background: rgba(148, 163, 184, 0.18);
    text-align: center;
    line-height: 32rpx;
    font-size: 20rpx;
  }

  &__target-item--done &__target-index {
    background: rgba(243, 90, 0, 0.14);
    color: var(--brand-primary, #ea580c);
  }

  &__board {
    position: relative;
    height: 360rpx;
    margin-top: 24rpx;
    border-radius: 24rpx;
    overflow: hidden;
    background: #e2e8f0;
  }

  &__board-bg,
  &__board-overlay {
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;
  }

  &__board-bg {
    width: 100%;
    height: 100%;
  }

  &__board-overlay {
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.05), rgba(15, 23, 42, 0.02));
    pointer-events: none;
  }

  &__marker {
    position: absolute;
    z-index: 1;
    width: 38rpx;
    height: 38rpx;
    border-radius: 19rpx;
    background: var(--brand-primary, #ea580c);
    box-shadow: 0 8rpx 18rpx rgba(243, 90, 0, 0.24);
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
  }

  &__marker-text {
    color: #ffffff;
    font-size: 20rpx;
    font-weight: 700;
    line-height: 1;
  }

  &__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 24rpx 28rpx 28rpx;
    border-top: 1rpx solid rgba(226, 232, 240, 0.8);
    background: #ffffff;
    gap: 20rpx;
  }

  &__actions {
    display: flex;
    align-items: center;
    gap: 20rpx;
  }

  &__action {
    display: flex;
    align-items: center;
    color: var(--text-secondary, #64748b);
    font-size: 24rpx;
    line-height: 32rpx;

    &--disabled {
      opacity: 0.4;
    }
  }

  &__refresh-icon {
    margin-right: 8rpx;
    font-size: 30rpx;
  }

  &__confirm {
    min-width: 220rpx;
    height: 84rpx;
    padding: 0 28rpx;
    border: none;
    border-radius: 99rpx;
    background: var(--brand-primary, #ea580c);
    color: #ffffff;
    font-size: 28rpx;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: unset;

    &--disabled {
      background: rgba(243, 90, 0, 0.36);
      color: rgba(255, 255, 255, 0.92);
    }

    &::after {
      border: none;
    }
  }
}
</style>
