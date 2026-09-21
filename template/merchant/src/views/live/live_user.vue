<template>
  <div class="live-user-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">直播用户</h1>
        <p class="page-curator-subtitle">
          查看直播间在线用户进出场情况，支持实时禁言与踢出处理。
        </p>
      </div>
    </header>

    <div class="live-user-work-surface">
      <!-- 搜索区域 -->
      <el-card shadow="never" class="search-card">
        <el-form
          ref="searchFormRef"
          :model="searchForm"
          inline
          class="search-form"
          @submit.prevent
        >
          <el-form-item label="直播间">
            <el-select
              v-model="searchForm.live_room_id"
              placeholder="请选择直播间"
              filterable
              clearable
              class="search-select"
            >
              <el-option
                v-for="item in roomOptions"
                :key="item.id"
                :label="item.title"
                :value="item.id"
              />
            </el-select>
          </el-form-item>
          <el-form-item label="用户昵称">
            <el-input
              v-model="searchForm.nickname"
              clearable
              placeholder="请输入用户昵称"
              class="search-input"
              @keyup.enter="handleSearch"
            />
          </el-form-item>
          <el-form-item label="状态">
            <el-select
              v-model="searchForm.is_online"
              placeholder="全部"
              clearable
              class="search-select-sm"
            >
              <el-option label="在线" :value="1" />
              <el-option label="已离开" :value="0" />
            </el-select>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" class="btn-curator-primary" :icon="Search" @click="handleSearch">查询</el-button>
            <el-button class="btn-curator-reset" :icon="Refresh" @click="handleReset">重置</el-button>
          </el-form-item>
        </el-form>
      </el-card>

      <!-- 表格区域 -->
      <el-card shadow="never" class="table-card">
        <el-table
          v-loading="loading"
          :data="userList"
          class="mt14 live-user-curator-table"
        >
          <el-table-column prop="id" label="ID" width="70" align="center" />
          <el-table-column label="头像" width="60" align="center">
            <template #default="{ row }">
              <el-avatar :size="36" :src="row.avatar" :icon="UserFilled" />
            </template>
          </el-table-column>
          <el-table-column prop="nickname" label="用户昵称" min-width="100" show-overflow-tooltip />
          <el-table-column label="直播间" min-width="120" show-overflow-tooltip>
            <template #default="{ row }">{{ row.room_title || '—' }}</template>
          </el-table-column>
          <el-table-column label="状态" width="90" align="center">
            <template #default="{ row }">
              <el-tag :type="row.is_online ? 'success' : 'info'" >
                {{ row.is_online ? '在线' : '已离开' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="join_time" label="进入时间" width="160" align="center" />
          <el-table-column prop="leave_time" label="离开时间" width="160" align="center">
            <template #default="{ row }">{{ row.leave_time || '—' }}</template>
          </el-table-column>
          <el-table-column label="操作" width="130" fixed="right" align="center">
            <template #default="{ row }">
              <template v-if="row.is_online">
                <a @click="handleMute(row)">禁言</a>
                <el-divider direction="vertical" />
                <a class="danger-link" @click="handleKick(row)">踢出</a>
              </template>
              <span v-else class="text-gray">—</span>
            </template>
          </el-table-column>
        </el-table>

        <div class="pagination-wrap">
          <el-pagination
            v-model:current-page="page"
            v-model:page-size="limit"
            :page-sizes="[10, 20, 50, 100]"
            layout="total, sizes, prev, pager, next, jumper"
            :total="total"
            @size-change="handleSizeChange"
            @current-change="handleCurrentChange"
          />
        </div>
      </el-card>
    </div>
  </div>
</template>

<script setup>
import { getRoomList, muteRoomUser, kickRoomUser } from '@/api/live'
import request from '@/utils/request'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Refresh, Search, UserFilled } from '@element-plus/icons-vue'
import { onMounted, reactive, ref } from 'vue'

defineOptions({
  name: 'LiveUserManage'
})

const loading = ref(false)
const userList = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(20)
const roomOptions = ref([])
const searchForm = reactive({
  live_room_id: '',
  nickname: '',
  is_online: ''
})

const searchFormRef = ref(null)

onMounted(() => {
  loadRoomOptions()
  getUserList()
})

function loadRoomOptions() {
  getRoomList({ page: 1, limit: 200 }).then(res => {
    roomOptions.value = res.data.list || res.data || []
  })
}

function getUserList() {
  loading.value = true
  request({
    url: '/live/room_users',
    method: 'get',
    params: { page: page.value, limit: limit.value, ...searchForm }
  }).then(res => {
    userList.value = res.data.list || res.data || []
    total.value = res.data.total || 0
  }).finally(() => {
    loading.value = false
  })
}

function handleSearch() {
  page.value = 1
  getUserList()
}

function handleReset() {
  searchFormRef.value.resetFields()
  Object.assign(searchForm, { live_room_id: '', nickname: '', is_online: '' })
  handleSearch()
}

function handleMute(row) {
  ElMessageBox.confirm(`确定禁言用户「${row.nickname || row.user_id}」吗？`, '提示', { type: 'warning' }).then(() => {
    muteRoomUser(row.live_room_id, row.user_id || row.id).then(() => {
      ElMessage.success('禁言成功')
    })
  })
}

function handleKick(row) {
  ElMessageBox.confirm(`确定踢出用户「${row.nickname || row.user_id}」吗？`, '提示', { type: 'warning' }).then(() => {
    kickRoomUser(row.live_room_id, row.user_id || row.id).then(() => {
      ElMessage.success('踢出成功')
      getUserList()
    })
  })
}

function handleSizeChange(val) {
  limit.value = val
  page.value = 1
  getUserList()
}

function handleCurrentChange(val) {
  page.value = val
  getUserList()
}
</script>

<style lang="scss" scoped>
.live-user-curator-page {
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

.live-user-work-surface {
  background: var(--ds-surface-card);
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
}

.search-card,
.table-card {
  border: none;
  background: transparent;

  :deep(.el-card__body) {
    padding: 0;
  }
}

.search-card {
  margin-bottom: 8px;
}

:deep(.btn-curator-primary.el-button--primary) {
  border: none;
  border-radius: 6px;
  color: var(--ds-on-primary);
  font-weight: 500;
  padding-left: 18px;
  padding-right: 18px;
  box-shadow: 0 2px 8px var(--brand-shadow);
}

:deep(.btn-curator-reset) {
  border-radius: 6px;
  background: var(--ds-surface-card);
  color: var(--ds-primary);
  border: 1px solid var(--ds-primary);

  &:hover,
  &:focus {
    background: var(--brand-primary-pale);
    color: var(--ds-primary-hover);
    border-color: var(--ds-primary-hover);
  }
}

.live-user-curator-table {
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

.live-user-work-surface {
  :deep(.el-pager li.active) {
    background: var(--ds-primary);
    color: var(--ds-on-primary);
    border-radius: 4px;
  }
}

.search-input {
  width: 200px;
}

.search-select {
  width: 200px;
}

.search-select-sm {
  width: 120px;
}

.mt14 {
  margin-top: 14px;
}

.text-gray {
  color: var(--text-placeholder);
  font-size: 12px;
}

.pagination-wrap {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}

.danger-link {
  color: var(--text-danger);
}

a {
  color: var(--ds-primary);
  cursor: pointer;
  font-size: 13px;

  &:hover {
    color: var(--ds-primary-hover);
  }
}
</style>
