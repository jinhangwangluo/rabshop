<template>
  <div class="rich-text-editor">
    <el-form :model="localConfig" label-width="100px">
      <el-form-item label="组件名称">
        <el-input v-model="localConfig.title" placeholder="富文本" />
      </el-form-item>
      <el-form-item label="内容">
        <div class="editor-wrap">
          <Toolbar
            style="border-bottom: 1px solid #e8e8e8;"
            :editor="editor"
            :default-config="toolbarConfig"
            :mode="editorMode"
          />
          <Editor
            v-model="localConfig.content"
            style="height: 300px; overflow-y: hidden;"
            :default-config="editorConfig"
            :mode="editorMode"
            @onCreated="onEditorCreated"
          />
        </div>
      </el-form-item>
    </el-form>
  </div>
</template>

<script setup>
import { onBeforeUnmount, reactive, ref, watch, nextTick } from 'vue'
import { Editor, Toolbar } from '@wangeditor/editor-for-vue'
import '@wangeditor/editor/dist/css/style.css'

defineOptions({
  name: 'RichTextEditor'
})

const props = defineProps({
  config: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['change'])
const editor = ref(null)
const toolbarConfig = reactive({})
const editorConfig = reactive({ placeholder: '请输入内容...' })
const editorMode = ref('default')
const localConfig = reactive({
  title: '富文本',
  content: ''
})
const fromProp = ref(false)
const lastEmit = ref('')

watch(() => props.config, val => {
  if (!val || Object.keys(val).length === 0) return
  if (lastEmit.value && JSON.stringify(val) === lastEmit.value) return
  fromProp.value = true
  Object.assign(localConfig, {
    title: val.title !== undefined ? val.title : '富文本',
    content: val.content !== undefined ? val.content : ''
  })
  nextTick(() => {
    fromProp.value = false
  })
}, {
  immediate: true
})

watch(localConfig, () => {
  if (fromProp.value) return
  emitConfig()
}, {
  deep: true
})

function onEditorCreated(instance) {
  editor.value = Object.seal(instance)
}

function emitConfig() {
  const cfg = { ...localConfig }
  lastEmit.value = JSON.stringify(cfg)
  emit('change', cfg)
}

function getConfig() {
  return localConfig
}

onBeforeUnmount(() => {
  if (editor.value) {
    editor.value.destroy()
  }
})

defineExpose({
  getConfig
})
</script>

<style lang="scss" scoped>
.rich-text-editor {
  .editor-wrap {
    border: 1px solid #ccc;
    width: 100%;
    max-width: 600px;
  }
}
</style>
