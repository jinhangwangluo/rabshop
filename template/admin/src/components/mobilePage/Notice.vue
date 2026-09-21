<template>
  <common-wrapper :config="config">
    <div class="notice-bar" :style="barStyle">
      <div v-if="showIcon" class="notice-icon">
        <LegacyIcon name="el-icon-volume-notification" />
      </div>
      <div class="notice-content">
        <div v-if="content" class="notice-text" :class="{ scrolling: scroll && content.length > 20 }">
          <span class="text-inner">{{ content }}</span>
        </div>
        <div v-else class="notice-text notice-placeholder">暂无公告</div>
      </div>
      <div v-if="showArrow" class="notice-arrow">
        <LegacyIcon name="el-icon-arrow-right" />
      </div>
    </div>
  </common-wrapper>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import CommonWrapper from './CommonWrapper'

defineOptions({
  name: 'Notice'
})

const props = defineProps({
  config: {
    type: Object,
    default: null
  }
})

const content = ref('')
const bg_color = ref('#fffbe6')
const showIcon = ref(true)
const showArrow = ref(true)
const scroll = ref(true)

const barStyle = computed(() => ({
  background: bg_color.value
}))

watch(() => props.config, setConfig, { deep: true, immediate: true })

function setConfig(data) {
  if (!data) return
  if (data.content !== undefined || data.items !== undefined) {
    if (data.items && Array.isArray(data.items)) {
      content.value = data.items.map(i => i.text || '').filter(Boolean).join(' | ')
    } else {
      content.value = data.content || ''
    }
    bg_color.value = data.bg_color || '#fffbe6'
    showIcon.value = data.show_icon !== undefined ? data.show_icon : true
    showArrow.value = data.show_arrow !== undefined ? data.show_arrow : true
    scroll.value = data.scroll_type !== undefined ? data.scroll_type === 'scroll' : true
  }
}
</script>

<style scoped lang="scss">
.notice-bar {
  display: flex;
  align-items: center;
  padding: 8px 12px;
  border-radius: 4px;
  overflow: hidden;
}

.notice-icon {
  flex-shrink: 0;
  margin-right: 8px;
  color: #ed6a0c;
  font-size: 16px;
}

.notice-content {
  flex: 1;
  overflow: hidden;
}

.notice-text {
  font-size: 13px;
  color: #ed6a0c;
  line-height: 1.4;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;

  &.scrolling {
    white-space: nowrap;
  }
}

.notice-placeholder {
  color: #c0c4cc;
}

.notice-arrow {
  flex-shrink: 0;
  margin-left: 8px;
  color: #c0c4cc;
  font-size: 12px;
}
</style>
