<template>
  <div class="cs-staff-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">客服人员管理</h1>
        <p class="page-curator-subtitle">
          统一管理客服账号、在线状态与服务承载能力。
        </p>
      </div>
      <el-button type="primary" class="btn-curator-primary" icon="el-icon-plus" @click="openCreate">新增客服</el-button>
    </header>

    <div class="staff-work-surface">
      <el-form :inline="true" :model="query" class="staff-search-form" @submit.prevent>
        <el-form-item label="关键词">
          <el-input v-model="query.keyword" clearable placeholder="昵称关键词" class="w220" @keyup.enter="fetchList" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="query.status" clearable placeholder="全部" class="w120">
            <el-option label="离线" :value="0" />
            <el-option label="在线" :value="1" />
            <el-option label="忙碌" :value="2" />
          </el-select>
        </el-form-item>
        <el-form-item label="在线">
          <el-select v-model="query.is_online" clearable placeholder="全部" class="w120">
            <el-option label="在线" :value="1" />
            <el-option label="离线" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" class="btn-curator-primary" icon="el-icon-search" @click="fetchList">查询</el-button>
          <el-button class="btn-curator-reset" icon="el-icon-refresh" @click="resetQuery">重置</el-button>
        </el-form-item>
      </el-form>

      <el-table v-loading="loading" :data="list" class="staff-table">
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="staff_no" label="工号" width="120" />
        <el-table-column prop="nickname" label="昵称" min-width="180" show-overflow-tooltip />
        <el-table-column prop="admin_id" label="管理员ID" width="110" />
        <el-table-column prop="current_sessions" label="当前会话" width="100" />
        <el-table-column prop="max_sessions" label="最大会话" width="100" />
        <el-table-column prop="status" label="状态" width="100">
          <template #default="scope">
            <el-tag :type="statusTagType(scope.row.status)">{{ statusText(scope.row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="在线" width="90">
          <template #default="scope">
            <el-switch
              :model-value="Number(scope.row.is_online) === 1"
              @change="val => handleOnlineChange(scope.row, val)"
            />
          </template>
        </el-table-column>
        <el-table-column prop="create_time" label="创建时间" width="180" />
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="scope">
            <el-button type="text" @click="openEdit(scope.row)">编辑</el-button>
            <el-button type="text" class="danger-text" @click="handleDelete(scope.row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrap">
        <el-pagination
          :current-page="query.page"
          :page-size="query.limit"
          :page-sizes="[10, 20, 50]"
          layout="total, sizes, prev, pager, next"
          :total="total"
          @size-change="handleSizeChange"
          @current-change="handleCurrentChange"
        />
      </div>
    </div>

    <el-dialog v-model="showDialog" :title="form.id ? '编辑客服' : '新增客服'" width="580px">
      <el-form :model="form" label-width="90px">
        <el-form-item v-if="!form.id" label="管理员">
          <el-select
            v-model="form.admin_id"
            filterable
            remote
            reserve-keyword
            clearable
            placeholder="请输入管理员账号搜索"
            :remote-method="remoteAdmin"
            :loading="adminLoading"
            class="w220"
            @focus="loadAdminOptions()"
          >
            <el-option
              v-for="item in adminOptions"
              :key="item.id"
              :label="item.label"
              :value="item.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="昵称">
          <el-input v-model="form.nickname" maxlength="32" />
        </el-form-item>
        <el-form-item label="头像">
          <Upload v-model="form.avatar" />
        </el-form-item>
        <el-form-item label="最大会话">
          <el-input-number v-model="form.max_sessions" :min="1" class="w220" />
        </el-form-item>
        <el-form-item label="状态">
          <el-radio-group v-model="form.status">
            <el-radio :label="0">离线</el-radio>
            <el-radio :label="1">在线</el-radio>
            <el-radio :label="2">忙碌</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <span>
          <el-button @click="showDialog = false">取消</el-button>
          <el-button type="primary" @click="submitForm">确定</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { getCurrentInstance, onBeforeMount, reactive, ref } from 'vue'
import {
  createCsStaff,
  deleteCsStaff,
  getCsStaffList,
  updateCsStaff,
  updateCsStaffOnlineStatus
} from '@/api/cs'
import { adminListApi } from '@/api/system'
import Upload from '@/components/Upload'

const defaultForm = () => ({
  id: 0,
  admin_id: 0,
  nickname: '',
  avatar: '',
  max_sessions: 10,
  status: 0
})

defineOptions({
  name: 'CsStaffIndex'
})

const { proxy } = getCurrentInstance()
const message = proxy.$message
const confirm = proxy.$confirm
const loading = ref(false)
const adminLoading = ref(false)
const list = ref([])
const total = ref(0)
const showDialog = ref(false)
const adminOptions = ref([])
const query = reactive({
  keyword: '',
  status: '',
  is_online: '',
  page: 1,
  limit: 20
})
const form = reactive(defaultForm())

async function fetchList() {
  loading.value = true
  try {
    const response = await getCsStaffList(query)
    const payload = response.data || response || {}
    const data = payload.data || payload
    list.value = data.list || []
    total.value = Number(data.total || 0)
  } finally {
    loading.value = false
  }
}

async function loadAdminOptions(keyword = '') {
  adminLoading.value = true
  try {
    const response = await adminListApi({
      page: 1,
      limit: 20,
      status: 1,
      account: keyword
    })
    const payload = response.data || response || {}
    const data = payload.data || payload
    adminOptions.value = normalizeAdminOptions(data.list || [])
  } finally {
    adminLoading.value = false
  }
}

function remoteAdmin(keyword) {
  loadAdminOptions(keyword)
}

function normalizeAdminOptions(list = []) {
  return (Array.isArray(list) ? list : [])
    .map(item => ({
      id: Number(item.id || 0),
      label: formatAdminLabel(item)
    }))
    .filter(item => item.id > 0)
}

function formatAdminLabel(item = {}) {
  const labelParts = []
  if (item.real_name) {
    labelParts.push(item.real_name)
  }
  if (item.account) {
    labelParts.push(item.account)
  }
  const mainLabel = labelParts.length ? labelParts.join(' / ') : '未命名管理员'

  return `${mainLabel}（ID：${item.id}）`
}

function resetQuery() {
  Object.assign(query, {
    keyword: '',
    status: '',
    is_online: '',
    page: 1,
    limit: 20
  })
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

async function openCreate() {
  Object.assign(form, defaultForm())
  adminOptions.value = []
  showDialog.value = true
  await loadAdminOptions()
}

function openEdit(row) {
  Object.assign(form, {
    id: row.id,
    admin_id: Number(row.admin_id || 0),
    nickname: row.nickname || '',
    avatar: row.avatar || '',
    max_sessions: Number(row.max_sessions || 10),
    status: Number(row.status || 0)
  })
  showDialog.value = true
}

async function submitForm() {
  if (!form.nickname) {
    message.warning('请输入客服昵称')
    return
  }

  const payload = {
    admin_id: form.admin_id,
    nickname: form.nickname,
    avatar: form.avatar,
    max_sessions: form.max_sessions,
    status: form.status
  }

  if (form.id) {
    await updateCsStaff(form.id, payload)
    message.success('修改成功')
  } else {
    if (!payload.admin_id) {
      message.warning('请选择管理员')
      return
    }
    await createCsStaff(payload)
    message.success('新增成功')
  }

  showDialog.value = false
  fetchList()
}

async function handleDelete(row) {
  await confirm('确定删除该客服吗？', '提示', { type: 'warning' })
  await deleteCsStaff(row.id)
  message.success('删除成功')
  fetchList()
}

async function handleOnlineChange(row, online) {
  await updateCsStaffOnlineStatus({
    id: row.id,
    is_online: online ? 1 : 0,
    status: row.status
  })
  message.success('在线状态已更新')
  fetchList()
}

function statusText(status) {
  const map = {
    0: '离线',
    1: '在线',
    2: '忙碌'
  }
  return map[Number(status)] || '未知'
}

function statusTagType(status) {
  const map = {
    0: 'info',
    1: 'success',
    2: 'warning'
  }
  return map[Number(status)] || 'info'
}

onBeforeMount(() => {
  fetchList()
})
</script>

<style lang="scss" scoped>
.cs-staff-page {
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

.staff-work-surface {
  background: var(--ds-surface-card);
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
}

.staff-search-form {
  margin-bottom: 8px;
}

.staff-table {
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

.w220 {
  width: 220px;
}

.w120 {
  width: 120px;
}

.danger-text {
  color: #f56c6c;
}

.pagination-wrap {
  display: flex;
  justify-content: flex-end;
  padding: 16px 24px 0;
}
</style>
