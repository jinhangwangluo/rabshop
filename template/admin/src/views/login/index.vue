<template>
  <div class="login-page" @mousemove="handleMouseMove">
    <!-- 原始背景装饰圆圈 -->
    <div class="bg-decoration">
      <div class="circle circle-1" />
      <div class="circle circle-2" />
      <div class="circle circle-3" />
      <div class="circle circle-4" />
      <div class="circle circle-5" />
      <div class="circle circle-6" />
      <div class="circle circle-7" />
    </div>

    <!-- 登录卡片（居中，含卡通角色 + 表单） -->
    <div class="login-card">

      <!-- 顶部卡通角色区域 -->
      <div class="characters-bar">
        <!-- 角色3: 橙色矮个子（最后/最深层） -->
        <div class="character char-3">
          <div class="char-body body-3" :style="getBodyStyle(2)">
            <div class="char-face">
              <div class="eye eye-left" :style="getEyeWrapStyle(eyeOffset, 4)">
                <div class="pupil" :style="getPupilStyle(eyeOffset, 4)" />
              </div>
              <div class="eye eye-right" :style="getEyeWrapStyle(eyeOffset, 5)">
                <div class="pupil" :style="getPupilStyle(eyeOffset, 5)" />
              </div>
            </div>
          </div>
        </div>

        <!-- 角色1: 紫色高个子 -->
        <div class="character char-1">
          <div class="char-body body-1" :style="getBodyStyle(0)">
            <div class="char-face">
              <div class="eye eye-left" :style="getEyeWrapStyle(eyeOffset, 0)">
                <div class="pupil" :style="getPupilStyle(eyeOffset, 0)" />
              </div>
              <div class="eye eye-right" :style="getEyeWrapStyle(eyeOffset, 1)">
                <div class="pupil" :style="getPupilStyle(eyeOffset, 1)" />
              </div>
            </div>
          </div>
        </div>

        <!-- 角色2: 黑色中等身材 -->
        <div class="character char-2">
          <div class="char-body body-2" :style="getBodyStyle(1)">
            <div class="char-face">
              <div class="eye eye-left" :style="getEyeWrapStyle(eyeOffset, 2)">
                <div class="pupil" :style="getPupilStyle(eyeOffset, 2)" />
              </div>
              <div class="eye eye-right" :style="getEyeWrapStyle(eyeOffset, 3)">
                <div class="pupil" :style="getPupilStyle(eyeOffset, 3)" />
              </div>
            </div>
          </div>
        </div>

        <!-- 角色4: 黄色小矮个（最前/最高层） -->
        <div class="character char-4">
          <div class="char-body body-4" :style="getBodyStyle(3)">
            <div class="char-face-wrapper">
              <div class="char-face face-4">
                <div class="eye eye-left eye-sm" :style="getEyeWrapStyle(eyeOffset, 6)">
                  <div class="pupil pupil-sm" :style="getPupilStyle(eyeOffset, 6)" />
                </div>
                <div class="eye eye-right eye-sm" :style="getEyeWrapStyle(eyeOffset, 7)">
                  <div class="pupil pupil-sm" :style="getPupilStyle(eyeOffset, 7)" />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 登录表单区域 -->
      <div class="form-area">
        <!-- Logo区域 -->
        <div class="logo-section">
          <img :src="loginLogo" alt="logo" class="logo-image">
          <h1 class="system-title">平台端管理系统</h1>
          <p class="system-desc">欢迎登录，请输入您的账号信息</p>
        </div>

        <el-form
          ref="formRef"
          :model="formInline"
          :rules="ruleInline"
          class="login-form"
          @submit.prevent="handleSubmit"
        >
          <el-form-item prop="username">
            <el-input
              v-model="formInline.username"
              type="text"
              placeholder="请输入用户名"
              class="login-input"
            >
              <template #prepend>
                <LegacyIcon name="el-icon-user" />
              </template>
            </el-input>
          </el-form-item>

          <el-form-item prop="password">
            <el-input
              v-model="formInline.password"
              :type="showPassword ? 'text' : 'password'"
              placeholder="请输入密码"
              class="login-input"
              @focus="handleInputFocus('password')"
              @blur="handleInputBlur"
            >
              <template #prepend>
                <LegacyIcon name="el-icon-lock" />
              </template>
              <template #suffix>
                <LegacyIcon
                  name="el-icon-view"
                  class=" password-toggle"
                  :class="{ active: showPassword }"
                  @click="togglePassword"
                />
              </template>
            </el-input>
          </el-form-item>

          <el-form-item class="submit-item">
            <el-button
              type="primary"
              :loading="loading"
              class="login-btn"
              @click="handleSubmit"
            >
              {{ loading ? '登录中...' : '登 录' }}
            </el-button>
          </el-form-item>
        </el-form>
      </div>
    </div>

    <!-- 验证码组件 -->
    <Verify
      ref="verifyRef"
      captcha-type="blockPuzzle"
      :img-size="{ width: '330px', height: '155px' }"
      @success="success"
    />

    <!-- 底部版权 -->
    <div class="footer">
      <div class="copyright-text">
        Copyright © 2014-2026 <a href="https://www.lfmn.fun" target="_blank">乖兔阅读</a>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import Verify from '@/components/Verifition/Verify'
import settings from '@/settings'
import { useUserStore } from '@/store/modules/user'
import loginLogo from '@/assets/images/logo.png'

defineOptions({
  name: 'Login'
})

const userStore = useUserStore()
const route = useRoute()
const router = useRouter()

const formRef = ref(null)
const verifyRef = ref(null)
const loading = ref(false)
const showPassword = ref(false)
const isPasswordVisible = ref(false)
const eyeOffset = reactive({ x: 0, y: 0 })
const bodyTilt = ref(0)
const loginCaptcha = ref(false)

const formInline = reactive({
  username: '',
  password: ''
})

const ruleInline = {
  username: [{ required: true, message: '请输入用户名', trigger: 'blur' }],
  password: [{ required: true, message: '请输入密码', trigger: 'blur' }]
}

const redirectPath = computed(() => {
  const value = route.query.redirect
  return Array.isArray(value) ? value[0] : value || ''
})

const otherQuery = computed(() => {
  return Object.entries(route.query).reduce((query, [key, value]) => {
    if (key !== 'redirect' && value !== undefined) {
      query[key] = value
    }
    return query
  }, {})
})

function handleMouseMove(e) {
  const centerX = window.innerWidth / 2
  const centerY = window.innerHeight / 2
  eyeOffset.x = (e.clientX - centerX) / centerX
  eyeOffset.y = (e.clientY - centerY) / centerY
  bodyTilt.value = ((e.clientX - centerX) / centerX) * 8
}

function getEyeWrapStyle() {
  return {}
}

function getPupilStyle(offset, index) {
  const eyeSizes = [18, 18, 16, 16, 14, 14, 13, 13]
  const r = eyeSizes[index] / 2
  const maxMove = r * (1 - 0.44)
  if (isPasswordVisible.value) {
    return {
      transform: `translate(calc(-50% + ${-maxMove}px), calc(-50% + 0px))`,
      transition: 'transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1)'
    }
  }
  const factor = 0.9 + (index % 3) * 0.05
  const px = offset.x * factor * maxMove
  const py = offset.y * factor * maxMove
  return {
    transform: `translate(calc(-50% + ${px}px), calc(-50% + ${py}px))`,
    transition: 'transform 0.12s ease-out'
  }
}

function getBodyStyle(charIndex) {
  const baseRotates = [-5, 0, 0, 0]
  const factors = [0.6, 0.8, 0.5, 0.7]
  const tilt = bodyTilt.value * factors[charIndex]
  return {
    transform: `rotate(${baseRotates[charIndex] + tilt}deg)`,
    transition: 'transform 0.25s ease-out'
  }
}

function handleInputFocus(type) {
  if (type === 'password' && showPassword.value) {
    isPasswordVisible.value = true
  }
}

function handleInputBlur() {}

function togglePassword() {
  showPassword.value = !showPassword.value
  isPasswordVisible.value = showPassword.value
}

function success(params) {
  void doLogin(params)
}

async function doLogin(params) {
  loading.value = true
  try {
    const data = await userStore.login({
      username: formInline.username,
      password: formInline.password,
      captchaType: 'blockPuzzle',
      captchaVerification: params ? params.captchaVerification : ''
    })
    if (data.isLogin === false) {
      loginCaptcha.value = data.login_captcha
      ElMessage.error(data.message || '登录失败')
      return
    }
    const path = settings.prefix ? '/' + settings.prefix + '/home' : '/home'
    await router.push({ path: redirectPath.value || path, query: otherQuery.value })
  } catch (error) {
    // 保持原有静默失败行为
  } finally {
    loading.value = false
  }
}

function handleSubmit() {
  if (loading.value) {
    return
  }
  formRef.value?.validate((valid) => {
    if (!valid) {
      return
    }
    if (loginCaptcha.value) {
      verifyRef.value?.show()
      return
    }
    void doLogin()
  })
}
</script>

<style scoped lang="stylus">
// =============================================
// 主容器
// =============================================
.login-page {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  min-height: 100vh;
  background: linear-gradient(135deg, #f5f7fa 0%, #e8eef5 100%);
  position: relative;
  overflow: hidden;
}

// =============================================
// 背景装饰圆圈（还原原版）
// =============================================
.bg-decoration {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  overflow: hidden;
  z-index: 0;

  .circle {
    position: absolute;
    border-radius: 50%;
    opacity: 0.08;

    &.circle-1 {
      width: 500px;
      height: 500px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      top: -200px;
      right: -100px;
      animation: float 20s ease-in-out infinite;
    }
    &.circle-2 {
      width: 350px;
      height: 350px;
      background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
      bottom: -100px;
      left: -80px;
      animation: float 25s ease-in-out infinite reverse;
    }
    &.circle-3 {
      width: 400px;
      height: 400px;
      background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      animation: pulse 15s ease-in-out infinite;
    }
    &.circle-4 {
      width: 280px;
      height: 280px;
      background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
      top: 15%;
      left: 10%;
      animation: float 22s ease-in-out infinite;
    }
    &.circle-5 {
      width: 320px;
      height: 320px;
      background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
      bottom: 20%;
      right: 15%;
      animation: pulse 18s ease-in-out infinite reverse;
    }
    &.circle-6 {
      width: 200px;
      height: 200px;
      background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
      top: 25%;
      right: 25%;
      animation: float 28s ease-in-out infinite reverse;
    }
    &.circle-7 {
      width: 240px;
      height: 240px;
      background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
      bottom: 35%;
      left: 20%;
      animation: pulse 20s ease-in-out infinite;
    }
  }
}

@keyframes float {
  0%, 100% { transform: translateY(0) translateX(0); }
  50% { transform: translateY(-30px) translateX(30px); }
}
@keyframes pulse {
  0%, 100% { transform: translate(-50%, -50%) scale(1); }
  50% { transform: translate(-50%, -50%) scale(1.1); }
}

// =============================================
// 登录卡片（居中浮层）
// =============================================
.login-card {
  position: relative;
  z-index: 1;
  width: 400px;
  max-width: calc(100vw - 32px);
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.10);
  overflow: visible;
  animation: slideUp 0.6s ease;
}

@keyframes slideUp {
  from { opacity: 0; transform: translateY(30px); }
  to   { opacity: 1; transform: translateY(0); }
}

// =============================================
// 顶部卡通角色条
// =============================================
.characters-bar {
  position: relative;
  height: 120px;
  background: linear-gradient(160deg, #2d2d44 0%, #1a1a2e 60%, #16213e 100%);
  border-radius: 16px 16px 0 0;
  overflow: visible;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  padding-bottom: 0;

  &::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 16px 16px 0 0;
    background: radial-gradient(ellipse at 30% 30%, rgba(103,65,170,0.2) 0%, transparent 60%),
    radial-gradient(ellipse at 70% 70%, rgba(64,158,255,0.1) 0%, transparent 50%);
  }
}

// =============================================
// 角色通用
// =============================================
.character {
  position: relative;
  flex-shrink: 0;
  display: flex;
  align-items: flex-end;
}

.char-face {
  position: absolute;
  display: flex;
  align-items: center;
}

.eye {
  position: relative;
  background: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  flex-shrink: 0;

  .pupil {
    position: absolute;
    background: #1a1a2e;
    border-radius: 50%;
    width: 44%;
    height: 44%;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    &::after {
      content: '';
      position: absolute;
      width: 30%;
      height: 30%;
      background: rgba(255,255,255,0.7);
      border-radius: 50%;
      top: 12%;
      left: 60%;
    }
  }
}

// =============================================
// 角色1：紫色高个子（层叠第2层）
// =============================================
.char-1 {
  z-index: 2;
  margin: 0 -4px;

  .body-1 {
    position: relative;
    width: 72px;
    height: 110px;
    background: linear-gradient(160deg, #7c5cbf 0%, #6441a5 50%, #5a3a9a 100%);
    border-radius: 14px 18px 6px 6px;
    box-shadow: 4px 0 16px rgba(100,65,165,0.4);
    transform-origin: bottom center;
  }

  .char-face {
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
    gap: 8px;
  }

  .eye { width: 18px; height: 18px; }

  .hands-cover {
    position: absolute;
    top: 12px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 2px;
    opacity: 0;
    transition: opacity 0.4s ease, top 0.4s ease;
    pointer-events: none;

    &.hands-visible { opacity: 1; top: 8px; }

    .hand {
      width: 16px;
      height: 18px;
      border: 1px solid rgba(255,255,255,0.2);
      &.hand-left  { background: linear-gradient(180deg, #9370db 0%, #7c5cbf 100%); border-radius: 5px 2px 3px 5px; }
      &.hand-right { background: linear-gradient(180deg, #9370db 0%, #7c5cbf 100%); border-radius: 2px 5px 5px 3px; }
    }
  }
}

// =============================================
// 角色2：黑色中等（层叠第3层）
// =============================================
.char-2 {
  z-index: 3;
  margin: 0 -4px;

  .body-2 {
    position: relative;
    width: 64px;
    height: 90px;
    background: linear-gradient(180deg, #3a3a3a 0%, #2a2a2a 60%, #1f1f1f 100%);
    border-radius: 10px 10px 5px 5px;
    box-shadow: 2px 0 12px rgba(0,0,0,0.5);
    transform-origin: bottom center;
  }

  .char-face {
    top: 16px;
    left: 50%;
    transform: translateX(-50%);
    gap: 7px;
  }

  .eye { width: 16px; height: 16px; }

  .hands-cover {
    position: absolute;
    top: 10px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 2px;
    opacity: 0;
    transition: opacity 0.4s ease, top 0.4s ease;
    pointer-events: none;

    &.hands-visible { opacity: 1; top: 6px; }

    .hand {
      width: 14px;
      height: 16px;
      border: 1px solid rgba(255,255,255,0.15);
      &.hand-left  { background: linear-gradient(180deg, #555 0%, #3a3a3a 100%); border-radius: 4px 2px 3px 4px; }
      &.hand-right { background: linear-gradient(180deg, #555 0%, #3a3a3a 100%); border-radius: 2px 4px 4px 3px; }
    }
  }
}

// =============================================
// 角色3：橙色半圆（最深层，层叠第1层）
// =============================================
.char-3 {
  z-index: 1;
  margin: 0 -4px;

  .body-3 {
    position: relative;
    width: 80px;
    height: 70px;
    background: linear-gradient(180deg, #f0a060 0%, #e8884a 50%, #d97030 100%);
    border-radius: 40px 40px 8px 8px;
    box-shadow: -2px 0 12px rgba(232,136,74,0.3);
    transform-origin: bottom center;
  }

  .char-face {
    top: 22px;
    left: 50%;
    transform: translateX(-50%);
    gap: 10px;
  }

  .eye { width: 14px; height: 14px; }

  .hands-cover.hands-cover-small {
    position: absolute;
    top: 14px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 2px;
    opacity: 0;
    transition: opacity 0.4s ease, top 0.4s ease;
    pointer-events: none;

    &.hands-visible { opacity: 1; top: 10px; }

    .hand {
      width: 13px;
      height: 15px;
      border: 1px solid rgba(255,255,255,0.15);
      &.hand-left  { background: linear-gradient(180deg, #f5b07a 0%, #e8884a 100%); border-radius: 4px 2px 2px 4px; }
      &.hand-right { background: linear-gradient(180deg, #f5b07a 0%, #e8884a 100%); border-radius: 2px 4px 4px 2px; }
    }
  }
}

// =============================================
// 角色4：黄色小矮个（最前层，层叠第4层）
// =============================================
.char-4 {
  z-index: 4;
  margin: 0 -4px;

  .body-4 {
    position: relative;
    width: 58px;
    height: 82px;
    background: linear-gradient(180deg, #f5d060 0%, #f0c040 50%, #e8b020 100%);
    border-radius: 40px 40px 5px 5px;
    box-shadow: 2px 0 10px rgba(240,192,64,0.3);
    transform-origin: bottom center;
  }

  .char-face-wrapper {
    position: absolute;
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
  }

  .face-4 {
    display: flex;
    flex-direction: row;
    gap: 8px;
    align-items: center;
  }

  .mouth-line {
    width: 18px;
    height: 2px;
    background: #c8900a;
    border-radius: 2px;
  }

  .eye-sm { width: 10px; height: 10px; }

  .hands-cover.hands-cover-xs {
    position: absolute;
    top: 12px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 2px;
    opacity: 0;
    transition: opacity 0.4s ease, top 0.4s ease;
    pointer-events: none;

    &.hands-visible { opacity: 1; top: 8px; }

    .hand {
      width: 12px;
      height: 14px;
      border: 1px solid rgba(255,255,255,0.15);
      &.hand-left  { background: linear-gradient(180deg, #f8de80 0%, #f0c040 100%); border-radius: 3px 2px 2px 3px; }
      &.hand-right { background: linear-gradient(180deg, #f8de80 0%, #f0c040 100%); border-radius: 2px 3px 3px 2px; }
    }
  }
}

// =============================================
// 表单区域
// =============================================
.form-area {
  padding: 28px 36px 32px;

  @media (max-width: 480px) {
    padding: 24px 20px 28px;
  }
}

// Logo区域
.logo-section {
  text-align: center;
  margin-bottom: 28px;

  .logo-image {
    width: 52px;
    height: 52px;
    margin-bottom: 12px;
    animation: fadeInDown 0.8s ease;
  }

  .system-title {
    font-size: 20px;
    font-weight: 600;
    color: #2c3e50;
    margin: 0 0 6px 0;
  }

  .system-desc {
    font-size: 13px;
    color: #909399;
    margin: 0;
  }
}

@keyframes fadeInDown {
  from { opacity: 0; transform: translateY(-12px); }
  to   { opacity: 1; transform: translateY(0); }
}

// 表单
.login-form {
  :deep(.el-form-item) {
    margin-bottom: 18px;
  }

  :deep(.el-form-item.submit-item) {
    margin-bottom: 0;
    margin-top: 22px;
  }

  .login-input {
    :deep(.el-input-group__prepend) {
      background: #f5f7fa;
      border: 1px solid #dcdfe6;
      border-right: none;
      padding: 0 12px;
      border-top-left-radius: 6px;
      border-bottom-left-radius: 6px;

      i { font-size: 16px; color: #909399; }
    }

    :deep(.el-input__inner) {
      height: 42px;
      line-height: 42px;
      font-size: 14px;
      border-radius: 0 6px 6px 0;
      border-left: none;
      padding: 0 36px 0 12px;
      transition: all 0.3s;

      &:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 2px rgba(102,126,234,0.12);
      }

      &::placeholder { color: #c0c4cc; }
    }

    :deep(.el-input__suffix) {
      display: flex;
      align-items: center;
      right: 10px;
    }
  }

  .password-toggle {
    font-size: 16px;
    color: #c0c4cc;
    cursor: pointer;
    transition: color 0.2s;

    &:hover, &.active { color: #667eea; }
  }
}

// 表单选项
.form-options {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0;

  :deep(.el-checkbox__label) {
    font-size: 13px;
    color: #606266;
  }

  .forgot-link {
    font-size: 13px;
    color: #667eea;
    text-decoration: none;
    transition: color 0.2s;

    &:hover { color: #764ba2; }
  }
}

// 登录按钮
.login-btn {
  width: 100%;
  height: 42px;
  font-size: 15px;
  font-weight: 500;
  border-radius: 6px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border: none;
  letter-spacing: 2px;
  transition: all 0.3s;
  box-shadow: 0 4px 12px rgba(102,126,234,0.3);

  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(102,126,234,0.4);
  }

  &:active { transform: translateY(0); }
}

// 底部版权
.footer {
  position: fixed;
  bottom: 0;
  left: 0;
  width: 100%;
  padding: 14px 20px;
  text-align: center;
  z-index: 10;

  .copyright-text {
    font-size: 12px;
    color: #909399;

    a {
      color: #667eea;
      text-decoration: none;
      margin-left: 3px;
      &:hover { color: #764ba2; }
    }
  }
}

// 响应式
@media (max-width: 480px) {
  .login-card { width: calc(100vw - 32px); }
}
</style>
