<template>
  <el-dialog
    v-model="localVisible"
    title="申请退款"
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
      <el-form-item label="订单号">
        <span class="order-sn-text">{{ orderSn }}</span>
      </el-form-item>
      <el-form-item label="退款金额" prop="refund_amount">
        <el-input-number
          v-model="form.refund_amount"
          :precision="2"
          :min="0.01"
          :max="maxAmount"
          :step="0.01"
          style="width: 100%"
        />
        <div class="amount-tip">订单实付：¥{{ formatMoney(maxAmount) }}</div>
      </el-form-item>
      <el-form-item label="退款原因" prop="refund_reason">
        <el-input
          v-model="form.refund_reason"
          type="textarea"
          :rows="3"
          placeholder="请输入退款原因"
          maxlength="200"
          show-word-limit
        />
      </el-form-item>
    </el-form>

    <template #footer>
      <span>
        <el-button @click="localVisible = false">取 消</el-button>
        <el-button type="warning" :loading="submitting" @click="handleSubmit">提交申请</el-button>
      </span>
    </template>
  </el-dialog>
</template>

<script setup>
import { computed, getCurrentInstance, nextTick, reactive, ref, watch } from 'vue'
import { merchantRefundApply } from '@/api/merchantOrder'

defineOptions({
  name: 'RefundApplyDialog'
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

const createForm = (orderId = 0, amount = 0) => ({
  order_id: orderId,
  refund_amount: amount,
  refund_reason: ''
})

const localVisible = ref(props.visible)
const submitting = ref(false)
const formRef = ref(null)
const form = reactive(createForm())
const rules = {
  refund_amount: [{ required: true, type: 'number', min: 0.01, message: '退款金额必须大于0', trigger: 'blur' }],
  refund_reason: [{ required: true, message: '请输入退款原因', trigger: 'blur' }]
}

const orderId = computed(() => (props.order ? (props.order.id || props.order.orderId || 0) : 0))
const orderSn = computed(() => (props.order ? (props.order.orderSn || props.order.order_sn || '-') : '-'))
const maxAmount = computed(() => (props.order ? Number(props.order.payAmount || props.order.pay_amount || 0) : 0))

watch(() => props.visible, val => {
  localVisible.value = val
  if (val) {
    resetForm()
  }
}, { immediate: true })

watch(localVisible, val => {
  emit('update:visible', val)
})

function resetForm() {
  Object.assign(form, createForm(orderId.value, maxAmount.value))
  nextTick(() => {
    formRef.value?.clearValidate?.()
  })
}

function handleClose() {
  resetForm()
}

async function handleSubmit() {
  if (!formRef.value) return
  const valid = await formRef.value.validate().catch(() => false)
  if (!valid) return
  submitting.value = true
  merchantRefundApply({
    order_id: orderId.value,
    refund_amount: form.refund_amount,
    refund_reason: form.refund_reason
  }).then(() => {
    message.success('退款申请已提交，等待平台审核')
    emit('success')
  }).catch(err => {
    message.error((err && err.message) || '申请失败')
  }).finally(() => {
    submitting.value = false
  })
}

function formatMoney(value) {
  return Number(value || 0).toFixed(2)
}
</script>

<style lang="scss" scoped>
.order-sn-text {
  font-weight: 600;
  color: #303133;
  word-break: break-all;
}

.amount-tip {
  margin-top: 4px;
  font-size: 12px;
  color: #909399;
}
</style>
