<template>
  <div>
    <!-- 搜索区域 -->
    <el-card shadow="never" class="search-card">
      <el-form ref="searchForm" :model="searchForm" inline class="search-form" @submit.prevent>
        <el-form-item label="关键词">
          <el-input
            v-model="searchForm.keyword"
            clearable
            placeholder="用户昵称/手机号/订单号"
            class="search-input"
            prefix-icon="el-icon-search"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="支付方式">
          <el-select v-model="searchForm.pay_type" placeholder="全部" clearable class="search-select">
            <el-option label="微信支付" value="wechat" />
            <el-option label="支付宝" value="alipay" />
            <el-option label="余额支付" value="balance" />
          </el-select>
        </el-form-item>
        <el-form-item label="支付状态">
          <el-select v-model="searchForm.status" placeholder="全部" clearable class="search-select-sm">
            <el-option label="未支付" :value="0" />
            <el-option label="已支付" :value="1" />
          </el-select>
        </el-form-item>
        <el-form-item label="充值时间">
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
            <span class="stat-label">总充值金额</span>
            <span class="stat-value primary">¥{{ stats.total_amount || '0.00' }}</span>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-item">
            <span class="stat-label">总充值笔数</span>
            <span class="stat-value">{{ stats.total || 0 }}</span>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-item">
            <span class="stat-label">今日充值</span>
            <span class="stat-value success">¥{{ stats.today_amount || '0.00' }}</span>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-item">
            <span class="stat-label">本月充值</span>
            <span class="stat-value warning">¥{{ stats.month_amount || '0.00' }}</span>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 表格区域 -->
    <el-card shadow="never">
      <el-table v-loading="loading" :data="list" class="mt14">
        <el-table-column prop="id" label="ID" width="80" align="center" />
        <el-table-column label="用户信息" min-width="140">
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
        <el-table-column prop="order_id" label="订单号" min-width="180" show-overflow-tooltip />
        <el-table-column label="充值金额" width="110" align="center">
          <template #default="scope">
            <span class="price-text">¥{{ scope.row.price || '0.00' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="支付方式" width="110" align="center">
          <template #default="scope">
            <el-tag :type="getPayTagType(scope.row.pay_type)">
              {{ getPayLabel(scope.row.pay_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="支付状态" width="100" align="center">
          <template #default="scope">
            <el-tag :type="scope.row.paid === 1 ? 'success' : 'warning'">
              {{ scope.row.paid === 1 ? '已支付' : '未支付' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="pay_time" label="支付时间" width="160" align="center">
          <template #default="scope">
            <span>{{ scope.row.pay_time || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="create_time" label="创建时间" width="160" align="center" />
        <el-table-column label="操作" width="90" fixed="right" align="center">
          <template #default="scope">
            <el-button type="text" @click="handleDetail(scope.row)">详情</el-button>
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
      title="充值详情"
      direction="rtl"
      size="720px"
      :wrapper-closable="true"
      custom-class="recharge-detail-drawer"
      @close="handleDetailClose"
    >
      <div v-loading="detailLoading" class="recharge-detail">
        <el-descriptions v-if="detailData" :column="2" border>
          <el-descriptions-item label="ID">{{ detailData.id || '-' }}</el-descriptions-item>
          <el-descriptions-item label="用户ID">{{ detailData.user_id || detailData.uid || '-' }}</el-descriptions-item>
          <el-descriptions-item label="用户昵称">{{ detailData.nickname || '-' }}</el-descriptions-item>
          <el-descriptions-item label="订单号">{{ detailData.order_sn || detailData.order_id || '-' }}</el-descriptions-item>
          <el-descriptions-item label="订单类型">
            {{ detailData.order_type_text || getOrderTypeLabel(detailData.order_type) }}
          </el-descriptions-item>
          <el-descriptions-item label="支付方式">
            {{ detailData.pay_type_text || getPayLabel(detailData.pay_type) }}
          </el-descriptions-item>
          <el-descriptions-item label="到账金额">
            <span class="price-text">¥{{ detailData.amount || detailData.price || '0.00' }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="支付金额">
            <span class="price-text">¥{{ detailData.pay_amount || detailData.price || '0.00' }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="支付状态">
            <el-tag :type="getRechargeStatusTag(detailData)">
              {{ getRechargeStatusLabel(detailData) }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="支付时间">{{ detailData.pay_time || '-' }}</el-descriptions-item>
          <el-descriptions-item label="第三方交易号" :span="2">
            {{ detailData.transaction_id || '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="创建时间">{{ detailData.create_time || '-' }}</el-descriptions-item>
          <el-descriptions-item label="更新时间">{{ detailData.update_time || '-' }}</el-descriptions-item>
          <el-descriptions-item label="备注" :span="2">{{ detailData.remark || '-' }}</el-descriptions-item>
        </el-descriptions>
        <el-empty v-else-if="!detailLoading" description="暂无详情数据" />
      </div>
    </el-drawer>
  </div>
</template>

<script setup>
import { reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import request from '@/utils/request'
import { getUserRechargeOrderDetail } from '@/api/finance'

function getRechargeList(params) {
  return request({ url: '/finance_record', method: 'get', params: { ...params, bill_type: 'recharge' }})
}

defineOptions({
  name: 'UserRecharge'
})

const route = useRoute()
const loading = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(15)
const dateRange = ref([])
const stats = reactive({})
const detailVisible = ref(false)
const detailLoading = ref(false)
const detailData = ref(null)
const routeDetailId = ref('')
const searchForm = reactive({
  keyword: '',
  pay_type: '',
  status: '',
  start_time: '',
  end_time: ''
})

function assignStats(nextStats) {
  Object.keys(stats).forEach((key) => delete stats[key])
  Object.assign(stats, nextStats || {})
}

async function getList() {
  loading.value = true
  try {
    const params = { page: page.value, limit: limit.value, ...searchForm }
    const res = await getRechargeList(params)
    list.value = res.data.list || []
    total.value = res.data.total || 0
    if (res.data.stats) assignStats(res.data.stats)
  } finally {
    loading.value = false
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
  void getList()
}

function handleReset() {
  Object.assign(searchForm, { keyword: '', pay_type: '', status: '', start_time: '', end_time: '' })
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
  openRouteRechargeDetail(value)
}

function openRouteRechargeDetail(id) {
  detailVisible.value = true
  detailLoading.value = true
  detailData.value = null
  getUserRechargeOrderDetail(id).then(res => {
    detailData.value = res.data || {}
  }).finally(() => {
    detailLoading.value = false
  })
}

function handleDetail(row) {
  detailData.value = row
  detailVisible.value = true
}

function handleDetailClose() {
  detailData.value = null
}

function getPayLabel(type) {
  const map = { wechat: '微信支付', alipay: '支付宝', balance: '余额', yue: '余额支付', wx: '微信支付' }
  return map[type] || type || '-'
}

function getPayTagType(type) {
  const map = { wechat: 'success', alipay: 'primary', balance: 'warning', yue: 'warning', wx: 'success' }
  return map[type] || 'info'
}

function getOrderTypeLabel(type) {
  const map = { recharge: '余额充值', member: '会员购买', live_coin: '直播金币充值' }
  return map[type] || type || '-'
}

function getRechargeStatusLabel(row = {}) {
  if (row.status_text) {
    return row.status_text
  }
  if (row.paid !== undefined) {
    return Number(row.paid) === 1 ? '已支付' : '未支付'
  }

  const status = Number(row.status || 0)
  if (status === 1) {
    return '已支付'
  }
  if (status === 2) {
    return '支付失败'
  }
  return '待支付'
}

function getRechargeStatusTag(row = {}) {
  if (row.paid !== undefined) {
    return Number(row.paid) === 1 ? 'success' : 'warning'
  }

  const status = Number(row.status || 0)
  if (status === 1) {
    return 'success'
  }
  if (status === 2) {
    return 'danger'
  }
  return 'warning'
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
  .search-select-sm { width: 100px; }
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

.price-text { color: #f56c6c; font-weight: 600; }

.recharge-detail {
  padding: 0 20px 24px;
}

.pagination-wrap {
  padding: 16px 0 0;
  display: flex;
  justify-content: flex-end;
}
</style>
