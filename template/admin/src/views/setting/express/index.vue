<template>
  <div class="express-company-page">
    <header class="page-header">
      <div>
        <h1 class="page-title">快递公司</h1>
        <p class="page-subtitle">维护订单发货可选快递公司和物流查询配置</p>
      </div>
      <el-button type="primary" :icon="Plus" @click="handleAdd">添加快递公司</el-button>
    </header>

    <el-card shadow="never">
      <div class="toolbar">
        <el-form :model="searchForm" inline @submit.prevent>
          <el-form-item label="关键词">
            <el-input
              v-model="searchForm.keyword"
              clearable
              placeholder="快递公司名称或编码"
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
            <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
            <el-button :icon="Refresh" @click="handleReset">重置</el-button>
          </el-form-item>
        </el-form>
      </div>

      <el-table v-loading="loading" :data="expressList" row-key="id">
        <el-table-column prop="id" label="ID" width="90" align="center" />
        <el-table-column prop="name" label="快递公司" min-width="180" />
        <el-table-column prop="code" label="快递编码" min-width="160">
          <template #default="{ row }">
            <el-tag type="info">{{ row.code }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="常用" width="100" align="center">
          <template #default="{ row }">
            <el-tag v-if="Number(row.is_common) === 1" type="success">常用</el-tag>
            <span v-else class="muted-text">否</span>
          </template>
        </el-table-column>
        <el-table-column prop="sort" label="排序" width="110" align="center" />
        <el-table-column label="状态" width="110" align="center">
          <template #default="{ row }">
            <el-switch
              v-model="row.status"
              :active-value="1"
              :inactive-value="0"
              :loading="statusLoadingId === row.id"
              @change="value => handleStatusChange(row, value)"
            />
          </template>
        </el-table-column>
        <el-table-column prop="create_time" label="创建时间" width="180" />
        <el-table-column label="操作" width="160" align="center" fixed="right">
          <template #default="{ row }">
            <a @click="handleEdit(row)">编辑</a>
            <el-divider direction="vertical" />
            <a @click="handleDelete(row)">删除</a>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrap">
        <el-pagination
          v-model:current-page="searchForm.page"
          v-model:page-size="searchForm.limit"
          :page-sizes="[10, 20, 50, 100]"
          layout="total, sizes, prev, pager, next, jumper"
          :total="total"
          @size-change="fetchExpressList"
          @current-change="fetchExpressList"
        />
      </div>
    </el-card>

    <el-dialog
      v-model="formVisible"
      :title="form.id ? '编辑快递公司' : '添加快递公司'"
      width="520px"
      :close-on-click-modal="false"
      @closed="resetForm"
    >
      <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
        <el-form-item label="快递公司" prop="name">
          <el-input v-model="form.name" clearable maxlength="80" placeholder="请输入快递公司名称" />
        </el-form-item>
        <el-form-item label="快递编码" prop="code">
          <el-input v-model.trim="form.code" clearable maxlength="64" placeholder="请输入快递公司编码" @input="handleCodeInput" />
        </el-form-item>
        <el-form-item label="常用" prop="is_common">
          <el-switch v-model="form.is_common" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="排序" prop="sort">
          <el-input-number v-model="form.sort" :min="0" :max="999999" controls-position="right" />
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-radio-group v-model="form.status">
            <el-radio :label="1">启用</el-radio>
            <el-radio :label="0">禁用</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <div>
          <el-button @click="formVisible = false">取 消</el-button>
          <el-button type="primary" :loading="submitLoading" @click="handleSubmit">确 定</el-button>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { nextTick, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Refresh, Search } from '@element-plus/icons-vue'
import {
  addExpressCompany,
  deleteExpressCompany,
  getExpressCompanyList,
  getExpressQueryConfig,
  saveExpressQueryConfig,
  toggleExpressCompanyStatus,
  updateExpressCompany
} from '@/api/expressCompany'

defineOptions({
  name: 'ExpressCompany'
})

const loading = ref(false)
const submitLoading = ref(false)
const querySaving = ref(false)
const statusLoadingId = ref(0)
const formVisible = ref(false)
const formRef = ref(null)
const expressList = ref([])
const total = ref(0)

const searchForm = reactive({
  keyword: '',
  status: '',
  page: 1,
  limit: 20
})

const queryConfig = reactive({
  provider: 'aliyun_market',
  appcode_configured: false
})
const queryForm = reactive({
  appcode: ''
})

const createForm = () => ({
  id: 0,
  name: '',
  code: '',
  is_common: 0,
  sort: 0,
  status: 1
})
const form = reactive(createForm())
const rules = {
  name: [{ required: true, message: '请输入快递公司名称', trigger: 'blur' }],
  code: [{ required: true, message: '请输入快递公司编码', trigger: 'blur' }]
}

function applyConfig(data = {}) {
  queryConfig.provider = data.provider || 'aliyun_market'
  queryConfig.appcode_configured = Boolean(data.appcode_configured)
}

async function fetchExpressList() {
  loading.value = true
  try {
    const res = await getExpressCompanyList({ ...searchForm })
    const data = res.data || {}
    expressList.value = data.list || []
    total.value = Number(data.total || data.count || 0)
  } finally {
    loading.value = false
  }
}

async function fetchQueryConfig() {
  const res = await getExpressQueryConfig()
  applyConfig(res.data || {})
}

function handleSearch() {
  searchForm.page = 1
  fetchExpressList()
}

function handleReset() {
  searchForm.keyword = ''
  searchForm.status = ''
  searchForm.page = 1
  fetchExpressList()
}

function resetForm() {
  Object.assign(form, createForm())
  nextTick(() => {
    formRef.value?.clearValidate?.()
  })
}

function handleAdd() {
  resetForm()
  formVisible.value = true
}

function handleEdit(row) {
  Object.assign(form, {
    id: row.id,
    name: row.name,
    code: row.code,
    is_common: Number(row.is_common || 0),
    sort: Number(row.sort || 0),
    status: Number(row.status)
  })
  formVisible.value = true
}

function handleCodeInput(value) {
  form.code = String(value || '').trim().toUpperCase()
}

function restoreRowStatus(row, status) {
  row.status = status
}

function clearQueryAppcode() {
  queryForm.appcode = ''
}

async function handleSubmit() {
  if (!formRef.value) return
  const valid = await formRef.value.validate().catch(() => false)
  if (!valid) return

  submitLoading.value = true
  try {
    const data = {
      name: form.name,
      code: form.code,
      is_common: form.is_common,
      sort: form.sort,
      status: form.status
    }
    if (form.id) {
      await updateExpressCompany(form.id, data)
      ElMessage.success('修改成功')
    } else {
      await addExpressCompany(data)
      ElMessage.success('添加成功')
    }
    formVisible.value = false
    fetchExpressList()
  } finally {
    submitLoading.value = false
  }
}

async function handleStatusChange(row, value) {
  const oldValue = value === 1 ? 0 : 1
  statusLoadingId.value = row.id
  try {
    await toggleExpressCompanyStatus(row.id, value)
    ElMessage.success('状态已更新')
  } catch (error) {
    restoreRowStatus(row, oldValue)
  } finally {
    statusLoadingId.value = 0
  }
}

async function handleDelete(row) {
  const confirmed = await ElMessageBox.confirm(`确定删除快递公司「${row.name}」吗？`, '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).catch(() => false)
  if (!confirmed || !row.id) return

  await deleteExpressCompany(row.id)
  ElMessage.success('删除成功')
  fetchExpressList()
}

async function handleSaveQueryConfig() {
  querySaving.value = true
  try {
    const res = await saveExpressQueryConfig({ appcode: queryForm.appcode })
    applyConfig(res.data || {})
    clearQueryAppcode()
    ElMessage.success('保存成功')
  } finally {
    querySaving.value = false
  }
}

onMounted(() => {
  fetchExpressList()
  fetchQueryConfig()
})
</script>

<style lang="scss" scoped>
.express-company-page {
  background: #f5f7fb;
  min-height: calc(100vh - 84px);
}

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 16px;
}

.page-title {
  margin: 0;
  color: #1f2937;
  font-size: 24px;
  font-weight: 700;
}

.page-subtitle {
  margin: 6px 0 0;
  color: #667085;
  font-size: 14px;
}

.config-card {
  margin-bottom: 16px;
}

.config-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
}

.config-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.config-title {
  color: #1f2937;
  font-size: 16px;
  font-weight: 600;
}

.config-actions {
  display: flex;
  align-items: center;
  gap: 12px;
  width: min(520px, 100%);
}

.toolbar {
  margin-bottom: 16px;
}

.search-input {
  width: 240px;
}

.status-select {
  width: 140px;
}

.muted-text {
  color: #98a2b3;
}

.pagination-wrap {
  display: flex;
  justify-content: flex-end;
  margin-top: 20px;
}

@media (max-width: 768px) {
  .page-header,
  .config-row,
  .config-actions {
    align-items: stretch;
    flex-direction: column;
  }
}
</style>
