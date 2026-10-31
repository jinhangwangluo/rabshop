<template>
  <div
    class="big-file-upload"
    :class="{ 'is-dragover': isDragover, 'is-disabled': disabled }"
    @dragover.prevent="onDragover"
    @dragleave.prevent="onDragleave"
    @drop.prevent="onDrop"
  >
    <!-- 已上传完成，展示文件信息 -->
    <div v-if="fileUrl" class="upload-result">
      <div class="result-info">
        <LegacyIcon :name="fileIconClass" class="result-icon" />
        <div class="result-detail">
          <div class="result-name" :title="fileName">{{ fileName || fileUrl }}</div>
          <div class="result-url" :title="fileUrl">{{ fileUrl }}</div>
        </div>
      </div>
      <div class="result-actions">
        <el-button size="mini" type="text" :icon="Refresh" @click="resetUpload">重新上传</el-button>
        <el-button size="mini" type="text" :icon="Delete" class="btn-delete" @click="clearFile">清除</el-button>
      </div>
    </div>

    <!-- 上传中，展示进度 -->
    <div v-else-if="uploading" class="upload-progress">
      <div class="progress-icon">
        <LegacyIcon name="el-icon-loading" />
      </div>
      <div class="progress-content">
        <div class="progress-name" :title="currentFileName">{{ currentFileName }}</div>
        <el-progress
          :percentage="progress"
          :stroke-width="8"
          :status="progressStatus"
          class="progress-bar"
        />
        <div class="progress-text">
          <span>{{ progressLabel }}</span>
          <span class="progress-speed">{{ uploadSpeed }}</span>
        </div>
      </div>
      <el-button size="mini" type="text" :icon="Close" class="btn-cancel" @click="cancelUpload">取消</el-button>
    </div>

    <!-- 初始状态，点击或拖拽上传 -->
    <div v-else class="upload-trigger" @click="triggerSelect">
      <input
        ref="fileInput"
        type="file"
        :accept="accept"
        style="display:none;"
        @change="onFileSelected"
      >
      <div class="trigger-content">
        <LegacyIcon name="el-icon-upload" class="trigger-icon" />
        <div class="trigger-text">
          <span class="trigger-primary">点击上传</span>
          <span class="trigger-secondary">或将文件拖拽到此处</span>
        </div>
        <div class="trigger-tip">{{ tipText }}</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import CryptoJS from 'crypto-js'
import axios from 'axios'
import { getUploadConfig, chunkUpload } from '@/api/upload'
import { getToken } from '@/utils/auth'
import { ElMessage } from 'element-plus'
import { Close, Delete, Refresh } from '@element-plus/icons-vue'
import { computed, ref, watch } from 'vue'

// 分片大小：1.5MB（需小于 PHP 的 upload_max_filesize，默认 2MB）
// 若已修改 php.ini 中 upload_max_filesize >= 10M，可改为 5 * 1024 * 1024
const CHUNK_SIZE = 1.5 * 1024 * 1024

defineOptions({
  name: 'BigFileUpload'
})

const props = defineProps({
  // v-model 绑定的文件 URL
  modelValue: {
    type: String,
    default: ''
  },
  // 接受的文件类型（MIME 或扩展名），默认音视频
  accept: {
    type: String,
    default: 'video/*,audio/*,.mp4,.avi,.mov,.wmv,.flv,.mkv,.mp3,.wav,.aac,.flac,.m4a,.ogg'
  },
  // 允许的扩展名（用于前端校验）
  allowExtensions: {
    type: Array,
    default: () => ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'mp3', 'wav', 'aac', 'flac', 'm4a', 'ogg']
  },
  // 最大文件大小（MB，0表示不限制）
  maxSize: {
    type: Number,
    default: 0
  },
  // 提示文字
  tip: {
    type: String,
    default: ''
  },
  // 是否禁用
  disabled: {
    type: Boolean,
    default: false
  },
  // 试听/试看自动截取秒数（0 = 不截取，>0 则上传完成后触发 @trial-clip 事件）
  trialSeconds: {
    type: Number,
    default: 0
  }
})

const emit = defineEmits(['update:modelValue', 'success', 'error', 'duration', 'trial-clipping', 'trial-uploaded', 'clear'])

const fileUrl = ref(props.modelValue || '')
const fileName = ref('')
const uploading = ref(false)
const isDragover = ref(false)
const progress = ref(0)
const progressStatus = ref(null) // null | 'success' | 'exception'
const currentFileName = ref('')
const uploadSpeed = ref('')
const uploadConfig = ref(null)
// 用于取消本地分片上传
const cancelled = ref(false)
// COS / OSS / Qiniu 使用 axios CancelToken
const cancelTokenSource = ref(null)
// 断点续传：记录已上传分片
const uploadedChunks = ref([])
// 速度计算（非响应式内部变量）
let lastLoaded = 0
let lastTime = 0
// 当前选中的原始 File 对象（用于截取试听/试看）
let currentRawFile = null
const fileInput = ref(null)

const fileIconClass = computed(() => {
  if (!fileUrl.value) return 'el-icon-document'
  const ext = (fileUrl.value.split('.').pop() || '').toLowerCase()
  const videoExts = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv']
  const audioExts = ['mp3', 'wav', 'aac', 'flac', 'm4a', 'ogg']
  if (videoExts.includes(ext)) return 'el-icon-video-camera'
  if (audioExts.includes(ext)) return 'el-icon-headset'
  return 'el-icon-document'
})

const progressLabel = computed(() => {
  if (progress.value < 100) return `上传中 ${progress.value}%`
  return '处理中，请稍候...'
})

const tipText = computed(() => {
  if (props.tip) return props.tip
  const parts = []
  if (props.allowExtensions.length) {
    parts.push('支持 ' + props.allowExtensions.join('/').toUpperCase())
  }
  if (props.maxSize > 0) {
    parts.push(`最大 ${props.maxSize}MB`)
  }
  parts.push('支持拖拽上传，大文件自动分片断点续传')
  return parts.join('，')
})

watch(() => props.modelValue, (val) => {
  fileUrl.value = val || ''
})

// ===== 触发文件选择 =====
function triggerSelect() {
  if (props.disabled || uploading.value) return
  fileInput.value.value = ''
  fileInput.value.click()
}

// ===== 文件选择回调 =====
function onFileSelected(e) {
  const file = e.target.files[0]
  if (file) startUpload(file)
}

// ===== 读取媒体时长（通过隐藏元素 loadedmetadata）=====
function readMediaDuration(file) {
  return new Promise((resolve) => {
    const ext = file.name.split('.').pop().toLowerCase()
    const videoExts = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv']
    const tag = videoExts.includes(ext) ? 'video' : 'audio'
    const el = document.createElement(tag)
    const url = URL.createObjectURL(file)
    el.preload = 'metadata'
    el.onloadedmetadata = () => {
      URL.revokeObjectURL(url)
      const dur = isFinite(el.duration) ? Math.round(el.duration) : 0
      resolve(dur)
    }
    el.onerror = () => {
      URL.revokeObjectURL(url)
      resolve(0)
    }
    el.src = url
  })
}

// ===== 拖拽事件 =====
function onDragover() {
  if (!props.disabled && !uploading.value) isDragover.value = true
}
function onDragleave() {
  isDragover.value = false
}
function onDrop(e) {
  isDragover.value = false
  if (props.disabled || uploading.value) return
  const file = e.dataTransfer.files[0]
  if (file) startUpload(file)
}

// ===== 前置校验 =====
function validateFile(file) {
  const ext = file.name.split('.').pop().toLowerCase()
  if (props.allowExtensions.length && !props.allowExtensions.includes(ext)) {
    ElMessage.error(`不支持的文件格式，请上传 ${props.allowExtensions.join('/')} 格式的文件`)
    return false
  }
  if (props.maxSize > 0 && file.size > props.maxSize * 1024 * 1024) {
    ElMessage.error(`文件大小不能超过 ${props.maxSize}MB`)
    return false
  }
  return true
}

// ===== 开始上传主流程 =====
async function startUpload(file) {
  if (!validateFile(file)) return

  currentRawFile = file
  uploading.value = true
  cancelled.value = false
  progress.value = 0
  progressStatus.value = null
  currentFileName.value = file.name
  uploadSpeed.value = ''
  lastLoaded = 0
  lastTime = Date.now()

  // 自动读取时长并抛出给父组件
  const dur = await readMediaDuration(file)
  if (dur > 0) {
    emit('duration', dur)
  }

  try {
    // 拉取上传配置
    if (!uploadConfig.value) {
      const res = await getUploadConfig()
      // eslint-disable-next-line require-atomic-updates
      uploadConfig.value = res.data
    }

    const type = uploadConfig.value.type

    let resultUrl = ''
    if (type === 'local') {
      resultUrl = await uploadByChunk(file)
    } else if (type === 'cos') {
      resultUrl = await uploadToCos(file)
    } else if (type === 'oss') {
      resultUrl = await uploadToOss(file)
    } else if (type === 'qiniu') {
      resultUrl = await uploadToQiniu(file)
    } else {
      resultUrl = await uploadByChunk(file)
    }

    if (resultUrl) {
      fileUrl.value = resultUrl
      fileName.value = file.name
      progress.value = 100
      progressStatus.value = 'success'
      emit('update:modelValue', resultUrl)
      emit('success', { url: resultUrl, name: file.name })
      // 自动截取试听/试看片段
      if (props.trialSeconds > 0 && currentRawFile) {
        generateTrialClip(currentRawFile)
      }
      setTimeout(() => { uploading.value = false }, 600)
    }
  } catch (err) {
    if (cancelled.value) {
      uploading.value = false
      return
    }
    progressStatus.value = 'exception'
    ElMessage.error(err.message || '上传失败，请重试')
    emit('error', err)
    setTimeout(() => { uploading.value = false }, 1000)
  }
}

// ===== 本地分片上传（含断点续传）=====
async function uploadByChunk(file) {
  const ext = file.name.split('.').pop().toLowerCase()
  const totalChunks = Math.ceil(file.size / CHUNK_SIZE)

  // 计算文件 MD5（用于断点续传标识）
  const fileMd5 = await calcMd5(file)

  if (cancelled.value) throw new Error('cancelled')

  // 先查询已上传的分片（断点续传）
  uploadedChunks.value = await getUploadedChunks(fileMd5, file.name, ext, totalChunks)

  const uploadedSet = new Set(uploadedChunks.value)
  let uploadedCount = uploadedSet.size

  for (let i = 0; i < totalChunks; i++) {
    if (cancelled.value) throw new Error('cancelled')

    // 已上传的分片跳过
    if (uploadedSet.has(i)) {
      uploadedCount++
      progress.value = Math.floor((uploadedCount / totalChunks) * 99)
      continue
    }

    const start = i * CHUNK_SIZE
    const end = Math.min(file.size, start + CHUNK_SIZE)
    const chunk = file.slice(start, end)

    const formData = new FormData()
    formData.append('file_md5', fileMd5)
    formData.append('chunk_index', i)
    formData.append('total_chunks', totalChunks)
    formData.append('file_name', file.name)
    formData.append('file_ext', ext)
    formData.append('chunk_data', chunk)

    const startTime = Date.now()
    const startSize = chunk.size

    const res = await chunkUpload(formData)

    // 计算速度
    const elapsed = (Date.now() - startTime) / 1000
    const speed = elapsed > 0 ? startSize / elapsed : 0
    uploadSpeed.value = formatSpeed(speed)

    uploadedCount++
    progress.value = Math.floor((uploadedCount / totalChunks) * 99)

    // 最后一片，后端返回 done:true 和文件 URL
    if (res.data && res.data.done) {
      return res.data.file_url
    }
  }

  return ''
}

// ===== 外部调用：手动触发重新截取（父组件 ref 调用）=====
async function reGenerateTrialClip() {
  if (!currentRawFile) {
    ElMessage.warning('请先上传完整文件后再截取试听片段')
    return
  }
  if (props.trialSeconds <= 0) {
    ElMessage.warning('试听秒数必须大于0')
    return
  }
  await generateTrialClip(currentRawFile)
}

// ===== 截取试听/试看片段并分片上传 =====
async function generateTrialClip(file) {
  try {
    const ext = file.name.split('.').pop().toLowerCase()
    const videoExts = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv']
    const isVideo = videoExts.includes(ext)

    emit('trial-clipping', true)
    let blob
    if (isVideo) {
      blob = await clipVideoBlob(file, props.trialSeconds)
    } else {
      blob = await clipAudioBlob(file, props.trialSeconds)
    }
    emit('trial-clipping', false)

    if (!blob) {
      ElMessage.warning('当前浏览器不支持视频截取')
      return
    }

    // 分片上传截取片段（避免单次请求超过 post_max_size）
    const trialExt = isVideo ? 'mp4' : 'mp3'
    const trialName = 'trial_' + Date.now() + '.' + trialExt
    const trialFile = new File([blob], trialName, { type: blob.type })

    const resultUrl = await uploadTrialByChunk(trialFile, trialExt)
    if (resultUrl) {
      emit('trial-uploaded', resultUrl)
    }
  } catch (e) {
    emit('trial-clipping', false)
    console.warn('[BigFileUpload] 截取/上传试听片段失败', e)
    ElMessage.error('试听片段处理失败：' + (e.message || '未知错误'))
  }
}

// 分片上传试听片段（复用 uploadByChunk 逻辑，但不显示主进度）
async function uploadTrialByChunk(file, ext) {
  // 确保上传配置已拉取
  if (!uploadConfig.value) {
    const res = await getUploadConfig()
    // eslint-disable-next-line require-atomic-updates
    uploadConfig.value = res.data
  }

  const totalChunks = Math.ceil(file.size / CHUNK_SIZE)
  const fileMd5 = 'trial_' + Date.now() + '_' + Math.random().toString(36).slice(2)

  const loadingMsg = ElMessage({ message: '试听片段上传中...', type: 'info', duration: 0 })
  try {
    for (let i = 0; i < totalChunks; i++) {
      const start = i * CHUNK_SIZE
      const end = Math.min(file.size, start + CHUNK_SIZE)
      const chunk = file.slice(start, end)

      const formData = new FormData()
      formData.append('file_md5', fileMd5)
      formData.append('chunk_index', i)
      formData.append('total_chunks', totalChunks)
      formData.append('file_name', file.name)
      formData.append('file_ext', ext)
      formData.append('chunk_data', chunk)

      const res = await chunkUpload(formData)
      if (res.data && res.data.done) {
        loadingMsg.close()
        return res.data.file_url
      }
    }
    loadingMsg.close()
    return ''
  } catch (e) {
    loadingMsg.close()
    throw e
  }
}

// 截取音频前 N 秒（Web Audio API + OfflineAudioContext）
function clipAudioBlob(file, seconds) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = async(e) => {
      try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)()
        const audioBuffer = await audioCtx.decodeAudioData(e.target.result)
        await audioCtx.close()

        const sampleRate = audioBuffer.sampleRate
        const channels = audioBuffer.numberOfChannels
        const framesToKeep = Math.min(Math.round(seconds * sampleRate), audioBuffer.length)

        const offlineCtx = new OfflineAudioContext(channels, framesToKeep, sampleRate)
        const bufferSource = offlineCtx.createBufferSource()
        bufferSource.buffer = audioBuffer
        bufferSource.connect(offlineCtx.destination)
        bufferSource.start(0)
        const renderedBuffer = await offlineCtx.startRendering()

        resolve(audioBufferToWavBlob(renderedBuffer))
      } catch (err) {
        reject(err)
      }
    }
    reader.onerror = reject
    reader.readAsArrayBuffer(file)
  })
}

// AudioBuffer → WAV Blob
function audioBufferToWavBlob(buffer) {
  const numChannels = buffer.numberOfChannels
  const sampleRate = buffer.sampleRate
  const numFrames = buffer.length
  const bytesPerSample = 2 // 16-bit
  const blockAlign = numChannels * bytesPerSample
  const byteRate = sampleRate * blockAlign
  const dataSize = numFrames * blockAlign
  const ab = new ArrayBuffer(44 + dataSize)
  const view = new DataView(ab)
  const ws = (off, str) => { for (let i = 0; i < str.length; i++) view.setUint8(off + i, str.charCodeAt(i)) }
  ws(0, 'RIFF'); view.setUint32(4, 36 + dataSize, true)
  ws(8, 'WAVE'); ws(12, 'fmt '); view.setUint32(16, 16, true)
  view.setUint16(20, 1, true); view.setUint16(22, numChannels, true)
  view.setUint32(24, sampleRate, true); view.setUint32(28, byteRate, true)
  view.setUint16(32, blockAlign, true); view.setUint16(34, 16, true)
  ws(36, 'data'); view.setUint32(40, dataSize, true)
  let off = 44
  for (let i = 0; i < numFrames; i++) {
    for (let ch = 0; ch < numChannels; ch++) {
      const s = Math.max(-1, Math.min(1, buffer.getChannelData(ch)[i]))
      view.setInt16(off, s < 0 ? s * 0x8000 : s * 0x7fff, true)
      off += 2
    }
  }
  return new Blob([ab], { type: 'audio/wav' })
}

// 截取视频前 N 秒（MediaRecorder + captureStream）
function clipVideoBlob(file, seconds) {
  return new Promise((resolve, reject) => {
    if (typeof MediaRecorder === 'undefined') return resolve(null)
    const mimeType = MediaRecorder.isTypeSupported('video/webm;codecs=vp9')
      ? 'video/webm;codecs=vp9'
      : MediaRecorder.isTypeSupported('video/webm') ? 'video/webm' : ''
    if (!mimeType) return resolve(null)

    const url = URL.createObjectURL(file)
    const video = document.createElement('video')
    video.muted = true
    video.preload = 'auto'
    video.src = url
    video.onloadeddata = () => {
      const chunks = []
      const recorder = new MediaRecorder(video.captureStream(), { mimeType })
      recorder.ondataavailable = (ev) => { if (ev.data.size > 0) chunks.push(ev.data) }
      recorder.onstop = () => {
        URL.revokeObjectURL(url)
        resolve(new Blob(chunks, { type: mimeType }))
      }
      recorder.start()
      video.play().catch(() => {})
      setTimeout(() => { recorder.stop(); video.pause() }, seconds * 1000)
    }
    video.onerror = (err) => { URL.revokeObjectURL(url); reject(err) }
  })
}

// 查询已上传分片（发送第0片时，后端会返回已上传的分片列表）
async function getUploadedChunks(fileMd5, fileName, fileExt, totalChunks) {
  if (totalChunks <= 1) return []
  try {
    const formData = new FormData()
    formData.append('file_md5', fileMd5)
    formData.append('chunk_index', -1) // 特殊值：仅查询
    formData.append('total_chunks', totalChunks)
    formData.append('file_name', fileName)
    formData.append('file_ext', fileExt)

    // 使用 -1 查询时后端不处理分片，直接返回已上传列表
    // 实际实现中：先检查第一片是否存在来判断是否可续传
    const res = await chunkUpload(formData)
    return (res.data && res.data.uploaded_chunks) ? res.data.uploaded_chunks : []
  } catch (e) {
    return []
  }
}

// 计算文件 MD5（取首尾各 2MB 快速计算）
function calcMd5(file) {
  return new Promise((resolve, reject) => {
    const sampleSize = 2 * 1024 * 1024
    const chunks = []
    chunks.push(file.slice(0, sampleSize))
    if (file.size > sampleSize * 2) {
      chunks.push(file.slice(file.size - sampleSize))
    }
    let index = 0
    let wordArray = CryptoJS.lib.WordArray.create([])
    const reader = new FileReader()

    const readNext = () => {
      if (index >= chunks.length) {
        resolve(CryptoJS.MD5(wordArray).toString())
        return
      }
      reader.readAsArrayBuffer(chunks[index++])
    }

    reader.onload = (e) => {
      const uint8 = new Uint8Array(e.target.result)
      const part = CryptoJS.lib.WordArray.create(uint8)
      wordArray = wordArray.concat(part)
      readNext()
    }
    reader.onerror = reject
    readNext()
  })
}

// ===== 腾讯云 COS 前端直传 =====
async function uploadToCos(file) {
  const cfg = uploadConfig.value.cos
  const ext = file.name.split('.').pop().toLowerCase()
  const key = `file/media/${dateDir()}/${Date.now()}_${Math.random().toString(36).slice(2)}.${ext}`
  const uploadUrl = `${cfg.upload_url}/${key}`

  const CancelToken = axios.CancelToken
  const source = CancelToken.source()
  cancelTokenSource.value = source

  await axios.put(uploadUrl, file, {
    headers: {
      'Content-Type': file.type || 'application/octet-stream',
      'Authorization': 'Bearer ' + getToken()
    },
    cancelToken: source.token,
    onUploadProgress: (e) => {
      if (e.total > 0) {
        progress.value = Math.floor((e.loaded / e.total) * 99)
        uploadSpeed.value = formatSpeed(e.loaded / ((Date.now() - lastTime) / 1000 + 1))
      }
    }
  })

  const cdn = cfg.cdn || cfg.url
  return cdn.replace(/\/$/, '') + '/' + key
}

// ===== 阿里云 OSS 前端直传（POST Policy）=====
async function uploadToOss(file) {
  const cfg = uploadConfig.value.oss
  const ext = file.name.split('.').pop().toLowerCase()
  const key = `${cfg.dir}${Date.now()}_${Math.random().toString(36).slice(2)}.${ext}`

  const formData = new FormData()
  formData.append('key', key)
  formData.append('policy', cfg.policy)
  formData.append('OSSAccessKeyId', cfg.access_id)
  formData.append('signature', cfg.signature)
  formData.append('success_action_status', '200')
  formData.append('file', file)

  const CancelToken = axios.CancelToken
  const source = CancelToken.source()
  cancelTokenSource.value = source

  await axios.post(cfg.host, formData, {
    cancelToken: source.token,
    onUploadProgress: (e) => {
      if (e.total > 0) {
        progress.value = Math.floor((e.loaded / e.total) * 99)
        uploadSpeed.value = formatSpeed(e.loaded / ((Date.now() - lastTime) / 1000 + 1))
      }
    }
  })

  return cfg.url.replace(/\/$/, '') + '/' + key
}

// ===== 七牛云前端直传 =====
async function uploadToQiniu(file) {
  const cfg = uploadConfig.value.qiniu
  const ext = file.name.split('.').pop().toLowerCase()
  const key = `file/media/${dateDir()}/${Date.now()}_${Math.random().toString(36).slice(2)}.${ext}`

  const formData = new FormData()
  formData.append('token', cfg.token)
  formData.append('key', key)
  formData.append('file', file)

  const CancelToken = axios.CancelToken
  const source = CancelToken.source()
  cancelTokenSource.value = source

  await axios.post(cfg.upload_url, formData, {
    cancelToken: source.token,
    onUploadProgress: (e) => {
      if (e.total > 0) {
        progress.value = Math.floor((e.loaded / e.total) * 99)
        uploadSpeed.value = formatSpeed(e.loaded / ((Date.now() - lastTime) / 1000 + 1))
      }
    }
  })

  return cfg.url.replace(/\/$/, '') + '/' + key
}

// ===== 取消上传 =====
function cancelUpload() {
  cancelled.value = true
  if (cancelTokenSource.value) {
    cancelTokenSource.value.cancel('用户取消上传')
    cancelTokenSource.value = null
  }
  uploading.value = false
  progress.value = 0
  progressStatus.value = null
  ElMessage.info('已取消上传')
}

// ===== 重置/清除 =====
function resetUpload() {
  fileUrl.value = ''
  fileName.value = ''
  uploadConfig.value = null // 强制重新拉配置（防止签名过期）
  emit('update:modelValue', '')
}
function clearFile() {
  fileUrl.value = ''
  fileName.value = ''
  emit('update:modelValue', '')
  emit('clear')
}

// ===== 工具方法 =====
function dateDir() {
  const now = new Date()
  return `${now.getFullYear()}/${String(now.getMonth() + 1).padStart(2, '0')}`
}
function formatSpeed(bytesPerSec) {
  if (bytesPerSec >= 1024 * 1024) {
    return (bytesPerSec / 1024 / 1024).toFixed(1) + ' MB/s'
  }
  if (bytesPerSec >= 1024) {
    return (bytesPerSec / 1024).toFixed(0) + ' KB/s'
  }
  return bytesPerSec.toFixed(0) + ' B/s'
}

defineExpose({
  reGenerateTrialClip,
  cancelUpload,
  resetUpload,
  clearFile
})
</script>

<style lang="scss" scoped>
.big-file-upload {
  border: 1px dashed #d9d9d9;
  border-radius: 6px;
  background: #fafafa;
  transition: border-color 0.2s;
  overflow: hidden;

  &:hover:not(.is-disabled) {
    border-color: #409eff;
  }

  &.is-dragover {
    border-color: #409eff;
    background: #ecf5ff;
  }

  &.is-disabled {
    cursor: not-allowed;
    opacity: 0.6;
  }
}

// ===== 触发区域 =====
.upload-trigger {
  cursor: pointer;
  padding: 32px 20px;
  text-align: center;

  .trigger-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
  }

  .trigger-icon {
    font-size: 48px;
    color: #c0c4cc;
  }

  .trigger-text {
    font-size: 14px;

    .trigger-primary {
      color: #409eff;
      font-weight: 500;
    }

    .trigger-secondary {
      color: #606266;
      margin-left: 4px;
    }
  }

  .trigger-tip {
    font-size: 12px;
    color: #909399;
    line-height: 1.6;
    max-width: 400px;
  }
}

// ===== 上传进度 =====
.upload-progress {
  display: flex;
  align-items: center;
  padding: 16px 20px;
  gap: 16px;

  .progress-icon {
    font-size: 28px;
    color: #409eff;
    flex-shrink: 0;
  }

  .progress-content {
    flex: 1;
    min-width: 0;

    .progress-name {
      font-size: 13px;
      color: #303133;
      margin-bottom: 8px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .progress-bar {
      margin-bottom: 4px;
    }

    .progress-text {
      display: flex;
      justify-content: space-between;
      font-size: 12px;
      color: #909399;

      .progress-speed {
        color: #606266;
      }
    }
  }

  .btn-cancel {
    color: #f56c6c;
    flex-shrink: 0;

    &:hover {
      color: #f56c6c;
      opacity: 0.8;
    }
  }
}

// ===== 上传结果 =====
.upload-result {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 20px;
  gap: 12px;

  .result-info {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
    flex: 1;

    .result-icon {
      font-size: 28px;
      color: #409eff;
      flex-shrink: 0;
    }

    .result-detail {
      min-width: 0;

      .result-name {
        font-size: 13px;
        color: #303133;
        font-weight: 500;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        max-width: 300px;
      }

      .result-url {
        font-size: 12px;
        color: #909399;
        margin-top: 2px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        max-width: 400px;
      }
    }
  }

  .result-actions {
    flex-shrink: 0;
    display: flex;
    gap: 4px;

    .btn-delete {
      color: #f56c6c;
    }
  }
}
</style>
