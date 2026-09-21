<template>
  <div class="agreement-page">
    <div class="agreement-page__header">
      <div>
        <h1 class="agreement-page__title">协议管理</h1>
        <p class="agreement-page__subtitle">维护平台固定协议内容，标题与类型由后端枚举统一输出</p>
      </div>
      <el-button
        type="primary"
        icon="el-icon-check"
        :loading="submitLoading"
        @click="handleSave"
      >
        保存当前协议
      </el-button>
    </div>

    <el-card shadow="never" class="agreement-page__card">
      <el-tabs
        v-model="activeType"
        type="card"
        class="agreement-tabs"
      >
        <el-tab-pane
          v-for="item in agreements"
          :key="item.type"
          :label="item.title"
          :name="item.type"
        />
      </el-tabs>

      <div
        v-loading="loading"
        class="agreement-workspace"
      >
        <div class="agreement-workspace__editor">
          <div class="agreement-panel-header">
            <div>
              <div class="agreement-panel-header__title">{{ activeAgreement.title || '协议内容' }}</div>
              <div class="agreement-panel-header__meta">
                <span>协议类型：{{ activeAgreement.type || '-' }}</span>
                <span v-if="activeAgreement.update_time">最近更新：{{ activeAgreement.update_time }}</span>
              </div>
            </div>
          </div>

          <div class="agreement-editor">
            <!-- 切换协议类型时通过 key 销毁重建编辑器，避免 wangEditor 单实例跨 tab 复用导致内容串档、丢失及选区报错 -->
            <Toolbar
              :key="activeType"
              class="agreement-editor__toolbar"
              :editor="editor"
              :default-config="toolbarConfig"
              :mode="editorMode"
            />
            <Editor
              v-if="activeType"
              :key="activeType"
              v-model="activeContent"
              class="agreement-editor__body"
              :default-config="editorConfig"
              :mode="editorMode"
              @onCreated="onEditorCreated"
              @vue:beforeUnmount="destroyEditor"
            />
          </div>
        </div>

        <aside class="agreement-preview">
          <div class="agreement-preview__heading">
            <span>协议预览</span>
            <span class="agreement-preview__status">
              <i class="agreement-preview__status-dot" />
              实时预览
            </span>
          </div>
          <div class="phone-frame">
            <div class="phone-frame__speaker" />
            <div class="phone-status-bar">
              <span>9:41</span>
              <div class="phone-status-bar__icons">
                <LegacyIcon name="el-icon-signal" />
                <LegacyIcon name="el-icon-wifi" />
                <LegacyIcon name="el-icon-battery-full" />
              </div>
            </div>
            <div class="phone-nav-bar">
              <LegacyIcon name="el-icon-arrow-left" />
              <span>{{ activeAgreement.title || '协议详情' }}</span>
              <LegacyIcon name="el-icon-more" />
            </div>
            <div class="phone-content">
              <div class="phone-agreement">
                <div
                  v-if="hasActiveContent"
                  class="phone-agreement__content"
                  v-html="activeContent"
                />
                <div v-else class="phone-agreement__empty">
                  <LegacyIcon name="el-icon-document" />
                  <span>编辑富文本后将在此处预览</span>
                </div>
              </div>
            </div>
            <div class="phone-frame__home-indicator" />
          </div>
        </aside>
      </div>
    </el-card>
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, onBeforeUnmount, reactive, ref, shallowRef } from 'vue'
import { Editor, Toolbar } from '@wangeditor/editor-for-vue'
import '@wangeditor/editor/dist/css/style.css'
import { getAgreementSchema, saveAgreementContent } from '@/api/agreement'

defineOptions({
  name: 'AgreementSetting'
})

const { proxy } = getCurrentInstance()
const message = proxy.$message
const imgModal = proxy.$imgModal

const loading = ref(false)
const submitLoading = ref(false)
const activeType = ref('')
const agreements = ref([])
const editor = shallowRef(null)
const editorMode = ref('default')
const toolbarConfig = reactive({})
const editorConfig = reactive({
  placeholder: '请输入协议内容...',
  MENU_CONF: {
    uploadImage: {
      customBrowseAndUpload: insertFn => {
        handleEditorImageSelect(insertFn)
      }
    }
  }
})

const activeAgreement = computed(() => agreements.value.find(item => item.type === activeType.value) || {})
const activeContent = computed({
  get() {
    return activeAgreement.value.content || ''
  },
  set(value) {
    const index = agreements.value.findIndex(item => item.type === activeType.value)
    if (index >= 0) {
      agreements.value[index].content = value
    }
  }
})
const hasActiveContent = computed(() => {
  const content = activeContent.value || ''
  return content
    .replace(/<[^>]*>/g, '')
    .replace(/&nbsp;|\s/g, '')
    .length > 0 || /<(img|video|audio|table)\b/i.test(content)
})

function fetchSchema() {
  loading.value = true
  getAgreementSchema()
    .then(res => {
      const data = res.data || {}
      const list = Array.isArray(data.agreements) ? data.agreements : []
      agreements.value = list.map(item => ({
        id: Number(item.id || 0),
        type: String(item.type || ''),
        title: String(item.title || ''),
        content: String(item.content || ''),
        update_time: String(item.update_time || '')
      })).filter(item => item.type && item.title)
      ensureActiveAgreement()
    })
    .catch(err => {
      message.error(err.message || '获取协议配置失败')
    })
    .finally(() => {
      loading.value = false
    })
}

function ensureActiveAgreement() {
  if (!agreements.value.length) {
    activeType.value = ''
    return
  }

  const hasActiveType = agreements.value.some(item => item.type === activeType.value)
  if (!activeType.value || !hasActiveType) {
    activeType.value = agreements.value[0].type
  }
}

function handleSave() {
  if (!activeAgreement.value.type) {
    message.warning('请选择协议类型')
    return
  }
  if (!hasActiveContent.value) {
    message.warning('请输入协议内容')
    return
  }

  submitLoading.value = true
  saveAgreementContent({
    type: activeAgreement.value.type,
    content: activeContent.value
  })
    .then(res => {
      const saved = res.data || {}
      message.success(res.message || '保存成功')
      mergeSavedAgreement(saved)
    })
    .catch(err => {
      message.error(err.message || '保存失败')
    })
    .finally(() => {
      submitLoading.value = false
    })
}

function mergeSavedAgreement(saved) {
  if (!saved.type) {
    return
  }
  const index = agreements.value.findIndex(item => item.type === saved.type)
  if (index < 0) {
    return
  }
  agreements.value[index] = {
    ...agreements.value[index],
    id: Number(saved.id || agreements.value[index].id || 0),
    title: String(saved.title || agreements.value[index].title || ''),
    content: String(saved.content || ''),
    update_time: String(saved.update_time || agreements.value[index].update_time || '')
  }
}

function onEditorCreated(nextEditor) {
  editor.value = Object.seal(nextEditor)
}

function destroyEditor() {
  const currentEditor = editor.value
  if (currentEditor) {
    currentEditor.destroy()
    editor.value = null
  }
}

function handleEditorImageSelect(insertFn) {
  if (typeof insertFn !== 'function') return
  imgModal((image) => {
    const imageList = Array.isArray(image) ? image : (image ? [image] : [])
    imageList.forEach(item => {
      const url = item && (item.path || item.url || item.att_dir || item.src)
      if (url) {
        insertFn(url, item.real_name || item.name || '', url)
      }
    })
  }, 20)
}

fetchSchema()

onBeforeUnmount(() => {
  destroyEditor()
})
</script>

<style lang="scss" scoped>
.agreement-page {
  box-sizing: border-box;

  &__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
  }

  &__title {
    margin: 0;
    color: var(--prev-color-text-primary);
    font-size: 22px;
    font-weight: 600;
    line-height: 32px;
  }

  &__subtitle {
    margin: 6px 0 0;
    color: var(--prev-color-text-secondary);
    font-size: 13px;
    line-height: 20px;
  }

  &__card {
    border: 0;
  }
}

.agreement-tabs {
  margin-bottom: 18px;

  :deep(.el-tabs__header) {
    margin-bottom: 0;
  }
}

.agreement-workspace {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 304px;
  align-items: start;
  gap: 24px;

  &__editor {
    min-width: 0;
  }
}

.agreement-panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 44px;
  margin-bottom: 12px;

  &__title {
    color: var(--prev-color-text-primary);
    font-size: 16px;
    font-weight: 600;
    line-height: 24px;
  }

  &__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 4px;
    color: var(--prev-color-text-secondary);
    font-size: 12px;
    line-height: 18px;
  }
}

.agreement-editor {
  overflow: hidden;
  border: 1px solid var(--dashboard-border, #dcdfe6);
  border-radius: 6px;

  &__toolbar {
    border-bottom: 1px solid var(--dashboard-border, #ebeef5);
  }

  &__body {
    height: 560px;
    overflow-y: hidden;
  }

  :deep(.w-e-text-container [data-slate-editor]) {
    min-height: 520px;
  }
}

.agreement-preview {
  width: 320px;
  padding: 8px;

  &__heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
    color: var(--prev-color-text-primary);
    font-size: 13px;
    font-weight: 600;
    line-height: 20px;
  }

  &__status {
    display: inline-flex;
    align-items: center;
    color: var(--prev-color-text-secondary);
    font-size: 12px;
    font-weight: 400;
  }

  &__status-dot {
    width: 6px;
    height: 6px;
    margin-right: 5px;
    border-radius: 50%;
    background: #35b46f;
  }
}

.phone-frame {
  position: relative;
  box-sizing: border-box;
  width: 304px;
  height: 600px;
  padding: 8px;
  overflow: hidden;
  border: 1px solid #111111;
  border-radius: 30px;
  background: #111111;
  box-shadow: 0 12px 28px rgba(17, 17, 17, 0.16);

  &__speaker {
    position: absolute;
    top: 12px;
    left: 50%;
    z-index: 2;
    width: 46px;
    height: 4px;
    border-radius: 2px;
    background: #303030;
    transform: translateX(-50%);
  }

  &__home-indicator {
    position: absolute;
    bottom: 12px;
    left: 50%;
    z-index: 2;
    width: 86px;
    height: 4px;
    border-radius: 2px;
    background: #111111;
    transform: translateX(-50%);
  }
}

.phone-status-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  box-sizing: border-box;
  height: 26px;
  padding: 4px 13px 0;
  border-radius: 22px 22px 0 0;
  color: #1a1c1c;
  background: #ffffff;
  font-size: 10px;
  font-weight: 600;

  &__icons {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 10px;
  }
}

.phone-nav-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  box-sizing: border-box;
  height: 42px;
  padding: 0 13px;
  color: #1a1c1c;
  background: #ffffff;
  font-size: 13px;
  font-weight: 600;

  span {
    max-width: 220px;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
  }
}

.phone-content {
  height: calc(100% - 68px);
  overflow-y: auto;
  border-radius: 0 0 22px 22px;
  background: #ffffff;
  scrollbar-width: thin;

  &::-webkit-scrollbar {
    width: 3px;
  }

  &::-webkit-scrollbar-thumb {
    border-radius: 2px;
    background: #d8d8dc;
  }
}

.phone-agreement {
  box-sizing: border-box;
  min-height: 100%;
  padding: 18px 16px 34px;
  color: #1a1c1c;

  &__title {
    margin-bottom: 16px;
    overflow-wrap: anywhere;
    font-size: 18px;
    font-weight: 700;
    line-height: 1.45;
    text-align: center;
  }

  &__content {
    overflow-wrap: anywhere;
    color: #3f454a;
    font-size: 14px;
    line-height: 1.78;

    :deep(img) {
      max-width: 100%;
      height: auto;
    }

    :deep(p) {
      margin: 0 0 10px;
    }
  }

  &__empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 280px;
    color: #9aa0a6;
    font-size: 13px;

    i {
      margin-bottom: 10px;
      font-size: 28px;
    }
  }
}

@media (max-width: 1180px) {
  .agreement-workspace {
    grid-template-columns: minmax(0, 1fr);
  }

  .agreement-preview {
    width: 100%;
  }
}
</style>
