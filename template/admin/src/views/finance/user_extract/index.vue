<template>
  <div>
    <!-- 搜索区域 -->
    <el-card shadow="never" class="search-card">
      <el-form ref="searchForm" :model="searchForm" inline class="search-form" @submit.prevent>
        <el-form-item label="关键词">
          <el-input
            v-model="searchForm.keyword"
            clearable
            placeholder="用户昵称/手机号/ID/银行卡"
            class="search-input"
            prefix-icon="el-icon-search"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="提现来源">
          <el-select v-model="searchForm.source" placeholder="全部" clearable class="search-select">
            <el-option label="佣金" value="commission" />
            <el-option label="余额" value="balance" />
          </el-select>
        </el-form-item>
        <el-form-item label="审核状态">
          <el-select v-model="searchForm.status" placeholder="全部" clearable class="search-select">
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
          <el-button type="primary" icon="el-icon-search" @click="handleSearch">查询</el-button>
          <el-button icon="el-icon-refresh" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="stat-row">
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-item">
            <span class="stat-label">待审核</span>
            <span class="stat-value warning">{{ stats.pending || 0 }}</span>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-item">
            <span class="stat-label">已通过金额</span>
            <span class="stat-value success">¥{{ stats.passed_amount || '0.00' }}</span>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-item">
            <span class="stat-label">今日申请</span>
            <span class="stat-value">{{ stats.today || 0 }}</span>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-item">
            <span class="stat-label">本月通过金额</span>
            <span class="stat-value primary">¥{{ stats.month_amount || '0.00' }}</span>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 表格区域 -->
    <el-card shadow="never">
      <el-table v-loading="loading" :data="list" class="mt14">
        <el-table-column prop="id" label="ID" width="70" align="center" />
        <el-table-column label="用户信息" min-width="130">
          <template #default="scope">
            <div class="user-info">
              <el-avatar :src="scope.row.avatar" :size="32" icon="el-icon-user-solid" />
              <div>
                <div class="user-name">{{ scope.row.nickname || '-' }}</div>
                <div class="user-uid">UID:{{ scope.row.uid }}</div>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="提现金额" width="110" align="center">
          <template #default="scope">
            <span class="price-text">¥{{ scope.row.amount || '0.00' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="手续费" width="100" align="center">
          <template #default="scope">
            <span>¥{{ scope.row.fee || '0.00' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="到账金额" width="110" align="center">
          <template #default="scope">
            <span class="success-link">¥{{ scope.row.actual_amount || '0.00' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="提现账户" width="190" min-width="190">
          <template #default="scope">
            <el-tag type="info" size="mini">{{ scope.row.source_text || '佣金' }}</el-tag>
            <div class="extract-info">{{ scope.row.bank_holder_name || '-' }} / {{ scope.row.bank_name || '-' }}</div>
            <div class="extract-info">尾号 {{ scope.row.bank_tail_number || '-' }}</div>
          </template>
        </el-table-column>
        <el-table-column label="审核状态" width="100" align="center">
          <template #default="scope">
            <el-tag :type="getStatusTagType(scope.row.status)">
              {{ getStatusLabel(scope.row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="remark" label="审核备注" min-width="120" show-overflow-tooltip>
          <template #default="scope">
            <span :class="scope.row.status === 2 ? 'text-danger' : ''">{{ scope.row.remark || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="create_time" label="申请时间" width="155" align="center" />
        <el-table-column label="操作" width="120" fixed="right" align="center">
          <template #default="scope">
            <a class="detail-link" @click="handleDetail(scope.row)">详情</a>
            <template v-if="Number(scope.row.status) === 0">
              <el-divider direction="vertical" />
              <a class="success-link" @click="handlePass(scope.row)">通过</a>
              <el-divider direction="vertical" />
              <a class="danger-link" @click="handleReject(scope.row)">拒绝</a>
            </template>
            <span v-else class="muted-text">已处理</span>
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
    </el-card>

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
          <el-descriptions-item label="用户ID">{{ detailData.uid || detailData.user_id || '-' }}</el-descriptions-item>
          <el-descriptions-item label="用户昵称">{{ detailData.nickname || '-' }}</el-descriptions-item>
          <el-descriptions-item label="手机号">{{ detailData.phone || '-' }}</el-descriptions-item>
          <el-descriptions-item label="提现来源">{{ detailData.source_text || '佣金' }}</el-descriptions-item>
          <el-descriptions-item label="申请时间">{{ detailData.create_time || '-' }}</el-descriptions-item>
          <el-descriptions-item label="提现金额">
            <span class="price-text">¥{{ detailData.amount || '0.00' }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="手续费">¥{{ detailData.fee || '0.00' }}</el-descriptions-item>
          <el-descriptions-item label="到账金额">
            <span class="success-text">¥{{ detailData.actual_amount || '0.00' }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="审核状态">
            <el-tag :type="getStatusTagType(detailData.status)">
              {{ getStatusLabel(detailData.status) }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="收款银行">{{ detailData.bank_name || '-' }}</el-descriptions-item>
          <el-descriptions-item label="持卡人">{{ detailData.bank_holder_name || '-' }}</el-descriptions-item>
          <el-descriptions-item label="银行卡号" :span="2">{{ detailData.bank_card_no || '-' }}</el-descriptions-item>
          <el-descriptions-item label="审核备注" :span="2">{{ detailData.remark || '-' }}</el-descriptions-item>
          <el-descriptions-item label="审核时间" :span="2">{{ detailData.audit_time || '-' }}</el-descriptions-item>
        </el-descriptions>

        <div v-if="Number(detailData.status) === 0" class="withdraw-detail__actions">
          <el-button type="success" :loading="auditLoading" @click="handlePassFromDetail">通过</el-button>
          <el-button type="danger" plain :loading="auditLoading" @click="handleRejectFromDetail">拒绝</el-button>
        </div>
      </div>
    </el-drawer>

    <!-- 拒绝原因弹窗 -->
    <el-dialog
      v-model="rejectVisible"
      title="拒绝提现"
      width="460px"
      :close-on-click-modal="false"
    >
      <el-form ref="rejectForm" :model="rejectForm" :rules="rejectRules" label-width="90px">
        <el-form-item label="拒绝原因" prop="reason">
          <el-input
            v-model="rejectForm.reason"
            type="textarea"
            :rows="4"
            placeholder="请填写拒绝原因"
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
import { getCurrentInstance, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { getUserExtractList, passExtract, rejectExtract } from '@/api/finance'

defineOptions({
  name: 'UserExtract'
})

const route = useRoute()
const { proxy } = getCurrentInstance()
const rejectFormRef = ref(null)
const loading = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(15)
const dateRange = ref([])
const stats = reactive({})
const searchForm = reactive({
  keyword: '',
  source: '',
  status: '',
  start_time: '',
  end_time: ''
})
const detailVisible = ref(false)
const detailData = ref(null)
const routeDetailId = ref('')
const rejectVisible = ref(false)
const rejectLoading = ref(false)
const rejectTargetId = ref(null)
const rejectForm = reactive({ reason: '' })
const rejectRules = {
  reason: [{ required: true, message: '请填写拒绝原因', trigger: 'blur' }]
}

function assignStats(nextStats) {
  Object.keys(stats).forEach((key) => delete stats[key])
  Object.assign(stats, nextStats || {})
}

async function getList() {
  loading.value = true
  try {
    const params = { page: page.value, limit: limit.value, ...searchForm }
    const res = await getUserExtractList(params)
    list.value = res.data.list || []
    total.value = res.data.total || 0
    if (res.data.stats) assignStats(res.data.stats)
    tryOpenDetailFromRoute()
  } finally {
    loading.value = false
  }
}

function applyRouteDetailQuery(detailId) {
  const value = String(detailId || '').trim()
  if (!value) {
    const previousId = routeDetailId.value
    routeDetailId.value = ''
    if (previousId && String(searchForm.keyword || '') === String(previousId)) {
      searchForm.keyword = ''
      page.value = 1
      void getList()
    }
    return
  }
  if (value === routeDetailId.value) {
    return
  }

  routeDetailId.value = value
  Object.assign(searchForm, { keyword: value, source: '', status: '', start_time: '', end_time: '' })
  dateRange.value = []
  page.value = 1
  void getList()
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
  void getList()
}

function handleReset() {
  Object.assign(searchForm, { keyword: '', source: '', status: '', start_time: '', end_time: '' })
  dateRange.value = []
  page.value = 1
  void getList()
}

function handleSizeChange(val) {
  limit.value = val
  page.value = 1
  void getList()
}

function handleCurrentChange(val) {
  page.value = val
  void getList()
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
  handlePass(detailData.value)
  detailVisible.value = false
}

function handleRejectFromDetail() {
  if (!detailData.value) return
  detailVisible.value = false
  handleReject(detailData.value)
}

function handlePass(row) {
  proxy.$confirm('确定通过该提现申请吗？', '提示', { type: 'warning' }).then(() => {
    passExtract({ id: row.id }).then(res => {
      proxy.$message.success(res.message || '操作成功')
      void getList()
    })
  }).catch(() => {})
}

function handleReject(row) {
  rejectTargetId.value = row.id
  rejectForm.reason = ''
  rejectVisible.value = true
}

function handleRejectSubmit() {
  rejectFormRef.value.validate((valid) => {
    if (!valid) return
    rejectLoading.value = true
    rejectExtract({ id: rejectTargetId.value, remark: rejectForm.reason }).then(res => {
      proxy.$message.success(res.message || '操作成功')
      rejectVisible.value = false
      void getList()
    }).finally(() => {
      rejectLoading.value = false
    })
  })
}

function getStatusLabel(status) {
  const s = String(status)
  const map = { '0': '待审核', '1': '已通过', '2': '已拒绝' }
  return map[s] || '-'
}

function getStatusTagType(status) {
  const s = String(status)
  const map = { '0': 'warning', '1': 'success', '2': 'danger' }
  return map[s] || 'info'
}

watch(() => route.query.detail_id, (value) => {
  applyRouteDetailQuery(value)
}, {
  immediate: true
})
</script>

<style lang="scss" scoped>
.search-card {
  margin-bottom: 16px;
  :deep(.el-card__body) { padding: 20px 20px 4px; }
}

.search-form {
  :deep(.el-form-item) { margin-bottom: 16px; margin-right: 16px; }
  .search-input { width: 200px; }
  .search-select { width: 120px; }
}

.stat-row {
  margin-bottom: 16px;
  .stat-card {
    .stat-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 8px 0;
    }
    .stat-label { font-size: 13px; color: #909399; margin-bottom: 6px; }
    .stat-value {
      font-size: 22px;
      font-weight: 700;
      color: #303133;
      &.primary { color: #409eff; }
      &.success { color: #67c23a; }
      &.warning { color: #e6a23c; }
      &.danger { color: #f56c6c; }
    }
  }
}

.mt14 { margin-top: 14px; }

.user-info {
  display: flex;
  align-items: center;
  gap: 8px;
  .user-name { font-size: 13px; font-weight: 500; }
  .user-uid { font-size: 12px; color: #909399; }
}

.extract-info { font-size: 12px; color: #606266; margin-top: 2px; }
.price-text { color: #f56c6c; font-weight: 600; }
.text-danger { color: #f56c6c; }
.muted-text { color: #909399; }
.success-link { color: #67c23a; }
.danger-link { color: #f56c6c; }
.detail-link { color: #409eff; }
a { color: #409eff; cursor: pointer; }
a:hover { opacity: 0.8; }

.pagination-wrap {
  padding: 16px 0 0;
  display: flex;
  justify-content: flex-end;
}

.withdraw-detail {
  padding: 0 20px 20px;

  &__actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 20px;
  }
}

.success-text {
  color: #67c23a;
  font-weight: 600;
}
</style>
