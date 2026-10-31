<template>
  <div class="config-container">
    <!-- 左侧：配置分类 -->
    <div class="config-left">
      <el-card shadow="never" class="category-card">
        <template #header>
          <div class="category-header">
            <span class="header-title">配置分类</span>
            <div class="category-action" @click="handleAddCategory">
              <LegacyIcon name="el-icon-plus" />
              <span>添加</span>
            </div>
          </div>
        </template>
        <div v-loading="categoryLoading" class="category-list">
          <div
            v-for="item in displayCategoryList"
            :key="item.id"
            class="category-item"
            :class="{ 'is-active': currentCategoryId === item.id }"
            @click="handleSelectCategory(item)"
          >
            <div class="category-item__info">
              <LegacyIcon name="el-icon-folder" />
              <span class="category-item__name">{{ item.name }}</span>
            </div>
            <el-dropdown
              v-if="item.isAll"
              class="category-more"
              trigger="click"
              @command="handleCategoryRootMenuCommand"
            >
              <span class="category-more-trigger" @click.stop>
                <LegacyIcon name="el-icon-more" />
              </span>
              <template #dropdown>
                <el-dropdown-menu>
                  <el-dropdown-item command="create">添加分类</el-dropdown-item>
                  <el-dropdown-item command="refresh">刷新列表</el-dropdown-item>
                </el-dropdown-menu>
              </template>
            </el-dropdown>
            <el-dropdown
              v-else
              class="category-more"
              trigger="click"
              @command="(command) => handleCategoryMenuCommand(item, command)"
            >
              <span class="category-more-trigger" @click.stop>
                <LegacyIcon name="el-icon-more" />
              </span>
              <template #dropdown>
                <el-dropdown-menu>
                  <el-dropdown-item command="edit">编辑分类</el-dropdown-item>
                  <el-dropdown-item command="delete" divided>删除</el-dropdown-item>
                </el-dropdown-menu>
              </template>
            </el-dropdown>
          </div>
          <el-empty v-if="!categoryLoading && categoryList.length === 0" description="暂无分类，请先添加分类" :image-size="80" />
        </div>
      </el-card>
    </div>

    <!-- 右侧：配置列表 -->
    <div class="config-right">
      <el-card shadow="never" class="config-card">
        <div class="table-header">
          <div class="header-title">
            <span class="title-text">
              {{ configListTitle }}
            </span>
            <span class="title-desc">共 {{ configTotal }} 条数据</span>
          </div>
          <div class="header-actions">
            <el-button
              type="primary"
              icon="el-icon-plus"
              :disabled="categoryList.length === 0"
              @click="handleAddConfig"
            >
              添加配置
            </el-button>
          </div>
        </div>
        <el-table
          v-loading="configLoading"
          :data="configList"
          class="config-table"
        >
          <el-table-column prop="id" label="ID" width="80" align="center" />
          <el-table-column prop="config_name" label="配置名称" min-width="150">
            <template #default="scope">
              <span class="config-name">{{ scope.row.config_name }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="config_key" label="配置标识" min-width="150">
            <template #default="scope">
              <el-tag type="info">{{ scope.row.config_key }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="所属分类" width="130" show-overflow-tooltip>
            <template #default="scope">
              {{ getCategoryName(scope.row.cate_id) }}
            </template>
          </el-table-column>
          <el-table-column prop="config_desc" label="描述" min-width="200" show-overflow-tooltip />
          <el-table-column label="状态" width="100" align="center">
            <template #default="scope">
              <el-switch
                v-model="scope.row.status"
                :active-value="1"
                :inactive-value="0"
                @change="handleConfigStatusChange(scope.row)"
              />
            </template>
          </el-table-column>
          <el-table-column prop="create_time" label="创建时间" width="170" align="center" />
          <el-table-column label="操作" width="200" align="center" fixed="right">
            <template #default="scope">
              <div class="table-actions">
                <a class="table-action" @click="handleDesignForm(scope.row)">
                  <LegacyIcon name="el-icon-setting" /> 设计表单
                </a>
                <el-divider direction="vertical" />
                <a class="table-action" @click="handleEditConfig(scope.row)">
                  <LegacyIcon name="el-icon-edit" /> 编辑
                </a>
                <el-divider direction="vertical" />
                <a class="table-action table-action--danger" @click="handleDeleteConfig(scope.row)">
                  <LegacyIcon name="el-icon-delete" /> 删除
                </a>
              </div>
            </template>
          </el-table-column>
        </el-table>
        <div v-if="configTotal > 0" class="pagination-wrap">
          <el-pagination
            :current-page="configQuery.page"
            :page-sizes="[10, 20, 50]"
            :page-size="configQuery.limit"
            :total="configTotal"
            layout="total, sizes, prev, pager, next, jumper"
            @size-change="handleConfigSizeChange"
            @current-change="handleConfigPageChange"
          />
        </div>
      </el-card>
    </div>

    <!-- 添加/编辑分类弹窗 -->
    <el-dialog
      v-model="categoryDialogVisible"
      :title="categoryForm.id ? '编辑分类' : '添加分类'"
      width="480px"
      :close-on-click-modal="false"
      @close="resetCategoryForm"
    >
      <el-form ref="categoryFormRef" :model="categoryForm" :rules="categoryRules" label-width="90px">
        <el-form-item label="分类名称" prop="name">
          <el-input v-model="categoryForm.name" placeholder="请输入分类名称" maxlength="50" show-word-limit />
        </el-form-item>
        <el-form-item label="排序" prop="sort">
          <el-input-number v-model="categoryForm.sort" :min="0" :max="9999" controls-position="right" style="width: 100%" />
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-switch v-model="categoryForm.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
      </el-form>
      <template #footer>
        <div>
          <el-button @click="categoryDialogVisible = false">取 消</el-button>
          <el-button type="primary" :loading="categorySubmitting" @click="submitCategoryForm">确 定</el-button>
        </div>
      </template>
    </el-dialog>

    <!-- 添加/编辑配置弹窗 -->
    <el-dialog
      v-model="configDialogVisible"
      :title="configForm.id ? '编辑配置' : '添加配置'"
      width="680px"
      :close-on-click-modal="false"
      @close="resetConfigForm"
    >
      <el-form ref="configFormRef" :model="configForm" :rules="configRules" label-width="100px">
        <el-form-item label="所属分类" prop="cate_id">
          <el-select v-model="configForm.cate_id" placeholder="请选择配置分类" filterable style="width: 100%">
            <el-option
              v-for="item in categoryList"
              :key="item.id"
              :label="item.name"
              :value="item.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="配置名称" prop="config_name">
          <el-input v-model="configForm.config_name" placeholder="请输入配置名称" maxlength="50" show-word-limit />
        </el-form-item>
        <el-form-item label="配置标识" prop="config_key">
          <el-input v-model="configForm.config_key" placeholder="请输入配置标识（英文）" maxlength="100" />
        </el-form-item>
        <el-form-item label="配置描述" prop="config_desc">
          <el-input v-model="configForm.config_desc" type="textarea" :rows="3" placeholder="请输入配置描述" maxlength="200" show-word-limit />
        </el-form-item>
        <el-form-item label="预定义字段">
          <div class="field-def-list">
            <div v-for="(f, idx) in configForm.fields" :key="idx" class="field-def-row">
              <el-input v-model="f.field" placeholder="字段名(英文)" class="field-def-input" />
              <el-input v-model="f.label" placeholder="字段标题" class="field-def-input" />
              <el-select v-model="f.type" class="field-def-type">
                <el-option label="文本框" value="input" />
                <el-option label="多行文本" value="textarea" />
                <el-option label="数字" value="number" />
                <el-option label="单选" value="radio" />
                <el-option label="多选" value="checkbox" />
                <el-option label="上传" value="upload" />
              </el-select>
              <el-button type="text" icon="el-icon-delete" class="danger-btn" @click="removeFieldDef(idx)" />
            </div>
            <el-button icon="el-icon-plus" @click="addFieldDef">添加字段</el-button>
          </div>
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-switch v-model="configForm.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
      </el-form>
      <template #footer>
        <div>
          <el-button @click="configDialogVisible = false">取 消</el-button>
          <el-button type="primary" :loading="configSubmitting" @click="submitConfigForm">确 定</el-button>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, onBeforeMount, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  getConfigCategoryListApi,
  getConfigCategoryReadApi,
  configCategorySaveApi,
  configCategoryUpdateApi,
  configCategoryDeleteApi,
  getConfigTemplateApi,
  configTemplateSaveApi,
  configTemplateStatusApi,
  configTemplateDeleteApi,
  configTemplateUpdateApi
} from '@/api/system'

defineOptions({ name: 'SystemConfig' })

const router = useRouter()
const categoryLoading = ref(false)
const categoryDialogVisible = ref(false)
const categorySubmitting = ref(false)
const configLoading = ref(false)
const configDialogVisible = ref(false)
const configSubmitting = ref(false)
const configTotal = ref(0)
const currentCategoryId = ref('')
const currentCategory = reactive({ id: '', name: '全部配置', isAll: true })
const categoryList = reactive([])
const configList = reactive([])
const configQuery = reactive({
  page: 1,
  limit: 20,
  cate_id: ''
})
const categoryFormRef = ref(null)
const configFormRef = ref(null)

function createCategoryForm() {
  return {
    id: null,
    name: '',
    sort: 0,
    status: 1
  }
}

function createConfigForm(cateId = '') {
  return {
    id: null,
    cate_id: cateId,
    config_name: '',
    config_key: '',
    config_desc: '',
    status: 1,
    fields: []
  }
}

function resetReactiveObject(target, source) {
  Object.keys(target).forEach(key => {
    delete target[key]
  })
  Object.assign(target, source)
}

const categoryForm = reactive(createCategoryForm())
const categoryRules = {
  name: [
    { required: true, message: '请输入分类名称', trigger: 'blur' },
    { min: 1, max: 50, message: '长度在 1 到 50 个字符', trigger: 'blur' }
  ]
}

const configForm = reactive(createConfigForm())
const configRules = {
  cate_id: [
    { required: true, message: '请选择配置分类', trigger: 'change' }
  ],
  config_name: [
    { required: true, message: '请输入配置名称', trigger: 'blur' }
  ],
  config_key: [
    { required: true, message: '请输入配置标识', trigger: 'blur' },
    { pattern: /^[a-zA-Z][a-zA-Z0-9_]*$/, message: '必须以字母开头，仅支持字母、数字和下划线', trigger: 'blur' }
  ]
}

const displayCategoryList = computed(() => [
  { id: '', name: '全部配置', isAll: true },
  ...categoryList
])

const configListTitle = computed(() => (currentCategoryId.value === '' ? '全部配置列表' : `${currentCategory.name} - 配置列表`))

function setCategoryLoading(value) {
  categoryLoading.value = value
}

function setConfigLoading(value) {
  configLoading.value = value
}

function setCategorySubmitting(value) {
  categorySubmitting.value = value
}

function setConfigSubmitting(value) {
  configSubmitting.value = value
}

function setRowStatus(row, status) {
  row.status = status
}

async function getCategoryList() {
  setCategoryLoading(true)
  try {
    const res = await getConfigCategoryListApi()
    const list = res.data.list || []
    categoryList.splice(0, categoryList.length, ...list)
    syncCurrentCategory()
    await getConfigList()
  } catch (e) {
    ElMessage.error(e.message || '获取分类列表失败')
  } finally {
    setCategoryLoading(false)
  }
}

function handleSelectCategory(item) {
  currentCategoryId.value = item.id
  resetReactiveObject(currentCategory, item)
  configQuery.cate_id = item.id
  configQuery.page = 1
  getConfigList()
}

function handleAddCategory() {
  resetReactiveObject(categoryForm, createCategoryForm())
  categoryDialogVisible.value = true
}

function handleCategoryRootMenuCommand(command) {
  if (command === 'create') {
    handleAddCategory()
    return
  }
  if (command === 'refresh') {
    getCategoryList()
  }
}

function handleCategoryMenuCommand(item, command) {
  if (command === 'edit') {
    handleEditCategory(item)
    return
  }
  if (command === 'delete') {
    handleDeleteCategory(item)
  }
}

async function handleEditCategory(item) {
  try {
    const res = await getConfigCategoryReadApi(item.id)
    const data = res.data || item
    resetReactiveObject(categoryForm, {
      id: data.id,
      name: data.name,
      sort: data.sort || 0,
      status: data.status
    })
    categoryDialogVisible.value = true
  } catch (e) {
    ElMessage.error(e.message || '获取分类详情失败')
  }
}

function handleDeleteCategory(item) {
  ElMessageBox.confirm(`确定删除分类「${item.name}」吗？删除后不可恢复`, '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(async() => {
    try {
      const res = await configCategoryDeleteApi(item.id)
      ElMessage.success(res.message || '删除成功')
      if (currentCategoryId.value === item.id) {
        currentCategoryId.value = ''
        resetReactiveObject(currentCategory, displayCategoryList.value[0])
        configQuery.cate_id = ''
        configQuery.page = 1
      }
      await getCategoryList()
    } catch (e) {
      ElMessage.error(e.message || '删除失败')
    }
  }).catch(() => {})
}

async function submitCategoryForm() {
  const valid = await categoryFormRef.value?.validate?.().catch(() => false)
  if (!valid) return
  setCategorySubmitting(true)
  try {
    const { id, ...formData } = categoryForm
    const res = id ? await configCategoryUpdateApi(id, formData) : await configCategorySaveApi(formData)
    ElMessage.success(res.message || '操作成功')
    categoryDialogVisible.value = false
    await getCategoryList()
  } catch (e) {
    ElMessage.error(e.message || '操作失败')
  } finally {
    setCategorySubmitting(false)
  }
}

function resetCategoryForm() {
  categoryFormRef.value?.resetFields?.()
}

function syncCurrentCategory() {
  if (currentCategoryId.value === '') {
    resetReactiveObject(currentCategory, displayCategoryList.value[0])
    return
  }
  const selectedCategory = categoryList.find(item => String(item.id) === String(currentCategoryId.value))
  if (selectedCategory) {
    currentCategoryId.value = selectedCategory.id
    resetReactiveObject(currentCategory, selectedCategory)
    return
  }
  currentCategoryId.value = ''
  resetReactiveObject(currentCategory, displayCategoryList.value[0])
  configQuery.cate_id = ''
  configQuery.page = 1
}

async function getConfigList() {
  setConfigLoading(true)
  try {
    const res = await getConfigTemplateApi(configQuery)
    const resData = res.data || {}
    const list = resData.list || resData || []
    configList.splice(0, configList.length, ...list)
    configTotal.value = resData.count || resData.total || 0
  } catch (e) {
    ElMessage.error(e.message || '获取配置列表失败')
  } finally {
    setConfigLoading(false)
  }
}

function handleAddConfig() {
  resetReactiveObject(configForm, createConfigForm(currentCategoryId.value || ''))
  configDialogVisible.value = true
}

function handleEditConfig(row) {
  resetReactiveObject(configForm, {
    id: row.id,
    cate_id: row.cate_id || '',
    config_name: row.config_name,
    config_key: row.config_key,
    config_desc: row.config_desc || '',
    status: row.status,
    fields: row.fields ? JSON.parse(JSON.stringify(row.fields)) : []
  })
  configDialogVisible.value = true
}

function handleDeleteConfig(row) {
  ElMessageBox.confirm(`确定删除配置「${row.config_name}」吗？`, '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(async() => {
    try {
      const res = await configTemplateDeleteApi(row.id)
      ElMessage.success(res.message || '删除成功')
      await getConfigList()
    } catch (e) {
      ElMessage.error(e.message || '删除失败')
    }
  }).catch(() => {})
}

async function handleConfigStatusChange(row) {
  const currentStatus = row.status
  const previousStatus = row.status === 1 ? 0 : 1
  try {
    await configTemplateStatusApi(row.id, currentStatus)
    ElMessage.success('状态修改成功')
  } catch (e) {
    setRowStatus(row, previousStatus)
    ElMessage.error(e.message || '状态修改失败')
  }
}

async function submitConfigForm() {
  const valid = await configFormRef.value?.validate?.().catch(() => false)
  if (!valid) return
  setConfigSubmitting(true)
  try {
    const data = { ...configForm }
    const res = data.id ? await configTemplateUpdateApi(data.id, data) : await configTemplateSaveApi(data)
    ElMessage.success(res.message || '操作成功')
    configDialogVisible.value = false
    await getConfigList()
  } catch (e) {
    ElMessage.error(e.message || '操作失败')
  } finally {
    setConfigSubmitting(false)
  }
}

function resetConfigForm() {
  configFormRef.value?.resetFields?.()
}

function addFieldDef() {
  configForm.fields.push({ field: '', label: '', type: 'input' })
}

function removeFieldDef(idx) {
  configForm.fields.splice(idx, 1)
}

function handleDesignForm(row) {
  router.push({
    name: 'ConfigFormDesigner',
    query: { id: row.id, name: row.config_name }
  })
}

function getCategoryName(cateId) {
  if (cateId === 0 || cateId === '0' || cateId === '' || cateId === null || cateId === undefined) {
    return '未分类'
  }
  const category = categoryList.find(item => String(item.id) === String(cateId))
  return category ? category.name : '-'
}

function handleConfigSizeChange(val) {
  configQuery.limit = val
  configQuery.page = 1
  getConfigList()
}

function handleConfigPageChange(val) {
  configQuery.page = val
  getConfigList()
}

onBeforeMount(() => {
  getCategoryList()
})
</script>

<style scoped lang="scss">
.config-container {
  display: flex;
  align-items: stretch;
  min-height: calc(100vh - 124px);
  padding: 0;
  border-radius: 4px;
  background: var(--prev-bg-white);
}

// 左侧分类面板
.config-left {
  width: 260px;
  min-width: 260px;
  flex-shrink: 0;

  .category-card {
    height: 100%;
    border: 0;
    border-right: 1px solid var(--prev-border-color-light);
    border-radius: 0;
    background: transparent;

    :deep(.el-card__header) {
      padding: 20px 20px 18px 24px;
      border-bottom: 0;
    }

    :deep(.el-card__body) {
      padding: 0 12px 20px 16px;
    }
  }

  .category-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-height: 32px;

    .header-title {
      font-size: 16px;
      font-weight: 600;
      color: var(--ds-on-surface);
      padding: 0;
      margin: 0;
    }
  }

  .category-action {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 0;
    color: var(--ds-primary);
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: color 0.2s ease;

    &:hover {
      color: var(--ds-primary-hover);
    }
  }

  .category-list {
    min-height: 560px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    overflow-x: hidden;
    overflow-y: auto;

    &::-webkit-scrollbar {
      display: none;
    }
  }

  .category-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    height: 40px;
    padding: 0 10px;
    border-radius: 2px;
    background: transparent;
    cursor: pointer;
    transition: background 0.2s ease, color 0.2s ease;

    &:hover {
      background: var(--ds-surface-low);
    }

    &.is-active {
      background: var(--ds-surface-low);

      .category-item__name {
        color: var(--ds-primary);
      }

      .category-item__info i {
        color: var(--ds-primary);
      }
    }

    .category-item__info {
      display: flex;
      align-items: center;
      gap: 12px;
      flex: 1;
      min-width: 0;
      overflow: hidden;

      i {
        font-size: 16px;
        color: var(--ds-primary);
      }

      .category-item__name {
        font-size: 14px;
        color: var(--ds-on-surface-variant);
        font-weight: 400;
        line-height: 20px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }
    }

    .category-more {
      display: inline-flex;
      align-items: center;
      flex-shrink: 0;
    }

    .category-more-trigger {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 28px;
      height: 28px;
      border-radius: 2px;
      color: var(--ds-on-surface-variant);
      cursor: pointer;
      transition: color 0.2s ease;

      i {
        font-size: 18px;
        line-height: 1;
      }

      &:hover {
        color: var(--ds-primary);
      }
    }
  }
}

// 右侧配置面板
.config-right {
  flex: 1;
  min-width: 0;
  margin-left: 20px;

  .config-card {
    height: 100%;
    border: 0;
    border-radius: 0;
    background: transparent;

    :deep(.el-card__body) {
      padding: 0 13px 10px 0;
    }
  }

  .table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-height: 32px;
    margin-bottom: 16px;
    padding: 0;
    border-bottom: 0;

    .header-title {
      display: flex;
      align-items: center;
      gap: 12px;

      .title-text {
        font-size: 16px;
        font-weight: 600;
        color: var(--ds-on-surface);
      }

      .title-desc {
        font-size: 13px;
        color: var(--ds-on-surface-variant);
      }
    }

    .header-actions {
      display: flex;
      gap: 12px;
    }
  }

  .config-table {
    :deep(.el-table) {
      font-size: 14px;
    }

    :deep(.el-table__header-wrapper th),
    :deep(.el-table__fixed-header-wrapper th) {
      background: var(--prev-bg-white) !important;
      background-color: var(--prev-bg-white) !important;
    }

    :deep(.el-table__cell.el-table-fixed-column--left),
    :deep(.el-table__cell.el-table-fixed-column--right) {
      background: var(--prev-bg-white) !important;
      background-color: var(--prev-bg-white) !important;
    }

    :deep(.el-table__fixed-right-patch) {
      background: var(--prev-bg-white) !important;
      background-color: var(--prev-bg-white) !important;
    }

    .config-name {
      font-weight: 500;
      color: var(--ds-on-surface);
    }

    .table-actions {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      white-space: nowrap;

      :deep(.el-divider--vertical) {
        margin: 0;
      }
    }

    .table-action {
      display: inline-flex;
      align-items: center;
      gap: 3px;
      font-size: 14px;
      color: var(--ds-primary);
      cursor: pointer;
      text-decoration: none;
      white-space: nowrap;

      &:hover {
        color: var(--ds-primary-hover);
      }

      &--danger {
        color: var(--dashboard-danger);

        &:hover {
          color: var(--dashboard-danger);
        }
      }
    }
  }

  .pagination-wrap {
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
  }
}

// 通用按钮样式
:deep(.el-button) {
  border-radius: 4px;

  &.el-button--primary {
    background: var(--ds-primary);
    border-color: var(--ds-primary);
    color: var(--ds-on-primary);

    &:hover {
      background: var(--ds-primary-hover);
      border-color: var(--ds-primary-hover);
    }
  }
}

// 弹窗样式
:deep(.el-dialog) {
  border-radius: 8px;

  .el-dialog__header {
    padding: 20px 24px 16px;
    border-bottom: 1px solid var(--prev-border-color-light);

    .el-dialog__title {
      font-size: 16px;
      font-weight: 600;
    }
  }

  .el-dialog__body {
    padding: 24px;
  }

  .el-dialog__footer {
    padding: 16px 24px;
    border-top: 1px solid var(--prev-border-color-light);
  }
}

// 预定义字段编辑区
.field-def-list {
  width: 100%;

  .field-def-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;

    .field-def-input {
      flex: 1;
    }

    .field-def-type {
      width: 110px;
      flex-shrink: 0;
    }

    .danger-btn {
      color: var(--dashboard-danger);
      padding: 0;
      flex-shrink: 0;

      &:hover {
        color: var(--dashboard-danger);
      }
    }
  }
}

@media (max-width: 1200px) {
  .config-left {
    width: 240px;
    min-width: 240px;
  }
}

@media (max-width: 960px) {
  .config-container {
    display: block;
    min-height: auto;
  }

  .config-left {
    width: 100%;
    min-width: 0;
    margin-bottom: 16px;

    .category-card {
      border-right: 0;
      border-bottom: 1px solid var(--prev-border-color-light);

      :deep(.el-card__header) {
        padding-right: 0;
      }

      :deep(.el-card__body) {
        padding-right: 0;
      }
    }

    .category-list {
      min-height: auto;
    }
  }

  .config-right {
    margin-left: 0;

    .config-card {
      :deep(.el-card__body) {
        padding-right: 0;
      }
    }

    .table-header {
      align-items: flex-start;
      flex-direction: column;
      gap: 12px;
    }
  }
}
</style>
