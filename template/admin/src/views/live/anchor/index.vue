<template>
  <div class="anchor-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">主播管理</h1>
        <p class="page-curator-subtitle">
          管理平台内所有直播主播的审核状态、所属商户及违规记录。
        </p>
      </div>
      <el-button
        type="primary"
        class="btn-curator-primary"
        icon="el-icon-plus"
        @click="handleAdd"
      >
        新增主播
      </el-button>
    </header>

    <div class="anchor-work-surface">
      <el-tabs v-model="activeTab" class="anchor-curator-tabs" @tab-click="handleTabClick">
        <el-tab-pane :label="`全部 (${tabCounts.all})`" name="all" />
        <el-tab-pane :label="`平台 (${tabCounts.platform})`" name="platform" />
        <el-tab-pane :label="`商户 (${tabCounts.merchant})`" name="merchant" />
        <el-tab-pane :label="`待审核 (${tabCounts.pending})`" name="pending" />
        <el-tab-pane :label="`违规 (${tabCounts.violation})`" name="violation" />
      </el-tabs>

      <el-form
        ref="searchFormRef"
        :model="searchForm"
        inline
        class="search-form anchor-search-form"
        @submit.prevent
      >
        <el-form-item label="主播名称">
          <el-input
            v-model="searchForm.name"
            clearable
            placeholder="请输入主播名称"
            class="search-input"
            prefix-icon="el-icon-search"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item v-if="activeTab !== 'pending'" label="审核状态">
          <el-select
            v-model="searchForm.audit_status"
            placeholder="全部"
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
        <el-form-item label="状态">
          <el-select
            v-model="searchForm.status"
            placeholder="全部"
            clearable
            class="search-select-sm"
          >
            <el-option label="启用" :value="1" />
            <el-option label="禁用" :value="0" />
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

      <el-table
        v-loading="loading"
        :data="anchorList"
        class="anchor-curator-table"
      >
        <el-table-column prop="id" label="ID" width="70" align="center" />
        <el-table-column label="头像" width="70" align="center">
          <template #default="scope">
            <el-avatar
              :src="scope.row.avatar"
              :size="40"
              icon="el-icon-user-solid"
            />
          </template>
        </el-table-column>
        <el-table-column prop="name" label="主播名称" min-width="100" show-overflow-tooltip />
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
        <el-table-column prop="real_name" label="真实姓名" width="100" align="center" />
        <el-table-column prop="phone" label="联系电话" width="120" align="center" />
        <el-table-column prop="direction" label="直播方向" min-width="120" show-overflow-tooltip />
        <el-table-column label="平台抽成" width="100" align="center">
          <template #default="scope">
            {{ formatPlatformCommissionRate(scope.row) }}
          </template>
        </el-table-column>
        <el-table-column label="商户抽成" width="100" align="center">
          <template #default="scope">
            {{ formatAnchorMerchantCommissionRate(scope.row) }}
          </template>
        </el-table-column>
        <el-table-column label="违规次数" width="90" align="center">
          <template #default="scope">
            <el-tag
              :type="scope.row.violation_count >= 3 ? 'danger' : scope.row.violation_count > 0 ? 'warning' : 'info'"
            >
              {{ scope.row.violation_count || 0 }} 次
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="审核状态" width="100" align="center">
          <template #default="scope">
            <el-tag :type="getAuditTagType(scope.row.audit_status)">
              {{ getAuditLabel(scope.row.audit_status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="禁播" width="80" align="center">
          <template #default="scope">
            <el-tag v-if="scope.row.is_live_banned" type="danger">已禁播</el-tag>
            <el-tag v-else type="success">正常</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="启用状态" width="90" align="center">
          <template #default="scope">
            <el-switch
              v-model="scope.row.status"
              :active-value="1"
              :inactive-value="0"
              @change="handleStatusChange(scope.row)"
            />
          </template>
        </el-table-column>
        <el-table-column prop="sort" label="排序" width="70" align="center" />
        <el-table-column prop="create_time" label="创建时间" width="160" align="center" />
        <el-table-column label="操作" width="250" fixed="right" align="center">
          <template #default="scope">
            <a @click="handleEdit(scope.row)">编辑</a>
            <el-divider direction="vertical" />
            <a
              v-if="scope.row.audit_status === 0"
              @click="handleAudit(scope.row)"
            >审核</a>
            <el-divider v-if="scope.row.audit_status === 0" direction="vertical" />
            <a @click="handleToggleBan(scope.row)">
              {{ scope.row.is_live_banned ? '解禁' : '禁播' }}
            </a>
            <el-divider direction="vertical" />
            <a @click="handleAddViolation(scope.row)">违规</a>
            <el-divider direction="vertical" />
            <a class="danger-link" @click="handleDelete(scope.row.id)">删除</a>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrap">
        <el-pagination
          class="anchor-curator-pagination"
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

    <!-- 新增/编辑弹窗 -->
    <el-dialog
      v-model="dialogVisible"
      :title="dialogTitle"
      width="620px"
      :close-on-click-modal="false"
      @close="handleDialogClose"
    >
      <el-form
        ref="editFormRef"
        :model="editForm"
        :rules="editRules"
        label-width="100px"
      >
        <el-form-item label="主播名称" prop="name">
          <el-input v-model="editForm.name" placeholder="请输入主播名称" />
        </el-form-item>
        <el-form-item label="头像">
          <Upload v-model="editForm.avatar" :limit="1" />
        </el-form-item>
        <el-form-item label="真实姓名" prop="real_name">
          <el-input v-model="editForm.real_name" placeholder="请输入真实姓名" />
        </el-form-item>
        <el-form-item label="联系电话" prop="phone">
          <el-input v-model="editForm.phone" placeholder="请输入联系电话" />
        </el-form-item>
        <el-form-item label="直播方向">
          <el-input
            v-model="editForm.direction"
            type="textarea"
            :rows="2"
            placeholder="请输入直播方向说明"
          />
        </el-form-item>
        <el-form-item label="简介">
          <el-input
            v-model="editForm.description"
            type="textarea"
            :rows="3"
            placeholder="请输入主播简介"
          />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="editForm.sort" :min="0" :max="9999" />
        </el-form-item>
        <el-form-item v-if="!isMerchantAnchorEdit" label="平台抽成">
          <div class="commission-control">
            <el-input-number
              v-model="editForm.commission_rate"
              :min="0"
              :max="100"
              :precision="2"
              :step="0.1"
              controls-position="right"
            />
            <span class="commission-unit">%</span>
          </div>
          <div class="form-tip">按平台主播礼物可兑换金额计算</div>
        </el-form-item>
        <el-form-item v-if="isMerchantAnchorEdit" label="平台抽成">
          <div class="commission-control">
            <el-input-number
              v-model="editPlatformCommissionRate"
              :min="0"
              :max="100"
              :precision="2"
              :step="0.1"
              controls-position="right"
            />
            <span class="commission-unit">%</span>
          </div>
          <div class="form-tip">按礼物可兑换金额计算</div>
        </el-form-item>
        <el-form-item v-if="isMerchantAnchorEdit" label="商户抽成">
          <div class="commission-control">
            <el-input-number
              v-model="editForm.commission_rate"
              :min="0"
              :max="100"
              :precision="2"
              :step="0.1"
              controls-position="right"
            />
            <span class="commission-unit">%</span>
          </div>
          <div class="form-tip">按礼物可兑换金额计算</div>
        </el-form-item>
        <el-form-item label="状态">
          <el-radio-group v-model="editForm.status">
            <el-radio :label="1">启用</el-radio>
            <el-radio :label="0">禁用</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="dialogVisible = false">取 消</el-button>
          <el-button type="primary" :loading="submitLoading" @click="handleSubmit">确 定</el-button>
        </span>
      </template>
    </el-dialog>

    <!-- 审核弹窗 -->
    <el-dialog
      v-model="auditVisible"
      title="审核主播"
      width="480px"
      :close-on-click-modal="false"
    >
      <el-form ref="auditFormRef" :model="auditForm" label-width="90px">
        <el-form-item label="主播名称">
          <span>{{ auditAnchor.name }}</span>
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
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, onBeforeMount, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Upload from '@/components/Upload/index'
import {
  getAnchorList as getAnchorListApi,
  getAnchorTabStats,
  getAnchorDetail,
  createAnchor,
  updateAnchor,
  deleteAnchor,
  showAnchor,
  auditAnchor as auditAnchorApi,
  toggleAnchorBan,
  addAnchorViolation
} from '@/api/live'
import { getMerchantList, updateMerchantCommissionRate } from '@/api/merchant'

defineOptions({
  name: 'LiveAnchorList'
})

const TAB_STORAGE_KEY = 'live-anchor-active-tab'
const TAB_OPTIONS = ['all', 'platform', 'merchant', 'pending', 'violation']

const { proxy } = getCurrentInstance() || {}
const route = useRoute()
const router = useRouter()

const searchFormRef = ref(null)
const editFormRef = ref(null)
const auditFormRef = ref(null)
const loading = ref(false)
const anchorList = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(20)
const activeTab = ref('all')
const tabCounts = reactive({
  all: 0,
  platform: 0,
  merchant: 0,
  pending: 0,
  violation: 0
})
const merchantOptions = ref([])
const merchantLoading = ref(false)
const searchForm = reactive({
  name: '',
  audit_status: '',
  status: '',
  merchant_id: ''
})
const dialogVisible = ref(false)
const submitLoading = ref(false)
const editId = ref(0)
const editMerchantId = ref(0)
const editPlatformCommissionRate = ref(0)
const editForm = reactive({
  name: '',
  avatar: '',
  real_name: '',
  phone: '',
  direction: '',
  description: '',
  commission_rate: 0,
  sort: 0,
  status: 1
})
const editRules = {
  name: [{ required: true, message: '请输入主播名称', trigger: 'blur' }],
  real_name: [{ required: true, message: '请输入真实姓名', trigger: 'blur' }],
  phone: [{ required: true, message: '请输入联系电话', trigger: 'blur' }]
}
const auditVisible = ref(false)
const auditLoading = ref(false)
const auditAnchor = ref({})
const routeDetailId = ref('')
const auditForm = reactive({
  audit_status: 1,
  audit_remark: ''
})

const DEFAULT_SEARCH_FORM = {
  name: '',
  audit_status: '',
  status: '',
  merchant_id: ''
}
const DEFAULT_EDIT_FORM = {
  name: '',
  avatar: '',
  real_name: '',
  phone: '',
  direction: '',
  description: '',
  commission_rate: 0,
  sort: 0,
  status: 1
}
const DEFAULT_AUDIT_FORM = {
  audit_status: 1,
  audit_remark: ''
}

const dialogTitle = computed(() => (editId.value ? '编辑主播' : '添加主播'))
const isMerchantAnchorEdit = computed(() => Number(editMerchantId.value || 0) > 0)

watch(() => route.query.tab, tab => {
  const nextTab = getValidTab(tab || 'all')
  if (nextTab !== activeTab.value) {
    applyTabChange(nextTab, { syncRoute: false })
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
  fetchTabStats()
  applyRouteDetailQuery(route.query.detail_id)
  fetchAnchorList()
})

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

  const navigation = router.replace({
    path: route.path,
    query
  })
  if (navigation && typeof navigation.catch === 'function') {
    navigation.catch(() => {})
  }
}

function applyTabChange(tab, options = {}) {
  const nextTab = getValidTab(tab)
  activeTab.value = nextTab
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
    fetchAnchorList()
  }
}

function fetchTabStats() {
  getAnchorTabStats().then(res => {
    const d = res.data || {}
    Object.assign(tabCounts, {
      all: d.all || 0,
      platform: d.platform || 0,
      merchant: d.merchant || 0,
      pending: d.pending || 0,
      violation: d.violation || 0
    })
  })
}

function handleTabClick(tab) {
  applyTabChange(tab?.props?.name || tab?.paneName || tab?.name)
}

function remoteMerchant(query) {
  if (query === '') {
    merchantOptions.value = []
    return
  }
  merchantLoading.value = true
  getMerchantList({ page: 1, limit: 50, mer_name: query })
    .then(res => {
      merchantOptions.value = res.data.list || []
    })
    .finally(() => {
      merchantLoading.value = false
    })
}

function formatMerchantCol(row) {
  const mid = row.merchant_id
  if (!mid) {
    return '平台'
  }
  const m = row.merchant
  if (m && m.mer_name) {
    return `${m.mer_name}（id：${mid}）`
  }
  return `未知商户（id：${mid}）`
}

function formatCommissionRate(value) {
  const rate = Number(value || 0)
  return rate.toFixed(2)
}

function resolvePlatformCommissionRate(row) {
  const merchantId = Number(row && row.merchant_id || 0)
  if (merchantId <= 0) {
    return 0
  }
  const merchant = row && row.merchant ? row.merchant : {}
  return Number(merchant.commission_rate || 0)
}

function formatPlatformCommissionRate(row) {
  const merchantId = Number(row && row.merchant_id || 0)
  if (merchantId <= 0) {
    return `${formatCommissionRate(row && row.commission_rate)}%`
  }
  return `${formatCommissionRate(resolvePlatformCommissionRate(row))}%`
}

function formatAnchorMerchantCommissionRate(row) {
  const merchantId = Number(row && row.merchant_id || 0)
  if (merchantId <= 0) {
    return '-'
  }
  return `${formatCommissionRate(row.commission_rate)}%`
}

function fetchAnchorList() {
  loading.value = true
  getAnchorListApi({
    page: page.value,
    limit: limit.value,
    tab: activeTab.value,
    ...searchForm
  }).then(res => {
    anchorList.value = res.data.list || res.data || []
    total.value = res.data.total || 0
  }).finally(() => {
    loading.value = false
  })
}

function handleSearch() {
  page.value = 1
  fetchAnchorList()
}

function handleReset() {
  searchFormRef.value?.resetFields?.()
  Object.assign(searchForm, DEFAULT_SEARCH_FORM)
  handleSearch()
}

function handleAdd() {
  editId.value = 0
  editMerchantId.value = 0
  editPlatformCommissionRate.value = 0
  Object.assign(editForm, DEFAULT_EDIT_FORM)
  dialogVisible.value = true
}

function handleEdit(row) {
  const id = Number(row && row.id || row || 0)
  const rowMerchantId = Number(row && row.merchant_id || 0)
  const rowPlatformCommissionRate = resolvePlatformCommissionRate(row)
  getAnchorDetail(id).then(res => {
    const d = res.data || {}
    editId.value = d.id
    editMerchantId.value = Number(d.merchant_id || rowMerchantId || 0)
    const detailPlatformCommissionRate = resolvePlatformCommissionRate(d)
    editPlatformCommissionRate.value = d.merchant ? detailPlatformCommissionRate : rowPlatformCommissionRate
    Object.assign(editForm, {
      name: d.name,
      avatar: d.avatar || '',
      real_name: d.real_name || '',
      phone: d.phone || '',
      direction: d.direction || '',
      description: d.description || '',
      commission_rate: Number(d.commission_rate || 0),
      sort: d.sort || 0,
      status: d.status
    })
    dialogVisible.value = true
  })
}

async function handleSubmit() {
  const valid = await Promise.resolve(editFormRef.value?.validate?.()).catch(() => false)
  if (!valid) return

  submitLoading.value = true
  try {
    const payload = { ...editForm }
    const api = editId.value ? updateAnchor(editId.value, payload) : createAnchor(payload)
    await api
    if (isMerchantAnchorEdit.value) {
      await updateMerchantCommissionRate(editMerchantId.value, editPlatformCommissionRate.value)
    }
    proxy?.$message?.success(editId.value ? '编辑成功' : '添加成功')
    dialogVisible.value = false
    fetchTabStats()
    fetchAnchorList()
  } finally {
    submitLoading.value = false
  }
}

function handleDialogClose() {
  editFormRef.value?.resetFields?.()
}

async function confirmAction(message, title = '提示') {
  const confirm = proxy?.$confirm
  if (!confirm) {
    return false
  }
  return confirm(message, title, { type: 'warning' }).then(() => true).catch(() => false)
}

async function handleDelete(id) {
  const confirmed = await confirmAction('确定要删除该主播吗？')
  if (!confirmed) return

  await deleteAnchor(id)
  proxy?.$message?.success('删除成功')
  fetchTabStats()
  fetchAnchorList()
}

function handleStatusChange(row) {
  showAnchor(row.id).then(() => {
    proxy?.$message?.success('状态更新成功')
  }).catch(() => {
    row.status = row.status === 1 ? 0 : 1
  })
}

function handleAudit(anchor) {
  auditAnchor.value = anchor
  Object.assign(auditForm, DEFAULT_AUDIT_FORM)
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
  getAnchorDetail(value).then(res => {
    const data = res.data || {}
    handleAudit({
      ...data,
      id: data.id || Number(value)
    })
  }).catch(() => {
    proxy?.$message?.error('获取主播详情失败')
  })
}

function submitAudit() {
  if (auditForm.audit_status === 2 && !auditForm.audit_remark) {
    proxy?.$message?.warning('驳回时请填写驳回原因')
    return
  }
  auditLoading.value = true
  auditAnchorApi(auditAnchor.value.id, { ...auditForm }).then(() => {
    proxy?.$message?.success('审核操作成功')
    auditVisible.value = false
    fetchTabStats()
    fetchAnchorList()
  }).finally(() => {
    auditLoading.value = false
  })
}

async function handleToggleBan(anchor) {
  const action = anchor.is_live_banned ? '解除禁播' : '禁播'
  const confirmed = await confirmAction(`确定要${action}该主播吗？`)
  if (!confirmed) return

  const res = await toggleAnchorBan(anchor.id)
  proxy?.$message?.success(res.data.msg || `${action}成功`)
  fetchTabStats()
  fetchAnchorList()
}

async function handleAddViolation(anchor) {
  const confirmed = await confirmAction(`确定为主播「${anchor.name}」记录一次违规？累计3次将永久禁播。`, '记录违规')
  if (!confirmed) return

  const res = await addAnchorViolation(anchor.id)
  proxy?.$message?.success(`违规记录已更新，当前违规 ${res.data.violation_count} 次`)
  fetchTabStats()
  fetchAnchorList()
}

function getAuditLabel(status) {
  const map = { 0: '待审核', 1: '已通过', 2: '已驳回' }
  return map[status] || '未知'
}

function getAuditTagType(status) {
  const map = { 0: 'info', 1: 'success', 2: 'danger' }
  return map[status] || 'info'
}

function handleSizeChange(val) {
  limit.value = val
  page.value = 1
  fetchAnchorList()
}

function handleCurrentChange(val) {
  page.value = val
  fetchAnchorList()
}
</script>

<style lang="scss" scoped>
/* material/stitch/DESIGN.md */
.anchor-curator-page {
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

.anchor-work-surface {
  background: var(--curator-surface-card);
  border-radius: 12px;
  padding: 15px;
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

.anchor-curator-tabs {
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

.anchor-search-form {
  margin-bottom: 8px;
}

.anchor-curator-table {
  margin-top: 8px;

  :deep(.el-table__header th) {
    color: var(--curator-on-surface-variant);
    font-weight: 500;
    background: rgba(var(--curator-surface-low), 0.5);
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

.anchor-curator-pagination {
  :deep(.el-pager li.active) {
    background: var(--curator-primary);
    color: var(--ds-on-primary);
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

.search-select-wide {
  width: 260px;
}

.form-tip {
  margin-top: 6px;
  color: var(--curator-on-surface-variant);
  font-size: 12px;
  line-height: 18px;
}

.commission-control {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.commission-unit {
  color: var(--curator-on-surface-variant);
  font-size: 13px;
}

.search-input {
  width: 200px;
}

.search-select,
.search-select-sm {
  width: 140px;
}

.danger-link {
  color: #f56c6c;
}

a {
  color: var(--curator-primary);
  cursor: pointer;
  font-size: 13px;

  &:hover {
    color: var(--curator-primary)-container;
  }
}
</style>
