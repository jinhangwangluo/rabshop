<template>
  <div class="level-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">等级配置</h1>
        <p class="page-curator-subtitle">
          管理直播间用户等级配置，设置等级称号、贡献值要求及权益。
        </p>
      </div>
      <el-button
        type="primary"
        class="btn-curator-primary"
        icon="el-icon-plus"
        @click="handleAdd"
      >
        添加等级配置
      </el-button>
    </header>

    <div class="level-work-surface">
      <!-- 搜索区域 -->
      <el-form
        ref="searchFormRef"
        :model="searchForm"
        inline
        class="search-form level-search-form"
        @submit.prevent
      >
        <el-form-item label="等级名称">
          <el-input
            v-model="searchForm.name"
            clearable
            placeholder="请输入等级名称"
            class="search-input"
            prefix-icon="el-icon-search"
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
          <el-button
            type="primary"
            class="btn-curator-primary"
            icon="el-icon-search"
            @click="handleSearch"
          >
            查询
          </el-button>
          <el-button
            class="btn-curator-reset"
            icon="el-icon-refresh"
            @click="handleReset"
          >
            重置
          </el-button>
        </el-form-item>
      </el-form>

      <!-- 表格区域 -->
      <el-table
        v-loading="loading"
        :data="list"
        class="level-curator-table"
      >
        <el-table-column prop="id" label="ID" width="70" align="center" />
        <el-table-column prop="level" label="等级值" width="80" align="center" />
        <el-table-column prop="name" label="等级称号" min-width="120" show-overflow-tooltip />
        <el-table-column label="徽章图标" width="100" align="center">
          <template #default="scope">
            <el-image
              v-if="scope.row.icon"
              :src="scope.row.icon"
              class="icon-preview"
              fit="contain"
              :preview-src-list="[scope.row.icon]"
            />
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column prop="min_contribution" label="所需贡献值 (元)" width="120" align="center" />
        <el-table-column label="礼物折扣" width="90" align="center">
          <template #default="scope">
            {{ scope.row.benefits && scope.row.benefits.gift_discount ? (scope.row.benefits.gift_discount * 10).toFixed(0) + '折' : '-' }}
          </template>
        </el-table-column>
        <el-table-column label="升级奖励积分" width="100" align="center">
          <template #default="scope">
            {{ scope.row.benefits && scope.row.benefits.upgrade_points ? scope.row.benefits.upgrade_points : 0 }}
          </template>
        </el-table-column>
        <el-table-column prop="sort" label="排序" width="70" align="center" />
        <el-table-column label="状态" width="100" align="center">
          <template #default="scope">
            <el-switch
              :model-value="scope.row.status === 1"
              @change="handleToggleStatus(scope.row)"
            />
          </template>
        </el-table-column>
        <el-table-column label="操作" width="160" fixed="right" align="center">
          <template #default="scope">
            <a @click="handleEdit(scope.row)">编辑</a>
            <el-divider direction="vertical" />
            <a class="danger-link" @click="handleDelete(scope.row.id)">删除</a>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrap">
        <el-pagination
          class="level-curator-pagination"
          :current-page="page"
          :page-sizes="[10, 20, 50, 100]"
          :page-size="limit"
          layout="total, sizes, prev, pager, next, jumper"
          :total="total"
          @size-change="handleSizeChange"
          @current-change="handleCurrentChange"
        />
      </div>
    </div>

    <!-- 新增/编辑弹窗 -->
    <el-dialog
      v-model="dialogVisible"
      :title="dialogTitle"
      width="560px"
      custom-class="level-config-dialog"
      :close-on-click-modal="false"
      @close="handleDialogClose"
    >
      <el-form
        ref="dialogFormRef"
        :model="dialogForm"
        :rules="dialogRules"
        label-width="120px"
      >
        <el-divider content-position="left">基础配置</el-divider>
        <el-form-item label="等级值" prop="level">
          <el-input-number v-model="dialogForm.level" :min="1" :max="999" />
          <span class="tips-text">唯一标识，建议从 1 开始递增</span>
        </el-form-item>
        <el-form-item label="等级称号" prop="name">
          <el-input v-model="dialogForm.name" placeholder="如：路人、粉丝、铁粉" />
        </el-form-item>
        <el-form-item label="徽章图标" prop="icon">
          <Upload v-model="dialogForm.icon" :limit="1" />
          <span class="tips-text">建议尺寸 50x50px，支持 PNG/JPG</span>
        </el-form-item>
        <el-form-item label="最低贡献值 (元)" prop="min_contribution">
          <el-input-number v-model="dialogForm.min_contribution" :min="0" :precision="2" :step="10" />
          <span class="tips-text">累计送礼金额达到此值可升级</span>
        </el-form-item>
        <el-form-item label="排序" prop="sort">
          <el-input-number v-model="dialogForm.sort" :min="0" :max="9999" />
          <span class="tips-text">数值越大越靠前</span>
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-switch
            v-model="dialogForm.status"
            :active-value="1"
            :inactive-value="0"
          />
        </el-form-item>

        <el-divider content-position="left">权益配置</el-divider>
        <el-form-item label="礼物折扣">
          <el-input-number v-model="dialogForm.benefits.gift_discount" :min="0.01" :max="1.00" :precision="2" :step="0.01" />
          <span class="tips-text">1.00=无折扣，0.95=95 折</span>
        </el-form-item>
        <el-form-item label="升级奖励积分">
          <el-input-number v-model="dialogForm.benefits.upgrade_points" :min="0" :step="10" />
          <span class="tips-text">用户升级时自动增加积分</span>
        </el-form-item>
      </el-form>
      <template #footer>
        <span>
          <el-button @click="dialogVisible = false">取 消</el-button>
          <el-button type="primary" :loading="submitLoading" @click="handleSubmit">确 定</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, onBeforeMount, reactive, ref } from 'vue'
import {
  getCategoryLevelList,
  createCategoryLevelConfig,
  updateCategoryLevelConfig,
  deleteCategoryLevelConfig,
  toggleCategoryLevelConfigStatus
} from '@/api/live'

defineOptions({ name: 'LiveRoomLevelConfig' })

const { proxy } = getCurrentInstance()

const searchFormRef = ref(null)
const dialogFormRef = ref(null)
const loading = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(20)
const searchForm = reactive({
  name: '',
  status: ''
})
const dialogVisible = ref(false)
const submitLoading = ref(false)
const editId = ref(0)
const dialogForm = reactive({
  level: 1,
  name: '',
  icon: '',
  min_contribution: '0.00',
  benefits: {
    gift_discount: 1,
    upgrade_points: 0
  },
  sort: 0,
  status: 1
})
const dialogRules = {
  level: [
    { required: true, message: '请输入等级值', trigger: 'blur' }
  ],
  name: [
    { required: true, message: '请输入等级称号', trigger: 'blur' }
  ],
  min_contribution: [
    { required: true, message: '请输入最低贡献值', trigger: 'blur' }
  ]
}

const dialogTitle = computed(() => (editId.value ? '编辑等级配置' : '新增等级配置'))

function createSearchForm() {
  return {
    name: '',
    status: ''
  }
}

function createDialogForm() {
  return {
    level: 1,
    name: '',
    icon: '',
    min_contribution: '0.00',
    benefits: {
      gift_discount: 1,
      upgrade_points: 0
    },
    sort: 0,
    status: 1
  }
}

async function loadList() {
  loading.value = true
  try {
    const params = {
      page: page.value,
      limit: limit.value,
      ...searchForm
    }
    const res = await getCategoryLevelList(params)
    list.value = res.data.list || []
    total.value = res.data.total || 0
  } catch (error) {
    console.error(error)
    proxy?.$message?.error('加载失败')
  } finally {
    loading.value = false
  }
}

function handleSearch() {
  page.value = 1
  loadList()
}

function handleReset() {
  searchFormRef.value?.resetFields()
  Object.assign(searchForm, createSearchForm())
  handleSearch()
}

function handleSizeChange(val) {
  limit.value = val
  loadList()
}

function handleCurrentChange(val) {
  page.value = val
  loadList()
}

function resetDialogForm() {
  Object.assign(dialogForm, createDialogForm())
}

function handleAdd() {
  editId.value = 0
  resetDialogForm()
  dialogVisible.value = true
}

function handleEdit(row) {
  editId.value = row.id
  Object.assign(dialogForm, createDialogForm(), {
    level: row.level,
    name: row.name,
    icon: row.icon || '',
    min_contribution: String(row.min_contribution),
    benefits: {
      gift_discount: row.benefits && row.benefits.gift_discount !== undefined ? row.benefits.gift_discount : 1,
      upgrade_points: row.benefits && row.benefits.upgrade_points !== undefined ? row.benefits.upgrade_points : 0
    },
    sort: row.sort,
    status: row.status
  })
  dialogVisible.value = true
}

function handleDialogClose() {
  resetDialogForm()
  dialogFormRef.value?.clearValidate()
}

async function handleSubmit() {
  if (!dialogFormRef.value) return
  const valid = await dialogFormRef.value.validate().catch(() => false)
  if (!valid) return

  submitLoading.value = true
  try {
    const data = {
      ...dialogForm,
      min_contribution: parseFloat(dialogForm.min_contribution)
    }
    if (editId.value) {
      await updateCategoryLevelConfig(editId.value, data)
      proxy?.$message?.success('修改成功')
    } else {
      await createCategoryLevelConfig(data)
      proxy?.$message?.success('添加成功')
    }
    dialogVisible.value = false
    loadList()
  } catch (error) {
    console.error(error)
  } finally {
    submitLoading.value = false
  }
}

async function handleToggleStatus(row) {
  try {
    await toggleCategoryLevelConfigStatus(row.id)
    proxy?.$message?.success('状态更新成功')
    loadList()
  } catch (error) {
    console.error(error)
    proxy?.$message?.error('状态更新失败')
    loadList()
  }
}

function handleDelete(id) {
  proxy.$confirm('确定要删除该等级配置吗？', '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(async() => {
    try {
      await deleteCategoryLevelConfig(id)
      proxy?.$message?.success('删除成功')
      loadList()
    } catch (error) {
      console.error(error)
      proxy?.$message?.error('删除失败')
    }
  }).catch(() => {})
}

onBeforeMount(loadList)
</script>

<style lang="scss" scoped>
/* material/stitch/DESIGN.md */
.level-curator-page {
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

.level-work-surface {
  background: var(--curator-surface-card);
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
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

.level-search-form {
  margin-bottom: 8px;
}

.level-curator-table {
  margin-top: 8px;

  :deep(.el-table__header-wrapper),
  :deep(.el-table__fixed-header-wrapper) {
    background: var(--curator-surface-low) !important;
  }

  :deep(th.el-table__cell),
  :deep(.el-table__header th),
  :deep(.el-table__fixed-right th.el-table__cell) {
    color: var(--curator-on-surface-variant) !important;
    font-weight: 500;
    background: var(--curator-surface-low) !important;
    background-color: var(--curator-surface-low) !important;
    border-color: var(--curator-outline) !important;
  }

  :deep(.el-table__fixed-right-patch) {
    background: var(--curator-surface-low) !important;
    border-color: var(--curator-outline) !important;
  }

  :deep(.el-table__row:hover > td) {
    background: var(--curator-surface-low) !important;
  }
}

.pagination-wrap {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}

.level-curator-pagination {
  :deep(.el-pager li.active) {
    background: var(--curator-primary);
    color: var(--curator-on-primary);
    border-radius: 4px;
  }

  :deep(.btn-next),
  :deep(.btn-prev),
  :deep(.el-pager li) {
    &:hover {
      color: var(--curator-primary);
    }
  }
}

.search-input {
  width: 200px;
}

.search-select-sm {
  width: 140px;
}

.icon-preview {
  width: 40px;
  height: 40px;
  border-radius: 4px;
}

.tips-text {
  display: block;
  margin-top: 6px;
  font-size: 12px;
  color: var(--dashboard-text-muted);
}

.danger-link {
  color: #f56c6c;
}

a {
  color: var(--curator-primary);
  cursor: pointer;
  font-size: 13px;

  &:hover {
    color: var(--curator-primary-container);
  }
}

:deep(.level-config-dialog) {
  color: var(--dashboard-text-primary);
  background: var(--dashboard-surface);

  .el-dialog__header,
  .el-dialog__body,
  .el-dialog__footer {
    background: var(--dashboard-surface);
    border-color: var(--dashboard-border);
  }

  .el-form-item__label {
    color: var(--dashboard-text-primary);
  }

  .el-divider {
    background-color: var(--dashboard-border);
  }

  .el-divider__text {
    background: var(--dashboard-surface);
    color: var(--dashboard-text-primary);
    font-size: 13px;
    font-weight: 600;
  }

  .el-input-number__decrease,
  .el-input-number__increase {
    color: var(--dashboard-text-secondary);
    background: var(--dashboard-surface-muted);
    border-color: var(--dashboard-border);

    &:hover {
      color: var(--curator-primary);
    }
  }

  .el-input__inner {
    color: var(--dashboard-text-primary);
    background: var(--dashboard-surface);
    border-color: var(--dashboard-border);
  }

  .el-select .el-input .el-select__caret {
    color: var(--dashboard-text-muted);
  }
}
</style>
