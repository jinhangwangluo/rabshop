<template>
  <div class="category-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">直播分类</h1>
        <p class="page-curator-subtitle">
          查看平台统一维护的直播分类信息，支持按名称与状态快速筛选。
        </p>
      </div>
    </header>

    <div class="category-work-surface">
      <!-- 搜索区域 -->
      <el-card shadow="never" class="search-card">
        <el-form
          ref="searchFormRef"
          :model="searchForm"
          inline
          class="search-form"
          @submit.prevent
        >
          <el-form-item label="分类名称">
            <el-input
              v-model="searchForm.name"
              clearable
              placeholder="请输入分类名称"
              class="search-input"
              :prefix-icon="Search"
              @keyup.enter="handleSearch"
            />
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
            <el-button type="primary" class="btn-curator-primary" :icon="Search" @click="handleSearch">查询</el-button>
            <el-button class="btn-curator-reset" :icon="Refresh" @click="handleReset">重置</el-button>
          </el-form-item>
        </el-form>
      </el-card>

      <!-- 表格区域 -->
      <el-card shadow="never" class="table-card">
        <el-alert
          title="直播分类由平台统一管理，商户端仅可查看"
          type="info"
          :closable="false"
          show-icon
          class="mb14"
        />

        <el-table
          v-loading="loading"
          :data="list"
          class="mt14 category-curator-table"
        >
          <el-table-column prop="id" label="ID" width="70" align="center" />
          <el-table-column prop="name" label="分类名称" min-width="150" show-overflow-tooltip />
          <el-table-column prop="sort" label="排序" width="80" align="center" />
          <el-table-column label="状态" width="100" align="center">
            <template #default="{ row }">
              <el-tag :type="row.status === 1 ? 'success' : 'info'" >
                {{ row.status === 1 ? '启用' : '禁用' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="create_time" label="创建时间" width="170" align="center" />
        </el-table>

        <div class="pagination-wrap">
          <el-pagination
            v-model:current-page="page"
            v-model:page-size="limit"
            class="category-curator-pagination"
            :page-sizes="[10, 20, 50, 100]"
            layout="total, sizes, prev, pager, next, jumper"
            :total="total"
            @size-change="handleSizeChange"
            @current-change="handleCurrentChange"
          />
        </div>
      </el-card>
    </div>
  </div>
</template>

<script setup>
import { getCategoryOptions } from '@/api/live'
import { Refresh, Search } from '@element-plus/icons-vue'
import { onMounted, reactive, ref } from 'vue'

defineOptions({
  name: 'MerchantLiveCategoryList'
})

const loading = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(20)
const searchForm = reactive({
  name: '',
  status: ''
})

const searchFormRef = ref(null)
let allData = []

onMounted(() => {
  getList()
})

function getList() {
  loading.value = true
  // 商户端使用只读接口获取分类列表
  getCategoryOptions().then(res => {
    const data = res.data || []
    // 过滤搜索条件（前端过滤）
    let filtered = Array.isArray(data) ? data : (data.list || [])
    if (searchForm.name) {
      filtered = filtered.filter(item => item.name && item.name.includes(searchForm.name))
    }
    if (searchForm.status !== '') {
      filtered = filtered.filter(item => item.status === searchForm.status)
    }
    list.value = filtered.slice((page.value - 1) * limit.value, page.value * limit.value)
    total.value = filtered.length
    allData = filtered
  }).finally(() => {
    loading.value = false
  })
}

function handleSearch() {
  page.value = 1
  getList()
}

function handleReset() {
  searchFormRef.value.resetFields()
  Object.assign(searchForm, { name: '', status: '' })
  handleSearch()
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
</script>

<style lang="scss" scoped>
.category-curator-page {
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

.category-work-surface {
  background: var(--ds-surface-card);
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
}

.search-card,
.table-card {
  border: none;
  background: transparent;

  :deep(.el-card__body) {
    padding: 0;
  }
}

.search-card {
  margin-bottom: 8px;
}

:deep(.btn-curator-primary.el-button--primary) {
  border: none;
  border-radius: 6px;
  color: var(--ds-on-primary);
  font-weight: 500;
  padding-left: 18px;
  padding-right: 18px;
  box-shadow: 0 2px 8px var(--brand-shadow);
}

:deep(.btn-curator-reset) {
  border-radius: 6px;
  background: var(--ds-surface-card);
  color: var(--ds-primary);
  border: 1px solid var(--ds-primary);

  &:hover,
  &:focus {
    background: var(--brand-primary-pale);
    color: var(--ds-primary-hover);
    border-color: var(--ds-primary-hover);
  }
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

.search-input {
  width: 200px;
}

.search-select-sm {
  width: 140px;
}

.mt14 {
  margin-top: 14px;
}

.mb14 {
  margin-bottom: 14px;
}

.pagination-wrap {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}

.category-curator-pagination {
  :deep(.el-pager li.active) {
    background: var(--ds-primary);
    color: var(--ds-on-primary);
    border-radius: 4px;
  }

  :deep(.btn-next),
  :deep(.btn-prev),
  :deep(.el-pager li) {
    &:hover {
      color: var(--ds-primary);
    }
  }
}
</style>
