<template>
  <el-dialog
    :model-value="visible"
    class="sms-captcha-dialog"
    width="480px"
    title="安全验证"
    :close-on-click-modal="!submitLoading"
    :show-close="!submitLoading"
    destroy-on-close
    @update:model-value="$emit('close')"
  >
    <p class="sms-captcha-dialog__subtitle">验证通过后将自动发送短信验证码</p>

    <div class="sms-captcha-dialog__tip">
      <span class="sms-captcha-dialog__tip-label">点击提示</span>
      <span class="sms-captcha-dialog__tip-text">{{ displayInstructionText }}</span>
    </div>

    <div v-if="sceneLoading" class="sms-captcha-dialog__status">正在加载验证场景...</div>
    <div v-else-if="!hasScene" class="sms-captcha-dialog__status">验证场景加载失败，请刷新重试</div>

    <template v-else>
      <div class="sms-captcha-dialog__targets">
        <span
          v-for="(word, index) in scene.targets"
          :key="`target-${index}`"
          class="sms-captcha-dialog__target"
          :class="{ 'is-done': index < selectedPoints.length }"
        >
          <i class="sms-captcha-dialog__target-index">{{ index + 1 }}</i>
          {{ word }}
        </span>
      </div>

      <div ref="boardRef" class="sms-captcha-dialog__board" @click="handleBoardClick">
        <img class="sms-captcha-dialog__board-bg" :src="scene.backgroundUrl" alt="验证码">
        <span
          v-for="(marker, index) in markerPoints"
          :key="`marker-${index}`"
          class="sms-captcha-dialog__marker"
          :style="marker.style"
        >
          {{ index + 1 }}
        </span>
      </div>
    </template>

    <template #footer>
      <div class="sms-captcha-dialog__footer">
        <span class="sms-captcha-dialog__refresh" @click="handleRefresh">换一张</span>
        <span
          class="sms-captcha-dialog__refresh"
          :class="{ 'is-disabled': selectedPoints.length === 0 }"
          @click="handleResetSelection"
        >
          重选
        </span>
        <el-button
          class="sms-captcha-dialog__confirm"
          type="primary"
          :disabled="!canSubmit"
          :loading="submitLoading"
          @click="handleSubmit"
        >
          验证并发送
        </el-button>
      </div>
    </template>
  </el-dialog>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { getPluginSmsScene, sendPluginSmsCode } from '@/api/plugin'

defineOptions({
  name: 'SmsClickCaptcha'
})

const DEFAULT_SCENE = {
  instructionText: '请依次点击以下文字',
  targets: [],
  backgroundUrl: '',
  canvasWidth: 600,
  canvasHeight: 320
}

// 开放平台返回的验证码图片是站点相对路径，展示时拼接开放平台域名。
const OPEN_API_BASE = 'https://www.rabshop.cn'

function resolveBackgroundUrl(value) {
  const url = String(value || '').trim()
  if (url === '') {
    return ''
  }
  if (url.startsWith('http://') || url.startsWith('https://')) {
    return url
  }
  return url.startsWith('/') ? `${OPEN_API_BASE}${url}` : url
}

const props = defineProps({
  visible: { type: Boolean, default: false },
  phone: { type: String, default: '' }
})

const emit = defineEmits(['close', 'sent'])

const sceneLoading = ref(false)
const submitLoading = ref(false)
const sceneToken = ref('')
const selectedPoints = ref([])
const scene = ref({ ...DEFAULT_SCENE })
const boardRef = ref(null)

const hasScene = computed(() => Boolean(
  sceneToken.value && scene.value.backgroundUrl && scene.value.targets.length > 0
))
const canSubmit = computed(() => hasScene.value && selectedPoints.value.length === scene.value.targets.length && !sceneLoading.value && !submitLoading.value)
const displayInstructionText = computed(() => {
  if (scene.value.instructionText) {
    const targets = scene.value.targets.join('、')
    return targets ? `${scene.value.instructionText}：${targets}` : scene.value.instructionText
  }
  return '请依次点击以下文字'
})
const markerPoints = computed(() => selectedPoints.value.map(point => ({
  style: {
    left: `${Math.min(Math.max((point.x / scene.value.canvasWidth) * 100, 0), 100)}%`,
    top: `${Math.min(Math.max((point.y / scene.value.canvasHeight) * 100, 0), 100)}%`
  }
})))

watch(() => props.visible, (nextValue) => {
  if (nextValue) {
    void fetchScene()
    return
  }
  resetSceneState()
})

function resetSceneState() {
  sceneLoading.value = false
  submitLoading.value = false
  sceneToken.value = ''
  selectedPoints.value = []
  scene.value = { ...DEFAULT_SCENE }
}

async function fetchScene() {
  if (!/^1\d{10}$/.test(String(props.phone || ''))) {
    resetSceneState()
    return
  }

  sceneLoading.value = true
  submitLoading.value = false
  selectedPoints.value = []
  try {
    const response = await getPluginSmsScene(props.phone)
    const data = readPayload(response) || {}
    sceneToken.value = String(data.scene_token || '')
    scene.value = {
      instructionText: String(data.instruction_text || '请依次点击以下文字'),
      targets: Array.isArray(data.targets) ? data.targets.filter(Boolean) : [],
      backgroundUrl: resolveBackgroundUrl(data.background_url),
      canvasWidth: Number(data.canvas_width || 600),
      canvasHeight: Number(data.canvas_height || 320)
    }
  } catch (error) {
    ElMessage.error(error?.message || '验证场景加载失败')
    sceneToken.value = ''
    scene.value = { ...DEFAULT_SCENE }
  } finally {
    sceneLoading.value = false
  }
}

function readPayload(response) {
  return response?.data ?? response ?? {}
}

function handleBoardClick(event) {
  if (!hasScene.value || submitLoading.value || sceneLoading.value) {
    return
  }
  if (selectedPoints.value.length >= scene.value.targets.length) {
    ElMessage.info('已完成选择，可直接提交')
    return
  }

  const board = boardRef.value
  if (!board) {
    return
  }
  const rect = board.getBoundingClientRect()
  if (!rect.width || !rect.height) {
    return
  }
  const offsetX = Math.min(Math.max(event.clientX - rect.left, 0), rect.width)
  const offsetY = Math.min(Math.max(event.clientY - rect.top, 0), rect.height)
  selectedPoints.value = [
    ...selectedPoints.value,
    {
      x: Math.round((offsetX / rect.width) * scene.value.canvasWidth),
      y: Math.round((offsetY / rect.height) * scene.value.canvasHeight)
    }
  ]
}

function handleRefresh() {
  if (sceneLoading.value || submitLoading.value) {
    return
  }
  void fetchScene()
}

function handleResetSelection() {
  if (sceneLoading.value || submitLoading.value || selectedPoints.value.length === 0) {
    return
  }
  selectedPoints.value = []
}

async function handleSubmit() {
  if (!canSubmit.value) {
    ElMessage.warning(hasScene.value ? '请先完成安全验证' : '验证场景未准备好')
    return
  }
  submitLoading.value = true
  try {
    const response = await sendPluginSmsCode({
      phone: String(props.phone || ''),
      scene_token: sceneToken.value,
      selected_points: selectedPoints.value
    })
    emit('sent', readPayload(response) || {})
  } catch (error) {
    ElMessage.error(error?.message || '验证码发送失败')
    void fetchScene()
  } finally {
    submitLoading.value = false
  }
}
</script>

<style scoped lang="scss">
.sms-captcha-dialog {
  :deep(.el-dialog__header) {
    padding-bottom: 8px;
  }
}

.sms-captcha-dialog__subtitle {
  margin: 0 0 12px;
  font-size: 13px;
  color: #98a2b3;
}

.sms-captcha-dialog__tip {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
  padding: 10px 12px;
  border-radius: 8px;
  background: #f8fafc;
}

.sms-captcha-dialog__tip-label {
  flex-shrink: 0;
  padding: 2px 6px;
  border-radius: 4px;
  background: #eff6ff;
  color: #2563eb;
  font-size: 12px;
}

.sms-captcha-dialog__tip-text {
  font-size: 13px;
  color: #344054;
}

.sms-captcha-dialog__status {
  padding: 36px 0;
  text-align: center;
  color: #98a2b3;
  font-size: 13px;
}

.sms-captcha-dialog__targets {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 12px;
}

.sms-captcha-dialog__target {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 10px;
  border-radius: 6px;
  background: #f1f5f9;
  color: #475569;
  font-size: 13px;

  &.is-done {
    background: #eff6ff;
    color: #2563eb;
  }
}

.sms-captcha-dialog__target-index {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: #ffffff;
  color: #64748b;
  font-size: 11px;
  font-style: normal;
}

.sms-captcha-dialog__board {
  position: relative;
  overflow: hidden;
  width: 100%;
  aspect-ratio: 600 / 320;
  border-radius: 8px;
  background: #eef2f7;
  cursor: crosshair;
}

.sms-captcha-dialog__board-bg {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
  user-select: none;
  pointer-events: none;
}

.sms-captcha-dialog__marker {
  position: absolute;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 26px;
  height: 26px;
  margin-left: -13px;
  margin-top: -13px;
  border: 2px solid #2563eb;
  border-radius: 50%;
  background: rgba(37, 99, 235, 0.85);
  color: #ffffff;
  font-size: 13px;
  font-weight: 700;
  pointer-events: none;
}

.sms-captcha-dialog__footer {
  display: flex;
  align-items: center;
  gap: 14px;
}

.sms-captcha-dialog__refresh {
  font-size: 13px;
  color: #2563eb;
  cursor: pointer;

  &.is-disabled {
    color: #c0c6d4;
    cursor: not-allowed;
  }
}

.sms-captcha-dialog__confirm {
  margin-left: auto;
  min-width: 120px;
}
</style>
