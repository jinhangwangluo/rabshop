<template>
  <div class="coupon-record-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">优惠券领取记录</h1>
        <p class="page-curator-subtitle">
          查看用户优惠券领取与使用情况，支持按券、用户、状态多维度筛选。
        </p>
      </div>
    </header>

    <div class="record-work-surface">
      <!-- 搜索区域 -->
      <el-form
        ref="searchFormRef"
        :model="searchForm"
        inline
        class="search-form record-search-form"
        @submit.prevent
      >
        <el-form-item label="优惠券ID">
          <el-input
            v-model="searchForm.coupon_id"
            clearable
            placeholder="请输入优惠券ID"
            class="search-input-sm"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="用户ID">
          <el-input
            v-model="searchForm.user_id"
            clearable
            placeholder="请输入用户ID"
            class="search-input-sm"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="领取方式">
          <el-select
            v-model="searchForm.receive_type"
            placeholder="全部方式"
            clearable
            class="search-select"
          >
            <el-option
              v-for="item in receiveTypes"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select
            v-model="searchForm.status"
            placeholder="全部状态"
            clearable
            class="search-select-sm"
          >
            <el-option
              v-for="item in statusOptions"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="创建时间">
          <el-date-picker
            v-model="searchForm.create_time"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="yyyy-MM-dd"
            class="search-date-range"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" class="btn-curator-primary" icon="el-icon-search" @click="handleSearch">查询</el-button>
          <el-button class="btn-curator-reset" icon="el-icon-refresh" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>

      <!-- 表格区域 -->
      <el-table
        v-loading="loading"
        :data="recordList"
        class="record-curator-table"
      >
        <el-table-column prop="id" label="ID" width="70" align="center" />
        <el-table-column prop="name" label="优惠券名称" min-width="150" show-overflow-tooltip />
        <el-table-column prop="user_id" label="用户ID" width="100" align="center" />
        <el-table-column label="优惠金额" width="110" align="center">
          <template #default="scope">
            <span class="amount-text">¥{{ scope.row.discount_amount }}</span>
          </template>
        </el-table-column>
        <el-table-column label="最低消费" width="110" align="center">
          <template #default="scope">
            <span v-if="scope.row.min_order_amount > 0">满¥{{ scope.row.min_order_amount }}</span>
            <span v-else class="text-muted">无门槛</span>
          </template>
        </el-table-column>
        <el-table-column label="领取方式" width="110" align="center">
          <template #default="scope">
            <el-tag type="info">
              {{ getReceiveTypeLabel(scope.row.receive_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="有效期" width="230" align="center">
          <template #default="scope">
            <span v-if="scope.row.valid_start_time && scope.row.valid_end_time">
              {{ scope.row.valid_start_time }} ~ {{ scope.row.valid_end_time }}
            </span>
            <span v-else class="text-muted">-</span>
          </template>
        </el-table-column>
        <el-table-column label="使用时间" width="175" align="center">
          <template #default="scope">
            <span v-if="scope.row.use_time">{{ scope.row.use_time }}</span>
            <span v-else class="text-muted">未使用</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="scope">
            <el-tag :type="getStatusTagType(scope.row.status)">
              {{ getStatusLabel(scope.row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="create_time" label="领取时间" width="175" align="center" />
      </el-table>

      <!-- 分页 -->
      <div class="pagination-wrap">
        <el-pagination
          :current-page="page"
          :page-sizes="[15, 20, 30, 50]"
          :page-size="limit"
          layout="total, sizes, prev, pager, next, jumper"
          :total="total"
          @size-change="handleSizeChange"
          @current-change="handleCurrentChange"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { onBeforeMount, reactive, ref } from 'vue'
import { getCouponUserList } from '@/api/coupon'

defineOptions({
  name: 'CouponRecord'
})

const searchFormRef = ref(null)
const loading = ref(false)
const recordList = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(15)

const searchForm = reactive({
  coupon_id: '',
  user_id: '',
  receive_type: '',
  status: '',
  create_time: []
})

const receiveTypes = [
  { value: 'receive', label: '主动领取' },
  { value: 'send', label: '平台赠送' },
  { value: 'give', label: '满赠' },
  { value: 'new', label: '新人赠送' },
  { value: 'buy', label: '买赠' },
  { value: 'first', label: '首单赠送' }
]

const statusOptions = [
  { value: 0, label: '未使用' },
  { value: 1, label: '已使用' },
  { value: 2, label: '已过期' }
]

function getRecordList() {
  loading.value = true
  const params = {
    ...searchForm,
    page: page.value,
    page_size: limit.value
  }

  if (Array.isArray(params.create_time) && params.create_time.length === 2) {
    params.create_time_start = params.create_time[0]
    params.create_time_end = params.create_time[1]
  }
  delete params.create_time

  getCouponUserList(params).then(res => {
    const data = res.data || {}
    recordList.value = data.list || []
    total.value = data.count || 0
  }).finally(() => {
    loading.value = false
  })
}

function handleSearch() {
  page.value = 1
  getRecordList()
}

function handleReset() {
  Object.assign(searchForm, {
    coupon_id: '',
    user_id: '',
    receive_type: '',
    status: '',
    create_time: []
  })
  page.value = 1
  getRecordList()
}

function handleSizeChange(val) {
  limit.value = val
  page.value = 1
  getRecordList()
}

function handleCurrentChange(val) {
  page.value = val
  getRecordList()
}

function getReceiveTypeLabel(type) {
  const found = receiveTypes.find(item => item.value === type)
  return found ? found.label : '-'
}

function getStatusTagType(status) {
  const map = { 0: 'warning', 1: 'success', 2: 'info' }
  return map[status] !== undefined ? map[status] : ''
}

function getStatusLabel(status) {
  const found = statusOptions.find(item => item.value === status)
  return found ? found.label : '-'
}

onBeforeMount(() => {
  getRecordList()
})
</script>

<style lang="scss" scoped>
.coupon-record-page {
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

.record-work-surface {
  background: var(--ds-surface-card);
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
}

:deep(.btn-curator-primary.el-button--primary) {
  border: none;
  border-radius: 6px;
  color: var(--ds-on-primary);
  font-weight: 500;
  padding-left: 18px;
  padding-right: 18px;
  box-shadow: 0 2px 8px rgba(0, 87, 194, 0.2);
}

:deep(.btn-curator-reset) {
  border-radius: 6px;
  background: var(--ds-surface-card);
  color: var(--ds-primary);
  border: 1px solid var(--ds-primary);

  &:hover,
  &:focus {
    background: rgba(0, 87, 194, 0.06);
    color: var(--ds-primary-container);
    border-color: var(--ds-primary-container);
  }
}

.record-search-form {
  margin-bottom: 8px;
}

.record-curator-table {
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

.search-input-sm { width: 130px; }
.search-select { width: 130px; }
.search-select-sm { width: 110px; }
.search-date-range { width: 240px; }

.amount-text {
  color: #f56c6c;
  font-weight: 600;
}

.text-muted {
  color: var(--ds-on-surface-variant);
  font-size: 12px;
}

.pagination-wrap {
  padding: 16px 0 0;
  display: flex;
  justify-content: flex-end;
}
</style>
