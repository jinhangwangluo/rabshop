<template>
  <div class="live-create-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">{{ isEdit ? '编辑直播间' : '创建直播间' }}</h1>
        <p class="page-curator-subtitle">
          配置直播基础信息、封面展示与推流设置，提交后即可进入直播运营流程。
        </p>
      </div>
      <el-button class="btn-curator-reset" :icon="ArrowLeft" @click="handleCancel">返回列表</el-button>
    </header>

    <el-card shadow="never" class="live-form-surface">

      <el-form
        ref="formRef"
        :model="form"
        :rules="rules"
        label-width="110px"
        class="create-form"
      >
        <div class="form-section-title">基础信息</div>
        <el-form-item label="直播标题" prop="title">
          <el-input v-model="form.title" placeholder="请输入直播标题" class="form-width" />
        </el-form-item>
        <el-form-item label="关联主播" prop="live_anchor_id">
          <el-select
            v-model="form.live_anchor_id"
            placeholder="请选择主播"
            filterable
            class="form-width"
          >
            <el-option
              v-for="item in anchorOptions"
              :key="item.id"
              :label="item.name"
              :value="item.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="直播分类">
          <el-select
            v-model="form.category_id"
            placeholder="请选择直播分类"
            clearable
            class="form-width"
          >
            <el-option
              v-for="item in categoryOptions"
              :key="item.id"
              :label="item.name"
              :value="item.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="直播封面" prop="cover">
          <Upload v-model="form.cover" :limit="1" />
          <div class="tips-text">建议尺寸 16:9，点击选择/更换封面图片</div>
        </el-form-item>
        <el-form-item label="开播时间" prop="live_time_range">
          <el-time-picker
            v-model="form.live_time_range"
            is-range
            format="HH:mm"
            value-format="HH:mm:ss"
            range-separator="-"
            start-placeholder="开始时间"
            end-placeholder="结束时间"
            class="form-width"
          />
        </el-form-item>

        <div class="form-section-title">介绍信息</div>
        <el-form-item label="直播说明">
          <el-input
            v-model="form.notice"
            type="textarea"
            :rows="3"
            placeholder="请输入直播说明，将展示在直播间公告位"
            class="form-width"
          />
        </el-form-item>
        <el-form-item label="欢迎语">
          <el-input
            v-model="form.welcome_msg"
            placeholder="请输入用户进场时的欢迎语"
            class="form-width"
          />
        </el-form-item>
        <el-form-item label="直播标签">
          <div class="tag-wrap">
            <el-tag
              v-for="tag in getTagList()"
              :key="tag"
              closable
              class="mr8"
              @close="handleTagClose(tag)"
            >{{ tag }}</el-tag>
            <el-input
              v-if="tagInputVisible"
              ref="tagInputRef"
              v-model="tagInputValue"

              class="tag-input"
              @keyup.enter="handleTagInputConfirm"
              @blur="handleTagInputConfirm"
            />
            <el-button
              v-else
              :icon="Plus"
              @click="showTagInput"
            >添加标签</el-button>
          </div>
          <div class="tips-text">回车或点击其他区域确认添加，最多支持5个标签</div>
        </el-form-item>
        <el-form-item label="直播简介">
          <el-input
            v-model="form.description"
            type="textarea"
            :rows="3"
            placeholder="请输入直播简介（一句话介绍）"
            class="form-width"
          />
        </el-form-item>
        <el-form-item label="详细简介">
          <el-input
            v-model="form.intro"
            type="textarea"
            :rows="5"
            placeholder="请输入直播详细简介（支持换行）"
            class="form-width"
          />
        </el-form-item>
        <el-form-item label="关键词">
          <el-input
            v-model="form.keywords"
            placeholder="多个关键词用逗号分隔，用于SEO"
            class="form-width"
          />
        </el-form-item>

        <div class="form-section-title">媒体资源</div>
        <el-form-item label="直播Banner">
          <Upload v-model="form.banner" :limit="1" />
          <div class="tips-text">直播间顶部Banner图，建议尺寸 750×300</div>
        </el-form-item>
        <el-form-item label="直播海报">
          <Upload v-model="form.poster" :limit="1" />
          <div class="tips-text">用于分享推广的直播海报图，建议尺寸 540×960</div>
        </el-form-item>

        <div class="form-section-title">直播设置</div>
        <el-form-item label="直播类型" prop="stream_type">
          <el-radio-group v-model="form.stream_type" @change="handleStreamTypeChange">
            <el-radio-button label="obs">OBS直播</el-radio-button>
            <el-radio-button label="external">外链直播</el-radio-button>
          </el-radio-group>
          <span class="tips-text">外链直播开播时不会调用第三方直播服务</span>
        </el-form-item>
        <el-form-item v-if="form.stream_type === 'external'" label="直播链接" prop="external_play_url">
          <el-input
            v-model="form.external_play_url"
            placeholder="请输入 http、https 或 rtmp 直播播放链接"
            class="form-width"
          />
          <div class="tips-text tips-text--block">开播后前台将直接使用该链接播放直播。</div>
        </el-form-item>
        <el-form-item v-if="form.stream_type === 'obs'" label="录制直播">
          <el-switch
            v-model="form.is_record"
            :active-value="1"
            :inactive-value="0"
          />
          <span class="tips-text">开启后直播内容将自动录制存档</span>
        </el-form-item>
        <el-form-item v-if="form.stream_type === 'obs'" label="播流鉴权">
          <el-switch
            v-model="form.is_playback_auth"
            :active-value="1"
            :inactive-value="0"
          />
          <span class="tips-text">开启后将为播流地址追加鉴权参数，防止未授权访问</span>
        </el-form-item>
        <el-form-item label="允许横屏">
          <el-switch
            v-model="form.allow_landscape"
            :active-value="1"
            :inactive-value="0"
          />
          <span class="tips-text">开启后移动端直播间展示横屏切换按钮</span>
        </el-form-item>
        <el-form-item label="热门推荐">
          <el-switch
            v-model="form.is_hot"
            :active-value="1"
            :inactive-value="0"
          />
          <span class="tips-text">开启后将在直播列表中优先展示</span>
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.sort" :min="0" :max="9999" />
          <span class="tips-text">数值越大越靠前</span>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" class="btn-curator-primary" :loading="submitLoading" @click="handleSubmit">
            {{ isEdit ? '保存修改' : '创建直播间' }}
          </el-button>
          <el-button class="btn-curator-reset" @click="handleCancel">取 消</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 推拉流管理（仅编辑模式显示） -->
    <el-card v-if="isEdit && form.stream_type === 'obs'" shadow="never" class="live-stream-surface">
      <template #header><div class="stream-header">
        <span>推拉流管理</span>
        <div>
          <el-button
            type="primary"
            :icon="VideoPlay"
            :loading="streamLoading"
            @click="handleCreateStream(false)"
          >生成推流地址</el-button>
          <el-button
            type="warning"
            :icon="Refresh"
            :loading="streamLoading"
            @click="handleCreateStream(true)"
          >强制刷新</el-button>
          <el-button
            v-if="streamInfo.stream_name"
            type="danger"
            :icon="SwitchButton"
            :loading="closeStreamLoading"
            @click="handleCloseStream"
          >关闭推流</el-button>
        </div>
      </div></template>

      <div v-if="streamLoading" class="stream-loading">
        <LegacyIcon name="el-icon-loading" /> 正在生成推流地址...
      </div>

      <div v-else-if="streamInfo.stream_name" class="stream-info">
        <el-descriptions :column="1" border >
          <el-descriptions-item label="流名称">
            <span class="stream-name">{{ streamInfo.stream_name }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="当前云服务商">
            <el-tag
              :type="streamInfo.cloud_provider === 'aliyun' ? 'primary' : 'warning'"

            >
              {{ streamInfo.cloud_provider === 'aliyun' ? '阿里云' : '七牛云' }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="地址生成时间">{{ streamInfo.stream_created_at || '—' }}</el-descriptions-item>
        </el-descriptions>

        <!-- 阿里云地址 -->
        <div v-if="streamInfo.ali_push_base || streamInfo.ali_pull_url" class="stream-section">
          <div class="stream-section-title">
            <el-tag type="primary" size="mini">阿里云</el-tag>
          </div>
          <template v-if="streamInfo.ali_push_base">
            <div class="stream-row">
              <span class="stream-label">推流地址</span>
              <el-input :value="streamInfo.ali_push_base" readonly class="stream-input"  />
              <el-button size="mini" :icon="CopyDocument" @click="copyText(streamInfo.ali_push_base)">复制</el-button>
            </div>
            <div class="stream-row">
              <span class="stream-label">推流 Key</span>
              <el-input :value="streamInfo.ali_push_key" readonly class="stream-input"  />
              <el-button size="mini" :icon="CopyDocument" @click="copyText(streamInfo.ali_push_key)">复制</el-button>
            </div>
          </template>
          <div v-if="streamInfo.ali_pull_url" class="stream-row">
            <span class="stream-label">播流地址</span>
            <el-input :value="streamInfo.ali_pull_url" readonly class="stream-input"  />
            <el-button size="mini" :icon="CopyDocument" @click="copyText(streamInfo.ali_pull_url)">复制</el-button>
          </div>
        </div>

        <!-- 七牛云地址 -->
        <div v-if="streamInfo.qiniu_push_base || streamInfo.qiniu_pull_url" class="stream-section">
          <div class="stream-section-title">
            <el-tag type="warning" size="mini">七牛云</el-tag>
          </div>
          <template v-if="streamInfo.qiniu_push_base">
            <div class="stream-row">
              <span class="stream-label">推流地址</span>
              <el-input :value="streamInfo.qiniu_push_base" readonly class="stream-input"  />
              <el-button size="mini" :icon="CopyDocument" @click="copyText(streamInfo.qiniu_push_base)">复制</el-button>
            </div>
            <div class="stream-row">
              <span class="stream-label">推流 Key</span>
              <el-input :value="streamInfo.qiniu_push_key" readonly class="stream-input"  />
              <el-button size="mini" :icon="CopyDocument" @click="copyText(streamInfo.qiniu_push_key)">复制</el-button>
            </div>
          </template>
          <div v-if="streamInfo.qiniu_pull_url" class="stream-row">
            <span class="stream-label">播流地址</span>
            <el-input :value="streamInfo.qiniu_pull_url" readonly class="stream-input"  />
            <el-button size="mini" :icon="CopyDocument" @click="copyText(streamInfo.qiniu_pull_url)">复制</el-button>
          </div>
        </div>
      </div>

      <el-empty v-else description="暂无推流地址，点击「生成推流地址」按钮创建" :image-size="80" />
    </el-card>

    <el-card v-if="isEdit && form.stream_type === 'external'" shadow="never" class="live-stream-header">
      <template #header><div class="stream-header">
        <span>外链直播</span>
      </div></template>
      <div class="stream-info">
        <div class="stream-row">
          <span class="stream-label">直播链接</span>
          <el-input :value="form.external_play_url" readonly class="stream-input"  />
          <el-button size="mini" :icon="CopyDocument" @click="copyText(form.external_play_url)">复制</el-button>
        </div>
      </div>
    </el-card>
  </div>
</template>

<script setup>
import {
  getRoomDetail,
  createRoom,
  updateRoom,
  getAnchorOptions as getAnchorOptionsApi,
  getCategoryOptions as getCategoryOptionsApi,
  createRoomStream,
  closeRoomStream,
  getRoomStreamInfo
} from '@/api/live'
import { ElMessage, ElMessageBox } from 'element-plus'
import { ArrowLeft, CopyDocument, Plus, Refresh, SwitchButton, VideoPlay } from '@element-plus/icons-vue'
import { nextTick, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

defineOptions({
  name: 'LiveRoomCreate'
})

const route = useRoute()
const router = useRouter()

const isEdit = ref(false)
const editId = ref(0)
const submitLoading = ref(false)
const streamLoading = ref(false)
const closeStreamLoading = ref(false)
const streamInfo = ref({})
const anchorOptions = ref([])
const categoryOptions = ref([])
const tagInputVisible = ref(false)
const tagInputValue = ref('')
const form = reactive({
  live_anchor_id: '',
  category_id: '',
  title: '',
  cover: '',
  description: '',
  notice: '',
  welcome_msg: '',
  tags: '',
  banner: '',
  poster: '',
  intro: '',
  is_record: 0,
  is_playback_auth: 0,
  allow_landscape: 0,
  keywords: '',
  start_time: '',
  end_time: '',
  live_time_range: [],
  stream_type: 'obs',
  external_play_url: '',
  is_hot: 0,
  sort: 0,
  status: 0
})

const formRef = ref(null)
const tagInputRef = ref(null)

function validateExternalUrl(rule, value, callback) {
  if (form.stream_type !== 'external') {
    callback()
    return
  }
  const url = String(value || '').trim()
  if (!url) {
    callback(new Error('请输入直播链接'))
    return
  }
  if (!/^(https?:\/\/|rtmp:\/\/)/i.test(url)) {
    callback(new Error('直播链接仅支持 http、https 或 rtmp 地址'))
    return
  }
  callback()
}

function validateLiveTimeRange(rule, value, callback) {
  const [startTime, endTime] = value || []
  if (!startTime || !endTime) {
    callback(new Error('请选择开播时间段'))
    return
  }
  if (compareTime(startTime, endTime) >= 0) {
    callback(new Error('结束时间必须晚于开始时间'))
    return
  }
  callback()
}

const rules = {
  title: [{ required: true, message: '请输入直播标题', trigger: 'blur' }],
  live_anchor_id: [{ required: true, message: '请选择关联主播', trigger: 'change' }],
  live_time_range: [{ validator: validateLiveTimeRange, trigger: 'change' }],
  stream_type: [{ required: true, message: '请选择直播类型', trigger: 'change' }],
  external_play_url: [{ validator: validateExternalUrl, trigger: 'blur' }]
}

onMounted(() => {
  getAnchorOptions()
  getCategoryOptions()
  const id = route.query.id
  if (id) {
    isEdit.value = true
    editId.value = Number(id)
    loadDetail()
  }
})

function getAnchorOptions() {
  getAnchorOptionsApi().then(res => {
    anchorOptions.value = res.data || []
  })
}

function getCategoryOptions() {
  getCategoryOptionsApi().then(res => {
    categoryOptions.value = res.data || []
  })
}

function loadDetail() {
  getRoomDetail(editId.value).then(res => {
    const d = res.data
    const startTime = d.start_time || ''
    const endTime = d.end_time || ''
    Object.assign(form, {
      live_anchor_id: d.live_anchor_id,
      category_id: d.category_id || '',
      title: d.title,
      cover: d.cover || '',
      description: d.description || '',
      notice: d.notice || '',
      welcome_msg: d.welcome_msg || '',
      tags: d.tags || '',
      banner: d.banner || '',
      poster: d.poster || '',
      intro: d.intro || '',
      is_record: d.is_record || 0,
      is_playback_auth: d.is_playback_auth || 0,
      allow_landscape: d.allow_landscape || 0,
      keywords: d.keywords || '',
      start_time: startTime,
      end_time: endTime,
      live_time_range: buildLiveTimeRange(startTime, endTime),
      stream_type: d.stream_type || 'obs',
      external_play_url: d.external_play_url || '',
      is_hot: d.is_hot || 0,
      sort: d.sort || 0,
      status: d.status || 0
    })
  })
  loadStreamInfo()
}

function handleStreamTypeChange(value) {
  if (value === 'obs') {
    form.external_play_url = ''
  } else {
    form.is_record = 0
    form.is_playback_auth = 0
  }
  nextTick(() => {
    formRef.value && formRef.value.clearValidate('external_play_url')
  })
}

function loadStreamInfo() {
  getRoomStreamInfo(editId.value).then(res => {
    streamInfo.value = res.data || {}
  }).catch(() => {
    streamInfo.value = {}
  })
}

function handleCreateStream(force) {
  if (form.stream_type === 'external') {
    ElMessage.warning('外链直播无需生成推流地址')
    return
  }
  const tip = force ? '强制刷新将重新生成推流地址，原地址将失效。确认继续？' : '确认为该直播间生成双云推流地址？'
  ElMessageBox.confirm(tip, '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    streamLoading.value = true
    createRoomStream(editId.value, force).then(res => {
      ElMessage.success('推流地址生成成功')
      streamInfo.value = res.data || {}
    }).finally(() => {
      streamLoading.value = false
    })
  })
}

function handleCloseStream() {
  ElMessageBox.confirm('确认关闭该直播间的双云推流？关闭后观众将无法继续观看', '警告', {
    confirmButtonText: '确认关闭',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    closeStreamLoading.value = true
    closeRoomStream(editId.value).then(res => {
      ElMessage.success(res.data && res.data.msg ? res.data.msg : '推流关闭指令已发送')
      loadStreamInfo()
    }).finally(() => {
      closeStreamLoading.value = false
    })
  })
}

function copyText(text) {
  if (navigator.clipboard) {
    navigator.clipboard.writeText(text).then(() => {
      ElMessage.success('复制成功')
    })
  } else {
    const input = document.createElement('input')
    input.value = text
    document.body.appendChild(input)
    input.select()
    document.execCommand('copy')
    document.body.removeChild(input)
    ElMessage.success('复制成功')
  }
}

function handleSubmit() {
  formRef.value.validate(valid => {
    if (!valid) return
    submitLoading.value = true
    const data = buildSubmitData()
    const api = isEdit.value
      ? updateRoom(editId.value, data)
      : createRoom(data)
    api.then(() => {
      ElMessage.success(isEdit.value ? '修改成功' : '创建成功')
      router.push({ name: 'MerchantLiveRoomList' })
    }).finally(() => {
      submitLoading.value = false
    })
  })
}

function handleCancel() {
  router.push({ name: 'MerchantLiveRoomList' })
}

function buildSubmitData() {
  const data = Object.assign({}, form)
  const [startTime, endTime] = form.live_time_range || []

  data.start_time = startTime
  data.end_time = endTime
  delete data.live_time_range

  return data
}

function buildLiveTimeRange(startTime, endTime) {
  const start = getTimePart(startTime)
  const end = getTimePart(endTime)
  return start && end ? [start, end] : []
}

function getTimePart(value) {
  const match = String(value || '').match(/(\d{2}:\d{2})(?::(\d{2}))?$/)
  if (!match) {
    return ''
  }
  return match[1] + ':' + (match[2] || '00')
}

function compareTime(start, end) {
  const startTime = getTimePart(start)
  const endTime = getTimePart(end)
  if (!startTime || !endTime) {
    return 0
  }
  return startTime.localeCompare(endTime)
}

function getTagList() {
  return form.tags ? form.tags.split(',').filter(t => t) : []
}

function handleTagClose(tag) {
  const tags = getTagList().filter(t => t !== tag)
  form.tags = tags.join(',')
}

function showTagInput() {
  tagInputVisible.value = true
  nextTick(() => {
    tagInputRef.value && tagInputRef.value.focus()
  })
}

function handleTagInputConfirm() {
  const val = tagInputValue.value.trim()
  if (val) {
    const tags = getTagList()
    if (!tags.includes(val)) {
      tags.push(val)
      form.tags = tags.join(',')
    }
  }
  tagInputVisible.value = false
  tagInputValue.value = ''
}
</script>

<style lang="scss" scoped>
.live-create-page {
  min-height: 100%;
  box-sizing: border-box;
}

.page-curator-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 20px;
}

.page-curator-title {
  margin: 0 0 8px;
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--ds-on-surface);
  letter-spacing: 0;
}

.page-curator-subtitle {
  margin: 0;
  font-size: 0.875rem;
  color: var(--ds-on-surface-variant);
  line-height: 1.5;
  max-width: 520px;
}

.live-form-surface,
.live-stream-surface {
  border-radius: 12px;
  border-color: var(--ds-outline-ghost);
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
}

.live-stream-surface {
  margin-top: 16px;
}

.create-form {
  max-width: 700px;
  padding: 10px 0;
}

.form-width {
  width: 400px;
}

.form-section-title {
  padding: 10px 0 6px;
  margin-top: 10px;
  margin-bottom: 4px;
  font-size: 14px;
  font-weight: 600;
  color: var(--text-primary);
  border-bottom: 1px solid var(--ds-outline);

  &:first-child {
    margin-top: 0;
  }
}

:deep(.btn-curator-primary.el-button--primary) {
  border: none;
  border-radius: 6px;
  color: var(--ds-on-primary);
  font-weight: 500;
  padding-left: 18px;
  padding-right: 18px;
  box-shadow: 0 2px 8px var(--brand-shadow);
}

:deep(.btn-curator-reset) {
  border-radius: 6px;
  background: var(--ds-surface-card);
  color: var(--ds-primary);
  border: 1px solid var(--ds-primary);

  &:hover,
  &:focus {
    background: var(--brand-primary-pale);
    color: var(--ds-primary-hover);
    border-color: var(--ds-primary-hover);
  }
}

.cover-preview {
  width: 160px;
  height: 100px;
  border-radius: 4px;
  margin-top: 8px;
}

.preview-wrap {
  margin-top: 6px;
}

.tips-text {
  margin-left: 10px;
  color: var(--text-secondary);
  font-size: 12px;
}

.tips-text--block {
  margin-left: 0;
  margin-top: 6px;
}

.tag-wrap {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}

.tag-input {
  width: 120px;
}

.mr8 {
  margin-right: 0;
}

.stream-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.stream-loading {
  padding: 20px;
  text-align: center;
  color: var(--text-secondary);
  font-size: 14px;
}

.stream-info {
  padding: 10px 0;
}

.stream-name {
  font-family: monospace;
  color: var(--ds-primary);
  font-weight: 600;
}

.stream-section {
  margin-top: 16px;
}

.stream-section-title {
  margin-bottom: 10px;
}

.stream-row {
  display: flex;
  align-items: center;
  margin-bottom: 8px;
  gap: 8px;
}

.stream-label {
  min-width: 64px;
  font-size: 13px;
  color: var(--text-secondary);
  flex-shrink: 0;
}

.stream-input {
  flex: 1;
}
</style>
