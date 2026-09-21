<template>
  <div class="apply-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">商户入驻申请</h1>
        <p class="page-curator-subtitle">
          审核商户入驻申请，支持查看详情、通过或拒绝操作。
        </p>
      </div>
    </header>

    <div class="panel panel--search">
      <el-form ref="searchFormRef" :model="searchForm" inline class="search-form" @submit.prevent>
        <el-form-item label="申请人信息">
          <el-input
            v-model="searchForm.keyword"
            clearable
            placeholder="姓名/手机号"
            class="search-input"
            prefix-icon="el-icon-search"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="商户名称">
          <el-input
            v-model="searchForm.mer_name"
            clearable
            placeholder="请输入商户名称"
            class="search-input"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="审核状态">
          <el-select v-model="searchForm.audit_status" placeholder="全部" clearable class="search-select-sm">
            <el-option label="待审核" :value="0" />
            <el-option label="已通过" :value="1" />
            <el-option label="已拒绝" :value="-1" />
          </el-select>
        </el-form-item>
        <el-form-item label="申请时间">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="yyyy-MM-dd"

            style="width: 240px;"
            @change="handleDateChange"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" class="btn-curator-primary" icon="el-icon-search" @click="handleSearch">查询</el-button>
          <el-button class="btn-curator-reset" icon="el-icon-refresh" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="mb12">
      <el-col :span="6">
        <div class="stat-badge stat-badge--green">
          <span class="stat-badge__label">总申请数</span>
          <div class="stat-badge__body">
            <span class="stat-badge__value">{{ stats.total || 0 }}</span>
            <span class="stat-badge__sub stat-badge__sub--up">全部</span>
          </div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-badge stat-badge--gold">
          <span class="stat-badge__label">待审核</span>
          <div class="stat-badge__body">
            <span class="stat-badge__value">{{ stats.pending || 0 }}</span>
            <span class="stat-badge__sub stat-badge__sub--warn">待处理</span>
          </div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-badge stat-badge--purple">
          <span class="stat-badge__label">已通过</span>
          <div class="stat-badge__body">
            <span class="stat-badge__value">{{ stats.passed || 0 }}</span>
            <span class="stat-badge__sub stat-badge__sub--tag">已完成</span>
          </div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-badge stat-badge--red">
          <span class="stat-badge__label">已拒绝</span>
          <div class="stat-badge__body">
            <span class="stat-badge__value">{{ stats.rejected || 0 }}</span>
            <span class="stat-badge__sub stat-badge__sub--down">已驳回</span>
          </div>
        </div>
      </el-col>
    </el-row>

    <div class="panel panel--table">
      <el-table v-loading="loading" :data="list" class="apply-curator-table">
        <el-table-column prop="id" label="ID" width="70" align="center" />
        <el-table-column label="申请人" min-width="130">
          <template #default="scope">
            <div class="user-info">
              <el-avatar :src="scope.row.avatar" :size="32" icon="el-icon-user-solid" />
              <div>
                <div class="user-name">{{ scope.row.real_name || scope.row.nickname || '-' }}</div>
                <div class="user-phone">{{ scope.row.phone || '-' }}</div>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="mer_name" label="申请商户名" min-width="130" show-overflow-tooltip />
        <el-table-column prop="mer_phone" label="商户电话" width="130" align="center" />
        <el-table-column prop="mer_address" label="商户地址" min-width="150" show-overflow-tooltip>
          <template #default="scope">{{ scope.row.mer_address || '-' }}</template>
        </el-table-column>
        <el-table-column label="审核状态" width="100" align="center">
          <template #default="scope">
            <el-tag :type="getAuditTagType(scope.row.audit_status)">
              {{ getAuditLabel(scope.row.audit_status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="refuse_reason" label="拒绝原因" min-width="140" show-overflow-tooltip>
          <template #default="scope">
            <span class="text-danger">{{ scope.row.refuse_reason || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="create_time" label="申请时间" width="160" align="center" />
        <el-table-column label="操作" width="160" fixed="right" align="center">
          <template #default="scope">
            <a @click="handleDetail(scope.row)">详情</a>
            <template v-if="scope.row.audit_status === 0">
              <el-divider direction="vertical" />
              <a class="success-link" @click="handlePass(scope.row)">通过</a>
              <el-divider direction="vertical" />
              <a class="danger-link" @click="handleReject(scope.row)">拒绝</a>
            </template>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrap">
        <el-pagination
          class="apply-curator-pagination"
          :current-page="page"
          :page-sizes="[15, 20, 50, 100]"
          :page-size="limit"
          layout="total, sizes, prev, pager, next, jumper"
          :total="total"
          @size-change="handleSizeChange"
          @current-change="handleCurrentChange"
        />
      </div>
    </div>

    <!-- 申请详情弹窗 -->
    <el-dialog
      v-model="detailVisible"
      title="申请详情"
      width="680px"
      :close-on-click-modal="false"
    >
      <div v-if="currentApply" v-loading="detailLoading">
        <el-descriptions :column="2" border>
          <el-descriptions-item label="申请人">{{ currentApply.real_name || currentApply.nickname || '-' }}</el-descriptions-item>
          <el-descriptions-item label="联系手机">{{ currentApply.phone || '-' }}</el-descriptions-item>
          <el-descriptions-item label="商户名称">{{ currentApply.mer_name }}</el-descriptions-item>
          <el-descriptions-item label="商户电话">{{ currentApply.mer_phone || '-' }}</el-descriptions-item>
          <el-descriptions-item label="商户地址" :span="2">{{ currentApply.mer_address || '-' }}</el-descriptions-item>
          <el-descriptions-item label="商户简介" :span="2">{{ currentApply.mer_info || '-' }}</el-descriptions-item>
          <el-descriptions-item label="审核状态">
            <el-tag :type="getAuditTagType(currentApply.audit_status)">
              {{ getAuditLabel(currentApply.audit_status) }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="申请时间">{{ currentApply.create_time }}</el-descriptions-item>
          <el-descriptions-item v-if="currentApply.refuse_reason" label="拒绝原因" :span="2">
            <span class="text-danger">{{ currentApply.refuse_reason }}</span>
          </el-descriptions-item>
        </el-descriptions>
      </div>
      <template #footer>
        <span>
          <el-button @click="detailVisible = false">关 闭</el-button>
          <template v-if="currentApply && currentApply.audit_status === 0">
            <el-button type="success" @click="handlePassFromDetail">通 过</el-button>
            <el-button type="danger" @click="handleRejectFromDetail">拒 绝</el-button>
          </template>
        </span>
      </template>
    </el-dialog>

    <!-- 拒绝原因弹窗 -->
    <el-dialog
      v-model="rejectVisible"
      title="填写拒绝原因"
      width="460px"
      :close-on-click-modal="false"
    >
      <el-form ref="rejectFormRef" :model="rejectForm" :rules="rejectRules" label-width="90px">
        <el-form-item label="拒绝原因" prop="reason">
          <el-input
            v-model="rejectForm.reason"
            type="textarea"
            :rows="4"
            placeholder="请填写拒绝原因（将通知申请人）"
            maxlength="200"
            show-word-limit
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <span>
          <el-button @click="rejectVisible = false">取 消</el-button>
          <el-button type="danger" :loading="rejectLoading" @click="handleRejectSubmit">确认拒绝</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { getCurrentInstance, nextTick, onBeforeMount, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { getApplyList, merchantApplyRead, auditApply } from '@/api/merchant'

defineOptions({
  name: 'MerchantApply'
})

const { proxy } = getCurrentInstance()
const message = proxy.$message
const confirm = proxy.$confirm
const route = useRoute()

const createSearchForm = () => ({
  keyword: '',
  mer_name: '',
  audit_status: '',
  start_time: '',
  end_time: ''
})

const createRejectForm = () => ({
  reason: ''
})

const createDefaultStats = () => ({
  total: 0,
  pending: 0,
  passed: 0,
  rejected: 0
})

const loading = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(15)
const dateRange = ref([])
const stats = reactive(createDefaultStats())
const searchFormRef = ref(null)
const searchForm = reactive(createSearchForm())
const detailVisible = ref(false)
const detailLoading = ref(false)
const currentApply = ref(null)
const routeDetailId = ref('')
const rejectVisible = ref(false)
const rejectLoading = ref(false)
const rejectTargetId = ref(null)
const rejectFormRef = ref(null)
const rejectForm = reactive(createRejectForm())
const rejectRules = {
  reason: [{ required: true, message: '请填写拒绝原因', trigger: 'blur' }]
}

function getList() {
  loading.value = true
  const params = { page: page.value, limit: limit.value, ...searchForm }
  getApplyList(params).then(res => {
    const data = res.data || {}
    list.value = (data.list || []).map(item => normalizeApplyData(item))
    total.value = data.total || 0
    if (data.stats) Object.assign(stats, createDefaultStats(), data.stats)
  }).finally(() => {
    loading.value = false
  })
}

function normalizeApplyData(row = {}) {
  const rawStatus = row.audit_status !== undefined ? row.audit_status : row.status
  const status = Number(rawStatus)
  return {
    ...row,
    audit_status: status === 2 ? -1 : status,
    refuse_reason: row.refuse_reason || row.fail_msg || ''
  }
}

function handleDateChange(val) {
  if (val && val.length === 2) {
    searchForm.start_time = val[0]
    searchForm.end_time = val[1]
  } else {
    searchForm.start_time = ''
    searchForm.end_time = ''
  }
}

function handleSearch() {
  page.value = 1
  getList()
}

function handleReset() {
  Object.assign(searchForm, createSearchForm())
  dateRange.value = []
  page.value = 1
  searchFormRef.value?.clearValidate?.()
  getList()
}

function handleSizeChange(val) {
  limit.value = val
  page.value = 1
  getList()
}

function handleCurrentChange(val) {
  page.value = val
  getList()
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
  openRouteApplyDetail(value)
}

function openRouteApplyDetail(id) {
  detailVisible.value = true
  detailLoading.value = true
  currentApply.value = null
  merchantApplyRead(id).then(res => {
    currentApply.value = res.data ? normalizeApplyData(res.data) : null
  }).finally(() => {
    detailLoading.value = false
  })
}

function handleDetail(row) {
  currentApply.value = normalizeApplyData(row)
  detailVisible.value = true
}

function handlePass(row) {
  confirm('确定通过该商户入驻申请吗？', '提示', { type: 'warning' }).then(() => {
    auditApply(row.id, { audit_status: 1 }).then(res => {
      message.success(res.message || '操作成功')
      getList()
    })
  }).catch(() => {})
}

function handlePassFromDetail() {
  if (!currentApply.value) return
  handlePass(currentApply.value)
  detailVisible.value = false
}

function handleReject(row) {
  rejectTargetId.value = row.id
  Object.assign(rejectForm, createRejectForm())
  rejectVisible.value = true
  nextTick(() => {
    rejectFormRef.value?.clearValidate?.()
  })
}

function handleRejectFromDetail() {
  if (!currentApply.value) return
  detailVisible.value = false
  handleReject(currentApply.value)
}

async function handleRejectSubmit() {
  if (!rejectFormRef.value) return
  const valid = await rejectFormRef.value.validate().catch(() => false)
  if (!valid) return
  rejectLoading.value = true
  auditApply(rejectTargetId.value, { audit_status: -1, refuse_reason: rejectForm.reason }).then(res => {
    message.success(res.message || '操作成功')
    rejectVisible.value = false
    getList()
  }).finally(() => {
    rejectLoading.value = false
  })
}

function getAuditLabel(status) {
  const map = { 0: '待审核', 1: '已通过', '-1': '已拒绝' }
  return map[String(status)] || '-'
}

function getAuditTagType(status) {
  const map = { 0: 'warning', 1: 'success', '-1': 'danger' }
  return map[String(status)] || 'info'
}

function handleRouteInit() {
  const routeAuditStatus = route.query.audit_status
  if (routeAuditStatus !== undefined && routeAuditStatus !== '') {
    searchForm.audit_status = Number(routeAuditStatus)
  }
  applyRouteDetailQuery(route.query.detail_id)
  getList()
}

watch(() => route.query.detail_id, value => {
  applyRouteDetailQuery(value)
})

onBeforeMount(() => {
  handleRouteInit()
})
</script>

<style lang="scss" scoped>
/* material/stitch/DESIGN.md */
.apply-curator-page {
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

.panel {
  background: var(--curator-surface-card);
  border-radius: 12px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
  margin-bottom: 12px;

  &--search {
    padding: 20px 24px 4px;
  }

  &--table {
    padding: 16px 24px;
  }
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

.search-form {
  margin-bottom: 8px;

  :deep(.el-form-item) {
    margin-bottom: 16px;
    margin-right: 16px;
  }
}

.apply-curator-table {
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

.apply-curator-pagination {
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

.search-input { width: 180px; }
.search-select-sm { width: 120px; }

.mb12 {
  margin-bottom: 12px;
}

/* ---- 统计徽章卡片 ---- */
.stat-badge {
  position: relative;
  background: var(--curator-surface-card);
  border: 1px solid var(--curator-outline);
  border-radius: 8px;
  padding: 18px 20px 16px 24px;
  min-height: 80px;
  overflow: hidden;

  &::before {
    content: '';
    position: absolute;
    left: 0;
    top: 12px;
    bottom: 12px;
    width: 4px;
    border-radius: 0 4px 4px 0;
  }

  &--green::before { background: linear-gradient(180deg, #43d79a 0%, #1db87b 100%); }
  &--gold::before { background: linear-gradient(180deg, #fbbf24 0%, #d97706 100%); }
  &--purple::before { background: linear-gradient(180deg, #a78bfa 0%, #7c3aed 100%); }
  &--red::before { background: linear-gradient(180deg, #fc8181 0%, #e53e3e 100%); }
}

.stat-badge__label {
  display: block;
  font-size: 13px;
  color: var(--curator-on-surface-variant);
  margin-bottom: 10px;
  line-height: 1;
}

.stat-badge__body {
  display: flex;
  align-items: baseline;
  gap: 8px;
}

.stat-badge__value {
  font-size: 26px;
  font-weight: 700;
  color: var(--curator-on-surface);
  line-height: 1;
  letter-spacing: -0.5px;
}

.stat-badge__sub {
  font-size: 13px;
  font-weight: 500;
  line-height: 1;

  &--up { color: #1db87b; }
  &--down { color: #f56c6c; }
  &--tag { color: #7c3aed; }
  &--warn { color: #d97706; }
}

.user-info {
  display: flex;
  align-items: center;
  gap: 8px;
  .user-name { font-size: 13px; font-weight: 500; }
  .user-phone { font-size: 12px; color: #909399; }
}

.text-danger { color: #f56c6c; }
.success-link { color: #67c23a; }
.danger-link { color: #f56c6c; }
a { color: #409eff; cursor: pointer; }
a:hover { opacity: 0.8; }

.pagination-wrap {
  padding: 16px 0 0;
  display: flex;
  justify-content: flex-end;
}
</style>
