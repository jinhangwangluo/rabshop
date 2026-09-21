<template>
  <div class="about-page">
    <div class="about-page__header">
      <div>
        <h1 class="about-page__title">关于我们</h1>
        <p class="about-page__subtitle">维护移动端关于我们页面的富文本内容</p>
      </div>
      <el-button
        type="primary"
        icon="el-icon-check"
        :loading="submitLoading"
        @click="handleSave"
      >
        保存内容
      </el-button>
    </div>

    <el-card shadow="never" class="about-page__card">
      <div
        v-loading="loading"
        class="about-workspace"
      >
        <div class="about-workspace__editor">
          <div class="about-panel-header">
            <div>
              <div class="about-panel-header__title">富文本编辑</div>
              <div class="about-panel-header__meta">
                <span v-if="updateTime">最近更新：{{ updateTime }}</span>
                <span v-else>尚未保存内容</span>
              </div>
            </div>
          </div>

          <div class="about-editor">
            <Toolbar
              class="about-editor__toolbar"
              :editor="editor"
              :default-config="toolbarConfig"
              :mode="editorMode"
            />
            <Editor
              v-model="content"
              class="about-editor__body"
              :default-config="editorConfig"
              :mode="editorMode"
              @onCreated="onEditorCreated"
            />
          </div>
        </div>

        <aside class="about-preview">
          <div class="about-preview__heading">
            <span>移动端预览</span>
            <span class="about-preview__status">
              <i class="about-preview__status-dot" />
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
              <span>关于我们</span>
              <LegacyIcon name="el-icon-more" />
            </div>
            <div class="phone-content">
              <div class="about-phone-content">
                <div
                  v-if="hasContent"
                  class="about-phone-content__rich-text"
                  v-html="content"
                />
                <div v-else class="about-phone-content__empty">
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
import { getAboutSchema, saveAboutContent } from '@/api/about'

defineOptions({
  name: 'AboutSetting'
})

const { proxy } = getCurrentInstance()
const message = proxy.$message
const imgModal = proxy.$imgModal

const loading = ref(false)
const submitLoading = ref(false)
const content = ref('')
const updateTime = ref('')
const editor = shallowRef(null)
const editorMode = ref('default')
const toolbarConfig = reactive({})
const editorConfig = reactive({
  placeholder: '请输入关于我们的内容...',
  MENU_CONF: {
    uploadImage: {
      customBrowseAndUpload: insertFn => {
        handleEditorImageSelect(insertFn)
      }
    }
  }
})

const hasContent = computed(() => {
  const value = content.value || ''
  return value.replace(/<[^>]*>/g, '').replace(/&nbsp;|\s/g, '').length > 0 ||
    /<(img|table|ul|ol)\b/i.test(value)
})

function fetchSchema() {
  loading.value = true
  getAboutSchema()
    .then(res => {
      const data = res.data || {}
      content.value = String(data.content || '')
      updateTime.value = String(data.update_time || '')
    })
    .catch(err => {
      message.error(err.message || '获取关于我们配置失败')
    })
    .finally(() => {
      loading.value = false
    })
}

function handleSave() {
  submitLoading.value = true
  saveAboutContent({
    content: content.value || ''
  })
    .then(res => {
      const saved = res.data || {}
      content.value = String(saved.content || '')
      updateTime.value = String(saved.update_time || '')
      message.success(res.message || '保存成功')
    })
    .catch(err => {
      message.error(err.message || '保存失败')
    })
    .finally(() => {
      submitLoading.value = false
    })
}

function onEditorCreated(nextEditor) {
  editor.value = Object.seal(nextEditor)
}

function destroyEditor() {
  if (editor.value) {
    editor.value.destroy()
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
.about-page {
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

.about-workspace {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 320px;
  align-items: start;
  gap: 24px;

  &__editor {
    min-width: 0;
  }
}

.about-panel-header {
  display: flex;
  align-items: center;
  min-height: 44px;
  margin-bottom: 12px;

  &__title {
    color: var(--prev-color-text-primary);
    font-size: 16px;
    font-weight: 600;
    line-height: 24px;
  }

  &__meta {
    margin-top: 4px;
    color: var(--prev-color-text-secondary);
    font-size: 12px;
    line-height: 18px;
  }
}

.about-editor {
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

.about-preview {
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

.about-phone-content {
  box-sizing: border-box;
  min-height: 100%;
  padding: 18px 16px 34px;
  color: #1a1c1c;

  &__rich-text {
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

    :deep(table) {
      width: 100%;
      border-collapse: collapse;
    }

    :deep(td),
    :deep(th) {
      padding: 4px;
      border: 1px solid #e5e7eb;
    }
  }

  &__empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 360px;
    color: #a1a1aa;
    font-size: 12px;

    i {
      margin-bottom: 10px;
      font-size: 28px;
    }
  }
}

@media screen and (max-width: 1180px) {
  .about-workspace {
    grid-template-columns: minmax(0, 1fr);
  }

  .about-preview {
    justify-self: center;
  }
}
</style>
