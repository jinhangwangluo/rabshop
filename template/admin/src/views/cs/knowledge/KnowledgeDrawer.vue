<template>
  <el-drawer
    v-model="visible"
    :title="drawerTitle"
    :size="drawerWidth"
    :before-close="handleClose"
    :wrapper-closable="false"
    direction="rtl"
  >
    <!-- 步骤条 -->
    <div class="drawer-body">
      <el-steps :active="step" finish-status="success" class="drawer-steps">
        <el-step title="基础信息" />
        <el-step title="内容分段" />
        <el-step title="QA预设" />
      </el-steps>

      <!-- 步骤1：基础信息 -->
      <div v-if="step === 0" class="step-content">
        <el-form ref="basicFormRef" :model="basicForm" :rules="basicRules" label-width="90px">
          <el-form-item label="标题" prop="title">
            <el-input v-model="basicForm.title" maxlength="120" show-word-limit />
          </el-form-item>
          <el-form-item label="来源">
            <el-radio-group v-model="basicForm.source">
              <el-radio label="manual">手动</el-radio>
              <el-radio label="url">URL采集</el-radio>
              <el-radio label="file">文件导入</el-radio>
            </el-radio-group>
          </el-form-item>
          <el-form-item label="状态">
            <el-radio-group v-model="basicForm.status">
              <el-radio :label="1">启用</el-radio>
              <el-radio :label="0">停用</el-radio>
            </el-radio-group>
          </el-form-item>
        </el-form>
      </div>

      <!-- 步骤2：内容分段 -->
      <div v-if="step === 1" class="step-content">
        <!-- 录入区：根据来源切换 -->
        <div class="input-area">
          <!-- 手动输入 -->
          <template v-if="basicForm.source === 'manual'">
            <el-input
              v-model="rawText"
              type="textarea"
              :rows="6"
              placeholder="请输入文本内容，系统将自动按段落拆分"
            />
            <el-button
              type="primary"
              :loading="parsing"
              style="margin-top:10px"
              @click="parseContent"
            >解析分段</el-button>
          </template>

          <!-- URL -->
          <template v-else-if="basicForm.source === 'url'">
            <el-input v-model="rawUrl" placeholder="https://example.com/..." style="width:360px" />
            <el-button
              type="primary"
              :loading="parsing"
              style="margin-left:10px"
              @click="parseContent"
            >抓取并分段</el-button>
          </template>

          <!-- 文件 -->
          <template v-else>
            <el-upload
              ref="uploaderRef"
              action="#"
              :before-upload="handleFileBeforeUpload"
              :show-file-list="false"
              accept=".txt,.md,.docx,.pdf,.xlsx,.xls,.csv"
            >
              <el-button :loading="parsing">选择文件并解析</el-button>
              <span class="file-support-tip">支持 txt/md/docx/pdf/xlsx/csv</span>
            </el-upload>
          </template>
        </div>

        <!-- 段落列表 -->
        <div v-if="segments.length > 0" class="segment-list">
          <div class="segment-list-header">
            <span>共 {{ segments.length }} 个段落</span>
            <el-button type="text" icon="el-icon-plus" @click="addSegment">新增段落</el-button>
          </div>

          <div
            v-for="(seg, idx) in segments"
            :key="idx"
            class="segment-card"
          >
            <div class="seg-header">
              <span class="seg-index">第 {{ idx + 1 }} 段</span>
              <div class="seg-ops">
                <el-button v-if="idx > 0" type="text" icon="el-icon-top" title="上移" @click="moveSegment(idx, -1)" />
                <el-button v-if="idx < segments.length - 1" type="text" icon="el-icon-bottom" title="下移" @click="moveSegment(idx, 1)" />
                <el-button v-if="idx < segments.length - 1" type="text" size="mini" title="与下段合并" @click="mergeSegment(idx)">合并↓</el-button>
                <el-button type="text" class="danger-text" icon="el-icon-delete" title="删除" @click="removeSegment(idx)" />
              </div>
            </div>
            <el-input
              v-model="seg.content"
              type="textarea"
              :rows="3"
              :autosize="{ minRows: 2, maxRows: 8 }"
            />
          </div>
        </div>

        <el-empty v-else description="暂无段落，请先录入内容并解析" :image-size="60" />
      </div>

      <!-- 步骤3：QA预设 -->
      <div v-if="step === 2" class="step-content">
        <div v-if="segments.length === 0">
          <el-empty description="无段落，无法配置QA" :image-size="60" />
        </div>

        <el-collapse v-else accordion>
          <el-collapse-item
            v-for="(seg, sIdx) in segments"
            :key="sIdx"
            :name="String(sIdx)"
          >
            <template #title>
              <span class="qa-collapse-title">第 {{ sIdx + 1 }} 段</span>
              <span class="qa-count">{{ (seg.qaList || []).length }} 条QA</span>
            </template>

            <div class="qa-block">
              <div class="seg-preview">{{ seg.content }}</div>

              <div v-for="(qa, qIdx) in (seg.qaList || [])" :key="qIdx" class="qa-item">
                <div class="qa-item__header">
                  <span class="qa-item__title">QA {{ qIdx + 1 }}</span>
                  <el-button
                    type="text"
                    class="qa-remove-btn"
                    icon="el-icon-delete"
                    @click="removeQa(sIdx, qIdx)"
                  >删除</el-button>
                </div>
                <el-input v-model="qa.question" placeholder="问题" class="qa-question" />
                <el-input
                  v-model="qa.answer"
                  placeholder="答案"
                  type="textarea"
                  :rows="2"
                  class="qa-answer"
                />
              </div>

              <el-button type="text" icon="el-icon-plus" @click="addQa(sIdx)">添加QA</el-button>
            </div>
          </el-collapse-item>
        </el-collapse>
      </div>

      <!-- 底部操作栏 -->
      <div class="drawer-footer">
        <el-checkbox v-if="step === 2" v-model="autoVectorize">保存后自动向量化</el-checkbox>
        <div class="footer-btns">
          <el-button @click="handleClose">取消</el-button>
          <el-button v-if="step > 0" @click="step--">上一步</el-button>
          <el-button v-if="step < 2" type="primary" @click="nextStep">下一步</el-button>
          <el-button v-if="step === 2" type="primary" :loading="saving" @click="handleSave">保存</el-button>
        </div>
      </div>
    </div>
  </el-drawer>
</template>

<script setup>
import { computed, getCurrentInstance, reactive, ref } from 'vue'
import {
  createCsKnowledge,
  updateCsKnowledge,
  parseKnowledgeContent,
  batchSaveKnowledgeSegments,
  batchSaveKnowledgeQa,
  batchVectorizeCsKnowledge
} from '@/api/cs'

const defaultBasicForm = () => ({
  knowledge_base_id: 0,
  title: '',
  source: 'manual',
  status: 1,
  sort: 0
})

defineOptions({
  name: 'KnowledgeDrawer'
})

const props = defineProps({
  knowledgeBaseId: {
    type: Number,
    default: 0
  }
})
const emit = defineEmits(['saved'])
const { proxy } = getCurrentInstance()
const message = proxy.$message
const visible = ref(false)
const step = ref(0)
const saving = ref(false)
const parsing = ref(false)
const autoVectorize = ref(true)
const knowledgeId = ref(0)
const basicFormRef = ref(null)
const uploaderRef = ref(null)
const basicForm = reactive(defaultBasicForm())
const basicRules = {
  title: [{ required: true, message: '请输入标题', trigger: 'blur' }]
}
const rawText = ref('')
const rawUrl = ref('')
const segments = ref([])

const drawerTitle = computed(() => knowledgeId.value > 0 ? '编辑知识' : '新增知识')
const drawerWidth = computed(() => window.innerWidth < 960 ? '100%' : '860px')

function open(knowledge = null) {
  step.value = 0
  segments.value = []
  rawText.value = ''
  rawUrl.value = ''
  saving.value = false

  if (knowledge) {
    knowledgeId.value = Number(knowledge.id)
    Object.assign(basicForm, {
      knowledge_base_id: Number(knowledge.knowledge_base_id || props.knowledgeBaseId),
      title: String(knowledge.title || ''),
      source: String(knowledge.source || 'manual'),
      status: Number(knowledge.status !== undefined && knowledge.status !== null ? knowledge.status : 1),
      sort: Number(knowledge.sort !== undefined && knowledge.sort !== null ? knowledge.sort : 0)
    })
  } else {
    knowledgeId.value = 0
    Object.assign(basicForm, defaultBasicForm(), {
      knowledge_base_id: props.knowledgeBaseId
    })
  }

  visible.value = true
}

function handleClose(done) {
  if (typeof done === 'function') {
    done()
    return
  }
  visible.value = false
}

function nextStep() {
  if (step.value === 0) {
    basicFormRef.value.validate(valid => {
      if (valid) step.value += 1
    })
    return
  }
  if (step.value === 1 && segments.value.length === 0) {
    message.warning('请先添加段落内容')
    return
  }
  step.value += 1
}

async function parseContent() {
  parsing.value = true
  try {
    const data = { source: basicForm.source }
    if (basicForm.source === 'manual') {
      if (!rawText.value.trim()) {
        message.warning('请输入内容')
        return
      }
      data.content = rawText.value
    } else if (basicForm.source === 'url') {
      if (!rawUrl.value.trim()) {
        message.warning('请输入URL')
        return
      }
      data.url = rawUrl.value
    }

    const res = await parseKnowledgeContent(data)
    const parsed = (res && res.data && res.data.data) ? res.data.data : (res.data || [])
    segments.value = (Array.isArray(parsed) ? parsed : []).map((s, i) => ({
      content: s.content || '',
      sort: s.sort !== undefined && s.sort !== null ? s.sort : i,
      qaList: []
    }))

    if (segments.value.length === 0) {
      message.warning('未解析出段落，请检查内容')
    }
  } finally {
    parsing.value = false
  }
}

async function handleFileBeforeUpload(file) {
  parsing.value = true
  try {
    const formData = new FormData()
    formData.append('source', 'file')
    formData.append('file', file)

    const res = await parseKnowledgeContent(formData)
    const parsed = (res && res.data && res.data.data) ? res.data.data : (res.data || [])
    segments.value = (Array.isArray(parsed) ? parsed : []).map((s, i) => ({
      content: s.content || '',
      sort: s.sort !== undefined && s.sort !== null ? s.sort : i,
      qaList: []
    }))

    if (segments.value.length === 0) {
      message.warning('未解析出段落，请检查文件内容')
    }
  } finally {
    parsing.value = false
  }
  return false
}

function addSegment() {
  segments.value.push({ content: '', sort: segments.value.length, qaList: [] })
}

function removeSegment(idx) {
  segments.value.splice(idx, 1)
}

function moveSegment(idx, dir) {
  const target = idx + dir
  if (target < 0 || target >= segments.value.length) return
  const tmp = segments.value[idx]
  segments.value[idx] = segments.value[target]
  segments.value[target] = tmp
}

function mergeSegment(idx) {
  const current = segments.value[idx]
  const next = segments.value[idx + 1]
  segments.value.splice(idx, 2, {
    content: `${current.content}\n${next.content}`,
    sort: idx,
    qaList: [...(current.qaList || []), ...(next.qaList || [])]
  })
}

function addQa(segIdx) {
  if (!segments.value[segIdx].qaList) {
    segments.value[segIdx].qaList = []
  }
  segments.value[segIdx].qaList.push({
    question: '',
    answer: '',
    sort: segments.value[segIdx].qaList.length
  })
}

function removeQa(segIdx, qaIdx) {
  segments.value[segIdx].qaList.splice(qaIdx, 1)
}

async function handleSave() {
  if (step.value !== 2) {
    message.warning('请先完成 QA 预设步骤后再保存')
    return
  }
  if (segments.value.length === 0) {
    message.warning('请先添加段落内容')
    return
  }

  const emptySegs = segments.value.filter(s => !s.content.trim())
  if (emptySegs.length > 0) {
    message.warning('存在内容为空的段落，请填写或删除')
    return
  }

  const qaList = segments.value.reduce((list, seg) => {
    return list.concat(Array.isArray(seg.qaList) ? seg.qaList : [])
  }, [])
  const meaningfulQaList = qaList.filter(qa => {
    return String(qa.question || '').trim() || String(qa.answer || '').trim()
  })
  const hasIncompleteQa = meaningfulQaList.some(qa => {
    return !String(qa.question || '').trim() || !String(qa.answer || '').trim()
  })
  if (hasIncompleteQa) {
    message.warning('请完善已添加的 QA，或删除空白 QA')
    return
  }

  saving.value = true
  try {
    let savedKnowledgeId = knowledgeId.value
    if (savedKnowledgeId > 0) {
      await updateCsKnowledge(savedKnowledgeId, {
        knowledge_base_id: basicForm.knowledge_base_id,
        title: basicForm.title,
        status: basicForm.status,
        sort: basicForm.sort
      })
    } else {
      const res = await createCsKnowledge({
        ...basicForm,
        content: (segments.value[0] && segments.value[0].content) || ''
      })
      const resData = res && res.data && res.data.data ? res.data.data : (res.data || {})
      savedKnowledgeId = Number(resData.id || 0)
      if (!savedKnowledgeId) {
        message.error('知识创建失败')
        return
      }
    }

    const segPayload = segments.value.map((s, i) => ({ content: s.content, sort: i }))
    const segRes = await batchSaveKnowledgeSegments({
      knowledge_id: savedKnowledgeId,
      segments: segPayload
    })
    const segIds = ((segRes && segRes.data && segRes.data.data) ? segRes.data.data : (segRes.data || {})).segment_ids || []

    for (let i = 0; i < segments.value.length; i++) {
      const qaItems = segments.value[i].qaList || []
      if (qaItems.length === 0 || !segIds[i]) continue
      const validQa = qaItems.filter(q => String(q.question || '').trim() && String(q.answer || '').trim())
      if (validQa.length === 0) continue

      await batchSaveKnowledgeQa({
        segment_id: segIds[i],
        knowledge_id: savedKnowledgeId,
        qa_list: validQa.map((q, qi) => ({
          question: String(q.question || '').trim(),
          answer: String(q.answer || '').trim(),
          sort: qi
        }))
      })
    }

    if (autoVectorize.value) {
      await batchVectorizeCsKnowledge({ ids: [savedKnowledgeId] })
    }

    message.success('保存成功')
    visible.value = false
    emit('saved')
  } finally {
    saving.value = false
  }
}

defineExpose({ open })
</script>

<style lang="scss" scoped>
.drawer-body {
  display: flex;
  flex-direction: column;
  height: 100%;
  padding: 0 24px 24px;
  color: var(--dashboard-text-primary);
  background: var(--dashboard-surface);
}

.drawer-steps {
  padding: 16px 0 24px;
  flex-shrink: 0;
}

:deep(.el-step__title),
:deep(.el-step__description),
:deep(.el-collapse-item__header),
:deep(.el-collapse-item__content) {
  color: var(--dashboard-text-secondary);
}

:deep(.el-collapse),
:deep(.el-collapse-item__header),
:deep(.el-collapse-item__wrap) {
  background: var(--dashboard-surface);
  border-color: var(--dashboard-border);
}

.step-content {
  flex: 1;
  overflow-y: auto;
  padding-bottom: 16px;
}

.input-area {
  margin-bottom: 16px;
}

.file-support-tip {
  margin-left: 8px;
  color: var(--dashboard-text-muted);
  font-size: 12px;
}

.segment-list-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
  font-size: 13px;
  color: var(--dashboard-text-secondary);
}

.segment-list {
  margin-top: 12px;
}

.segment-card {
  border: 1px solid var(--dashboard-border);
  border-radius: 8px;
  padding: 12px;
  margin-bottom: 12px;
  background: var(--dashboard-surface-muted);
  transition: box-shadow 0.2s;

  &:hover {
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.16);
  }
}

.seg-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}

.seg-index {
  font-size: 13px;
  font-weight: 500;
  color: var(--dashboard-text-secondary);
}

.seg-ops {
  display: flex;
  align-items: center;
  gap: 4px;
}

.qa-collapse-title {
  font-size: 14px;
  font-weight: 500;
}

.qa-count {
  font-size: 12px;
  color: var(--dashboard-text-muted);
  margin-left: 8px;
}

.qa-block {
  padding: 8px 0;
}

.seg-preview {
  font-size: 12px;
  color: var(--dashboard-text-muted);
  line-height: 1.5;
  margin-bottom: 12px;
  max-height: 60px;
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  line-clamp: 3;
  -webkit-box-orient: vertical;
}

.qa-item {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 12px;
  padding: 12px;
  border: 1px dashed var(--ds-outline);
  border-radius: 10px;
  background: var(--ds-surface-low);
}

.qa-item__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.qa-item__title {
  font-size: 12px;
  font-weight: 600;
  color: var(--ds-on-surface-variant);
}

.qa-remove-btn {
  padding: 0;
  color: var(--dashboard-danger);

  &:hover,
  &:focus {
    color: var(--dashboard-danger);
    opacity: 0.86;
  }
}

.qa-question,
.qa-answer {
  margin-right: 0;
}

.drawer-footer {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: 16px;
  border-top: 1px solid var(--dashboard-border);
  margin-top: 16px;
}

.footer-btns {
  display: flex;
  gap: 10px;
}

.danger-text {
  color: var(--dashboard-danger);
}
</style>
