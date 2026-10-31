<template>
  <span class="tag-preview" :class="{ 'tag-preview--image': isImageTag }" :style="tagStyle">
    <img v-if="isImageTag" class="tag-preview__icon" :src="tag.icon" :alt="tag.name || '标签'">
    <span v-else>{{ tag.name || '-' }}</span>
  </span>
</template>

<script setup>
import { computed } from 'vue'

defineOptions({
  name: 'TagPreview'
})

const { tag } = defineProps({
  tag: {
    type: Object,
    default: () => ({})
  }
})

const isImageTag = computed(() => Number(tag.effect_type) === 2 && !!tag.icon)
const tagStyle = computed(() => {
  if (isImageTag.value) {
    return {}
  }
  const style = {}
  if (tag.text_color) {
    style.color = tag.text_color
  }
  if (tag.bg_color) {
    style.backgroundColor = tag.bg_color
  }
  if (tag.border_color) {
    style.borderColor = tag.border_color
  }
  return style
})
</script>

<style lang="scss" scoped>
.tag-preview {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 44px;
  min-height: 24px;
  padding: 2px 10px;
  border: 1px solid var(--prev-border-color-light, #ebeef5);
  border-radius: 14px;
  background: var(--prev-bg-white, #fff);
  color: var(--prev-color-text-regular, #606266);
  font-size: 13px;
  line-height: 18px;
  box-sizing: border-box;
  white-space: nowrap;

  &--image {
    min-width: auto;
    padding: 0;
    border-color: transparent;
    background: transparent;
  }

  &__icon {
    display: block;
    max-width: 72px;
    max-height: 24px;
  }
}
</style>
