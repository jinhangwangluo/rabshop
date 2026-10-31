<template>
  <el-dialog
    v-model="localVisible"
    :title="dialogTitle"
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
      <el-form-item label="快递公司" prop="express_code">
        <el-select
          v-model="form.express_code"
          class="full-width"
          filterable
          clearable
          placeholder="请选择快递公司"
          :loading="expressLoading"
          @visible-change="handleExpressVisible"
          @change="handleExpressChange"
        >
          <el-option
            v-for="item in expressOptions"
            :key="item.code"
            :label="formatExpressLabel(item)"
            :value="item.code"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="快递单号" prop="express_sn">
        <el-input v-model="form.express_sn" placeholder="请输入快递单号" />
      </el-form-item>
      <el-form-item label="订单号">
        <span class="order-sn-text">{{ orderSn }}</span>
      </el-form-item>
    </el-form>

    <template #footer>
      <span>
        <el-button @click="localVisible = false">取 消</el-button>
        <el-button type="primary" :loading="submitting" @click="handleSubmit">{{ submitText }}</el-button>
      </span>
    </template>
  </el-dialog>
</template>

<script setup>
import { computed, nextTick, reactive, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { getExpressCompanyOptions } from '@/api/expressCompany'
import { deliverOrder } from '@/api/order'

defineOptions({
  name: 'OrderDeliveryDialog'
})

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  order: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['update:visible', 'success'])

const createForm = () => ({
  express_name: '',
  express_sn: '',
  express_code: ''
})

const localVisible = ref(props.visible)
const submitting = ref(false)
const expressLoading = ref(false)
const formRef = ref(null)
const expressOptions = ref([])
const form = reactive(createForm())
const rules = {
  express_code: [{ required: true, message: '请选择快递公司', trigger: 'change' }],
  express_sn: [{ required: true, message: '请输入快递单号', trigger: 'blur' }]
}

const orderId = computed(() => (props.order ? (props.order.id || props.order.orderId || '') : ''))
const orderSn = computed(() => (props.order ? (props.order.orderSn || props.order.order_sn || '-') : '-'))
const isEditMode = computed(() => hasFilledValue(getOrderExpressNo()) || hasFilledValue(getOrderDeliveryTime()))
const dialogTitle = computed(() => (isEditMode.value ? '修改物流' : '订单发货'))
const submitText = computed(() => (isEditMode.value ? '确认修改' : '确认发货'))

watch(() => props.visible, val => {
  localVisible.value = val
  if (val) {
    resetForm()
    fetchExpressOptions()
  }
}, { immediate: true })

watch(localVisible, val => {
  emit('update:visible', val)
})

function resetForm() {
  Object.assign(form, {
    express_name: getOrderExpressName(),
    express_sn: getOrderExpressNo(),
    express_code: getOrderExpressCode()
  })
  ensureCurrentExpressOption()
  nextTick(() => {
    formRef.value?.clearValidate?.()
  })
}

function handleClose() {
  resetForm()
}

async function fetchExpressOptions(keyword = '') {
  expressLoading.value = true
  try {
    const res = await getExpressCompanyOptions({ keyword })
    expressOptions.value = res.data || []
    ensureCurrentExpressOption()
  } finally {
    expressLoading.value = false
  }
}

function handleExpressVisible(visible) {
  if (visible && expressOptions.value.length === 0) {
    fetchExpressOptions()
  }
}

function handleExpressChange(code) {
  const selected = expressOptions.value.find(item => item.code === code)
  form.express_name = selected ? selected.name : ''
}

function getOrderExpressName() {
  return props.order ? firstFilledValue(props.order.expressName, props.order.express_name, props.order.delivery_name) : ''
}

function getOrderExpressNo() {
  return props.order ? firstFilledValue(props.order.expressNo, props.order.express_no, props.order.delivery_id) : ''
}

function getOrderExpressCode() {
  return props.order ? firstFilledValue(props.order.delivery_code, props.order.express_code) : ''
}

function getOrderDeliveryTime() {
  return props.order ? firstFilledValue(props.order.deliveryTime, props.order.delivery_time) : ''
}

function hasFilledValue(value) {
  return value !== undefined && value !== null && String(value).trim() !== '' && String(value).trim() !== '-'
}

function firstFilledValue(...values) {
  const target = values.find(item => hasFilledValue(item))
  return target === undefined ? '' : String(target).trim()
}

function ensureCurrentExpressOption() {
  if (!hasFilledValue(form.express_code) || !hasFilledValue(form.express_name)) return
  const exists = expressOptions.value.some(item => item.code === form.express_code)
  if (!exists) {
    expressOptions.value = [
      { code: form.express_code, name: form.express_name, is_common: 0 },
      ...expressOptions.value
    ]
  }
}

function formatExpressLabel(item) {
  const prefix = Number(item.is_common) === 1 ? '常用 - ' : ''
  return `${prefix}${item.name}（${item.code}）`
}

async function handleSubmit() {
  if (!formRef.value) return
  const valid = await formRef.value.validate().catch(() => false)
  if (!valid) return
  if (!orderId.value) {
    ElMessage.error('订单信息异常')
    return
  }

  submitting.value = true
  try {
    handleExpressChange(form.express_code)
    const res = await deliverOrder({
      id: orderId.value,
      ...form
    })
    ElMessage.success(isEditMode.value ? '物流信息已修改' : (res.message || '发货成功'))
    emit('success')
  } finally {
    submitting.value = false
  }
}
</script>

<style lang="scss" scoped>
.order-sn-text {
  font-weight: 600;
  color: var(--dashboard-text-primary);
  word-break: break-all;
}

.full-width {
  width: 100%;
}
</style>
