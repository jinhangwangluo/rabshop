<template>
  <view
    v-if="active"
    class="six-petal-spiral"
    :class="{ 'six-petal-spiral--fullscreen': fullscreen }"
    :style="wrapperStyle"
  >
    <view
      v-if="mask"
      class="six-petal-spiral__mask"
      :style="maskStyle"
    ></view>
    <view class="six-petal-spiral__content">
      <view class="six-petal-spiral__inner" :style="innerStyle">
        <canvas
          :id="canvasId"
          :canvas-id="canvasId"
          class="six-petal-spiral__canvas"
          :style="canvasStyle"
          :width="canvasPx"
          :height="canvasPx"
        />
      </view>
      <text v-if="text" class="six-petal-spiral__text" :style="textStyle">{{ text }}</text>
    </view>
  </view>
</template>

<script setup>
import { computed, getCurrentInstance, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

defineOptions({
  name: 'SixPetalSpiral'
})

const props = defineProps({
  active: {
    type: Boolean,
    default: true
  },
  size: {
    type: Number,
    default: 100
  },
  unit: {
    type: String,
    default: 'rpx',
    validator: (value) => ['rpx', 'upx', 'px'].includes(value)
  },
  color: {
    type: String,
    default: 'rgb(233, 89, 15)'
  },
  trailColor: {
    type: String,
    default: ''
  },
  text: {
    type: String,
    default: '加载中'
  },
  pathColor: {
    type: String,
    default: ''
  },
  strokeWidth: {
    type: Number,
    default: 1.1
  },
  particleCount: {
    type: Number,
    default: 42
  },
  pathSteps: {
    type: Number,
    default: 180
  },
  trailSpan: {
    type: Number,
    default: 0.34
  },
  fps: {
    type: Number,
    default: 30
  },
  durationMs: {
    type: Number,
    default: 4600
  },
  rotationDurationMs: {
    type: Number,
    default: 28000
  },
  pulseDurationMs: {
    type: Number,
    default: 4200
  },
  rotate: {
    type: Boolean,
    default: true
  },
  spiralR: {
    type: Number,
    default: 6
  },
  spiralr: {
    type: Number,
    default: 1
  },
  spirald: {
    type: Number,
    default: 3
  },
  spiralScale: {
    type: Number,
    default: 2.2
  },
  spiralBreath: {
    type: Number,
    default: 0.45
  },
  fullscreen: {
    type: Boolean,
    default: false
  },
  mask: {
    type: Boolean,
    default: false
  },
  maskColor: {
    type: String,
    default: '#ffffff'
  },
  maskOpacity: {
    type: Number,
    default: 0.82,
    validator: (value) => Number(value) >= 0 && Number(value) <= 1
  },
  zIndex: {
    type: Number,
    default: 99
  }
})

// 供 uni.createCanvasContext / createSelectorQuery 等需要组件实例的 API 使用。
const { proxy } = getCurrentInstance() || {}

const canvasId = ref(`six-petal-spiral-${Date.now()}-${Math.random().toString(16).slice(2, 8)}`)
const ctx = ref(null)
const timer = ref(null)
const initTimer = ref(null)
const startedAt = ref(0)
const isReady = ref(false)

const normalizedUnit = computed(() => {
  return props.unit === 'upx' ? 'rpx' : props.unit
})

const sizeStyle = computed(() => {
  return `${props.size}${normalizedUnit.value}`
})

const canvasPx = computed(() => {
  const size = Number(props.size) || 0
  if (normalizedUnit.value === 'px') {
    return Math.max(1, Math.round(size))
  }
  return Math.max(1, Math.round(uni.upx2px(size)))
})

const logicalScale = computed(() => {
  return canvasPx.value / 100
})

const frameInterval = computed(() => {
  const fps = Math.min(60, Math.max(12, Number(props.fps) || 30))
  return Math.round(1000 / fps)
})

const particleTotal = computed(() => {
  return Math.max(1, Math.round(Number(props.particleCount) || 0))
})

const sampledSteps = computed(() => {
  return Math.max(60, Math.round(Number(props.pathSteps) || 0))
})

const strokeWidthPx = computed(() => {
  return Math.max(1, toCanvasUnit(props.strokeWidth))
})

const resolvedColor = computed(() => {
  return normalizeColor(props.color, '#ea580c')
})

const resolvedTrailColor = computed(() => {
  return normalizeColor(props.trailColor || resolvedColor.value, resolvedColor.value)
})

const resolvedPathColor = computed(() => {
  const defaultPathColor = withAlpha(resolvedColor.value, 0.14)
  return normalizeColor(props.pathColor || defaultPathColor, defaultPathColor)
})

const resolvedMaskColor = computed(() => {
  return normalizeColor(props.maskColor, '#ffffff')
})

const wrapperStyle = computed(() => {
  if (props.fullscreen) {
    return {
      zIndex: props.zIndex
    }
  }
  return {
    minWidth: sizeStyle.value,
    minHeight: sizeStyle.value,
    zIndex: props.zIndex
  }
})

const innerStyle = computed(() => {
  return {
    width: sizeStyle.value,
    height: sizeStyle.value
  }
})

const canvasStyle = computed(() => {
  return {
    width: sizeStyle.value,
    height: sizeStyle.value
  }
})

const maskStyle = computed(() => {
  return {
    backgroundColor: withAlpha(resolvedMaskColor.value, props.maskOpacity)
  }
})

const textStyle = computed(() => {
  return {
    color: resolvedColor.value
  }
})

const renderSignature = computed(() => {
  return [
    props.size,
    props.unit,
    props.color,
    props.trailColor,
    props.pathColor,
    props.maskColor,
    props.strokeWidth,
    props.particleCount,
    props.pathSteps,
    props.trailSpan,
    props.fps,
    props.durationMs,
    props.rotationDurationMs,
    props.pulseDurationMs,
    props.rotate,
    props.spiralR,
    props.spiralr,
    props.spirald,
    props.spiralScale,
    props.spiralBreath
  ].join('|')
})

watch(() => props.active, (nextValue) => {
  if (nextValue) {
    initializeCanvas()
    return
  }
  stopAnimation()
})

watch(renderSignature, () => {
  if (props.active) {
    initializeCanvas()
  }
})

onMounted(() => {
  if (props.active) {
    initializeCanvas()
  }
})

onBeforeUnmount(() => {
  stopAnimation()
})

/**
 * 将逻辑坐标系中的值映射到当前画布像素。
 *
 * @param {number} value 逻辑坐标中的长度。
 * @returns {number} 当前画布上的像素值。
 */
function toCanvasUnit(value) {
  return (Number(value) || 0) * logicalScale.value
}

/**
 * 解析颜色值，兼容小程序不支持的 CSS 变量 fallback 写法。
 *
 * @param {string} color 原始颜色，支持常规颜色与 var(--name, fallback)。
 * @param {string} fallback 无法解析时使用的兜底颜色。
 * @returns {string} 可用于小程序内联样式和 canvas 的颜色值。
 */
function normalizeColor(color, fallback = '#ea580c') {
  const safeFallback = fallback || '#ea580c'
  const value = String(color || '').trim()

  if (!value) {
    return safeFallback
  }

  const cssVarMatched = value.match(/^var\(\s*--[\w-]+(?:\s*,\s*(.+))?\s*\)$/)
  if (cssVarMatched) {
    return cssVarMatched[1] ? cssVarMatched[1].trim() : safeFallback
  }

  return value
}

/**
 * 生成带透明度的颜色，优先兼容十六进制与 rgb/rgba。
 *
 * @param {string} color 原始颜色。
 * @param {number} alpha 透明度，范围 0 ~ 1。
 * @returns {string} 可直接用于 canvas 的颜色字符串。
 */
function withAlpha(color, alpha) {
  const safeAlpha = Math.max(0, Math.min(1, Number(alpha) || 0))
  const safeColor = normalizeColor(color, '#ea580c')

  if (/^rgba?/i.test(safeColor)) {
    const values = safeColor.replace(/rgba?\(/i, '').replace(')', '').split(',').map(item => item.trim())
    if (values.length >= 3) {
      return `rgba(${values[0]}, ${values[1]}, ${values[2]}, ${safeAlpha})`
    }
  }

  if (/^#([\da-f]{3}|[\da-f]{6})$/i.test(safeColor)) {
    const normalized = safeColor.length === 4
      ? safeColor.split('').map((char, index) => (index === 0 ? '' : char + char)).join('')
      : safeColor.slice(1)
    const red = parseInt(normalized.slice(0, 2), 16)
    const green = parseInt(normalized.slice(2, 4), 16)
    const blue = parseInt(normalized.slice(4, 6), 16)
    return `rgba(${red}, ${green}, ${blue}, ${safeAlpha})`
  }

  return safeColor
}

/**
 * 初始化画布上下文并启动固定帧率动画循环。
 *
 * 使用少量延迟确保 UniApp 在 H5/微信小程序端都已完成 canvas 挂载。
 */
function initializeCanvas() {
  stopAnimation()
  nextTick(() => {
    initTimer.value = setTimeout(() => {
      if (!props.active) {
        return
      }
      ctx.value = uni.createCanvasContext(canvasId.value, proxy)
      isReady.value = true
      startedAt.value = Date.now()
      drawFrame(startedAt.value)
      queueNextFrame()
    }, 30)
  })
}

/**
 * 终止动画循环，避免页面切换或组件隐藏后继续占用资源。
 */
function stopAnimation() {
  if (initTimer.value) {
    clearTimeout(initTimer.value)
    initTimer.value = null
  }
  if (timer.value) {
    clearTimeout(timer.value)
    timer.value = null
  }
  ctx.value = null
  isReady.value = false
}

/**
 * 以固定帧率推进动画，降低端差异带来的抖动。
 */
function queueNextFrame() {
  if (!props.active || !isReady.value) {
    return
  }
  timer.value = setTimeout(() => {
    const now = Date.now()
    drawFrame(now)
    queueNextFrame()
  }, frameInterval.value)
}

/**
 * 绘制当前帧的路径与尾迹粒子。
 *
 * @param {number} now 当前时间戳。
 */
function drawFrame(now) {
  if (!ctx.value) {
    return
  }

  const elapsed = now - startedAt.value
  const progress = (elapsed % props.durationMs) / props.durationMs
  const detailScale = getDetailScale(elapsed)
  const rotation = getRotation(elapsed)
  const center = canvasPx.value / 2

  ctx.value.clearRect(0, 0, canvasPx.value, canvasPx.value)
  ctx.value.save()
  ctx.value.translate(center, center)
  ctx.value.rotate(rotation)
  ctx.value.translate(-center, -center)

  drawPath(detailScale)
  drawParticles(progress, detailScale)

  ctx.value.restore()
  ctx.value.draw()
}

/**
 * 绘制六瓣螺旋轨迹底线。
 *
 * @param {number} detailScale 当前呼吸缩放系数。
 */
function drawPath(detailScale) {
  ctx.value.beginPath()
  ctx.value.setLineWidth(strokeWidthPx.value)
  ctx.value.setStrokeStyle(resolvedPathColor.value)
  ctx.value.setLineCap('round')
  ctx.value.setLineJoin('round')

  for (let index = 0; index <= sampledSteps.value; index += 1) {
    const point = getCurvePoint(index / sampledSteps.value, detailScale)
    const x = toCanvasUnit(point.x)
    const y = toCanvasUnit(point.y)
    if (index === 0) {
      ctx.value.moveTo(x, y)
      continue
    }
    ctx.value.lineTo(x, y)
  }

  ctx.value.stroke()
}

/**
 * 绘制沿轨迹移动的粒子尾迹。
 *
 * @param {number} progress 当前动画进度。
 * @param {number} detailScale 当前呼吸缩放系数。
 */
function drawParticles(progress, detailScale) {
  for (let index = 0; index < particleTotal.value; index += 1) {
    const tailOffset = particleTotal.value === 1 ? 0 : index / (particleTotal.value - 1)
    const point = getCurvePoint(
      normalizeProgress(progress - tailOffset * props.trailSpan),
      detailScale
    )
    const fade = Math.pow(1 - tailOffset, 0.56)
    const radius = toCanvasUnit(0.9 + fade * 2.7)
    const opacity = 0.04 + fade * 0.96

    ctx.value.beginPath()
    ctx.value.setFillStyle(withAlpha(resolvedTrailColor.value, opacity))
    ctx.value.arc(toCanvasUnit(point.x), toCanvasUnit(point.y), radius, 0, Math.PI * 2)
    ctx.value.fill()
  }
}

/**
 * 计算六瓣螺旋在指定进度下的逻辑坐标。
 *
 * @param {number} progress 动画进度，范围 0 ~ 1。
 * @param {number} detailScale 呼吸缩放系数。
 * @returns {{x: number, y: number}} 逻辑坐标系中的点位。
 */
function getCurvePoint(progress, detailScale) {
  const t = progress * Math.PI * 2
  const currentD = props.spirald + detailScale * 0.25
  const ratio = (props.spiralR - props.spiralr) / props.spiralr
  const baseX = (props.spiralR - props.spiralr) * Math.cos(t) + currentD * Math.cos(ratio * t)
  const baseY = (props.spiralR - props.spiralr) * Math.sin(t) - currentD * Math.sin(ratio * t)
  const scale = props.spiralScale + detailScale * props.spiralBreath

  return {
    x: 50 + baseX * scale,
    y: 50 + baseY * scale
  }
}

/**
 * 通过正弦脉冲制造轻微呼吸感。
 *
 * @param {number} elapsed 已运行时长。
 * @returns {number} 呼吸缩放系数。
 */
function getDetailScale(elapsed) {
  const pulseProgress = (elapsed % props.pulseDurationMs) / props.pulseDurationMs
  const pulseAngle = pulseProgress * Math.PI * 2
  return 0.52 + ((Math.sin(pulseAngle + 0.55) + 1) / 2) * 0.48
}

/**
 * 计算整体旋转角度。
 *
 * @param {number} elapsed 已运行时长。
 * @returns {number} 画布旋转弧度。
 */
function getRotation(elapsed) {
  if (!props.rotate) {
    return 0
  }
  return -((elapsed % props.rotationDurationMs) / props.rotationDurationMs) * Math.PI * 2
}

/**
 * 保证进度始终落在 0 ~ 1 区间内。
 *
 * @param {number} progress 原始进度值。
 * @returns {number} 归一化后的进度。
 */
function normalizeProgress(progress) {
  return ((progress % 1) + 1) % 1
}
</script>

<style scoped lang="scss">
.six-petal-spiral {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  max-width: 100%;
  box-sizing: border-box;
  margin: 0 auto;
  overflow: visible;

  &--fullscreen {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 100%;
  }

  &__mask {
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;
  }

  &__content {
    position: relative;
    z-index: 2;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    max-width: 520rpx;
    box-sizing: border-box;
  }

  &__inner {
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
  }

  &__text {
    display: block;
    max-width: 100%;
    margin-top: -12rpx;
    padding: 0 24rpx;
    box-sizing: border-box;
    font-size: 24rpx;
    line-height: 1.5;
    text-align: center;
    white-space: normal;
  }

  &__canvas {
    display: block;
    border-radius: 50%;
  }
}
</style>
