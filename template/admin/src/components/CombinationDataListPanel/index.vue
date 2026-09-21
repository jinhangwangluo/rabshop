<template>
  <div class="combination-data-list-panel">
    <el-card shadow="never">
      <template #header>
        <div v-if="showHeader" class="panel-header">
          <div class="title-wrap">
            <el-button v-if="showBack" type="text" icon="el-icon-arrow-left" @click="goBack">返回</el-button>
            <span>{{ resolvedTitle }}</span>
          </div>
          <div class="header-actions">
            <slot name="header-actions" :config="currentConfig" :reload="loadData" />
            <el-button
              v-if="allowCreate"
              type="primary"
              size="mini"
              icon="el-icon-plus"
              :disabled="!currentConfig"
              @click="openDataDialog()"
            >
              添加数据
            </el-button>
          </div>
        </div>
      </template>

      <slot
        name="before-table"
        :config="currentConfig"
        :list="dataList"
        :loading="dataLoading"
        :reload="loadData"
      />

      <slot
        name="table"
        :config="currentConfig"
        :columns="dataColumns"
        :list="dataList"
        :loading="dataLoading"
        :reload="loadData"
        :open="openDataDialog"
        :save-row="saveRow"
      >
        <el-table
          v-loading="dataLoading"
          :data="dataList"
          :empty-text="emptyText"
          row-key="id"
        >
          <el-table-column v-if="showIdColumn" prop="id" label="ID" width="70" />
          <el-table-column
            v-for="col in dataColumns"
            :key="col.prop"
            :prop="col.prop"
            :label="col.label"
            min-width="140"
          >
            <template #default="{ row }">
              {{ formatCell(row[col.prop]) }}
            </template>
          </el-table-column>
          <el-table-column v-if="showCreateTimeColumn" prop="create_time" label="创建时间" width="170" />
          <el-table-column v-if="showSortColumn" prop="sort" label="排序" width="90" />
          <el-table-column v-if="showStatusColumn" prop="status" label="状态" width="90">
            <template #default="{ row }">
              <el-tag :type="Number(row.status) === 1 ? 'success' : 'info'" size="mini">
                {{ Number(row.status) === 1 ? '启用' : '禁用' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column v-if="showActions" label="操作" :width="actionWidth">
            <template #default="{ row }">
              <a v-if="allowEdit" href="javascript:;" class="table-action" @click="openDataDialog(row)">编辑</a>
              <a v-if="allowDelete" href="javascript:;" class="table-action danger" @click="deleteData(row)">删除</a>
            </template>
          </el-table-column>
        </el-table>
      </slot>

      <slot
        name="after-table"
        :config="currentConfig"
        :list="dataList"
        :loading="dataLoading"
        :reload="loadData"
      />
    </el-card>

    <el-dialog
      v-model="dataVisible"
      :title="dataForm.id ? '编辑数据' : '添加数据'"
      width="760px"
      :close-on-click-modal="false"
    >
      <el-form ref="dataFormRef" class="data-form" :model="formData" :rules="formRules" label-width="110px">
        <el-form-item
          v-for="field in formFields"
          :key="fieldKey(field)"
          :label="field.props.label || field.label"
          :prop="field.props.field"
        >
          <el-input v-if="field.type === 'input'" v-model="formData[field.props.field]" :placeholder="field.props.placeholder" />
          <el-input
            v-else-if="field.type === 'textarea'"
            v-model="formData[field.props.field]"
            type="textarea"
            :rows="field.props.rows || 3"
          />
          <el-input-number
            v-else-if="field.type === 'number'"
            v-model="formData[field.props.field]"
            :min="field.props.min"
            :max="field.props.max"
            :step="field.props.step"
            controls-position="right"
          />
          <el-radio-group v-else-if="field.type === 'radio'" v-model="formData[field.props.field]">
            <el-radio v-for="opt in field.props.options || []" :key="opt.value" :label="opt.value">{{ opt.label }}</el-radio>
          </el-radio-group>
          <el-checkbox-group v-else-if="field.type === 'checkbox'" v-model="formData[field.props.field]">
            <el-checkbox v-for="opt in field.props.options || []" :key="opt.value" :label="opt.value">{{ opt.label }}</el-checkbox>
          </el-checkbox-group>
          <Upload
            v-else-if="field.type === 'upload'"
            :key="uploadFieldKey(field)"
            v-model="formData[field.props.field]"
            :limit="getUploadLimit(field)"
          />
          <el-input v-else v-model="formData[field.props.field]" />
        </el-form-item>
        <div v-if="showFixedFields" class="fixed-fields">
          <el-form-item label="排序">
            <el-input-number v-model="dataForm.sort" :min="0" :step="1" controls-position="right" />
          </el-form-item>
          <el-form-item label="状态">
            <el-switch v-model="dataForm.status" :active-value="1" :inactive-value="0" active-text="启用" inactive-text="禁用" />
          </el-form-item>
        </div>
      </el-form>
      <template #footer>
        <div>
          <el-button @click="dataVisible = false">取消</el-button>
          <el-button type="primary" :loading="submitLoading" @click="submitData">保存</el-button>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, onBeforeMount, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  getCombinationDataConfigByKey,
  getCombinationDataSchema,
  getCombinationDataList,
  saveCombinationData,
  updateCombinationData,
  deleteCombinationData
} from '@/api/combinationData'
import Upload from '@/components/Upload'

defineOptions({
  name: 'CombinationDataListPanel'
})

const props = defineProps({
  configId: { type: Number, default: 0 },
  configKey: { type: String, default: '' },
  categoryId: { type: Number, default: 0 },
  title: { type: String, default: '' },
  showHeader: { type: Boolean, default: true },
  showBack: { type: Boolean, default: true },
  backRoute: { type: [String, Object], default: () => ({ name: 'CombinationData' }) },
  allowCreate: { type: Boolean, default: true },
  allowEdit: { type: Boolean, default: true },
  allowDelete: { type: Boolean, default: true },
  showActions: { type: Boolean, default: true },
  showFixedFields: { type: Boolean, default: true },
  showIdColumn: { type: Boolean, default: true },
  showCreateTimeColumn: { type: Boolean, default: true },
  showSortColumn: { type: Boolean, default: true },
  showStatusColumn: { type: Boolean, default: true },
  actionWidth: { type: [String, Number], default: 130 },
  pageSize: { type: Number, default: 50 },
  emptyText: { type: String, default: '暂无数据' },
  autoLoad: { type: Boolean, default: true }
})

const emit = defineEmits(['loaded'])
const router = useRouter()

const componentDefaults = {
  input: { field: '', label: '文本框', placeholder: '请输入', maxlength: 200, required: false, disabled: false, defaultValue: '', description: '' },
  textarea: { field: '', label: '多行文本', placeholder: '请输入', rows: 3, required: false, disabled: false, defaultValue: '', description: '' },
  number: { field: '', label: '数字', min: 0, max: 99999, step: 1, required: false, disabled: false, defaultValue: null, description: '' },
  radio: { field: '', label: '单选', options: [], required: false, disabled: false, defaultValue: '', description: '' },
  checkbox: { field: '', label: '多选', options: [], required: false, disabled: false, defaultValue: [], description: '' },
  upload: { field: '', label: '上传', buttonText: '点击上传', accept: 'image/*', limit: 1, required: false, disabled: false, description: '' }
}

const dataLoading = ref(false)
const submitLoading = ref(false)
const resolvedConfigId = ref(0)
const dataList = ref([])
const dataColumns = ref([])
const formFields = ref([])
const currentConfig = ref(null)
const dataVisible = ref(false)
const dataFormRef = ref(null)
const dataForm = ref({ id: 0, sort: 0, status: 1 })
const formData = ref({})
const formRules = ref({})

const currentConfigId = computed(() => Number(props.configId || resolvedConfigId.value || 0))
const currentCategoryId = computed(() => Number(props.categoryId || (currentConfig.value ? currentConfig.value.category_id : 0) || 0))
const resolvedTitle = computed(() => {
  if (props.title) return props.title
  return currentConfig.value ? `${currentConfig.value.config_name} - 组合数据列表` : '组合数据列表'
})

watch(() => props.configId, () => {
  void bootstrap().catch(() => {})
})

watch(() => props.configKey, () => {
  void bootstrap().catch(() => {})
})

onBeforeMount(() => {
  if (props.autoLoad) {
    void bootstrap().catch(() => {})
  }
})

async function bootstrap() {
  try {
    if (props.configKey) {
      await loadConfigByKey()
      return
    }
    if (!currentConfigId.value) {
      ElMessage.warning('请选择组合数据配置')
      return
    }
    await loadSchema()
    await loadData()
  } catch (error) {
    ElMessage.error(error?.message || '加载失败')
  }
}

function goBack() {
  if (!props.backRoute) {
    router.back()
    return
  }
  router.push(props.backRoute).catch(() => {})
}

async function loadConfigByKey() {
  const key = props.configKey
  if (!key) return
  dataLoading.value = true
  try {
    const res = await getCombinationDataConfigByKey(key)
    const data = res.data || {}
    resolvedConfigId.value = Number(data.id || 0)
    if (!resolvedConfigId.value) {
      ElMessage.warning('组合数据配置不存在')
      return
    }
    await loadSchema()
    await loadData()
  } finally {
    dataLoading.value = false
  }
}

async function loadSchema() {
  if (!currentConfigId.value) return
  const res = await getCombinationDataSchema(currentConfigId.value)
  const data = res.data || {}
  currentConfig.value = data
  formFields.value = normalizeFields(data.content || [])
  dataColumns.value = data.columns || []
  buildRules()
}

async function loadData() {
  if (!currentConfigId.value) return
  dataLoading.value = true
  try {
    const res = await getCombinationDataList({ config_id: currentConfigId.value, page: 1, limit: props.pageSize })
    const data = res.data || {}
    dataList.value = data.list || []
    dataColumns.value = data.columns || dataColumns.value
    if (data.config) {
      currentConfig.value = data.config
      if (!formFields.value.length) {
        formFields.value = normalizeFields(data.config.content || [])
        buildRules()
      }
    }
    emit('loaded', {
      config: currentConfig.value,
      list: dataList.value,
      columns: dataColumns.value
    })
  } finally {
    dataLoading.value = false
  }
}

function openDataDialog(row) {
  if (row && !props.allowEdit) return
  if (!row && !props.allowCreate) return
  if (!formFields.value.length && currentConfig.value) {
    formFields.value = normalizeFields(currentConfig.value.content || [])
    buildRules()
  }
  dataForm.value = row
    ? { id: row.id, sort: Number(row.sort || 0), status: row.status === undefined || row.status === null ? 1 : Number(row.status) }
    : { id: 0, sort: 0, status: 1 }
  formData.value = row && row.form_data ? normalizeFormData(row.form_data) : initFormData()
  dataVisible.value = true
}

function initFormData() {
  const data = {}
  formFields.value.forEach(field => {
    const key = field.props.field
    if (field.type === 'checkbox') data[key] = []
    else if (field.type === 'upload') data[key] = normalizeUploadValue(field, field.props.defaultValue)
    else if (field.type === 'number') data[key] = field.props.defaultValue || 0
    else data[key] = field.props.defaultValue || ''
  })
  return data
}

function normalizeFormData(source) {
  let data = {}
  if (typeof source === 'string') {
    try {
      data = JSON.parse(source) || {}
    } catch (e) {
      data = {}
    }
  } else {
    data = { ...(source || {}) }
  }
  formFields.value.forEach(field => {
    const key = field.props.field
    if (field.type === 'upload') {
      data[key] = normalizeUploadValue(field, data[key])
    } else if (field.type === 'checkbox' && !Array.isArray(data[key])) {
      data[key] = data[key] ? [data[key]] : []
    }
  })
  return data
}

function buildRules() {
  const rules = {}
  formFields.value.forEach(field => {
    if (field.props.required) {
      rules[field.props.field] = [{ required: true, message: `请填写${field.props.label || field.label}`, trigger: ['blur', 'change'] }]
    }
  })
  formRules.value = rules
}

async function submitData() {
  if (!dataFormRef.value) return
  try {
    await dataFormRef.value.validate()
  } catch {
    return
  }
  submitLoading.value = true
  try {
    await saveRow(
      { id: dataForm.value.id },
      formData.value,
      { sort: dataForm.value.sort, status: dataForm.value.status }
    )
    dataVisible.value = false
  } catch (error) {
    ElMessage.error(error?.message || '保存失败')
  } finally {
    submitLoading.value = false
  }
}

async function saveRow(row, payloadFormData, options = {}) {
  const payload = {
    category_id: Number(options.category_id || currentCategoryId.value || 0),
    config_id: currentConfigId.value,
    sort: Number(options.sort || 0),
    status: options.status === undefined || options.status === null ? 1 : Number(options.status),
    form_data: payloadFormData
  }
  const id = Number(row && row.id ? row.id : 0)
  const request = id ? updateCombinationData(id, payload) : saveCombinationData(payload)
  const res = await request
  if (options.message !== false) {
    ElMessage.success(res.message || '保存成功')
  }
  if (options.reload !== false) {
    void loadData().catch(() => {})
  }
  return res
}

async function deleteData(row) {
  try {
    await ElMessageBox.confirm('确定删除该数据吗？', '提示')
  } catch {
    return
  }
  try {
    await deleteCombinationData(row.id)
    ElMessage.success('删除成功')
    await loadData()
  } catch (error) {
    ElMessage.error(error?.message || '删除失败')
  }
}

function formatCell(value) {
  if (Array.isArray(value)) return value.map(item => formatCell(item)).filter(item => item !== '').join('，')
  if (value && typeof value === 'object') return normalizeUploadItem(value) || JSON.stringify(value)
  return value
}

function fieldKey(field) {
  return field._id || `${field.type}_${field.props.field}`
}

function uploadFieldKey(field) {
  return `${fieldKey(field)}_${dataForm.value.id || 0}_${dataVisible.value ? 1 : 0}`
}

function getUploadLimit(field) {
  const limit = Number(field.props.limit || 1)
  return limit > 0 ? limit : 1
}

function normalizeUploadValue(field, value) {
  const list = normalizeUploadList(value)
  const limit = getUploadLimit(field)
  return limit > 1 ? list.slice(0, limit) : (list[0] || '')
}

function normalizeUploadList(value) {
  if (Array.isArray(value)) return value.map(item => normalizeUploadItem(item)).filter(Boolean)
  const item = normalizeUploadItem(value)
  return item ? [item] : []
}

function normalizeUploadItem(item) {
  if (!item) return ''
  if (typeof item === 'string') return item
  if (typeof item === 'object') return item.url || item.path || item.filePath || item.src || ''
  return ''
}

function normalizeFields(fields) {
  return (Array.isArray(fields) ? fields : []).map(field => {
    const props = {
      ...(componentDefaults[field.type] || {}),
      ...(field.defaultProps || {}),
      ...(field.props || {})
    }
    return {
      ...field,
      _id: field._id || `${field.type}_${props.field || Math.random().toString(36).slice(2)}`,
      label: field.label || props.label || '',
      props
    }
  }).filter(field => field.props.field)
}

defineExpose({
  loadData,
  saveRow
})
</script>

<style scoped lang="scss">
.combination-data-list-panel {
  .panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .title-wrap,
  .header-actions {
    display: flex;
    align-items: center;
    gap: 12px;
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

  .fixed-fields {
    display: block;
  }

  .data-form :deep(.el-input),
  .data-form :deep(.el-input-number),
  .data-form :deep(.el-select),
  .data-form :deep(.el-textarea) {
    width: 100%;
  }
}
</style>
