<template>
  <div class="city-area-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">地址管理</h1>
        <p class="page-curator-subtitle">管理平台省市区基础地址，当前精确到区县</p>
      </div>
      <el-button type="primary" class="btn-curator-primary" icon="el-icon-plus" @click="handleCreate">
        添加地址
      </el-button>
    </header>

    <div class="city-area-work-surface">
      <div class="search-form-wrap">
        <el-form ref="searchForm" :model="searchForm" inline @submit.prevent>
          <el-form-item label="地址名称：">
            <el-input
              v-model="searchForm.name"
              clearable
              placeholder="请输入省市区名称"
              class="form_content_width"
              @keyup.enter="handleSearch"
            />
          </el-form-item>
          <el-form-item label="层级：">
            <el-select v-model="searchForm.level" clearable placeholder="请选择" class="form_content_width" @change="handleSearch">
              <el-option :value="1" label="省" />
              <el-option :value="2" label="市" />
              <el-option :value="3" label="区县" />
            </el-select>
          </el-form-item>
          <el-form-item label="状态：">
            <el-select v-model="searchForm.status" clearable placeholder="请选择" class="form_content_width" @change="handleSearch">
              <el-option :value="1" label="启用" />
              <el-option :value="0" label="禁用" />
            </el-select>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" class="btn-curator-primary" @click="handleSearch">查询</el-button>
            <el-button class="btn-curator-reset" @click="handleReset">重置</el-button>
          </el-form-item>
        </el-form>
      </div>

      <el-table
        v-loading="loading"
        :data="tableList"
        row-key="id"
        :tree-props="{ children: 'children' }"
      >
        <el-table-column prop="name" label="地址名称" min-width="180" />
        <el-table-column prop="id" label="编码" min-width="110" />
        <el-table-column label="层级" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="getLevelTagType(row.level)">{{ getLevelText(row.level) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="parent_id" label="父级编码" min-width="110" />
        <el-table-column prop="sort" label="排序" width="90" align="center" />
        <el-table-column label="状态" width="140" align="center">
          <template #default="{ row }">
            <el-switch
              v-model="row.status"
              :active-value="1"
              :inactive-value="0"
              active-text="启用"
              inactive-text="禁用"
              @change="handleStatusChange(row)"
            />
          </template>
        </el-table-column>
        <el-table-column prop="update_time" label="更新时间" min-width="160" />
        <el-table-column label="操作" fixed="right" width="220" align="center">
          <template #default="{ row }">
            <a v-if="row.level < 3" class="table-action-primary" href="javascript:;" @click="handleCreateChild(row)">
              {{ getChildButtonText(row.level) }}
            </a>
            <el-divider v-if="row.level < 3" direction="vertical" />
            <a class="table-action-primary" href="javascript:;" @click="handleEdit(row)">编辑</a>
            <el-divider direction="vertical" />
            <a class="table-action-danger" href="javascript:;" @click="handleDelete(row)">删除</a>
          </template>
        </el-table-column>
      </el-table>

    </div>

    <el-dialog
      v-model="dialogVisible"
      :title="dialogTitle"
      :close-on-click-modal="false"
      width="560px"
      @closed="handleDialogClosed"
    >
      <el-form ref="formRef" :model="form" :rules="rules" label-width="100px" @submit.prevent>
        <el-form-item label="地址编码：" prop="id">
          <el-input-number v-model="form.id" :min="1" :disabled="isEditDialog" controls-position="right" />
        </el-form-item>
        <el-form-item label="地址名称：" prop="name">
          <el-input v-model="form.name" placeholder="请输入地址名称" />
        </el-form-item>
        <el-form-item label="排序：" prop="sort">
          <el-input-number v-model="form.sort" :min="0" :max="999999" controls-position="right" />
        </el-form-item>
        <el-form-item label="状态：" prop="status">
          <el-radio-group v-model="form.status">
            <el-radio :label="1">启用</el-radio>
            <el-radio :label="0">禁用</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="dialogVisible = false">取 消</el-button>
          <el-button type="primary" :loading="saving" @click="handleSubmit">提 交</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, onBeforeMount, reactive, ref } from 'vue'
import {
  createCityAreaApi,
  deleteCityAreaApi,
  getCityAreaTreeApi,
  setCityAreaStatusApi,
  updateCityAreaApi
} from '@/api/system'

defineOptions({
  name: 'CityArea'
})

const { proxy } = getCurrentInstance()
const message = proxy.$message
const modalSure = proxy.$modalSure

const loading = ref(false)
const saving = ref(false)
const dialogVisible = ref(false)
const dialogTitle = ref('添加地址')
const dialogType = ref('create')
const tableList = ref([])
const parentPath = ref([])
const formRef = ref(null)

const searchForm = reactive({
  name: '',
  level: '',
  status: ''
})

const form = reactive(getDefaultForm())

const rules = {
  id: [{ required: true, type: 'number', min: 1, message: '请输入地址编码', trigger: 'blur' }],
  name: [{ required: true, message: '请输入地址名称', trigger: 'blur' }],
  status: [{ required: true, type: 'number', message: '请选择状态', trigger: 'change' }]
}

const isEditDialog = computed(() => dialogType.value === 'edit')

function getDefaultForm() {
  return {
    id: 0,
    parent_id: 0,
    path: '',
    type: 1,
    name: '',
    level: 1,
    sort: 0,
    status: 1
  }
}

async function fetchTree() {
  loading.value = true
  try {
    const res = await getCityAreaTreeApi(searchForm)
    tableList.value = res.data || []
  } finally {
    loading.value = false
  }
}

function handleSearch() {
  fetchTree()
}

function handleReset() {
  Object.assign(searchForm, { name: '', level: '', status: '' })
  fetchTree()
}

function handleCreate() {
  openCreateDialog()
}

function handleCreateChild(row) {
  const level = Number(row.level || 1) + 1
  if (level > 3) return
  openCreateDialog({
    parent_id: Number(row.id || 0),
    level,
    type: level,
    path: row.path || `/${row.id}/`
  })
}

function openCreateDialog(extra = {}) {
  dialogType.value = 'create'
  Object.assign(form, getDefaultForm(), extra)
  dialogTitle.value = `添加${getLevelText(form.level)}`
  parentPath.value = form.parent_id ? resolveParentPath({ id: 0, path: form.path }) : []
  dialogVisible.value = true
}

function handleEdit(row) {
  dialogType.value = 'edit'
  dialogTitle.value = '编辑地址'
  Object.assign(form, {
    id: Number(row.id || 0),
    parent_id: Number(row.parent_id || 0),
    path: row.path || '',
    type: Number(row.type || row.level || 1),
    name: row.name || '',
    level: Number(row.level || 1),
    sort: Number(row.sort || 0),
    status: Number(row.status || 0)
  })
  parentPath.value = resolveParentPath(row)
  dialogVisible.value = true
}

function handleDialogClosed() {
  dialogType.value = 'create'
  dialogTitle.value = '添加地址'
  Object.assign(form, getDefaultForm())
  parentPath.value = []
  formRef.value && formRef.value.clearValidate()
}

async function handleSubmit() {
  if (!formRef.value) return
  formRef.value.validate(async valid => {
    if (!valid) return
    saving.value = true
    try {
      const data = buildSubmitData()
      if (isEditDialog.value) {
        await updateCityAreaApi(form.id, data)
        updateTreeNode(data)
      } else {
        await createCityAreaApi(data)
        appendTreeNode(data)
      }
      message.success('保存成功')
      dialogVisible.value = false
    } finally {
      saving.value = false
    }
  })
}

function buildSubmitData() {
  const path = buildPath()
  return {
    id: form.id,
    parent_id: form.level === 1 ? 0 : form.parent_id,
    path,
    type: form.level,
    name: form.name,
    level: form.level,
    sort: form.sort,
    status: form.status
  }
}

function buildPath() {
  if (form.level === 1) {
    return form.id ? `/${form.id}/` : ''
  }
  const currentParentPath = String(form.path || '').split('/').filter(Boolean)
  return currentParentPath.length ? `/${currentParentPath.join('/')}/${form.id}/` : `/${form.parent_id}/${form.id}/`
}

async function handleStatusChange(row) {
  const nextStatus = row.status
  const oldStatus = nextStatus === 1 ? 0 : 1
  try {
    await setCityAreaStatusApi(row.id, nextStatus)
    updateTreeNode({ id: row.id, status: nextStatus })
    message.success('状态修改成功')
  } catch (e) {
    Object.assign(row, { status: oldStatus })
  }
}

function handleDelete(row) {
  modalSure('删除后不可恢复，确认删除该地址吗？').then(async() => {
    await deleteCityAreaApi(row.id)
    removeTreeNode(row.id)
    message.success('删除成功')
  })
}

function resolveParentPath(row) {
  const path = String(row.path || '').split('/').filter(Boolean).map(item => Number(item))
  return path.filter(id => id !== Number(row.id || 0))
}

function appendTreeNode(data) {
  const node = normalizeNode({
    ...data,
    create_time: data.create_time || '',
    update_time: data.update_time || ''
  })
  if (node.parent_id === 0) {
    tableList.value.push(node)
    sortTree(tableList.value)
    return
  }
  const parent = findTreeNode(node.parent_id)
  if (!parent) return
  if (!Array.isArray(parent.children)) {
    parent.children = []
  }
  parent.children.push(node)
  sortTree(parent.children)
}

function updateTreeNode(data) {
  const current = findTreeNode(data.id)
  if (!current) return
  const node = normalizeNode({ ...current, ...data })
  if (Number(current.parent_id || 0) !== Number(node.parent_id || 0)) {
    removeTreeNode(node.id)
    appendTreeNode(node)
    return
  }
  Object.keys(node).forEach(key => {
    if (key !== 'children') {
      current[key] = node[key]
    }
  })
  if (current.children) {
    sortTree(current.children)
  }
}

function removeTreeNode(id, list) {
  const target = list || tableList.value
  const index = target.findIndex(item => Number(item.id) === Number(id))
  if (index > -1) {
    target.splice(index, 1)
    return true
  }
  return target.some(item => removeTreeNode(id, item.children || []))
}

function findTreeNode(id, list) {
  const source = list || tableList.value
  for (const item of source || []) {
    if (Number(item.id) === Number(id)) return item
    const child = findTreeNode(id, item.children || [])
    if (child) return child
  }
  return null
}

function normalizeNode(data) {
  return {
    ...data,
    id: Number(data.id || 0),
    parent_id: Number(data.parent_id || 0),
    type: Number(data.type || data.level || 1),
    level: Number(data.level || data.type || 1),
    sort: Number(data.sort || 0),
    status: Number(data.status || 0)
  }
}

function sortTree(list) {
  list.sort((a, b) => Number(a.sort || 0) - Number(b.sort || 0) || Number(a.id || 0) - Number(b.id || 0))
}

function getChildButtonText(level) {
  return Number(level) === 1 ? '添加市' : '添加区'
}

function getLevelText(level) {
  return { 1: '省', 2: '市', 3: '区县' }[Number(level)] || '-'
}

function getLevelTagType(level) {
  return { 1: 'success', 2: 'warning', 3: 'info' }[Number(level)] || ''
}

onBeforeMount(() => {
  fetchTree()
})
</script>

<style lang="scss" scoped>
.city-area-page {
  min-height: calc(100vh - 84px);
  background: var(--dashboard-bg);
}

.page-curator-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 18px;
  padding: 22px 24px;
  background: var(--dashboard-surface);
  border: 1px solid var(--dashboard-border);
  border-radius: 14px;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);

  &__text {
    display: flex;
    flex-direction: column;
  }
}

.page-curator-title {
  margin: 0;
  font-size: 22px;
  font-weight: 700;
  color: var(--dashboard-text-primary);
}

.page-curator-subtitle {
  margin: 8px 0 0;
  font-size: 13px;
  color: var(--dashboard-text-muted);
}

.city-area-work-surface {
  padding: 22px;
  background: var(--dashboard-surface);
  border: 1px solid var(--dashboard-border);
  border-radius: 14px;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
}

.search-form-wrap {
  margin-bottom: 18px;
}

.form_content_width {
  width: 190px;
}

:deep(.btn-curator-primary.el-button--primary) {
  border-color: var(--ds-primary);
  background: var(--ds-primary);
  color: var(--ds-on-primary);

  &:hover,
  &:focus {
    border-color: var(--ds-primary-hover);
    background: var(--ds-primary-hover);
    color: var(--ds-on-primary);
  }

  &:active {
    border-color: var(--ds-primary-hover);
    background: var(--ds-primary-hover);
  }
}

:deep(.el-button.btn-curator-reset) {
  border-color: var(--ds-outline);
  background: var(--dashboard-surface-muted);
  color: var(--ds-on-surface);

  &:hover,
  &:focus {
    border-color: var(--ds-outline-variant);
    background: var(--dashboard-accent-outline);
    color: var(--ds-on-surface);
  }
}

:deep(.el-form-item__label) {
  color: var(--dashboard-text-secondary);
}

:deep(.el-table) {
  color: var(--dashboard-text-primary);
  background: var(--dashboard-surface);
}

:deep(.el-dialog) {
  color: var(--dashboard-text-primary);
  background: var(--dashboard-surface);
}

:deep(.el-dialog .el-button--primary) {
  border-color: var(--ds-primary);
  background: var(--ds-primary);
  color: var(--ds-on-primary);

  &:hover,
  &:focus {
    border-color: var(--ds-primary-hover);
    background: var(--ds-primary-hover);
    color: var(--ds-on-primary);
  }
}

:deep(.table-action-primary) {
  color: var(--ds-primary);
  cursor: pointer;

  &:hover,
  &:focus {
    color: var(--ds-primary-hover);
  }
}

:deep(.table-action-danger) {
  color: var(--text-danger);
  cursor: pointer;

  &:hover,
  &:focus {
    color: var(--text-danger);
  }
}

.page {
  margin-top: 18px;
}

</style>
