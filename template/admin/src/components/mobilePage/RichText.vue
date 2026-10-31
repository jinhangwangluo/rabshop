<template>
  <common-wrapper :config="config">
    <div class="rich-text">
      <div v-if="!hasContent" class="rich-empty">
        <LegacyIcon name="el-icon-document" />
        <span>富文本内容</span>
      </div>
      <div v-else class="rich-content" v-html="sanitizedContent" />
    </div>
  </common-wrapper>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import CommonWrapper from './CommonWrapper'

defineOptions({
  name: 'RichText'
})

const props = defineProps({
  config: {
    type: Object,
    default: null
  }
})

const content = ref('')

const sanitizedContent = computed(() => content.value || '')
const hasContent = computed(() => hasMeaningfulContent(sanitizedContent.value))

watch(() => props.config, setConfig, { deep: true, immediate: true })

function setConfig(data) {
  if (!data) return
  if (data.content !== undefined) {
    content.value = data.content || ''
  }
}

function hasMeaningfulContent(html) {
  if (!html) return false
  if (/<(img|video|audio|iframe|table|ul|ol|blockquote)\b/i.test(html)) {
    return true
  }
  const pureText = html
    .replace(/<style[\s\S]*?<\/style>/gi, '')
    .replace(/<script[\s\S]*?<\/script>/gi, '')
    .replace(/<[^>]+>/g, '')
    .replace(/&nbsp;|&#160;/gi, '')
    .trim()
  return pureText.length > 0
}
</script>

<style scoped lang="scss">
.rich-text {
  width: auto;
}

.rich-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 24px 0;
  border: 1px dashed #dcdfe6;
  border-radius: 6px;
  background: #fafafa;
  color: #c0c4cc;
  font-size: 12px;

  i {
    font-size: 28px;
    margin-bottom: 8px;
  }
}

.rich-content {
  font-size: 14px;
  color: #333;
  line-height: 1.6;
  word-break: break-word;

  :deep(img) {
      max-width: 100%;
      height: auto;
      display: block;
      margin: 8px 0;
      border-radius: 4px;
  }

  :deep(p) {
      margin: 8px 0;
  }

  :deep(table) {
      width: 100%;
      border-collapse: collapse;
      margin: 8px 0;

      td, th {
        border: 1px solid #eee;
        padding: 6px 8px;
        font-size: 13px;
      }
  }

  :deep(a) {
      color: #409eff;
      text-decoration: underline;
  }

  :deep(h1, h2, h3, h4, h5, h6) {
    margin: 12px 0 6px;
    font-weight: 600;
  }

  :deep(ul, ol) {
    padding-left: 20px;
  }

  :deep(blockquote) {
    border-left: 3px solid #ddd;
    padding-left: 12px;
    color: #666;
    margin: 8px 0;
  }
}
</style>
