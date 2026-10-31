<template>
  <div class="category-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">直播分类</h1>
        <p class="page-curator-subtitle">
          管理直播间的分类信息，支持添加、编辑、删除及状态切换。
        </p>
      </div>
      <el-button
        type="primary"
        class="btn-curator-primary"
        icon="el-icon-plus"
        @click="handleAdd"
      >
        添加分类
      </el-button>
    </header>

    <div class="category-work-surface">
      <!-- 搜索区域 -->
      <el-form
        ref="searchFormRef"
        :model="searchForm"
        inline
        class="search-form category-search-form"
        @submit.prevent
      >
        <el-form-item label="分类名称">
          <el-input
            v-model="searchForm.name"
            clearable
            placeholder="请输入分类名称"
            class="search-input"
            prefix-icon="el-icon-search"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="状态">
          <el-select
            v-model="searchForm.status"
            placeholder="全部"
            clearable
            class="search-select-sm"
          >
            <el-option label="启用" :value="1" />
            <el-option label="禁用" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button
            type="primary"
            class="btn-curator-primary"
            icon="el-icon-search"
            @click="handleSearch"
          >
            查询
          </el-button>
          <el-button
            class="btn-curator-reset"
            icon="el-icon-refresh"
            @click="handleReset"
          >
            重置
          </el-button>
        </el-form-item>
      </el-form>

      <!-- 表格区域 -->
      <el-table
        v-loading="loading"
        :data="list"
        class="category-curator-table"
      >
        <el-table-column prop="id" label="ID" width="70" align="center" />
        <el-table-column prop="name" label="分类名称" min-width="150" show-overflow-tooltip />
        <el-table-column prop="sort" label="排序" width="80" align="center" />
        <el-table-column label="状态" width="100" align="center">
          <template #default="scope">
            <el-switch
              v-model="scope.row.status"
              :active-value="1"
              :inactive-value="0"
              @change="handleToggleStatus(scope.row)"
            />
          </template>
        </el-table-column>
        <el-table-column prop="create_time" label="创建时间" width="170" align="center" />
        <el-table-column label="操作" width="160" fixed="right" align="center">
          <template #default="scope">
            <a @click="handleEdit(scope.row)">编辑</a>
            <el-divider direction="vertical" />
            <a class="danger-link" @click="handleDelete(scope.row.id)">删除</a>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrap">
        <el-pagination
          class="category-curator-pagination"
          :current-page="page"
          :page-sizes="[10, 20, 50, 100]"
          :page-size="limit"
          layout="total, sizes, prev, pager, next, jumper"
          :total="total"
          @size-change="handleSizeChange"
          @current-change="handleCurrentChange"
        />
      </div>
    </div>

    <!-- 新增/编辑弹窗 -->
    <el-dialog
      v-model="dialogVisible"
      :title="dialogTitle"
      width="480px"
      :close-on-click-modal="false"
      @close="handleDialogClose"
    >
      <el-form
        ref="dialogFormRef"
        :model="dialogForm"
        :rules="dialogRules"
        label-width="90px"
      >
        <el-form-item label="分类名称" prop="name">
          <el-input v-model="dialogForm.name" placeholder="请输入分类名称" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="dialogForm.sort" :min="0" :max="9999" />
          <span class="tips-text">数值越大越靠前</span>
        </el-form-item>
        <el-form-item label="状态">
          <el-switch
            v-model="dialogForm.status"
            :active-value="1"
            :inactive-value="0"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <span>
          <el-button @click="dialogVisible = false">取 消</el-button>
          <el-button type="primary" :loading="submitLoading" @click="handleSubmit">确 定</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, onBeforeMount, reactive, ref } from 'vue'
import {
  createCategory as createCategoryApi,
  deleteCategory as deleteCategoryApi,
  getCategoryList as getCategoryListApi,
  toggleCategoryStatus as toggleCategoryStatusApi,
  updateCategory as updateCategoryApi
} from '@/api/live'

defineOptions({
  name: 'LiveCategoryList'
})

const { proxy } = getCurrentInstance() || {}

const searchFormRef = ref(null)
const dialogFormRef = ref(null)
const loading = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(20)
const dialogVisible = ref(false)
const submitLoading = ref(false)
const editId = ref(0)
const searchForm = reactive({
  name: '',
  status: ''
})
const dialogForm = reactive({
  name: '',
  sort: 0,
  status: 1
})
const dialogRules = {
  name: [{ required: true, message: '请输入分类名称', trigger: 'blur' }]
}

const DEFAULT_SEARCH_FORM = {
  name: '',
  status: ''
}

const DEFAULT_DIALOG_FORM = {
  name: '',
  sort: 0,
  status: 1
}

const dialogTitle = computed(() => (editId.value ? '编辑分类' : '添加分类'))

onBeforeMount(() => {
  fetchList()
})

async function fetchList() {
  loading.value = true
  try {
    const res = await getCategoryListApi({
      page: page.value,
      limit: limit.value,
      ...searchForm
    })
    list.value = res.data.list || res.data || []
    total.value = res.data.total || 0
  } finally {
    loading.value = false
  }
}

function handleSearch() {
  page.value = 1
  fetchList()
}

function handleReset() {
  searchFormRef.value?.resetFields?.()
  Object.assign(searchForm, DEFAULT_SEARCH_FORM)
  handleSearch()
}

function handleAdd() {
  editId.value = 0
  Object.assign(dialogForm, DEFAULT_DIALOG_FORM)
  dialogVisible.value = true
}

function handleEdit(row) {
  editId.value = row.id
  Object.assign(dialogForm, {
    name: row.name,
    sort: row.sort || 0,
    status: row.status
  })
  dialogVisible.value = true
}

async function handleSubmit() {
  const valid = await Promise.resolve(dialogFormRef.value?.validate?.()).catch(() => false)
  if (!valid) return

  submitLoading.value = true
  try {
    const api = editId.value
      ? updateCategoryApi(editId.value, { ...dialogForm })
      : createCategoryApi({ ...dialogForm })
    await api
    proxy?.$message?.success(editId.value ? '修改成功' : '添加成功')
    dialogVisible.value = false
    await fetchList()
  } finally {
    submitLoading.value = false
  }
}

async function handleDelete(id) {
  if (!proxy?.$confirm) return
  const confirmed = await proxy.$confirm('确定要删除该分类吗？', '提示', { type: 'warning' })
    .then(() => true)
    .catch(() => false)
  if (!confirmed) return

  await deleteCategoryApi(id)
  proxy?.$message?.success('删除成功')
  await fetchList()
}

async function handleToggleStatus(row) {
  const currentStatus = row.status
  try {
    await toggleCategoryStatusApi(row.id)
    proxy?.$message?.success('状态已更新')
    await fetchList()
  } catch {
    Object.assign(row, {
      status: currentStatus === 1 ? 0 : 1
    })
  }
}

function handleDialogClose() {
  dialogFormRef.value?.resetFields?.()
}

function handleSizeChange(val) {
  limit.value = val
  page.value = 1
  fetchList()
}

function handleCurrentChange(val) {
  page.value = val
  fetchList()
}
</script>

<style lang="scss" scoped>
/* material/stitch/DESIGN.md */
.category-curator-page {
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
  color: var(--curator-on-surface);
  letter-spacing: 0.02em;
}

.page-curator-subtitle {
  margin: 0;
  font-size: 0.875rem;
  color: var(--curator-on-surface-variant);
  line-height: 1.5;
  max-width: 520px;
}

.category-work-surface {
  background: var(--curator-surface-card);
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
}

:deep(.btn-curator-primary.el-button--primary) {
  border: none;
  border-radius: 6px;
  color: var(--curator-on-primary);
  font-weight: 500;
  padding-left: 18px;
  padding-right: 18px;
  box-shadow: 0 2px 8px rgba(0, 87, 194, 0.2);
}

:deep(.btn-curator-reset) {
  border-radius: 6px;
  background: var(--curator-surface-card);
  color: var(--curator-primary);
  border: 1px solid var(--curator-primary);

  &:hover,
  &:focus {
    background: rgba(0, 87, 194, 0.06);
    color: var(--curator-primary-container);
    border-color: var(--curator-primary-container);
  }
}

.category-search-form {
  margin-bottom: 8px;
}

.category-curator-table {
  margin-top: 8px;

  :deep(.el-table__header th) {
    color: var(--curator-on-surface-variant);
    font-weight: 500;
    background: var(--curator-surface-low);
  }

  :deep(.el-table__row:hover > td) {
    background: var(--curator-surface-low) !important;
  }
}

.pagination-wrap {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}

.category-curator-pagination {
  :deep(.el-pager li.active) {
    background: var(--curator-primary);
    color: var(--curator-on-primary);
    border-radius: 4px;
  }

  :deep(.btn-next),
  :deep(.btn-prev),
  :deep(.el-pager li) {
    &:hover {
      color: var(--curator-primary);
    }
  }
}

.search-input {
  width: 200px;
}

.search-select-sm {
  width: 140px;
}

.danger-link {
  color: var(--dashboard-danger);
}

.tips-text {
  margin-left: 10px;
  color: var(--curator-on-surface-variant);
  font-size: 12px;
}

a {
  color: var(--curator-primary);
  cursor: pointer;
  font-size: 13px;

  &:hover {
    color: var(--curator-primary-container);
  }
}
</style>
