<template>
  <common-wrapper :config="config">
    <div class="title-block" :class="`style-${style}`" :style="blockStyle">
      <div class="title-content" :class="`align-${align}`">
        <template v-if="style === 'style1'">
          <div class="title-text" :style="{ color }">{{ text }}</div>
          <div v-if="sub_text" class="title-sub" :style="{ color: sub_color }">{{ sub_text }}</div>
        </template>

        <template v-else-if="style === 'style2'">
          <div class="title-line-group">
            <span class="title-line" />
            <span class="title-text" :style="{ color }">{{ text }}</span>
            <span class="title-line" />
          </div>
          <div v-if="sub_text" class="title-sub" :style="{ color: sub_color }">{{ sub_text }}</div>
        </template>

        <template v-else-if="style === 'style3'">
          <div class="title-icon-group">
            <LegacyIcon v-if="icon" :name="icon" class="title-icon" :style="{ color }" />
            <span class="title-text" :style="{ color }">{{ text }}</span>
          </div>
          <div v-if="sub_text" class="title-sub" :style="{ color: sub_color }">{{ sub_text }}</div>
        </template>

        <template v-else>
          <span class="title-text" :style="{ color }">{{ text }}</span>
        </template>
      </div>
    </div>
  </common-wrapper>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import CommonWrapper from './CommonWrapper'

defineOptions({
  name: 'Title'
})

const props = defineProps({
  config: {
    type: Object,
    default: null
  }
})

const text = ref('标题')
const style = ref('style1')
const align = ref('left')
const color = ref('#333333')
const sub_text = ref('')
const sub_color = ref('#999999')
const icon = ref('')

const blockStyle = computed(() => ({}))

watch(() => props.config, setConfig, { deep: true, immediate: true })

function setConfig(data) {
  if (!data) return
  if (data.title !== undefined || data.text !== undefined || data.style !== undefined) {
    text.value = data.text || data.title || '标题'
    if (data.style === 'normal' || data.style === undefined) {
      style.value = 'style1'
    } else {
      style.value = data.style
    }
    align.value = data.align || 'left'
    color.value = data.color || data.text_color || '#333333'
    sub_text.value = data.sub_text !== undefined ? data.sub_text : (data.subtitle || '')
    sub_color.value = data.sub_color || '#999999'
    icon.value = data.icon || ''
  }
}
</script>

<style scoped lang="scss">
.title-block {
  width: auto;
}

.title-content {
  &.align-left {
    text-align: left;
  }

  &.align-center {
    text-align: center;
  }

  &.align-right {
    text-align: right;
  }
}

.title-text {
  font-size: 16px;
  font-weight: 600;
  color: #333;
  line-height: 1.4;
}

.title-sub {
  font-size: 12px;
  color: #999;
  margin-top: 4px;
}

// style2: 左右装饰线
.title-line-group {
  display: flex;
  align-items: center;
  gap: 12px;

  .title-text {
    flex: 0 0 auto;
  }
}

.title-line {
  flex: 1;
  height: 1px;
  background: linear-gradient(to right, transparent, #ddd, transparent);
}

// style3: 图标+文字
.title-icon-group {
  display: flex;
  align-items: center;
  gap: 6px;

  .title-icon {
    font-size: 18px;
    color: #ac2c00;
  }
}
</style>
