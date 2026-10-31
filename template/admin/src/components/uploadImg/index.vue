<template>
  <div>
    <el-dialog
      v-model="uploadModal"
      :title="dialogTitle"
      :append-to-body="true"
      :width="isIframe ? '50%' : '1024px'"
      :fullscreen="isIframe"
      :before-close="handleBeforeClose"
      @closed="closed"
    >
      <div class="main">
        <el-form ref="ruleFormRef" :model="ruleForm" :rules="rules" label-width="100px" class="demo-ruleForm">
          <el-form-item label="上传至分组：" prop="region">
            <el-cascader
              v-model="ruleForm.region"
              class="form-width"
              :disabled="isUploading"
              :props="cascaderProps"
              :options="categoryList"
              @change="handleChange"
            />
          </el-form-item>
          <el-form-item :label="uploadLabel" prop="region">
            <div class="acea-row">
              <div class="uploadCont">
                <el-upload
                  ref="uploadRef"
                  :action="fileUrl"
                  list-type="picture-card"
                  :on-change="fileChange"
                  :file-list="ruleForm.imgList"
                  :auto-upload="false"
                  :data="uploadData"
                  :headers="header"
                  :multiple="true"
                  :limit="limit"
                  :disabled="isUploading"
                  :accept="accept"
                >
                  <LegacyIcon name="el-icon-plus" />
                  <template #file="{ file }">
                    <div
                      :class="['upload-list-file', file.uploadStatus ? 'is-' + file.uploadStatus : '']"
                      draggable="false"
                      @dragstart="handleDragStart($event, file)"
                      @dragover="handleDragOver($event, file)"
                      @dragenter="handleDragEnter($event, file)"
                      @dragend="handleDragEnd($event, file)"
                    >
                      <img v-if="isImageType" class="el-upload-list__item-thumbnail" :src="file.url" alt="">
                      <div v-else class="upload-file-card">
                        <LegacyIcon :name="fileIcon" />
                        <span>{{ getFileExt(file) }}</span>
                      </div>
                      <LegacyIcon
                        v-if="!isUploading"
                        name="el-icon-error"
                        class="btndel"
                        @click="handleWebRemove(file)"
                      />
                      <div v-if="file.uploadStatus && file.uploadStatus !== 'ready'" class="upload-status-mask">
                        <LegacyIcon :name="getUploadStatusIcon(file)" />
                        <span>{{ getUploadStatusText(file) }}</span>
                      </div>
                    </div>
                  </template>
                </el-upload>
                <div class="tips">{{ tipsText }}</div>
              </div>
            </div>
          </el-form-item>
        </el-form>
      </div>

      <template #footer>
        <span class="dialog-footer">
          <el-button @click="handleCancelClick">取 消</el-button>
          <el-button
            type="primary"
            :loading="isUploading"
            :disabled="!canSubmitUpload"
            @click="submitUpload"
          >{{ submitButtonText }}</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, reactive, ref, toRefs, watch } from 'vue'
import Setting from '@/settings'
import { getToken } from '@/utils/auth'
import { fileUploadApi } from '@/api/file'
import compressImg from '@/utils/compressImg.js'
import { isPicUpload } from '@/utils'

const FILE_TYPE_MAP = {
  image: {
    value: 0,
    label: '图片',
    accept: '.jpg,.jpeg,.png,.gif,.webp',
    icon: 'el-icon-picture',
    extensions: ['jpg', 'jpeg', 'png', 'gif', 'webp']
  },
  video: {
    value: 2,
    label: '视频',
    accept: 'video/*,.mp4,.avi,.mov,.wmv,.flv,.mkv,.webm',
    icon: 'el-icon-video-camera',
    extensions: ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm']
  },
  audio: {
    value: 1,
    label: '音频',
    accept: 'audio/*,.mp3,.wav,.aac,.flac,.m4a,.ogg',
    icon: 'el-icon-headset',
    extensions: ['mp3', 'wav', 'aac', 'flac', 'm4a', 'ogg']
  },
  other: {
    value: 3,
    label: '文件',
    accept: '',
    icon: 'el-icon-document',
    extensions: []
  }
}

defineOptions({
  name: 'UploadImg'
})

const props = defineProps({
  categoryList: {
    type: Array,
    default: () => []
  },
  categoryId: {
    type: [String, Number],
    default: ''
  },
  isPage: {
    type: Boolean,
    default: false
  },
  isIframe: {
    type: Boolean,
    default: false
  },
  fileType: {
    type: String,
    default: 'image'
  }
})
const emit = defineEmits(['uploadSuccess'])
const { categoryList, categoryId, isIframe, fileType } = toRefs(props)
const { proxy } = getCurrentInstance() || {}

const ruleFormRef = ref(null)
const uploadRef = ref(null)
const webImgUrl = ref('')
const uploadModal = ref(false)
const fileUrl = Setting.baseUri + '/setting/file/upload'
const header = {
  Authorization: 'Bearer ' + getToken()
}
const uploadData = ref({})
const cascaderProps = { checkStrictly: true, emitPath: false, label: 'label', value: 'id' }
const ruleForm = reactive({
  type: 0,
  region: '',
  imgList: []
})
const rules = { type: [{ required: true, message: '请选择活动资源', trigger: 'change' }] }
const limit = 20
const loading = ref(false)
const uploadSessionId = ref(0)
const uploadCancelRequested = ref(false)
const currentUploadTotal = ref(0)
const currentUploadFinished = ref(0)
const dragging = ref(null)

const currentTypeConfig = computed(() => FILE_TYPE_MAP[fileType.value] || FILE_TYPE_MAP.image)
const isImageType = computed(() => fileType.value === 'image')
const dialogTitle = computed(() => '上传' + currentTypeConfig.value.label)
const uploadLabel = computed(() => '上传' + currentTypeConfig.value.label + '：')
const accept = computed(() => currentTypeConfig.value.accept)
const fileIcon = computed(() => currentTypeConfig.value.icon)
const tipsText = computed(() => {
  if (fileType.value === 'image') {
    return '建议上传图片最大宽度750px，不超过3MB；仅支持jpeg、jpg、png、gif、webp格式，可拖拽调整上传顺序'
  }
  if (fileType.value === 'video') {
    return '支持mp4、avi、mov、wmv、flv、mkv、webm格式，可批量上传'
  }
  if (fileType.value === 'audio') {
    return '支持mp3、wav、aac、flac、m4a、ogg格式，可批量上传'
  }
  return '可上传文档、压缩包等其他文件，可批量上传'
})
const isUploading = computed(() => loading.value)
const canSubmitUpload = computed(() => ruleForm.imgList.length > 0 && !isUploading.value)
const submitButtonText = computed(() => {
  if (isUploading.value) {
    return `上传中 ${currentUploadFinished.value}/${currentUploadTotal.value || ruleForm.imgList.length}`
  }
  if (ruleForm.imgList.some((file) => file.uploadStatus === 'error')) {
    return '重新上传'
  }
  return '确 定'
})

watch(uploadModal, (newVal) => {
  if (newVal) ruleForm.region = categoryId.value
}, { immediate: true })

function getImg() {
  if (!webImgUrl.value) {
    proxy?.$message?.error('请先输入图片地址')
    return
  }
  if (webImgUrl.value.indexOf('.php') !== -1) {
    proxy?.$message?.error('请先输入其他图片地址')
    return
  }
  ruleForm.imgList.push({
    url: webImgUrl.value
  })
}

async function submitUpload() {
  if (!ruleForm.imgList.length) return proxy?.$message?.warning('请先选择' + currentTypeConfig.value.label)
  uploadData.value = {
    pid: ruleForm.region
  }
  if (loading.value) return

  const files = ruleForm.imgList.filter((file) => file.uploadStatus !== 'success')
  if (!files.length) {
    handleUploadComplete()
    return
  }

  const sessionId = uploadSessionId.value + 1
  uploadSessionId.value = sessionId
  uploadCancelRequested.value = false
  currentUploadTotal.value = files.length
  currentUploadFinished.value = 0
  loading.value = true
  files.forEach((file) => {
    setUploadStatus(file, 'pending')
  })

  for (let i = 0; i < files.length; i++) {
    if (shouldStopUpload(sessionId)) return

    const item = files[i]
    try {
      const uploaded = await uploadItem(item, sessionId)
      if (!uploaded || shouldStopUpload(sessionId)) return
      currentUploadFinished.value += 1
    } catch (error) {
      if (shouldStopUpload(sessionId)) return
      currentUploadFinished.value += 1
      proxy?.$message?.error((error && error.message) || '上传失败')
    }
  }

  if (shouldStopUpload(sessionId)) return
  loading.value = false
  const hasError = ruleForm.imgList.some((file) => file.uploadStatus === 'error')
  if (hasError) {
    proxy?.$message?.error('部分' + currentTypeConfig.value.label + '上传失败，请重试')
    return
  }

  handleUploadComplete()
}

function uploadItem(file, sessionId) {
  return new Promise((resolve, reject) => {
    const formData = new FormData()
    const rawFile = file && file.raw
    if (!rawFile) {
      setUploadStatus(file, 'error', '文件无效')
      reject(new Error(currentTypeConfig.value.label + '文件无效，请重新选择'))
      return
    }
    setUploadStatus(file, 'uploading')
    formData.append('file', rawFile)
    formData.append('cate_id', ruleForm.region)
    formData.append('file_type', currentTypeConfig.value.value)
    fileUploadApi(formData)
      .then((res) => {
        if (shouldStopUpload(sessionId)) {
          resolve(false)
          return
        }
        if (res.status === 200) {
          setUploadStatus(file, 'success')
          resolve(true)
        } else {
          setUploadStatus(file, 'error', res.message || '上传失败')
          reject(new Error(res.message || '上传失败'))
        }
      })
      .catch((err) => {
        if (shouldStopUpload(sessionId)) {
          resolve(false)
          return
        }
        const message = (err && (err.message || err.msg)) || '上传失败'
        setUploadStatus(file, 'error', message)
        reject(new Error(message))
      })
  })
}

function handleWebRemove(file) {
  if (isUploading.value) {
    proxy?.$message?.warning(currentTypeConfig.value.label + '上传中，暂不能删除')
    return
  }
  const index = ruleForm.imgList.findIndex((e) => {
    return e.uid === file.uid || e.url === file.url
  })
  if (index !== -1) ruleForm.imgList.splice(index, 1)
}

async function fileChange(file, fileList) {
  if (isUploading.value) return
  if (isValidUploadFile(file)) {
    ensureUploadFileStatus(file)
    if (isImageType.value && file.size >= 2097152) {
      const res = await comImg(file.raw)
      fileList.map((e) => {
        if (e.uid === file.uid) {
          e.raw = res
        }
        ensureUploadFileStatus(e)
      })
      ruleForm.imgList = fileList
    } else {
      fileList.map((e) => ensureUploadFileStatus(e))
      ruleForm.imgList = fileList
    }
  } else {
    ruleForm.imgList = fileList.filter((item) => item.uid !== file.uid)
  }
}

function comImg(file) {
  return new Promise((resolve) => {
    compressImg(file).then((res) => {
      resolve(res)
    })
  })
}

function handleChange() {
  if (ruleForm.type === 2) scanUploadQrcode()
}

function scanUploadQrcode() {}

function handleDragStart(e, item) {
  if (isUploading.value) return
  dragging.value = item
}

function handleDragEnd() {
  dragging.value = null
}

function handleDragOver(e) {
  if (isUploading.value) return
  e.dataTransfer.dropEffect = 'move'
}

function handleDragEnter(e, item) {
  if (isUploading.value) return
  e.dataTransfer.effectAllowed = 'move'
  if (item === dragging.value) {
    return
  }
  const newItems = [...ruleForm.imgList]
  const src = newItems.indexOf(dragging.value)
  const dst = newItems.indexOf(item)
  newItems.splice(dst, 0, ...newItems.splice(src, 1))
  ruleForm.imgList = newItems
}

function handleBeforeClose(done) {
  confirmCloseIfUploading(done)
}

function closed() {
  if (!isUploading.value) initData()
}

function handleCancelClick() {
  confirmCloseIfUploading(() => {
    uploadModal.value = false
  })
}

function confirmCloseIfUploading(done) {
  if (!isUploading.value) {
    done()
    return
  }

  proxy?.$confirm(currentTypeConfig.value.label + '仍在上传中，确定取消本次上传吗？', '提示', { type: 'warning' })
    .then(() => {
      cancelCurrentUpload()
      done()
    })
    .catch(() => {})
}

function cancelCurrentUpload() {
  uploadCancelRequested.value = true
  uploadSessionId.value += 1
  loading.value = false
  currentUploadTotal.value = 0
  currentUploadFinished.value = 0
}

function handleUploadComplete() {
  proxy?.$message?.success('上传成功')
  emit('uploadSuccess')
  loading.value = false
  uploadModal.value = false
}

function ensureUploadFileStatus(file) {
  if (!file.uploadStatus) {
    file.uploadStatus = 'ready'
  }
  if (!file.uploadMessage) {
    file.uploadMessage = ''
  }
}

function setUploadStatus(file, status, message = '') {
  if (!file) return
  file.uploadStatus = status
  file.uploadMessage = message
}

function shouldStopUpload(sessionId) {
  return uploadCancelRequested.value || sessionId !== uploadSessionId.value
}

function getUploadStatusText(file) {
  const status = file.uploadStatus || 'ready'
  const statusMap = {
    pending: '等待上传',
    uploading: '上传中',
    success: '上传成功',
    error: file.uploadMessage || '上传失败'
  }
  return statusMap[status] || ''
}

function getUploadStatusIcon(file) {
  const status = file.uploadStatus || 'ready'
  const iconMap = {
    pending: 'el-icon-time',
    uploading: 'el-icon-loading',
    success: 'el-icon-circle-check',
    error: 'el-icon-circle-close'
  }
  return iconMap[status] || ''
}

function isValidUploadFile(file) {
  if (isImageType.value) {
    return isPicUpload(file)
  }
  if (!currentTypeConfig.value.extensions.length) {
    return true
  }
  const ext = getFileExt(file).toLowerCase()
  const valid = currentTypeConfig.value.extensions.includes(ext)
  if (!valid) {
    proxy?.$message?.error('上传' + currentTypeConfig.value.label + '格式不对')
  }
  return valid
}

function getFileExt(file) {
  const name = file.name || ''
  return (name.split('.').pop() || '').toUpperCase()
}

function initData() {
  ruleForm.type = 0
  ruleForm.region = 0
  webImgUrl.value = ''
  ruleForm.imgList = []
  loading.value = false
  uploadCancelRequested.value = false
  currentUploadTotal.value = 0
  currentUploadFinished.value = 0
}

defineExpose({
  uploadModal,
  initData,
  getImg
})
</script>
<style lang="scss" scoped>
:deep(.el-dialog__title) {
  font-size: 16px;
}

:deep(.el-dialog.is-fullscreen){
  width: 50% !important;
  height: auto !important; /* 替换 unset 更通用 */
  max-width: 50% !important; /* 覆盖全屏默认的100% */
  left: 50% !important;
  top: 50% !important;
  bottom: auto !important; /* 重置全屏默认的bottom:0 */
  right: auto !important; /* 重置全屏默认的right:0 */
  transform: translate(-50%, -50%) !important;
  margin: 0 !important; /* 清除默认margin干扰 */
}
:deep(.el-dialog.is-fullscreen .el-dialog__body) {
  height: calc(100% - 100px); /* 减去头部/底部高度，根据需要调整 */
  overflow: auto; /* 内容超出时滚动 */
}

.main {
  min-height: 410px;
}

.pictrue {
  width: 60px !important;
  height: 60px !important;
  border: 1px dotted rgba(0, 0, 0, 0.1);
  margin-right: 10px;
  position: relative;
  cursor: pointer;

  img {
    width: 100%;
    height: 100%;
  }
}

.btndel {
  position: absolute;
  z-index: 1;
  font-size: 18px;
  right: -5px;
  top: -5px;
  color: #999;
}

.form-width {
  width: 280px;
}

.tq-text {
  margin-left: 14px;
  font-size: 12px;
  font-weight: 400;
  color: var(--prev-color-primary);
  cursor: pointer;
}

.uploadCont :deep(.el-upload--picture-card),
:deep(.el-upload-list--picture-card .el-upload-list__item) {
  width: 64px;
  height: 64px;
  line-height: 72px;
  overflow: inherit;
}

.uploadCont :deep(.el-upload--picture-card),
:deep(.el-upload-list--picture-card .el-upload-list__item img) {
  width: 64px !important;
  height: 64px !important;
  border-radius: 6px;
  object-fit: cover;
}

.upload-list-file {
  position: relative;
  width: 64px;
  height: 64px;
  border-radius: 6px;
}

.upload-file-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: 64px;
  height: 64px;
  color: var(--dashboard-text-secondary);
  background: var(--dashboard-surface-muted);

  i {
    font-size: 24px;
    color: var(--prev-color-primary);
  }

  span {
    max-width: 52px;
    margin-top: 4px;
    overflow: hidden;
    font-size: 11px;
    line-height: 14px;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}

.upload-status-mask {
  position: absolute;
  inset: 0;
  z-index: 2;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4px;
  border-radius: 6px;
  background: rgba(0, 0, 0, 0.58);
  color: #fff;
  font-size: 12px;
  line-height: 16px;
  text-align: center;

  .el-icon-loading,
  .el-icon-time,
  .el-icon-circle-check,
  .el-icon-circle-close {
    margin-bottom: 2px;
    font-size: 16px;
  }
}

.upload-list-file.is-success .upload-status-mask {
  background: rgba(103, 194, 58, 0.86);
}

.upload-list-file.is-error .upload-status-mask {
  background: rgba(245, 108, 108, 0.88);
}

.upload-list-file.is-pending .upload-status-mask {
  background: rgba(144, 147, 153, 0.78);
}

.pl100 {
  padding-left: 100px;
}

.img-box {
  display: flex;
  flex-wrap: wrap;
}

.tips {
  font-size: 12px;
  color: #bbbbbb;
}

.code-image {
  display: flex;
  margin-top: 12px;

  .left {
    display: flex;
    flex-direction: column;
    margin-right: 20px;
    align-items: center;

    .code {
      border: 1px solid #dddddd;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 200px;
      height: 200px;
      border-radius: 4px;

      .code-img {
        width: 160px;
        height: 160px;
      }
    }

    .form-width {
      width: 200px;
    }

    .code {
      margin-bottom: 14px;
    }

    .trip {
      color: #333333;
      text-align: center;
      line-height: 18px;
    }

    .trip-small {
      font-size: 12px;
      font-weight: 400;
      color: #bbbbbb;
      text-align: center;
      line-height: 16px;
    }
  }

  .right {
    margin-top: 62px;

    .tip {
      font-size: 12px;
      font-weight: 400;
      color: #bbbbbb;
      margin: 10px 0;
    }
  }
}
</style>
