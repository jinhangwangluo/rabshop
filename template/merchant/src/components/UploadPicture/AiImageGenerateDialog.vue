<template>
  <el-dialog
    v-model="visibleValue"
    width="1000px"
    top="6vh"
    append-to-body
    custom-class="ai-image-generate-dialog"
    :close-on-click-modal="false"
    @closed="handleClosed"
  >
    <template #header>
      <div class="ai-dialog__title">
        <div class="ai-dialog__title-main">
          <span class="ai-dialog__spark">AI</span>
          <div>
            <div class="ai-dialog__headline">生成AI图片（异步队列）</div>
            <div class="ai-dialog__subtitle">任务将进入异步队列，稍后可在任务列表中查看结果。</div>
          </div>
        </div>
        <el-button
          class="ai-dialog__pin-btn"
          type="text"
          :icon="isPinned ? Close : Top"
          @click.stop="togglePinned"
        >{{ isPinned ? '取消固定' : '固定到顶部' }}</el-button>
      </div>
    </template>

    <div class="ai-dialog">
      <div class="ai-dialog__form">
        <el-form ref="formRef" :model="form" label-position="top" >
          <el-form-item label="图片主题">
            <el-input
              v-model="form.title"
              maxlength="50"
              show-word-limit
              placeholder="请输入图片主题，例如：未来城市"
            />
          </el-form-item>

          <el-form-item label="提示词">
            <el-input
              v-model="form.prompt"
              type="textarea"
              :rows="4"
              maxlength="1000"
              show-word-limit
              placeholder="请输入详细的提示词，描述画面内容、细节、光线、氛围等..."
            />
          </el-form-item>

          <div class="ai-dialog__grid ai-dialog__grid--three">
            <el-form-item label="风格选择">
              <el-select v-model="form.style" placeholder="请选择风格">
                <el-option label="写实风格" value="写实风格" />
                <el-option label="插画风格" value="插画风格" />
                <el-option label="电商海报" value="电商海报" />
                <el-option label="极简高级" value="极简高级" />
                <el-option label="国潮风格" value="国潮风格" />
              </el-select>
            </el-form-item>
            <el-form-item label="尺寸选择">
              <el-select v-model="form.size" placeholder="请选择尺寸" @change="handleSizeChange">
                <el-option label="1024 x 1024（1:1）" value="1024x1024" />
                <el-option label="1024 x 1536（2:3）" value="1024x1536" />
                <el-option label="1536 x 1024（3:2）" value="1536x1024" />
                <el-option label="自动" value="auto" />
              </el-select>
            </el-form-item>
            <el-form-item label="比例选择">
              <el-select v-model="form.ratio" placeholder="请选择比例">
                <el-option label="1:1" value="1:1" />
                <el-option label="2:3" value="2:3" />
                <el-option label="3:2" value="3:2" />
                <el-option label="自动" value="auto" />
              </el-select>
            </el-form-item>
          </div>

          <div class="ai-dialog__grid ai-dialog__grid--three">
            <el-form-item label="生成数量">
              <el-input-number v-model="form.count" :min="1" :max="4" controls-position="right" />
              <div class="ai-dialog__hint">最多支持一次生成 4 张</div>
            </el-form-item>
            <el-form-item label="随机种子">
              <el-switch
                v-model="form.random_seed"
                :active-value="1"
                :inactive-value="0"
                active-text="随机"
                inactive-text="固定"
              />
            </el-form-item>
            <el-form-item label="高清模式">
              <el-switch
                v-model="form.high_quality"
                :active-value="1"
                :inactive-value="0"
                active-text="开启"
                inactive-text="关闭"
              />
            </el-form-item>
          </div>

          <div v-if="form.random_seed === 0" class="ai-dialog__seed">
            <el-form-item label="固定种子">
              <el-input v-model="form.seed" placeholder="请输入固定种子，便于复现近似效果" />
            </el-form-item>
          </div>

          <div class="ai-dialog__grid ai-dialog__grid--three">
            <el-form-item label="质量">
              <el-select v-model="form.quality" placeholder="请选择质量">
                <el-option label="自动" value="auto" />
                <el-option label="高质量" value="high" />
                <el-option label="中质量" value="medium" />
                <el-option label="低质量" value="low" />
              </el-select>
            </el-form-item>
            <el-form-item label="格式">
              <el-select v-model="form.output_format" placeholder="请选择格式">
                <el-option label="PNG" value="png" />
                <el-option label="JPEG" value="jpeg" />
                <el-option label="WEBP" value="webp" />
              </el-select>
            </el-form-item>
            <el-form-item label="压缩率">
              <el-input-number
                v-model="form.output_compression"
                :min="0"
                :max="100"
                controls-position="right"
                :disabled="form.output_format === 'png'"
              />
            </el-form-item>
          </div>

          <el-form-item label="负面提示词（可选）">
            <el-input
              v-model="form.negative_prompt"
              maxlength="500"
              show-word-limit
              placeholder="请输入不希望在图片中出现的内容，例如：模糊、文字、水印等..."
            />
          </el-form-item>

          <el-form-item label="参考图（可选）">
            <div v-if="referencePreview" class="ai-dialog__reference">
              <img :src="referencePreview" alt="参考图">
              <div class="ai-dialog__reference-info">
                <div class="line1">{{ referenceName }}</div>
                <span>{{ referenceSourceText }}</span>
              </div>
              <div class="ai-dialog__reference-actions">
                <el-upload
                  action=""
                  :show-file-list="false"
                  :http-request="uploadReference"
                  :before-upload="beforeReferenceUpload"
                  :disabled="referenceUploadLoading"
                >
                  <el-button type="text" :loading="referenceUploadLoading">重新上传</el-button>
                </el-upload>
                <el-button type="text" @click="clearReference">清除</el-button>
              </div>
            </div>
            <div v-else class="ai-dialog__reference-empty">
              <el-upload
                class="ai-dialog__reference-upload"
                drag
                action=""
                :show-file-list="false"
                :http-request="uploadReference"
                :before-upload="beforeReferenceUpload"
                :disabled="referenceUploadLoading"
              >
                <LegacyIcon name="el-icon-picture-outline" />
                <div class="el-upload__text">
                  点击或拖拽图片到此处上传
                </div>
                <template #tip>
                  <div class="ai-dialog__upload-tip">
                    也可以先在图片库中选中 1 张图片，再点击 AI生成带入参考图
                  </div>
                </template>
              </el-upload>
            </div>
          </el-form-item>
        </el-form>
      </div>

      <div class="ai-dialog__side">
        <section class="ai-panel">
          <div class="ai-panel__header">
            <span>生成预览</span>
            <el-tooltip content="队列生成完成后会自动刷新预览" placement="top">
              <LegacyIcon name="el-icon-question" />
            </el-tooltip>
          </div>
          <div class="ai-progress">
            <div>
              <LegacyIcon name="el-icon-time" />
              <span>{{ progressText }}</span>
            </div>
            <el-progress :percentage="currentProgress" :stroke-width="6" :show-text="false" />
          </div>
          <div class="ai-preview">
            <div
              v-for="card in previewCards"
              :key="card.key"
              class="ai-preview__card"
              :class="{ 'is-image': card.path }"
              @click="card.path ? viewImage(card.path) : null"
            >
              <img v-if="card.path" :src="card.path" alt="生成图片">
              <template v-else>
                <LegacyIcon name="el-icon-picture-outline" />
                <span>{{ card.text }}</span>
              </template>
            </div>
          </div>
        </section>

        <section class="ai-panel ai-panel--tasks">
          <div class="ai-panel__header">
            <span>生成队列 / 任务列表（{{ total }}）</span>
            <el-button :icon="Refresh" @click="fetchTasks(false)">刷新</el-button>
          </div>
          <el-table
            v-loading="taskLoading"
            :data="tasks"

            row-key="id"
            height="285"
          >
            <el-table-column label="主题" min-width="130">
              <template #default="{ row }">
                <span class="line1">{{ row.title || row.prompt }}</span>
              </template>
            </el-table-column>
            <el-table-column label="进度" width="58" align="center">
              <template #default="{ row }">
                <span class="ai-task-progress">{{ getTaskProgress(row) }}%</span>
              </template>
            </el-table-column>
            <el-table-column label="状态" width="72" align="center">
              <template #default="{ row }">
                <el-tooltip
                  v-if="getTaskFailureReason(row)"
                  :content="getTaskFailureReason(row)"
                  placement="top"
                  :open-delay="200"
                  popper-class="ai-task-error-tooltip"
                >
                  <el-tag class="ai-task-status"  :type="row.status_type">{{ row.status_text }}</el-tag>
                </el-tooltip>
                <el-tag v-else class="ai-task-status"  :type="row.status_type">{{ row.status_text }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="66" align="center">
              <template #default="{ row }">
                <span class="ai-task-actions">
                  <a
                    v-if="row.status === 0 || row.status === 1"
                    href="javascript:;"
                    class="ai-task-action"
                    @click="cancelTask(row)"
                  >取消</a>
                  <a
                    v-if="row.status === 2"
                    href="javascript:;"
                    class="ai-task-action"
                    @click="viewFirstResult(row)"
                  >查看</a>
                  <a
                    v-if="row.status === 2"
                    href="javascript:;"
                    class="ai-task-action"
                    @click="downloadFirstResult(row)"
                  >下载</a>
                  <a
                    v-if="row.status === 3 || row.status === 4"
                    href="javascript:;"
                    class="ai-task-action"
                    @click="retryTask(row)"
                  >重试</a>
                </span>
              </template>
            </el-table-column>
          </el-table>
        </section>
      </div>
    </div>

    <template #footer>
      <div class="ai-dialog__footer">
        <span>任务将进入异步队列</span>
        <div>
          <el-button @click="visibleValue = false">取消</el-button>
          <el-button :icon="Refresh" @click="resetForm">重置</el-button>
          <el-button
            type="primary"
            :icon="MagicStick"
            :loading="submitLoading"
            @click="submitTask"
          >提交到队列</el-button>
        </div>
      </div>
    </template>
  </el-dialog>
</template>

<script setup>
import {
  cancelFileAiGenerateTaskApi,
  createFileAiGenerateTaskApi,
  fileUploadApi,
  getFileAiGenerateTaskListApi,
  retryFileAiGenerateTaskApi
} from '@/api/file'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Close, MagicStick, Refresh, Top } from '@element-plus/icons-vue'
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { imageAmplify } from '@/utils/index'
import { useAiImageGenerateStore } from '@/store/modules/aiImageGenerate'

defineOptions({
  name: 'AiImageGenerateDialog'
})

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  referenceFile: {
    type: Object,
    default: null
  },
  categoryId: {
    type: [String, Number],
    default: 0
  }
})

const emit = defineEmits(['update:visible', 'generated'])

const aiImageGenerateStore = useAiImageGenerateStore()
const { pinned: isPinned } = storeToRefs(aiImageGenerateStore)

function getDefaultForm() {
  return {
    title: '',
    prompt: '',
    negative_prompt: '',
    style: '写实风格',
    size: 'auto',
    ratio: '1:1',
    count: 1,
    quality: 'auto',
    output_format: 'png',
    output_compression: 100,
    seed: '',
    random_seed: 1,
    high_quality: 0,
    reference_file_id: 0
  }
}

const form = reactive(getDefaultForm())
const formRef = ref(null)
const tasks = ref([])
const total = ref(0)
const taskLoading = ref(false)
const submitLoading = ref(false)
const referenceUploadLoading = ref(false)
const timer = ref(null)
const completedTaskMap = reactive({})
const uploadedReferenceFile = ref(null)
const referenceSource = ref('')
const latestResults = ref([])

const visibleValue = computed({
  get: () => props.visible,
  set: value => emit('update:visible', value)
})

const activeReferenceFile = computed(() => {
  if (
    uploadedReferenceFile.value &&
    form.reference_file_id &&
    Number(form.reference_file_id) === Number(uploadedReferenceFile.value.id)
  ) {
    return uploadedReferenceFile.value
  }

  if (
    props.referenceFile &&
    form.reference_file_id &&
    Number(form.reference_file_id) === Number(props.referenceFile.id)
  ) {
    return props.referenceFile
  }

  return null
})

const referencePreview = computed(() => {
  const file = activeReferenceFile.value
  if (!file) {
    return ''
  }
  return file.path || file.att_dir || file.filePath || ''
})

const referenceName = computed(() => {
  const file = activeReferenceFile.value
  if (!file) {
    return ''
  }
  return file.real_name || file.realName || file.name || file.fileName || '参考图'
})

const referenceSourceText = computed(() => {
  return referenceSource.value === 'upload' ? '将使用上传图片作为参考图' : '将使用图片库选中图片作为参考图'
})

const activeTask = computed(() => {
  for (let i = 0; i < tasks.value.length; i++) {
    const task = tasks.value[i]
    if (task.status === 0 || task.status === 1) {
      return task
    }
  }
  return null
})

const currentProgress = computed(() => {
  return activeTask.value ? activeTask.value.progress || 0 : 0
})

const progressText = computed(() => {
  if (!activeTask.value) {
    return latestResults.value.length ? '最近一次任务已完成' : '当前暂无进行中的任务'
  }
  return activeTask.value.status_text + ' ' + (activeTask.value.progress || 0) + '%'
})

const previewCards = computed(() => {
  const cards = []
  if (latestResults.value.length) {
    for (let i = 0; i < latestResults.value.length; i++) {
      const item = latestResults.value[i]
      cards.push({
        key: 'image-' + item.file_id + '-' + i,
        path: item.path,
        text: item.name || '生成图片'
      })
    }
    return cards
  }

  const count = Number(form.count) || 4
  for (let i = 0; i < count; i++) {
    cards.push({
      key: 'empty-' + i,
      path: '',
      text: activeTask.value ? '排队中...' : '等待生成'
    })
  }
  return cards
})

watch(() => props.visible, value => {
  if (value) {
    handleOpen()
  } else {
    stopPolling()
  }
})

watch(() => props.referenceFile, () => {
  applyReferenceFile()
}, { deep: true })

function handleOpen() {
  applyReferenceFile()
  fetchTasks(true)
  startPolling()
}

function handleClosed() {
  stopPolling()
}

function togglePinned() {
  const nextPinned = !isPinned.value
  const title = nextPinned ? '固定到顶部' : '取消固定'
  const message = nextPinned
    ? '确定将 AI 图片生成固定到顶部吗？固定后可在顶部消息旁快速打开。'
    : '确定取消顶部固定入口吗？'

  ElMessageBox.confirm(message, title, {
    type: 'warning',
    confirmButtonText: '确定',
    cancelButtonText: '取消'
  }).then(() => {
    aiImageGenerateStore.setPinned(nextPinned)
    ElMessage.success(nextPinned ? '已固定到顶部' : '已取消固定')
  }).catch(() => {})
}

function applyReferenceFile() {
  if (referenceSource.value === 'upload' && uploadedReferenceFile.value) {
    return
  }

  if (props.referenceFile && props.referenceFile.id) {
    form.reference_file_id = props.referenceFile.id
    referenceSource.value = 'library'
  }
}

function clearReference() {
  form.reference_file_id = 0
  uploadedReferenceFile.value = null
  referenceSource.value = ''
}

function beforeReferenceUpload(file) {
  const isImage = file.type && file.type.indexOf('image/') === 0
  const isLt10M = file.size / 1024 / 1024 <= 10
  if (!isImage) {
    ElMessage.warning('请上传图片文件')
    return false
  }
  if (!isLt10M) {
    ElMessage.warning('参考图大小不能超过 10MB')
    return false
  }
  return true
}

async function uploadReference(options) {
  const file = options.file
  if (!file) {
    return
  }

  referenceUploadLoading.value = true
  try {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('cate_id', props.categoryId || 0)
    const res = await fileUploadApi(formData)
    const uploaded = normalizeUploadedReference(res.data || {})
    if (!uploaded.id) {
      ElMessage.error('参考图上传失败：接口未返回文件ID')
      return
    }

    uploadedReferenceFile.value = uploaded
    form.reference_file_id = uploaded.id
    referenceSource.value = 'upload'
    ElMessage.success('参考图上传成功')
    if (options.onSuccess) {
      options.onSuccess(res, file)
    }
  } catch (error) {
    if (options.onError) {
      options.onError(error)
    }
    throw error
  } finally {
    referenceUploadLoading.value = false
  }
}

function normalizeUploadedReference(data) {
  const id = Number(data.id || data.file_id || 0)
  return {
    id: id,
    path: data.path || data.filePath || '',
    att_dir: data.path || data.filePath || '',
    real_name: data.real_name || data.realName || data.fileName || data.name || '参考图',
    name: data.name || data.fileName || data.real_name || data.realName || '参考图'
  }
}

function handleSizeChange(value) {
  const ratioMap = {
    '1024x1024': '1:1',
    '1024x1536': '2:3',
    '1536x1024': '3:2',
    auto: 'auto'
  }
  form.ratio = ratioMap[value] || '1:1'
}

function getTaskProgress(row) {
  const progress = Number(row && row.progress) || 0
  return Math.max(0, Math.min(100, progress))
}

function getTaskFailureReason(row) {
  if (!row || row.status !== 3) {
    return ''
  }
  return row.fail_reason || row.error_message || row.error_msg || ''
}

async function fetchTasks(silent) {
  taskLoading.value = true
  try {
    const res = await getFileAiGenerateTaskListApi({ page: 1, limit: 8 })
    const data = res.data || {}
    tasks.value = data.list || []
    total.value = data.total || 0
    syncLatestResults()
    handleCompletedTasks(Boolean(silent))
  } finally {
    taskLoading.value = false
  }
}

function syncLatestResults() {
  for (let i = 0; i < tasks.value.length; i++) {
    const task = tasks.value[i]
    if (task.status === 2 && task.result_images && task.result_images.length) {
      latestResults.value = task.result_images
      return
    }
  }
}

function handleCompletedTasks(silent) {
  for (let i = 0; i < tasks.value.length; i++) {
    const task = tasks.value[i]
    if (task.status !== 2) {
      continue
    }
    if (completedTaskMap[task.id]) {
      continue
    }
    completedTaskMap[task.id] = true
    if (!silent) {
      ElMessage.success('AI 图片生成完成')
      emit('generated', task)
    }
  }
}

function startPolling() {
  stopPolling()
  timer.value = setInterval(() => {
    if (props.visible) {
      fetchTasks(false)
    }
  }, 60000)
}

function stopPolling() {
  if (timer.value) {
    clearInterval(timer.value)
    timer.value = null
  }
}

async function submitTask() {
  if (!form.prompt || !form.prompt.trim()) {
    ElMessage.warning('请先填写提示词')
    return
  }
  submitLoading.value = true
  try {
    await createFileAiGenerateTaskApi(Object.assign({}, form))
    ElMessage.success('任务已提交到队列')
    await fetchTasks(false)
    startPolling()
  } finally {
    submitLoading.value = false
  }
}

function resetForm() {
  Object.assign(form, getDefaultForm())
  uploadedReferenceFile.value = null
  referenceSource.value = ''
  applyReferenceFile()
  latestResults.value = []
}

async function cancelTask(row) {
  await cancelFileAiGenerateTaskApi(row.id)
  ElMessage.success('已取消任务')
  fetchTasks(false)
}

async function retryTask(row) {
  await retryFileAiGenerateTaskApi(row.id)
  ElMessage.success('已重新加入队列')
  fetchTasks(false)
  startPolling()
}

function viewFirstResult(row) {
  const image = getFirstResult(row)
  if (image && image.path) {
    viewImage(image.path)
  }
}

function downloadFirstResult(row) {
  const image = getFirstResult(row)
  if (!image || !image.path) {
    return
  }
  const link = document.createElement('a')
  link.href = image.path
  link.download = image.name || 'ai-image'
  link.target = '_blank'
  link.click()
}

function getFirstResult(row) {
  if (!row.result_images || !row.result_images.length) {
    return null
  }
  return row.result_images[0]
}

function viewImage(path) {
  imageAmplify(path)
}

onBeforeUnmount(() => {
  stopPolling()
})
</script>

<style lang="scss">
.ai-task-error-tooltip {
  max-width: 360px;
  line-height: 18px;
  white-space: normal;
  word-break: break-all;
}
</style>

<style lang="scss" scoped>
:deep(.ai-image-generate-dialog) {
  border-radius: 12px;
  overflow: hidden;

  .el-dialog__header {
    padding: 24px 24px 12px;
  }

  .el-dialog__body {
    padding: 0 24px 0;
  }

  .el-dialog__footer {
    padding: 16px 24px 24px;
  }
}

.ai-dialog__title {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding-right: 34px;
}

.ai-dialog__title-main {
  display: flex;
  align-items: flex-start;
  min-width: 0;
  gap: 12px;
}

.ai-dialog__pin-btn {
  flex-shrink: 0;
  margin-top: 3px;
  font-weight: 500;
  color: var(--text-link);

  &:hover,
  &:focus {
    color: var(--text-link-hover);
  }
}

.ai-dialog__spark {
  width: 32px;
  height: 32px;
  line-height: 32px;
  text-align: center;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
  color: var(--text-inverse);
  background: var(--brand-primary);
  box-shadow: 0 6px 14px var(--brand-shadow);
}

.ai-dialog__headline {
  font-size: 20px;
  line-height: 28px;
  font-weight: 600;
  color: var(--text-title);
}

.ai-dialog__subtitle {
  margin-top: 4px;
  font-size: 13px;
  color: var(--text-secondary);
}

.ai-dialog {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  max-height: 660px;
}

.ai-dialog__form,
.ai-dialog__side {
  min-width: 0;
}

.ai-dialog__form {
  padding-right: 4px;
  overflow-y: auto;
}

.ai-dialog__grid {
  display: grid;
  gap: 12px;
}

.ai-dialog__grid--three {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.ai-dialog__hint {
  margin-top: 4px;
  font-size: 12px;
  color: var(--text-secondary);
}

.ai-dialog__seed {
  margin-top: -8px;
}

.ai-dialog__reference,
.ai-dialog__reference-empty {
  display: flex;
  align-items: center;
  min-height: 72px;
  padding: 12px;
  border: 1px dashed var(--brand-primary-light);
  border-radius: 8px;
  background: var(--brand-primary-pale);
}

.ai-dialog__reference {
  gap: 12px;

  img {
    width: 56px;
    height: 56px;
    object-fit: cover;
    border-radius: 8px;
  }
}

.ai-dialog__reference-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

.ai-dialog__reference-info {
  flex: 1;
  min-width: 0;

  div {
    font-size: 13px;
    color: #374151;
  }

  span {
    font-size: 12px;
    color: var(--text-secondary);
  }
}

.ai-dialog__reference-empty {
  justify-content: center;
  padding: 0;
  overflow: hidden;
}

.ai-dialog__reference-upload {
  width: 100%;

  :deep(.el-upload) {
    display: block;
    width: 100%;
  }

  :deep(.el-upload-dragger) {
    width: 100%;
    height: 118px;
    border: 0;
    background: transparent;
  }

  i {
    margin-top: 22px;
    margin-bottom: 6px;
    font-size: 28px;
    color: var(--brand-primary);
  }

  :deep(.el-upload__text) {
    font-size: 12px;
    color: var(--text-secondary);
  }
}

.ai-dialog__upload-tip {
  margin-top: -32px;
  font-size: 12px;
  color: var(--text-secondary);
}

.ai-panel {
  padding: 14px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #ffffff;
}

.ai-panel + .ai-panel {
  margin-top: 12px;
}

.ai-panel__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
  font-size: 14px;
  font-weight: 500;
  color: #374151;
}

.ai-progress {
  display: grid;
  grid-template-columns: 150px 1fr;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  margin-bottom: 12px;
  border-radius: 8px;
  color: var(--text-primary);
  background: var(--brand-primary-pale);

  div {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
  }
}

.ai-preview {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 8px;
}

.ai-preview__card {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  height: 134px;
  border: 1px solid var(--brand-primary-light);
  border-radius: 8px;
  color: var(--text-secondary);
  background: #ffffff;
  overflow: hidden;

  i {
    margin-bottom: 8px;
    font-size: 32px;
    color: var(--brand-primary);
  }

  span {
    font-size: 12px;
  }

  img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
}

.ai-preview__card.is-image {
  cursor: zoom-in;
}

.ai-panel--tasks {
  :deep(.el-table th > .cell),
  :deep(.el-table td > .cell) {
    padding-right: 6px;
    padding-left: 6px;
  }

  :deep(.el-table__body-wrapper::-webkit-scrollbar) {
    width: 6px;
  }

  :deep(.el-table__body-wrapper::-webkit-scrollbar-thumb) {
    border-radius: 8px;
    background: #d7dceb;
  }
}

.ai-task-progress {
  display: inline-block;
  min-width: 34px;
  font-size: 12px;
  line-height: 20px;
  color: var(--text-primary);
  text-align: center;
}

.ai-task-status {
  cursor: default;
}

.ai-task-status.el-tag--danger {
  cursor: help;
}

.ai-task-actions {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  white-space: nowrap;
}

.ai-task-action {
  display: inline-block;
  font-size: 12px;
  line-height: 20px;
  color: var(--text-link);
  cursor: pointer;

  &:hover {
    color: var(--text-link-hover);
    text-decoration: none;
  }

}

.ai-dialog__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;

  > span {
    font-size: 12px;
    color: var(--text-secondary);
  }
}

:deep(.el-form-item__label) {
  padding-bottom: 6px;
  line-height: 20px;
  color: var(--text-primary);
}

:deep(.el-select),
:deep(.el-input-number) {
  width: 100%;
}
</style>
