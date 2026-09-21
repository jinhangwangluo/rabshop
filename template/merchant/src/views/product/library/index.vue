<template>
  <div class="product-library-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">商品库</h1>
        <p class="page-curator-subtitle">
          平台单规格商品池，商户可从商品库选品上架；库商品恒为单规格，可绑定规格值用于组合归类。
        </p>
      </div>
      <el-button
        type="primary"
        class="btn-curator-primary"
        icon="el-icon-plus"
        @click="handleCreate"
      >
        添加商品库商品
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
            placeholder="SP开头的库编码"
            class="search-input"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="分类">
          <el-cascader
            v-model="categoryPath"
            :options="categoryOptions"
            :props="{ checkStrictly: true, emitPath: true, value: 'value', label: 'label', children: 'children' }"
            placeholder="全部"
            clearable
            filterable
            class="search-select-lg"
            @change="handleCategorySearch"
          />
        </el-form-item>
        <el-form-item label="品牌">
          <el-select v-model="searchForm.brand_id" placeholder="全部" clearable class="search-select-sm">
            <el-option v-for="item in brandOptions" :key="item.id" :label="item.name" :value="item.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="共享状态">
          <el-select v-model="searchForm.is_share" placeholder="全部" clearable class="search-select-sm">
            <el-option label="共享" :value="1" />
            <el-option label="私有" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="全部" clearable class="search-select-sm">
            <el-option label="启用" :value="1" />
            <el-option label="禁用" :value="0" />
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
        <el-table-column prop="product_code" label="商品编码" width="180" show-overflow-tooltip />
        <el-table-column label="分类" width="110" align="center">
          <template #default="scope">
            <span>{{ categoryNameMap[scope.row.category_id] || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="品牌" width="100" align="center">
          <template #default="scope">
            <span>{{ brandNameMap[scope.row.brand_id] || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="price" label="售价(元)" width="100" align="center" />
        <el-table-column prop="stock" label="库存" width="80" align="center" />
        <el-table-column prop="unit" label="单位" width="70" align="center" />
        <el-table-column label="规格值" width="120" align="center">
          <template #default="scope">
            <el-tag v-if="scope.row.spec_value_id" type="warning">
              {{ specValueNameMap[scope.row.spec_value_id] || scope.row.spec_value_id }}
            </el-tag>
            <span v-else class="empty-text">-</span>
          </template>
        </el-table-column>
        <el-table-column label="共享" width="80" align="center">
          <template #default="scope">
            <el-switch
              v-model="scope.row.is_share"
              :active-value="1"
              :inactive-value="0"
              @change="handleShareChange(scope.row)"
            />
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
        <el-table-column label="操作" width="120" fixed="right" align="center">
          <template #default="scope">
            <a @click="handleEdit(scope.row)">编辑</a>
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
  </div>
</template>

<script setup>
import { getCurrentInstance, onBeforeMount, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  getProductLibraryList,
  deleteProductLibrary,
  setProductLibraryStatus,
  setProductLibraryShare,
  getPlatformProductCategoryTree,
  getBrandOptions,
  getSpecOptions
} from '@/api/product'

defineOptions({
  name: 'MerchantProductLibrary'
})

const { proxy } = getCurrentInstance()
const message = proxy.$message
const confirm = proxy.$confirm
const router = useRouter()

const loading = ref(false)
const list = ref([])
const total = ref(0)

const categoryOptions = ref([])
const brandOptions = ref([])
const specValueNameMap = ref({})
const categoryNameMap = ref({})
const brandNameMap = ref({})

const categoryPath = ref([])
const createTimeRange = ref([])

const searchForm = reactive({
  name: '',
  product_code: '',
  category_id: 0,
  brand_id: '',
  is_share: '',
  status: '',
  create_time: '',
  page: 1,
  limit: 20
})

function getList() {
  loading.value = true
  const params = { ...searchForm }
  if (params.category_id === 0) {
    delete params.category_id
  }
  if (params.brand_id === '') {
    delete params.brand_id
  }
  if (params.is_share === '') {
    delete params.is_share
  }
  if (params.status === '') {
    delete params.status
  }
  if (params.create_time === '') {
    delete params.create_time
  }
  getProductLibraryList(params).then(res => {
    const data = res.data || {}
    list.value = data.list || []
    total.value = data.total || 0
  }).finally(() => {
    loading.value = false
  })
}

function loadMaps() {
  getPlatformProductCategoryTree().then(res => {
    const tree = res.data || []
    categoryOptions.value = tree
    const map = {}
    const walk = nodes => {
      (nodes || []).forEach(node => {
        map[node.id] = node.label
        walk(node.children)
      })
    }
    walk(tree)
    categoryNameMap.value = map
  }).catch(() => {})
  getBrandOptions().then(res => {
    brandOptions.value = res.data || []
    const map = {}
    ;(res.data || []).forEach(item => { map[item.id] = item.name })
    brandNameMap.value = map
  }).catch(() => {})
  getSpecOptions().then(res => {
    const map = {}
    ;(res.data || []).forEach(spec => {
      (spec.values || []).forEach(value => { map[value.id] = value.value })
    })
    specValueNameMap.value = map
  }).catch(() => {})
}

function handleCategorySearch(val) {
  searchForm.category_id = val && val.length ? val[val.length - 1] : 0
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
    brand_id: '',
    is_share: '',
    status: '',
    create_time: '',
    page: 1,
    limit: 20
  })
  categoryPath.value = []
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
  router.push('/product/library/create')
}

function handleEdit(row) {
  router.push(`/product/library/create?id=${row.id}`)
}

function handleDelete(id) {
  confirm('确定要删除该库商品吗？软删后已上架的店铺商品副本不受影响。', '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    deleteProductLibrary(id).then(res => {
      message.success(res.message || '删除成功')
      getList()
    }).catch(() => {})
  }).catch(() => {})
}

function handleStatusChange(row) {
  setProductLibraryStatus(row.id, row.status).then(res => {
    message.success(res.message || '状态更新成功')
  }).catch(() => {
    row.status = row.status === 1 ? 0 : 1
  })
}

function handleShareChange(row) {
  setProductLibraryShare(row.id, row.is_share).then(res => {
    message.success(res.message || '设置成功')
  }).catch(() => {
    row.is_share = row.is_share === 1 ? 0 : 1
  })
}

onBeforeMount(() => {
  getList()
  loadMaps()
})
</script>

<style lang="scss" scoped>
.product-library-page {
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
.search-select-sm { width: 110px; }
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
</style>
