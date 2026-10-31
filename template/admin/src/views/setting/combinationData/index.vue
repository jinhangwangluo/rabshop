<template>
  <div class="combination-page">
    <el-card shadow="never" class="left-panel">
      <template #header>
        <div class="panel-header">
          <span>组合数据分类</span>
          <div class="panel-action" @click="openCategoryDialog()">
            <LegacyIcon name="el-icon-plus" />
            <span>添加</span>
          </div>
        </div>
      </template>
      <div v-loading="categoryLoading" class="category-list">
        <div
          v-for="item in displayCategoryList"
          :key="item.isAll ? 'all' : item.id"
          class="category-item"
          :class="{ active: currentCategoryId === item.id }"
          @click="selectCategory(item)"
        >
          <span class="category-name">
            <LegacyIcon name="el-icon-folder" class=" category-icon" />
            <span>{{ item.name }}</span>
          </span>
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
        <el-empty v-if="!categoryLoading && displayCategoryList.length === 0" description="暂无分类" :image-size="80" />
      </div>
    </el-card>

    <div class="right-panel">
      <el-card shadow="never" class="right-card">
        <template #header>
          <div class="panel-header">
            <span>组合数据配置</span>
            <el-button type="primary" size="mini" icon="el-icon-plus" :disabled="!canCreateConfig" @click="openConfigDialog()">添加配置</el-button>
          </div>
        </template>
        <el-table v-loading="configLoading" :data="configList">
          <el-table-column prop="id" label="ID" width="70" />
          <el-table-column prop="config_name" label="配置名称" min-width="140" />
          <el-table-column prop="config_key" label="数据类型" min-width="130" />
          <el-table-column label="状态" width="90">
            <template #default="{ row }">
              <el-switch v-model="row.status" :active-value="1" :inactive-value="0" @change="changeConfigStatus(row)" />
            </template>
          </el-table-column>
          <el-table-column label="操作" width="260">
            <template #default="{ row }">
              <a href="javascript:;" class="table-action" @click="openDataList(row)">数据配置</a>
              <a href="javascript:;" class="table-action" @click="openConfigDialog(row)">编辑</a>
              <a href="javascript:;" class="table-action danger" @click="deleteConfig(row)">删除</a>
            </template>
          </el-table-column>
        </el-table>
      </el-card>
    </div>

    <el-dialog
      v-model="categoryVisible"
      :title="categoryForm.id ? '编辑分类' : '添加分类'"
      width="460px"
      :close-on-click-modal="false"
    >
      <el-form ref="categoryFormRef" :model="categoryForm" label-width="90px">
        <el-form-item label="分类名称">
          <el-input v-model="categoryForm.name" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="categoryForm.sort" :min="0" controls-position="right" />
        </el-form-item>
      </el-form>
      <template #footer>
        <div>
          <el-button @click="categoryVisible = false">取消</el-button>
          <el-button type="primary" @click="submitCategory">保存</el-button>
        </div>
      </template>
    </el-dialog>

    <el-dialog
      v-model="configVisible"
      :title="configForm.id ? '编辑数据配置' : '添加数据配置'"
      width="1080px"
      class="config-dialog"
      :close-on-click-modal="false"
    >
      <el-form :model="configForm" label-width="100px">
        <el-row :gutter="16">
          <el-col :span="6">
            <el-form-item label="所属分类">
              <el-select v-model="configForm.category_id" class="config-category-select" filterable placeholder="请选择分类">
                <el-option v-for="item in categoryList" :key="item.id" :label="item.name" :value="item.id" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="配置名称">
              <el-input v-model="configForm.config_name" placeholder="例如：首页轮播图" />
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="配置标识">
              <el-input v-model="configForm.config_key" :disabled="!!configForm.id" placeholder="例如：home_banner" />
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="状态">
              <el-switch v-model="configForm.status" :active-value="1" :inactive-value="0" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="配置描述">
          <el-input v-model="configForm.config_desc" type="textarea" :rows="2" placeholder="说明该组合数据用于哪个页面或业务场景" />
        </el-form-item>
        <el-alert
          class="config-help"
          type="info"
          :closable="false"
          show-icon
          title="设计字段会直接保存到当前数据配置中；数据列表和添加数据表单会按这里的字段自动渲染。字段标识必须唯一，建议使用英文、数字和下划线。"
        />
        <div class="field-designer">
          <div class="designer-side">
            <div class="designer-title">组件库</div>
            <button v-for="item in basicComponents" :key="item.type" type="button" class="component-button" @click="addField(item)">
              <LegacyIcon :name="item.icon" />
              <span>{{ item.label }}</span>
            </button>
          </div>
          <div class="designer-main">
            <div class="designer-title">设计字段</div>
            <el-empty v-if="configForm.content.length === 0" description="请从组件库添加字段" :image-size="70" />
            <div v-for="(field, index) in configForm.content" :key="field._id" class="field-row">
              <div class="field-row__header">
                <div class="field-title-line">
                  <span class="field-title">{{ index + 1 }}. {{ field.label }}</span>
                  <span class="required-switch">
                    <span class="inline-label">必填</span>
                    <el-switch v-model="field.props.required" />
                  </span>
                </div>
                <div class="field-actions">
                  <el-button type="text" icon="el-icon-top" :disabled="index === 0" @click="moveField(index, -1)" />
                  <el-button type="text" icon="el-icon-bottom" :disabled="index === configForm.content.length - 1" @click="moveField(index, 1)" />
                  <el-button type="text" icon="el-icon-delete" class="danger" @click="removeField(index)" />
                </div>
              </div>
              <div class="field-config-row">
                <div class="inline-form-item">
                  <span class="inline-label">字段标识</span>
                  <el-input v-model="field.props.field" placeholder="英文标识" />
                </div>
                <div class="inline-form-item">
                  <span class="inline-label">字段标题</span>
                  <el-input v-model="field.props.label" />
                </div>
                <div class="inline-form-item">
                  <span class="inline-label">占位提示</span>
                  <el-input v-model="field.props.placeholder" />
                </div>
              </div>
              <el-form-item v-if="['radio', 'checkbox'].includes(field.type)" label="选项">
                <div v-for="(opt, optIndex) in field.props.options" :key="optIndex" class="option-row">
                  <el-input v-model="opt.label" placeholder="选项名" />
                  <el-input v-model="opt.value" placeholder="选项值" />
                  <el-button type="text" icon="el-icon-delete" @click="field.props.options.splice(optIndex, 1)" />
                </div>
                <el-button size="mini" icon="el-icon-plus" @click="field.props.options.push({ label: '选项', value: String(field.props.options.length + 1) })">添加选项</el-button>
              </el-form-item>
              <div class="field-description-row">
                <span class="inline-label">字段说明</span>
                <el-input v-model="field.props.description" type="textarea" :rows="2" placeholder="展示给录入人员的字段说明" />
              </div>
            </div>
          </div>
        </div>
      </el-form>
      <template #footer>
        <div>
          <el-button @click="configVisible = false">取消</el-button>
          <el-button type="primary" @click="submitConfig">保存</el-button>
        </div>
      </template>
    </el-dialog>

  </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  getCombinationDataCategoryList,
  saveCombinationDataCategory,
  updateCombinationDataCategory,
  deleteCombinationDataCategory,
  getCombinationDataConfigList,
  saveCombinationDataConfig,
  updateCombinationDataConfig,
  deleteCombinationDataConfig,
  changeCombinationDataConfigStatus
} from '@/api/combinationData'

let fieldSeed = 0
const ALL_CATEGORY_ID = ''

function genFieldId() {
  fieldSeed += 1
  return 'field_' + Date.now() + '_' + fieldSeed
}

function createEmptyConfigForm() {
  return { id: 0, category_id: 0, config_name: '', config_key: '', config_desc: '', content: [], field_keys: [], fields: [], status: 1 }
}

defineOptions({
  name: 'CombinationData'
})

const router = useRouter()
const categoryFormRef = ref(null)
const categoryLoading = ref(false)
const configLoading = ref(false)
const categoryList = ref([])
const configList = ref([])
const currentCategoryId = ref(ALL_CATEGORY_ID)
const categoryVisible = ref(false)
const configVisible = ref(false)
const categoryForm = reactive({ id: 0, name: '', sort: 0, status: 1 })
const configForm = reactive(createEmptyConfigForm())
const basicComponents = [
  { type: 'input', label: '文本框', icon: 'el-icon-edit', defaultProps: { field: '', label: '文本框', placeholder: '请输入', maxlength: 200, required: false, disabled: false, defaultValue: '', description: '' }},
  { type: 'textarea', label: '多行文本', icon: 'el-icon-document', defaultProps: { field: '', label: '多行文本', placeholder: '请输入', rows: 3, required: false, disabled: false, defaultValue: '', description: '' }},
  { type: 'number', label: '数字', icon: 'el-icon-s-data', defaultProps: { field: '', label: '数字', min: 0, max: 99999, step: 1, required: false, disabled: false, defaultValue: null, description: '' }},
  { type: 'radio', label: '单选', icon: 'el-icon-circle-check', defaultProps: { field: '', label: '单选', options: [{ label: '选项一', value: '1' }, { label: '选项二', value: '2' }], required: false, disabled: false, defaultValue: '', description: '' }},
  { type: 'checkbox', label: '多选', icon: 'el-icon-finished', defaultProps: { field: '', label: '多选', options: [{ label: '选项一', value: '1' }, { label: '选项二', value: '2' }], required: false, disabled: false, defaultValue: [], description: '' }},
  { type: 'upload', label: '上传', icon: 'el-icon-upload', defaultProps: { field: '', label: '上传', buttonText: '点击上传', accept: 'image/*', limit: 1, required: false, disabled: false, description: '' }}
]

const displayCategoryList = computed(() => [{ id: ALL_CATEGORY_ID, name: '全部分类', isAll: true }, ...categoryList.value])
const isAllCategorySelected = computed(() => currentCategoryId.value === ALL_CATEGORY_ID)
const canCreateConfig = computed(() => categoryList.value.length > 0)

void loadCategories()
void loadConfigs()

function loadCategories() {
  categoryLoading.value = true
  getCombinationDataCategoryList().then(res => {
    categoryList.value = (res.data && res.data.list) || []
    const currentExists = categoryList.value.some(item => String(item.id) === String(currentCategoryId.value))
    if (!isAllCategorySelected.value && !currentExists) selectCategory({ id: ALL_CATEGORY_ID, name: '全部分类', isAll: true })
  }).finally(() => {
    categoryLoading.value = false
  })
}

function selectCategory(item) {
  currentCategoryId.value = item.id
  loadConfigs()
}

function openCategoryDialog(row) {
  if (row && row.isAll) return
  Object.assign(categoryForm, row ? { id: row.id, name: row.name, sort: row.sort || 0, status: row.status } : { id: 0, name: '', sort: 0, status: 1 })
  categoryVisible.value = true
}

function handleCategoryRootMenuCommand(command) {
  if (command === 'create') {
    openCategoryDialog()
    return
  }
  if (command === 'refresh') {
    loadCategories()
  }
}

function handleCategoryMenuCommand(row, command) {
  if (command === 'edit') {
    openCategoryDialog(row)
    return
  }
  if (command === 'delete') {
    deleteCategory(row)
  }
}

function submitCategory() {
  const request = categoryForm.id ? updateCombinationDataCategory(categoryForm.id, categoryForm) : saveCombinationDataCategory(categoryForm)
  request.then(() => {
    categoryVisible.value = false
    loadCategories()
  })
}

async function deleteCategory(row) {
  if (row && row.isAll) return
  try {
    await ElMessageBox.confirm(`确定删除分类「${row.name}」吗？`, '提示')
  } catch {
    return
  }
  await deleteCombinationDataCategory(row.id)
  loadCategories()
}

function loadConfigs() {
  configLoading.value = true
  const params = { page: 1, limit: 50 }
  if (!isAllCategorySelected.value && currentCategoryId.value) params.category_id = currentCategoryId.value
  getCombinationDataConfigList(params).then(res => {
    const data = res.data || {}
    configList.value = data.list || []
  }).finally(() => {
    configLoading.value = false
  })
}

function openConfigDialog(row) {
  if (row) {
    const content = normalizeFields(row.content || [])
    Object.assign(configForm, createEmptyConfigForm(), row, { content })
  } else {
    if (!canCreateConfig.value) {
      ElMessage.warning('请先添加分类后再添加配置')
      return
    }
    const categoryId = isAllCategorySelected.value ? '' : currentCategoryId.value
    Object.assign(configForm, createEmptyConfigForm(), { category_id: categoryId })
  }
  configVisible.value = true
}

function submitConfig() {
  if (!configForm.category_id) {
    ElMessage.warning('请选择所属分类')
    return
  }
  const fields = configForm.content.filter(f => f.props && f.props.field)
  if (fields.length !== configForm.content.length) {
    ElMessage.warning('请为所有设计字段填写字段标识')
    return
  }
  const keys = fields.map(f => f.props.field)
  const duplicate = keys.find((key, index) => keys.indexOf(key) !== index)
  if (duplicate) {
    ElMessage.warning(`字段标识「${duplicate}」重复`)
    return
  }
  const data = {
    ...configForm,
    category_id: configForm.category_id,
    field_keys: keys,
    fields: fields.map(field => ({
      field: field.props.field,
      label: field.props.label || field.label,
      type: field.type
    }))
  }
  const request = data.id ? updateCombinationDataConfig(data.id, data) : saveCombinationDataConfig(data)
  request.then(() => {
    configVisible.value = false
    loadConfigs()
  })
}

function changeConfigStatus(row) {
  changeCombinationDataConfigStatus(row.id, row.status)
}

async function deleteConfig(row) {
  try {
    await ElMessageBox.confirm(`确定删除配置「${row.config_name}」吗？`, '提示')
  } catch {
    return
  }
  await deleteCombinationDataConfig(row.id)
  loadConfigs()
}

function openDataList(row) {
  router.push({
    name: 'CombinationDataList',
    query: {
      config_id: row.id,
      category_id: row.category_id,
      name: row.config_name
    }
  })
}

function addField(item) {
  const props = JSON.parse(JSON.stringify(item.defaultProps))
  configForm.content.push({ _id: genFieldId(), type: item.type, label: item.label, icon: item.icon, props })
}

function moveField(index, step) {
  const next = index + step
  const item = configForm.content.splice(index, 1)[0]
  configForm.content.splice(next, 0, item)
}

function removeField(index) {
  configForm.content.splice(index, 1)
}

function normalizeFields(fields) {
  return fields.map(field => {
    const component = basicComponents.find(item => item.type === field.type)
    return {
      ...field,
      _id: field._id || genFieldId(),
      label: field.label || (field.props && field.props.label) || (field.defaultProps && field.defaultProps.label) || '',
      props: {
        ...((component && component.defaultProps) || {}),
        ...(field.defaultProps || {}),
        ...(field.props || {})
      }
    }
  })
}
</script>

<style scoped lang="scss">
.combination-page {
  display: flex;
  align-items: stretch;
  min-height: calc(100vh - 124px);
  padding: 0;
  border-radius: 4px;
  background: var(--prev-bg-white);
}
.left-panel {
  flex-shrink: 0;
  width: 260px;
  min-width: 260px;
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
.right-panel {
  flex: 1;
  min-width: 0;
  margin-left: 20px;
}
.right-card {
  height: 100%;
  border: 0;
  border-radius: 0;
  background: transparent;

  :deep(.el-card__header) {
    padding: 0 13px 20px 0;
    border-bottom: 0;
  }

  :deep(.el-card__body) {
    padding: 0 13px 10px 0;
  }
}
.panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 32px;
  color: var(--ds-on-surface);
  font-size: 16px;
  font-weight: 600;
}
.panel-action {
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
  align-items: center;
  justify-content: space-between;
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

  &.active {
    background: var(--ds-surface-low);

    .category-name,
    .category-icon {
      color: var(--ds-primary);
    }
  }
}
.category-name {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  flex: 1;
  min-width: 0;
  color: var(--ds-on-surface-variant);
  font-size: 14px;
  font-weight: 400;
  line-height: 20px;
}
.category-name span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.category-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 16px;
  height: 16px;
  color: var(--ds-primary);
  font-size: 16px;
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
.danger {
  color: var(--dashboard-danger);
}
.table-action {
  margin-right: 10px;
  color: var(--ds-primary);
  cursor: pointer;
  text-decoration: none;
}
.table-action:last-child {
  margin-right: 0;
}
.table-action.danger {
  color: var(--dashboard-danger);
}
.config-help {
  margin-bottom: 16px;
}
.config-category-select {
  width: 100%;
}
.field-designer {
  display: grid;
  grid-template-columns: 138px minmax(0, 1fr);
  gap: 20px;
  align-items: stretch;
  min-height: 520px;
}
.designer-side {
  display: flex;
  flex-direction: column;
  gap: 18px;
  padding: 0 20px 20px 0;
  border-right: 1px solid var(--prev-border-color-light);
}
.designer-title {
  margin-bottom: 6px;
  font-size: 24px;
  font-weight: 600;
  line-height: 1.3;
  color: var(--ds-on-surface);
}
.designer-main {
  min-width: 0;
  padding-left: 12px;
}
.component-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  padding: 10px 14px;
  font-size: 13px;
  font-weight: 600;
  line-height: 1;
  color: var(--ds-on-surface);
  cursor: pointer;
  background: var(--prev-bg-white);
  border: 1px solid var(--prev-border-color-base);
  border-radius: 14px;
  outline: none;
  transition: border-color .15s ease, color .15s ease, box-shadow .15s ease;
}
.component-button i {
  margin-right: 14px;
  font-size: 18px;
}
.component-button:hover {
  color: var(--ds-primary);
  border-color: var(--ds-primary);
  box-shadow: 0 2px 8px rgba(0, 0, 0, .04);
}
.field-row {
  padding: 12px;
  margin-bottom: 12px;
  border: 1px solid var(--prev-border-color-lighter);
  border-radius: 6px;
}
.field-row__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 18px;
  font-weight: 600;
}
.field-title-line {
  display: flex;
  align-items: center;
  gap: 14px;
}
.field-title {
  font-size: 16px;
  line-height: 32px;
}
.required-switch {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.field-actions {
  display: inline-flex;
  align-items: center;
  gap: 18px;
}
.field-config-row {
  display: grid;
  grid-template-columns: repeat(3, minmax(260px, 1fr));
  gap: 24px;
  align-items: center;
  margin-bottom: 16px;
}
.inline-form-item {
  display: grid;
  grid-template-columns: 76px minmax(0, 1fr);
  gap: 10px;
  align-items: center;
}
.inline-form-item--switch {
  grid-template-columns: 42px 1fr;
}
.field-description-row {
  display: grid;
  grid-template-columns: 76px minmax(0, 1fr);
  gap: 10px;
  align-items: flex-start;
}
.field-description-row .inline-label {
  padding-top: 8px;
  text-align: right;
}
.inline-label {
  font-weight: 600;
  color: var(--ds-on-surface);
  white-space: nowrap;
}
.inline-form-item :deep(.el-input) {
  width: 100%;
}
.field-description-row :deep(.el-textarea) {
  width: 100%;
}
.option-row {
  display: grid;
  grid-template-columns: 1fr 1fr 40px;
  gap: 8px;
  margin-bottom: 8px;
}

@media (max-width: 1200px) {
  .left-panel {
    width: 240px;
    min-width: 240px;
  }
}

@media (max-width: 960px) {
  .combination-page {
    display: block;
    min-height: auto;
  }

  .left-panel {
    width: 100%;
    min-width: 0;
    margin-bottom: 16px;
    border-right: 0;
    border-bottom: 1px solid var(--prev-border-color-light);

    :deep(.el-card__header) {
      padding-right: 0;
    }

    :deep(.el-card__body) {
      padding-right: 0;
    }
  }

  .right-panel {
    margin-left: 0;
  }

  .right-card {
    :deep(.el-card__header),
    :deep(.el-card__body) {
      padding-right: 0;
    }
  }

  .category-list {
    min-height: auto;
  }
}
</style>
