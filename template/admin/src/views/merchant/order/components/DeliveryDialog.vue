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
      <el-form-item label="快递编码">
        <el-input v-model="form.express_code" disabled placeholder="选择快递公司后自动带出" />
      </el-form-item>
      <el-form-item label="订单号">
        <span class="order-sn-text">{{ orderSn }}</span>
      </el-form-item>
    </el-form>

    <template #footer>
      <span>
        <el-button @click="localVisible = false">取 消</el-button>
        <el-button type="primary" :loading="submitting" @click="handleSubmit">确认发货</el-button>
      </span>
    </template>
  </el-dialog>
</template>

<script setup>
import { computed, getCurrentInstance, nextTick, reactive, ref, watch } from 'vue'
import { getMerchantExpressCompanyOptions, merchantDelivery } from '@/api/merchantOrder'

defineOptions({
  name: 'DeliveryDialog'
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

const { proxy } = getCurrentInstance()
const message = proxy.$message

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
  Object.assign(form, createForm())
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
    const res = await getMerchantExpressCompanyOptions({ keyword })
    expressOptions.value = res.data || []
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

function formatExpressLabel(item) {
  const prefix = Number(item.is_common) === 1 ? '常用 - ' : ''
  return `${prefix}${item.name}（${item.code}）`
}

async function handleSubmit() {
  if (!formRef.value) return
  const valid = await formRef.value.validate().catch(() => false)
  if (!valid) return
  if (!orderId.value) {
    message.error('订单信息异常')
    return
  }
  submitting.value = true
  handleExpressChange(form.express_code)
  merchantDelivery(orderId.value, { ...form }).then(() => {
    message.success('发货成功')
    emit('success')
  }).catch(err => {
    message.error((err && err.message) || '发货失败')
  }).finally(() => {
    submitting.value = false
  })
}
</script>

<style lang="scss" scoped>
.order-sn-text {
  font-weight: 600;
  color: #303133;
  word-break: break-all;
}

.full-width {
  width: 100%;
}
</style>
