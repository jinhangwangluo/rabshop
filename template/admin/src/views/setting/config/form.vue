<template>
  <div class="form-render-container">
    <!-- 页面头部 -->
    <div class="form-header">
      <div class="header-left">
        <span class="header-title">{{ configName || '表单填写' }}</span>
        <span class="header-desc">{{ configDesc || '' }}</span>
      </div>
      <div class="header-right">
        <el-button type="primary" icon="el-icon-check" :loading="submitting" @click="handleSubmit">提交</el-button>
      </div>
    </div>

    <!-- 表单内容区域 -->
    <div class="form-body">

      <div v-if="formFields.length === 0" class="empty-state">
        <el-empty description="暂无表单配置" :image-size="120" />
      </div>

      <el-form
        v-else
        ref="formRef"
        :model="formData"
        :rules="formRules"
        label-width="120px"
        label-position="right"
        class="dynamic-form"
      >
        <template v-for="field in formFields">
          <!-- 普通表单字段 -->
          <el-form-item
            v-if="!['card', 'alert', 'tabs'].includes(field.type) && isFieldVisible(field)"
            :key="field._id"
            :label="field.props.label || field.label"
            :prop="field.props.field"
          >
            <!-- 文本框 -->
            <div v-if="field.type === 'input'" class="input-with-actions">
              <el-input
                v-model="formData[field.props.field]"
                :placeholder="field.props.placeholder"
                :maxlength="field.props.maxlength"
                :disabled="!!field.props.disabled"
                clearable
              />
              <div v-if="field.props.showCopy || field.props.showRandom || field.props.fetchEnabled" class="input-actions">
                <el-button
                  v-if="field.props.fetchEnabled"
                  size="mini"
                  icon="el-icon-download"
                  :loading="fetchLoadingMap[getFetchLoadingKey(field)]"
                  @click="handleFetchAndFill(field)"
                >
                  {{ getFetchButtonText(field) }}
                </el-button>
                <el-button
                  v-if="field.props.showCopy"
                  size="mini"
                  icon="el-icon-document-copy"
                  @click="handleCopyText(field)"
                >
                  复制
                </el-button>
                <el-button
                  v-if="field.props.showRandom"
                  size="mini"
                  icon="el-icon-refresh"
                  @click="handleGenerateRandom(field)"
                >
                  随机生成
                </el-button>
              </div>
            </div>

            <!-- 多行文本 -->
            <el-input
              v-else-if="field.type === 'textarea'"
              v-model="formData[field.props.field]"
              type="textarea"
              :rows="field.props.rows"
              :placeholder="field.props.placeholder"
              clearable
            />

            <!-- 数字 -->
            <el-input-number
              v-else-if="field.type === 'number'"
              v-model="formData[field.props.field]"
              :min="field.props.min"
              :max="field.props.max"
              :step="field.props.step"
              controls-position="right"
              style="width: 100%"
            />

            <!-- 开关 -->
            <el-switch
              v-else-if="field.type === 'switch'"
              v-model="formData[field.props.field]"
              :active-text="field.props.activeText || '开启'"
              :inactive-text="field.props.inactiveText || '关闭'"
              :active-value="getSwitchActiveValue(field)"
              :inactive-value="getSwitchInactiveValue(field)"
              :disabled="!!field.props.disabled"
            />

            <!-- 单选 -->
            <el-radio-group v-else-if="field.type === 'radio'" v-model="formData[field.props.field]">
              <el-radio v-for="opt in field.props.options" :key="opt.value" :label="opt.value">
                {{ opt.label }}
              </el-radio>
            </el-radio-group>

            <!-- 多选 -->
            <el-checkbox-group v-else-if="field.type === 'checkbox'" v-model="formData[field.props.field]">
              <el-checkbox v-for="opt in field.props.options" :key="opt.value" :label="opt.value">
                {{ opt.label }}
              </el-checkbox>
            </el-checkbox-group>

            <!-- 上传 -->
            <div v-else-if="field.type === 'upload'" class="upload-wrapper">
              <div class="upload-file-list">
                <div
                  v-for="(fileItem, fileIndex) in (formData[field.props.field] || [])"
                  :key="fileItem.uid || fileIndex"
                  class="upload-file-item"
                >
                  <template v-if="isImageFile(fileItem)">
                    <img :src="fileItem.url" class="file-thumb-img">
                  </template>
                  <template v-else>
                    <div class="file-thumb-icon">
                      <LegacyIcon name="el-icon-document" />
                      <span class="file-name" :title="fileItem.fileName">{{ fileItem.fileName }}</span>
                    </div>
                  </template>
                  <div class="file-item-mask">
                    <LegacyIcon name="el-icon-view" @click.stop="handleCustomUploadShow(fileItem)" />
                    <LegacyIcon name="el-icon-delete" @click.stop="handleCustomUploadRemove(field, fileIndex)" />
                  </div>
                </div>
                <button
                  v-if="canAppendUpload(field) && isImageUploadField(field)"
                  type="button"
                  class="upload-trigger"
                  @click="handleOpenImagePicker(field)"
                >
                  <span class="upload-trigger-btn">
                    <LegacyIcon name="el-icon-plus" />
                  </span>
                </button>
                <el-upload
                  v-else-if="canAppendUpload(field)"
                  :action="uploadAction"
                  :headers="uploadHeaders"
                  :auto-upload="true"
                  :show-file-list="false"
                  :accept="field.props.accept"
                  :on-success="(response, file, fileList) => handleUploadSuccess(response, file, fileList, field)"
                  :on-error="handleUploadError"
                  :before-upload="beforeUpload"
                  class="upload-trigger"
                >
                  <div class="upload-trigger-btn">
                    <LegacyIcon name="el-icon-plus" />
                  </div>
                </el-upload>
              </div>
            </div>

            <!-- 说明文字 -->
            <div v-if="field.props.description" class="form-field-description" v-html="renderSafeDescription(field.props.description)" />
          </el-form-item>

          <!-- 卡片容器 -->
          <template v-else-if="field.type === 'card'">
            <el-card :key="field._id" :shadow="field.props.shadow || 'hover'" class="form-card-item">
              <template #header>
                <div>{{ field.props.title || '卡片标题' }}</div>
              </template>

              <template v-if="field.props.children && field.props.children.length > 0">
                <template v-for="child in field.props.children">
                  <el-form-item
                    v-if="!['alert'].includes(child.type) && isFieldVisible(child)"
                    :key="child._id"
                    :label="child.props.label || child.label"
                    :prop="child.props.field"
                  >
                    <!-- 文本框 -->
                    <div v-if="child.type === 'input'" class="input-with-actions">
                      <el-input
                        v-model="formData[child.props.field]"
                        :placeholder="child.props.placeholder"
                        :maxlength="child.props.maxlength"
                        :disabled="!!child.props.disabled"
                        clearable
                      />
                      <div v-if="child.props.showCopy || child.props.showRandom || child.props.fetchEnabled" class="input-actions">
                        <el-button
                          v-if="child.props.fetchEnabled"
                          size="mini"
                          icon="el-icon-download"
                          :loading="fetchLoadingMap[getFetchLoadingKey(child)]"
                          @click="handleFetchAndFill(child)"
                        >
                          {{ getFetchButtonText(child) }}
                        </el-button>
                        <el-button v-if="child.props.showCopy" size="mini" icon="el-icon-document-copy" @click="handleCopyText(child)">复制</el-button>
                        <el-button v-if="child.props.showRandom" size="mini" icon="el-icon-refresh" @click="handleGenerateRandom(child)">随机生成</el-button>
                      </div>
                    </div>

                    <!-- 多行文本 -->
                    <el-input v-else-if="child.type === 'textarea'" v-model="formData[child.props.field]" type="textarea" :rows="child.props.rows" :placeholder="child.props.placeholder" clearable />

                    <!-- 数字 -->
                    <el-input-number v-else-if="child.type === 'number'" v-model="formData[child.props.field]" :min="child.props.min" :max="child.props.max" :step="child.props.step" controls-position="right" style="width: 100%" />

                    <!-- 开关 -->
                    <el-switch
                      v-else-if="child.type === 'switch'"
                      v-model="formData[child.props.field]"
                      :active-text="child.props.activeText || '开启'"
                      :inactive-text="child.props.inactiveText || '关闭'"
                      :active-value="getSwitchActiveValue(child)"
                      :inactive-value="getSwitchInactiveValue(child)"
                      :disabled="!!child.props.disabled"
                    />

                    <!-- 单选 -->
                    <el-radio-group v-else-if="child.type === 'radio'" v-model="formData[child.props.field]">
                      <el-radio v-for="opt in child.props.options" :key="opt.value" :label="opt.value">{{ opt.label }}</el-radio>
                    </el-radio-group>

                    <!-- 多选 -->
                    <el-checkbox-group v-else-if="child.type === 'checkbox'" v-model="formData[child.props.field]">
                      <el-checkbox v-for="opt in child.props.options" :key="opt.value" :label="opt.value">{{ opt.label }}</el-checkbox>
                    </el-checkbox-group>

                    <!-- 上传 -->
                    <div v-else-if="child.type === 'upload'" class="upload-wrapper">
                      <div class="upload-file-list">
                        <div v-for="(fileItem, fileIndex) in (formData[child.props.field] || [])" :key="fileIndex" class="upload-file-item">
                          <template v-if="isImageFile(fileItem)">
                            <img :src="fileItem.url" class="file-thumb-img">
                          </template>
                          <template v-else>
                            <div class="file-thumb-icon">
                              <LegacyIcon name="el-icon-document" />
                              <span class="file-name" :title="fileItem.fileName">{{ fileItem.fileName }}</span>
                            </div>
                          </template>
                          <div class="file-item-mask">
                            <LegacyIcon name="el-icon-view" @click.stop="handleCustomUploadShow(fileItem)" />
                            <LegacyIcon name="el-icon-delete" @click.stop="handleCustomUploadRemove(child, fileIndex)" />
                          </div>
                        </div>
                        <button
                          v-if="canAppendUpload(child) && isImageUploadField(child)"
                          type="button"
                          class="upload-trigger"
                          @click="handleOpenImagePicker(child)"
                        >
                          <span class="upload-trigger-btn"><LegacyIcon name="el-icon-plus" /></span>
                        </button>
                        <el-upload
                          v-else-if="canAppendUpload(child)"
                          :action="uploadAction"
                          :headers="uploadHeaders"
                          :auto-upload="true"
                          :show-file-list="false"
                          :accept="child.props.accept"
                          :on-success="(response, file, fileList) => handleUploadSuccess(response, file, fileList, child)"
                          :on-error="handleUploadError"
                          :before-upload="beforeUpload"
                          class="upload-trigger"
                        >
                          <div class="upload-trigger-btn"><LegacyIcon name="el-icon-plus" /></div>
                        </el-upload>
                      </div>
                    </div>

                    <!-- 说明文字 -->
                    <div v-if="child.props.description && child.type !== 'alert'" class="form-field-description" v-html="renderSafeDescription(child.props.description)" />
                  </el-form-item>

                  <!-- 警告提示 -->
                  <el-alert
                    v-else-if="child.type === 'alert'"
                    :key="child._id + '_alert'"
                    :title="child.props.title || '提示'"
                    :type="child.props.alertType || 'info'"
                    :description="child.props.description"
                    :closable="false"
                    show-icon
                    style="margin-bottom: 18px"
                  />
                </template>
              </template>

              <p v-else class="card-empty-content">{{ field.props.content || '暂无内容' }}</p>
            </el-card>
          </template>

          <!-- Tabs 标签页容器 -->
          <template v-else-if="field.type === 'tabs'">
            <div :key="field._id" class="form-tabs-item">
              <el-tabs v-model="tabsActiveMap[field._id]" :type="field.props.tabType || ''">
                <el-tab-pane
                  v-for="tab in field.props.tabs"
                  :key="tab._id"
                  :label="tab.name"
                  :name="tab._id"
                >
                  <template v-for="child in (tab.children || [])">
                    <el-form-item
                      v-if="!['alert'].includes(child.type) && isFieldVisible(child)"
                      :key="child._id"
                      :label="child.props.label || child.label"
                      :prop="child.props.field"
                    >
                      <!-- 文本框 -->
                      <div v-if="child.type === 'input'" class="input-with-actions">
                        <el-input v-model="formData[child.props.field]" :placeholder="child.props.placeholder" :maxlength="child.props.maxlength" :disabled="!!child.props.disabled" clearable />
                        <div v-if="child.props.showCopy || child.props.showRandom || child.props.fetchEnabled" class="input-actions">
                          <el-button
                            v-if="child.props.fetchEnabled"
                            size="mini"
                            icon="el-icon-download"
                            :loading="fetchLoadingMap[getFetchLoadingKey(child)]"
                            @click="handleFetchAndFill(child)"
                          >
                            {{ getFetchButtonText(child) }}
                          </el-button>
                          <el-button v-if="child.props.showCopy" size="mini" icon="el-icon-document-copy" @click="handleCopyText(child)">复制</el-button>
                          <el-button v-if="child.props.showRandom" size="mini" icon="el-icon-refresh" @click="handleGenerateRandom(child)">随机生成</el-button>
                        </div>
                      </div>

                      <!-- 多行文本 -->
                      <el-input v-else-if="child.type === 'textarea'" v-model="formData[child.props.field]" type="textarea" :rows="child.props.rows" :placeholder="child.props.placeholder" clearable />

                      <!-- 数字 -->
                      <el-input-number v-else-if="child.type === 'number'" v-model="formData[child.props.field]" :min="child.props.min" :max="child.props.max" :step="child.props.step" controls-position="right" style="width: 100%" />

                      <!-- 开关 -->
                      <el-switch
                        v-else-if="child.type === 'switch'"
                        v-model="formData[child.props.field]"
                        :active-text="child.props.activeText || '开启'"
                        :inactive-text="child.props.inactiveText || '关闭'"
                        :active-value="getSwitchActiveValue(child)"
                        :inactive-value="getSwitchInactiveValue(child)"
                        :disabled="!!child.props.disabled"
                      />

                      <!-- 单选 -->
                      <el-radio-group v-else-if="child.type === 'radio'" v-model="formData[child.props.field]">
                        <el-radio v-for="opt in child.props.options" :key="opt.value" :label="opt.value">{{ opt.label }}</el-radio>
                      </el-radio-group>

                      <!-- 多选 -->
                      <el-checkbox-group v-else-if="child.type === 'checkbox'" v-model="formData[child.props.field]">
                        <el-checkbox v-for="opt in child.props.options" :key="opt.value" :label="opt.value">{{ opt.label }}</el-checkbox>
                      </el-checkbox-group>

                      <!-- 上传 -->
                      <div v-else-if="child.type === 'upload'" class="upload-wrapper">
                        <div class="upload-file-list">
                          <div v-for="(fileItem, fileIndex) in (formData[child.props.field] || [])" :key="fileIndex" class="upload-file-item">
                            <template v-if="isImageFile(fileItem)">
                              <img :src="fileItem.url" class="file-thumb-img">
                            </template>
                            <template v-else>
                              <div class="file-thumb-icon">
                                <LegacyIcon name="el-icon-document" />
                                <span class="file-name" :title="fileItem.fileName">{{ fileItem.fileName }}</span>
                              </div>
                            </template>
                            <div class="file-item-mask">
                              <LegacyIcon name="el-icon-view" @click.stop="handleCustomUploadShow(fileItem)" />
                              <LegacyIcon name="el-icon-delete" @click.stop="handleCustomUploadRemove(child, fileIndex)" />
                            </div>
                          </div>
                          <button
                            v-if="canAppendUpload(child) && isImageUploadField(child)"
                            type="button"
                            class="upload-trigger"
                            @click="handleOpenImagePicker(child)"
                          >
                            <span class="upload-trigger-btn"><LegacyIcon name="el-icon-plus" /></span>
                          </button>
                          <el-upload
                            v-else-if="canAppendUpload(child)"
                            :action="uploadAction"
                            :headers="uploadHeaders"
                            :auto-upload="true"
                            :show-file-list="false"
                            :accept="child.props.accept"
                            :on-success="(response, file, fileList) => handleUploadSuccess(response, file, fileList, child)"
                            :on-error="handleUploadError"
                            :before-upload="beforeUpload"
                            class="upload-trigger"
                          >
                            <div class="upload-trigger-btn"><LegacyIcon name="el-icon-plus" /></div>
                          </el-upload>
                        </div>
                      </div>

                      <!-- 说明文字 -->
                      <div v-if="child.props.description" class="form-field-description" v-html="renderSafeDescription(child.props.description)" />
                    </el-form-item>

                    <!-- 警告提示 -->
                    <el-alert
                      v-else-if="child.type === 'alert'"
                      :key="child._id + '_alert'"
                      :title="child.props.title || '提示'"
                      :type="child.props.alertType || 'info'"
                      :description="child.props.description"
                      :closable="false"
                      show-icon
                      style="margin-bottom: 18px"
                    />
                  </template>
                </el-tab-pane>
              </el-tabs>
            </div>
          </template>

          <!-- 警告提示 -->
          <el-alert
            v-else-if="field.type === 'alert'"
            :key="field._id + '_alert'"
            :title="field.props.title || '提示'"
            :type="field.props.alertType || 'info'"
            :description="field.props.description"
            :closable="false"
            show-icon
            class="form-alert-item"
          />
        </template>

      </el-form>
    </div>

  </div>
</template>

<script setup>
import { getCurrentInstance, nextTick, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { configSaveApi } from '@/api/system'
import request from '@/utils/request'
import { getToken } from '@/utils/auth'
import setting from '@/settings'
import { getConfigTemplateFindApi } from '@/api/setting'
import { useTagsViewStore } from '@/store/modules/tagsView'
import { renderSafeDescription } from '@/utils/renderDescription'
import { usePageSlots, slotAction, validateForm, operationResult, trackPageSlotAction } from '@/utils/pageSlots'
import { dynamicSlotFields } from '@/utils/pageSlots/dynamicFields'

defineOptions({ name: 'ConfigFormRender' })

const route = useRoute()
const configRouteName = route.name
let loadedConfigId = null
const router = useRouter()
const tagsViewStore = useTagsViewStore()
const instance = getCurrentInstance()
const proxy = instance && instance.proxy

const configId = ref(null)
const configName = ref('')
const configDesc = ref('')
const loading = ref(false)
const submitting = ref(false)
const formFields = reactive([])
const formData = reactive({})
const formRules = reactive({})
const tabsActiveMap = reactive({})
const fetchLoadingMap = reactive({})
const imagePreviewVisible = ref(false)
const previewImageUrl = ref('')
const formRef = ref(null)
const formReady = ref(false)
let configLoadSequence = 0
const handleSubmit = trackPageSlotAction('save', saveConfigForm)

usePageSlots({
  page: () => ({ id: 'setting.config.form', title: configName.value || '配置表单', description: configDesc.value || '填写并保存系统配置', entityId: configId.value, mode: 'edit' }),
  identity: () => route.params.id,
  ready: () => formReady.value && !!formRef.value,
  busy: () => loading.value || submitting.value,
  fields: () => dynamicSlotFields(collectAllFields(formFields), formData, isFieldVisible, field => [getSwitchActiveValue(field), getSwitchInactiveValue(field)]),
  actions: {
    validate: slotAction('校验配置表单', () => validateForm(formRef.value)),
    save: slotAction('保存配置表单', handleSubmit),
    reset: slotAction('重置表单', () => { handleReset(); return { ok: true } }),
    close: slotAction('返回配置列表', async() => { await handleBack(); return { ok: true } })
  }
})

const uploadAction = setting.baseUri + '/setting/file/upload'
const uploadHeaders = {
  'X-Requested-With': 'XMLHttpRequest',
  Authorization: 'Bearer ' + getToken()
}

function resetReactiveObject(target, source = {}) {
  Object.keys(target).forEach(key => {
    delete target[key]
  })
  Object.assign(target, source)
}

function setLoading(value) {
  loading.value = value
}

function setSubmitting(value) {
  submitting.value = value
}

function setFetchLoading(key, value) {
  if (key) fetchLoadingMap[key] = value
}

function isFetchEnabled(field) {
  return !!(field && field.props && field.props.fetchEnabled && field.props.fetchRequest && field.props.fetchRequest.url)
}

function getFetchButtonText(field) {
  if (!field || !field.props) return '获取'
  const text = field.props.fetchButtonText
  return text !== undefined && text !== null && String(text).trim() !== '' ? String(text) : '获取'
}

function getFetchLoadingKey(field) {
  return field && (field._id || (field.props && field.props.field)) ? (field._id || field.props.field) : ''
}

function getSwitchActiveValue(field) {
  const props = field && field.props ? field.props : {}
  return props.activeValue !== undefined && props.activeValue !== null ? props.activeValue : 1
}

function getSwitchInactiveValue(field) {
  const props = field && field.props ? field.props : {}
  return props.inactiveValue !== undefined && props.inactiveValue !== null ? props.inactiveValue : 0
}

function isSameLinkageValue(left, right) {
  if (left === right) return true
  if (left === undefined || left === null || right === undefined || right === null) return false
  return String(left) === String(right)
}

function parseJsonOrEmpty(text) {
  if (text === undefined || text === null) return {}
  const raw = String(text).trim()
  if (!raw) return {}
  return JSON.parse(raw)
}

function getValueByPath(obj, path) {
  if (obj === undefined || obj === null) return undefined
  if (path === undefined || path === null || String(path).trim() === '') {
    return obj.data
  }

  let p = String(path).trim()
  p = p.replace(/\[(\d+)\]/g, '.$1')
  p = p.replace(/\[['"]([^'"]+)['"]\]/g, '.$1')
  p = p.replace(/^\./, '')

  const parts = p.split('.').filter(Boolean)
  let cur = obj
  for (const key of parts) {
    if (cur === undefined || cur === null) return undefined
    cur = cur[key]
  }
  return cur
}

async function handleFetchAndFill(field) {
  if (!isFetchEnabled(field)) {
    ElMessage.warning('未配置接口回显')
    return
  }

  const loadingKey = getFetchLoadingKey(field)
  if (loadingKey && fetchLoadingMap[loadingKey]) return

  const req = field.props.fetchRequest || {}
  const url = req.url
  const method = (req.method || 'get').toLowerCase()

  if (!url) {
    ElMessage.error('请求地址不能为空')
    return
  }

  let params = {}
  let data = {}

  try {
    params = parseJsonOrEmpty(req.paramsJson)
    data = parseJsonOrEmpty(req.dataJson)
  } catch (e) {
    ElMessage.error('请求参数 JSON 格式错误')
    return
  }

  setFetchLoading(loadingKey, true)
  try {
    const config = { url, method }
    if (method === 'get' || method === 'delete') {
      config.params = params
    } else {
      config.data = data
    }

    const res = await request(config)
    const value = getValueByPath(res, field.props.fetchValuePath)
    if (value === undefined) {
      ElMessage.error('接口返回未找到指定路径')
      return
    }
    formData[field.props.field] = value
    ElMessage.success('获取成功')
  } catch (e) {
    ElMessage.error(e.message || '获取失败')
  } finally {
    setFetchLoading(loadingKey, false)
  }
}

async function loadFormConfig() {
  const loadSequence = ++configLoadSequence
  const id = configId.value
  formReady.value = false
  setLoading(true)
  try {
    const res = await getConfigTemplateFindApi(id)
    if (loadSequence !== configLoadSequence) return
    const config = res.data
    if (config) {
      if (config.config_name) {
        configName.value = config.config_name
        configDesc.value = config.config_desc || ''
        tagsViewStore.updateVisitedView({
          ...route,
          title: config.config_name
        })
        document.title = config.config_name
      }
      if (config.content) {
        const fields = typeof config.content === 'string'
          ? JSON.parse(config.content)
          : config.content
        if (Array.isArray(fields)) {
          formFields.splice(0, formFields.length, ...fields)
          initFormData(fields)
          if (config.configData && Object.keys(config.configData).length > 0) {
            Object.keys(config.configData).forEach(key => {
              formData[key] = config.configData[key]
            })
          }
          buildFormRules(fields)
          initTabsActive(fields)
          formReady.value = true
          loadedConfigId = id
        }
      }
    }
  } catch (e) {
    if (loadSequence !== configLoadSequence) return
    ElMessage.error(e.message || '加载表单配置失败')
  } finally {
    if (loadSequence === configLoadSequence) setLoading(false)
  }
}

function initTabsActive(fields) {
  const activeMap = {}
  const walk = (list) => {
    list.forEach(f => {
      if (f.type === 'tabs' && f.props.tabs && f.props.tabs.length > 0) {
        activeMap[f._id] = f.props.tabs[0]._id
      }
      if (f.type === 'card' && f.props.children) {
        walk(f.props.children)
      }
    })
  }
  walk(fields)
  resetReactiveObject(tabsActiveMap, activeMap)
}

function collectAllFields(fields) {
  const result = []
  fields.forEach(f => {
    result.push(f)
    if (f.type === 'card' && Array.isArray(f.props.children)) {
      result.push(...collectAllFields(f.props.children))
    }
    if (f.type === 'tabs' && Array.isArray(f.props.tabs)) {
      f.props.tabs.forEach(tab => {
        result.push(...collectAllFields(tab.children || []))
      })
    }
  })
  return result
}

function isFieldVisible(field) {
  if (!field.props || !field.props.field) return true
  const allFields = collectAllFields(formFields)
  const controllerFields = allFields.filter(f => ['radio', 'switch'].includes(f.type) && f.props.linkage && f.props.linkage.length > 0)
  for (const controllerField of controllerFields) {
    const enabledRules = controllerField.props.linkage.filter(rule => rule && rule.enabled)
    if (enabledRules.length === 0) continue
    const controlledFields = new Set()
    enabledRules.forEach(rule => {
      if (Array.isArray(rule.showFields)) {
        rule.showFields.forEach(f => controlledFields.add(f))
      }
    })
    if (!controlledFields.has(field.props.field)) continue
    const currentValue = formData[controllerField.props.field]
    const rule = enabledRules.find(item => isSameLinkageValue(item.value, currentValue))
    if (rule) {
      if (!Array.isArray(rule.showFields) || !rule.showFields.includes(field.props.field)) {
        return false
      }
    }
  }
  return true
}

function initFormData(fields) {
  const data = {}
  const initField = (field) => {
    if (field.props && field.props.field) {
      if (field.type === 'checkbox') {
        data[field.props.field] = Array.isArray(field.props.defaultValue) && field.props.defaultValue.length > 0
          ? [...field.props.defaultValue]
          : []
      } else if (field.type === 'number') {
        data[field.props.field] = field.props.defaultValue !== null && field.props.defaultValue !== undefined
          ? field.props.defaultValue
          : (field.props.min || 0)
      } else if (field.type === 'switch') {
        data[field.props.field] = field.props.defaultValue !== null && field.props.defaultValue !== undefined
          ? field.props.defaultValue
          : getSwitchInactiveValue(field)
      } else if (field.type === 'upload') {
        data[field.props.field] = []
      } else {
        data[field.props.field] = field.props.defaultValue !== undefined ? field.props.defaultValue : ''
      }
    }
    if (field.type === 'card' && field.props.children) {
      field.props.children.forEach(child => initField(child))
    }
    if (field.type === 'tabs' && field.props.tabs) {
      field.props.tabs.forEach(tab => {
        (tab.children || []).forEach(child => initField(child))
      })
    }
  }
  fields.forEach(field => initField(field))
  resetReactiveObject(formData, data)
}

function buildFormRules(fields) {
  const rules = {}
  const buildRules = (field) => {
    if (field.props && field.props.field && field.props.required) {
      const rule = { required: true, message: `请填写${field.props.label || field.label}`, trigger: 'blur' }
      if (field.type === 'checkbox') {
        rule.type = 'array'
        rule.message = `请选择${field.props.label || field.label}`
        rule.trigger = 'change'
      } else if (field.type === 'radio') {
        rule.trigger = 'change'
      } else if (field.type === 'switch') {
        rule.trigger = 'change'
      } else if (field.type === 'upload') {
        rule.type = 'array'
        rule.message = `请上传${field.props.label || field.label}`
        rule.trigger = 'change'
      }
      rules[field.props.field] = [rule]
    }
    if (field.type === 'card' && field.props.children) {
      field.props.children.forEach(child => buildRules(child))
    }
    if (field.type === 'tabs' && field.props.tabs) {
      field.props.tabs.forEach(tab => {
        (tab.children || []).forEach(child => buildRules(child))
      })
    }
  }
  fields.forEach(field => buildRules(field))
  resetReactiveObject(formRules, rules)
}

async function saveConfigForm(args, context) {
  if (submitting.value) return { ok: false, code: 'BUSY', message: '正在提交' }
  const id = configId.value
  const form = formRef.value
  const validation = await validateForm(form)
  if (!validation.ok) {
    ElMessage.warning('请检查表单填写是否正确')
    return validation
  }
  context.assertCurrent()
  setSubmitting(true)
  try {
    const submitData = { ...formData }
    Object.keys(submitData).forEach(key => {
      if (Array.isArray(submitData[key]) && submitData[key].length > 0 && submitData[key][0].raw) {
        submitData[key] = submitData[key].map(file => ({
          name: file.name,
          url: file.url || ''
        }))
      }
    })

    const res = await configSaveApi({
      id,
      form_data: submitData
    })
    ElMessage.success(res.message || '提交成功')
    return { ok: true }
  } catch (e) {
    ElMessage.error(e.message || '提交失败')
    return operationResult(e)
  } finally {
    setSubmitting(false)
  }
}

function handleReset() {
  formRef.value?.resetFields?.()
  ElMessage.success('表单已重置')
}

function handleBack() {
  return router.push({ name: 'system_config' })
}

function handleCopyText(field) {
  const text = formData[field.props.field] || ''
  if (!text) {
    ElMessage.warning('没有可复制的内容')
    return
  }
  const textarea = document.createElement('textarea')
  textarea.value = text
  document.body.appendChild(textarea)
  textarea.select()
  document.execCommand('copy')
  document.body.removeChild(textarea)
  ElMessage.success('已复制到剪贴板')
}

function handleGenerateRandom(field) {
  const length = field.props.randomLength || 16
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'
  let result = ''
  for (let i = 0; i < length; i++) {
    result += chars.charAt(Math.floor(Math.random() * chars.length))
  }
  formData[field.props.field] = result
  ElMessage.success('随机字符串已生成')
}

function handleCustomUploadShow(file) {
  proxy?.$imageAmplify?.(file.url)
}

function canAppendUpload(field) {
  const props = field && field.props ? field.props : {}
  const list = Array.isArray(formData[props.field]) ? formData[props.field] : []
  const limit = Number(props.limit || 0)
  return !limit || list.length < limit
}

function isImageUploadField(field) {
  const props = field && field.props ? field.props : {}
  const accept = props.accept === undefined || props.accept === null ? 'image/*' : String(props.accept)
  const normalizedAccept = accept.trim().toLowerCase()
  return normalizedAccept === '' || normalizedAccept.indexOf('image') !== -1
}

function getRemainingUploadLimit(field) {
  const props = field && field.props ? field.props : {}
  const list = Array.isArray(formData[props.field]) ? formData[props.field] : []
  const limit = Number(props.limit || 0)
  return limit > 0 ? Math.max(limit - list.length, 1) : 1
}

function handleOpenImagePicker(field) {
  if (!field || !field.props || !field.props.field) {
    return
  }
  if (!proxy?.$imgModal) {
    ElMessage.error('图片选择组件未初始化')
    return
  }

  proxy.$imgModal((images) => {
    const pickedImages = normalizePickedImages(images)
    if (pickedImages.length === 0) {
      return
    }

    const fieldKey = field.props.field
    const currentList = Array.isArray(formData[fieldKey]) ? [...formData[fieldKey]] : []
    const limit = Number(field.props.limit || 0)
    const nextList = currentList.concat(pickedImages)
    formData[fieldKey] = limit > 0 ? nextList.slice(0, limit) : nextList
    nextTick(() => {
      formRef.value?.validateField?.(fieldKey)
    })
  }, getRemainingUploadLimit(field))
}

function normalizePickedImages(images) {
  const list = Array.isArray(images) ? images : [images]
  return list.map(item => normalizePickedImage(item)).filter(Boolean)
}

function normalizePickedImage(image) {
  if (!image) {
    return null
  }

  const url = getPickedImageUrl(image)
  if (!url) {
    return null
  }

  return {
    fileName: getPickedImageName(image, url),
    url
  }
}

function getPickedImageUrl(image) {
  if (typeof image === 'string') {
    return image
  }
  return image.path || image.filePath || image.file_url || image.att_dir || image.url || ''
}

function getPickedImageName(image, url) {
  if (typeof image === 'object') {
    const name = image.real_name || image.name || image.fileName || image.original_name || ''
    if (name) {
      return name
    }
  }
  const path = String(url || '').split('?')[0]
  const basename = path.split('/').filter(Boolean).pop()
  return basename || 'image'
}

function beforeUpload(file) {
  const isLt10M = file.size / 1024 / 1024 < 5
  if (!isLt10M) {
    ElMessage.error('上传文件大小不能超过 5MB!')
    return false
  }
  return true
}

function handleUploadSuccess(response, file, fileList, field) {
  if (response.status === 200) {
    const uploadedFile = {
      fileName: file.name,
      url: response.data.filePath
    }
    const currentList = formData[field.props.field] || []
    const index = currentList.findIndex(item => item.fileName === file.name)
    if (index > -1) {
      currentList[index] = uploadedFile
    } else {
      currentList.push(uploadedFile)
    }
    formData[field.props.field] = [...currentList]
    ElMessage.success('上传成功')
  } else {
    ElMessage.error(response.message || '上传失败')
    const currentList = formData[field.props.field] || []
    const index = currentList.findIndex(item => item.uid === file.uid)
    if (index > -1) {
      currentList.splice(index, 1)
      formData[field.props.field] = [...currentList]
    }
  }
}

function handleUploadError(err, file, fileList) {
  ElMessage.error('上传失败: ' + err.message)
}

function isImageFile(file) {
  const imageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/svg+xml']
  if (file.type) return imageTypes.includes(file.type)
  const ext = (file.name || file.url || '').split('.').pop().toLowerCase()
  return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'].includes(ext)
}

function handleCustomUploadRemove(field, index) {
  const list = [...(formData[field.props.field] || [])]
  list.splice(index, 1)
  formData[field.props.field] = list
}

watch(() => [route.name, route.params.id], () => {
  if (route.name !== configRouteName) {
    if (loading.value) {
      ++configLoadSequence
      setLoading(false)
      formReady.value = false
    }
    return
  }
  if (loadedConfigId === route.params.id && formReady.value) return
  configId.value = route.params.id || null
  configName.value = route.query.name || ''
  formReady.value = false
  formFields.splice(0)
  resetReactiveObject(formData)
  resetReactiveObject(formRules)
  if (configId.value) {
    loadFormConfig()
  }
}, { immediate: true, flush: 'sync' })
</script>

<style scoped lang="scss">
.form-render-container {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 84px);
  background: var(--dashboard-bg);
}

// 页面头部
.form-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 20px;
  background: var(--dashboard-surface);
  border-bottom: 1px solid var(--dashboard-border);
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
  border-radius: 6px;

  .header-left {
    display: flex;
    align-items: center;
    gap: 8px;

    .header-title {
      font-size: 16px;
      font-weight: 600;
      color: var(--dashboard-text-primary);
      margin-bottom: 0;
    }

    .header-desc {
      color: var(--dashboard-text-muted);
    }
  }

  .header-right {
    display: flex;
    gap: 8px;
  }
}

// 表单内容区域
.form-body {
  flex: 1;
  overflow-y: auto;
  padding: 20px 0 0;

  .form-card {
    margin: 0 auto;
    border-radius: 8px;

    :deep(.el-card__body) {
      padding: 32px 40px;
    }
  }

  .empty-state {
    padding: 60px 0;
  }
}

// 动态表单样式
.dynamic-form {
  .el-form-item {
    margin-bottom: 24px;
  }

  // 文本框带操作按钮
  .input-with-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;

    .el-input {
      flex: 1;
      min-width: 0;
    }

    .input-actions {
      display: flex;
      gap: 8px;
      flex-shrink: 0;
      white-space: nowrap;

      .el-button {
        margin-left: 0;
      }
    }
  }

  // 上传组件
  .upload-wrapper {
    .upload-file-list {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: flex-start;
    }

    .upload-file-item {
      position: relative;
      width: 80px;
      height: 80px;
      border-radius: 6px;
      overflow: hidden;
      border: 1px solid var(--dashboard-border);
      background: var(--dashboard-surface-muted);
      cursor: pointer;
      flex-shrink: 0;

      &:hover .file-item-mask {
        opacity: 1;
      }

      .file-thumb-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
      }

      .file-thumb-icon {
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 4px;
        padding: 6px;

        i {
          font-size: 28px;
          color: var(--ds-primary);
        }

        .file-name {
          font-size: 11px;
          color: var(--dashboard-text-secondary);
          word-break: break-all;
          text-align: center;
          overflow: hidden;
          display: -webkit-box;
          -webkit-line-clamp: 2;
          -webkit-box-orient: vertical;
          line-height: 1.3;
        }
      }

      .file-item-mask {
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: space-around;
        opacity: 0;
        transition: opacity 0.2s;

        i {
          font-size: 18px;
          color: #fff;
          cursor: pointer;

          &:hover {
            color: #f56c6c;
          }
        }
      }
    }

    .upload-trigger {
      flex-shrink: 0;
      padding: 0;
      font: inherit;
      background: transparent;
      border: 0;

      :deep(.el-upload) {
        display: block;
      }

      .upload-trigger-btn {
        width: 80px;
        height: 80px;
        border: 1px dashed var(--dashboard-border);
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--dashboard-surface-muted);
        cursor: pointer;
        transition: all 0.2s;

        &:hover {
          border-color: var(--ds-primary);
          background: var(--ds-surface-container-highest);

          i {
            color: var(--ds-primary);
          }
        }

        i {
          font-size: 24px;
          color: #c0c4cc;
        }
      }
    }
  }

  // 卡片容器
  .form-card-item {
    margin-bottom: 24px;
    border-radius: 6px;

    :deep(.el-card__header) {
      padding: 14px 20px;
      font-size: 14px;
      font-weight: 600;
      color: var(--dashboard-text-primary);
      background: var(--dashboard-surface-muted);
      border-bottom: 1px solid var(--dashboard-border);
    }

    :deep(.el-card__body) {
      padding: 20px;
    }

    .card-empty-content {
      color: var(--dashboard-text-muted);
      font-size: 14px;
      text-align: center;
      padding: 20px 0;
    }

    .el-form-item {
      margin-bottom: 20px;

      &:last-child {
        margin-bottom: 0;
      }
    }
  }

  // Tabs 标签页容器
  .form-tabs-item {
    margin-bottom: 24px;
    background: var(--dashboard-surface);
    border-radius: 6px;
    border: 1px solid var(--dashboard-border);
    overflow: hidden;
    padding:10px;

    :deep(.el-tabs) {
      .el-tabs__header {
        margin-bottom: 0;
      }

      .el-tabs__content {
        padding: 20px;
      }
    }

    .el-form-item {
      margin-bottom: 20px;

      &:last-child {
        margin-bottom: 0;
      }
    }
  }

  // 警告提示
  .form-alert-item {
    margin-bottom: 20px;
  }

  // 说明文字
  .form-field-description {
    margin-top: 6px;
    font-size: 12px;
    color: var(--dashboard-text-muted);
    line-height: 1.5;
    display: block;

    :deep(a) {
      color: var(--ds-primary);
      text-decoration: none;
      word-break: break-all;

      &:hover {
        text-decoration: underline;
      }
    }

    i {
      font-size: 13px;
      margin-top: 1px;
      flex-shrink: 0;
      color: var(--dashboard-text-muted);
    }
  }

  // 提交按钮区域
  .form-submit-area {
    margin-top: 32px;
    padding-top: 24px;
    border-top: 1px solid var(--dashboard-border);

    .el-button {
      padding: 10px 24px;
    }
  }
}

// 响应式适配
@media screen and (max-width: 768px) {
  .form-body {
    padding: 12px;

    .form-card {
      :deep(.el-card__body) {
        padding: 20px;
      }
    }
  }

  .dynamic-form {
    .el-form-item {
      :deep(.el-form-item__label) {
        float: none;
        display: block;
        text-align: left;
        margin-bottom: 8px;
      }
    }

    .input-with-actions {
      flex-wrap: wrap;

      .input-actions {
        width: 100%;
        justify-content: flex-start;
      }
    }
  }
}
</style>
