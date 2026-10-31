<template>
  <div class="coupon-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">优惠券管理</h1>
        <p class="page-curator-subtitle">
          管理平台优惠券，支持多种类型优惠券的创建、编辑与状态控制。
        </p>
      </div>
      <el-button
        type="primary"
        class="btn-curator-primary"
        icon="el-icon-plus"
        @click="handleCreate"
      >
        创建优惠券
      </el-button>
    </header>

    <div class="coupon-work-surface">
      <!-- 搜索区域 -->
      <el-form
        ref="searchFormRef"
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
            prefix-icon="el-icon-search"
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
          <el-button type="primary" class="btn-curator-primary" icon="el-icon-search" @click="handleSearch">查询</el-button>
          <el-button class="btn-curator-reset" icon="el-icon-refresh" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>

      <!-- 表格区域 -->
      <el-table
        v-loading="loading"
        :data="couponList"
        class="coupon-curator-table"
      >
        <el-table-column prop="id" label="ID" width="70" align="center" />
        <el-table-column prop="name" label="优惠券名称" min-width="160" show-overflow-tooltip />
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
        <el-table-column label="适用范围" width="120" align="center">
          <template #default="scope">
            <el-tag :type="getScopeTagType(scope.row.scope_type)">
              {{ getScopeLabel(scope.row.scope_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="发放类型" width="110" align="center">
          <template #default="scope">
            <el-tag type="info">
              {{ getDistributeLabel(scope.row.distribute_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="有效期" width="200" align="center">
          <template #default="scope">
            <span v-if="scope.row.expire_type === 0">领取后{{ scope.row.valid_days }}天内有效</span>
            <span v-else>{{ scope.row.valid_start_time }} ~ {{ scope.row.valid_end_time }}</span>
          </template>
        </el-table-column>
        <el-table-column label="剩余/总量" width="110" align="center">
          <template #default="scope">
            <span v-if="scope.row.is_quantity_limited">
              <span :class="scope.row.remain_quantity === 0 ? 'text-danger' : ''">{{ scope.row.remain_quantity }}</span>
              /{{ scope.row.total_quantity }}
            </span>
            <span v-else class="text-muted">不限量</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="80" align="center">
          <template #default="scope">
            <el-switch
              v-model="scope.row.status"
              :active-value="1"
              :inactive-value="0"
              @change="handleStatusChange(scope.row)"
            />
          </template>
        </el-table-column>
        <el-table-column prop="create_time" label="创建时间" width="175" align="center" />
        <el-table-column label="操作" width="150" fixed="right" align="center">
          <template #default="scope">
            <a @click="handleEdit(scope.row.id)">编辑</a>
            <el-divider direction="vertical" />
            <a class="danger-link" @click="handleDelete(scope.row.id)">删除</a>
          </template>
        </el-table-column>
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
import { getCurrentInstance, onBeforeMount, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  getCouponList as getCouponListApi,
  setCouponStatus,
  deleteCoupon,
  getCouponScopeTypes,
  getCouponDistributeTypes
} from '@/api/coupon'

defineOptions({
  name: 'CouponList'
})

const router = useRouter()
const { proxy } = getCurrentInstance()
const message = proxy.$message
const confirm = proxy.$confirm

const searchFormRef = ref(null)
const loading = ref(false)
const couponList = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(15)
const scopeTypes = ref([])
const distributeTypes = ref([])

const searchForm = reactive({
  name: '',
  scope_type: '',
  distribute_type: '',
  status: ''
})

function loadEnums() {
  getCouponScopeTypes().then(res => {
    scopeTypes.value = res.data || []
  })
  getCouponDistributeTypes().then(res => {
    distributeTypes.value = res.data || []
  })
}

function getCouponList() {
  loading.value = true
  getCouponListApi({
    ...searchForm,
    page: page.value,
    page_size: limit.value
  }).then(res => {
    const data = res.data || {}
    couponList.value = data.list || []
    total.value = data.count || 0
  }).finally(() => {
    loading.value = false
  })
}

function handleSearch() {
  page.value = 1
  getCouponList()
}

function handleReset() {
  Object.assign(searchForm, { name: '', scope_type: '', distribute_type: '', status: '' })
  page.value = 1
  getCouponList()
}

function handleCreate() {
  router.push('/coupon/create')
}

function handleEdit(id) {
  router.push(`/coupon/edit/${id}`)
}

function handleDelete(id) {
  confirm('确定要删除该优惠券吗？删除后不可恢复。', '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    deleteCoupon(id).then(res => {
      message.success(res.message || '删除成功')
      if (couponList.value.length === 1 && page.value > 1) {
        page.value -= 1
      }
      getCouponList()
    }).catch(() => {})
  }).catch(() => {})
}

function handleStatusChange(row) {
  const newStatus = row.status === 1 ? 0 : 1
  setCouponStatus(row.id, newStatus).then(res => {
    if (res.code === 200 || res.status === 200) {
      message.success(res.message || '状态更新成功')
      row.status = newStatus
    } else {
      message.error(res.message || '状态更新失败')
      row.status = row.status === 1 ? 0 : 1
    }
  }).catch(() => {
    row.status = row.status === 1 ? 0 : 1
  })
}

function handleSizeChange(val) {
  limit.value = val
  page.value = 1
  getCouponList()
}

function handleCurrentChange(val) {
  page.value = val
  getCouponList()
}

function getScopeTagType(scopeType) {
  const map = { 0: 'warning', 1: 'success', 10: 'primary', 11: 'info' }
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

onBeforeMount(() => {
  loadEnums()
  getCouponList()
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

.coupon-search-form {
  margin-bottom: 8px;
}

.coupon-curator-table {
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

.search-input { width: 180px; }
.search-select { width: 140px; }
.search-select-sm { width: 110px; }

.amount-text {
  color: #f56c6c;
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
