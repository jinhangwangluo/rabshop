<template>
  <div class="product-brand-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">商品品牌</h1>
        <p class="page-curator-subtitle">
          管理商户商品品牌，品牌信息用于商品展示与前台筛选。
        </p>
      </div>
      <el-button
        type="primary"
        class="btn-curator-primary"
        icon="el-icon-plus"
        @click="openDialog()"
      >
        添加品牌
      </el-button>
    </header>

    <div class="category-work-surface">
      <!-- 搜索区域 -->
      <el-form
        ref="searchFormRef"
        :model="searchForm"
        inline
        class="category-search-form"
        @submit.prevent
      >
        <el-form-item label="品牌名称">
          <el-input
            v-model="searchForm.name"
            clearable
            placeholder="请输入品牌名称"
            class="search-input"
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
        <el-table-column prop="id" label="ID" width="80" align="center" />
        <el-table-column prop="name" label="品牌名称" min-width="160" show-overflow-tooltip />
        <el-table-column label="Logo" width="90" align="center">
          <template #default="scope">
            <el-image
              v-if="scope.row.logo"
              :src="scope.row.logo"
              :preview-src-list="[scope.row.logo]"
              preview-teleported
              fit="cover"
              class="brand-logo"
            />
            <span v-else class="empty-text">-</span>
          </template>
        </el-table-column>
        <el-table-column prop="letter" label="首字母" width="80" align="center" />
        <el-table-column prop="description" label="描述" min-width="240" show-overflow-tooltip />
        <el-table-column prop="sort" label="排序" width="90" align="center" />
        <el-table-column label="状态" width="100" align="center">
          <template #default="scope">
            <el-switch
              v-model="scope.row.status"
              :active-value="1"
              :inactive-value="0"
              @change="handleStatusChange(scope.row)"
            />
          </template>
        </el-table-column>
        <el-table-column prop="create_time" label="创建时间" width="170" align="center" />
        <el-table-column label="操作" width="140" fixed="right" align="center">
          <template #default="scope">
            <a @click="openDialog(scope.row)">编辑</a>
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

    <!-- 添加/编辑品牌弹窗 -->
    <el-dialog
      v-model="dialogVisible"
      :title="dialogForm.id ? '编辑品牌' : '添加品牌'"
      width="560px"
      :before-close="handleClose"
      destroy-on-close
    >
      <el-form
        ref="dialogFormRef"
        :model="dialogForm"
        :rules="dialogRules"
        label-width="90px"
        class="dialog-form"
      >
        <el-form-item label="品牌名称" prop="name">
          <el-input
            v-model="dialogForm.name"
            placeholder="请输入品牌名称"
            maxlength="30"
            show-word-limit
          />
        </el-form-item>
        <el-form-item label="品牌Logo">
          <Upload v-model="dialogForm.logo" :limit="1" />
        </el-form-item>
        <el-form-item label="首字母">
          <el-select
            v-model="dialogForm.letter"
            placeholder="请选择品牌首字母"
            clearable
            filterable
            class="form-width"
          >
            <el-option v-for="alpha in letterOptions" :key="alpha" :label="alpha" :value="alpha" />
          </el-select>
          <div class="form-tip">用于品牌字母索引，可留空</div>
        </el-form-item>
        <el-form-item label="品牌描述">
          <el-input
            v-model="dialogForm.description"
            type="textarea"
            :rows="3"
            maxlength="200"
            show-word-limit
            placeholder="请输入品牌描述"
          />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number
            v-model="dialogForm.sort"
            :min="0"
            :max="9999"
            placeholder="数值越大越靠前"
            class="form-width"
          />
        </el-form-item>
        <el-form-item label="状态">
          <el-radio-group v-model="dialogForm.status">
            <el-radio :label="1">启用</el-radio>
            <el-radio :label="0">禁用</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="handleClose">取 消</el-button>
          <el-button type="primary" :loading="submitLoading" @click="submitForm">确 定</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { getCurrentInstance, onBeforeMount, reactive, ref } from 'vue'
import Upload from '@/components/Upload/index'
import {
  getBrandList,
  createBrand,
  updateBrand,
  deleteBrand,
  setBrandStatus
} from '@/api/product'

defineOptions({
  name: 'MerchantProductBrand'
})

const { proxy } = getCurrentInstance()
const message = proxy.$message
const confirm = proxy.$confirm

const letterOptions = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('')
const loading = ref(false)
const list = ref([])
const total = ref(0)
const dialogVisible = ref(false)
const submitLoading = ref(false)
const searchFormRef = ref(null)
const dialogFormRef = ref(null)

const searchForm = reactive({
  name: '',
  status: '',
  page: 1,
  limit: 20
})

const dialogForm = reactive(getDefaultDialogForm())

const dialogRules = {
  name: [
    { required: true, message: '请输入品牌名称', trigger: 'blur' },
    { max: 30, message: '品牌名称最多30个字符', trigger: 'blur' }
  ]
}

function getDefaultDialogForm() {
  return {
    id: 0,
    name: '',
    letter: '',
    logo: '',
    description: '',
    sort: 0,
    status: 1
  }
}

function getList() {
  loading.value = true
  getBrandList(searchForm).then(res => {
    const data = res.data || {}
    list.value = data.list || []
    total.value = data.total || 0
  }).finally(() => {
    loading.value = false
  })
}

function handleSearch() {
  searchForm.page = 1
  getList()
}

function handleReset() {
  Object.assign(searchForm, { name: '', status: '', page: 1, limit: 20 })
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

function openDialog(row) {
  Object.assign(dialogForm, row && row.id ? {
    id: row.id,
    name: row.name,
    letter: row.letter || '',
    logo: row.logo || '',
    description: row.description || '',
    sort: Number(row.sort) || 0,
    status: Number(row.status)
  } : getDefaultDialogForm())
  dialogVisible.value = true
}

function handleClose() {
  dialogVisible.value = false
  dialogFormRef.value && dialogFormRef.value.resetFields()
  Object.assign(dialogForm, getDefaultDialogForm())
}

function submitForm() {
  dialogFormRef.value && dialogFormRef.value.validate(valid => {
    if (!valid) return
    submitLoading.value = true
    const api = dialogForm.id
      ? updateBrand(dialogForm.id, dialogForm)
      : createBrand(dialogForm)
    api.then(res => {
      message.success(res.message || '操作成功')
      handleClose()
      getList()
    }).catch(() => {}).finally(() => {
      submitLoading.value = false
    })
  })
}

function handleDelete(id) {
  confirm('确定要删除该品牌吗？', '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    deleteBrand(id).then(res => {
      message.success(res.message || '删除成功')
      getList()
    }).catch(() => {})
  }).catch(() => {})
}

function handleStatusChange(row) {
  setBrandStatus(row.id, row.status).then(res => {
    message.success(res.message || '状态更新成功')
  }).catch(() => {
    row.status = row.status === 1 ? 0 : 1
  })
}

onBeforeMount(() => {
  getList()
})
</script>

<style lang="scss" scoped>
.product-brand-page {
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

.search-input { width: 180px; }
.search-select-sm { width: 120px; }

.brand-logo {
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

.dialog-form {
  padding: 8px 16px;

  :deep(.el-input-number) {
    width: 100%;

    .el-input__inner {
      text-align: left;
    }
  }
}

.form-width {
  width: 100%;
}

.form-tip {
  font-size: 12px;
  color: #909399;
  margin-top: 4px;
  line-height: 1.5;
}

.dialog-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
</style>
