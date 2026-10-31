<template>
  <div :id="id" />
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

// deps for editor
import 'codemirror/lib/codemirror.css'
import 'tui-editor/dist/tui-editor.css'
import 'tui-editor/dist/tui-editor-contents.css'

import Editor from 'tui-editor'
import defaultOptions from './default-options'

defineOptions({
  name: 'MarkdownEditor'
})

const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  },
  id: {
    type: String,
    required: false,
    default() {
      return 'markdown-editor-' + +new Date() + ((Math.random() * 1000).toFixed(0) + '')
    }
  },
  options: {
    type: Object,
    default() {
      return defaultOptions
    }
  },
  mode: {
    type: String,
    default: 'markdown'
  },
  height: {
    type: String,
    required: false,
    default: '300px'
  },
  language: {
    type: String,
    required: false,
    default: 'en_US'
  }
})

const emit = defineEmits(['update:modelValue'])
const editor = ref(null)

const editorOptions = computed(() => {
  const options = Object.assign({}, defaultOptions, props.options)
  options.initialEditType = props.mode
  options.height = props.height
  options.language = props.language
  return options
})

function handleChange() {
  if (!editor.value) return
  emit('update:modelValue', editor.value.getValue())
}

function initEditor() {
  const el = document.getElementById(props.id)
  if (!el) return

  editor.value = new Editor({
    el,
    ...editorOptions.value
  })
  if (props.modelValue) {
    editor.value.setValue(props.modelValue)
  }
  editor.value.on('change', handleChange)
}

function destroyEditor() {
  if (!editor.value) return
  editor.value.off('change')
  editor.value.remove()
  editor.value = null
}

function setValue(value) {
  if (!editor.value) return
  editor.value.setValue(value)
}

function getValue() {
  return editor.value ? editor.value.getValue() : ''
}

function setHtml(value) {
  if (!editor.value) return
  editor.value.setHtml(value)
}

function getHtml() {
  return editor.value ? editor.value.getHtml() : ''
}

watch(() => props.modelValue, (newValue, preValue) => {
  if (editor.value && newValue !== preValue && newValue !== editor.value.getValue()) {
    editor.value.setValue(newValue)
  }
})

watch(() => props.language, () => {
  destroyEditor()
  initEditor()
})

watch(() => props.height, (newValue) => {
  if (editor.value) {
    editor.value.height(newValue)
  }
})

watch(() => props.mode, (newValue) => {
  if (editor.value) {
    editor.value.changeMode(newValue)
  }
})

onMounted(() => {
  initEditor()
})

onBeforeUnmount(() => {
  destroyEditor()
})

defineExpose({
  setValue,
  getValue,
  setHtml,
  getHtml
})
</script>
