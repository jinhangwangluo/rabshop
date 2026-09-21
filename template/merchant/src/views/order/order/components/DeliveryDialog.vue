<template>
  <el-dialog
    v-model="localVisible"
    title="订单发货"
    width="480px"
    :close-on-click-modal="false"
    @close="handleClose"
  >
    <el-form
      ref="formRef"
      :model="form"
      :rules="rules"
      label-width="90px"
    >
      <el-form-item label="快递公司" prop="express_name">
        <el-input v-model="form.express_name" placeholder="请输入快递公司名称" />
      </el-form-item>
      <el-form-item label="快递单号" prop="express_sn">
        <el-input v-model="form.express_sn" placeholder="请输入快递单号" />
      </el-form-item>
      <el-form-item label="快递编码">
        <el-input v-model="form.express_code" placeholder="可选，如 SF、YTO 等" />
      </el-form-item>
      <el-form-item label="订单号">
        <span class="order-sn-text">{{ orderSn }}</span>
      </el-form-item>
    </el-form>

    <template #footer>
      <el-button @click="localVisible = false">取 消</el-button>
      <el-button type="primary" :loading="submitting" @click="handleSubmit">确认发货</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { deliveryOrder } from '@/api/order'

defineOptions({
  name: 'DeliveryDialog'
})

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  },
  order: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['update:modelValue', 'success'])

const localVisible = ref(false)
const submitting = ref(false)
const form = ref({
  express_name: '',
  express_sn: '',
  express_code: ''
})
const rules = ref({
  express_name: [{ required: true, message: '请输入快递公司名称', trigger: 'blur' }],
  express_sn: [{ required: true, message: '请输入快递单号', trigger: 'blur' }]
})
const formRef = ref(null)

const orderId = computed(() => {
  return props.order ? (props.order.id || props.order.orderId || '') : ''
})
const orderSn = computed(() => {
  return props.order ? (props.order.orderSn || props.order.order_sn || '-') : '-'
})

watch(() => props.modelValue, (val) => {
  localVisible.value = val
  if (val) {
    resetForm()
  }
})

watch(localVisible, (val) => {
  emit('update:modelValue', val)
})

function resetForm() {
  form.value = { express_name: '', express_sn: '', express_code: '' }
  nextTick(() => {
    formRef.value && formRef.value.clearValidate()
  })
}

function handleClose() {
  resetForm()
}

function handleSubmit() {
  formRef.value.validate(valid => {
    if (!valid) return
    if (!orderId.value) {
      ElMessage.error('订单信息异常')
      return
    }

    submitting.value = true
    deliveryOrder(orderId.value, form.value).then(() => {
      ElMessage.success('发货成功')
      emit('success')
    }).catch(err => {
      ElMessage.error((err && err.message) || '发货失败')
    }).finally(() => {
      submitting.value = false
    })
  })
}
</script>

<style lang="scss" scoped>
.order-sn-text {
  font-weight: 600;
  color: var(--text-title);
  word-break: break-all;
}
</style>
