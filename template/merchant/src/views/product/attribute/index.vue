<template>
  <div class="product-attribute-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">商品参数</h1>
        <p class="page-curator-subtitle">按平台分类维护参数模板，一个分类只保留一个模板，模板支持多个参数值。</p>
      </div>
      <el-button type="primary" class="btn-curator-primary" @click="openDrawer()">
        <LegacyIcon name="el-icon-plus" />
        <span>添加参数</span>
      </el-button>
    </header>

    <div class="category-work-surface">
      <el-form :model="searchForm" inline class="category-search-form" @submit.prevent>
        <el-form-item label="模板名称">
          <el-input v-model="searchForm.name" clearable placeholder="请输入模板名称" class="search-input" @keyup.enter="handleSearch" />
        </el-form-item>
        <el-form-item label="是否必选">
          <el-select v-model="searchForm.is_required" clearable placeholder="全部" class="search-select-sm">
            <el-option label="是" :value="1" />
            <el-option label="否" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" clearable placeholder="全部" class="search-select-sm">
            <el-option label="启用" :value="1" />
            <el-option label="禁用" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" class="btn-curator-primary" @click="handleSearch">查询</el-button>
          <el-button class="btn-curator-reset" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>

      <el-table v-loading="loading" :data="list" row-key="id" class="category-curator-table">
        <el-table-column prop="id" label="ID" width="80" align="center" />
        <el-table-column prop="name" label="模板名称" min-width="180" show-overflow-tooltip />
        <el-table-column prop="category_names" label="绑定分类" min-width="220" show-overflow-tooltip />
        <el-table-column label="必选" width="90" align="center">
          <template #default="scope">
            <el-tag :type="Number(scope.row.is_required) === 1 ? 'success' : 'info'">
              {{ Number(scope.row.is_required) === 1 ? '是' : '否' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="参数数" width="90" align="center">
          <template #default="scope">
            {{ Array.isArray(scope.row.params) ? scope.row.params.length : 0 }}
          </template>
        </el-table-column>
        <el-table-column prop="sort" label="排序" width="90" align="center" />
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
        <el-table-column prop="create_time" label="创建时间" width="170" align="center" />
        <el-table-column label="操作" width="160" fixed="right" align="center">
          <template #default="scope">
            <a @click="openDrawer(scope.row)">编辑</a>
            <el-divider direction="vertical" />
            <a class="danger-link" @click="handleDelete(scope.row.id)">删除</a>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrap">
        <el-pagination
          v-if="total > 0"
          v-model:current-page="searchForm.page"
          v-model:page-size="searchForm.limit"
          :page-sizes="[10, 20, 50, 100]"
          layout="total, sizes, prev, pager, next, jumper"
          :total="total"
          @size-change="handleSizeChange"
          @current-change="handlePageChange"
        />
      </div>
    </div>

    <el-drawer
      v-model="drawerVisible"
      :title="drawerForm.id ? '编辑商品参数' : '添加商品参数'"
      size="760px"
      destroy-on-close
    >
      <el-form ref="drawerFormRef" :model="drawerForm" :rules="rules" label-width="100px" class="drawer-form">
        <el-form-item label="模板名称" prop="name">
          <el-input v-model="drawerForm.name" maxlength="50" show-word-limit placeholder="请输入模板名称" />
        </el-form-item>

        <el-form-item label="绑定分类" prop="category_ids">
          <el-cascader
            v-model="categoryPaths"
            :options="categoryOptions"
            :props="cascaderProps"
            multiple
            clearable
            filterable
            collapse-tags
            collapse-tags-tooltip
            placeholder="请选择分类"
            class="drawer-cascader"
            @change="handleCategoryChange"
          />
        </el-form-item>

        <el-form-item label="是否必选">
          <el-switch v-model="drawerForm.is_required" :active-value="1" :inactive-value="0" />
        </el-form-item>

        <el-form-item label="排序">
          <el-input-number v-model="drawerForm.sort" :min="0" :max="9999" class="drawer-number" />
        </el-form-item>

        <el-form-item label="参数明细">
          <div class="param-editor">
            <div v-for="(row, index) in drawerForm.params" :key="row.row_key" class="param-editor__row">
              <div class="param-editor__head">
                <span class="param-editor__index">参数 {{ index + 1 }}</span>
                <a class="danger-link" @click="removeParamRow(index)">删除</a>
              </div>
              <el-input v-model="row.name" maxlength="30" show-word-limit placeholder="参数名称" />
              <el-input-tag
                :model-value="row.values"
                placeholder="输入多个参数值后回车"
                class="param-editor__tags"
                @add-tag="handleTagAdd(row, $event)"
                @remove-tag="handleTagRemove(row, $event)"
                @clear="row.values = []"
              />
              <div class="param-editor__switches">
                <el-switch v-model="row.is_required" :active-value="1" :inactive-value="0" />
                <span>必填</span>
                <el-input-number v-model="row.sort" :min="0" :max="9999" class="param-editor__sort" />
              </div>
            </div>
            <el-button text type="primary" class="param-editor__add" @click="addParamRow">添加参数</el-button>
          </div>
        </el-form-item>

        <el-form-item label="状态">
          <el-switch v-model="drawerForm.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
      </el-form>

      <template #footer>
        <div class="drawer-footer">
          <el-button @click="drawerVisible = false">取消</el-button>
          <el-button type="primary" :loading="submitLoading" @click="submitForm">保存</el-button>
        </div>
      </template>
    </el-drawer>
  </div>
</template>

<script setup>
import { getCurrentInstance, onBeforeMount, reactive, ref } from 'vue'
import {
  getAttributeList,
  createAttribute,
  updateAttribute,
  deleteAttribute,
  setAttributeStatus,
  getPlatformProductCategoryTree
} from '@/api/product'

defineOptions({
  name: 'MerchantProductAttribute'
})

const { proxy } = getCurrentInstance()
const message = proxy.$message
const confirm = proxy.$confirm

const loading = ref(false)
const list = ref([])
const total = ref(0)
const drawerVisible = ref(false)
const submitLoading = ref(false)
const drawerFormRef = ref(null)
const categoryOptions = ref([])
const categoryPaths = ref([])
const searchForm = reactive({
  name: '',
  is_required: '',
  status: '',
  page: 1,
  limit: 20
})
const drawerForm = reactive(getDefaultForm())

const cascaderProps = {
  multiple: true,
  checkStrictly: false,
  emitPath: true,
  value: 'value',
  label: 'label',
  children: 'children'
}

const rules = {
  name: [{ required: true, message: '请输入模板名称', trigger: 'blur' }],
  category_ids: [{ validator: (_, value, callback) => { if (!Array.isArray(value) || !value.length) callback(new Error('请选择分类')); else callback() }, trigger: 'change' }]
}

function getDefaultForm() {
  return {
    id: 0,
    name: '',
    category_ids: [],
    is_required: 0,
    sort: 0,
    status: 1,
    params: [getDefaultParamRow()]
  }
}

function getDefaultParamRow(extra = {}) {
  return {
    row_key: `param_${Date.now()}_${Math.random().toString(16).slice(2, 8)}`,
    param_id: 0,
    name: '',
    is_required: 0,
    sort: 0,
    values: [],
    ...extra
  }
}

function resetForm(row = null) {
  Object.assign(drawerForm, getDefaultForm())
  if (row && row.id) {
    drawerForm.id = Number(row.id) || 0
    drawerForm.name = row.name || ''
    drawerForm.category_ids = Array.isArray(row.category_ids) ? row.category_ids.map(Number).filter(Boolean) : []
    drawerForm.is_required = Number(row.is_required) || 0
    drawerForm.sort = Number(row.sort) || 0
    drawerForm.status = Number(row.status) || 0
    drawerForm.params = normalizeParams(Array.isArray(row.params) ? row.params : [])
    categoryPaths.value = buildCategoryPaths(drawerForm.category_ids)
    if (!drawerForm.params.length) {
      drawerForm.params = [getDefaultParamRow()]
    }
    return
  }
  drawerForm.params = [getDefaultParamRow()]
  categoryPaths.value = []
}

function normalizeParams(params) {
  return params
    .map((item, index) => ({
      row_key: String(item.row_key || item.key || `param_${index + 1}`),
      param_id: Number(item.param_id || item.attribute_id || 0),
      name: String(item.name || '').trim(),
      is_required: Number(item.is_required || 0),
      sort: Number(item.sort || index),
      values: Array.isArray(item.values) ? item.values.map((value) => String(value).trim()).filter(Boolean) : []
    }))
    .filter((item) => item.name)
}

function handleTagAdd(row, value) {
  const text = String(value || '').trim()
  if (!text) return
  row.values = Array.from(new Set([...(row.values || []), text]))
}

function handleTagRemove(row, value) {
  const text = String(value || '').trim()
  row.values = (row.values || []).filter((item) => item !== text)
}

function addParamRow() {
  drawerForm.params.push(getDefaultParamRow())
}

function removeParamRow(index) {
  drawerForm.params.splice(index, 1)
  if (!drawerForm.params.length) {
    drawerForm.params.push(getDefaultParamRow())
  }
}

function buildCategoryPaths(ids) {
  const list = []
  ;(ids || []).forEach((id) => {
    const path = findCategoryPath(categoryOptions.value, id)
    if (path.length) {
      list.push(path)
    }
  })
  return list
}

function findCategoryPath(nodes, id) {
  for (const node of nodes || []) {
    if (Number(node.value) === Number(id)) {
      return [node.value]
    }
    if (node.children && node.children.length) {
      const sub = findCategoryPath(node.children, id)
      if (sub.length) {
        return [node.value, ...sub]
      }
    }
  }
  return []
}

function handleCategoryChange(paths) {
  const ids = (Array.isArray(paths) ? paths : [])
    .map((path) => Number(path[path.length - 1]))
    .filter(Boolean)
  drawerForm.category_ids = [...new Set(ids)]
  drawerFormRef.value?.validateField('category_ids')
}

function getList() {
  loading.value = true
  getAttributeList(searchForm).then((res) => {
    const data = res.data || {}
    list.value = data.list || []
    total.value = data.total || 0
  }).finally(() => {
    loading.value = false
  })
}

function handleSearch() {
  searchForm.page = 1
  getList()
}

function handleReset() {
  Object.assign(searchForm, { name: '', is_required: '', status: '', page: 1, limit: 20 })
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

function openDrawer(row = null) {
  resetForm(row)
  drawerVisible.value = true
}

function submitForm() {
  drawerFormRef.value && drawerFormRef.value.validate((valid) => {
    if (!valid) return
    const params = drawerForm.params
      .map((item) => ({
        row_key: item.row_key,
        param_id: Number(item.param_id) || 0,
        name: String(item.name || '').trim(),
        is_required: Number(item.is_required) || 0,
        sort: Number(item.sort) || 0,
        values: Array.isArray(item.values) ? item.values.map((value) => String(value).trim()).filter(Boolean) : []
      }))
      .filter((item) => item.name && item.values.length)
    if (!params.length) {
      message.warning('请至少填写一条完整的参数明细')
      return
    }

    submitLoading.value = true
    const payload = {
      name: drawerForm.name,
      category_ids: drawerForm.category_ids,
      is_required: drawerForm.is_required,
      sort: drawerForm.sort,
      status: drawerForm.status,
      params
    }
    const api = drawerForm.id ? updateAttribute(drawerForm.id, payload) : createAttribute(payload)
    api.then((res) => {
      message.success(res.message || '操作成功')
      drawerVisible.value = false
      getList()
    }).catch(() => {}).finally(() => {
      submitLoading.value = false
    })
  })
}

function handleDelete(id) {
  confirm('确定要删除该模板吗？', '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => deleteAttribute(id).then((res) => {
    message.success(res.message || '删除成功')
    getList()
  })).catch(() => {})
}

function handleStatusChange(row) {
  setAttributeStatus(row.id, row.status).then((res) => {
    message.success(res.message || '状态更新成功')
  }).catch(() => {
    row.status = row.status === 1 ? 0 : 1
  })
}

function loadCategoryOptions() {
  getPlatformProductCategoryTree().then((res) => {
    categoryOptions.value = res.data || []
    if (categoryPaths.value.length) {
      categoryPaths.value = buildCategoryPaths(drawerForm.category_ids)
    }
  }).catch(() => {})
}

onBeforeMount(() => {
  getList()
  loadCategoryOptions()
})
</script>

<style lang="scss" scoped>
.product-attribute-page {
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
}

.page-curator-subtitle {
  margin: 0;
  max-width: 560px;
  font-size: 0.875rem;
  color: var(--ds-on-surface-variant);
  line-height: 1.5;
}

.category-work-surface {
  padding: 24px;
  border-radius: 12px;
  background: var(--ds-surface-card);
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
}

.category-search-form {
  margin-bottom: 8px;
}

.category-curator-table {
  margin-top: 8px;
}

.search-input {
  width: 180px;
}

.search-select-sm {
  width: 120px;
}

.danger-link {
  color: #f56c6c;
}

.pagination-wrap {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}

.drawer-form {
  padding-right: 8px;
}

.drawer-cascader {
  width: 100%;
}

.drawer-number {
  width: 180px;
}

.param-editor {
  width: 100%;
}

.param-editor__row {
  padding: 16px;
  margin-bottom: 12px;
  border: 1px solid #ebeef5;
  border-radius: 8px;
  background: #fff;
}

.param-editor__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.param-editor__index {
  font-size: 13px;
  font-weight: 600;
  color: var(--ds-on-surface);
}

.param-editor__tags {
  margin-top: 10px;
}

.param-editor__switches {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 10px;
  font-size: 13px;
  color: var(--ds-on-surface-variant);
}

.param-editor__sort {
  width: 140px;
  margin-left: auto;
}

.param-editor__add {
  padding-left: 0;
}

.drawer-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
</style>
