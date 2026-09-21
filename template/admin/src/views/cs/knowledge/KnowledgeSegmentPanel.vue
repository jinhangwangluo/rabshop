<template>
  <div>
    <el-card shadow="never">
      <template #header>
        <div class="panel-header">
          <div class="panel-header-left">
            <span class="panel-title">分段管理</span>
            <span class="panel-hint">管理该知识库下所有文档的段落</span>
          </div>
          <div class="panel-actions">
            <el-button
              :disabled="segments.length === 0"
              @click="handleToggleSegmentPageSelection"
            >{{ isSegmentPageSelected ? '取消全选' : '全选当前页' }}</el-button>
            <el-button
              :disabled="selectedSegmentIds.length === 0"
              :loading="vectorizing"
              icon="el-icon-refresh"
              @click="handleBatchVectorize"
            >批量加入向量化</el-button>
          </div>
        </div>
      </template>

      <!-- 筛选：文档可选 -->
      <el-form :inline="true" :model="query" @submit.prevent>
        <el-form-item label="选择文档">
          <el-select
            v-model="query.knowledge_id"
            clearable
            placeholder="全部文档"
            style="width:240px"
            filterable
            @change="onFilterChange"
          >
            <el-option
              v-for="k in knowledgeOptions"
              :key="k.id"
              :label="k.title"
              :value="Number(k.id)"
            />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" icon="el-icon-search" @click="onFilterChange">查询</el-button>
        </el-form-item>
      </el-form>

      <!-- 段落列表 -->
      <div v-loading="loading">
        <div v-if="segments.length === 0 && !loading">
          <el-empty description="暂无段落数据" />
        </div>
        <div
          v-for="seg in segments"
          :key="seg.id"
          class="seg-card"
        >
          <div class="seg-header">
            <el-checkbox
              :model-value="isSegmentSelected(seg.id)"
              @change="handleSegmentCheck(seg.id, $event)"
            />
            <span class="seg-index">段落 #{{ seg.id }}</span>
            <el-tag size="mini" :type="vectorTagType(seg.vector_status)">{{ vectorTagLabel(seg.vector_status) }}</el-tag>
            <div class="seg-ops">
              <a class="op-link" @click="handleVectorize(seg)"><LegacyIcon name="el-icon-refresh" /> 向量化</a>
              <el-divider direction="vertical" />
              <a class="op-link" @click="openEdit(seg)"><LegacyIcon name="el-icon-edit" /> 编辑</a>
              <el-divider direction="vertical" />
              <a class="op-link danger" @click="handleDelete(seg)"><LegacyIcon name="el-icon-delete" /> 删除</a>
            </div>
          </div>
          <div class="seg-content">{{ seg.content }}</div>
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

    <!-- 编辑段落抽屉 -->
    <el-drawer
      v-model="editVisible"
      title="编辑段落"
      direction="rtl"
      size="600px"
      :wrapper-closable="false"
    >
      <div class="drawer-body">
        <el-input
          v-model="editContent"
          type="textarea"
          :rows="24"
          placeholder="段落内容"
          resize="none"
        />
      </div>
      <div class="drawer-footer">
        <el-button @click="editVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="handleSaveEdit">保存</el-button>
      </div>
    </el-drawer>
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, onBeforeMount, reactive, ref } from 'vue'
import {
  getCsKnowledgeList,
  getKnowledgeSegmentList,
  updateKnowledgeSegment,
  deleteKnowledgeSegment,
  vectorizeKnowledgeSegment,
  batchVectorizeKnowledgeSegment
} from '@/api/cs'

const VECTOR_STATUS = { PENDING: 0, PROCESSING: 1, COMPLETED: 2 }

defineOptions({
  name: 'KnowledgeSegmentPanel'
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
const segments = ref([])
const total = ref(0)
const selectedSegmentIds = ref([])
const editVisible = ref(false)
const editSegId = ref(0)
const editContent = ref('')
const query = reactive({ knowledge_id: '', page: 1, limit: 15 })
const isSegmentPageSelected = computed(() => segments.value.length > 0 && selectedSegmentIds.value.length === segments.value.length)

function vectorTagType(status) {
  const map = { [VECTOR_STATUS.COMPLETED]: 'success', [VECTOR_STATUS.PROCESSING]: 'warning' }
  return map[Number(status)] || 'info'
}

function vectorTagLabel(status) {
  const map = { [VECTOR_STATUS.COMPLETED]: '已完成', [VECTOR_STATUS.PROCESSING]: '向量化中', [VECTOR_STATUS.PENDING]: '待处理' }
  return map[Number(status)] || '待处理'
}

async function fetchKnowledgeOptions() {
  const res = await getCsKnowledgeList({ knowledge_base_id: props.knowledgeBaseId, page: 1, limit: 200 })
  const data = (res && res.data && res.data.data) ? res.data.data : (res.data || {})
  knowledgeOptions.value = data.list || []
}

async function fetchSegments() {
  loading.value = true
  try {
    const params = {
      knowledge_base_id: props.knowledgeBaseId,
      page: query.page,
      limit: query.limit
    }
    if (query.knowledge_id) {
      params.knowledge_id = query.knowledge_id
    }
    const res = await getKnowledgeSegmentList(params)
    const data = (res && res.data && res.data.data) ? res.data.data : (res.data || {})
    segments.value = data.list || []
    total.value = data.total || 0
    selectedSegmentIds.value = []
  } finally {
    loading.value = false
  }
}

function onFilterChange() {
  query.page = 1
  fetchSegments()
}

function onPageChange(page) {
  query.page = page
  fetchSegments()
}

function onSizeChange(size) {
  query.limit = size
  query.page = 1
  fetchSegments()
}

function openEdit(seg) {
  editSegId.value = seg.id
  editContent.value = seg.content
  editVisible.value = true
}

async function handleSaveEdit() {
  if (!editContent.value.trim()) {
    message.warning('段落内容不能为空')
    return
  }
  saving.value = true
  try {
    await updateKnowledgeSegment(editSegId.value, { content: editContent.value })
    message.success('保存成功')
    editVisible.value = false
    fetchSegments()
  } finally {
    saving.value = false
  }
}

async function handleVectorize(seg) {
  await vectorizeKnowledgeSegment(seg.id)
  message.success('向量化任务已提交')
  fetchSegments()
}

function isSegmentSelected(id) {
  return selectedSegmentIds.value.indexOf(Number(id)) !== -1
}

function handleSegmentCheck(id, checked) {
  const segmentId = Number(id)
  if (checked && selectedSegmentIds.value.indexOf(segmentId) === -1) {
    selectedSegmentIds.value.push(segmentId)
  }
  if (!checked) {
    selectedSegmentIds.value = selectedSegmentIds.value.filter(item => item !== segmentId)
  }
}

function handleToggleSegmentPageSelection() {
  if (isSegmentPageSelected.value) {
    selectedSegmentIds.value = []
    return
  }
  selectedSegmentIds.value = segments.value.map(seg => Number(seg.id)).filter(id => id > 0)
}

async function handleBatchVectorize() {
  if (selectedSegmentIds.value.length === 0) {
    message.warning('请选择要加入向量化的段落')
    return
  }

  vectorizing.value = true
  try {
    const ids = selectedSegmentIds.value
    const res = await batchVectorizeKnowledgeSegment({ ids })
    const data = (res && res.data && res.data.data) ? res.data.data : (res.data || {})
    message.success(`已提交 ${Number(data.queued_count || ids.length)} 个向量化任务`)
    fetchSegments()
  } finally {
    vectorizing.value = false
  }
}

async function handleDelete(seg) {
  try {
    await confirm('确定删除该段落吗？', '提示', { type: 'warning' })
  } catch {
    return
  }
  await deleteKnowledgeSegment(seg.id)
  message.success('删除成功')
  fetchSegments()
}

onBeforeMount(() => {
  fetchKnowledgeOptions()
  fetchSegments()
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

.seg-card {
  border: 1px solid var(--dashboard-border);
  border-radius: 8px;
  padding: 12px 16px;
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
  gap: 10px;
  margin-bottom: 8px;
}

.seg-index {
  font-size: 12px;
  font-weight: 500;
  color: var(--dashboard-text-muted);
}

.seg-ops {
  margin-left: auto;
  display: flex;
  align-items: center;
  gap: 4px;
}

.seg-content {
  font-size: 13px;
  color: var(--dashboard-text-secondary);
  line-height: 1.6;
  white-space: pre-wrap;
  max-height: 120px;
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 5;
  line-clamp: 5;
  -webkit-box-orient: vertical;
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

.drawer-body {
  padding: 20px 20px 80px;
  height: 100%;
  box-sizing: border-box;
  color: var(--dashboard-text-primary);
  background: var(--dashboard-surface);

  .el-textarea {
    height: 100%;

    :deep(.el-textarea__inner) {
      height: 100%;
      resize: none;
      color: var(--dashboard-text-primary);
      background: var(--dashboard-surface-muted);
      border-color: var(--dashboard-border);
    }
  }
}

.drawer-footer {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 14px 20px;
  background: var(--dashboard-surface);
  border-top: 1px solid var(--dashboard-border);
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
</style>
