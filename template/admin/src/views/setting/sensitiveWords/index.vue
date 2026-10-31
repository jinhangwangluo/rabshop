<template>
  <div class="sensitive-words-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">敏感词管理</h1>
        <p class="page-curator-subtitle">
          维护敏感词词库，支持批量导入导出与实时检测
        </p>
      </div>
      <div class="page-curator-header__actions">
        <!-- 操作按钮区域 -->
        <div class="header-actions">
          <el-button
            v-if="selectedIds.length > 0"
            type="danger"
            plain
            :icon="Delete"
            @click="handleBatchDelete"
          >
            批量删除 ({{ selectedIds.length }})
          </el-button>
          <el-button :icon="UploadFilled" @click="showImportDialog = true">导入</el-button>
          <el-button :icon="Download" @click="handleExport">导出</el-button>
          <el-button :icon="DeleteFilled" @click="handleClearCache">清除缓存</el-button>
        </div>
        <el-button type="primary" class="btn-curator-primary" :icon="Plus" @click="handleAdd">
          添加敏感词
        </el-button>
      </div>
    </header>
    <div class="sensitive-words-work-surface">
      <!-- 搜索筛选区域 -->
      <div class="search-form-wrap">
        <div class="search-form-wrap__main">
          <el-form
            ref="searchFormRef"
            :model="searchForm"
            inline
            @submit.prevent
          >
            <el-form-item label="关键词">
              <el-input
                v-model="searchForm.word"
                clearable
                placeholder="请输入敏感词"
                class="search-input"
                :prefix-icon="Search"
                @clear="handleSearch"
                @keyup.enter="handleSearch"
              />
            </el-form-item>
            <el-form-item label="状态">
              <el-select v-model="searchForm.status" clearable placeholder="全部状态" class="status-select" @change="handleSearch">
                <el-option label="启用" :value="1" />
                <el-option label="禁用" :value="0" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" class="btn-curator-primary" :icon="Search" @click="handleSearch">查询</el-button>
              <el-button class="btn-curator-reset" :icon="Refresh" @click="resetSearch">重置</el-button>
            </el-form-item>
          </el-form>
        </div>

      </div>

      <el-table
        v-loading="loading"
        :data="wordList"
        row-key="id"
        @selection-change="handleSelectionChange"
      >
        <el-table-column type="selection" width="50" align="center" />
        <el-table-column prop="id" label="ID" width="80" align="center" />
        <el-table-column prop="word" label="敏感词" min-width="160">
          <template #default="{ row }">
            <el-tag type="danger" class="word-tag">{{ row.word }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="remark" label="备注说明" min-width="200">
          <template #default="{ row }">
            <span class="remark-text">{{ row.remark || '—' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-switch
              v-model="row.status"
              :active-value="1"
              :inactive-value="0"
              @change="handleStatusChange(row)"
            />
          </template>
        </el-table-column>
        <el-table-column prop="create_time" label="添加时间" width="180" align="center" />
        <el-table-column label="操作" width="150" align="center" fixed="right">
          <template #default="{ row }">
            <a @click="handleEdit(row)">编辑</a>
            <el-divider direction="vertical" />
            <a @click="handleDelete(row)">删除</a>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="pagination-wrap">
        <el-pagination
          :current-page="searchForm.page"
          :page-sizes="[10, 20, 50, 100]"
          :page-size="searchForm.page_size"
          layout="total, sizes, prev, pager, next, jumper"
          :total="total"
          @size-change="handleSizeChange"
          @current-change="handlePageChange"
        />
      </div>
    </div>

    <!-- 新增/编辑对话框 -->
    <el-dialog
      v-model="showFormDialog"
      :title="dialogTitle"
      width="520px"
      :close-on-click-modal="false"
      @closed="resetForm"
    >
      <el-form ref="wordFormRef" :model="wordForm" :rules="wordRules" label-width="90px">
        <el-form-item label="敏感词" prop="word">
          <el-input
            v-model="wordForm.word"
            placeholder="请输入敏感词"
            maxlength="100"
            show-word-limit
            clearable
          />
        </el-form-item>
        <el-form-item label="备注说明" prop="remark">
          <el-input
            v-model="wordForm.remark"
            type="textarea"
            :rows="3"
            placeholder="请输入备注，说明该词的违规类型（可选）"
            maxlength="255"
            show-word-limit
          />
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-radio-group v-model="wordForm.status">
            <el-radio :label="1">启用</el-radio>
            <el-radio :label="0">禁用</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <div>
          <el-button @click="showFormDialog = false">取 消</el-button>
          <el-button type="primary" :loading="submitLoading" @click="handleSubmit">确 定</el-button>
        </div>
      </template>
    </el-dialog>

    <!-- 批量添加对话框 -->
    <el-dialog
      v-model="showBatchDialog"
      title="批量添加敏感词"
      width="560px"
      :close-on-click-modal="false"
    >
      <div class="batch-tips">
        <el-alert
          title="每行输入一个敏感词，重复词汇将自动跳过"
          type="info"
          :closable="false"
          show-icon
        />
      </div>
      <el-input
        v-model="batchWords"
        type="textarea"
        :rows="10"
        placeholder="请输入敏感词，每行一个&#10;例如：&#10;违禁词1&#10;违禁词2&#10;违禁词3"
        class="batch-textarea"
      />
      <template #footer>
        <div>
          <el-button @click="showBatchDialog = false">取 消</el-button>
          <el-button type="primary" :loading="batchLoading" @click="handleBatchAdd">批量添加</el-button>
        </div>
      </template>
    </el-dialog>

    <!-- 导入对话框 -->
    <el-dialog
      v-model="showImportDialog"
      title="导入敏感词"
      width="480px"
      :close-on-click-modal="false"
    >
      <div class="import-wrap">
        <el-alert
          title="支持 TXT/CSV 格式，每行一个敏感词，文件编码需为 UTF-8"
          type="info"
          :closable="false"
          show-icon
          class="import-alert"
        />
        <el-upload
          ref="uploadRef"
          :action="uploadAction"
          :http-request="handleImportUpload"
          :limit="1"
          :show-file-list="true"
          accept=".txt,.csv"
          :auto-upload="false"
          class="import-upload"
        >
          <template #trigger>
            <el-button :icon="FolderOpened">选择文件</el-button>
          </template>
          <template #tip>
            <div class="el-upload__tip">支持 .txt / .csv 格式，文件大小不超过 5MB</div>
          </template>
        </el-upload>
      </div>
      <template #footer>
        <div>
          <el-button @click="showImportDialog = false">取 消</el-button>
          <el-button type="primary" :loading="importLoading" @click="handleImportSubmit">开始导入</el-button>
        </div>
      </template>
    </el-dialog>

    <!-- 文本检测对话框 -->
    <el-dialog
      v-model="showCheckDialog"
      title="敏感词检测"
      width="560px"
      :close-on-click-modal="false"
    >
      <el-input
        v-model="checkContent"
        type="textarea"
        :rows="6"
        placeholder="请输入要检测的文本内容"
        class="check-textarea"
      />
      <div v-if="checkResult" class="check-result">
        <el-divider content-position="left">检测结果</el-divider>
        <div v-if="checkResult.has_sensitive" class="result-danger">
          <el-alert
            :title="`发现 ${checkResult.count} 个敏感词`"
            type="error"
            :closable="false"
            show-icon
          />
          <div class="hit-words">
            <el-tag
              v-for="(word, idx) in checkResult.hits"
              :key="idx"
              type="danger"

              class="hit-tag"
            >
              {{ word }}
            </el-tag>
          </div>
        </div>
        <el-alert
          v-else
          title="未发现敏感词，内容安全"
          type="success"
          :closable="false"
          show-icon
        />
      </div>
      <template #footer>
        <div>
          <el-button @click="showCheckDialog = false">关 闭</el-button>
          <el-button type="primary" :loading="checkLoading" @click="handleCheck">开始检测</el-button>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, onBeforeMount, reactive, ref } from 'vue'
import { Delete, DeleteFilled, Download, FolderOpened, Plus, Refresh, Search, UploadFilled } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  addSensitiveWord,
  batchAddSensitiveWords,
  batchDeleteSensitiveWords,
  checkSensitiveWords,
  clearSensitiveWordsCache,
  deleteSensitiveWord,
  exportSensitiveWords,
  getSensitiveWordList,
  importSensitiveWords,
  toggleSensitiveWordStatus,
  updateSensitiveWord
} from '@/api/sensitiveWords'

defineOptions({ name: 'SensitiveWords' })

const loading = ref(false)
const submitLoading = ref(false)
const batchLoading = ref(false)
const importLoading = ref(false)
const checkLoading = ref(false)
const total = ref(0)
const wordList = ref([])
const selectedIds = ref([])
const showFormDialog = ref(false)
const editId = ref(null)
const showBatchDialog = ref(false)
const batchWords = ref('')
const showImportDialog = ref(false)
const uploadAction = ref('')
const showCheckDialog = ref(false)
const checkContent = ref('')
const checkResult = ref(null)
const searchFormRef = ref(null)
const wordFormRef = ref(null)
const uploadRef = ref(null)

const searchForm = reactive({
  word: '',
  status: '',
  page: 1,
  limit: 20
})
const wordForm = reactive({
  word: '',
  remark: '',
  status: 1
})

const wordRules = {
  word: [
    { required: true, message: '请输入敏感词', trigger: 'blur' },
    { max: 100, message: '长度不超过100个字符', trigger: 'blur' }
  ]
}

const dialogTitle = computed(() => (editId.value ? '编辑敏感词' : '添加敏感词'))

function resetWordForm() {
  Object.assign(wordForm, { word: '', remark: '', status: 1 })
}

async function getList() {
  loading.value = true
  try {
    const res = await getSensitiveWordList(searchForm)
    wordList.value = res.data?.list || []
    total.value = res.data?.total || 0
  } catch (error) {
    ElMessage.error(error.message || '获取列表失败')
  } finally {
    loading.value = false
  }
}

function handleSearch() {
  searchForm.page = 1
  getList()
}

function resetSearch() {
  searchForm.word = ''
  searchForm.status = ''
  searchForm.page = 1
  getList()
}

function handleSizeChange(size) {
  searchForm.limit = size
  searchForm.page = 1
  getList()
}

function handlePageChange(page) {
  searchForm.page = page
  getList()
}

function handleSelectionChange(rows) {
  selectedIds.value = rows.map(row => row.id)
}

function handleAdd() {
  editId.value = null
  resetWordForm()
  showFormDialog.value = true
}

function handleEdit(row) {
  editId.value = row.id
  Object.assign(wordForm, {
    word: row.word,
    remark: row.remark || '',
    status: row.status
  })
  showFormDialog.value = true
}

function resetForm() {
  editId.value = null
  resetWordForm()
  wordFormRef.value?.clearValidate()
}

async function handleSubmit() {
  if (!wordFormRef.value) return
  const valid = await wordFormRef.value.validate().catch(() => false)
  if (!valid) return

  submitLoading.value = true
  try {
    const payload = { ...wordForm }
    const res = editId.value
      ? await updateSensitiveWord(editId.value, payload)
      : await addSensitiveWord(payload)
    ElMessage.success(res.message || '操作成功')
    showFormDialog.value = false
    getList()
  } catch (error) {
    ElMessage.error(error.message || '操作失败')
  } finally {
    submitLoading.value = false
  }
}

async function handleDelete(row) {
  try {
    await ElMessageBox.confirm(`确定要删除敏感词「${row.word}」吗？`, '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
    const res = await deleteSensitiveWord(row.id)
    ElMessage.success(res.message || '删除成功')
    getList()
  } catch (error) {
    if (error !== 'cancel' && error !== 'close') {
      ElMessage.error(error.message || '删除失败')
    }
  }
}

async function handleBatchDelete() {
  if (!selectedIds.value.length) return
  const selectedCount = selectedIds.value.length
  const selectedSnapshot = [...selectedIds.value]
  try {
    await ElMessageBox.confirm(`确定要删除选中的 ${selectedCount} 个敏感词吗？`, '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
    const res = await batchDeleteSensitiveWords(selectedSnapshot)
    ElMessage.success(res.message || '批量删除成功')
    clearSelectedIds()
    getList()
  } catch (error) {
    if (error !== 'cancel' && error !== 'close') {
      ElMessage.error(error.message || '批量删除失败')
    }
  }
}

async function handleStatusChange(row) {
  const nextStatus = row.status
  const prevStatus = nextStatus === 1 ? 0 : 1
  try {
    const res = await toggleSensitiveWordStatus(row.id)
    ElMessage.success(res.message || '状态更新成功')
  } catch (error) {
    rollbackStatus(row, prevStatus)
    ElMessage.error(error.message || '状态更新失败')
  }
}

async function handleBatchAdd() {
  const lines = batchWords.value.split('\n').map(text => text.trim()).filter(Boolean)
  if (!lines.length) {
    ElMessage.warning('请输入要添加的敏感词')
    return
  }

  batchLoading.value = true
  try {
    const res = await batchAddSensitiveWords(lines)
    const { success = 0, failed = 0 } = res.data || {}
    ElMessage.success(`添加完成：成功 ${success} 个，跳过 ${failed} 个`)
    showBatchDialog.value = false
    resetBatchWords()
    getList()
  } catch (error) {
    ElMessage.error(error.message || '批量添加失败')
  } finally {
    batchLoading.value = false
  }
}

function clearSelectedIds() {
  selectedIds.value.splice(0, selectedIds.value.length)
}

function resetBatchWords() {
  batchWords.value = ''
}

function rollbackStatus(row, status) {
  row.status = status
}

async function handleExport() {
  try {
    const res = await exportSensitiveWords({
      word: searchForm.word,
      status: searchForm.status
    })
    const list = res.data || []
    if (!list.length) {
      ElMessage.warning('暂无可导出数据')
      return
    }

    const content = list.map(item => item.word).join('\n')
    const blob = new Blob([content], { type: 'text/plain;charset=utf-8' })
    const link = document.createElement('a')
    const url = URL.createObjectURL(blob)
    link.href = url
    link.download = `sensitive_words_${Date.now()}.txt`
    link.click()
    URL.revokeObjectURL(url)
    ElMessage.success(`已导出 ${list.length} 个敏感词`)
  } catch (error) {
    ElMessage.error(error.message || '导出失败')
  }
}

async function handleImportUpload(options) {
  const formData = new FormData()
  formData.append('file', options.file)
  importLoading.value = true
  try {
    const res = await importSensitiveWords(formData)
    const { success = 0, failed = 0, errors = [] } = res.data || {}
    ElMessage.success(`导入完成：成功 ${success} 个，失败 ${failed} 个`)
    if (errors.length) {
      console.warn('导入错误详情：', errors)
    }
    showImportDialog.value = false
    uploadRef.value?.clearFiles()
    if (options.onSuccess) {
      options.onSuccess(res)
    }
    getList()
  } catch (error) {
    if (options.onError) {
      options.onError(error)
    }
    ElMessage.error(error.message || '导入失败')
  } finally {
    importLoading.value = false
  }
}

function handleImportSubmit() {
  const upload = uploadRef.value
  if (!upload || !upload.uploadFiles.length) {
    ElMessage.warning('请先选择要导入的文件')
    return
  }
  upload.submit()
}

async function handleClearCache() {
  try {
    await ElMessageBox.confirm('确定要清除敏感词缓存吗？清除后系统将重新加载最新词库。', '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
    const res = await clearSensitiveWordsCache()
    ElMessage.success(res.message || '缓存清除成功')
  } catch (error) {
    if (error !== 'cancel' && error !== 'close') {
      ElMessage.error(error.message || '清除失败')
    }
  }
}

async function handleCheck() {
  if (!checkContent.value.trim()) {
    ElMessage.warning('请输入待检测文本')
    return
  }
  checkLoading.value = true
  try {
    const res = await checkSensitiveWords(checkContent.value)
    checkResult.value = res.data || {}
  } catch (error) {
    ElMessage.error(error.message || '检测失败')
  } finally {
    checkLoading.value = false
  }
}

onBeforeMount(() => {
  getList()
})
</script>

<style lang="scss" scoped>
.sensitive-words-curator-page {
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

.page-curator-header__actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  flex-wrap: wrap;
  flex-shrink: 0;
}

.page-curator-title {
  margin: 0 0 8px;
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--ds-on-surface);
  letter-spacing: 0.02em;
}

.page-curator-subtitle {
  margin: 0;
  font-size: 0.875rem;
  color: var(--ds-on-surface-variant);
  line-height: 1.5;
  max-width: 520px;
}

.sensitive-words-work-surface {
  background: var(--ds-surface-card);
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
}

.search-form-wrap {
  &__main {
    flex: 1;
    min-width: 0;
  }

  .search-input {
    width: 220px;
  }

  .status-select {
    width: 130px;
  }
}

:deep(.btn-curator-primary.el-button--primary) {
  background: var(--ds-primary);
  border-color: var(--ds-primary);
  color: var(--ds-on-primary);

  &:hover, &:focus {
    background: var(--ds-primary-hover);
    border-color: var(--ds-primary-hover);
  }
}

:deep(.btn-curator-reset) {
  background: var(--ds-surface-container-highest);
  border-color: var(--ds-outline);
  color: var(--ds-on-surface);

  &:hover, &:focus {
    background: var(--ds-surface-container-high);
    border-color: var(--ds-outline-variant);
  }
}

.header-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  justify-content: flex-end;
}

@media screen and (max-width: 1200px) {
  .page-curator-header {
    flex-wrap: wrap;
  }

  .page-curator-header__actions {
    width: 100%;
  }
}

:deep(.el-table) {
  font-size: 14px;
}

.word-tag {
  font-size: 13px;
  font-weight: 500;
}

.remark-text {
  font-size: 13px;
  color: #606266;
}

:deep(.el-button--text) {
  padding: 0;
  margin: 0 6px;
  font-size: 13px;

  &:first-child {
    margin-left: 0;
  }

  &.delete-btn {
    color: #f56c6c;

    &:hover {
      color: #f78989;
    }
  }
}

.pagination-wrap {
  padding: 16px 0 0;
  display: flex;
  justify-content: flex-end;
  border-top: 1px solid var(--ds-outline-variant);
}

// 批量添加
.batch-tips {
  margin-bottom: 12px;
}

.batch-textarea {
  width: 100%;
}

// 导入
.import-wrap {
  .import-alert {
    margin-bottom: 16px;
  }
}

// 检测结果
.check-textarea {
  width: 100%;
  margin-bottom: 4px;
}

.check-result {
  margin-top: 12px;

  .result-danger {
    .hit-words {
      margin-top: 10px;
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }

    .hit-tag {
      font-weight: 500;
    }
  }
}
</style>
