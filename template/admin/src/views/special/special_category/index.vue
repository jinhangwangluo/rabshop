<template>
  <div class="category-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">专题分类</h1>
        <p class="page-curator-subtitle">
          管理专题分类，支持多级分类结构、排序调整与状态控制。
        </p>
      </div>
      <el-button
        type="primary"
        class="btn-curator-primary"
        icon="el-icon-plus"
        @click="handleAdd(0)"
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
        <el-form-item label="显示状态">
          <el-select
            v-model="searchForm.status"
            placeholder="全部状态"
            clearable
            class="search-select-sm"
          >
            <el-option label="显示" :value="1" />
            <el-option label="隐藏" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" class="btn-curator-primary" icon="el-icon-search" @click="handleSearch">查询</el-button>
          <el-button class="btn-curator-reset" icon="el-icon-refresh" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>

      <!-- 表格区域 -->
      <el-table
        v-loading="loading"
        :data="categoryList"
        row-key="id"
        :tree-props="{ children: 'children', hasChildren: 'hasChildren' }"
        default-expand-all
        class="category-curator-table"
      >
        <el-table-column prop="id" label="ID" width="80" align="center" />
        <el-table-column prop="label" label="分类名称" min-width="200">
          <template #default="scope">
            <span>{{ scope.row.label }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="sort" label="排序" width="100" align="center">
          <template #default="scope">
            <el-tag type="info">{{ scope.row.sort }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="scope">
            <el-switch
              v-model="scope.row.status"
              :active-value="1"
              :inactive-value="0"
              @change="handleStatusChange(scope.row)"
            />
          </template>
        </el-table-column>
        <el-table-column prop="create_time" label="创建时间" width="180" align="center" />
        <el-table-column label="操作" width="200" fixed="right" align="center">
          <template #default="scope">
            <a @click="handleEdit(scope.row)">编辑</a>
            <el-divider direction="vertical" />
            <a v-if="scope.row.pid === 0" @click="handleAdd(scope.row.id)">添加子分类</a>
            <el-divider v-if="scope.row.pid === 0" direction="vertical" />
            <a class="danger-link" @click="handleDelete(scope.row.id)">删除</a>
          </template>
        </el-table-column>
      </el-table>
    </div>

    <!-- 添加/编辑分类弹窗 -->
    <el-dialog
      v-model="dialogVisible"
      :title="dialogTitle"
      width="500px"
      :before-close="handleClose"
      destroy-on-close
    >
      <el-form
        ref="dialogFormRef"
        :model="dialogForm"
        :rules="dialogRules"
        label-width="90px"
        class="dialog-form"
      >
        <el-form-item label="上级分类" prop="pid">
          <el-cascader
            v-model="dialogForm.pid"
            :options="parentCategoryOptions"
            :props="{ checkStrictly: true, emitPath: false, value: 'id', label: 'label', children: 'children' }"
            placeholder="请选择上级分类（不选为一级）"
            class="form-width"
            clearable
            filterable
          />
          <div class="form-tip">最多支持二级分类</div>
        </el-form-item>
        <el-form-item label="分类名称" prop="name">
          <el-input
            v-model="dialogForm.name"
            placeholder="请输入分类名称"
            maxlength="30"
            show-word-limit
          />
        </el-form-item>
        <el-form-item label="分类图片" prop="image">
          <Upload
            :key="'image-' + uploadKey"
            v-model="dialogForm.image"
            :limit="1"
            @update:modelValue="handleImageChange"
          />
          <div class="form-tip">建议尺寸：400×300px，支持JPG/PNG格式</div>
        </el-form-item>
        <el-form-item label="分类大图" prop="banner_image">
          <Upload
            :key="'banner-' + uploadKey"
            v-model="dialogForm.banner_image"
            :limit="1"
            @update:modelValue="handleBannerImageChange"
          />
          <div class="form-tip">建议尺寸：554×210px（277×105px的2倍图），用于移动端分类页顶部大图</div>
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number
            v-model="dialogForm.sort"
            :min="0"
            :max="9999"
            placeholder="数值越大越靠前"
            style="width: 100%;"
          />
        </el-form-item>
        <el-form-item label="描述">
          <el-input
            v-model="dialogForm.description"
            type="textarea"
            placeholder="描述"
          />
        </el-form-item>
        <el-form-item label="显示状态">
          <el-radio-group v-model="dialogForm.status">
            <el-radio :label="1">显示</el-radio>
            <el-radio :label="0">隐藏</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="handleClose">取 消</el-button>
          <el-button type="primary" :loading="submitLoading" @click="submitForm">确 定</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, nextTick, onBeforeMount, reactive, ref } from 'vue'
import {
  getSpecialCategoryTree,
  specialCategoryAdd,
  specialCategorySave,
  specialCategoryDelete,
  specialCategorySetStatus
} from '@/api/special'
import Upload from '@/components/Upload/index'

defineOptions({
  name: 'SpecialCategory'
})

function normalizeImageValue(value) {
  if (Array.isArray(value)) {
    return String(value.filter(Boolean)[0] || '').trim()
  }
  if (value && typeof value === 'object') {
    return String(value.path || value.url || value.src || '').trim()
  }
  return String(value || '').trim()
}

function createSearchForm() {
  return {
    name: '',
    status: ''
  }
}

function createDialogForm(pid = 0) {
  return {
    id: 0,
    pid: pid || 0,
    name: '',
    image: '',
    banner_image: '',
    description: '',
    sort: 0,
    status: 1
  }
}

const { proxy } = getCurrentInstance()
const message = proxy.$message
const confirm = proxy.$confirm

const loading = ref(false)
const categoryList = ref([])
const searchFormRef = ref(null)
const searchForm = reactive(createSearchForm())
const dialogVisible = ref(false)
const dialogTitle = ref('添加分类')
const submitLoading = ref(false)
const uploadKey = ref(0)
const dialogFormRef = ref(null)
const dialogForm = reactive(createDialogForm())

const dialogRules = {
  pid: [
    {
      validator: (rule, value, callback) => {
        if (isInvalidParent(value)) {
          callback(new Error('最多支持二级分类'))
          return
        }
        callback()
      },
      trigger: 'change'
    }
  ],
  name: [
    { required: true, message: '请输入分类名称', trigger: 'blur' },
    { max: 30, message: '分类名称最多30个字符', trigger: 'blur' }
  ],
  image: [
    {
      validator: (...args) => {
        const value = args[1]
        const callback = args[2]
        if (!normalizeImageValue(value)) {
          callback(new Error('请上传分类图片'))
          return
        }
        callback()
      },
      trigger: 'change'
    }
  ]
}

const parentCategoryOptions = computed(() => buildParentCategoryOptions(categoryList.value))

function getCategoryList() {
  loading.value = true
  getSpecialCategoryTree({ ...searchForm }).then(res => {
    categoryList.value = res.data || []
  }).catch(() => {}).finally(() => {
    loading.value = false
  })
}

function handleSearch() {
  getCategoryList()
}

function handleReset() {
  Object.assign(searchForm, createSearchForm())
  getCategoryList()
}

function handleAdd(pid) {
  dialogTitle.value = pid > 0 ? '添加子分类' : '添加一级分类'
  Object.assign(dialogForm, createDialogForm(pid))
  uploadKey.value += 1
  dialogVisible.value = true
  nextTick(() => {
    dialogFormRef.value?.clearValidate?.()
  })
}

function handleEdit(row) {
  dialogTitle.value = '编辑分类'
  Object.assign(dialogForm, createDialogForm(), {
    id: row.id,
    pid: Number(row.pid) || 0,
    name: row.name || row.label,
    image: normalizeImageValue(row.image),
    banner_image: normalizeImageValue(row.banner_image),
    description: row.description || '',
    sort: Number(row.sort) || 0,
    status: Number(row.status)
  })
  uploadKey.value += 1
  dialogVisible.value = true
  nextTick(() => {
    dialogFormRef.value?.clearValidate?.()
  })
}

function handleClose() {
  dialogVisible.value = false
  Object.assign(dialogForm, createDialogForm())
}

function handleImageChange(value) {
  dialogForm.image = normalizeImageValue(value)
  nextTick(() => {
    dialogFormRef.value?.clearValidate?.('image')
  })
}

function handleBannerImageChange(value) {
  dialogForm.banner_image = normalizeImageValue(value)
}

async function submitForm() {
  if (!dialogFormRef.value) return
  const valid = await dialogFormRef.value.validate().catch(() => false)
  if (!valid) return
  submitLoading.value = true
  const data = buildSubmitData()
  const api = dialogForm.id
    ? specialCategorySave(dialogForm.id, data)
    : specialCategoryAdd(data)
  api.then(res => {
    message.success(res.message || '操作成功')
    handleClose()
    getCategoryList()
  }).catch(() => {}).finally(() => {
    submitLoading.value = false
  })
}

function buildSubmitData() {
  const pid = Number(dialogForm.pid) || 0
  return {
    name: String(dialogForm.name || '').trim(),
    pid,
    path: pid > 0 ? [pid] : [],
    image: normalizeImageValue(dialogForm.image),
    banner_image: normalizeImageValue(dialogForm.banner_image),
    description: String(dialogForm.description || '').trim(),
    sort: Number(dialogForm.sort) || 0,
    status: Number(dialogForm.status) === 0 ? 0 : 1
  }
}

function buildParentCategoryOptions(list, level = 0) {
  return (list || []).map(item => {
    const id = Number(item.id) || 0
    const option = {
      ...item,
      disabled: level > 0 || (Number(dialogForm.id) > 0 && id === Number(dialogForm.id))
    }
    if (Array.isArray(item.children) && item.children.length) {
      option.children = buildParentCategoryOptions(item.children, level + 1)
    }
    return option
  })
}

function isInvalidParent(value) {
  const pid = Number(value) || 0
  if (pid <= 0) return false
  if (Number(dialogForm.id) > 0 && pid === Number(dialogForm.id)) return true
  const parent = findCategoryById(categoryList.value, pid)
  return !parent || Number(parent.pid) !== 0
}

function findCategoryById(list, id) {
  for (const item of list || []) {
    if (Number(item.id) === Number(id)) return item
    const child = findCategoryById(item.children || [], id)
    if (child) return child
  }
  return null
}

function handleDelete(id) {
  confirm('确定要删除该分类吗？子分类也将一并删除，该操作不可恢复。', '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    specialCategoryDelete(id).then(res => {
      message.success(res.message || '删除成功')
      getCategoryList()
    }).catch(() => {})
  }).catch(() => {})
}

function handleStatusChange(row) {
  specialCategorySetStatus(row.id).then(res => {
    if (res.code === 200) {
      message.success(res.message || '状态更新成功')
    } else {
      message.error(res.message || '状态更新失败')
      row.status = row.status === 1 ? 0 : 1
    }
  }).catch(() => {
    row.status = row.status === 1 ? 0 : 1
  })
}

onBeforeMount(() => {
  getCategoryList()
})
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

.category-work-surface {
  background: var(--ds-surface-card);
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
}

:deep(.btn-curator-primary.el-button--primary) {
  border: none;
  border-radius: 6px;
  color: var(--ds-on-primary);
  font-weight: 500;
  padding-left: 18px;
  padding-right: 18px;
  box-shadow: 0 2px 8px rgba(0, 87, 194, 0.2);
}

:deep(.btn-curator-reset) {
  border-radius: 6px;
  background: var(--ds-surface-card);
  color: var(--ds-primary);
  border: 1px solid var(--ds-primary);

  &:hover,
  &:focus {
    background: rgba(0, 87, 194, 0.06);
    color: var(--ds-primary-container);
    border-color: var(--ds-primary-container);
  }
}

.category-search-form {
  margin-bottom: 8px;
}

.category-curator-table {
  margin-top: 8px;

  :deep(.el-table__header th) {
    color: var(--ds-on-surface-variant);
    font-weight: 500;
    background: rgba(var(--ds-surface-low-rgb), 0.5);
  }

  :deep(.el-table__row:hover > td) {
    background: var(--ds-surface-low) !important;
  }

  :deep(.el-table__row--level-1) {
    background-color: rgba(var(--ds-surface-low-rgb), 0.5);
  }
}

.search-input { width: 180px; }
.search-select-sm { width: 120px; }

.ml-6 {
  margin-left: 6px;
}

.danger-link {
  color: #f56c6c;

  &:hover {
    color: #f56c6c;
    opacity: 0.8;
  }
}

.dialog-form {
  padding: 8px 16px;

  :deep(.el-input-number) {
    width: 100%;

    .el-input__inner {
      text-align: left;
    }
  }
}

.form-width {
  width: 100%;
}

.form-tip {
  font-size: 12px;
  color: #909399;
  margin-top: 4px;
  line-height: 1.5;
}

.dialog-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
</style>
