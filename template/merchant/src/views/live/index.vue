<template>
  <div class="live-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">直播间管理</h1>
        <p class="page-curator-subtitle">
          管理直播间的创建、开播、中控台操作与热门推荐配置。
        </p>
      </div>
      <el-button type="primary" class="btn-curator-primary" :icon="Plus" @click="handleAdd">
        创建直播间
      </el-button>
    </header>

    <div class="live-work-surface">
      <!-- 搜索区域 -->
      <el-card shadow="never" class="search-card">
        <el-form
          ref="searchFormRef"
          :model="searchForm"
          inline
          class="search-form"
          @submit.prevent
        >
          <el-form-item label="直播标题">
            <el-input
              v-model="searchForm.title"
              clearable
              placeholder="请输入直播标题"
              class="search-input"
              :prefix-icon="Search"
              @keyup.enter="handleSearch"
            />
          </el-form-item>
          <el-form-item label="直播状态">
            <el-select
              v-model="searchForm.status"
              placeholder="全部状态"
              clearable
              class="search-select-sm"
            >
              <el-option label="未开始" :value="0" />
              <el-option label="直播中" :value="1" />
              <el-option label="已结束" :value="2" />
            </el-select>
          </el-form-item>
          <el-form-item label="热门推荐">
            <el-select
              v-model="searchForm.is_hot"
              placeholder="全部"
              clearable
              class="search-select-sm"
            >
              <el-option label="热门" :value="1" />
              <el-option label="普通" :value="0" />
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
          :data="roomList"
          class="mt14 live-curator-table"
        >
          <el-table-column prop="id" label="ID" width="70" align="center" />
          <el-table-column label="封面" width="80" align="center">
            <template #default="{ row }">
              <el-image
                v-if="row.cover"
                :src="row.cover"
                :preview-src-list="[row.cover]"
                class="cover-img"
                fit="cover"
              />
              <span v-else class="no-cover">暂无</span>
            </template>
          </el-table-column>
          <el-table-column prop="title" label="直播标题" min-width="120" show-overflow-tooltip />
          <el-table-column label="主播" width="120" align="center">
            <template #default="{ row }">
              <span v-if="row.anchor">{{ row.anchor.name }}</span>
              <span v-else class="text-gray">-</span>
            </template>
          </el-table-column>
          <el-table-column label="直播状态" width="100" align="center">
            <template #default="{ row }">
              <el-tag :type="getStatusTagType(row.status)" >
                {{ getStatusLabel(row.status) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="热门" width="80" align="center">
            <template #default="{ row }">
              <el-tag v-if="row.is_hot" type="warning" >热门</el-tag>
              <span v-else class="text-gray">-</span>
            </template>
          </el-table-column>
          <el-table-column label="直播类型" width="100" align="center">
            <template #default="{ row }">
              <el-tag :type="isExternalStream(row) ? 'warning' : 'primary'" >
                {{ getStreamTypeLabel(row.stream_type) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="start_time" label="开播时间" width="160" align="center" />
          <el-table-column prop="sort" label="排序" width="70" align="center" />
          <el-table-column label="操作" width="250" fixed="right" align="center">
            <template #default="{ row }">
              <a @click="handleEdit(row.id)">编辑</a>
              <el-divider direction="vertical" />
              <a @click="handleConsole(row.id)">中控台</a>
              <el-divider direction="vertical" />
              <el-dropdown
                trigger="click"
                popper-class="live-room-more-dropdown"
                @command="handleMoreCommand($event, row)"
              >
                <a class="more-link">更多<LegacyIcon name="el-icon-arrow-down" class="el-icon--right" /></a>
                <el-dropdown-menu #dropdown>
                  <el-dropdown-item command="rank">贡献榜</el-dropdown-item>
                  <el-dropdown-item command="users">用户</el-dropdown-item>
                  <el-dropdown-item command="goods">商品</el-dropdown-item>
                  <el-dropdown-item v-if="row.audit_status === 1" command="goLive">去开播</el-dropdown-item>
                  <el-dropdown-item v-if="row.audit_status === 1 && row.status === 0" command="start">开播</el-dropdown-item>
                  <el-dropdown-item v-if="row.status === 1" command="end">结束直播</el-dropdown-item>
                  <el-dropdown-item v-if="row.status === 2 && row.is_record === 1" command="records">回放管理</el-dropdown-item>
                  <el-dropdown-item command="toggleHot">{{ row.is_hot ? '取消热门' : '设为热门' }}</el-dropdown-item>
                  <el-dropdown-item command="delete" class="dropdown-danger">删除</el-dropdown-item>
                </el-dropdown-menu>
              </el-dropdown>
            </template>
          </el-table-column>
        </el-table>

        <div class="pagination-wrap">
          <el-pagination
            v-model:current-page="page"
            v-model:page-size="limit"
            class="live-curator-pagination"
            :page-sizes="[10, 20, 50, 100]"
            layout="total, sizes, prev, pager, next, jumper"
            :total="total"
            @size-change="handleSizeChange"
            @current-change="handleCurrentChange"
          />
        </div>
      </el-card>
    </div>

    <!-- 去开播弹窗 -->
    <el-dialog
      v-model="streamDialogVisible"
      title="开播推流地址"
      width="680px"
      :close-on-click-modal="false"
    >
      <div v-if="streamDialogLoading" class="stream-dialog-loading">
        <LegacyIcon name="el-icon-loading" /> 正在获取推流地址...
      </div>
      <div v-else-if="isExternalStream(streamDialogInfo)">
        <el-descriptions :column="1" border >
          <el-descriptions-item label="直播类型">
            <el-tag type="warning" >外链直播</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="直播链接">
            <div class="stream-row stream-row--inline">
              <el-input :value="streamDialogInfo.external_play_url || streamDialogInfo.live_play_url" readonly class="stream-input"  />
              <el-button size="mini" :icon="CopyDocument" @click="copyText(streamDialogInfo.external_play_url || streamDialogInfo.live_play_url)">复制</el-button>
            </div>
          </el-descriptions-item>
        </el-descriptions>
      </div>
      <div v-else-if="hasStreamDialogInfo">
        <el-descriptions :column="1" border >
          <el-descriptions-item label="流名称">
            <span class="stream-name">{{ streamDialogInfo.stream_name }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="当前云服务商">
            <el-tag
              :type="streamDialogInfo.cloud_provider === 'aliyun' ? 'primary' : 'warning'"

            >
              {{ streamDialogInfo.cloud_provider === 'aliyun' ? '阿里云' : '七牛云' }}
            </el-tag>
          </el-descriptions-item>
        </el-descriptions>

        <div v-if="streamDialogInfo.ali_push_url || streamDialogInfo.ali_pull_url" class="stream-section">
          <div class="stream-section-title"><el-tag type="primary" size="mini">阿里云</el-tag></div>
          <div v-if="streamDialogInfo.ali_push_url" class="stream-row">
            <span class="stream-label">推流地址</span>
            <el-input :value="streamDialogInfo.ali_push_url" readonly class="stream-input"  />
            <el-button size="mini" :icon="CopyDocument" @click="copyText(streamDialogInfo.ali_push_url)">复制</el-button>
          </div>
          <div v-if="streamDialogInfo.ali_pull_url" class="stream-row">
            <span class="stream-label">拉流地址</span>
            <el-input :value="streamDialogInfo.ali_pull_url" readonly class="stream-input"  />
            <el-button size="mini" :icon="CopyDocument" @click="copyText(streamDialogInfo.ali_pull_url)">复制</el-button>
          </div>
        </div>

        <div v-if="streamDialogInfo.qiniu_push_url || streamDialogInfo.qiniu_pull_url" class="stream-section">
          <div class="stream-section-title"><el-tag type="warning" size="mini">七牛云</el-tag></div>
          <div v-if="streamDialogInfo.qiniu_push_url" class="stream-row">
            <span class="stream-label">推流地址</span>
            <el-input :value="streamDialogInfo.qiniu_push_url" readonly class="stream-input"  />
            <el-button size="mini" :icon="CopyDocument" @click="copyText(streamDialogInfo.qiniu_push_url)">复制</el-button>
          </div>
          <div v-if="streamDialogInfo.qiniu_pull_url" class="stream-row">
            <span class="stream-label">拉流地址</span>
            <el-input :value="streamDialogInfo.qiniu_pull_url" readonly class="stream-input"  />
            <el-button size="mini" :icon="CopyDocument" @click="copyText(streamDialogInfo.qiniu_pull_url)">复制</el-button>
          </div>
        </div>
      </div>
      <el-empty v-else description="暂无推流地址" :image-size="60">
        <el-button
          v-if="!isExternalStream(streamDialogInfo)"
          type="primary"
          :icon="VideoPlay"
          :loading="streamGenLoading"
          @click="handleGenerateStream"
        >生成推流地址</el-button>
      </el-empty>
      <template #footer><span class="dialog-footer">
        <el-button
          v-if="hasStreamDialogInfo && !isExternalStream(streamDialogInfo)"
          type="primary"
          :icon="Refresh"
          :loading="streamGenLoading"
          @click="handleGenerateStream(true)"
        >强制刷新</el-button>
        <el-button @click="streamDialogVisible = false">关闭</el-button>
        <el-button type="success" @click="handleConsole(streamDialogRoomId)">进入中控台</el-button>
      </span></template>
    </el-dialog>

    <!-- 回放管理弹窗 -->
    <el-dialog
      v-model="recordDialogVisible"
      title="回放管理"
      width="980px"
      :close-on-click-modal="false"
    >
      <div v-if="recordDialogLoading" class="stream-dialog-loading">
        <LegacyIcon name="el-icon-loading" /> 正在加载回放...
      </div>

      <el-empty v-else-if="recordList.length === 0" description="暂无回放记录" :image-size="60" />

      <el-table
        v-else
        :data="recordList"
        border

      >
        <el-table-column prop="start_time" label="开始时间" width="190" />
        <el-table-column prop="end_time" label="结束时间" width="190" />
        <el-table-column label="云服务商" width="120" align="center">
          <template #default="{ row }">
            <el-tag :type="row.cloud_provider === 'qiniu' ? 'warning' : 'primary'" size="mini">
              {{ row.cloud_provider === 'qiniu' ? '七牛云' : '阿里云' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="回放地址" min-width="420">
          <template #default="{ row }">
            <div class="playback-row">
              <el-input :value="row.playback_url" readonly class="stream-input"  />
              <el-button size="mini" :icon="CopyDocument" @click="copyText(row.playback_url)">复制</el-button>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="220" fixed="right" align="center">
          <template #default="{ row }">
            <el-button type="primary" size="mini" @click="handleSetPlaybackRecord(row)">播放</el-button>
            <el-divider direction="vertical" />
            <el-button type="danger" size="mini" @click="handleDeleteRecord(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-dialog>

    <!-- 直播间用户抽屉 -->
    <RoomUserDrawer
      v-model="userDrawerVisible"
      :room-id="userDrawerRoomId"
      :room-title="userDrawerRoomTitle"
    />

    <!-- 直播间商品抽屉 -->
    <RoomGoodsDrawer
      v-model="goodsDrawerVisible"
      :room-id="goodsDrawerRoomId"
      :room-title="goodsDrawerRoomTitle"
    />
  </div>
</template>

<script setup>
import {
  getRoomList as getRoomListApi,
  deleteRoom,
  switchRoomStatus,
  toggleRoomHot,
  getRoomStreamInfo,
  createRoomStream,
  closeRoomStream,
  getRoomRecords,
  deleteRoomRecord,
  setRoomPlaybackRecord
} from '@/api/live'
import RoomUserDrawer from './components/RoomUserDrawer'
import RoomGoodsDrawer from './components/RoomGoodsDrawer'
import { ElMessage, ElMessageBox } from 'element-plus'
import { CopyDocument, Plus, Refresh, Search, VideoPlay } from '@element-plus/icons-vue'
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'

defineOptions({
  name: 'LiveRoomList'
})

const router = useRouter()

const loading = ref(false)
const roomList = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(20)
const searchForm = reactive({
  title: '',
  status: '',
  is_hot: ''
})
// 去开播弹窗
const streamDialogVisible = ref(false)
const streamDialogLoading = ref(false)
const streamGenLoading = ref(false)
const streamDialogRoomId = ref(0)
const streamDialogInfo = ref({})
// 用户抽屉
const userDrawerVisible = ref(false)
const userDrawerRoomId = ref(0)
const userDrawerRoomTitle = ref('')
// 商品抽屉
const goodsDrawerVisible = ref(false)
const goodsDrawerRoomId = ref(0)
const goodsDrawerRoomTitle = ref('')
// 回放管理弹窗
const recordDialogVisible = ref(false)
const recordDialogLoading = ref(false)
const recordDialogRoomId = ref(0)
const recordList = ref([])

const searchFormRef = ref(null)

const hasStreamDialogInfo = computed(() => {
  return !!(
    streamDialogInfo.value.stream_name ||
    streamDialogInfo.value.live_play_url ||
    streamDialogInfo.value.ali_pull_url ||
    streamDialogInfo.value.qiniu_pull_url
  )
})

onMounted(() => {
  getRoomList()
})

function getRoomList() {
  loading.value = true
  getRoomListApi({
    page: page.value,
    limit: limit.value,
    ...searchForm
  }).then(res => {
    roomList.value = res.data.list || res.data || []
    total.value = res.data.total || 0
    loading.value = false
  }).catch(() => {
    loading.value = false
  })
}

function handleSearch() {
  page.value = 1
  getRoomList()
}

function handleReset() {
  searchFormRef.value.resetFields()
  Object.assign(searchForm, { title: '', status: '', is_hot: '' })
  handleSearch()
}

function isExternalStream(row) {
  return String(row && row.stream_type || '') === 'external'
}

function getStreamTypeLabel(streamType) {
  return streamType === 'external' ? '外链直播' : 'OBS直播'
}

function handleAdd() {
  router.push({ name: 'MerchantLiveRoomCreate' })
}

function handleEdit(id) {
  router.push({ name: 'MerchantLiveRoomCreate', query: { id }})
}

function handleConsole(id) {
  router.push({ name: 'MerchantLiveConsole', query: { id }})
}

function handleOpenRecordManager(row) {
  recordDialogRoomId.value = row.id
  recordDialogVisible.value = true
  fetchRoomRecords()
}

function fetchRoomRecords() {
  if (!recordDialogRoomId.value) {
    recordList.value = []
    return
  }
  recordDialogLoading.value = true
  getRoomRecords(recordDialogRoomId.value).then(res => {
    const data = res.data || {}
    recordList.value = data.list || data || []
  }).catch(() => {
    recordList.value = []
  }).finally(() => {
    recordDialogLoading.value = false
  })
}

function handleDeleteRecord(record) {
  ElMessageBox.confirm('确定要删除该回放吗？', '提示', { type: 'warning' }).then(() => {
    deleteRoomRecord(recordDialogRoomId.value, record.id).then(() => {
      ElMessage.success('删除成功')
      fetchRoomRecords()
    })
  })
}

function handleSetPlaybackRecord(record) {
  ElMessageBox.confirm('确定要将该回放设为当前播放吗？', '提示', { type: 'warning' }).then(() => {
    setRoomPlaybackRecord(recordDialogRoomId.value, record.id).then(() => {
      ElMessage.success('设置播放回放成功')
      recordDialogVisible.value = false
      router.push({ name: 'MerchantLiveConsole', query: { id: recordDialogRoomId.value }})
    })
  })
}

function handleRank(id) {
  router.push({ name: 'MerchantLiveRoomRank', query: { room_id: id }})
}

function handleViewUsers(row) {
  userDrawerRoomId.value = row.id
  userDrawerRoomTitle.value = row.title || `#${row.id}`
  userDrawerVisible.value = true
}

function handleViewGoods(row) {
  goodsDrawerRoomId.value = row.id
  goodsDrawerRoomTitle.value = row.title || `#${row.id}`
  goodsDrawerVisible.value = true
}

function handleGoLive(row) {
  streamDialogRoomId.value = row.id
  streamDialogInfo.value = {}
  streamDialogVisible.value = true
  streamDialogLoading.value = true
  getRoomStreamInfo(row.id).then(res => {
    streamDialogInfo.value = res.data || {}
  }).catch(() => {
    streamDialogInfo.value = {}
  }).finally(() => {
    streamDialogLoading.value = false
  })
}

function handleGenerateStream(force = false) {
  if (isExternalStream(streamDialogInfo.value)) {
    ElMessage.warning('外链直播无需生成推流地址')
    return
  }
  const tip = force === true ? '强制刷新将重新生成推流地址，原地址将失效。确认继续？' : '确认为该直播间生成推流地址？'
  ElMessageBox.confirm(tip, '提示', { type: 'warning' }).then(() => {
    streamGenLoading.value = true
    createRoomStream(streamDialogRoomId.value, force === true).then(res => {
      ElMessage.success('推流地址生成成功')
      streamDialogInfo.value = res.data || {}
    }).finally(() => {
      streamGenLoading.value = false
    })
  })
}

function copyText(text) {
  if (navigator.clipboard) {
    navigator.clipboard.writeText(text).then(() => { ElMessage.success('复制成功') })
  } else {
    const el = document.createElement('input')
    el.value = text
    document.body.appendChild(el)
    el.select()
    document.execCommand('copy')
    document.body.removeChild(el)
    ElMessage.success('复制成功')
  }
}

function handleDelete(id) {
  ElMessageBox.confirm('确定要删除该直播间吗？', '提示', { type: 'warning' }).then(() => {
    deleteRoom(id).then(() => {
      ElMessage.success('删除成功')
      getRoomList()
    })
  })
}

function handleSwitchStatus(row, status) {
  const actionMap = { 1: '开播', 2: '结束直播' }
  ElMessageBox.confirm(`确定要${actionMap[status]}吗？`, '提示', { type: 'warning' }).then(() => {
    switchRoomStatus(row.id, status).then(res => {
      ElMessage.success(res.data.msg || '操作成功')
      getRoomList()
    })
  })
}

function handleToggleHot(row) {
  const action = row.is_hot ? '取消热门' : '设为热门'
  ElMessageBox.confirm(`确定要${action}吗？`, '提示', { type: 'warning' }).then(() => {
    toggleRoomHot(row.id).then(() => {
      ElMessage.success(`${action}成功`)
      getRoomList()
    })
  })
}

function getStatusLabel(status) {
  const map = { 0: '未开始', 1: '直播中', 2: '已结束' }
  return map[status] || '未知'
}

function getStatusTagType(status) {
  const map = { 0: 'info', 1: 'success', 2: '' }
  return map[status] || 'info'
}

function handleSizeChange(val) {
  limit.value = val
  page.value = 1
  getRoomList()
}

function handleCurrentChange(val) {
  page.value = val
  getRoomList()
}

function handleMoreCommand(command, row) {
  switch (command) {
    case 'rank':
      handleRank(row.id)
      break
    case 'users':
      handleViewUsers(row)
      break
    case 'goods':
      handleViewGoods(row)
      break
    case 'goLive':
      handleGoLive(row)
      break
    case 'start':
      handleSwitchStatus(row, 1)
      break
    case 'end':
      handleSwitchStatus(row, 2)
      break
    case 'records':
      handleOpenRecordManager(row)
      break
    case 'toggleHot':
      handleToggleHot(row)
      break
    case 'delete':
      handleDelete(row.id)
      break
  }
}
</script>

<style lang="scss" scoped>
.live-curator-page {
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

.live-work-surface {
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

.live-curator-table {
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

.search-input {
  width: 200px;
}

.search-select-sm {
  width: 140px;
}

.mt14 {
  margin-top: 14px;
}

.cover-img {
  width: 50px;
  height: 36px;
  border-radius: 4px;
}

.no-cover,
.text-gray {
  color: var(--text-placeholder);
  font-size: 12px;
}

.pagination-wrap {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}

.live-curator-pagination {
  :deep(.el-pager li.active) {
    background: var(--ds-primary);
    color: var(--ds-on-primary);
    border-radius: 4px;
  }

  :deep(.btn-next),
  :deep(.btn-prev),
  :deep(.el-pager li) {
    &:hover {
      color: var(--ds-primary);
    }
  }
}

.more-link {
  color: var(--ds-primary);
  cursor: pointer;
  font-size: 13px;
  transition: color 0.2s ease;

  &:hover,
  &:focus {
    color: var(--ds-primary-hover);
  }
}

.stream-dialog-loading {
  padding: 30px;
  text-align: center;
  color: var(--text-secondary);
}

.stream-name {
  font-family: monospace;
  color: var(--ds-primary);
  font-weight: 600;
}

.stream-section {
  margin-top: 16px;
}

.stream-section-title {
  margin-bottom: 10px;
}

.stream-row {
  display: flex;
  align-items: center;
  margin-bottom: 8px;
  gap: 8px;
}

.playback-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.stream-label {
  min-width: 64px;
  font-size: 13px;
  color: var(--text-secondary);
  flex-shrink: 0;
}

.stream-input {
  flex: 1;
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

<style lang="scss">
.live-room-more-dropdown {
  border: 1px solid var(--ds-outline, #ebe6e1);
  box-shadow: 0 10px 24px rgba(243, 65, 0, 0.12);

  .el-dropdown-menu__item {
    color: var(--ds-on-surface, #1a1c1c);
    transition: background-color 0.2s ease, color 0.2s ease;

    &:focus,
    &:not(.is-disabled):hover {
      background: var(--brand-primary-pale);
      color: var(--ds-primary, #1456f0);
    }
  }

  .el-dropdown-menu__item.dropdown-danger {
    color: var(--text-danger, #f56c6c);

    &:focus,
    &:not(.is-disabled):hover {
      color: var(--text-danger, #f56c6c);
      background: rgba(245, 108, 108, 0.08);
    }
  }
}
</style>
