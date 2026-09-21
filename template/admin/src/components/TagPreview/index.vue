<template>
  <span
    class="tag-preview"
    :class="{ 'tag-preview--image': isImageTag, 'tag-preview--small': size === 'small' }"
    :style="tagStyle"
  >
    <img v-if="isImageTag" class="tag-preview__icon" :src="tag.icon" :alt="tag.name || '标签'">
    <span v-else>{{ tag.name || '-' }}</span>
  </span>
</template>

<script setup>
import { computed } from 'vue'

defineOptions({
  name: 'TagPreview'
})

const { tag, size } = defineProps({
  tag: {
    type: Object,
    default: () => ({})
  },
  size: {
    type: String,
    default: 'medium'
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
  min-width: 30px;
  min-height: 18px;
  padding: 2px 5px;
  border: 1px solid var(--prev-border-color-light, #ebeef5);
  border-radius: 14px;
  background: var(--prev-bg-white, #fff);
  color: var(--prev-color-text-regular, #606266);
  font-size: 10px;
  line-height: 18px;
  box-sizing: border-box;
  white-space: nowrap;

  &--image {
    min-width: auto;
    padding: 0;
    border-color: transparent;
    background: transparent;
  }

  &--small {
    min-width: 0;
    min-height: 18px;
    padding: 0 6px;
    border-radius: 4px;
    font-size: 10px;
    line-height: 16px;
  }

  &__icon {
    display: block;
    max-width: 72px;
    max-height: 24px;
  }
}

.tag-preview--small .tag-preview__icon {
  max-width: 66px;
  max-height: 18px;
}
</style>
