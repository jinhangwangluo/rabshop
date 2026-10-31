<template>
  <div class="product-category-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">
          商品分类
          <span v-if="isSubView" class="category-crumb">/ {{ currentParentName }}</span>
        </h1>
        <p class="page-curator-subtitle">
          {{ isSubView ? `当前展示「${currentParentName}」下的分类` : '点击分类名称进入下级分类，支持三级分类结构（最多三级）、排序调整与状态控制。' }}
        </p>
      </div>
      <div class="page-curator-header__actions">
        <el-button v-if="isSubView" class="btn-curator-reset" icon="el-icon-back" @click="backToParent">
          返回上级分类
        </el-button>
        <el-button
          type="primary"
          class="btn-curator-primary"
          icon="el-icon-plus"
          @click="handleAdd(isSubView ? currentParentId : 0)"
        >
          {{ isSubView ? '添加分类' : '添加一级分类' }}
        </el-button>
      </div>
    </header>

    <div class="category-work-surface">
      <!-- 搜索区域 -->
      <el-form :model="searchForm" inline class="category-search-form" @submit.prevent>
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

      <!-- 当前层级导航 -->
      <div v-if="isSubView" class="category-nav">
        <a class="category-nav__link" @click="backToParent">← 返回上级分类</a>
        <span class="category-nav__current">当前分类：<b>{{ currentParentName }}</b></span>
      </div>

      <!-- 表格区域 -->
      <el-table
        v-loading="loading"
        :data="displayList"
        row-key="id"
        class="category-curator-table"
      >
        <el-table-column prop="id" label="ID" width="110" align="center" />
        <el-table-column prop="label" label="分类名称" min-width="200">
          <template #default="scope">
            <a v-if="scope.row.level < 3" class="category-link" @click="enterCategory(scope.row)">
              {{ scope.row.label }}
            </a>
            <span v-else>{{ scope.row.label }}</span>
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
            <a v-if="scope.row.level < 3" @click="handleAdd(scope.row.id)">添加子分类</a>
            <el-divider v-if="scope.row.level < 3" direction="vertical" />
            <a class="danger-link" @click="handleDelete(scope.row.id)">删除</a>
          </template>
        </el-table-column>
      </el-table>
    </div>

    <!-- 添加/编辑分类弹窗 -->
    <el-dialog
      v-model="dialogVisible"
      :title="dialogTitle"
      width="600px"
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
        <el-form-item label="上级分类">
          <el-cascader
            v-model="dialogForm.pidPath"
            :options="cascaderOptions"
            :props="{ checkStrictly: true, emitPath: true, value: 'value', label: 'label', children: 'children', disabled: 'disabled' }"
            placeholder="请选择分类"
            class="form-width"
            clearable
            filterable
          />
          <div class="form-tip">最多支持三级分类，三级分类下不可再添加</div>
        </el-form-item>
        <el-form-item label="分类名称" prop="name">
          <el-input
            v-model="dialogForm.name"
            placeholder="请输入分类名称"
            maxlength="30"
            show-word-limit
          />
        </el-form-item>
        <el-form-item label="分类图">
          <Upload
            :key="'image-' + uploadKey"
            v-model="dialogForm.image"
            :limit="1"
            @update:modelValue="handleImageChange"
          />
          <div class="form-tip">可选，尺寸建议：100x100px。留空则不展示。建议用于移动端分类页图标或首图。</div>
        </el-form-item>
        <el-form-item label="小图">
          <Upload
            :key="'banner-' + uploadKey"
            v-model="dialogForm.banner_image"
            :limit="1"
            @update:modelValue="handleBannerImageChange"
          />
          <div class="form-tip">可选，尺寸建议：80x80px。留空则不展示。用于补充分类页的小尺寸展示图。</div>
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number
            v-model="dialogForm.sort"
            :min="0"
            :max="9999"
            placeholder="数值越大越靠前"
            class="form-width"
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
  getProductCategoryTree,
  createProductCategory,
  updateProductCategory,
  deleteProductCategory,
  setProductCategoryStatus
} from '@/api/product'
import Upload from '@/components/Upload/index'

defineOptions({
  name: 'ProductCategory'
})

const { proxy } = getCurrentInstance()
const message = proxy.$message
const confirm = proxy.$confirm

const loading = ref(false)
const categoryList = ref([])
const dialogVisible = ref(false)
const dialogTitle = ref('添加分类')
const submitLoading = ref(false)
const uploadKey = ref(0)
const dialogFormRef = ref(null)

// 钻取状态：0=一级分类视图；>0=查看该一级分类下的二级分类
const currentParentId = ref(0)
const currentParentName = ref('')

const searchForm = reactive({
  name: '',
  status: ''
})

const dialogForm = reactive(getDefaultDialogForm())

const dialogRules = {
  name: [
    { required: true, message: '请输入分类名称', trigger: 'blur' },
    { max: 30, message: '分类名称最多30个字符', trigger: 'blur' }
  ]
}

const isSubView = computed(() => currentParentId.value > 0)

// 当前层级列表：一级视图取 pid=0 的顶级；次级视图取当前父分类的子级；搜索在前端本地过滤
// level 在 getCategoryList 时已递归附加到每个节点（顶级=1，最多三级），用于控制进入下级/添加子分类
const displayList = computed(() => {
  let scope = []
  if (isSubView.value) {
    const parent = findNode(categoryList.value, currentParentId.value)
    scope = parent ? (parent.children || []) : []
  } else {
    scope = categoryList.value.filter(item => Number(item.pid) === 0)
  }
  return filterList(scope)
})

// 弹窗上级分类树：三级分类禁用（不能作为父级，保证最多三级）
const cascaderOptions = computed(() => buildCascaderOptions(categoryList.value))

function getDefaultDialogForm() {
  return {
    id: 0,
    pidPath: [],
    name: '',
    image: '',
    banner_image: '',
    sort: 0,
    status: 1
  }
}

function findNode(list, id) {
  for (const item of list) {
    if (Number(item.id) === Number(id)) {
      return item
    }
    const found = findNode(item.children || [], id)
    if (found) {
      return found
    }
  }
  return null
}

// 从根到目标节点的链路（不含则不命中）
function findPath(list, id) {
  for (const item of list) {
    if (Number(item.id) === Number(id)) {
      return [item]
    }
    const sub = findPath(item.children || [], id)
    if (sub.length) {
      return [item, ...sub]
    }
  }
  return []
}

function getCategoryLevel(id) {
  return findPath(categoryList.value, id).length
}

function buildCascaderOptions(list, depth = 1) {
  return (list || []).map(item => ({
    value: item.id,
    label: item.name,
    disabled: depth >= 3,
    children: item.children && item.children.length
      ? buildCascaderOptions(item.children, depth + 1)
      : undefined
  }))
}

function filterList(list) {
  const keyword = searchForm.name.trim()
  return list.filter(item => {
    const nameMatch = !keyword || (item.name || '').includes(keyword)
    const statusMatch = searchForm.status === '' || Number(item.status) === Number(searchForm.status)
    return nameMatch && statusMatch
  })
}

// 递归附加层级（顶级=1），树形表格的内层行（子级分类）也需要该字段；
// 叶子节点保持无 children 字段，避免 el-table 渲染出空展开箭头
function normalizeLevel(list, depth = 1) {
  return (list || []).map(item => {
    const children = normalizeLevel(item.children, depth + 1)
    return {
      ...item,
      level: depth,
      ...(children.length ? { children } : {})
    }
  })
}

function getCategoryList() {
  loading.value = true
  return getProductCategoryTree({ owner_id: 0 }).then(res => {
    categoryList.value = normalizeLevel(res.data || [])
  }).finally(() => {
    loading.value = false
  })
}

function enterCategory(row) {
  currentParentId.value = row.id
  currentParentName.value = row.name
}

function backToParent() {
  currentParentId.value = 0
  currentParentName.value = ''
}

function handleSearch() {
  getCategoryList()
}

function handleReset() {
  Object.assign(searchForm, { name: '', status: '' })
  getCategoryList()
}

function handleAdd(pid) {
  dialogTitle.value = pid > 0 ? '添加子分类' : '添加一级分类'
  Object.assign(dialogForm, {
    ...getDefaultDialogForm(),
    pidPath: pid > 0 ? [pid] : []
  })
  uploadKey.value += 1
  dialogVisible.value = true
}

function handleEdit(row) {
  // 上级分类回填完整的祖先链路（三级分类也能正确回显其二级父级）
  const path = findPath(categoryList.value, row.id)
  const pidPath = path.length > 1 ? path.slice(0, -1).map(item => Number(item.id)) : []
  dialogTitle.value = '编辑分类'
  Object.assign(dialogForm, {
    id: row.id,
    pidPath,
    name: row.name,
    image: normalizeImageValue(row.image),
    banner_image: normalizeImageValue(row.banner_image),
    sort: Number(row.sort) || 0,
    status: Number(row.status)
  })
  uploadKey.value += 1
  dialogVisible.value = true
}

function handleClose() {
  dialogVisible.value = false
  nextTick(() => {
    dialogFormRef.value && dialogFormRef.value.resetFields()
    Object.assign(dialogForm, getDefaultDialogForm())
  })
}

function normalizeImageValue(value) {
  if (Array.isArray(value)) {
    return String(value.filter(Boolean)[0] || '').trim()
  }
  if (value && typeof value === 'object') {
    return String(value.path || value.url || value.src || '').trim()
  }
  return String(value || '').trim()
}

function handleImageChange(value) {
  dialogForm.image = normalizeImageValue(value)
}

function handleBannerImageChange(value) {
  dialogForm.banner_image = normalizeImageValue(value)
}

function submitForm() {
  dialogFormRef.value && dialogFormRef.value.validate(valid => {
    if (!valid) return
    submitLoading.value = true
    const pid = Array.isArray(dialogForm.pidPath)
      ? (dialogForm.pidPath.length ? dialogForm.pidPath[dialogForm.pidPath.length - 1] : 0)
      : (dialogForm.pidPath || 0)
    const payload = {
      name: dialogForm.name,
      pid,
      image: normalizeImageValue(dialogForm.image),
      banner_image: normalizeImageValue(dialogForm.banner_image),
      sort: dialogForm.sort,
      status: dialogForm.status
    }
    const api = dialogForm.id
      ? updateProductCategory(dialogForm.id, payload)
      : createProductCategory(payload)
    api.then(res => {
      message.success(res.message || '操作成功')
      handleClose()
      getCategoryList()
    }).catch(() => {}).finally(() => {
      submitLoading.value = false
    })
  })
}

function handleDelete(id) {
  const tip = isSubView.value
    ? '确定要删除该分类吗？该操作不可恢复。'
    : '确定要删除该分类吗？子分类也将一并删除，该操作不可恢复。'
  confirm(tip, '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    deleteProductCategory(id).then(res => {
      message.success(res.message || '删除成功')
      getCategoryList()
    }).catch(() => {})
  }).catch(() => {})
}

function handleStatusChange(row) {
  setProductCategoryStatus(row.id, row.status).then(res => {
    message.success(res.message || '状态更新成功')
  }).catch(() => {
    row.status = row.status === 1 ? 0 : 1
  })
}

onBeforeMount(() => {
  getCategoryList()
})
</script>

<style lang="scss" scoped>
.product-category-page {
  min-height: 100%;
  box-sizing: border-box;
}

.page-curator-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 20px;

  &__actions {
    display: flex;
    align-items: center;
    gap: 12px;
  }
}

.page-curator-title {
  margin: 0 0 8px;
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--ds-on-surface);
  letter-spacing: 0.02em;
}

.category-crumb {
  margin-left: 8px;
  font-size: 1.1rem;
  font-weight: 500;
  color: var(--ds-primary);
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

.category-nav {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 10px 12px;
  margin-bottom: 12px;
  border-radius: 8px;
  background: var(--ds-surface-low);
  font-size: 13px;

  &__link {
    color: var(--ds-primary);
    cursor: pointer;

    &:hover {
      color: var(--ds-primary-hover);
    }
  }

  &__current {
    color: var(--ds-on-surface-variant);

    b {
      color: var(--ds-on-surface);
      font-weight: 600;
    }
  }
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
}

.category-link {
  color: var(--ds-primary);
  font-weight: 500;

  &:hover {
    color: var(--ds-primary-hover);
    text-decoration: underline;
  }
}

.category-empty {
  padding: 24px 0;
}

.search-input { width: 180px; }
.search-select-sm { width: 120px; }

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
