<template>
  <div>
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
          <el-button type="primary" icon="el-icon-search" @click="handleSearch">查询</el-button>
          <el-button icon="el-icon-refresh" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 表格区域 -->
    <el-card shadow="never">
      <el-table
        v-loading="loading"
        :data="userList"
        class="mt14"
      >
        <el-table-column prop="id" label="ID" width="70" align="center" />
        <el-table-column label="头像" width="60" align="center">
          <template #default="scope">
            <el-avatar :size="36" :src="scope.row.avatar" icon="el-icon-user-solid" />
          </template>
        </el-table-column>
        <el-table-column prop="nickname" label="用户昵称" min-width="100" show-overflow-tooltip />
        <el-table-column label="直播间" min-width="120" show-overflow-tooltip>
          <template #default="scope">{{ scope.row.room_title || '—' }}</template>
        </el-table-column>
        <el-table-column label="状态" width="90" align="center">
          <template #default="scope">
            <el-tag :type="scope.row.is_online ? 'success' : 'info'">
              {{ scope.row.is_online ? '在线' : '已离开' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="join_time" label="进入时间" width="160" align="center" />
        <el-table-column prop="leave_time" label="离开时间" width="160" align="center">
          <template #default="scope">{{ scope.row.leave_time || '—' }}</template>
        </el-table-column>
        <el-table-column label="操作" width="130" fixed="right" align="center">
          <template #default="scope">
            <template v-if="scope.row.is_online">
              <a @click="handleMute(scope.row)">禁言</a>
              <el-divider direction="vertical" />
              <a class="danger-link" @click="handleKick(scope.row)">踢出</a>
            </template>
            <span v-else class="text-gray">—</span>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrap">
        <el-pagination
          :current-page="page"
          :page-sizes="[10, 20, 50, 100]"
          :page-size="limit"
          layout="total, sizes, prev, pager, next, jumper"
          :total="total"
          @size-change="handleSizeChange"
          @current-change="handleCurrentChange"
        />
      </div>
    </el-card>
  </div>
</template>

<script setup>
import { getCurrentInstance, onBeforeMount, reactive, ref } from 'vue'
import { getRoomList, muteRoomUser, kickRoomUser } from '@/api/live'
import request from '@/utils/request'

defineOptions({
  name: 'LiveUserManage'
})

const { proxy } = getCurrentInstance() || {}

const searchFormRef = ref(null)
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

onBeforeMount(() => {
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
  searchFormRef.value && searchFormRef.value.resetFields && searchFormRef.value.resetFields()
  Object.assign(searchForm, { live_room_id: '', nickname: '', is_online: '' })
  handleSearch()
}

function handleMute(row) {
  proxy?.$confirm(`确定禁言用户「${row.nickname || row.user_id}」吗？`, '提示', { type: 'warning' }).then(() => {
    muteRoomUser(row.live_room_id, row.user_id || row.id).then(() => {
      proxy?.$message?.success('禁言成功')
    })
  })
}

function handleKick(row) {
  proxy?.$confirm(`确定踢出用户「${row.nickname || row.user_id}」吗？`, '提示', { type: 'warning' }).then(() => {
    kickRoomUser(row.live_room_id, row.user_id || row.id).then(() => {
      proxy?.$message?.success('踢出成功')
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
.search-card {
  margin-bottom: 14px;
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
  color: #c0c4cc;
  font-size: 12px;
}
.pagination-wrap {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}
.danger-link {
  color: #f56c6c;
}
a {
  color: #409eff;
  cursor: pointer;
  font-size: 13px;
  &:hover {
    opacity: 0.8;
  }
}
</style>
