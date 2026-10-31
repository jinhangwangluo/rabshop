<template>
  <div class="coupon-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">优惠券管理</h1>
        <p class="page-curator-subtitle">
          管理当前商户的优惠券，支持创建、编辑、状态切换与记录查看。
        </p>
      </div>
      <div class="page-curator-actions">
        <el-button class="btn-curator-reset" :icon="Tickets" @click="handleRecord">
          领取记录
        </el-button>
        <el-button
          type="primary"
          class="btn-curator-primary"
          :icon="Plus"
          @click="handleCreate"
        >
          创建优惠券
        </el-button>
      </div>
    </header>

    <div class="coupon-work-surface">
      <el-form
        ref="searchForm"
        :model="searchForm"
        inline
        class="search-form coupon-search-form"
        @submit.prevent
      >
        <el-form-item label="优惠券名称">
          <el-input
            v-model="searchForm.name"
            clearable
            placeholder="请输入优惠券名称"
            class="search-input"
            :prefix-icon="Search"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="适用范围">
          <el-select
            v-model="searchForm.scope_type"
            placeholder="全部范围"
            clearable
            class="search-select"
          >
            <el-option
              v-for="item in scopeTypes"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="发放类型">
          <el-select
            v-model="searchForm.distribute_type"
            placeholder="全部类型"
            clearable
            class="search-select"
          >
            <el-option
              v-for="item in distributeTypes"
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
            <el-option label="启用" :value="1" />
            <el-option label="禁用" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" class="btn-curator-primary" :icon="Search" @click="handleSearch">查询</el-button>
          <el-button class="btn-curator-reset" :icon="Refresh" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>

      <el-table
        v-loading="loading"
        :data="couponList"
        class="coupon-curator-table"
      >
        <el-table-column prop="id" label="ID" width="70" align="center" />
        <el-table-column prop="name" label="优惠券名称" min-width="160" show-overflow-tooltip />
        <el-table-column label="优惠金额" width="110" align="center">
          <template #default="{ row }">
            <span class="amount-text">¥{{ row.discount_amount }}</span>
          </template>
        </el-table-column>
        <el-table-column label="最低消费" width="110" align="center">
          <template #default="{ row }">
            <span v-if="Number(row.min_order_amount) > 0">满¥{{ row.min_order_amount }}</span>
            <span v-else class="text-muted">无门槛</span>
          </template>
        </el-table-column>
        <el-table-column label="适用范围" width="120" align="center">
          <template #default="{ row }">
            <el-tag :type="getScopeTagType(row.scope_type)" >
              {{ getScopeLabel(row.scope_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="发放类型" width="110" align="center">
          <template #default="{ row }">
            <el-tag type="info" >
              {{ getDistributeLabel(row.distribute_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="有效期" width="200" align="center">
          <template #default="{ row }">
            <span v-if="Number(row.expire_type) === 0">领取后{{ row.valid_days }}天内有效</span>
            <span v-else>{{ row.valid_start_time }} ~ {{ row.valid_end_time }}</span>
          </template>
        </el-table-column>
        <el-table-column label="剩余/总量" width="120" align="center">
          <template #default="{ row }">
            <span v-if="Number(row.is_quantity_limited) === 1">
              <span :class="Number(row.remain_quantity) === 0 ? 'text-danger' : ''">{{ row.remain_quantity }}</span>
              /{{ row.total_quantity }}
            </span>
            <span v-else class="text-muted">不限量</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="80" align="center">
          <template #default="{ row }">
            <el-switch
              v-model="row.status"
              :active-value="1"
              :inactive-value="0"
              @change="handleStatusChange(row)"
            />
          </template>
        </el-table-column>
        <el-table-column prop="create_time" label="创建时间" width="175" align="center" />
        <el-table-column label="操作" width="150" fixed="right" align="center">
          <template #default="{ row }">
            <a @click="handleEdit(row.id)">编辑</a>
            <el-divider direction="vertical" />
            <a class="danger-link" @click="handleDelete(row.id)">删除</a>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrap">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="limit"
          :page-sizes="[15, 20, 30, 50]"
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
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Refresh, Search, Tickets } from '@element-plus/icons-vue'
import settings from '@/settings'
import {
  getCouponList,
  setCouponStatus,
  deleteCoupon,
  getCouponScopeTypes,
  getCouponDistributeTypes
} from '@/api/coupon'

defineOptions({
  name: 'MerchantCouponList'
})

const router = useRouter()

const prefix = '/' + (settings.prefix ? settings.prefix + '/' : '')
const couponBasePath = `${prefix}coupon`

const loading = ref(false)
const couponList = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(15)
const searchForm = reactive({
  name: '',
  scope_type: '',
  distribute_type: '',
  status: ''
})
const scopeTypes = ref([])
const distributeTypes = ref([])

function loadEnums() {
  getCouponScopeTypes().then(res => {
    scopeTypes.value = res.data || []
  })
  getCouponDistributeTypes().then(res => {
    distributeTypes.value = res.data || []
  })
}

function fetchCouponList() {
  loading.value = true
  getCouponList({
    ...searchForm,
    page: page.value,
    page_size: limit.value
  }).then(res => {
    couponList.value = res.data.list || []
    total.value = res.data.count || 0
  }).finally(() => {
    loading.value = false
  })
}

function handleSearch() {
  page.value = 1
  fetchCouponList()
}

function handleReset() {
  Object.assign(searchForm, {
    name: '',
    scope_type: '',
    distribute_type: '',
    status: ''
  })
  page.value = 1
  fetchCouponList()
}

function handleCreate() {
  router.push(`${couponBasePath}/create`)
}

function handleRecord() {
  router.push(`${couponBasePath}/record`)
}

function handleEdit(id) {
  router.push(`${couponBasePath}/edit/${id}`)
}

function handleDelete(id) {
  ElMessageBox.confirm('确定要删除该优惠券吗？删除后不可恢复。', '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    deleteCoupon(id).then(res => {
      ElMessage.success(res.message || '删除成功')
      if (couponList.value.length === 1 && page.value > 1) {
        page.value -= 1
      }
      fetchCouponList()
    })
  }).catch(() => {})
}

function handleStatusChange(row) {
  const currentStatus = row.status
  const rollbackStatus = currentStatus === 1 ? 0 : 1
  setCouponStatus(row.id, currentStatus).then(res => {
    ElMessage.success(res.message || '状态更新成功')
  }).catch(() => {
    row.status = rollbackStatus
  })
}

function handleSizeChange(val) {
  limit.value = val
  page.value = 1
  fetchCouponList()
}

function handleCurrentChange(val) {
  page.value = val
  fetchCouponList()
}

function getScopeTagType(scopeType) {
  const map = { 0: 'warning', 1: 'success' }
  return map[scopeType] || ''
}

function getScopeLabel(scopeType) {
  const found = scopeTypes.value.find(item => item.value === scopeType)
  return found ? found.label : '-'
}

function getDistributeLabel(distributeType) {
  const found = distributeTypes.value.find(item => item.value === distributeType)
  return found ? found.label : '-'
}

onMounted(() => {
  loadEnums()
  fetchCouponList()
})
</script>

<style lang="scss" scoped>
.coupon-curator-page {
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

.page-curator-actions {
  display: flex;
  align-items: center;
  gap: 12px;
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

.coupon-work-surface {
  background: var(--ds-surface-card);
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
}

::v-deep .btn-curator-primary.el-button--primary {
  border: none;
  border-radius: 6px;
  color: var(--ds-on-primary);
  font-weight: 500;
  padding-left: 18px;
  padding-right: 18px;
  box-shadow: 0 2px 8px rgba(0, 87, 194, 0.2);
}

::v-deep .btn-curator-reset {
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

.coupon-search-form {
  margin-bottom: 8px;
}

.coupon-curator-table {
  margin-top: 8px;

  ::v-deep .el-table__header th {
    color: var(--ds-on-surface-variant);
    font-weight: 500;
    background: rgba(var(--ds-surface-low-rgb), 0.5);
  }

  ::v-deep .el-table__row:hover > td {
    background: var(--ds-surface-low) !important;
  }
}

.search-input { width: 180px; }
.search-select { width: 140px; }
.search-select-sm { width: 110px; }

.amount-text {
  color: var(--text-price);
  font-weight: 600;
}

.text-muted {
  color: var(--ds-on-surface-variant);
  font-size: 12px;
}

.text-danger {
  color: #f56c6c;
}

.danger-link {
  color: #f56c6c;

  &:hover {
    color: #f56c6c;
    opacity: 0.8;
  }
}

.pagination-wrap {
  padding: 16px 0 0;
  display: flex;
  justify-content: flex-end;
}
</style>
