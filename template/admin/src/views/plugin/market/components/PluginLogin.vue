<template>
  <el-dialog
    :model-value="visible"
    class="plugin-login-dialog"
    width="420px"
    :show-close="!submitLoading"
    :close-on-click-modal="!submitLoading"
    :close-on-press-escape="!submitLoading"
    destroy-on-close
    @update:model-value="handleVisibleChange"
  >
    <template #header>
      <div class="plugin-login-dialog__header">
        <h3 class="plugin-login-dialog__title">登录插件市场</h3>
        <p class="plugin-login-dialog__subtitle">登录后可购买插件并管理开发者资产</p>
      </div>
    </template>

    <div class="plugin-login-dialog__tabs">
      <button
        type="button"
        class="plugin-login-dialog__tab"
        :class="{ 'is-active': activeTab === 'phone' }"
        @click="switchTab('phone')"
      >
        手机登录
      </button>
      <button
        type="button"
        class="plugin-login-dialog__tab"
        :class="{ 'is-active': activeTab === 'account' }"
        @click="switchTab('account')"
      >
        账号密码
      </button>
    </div>

    <el-form
      :model="form"
      class="plugin-login-dialog__form"
      label-position="top"
      hide-required-asterisk
      @submit.prevent
    >
      <template v-if="activeTab === 'phone'">
        <el-form-item label="手机号">
          <el-input
            v-model="form.phone"
            class="plugin-login-dialog__input"
            placeholder="请输入手机号"
            maxlength="11"
            clearable
          />
        </el-form-item>
        <el-form-item label="短信验证码">
          <div class="plugin-login-dialog__code-row">
            <el-input
              v-model="form.code"
              class="plugin-login-dialog__input"
              placeholder="请输入短信验证码"
              maxlength="6"
              clearable
            />
            <el-button
              class="plugin-login-dialog__send-button"
              :disabled="!isPhoneValid || countdown > 0 || sendLoading"
              :loading="sendLoading"
              @click="handleSendCode"
            >
              {{ countdown > 0 ? `${countdown}s 后重发` : '获取验证码' }}
            </el-button>
          </div>
        </el-form-item>
      </template>

      <template v-else>
        <el-form-item label="账号">
          <el-input
            v-model="form.account"
            class="plugin-login-dialog__input"
            placeholder="请输入手机号"
            maxlength="11"
            clearable
          />
        </el-form-item>
        <el-form-item label="密码">
          <el-input
            v-model="form.password"
            class="plugin-login-dialog__input"
            type="password"
            placeholder="请输入登录密码"
            show-password
            maxlength="32"
          />
        </el-form-item>
      </template>

      <el-button
        class="plugin-login-dialog__submit"
        type="primary"
        :loading="submitLoading"
        :disabled="!canSubmit"
        @click="handleSubmit"
      >
        登 录
      </el-button>

      <p class="plugin-login-dialog__agreement">
        登录即表示同意
        <a href="javascript:;">《插件市场服务协议》</a>
      </p>
    </el-form>

    <SmsClickCaptcha
      :visible="captchaVisible"
      :phone="form.phone"
      @close="captchaVisible = false"
      @sent="handleCaptchaSent"
    />
  </el-dialog>
</template>

<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { getPluginSmsScene, pluginAccountLogin, pluginPhoneLogin } from '@/api/plugin'
import SmsClickCaptcha from './SmsClickCaptcha.vue'

defineOptions({
  name: 'PluginLogin'
})

const props = defineProps({
  visible: { type: Boolean, default: false }
})

const emit = defineEmits(['update:visible', 'success'])

const activeTab = ref('phone')
const form = reactive({
  phone: '',
  code: '',
  account: '',
  password: ''
})
const submitLoading = ref(false)
const sendLoading = ref(false)
const captchaVisible = ref(false)
const countdown = ref(0)
let countdownTimer = null

const isPhoneValid = computed(() => /^1[3-9]\d{9}$/.test(String(form.phone || '').trim()))
const canSubmit = computed(() => {
  if (activeTab.value === 'phone') {
    return isPhoneValid.value && String(form.code || '').trim().length >= 4
  }
  return /^1[3-9]\d{9}$/.test(String(form.account || '').trim()) && String(form.password || '').trim() !== ''
})

watch(() => props.visible, (next) => {
  if (next) {
    return
  }
  stopCountdown()
  captchaVisible.value = false
})

onBeforeUnmount(stopCountdown)

function switchTab(tab) {
  if (submitLoading.value) {
    return
  }
  activeTab.value = tab
}

function handleVisibleChange() {
  emit('update:visible', false)
}

function stopCountdown() {
  if (countdownTimer) {
    clearInterval(countdownTimer)
    countdownTimer = null
  }
  countdown.value = 0
}

function startCountdown(seconds) {
  stopCountdown()
  countdown.value = Number(seconds || 60)
  countdownTimer = setInterval(() => {
    countdown.value -= 1
    if (countdown.value <= 0) {
      stopCountdown()
    }
  }, 1000)
}

async function handleSendCode() {
  if (!isPhoneValid.value) {
    ElMessage.warning('请输入正确的手机号')
    return
  }
  if (countdown.value > 0 || sendLoading.value) {
    return
  }
  sendLoading.value = true
  try {
    await getPluginSmsScene(form.phone)
    captchaVisible.value = true
  } catch (error) {
    ElMessage.error(error?.message || '验证场景获取失败')
  } finally {
    // eslint-disable-next-line require-atomic-updates
    sendLoading.value = false
  }
}

function handleCaptchaSent() {
  captchaVisible.value = false
  startCountdown(60)
  ElMessage.success('验证码已发送，请注意查收')
}

async function handleSubmit() {
  if (!canSubmit.value || submitLoading.value) {
    return
  }
  submitLoading.value = true
  try {
    const response = activeTab.value === 'phone'
      ? await pluginPhoneLogin({ phone: form.phone, code: form.code })
      : await pluginAccountLogin({ account: form.account, password: form.password })
    const user = readPayload(response)?.user || {}
    ElMessage.success('登录成功')
    emit('update:visible', false)
    emit('success', user)
    resetForm()
  } catch (error) {
    ElMessage.error(error?.message || '登录失败')
  } finally {
    // eslint-disable-next-line require-atomic-updates
    submitLoading.value = false
  }
}

function resetForm() {
  stopCountdown()
  form.phone = ''
  form.code = ''
  form.account = ''
  form.password = ''
}

function readPayload(response) {
  return response?.data ?? response ?? {}
}
</script>

<style scoped lang="scss">
.plugin-login-dialog {
  :deep(.el-dialog__header) {
    padding-bottom: 6px;
  }
}

.plugin-login-dialog__header {
  padding-top: 2px;
}

.plugin-login-dialog__title {
  margin: 0;
  font-size: 18px;
  line-height: 1.3;
  font-weight: 700;
  color: #101828;
}

.plugin-login-dialog__subtitle {
  margin: 4px 0 0;
  font-size: 12px;
  line-height: 1.5;
  color: #98a2b3;
}

.plugin-login-dialog__tabs {
  display: flex;
  gap: 24px;
  margin-bottom: 16px;
  border-bottom: 1px solid #edf2f7;
}

.plugin-login-dialog__tab {
  position: relative;
  padding: 0 2px 12px;
  border: 0;
  background: transparent;
  color: #667085;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;

  &::after {
    content: '';
    position: absolute;
    right: 0;
    bottom: -1px;
    left: 0;
    height: 2px;
    border-radius: 2px;
    background: transparent;
  }

  &.is-active {
    color: #2563eb;

    &::after {
      background: #2563eb;
    }
  }
}

.plugin-login-dialog__form {
  :deep(.el-form-item__label) {
    padding-bottom: 4px;
    font-size: 13px;
    color: #475569;
  }
}

.plugin-login-dialog__input {
  width: 100%;

  :deep(.el-input__wrapper) {
    border-radius: 8px;
  }
}

.plugin-login-dialog__code-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 10px;
  width: 100%;
}

.plugin-login-dialog__send-button {
  min-width: 118px;
}

.plugin-login-dialog__submit {
  width: 100%;
  height: 42px;
  margin-top: 8px;
  border-radius: 8px;
  font-size: 15px;
  font-weight: 600;
}

.plugin-login-dialog__agreement {
  margin: 14px 0 0;
  text-align: center;
  font-size: 12px;
  color: #98a2b3;

  a {
    color: #2563eb;
    text-decoration: none;
  }
}
</style>
