<template>
  <div class="combination-data-list-page">
    <el-card shadow="never">
      <template #header>
        <div class="panel-header">
          <div class="title-wrap">
            <el-button type="text" :icon="ArrowLeft" @click="goBack">返回</el-button>
            <span>{{ pageTitle }}</span>
          </div>
          <el-button type="primary" size="mini" :icon="Plus" :disabled="!currentConfig" @click="openDataDialog()">添加数据</el-button>
        </div>
      </template>

      <el-table v-loading="dataLoading" :data="dataList" >
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column v-for="col in dataColumns" :key="col.prop" :prop="col.prop" :label="col.label" min-width="140">
          <template #default="{ row }">
            {{ formatCell(row[col.prop]) }}
          </template>
        </el-table-column>
        <el-table-column prop="create_time" label="创建时间" width="170" />
        <el-table-column prop="sort" label="排序" width="90" />
        <el-table-column prop="status" label="状态" width="90">
          <template #default="{ row }">
            <el-tag :type="Number(row.status) === 1 ? 'success' : 'info'" size="mini">{{ Number(row.status) === 1 ? '启用' : '禁用' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="130">
          <template #default="{ row }">
            <a href="javascript:;" class="table-action" @click="openDataDialog(row)">编辑</a>
            <a href="javascript:;" class="table-action danger" @click="deleteData(row)">删除</a>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="dataVisible" :title="dataForm.id ? '编辑数据' : '添加数据'" width="760px">
      <el-form ref="dataFormRef" class="data-form" :model="formData" :rules="formRules" label-width="110px">
        <el-form-item v-for="field in formFields" :key="fieldKey(field)" :label="field.props.label || field.label" :prop="field.props.field">
          <el-input v-if="field.type === 'input'" v-model="formData[field.props.field]" :placeholder="field.props.placeholder" />
          <el-input v-else-if="field.type === 'textarea'" v-model="formData[field.props.field]" type="textarea" :rows="field.props.rows || 3" />
          <el-input-number v-else-if="field.type === 'number'" v-model="formData[field.props.field]" :min="field.props.min" :max="field.props.max" :step="field.props.step" controls-position="right" />
          <el-radio-group v-else-if="field.type === 'radio'" v-model="formData[field.props.field]">
            <el-radio v-for="opt in field.props.options || []" :key="opt.value" :label="opt.value">{{ opt.label }}</el-radio>
          </el-radio-group>
          <el-checkbox-group v-else-if="field.type === 'checkbox'" v-model="formData[field.props.field]">
            <el-checkbox v-for="opt in field.props.options || []" :key="opt.value" :label="opt.value">{{ opt.label }}</el-checkbox>
          </el-checkbox-group>
          <div v-else-if="field.type === 'upload'" class="upload-list">
            <div v-for="(file, fileIndex) in formData[field.props.field] || []" :key="fileIndex" class="upload-item">
              <span>{{ file.fileName || file.name || file.url }}</span>
              <LegacyIcon name="el-icon-delete" @click="removeUploadFile(field, fileIndex)" />
            </div>
            <el-upload
              v-if="!field.props.limit || (formData[field.props.field] || []).length < field.props.limit"
              :action="uploadAction"
              :headers="uploadHeaders"
              :show-file-list="false"
              :accept="field.props.accept"
              :on-success="(response, file) => handleUploadSuccess(response, file, field)"
            >
              <el-button :icon="UploadFilled">{{ field.props.buttonText || '点击上传' }}</el-button>
            </el-upload>
          </div>
          <el-input v-else v-model="formData[field.props.field]" />
        </el-form-item>
        <div class="fixed-fields">
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
          <el-button type="primary" @click="submitData">保存</el-button>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import {
  getCombinationDataSchema,
  getCombinationDataList,
  saveCombinationData,
  updateCombinationData,
  deleteCombinationData
} from '@/api/combinationData'
import { ArrowLeft, Plus, UploadFilled } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import setting from '@/settings'
import { getToken } from '@/utils/auth'

defineOptions({
  name: 'CombinationDataList'
})

const route = useRoute()
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
const dataList = ref([])
const dataColumns = ref([])
const formFields = ref([])
const currentConfig = ref(null)
const dataVisible = ref(false)
const dataForm = ref({ id: 0 })
const formData = ref({})
const formRules = ref({})
const dataFormRef = ref(null)
const uploadAction = setting.baseUri + '/setting/file/upload'
const uploadHeaders = {
  'X-Requested-With': 'XMLHttpRequest',
  'Authorization': 'Bearer ' + getToken()
}

const configId = computed(() => {
  return Number(route.query.config_id || 0)
})
const categoryId = computed(() => {
  return Number(route.query.category_id || 0)
})
const pageTitle = computed(() => {
  return currentConfig.value ? `${currentConfig.value.config_name} - 组合数据列表` : '组合数据列表'
})

onMounted(() => {
  if (!configId.value) {
    ElMessage.warning('请选择组合数据配置')
    goBack()
    return
  }
  loadSchema()
  loadData()
})

function goBack() {
  router.push({ name: 'CombinationData' })
}

function loadSchema() {
  getCombinationDataSchema(configId.value).then(res => {
    const data = res.data || {}
    currentConfig.value = data
    formFields.value = normalizeFields(data.content || [])
    dataColumns.value = data.columns || []
    buildRules()
  })
}

function loadData() {
  dataLoading.value = true
  getCombinationDataList({ config_id: configId.value, page: 1, limit: 50 }).then(res => {
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
  }).finally(() => {
    dataLoading.value = false
  })
}

function openDataDialog(row) {
  if (!formFields.value.length && currentConfig.value) {
    formFields.value = normalizeFields(currentConfig.value.content || [])
    buildRules()
  }
  dataForm.value = row ? { id: row.id, sort: Number(row.sort || 0), status: row.status === undefined || row.status === null ? 1 : Number(row.status) } : { id: 0, sort: 0, status: 1 }
  formData.value = row && row.form_data ? { ...row.form_data } : initFormData()
  dataVisible.value = true
}

function initFormData() {
  const data = {}
  formFields.value.forEach(field => {
    const key = field.props.field
    if (field.type === 'checkbox') data[key] = []
    else if (field.type === 'upload') data[key] = []
    else if (field.type === 'number') data[key] = field.props.defaultValue || 0
    else data[key] = field.props.defaultValue || ''
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

function submitData() {
  dataFormRef.value.validate(valid => {
    if (!valid) return
    const payload = {
      category_id: categoryId.value || currentConfig.value.category_id,
      config_id: configId.value,
      sort: Number(dataForm.value.sort || 0),
      status: Number(dataForm.value.status),
      form_data: formData.value
    }
    const request = dataForm.value.id ? updateCombinationData(dataForm.value.id, payload) : saveCombinationData(payload)
    request.then(() => {
      dataVisible.value = false
      loadData()
    })
  })
}

function deleteData(row) {
  ElMessageBox.confirm('确定删除该数据吗？', '提示').then(() => deleteCombinationData(row.id).then(() => loadData())).catch(() => {})
}

function handleUploadSuccess(response, file, field) {
  if (response.status !== 200) {
    ElMessage.error(response.message || '上传失败')
    return
  }
  const key = field.props.field
  const list = [...(formData.value[key] || [])]
  list.push({
    fileName: file.name,
    url: response.data.filePath
  })
  formData.value[key] = list
}

function removeUploadFile(field, index) {
  const key = field.props.field
  const list = [...(formData.value[key] || [])]
  list.splice(index, 1)
  formData.value[key] = list
}

function formatCell(value) {
  if (Array.isArray(value)) return value.join('，')
  if (value && typeof value === 'object') return JSON.stringify(value)
  return value
}

function fieldKey(field) {
  return field._id || `${field.type}_${field.props.field}`
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
</script>

<style scoped lang="scss">
.panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.title-wrap {
  display: flex;
  align-items: center;
  gap: 12px;
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
.fixed-fields {
  display: block;
}
.data-form :deep(.el-input),
.data-form :deep(.el-input-number),
.data-form :deep(.el-select),
.data-form :deep(.el-textarea) {
  width: 100%;
}
.upload-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  align-items: flex-start;
}
.upload-item {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: #606266;
  i {
    cursor: pointer;
    color: #f56c6c;
  }
}
</style>
