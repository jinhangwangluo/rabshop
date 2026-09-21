<template>
  <el-dialog
    v-model="localVisible"
    title="调整用户资产"
    width="480px"
    :close-on-click-modal="false"
    :append-to-body="appendToBody"
    @open="handleOpen"
    @close="handleClose"
  >
    <div v-if="userName" class="adjust-user-info">
      用户：<strong>{{ userName }}</strong>
    </div>
    <el-form
      ref="formRef"
      :model="form"
      :rules="rules"
      label-width="100px"
    >
      <el-form-item label="资产类型" prop="type">
        <el-radio-group v-model="form.type">
          <el-radio label="balance">余额</el-radio>
          <el-radio label="points">积分</el-radio>
          <el-radio label="coins">金币</el-radio>
          <el-radio label="exp">经验值</el-radio>
        </el-radio-group>
      </el-form-item>
      <el-form-item label="变动方向" prop="change_type">
        <el-radio-group v-model="form.change_type">
          <el-radio label="in">增加</el-radio>
          <el-radio label="out">减少</el-radio>
        </el-radio-group>
      </el-form-item>
      <el-form-item label="变动数量" prop="amount">
        <el-input-number
          v-model="form.amount"
          :min="0.01"
          :precision="form.type === 'balance' ? 2 : 0"
          controls-position="right"
          style="width: 180px;"
        />
      </el-form-item>
      <el-form-item label="备注">
        <el-input v-model="form.remark" placeholder="请输入调整备注" maxlength="100" />
      </el-form-item>
    </el-form>
    <template #footer>
      <span>
        <el-button @click="localVisible = false">取 消</el-button>
        <el-button type="primary" :loading="loading" @click="handleSubmit">确 定</el-button>
      </span>
    </template>
  </el-dialog>
</template>

<script setup>
import { reactive, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { changeUserAsset } from '@/api/user'

defineOptions({ name: 'UserAdjustAssetDialog' })

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  userId: {
    type: [Number, String],
    default: 0
  },
  userName: {
    type: String,
    default: ''
  },
  appendToBody: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:visible', 'success'])

const formRef = ref(null)
const localVisible = ref(false)
const loading = ref(false)
const form = reactive(createDefaultForm())
const rules = {
  type: [{ required: true, message: '请选择资产类型', trigger: 'change' }],
  change_type: [{ required: true, message: '请选择变动方向', trigger: 'change' }],
  amount: [{ required: true, message: '请输入变动数量', trigger: 'blur' }]
}

watch(() => props.visible, val => {
  localVisible.value = val
}, { immediate: true })

watch(localVisible, val => {
  emit('update:visible', val)
})

function createDefaultForm() {
  return { type: 'balance', change_type: 'in', amount: 1, remark: '' }
}

function handleOpen() {
  Object.assign(form, createDefaultForm())
}

function handleClose() {
  formRef.value?.resetFields()
}

function handleSubmit() {
  formRef.value?.validate(valid => {
    if (!valid) return
    loading.value = true
    const data = { user_id: props.userId, ...form }
    changeUserAsset(data).then(res => {
      ElMessage.success(res.message || '调整成功')
      localVisible.value = false
      emit('success', props.userId)
    }).finally(() => {
      loading.value = false
    })
  })
}
</script>

<style lang="scss" scoped>
.adjust-user-info {
  margin-bottom: 16px;
  padding: 8px 12px;
  background: #f5f7fa;
  border-radius: 4px;
  font-size: 13px;
  color: #606266;
}
</style>
