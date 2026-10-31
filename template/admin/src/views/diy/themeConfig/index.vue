<template>
  <div class="theme-config">
    <div class="theme-config__header">
      <div>
        <div class="theme-config__title">主题换色</div>
        <div class="theme-config__desc">当前主题颜色共 {{ colorCount }} 种：主色、辅助色、渐变色。</div>
      </div>
      <div class="theme-config__actions">
        <el-button :loading="loading" @click="fetchConfig">刷新</el-button>
        <el-button type="primary" :loading="submitting" @click="handleSubmit">保存主题</el-button>
      </div>
    </div>

    <div v-loading="loading" class="theme-config__body">
      <section class="theme-section">
        <div class="theme-section__head">
          <div>
            <div class="theme-section__title">一键换色</div>
            <div class="theme-section__desc">选择预设后，会同步替换主色、辅助色、渐变色。</div>
          </div>
          <el-tag type="info">{{ currentThemeName }}</el-tag>
        </div>
        <div class="theme-presets">
          <button
            v-for="item in presets"
            :key="item.key"
            type="button"
            class="theme-presets__item"
            :class="{ 'theme-presets__item--active': form.theme_key === item.key }"
            @click="handleApplyPreset(item)"
          >
            <span class="theme-presets__swatches">
              <span class="theme-presets__swatch" :style="{ backgroundColor: item.primary_color }" />
              <span class="theme-presets__swatch" :style="{ backgroundColor: item.secondary_color }" />
              <span class="theme-presets__swatch" :style="{ background: item.gradient_color }" />
            </span>
            <span class="theme-presets__content">
              <span class="theme-presets__name">{{ item.name }}</span>
              <span class="theme-presets__desc">{{ item.description }}</span>
            </span>
          </button>
        </div>
      </section>

      <section class="theme-section">
        <div class="theme-section__head">
          <div>
            <div class="theme-section__title">颜色设置</div>
            <div class="theme-section__desc">只维护三种主题颜色，移动端会按这三项派生页面变量。</div>
          </div>
        </div>
        <el-form label-width="90px" class="theme-form">
          <el-form-item label="主色">
            <div class="theme-field">
              <el-color-picker v-model="form.primary_color" />
              <el-input v-model="form.primary_color" class="theme-field__input" />
            </div>
          </el-form-item>
          <el-form-item label="辅助色">
            <div class="theme-field">
              <el-color-picker v-model="form.secondary_color" />
              <el-input v-model="form.secondary_color" class="theme-field__input" />
            </div>
          </el-form-item>
          <el-form-item label="渐变色">
            <el-input v-model="form.gradient_color" />
          </el-form-item>
        </el-form>
      </section>

      <section class="theme-section theme-section--preview">
        <div class="theme-section__head">
          <div>
            <div class="theme-section__title">移动端预览</div>
            <div class="theme-section__desc">预览使用当前待保存色值生成。</div>
          </div>
        </div>
        <div class="mobile-preview" :style="previewStyle">
          <div class="mobile-preview__hero">
            <div class="mobile-preview__title">知识商城</div>
            <div class="mobile-preview__subtitle">主题换色预览</div>
          </div>
          <div class="mobile-preview__card">
            <div class="mobile-preview__card-title">精品课程</div>
            <div class="mobile-preview__card-desc">主按钮使用主色，标签使用辅助色，头部使用渐变色。</div>
            <div class="mobile-preview__button">立即查看</div>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeMount, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { getThemeConfigApi, saveThemeConfigApi } from '@/api/diy'

defineOptions({ name: 'ThemeConfig' })

const loading = ref(false)
const submitting = ref(false)
const colorCount = ref(3)
const presets = ref([])
const form = reactive({
  theme_key: 'orange',
  primary_color: '#ea580c',
  secondary_color: '#fb923c',
  gradient_color: 'linear-gradient(135deg, #ea580c 0%, #fb923c 100%)'
})

const currentThemeName = computed(() => {
  const preset = presets.value.find(item => item.key === form.theme_key)
  return preset ? preset.name : '自定义主题'
})

const previewStyle = computed(() => ({
  '--preview-primary': form.primary_color || '#ea580c',
  '--preview-secondary': form.secondary_color || '#fb923c',
  '--preview-gradient': form.gradient_color || 'linear-gradient(135deg, #ea580c 0%, #fb923c 100%)'
}))

onBeforeMount(() => {
  fetchConfig()
})

async function fetchConfig() {
  loading.value = true
  try {
    const res = await getThemeConfigApi()
    const data = res.data || {}
    presets.value = Array.isArray(data.presets) ? data.presets : []
    colorCount.value = Number(data.color_count || 3)
    applyConfig(data.config || {})
  } catch (error) {
    ElMessage.error(error.message || '主题配置加载失败')
  } finally {
    loading.value = false
  }
}

function applyConfig(config = {}) {
  form.theme_key = config.theme_key || config.key || 'orange'
  form.primary_color = config.primary_color || '#ea580c'
  form.secondary_color = config.secondary_color || '#fb923c'
  form.gradient_color = config.gradient_color || 'linear-gradient(135deg, #ea580c 0%, #fb923c 100%)'
}

function handleApplyPreset(preset) {
  if (!preset) {
    return
  }
  applyConfig({
    ...preset,
    theme_key: preset.key
  })
}

async function handleSubmit() {
  submitting.value = true
  try {
    const res = await saveThemeConfigApi({
      theme_key: form.theme_key,
      primary_color: form.primary_color,
      secondary_color: form.secondary_color,
      gradient_color: form.gradient_color
    })
    const data = res.data || {}
    if (data.config) {
      applyConfig(data.config)
    }
    ElMessage.success(res.message || '保存成功')
  } catch (error) {
    ElMessage.error(error.message || '保存失败')
  } finally {
    submitting.value = false
  }
}
</script>

<style lang="scss" scoped>
.theme-config__header,
.theme-section {
  border-radius: 6px;
  background: var(--dashboard-surface, #ffffff);
}

.theme-config__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
}

.theme-config__title {
  font-size: 20px;
  line-height: 28px;
  font-weight: 700;
  color: var(--dashboard-text-primary, #303133);
}

.theme-config__desc,
.theme-section__desc,
.theme-presets__desc,
.mobile-preview__subtitle,
.mobile-preview__card-desc {
  color: var(--dashboard-text-muted, #909399);
}

.theme-config__desc {
  margin-top: 6px;
  font-size: 13px;
}

.theme-config__actions {
  display: flex;
  align-items: center;
}

.theme-config__actions :deep(.el-button + .el-button) {
  margin-left: 10px;
}

.theme-config__body {
  margin-top: 16px;
}

.theme-section {
  padding: 20px 24px;
  margin-bottom: 16px;
}

.theme-section__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}

.theme-section__title {
  font-size: 16px;
  line-height: 24px;
  font-weight: 700;
  color: var(--dashboard-text-primary, #303133);
}

.theme-section__desc {
  margin-top: 4px;
  font-size: 13px;
}

.theme-presets {
  display: flex;
  flex-wrap: wrap;
  margin: -6px;
}

.theme-presets__item {
  display: flex;
  align-items: center;
  width: 248px;
  min-height: 82px;
  margin: 6px;
  padding: 14px;
  border: 1px solid var(--dashboard-border, #ebeef5);
  border-radius: 6px;
  background: #ffffff;
  cursor: pointer;
  text-align: left;
}

.theme-presets__item--active {
  border-color: var(--el-color-primary, #409eff);
  box-shadow: 0 0 0 2px rgba(64, 158, 255, 0.12);
}

.theme-presets__swatches {
  display: flex;
  flex-shrink: 0;
}

.theme-presets__swatch {
  width: 28px;
  height: 28px;
  border: 2px solid #ffffff;
  border-radius: 50%;
}

.theme-presets__swatch + .theme-presets__swatch {
  margin-left: -8px;
}

.theme-presets__content {
  display: flex;
  min-width: 0;
  flex-direction: column;
  margin-left: 12px;
}

.theme-presets__name {
  font-size: 14px;
  line-height: 22px;
  font-weight: 700;
  color: var(--dashboard-text-primary, #303133);
}

.theme-presets__desc {
  margin-top: 4px;
  font-size: 12px;
  line-height: 18px;
}

.theme-form {
  max-width: 640px;
}

.theme-field {
  display: flex;
  align-items: center;
}

.theme-field__input {
  width: 180px;
  margin-left: 10px;
}

.mobile-preview {
  width: 360px;
  border-radius: 6px;
  background: #f9f9f9;
  overflow: hidden;
}

.mobile-preview__hero {
  padding: 22px 20px;
  background: var(--preview-gradient);
}

.mobile-preview__title {
  font-size: 20px;
  line-height: 28px;
  font-weight: 700;
  color: #ffffff;
}

.mobile-preview__subtitle {
  margin-top: 4px;
  font-size: 13px;
  color: rgba(255, 255, 255, 0.82);
}

.mobile-preview__card {
  margin: 16px;
  padding: 18px;
  border-radius: 6px;
  background: #ffffff;
}

.mobile-preview__card-title {
  font-size: 16px;
  line-height: 24px;
  font-weight: 700;
  color: #18181b;
}

.mobile-preview__card-desc {
  margin-top: 8px;
  font-size: 13px;
}

.mobile-preview__button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 34px;
  margin-top: 14px;
  padding: 0 18px;
  border-radius: 999px;
  background: var(--preview-primary);
  color: #ffffff;
  font-size: 13px;
}
</style>
