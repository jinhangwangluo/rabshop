<template>
  <div v-loading="loading" class="uploader-watermark-page">
    <el-card shadow="never" class="page-card">
      <div class="page-header">
        <div>
          <div class="page-header__title">图片水印配置</div>
          <div class="page-header__desc">统一维护四种上传存储参数和普通图片上传水印配置，保存后立即生效。</div>
        </div>
        <div class="page-header__actions">
          <el-button :icon="Refresh" :loading="loading" @click="fetchConfig">刷新</el-button>
          <el-button type="primary" :icon="Check" :loading="saving" @click="submitForm">保存</el-button>
        </div>
      </div>

      <el-alert
        :closable="false"
        class="page-alert"
        title="水印仅作用于普通图片上传链路，上传水印图片时会自动跳过处理。"
        type="info"
        show-icon
      />

      <el-form ref="formRef" :model="form" label-width="120px" class="config-form">
        <div class="section section--top">
          <el-form-item label="存储方式">
            <el-radio-group v-model="form.uploader_type" @change="handleStorageTypeChange">
              <el-radio
                v-for="item in storageOptions"
                :key="item.value"
                :label="item.value"
              >{{ item.label }}</el-radio>
            </el-radio-group>
          </el-form-item>
          <el-form-item label="开启缩略图">
            <el-switch v-model="form.thumbnail_enabled" :active-value="1" :inactive-value="0" />
          </el-form-item>
        </div>

        <el-tabs v-model="activeTab" class="setting-tabs">
          <el-tab-pane label="本地存储" name="local">
            <div class="tab-empty">
              <el-empty description="本地存储无需额外参数" :image-size="84" />
            </div>
          </el-tab-pane>

          <el-tab-pane label="七牛云存储" name="qiniu">
            <div class="tab-panel">
              <el-row :gutter="24">
                <el-col :span="12">
                  <el-form-item label="AccessKey">
                    <el-input v-model="form.qiniu_access_key" placeholder="请输入七牛云 AccessKey" />
                  </el-form-item>
                </el-col>
                <el-col :span="12">
                  <el-form-item label="SecretKey">
                    <el-input v-model="form.qiniu_secret_key" placeholder="请输入七牛云 SecretKey" show-password />
                  </el-form-item>
                </el-col>
              </el-row>
              <el-row :gutter="24">
                <el-col :span="12">
                  <el-form-item label="Bucket">
                    <el-input v-model="form.qiniu_name" placeholder="请输入七牛云 Bucket" />
                  </el-form-item>
                </el-col>
                <el-col :span="12">
                  <el-form-item label="区域">
                    <el-input v-model="form.qiniu_region" placeholder="请输入七牛云区域（如 z0/z2）" />
                  </el-form-item>
                </el-col>
              </el-row>
              <el-row :gutter="24">
                <el-col :span="12">
                  <el-form-item label="访问域名">
                    <el-input v-model="form.qiniu_url" placeholder="请输入七牛云访问域名" />
                  </el-form-item>
                </el-col>
              </el-row>
            </div>
          </el-tab-pane>

          <el-tab-pane label="阿里云存储" name="oss">
            <div class="tab-panel">
              <el-row :gutter="24">
                <el-col :span="12">
                  <el-form-item label="AccessKey">
                    <el-input v-model="form.oss_access_key" placeholder="请输入阿里云 AccessKey" />
                  </el-form-item>
                </el-col>
                <el-col :span="12">
                  <el-form-item label="SecretKey">
                    <el-input v-model="form.oss_secret_key" placeholder="请输入阿里云 SecretKey" show-password />
                  </el-form-item>
                </el-col>
              </el-row>
              <el-row :gutter="24">
                <el-col :span="12">
                  <el-form-item label="Bucket">
                    <el-input v-model="form.oss_name" placeholder="请输入阿里云 Bucket" />
                  </el-form-item>
                </el-col>
                <el-col :span="12">
                  <el-form-item label="区域">
                    <el-input v-model="form.oss_region" placeholder="请输入阿里云区域" />
                  </el-form-item>
                </el-col>
              </el-row>
              <el-row :gutter="24">
                <el-col :span="12">
                  <el-form-item label="访问域名">
                    <el-input v-model="form.oss_url" placeholder="请输入阿里云访问域名" />
                  </el-form-item>
                </el-col>
              </el-row>
            </div>
          </el-tab-pane>

          <el-tab-pane label="腾讯云存储" name="cos">
            <div class="tab-panel">
              <el-row :gutter="24">
                <el-col :span="12">
                  <el-form-item label="AccessKey">
                    <el-input v-model="form.cos_access_key" placeholder="请输入腾讯云 AccessKey" />
                  </el-form-item>
                </el-col>
                <el-col :span="12">
                  <el-form-item label="SecretKey">
                    <el-input v-model="form.cos_secret_key" placeholder="请输入腾讯云 SecretKey" show-password />
                  </el-form-item>
                </el-col>
              </el-row>
              <el-row :gutter="24">
                <el-col :span="12">
                  <el-form-item label="AppID">
                    <el-input v-model="form.cos_appid" placeholder="请输入腾讯云 AppID" />
                  </el-form-item>
                </el-col>
                <el-col :span="12">
                  <el-form-item label="Bucket">
                    <el-input v-model="form.cos_name" placeholder="请输入腾讯云 Bucket" />
                  </el-form-item>
                </el-col>
              </el-row>
              <el-row :gutter="24">
                <el-col :span="12">
                  <el-form-item label="区域">
                    <el-input v-model="form.cos_region" placeholder="请输入腾讯云区域" />
                  </el-form-item>
                </el-col>
                <el-col :span="12">
                  <el-form-item label="访问域名">
                    <el-input v-model="form.cos_url" placeholder="请输入腾讯云访问域名" />
                  </el-form-item>
                </el-col>
              </el-row>
              <el-row :gutter="24">
                <el-col :span="12">
                  <el-form-item label="CDN 域名">
                    <el-input v-model="form.cos_cdn" placeholder="请输入腾讯云 CDN 域名" />
                  </el-form-item>
                </el-col>
              </el-row>
            </div>
          </el-tab-pane>

          <el-tab-pane label="图片水印" name="watermark">
            <div class="tab-panel watermark-panel">
              <el-form-item label="是否开启水印" class="watermark-switch">
                <el-switch v-model="form.watermark_enabled" :active-value="1" :inactive-value="0" />
              </el-form-item>

              <div v-if="isWatermarkEnabled" class="watermark-detail">
                <el-form-item label="类型">
                  <el-radio-group v-model="form.watermark_type">
                    <el-radio
                      v-for="item in watermarkTypeOptions"
                      :key="item.value"
                      :label="item.value"
                    >{{ item.label }}</el-radio>
                  </el-radio-group>
                </el-form-item>

                <template v-if="form.watermark_type === 'image'">
                  <el-divider content-position="left">图片水印</el-divider>
                  <el-row :gutter="24">
                    <el-col :span="12">
                      <el-form-item label="水印图片">
                        <div class="watermark-upload">
                          <Upload v-model="form.watermark_image" :limit="1" :size="5" :file-type="watermarkFileTypes" />
                          <div class="watermark-upload__tip">点击选择水印图片，建议使用透明背景 PNG 图片</div>
                        </div>
                      </el-form-item>
                    </el-col>
                    <el-col :span="12">
                      <el-form-item label="透明度">
                        <el-input v-model="form.watermark_opacity" type="number" min="0" max="100" placeholder="请输入水印透明度">
                          <template #append>%</template>
                        </el-input>
                      </el-form-item>
                      <el-form-item label="倾斜度">
                        <el-input v-model="form.watermark_angle" type="number" min="-360" max="360" placeholder="请输入水印倾斜度">
                          <template #append>°</template>
                        </el-input>
                      </el-form-item>
                    </el-col>
                  </el-row>
                </template>

                <template v-else>
                  <el-divider content-position="left">文字水印</el-divider>
                  <el-row :gutter="24">
                    <el-col :span="12">
                      <el-form-item label="水印文字">
                        <el-input v-model="form.watermark_text" maxlength="60" show-word-limit placeholder="请输入水印文字" />
                      </el-form-item>
                    </el-col>
                    <el-col :span="12">
                      <el-form-item label="文字大小">
                        <el-input v-model="form.watermark_text_size" type="number" min="8" max="200" placeholder="请输入水印文字大小">
                          <template #append>px</template>
                        </el-input>
                      </el-form-item>
                    </el-col>
                  </el-row>
                  <el-row :gutter="24">
                    <el-col :span="12">
                      <el-form-item label="字体颜色">
                        <el-color-picker v-model="form.watermark_text_color" :predefine="['#000000', '#FFFFFF', '#FF4D4F', '#1D39C4']" />
                      </el-form-item>
                    </el-col>
                    <el-col :span="12">
                      <el-form-item label="字体旋转角度">
                        <el-input v-model="form.watermark_text_angle" type="number" min="-360" max="360" placeholder="请输入字体旋转角度">
                          <template #append>°</template>
                        </el-input>
                      </el-form-item>
                    </el-col>
                  </el-row>
                </template>

                <el-divider content-position="left">位置</el-divider>
                <el-form-item label="水印位置">
                  <div class="position-picker">
                    <div class="position-picker__grid">
                      <button
                        v-for="item in watermarkPositionOptions"
                        :key="item.value"
                        type="button"
                        class="position-picker__cell"
                        :class="{ 'is-active': form.watermark_position === item.value }"
                        :title="item.label"
                        @click="form.watermark_position = item.value"
                      />
                    </div>
                    <div class="position-picker__labels">
                      <span
                        v-for="item in watermarkPositionOptions"
                        :key="item.value + '-label'"
                        :class="{ 'is-active': form.watermark_position === item.value }"
                      >{{ item.label }}</span>
                    </div>
                  </div>
                </el-form-item>

                <el-row :gutter="24">
                  <el-col :span="12">
                    <el-form-item label="横坐标偏移">
                      <el-input v-model="form.watermark_x_offset" type="number" min="0" max="9999" placeholder="请输入水印横坐标偏移量">
                        <template #append>px</template>
                      </el-input>
                    </el-form-item>
                  </el-col>
                  <el-col :span="12">
                    <el-form-item label="纵坐标偏移">
                      <el-input v-model="form.watermark_y_offset" type="number" min="0" max="9999" placeholder="请输入水印纵坐标偏移量">
                        <template #append>px</template>
                      </el-input>
                    </el-form-item>
                  </el-col>
                </el-row>

                <el-divider content-position="left">效果展示</el-divider>
                <div class="watermark-preview-panel">
                  <div class="watermark-preview-stage">
                    <div class="watermark-preview-stage__mock">
                      <div class="mock-media-block" />
                      <div class="mock-content-lines">
                        <span />
                        <span />
                        <span />
                      </div>
                    </div>
                    <div class="watermark-preview-overlay" :style="previewWatermarkStyle">
                      <img
                        v-if="form.watermark_type === 'image' && previewWatermarkImage"
                        :src="previewWatermarkImage"
                        :style="previewImageStyle"
                        alt="水印效果"
                      >
                      <div
                        v-else-if="form.watermark_type === 'image'"
                        class="preview-image-placeholder"
                        :style="previewImageStyle"
                      >
                        <el-icon><Picture /></el-icon>
                      </div>
                      <span
                        v-else
                        class="preview-text-watermark"
                        :style="previewTextStyle"
                      >{{ previewText }}</span>
                    </div>
                  </div>
                </div>
              </div>
              <div v-else class="watermark-empty">
                <el-empty description="开启水印后展示图片/文字水印配置" :image-size="72" />
              </div>
            </div>
          </el-tab-pane>
        </el-tabs>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { computed, onBeforeMount, reactive, ref } from 'vue'
import { Check, Picture, Refresh } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import setting from '@/settings'
import Upload from '@/components/Upload'
import { getUploaderWatermarkConfigApi, saveUploaderWatermarkConfigApi } from '@/api/uploaderWatermark'

defineOptions({ name: 'UploaderWatermark' })

const loading = ref(false)
const saving = ref(false)
const activeTab = ref('local')
const storageOptions = ref([])
const watermarkTypeOptions = ref([])
const watermarkPositionOptions = ref([])
const watermarkFileTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp']
const formRef = ref(null)
const form = reactive(getDefaultForm())

const isWatermarkEnabled = computed(() => Number(form.watermark_enabled) === 1)
const previewWatermarkImage = computed(() => normalizePreviewUrl(form.watermark_image))
const previewText = computed(() => (form.watermark_text ? String(form.watermark_text) : '水印文字'))
const previewWatermarkStyle = computed(() => {
  const position = String(form.watermark_position || 'top_left')
  const xOffset = toPreviewOffset(form.watermark_x_offset)
  const yOffset = toPreviewOffset(form.watermark_y_offset)
  const angle = form.watermark_type === 'text'
    ? toNumber(form.watermark_text_angle, 0)
    : toNumber(form.watermark_angle, 0)
  const style = {
    transformOrigin: 'center center'
  }

  switch (position) {
    case 'top_center':
      style.top = `${yOffset}px`
      style.left = buildPreviewCenterPosition(xOffset)
      break
    case 'top_right':
      style.top = `${yOffset}px`
      style.right = `${xOffset}px`
      break
    case 'middle_left':
      style.top = buildPreviewCenterPosition(yOffset)
      style.left = `${xOffset}px`
      break
    case 'center':
      style.top = buildPreviewCenterPosition(yOffset)
      style.left = buildPreviewCenterPosition(xOffset)
      break
    case 'middle_right':
      style.top = buildPreviewCenterPosition(yOffset)
      style.right = `${xOffset}px`
      break
    case 'bottom_left':
      style.bottom = `${yOffset}px`
      style.left = `${xOffset}px`
      break
    case 'bottom_center':
      style.bottom = `${yOffset}px`
      style.left = buildPreviewCenterPosition(xOffset)
      break
    case 'bottom_right':
      style.bottom = `${yOffset}px`
      style.right = `${xOffset}px`
      break
    default:
      style.top = `${yOffset}px`
      style.left = `${xOffset}px`
      break
  }

  style.transform = buildPreviewTransform(position, angle)
  return style
})
const previewImageStyle = computed(() => ({
  opacity: Math.max(0, Math.min(100, toNumber(form.watermark_opacity, 80))) / 100
}))
const previewTextStyle = computed(() => ({
  color: form.watermark_text_color || '#000000',
  fontSize: `${toPreviewTextSize(form.watermark_text_size)}px`
}))

function getDefaultForm() {
  return {
    uploader_type: 'local',
    qiniu_access_key: '',
    qiniu_secret_key: '',
    qiniu_name: '',
    qiniu_region: '',
    qiniu_url: '',
    oss_access_key: '',
    oss_secret_key: '',
    oss_name: '',
    oss_region: '',
    oss_url: '',
    cos_access_key: '',
    cos_secret_key: '',
    cos_appid: '',
    cos_name: '',
    cos_region: '',
    cos_url: '',
    cos_cdn: '',
    watermark_enabled: 0,
    watermark_type: 'image',
    watermark_position: 'top_left',
    watermark_x_offset: 0,
    watermark_y_offset: 0,
    watermark_image: '',
    watermark_opacity: 80,
    watermark_angle: 0,
    watermark_text: '',
    watermark_text_size: 16,
    watermark_text_color: '#000000',
    watermark_text_angle: 0,
    thumbnail_enabled: 0
  }
}

function normalizeFormConfig(config) {
  const nextForm = Object.assign(getDefaultForm(), config || {})
  const numericKeys = [
    'watermark_enabled',
    'watermark_x_offset',
    'watermark_y_offset',
    'watermark_opacity',
    'watermark_angle',
    'watermark_text_size',
    'watermark_text_angle',
    'thumbnail_enabled'
  ]

  numericKeys.forEach((key) => {
    if (nextForm[key] === '' || nextForm[key] === null || typeof nextForm[key] === 'undefined') {
      return
    }
    nextForm[key] = Number(nextForm[key])
  })

  nextForm.watermark_enabled = Number(nextForm.watermark_enabled) === 1 ? 1 : 0
  nextForm.thumbnail_enabled = Number(nextForm.thumbnail_enabled) === 1 ? 1 : 0
  nextForm.watermark_image = normalizePreviewUrl(nextForm.watermark_image)

  return nextForm
}

async function fetchConfig() {
  loading.value = true
  try {
    const res = await getUploaderWatermarkConfigApi()
    const data = res.data || {}
    Object.assign(form, normalizeFormConfig(data.config || {}))
    storageOptions.value = data.options && data.options.storage_options ? data.options.storage_options : []
    watermarkTypeOptions.value = data.options && data.options.watermark_type_options ? data.options.watermark_type_options : []
    watermarkPositionOptions.value = data.options && data.options.watermark_position_options ? data.options.watermark_position_options : []
    if (form.uploader_type) {
      activeTab.value = form.uploader_type
    }
  } catch (error) {
    ElMessage.error(error.message || '获取配置失败')
  } finally {
    loading.value = false
  }
}

async function submitForm() {
  saving.value = true
  try {
    const res = await saveUploaderWatermarkConfigApi({ ...form })
    ElMessage.success(res.message || '保存成功')
    const data = res.data || {}
    Object.assign(form, normalizeFormConfig(data.config || form))
    storageOptions.value = data.options && data.options.storage_options ? data.options.storage_options : storageOptions.value
    watermarkTypeOptions.value = data.options && data.options.watermark_type_options ? data.options.watermark_type_options : watermarkTypeOptions.value
    watermarkPositionOptions.value = data.options && data.options.watermark_position_options ? data.options.watermark_position_options : watermarkPositionOptions.value
  } catch (error) {
    ElMessage.error(error.message || '保存失败')
  } finally {
    saving.value = false
  }
}

function handleStorageTypeChange(value) {
  if (value) {
    activeTab.value = value
  }
}

function toNumber(value, defaultValue) {
  if (value === '' || value === null || typeof value === 'undefined' || Number.isNaN(Number(value))) {
    return defaultValue
  }
  return Number(value)
}

function toPreviewOffset(value) {
  const offset = toNumber(value, 0)
  return Math.max(0, Math.min(120, offset))
}

function toPreviewTextSize(value) {
  const size = toNumber(value, 16)
  return Math.max(12, Math.min(64, size))
}

function buildPreviewCenterPosition(offset) {
  return offset > 0 ? `calc(50% + ${offset}px)` : '50%'
}

function buildPreviewTransform(position, angle) {
  const transforms = []

  if (['top_center', 'center', 'bottom_center'].includes(position)) {
    transforms.push('translateX(-50%)')
  }
  if (['middle_left', 'center', 'middle_right'].includes(position)) {
    transforms.push('translateY(-50%)')
  }
  if (angle !== 0) {
    transforms.push(`rotate(${angle}deg)`)
  }

  return transforms.length ? transforms.join(' ') : 'none'
}

function normalizePreviewUrl(path) {
  if (!path) {
    return ''
  }
  if (/^https?:\/\//i.test(path) || path.indexOf('//') === 0) {
    return path
  }
  if (path.indexOf('/') === 0) {
    return getSiteOrigin() + path
  }
  return getSiteOrigin() + '/' + path
}

function getSiteOrigin() {
  const baseUri = String(setting.baseUri || '')
  if (baseUri) {
    return baseUri.replace(/\/api\/admin\/?$/, '')
  }
  if (typeof window !== 'undefined' && window.location && window.location.origin) {
    return window.location.origin
  }
  return ''
}

onBeforeMount(() => {
  fetchConfig()
})
</script>

<style lang="scss" scoped>
.uploader-watermark-page {
  min-height: calc(100vh - 100px);
}

.page-card {
  border-radius: 8px;

  :deep(.el-card__body) {
    padding: 24px;
  }
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 16px;

  &__title {
    color: var(--dashboard-text-primary);
    font-size: 18px;
    font-weight: 600;
    line-height: 1.4;
  }

  &__desc {
    margin-top: 8px;
    color: var(--dashboard-text-muted);
    font-size: 13px;
    line-height: 1.7;
  }

  &__actions {
    display: flex;
    align-items: center;
    gap: 12px;
  }
}

.page-alert {
  margin-bottom: 20px;
}

.config-form {
  :deep(.el-form-item__label) {
    color: var(--dashboard-text-primary);
  }
}

.section {
  display: flex;
  flex-wrap: wrap;
  gap: 0 36px;
  align-items: flex-end;
  padding: 8px 0 4px;

  &--top {
    margin-bottom: 12px;
  }
}

.setting-tabs {
  margin-top: 8px;

  :deep(.el-tabs__header) {
    margin-bottom: 16px;
  }
}

.tab-panel {
  padding: 6px 2px 0;
}

.tab-empty {
  padding: 16px 0 8px;
}

.watermark-panel {
  padding-top: 4px;
}

.watermark-detail {
  padding-top: 4px;
}

.watermark-empty {
  display: flex;
  justify-content: center;
  padding: 20px 0 8px;
}

.watermark-switch {
  margin-bottom: 8px;
}

.watermark-upload {
  display: flex;
  flex-direction: column;
  gap: 10px;

  &__tip {
    color: var(--dashboard-text-muted);
    font-size: 12px;
    line-height: 1.6;
  }
}

.position-picker {
  display: inline-flex;
  flex-direction: column;
  gap: 10px;
}

.position-picker__grid {
  display: grid;
  width: 108px;
  height: 108px;
  grid-template-columns: repeat(3, 1fr);
  grid-template-rows: repeat(3, 1fr);
  border: 1px solid var(--dashboard-border);
  border-radius: 6px;
  overflow: hidden;
  background: var(--dashboard-surface);

  .position-picker__cell {
    padding: 0;
    border: 1px solid var(--dashboard-border);
    background: var(--dashboard-surface);
    cursor: pointer;
    transition: background-color 0.2s ease, border-color 0.2s ease;
  }

  .position-picker__cell:hover {
    background: rgba(64, 158, 255, 0.08);
  }

  .position-picker__cell.is-active {
    background: rgba(64, 158, 255, 0.18);
    border-color: #409eff;
  }
}

.position-picker__labels {
  display: grid;
  width: 108px;
  grid-template-columns: repeat(3, 1fr);
  gap: 4px 0;
  color: var(--dashboard-text-muted);
  font-size: 12px;
  line-height: 1.4;
  text-align: center;

  span.is-active {
    color: var(--prev-color-primary);
    font-weight: 600;
  }
}

.watermark-preview-panel {
  padding: 2px 0 8px;
}

.watermark-preview-stage {
  position: relative;
  width: 100%;
  max-width: 680px;
  min-height: 260px;
  overflow: hidden;
  border: 1px solid var(--dashboard-border);
  border-radius: 8px;
  background: var(--dashboard-surface);
}

.watermark-preview-stage__mock {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  gap: 24px;
  padding: 28px;
  background: var(--dashboard-surface);
}

.mock-media-block {
  width: 188px;
  height: 126px;
  flex-shrink: 0;
  border: 1px solid var(--dashboard-border);
  border-radius: 8px;
  background: var(--dashboard-surface-muted);
}

.mock-content-lines {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 14px;

  span {
    display: block;
    height: 12px;
    border-radius: 12px;
    background: var(--dashboard-surface-muted);
  }

  span:nth-child(1) {
    width: 78%;
  }

  span:nth-child(2) {
    width: 62%;
  }

  span:nth-child(3) {
    width: 46%;
  }
}

.watermark-preview-overlay {
  position: absolute;
  z-index: 2;
  display: inline-flex;
  max-width: 70%;
  align-items: center;
  justify-content: center;
  pointer-events: none;

  img {
    display: block;
    max-width: 168px;
    max-height: 108px;
    object-fit: contain;
  }
}

.preview-image-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 118px;
  height: 76px;
  border: 1px dashed var(--dashboard-border);
  border-radius: 6px;
  color: var(--dashboard-text-muted);
  background: rgba(255, 255, 255, 0.72);
  font-size: 28px;
}

.preview-text-watermark {
  display: inline-block;
  max-width: 100%;
  overflow-wrap: break-word;
  font-weight: 600;
  line-height: 1.2;
  text-shadow: 0 1px 2px rgba(255, 255, 255, 0.65);
  white-space: nowrap;
}

.watermark-switch :deep(.el-form-item__content) {
  display: flex;
  align-items: center;
  min-height: 32px;
}

:deep(.el-input-number),
:deep(.el-input) {
  width: 100%;
}
</style>
