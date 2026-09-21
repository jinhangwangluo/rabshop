<template>
  <div class="payment-config">
    <el-card shadow="never" class="overview-card">
      <div class="overview-card__content">
        <div>
          <div class="overview-card__title">支付配置</div>
          <div class="overview-card__desc">维护支付方式列表展示信息，并在编辑表单下方补齐对应支付参数。</div>
        </div>
        <el-button
          :loading="loading"
          :icon="Refresh"
          @click="fetchConfig"
        >刷新配置</el-button>
      </div>
    </el-card>

    <el-card shadow="never" class="table-card">
      <div class="table-header">
        <div>
          <span class="table-header__title">支付方式列表</span>
          <span class="table-header__desc">图片、文字、支付类型、支付标题、支付副标题按排序展示</span>
        </div>
        <div class="table-header__actions">
          <el-select
            v-model="addPayType"
            :disabled="!availablePayTypeOptions.length"

            placeholder="请选择支付类型"
            class="add-pay-type"
          >
            <el-option
              v-for="item in availablePayTypeOptions"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
          </el-select>
          <el-button
            :disabled="!availablePayTypeOptions.length"
            type="primary"

            :icon="Plus"
            @click="handleAdd"
          >新增支付方式</el-button>
        </div>
      </div>

      <el-table
        v-loading="loading"
        :data="methods"
        row-key="pay_type"
        class="payment-table"
      >
        <el-table-column label="图片" min-width="90" align="center">
          <template #default="{ row }">
            <div class="pay-image">
              <img v-if="row.image" :src="row.image" alt="支付图片">
              <el-icon v-else><Picture /></el-icon>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="text" label="文字" min-width="100" />
        <el-table-column label="支付类型" min-width="120">
          <template #default="{ row }">
            <el-tag type="info">{{ getPayTypeLabel(row.pay_type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="支付标题/副标题" min-width="200">
          <template #default="{ row }">
            <div class="pay-title">{{ row.title }}</div>
            <div class="pay-subtitle">{{ row.subtitle || '未填写副标题' }}</div>
          </template>
        </el-table-column>
        <el-table-column label="展示终端" min-width="150">
          <template #default="{ row }">
            <el-tag
              v-for="terminal in row.terminals"
              :key="terminal"

              class="terminal-tag"
            >{{ getTerminalLabel(terminal) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="状态" align="center">
          <template #default="{ row }">
            <el-switch
              v-model="row.status"
              :disabled="isSavingRow(row.pay_type)"
              :active-value="1"
              :inactive-value="0"
              @change="handleSaveRow(row)"
            />
          </template>
        </el-table-column>
        <el-table-column prop="sort" label="排序" align="center" />
        <el-table-column label="操作" align="center" fixed="right">
          <template #default="{ row }">
            <a :icon="Edit" @click="handleEdit(row)">编辑</a>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog
      v-model="dialogVisible"
      :title="dialogTitle"
      width="680px"
      custom-class="payment-config-dialog"
      append-to-body
      @closed="handleDialogClosed"
    >
      <el-form ref="formRef" :model="form" :rules="rules" label-width="110px">
        <el-form-item label="支付类型" prop="pay_type">
          <el-select v-model="form.pay_type" :disabled="editIndex > -1" class="form-control" @change="handlePayTypeChange">
            <el-option
              v-for="item in formPayTypeOptions"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="图片" prop="image">
          <Upload v-model="form.image" :limit="1" />
        </el-form-item>
        <el-form-item label="文字" prop="text">
          <el-input v-model="form.text" maxlength="20" show-word-limit placeholder="请输入列表展示文字" />
        </el-form-item>
        <el-form-item label="支付标题" prop="title">
          <el-input v-model="form.title" maxlength="50" show-word-limit placeholder="请输入支付标题" />
        </el-form-item>
        <el-form-item label="支付副标题" prop="subtitle">
          <el-input
            v-model="form.subtitle"
            type="textarea"
            :rows="3"
            maxlength="120"
            show-word-limit
            placeholder="请输入支付副标题"
          />
        </el-form-item>
        <el-form-item label="展示终端" prop="terminals">
          <el-checkbox-group v-model="form.terminals">
            <el-checkbox
              v-for="item in terminalOptions"
              :key="item.value"
              :label="item.value"
            >{{ item.label }}</el-checkbox>
          </el-checkbox-group>
        </el-form-item>
        <el-form-item label="启用状态" prop="status">
          <el-switch v-model="form.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="排序" prop="sort">
          <el-input-number v-model="form.sort" :min="0" :step="1" controls-position="right" />
        </el-form-item>

        <el-divider content-position="left">{{ getPayTypeLabel(form.pay_type) }}参数配置</el-divider>
        <div v-if="form.setting_fields.length" class="setting-form">
          <el-form-item
            v-for="field in visibleSettingFields"
            :key="field.key"
            :label="field.label"
          >
            <el-select
              v-if="field.type === 'select'"
              v-model="form.settings[field.key]"
              :placeholder="field.placeholder"
              class="form-control"
            >
              <el-option
                v-for="option in field.options || []"
                :key="option.value"
                :label="option.label"
                :value="option.value"
              />
            </el-select>
            <el-switch
              v-else-if="field.type === 'switch'"
              v-model="form.settings[field.key]"
              :active-value="1"
              :inactive-value="0"
            />
            <el-input
              v-else-if="field.type === 'textarea'"
              v-model="form.settings[field.key]"
              type="textarea"
              :rows="4"
              :placeholder="field.placeholder"
            />
            <div v-else-if="field.type === 'file'" class="setting-file-upload">
              <el-upload
                action="#"
                :http-request="options => handleSettingFileUpload(options, field)"
                :show-file-list="false"
                :accept="field.accept || '.pem'"
                :before-upload="file => beforeSettingFileUpload(file, field)"
                :disabled="saving || !!uploadingFields[field.key]"
              >
                <el-button
                  :loading="!!uploadingFields[field.key]"

                  :icon="UploadFilled"
                >上传文件</el-button>
              </el-upload>
              <div v-if="form.settings[field.key]" class="setting-file-upload__file">
                <el-icon><Document /></el-icon>
                <span :title="form.settings[field.key]">{{ getSettingFileName(form.settings[field.key]) }}</span>
                <el-button
                  text

                  :icon="Close"
                  @click="clearSettingFile(field)"
                />
              </div>
              <div v-else class="setting-file-upload__empty">{{ field.placeholder }}</div>
            </div>
            <el-input
              v-else
              v-model="form.settings[field.key]"
              :show-password="field.type === 'password'"
              :placeholder="field.placeholder"
            />
            <div v-if="field.tip" class="setting-form__tip">{{ field.tip }}</div>
          </el-form-item>
        </div>
        <el-empty v-else description="当前支付方式暂无额外参数" :image-size="80" />
      </el-form>
      <template #footer>
        <div>
          <el-button @click="dialogVisible = false">取消</el-button>
          <el-button type="primary" :loading="saving" @click="handleSubmitEdit">确定</el-button>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, onBeforeMount, reactive, ref } from 'vue'
import { Close, Document, Edit, Picture, Plus, Refresh, UploadFilled } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { fileUploadApi } from '@/api/file'
import { getPaymentConfig, savePaymentConfig } from '@/api/paymentConfig'
import Upload from '@/components/Upload/index'

defineOptions({ name: 'PaymentConfig' })

const loading = ref(false)
const saving = ref(false)
const dialogVisible = ref(false)
const editIndex = ref(-1)
const addPayType = ref('')
const methods = ref([])
const payTypeOptions = ref([])
const terminalOptions = ref([])
const payTypeTemplates = ref({})
const formRef = ref(null)
const uploadingFields = reactive({})
const savingRows = reactive({})
const form = reactive(getDefaultForm())

const rules = {
  text: [{ required: true, message: '请输入文字', trigger: 'blur' }],
  title: [{ required: true, message: '请输入支付标题', trigger: 'blur' }],
  terminals: [{ type: 'array', required: true, message: '请选择展示终端', trigger: 'change' }],
  sort: [{ required: true, message: '请输入排序', trigger: 'blur' }]
}

const dialogTitle = computed(() => (editIndex.value > -1 ? `编辑${form.title}` : '新增支付方式'))

const availablePayTypeOptions = computed(() => {
  const exists = methods.value.map(item => item.pay_type)
  return payTypeOptions.value.filter(item => !exists.includes(item.value))
})

const formPayTypeOptions = computed(() => (editIndex.value > -1 ? payTypeOptions.value : availablePayTypeOptions.value))

const visibleSettingFields = computed(() => {
  return (form.setting_fields || []).filter(field => {
    if (!field.visible_version) {
      return true
    }

    return field.visible_version === form.settings.wechat_pay_version
  })
})

function getDefaultForm() {
  return {
    pay_type: '',
    image: '',
    text: '',
    title: '',
    subtitle: '',
    status: 1,
    terminals: [],
    sort: 0,
    setting_fields: [],
    settings: {}
  }
}

async function fetchConfig() {
  loading.value = true
  try {
    const res = await getPaymentConfig()
    const data = res.data || {}
    methods.value = data.methods || []
    payTypeOptions.value = data.pay_type_options || []
    payTypeTemplates.value = data.pay_type_templates || {}
    terminalOptions.value = data.terminal_options || []
    if (!availablePayTypeOptions.value.some(item => item.value === addPayType.value)) {
      addPayType.value = availablePayTypeOptions.value.length ? availablePayTypeOptions.value[0].value : ''
    }
  } catch (error) {
    ElMessage.error(error.message || '获取支付配置失败')
  } finally {
    loading.value = false
  }
}

function handleAdd() {
  if (!addPayType.value) return
  editIndex.value = -1
  Object.assign(form, buildFormByPayType(addPayType.value))
  dialogVisible.value = true
}

function buildFormByPayType(payType) {
  const option = payTypeOptions.value.find(item => item.value === payType) || {}
  const template = payTypeTemplates.value[payType] || {}
  const maxSort = methods.value.reduce((max, item) => Math.max(max, Number(item.sort || 0)), 0)
  const nextForm = getDefaultForm()
  nextForm.pay_type = payType
  nextForm.image = template.image || ''
  nextForm.text = template.text || option.label || ''
  nextForm.title = template.title || option.label || ''
  nextForm.subtitle = template.subtitle || ''
  nextForm.status = template.status === 0 ? 0 : 1
  nextForm.terminals = template.terminals ? [...template.terminals] : terminalOptions.value.map(item => item.value)
  nextForm.sort = template.sort || maxSort + 10
  nextForm.setting_fields = template.setting_fields || []
  nextForm.settings = normalizeSettingValues(
    nextForm.setting_fields,
    template.settings || buildDefaultSettings(nextForm.setting_fields)
  )
  return nextForm
}

function buildDefaultSettings(fields) {
  const settings = {}
  fields.forEach(field => {
    if (field.type === 'switch') {
      settings[field.key] = 0
    } else if (field.type === 'select') {
      const options = field.options || []
      settings[field.key] = options.length ? options[0].value : ''
    } else {
      settings[field.key] = ''
    }
  })
  return settings
}

function handlePayTypeChange(payType) {
  const nextForm = buildFormByPayType(payType)
  Object.assign(form, nextForm)
}

function handleEdit(row) {
  editIndex.value = methods.value.findIndex(item => item.pay_type === row.pay_type)
  Object.assign(form, {
    pay_type: row.pay_type,
    image: row.image,
    text: row.text,
    title: row.title,
    subtitle: row.subtitle,
    status: row.status,
    terminals: Array.isArray(row.terminals) ? [...row.terminals] : [],
    sort: row.sort,
    setting_fields: row.setting_fields || [],
    settings: normalizeSettingValues(row.setting_fields || [], row.settings || {})
  })
  dialogVisible.value = true
}

function handleSubmitEdit() {
  if (!formRef.value) return
  formRef.value.validate(valid => {
    if (!valid) return
    savePaymentMethod({ ...form }, true)
  })
}

function isSavingRow(payType) {
  return !!savingRows[payType]
}

function buildSavePayload(item) {
  return {
    pay_type: item.pay_type,
    image: item.image,
    text: item.text,
    title: item.title,
    subtitle: item.subtitle,
    status: item.status,
    terminals: item.terminals,
    sort: item.sort,
    settings: buildVisibleSettings(item)
  }
}

function buildVisibleSettings(item) {
  const settings = {}
  const fields = item.setting_fields || []
  const currentSettings = item.settings || {}
  const version = currentSettings.wechat_pay_version
  fields.forEach(field => {
    if (field.visible_version && field.visible_version !== version) {
      return
    }

    settings[field.key] = field.type === 'file'
      ? normalizeUploadValue(currentSettings[field.key])
      : currentSettings[field.key]
  })
  return settings
}

function normalizeSettingValues(fields, settings) {
  const result = { ...(settings || {}) }
  fields.forEach(field => {
    if (field.type === 'file') {
      result[field.key] = normalizeUploadValue(result[field.key])
    }
  })
  return result
}

function normalizeUploadValue(value) {
  if (Array.isArray(value)) {
    return value.length ? normalizeUploadValue(value[0]) : ''
  }
  if (value && typeof value === 'object') {
    const filePath = value.absolutePath || value.server_path || value.filePath || value.path || value.url || value.value || ''
    return String(filePath).trim()
  }
  return value === undefined || value === null ? '' : String(value).trim()
}

function getUploadedFilePath(data) {
  return normalizeUploadValue(data)
}

function getSettingFileName(value) {
  const filePath = normalizeUploadValue(value)
  if (!filePath) {
    return ''
  }
  const path = filePath.split('?')[0]
  const segments = path.split(/[\\/]/)
  return segments[segments.length - 1] || filePath
}

function beforeSettingFileUpload(file, field) {
  const accept = String(field.accept || '').split(',').map(item => item.trim().toLowerCase()).filter(Boolean)
  const fileName = String(file.name || '').toLowerCase()
  const fileType = String(file.type || '').toLowerCase()
  if (accept.length && !accept.some(item => item.charAt(0) === '.' ? fileName.endsWith(item) : fileType === item)) {
    ElMessage.error('请上传指定格式的文件')
    return false
  }

  const maxSize = Number(field.max_size || 0)
  if (maxSize > 0 && file.size / 1024 / 1024 > maxSize) {
    ElMessage.error(`上传文件大小不能超过 ${maxSize}MB`)
    return false
  }

  return true
}

async function handleSettingFileUpload(options, field) {
  uploadingFields[field.key] = true
  const formData = new FormData()
  formData.append('file', options.file)
  if (field.storage_type) {
    formData.append('storage_type', field.storage_type)
  }

  try {
    const res = await fileUploadApi(formData)
    const filePath = getUploadedFilePath(res.data)
    if (!filePath) {
      throw new Error('上传成功但未返回文件路径')
    }

    form.settings[field.key] = filePath
    if (options.onSuccess) {
      options.onSuccess(res)
    }
    ElMessage.success(res.message || '上传成功')
  } catch (error) {
    if (options.onError) {
      options.onError(error)
    }
    ElMessage.error(error.message || error.msg || '上传失败')
  } finally {
    delete uploadingFields[field.key]
  }
}

function clearSettingFile(field) {
  form.settings[field.key] = ''
}

async function handleSaveRow(row) {
  await savePaymentMethod({ ...row })
}

async function savePaymentMethod(method, closeDialog = false) {
  if (!method.pay_type) return
  saving.value = closeDialog
  savingRows[method.pay_type] = true
  try {
    const res = await savePaymentConfig({ method: buildSavePayload(method) })
    ElMessage.success(res.message || '保存成功')
    const data = res.data || {}
    methods.value = data.methods || methods.value
    if (!availablePayTypeOptions.value.some(item => item.value === addPayType.value)) {
      addPayType.value = availablePayTypeOptions.value.length ? availablePayTypeOptions.value[0].value : ''
    }
    if (closeDialog) {
      dialogVisible.value = false
    }
  } catch (error) {
    ElMessage.error(error.message || '保存支付配置失败')
    fetchConfig()
  } finally {
    saving.value = false
    delete savingRows[method.pay_type]
  }
}

function handleDialogClosed() {
  editIndex.value = -1
  Object.assign(form, getDefaultForm())
  if (formRef.value) formRef.value.clearValidate()
}

function getPayTypeLabel(value) {
  const item = payTypeOptions.value.find(option => option.value === value)
  return item ? item.label : value
}

function getTerminalLabel(value) {
  const item = terminalOptions.value.find(option => option.value === value)
  return item ? item.label : value
}

onBeforeMount(() => {
  fetchConfig()
})
</script>

<style lang="scss" scoped>
.payment-config {
  min-height: calc(100vh - 100px);
}

.overview-card,
.table-card {
  border-radius: 8px;
}

.overview-card {
  margin-bottom: 16px;

  &__content {
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  &__title {
    margin-bottom: 8px;
    color: var(--dashboard-text-primary);
    font-size: 16px;
    font-weight: 600;
  }

  &__desc {
    color: var(--dashboard-text-muted);
    font-size: 14px;
  }
}

.table-card {
  margin-bottom: 16px;

  :deep(.el-card__body) {
    padding: 0;
  }
}

.table-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
  border-bottom: 1px solid var(--dashboard-border);

  &__title {
    margin-right: 12px;
    color: var(--dashboard-text-primary);
    font-size: 16px;
    font-weight: 600;
  }

  &__desc {
    color: var(--dashboard-text-muted);
    font-size: 13px;
  }
  &__actions {
    display: flex;
    align-items: center;
  }
}

.add-pay-type {
  width: 180px;
  margin-right: 10px;
}

.payment-table {
  :deep(.el-table__body-wrapper) {
    padding: 0 24px 24px;
  }

  :deep(.el-table td) {
    padding: 14px 0;
  }
}

.pay-image {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 42px;
  height: 42px;
  margin: 0 auto;
  overflow: hidden;
  color: var(--dashboard-accent);
  font-size: 22px;
  background: var(--dashboard-accent-outline);
  border-radius: 10px;

  img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
}

.pay-title {
  margin-bottom: 6px;
  color: var(--dashboard-text-primary);
  font-weight: 500;
}

.pay-subtitle {
  color: var(--dashboard-text-muted);
  font-size: 13px;
  line-height: 1.5;
}

.terminal-tag {
  margin: 0 6px 6px 0;
}

.form-control {
  width: 100%;
}

.setting-form {
  padding-top: 4px;

  &__tip {
    margin-top: 6px;
    color: var(--dashboard-text-muted);
    font-size: 12px;
    line-height: 1.5;
  }
}

.setting-file-upload {
  display: flex;
  align-items: center;
  min-height: 32px;

  &__file {
    display: flex;
    align-items: center;
    min-width: 0;
    margin-left: 12px;
    color: var(--dashboard-text-secondary);
    font-size: 13px;

    .el-icon-document {
      margin-right: 6px;
      color: var(--dashboard-accent);
    }

    span {
      max-width: 360px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .el-button {
      margin-left: 6px;
      color: var(--dashboard-text-muted);
    }
  }

  &__empty {
    margin-left: 12px;
    color: var(--dashboard-text-muted);
    font-size: 13px;
  }
}

:deep(.payment-config-dialog) {
  color: var(--dashboard-text-primary);
  background: var(--dashboard-surface);

  .el-dialog__header {
    background: var(--dashboard-surface);
    border-bottom: 1px solid var(--dashboard-border);
  }

  .el-dialog__body,
  .el-dialog__footer {
    background: var(--dashboard-surface);
  }
}

:deep(.el-button) {
  border-radius: 4px;
}
</style>
