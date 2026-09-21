<template>
  <div class="withdraw-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">提现管理</h1>
        <p class="page-curator-subtitle">
          审核商户提现申请，支持通过或拒绝操作，查看提现统计数据。
        </p>
      </div>
    </header>

    <div class="panel panel--search">
      <el-form ref="searchFormRef" :model="searchForm" inline class="search-form" @submit.prevent>
        <el-form-item label="商户信息">
          <el-input
            v-model="searchForm.keyword"
            clearable
            placeholder="商户名称/ID/收款人"
            class="search-input"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="提现方式">
          <el-select v-model="searchForm.withdraw_type" placeholder="全部" clearable class="search-select-sm">
            <el-option label="银行卡" value="bank" />
            <el-option label="微信" value="wechat" />
            <el-option label="支付宝" value="alipay" />
          </el-select>
        </el-form-item>
        <el-form-item label="审核状态">
          <el-select v-model="searchForm.status" placeholder="全部" clearable class="search-select-sm">
            <el-option label="待审核" :value="0" />
            <el-option label="已通过" :value="1" />
            <el-option label="已拒绝" :value="2" />
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
        <div class="stat-badge stat-badge--gold">
          <span class="stat-badge__label">待审核</span>
          <div class="stat-badge__body">
            <span class="stat-badge__value">{{ stats.pending || 0 }}</span>
            <span class="stat-badge__sub stat-badge__sub--warn">待处理</span>
          </div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-badge stat-badge--green">
          <span class="stat-badge__label">已通过金额</span>
          <div class="stat-badge__body">
            <span class="stat-badge__value">¥{{ stats.approved_amount || '0.00' }}</span>
            <span class="stat-badge__sub stat-badge__sub--up">累计</span>
          </div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-badge stat-badge--purple">
          <span class="stat-badge__label">今日申请</span>
          <div class="stat-badge__body">
            <span class="stat-badge__value">{{ stats.today || 0 }}</span>
            <span class="stat-badge__sub stat-badge__sub--tag">今日</span>
          </div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-badge stat-badge--red">
          <span class="stat-badge__label">本月通过金额</span>
          <div class="stat-badge__body">
            <span class="stat-badge__value">¥{{ stats.month_amount || '0.00' }}</span>
            <span class="stat-badge__sub stat-badge__sub--down">月累计</span>
          </div>
        </div>
      </el-col>
    </el-row>

    <div class="panel panel--table">
      <el-table v-loading="loading" :data="list" class="withdraw-curator-table">
        <el-table-column prop="id" label="ID" width="70" align="center" />
        <el-table-column label="商户" min-width="130" show-overflow-tooltip>
          <template #default="scope">
            <span v-if="scope.row.merchant && scope.row.merchant.mer_name">{{ scope.row.merchant.mer_name }}</span>
            <span v-else>商户ID:{{ scope.row.merchant_id || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="提现金额" width="120" align="center">
          <template #default="scope">
            <span class="price-text">¥{{ scope.row.amount }}</span>
          </template>
        </el-table-column>
        <el-table-column label="提现方式" width="100" align="center">
          <template #default="scope">
            <el-tag>{{ scope.row.withdraw_type_text || getWithdrawTypeName(scope.row.withdraw_type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="bank_name" label="收款账号" min-width="150" show-overflow-tooltip>
          <template #default="scope">
            <div v-if="getWithdrawType(scope.row) === 'bank'" class="account-info">
              <div>{{ scope.row.bank_name || '-' }}</div>
              <div class="account-info__sub">
                {{ scope.row.account_name || '-' }} {{ scope.row.bank_account || '-' }}
              </div>
            </div>
            <div v-else class="account-info account-info--qrcode">
              <el-image
                v-if="scope.row.payment_qrcode"
                :src="scope.row.payment_qrcode"
                :preview-src-list="[scope.row.payment_qrcode]"
                class="qrcode-thumb"
              />
              <div>
                <div>{{ scope.row.account_name || '-' }}</div>
                <div class="account-info__sub">{{ scope.row.account_summary || '-' }}</div>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="审核状态" width="100" align="center">
          <template #default="scope">
            <el-tag :type="getStatusTagType(scope.row.status)">
              {{ getStatusLabel(scope.row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="remark" label="审核备注" min-width="130" show-overflow-tooltip>
          <template #default="scope">
            <span v-if="scope.row.status === 2" class="fail-msg">{{ scope.row.remark || '-' }}</span>
            <span v-else-if="scope.row.remark">{{ scope.row.remark }}</span>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column prop="create_time" label="申请时间" width="160" align="center" />
        <el-table-column label="操作" width="150" fixed="right" align="center">
          <template #default="scope">
            <a class="detail-link" @click="handleDetail(scope.row)">详情</a>
            <template v-if="scope.row.status === 0">
              <el-divider direction="vertical" />
              <a class="success-link" @click="handleAudit(scope.row, 1)">通过</a>
              <el-divider direction="vertical" />
              <a class="danger-link" @click="openRefuseDialog(scope.row)">拒绝</a>
            </template>
            <span v-else class="text-gray">已处理</span>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrap">
        <el-pagination
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

    <el-drawer
      v-model="detailVisible"
      title="提现详情"
      direction="rtl"
      size="720px"
      :wrapper-closable="true"
      custom-class="withdraw-detail-drawer"
      @close="handleDetailClose"
    >
      <div v-if="detailData" class="withdraw-detail">
        <el-descriptions :column="2" border>
          <el-descriptions-item label="ID">{{ detailData.id || '-' }}</el-descriptions-item>
          <el-descriptions-item label="商户ID">{{ detailData.merchant_id || '-' }}</el-descriptions-item>
          <el-descriptions-item label="商户名称">{{ (detailData.merchant && detailData.merchant.mer_name) || '-' }}</el-descriptions-item>
          <el-descriptions-item label="提现方式">{{ detailData.withdraw_type_text || getWithdrawTypeName(detailData.withdraw_type) }}</el-descriptions-item>
          <el-descriptions-item label="申请时间">{{ detailData.create_time || '-' }}</el-descriptions-item>
          <el-descriptions-item label="审核时间">{{ detailData.audit_time || '-' }}</el-descriptions-item>
          <el-descriptions-item label="提现金额">
            <span class="price-text">¥{{ detailData.amount || '0.00' }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="审核状态">
            <el-tag :type="getStatusTagType(detailData.status)">
              {{ getStatusLabel(detailData.status) }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="收款账号" :span="2">
            <template v-if="getWithdrawType(detailData) === 'bank'">
              <div>{{ detailData.bank_name || '-' }}</div>
              <div class="account-info__sub">{{ detailData.account_name || '-' }} {{ detailData.bank_account || '-' }}</div>
            </template>
            <template v-else>
              <div>{{ detailData.account_name || '-' }}</div>
              <div class="account-info__sub">{{ detailData.account_summary || '-' }}</div>
            </template>
          </el-descriptions-item>
          <el-descriptions-item label="审核备注" :span="2">{{ detailData.remark || '-' }}</el-descriptions-item>
        </el-descriptions>

        <div v-if="Number(detailData.status) === 0" class="withdraw-detail__actions">
          <el-button type="success" :loading="auditLoading" @click="handlePassFromDetail">通过</el-button>
          <el-button type="danger" plain :loading="auditLoading" @click="handleRejectFromDetail">拒绝</el-button>
        </div>
      </div>
    </el-drawer>

    <!-- 拒绝弹窗 -->
    <el-dialog v-model="refuseVisible" title="拒绝提现" width="420px" :close-on-click-modal="false">
      <el-form label-width="80px">
        <el-form-item label="拒绝原因">
          <el-input
            v-model="refuseMsg"
            type="textarea"
            :rows="3"
            placeholder="请输入拒绝原因（必填）"
            maxlength="200"
            show-word-limit
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <span>
          <el-button @click="refuseVisible = false">取 消</el-button>
          <el-button type="danger" :loading="auditLoading" @click="handleRefuse">确认拒绝</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { getCurrentInstance, onBeforeMount, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { getWithdrawList, auditWithdraw } from '@/api/merchant'

defineOptions({
  name: 'MerchantWithdraw'
})

const { proxy } = getCurrentInstance()
const message = proxy.$message
const confirm = proxy.$confirm
const route = useRoute()

const createSearchForm = () => ({
  keyword: '',
  withdraw_type: '',
  status: '',
  start_time: '',
  end_time: ''
})

const createDefaultStats = () => ({
  pending: 0,
  approved_amount: '0.00',
  today: 0,
  month_amount: '0.00'
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
const detailData = ref(null)
const routeDetailId = ref('')
const refuseVisible = ref(false)
const refuseMsg = ref('')
const refuseRow = ref(null)
const auditLoading = ref(false)

function getList() {
  loading.value = true
  getWithdrawList({ page: page.value, limit: limit.value, ...searchForm }).then(res => {
    const data = res.data || {}
    list.value = data.list || []
    total.value = data.total || 0
    if (data.stats) Object.assign(stats, createDefaultStats(), data.stats)
    tryOpenDetailFromRoute()
  }).finally(() => {
    loading.value = false
  })
}

function applyRouteDetailQuery(detailId) {
  const value = String(detailId || '').trim()
  if (!value) {
    const previousId = routeDetailId.value
    routeDetailId.value = ''
    if (previousId && String(searchForm.keyword || '') === String(previousId)) {
      searchForm.keyword = ''
      page.value = 1
      getList()
    }
    return
  }
  if (value === routeDetailId.value) {
    return
  }

  routeDetailId.value = value
  Object.assign(searchForm, createSearchForm(), { keyword: value })
  dateRange.value = []
  page.value = 1
  getList()
}

function tryOpenDetailFromRoute() {
  if (!routeDetailId.value || detailVisible.value) {
    return
  }

  const target = list.value.find(item => String(item.id) === String(routeDetailId.value))
  if (!target) {
    return
  }

  detailData.value = target
  detailVisible.value = true
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

function handleDetail(row) {
  detailData.value = row
  detailVisible.value = true
}

function handleDetailClose() {
  detailData.value = null
}

function handlePassFromDetail() {
  if (!detailData.value) return
  handleAudit(detailData.value, 1)
  detailVisible.value = false
}

function handleRejectFromDetail() {
  if (!detailData.value) return
  detailVisible.value = false
  openRefuseDialog(detailData.value)
}

function handleAudit(row, status) {
  const label = status === 1 ? '通过' : '拒绝'
  confirm(`确定${label}该提现申请？`, '提示', { type: 'warning' }).then(() => {
    auditWithdraw(row.id, { status }).then(res => {
      message.success(res.message || '操作成功')
      getList()
    })
  }).catch(() => {})
}

function openRefuseDialog(row) {
  refuseRow.value = row
  refuseMsg.value = ''
  refuseVisible.value = true
}

function handleRefuse() {
  if (!refuseMsg.value.trim()) {
    message.warning('请输入拒绝原因')
    return
  }
  if (!refuseRow.value) {
    message.error('提现申请信息异常')
    return
  }
  auditLoading.value = true
  auditWithdraw(refuseRow.value.id, { status: 2, remark: refuseMsg.value }).then(res => {
    message.success(res.message || '操作成功')
    refuseVisible.value = false
    getList()
  }).finally(() => {
    auditLoading.value = false
  })
}

function getWithdrawType(row) {
  return (row && row.withdraw_type) || 'bank'
}

function getWithdrawTypeName(type) {
  const map = { bank: '银行卡', alipay: '支付宝', wechat: '微信' }
  return map[type] || '-'
}

function getStatusLabel(status) {
  const map = { 0: '待审核', 1: '已通过', 2: '已拒绝' }
  return map[status] !== undefined ? map[status] : '-'
}

function getStatusTagType(status) {
  const map = { 0: 'warning', 1: 'success', 2: 'danger' }
  return map[status] || 'info'
}

function handleRouteInit() {
  if (route.query.detail_id) {
    applyRouteDetailQuery(route.query.detail_id)
  } else {
    getList()
  }
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
.withdraw-curator-page {
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

.mb12 {
  margin-bottom: 12px;
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

.withdraw-curator-table {
  margin-top: 8px;

  :deep(.el-table__header th) {
    color: var(--curator-on-surface-variant);
    font-weight: 500;
    background: rgba(242, 244, 247, 0.5);
  }

  :deep(.el-table__row:hover > td) {
    background: var(--curator-surface-low) !important;
  }
}

.search-form {
  margin-bottom: 8px;

  :deep(.el-form-item) {
    margin-bottom: 16px;
    margin-right: 16px;
  }
}

.search-input { width: 180px; }
.search-select-sm { width: 120px; }

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

.price-text { color: #f56c6c; font-weight: 600; }
.fail-msg { color: #f56c6c; font-size: 12px; }
.success-link { color: #67c23a; &:hover { opacity: 0.8; } }
.danger-link { color: #f56c6c; &:hover { opacity: 0.8; } }
.text-gray { color: #c0c4cc; font-size: 13px; }
.detail-link { color: #409eff; }
.account-info {
  line-height: 1.5;

  &--qrcode {
    display: flex;
    align-items: center;
    gap: 8px;
  }
}

.account-info__sub {
  color: var(--curator-on-surface-variant);
  font-size: 12px;
}

.qrcode-thumb {
  flex: 0 0 34px;
  width: 34px;
  height: 34px;
  border: 1px solid var(--curator-outline);
  border-radius: 6px;
}

.pagination-wrap { padding: 16px 0 0; display: flex; justify-content: flex-end; }

.withdraw-detail {
  padding: 0 20px 20px;

  &__actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 20px;
  }
}
</style>
