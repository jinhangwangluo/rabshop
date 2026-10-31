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
        <el-button size="mini" type="text" icon="el-icon-refresh" @click="resetUpload">重新上传</el-button>
        <el-button size="mini" type="text" icon="el-icon-delete" class="btn-delete" @click="clearFile">清除</el-button>
      </div>
    </div>

    <!-- 上传中，展示进度 -->
    <div
      v-else-if="uploading"
      class="upload-progress"
      :class="{ 'upload-progress--circle': progressType === 'circle' }"
    >
      <div v-if="progressType !== 'circle'" class="progress-icon">
        <LegacyIcon name="el-icon-loading" />
      </div>
      <div v-else class="progress-circle-wrap">
        <el-progress
          type="circle"
          :percentage="progress"
          :width="72"
          :stroke-width="8"
          :status="progressStatus"
        />
      </div>
      <div class="progress-content">
        <div class="progress-name" :title="currentFileName">{{ currentFileName }}</div>
        <el-progress
          v-if="progressType !== 'circle'"
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
      <el-button size="mini" type="text" icon="el-icon-close" class="btn-cancel" @click="cancelUpload">取消</el-button>
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
        <LegacyIcon name="el-icon-upload" class=" trigger-icon" />
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
import { computed, getCurrentInstance, ref, watch } from 'vue'
import CryptoJS from 'crypto-js'
import axios from 'axios'
import { getUploadConfig, chunkUpload } from '@/api/upload'
import { fileRecordApi } from '@/api/file'
import { getToken } from '@/utils/auth'
import { normalizeResourceUrl } from '@/utils'

// 分片大小：1.5MB（需小于 PHP 的 upload_max_filesize，默认 2MB）
// 若已修改 php.ini 中 upload_max_filesize >= 10M，可改为 5 * 1024 * 1024
const CHUNK_SIZE = 1.5 * 1024 * 1024
const AUDIO_EXTENSIONS = ['mp3', 'wav', 'aac', 'flac', 'm4a', 'ogg', 'wma', 'ape', 'amr', 'mid', 'midi']
const VIDEO_EXTENSIONS = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm', 'mpeg', 'mpg', 'm4v', '3gp', 'rmvb', 'rm']

defineOptions({
  name: 'BigFileUpload'
})

const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  },
  accept: {
    type: String,
    default: 'video/*,audio/*,.mp4,.avi,.mov,.wmv,.flv,.mkv,.webm,.mp3,.wav,.aac,.flac,.m4a,.ogg'
  },
  allowExtensions: {
    type: Array,
    default: () => ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm', 'mp3', 'wav', 'aac', 'flac', 'm4a', 'ogg']
  },
  maxSize: {
    type: Number,
    default: 0
  },
  tip: {
    type: String,
    default: ''
  },
  disabled: {
    type: Boolean,
    default: false
  },
  trialSeconds: {
    type: Number,
    default: 0
  },
  progressType: {
    type: String,
    default: 'line',
    validator: value => ['line', 'circle'].includes(value)
  }
})

const emit = defineEmits(['update:modelValue', 'success', 'error', 'duration', 'trial-clipping', 'trial-uploaded', 'clear'])
const fileInput = ref(null)
const fileUrl = ref(props.modelValue || '')
const fileName = ref('')
const uploading = ref(false)
const isDragover = ref(false)
const progress = ref(0)
const progressStatus = ref(null)
const currentFileName = ref('')
const uploadSpeed = ref('')
const uploadConfig = ref(null)
const cancelled = ref(false)
const cancelTokenSource = ref(null)
const uploadedChunks = ref([])
const lastLoaded = ref(0)
const lastTime = ref(0)
const currentRawFile = ref(null)

const instance = getCurrentInstance()
const messageApi = instance && instance.proxy ? instance.proxy.$message : null

const fileIconClass = computed(() => {
  if (!fileUrl.value) return 'el-icon-document'
  const ext = (fileUrl.value.split('.').pop() || '').toLowerCase()
  const videoExts = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm']
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

watch(() => props.modelValue, (value) => {
  fileUrl.value = value || ''
})

function triggerSelect() {
  if (props.disabled || uploading.value) return
  if (fileInput.value) {
    fileInput.value.value = ''
    fileInput.value.click()
  }
}

function onFileSelected(e) {
  const file = e.target.files[0]
  if (file) {
    void startUpload(file)
  }
}

function readMediaDuration(file) {
  return new Promise((resolve) => {
    const ext = file.name.split('.').pop().toLowerCase()
    const videoExts = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm']
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
  if (file) {
    void startUpload(file)
  }
}

function validateFile(file) {
  const ext = file.name.split('.').pop().toLowerCase()
  if (props.allowExtensions.length && !props.allowExtensions.includes(ext)) {
    messageApi && messageApi.error(`不支持的文件格式，请上传 ${props.allowExtensions.join('/')} 格式的文件`)
    return false
  }
  if (props.maxSize > 0 && file.size > props.maxSize * 1024 * 1024) {
    messageApi && messageApi.error(`文件大小不能超过 ${props.maxSize}MB`)
    return false
  }
  return true
}

async function startUpload(file) {
  if (!validateFile(file)) return

  currentRawFile.value = file
  uploading.value = true
  cancelled.value = false
  progress.value = 0
  progressStatus.value = null
  currentFileName.value = file.name
  uploadSpeed.value = ''
  lastLoaded.value = 0
  lastTime.value = Date.now()

  const dur = await readMediaDuration(file)
  if (dur > 0) {
    emit('duration', dur)
  }

  try {
    if (!uploadConfig.value) {
      const res = await getUploadConfig()
      // eslint-disable-next-line require-atomic-updates
      uploadConfig.value = res.data
    }

    const type = uploadConfig.value.type

    let uploadResult = null
    if (type === 'local') {
      uploadResult = await uploadByChunk(file)
    } else if (type === 'cos') {
      uploadResult = await uploadToCos(file)
    } else if (type === 'oss') {
      uploadResult = await uploadToOss(file)
    } else if (type === 'qiniu') {
      uploadResult = await uploadToQiniu(file)
    } else {
      uploadResult = await uploadByChunk(file)
    }

    const normalizedResult = normalizeUploadResult(uploadResult)
    let resultUrl = normalizedResult.url
    if (resultUrl) {
      resultUrl = normalizeResourceUrl(resultUrl)
      const fileRecord = await recordUploadedFile(file, normalizedResult, type)
      fileUrl.value = resultUrl
      fileName.value = file.name
      progress.value = 100
      progressStatus.value = 'success'
      emit('update:modelValue', resultUrl)
      emit('success', { url: resultUrl, name: file.name, file: fileRecord })
      if (props.trialSeconds > 0 && currentRawFile.value) {
        void generateTrialClip(currentRawFile.value)
      }
      setTimeout(() => {
        uploading.value = false
      }, 600)
    }
  } catch (err) {
    if (cancelled.value) {
      uploading.value = false
      return
    }
    progressStatus.value = 'exception'
    messageApi && messageApi.error(err.message || '上传失败，请重试')
    emit('error', err)
    setTimeout(() => {
      uploading.value = false
    }, 1000)
  }
}

async function uploadByChunk(file) {
  const ext = file.name.split('.').pop().toLowerCase()
  const totalChunks = Math.ceil(file.size / CHUNK_SIZE)
  const fileMd5 = await calcMd5(file)

  if (cancelled.value) throw new Error('cancelled')

  uploadedChunks.value = await getUploadedChunks(fileMd5, file.name, ext, totalChunks)

  const uploadedSet = new Set(uploadedChunks.value)
  let uploadedCount = uploadedSet.size
  let lastFileUrl = ''

  for (let i = 0; i < totalChunks; i++) {
    if (cancelled.value) throw new Error('cancelled')

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
    formData.append('file_size', file.size)
    formData.append('chunk_data', chunk)

    const startTime = Date.now()
    const startSize = chunk.size

    const res = await chunkUpload(formData)

    const elapsed = (Date.now() - startTime) / 1000
    const speed = elapsed > 0 ? startSize / elapsed : 0
    uploadSpeed.value = formatSpeed(speed)

    uploadedCount++
    progress.value = Math.floor((uploadedCount / totalChunks) * 99)

    if (res.data && res.data.file_url) {
      lastFileUrl = res.data.file_url
    }

    if (res.data && res.data.done) {
      return {
        url: normalizeResourceUrl(res.data.file_url),
        path: res.data.file_path,
        update_type: 'local',
        file: res.data
      }
    }
  }

  return {
    url: normalizeResourceUrl(lastFileUrl),
    update_type: 'local'
  }
}

async function reGenerateTrialClip() {
  if (!currentRawFile.value) {
    messageApi && messageApi.warning('请先上传完整文件后再截取试听片段')
    return
  }
  if (props.trialSeconds <= 0) {
    messageApi && messageApi.warning('试听秒数必须大于0')
    return
  }
  await generateTrialClip(currentRawFile.value)
}

async function generateTrialClip(file) {
  try {
    const ext = file.name.split('.').pop().toLowerCase()
    const videoExts = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm']
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
      messageApi && messageApi.warning('当前浏览器不支持视频截取')
      return
    }

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
    messageApi && messageApi.error('试听片段处理失败：' + (e.message || '未知错误'))
  }
}

async function uploadTrialByChunk(file, ext) {
  if (!uploadConfig.value) {
    const res = await getUploadConfig()
    // eslint-disable-next-line require-atomic-updates
    uploadConfig.value = res.data
  }

  const totalChunks = Math.ceil(file.size / CHUNK_SIZE)
  const fileMd5 = await calcMd5(file)

  const loadingMsg = messageApi ? messageApi({ message: '试听片段上传中...', type: 'info', duration: 0 }) : null
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
      formData.append('file_size', file.size)
      formData.append('chunk_data', chunk)

      const res = await chunkUpload(formData)
      if (res.data && res.data.done) {
        loadingMsg && loadingMsg.close()
        const uploadResult = {
          url: normalizeResourceUrl(res.data.file_url),
          path: res.data.file_path,
          update_type: 'local',
          file: res.data
        }
        await recordUploadedFile(file, uploadResult, 'local')
        return uploadResult.url
      }
    }
    loadingMsg && loadingMsg.close()
    return ''
  } catch (e) {
    loadingMsg && loadingMsg.close()
    throw e
  }
}

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

function audioBufferToWavBlob(buffer) {
  const numChannels = buffer.numberOfChannels
  const sampleRate = buffer.sampleRate
  const numFrames = buffer.length
  const bytesPerSample = 2
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

async function getUploadedChunks(fileMd5, fileName, fileExt, totalChunks) {
  if (totalChunks <= 1) return []
  try {
    const formData = new FormData()
    formData.append('file_md5', fileMd5)
    formData.append('chunk_index', -1)
    formData.append('total_chunks', totalChunks)
    formData.append('file_name', fileName)
    formData.append('file_ext', fileExt)

    const res = await chunkUpload(formData)
    return (res.data && res.data.uploaded_chunks) ? res.data.uploaded_chunks : []
  } catch (e) {
    return []
  }
}

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
        uploadSpeed.value = formatSpeed(e.loaded / ((Date.now() - lastTime.value) / 1000 + 1))
      }
    }
  })

  const cdn = cfg.cdn || cfg.url
  return {
    url: normalizeResourceUrl(cdn.replace(/\/$/, '') + '/' + key),
    path: key,
    update_type: 'cos'
  }
}

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
        uploadSpeed.value = formatSpeed(e.loaded / ((Date.now() - lastTime.value) / 1000 + 1))
      }
    }
  })

  return {
    url: normalizeResourceUrl(cfg.url.replace(/\/$/, '') + '/' + key),
    path: key,
    update_type: 'oss'
  }
}

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
        uploadSpeed.value = formatSpeed(e.loaded / ((Date.now() - lastTime.value) / 1000 + 1))
      }
    }
  })

  return {
    url: normalizeResourceUrl(cfg.url.replace(/\/$/, '') + '/' + key),
    path: key,
    update_type: 'qiniu'
  }
}

function cancelUpload() {
  cancelled.value = true
  if (cancelTokenSource.value) {
    cancelTokenSource.value.cancel('用户取消上传')
    cancelTokenSource.value = null
  }
  uploading.value = false
  progress.value = 0
  progressStatus.value = null
  messageApi && messageApi.info('已取消上传')
}

function resetUpload() {
  fileUrl.value = ''
  fileName.value = ''
  uploadConfig.value = null
  emit('update:modelValue', '')
}

function clearFile() {
  fileUrl.value = ''
  fileName.value = ''
  emit('update:modelValue', '')
  emit('clear')
}

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

function normalizeUploadResult(result) {
  if (typeof result === 'string') {
    return { url: result }
  }
  const data = result || {}
  return {
    url: data.url || data.file_url || data.filePath || data.path || '',
    path: data.file_path || data.path || '',
    update_type: data.update_type || data.updateType || '',
    file: data.file || null
  }
}

async function recordUploadedFile(file, uploadResult, uploadType) {
  const result = normalizeUploadResult(uploadResult)
  const existingFile = result.file || null
  const payload = {
    path: result.path || result.url,
    url: result.url,
    name: existingFile && existingFile.name ? existingFile.name : file.name,
    real_name: file.name,
    file_size: String(file.size || ''),
    update_type: result.update_type || uploadType || 'local',
    file_type: getFileTypeByName(file.name)
  }
  const res = await fileRecordApi(payload)
  return res.data || existingFile || null
}

function getFileTypeByName(fileName) {
  const ext = (fileName.split('.').pop() || '').toLowerCase()
  if (AUDIO_EXTENSIONS.includes(ext)) return 1
  if (VIDEO_EXTENSIONS.includes(ext)) return 2
  return 3
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
  border: 1px dashed var(--prev-border-color-base);
  border-radius: 6px;
  color: var(--prev-color-text-primary);
  background: var(--prev-bg-color);
  transition: border-color 0.2s, background-color 0.2s;
  overflow: hidden;

  &:hover:not(.is-disabled) {
    border-color: var(--prev-color-primary);
  }

  &.is-dragover {
    border-color: var(--prev-color-primary);
    background: var(--prev-color-primary-light-9);
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
    color: var(--prev-color-text-placeholder);
  }

  .trigger-text {
    font-size: 14px;

    .trigger-primary {
      color: var(--prev-color-primary);
      font-weight: 500;
    }

    .trigger-secondary {
      color: var(--prev-color-text-regular);
      margin-left: 4px;
    }
  }

  .trigger-tip {
    font-size: 12px;
    color: var(--prev-color-text-secondary);
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
    color: var(--prev-color-primary);
    flex-shrink: 0;
  }

  .progress-content {
    flex: 1;
    min-width: 0;

    .progress-name {
      font-size: 13px;
      color: var(--prev-color-text-primary);
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
      color: var(--prev-color-text-secondary);

      .progress-speed {
        color: var(--prev-color-text-regular);
      }
    }
  }

  .btn-cancel {
    color: var(--dashboard-danger);
    flex-shrink: 0;

    &:hover {
      color: var(--dashboard-danger);
      opacity: 0.8;
    }
  }
}

.upload-progress--circle {
  min-height: 104px;

  .progress-circle-wrap {
    flex-shrink: 0;
    width: 72px;
    height: 72px;
  }

  .progress-content {
    .progress-name {
      margin-bottom: 6px;
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
      color: var(--prev-color-primary);
      flex-shrink: 0;
    }

    .result-detail {
      min-width: 0;

      .result-name {
        font-size: 13px;
        color: var(--prev-color-text-primary);
        font-weight: 500;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        max-width: 300px;
      }

      .result-url {
        font-size: 12px;
        color: var(--prev-color-text-secondary);
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
      color: var(--dashboard-danger);
    }
  }
}
</style>
