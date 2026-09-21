<template>
  <el-dialog
    v-model="visible"
    :title="form.id ? '编辑知识库' : '新建知识库'"
    width="680px"
    :close-on-click-modal="false"
    @closed="resetForm"
  >
    <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
      <el-form-item label="知识库名称" prop="name">
        <el-input v-model="form.name" maxlength="50" show-word-limit placeholder="请输入知识库名称" />
      </el-form-item>
      <el-form-item label="描述">
        <el-input
          v-model="form.description"
          type="textarea"
          :rows="3"
          maxlength="200"
          show-word-limit
          placeholder="请输入描述（选填）"
        />
      </el-form-item>
      <el-form-item label="封面图">
        <Upload v-model="form.cover" />
      </el-form-item>
      <el-form-item label="绑定AI模型">
        <el-select v-model="form.ai_model_id" placeholder="请选择（选填，不选用系统默认）" clearable style="width:100%">
          <el-option :value="0" label="不指定（使用系统默认）" />
          <el-option
            v-for="m in aiModelOptions"
            :key="m.id"
            :label="getAiModelOptionLabel(m)"
            :value="Number(m.id)"
          />
        </el-select>
      </el-form-item>

      <!-- 检索配置分组 -->
      <el-divider content-position="left"><span style="font-size:12px;color:#909399">检索精度配置</span></el-divider>

      <el-form-item prop="threshold">
        <template #label>
          <span>相似度阈值</span>
          <el-tooltip placement="top" content="低于此分数的检索结果将被过滤，值越高匹配越严格，建议 0.60~0.80">
            <LegacyIcon name="el-icon-question" style="margin-left:4px;color:#c0c4cc" />
          </el-tooltip>
        </template>
        <div class="threshold-row">
          <el-slider
            v-model="form.threshold"
            :min="0.1"
            :max="1.0"
            :step="0.05"
            :marks="thresholdMarks"
            show-tooltip
            style="flex:1;margin-right:16px"
          />
          <el-input-number
            v-model="form.threshold"
            :min="0.1"
            :max="1.0"
            :step="0.05"
            :precision="2"
            style="width:120px"
          />
        </div>
        <div class="field-tip">推荐：0.60（宽松）~ 0.80（严格）</div>
      </el-form-item>

      <el-form-item prop="search_limit">
        <template #label>
          <span>最多返回条数</span>
          <el-tooltip placement="top" content="该知识库每次最多贡献多少条检索结果，多知识库时各库独立截取后再合并">
            <LegacyIcon name="el-icon-question" style="margin-left:4px;color:#c0c4cc" />
          </el-tooltip>
        </template>
        <el-input-number v-model="form.search_limit" :min="1" :max="20" :step="1" />
        <span class="field-tip" style="margin-left:12px">条（建议 3~5）</span>
      </el-form-item>

      <el-form-item label="排序">
        <el-input-number v-model="form.sort" :min="0" :max="9999" />
      </el-form-item>
      <el-form-item label="状态">
        <el-radio-group v-model="form.status">
          <el-radio :label="1">启用</el-radio>
          <el-radio :label="0">禁用</el-radio>
        </el-radio-group>
      </el-form-item>
    </el-form>
    <template #footer>
      <span>
        <el-button @click="visible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="handleSave">确定</el-button>
      </span>
    </template>
  </el-dialog>
</template>

<script setup>
import { getCurrentInstance, onBeforeMount, reactive, ref } from 'vue'
import {
  createCsKnowledgeBase,
  updateCsKnowledgeBase,
  getCsAiModelList
} from '@/api/cs'
import Upload from '@/components/Upload'

const defaultForm = () => ({
  id: 0,
  name: '',
  description: '',
  cover: '',
  ai_model_id: 0,
  sort: 0,
  status: 1,
  threshold: 0.6,
  search_limit: 5
})

defineOptions({
  name: 'KnowledgeBaseFormDialog'
})

const emit = defineEmits(['saved'])
const { proxy } = getCurrentInstance()
const message = proxy.$message
const visible = ref(false)
const saving = ref(false)
const formRef = ref(null)
const form = reactive(defaultForm())
const rules = {
  name: [{ required: true, message: '请输入知识库名称', trigger: 'blur' }]
}
const aiModelOptions = ref([])
const thresholdMarks = {
  0.6: '0.60',
  0.75: '0.75'
}

async function fetchAiModels() {
  const res = await getCsAiModelList({ status: 1, page: 1, limit: 100 })
  const data = (res && res.data && res.data.data) ? res.data.data : (res.data || {})
  aiModelOptions.value = data.list || []
}

function getAiModelOptionLabel(model) {
  const modelName = String((model && model.model_name) || '').trim()
  if (modelName) {
    return modelName
  }
  const modelId = String((model && model.model_id) || '').trim()
  if (modelId) {
    return modelId
  }
  return `模型#${Number((model && model.id) || 0)}`
}

function open(item = null) {
  if (item) {
    Object.assign(form, {
      id: Number(item.id),
      name: String(item.name || ''),
      description: String(item.description || ''),
      cover: String(item.cover || ''),
      ai_model_id: Number(item.ai_model_id || 0),
      sort: Number(item.sort || 0),
      status: Number(item.status !== undefined ? item.status : 1),
      threshold: Number(item.threshold || 0.6),
      search_limit: Number(item.search_limit || 5)
    })
  } else {
    Object.assign(form, defaultForm())
  }
  visible.value = true
}

function resetForm() {
  formRef.value && formRef.value.clearValidate()
}

async function handleSave() {
  const valid = await new Promise(resolve => formRef.value.validate(resolve))
  if (!valid) return

  saving.value = true
  try {
    const payload = {
      name: form.name,
      description: form.description,
      cover: form.cover || '',
      ai_model_id: Number(form.ai_model_id || 0),
      sort: Number(form.sort || 0),
      status: Number(form.status),
      threshold: Number(form.threshold),
      search_limit: Number(form.search_limit)
    }

    if (form.id > 0) {
      await updateCsKnowledgeBase(form.id, payload)
      message.success('修改成功')
    } else {
      await createCsKnowledgeBase(payload)
      message.success('创建成功')
    }

    visible.value = false
    emit('saved')
  } finally {
    saving.value = false
  }
}

defineExpose({ open })

onBeforeMount(() => {
  fetchAiModels()
})
</script>

<style scoped>
.threshold-row {
  display: flex;
  align-items: center;
}
.field-tip {
  font-size: 12px;
  color: #909399;
  line-height: 1.4;
  margin-top: 4px;
}
</style>
