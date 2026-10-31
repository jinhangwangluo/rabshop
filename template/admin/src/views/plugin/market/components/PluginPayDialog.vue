<template>
  <el-dialog
    :model-value="visible"
    class="plugin-pay-dialog"
    width="520px"
    :show-close="!paying"
    :close-on-click-modal="!paying"
    :close-on-press-escape="!paying"
    destroy-on-close
    @update:model-value="handleVisibleChange"
  >
    <template #header>
      <div class="plugin-pay-dialog__header">
        <span class="plugin-pay-dialog__header-icon">
          <el-icon><Wallet /></el-icon>
        </span>
        <span class="plugin-pay-dialog__header-title">支付订单</span>
      </div>
    </template>

    <!-- 商品与金额 -->
    <div class="plugin-pay-dialog__product">
      <div class="plugin-pay-dialog__product-main">
        <div class="plugin-pay-dialog__product-name">{{ productName }}</div>
        <div class="plugin-pay-dialog__product-desc">{{ productDesc }}</div>
      </div>
      <div class="plugin-pay-dialog__amount-block">
        <div class="plugin-pay-dialog__amount-label">支付金额</div>
        <div class="plugin-pay-dialog__amount">¥{{ amountText }}</div>
      </div>
    </div>

    <!-- 支付方式 -->
    <div class="plugin-pay-dialog__methods">
      <button
        type="button"
        class="plugin-pay-dialog__method"
        :class="{ 'is-active': payType === 'wechat' }"
        @click="switchPayType('wechat')"
      >
        <span class="plugin-pay-dialog__method-icon is-wechat">
          <el-icon><ChatDotRound /></el-icon>
        </span>
        <span class="plugin-pay-dialog__method-name">微信支付</span>
      </button>
      <button
        type="button"
        class="plugin-pay-dialog__method"
        :class="{ 'is-active': payType === 'alipay' }"
        @click="switchPayType('alipay')"
      >
        <span class="plugin-pay-dialog__method-icon is-alipay">
          <el-icon><Wallet /></el-icon>
        </span>
        <span class="plugin-pay-dialog__method-name">支付宝</span>
      </button>
    </div>

    <!-- 二维码 -->
    <div v-loading="paying" class="plugin-pay-dialog__qr-wrap">
      <div class="plugin-pay-dialog__qr-card">
        <img v-if="qrImage" class="plugin-pay-dialog__qr" :src="qrImage" alt="支付二维码">
        <div v-else-if="payError" class="plugin-pay-dialog__qr-empty">
          <span>{{ payError }}</span>
          <el-button text type="primary" :icon="RefreshRight" @click="initPay(payType)">重试</el-button>
        </div>
        <div v-else class="plugin-pay-dialog__qr-empty">
          <el-icon class="plugin-pay-dialog__qr-empty-icon"><Loading /></el-icon>
          <span>正在生成支付二维码...</span>
        </div>
      </div>
      <p v-if="qrImage" class="plugin-pay-dialog__qr-tip">
        请使用{{ payType === 'wechat' ? '微信' : '支付宝' }}扫一扫完成支付
      </p>
    </div>

    <!-- 状态 -->
    <div v-if="paid" class="plugin-pay-dialog__paid-tip">
      <el-icon class="plugin-pay-dialog__paid-icon"><CircleCheckFilled /></el-icon>
      支付成功，授权将自动下发
    </div>

    <!-- 底部 -->
    <div class="plugin-pay-dialog__footer">
      <el-icon class="plugin-pay-dialog__footer-icon"><CircleCheckFilled /></el-icon>
      <span>支付由微信支付 / 支付宝提供安全保障</span>
    </div>
  </el-dialog>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import QRCode from 'qrcode'
import {
  ChatDotRound,
  CircleCheckFilled,
  Loading,
  RefreshRight,
  Wallet
} from '@element-plus/icons-vue'
import { getPluginOrderStatus, payPluginOrder } from '@/api/plugin'

defineOptions({
  name: 'PluginPayDialog'
})

const props = defineProps({
  visible: { type: Boolean, default: false },
  order: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['update:visible', 'success'])

const payType = ref('wechat')
const paying = ref(false)
const paid = ref(false)
const qrImage = ref('')
const payError = ref('')
let pollTimer = null
let session = 0
let polling = false

const orderNo = computed(() => String(props.order?.order_no || ''))
const amountText = computed(() => {
  const amount = Number(props.order?.pay_amount || 0)
  return Number.isFinite(amount) ? amount.toFixed(2) : '0.00'
})
const productName = computed(() => String(props.order?.plugin_name || props.order?.item?.plugin_name || props.order?.sku?.name || '插件商品'))
const productDesc = computed(() => String(props.order?.sku?.billing_label || props.order?.item?.billing_label || props.order?.billing_label || '插件购买'))

watch(() => props.visible, (next) => {
  session += 1
  if (!next) {
    stopPolling()
    paying.value = false
    return
  }
  paid.value = false
  qrImage.value = ''
  payError.value = ''
  payType.value = 'wechat'
  void initPay('wechat')
})

onBeforeUnmount(() => {
  session += 1
  stopPolling()
})

async function initPay(type) {
  stopPolling()
  if (!orderNo.value) {
    payError.value = '订单号无效，请重新创建订单'
    return
  }
  if (props.order?.already_paid === true) {
    paid.value = true
    emit('success', props.order)
    return
  }
  if (await requestQrCode(type)) {
    startPolling()
  }
}

async function requestQrCode(type) {
  if (!orderNo.value || !props.visible || paying.value) {
    return false
  }
  const currentSession = session
  paying.value = true
  payError.value = ''
  qrImage.value = ''
  try {
    const response = await payPluginOrder({ order_no: orderNo.value, pay_type: type })
    if (currentSession !== session || !props.visible) {
      return false
    }
    const data = readPayload(response) || {}
    if (data.already_paid === true) {
      paid.value = true
      stopPolling()
      ElMessage.success('支付已完成')
      emit('success', props.order)
      return false
    }
    const code = String(type === 'wechat' ? (data.code_url || '') : (data.qr_code || ''))
    if (!code) {
      throw new Error(type === 'wechat' ? '微信二维码获取失败' : '支付宝二维码获取失败')
    }
    const image = await QRCode.toDataURL(code, {
      width: 260,
      margin: 1,
      errorCorrectionLevel: 'M'
    })
    if (currentSession !== session || !props.visible) {
      return false
    }
    qrImage.value = image
    return true
  } catch (error) {
    if (currentSession === session && props.visible) {
      payError.value = error?.message || '支付二维码获取失败'
      ElMessage.error(payError.value)
    }
    return false
  } finally {
    if (currentSession === session) {
      // 当前会话独占支付请求锁，过期请求不能结束新会话的 loading。
      // eslint-disable-next-line require-atomic-updates
      paying.value = false
    }
  }
}

function switchPayType(type) {
  if (payType.value === type || paying.value || paid.value) {
    return
  }
  payType.value = type
  qrImage.value = ''
  void initPay(type)
}

function startPolling() {
  stopPolling()
  if (!props.visible || paid.value) {
    return
  }
  pollTimer = setInterval(() => {
    void pollOrderStatus()
  }, 3000)
}

async function pollOrderStatus() {
  if (!props.visible || !orderNo.value || paying.value || polling || paid.value) {
    return
  }
  const currentSession = session
  polling = true
  try {
    const response = await getPluginOrderStatus({
      order_no: orderNo.value,
      pay_type: payType.value
    })
    if (currentSession !== session || !props.visible) {
      return
    }
    const data = readPayload(response) || {}
    const order = data.order || {}
    if (Number(order.status || 0) === 1) {
      stopPolling()
      // 响应已通过会话检查，当前轮询独占请求锁。
      // eslint-disable-next-line require-atomic-updates
      paid.value = true
      ElMessage.success('支付成功')
      emit('success', props.order)
    }
  } catch (error) {
    // 轮询失败不打断用户，等待下一次轮询重试。
  } finally {
    // 请求锁在 await 前已获取，仅此请求负责释放，不依赖旧状态值。
    // eslint-disable-next-line require-atomic-updates
    polling = false
  }
}

function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }
}

function handleVisibleChange() {
  if (paying.value) {
    return
  }
  emit('update:visible', false)
}

function readPayload(response) {
  return response?.data ?? response ?? {}
}
</script>

<style scoped lang="scss">
.plugin-pay-dialog {
  :deep(.el-dialog__header) {
    padding-bottom: 12px;
  }
}

.plugin-pay-dialog__header {
  display: flex;
  align-items: center;
  gap: 10px;
}

.plugin-pay-dialog__header-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border-radius: 9px;
  background: #eff6ff;
  color: #2563eb;

  .el-icon {
    font-size: 18px;
  }
}

.plugin-pay-dialog__header-title {
  font-size: 17px;
  font-weight: 700;
  color: #101828;
}

.plugin-pay-dialog__product {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  padding: 16px 18px;
  border-radius: 10px;
  background: linear-gradient(90deg, #f3f8ff 0%, #eef4ff 100%);
}

.plugin-pay-dialog__product-main {
  min-width: 0;
}

.plugin-pay-dialog__product-name {
  font-size: 15px;
  font-weight: 700;
  color: #101828;
}

.plugin-pay-dialog__product-desc {
  margin-top: 6px;
  font-size: 12px;
  color: #98a2b3;
}

.plugin-pay-dialog__amount-block {
  flex-shrink: 0;
  text-align: right;
}

.plugin-pay-dialog__amount-label {
  font-size: 12px;
  color: #98a2b3;
}

.plugin-pay-dialog__amount {
  margin-top: 6px;
  font-size: 22px;
  font-weight: 700;
  color: #2563eb;
  line-height: 1.1;
}

.plugin-pay-dialog__methods {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
  margin-top: 14px;
}

.plugin-pay-dialog__method {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  height: 46px;
  border: 1px solid #e4e7ec;
  border-radius: 8px;
  background: #ffffff;
  cursor: pointer;
  transition: border-color 0.2s ease, background-color 0.2s ease;

  &:hover {
    border-color: #93c5fd;
  }

  &.is-active {
    border-color: #2563eb;
    background: #f5f9ff;
  }
}

.plugin-pay-dialog__method-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  color: #ffffff;

  .el-icon {
    font-size: 15px;
  }

  &.is-wechat {
    background: #07c160;
  }

  &.is-alipay {
    background: #1677ff;
  }
}

.plugin-pay-dialog__method-name {
  font-size: 14px;
  font-weight: 600;
  color: #101828;
}

.plugin-pay-dialog__qr-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  margin-top: 14px;
  padding: 4px 0 0;
  min-height: 260px;
  box-sizing: border-box;
}

.plugin-pay-dialog__qr-card {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 250px;
  height: 250px;
  padding: 10px;
  border: 1px solid #eef2f7;
  border-radius: 12px;
  background: #ffffff;
  box-sizing: border-box;
}

.plugin-pay-dialog__qr {
  display: block;
  width: 200px;
  height: 200px;
}

.plugin-pay-dialog__qr-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  max-width: 100%;
  text-align: center;
  overflow-wrap: anywhere;
  color: #98a2b3;
  font-size: 13px;
}

.plugin-pay-dialog__qr-empty-icon {
  font-size: 26px;
  color: #2563eb;
  animation: plugin-pay-spin 1s linear infinite;
}

.plugin-pay-dialog__qr-tip {
  margin: 0;
  font-size: 13px;
  color: #667085;
}

.plugin-pay-dialog__paid-tip {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  margin-top: 10px;
  color: #16a34a;
  font-size: 13px;
  font-weight: 600;
}

.plugin-pay-dialog__footer {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  margin-top: 16px;
  padding: 12px 0 0;
  border-top: 1px solid #f0f2f5;
  color: #98a2b3;
  font-size: 12px;
}

.plugin-pay-dialog__footer-icon {
  color: #2563eb;
}

@keyframes plugin-pay-spin {
  from {
    transform: rotate(0deg);
  }

  to {
    transform: rotate(360deg);
  }
}
</style>
