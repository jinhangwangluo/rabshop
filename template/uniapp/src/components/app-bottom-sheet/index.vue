<template>
  <view
    v-if="isRendered"
    class="app-bottom-sheet"
  >
    <view
      class="app-bottom-sheet__mask"
      :class="{ 'app-bottom-sheet__mask--active': animationState === 'enter' }"
      @click="handleMaskClick"
      @touchmove.stop.prevent="noop"
    ></view>

    <view
      class="app-bottom-sheet__panel"
      :class="{ 'app-bottom-sheet__panel--active': animationState === 'enter' }"
      @click.stop
    >
      <slot></slot>
    </view>
  </view>
</template>

<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'

const LEAVE_DURATION = 260

defineOptions({
  name: 'AppBottomSheet'
})

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  closeOnMask: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits(['close', 'after-leave'])

const isRendered = ref(false)
const animationState = ref('leave')
const leaveTimer = ref(null)

watch(() => props.visible, nextValue => {
  if (nextValue) {
    enter()
    return
  }

  leave()
}, { immediate: true })

onBeforeUnmount(() => {
  clearLeaveTimer()
})

/**
 * 空函数，用于阻止遮罩穿透。
 *
 * @returns {void}
 */
function noop() {}

/**
 * 播放入场动画。
 *
 * @returns {void}
 */
function enter() {
  clearLeaveTimer()
  if (!isRendered.value) {
    isRendered.value = true
  }

  nextTick(() => {
    animationState.value = 'enter'
  })
}

/**
 * 播放退场动画并延迟卸载。
 *
 * @returns {void}
 */
function leave() {
  if (!isRendered.value) {
    return
  }

  animationState.value = 'leave'
  clearLeaveTimer()
  leaveTimer.value = setTimeout(() => {
    isRendered.value = false
    emit('after-leave')
  }, LEAVE_DURATION)
}

/**
 * 清理退场定时器。
 *
 * @returns {void}
 */
function clearLeaveTimer() {
  if (!leaveTimer.value) {
    return
  }

  clearTimeout(leaveTimer.value)
  leaveTimer.value = null
}

/**
 * 点击遮罩时关闭弹层。
 *
 * @returns {void}
 */
function handleMaskClick() {
  if (!props.closeOnMask) {
    return
  }

  emit('close')
}
</script>

<style scoped lang="scss">
.app-bottom-sheet {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  z-index: 90;
}

.app-bottom-sheet__mask {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  opacity: 0;
  background: rgba(0, 0, 0, 0.4);
  transition: opacity 260ms ease;
}

.app-bottom-sheet__mask--active {
  opacity: 1;
}

.app-bottom-sheet__panel {
  position: absolute;
  right: 0;
  bottom: 0;
  left: 0;
  transform: translateY(100%);
  opacity: 0.98;
  transition: transform 260ms cubic-bezier(0.22, 0.61, 0.36, 1), opacity 260ms ease;
}

.app-bottom-sheet__panel--active {
  transform: translateY(0);
  opacity: 1;
}
</style>
