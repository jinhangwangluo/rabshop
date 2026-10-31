<template>
  <div class="form-render-container">
    <!-- 页面头部 -->
    <div class="form-header">
      <div class="header-left">
        <span class="header-title">{{ configName || '表单填写' }}</span>
        <span class="header-desc">{{ configDesc || '' }}</span>
      </div>
      <div class="header-right">
        <el-button type="primary" class="btn-curator-primary" :icon="Check" :loading="submitting" @click="handleSubmit">提交</el-button>
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
              <div v-if="field.props.showCopy || field.props.showRandom" class="input-actions">
                <el-button
                  v-if="field.props.showCopy"
                  size="mini"
                  :icon="DocumentCopy"
                  @click="handleCopyText(field)"
                >
                  复制
                </el-button>
                <el-button
                  v-if="field.props.showRandom"
                  size="mini"
                  :icon="Refresh"
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
                <el-upload
                  v-if="!field.props.limit || (formData[field.props.field] || []).length < field.props.limit"
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
            <div v-if="field.props.description" class="form-field-description">
              {{ field.props.description }}
            </div>
          </el-form-item>

          <!-- 卡片容器 -->
          <template v-else-if="field.type === 'card'">
            <el-card :key="field._id" :shadow="field.props.shadow || 'hover'" class="form-card-item">
              <template #header>
                <div class="form-card-item__header">{{ field.props.title || '卡片标题' }}</div>
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
                      <div v-if="child.props.showCopy || child.props.showRandom" class="input-actions">
                        <el-button v-if="child.props.showCopy" size="mini" :icon="DocumentCopy" @click="handleCopyText(child)">复制</el-button>
                        <el-button v-if="child.props.showRandom" size="mini" :icon="Refresh" @click="handleGenerateRandom(child)">随机生成</el-button>
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
                        <el-upload
                          v-if="!child.props.limit || (formData[child.props.field] || []).length < child.props.limit"
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
                    <div v-if="child.props.description && child.type !== 'alert'" class="form-field-description">{{ child.props.description }}</div>
                  </el-form-item>

                  <!-- 警告提示 -->
                  <el-alert
                    v-else-if="child.type === 'alert'"
                    :key="child._id"
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
                        <div v-if="child.props.showCopy || child.props.showRandom" class="input-actions">
                          <el-button v-if="child.props.showCopy" size="mini" :icon="DocumentCopy" @click="handleCopyText(child)">复制</el-button>
                          <el-button v-if="child.props.showRandom" size="mini" :icon="Refresh" @click="handleGenerateRandom(child)">随机生成</el-button>
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
                          <el-upload
                            v-if="!child.props.limit || (formData[child.props.field] || []).length < child.props.limit"
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
                      <div v-if="child.props.description" class="form-field-description">{{ child.props.description }}</div>
                    </el-form-item>

                    <!-- 警告提示 -->
                    <el-alert
                      v-else-if="child.type === 'alert'"
                      :key="child._id"
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
            :key="field._id"
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
import { configSaveApi } from '@/api/system'
import { getToken } from '@/utils/auth'
import setting from '@/settings'
import { getConfigTemplateFindApi } from '@/api/setting'
import { Check, DocumentCopy, Refresh } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useTagsViewStore } from '@/store/modules/tagsView'
import { imageAmplify } from '@/utils/index'

defineOptions({
  name: 'ConfigFormRender'
})

const route = useRoute()
const router = useRouter()
const tagsViewStore = useTagsViewStore()

const configId = ref(route.params.id || null)
const configName = ref(route.query.name || '')
const configDesc = ref('')
const loading = ref(false)
const submitting = ref(false)
// 表单字段配置
const formFields = ref([])
// 表单数据
const formData = ref({})
// 表单验证规则
const formRules = ref({})
// tabs 激活状态 { [tabsFieldId]: activeTabId }
const tabsActiveMap = ref({})
// 图片预览
const imagePreviewVisible = ref(false)
const previewImageUrl = ref('')
// 上传配置
const uploadAction = setting.baseUri + '/setting/file/upload'
const uploadHeaders = {
  'X-Requested-With': 'XMLHttpRequest',
  'Authorization': 'Bearer ' + getToken()
}
const formRef = ref(null)

onMounted(() => {
  if (configId.value) {
    loadFormConfig()
  }
})

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

// 加载表单配置
async function loadFormConfig() {
  loading.value = true
  try {
    const res = await getConfigTemplateFindApi(configId.value)
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
          formFields.value = fields
          // 先用默认值初始化，再用已保存数据覆盖，保证所有字段都有初始值
          initFormData(fields)
          if (config.configData && Object.keys(config.configData).length > 0) {
            // 已保存数据覆盖默认值，但只覆盖已存在的 key，缺失的字段保留默认值
            Object.keys(config.configData).forEach(key => {
              formData.value[key] = config.configData[key]
            })
          }
          buildFormRules(fields)
          initTabsActive(fields)
        }
      }
    }
  } catch (e) {
    ElMessage.error(e.message || '加载表单配置失败')
  } finally {
    loading.value = false
  }
}

// 初始化 tabs 激活状态
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
  tabsActiveMap.value = activeMap
}

// 收集所有叶子字段
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

// 判断字段是否应该显示（根据联动规则）
function isFieldVisible(field) {
  if (!field.props || !field.props.field) return true
  const allFields = collectAllFields(formFields.value)
  const controllerFields = allFields.filter(f => ['radio', 'switch'].includes(f.type) && f.props.linkage && f.props.linkage.length > 0)
  for (const controllerField of controllerFields) {
    // 只取 enabled=true 的规则参与联动判断
    const enabledRules = controllerField.props.linkage.filter(rule => rule && rule.enabled)
    if (enabledRules.length === 0) continue
    // 收集所有启用规则中出现过的字段（被联动控制的字段集合）
    const controlledFields = new Set()
    enabledRules.forEach(rule => {
      if (Array.isArray(rule.showFields)) {
        rule.showFields.forEach(f => controlledFields.add(f))
      }
    })
    // 如果当前字段不在任何启用规则的 showFields 中，则不受此联动控制，直接显示
    if (!controlledFields.has(field.props.field)) continue
    // 当前联动控制组件的值
    const currentValue = formData.value[controllerField.props.field]
    // 找到当前值对应的已启用规则
    const rule = enabledRules.find(item => isSameLinkageValue(item.value, currentValue))
    if (rule) {
      // showFields 为空 → 隐藏所有受控字段；不为空 → 只显示列表中的字段
      if (!Array.isArray(rule.showFields) || !rule.showFields.includes(field.props.field)) {
        return false
      }
    }
    // 当前选中值没有对应的启用规则，受控字段正常显示
  }
  return true
}

// 初始化表单数据
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
        // input / textarea / radio 等：优先使用 defaultValue
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
  formData.value = data
}

// 构建表单验证规则
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
  formRules.value = rules
}

// 提交表单
function handleSubmit() {
  formRef.value.validate(async(valid) => {
    if (!valid) {
      ElMessage.warning('请检查表单填写是否正确')
      return
    }
    submitting.value = true
    try {
      const submitData = { ...formData.value }
      Object.keys(submitData).forEach(key => {
        if (Array.isArray(submitData[key]) && submitData[key].length > 0 && submitData[key][0].raw) {
          submitData[key] = submitData[key].map(file => ({
            name: file.name,
            url: file.url || ''
          }))
        }
      })

      const res = await configSaveApi({
        id: configId.value,
        form_data: submitData
      })
      ElMessage.success(res.message || '提交成功')
    } catch (e) {
      ElMessage.error(e.message || '提交失败')
    } finally {
      submitting.value = false
    }
  })
}

// 重置表单
function handleReset() {
  formRef.value.resetFields()
  ElMessage.success('表单已重置')
}

// 返回配置列表
function handleBack() {
  router.push({ name: 'system_config' })
}

// 复制文本框内容
function handleCopyText(field) {
  const text = formData.value[field.props.field] || ''
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

// 生成随机字符串
function handleGenerateRandom(field) {
  const length = field.props.randomLength || 16
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'
  let result = ''
  for (let i = 0; i < length; i++) {
    result += chars.charAt(Math.floor(Math.random() * chars.length))
  }
  formData.value[field.props.field] = result
  ElMessage.success('随机字符串已生成')
}

function handleCustomUploadShow(file) {
  imageAmplify(file.url)
}

// 上传前验证
function beforeUpload(file) {
  const isLt10M = file.size / 1024 / 1024 < 5
  if (!isLt10M) {
    ElMessage.error('上传文件大小不能超过 5MB!')
    return false
  }
  return true
}

// 上传成功
function handleUploadSuccess(response, file, fileList, field) {
  if (response.status === 200) {
    const uploadedFile = {
      fileName: file.name,
      url: response.data.filePath
    }
    const currentList = formData.value[field.props.field] || []
    const index = currentList.findIndex(item => item.fileName === file.name)
    if (index > -1) {
      currentList[index] = uploadedFile
    } else {
      currentList.push(uploadedFile)
    }
    formData.value[field.props.field] = [...currentList]
    ElMessage.success('上传成功')
  } else {
    ElMessage.error(response.message || '上传失败')
    const currentList = formData.value[field.props.field] || []
    const index = currentList.findIndex(item => item.uid === file.uid)
    if (index > -1) {
      currentList.splice(index, 1)
      formData.value[field.props.field] = [...currentList]
    }
  }
}

// 上传失败
function handleUploadError(err, file, fileList) {
  ElMessage.error('上传失败: ' + err.message)
}

// 判断是否为图片文件
function isImageFile(file) {
  const imageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/svg+xml']
  if (file.type) return imageTypes.includes(file.type)
  const ext = (file.name || file.url || '').split('.').pop().toLowerCase()
  return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'].includes(ext)
}

// 自定义列表删除
function handleCustomUploadRemove(field, index) {
  const list = [...(formData.value[field.props.field] || [])]
  list.splice(index, 1)
  formData.value[field.props.field] = list
}
</script>

<style scoped lang="scss">
.form-render-container {
  display: flex;
  flex-direction: column;
  min-height: 100%;
}

:deep(.btn-curator-primary.el-button--primary) {
  border: none;
  border-radius: 6px;
  color: var(--ds-on-primary);
  font-weight: 500;
  padding-left: 18px;
  padding-right: 18px;
  box-shadow: 0 2px 8px var(--brand-shadow);
}

// 页面头部
.form-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 24px;
  margin-bottom: 20px;
  padding: 0;
  background: transparent;
  border: none;
  box-shadow: none;
  border-radius: 0;

  .header-left {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;

    .header-title {
      font-size: 1.5rem;
      font-weight: 600;
      color: var(--ds-on-surface);
      margin-bottom: 0;
      letter-spacing: 0.02em;
    }

    .header-desc {
      color: var(--ds-on-surface-variant);
      line-height: 1.5;
    }
  }

  .header-right {
    display: flex;
    gap: 8px;
    flex-shrink: 0;
  }
}

// 表单内容区域
.form-body {
  flex: 1;
  overflow-y: auto;
  padding: 0;
  background: var(--ds-surface-card);
  border-radius: 12px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);

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
  padding: 28px 32px 32px;

  .el-form-item {
    margin-bottom: 24px;
  }

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
        border-radius: 6px;
      }
    }
  }

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
      border-radius: 8px;
      overflow: hidden;
      border: 1px solid var(--ds-outline-ghost);
      background: var(--ds-surface-low);
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
          color: var(--text-secondary);
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
            color: var(--text-danger);
          }
        }
      }
    }

    .upload-trigger {
      flex-shrink: 0;

      :deep(.el-upload) {
        display: block;
      }

      .upload-trigger-btn {
        width: 80px;
        height: 80px;
        border: 1px dashed var(--ds-outline);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;

        &:hover {
          border-color: var(--ds-primary);
          background: var(--brand-primary-pale);

          i {
            color: var(--ds-primary);
          }
        }

        i {
          font-size: 24px;
          color: var(--text-placeholder);
        }
      }
    }
  }

  .form-card-item {
    margin-bottom: 24px;
    border-radius: 10px;
    border-color: var(--ds-outline-ghost);
    box-shadow: none;

    :deep(.el-card__header) {
      padding: 14px 20px;
      background: rgba(var(--ds-surface-low-rgb), 0.45);
      border-bottom: 1px solid var(--ds-outline-ghost);
    }

    :deep(.el-card__body) {
      padding: 20px;
    }

    .form-card-item__header {
      font-size: 14px;
      font-weight: 600;
      color: var(--ds-on-surface);
    }

    .card-empty-content {
      color: var(--text-secondary);
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

  .form-tabs-item {
    margin-bottom: 24px;
    background: var(--ds-surface-card);
    border: 1px solid var(--ds-outline-ghost);
    border-radius: 10px;
    overflow: hidden;
    padding: 10px;

    :deep(.el-tabs) {
      .el-tabs__header {
        margin-bottom: 0;
      }

      .el-tabs__item {
        color: var(--ds-on-surface-variant);

        &.is-active {
          color: var(--ds-primary);
          font-weight: 600;
        }
      }

      .el-tabs__active-bar {
        background: var(--ds-primary);
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

  .form-alert-item {
    margin-bottom: 20px;
  }

  .form-field-description {
    margin-top: 6px;
    font-size: 12px;
    color: var(--text-secondary);
    line-height: 1.5;
    display: flex;
    align-items: flex-start;
    gap: 4px;

    i {
      font-size: 13px;
      margin-top: 1px;
      flex-shrink: 0;
      color: var(--text-secondary);
    }
  }

  .form-submit-area {
    margin-top: 32px;
    padding-top: 24px;
    border-top: 1px solid var(--ds-outline-ghost);

    .el-button {
      padding: 10px 24px;
    }
  }
}

@media screen and (max-width: 768px) {
  .form-body {
    .form-card {
      :deep(.el-card__body) {
        padding: 20px;
      }
    }
  }

  .dynamic-form {
    padding: 18px 16px 24px;

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
