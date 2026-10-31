<template>
  <div class="live-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">直播间管理</h1>
        <p class="page-curator-subtitle">
          管理平台内所有直播间，包括创建、编辑、开播、结束以及回放管理等功能。
        </p>
      </div>
      <el-button
        type="primary"
        class="btn-curator-primary"
        icon="el-icon-plus"
        @click="handleAdd"
      >
        创建直播间
      </el-button>
    </header>

    <div class="live-work-surface">
      <el-tabs v-model="activeTab" class="live-curator-tabs">
        <el-tab-pane :label="`全部 (${tabCounts.all})`" name="all" />
        <el-tab-pane :label="`平台 (${tabCounts.platform})`" name="platform" />
        <el-tab-pane :label="`商户 (${tabCounts.merchant})`" name="merchant" />
        <el-tab-pane :label="`待审核 (${tabCounts.pending})`" name="pending" />
        <el-tab-pane :label="`违规 (${tabCounts.violation})`" name="violation" />
      </el-tabs>

      <!-- 搜索区域 -->
      <el-form
        ref="searchFormRef"
        :model="searchForm"
        inline
        class="search-form live-search-form"
        @submit.prevent
      >
        <el-form-item label="直播标题">
          <el-input
            v-model="searchForm.title"
            clearable
            placeholder="请输入直播标题"
            class="search-input"
            prefix-icon="el-icon-search"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item v-if="activeTab !== 'pending'" label="审核状态">
          <el-select
            v-model="searchForm.audit_status"
            placeholder="全部审核状态"
            clearable
            class="search-select-sm"
          >
            <el-option label="待审核" :value="0" />
            <el-option label="审核通过" :value="1" />
            <el-option label="审核驳回" :value="2" />
          </el-select>
        </el-form-item>
        <el-form-item v-if="activeTab !== 'platform'" label="所属商户">
          <el-select
            v-model="searchForm.merchant_id"
            filterable
            remote
            reserve-keyword
            clearable
            placeholder="输入商户名称搜索"
            :remote-method="remoteMerchant"
            :loading="merchantLoading"
            class="search-select-wide"
          >
            <el-option
              v-for="item in merchantOptions"
              :key="item.id"
              :label="`${item.mer_name}（id：${item.id}）`"
              :value="item.id"
            />
          </el-select>
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
        <el-form-item label="直播分类">
          <el-select
            v-model="searchForm.category_id"
            placeholder="全部分类"
            clearable
            class="search-select-sm"
          >
            <el-option
              v-for="item in categoryOptions"
              :key="item.id"
              :label="item.name"
              :value="item.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button
            type="primary"
            class="btn-curator-primary"
            icon="el-icon-search"
            @click="handleSearch"
          >
            查询
          </el-button>
          <el-button
            class="btn-curator-reset"
            icon="el-icon-refresh"
            @click="handleReset"
          >
            重置
          </el-button>
        </el-form-item>
      </el-form>

      <!-- 表格区域 -->

      <el-table
        v-loading="loading"
        :data="roomList"
        row-key="id"
        class="live-curator-table"
      >
        <el-table-column prop="id" label="ID" width="70" align="center" />
        <el-table-column label="封面" width="80" align="center">
          <template #default="scope">
            <el-image
              v-if="scope.row.cover"
              :src="scope.row.cover"
              :preview-src-list="[scope.row.cover]"
              class="cover-img"
              fit="cover"
            />
            <span v-else class="no-cover">暂无</span>
          </template>
        </el-table-column>
        <el-table-column prop="title" label="直播标题" min-width="120" show-overflow-tooltip />
        <el-table-column label="主播" width="120" align="center">
          <template #default="scope">
            <span v-if="scope.row.anchor">{{ scope.row.anchor.name }}</span>
            <span v-else class="text-gray">-</span>
          </template>
        </el-table-column>
        <el-table-column
          v-if="activeTab !== 'platform'"
          label="所属商户"
          min-width="180"
          show-overflow-tooltip
        >
          <template #default="scope">
            {{ formatMerchantCol(scope.row) }}
          </template>
        </el-table-column>
        <el-table-column label="分类" width="100" align="center">
          <template #default="scope">
            <span v-if="scope.row.category">{{ scope.row.category.name }}</span>
            <span v-else class="text-gray">-</span>
          </template>
        </el-table-column>
        <el-table-column label="审核状态" width="100" align="center">
          <template #default="scope">
            <el-tag :type="getAuditTagType(scope.row.audit_status)">
              {{ getAuditLabel(scope.row.audit_status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="违规次数" width="90" align="center">
          <template #default="scope">
            <el-tag
              :type="scope.row.violation_count > 0 ? 'warning' : 'info'"
            >
              {{ scope.row.violation_count || 0 }} 次
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="直播状态" width="100" align="center">
          <template #default="scope">
            <el-tag :type="getStatusTagType(scope.row.status)">
              {{ getStatusLabel(scope.row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="直播类型" width="100" align="center">
          <template #default="scope">
            <el-tag :type="isExternalStream(scope.row) ? 'warning' : 'primary'">
              {{ getStreamTypeLabel(scope.row.stream_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="热门" width="80" align="center">
          <template #default="scope">
            <el-tag v-if="scope.row.is_hot" type="warning">热门</el-tag>
            <span v-else class="text-gray">-</span>
          </template>
        </el-table-column>
        <el-table-column label="开播时间" width="150" align="center">
          <template #default="scope">
            {{ formatLiveTimeRange(scope.row) }}
          </template>
        </el-table-column>
        <el-table-column prop="sort" label="排序" width="70" align="center" />
        <el-table-column label="操作" width="250" fixed="right" align="center">
          <template #default="scope">
            <a @click="handleEdit(scope.row.id)">编辑</a>
            <template v-if="scope.row.audit_status === 0">
              <el-divider direction="vertical" />
              <a @click="handleAudit(scope.row)">审核</a>
            </template>
            <el-divider direction="vertical" />
            <a @click="handleConsole(scope.row.id)">中控台</a>
            <el-divider direction="vertical" />
            <el-dropdown
              trigger="click"
              @command="handleMoreCommand($event, scope.row)"
            >
              <a class="more-link">更多<LegacyIcon name="el-icon-arrow-down" class=" el-icon--right" /></a>
              <template #dropdown>
                <el-dropdown-menu class="live-room-more-dropdown">
                  <el-dropdown-item command="rank">贡献榜</el-dropdown-item>
                  <el-dropdown-item command="users">用户</el-dropdown-item>
                  <el-dropdown-item command="goods">商品</el-dropdown-item>
                  <el-dropdown-item v-if="scope.row.audit_status === 1" command="goLive">去开播</el-dropdown-item>
                  <el-dropdown-item v-if="scope.row.audit_status === 1 && scope.row.status === 0" command="start">开播</el-dropdown-item>
                  <el-dropdown-item v-if="scope.row.status === 1" command="end">结束直播</el-dropdown-item>
                  <el-dropdown-item v-if="scope.row.status === 2 && scope.row.is_record === 1" command="records">回放管理</el-dropdown-item>
                  <el-dropdown-item command="toggleHot">{{ scope.row.is_hot ? '取消热门' : '设为热门' }}</el-dropdown-item>
                  <el-dropdown-item v-if="isMerchantRoom(scope.row)" command="forceOffline" class="dropdown-danger">强制下架</el-dropdown-item>
                  <el-dropdown-item command="delete" class="dropdown-danger">删除</el-dropdown-item>
                </el-dropdown-menu>
              </template>
            </el-dropdown>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrap">
        <el-pagination
          class="live-curator-pagination"
          :current-page="page"
          :page-sizes="[10, 20, 50, 100]"
          :page-size="limit"
          layout="total, sizes, prev, pager, next, jumper"
          :total="total"
          @size-change="handleSizeChange"
          @current-change="handleCurrentChange"
        />
      </div>
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
        <el-descriptions :column="1" border>
          <el-descriptions-item label="直播类型">
            <el-tag type="warning">外链直播</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="直播链接">
            <div class="stream-row stream-row--inline">
              <el-input :value="streamDialogInfo.external_play_url || streamDialogInfo.live_play_url" readonly class="stream-input" />
              <el-button size="mini" icon="el-icon-copy-document" @click="copyText(streamDialogInfo.external_play_url || streamDialogInfo.live_play_url)">复制</el-button>
            </div>
          </el-descriptions-item>
        </el-descriptions>
      </div>
      <div v-else-if="hasStreamDialogInfo">
        <el-descriptions :column="1" border>
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
          <el-descriptions-item label="地址生成时间">
            {{ streamDialogInfo.stream_created_at || '—' }}
          </el-descriptions-item>
        </el-descriptions>

        <div v-if="streamDialogInfo.ali_push_base || streamDialogInfo.ali_pull_url" class="stream-section">
          <div class="stream-section-title"><el-tag type="primary" size="mini">阿里云</el-tag></div>
          <template v-if="streamDialogInfo.ali_push_base">
            <div class="stream-row">
              <span class="stream-label">推流地址</span>
              <el-input :value="streamDialogInfo.ali_push_base" readonly class="stream-input" />
              <el-button size="mini" icon="el-icon-copy-document" @click="copyText(streamDialogInfo.ali_push_base)">复制</el-button>
            </div>
            <div class="stream-row">
              <span class="stream-label">推流 Key</span>
              <el-input :value="streamDialogInfo.ali_push_key" readonly class="stream-input" />
              <el-button size="mini" icon="el-icon-copy-document" @click="copyText(streamDialogInfo.ali_push_key)">复制</el-button>
            </div>
          </template>
          <div v-if="streamDialogInfo.ali_pull_url" class="stream-row">
            <span class="stream-label">播流地址</span>
            <el-input :value="streamDialogInfo.ali_pull_url" readonly class="stream-input" />
            <el-button size="mini" icon="el-icon-copy-document" @click="copyText(streamDialogInfo.ali_pull_url)">复制</el-button>
          </div>
        </div>

        <div v-if="streamDialogInfo.qiniu_push_base || streamDialogInfo.qiniu_pull_url" class="stream-section">
          <div class="stream-section-title"><el-tag type="warning" size="mini">七牛云</el-tag></div>
          <template v-if="streamDialogInfo.qiniu_push_base">
            <div class="stream-row">
              <span class="stream-label">推流地址</span>
              <el-input :value="streamDialogInfo.qiniu_push_base" readonly class="stream-input" />
              <el-button size="mini" icon="el-icon-copy-document" @click="copyText(streamDialogInfo.qiniu_push_base)">复制</el-button>
            </div>
            <div class="stream-row">
              <span class="stream-label">推流 Key</span>
              <el-input :value="streamDialogInfo.qiniu_push_key" readonly class="stream-input" />
              <el-button size="mini" icon="el-icon-copy-document" @click="copyText(streamDialogInfo.qiniu_push_key)">复制</el-button>
            </div>
          </template>
          <div v-if="streamDialogInfo.qiniu_pull_url" class="stream-row">
            <span class="stream-label">播流地址</span>
            <el-input :value="streamDialogInfo.qiniu_pull_url" readonly class="stream-input" />
            <el-button size="mini" icon="el-icon-copy-document" @click="copyText(streamDialogInfo.qiniu_pull_url)">复制</el-button>
          </div>
        </div>
      </div>
      <el-empty v-else description="暂无推流地址" :image-size="60">
        <el-button
          v-if="!isExternalStream(streamDialogInfo)"
          type="primary"
          icon="el-icon-video-play"
          :loading="streamGenLoading"
          @click="handleGenerateStream"
        >生成推流地址</el-button>
      </el-empty>
      <template #footer>
        <span class="dialog-footer">
          <el-button
            v-if="hasStreamDialogInfo && !isExternalStream(streamDialogInfo)"
            type="primary"
            icon="el-icon-refresh"
            :loading="streamGenLoading"
            @click="handleGenerateStream(true)"
          >强制刷新</el-button>
          <el-button @click="streamDialogVisible = false">关闭</el-button>
          <el-button type="success" @click="handleConsole(streamDialogRoomId)">进入中控台</el-button>
        </span>
      </template>
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
          <template #default="scope">
            <el-tag :type="scope.row.cloud_provider === 'qiniu' ? 'warning' : 'primary'" size="mini">
              {{ scope.row.cloud_provider === 'qiniu' ? '七牛云' : '阿里云' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="回放地址" min-width="420">
          <template #default="scope">
            <div class="playback-row">
              <el-input :value="scope.row.playback_url" readonly class="stream-input" />
              <el-button size="mini" icon="el-icon-copy-document" @click="copyText(scope.row.playback_url)">复制</el-button>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="220" fixed="right" align="center">
          <template #default="scope">
            <el-button type="primary" size="mini" @click="handleSetPlaybackRecord(scope.row)">播放</el-button>
            <el-divider direction="vertical" />
            <el-button type="danger" size="mini" @click="handleDeleteRecord(scope.row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-dialog>

    <!-- 审核弹窗 -->
    <el-dialog
      v-model="auditVisible"
      title="审核直播间"
      width="480px"
      :close-on-click-modal="false"
    >
      <el-form ref="auditFormRef" :model="auditForm" label-width="90px">
        <el-form-item label="直播标题">
          <span>{{ auditRoomInfo.title }}</span>
        </el-form-item>
        <el-form-item label="审核结果" prop="audit_status">
          <el-radio-group v-model="auditForm.audit_status">
            <el-radio :label="1">审核通过</el-radio>
            <el-radio :label="2">审核驳回</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item v-if="auditForm.audit_status === 2" label="驳回原因" prop="audit_remark">
          <el-input
            v-model="auditForm.audit_remark"
            type="textarea"
            :rows="3"
            placeholder="请填写驳回原因（必填）"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="auditVisible = false">取 消</el-button>
          <el-button type="primary" :loading="auditLoading" @click="submitAudit">确 定</el-button>
        </span>
      </template>
    </el-dialog>

    <!-- 直播间用户抽屉 -->
    <RoomUserDrawer
      v-model:visible="userDrawerVisible"
      :room-id="userDrawerRoomId"
      :room-title="userDrawerRoomTitle"
    />

    <!-- 直播间商品抽屉 -->
    <RoomGoodsDrawer
      v-model:visible="goodsDrawerVisible"
      :room-id="goodsDrawerRoomId"
      :room-title="goodsDrawerRoomTitle"
    />
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, onBeforeMount, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  getRoomList as getRoomListApi,
  getRoomTabStats,
  getRoomDetail,
  auditRoom,
  deleteRoom,
  switchRoomStatus,
  toggleRoomHot,
  getRoomStreamInfo,
  createRoomStream,
  getCategoryOptions as getCategoryOptionsApi,
  getRoomRecords,
  deleteRoomRecord,
  setRoomPlaybackRecord
} from '@/api/live'
import { getMerchantList } from '@/api/merchant'
import RoomUserDrawer from './components/RoomUserDrawer.vue'
import RoomGoodsDrawer from './components/RoomGoodsDrawer.vue'

defineOptions({ name: 'LiveRoomList' })

const TAB_STORAGE_KEY = 'live-room-active-tab'
const TAB_OPTIONS = ['all', 'platform', 'merchant', 'pending', 'violation']

const route = useRoute()
const router = useRouter()
const { proxy } = getCurrentInstance()

const searchFormRef = ref(null)
const auditFormRef = ref(null)
const loading = ref(false)
const roomList = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(20)
const activeTab = ref('all')
const tabReady = ref(false)
const tabCounts = reactive({
  all: 0,
  platform: 0,
  merchant: 0,
  pending: 0,
  violation: 0
})
const categoryOptions = ref([])
const merchantOptions = ref([])
const merchantLoading = ref(false)
const searchForm = reactive({
  title: '',
  merchant_id: '',
  audit_status: '',
  status: '',
  is_hot: '',
  category_id: ''
})
const auditVisible = ref(false)
const auditLoading = ref(false)
const auditRoomInfo = ref({})
const routeDetailId = ref('')
const auditForm = reactive({
  audit_status: 1,
  audit_remark: ''
})
const streamDialogVisible = ref(false)
const streamDialogLoading = ref(false)
const streamGenLoading = ref(false)
const streamDialogRoomId = ref(0)
const streamDialogInfo = ref({})
const userDrawerVisible = ref(false)
const userDrawerRoomId = ref(0)
const userDrawerRoomTitle = ref('')
const goodsDrawerVisible = ref(false)
const goodsDrawerRoomId = ref(0)
const goodsDrawerRoomTitle = ref('')
const recordDialogVisible = ref(false)
const recordDialogLoading = ref(false)
const recordDialogRoomId = ref(0)
const recordList = ref([])

const hasStreamDialogInfo = computed(() => !!(
  streamDialogInfo.value.stream_name ||
  streamDialogInfo.value.live_play_url ||
  streamDialogInfo.value.ali_pull_url ||
  streamDialogInfo.value.qiniu_pull_url
))

function createSearchForm() {
  return {
    title: '',
    merchant_id: '',
    audit_status: '',
    status: '',
    is_hot: '',
    category_id: ''
  }
}

function createAuditForm() {
  return {
    audit_status: 1,
    audit_remark: ''
  }
}

function createTabCounts() {
  return {
    all: 0,
    platform: 0,
    merchant: 0,
    pending: 0,
    violation: 0
  }
}

function getValidTab(tab) {
  return TAB_OPTIONS.includes(tab) ? tab : 'all'
}

function resolveActiveTab() {
  const routeTab = route.query.tab
  if (routeTab) {
    return getValidTab(routeTab)
  }
  if (typeof window !== 'undefined') {
    const cachedTab = window.sessionStorage.getItem(TAB_STORAGE_KEY)
    if (cachedTab) {
      return getValidTab(cachedTab)
    }
  }
  return 'all'
}

function persistActiveTab(tab) {
  if (typeof window !== 'undefined') {
    window.sessionStorage.setItem(TAB_STORAGE_KEY, tab)
  }
}

function syncRouteTab(tab) {
  const query = { ...route.query }
  if (tab === 'all') {
    if (!Object.prototype.hasOwnProperty.call(query, 'tab')) return
    delete query.tab
  } else {
    if (query.tab === tab) return
    query.tab = tab
  }

  router.replace({
    path: route.path,
    query
  }).catch(() => {})
}

function applyTabChange(tab, options = {}) {
  const nextTab = getValidTab(tab)
  if (nextTab === 'platform') {
    searchForm.merchant_id = ''
  }
  if (nextTab === 'pending') {
    searchForm.audit_status = ''
  }
  persistActiveTab(nextTab)
  if (options.syncRoute !== false) {
    syncRouteTab(nextTab)
  }
  if (options.resetPage !== false) {
    page.value = 1
  }
  if (options.fetchList !== false) {
    getRoomList()
  }
}

function fetchTabStats() {
  getRoomTabStats()
    .then(res => {
      const data = res.data || {}
      Object.assign(tabCounts, {
        all: data.all || 0,
        platform: data.platform || 0,
        merchant: data.merchant || 0,
        pending: data.pending || 0,
        violation: data.violation || 0
      })
    })
    .catch(() => {
      Object.assign(tabCounts, createTabCounts())
    })
}

function remoteMerchant(query) {
  if (query === '') {
    merchantOptions.value = []
    return
  }
  merchantLoading.value = true
  getMerchantList({ page: 1, limit: 50, mer_name: query }).then(res => {
    merchantOptions.value = res.data.list || []
  }).finally(() => {
    merchantLoading.value = false
  })
}

function formatMerchantCol(row) {
  const merchantId = row.merchant_id
  if (!merchantId) {
    return '平台'
  }
  const merchant = row.merchant
  if (merchant && merchant.mer_name) {
    return `${merchant.mer_name}（id：${merchantId}）`
  }
  return `未知商户（id：${merchantId}）`
}

function isMerchantRoom(row) {
  return Number(row && row.merchant_id) > 0
}

function isExternalStream(row) {
  return String((row && row.stream_type) || '') === 'external'
}

function getStreamTypeLabel(streamType) {
  return streamType === 'external' ? '外链直播' : 'OBS直播'
}

function formatTimePart(value) {
  const match = String(value || '').match(/(\d{2}):(\d{2})(?::\d{2})?$/)
  return match ? `${match[1]}:${match[2]}` : ''
}

function formatLiveTimeRange(row) {
  const start = formatTimePart(row && row.start_time)
  const end = formatTimePart(row && row.end_time)
  if (!start) {
    return '-'
  }
  return end ? `${start} - ${end}` : start
}

function getRoomList() {
  loading.value = true
  getRoomListApi({
    page: page.value,
    limit: limit.value,
    tab: activeTab.value,
    ...searchForm
  })
    .then(res => {
      const data = res.data || {}
      roomList.value = data.list || data || []
      total.value = data.total || 0
    })
    .catch(() => {
      roomList.value = []
      total.value = 0
    })
    .finally(() => {
      loading.value = false
    })
}

function getCategoryOptions() {
  getCategoryOptionsApi().then(res => {
    categoryOptions.value = res.data || []
  })
}

function handleSearch() {
  page.value = 1
  getRoomList()
}

function handleReset() {
  searchFormRef.value?.resetFields()
  Object.assign(searchForm, createSearchForm())
  handleSearch()
}

function handleAdd() {
  router.push({ name: 'LiveRoomCreate' })
}

function handleEdit(id) {
  router.push({ name: 'LiveRoomCreate', query: { id }})
}

/**
 * 在新浏览器标签页打开中台，保留路由前缀及直播间参数。
 * @param {number} id 直播间 ID
 * @returns {void}
 */
function handleConsole(id) {
  const { href } = router.resolve({ name: 'LiveConsole', query: { id }})
  // noopener 可能在标签页成功打开时仍返回 null，不能据此重复打开或误报拦截。
  window.open(href, '_blank', 'noopener,noreferrer')
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
  proxy.$confirm('确定要删除该回放吗？', '提示', { type: 'warning' }).then(() => {
    deleteRoomRecord(recordDialogRoomId.value, record.id).then(() => {
      proxy?.$message?.success('删除成功')
      fetchRoomRecords()
    })
  })
}

/**
 * 设置回放后在新标签页查看中台；失败时关闭预开的空标签页。
 * @param {Object} record 回放记录，包含记录 ID
 * @returns {Promise<void>}
 */
function handleSetPlaybackRecord(record) {
  return proxy.$confirm('确定要将该回放设为当前播放吗？', '提示', { type: 'warning' }).then(() => {
    const roomId = recordDialogRoomId.value
    // 立即预开标签页并断开 opener，保留句柄以便接口成功后跳转或失败后关闭。
    const consoleWindow = window.open('about:blank', '_blank')
    if (consoleWindow) {
      consoleWindow.opener = null
    }
    return setRoomPlaybackRecord(roomId, record.id).then(() => {
      proxy?.$message?.success('设置播放回放成功')
      recordDialogVisible.value = false
      if (consoleWindow && !consoleWindow.closed) {
        const { href } = router.resolve({ name: 'LiveConsole', query: { id: roomId }})
        consoleWindow.location.replace(href)
      } else {
        proxy?.$message?.warning('中台标签页未打开')
      }
    }).catch(() => {
      if (consoleWindow && !consoleWindow.closed) {
        consoleWindow.close()
      }
    })
  }).catch(() => {})
}

function handleRank(id) {
  router.push({ name: 'LiveRoomRank', query: { room_id: id }})
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

function handleAudit(row) {
  auditRoomInfo.value = row
  Object.assign(auditForm, createAuditForm())
  auditVisible.value = true
}

function applyRouteDetailQuery(detailId) {
  const value = String(detailId || '').trim()
  if (!value) {
    routeDetailId.value = ''
    return
  }
  if (value === routeDetailId.value) {
    return
  }

  routeDetailId.value = value
  getRoomDetail(value).then(res => {
    const data = res.data || {}
    handleAudit({
      ...data,
      id: data.id || Number(value)
    })
  }).catch(() => {
    proxy?.$message?.error('获取直播间详情失败')
  })
}

function submitAudit() {
  if (auditForm.audit_status === 2 && !String(auditForm.audit_remark).trim()) {
    proxy?.$message?.warning('驳回时请填写驳回原因')
    return
  }
  auditLoading.value = true
  auditRoom(auditRoomInfo.value.id, auditForm).then(() => {
    proxy?.$message?.success('审核操作成功')
    auditVisible.value = false
    fetchTabStats()
    getRoomList()
  }).finally(() => {
    auditLoading.value = false
  })
}

function handleGenerateStream(force = false) {
  const tip = force === true ? '强制刷新将重新生成推流地址，原地址将失效。确认继续？' : '确认为该直播间生成推流地址？'
  proxy.$confirm(tip, '提示', { type: 'warning' }).then(() => {
    streamGenLoading.value = true
    createRoomStream(streamDialogRoomId.value, force === true).then(res => {
      proxy?.$message?.success('推流地址生成成功')
      streamDialogInfo.value = res.data || {}
    }).finally(() => {
      streamGenLoading.value = false
    })
  })
}

function copyText(text) {
  if (navigator.clipboard) {
    navigator.clipboard.writeText(text).then(() => { proxy?.$message?.success('复制成功') })
  } else {
    const el = document.createElement('input')
    el.value = text
    document.body.appendChild(el)
    el.select()
    document.execCommand('copy')
    document.body.removeChild(el)
    proxy?.$message?.success('复制成功')
  }
}

function handleDelete(id) {
  proxy.$confirm('确定要删除该直播间吗？', '提示', { type: 'warning' }).then(() => {
    deleteRoom(id).then(() => {
      proxy?.$message?.success('删除成功')
      fetchTabStats()
      getRoomList()
    })
  })
}

function handleSwitchStatus(row, status) {
  const actionMap = { 1: '开播', 2: '结束直播' }
  proxy.$confirm(`确定要${actionMap[status]}吗？`, '提示', { type: 'warning' }).then(() => {
    switchRoomStatus(row.id, status).then(res => {
      proxy?.$message?.success(res.data.msg || '操作成功')
      getRoomList()
    })
  })
}

function handleForceOffline(row) {
  const title = row.title ? `「${row.title}」` : '该直播间'
  proxy.$confirm(`确定要强制下架${title}吗？下架后用户端将不可继续观看。`, '强制下架确认', {
    type: 'warning',
    confirmButtonText: '确定下架'
  }).then(() => {
    switchRoomStatus(row.id, 2).then(() => {
      proxy?.$message?.success('强制下架成功')
      getRoomList()
    })
  })
}

function handleToggleHot(row) {
  const action = row.is_hot ? '取消热门' : '设为热门'
  proxy.$confirm(`确定要${action}吗？`, '提示', { type: 'warning' }).then(() => {
    toggleRoomHot(row.id).then(() => {
      proxy?.$message?.success(`${action}成功`)
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

function getAuditLabel(status) {
  const map = { 0: '待审核', 1: '已通过', 2: '已驳回' }
  return map[status] || '未知'
}

function getAuditTagType(status) {
  const map = { 0: 'warning', 1: 'success', 2: 'danger' }
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
    case 'forceOffline':
      handleForceOffline(row)
      break
    case 'delete':
      handleDelete(row.id)
      break
  }
}

watch(activeTab, (tab, prevTab) => {
  if (!tabReady.value || tab === prevTab) {
    return
  }
  applyTabChange(tab)
})

watch(() => route.query.tab, tab => {
  const nextTab = getValidTab(tab || 'all')
  if (nextTab !== activeTab.value) {
    activeTab.value = nextTab
  }
})

watch(() => route.query.detail_id, value => {
  applyRouteDetailQuery(value)
})

onBeforeMount(() => {
  activeTab.value = resolveActiveTab()
  if (route.query.tab || activeTab.value !== 'all') {
    syncRouteTab(activeTab.value)
  }
  getCategoryOptions()
  fetchTabStats()
  applyRouteDetailQuery(route.query.detail_id)
  getRoomList()
  tabReady.value = true
})
</script>

<style lang="scss" scoped>
/* material/stitch/DESIGN.md */
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
  color: var(--curator-on-surface);
  letter-spacing: 0.02em;
}

.page-curator-subtitle {
  margin: 0;
  font-size: 0.875rem;
  color: var(--curator-on-surface-variant);
  line-height: 1.5;
  max-width: 520px;
}

.live-work-surface {
  background: var(--curator-surface-card);
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
}

:deep(.btn-curator-primary.el-button--primary) {
  border: none;
  border-radius: 6px;
  color: var(--curator-on-primary);
  font-weight: 500;
  padding-left: 18px;
  padding-right: 18px;
  box-shadow: 0 2px 8px rgba(0, 87, 194, 0.2);
}

:deep(.btn-curator-reset) {
  border-radius: 6px;
  background: var(--curator-surface-card);
  color: var(--curator-primary);
  border: 1px solid var(--curator-primary);

  &:hover,
  &:focus {
    background: rgba(0, 87, 194, 0.06);
    color: var(--curator-primary-container);
    border-color: var(--curator-primary-container);
  }
}

.live-curator-tabs {
  :deep(.el-tabs__header) {
    margin-bottom: 16px;
  }

  :deep(.el-tabs__item) {
    color: var(--curator-on-surface-variant);
    font-size: 14px;

    &.is-active {
      color: var(--curator-primary);
      font-weight: 600;
    }
  }

  :deep(.el-tabs__active-bar) {
    height: 3px;
    background-color: var(--curator-primary);
    border-radius: 2px 2px 0 0;
  }

  :deep(.el-tabs__nav-wrap::after) {
    height: 1px;
    background-color: var(--curator-outline-variant);
  }
}

.live-search-form {
  margin-bottom: 8px;
}

.live-curator-table {
  margin-top: 8px;

  :deep(.el-table__header th) {
    color: var(--curator-on-surface-variant);
    font-weight: 500;
    background: var(--curator-surface-low);
  }

  :deep(.el-table__row:hover > td) {
    background: var(--curator-surface-low) !important;
  }
}

.pagination-wrap {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}

.live-curator-pagination {
  :deep(.el-pager li.active) {
    background: var(--curator-primary);
    color: var(--curator-on-primary);
    border-radius: 4px;
  }

  :deep(.btn-next),
  :deep(.btn-prev),
  :deep(.el-pager li) {
    &:hover {
      color: var(--curator-primary);
    }
  }
}

.search-input {
  width: 200px;
}

.search-select-sm {
  width: 140px;
}

.search-select-wide {
  width: 260px;
}

.cover-img {
  width: 50px;
  height: 36px;
  border-radius: 4px;
}

.no-cover,
.text-gray {
  color: var(--curator-on-surface-variant);
  font-size: 12px;
}

.danger-link {
  color: var(--dashboard-danger);
}

.more-link {
  color: var(--curator-primary);
  cursor: pointer;
  font-size: 13px;
  transition: color 0.2s ease;

  &:hover,
  &:focus {
    color: var(--curator-primary-container);
  }
}

.stream-dialog-loading {
  padding: 30px;
  text-align: center;
  color: var(--curator-on-surface-variant);
}

.stream-name {
  font-family: monospace;
  color: var(--curator-primary);
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

.stream-label {
  min-width: 64px;
  font-size: 13px;
  color: var(--curator-on-surface-variant);
  flex-shrink: 0;
}

.stream-input {
  flex: 1;
}

a {
  color: var(--curator-primary);
  cursor: pointer;
  font-size: 13px;

  &:hover {
    color: var(--curator-primary-container);
  }
}
</style>

<style lang="scss">
.live-room-more-dropdown {
  background: var(--dashboard-surface, #fff) !important;
  border: 1px solid var(--dashboard-border, #ebe6e1) !important;
  box-shadow: 0 10px 24px rgba(243, 65, 0, 0.12);

  .popper__arrow,
  .popper__arrow::after {
    border-bottom-color: var(--dashboard-border, #ebe6e1) !important;
  }

  &[x-placement^="bottom"] .popper__arrow::after {
    border-bottom-color: var(--dashboard-surface, #fff) !important;
  }

  &[x-placement^="top"] .popper__arrow::after {
    border-top-color: var(--dashboard-surface, #fff) !important;
  }

  .el-dropdown-menu__item {
    color: var(--dashboard-text-primary, #1a1c1c) !important;
    transition: background-color 0.2s ease, color 0.2s ease;

    &:focus,
    &:not(.is-disabled):hover {
      background: var(--dashboard-accent-soft, #fff0ea) !important;
      color: var(--dashboard-accent, #f34100) !important;
    }
  }

  .el-dropdown-menu__item.dropdown-danger {
    color: var(--dashboard-danger, #ba1a1a) !important;

    &:focus,
    &:not(.is-disabled):hover {
      color: var(--dashboard-danger, #ba1a1a) !important;
      background: rgba(186, 26, 26, 0.08) !important;
    }
  }
}
</style>
