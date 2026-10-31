<template>
  <div class="combination-page">
    <el-card shadow="never" class="left-panel">
      <template #header>
        <div class="panel-header">
          <span>组合数据分类</span>
          <el-button type="primary" size="mini" :icon="Plus" @click="openCategoryDialog()">添加</el-button>
        </div>
      </template>
      <div v-loading="categoryLoading">
        <div
          v-for="item in categoryList"
          :key="item.id"
          class="category-item"
          :class="{ active: currentCategoryId === item.id }"
          @click="selectCategory(item)"
        >
          <span>{{ item.name }}</span>
          <span @click.stop>
            <LegacyIcon name="el-icon-edit" @click="openCategoryDialog(item)" />
            <LegacyIcon name="el-icon-delete" @click="deleteCategory(item)" />
          </span>
        </div>
        <el-empty v-if="!categoryLoading && categoryList.length === 0" description="暂无分类" :image-size="80" />
      </div>
    </el-card>

    <div class="right-panel">
      <el-card shadow="never">
        <template #header>
          <div class="panel-header">
            <span>组合数据配置</span>
            <el-button type="primary" size="mini" :icon="Plus" :disabled="!currentCategoryId" @click="openConfigDialog()">添加配置</el-button>
          </div>
        </template>
        <el-table v-loading="configLoading" :data="configList" >
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

    <el-dialog v-model="categoryVisible" :title="categoryForm.id ? '编辑分类' : '添加分类'" width="460px">
      <el-form :model="categoryForm" label-width="90px">
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

    <el-dialog v-model="configVisible" :title="configForm.id ? '编辑数据配置' : '添加数据配置'" width="1080px" class="config-dialog">
      <el-form :model="configForm" label-width="100px">
        <el-row :gutter="16">
          <el-col :span="8">
            <el-form-item label="配置名称">
              <el-input v-model="configForm.config_name" placeholder="例如：首页轮播图" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="配置标识">
              <el-input v-model="configForm.config_key" :disabled="!!configForm.id" placeholder="例如：home_banner" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
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
                  <el-button type="text" :icon="Top" :disabled="index === 0" @click="moveField(index, -1)" />
                  <el-button type="text" :icon="Bottom" :disabled="index === configForm.content.length - 1" @click="moveField(index, 1)" />
                  <el-button type="text" :icon="Delete" class="danger" @click="removeField(index)" />
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
                  <el-button type="text" :icon="Delete" @click="field.props.options.splice(optIndex, 1)" />
                </div>
                <el-button size="mini" :icon="Plus" @click="field.props.options.push({ label: '选项', value: String(field.props.options.length + 1) })">添加选项</el-button>
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
import { Bottom, Delete, Plus, Top } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { ref } from 'vue'
import { useRouter } from 'vue-router'

defineOptions({
  name: 'CombinationData'
})

const router = useRouter()

let fieldSeed = 0
function genFieldId() {
  fieldSeed += 1
  return 'field_' + Date.now() + '_' + fieldSeed
}

function createEmptyConfigForm() {
  return { id: 0, category_id: 0, config_name: '', config_key: '', config_desc: '', content: [], field_keys: [], fields: [], status: 1 }
}

const categoryLoading = ref(false)
const configLoading = ref(false)
const categoryList = ref([])
const configList = ref([])
const currentCategoryId = ref('')
const categoryVisible = ref(false)
const configVisible = ref(false)
const categoryForm = ref({ id: 0, name: '', sort: 0, status: 1 })
const configForm = ref(createEmptyConfigForm())
const basicComponents = [
  { type: 'input', label: '文本框', icon: 'el-icon-edit', defaultProps: { field: '', label: '文本框', placeholder: '请输入', maxlength: 200, required: false, disabled: false, defaultValue: '', description: '' }},
  { type: 'textarea', label: '多行文本', icon: 'el-icon-document', defaultProps: { field: '', label: '多行文本', placeholder: '请输入', rows: 3, required: false, disabled: false, defaultValue: '', description: '' }},
  { type: 'number', label: '数字', icon: 'el-icon-s-data', defaultProps: { field: '', label: '数字', min: 0, max: 99999, step: 1, required: false, disabled: false, defaultValue: null, description: '' }},
  { type: 'radio', label: '单选', icon: 'el-icon-circle-check', defaultProps: { field: '', label: '单选', options: [{ label: '选项一', value: '1' }, { label: '选项二', value: '2' }], required: false, disabled: false, defaultValue: '', description: '' }},
  { type: 'checkbox', label: '多选', icon: 'el-icon-finished', defaultProps: { field: '', label: '多选', options: [{ label: '选项一', value: '1' }, { label: '选项二', value: '2' }], required: false, disabled: false, defaultValue: [], description: '' }},
  { type: 'upload', label: '上传', icon: 'el-icon-upload', defaultProps: { field: '', label: '上传', buttonText: '点击上传', accept: 'image/*', limit: 1, required: false, disabled: false, description: '' }}
]

function loadCategories() {
  categoryLoading.value = true
  getCombinationDataCategoryList().then(res => {
    categoryList.value = (res.data && res.data.list) || []
    if (!currentCategoryId.value && categoryList.value.length) selectCategory(categoryList.value[0])
  }).finally(() => {
    categoryLoading.value = false
  })
}

function selectCategory(item) {
  currentCategoryId.value = item.id
  loadConfigs()
}

function openCategoryDialog(row) {
  categoryForm.value = row ? { id: row.id, name: row.name, sort: row.sort || 0, status: row.status } : { id: 0, name: '', sort: 0, status: 1 }
  categoryVisible.value = true
}

function submitCategory() {
  const request = categoryForm.value.id ? updateCombinationDataCategory(categoryForm.value.id, categoryForm.value) : saveCombinationDataCategory(categoryForm.value)
  request.then(() => {
    categoryVisible.value = false
    loadCategories()
  })
}

function deleteCategory(row) {
  ElMessageBox.confirm(`确定删除分类「${row.name}」吗？`, '提示').then(() => deleteCombinationDataCategory(row.id).then(() => loadCategories())).catch(() => {})
}

function loadConfigs() {
  configLoading.value = true
  getCombinationDataConfigList({ category_id: currentCategoryId.value, page: 1, limit: 50 }).then(res => {
    const data = res.data || {}
    configList.value = data.list || []
  }).finally(() => {
    configLoading.value = false
  })
}

function openConfigDialog(row) {
  if (row) {
    const content = normalizeFields(row.content || [])
    configForm.value = { ...createEmptyConfigForm(), ...row, content }
  } else {
    configForm.value = { ...createEmptyConfigForm(), category_id: currentCategoryId.value }
  }
  configVisible.value = true
}

function submitConfig() {
  const fields = configForm.value.content.filter(f => f.props && f.props.field)
  if (fields.length !== configForm.value.content.length) {
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
    ...configForm.value,
    category_id: currentCategoryId.value,
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

function deleteConfig(row) {
  ElMessageBox.confirm(`确定删除配置「${row.config_name}」吗？`, '提示').then(() => deleteCombinationDataConfig(row.id).then(() => loadConfigs())).catch(() => {})
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
  configForm.value.content.push({ _id: genFieldId(), type: item.type, label: item.label, icon: item.icon, props })
}

function moveField(index, step) {
  const next = index + step
  const item = configForm.value.content.splice(index, 1)[0]
  configForm.value.content.splice(next, 0, item)
}

function removeField(index) {
  configForm.value.content.splice(index, 1)
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
  display: grid;
  grid-template-columns: 260px minmax(0, 1fr);
  gap: 12px;
}
.panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.category-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px;
  border-radius: 6px;
  cursor: pointer;
  &.active {
    color: var(--ds-primary);
    background: var(--ds-surface-container-highest);
  }
  i {
    margin-left: 8px;
  }
}
.danger {
  color: #f56c6c;
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
  color: #f56c6c;
}
.config-help {
  margin-bottom: 16px;
}
.field-designer {
  display: grid;
  grid-template-columns: 260px minmax(0, 1fr);
  gap: 20px;
  align-items: stretch;
  min-height: 520px;
}
.designer-side {
  display: flex;
  flex-direction: column;
  gap: 18px;
  padding: 0 20px 20px 0;
  border-right: 1px solid #edf0f5;
}
.designer-title {
  margin-bottom: 6px;
  font-size: 24px;
  font-weight: 600;
  line-height: 1.3;
  color: #2f343d;
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
  height: 56px;
  padding: 0 14px;
  font-size: 18px;
  font-weight: 600;
  line-height: 1;
  color: #20242a;
  cursor: pointer;
  background: #fff;
  border: 1px solid #dcdfe6;
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
  border: 1px solid #ebeef5;
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
  color: #303133;
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
</style>
