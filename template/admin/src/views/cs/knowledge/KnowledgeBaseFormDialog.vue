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
import { usePageSlots, modelField, slotAction, validateForm, operationResult, trackPageSlotAction } from '@/utils/pageSlots'

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
const modelsLoading = ref(true)
const formRef = ref(null)
const form = reactive(defaultForm())
const openSequence = ref(0)
const handleSave = trackPageSlotAction('save', saveKnowledgeBase)
const rules = {
  name: [{ required: true, message: '请输入知识库名称', trigger: 'blur' }]
}
const aiModelOptions = ref([])
const thresholdMarks = {
  0.6: '0.60',
  0.75: '0.75'
}

usePageSlots({
  kind: 'dialog',
  active: visible,
  identity: () => `${form.id}:${openSequence.value}`,
  page: () => ({ id: 'cs.knowledge.form', title: form.id ? '编辑知识库' : '新建知识库', description: '维护知识库信息和检索配置', entityId: form.id, mode: form.id ? 'edit' : 'create' }),
  ready: () => !!formRef.value && !modelsLoading.value,
  busy: saving,
  fields: () => [
    modelField(form, 'name', '知识库名称', { required: true, schema: { type: 'string', maxLength: 50 }}),
    modelField(form, 'description', '描述', { schema: { type: 'string', maxLength: 200 }}),
    modelField(form, 'cover', '封面图', { schema: { type: 'string', maxLength: 2048 }}),
    modelField(form, 'ai_model_id', '绑定AI模型', {
      schema: { enum: [0, '', ...aiModelOptions.value.map(item => Number(item.id))] },
      options: [{ value: 0, label: '使用系统默认' }, ...aiModelOptions.value.map(item => ({ value: Number(item.id), label: getAiModelOptionLabel(item) }))]
    }),
    modelField(form, 'threshold', '相似度阈值', { schema: { type: 'number', min: 0.1, max: 1, precision: 2 }}),
    modelField(form, 'search_limit', '最多返回条数', { schema: { type: 'integer', min: 1, max: 20 }}),
    modelField(form, 'sort', '排序', { schema: { type: 'integer', min: 0, max: 9999 }}),
    modelField(form, 'status', '状态', { schema: { enum: [0, 1] }, options: [{ value: 1, label: '启用' }, { value: 0, label: '禁用' }] })
  ],
  actions: {
    validate: slotAction('校验知识库表单', () => validateForm(formRef.value)),
    save: slotAction('保存知识库', handleSave),
    close: slotAction('关闭弹窗', () => { visible.value = false; return { ok: true } })
  }
})

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
  openSequence.value += 1
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

async function saveKnowledgeBase(args, context) {
  if (saving.value) return { ok: false, code: 'BUSY', message: '正在保存' }
  const sequence = openSequence.value
  const id = form.id
  const validation = await validateForm(formRef.value)
  if (!validation.ok) return validation
  context.assertCurrent()

  // 插槽同步门禁和上下文校验保护本次操作；finally 只收敛本次忙碌状态。
  // eslint-disable-next-line require-atomic-updates
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

    if (id > 0) {
      await updateCsKnowledgeBase(id, payload)
      message.success('修改成功')
    } else {
      await createCsKnowledgeBase(payload)
      message.success('创建成功')
    }

    if (openSequence.value === sequence) visible.value = false
    emit('saved')
    return { ok: true }
  } catch (error) {
    return operationResult(error)
  } finally {
    // 插槽同步门禁和上下文校验保护本次操作；finally 只收敛本次忙碌状态。
    // eslint-disable-next-line require-atomic-updates
    saving.value = false
  }
}

defineExpose({ open })

onBeforeMount(() => {
  fetchAiModels().catch(() => {}).finally(() => { modelsLoading.value = false })
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
