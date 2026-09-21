<template>
  <div>
    <el-card shadow="never">
      <template #header>
        <div class="panel-header">
          <span class="panel-title">知识库内容</span>
          <div class="panel-actions">
            <el-button
              :disabled="list.length === 0"
              @click="handleToggleKnowledgePageSelection"
            >{{ isKnowledgePageSelected ? '取消全选' : '全选当前页' }}</el-button>
            <el-button
              :disabled="selectedKnowledgeIds.length === 0"
              :loading="vectorizing"
              :icon="Refresh"
              @click="handleBatchVectorize"
            >批量加入向量化</el-button>
            <el-button type="primary" :icon="Plus" @click="openDrawer()">新增文档</el-button>
          </div>
        </div>
      </template>

      <!-- 搜索 -->
      <el-form :inline="true" :model="query" @submit.prevent>
        <el-form-item label="关键词">
          <el-input v-model="query.keyword" clearable placeholder="标题关键词" style="width:200px" @keyup.enter="fetchList" />
        </el-form-item>
        <el-form-item label="向量化">
          <el-select v-model="query.vector_status" clearable placeholder="全部" style="width:120px">
            <el-option label="待处理" :value="0" />
            <el-option label="向量化中" :value="1" />
            <el-option label="已完成" :value="2" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :icon="Search" @click="fetchList">查询</el-button>
          <el-button :icon="Refresh" @click="resetQuery">重置</el-button>
        </el-form-item>
      </el-form>

      <el-table
        ref="knowledgeTable"
        v-loading="loading"
        :data="list"
        row-key="id"
        @selection-change="handleSelectionChange"
      >
        <el-table-column type="selection" width="48" />
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column prop="title" label="标题" min-width="220" show-overflow-tooltip />
        <el-table-column label="来源" width="100">
          <template #default="{ row }">
            <el-tag  :type="sourceTagType(row.source)">{{ sourceLabel(row.source) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="向量化状态" width="110">
          <template #default="{ row }">
            <el-tag  :type="vectorTagType(row.vector_status)">
              {{ vectorTagLabel(row.vector_status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="create_time" label="创建时间" width="180" />
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <div class="table-ops">
              <a class="op-link" @click="openDrawer(row)">编辑</a>
              <el-divider direction="vertical" />
              <a class="op-link" @click="handleVectorize(row)">向量化</a>
              <el-divider direction="vertical" />
              <a class="op-link danger" @click="handleDelete(row)">删除</a>
            </div>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrap">
        <el-pagination
          v-model:current-page="query.page"
          v-model:page-size="query.limit"
          :page-sizes="[10, 20, 50]"
          layout="total, sizes, prev, pager, next"
          :total="total"
          @size-change="val => { query.limit = val; fetchList() }"
          @current-change="val => { query.page = val; fetchList() }"
        />
      </div>
    </el-card>

    <!-- 文档抽屉 -->
    <knowledge-drawer
      ref="knowledgeDrawer"
      :knowledge-base-id="knowledgeBaseId"
      @saved="fetchList"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Refresh, Search } from '@element-plus/icons-vue'
import { getCsKnowledgeList, deleteCsKnowledge, batchVectorizeCsKnowledge } from '@/api/cs'
import KnowledgeDrawer from './KnowledgeDrawer.vue'

defineOptions({
  name: 'KnowledgeContentPanel'
})

const props = defineProps({
  knowledgeBaseId: { type: Number, required: true }
})

const VECTOR_STATUS = { PENDING: 0, PROCESSING: 1, COMPLETED: 2 }
const SOURCE_MAP = { manual: { label: '手动录入', type: '' }, file: { label: '文件导入', type: 'success' }, url: { label: 'URL采集', type: 'warning' }}

const loading = ref(false)
const vectorizing = ref(false)
const list = ref([])
const total = ref(0)
const selectedKnowledgeIds = ref([])
const query = reactive({
  keyword: '',
  vector_status: '',
  page: 1,
  limit: 20
})
const knowledgeTable = ref(null)
const knowledgeDrawer = ref(null)

const isKnowledgePageSelected = computed(() => {
  return list.value.length > 0 && selectedKnowledgeIds.value.length === list.value.length
})

onMounted(() => {
  fetchList()
})

function vectorTagType(status) {
  const map = { [VECTOR_STATUS.COMPLETED]: 'success', [VECTOR_STATUS.PROCESSING]: 'warning' }
  return map[Number(status)] || 'info'
}
function vectorTagLabel(status) {
  const map = { [VECTOR_STATUS.COMPLETED]: '已完成', [VECTOR_STATUS.PROCESSING]: '向量化中', [VECTOR_STATUS.PENDING]: '待处理' }
  return map[Number(status)] || '待处理'
}
function sourceLabel(source) {
  return (SOURCE_MAP[source] || { label: source || '未知' }).label
}
function sourceTagType(source) {
  return (SOURCE_MAP[source] || { type: 'info' }).type
}

async function fetchList() {
  loading.value = true
  try {
    const res = await getCsKnowledgeList({
      knowledge_base_id: props.knowledgeBaseId,
      keyword: query.keyword,
      vector_status: query.vector_status,
      page: query.page,
      limit: query.limit
    })
    const data = (res && res.data && res.data.data) ? res.data.data : (res.data || {})
    list.value = data.list || []
    total.value = Number(data.total || 0)
    selectedKnowledgeIds.value = []
  } finally {
    loading.value = false
  }
}

function resetQuery() {
  Object.assign(query, { keyword: '', vector_status: '', page: 1, limit: 20 })
  fetchList()
}

function openDrawer(knowledge = null) {
  knowledgeDrawer.value.open(knowledge)
}

async function handleVectorize(row) {
  await batchVectorizeCsKnowledge({ ids: [row.id] })
  ElMessage.success('向量化任务已提交')
  fetchList()
}

function handleSelectionChange(rows) {
  selectedKnowledgeIds.value = rows.map(row => Number(row.id)).filter(id => id > 0)
}

function handleToggleKnowledgePageSelection() {
  if (!knowledgeTable.value) return
  if (isKnowledgePageSelected.value) {
    knowledgeTable.value.clearSelection()
    return
  }
  knowledgeTable.value.toggleAllSelection()
}

async function handleBatchVectorize() {
  if (selectedKnowledgeIds.value.length === 0) {
    ElMessage.warning('请选择要加入向量化的文档')
    return
  }

  vectorizing.value = true
  try {
    const res = await batchVectorizeCsKnowledge({ ids: selectedKnowledgeIds.value })
    const data = (res && res.data && res.data.data) ? res.data.data : (res.data || {})
    ElMessage.success(`已提交 ${Number(data.queued_count || selectedKnowledgeIds.value.length)} 个向量化任务`)
    fetchList()
  } finally {
    vectorizing.value = false
  }
}

async function handleDelete(row) {
  try {
    await ElMessageBox.confirm('确定删除该文档吗？', '提示', { type: 'warning' })
  } catch {
    return
  }
  await deleteCsKnowledge(row.id)
  ElMessage.success('删除成功')
  fetchList()
}
</script>

<style lang="scss" scoped>
.panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.panel-title {
  font-size: 15px;
  font-weight: 600;
  color: #303133;
}

.panel-actions {
  display: flex;
  align-items: center;
}

.pagination-wrap {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}

.table-ops {
  display: inline-flex;
  align-items: center;
  gap: 2px;
}

.op-link {
  font-size: 13px;
  color: #409eff;
  cursor: pointer;
  text-decoration: none;

  &:hover {
    color: #66b1ff;
  }

  &.danger {
    color: #f56c6c;

    &:hover {
      color: #f78989;
    }
  }
}
</style>
