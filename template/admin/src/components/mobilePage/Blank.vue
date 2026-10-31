<template>
  <common-wrapper :config="config">
    <div class="blank-block" :style="blockStyle">
      <div v-if="hasHeight" class="blank-placeholder blank-placeholder--compact">
        <span>空白 {{ height }}px</span>
      </div>
      <div v-else class="blank-placeholder">
        <LegacyIcon name="el-icon-minus" />
        <span>空白组件</span>
      </div>
    </div>
  </common-wrapper>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import CommonWrapper from './CommonWrapper'

defineOptions({
  name: 'Blank'
})

const props = defineProps({
  config: {
    type: Object,
    default: null
  }
})

const height = ref(0)
const hasHeight = computed(() => Number(height.value) > 0)
const blockStyle = computed(() => {
  if (!hasHeight.value) return {}
  return {
    height: `${height.value}px`
  }
})

watch(() => props.config, setConfig, { deep: true, immediate: true })

function setConfig(data) {
  if (!data) return
  if (data.height !== undefined) {
    const nextHeight = Number(data.height)
    height.value = Number.isNaN(nextHeight) ? 0 : nextHeight
  }
}
</script>

<style scoped lang="scss">
.blank-block {
  width: auto;
  border: 1px dashed #dcdfe6;
  border-radius: 4px;
  background: repeating-linear-gradient(
    135deg,
    rgba(192, 196, 204, 0.12) 0,
    rgba(192, 196, 204, 0.12) 8px,
    rgba(255, 255, 255, 0.9) 8px,
    rgba(255, 255, 255, 0.9) 16px
  );
  box-sizing: border-box;
  overflow: hidden;
}

.blank-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 18px 0;
  color: #909399;
  font-size: 12px;

  i {
    font-size: 18px;
  }

  &--compact {
    flex-direction: row;
    gap: 0;
    height: 100%;
    padding: 0 8px;
    font-size: 11px;
    white-space: nowrap;
  }
}
</style>
