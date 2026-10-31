<template>
  <div class="stream-config-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">直播云服务配置</h1>
        <p class="page-curator-subtitle">
          管理阿里云、七牛云等推拉流配置，统一直播云服务商切换与默认配置。
        </p>
      </div>
      <el-button type="primary" class="btn-curator-primary" :icon="Plus" @click="handleAdd">新增配置</el-button>
    </header>

    <div class="stream-config-work-surface">
      <el-card shadow="never" class="table-card">

        <!-- 配置列表 -->
        <el-table
          v-loading="listLoading"
          :data="configList"
          border
          style="width:100%"
        >
          <el-table-column label="云服务商" width="120">
            <template #default="{ row }">
              <el-tag :type="row.provider === 'aliyun' ? 'primary' : 'warning'" >
                {{ row.provider === 'aliyun' ? '阿里云' : '七牛云' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="label" label="配置备注" min-width="120" />
          <el-table-column prop="app_name" label="应用名/Hub" width="120" />
          <el-table-column prop="push_domain" label="推流域名" min-width="160" show-overflow-tooltip />
          <el-table-column prop="pull_domain" label="拉流域名" min-width="160" show-overflow-tooltip />
          <el-table-column label="鉴权过期" width="100">
            <template #default="{ row }">{{ row.expire_seconds }}秒</template>
          </el-table-column>
          <el-table-column label="状态" width="80">
            <template #default="{ row }">
              <el-tag :type="row.status === 1 ? 'success' : 'info'" >
                {{ row.status === 1 ? '启用' : '禁用' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="默认云" width="90">
            <template #default="{ row }">
              <el-tag v-if="row.is_default === 1" type="success" >默认</el-tag>
              <span v-else class="text-gray">—</span>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="220" fixed="right">
            <template #default="{ row }">
              <el-button type="text" @click="handleEdit(row)">编辑</el-button>
              <el-divider direction="vertical" />
              <el-button
                v-if="row.is_default !== 1 && row.status === 1"
                type="text"
                @click="handleSetDefault(row)"
              >设为默认</el-button>
              <el-divider v-if="row.is_default !== 1 && row.status === 1" direction="vertical" />
              <el-button type="text" class="text-danger" @click="handleDelete(row)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-card>
    </div>

    <!-- 新增/编辑弹窗 -->
    <el-dialog
      v-model="dialogVisible"
      :title="dialogTitle"
      width="600px"
      :close-on-click-modal="false"
      @close="resetForm"
    >
      <el-form
        ref="configFormRef"
        :model="form"
        :rules="rules"
        label-width="120px"
      >
        <el-form-item label="云服务商" prop="provider">
          <el-radio-group v-model="form.provider">
            <el-radio label="aliyun">阿里云直播</el-radio>
            <el-radio label="qiniu">七牛云直播</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="配置备注" prop="label">
          <el-input v-model="form.label" placeholder="如：生产环境阿里云" class="form-ipt" />
        </el-form-item>
        <el-form-item label="AccessKey" prop="app_key">
          <el-input v-model="form.app_key" placeholder="AccessKeyId / AppKey" class="form-ipt" />
        </el-form-item>
        <el-form-item label="AccessSecret" prop="app_secret">
          <el-input
            v-model="form.app_secret"
            :placeholder="isEdit ? '不修改请留空' : 'AccessKeySecret / AppSecret'"
            show-password
            class="form-ipt"
          />
          <div v-if="isEdit" class="tips-text">编辑时留空则保留原密钥，不回显明文</div>
        </el-form-item>
        <el-form-item label="应用名/Hub" prop="app_name">
          <el-input
            v-model="form.app_name"
            :placeholder="form.provider === 'aliyun' ? '阿里云直播应用名 如：live' : '七牛 Hub 名称'"
            class="form-ipt"
          />
        </el-form-item>
        <el-form-item label="推流域名" prop="push_domain">
          <el-input v-model="form.push_domain" placeholder="如：push.example.com" class="form-ipt" />
        </el-form-item>
        <el-form-item label="拉流域名" prop="pull_domain">
          <el-input v-model="form.pull_domain" placeholder="如：pull.example.com" class="form-ipt" />
        </el-form-item>
        <el-form-item label="鉴权过期(秒)">
          <el-input-number v-model="form.expire_seconds" :min="600" :max="86400" :step="600" />
          <span class="tips-text">推流地址有效期，建议7200秒</span>
        </el-form-item>
        <el-form-item label="状态">
          <el-switch
            v-model="form.status"
            :active-value="1"
            :inactive-value="0"
            active-text="启用"
            inactive-text="禁用"
          />
        </el-form-item>
        <el-form-item label="设为默认">
          <el-switch
            v-model="form.is_default"
            :active-value="1"
            :inactive-value="0"
          />
          <span class="tips-text">同一云服务商下仅一个默认配置</span>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button class="btn-curator-reset" @click="dialogVisible = false">取 消</el-button>
        <el-button type="primary" class="btn-curator-primary" :loading="saveLoading" @click="handleSave">保 存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import {
  getStreamConfigList,
  getStreamConfigDetail,
  saveStreamConfig,
  deleteStreamConfig,
  setStreamConfigDefault
} from '@/api/live'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { computed, nextTick, onMounted, reactive, ref } from 'vue'

defineOptions({
  name: 'LiveStreamConfig'
})

const listLoading = ref(false)
const configList = ref([])
const dialogVisible = ref(false)
const saveLoading = ref(false)
const isEdit = ref(false)
const editId = ref(0)
const form = reactive({
  provider: 'aliyun',
  label: '',
  app_key: '',
  app_secret: '',
  app_name: '',
  push_domain: '',
  pull_domain: '',
  expire_seconds: 7200,
  status: 1,
  is_default: 0
})
const rules = {
  provider: [{ required: true, message: '请选择云服务商', trigger: 'change' }],
  label: [{ required: true, message: '请填写配置备注', trigger: 'blur' }],
  app_key: [{ required: true, message: '请填写 AccessKey', trigger: 'blur' }],
  app_name: [{ required: true, message: '请填写应用名/Hub', trigger: 'blur' }],
  push_domain: [{ required: true, message: '请填写推流域名', trigger: 'blur' }],
  pull_domain: [{ required: true, message: '请填写拉流域名', trigger: 'blur' }]
}
const configFormRef = ref(null)

const dialogTitle = computed(() => {
  return isEdit.value ? '编辑云配置' : '新增云配置'
})

onMounted(() => {
  loadList()
})

function loadList() {
  listLoading.value = true
  getStreamConfigList({ page: 1, limit: 100 }).then(res => {
    configList.value = res.data && res.data.list ? res.data.list : (res.data || [])
  }).finally(() => {
    listLoading.value = false
  })
}

function handleAdd() {
  isEdit.value = false
  editId.value = 0
  resetForm()
  dialogVisible.value = true
}

function handleEdit(row) {
  isEdit.value = true
  editId.value = row.id
  getStreamConfigDetail(row.id).then(res => {
    const d = res.data
    Object.assign(form, {
      provider: d.provider,
      label: d.label || '',
      app_key: d.app_key || '',
      app_secret: '',
      app_name: d.app_name || '',
      push_domain: d.push_domain || '',
      pull_domain: d.pull_domain || '',
      expire_seconds: d.expire_seconds || 7200,
      status: d.status,
      is_default: d.is_default
    })
    dialogVisible.value = true
  })
}

function handleSave() {
  configFormRef.value.validate(valid => {
    if (!valid) return
    saveLoading.value = true
    const data = { ...form }
    if (isEdit.value) {
      data.id = editId.value
    }
    saveStreamConfig(data).then(() => {
      ElMessage.success('保存成功')
      dialogVisible.value = false
      loadList()
    }).finally(() => {
      saveLoading.value = false
    })
  })
}

function handleSetDefault(row) {
  ElMessageBox.confirm(`确定将"${row.label}"设为 ${row.provider === 'aliyun' ? '阿里云' : '七牛云'} 的默认配置？`, '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    setStreamConfigDefault(row.id).then(() => {
      ElMessage.success('已设为默认')
      loadList()
    })
  })
}

function handleDelete(row) {
  ElMessageBox.confirm(`确定删除配置"${row.label}"？删除后无法恢复`, '警告', {
    confirmButtonText: '确定删除',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    deleteStreamConfig(row.id).then(() => {
      ElMessage.success('删除成功')
      loadList()
    })
  })
}

function resetForm() {
  Object.assign(form, {
    provider: 'aliyun',
    label: '',
    app_key: '',
    app_secret: '',
    app_name: '',
    push_domain: '',
    pull_domain: '',
    expire_seconds: 7200,
    status: 1,
    is_default: 0
  })
  nextTick(() => {
    configFormRef.value && configFormRef.value.clearValidate()
  })
}
</script>

<style lang="scss" scoped>
.stream-config-curator-page {
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
  max-width: 560px;
}

.stream-config-work-surface {
  background: var(--ds-surface-card);
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
}

.table-card {
  border: none;
  background: transparent;

  :deep(.el-card__body) {
    padding: 0;
  }

  :deep(.el-table__header th) {
    color: var(--ds-on-surface-variant);
    font-weight: 500;
    background: rgba(var(--ds-surface-low-rgb), 0.5);
  }

  :deep(.el-table__row:hover > td) {
    background: var(--ds-surface-low) !important;
  }
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

.form-ipt {
  width: 380px;
}

.tips-text {
  margin-left: 10px;
  color: var(--text-secondary);
  font-size: 12px;
}

.text-gray {
  color: var(--text-placeholder);
}

.text-danger {
  color: var(--text-danger);
}
</style>
