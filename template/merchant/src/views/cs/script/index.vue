<template>
  <div class="cs-page">
    <div class="split-layout">
      <el-card shadow="never" class="left-pane">
        <div class="pane-header">
          <span class="pane-title">话术分类</span>
          <div class="pane-action" @click="openCreateCategory">
            <LegacyIcon name="el-icon-plus" />
            <span>新增</span>
          </div>
        </div>
        <div v-loading="categoryLoading" class="category-list">
          <div
            :class="['category-item', { active: Number(selectedCategoryId) === 0 }]"
            @click="handleSelectCategory(0)"
          >
            <div class="category-main">
              <span class="category-icon">
                <LegacyIcon name="el-icon-folder" />
              </span>
              <div class="category-content">
                <span class="name">全部话术</span>
              </div>
            </div>
            <el-dropdown
              class="category-more"
              trigger="click"
              @command="handleCategoryRootMenuCommand"
            >
              <span class="category-more-trigger" @click.stop>
                <LegacyIcon name="el-icon-more" />
              </span>
              <template #dropdown>
                <el-dropdown-menu>
                  <el-dropdown-item command="create">新增分类</el-dropdown-item>
                  <el-dropdown-item command="refresh">刷新列表</el-dropdown-item>
                </el-dropdown-menu>
              </template>
            </el-dropdown>
          </div>
          <div
            v-for="item in categoryOptions"
            :key="item.id"
            :class="['category-item', { active: Number(selectedCategoryId) === Number(item.id) }]"
            @click="handleSelectCategory(item.id)"
          >
            <div class="category-main">
              <span class="category-icon">
                <LegacyIcon name="el-icon-folder" />
              </span>
              <div class="category-content">
                <span class="name">{{ item.name }}</span>
              </div>
            </div>
            <el-dropdown
              class="category-more"
              trigger="click"
              @command="(command) => handleCategoryMenuCommand(item, command)"
            >
              <span class="category-more-trigger" @click.stop>
                <LegacyIcon name="el-icon-more" />
              </span>
              <template #dropdown>
                <el-dropdown-menu>
                  <el-dropdown-item command="edit">编辑分类</el-dropdown-item>
                  <el-dropdown-item command="delete" divided>删除</el-dropdown-item>
                </el-dropdown-menu>
              </template>
            </el-dropdown>
          </div>
          <el-empty v-if="!categoryLoading && categoryOptions.length === 0" :image-size="56" description="暂无分类" />
        </div>
      </el-card>

      <el-card shadow="never" class="right-pane">
        <el-form :inline="true" :model="query" class="query-form" @submit.prevent>
          <el-form-item label="关键词">
            <el-input v-model="query.keyword" clearable placeholder="标题关键词" class="w220" @keyup.enter="fetchList" />
          </el-form-item>
          <el-form-item>
            <el-button type="primary" :icon="Search" @click="fetchList">查询</el-button>
            <el-button :icon="Refresh" @click="resetQuery">重置</el-button>
          </el-form-item>
        </el-form>

        <div class="action-row">
          <el-button type="primary" :icon="Plus" @click="openCreate">新增话术</el-button>
        </div>

        <el-table v-loading="loading" :data="list" class="mt16">
          <el-table-column prop="id" label="ID" width="80" />
          <el-table-column label="分类" min-width="120">
            <template #default="{ row }">
              <span>{{ getCategoryName(row.category_id) }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="title" label="标题" min-width="200" show-overflow-tooltip />
          <el-table-column prop="use_count" label="使用次数" width="100" />
          <el-table-column prop="status" label="状态" width="110">
            <template #default="{ row }">
              <el-switch
                v-model="row.status"
                :active-value="1"
                :inactive-value="0"
                :loading="Boolean(statusLoadingMap[Number(row.id)])"
                @change="handleStatusToggle(row)"
              />
            </template>
          </el-table-column>
          <el-table-column prop="sort" label="排序" width="90" />
          <el-table-column prop="create_time" label="创建时间" width="180" />
          <el-table-column label="操作" width="120" fixed="right">
            <template #default="{ row }">
              <div class="table-ops">
                <a class="op-link" @click="openEdit(row)">编辑</a>
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
            @size-change="handleSizeChange"
            @current-change="handleCurrentChange"
          />
        </div>
      </el-card>
    </div>

    <el-dialog v-model="showDialog" :title="form.id ? '编辑话术' : '新增话术'" width="640px">
      <el-form :model="form" label-width="90px">
        <el-form-item label="分类" required>
          <el-select v-model="form.category_id" placeholder="请选择分类" class="w220">
            <el-option v-for="item in categoryOptions" :key="item.id" :label="item.name" :value="item.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="标题">
          <el-input v-model="form.title" maxlength="120" show-word-limit />
        </el-form-item>
        <el-form-item label="内容">
          <el-input v-model="form.content" type="textarea" :rows="6" />
        </el-form-item>
        <el-form-item label="状态">
          <el-radio-group v-model="form.status">
            <el-radio :label="1">启用</el-radio>
            <el-radio :label="0">禁用</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.sort" :min="0" class="w220" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showDialog = false">取消</el-button>
        <el-button type="primary" @click="submitForm">确定</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="showCategoryFormDialog" :title="categoryForm.id ? '编辑话术分类' : '新增话术分类'" width="420px">
      <el-form :model="categoryForm" label-width="80px">
        <el-form-item label="分类名称" required>
          <el-input v-model="categoryForm.name" maxlength="50" show-word-limit />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="categoryForm.sort" :min="0" class="w220" />
        </el-form-item>
        <el-form-item label="状态">
          <el-radio-group v-model="categoryForm.status">
            <el-radio :label="1">启用</el-radio>
            <el-radio :label="0">禁用</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCategoryFormDialog = false">取消</el-button>
        <el-button type="primary" @click="submitCategory">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Refresh, Search } from '@element-plus/icons-vue'
import {
  createCsScript,
  createCsScriptCategory,
  deleteCsScript,
  deleteCsScriptCategory,
  getCsScriptCategoryList,
  getCsScriptList,
  updateCsScript,
  updateCsScriptCategory,
  updateCsScriptStatus
} from '@/api/cs'

defineOptions({
  name: 'MerchantCsScriptIndex'
})

const defaultForm = () => ({
  id: 0,
  category_id: 0,
  title: '',
  content: '',
  status: 1,
  sort: 0
})

const defaultCategoryForm = () => ({
  id: 0,
  name: '',
  sort: 0,
  status: 1,
  pid: 0,
  path: []
})

const loading = ref(false)
const categoryLoading = ref(false)
const list = ref([])
const total = ref(0)
const selectedCategoryId = ref(0)
const showDialog = ref(false)
const showCategoryFormDialog = ref(false)
const categoryOptions = ref([])
const query = reactive({
  keyword: '',
  page: 1,
  limit: 20
})
const statusLoadingMap = reactive({})
const form = ref(defaultForm())
const categoryForm = ref(defaultCategoryForm())

onMounted(async() => {
  await fetchCategoryOptions()
  fetchList()
})

function unwrapResponse(response) {
  const payload = response && response.data ? response.data : (response || {})
  return payload.data || payload
}
async function fetchCategoryOptions() {
  categoryLoading.value = true
  try {
    const response = await getCsScriptCategoryList({ status: '' })
    const data = unwrapResponse(response)
    const list = Array.isArray(data) ? data : (data.list || [])
    categoryOptions.value = list
  } finally {
    categoryLoading.value = false
  }
}
async function fetchList() {
  loading.value = true
  try {
    const response = await getCsScriptList({
      keyword: query.keyword,
      category_id: Number(selectedCategoryId.value || 0),
      page: query.page,
      limit: query.limit
    })
    const data = unwrapResponse(response)
    list.value = data.list || []
    total.value = Number(data.total || 0)
  } finally {
    loading.value = false
  }
}
function getCategoryName(categoryId) {
  const matched = categoryOptions.value.find(item => Number(item.id) === Number(categoryId))
  return matched ? matched.name : '未分类'
}
function handleSelectCategory(categoryId) {
  selectedCategoryId.value = Number(categoryId || 0)
  query.page = 1
  fetchList()
}
function resetQuery() {
  Object.assign(query, { keyword: '', page: 1, limit: 20 })
  fetchList()
}
function handleSizeChange(size) {
  query.limit = size
  fetchList()
}
function handleCurrentChange(page) {
  query.page = page
  fetchList()
}
function openCreate() {
  form.value = defaultForm()
  const firstCategory = categoryOptions.value[0]
  if (Number(selectedCategoryId.value) > 0) {
    form.value.category_id = Number(selectedCategoryId.value)
  } else if (firstCategory) {
    form.value.category_id = Number(firstCategory.id)
  }
  showDialog.value = true
}
function openEdit(row) {
  form.value = {
    id: row.id,
    category_id: Number(row.category_id || 0),
    title: row.title || '',
    content: row.content || '',
    status: Number(row.status || 0),
    sort: Number(row.sort || 0)
  }
  showDialog.value = true
}
async function submitForm() {
  if (!form.value.title || !form.value.content) {
    ElMessage.warning('请填写标题和内容')
    return
  }
  if (Number(form.value.category_id) <= 0) {
    ElMessage.warning('请选择话术分类')
    return
  }

  const payload = {
    category_id: Number(form.value.category_id),
    title: form.value.title,
    content: form.value.content,
    status: form.value.status,
    sort: form.value.sort
  }

  if (form.value.id) {
    await updateCsScript(form.value.id, payload)
    ElMessage.success('修改成功')
  } else {
    await createCsScript(payload)
    ElMessage.success('新增成功')
  }

  showDialog.value = false
  fetchList()
}
function openCreateCategory() {
  categoryForm.value = defaultCategoryForm()
  showCategoryFormDialog.value = true
}
function handleCategoryRootMenuCommand(command) {
  if (command === 'create') {
    openCreateCategory()
    return
  }
  if (command === 'refresh') {
    fetchCategoryOptions()
  }
}
function handleCategoryMenuCommand(row, command) {
  if (command === 'edit') {
    openEditCategory(row)
    return
  }
  if (command === 'delete') {
    handleDeleteCategory(row)
  }
}
function openEditCategory(row) {
  categoryForm.value = {
    id: Number(row.id || 0),
    name: row.name || '',
    sort: Number(row.sort || 0),
    status: Number(row.status || 0),
    pid: Number(row.pid || 0),
    path: Array.isArray(row.path) ? row.path : []
  }
  showCategoryFormDialog.value = true
}
async function submitCategory() {
  if (!categoryForm.value.name) {
    ElMessage.warning('请输入分类名称')
    return
  }

  const payload = {
    name: categoryForm.value.name,
    sort: Number(categoryForm.value.sort || 0),
    status: Number(categoryForm.value.status || 0),
    pid: Number(categoryForm.value.pid || 0),
    path: categoryForm.value.path || []
  }

  if (categoryForm.value.id) {
    await updateCsScriptCategory(categoryForm.value.id, payload)
    ElMessage.success('分类修改成功')
  } else {
    await createCsScriptCategory(payload)
    ElMessage.success('分类新增成功')
  }

  showCategoryFormDialog.value = false
  await fetchCategoryOptions()
  if (Number(selectedCategoryId.value) > 0 && !categoryOptions.value.find(item => Number(item.id) === Number(selectedCategoryId.value))) {
    selectedCategoryId.value = 0
  }
  fetchList()
}
async function handleDeleteCategory(row) {
  await ElMessageBox.confirm('确定删除该分类吗？', '提示', { type: 'warning' })
  await deleteCsScriptCategory(row.id)
  ElMessage.success('分类删除成功')
  await fetchCategoryOptions()
  if (Number(selectedCategoryId.value) === Number(row.id)) {
    selectedCategoryId.value = 0
  }
  fetchList()
}
async function handleDelete(row) {
  await ElMessageBox.confirm('确定删除该话术吗？', '提示', { type: 'warning' })
  await deleteCsScript(row.id)
  ElMessage.success('删除成功')
  fetchList()
}
function handleStatusToggle(row) {
  const id = Number(row.id || 0)
  const nextStatus = Number(row.status || 0)
  const previousStatus = nextStatus === 1 ? 0 : 1

  if (id <= 0) {
    row.status = previousStatus
    ElMessage.error('话术ID无效')
    return
  }

  statusLoadingMap[id] = true

  updateCsScriptStatus({
    id,
    status: nextStatus
  })
    .then(() => {
      ElMessage.success(nextStatus === 1 ? '已启用' : '已禁用')
    })
    .catch(error => {
      row.status = previousStatus
      ElMessage.error((error && error.msg) || (error && error.message) || '状态更新失败')
    })
    .finally(() => {
      delete statusLoadingMap[id]
    })
}
</script>

<style lang="scss" scoped>
.cs-page {
  min-height: calc(100vh - 124px);
  padding: 0;
  border-radius: 4px;
  background: var(--prev-bg-white);

  .split-layout {
    display: flex;
    align-items: stretch;
    min-height: calc(100vh - 124px);
  }

  .left-pane {
    width: 260px;
    min-width: 260px;
    flex-shrink: 0;
    border: 0;
    border-right: 1px solid var(--prev-border-color-light);
    border-radius: 0;
    background: transparent;

    :deep(.el-card__body) {
      padding: 20px 12px 20px 16px;
    }
  }

  .right-pane {
    flex: 1;
    min-width: 0;
    margin-left: 20px;
    margin-top: 20px;
    border: 0;
    border-radius: 0;
    background: transparent;

    :deep(.el-card__body) {
      padding: 0 13px 10px 0;
    }
  }

  .pane-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 18px;
    padding: 0 8px;
  }

  .pane-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--ds-on-surface);
  }

  .pane-action {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 0;
    color: var(--ds-primary);
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: color 0.2s ease;

    &:hover {
      color: var(--ds-primary-hover);
    }
  }

  .category-list {
    min-height: 560px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    overflow-x: hidden;
    overflow-y: auto;

    &::-webkit-scrollbar {
      display: none;
    }
  }

  .category-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    height: 40px;
    padding: 0 10px;
    border-radius: 2px;
    background: transparent;
    cursor: pointer;
    transition: background 0.2s ease, color 0.2s ease;

    &:hover {
      background: var(--ds-surface-low);
    }

    &.active {
      background: var(--ds-surface-low);
    }
  }

  .category-main {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
    min-width: 0;
  }

  .category-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 16px;
    height: 16px;
    color: var(--ds-primary);
    flex-shrink: 0;

    i {
      font-size: 16px;
      line-height: 1;
    }
  }

  .category-item.active .category-icon {
    color: var(--ds-primary);
  }

  .category-content {
    display: flex;
    align-items: center;
    flex: 1;
    min-width: 0;
  }

  .category-item .name {
    display: inline-block;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: var(--ds-on-surface-variant);
    font-size: 14px;
    font-weight: 400;
    line-height: 20px;
  }

  .category-item.active .name {
    color: var(--ds-primary);
  }

  .query-form {
    margin-bottom: 16px;

    :deep(.el-form-item) {
      margin-bottom: 12px;
    }
  }

  .action-row {
    margin: 0 0 16px;
  }

  .category-more {
    display: inline-flex;
    align-items: center;
    flex-shrink: 0;
  }

  .category-more-trigger {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 2px;
    color: var(--ds-on-surface-variant);
    cursor: pointer;
    transition: color 0.2s ease;

    i {
      font-size: 18px;
      line-height: 1;
    }

    &:hover {
      color: var(--ds-primary);
    }
  }

  .mt16 {
    margin-top: 0;
  }

  .w220 {
    width: 220px;
  }

  .table-ops {
    display: inline-flex;
    align-items: center;
    gap: 4px;
  }

  .op-link {
    font-size: 13px;
    color: var(--ds-primary);
    cursor: pointer;
    text-decoration: none;

    &:hover {
      color: var(--ds-primary-hover);
    }

    &.danger {
      color: var(--text-danger, var(--dashboard-danger));

      &:hover {
        color: var(--text-danger, var(--dashboard-danger));
      }
    }
  }

  .pagination-wrap {
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
  }
}

@media (max-width: 1200px) {
  .cs-page {
    .left-pane {
      width: 240px;
      min-width: 240px;
    }
  }
}

@media (max-width: 960px) {
  .cs-page {
    .split-layout {
      display: block;
      min-height: auto;
    }

    .left-pane {
      width: 100%;
      min-width: 0;
      margin-bottom: 16px;
      border-right: 0;
      border-bottom: 1px solid var(--prev-border-color-light);

      :deep(.el-card__body) {
        padding-right: 0;
      }
    }

    .right-pane {
      margin-left: 0;

      :deep(.el-card__body) {
        padding-right: 0;
      }
    }

    .category-list {
      min-height: auto;
    }
  }
}
</style>
