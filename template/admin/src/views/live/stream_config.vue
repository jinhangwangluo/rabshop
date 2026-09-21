<template>
  <div>
    <!-- 回调地址信息卡片 -->
    <el-card shadow="never" style="margin-bottom:16px">
      <template #header>
        <div class="card-header">
          <span>直播回调地址</span>
          <span class="tips-text">将以下地址填入对应云服务商控制台的直播事件回调，Token 可在系统配置-直播设置中修改</span>
        </div>
      </template>
      <el-descriptions v-loading="callbackLoading" :column="1" border>
        <el-descriptions-item label="阿里云回调地址">
          <div class="callback-url-row">
            <el-input :value="callbackInfo.ali_callback_url || '未配置（请先在直播设置中填写 Token）'" readonly class="callback-ipt" />
            <el-button
              v-if="callbackInfo.ali_callback_url"
              size="mini"
              icon="el-icon-copy-document"
              @click="copyUrl(callbackInfo.ali_callback_url)"
            >复制</el-button>
          </div>
        </el-descriptions-item>
        <el-descriptions-item label="七牛云回调地址">
          <div class="callback-url-row">
            <el-input :value="callbackInfo.qiniu_callback_url || '未配置（请先在直播设置中填写 Token）'" readonly class="callback-ipt" />
            <el-button
              v-if="callbackInfo.qiniu_callback_url"
              size="mini"
              icon="el-icon-copy-document"
              @click="copyUrl(callbackInfo.qiniu_callback_url)"
            >复制</el-button>
          </div>
        </el-descriptions-item>
      </el-descriptions>
    </el-card>

    <!-- 云服务配置列表 -->
    <el-card shadow="never">
      <template #header>
        <div class="card-header">
          <span>直播云服务配置</span>
          <el-button type="primary" icon="el-icon-plus" @click="handleAdd">新增配置</el-button>
        </div>
      </template>

      <!-- 配置列表 -->
      <el-table
        v-loading="listLoading"
        :data="configList"
        border
        style="width:100%"
      >
        <el-table-column label="云服务商" width="120">
          <template #default="{row}">
            <el-tag :type="row.provider === 'aliyun' ? 'primary' : 'warning'">
              {{ row.provider === 'aliyun' ? '阿里云' : '七牛云' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="label" label="配置备注" min-width="120" />
        <el-table-column prop="app_name" label="应用名/Hub" width="120" />
        <el-table-column prop="push_domain" label="推流域名" min-width="160" show-overflow-tooltip />
        <el-table-column prop="pull_domain" label="拉流域名" min-width="160" show-overflow-tooltip />
        <el-table-column label="鉴权过期" width="100">
          <template #default="{row}">{{ row.expire_seconds }}秒</template>
        </el-table-column>
        <el-table-column label="状态" width="80">
          <template #default="{row}">
            <el-tag :type="row.status === 1 ? 'success' : 'info'">
              {{ row.status === 1 ? '启用' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="默认云" width="90">
          <template #default="{row}">
            <el-tag v-if="row.is_default === 1" type="success">默认</el-tag>
            <span v-else class="text-gray">—</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="220" fixed="right">
          <template #default="{row}">
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
        <div>
          <el-button @click="dialogVisible = false">取 消</el-button>
          <el-button type="primary" :loading="saveLoading" @click="handleSave">保 存</el-button>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, onBeforeMount, reactive, ref } from 'vue'
import {
  getStreamConfigList,
  getStreamConfigDetail,
  saveStreamConfig,
  deleteStreamConfig,
  setStreamConfigDefault,
  getCallbackInfo
} from '@/api/live'

defineOptions({ name: 'LiveStreamConfig' })

const { proxy } = getCurrentInstance()

const configFormRef = ref(null)
const listLoading = ref(false)
const configList = ref([])
const callbackLoading = ref(false)
const callbackInfo = reactive({
  ali_callback_url: '',
  qiniu_callback_url: ''
})
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

const dialogTitle = computed(() => (isEdit.value ? '编辑云配置' : '新增云配置'))

function createForm() {
  return {
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
  }
}

function loadCallbackInfo() {
  callbackLoading.value = true
  getCallbackInfo().then(res => {
    Object.assign(callbackInfo, res.data || {})
  }).finally(() => {
    callbackLoading.value = false
  })
}

function copyUrl(url) {
  if (!url) return
  proxy.$copyText(url).then(() => {
    proxy?.$message?.success('地址已复制到剪贴板')
  }).catch(() => {
    proxy?.$message?.error('复制失败，请手动复制')
  })
}

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
    const d = res.data || {}
    Object.assign(form, createForm(), {
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

async function handleSave() {
  if (!configFormRef.value) return
  const valid = await configFormRef.value.validate().catch(() => false)
  if (!valid) return
  saveLoading.value = true
  const data = { ...form }
  if (isEdit.value) {
    data.id = editId.value
  }
  saveStreamConfig(data).then(() => {
    proxy?.$message?.success('保存成功')
    dialogVisible.value = false
    loadList()
  }).finally(() => {
    saveLoading.value = false
  })
}

function handleSetDefault(row) {
  proxy.$confirm(`确定将"${row.label}"设为 ${row.provider === 'aliyun' ? '阿里云' : '七牛云'} 的默认配置？`, '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    setStreamConfigDefault(row.id).then(() => {
      proxy?.$message?.success('已设为默认')
      loadList()
    })
  })
}

function handleDelete(row) {
  proxy.$confirm(`确定删除配置"${row.label}"？删除后无法恢复`, '警告', {
    confirmButtonText: '确定删除',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    deleteStreamConfig(row.id).then(() => {
      proxy?.$message?.success('删除成功')
      loadList()
    })
  })
}

function resetForm() {
  Object.assign(form, createForm())
  configFormRef.value?.clearValidate()
}

onBeforeMount(() => {
  loadList()
  loadCallbackInfo()
})
</script>

<style lang="scss" scoped>
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.form-ipt {
  width: 380px;
}
.tips-text {
  margin-left: 10px;
  color: #909399;
  font-size: 12px;
}
.text-gray {
  color: #c0c4cc;
}
.text-danger {
  color: #f56c6c;
}
.callback-url-row {
  display: flex;
  align-items: center;
  gap: 8px;
}
.callback-ipt {
  flex: 1;
}
</style>
