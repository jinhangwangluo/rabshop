<template>
  <div>
    <el-card shadow="never">
      <template #header>
        <div class="panel-header">
          <div class="panel-header-left">
            <span class="panel-title">问答设置</span>
            <span class="panel-hint">管理该知识库下所有 QA 预设</span>
          </div>
          <div class="panel-actions">
            <el-button
              :disabled="qaList.length === 0"
              @click="handleToggleQaPageSelection"
            >{{ isQaPageSelected ? '取消全选' : '全选当前页' }}</el-button>
            <el-button
              :disabled="selectedQaIds.length === 0"
              :loading="vectorizing"
              icon="el-icon-refresh"
              @click="handleBatchVectorize"
            >批量加入向量化</el-button>
            <el-button
              type="primary"
              icon="el-icon-plus"
              @click="openCreate"
            >新增问答</el-button>
          </div>
        </div>
      </template>

      <!-- 筛选 -->
      <el-form :inline="true" :model="query" class="filter-form" @submit.prevent>
        <el-form-item label="选择文档">
          <el-select
            v-model="query.knowledge_id"
            clearable
            placeholder="全部文档"
            style="width:200px"
            filterable
            @change="onKnowledgeChange"
          >
            <el-option
              v-for="k in knowledgeOptions"
              :key="k.id"
              :label="k.title"
              :value="Number(k.id)"
            />
          </el-select>
        </el-form-item>
        <el-form-item v-if="query.knowledge_id" label="选择段落">
          <el-select
            v-model="query.segment_id"
            clearable
            placeholder="全部段落"
            style="width:240px"
            @change="onFilterChange"
          >
            <el-option
              v-for="s in segmentOptions"
              :key="s.id"
              :label="segmentLabel(s)"
              :value="Number(s.id)"
            />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" icon="el-icon-search" @click="onFilterChange">查询</el-button>
        </el-form-item>
      </el-form>

      <!-- 当前段落内容展示 -->
      <div v-if="currentSegment" class="segment-preview">
        <div class="segment-preview-label"><LegacyIcon name="el-icon-document" /> 当前段落内容</div>
        <div class="segment-preview-content">{{ currentSegment.content }}</div>
      </div>

      <!-- QA 列表 -->
      <div v-loading="loading">
        <div v-if="qaList.length === 0 && !loading" class="empty-tip">
          <el-empty description="暂无问答，点击右上角「新增问答」添加" :image-size="80" />
        </div>

        <div
          v-for="qa in qaList"
          :key="qa.id"
          class="qa-card"
        >
          <div class="qa-header">
            <div class="qa-header-left">
              <el-checkbox
                :model-value="isQaSelected(qa.id)"
                @change="handleQaCheck(qa.id, $event)"
              />
              <el-tag size="mini" :type="vectorTagType(qa.vector_status)">{{ vectorTagLabel(qa.vector_status) }}</el-tag>
            </div>
            <div class="qa-ops">
              <a class="op-link" @click="handleVectorize(qa)"><LegacyIcon name="el-icon-refresh" /> 向量化</a>
              <el-divider direction="vertical" />
              <a class="op-link" @click="openEdit(qa)"><LegacyIcon name="el-icon-edit" /> 编辑</a>
              <el-divider direction="vertical" />
              <a class="op-link danger" @click="handleDelete(qa)"><LegacyIcon name="el-icon-delete" /> 删除</a>
            </div>
          </div>
          <div class="qa-q"><span class="qa-label qa-label-q">Q</span>{{ qa.question }}</div>
          <div class="qa-a"><span class="qa-label qa-label-a">A</span>{{ qa.answer }}</div>
        </div>
      </div>

      <!-- 分页 -->
      <div v-if="total > 0" class="pagination-wrap">
        <el-pagination
          background
          layout="total, sizes, prev, pager, next"
          :total="total"
          :page-size="query.limit"
          :current-page="query.page"
          :page-sizes="[10, 15, 20, 50]"
          @size-change="onSizeChange"
          @current-change="onPageChange"
        />
      </div>
    </el-card>

    <!-- 新增 / 编辑 QA 弹框 -->
    <el-dialog
      v-model="editVisible"
      :title="editQaId ? '编辑问答' : '新增问答'"
      width="560px"
      @close="resetForm"
    >
      <!-- 弹框内当前文档/段落信息 -->
      <el-form :model="createForm" label-width="60px" style="margin-top:4px">
        <el-form-item label="文档">
          <el-select
            v-model="createForm.knowledge_id"
            placeholder="请选择文档"
            style="width:100%"
            filterable
            @change="onCreateKnowledgeChange"
          >
            <el-option
              v-for="k in knowledgeOptions"
              :key="k.id"
              :label="k.title"
              :value="Number(k.id)"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="段落">
          <el-select
            v-model="createForm.segment_id"
            placeholder="请选择段落"
            style="width:100%"
            :disabled="!createForm.knowledge_id"
          >
            <el-option
              v-for="s in createSegmentOptions"
              :key="s.id"
              :label="segmentLabel(s)"
              :value="Number(s.id)"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="问题">
          <el-input v-model="createForm.question" placeholder="请输入问题" />
        </el-form-item>
        <el-form-item label="答案">
          <el-input v-model="createForm.answer" type="textarea" :rows="4" placeholder="请输入答案" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="createForm.sort" :min="0" :max="9999" style="width:120px" />
        </el-form-item>
      </el-form>
      <template #footer>
        <span>
          <el-button @click="editVisible = false">取消</el-button>
          <el-button type="primary" :loading="saving" @click="handleSave">保存</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, onBeforeMount, reactive, ref } from 'vue'
import {
  getCsKnowledgeList,
  getKnowledgeSegmentList,
  getKnowledgeQaList,
  createKnowledgeQa,
  updateKnowledgeQa,
  deleteKnowledgeQa,
  vectorizeKnowledgeQa,
  batchVectorizeKnowledgeQa
} from '@/api/cs'

const VECTOR_STATUS = { PENDING: 0, PROCESSING: 1, COMPLETED: 2 }

defineOptions({
  name: 'KnowledgeQaPanel'
})

const props = defineProps({
  knowledgeBaseId: { type: Number, required: true }
})
const { proxy } = getCurrentInstance()
const message = proxy.$message
const confirm = proxy.$confirm
const loading = ref(false)
const saving = ref(false)
const vectorizing = ref(false)
const knowledgeOptions = ref([])
const segmentOptions = ref([])
const createSegmentOptions = ref([])
const currentSegment = ref(null)
const qaList = ref([])
const total = ref(0)
const selectedQaIds = ref([])
const editVisible = ref(false)
const editQaId = ref(0)
const query = reactive({ knowledge_id: '', segment_id: '', page: 1, limit: 15 })
const createForm = reactive({ knowledge_id: '', segment_id: '', question: '', answer: '', sort: 0 })
const isQaPageSelected = computed(() => qaList.value.length > 0 && selectedQaIds.value.length === qaList.value.length)

function vectorTagType(status) {
  const map = { [VECTOR_STATUS.COMPLETED]: 'success', [VECTOR_STATUS.PROCESSING]: 'warning' }
  return map[Number(status)] || 'info'
}

function vectorTagLabel(status) {
  const map = { [VECTOR_STATUS.COMPLETED]: '已完成', [VECTOR_STATUS.PROCESSING]: '向量化中', [VECTOR_STATUS.PENDING]: '待处理' }
  return map[Number(status)] || '待处理'
}

function segmentLabel(s) {
  const text = s.content ? s.content.slice(0, 30) + (s.content.length > 30 ? '...' : '') : `段落#${s.id}`
  return `#${s.id} ${text}`
}

async function fetchKnowledgeOptions() {
  const res = await getCsKnowledgeList({ knowledge_base_id: props.knowledgeBaseId, page: 1, limit: 200 })
  const data = (res && res.data && res.data.data) ? res.data.data : (res.data || {})
  knowledgeOptions.value = data.list || []
}

async function onKnowledgeChange(val) {
  segmentOptions.value = []
  query.segment_id = ''
  currentSegment.value = null
  if (!val) {
    onFilterChange()
    return
  }
  const res = await getKnowledgeSegmentList({ knowledge_base_id: props.knowledgeBaseId, knowledge_id: val, page: 1, limit: 500 })
  const data = (res && res.data && res.data.data) ? res.data.data : (res.data || {})
  segmentOptions.value = data.list || []
  onFilterChange()
}

function onFilterChange() {
  currentSegment.value = query.segment_id
    ? segmentOptions.value.find(s => Number(s.id) === query.segment_id) || null
    : null
  query.page = 1
  fetchQaList()
}

async function fetchQaList() {
  loading.value = true
  try {
    const params = {
      knowledge_base_id: props.knowledgeBaseId,
      page: query.page,
      limit: query.limit
    }
    if (query.knowledge_id) params.knowledge_id = query.knowledge_id
    if (query.segment_id) params.segment_id = query.segment_id
    const res = await getKnowledgeQaList(params)
    const data = (res && res.data && res.data.data) ? res.data.data : (res.data || {})
    qaList.value = data.list || []
    total.value = data.total || 0
    selectedQaIds.value = []
  } finally {
    loading.value = false
  }
}

function onPageChange(page) {
  query.page = page
  fetchQaList()
}

function onSizeChange(size) {
  query.limit = size
  query.page = 1
  fetchQaList()
}

function openCreate() {
  editQaId.value = 0
  Object.assign(createForm, {
    knowledge_id: query.knowledge_id || '',
    segment_id: query.segment_id || '',
    question: '',
    answer: '',
    sort: 0
  })
  createSegmentOptions.value = createForm.knowledge_id ? segmentOptions.value : []
  editVisible.value = true
}

function openEdit(qa) {
  editQaId.value = qa.id
  Object.assign(createForm, {
    knowledge_id: qa.knowledge_id || '',
    segment_id: qa.segment_id || '',
    question: qa.question,
    answer: qa.answer,
    sort: qa.sort || 0
  })
  createSegmentOptions.value = segmentOptions.value
  editVisible.value = true
}

function resetForm() {
  editQaId.value = 0
  Object.assign(createForm, { knowledge_id: '', segment_id: '', question: '', answer: '', sort: 0 })
}

async function onCreateKnowledgeChange(val) {
  createForm.segment_id = ''
  createSegmentOptions.value = []
  if (!val) return
  const res = await getKnowledgeSegmentList({ knowledge_base_id: props.knowledgeBaseId, knowledge_id: val, page: 1, limit: 500 })
  const data = (res && res.data && res.data.data) ? res.data.data : (res.data || {})
  createSegmentOptions.value = data.list || []
}

async function handleSave() {
  if (!createForm.knowledge_id) {
    message.warning('请选择文档')
    return
  }
  if (!createForm.segment_id) {
    message.warning('请选择段落')
    return
  }
  if (!createForm.question.trim() || !createForm.answer.trim()) {
    message.warning('问题和答案不能为空')
    return
  }
  saving.value = true
  try {
    if (editQaId.value) {
      await updateKnowledgeQa(editQaId.value, {
        question: createForm.question,
        answer: createForm.answer,
        sort: createForm.sort
      })
      message.success('保存成功')
    } else {
      await createKnowledgeQa({
        segment_id: createForm.segment_id,
        knowledge_id: createForm.knowledge_id,
        question: createForm.question,
        answer: createForm.answer,
        sort: createForm.sort
      })
      message.success('新增成功')
    }
    editVisible.value = false
    fetchQaList()
  } finally {
    saving.value = false
  }
}

async function handleVectorize(qa) {
  await vectorizeKnowledgeQa(qa.id)
  message.success('向量化任务已提交')
  fetchQaList()
}

function isQaSelected(id) {
  return selectedQaIds.value.indexOf(Number(id)) !== -1
}

function handleQaCheck(id, checked) {
  const qaId = Number(id)
  if (checked && selectedQaIds.value.indexOf(qaId) === -1) {
    selectedQaIds.value.push(qaId)
  }
  if (!checked) {
    selectedQaIds.value = selectedQaIds.value.filter(item => item !== qaId)
  }
}

function handleToggleQaPageSelection() {
  if (isQaPageSelected.value) {
    selectedQaIds.value = []
    return
  }
  selectedQaIds.value = qaList.value.map(qa => Number(qa.id)).filter(id => id > 0)
}

async function handleBatchVectorize() {
  if (selectedQaIds.value.length === 0) {
    message.warning('请选择要加入向量化的问答')
    return
  }

  vectorizing.value = true
  try {
    const ids = selectedQaIds.value
    const res = await batchVectorizeKnowledgeQa({ ids })
    const data = (res && res.data && res.data.data) ? res.data.data : (res.data || {})
    message.success(`已提交 ${Number(data.queued_count || ids.length)} 个向量化任务`)
    fetchQaList()
  } finally {
    vectorizing.value = false
  }
}

async function handleDelete(qa) {
  try {
    await confirm('确定删除该问答吗？', '提示', { type: 'warning' })
  } catch {
    return
  }
  await deleteKnowledgeQa(qa.id)
  message.success('删除成功')
  fetchQaList()
}

onBeforeMount(() => {
  fetchKnowledgeOptions()
  fetchQaList()
})
</script>

<style lang="scss" scoped>
:deep(.el-card) {
  color: var(--dashboard-text-primary);
  background: var(--dashboard-surface);
  border-color: var(--dashboard-border);
}

:deep(.el-card__header) {
  border-bottom-color: var(--dashboard-border);
}

.panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.panel-header-left {
  display: flex;
  align-items: center;
  gap: 16px;
}

.panel-actions {
  display: flex;
  align-items: center;
}

.panel-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--dashboard-text-primary);
}

.panel-hint {
  font-size: 12px;
  color: var(--dashboard-text-muted);
}

.filter-form {
  margin-bottom: 4px;
}

/* 段落内容预览 */
.segment-preview {
  background: var(--dashboard-accent-outline);
  border: 1px solid var(--dashboard-border);
  border-radius: 8px;
  padding: 10px 14px;
  margin-bottom: 16px;
}

.segment-preview-label {
  font-size: 12px;
  color: var(--dashboard-accent);
  font-weight: 500;
  margin-bottom: 6px;

  i { margin-right: 4px; }
}

.segment-preview-content {
  font-size: 13px;
  color: var(--dashboard-text-secondary);
  line-height: 1.7;
  max-height: 80px;
  overflow-y: auto;
  white-space: pre-wrap;
}

.empty-tip {
  padding: 30px 0;
}

/* QA 卡片 */
.qa-card {
  border: 1px solid var(--dashboard-border);
  border-radius: 8px;
  padding: 14px 16px;
  margin-bottom: 12px;
  background: var(--dashboard-surface-muted);
  transition: box-shadow 0.2s;

  &:hover {
    box-shadow: 0 2px 12px var(--dashboard-accent-outline);
  }
}

.qa-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}

.qa-header-left {
  display: flex;
  align-items: center;
  gap: 10px;
}

.qa-ops {
  display: flex;
  align-items: center;
  gap: 4px;
}

.qa-q, .qa-a {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  font-size: 13px;
  color: var(--dashboard-text-secondary);
  line-height: 1.6;
  margin-bottom: 6px;
}

.qa-label {
  flex-shrink: 0;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  color: var(--dashboard-on-accent);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 700;

  &.qa-label-q {
    background: var(--dashboard-accent);
  }

  &.qa-label-a {
    background: var(--dashboard-accent-hover);
  }
}

.op-link {
  font-size: 13px;
  color: var(--dashboard-accent);
  cursor: pointer;
  text-decoration: none;

  &:hover {
    color: var(--dashboard-accent-hover);
  }

  &.danger {
    color: var(--dashboard-danger);

    &:hover {
      color: var(--dashboard-danger);
      opacity: 0.86;
    }
  }
}

.pagination-wrap {
  margin-top: 16px;
  display: flex;
  justify-content: flex-end;
}
</style>
