<template>
  <el-drawer
    v-model="localVisible"
    :title="`直播间用户 — ${props.roomTitle}`"
    size="900px"
    :wrapper-closable="true"
    @close="handleClose"
  >
    <div class="drawer-body">
      <!-- 筛选栏 -->
      <div class="drawer-filter">
        <el-input
          v-model="userSearch.nickname"
          placeholder="搜索用户昵称"
          clearable
          prefix-icon="el-icon-search"
          class="drawer-search-input"
          @keyup.enter="handleSearch"
          @clear="handleSearch"
        />
        <el-select
          v-model="userSearch.is_online"
          placeholder="全部状态"
          clearable
          class="drawer-search-select"
          @change="handleSearch"
        >
          <el-option label="在线" :value="1" />
          <el-option label="已离开" :value="0" />
        </el-select>
        <el-button icon="el-icon-refresh" @click="loadUsers">刷新</el-button>
      </div>

      <!-- 用户列表 -->
      <el-table
        v-loading="loading"
        :data="userList"
        class="drawer-table"
      >
        <el-table-column label="头像" width="54" align="center">
          <template #default="scope">
            <el-avatar :size="32" :src="scope.row.avatar" icon="el-icon-user-solid" />
          </template>
        </el-table-column>
        <el-table-column prop="nickname" label="昵称" min-width="100" show-overflow-tooltip />
        <el-table-column label="等级" width="130" align="center">
          <template #default="scope">
            <div v-if="scope.row._levelLoading" class="level-loading">
              <LegacyIcon name="el-icon-loading" />
            </div>
            <div v-else-if="scope.row._level" class="level-badge">
              <el-image
                v-if="scope.row._level.level_icon"
                :src="scope.row._level.level_icon"
                class="level-icon"
                fit="contain"
              />
              <el-tag size="mini" type="warning">Lv.{{ scope.row._level.level || 0 }}</el-tag>
              <span class="level-name">{{ scope.row._level.level_name }}</span>
            </div>
            <a v-else @click="handleViewLevel(scope.row)">查看等级</a>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="80" align="center">
          <template #default="scope">
            <el-tag :type="scope.row.is_online ? 'success' : 'info'" size="mini">
              {{ scope.row.is_online ? '在线' : '已离开' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="join_time" label="进入时间" width="150" align="center" />
        <el-table-column prop="leave_time" label="离开时间" width="150" align="center">
          <template #default="scope">{{ scope.row.leave_time || '—' }}</template>
        </el-table-column>
        <el-table-column label="操作" width="110" fixed="right" align="center">
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

      <!-- 分页 -->
      <div class="drawer-pagination">
        <el-pagination
          :current-page="page"
          :page-sizes="[10, 20, 50]"
          :page-size="limit"
          layout="total, sizes, prev, pager, next"
          :total="total"
          small
          @size-change="handleSizeChange"
          @current-change="handleCurrentChange"
        />
      </div>
    </div>
  </el-drawer>
</template>

<script setup>
import { getCurrentInstance, reactive, ref, watch } from 'vue'
import {
  getLiveRoomUsers,
  muteRoomUser,
  kickRoomUser,
  getUserLevelInRoom
} from '@/api/live'

defineOptions({
  name: 'RoomUserDrawer'
})

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  roomId: {
    type: Number,
    default: 0
  },
  roomTitle: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['update:visible'])
const { proxy } = getCurrentInstance() || {}

const localVisible = ref(false)
const loading = ref(false)
const userList = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(20)
const userSearch = reactive({
  nickname: '',
  is_online: ''
})

const DEFAULT_USER_SEARCH = {
  nickname: '',
  is_online: ''
}

watch(() => props.visible, val => {
  localVisible.value = val
  if (val) {
    page.value = 1
    Object.assign(userSearch, DEFAULT_USER_SEARCH)
    loadUsers()
  }
}, { immediate: true })

watch(localVisible, val => {
  emit('update:visible', val)
})

async function loadUsers() {
  if (!props.roomId) return
  loading.value = true
  const params = {
    page: page.value,
    limit: limit.value,
    ...userSearch
  }
  try {
    const res = await getLiveRoomUsers(props.roomId, params)
    const list = res.data.list || res.data || []
    userList.value = list.map(item => ({ ...item, _level: null, _levelLoading: false }))
    total.value = res.data.total || 0
  } catch {
    userList.value = []
    total.value = 0
  } finally {
    loading.value = false
  }
}

function handleSearch() {
  page.value = 1
  loadUsers()
}

function handleSizeChange(val) {
  limit.value = val
  page.value = 1
  loadUsers()
}

function handleCurrentChange(val) {
  page.value = val
  loadUsers()
}

function handleClose() {
  userList.value = []
  total.value = 0
  emit('update:visible', false)
}

async function confirmAction(message, title = '提示') {
  if (!proxy?.$confirm) return false
  return proxy.$confirm(message, title, { type: 'warning' }).then(() => true).catch(() => false)
}

async function handleMute(row) {
  const confirmed = await confirmAction(`确定禁言用户「${row.nickname || row.user_id}」吗？`)
  if (!confirmed) return

  await muteRoomUser(props.roomId, row.user_id || row.id)
  proxy?.$message?.success('禁言成功')
}

async function handleKick(row) {
  const confirmed = await confirmAction(`确定踢出用户「${row.nickname || row.user_id}」吗？`)
  if (!confirmed) return

  await kickRoomUser(props.roomId, row.user_id || row.id)
  proxy?.$message?.success('踢出成功')
  loadUsers()
}

function handleViewLevel(row) {
  const userId = row.user_id || row.id
  row._levelLoading = true
  getUserLevelInRoom(props.roomId, userId).then(res => {
    row._level = res.data || { level: 0, level_name: '暂无等级' }
  }).catch(() => {
    row._level = { level: 0, level_name: '暂无等级' }
  }).finally(() => {
    row._levelLoading = false
  })
}
</script>

<style lang="scss" scoped>
.drawer-body {
  padding: 0 20px 20px;
  display: flex;
  flex-direction: column;
  height: 100%;
}
.drawer-filter {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 16px 0 12px;
  flex-shrink: 0;
}
.drawer-search-input {
  width: 200px;
}
.drawer-search-select {
  width: 130px;
}
.drawer-table {
  flex: 1;
}
.drawer-pagination {
  margin-top: 14px;
  display: flex;
  justify-content: flex-end;
  flex-shrink: 0;
}
.level-badge {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
}
.level-icon {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
}
.level-name {
  font-size: 12px;
  color: #606266;
  max-width: 60px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.level-loading {
  color: #909399;
  font-size: 14px;
}
.danger-link {
  color: #f56c6c;
}
.text-gray {
  color: #c0c4cc;
  font-size: 12px;
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
