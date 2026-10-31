<template>
  <div class="product-list-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">商品列表</h1>
        <p class="page-curator-subtitle">
          店铺商品按平台分类在移动端展示，商户自定义分类仅用于后台内部筛选。
        </p>
      </div>
      <el-button
        type="primary"
        class="btn-curator-primary"
        icon="el-icon-plus"
        @click="handleCreate"
      >
        上架商品
      </el-button>
    </header>

    <div class="category-work-surface">
      <!-- 搜索区域 -->
      <el-form :model="searchForm" inline class="category-search-form" @submit.prevent>
        <el-form-item label="商品名称">
          <el-input
            v-model="searchForm.name"
            clearable
            placeholder="请输入商品名称"
            class="search-input"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="商品编码">
          <el-input
            v-model="searchForm.product_code"
            clearable
            placeholder="请输入商品编码"
            class="search-input"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="平台分类">
          <el-cascader
            v-model="platformCategoryPath"
            :options="platformCategoryOptions"
            :props="{ checkStrictly: true, emitPath: true, value: 'value', label: 'label', children: 'children' }"
            placeholder="全部"
            clearable
            filterable
            class="search-select-lg"
            @change="handlePlatformCategorySearch"
          />
        </el-form-item>
        <el-form-item label="商户分类">
          <el-cascader
            v-model="merchantCategoryPath"
            :options="merchantCategoryOptions"
            :props="{ checkStrictly: true, emitPath: true, value: 'value', label: 'label', children: 'children' }"
            placeholder="全部"
            clearable
            filterable
            class="search-select-lg"
            @change="handleMerchantCategorySearch"
          />
        </el-form-item>
        <el-form-item label="品牌">
          <el-select v-model="searchForm.brand_id" placeholder="全部" clearable class="search-select-sm">
            <el-option v-for="item in brandOptions" :key="item.id" :label="item.name" :value="item.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="规格类型">
          <el-select v-model="searchForm.spec_type" placeholder="全部" clearable class="search-select-sm">
            <el-option label="单规格" :value="0" />
            <el-option label="多规格" :value="1" />
          </el-select>
        </el-form-item>
        <el-form-item label="商品类型">
          <el-select v-model="searchForm.goods_type" placeholder="全部" clearable class="search-select-sm">
            <el-option label="实物" :value="1" />
            <el-option label="虚拟" :value="2" />
            <el-option label="内容" :value="3" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="全部" clearable class="search-select-sm">
            <el-option label="上架" :value="1" />
            <el-option label="下架" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item label="审核状态">
          <el-select v-model="searchForm.audit_status" placeholder="全部" clearable class="search-select-sm">
            <el-option label="待审核" :value="0" />
            <el-option label="审核通过" :value="1" />
            <el-option label="审核驳回" :value="2" />
          </el-select>
        </el-form-item>
        <el-form-item label="创建时间">
          <el-date-picker
            v-model="createTimeRange"
            type="daterange"
            value-format="YYYY-MM-DD"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            class="search-date"
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
        :data="list"
        row-key="id"
        class="category-curator-table"
      >
        <el-table-column prop="id" label="ID" width="70" align="center" />
        <el-table-column label="封面" width="70" align="center">
          <template #default="scope">
            <el-image
              v-if="scope.row.cover"
              :src="scope.row.cover"
              :preview-src-list="[scope.row.cover]"
              preview-teleported
              fit="cover"
              class="cover-img"
            />
            <span v-else class="empty-text">-</span>
          </template>
        </el-table-column>
        <el-table-column prop="name" label="商品名称" min-width="180" show-overflow-tooltip />
        <el-table-column label="审核状态" width="100" align="center">
          <template #default="scope">
            <el-tag :type="auditStatusTagType(scope.row.audit_status)">
              {{ scope.row.audit_status_text || auditStatusText(scope.row.audit_status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="规格类型" width="90" align="center">
          <template #default="scope">
            <el-tag :type="Number(scope.row.spec_type) === 1 ? 'warning' : 'info'">
              {{ specTypeText(scope.row.spec_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="商品类型" width="90" align="center">
          <template #default="scope">
            <el-tag :type="goodsTypeTagType(scope.row.goods_type)">
              {{ goodsTypeText(scope.row.goods_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="sku_count" label="SKU数" width="80" align="center" />
        <el-table-column label="价格" width="110" align="center">
          <template #default="scope">
            <span v-if="Number(scope.row.spec_type) === 1">¥{{ scope.row.price }} 起</span>
            <span v-else>¥{{ scope.row.price }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="stock" label="库存" width="80" align="center" />
        <el-table-column prop="display_sales" label="展示销量" width="90" align="center">
          <template #default="scope">
            <el-tooltip :content="`真实销量 ${scope.row.sales} + 虚拟 ${scope.row.virtual_sales || 0}`" placement="top" :show-after="300">
              <span>{{ scope.row.display_sales }}</span>
            </el-tooltip>
          </template>
        </el-table-column>
        <el-table-column prop="display_view_count" label="展示浏览量" width="100" align="center">
          <template #default="scope">
            <el-tooltip :content="`真实浏览量 ${scope.row.view_count} + 虚拟 ${scope.row.virtual_view_count || 0}`" placement="top" :show-after="300">
              <span>{{ scope.row.display_view_count }}</span>
            </el-tooltip>
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
        <el-table-column prop="create_time" label="创建时间" width="160" align="center" />
        <el-table-column label="操作" width="180" fixed="right" align="center">
          <template #default="scope">
            <a @click="handleEdit(scope.row)">编辑</a>
            <el-divider direction="vertical" />
            <a @click="openStatsDialog(scope.row)">虚拟统计</a>
            <el-divider direction="vertical" />
            <a class="danger-link" @click="handleDelete(scope.row.id)">删除</a>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrap">
        <el-pagination
          v-if="total > 0"
          v-model:current-page="searchForm.page"
          v-model:page-size="searchForm.limit"
          :page-sizes="[10, 20, 50, 100]"
          layout="total, sizes, prev, pager, next, jumper"
          :total="total"
          @size-change="handleSizeChange"
          @current-change="handlePageChange"
        />
      </div>
    </div>

    <!-- 虚拟统计弹窗 -->
    <el-dialog
      v-model="statsDialogVisible"
      title="虚拟统计维护"
      width="420px"
      destroy-on-close
    >
      <el-form label-width="100px" class="stats-form">
        <el-form-item label="虚拟销量">
          <el-input-number v-model="statsForm.virtual_sales" :min="0" :precision="0" class="form-width" />
          <div class="form-tip">展示基数=真实销量 + 虚拟销量</div>
        </el-form-item>
        <el-form-item label="虚拟浏览量">
          <el-input-number v-model="statsForm.virtual_view_count" :min="0" :precision="0" class="form-width" />
          <div class="form-tip">展示基数=真实浏览量 + 虚拟浏览量</div>
        </el-form-item>
        <div class="stats-tip">仅前台展示用，不参与库存、结算与佣金统计</div>
      </el-form>
      <template #footer>
        <span class="stats-footer">
          <el-button @click="statsDialogVisible = false">取 消</el-button>
          <el-button type="primary" :loading="statsSaving" @click="submitStats">确 定</el-button>
        </span>
      </template>
    </el-dialog>

  </div>
</template>

<script setup>
import { getCurrentInstance, onBeforeMount, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  getStoreProductList,
  deleteStoreProduct,
  setStoreProductStatus,
  setStoreProductVirtualStats,
  getPlatformProductCategoryTree,
  getProductCategoryTree,
  getBrandOptions
} from '@/api/product'

defineOptions({
  name: 'MerchantProductList'
})

const { proxy } = getCurrentInstance()
const message = proxy.$message
const confirm = proxy.$confirm
const router = useRouter()

const loading = ref(false)
const list = ref([])
const total = ref(0)

const platformCategoryOptions = ref([])
const merchantCategoryOptions = ref([])
const brandOptions = ref([])

const platformCategoryPath = ref([])
const merchantCategoryPath = ref([])
const createTimeRange = ref([])

const statsDialogVisible = ref(false)
const statsSaving = ref(false)
const activeStatsRow = ref(0)
const statsForm = reactive({ virtual_sales: 0, virtual_view_count: 0 })

const searchForm = reactive({
  name: '',
  product_code: '',
  category_id: 0,
  merchant_category_id: 0,
  brand_id: '',
  spec_type: '',
  goods_type: 0,
  status: '',
  audit_status: '',
  create_time: '',
  page: 1,
  limit: 20
})

function specTypeText(type) {
  const map = { 0: '单规格', 1: '多规格' }
  return map[Number(type)] || '-'
}

function goodsTypeText(type) {
  const map = { 1: '实物', 2: '虚拟', 3: '内容' }
  return map[Number(type)] || '-'
}

function goodsTypeTagType(type) {
  const map = { 1: 'info', 2: 'success', 3: 'warning' }
  return map[Number(type)] || 'info'
}

function auditStatusText(status) {
  const map = { 0: '待审核', 1: '审核通过', 2: '审核驳回' }
  return map[Number(status)] || '-'
}

function auditStatusTagType(status) {
  const map = { 0: 'warning', 1: 'success', 2: 'danger' }
  return map[Number(status)] || 'info'
}

function getList() {
  loading.value = true
  const params = { ...searchForm }
  if (params.category_id === 0) delete params.category_id
  if (params.merchant_category_id === 0) delete params.merchant_category_id
  if (params.brand_id === '') delete params.brand_id
  if (params.spec_type === '') delete params.spec_type
  if (params.goods_type === 0) delete params.goods_type
  if (params.status === '') delete params.status
  if (params.audit_status === '') delete params.audit_status
  if (params.create_time === '') delete params.create_time
  getStoreProductList(params).then(res => {
    const data = res.data || {}
    list.value = data.list || []
    total.value = data.total || 0
  }).finally(() => {
    loading.value = false
  })
}

function loadOptions() {
  getPlatformProductCategoryTree().then(res => {
    platformCategoryOptions.value = res.data || []
  }).catch(() => {})
  getProductCategoryTree().then(res => {
    merchantCategoryOptions.value = res.data || []
  }).catch(() => {})
  getBrandOptions().then(res => { brandOptions.value = res.data || [] }).catch(() => {})
}

function handlePlatformCategorySearch(val) {
  searchForm.category_id = val && val.length ? val[val.length - 1] : 0
}

function handleMerchantCategorySearch(val) {
  searchForm.merchant_category_id = val && val.length ? val[val.length - 1] : 0
}

function handleSearch() {
  searchForm.page = 1
  getList()
}

function handleReset() {
  Object.assign(searchForm, {
    name: '',
    product_code: '',
    category_id: 0,
    merchant_category_id: 0,
    brand_id: '',
    spec_type: '',
    goods_type: 0,
    status: '',
    audit_status: '',
    create_time: '',
    page: 1,
    limit: 20
  })
  platformCategoryPath.value = []
  merchantCategoryPath.value = []
  createTimeRange.value = []
  getList()
}

function handleSizeChange(size) {
  searchForm.limit = size
  searchForm.page = 1
  getList()
}

function handlePageChange(page) {
  searchForm.page = page
  getList()
}

function handleCreate() {
  router.push('/product/list/create')
}

function handleEdit(row) {
  router.push(`/product/list/create?id=${row.id}`)
}

function handleDelete(id) {
  confirm('确定要删除该商品吗？', '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    deleteStoreProduct(id).then(res => {
      message.success(res.message || '删除成功')
      getList()
    }).catch(() => {})
  }).catch(() => {})
}

function handleStatusChange(row) {
  setStoreProductStatus(row.id, row.status).then(res => {
    message.success(res.message || '状态更新成功')
  }).catch(() => {
    row.status = row.status === 1 ? 0 : 1
  })
}

function openStatsDialog(row) {
  activeStatsRow.value = row.id
  statsForm.virtual_sales = Number(row.virtual_sales) || 0
  statsForm.virtual_view_count = Number(row.virtual_view_count) || 0
  statsDialogVisible.value = true
}

function submitStats() {
  statsSaving.value = true
  setStoreProductVirtualStats(activeStatsRow.value, { ...statsForm }).then(res => {
    message.success(res.message || '修改成功')
    statsDialogVisible.value = false
    getList()
  }).catch(() => {}).finally(() => {
    statsSaving.value = false
  })
}

onBeforeMount(() => {
  getList()
  loadOptions()
})
</script>

<style lang="scss" scoped>
.product-list-page {
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
  max-width: 560px;
}

.category-work-surface {
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

.category-search-form {
  margin-bottom: 8px;
}

.category-curator-table {
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

.search-input { width: 160px; }
.search-select-sm { width: 100px; }
.search-select-lg { width: 180px; }
.search-date { width: 240px; }

.cover-img {
  width: 40px;
  height: 40px;
  border-radius: 6px;
}

.danger-link {
  color: #f56c6c;

  &:hover {
    color: #f56c6c;
    opacity: 0.8;
  }
}

.empty-text {
  color: #909399;
  font-size: 12px;
}

.pagination-wrap {
  padding: 16px 0 0;
  display: flex;
  justify-content: flex-end;
}

.stats-form {
  padding: 8px 0;
}

.form-width {
  width: 100%;

  :deep(.el-input-number) {
    width: 100%;

    .el-input__inner {
      text-align: left;
    }
  }
}

.form-tip {
  font-size: 12px;
  color: #909399;
  margin-top: 4px;
  line-height: 1.5;
}

.stats-tip {
  padding: 8px 12px;
  margin-top: 8px;
  border-radius: 6px;
  background: rgba(243, 65, 0, 0.06);
  color: #f34100;
  font-size: 12px;
}

.stats-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
</style>
