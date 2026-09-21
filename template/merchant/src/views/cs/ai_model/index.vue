<template>
  <div class="cs-ai-model-page">

    <div class="split-layout">
      <el-card shadow="never" class="left-pane">
        <div class="pane-header">
          <span class="pane-title">服务商分类</span>
          <div class="pane-action" @click="openProviderCreate">
            <LegacyIcon name="el-icon-plus" />
            <span>新增</span>
          </div>
        </div>

        <div v-loading="providerLoading" class="provider-list">
          <div
            :class="['provider-item', { active: Number(selectedProviderId) === 0 }]"
            @click="handleSelectProvider(0)"
          >
            <div class="provider-main">
              <span class="provider-icon">
                <LegacyIcon name="el-icon-folder" />
              </span>
              <div class="provider-content">
                <span class="name">全部服务商</span>
              </div>
            </div>
            <el-dropdown
              class="provider-more"
              trigger="click"
              @command="handleProviderRootMenuCommand"
            >
              <span class="provider-more-trigger" @click.stop>
                <LegacyIcon name="el-icon-more" />
              </span>
              <template #dropdown>
                <el-dropdown-menu>
                  <el-dropdown-item command="create">新增服务商</el-dropdown-item>
                  <el-dropdown-item command="refresh">刷新列表</el-dropdown-item>
                </el-dropdown-menu>
              </template>
            </el-dropdown>
          </div>
          <div
            v-for="item in providerList"
            :key="item.id"
            :class="['provider-item', { active: Number(selectedProviderId) === Number(item.id) }]"
            @click="handleSelectProvider(item.id)"
          >
            <div class="provider-main">
              <span class="provider-icon">
                <LegacyIcon name="el-icon-folder" />
              </span>
              <div class="provider-content">
                <span class="name">{{ item.provider_name || item.provider_code || `服务商#${item.id}` }}</span>
              </div>
            </div>
            <el-dropdown
              class="provider-more"
              trigger="click"
              @command="(command) => handleProviderMenuCommand(item, command)"
            >
              <span class="provider-more-trigger" @click.stop>
                <LegacyIcon name="el-icon-more" />
              </span>
              <template #dropdown>
                <el-dropdown-menu>
                  <el-dropdown-item command="edit">编辑服务商</el-dropdown-item>
                  <el-dropdown-item command="toggle">{{ Number(item.status) === 1 ? '禁用服务商' : '启用服务商' }}</el-dropdown-item>
                  <el-dropdown-item command="delete" divided>删除</el-dropdown-item>
                </el-dropdown-menu>
              </template>
            </el-dropdown>
          </div>
          <el-empty v-if="!providerLoading && providerList.length === 0" :image-size="56" description="暂无服务商" />
        </div>
      </el-card>

      <el-card shadow="never" class="right-pane">
        <el-form :inline="true" :model="modelQuery" class="query-form" @submit.prevent>
          <el-form-item label="类型">
            <el-select v-model="modelQuery.type" class="w140" clearable placeholder="全部" @change="handleModelTypeChange">
              <el-option
                v-for="item in modelTypeOptions"
                :key="item.value"
                :label="item.label"
                :value="item.value"
              />
            </el-select>
          </el-form-item>
          <el-form-item label="状态">
            <el-select v-model="modelQuery.status" class="w120" clearable placeholder="全部">
              <el-option label="启用" :value="1" />
              <el-option label="禁用" :value="0" />
            </el-select>
          </el-form-item>
          <el-form-item label="关键词">
            <el-input v-model="modelQuery.keyword" clearable class="w240" placeholder="模型名称/ID" @keyup.enter="fetchModelList" />
          </el-form-item>
          <el-form-item>
            <el-button type="primary" :icon="Search" @click="fetchModelList">查询</el-button>
            <el-button :icon="Refresh" @click="resetModelQuery">重置</el-button>
          </el-form-item>
        </el-form>

        <div class="action-row">
          <el-button type="primary" :icon="Plus" @click="openModelCreate">新增模型</el-button>
        </div>

        <el-table v-loading="modelLoading" :data="modelList" class="mt16">
          <el-table-column prop="id" label="ID" width="80" />
          <el-table-column prop="type" label="类型" width="100">
            <template #default="{ row }">
              <el-tag  :type="typeTagType(row.type)">{{ typeLabel(row.type) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="provider_name" label="服务商" min-width="140" show-overflow-tooltip />
          <el-table-column prop="model_name" label="模型名称" min-width="160" show-overflow-tooltip />
          <el-table-column prop="model_id" label="模型ID" min-width="180" show-overflow-tooltip />
          <el-table-column prop="timeout" label="超时(秒)" width="100" />
          <el-table-column label="默认" width="90">
            <template #default="{ row }">
              <el-tag v-if="Number(row.is_default) === 1"  type="success">默认</el-tag>
              <el-button v-else type="text" @click="handleSetDefaultModel(row)">设为默认</el-button>
            </template>
          </el-table-column>
          <el-table-column label="状态" width="90">
            <template #default="{ row }">
              <el-switch :model-value="Number(row.status) === 1" @change="(val) => handleModelStatusChange(row, val)" />
            </template>
          </el-table-column>
          <el-table-column prop="sort" label="排序" width="90" />
          <el-table-column prop="create_time" label="创建时间" width="170" />
          <el-table-column label="操作" width="180" fixed="right">
            <template #default="{ row }">
              <el-button type="text" @click="openModelEdit(row)">编辑</el-button>
              <el-button type="text" class="danger-text" @click="handleDeleteModel(row)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>

        <div class="pagination-wrap">
          <el-pagination
            v-model:current-page="modelQuery.page"
            v-model:page-size="modelQuery.limit"
            :page-sizes="[10, 20, 50]"
            layout="total, sizes, prev, pager, next"
            :total="modelTotal"
            @size-change="handleModelSizeChange"
            @current-change="handleModelCurrentChange"
          />
        </div>
      </el-card>
    </div>

    <el-dialog v-model="showProviderDialog" :title="providerForm.id ? '编辑服务商' : '新增服务商'" width="620px">
      <el-form :model="providerForm" label-width="110px">
        <el-form-item label="模型类型" required>
          <el-radio-group v-model="providerForm.type" :disabled="providerReadonlyType">
            <el-radio v-for="item in modelTypeOptions" :key="item.value" :label="item.value">{{ item.label }}</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="服务商编码" required>
          <el-input v-model="providerForm.provider_code" :disabled="providerReadonlyType" maxlength="64" />
        </el-form-item>
        <el-form-item label="服务商名称" required>
          <el-input v-model="providerForm.provider_name" maxlength="64" />
        </el-form-item>
        <el-form-item label="驱动" required>
          <el-select v-model="providerForm.driver" class="w240" :disabled="providerReadonlyType">
            <el-option label="openai_compatible" value="openai_compatible" />
            <el-option label="openai" value="openai" />
            <el-option label="aliyun" value="aliyun" />
          </el-select>
        </el-form-item>
        <el-form-item label="Base URL">
          <el-input v-model="providerForm.base_url" placeholder="https://api.example.com/v1" />
        </el-form-item>
        <el-form-item label="API Key">
          <el-input v-model.trim="providerForm.api_key" type="password" show-password placeholder="请输入 API Key" />
          <div class="form-tip">当前 AI 模型会读取所属服务商这里配置的 API Key。</div>
        </el-form-item>
        <el-form-item label="状态">
          <el-radio-group v-model="providerForm.status">
            <el-radio :label="1">启用</el-radio>
            <el-radio :label="0">禁用</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="providerForm.sort" :min="0" class="w240" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showProviderDialog = false">取消</el-button>
        <el-button type="primary" @click="submitProviderForm">确定</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="showModelDialog" :title="modelForm.id ? '编辑模型' : '新增模型'" width="840px">
      <el-form :model="modelForm" label-width="110px">
        <el-form-item label="模型类型" required>
          <el-radio-group v-model="modelForm.type" :disabled="Boolean(modelForm.id)" @change="syncModelProviderByType">
            <el-radio v-for="item in modelTypeOptions" :key="item.value" :label="item.value">{{ item.label }}</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="服务商" required>
          <el-select v-model="modelForm.provider_id" class="w280" filterable placeholder="请选择服务商">
            <el-option v-for="item in modelProviderOptions" :key="item.id" :label="item.provider_name" :value="Number(item.id)" />
          </el-select>
        </el-form-item>
        <el-form-item v-if="selectedModelProvider" label="服务商配置">
          <div class="provider-config-tip">
            <div class="provider-config-main">
              <div class="provider-config-title">
                <span class="provider-config-name">{{ selectedModelProvider.provider_name || selectedModelProvider.provider_code || '当前服务商' }}</span>
                <el-tag size="mini" :type="providerHasApiKey(selectedModelProvider) ? 'success' : 'warning'">
                  {{ providerHasApiKey(selectedModelProvider) ? 'API Key 已配置' : 'API Key 未配置' }}
                </el-tag>
              </div>
              <div class="provider-config-desc">
                <span>驱动：{{ selectedModelProvider.driver || '-' }}</span>
                <span>Base URL：{{ selectedModelProvider.base_url || '未设置' }}</span>
                <span>当前 AI 模型会读取所属服务商的 API Key</span>
              </div>
            </div>
            <el-button type="text" @click="openProviderEditById(modelForm.provider_id)">
              {{ providerHasApiKey(selectedModelProvider) ? '修改 API Key' : '去设置 API Key' }}
            </el-button>
          </div>
        </el-form-item>
        <el-form-item label="模型ID" required>
          <el-input v-model="modelForm.model_id" maxlength="120" />
        </el-form-item>
        <el-form-item label="模型名称">
          <el-input v-model="modelForm.model_name" maxlength="120" placeholder="为空时默认等于模型ID" />
        </el-form-item>
        <el-form-item label="模型参数">
          <div class="param-editor">
            <el-table :data="modelParamRows" border size="mini" class="param-table">
              <el-table-column label="参数名" min-width="180">
                <template #default="{ row }">
                  <el-input v-model.trim="row.key" maxlength="100" placeholder="例如 temperature" />
                </template>
              </el-table-column>
              <el-table-column label="参数值" min-width="260">
                <template #default="{ row }">
                  <el-input v-model.trim="row.value" placeholder="例如 0.7 / true / 文本" />
                </template>
              </el-table-column>
              <el-table-column label="操作" width="90">
                <template #default="{ row, $index }">
                  <el-button type="text" class="danger-text" @click="removeParamRow($index)">删除</el-button>
                </template>
              </el-table-column>
            </el-table>
            <div class="param-actions">
              <el-button type="primary" size="mini" :icon="Plus" class="param-add-btn" @click="addParamRow">添加一行</el-button>
            </div>
          </div>
        </el-form-item>
        <el-form-item label="超时(秒)">
          <el-input-number v-model="modelForm.timeout" :min="1" class="w240" />
        </el-form-item>
        <el-form-item label="状态">
          <el-radio-group v-model="modelForm.status">
            <el-radio :label="1">启用</el-radio>
            <el-radio :label="0">禁用</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="modelForm.sort" :min="0" class="w240" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showModelDialog = false">取消</el-button>
        <el-button type="primary" @click="submitModelForm">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Refresh, Search } from '@element-plus/icons-vue'
import {
  createCsAiModel,
  createCsAiProvider,
  deleteCsAiModel,
  deleteCsAiProvider,
  getCsAiModelList,
  getCsAiModelTypeOptions,
  getCsAiProviderList,
  setCsAiModelDefault,
  updateCsAiModel,
  updateCsAiModelStatus,
  updateCsAiProvider,
  updateCsAiProviderStatus
} from '@/api/cs'

defineOptions({
  name: 'MerchantCsAiModelConfigIndex'
})

const defaultProviderQuery = () => ({
  type: '',
  status: '',
  keyword: '',
  page: 1,
  limit: 20
})

const defaultModelQuery = () => ({
  type: '',
  provider_id: '',
  status: '',
  keyword: '',
  page: 1,
  limit: 20
})

const defaultProviderForm = () => ({
  id: 0,
  type: 'chat',
  provider_code: '',
  provider_name: '',
  driver: 'openai_compatible',
  base_url: '',
  api_key: '',
  status: 1,
  sort: 0,
  is_builtin: 0
})

const defaultModelForm = () => ({
  id: 0,
  provider_id: '',
  type: 'chat',
  model_id: '',
  model_name: '',
  timeout: 30,
  status: 1,
  sort: 0
})

const defaultParamRow = () => ({
  key: '',
  value: ''
})

const providerLoading = ref(false)
const modelLoading = ref(false)
const selectedProviderId = ref(0)
const providerQuery = reactive(defaultProviderQuery())
const modelQuery = reactive(defaultModelQuery())
const providerList = ref([])
const modelList = ref([])
const modelTypeOptions = ref([])
const providerTotal = ref(0)
const modelTotal = ref(0)
const showProviderDialog = ref(false)
const showModelDialog = ref(false)
const providerForm = ref(defaultProviderForm())
const modelForm = ref(defaultModelForm())
const modelParamRows = ref([defaultParamRow()])

const providerReadonlyType = computed(() => {
  return Boolean(providerForm.value.id) && Number(providerForm.value.is_builtin) === 1
})
const providerFilterOptions = computed(() => {
  const type = modelQuery.type
  return providerList.value.filter(item => {
    if (Number(item.status) !== 1) {
      return false
    }
    if (!type) {
      return true
    }
    return String(item.type) === String(type)
  })
})
const modelProviderOptions = computed(() => {
  const type = modelForm.value.type
  return providerList.value.filter(item => Number(item.status) === 1 && String(item.type) === String(type))
})
const selectedModelProvider = computed(() => {
  const providerId = Number(modelForm.value.provider_id || 0)
  if (providerId <= 0) {
    return null
  }
  return providerList.value.find(item => Number(item.id) === providerId) || null
})

onMounted(async() => {
  await fetchModelTypeOptions()
  await fetchProviderList()
  fetchModelList()
})

function unwrapResponse(response) {
  const payload = response && response.data ? response.data : (response || {})
  return payload.data || payload
}
function typeLabel(type) {
  const option = modelTypeOptions.value.find(item => String(item.value) === String(type))
  if (option) {
    return option.label
  }

  const fallbackMap = {
    chat: '对话',
    embedding: '向量',
    image: '图片'
  }

  return fallbackMap[type] || type
}
function typeTagType(type) {
  const tagMap = {
    chat: 'success',
    embedding: 'warning',
    image: 'info'
  }

  return tagMap[type] || 'info'
}
function providerHasApiKey(provider) {
  return Boolean(provider && String(provider.api_key || '').trim())
}
function openProviderEditById(providerId) {
  const provider = providerList.value.find(item => Number(item.id) === Number(providerId))
  if (!provider) {
    ElMessage.warning('未找到对应服务商，请先刷新页面后重试')
    return
  }
  openProviderEdit(provider)
}
async function fetchModelTypeOptions() {
  const response = await getCsAiModelTypeOptions()
  const data = unwrapResponse(response)
  modelTypeOptions.value = Array.isArray(data) ? data : []
}
async function fetchProviderList() {
  providerLoading.value = true
  try {
    const response = await getCsAiProviderList(providerQuery)
    const data = unwrapResponse(response)
    providerList.value = data.list || []
    providerTotal.value = Number(data.total || 0)

    if (Number(selectedProviderId.value) > 0) {
      const existed = providerList.value.find(item => Number(item.id) === Number(selectedProviderId.value))
      if (!existed) {
        handleSelectProvider(0, false)
      }
    }
  } finally {
    providerLoading.value = false
  }
}
function resetProviderQuery() {
  Object.assign(providerQuery, defaultProviderQuery())
  fetchProviderList()
}
function handleSelectProvider(providerId, shouldFetch = true) {
  selectedProviderId.value = Number(providerId || 0)
  modelQuery.provider_id = selectedProviderId.value > 0 ? selectedProviderId.value : ''
  modelQuery.page = 1
  if (shouldFetch) {
    fetchModelList()
  }
}
async function handleProviderStatusQuick(row) {
  const targetStatus = Number(row.status) === 1 ? 0 : 1
  await updateCsAiProviderStatus(row.id, { status: targetStatus })
  ElMessage.success(targetStatus === 1 ? '服务商已启用' : '服务商已禁用')
  await fetchProviderList()
  fetchModelList()
}
function handleProviderRootMenuCommand(command) {
  if (command === 'create') {
    openProviderCreate()
    return
  }
  if (command === 'refresh') {
    fetchProviderList()
  }
}
function handleProviderMenuCommand(row, command) {
  if (command === 'edit') {
    openProviderEdit(row)
    return
  }
  if (command === 'toggle') {
    handleProviderStatusQuick(row)
    return
  }
  if (command === 'delete') {
    handleDeleteProvider(row)
  }
}
function handleProviderSizeChange(size) {
  providerQuery.limit = size
  fetchProviderList()
}
function handleProviderCurrentChange(page) {
  providerQuery.page = page
  fetchProviderList()
}
function openProviderCreate() {
  providerForm.value = defaultProviderForm()
  showProviderDialog.value = true
}
function openProviderEdit(row) {
  providerForm.value = {
    id: Number(row.id || 0),
    type: row.type || 'chat',
    provider_code: row.provider_code || '',
    provider_name: row.provider_name || '',
    driver: row.driver || 'openai_compatible',
    base_url: row.base_url || '',
    api_key: row.api_key || '',
    status: Number(row.status || 0),
    sort: Number(row.sort || 0),
    is_builtin: Number(row.is_builtin || 0)
  }
  showProviderDialog.value = true
}
async function submitProviderForm() {
  if (!providerForm.value.type) {
    ElMessage.warning('请选择模型类型')
    return
  }
  if (!providerForm.value.provider_code) {
    ElMessage.warning('请输入服务商编码')
    return
  }
  if (!providerForm.value.provider_name) {
    ElMessage.warning('请输入服务商名称')
    return
  }

  const payload = {
    type: providerForm.value.type,
    provider_code: providerForm.value.provider_code,
    provider_name: providerForm.value.provider_name,
    driver: providerForm.value.driver,
    base_url: providerForm.value.base_url,
    api_key: providerForm.value.api_key,
    status: Number(providerForm.value.status),
    sort: Number(providerForm.value.sort || 0)
  }

  if (providerForm.value.id) {
    await updateCsAiProvider(providerForm.value.id, payload)
    ElMessage.success('服务商更新成功')
  } else {
    await createCsAiProvider(payload)
    ElMessage.success('服务商创建成功')
  }

  showProviderDialog.value = false
  await fetchProviderList()
  fetchModelList()
}
async function handleProviderStatusChange(row, value) {
  await updateCsAiProviderStatus(row.id, { status: value ? 1 : 0 })
  ElMessage.success('服务商状态已更新')
  await fetchProviderList()
  fetchModelList()
}
async function handleDeleteProvider(row) {
  await ElMessageBox.confirm('确定删除该服务商吗？', '提示', { type: 'warning' })
  await deleteCsAiProvider(row.id)
  ElMessage.success('服务商删除成功')
  await fetchProviderList()
  fetchModelList()
}
async function fetchModelList() {
  modelLoading.value = true
  try {
    const response = await getCsAiModelList(modelQuery)
    const data = unwrapResponse(response)
    modelList.value = data.list || []
    modelTotal.value = Number(data.total || 0)
  } finally {
    modelLoading.value = false
  }
}
function resetModelQuery() {
  Object.assign(modelQuery, defaultModelQuery())
  modelQuery.provider_id = Number(selectedProviderId.value) > 0 ? Number(selectedProviderId.value) : ''
  fetchModelList()
}
function handleModelTypeChange() {
  modelQuery.provider_id = Number(selectedProviderId.value) > 0 ? Number(selectedProviderId.value) : ''
  modelQuery.page = 1
  fetchModelList()
}
function handleModelSizeChange(size) {
  modelQuery.limit = size
  fetchModelList()
}
function handleModelCurrentChange(page) {
  modelQuery.page = page
  fetchModelList()
}
function openModelCreate() {
  modelForm.value = defaultModelForm()
  modelParamRows.value = [defaultParamRow()]

  if (Number(selectedProviderId.value) > 0) {
    const selectedProvider = providerList.value.find(item => Number(item.id) === Number(selectedProviderId.value))
    if (selectedProvider) {
      modelForm.value.type = selectedProvider.type || 'chat'
      modelForm.value.provider_id = Number(selectedProvider.id)
    }
  }

  syncModelProviderByType()
  showModelDialog.value = true
}
function openModelEdit(row) {
  modelForm.value = {
    id: Number(row.id || 0),
    provider_id: Number(row.provider_id || 0) > 0 ? Number(row.provider_id) : '',
    type: row.type || 'chat',
    model_id: row.model_id || '',
    model_name: row.model_name || '',
    timeout: Number(row.timeout || 30),
    status: Number(row.status || 1),
    sort: Number(row.sort || 0)
  }
  const params = row.params && typeof row.params === 'object' ? row.params : {}
  modelParamRows.value = Object.keys(params).length > 0
    ? Object.keys(params).map((key) => ({ key, value: params[key] === null || params[key] === undefined ? '' : String(params[key]) }))
    : [defaultParamRow()]
  showModelDialog.value = true
}
function syncModelProviderByType() {
  const first = modelProviderOptions.value[0]
  if (first && Number(modelForm.value.provider_id) <= 0) {
    modelForm.value.provider_id = Number(first.id)
  }
  if (!first) {
    modelForm.value.provider_id = ''
  }
}
function addParamRow() {
  modelParamRows.value.push(defaultParamRow())
}
function removeParamRow(index) {
  if (modelParamRows.value.length <= 1) {
    modelParamRows.value = [defaultParamRow()]
    return
  }
  modelParamRows.value.splice(index, 1)
}
function normalizeParamValue(rawValue) {
  const value = String(rawValue || '').trim()
  if (value === '') {
    return ''
  }
  if (value === 'true') {
    return true
  }
  if (value === 'false') {
    return false
  }
  if (value === 'null') {
    return null
  }
  if (/^-?\d+(\.\d+)?$/.test(value)) {
    return Number(value)
  }
  if ((value.startsWith('{') && value.endsWith('}')) || (value.startsWith('[') && value.endsWith(']'))) {
    try {
      return JSON.parse(value)
    } catch (error) {
      return value
    }
  }
  return value
}
function buildModelParams() {
  const params = {}
  for (const row of modelParamRows.value) {
    const key = String((row && row.key) || '').trim()
    if (!key) {
      continue
    }
    if (Object.prototype.hasOwnProperty.call(params, key)) {
      ElMessage.warning(`参数名重复：${key}`)
      return null
    }
    params[key] = normalizeParamValue(row.value)
  }
  return params
}
async function submitModelForm() {
  if (!modelForm.value.type) {
    ElMessage.warning('请选择模型类型')
    return
  }
  if (Number(modelForm.value.provider_id) <= 0) {
    ElMessage.warning('请选择服务商')
    return
  }
  if (!modelForm.value.model_id) {
    ElMessage.warning('请输入模型ID')
    return
  }

  const params = buildModelParams()
  if (params === null) {
    return
  }

  const payload = {
    provider_id: Number(modelForm.value.provider_id),
    type: modelForm.value.type,
    model_id: modelForm.value.model_id,
    model_name: modelForm.value.model_name,
    params,
    timeout: Number(modelForm.value.timeout || 30),
    status: Number(modelForm.value.status),
    sort: Number(modelForm.value.sort || 0)
  }

  if (modelForm.value.id) {
    await updateCsAiModel(modelForm.value.id, payload)
    ElMessage.success('模型更新成功')
  } else {
    await createCsAiModel(payload)
    ElMessage.success('模型创建成功')
  }

  showModelDialog.value = false
  fetchModelList()
}
async function handleModelStatusChange(row, value) {
  await updateCsAiModelStatus(row.id, { status: value ? 1 : 0 })
  ElMessage.success('模型状态已更新')
  fetchModelList()
}
async function handleSetDefaultModel(row) {
  await setCsAiModelDefault(row.id)
  ElMessage.success('默认模型设置成功')
  fetchModelList()
}
async function handleDeleteModel(row) {
  await ElMessageBox.confirm('确定删除该模型吗？', '提示', { type: 'warning' })
  await deleteCsAiModel(row.id)
  ElMessage.success('模型删除成功')
  fetchModelList()
}
</script>

<style lang="scss" scoped>
.cs-ai-model-page {
  min-height: calc(100vh - 124px);
  padding: 0;
  border-radius: 4px;
  background: var(--prev-bg-white);
  box-sizing: border-box;

  .split-layout {
    display: flex;
    align-items: stretch;
    min-height: calc(100vh - 124px);
  }

  .left-pane {
    width: 260px;
    min-width: 260px;
    flex-shrink: 0;
    border: 0;
    border-right: 1px solid var(--prev-border-color-light);
    border-radius: 0;
    background: transparent;

    :deep(.el-card__body) {
      padding: 20px 12px 20px 16px;
    }
  }

  .right-pane {
    flex: 1;
    min-width: 0;
    margin-left: 20px;
    margin-top: 20px;
    border: 0;
    border-radius: 0;
    background: transparent;

    :deep(.el-card__body) {
      padding: 0 13px 10px 0;
    }
  }

  .pane-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 18px;
    padding: 0 8px;
  }

  .pane-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--ds-on-surface);
  }

  .pane-action {
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

  .provider-list {
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

  .provider-item {
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

    &.active {
      background: var(--ds-surface-low);
    }
  }

  .provider-main {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
    min-width: 0;
  }

  .provider-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 16px;
    height: 16px;
    color: var(--ds-primary);
    flex-shrink: 0;

    i {
      font-size: 16px;
      line-height: 1;
    }
  }

  .provider-item.active .provider-icon {
    color: var(--ds-primary);
  }

  .provider-content {
    display: flex;
    align-items: center;
    flex: 1;
    min-width: 0;
  }

  .name {
    display: inline-block;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: var(--ds-on-surface-variant);
    font-size: 14px;
    font-weight: 400;
    line-height: 20px;
  }

  .provider-item.active .name {
    color: var(--ds-primary);
  }

  .query-form {
    margin-bottom: 16px;

    :deep(.el-form-item) {
      margin-bottom: 12px;
    }
  }

  .action-row {
    margin: 0 0 16px;
  }

  .provider-more {
    display: inline-flex;
    align-items: center;
    flex-shrink: 0;
  }

  .provider-more-trigger {
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

  .mt16 {
    margin-top: 0;
  }

  .w120 {
    width: 120px;
  }

  .w140 {
    width: 140px;
  }

  .w220 {
    width: 220px;
  }

  .w240 {
    width: 240px;
  }

  .w280 {
    width: 280px;
  }

  .danger-text {
    color: var(--dashboard-danger);
  }

  .form-tip {
    margin-top: 6px;
    color: var(--ds-on-surface-variant);
    font-size: 12px;
    line-height: 1.6;
  }

  .provider-config-tip {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 14px;
    border: 1px solid var(--ds-outline);
    border-radius: 10px;
    background: var(--dashboard-accent-soft);
  }

  .provider-config-main {
    flex: 1;
    min-width: 0;
  }

  .provider-config-title {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 6px;
  }

  .provider-config-name {
    color: var(--ds-on-surface);
    font-size: 14px;
    font-weight: 500;
  }

  .provider-config-desc {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 16px;
    color: var(--ds-on-surface-variant);
    font-size: 12px;
    line-height: 1.7;
  }

  .param-editor {
    width: 100%;
  }

  .param-table {
    width: 100%;

    :deep(.el-table__header th) {
      background: rgba(var(--ds-surface-low-rgb), 0.5);
      color: var(--ds-on-surface-variant);
      font-weight: 500;
    }
  }

  .param-actions {
    margin-top: 10px;
    display: flex;
    justify-content: flex-end;
  }

  .param-actions :deep(.el-button) {
    min-width: 92px;
  }

  .pagination-wrap {
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
  }
}

@media (max-width: 1200px) {
  .cs-ai-model-page {
    .left-pane {
      width: 240px;
      min-width: 240px;
    }
  }
}

@media (max-width: 960px) {
  .cs-ai-model-page {
    .split-layout {
      display: block;
      min-height: auto;
    }

    .left-pane {
      width: 100%;
      min-width: 0;
      margin-bottom: 16px;
      border-right: 0;
      border-bottom: 1px solid var(--prev-border-color-light);

      :deep(.el-card__body) {
        padding-right: 0;
      }
    }

    .right-pane {
      margin-left: 0;

      :deep(.el-card__body) {
        padding-right: 0;
      }
    }

    .provider-list {
      min-height: auto;
    }
  }
}
</style>
